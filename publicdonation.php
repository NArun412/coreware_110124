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

/* text instructions
<p>Several page text chunks can be used on the donation page. The code is important and must match the code listed here. The description is just informational and can contain anything. The value is what is used by the page.</p>
<ul>
    <li><strong>designation_label</strong> - Typically, the designation label is "Designed For". This text chunk allows the administrator to change the label used for the designation. Other options might be "What is this for" or "Donation Fund".</li>
    <li><strong>preset_amount_1</strong> and <strong>preset_amount_X</strong> - The donation page allows preset values. If preset_amount_1 is set, the preset value buttons are shown on the page. Any number of preset amounts can be included (_2, _3, _4, etc) and there will also be an "Other" button so the donor can put in a custom amount.</li>
    <li><strong>preset_text_1</strong> and <strong>preset_text_X</strong> - This is text that can be included in the button for a preset amount. The default is none so that only the amount appears in the button.</li>
    <li><strong>introduction_text</strong> - Text/HTML that appears just before the Payment Information section</li>
    <li><strong>show_designation_code_field</strong> - Typically, the designation code field is NOT included in a giving page. There are two instances where the field will appear: 1) if the number of designations that are included on the page is less than the number of designations in the designation dropdown. This can happen if some designations are in a designation group that is tagged as High Security (Internal Use Only). 2) If this text chunk is set to a non-empty value, the designation code will always appear.</li>
    <li><strong>recurring_donation_type_select</strong> - Normally, the donation types (one-time, monthly recurring, annual, etc) are set up in a radio button control. If this text chunk is set to a non-empty value, a dropdown is used instead.</li>
    <li><strong>phone_number_types</strong> - This text chunk allows the administrator to determine what phone numbers are asked for in the donation form. The default is home and cell phone numbers with neither being required. The format of the value is a comma separated list of phone number types, with an asterisk for the ones that are required, such as "*home,cell", "*home,*cell,toll-free", or "*home,cell,work".</li>
    <li><strong>finalize_section</strong> - Text/HTML to appear in the final section of the form.</li>
</ul>
*/

