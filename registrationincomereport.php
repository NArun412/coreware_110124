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

$GLOBALS['gPageCode'] = "REGISTRATIONINCOMEREPORT";
require_once "shared/startup.inc";

class RegistrationIncomeReportPage extends Page implements BackgroundReport {

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
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		processPresetDates($_POST['preset_dates'], "report_date_from", "report_date_to");

# Here, you would use the report parameters in $_POST to construct a where statement to get the data and construct the report

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$parameters[] = $GLOBALS['gClientId'];
		$displayCriteria = "";

		if (!empty($_POST['report_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "events.start_date >= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_from']));
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "events.start_date <= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_to'])) . " 23:59:59";
		}
		if (!empty($_POST['report_date_from']) && !empty($_POST['report_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Report date is between " . date("m/d/Y", strtotime($_POST['report_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['report_date_to']));
		} else {
			if (!empty($_POST['report_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Report date is on or after " . date("m/d/Y", strtotime($_POST['report_date_from']));
			} else {
				if (!empty($_POST['report_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Report date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
		}
		if (!empty($_POST['event_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "event_type_id = ?";
			$parameters[] = $_POST['event_type_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Event Type is " . getFieldFromId("description", "event_types", "event_type_id", $_POST['event_type_id']);
		}
		if (!empty($_POST['location_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "events.location_id = ?";
			$parameters[] = $_POST['location_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Location is " . getFieldFromId("description", "locations", "location_id", $_POST['location_id']);
		}

		ob_start();

		$resultSet = executeReadQuery("select (select description from locations where location_id = events.location_id) as location_description," .
			"(select description from event_types where event_type_id = events.event_type_id) as event_type_description," .
			"(select count(*) from event_registrants where event_id in (select event_id from events as inner_events where client_id = ? and " .
			"inner_events.location_id = events.location_id and event_type_id = events.event_type_id)) as registrant_count, sum(sale_price * quantity) as total_income " .
			"from order_items join events using (product_id) where " .
			"events.client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " group by location_description,event_type_description order by location_description,event_type_description", $parameters);
		$returnArray['report_title'] = "Registration Income Report";
		if ($_POST['report_type'] == "csv") {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"registrations.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "registrations.csv";
			echo "Location,Event Type,Registrants,Income\n";
		} else {
			?>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
            <tr>
                <th>Location</th>
                <th>Event Type</th>
                <th class="align-right">Registrants</th>
                <th class="align-right">Income</th>
            </tr>
			<?php
		}
		while ($row = getNextRow($resultSet)) {
			if ($_POST['report_type'] == "csv") {
				echo '"' . str_replace('"', '', $row['location_description']) . '",' .
					'"' . str_replace('"', '', $row['event_type_description']) . '",' .
					'"' . $row['registrant_count'] . '",' .
					'"' . number_format($row['total_income'], 2) . '"' . "\n";
			} else {
				?>
                <tr>
                    <td><?= htmlText($row['location_description']) ?></td>
                    <td><?= htmlText($row['event_type_description']) ?></td>
                    <td class="align-right"><?= number_format($row['registrant_count'], 0) ?></td>
                    <td class="align-right"><?= number_format($row['total_income'], 2) ?></td>
                </tr>
				<?php
			}
		}
		if ($_POST['report_type'] == "csv") {
			$returnArray['report_export'] = ob_get_clean();
		} else {
			?>
            </table>
			<?php
			$returnArray['report_content'] = ob_get_clean();
		}
		return $returnArray;
	}

	function mainContent() {

# The report form is where the user can set parameters for how the report would be run.

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">CSV Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Report Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_event_type_id_row">
                    <label for="event_type_id">Event Type</label>
                    <select tabindex="10" id="event_type_id" name="event_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from event_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['event_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select tabindex="10" id="location_id" name="location_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from locations where client_id = ? and product_distributor_id is null and user_location = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
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
                const $pdfForm = $("#_pdf_form");
                $pdfForm.html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("registrationincome.pdf");
                $pdfForm.append($(input));
                $pdfForm.attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                const $reportForm = $("#_report_form");
                if ($reportForm.validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "csv") {
                        $reportForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, 600);
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

$pageObject = new RegistrationIncomeReportPage();
$pageObject->displayPage();
