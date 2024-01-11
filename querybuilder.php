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

$GLOBALS['gPageCode'] = "QUERYBUILDER";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class ThisPage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("query_details", "query_results"));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$queryDefinitionId = getFieldFromId("query_definition_id", "query_definitions", "query_definition_id", $_GET['primary_id']);
			if (empty($queryDefinitionId)) {
				return;
			}
			$resultSet = executeQuery("select * from query_definitions where query_definition_id = ?", $queryDefinitionId);
			$queryDefinitionRow = getNextRow($resultSet);
			$originalQueryDefinitionCode = $queryDefinitionRow['query_definition_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($queryDefinitionRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$queryDefinitionRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$queryDefinitionRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newQueryDefinitionId = "";
			$queryDefinitionRow['description'] .= " Copy";
			while (empty($newQueryDefinitionId)) {
				$queryDefinitionRow['query_definition_code'] = $originalQueryDefinitionCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from query_definitions where query_definition_code = ?",
						$queryDefinitionRow['query_definition_code']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into query_definitions values (" . $queryString . ")", $queryDefinitionRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newQueryDefinitionId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newQueryDefinitionId;
			$subTables = array("query_details");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where query_definition_id = ?", $queryDefinitionId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['query_definition_id'] = $newQueryDefinitionId;
					$insertSet = executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "query_action":
				$queryDefinitionId = getFieldFromId("query_definition_id", "query_definitions", "query_definition_id", $_GET['query_definition_id']);
				if (empty($queryDefinitionId)) {
					$returnArray['query_action_results'] = "Query Definition Not Found.";
					ajaxResponse($returnArray);
					break;
				}
				$queryAction = $_GET['query_action'];
				if (empty($queryAction)) {
					ajaxResponse($returnArray);
					break;
				}
				if (!in_array($queryAction, array("clearselect", "select", "unselect", "export"))) {
					$returnArray['query_action_results'] = "Invalid action... nothing done.";
					ajaxResponse($returnArray);
					break;
				}
				if ($queryAction == "export") {
					header("Content-Type: text/csv");
					header("Content-Disposition: attachment; filename=\"contacts.csv\"");
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					echo '"Contact ID","Title","First Name","Middle Name","Last Name","Suffix","Preferred First Name","Alternate Name","Business Name",' .
							'"Job Title","Salutation","Address 1","Address 2","City","State","Postal Code","Country","Email Address","Date Created"' . "\r\n";

					$resultSet = executeQuery("select * from query_results join contacts using (contact_id) where query_definition_id = ?", $queryDefinitionId);
					while ($row = getNextRow($resultSet)) {
						echo createCsvRow(array($row['contact_id'],
								$row['title'],
								$row['first_name'],
								$row['middle_name'],
								$row['last_name'],
								$row['suffix'],
								$row['preferred_first_name'],
								$row['alternate_name'],
								$row['business_name'],
								$row['job_title'],
								$row['salutation'],
								$row['address_1'],
								$row['address_2'],
								$row['city'],
								$row['state'],
								$row['postal_code'],
								$row['country_id'],
								$row['email_address'],
								date("m/d/Y", strtotime($row['date_created']))));
					}
					exit;
				} else {
					$pageId = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
					$unselectCount = 0;
					$selectCount = 0;
					$alreadySelectedCount = 0;
					if ($queryAction == "clearselect") {
						$resultSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
						$unselectCount = $resultSet['affected_rows'];
					}
					$resultSet = executeQuery("select * from query_results where query_definition_id = ?", $queryDefinitionId);
					while ($row = getNextRow($resultSet)) {
						if ($queryAction == "unselect") {
							$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ? and primary_identifier = ?",
									$GLOBALS['gUserId'], $pageId, $row['contact_id']);
							$unselectCount += $actionSet['affected_rows'];
						} else {
							$checkSet = executeQuery("select * from selected_rows where user_id = ? and page_id = ? and primary_identifier = ?",
									$GLOBALS['gUserId'], $pageId, $row['contact_id']);
							if (!$checkRow = getNextRow($checkSet)) {
								executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) values (?,?,?)",
										$GLOBALS['gUserId'], $pageId, $row['contact_id']);
								$selectCount++;
							} else {
								$alreadySelectedCount++;
							}
						}
					}
					$returnArray['query_action_results'] = $selectCount . " contacts selected, " . $unselectCount . " contacts unselected, " .
							$alreadySelectedCount . " contacts already selected.";
					$selectedCount = 0;
					$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?",
							$GLOBALS['gUserId'], $pageId);
					if ($row = getNextRow($resultSet)) {
						$selectedCount = $row['count(*)'];
					}
					$returnArray['contacts_selected'] = $selectedCount;
					ajaxResponse($returnArray);
					break;
				}
			case "run_query":
				$returnArray = array();

				$contactCount = 0;
				$queryDefinitionId = getFieldFromId("query_definition_id", "query_definitions", "query_definition_id", $_GET['query_definition_id']);
				if (empty($queryDefinitionId)) {
					$returnArray['date_last_run'] = "";
					$returnArray['contacts_chosen'] = $contactCount;
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select * from query_definitions where query_definition_id = ?", $queryDefinitionId);
				$queryDefinitionRow = getNextRow($resultSet);
				$queryStatements = "Include contacts that match " . $queryDefinitionRow['include_match_code'] . ":\n";

# Get included records

				$queryDetailSet = executeQuery("select queryable_tables.table_name,queryable_tables.join_table_name,queryable_tables.column_name as contact_identifier_column_name," .
						"queryable_tables.query_text,queryable_fields.column_name,query_details.comparator,query_details.field_value,query_details.exclude from " .
						"query_details,queryable_tables,queryable_fields where " .
						"query_definition_id = ? and query_details.queryable_table_id = queryable_tables.queryable_table_id and " .
						"query_details.queryable_field_id = queryable_fields.queryable_field_id and exclude = 0 " .
						"order by query_details.sort_order,query_detail_id", $queryDefinitionRow['query_definition_id']);
				$firstQuery = true;
				$contactsArray = array();
				while ($queryDetailRow = getNextRow($queryDetailSet)) {
					$thisTable = new DataTable($queryDetailRow['table_name']);
					if (!empty($queryDetailRow['join_table_name'])) {
						$thisJoinTable = new DataTable($queryDetailRow['join_table_name']);
					} else {
						$thisJoinTable = false;
					}
					if ($thisTable->columnExists($queryDetailRow['column_name'])) {
						$thisColumn = $thisTable->getColumns($queryDetailRow['column_name']);
					} else {
						if (!empty($queryDetailRow['join_table_name']) && $thisJoinTable->columnExists($queryDetailRow['column_name'])) {
							$thisColumn = $thisJoinTable->getColumns($queryDetailRow['column_name']);
						} else {
							continue;
						}
					}
					$dataType = $thisColumn->getControlValue("data_type");
					$fieldQueryArray = $this->createFieldQuery($queryDetailRow['column_name'], $queryDetailRow['comparator'], $queryDetailRow['field_value'], $dataType);
					$fieldQuery = $fieldQueryArray['field_query'];
					$negated = $fieldQueryArray['negated'];
					$negatedFieldQuery = $fieldQueryArray['negated_field_query'];
					$parameters = $fieldQueryArray['parameters'];
					$primaryIdentifierField = getFieldFromId("column_name", "column_definitions", "column_name", $queryDetailRow['contact_identifier_column_name']);
					if (empty($primaryIdentifierField)) {
						$primaryIdentifierField = "contact_id";
					}
					if ($queryDetailRow['table_name'] == "contacts") {
						$query = "select contact_id from " . $queryDetailRow['table_name'] . (empty($queryDetailRow['join_table_name']) ? "" : "," . $queryDetailRow['join_table_name']) .
								" where " . (empty($queryDetailRow['query_text']) ? "" : $queryDetailRow['query_text'] . " and ") .
								"contacts.client_id = " . $GLOBALS['gClientId'] . " and " . $fieldQuery;
					} else {
						$query = "select contact_id from contacts where contacts.client_id = " . $GLOBALS['gClientId'] . " and " .
								"contact_id " . ($negated ? "not " : "") . "in (select " . $primaryIdentifierField . " from " . $queryDetailRow['table_name'] .
								(empty($queryDetailRow['join_table_name']) ? "" : "," . $queryDetailRow['join_table_name']) .
								" where " . (empty($queryDetailRow['query_text']) ? "" : $queryDetailRow['query_text'] . " and ") .
								($negated ? $negatedFieldQuery : $fieldQuery) . ")";
					}
					$queryStatements .= date("H:i:s") . " - " . $query . " : " . implode(",", $parameters) . "\n";
					$resultSet = executeQuery($query, $parameters);
					$thisContactsArray = array();
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['contact_id'], $thisContactsArray)) {
							$thisContactsArray[$row['contact_id']] = true;
						}
					}
					if ($firstQuery || $queryDefinitionRow['include_match_code'] != "all") {
						$newArray = array();
						foreach ($thisContactsArray as $contactId => $value) {
							$newArray[$contactId] = $value;
						}
						foreach ($contactsArray as $contactId => $value) {
							$newArray[$contactId] = $value;
						}
						$contactsArray = $newArray;
					} else {
						$newArray = array();
						foreach ($thisContactsArray as $contactId => $value) {
							if (array_key_exists($contactId,$contactsArray)) {
								$newArray[$contactId] = $value;
							}
						}
						$contactsArray = $newArray;
					}
					$queryStatements .= "Found " . count($thisContactsArray) . ", Current count: " . count($contactsArray) . "\n";
					$firstQuery = false;
				}

