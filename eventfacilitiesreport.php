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

$GLOBALS['gPageCode'] = "EVENTFACILITIESREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class EventFacilitiesReportPage extends Page implements BackgroundReport {

	function executePageUrlActions() {
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
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		processPresetDates($_POST['preset_dates'], "start_date_from", "start_date_to", true);

		$whereStatement = "client_id = ? and location_id in (select location_id from locations where inactive = 0 and product_distributor_id is null)";
		$parameters = array($GLOBALS['gClientId']);

		if (!empty($_POST['start_date_from'])) {
			$whereStatement .= " and start_date >= ?";
			$parameters[] = $GLOBALS['gPrimaryDatabase']->makeDateParameter($_POST['start_date_from']);
		}
		if (!empty($_POST['start_date_to'])) {
			$whereStatement .= " and start_date <= ?";
			$parameters[] = $GLOBALS['gPrimaryDatabase']->makeDateParameter($_POST['start_date_to']);
		}
        $locationId = getFieldFromId("location_id", "locations", "location_id", $_POST['location_id']);
		if (!empty($locationId)) {
			$whereStatement .= " and location_id = ?";
			$parameters[] = $locationId;
		}

		$exportReport = $_POST['report_type'] == "csv";
		$facilityType = getFieldFromId("description", "facility_types", "facility_type_id", $_POST['facility_type_id']);

		ob_start();

		$resultSet = executeReadQuery("select * from events where " . $whereStatement . " order by start_date", $parameters);
		$returnArray['report_title'] = "Event Facilities Report";
		$headers = array("Event", "Start Date", "Location", "Facilities", "Overbooked Facilities", $facilityType, "Missing " . $facilityType);
        $emptyReport = true;

		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"eventfacilities.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';

			echo createCsvRow($headers);
		} else {
			?>
			<table class="grid-table">
			<tr>
				<?php foreach ($headers as $thisHeader) {
					echo "<th>" . $thisHeader . "</th>";
				} ?>
			</tr>
			<?php
		}
		while($eventRow = getNextRow($resultSet)) {
			$facilities = array();
			$requiredTypeFacilities = array();
			$overbookedFacilities = array();
			$facilitySet = executeReadQuery("select facility_id, facility_type_id, description, date_needed, min(hour) start_hour,max(hour) end_hour from event_facilities join facilities using (facility_id) where event_id = ? group by facility_id, date_needed", $eventRow['event_id']);
			while ($facilityRow = getNextRow($facilitySet)) {
				if ($facilityRow['facility_type_id'] == $_POST['facility_type_id']) {
					$requiredTypeFacilities[$facilityRow['facility_id']] = array($facilityRow['description'], $facilityRow['date_needed'],
						Events::getDisplayTime($facilityRow['start_hour']), Events::getDisplayTime($facilityRow['end_hour'], true));
				} else {
					$facilities[$facilityRow['facility_id']] = array($facilityRow['description'], $facilityRow['date_needed'],
						Events::getDisplayTime($facilityRow['start_hour']), Events::getDisplayTime($facilityRow['end_hour'], true));
				}
				$otherEventId = getFieldFromId("event_id", "event_facilities", "facility_id", $facilityRow['facility_id'],
					"date_needed = ? and hour between ? and ? and event_id <> ?", $facilityRow['date_needed'], $facilityRow['start_hour'],
					$facilityRow['end_hour'], $eventRow['event_id']);
				if (!empty($otherEventId)) {
					$otherEventStartHour = getFieldFromId("min(hour)", "event_facilities", "facility_id", $facilityRow['facility_id'],
						"event_id = ? and date_needed = ?", $otherEventId, $facilityRow['date_needed']);
					$otherEventEndHour = getFieldFromId("max(hour)", "event_facilities", "facility_id", $facilityRow['facility_id'],
						"event_id = ? and date_needed = ?", $otherEventId, $facilityRow['date_needed']);
					$overbookedFacilities[$facilityRow['facility_id']] = array($facilityRow['description'], $facilityRow['date_needed'],
						Events::getDisplayTime($otherEventStartHour), Events::getDisplayTime($otherEventEndHour, true));
				}
			}
			if (!empty($_POST['exceptions_only']) && !empty($requiredTypeFacilities) && empty($overbookedFacilities)) {
				continue;
			}
            $emptyReport = false;
			$dataRow = array($eventRow['description'],
				$eventRow['start_date'],
				getFieldFromId("description", "locations", "location_id", $eventRow['location_id']),
				self::formatFacilities($facilities, $exportReport),
				self::formatFacilities($overbookedFacilities, $exportReport),
				self::formatFacilities($requiredTypeFacilities, $exportReport),
				empty($requiredTypeFacilities) ? "YES" : "");
			if ($exportReport) {
				echo createCsvRow($dataRow);
			} else {
				?>
				<tr>
					<?php foreach($dataRow as $thisItem) {
						echo "<td>" . htmlText($thisItem) . "</td>";
					} ?>
				</tr>
				<?php
			}
		}

		if ($exportReport) {
			$returnArray['filename'] = "eventfacilities.csv";
			$returnArray['report_export'] = ob_get_clean();
		} else {
			?>
			</table>
			<?php
			$returnArray['filename'] = "eventfacilities.pdf";
			$returnArray['report_content'] = ob_get_clean();
		}
		$returnArray['empty_report'] = $emptyReport;
		return $returnArray;
	}

	static function formatFacilities($facilitiesArray, $export) {
		$resultLines = array();
		$lineSeparator = $export ? "\n" : "<br>";
		$columnSeparator = $export ? ";" : ",";
		foreach($facilitiesArray as $thisFacility) {
			$resultLines[] = implode($columnSeparator,$thisFacility);
		}
		return implode($lineSeparator, $resultLines);
	}

	function mainContent() {
		?>
		<div id="report_parameters">
			<form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

				<div class="basic-form-line" id="_report_type_row">
					<label for="report_type">Output Type</label>
					<select tabindex="10" id="report_type" name="report_type">
						<option value="web">Web</option>
						<option value="csv">CSV</option>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<?php getPresetDateOptions(["future_only"=>true]) ?>

				<div class="basic-form-line preset-date-custom" id="_start_date_row">
					<label for="start_date_from">Start Date: From</label>
					<input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="start_date_from" name="start_date_from">
					<label for="start_date_to" class="second-label">Through</label>
					<input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="start_date_to" name="start_date_to">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_location_id_row">
					<label for="location_id">Location</label>
					<select tabindex="10" id="location_id" name="location_id">
						<option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is null order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_facility_type_id_row">
					<label for="facility_type_id">Facility Type to require for all events</label>
					<select tabindex="10" id="facility_type_id" name="facility_type_id">
						<?php
						$resultSet = executeReadQuery("select * from facility_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['facility_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label">Specify a facility type (e.g. Instructor) that every event must have.</span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_exceptions_only_row">
					<input tabindex="10" type="checkbox" id="exceptions_only" name="exceptions_only"><label class="checkbox-label" for="exceptions_only">Only show exceptions (missing or overbooked facilities)</label>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
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
			<button id="printable_button">Printable Report</button>
			<button id="pdf_button">Download PDF</button>
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
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("")
                    .append($("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html()))
                    .append($("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html()))
                    .append($("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html()))
                    .append($("<input>").attr("type", "hidden").attr("name", "filename").val("eventfacilities.pdf"))
                    .attr("action", "/reportpdf.php")
                    .attr("method", "POST")
                    .submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                const reportForm = $("#_report_form");
                if (reportForm.validationEngine("validate")) {
                    let reportType = $("#report_type").val();
                    if (reportType === "export" || reportType === "file" || reportType === "csv") {
                        reportForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", reportForm.serialize(), function (returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({scrollTop: 0}, "slow");
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
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
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

            .live-value {
                color: rgb(0, 200, 0);
                font-weight: bold;
            }
		</style>
		<style id="_printable_style">
            /*this style section will be used in the printable page and PDF document*/
            #_report_content {
                width: auto;
                display: block;
            }

            #_report_title {
                width: auto;
                display: block;
            }
		</style>
		<?php
	}
}

$pageObject = new EventFacilitiesReportPage();
$pageObject->displayPage();
