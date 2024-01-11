<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the
	system are installed.
*/

$GLOBALS['gPageCode'] = "SSOCALLBACK";
require_once "shared/startup.inc";

class SsoCallbackPage extends Page {

    private function getServerHttpVariables(): string {
        $logEntry = "Server HTTP variables:\n";
        foreach ($_SERVER as $key=>$value) {
            if(startsWith($key,"HTTP_")) {
                $logEntry .= $key . ": " . $value . "\n";
            }
        }
        return $logEntry;
    }

	function mainContent() {
		$logging = !empty(getPreference("LOG_SSO"));
		$_GET = array_merge($_GET, $_POST);
		$returnedStateArray = array();
		if (!empty($_GET['state'])) {
			parse_str($_GET['state'], $returnedStateArray);
		}
		$loginProviderCredentialId = $returnedStateArray['credential_id'] ?: $_COOKIE['login_provider_credential_id'];
		$ssoProvider = OidcProvider::getOidcProviderById($loginProviderCredentialId);
		if (!empty($_GET['error_description'])) {
			echo $_GET['error_description'];
			if ($logging) {
				addDebugLog("SSO login failed because an error occurred: " . $_GET['error_description']);
			}
            addSecurityLog("","SSO_FAILURE", "Single Sign-on Login failed because an error occurred:" . $_GET['error_description']);

			return;
		}
		if (empty($_GET)) { // If code was returned as fragment instead of query string, resend it
			?>
            <script>
                if (location.href.includes("#")) {
                    window.location = location.href.replace("#", "?");
                }
            </script>
			<?php
			return;
		}
		if (!is_a($ssoProvider, "OidcProvider") || ($checkResponse = $ssoProvider->checkResponse($_GET, $returnedStateArray)) === false) {
			header("Location: /");
			if ($logging) {
				addDebugLog("SSO login failed because the returned state array did not pass checkResponse: " . jsonEncode($_GET) . "\n\n" . $this->getServerHttpVariables());
			}
            addSecurityLog("","SSO_FAILURE", "Single Sign-on Login failed because the returned state array did not pass checkResponse.\n\n" . $this->getServerHttpVariables());
			exit;
		}
		if ($checkResponse !== true) {
			// $_SERVER['SCRIPT_URI'] behaves differently in different browsers, so it is not reliable to use for the redirect
			$redirect = "https://" . $checkResponse . $_SERVER['REQUEST_URI'];
			if ($logging) {
				addDebugLog("SSO login redirecting to original server: " . $redirect);
			}
			header("Location: " . $redirect);
			exit;
		}
		$goToUrl = "/";
		if (!empty($returnedStateArray['url']) || !empty($returnedStateArray['referrer'])) {
			$goToUrl = $returnedStateArray['url'] ?: $returnedStateArray['referrer'];
		}

		$result = $ssoProvider->processUserInfo($_GET);
		if (!$result) {
			echo $ssoProvider->getErrorMessage() ?: "Unknown error occurred.";
			if ($GLOBALS['gDevelopmentServer']) {
				echo jsonEncode($_GET);
			}
			if ($logging) {
				addDebugLog("SSO login failed because processUserInfo returned an error: " . $ssoProvider->getErrorMessage() ?: "Unknown error occurred.");
			}
            addSecurityLog("","SSO_FAILURE", "Single Sign-on Login failed because processUserInfo returned an error: " . $ssoProvider->getErrorMessage() ?: "Unknown error occurred.");
			return;
		}
        $loginProviderName = getFieldFromId("description", "login_providers", "class_name", get_class($ssoProvider));
		$password = "SSO_" . md5($result['sub']);
		$passwordSalt = "SSO_" . md5($result['iss']);
		$usersTable = new DataTable('users');
		$usersTable->setSaveOnlyPresent(true);
		$existingUserRow = getRowFromId("users", "password", $password, "(client_id = ? or superuser_flag = 1) and password_salt = ?",
			$GLOBALS['gClientId'], $passwordSalt);
		if (empty($existingUserRow)) {
			$attempt = 1;
			while ($attempt < 5) {
				switch ($attempt) {
					case 1:
						$userResult = executeQuery("select * from users where user_name = ? " .
							"and (client_id = ? or superuser_flag = 1) and contact_id in (select primary_identifier from custom_field_data " .
							"where custom_field_id in (select custom_field_id from custom_fields where custom_field_code = 'SSO_LOGIN_PROVIDER') and text_data = ?)",
							$result['user_name'], $GLOBALS['gClientId'], $loginProviderName);
						break;
					case 2:
						$userResult = executeQuery("select * from users where contact_id in (select contact_id from contacts where email_address = ?) " .
							"and (client_id = ? or superuser_flag = 1) and contact_id in (select primary_identifier from custom_field_data " .
							"where custom_field_id in (select custom_field_id from custom_fields where custom_field_code = 'SSO_LOGIN_PROVIDER') and text_data = ?)",
							$result['email'], $GLOBALS['gClientId'], $loginProviderName);
						break;
					case 3:
						$userResult = executeQuery("select * from users where user_name = ? " .
							"and (client_id = ? or superuser_flag = 1) and contact_id in (select primary_identifier from custom_field_data " .
							"where custom_field_id in (select custom_field_id from custom_fields where custom_field_code = 'SSO_LINK_USER') and text_data = '1')",
							$result['user_name'], $GLOBALS['gClientId']);
						break;
					default:
						$userResult = executeQuery("select * from users where contact_id in (select contact_id from contacts where email_address = ?) " .
							"and (client_id = ? or superuser_flag = 1) and contact_id in (select primary_identifier from custom_field_data " .
							"where custom_field_id in (select custom_field_id from custom_fields where custom_field_code = 'SSO_LINK_USER') and text_data = '1')",
							$result['email'], $GLOBALS['gClientId']);
						break;
				}
				if ($existingUserRow = getNextRow($userResult)) {
					break;
				}
				$attempt++;
			}
			if (!empty($existingUserRow)) {
				$nameValues = array(
					"password" => $password,
					"password_salt" => $passwordSalt,
					"last_password_change" => date("Y-m-d H:i:s"),
					"force_password_change" => 0);
				$usersTable->saveRecord(["name_values" => $nameValues, "primary_id" => $existingUserRow['user_id']]);
				executeQuery("delete from custom_field_data where primary_identifier = ? and custom_field_id in (select custom_field_id from custom_fields " .
					"where custom_field_code = 'SSO_LINK_USER') and text_data = '1'", $existingUserRow['contact_id']);
			}
		}
		if (empty($existingUserRow)) {
			if (!$ssoProvider->createUsersFromLogin()) {
                addSecurityLog("","SSO_FAILURE", "Login failed because SSO user '" . $result['user_name'] . "' was not found and Allow Create Users is not enabled.");
				echo "You are not permitted to log into this system. Please contact your site administrator.";
				return;
			}
			$contactsTable = new DataTable('contacts');
			$countryArray = getCountryArray();
			$existingContactRow = getRowFromId("contacts", "email_address", $result['email'],
				"contact_id not in (select contact_id from users union select contact_id from locations union select contact_id from federal_firearms_licensees union select contact_id from clients)");
			if (empty($existingContactRow)) {
				$nameValues = array("client_id" => $GLOBALS['gClientId'],
					"date_created" => date("Y-m-d"),
					"first_name" => $result['given_name'],
					"last_name" => $result['family_name'],
					"email_address" => $result['email']);
				if (!empty($result['address'])) {
					$address = json_decode($result['address'], true);
					$streetAddress = explode("\n", $address['street_address'], 2);
					$nameValues['address_1'] = $streetAddress[0];
					$nameValues['address_2'] = $streetAddress[1];
					$nameValues['city'] = $address['locality'];
					$nameValues['state'] = $address['region'];
					$nameValues['postal_code'] = $address['postal_code'];
					$nameValues['country_id'] = array_search($address['country'], $countryArray);
				}
				$nameValues['country_id'] = $nameValues['country_id'] ?: 1000;
				if (!empty($result['picture']) && startsWith($result['picture'], "http")) {
					$imageContents = file_get_contents(trim($result['picture']));
					if (!empty($imageContents) && strpos($imageContents, "<body") === false && strpos($imageContents, "</body>") === false && strpos($imageContents, "</html>") === false) {
						$extension = (substr($result['picture'], -3) == "png" ? "png" : "jpg");
						$nameValues['image_id'] = createImage(array("extension" => $extension, "file_content" => $imageContents, "name" => makeCode($result['name']) . ".jpg", "description" => $result['name']));
					}
				}
				$contactId = $contactsTable->saveRecord(["name_values" => $nameValues]);
				if (!empty($result['phone_number'])) {
					executeQuery("insert into phone_numbers (contact_id, phone_number, description) values (?,?,'Primary')", $contactId, formatPhoneNumber($result['phone_number']));
				}
			} else {
				$contactId = $existingContactRow['contact_id'];
			}
			$userNameNumber = 0;
			$userName = $result['user_name'];
			while (!empty(getRowFromId("users", "user_name", $userName, "superuser_flag = 1 or client_id = ?", $GLOBALS['gClientId']))) {
				$userName = $result['user_name'] . "_" . ++$userNameNumber;
			}
			$nameValues = array("client_id" => $GLOBALS['gClientId'],
				"date_created" => date("Y-m-d"),
				"contact_id" => $contactId,
				"user_name" => $userName,
				"password" => $password,
				"password_salt" => $passwordSalt,
				"last_password_change" => date("Y-m-d H:i:s"));
			$loggedInUserId = $usersTable->saveRecord(["name_values" => $nameValues]);
		} else {
			if (!empty($existingUserRow['inactive'])) {
                addSecurityLog($existingUserRow['user_name'], "SSO_FAILURE", "User attempted Single Sign-on login for inactive account");
				echo getSystemMessage("inactive_account", "Your account is inactive, please contact your site administrator.");
				return;
			}
			if (!empty($existingUserRow['locked'])) {
                addSecurityLog($existingUserRow['user_name'], "LOCKED", "User attempted Single Sign-on login for locked account");
                echo getSystemMessage("locked", "Your account is locked. This can happen because of unsuccessful login attempts or if you haven't confirmed your email address.");
				return;
			}

			executeQuery("update users set force_password_change = 0 where user_id = ?", $existingUserRow['user_id']);
			$loggedInUserId = $existingUserRow['user_id'];
            $userName = $existingUserRow['user_name'];
		}

		setCoreCookie("LAST_LOGIN_PROVIDER", get_class($ssoProvider), 168);
		$_SESSION = array();
		saveSessionData();
		login($loggedInUserId);
        $securityLogSet = executeQuery("select * from security_log where user_name = ? and entry_time > date_sub(now(), interval 30 minute) order by log_id desc", $result['user_name']);
        $recentLoginCount = 0;
        while($securityLogRow = getNextRow($securityLogSet)) {
            if($securityLogRow['security_log_type'] == "LOGOUT" || $securityLogRow['security_log_type'] == "USER-TIMEOUT") {
                break;
            } elseif ($securityLogRow['security_log_type'] == "LOGIN") {
                $recentLoginCount++;
            }
        }
        $logEntry = "Log In succeeded via Single Sign-on from " . $loginProviderName;
        if($recentLoginCount > 0) {
            $logEntry .= "\nNOTE: This user has logged in " . $recentLoginCount . " times in the past 30 minutes without logging out. This may indicate the user experiencing login problems.\n\n" . $this->getServerHttpVariables();
        }
        addSecurityLog($userName, "LOGIN", $logEntry);

		if (empty(getPreference("REQUIRE_TWO_FACTOR_FOR_SSO_LOGIN"))) {
			setCoreCookie("COMPUTER_AUTHORIZATION_" . $GLOBALS['gUserId'], hash("sha256", $GLOBALS['gUserRow']['security_question_id'] . ":" . $GLOBALS['gUserRow']['secondary_security_question_id'] . ":" . $GLOBALS['gUserId']),
				($_POST['computer_environment_private'] == "1" ? 24 * 730 : false));
		}
		header("location: " . $goToUrl);
	}
}

$pageObject = new SsoCallbackPage();
$pageObject->displayPage();
