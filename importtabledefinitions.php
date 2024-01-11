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

$GLOBALS['gPageCode'] = "IMPORTTABLEDEFINITIONS";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_tables":
				$definitionArray = json_decode($_POST['table_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				ob_start();
				?>
                <h2>Verify all changes and click Update</h2>
				<?php
				foreach ($definitionArray as $newTableInfo) {
					if (empty($newTableInfo['table_name'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					?>
                    <h3><?= $newTableInfo['table_name'] ?></h3>
					<?php
					if (!empty($newTableInfo['subsystem'])) {
						$subsystemId = getFieldFromId("subsystem_id", "subsystems", "description", $newTableInfo['subsystem']);
						if (empty($subsystemId)) {
							?>
                            <p class="error-message">Subsystem does not exist: <?= $newTableInfo['subsystem'] ?>.</p>
							<?php
							$errorFound = true;
							continue;
						}
					}
					$existingTableRow = getRowFromId("tables", "table_name", $newTableInfo['table_name']);
					if (empty($existingTableRow)) {
						?>
                        <p class="info-message">Table does not exist. Will be created.</p>
						<?php
					} else {
						if ($existingTableRow['description'] != $newTableInfo['description']) {
							?>
                            <p class="info-message">Description will be changed.</p>
							<?php
						}
						if ($existingTableRow['detailed_description'] != $newTableInfo['detailed_description']) {
							?>
                            <p class="info-message">Detailed Description will be changed.</p>
							<?php
						}
						if ($existingTableRow['subsystem_id'] != $subsystemId) {
							?>
                            <p class="info-message">Subsystem will be changed to <?= $newTableInfo['subsystem'] ?>.</p>
							<?php
						}
					}
					if (!is_array($newTableInfo['columns']) || empty($newTableInfo['columns'])) {
						?>
                        <p class="error-message">Table columns not defined.</p>
						<?php
						$errorFound = true;
						continue;
					}
					$existingColumns = array();
					if (!empty($existingTableRow['table_id'])) {
						$columnSet = executeQuery("select * from column_definitions join table_columns using (column_definition_id) where table_id = ? order by sequence_number", $existingTableRow['table_id']);
						$sequenceNumber = 0;
						while ($columnRow = getNextRow($columnSet)) {
							$sequenceNumber++;
							$columnRow['sequence_number'] = $sequenceNumber;
							$existingColumns[$columnRow['column_name']] = $columnRow;
						}
					}
					$sequenceNumber = 0;
					$newColumnList = array();
					foreach ($newTableInfo['columns'] as $newColumnInfo) {
						$newColumnList[] = $newColumnInfo['column_name'];
						$columnDefinition = getRowFromId("column_definitions", "column_name", $newColumnInfo['column_name']);
						if (empty($columnDefinition)) {
							?>
                            <p class="info-message">Column '<?= $newColumnInfo['column_name'] ?>' will be created and added to the table.</p>
							<?php
							continue;
						} else if ($columnDefinition['column_type'] != $newColumnInfo['column_type'] || $columnDefinition['data_size'] != $newColumnInfo['data_size'] ||
							$columnDefinition['decimal_places'] != $newColumnInfo['decimal_places'] || $columnDefinition['data_format'] != $newColumnInfo['data_format'] ||
							$columnDefinition['code_value'] != $newColumnInfo['code_value'] || $columnDefinition['letter_case'] != $newColumnInfo['letter_case'] ||
							$columnDefinition['minimum_value'] != $newColumnInfo['minimum_value'] || $columnDefinition['maximum_value'] != $newColumnInfo['maximum_value']) {
							?>
                            <p class="error-message">Column '<?= $newColumnInfo['column_name'] ?>' has been redefined. This is not permitted.</p>
							<?php
							$errorFound = true;
						}
						if (!array_key_exists($columnDefinition['column_name'], $existingColumns)) {
							?>
                            <p class="info-message">Column '<?= $newColumnInfo['column_name'] ?>' will be added to the table.</p>
							<?php
							continue;
						}
						$sequenceNumber++;
						if ($sequenceNumber == 1) {
							if ($existingColumns[$newColumnInfo['column_name']]['sequence_number'] != "1") {
								?>
                                <p class="error-message">Primary key has changed. This is not permitted.</p>
								<?php
								$errorFound = true;
							}
						} else if ($existingColumns[$newColumnInfo['column_name']]['sequence_number'] != $sequenceNumber) {
							?>
                            <p class="info-message">Column '<?= $newColumnInfo['column_name'] ?>' is being moved.</p>
							<?php
						}
						if ($newColumnInfo['description'] != $existingColumns[$newColumnInfo['column_name']]['description']) {
							?>
                            <p class="info-message">Column '<?= $newColumnInfo['column_name'] ?>' description is being changed to '<?= $newColumnInfo['description'] ?>'.</p>
							<?php
						}
						if ($newColumnInfo['detailed_description'] != $existingColumns[$newColumnInfo['column_name']]['detailed_description']) {
							?>
                            <p class="info-message">Column '<?= $newColumnInfo['column_name'] ?>' detailed description is being changed.</p>
							<?php
						}
						if ($newColumnInfo['indexed'] != $existingColumns[$newColumnInfo['column_name']]['indexed']) {
							?>
                            <p class="info-message">Index for column '<?= $newColumnInfo['column_name'] ?>' is being <?= ($newColumnInfo['indexed'] ? "added" : "removed") ?>.</p>
							<?php
						}
						if ($newColumnInfo['full_text'] != $existingColumns[$newColumnInfo['column_name']]['full_text']) {
							?>
                            <p class="info-message">Full Text Index for column '<?= $newColumnInfo['column_name'] ?>' is being <?= ($newColumnInfo['full_text'] ? "added" : "removed") ?>.</p>
							<?php
						}
						if ($newColumnInfo['not_null'] != $existingColumns[$newColumnInfo['column_name']]['not_null']) {
							?>
                            <p class="info-message">Column '<?= $newColumnInfo['column_name'] ?>' is being changed to<?= ($newColumnInfo['not_null'] ? "" : " not") ?> required.</p>
							<?php
						}
						if ($newColumnInfo['default_value'] != $existingColumns[$newColumnInfo['column_name']]['default_value']) {
							?>
                            <p class="info-message">Default value for column '<?= $newColumnInfo['column_name'] ?>' is being changed to '<?= $newColumnInfo['default_value'] ?>'.</p>
							<?php
						}
					}
					foreach ($existingColumns as $existingColumnInfo) {
						if (!in_array($existingColumnInfo['column_name'], $newColumnList)) {
							?>
                            <p class="info-message">Column '<?= $existingColumnInfo['column_name'] ?>' will be removed.</p>
							<?php
						}
					}
					$existingUniqueKeys = array();
					if (!empty($existingTableRow['table_id'])) {
						$columnSet = executeQuery("select *,(select group_concat(column_name) from unique_key_columns join table_columns using (table_column_id) " .
							"join column_definitions using (column_definition_id) where unique_key_id = unique_keys.unique_key_id group by unique_key_id " .
							"order by unique_key_column_id) column_names from unique_keys where table_id = ?", $existingTableRow['table_id']);
						while ($columnRow = getNextRow($columnSet)) {
							$existingUniqueKeys[] = $columnRow['column_names'];
						}
					}
					$newUniqueKeyList = array();
					foreach ($newTableInfo['unique_keys'] as $newUniqueKey) {
						$newUniqueKeyList[] = $newUniqueKey['column_names'];
						if (!in_array($newUniqueKey['column_names'], $existingUniqueKeys)) {
							?>
                            <p class="info-message">Unique key for '<?= $newUniqueKey['column_names'] ?>' will be created.</p>
							<?php
						}
					}
					foreach ($existingUniqueKeys as $existingUniqueKey) {
						if (!in_array($existingUniqueKey, $newUniqueKeyList)) {
							?>
                            <p class="info-message">Unique key for '<?= $existingUniqueKey ?>' will be removed.</p>
							<?php
						}
					}
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['table_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_tables":
				$databaseDefinitionId = getFieldFromId("database_definition_id", "database_definitions", "database_name", $GLOBALS['gPrimaryDatabase']->getName());
				$hashValue = md5($_POST['table_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['table_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();
				foreach ($definitionArray as $newTableInfo) {
					?>
                    <p class="info-message">Updating Table '<?= $newTableInfo['table_name'] ?>'.</p>
					<?php
					$subsystemId = "";
					if (!empty($newTableInfo['subsystem'])) {
						$subsystemId = getFieldFromId("subsystem_id", "subsystems", "description", $newTableInfo['subsystem']);
						if (empty($subsystemId)) {
							?>
                            <p class="error-message">Subsystem does not exist: <?= $newTableInfo['subsystem'] ?>.</p>
							<?php
							$errorFound = true;
							break;
						}
					}
					$existingTableRow = getRowFromId("tables", "table_name", $newTableInfo['table_name']);
					if (empty($existingTableRow)) {
						$updateSet = executeQuery("insert into tables (database_definition_id,table_name,description,subsystem_id,detailed_description) values (?,?,?,?,?)",
							$databaseDefinitionId, $newTableInfo['table_name'], $newTableInfo['description'], $subsystemId, $newTableInfo['detailed_description']);
						if (!empty($updateSet['sql_error'])) {
							echo "<p>" . $updateSet['sql_error'] . "</p>";
							$errorFound = true;
							break;
						}
						$existingTableRow = getRowFromId("tables", "table_name", $newTableInfo['table_name']);
					} else {
						$tableQuery = "";
						$tableParameters = array();
						if ($existingTableRow['description'] != $newTableInfo['description']) {
							$tableQuery .= (empty($tableQuery) ? "" : ",") . "description = ?";
							$tableParameters[] = $newTableInfo['description'];
						}
						if ($existingTableRow['detailed_description'] != $newTableInfo['detailed_description']) {
							$tableQuery .= (empty($tableQuery) ? "" : ",") . "detailed_description = ?";
							$tableParameters[] = $newTableInfo['detailed_description'];
						}
						if ($existingTableRow['subsystem_id'] != $subsystemId) {
							$tableQuery .= (empty($tableQuery) ? "" : ",") . "subsystem_id = ?";
							$tableParameters[] = $subsystemId;
						}
						if (!empty($tableQuery)) {
							$updateSet = executeQuery($tableQuery, $tableParameters);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break;
							}
						}
					}
					if (!is_array($newTableInfo['columns']) || empty($newTableInfo['columns'])) {
						?>
                        <p class="error-message">Table columns not defined.</p>
						<?php
						$errorFound = true;
						break;
					}
					$existingColumns = array();
					if (!empty($existingTableRow['table_id'])) {
						$columnSet = executeQuery("select * from column_definitions join table_columns using (column_definition_id) where table_id = ? order by sequence_number", $existingTableRow['table_id']);
						$sequenceNumber = 0;
						while ($columnRow = getNextRow($columnSet)) {
							$sequenceNumber++;
							$columnRow['sequence_number'] = $sequenceNumber;
							$existingColumns[$columnRow['column_name']] = $columnRow;
						}
					}
					$sequenceNumber = 0;
					$newColumnList = array();
					foreach ($newTableInfo['columns'] as $newColumnInfo) {
						$newColumnList[] = $newColumnInfo['column_name'];
						$sequenceNumber++;
						$columnDefinition = getRowFromId("column_definitions", "column_name", $newColumnInfo['column_name']);
						if (empty($columnDefinition)) {
							$updateSet = executeQuery("insert into column_definitions (column_name,column_type,data_size,decimal_places,minimum_value,maximum_value,data_format,not_null,code_value,letter_case,default_value) values " .
								"(?,?,?,?,?, ?,?,?,?,?, ?)", $newColumnInfo['column_name'], $newColumnInfo['column_type'], $newColumnInfo['data_size'], $newColumnInfo['decimal_places'],
								$newColumnInfo['minimum_value'], $newColumnInfo['maximum_value'], $newColumnInfo['data_format'], $newColumnInfo['not_null'], $newColumnInfo['code_value'],
								$newColumnInfo['letter_case'], $newColumnInfo['default_value']);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
							$columnDefinition = getRowFromId("column_definitions", "column_name", $newColumnInfo['column_name']);
						} else {
							if ($columnDefinition['column_type'] != $newColumnInfo['column_type'] || $columnDefinition['data_size'] != $newColumnInfo['data_size'] ||
								$columnDefinition['decimal_places'] != $newColumnInfo['decimal_places'] || $columnDefinition['data_format'] != $newColumnInfo['data_format'] ||
								$columnDefinition['code_value'] != $newColumnInfo['code_value'] || $columnDefinition['letter_case'] != $newColumnInfo['letter_case'] ||
								$columnDefinition['minimum_value'] != $newColumnInfo['minimum_value'] || $columnDefinition['maximum_value'] != $newColumnInfo['maximum_value']) {
								?>
                                <p class="error-message">Column '<?= $newColumnInfo['column_name'] ?>' has been redefined. This is not permitted.</p>
								<?php
								$errorFound = true;
								break 2;
							}
							if ($sequenceNumber == 1) {
								if ($existingColumns[$newColumnInfo['column_name']]['sequence_number'] != "1") {
									?>
                                    <p class="error-message">Primary key has changed. This is not permitted.</p>
									<?php
									$errorFound = true;
									break 2;
								}
							}
						}
						if (!array_key_exists($columnDefinition['column_name'], $existingColumns)) {
							$updateSet = executeQuery("insert into table_columns (table_id,column_definition_id,description,detailed_description,sequence_number,primary_table_key,indexed,full_text,not_null,default_value) values " .
								"(?,?,?,?,?, ?,?,?,?,?)", $existingTableRow['table_id'], $columnDefinition['column_definition_id'], $newColumnInfo['description'], $newColumnInfo['detailed_description'],
								$sequenceNumber, $newColumnInfo['primary_table_key'], $newColumnInfo['indexed'], $newColumnInfo['full_text'], $newColumnInfo['not_null'], $newColumnInfo['default_value']);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
							$tableColumnId = $updateSet['insert_id'];
							if (substr($columnDefinition['column_name'], -3) == "_id") {
								if (array_key_exists($columnDefinition['column_name'], $newTableInfo['foreign_keys'])) {
									$foreignKeyDefinition = $newTableInfo['foreign_keys'][$columnDefinition['column_name']];
									$referencedTableId = getFieldFromId("table_id", "tables", "table_name", $foreignKeyDefinition['referenced_table_name']);
									$referencedColumnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $foreignKeyDefinition['referenced_column_name']);
									$referencedTableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $referencedTableId, "column_definition_id = ?", $referencedColumnDefinitionId);
									if (empty($referencedTableColumnId)) {
										?>
                                        <p class="error-message">Foreign Key referenced column not found: <?= $foreignKeyDefinition['referenced_table_name'] . "." . $foreignKeyDefinition['referenced_column_name'] ?>.</p>
										<?php
										$errorFound = true;
										break 2;
									}
									$updateSet = executeQuery("insert ignore into foreign_keys (table_column_id,referenced_table_column_id) values (?,?)", $tableColumnId, $referencedTableColumnId);
									if (!empty($updateSet['sql_error'])) {
										echo "<p>" . $updateSet['sql_error'] . "</p>";
										$errorFound = true;
										break 2;
									}
								}
							}
						} else {
							$updateSet = executeQuery("update table_columns set description = ?,detailed_description = ?,sequence_number = ?,indexed = ?,full_text = ?,not_null = ?," .
								"default_value = ? where table_column_id = ?", $newColumnInfo['description'], $newColumnInfo['detailed_description'], $sequenceNumber,
								$newColumnInfo['indexed'], $newColumnInfo['full_text'], $newColumnInfo['not_null'], $newColumnInfo['default_value'], $existingColumns[$columnDefinition['column_name']]['table_column_id']);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
						}
					}
					foreach ($existingColumns as $existingColumnInfo) {
						if (!in_array($existingColumnInfo['column_name'], $newColumnList)) {
							$updateSet = executeQuery("delete from table_columns where table_column_id = ?", $existingColumns[$columnDefinition['column_name']]['table_column_id']);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
						}
					}
					$existingUniqueKeys = array();
					if (!empty($existingTableRow['table_id'])) {
						$columnSet = executeQuery("select *,(select group_concat(column_name) from unique_key_columns join table_columns using (table_column_id) " .
							"join column_definitions using (column_definition_id) where unique_key_id = unique_keys.unique_key_id group by unique_key_id " .
							"order by unique_key_column_id) column_names from unique_keys where table_id = ?", $existingTableRow['table_id']);
						while ($columnRow = getNextRow($columnSet)) {
							$existingUniqueKeys[$columnRow['unique_key_id']] = $columnRow['column_names'];
						}
					}
					$newUniqueKeyList = array();
					foreach ($newTableInfo['unique_keys'] as $newUniqueKey) {
						$newUniqueKeyList[] = $newUniqueKey['column_names'];
						if (!in_array($newUniqueKey['column_names'], $existingUniqueKeys)) {
							$columnNames = explode(",", $newUniqueKey['column_names']);
							$updateSet = executeQuery("insert into unique_keys (table_id) values (?)", $existingTableRow['table_id']);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
							$uniqueKeyId = $updateSet['insert_id'];
							foreach ($columnNames as $columnName) {
								$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $existingTableRow['table_id'],
									"column_definition_id = (select column_definition_id from column_definitions where column_name = ?)", $columnName);
								if (empty($tableColumnId)) {
									?>
                                    <p class="error-message">Column '<?= $columnName ?>' in table '<?= $existingTableRow['table_name'] ?>' does not exist.</p>
									<?php
									break 3;
								}
								$updateSet = executeQuery("insert ignore into unique_key_columns (unique_key_id,table_column_id) values (?,?)", $uniqueKeyId, $tableColumnId);
								if (!empty($updateSet['sql_error'])) {
									echo "<p>" . $updateSet['sql_error'] . "</p>";
									$errorFound = true;
									break 3;
								}
							}
						}
					}
					foreach ($existingUniqueKeys as $uniqueKeyId => $existingUniqueKey) {
						if (!in_array($existingUniqueKey, $newUniqueKeyList)) {
							$updateSet = executeQuery("delete unique_key_columns where unique_key_id = ?", $uniqueKeyId);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
							$updateSet = executeQuery("delete unique_keys where unique_key_id = ?", $uniqueKeyId);
							if (!empty($updateSet['sql_error'])) {
								echo "<p>" . $updateSet['sql_error'] . "</p>";
								$errorFound = true;
								break 2;
							}
						}
					}
				}
				if ($errorFound) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#import_tables").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_tables", $("#_edit_form").serialize(), function(returnArray) {
                    if ("results" in returnArray) {
                        $("#import_tables").hide();
                        $("#update_tables").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_tables").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#table_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_tables").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_tables", $("#_edit_form").serialize(), function(returnArray) {
                    if ("results" in returnArray) {
                        $("#import_tables").hide();
                        $("#update_tables").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#table_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#table_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").click(function () {
                $("#reenter_json").hide();
                $("#update_tables").hide();
                $("#import_tables").show();
                $("#table_definitions").show();
                $("#results").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #update_tables {
                display: none;
            }

            #reenter_json {
                display: none;
            }

            #_edit_form button {
                margin-right: 20px;
            }

            #results {
                display: none;
            }

            #table_definitions label {
                display: block;
                margin: 10px 0;
            }

            #table_definitions_json {
                width: 1200px;
                height: 600px;
            }
        </style>
		<?php
	}

	function mainContent() {
		?>
        <form id="_edit_form">
            <input type="hidden" id="hash_value" name="hash_value">
            <p>
                <button id="import_tables">Import Tables</button>
                <button id="reenter_json">Re-enter JSON</button>
                <button id="update_tables">Update Database Structure</button>
            </p>
            <div id="table_definitions">
                <label>Table Definitions JSON</label>
                <textarea id="table_definitions_json" name="table_definitions_json"></textarea>
            </div>
            <div id="results">
            </div>
        </form>
		<?php
		return true;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
