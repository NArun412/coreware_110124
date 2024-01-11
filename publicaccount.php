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

$GLOBALS['gPageCode'] = "PUBLICACCOUNT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gPasswordReset'] = true;
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

class PublicAccountPage extends Page {

	var $iValidPanels = array("contact", "account", "opt_in", "donations", "recurring", "payment", "logout", "files");

	function setup() {
		if (!in_array($_GET['panel'], $this->iValidPanels)) {
			$_GET['panel'] = "";
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
            case "resend_donation_receipt":
                $donationId = getFieldFromId("donation_id","donations","donation_id",$_GET['donation_id'],"contact_id = ?",$GLOBALS['gUserRow']['contact_id']);
                if (empty($donationId)) {
                    $returnArray['error_message'] = "Unable to resend receipt";
                    ajaxResponse($returnArray);
                    exit;
                }
	            $receiptProcessed = Donations::processDonationReceipt($donationId, array("email_only" => true));
                if ($receiptProcessed !== true) {
	                $returnArray['error_message'] = "Unable to resend receipt";
	                ajaxResponse($returnArray);
	                exit;
                } else {
                    $returnArray['info_message'] = "Receipt successfully emailed";
                }
                ajaxResponse($returnArray);
                exit;
			case "create_payment_method":
				$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
					"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
						$_POST['payment_method_id']));
				$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
				if (!empty($_POST['same_address'])) {
					$fields = array("address_1", "city", "state", "postal_code", "country_id");
					foreach ($fields as $fieldName) {
						$_POST['billing_' . $fieldName] = $GLOBALS['gUserRow'][$fieldName];
					}
				}
				$requiredFields = array(
					"billing_first_name" => array(),
					"billing_last_name" => array(),
					"billing_address_1" => array(),
					"billing_city" => array(),
					"billing_state" => array("billing_country_id" => "1000"),
					"billing_postal_code" => array("billing_country_id" => "1000"),
					"billing_country_id" => array(),
					"payment_method_id" => array(),
					"account_number" => array("payment_method_type_code" => "CREDIT_CARD"),
					"expiration_month" => array("payment_method_type_code" => "CREDIT_CARD"),
					"expiration_year" => array("payment_method_type_code" => "CREDIT_CARD"),
					"card_code" => array("payment_method_type_code" => "CREDIT_CARD"),
					"routing_number" => array("payment_method_type_code" => "BANK_ACCOUNT"),
					"bank_account_number" => array("payment_method_type_code" => "BANK_ACCOUNT"));
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
				$_POST['account_number'] = str_replace(" ", "", $_POST['account_number']);
				$_POST['account_number'] = str_replace("-", "", $_POST['account_number']);
				$_POST['bank_account_number'] = str_replace(" ", "", $_POST['bank_account_number']);
				$_POST['bank_account_number'] = str_replace("-", "", $_POST['bank_account_number']);
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactRow = $GLOBALS['gUserRow'];

				$merchantAccountId = $GLOBALS['gMerchantAccountId'];
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
				if (!empty($achMerchantAccount)) {
					$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
				}
				$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

				if (!$useECommerce) {
					$returnArray['error_message'] = "Unable to connect to Merchant Services account. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($useECommerce) || !$useECommerce->hasCustomerDatabase()) {
					$returnArray['error_message'] = "Unable to connect to Merchant Services account. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}

				$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $GLOBALS['gMerchantAccountId']);
				if (empty($merchantIdentifier)) {
					$success = $useECommerce->createCustomerProfile($contactRow);
					$response = $useECommerce->getResponse();
					if ($success) {
						$merchantIdentifier = $response['merchant_identifier'];
					}
				}
				if (empty($merchantIdentifier)) {
					$returnArray['error_message'] = "Unable to create the recurring donation. Please contact customer service. #683";
					ajaxResponse($returnArray);
					break;
				}

				$testOrderId = date("Z") + 60000;
				if (!$isBankAccount) {
					$paymentArray = array("amount" => "1.00", "order_number" => $testOrderId, "description" => "Test Transaction", "authorize_only" => true,
						"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
						"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
						"email_address" => $contactRow['email_address'], "contact_id" => $contactId);
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['card_code'];

					$success = $useECommerce->authorizeCharge($paymentArray);
					$response = $useECommerce->getResponse();
					if ($success) {
						$paymentArray['transaction_identifier'] = $response['transaction_id'];
						$useECommerce->voidCharge($paymentArray);
					} else {
						$returnArray['error_message'] = "Authorization failed: " . $response['response_reason_text'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$this->iDatabase->startTransaction();
				$accountLabel = $_POST['account_label'];
				if (empty($accountLabel)) {
					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
				}
				$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['business_name']);
				$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
					"account_number,expiration_date) values (?,?,?,?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
					$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
					(empty($_POST['expiration_date']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))));
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$accountId = $resultSet['insert_id'];

