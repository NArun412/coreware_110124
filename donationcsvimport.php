<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "DONATIONCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gSkipCorestoreContactUpdate'] = true;
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class DonationCsvImportPage extends Page {

	var $iErrorMessages = array();
	var $iValidFields = array("contact_id", "old_contact_id", "last_name", "business_name", "first_name", "address_1", "address_2", "city", "state", "postal_code", "country",
		"phone_number", "phone_numbers", "phone_descriptions", "email_address", "donation_date", "reference_number", "payment_method_code", "amount", "designation_code", "donation_fee",
		"anonymous_gift", "category_codes", "notes");
	var $iIgnoreFields = array("payment_id", "charge_time", "fb_fee", "net_payout_amount", "payout_currency", "sender_currency", "tax_amount", "tax_usd_amount", "charge_action_type",
		"campaign_id", "fundraiser_title", "source_name", "permalink", "charity_id", "campaign_owner_name", "payment_processor", "matching_donation", "fundraiser_type", "charge_time_pt",
		"donation", "billing_status", "account", "account_id", "account_plan", "account_plan_price", "fee_rate", "activity_details", "form", "form_id",
		"form_name", "form_payment_type", "form_type", "keyword", "shortcode", "solicit_name", "sub_solicit_name", "type", "donor_full_name", "gsf_donor_id",
		"gender", "alternative_team_id", "team", "volunteer_fundraiser", "billing_response_code", "billing_transaction", "billing_transaction_reference",
		"billing_type", "discount", "frequency", "fulfillment_texts", "ip_address", "payment_gateway", "pledged_amount", "processing_fee", "recurring_limit",
		"reference_transaction_id", "source", "transaction_date", "transaction_id", "transaction_status", "cc_expiration", "cc_type", "last_4", "comments",
		"match_donation", "matching_company", "promotional_codes");
	var $iConvertFields = array("donation_amount" => "amount", "charge_date" => "donation_date", "collected_amount" => "amount", "campaign_name" => "designation_code",
		"email" => "email_address", "phone" => "phone_number", "street_address" => "address_1", "zip" => "postal_code", "memo" => "notes", "payment_method" => "payment_method_code",
		"anonymous" => "anonymous_gift");
	var $iNotesFields = array("campaign_id", "campaign_owner_name");

	function setup() {
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iValidFields[] = "custom_field-" . strtolower($row['custom_field_code']);
		}
	}

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "donations", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to donations";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$resultSet = executeQuery("select * from donations where donation_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				while ($row = getNextRow($resultSet)) {
					$accountId = getFieldFromId("account_id", "accounts", "account_id", $row['account_id'],
						"payment_method_id in (select payment_method_id from payment_methods where " .
						"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_ACCOUNT'))");
					if (!empty($accountId)) {
						$creditAccountLogRow = getRowFromId("credit_account_log", "account_id", $accountId, "description like 'Donation ID " . $row['donation_id'] . " from%'");
						if (!empty($creditAccountLogRow)) {
							executeQuery("update accounts set credit_limit = credit_limit - ? where account_id = ?", $creditAccountLogRow['amount'], $accountId);
							executeQuery("delete from credit_account_log where credit_account_log_id = ?", $creditAccountLogRow['credit_account_log_id']);
						}
					}
				}

				$deleteSet = executeQuery("delete from donations where donation_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to donations";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to donations";
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
			case "select_donations":
				$pageId = $GLOBALS['gAllPageCodes']["DONATIONMAINT"];
				$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Donations selected in donations Maintenance program";
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

				$allValidFields = array_merge($this->iValidFields, $this->iIgnoreFields, array_keys($this->iConvertFields));
				$requiredFields = array("donation_date", "amount", "designation_code");
				$numericFields = array("amount", "donation_fee");
				$defaultDesignationCode = getFieldFromId("designation_code", "designations", "designation_id", $_POST['designation_id']);

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$contactCount = 0;
				$this->iErrorMessages = array();
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							$fieldName = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
							if (array_key_exists($fieldName, $this->iConvertFields)) {
								$fieldName = $this->iConvertFields[$fieldName];
							}
							$fieldNames[] = $fieldName;
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
							if ($thisFieldName != "country" && !empty($fieldData[$thisFieldName])) {
								$dataFound = true;
							}
						}
						foreach ($fieldData as $thisFieldName => $thisFieldValue) {
							if (in_array($thisFieldName, $this->iNotesFields)) {
								$fieldData['notes'] .= (empty($fieldData['notes']) ? "" : "\n") . $thisFieldName . ": " . $thisFieldValue;
							}
							if (in_array($thisFieldName, $this->iIgnoreFields)) {
								unset($fieldData[$thisFieldName]);
							}
							if (in_array($thisFieldName, $numericFields)) {
								$fieldData[$thisFieldName] = str_replace(",", "", str_replace("$", "", $fieldData[$thisFieldName]));
							}
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);
				$paymentMethods = array();
				$designations = array();

				$errorMessage = "";
				foreach ($importRecords as $index => $thisRecord) {
					$caseFields = array("first_name", "last_name", "address_1", "city", "business_name");
					foreach ($caseFields as $thisFieldName) {
						if (ctype_lower(str_replace("-", "", str_replace(" ", "", $thisRecord[$thisFieldName])))) {
							$thisRecord[$thisFieldName] = implode('-', array_map('ucwords', explode('-', $thisRecord[$thisFieldName])));
						}
					}
					if (empty($thisRecord['contact_id']) && empty($thisRecord['old_contact_id']) && empty($thisRecord['first_name']) && empty($thisRecord['last_name']) && empty($thisRecord['business_name']) && empty($thisRecord['email_address'])) {
						$thisRecord['last_name'] = "Anonymous";
					}
					if (empty($thisRecord['country']) || $thisRecord['country'] == "USA") {
						$countryId = 1000;
					} else {
						$countryId = getFieldFromId("country_id", "countries", "country_name", $thisRecord['country']);
						if (empty($countryId)) {
							$countryId = getFieldFromId("country_id", "countries", "country_code", $thisRecord['country']);
						}
					}
					if (empty($countryId)) {
						$errorMessage .= "<p>Invalid Country: " . $thisRecord['country'] . "</p>";
					}
					$importRecords[$index]['country_id'] = $thisRecord['country_id'] = $countryId;

					if (!array_key_exists("business_name", $thisRecord) && empty($thisRecord['first_name']) && !empty($thisRecord['last_name'])) {
						$thisRecord['business_name'] = $thisRecord['last_name'];
						$thisRecord['last_name'] = "";
					}

					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
					if (empty($contactId) && !empty($thisRecord['contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
					}
					if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
					}
					if (empty($contactId) && !empty($thisRecord['email_address'])) {
						$resultSet = executeQuery("select * from contacts where email_address = ? and client_id = ?", $thisRecord['email_address'], $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 1) {
							$resultSet = executeQuery("select * from contacts where email_address = ? and client_id = ? and contact_id in (select contact_id from donations)", $thisRecord['email_address'], $GLOBALS['gClientId']);
							if ($resultSet['row_count'] != 1) {
								$this->addErrorMessage("Line " . ($index + 2) . ": More than one matching email address found");
							} else {
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
								}
							}
						} else if ($resultSet['row_count'] == 1) {
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
							}
						}
					}
					if (empty($contactId) && empty($thisRecord['email_address']) && (!empty($thisRecord['first_name']) || !empty($thisRecord['last_name']) || !empty($thisRecord['business_name']))) {
						$resultSet = executeQuery("select * from contacts where first_name <=> ? and last_name <=> ? and business_name <=> ? and email_address is null and client_id = ?",
							$thisRecord['first_name'], $thisRecord['last_name'], $thisRecord['business_name'], $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 1) {
							$resultSet = executeQuery("select * from contacts where first_name <=> ? and last_name <=> ? and business_name <=> ? and email_address is null and client_id = ? and contact_id in (select contact_id from donations)",
								$thisRecord['first_name'], $thisRecord['last_name'], $thisRecord['business_name'], $GLOBALS['gClientId']);
							if ($resultSet['row_count'] == 0) {
								$resultSet = executeQuery("select * from contacts where first_name <=> ? and last_name <=> ? and business_name <=> ? and email_address is null and client_id = ?",
									$thisRecord['first_name'], $thisRecord['last_name'], $thisRecord['business_name'], $GLOBALS['gClientId']);
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
								}
							} else if ($resultSet['row_count'] == 1) {
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
								}
							} else {
								$this->addErrorMessage("Line " . ($index + 2) . ": More than one matching name found");
							}
						} else if ($resultSet['row_count'] == 1) {
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
							}
						}
					}
					if (empty($contactId) && (!empty($thisRecord['first_name']) || !empty($thisRecord['last_name']) || !empty($thisRecord['business_name']) || !empty($thisRecord['email_address']))) {
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $thisRecord['first_name'], "last_name" => $thisRecord['last_name'],
							"business_name" => $thisRecord['business_name'], "address_1" => $thisRecord['address_1'], "address_2" => $thisRecord['address_2'], "city" => $thisRecord['city'], "state" => $thisRecord['state'],
							"postal_code" => $thisRecord['postal_code'], "email_address" => $thisRecord['email_address'], "country_id" => $thisRecord['country_id'])))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $contactDataTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
						$contactCount++;
					}
					if (empty($contactId)) {
						$this->addErrorMessage("Line " . ($index + 2) . ": unable to get or create single contact.");
						continue;
					}
					if (!empty($thisRecord['phone_numbers'])) {
						$phoneNumbers = explode("|", $thisRecord['phone_numbers']);
						$phoneDescriptions = explode("|", $thisRecord['phone_descriptions']);
						foreach ($phoneNumbers as $phoneIndex => $thisPhoneNumber) {
							if ($thisRecord['country_id'] <= 1001) {
								$thisPhoneNumber = formatPhoneNumber($thisPhoneNumber);
							}
							if (!empty($thisPhoneNumber)) {
								$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $thisPhoneNumber);
								if (empty($phoneNumberId)) {
									executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)", $contactId, $thisPhoneNumber, $phoneDescriptions[$phoneIndex]);
								}
							}
						}
					} else if (!empty($thisRecord['phone_number'])) {
						$thisPhoneNumber = formatPhoneNumber($thisRecord['phone_number']);
						$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $thisPhoneNumber);
						if (empty($phoneNumberId)) {
							executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $thisPhoneNumber);
						}
					}
					$importRecords[$index]['contact_id'] = $contactId;

					$missingFields = "";

					if (empty($thisRecord['designation_code'])) {
						$thisRecord['designation_code'] = $defaultDesignationCode;
					}

					foreach ($requiredFields as $thisField) {
						if (empty($thisRecord[$thisField])) {
							$this->addErrorMessage("Line " . ($index + 2) . ": " . $thisField . " needs a value");
						}
					}
					if (date("Y-m-d", strtotime($thisRecord['donation_date'])) < '1961-07-23' || date("Y-m-d", strtotime($thisRecord['donation_date'])) > date("Y-m-d")) {
						$this->addErrorMessage("Line " . ($index + 2) . ": Donation date is out of range");
					}

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
				}
				foreach ($paymentMethods as $thisCode => $paymentMethodId) {
					$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", makeCode($thisCode));
					if (empty($paymentMethodId)) {
						$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "description", $thisCode);
					}
					if (empty($paymentMethodId)) {
						$this->addErrorMessage("Invalid Payment Method: " . $thisCode);
					} else {
						$paymentMethods[$thisCode] = $paymentMethodId;
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
							$thisPart = trim($thisPart);
							if (strpos($thisPart, " and ") !== false) {
								$nameParts = explode(" and ", $thisPart);
								$designationParts[$index] = implode(" and ", array_reverse($nameParts));
							}
						}
						$alternateDesignation = implode(", ", $designationParts);
						$designationId = getFieldFromId("designation_id", "designations", "description", $alternateDesignation);

						if (empty($designationId)) {
							$alternateDesignation = str_replace(" and ", " & ", $alternateDesignation);
							$designationId = getFieldFromId("designation_id", "designations", "description", $alternateDesignation);
						}
					}
					if (empty($designationId) && strlen($thisCode) > 6) {
						$resultSet = executeQuery("select * from designations where client_id = ? and description like ?", $GLOBALS['gClientId'], $thisCode . "%");
						if ($resultSet['row_count'] == 1) {
							$row = getNextRow($resultSet);
							$designationId = $row['designation_id'];
						}
					}
					if (empty($designationId)) {
						$this->addErrorMessage("Invalid Designation: " . $thisCode);
					} else {
						$designations[$thisCode] = $designationId;
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

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'donations',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ":" . $resultSet['sql_error'];
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;

				$resultSet = executeQuery("insert into pay_periods (client_id,date_created,date_paid_out,donation_count,total_donations,user_id,log_entry) values " .
					"(?,now(),now(),?,?,?,?)", $GLOBALS['gClientId'], 0, 0, $GLOBALS['gUserId'], "Imported Donations");
				$payPeriodId = $resultSet['insert_id'];

				foreach ($importRecords as $index => $thisRecord) {
					$designationId = $designations[$thisRecord['designation_code']];
					if (empty($designationId)) {
						$designationId = $_POST['designation_id'];
					}
					if (empty($thisRecord['donation_source_id'])) {
						$thisRecord['donation_source_id'] = $_POST['donation_source_id'];
					}
					if (empty($designationId)) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = "Line: " . $index . ": No designation ID found: " . $thisRecord['designation_code'];
						ajaxResponse($returnArray);
						break;
					}
					$amount = str_replace(",", "", $thisRecord['amount']);
					if (empty($amount)) {
						continue;
					}
					$insertSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id,reference_number,designation_id,amount,anonymous_gift,donation_fee,receipt_sent,pay_period_id,donation_source_id,notes) values " .
						"(?,?,?,?,?, ?,?,?,?,?, ?,?,?)", $GLOBALS['gClientId'], $thisRecord['contact_id'], makeDateParameter($thisRecord['donation_date']), $paymentMethods[$thisRecord['payment_method_code']], $thisRecord['reference_number'],
						$designationId, str_replace(",", "", $thisRecord['amount']), (empty($thisRecord['anonymous_gift']) ? 0 : 1), $thisRecord['donation_fee'], makeDateParameter($thisRecord['donation_date']), $payPeriodId,
						$thisRecord['donation_source_id'], $thisRecord['notes']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . ":" . jsonEncode($thisRecord);
						ajaxResponse($returnArray);
						break;
					}
					$donationId = $insertSet['insert_id'];
					Donations::processDonation($donationId);
					$insertCount++;

					foreach ($fieldNames as $thisFieldName) {
						if (empty($thisRecord[$thisFieldName])) {
							continue;
						}
						if (substr($thisFieldName, 0, strlen("custom_field-")) == "custom_field-") {
							$customFieldCode = strtoupper(substr($thisFieldName, strlen("custom_field-")));
							$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode);
							if (empty($customFieldId)) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "Invalid Custom Field";
								ajaxResponse($returnArray);
								break;
							}
							CustomField::setCustomFieldData($thisRecord['contact_id'], $customFieldCode, $thisRecord[$thisFieldName]);
						}
					}

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $donationId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " donations imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " donations updated.</p>";
				$returnArray['response'] .= "<p>" . $contactCount . " contacts created.</p>";
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
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>

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

                <div class="basic-form-line" id="_donation_source_id_row">
                    <label for="donation_source_id" class="">Default source for donations in this CSV</label>
                    <select tabindex="10" class="" id="donation_source_id" name="donation_source_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from donation_sources where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['donation_source_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_id_row">
                    <label for="designation_id" class="">Default designation for donations in this CSV</label>
                    <select tabindex="10" class="" id="designation_id" name="designation_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from designations where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
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
			$resultSet = executeQuery("select * from csv_imports where table_name = 'donations' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
                        <td><span class='far fa-check-square select-donations'></span></td>
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
            $(document).on("click", ".select-donations", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_donations&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
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
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function (returnArray) {
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
                if ($("#_submit_form").data("disabled") === "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
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
        <style>
            #import_error {
                color: rgb(192, 0, 0);
            }
            .remove-import {
                cursor: pointer;
            }
            .select-donations {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these donations being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new DonationCsvImportPage();
$pageObject->displayPage();
