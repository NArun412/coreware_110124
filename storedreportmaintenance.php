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

$GLOBALS['gPageCode'] = "STOREDREPORTMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("parameters", "last_start_time", "repeat_rules", "email_results", "class_name"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("date_created", "readonly", true);
		$this->iDataSource->addColumnControl("page_id", "readonly", true);
		$this->iDataSource->addColumnControl("page_id", "get_choices", "pageChoices");
		if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gUserRow']['full_client_access']) {
			$this->iDataSource->addColumnControl("user_id", "readonly", true);
		}
		$this->iDataSource->addColumnControl("user_id", "data_type", "user_picker");
		$this->iDataSource->addColumnControl("parameters", "readonly", true);

		$this->iDataSource->addColumnControl("email_results", "form_label", "Email report when completed");
		$this->iDataSource->addColumnControl("stored_report_email_addresses", "form_label", "Additional Email Addresses to receive report");
		$this->iDataSource->addColumnControl("stored_report_email_addresses", "data_type", "custom");
		$this->iDataSource->addColumnControl("stored_report_email_addresses", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("stored_report_email_addresses", "list_table", "stored_report_email_addresses");

		$this->iDataSource->addColumnControl("most_recent", "data_type", "datetime");
		$this->iDataSource->addColumnControl("most_recent", "form_label", "Last Report");
		$this->iDataSource->addColumnControl("most_recent", "select_value", "select max(log_time) from stored_report_results where stored_report_id = stored_reports.stored_report_id");

		$this->iDataSource->getPrimaryTable()->setSubtables(array("stored_report_results", "stored_report_email_addresses"));
		if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gUserRow']['full_client_access']) {
			$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#remove_all", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_all_results&stored_report_id=" + $("#primary_id").val(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#results_table").replaceWith("<p>None Found</p>");
                    }
                });
                return false;
            });
            $(document).on("click", ".delete-report", function () {
                const $thisButton = $(this);
                const storedReportResultId = $(this).closest("tr.previous-report-row").data("stored_report_result_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_result&stored_report_id=" + $("#primary_id").val() + "&stored_report_result_id=" + storedReportResultId, function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $thisButton.closest("tr").remove();
                        if ($(".previous-report-row").length == 0) {
                            $("#results_table").replaceWith("<p>None Found</p>");
                        }
                    }
                });
                return false;
            })
            $(document).on("click", "#hide_report", function () {
                $("#_button_row").addClass("hidden");
                $("#_report_content").addClass("hidden");
                $("#_report_title").addClass("hidden");
                $("#_stored_report_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orders.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("change", "#frequency", function () {
                $(".repeat-field").hide();
                const thisValue = $(this).val();
                $("." + thisValue.toLowerCase() + "-repeat").show();
            });
            $(document).on("click", ".download-report", function () {
                const storedReportResultId = $(this).closest("tr.previous-report-row").data("stored_report_result_id");
                window.open("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=download_report&stored_report_id=" + $("#primary_id").val() + "&stored_report_result_id=" + storedReportResultId);
                return false;
            });
            $(document).on("click", ".view-report", function () {
                const $thisButton = $(this);
                const storedReportResultId = $(this).closest("tr.previous-report-row").data("stored_report_result_id");
                if ($("#_stored_report_result_id").html() == storedReportResultId) {
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_report_result&stored_report_id=" + $("#primary_id").val() + "&stored_report_result_id=" + storedReportResultId, function(returnArray) {
                    if ("report_content" in returnArray) {
                        $("#_stored_report_result_id").html(storedReportResultId);
                        $("#_button_row").removeClass("hidden");
                        $("#_report_content").html(returnArray['report_content']).removeClass("hidden");
                        $("#_report_title").html(returnArray['report_title']).removeClass("hidden");
                        $("#_stored_report_wrapper").removeClass("hidden");
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#_button_row").addClass("hidden");
                $("#_report_content").html(returnArray['report_content']).addClass("hidden");
                $("#_report_title").html(returnArray['report_title']).addClass("hidden");
                $("#_stored_report_wrapper").addClass("hidden");
                $("#frequency").trigger("change");
                if (empty(returnArray['not_background_compatible'])) {
                    $("#interval_controls").removeClass("hidden");
                } else {
                    $("#interval_controls").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "download_report":
				$storedReportId = getFieldFromId("stored_report_id", "stored_reports", "stored_report_id", $_GET['stored_report_id']);
				$storedReportResultRow = getRowFromId("stored_report_results", "stored_report_id", $storedReportId, "stored_report_result_id = ?", $_GET['stored_report_result_id']);
				if (!empty($storedReportResultRow['content'])) {
					$returnArray = json_decode($storedReportResultRow['content'], true);
				}
				if (is_array($returnArray['export_headers'])) {
					foreach ($returnArray['export_headers'] as $thisHeader) {
						header($thisHeader);
					}
				}
				echo $returnArray['report_export'];
				exit;
			case "delete_all_results":
				$storedReportId = getFieldFromId("stored_report_id", "stored_reports", "stored_report_id", $_GET['stored_report_id']);
				if (empty($storedReportId)) {
					$returnArray['error_message'] = "Report not found";
				} else {
					executeQuery("delete from stored_report_results where stored_report_id = ?", $storedReportId);
				}
				ajaxResponse($returnArray);
				break;
			case "get_report_result":
				$storedReportId = getFieldFromId("stored_report_id", "stored_reports", "stored_report_id", $_GET['stored_report_id']);
				$storedReportResultRow = getRowFromId("stored_report_results", "stored_report_id", $storedReportId, "stored_report_result_id = ?", $_GET['stored_report_result_id']);
				if (!empty($storedReportResultRow['content'])) {
					$returnArray = json_decode($storedReportResultRow['content'], true);
				}
				ajaxResponse($returnArray);
				break;
			case "delete_result":
				$storedReportId = getFieldFromId("stored_report_id", "stored_reports", "stored_report_id", $_GET['stored_report_id']);
				$storedReportResultRow = getRowFromId("stored_report_results", "stored_report_id", $storedReportId, "stored_report_result_id = ?", $_GET['stored_report_result_id']);
				if (empty($storedReportId) || empty($storedReportResultRow)) {
					$returnArray['error_message'] = "Report not found";
				} else {
					executeQuery("delete from stored_report_results where stored_report_result_id = ?", $storedReportResultRow['stored_report_result_id']);
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function intervalFields() {
		?>
        <div class="form-line" id="_frequency_row">
            <label for="frequency">Frequency</label>
            <select tabindex="10" class='field-text' name='frequency' id='frequency'>
                <option value="">[None]</option>
                <option value="HOURLY">Hourly</option>
                <option value="DAILY">Daily</option>
                <option value="WEEKLY">Weekly</option>
                <option value="MONTHLY">Monthly</option>
            </select>
            <div class='clear-div'></div>
        </div>

        <div class="form-line repeat-field monthly-repeat" id="_month_row">
            <label class="required-label">Months of the year</label>
            <table class="grid-table">
                <tr>
					<?php foreach ($GLOBALS['gMonthArray'] as $month => $description) { ?>
                        <th class="align-center"><label for="month_<?= $month ?>"><?= $description ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php foreach ($GLOBALS['gMonthArray'] as $month => $description) { ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $month ?>" name="month_<?= $month ?>" id="month_<?= $month ?>"></td>
					<?php } ?>
                </tr>
            </table>
            <div class='clear-div'></div>
        </div>

        <div class="form-line repeat-field monthly-repeat" id="_month_day_row">
            <label class="required-label">Day(s) of the month</label>
            <table class="grid-table">
                <tr>
					<?php for ($x = 1; $x <= 31; $x++) { ?>
                        <th class="align-center"><label for="month_day_<?= $x ?>"><?= ($x == 31 ? "Last" : $x) ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php for ($x = 1; $x <= 31; $x++) { ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $x ?>" name="month_day_<?= $x ?>" id="month_day_<?= $x ?>"></td>
					<?php } ?>
                </tr>
            </table>
            <div class='clear-div'></div>
        </div>

        <div class="form-line repeat-field weekly-repeat" id="_weekday_row">
            <label class="required-label">Day(s) of the week</label>
            <table class="grid-table">
                <tr>
					<?php foreach ($GLOBALS['gWeekdays'] as $weekday => $description) { ?>
                        <th class="align-center"><label for="weekday_<?= $weekday ?>"><?= $description ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php foreach ($GLOBALS['gWeekdays'] as $weekday => $description) { ?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $weekday ?>" name="weekday_<?= $weekday ?>" id="weekday_<?= $weekday ?>"></td>
					<?php } ?>
                </tr>
            </table>
            <div class='clear-div'></div>
        </div>

        <div class="form-line repeat-field monthly-repeat weekly-repeat daily-repeat" id="_hour_of_day_row">
            <label class="required-label">Hour(s) of the day</label>
            <table class="grid-table">
                <tr>
					<?php
					for ($x = 0; $x <= 23; $x++) {
						$description = ($x == 0 ? "12" : ($x < 13 ? $x : ($x - 12))) . ($x == 0 || $x == 12 ? " " . ($x < 12 ? "am" : "pm") : "");
						?>
                        <th class="align-center"><label for="hour_<?= $x ?>"><?= $description ?></label></th>
					<?php } ?>
                </tr>
                <tr>
					<?php
					for ($x = 0; $x <= 23; $x++) {
						?>
                        <td class="align-center"><input tabindex="10" type="checkbox" value="<?= $x ?>" name="hour_<?= $x ?>" id="hour_<?= $x ?>"></td>

					<?php } ?>
                </tr>
            </table>
            <div class='clear-div'></div>
        </div>

        <div class="form-line repeat-field monthly-repeat weekly-repeat daily-repeat hourly-repeat" id="_hour_minute_row">
            <label for="hour_minute" class="required-label">Minute of the hour (0-59)</label>
            <input tabindex="10" class='validate[required,custom[integer],min[0],max[59]] align-right' type='text' size='4' maxlength='4' name='hour_minute' id='hour_minute' value=''/>
            <div class='clear-div'></div>
        </div>

		<?php
	}

	function afterGetRecord(&$returnArray) {
		$repeatParts = explode(":", $returnArray['repeat_rules']['data_value']);
		$returnArray['frequency'] = array("data_value" => $repeatParts[0], "crc_value" => getCrcValue($repeatParts[0]));
		$returnArray['minute_interval'] = array("data_value" => $repeatParts[1], "crc_value" => getCrcValue($repeatParts[1]));
		$monthValues = explode(",", $repeatParts[2]);
		foreach ($GLOBALS['gMonthArray'] as $month => $description) {
			$returnArray['month_' . $month] = array("data_value" => (in_array($month, $monthValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($month, $monthValues) ? "1" : "0")));
		}
		$monthDayValues = explode(",", $repeatParts[3]);
		for ($x = 1; $x <= 31; $x++) {
			$returnArray['month_day_' . $x] = array("data_value" => (in_array($x, $monthDayValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($x, $monthDayValues) ? "1" : "0")));
		}
		$weekdayValues = explode(",", $repeatParts[4]);
		foreach ($GLOBALS['gWeekdays'] as $weekday => $description) {
			$returnArray['weekday_' . $weekday] = array("data_value" => (in_array($weekday, $weekdayValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($weekday, $weekdayValues) ? "1" : "0")));
		}
		$hourValues = explode(",", $repeatParts[5]);
		for ($x = 0; $x <= 23; $x++) {
			$returnArray['hour_' . $x] = array("data_value" => (in_array($x, $hourValues) ? "1" : "0"), "crc_value" => getCrcValue((in_array($x, $hourValues) ? "1" : "0")));
		}
		$returnArray['hour_minute'] = array("data_value" => $repeatParts[6], "crc_value" => getCrcValue($repeatParts[6]));
		$resultSet = executeQuery("select * from stored_report_results where stored_report_id = ? order by log_time desc limit 20", $returnArray['primary_id']['data_value']);
		if ($resultSet['row_count'] == 0) {
			$returnArray['previous_reports'] = array("data_value" => "<p>None Found</p>");
		} else {
			ob_start();
			?>
            <p>
                <button id="remove_all">Remove All Reports</button>
            </p>
            <table class='grid-table' id="results_table">
                <tr>
                    <th>Time Run</th>
                    <th></th>
                    <th></th>
                </tr>
				<?php
				while ($row = getNextRow($resultSet)) {
					$reportContents = json_decode($row['content'], true);
					?>
                    <tr class='previous-report-row' data-stored_report_result_id="<?= $row['stored_report_result_id'] ?>">
                        <td><?= date("m/d/Y g:i a", strtotime($row['log_time'])) ?></td>
                        <td>
							<?php if (!empty($row['file_id'])) { ?>
                                <a href='/download.php?id=<?= $row['file_id'] ?>' class='button'>Download</a>
							<?php } else if (is_array($reportContents) && array_key_exists("report_export", $reportContents) || !empty($row['file_id'])) { ?>
                                <button class='download-report'>Download</button>
							<?php } else { ?>
                                <button class='view-report'>View Report</button>
							<?php } ?>
                        </td>
                        <td class='align-center'><span class='delete-report fad fa-trash'></span></td>
                    </tr>
					<?php
				}
				?>
            </table>
			<?php
			$returnArray['previous_reports'] = array("data_value" => ob_get_clean());
		}
		$returnArray['not_background_compatible'] = false;
		if (empty($returnArray['class_name']['data_value'])) {
			$returnArray['not_background_compatible'] = true;
		}
	}

	function beforeSaveChanges(&$dataValues) {
		$monthValues = "";
		foreach ($GLOBALS['gMonthArray'] as $month => $description) {
			if ($dataValues['month_' . $month]) {
				$monthValues .= (strlen($monthValues) == 0 ? "" : ",") . $dataValues['month_' . $month];
			}
		}
		$monthDayValues = "";
		for ($x = 1; $x <= 31; $x++) {
			if ($dataValues['month_day_' . $x]) {
				$monthDayValues .= (strlen($monthDayValues) == 0 ? "" : ",") . $dataValues['month_day_' . $x];
			}
		}
		$weekdayValues = "";
		foreach ($GLOBALS['gWeekdays'] as $weekday => $description) {
			if (strlen($dataValues['weekday_' . $weekday]) > 0) {
				$weekdayValues .= (strlen($weekdayValues) == 0 ? "" : ",") . $dataValues['weekday_' . $weekday];
			}
		}
		$hourValues = "";
		for ($x = 0; $x <= 23; $x++) {
			if (strlen($dataValues['hour_' . $x]) > 0) {
				$hourValues .= (strlen($hourValues) == 0 ? "" : ",") . $dataValues['hour_' . $x];
			}
		}
		switch ($dataValues['frequency']) {
			case "MINUTES":
				$monthValues = "";
				$monthDayValues = "";
				$weekdayValues = "";
				$hourValues = "";
				$dataValues['hour_minute'] = "";
				break;
			case "HOURLY":
				$monthValues = "";
				$monthDayValues = "";
				$weekdayValues = "";
				$hourValues = "";
				$dataValues['minute_interval'] = "";
				break;
			case "DAILY":
				$monthValues = "";
				$monthDayValues = "";
				$weekdayValues = "";
				$dataValues['minute_interval'] = "";
				break;
			case "WEEKLY":
				$monthValues = "";
				$monthDayValues = "";
				$dataValues['minute_interval'] = "";
				break;
			case "MONTHLY":
				$weekdayValues = "";
				$dataValues['minute_interval'] = "";
				break;
		}
		$dataValues['repeat_rules'] = $dataValues['frequency'] . ":" . $dataValues['minute_interval'] . ":" . $monthValues . ":" . $monthDayValues . ":" . $weekdayValues . ":" . $hourValues . ":" . $dataValues['hour_minute'];
		$dataValues['last_start_time'] = "";
		return true;
	}

	function hiddenElements() {
		?>
        <div id="_pdf_data" class="">
            <form id="_pdf_form">
            </form>
        </div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .delete-report {
                font-size: 1rem;
                cursor: pointer;
            }

            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_report_content {
                margin-bottom: 80px;
            }

            #_report_content table td {
                font-size: 13px;
            }

            #_button_row {
                margin-top: 40px;
                margin-bottom: 20px;
            }

            #_stored_report_wrapper {
                border: 1px solid rgb(200, 200, 200);
                padding: 40px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("stored_reports");
$pageObject->displayPage();
