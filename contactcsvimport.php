<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "CONTACTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gSkipCorestoreContactUpdate'] = true;
$GLOBALS['gDontReloadUsersContacts'] = true;
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class ContactCsvImportPage extends Page {

	var $iExistingUserNames = array();
	var $iExistingEmailAddresses = array();
    var $iExistingContactKeys = array();
	var $iContactRedirects = array();
	var $iContactIdentifierTypes = array();
	var $iProgramLogId = "";
	var $iShowDetailedErrors = false;

	var $iValidDefaultFields = array("old_contact_id", "alternate_old_contact_id", "contact_id", "title", "first_name", "middle_name", "last_name", "suffix", "salutation", "preferred_first_name",
		"alternate_name", "business_name", "job_title", "address_1", "address_2", "city", "state", "postal_code", "full_address", "country", "email_address", "web_page", "contact_type_code",
		"birthdate", "notes", "category_codes", "mailing_list_codes", "phone_numbers", "phone_descriptions", "user_name", "password", "user_type_code", "administrator_flag", "source_code",
		"date_created", "deleted", "user_group_code", "inactive", "subscription_code", "subscription_start_date", "subscription_expiration_date", "shares_membership_contact_id",
		"shares_membership_email_address");
	var $iValidCustomFields = array();

	var $iCountryArray = array();
	var $iStateArray = array();

	function setup() {
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ? order by custom_field_code", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iValidCustomFields[] = "custom_field-" . strtolower($row['custom_field_code']);
		}
		$resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iValidDefaultFields[] = "contact_identifier-" . strtolower($row['contact_identifier_type_code']);
			$this->iContactIdentifierTypes[$row['contact_identifier_type_code']] = $row['contact_identifier_type_id'];
		}
		sort($this->iValidDefaultFields);

		$this->iCountryArray = getCountryArray();
		$this->iStateArray = getStateArray();
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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "contacts", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from phone_numbers where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (phone_numbers)");

				$deleteSet = executeQuery("delete from contact_categories where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (contact_categories)");

				$deleteSet = executeQuery("delete from contact_mailing_lists where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (contact_mailing_lists)");

				$deleteSet = executeQuery("delete from contact_subscriptions where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (contact_subscriptions)");

				$deleteSet = executeQuery("delete from relationships where related_contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (relationships)");

				$deleteSet = executeQuery("delete from users where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (users)");

				$deleteSet = executeQuery("delete from contact_redirect where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (contact_redirect)");

				$deleteSet = executeQuery("delete from contacts where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts");

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (csv_import_details)");

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				$this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to contacts (csv_imports)");

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);

				break;
			case "select_contacts":
				$pageId = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
				executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Contacts selected in Contact Maintenance program";
				ajaxResponse($returnArray);
				break;
			case "import_csv":
				if (!$GLOBALS['gUserRow']['superuser_flag']) {
					$_POST['use_address_lookup'] = "";
				}
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gStartTime'] = getMilliseconds();
				$this->addResult("Start Import");
				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");
				$this->addResult("File Opened");

				$allValidFields = array_merge($this->iValidDefaultFields, $this->iValidCustomFields);

				$resultSet = executeQuery("select user_name,contact_id from users where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$this->iExistingUserNames[strtolower($row['user_name'])] = $row['contact_id'];
				}
				$this->addResult("User Names Loaded");

                $resultSet = executeQuery("select contact_id,email_address from contacts where client_id = ? and email_address is not null", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $this->iExistingEmailAddresses[strtolower($row['email_address'])] = $row['contact_id'];
                }
                $this->addResult("Email Addresses Loaded");

                $resultSet = executeQuery("select contact_id,first_name,last_name,postal_code from contacts where client_id = ? and first_name is not null and last_name is not null and postal_code is not null " .
                    "and contact_id not in (select contact_id from orders union select contact_id from donations)", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $this->iExistingContactKeys[strtolower($row['first_name']) . ":" . strtolower($row['last_name']) . ":" . $row['postal_code']] = $row['contact_id'];
                }
                $this->addResult("Contact Name Lookup Loaded");

                $resultSet = executeQuery("select * from contact_redirect where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$this->iContactRedirects[$row['retired_contact_identifier']] = $row['contact_id'];
				}
				$this->addResult("Retired Contact Identifiers Loaded");

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$errorMessage = "";
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
							$errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . " <a class='valid-fields-trigger'>View valid fields</a></p>";
						}
						$this->addResult("Fields: " . jsonEncode($fieldNames));
					} else {
						$fieldData = array();
						$dataFound = false;
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
                            $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
							if ($thisFieldName != "country" && !empty($fieldData[$thisFieldName])) {
								$dataFound = true;
							}
						}
						if (!$dataFound) {
							$this->addResult("Empty Row: " . jsonEncode($csvData));
						}
						if ($dataFound) {
							if ($GLOBALS['gUserId'] == 10000) {
								$fieldData = $this->customProcessing($fieldData);
							}
							if (!empty($fieldData)) {
								$importRecords[] = $fieldData;
							}
						}
					}
					$count++;
				}
				fclose($openFile);
				$this->addResult("File loaded into array, count: " . count($importRecords));

				$contactTypes = array();
				$sourceCodes = array();
				$userTypes = array();
				$userGroups = array();
				$categories = array();
				$mailingLists = array();
				$subscriptions = array();
				$importEmailAddresses = array();

				$countryIds = array();
				$userGroupMemberInsertStatement = "";
				$userGroupMemberInsertParameters = array();
				$csvImportDetailsInsertStatement = "";
				$csvImportDetailsInsertParameters = array();
				$contactRedirectInsertStatement = "";
				$contactRedirectInsertParameters = array();
				$phoneNumbersInsertStatement = "";
				$phoneNumbersInsertParameters = array();
				$contactIdentifiersInsertStatement = "";
				$contactIdentifiersInsertParameters = array();
				$contactSubscriptionsInsertStatement = "";
				$contactSubscriptionsInsertParameters = array();
				$relationshipsInsertStatement = "";
				$relationshipsInsertParameters = array();
				$subscriptionsAdded = false;

				foreach ($importRecords as $index => $thisRecord) {
					if (empty($thisRecord)) {
						continue;
					}
					if (empty($thisRecord['country']) || $thisRecord['country'] == "USA") {
						$countryId = 1000;
					} else if (array_key_exists($thisRecord['country'], $countryIds)) {
						$countryId = $countryIds[$thisRecord['country']];
					} else {
						$countryId = getFieldFromId("country_id", "countries", "country_name", $thisRecord['country']);
						if (empty($countryId)) {
							$countryId = getFieldFromId("country_id", "countries", "country_code", $thisRecord['country']);
						}
					}
					$countryIds[$thisRecord['country']] = $countryId;
					if (empty($countryId)) {
						$errorMessage .= "<p>Line " . ($index + 2) . ": Invalid Country: " . $thisRecord['country'] . "</p>";
					}
					if ($countryId == 1000) {
						if (!empty($thisRecord['postal_code'])) {
							if (strlen($thisRecord['postal_code']) == 9) {
								$thisRecord['postal_code'] = substr($thisRecord['postal_code'], 0, 5) . "-" . substr($thisRecord['postal_code'], 5, 4);
							}
							$thisRecord['postal_code'] = str_replace("-0000", "", $thisRecord['postal_code']);
						}
					}
					$importRecords[$index]['country_id'] = $countryId;
					if (!empty($thisRecord['category_codes'])) {
						$thisRecordCategories = explode("|", strtoupper($thisRecord['category_codes']));
						foreach ($thisRecordCategories as $thisCategory) {
							if (!array_key_exists($thisCategory, $categories) && !empty($thisCategory)) {
								$categories[$thisCategory] = "";
							}
						}
					}
					if (!empty($thisRecord['mailing_list_codes'])) {
						$thisRecordMailingListCodes = explode("|", strtoupper($thisRecord['mailing_list_codes']));
						foreach ($thisRecordMailingListCodes as $thisMailingListCode) {
							if (!array_key_exists($thisMailingListCode, $mailingLists) && !empty($thisMailingListCode)) {
								$mailingLists[$thisMailingListCode] = "";
							}
						}
					}
					if (!empty($thisRecord['contact_type_code'])) {
						if (!array_key_exists(strtoupper($thisRecord['contact_type_code']), $contactTypes)) {
							$contactTypes[strtoupper($thisRecord['contact_type_code'])] = "";
						}
					}
					if (!empty($thisRecord['source_code'])) {
						if (!array_key_exists(strtoupper($thisRecord['source_code']), $sourceCodes)) {
							$sourceCodes[strtoupper($thisRecord['source_code'])] = "";
						}
					}
					if (!empty($thisRecord['user_type_code'])) {
						if (!array_key_exists(strtoupper($thisRecord['user_type_code']), $userTypes)) {
							$userTypes[strtoupper($thisRecord['user_type_code'])] = "";
						}
					}
					if (!empty($thisRecord['user_group_code'])) {
						if (!array_key_exists(strtoupper($thisRecord['user_group_code']), $userGroups)) {
							$userGroups[strtoupper($thisRecord['user_group_code'])] = "";
						}
					}
					if (!empty($thisRecord['subscription_code'])) {
						if (!array_key_exists(strtoupper($thisRecord['subscription_code']), $subscriptions)) {
							$subscriptions[strtoupper($thisRecord['subscription_code'])] = "";
						}
						if (empty(strtotime($thisRecord['subscription_start_date']))) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": Invalid Start Date: " . $thisRecord['subscription_start_date'] . "</p>";
						}
						if (!empty($thisRecord['subscription_expiration_date']) && empty(strtotime($thisRecord['subscription_expiration_date']))) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": Invalid Expiration Date: " . $thisRecord['subscription_expiration_date'] . "</p>";
						}
					}
					if (!empty($thisRecord['shares_membership_contact_id'])) {
						$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['shares_membership_contact_id']);
						if (empty($contactId)) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": Contact ID for primary member in shared membership not found: " . $thisRecord['shares_membership_contact_id'] . "</p>";
						}
					}
					if (!empty($thisRecord['shares_membership_email_address'])) {
						if (!in_array(strtolower($thisRecord['shares_membership_email_address']), $importEmailAddresses)) {
							$resultSet = executeQuery("select * from contacts where email_address = ? and client_id = ?", $thisRecord['shares_membership_email_address'], $GLOBALS['gClientId']);
							if ($resultSet['row_count'] != 1) {
								$errorMessage .= "<p>Line " . ($index + 2) . ": Email address for primary member " . $thisRecord['shares_membership_email_address'] . " in shared membership could not be matched to a contact. (" . $resultSet['row_count'] . " contacts found)</p>";
							} else {
								$row = getNextRow($resultSet);
								$importRecords[$index]['shares_membership_contact_id'] = $row['contact_id'];
							}
						}
					}

					if (!empty($thisRecord['user_name'])) {
						$importRecords[$index]['user_name'] = $thisRecord['user_name'] = makeCode($thisRecord['user_name'], array("lowercase" => true, "allow_dash" => true));
						$contactId = "";
						if (!empty($thisRecord['contact_id'])) {
							$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
						}
						if (empty($contactId) && !empty($thisRecord['contact_id'])) {
							$contactId = $this->iContactRedirects[$thisRecord['contact_id']];
						}
						if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
							$contactId = $this->iContactRedirects[$thisRecord['old_contact_id']];
						}
						if (empty($contactId) && !empty($thisRecord['alternate_old_contact_id'])) {
							$contactId = $this->iContactRedirects[$thisRecord['alternate_old_contact_id']];
						}
                        if (empty($contactId) && !empty($thisRecord['email_address'])) {
                            $contactId = $this->iExistingEmailAddresses[strtolower($thisRecord['email_address'])];
                        }
                        if (empty($contactId) && !empty($thisRecord['first_name']) && !empty($thisRecord['last_name']) && !empty($thisRecord['postal_code']) && empty($thisRecord['email_address'])) {
                            $contactId = $this->iExistingContactKeys[strtolower($thisRecord['first_name']) . ":" . strtolower($thisRecord['last_name']) . ":" . $thisRecord['postal_code']];
                        }
                        if (!empty($contactId)) {
                            $importRecords[$index]['existing_contact_id'] = $contactId;
							$userName = array_search($contactId, $this->iExistingUserNames);
							if (!empty($userName) && $userName != $thisRecord['user_name']) {
								$errorMessage .= "<p>Line " . ($index + 2) . ": " . $thisRecord['user_name'] . " Contact already has a user with different user name</p>";
							}
						}
						if (empty($_POST['skip_users']) && array_key_exists($thisRecord['user_name'], $this->iExistingUserNames) && $this->iExistingUserNames[$thisRecord['user_name']] != $contactId) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": " . $thisRecord['user_name'] . " User name already exists</p>";
						}
						if (empty($_POST['autogenerate_passwords']) && empty($thisRecord['password'])) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": " . $thisRecord['user_name'] . " User name given, but no password</p>";
						}
					}
					$importEmailAddresses[] = strtolower($thisRecord['email_address']);
				}
				$this->addResult("Data validated");
				foreach ($contactTypes as $thisType => $contactTypeId) {
					$contactTypeId = getFieldFromId("contact_type_id", "contact_types", "contact_type_code", makeCode($thisType));
					if (empty($contactTypeId)) {
						$contactTypeId = getFieldFromId("contact_type_id", "contact_types", "description", $thisType);
					}
					if (empty($contactTypeId)) {
						$errorMessage .= "<p>Invalid Contact Type: " . $thisType . "</p>";
					} else {
						$contactTypes[$thisType] = $contactTypeId;
					}
				}
				foreach ($sourceCodes as $thisCode => $sourceId) {
					$sourceId = getFieldFromId("source_id", "sources", "source_code", makeCode($thisCode));
					if (empty($sourceId)) {
						$sourceId = getFieldFromId("source_id", "sources", "description", $thisCode);
					}
					if (empty($sourceId)) {
						$errorMessage .= "<p>Invalid Source Code: " . $thisCode . "</p>";
					} else {
						$sourceCodes[$thisCode] = $sourceId;
					}
				}
				foreach ($userTypes as $thisType => $userTypeId) {
					$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_code", makeCode($thisType));
					if (empty($userTypeId)) {
						$userTypeId = getFieldFromId("user_type_id", "user_types", "description", $thisType);
					}
					if (empty($userTypeId)) {
						$errorMessage .= "<p>Invalid User Type: " . $thisType . "</p>";
					} else {
						$userTypes[$thisType] = $userTypeId;
					}
				}
				foreach ($userGroups as $thisGroup => $userGroupId) {
					$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_code", makeCode($thisGroup));
					if (empty($userGroupId)) {
						$userGroupId = getFieldFromId("user_group_id", "user_groups", "description", $thisGroup);
					}
					if (empty($userGroupId)) {
						$errorMessage .= "<p>Invalid User Group: " . $thisGroup . "</p>";
					} else {
						$userGroups[$thisGroup] = $userGroupId;
					}
				}
				foreach ($categories as $thisCategory => $categoryId) {
					$categoryId = getFieldFromId("category_id", "categories", "category_code", makeCode($thisCategory));
					if (empty($categoryId)) {
						$categoryId = getFieldFromId("category_id", "categories", "description", $thisCategory);
					}
					if (empty($categoryId)) {
						$errorMessage .= "<p>Invalid Category: " . $thisCategory . "</p>";
					} else {
						$categories[$thisCategory] = $categoryId;
					}
				}
				foreach ($mailingLists as $thisMailingList => $mailingListId) {
					$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_code", makeCode($thisMailingList));
					if (empty($mailingListId)) {
						$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "description", $thisMailingList);
					}
					if (empty($mailingListId)) {
						$errorMessage .= "<p>Invalid Mailing List: " . $thisMailingList . "</p>";
					} else {
						$mailingLists[$thisMailingList] = $mailingListId;
					}
				}
				foreach ($subscriptions as $thisSubscription => $subscriptionId) {
					$subscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", makeCode($thisSubscription));
					if (empty($subscriptionId)) {
						$subscriptionId = getFieldFromId("subscription_id", "subscriptions", "description", $thisSubscription);
					}
					if (empty($subscriptionId)) {
						$errorMessage .= "<p>Invalid Subscription: " . $thisSubscription . "</p>";
					} else {
						$subscriptions[$thisSubscription] = $subscriptionId;
					}
				}
				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
                    $this->addResult("Import failed: " . strip_tags(str_replace("</p>", "\n", $returnArray['import_error'])));
                    ajaxResponse($returnArray);
					break;
				}
				$this->addResult("Controls validated");

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'contacts',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], getFirstPart(file_get_contents($_FILES['csv_file']['tmp_name']), 1000000));
				$this->checkSqlError($resultSet, $returnArray);

				$relationshipTypeId = getFieldFromId("relationship_type_id", "relationship_types", "relationship_type_code", "SHARES_MEMBERSHIP");
				if (empty($relationshipTypeId)) {
					$insertSet = executeQuery("insert into relationship_types (client_id,relationship_type_code,description) values (?,'SHARES_MEMBERSHIP','Sharing Membership')", $GLOBALS['gClientId']);
					$this->checkSqlError($insertSet, $returnArray);
					$relationshipTypeId = $insertSet['insert_id'];
				}

				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				$totalCount = 0;
				$skipCount = 0;
				$this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));
				$GLOBALS['gLogLiveQueries'] = true;
				$this->addResult("Begin processing " . count($importRecords) . " records");
				foreach ($importRecords as $index => $thisRecord) {
					if (!empty($_POST['skip_users']) && !empty($thisRecord['user_name'])) {
						$userName = makeCode($thisRecord['user_name'], array("lowercase" => true, "allow_dash" => true));
						$userId = getFieldFromId("user_id", "users", "user_name", $thisRecord['user_name']);
						if (!empty($userId)) {
							$skipCount++;
							continue;
						}
					}
					if (empty($thisRecord)) {
						$skipCount++;
						continue;
					}
					$totalCount++;
					if ($totalCount > 100) {
						$GLOBALS['gLogLiveQueries'] = false;
					}
					if ($totalCount % 1000 == 0) {
						$this->addResult($totalCount . " Records processed");
					}
					if (!array_key_exists("business_name", $thisRecord) && empty($thisRecord['first_name']) && !empty($thisRecord['last_name'])) {
						$thisRecord['business_name'] = $thisRecord['last_name'];
						$thisRecord['last_name'] = "";
					}
					$contactId = "";
					if (array_key_exists("existing_contact_id", $thisRecord)) {
						$contactId = $thisRecord['existing_contact_id'];
					} else {
						if (!empty($thisRecord['contact_id'])) {
							$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
						}
						if (empty($contactId) && !empty($thisRecord['contact_id'])) {
							$contactId = $this->iContactRedirects[$thisRecord['contact_id']];
						}
						if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
							$contactId = $this->iContactRedirects[$thisRecord['old_contact_id']];
						}
						if (empty($contactId) && !empty($thisRecord['alternate_old_contact_id'])) {
							$contactId = $this->iContactRedirects[$thisRecord['alternate_old_contact_id']];
						}
						if (empty($contactId) && !empty($thisRecord['email_address'])) {
							$contactId = getFieldFromId("contact_id", "contacts", "email_address", $thisRecord['email_address']);
						}
						if (empty($contactId) && !empty($thisRecord['first_name']) && !empty($thisRecord['last_name']) && !empty($thisRecord['postal_code']) && empty($thisRecord['email_address'])) {
							$contactId = getFieldFromId("contact_id", "contacts", "last_name", $thisRecord['last_name'], "first_name = ? and postal_code = ? and contact_id not in (select contact_id from donations)", $thisRecord['first_name'], $thisRecord['postal_code']);
						}
					}
					$foundExistingContact = true;
					if (empty($contactId) && !empty($thisRecord['user_name'])) {
						$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $contactId, "contact_id not in (select contact_id from contacts)");
					}
					if (empty($contactId) && !empty($_POST['update_only'])) {
						$skipCount++;
						continue;
					}
					if (!empty($thisRecord['full_address'] && $thisRecord['country_id'] == 1000 && empty($thisRecord['address_1'] . $thisRecord['city'] . $thisRecord['state'] . $thisRecord['postal_code']))) {
						$addressResults = array();
						if ($GLOBALS['gUserRow']['superuser_flag'] && $_POST['use_address_lookup']) {
							$addressResults = $this->lookupAddress($thisRecord['full_address']);    // Try SmartyStreets address lookup
						}
						if (empty($addressResults)) {
							$addressResults = $this->parseAddress($thisRecord['full_address']); // If SmartyStreets fails, parse by words
						}
						if (!empty($addressResults)) {
							$thisRecord = array_merge($thisRecord, $addressResults);
						}
						unset($thisRecord['full_address']);
					}

					if (empty($contactId)) {
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("title" => $thisRecord['title'], "first_name" => $thisRecord['first_name'],
							"middle_name" => $thisRecord['middle_name'], "salutation" => $thisRecord['salutation'], "preferred_first_name" => $thisRecord['preferred_first_name'],
							"last_name" => $thisRecord['last_name'], "alternate_name" => $thisRecord['alternate_name'], "job_title" => $thisRecord['job_title'],
							"suffix" => $thisRecord['suffix'], "business_name" => $thisRecord['business_name'], "address_1" => $thisRecord['address_1'],
							"address_2" => $thisRecord['address_2'], "city" => $thisRecord['city'], "state" => $thisRecord['state'], "postal_code" => $thisRecord['postal_code'],
							"email_address" => $thisRecord['email_address'], "web_page" => $thisRecord['web_page'], "country_id" => $thisRecord['country_id'],
							"contact_type_id" => $contactTypes[strtoupper($thisRecord['contact_type_code'])],
							"birthdate" => makeDateParameter($thisRecord['birthdate']), "notes" => $thisRecord['notes'], "source_id" => $sourceCodes[strtoupper($thisRecord['source_code'])],
							"date_created" => (empty($thisRecord['date_created']) ? date("Y-m-d") : date("Y-m-d", strtotime($thisRecord['date_created']))),
							"deleted" => ($thisRecord['deleted'] == "false" || empty($thisRecord['deleted']) ? "0" : "1"))))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = "Error creating contact: " . $contactDataTable->getErrorMessage() . ($this->iShowDetailedErrors ? ":" . jsonEncode($thisRecord) : "");
                            $this->addResult("Import failed: " . $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
						$foundExistingContact = false;
						$insertCount++;
					} else {
						$nameValues = array();
						$updateFields = array("title", "first_name", "middle_name", "last_name", "suffix", "salutation", "preferred_first_name",
							"alternate_name", "business_name", "job_title", "address_1", "address_2", "city", "state", "postal_code", "country_id",
							"email_address", "web_page", "contact_type_code", "birthdate", "notes");
						foreach ($updateFields as $fieldName) {
							if (!empty($thisRecord[$fieldName])) {
								$nameValues[$fieldName] = $thisRecord[$fieldName];
							}
						}
						$dataTable = new DataTable("contacts");
						$dataTable->setPrimaryId($contactId);
						if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = "Error saving contact: " . $dataTable->getErrorMessage() . ($this->iShowDetailedErrors ? ":" . jsonEncode($thisRecord) : "");
                            $this->addResult("Import failed: " . $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
						$updateCount++;
					}
					if (!empty($thisRecord['user_name'])) {
						$thisRecord['user_name'] = makeCode($thisRecord['user_name'], array("lowercase" => true, "allow_dash" => true));
						$userParts = getMultipleFieldsFromId(array("user_id", "user_name"), "users", "contact_id", $contactId);
						$userId = $userParts['user_id'];
						$userName = $userParts['user_name'];
						if (!empty($userName) && $thisRecord['user_name'] != $userName) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = "<p>Line " . ($index + 2) . ": " . $thisRecord['user_name'] . " Contact already has user with different username</p>";
                            $this->addResult("Import failed: " . $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
						if (empty($userId)) {
							if (array_key_exists($thisRecord['user_name'], $this->iExistingUserNames) && $this->iExistingUserNames[$thisRecord['user_name']] != $contactId) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "<p>Line " . ($index + 2) . ": " . $thisRecord['user_name'] . " User name already exists</p>";
                                $this->addResult("Import failed: " . $returnArray['error_message']);
								ajaxResponse($returnArray);
								break;
							}
							$passwordSalt = getRandomString(64);
							$usersTable = new DataTable("users");
							if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => strtolower($thisRecord['user_name']),
								"password_salt" => $passwordSalt, "password" => $passwordSalt, "user_type_id" => $userTypes[strtoupper($thisRecord['user_type_code'])],
								"administrator_flag" => (empty($thisRecord['administrator_flag']) ? "0" : "1"), "inactive" => (empty($thisRecord['inactive']) ? "0" : "1"),
								"date_created" => date("Y-m-d H:i:s"))))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "Error creating user: " . $usersTable->getErrorMessage() . ($this->iShowDetailedErrors ? ":" . jsonEncode($thisRecord) : "");
                                $this->addResult("Import failed: " . $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
							if (!empty($_POST['autogenerate_passwords'])) {
								$thisRecord['password'] = $thisRecord['password'] ?: getRandomString(64);
							}
							$resultSet = executeQuery("update users set password = ? where user_id = ?", hash("sha256", $userId . $passwordSalt . $thisRecord['password']), $userId);
							$this->checkSqlError($resultSet, $returnArray, "Error creating user");

						}
						$this->iExistingUserNames[$thisRecord['user_name']] = $contactId;
						if (!empty($thisRecord['user_group_code'])) {
							if (!$foundExistingContact || !isInUserGroup($userId, $userGroups[strtoupper($thisRecord['user_group_code'])])) {
								$userGroupMemberInsertStatement .= (empty($userGroupMemberInsertStatement) ? "insert into user_group_members (user_id,user_group_id) values " : ",") . "(?,?)";
								$userGroupMemberInsertParameters[] = $userId;
								$userGroupMemberInsertParameters[] = $userGroups[strtoupper($thisRecord['user_group_code'])];
								if (count($userGroupMemberInsertParameters) > 50000) {
									$insertSet = executeQuery($userGroupMemberInsertStatement, $userGroupMemberInsertParameters);
									$this->checkSqlError($insertSet, $returnArray);
									$userGroupMemberInsertStatement = "";
									$userGroupMemberInsertParameters = array();
								}
							}
						}
					}
					if (!empty($thisRecord['user_type_code'])) {
						$resultSet = executeQuery("update users set user_type_id = ? where contact_id = ?", $userTypes[$thisRecord['user_type_code']], $contactId);
					}
					if (!empty($thisRecord['old_contact_id'])) {
						if (!array_key_exists($thisRecord['old_contact_id'], $this->iContactRedirects) || $this->iContactRedirects[$thisRecord['old_contact_id']] != $contactId) {
							$contactRedirectInsertStatement .= (empty($contactRedirectInsertStatement) ? "insert into contact_redirect (client_id,contact_id,retired_contact_identifier) values " : ",") . "(?,?,?)";
							$contactRedirectInsertParameters[] = $GLOBALS['gClientId'];
							$contactRedirectInsertParameters[] = $contactId;
							$contactRedirectInsertParameters[] = $thisRecord['old_contact_id'];
							if (count($contactRedirectInsertParameters) > 50000) {
								$insertSet = executeQuery($contactRedirectInsertStatement, $contactRedirectInsertParameters);
								$this->checkSqlError($insertSet, $returnArray);
								$contactRedirectInsertStatement = "";
								$contactRedirectInsertParameters = array();
							}
						}
					}
					if (!empty($thisRecord['alternate_old_contact_id'])) {
						if (!array_key_exists($thisRecord['alternate_old_contact_id'], $this->iContactRedirects) || $this->iContactRedirects[$thisRecord['alternate_old_contact_id']] != $contactId) {
							$contactRedirectInsertStatement .= (empty($contactRedirectInsertStatement) ? "insert into contact_redirect (client_id,contact_id,retired_contact_identifier) values " : ",") . "(?,?,?)";
							$contactRedirectInsertParameters[] = $GLOBALS['gClientId'];
							$contactRedirectInsertParameters[] = $contactId;
							$contactRedirectInsertParameters[] = $thisRecord['alternate_old_contact_id'];
							if (count($contactRedirectInsertParameters) > 50000) {
								$insertSet = executeQuery($contactRedirectInsertStatement, $contactRedirectInsertParameters);
								$this->checkSqlError($insertSet, $returnArray);
								$contactRedirectInsertStatement = "";
								$contactRedirectInsertParameters = array();
							}
						}
					}
					$thisRecordCategories = explode("|", strtoupper($thisRecord['category_codes']));
					foreach ($thisRecordCategories as $thisCategory) {
						if (!empty($thisCategory)) {
							$categoryId = $categories[$thisCategory];
							$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "contact_id", $contactId, "category_id = ?", $categoryId);
							if (empty($contactCategoryId)) {
								$insertSet = executeQuery("insert into contact_categories (contact_id,category_id) values (?,?)", $contactId, $categoryId);
								$this->checkSqlError($insertSet, $returnArray);
							}
						}
					}
					$thisRecordMailingLists = explode("|", strtoupper($thisRecord['mailing_list_codes']));
					foreach ($thisRecordMailingLists as $thisMailingList) {
						if (!empty($thisMailingList)) {
							$mailingListId = $mailingLists[$thisMailingList];
							$contactMailingListId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "contact_id", $contactId, "mailing_list_id = ?", $mailingListId);
							if (empty($contactMailingListId)) {
								$insertSet = executeQuery("insert into contact_mailing_lists (contact_id,mailing_list_id,date_opted_in) values (?,?,current_date)", $contactId, $mailingListId);
								$this->checkSqlError($insertSet, $returnArray);
							}
						}
					}
					if (!empty($thisRecord['subscription_code'])) {
						$subscriptionId = $subscriptions[strtoupper($thisRecord['subscription_code'])];
						$startDate = date("Y-m-d", strtotime($thisRecord['subscription_start_date']));
						$endDate = date("Y-m-d", strtotime($thisRecord['subscription_expiration_date']));
						$existingSubscription = getRowFromId("contact_subscriptions", "contact_id", $contactId,
							"subscription_id = ? and inactive = 0 and start_date = ? and expiration_date = ?", $subscriptionId, $startDate, $endDate);
						if (empty($existingSubscription)) {
							$contactSubscriptionsInsertStatement .= (empty($contactSubscriptionsInsertStatement) ? "insert into contact_subscriptions (contact_id, subscription_id, start_date, expiration_date) values " : ",") . "(?,?,?,?)";
							$contactSubscriptionsInsertParameters[] = $contactId;
							$contactSubscriptionsInsertParameters[] = $subscriptionId;
							$contactSubscriptionsInsertParameters[] = $startDate;
							$contactSubscriptionsInsertParameters[] = $endDate;
							if (count($contactSubscriptionsInsertParameters) > 50000) {
								$insertSet = executeQuery($contactSubscriptionsInsertStatement, $contactSubscriptionsInsertParameters);
								$this->checkSqlError($insertSet, $returnArray);
								$contactSubscriptionsInsertStatement = "";
								$contactSubscriptionsInsertParameters = array();
								$subscriptionsAdded = true;
							}
						}
					}
					if (!empty($thisRecord['shares_membership_contact_id']) || !empty($thisRecord['shares_membership_email_address'])) {
						$primaryContactId = $thisRecord['shares_membership_contact_id'] ?: getFieldFromId("contact_id", "contacts",
							"email_address", $thisRecord['shares_membership_email_address']);
						$existingRelationship = getRowFromId("relationships", "contact_id", $primaryContactId,
							"related_contact_id = ? and relationship_type_id = ?", $contactId, $relationshipTypeId);
						if (empty($existingRelationship)) {
							$relationshipsInsertStatement .= (empty($relationshipsInsertStatement) ? "insert into relationships (contact_id, related_contact_id, relationship_type_id) values " : ",") . "(?,?,?)";
							$relationshipsInsertParameters[] = $primaryContactId;
							$relationshipsInsertParameters[] = $contactId;
							$relationshipsInsertParameters[] = $relationshipTypeId;
							if (count($relationshipsInsertParameters) > 50000) {
								$insertSet = executeQuery($relationshipsInsertStatement, $relationshipsInsertParameters);
								$this->checkSqlError($insertSet, $returnArray);
								$relationshipsInsertStatement = "";
								$relationshipsInsertParameters = array();
								$subscriptionsAdded = true;
							}
						}
					}
					if (!empty($thisRecord['phone_numbers'])) {
						$phoneNumbers = explode("|", $thisRecord['phone_numbers']);
						$phoneDescriptions = explode("|", $thisRecord['phone_descriptions']);
						foreach ($phoneNumbers as $phoneIndex => $thisPhoneNumber) {
							if ($thisRecord['country_id'] <= 1001) {
								$thisPhoneNumber = formatPhoneNumber($thisPhoneNumber);
							}
							if (!empty($thisPhoneNumber)) {
								if ($foundExistingContact) {
									$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $thisPhoneNumber);
								} else {
									$phoneNumberId = "";
								}
								if (empty($phoneNumberId)) {
									$phoneNumbersInsertStatement .= (empty($phoneNumbersInsertStatement) ? "insert into phone_numbers (contact_id,phone_number,description) values " : ",") . "(?,?,?)";
									$phoneNumbersInsertParameters[] = $contactId;
									$phoneNumbersInsertParameters[] = $thisPhoneNumber;
									$phoneNumbersInsertParameters[] = $phoneDescriptions[$phoneIndex];
									if (count($phoneNumbersInsertParameters) > 50000) {
										$insertSet = executeQuery($phoneNumbersInsertStatement, $phoneNumbersInsertParameters);
										$this->checkSqlError($insertSet, $returnArray);
										$phoneNumbersInsertStatement = "";
										$phoneNumbersInsertParameters = array();
									}
								}
							}
						}
					}
					foreach ($fieldNames as $thisFieldName) {
						if (empty($thisRecord[$thisFieldName])) {
							continue;
						}
						if (startsWith($thisFieldName, "custom_field-")) {
							$customFieldCode = strtoupper(substr($thisFieldName, strlen("custom_field-")));
							$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode);
							if (empty($customFieldId)) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "Invalid Custom Field: " . $customFieldCode;
                                $this->addResult("Import failed: " . $returnArray['error_message']);
								ajaxResponse($returnArray);
								break;
							}
							CustomField::setCustomFieldData($contactId, $customFieldCode, $thisRecord[$thisFieldName]);
						}
						if (startsWith($thisFieldName, "contact_identifier-")) {
							$contactIdentifierTypeId = $this->iContactIdentifierTypes[strtoupper(substr($thisFieldName, strlen("contact_identifier-")))];
							if (empty($contactIdentifierTypeId)) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "Invalid Contact Identifier Type: " . $thisFieldName;
                                $this->addResult("Import failed: " . $returnArray['error_message']);
								ajaxResponse($returnArray);
								break;
							}
							$thisRecord[$thisFieldName] = str_replace("-", "", $thisRecord[$thisFieldName]);
							$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_id", $contactId,
								"contact_identifier_type_id = ? and identifier_value = ?", $contactIdentifierTypeId, $thisRecord[$thisFieldName]);
							if (empty($contactIdentifierId)) {
								$contactIdentifiersInsertStatement .= (empty($contactIdentifiersInsertStatement) ? "insert ignore into contact_identifiers (contact_id,contact_identifier_type_id,identifier_value) values " : ",") . "(?,?,?)";
								$contactIdentifiersInsertParameters[] = $contactId;
								$contactIdentifiersInsertParameters[] = $contactIdentifierTypeId;
								$contactIdentifiersInsertParameters[] = $thisRecord[$thisFieldName];
								if (count($contactIdentifiersInsertParameters) > 50000) {
									$insertSet = executeQuery($contactIdentifiersInsertStatement, $contactIdentifiersInsertParameters);
									$this->checkSqlError($insertSet, $returnArray);
									$contactIdentifiersInsertStatement = "";
									$contactIdentifiersInsertParameters = array();
								}
							}
						}
					}

					$csvImportDetailsInsertStatement .= (empty($csvImportDetailsInsertStatement) ? "insert into csv_import_details (csv_import_id,primary_identifier) values " : ",") . "(?,?)";
					$csvImportDetailsInsertParameters[] = $csvImportId;
					$csvImportDetailsInsertParameters[] = $contactId;
					if (count($csvImportDetailsInsertParameters) > 50000) {
						$insertSet = executeQuery($csvImportDetailsInsertStatement, $csvImportDetailsInsertParameters);
						$this->checkSqlError($insertSet, $returnArray);
						$csvImportDetailsInsertStatement = "";
						$csvImportDetailsInsertParameters = array();
					}
				}
				$this->addResult("Finished processing");

				if (!empty($userGroupMemberInsertStatement)) {
					$insertSet = executeQuery($userGroupMemberInsertStatement, $userGroupMemberInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
				}

				if (!empty($csvImportDetailsInsertStatement)) {
					$insertSet = executeQuery($csvImportDetailsInsertStatement, $csvImportDetailsInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
				}

				if (!empty($contactRedirectInsertStatement)) {
					$insertSet = executeQuery($contactRedirectInsertStatement, $contactRedirectInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
				}

				if (!empty($phoneNumbersInsertStatement)) {
					$insertSet = executeQuery($phoneNumbersInsertStatement, $phoneNumbersInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
				}

				if (!empty($contactIdentifiersInsertStatement)) {
					$insertSet = executeQuery($contactIdentifiersInsertStatement, $contactIdentifiersInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
				}

				if (!empty($contactSubscriptionsInsertStatement)) {
					$insertSet = executeQuery($contactSubscriptionsInsertStatement, $contactSubscriptionsInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
					$subscriptionsAdded = true;
				}

				if (!empty($relationshipsInsertStatement)) {
					$insertSet = executeQuery($relationshipsInsertStatement, $relationshipsInsertParameters);
					$this->checkSqlError($insertSet, $returnArray);
					$subscriptionsAdded = true;
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				if ($subscriptionsAdded) {
					updateUserSubscriptions();
				}

				$returnArray['response'] = "<p>" . $insertCount . " contacts imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " contacts updated.</p>";
				$returnArray['response'] .= "<p>" . $skipCount . " contacts skipped.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

    private function addResult($message) {
        $this->iProgramLogId = addProgramLog(numberFormat( (getMilliseconds() - $GLOBALS['gStartTime']) / 1000, 2) . ": " . $message, $this->iProgramLogId);
    }

	function customProcessing($thisRecord) {
		return $thisRecord;
	}

	private function lookupAddress($search) {
		$search = strtolower($search);
		$addressSearchResults = getCachedData("autocomplete_addresses", $search, true);

		if (!is_array($addressSearchResults)) {

			$curlHandle = curl_init();
			$url = "https://us-autocomplete-pro.api.smartystreets.com/lookup?key=32345522799156561&search=" . urlencode($search);
			curl_setopt($curlHandle, CURLOPT_URL, $url);
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
			curl_setopt($curlHandle, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
			curl_setopt($curlHandle, CURLOPT_REFERER, 'https://coreware.com');
			$returnValue = curl_exec($curlHandle);

			curl_close($curlHandle);
			if (empty($returnValue)) {
				return array();
			}
			try {
				$suggestions = json_decode($returnValue, true);
			} catch (Exception $e) {
				return array();
			}
			if (!is_array($suggestions['suggestions'])) {
				return array();
			}
			$addressSearchResults = array();
			foreach ($suggestions['suggestions'] as $thisSuggestion) {
				$thisAddress = array("address_1" => $thisSuggestion['street_line'], "address_2" => $thisSuggestion['secondary'], "city" => $thisSuggestion['city'],
					"state" => $thisSuggestion['state'], "postal_code" => $thisSuggestion['zipcode']);
				$addressSearchResults[] = $thisAddress;
			}
			setCachedData("autocomplete_addresses", $search, $addressSearchResults, 48, true);
		}
		return $addressSearchResults[0];
	}

	private function parseAddress($fullAddress) {
		$addressResults = array("address_1" => "", "address_2" => "", "city" => "", "state" => "", "postal_code" => "", "country_id" => "");
		$words = explode(" ", trim($fullAddress));
		$word = array_pop($words);
		if (in_array($word, array("US", "USA"))) {
			$addressResults['country_id'] = 1000;
			$word = array_pop($words);
		}
		if (in_array($word, $this->iCountryArray)) {
			$addressResults['country_id'] = array_search($word, $this->iCountryArray);
			$word = array_pop($words);
		}
		if (is_numeric($word) || is_numeric(str_replace("-", "", $word))) {
			$addressResults['postal_code'] = $word;
			$addressResults['country_id'] = $addressResults['country_id'] ?: 1000;
			$word = array_pop($words);
		}
		if (array_key_exists(strtoupper($word), $this->iStateArray)) {
			$addressResults['state'] = strtoupper($word);
			$word = array_pop($words);
		}
		$addressResults['city'] = trim($word, ",");
		$addressResults['address_1'] = implode(" ", $words);
		return $addressResults;
	}

	function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
		if (!empty($resultSet['sql_error'])) {
			if ($this->iShowDetailedErrors) {
				$returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'] . " - " . $resultSet['query'];
			} else {
				$returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
			}
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			ajaxResponse($returnArray);
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='basic-form-line-messages'><span class="help-label"><a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_update_only_row">
                    <input tabindex="10" class="" type="checkbox" id="update_only" name="update_only" value="1"><label class="checkbox-label" for="update_only">Skip contacts not found</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_users_row">
                    <input tabindex="10" class="" type="checkbox" id="skip_users" name="skip_users" value="1"><label class="checkbox-label" for="skip_users">Skip Where Username Already exists</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_autogenerate_passwords_row">
                    <input tabindex="10" class="" type="checkbox" id="autogenerate_passwords" name="autogenerate_passwords" value="1"><label class="checkbox-label" for="autogenerate_passwords">Auto-generate passwords for user accounts (users will need to reset their password before first login)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_use_address_lookup_row">
					<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                        <input tabindex="10" class="" type="checkbox" id="use_address_lookup" name="use_address_lookup" value="1"><label class="checkbox-label" for="use_address_lookup">Use Online Address Lookup (paid service)</label>
					<?php } else { ?>
                        <input tabindex="10" class="" type="checkbox" id="use_address_lookup" name="use_address_lookup" disabled value="0"><label class="checkbox-label" for="use_address_lookup">Use Online Address Lookup (Contact us for more information)</label>
					<?php } ?>
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
                <th>Undo</th>
				<?php if (canAccessPage("CONTACTMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'contacts' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 48 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row"
                    data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
					<?php if (canAccessPage("CONTACTMAINT")) { ?>
                        <td><span class='far fa-check-square select-contacts'></span></td>
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
            $(document).on("click", ".select-contacts", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_contacts&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
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
                const $submitForm = $("#_submit_form");
                const $editForm = $("#_edit_form");
                const $postIframe = $("#_post_iframe");
                if ($submitForm.data("disabled") === "true") {
                    return false;
                }
                getElapsedTime("start import");
                if ($editForm.validationEngine("validate")) {
                    disableButtons($submitForm);
                    $("body").addClass("waiting-for-ajax");
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($submitForm);
                            return;
                        }
                        getElapsedTime("end import");
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($submitForm);
                    });
                }
                return false;
            });
            $(document).on("tap click", ".valid-fields-trigger", function () {
                $("#_valid_fields_dialog").dialog({
                    modal: true,
                    resizable: true,
                    width: 1000,
                    title: 'Valid Fields',
                    buttons: {
                        Close: function (event) {
                            $("#_valid_fields_dialog").dialog('close');
                        }
                    }
                });
            });
            $("#_valid_fields_dialog .accordion").accordion({
                active: false,
                heightStyle: "content",
                collapsible: true
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

            .select-contacts {
                cursor: pointer;
            }

            #_valid_fields_dialog .ui-accordion-content {
                max-height: 200px;
            }

            #_valid_fields_dialog > ul {
                columns: 3;
                padding-bottom: 1rem;
            }

            #_valid_fields_dialog .ui-accordion ul {
                columns: 2;
            }

            #_valid_fields_dialog ul li {
                padding-right: 20px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog - box">
            This will result in these contacts being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->

        <div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
            <ul>
                <li><?= implode("</li><li>", $this->iValidDefaultFields) ?></li>
            </ul>

            <div class="accordion">
				<?php if (!empty($this->iValidCustomFields)) { ?>
                    <h3>Valid Custom Fields</h3>
                    <div>
                        <ul>
                            <li><?= implode("</li><li>", $this->iValidCustomFields) ?></li>
                        </ul>
                    </div>
				<?php } ?>
            </div>
        </div>
		<?php
	}
}

$pageObject = new ContactCsvImportPage();
$pageObject->displayPage();