$GLOBALS['gPageCode'] = "PUBLICDONATION";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gForceSSL'] = true;
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	var $iUseRecaptchaV2 = false;

	function setup() {
		$useCaptcha = getPreference("USE_DONATION_CAPTCHA") && !$GLOBALS['gUserRow']['administrator_flag'];
		$this->iUseRecaptchaV2 = $useCaptcha && !$GLOBALS['gUserRow']['administrator_flag'] && !empty(getPreference("ORDER_RECAPTCHA_V2_SITE_KEY")) && !empty(getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY"));
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_projects":
				$designationId = $_GET['designation_id'];
				$returnArray['projects'] = array();
				$resultSet = executeQuery("select * from designation_projects where designation_id = ? order by project_name", $designationId);
				while ($row = getNextRow($resultSet)) {
					$returnArray['projects'][] = $row['project_name'];
				}

				$projectLabel = "Project";
				$projectNameFields = getMultipleFieldsFromId(array("project_label", "project_required"), "designation_types", "designation_type_id",
					getFieldFromId("designation_type_id", "designations", "designation_id", $designationId));
				$projectLabel = (empty($projectNameFields['project_label']) ? $projectLabel : $projectNameFields['project_label']);
				$projectRequired = (empty($projectNameFields['project_label']) ? "" : $projectNameFields['project_required']);
				$projectNameFields = getMultipleFieldsFromId(array("project_label", "project_required"), "designations", "designation_id", $designationId);
				$projectLabel = (empty($projectNameFields['project_label']) ? $projectLabel : $projectNameFields['project_label']);
				$returnArray['project_label'] = $projectLabel;
				$returnArray['project_required'] = $projectNameFields['project_required'];

				$memoLabel = "";
				$memoNameFields = getMultipleFieldsFromId(array("memo_label", "memo_required"), "designations", "designation_id", $designationId);
				$memoLabel = (empty($memoNameFields['memo_label']) ? $memoLabel : $memoNameFields['memo_label']);
				$returnArray['memo_label'] = $memoLabel;
				$returnArray['memo_required'] = $memoNameFields['memo_required'];

				$returnArray['detailed_description'] = "";
				$descriptionFields = getMultipleFieldsFromId(array("detailed_description", "image_id", "not_tax_deductible"), "designations", "designation_id", $designationId);
				if (!empty($descriptionFields['detailed_description']) || !empty($descriptionFields['image_id'])) {
					if (!empty($descriptionFields['image_id'])) {
						$groupSet = executeQuery("select * from designation_groups where designation_group_id in (select designation_group_id " .
							"from designation_group_links where designation_id = ?)", $designationId);
						while ($groupRow = getNextRow($groupSet)) {
							if (empty($groupRow['allow_image'])) {
								$descriptionFields['image_id'] = "";
								break;
							}
						}
					}
					$detailedDescription = (isHTML($descriptionFields['detailed_description']) ? htmlText($descriptionFields['detailed_description']) : makeHtml($descriptionFields['detailed_description']));
					$returnArray['detailed_description'] = (empty($descriptionFields['image_id']) ? "" : "<p id='designation_image'><img src='" . getImageFilename($descriptionFields['image_id'], array("use_cdn" => true)) . "'></p>") .
						$detailedDescription;
				}
				$resultSet = executeQuery("select * from designation_giving_goals where designation_id = ? and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) order by end_date is null,end_date", $designationId);
				if ($row = getNextRow($resultSet)) {
					$total = 0;
					$countSet = executeQuery("select sum(amount) from donations where designation_id = ? and donation_date between ? and ?", $row['designation_id'],
						(empty($row['start_date']) ? "1900-01-01" : $row['start_date']), (empty($row['end_date']) ? "2500-01-01" : $row['end_date']));
					if ($countRow = getNextRow($countSet)) {
						$total = $countRow['sum(amount)'];
					}
					if ($total < $row['amount'] && $row['amount'] > 0) {
						$percentDone = round($total * 100 / $row['amount'], 2);
						ob_start();
						?>
						<div id="goal_progress">
							<div id="goal_progress_description"><?= $row['description'] ?></div>
							<div id="goal_progress_bar"><div id="goal_progress_fill" style="width: <?= $percentDone ?>%"></div><?= $percentDone ?>% achieved</div>
						</div>
						<?php
						$progress = ob_get_clean();
						$returnArray['detailed_description'] = $progress . $returnArray['detailed_description'];
					}
				}
				if (!empty($descriptionFields['not_tax_deductible'])) {
					$taxMessage = $this->getFragment("tax_deductible_message");
					if (empty($taxMessage)) {
						$taxMessage = "This designation is NOT tax-deductible.";
					}
					$returnArray['tax_deductible_message'] = $taxMessage;
				}
				ajaxResponse($returnArray);
				break;
			case "check_designation":
				$parameters = array(makeCode($_GET['designation_code']));
				$parameters[] = $_GET['designation_code'];
				if (!empty($_GET['type'])) {
					$parameters[] = $_GET['type'];
				}
				if (!empty($_GET['group'])) {
					$parameters[] = $_GET['group'];
				}
				$parameters[] = $GLOBALS['gClientId'];
				$resultSet = executeQuery("select * from designations where (designation_code = ? or alias like ?)" .
					(empty($_GET['type']) ? "" : " and designation_type_id in (select designation_type_id from designation_types where designation_type_code = ?)") .
					(empty($_GET['group']) ? "" : " and designation_id in (select designation_id from designation_group_links where designation_group_id in (select designation_group_id from designation_groups where designation_group_code = ?))") .
					" and client_id = ? and inactive = 0 and internal_use_only = 0", $parameters);
				if ($resultSet['row_count'] == 1 && $row = getNextRow($resultSet)) {
					$returnArray['designation'] = array("designation_id" => $row['designation_id'], "description" => $row['designation_code'], "merchant_account_id" => $GLOBALS['gMerchantAccountId']);
					if (!empty($row['not_tax_deductible'])) {
						$taxMessage = $this->getFragment("tax_deductible_message");
						if (empty($taxMessage)) {
							$taxMessage = "This designation is NOT tax-deductible.";
						}
						$returnArray['designation']['tax_deductible_message'] = $taxMessage;
					}
				} else {
					$returnArray['error_message'] = "That designation cannot be found.";
				}
				ajaxResponse($returnArray);
				break;
			case "make_donation":
				$resultSet = executeQuery("select * from add_hashes where add_hash = ?", $_POST['_add_hash']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = "This donation has already been processed.";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['bank_name']) || !empty($_POST['agree_terms']) || !empty($_POST['confirm_human'])) {
					sleep(30);
					$returnArray['error_message'] = "Charge failed: Transaction declined";
					addProgramLog("Donation unable to be completed because BOT detection fields were populated.");
					ajaxResponse($returnArray);
					break;
				}
				$useCaptcha = getPreference("USE_DONATION_CAPTCHA") && !$GLOBALS['gUserRow']['administrator_flag'];
				if ($useCaptcha) {
					if (!empty(getPreference("ORDER_RECAPTCHA_V2_SITE_KEY")) && !empty(getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY"))) {
						if (empty($_POST['g-recaptcha-response'])) {
							$returnArray['error_message'] = "Invalid captcha";
							addProgramLog("Donation unable to be completed because of invalid captcha: User response token missing in request.");
							ajaxResponse($returnArray);
							break;
						}

						$ch = curl_init();
						$recaptchaVerifyURL = "https://www.google.com/recaptcha/api/siteverify?secret=" . getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY") . "&response=" . $_POST['g-recaptcha-response'];
						curl_setopt($ch, CURLOPT_URL, $recaptchaVerifyURL);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
						curl_setopt($ch, CURLOPT_POST, TRUE);
						curl_setopt($ch, CURLOPT_HEADER, 0);

						$response = curl_exec($ch);
						$decodedResponse = json_decode($response, TRUE);
						$_POST['decoded_recaptcha_response'] = $decodedResponse;

						if (empty($decodedResponse) || empty($decodedResponse['success']) || empty($decodedResponse['hostname'])) {
							$returnArray['error_message'] = "Invalid captcha";
							addProgramLog("Donation unable to be completed because of invalid captcha: Site verification failed.");
							ajaxResponse($returnArray);
							break;
						}

						$matchDomain = false;
						if (empty(strcasecmp($GLOBALS['gDomainNameRow']['domain_name'], $decodedResponse['hostname']))) {
							$matchDomain = true;
						}
						if (!$matchDomain && $GLOBALS['gDomainNameRow']['include_www'] && empty(strcasecmp($GLOBALS['gDomainNameRow']['domain_name'], str_replace("www.", "", $decodedResponse['hostname'])))) {
							$matchDomain = true;
						}
						if (!$matchDomain) {
							$returnArray['error_message'] = "Invalid captcha";
							addProgramLog("Donation unable to be completed because of invalid captcha: Invalid hostname.");
							ajaxResponse($returnArray);
							break;
						}
					} else {
						$captchaCode = getFieldFromId("captcha_code", "captcha_codes", "captcha_code_id", $_POST['captcha_code_id']);
						if (empty($_POST['captcha_code']) || strtoupper($captchaCode) != strtoupper($_POST['captcha_code'])) {
							$_SESSION['wrong_captcha_count'] = (empty($_SESSION['wrong_captcha_count']) ? 1 : $_SESSION['wrong_captcha_count'] + 1);
							saveSessionData();
							if ($_SESSION['wrong_captcha_count'] > 10) {
								blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Too many captcha failures");
							}
							$returnArray['error_message'] = "Invalid captcha code" . ($GLOBALS['gUserRow']['administrator_flag'] ? ":" . $_POST['captcha_code'] . ":" . $captchaCode : "");
							addProgramLog("Donation unable to be completed because of invalid captcha code.");
							ajaxResponse($returnArray);
							break;
						}
					}
				}

				if (!empty($_SESSION['form_displayed'])) {
					$_SESSION['form_submitted'] = date("U");
					saveSessionData();
					$timeToSubmit = $_SESSION['form_submitted'] - $_SESSION['form_displayed'];
					if ($timeToSubmit <= 10 || $timeToSubmit > 1000) {
						sleep(30);
						$returnArray['error_message'] = "Charge failed: Transaction declined";
						ajaxResponse($returnArray);
						break;
					}
				}
				if (strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME']) === false) {
					sleep(30);
					$returnArray['error_message'] = "Charge failed: Transaction declined";
					ajaxResponse($returnArray);
					break;
				}

# Check for required fields

				if ($_POST['same_address']) {
					$fields = array("first_name", "last_name", "address_1", "city", "state", "postal_code", "country_id");
					foreach ($fields as $fieldName) {
						$_POST['billing_' . $fieldName] = $_POST[$fieldName];
					}
				}
				$failedCreditCardNames = getCachedData("failed_credit_card_names", "credit_card_failures");
				if (empty($failedCreditCardNames)) {
					$failedCreditCardNames = array();
				}
				$failedCreditCardNameKey = $_POST['billing_first_name'] . ":" . $_POST['billing_last_name'];
				if (array_key_exists($failedCreditCardNameKey, $failedCreditCardNames)) {
					if ($failedCreditCardNames[$failedCreditCardNameKey] > 5) {
						sleep(60);
						$returnArray['error_message'] = "Charge failed: Transaction declined";
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$failedCreditCardNames[$failedCreditCardNameKey] = 0;
				}
				$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
					"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
						$_POST['payment_method_id']));
				$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
				$requiredFields = array(
					"amount" => array(),
					"designation_id" => array(),
					"first_name" => array(),
					"last_name" => array(),
					"address_1" => array("simplified" => ""),
					"city" => array("simplified" => ""),
					"country_id" => array("simplified" => ""),
					"email_address" => array(),
					"state" => array("country_id" => "1000", "simplified" => ""),
					"postal_code" => array("country_id" => "1000", "simplified" => ""),
					"payment_method_id" => array("account_id" => ""),
					"account_number" => array("account_id" => "", "payment_method_type_code" => "CREDIT_CARD"),
					"expiration_month" => array("account_id" => "", "payment_method_type_code" => "CREDIT_CARD"),
					"expiration_year" => array("account_id" => "", "payment_method_type_code" => "CREDIT_CARD"),
					"cvv_code" => array("account_id" => "", "payment_method_type_code" => "CREDIT_CARD"),
					"routing_number" => array("account_id" => "", "payment_method_type_code" => "BANK_ACCOUNT"),
					"bank_account_number" => array("account_id" => "", "payment_method_type_code" => "BANK_ACCOUNT"),
					"user_name" => array("create_user" => "1"),
					"password" => array("create_user" => "1"),
					"security_question_id" => array("create_user" => "1"),
					"answer_text" => array("create_user" => "1"),
					"secondary_security_question_id" => array("create_user" => "1"),
					"secondary_answer_text" => array("create_user" => "1"));
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
				$minimumDonation = getPreference("MINIMUM_DONATION");
				if (empty($minimumDonation) || $minimumDonation < 0) {
					$minimumDonation = 0;
				}
				if ($_POST['amount'] < $minimumDonation) {
					$returnArray['error_message'] = ($GLOBALS['gUserRow']['administrator_flag'] ? "Minimum donation is " . number_format($minimumDonation, 2) : "Unable to process donation");
					ajaxResponse($returnArray);
					break;
				}
				$eCommerce = eCommerce::getEcommerceInstance($GLOBALS['gMerchantAccountId']);
				if (!$eCommerce) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "Unable to connect to Merchant Services " . $GLOBALS['gMerchantAccountId'] . ". Please contact customer service. #955";
					ajaxResponse($returnArray);
					break;
				}

