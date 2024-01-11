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

$GLOBALS['gPageCode'] = "EXPORTEMAILDEFINITIONS";
require_once "shared/startup.inc";

class ExportEmailDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();

		switch ($_GET['url_action']) {
			case "export_emails":
				$emailIds = explode("|", $_GET['email_ids']);
				$parameters = array_merge($emailIds, array($GLOBALS['gClientId']));

				$resultSet = executeQuery("select *, (select email_credential_code from email_credentials where email_credential_id = emails.email_credential_id) email_credential_code"
					. " from emails where email_id in (" . implode(",", array_fill(0, count($emailIds), "?"))
					. ") and client_id = ? order by email_code", $parameters);

				while ($row = getNextRow($resultSet)) {
					$thisPage = $row;
					$subTables = array("email_copies" => array());
					foreach ($subTables as $subTableName => $extraBits) {
						$thisPage[$subTableName] = array();
						$columnSet = executeQuery("select *" . $extraBits['extra_select'] . " from " . $subTableName . " where email_id = ?" . (empty($extraBits['extra_where']) ? "" : " and " . $extraBits['extra_where']), $row['email_id']);
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
            $("#export_emails").on("click", function () {
                let emailIds = "";
                $(".selection-cell.selected").find(".selected-email").each(function () {
                    const emailId = $(this).val();
                    emailIds += (empty(emailIds) ? "" : "|") + emailId;
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_emails&email_ids=" + emailIds, function (returnArray) {
                    $("#result_json").val(returnArray['json']);
                    $("#email_list").addClass("hidden");
                    $("#_results").removeClass("hidden");
                    $("#result_json").select();
                    $("#select_emails").removeClass("hidden");
                    $("#export_emails").addClass("hidden");
                    $("#select_all").addClass("hidden");
                });
                return false;
            });
            $("#select_all").on("click", function () {
                $("#email_list").find(".selected").removeClass("selected");
                $("#email_list").find("tr.data-row").not(".hidden").find(".selection-cell").addClass("selected");
                $("#email_count").html($("#email_list").find(".selected").length);
            });
            $("#select_emails").on("click", function () {
                $("#email_list").removeClass("hidden");
                $("#_results").addClass("hidden");
                $("#select_emails").addClass("hidden");
                $("#export_emails").removeClass("hidden");
                $("#select_all").removeClass("hidden");
                return false;
            });
            $(document).on("keyup", "#email_filter", function () {
                let filterText = $("#email_filter").val();
                if (empty(filterText)) {
                    filterText = "";
                }
                $("#email_list").find("tr.data-row").each(function () {
                    if (empty(filterText) || $(this).text().toLowerCase().indexOf(filterText.toLowerCase()) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(".selection-cell").on("click", function () {
                $(this).toggleClass("selected");
                $("#email_count").html($("#email_list").find(".selected").length);
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
		$resultSet = executeQuery("select * from emails where client_id = ? order by email_id", $GLOBALS['gClientId']);
		?>
        <p>
			<input tabindex="10" type="text" id="email_filter" placeholder="Filter" aria-label="Filter">
            <button id="select_all">Select All</button>
            <button id="export_emails">Export Emails</button>
            <button id="select_emails" class='hidden'>Reselect Emails</button>
        </p>
        <p><span id="email_count">0</span> emails selected</p>
        <div id="_results" class='hidden'>
            <textarea id="result_json" readonly="readonly" aria-label="Result"></textarea>
        </div>
        <table id="email_list" class="header-sortable grid-table">
            <tr class="header-row">
                <th></th>
                <th>Email ID</th>
                <th>Email Code</th>
                <th>Description</th>
				<th>Subject</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr class="data-row">
					<td class="selection-cell">
						<input class="selected-email" type="hidden" value="<?= $row['email_id'] ?>"
							   id="selected_email_<?= $row['email_id'] ?>"><span class="far fa-square"></span><span
							class="far fa-check-square"></span>
					</td>
                    <td><?= $row['email_id'] ?></td>
                    <td><?= $row['email_code'] ?></td>
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

$pageObject = new ExportEmailDefinitionsPage();
$pageObject->displayPage();
