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

$GLOBALS['gPageCode'] = "EXPORTFRAGMENTDEFINITIONS";
require_once "shared/startup.inc";

class ExportFragmentDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();

		switch ($_GET['url_action']) {
			case "export_fragments":
				$fragmentIds = explode("|", $_GET['fragment_ids']);
				$parameters = array_merge($fragmentIds, array($GLOBALS['gClientId']));

				$resultSet = executeQuery("select *, (select fragment_type_code from fragment_types where fragment_type_id = fragments.fragment_type_id) fragment_type_code,"
					. "(select image_code from images where image_id = fragments.image_id) image_code"
					. " from fragments where fragment_id in (" . implode(",", array_fill(0, count($fragmentIds), "?"))
					. ") and client_id = ? order by fragment_code", $parameters);

				while ($row = getNextRow($resultSet)) {
					$returnArray[] = $row;
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
            $("#export_fragments").on("click", function () {
                let fragmentIds = "";
                $(".selection-cell.selected").find(".selected-fragment").each(function () {
                    const fragmentId = $(this).val();
                    fragmentIds += (empty(fragmentIds) ? "" : "|") + fragmentId;
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_fragments&fragment_ids=" + fragmentIds, function (returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#fragment_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#result_json").select();
                    $("#select_fragments").removeClass("hidden");
                    $("#export_fragments").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").on("click", function () {
                $("#fragment_list").find(".selected").removeClass("selected");
                $("#fragment_list").find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#fragment_count").html($("#fragment_list").find(".selected").length);
            });
            $("#select_fragments").on("click", function () {
                $("#fragment_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_fragments").addClass("hidden");
                $("#export_fragments").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#fragment_filter", function () {
                let filterText = $("#fragment_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#fragment_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").on("click", function () {
                $(this).toggleClass("selected");
                $("#fragment_count").html($("#fragment_list").find(".selected").length);
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
		$resultSet = executeQuery("select * from fragments where client_id = ? order by fragment_id", $GLOBALS['gClientId']);
		?>
        <p>
			<input tabindex="10" type="text" id="fragment_filter" placeholder="Filter" aria-label="Filter">
            <button id="select_all">Select All</button>
            <button id="export_fragments">Export Fragments</button>
            <button id="select_fragments" class='hidden'>Reselect Fragments</button>
        </p>
        <p><span id="fragment_count">0</span> fragments selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly" aria-label="Result"></textarea>
        </div>
        <table id="fragment_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Fragment ID</th>
                <th>Fragment Code</th>
                <th>Description</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
					<td class="selection-cell">
						<input class="selected-fragment" type="hidden" value="<?= $row['fragment_id'] ?>"
							   id="selected_fragment_<?= $row['fragment_id'] ?>"><span class="far fa-square"></span><span
							class="far fa-check-square"></span>
					</td>
                    <td><?= $row['fragment_id'] ?></td>
                    <td><?= $row['fragment_code'] ?></td>
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

$pageObject = new ExportFragmentDefinitionsPage();
$pageObject->displayPage();
