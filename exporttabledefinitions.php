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

$GLOBALS['gPageCode'] = "EXPORTTABLEDEFINITIONS";
require_once "shared/startup.inc";

class ExportTableDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "export_tables":
				$tableIds = explode("|", $_GET['table_ids']);
				$parameters = array_merge($tableIds, array($GLOBALS['gUserId']));
				$resultSet = executeQuery("select *,(select description from subsystems where subsystem_id = tables.subsystem_id) subsystem from tables where table_id in (" .
					implode(",", array_fill(0, count($tableIds), "?")) . ") and (subsystem_id is null or " .
					"subsystem_id in (select subsystem_id from subsystems where restricted_access = 0) or subsystem_id in (select subsystem_id from subsystem_users where " .
					"user_id = ?)) order by table_name", $parameters);
				while ($row = getNextRow($resultSet)) {
					$thisTable = $row;
					$thisTable['columns'] = array();
					$columnSet = executeQuery("select * from column_definitions join table_columns using (column_definition_id) where table_id = ? order by sequence_number", $row['table_id']);
					while ($columnRow = getNextRow($columnSet)) {
						$thisTable['columns'][] = $columnRow;
					}
					$thisTable['unique_keys'] = array();
					$columnSet = executeQuery("select *,(select group_concat(column_name) from unique_key_columns join table_columns using (table_column_id) " .
						"join column_definitions using (column_definition_id) where unique_key_id = unique_keys.unique_key_id group by unique_key_id order by unique_key_column_id) column_names from unique_keys where table_id = ?", $row['table_id']);
					while ($columnRow = getNextRow($columnSet)) {
						$thisTable['unique_keys'][] = $columnRow;
					}
					$thisTable['foreign_keys'] = array();
					$columnSet = executeQuery("select (select column_name from column_definitions where column_definition_id = (select column_definition_id from table_columns where " .
						"table_column_id = foreign_keys.table_column_id)) column_name,(select table_name from tables where table_id = (select table_id from table_columns where " .
						"table_column_id = foreign_keys.referenced_table_column_id)) referenced_table_name,(select column_name from column_definitions where column_definition_id = (select column_definition_id from table_columns where " .
						"table_column_id = foreign_keys.referenced_table_column_id)) referenced_column_name from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?)", $row['table_id']);
					while ($columnRow = getNextRow($columnSet)) {
						$thisTable['foreign_keys'][$columnRow['column_name']] = $columnRow;
					}
					$returnArray[] = $thisTable;
				}
				$jsonCode = jsonEncode($returnArray);
				$returnArray = array("json" => $jsonCode);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#export_tables").click(function () {
                let tableIds = "";
                $(".selection-cell.selected").find(".selected-table").each(function () {
                    const tableId = $(this).val();
                    tableIds += (empty(tableIds) ? "" : "|") + tableId;
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_tables&table_ids=" + tableIds, function(returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#table_list").hide();
                    $("#_results").show();
                    $("#result_json").select();
                    $("#select_tables").show();
                    $("#export_tables").hide();
                });
                return false;
            });
            $("#select_tables").click(function () {
                $("#table_list").show();
                $("#_results").hide();
                $("#select_tables").hide();
                $("#export_tables").show();
                return false;
            });
            $(document).on("keyup", "#table_filter", function () {
                let filterText = $("#table_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#table_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
            $(".selection-cell").click(function () {
                $(this).toggleClass("selected");
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            td.selection-cell {
                cursor: pointer;
                width: 40px;
                text-align: center;
            }

            td.selection-cell .fa-square {
                font-size: 18px;
                color: rgb(0, 60, 0);
                font-weight: bold;
            }

            td.selection-cell .fa-check-square {
                font-size: 18px;
                color: rgb(0, 60, 0);
                display: none;
                font-weight: bold;
            }

            td.selection-cell.selected .fa-check-square {
                display: inline;
            }

            td.selection-cell.selected .fa-square {
                display: none;
            }

            #_results {
                display: none;
            }

            #result_json {
                width: 1200px;
                height: 600px;
            }

            #select_tables {
                display: none;
            }
        </style>
		<?php
	}

	function mainContent() {
		$databaseDefinitionId = getFieldFromId("database_definition_id", "database_definitions", "database_name", $GLOBALS['gPrimaryDatabase']->getName());
		$checked = getFieldFromId("checked", "database_definitions", "database_definition_id", $databaseDefinitionId);
		if (empty($checked)) {
			?>
            <p>Database integrity must be checked before exporting.</p>
			<?php
			return true;
		}
		$resultSet = executeQuery("select *,(select description from subsystems where subsystem_id = tables.subsystem_id) subsystem from tables where (subsystem_id is null or " .
			"subsystem_id in (select subsystem_id from subsystems where restricted_access = 0) or subsystem_id in (select subsystem_id from subsystem_users where user_id = ?)) " .
			"and database_definition_id = ? order by table_name", $GLOBALS['gUserId'], $databaseDefinitionId);
		?>
        <p><input tabindex="10" type="text" id="table_filter" placeholder="Filter">&nbsp;<button id="export_tables">Export Tables</button>&nbsp;<button id="select_tables">Reselect Tables</button>
        </p>
        <div id="_results">
            <textarea id="result_json" readonly="readonly"></textarea>
        </div>
        <table id="table_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Table ID</th>
                <th>Table Name</th>
                <th>Description</th>
                <th>Subsystem</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
                    <td class="selection-cell"><input class="selected-table" type="hidden" value="<?= $row['table_id'] ?>" id="selected_table_<?= $row['table_id'] ?>"><span class="far fa-square"></span><span class="far fa-check-square"></span></td>
                    <td><?= $row['table_id'] ?></td>
                    <td><?= $row['table_name'] ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText($row['subsystem']) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}
}

$pageObject = new ExportTableDefinitionsPage();
$pageObject->displayPage();
