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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}
$GLOBALS['gDontReloadUsersContacts'] = true;

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "merge_contacts";
	}

	function process() {
		$limitContactIds = array();
		if (!empty($_GET['contact_ids'])) {
			$limitContactIds = explode("|",$_GET['contact_ids']);
			$this->addResult("Check: " . jsonEncode($limitContactIds));
			foreach ($limitContactIds as $contactId) {
				$contactId = getFieldFromId("contact_id","contacts","contact_id",$contactId,"client_id is not null");
				if (empty($contactId)) {
					$limitContactIds = array();
					break;
				}
			}
		}
		if (empty($limitContactIds) && !empty($_GET['contact_ids'])) {
			$this->addResult("Invalid contact IDs");
			return;
		}
		if (!empty($limitContactIds)) {
			$this->addResult("Limit to: " . jsonEncode($limitContactIds));
		}
		$clientSet = executeQuery("select * from clients where inactive = 0" . (empty($_GET['client_id']) ? "" : " and client_id = " . makeNumberParameter($_GET['client_id'])));

		$contactDataFields = array("title", "first_name", "middle_name", "last_name", "suffix", "preferred_first_name", "alternate_name", "business_name", "company_id", "job_title", "salutation", "address_1", "address_2", "city", "state", "postal_code", "country_id", "attention_line", "email_address", "web_page", "contact_type_id", "source_id", "birthdate", "image_id", "notes");
		$ignoreTables = array("federal_firearms_licensees", "product_manufacturers", "class_instructors", "clients", "companies", "designations", "developers", "locations", "vendors");
		$onlyOneTables = array("users", "recurring_donations", "recurring_payments", "contact_subscriptions", "orders", "donations", "invoices", "accounts");
		$exactAllBothTables = array("recurring_donations", "recurring_payments", "contact_subscriptions", "orders", "donations", "invoices", "accounts");
		$exactFields = array("first_name"=>true,"last_name"=>true,"address_1"=>true,"postal_code"=>array("must_have_value"=>true,"check_first_chars"=>5),"email_address"=>true,"business_name"=>false);

		while ($clientRow = getNextRow($clientSet)) {
			$clientId = $clientRow['client_id'];
			changeClient($clientId);

			# search by email address
			# search by first name, last name, state

			$queryText = "client_id = ? and contact_id not in (select contact_id from users where superuser_flag = 1 or full_client_access = 1)";
			$queryText .= " and contact_id not in (select contact_id from clients)";
			foreach ($ignoreTables as $tableName) {
				$queryText .= " and contact_id not in (select contact_id from " . $tableName . " where contact_id is not null)" . (empty($limitContactIds) ? "" : " and contact_id in (" . implode(",",$limitContactIds) . ")");
			}
			$queryParameters = array($GLOBALS['gClientId']);
			$fieldsArray = array("email_address");
			foreach ($fieldsArray as $fieldName) {
				$queryText .= ($queryText ? " and " : "") . $fieldName . " is not null";
			}
			$dupArray = array();
			$resultSet = executeQuery("select * from contacts where " . $queryText, $queryParameters);
			while ($row = getNextRow($resultSet)) {
				$dupKey = "";
				foreach ($fieldsArray as $fieldName) {
					$dupKey .= (empty($dupKey) ? "" : "|") . $row[$fieldName];
				}
				$dupKey = strtolower($dupKey);
				$contactId = $row['contact_id'];
				if (array_key_exists($dupKey, $dupArray)) {
					$dupArray[$dupKey][] = $contactId;
				} else {
					$dupArray[$dupKey] = array($contactId);
				}
			}

			$queryText = "client_id = ?";
			foreach ($ignoreTables as $tableName) {
				$queryText .= " and contact_id not in (select contact_id from " . $tableName . " where contact_id is not null)" . (empty($limitContactIds) ? "" : " and contact_id in (" . implode(",",$limitContactIds) . ")");
			}
			$queryParameters = array($GLOBALS['gClientId']);
			$fieldsArray = array("first_name", "last_name", "state");
			foreach ($fieldsArray as $fieldName) {
				$queryText .= ($queryText ? " and " : "") . $fieldName . " is not null";
			}
			$resultSet = executeQuery("select * from contacts where " . $queryText, $queryParameters);
			while ($row = getNextRow($resultSet)) {
				$dupKey = "";
				foreach ($fieldsArray as $fieldName) {
					$dupKey .= (empty($dupKey) ? "" : "|") . $row[$fieldName];
				}
				$dupKey = strtolower($dupKey);
				$contactId = $row['contact_id'];
				if (array_key_exists($dupKey, $dupArray)) {
					$dupArray[$dupKey][] = $contactId;
				} else {
					$dupArray[$dupKey] = array($contactId);
				}
			}

			$queryText = "client_id = ?";
			foreach ($ignoreTables as $tableName) {
				$queryText .= " and contact_id not in (select contact_id from " . $tableName . " where contact_id is not null)" . (empty($limitContactIds) ? "" : " and contact_id in (" . implode(",",$limitContactIds) . ")");
			}
			$queryParameters = array($GLOBALS['gClientId']);
			$fieldsArray = array("business_name", "address_1", "postal_code");
			foreach ($fieldsArray as $fieldName) {
				$queryText .= ($queryText ? " and " : "") . $fieldName . " is not null";
			}
			$resultSet = executeQuery("select * from contacts where " . $queryText, $queryParameters);
			while ($row = getNextRow($resultSet)) {
				$dupKey = "";
				foreach ($fieldsArray as $fieldName) {
					$dupKey .= (empty($dupKey) ? "" : "|") . $row[$fieldName];
				}
				$dupKey = strtolower($dupKey);
				$contactId = $row['contact_id'];
				if (array_key_exists($dupKey, $dupArray)) {
					$dupArray[$dupKey][] = $contactId;
				} else {
					$dupArray[$dupKey] = array($contactId);
				}
			}

			$queryText = "client_id = ? and email_address is null and city is null and address_1 is null and postal_code is null";
			foreach ($ignoreTables as $tableName) {
				$queryText .= " and contact_id not in (select contact_id from " . $tableName . " where contact_id is not null)" . (empty($limitContactIds) ? "" : " and contact_id in (" . implode(",",$limitContactIds) . ")");
			}
			$queryParameters = array($GLOBALS['gClientId']);
			$fieldsArray = array("first_name","last_name");
			foreach ($fieldsArray as $fieldName) {
				$queryText .= ($queryText ? " and " : "") . $fieldName . " is not null";
			}
			$resultSet = executeQuery("select * from contacts where " . $queryText, $queryParameters);
			while ($row = getNextRow($resultSet)) {
				$dupKey = "";
				foreach ($fieldsArray as $fieldName) {
					$dupKey .= (empty($dupKey) ? "" : "|") . $row[$fieldName];
				}
				$dupKey = strtolower($dupKey);
				$contactId = $row['contact_id'];
				if (array_key_exists($dupKey, $dupArray)) {
					$dupArray[$dupKey][] = $contactId;
				} else {
					$dupArray[$dupKey] = array($contactId);
				}
			}

			foreach ($dupArray as $index => $contactIds) {
				if (count($contactIds) < 2) {
					unset($dupArray[$index]);
				}
			}

			$dupIdArray = array();
			$dupCount = 0;
			$duplicateIdsArray = array();
			foreach ($dupArray as $dupKey => $contactArray) {
				if (count($contactArray) > 1) {
					$maxIndex = count($contactArray) - 1;
					$startIndex = 0;
					while ($startIndex < $maxIndex) {
						$nextIndex = $startIndex + 1;
						while ($nextIndex <= $maxIndex) {
							if (!in_array($contactArray[$startIndex], $dupIdArray)) {
								$dupIdArray[] = $contactArray[$startIndex];
							}
							if (!in_array($contactArray[$nextIndex], $dupIdArray)) {
								$dupIdArray[] = $contactArray[$nextIndex];
							}
							$checkSet = executeQuery("select * from duplicate_exclusions where client_id = ? and ((contact_id = ? and duplicate_contact_id = ?) or " .
								"(duplicate_contact_id = ? and contact_id = ?))", $GLOBALS['gClientId'], $contactArray[$startIndex], $contactArray[$nextIndex],
								$contactArray[$startIndex], $contactArray[$nextIndex]);
							if ($checkSet['row_count'] == 0) {
								$duplicateIdsArray[] = array($contactArray[$startIndex], $contactArray[$nextIndex]);
							}
							$nextIndex++;
						}
						$startIndex++;
					}
				}
			}

			$mergedContactIds = array();
			$countsArray = array("skipped"=>0,"merged"=>0,"success"=>0,"error"=>0,"one_already_merged"=>0,"contact_differences"=>0,"differing_contact_identifiers"=>0);
			foreach ($duplicateIdsArray as $duplicateContacts) {
				$firstContactId = $duplicateContacts[0];
				$secondContactId = $duplicateContacts[1];
				if ($firstContactId == $secondContactId) {
					continue;
				}
				if (array_key_exists($firstContactId, $mergedContactIds) || array_key_exists($secondContactId, $mergedContactIds)) {
					$countsArray['skipped']++;
					$countsArray['one_previously_merged']++;
					continue;
				}
				$firstContactRow = getRowFromId("contacts", "contact_id", $firstContactId);
				$secondContactRow = getRowFromId("contacts", "contact_id", $secondContactId);
				$exactDuplicateContacts = true;
				if (function_exists("customAutoMergeIsExactMatch")) {
					$exactDuplicateContacts = customAutoMergeIsExactMatch($firstContactRow,$secondContactRow);
				} else {
					foreach ($exactFields as $fieldName => $mustHaveValue) {
						$testValue1 = $firstContactRow[$fieldName];
						$testValue2 = $secondContactRow[$fieldName];
						if (is_array($mustHaveValue)) {
							if (!empty($mustHaveValue['check_first_chars'])) {
								$testValue1 = substr($testValue1, 0, $mustHaveValue['check_first_chars']);
								$testValue2 = substr($testValue2, 0, $mustHaveValue['check_first_chars']);
							}
							$mustHaveValue = $mustHaveValue['must_have_value'];
						}
						if ($mustHaveValue && empty($testValue1) || empty($testValue2)) {
							$exactDuplicateContacts = false;
							break;
						}
						if (strcasecmp($testValue1,$testValue2) != 0) {
							$exactDuplicateContacts = false;
							break;
						}
					}
				}
				foreach ($onlyOneTables as $tableName) {
					if ($exactDuplicateContacts && in_array($tableName,$exactAllBothTables)) {
						continue;
					}
					$query = "select contact_id from contacts where contact_id = ? and exists (select contact_id from " . $tableName .
						" where contact_id = contacts.contact_id)";
					$resultSet = executeQuery($query, $firstContactId);
					if ($resultSet['row_count'] > 0) {
						$resultSet = executeQuery($query, $secondContactId);
						if ($resultSet['row_count'] > 0) {
							$countsArray['skipped']++;
							if (!array_key_exists("both_in_" . $tableName,$countsArray)) {
								$countsArray['both_in_' . $tableName] = 0;
							}
							$countsArray['both_in_' . $tableName]++;
							continue 2;
						}
					}
				}
				if ($secondContactRow['date_created'] < $firstContactRow['date_created']) {
					$keepContactId = $secondContactId;
					$keepContactRow = $secondContactRow;
					$mergedContactId = $firstContactId;
					$mergedContactRow = $firstContactRow;
				} else {
					$keepContactId = $firstContactId;
					$keepContactRow = $firstContactRow;
					$mergedContactId = $secondContactId;
					$mergedContactRow = $secondContactRow;
				}
				$unableToMerge = false;
				if (function_exists("customAutoMergeDuplicateCriteria")) {
					if (!customAutoMergeDuplicateCriteria($keepContactRow,$mergedContactRow)) {
						$unableToMerge = true;
					}
				} else {
					foreach ($contactDataFields as $contactFieldName) {
						if (!empty($keepContactRow[$contactFieldName]) && !empty($mergedContactRow[$contactFieldName]) && strcasecmp($mergedContactRow[$contactFieldName],$keepContactRow[$contactFieldName]) != 0) {
							$unableToMerge = true;
							break;
						}
					}
				}
				if ($unableToMerge) {
					$countsArray['skipped']++;
					$countsArray['contact_differences']++;
					continue;
				}
				$resultSet = executeQuery("select * from contact_identifiers where contact_id = ?", $keepContactId);
				while ($row = getNextRow($resultSet)) {
					$identifierValue = getFieldFromId("identifier_value", "contact_identifiers", "contact_id", $mergedContactId, "contact_identifier_type_id = ?", $row['contact_identifier_type_id']);
					if (!empty($identifierValue) && $identifierValue != $row['identifier_value']) {
						$unableToMerge = true;
						break;
					}
				}
				if ($unableToMerge) {
					$countsArray['skipped']++;
					$countsArray['differing_contact_identifiers']++;
					continue;
				}
				$countsArray['merged']++;

				if ($this->mergeContacts($keepContactRow, $mergedContactRow)) {
					$mergedContactIds[$mergedContactId] = $mergedContactId;
					$countsArray['success']++;
					$successCount++;
				} else {
					$countsArray['error']++;
					$errorCount++;
				}
				if ($countsArray['merged'] >= 10000) {
					break;
				}
			}
			$this->addResult("Results for client " . $clientRow['client_code'] . ":");
			foreach ($countsArray as $description => $count) {
				$this->addResult(str_replace("_"," ",$description) . ": " . $count);
			}
		}
	}

	function mergeContacts($keepContactRow, $mergedContactRow) {
		$keepContactId = $keepContactRow['contact_id'];
		$mergedContactId = $mergedContactRow['contact_id'];
		if (empty($keepContactId) || empty($mergedContactId)) {
			return false;
		}

		$mergeChangeLog = array();

		$contactRows = array();
		$contactRows[$keepContactId] = $keepContactRow;
		$contactRows[$mergedContactId] = $mergedContactRow;

		$GLOBALS['gPrimaryDatabase']->startTransaction();

# update accounts with merchant_identifier and merchant_account. This doesn't need to be reversed.

		$resultSet = executeQuery("select * from accounts where account_token is not null and merchant_identifier is null and contact_id in (?,?)", $mergedContactId, $keepContactId);
		while ($row = getNextRow($resultSet)) {
			$merchantIdentifier = "";
			$merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
			$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $row['contact_id'], "merchant_account_id = ?", $merchantAccountId);
			if (!empty($merchantIdentifier)) {
				executeQuery("update accounts set merchant_identifier = ?,merchant_account_id = ? where account_id = ?", $merchantIdentifier, $merchantAccountId, $row['account_id']);
			}
		}
		$accountIds = array();
		$resultSet = executeQuery("select merchant_account_id from merchant_profiles where contact_id = ?", $keepContactId);
		while ($row = getNextRow($resultSet)) {
			$accountIds[] = $row['merchant_account_id'];
		}

