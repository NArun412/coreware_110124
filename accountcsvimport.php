<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "ACCOUNTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 3000000;

class AccountCsvImportPage extends Page {

	var $iErrorMessages = array();
	var $iContactIdentifierTypes = array();
	var $iValidFields = array("old_contact_id", "contact_id", "last_name", "business_name", "first_name", "middle_name", "address_1", "address_2", "city", "state", "postal_code", "email_address",
		"country", "phone_number", "payment_method_code", "account_number", "routing_number", "expiration_date", "account_token", "merchant_identifier");

    private $iProgramLogId = false;
    function setup() {
		$resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iValidFields[] = "contact_identifier-" . strtolower($row['contact_identifier_type_code']);
			$this->iContactIdentifierTypes[strtolower($row['contact_identifier_type_code'])] = $row['contact_identifier_type_id'];
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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "accounts", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to accounts #872";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from accounts where account_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to accounts #294";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to accounts #183";
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

				$updateRecurring = !empty($_POST['update_recurring']);
				$makeOldAccountsInactive = !empty($_POST['make_old_accounts_inactive']);

				$allValidFields = $this->iValidFields;
				$requiredFields = array("payment_method_code", "account_number|account_token");
				$numericFields = array();

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
                $GLOBALS['gStartTime'] = getMilliseconds();
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

                $this->addResult("Account import: " . count($importRecords) . " rows found");

                $paymentMethods = array();
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
					if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
					}
					if (empty($contactId)) {
						foreach ($this->iContactIdentifierTypes as $thisIdentifierType => $thisIdentifierId) {
							if (!empty($thisRecord['contact_identifier-' . $thisIdentifierType])) {
								$contactId = getFieldFromId("contact_id", "contact_identifiers", "identifier_value", $thisRecord['contact_identifier-' . $thisIdentifierType],
									"contact_identifier_type_id = ?", $thisIdentifierId);
								if (!empty($contactId)) {
									break;
								}
							}
						}
					}
					$importRecords[$index]['contact_id'] = $contactId;

