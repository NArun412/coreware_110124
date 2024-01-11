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

$GLOBALS['gPageCode'] = "EXPORTPAGEDEFINITIONS";
require_once "shared/startup.inc";

class ExportPageDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "export_pages":
				$pageIds = explode("|", $_GET['page_ids']);
				$parameters = array_merge($pageIds, array($GLOBALS['gClientId'], $GLOBALS['gUserId']));
				$resultSet = executeQuery("select *,(select description from subsystems where subsystem_id = pages.subsystem_id) subsystem," .
					"(select template_code from templates where template_id = pages.template_id) template_code from pages where page_id in (" .
					implode(",", array_fill(0, count($pageIds), "?")) . ") and client_id = ? and (subsystem_id is null or " .
					"subsystem_id in (select subsystem_id from subsystems where restricted_access = 0) or subsystem_id in (select subsystem_id from subsystem_users where " .
					"user_id = ?)) order by page_code", $parameters);
				while ($row = getNextRow($resultSet)) {
					$thisPage = $row;
					$subtables = array("page_access" => array("extra_where" => "client_type_id is null and user_type_id is null and user_group_id is null"),
						"page_controls" => array(), "page_data" => array("extra_where" => "image_id is null and file_id is null", "extra_select" => ",(select data_name from template_data where template_data_id = page_data.template_data_id) template_data_name"),
						"page_functions" => array(), "page_meta_tags" => array(), "page_notifications" => array(), "page_text_chunks" => array());
					foreach ($subtables as $subtableName => $extraBits) {
						$thisPage[$subtableName] = array();
						$columnSet = executeQuery("select *" . $extraBits['extra_select'] . " from " . $subtableName . " where page_id = ?" . (empty($extraBits['extra_where']) ? "" : " and " . $extraBits['extra_where']), $row['page_id']);
						while ($columnRow = getNextRow($columnSet)) {
							$thisPage[$subtableName][] = $columnRow;
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
            $("#export_pages").click(function () {
                let pageIds = "";
                $(".selection-cell.selected").find(".selected-page").each(function () {
                    const pageId = $(this).val();
                    pageIds += (empty(pageIds) ? "" : "|") + pageId;
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_pages&page_ids=" + pageIds, function(returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#page_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#result_json").select();
                    $("#select_pages").removeClass("hidden");
                    $("#export_pages").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").click(function () {
                $("#page_list").find(".selected").removeClass("selected");
                $("#page_list").find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#page_count").html($("#page_list").find(".selected").length);
            });
            $("#select_pages").click(function () {
                $("#page_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_pages").addClass("hidden");
                $("#export_pages").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#page_filter", function () {
                let filterText = $("#page_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#page_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").click(function () {
                $(this).toggleClass("selected");
                $("#page_count").html($("#page_list").find(".selected").length);
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
		$resultSet = executeQuery("select *,(select description from templates where template_id = pages.template_id) template from pages where client_id = ? and (subsystem_id is null or " .
			"subsystem_id in (select subsystem_id from subsystems where restricted_access = 0) or subsystem_id in (select subsystem_id from subsystem_users where user_id = ?)) " .
			"order by page_id", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
		?>
        <p><input tabindex="10" type="text" id="page_filter" placeholder="Filter">
            <button id="select_all">Select All</button>
            <button id="export_pages">Export Pages</button>
            <button id="select_pages" class='hidden'>Reselect Pages</button>
        </p>
        <p><span id="page_count">0</span> pages selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly"></textarea>
        </div>
        <table id="page_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Page ID</th>
                <th>Page Code</th>
                <th>Description</th>
                <th>Template</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
                    <td class="selection-cell"><input class="selected-page" type="hidden" value="<?= $row['page_id'] ?>" id="selected_page_<?= $row['page_id'] ?>"><span class="far fa-square"></span><span class="far fa-check-square"></span></td>
                    <td><?= $row['page_id'] ?></td>
                    <td><?= $row['page_code'] ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText($row['template']) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}
}

$pageObject = new ExportPageDefinitionsPage();
$pageObject->displayPage();