# By deleting duplicate merchant profiles, we are abandoning the customer record in the merchant gateway for the merged Contact ID, since there is already one for the Keep Contact ID

		if (!empty($accountIds)) {
			$resultSet = executeQuery("select * from merchant_profiles where contact_id = ? and merchant_account_id in (" . implode(",", $accountIds) . ")", $mergedContactId);
			while ($row = getNextRow($resultSet)) {
				$mergeChangeLog[] = array("action" => "delete", "table_name" => "merchant_profiles", "old_value" => $row);
				executeQuery("delete from merchant_profiles where merchant_profile_id = ?", $row['merchant_profile_id']);
			}
		}

		$contactNotes = $contactRows[$keepContactId]['notes'];
		if (!empty($contactRows[$mergedContactId]['notes'])) {
			$contactNotes = $contactRows[$keepContactId]['notes'] . (empty($contactRows[$keepContactId]['notes']) ? "" : "\n") . $contactRows[$mergedContactId]['notes'];
		}
		$contactNotes .= "\n";

		$contactIdTableColumnId = "";
		$resultSet = executeQuery("select table_column_id from table_columns where table_id = (select table_id from tables where table_name = 'contacts' and " .
			"database_definition_id = (select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
			"(select column_definition_id from column_definitions where column_name = 'contact_id')", $GLOBALS['gPrimaryDatabase']->getName());
		if ($row = getNextRow($resultSet)) {
			$contactIdTableColumnId = $row['table_column_id'];
		}
		if (empty($contactIdTableColumnId)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}

