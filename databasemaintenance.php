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

$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gPageCode'] = "DATABASEMAINT";
$GLOBALS['gDefaultAjaxTimeout'] = 600000;
require_once "shared/startup.inc";

class DatabaseMaintenancePage extends Page {

	var $iColumnDefinitions = array();
	var $iColumnNames = array();
	var $iTableColumns = array();
	var $iTables = array();

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn("checked");
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete","add"));
		}
	}

	function massageUrlParameters() {
		$resultSet = executeQuery("select database_definition_id from database_definitions where database_name = ?",$GLOBALS['gPrimaryDatabase']->getName());
        if ($row = getNextRow($resultSet)) {
            $_GET['url_subpage'] = $_GET['url_page'];
            $_GET['url_page'] = "show";
            $_GET['primary_id'] = $row['database_definition_id'];
        }
	}

	function internalCSS() {
		?>
        <style>
            #results_table {
                width: 100%;
            }

            .results {
                width: 100%;
                height: 600px;
                font-family: "Courier New";
                font-size: .75rem;
            }

            #_script_part_1, #_script_part_2 {
                display: none;
            }
        </style>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                setTimeout(function () {
                    $("#check_integrity").data("no_statistics",true).trigger("click");
                }, 300);
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            disableButtons($("#update"));
            $("#results_1").prop("readonly", true);
            $(document).on("tap click", "#check_integrity", function (event) {
                $(".results").val("");
                if (!empty($("#primary_id").val())) {
                    disableButtons($("#check_integrity").add("#sql_scripts").add("#alter_script").add("#update"));
                    const getStatistics = (empty($(this).data("no_statistics")));
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_integrity&database_definition_id=" + $("#primary_id").val() + "&get_statistics=" + (getStatistics ? "true" : "") + (event.shiftKey ? "&products=true" : ""), function(returnArray) {
                        $("#results_1").prop("readonly", true).val(returnArray['results_1']).show();
                        $("#results_2").hide();
                        $("#check_integrity").data("no_statistics",false);
                        enableButtons($("#check_integrity").add("#sql_scripts").add("#alter_script"));
                        if ("checked" in returnArray) {
                            $("#checked").val(returnArray['checked']);
                        }
                    });
                }
                return false;
            });
            $(document).on("tap click", "#sql_scripts", function () {
                if ($("#checked").val() !== "1") {
                    $("#results_1").val("Database must pass integrity check first");
                } else {
                    $("#_script_part_1").attr("src", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_script_part_1&database_definition_id=" + $("#primary_id").val());
                    $("#_script_part_2").attr("src", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_script_part_2&database_definition_id=" + $("#primary_id").val());
                }
                return false;
            });
            $(document).on("keydown", "#results_1", function (event) {
                enableButtons($("#update"));
                if ($("#results_1").prop("readonly")) {
                    $("#results_1").prop("readonly", false);
                    $("#results_1").val("");
                } else {
                    if (event.which == 13 && !empty($("#results_1").val()) && event.metaKey) {
                        $("#update").trigger("click");
                    }
                    if (event.which == 38 && event.metaKey) {
                        $("#results_1").val($("#last_script").val());
                    }
                }
            });
            $(document).on("tap click", "#update", function () {
                const encodedString = window.btoa($("#results_1").val());
                $("#last_script").val($("#results_1").val());
                if (!empty($("#primary_id").val())) {
                    disableButtons($("#check_integrity").add("#sql_scripts").add("#alter_script").add("#update"));
                    $("#results_1").prop("readonly", true);
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update&database_definition_id=" + $("#primary_id").val(), {alter_script: encodedString}, function(returnArray) {
                        $("#results_1").prop("readonly", false).val(returnArray['results_1']).show();
                        $("#results_2").hide();
                        enableButtons($("#check_integrity").add("#sql_scripts").add("#alter_script"));
                    });
                }
                return false;
            });
            $(document).on("tap click", "#alter_script", function () {
                if (!empty($("#primary_id").val())) {
                    disableButtons($("#check_integrity").add("#sql_scripts").add("#alter_script").add("#update"));
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=alter_script&database_definition_id=" + $("#primary_id").val(), function(returnArray) {
                        $(".results").val("");
                        $("#results_1").prop("readonly", true).val(returnArray['results_1'].trim()).show();
                        $("#results_2").hide();
                        enableButtons($("#check_integrity").add("#sql_scripts").add("#alter_script"));
                        if (!("not_checked" in returnArray)) {
                            enableButtons($("#update"));
                        }
                        $("#results_1").prop("readonly", false);
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case ("update");
				$databaseName = getFieldFromId("database_name", "database_definitions", "database_definition_id", $_GET['database_definition_id'], "checked = 1");
				if (empty($databaseName)) {
					$returnArray['results_1'] = "Update cannot be run: Database must pass integrity check first";
					$returnArray['results_2'] = "";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select SCHEMA_NAME from information_schema.schemata where schema_name = ?", $databaseName);
				if (!$row = getNextRow($resultSet)) {
					$returnArray['results_1'] = "Database '" . getFieldFromId("database_name", "database_definitions", "database_definition_id", $_GET['database_definition_id']) . "' does not exist";
					$returnArray['results_2'] = "";
					ajaxResponse($returnArray);
					break;
				}
				$alterScript = trim(base64_decode($_POST['alter_script']));
				if (substr($alterScript, -1) != ";") {
					$alterScript .= ";";
				}
				$alterResults = array();
				$alterResults[] = "ALTER SCRIPT RUN:";
				$alterResults[] = "";
				$logResults = "";
				$alterCommand = "";
				$delimiter = ";";
				foreach (getContentLines($alterScript) as $alterLine) {
					$alterResults[] = $alterLine;
					if (!startsWith($alterLine, "delimiter") && $alterLine != $delimiter) {
						$alterCommand .= "\n" . $alterLine;
					}
					if ($alterLine == $delimiter || substr($alterCommand, -1 * strlen($delimiter)) == $delimiter) {
						$resultSet = executeQuery($alterCommand);
						if (!empty($resultSet['sql_error'])) {
							$alterResults[] = $resultSet['sql_error'];
							$logResults .= $alterCommand . "\n";
							$logResults .= $resultSet['sql_error'] . "\n";
							$returnArray['error_message'] = getLanguageText("An error occurred");
						} else {
							$firstWord = strtolower(trim(explode(" ", $alterCommand)[0]));
							switch ($firstWord) {
								case "delete":
									$alterResults[] = $resultSet['affected_rows'] . " row" . ($resultSet['affected_rows'] == 1 ? "" : "s") . " deleted";
									break;
								case "update":
									$alterResults[] = $resultSet['affected_rows'] . " row" . ($resultSet['affected_rows'] == 1 ? "" : "s") . " updated";
									break;
								case "insert":
									$alterResults[] = $resultSet['affected_rows'] . " row" . ($resultSet['affected_rows'] == 1 ? "" : "s") . " inserted";
									break;
								case "select";
									$fieldValues = array();
									$headers = array();
									while ($row = getNextRow($resultSet)) {
										$thisRow = array();
										$headerIndex = 0;
										foreach ($row as $fieldName => $fieldData) {
											if (is_numeric($fieldName)) {
												continue;
											}
											$thisRow[] = $fieldData;
											if (empty($fieldValues)) {
												$headers[] = array("label" => $fieldName, "length" => max(strlen($fieldName), strlen($fieldData)));
											} else {
												$headers[$headerIndex]['length'] = max($headers[$headerIndex]['length'], strlen($fieldData));
											}
											$headerIndex++;
										}
										$fieldValues[] = $thisRow;
										if (count($fieldValues) >= 1000 && strpos($alterCommand, "limit") === false) {
											break;
										}
									}
									if (!empty($headers)) {
										$rowlength = 1;
										foreach ($headers as $thisHeader) {
											$thisLine = " " . str_pad($thisHeader['label'], $thisHeader['length'], " ") . " |";
											$rowlength += strlen($thisLine);
										}
										$separatorLine = str_repeat("-", $rowlength);
										$alterResults[] = $separatorLine;
										$thisResult = "|";
										foreach ($headers as $thisHeader) {
											$thisLine = " " . str_pad($thisHeader['label'], $thisHeader['length'], " ") . " |";
											$thisResult .= $thisLine;
										}
										$alterResults[] = $thisResult;
										$alterResults[] = $separatorLine;
										foreach ($fieldValues as $thisRow) {
											$thisResult = "|";
											foreach ($thisRow as $index => $fieldValue) {
												$thisResult .= " " . str_pad($fieldValue, $headers[$index]['length'], " ") . " |";
											}
											$alterResults[] = $thisResult;
										}
										$alterResults[] = $separatorLine;
									}
									if (count($fieldValues) < $resultSet['row_count']) {
										$alterResults[] = count($fieldValues) . " rows of " . $resultSet['row_count'] . " displayed";
									} else {
										$alterResults[] = count($fieldValues) . " row" . (count($fieldValues) == 1 ? "" : "s");
									}
									break;
								default:
									$alterResults[] = "Successful!";
							}
						}
						$alterResults[] = "";
						$alterCommand = "";
					}
					if (startsWith($alterLine, "delimiter")) {
						$delimiter = substr($alterLine, strlen("delimiter "));
					}
				}
				executeQuery("insert into database_alter_log (database_definition_id,log_date,user_id,alter_script,results) " .
					"values (?,now(),?,?,?)", $_GET['database_definition_id'], $GLOBALS['gUserId'], $alterScript, $logResults);
				$returnArray['results_1'] = implode("\n", $alterResults) . "\n";
				$returnArray['results_2'] = "";
				ajaxResponse($returnArray);
				break;
			case ("alter_script"):
				$databaseName = getFieldFromId("database_name", "database_definitions", "database_definition_id", $_GET['database_definition_id'], "checked = 1");
				if (empty($databaseName)) {
					$returnArray['results_1'] = "Alter script cannot be generated: Database must pass integrity check first";
					$returnArray['results_2'] = "";
					$returnArray['not_checked'] = true;
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select SCHEMA_NAME from information_schema.schemata where schema_name = ?", $databaseName);
				if (!$row = getNextRow($resultSet)) {
					$returnArray['results_1'] = "Database '" . $databaseName . "' does not exist";
					$returnArray['results_2'] = "";
					ajaxResponse($returnArray);
					break;
				}
				$alterScript = Database::generateAlterScript();
                $alterScriptLines = $alterScript['alter_script'];

				$returnArray['results_1'] = implode("\n", $alterScriptLines) . "\n";
				$returnArray['results_2'] = "";
				ajaxResponse($returnArray);
				break;
			case ("get_script_part_1"):
			case ("get_script_part_2"):
				if (empty($this->iColumnDefinitions)) {
					$resultSet = executeQuery("select * from column_definitions");
					while ($row = getNextRow($resultSet)) {
						$this->iColumnDefinitions[$row['column_definition_id']] = $row;
						$this->iColumnNames[$row['column_name']] = $row['column_definition_id'];
					}
				}
				if (empty($this->iTableColumns)) {
					$resultSet = executeQuery("select * from table_columns");
					while ($row = getNextRow($resultSet)) {
						$this->iTableColumns[$row['table_column_id']] = $row;
					}
				}
				if (empty($this->iTables)) {
					$resultSet = executeQuery("select * from tables");
					while ($row = getNextRow($resultSet)) {
						$this->iTables[$row['table_id']] = $row;
					}
				}
				$databaseName = getFieldFromId("database_name", "database_definitions", "database_definition_id", $_GET['database_definition_id'], "checked = 1");
				if (empty($databaseName)) {
					$integrityArray = Database::checkDatabaseIntegrity();
				}
				$databaseName = getFieldFromId("database_name", "database_definitions", "database_definition_id", $_GET['database_definition_id'], "checked = 1");
				if (empty($databaseName)) {
					exit;
				}
				$sqlCode1 = array();
				$sqlCode2 = array();
				$resultSet = executeQuery("select * from database_definitions where database_definition_id = ?", $_GET['database_definition_id']);
				if (!$databaseRow = getNextRow($resultSet)) {
					exit;
				}
				$sqlCode1[] = "drop database if exists " . $databaseRow['database_name'] . ";";
				$sqlCode1[] = "SET NAMES utf8mb4;";
				$sqlCode1[] = "create database " . $databaseRow['database_name'] . " character set utf8mb4 collate utf8mb4_unicode_ci;";
				$sqlCode1[] = $sqlCode2[] = "use " . $databaseRow['database_name'] . ";";

				$tableSet = executeQuery("select * from tables where table_view = 0 and database_definition_id = ? order by table_name", $_GET['database_definition_id']);
				$sqlCode1[] = "";
				$sqlCode1[] = "-- Creating " . $tableSet['row_count'] . " table" . ($tableSet['row_count'] == 1 ? "" : "s");
				$sqlCode1[] = "";

				$tableCounts = array();
				$countSet = executeQuery("select table_id,count(*) from table_columns group by table_id");
				while ($countRow = getNextRow($countSet)) {
					$tableCounts[$countRow['table_id']] = $countRow['count(*)'];
				}
				$indexCounts = array();
				$countSet = executeQuery("select table_id,count(*) from table_columns where indexed = 1 group by table_id");
				while ($countRow = getNextRow($countSet)) {
					$indexCounts[$countRow['table_id']] = $countRow['count(*)'];
				}
				$fullTextCounts = array();
				$countSet = executeQuery("select table_id,count(*) from table_columns where full_text = 1 group by table_id");
				while ($countRow = getNextRow($countSet)) {
					$fullTextCounts[$countRow['table_id']] = $countRow['count(*)'];
				}
				$uniqueKeyCounts = array();
				$countSet = executeQuery("select table_id,count(*) from unique_keys group by table_id");
				while ($countRow = getNextRow($countSet)) {
					$uniqueKeyCounts[$countRow['table_id']] = $countRow['count(*)'];
				}
				$foreignKeyCounts = array();
				$countSet = executeQuery("select table_id,count(*) from foreign_keys join table_columns using (table_column_id) group by table_id");
				while ($countRow = getNextRow($countSet)) {
					$foreignKeyCounts[$countRow['table_id']] = $countRow['count(*)'];
				}
				while ($tableRow = getNextRow($tableSet)) {
					$columnCount = $tableCounts[$tableRow['table_id']] ?: 0;
					$indexCount = $indexCounts[$tableRow['table_id']] ?: 0;
					$fullTextCount = $fullTextCounts[$tableRow['table_id']] ?: 0;
					$uniqueKeyCount = $uniqueKeyCounts[$tableRow['table_id']] ?: 0;
					$foreignKeyCount = $foreignKeyCounts[$tableRow['table_id']] ?: 0;
					$sqlCode1[] = "-- " . $tableRow['table_name'] . " - " . $columnCount . " column" . ($columnCount == 1 ? "" : "s") .
						", " . $indexCount . " index" . ($indexCount == 1 ? "" : "es") . ", " . $uniqueKeyCount . " unique key" .
						($uniqueKeyCount == 1 ? "" : "s") . ", " . $foreignKeyCount . " foreign key" . ($foreignKeyCount == 1 ? "" : "s");
					$sqlCode1[] = "drop table if exists " . $tableRow['table_name'] . ";";
					$sqlCode1[] = "CREATE TABLE " . $tableRow['table_name'] . " (";

					if ($fullTextCount > 0) {
						$sqlCode2[] = "";
						$sqlCode2[] = "-- Full Text indexes for " . $tableRow['table_name'];
					}

					$resultSet = executeQuery("select * from column_definitions,table_columns where column_definitions.column_definition_id = table_columns.column_definition_id and table_id = ? order by sequence_number", $tableRow['table_id']);
					$primaryKey = "";
					$indexes = array();
					while ($row = getNextRow($resultSet)) {
						if (!empty($row['primary_table_key'])) {
							$primaryKey = $row['column_name'];
						} else if (!empty($row['indexed'])) {
							$indexes[] = $row['column_name'];
						}
						$sqlCode1[] = "\t" . $row['column_name'] . " " . $row['column_type'] .
							(empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")") . (empty($row['not_null']) ? "" : " NOT NULL") .
							(strlen($row['default_value']) == 0 || ($row['default_value'] == "now" && $row['column_type'] == "date") ? "" : " default " . ($row['default_value'] == "now" ? "current_timestamp" : (is_numeric($row['default_value']) ? $row['default_value'] : "'" . $row['default_value'] . "'"))) .
							($row['column_name'] == $primaryKey ? " auto_increment" : "") . ",";
						if ($row['full_text']) {
							$sqlCode2[] = "CREATE FULLTEXT INDEX ft_" . md5($tableRow['table_name'] . "_" . $row['column_name']) . " ON " . $tableRow['table_name'] . "(" . $row['column_name'] . ");";
						}
					}
					$sqlCode1[] = "\tPRIMARY KEY(" . $primaryKey . ")";
					$sqlCode1[] = ") engine=innoDB;";
					$resultSet = executeQuery("select * from unique_keys where table_id = ? order by unique_key_id", $tableRow['table_id']);
					$keysDone = array();
					while ($row = getNextRow($resultSet)) {
						$uniqueKeyName = $tableRow['table_name'];
						$uniqueKey = "";
						$resultSet1 = executeQuery("select * from unique_key_columns where unique_key_id = ? order by unique_key_column_id", $row['unique_key_id']);
						while ($row1 = getNextRow($resultSet1)) {
							if (!empty($uniqueKey)) {
								$uniqueKey .= ",";
							}
							$columnName = $this->iColumnDefinitions[$this->iTableColumns[$row1['table_column_id']]['column_definition_id']]['column_name'];
							$uniqueKey .= $columnName;
							$uniqueKeyName .= "_" . $columnName;
						}
						$uniqueKeyMD5Name = md5($uniqueKeyName);
						if (in_array($uniqueKeyMD5Name, $keysDone)) {
							continue;
						}
						$keysDone[] = $uniqueKeyMD5Name;
						$sqlCode1[] = "CREATE UNIQUE INDEX uk_" . $uniqueKeyMD5Name . " on " . $tableRow['table_name'] . "(" . $uniqueKey . ");";
					}
					foreach ($indexes as $columnName) {
						$columnType = $this->iColumnDefinitions[$this->iColumnNames[$columnName]]['column_type'];
						$sqlCode1[] = "CREATE " . ($columnType == "point" ? "SPATIAL " : "") . "INDEX i_" . md5($tableRow['table_name'] . "_" . $columnName) . " ON " . $tableRow['table_name'] . "(" . $columnName . ($columnType == "text" || $columnType == "mediumtext" ? "(20)" : "") . ");";
					}
					$sqlCode1[] = "";

					if ($foreignKeyCount > 0) {
						$sqlCode2[] = "";
						$sqlCode2[] = "-- Foreign Keys for " . $tableRow['table_name'];
					}

					$resultSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = " .
						"(select column_definition_id from table_columns where table_column_id = foreign_keys.table_column_id)) column_name " .
						"from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?) order by column_name", $tableRow['table_id']);
					while ($row = getNextRow($resultSet)) {
						$columnName = $this->iColumnDefinitions[$this->iTableColumns[$row['table_column_id']]['column_definition_id']]['column_name'];
						$referencedTableName = $this->iTables[$this->iTableColumns[$row['referenced_table_column_id']]['table_id']]['table_name'];
						$referencedColumnName = $this->iColumnDefinitions[$this->iTableColumns[$row['referenced_table_column_id']]['column_definition_id']]['column_name'];
						$sqlCode2[] = "ALTER TABLE " . $tableRow['table_name'] . " ADD CONSTRAINT fk_" . md5($tableRow['table_name'] . "_" . $columnName) .
							" FOREIGN KEY (" . $columnName . ") REFERENCES " . $referencedTableName . "(" . $referencedColumnName . ");";
					}
				}

				# create views

				$resultSet = executeQuery("select * from tables where table_view = 1 and database_definition_id = ? and custom_definition = 0", $_GET['database_definition_id']);
				if ($resultSet['row_count'] > 0) {
					$sqlCode2[] = "";
					$sqlCode2[] = "-- Create Views";
				}
				while ($row = getNextRow($resultSet)) {
					$tableIdList = array();
					$tableSet = executeQuery("select *,(select table_name from tables where table_id = view_tables.referenced_table_id) table_name from view_tables where table_id = ? order by sequence_number", $row['table_id']);
					while ($tableRow = getNextRow($tableSet)) {
						$tableIdList[] = $tableRow;
					}
					$lastTableId = "";
					$lastTableName = "";
					$columnList = "";
					$columnSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = (select column_definition_id from table_columns where table_column_id = view_columns.table_column_id)) column_name," .
						"(select table_name from tables where table_id = (select table_id from table_columns where table_column_id = view_columns.table_column_id)) table_name from view_columns where table_id = ? order by sequence_number", $row['table_id']);
					while ($columnRow = getNextRow($columnSet)) {
						$columnList .= (empty($columnList) ? "" : ",") . $columnRow['table_name'] . "." . $columnRow['column_name'];
					}
					if (empty($columnList)) {
						$columnList = "*";
					}
					if (empty($row['full_query_text'])) {
						$query = "create view " . $row['table_name'] . " as select " . $columnList . " from";
						foreach ($tableIdList as $tableInfoByName) {
							if (empty($lastTableId)) {
								$query .= " " . $tableInfoByName['table_name'];
								$lastTableId = $tableInfoByName['referenced_table_id'];
								$lastTableName = $tableInfoByName['table_name'];
								$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and primary_table_key = 1", $tableInfoByName['referenced_table_id']);
								if ($columnRow = getNextRow($columnSet)) {
									$lastPrimaryKey = $columnRow['column_name'];
								}
							} else {
								$lastTableForeignKey = "";
								$thisTableForeignKey = "";
								$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and primary_table_key = 1", $tableInfoByName['referenced_table_id']);
								if ($columnRow = getNextRow($columnSet)) {
									$thisPrimaryKey = $columnRow['column_name'];
								}
								# check for foreign key from this table to previous primary key
								# check for foreign key from previous table to this primary key

								$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and table_column_id in (select referenced_table_column_id from foreign_keys where " .
									"table_column_id in (select table_column_id from table_columns where table_id = ?)) order by sequence_number", $lastTableId, $tableInfoByName['referenced_table_id']);
								if ($columnRow = getNextRow($columnSet)) {
									$lastTableForeignKey = $lastPrimaryKey;
									$thisTableForeignKey = $columnRow['column_name'];
								}
								if (empty($lastTableForeignKey) || empty($thisTableForeignKey)) {
									$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and table_column_id in (select referenced_table_column_id from foreign_keys where " .
										"table_column_id in (select table_column_id from table_columns where table_id = ?)) order by sequence_number", $tableInfoByName['referenced_table_id'], $lastTableId);
									if ($columnRow = getNextRow($columnSet)) {
										$lastTableForeignKey = $columnRow['column_name'];
										$thisTableForeignKey = $thisPrimaryKey;
									}
								}
								if (empty($lastTableForeignKey) || empty($thisTableForeignKey)) {
									$results[] = "For view '" . $row['table_name'] . "', no foreign key between " . $lastTableName . " and " . $tableInfoByName['table_name'];
									$lastTableId = $tableInfoByName['referenced_table_id'];
									$lastTableName = $tableInfoByName['table_name'];
									continue;
								}
								$query .= " join " . $tableInfoByName['table_name'] . ($lastTableForeignKey == $thisTableForeignKey ? " using (" . $thisTableForeignKey . ")" : " on (" . $lastTableName . "." . $lastTableForeignKey . " = " . $tableInfoByName['table_name'] . "." . $thisTableForeignKey . ")");
							}
						}
						if (!empty($row['query_text'])) {
							$query .= " where " . $row['query_text'];
						}
					} else {
						$query = "create view " . $row['table_name'] . " as " . $row['full_query_text'];
					}
					$sqlCode2[] = "";
					$sqlCode2[] = "drop view if exists " . $row['table_name'] . ";";
					$sqlCode2[] = $query . ";";
					$sqlCode2[] = "update tables set query_string = " . makeParameter($query) . " where table_name = " . makeParameter($row['table_name']) . ";";
				}

				$resultSet = executeQuery("select * from stored_procedures where inactive = 0 and database_definition_id = ? order by sort_order", $_GET['database_definition_id']);
				if ($resultSet['row_count'] > 0) {
					$sqlCode2[] = "";
					$sqlCode2[] = "-- Create Stored Procedures";
				}
				while ($row = getNextRow($resultSet)) {
					$sqlCode1[] = "DELIMITER $$";
					$sqlCode1[] = "CREATE PROCEDURE " . $row['stored_procedure_name'] . "(" . $row['parameters'] . ")";
					$sqlLines = getContentLines($row['content']);
					foreach ($sqlLines as $thisLine) {
						if (substr($thisLine, 0, 2) != "--") {
							$sqlCode1[] = $thisLine;
						}
					}
					$sqlCode1[] = "$$";
					$sqlCode1[] = "DELIMITER ;";
				}

				$partNumber = ($_GET['url_action'] == "get_script_part_1" ? "1" : "2");
				$content = ($partNumber == 1 ? trim(implode("\n", $sqlCode1) . "\n" . $databaseRow['additional_script'] . "\n") : trim(implode("\n", $sqlCode2) . "\n"));
				header("Content-Type: text/sql");
				header("Content-Disposition: attachment; filename=\"" . $databaseRow['database_name'] . "p" . $partNumber . ".sql\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				echo $content;
				exit;
			case ("check_integrity"):
                $returnOutput = array();
				$returnOutput[] = "PHP Version: " . phpversion();
				$returnOutput[] = "Time Checked: " . date("g:i:s");
				if ($GLOBALS['gApcuEnabled']) {
					$returnOutput[] = "APCU Caching is enabled.";
				} else {
					$returnOutput[] = "APCU Caching is NOT enabled. Consider enabling it.";
				}

				if ($_GET['products']) {
					$resultSet = executeQuery("select count(*) from products where inactive = 0 and client_id in (select client_id from clients where inactive = 0) and client_id not in (select client_id from client_preferences where preference_value = 'true' and " .
						"preference_id = (select preference_id from preferences where preference_code = 'IGNORE_STORED_PRICES'))");
					$productCount = 0;
					if ($row = getNextRow($resultSet)) {
						$productCount = $row['count(*)'];
					}
					if ($productCount > 0) {
						$resultSet = executeQuery("select count(*) from products where product_id in (select product_id from product_sale_prices where expiration_time > now()) and " .
							"inactive = 0 and client_id in (select client_id from clients where inactive = 0) and client_id not in (select client_id from client_preferences where preference_value = 'true' and " .
							"preference_id = (select preference_id from preferences where preference_code = 'IGNORE_STORED_PRICES'))");
						$storedCount = 0;
						if ($row = getNextRow($resultSet)) {
							$storedCount = $row['count(*)'];
						}
						$returnOutput[] = $productCount . " products on this server, " . $storedCount . " with stored prices (" . round($storedCount * 100 / $productCount, 1) . "%)";
						$returnOutput[] = "";
					}
				}
				$returnArray = Database::checkDatabaseIntegrity((!empty($_GET['get_statistics'])));

				$returnArray['checked'] = getFieldFromId("checked", "database_definitions", "database_definition_id", $_GET['database_definition_id']);
				$returnArray['results_1'] = implode("\n", array_merge($returnArray['output'], $returnArray['errors']));
				ajaxResponse($returnArray);
				break;
		}
	}

	function hiddenElements() {
		?>
        <iframe id="_script_part_1" name="_script_part_1"></iframe>
        <iframe id="_script_part_2" name="_script_part_2"></iframe>
		<?php
	}
}

$pageObject = new DatabaseMaintenancePage("database_definitions");
$pageObject->displayPage();
