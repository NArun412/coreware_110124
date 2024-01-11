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

$GLOBALS['gPageCode'] = "USERGIFTREPORT";
require_once "shared/startup.inc";

class UserGiftReportPage extends Page implements BackgroundReport {

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

		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalDonations = 0;
		$designationId = getFieldFromId("designation_id", "designations", "designation_id", $_POST['designation_id'],
			($GLOBALS['gUserRow']['full_client_access'] ? "" : "inactive = 0 and (designation_id in (select " .
				"designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ") or designation_id in (select designation_id from designation_group_links where " .
				"designation_group_id in (select designation_group_id from designation_groups where user_id = " . $GLOBALS['gUserId'] . ") or designation_group_id in " .
				"(select designation_group_id from designation_group_users where user_id = " . $GLOBALS['gUserId'] . ")))"));
		switch ($_POST['report_type']) {
			case "export":
			case "details":
				$exportReport = $_POST['report_type'] == "export";
				$whereStatement = "";
				$parameters = array($GLOBALS['gClientId'], $designationId);
				$displayCriteria = "";

				if (!empty($_POST['donation_date_from'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donation_date >= ?";
					$parameters[] = makeDateParameter($_POST['donation_date_from']);
				}
				if (!empty($_POST['donation_date_to'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donation_date <= ?";
					$parameters[] = makeDateParameter($_POST['donation_date_to']);
				}
				if (!empty($_POST['donation_date_from']) && !empty($_POST['donation_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Donation date is between " . date("m/d/Y", strtotime($_POST['donation_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				} else if (!empty($_POST['donation_date_from'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Donation date is on or after " . date("m/d/Y", strtotime($_POST['donation_date_from']));
				} else if (!empty($_POST['donation_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Donation date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}

				if (!empty($_POST['pay_period_id'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "pay_period_id = ?";
					$parameters[] = $_POST['pay_period_id'];
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Pay Period is from " . date("m/d/Y", strtotime(getFieldFromId("date_created", "pay_periods", "pay_period_id", $_POST['pay_period_id'])));
				}

				$datePaidOutWhere = "";
				if (!empty($_POST['date_paid_out_from'])) {
					if (!empty($datePaidOutWhere)) {
						$datePaidOutWhere .= " and ";
					}
					$datePaidOutWhere = "date_paid_out >= ?";
					$parameters[] = makeDateParameter($_POST['date_paid_out_from']);
				}
				if (!empty($_POST['date_paid_out_to'])) {
					if (!empty($datePaidOutWhere)) {
						$datePaidOutWhere .= " and ";
					}
					$datePaidOutWhere .= "date_paid_out <= ?";
					$parameters[] = makeDateParameter($_POST['date_paid_out_to']);
				}
				if (!empty($_POST['date_paid_out_from']) && !empty($_POST['date_paid_out_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Date paid is between " . date("m/d/Y", strtotime($_POST['date_paid_out_from'])) . " and " . date("m/d/Y", strtotime($_POST['date_paid_out_to']));
				} else if (!empty($_POST['date_paid_out_from'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Date paid is on or after " . date("m/d/Y", strtotime($_POST['date_paid_out_from']));
				} else if (!empty($_POST['date_paid_out_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Date paid is on or before " . date("m/d/Y", strtotime($_POST['date_paid_out_to']));
				}
				if (!empty($datePaidOutWhere)) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "pay_period_id in (select pay_period_id from pay_periods where " . $datePaidOutWhere . " and client_id = ?)";
					$parameters[] = $GLOBALS['gClientId'];
				}
				if (!empty($_POST['only_not_paid'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "(pay_period_id is null or pay_period_id in (select pay_period_id from pay_periods where client_id = ? and date_paid_out is null))";
					$parameters[] = $GLOBALS['gClientId'];
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Not yet paid out";
				}

				$projectNameArray = array();
				$includeBackouts = getPreference("INCLUDE_BACKOUTS_GIVING_REPORT");
				$includeNotes = getPreference("INCLUDE_NOTES_GIVING_REPORT");
				$resultSet = executeReadQuery("select * from donations where client_id = ?" . ($includeBackouts ? "" : " and associated_donation_id is null") . " and designation_id = ?" .
					(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by donation_date desc", $parameters);

				ob_start();
				if ($exportReport) {
					$returnArray['export_headers'] = array();
					$returnArray['export_headers'][] = "Content-Type: text/csv";
					$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"donations.csv\"";
					$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
					$returnArray['export_headers'][] = 'Pragma: public';
					$returnArray['filename'] = "donations.csv";
					echo "\"Date\",\"Donor\",\"Project\",\"Recurring\",\"Amount\",\"Fee\",\"Net\"\r\n";
				} else {
					?>
                    <h1>For Designation <?= getFieldFromId("designation_code", "designations", "designation_id", $designationId) . " (" . getFieldFromId("description", "designations", "designation_id", $designationId) . ")" ?></h1>
                    <p><?= $displayCriteria ?></p>
                    <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
                    <table class="grid-table">
                    <tr>
                        <th>Date</th>
                        <th>From</th>
                        <th>Project</th>
						<?php if ($includeNotes) { ?>
                            <th>Notes</th>
						<?php } ?>
                        <th>Recurring</th>
                        <th>Amount</th>
                        <th>Fee</th>
                        <th>Net</th>
                    </tr>
					<?php
				}
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['project_name'])) {
						if (!array_key_exists(strtolower($row['project_name']), $projectNameArray)) {
							$projectNameArray[strtolower($row['project_name'])] = 0;
						}
						$projectNameArray[strtolower($row['project_name'])] += $row['amount'];
					}
					if (empty($row['donation_fee'])) {
						$row['donation_fee'] = 0;
					}
					$totalCount++;
					$totalDonations += $row['amount'];
					$totalFees += $row['donation_fee'];
					$fromDisplay = "";
					if (!empty($row['anonymous_gift'])) {
						$fromDisplay = "Anonymous";
					} else {
						$fromDisplay = getDisplayName($row['contact_id']);
						$resultSet1 = executeReadQuery("select * from contacts where contact_id = ?", $row['contact_id']);
						if ($row1 = getNextRow($resultSet1)) {
							if (!empty($row1['address_1'])) {
								$fromDisplay .= ", " . $row1['address_1'];
							}
							if (!empty($row1['city'])) {
								$fromDisplay .= ", " . $row1['city'];
							}
							if (!empty($row1['state'])) {
								$fromDisplay .= ", " . $row1['state'];
							}
							if (!empty($row1['postal_code'])) {
								$fromDisplay .= " " . $row1['postal_code'];
							}
							if (!empty($row1['email_address'])) {
								$fromDisplay .= ", " . $row1['email_address'];
							}
						}
					}
					if ($exportReport) {
						echo date("m/d/Y", strtotime($row['donation_date'])) . "," .
							'"' . str_replace('"', '', htmlText($fromDisplay)) . '",' .
							'"' . str_replace('"', '', htmlText($row['project_name'])) . '",' .
							(empty($row['recurring_donation_id']) ? "" : "YES") . "," .
							number_format($row['amount'], 2, ".", "") . "," . number_format($row['donation_fee'], 2, ".", "") . "," .
							number_format($row['amount'] - $row['donation_fee'], 2, ".", "") . "\r\n";
					} else {
						?>
                        <tr>
                            <td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
                            <td><?= htmlText($fromDisplay) ?></td>
                            <td><?= htmlText($row['project_name']) ?></td>
							<?php if ($includeNotes) { ?>
                                <td><?= htmlText($row['notes']) ?></td>
							<?php } ?>
                            <td><?= (empty($row['recurring_donation_id']) ? "" : "YES") ?></td>
                            <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                            <td class="align-right"><?= number_format($row['donation_fee'], 2) ?></td>
                            <td class="align-right"><?= number_format($row['amount'] - $row['donation_fee'], 2) ?></td>
                        </tr>
						<?php
					}
				}
				if ($exportReport) {
					$returnArray['report_export'] = ob_get_clean();
					return $returnArray;
				} else {
					?>
                    <tr>
                        <td colspan="<?= ($includeNotes ? "5" : "4") ?>" class="highlighted-text">Total for <?= $totalCount ?> gifts</td>
                        <td class="align-right"><?= number_format($totalDonations, 2) ?></td>
                        <td class="align-right"><?= number_format($totalFees, 2) ?></td>
                        <td class="align-right"><?= number_format($totalDonations - $totalFees, 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="<?= ($includeNotes ? "5" : "4") ?>" class="highlighted-text">Average Gift Amount</td>
                        <td class="align-right"><?= ($totalCount > 0 ? number_format($totalDonations / $totalCount, 2) : "0.00") ?></td>
                        <td colspan="2"></td>
                    </tr>
					<?php if (!empty($projectNameArray)) { ?>
                        <tr>
                            <td class="highlighted-text project-totals" colspan="<?= ($includeNotes ? "8" : "7") ?>">Project Totals</td>
                        </tr>
						<?php foreach ($projectNameArray as $projectName => $projectAmount) { ?>
                            <tr>
                                <td colspan="<?= ($includeNotes ? "5" : "4") ?>">Given for <?= htmlText($projectName) ?></td>
                                <td class="align-right"><?= number_format($projectAmount, 2) ?></td>
                                <td colspan="2"></td>
                            </tr>
						<?php } ?>
					<?php } ?>
                    </table>
					<?php
				}
				break;
			case "monthly":
				$startDate = $_POST['chart_year'] . "-01-01";
				$endDate = $_POST['chart_year'] . "-12-31";

				$donationArray = array();
				$resultSet = executeReadQuery("select * from donations,contacts where donations.contact_id = contacts.contact_id and " .
					"donations.client_id = ? and designation_id = ? and donation_date between ? and ? " .
					"order by last_name,first_name,contacts.contact_id,donation_date desc", $GLOBALS['gClientId'], $designationId, $startDate, $endDate);
				while ($row = getNextRow($resultSet)) {
					if ($row['anonymous_gift']) {
						$row['contact_id'] = 0;
					}
					if (!array_key_exists($row['contact_id'], $donationArray)) {
						$donationArray[$row['contact_id']] = array("contact_id" => $row['contact_id']);
						for ($x = 1; $x <= 12; $x++) {
							$donationArray[$row['contact_id']]["month" . $x] = 0;
						}
					}
					$month = date("n", strtotime($row['donation_date']));
					$donationArray[$row['contact_id']]["month" . $month] += $row['amount'];
				}
				ob_start();
				?>
                <h1><?= $_POST['chart_year'] ?> Gifts for designation <?= getFieldFromId("designation_code", "designations", "designation_id", $designationId) . " (" . getFieldFromId("description", "designations", "designation_id", $designationId) . ")" ?></h1>
                <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
                <table class="grid-table">
                    <tr>
                        <td rowspan="2">&nbsp;</td>
						<?php for ($x = 1; $x <= 6; $x++) { ?>
                            <td class="align-center"><?= date("M", strtotime($x . "/01/2010")) ?></td>
						<?php } ?>
                        <td rowspan="2" class="align-center highlighted-text">Total</td>
                    </tr>
                    <tr>
						<?php for ($x = 7; $x <= 12; $x++) { ?>
                            <td class="align-center"><?= date("M", strtotime($x . "/01/2010")) ?></td>
						<?php } ?>
                    </tr>
					<?php
					$totalsArray = array();
					for ($x = 1; $x <= 12; $x++) {
						$totalsArray["month" . $x] = 0;
					}
					foreach ($donationArray as $donorArray) {
						$totalAmount = 0;
						for ($x = 1; $x <= 12; $x++) {
							$totalAmount += $donorArray["month" . $x];
						}
						$displayName = (empty($donorArray['contact_id']) ? "Anonymous" : getDisplayName($donorArray['contact_id']));
						?>
                        <tr>
                            <td rowspan="2"><?= htmlText($displayName) ?></td>
							<?php
							for ($x = 1; $x <= 6; $x++) {
								?>
                                <td class="align-right"><?= number_format($donorArray["month" . $x], 2) ?></td>
								<?php
								$totalsArray["month" . $x] += $donorArray["month" . $x];
							}
							?>
                            <td class="align-right" rowspan="2"><?= number_format($totalAmount, 2) ?></td>
                        </tr>
                        <tr>
							<?php
							for ($x = 7; $x <= 12; $x++) {
								?>
                                <td class="align-right"><?= number_format($donorArray["month" . $x], 2) ?></td>
								<?php
								$totalsArray["month" . $x] += $donorArray["month" . $x];
							}
							?>
                        </tr>
						<?php
					}
					$totalAmount = 0;
					for ($x = 1; $x <= 12; $x++) {
						$totalAmount += $totalsArray["month" . $x];
					}
					?>
                    <tr>
                        <td rowspan="2" class="highlighted-text">Total</td>
						<?php
						for ($x = 1; $x <= 6; $x++) {
							?>
                            <td class="align-right"><?= number_format($totalsArray["month" . $x], 2) ?></td>
							<?php
						}
						?>
                        <td class="align-right" rowspan="2"><?= number_format($totalAmount, 2) ?></td>
                    </tr>
                    <tr>
						<?php
						for ($x = 7; $x <= 12; $x++) {
							?>
                            <td class="align-right"><?= number_format($totalsArray["month" . $x], 2) ?></td>
							<?php
						}
						?>
                    </tr>
                </table>
				<?php
				break;
		}
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		$returnArray['report_title'] = "User Gift Report";
		return $returnArray;
	}

	function mainContent() {
		$designationArray = array();
		$resultSet = executeReadQuery("select * from designations where inactive = 0 and client_id = ?" .
			($GLOBALS['gUserRow']['full_client_access'] ? "" : " and (designation_id in " .
				"(select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ") or designation_id in (select designation_id from designation_group_links where " .
				"designation_group_id in (select designation_group_id from designation_groups where user_id = " . $GLOBALS['gUserId'] . ") or designation_group_id in " .
				"(select designation_group_id from designation_group_users where user_id = " . $GLOBALS['gUserId'] . ")))") .
			" order by sort_order,designation_code", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designationArray[$row['designation_id']] = $row['designation_code'] . " - " . $row['description'];
		}
		?>
        <div id="report_parameters">
			<?php if (empty($designationArray)) { ?>
                <p class="error-message">There are no designations assigned to your user.</p>
			<?php } else { ?>
                <form id="_report_form" name="_report_form">

					<?php getStoredReports() ?>

                    <div class="form-line" id="_report_type_row">
                        <label for="report_type">Report Type</label>
                        <select tabindex="10" id="report_type" name="report_type">
                            <option value="details">Giving Details</option>
                            <option value="monthly">Monthly Chart</option>
                            <option value="export">CSV Export</option>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_designation_id_row">
                        <label for="designation_id" class="required-label">Designation</label>
                        <select tabindex="10" id="designation_id" name="designation_id" class="validate[required]">
                            <option value="">[Select]</option>
							<?php
							foreach ($designationArray as $designationId => $description) {
								?>
                                <option value="<?= $designationId ?>"><?= htmlText($description) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div id="monthly_table" class="monthly-table parameter-section">

                        <div class="form-line" id="_chart_year_row">
                            <label for="chart_year">Year</label>
                            <select tabindex="10" id="chart_year" name="chart_year">
								<?php for ($x = 0; $x < 5; $x++) { ?>
                                    <option value="<?= (date("Y") - $x) ?>"><?= (date("Y") - $x) ?></option>
								<?php } ?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                    </div>

                    <div id="details_table" class="details-table export-table parameter-section">

						<?php getPresetDateOptions() ?>

                        <div class="form-line preset-date-custom" id="_donation_date_row">
                            <label for="donation_date_from">Donation Date: From</label>
                            <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_from" name="donation_date_from">
                            <label class="second-label">Through</label>
                            <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_to" name="donation_date_to">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_pay_period_id_row">
                            <label for="pay_period_id">Pay Period</label>
                            <select tabindex="10" id="pay_period_id" name="pay_period_id">
                                <option value="">[All]</option>
								<?php
								$resultSet = executeReadQuery("select * from pay_periods where client_id = ? order by date_created desc limit 20", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['pay_period_id'] ?>"><?= date("m/d/Y", strtotime($row['date_created'])) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_date_paid_out_row">
                            <label for="date_paid_out_from">Date Paid: From</label>
                            <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_from" name="date_paid_out_from">
                            <label class="second-label">Through</label>
                            <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_to" name="date_paid_out_to">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_only_not_paid_row">
                            <label></label>
                            <input tabindex="10" type="checkbox" id="only_not_paid" name="only_not_paid"><label class="checkbox-label" for="only_not_paid">Only Not Paid Out</label>
                            <div class='clear-div'></div>
                        </div>

                    </div>

					<?php storedReportDescription() ?>

                    <div class="form-line">
                        <label></label>
                        <button tabindex="10" id="create_report">Create Report</button>
                        <div class='clear-div'></div>
                    </div>

                </form>
			<?php } ?>
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
            $("#report_type").change(function () {
                $(".parameter-section").hide();
                $("." + $(this).val() + "-table").show();
            }).trigger("change");
            $(document).on("tap click", "#printable_button", function () {
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("usergifts.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "export") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, "slow");
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

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

            .grid-table td.project-totals {
                border-top: 2px solid rgb(0, 0, 0);
            }
        </style>
		<?php
	}
}

$pageObject = new UserGiftReportPage();
$pageObject->displayPage();