					$missingFields = "";
					foreach ($requiredFields as $thisField) {
						if (strpos($thisField, "|") === false) {
							if (empty($thisRecord[$thisField])) {
								$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
							}
						} else {
							$found = false;
							foreach (explode("|", $thisField) as $orField) {
								if (!empty($thisRecord[$orField])) {
									$found = true;
								}
							}
							if (!$found) {
								$missingFields .= (empty($missingFields) ? "" : ", ") . str_replace(" OR ", "|", $thisField);
							}
						}
					}
					if (!empty($missingFields)) {
						$this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
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

				$merchantAccountId = $_POST['merchant_account_id'];
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				if (!$eCommerce || !$eCommerce->hasCustomerDatabase()) {
					ajaxResponse($this->rollbackImport($csvImportId, "Unable to connect to Merchant Services with customer database"));
					break;
				}


				foreach ($importRecords as $index => $thisRecord) {
					$paymentMethodId = $paymentMethods[$thisRecord['payment_method_code']];
					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $paymentMethodId));
					$importRecords[$index]['payment_method_type_code'] = $paymentMethodTypeCode;
					switch ($paymentMethodTypeCode) {
						case "BANK_ACCOUNT":
							if ((empty($thisRecord['account_number']) || empty($thisRecord['routing_number']))
								&& (empty($thisRecord['account_token']) || (empty($thisRecord['merchant_identifier']) && $eCommerce->requiresCustomerToken()))) {
								$this->addErrorMessage("Line " . ($index + 2) . ": missing account information");
							}
							break;
						case "CREDIT_CARD":
							if (empty($thisRecord['account_token'])) {
								if (empty($thisRecord['account_number']) || empty($thisRecord['expiration_date'])) {
									$this->addErrorMessage("Line " . ($index + 2) . ": missing account information");
								}
								$expirationDate = $this->parseExpirationDate($thisRecord['expiration_date']);
								if ($expirationDate < "1990-01-01") {
									$this->addErrorMessage("Line " . ($index + 2) . ": invalid expiration date");
								}
							} else {
								if (empty($thisRecord['merchant_identifier']) && $eCommerce->requiresCustomerToken()) {
									$this->addErrorMessage("Line " . ($index + 2) . ": missing customer merchant identifier");
								}
							}
							break;
					}
				}

				if (!empty($this->iErrorMessages)) {
					$returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
					foreach ($this->iErrorMessages as $thisMessage => $count) {
						$returnArray['import_error'] .= "<p>" . $count . ": " . $thisMessage . "</p>";
					}
                    $this->addResult($returnArray['import_error']);
                    $logEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $this->iProgramLogId);
                    sendEmail(["subject"=>"Account Import Error", "body"=>$logEntry, "email_address"=>$GLOBALS['gUserRow']['email_address']]);
					ajaxResponse($returnArray);
					break;
				}

                $this->addResult("Data validated. Starting import");

                $GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id) values (?,?,'accounts',?,now(),?)", $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

                $processCount = 0;
				$insertCount = 0;
				$updateCount = 0;
				$updatePaymentCount = 0;
				$updateDonationCount = 0;
				$inactiveCount = 0;
				$skipCount = 0;
				$failedCount = 0;
				$failedRows = array();
				$recurringPaymentsTable = new DataTable('recurring_payments');
				$recurringPaymentsTable->setSaveOnlyPresent(true);
				$recurringDonationsTable = new DataTable('recurring_donations');
				$recurringDonationsTable->setSaveOnlyPresent(true);
				$accountsTable = new DataTable('accounts');
				$accountsTable->setSaveOnlyPresent(true);
				foreach ($importRecords as $index => $thisRecord) {
                    $processCount++;
                    if($processCount % 1000 == 0) {
                        $this->addResult($processCount . " records processed");
                    }

                    $contactId = $thisRecord['contact_id'];
					if (empty($contactId)) {
						$thisRecord['error_message'] = "Contact ID not found";
						$failedRows[] = $thisRecord;
						$skipCount++;
						continue;
					}
					$contactRow = Contact::getContact($contactId);
					$isBankAccount = ($thisRecord['payment_method_type_code'] == "BANK_ACCOUNT");
					$thisRecord['account_number'] = str_replace(" ", "", $thisRecord['account_number']);
					if (empty($thisRecord['merchant_identifier'])) {
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
					} else {
						$merchantIdentifier = $thisRecord['merchant_identifier'];
						$success = $eCommerce->getCustomerProfile(array("merchant_identifier" => $merchantIdentifier));
						if ($success) {
							$merchantProfileId = getFieldFromId("merchant_profile_id", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
							if (empty($merchantProfileId)) {
								executeQuery("insert into merchant_profiles (contact_id,merchant_account_id,merchant_identifier) values (?,?,?)",
									$contactId, $merchantAccountId, $merchantIdentifier);
							} else {
								executeQuery("update merchant_profiles set merchant_identifier = ? where contact_id = ? and merchant_account_id = ?",
									$merchantIdentifier, $contactId, $merchantAccountId);
							}
						} else {
							$merchantIdentifier = "";
						}
					}
					if (empty($merchantIdentifier)) {
						if ($_POST['skip_accounts_not_found']) {
							$failedCount++;
							$thisRecord['error_message'] = "Unable to create the merchant profile for contact ID " . $contactId;
							$failedRows[] = $thisRecord;
							continue;
						} else {
							ajaxResponse($this->rollbackImport($csvImportId, "Unable to create the merchant profile for contact ID " . $contactId));
							break;
						}
					}

					if ($isBankAccount) {
						$accountId = getFieldFromId("account_id", "accounts", "contact_id", $contactId,
							"inactive = 0 and payment_method_id = ? and account_number like ? and merchant_account_id = ?",
							$paymentMethods[$thisRecord['payment_method_code']], "%" . substr($thisRecord["account_number"], -4), $merchantAccountId);
					} else {
						$accountId = getFieldFromId("account_id", "accounts", "contact_id", $contactId,
							"inactive = 0 and payment_method_id = ? and account_number like ? and merchant_account_id = ? and expiration_date = ?",
							$paymentMethods[$thisRecord['payment_method_code']], "%" . substr($thisRecord["account_number"], -4), $merchantAccountId, makeDateParameter($thisRecord['expiration_date']));
					}
					if (empty($accountId)) {
						$error = "";
						if (empty($thisRecord['account_token'])) {

							$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
								"first_name" => (empty($thisRecord['first_name']) ? $contactRow['first_name'] : $thisRecord['first_name']),
								"last_name" => (empty($thisRecord['last_name']) ? $contactRow['last_name'] : $thisRecord['last_name']),
								"business_name" => $thisRecord['business_name'],
								"address_1" => (empty($thisRecord['address_1']) ? $contactRow['address_1'] : $thisRecord['address_1']),
								"city" => (empty($thisRecord['city']) ? $contactRow['city'] : $thisRecord['city']),
								"state" => (empty($thisRecord['state']) ? $contactRow['state'] : $thisRecord['state']),
								"postal_code" => (empty($thisRecord['postal_code']) ? $contactRow['postal_code'] : $thisRecord['postal_code']),
								"country_id" => (empty($thisRecord['country_id']) ? $contactRow['country_id'] : $thisRecord['country_id']));
							if ($isBankAccount) {
								$expirationDate = "";
								$paymentArray['bank_routing_number'] = $thisRecord['routing_number'];
								$paymentArray['bank_account_number'] = $thisRecord['account_number'];
								$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $thisRecord['payment_method_id']))))));
							} else {
								$expirationDate = $this->parseExpirationDate($thisRecord['expiration_date']);
								$paymentArray['card_number'] = $thisRecord['account_number'];
								$paymentArray['expiration_date'] = $expirationDate;
								$paymentArray['card_code'] = "SKIP_CARD_CODE";
							}
							$success = $eCommerce->createCustomerPaymentProfile($paymentArray);
							$response = $eCommerce->getResponse();
							if ($success) {
								$accountToken = $response['account_token'];
								$merchantIdentifier = $response['merchant_identifier'];
								$lastFour = substr($thisRecord["account_number"], -4);
							} else {
								$error = $eCommerce->getErrorMessage() ?: $response['response_reason_text'];
								$accountToken = "";
							}
						} else {
							$success = $eCommerce->getCustomerPaymentProfile(array('account_token' => $thisRecord['account_token'],
								'merchant_identifier' => $merchantIdentifier));
							$response = $eCommerce->getResponse();
							if ($success) {
								$accountToken = $thisRecord['account_token'];
								$expirationDate = $this->parseExpirationDate($response['expiration_date']);
								if (strtotime($expirationDate) < time()) {
									$expirationDate = $this->parseExpirationDate($thisRecord['expiration_date']);
								}
								if (strtotime($expirationDate) < time()) {
									$expirationDate = "";
								}
								$lastFour = (empty($response['card_number']) ? substr($response['account_number'], -4) : substr($response['card_number'], -4));
							} else {
								$error = $eCommerce->getErrorMessage() ?: $response['response_reason_text'];
								$accountToken = "";
							}
						}

						if (!empty($accountToken)) {
							$paymentMethodId = $paymentMethods[$thisRecord['payment_method_code']];
							$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $paymentMethodId) . " - " . $lastFour;
							$fullName = trim($thisRecord['first_name'] . " " . $thisRecord['last_name'] . (empty($thisRecord['business_name']) ? "" : ", " . $thisRecord['business_name']));
							$fullName = $fullName ?: getDisplayName($contactId);
							$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
								"account_number,expiration_date,merchant_identifier,merchant_account_id,account_token,inactive) values (?,?,?,?,?, ?,?,?,?,?)", $contactId, $accountLabel,
								$paymentMethodId, $fullName, "XXXX-" . $lastFour, (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate))),
								$merchantIdentifier, $merchantAccountId, $accountToken, 0);
							if (!empty($resultSet['sql_error'])) {
								ajaxResponse($this->rollbackImport($csvImportId, getSystemMessage("basic", $resultSet['sql_error'])));
								break;
							}
							$accountId = $resultSet['insert_id'];
							$insertCount++;
							if ($updateRecurring) {
								$oldAccountsResultSet = executeQuery("select account_id from accounts where contact_id = ? and account_number like ? and merchant_account_id <> ?",
									$contactId, "%-" . $lastFour, $merchantAccountId);
								while ($oldAccountRow = getNextRow($oldAccountsResultSet)) {
									$recurringPaymentSet = executeQuery("select * from recurring_payments where account_id = ? and contact_id = ?",
										$oldAccountRow['account_id'], $contactId);
									while ($thisRecurringPayment = getNextRow($recurringPaymentSet)) {
										if (!$recurringPaymentsTable->saveRecord(array("name_values" => array("account_id" => $accountId), "primary_id" => $thisRecurringPayment['recurring_payment_id']))) {
											ajaxResponse($this->rollbackImport($csvImportId, $recurringPaymentsTable->getErrorMessage()));
											break;
										} else {
											$updatePaymentCount++;
										}
									}
									$recurringDonationSet = executeQuery("select * from recurring_donations where account_id = ? and contact_id = ?",
										$oldAccountRow['account_id'], $contactId);
									while ($thisRecurringDonation = getNextRow($recurringDonationSet)) {
										if (!$recurringDonationsTable->saveRecord(array("name_values" => array("account_id" => $accountId), "primary_id" => $thisRecurringDonation['recurring_donation_id']))) {
											ajaxResponse($this->rollbackImport($csvImportId, $recurringDonationsTable->getErrorMessage()));
											break;
										} else {
											$updateDonationCount++;
										}
									}
									if ($makeOldAccountsInactive) {
										if (!$accountsTable->saveRecord(array("name_values" => array("inactive" => 1), "primary_id" => $oldAccountRow['account_id']))) {
											ajaxResponse($this->rollbackImport($csvImportId, $accountsTable->getErrorMessage()));
											break;
										} else {
											$inactiveCount++;
										}
									}
								}
							}
							$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $accountId);
							if (!empty($insertSet['sql_error'])) {
								ajaxResponse($this->rollbackImport($csvImportId, getSystemMessage("basic", $insertSet['sql_error'])));
								break;
							}
						} else {
							if ($_POST['skip_accounts_not_found']) {
								$failedCount++;
								$thisRecord['error_message'] = "Unable to create the account token for contact ID " . $contactId . (empty($error) ? "" :  ": " . $error);
								$failedRows[] = $thisRecord;
							} else {
								ajaxResponse($this->rollbackImport($csvImportId, "Unable to create the account token for contact ID " . $contactId));
								break;
							}
						}
					} else {
						$updateCount++;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['response'] = "<p>" . $insertCount . " accounts imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " existing accounts found.</p>";
				$returnArray['response'] .= "<p>" . $skipCount . " skipped because no contact found.</p>";
				if ($updateRecurring) {
					$returnArray['response'] .= "<p>" . $updatePaymentCount . " recurring payments updated.</p>";
					$returnArray['response'] .= "<p>" . $updateDonationCount . " recurring donations updated.</p>";
					$returnArray['response'] .= "<p>" . $inactiveCount . " old accounts marked inactive.</p>";
				}
				if ($_POST['skip_accounts_not_found']) {
					$returnArray['response'] .= "<p>" . $failedCount . " skipped because account was not found on merchant gateway.</p>";
					if (!empty($failedRows)) {
						$failedContent = "<table><tr>";
						foreach ($failedRows[0] as $thisKey => $thisValue) {
							$failedContent .= "<th>" . $thisKey . "</th>";
						}
						$failedContent .= "</tr>";
						foreach ($failedRows as $thisRow) {
							$failedContent .= "<tr>";
							foreach ($thisRow as $thisValue) {
								$failedContent .= "<td>" . htmlText($thisValue) . "</td>";
							}
							$failedContent .= "</tr>";
						}
						$failedContent .= "</table>";
						$returnArray['response'] .= $failedContent;
					}
				}
                $this->addResult($returnArray['response']);
                $logEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $this->iProgramLogId);
                sendEmail(["subject"=>"Account Import Results", "body"=>$logEntry, "email_address"=>$GLOBALS['gUserRow']['email_address']]);
				ajaxResponse($returnArray);
				break;
		}

	}

    private function addResult($message) {
        $this->iProgramLogId = addProgramLog(numberFormat( (getMilliseconds() - $GLOBALS['gStartTime']) / 1000, 2) . ": " . $message, $this->iProgramLogId);
        error_log($GLOBALS['gClientRow']['client_code'] . " : " . date("m/d/Y H:i:s") . " : " . (is_array($message) ? json_encode($message) : $message) . "\n", 3,
            "/var/www/html/cache/import.log");
    }

    function addErrorMessage($errorMessage) {
		if (array_key_exists($errorMessage, $this->iErrorMessages)) {
			$this->iErrorMessages[$errorMessage]++;
		} else {
			$this->iErrorMessages[$errorMessage] = 1;
		}
	}

	private function rollbackImport($csvImportId, $errorMessage) {
		executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
		executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
		$returnArray['error_message'] = $returnArray['import_error'] = $errorMessage;
        $this->addResult($returnArray['import_error']);
        $logEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $this->iProgramLogId);
        sendEmail(["subject"=>"Account Import Error", "body"=>$logEntry, "email_address"=>$GLOBALS['gUserRow']['email_address']]);
        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		return $returnArray;
	}

	private function parseExpirationDate($input) {
		if (strlen($input) <= 5) {
			if (is_numeric($input)) {
				$parts = str_split($input, 2);
			} else {
				$parts = explode("/", str_replace("-", "/", $input));
			}
			$expirationDate = date("Y-m-d", strtotime($parts[0] . "/01/" . $parts[1]));
		} else {
			$expirationDate = date("Y-m-d", strtotime($input));
		}
		return $expirationDate;
	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>

            <form id="_edit_form" enctype='multipart/form-data'>

                <div class='basic-form-line'>
                    <label for="merchant_account_id">Import into Merchant Account</label>
                    <select id="merchant_account_id" name="merchant_account_id">
						<?php
						$defaultCode = ($GLOBALS['gDevelopmentServer'] ? "DEVELOPMENT" : "DEFAULT");
						$merchantResults = executeQuery("select * from merchant_accounts where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
						while ($merchantRow = getNextRow($merchantResults)) {
							$selected = $merchantRow['merchant_account_code'] == $defaultCode ? "selected" : "";
							echo '<option ' . $selected . ' value="' . $merchantRow['merchant_account_id'] . '">' . $merchantRow['description'] . "</option>";
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

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

                <div class="basic-form-line" id="_update_recurring_row">
                    <input type="checkbox" name="update_recurring" id="update_recurring" value="1"><label class="checkbox-label" for="update_recurring">Update existing recurring payments and donations with matching imported accounts</label>
                    <p><strong>Important:</strong> If this is checked, the import can not be undone.</p>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_make_old_accounts_inactive">
                    <input type="checkbox" name="make_old_accounts_inactive" id="make_old_accounts_inactive" value="1"><label class="checkbox-label" for="make_old_accounts_inactive">Make old accounts matching import inactive</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_accounts_not_found_row">
                    <input type="checkbox" tabindex="10" id="skip_accounts_not_found" name="skip_accounts_not_found" value="1"><label
                            class="checkbox-label" for="skip_accounts_not_found">Skip accounts that cannot be found on the merchant gateway (instead of failing).</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

                <div id="import_error"></div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th></th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'accounts' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 48 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row" data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
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
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these accounts being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new AccountCsvImportPage();
$pageObject->displayPage();
