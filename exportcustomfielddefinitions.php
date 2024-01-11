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

$GLOBALS['gPageCode'] = "EXPORTCUSTOMFIELDDEFINITIONS";
require_once "shared/startup.inc";

class ExportCustomFieldDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();

		switch ($_GET['url_action']) {
			case "export_custom_fields":
				$customFieldIds = explode("|", $_GET['custom_field_ids']);
				$parameters = array_merge($customFieldIds, array($GLOBALS['gClientId']));

				$resultSet = executeQuery("select *, (select custom_field_type_code from custom_field_types where custom_field_type_id = custom_fields.custom_field_type_id) custom_field_type_code,"
					. " (select user_group_code from user_groups where user_group_id = custom_fields.user_group_id) user_group_code"
					. " from custom_fields where custom_field_id in (" . implode(",", array_fill(0, count($customFieldIds), "?"))
					. ") and client_id = ? order by custom_field_code", $parameters);

				while ($row = getNextRow($resultSet)) {
					$thisPage = $row;

					$subTables = array("custom_field_choices" => array(), "custom_field_controls" => array(),
                        "custom_field_group_links" => array("extra_select" => ", (select custom_field_group_code from custom_field_groups where custom_field_group_id = custom_field_group_links.custom_field_group_id) custom_field_group_code"));
					foreach ($subTables as $subTableName => $extraBits) {
						$thisPage[$subTableName] = array();
						$columnSet = executeQuery("select *" . $extraBits['extra_select'] . " from " . $subTableName . " where custom_field_id = ?" . (empty($extraBits['extra_where']) ? "" : " and " . $extraBits['extra_where']), $row['custom_field_id']);
						while ($columnRow = getNextRow($columnSet)) {
							$thisPage[$subTableName][] = $columnRow;
						}
					}
					$returnArray[] = $thisPage;
				}

				$jsonText = jsonEncode($returnArray);
				$returnArray = array("json" => $jsonText);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#export_custom_fields").on("click", function () {
                let customFieldIds = "";
                $(".selection-cell.selected").find(".selected-custom-field").each(function () {
                    const customFieldId = $(this).val();
                    customFieldIds += (empty(customFieldIds) ? "" : "|") + customFieldId;
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_custom_fields&custom_field_ids=" + customFieldIds, function (returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#custom_field_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#result_json").select();
                    $("#select_custom_fields").removeClass("hidden");
                    $("#export_custom_fields").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").on("click", function () {
                $("#custom_field_list").find(".selected").removeClass("selected");
                $("#custom_field_list").find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#custom_field_count").html($("#custom_field_list").find(".selected").length);
            });
            $("#select_custom_fields").on("click", function () {
                $("#custom_field_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_custom_fields").addClass("hidden");
                $("#export_custom_fields").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#custom_field_filter", function () {
                let filterText = $("#custom_field_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#custom_field_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").on("click", function () {
                $(this).toggleClass("selected");
                $("#custom_field_count").html($("#custom_field_list").find(".selected").length);
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
            }

            td.selection-cell .fa-check-square {
                font-size: 18px;
                color: rgb(0, 60, 0);
                display: none;
            }

            td.selection-cell.selected .fa-check-square {
                display: inline;
            }

            td.selection-cell.selected .fa-square {
                display: none;
            }

            #result_json {
                width: 1200px;
                height: 600px;
            }

        </style>
		<?php
	}

	function mainContent() {
		$resultSet = executeQuery("select *, (select custom_field_type_code from custom_field_types where custom_field_type_id = custom_fields.custom_field_type_id) custom_field_type_code"
            . " from custom_fields where client_id = ? order by custom_field_id", $GLOBALS['gClientId']);
		?>
        <p>
			<input tabindex="10" type="text" id="custom_field_filter" placeholder="Filter" aria-label="Filter">
            <button id="select_all">Select All</button>
            <button id="export_custom_fields">Export Custom Fields</button>
            <button id="select_custom_fields" class='hidden'>Reselect Custom Fields</button>
        </p>
        <p><span id="custom_field_count">0</span> custom fields selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly" aria-label="Result"></textarea>
        </div>
        <table id="custom_field_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Custom Field ID</th>
                <th>Custom Field Code</th>
                <th>Custom Field Type</th>
				<th>Description</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
					<td class="selection-cell">
						<input class="selected-custom-field" type="hidden" value="<?= $row['custom_field_id'] ?>"
							   id="selected_custom_field_<?= $row['custom_field_id'] ?>"><span class="far fa-square"></span><span
							class="far fa-check-square"></span>
					</td>
                    <td><?= $row['custom_field_id'] ?></td>
                    <td><?= $row['custom_field_code'] ?></td>
                    <td><?= $row['custom_field_type_code'] ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}
}

$pageObject = new ExportCustomFieldDefinitionsPage();
$pageObject->displayPage();
