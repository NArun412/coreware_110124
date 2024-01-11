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

$GLOBALS['gPageCode'] = "GIVINGREPORT";
require_once "shared/startup.inc";

class GivingReportPage extends Page implements BackgroundReport {

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

		processPresetDates($_POST['preset_dates'], "donation_date_from", "donation_date_to");

		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalDonations = 0;

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
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

		if (!empty($_POST['batch_number_from']) && !empty($_POST['batch_number_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and " .
				"batch_number between ? and ?)";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_from'];
			$parameters[] = $_POST['batch_number_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch Number is between " . $_POST['batch_number_from'] . " and " . $_POST['batch_number_to'];
		} else if (!empty($_POST['batch_number_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and " .
				"batch_number >= ?)";
			$parameters[] = $_POST['batch_number_from'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch number is greater than or equal " . $_POST['batch_number_from'];
		} else if (!empty($_POST['batch_number_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and " .
				"batch_number <= ?)";
			$parameters[] = $_POST['batch_number_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch number is less than or equal " . $_POST['batch_number_to'];
		}

		if (!empty($_POST['designation_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "designation_id = ?";
			$parameters[] = $_POST['designation_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
            $designationDescription = getFieldFromId("designation_code", "designations", "designation_id", $_POST['designation_id']);
			$displayCriteria .= "Designation code is " . $designationDescription;
		}

		ob_start();
		$summary = ($_POST['report_type'] == "summary");
		$payroll = ($_POST['report_type'] == "payroll");
		$includeBackouts = getPreference("INCLUDE_BACKOUTS_GIVING_REPORT");
		$includeNotes = getPreference("INCLUDE_NOTES_GIVING_REPORT");
		if ($payroll) {
			$donationSet = executeReadQuery("select date_created,sum(amount) as total_amount,sum(coalesce(donation_fee,0)) as total_fees,sum(amount - coalesce(donation_fee,0)) net_total,pay_periods.pay_period_id from donations join pay_periods using (pay_period_id) where pay_period_id is not null and donations.client_id = ?" . ($includeBackouts ? "" : " and associated_donation_id is null") .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . " group by date_created order by date_created", $parameters);
		} else {
			$donationSet = executeReadQuery("select * from donations where client_id = ?" . ($includeBackouts ? "" : " and associated_donation_id is null") .
				(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by donation_id", $parameters);
		}
		$donationArray = array();
		$reportTotal = 0;
		if ($summary) {
			?>
            <table class='grid-table'>
            <tr>
                <th>Date</th>
                <th>From</th>
                <th>For</th>
                <th>Project</th>
                <th>Amount</th>
                <th>Fees</th>
                <th>Net</th>
            </tr>
			<?php
		} else if ($payroll) {
			?>
            <p>For: <?= htmlText($designationDescription) ?></p>
            <table class='grid-table'>
            <tr>
                <th>Payroll Date</th>
                <th>Amount</th>
                <th>Fees</th>
                <th>Net</th>
            </tr>
			<?php
		} else if ($_POST['report_type'] == "monthly" || $_POST['report_type'] == "yearly") {
			?>
            <p>For: <?= htmlText($designationDescription) ?></p>
            <table class='grid-table'>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Fees</th>
                <th>Net</th>
            </tr>
			<?php
		}
		$totalAmount = 0;
		$totalFees = 0;
		$netTotal = 0;
		$saveGroup = false;
		$saveAmount = 0;
		$saveFees = 0;
		while ($donationRow = getNextRow($donationSet)) {
			if (empty($donationRow['donation_fee'])) {
				$donationRow['donation_fee'] = 0;
			}
			if ($_POST['report_type'] == "monthly" || $_POST['report_type'] == "yearly") {
				$thisGroup = date(($_POST['report_type'] == "monthly" ? "F, Y" : "Y"),strtotime($donationRow['donation_date']));
				if ($thisGroup != $saveGroup) {
					if (!empty($saveGroup)) {
				?>
                <tr>
                    <td><?= $saveGroup ?></td>
                    <td class='align-right'><?= number_format($saveAmount, 2) ?></td>
                    <td class='align-right'><?= number_format($saveFees, 2) ?></td>
                    <td class='align-right'><?= number_format($saveAmount - $saveFees, 2) ?></td>
                </tr>
				<?php
					}
					$saveAmount = 0;
					$saveFees = 0;
				}
				$saveGroup = $thisGroup;
				$saveAmount += $donationRow['amount'];
				$totalAmount += $donationRow['amount'];
				$saveFees += $donationRow['donation_fee'];
				$totalFees += $donationRow['donation_fee'];
				continue;
			}
			if ($payroll) {
				?>
                <tr>
	                <td><a href='#' class='payroll-details' data-pay_period_id='<?= $donationRow['pay_period_id'] ?>'><?= date("F j, Y", strtotime($donationRow['date_created'])) ?></a></td>
                    <td class='align-right'><?= number_format($donationRow['total_amount'], 2) ?></td>
                    <td class='align-right'><?= number_format($donationRow['total_fees'], 2) ?></td>
                    <td class='align-right'><?= number_format($donationRow['net_total'], 2) ?></td>
                </tr>
				<?php
				$totalAmount += $donationRow['total_amount'];
				$totalFees += $donationRow['total_fees'];
				$netTotal += $donationRow['net_total'];
				continue;
			}
			$designationCode = getFieldFromId("designation_code", "designations", "designation_id", $donationRow['designation_id']);
			$description = getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']);
			if ($summary) {
				?>
                <tr>
                    <td><?= date("F j, Y", strtotime($donationRow['donation_date'])) ?></td>
                    <td><?= htmlText(($donationRow['anonymous_gift'] ? "Anonymous" : getDisplayName($donationRow['contact_id']))) ?></td>
                    <td><?= htmlText($description) ?></td>
                    <td><?= htmlText($donationRow['project_name']) ?></td>
                    <td class='align-right'><?= number_format($donationRow['amount'], 2) ?></td>
                    <td class='align-right'><?= number_format($donationRow['donation_fee'], 2) ?></td>
                    <td class='align-right'><?= number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) ?></td>
                </tr>
				<?php
				$totalAmount += $donationRow['amount'];
				$totalFees += $donationRow['donation_fee'];
				continue;
			}
			?>
            <h2><?= $designationCode . " - " . $description ?></h2>
            <p><?= date("M j, Y") ?></p>
			<?php
			if (empty($donationRow['donation_fee'])) {
				$donationRow['donation_fee'] = 0;
			}
			$contactRow = Contact::getContact($donationRow['contact_id']);
			if ($donationRow['anonymous_gift']) {
				?>
                <p>Anonymous Gift: $<?= number_format($donationRow['amount'], 2) ?></p>
				<?php
				if (!empty($donationRow['project_name'])) {
					?>
                    <p>Given for: <?= $donationRow['project_name'] ?></p>
					<?php
				}
				?>
                <p>*** Given on <?= date("F j, Y", strtotime($donationRow['donation_date'])) ?></p>
                <p>Given by <?= getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']) . ", Administration fee is $" . number_format($donationRow['donation_fee'], 2) . ", Net gift amount is $" . number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) ?></p>
				<?php
			} else {
				$lines = array();
				$lines[] = $contactRow['contact_id'];
				$lines[] = getDisplayName($contactRow['contact_id'], array("include_company" => true));
				$lines[] = $contactRow['address_1'];
				$lines[] = $contactRow['address_2'];
				$lines[] = $contactRow['city'] . (empty($contactRow['state']) ? "" : ", " . $contactRow['state']) . " " . $contactRow['postal_code'];
				if ($contactRow['country_id'] != 1000) {
					$lines[] = getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']);
				}
				$lines[] = $contactRow['email_address'];
				$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ?", $contactRow['contact_id']);
				while ($phoneRow = getNextRow($phoneSet)) {
					$lines[] = $phoneRow['phone_number'] . " " . $phoneRow['description'];
				}
				$contactBlock = "";
				foreach ($lines as $line) {
					$line = trim($line);
					if (!empty($line)) {
						$contactBlock .= (empty($contactBlock) ? "" : "<br>") . $line;
					}
				}
				?>
                <p><?= $contactBlock ?></p>
				<?php
				if (!empty($donationRow['project_name'])) {
					?>
                    <p>Given for: <?= $donationRow['project_name'] ?></p>
					<?php
				}
				?>
                <p>*** Given on <?= date("F j, Y", strtotime($donationRow['donation_date'])) . ", Receipt #" . $donationRow['donation_id'] . ", $" . number_format($donationRow['amount'], 2) ?> USD</p>
                <p>Given by <?= getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']) . ", Administration fee is $" . number_format($donationRow['donation_fee'], 2) . ", Net gift amount is $" . number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) ?></p>
				<?php
			}
			if (!empty($donationRow['notes']) && $includeNotes) {
				?>
                <p>Notes: <?= htmlText($donationRow['notes']) ?></p>
				<?php
			}
			if (!empty($donationRow['recurring_donation_id'])) {
				?>
                <p>Recurring: <?= getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", getFieldFromId("recurring_donation_type_id", "recurring_donations", "recurring_donation_id", $donationRow['recurring_donation_id'])) ?></p>
				<?php
			}
			?>
            <hr>
			<?php
			$reportTotal += $donationRow['amount'];
			$totalFees += $donationRow['donation_fee'];
		}
		if ($summary) {
			?>
            <tr>
                <td colspan='4' class='align-right highlighted-text'>Total</td>
                <td class='align-right highlighted-text'><?= number_format($totalAmount, 2) ?></td>
                <td class='align-right highlighted-text'><?= number_format($totalFees, 2) ?></td>
                <td class='align-right highlighted-text'><?= number_format($totalAmount - $totalFees, 2) ?></td>
            </tr>
            </table>
			<?php
		} else if ($payroll) {
			?>
            <tr>
                <td class='align-right highlighted-text'>Total</td>
                <td class='align-right highlighted-text'><?= number_format($totalAmount, 2) ?></td>
                <td class='align-right highlighted-text'><?= number_format($totalFees, 2) ?></td>
                <td class='align-right highlighted-text'><?= number_format($netTotal, 2) ?></td>
            </tr>
            </table>
			<?php
		} else if ($_POST['report_type'] == "monthly" || $_POST['report_type'] == "yearly") {
			?>
            <tr>
                <td class='align-right highlighted-text'>Total</td>
                <td class='align-right highlighted-text'><?= number_format($totalAmount, 2) ?></td>
                <td class='align-right highlighted-text'><?= number_format($totalFees, 2) ?></td>
                <td class='align-right highlighted-text'><?= number_format($totalAmount - $totalFees, 2) ?></td>
            </tr>
			</table>
			<?php
		} else {
			?>
            <p>Total Gifts: $<?= number_format($reportTotal, 2) . ", Total Fees: $" . number_format($totalFees, 2) . ", Net Total: $" . number_format($reportTotal - $totalFees, 2) ?></p>
			<?php
			$feeMessage = Donations::getFeeMessage($_POST['designation_id']);
			if (!empty($feeMessage)) {
				?>
                <p>Fee Schedule:</p>
                <p><?= $feeMessage ?></p>
				<?php
			}
		}
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		$returnArray['report_title'] = "Giving Report";
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="detail" selected>Details</option>
                        <option value="summary">Summary</option>
						<option value="payroll">By Payroll</option>
						<option value="monthly">Monthly</option>
						<option value="yearly">Yearly</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_donation_date_row">
                    <label for="donation_date_from">Donation Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_from" name="donation_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_to" name="donation_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_pay_period_id_row">
                    <label for="pay_period_id">Pay Period</label>
                    <select tabindex="10" id="pay_period_id" name="pay_period_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from pay_periods where client_id = ? order by date_created desc limit 20", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['pay_period_id'] ?>"><?= date("m/d/Y", strtotime($row['date_created'])) . " run by " . getUserDisplayName($row['user_id']) . ", " . $row['donation_count'] . ", " . number_format($row['total_donations'], 2) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_date_paid_out_row">
                    <label for="date_paid_out_from">Date Paid: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_from" name="date_paid_out_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_to" name="date_paid_out_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_not_paid_row">
                    <input tabindex="10" type="checkbox" id="only_not_paid" name="only_not_paid"><label class="checkbox-label" for="only_not_paid">Only Not Paid Out</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_batch_number_row">
                    <label for="batch_number_from">Batch Number: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_from" name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_id_row">
                    <label for="designation_id" class="required-label">Designation</label>
                    <select tabindex="10" id="designation_id" name="designation_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeReadQuery("select * from designations where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
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
	        $(document).on("click",".payroll-details",function() {
                let postData = {};
                postData['report_type'] = "detail";
                postData['pay_period_id'] = $(this).data("pay_period_id");
                postData['designation_id'] = $("#designation_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", postData, function (returnArray) {
                    if ("report_content" in returnArray) {
                        $("#report_parameters").hide();
                        $("#_report_title").html(returnArray['report_title']).show();
                        $("#_report_content").html(returnArray['report_content']).show();
                        $("#_button_row").show();
                        $("html, body").animate({ scrollTop: 0 }, "slow");
                    }
                });
	        });
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("giving.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function (returnArray) {
                        if ("report_content" in returnArray) {
                            $("#report_parameters").hide();
                            $("#_report_title").html(returnArray['report_title']).show();
                            $("#_report_content").html(returnArray['report_content']).show();
                            $("#_button_row").show();
                            $("html, body").animate({ scrollTop: 0 }, "slow");
                        }
                    });
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
        </style>
		<?php
	}
}

$pageObject = new GivingReportPage();
$pageObject->displayPage();
