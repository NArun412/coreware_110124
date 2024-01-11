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

$GLOBALS['gPageCode'] = "EXPORTIMAGEDEFINITIONS";
require_once "shared/startup.inc";

class ExportImageDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();

		switch ($_GET['url_action']) {
			case "export_images":
				$imageIds = explode("|", $_POST['image_ids']);
				$parameters = array_merge($imageIds, array($GLOBALS['gClientId']));

				$resultSet = executeQuery("select *, (select source_code from sources where source_id = images.source_id) source_code,"
					. " (select user_group_code from user_groups where user_group_id = images.user_group_id) user_group_code,"
					. " (select country_code from countries where country_id = images.country_id) country_code,"
					. " (select image_folder_code from image_folders where image_folder_id = images.image_folder_id) image_folder_code"
					. " from images where image_id in (" . implode(",", array_fill(0, count($imageIds), "?"))
					. ") and client_id = ? order by image_code", $parameters);

				while ($row = getNextRow($resultSet)) {
					$thisRecord = $row;

					$subTables = array("image_data" => array("extra_select" => ", (select image_data_type_code from image_data_types where image_data_type_id = image_data.image_data_type_id) image_data_type_code"),
						"album_images" => array("extra_select" => ", (select album_code from albums where album_id = album_images.album_id) album_code"));

					foreach ($subTables as $subTableName => $extraBits) {
						$thisRecord[$subTableName] = array();
						$columnSet = executeQuery("select *" . $extraBits['extra_select'] . " from " . $subTableName . " where image_id = ?" . (empty($extraBits['extra_where']) ? "" : " and " . $extraBits['extra_where']), $row['image_id']);
						while ($columnRow = getNextRow($columnSet)) {
							$thisRecord[$subTableName][] = $columnRow;
						}
					}

					$thisRecord['file_content'] = base64_encode($thisRecord['file_content']);
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
            $("#export_images").on("click", function () {
                let imageIds = "";
                $(".selection-cell.selected").find(".selected-image").each(function () {
                    const imageId = $(this).val();
                    imageIds += (empty(imageIds) ? "" : "|") + imageId;
                });
                const data = { image_ids: imageIds };
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_images", data, function (returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#image_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#result_json").select();
                    $("#select_images").removeClass("hidden");
                    $("#export_images").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").on("click", function () {
                $("#image_list").find(".selected").removeClass("selected");
                $("#image_list").find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#image_count").html($("#image_list").find(".selected").length);
            });
            $("#select_images").on("click", function () {
                $("#image_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_images").addClass("hidden");
                $("#export_images").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#image_filter", function () {
                let filterText = $("#image_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#image_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").on("click", function () {
                $(this).toggleClass("selected");
                $("#image_count").html($("#image_list").find(".selected").length);
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
		// Those with os_filename (large files) are not yet supported
		$resultSet = executeQuery("select * from images where client_id = ? and os_filename is null order by image_id", $GLOBALS['gClientId']);
		?>
        <p>
			<input tabindex="10" type="text" id="image_filter" placeholder="Filter" aria-label="Filter">
            <button id="select_all">Select All</button>
            <button id="export_images">Export Images</button>
            <button id="select_images" class='hidden'>Reselect Images</button>
        </p>
        <p><span id="image_count">0</span> images selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly" aria-label="Result"></textarea>
        </div>
        <table id="image_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Image ID</th>
                <th>Image Code</th>
				<th>File Name</th>
				<th>Date Uploaded</th>
                <th>Description</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
					<td class="selection-cell">
						<input class="selected-image" type="hidden" value="<?= $row['image_id'] ?>"
							   id="selected_image_<?= $row['image_id'] ?>"><span class="far fa-square"></span><span
							class="far fa-check-square"></span>
					</td>
                    <td><?= $row['image_id'] ?></td>
                    <td><?= $row['image_code'] ?></td>
					<td><?= getFirstPart($row['filename'], 50) ?></td>
					<td><?= $row['date_uploaded'] ?></td>
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

$pageObject = new ExportImageDefinitionsPage();
$pageObject->displayPage();
