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

$GLOBALS['gPageCode'] = "VIEWMAINT";
require_once "shared/startup.inc";

class ViewMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setAddUrl("viewadd.php");
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("table_id", "database_definition_id", "table_name", "description", "detailed_description", "subsystem_id"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("view_tables", "view_columns", "table_columns"));
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->setFilterWhere("table_view = 1");
		$this->iDataSource->addColumnControl("table_name", "form_label", "View Name");
		$this->iDataSource->addColumnControl("table_view", "default_value", "1");
		$this->iDataSource->addColumnControl("view_tables", "data_type", "custom");
		$this->iDataSource->addColumnControl("view_tables", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("view_tables", "links_table", "view_tables");
		$this->iDataSource->addColumnControl("view_tables", "control_table", "tables");
		$this->iDataSource->addColumnControl("view_tables", "form_label", "Select Tables");
		$this->iDataSource->addColumnControl("view_tables", "get_choices", "tableChoices");
		$this->iDataSource->addColumnControl("view_tables", "control_key", "referenced_table_id");
	}

	function tableChoices($showInactive = false) {
		$tableChoices = array();
		$resultSet = executeQuery("select table_id,table_name from tables where database_definition_id = (select database_definition_id from database_definitions where " .
			"database_name = ?) and table_view = 0 order by table_name", $GLOBALS['gPrimaryDatabase']->getName());
		while ($row = getNextRow($resultSet)) {
			$tableChoices[$row['table_id']] = array("key_value" => $row['table_id'], "description" => $row['table_name'], "inactive" => false);
		}
		freeResult($resultSet);
		return $tableChoices;
	}

	function internalCSS() {
		?>
        <style>
            #column_selector {
                margin: 20px 0 0 0;
            }

            #column_list li {
                margin-bottom: 5px;
            }

            .drag-strip {
                text-align: center;
                cursor: pointer;
            }

            .drag-strip img {
                margin: 0 5px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#view_data").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_data&table_name=" + $("#table_name").val() + "&database_definition_id=" + $("#database_definition_id").val(), function(returnArray) {
                    if ("table_data" in returnArray) {
                        $("#data_content").html(returnArray['table_data']);
                        $('#_data_dialog').dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                            width: 1000,
                            title: 'Data Sample',
                            buttons: {
                                Cancel: function (event) {
                                    $("#_data_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $("#view_tables").change(function () {
                if ($(this).val() != $("#table_id_list").val()) {
                    $("#table_id_list").val($(this).val());
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_columns&primary_id=" + $("#primary_id").val() + "&table_ids=" + $(this).val(), function(returnArray) {
                        if ("column_selector" in returnArray) {
                            $("#column_selector").html(returnArray['column_selector']);
                        }
                        $("#column_list").sortable({
                            update: function () {
                                changeSortOrder();
                            },
                            items: ".sortable-row",
                            handle: ".drag-strip"
                        });
                    });
                }
            });
            $(document).on("click", ".column-checkbox", function () {
                $(this).closest("tr").find(".column-check-value").val(($(this).prop("checked") ? "1" : "0"));
                changeSortOrder();
            });
            $("#column_filter").keyup(function () {
                var filterValue = $(this).val().toLowerCase();
                $("#column_list tr.sortable-row").each(function () {
                    if ($(this).text().toLowerCase().indexOf(filterValue) >= 0) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                if ("hide_data" in returnArray) {
                    $("#view_data").hide();
                } else {
                    $("#view_data").show();
                }
            }

            function changeSortOrder() {
                var sequenceNumber = 0;
                $("#column_list").find(".sortable-row").each(function () {
                    if ($(this).find(".column-check-value").val() == "1") {
                        sequenceNumber++;
                        $(this).find(".sequence-number").val(sequenceNumber);
                    }
                });
                var sortObjects = new Array();
                $("#column_list").find("tr.sortable-row").each(function () {
                    var thisId = $(this).attr("id");
                    var dataValue = $(this).find(".sequence-number").val();
                    sortObjects.push({"data_value": dataValue, "row_id": thisId})
                });
                var objectCount = 0;
                for (var i in sortObjects) {
                    objectCount++;
                }
                if (objectCount > 0) {
                    sortObjects.sort(function (a, b) {
                        if (a.data_value == b.data_value) {
                            return 0;
                        } else {
                            return ((a.data_value != "" && b.data_value != "" && a.data_value < b.data_value) || b.data_value == "" ? -1 : 1);
                        }
                    });
                    for (var i in sortObjects) {
                        $("#column_list").find("#" + sortObjects[i].row_id).appendTo("#column_list");
                    }
                }
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		if (empty($returnArray['query_string']['data_value'])) {
			$returnArray['hide_data'] = true;
		}
		$returnArray['table_id_list'] = array("data_value" => "");
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$restrictedAccess = getFieldFromId("restricted_access", "subsystems", "subsystem_id", $returnArray['subsystem_id']['data_value']);
			if (!empty($restrictedAccess)) {
				$userId = getFieldFromId("user_id", "subsystem_users", "subsystem_id", $returnArray['subsystem_id']['data_value'], "user_id = ?", $GLOBALS['gUserId']);
				if (empty($userId)) {
					$returnArray['_permission'] = array("data_value" => _READONLY);
				}
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_data":
				$tableId = getFieldFromId("table_id", "tables", "table_name", $_GET['table_name'], "database_definition_id = ?", $_GET['database_definition_id']);
				if (empty($tableId)) {
					$returnArray['error_message'] = "Invalid Table: " . $_GET['table_name'] . ":" . $_GET['database_definition_id'];
				} else {
					ob_start();
					$rowCount = 0;
					$resultSet = executeQuery("select count(*) from " . $_GET['table_name']);
					if ($row = getNextRow($resultSet)) {
						$rowCount = $row['count(*)'];
					}
					?>
                    <p><?= $rowCount ?> row<?= ($rowCount == 1 ? "" : "s") ?> found in table.</p>
                    <table class="grid-table">
						<?php
						$resultSet = executeQuery("select * from " . $_GET['table_name'] . " limit 50");
						$headerDisplayed = false;
						$columnDefinitions = array();
						while ($row = getNextRow($resultSet)) {
							if (!$headerDisplayed) {
								?>
                                <tr>
									<?php
									foreach ($row as $fieldName => $fieldData) {
										$columnDefinitionRow = getRowFromId("column_definitions", "column_name", $fieldName);
										$columnDefinitions[] = $columnDefinitionRow;
										?>
                                        <th><?= $columnDefinitionRow['column_name'] ?></th>
										<?php
									}
									?>
                                </tr>
								<?php
								$headerDisplayed = true;
							}
							?>
                            <tr>
								<?php
								foreach ($columnDefinitions as $columnRow) {
									$classNames = "";
									switch ($columnRow['column_type']) {
										case "longblob":
										case "mediumblob":
											$displayValue = "Raw Data";
											break;
										case "date":
											$displayValue = (empty($row[$columnRow['column_name']]) ? "" : date("m/d/Y", strtotime($row[$columnRow['column_name']])));
											break;
										case "datetime":
										case "timestamp":
											$displayValue = (empty($row[$columnRow['column_name']]) ? "" : date("m/d/Y g:i:sa", strtotime($row[$columnRow['column_name']])));
											break;
										case "float":
										case "int":
										case "bigint":
										case "decimal":
											$classNames = "align-right";
											$displayValue = $row[$columnRow['column_name']];
											break;
										case "tinyint":
											$classNames = "align-center";
											$displayValue = (empty($row[$columnRow['column_name']]) ? "no" : "YES");
											break;
										default:
											$displayValue = htmlText(getFirstPart($row[$columnRow['column_name']], 40));
											break;
									}
									?>
                                    <td class="<?= $classNames ?>"><?= $displayValue ?></td>
									<?php
								}
								?>
                            </tr>
							<?php
						}
						?>
                    </table>
					<?php
					$returnArray['table_data'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
			case "get_columns":
				$tableIds = explode(",", $_GET['table_ids']);
				$returnArray['column_selector'] = "<table class='grid-table' id='column_list'>";
				$columnNames = array();
				foreach ($tableIds as $tableId) {
					$tableName = getFieldFromId("table_name", "tables", "table_id", $tableId, "database_definition_id = (select database_definition_id from database_definitions where database_name = ?)", $GLOBALS['gPrimaryDatabase']->getName());
					$resultSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) left outer join view_columns using(table_column_id) where table_columns.table_id = ? and column_name <> 'version' order by ISNULL(view_column_id),view_columns.sequence_number,table_columns.sequence_number", $tableId);
					while ($row = getNextRow($resultSet)) {
						if (in_array($row['column_name'], $columnNames)) {
							continue;
						}
						$columnNames[] = $row['column_name'];
						$returnArray['column_selector'] .= "<tr class='sortable-row' id='row_" . $row['table_column_id'] . "'><td class='drag-strip'><input class='sequence-number' type='hidden' id='sequence_number_" . $row['table_column_id'] .
							"' name='sequence_number_" . $row['table_column_id'] . "' value='" . $row['sequence_number'] . "' data-crc_value='" .
							getCrcValue($row['sequence_number']) . "'><input class='column-check-value' type='hidden' id='selected_table_column_id_" . $row['table_column_id'] .
							"' name='selected_table_column_id_" . $row['table_column_id'] . "' value='" . (empty($row['view_column_id']) ? "0" : "1") . "' data-crc_value='" . getCrcValue((empty($row['view_column_id']) ? "0" : "1")) .
							"'><img alt='drag strip' src='/images/drag_strip.png'></td><td><input class='column-checkbox' type='checkbox' " . (empty($row['view_column_id']) ? "" : " checked='checked' ") .
							" id='table_column_id_" . $row['table_column_id'] . "' name='table_column_id_" . $row['table_column_id'] . "' value='" . $row['table_column_id'] .
							"'><label class='checkbox-label' for='table_column_id_" . $row['table_column_id'] . "'>" . $tableName . "." . $row['column_name'] . "</label></td></tr>";
					}
				}
				$returnArray['column_selector'] .= "</table>";
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		executeQuery("delete from view_columns where table_id = ?", $nameValues['primary_id']);
		foreach ($nameValues as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("table_column_id_")) == "table_column_id_" && !empty($fieldData)) {
				executeQuery("insert ignore into view_columns (table_id,table_column_id,sequence_number) values (?,?,?)", $nameValues['primary_id'], ($fieldData == "1" ? $fieldName : "") . $fieldData, $nameValues['sequence_number_' . $fieldData]);
			}
		}
		executeQuery("update database_definitions set checked = 0");
		return true;
	}

	function hiddenElements() {
		?>
        <div id="_data_dialog" class="dialog-box">
            <div id="data_content">
            </div>
        </div>
		<?php
	}
}

$pageObject = new ViewMaintenancePage("tables");
$pageObject->displayPage();
