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

$GLOBALS['gPageCode'] = "EXPORTMENUDEFINITIONS";
require_once "shared/startup.inc";

class ExportMenuDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();

		switch ($_GET['url_action']) {
			case "export_menus":
				$menuIds = explode("|", $_GET['menu_ids']);
				$parameters = array_merge($menuIds, array($GLOBALS['gClientId']));

				$resultSet = executeQuery("select * from menus where menu_id in (" . implode(",", array_fill(0, count($menuIds), "?"))
					. ") and client_id = ? order by menu_code", $parameters);

				while ($row = getNextRow($resultSet)) {
					$thisRecord = $row;
					$thisRecord['menu_items'] = array();
					$columnSet = executeQuery("select menu_items.*, menu_contents.sequence_number, (select page_code from pages where page_id = menu_items.page_id) page_code,"
						. " (select subsystem_code from subsystems where subsystem_id = menu_items.subsystem_id) subsystem_code,"
						. " (select image_code from images where image_id = menu_items.image_id) image_code,"
						. " (select menu_code from menus where menu_id = menu_items.menu_id) menu_code"
						. " from menu_items join menu_contents using (menu_item_id) where menu_contents.menu_id = ? order by menu_contents.sequence_number", $row['menu_id']);
					while ($columnRow = getNextRow($columnSet)) {
						$thisRecord['menu_items'][] = $columnRow;
					}
					$returnArray[] = $thisRecord;
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
            $("#export_menus").on("click", function () {
                let menuIds = "";
                $(".selection-cell.selected").find(".selected-menu").each(function () {
                    const menuId = $(this).val();
                    menuIds += (empty(menuIds) ? "" : "|") + menuId;
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_menus&menu_ids=" + menuIds, function (returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#menu_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#result_json").select();
                    $("#select_menus").removeClass("hidden");
                    $("#export_menus").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").on("click", function () {
                $("#menu_list").find(".selected").removeClass("selected");
                $("#menu_list").find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#menu_count").html($("#menu_list").find(".selected").length);
            });
            $("#select_menus").on("click", function () {
                $("#menu_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_menus").addClass("hidden");
                $("#export_menus").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#menu_filter", function () {
                let filterText = $("#menu_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#menu_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").on("click", function () {
                $(this).toggleClass("selected");
                $("#menu_count").html($("#menu_list").find(".selected").length);
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
		$resultSet = executeQuery("select * from menus where client_id = ? and core_menu = 0 order by menu_id", $GLOBALS['gClientId']);
		?>
        <p>
			<input tabindex="10" type="text" id="menu_filter" placeholder="Filter" aria-label="Filter">
            <button id="select_all">Select All</button>
            <button id="export_menus">Export Menus</button>
            <button id="select_menus" class='hidden'>Reselect Menus</button>
        </p>
        <p><span id="menu_count">0</span> menus selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly" aria-label="Result"></textarea>
        </div>
        <table id="menu_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Menu ID</th>
                <th>Menu Code</th>
                <th>Description</th>
				<th>Subject</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
					<td class="selection-cell">
						<input class="selected-menu" type="hidden" value="<?= $row['menu_id'] ?>"
							   id="selected_menu_<?= $row['menu_id'] ?>"><span class="far fa-square"></span><span
							class="far fa-check-square"></span>
					</td>
                    <td><?= $row['menu_id'] ?></td>
                    <td><?= $row['menu_code'] ?></td>
                    <td><?= htmlText($row['description']) ?></td>
					<td><?= htmlText($row['subject']) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}
}

$pageObject = new ExportMenuDefinitionsPage();
$pageObject->displayPage();