# Strip spaces and dashes from account numbers

				$_POST['account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['account_number']));
				$_POST['bank_account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['bank_account_number']));

# create Substitutions array

				$substitutions = $_POST;
				unset($substitutions['account_number']);
				unset($substitutions['expiration_month']);
				unset($substitutions['expiration_year']);
				unset($substitutions['cvv_code']);
				unset($substitutions['routing_number']);
				unset($substitutions['bank_account_number']);
				unset($substitutions['password']);
				unset($substitutions['password_again']);
				$customerPaymentProfileId = "";
				$this->iDatabase->startTransaction();
				executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())", $_POST['_add_hash']);
				if (!$GLOBALS['gLoggedIn']) {

# create Contact

					$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
					if (empty($sourceId)) {
						$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
					}
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
						"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
						"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "source_id" => $sourceId)))) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $contactDataTable->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}

# create User

					if ($_POST['create_user'] == "1") {
						$resultSet = executeQuery("select * from users where user_name = ? and client_id = ?", strtolower($_POST['user_name']), $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "User name is already taken. Please select another.";
							ajaxResponse($returnArray);
							break;
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
						$confirmUserAccount = getPreference("CONFIRM_USER_ACCOUNT");
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
						if (!empty($confirmUserAccount)) {
							$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
							executeQuery("update users set verification_code = ?,locked = 1 where user_id = ?", $randomCode, $userId);
						}
						$password = hash("sha256", $userId . $passwordSalt . $_POST['password']);
						executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
						$resultSet = executeQuery("update users set password = ? where user_id = ?", $password, $userId);
						if (!empty($resultSet['sql_error'])) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
						$_SESSION = array();
						saveSessionData();
						login($userId);
						$emailId = getFieldFromId("email_id", "emails", "email_code", "NEW_ACCOUNT", "inactive = 0");
						if (!empty($emailId)) {
							sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $_POST['email_address']));
						}
					}
				} else {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
					if (!$GLOBALS['gUserRow']['administrator_flag']) {
						$contactTable = new DataTable("contacts");
						$contactTable->setSaveOnlyPresent(true);
						$contactValues = $_POST;
						unset($contactValues['notes']);
						if (!$contactTable->saveRecord(array("name_values" => $contactValues, "primary_id" => $contactId))) {
							$returnArray['error_message'] = $contactTable->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Add or update phone numbers to contacts

				$phoneDescriptions = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, (-1 * strlen("_phone_number"))) == "_phone_number") {
						$thisType = substr($fieldName, 0, (-1 * strlen("_phone_number")));
						$phoneDescriptions[] = $thisType;
					}
				}
				foreach ($phoneDescriptions as $phoneDescription) {
					$displayType = ucwords(str_replace("_", " ", $phoneDescription));
					if (!empty($_POST[$phoneDescription . "_phone_number"])) {
						$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = ?", $contactId, $displayType);
						if ($row = getNextRow($resultSet)) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST[$phoneDescription . "_phone_number"], $row['phone_number_id']);
						} else {
							executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)",
								$contactId, $_POST[$phoneDescription . "_phone_number"], $displayType);
						}
					} else {
						executeQuery("delete from phone_numbers where description = ? and contact_id = ?", $displayType, $contactId);
					}
				}

# Update mailing lists

				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("mailing_list_id_")) == "mailing_list_id_") {
						$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", substr($fieldName, strlen("mailing_list_id_")));
						if (!empty($mailingListId)) {
							$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
							if (!empty($mailingListRow)) {
								if ($fieldData == "Y") {
									if (!empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
									}
								} else {
									if (empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_out" => date("Y-m-d")), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										executeQuery("update contact_mailing_lists set date_opted_out = now() where contact_mailing_list_id = ?",
											$mailingListRow['contact_mailing_list_id']);
									}
								}
							} else {
								if ($fieldData == "Y") {
									$contactMailingListSource = new DataSource("contact_mailing_lists");
									$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
								}
							}
						}
					}
				}

# If the user is logged in or it is a recurring donation, get or create a customer profile

				if (!$GLOBALS['gUserRow']['administrator_flag'] || $_POST['payment_method_type_code'] == "CREDIT_CARD" || $_POST['payment_method_type_code'] == "BANK_ACCOUNT" || !empty($_POST['account_id'])) {
					$merchantIdentifier = "";
					if ($GLOBALS['gLoggedIn'] || !empty($_POST['recurring_donation_type_id'])) {
						$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $GLOBALS['gMerchantAccountId']);
						if (empty($merchantIdentifier) && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
							$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $_POST['first_name'],
								"last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "city" => $_POST['city'],
								"state" => $_POST['state'], "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address']));
							$response = $eCommerce->getResponse();
							if ($success) {
								$merchantIdentifier = $response['merchant_identifier'];
							}
						}
					}
					if (empty($merchantIdentifier) && !empty($_POST['recurring_donation_type_id'])) {
						$returnArray['error_message'] = "Unable to create the recurring donation. Please contact customer service. #686";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if (empty($merchantIdentifier) && !empty($_POST['account_id'])) {
						$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #128";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}

# Always save account if it is a recurring donation but never save if not and not a user

					if (!empty($_POST['recurring_donation_type_id'])) {
						$_POST['save_account'] = "1";
					} else if (!$GLOBALS['gLoggedIn']) {
						$_POST['save_account'] = "0";
					}

# if new account, create it

					if (empty($_POST['account_id'])) {
						$accountLabel = $_POST['account_label'];
						if (empty($accountLabel)) {
							$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
						}
						$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['billing_business_name']);
						$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
							"account_number,expiration_date,merchant_account_id,inactive) values (?,?,?,?,?, ?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
							$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
							(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))), $GLOBALS['gMerchantAccountId'], ($_POST['save_account'] ? 0 : 1));
						if (!empty($resultSet['sql_error'])) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
						$accountId = $resultSet['insert_id'];
					} else {
						$accountId = getFieldFromId("account_id", "accounts", "account_id", $_POST['account_id'], "contact_id = ?", $contactId);
						$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
					}
					$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
					$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
					if (empty($accountToken) && !empty($_POST['account_id'])) {
						$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #953";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}

					$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountId);
					if ($accountMerchantAccountId != $GLOBALS['gMerchantAccountId']) {
						$returnArray['error_message'] = "There is a problem with this account for this designation. #584";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$_POST['recurring_donation_type_id'] = "";
				}

