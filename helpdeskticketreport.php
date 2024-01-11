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

$GLOBALS['gPageCode'] = "HELPDESKTICKETREPORT";
require_once "shared/startup.inc";

class HelpDeskTicketReportPage extends Page implements BackgroundReport {

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

		if (!empty($_POST['report_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "time_submitted >= ?";
			$parameters[] = date("Y-m-d",strtotime($_POST['report_date_from']));
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "time_submitted <= ?";
			$parameters[] = date("Y-m-d",strtotime($_POST['report_date_to'])) . " 23:59:59";
		}
		if (!empty($_POST['report_date_from']) && !empty($_POST['report_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Time submitted is between " . date("m/d/Y", strtotime($_POST['report_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['report_date_to']));
		} else {
			if (!empty($_POST['report_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Time submitted is on or after " . date("m/d/Y", strtotime($_POST['report_date_from']));
			} else {
				if (!empty($_POST['report_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Time submitted is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
		}
		if (!empty($_POST['exclude_feature_requests'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(help_desk_status_id is null or help_desk_status_id not in (select help_desk_status_id from help_desk_statuses where long_term_project = 1))";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "exclude long term projects";
		}
		if (!empty($_POST['help_desk_tag_group_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "help_desk_entry_id in (select help_desk_entry_id from help_desk_tag_links where help_desk_tag_id in (select help_desk_tag_id from help_desk_tags where help_desk_tag_group_id = ?))";
			$parameters[] = $_POST['help_desk_tag_group_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Help Desk Tag group is " . getFieldFromId("description","help_desk_tag_groups","help_desk_tag_group_id",$_POST['help_desk_tag_group_id']);
		}
		if (!empty($_POST['help_desk_tag_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "help_desk_entry_id in (select help_desk_entry_id from help_desk_tag_links where help_desk_tag_id = ?)";
			$parameters[] = $_POST['help_desk_tag_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Help Desk Tag is " . getFieldFromId("description","help_desk_tags","help_desk_tag_id",$_POST['help_desk_tag_id']);
		}

		ob_start();

		$resultSet = executeReadQuery("select user_id,(select concat(last_name,first_name) from contacts where contact_id = (select contact_id from users where user_id = help_desk_entries.user_id)) full_name," .
			"time_closed from help_desk_entries where client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : ""), $parameters);
		$dataArray = array();
		while ($row = getNextRow($resultSet)) {
			$keyField = $row['full_name'] . ":" . $row['user_id'];
			if (!array_key_exists($keyField, $dataArray)) {
				$dataArray[$keyField] = array("user_id" => $row['user_id'], "open" => 0, "closed" => 0, "closed_today" => 0, "closed_week" => 0);
			}
			if (empty($row['time_closed'])) {
				$dataArray[$keyField]['open']++;
			} else {
				$dataArray[$keyField]['closed']++;
				if (date("Y-m-d", strtotime($row['time_closed'])) == date("Y-m-d")) {
					$dataArray[$keyField]['closed_today']++;
				}
				$sunday = date('Y-m-d', strtotime('last sunday'));
				if (date("Y-m-d", strtotime($row['time_closed'])) >= $sunday) {
					$dataArray[$keyField]['closed_week']++;
				}
			}
		}
		ksort($dataArray);
		$returnArray['report_title'] = "Help Desk Ticket Report";
		?>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table class='header-sortable grid-table' id="_help_desk_ticket_report">
            <tr class='header-row'>
                <th colspan="2"></th>
                <th class='align-center' colspan="3">Closed</th>
            </tr>
            <tr class='header-row'>
                <th>User</th>
                <th class='align-center'>Open</th>
                <th class='align-center'>Total</th>
                <th class='align-center'>Today</th>
                <th class='align-center'>This Week</th>
            </tr>
			<?php
			$totalOpen = 0;
			$totalClosed = 0;
			$totalToday = 0;
			$totalWeek = 0;
			foreach ($dataArray as $row) {
				?>
                <tr>
                    <td><?= (empty($row['user_id']) ? "Unassigned" : getUserDisplayName($row['user_id'])) ?></td>
                    <td class='align-center'><?= $row['open'] ?></td>
                    <td class='align-center'><?= $row['closed'] ?></td>
                    <td class='align-center'><?= $row['closed_today'] ?></td>
                    <td class='align-center'><?= $row['closed_week'] ?></td>
                </tr>
				<?php
				$totalOpen += $row['open'];
				$totalClosed += $row['closed'];
				$totalToday += $row['closed_today'];
				$totalWeek += $row['closed_week'];
			}
			?>
            <tr class='footer-row'>
                <td class='highlighted-text'>Total</td>
                <td class='align-center'><?= $totalOpen ?></td>
                <td class='align-center'><?= $totalClosed ?></td>
                <td class='align-center'><?= $totalToday ?></td>
                <td class='align-center'><?= $totalWeek ?></td>
            </tr>
			<?php
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

                <div class="basic-form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Date Ticket Submitted: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_exclude_feature_requests_row">
                    <input tabindex="10" type="checkbox" checked id="exclude_feature_requests" name="exclude_feature_requests" value='1'><label class='checkbox-label' for='exclude_feature_requests'>Exclude Feature Requests</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_help_desk_tag_group_id_row">
                    <label for="help_desk_tag_group_id">Help Desk Tag Group</label>
                    <select tabindex="10" id="help_desk_tag_group_id" name="help_desk_tag_group_id">
                        <option value="">[All]</option>
			            <?php
			            $resultSet = executeReadQuery("select * from help_desk_tag_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			            while ($row = getNextRow($resultSet)) {
				            ?>
                            <option value="<?= $row['help_desk_tag_group_id'] ?>"><?= htmlText($row['description']) ?></option>
				            <?php
			            }
			            ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_help_desk_tag_id_row">
                    <label for="help_desk_tag_id">Help Desk Tag</label>
                    <select tabindex="10" id="help_desk_tag_id" name="help_desk_tag_id">
                        <option value="">[All]</option>
			            <?php
			            $resultSet = executeReadQuery("select * from help_desk_tags where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			            while ($row = getNextRow($resultSet)) {
				            ?>
                            <option value="<?= $row['help_desk_tag_id'] ?>"><?= htmlText($row['description']) ?></option>
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
                                $("html, body").animate({scrollTop: 0}, 600);
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

$pageObject = new HelpDeskTicketReportPage();
$pageObject->displayPage();