# Merge change log records

		$resultSet = executeQuery("select * from change_log where primary_identifier = ? and table_name = 'contacts'", $mergedContactId);
		while ($row = getNextRow($resultSet)) {
			$mergeChangeLog[] = array("action" => "update", "table_name" => "change_log", "old_value" => $row, "new_value" => array("primary_identifier" => $keepContactId));
		}
		executeQuery("update change_log set primary_identifier = ? where primary_identifier = ? and table_name = 'contacts'", $keepContactId, $mergedContactId);
		$changeLogTables = $GLOBALS['gPrimaryDatabase']->getChangeLogForeignKeys("contacts");
		foreach ($changeLogTables as $thisChangeLogTable) {
			$resultSet = executeQuery("select * from change_log where foreign_key_identifier = ? and table_name = ?", $mergedContactId, $thisChangeLogTable['table_name']);
			while ($row = getNextRow($resultSet)) {
				$mergeChangeLog[] = array("action" => "update", "table_name" => "change_log", "old_value" => $row, "new_value" => array("foreign_key_identifier" => $keepContactId));
			}
			executeQuery("update change_log set foreign_key_identifier = ? where foreign_key_identifier = ? and table_name = ?",
				$keepContactId, $mergedContactId, $thisChangeLogTable['table_name']);
		}

		$tableSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) table_name," .
			"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name " .
			"from table_columns where table_column_id in (select table_column_id from foreign_keys where referenced_table_column_id = ?) order by table_name", $contactIdTableColumnId);
		while ($tableRow = getNextRow($tableSet)) {
			$tableName = $tableRow['table_name'];
			$columnName = $tableRow['column_name'];
			switch ($tableName) {
				case "blog_subscriptions":
					$count = 0;
					$resultSet = executeQuery("select count(*) from blog_subscriptions where contact_id = ?", $mergedContactId);
					if ($row = getNextRow($resultSet)) {
						$count = $row['count(*)'];
					}
					if ($count == 0) {
						break;
					}
					$count = 0;
					$resultSet = executeQuery("select count(*) from blog_subscriptions where contact_id = ?", $keepContactId);
					if ($row = getNextRow($resultSet)) {
						$count = $row['count(*)'];
					}
					if ($count == 0) {
						$resultSet = executeQuery("select * from blog_subscriptions where contact_id = ?", $mergedContactId);
						while ($row = getNextRow($resultSet)) {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "blog_subscriptions", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
						}
						$resultSet = executeQuery("update blog_subscriptions set contact_id = ? where contact_id = ?", $keepContactId, $mergedContactId);
						break;
					}
					$postCategoryIdArray = array("");
					$resultSet = executeQuery("select distinct post_category_id from blog_subscriptions where post_category_id is not null and contact_id in (?,?)",
						$keepContactId, $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$postCategoryIdArray[] = $row['post_category_id'];
					}
					$saveRow = array();
					foreach ($postCategoryIdArray as $postCategoryId) {
						$resultSet = executeQuery("select * from blog_subscriptions where post_category_id <=> ? and contact_id in (?,?) order by contact_id desc", $postCategoryId, $keepContactId, $mergedContactId);
						if ($resultSet['row_count'] > 0) {
							$startDate = "";
							$blogSubscriptionId = "";
							while ($row = getNextRow($resultSet)) {
								if (empty($startDate) || $row['start_date'] < $startDate) {
									$saveRow = $row;
									$blogSubscriptionId = $row['blog_subscription_id'];
									$startDate = $row['start_date'];
								}
							}
							if ($saveRow['contact_id'] != $keepContactId) {
								$mergeChangeLog[] = array("action" => "update", "table_name" => "blog_subscriptions", "old_value" => $saveRow, "new_value" => array("contact_id" => $keepContactId));
								$resultSet = executeQuery("update blog_subscriptions set contact_id = ? where blog_subscription_id = ?", $keepContactId, $blogSubscriptionId);
								if (!empty($resultSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									return false;
								}
							}
							$postIdArray = array();
							$resultSet = executeQuery("select post_id from blog_subscription_emails where blog_subscription_id = ?", $blogSubscriptionId);
							while ($row = getNextRow($resultSet)) {
								$postIdArray[] = $row['post_id'];
							}
							$resultSet = executeQuery("select * from blog_subscription_emails where blog_subscription_id <> ? and " .
								"blog_subscription_id in (select blog_subscription_id from blog_subscriptions where post_category_id <=> ? and contact_id in (?,?))",
								$blogSubscriptionId, $keepContactId, $mergedContactId);
							while ($row = getNextRow($resultSet)) {
								if (!in_array($row['post_id'], $postIdArray)) {
									$mergeChangeLog[] = array("action" => "update", "table_name" => "blog_subscription_emails", "old_value" => $row, "new_value" => array("blog_subscription_id" => $blogSubscriptionId));
									$updateSet = executeQuery("update blog_subscription_emails set blog_subscription_id = ? where blog_subscription_email_id = ?",
										$blogSubscriptionId, $row['blog_subscription_email_id']);
									if (!empty($updateSet['sql_error'])) {
										$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
										return false;
									}
									$postIdArray[] = $row['post_id'];
								}
							}
							$resultSet = executeQuery("select * from blog_subscription_emails where blog_subscription_id <> ? and " .
								"blog_subscription_id in (select blog_subscription_id from blog_subscriptions where post_category_id <=> ? and contact_id in (?,?))",
								$blogSubscriptionId, $keepContactId, $mergedContactId);
							while ($row = getNextRow($resultSet)) {
								$mergeChangeLog[] = array("action" => "delete", "table_name" => "blog_subscription_emails", "old_value" => $row);
							}
							$resultSet = executeQuery("delete from blog_subscription_emails where blog_subscription_id <> ? and " .
								"blog_subscription_id in (select blog_subscription_id from blog_subscriptions where post_category_id <=> ? and contact_id in (?,?))",
								$blogSubscriptionId, $keepContactId, $mergedContactId);
							if (!empty($resultSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
							$resultSet = executeQuery("select * from blog_subscriptions where blog_subscription_id <> ? and post_category_id <=> ? and contact_id in (?,?)",
								$blogSubscriptionId, $keepContactId, $mergedContactId);
							while ($row = getNextRow($resultSet)) {
								$mergeChangeLog[] = array("action" => "delete", "table_name" => "blog_subscription_emails", "old_value" => $row);
							}
							$resultSet = executeQuery("delete from blog_subscriptions where blog_subscription_id <> ? and " .
								"post_category_id <=> ? and contact_id in (?,?)", $blogSubscriptionId, $keepContactId, $mergedContactId);
							if (!empty($resultSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
							break;
						}
					}
					break;
				case "contact_categories":
					$resultSet = executeQuery("select * from contact_categories where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "contact_id", $keepContactId, "category_id = ?", $row['category_id']);
						if (empty($contactCategoryId)) {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_categories", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
							$updateSet = executeQuery("update contact_categories set contact_id = ? where contact_category_id = ?",
								$keepContactId, $row['contact_category_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						} else {
							$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_categories", "old_value" => $row);
							$updateSet = executeQuery("delete from contact_categories where contact_category_id = ?", $row['contact_category_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						}
					}
					break;
				case "help_desk_entry_votes":
					$resultSet = executeQuery("select * from help_desk_entry_votes where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$helpDeskEntryVoteId = getFieldFromId("help_desk_entry_vote_id", "help_desk_entry_votes", "contact_id", $keepContactId, "help_desk_entry_id = ?", $row['help_desk_entry_id']);
						if (empty($helpDeskEntryVoteId)) {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "help_desk_entry_vote_id", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
							$updateSet = executeQuery("update help_desk_entry_votes set contact_id = ? where help_desk_entry_vote_id = ?",
								$keepContactId, $row['help_desk_entry_vote_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						} else {
							$mergeChangeLog[] = array("action" => "delete", "table_name" => "help_desk_entry_votes", "old_value" => $row);
							$updateSet = executeQuery("delete from help_desk_entry_votes where help_desk_entry_vote_id = ?", $row['help_desk_entry_vote_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						}
					}
					break;
				case "contact_mailing_lists":
					$resultSet = executeQuery("select * from contact_mailing_lists where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$keepSet = executeQuery("select * from contact_mailing_lists where contact_id = ? and mailing_list_id = ?",
							$keepContactId, $row['mailing_list_id']);
						if ($keepRow = getNextRow($keepSet)) {
							$setStatement = "";
							$setParameters = array();
							if ((!empty($row['date_opted_in']) && !empty($keepRow['date_opted_in']) && $row['date_opted_in'] < $keepRow['date_opted_in']) ||
								(empty($keepRow['date_opted_in']) && !empty($row['date_opted_in']))) {
								$setStatement .= (empty($setStatement) ? "" : ",") . "date_opted_in = ?, ip_address = ?";
								$setParameters[] = $row['date_opted_in'];
								$setParameters[] = $row['ip_address'];
							}
							if (!empty($row['date_opted_out']) && !empty($keepRow['date_opted_out']) && $row['date_opted_out'] > $keepRow['date_opted_out']) {
								$setStatement .= (empty($setStatement) ? "" : ",") . "date_opted_out = ?";
								$setParameters[] = $row['date_opted_out'];
							} else if (!empty($keepRow['date_opted_out']) && empty($row['date_opted_out'])) {
								$setStatement .= (empty($setStatement) ? "" : ",") . "date_opted_out = null";
							}
							if (!empty($row['date_opted_out']) && !empty($keepRow['date_opted_out']) && !empty($row['opt_out_reason']) && empty($keepRow['opt_out_reason'])) {
								$setStatement .= (empty($setStatement) ? "" : ",") . "opt_out_reason = ?";
								$setParameters[] = $row['opt_out_reason'];
							}
							if (!empty($setStatement)) {
								$setParameters[] = $keepRow['contact_mailing_list_id'];
								$updateSet = executeQuery("update contact_mailing_lists set " . $setStatement . " where contact_mailing_list_id = ?", $setParameters);
								if (!empty($updateSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									return false;
								}
								$newRow = getRowFromId("contact_mailing_lists", "contact_mailing_list_id", $keepRow['contact_mailing_list_id']);
								$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_mailing_lists", "old_value" => $keepRow, "new_value" => $newRow);
							}
						} else {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_mailing_lists", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
							$updateSet = executeQuery("update contact_mailing_lists set contact_id = ? where contact_mailing_list_id = ?",
								$keepContactId, $row['contact_mailing_list_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						}
					}
					$resultSet = executeQuery("select * from contact_mailing_lists where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_mailing_lists", "old_value" => $row);
					}
					executeQuery("delete from contact_mailing_lists where contact_id = ?", $mergedContactId);
					break;
				case "phone_numbers":
				case "contact_emails":
				case "potential_duplicates":
					break;
				case "query_results":
					$resultSet = executeQuery("select * from query_results where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$queryResultId = getFieldFromId("query_result_id", "query_results", "contact_id", $keepContactId, "query_definition_id = ?", $row['query_definition_id']);
						if (empty($queryResultId)) {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "query_results", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
							$updateSet = executeQuery("update query_results set contact_id = ? where query_result_id = ?", $keepContactId, $row['query_result_id']);
						} else {
							$mergeChangeLog[] = array("action" => "delete", "table_name" => "query_results", "old_value" => $row);
							$updateSet = executeQuery("delete from query_results where query_result_id = ?", $row['query_result_id']);
						}
					}
					break;
				case "contact_identifiers":
					$resultSet = executeQuery("select * from contact_identifiers where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$keepSet = executeQuery("select * from contact_identifiers where contact_id = ? and contact_identifier_type_id = ?",
							$keepContactId, $row['contact_identifier_type_id']);
						if ($keepRow = getNextRow($keepSet)) {
							$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_identifiers", "old_value" => $row);
							$updateSet = executeQuery("delete from contact_identifiers where contact_identifier_id = ?", $row['contact_identifier_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						} else {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_identifiers", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
							$updateSet = executeQuery("update contact_identifiers set contact_id = ? where contact_identifier_id = ?",
								$keepContactId, $row['contact_identifier_id']);
							if (!empty($updateSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								return false;
							}
						}
					}
					break;
				case "relationships":
					$resultSet = executeQuery("select * from relationships where contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$mergeChangeLog[] = array("action" => "update", "table_name" => "relationships", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
						$updateSet = executeQuery("update relationships set contact_id = ? where relationship_id = ?", $keepContactId, $row['relationship_id']);
						if (!empty($updateSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							return false;
						}
					}
					$resultSet = executeQuery("select * from relationships where related_contact_id = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$mergeChangeLog[] = array("action" => "update", "table_name" => "relationships", "old_value" => $row, "new_value" => array("related_contact_id" => $keepContactId));
						$updateSet = executeQuery("update relationships set related_contact_id = ? where relationship_id = ?", $keepContactId, $row['relationship_id']);
						if (!empty($updateSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							return false;
						}
					}
					$resultSet = executeQuery("select * from relationships where contact_id = related_contact_id");
					while ($row = getNextRow($resultSet)) {
						$mergeChangeLog[] = array("action" => "delete", "table_name" => "relationships", "old_value" => $row);
						$updateSet = executeQuery("delete from relationships where relationship_id = ?", $row['relationship_id']);
						if (!empty($updateSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							return false;
						}
					}
					break;
				case "companies":
					$keepCompanyId = getFieldFromId("company_id", "companies", "contact_id", $keepContactId);
					$mergedCompanyId = getFieldFromId("company_id", "companies", "contact_id", $mergedContactId);

# If both contacts are companies, save the first customer and remove the second.

					if (!empty($keepCompanyId) && !empty($mergedCompanyId)) {
						$resultSet = executeQuery("select * from users where company_id = ?", $mergedCompanyId);
						while ($row = getNextRow($resultSet)) {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "users", "old_value" => $row, "new_value" => array("company_id" => $keepCompanyId));
						}
						$resultSet = executeQuery("update users set company_id = ? where company_id = ?", $keepCompanyId, $mergedCompanyId);
						if (!empty($resultSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							return false;
						}
						$companyRow = getRowFromId("companies", "company_id", $mergedCompanyId);
						$mergeChangeLog[] = array("action" => "delete", "table_name" => "companies", "old_value" => $companyRow);
						$updateSet = executeQuery("delete from companies where company_id = ?", $mergedCompanyId);
						if (!empty($updateSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							return false;
						}
					}
				case "shopping_carts":
					$resultSet = executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_carts where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ?))", $mergedContactId);
					$resultSet = executeQuery("delete from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ?)", $mergedContactId);
					$resultSet = executeQuery("delete from product_map_overrides where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ?)", $mergedContactId);
					$resultSet = executeQuery("delete from shopping_carts where contact_id = ?", $mergedContactId);
					break;
				case "wish_lists":
					$resultSet = executeQuery("delete from wish_list_items where wish_list_id in (select wish_list_id from wish_lists where contact_id = ?)", $mergedContactId);
					$resultSet = executeQuery("delete from wish_lists where contact_id = ?", $mergedContactId);
					break;
				default:
					$resultSet = executeQuery("select * from " . $tableName . " where " . $columnName . " = ?", $mergedContactId);
					while ($row = getNextRow($resultSet)) {
						$mergeChangeLog[] = array("action" => "update", "table_name" => $tableName, "old_value" => $row, "new_value" => array($columnName => $keepContactId));
					}
					$resultSet = executeQuery("update " . $tableName . " set " . $columnName . " = ? where " . $columnName . " = ?", $keepContactId, $mergedContactId);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return false;
					}
					break;
			}
		}
		$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')", $GLOBALS['gClientId']);
		while ($customFieldRow = getNextRow($customFieldSet)) {
			$resultSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?", $customFieldRow['custom_field_id'], $mergedContactId);
			while ($row = getNextRow($resultSet)) {
				$customField = $customFieldRow['description'];
				$keepSet = executeQuery("select * from custom_field_data where primary_identifier = ? and custom_field_id = ?",
					$keepContactId, $row['custom_field_id']);
				if ($keepRow = getNextRow($keepSet)) {
					if (!empty($keepRow['integer_data']) && $keepRow['integer_data'] != $row['integer_data']) {
						$contactNotes .= $customField . ": " . $row['integer_data'] . "\n";
					}
					if (!empty($keepRow['number_data']) && $keepRow['number_data'] != $row['number_data']) {
						$contactNotes .= $customField . ": " . $row['number_data'] . "\n";
					}
					if (!empty($keepRow['text_data']) && $keepRow['text_data'] != $row['text_data']) {
						$contactNotes .= $customField . ": " . $row['text_data'] . "\n";
					}
					if (!empty($keepRow['date_data']) && $keepRow['date_data'] != $row['date_data']) {
						$contactNotes .= $customField . ": " . $row['date_data'] . "\n";
					}
					$mergeChangeLog[] = array("action" => "delete", "table_name" => "custom_field_data", "old_value" => $row);
					$updateSet = executeQuery("delete from custom_field_data where custom_field_data_id = ?", $row['custom_field_data_id']);
					if (!empty($updateSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return false;
					}
				} else {
					$mergeChangeLog[] = array("action" => "update", "table_name" => "custom_field_data", "old_value" => $row, "new_value" => array("primary_identifier" => $keepContactId));
					$updateSet = executeQuery("update custom_field_data set primary_identifier = ? where custom_field_data_id = ?", $keepContactId, $row['custom_field_data_id']);
					if (!empty($updateSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return false;
					}
				}
			}
		}

# Merge Phone Numbers

		$phoneNumbers = array();
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $keepContactId);
		while ($row = getNextRow($resultSet)) {
			$phoneNumbers[] = $row['phone_number'];
		}
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $mergedContactId);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['phone_number'],$phoneNumbers)) {
				$mergeChangeLog[] = array("action" => "delete", "table_name" => "phone_numbers", "old_value" => $row);
				executeQuery("delete from phone_numbers where phone_number_id = ?", $row['phone_number_id']);
			} else {
				$mergeChangeLog[] = array("action" => "update", "table_name" => "phone_numbers", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
				executeQuery("update phone_numbers set contact_id = ? where phone_number_id = ?",$keepContactId,$row['phone_number_id']);
				$phoneNumbers[] = $row['phone_number'];
			}
		}

# Merge Email Addresses

		$emailAddresses = array();
		$resultSet = executeQuery("select * from contact_emails where contact_id = ?", $keepContactId);
		while ($row = getNextRow($resultSet)) {
			$emailAddresses[] = $row['email_address'];
		}
		$resultSet = executeQuery("select * from contact_emails where contact_id = ?", $mergedContactId);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['email_address'],$emailAddresses)) {
				$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_emails", "old_value" => $row);
				executeQuery("delete from contact_emails where contact_email_id = ?", $row['contact_email_id']);
			} else {
				$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_emails", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
				executeQuery("update contact_emails set contact_id = ? where contact_email_id = ?",$keepContactId,$row['contact_email_id']);
				$emailAddresses[] = $row['email_address'];
			}
		}

		$contactTable = new DataTable("contacts");
		$contactTable->setSaveOnlyPresent(true);
		$parameterArray = $keepContactRow;
		foreach ($mergedContactRow as $fieldName => $fieldData) {
			if (!empty($fieldData) && empty($parameterArray[$fieldName])) {
				$parameterArray[$fieldName] = $fieldData;
			}
		}
		if (empty($contactRows[$mergedContactId]['deleted']) || empty($contactRows[$keepContactId]['deleted'])) {
			$parameterArray['deleted'] = 0;
		}
		if ($contactRows[$mergedContactId]['date_created'] < $contactRows[$keepContactId]['date_created']) {
			$parameterArray['date_created'] = $contactRows[$mergedContactId]['date_created'];
		}

		$parameterArray['notes'] = $contactNotes;

		if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $keepContactId, "no_change_log" => true))) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}
		$newContactRow = getRowFromId("contacts", "contact_id", $keepContactId);
		$mergeChangeLog[] = array("action" => "update", "table_name" => "contacts", "old_value" => $contactRows[$keepContactId], "new_value" => $newContactRow);
		$resultSet = executeQuery("delete from potential_duplicates where contact_id = ? or duplicate_contact_id = ?", $mergedContactId, $mergedContactId);

		$fullName = getDisplayName($mergedContactId);
		$mergeChangeLog[] = array("action" => "delete", "table_name" => "contacts", "old_value" => $contactRows[$mergedContactId]);
		$resultSet = executeQuery("delete from contacts where contact_id = ?", $mergedContactId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}
		$resultSet = executeQuery("insert into contact_redirect (client_id,contact_id,retired_contact_identifier) values (?,?,?)", $GLOBALS['gClientId'], $keepContactId, $mergedContactId);
		$mergeChangeLog[] = array("action" => "insert", "table_name" => "contact_redirect", "old_value" => array(), "new_value" => array("contact_redirect_id" => $resultSet['insert_id']));
		$resultSet = executeQuery("insert into change_log (client_id,user_id,table_name,column_name,primary_identifier," .
			"old_value,new_value,notes) values (?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'], 'contacts', 'contact_id',
			$keepContactId, 'Automatically Merged With', $mergedContactId, (empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
		$mergeChangeLog[] = array("action" => "insert", "table_name" => "change_log", "old_value" => array(), "new_value" => array("log_id" => $resultSet['insert_id']));

		$userId = getFieldFromId("user_id","users","full_client_access","1", "superuser_flag = 0");
		if (empty($userId)) {
			$userId = getFieldFromId("user_id", "users", "superuser_flag", "1");
		}
		$resultSet = executeQuery("insert into merge_log (contact_id,retired_contact_identifier,full_name,log_time,user_id) values (?,?,?,now(),?)", $keepContactId, $mergedContactId, $fullName, $userId);
		$mergeLogId = $resultSet['insert_id'];
		foreach ($mergeChangeLog as $mergeInfo) {
			executeQuery("insert into merge_log_details (merge_log_id,merge_action,table_name,old_value,new_value) values (?,?,?,?,?)", $mergeLogId, $mergeInfo['action'], $mergeInfo['table_name'],
				jsonEncode(empty($mergeInfo['old_value']) ? array() : $mergeInfo['old_value']), (empty($mergeInfo['new_value']) ? "" : jsonEncode($mergeInfo['new_value'])));
		}
		updateUserSubscriptions($keepContactId);
		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		return true;
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
