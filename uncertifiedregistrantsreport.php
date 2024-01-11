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

$GLOBALS['gPageCode'] = "UNCERTIFIEDREGISTRANTSREPORT";
require_once "shared/startup.inc";

class UncertifiedRegistrantsPage extends Page implements BackgroundReport {

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
		$displayCriteria = "";

		if (empty($_POST['report_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "start_date >= current_date";
			$parameters[] = makeDateParameter($_POST['report_date_from']);
		} else {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "start_date >= ?";
			$parameters[] = makeDateParameter($_POST['report_date_from']);
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "start_date <= ?";
			$parameters[] = makeDateParameter($_POST['report_date_to']);
		}
		if (!empty($_POST['report_date_from']) && !empty($_POST['report_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Event date is between " . date("m/d/Y", strtotime($_POST['report_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['report_date_to']));
		} else {
			if (!empty($_POST['report_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Event date is on or after " . date("m/d/Y", strtotime($_POST['report_date_from']));
			} else {
				if (!empty($_POST['report_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Event date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
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
			$displayCriteria .= "Event type is " . getFieldFromId("description", "event_types", "event_type_id", $_POST['event_type_id']);
		}

		$detailReport = $_POST['report_type'] == "detail";
		ob_start();

		$resultSet = executeReadQuery("select * from event_registrants join contacts using (contact_id) where event_id in (select event_id from events where client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") .
			" and event_type_id in (select event_type_id from event_type_requirements)) order by last_name,first_name", $parameters);
		$returnArray['report_title'] = "Uncertified Registrants Report";
		?>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table class='grid-table'>
            <tr>
                <th>Contact</th>
                <th>Registered Event</th>
                <th>Requirements</th>
                <th>Certifications</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				$eventRow = getRowFromId("events", "event_id", $row['event_id']);
				$eventTypeRow = getRowFromId("event_types", "event_type_id", $eventRow['event_type_id']);
				$requirements = "";
				$requirementsSet = executeQuery("select * from event_type_requirements join certification_types using (certification_type_id) where event_type_id = ? order by description", $eventTypeRow['event_type_id']);
				while ($requirementsRow = getNextRow($requirementsSet)) {
					$requirements .= (empty($requirements) ? "" : "<br>") . $row['description'];
				}
				$certifications = "";
				$requirementsSet = executeQuery("select * from contact_certifications join certification_types using (certification_type_id) where contact_id = ? and (expiration_date is null or expiration_date >= ?) order by description",
					$row['contact_id'], $eventRow['start_date']);
				while ($requirementsRow = getNextRow($requirementsSet)) {
					$certifications .= (empty($certifications) ? "" : "<br>") . $row['description'];
				}
				?>
                <tr>
                    <td><?= htmlText(getDisplayName($row['contact_id'])) ?></td>
                    <td><?= htmlText($eventTypeRow['description']) . " - " . date("m/d/Y", strtotime($eventRow['start_date'])) ?></td>
                    <td><?= htmlText($requirements) ?></td>
                    <td><?= htmlText($certifications) ?></td>
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

# The report form is where the user can set parameters for how the report would be run.

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

				<?php getPresetDateOptions() ?>

                <div class="form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Event Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_event_type_id_row">
                    <label for="event_type_id">Class</label>
                    <select id="event_type_id" name="event_type_id" class="">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeQuery("select * from event_types where client_id = ? and event_type_id in (select event_type_id from event_type_requirements) order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['event_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="form-line">
                    <label></label>
                    <button tabindex="10" id="create_report">Create Report</button>
                    <div class='clear-div'></div>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
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

$pageObject = new UncertifiedRegistrantsPage();
$pageObject->displayPage();