				$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
					"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
					"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
					"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id']);
				if ($isBankAccount) {
					$paymentArray['bank_routing_number'] = $_POST['routing_number'];
					$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
					$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
				} else {
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['card_code'];
				}
				$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
				if (!$success) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create account. Please contact customer service. #157";
					ajaxResponse($returnArray);
					break;
				}

				$this->iDatabase->commitTransaction();

				$logEntry = "Added new payment method";
				if (!empty($_POST['set_recurring'])) {
					$resultSet = executeQuery("select * from recurring_donations where (end_date > current_date or end_date is null) and contact_id = ? and account_id is not null and account_id <> ?",
						$contactId, $accountId);
					$count = 0;
					while ($row = getNextRow($resultSet)) {
						$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
						if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
							executeQuery("update recurring_donations set account_id = ?,requires_attention = 0 where recurring_donation_id = ?", $accountId, $row['recurring_donation_id']);
							$count++;
						}
					}
					if ($count > 0) {
						$logEntry .= " and updated " . $count . " recurring donation" . ($count == 1 ? "" : "s");
					}
				} else {

# check to see if there is only one active account and if there are recurring donations that are not ended that use an inactive account and reset them.

					$count = 0;
					$resultSet = executeQuery("select * from accounts where inactive = 0 and account_token is not null and contact_id = ?", $contactId);
					while ($row = getNextRow($resultSet)) {
						$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
						if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
							$count++;
						}
					}
					if ($count == 1) {
						$resultSet = executeQuery("select * from recurring_donations where (end_date > current_date or end_date is null) and contact_id = ? and account_id is not null and account_id <> ?",
							$contactId, $accountId);
						$count = 0;
						while ($row = getNextRow($resultSet)) {
							$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
							if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
								executeQuery("update recurring_donations set account_id = ?,requires_attention = 0 where recurring_donation_id = ?", $accountId, $row['recurring_donation_id']);
								$count++;
							}
						}
						if ($count > 0) {
							$logEntry .= " and updated " . $count . " recurring donation" . ($count == 1 ? "" : "s");
						}
					}
				}
				addActivityLog($logEntry);

				ajaxResponse($returnArray);

				break;
			case "update_payment_method":
				if (empty($_POST['expiration_month']) || empty($_POST['expiration_year']) ||
					empty($_POST['billing_address_1']) || empty($_POST['billing_postal_code'])) {
					$returnArray['error_message'] = "Required information missing";
					ajaxResponse($returnArray);
					break;
				}
				$accountRow = getRowFromId("accounts", "account_id", $_POST['account_id']);
				if (empty($accountRow)) {
					$returnArray['error_message'] = "Invalid Payment Method";
					ajaxResponse($returnArray);
					break;
				}
				$merchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
				$merchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $_POST['account_id']);
				if (empty($merchantIdentifier)) {
					$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $GLOBALS['gUserRow']['contact_id'], "merchant_account_id = ?", $merchantAccountId);
				}
				$customerPaymentProfileIdentifier = $accountRow['account_token'];
				if (empty($customerPaymentProfileIdentifier) || empty($merchantIdentifier)) {
					$returnArray['error_message'] = "Invalid Payment Method";
					ajaxResponse($returnArray);
					break;
				}
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				if (!$eCommerce) {
					$returnArray['error_message'] = "Unable to connect to Merchant Services account. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}
				$parameters = array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileIdentifier);
				$eCommerce->getCustomerPaymentProfile($parameters);
				$response = $eCommerce->getResponse();
				if (is_array($response) && array_key_exists("payment_profile", $response)) {
					$parameters['first_name'] = $response['first_name'];
					$parameters['last_name'] = $response['last_name'];
					$parameters['business_name'] = $response['business_name'];
					$parameters['address_1'] = $response['address_1'];
					$parameters['city'] = $response['city'];
					$parameters['state'] = $response['state'];
					$parameters['postal_code'] = $response['postal_code'];
					$parameters['country'] = $response['country'];
				}
				$parameters['address_1'] = $_POST['billing_address_1'];
				$parameters['postal_code'] = $_POST['billing_postal_code'];
				if (array_key_exists("card_number", $response)) {
					$parameters['card_number'] = $response['card_number'];
				}
				if (array_key_exists("account_type", $response)) {
					$parameters['account_type'] = $response['account_type'];
					$parameters['routing_number'] = $response['routing_number'];
					$parameters['account_number'] = $response['account_number'];
					$parameters['echeck_type'] = $response['echeck_type'];
					$parameters['bank_name'] = $response['bank_name'];
				}
				if (!empty($_POST['expiration_month']) && !empty($_POST['expiration_year'])) {
					$parameters['expiration_date'] = $_POST['expiration_year'] . "-" . $_POST['expiration_month'] . "-01";
				}
				if ($eCommerce->updateCustomerPaymentProfile($parameters)) {
					$returnArray['info_message'] = "Information Saved";
					addActivityLog("Updated expiration date and billing address for account '" . $accountRow['account_label'] . "'");

# check to see if there is only one active account and if there are recurring donations that are not ended that use an inactive account and reset them.

					$count = 0;
					$contactId = $GLOBALS['gUserRow']['contact_id'];
					$resultSet = executeQuery("select * from accounts where inactive = 0 and account_token is not null and contact_id = ?", $contactId);
					while ($row = getNextRow($resultSet)) {
						$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
						if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
							$count++;
						}
					}
					if ($count == 1) {
						$resultSet = executeQuery("select * from recurring_donations where (end_date > current_date or end_date is null) and contact_id = ? and account_id is not null and account_id <> ?",
							$contactId, $accountRow['account_id']);
						$count = 0;
						while ($row = getNextRow($resultSet)) {
							$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
							if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
								executeQuery("update recurring_donations set account_id = ?,requires_attention = 0 where recurring_donation_id = ?", $accountRow['account_id'], $row['recurring_donation_id']);
								$count++;
							}
						}
						if ($count > 0) {
							$returnArray['info_message'] .= "Updated " . $count . " recurring donation" . ($count == 1 ? "" : "s") . " with new payment method";
						}
					}
					if (!empty($_POST['expiration_month']) && !empty($_POST['expiration_year'])) {
						$expirationDate = $_POST['expiration_year'] . "-" . $_POST['expiration_month'] . "-01";
						executeQuery("update accounts set expiration_date = ? where account_id = ?", $expirationDate, $_POST['account_id']);
					}
				} else {
					$returnArray['error_message'] = "Error saving information: " . $eCommerce->getErrorMessage();
				}

				ajaxResponse($returnArray);

				break;
			case "create_account":
				$requiredFields = array(
					"first_name" => array(),
					"last_name" => array(),
					"address_1" => array(),
					"city" => array(),
					"country_id" => array(),
					"email_address" => array(),
					"state" => array("country_id" => "1000"),
					"postal_code" => array("country_id" => "1000"),
					"user_name" => array(),
					"password" => array());
				if (getPreference("PCI_COMPLIANCE")) {
					$requiredFields['security_question_id'] = array();
					$requiredFields['answer_text'] = array();
					$requiredFields['secondary_security_question_id'] = array();
					$requiredFields['secondary_answer_text'] = array();
				}
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['password']) && !isPCIPassword($_POST['password'])) {
					$minimumPasswordLength = getPreference("minimum_password_length");
					if (empty($minimumPasswordLength)) {
						$minimumPasswordLength = 10;
					}
					$noPasswordRequirements = getPreference("no_password_requirements");
					$returnArray['error_message'] = getSystemMessage("password_minimum_standards", "Password does not meet minimum standards. Must be at least " . $minimumPasswordLength .
						" characters long" . ($noPasswordRequirements ? "" : " and include an upper and lowercase letter and a number"));
					ajaxResponse($returnArray);
					break;
				}
				$this->iDatabase->startTransaction();
				$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
				$contactId = "";
				$resultSet = executeQuery("select contact_id from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
					"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
					$contactTable = new DataTable("contacts");
					$contactTable->setSaveOnlyPresent(true);
					$contactTable->saveRecord(array("name_values" => $_POST, "primary_id" => $contactId));
				}
				if (empty($contactId)) {
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
						"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
						"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "source_id" => $sourceId)))) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $contactDataTable->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
				}
				$resultSet = executeQuery("select * from users where user_name = ? and client_id = ?", strtolower($_POST['user_name']), $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "User name is already taken. Please select another.";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['email_address'])) {
					$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address'], "contact_id in (select contact_id from users)");
					if (!empty($existingContactId)) {
						$returnArray['error_message'] = "A User already exists with this email address. Please log in.";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$passwordSalt = getRandomString(64);
				$password = hash("sha256", $passwordSalt . $_POST['password']);
				$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($_POST['user_name']), "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
				if (!empty($checkUserId)) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "User name is unavailable. Choose another";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select count(*) from users where client_id = ? and inactive = 1 and contact_id in (select contact_id from contacts where email_address = ?)",
					$GLOBALS['gClientId'], $_POST['email_address']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > 0) {
						$returnArray['error_message'] = "Unable to create user account";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$usersTable = new DataTable("users");
				if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => strtolower($_POST['user_name']),
					"password_salt" => $passwordSalt, "password" => $password, "security_question_id" => $_POST['security_question_id'], "answer_text" => $_POST['answer_text'],
					"secondary_security_question_id" => $_POST['secondary_security_question_id'], "secondary_answer_text" => $_POST['secondary_answer_text'],
					"date_created" => date("Y-m-d H:i:s"))))) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = $usersTable->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				$confirmUserAccount = getPreference("CONFIRM_USER_ACCOUNT");
				if (!empty($confirmUserAccount)) {
					$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
					executeQuery("update users set verification_code = ?,locked = 1 where user_id = ?", $randomCode, $userId);
				}
				$password = hash("sha256", $userId . $passwordSalt . $_POST['password']);
				executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
				$resultSet = executeQuery("update users set password = ?,last_password_change = now() where user_id = ?", $password, $userId);
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				makeWebUserContact($contactId);
				$_SESSION = array();
				saveSessionData();
				login($userId);
				$emailId = getFieldFromId("email_id", "emails", "email_code", "NEW_ACCOUNT", "inactive = 0");
				if (!empty($emailId)) {
					$substitutions = $_POST;
					unset($substitutions['password']);
					unset($substitutions['password_again']);
					sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $_POST['email_address'], "contact_id" => $contactId));
				}

				$phoneDescriptions = array("primary");
				foreach ($phoneDescriptions as $phoneDescription) {
					if (!empty($_POST[$phoneDescription . "_phone_number"])) {
						$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = ?", $contactId, $phoneDescription);
						if ($row = getNextRow($resultSet)) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST[$phoneDescription . "_phone_number"], $row['phone_number_id']);
						} else {
							executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)",
								$contactId, $_POST[$phoneDescription . "_phone_number"], $phoneDescription);
						}
					} else {
						executeQuery("delete from phone_numbers where description = ? and contact_id = ?", $phoneDescription, $contactId);
					}
				}
				$this->iDatabase->commitTransaction();
				if (!empty($userId)) {
					sendEmail(array("subject" => "User Account Created", "body" => "User account '" . $_POST['user_name'] . "' for contact " . getDisplayName($contactId) . " was created.", "email_address" => getNotificationEmails("USER_MANAGEMENT")));
				}
				if (!empty($confirmUserAccount)) {
					$confirmLink = "https://" . $_SERVER['HTTP_HOST'] . "/confirmuseraccount.php?user_id=" . $userId . "&hash=" . $randomCode;
					sendEmail(array("email_address" => $_POST['email_address'], "send_immediately" => true, "email_code" => "ACCOUNT_CONFIRMATION", "substitutions" => array("confirmation_link" => $confirmLink), "subject" => "Confirm Email Address", "body" => "<p>Click <a href='" . $confirmLink . "'>here</a> to confirm your email address and complete the creation of your user account.</p>"));
					logout();
					$returnArray['info_message'] = "Please check your email and confirm your user account before you attempt to log in.";
				}
				ajaxResponse($returnArray);
				break;
			case "update_account":
				if (!$GLOBALS['gLoggedIn']) {
					$returnArray['error_message'] = "Invalid Contact Information";
					ajaxResponse($returnArray);
					break;
				}
				$requiredFields = array(
					"first_name" => array(),
					"last_name" => array(),
					"address_1" => array(),
					"city" => array(),
					"country_id" => array(),
					"email_address" => array(),
					"state" => array("country_id" => "1000"),
					"postal_code" => array("country_id" => "1000"),
					"user_name" => array());
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					$returnArray['panel_name'] = "contact";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['password']) && !isPCIPassword($_POST['password'])) {
					$minimumPasswordLength = getPreference("minimum_password_length");
					if (empty($minimumPasswordLength)) {
						$minimumPasswordLength = 10;
					}
					$noPasswordRequirements = getPreference("no_password_requirements");
					if ($noPasswordRequirements) {
						$this->iDataSource->addColumnControl("contact_pw", "classes", "no-password-requirements");
					}
					$returnArray['error_message'] = getSystemMessage("password_minimum_standards", "Password does not meet minimum standards. Must be at least " . $minimumPasswordLength .
						" characters long and include an upper and lowercase letter and a number");
					ajaxResponse($returnArray);
					break;
				}
				if (getPreference("PCI_COMPLIANCE") && !empty($_POST['password'])) {
					executeQuery("delete from user_passwords where time_changed < date_sub(current_date,interval 2 year)");
					$resultSet = executeQuery("select * from user_passwords where user_id = ?", $GLOBALS['gUserId']);
					while ($row = getNextRow($resultSet)) {
						$thisPassword = hash("sha256", $GLOBALS['gUserId'] . $row['password_salt'] . $_POST['password']);
						if ($thisPassword == $row['password']) {
							$returnArray['error_message'] = getSystemMessage("recent_password", "You cannot reuse a recent password.");
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactTable = new DataTable("contacts");
				$contactTable->setSaveOnlyPresent(true);
				if (!$contactTable->saveRecord(array("name_values" => $_POST, "primary_id" => $contactId))) {
					$returnArray['error_message'] = getSystemMessage("basic", $contactTable->getErrorMessage());
					$returnArray['panel_name'] = "contact";
					ajaxResponse($returnArray);
					break;
				}
				$phoneDescriptions = array("primary");
				$phoneUpdated = false;
				foreach ($phoneDescriptions as $phoneDescription) {
					if (!empty($_POST[$phoneDescription . "_phone_number"])) {
						$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = ?", $contactId, $phoneDescription);
						if ($row = getNextRow($resultSet)) {
							if ($_POST[$phoneDescription . "_phone_number"] != $row['phone_number']) {
								executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
									$_POST[$phoneDescription . "_phone_number"], $row['phone_number_id']);
								$phoneUpdated = true;
							}
						} else {
							executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)",
								$contactId, $_POST[$phoneDescription . "_phone_number"], $phoneDescription);
							$phoneUpdated = true;
						}
					} else {
						executeQuery("delete from phone_numbers where description = ? and contact_id = ?", $phoneDescription, $contactId);
						$phoneUpdated = true;
					}
				}
				if ($contactTable->getColumnsChanged() > 0 || $phoneUpdated) {
					addActivityLog("Updated Contact Info");
				}
				if ((!empty($_POST['user_name']) || !empty($_POST['password'])) && !empty($_POST['current_password'])) {
					$userTable = new DataTable("users");
					$userTable->setSaveOnlyPresent(true);
					$saveData = array();
					if ($_POST['user_name'] != $GLOBALS['gUserRow']['user_name'] && !empty($_POST['user_name'])) {
						$userId = getFieldFromId("user_id", "users", "user_name", $_POST['user_name'], "client_id = ?", $GLOBALS['gClientId']);
						if (!empty($userId)) {
							$returnArray['error_message'] = "User name is already taken. Please choose another.";
							$returnArray['panel_name'] = "account";
							ajaxResponse($returnArray);
							break;
						}
						$saveData['user_name'] = $_POST['user_name'];
					}
					if (!empty($_POST['password'])) {
						$currentPassword = hash("sha256", $GLOBALS['gUserId'] . $GLOBALS['gUserRow']['password_salt'] . $_POST['current_password']);
						if ($currentPassword != $GLOBALS['gUserRow']['password']) {
							$returnArray['error_message'] = "Password cannot be reset because current password is not correct.";
							$returnArray['panel_name'] = "account";
							ajaxResponse($returnArray);
							break;
						} else {
							$saveData['password_salt'] = getRandomString(64);
							$saveData['password'] = hash("sha256", $GLOBALS['gUserId'] . $saveData['password_salt'] . $_POST['password']);
						}
					}
					if (array_key_exists("security_question_id", $_POST)) {
						$saveData['security_question_id'] = $_POST['security_question_id'];
					}
					if (array_key_exists("answer_text", $_POST)) {
						$saveData['answer_text'] = $_POST['answer_text'];
					}
					if (array_key_exists("secondary_security_question_id", $_POST)) {
						$saveData['secondary_security_question_id'] = $_POST['secondary_security_question_id'];
					}
					if (array_key_exists("secondary_answer_text", $_POST)) {
						$saveData['secondary_answer_text'] = $_POST['secondary_answer_text'];
					}
					if (!empty($saveData)) {
						if (!$userTable->saveRecord(array("name_values" => $saveData, "primary_id" => $GLOBALS['gUserId']))) {
							$returnArray['error_message'] = getSystemMessage("basic", $userTable->getErrorMessage());
							$returnArray['panel_name'] = "account";
							ajaxResponse($returnArray);
							break;
						}
						if (array_key_exists("password", $saveData)) {
							executeQuery("insert into user_passwords (user_id,password_salt,password,time_changed) values (?,?,?,now())", $GLOBALS['gUserId'], $saveData['password_salt'], $saveData['password']);
							executeQuery("update users set last_password_change = now() where user_id = ?", $GLOBALS['gUserId']);
							addActivityLog("Reset Password");
						}
					}
					if ($userTable->getColumnsChanged() > 0) {
						addActivityLog("Updated User Information");
					}
				}
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("mailing_list_id_")) == "mailing_list_id_") {
						$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", substr($fieldName, strlen("mailing_list_id_")));
						if (!empty($mailingListId)) {
							$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
							if (!empty($mailingListRow)) {
								if ($fieldData == "Y") {
									if (!empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
									}
								} else {
									if (empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_out" => date("Y-m-d")), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										executeQuery("update contact_mailing_lists set date_opted_out = now() where contact_mailing_list_id = ?",
											$mailingListRow['contact_mailing_list_id']);
										addActivityLog("Opted out of mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
									}
								}
							} else {
								if ($fieldData == "Y") {
									$contactMailingListSource = new DataSource("contact_mailing_lists");
									$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
									addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
								}
							}
						}
					} else if (substr($fieldName, 0, strlen("category_id_")) == "category_id_") {
						$categoryId = getFieldFromId("category_id", "categories", "category_id", substr($fieldName, strlen("category_id_")));
						if (!empty($categoryId)) {
							$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $categoryId, "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
							if (empty($contactCategoryId)) {
								if ($fieldData == "Y") {
									$contactCategoryDataSource = new DataSource("contact_categories");
									$contactCategoryDataSource->saveRecord(array("contact_id" => $GLOBALS['gUserRow']['contact_id'], "category_id" => $categoryId));
									addActivityLog("Selected Category '" . getFieldFromId("description", "categories", "category_id", $categoryId) . "'");
								}
							} else {
								if ($fieldData != "Y") {
									$contactCategoryDataSource = new DataSource("contact_categories");
									$contactCategoryDataSource->deleteRecord(array("primary_id" => $contactCategoryId));
									addActivityLog("Unselected Category '" . getFieldFromId("description", "categories", "category_id", $categoryId) . "'");
								}
							}
						}
					}
				}

				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("account_id_")) == "account_id_" && $fieldData == "1") {
						$accountId = getFieldFromId("account_id", "accounts", "account_id",
							substr($fieldName, strlen("account_id_")), "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
						if (empty($accountId)) {
							continue;
						}
						$accountRow = getRowFromId("accounts", "account_id", $accountId);
						if (empty($accountRow)) {
							continue;
						}
						$merchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
						$accountsDataSource = new DataSource("accounts");
						$accountsDataSource->setSaveOnlyPresent(true);
						$accountsDataSource->saveRecord(array("name_values" => array("inactive" => "1", "account_token" => "", "merchant_identifier" => "", "merchant_account_id" => ""), "primary_id" => $accountId));

						$merchantIdentifier = $accountRow['merchant_identifier'];
						if (empty($merchantIdentifier)) {
							$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $GLOBALS['gUserRow']['contact_id'], "merchant_account_id = ?", $merchantAccountId);
						}
						if (!empty($accountRow['account_token']) && !empty($merchantIdentifier)) {
							$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
							if ($eCommerce) {
								$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier,
									"account_token" => $accountRow['account_token']));
							}
						}
						$resultSet = executeQuery("select * from recurring_donations where account_id = ? and (end_date is null or end_date > current_date)", $accountId);
						while ($row = getNextRow($resultSet)) {
							if (array_key_exists("recurring_donation_id_" . $row['recurring_donation_id'], $_POST)) {
								$_POST['end_date_' . $row['recurring_donation_id']] = date("m/d/Y");
							}
						}
						addActivityLog("Made account '" . getFieldFromId("account_label", "accounts", "account_id", $accountId) . "' inactive");
					}
				}

				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("recurring_donation_id_")) == "recurring_donation_id_") {
						$recurringDonationId = getFieldFromId("recurring_donation_id", "recurring_donations", "recurring_donation_id",
							substr($fieldName, strlen("recurring_donation_id_")), "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
						if (!empty($recurringDonationId)) {
							$recurringDonationTable = new DataTable("recurring_donations");
							$saveRecurringRow = $recurringDonationTable->getRow($recurringDonationId);
							$recurringDonationTable->setSaveOnlyPresent(true);
							$endDate = (empty($_POST['end_date_' . $recurringDonationId]) ? "" : makeDateParameter($_POST['end_date_' . $recurringDonationId]));
							$nextBillingDate = (empty($_POST['next_billing_date_' . $recurringDonationId]) ? date("Y-m-d") : makeDateParameter($_POST['next_billing_date_' . $recurringDonationId]));
							$saveData = array("amount" => $_POST['amount_' . $recurringDonationId], "project_name" => $_POST['project_name_' . $recurringDonationId],
								"end_date" => $endDate, "next_billing_date" => $nextBillingDate, "requires_attention" => "0");
							if (!empty($_POST['account_id_' . $recurringDonationId])) {
								$saveData['account_id'] = $_POST['account_id_' . $recurringDonationId];
							}
							if (!$recurringDonationTable->saveRecord(array("name_values" => $saveData, "primary_id" => $recurringDonationId))) {
								$returnArray['error_message'] = getSystemMessage("basic", $contactTable->getErrorMessage());
								$returnArray['panel_name'] = "recurring";
								ajaxResponse($returnArray);
								break;
							}
							if ($recurringDonationTable->getColumnsChanged() > 0) {
								$logEntry = "Updated Recurring Donation for '" . getFieldFromId("description", "designations", "designation_id", $saveRecurringRow['designation_id']) . "'";
								if (!empty($saveData['account_id'])) {
									$logEntry .= ", changed to account '" . getFieldFromId("account_label", "accounts", "account_id", $saveData['account_id']) . "'";
								}
								if ($saveRecurringRow['amount'] != $_POST['amount_' . $recurringDonationId]) {
									$logEntry .= ", changed amount from " . number_format($saveRecurringRow['amount'], 2, ".", ",") . " to " . number_format($_POST['amount_' . $recurringDonationId], 2, ".", ",");
								}
								if ($saveRecurringRow['end_date'] != $endDate) {
									$logEntry .= ", changed end date from " . (empty($saveRecurringRow['end_date']) ? "never" : date("m/d/Y", strtotime($saveRecurringRow['end_date']))) . " to " .
										(empty($endDate) ? "never" : date("m/d/Y", strtotime($endDate)));
								}
								addActivityLog($logEntry);
							}
							if ($saveRecurringRow['amount'] != $_POST['amount_' . $recurringDonationId] ||
								$saveRecurringRow['project_name'] != $_POST['project_name_' . $recurringDonationId] ||
								$saveRecurringRow['end_date'] != $endDate) {
								$substitutions = array();
								$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $saveRecurringRow['designation_id']);
								$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", $saveRecurringRow['designation_id']);
								$substitutions['full_name'] = ($saveRecurringRow['anonymous_gift'] ? "Anonymous" : getDisplayName($saveRecurringRow['contact_id']));
								$substitutions['old_amount'] = number_format($saveRecurringRow['amount'], 2);
								$substitutions['amount'] = number_format($_POST['amount_' . $recurringDonationId], 2);
								$substitutions['old_project_name'] = (empty($saveRecurringRow['project_name']) ? "None" : $saveRecurringRow['project_name']);
								$substitutions['project_name'] = (empty($_POST['project_name_' . $recurringDonationId]) ? "None" : $_POST['project_name_' . $recurringDonationId]);
								$substitutions['old_end_date'] = (empty($saveRecurringRow['end_date']) ? "none" : date("m/d/Y", strtotime($saveRecurringRow['end_date'])));
								$substitutions['end_date'] = (empty($endDate) ? "none" : date("m/d/Y", strtotime($endDate)));
								$substitutions['old_day_of_month'] = date("d", strtotime($saveRecurringRow['next_billing_date']));
								$substitutions['day_of_month'] = date("d", strtotime($nextBillingDate));
								$emailId = getFieldFromId("email_id", "emails", "email_code", "RECURRING_GIFT_CHANGED", "inactive = 0");
								if (!empty($emailId)) {
									$emailAddresses = array();
									$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $saveRecurringRow['designation_id']);
									while ($emailRow = getNextRow($emailSet)) {
										$emailAddresses[] = $emailRow['email_address'];
									}
									if (!empty($emailAddresses)) {
										sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
									}
								}
							}
						}
					}
				}

				ajaxResponse($returnArray);

				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if ($_GET['account'] == "saved") { ?>
            displayInfoMessage("Account changes successfully saved");
			<?php } ?>
			<?php if ($_GET['account'] == "created") { ?>
            displayInfoMessage("Account successfully created");
			<?php } ?>
            $(document).on("click",".donation-receipt",function() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=resend_donation_receipt&donation_id=" + $(this).data("donation_id"));
            });
            $(".end-now").click(function () {
                $(this).closest("tr").find(".end-date").val("<?= date("m/d/Y") ?>");
                return false;
            });
            $(".add-payment-method").click(function () {
                $(this).hide();
                $("#_button_paragraph").hide();
                $("#new_payment_method_section").show();
                $("#billing_first_name").focus();
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            });
            $("#_cancel_new_payment_method").click(function () {
                $(".add-payment-method").show();
                $("#_button_paragraph").show();
                $("#new_payment_method_section").hide();
                return false;
            });
            $("#_save_new_payment_method").click(function () {
                if ($("#_new_payment_method_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_payment_method", $("#_new_payment_method_form").serialize(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?panel=payment";
                        }
                    });
                }
                return false;
            });
            $("#billing_country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            }).trigger("change");
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            })
            $(document).on("click", ".edit-account", function () {
                $("#account_id").val($(this).closest("tr").data("account_id"));
                $("#_account_description").html($(this).closest("tr").find("td.account-label").html());
                $("#expiration_month,#expiration_year,#billing_address_1,#billing_postal_code").val("");
                $('#_edit_account_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 380,
                    title: 'Account Information',
                    buttons: {
                        Save: function (event) {
                            if ($("#_edit_account").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=update_payment_method", $("#_edit_account").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_edit_account_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_edit_account_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("change blur", ".filter-field", function () {
                var element = $(this);
                setTimeout(function () {
                    if (element.is(".formFieldError")) {
                        element.val("");
                        element.removeClass("formFieldError");
                    }
                    filterDonations();
                }, 100);
            });
            $(".mailing-list").click(function () {
                var mailingListId = $(this).data("mailing_list_id");
                if ($(this).prop("checked")) {
                    $("#mailing_list_id_" + mailingListId).val("Y");
                } else {
                    $("#mailing_list_id_" + mailingListId).val("N");
                }
            });
            $(".category").click(function () {
                var categoryId = $(this).data("category_id");
                if ($(this).prop("checked")) {
                    $("#category_id_" + categoryId).val("Y");
                } else {
                    $("#category_id_" + categoryId).val("N");
                }
            });
            $("#_logout_button").click(function () {
                document.location = "/logout.php";
            });
            $(".account-button").click(function () {
                $("#_cancel_new_payment_method").trigger("click");
                if ($("#_edit_form").validationEngine("validate")) {
                    $(".account-section").hide();
                    $("#" + $(this).attr("id").replace("button", "section")).show();
                    var saveable = $("#" + $(this).attr("id").replace("button", "section")).data("saveable");
                    if (saveable == "yes") {
                        $("#_button_paragraph").show();
                    } else {
                        $("#_button_paragraph").hide();
                    }
                }
            });
			<?php if ($GLOBALS['gLoggedIn']) { ?>
			<?php
			if (empty($_GET['panel'])) {
			?>
            $(".account-section").hide();
            $(".account-button:first-child").trigger("click");
			<?php
			} else {
			$panel = $_GET['panel'];
			?>
            if ($("#_<?= htmlText($panel) ?>_button.account-button").length > 0) {
                $("#_<?= htmlText($panel) ?>_button").trigger("click");
            } else {
                $(".account-section").hide();
                $(".account-button:first-child").trigger("click");
            }
			<?php
			}
			?>
			<?php } ?>
            $("#country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_state_row").hide();
                    $("#_state_select_row").show();
                } else {
                    $("#_state_row").show();
                    $("#_state_select_row").hide();
                }
            }).trigger("change");
            $("#state_select").change(function () {
                $("#state").val($(this).val());
            })
            $("#user_name").blur(function () {
                $("#_user_name_message").removeClass("info-message").removeClass("error-message").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val() + "&user_id=<?= $GLOBALS['gUserId'] ?>", function (returnArray) {
                        $("#_user_name_message").removeClass("info-message").removeClass("error-message");
                        if ("info_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['info_user_name_message']).addClass("info-message");
                        }
                        if ("error_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['error_user_name_message']).addClass("error-message");
                            $("#user_name").val("");
                            $("#user_name").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $("#_user_name_message").val("");
                }
            });
            $(document).on("tap click", "#_submit_form", function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#_submit_form").hide();
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=<?= ($GLOBALS['gLoggedIn'] ? "update_account" : "create_account") ?>", $("#_edit_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#_submit_form").show();
                            return;
                        }
                        if (!("error_message" in returnArray)) {
                            document.location = "<?= (empty($_GET['referrer']) ? $GLOBALS['gLinkUrl'] . ($GLOBALS['gLoggedIn'] ? "?account=saved" : "?account=created") : $_GET['referrer']) ?>";
                        } else {
                            $("#_submit_form").show();
                            if ("panel_name" in returnArray) {
                                $("#_" + returnArray['panel_name'] + "_button").trigger("click");
                            }
                        }
                    });
                }
                return false;
            });
            $("#same_address").click(function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                } else {
                    $("#_billing_address").removeClass("hidden");
                }
            });
            $("#_edit_form").find("input[type!=hidden]:not([readonly='readonly']):not([disabled='disabled']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])")[0].focus();
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function filterDonations() {
                var startDate = "1900-01-01";
                var endDate = "2500-01-01";
                if ($("#filter_start").val() != "") {
                    startDate = $.formatDate($("#filter_start").val(), "yyyy-MM-dd");
                }
                if ($("#filter_end").val() != "") {
                    endDate = $.formatDate($("#filter_end").val(), "yyyy-MM-dd");
                }
                var designationId = $("#filter_designation").val();
                var totalDonations = 0;
                $(".donation-row").show().each(function () {
                    var thisDesignation = $(this).find(".donation-designation-id").val();
                    var thisDonationDate = $(this).find(".donation-date").val();
                    if ((designationId != "" && thisDesignation != designationId) || thisDonationDate < startDate || thisDonationDate > endDate) {
                        $(this).hide();
                    } else {
                        totalDonations += parseFloat($(this).find(".donation-amount").html().replace(new RegExp(",", "g"), ""));
                    }
                });
                $("#total_donations").html(RoundFixed(totalDonations, 2));
            }
        </script>
		<?php
	}

	function mainContent() {
		$pageContent = $this->getPageData("content");
		echo $pageContent;
		$mailingLists = array();
		$resultSet = executeQuery("select * from mailing_lists where client_id = ? and inactive = 0 and " .
			"internal_use_only = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$mailingLists[] = array("mailing_list_id" => $row['mailing_list_id'], "description" => $row['description']);
		}
		$categories = array();
		$resultSet = executeQuery("select * from categories where client_id = ? and inactive = 0 and " .
			"internal_use_only = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categories[] = array("category_id" => $row['category_id'], "description" => $row['description']);
		}
		$donationsArray = array();
		$resultSet = executeQuery("select * from donations where contact_id = ? and associated_donation_id is null order by donation_date desc", $GLOBALS['gUserRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$donationsArray[] = $row;
		}
		$recurringArray = array();
		$resultSet = executeQuery("select *,(select max(donation_date) from donations where recurring_donation_id = recurring_donations.recurring_donation_id) last_billing_date," .
			"(select account_label from accounts where account_id = recurring_donations.account_id) account_label," .
			"(select account_number from accounts where account_id = recurring_donations.account_id) account_number " .
			"from recurring_donations where account_id is not null and contact_id = ? and " .
			"(end_date is null or end_date >= current_date) order by start_date,recurring_donation_id", $GLOBALS['gUserRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
			if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
				$recurringArray[] = $row;
			}
		}
		$accountsArray = array();
		$resultSet = executeQuery("select * from accounts where account_token is not null and inactive = 0 and contact_id = ? order by account_label", $GLOBALS['gUserRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
			if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
				$row['merchant_account_id'] = $accountMerchantAccountId;
				$recurringDonationId = getFieldFromId("recurring_donation_id", "recurring_donations", "account_id", $row['account_id'],
					"(end_date is null or end_date > current_date) and (start_date is null or start_date <= current_date)");
				$row['recurring_donation_id'] = $recurringDonationId;
				$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']);
				$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodTypeId);
				$row['payment_method_type_id'] = $paymentMethodTypeId;
				$row['payment_method_type_code'] = $paymentMethodTypeCode;
				$accountsArray[] = $row;
			}
		}
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$contactFiles = array();
		$resultSet = executeQuery("select contact_files.description,contact_files.file_id from contact_files join files using (file_id) where contact_files.contact_id = ? order by date_uploaded desc", $GLOBALS['gUserRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$contactFiles[] = array("file_id" => $row['file_id'], "description" => $row['description']);
		}
		$eCommerce = eCommerce::getEcommerceInstance();
		?>
        <div id="_account_form">

			<?php if ($GLOBALS['gLoggedIn']) { ?>
                <div id="_button_div">
                    <button tabindex="20" class="account-button" id="_contact_button">Contact</button>
                    <button tabindex="20" class="account-button" id="_account_button">Account</button>
					<?php if (count($mailingLists) > 0 || count($categories) > 0) { ?>
                        <button tabindex="20" class="account-button" id="_opt_in_button">Opt In</button>
					<?php } ?>
					<?php if (count($donationsArray) > 0) { ?>
                        <button tabindex="20" class="account-button" id="_donations_button">Donations</button>
					<?php } ?>
					<?php if (!empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
						<?php if (count($recurringArray) > 0) { ?>
                            <button tabindex="20" class="account-button" id="_recurring_button">Recurring Donations</button>
						<?php } ?>
                        <button tabindex="20" class="account-button" id="_payment_button">Payment Methods</button>
					<?php } ?>
					<?php if (count($contactFiles) > 0) { ?>
                        <button tabindex="20" class="account-button" id="_files_button">Files</button>
					<?php } ?>
                    <button tabindex="20" class="account-button" id="_logout_button">Logout</button>
                </div> <!-- button_div -->
			<?php } ?>
            <form name="_edit_form" id="_edit_form" method="POST">
                <div class="account-section" id="_contact_section" data-saveable="yes">

					<?php if ($GLOBALS['gLoggedIn'] || empty($pageContent)) { ?>
                        <h2>Contact Information</h2>
						<?php if ($GLOBALS['gLoggedIn']) { ?>
                            <p id="_update_account">If you are changing your address, be sure to update the billing address on any saved payment methods that also changed.</p>
						<?php } ?>
					<?php } ?>
                    <div class="form-line" id="_first_name_row">
                        <label for="first_name" class="required-label">First Name</label>
                        <input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" size="25" maxlength="25" id="first_name" name="first_name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_last_name_row">
                        <label for="last_name" class="required-label">Last Name</label>
                        <input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="35" id="last_name" name="last_name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_business_name_row">
                        <label for="business_name">Business Name</label>
                        <input tabindex="10" type="text" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="35" id="business_name" name="business_name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_address_1_row">
                        <label for="address_1" class="required-label">Address</label>
                        <input tabindex="10" type="text" autocomplete='chrome-off' autocomplete='off' class="autocomplete-address validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="60" id="address_1" name="address_1" value="<?= htmlText($GLOBALS['gUserRow']['address_1']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_address_2_row">
                        <label for="address_2" class=""></label>
                        <input tabindex="10" type="text" class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="address_2" name="address_2" value="<?= htmlText($GLOBALS['gUserRow']['address_2']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_city_row">
                        <label for="city" class="required-label">City</label>
                        <input tabindex="10" type="text" class="validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="60" id="city" name="city" value="<?= htmlText($GLOBALS['gUserRow']['city']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_state_row">
                        <label for="state" class="">State</label>
                        <input tabindex="10" type="text" class="validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#country_id').val() == 1000" size="10" maxlength="30" id="state" name="state" value="<?= htmlText($GLOBALS['gUserRow']['state']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_state_select_row">
                        <label for="state_select" class="">State</label>
                        <select tabindex="10" id="state_select" name="state_select" class="validate[required]" data-conditional-required="$('#country_id').val() == 1000">
                            <option value="">[Select]</option>
							<?php
							foreach (getStateArray() as $stateCode => $state) {
								?>
                                <option value="<?= $stateCode ?>"<?= ($GLOBALS['gUserRow']['state'] == $stateCode ? " selected" : "") ?>><?= htmlText($state) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_postal_code_row">
                        <label for="postal_code" class="">Postal Code</label>
                        <input tabindex="10" type="text" class="validate[required] uppercase" size="10" maxlength="10" data-conditional-required="$('#country_id').val() == 1000" id="postal_code" name="postal_code" value="<?= htmlText($GLOBALS['gUserRow']['postal_code']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_country_id_row">
                        <label for="country_id" class="">Country</label>
                        <select tabindex="10" class="validate[required]" id="country_id" name="country_id">
							<?php
							foreach (getCountryArray(true) as $countryId => $countryName) {
								?>
                                <option <?= ($GLOBALS['gUserRow']['country_id'] == $countryId ? " selected" : "") ?> value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_email_address_row">
                        <label for="email_address" class="required-label">Email</label>
                        <input tabindex="10" type="text" class="validate[required,custom[email]]" size="30" maxlength="60" id="email_address" name="email_address" value="<?= htmlText($GLOBALS['gUserRow']['email_address']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_primary_phone_number_row">
                        <label for="primary_phone_number" class="">Primary Phone</label>
                        <input tabindex="10" type="text" class="validate[custom[phone]]" size="20" maxlength="25" id="primary_phone_number" name="primary_phone_number" value="<?= htmlText(Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'],"Primary",false)) ?>">
                        <div class='clear-div'></div>
                    </div>

					<?php if ($GLOBALS['gLoggedIn']) { ?>
                </div> <!-- _contact_section -->

                <div class="account-section" id="_account_section" data-saveable="yes">
					<?php } ?>
                    <h2>Account Information</h2>

                    <div class="form-line" id="_user_name_row">
                        <label for="user_name" class="required-label">User Name</label>
                        <input tabindex="10" type="text" autocomplete="chrome-off" autocomplete="off" class="code-value allow-dash lowercase validate[<?= ($GLOBALS['gLoggedIn'] ? "" : "required") ?>]" size="40" maxlength="40" id="user_name" name="user_name" value="<?= $GLOBALS['gUserRow']['user_name'] ?>">
                        <p id="_user_name_message"></p>
                        <div class='clear-div'></div>
                    </div>

					<?php if ($GLOBALS['gLoggedIn']) { ?>
                        <div class="form-line" id="_current_password_row">
                            <label for="current_password">Current Password</label>
                            <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[required]" data-conditional-required="$('#password').val() != ''" type="password" size="40" maxlength="40" id="current_password" name="current_password" value=""><span class='fad fa-eye show-password'></span>
                            <div class='clear-div'></div>
                        </div>
					<?php } ?>

                    <div class="form-line" id="_password_row">
                        <label for="password" class="<?= ($GLOBALS['gLoggedIn'] ? "" : "required-label") ?>">New Password</label>
						<?php
						$minimumPasswordLength = getPreference("minimum_password_length");
						if (empty($minimumPasswordLength)) {
							$minimumPasswordLength = 10;
						}
						?>
                        <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]]<?= ($GLOBALS['gLoggedIn'] ? "" : ",required") ?>] password-strength" type="password" size="40" maxlength="40" id="password" name="password" value=""><span class='fad fa-eye show-password'></span>
                        <div class='strength-bar-div hidden' id='password_strength_bar_div'>
                            <p class='strength-bar-label' id='password_strength_bar_label'></p>
                            <div class='strength-bar' id='password_strength_bar'></div>
                        </div>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_password_again_row">
                        <label for="password_again" class="<?= ($GLOBALS['gLoggedIn'] ? "" : "required-label") ?>">Re-enter New Password</label>
                        <input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="password" class="validate[equals[password]]" size="40" maxlength="40" id="password_again" name="password_again" value=""><span class='fad fa-eye show-password'></span>
                        <div class='clear-div'></div>
                    </div>

					<?php if (getPreference("PCI_COMPLIANCE")) { ?>
                        <p>For security reasons, we need you to select and answer a couple questions. If you ever forget your password, you'll need to remember which questions you chose and answer them.</p>
                        <div class="form-line create-user" id="_security_question_id_row">
                            <label for="security_question_id" class="required-label">Security Question</label>
                            <select tabindex="10" class="validate[required]" id="security_question_id" name="security_question_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from security_questions where internal_use_only = 0 and inactive = 0 order by sort_order,security_question");
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option <?= ($GLOBALS['gUserRow']['security_question_id'] == $row['security_question_id'] ? " selected" : "") ?> value="<?= $row['security_question_id'] ?>"><?= htmlText($row['security_question']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line create-user" id="_answer_text_row">
                            <label for="answer_text" class="required-label">Answer</label>
                            <input tabindex="10" type="text" class="validate[required,minSize[3]]" size="30" maxlength="100" id="answer_text" name="answer_text" placeholder="Answer" value="<?= htmlText($GLOBALS['gUserRow']['answer_text']) ?>">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line create-user" id="_secondary_security_question_id_row">
                            <label for="secondary_security_question_id" class="required-label">Security Question</label>
                            <select tabindex="10" class="validate[required]" id="secondary_security_question_id" name="secondary_security_question_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from security_questions where internal_use_only = 0 and inactive = 0 order by sort_order,security_question");
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option <?= ($GLOBALS['gUserRow']['secondary_security_question_id'] == $row['security_question_id'] ? " selected" : "") ?> value="<?= $row['security_question_id'] ?>"><?= htmlText($row['security_question']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line create-user" id="_secondary_answer_text_row">
                            <label for="secondary_answer_text" class="required-label">Answer</label>
                            <input tabindex="10" type="text" class="validate[required,minSize[3]]" size="30" maxlength="100" id="secondary_answer_text" name="secondary_answer_text" placeholder="Answer" value="<?= htmlText($GLOBALS['gUserRow']['secondary_answer_text']) ?>">
                            <div class='clear-div'></div>
                        </div>

					<?php } ?>

                </div> <!-- account_section -->

				<?php if ($GLOBALS['gLoggedIn']) { ?>
					<?php if (count($mailingLists) > 0 || count($categories) > 0) { ?>
                        <div class="account-section" id="_opt_in_section" data-saveable="yes">
							<?php if (count($mailingLists) > 0) { ?>
                                <h2>Opt-In Mailing Lists</h2>
								<?php
								foreach ($mailingLists as $mailingListInfo) {
									$contactMailingListId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "mailing_list_id", $mailingListInfo['mailing_list_id'], "contact_id = ? and " .
										"(date_opted_out is null or date_opted_out > current_date)", $GLOBALS['gUserRow']['contact_id']);
									$optedIn = (!empty($contactMailingListId));
									?>
                                    <div class="form-line checkbox-input" id="_mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>_row">
                                        <input type="hidden" id="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>" name="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>" value="<?= ($optedIn ? "Y" : "N") ?>">
                                        <input tabindex="10" type="checkbox" class="mailing-list" id="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>_checkbox" data-mailing_list_id="<?= $mailingListInfo['mailing_list_id'] ?>"<?= ($optedIn ? " checked" : "") ?> value="1">
                                        <label class="checkbox-label" for="mailing_list_id_<?= $mailingListInfo['mailing_list_id'] ?>_checkbox"><?= htmlText($mailingListInfo['description']) ?></label>
                                        <div class='clear-div'></div>
                                    </div>
									<?php
								}
							}
							if (count($categories) > 0) {
								?>
                                <h2>Account Settings</h2>
								<?php
								foreach ($categories as $categoryInfo) {
									$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $categoryInfo['category_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
									$optedIn = (!empty($contactCategoryId));
									?>
                                    <div class="form-line checkbox-input" id="_category_id_<?= $categoryInfo['category_id'] ?>_row">
                                        <input type="hidden" id="category_id_<?= $categoryInfo['category_id'] ?>" name="category_id_<?= $categoryInfo['category_id'] ?>" value="<?= ($optedIn ? "Y" : "N") ?>">
                                        <input tabindex="10" type="checkbox" id="category_id_<?= $categoryInfo['category_id'] ?>_checkbox" class="category" data-category_id="<?= $categoryInfo['category_id'] ?>"<?= ($optedIn ? " checked" : "") ?> value="1">
                                        <label class="checkbox-label" for="category_id_<?= $categoryInfo['category_id'] ?>_checkbox"><?= htmlText($categoryInfo['description']) ?></label>
                                        <div class='clear-div'></div>
                                    </div>
									<?php
								}
							}
							?>
                        </div> <!-- opt_in_section -->
					<?php } ?>

					<?php if (count($donationsArray) > 0) { ?>
                        <div class="account-section" id="_donations_section">
							<?php
							$nameAddressBlock = $GLOBALS['gUserRow']['contact_id'] . "<br>" . getDisplayName($GLOBALS['gUserRow']['contact_id']);
							if (!empty($GLOBALS['gUserRow']['address_1'])) {
								$nameAddressBlock .= "<br>" . $GLOBALS['gUserRow']['address_1'];
							}
							if (!empty($GLOBALS['gUserRow']['address_2'])) {
								$nameAddressBlock .= "<br>" . $GLOBALS['gUserRow']['address_2'];
							}
							if (!empty($GLOBALS['gUserRow']['city']) || !empty($GLOBALS['gUserRow']['state']) || !empty($GLOBALS['gUserRow']['postal_code'])) {
								$cityLine = $GLOBALS['gUserRow']['city'];
								if (!empty($GLOBALS['gUserRow']['state'])) {
									$cityLine .= (empty($cityLine) ? "" : ", ") . $GLOBALS['gUserRow']['state'];
								}
								if (!empty($GLOBALS['gUserRow']['postal_code'])) {
									$cityLine .= (empty($cityLine) ? "" : " ") . $GLOBALS['gUserRow']['postal_code'];
								}
								$nameAddressBlock .= "<br>" . $cityLine;
							}
							?>
                            <h2>Past Giving</h2>
                            <p><?= $nameAddressBlock ?></p>
                            <p>Filter: Donation Date&nbsp;&nbsp;<input type="text" size="12" class="filter-field validate[custom[date]]" name="filter_start" id="filter_start">&nbsp;&nbsp;through&nbsp;&nbsp;<input type="text" size="12" class="filter-field validate[custom[date]]" name="filter_end" id="filter_end"></p>
                            <p>Filter: For&nbsp;&nbsp;<select class="filter-field" id="filter_designation" name="filter_designation">
                                    <option value="">[All]</option>
									<?php
									$selectDesignations = array();
									foreach ($donationsArray as $donationRow) {
										if (!array_key_exists($donationRow['designation_id'], $selectDesignations)) {
											$selectDesignations[$donationRow['designation_id']] = $donationRow['designation_id'];
										}
									}
									$resultSet = executeQuery("select * from designations where designation_id in (" . implode(",", $selectDesignations) . ") order by sort_order,description");
									while ($row = getNextRow($resultSet)) {
										?>
                                        <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
										<?php
									}
									?>
                                </select></p>
                            <table>
                                <tr>
                                    <th>Date</th>
                                    <th>For</th>
                                    <th>Method</th>
                                    <th>Ref#</th>
                                    <th>Amount</th>
                                    <th>Anonymous</th>
                                    <th></th>
                                </tr>
								<?php
								$totalDonations = 0;
								foreach ($donationsArray as $donationRow) {
									$totalDonations += $donationRow['amount'];
									$designationRow = getRowFromId("designations", "designation_id", $donationRow['designation_id']);
									?>
                                    <tr class="donation-row">
                                        <td><input type="hidden" class="donation-date" value="<?= $donationRow['donation_date'] ?>">
                                            <input type="hidden" class="donation-designation-id" value="<?= $donationRow['designation_id'] ?>">
											<?= date("m/d/Y", strtotime($donationRow['donation_date'])) ?>
                                        </td>
                                        <td><?= htmlText($designationRow['designation_code'] . " - " . $designationRow['description']) . (empty($designationRow['not_tax_deductible']) ? "" : " (NOT tax-deductible)") ?></td>
                                        <td><?= htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id'])) ?></td>
                                        <td><?= htmlText($donationRow['reference_number']) ?></td>
                                        <td class="align-right donation-amount"><?= number_format($donationRow['amount'], 2) ?></td>
                                        <td><?= ($donationRow['anonymous_gift'] ? "YES" : "") ?></td>
                                        <td class='align-center'><span class='fad fa-paper-plane donation-receipt' data-donation_id='<?= $donationRow['donation_id'] ?>' title="Resend Receipt"></span></td>
                                    </tr>
									<?php
								}
								?>
                                <tr id="total_donations_row">
                                    <td colspan="4">Total</td>
                                    <td class="align-right" id="total_donations"><?= number_format($totalDonations, 2) ?></td>
                                </tr>
                            </table>
							<?= makeHtml($this->getFragment("donation_terms")) ?>
                        </div> <!-- _donations_section -->
					<?php } ?>

					<?php if (count($recurringArray) > 0) { ?>
                        <div class="account-section" id="_recurring_section" data-saveable="yes">
                            <h2>Recurring Gifts</h2>
                            <p>Changes may be made to the amount, end date and day of the month. For any other changes, set the end date to today and create a new recurring gift on the giving page.</p>
                            <table>
                                <tr>
                                    <th>Started</th>
                                    <th>For</th>
                                    <th>Project</th>
                                    <th>Account</th>
                                    <th>Amount</th>
                                    <th>Last Billing</th>
                                    <th>Next Billing</th>
                                    <th>End Date</th>
                                    <th>Memo/Notes</th>
                                    <th></th>
                                </tr>
								<?php
								foreach ($recurringArray as $recurringRow) {
									?>
                                    <tr>
                                        <td><input type="hidden" id="recurring_donation_id_<?= $recurringRow['recurring_donation_id'] ?>" name="recurring_donation_id_<?= $recurringRow['recurring_donation_id'] ?>" value="<?= $recurringRow['recurring_donation_id'] ?>"><?= date("m/d/Y", strtotime($recurringRow['start_date'])) ?></td>
                                        <td><?= htmlText(getFieldFromId("description", "designations", "designation_id", $recurringRow['designation_id'])) ?></td>
										<?php
										$projects = array();
										$resultSet = executeQuery("select * from designation_projects where designation_id = ?", $recurringRow['designation_id']);
										while ($row = getNextRow($resultSet)) {
											$projects[] = $row['project_name'];
										}
										?>
                                        <td>
											<?php if (count($projects) > 0) { ?>
                                                <select tabindex="10" name="project_name_<?= $recurringRow['recurring_donation_id'] ?>" id="project_name_<?= $recurringRow['recurring_donation_id'] ?>">
                                                    <option value="">[No specific project]</option>
													<?php foreach ($projects as $projectName) { ?>
                                                        <option <?= ($projectName == $recurringRow['project_name'] ? " selected" : "") ?> value="<?= htmlText(str_replace('"', '', $projectName)) ?>"><?= htmlText($projectName) ?></option>
													<?php } ?>
                                                </select>
											<?php } ?>
                                        </td>
                                        <td><select tabindex="10" name="account_id_<?= $recurringRow['recurring_donation_id'] ?>" id="account_id_<?= $recurringRow['recurring_donation_id'] ?>">
												<?php
												$merchantAccountId = eCommerce::getAccountMerchantAccount($recurringRow['account_id']);
												foreach ($accountsArray as $accountsRow) {
													$thisMerchantAccountId = $accountsRow['merchant_account_id'];
													if ($thisMerchantAccountId == $merchantAccountId && $accountsRow['inactive'] == 0 && !empty($accountsRow['account_token'])) {
														?>
                                                        <option <?= ($accountsRow['account_id'] == $recurringRow['account_id'] ? " selected" : "") ?> value="<?= $accountsRow['account_id'] ?>"><?= htmlText(empty($accountsRow['account_label']) ? $accountsRow['account_number'] : $accountsRow['account_label']) ?></option>
													<?php } ?>
												<?php } ?>
                                            </select></td>
                                        <td><input tabindex="10" type="text" size="10" class="align-right validate[required,min[5],custom[number]]" data-decimal-places="2" id="amount_<?= $recurringRow['recurring_donation_id'] ?>" name="amount_<?= $recurringRow['recurring_donation_id'] ?>" value="<?= number_format($recurringRow['amount'], 2) ?>"></td>
                                        <td class="align-center"><?= (empty($recurringRow['last_billing_date']) ? "" : date("m/d/Y", strtotime($recurringRow['last_billing_date']))) ?></td>
                                        <td><input tabindex="10" type="text" size="12" class="end-date validate[custom[date],required]" id="next_billing_date_<?= $recurringRow['recurring_donation_id'] ?>" name="next_billing_date_<?= $recurringRow['recurring_donation_id'] ?>" value="<?= date("m/d/Y", strtotime($recurringRow['next_billing_date'])) ?>"></td>
                                        <td><input tabindex="10" type="text" size="12" class="end-date validate[custom[date]]" id="end_date_<?= $recurringRow['recurring_donation_id'] ?>" name="end_date_<?= $recurringRow['recurring_donation_id'] ?>" value="<?= (empty($recurringRow['end_date']) ? "" : date("m/d/Y", strtotime($recurringRow['end_date']))) ?>"></td>
                                        <td><?= $recurringRow['notes'] ?></td>
                                        <td>
                                            <button class="end-now">Stop Now</button>
                                        </td>
                                    </tr>
									<?php
								}
								?>
                            </table>
                        </div> <!-- _recurring_section -->
					<?php } ?>

                    <div class="account-section" id="_payment_section" data-saveable="yes">
                        <h2>Payment Methods</h2>
						<?php if (count($recurringArray) > 0) { ?>
                            <p>Making a payment method inactive will also end any recurring gifts using that payment method.</p>
						<?php } ?>
                        <table id="accounts_table">
                            <tr>
                                <th>Label</th>
                                <th>Payment Method</th>
                                <th>Expiration</th>
                                <th>Used in Recurring Gifts?</th>
                                <th>Make Inactive</th>
                                <th></th>
                            </tr>
							<?php
							foreach ($accountsArray as $accountsRow) {
								if ($accountsRow['inactive'] == 0 && !empty($accountsRow['account_token'])) {
									?>
                                    <tr data-account_id="<?= $accountsRow['account_id'] ?>">
                                        <td class="account-label"><?= htmlText(empty($accountsRow['account_label']) ? $accountsRow['account_number'] : $accountsRow['account_label']) ?></td>
                                        <td class="account-type"><?= htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $accountsRow['payment_method_id'])) ?></td>
                                        <td class="account-expiration align-center"><?= htmlText(empty($accountsRow['expiration_date']) ? "" : date("m/y", strtotime($accountsRow['expiration_date']))) ?></td>
                                        <td class="highlighted-text align-center"><?= (empty($accountsRow['recurring_donation_id']) ? "no" : "YES") ?></td>
                                        <td class="align-center"><input tabindex="10" type="checkbox" id="account_id_<?= $accountsRow['account_id'] ?>" name="account_id_<?= $accountsRow['account_id'] ?>" value="1"></td>
                                        <td class="align-center"><?php if ($accountsRow['payment_method_type_code'] == "CREDIT_CARD" && !empty($accountsRow['account_token'])) { ?>
                                                <button class="edit-account">Update Account Information</button><?php } ?></td>
                                    </tr>
									<?php
								}
							}
							?>
                            <tr>
                                <td class='add-payment-method' colspan="5">Click here to add a new payment method</td>
                            </tr>
                        </table>
                    </div> <!-- _payment_section -->
				<?php } ?>

				<?php if (count($contactFiles) > 0) { ?>
                    <div class="account-section" id="_files_section" data-saveable="no">
                        <h2>Files</h2>
						<?php
						foreach ($contactFiles as $contactFileInfo) {
							?>
                            <p><a href="/download.php?id=<?= $contactFileInfo['file_id'] ?>"><?= htmlText($contactFileInfo['description']) ?></a></p>
							<?php
						}
						?>
                    </div> <!-- _files_section -->
				<?php } ?>

                <p class="error-message" id="button_error_message"></p>
                <p id="_button_paragraph" class="align-center">
                    <button tabindex="10" id="_submit_form"><?= ($GLOBALS['gLoggedIn'] ? "Save Changes" : "Create Account") ?></button>
                </p>
            </form>
        </div> <!-- account_form -->
        <div id="new_payment_method_section">
            <div id="_new_payment_method">
                <form id="_new_payment_method_form" method="POST">
                    <p class="error-message">Be sure to make payment methods that are no longer needed inactive.</p>
                    <h2>Billing Information</h2>
                    <div class="form-line" id="_billing_first_name_row">
                        <label for="billing_first_name" class="required-label">First Name</label>
                        <input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_billing_last_name_row">
                        <label for="billing_last_name" class="required-label">Last Name</label>
                        <input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_billing_business_name_row">
                        <label for="billing_business_name">Business Name</label>
                        <input tabindex="10" type="text" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line checkbox-input" id="_same_address_row">
                        <label class=""></label>
                        <input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" value="1"><label class="checkbox-label" for="same_address">Billing address is same as primary address</label>
                        <div class='clear-div'></div>
                    </div>

                    <div id="_billing_address" class="hidden">
                        <div class="form-line" id="_billing_address_1_row">
                            <label for="billing_address_1" class="required-label">Address</label>
                            <input tabindex="10" type="text" data-prefix="billing_" autocomplete='chrome-off' autocomplete='off' class="autocomplete-address validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_address_2_row">
                            <label for="billing_address_2" class=""></label>
                            <input tabindex="10" type="text" class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_city_row">
                            <label for="billing_city" class="required-label">City</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_state_row">
                            <label for="billing_state" class="">State</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_state_select_row">
                            <label for="billing_state_select" class="">State</label>
                            <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#billing_country_id').val() == 1000">
                                <option value="">[Select]</option>
								<?php
								foreach (getStateArray() as $stateCode => $state) {
									?>
                                    <option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_postal_code_row">
                            <label for="billing_postal_code" class="">Postal Code</label>
                            <input tabindex="10" type="text" class="validate[required] uppercase" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_country_id_row">
                            <label for="billing_country_id" class="">Country</label>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
								<?php
								foreach (getCountryArray() as $countryId => $countryName) {
									?>
                                    <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>
                    </div>

                    <div class="form-line" id="_payment_method_id_row">
                        <label for="payment_method_id" class="">Payment Method</label>
                        <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
								"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
								($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
								"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
								(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
								"inactive = 0 and internal_use_only = 0 and client_id = ? and payment_method_type_id in " .
								"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
								"client_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['payment_method_id'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="payment-method-fields" id="payment_method_credit_card">
                        <div class="form-line" id="_account_number_row">
                            <label for="account_number" class="">Card Number</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_expiration_month_row">
                            <label for="expiration_month" class="">Expiration Date</label>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_month" name="expiration_month">
                                <option value="">[Month]</option>
								<?php
								for ($x = 1; $x <= 12; $x++) {
									?>
                                    <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
									<?php
								}
								?>
                            </select>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_year" name="expiration_year">
                                <option value="">[Year]</option>
								<?php
								for ($x = 0; $x < 12; $x++) {
									$year = date("Y") + $x;
									?>
                                    <option value="<?= $year ?>"><?= $year ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_card_code_row">
                            <label for="card_code" class="">Security Code</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="card_code" name="card_code" placeholder="CVV Code" value="">
                            <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img alt="cvv image" id="cvv_image" src="/images/cvv_code.gif"></a>
                            <div class='clear-div'></div>
                        </div>
                    </div> <!-- payment_method_credit_card -->

                    <div class="payment-method-fields" id="payment_method_bank_account">
                        <div class="form-line" id="_routing_number_row">
                            <label for="routing_number" class="">Bank Routing Number</label>
                            <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_bank_account_number_row">
                            <label for="bank_account_number" class="">Account Number</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                            <div class='clear-div'></div>
                        </div>
                    </div> <!-- payment_method_bank_account -->

                    <div class="form-line" id="_account_label_row">
                        <label for="account_label" class="">Account Nickname</label>
                        <span class="help-label">for future reference</span>
                        <input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_set_recurring_row">
                        <label for="set_recurring" class=""></label>
                        <input tabindex="10" type="checkbox" checked="checked" class="" id="set_recurring" name="set_recurring" value="1"><label class="checkbox-label" for="set_recurring">Use this new payment method on all active recurring donations</label>
                        <div class='clear-div'></div>
                    </div>

                    <p class="align-center">
                        <button tabindex="10" id="_save_new_payment_method">Save</button>&nbsp;<button tabindex="10" id="_cancel_new_payment_method">Cancel</button>
                    </p>

                </form>
            </div> <!-- new_payment_method -->
        </div> <!-- new_payment_method_section -->

        <div class='clear-div'></div>

		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #new_payment_method_section {
                display: none;
                margin: 20px auto;
                border: 1px solid rgb(150, 150, 150);
                padding: 10px;
                width: 600px;
            }
            #new_payment_method_section h2 {
                text-align: center;
                margin-bottom: 10px;
            }
            #_button_paragraph {
                margin-top: 20px;
            }
            #_account_form {
                text-align: center;
            }
            .account-section {
                display: inline-block;
                margin: 20px auto;
                text-align: left;
            }
            .account-section h2 {
                text-align: center;
                padding-bottom: 10px;
            }
            .account-section:first-child {
                display: inline-block;
            }
            #_account_form table {
                border: 1px solid rgb(150, 150, 150);
                margin: 0 auto;
            }
            #_account_form table td {
                border: 1px solid rgb(150, 150, 150);
                padding: 3px 10px;
            }
            #_account_form table th {
                border: 1px solid rgb(150, 150, 150);
                padding: 3px 10px;
            }
            #_account_form #_error_message {
                position: relative;
                top: 0;
                bottom: 0;
                padding-top: 10px;
                display: none;
            }
            #total_donations_row td {
                font-weight: bold;
            }
            #accounts_table td {
                height: 40px;
                vertical-align: center;
            }
            #_edit_account_dialog .form-line p {
                margin: 10px 0 0 0;
            }
            #_edit_account_dialog .form-line p label {
                display: inline;
                float: none;
            }
            #_account_description {
                font-size: 16px;
                font-weight: bold;
            }
            #_update_account {
                max-width: 800px;
                margin-bottom: 30px;
            }
            .add-payment-method {
                background-color: rgb(200, 200, 200);
                text-align: center;
                cursor: pointer;
                font-weight: bold;
            }
            .add-payment-method:hover {
                background-color: rgb(220, 220, 220);
                color: rgb(0, 0, 100);
            }
            .payment-method-fields {
                display: none;
            }
            #cvv_image {
                position: absolute;
                top: 0;
                height: 26px;
            }
            .strength-bar-div {
                height: 16px;
                width: 200px;
                margin: 0;
                margin-top: 10px;
                display: block;
                top: 5px;
            }
            #_main_content p.strength-bar-label {
                font-size: .6rem;
                margin: 0;
            }
            .strength-bar {
                font-size: 1px;
                height: 8px;
                width: 10px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		?>
        <div id="_edit_account_dialog" class="dialog-box">
            <form id="_edit_account" enctype='multipart/form-data' method="POST">
                <input type="hidden" id="account_id" name="account_id"/>
                <p class="align-center">Account Information for<br><span id="_account_description"></span></p>
                <p id="_edit_account_error_message" class="error-message"></p>

                <div class="form-line" id="_billing_address_1_row">
                    <p><label for="billing_address_1" class="required-label">Billing Street Address</label></p>
                    <input type="text" class="validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_billing_postal_code_row">
                    <p><label for="billing_postal_code" class="">Billing Postal Code</label></p>
                    <input type="text" class="validate[required] uppercase" size="10" maxlength="10" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_expiration_month_row">
                    <p><label for="expiration_month" class="">Expiration Date</label></p>
                    <select class="validate[required]" id="expiration_month" name="expiration_month">
                        <option value="">[Month]</option>
						<?php
						for ($x = 1; $x <= 12; $x++) {
							?>
                            <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
							<?php
						}
						?>
                    </select>
                    <select class="validate[required]" id="expiration_year" name="expiration_year">
                        <option value="">[Year]</option>
						<?php
						for ($x = 0; $x < 12; $x++) {
							$year = date("Y") + $x;
							?>
                            <option value="<?= $year ?>"><?= $year ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

            </form>
        </div> <!-- _edit_account_dialog -->
		<?php
	}
}

$pageObject = new PublicAccountPage();
$pageObject->displayPage();
