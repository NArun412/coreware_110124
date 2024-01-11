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

$GLOBALS['gPageCode'] = "FACILITIESREPORT";
require_once "shared/startup.inc";

class FacilitiesReportPage extends Page {

	var $iRunReport = false;

	function setup() {
		$this->iRunReport = array_key_exists("start_date", $_POST) && $_GET['url_action'] == "run_report";
		if (!array_key_exists("start_date", $_POST)) {
			$resultSet = executeQuery("select * from facilities where inactive = 0 and client_id = ? and facility_type_id not in " .
				"(select facility_type_id from facility_types where exclude_reports = 1)", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$_POST['facility_id_' . $row['facility_id']] = $row['facility_id'];
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "email_report":
				$programSettings = Page::getPagePreferences();
				$programSettings['email_address'] = $_POST['email_address'];
				Page::setPagePreferences($programSettings);
				ob_start();
				$this->generateReport("email");
				$reportContent = ob_get_clean();
				$reportContent = "<body style='width: 800px'>" . $reportContent . "</body>";
				$substitutions = array();
				$substitutions[] = array('class="report-subheader"', 'style="font-size: 16px; color: rgb(0,50,150); padding-top: 15px; padding-bottom: 5px; margin: 0;"');
				$substitutions[] = array('class="grid-table report-table"', 'style="margin-left: 20px; width: 780px; border: 1px solid rgb(150,150,150); border-collapse: collapse;"');
				$substitutions[] = array('class="quarter-cell"', 'style="width: 190px; border: 1px solid rgb(150,150,150);"');
				$substitutions[] = array('class="half-cell"', 'style="width: 400px; border: 1px solid rgb(150,150,150);"');
				foreach ($substitutions as $values) {
					$reportContent = str_replace($values[0], $values[1], $reportContent);
				}
				$facilitiesName = $this->getPageTextChunk("facility_name");
				$result = sendEmail(array("subject" => $_POST['time_range'] . (empty($facilitiesName) ? "" : " at the " . $facilitiesName), "body" => $reportContent, "email_address" => $_POST['email_address']));
				$returnArray['info_message'] = "Report Email Sent";
				ajaxResponse($returnArray);
				break;
			case "export_report":
				$programSettings = Page::getPagePreferences();
				Page::setPagePreferences($programSettings);
				ob_start();
				$this->generateReport("export");
				header("Content-Type: text/csv");
				header("Content-Disposition: attachment; filename=\"facilities_report.csv\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				echo ob_get_clean();
				exit;
		}
	}

	function generateReport($reportType = "web") {
		$domainName = getDomainName();
		if ($reportType == "web") {
			$this->buttonRow();
			?>
            <form name="_edit_form" id="_edit_form">
				<?php
				foreach ($_POST as $fieldName => $fieldData) {
					?>
                    <input type="hidden" id="<?= $fieldName ?>" name="<?= $fieldName ?>" value="<?= $fieldData ?>"/>
					<?php
				}
				?>
            </form>
			<?php
		}
		$sortOrder = $_POST['sort_order'];
		$facilityIds = "";
		foreach ($_POST as $fieldName => $fieldData) {
			if (!is_numeric($fieldData)) {
				continue;
			}
			if (substr($fieldName, 0, strlen("facility_id_")) == "facility_id_") {
				if (!empty($facilityIds)) {
					$facilityIds .= ",";
				}
				$facilityIds .= $fieldData;
			}
		}
		$startDate = (empty($_POST['start_date']) ? "1900-01-01" : date("Y-m-d", strtotime($_POST['start_date'])));
		$endDate = (empty($_POST['end_date']) ? "2100-12-31" : date("Y-m-d", strtotime($_POST['end_date'])));
		$firstDate = $lastDate = "";
		$eventArray = array();
		$resultSet = executeQuery("select * from event_facilities where facility_id in (" . $facilityIds . ") and " .
			"date_needed between ? and ? and event_id in (select event_id from events where client_id = ? and start_date <= ? and (end_date is null or end_date >= ?)) " .
			"order by facility_id,event_id,date_needed,hour",
			$startDate, $endDate, $GLOBALS['gClientId'], $endDate, $startDate);
		$currentDate = "";
		$currentFacilityId = "";
		$lastHour = "";
		$currentEventId = "";
		$index = -1;
		$eventRevenue = array();
		while ($row = getNextRow($resultSet)) {
			if (empty($firstDate) || $row['date_needed'] < $firstDate) {
				$firstDate = $row['date_needed'];
			}
			if (empty($lastDate) || $row['date_needed'] > $lastDate) {
				$lastDate = $row['date_needed'];
			}
			$facilityTypeId = getFieldFromId("facility_type_id", "facilities", "facility_id", $row['facility_id']);
			$checkSet = executeQuery("select * from facility_closures where client_id = ? and (facility_id = ? or (facility_id is null and facility_type_id = ?) or " .
				"(facility_id is null and facility_type_id is null)) and closure_date = ?", $GLOBALS['gClientId'], $row['facility_id'], $facilityTypeId, $row['date_needed']);
			if ($checkRow = getNextRow($checkSet)) {
				continue;
			}
			if (!array_key_exists($row['event_id'], $eventRevenue)) {
				$revenue = getFieldFromId("cost", "events", "event_id", $row['event_id']);
				$eventRevenue[$row['event_id']] = $revenue;
			}
			if ($currentFacilityId != $row['facility_id'] || $currentDate != $row['date_needed'] || $currentEventId != $row['event_id']) {
				$index++;
				$eventArray[$index] = array("event_id" => $row['event_id'], "facility_id" => $row['facility_id'],
					"sort_order" => getFieldFromId("sort_order", "facilities", "facility_id", $row['facility_id']),
					"description" => getFieldFromId("description", "facilities", "facility_id", $row['facility_id']),
					"date_needed" => $row['date_needed'], "start" => $row['hour'], "end" => $row['hour']);
				$lastHour = $row['hour'];
				$currentDate = $row['date_needed'];
				$currentEventId = $row['event_id'];
				$currentFacilityId = $row['facility_id'];
				continue;
			}
			if ($row['hour'] <= ($lastHour + .25)) {
				if ($row['hour'] > $lastHour) {
					$eventArray[$index]["end"] = $row['hour'];
				}
			} else {
				$index++;
				$eventArray[$index] = array("event_id" => $row['event_id'], "facility_id" => $row['facility_id'],
					"sort_order" => getFieldFromId("sort_order", "facilities", "facility_id", $row['facility_id']),
					"description" => getFieldFromId("description", "facilities", "facility_id", $row['facility_id']),
					"date_needed" => $row['date_needed'], "start" => $row['hour'], "end" => $row['hour']);
			}
			$lastHour = $row['hour'];
			$currentDate = $row['date_needed'];
			$currentEventId = $row['event_id'];
			$currentFacilityId = $row['facility_id'];
		}

		$recurrenceArray = array();
		$currentEventId = "";
		$currentFacilityId = "";
		$currentRepeatRules = "";
		$lastHour = "";
		$recurIndex = -1;
		$resultSet = executeQuery("select * from event_facility_recurrences where facility_id in (" . $facilityIds .
			") and event_id in (select event_id from events where client_id = ? and start_date <= ? and (end_date is null or end_date >= ?)) " .
			"order by event_id,facility_id,repeat_rules,hour", $GLOBALS['gClientId'], $endDate, $startDate);
		while ($row = getNextRow($resultSet)) {
			if ($currentFacilityId != $row['facility_id'] || $currentEventId != $row['event_id'] || $currentRepeatRules != $row['repeat_rules']) {
				$recurIndex++;
				$row['start'] = $row['hour'];
				$row['end'] = $row['hour'];
				$recurrenceArray[$recurIndex] = $row;
				$lastHour = $row['hour'];
				$currentEventId = $row['event_id'];
				$currentFacilityId = $row['facility_id'];
				$currentRepeatRules = $row['repeat_rules'];
				continue;
			}
			if ($row['hour'] <= ($lastHour + .25)) {
				if ($row['hour'] > $lastHour) {
					$recurrenceArray[$recurIndex]["end"] = $row['hour'];
				}
			} else {
				$recurIndex++;
				$row['start'] = $row['hour'];
				$row['end'] = $row['hour'];
				$recurrenceArray[$recurIndex] = $row;
			}
			$lastHour = $row['hour'];
			$currentEventId = $row['event_id'];
			$currentFacilityId = $row['facility_id'];
			$currentRepeatRules = $row['repeat_rules'];
		}
		$useDate = $firstDate;
		while ($useDate <= $lastDate) {
			foreach ($recurrenceArray as $repeatIndex => $row) {
				$facilityTypeId = getFieldFromId("facility_type_id", "facilities", "facility_id", $row['facility_id']);
				$checkSet = executeQuery("select * from facility_closures where client_id = ? and (facility_id = ? or (facility_id is null and facility_type_id = ?) or " .
					"(facility_id is null and facility_type_id is null)) and closure_date = ?", $GLOBALS['gClientId'], $row['facility_id'], $facilityTypeId, $useDate);
				if ($checkRow = getNextRow($checkSet)) {
					continue;
				}
				if (empty($row['repeat_rules'])) {
					continue;
				}
				if (isInSchedule($useDate, $row['repeat_rules'])) {
					$revenue = getFieldFromId("cost", "events", "event_id", $row['event_id']);
					if (!array_key_exists($row['event_id'], $eventRevenue)) {
						$eventRevenue[$row['event_id']] = $revenue;
					} else {
						$eventRevenue[$row['event_id']] += $revenue;
					}
					$eventArray[] = array("event_id" => $row['event_id'], "facility_id" => $row['facility_id'],
						"sort_order" => getFieldFromId("sort_order", "facilities", "facility_id", $row['facility_id']),
						"description" => getFieldFromId("description", "facilities", "facility_id", $row['facility_id']),
						"date_needed" => $useDate, "start" => $row['start'], "end" => $row['end']);
				}
			}
			$useDate = date("Y-m-d", strtotime("+1 day", strtotime($useDate)));
		}
		$totalTime = 0;
		foreach ($eventArray as $row) {
			$totalTime += .25 + ($row['end'] - $row['start']);
		}
		$totalHours = floor($totalTime);
		if ($reportType == "export") {
			if ($sortOrder == "facility_order") {
				echo createCsvRow(array("Date", "Start Time", "End Time", "Facility", "Description", "Contact"));
			} else {
				echo createCsvRow(array("Facility", "Date", "Start Time", "End Time", "Description", "Contact", "User Type", "Visitor Log"));
			}
		} else {
			if ($reportType == "email") {
				?>
                <img alt='email header' src="<?= $domainName ?>/getimage.php?code=email_header">
				<?php
			}
			?>
            <div id="_report_content">
            <p class="highlighted-text">Total time of use: <?= $totalHours ?> hours<?= ($totalTime == $totalHours ? "" : ", " . (($totalTime - $totalHours) * 60) . " minutes") ?>. Revenue: <?= number_format(array_sum($eventRevenue), 2) ?></p>
			<?php
		}
		if ($sortOrder == "facility_order") {
			uasort($eventArray, array($this, "facilitySortOrder"));
			$saveFacilityId = "";
			foreach ($eventArray as $row) {
				if ($saveFacilityId != $row['facility_id']) {
					if (!empty($saveFacilityId) && $reportType !== 'export') {
						echo "</table>";
					}
					if ($reportType !== "export") {
						?>
                        <p class="report-subheader"><?= $row['description'] ?></p>
                        <table class="grid-table report-table">
						<?php
					}
					$saveFacilityId = $row['facility_id'];
				}
				$thisStartDate = $row['date_needed'] . " " . floor($row['start']) . ":" . str_pad(($row['start'] - floor($row['start'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
				$thisEndDate = $row['date_needed'] . " " . floor($row['end'] + .25) . ":" . str_pad(($row['end'] + .25 - floor($row['end'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
				$description = getFieldFromId("description", "events", "event_id", $row['event_id']);
				$contact = getDisplayName(getFieldFromId("contact_id", "events", "event_id", $row['event_id']));
				$descriptionWithContact = $description . (empty($contact) ? "" : ", " . $contact);
				if ($reportType !== "export") {
					?>
                    <tr>
                        <td class="half-cell"><?= date("l, F d, Y", strtotime($thisStartDate)) . " " . date("g:i a", strtotime($thisStartDate)) . "-" . date("g:i a", strtotime($thisEndDate)) ?></td>
                        <td class="half-cell"><?= htmlText($descriptionWithContact) ?></td>
                    </tr>
					<?php
				} else {
					echo createCsvRow(array($row['description'], date("Y-m-d", strtotime($thisStartDate)), date("g:i a", strtotime($thisStartDate)),
						date("g:i a", strtotime($thisEndDate)), $description, $contact));
				}
			}
			if (!empty($saveFacilityId) && $reportType !== "export") {
				?>
                </table>
				<?php
			}
		} else {
			uasort($eventArray, array($this, "dateSortOrder"));
			$useDate = $startDate;
			while ($useDate <= $endDate) {
				if ($reportType !== "export") {
					?>
                    <p class="report-subheader"><?= date("l, F d, Y", strtotime($useDate)) ?></p>
                    <table class="grid-table report-table">
					<?php
				}
				$lineCount = 0;
				foreach ($eventArray as $row) {
					if ($useDate != $row['date_needed']) {
						continue;
					}
					$thisStartDate = $row['date_needed'] . " " . floor($row['start']) . ":" . str_pad(($row['start'] - floor($row['start'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
					$thisEndDate = $row['date_needed'] . " " . floor($row['end'] + .25) . ":" . str_pad(($row['end'] + .25 - floor($row['end'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
					$facility = getFieldFromId("description", "facilities", "facility_id", $row['facility_id']);
					$description = getFieldFromId("description", "events", "event_id", $row['event_id']);
					$contactId = getFieldFromId("contact_id", "events", "event_id", $row['event_id']);
					if (empty($contactId)) {
						$userTypeId = $userTypeDescription = $contactName = "";
					} else {
						$userTypeId = getFieldFromId('user_type_id', 'users', 'contact_id', $contactId);
						$userTypeDescription = getFieldFromId('description', 'user_types', 'user_type_id', $userTypeId);
						$contactName = getDisplayName($contactId);
					}
					$visitorLogId = getFieldFromId("visitor_log_id", "visitor_log", "contact_id", $contactId, "date(visit_time) = ?", $useDate);
					if ($reportType !== "export") {
						?>
                        <tr>
                            <td class="quarter-cell"><?= date("g:i a", strtotime($thisStartDate)) . "-" . date("g:i a", strtotime($thisEndDate)) ?></td>
                            <td class="quarter-cell"><?= htmlText($facility) ?></td>
                            <td class="quarter-cell" data-contact_id="<?= $contactId ?>"><?= htmlText($description . (empty($contactId) ? "" : " - " . $contactName)) ?></td>
                            <td class="quarter-cell"><?= htmlText($userTypeDescription) . (empty($visitorLogId) ? (empty($userTypeDescription) ? "" : " - ") . "<span class='red-text'>No Visitor Log</span>" : "") ?></td>
                        </tr>
						<?php
					} else {
						echo createCsvRow(array(date("Y-m-d", strtotime($useDate)), date("g:i a", strtotime($thisStartDate)),
							date("g:i a", strtotime($thisEndDate)), $facility, $description, $contactName, $userTypeDescription, (empty($visitorLogId) ? "NO" : "YES")));
					}
					$lineCount++;
				}
				$closureSet = executeReadQuery("select * from facility_closures where client_id = ? and closure_date = ?", $GLOBALS['gClientId'], $useDate);
				while ($closureRow = getNextRow($closureSet)) {
					$newHours = false;
					if (empty($closureRow['start_time']) && empty($closureRow['end_time'])) {
						$closureTime = "All Day";
					} else if (empty($closureRow['start_time'])) {
						$closureTime = "From " . date("g:i a", strtotime($closureRow['end_time']));
					} else if (empty($closureRow['end_time'])) {
						$closureTime = "Until " . date("g:i a", strtotime($closureRow['start_time']));
					} else {
						$newHours = true;
						$closureTime = date("g:i a", strtotime($closureRow['start_time'])) . "-" . date("g:i a", strtotime($closureRow['end_time']));
					}
					$closureFacility = "";
					if (!empty($closureRow['facility_id'])) {
						$closureFacility = getFieldFromId("description", "facilities", "facility_id", $closureRow['facility_id']);
					}
					if (!empty($closureRow['facility_type_id'])) {
						$closureFacility .= (empty($closureFacility) ? "" : " & ") . getFieldFromId("description", "facility_types", "facility_type_id", $closureRow['facility_type_id']);
					}
					if (empty($closureFacility)) {
						$closureFacility = $this->getPageTextChunk("facility_name");
						if (empty($closureFacility)) {
							$closureFacility = "Whole Building";
						}
					}
					if ($reportType !== "export") {
						?>
                        <tr>
                            <td class="quarter-cell"><?= $closureTime ?></td>
                            <td class="quarter-cell"><?= $closureFacility ?></td>
                            <td class="quarter-cell color-red"><?= ($newHours ? "NEW HOURS" : "CLOSED") ?></td>
                            <td class="quarter-cell color-red"><?= $closureRow['notes'] ?></td>
                        </tr>
						<?php
					} else {
						echo createCsvRow(array(date("Y-m-d", strtotime($useDate)), date("g:i a", strtotime($closureRow['start_time'])),
							date("g:i a", strtotime($closureRow['end_time'])), $closureFacility, ($newHours ? "NEW HOURS" : "CLOSED") . " " . $closureRow['notes'], "", "", ""));
					}
					$lineCount++;
				}
				if ($lineCount == 0 && $reportType !== "export") {
					?>
                    <tr>
                        <td>Nothing Scheduled</td>
                    </tr>
					<?php
				}
				if ($reportType !== "export") {
					?>
                    </table>
					<?php
				}
				$useDate = date("Y-m-d", strtotime("+1 day", strtotime($useDate)));
			}
		}
		if ($reportType == "web") {
			?>
            <p>&nbsp;</p>
            <p>Facility Usage for <?= date("l, F d, Y", strtotime($startDate)) ?> through <?= date("l, F d, Y", strtotime($endDate)) ?></p>
            <p>Report run by <?= getUserDisplayName() ?> on <?= date("m/d/Y") ?> at <?= date("g:i:s a") ?></p>
		<?php }
		if ($reportType !== "export") {
			?>
            </div>
			<?php
		}
	}

	function buttonRow() {
		$buttonFunctions = array(
			"run" => array("accesskey" => "r", "label" => "Run Report"),
			"change" => array("accesskey" => "c", "label" => "Change Report"),
			"week" => array("accesskey" => "w", "label" => "This Week"),
			"month" => array("accesskey" => "m", "label" => "This Month"),
			"today_plus_30_days" => array("accesskey" => "m", "label" => "Today +30 Days"),
			"last_month" => array("accesskey" => "m", "label" => "Last Month"),
			"year" => array("accesskey" => "m", "label" => "YTD"),
			"email_report" => array("label" => "Email Report"),
			"export_report" => array("label" => "Export Report")
		);
		?>
        <div id="button_row">
			<?php
			foreach ($buttonFunctions as $buttonName => $buttonInfo) {
				?>
                <button tabindex="9000"<?= (empty($buttonInfo['accesskey']) ? "" : " accesskey='" . $buttonInfo['accesskey'] . "'") ?> class="enabled-button" id="_<?= $buttonName ?>_button"><?= $buttonInfo['label'] ?></button>
				<?php
			}
			?>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if ($this->iRunReport) { ?>
            disableButtons($("#_run_button"));
            disableButtons($("#_week_button"));
            disableButtons($("#_month_button"));
            disableButtons($("#_today_plus_30_days_button"));
            disableButtons($("#_last_month_button"));
            disableButtons($("#_year_button"));
			<?php } else { ?>
            disableButtons($("#_change_button"));
            disableButtons($("#_email_report_button"));
            disableButtons($("#_export_report_button"));
			<?php } ?>
            $("#_email_report_button").click(function () {
                $('#email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 500,
                    title: 'Email Report',
                    buttons: {
                        Send: function (event) {
                            if ($("#_edit_form").validationEngine('validate')) {
                                $("#email_address").val($("#entry_email").val());
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=email_report", $("#_edit_form").serialize());
                                $("#email_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#email_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $("#_export_report_button").click(function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=export_report").attr("method", "POST").submit();
                }
            });
            $("#_run_button").click(function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=run_report").attr("method", "POST").submit();
                }
                return false;
            });
            $("#_change_button").click(function () {
                $("#_edit_form").attr("method", "POST").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>").submit();
            });
            $(".facility-list-header").click(function () {
                $(this).closest("div").find("input[type=checkbox]").prop("checked", !$(this).closest("div").find("input[type=checkbox]:first").prop("checked"));
            });
            $("#_week_button").click(function () {
                const monday = getMonday();
                $("#start_date").val($.formatDate(monday, "MM/dd/yyyy"));
                $("#end_date").val($.formatDate(getSunday(monday), "MM/dd/yyyy"));
                $("#time_range").val("This Week");
                return false;
            });
            $("#_month_button").click(function () {
                $("#start_date").val($.formatDate(getFirst(), "MM/dd/yyyy"));
                $("#end_date").val($.formatDate(getLast(), "MM/dd/yyyy"));
                $("#time_range").val("This Month");
                return false;
            });
            $("#_year_button").click(function () {
                $("#start_date").val("01/01/<?= date("Y") ?>");
                $("#end_date").val("<?= date("m/d/Y") ?>");
                $("#time_range").val("This Year");
                return false;
            });
            $("#_last_month_button").click(function () {
                $("#start_date").val("<?= date("m/d/Y", mktime(0, 0, 0, date("m") - 1, 1, date("Y"))) ?>");
                $("#end_date").val("<?= date("m/d/Y", mktime(0, 0, 0, date("m"), 0, date("Y"))) ?>");
                $("#time_range").val("Last Month");
                return false;
            });
            $("#_today_plus_30_days_button").click(function () {
                $("#start_date").val("<?= date("m/d/Y") ?>");
                $("#end_date").val("<?= date("m/d/Y", strtotime("+30 days")) ?>");
                $("#time_range").val("Today +30 Days");
                return false;
            });
            $(document).on("click", "#_show_button", function () {
                $("#_report_content").slideDown("fast");
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function getMonday() {
                const today = new Date();
                const day = today.getDay();
                const diff = today.getDate() - day + 1;
                return new Date(today.setDate(diff));
            }

            function getSunday(monday) {
                const day = monday.getDate() + 6;
                return new Date(monday.setDate(day));
            }

            function getFirst() {
                const today = new Date();
                return new Date(today.setDate(1));
            }

            function getLast() {
                const today = new Date();
                return (new Date((new Date(today.getFullYear(), today.getMonth() + 1, 1)) - 1));
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_report_content {
                margin-top: 20px;
            }

            #_report_criteria {
                margin-top: 20px;
            }

            #_select_facilities {
                padding: 20px;
                border: 1px solid rgb(200, 200, 200);
                overflow: auto;
            }

            .facility-list {
                float: left;
                padding-right: 20px;
            }

            .facility-list p {
                padding: 0;
            }

            .facility-list-header {
                cursor: pointer;
            }

            .report-table {
                margin-left: 20px;
                width: 90%;
                max-width: 1200px;
            }

            .report-subheader {
                font-size: 16px;
                color: rgb(0, 50, 150);
                padding-top: 15px;
                padding-bottom: 5px;
                margin: 0;
            }

            .half-cell {
                width: 50%;
            }

            .quarter-cell {
                width: 25%;
            }

            td {
                font-size: 13px;
            }

            #button_row {
                margin: 30px 0 0 0;
            }
        </style>
		<?php
	}

	function mainContent() {
		if ($this->iRunReport) {
			$this->generateReport();
		} else {
			$this->reportForm();
		}
	}

	function reportForm() {
		$this->buttonRow();
		$startDate = (empty($_POST['start_date']) ? "" : date("m/d/Y", strtotime($_POST['start_date'])));
		$endDate = (empty($_POST['end_date']) ? "" : date("m/d/Y", strtotime($_POST['end_date'])));
		?>
        <div id="_report_criteria">
            <form name="_edit_form" id="_edit_form">
                <input type="hidden" name="email_address" id="email_address">
                <input type="hidden" name="time_range" id="time_range" value="This Week">

                <div class='basic-form-line'>
                    <label for="start_date">Start Date</label>
                    <input tabindex="10" type="text" name="start_date" id="start_date" class="field-text validate[custom[date]] datepicker" size="12" maxlength="12" value="<?= $startDate ?>"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='basic-form-line'>
                    <label for="end_date">End Date</label>
                    <input tabindex="10" type="text" name="end_date" id="end_date" class="field-text validate[custom[date]] datepicker" size="12" maxlength="12" value="<?= $endDate ?>"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='basic-form-line'>
                    <label for="sort_order">Organize By</label>
                    <select tabindex="10" name="sort_order" id="sort_order">
                        <option value="date_order"<?= ($_POST['sort_order'] == "date_order" ? " selected" : "") ?>>Date</option>
                        <option value="facility_order"<?= ($_POST['sort_order'] == "facility_order" ? " selected" : "") ?>>Facility</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div id="_select_facilities">
                    <p class='subheader'>Facilities to include in report (click on facility type to select all)</p>
					<?php
					$facilityCount = 0;
					$resultSet = executeQuery("select * from facility_types where client_id = ? and facility_type_id in (select facility_type_id from facilities) order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <div class="facility-list">
                            <p class="highlighted-text facility-list-header"><?= htmlText($row['description']) ?></p>
							<?php
							$facilitySet = executeQuery("select * from facilities where inactive = 0 and client_id = ? and facility_type_id = ? order by sort_order,description", $GLOBALS['gClientId'], $row['facility_type_id']);
							while ($facilityRow = getNextRow($facilitySet)) {
								?>
                                <p><input tabindex="10" rel="facility_ids" type="checkbox" data-description="<?= htmlText($facilityRow['description']) ?>" class="facility-type-id validate[minCheckbox[1]]" id="facility_id_<?= $facilityRow['facility_id'] ?>" name="facility_id_<?= $facilityRow['facility_id'] ?>" value="<?= $facilityRow['facility_id'] ?>"<?= (!empty($_POST['facility_id_' . $facilityRow['facility_id']]) ? " checked" : "") ?> /><label class="checkbox-label" for="facility_id_<?= $facilityRow['facility_id'] ?>"><?= htmlText($facilityRow['description']) ?></label></p>
								<?php
							}
							?>
                        </div>
						<?php
					}
					$columnCount = max(ceil($facilityCount / 6), 6);
					$saveFacilityType = "";
					?>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>
		<?php
	}

	function facilitySortOrder($a, $b) {
		if ($a['facility_id'] != $b['facility_id']) {
			if ($a['sort_order'] == $b['sort_order']) {
				if ($a['description'] == $b['description']) {
					return ($a['facility_id'] < $b['facility_id'] ? -1 : 1);
				} else {
					return ($a['description'] < $b['description'] ? -1 : 1);
				}
			} else {
				return ($a['sort_order'] < $b['sort_order'] ? -1 : 1);
			}
		}
		if ($a['date_needed'] != $b['date_needed']) {
			return ($a['date_needed'] < $b['date_needed'] ? -1 : 1);
		}
		if ($a['start'] != $b['start']) {
			return ($a['start'] < $b['start'] ? -1 : 1);
		}
		if ($a['end'] == $b['end']) {
			return 0;
		} else {
			return ($a['end'] < $b['end'] ? -1 : 1);
		}
	}

	function dateSortOrder($a, $b) {
		if ($a['date_needed'] != $b['date_needed']) {
			return ($a['date_needed'] < $b['date_needed'] ? -1 : 1);
		}
		if ($a['start'] != $b['start']) {
			return ($a['start'] < $b['start'] ? -1 : 1);
		}
		if ($a['facility_id'] != $b['facility_id']) {
			if ($a['sort_order'] == $b['sort_order']) {
				if ($a['description'] == $b['description']) {
					return ($a['facility_id'] < $b['facility_id'] ? -1 : 1);
				} else {
					return ($a['description'] < $b['description'] ? -1 : 1);
				}
			} else {
				return ($a['sort_order'] < $b['sort_order'] ? -1 : 1);
			}
		}
		return 0;
	}

	function jqueryTemplates() {
		$programSettings = Page::getPagePreferences();
		$emailAddress = $programSettings['email_address'];
		?>
        <div id="email_dialog" class="dialog-box">
            <div class="shorter-label">
                <div class="basic-form-line" id="_entry_email_row">
                    <label for="entry_email">Email Address</label>
                    <input type="text" class="validate[required,custom[email]]" size="30" id="entry_email" name="entry_email" value="<?= $emailAddress ?>">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </div>
        </div>
		<?php
	}
}

$pageObject = new FacilitiesReportPage();
$pageObject->displayPage();
