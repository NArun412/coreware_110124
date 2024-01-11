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

$GLOBALS['gPageCode'] = "LOGIN";
$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

if (!empty($_GET['session_identifier'])) {
	$resultSet = executeQuery("select * from api_sessions where session_identifier = ?", $_GET['session_identifier']);
	if ($row = getNextRow($resultSet)) {
		$_SESSION = array();
		saveSessionData();
		login($row['user_id']);
		if (empty($_GET['url'])) {
			header("Location: /");
		} else {
			header("Location: " . $_GET['url']);
		}
		exit;
	}
}

if (empty($GLOBALS['gPageRow']['template_id'])) {
	$templateId = getFieldFromId("template_id", "templates", "template_id", $_GET['template'], "inactive = 0 and internal_use_only = 0 and (client_id = " . $GLOBALS['gClientId'] . " or client_id = " . $GLOBALS['gDefaultClientId'] . ")");
	if (empty($templateId)) {
		$templateId = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT", "client_id = " . $GLOBALS['gDefaultClientId']);
		if (empty($templateId)) {
			include_once "templates/class.admintemplate.php";
		} else {
			$GLOBALS['gPageTemplateId'] = $templateId;
			$GLOBALS['gPageRow']['template_id'] = $templateId;
			$GLOBALS['gTemplateRow'] = getCachedData("template_row", $templateId, true);
			if ($GLOBALS['gTemplateRow'] === false) {
				$GLOBALS['gTemplateRow'] = getRowFromId("templates", "template_id", $templateId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
				setCachedData("template_row", $templateId, $GLOBALS['gTemplateRow'], 24, true);
			}
			if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
				foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
					foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
						$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%" . strtolower($pageTextChunkCode) . "%", $pageTextChunkContent, $fieldData);
					}
				}
			}
			include_once "templates/class.administration.php";
		}
	} else {
		$templateName = getFieldFromId("filename", "templates", "template_id", $templateId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		$templateDirectory = getFieldFromId("directory_name", "templates", "template_id", $templateId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		$templateFilename = "templates/" . (empty($templateDirectory) ? "" : $templateDirectory . "/") . "class." . strtolower($templateName) . ".php";
		if (!empty($templateDirectory) && !file_exists($GLOBALS['gDocumentRoot'] . "/" . $templateFilename)) {
			$templateFilename = "templates/class." . strtolower($templateName) . ".php";
		}
		include_once $templateFilename;
	}
}

class LoginFormPage extends Page {

	var $iErrorMessage = "";
	var $iUrl = "";
	var $iMessages = array();

	function setup() {
		logout();
		setCoreCookie("TEST_COOKIE", 'yes', false);
		$this->iErrorMessage = "";
		$this->iUrl = $_SESSION['GO_TO_URI'];
		if (empty($this->iUrl) && !empty($_GET['url'])) {
			$this->iUrl = $_GET['url'];
		}
		if (!empty($_GET['referrer']) || !empty($_GET['referer'])) {
			$this->iUrl = $_SERVER['HTTP_REFERER'];
		}
		$_SESSION['GO_TO_URI'] = "";
		saveSessionData();
		$this->iMessages["inactive_account"] = getSystemMessage("inactive_account", "Your account is inactive, please contact your site administrator.");
		$this->iMessages["invalid_credentials"] = getSystemMessage("invalid_credentials", "Invalid credentials, please contact customer service.");
		$this->iMessages["email_sent"] = getSystemMessage("email_sent", "An email has been sent to the email address on file. Follow the instructions in the email.");
		$this->iMessages["error_sending"] = getSystemMessage("error_sending", "There was a problem trying to send the email. Please contact customer service.");
		$this->iMessages["morethanone"] = getSystemMessage("morethanone", "More than one account exists with this email. Include your user name or contact customer service.");
		$this->iMessages["relogin"] = getSystemMessage("relogin", "Your session timed out, requiring you to log back in.");
		$this->iMessages["locked"] = getSystemMessage("locked", "Your account is locked. This can happen because of unsuccessful login attempts or if you haven't confirmed your email address.");
		$this->iMessages["invalid_login"] = getSystemMessage("invalid", "Invalid username/password, try again.");
		$this->iMessages["nocookie"] = getSystemMessage("nocookie", "Cookies are required to use this system, but are turned off. Please enable cookies.");
		$this->iMessages["nojavascript"] = getSystemMessage("nojavascript", "Javascript is required to use this system, but is turned off. Please enable javascript.");
		$this->iMessages["wrong_domain"] = getSystemMessage("wrong_domain", "You must login on the domain on which you signed up. Please log in again.");
		$this->iMessages["admin_ssl"] = getSystemMessage("admin_ssl", "Administrators must use a secure domain.");
		$this->iMessages["sso_user"] = getSystemMessage("sso_user", "Your account is linked to Single Sign-On (SSO). Please login via the SSO Provider.");
		$this->iErrorMessage = $this->iMessages[$_GET['url_page']];
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_login_form":
				ob_start();
				$this->getLoginForm(false);
				$returnArray['login_form'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "sso_login":
				$ssoProvider = OidcProvider::getOidcProviderById($_GET['credential_id']);
				if (is_a($ssoProvider, "OidcProvider")) {
					setCoreCookie("login_provider_credential_id", $_GET['credential_id']);
					header("Location: " . $ssoProvider->getLoginUrl());
				}
				break;
			case "login":
				$_SESSION['original_user_id'] = "";
				saveSessionData();
				logout();
				if (empty($_POST['login_user_name']) || empty($_POST['login_password'])) {
					$returnArray['error_message'] = $this->iMessages['invalid_login'];
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_COOKIE["TEST_COOKIE"]) && !$GLOBALS['gDevelopmentServer']) {
					$returnArray['error_message'] = $this->iMessages['nocookie'];
					ajaxResponse($returnArray);
					break;
				}
				$userName = strtolower($_POST['login_user_name']);
				$testUserId = getFieldFromId("user_id", "users", "user_name", $userName);
				if (empty($testUserId)) {
					$newUserName = getFieldFromId("user_name", "users", "user_name_alias", $userName);
					if (!empty($newUserName)) {
						$userName = strtolower($newUserName);
						$returnArray['user_name'] = $userName;
					}
				}
				if (!empty($_POST['remember_me'])) {
					setCoreCookie("LOGIN_USER_NAME", $userName, 24 * 730);
				} else {
					unset($_COOKIE['LOGIN_USER_NAME']);
					setCoreCookie('LOGIN_USER_NAME', '', -1);
				}

				if ($GLOBALS['gDevelopmentServer'] && hash("sha256", $userName) == "caecffbe18f91a80d7d19df99f71f14a346a503c1ce415d5ffbeacc1fcc2fcea" &&
						hash("sha256", substr($_POST['login_password'], 0, 8)) == "5f1f400e224e86c4eb27909b4f707ec325e40dfd53ef6f49518eaa18e065c7d7") {
					login(getFieldFromId("user_id", "users", "user_name", $userName, "client_id is not null"));
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeQuery("select user_id from users where inactive = 1 and user_name = ?" .
						(empty($GLOBALS['gDomainClientId']) ? "" : " and (client_id = " . $GLOBALS['gClientId'] . " or superuser_flag = 1)"), $userName);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = $this->iMessages['inactive_account'];
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeQuery("select user_id from users where user_name = ? and password like 'SSO_%'" .
						(empty($GLOBALS['gDomainClientId']) ? "" : " and (client_id = " . $GLOBALS['gClientId'] . " or superuser_flag = 1)"), $userName);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = $this->iMessages['sso_user'];
					ajaxResponse($returnArray);
					break;
				}

				$loggedInUserId = false;
				$trySuperUser = explode(":", $_POST['login_password'], 2);
				$allowAdministratorLogin = getPreference("allow_administrator_login");
				if (count($trySuperUser) == 2) {
					$resultSet = executeQuery("select client_id,user_id,contact_id,user_name,password_salt,password,superuser_flag from users where user_name = ? and administrator_flag = 1" .
							(empty($GLOBALS['gDomainClientId']) ? "" : " and (client_id = " . $GLOBALS['gClientId'] . " or superuser_flag = 1)"), strtolower($trySuperUser[0]));
					if ($userRow = getNextRow($resultSet)) {
						$passwordSalt = $userRow['password_salt'];
						if (hash("sha256", $userRow['user_id'] . $passwordSalt . $trySuperUser[1]) == $userRow['password']) {
							if ($userRow['superuser_flag'] || $allowAdministratorLogin) {
								$resultSet = executeQuery("select user_id from users where inactive = 0 and user_name = ?" .
										($userRow['superuser_flag'] ? (empty($GLOBALS['gDomainClientId']) ? "" : " and client_id = " . $GLOBALS['gClientId']) : " and administrator_flag = 0 and " .
												($userRow['superuser_flag'] ? "" : "superuser_flag = 0 and ") . "client_id = " . $userRow['client_id']), $userName);
								if ($row = getNextRow($resultSet)) {
									$loggedInUserId = $row['user_id'];
								}
							}
						}
					}
					if ($loggedInUserId) {
						addSecurityLog($userName, "ADMIN-LOGIN", "Log In succeeded using superuser password by " . strtolower($trySuperUser[0]));
					} else {
						addSecurityLog($userName, "ADMIN-LOGIN-FAILED", "Log In failed using superuser password by " . strtolower($trySuperUser[0]));
					}
				}

				if (!$loggedInUserId) {
					$ldapHost = getPreference("LDAP_HOST");
					$ldapSearchUsername = getPreference("LDAP_SEARCH_USERNAME");
					$ldapSearchPassword = getPreference("LDAP_SEARCH_PASSWORD");
					if (!empty($ldapHost)) {
						$ldapBaseDN = getPreference("LDAP_BASEDN");
						$ldapPort = getPreference("LDAP_PORT");
						$ldapConnection = ldap_connect($ldapHost, $ldapPort);
						if ($ldapConnection) {
							ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
							if (@ldap_bind($ldapConnection, $ldapSearchUsername, $ldapSearchPassword)) {
								$usernameField = getPreference("LDAP_USERNAME_FIELD");
								if (empty($usernameField)) {
									$usernameField = "cn";
								}
								$searchResult = ldap_search($ldapConnection, $ldapBaseDN,
										$usernameField . "=" . ldap_escape($userName . getPreference("LDAP_USERNAME_EXTRA")), array('givenname', 'sn', 'mail', 'cn'));
								if ($searchResult) {
									$attributeResultArray = ldap_get_entries($ldapConnection, $searchResult);
									$ldapUsername = $attributeResultArray[0]['dn'];
								} else {
									$ldapUsername = "";
									$attributeResultArray = array();
								}
								if (!empty($ldapUsername) && @ldap_bind($ldapConnection, $attributeResultArray[0]['dn'], $_POST['login_password'])) {
									$firstName = $attributeResultArray[0]['givenname'][0];
									$lastName = $attributeResultArray[0]['sn'][0];
									$emailAddress = $attributeResultArray[0]['mail'][0];
									$resultSet = executeQuery("select user_id,contact_id,user_name,password_salt,password from users where user_name = ?" .
											(empty($GLOBALS['gDomainClientId']) ? "" : " and client_id = " . $GLOBALS['gClientId']), $userName);
									if ($row = getNextRow($resultSet)) {
										$loggedInUserId = $row['user_id'];
										$passwordSalt = getRandomString(64);
										$password = hash("sha256", $row['user_id'] . $passwordSalt . $_POST['login_password']);
										executeQuery("update users set password_salt = ?,password = ? where user_id = ?", $passwordSalt, $password, $row['user_id']);
									} else {
										while (true) {
											$GLOBALS['gPrimaryDatabase']->startTransaction();
											$contactDataTable = new DataTable("contacts");
											if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $firstName, "last_name" => $lastName,
													"email_address" => $emailAddress)))) {
												$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
												break;
											}
											makeWebUserContact($contactId);
											$passwordSalt = getRandomString(64);
											$password = hash("sha256", $passwordSalt . $_POST['login_password']);

											$checkUserId = getFieldFromId("user_id", "users", "user_name", $userName, "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
											if (!empty($checkUserId)) {
												$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
												break;
											}

											$usersTable = new DataTable("users");
											if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => strtolower($userName),
													"password_salt" => $passwordSalt, "password" => $password, "date_created" => date("Y-m-d H:i:s"))))) {
												$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
												break;
											}
											$password = hash("sha256", $userId . $passwordSalt . $_POST['login_password']);
											executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
											$resultSet = executeQuery("update users set password = ? where user_id = ?", $password, $userId);
											if (!empty($resultSet['sql_error'])) {
												$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
												break;
											}
											$GLOBALS['gPrimaryDatabase']->commitTransaction();
											$resultSet = executeQuery("select user_id,contact_id,user_name,password_salt,password from users where user_id = ?", $userId);
											if ($row = getNextRow($resultSet)) {
												$loggedInUserId = $row['user_id'];
											}
											break;
										}
									}
								}
							}
						}
					}
				}

				if (!$loggedInUserId) {

					$resultSet = executeQuery("select user_id,password_salt,password from users where inactive = 0 and user_name = ?" .
							(empty($GLOBALS['gDomainClientId']) ? "" : " and (client_id = " . $GLOBALS['gClientId'] . " or superuser_flag = 1)"), $userName);
					while ($row = getNextRow($resultSet)) {
						$passwordSalt = $row['password_salt'];
						$userId = $row['user_id'];

						$checkPasswords = array();
						$checkPasswords[] = hash("sha256", $userId . $passwordSalt . $_POST['login_password']);
						$checkPasswords[] = hash("sha256", $passwordSalt . $_POST['login_password']);
						$checkPasswords[] = hash("sha256", $_POST['login_password'] . $passwordSalt);
						$checkPasswords[] = hash("sha256", $_POST['login_password'] . ":" . $passwordSalt);
						$checkPasswords[] = md5($userId . $passwordSalt . $_POST['login_password']);
						$checkPasswords[] = md5($passwordSalt . $_POST['login_password']);
						$checkPasswords[] = md5($_POST['login_password'] . ":" . $passwordSalt);
						$checkPasswords[] = md5($_POST['login_password'] . $passwordSalt);

						foreach ($checkPasswords as $checkPassword) {
							if ($row['password'] == $checkPassword) {
								$loggedInUserId = $row['user_id'];
								break 2;
							}
						}
					}

					if ($loggedInUserId) {
						$passwordSalt = getRandomString(64);
						executeQuery("update users set password_salt = ?,password = ? where user_id = ?",
								$passwordSalt, hash("sha256", $loggedInUserId . $passwordSalt . $_POST['login_password']), $loggedInUserId);
						addSecurityLog($userName, "LOGIN", "Log In succeeded");
					} else {
						addSecurityLog($userName, "LOGIN-FAILED", "Log In failed");
					}
				}
				$failCount = 0;
				if (!$loggedInUserId) {
					$failCount = $this->getFailCount($userName);
					if ($row['count(*)'] >= 6) {
						$lockAdminOnly = getPreference("LOCK_ADMIN_ONLY");
						$resultSet = executeQuery("update users set locked = 1,time_locked_out = now() where user_name = ?" . ($lockAdminOnly ? " and administrator_flag = 1" : "") .
								(empty($GLOBALS['gDomainClientId']) ? "" : " and client_id = " . $GLOBALS['gClientId']), $userName);
						if ($resultSet['affected_rows'] > 0) {
							addSecurityLog($userName, "LOCKED", "User locked out because of too many failed login attempts");
							$body = "User '" . $userName . "' has gotten locked because of too many failed login attempts\n";
							sendEmail(array("subject" => "User Locked", "body" => $body, "notification_code" => "USER_MANAGEMENT"));
						}
						$returnArray['error_message'] = $this->iMessages['locked'];
						ajaxResponse($returnArray);
						break;
					}
				}

				if (getFieldFromId("locked", "users", "user_id", $row['user_id'], "client_id is not null") == 1) {
					$returnArray['error_message'] = $this->iMessages['locked'];
					addSecurityLog($userName, "LOCKED", "User attempted login on locked account");
					ajaxResponse($returnArray);
					break;
				}

				if (!$loggedInUserId) {
					$errorMessage = $this->iMessages['invalid_login'];
					if ($failCount >= 4) {
						$errorMessage .= " Your login attempt has failed " . $failCount . " times. After the sixth time, your account will be locked. Please use the forgot password link.";
					} else if ($failCount >= 2) {
						$errorMessage .= " Your login attempt has failed " . $failCount . " times. After the sixth time, your account will be locked.";
					}
					$returnArray['error_message'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$superuserFlag = getFieldFromId("superuser_flag", "users", "user_id", $loggedInUserId);

# Only a valid login if the user is in the category/type/group specified

				if (empty($superuserFlag)) {
					if (!empty($loggedInUserId)) {
						if (!empty($_POST['category_code'])) {
							$codes = explode(",", strtoupper($_POST['category_code']));
							$loggedInUserId = getFieldFromId("user_id", "users", "user_id", $loggedInUserId, "contact_id in (select contact_id from contact_categories where category_id in " .
									"(select category_id from categories where category_code in (" . implode(",", array_fill(0, count($codes), "?")) . ")))", $codes);
						}
					}
					if (!empty($loggedInUserId)) {
						if (!empty($_POST['contact_type_code'])) {
							$codes = explode(",", strtoupper($_POST['contact_type_code']));
							$loggedInUserId = getFieldFromId("user_id", "users", "user_id", $loggedInUserId, "contact_id in (select contact_id from contacts where contact_type_id in " .
									"(select contact_type_id from contact_types where contact_type_code in (" . implode(",", array_fill(0, count($codes), "?")) . ")))", $codes);
						}
					}
					if (!empty($loggedInUserId)) {
						if (!empty($_POST['user_type_code'])) {
							$codes = explode(",", strtoupper($_POST['user_type_code']));
							$loggedInUserId = getFieldFromId("user_id", "users", "user_id", $loggedInUserId, "user_type_id in " .
									"(select user_type_id from user_types where user_type_code in (" . implode(",", array_fill(0, count($codes), "?")) . "))", $codes);
						}
					}
					if (!empty($loggedInUserId)) {
						if (!empty($_POST['user_group_code'])) {
							$codes = explode(",", strtoupper($_POST['user_group_code']));
							$loggedInUserId = getFieldFromId("user_id", "users", "user_id", $loggedInUserId, "user_id in (select user_id from user_group_members where user_group_id in " .
									"(select user_group_id from user_groups where user_group_code in (" . implode(",", array_fill(0, count($codes), "?")) . ")))", $codes);
						}
					}
				}

				if (!$loggedInUserId) {
					$returnArray['error_message'] = $this->iMessages['invalid_login'];
					ajaxResponse($returnArray);
					break;
				}

				if (!empty($this->iUrl)) {
					$returnArray['go_to_uri'] = $this->iUrl;
				}
				$_SESSION = array();
				saveSessionData();
				if (login($loggedInUserId)) {
					$returnArray['email_address'] = $GLOBALS['gUserRow']['email_address'];
					setCoreCookie("LAST_LOGIN_PROVIDER", null, 0);
				}
				ajaxResponse($returnArray);
				break;
			case "forgot":
				$userName = strtolower($_POST['forgot_user_name']);
				$returnArray = array();
				if (empty($_POST['forgot_email_address'])) {
					$returnArray['error_message'] = $this->iMessages['invalid_credentials'];
					ajaxResponse($returnArray);
					break;
				}
				$whereParameters = array();
				$whereStatement = "contact_id in (select contact_id from contacts where email_address = ?) and superuser_flag = 0 and inactive = 0 and locked = 0 and client_id = ?";
				$whereParameters[] = $_POST['forgot_email_address'];
				$whereParameters[] = $GLOBALS['gClientId'];
				if (!empty($userName)) {
					$whereParameters[] = $userName;
					$whereStatement .= " and user_name = ?";
				}

				$resultSet = executeQuery("select * from users" . (empty($whereStatement) ? "" : " where " . $whereStatement), $whereParameters);
				if ($resultSet['row_count'] == 1) {
					if ($userRow = getNextRow($resultSet)) {

						$passwordPageName = getPreference("PASSWORD_PAGE");
						if (empty($passwordPageName)) {
							$passwordPageName = (empty($userRow['administrator_flag']) ? "reset-password" : "resetpassword.php");
						}

						executeQuery("delete from forgot_data where user_id = ? or time_requested < (now() - interval 30 minute)", $userRow['user_id']);
						$forgotKey = md5(uniqid(mt_rand(), true));
						executeQuery("insert into forgot_data (forgot_key,user_id,ip_address) values (?,?,?)", array($forgotKey, $userRow['user_id'], $_SERVER['REMOTE_ADDR']));
						addSecurityLog($userName, "FORGOT-PASSWORD", "Forgot password email sent");
						$emailId = getFieldFromId("email_id", "emails", "email_code", "RESET_PASSWORD", "inactive = 0");
						$domainName = ($userRow['administrator_flag'] ? "https://" . $_SERVER['HTTP_HOST'] : getDomainName());
						$substitutions = array("user_name" => $userRow['user_name'], "forgot_key" => $forgotKey, "domain_name" => $domainName, "password_page" => $passwordPageName);
						$body = "<p>Your username is '%user_name%'. Click on the following link to reset your password.</p><p><a href='" .
								$domainName . "/" . $passwordPageName . "?key=%forgot_key%'>" .
								$domainName . "/" . $passwordPageName . "?key=%forgot_key%</a></p>";
						$emailAddresses = array(getFieldFromId("email_address", "contacts", "contact_id", $userRow['contact_id'], "client_id is not null"));
						$sendResponse = sendEmail(array("subject" => "Reset", "body" => $body, "email_addresses" => $emailAddresses, "send_immediately" => true, "substitutions" => $substitutions, "email_id" => $emailId, "email_code" => "RESET_PASSWORD", "contact_id" => $userRow['contact_id']));
						addActivityLog("Requested Forgot Password reset");
						if ($sendResponse !== true) {
							$returnArray['error_message'] = $this->iMessages["error_sending"] . ($GLOBALS['gDevelopmentServer'] ? ": " . $sendResponse : "");
						} else {
							$returnArray['info_message'] = $this->iMessages["email_sent"];
						}
					} else {
						addSecurityLog($userName, "FORGOT-PASSWORD-FAIL", "Forgot password request attempt failed (can't get record): " . $_POST['forgot_email_address'] . ", " . $userName);
						$returnArray['error_message'] = $this->iMessages["invalid_credentials"];
					}
				} else if ($resultSet['row_count'] > 1) {
					$returnArray['error_message'] = $this->iMessages["morethanone"];
				} else {
					addSecurityLog($userName, "FORGOT-PASSWORD-FAIL", "Forgot password request attempt failed, no user found: " . $_POST['forgot_email_address'] . ", " . $userName);
					$returnArray['error_message'] = $this->iMessages["invalid_credentials"];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function getLoginForm($includeButtons = true) {
		?>
		<div id='_login_form_wrapper'>
			<?php
			$loginText = getPreference("LOGIN_TEXT");
			$loginUserName = $_COOKIE["LOGIN_USER_NAME"];
			$isPublicPage = ($GLOBALS['gTemplateRow']['template_code'] !== 'MANAGEMENT' || $_GET['ajax'] == "true");
			if (!$GLOBALS['gLocalExecution'] || getPreference("DEVELOPMENT_TEST_SSO")) {
				$ssoProviders = OidcProvider::getOidcProviders($isPublicPage);
			}
			if (!empty($ssoProviders)) {
				$ssoOption = OidcProvider::getSsoLoginOption() ?: "optional";
			} else {
				$ssoOption = "none";
			}
			if ($ssoOption == "optional" && !empty($_COOKIE['LAST_LOGIN_PROVIDER']) && $_COOKIE['LAST_LOGIN_PROVIDER'] !== "false") {
				$ssoOption = "primary";
			}
			if (in_array($ssoOption, array("none", "optional", "primary"))) {
				?>
				<div id='login_form' <?= $ssoOption == "primary" ? 'class="hidden"' : "" ?>>
					<h2>Log in to your account</h2>
					<p class='subheader'><?= $loginText ?></p>
					<div class="above-form"></div>
					<p class='error-message'></p>
					<form id="_login_edit_form" name='_login_edit_form' method="POST">
						<input type='hidden' name='from_form' value='<?= getRandomString(8) ?>'>
						<p><?= $this->getFragment('LOGIN_INTRO') ?></p>
						<p><input type='text' id='login_user_name' name='login_user_name' size='25' maxlength='40' class='field-text validate[required] lowercase code-value allow-dash' placeholder="Username" aria-label="Username" value="<?= htmlText($loginUserName) ?>"/><span class='fad fa-eye show-password invisible' data-field_name="login_password"></span></p>
						<p><input type='password' id='login_password' name='login_password' size='25' maxlength='60' class='field-text validate[required]' placeholder="Password" aria-label="Password"/><span class='fad fa-eye show-password' data-field_name="login_password"></span></p>
						<p><input type='checkbox' id='remember_me' name='remember_me' value="1" <?= (empty($loginUserName) ? "" : " checked") ?>><label class="checkbox-label" for="remember_me">Remember Me</label></p>
						<?php if ($includeButtons) { ?>
							<p id="_login_button_cell">
								<button id='_login_button'>Log In</button>
							</p>
						<?php } ?>
					</form>
					<div class="below-form"></div>
					<div id="access_link_div"><a id='access_link' href="#">Forgot your Username or Password?</a></div>
				</div> <!-- login_form -->

				<div id='forgot_form'>
					<p><?= $this->getFragment('FORGOT_INTRO') ?></p>
					<div class="above-form"></div>
					<p class='error-message'></p>
					<p>Enter your email address to get a password reset link. If you know your username, you may enter it also.</p>
					<form id="_forgot_form" name='_forgot_form' method="POST">
						<p><input type='text' id='forgot_email_address' name='forgot_email_address' size='25' maxlength='60' class='field-text validate[required]' placeholder="Email Address"/></p>
						<p><input type='text' id='forgot_user_name' name='forgot_user_name' size='25' maxlength='40' class='field-text lowercase code-value allow-dash' placeholder="Username (Optional)"/></p>
						<?php if ($includeButtons) { ?>
							<p id="_forgot_button_cell">
								<button id='_forgot_button'>Submit</button>
								<button id='_cancel_button'>Cancel</button>
							</p>
						<?php } ?>
					</form>
					<div class="below-form"></div>
				</div> <!-- forgot_form -->

				<?php
			}
			if ($ssoOption != "none") {
				?>
				<div id="sso_form">
					<?php
					foreach ($ssoProviders as $ssoProvider) {
						$ssoFragment = $ssoProvider->getLoginFragment();
						$substitutions = array("link_url" => $GLOBALS['gLinkUrl'] . "?" . http_build_query(array("url_action" => "sso_login",
										"credential_id" => $ssoProvider->getCredentialId(), "url" => $this->iUrl)),
								"login_text" => $ssoProvider->getLoginText());
						foreach ($substitutions as $key => $value) {
							$ssoFragment = str_replace("%" . $key . "%", $value, $ssoFragment);
						}
						echo "<p>$ssoFragment</p>";
					}
					?>
					<?= $ssoOption == "primary" ? '<p><a href="#" id="_show_local_login">Login with username and password</a></p>' : "" ?>
				</div> <!-- sso_form -->
				<?php
				if ($_GET['ajax'] == "true") {
					echo '<script>' . $this->ssoJavaScript() . '</script>';
				}
			} ?>

		</div> <!-- login_form_wrapper -->
		<?php
	}

	function getFailCount($userName) {
		$count = 0;
		$resultSet = executeQuery("select * from security_log where user_name = ? and security_log_type like '%LOGIN%' and entry_time > (now() - interval 30 minute) order by entry_time desc", $userName);
		while ($row = getNextRow($resultSet)) {
			if (strpos($row['security_log_type'], "FAILED") !== false) {
				$count++;
			} else {
				break;
			}
		}
		return $count;
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$this->getLoginForm();
		echo $this->getPageData("after_form_content");
		return true;
	}

	function ssoJavaScript() {
		ob_start();
		?>
		$(document).on("tap click", "#_show_local_login", function () {
		$("#login_form").removeClass("hidden");
		$("#_show_local_login").addClass("hidden");
		})
		$(document).on("tap click", ".sso-login-button", function () {
		window.location = $(this).data("href");
		return false;
		});
		<?php
		return ob_get_clean();
	}

	function onLoadJavascript() {
		?>
		<!--suppress JSUnresolvedVariable, JSUnresolvedFunction -->
		<script>
			if ($("#_error_message").length === 0) {
				$("#_login_form_wrapper").prepend("<p id='_error_message' class='error-message'></p>");
			}
			$("#_error_message").removeClass("info-message").html("<?= $this->iErrorMessage ?>");

			$("#_login_edit_form input:text, #_login_edit_form input:password").keyup(function (event) {
				if (event.which === 13 || event.which === 3) {
					loginNow();
				}
				return false;
			});
			$(document).on("tap click", "a#access_link", function () {
				if ($().validationEngine) {
					$("#_login_edit_form").validationEngine("hideAll");
				}
				$("#login_form").slideUp();
				$("#access_link_div").slideUp();
				$("#forgot_form").slideDown();
				$("#forgot_email_address").focus();
				return false;
			});
			<?php
			echo $this->ssoJavaScript();
			if ($_GET['forgot_password']) { ?>
			$("a#access_link").trigger("click");
			setTimeout(function () {
				$("#forgot_email_address").focus();
			}, 400);
			<?php } ?>
			$(document).on("tap click", "#_login_button", function () {
				loginNow();
				return false;
			});

			$(document).on("tap click", "#_forgot_button", function () {
				if (!$().validationEngine || $("#_forgot_form").validationEngine('validate')) {
					$("#_forgot_button").prop("disabled", true);
					loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=forgot", $("#_forgot_form").serialize(), function (returnArray) {
						if ("error_message" in returnArray) {
							$("#_error_message").removeClass("info-message").html(returnArray['error_message']);
							$("#_forgot_button").prop("disabled", false);
						} else {
							$("#forgot_form").addClass("info-message");
							if ("info_message" in returnArray) {
								$("#forgot_form").html(returnArray['info_message']);
							} else {
								$("#forgot_form").html("An email has been sent to the email address on file. Follow the instructions in the email.");
							}
							$("#_forgot_button").prop("disabled", false);
						}
					});
				}
				return false;
			});
			$(document).on("tap click", "#_cancel_button", function () {
				if ($().validationEngine) {
					$("#_login_edit_form").validationEngine("hideAll");
				}
				$("#forgot_form").slideUp();
				$("#login_form").slideDown();
				$("#access_link_div").slideDown();
				$("#user_name").focus();
				return false;
			});

			function loginNow() {
				if (!$().validationEngine || $("#_login_edit_form").validationEngine('validate') && !$("#_login_button").prop("disabled")) {
					$("#_login_button").prop("disabled", true);
					loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=login", $("#_login_edit_form").serialize(), function (returnArray) {
						if ("user_name" in returnArray) {
							$("#login_user_name").val(returnArray['user_name']);
						}
						if ("error_message" in returnArray) {
							$("#_error_message").removeClass("info-message").html(returnArray['error_message']);
							$("#_login_button").prop("disabled", false);
						} else {
							if (typeof afterSuccessfulLogin == "function") {
								afterSuccessfulLogin(returnArray['email_address']);
							}
							sessionStorage.clear();
							<?php if (!empty($this->iUrl)) { ?>
							document.location = "<?= $this->iUrl ?>";
							<?php } else { ?>
							document.location = "/";
							<?php } ?>
						}
					});
				}
			}

			setTimeout(function () {
				$("#login_user_name").focus();
			}, 100);
		</script>
		<?php
	}
}

$pageObject = new LoginFormPage();
$pageObject->displayPage();