# Create donation record if it is not recurring

				$donationId = "";
				if (empty($_POST['recurring_donation_type_id'])) {
					if (!empty($accountId) && empty($_POST['payment_method_id'])) {
						$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
					}
					$donationFee = Donations::getDonationFee(array("designation_id" => $_POST['designation_id'], "amount" => $_POST['amount'], "payment_method_id" => $_POST['payment_method_id']));
					$donationCommitmentId = Donations::getContactDonationCommitment($contactId, $_POST['designation_id'], $_POST['donation_source_id']);
					$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
						"account_id,designation_id,project_name,donation_source_id,amount,anonymous_gift,donation_fee,donation_commitment_id,notes) values (?,?,now(),?,?,?,?,?, ?,?,?,?,?)",
						$GLOBALS['gClientId'], $contactId, $_POST['payment_method_id'], $accountId, $_POST['designation_id'], $_POST['project_name'],
						$_POST['donation_source_id'], $_POST['amount'], (empty($_POST['anonymous_gift']) ? "0" : "1"), $donationFee, $donationCommitmentId, $_POST['notes']);
					if (!empty($resultSet['sql_error'])) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$donationId = $resultSet['insert_id'];
					Donations::processDonation($donationId);
					Donations::completeDonationCommitment($donationCommitmentId);
					$requiresAttention = getFieldFromId("requires_attention", "designations", "designation_id", $_POST['designation_id']);
					if ($requiresAttention) {
						sendEmail(array("subject" => "Designation Requires Attention", "body" => "Donation ID " . $donationId . " was created with a designation that requires attention.", "email_address" => getNotificationEmails("DONATIONS")));
					}
					addActivityLog("Made a donation for '" . getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']) . "'");
				}

# if the user is asking to save account, make sure the account exists

				if (!$GLOBALS['gUserRow']['administrator_flag'] || $_POST['payment_method_type_code'] == "CREDIT_CARD" || $_POST['payment_method_type_code'] == "BANK_ACCOUNT" || !empty($_POST['account_id'])) {
					if ($_POST['save_account'] && empty($accountToken) && empty($_POST['recurring_donation_type_id'])) {
						$resultSet = executeQuery("select * from accounts where contact_id = ? and account_token is not null and account_number like ? and payment_method_id = ?",
							$contactId, "%" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4), $_POST['payment_method_id']);
						$foundAccount = false;
						while ($row = getNextRow($resultSet)) {
							$thisMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
							if ($thisMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
								$foundAccount = true;
								break;
							}
						}
						if ($foundAccount) {
							$_POST['save_account'] = "";
						}
					}

# if the user is asking to save account, make sure the account exists

					if ($_POST['save_account'] && empty($accountToken) && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
						$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
							"first_name" => (empty($_POST['billing_first_name']) ? $_POST['first_name'] : $_POST['billing_first_name']),
							"last_name" => (empty($_POST['billing_last_name']) ? $_POST['last_name'] : $_POST['billing_last_name']),
							"business_name" => (empty($_POST['billing_business_name']) ? $_POST['business_name'] : $_POST['billing_business_name']),
							"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
							"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
							"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']));
						if ($isBankAccount) {
							$paymentArray['bank_routing_number'] = $_POST['routing_number'];
							$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
							$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
						} else {
							$paymentArray['card_number'] = $_POST['account_number'];
							$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
							$paymentArray['card_code'] = $_POST['cvv_code'];
						}
						$success = $eCommerce->createCustomerPaymentProfile($paymentArray);
						$response = $eCommerce->getResponse();
						if ($success) {
							$customerPaymentProfileId = $accountToken = $response['account_token'];
							$accountMerchantIdentifier = $merchantIdentifier;
						} else if (!empty($_POST['recurring_donation_type_id'])) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Unable to create payment account. Do you already have this payment method saved?";
							ajaxResponse($returnArray);
							break;
						}
					}

# If creating the account didn't work, exit with error.

					if (empty($accountToken) && empty($_POST['account_number']) && empty($_POST['bank_account_number'])) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Unable to charge account. Please contact customer service. #532";
						ajaxResponse($returnArray);
						break;
					}

