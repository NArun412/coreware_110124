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

$GLOBALS['gPageCode'] = "DUPLICATEPROCESSING";
require_once "shared/startup.inc";

class DuplicateProcessingPage extends Page {

	function massageUrlParameters() {
		if ($_GET['selected'] == "true") {
			$resultSet = executeQuery("delete from potential_duplicates where user_id = ?", $GLOBALS['gUserId']);
			$resultSet = executeQuery("select * from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gAllPageCodes'][($_GET['return'] == "contacts" ? "CONTACTMAINT" : "USERMAINT")]);
			if ($resultSet['row_count'] == 2) {
				if ($row = getNextRow($resultSet)) {
					if ($_GET['return'] == "contacts") {
						$firstContactId = getFieldFromId("contact_id", "contacts", "contact_id", $row['primary_identifier'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "contact_id not in (select contact_id from users where superuser_flag = 1)"));
					} else {
						$firstContactId = getFieldFromId("contact_id", "users", "user_id", $row['primary_identifier'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0"));
					}
				}
				if ($row = getNextRow($resultSet)) {
					if ($_GET['return'] == "contacts") {
						$secondContactId = getFieldFromId("contact_id", "contacts", "contact_id", $row['primary_identifier'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "contact_id not in (select contact_id from users where superuser_flag = 1)"));
					} else {
						$secondContactId = getFieldFromId("contact_id", "users", "user_id", $row['primary_identifier'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0"));
					}
				}
				if (!empty($firstContactId) && !empty($secondContactId)) {
					$insertSet = executeQuery("insert into potential_duplicates (client_id,contact_id,duplicate_contact_id,user_id) values " .
						"(?,?,?,?)", $GLOBALS['gClientId'], $firstContactId, $secondContactId, $GLOBALS['gUserId']);
					$_GET['url_page'] = "show";
					$_GET['primary_id'] = $insertSet['insert_id'];
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gAllPageCodes'][($_GET['return'] == "contacts" ? "CONTACTMAINT" : "USERMAINT")]);
				}
			}
		}
	}

	function setup() {
		if (empty($GLOBALS['gUserRow']['superuser_flag'])) {
			executeQuery("delete from potential_duplicates where user_id = ? and " .
				"(contact_id in (select contact_id from users where superuser_flag = 1) or " .
				"duplicate_contact_id in (select contact_id from users where superuser_flag = 1) or contact_id in (select contact_id from clients))", $GLOBALS['gUserId']);
		}
		$this->iDataSource->addColumnControl("contact_1", "select_value", "select concat_ws(', ',first_name,last_name,business_name,city,postal_code) from contacts where contact_id = potential_duplicates.contact_id");
		$this->iDataSource->addColumnControl("contact_1", "form_label", "First Contact");
		$this->iDataSource->addColumnControl("contact_2", "select_value", "select concat_ws(', ',first_name,last_name,business_name,city,postal_code) from contacts where contact_id = potential_duplicates.duplicate_contact_id");
		$this->iDataSource->addColumnControl("contact_2", "form_label", "Second Contact");
		$this->iDataSource->addColumnControl("contact_id", "foreign_key", false);
		$this->iDataSource->addColumnControl("duplicate_contact_id", "foreign_key", false);
		$this->iDataSource->addColumnControl("permanent_skip", "data_type", "hidden");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "save"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("contact_id", "contact_1", "duplicate_contact_id", "contact_2"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("contact_id", "duplicate_contact_id", "contact_1", "contact_2"));
			$this->iTemplateObject->getTableEditorObject()->setListDataLength(60);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("merge" => array("label" => getLanguageText("Merge"),
				"disabled" => false), "ignore" => array("label" => getLanguageText("Permanently Skip"), "disabled" => false)));
		}
	}

	function beforeDeleteRecord($primaryId) {
		if (!empty($_POST['permanent_skip'])) {
			$potentialDuplicateRow = getRowFromId("potential_duplicates", "potential_duplicate_id", $primaryId);
			if (!empty($potentialDuplicateRow)) {
				$resultSet = executeQuery("select * from duplicate_exclusions where client_id = ? and ((contact_id = ? and duplicate_contact_id = ?) or " .
					"(duplicate_contact_id = ? and contact_id = ?))", $GLOBALS['gClientId'], $potentialDuplicateRow['contact_id'], $potentialDuplicateRow['duplicate_contact_id'],
					$potentialDuplicateRow['contact_id'], $potentialDuplicateRow['duplicate_contact_id']);
				if ($resultSet['row_count'] == 0) {
					executeQuery("insert into duplicate_exclusions (client_id,contact_id,duplicate_contact_id) values (?,?,?)", $GLOBALS['gClientId'], $potentialDuplicateRow['contact_id'], $potentialDuplicateRow['duplicate_contact_id']);
				}
			}
		}
		return true;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_contacts":
				unset($_POST['version']);

				$mergeChangeLog = array();

				$primaryId = getFieldFromId("potential_duplicate_id", "potential_duplicates", "potential_duplicate_id", $_POST['primary_id'], "user_id = ?", $GLOBALS['gUserId']);
				if (empty($primaryId)) {
					$returnArray['error_message'] = "Invalid Duplicate Record";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "potential_duplicates", "potential_duplicate_id", $primaryId);
				$duplicateContactId = getFieldFromId("duplicate_contact_id", "potential_duplicates", "potential_duplicate_id", $primaryId);
				$contactRows = array();
				$resultSet = executeQuery("select * from contacts where contact_id = ?", $contactId);
				$contactRows[$contactId] = getNextRow($resultSet);
				$resultSet = executeQuery("select * from contacts where contact_id = ?", $duplicateContactId);
				$contactRows[$duplicateContactId] = getNextRow($resultSet);

				$resultSet = executeQuery("select * from change_log where table_name = 'contacts' and primary_identifier = ? order by time_changed desc", $contactId);
				if ($row = getNextRow($resultSet)) {
					$timeChanged1 = $row['time_changed'];
				} else {
					$timeChanged1 = $contactRows[$contactId]['date_created'];
				}
				$resultSet = executeQuery("select * from change_log where table_name = 'contacts' and primary_identifier = ? order by time_changed desc", $duplicateContactId);
				if ($row = getNextRow($resultSet)) {
					$timeChanged2 = $row['time_changed'];
				} else {
					$timeChanged2 = $contactRows[$duplicateContactId]['date_created'];
				}
				$latestContactId = ($timeChanged1 > $timeChanged2 ? 1 : 2);
				$keepContactId = min($contactId, $duplicateContactId);
				$mergedContactId = max($contactId, $duplicateContactId);

				$this->iDatabase->startTransaction();

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

				$userName1 = getFieldFromId("user_name", "users", "contact_id", $contactId);
				$userName2 = getFieldFromId("user_name", "users", "contact_id", $duplicateContactId);

				if (!empty($userName1) && !empty($userName2)) {
# Merge Users
					$userId1 = getFieldFromId("user_id", "users", "contact_id", $contactId);
					$userId2 = getFieldFromId("user_id", "users", "contact_id", $duplicateContactId);
					$userName = $_POST['user_name'];
					if ($userName != $userName1 && $userName != $userName2) {
						$returnArray['error_message'] = "Invalid User Name";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if ($userName == $userName1) {
						$keepUserId = $userId1;
						$mergedUserId = $userId2;
					} else {
						$keepUserId = $userId2;
						$mergedUserId = $userId1;
					}
					$userIdTableColumnId = "";
					$resultSet = executeQuery("select table_column_id from table_columns where table_id = (select table_id from tables where table_name = 'users' and " .
						"database_definition_id = (select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
						"(select column_definition_id from column_definitions where column_name = 'user_id')", $GLOBALS['gPrimaryDatabase']->getName());
					if ($row = getNextRow($resultSet)) {
						$userIdTableColumnId = $row['table_column_id'];
					}
					if (empty($userIdTableColumnId)) {
						$returnArray['error_message'] = "Can't locate user ID field";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}

					$resultSet = executeQuery("select * from change_log where primary_identifier = ? and table_name = 'users'", $mergedUserId);
					while ($row = getNextRow($resultSet)) {
						$mergeChangeLog[] = array("action" => "update", "table_name" => "change_log", "old_value" => $row, "new_value" => array("primary_identifier" => $keepUserId));
					}
					executeQuery("update change_log set primary_identifier = ? where primary_identifier = ? and table_name = 'users'", $keepUserId, $mergedUserId);
					$changeLogTables = $GLOBALS['gPrimaryDatabase']->getChangeLogForeignKeys("users");
					foreach ($changeLogTables as $thisChangeLogTable) {
						$resultSet = executeQuery("select * from change_log where foreign_key_identifier = ? and table_name = ?", $mergedUserId, $thisChangeLogTable['table_name']);
						while ($row = getNextRow($resultSet)) {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "change_log", "old_value" => $row, "new_value" => array("foreign_key_identifier" => $keepUserId));
						}
						executeQuery("update change_log set foreign_key_identifier = ? where foreign_key_identifier = ? and table_name = ?",
							$keepUserId, $mergedUserId, $thisChangeLogTable['table_name']);
					}

					$tableSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) table_name," .
						"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name " .
						"from table_columns where table_column_id in (select table_column_id from foreign_keys where referenced_table_column_id = ?) order by table_name", $userIdTableColumnId);
					while ($tableRow = getNextRow($tableSet)) {
						$tableName = $tableRow['table_name'];
						$columnName = $tableRow['column_name'];
						$primaryKey = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_id", $tableRow['table_id'], "primary_table_key = 1"));
						switch ($tableName) {
							case "loyalty_program_points":
								$resultSet = executeQuery("select * from loyalty_program_points where user_id = ?", $mergedUserId);
								while ($row = getNextRow($resultSet)) {
									$loyaltyProgramPointRow = getRowFromId("loyalty_program_points", "user_id", $keepUserId, "loyalty_program_id = ?", $row['loyalty_program_id']);
									if (empty($loyaltyProgramPointRow['loyalty_program_point_id'])) {
										$mergeChangeLog[] = array("action" => "update", "table_name" => "loyalty_program_points", "old_value" => $row, "new_value" => array("user_id" => $keepUserId));
										executeQuery("update loyalty_program_points set user_id = ? where loyalty_program_point_id = ?", $keepUserId, $row['loyalty_program_point_id']);
									} else {
										$subSet = executeQuery("select * from loyalty_program_point_log where loyalty_program_point_id = ?", $row['loyalty_program_point_id']);
										while ($subRow = getNextRow($subSet)) {
											$mergeChangeLog[] = array("action" => "update", "table_name" => "loyalty_program_point_log", "old_value" => $subRow, "new_value" => array("loyalty_program_point_id" => $loyaltyProgramPointRow['loyalty_program_point_id']));
											executeQuery("update loyalty_program_point_log set loyalty_program_point_id = ? where loyalty_program_point_log_id = ?", $loyaltyProgramPointRow['loyalty_program_point_id'], $subRow['loyalty_program_point_log_id']);
										}

										$pointValue = $loyaltyProgramPointRow['point_value'] + $row['point_value'];
										$mergeChangeLog[] = array("action" => "update", "table_name" => "loyalty_program_points", "old_value" => $loyaltyProgramPointRow, "new_value" => array("point_value" => $pointValue));
										executeQuery("update loyalty_program_points set point_value = ? where loyalty_program_point_id = ?", $pointValue, $loyaltyProgramPointRow['loyalty_program_point_id']);
										$mergeChangeLog[] = array("action" => "delete", "table_name" => "loyalty_program_points", "old_value" => $row);
										executeQuery("delete from loyalty_program_points where loyalty_program_point_id = ?", $row['loyalty_program_point_id']);
									}
								}
								break;
							case "user_access":
								$resultSet = executeQuery("select * from user_access where user_id = ?", $mergedUserId);
								while ($row = getNextRow($resultSet)) {
									$userAccessInfo = getMultipleFieldsFromId(array("user_access_id", "permission_level"), "user_access", "user_id", $keepUserId, "page_id = ?", $row['page_id']);
									if (empty($userAccessInfo)) {
										$mergeChangeLog[] = array("action" => "update", "table_name" => "user_access", "old_value" => $row, "new_value" => array("user_id" => $keepUserId));
										$updateSet = executeQuery("update user_access set user_id = ? where user_access_id = ?", $keepUserId, $row['user_access_id']);
										if (!empty($updateSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
									} else {
										if ($row['permission_level'] > $userAccessInfo['permission_level']) {
											$mergeChangeLog[] = array("action" => "update", "table_name" => "user_access", "old_value" => $userAccessInfo, "new_value" => array("permission_level" => $row['permission_level']));
											$updateSet = executeQuery("update user_access set permission_level = ? where user_access_id = ?", $row['permission_level'], $userAccessInfo['user_access_id']);
											if (!empty($updateSet['sql_error'])) {
												$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
												$this->iDatabase->rollbackTransaction();
												ajaxResponse($returnArray);
												break;
											}
										}
										$mergeChangeLog[] = array("action" => "delete", "table_name" => "user_access", "old_value" => $row);
										$updateSet = executeQuery("delete from user_access where user_access_id = ?", $row['user_access_id']);
										if (!empty($updateSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
									}
								}
								break;
							case "user_preferences":
								$resultSet = executeQuery("select * from " . $tableName . " where " . $columnName . " = ?", $mergedUserId);
								while ($row = getNextRow($resultSet)) {
									if (empty($thisTableUserField)) {
										$testId = getFieldFromId($primaryKey, $tableName, $columnName, $keepUserId);
									} else {
										$testId = getFieldFromId($primaryKey, $tableName, $columnName, $keepUserId, "preference_id = ? and preference_qualifier <=> ?", $row['preference_id'], $row['preference_qualifier']);
									}
									if (empty($testId)) {
										$mergeChangeLog[] = array("action" => "update", "table_name" => $tableName, "old_value" => $row, "new_value" => array($columnName => $keepUserId));
										$updateSet = executeQuery("update " . $tableName . " set " . $columnName . " = ? where " . $primaryKey . " = ?", $keepUserId, $row[$primaryKey]);
										if (!empty($updateSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
									} else {
										$mergeChangeLog[] = array("action" => "delete", "table_name" => $tableName, "old_value" => $row);
										$updateSet = executeQuery("delete from " . $tableName . " where " . $primaryKey . " = ?", $row[$primaryKey]);
										if (!empty($updateSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
									}
								}
								break;
							case "project_member_users":
							case "project_notifications":
							case "task_type_users":
							case "task_user_attendees":
							case "user_function_uses":
							case "user_group_members":
							case "work_flow_user_access":
							case "user_media":
							case "outreach_users":
								$tableUserFields = array();
								$tableUserFields['project_member_users'] = "project_id";
								$tableUserFields['project_notifications'] = "project_id";
								$tableUserFields['task_type_users'] = "task_type_id";
								$tableUserFields['user_function_uses'] = "user_function_id";
								$tableUserFields['user_group_members'] = "user_group_id";
								$tableUserFields['user_media'] = "media_id";
								$tableUserFields['wish_lists'] = "";
								$tableUserFields['work_flow_user_access'] = "work_flow_definition_id";
								$tableUserFields['outreach_users'] = "outreach_id";

								$thisTableUserField = $tableUserFields[$tableName];
								$resultSet = executeQuery("select * from " . $tableName . " where " . $columnName . " = ?", $mergedUserId);
								while ($row = getNextRow($resultSet)) {
									if (empty($thisTableUserField)) {
										$testId = getFieldFromId($primaryKey, $tableName, $columnName, $keepUserId);
									} else {
										$testId = getFieldFromId($primaryKey, $tableName, $columnName, $keepUserId, $thisTableUserField . " = ?", $row[$thisTableUserField]);
									}
									if (empty($testId)) {
										$mergeChangeLog[] = array("action" => "update", "table_name" => $tableName, "old_value" => $row, "new_value" => array($columnName => $keepUserId));
										$updateSet = executeQuery("update " . $tableName . " set " . $columnName . " = ? where " . $primaryKey . " = ?", $keepUserId, $row[$primaryKey]);
										if (!empty($updateSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
									} else {
										$mergeChangeLog[] = array("action" => "delete", "table_name" => $tableName, "old_value" => $row);
										$updateSet = executeQuery("delete from " . $tableName . " where " . $primaryKey . " = ?", $row[$primaryKey]);
										if (!empty($updateSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
									}
								}
								break;
							default:
								$resultSet = executeQuery("select * from " . $tableName . " where " . $columnName . " = ?", $mergedUserId);
								while ($row = getNextRow($resultSet)) {
									$mergeChangeLog[] = array("action" => "update", "table_name" => $tableName, "old_value" => $row, "new_value" => array($columnName => $keepUserId));
								}
								$resultSet = executeQuery("update " . $tableName . " set " . $columnName . " = ? where " . $columnName . " = ?", $keepUserId, $mergedUserId);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								break;
						}
					}
					$oldUserRow = getRowFromId("users", "user_id", $mergedUserId);
					$mergeChangeLog[] = array("action" => "delete", "table_name" => "users", "old_value" => $oldUserRow);
					$resultSet = executeQuery("delete from users where user_id = ?", $mergedUserId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$contactIdTableColumnId = "";
				$resultSet = executeQuery("select table_column_id from table_columns where table_id = (select table_id from tables where table_name = 'contacts' and " .
					"database_definition_id = (select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
					"(select column_definition_id from column_definitions where column_name = 'contact_id')", $GLOBALS['gPrimaryDatabase']->getName());
				if ($row = getNextRow($resultSet)) {
					$contactIdTableColumnId = $row['table_column_id'];
				}
				if (empty($contactIdTableColumnId)) {
					$returnArray['error_message'] = "Can't locate contact ID field";
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
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
											$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
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
												$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
												$this->iDatabase->rollbackTransaction();
												ajaxResponse($returnArray);
												break;
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
										$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}
									$resultSet = executeQuery("select * from blog_subscriptions where blog_subscription_id <> ? and post_category_id <=> ? and contact_id in (?,?)",
										$blogSubscriptionId, $keepContactId, $mergedContactId);
									while ($row = getNextRow($resultSet)) {
										$mergeChangeLog[] = array("action" => "delete", "table_name" => "blog_subscription_emails", "old_value" => $row);
									}
									$resultSet = executeQuery("delete from blog_subscriptions where blog_subscription_id <> ? and " .
										"post_category_id <=> ? and contact_id in (?,?)", $blogSubscriptionId, $keepContactId, $mergedContactId);
									if (!empty($resultSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
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
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}
								} else {
									$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_categories", "old_value" => $row);
									$updateSet = executeQuery("delete from contact_categories where contact_category_id = ?", $row['contact_category_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
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
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}
								} else {
									$mergeChangeLog[] = array("action" => "delete", "table_name" => "help_desk_entry_votes", "old_value" => $row);
									$updateSet = executeQuery("delete from help_desk_entry_votes where help_desk_entry_vote_id = ?", $row['help_desk_entry_vote_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
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
											$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
										$newRow = getRowFromId("contact_mailing_lists", "contact_mailing_list_id", $keepRow['contact_mailing_list_id']);
										$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_mailing_lists", "old_value" => $keepRow, "new_value" => $newRow);
									}
								} else {
									$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_mailing_lists", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
									$updateSet = executeQuery("update contact_mailing_lists set contact_id = ? where contact_mailing_list_id = ?",
										$keepContactId, $row['contact_mailing_list_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
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
								$keepSet = executeQuery("select * from contact_identifiers where contact_id = ? and contact_identifier_type_id = ? and identifier_value = ?",
									$keepContactId, $row['contact_identifier_type_id'], $row['identifier_value']);
								if ($keepRow = getNextRow($keepSet)) {
									$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_identifiers", "old_value" => $row);
									$updateSet = executeQuery("delete from contact_identifiers where contact_identifier_id = ?", $row['contact_identifier_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
									}
								} else {
									$mergeChangeLog[] = array("action" => "update", "table_name" => "contact_identifiers", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
									$updateSet = executeQuery("update contact_identifiers set contact_id = ? where contact_identifier_id = ?",
										$keepContactId, $row['contact_identifier_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
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
									$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
							$resultSet = executeQuery("select * from relationships where related_contact_id = ?", $mergedContactId);
							while ($row = getNextRow($resultSet)) {
								$mergeChangeLog[] = array("action" => "update", "table_name" => "relationships", "old_value" => $row, "new_value" => array("related_contact_id" => $keepContactId));
								$updateSet = executeQuery("update relationships set related_contact_id = ? where relationship_id = ?", $keepContactId, $row['relationship_id']);
								if (!empty($updateSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
							$resultSet = executeQuery("select * from relationships where contact_id = related_contact_id");
							while ($row = getNextRow($resultSet)) {
								$mergeChangeLog[] = array("action" => "delete", "table_name" => "relationships", "old_value" => $row);
								$updateSet = executeQuery("delete from relationships where relationship_id = ?", $row['relationship_id']);
								if (!empty($updateSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
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
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$companyRow = getRowFromId("companies", "company_id", $mergedCompanyId);
								$mergeChangeLog[] = array("action" => "delete", "table_name" => "companies", "old_value" => $companyRow);
								$updateSet = executeQuery("delete from companies where company_id = ?", $mergedCompanyId);
								if (!empty($updateSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
						case "shopping_carts":
							$resultSet = executeQuery("select * from shopping_carts where contact_id = ?", $mergedContactId);
							while ($row = getNextRow($resultSet)) {
								$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "contact_id", $keepContactId, "shopping_cart_code <=> ?", $row['shopping_cart_code']);
								if (empty($shoppingCartId)) {
									$mergeChangeLog[] = array("action" => "update", "table_name" => "shopping_carts", "old_value" => $row, "new_value" => array("contact_id" => $keepContactId));
									executeQuery("update shopping_carts set contact_id = ? where shopping_cart_id = ?", $keepContactId, $row['shopping_cart_id']);
								} else {
									$subSet = executeQuery("select * from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?)", $row['shopping_cart_id']);
									while ($subRow = getNextRow($subSet)) {
										$mergeChangeLog[] = array("action" => "delete", "table_name" => "shopping_cart_item_addons", "old_value" => $subRow);
									}

									$subSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ?", $row['shopping_cart_id']);
									while ($subRow = getNextRow($subSet)) {
										$mergeChangeLog[] = array("action" => "delete", "table_name" => "shopping_cart_items", "old_value" => $subRow);
									}

									$mergeChangeLog[] = array("action" => "delete", "table_name" => "shopping_carts", "old_value" => $row);

									$updateSet = executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?)", $row['shopping_cart_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}

									$updateSet = executeQuery("delete from shopping_cart_items where shopping_cart_id = ?", $row['shopping_cart_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}

									$updateSet = executeQuery("delete from product_map_overrides where shopping_cart_id = ?", $row['shopping_cart_id']);
									if (!empty($updateSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}

									executeQuery("delete from shopping_carts where shopping_cart_id = ?", $row['shopping_cart_id']);
								}
							}
							break;
						default:
							$resultSet = executeQuery("select * from " . $tableName . " where " . $columnName . " = ?", $mergedContactId);
							while ($row = getNextRow($resultSet)) {
								$mergeChangeLog[] = array("action" => "update", "table_name" => $tableName, "old_value" => $row, "new_value" => array($columnName => $keepContactId));
							}
							$resultSet = executeQuery("update " . $tableName . " set " . $columnName . " = ? where " . $columnName . " = ?", $keepContactId, $mergedContactId);
							if (!empty($resultSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
							break;
					}
				}
				$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')",
					$GLOBALS['gClientId']);
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
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						} else {
							$mergeChangeLog[] = array("action" => "update", "table_name" => "custom_field_data", "old_value" => $row, "new_value" => array("primary_identifier" => $keepContactId));
							$updateSet = executeQuery("update custom_field_data set primary_identifier = ? where custom_field_data_id = ?", $keepContactId, $row['custom_field_data_id']);
							if (!empty($updateSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}
				}

# Merge Phone Numbers

				$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $keepContactId);
				while ($row = getNextRow($resultSet)) {
					$mergeChangeLog[] = array("action" => "delete", "table_name" => "phone_numbers", "old_value" => $row);
				}
				$resultSet = executeQuery("delete from phone_numbers where contact_id = ?", $keepContactId);
				$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $mergedContactId);
				while ($row = getNextRow($resultSet)) {
					$mergeChangeLog[] = array("action" => "delete", "table_name" => "phone_numbers", "old_value" => $row);
				}
				$resultSet = executeQuery("delete from phone_numbers where contact_id = ?", $mergedContactId);

				$phoneNumberControl = new DataColumn("phone_numbers");
				$phoneNumberControl->setControlValue("data_type", "custom");
				$phoneNumberControl->setControlValue("control_class", "EditableList");
				$phoneNumberControl->setControlValue("primary_table", "contacts");
				$phoneNumberControl->setControlValue("list_table", "phone_numbers");
				$customControl = new EditableList($phoneNumberControl, $this);
				$customControl->setPrimaryId($keepContactId);
				$nameValues = $_POST;
				unset($nameValues['primary_id']);
				if ($customControl->saveData($nameValues, array("no_change_log" => true)) !== true) {
					$returnArray['error_message'] = $customControl->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select phone_number_id from phone_numbers where contact_id = ?", $keepContactId);
				while ($row = getNextRow($resultSet)) {
					$mergeChangeLog[] = array("action" => "insert", "table_name" => "phone_numbers", "old_value" => array(), "new_value" => $row);
				}

# Merge Email Addresses

				$resultSet = executeQuery("select * from contact_emails where contact_id = ?", $keepContactId);
				while ($row = getNextRow($resultSet)) {
					$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_emails", "old_value" => $row);
				}
				$resultSet = executeQuery("delete from contact_emails where contact_id = ?", $keepContactId);
				$resultSet = executeQuery("select * from contact_emails where contact_id = ?", $mergedContactId);
				while ($row = getNextRow($resultSet)) {
					$mergeChangeLog[] = array("action" => "delete", "table_name" => "contact_emails", "old_value" => $row);
				}
				$resultSet = executeQuery("delete from contact_emails where contact_id = ?", $mergedContactId);

				$contactEmailControl = new DataColumn("contact_emails");
				$contactEmailControl->setControlValue("data_type", "custom");
				$contactEmailControl->setControlValue("control_class", "EditableList");
				$contactEmailControl->setControlValue("primary_table", "contacts");
				$contactEmailControl->setControlValue("list_table", "contact_emails");
				$customControl = new EditableList($contactEmailControl, $this);
				$customControl->setPrimaryId($keepContactId);
				$nameValues = $_POST;
				unset($nameValues['primary_id']);
				if ($customControl->saveData($nameValues, array("no_change_log" => true)) !== true) {
					$returnArray['error_message'] = $customControl->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select contact_email_id from contact_emails where contact_id = ?", $keepContactId);
				while ($row = getNextRow($resultSet)) {
					$mergeChangeLog[] = array("action" => "insert", "table_name" => "contact_emails", "old_value" => array(), "new_value" => $row);
				}

				$contactTable = new DataTable("contacts");
				$contactTable->setSaveOnlyPresent(true);
				$parameterArray = $_POST;
				$parameterArray['latitude'] = "";
				$parameterArray['longitude'] = "";
				$parameterArray['validation_status'] = 0;
				if (!empty($contactRows[$mergedContactId]['image_id']) && empty($contactRows[$keepContactId]['image_id'])) {
					$parameterArray['image_id'] = $contactRows[$mergedContactId]['image_id'];
				}
				if (empty($contactRows[$mergedContactId]['deleted']) || empty($contactRows[$keepContactId]['deleted'])) {
					$parameterArray['deleted'] = 0;
				}
				if (!empty($contactRows[$mergedContactId]['web_page']) && empty($contactRows[$mergedContactId]['web_page'])) {
					$parameterArray['web_page'] = $contactRows[$mergedContactId]['web_page'];
				}
				if (!empty($contactRows[$mergedContactId]['timezone_id']) && empty($contactRows[$mergedContactId]['timezone_id'])) {
					$parameterArray['timezone_id'] = $contactRows[$mergedContactId]['timezone_id'];
				}
				if (!empty($contactRows[$mergedContactId]['attention_line']) && empty($contactRows[$mergedContactId]['attention_line'])) {
					$parameterArray['attention_line'] = $contactRows[$mergedContactId]['attention_line'];
				}
				if ($contactRows[$mergedContactId]['date_created'] < $contactRows[$keepContactId]['date_created']) {
					$parameterArray['date_created'] = $contactRows[$mergedContactId]['date_created'];
				}

				$parameterArray['notes'] = $contactNotes;

				if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $keepContactId, "no_change_log" => true))) {
					$returnArray['error_message'] = $contactTable->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$newContactRow = getRowFromId("contacts", "contact_id", $keepContactId);
				$mergeChangeLog[] = array("action" => "update", "table_name" => "contacts", "old_value" => $contactRows[$keepContactId], "new_value" => $newContactRow);
				$resultSet = executeQuery("delete from potential_duplicates where potential_duplicate_id = ? or contact_id = ? or duplicate_contact_id = ?",
					$primaryId, $mergedContactId, $mergedContactId);

				if ($resultSet['affected_rows'] > 1) {
					$returnArray['next_primary_id'] = $this->getNextPrimaryId();
				}

				$fullName = getDisplayName($mergedContactId);
				$mergeChangeLog[] = array("action" => "delete", "table_name" => "contacts", "old_value" => $contactRows[$mergedContactId]);
				$resultSet = executeQuery("delete from contacts where contact_id = ?", $mergedContactId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("insert into contact_redirect (client_id,contact_id,retired_contact_identifier) values (?,?,?)",
					$GLOBALS['gClientId'], $keepContactId, $mergedContactId);
				$mergeChangeLog[] = array("action" => "insert", "table_name" => "contact_redirect", "old_value" => array(), "new_value" => array("contact_redirect_id" => $resultSet['insert_id']));
				$resultSet = executeQuery("insert into change_log (client_id,user_id,table_name,column_name,primary_identifier," .
					"old_value,new_value,notes) values (?,?,?,?,?,?,?,?)", array($GLOBALS['gClientId'], $GLOBALS['gUserId'], 'contacts', 'contact_id',
					$keepContactId, 'Merged With', $mergedContactId,(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id']))));
				$mergeChangeLog[] = array("action" => "insert", "table_name" => "change_log", "old_value" => array(), "new_value" => array("log_id" => $resultSet['insert_id']));

				$resultSet = executeQuery("insert into merge_log (contact_id,retired_contact_identifier,full_name,log_time,user_id) values (?,?,?,now(),?)", $keepContactId, $mergedContactId, $fullName, $GLOBALS['gUserId']);
				$mergeLogId = $resultSet['insert_id'];
				foreach ($mergeChangeLog as $mergeInfo) {
					executeQuery("insert into merge_log_details (merge_log_id,merge_action,table_name,old_value,new_value) values (?,?,?,?,?)", $mergeLogId, $mergeInfo['action'], $mergeInfo['table_name'],
						jsonEncode(empty($mergeInfo['old_value']) ? array() : $mergeInfo['old_value']), (empty($mergeInfo['new_value']) ? "" : jsonEncode($mergeInfo['new_value'])));
				}
				$GLOBALS['gChangeLogNotes'] = "Updating User Subscriptions from Duplicate Processing";
				updateUserSubscriptions($keepContactId);
				$GLOBALS['gChangeLogNotes'] = "";
				$this->iDatabase->commitTransaction();
				ajaxResponse($returnArray);
				break;
		}
	}

	function getNextPrimaryId() {
		$filterText = getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageRow']['page_code']);
		$searchColumn = getPreference("MAINTENANCE_FILTER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$this->iDataSource->setFilterText($filterText);
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$customWhereValue = getPreference("MAINTENANCE_CUSTOM_WHERE_VALUE", $GLOBALS['gPageRow']['page_code']);
			if (!empty($customWhereValue)) {
				$this->iDataSource->setFilterWhere($customWhereValue);
			}
		}
		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
		$sortOrderColumns = array($sortOrderColumn);
		$reverseSortOrders = array($reverseSortOrder ? "desc" : "asc");
		if (!empty($secondarySortOrderColumn)) {
			$sortOrderColumns[] = $secondarySortOrderColumn;
			$reverseSortOrders[] = (getPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']) ? "desc" : "asc");
		}
		$sortOrderColumns[] = $this->iDataSource->getPrimaryTable()->getPrimaryKey();
		$reverseSortOrders[] = "asc";
		$this->iDataSource->setSortOrder($sortOrderColumns, $reverseSortOrders);

		$columns = $this->iDataSource->getColumns();
		$allSearchColumns = array();
		foreach ($columns as $columnName => $thisColumn) {
			switch ($thisColumn->getControlValue('data_type')) {
				case "date":
				case "bigint":
				case "int":
				case "select":
				case "text":
				case "mediumtext":
				case "varchar":
				case "decimal":
					$allSearchColumns[] = $columnName;
					break;
				default;
					continue 2;
			}
		}

		if (empty($searchColumn) || !in_array($searchColumn, $allSearchColumns)) {
			$this->iDataSource->setSearchFields($allSearchColumns);
		} else {
			$this->iDataSource->setSearchFields($searchColumn);
		}
		$itemCount = 1;
		$startRow = 0;
		$dataList = $this->iDataSource->getDataList(array("row_count" => $itemCount, "start_row" => $startRow));
		if (count($dataList) > 0) {
			return $dataList[0]['potential_duplicate_id'];
		} else {
			return "";
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "duplicate_contact_id",
			"description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "duplicate_contact_id",
			"description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "business_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "duplicate_contact_id",
			"description" => "business_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "city"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "duplicate_contact_id",
			"description" => "city"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "email_address"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "duplicate_contact_id",
			"description" => "email_address"));
	}

	function mergeForm() {
		$contactTypeIds = array();
		$resultSet = executeQuery("select * from contact_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$contactTypeIds[] = $row;
		}
		?>
        <p>Click on a field to move it to the final data</p>
        <div id="merge_data_form">
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Contact ID</label></p></div>
                <div class="merge-data-cell"><p><input type="text" id="contact_id_1" size="10" class="field-text align-right" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="contact_id_2" size="10" class="field-text align-right" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="contact_id" size="10" class="field-text align-right" readonly="readonly"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Title</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="title" id="title_1" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="title" id="title_2" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="title" name="title" size="20" maxlength="20" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>First</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="first_name" id="first_name_1" size="25" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="first_name" id="first_name_2" size="25" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="first_name" name="first_name" size="25" maxlength="25" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Middle</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="middle_name" id="middle_name_1" size="15" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="middle_name" id="middle_name_2" size="15" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="middle_name" name="middle_name" size="15" maxlength="15" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Last</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="last_name" id="last_name_1" size="35" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="last_name" id="last_name_2" size="35" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="last_name" name="last_name" size="35" maxlength="35" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Suffix</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="suffix" id="suffix_1" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="suffix" id="suffix_2" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="suffix" name="suffix" size="20" maxlength="20" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Preferred First Name</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="preferred_first_name" id="preferred_first_name_1" size="25" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="preferred_first_name" id="preferred_first_name_2" size="25" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="preferred_first_name" name="preferred_first_name" size="25" maxlength="25" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Alternate Name</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="alternate_name" id="alternate_name_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="alternate_name" id="alternate_name_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="alternate_name" name="alternate_name" size="40" maxlength="60" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Business Name</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="business_name" id="business_name_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="business_name" id="business_name_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="business_name" name="business_name" size="40" maxlength="60" class="fmerge-field ield-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Job Title</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="job_title" id="job_title_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="job_title" id="job_title_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="job_title" name="job_title" size="40" maxlength="120" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Salutation</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="salutation" id="salutation_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="salutation" id="salutation_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="salutation" name="salutation" size="40" maxlength="60" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Address</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="address_1" id="address_1_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="address_1" id="address_1_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="address_1" name="address_1" size="40" maxlength="60" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label></label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="address_2" id="address_2_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="address_2" id="address_2_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="address_2" name="address_2" size="40" maxlength="60" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>City</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="city,#city_select" id="city_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="city,#city_select" id="city_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p id="_city_row"><input type="text" tabindex="10" id="city" name="city" size="40" maxlength="60" class="merge-field field-text"></p>
                    <p id="_city_select_row"><select tabindex="10" id="city_select" name="city_select"></select></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>State</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="state" id="state_1" size="30" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="state" id="state_2" size="30" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="state" name="state" size="30" maxlength="30" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Postal Code</label></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="postal_code" id="postal_code_1" size="10" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" data-field_name="postal_code" id="postal_code_2" size="10" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="postal_code" name="postal_code" size="10" maxlength="10" class="merge-field field-text"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Country</label></p></div>
                <div class="merge-data-cell">
                    <p><select class="field-text validate[required]" id="country_id_1" disabled="disabled">
							<?php
							foreach (getCountryArray() as $countryId => $countryName) {
								?>
                                <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
								<?php
							}
							?>
                        </select></p>
                </div>
                <div class="merge-data-cell">
                    <p><select class="field-text validate[required]" id="country_id_2" disabled="disabled">
							<?php
							foreach (getCountryArray() as $countryId => $countryName) {
								?>
                                <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
								<?php
							}
							?>
                        </select></p>
                </div>
                <div class="merge-data-cell">
                    <p><select tabindex="10" class="merge-field field-text validate[required]" id="country_id" name="country_id">
							<?php
							foreach (getCountryArray() as $countryId => $countryName) {
								?>
                                <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
								<?php
							}
							?>
                        </select></p>
                </div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Primary Email</label></p></div>
                <div class="merge-data-cell"><p><input type="text" id="email_address_1" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="email_address_2" size="40" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" tabindex="10" id="email_address" name="email_address" size="40" maxlength="60" class="merge-field field-text validate[custom[email]]"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Alternate Emails</label></p></div>
                <div class="merge-data-cell"><p id="contact_emails_1"></p></div>
                <div class="merge-data-cell"><p id="contact_emails_2"></p></div>
				<?php
				$contactEmailControl = new DataColumn("contact_emails");
				$contactEmailControl->setControlValue("data_type", "custom");
				$contactEmailControl->setControlValue("control_class", "EditableList");
				$contactEmailControl->setControlValue("primary_table", "contacts");
				$contactEmailControl->setControlValue("list_table", "contact_emails");
				$contactEmailControl->setControlValue("list_table_controls", "return array('description'=>array('inline-width'=>'130px'),'email_address'=>array('inline-width'=>'120px'))");
				?>
                <div class="merge-data-cell">
					<?= $contactEmailControl->getControl($this) ?>
                </div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Date Created</label></p></div>
                <div class="merge-data-cell"><p><input type="text" id="date_created_1" size="12" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="date_created_2" size="12" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="date_created" size="12" class="field-text" readonly="readonly"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Last Changed</label></p></div>
                <div class="merge-data-cell"><p><input type="text" id="last_changed_1" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="last_changed_2" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="last_changed" size="20" class="field-text" readonly="readonly"></p></div>
            </div>
            <div class="merge-data-row">
				<?php if (count($contactTypeIds) > 0) { ?>
                    <div class="merge-data-cell"><p><label>Contact Type</label></p></div>
                    <div class="merge-data-cell">
                        <p><select class="field-text" id="contact_type_id_1" disabled="disabled">
                                <option value="">[None]</option>
								<?php
								foreach ($contactTypeIds as $row) {
									?>
                                    <option value="<?= $row['contact_type_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select></p>
                    </div>
                    <div class="merge-data-cell">
                        <p><select class="field-text" id="contact_type_id_2" disabled="disabled">
                                <option value="">[None]</option>
								<?php
								foreach ($contactTypeIds as $row) {
									?>
                                    <option value="<?= $row['contact_type_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select></p>
                    </div>
                    <div class="merge-data-cell">
                        <p><select class="field-text" id="contact_type_id" name="contact_type_id">
                                <option value="">[None]</option>
								<?php
								foreach ($contactTypeIds as $row) {
									?>
                                    <option value="<?= $row['contact_type_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select></p>
                    </div>
				<?php } ?>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Username</label></p></div>
                <div class="merge-data-cell"><p><input type="text" id="user_name_1" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><input type="text" id="user_name_2" size="20" class="field-text" readonly="readonly"></p></div>
                <div class="merge-data-cell"><p><select tabindex="10" class="field-text" id="user_name" name="user_name"></select></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Phone Numbers</label></p></div>
                <div class="merge-data-cell"><p id="phone_numbers_1"></p></div>
                <div class="merge-data-cell"><p id="phone_numbers_2"></p></div>
				<?php
				$phoneNumberControl = new DataColumn("phone_numbers");
				$phoneNumberControl->setControlValue("data_type", "custom");
				$phoneNumberControl->setControlValue("control_class", "EditableList");
				$phoneNumberControl->setControlValue("primary_table", "contacts");
				$phoneNumberControl->setControlValue("list_table", "phone_numbers");
				$phoneNumberControl->setControlValue("list_table_controls", "return array('phone_number'=>array('inline-width'=>'130px'),'description'=>array('inline-width'=>'120px'))");
				?>
                <div class="merge-data-cell">
					<?= $phoneNumberControl->getControl($this) ?>
                </div>
            </div>

            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Other</label></p></div>
                <div class="merge-data-cell"><p id="other_information_1"></p></div>
                <div class="merge-data-cell"><p id="other_information_2"></p></div>
                <div class="merge-data-cell"></div>
            </div>

        </div>

		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['permanent_skip'] = array("data_value" => "");
		$contactId = $returnArray['contact_id']['data_value'];
		$duplicateContactId = $returnArray['duplicate_contact_id']['data_value'];
		$contactRows = array();
		$resultSet = executeQuery("select * from contacts where contact_id = ?", $contactId);
		$contactRows["1"] = getNextRow($resultSet);
		$resultSet = executeQuery("select * from contacts where contact_id = ?", $duplicateContactId);
		$contactRows["2"] = getNextRow($resultSet);
		$fieldsArray = array("contact_id" => array("ignore" => true), "title" => array(), "first_name" => array(), "middle_name" => array(), "last_name" => array(),
			"suffix" => array(), "preferred_first_name" => array(), "alternate_name" => array(), "business_name" => array("use_either" => true),
			"job_title" => array("use_either" => true), "salutation" => array("use_either" => true), "address_1" => array(), "address_2" => array(),
			"city" => array(), "state" => array(), "postal_code" => array(), "country_id" => array(), "email_address" => array("ignore" => true),
			"contact_type_id" => array("use_either" => true));
		foreach ($contactRows as $index => $contactRow) {
			foreach ($fieldsArray as $fieldName => $fieldInfo) {
				$returnArray[$fieldName . "_" . $index] = array("data_value" => $contactRow[$fieldName]);
			}
		}
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ? order by description", $contactId);
		$returnArray['phone_numbers_1'] = array("data_value" => "");
		while ($row = getNextRow($resultSet)) {
			$returnArray['phone_numbers_1']['data_value'] .= ($returnArray['phone_numbers_1']['data_value'] == "" ? "" : "<br>") . $row['phone_number'] . " " . $row['description'];
		}
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ? order by description", $duplicateContactId);
		$returnArray['phone_numbers_2'] = array("data_value" => "");
		while ($row = getNextRow($resultSet)) {
			$returnArray['phone_numbers_2']['data_value'] .= ($returnArray['phone_numbers_2']['data_value'] == "" ? "" : "<br>") . $row['phone_number'] . " " . $row['description'];
		}
		$phoneNumberControl = new DataColumn("phone_numbers");
		$phoneNumberControl->setControlValue("data_type", "custom");
		$phoneNumberControl->setControlValue("control_class", "EditableList");
		$phoneNumberControl->setControlValue("primary_table", "contacts");
		$phoneNumberControl->setControlValue("list_table", "phone_numbers");
		$customControl = new EditableList($phoneNumberControl, $this);
		$phoneNumbers1 = $customControl->getRecord($contactId);
		$phoneNumbers2 = $customControl->getRecord($duplicateContactId);
		$uniquePhoneNumbers = array();
		foreach ($phoneNumbers1['phone_numbers'] as $index => $phoneNumber) {
			$phoneNumbers1['phone_numbers'][$index]['phone_number_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
			if (!in_array($phoneNumber['phone_number']['data_value'], $uniquePhoneNumbers)) {
				$uniquePhoneNumbers[] = $phoneNumber['phone_number']['data_value'];
			}
		}
		foreach ($phoneNumbers2['phone_numbers'] as $phoneNumber) {
			$phoneNumbers2['phone_numbers'][$index]['phone_number_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
			if (!in_array($phoneNumber['phone_number']['data_value'], $uniquePhoneNumbers)) {
				$phoneNumber['phone_number_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
				$phoneNumbers1['phone_numbers'][] = $phoneNumber;
				$uniquePhoneNumbers[] = $phoneNumber['phone_number']['data_value'];
			}
		}
		$returnArray = array_merge($returnArray, $phoneNumbers1);
		$returnArray['contact_id'] = array("data_value" => min($contactId, $duplicateContactId));
		$resultSet = executeQuery("select * from change_log where table_name = 'contacts' and primary_identifier = ? order by time_changed desc", $contactId);
		if ($row = getNextRow($resultSet)) {
			$timeChanged1 = $row['time_changed'];
		} else {
			$timeChanged1 = $contactRows['1']['date_created'];
		}
		$resultSet = executeQuery("select * from change_log where table_name = 'contacts' and primary_identifier = ? order by time_changed desc", $duplicateContactId);
		if ($row = getNextRow($resultSet)) {
			$timeChanged2 = $row['time_changed'];
		} else {
			$timeChanged2 = $contactRows['2']['date_created'];
		}
		$latestContact = ($timeChanged1 > $timeChanged2 ? 1 : 2);
		$otherContact = ($latestContact == 1 ? 2 : 1);
		$returnArray['last_changed_1'] = array("data_value" => date((strlen($timeChanged1) > 10 ? "m/d/Y g:ia" : "m/d/Y"), strtotime($timeChanged1)));
		$returnArray['last_changed_2'] = array("data_value" => date((strlen($timeChanged2) > 10 ? "m/d/Y g:ia" : "m/d/Y"), strtotime($timeChanged2)));
		$returnArray['last_changed'] = array("data_value" => ($timeChanged1 > $timeChanged2 ? date((strlen($timeChanged1) > 10 ? "m/d/Y g:ia" : "m/d/Y"), strtotime($timeChanged1)) : date((strlen($timeChanged2) > 10 ? "m/d/Y g:ia" : "m/d/Y"), strtotime($timeChanged2))));
		$returnArray['date_created_1'] = array("data_value" => date("m/d/Y", strtotime($contactRows['1']['date_created'])));
		$returnArray['date_created_2'] = array("data_value" => date("m/d/Y", strtotime($contactRows['2']['date_created'])));
		$returnArray['date_created'] = array("data_value" => ($contactRows['1']['date_created'] > $contactRows['2']['date_created'] ? date("m/d/Y", strtotime($contactRows['2']['date_created'])) : date("m/d/Y", strtotime($contactRows['1']['date_created']))));
		foreach ($fieldsArray as $fieldName => $fieldInfo) {
			if ($fieldInfo['ignore']) {
				continue;
			}
			$useValue = ($fieldInfo['use_either'] ? (empty($contactRows[$latestContact][$fieldName]) ? $contactRows[$otherContact][$fieldName] : $contactRows[$latestContact][$fieldName]) : $contactRows[$latestContact][$fieldName]);
			$returnArray[$fieldName] = array("data_value" => $useValue, "crc_value" => getCrcValue($useValue));
		}
		$addressFields = array("address_1", "address_2", "city", "state", "postal_code");
		$allEmpty = true;
		foreach ($addressFields as $addressField) {
			if (!empty($returnArray[$addressField]['data_value'])) {
				$allEmpty = false;
				break;
			}
		}
		if ($allEmpty) {
			foreach ($addressFields as $addressField) {
				$returnArray[$addressField] = array("data_value" => $contactRows[$otherContact][$addressField], "crc_value" => getCrcValue($contactRows[$otherContact][$addressField]));
			}
		} else {
			foreach ($addressFields as $addressField) {
				$returnArray[$addressField] = array("data_value" => $contactRows[$latestContact][$addressField], "crc_value" => getCrcValue($contactRows[$latestContact][$addressField]));
			}
		}
		if ($contactRows[$otherContact]['country_id'] == $contactRows[$latestContact]['country_id'] &&
			$contactRows[$otherContact]['country_id'] == 1000 && strlen($contactRows[$latestContact]['state']) > 2 &&
			strlen($contactRows[$otherContact]['state']) == 2 && $contactRows[$otherContact]['postal_code'] == $contactRows[$latestContact]['postal_code']) {
			$returnArray['state'] = array("data_value" => $contactRows[$otherContact]['state'], "crc_value" => getCrcValue($contactRows[$otherContact]['state']));
		}

		$resultSet = executeQuery("select * from contact_emails where contact_id = ? order by description", $contactId);
		$returnArray['contact_emails_1'] = array("data_value" => "");
		while ($row = getNextRow($resultSet)) {
			$returnArray['contact_emails_1']['data_value'] .= ($returnArray['contact_emails_1']['data_value'] == "" ? "" : "<br>") . $row['email_address'] . " " . $row['description'];
		}
		$resultSet = executeQuery("select * from contact_emails where contact_id = ? order by description", $duplicateContactId);
		$returnArray['contact_emails_2'] = array("data_value" => "");
		while ($row = getNextRow($resultSet)) {
			$returnArray['contact_emails_2']['data_value'] .= ($returnArray['contact_emails_2']['data_value'] == "" ? "" : "<br>") . $row['email_address'] . " " . $row['description'];
		}

		$contactEmailControl = new DataColumn("contact_emails");
		$contactEmailControl->setControlValue("data_type", "custom");
		$contactEmailControl->setControlValue("control_class", "EditableList");
		$contactEmailControl->setControlValue("primary_table", "contacts");
		$contactEmailControl->setControlValue("list_table", "contact_emails");
		$customControl = new EditableList($contactEmailControl, $this);
		$emailAddresses1 = $customControl->getRecord($contactId);
		$emailAddresses2 = $customControl->getRecord($duplicateContactId);

		$useEmailAddress = "";
		$uniqueEmailAddresses = array();
		if (!empty($contactRows[$latestContact]['email_address']) && !in_array($contactRows[$latestContact]['email_address'], $uniqueEmailAddresses)) {
			if (empty($useEmailAddress)) {
				$useEmailAddress = $contactRows[$latestContact]['email_address'];
				$uniqueEmailAddresses[] = $contactRows[$latestContact]['email_address'];
			}
		}
		if (!empty($contactRows[$otherContact]['email_address']) && !in_array($contactRows[$otherContact]['email_address'], $uniqueEmailAddresses)) {
			if (empty($useEmailAddress)) {
				$useEmailAddress = $contactRows[$otherContact]['email_address'];
				$uniqueEmailAddresses[] = $contactRows[$otherContact]['email_address'];
			} else {
				$emailAddresses2['contact_emails'][] = array("contact_email_id" => array("data_value" => "", "crc_value" => getCrcValue("")),
					"email_address" => array("data_value" => $contactRows[$otherContact]['email_address'], "crc_value" => getCrcValue($contactRows[$otherContact]['email_address'])),
					"description" => array("data_value" => "Alternate Email", "crc_value" => getCrcValue("Alternate Email")));
			}
		}
		$returnArray['email_address'] = array("data_value" => $useEmailAddress, "crc_value" => getCrcValue($useEmailAddress));

		foreach ($emailAddresses1['contact_emails'] as $index => $thisEmailAddress) {
			$emailAddresses1['contact_emails'][$index]['contact_email_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
			if (!in_array($thisEmailAddress['email_address']['data_value'], $uniqueEmailAddresses)) {
				$uniqueEmailAddresses[] = $thisEmailAddress['email_address']['data_value'];
			} else {
				unset($emailAddresses1['contact_emails'][$index]);
			}
		}
		foreach ($emailAddresses2['contact_emails'] as $thisEmailAddress) {
			$emailAddresses2['contact_emails'][$index]['contact_email_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
			if (!in_array($thisEmailAddress['email_address']['data_value'], $uniqueEmailAddresses)) {
				$thisEmailAddress['contact_email_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
				$emailAddresses1['contact_emails'][] = $thisEmailAddress;
				$uniqueEmailAddresses[] = $thisEmailAddress['email_address']['data_value'];
			}
		}
		$returnArray = array_merge($returnArray, $emailAddresses1);

		$userName1 = getFieldFromId("user_name", "users", "contact_id", $contactId);
		$userLastLogin1 = getFieldFromId("last_login", "users", "contact_id", $contactId);
		$returnArray['user_name_1'] = array("data_value" => $userName1);
		$userName2 = getFieldFromId("user_name", "users", "contact_id", $duplicateContactId);
		$userLastLogin2 = getFieldFromId("last_login", "users", "contact_id", $duplicateContactId);
		$returnArray['user_name_2'] = array("data_value" => $userName2);
		$returnArray['select_values']['user_name'] = array();
		if ((empty($userLastLogin1) && !empty($userLastLogin2)) || (!empty($userLastLogin1) && !empty($userLastLogin2) && $userLastLogin2 > $userLastLogin1)) {
			$returnArray['select_values']['user_name'][] = array("key_value" => $userName2, "description" => $userName2);
		}
		if (!empty($userName1)) {
			$returnArray['select_values']['user_name'][] = array("key_value" => $userName1, "description" => $userName1);
		}
		if (!empty($userName2) && count($returnArray['select_values']['user_name']) < 2) {
			$returnArray['select_values']['user_name'][] = array("key_value" => $userName2, "description" => $userName2);
		}

		$otherInformation = "";

		if (!empty($contactRows['1']['notes'])) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Notes: " . makeHtml($contactRows['1']['notes']);
		}
		if (!empty($contactRows['1']['birthdate'])) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Birthdate: " . date("m/d/Y", strtotime($contactRows['1']['birthdate']));
		}
		$resultSet = executeQuery("select * from donations where contact_id = ? order by donation_id desc", $contactId);
		$donationCount = $resultSet['row_count'];
		if ($row = getNextRow($resultSet)) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Donation" . ($donationCount == 1 ? "" : "s") . ": $" . number_format($row['amount'], 2, ".", ",") . " for " .
				getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . ($donationCount > 1 ? ", plus " . ($donationCount - 1) . " more" : "");
		} else {
			$otherInformation = (empty($otherInformation) ? "" : "<br>") . "No Donations";
		}

		$resultSet = executeQuery("select * from recurring_donations where contact_id = ? and (end_date is null or end_date >= current_date) order by recurring_donation_id desc", $contactId);
		$donationCount = $resultSet['row_count'];
		if ($row = getNextRow($resultSet)) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Recurring Donation" . ($donationCount == 1 ? "" : "s") . ": $" . number_format($row['amount'], 2, ".", ",") . " for " .
				getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . ($donationCount > 1 ? ", plus " . ($donationCount - 1) . " more" : "");
		}

		$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null order by account_id desc", $contactId);
		$donationCount = $resultSet['row_count'];
		if ($row = getNextRow($resultSet)) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Account" . ($donationCount == 1 ? "" : "s") . ": " . $row['account_label'] .
				($donationCount > 1 ? ", plus " . ($donationCount - 1) . " more" : "");
		}
		$resultSet = executeQuery("select count(*) from orders where contact_id = ?", $contactId);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] > 0) {
				$otherInformation .= (empty($otherInformation) ? "" : "<br>") . $row['count(*)'] . " order" . ($row['count(*)'] == 1 ? "" : "s");
			}
		}

		$returnArray['other_information_1'] = array("data_value" => $otherInformation);

		$otherInformation = "";
		if (!empty($contactRows['2']['notes'])) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Notes: " . makeHtml($contactRows['2']['notes']);
		}
		if (!empty($contactRows['2']['birthdate'])) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Birthdate: " . date("m/d/Y", strtotime($contactRows['2']['birthdate']));
		}
		$resultSet = executeQuery("select * from donations where contact_id = ? order by donation_id desc", $duplicateContactId);
		$donationCount = $resultSet['row_count'];
		if ($row = getNextRow($resultSet)) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Donation" . ($donationCount == 1 ? "" : "s") . ": $" . number_format($row['amount'], 2, ".", ",") . " for " .
				getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . ($donationCount > 1 ? ", plus " . ($donationCount - 1) . " more" : "");
		} else {
			$otherInformation = (empty($otherInformation) ? "" : "<br>") . "No Donations";
		}

		$resultSet = executeQuery("select * from recurring_donations where contact_id = ? and (end_date is null or end_date >= current_date) order by recurring_donation_id desc", $duplicateContactId);
		$donationCount = $resultSet['row_count'];
		if ($row = getNextRow($resultSet)) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Recurring Donation" . ($donationCount == 1 ? "" : "s") . ": $" . number_format($row['amount'], 2, ".", ",") . " for " .
				getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . ($donationCount > 1 ? ", plus " . ($donationCount - 1) . " more" : "");
		}

		$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null order by account_id desc", $duplicateContactId);
		$donationCount = $resultSet['row_count'];
		if ($row = getNextRow($resultSet)) {
			$otherInformation .= (empty($otherInformation) ? "" : "<br>") . "Account" . ($donationCount == 1 ? "" : "s") . ": " . $row['account_label'] .
				($donationCount > 1 ? ", plus " . ($donationCount - 1) . " more" : "");
		}
		$resultSet = executeQuery("select count(*) from orders where contact_id = ?", $duplicateContactId);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] > 0) {
				$otherInformation .= (empty($otherInformation) ? "" : "<br>") . $row['count(*)'] . " order" . ($row['count(*)'] == 1 ? "" : "s");
			}
		}

		$returnArray['other_information_2'] = array("data_value" => $otherInformation);
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#_delete_button").html("Skip");
            $("#postal_code").blur(function () {
                if ($("#country_id").val() === "1000") {
                    $("#postal_code").data("city_hide", "_city_row").data("city_select_hide", "_city_select_row");
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() === "1000") {
                    $("#postal_code").data("city_hide", "_city_row").data("city_select_hide", "_city_select_row");
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
            $(document).on("tap click", "input[readonly=readonly]", function () {
                const fieldName = $(this).data("field_name");
                if (!empty(fieldName)) {
                    $("#" + fieldName).val($(this).val()).trigger("change").trigger("blur");
                }
            });
            $(document).on("tap click", "#_merge_button", function () {
                disableButtons($(this));
                mergeContacts();
                return false;
            });
            $(document).on("tap click", "#_ignore_button", function () {
                $("#permanent_skip").val("1");
                $("#_delete_button").trigger("click");
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function dontAskAboutChanges() {
                return true;
            }

            function beforeGetRecord() {
                $("#user_name option").remove();
                return true;
            }

            function afterGetRecord() {
                $('body').removeData('just_saved');
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
                $(".highlighted-field").removeClass("highlighted-field");
                $(".merge-field").each(function () {
                    const thisId = $(this).attr("id");
                    const field1Value = $("#" + thisId + "_1").val().replace(new RegExp("/\./", 'g'), "").toLowerCase();
                    const field2Value = $("#" + thisId + "_2").val().replace(new RegExp("/\./", 'g'), "").toLowerCase();
                    if (field1Value !== field2Value) {
                        $(this).addClass("highlighted-field");
                    }
                });
            }

            function mergeContacts() {
                $("#_next_button").add("#_previous_button").data("ignore_click", "true");
                disableButtons($("#_merge_button"));
                if ($("#_edit_form").validationEngine('validate')) {
                    let message = "<p>Merging contact ID " + $("#contact_id_1").val() + " and contact ID " + $("#contact_id_2").val() +
                        ". This is irreversible!</p>";
                    if (!empty($("#user_name_1").val()) && !empty($("#user_name_2").val())) {
                        const userName1 = $("#user_name_1").val();
                        const userName2 = $("#user_name_2").val();
                        const userName = $("#user_name").val();
                        message += "<p class='color-red'>User '" + (userName1 === userName ? userName2 : userName1) + "' will be removed from the system and merged into user '" +
                            (userName1 === userName ? userName1 : userName2) + "'. This is also irreversible!</p>";
                    }
                    const field1Value = $("#last_name_1").val().replace(new RegExp(".", 'g'), "").toLowerCase();
                    const field2Value = $("#last_name_2").val().replace(new RegExp(".", 'g'), "").toLowerCase();
                    if (field1Value !== field2Value) {
                        message += "<p class='color-red'>These two contacts have different last names! Are you sure you have selected the right contacts? This is also irreversible!</p>";
                    }
                    message += "<p>Are you sure you want to continue?</p>";
                    $("#_confirm_dialog").html(message);
                    $('#_confirm_dialog').dialog({
                        closeOnEscape: true,
                        draggable: true,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Confirm Merge',
                        close: function (event, ui) {
                            $("#_next_button").add("#_previous_button").removeData("ignore_click");
                            enableButtons($("#_merge_button"));
                        },
                        buttons: {
                            Merge: function (event) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_contacts", $("#_edit_form").serialize(), function(returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        displayInfoMessage("Contacts Merged");
                                        $('body').data('just_saved', 'true');
										<?php
										if ($_GET['return'] == "contacts") {
										?>
                                        $("#_maintenance_form").html("");
                                        document.location = "/contactmaintenance.php";
										<?php
										echo "return;";
										}
										?>
                                        if ("next_primary_id" in returnArray) {
                                            $("#_next_primary_id").val(returnArray['next_primary_id']);
                                        }
                                        if (!empty($("#_next_primary_id").val())) {
                                            getRecord($("#_next_primary_id").val());
                                        } else if (!empty($("#_previous_primary_id").val())) {
                                            getRecord($("#_previous_primary_id").val());
                                        } else {
                                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                                        }
                                    }
                                });
                                $("#_confirm_dialog").dialog('close');
                            },
                            Cancel: function (event) {
                                $("#_confirm_dialog").dialog('close');
                            }
                        }
                    });
                } else {
                    $("#_next_button").add("#_previous_button").removeData("ignore_click");
                    enableButtons($("#_merge_button"));
                }
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #merge_data_form {
                display: table;
            }

            #_main_content .merge-data-row {
                display: table-row;
            }

            #_main_content .merge-data-cell {
                display: table-cell;
                padding: 5px 20px 5px 10px;
                background-color: rgb(240, 240, 240);
            }

            #_main_content .merge-data-cell p {
                margin: 0;
                padding: 0;
                font-size: .8rem;
            }

            #_main_content .merge-data-cell label {
                display: block;
                text-align: right;
            }

            #_confirm_dialog p {
                margin-bottom: 20px;
                font-size: 14px;
                font-weight: bold;
            }

            #_main_content input[type=text] {
                font-size: 11px;
                max-width: 250px;
                min-width: 250px;
            }

            #_main_content select {
                font-size: 11px;
                max-width: 250px;
                min-width: 250px;
            }

            #_main_content .editable-list input[type=text] {
                font-size: 11px;
                max-width: 150px;
                min-width: 150px;
            }

            #_main_content input[readonly=readonly]:hover {
                cursor: pointer;
            }

            .highlighted-field {
                background-color: rgb(255, 200, 200);
            }
        </style>
		<?php
	}

	function jqueryTemplates() {
		$phoneNumberControl = new DataColumn("phone_numbers");
		$phoneNumberControl->setControlValue("data_type", "custom");
		$phoneNumberControl->setControlValue("control_class", "EditableList");
		$phoneNumberControl->setControlValue("primary_table", "contacts");
		$phoneNumberControl->setControlValue("list_table", "phone_numbers");
		$phoneNumberControl->setControlValue("list_table_controls", "return array('phone_number'=>array('inline-width'=>'130px'),'description'=>array('inline-width'=>'120px'))");
		$customControl = new EditableList($phoneNumberControl, $this);
		echo $customControl->getTemplate();
		$emailAddressControl = new DataColumn("contact_emails");
		$emailAddressControl->setControlValue("data_type", "custom");
		$emailAddressControl->setControlValue("control_class", "EditableList");
		$emailAddressControl->setControlValue("primary_table", "contacts");
		$emailAddressControl->setControlValue("list_table", "contact_emails");
		$emailAddressControl->setControlValue("list_table_controls", "return array('description'=>array('inline-width'=>'130px'),'email_address'=>array('inline-width'=>'120px'))");
		$customControl = new EditableList($emailAddressControl, $this);
		echo $customControl->getTemplate();
	}

	function hiddenElements() {
		?>
        <div id="_confirm_dialog" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new DuplicateProcessingPage("potential_duplicates");
$pageObject->displayPage();
