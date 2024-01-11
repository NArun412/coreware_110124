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

$GLOBALS['gPageCode'] = "CHANGELOG";
require_once "shared/startup.inc";

class ChangeLogPage extends Page {
	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$returnArray = self::getReportContent();
				if (array_key_exists("report_export", $returnArray)) {
					if (is_array($returnArray['export_headers'])) {
						foreach ($returnArray['export_headers'] as $thisHeader) {
							header($thisHeader);
						}
					}
					echo $returnArray['report_export'];
				} else {
					echo jsonEncode($returnArray);
				}
				exit;
			case "get_details":
				$resultSet = executeQuery("select * from change_log where log_id = ? and client_id = ?", $_GET['log_id'], $GLOBALS['gClientId']);
				$row = getNextRow($resultSet);
				$returnArray['old_value'] = $row['old_value'];
				$returnArray['new_value'] = $row['new_value'];
				$returnArray['notes'] = $row['notes'];
				ajaxResponse($returnArray);
				exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);

		if (!empty($_POST['date_changed_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "time_changed >= ?";
			$parameters[] = makeDateParameter($_POST['date_changed_from']);
		}
		if (!empty($_POST['date_changed_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "time_changed <= ?";
			$parameters[] = makeDateParameter($_POST['date_changed_to']);
		}
		if (!empty($_POST['user_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "user_id = ?";
			$parameters[] = $_POST['user_id'];
		}
		if (!empty($_POST['table_name'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "table_name = ?";
			$parameters[] = $_POST['table_name'];
		}
		if (!empty($_POST['column_name'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "column_name = ?";
			$parameters[] = $_POST['column_name'];
		}
		if (!empty($_POST['primary_identifier'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "primary_identifier = ?";
			$parameters[] = $_POST['primary_identifier'];
		}
		if (!empty($_POST['foreign_key_identifier'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "foreign_key_identifier = ?";
			$parameters[] = $_POST['foreign_key_identifier'];
		}

		$detailReport = $_POST['report_type'] == "detail";
		ob_start();

		$resultSet = executeReadQuery("select * from change_log where client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by time_changed desc", $parameters);
		$returnArray['report_title'] = "Change Log";
		?>
        <p><input type='text' placeholder='Filter' id='text_filter'></p>
        <table class='grid-table' id='change_log_table'>
            <tr>
                <th>Changed</th>
                <th>By Whom</th>
                <th>Table</th>
                <th>Column</th>
                <th>ID</th>
                <th>Old</th>
                <th>New</th>
                <th>Notes</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
                $noDetails = strlen($row['old_value']) > 50 || strlen($row['new_value']) > 50 || strlen($row['notes']) > 50;
				?>
                <tr class='data-row'>
                    <td><?= date("m/d/Y g:ia", strtotime($row['time_changed'])) ?></td>
                    <td><?= (empty($row['user_id']) ? "n/a" : getUserDisplayName($row['user_id'])) ?></td>
                    <td><?= htmlText($row['table_name']) ?></td>
                    <td><?= htmlText($row['column_name']) ?></td>
                    <td><?= $row['primary_identifier'] ?></td>
                    <td><?php if ($noDetails) { ?><a href='#' class='view-details' data-log_id='<?= $row['log_id'] ?>'>Details</a><?php } else { ?><?= htmlText($row['old_value']) ?><?php } ?></td>
                    <td><?php if ($noDetails) { ?><?php } else { ?><?= htmlText($row['new_value']) ?><?php } ?></td>
                    <td><?php if ($noDetails) { ?><?php } else { ?><?= htmlText($row['notes']) ?><?php } ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <p class='info-message'>Either table or user is required.</p>

				<?php echo getFieldControl("change_log", "user_id", array("not_null" => false, "data_type" => "user_picker")) ?>

                <div class="basic-form-line" id="_table_name_row">
                    <label class='required-label'>Table</label>
                    <select tabindex='10' id='table_name' name='table_name' class='validate[required]' data-conditional-required="empty($('#user_id').val())">
                        <option value=''>[All]</option>
						<?php
						$resultSet = executeQuery("select * from tables order by table_name");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value='<?= $row['table_name'] ?>' <?= ($row['table_name'] == $_GET['table_name'] ? "selected" : "") ?>><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                </div>

                <div class="basic-form-line" id="_date_changed_row">
                    <label for="date_changed_from">Date Change Made: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_changed_from" name="date_changed_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_changed_to" name="date_changed_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_primary_identifier_row">
                    <label>Primary Key Value</label>
                    <input type='text' size='12' class='align-right validate[custom[integer]]' id='primary_identifier' name='primary_identifier' value='<?= $_GET['primary_identifier'] ?>'>
                    <div class='basic-form-line-messages'><span class="help-label">Will be ignored unless table is set</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_foreign_key_identifier_row">
                    <label>Foreign Key Value</label>
                    <input type='text' size='12' class='align-right validate[custom[integer]]' id='foreign_key_identifier' name='foreign_key_identifier' value='<?= $_GET['foreign_key_identifier'] ?>'>
                    <div class='basic-form-line-messages'><span class="help-label">Will be ignored unless table is set</span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
            <button id="new_parameters_button">Search Again</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("keyup", "#text_filter", function (event) {
                const textFilter = $(this).val().toLowerCase();
                console.log(textFilter);
                if (empty(textFilter)) {
                    $("#change_log_table tr.data-row").removeClass("hidden");
                } else {
                    $("#change_log_table tr.data-row").each(function () {
                        const description = $(this).text().toLowerCase();
                        console.log(description);
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
            $(document).on("click", ".view-details", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_details&log_id=" + $(this).data("log_id"), function (returnArray) {
                    $("#old_value").val(returnArray['old_value']);
                    $("#new_value").val(returnArray['new_value']);
                    $("#notes").val(returnArray['notes']);
                    $('#_details_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 1200,
                        title: 'Change Details',
                        buttons: {
                            Close: function (event) {
                                $("#_details_dialog").dialog('close');
                            }
                        }
                    });
                });
            })
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                const $reportForm = $("#_report_form");
                if ($reportForm.validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "csv") {
                        $reportForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function (returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, 600);
                                $("#text_filter").focus();
                            }
                        });
                    }
                }
                return false;
            });
            $(document).on("tap click", "#new_parameters_button", function () {
                $("#report_parameters").show();
                $("#_report_title").hide();
                $("#_report_content").hide();
                $("#_button_row").hide();
                return false;
            });
            <?php if ($_GET['url_page'] == "list" && !empty($_GET['table_name'])) { ?>
            setTimeout(function() {
                $("#create_report").trigger("click");
            },500)
            <?php } ?>
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .basic-form-line textarea {
                width: 100%;
            }
            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_report_content {
                display: none;
            }

            #_report_content table td {
                font-size: .9rem;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
		<?php
	}

	public function hiddenElements() {
		include "userpicker.inc";

		?>
        <div class='dialog-box' id='_details_dialog'>
            <div class='basic-form-line'>
                <label>Old Value</label>
                <textarea readonly=readonly id='old_value'></textarea>
            </div>
            <div class='basic-form-line'>
                <label>New Value</label>
                <textarea readonly=readonly id='new_value'></textarea>
            </div>
            <div class='basic-form-line'>
                <label>Notes</label>
                <textarea readonly=readonly id='notes'></textarea>
            </div>
        </div>
		<?php
	}
}

$pageObject = new ChangeLogPage();
$pageObject->displayPage();
