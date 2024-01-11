<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "RECURRINGDONATIONCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gSkipCorestoreContactUpdate'] = true;
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class ThisPage extends Page {

	var $iErrorMessages = array();

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_import":
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				if (empty($csvImportId)) {
					$returnArray['error_message'] = "Invalid CSV Import";
					ajaxResponse($returnArray);
					break;
				}
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "recurring_donations", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring donations #872";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from recurring_donations where recurring_donation_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring donations #294";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to recurring donations #183";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $deleteSet['sql_error'];
					ajaxResponse($returnArray);
					break;
				}

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);

				break;
			case "select_recurring_donations":
				$pageId = $GLOBALS['gAllPageCodes']["RECURRINGDONATIONMAINT"];
				$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "recurring donations selected in Recurring Donations Maintenance program";
				ajaxResponse($returnArray);
				break;
			case "import_csv":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

				$allValidFields = array("contact_id", "last_name", "business_name", "first_name", "middle_name", "address_1", "address_2", "city", "state", "postal_code", "email_address",
					"country", "phone_number", "payment_method_code", "account_number", "routing_number", "expiration_date", "amount", "designation_code", "start_date", "next_billing_date", "anonymous_gift");
				$requiredFields = array("payment_method_code", "account_number", "amount", "designation_code", "recurring_donation_type_code");
				$numericFields = array("amount");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$this->iErrorMessages = array();
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
						}
						$invalidFields = "";
						foreach ($fieldNames as $fieldName) {
							if (!in_array($fieldName, $allValidFields)) {
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
							$this->addErrorMessage("Valid fields are: " . implode(", ", $allValidFields));
						}
					} else {
						$fieldData = array();
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							$fieldData[$thisFieldName] = trim($thisData);
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);
				$paymentMethods = array();
				$designations = array();
				$recurringDonationTypes = array();
				foreach ($importRecords as $index => $thisRecord) {
					if (empty($thisRecord['country'])) {
						$countryId = 1000;
					} else {
						$countryId = getFieldFromId("country_id", "countries", "country_name", $thisRecord['country']);
						if (empty($countryId)) {
							$countryId = getFieldFromId("country_id", "countries", "country_code", $thisRecord['country']);
						}
					}
					if (empty($countryId)) {
						$this->addErrorMessage("Invalid Country: " . $thisRecord['country']);
						continue;
					}

					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
					if (empty($contactId) && !empty($thisRecord['contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
					}
					if (empty($contactId) && !empty($thisRecord['email_address'])) {
						$resultSet = executeQuery("select * from contacts where email_address = ? and client_id = ?", $thisRecord['email_address'], $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 1) {
							$this->addErrorMessage("Line " . ($index + 2) . ": More than one matching contact found - " . $thisRecord['email_address']);
							continue;
						} else if ($resultSet['row_count'] == 1) {
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
							}
						}
					}
					if (empty($contactId)) {
						$resultSet = executeQuery("select * from contacts where first_name <=> ? and last_name <=> ? and business_name <=> ? and client_id = ?",
							$thisRecord['first_name'], $thisRecord['last_name'], $thisRecord['business_name'], $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 1) {
							$this->addErrorMessage("Line " . ($index + 2) . ": More than one matching contact found");
							continue;
						} else if ($resultSet['row_count'] == 1) {
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
							}
						}
					}
					if (empty($contactId)) {
						$contactDataTable = new DataTable("contacts");
						$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $thisRecord['first_name'], "middle_name" => $thisRecord['middle_name'],
							"last_name" => $thisRecord['last_name'], "business_name" => $thisRecord['business_name'], "address_1" => $thisRecord['address_1'], "address_2" => $thisRecord['address_2'],
							"city" => $thisRecord['city'], "state" => $thisRecord['state'], "postal_code" => $thisRecord['postal_code'], "email_address" => $thisRecord['email_address'], "country_id" => $countryId)));
					}
					$importRecords[$index]['contact_id'] = $contactId;

					$missingFields = "";

					foreach ($numericFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
						}
					}
					if (!empty($thisRecord['payment_method_code'])) {
						if (!array_key_exists($thisRecord['payment_method_code'], $paymentMethods)) {
							$paymentMethods[$thisRecord['payment_method_code']] = "";
						}
					}
					if (!empty($thisRecord['designation_code'])) {
						if (!array_key_exists($thisRecord['designation_code'], $designations)) {
							$designations[$thisRecord['designation_code']] = "";
						}
					}
					if (!empty($thisRecord['recurring_donation_type_code'])) {
						if (!array_key_exists($thisRecord['recurring_donation_type_code'], $recurringDonationTypes)) {
							$recurringDonationTypes[$thisRecord['recurring_donation_type_code']] = "";
						}
					} else if (!array_key_exists("MONTHLY", $recurringDonationTypes)) {
						$recurringDonationTypes["MONTHLY"] = "";
					}
				}
				foreach ($paymentMethods as $thisCode => $paymentMethodId) {
					$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", makeCode($thisCode));
					if (empty($paymentMethodId)) {
						$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "description", $thisCode);
					}
					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $paymentMethodId));
					if (empty($paymentMethodId) || empty($paymentMethodTypeCode)) {
						$this->addErrorMessage("Invalid Payment Method: " . $thisCode);
					} else {
						$paymentMethods[$thisCode] = $paymentMethodId;
					}
				}
				foreach ($recurringDonationTypes as $thisCode => $recurringDonationTypeId) {
					$recurringDonationTypeId = getFieldFromId("recurring_donation_type_id", "recurring_donation_types", "recurring_donation_type_code", makeCode($thisCode));
					if (empty($recurringDonationTypeId)) {
						$recurringDonationTypeId = getFieldFromId("recurring_donation_type_id", "recurring_donation_types", "description", $thisCode);
					}
					if (empty($recurringDonationTypeId)) {
						$this->addErrorMessage("Invalid Recurring donation type: " . $thisCode);
					} else {
						$recurringDonationTypes[$thisCode] = $recurringDonationTypeId;
					}
				}
				foreach ($designations as $thisCode => $designationId) {
					$designationId = getFieldFromId("designation_id", "designations", "designation_code", makeCode($thisCode));
					if (empty($designationId)) {
						$designationId = getFieldFromId("designation_id", "designations", "description", $thisCode);
					}
					if (empty($designationId)) {
						$alternateDesignation = str_replace(" and ", " & ", $thisCode);
						$designationId = getFieldFromId("designation_id", "designations", "description", $alternateDesignation);
					}
					if (empty($designationId)) {
						$alternateDesignation = str_replace(" & ", " and ", $thisCode);
						$designationId = getFieldFromId("designation_id", "designations", "description", $alternateDesignation);
					}
					if (empty($designationId)) {
						$designationParts = explode(",", str_replace(" & ", " and ", $thisCode));
						foreach ($designationParts as $index => $thisPart) {
							if (strpos($thisPart, " and ") !== false) {
								$nameParts = explode(" and ", $thisPart);
								$designationParts[$index] = implode(" and ", array_reverse($nameParts));
							}
						}
						$alternateDesignation = implode(",", $designationParts);
						$designationId = getFieldFromId("designation_id", "designations", "description", $alternateDesignation);
					}
					if (empty($designationId)) {
						$alternateDesignation = str_replace(" and ", " & ", $thisCode);
						$designationId = getFieldFromId("designation_id", "designations", "description", $alternateDesignation);
					}
					if (empty($designationId) && strlen($thisCode) > 3) {
						$resultSet = executeQuery("select * from designations where client_id = ? and description like ?", $GLOBALS['gClientId'], "%" . $thisCode . "%");
						if ($resultSet['row_count'] == 1) {
							$row = getNextRow($resultSet);
							$designationId = $row['designation_id'];
						}
						if (empty($designationId)) {
							$resultSet = executeQuery("select * from designations where client_id = ? and description like ?", $GLOBALS['gClientId'], "%" . str_replace(" ", ", ", $thisCode) . "%");
							if ($resultSet['row_count'] == 1) {
								$row = getNextRow($resultSet);
								$designationId = $row['designation_id'];
							}
						}
					}
					if (empty($designationId)) {
						$this->addErrorMessage("Invalid Designation: " . $thisCode);
					} else {
						$designations[$thisCode] = $designationId;
					}
				}

				foreach ($importRecords as $index => $thisRecord) {
					$paymentMethodId = $paymentMethods[$thisRecord['payment_method_code']];
					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $paymentMethodId));
					$importRecords[$index]['payment_method_type_code'] = $paymentMethodTypeCode;
					switch ($paymentMethodTypeCode) {
						case "BANK_ACCOUNT":
							if (empty($thisRecord['account_number']) || empty($thisRecord['routing_number'])) {
								$this->addErrorMessage("Line " . ($index + 2) . ": missing account information");
							}
							break;
						case "CREDIT_CARD":
							if (empty($thisRecord['account_number']) || empty($thisRecord['expiration_date'])) {
								$this->addErrorMessage("Line " . ($index + 2) . ": missing account information");
							}
							$expirationDate = "";
							if (strlen($thisRecord['expiration_date']) <= 5) {
								$parts = explode("/", str_replace("-", "/", $thisRecord['expiration_date']));
								$expirationDate = date("Y-m-d", strtotime($parts[0] . "/01/" . (strlen($parts[1]) < 4 ? "20" : "") . $parts[1]));
							} else {
								$expirationDate = date("Y-m-d", strtotime($thisRecord['expiration_date']));
							}
							if ($expirationDate < "2000-01-01") {
								$this->addErrorMessage("Line " . ($index + 2) . ": invalid expiration date");
							}
							break;
					}
				}

				if (!empty($this->iErrorMessages)) {
					$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
					foreach ($this->iErrorMessages as $thisMessage => $count) {
						$returnArray['import_error'] .= "<p>" . $count . ": " . $thisMessage . "</p>";
					}
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'recurring_donations',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$merchantAccountId = $GLOBALS['gMerchantAccountId'];
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				if (!$eCommerce || !$eCommerce->hasCustomerDatabase()) {
					executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
					$returnArray['error_message'] = $returnArray['import_error'] = "Unable to connect to Merchant Services with customer database";
					ajaxResponse($returnArray);
					break;
				}

				$insertCount = 0;
				$updateCount = 0;
				$recurringDonationIds = array();
				foreach ($importRecords as $index => $thisRecord) {
					$amount = str_replace(",", "", $thisRecord['amount']);
					if (empty($amount)) {
						continue;
					}
					$contactId = $thisRecord['contact_id'];
					if (empty($contactId)) {
						if (!empty($recurringDonationIds)) {
							executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
						}
						executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
						executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
						$returnArray['error_message'] = $returnArray['import_error'] = "Line " . ($index + 2) . ": unable to find contact";
						ajaxResponse($returnArray);
						break;
					}
					$contactRow = Contact::getContact($contactId);
					$isBankAccount = ($thisRecord['payment_method_type_code'] == "BANK_ACCOUNT");

					$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
					if (empty($merchantIdentifier)) {
						$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $contactRow['first_name'],
							"last_name" => $contactRow['last_name'], "business_name" => $contactRow['business_name'], "address_1" => $contactRow['address_1'], "city" => $contactRow['city'],
							"state" => $contactRow['state'], "postal_code" => $contactRow['postal_code'], "email_address" => $contactRow['email_address']));
						$response = $eCommerce->getResponse();
						if ($success) {
							$merchantIdentifier = $response['merchant_identifier'];
						}
					}
					if (empty($merchantIdentifier)) {
						if (!empty($recurringDonationIds)) {
							executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
						}
						executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
						executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
						$returnArray['error_message'] = $returnArray['import_error'] = "Unable to create the merchant profile for contact ID " . $contactId;
						ajaxResponse($returnArray);
						break;
					}

					$accountId = getFieldFromId("account_id", "accounts", "contact_id", $contactId,
						"inactive = 0 and payment_method_id = ? and account_number like ? and account_token is not null and merchant_account_id = ?",
						$paymentMethods[$thisRecord['payment_method_code']], "%" . substr($thisRecord["account_number"], -4), $merchantAccountId);
					if (empty($accountId)) {
						$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
							"first_name" => $thisRecord['first_name'], "last_name" => $thisRecord['last_name'], "business_name" => $thisRecord['business_name'],
							"address_1" => $thisRecord['address_1'], "city" => $thisRecord['city'], "state" => $thisRecord['state'],
							"postal_code" => $thisRecord['postal_code'], "country_id" => $thisRecord['country_id']);
						if ($isBankAccount) {
							$expirationDate = "";
							$paymentArray['bank_routing_number'] = $thisRecord['routing_number'];
							$paymentArray['bank_account_number'] = $thisRecord['account_number'];
							$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $thisRecord['payment_method_id']))))));
						} else {
							if (strlen($thisRecord['expiration_date']) <= 5) {
								$parts = explode("/", str_replace("-", "/", $thisRecord['expiration_date']));
								$expirationDate = date("m/d/Y", strtotime($parts[0] . "/01/" . $parts[1]));
							} else {
								$expirationDate = date("m/d/Y", strtotime($thisRecord['expiration_date']));
							}
							$paymentArray['card_number'] = $thisRecord['account_number'];
							$paymentArray['expiration_date'] = $expirationDate;
							$paymentArray['card_code'] = "SKIP_CARD_CODE";
						}
						$success = $eCommerce->createCustomerPaymentProfile($paymentArray);
						$response = $eCommerce->getResponse();
						if ($success) {
							$accountToken = $response['account_token'];
						} else {
							if (!empty($recurringDonationIds)) {
								executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
							}
							executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
							executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
							$returnArray['error_message'] = $returnArray['import_error'] = "Unable to create payment account: " . jsonEncode($paymentArray);
							ajaxResponse($returnArray);
							break;
						}
						$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $paymentMethods[$thisRecord['payment_method_code']]) . " - " . substr($thisRecord["account_number"], -4);
						$fullName = $thisRecord['first_name'] . " " . $thisRecord['last_name'] . (empty($thisRecord['business_name']) ? "" : ", " . $thisRecord['business_name']);
						$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
							"account_number,expiration_date,merchant_identifier,merchant_account_id,account_token,inactive) values (?,?,?,?,?, ?,?,?,?,?)", $contactId, $accountLabel, $paymentMethods[$thisRecord['payment_method_code']],
							$fullName, "XXXX-" . substr($thisRecord["account_number"], -4),
							(empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate))), $merchantIdentifier, $merchantAccountId, $accountToken, 0);
						if (!empty($resultSet['sql_error'])) {
							if (!empty($recurringDonationIds)) {
								executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
							}
							executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
							executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
							$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
						$accountId = $resultSet['insert_id'];
					}

					$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
					$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
					if (empty($accountToken)) {
						if (!empty($recurringDonationIds)) {
							executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
						}
						executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
						executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
						$returnArray['error_message'] = $returnArray['import_error'] = "There is a problem using an existing payment method on line " . $index;
						ajaxResponse($returnArray);
						break;
					}

					if (empty($thisRecord['recurring_donation_type_code'])) {
						$thisRecord['recurring_donation_type_code'] = "MONTHLY";
					}
					if (empty($thisRecord['start_date'])) {
						$thisRecord['start_date'] = date("Y-m-d");
					}
					$insertSet = executeQuery("insert into recurring_donations (contact_id,recurring_donation_type_id,amount,payment_method_id,start_date," .
						"next_billing_date,designation_id,anonymous_gift,account_id) values (?,?,?,?,?, ?,?,?,?)",
						$contactId, $recurringDonationTypes[$thisRecord['recurring_donation_type_code']], $amount,
						getFieldFromId("payment_method_id", "accounts", "account_id", $accountId), makeDateParameter($thisRecord['start_date']),
						(empty($thisRecord['next_billing_date']) ? makeDateParameter($thisRecord['start_date']) : makeDateParameter($thisRecord['next_billing_date'])),
						$designations[$thisRecord['designation_code']], (empty($thisRecord['anonymous_gift']) ? "0" : "1"), $accountId);
					if (!empty($insertSet['sql_error'])) {
						if (!empty($recurringDonationIds)) {
							executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
						}
						executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
						executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . ":" . jsonEncode($thisRecord);
						ajaxResponse($returnArray);
						break;
					}
					$recurringDonationId = $insertSet['insert_id'];
					$recurringDonationIds[] = $recurringDonationId;
					$insertCount++;

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $recurringDonationId);
					if (!empty($insertSet['sql_error'])) {
						if (!empty($recurringDonationIds)) {
							executeQuery("delete from recurring_donations where recurring_donation_id in (" . implode(",", $recurringDonationIds) . ")");
						}
						executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
						executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$returnArray['response'] = "<p>" . $insertCount . " recurring donations imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " recurring donations updated.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

	function addErrorMessage($errorMessage) {
		if (array_key_exists($errorMessage, $this->iErrorMessages)) {
			$this->iErrorMessages[$errorMessage]++;
		} else {
			$this->iErrorMessages[$errorMessage] = 1;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div id="import_error"></div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th></th>
				<?php if (canAccessPage("DONATIONMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'recurring_donations' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = $minutesSince < 48;
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row" data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
					<?php if (canAccessPage("DONATIONMAINT")) { ?>
                        <td><span class='far fa-check-square select-recurring_donations'></span></td>
					<?php } ?>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".select-recurring_donations", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_recurring_donations&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
            $(document).on("click", ".remove-import", function () {
                var csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function(returnArray) {
                                if ("csv_import_id" in returnArray) {
                                    $("#csv_import_id_" + returnArray['csv_import_id']).remove();
                                }
                            });
                            $("#_confirm_undo_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_undo_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_submit_form", function () {
                if ($("#_submit_form").data("disabled") == "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($("#_submit_form"));
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        #import_error { color: rgb(192,0,0); }
        .remove-import { cursor: pointer; }
        .select-recurring_donations { cursor: pointer; }
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these recurring_donations being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
