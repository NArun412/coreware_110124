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

$GLOBALS['gPageCode'] = "EXPORTBANNERDEFINITIONS";
require_once "shared/startup.inc";

class ExportBannerDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();

		switch ($_GET['url_action']) {
			case "export_banners":
				$bannerIds = explode("|", $_POST['banner_ids']);
				$parameters = array_merge($bannerIds, array($GLOBALS['gClientId']));

				$returnArray['banners'] = array();

				$bannerTags = array();
                $bannerGroups = array();

				$resultSet = executeQuery("select *, (select image_code from images where image_id = banners.image_id) image_code"
					. " from banners where banner_id in (" . implode(",", array_fill(0, count($bannerIds), "?"))
					. ") and client_id = ? order by banner_code", $parameters);

				while ($row = getNextRow($resultSet)) {
					$thisRecord = $row;

					$subTables = array(
                        "banner_context" => array("extra_select" => ", (select page_code from pages where page_id = banner_context.page_id) page_code"),
						"banner_group_links" => array("extra_select" => ", (select banner_group_code from banner_groups where banner_group_id = banner_group_links.banner_group_id) banner_group_code"),
						"banner_tag_links" => array("extra_select" => ", (select banner_tag_code from banner_tags where banner_tag_id = banner_tag_links.banner_tag_id) banner_tag_code")
                    );

					foreach ($subTables as $subTableName => $extraBits) {
						$thisRecord[$subTableName] = array();
						$columnSet = executeQuery("select *" . $extraBits['extra_select'] . " from " . $subTableName . " where banner_id = ?" . (empty($extraBits['extra_where']) ? "" : " and " . $extraBits['extra_where']), $row['banner_id']);
						while ($columnRow = getNextRow($columnSet)) {
							$thisRecord[$subTableName][] = $columnRow;
						}
					}
					$returnArray['banners'][] = $thisRecord;

					$bannerGroupsResultSet = executeQuery("select * from banner_groups where banner_group_id in (select banner_group_id from banner_group_links where banner_id = ?)"
						. " and client_id = ?", $row['banner_id'], $GLOBALS['gClientId']);
					while ($bannerGroupRow = getNextRow($bannerGroupsResultSet)) {
						if (!array_key_exists($bannerGroupRow['banner_group_code'], $bannerGroups)) {
							$bannerGroups[$bannerGroupRow['banner_group_code']] = $bannerGroupRow;
						}
					}

					$bannerTagsResultSet = executeQuery("select * from banner_tags where banner_tag_id in (select banner_tag_id from banner_tag_links where banner_id = ?)"
                        . " and client_id = ?", $row['banner_id'], $GLOBALS['gClientId']);
					while ($bannerTagRow = getNextRow($bannerTagsResultSet)) {
                        if (!array_key_exists($bannerTagRow['banner_tag_code'], $bannerTags)) {
							$bannerTags[$bannerTagRow['banner_tag_code']] = $bannerTagRow;
                        }
					}
				}

				$returnArray['banner_tags'] = $bannerTags;
				$returnArray['banner_groups'] = $bannerGroups;

				$jsonText = jsonEncode($returnArray);
				$returnArray = array("json" => $jsonText);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#export_banners").on("click", function () {
                let bannerIds = "";
                $(".selection-cell.selected").find(".selected-banner").each(function () {
                    const bannerId = $(this).val();
                    bannerIds += (empty(bannerIds) ? "" : "|") + bannerId;
                });
                const data = { banner_ids: bannerIds };
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_banners", data, function (returnArray) {
                    $("#result_json").val(returnArray['json']).select();
                    $("#banner_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#select_banners").removeClass("hidden");
                    $("#export_banners").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").on("click", function () {
                const banners = $("#banner_list");
                banners.find(".selected").removeClass("selected");
                banners.find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#banner_count").html(banners.find(".selected").length);
            });
            $("#select_banners").on("click", function () {
                $("#banner_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_banners").addClass("hidden");
                $("#export_banners").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#banner_filter", function () {
                let filterText = $("#banner_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#banner_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").on("click", function () {
                $(this).toggleClass("selected");
                $("#banner_count").html($("#banner_list").find(".selected").length);
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
		$resultSet = executeQuery("select * from banners where client_id = ? order by banner_id", $GLOBALS['gClientId']);
		?>
        <p>
			<input tabindex="10" type="text" id="banner_filter" placeholder="Filter" aria-label="Filter">
            <button id="select_all">Select All</button>
            <button id="export_banners">Export Banners</button>
            <button id="select_banners" class='hidden'>Reselect Banners</button>
        </p>
        <p><span id="banner_count">0</span> banners selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly" aria-label="Result"></textarea>
        </div>
        <table id="banner_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Banner ID</th>
                <th>Banner Code</th>
                <th>Description</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
					<td class="selection-cell">
						<input class="selected-banner" type="hidden" value="<?= $row['banner_id'] ?>"
							   id="selected_banner_<?= $row['banner_id'] ?>"><span class="far fa-square"></span><span
							class="far fa-check-square"></span>
					</td>
                    <td><?= $row['banner_id'] ?></td>
                    <td><?= $row['banner_code'] ?></td>
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

$pageObject = new ExportBannerDefinitionsPage();
$pageObject->displayPage();