# Get excluded records

				$queryStatements .= "Exclude contacts that match " . $queryDefinitionRow['exclude_match_code'] . ":\n";
				$queryDetailSet = executeQuery("select * from query_details,queryable_tables,queryable_fields where " .
						"query_definition_id = ? and query_details.queryable_table_id = queryable_tables.queryable_table_id and " .
						"query_details.queryable_field_id = queryable_fields.queryable_field_id and exclude = 1 " .
						"order by query_details.sort_order,query_detail_id", $queryDefinitionRow['query_definition_id']);
				$firstQuery = true;
				$excludeArray = array();
				while ($queryDetailRow = getNextRow($queryDetailSet)) {
					$thisTable = new DataTable($queryDetailRow['table_name']);
					$thisColumn = $thisTable->getColumns($queryDetailRow['column_name']);
					$dataType = $thisColumn->getControlValue("data_type");
					$fieldQueryArray = $this->createFieldQuery($queryDetailRow['column_name'], $queryDetailRow['comparator'], $queryDetailRow['field_value'], $dataType);
					$fieldQuery = $fieldQueryArray['field_query'];
					$negated = $fieldQueryArray['negated'];
					$negatedFieldQuery = $fieldQueryArray['negated_field_query'];
					$parameters = $fieldQueryArray['parameters'];
					$primaryIdentifierField = getFieldFromId("column_name", "column_definitions", "column_name", $queryDetailRow['contact_identifier_column_name']);
					if (empty($primaryIdentifierField)) {
						$primaryIdentifierField = "contact_id";
					}
					if ($queryDetailRow['table_name'] == "contacts") {
						$query = "select contact_id from " . $queryDetailRow['table_name'] . (empty($queryDetailRow['join_table_name']) ? "" : "," . $queryDetailRow['join_table_name']) .
								" where " . (empty($queryDetailRow['query_text']) ? "" : $queryDetailRow['query_text'] . " and ") .
								"contacts.client_id = " . $GLOBALS['gClientId'] . " and " . $fieldQuery;
					} else {
						$query = "select contact_id from contacts where contacts.client_id = " . $GLOBALS['gClientId'] . " and " .
								"contact_id " . ($negated ? "not " : "") . "in (select " . $primaryIdentifierField . " from " . $queryDetailRow['table_name'] .
								(empty($queryDetailRow['join_table_name']) ? "" : "," . $queryDetailRow['join_table_name']) .
								" where " . (empty($queryDetailRow['query_text']) ? "" : $queryDetailRow['query_text'] . " and ") .
								($negated ? $negatedFieldQuery : $fieldQuery) . ")";
					}
					$queryStatements .= $query . " : " . implode(",", $parameters) . "\n";
					$resultSet = executeQuery($query, $parameters);
					$thisContactsArray = array();
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['contact_id'], $thisContactsArray)) {
							$thisContactsArray[$row['contact_id']] = true;
						}
					}
					if ($firstQuery || $queryDefinitionRow['include_match_code'] != "all") {
						$newArray = array();
						foreach ($thisContactsArray as $contactId => $value) {
							$newArray[$contactId] = $value;
						}
						foreach ($excludeArray as $contactId => $value) {
							$newArray[$contactId] = $value;
						}
						$excludeArray = $newArray;
					} else {
						$newArray = array();
						foreach ($thisContactsArray as $contactId => $value) {
							if (array_key_exists($contactId,$excludeArray)) {
								$newArray[$contactId] = $value;
							}
						}
						$excludeArray = $newArray;
					}
					$queryStatements .= "Found " . count($thisContactsArray) . ", Current count: " . count($contactsArray) . "\n";
					$firstQuery = false;
				}

				$contactsArray = array_diff_key($contactsArray, $excludeArray);
				$queryStatements .= "Found to exclude: " . count($excludeArray) . ", Final count: " . count($contactsArray) . "\n";
				executeQuery("delete from query_results where query_definition_id = ?", $queryDefinitionRow['query_definition_id']);
				$insertString = "";
				foreach ($contactsArray as $contactId => $value) {
					$insertString .= (empty($insertString) ? "" : ",") . "(" . $queryDefinitionRow['query_definition_id'] . "," . $contactId . ")";
					if (strlen($insertString) > 5000) {
						$this->iDatabase->executeQuery("insert into query_results (query_definition_id,contact_id) values " . $insertString);
						$insertString = "";
					}
				}
				if (!empty($insertString)) {
					$this->iDatabase->executeQuery("insert into query_results (query_definition_id,contact_id) values " . $insertString);
					$insertString = "";
				}
				$resultSet = executeQuery("select count(*) from query_results where query_definition_id = ?", $queryDefinitionRow['query_definition_id']);
				if ($row = getNextRow($resultSet)) {
					$contactCount = $row['count(*)'];
				}
				executeQuery("update query_definitions set date_last_run = now() where query_definition_id = ?", $queryDefinitionRow['query_definition_id']);
				if ($GLOBALS['gUserRow']['superuser_flag']) {
					$returnArray['query_statements'] = $queryStatements . "\n";
				}
				$returnArray['date_last_run'] = date("m/d/Y");
				$returnArray['contacts_chosen'] = $contactCount;
				ajaxResponse($returnArray);
				break;
		}
	}

	function createFieldQuery($columnName, $comparator, $fieldValue, $dataType) {
		$fieldQuery = "";
		$negated = false;
		$negatedFieldQuery = "";
		$parameters = array();
		if ($dataType == "date" || $dataType == "datetime") {
			$parameters[] = $this->iDatabase->makeDateParameter($fieldValue);
		} else {
			$parameters[] = $fieldValue;
		}
		switch ($comparator) {
			case "notequal":
				$fieldQuery = $columnName . " <> ?";
				$negated = true;
				$negatedFieldQuery = $columnName . " = ?";
				break;
			case "greater":
				$fieldQuery = $columnName . " > ?";
				break;
			case "less":
				$fieldQuery = $columnName . " < ?";
				break;
			case "notgreater":
				$fieldQuery = $columnName . " <= ?";
				break;
			case "notless":
				$fieldQuery = $columnName . " >= ?";
				break;
			case "null":
				$parameters = array();
				$fieldQuery = $columnName . " is null";
				break;
			case "notnull":
				$parameters = array();
				$fieldQuery = $columnName . " is not null";
				$negated = true;
				$negatedFieldQuery = $columnName . " is null";
				break;
			case "between":
				$fieldQuery = $columnName . " between ? and ?";
				$fieldValues = explode(",", $fieldValue);
				$parameters = array();
				if ($dataType == "date" || $dataType == "datetime") {
					$parameters[] = $this->iDatabase->makeDateParameter($fieldValues[0]);
					$parameters[] = $this->iDatabase->makeDateParameter($fieldValues[1]);
				} else {
					$parameters[] = $fieldValues[0];
					$parameters[] = $fieldValues[1];
				}
				break;
			case "start":
				$fieldQuery = $columnName . " like ?";
				$parameters = array($fieldValue . "%");
				break;
			case "contains":
				$fieldQuery = $columnName . " like ?";
				$parameters = array("%" . $fieldValue . "%");
				break;
			case "notcontains":
				$fieldQuery = $columnName . " not like ?";
				$parameters = array("%" . $fieldValue . "%");
				$negated = true;
				$negatedFieldQuery = $columnName . " like ?";
				break;
			case "in":
			case "notin":
				$fieldValues = explode(",", $fieldValue);
				$fieldQuery = "";
				$parameters = array();
				foreach ($fieldValues as $fieldValue) {
					if (!empty($fieldQuery)) {
						$fieldQuery .= ",";
					}
					$fieldQuery .= "?";
					$parameters[] = $fieldValue;
				}
				$fieldQuery = $columnName . ($comparator == "notin" ? " not" : "") . " in (" . $fieldQuery . ")";
				if ($comparator == "notin") {
					$negated = true;
					$negatedFieldQuery = $columnName . " in (" . $fieldQuery . ")";
				}
				break;
			case "set":
				$parameters = array();
				$fieldQuery = $columnName . " = 1";
				break;
			case "notset":
				$parameters = array();
				$fieldQuery = $columnName . " = 0";
				break;
			default:
				$fieldQuery = $columnName . " = ?";
				break;
		}
		return array("field_query" => $fieldQuery, "negated" => $negated, "negated_field_query" => $negatedFieldQuery, "parameters" => $parameters);
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("exclude_match_code", "include_match_code"));
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
						"disabled" => false)));
			}
		}
	}

	function onLoadJavascript() {
		?>
		<script>
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
			$(document).on("tap click", "#_duplicate_button", function () {
				if ($("#primary_id").val() != "") {
					if (changesMade()) {
						askAboutChanges(function () {
							$('body').data('just_saved', 'true');
							document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
						});
					} else {
						document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
					}
				}
				return false;
			});
			<?php } ?>
			$("#query_action").change(function () {
				if (!empty($(this).val())) {
					if ($(this).val() == "export") {
						document.location = "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=query_action&query_action=" + $("#query_action").val() + "&query_definition_id=" + $("#primary_id").val();
						$(this).val("");
					} else {
						loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=query_action&query_action=" + $("#query_action").val() + "&query_definition_id=" + $("#primary_id").val(), function(returnArray) {
							$("#query_action").val("");
							if ("query_action_results" in returnArray) {
								$("#query_action_results").html(returnArray['query_action_results']);
							}
							if ("contacts_selected" in returnArray) {
								$("#contacts_selected").val(returnArray['contacts_selected']);
							}
						});
					}
				}
			});
			$(document).on("tap click", "#run_query", function () {
				if (changesMade()) {
					saveChanges(function () {
						$("#run_query").data("primary_id", $("#primary_id").val());
						getRecord($("#primary_id").val());
						enableButtons($("#_save_button"));
					}, function () {
						enableButtons($("#_save_button"));
					});
				} else {
					loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=run_query&query_definition_id=" + $("#primary_id").val(), function(returnArray) {
						$.each(returnArray, function (fieldName, fieldValue) {
							$("#" + fieldName).val(fieldValue);
						});
					});
				}
				return false;
			});
			$(document).on("tap click", "#add_include", function () {
				var rowNumber = parseInt($("#include_table").data("row_number"));
				$("#include_table").data("row_number", (rowNumber + 1));
				var newRow = $("#clude_row").html().replace(/%rowId%/g, rowNumber);
				$("#include_table").find(".add-row").before(newRow);
				$.each(queryableTables, function (queryableTableIndex, queryableTableInfo) {
					$("#queryable_table_id_" + rowNumber).append($('<option>', { value: queryableTableInfo['queryable_table_id'] }).text(queryableTableInfo['description']));
				});
				$("#clude_row_" + rowNumber).find("input,select,textarea").not("input[type=hidden]").data("crc_value", getCrcValue(""));
				$("#queryable_table_id_" + rowNumber).focus();
				return false;
			});
			$(document).on("tap click", "#add_exclude", function () {
				var rowNumber = parseInt($("#include_table").data("row_number"));
				$("#include_table").data("row_number", (rowNumber + 1));
				var newRow = $("#clude_row").html().replace(/%rowId%/g, rowNumber);
				$("#exclude_table").find(".add-row").before(newRow);
				$.each(queryableTables, function (queryableTableIndex, queryableTableInfo) {
					$("#queryable_table_id_" + rowNumber).append($('<option>', { value: queryableTableInfo['queryable_table_id'] }).text(queryableTableInfo['description']));
				});
				$("#exclude_" + rowNumber).val("1");
				$("#clude_row_" + rowNumber).find("input,select,textarea").not("input[type=hidden]").data("crc_value", getCrcValue(""));
				$("#queryable_table_id_" + rowNumber).focus();
				return false;
			});
			$(document).on("tap click", "#delete_clude", function () {
				var primaryId = $(this).closest("tr").find(".query-detail-id").val();
				if (primaryId != "") {
					var deleteIds = $(this).closest("table").find(".delete-ids").val();
					if (deleteIds != "") {
						deleteIds += ",";
					}
					deleteIds += primaryId;
					$(this).closest("table").find(".delete-ids").val(deleteIds);
				}
				$(this).closest("tr").remove();
				return false;
			});
			$(document).on("change", ".queryable-table", function () {
				var queryableTableId = $(this).val();
				var rowNumber = $(this).data("row_number");

				$("#queryable_field_id_" + rowNumber).data("data_type", "");
				$("#queryable_field_id_" + rowNumber).find("option[value!='']").remove();
				$("#comparator_" + rowNumber).find("option[value!='']").remove();
				$("#field_value_cell_" + rowNumber).html("");

				if (queryableTableId in queryableData) {
					$.each(queryableData[queryableTableId], function (index, queryableField) {
						$("#queryable_field_id_" + rowNumber).append($('<option>', { value: queryableField['queryable_field_id'] }).text(queryableField['description']));
					});
				}
				$("#queryable_field_id_" + rowNumber).val("");
				$("#comparator_" + rowNumber).val("");
			});
			$(document).on("change", ".queryable-field", function () {
				var queryableFieldId = $(this).val();
				var rowNumber = $(this).data("row_number");
				var queryableTableId = $("#queryable_table_id_" + rowNumber).val();

				$("#comparator_" + rowNumber).find("option[value!='']").remove();
				$("#queryable_field_id_" + rowNumber).data("data_type", "");
				$("#field_value_cell_" + rowNumber).html("");

				if (queryableTableId in queryableData) {
					if (queryableFieldId in queryableData[queryableTableId]) {
						if (queryableData[queryableTableId][queryableFieldId]['data_type'] in dataTypes) {
							var thisDataType = queryableData[queryableTableId][queryableFieldId]['data_type'];
							var notNull = queryableData[queryableTableId][queryableFieldId]['not_null'];
							$("#queryable_field_id_" + rowNumber).data("data_type", thisDataType);
							$.each(dataTypes[thisDataType], function (index, comparator) {
								if (comparator in comparatorArray) {
									if (!notNull || (comparator != "null" && comparator != "notnull")) {
										$("#comparator_" + rowNumber).append($('<option>', { value: comparator }).text(comparatorArray[comparator]));
									}
								}
							});
						}
					}
				}
				$("#comparator_" + rowNumber).val("");
			});
			$(document).on("change", ".comparator", function () {
				var rowNumber = $(this).data("row_number");
				var dataType = $("#queryable_field_id_" + rowNumber).data("data_type");
				var queryableTableId = $("#queryable_table_id_" + rowNumber).val();
				var queryableFieldId = $("#queryable_field_id_" + rowNumber).val();
				var fieldValue = "";
				if ($("#field_value_" + rowNumber).length > 0) {
					fieldValue = $("#field_value_" + rowNumber).val();
				}
				$("#field_value_cell_" + rowNumber).html("");
				if (dataType == "tinyint" || $(this).val() == "null" || $(this).val() == "notnull") {
					return;
				}
				if (dataType == "select") {
					$("#field_value_cell_" + rowNumber).html("<select tabindex='10' id='field_value_" + rowNumber + "' name='field_value_" + rowNumber + "' data-crc_value='<?= getCrcValue("") ?>'><option value=''>[Select]</option></select>");
					if (queryableTableId in queryableData) {
						if (queryableFieldId in queryableData[queryableTableId]) {
							if ("choices" in queryableData[queryableTableId][queryableFieldId]) {
								$.each(queryableData[queryableTableId][queryableFieldId]['choices'], function (selectValue, selectOptions) {
									$("#field_value_" + rowNumber).append($('<option>', { value: selectOptions['key_value'] }).text(selectOptions['description']));
								});
							}
						}
					}
					$("#field_value_" + rowNumber).val(fieldValue);
					return;
				}

				if (dataType == "autocomplete") {
					var referencedTableName = "";
					if (queryableTableId in queryableData) {
						if (queryableFieldId in queryableData[queryableTableId]) {
							referencedTableName = queryableData[queryableTableId][queryableFieldId]['referenced_table_id'];
						}
					}
					$("#field_value_cell_" + rowNumber).html("<input type='hidden' id='field_value_" + rowNumber + "' name='field_value_" + rowNumber + "' value=''><input autocomplete='chrome-off' autocomplete='off' tabindex='10' class='autocomplete-field' type='text' size='50' name='field_value_" + rowNumber + "_autocomplete_text' id='field_value_" + rowNumber + "_autocomplete_text' data-autocomplete_tag='" + referencedTableName + "'>");
					$("#field_value_" + rowNumber).val(fieldValue);
					$("#field_value_" + rowNumber + "autocomplete_text").trigger("get_autocomplete_text");
					return;
				}

				var extraClass = "";
				var dataValues = "size='50' data-crc_value='<?= getCrcValue("") ?>'";
				if (dataType == "date" || dataType == "datetime") {
					extraClass = "validate[custom[date]]";
					dataValues = "size='12'"
				} else if (dataType == "int" || dataType == "bigint") {
					extraClass = "validate[custom[integer]] align-right";
					dataValues = "size='10'"
				} else if (dataType == "decimal") {
					extraClass = "validate[custom[number]] align-right";
					dataValues = "data-decimal-places='2' size='12'";
				} else if ($(this).val() == "between") {
					dataValues = "size='20' data-crc_value='<?= getCrcValue("") ?>'";
				}
				$("#field_value_cell_" + rowNumber).html("<input type='text' tabindex='10' id='field_value_" + rowNumber + "' name='field_value_" + rowNumber + "' class='" + extraClass + "' " + dataValues + ">");
				$("#field_value_" + rowNumber).val(fieldValue);
				if ($(this).val() == "between") {
					$("#field_value_cell_" + rowNumber).append("&nbsp;<span>and</span>&nbsp;<input tabindex='10' id='field_value_2_" + rowNumber + "' name='field_value_2_" + rowNumber + "' class='" + extraClass + "' " + dataValues + ">");
				}
			});
		</script>
		<?php
	}

	function javascript() {
		$queryableData = array();
		$queryableTables = array();
		$resultSet = executeQuery("select * from queryable_tables where inactive = 0 and (queryable_table_id not in " .
				"(select queryable_table_id from queryable_table_clients) or queryable_table_id in (select queryable_table_id " .
				"from queryable_table_clients where client_id = ?)) order by sort_order,table_name", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			try {
				$thisTable = new DataTable($row['table_name']);
				if (!empty($row['join_table_name'])) {
					$thisJoinTable = new DataTable($row['join_table_name']);
				} else {
					$thisJoinTable = false;
				}
			} catch (Exception $e) {
				continue;
			}
			$queryableData[$row['queryable_table_id']] = array();
			$resultSet1 = executeQuery("select * from queryable_fields where inactive = 0 and (queryable_field_id not in " .
					"(select queryable_field_id from queryable_field_clients) or queryable_field_id in (select queryable_field_id " .
					"from queryable_field_clients where client_id = ?)) and queryable_table_id = ? order by sort_order,column_name", $GLOBALS['gClientId'], $row['queryable_table_id']);
			while ($row1 = getNextRow($resultSet1)) {
				if ($thisTable->columnExists($row1['column_name'])) {
					$thisColumn = $thisTable->getColumns($row1['column_name']);
				} else {
					if (!empty($row['join_table_name']) && $thisJoinTable->columnExists($row1['column_name'])) {
						$thisColumn = $thisJoinTable->getColumns($row1['column_name']);
					} else {
						continue;
					}
				}
				if ($thisColumn->getControlValue("data_type") == "select") {
					$choices = $thisColumn->getChoices($this);
					if (empty($choices)) {
						if ($thisColumn->getControlValue("not_null")) {
							unset($queryableData[$row['queryable_table_id']]);
							continue 2;
						} else {
							continue;
						}
					}
				}
				$queryableData[$row['queryable_table_id']][$row1['queryable_field_id']] = array("queryable_field_id" => $row1['queryable_field_id'], "description" => $thisColumn->getControlValue("form_label"),
						"data_type" => $thisColumn->getControlValue("data_type"), "not_null" => $thisColumn->getControlValue("not_null"), "referenced_table_id" => $thisColumn->getReferencedTable());
				if ($thisColumn->getControlValue("data_type") == "select") {
					$queryableData[$row['queryable_table_id']][$row1['queryable_field_id']]['choices'] = array_values($choices);
				}
			}
			if (empty($queryableData[$row['queryable_table_id']])) {
				unset($queryableData[$row['queryable_table_id']]);
			} else {
				$queryableTables[] = array("queryable_table_id" => $row['queryable_table_id'], "description" => $thisTable->getDescription());
			}
		}
		?>
		<script>
			var queryableData = <?= jsonEncode($queryableData) ?>;
			var queryableTables = <?= jsonEncode($queryableTables) ?>;
			<?php
			$comparatorArray = array("equal" => "equals", "notequal" => "not equals", "greater" => "greater than", "less" => "less than",
					"notgreater" => "less than or equal", "notless" => "greater than or equal", "null" => "is empty", "notnull" => "is not empty",
					"between" => "between", "start" => "starts with", "contains" => "contains", "notcontains" => "does not contain", "in" => "in",
					"notin" => "not in", "set" => "is set", "notset" => "is not set");
			$dataTypes = array();
			$dataTypes['varchar'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between", "start", "contains", "notcontains", "in", "notin");
			$dataTypes['text'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between", "start", "contains", "notcontains", "in", "notin");
			$dataTypes['mediumtext'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between", "start", "contains", "notcontains", "in", "notin");
			$dataTypes['int'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between");
			$dataTypes['decimal'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between");
			$dataTypes['date'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between");
			$dataTypes['datetime'] = array("equal", "notequal", "greater", "less", "notless", "notgreater", "null", "notnull", "between");
			$dataTypes['tinyint'] = array("set", "notset");
			$dataTypes['select'] = array("equal", "notequal", "null", "notnull");
			$dataTypes['autocomplete'] = array("equal", "notequal", "null", "notnull");
			?>
			var comparatorArray = <?= jsonEncode($comparatorArray) ?>;
			var dataTypes = <?= jsonEncode($dataTypes) ?>;

			function afterGetRecord(returnArray) {
				$("#exclude_table tr.clude-row").remove();
				$("#include_table tr.clude-row").remove();
				$("#include").data("row_number", "1");
				if ("query_details" in returnArray) {
					for (var x in returnArray['query_details']) {
						if (returnArray['query_details'][x]['exclude']['data_value'] == "0") {
							addIncludeRow(returnArray['query_details'][x]);
						} else {
							addExcludeRow(returnArray['query_details'][x]);
						}
					}
				}
				var runQueryId = $("#run_query").data("primary_id");
				$("#run_query").data("primary_id", "");
				if (runQueryId == $("#primary_id").val()) {
					$("#run_query").trigger("click");
				}
				$(".autocomplete-field").trigger("get_autocomplete_text");
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
				if ($("#primary_id").val() == "") {
					disableButtons($("#_duplicate_button"));
				} else {
					enableButtons($("#_duplicate_button"));
				}
				<?php } ?>
			}

			function addIncludeRow(dataArray) {
				var rowNumber = parseInt($("#include_table").data("row_number"));
				$("#include_table").data("row_number", (rowNumber + 1));
				var newRow = $("#clude_row").html().replace(/%rowId%/g, rowNumber);
				$("#include_table").find(".add-row").before(newRow);
				$.each(queryableTables, function (queryableTableIndex, queryableTableInfo) {
					$("#queryable_table_id_" + rowNumber).append($('<option>', { value: queryableTableInfo['queryable_table_id'] }).text(queryableTableInfo['description']));
				});
				$("#clude_row_" + rowNumber).find("input,select,textarea").not("input[type=hidden]").data("crc_value", getCrcValue(""));
				$("#queryable_table_id_" + rowNumber).val(dataArray['queryable_table_id']['data_value']).data("crc_value", dataArray['queryable_table_id']['crc_value']).trigger("change");
				$("#queryable_field_id_" + rowNumber).val(dataArray['queryable_field_id']['data_value']).data("crc_value", dataArray['queryable_field_id']['crc_value']).trigger("change");
				$("#comparator_" + rowNumber).val(dataArray['comparator']['data_value']).data("crc_value", dataArray['comparator']['crc_value']).trigger("change");
				if ($("#field_value_" + rowNumber).length > 0) {
					$("#field_value_" + rowNumber).val(dataArray['field_value']['data_value']).data("crc_value", dataArray['field_value']['crc_value']);
				}
				if ($("#field_value_2_" + rowNumber).length > 0) {
					$("#field_value_2_" + rowNumber).val(dataArray['field_value_2']['data_value']).data("crc_value", dataArray['field_value_2']['crc_value']);
				}
				$("#query_detail_id_" + rowNumber).val(dataArray['query_detail_id']['data_value']);
			}

			function addExcludeRow(dataArray) {
				var rowNumber = parseInt($("#include_table").data("row_number"));
				$("#include_table").data("row_number", (rowNumber + 1));
				var newRow = $("#clude_row").html().replace(/%rowId%/g, rowNumber);
				$("#exclude_table").find(".add-row").before(newRow);
				$.each(queryableTables, function (queryableTableIndex, queryableTableInfo) {
					$("#queryable_table_id_" + rowNumber).append($('<option>', { value: queryableTableInfo['queryable_table_id'] }).text(queryableTableInfo['description']));
				});
				$("#clude_row_" + rowNumber).find("input,select,textarea").not("input[type=hidden]").data("crc_value", getCrcValue(""));
				$("#queryable_table_id_" + rowNumber).val(dataArray['queryable_table_id']['data_value']).data("crc_value", dataArray['queryable_table_id']['crc_value']).trigger("change");
				$("#queryable_field_id_" + rowNumber).val(dataArray['queryable_field_id']['data_value']).data("crc_value", dataArray['queryable_field_id']['crc_value']).trigger("change");
				$("#comparator_" + rowNumber).val(dataArray['comparator']['data_value']).data("crc_value", dataArray['comparator']['crc_value']).trigger("change");
				if ($("#field_value_" + rowNumber).length > 0) {
					$("#field_value_" + rowNumber).val(dataArray['field_value']['data_value']).data("crc_value", dataArray['field_value']['crc_value']);
				}
				if ($("#field_value_2_" + rowNumber).length > 0) {
					$("#field_value_2_" + rowNumber).val(dataArray['field_value_2']['data_value']).data("crc_value", dataArray['field_value_2']['crc_value']);
				}
				$("#query_detail_id_" + rowNumber).val(dataArray['query_detail_id']['data_value']);
				$("#exclude_" + rowNumber).val("1");
			}
		</script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['query_action'] = array("data_value" => "");
		$returnArray['_include_delete_ids'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['_exclude_delete_ids'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$queryDetails = array();
		$resultSet = executeQuery("select * from query_details where query_definition_id = ? order by sort_order", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$thisArray = array();
			$thisArray['query_detail_id'] = array("data_value" => $row['query_detail_id'], "crc_value" => getCrcValue($row['query_detail_id']));
			$thisArray['queryable_table_id'] = array("data_value" => $row['queryable_table_id'], "crc_value" => getCrcValue($row['queryable_table_id']));
			$thisArray['queryable_field_id'] = array("data_value" => $row['queryable_field_id'], "crc_value" => getCrcValue($row['queryable_field_id']));
			$thisArray['comparator'] = array("data_value" => $row['comparator'], "crc_value" => getCrcValue($row['comparator']));
			if ($row['comparator'] == "between") {
				$fieldValues = explode(",", $row['field_value']);
				$fieldValue = $fieldValues[0];
				$fieldValue2 = $fieldValues[1];
			} else {
				$fieldValue = $row['field_value'];
				$fieldValue2 = "";
			}
			$thisArray['field_value'] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
			$thisArray['field_value_2'] = array("data_value" => $fieldValue2, "crc_value" => getCrcValue($fieldValue2));
			$thisArray['exclude'] = array("data_value" => $row['exclude']);
			$queryDetails[] = $thisArray;
		}
		$returnArray['query_details'] = $queryDetails;
		$count = 0;
		$resultSet = executeQuery("select count(*) from query_results where query_definition_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		$returnArray['contacts_chosen'] = array("data_value" => $count);
		unset($returnArray['date_last_run']['crc_value']);
		if (empty($returnArray['date_last_run']['data_value'])) {
			$returnArray['date_last_run']['data_value'] = "Never Run";
		}
		$pageId = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
		$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?",
				$GLOBALS['gUserId'], $pageId);
		if ($row = getNextRow($resultSet)) {
			$selectedCount = $row['count(*)'];
		}
		$returnArray['contacts_selected'] = array("data_value" => $selectedCount);
	}

	function beforeSaveChanges(&$nameValues) {
		unset($nameValues['date_last_run']);
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (array_key_exists("_include_delete_ids", $nameValues)) {
			$deleteIdArray = explode(",", $nameValues["_include_delete_ids"]);
			$deleteIds = "";
			foreach ($deleteIdArray as $thisId) {
				if (!empty($thisId) && is_numeric($thisId)) {
					if (!empty($deleteIds)) {
						$deleteIds .= ",";
					}
					$deleteIds .= $thisId;
				}
			}
			if (!empty($deleteIds)) {
				$resultSet = executeQuery("delete from query_details where query_definition_id = ? and query_detail_id in (" . $deleteIds . ")", $nameValues['primary_id']);
			}
		}
		if (array_key_exists("_exclude_delete_ids", $nameValues)) {
			$deleteIdArray = explode(",", $nameValues["_exclude_delete_ids"]);
			$deleteIds = "";
			foreach ($deleteIdArray as $thisId) {
				if (!empty($thisId) && is_numeric($thisId)) {
					if (!empty($deleteIds)) {
						$deleteIds .= ",";
					}
					$deleteIds .= $thisId;
				}
			}
			if (!empty($deleteIds)) {
				$resultSet = executeQuery("delete from query_details where query_definition_id = ? and query_detail_id in (" . $deleteIds . ")", $nameValues['primary_id']);
			}
		}
		foreach ($nameValues as $fieldName => $queryDetailId) {
			if (substr($fieldName, 0, strlen("query_detail_id_")) == "query_detail_id_") {
				$rowNumber = substr($fieldName, strlen("query_detail_id_"));
				if (!is_numeric($rowNumber)) {
					continue;
				}
				$queryableTableId = $nameValues['queryable_table_id_' . $rowNumber];
				$queryableFieldId = $nameValues['queryable_field_id_' . $rowNumber];
				$comparator = $nameValues['comparator_' . $rowNumber];
				$fieldValue = $nameValues['field_value_' . $rowNumber];
				if (!empty($nameValues['field_value_2_' . $rowNumber]) && $comparator == "between") {
					$fieldValue .= "," . $nameValues['field_value_2_' . $rowNumber];
				}
				$exclude = $nameValues['exclude_' . $rowNumber];
				$sortOrder = $rowNumber * 10;
				if (empty($queryDetailId)) {
					executeQuery("insert into query_details (query_definition_id,queryable_table_id,queryable_field_id,comparator,field_value,exclude,sort_order) values " .
							"(?,?,?,?,?,?,?)", $nameValues['primary_id'], $queryableTableId, $queryableFieldId, $comparator, $fieldValue, $exclude, $sortOrder);
				} else {
					executeQuery("update query_details set queryable_table_id = ?,queryable_field_id = ?," .
							"comparator =  ?,field_value = ?,exclude = ?,sort_order = ? where query_detail_id = ?",
							$queryableTableId, $queryableFieldId, $comparator, $fieldValue, $exclude, $sortOrder, $queryDetailId);
				}
			}
		}
		return true;
	}

	function includeControls() {
		?>
		<table class="editable-list" id="include_table" data-row_number="1">
			<tr>
				<th>Table</th>
				<th>Field</th>
				<th></th>
				<th>Value</th>
				<th></th>
			</tr>
			<tr class="add-row">
				<th colspan="4"><input type="hidden" class="delete-ids" name="_include_delete_ids" id="_include_delete_ids" data-crc_value="<?= getCrcValue("") ?>" value=""/></th>
				<th class="align-center">
					<button tabindex="10" class="no-ui editable-list-add" id="add_include" data-list_identifier="include_table"><span class='fad fa-plus-octagon'></span></button>
				</th>
			</tr>
		</table>
		<?php
	}

	function excludeControls() {
		?>
		<table class="editable-list" id="exclude_table">
			<tr>
				<th>Table</th>
				<th>Field</th>
				<th></th>
				<th>Value</th>
				<th></th>
			</tr>
			<tr class="add-row">
				<th colspan="4"><input type="hidden" class="delete-ids" name="_exclude_delete_ids" id="_exclude_delete_ids" data-crc_value="<?= getCrcValue("") ?>" value=""/></th>
				<th class="align-center">
					<button tabindex="10" class="no-ui editable-list-add" id="add_exclude" data-list_identifier="exclude_table"><span class='fad fa-plus-octagon'></span></button>
				</th>
			</tr>
		</table>
		<?php
	}

	function jqueryTemplates() {
		?>
		<table>
			<tbody id="clude_row">
			<tr class="clude-row" id="clude_row_%rowId%">
				<td><input type="hidden" id="exclude_%rowId%" name="exclude_%rowId%" value="0"><select tabindex="10" id="queryable_table_id_%rowId%" name="queryable_table_id_%rowId%" data-row_number="%rowId%" class="queryable-table validate[required]">
						<option value="">[Select]</option>
					</select></td>
				<td><select tabindex="10" id="queryable_field_id_%rowId%" name="queryable_field_id_%rowId%" data-row_number="%rowId%" class="queryable-field validate[required]">
						<option value="">[Select]</option>
					</select></td>
				<td><select tabindex="10" id="comparator_%rowId%" name="comparator_%rowId%" data-row_number="%rowId%" class="comparator validate[required]">
						<option value="">[Select]</option>
					</select></td>
				<td id="field_value_cell_%rowId%"></td>
				<td class="align-center"><input type="hidden" class="query-detail-id" name="query_detail_id_%rowId%" id="query_detail_id_%rowId%" value=""/>
					<button tabindex="10" class="no-ui editable-list-remove" id="delete_clude"><span class='fad fa-trash-alt'></span></button>
				</td>
			</tr>
			</tbody>
		</table>
		<?php
	}

	function internalCSS() {
		?>
		#include_table { min-width: 500px; }
		#exclude_table { min-width: 500px; }
		#query_statements { width: 900px; }
		#action_results { font-size: 14px; }
		select.comparator { max-width: 200px; }
		<?php
	}
}

$pageObject = new ThisPage("query_definitions");
$pageObject->displayPage();