# If it is a one-time donation, charge the card.

					if (!empty($donationId)) {
						if (empty($accountToken)) {
							$paymentArray = array("amount" => $_POST['amount'], "order_number" => $donationId, "description" => "Donation for " .
								getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']),
								"first_name" => (empty($_POST['billing_first_name']) ? $_POST['first_name'] : $_POST['billing_first_name']),
								"last_name" => (empty($_POST['billing_last_name']) ? $_POST['last_name'] : $_POST['billing_last_name']),
								"business_name" => (empty($_POST['billing_business_name']) ? $_POST['business_name'] : $_POST['billing_business_name']),
								"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
								"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
								"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']),
								"email_address" => $_POST['email_address'], "contact_id" => $contactId);
							if ($isBankAccount) {
								$paymentArray['bank_routing_number'] = $_POST['routing_number'];
								$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
								$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
							} else {
								$paymentArray['card_number'] = $_POST['account_number'];
								$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
								$paymentArray['card_code'] = $_POST['cvv_code'];
							}
							$success = $eCommerce->authorizeCharge($paymentArray);
							$response = $eCommerce->getResponse();
							if ($success) {
								executeQuery("update donations set transaction_identifier = ?,authorization_code = ?,bank_batch_number = ? where donation_id = ?",
									$response['transaction_id'], $response['authorization_code'], $response['bank_batch_number'], $donationId);
							} else {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
								$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
								$failedCreditCardNames[$failedCreditCardNameKey]++;
								setCachedData("failed_credit_card_names", "credit_card_failures", $failedCreditCardNames, 12);

								ajaxResponse($returnArray);

								break;
							}
						} else if (!empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
							$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
							if (empty($accountMerchantIdentifier)) {
								$accountMerchantIdentifier = $merchantIdentifier;
							}
							$addressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
							$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $_POST['amount'], "order_number" => $donationId,
								"merchant_identifier" => $accountMerchantIdentifier, "account_token" => $accountToken, "address_id" => $addressId));
							$response = $eCommerce->getResponse();
							if ($success) {
								executeQuery("update donations set transaction_identifier = ?,authorization_code = ?,bank_batch_number = ? where donation_id = ?",
									$response['transaction_id'], $response['authorization_code'], $response['bank_batch_number'], $donationId);
							} else {
								if (!empty($customerPaymentProfileId)) {
									$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
								}
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
								echo jsonEncode($returnArray);
								$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
								exit;
							}
						} else {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Charge Failed";
							echo jsonEncode($returnArray);
							$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), "No Customer Database", true);
							exit;
						}
					}
					if (empty($accountId) && !empty($_POST['recurring_donation_type_id'])) {
						$returnArray['error_message'] = "Unable to create the recurring donation. Please contact customer service. #385";
						if (!empty($customerPaymentProfileId) && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
							$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
						}
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}

				makeWebUserContact($contactId);
				$contactRow = Contact::getContact($contactId);
				$substitutions = array_merge($substitutions, $contactRow);
				if (empty($substitutions['salutation'])) {
					$substitutions['salutation'] = generateSalutation($contactRow);
				}
				$substitutions['designation_alias'] = getFieldFromId("alias", "designations", "designation_id", $_POST['designation_id']);
				$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']) . (!empty($_POST['anonymous_gift']) ? " (Anonymous)" : "");
				$substitutions['designation'] = getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']) . (!empty($_POST['anonymous_gift']) ? " (Anonymous)" : "");
				$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $_POST['designation_id']);
				$substitutions['recurring_donation_type_description'] = getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", $_POST['recurring_donation_type_id']);
				$substitutions['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
				$substitutions['full_name'] = getDisplayName($contactId);
				$substitutions['receipt_number'] = $donationId;
				$substitutions['donation_date'] = date("m/d/Y");
				$substitutions['gift_amount'] = $_POST['amount'];
				$substitutions['day_of_month'] = (empty($_POST['start_date']) ? "" : date("d", strtotime($_POST['start_date'])));
				$projectLabel = getFieldFromId("project_label", "designation_types", "designation_type_id", getFieldFromId("designation_type_id", "designations", "designation_id", $_POST['designation_id']));
				$designationProjectLabel = getFieldFromId("project_label", "designations", "designation_id", $_POST['designation_id']);
				if (!empty($designationProjectLabel)) {
					$projectLabel = $designationProjectLabel;
				}
				if (empty($projectLabel)) {
					$projectLabel = "Project";
				}
				$substitutions['project_label'] = $projectLabel;
				$substitutions['project_name'] = $_POST['project_name'];
				$substitutions['notes_label'] = getFieldFromId("memo_label", "designations", "designation_id", $_POST['designation_id']);
				if (!empty($substitutions['notes_label'])) {
					$substitutions['notes_label'] .= ": ";
				}
				$substitutions['notes'] = $_POST['notes'];
				$addressBlock = $substitutions['full_name'];
				if (!empty($substitutions['address_1'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['address_1'];
				}
				if (!empty($substitutions['address_2'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['address_2'];
				}
				if (!empty($substitutions['city'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['city'];
				}
				if (!empty($substitutions['state'])) {
					$addressBlock .= (empty($addressBlock) ? "" : ", ") . $substitutions['state'];
				}
				if (!empty($substitutions['postal_code'])) {
					$addressBlock .= (empty($addressBlock) ? "" : " ") . $substitutions['postal_code'];
				}
				if (!empty($substitutions['country_id']) && $substitutions['country_id'] != 1000) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $substitutions['country_id']);
				}
				$substitutions['address_block'] = $addressBlock;
				$anonymizedSubstitutions = $substitutions;
				if ($_POST['anonymous_gift']) {
					$anonymizeFields = array("first_name", "last_name", "business_name", "full_name", "address_block", "address_1", "address_2", "city", "state", "postal_code",
						"country_id", "email_address", "billing_first_name", "billing_last_name", "billing_business_name", "billing_address_1", "billing_address_2",
						"billing_city", "billing_state", "billing_postal_code", "billing_country_id");
					foreach ($anonymizeFields as $fieldName) {
						$anonymizedSubstitutions[$fieldName] = "Anonymous";
					}
					foreach ($phoneDescriptions as $thisType) {
						$anonymizedSubstitutions[$thisType . "_phone_number"] = "Anonymous";
					}
				}

# If recurring donation, authorize and void a $1.00 charge to test payment account

				if (!empty($_POST['recurring_donation_type_id'])) {
					if (empty($_POST['account_id']) && !$isBankAccount) {
						$testOrderId = date("Z") + 60000;
						$paymentArray = array("amount" => "1.00", "order_number" => $testOrderId, "description" => "Test Transaction", "authorize_only" => true,
							"first_name" => (empty($_POST['billing_first_name']) ? $_POST['first_name'] : $_POST['billing_first_name']),
							"last_name" => (empty($_POST['billing_last_name']) ? $_POST['last_name'] : $_POST['billing_last_name']),
							"business_name" => (empty($_POST['billing_business_name']) ? $_POST['business_name'] : $_POST['billing_business_name']),
							"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
							"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
							"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']),
							"email_address" => $_POST['email_address'], "contact_id" => $contactId);
						$paymentArray['card_number'] = $_POST['account_number'];
						$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
						$paymentArray['card_code'] = $_POST['cvv_code'];
						$success = $eCommerce->authorizeCharge($paymentArray);
						$response = $eCommerce->getResponse();
						if ($success) {
							$paymentArray['transaction_identifier'] = $response['transaction_id'];
							$eCommerce->voidCharge($paymentArray);
						} else {
							if (!empty($customerPaymentProfileId) && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
								$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
							}
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
							$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
							$failedCreditCardNames[$failedCreditCardNameKey]++;
							setCachedData("failed_credit_card_names", "credit_card_failures", $failedCreditCardNames, 12);
							ajaxResponse($returnArray);
							break;
						}
					}

					if (empty($_POST['start_date'])) {
						$_POST['start_date'] = date("m/d/Y");
					}

# Create Recurring donation

					$requiresAttention = getFieldFromId("requires_attention", "designations", "designation_id", $_POST['designation_id']);
					$resultSet = executeQuery("insert into recurring_donations (contact_id,recurring_donation_type_id," .
						"amount,payment_method_id,start_date,next_billing_date,designation_id,project_name,donation_source_id," .
						"anonymous_gift,account_id,requires_attention,notes) values (?,?,?,?,?, ?,?,?,?,?, ?,?,?)",
						$contactId, $_POST['recurring_donation_type_id'], $_POST['amount'],
						getFieldFromId("payment_method_id", "accounts", "account_id", $accountId), makeDateParameter($_POST['start_date']),
						makeDateParameter($_POST['start_date']), $_POST['designation_id'], $_POST['project_name'],
						$_POST['donation_source_id'], (empty($_POST['anonymous_gift']) ? "0" : "1"), $accountId, $requiresAttention, $_POST['notes']);
					if (!empty($resultSet['sql_error'])) {
						if (!empty($customerPaymentProfileId) && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
							$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
						}
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Unable to create the recurring donation. Please contact customer service. #902";
						ajaxResponse($returnArray);
						break;
					}
					addActivityLog("Added a recurring donation for '" . getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']) . "'");
					$emailId = getFieldFromId("email_id", "emails", "email_code", "RECURRING_GIFT_CONFIRMATION", "inactive = 0");
					if (!empty($emailId)) {
						$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
						if (!empty($emailAddress)) {
							sendEmail(array("email_id" => $emailId, "substitutions" => array_merge($substitutions, array("start_date" => (empty($_POST['start_date']) ? "immediately" : date("m/d/Y", strtotime($_POST['start_date']))))), "email_addresses" => $emailAddress));
						}
					}
					$emailId = getFieldFromId("email_id", "emails", "email_code", "RECURRING_GIFT_ADDED", "inactive = 0");
					if (!empty($emailId)) {
						$emailAddresses = array();
						$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $_POST['designation_id']);
						while ($emailRow = getNextRow($emailSet)) {
							$emailAddresses[] = $emailRow['email_address'];
						}
						if (!empty($emailAddresses)) {
							sendEmail(array("email_id" => $emailId, "substitutions" => $anonymizedSubstitutions, "email_addresses" => $emailAddresses));
						}
					}
					if ($requiresAttention) {
						sendEmail(array("subject" => "Designation Requires Attention", "body" => "A recurring donation was created for contact ID " . $contactId . " with a designation that requires attention.", "email_address" => getNotificationEmails("DONATIONS")));
					}
					$emailId = getFieldFromId("email_id", "emails", "email_code", "ALL_RECURRING_DONATIONS",  "inactive = 0");
					if (!empty($emailId)) {
						sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => getNotificationEmails("ALL_RECURRING_DONATIONS")));
					}
				}

# process donation receipt

				if (!empty($donationId)) {
					$receiptProcessed = Donations::processDonationReceipt($donationId, array("email_only" => true, "substitutions" => $substitutions));

					$emailId = getFieldFromId("email_id", "emails", "email_code", "DONATION_RECEIVED", "inactive = 0");
					if (!empty($emailId)) {
						Donations::sendDonationNotifications($donationId, $emailId);
					}
					$emailId = getFieldFromId("email_id", "emails", "email_code", "ALL_DONATIONS", "inactive = 0");
					if (!empty($emailId)) {
						sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => getNotificationEmails("ALL_DONATIONS")));
					}
				}

				$this->iDatabase->commitTransaction();

				addProgramLog("Donation successful: " . $donationId . "\n\n" . jsonEncode(array_merge($_POST,array("account_number"=>"","cvv_code"=>"","bank_account_number"=>""))));
				if (!empty($_POST['captcha_code_id'])) {
					executeQuery("delete from captcha_codes where captcha_code_id = ?", $_POST['captcha_code_id']);
				}
				if (!empty($userId) && $_POST['create_user'] == "1") {
					sendEmail(array("subject" => "User Account Created", "body" => "User account '" . $_POST['user_name'] . "' for contact " . getDisplayName($contactId) . " was created.", "email_address" => getNotificationEmails("USER_MANAGEMENT")));
				}
				if (empty($_POST['notes'])) {
					$substitutions['memo'] = "";
				} else {
					$memoLabel = getFieldFromId("memo_label", "designations", "designation_id", $_POST['designation_id']);
					if (empty($memoLabel)) {
						$substitutions['memo'] = "";
					} else {
						$substitutions['memo'] = $memoLabel . ": " . $_POST['notes'] . "<br>";
					}
				}
				$fragmentId = getFieldFromId("fragment_id", "designation_types", "designation_type_id",
					getFieldFromId("designation_type_id", "designations", "designation_id", $_POST['designation_id']));
				if (empty($fragmentId)) {
					$responseFragment = getFragment("ONLINE_DONATION_RESPONSE");
				} else {
					$responseFragment = getFragmentFromId($fragmentId);
				}
				$returnArray['designation_id'] = $_POST['designation_id'];
				$returnArray['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $_POST['designation_id']);
				$returnArray['amount'] = $_POST['amount'];
				$returnArray['recurring_donation_type_id'] = $_POST['recurring_donation_type_id'];
				$returnArray['response'] = PlaceHolders::massageContent($responseFragment, $substitutions);
				if (!empty($confirmUserAccount)) {
					$confirmLink = "https://" . $_SERVER['HTTP_HOST'] . "/confirmuseraccount.php?user_id=" . $userId . "&hash=" . $randomCode;
					sendEmail(array("email_address" => $_POST['email_address'], "send_immediately" => true, "email_code" => "ACCOUNT_CONFIRMATION", "substitutions" => array("confirmation_link" => $confirmLink), "subject" => "Confirm Email Address", "body" => "<p>Click <a href='" . $confirmLink . "'>here</a> to confirm your email address and complete the creation of your user account.</p>"));
					logout();
					$returnArray['info_message'] = "Please check your email and confirm your user account before you attempt to log in.";
				}
				$returnArray['info_message'] = "Your donation was successful";
				ajaxResponse($returnArray);
				break;
		}
	}

	function headerIncludes() {
		if ($this->iUseRecaptchaV2) {
			?>
			<script src="https://www.google.com/recaptcha/api.js" async defer></script>
			<?php
		}
#		echo '<script src="https://serveipqs.com/api/*/QG87fca0KhDNnBUWdwsRDfM3VMnMao8gqtf8leVPhdU3kMVrHbR27Ep8qZgRdFCIDr26b9whOSuas9ktf19z3XPdW42BpUAS20F9EKgATLTitQFO9hizW9dNS77lLfz9Mip6F0FJRedHfsRT5IlZ2PDxOVhpzU6qZAMY5reqXVIavcB9jj3lmZ1nkQefaYZAi0fntizXuJapmnPxRsO2GOGz3IfktahkBsP1U7mvvovE6N0elevbLFBMHldexAcq/learn.js" crossorigin="anonymous"></script><noscript><img src="https://serveipqs.com/api/*/QG87fca0KhDNnBUWdwsRDfM3VMnMao8gqtf8leVPhdU3kMVrHbR27Ep8qZgRdFCIDr26b9whOSuas9ktf19z3XPdW42BpUAS20F9EKgATLTitQFO9hizW9dNS77lLfz9Mip6F0FJRedHfsRT5IlZ2PDxOVhpzU6qZAMY5reqXVIavcB9jj3lmZ1nkQefaYZAi0fntizXuJapmnPxRsO2GOGz3IfktahkBsP1U7mvvovE6N0elevbLFBMHldexAcq/pixel.png" /></noscript>';
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("click", "#save_account", function () {
                if ($(this).prop("checked")) {
                    $("#_account_label_row").removeClass("hidden");
                } else {
                    $("#_account_label_row").addClass("hidden");
                }
            });
            $("#account_id").data("swipe_string", "");
            $("#payment_method_id").data("swipe_string", "");

            $("#account_id,#payment_method_id").keypress(function (event) {
                var thisChar = String.fromCharCode(event.which);
                if ($(this).data("swipe_string") != "") {
                    if (event.which == 13) {
                        processMagneticData($(this).data("swipe_string"));
                        $(this).data("swipe_string", "");
                    } else {
                        $(this).data("swipe_string", $(this).data("swipe_string") + thisChar);
                    }
                    return false;
                } else {
                    if (thisChar == "%") {
                        $(this).data("swipe_string", "%");
                        setTimeout(function () {
                            if ($(this).data('swipe_string') == "%") {
                                $(this).data('swipe_string', "");
                            }
                        }, 3000);
                        return false;
                    } else {
                        return true;
                    }
                }
            });
            $("#payment_method_id").change(function (event) {
                $(".payment-method-logo").removeClass("selected");
                $("#payment_method_logo_" + $(this).val()).addClass("selected");
                return false;
            });
            $(".preset-amount").click(function () {
                var amount = $(this).data("amount");
                if (!empty(amount)) {
                    $("#amount").val(amount);
                } else {
                    $("#amount").val("").focus();
                }
                return false;
            });
            $("#same_address").click(function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").hide();
                    $("#_billing_address").find("input,select").val("");
                } else {
                    $("#_billing_address").show();
                }
            });
            $("#create_user").click(function () {
                if ($("#create_user").prop("checked")) {
                    $(".create-user").show();
                } else {
                    $(".create-user").hide();
                }
            });
            $("input[name=recurring_donation_type_id]").click(function () {
                showRecurringStartDate();
            });
            $("select#recurring_donation_type_id").change(function () {
                showRecurringStartDate();
            });
            $("#country_id").change(function () {
				<?php if (!$GLOBALS['gUserRow']['administrator_flag']) { ?>
                if ($(this).val() == "1000") {
                    $("#_state_row").hide();
                    $("#_state_select_row").show();
                } else {
					<?php } ?>
                    $("#_state_row").show();
                    $("#_state_select_row").hide();
					<?php if (!$GLOBALS['gUserRow']['administrator_flag']) { ?>
                }
				<?php } ?>
            }).trigger("change");
            $("#billing_country_id").change(function () {
				<?php if (!$GLOBALS['gUserRow']['administrator_flag']) { ?>
                if ($(this).val() == "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
					<?php } ?>
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
					<?php if (!$GLOBALS['gUserRow']['administrator_flag']) { ?>
                }
				<?php } ?>
            }).trigger("change");
            $("#state_select").change(function () {
                $("#state").val($(this).val());
            })
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            });
            $("#user_name").change(function () {
                if (!empty($(this).val())) {
                    $("#create_user").prop("checked", true);
                    $(".create-user").show();
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val(), function (returnArray) {
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
            $(document).on("tap click", "#copy_address", function () {
                $("#_donor_info_section").find("input,select").each(function () {
                    if ($("#billing_" + $(this).attr("id")).length > 0) {
                        $("#billing_" + $(this).attr("id")).val($(this).val());
                    }
                });
                $("#billing_country_id").trigger("change");
                return false;
            });
            $("#account_id").change(function () {
                if (!empty($(this).val())) {
                    $("#_new_account").hide();
                } else {
                    $("#_new_account").show();
                }
            });
            $("#designation_code").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    $(this).trigger("blur");
                }
                return false;
            });
            $(document).on("blur", "#designation_code", function () {
                $("#designation_code_error").html("");
                if ($("#designation_code").val() != "") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=check_designation&designation_code=" + encodeURIComponent($(this).val()), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#designation_code_error").html(returnArray['error_message']);
                        }
                        if ("designation" in returnArray) {
                            if ($("#designation_id").find("option[value=" + returnArray['designation']['designation_id'] + "]").length == 0) {
                                $("#designation_id").append($("<option></option>").attr("value", returnArray['designation']['designation_id']).text(returnArray['designation']['description']).data("merchant_account_id", returnArray['designation']['merchant_account_id']));
                            }
                            $("#designation_id").val(returnArray['designation']['designation_id']).trigger("change");
                            if ("tax_deductible_message" in returnArray['designation']) {
                                $("#tax_deductible_message").html(returnArray['designation']['tax_deductible_message']).removeClass("hidden");
                            } else {
                                $("#tax_deductible_message").addClass("hidden");
                            }
                            $("#designation_code").val("");
                        }
                    });
                }
            });
            $(document).on("change", "#designation_id", function () {
                if (accountIds == null) {
                    accountIds = new Array();
                    if ($("select#account_id").length > 0) {
                        $("select#account_id").find("option").each(function () {
                            if (!empty($(this).val())) {
                                var thisAccount = new Object();
                                thisAccount.account_id = $(this).val();
                                thisAccount.merchant_account_id = $(this).data("merchant_account_id");
                                thisAccount.text = $(this).text();
                                accountIds.push(thisAccount);
                            }
                        });
                    }
                }
                var designationId = $("#designation_id").val();
                var merchantAccountId = "";
                if ($("#designation_id").is("select")) {
                    merchantAccountId = $("#designation_id").find("option:selected").data("merchant_account_id");
                } else {
                    merchantAccountId = $("#designation_id").data("merchant_account_id");
                }
                if (empty(merchantAccountId)) {
                    merchantAccountId = defaultMerchantAccountId;
                }
                $("#merchant_account_id").val(merchantAccountId);
                if ($("select#account_id").length > 0) {
                    var saveAccountId = $("select#account_id").val();
                    $("select#account_id").find("option[value!='']").remove();
                    var foundAccountId = false;
                    for (var i in accountIds) {
                        if (merchantAccountId == accountIds[i].merchant_account_id) {
                            if (!empty(saveAccountId) && saveAccountId == accountIds[i].account_id) {
                                foundAccountId = true;
                            }
                            var thisOption = $("<option></option>").attr("value", accountIds[i].account_id).text(accountIds[i].text);
                            $("select#account_id").append(thisOption);
                        }
                    }
                    if (foundAccountId) {
                        $("#select#account_id").val(saveAccountId);
                    }
                    if ($("select#account_id").val() != "") {
                        $("#_new_account").hide();
                    } else {
                        $("#_new_account").show();
                    }
                }
                if (designationId == "") {
                    $("#_designation_code_row").show();
                    $("#_detailed_description_row").hide();
                    $("#_project_name_row").hide();
                    $("#_notes_row").hide();
                    return;
                }
                $("#_designation_code_row").hide();
                $("#_detailed_description_row").hide();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_projects&designation_id=" + $(this).val(), function (returnArray) {
                    if ("tax_deductible_message" in returnArray) {
                        $("#tax_deductible_message").html(returnArray['tax_deductible_message']).removeClass("hidden");
                    } else {
                        $("#tax_deductible_message").addClass("hidden");
                    }
                    if ("detailed_description" in returnArray && returnArray['detailed_description'] != "") {
                        $("#_detailed_description_row").html(returnArray['detailed_description']).show();
                    }
                    var urlProject = getURLParameter("project");
                    var selectedProject = "";
                    if ("projects" in returnArray) {
                        $("#project_name").find("option[value!='']").remove();
                        for (var i in returnArray['projects']) {
                            if (!empty(urlProject) && urlProject == returnArray['projects'][i]) {
                                selectedProject = returnArray['projects'][i];
                            }
                            $("#project_name").append($("<option></option>").attr("value", returnArray['projects'][i]).text(returnArray['projects'][i]).data("inactive", "1"));
                        }
                    } else {
                        $("#_project_name_row").hide();
                    }
                    $("#project_name").val(selectedProject);
                    if ("project_label" in returnArray) {
                        $("#project_label").html(returnArray['project_label']);
                    } else {
                        $("#project_label").html("Project");
                    }
                    if ("project_required" in returnArray && returnArray['project_required'] && $("#project_name").find("option[value!='']").length > 0) {
                        $("#project_name").addClass("validate[required]");
                        $("#project_label").append("<span class='required-tag'>*</span>");
                        $("#no_project").html("[Select]");
                    } else {
                        $("#project_name").removeClass("validate[required]");
                        $("#no_project").html("[None]");
                    }
                    if ("memo_label" in returnArray) {
                        $("#notes_label").html(returnArray['memo_label']).attr("placeholder", returnArray['memo_label']);
                    }
                    if ("memo_required" in returnArray && returnArray['memo_required']) {
                        $("#notes").addClass("validate[required]");
                        $("#notes_label").append("<span class='required-tag'>*</span>");
                    } else {
                        $("#notes").removeClass("validate[required]");
                    }
                    checkProject();
                });
            });
            $(document).on("tap click", "#_submit_form", function () {
				<?php if (!empty($this->iUseRecaptchaV2)) { ?>
                if (empty(grecaptcha) || empty(grecaptcha.getResponse())) {
                    displayErrorMessage("Captcha invalid, please check \"I'm not a robot checkbox\".");
                    orderInProcess = false;
                    return false;
                }
				<?php } ?>
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#_submit_form").hide();
                    $("#_processing_paragraph").show();
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=make_donation", $("#_edit_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#_submit_form").show();
                            return;
                        }
                        if ("response" in returnArray) {
                            $("#_donation_form").html(returnArray['response']);
                            scrollInView($("#_donation_form"));
                            if (typeof afterSubmitForm == "function") {
                                afterSubmitForm(returnArray);
                            }
                        } else {
                            $("#_submit_form").show();
                        }
                    }, function (returnArray) {
                        $("#_processing_paragraph").hide();
                        $("#_submit_form").show();
                    });
                }
                return false;
            });
            $("#designation_id").trigger("change");
			<?php if (false && !$GLOBALS['gDevelopmentServer']) { ?>
            if (window.location.protocol != "https:") {
                $("#_donation_form").html("<p style='font-size: 18px; font-weight: bold;'>Secure form was unable to be displayed. A browser upgrade is probably required.</p>");
            }
			<?php } ?>
            showRecurringStartDate();
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
            var defaultMerchantAccountId = <?= (empty($GLOBALS['gMerchantAccountId']) ? "0" : $GLOBALS['gMerchantAccountId']) ?>;
            var accountIds = null;

            function showRecurringStartDate() {
                if ($("select#recurring_donation_type_id").length > 0 && $("select#recurring_donation_type_id").val() == "") {
                    $("#_start_date_row").hide();
                } else if ($("select#recurring_donation_type_id").length == 0 && $("input[name=recurring_donation_type_id]:checked").val() == "") {
                    $("#_start_date_row").hide();
                } else {
                    $("#_start_date_row").show();
                }
            }

            function checkProject() {
                if ($("#project_name option").length > 1) {
                    $("#_project_name_row").show();
                } else {
                    $("#_project_name_row").hide();
                }
                if ($("#notes_label").html() == "") {
                    $("#_notes_row").hide();
                } else {
                    $("#_notes_row").show();
                }
            }
		</script>
		<?php
	}

	function mainContent() {
		echo $this->getPageData("content");
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$minimumDonation = getPreference("MINIMUM_DONATION");
		if (empty($minimumDonation) || $minimumDonation < 0) {
			$minimumDonation = 0;
		}
		$formFilename = $_GET['form'];
		if (empty($formFilename) || !file_exists($GLOBALS['gDocumentRoot'] . "/forms/" . $formFilename . "giving.frm")) {
			$formFilename = "public";
		}
		include_once("forms/" . $formFilename . "giving.frm");
		$_SESSION['form_displayed'] = date("U");
		saveSessionData();
		return true;
	}

	function internalCSS() {
		?>
		<style>
            #_recurring_donation_type_id_row {
                margin-bottom: 40px;
                margin-top: 20px;
            }

            #_recurring_donation_type_id_row p {
                margin: 0;
                line-height: 1;
                padding: 0;
                padding-bottom: 2px;
            }

            .create-user {
                display: none;
            }

            .payment-method-fields {
                display: none;
            }

            #_processing_paragraph {
                display: none;
            }

            #_detailed_description_row {
                max-width: 600px;
            }

            #_detailed_description_row img {
                max-width: 100%;
            }

            #designation_image {
                max-width: 95%;
            }

            #designation_image img {
                max-width: 100%;
            }

            #_billing_address {
                display: none;
            }

            #_bank_name_row {
                height: 0 !important;
                min-height: 0 !important;
                max-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            #_agree_terms_row {
                height: 0 !important;
                min-height: 0 !important;
                max-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            #_confirm_human_row {
                height: 0 !important;
                min-height: 0 !important;
                max-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
            }

            #payment_logos {
                padding: 10px 0;
            }

            .payment-method-logo {
                max-height: 64px;
                opacity: .2;
                margin-right: 20px;
            }

            .payment-method-logo.selected {
                opacity: 1;
            }

            #designation_code_error {
                color: rgb(192, 0, 0);
            }

            #goal_progress {
                width: 80%;
                max-width: 600px;
                margin: 20px 0;
                border-radius: 5px;
                height: 100px;
                background-color: rgb(255, 255, 255);
                overflow: hidden;
                position: relative;
                border: 1px solid rgb(0, 125, 0);
            }

            #goal_progress_bar {
                width: calc(100% - 30px);
                height: 25px;
                position: absolute;
                bottom: 15px;
                left: 15px;
                border: 1px solid rgb(240,240,240);
                overflow: hidden;
                font-size: 16px;
                border-radius: 10px;
                text-align: center;
                font-weight: bold;
            }

            #goal_progress_fill {
                max-width: 100%;
                height: 30px;
                background-color: rgb(0, 125, 0);
                position: absolute;
                top: 0;
                left: 0;
            }

            #goal_progress_description {
                font-size: 1rem;
                color: rgb(20, 30, 40);
                text-align: left;
                line-height: 24px;
                z-index: 1000;
                position: absolute;
                top: 20px;
                left: 20px;
                width: calc(100% - 40px);
            }

            .g-recaptcha {
                margin: 20px 0;
            }
		</style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
