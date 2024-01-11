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

$GLOBALS['gPageCode'] = "DONATIONSREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class DonationsReportPage extends Page implements BackgroundReport {

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

		if (!empty($_POST['contact_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "contact_id = ?";
			$parameters[] = $_POST['contact_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donor ID is " . $_POST['contact_id'];
		}

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

		if (!empty($_POST['payment_method_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?)";
			$parameters[] = $_POST['payment_method_type_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Payment method type is " . getFieldFromId("description", "payment_method_types", "payment_method_type_id", $_POST['payment_method_type_id']);
		}

		if (!empty($_POST['payment_method_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "payment_method_id = ?";
			$parameters[] = $_POST['payment_method_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Payment method is " . getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
		}

		$batchWhere = "";
		if (!empty($_POST['batch_number_from']) && !empty($_POST['batch_number_to'])) {
			if (!empty($batchWhere)) {
				$batchWhere .= " and ";
			}
			$batchWhere .= "batch_number between ? and ?";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_from'];
			$parameters[] = $_POST['batch_number_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch Number is between " . $_POST['batch_number_from'] . " and " . $_POST['batch_number_to'];
		} else if (!empty($_POST['batch_number_from'])) {
			if (!empty($batchWhere)) {
				$batchWhere .= " and ";
			}
			$batchWhere .= "batch_number >= ?";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_from'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch number is greater than or equal " . $_POST['batch_number_from'];
		} else if (!empty($_POST['batch_number_to'])) {
			if (!empty($batchWhere)) {
				$batchWhere .= " and ";
			}
			$batchWhere .= "batch_number <= ?";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['batch_number_to'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Batch number is less than or equal " . $_POST['batch_number_to'];
		}
		if (!empty($batchWhere)) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(" . (empty($_POST['include_no_batch']) ? "" : "donation_batch_id is null or ") . "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and (" . $batchWhere . ")))";
			if (!empty($_POST['include_no_batch'])) {
				$displayCriteria .= " (Include donations with no batch)";
			}
		} else if (!empty($_POST['include_no_batch'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_batch_id is null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation Batch is empty";
		}

		if (!empty($_POST['bank_batch_number'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "bank_batch_number is not null and bank_batch_number = ?";
			$parameters[] = $_POST['bank_batch_number'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Bank Batch Number is " . $_POST['bank_batch_number'];
		}

		if (!empty($_POST['designation_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "designation_id in (select designation_id from designations where designation_type_id = ?)";
			$parameters[] = $_POST['designation_type_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Designation type is " . getFieldFromId("description", "designation_types", "designation_type_id", $_POST['designation_type_id']);
		}

		if (!empty($_POST['designation_group_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "designation_id in (select designation_id from designation_group_links where designation_group_id = ?)";
			$parameters[] = $_POST['designation_group_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Designation group is " . getFieldFromId("description", "designation_groups", "designation_group_id", $_POST['designation_group_id']);
		}

		if (!empty($_POST['designation_groups'])) {
			$designationGroupArray = explode(",", $_POST['designation_groups']);
		} else {
			$designationGroupArray = array();
		}
		if (count($designationGroupArray) > 0) {
			$designationGroupWhere = "";
			$displayDesignationGroups = "";
			foreach ($designationGroupArray as $designationGroupId) {
				$designationGroupId = getFieldFromId("designation_group_id", "designation_groups", "designation_group_id", $designationGroupId);
				if (empty($designationGroupId)) {
					continue;
				}
				if (!empty($designationGroupWhere)) {
					$designationGroupWhere .= ",";
				}
				$designationGroupWhere .= "?";
				$parameters[] = $designationGroupId;
				if (!empty($displayDesignationGroups)) {
					$displayDesignationGroups .= ", ";
				}
				$displayDesignationGroups .= getFieldFromId("description", "designation_groups", "designation_group_id", $designationGroupId);
			}
			if (!empty($designationGroupWhere)) {
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "designation_id not in (select designation_id from designation_group_links where designation_group_id in (" . $designationGroupWhere . "))";
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Designation Group not in (" . $displayDesignationGroups . ")";
			}
		}

		if (!empty($_POST['designations'])) {
			$designationArray = explode(",", $_POST['designations']);
		} else {
			$designationArray = array();
		}
		if (count($designationArray) > 0) {
			$designationWhere = "";
			$displayDesignations = "";
			foreach ($designationArray as $designationId) {
				$designationId = getFieldFromId("designation_id", "designations", "designation_id", $designationId);
				if (empty($designationId)) {
					continue;
				}
				if (!empty($designationWhere)) {
					$designationWhere .= ",";
				}
				$designationWhere .= "?";
				$parameters[] = $designationId;
				if (!empty($displayDesignations)) {
					$displayDesignations .= ", ";
				}
				$displayDesignations .= getFieldFromId("designation_code", "designations", "designation_id", $designationId);
			}
			if (!empty($designationWhere)) {
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "designation_id in (" . $designationWhere . ")";
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Designation Code in (" . $displayDesignations . ")";
			}
		}

		if ($GLOBALS['gUserRow']['superuser_flag'] && !empty($_POST['extra_where'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(" . $_POST['extra_where'] . ")";
		}

		$detailReport = ($_POST['report_type'] == "detail" || $_POST['report_type'] == "bank");
		ob_start();

		$bankReport = $_POST['report_type'] == "bank";
		if ($bankReport) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "bank_batch_number is not null";
		}
		$donationArray = array();
		$resultSet = executeReadQuery("select *,(select designation_code from designations where designation_id = donations.designation_id) designation_code," .
			"(select date_posted from donation_batches where donation_batch_id = donations.donation_batch_id) date_posted," .
			"(select batch_number from donation_batches where donation_batch_id = donations.donation_batch_id) batch_number" .
			" from donations where client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by " . ($bankReport ? "bank_batch_number" : "donation_date") . ",donation_id", $parameters);
		while ($row = getNextRow($resultSet)) {
			$donationArray[] = $row;
		}
		$returnArray['report_title'] = "Designation Code Totals " . ($detailReport ? "Details" : "Summary") . " Report";
		$summaryField = ($bankReport ? "bank_batch_number" : "donation_date");
		?>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table class="grid-table">
			<?php if ($detailReport) { ?>
                <tr>
					<?php if ($bankReport) { ?>
						<th>Bank Batch</th>
					<?php } ?>
					<th>Date</th>
                    <th>Designation</th>
                    <th>From</th>
                    <th>Payment Method</th>
                    <th>Batch #</th>
                    <th>Recur</th>
                    <th>Amount</th>
                </tr>
			<?php } else { ?>
                <tr>
                    <th>Date</th>
                    <th class="align-right">Count</th>
                    <th class="align-right">Total</th>
                    <th class="align-right">Fees</th>
                    <th class="align-right">Net</th>
                </tr>
			<?php } ?>
			<?php
			$saveData = "";
			$saveCount = 0;
			$saveFee = 0;
			$saveAmount = 0;
			$startDate = false;
			$endDate = false;
			foreach ($donationArray as $row) {
				if (empty($row['donation_fee'])) {
					$row['donation_fee'] = 0;
				}
				if ($row[$summaryField] != $saveData) {
					if (strlen($saveData) > 0) {
						if ($detailReport) {
							?>
                            <tr>
								<?php if ($bankReport) { ?>
									<td colspan='5' class="highlighted-text">Total for <?= $saveData ?></td>
								<?php } else { ?>
									<td colspan="4" class="highlighted-text">Total for <?= date("m/d/Y", strtotime($saveData)) ?></td>
								<?php } ?>
                                <td class="highlighted-text align-center" colspan="2"><?= number_format($saveCount, 0) ?> gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                                <td class="align-right highlighted-text"><?= number_format($saveAmount, 2) ?></td>
                            </tr>
							<?php
						} else {
							?>
                            <tr>
                                <td><?= date("m/d/Y", strtotime($saveData)) ?></td>
                                <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                                <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                                <td class="align-right"><?= number_format($saveFee, 2) ?></td>
                                <td class="align-right"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                            </tr>
							<?php
						}
					}
					$saveData = $row[$summaryField];
					$saveCount = 0;
					$saveFee = 0;
					$saveAmount = 0;
					$startDate = false;
					$endDate = false;
				}

				$totalCount++;
				$totalDonations += $row['amount'];
				$totalFees += $row['donation_fee'];
				$saveCount++;
				$saveFee += $row['donation_fee'];
				$saveAmount += $row['amount'];
				if (empty($startDate) || $row['donation_date'] < $startDate) {
					$startDate = $row['donation_date'];
				}
				if (empty($endDate) || $row['donation_date'] > $endDate) {
					$endDate = $row['donation_date'];
				}
				if ($detailReport) {
					?>
                    <tr>
						<?php if ($bankReport) { ?>
							<td><?= ($saveCount == 1 ? $saveData : "") ?></td>
							<td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
						<?php } else { ?>
							<td><?= date("m/d/Y", strtotime($saveData)) ?></td>
						<?php } ?>
						<?php if (canAccessPageCode("DESIGNATIONMAINT")) { ?>
                            <td><a target='_blank' href='/designationmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['designation_id'] ?>'><?= getFieldFromId("description", "designations", "designation_id", $row['designation_id']) ?></a></td>
						<?php } else { ?>
                            <td><?= getFieldFromId("description", "designations", "designation_id", $row['designation_id']) ?></td>
						<?php } ?>
						<?php if (canAccessPageCode("CONTACTMAINT")) { ?>
                            <td><a target='_blank' href='/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['contact_id'] ?>'><?= getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "") ?></a></td>
						<?php } else { ?>
                            <td><?= getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "") ?></td>
						<?php } ?>
                        <td class="align-right"><?= getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) ?></td>
						<?php if (canAccessPageCode("DONATIONBATCHMAINT")) { ?>
                            <td class="align-right"><a target='_blank' href='donationbatchmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['donation_batch_id'] ?>'><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></a></td>
						<?php } else { ?>
                            <td class="align-right"><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></td>
						<?php } ?>
                        <td class="align-center"><?= (empty($row['recurring_donation_id']) ? "" : "YES") ?></td>
                        <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                    </tr>
					<?php
				}
			}
			if (strlen($saveData) > 0) {
				if ($detailReport) {
					?>
                    <tr>
                        <td colspan="4" class="highlighted-text">Total for <?= ($bankReport ? $saveData : date("m/d/Y", strtotime($saveData))) ?></td>
                        <td class="highlighted-text align-center" colspan="2"><?= number_format($saveCount, 0) ?> gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                        <td class="align-right highlighted-text"><?= number_format($saveAmount, 2) ?></td>
                    </tr>
					<?php
				} else {
					?>
                    <tr>
                        <td><?= date("m/d/Y", strtotime($saveData)) ?></td>
                        <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                        <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                        <td class="align-right"><?= number_format($saveFee, 2) ?></td>
                        <td class="align-right"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                    </tr>
					<?php
				}
			}
			?>
            <tr>
                <td colspan='<?= ($detailReport ? "4" : "1") ?>' class="total-line">Total for Report</td>
                <td colspan='<?= ($detailReport ? "2" : "1") ?>' class="total-line align-center"><?= number_format($totalCount, 0) ?> gift<?= ($totalCount == 1 ? "" : "s") ?></td>
                <td class="total-line align-right"><?= number_format($totalDonations, 2) ?></td>
				<?php if (!$detailReport) { ?>
                    <td class="align-right total-line"><?= number_format($totalFees, 2) ?></td>
                    <td class="align-right total-line"><?= number_format($totalDonations - $totalFees, 2) ?></td>
				<?php } ?>
            </tr>
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

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="detail">Date Details</option>
                        <option value="summary">Date Summary</option>
                        <option value="bank">Bank Batch</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_contact_id_row">
                    <label for="contact_id">Donor ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="contact_id" name="contact_id">
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
                            <option value="<?= $row['pay_period_id'] ?>"><?= date("m/d/Y", strtotime($row['date_created'])) ?></option>
							<?php
						}
						?>
                    </select>
                </div>

                <div class="basic-form-line" id="_date_paid_out_row">
                    <label for="date_paid_out_from">Date Paid: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_from" name="date_paid_out_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="date_paid_out_to" name="date_paid_out_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_not_paid_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="only_not_paid" name="only_not_paid"><label class="checkbox-label" for="only_not_paid">Only Not Paid Out</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_type_id_row">
                    <label for="payment_method_type_id">Payment Method Type</label>
                    <select tabindex="10" id="payment_method_type_id" name="payment_method_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from payment_method_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_id_row">
                    <label for="payment_method_id">Payment Method</label>
                    <select tabindex="10" id="payment_method_id" name="payment_method_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from payment_methods where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_batch_number_row">
                    <label for="batch_number_from">Batch Number: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_from" name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_no_batch_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_no_batch" name="include_no_batch"><label class="checkbox-label" for="include_no_batch">Include Donations without Batch</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_bank_batch_number_row">
                    <label for="bank_batch_number">Bank Batch Number</label>
                    <input tabindex="10" type="text" size="20" maxlength="20" id="bank_batch_number" name="bank_batch_number">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_type_id_row">
                    <label for="designation_type_id">Designation Type</label>
                    <select tabindex="10" id="designation_type_id" name="designation_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from designation_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_group_id_row">
                    <label for="designation_group_id">Designation Group</label>
                    <select tabindex="10" id="designation_group_id" name="designation_group_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from designation_groups where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$designationGroupControl = new DataColumn("designation_groups");
				$designationGroupControl->setControlValue("data_type", "custom");
				$designationGroupControl->setControlValue("include_inactive", "true");
				$designationGroupControl->setControlValue("control_class", "MultiSelect");
				$designationGroupControl->setControlValue("control_table", "designation_groups");
				$designationGroupControl->setControlValue("links_table", "designation_group_links");
				$designationGroupControl->setControlValue("primary_table", "designations");
				$customControl = new MultipleSelect($designationGroupControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_designation_groups_row">
                    <label for="designation_groups">Exclude Designation Groups</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php
				$designationControl = new DataColumn("designations");
				$designationControl->setControlValue("data_type", "custom");
				$designationControl->setControlValue("include_inactive", "true");
				$designationControl->setControlValue("control_class", "MultiSelect");
				$designationControl->setControlValue("control_table", "designations");
				$designationControl->setControlValue("links_table", "designations");
				$designationControl->setControlValue("primary_table", "donations");
				$customControl = new MultipleSelect($designationControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_designations_row">
                    <label for="designations">Designations</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                    <div class="basic-form-line" id="_extra_where_row">
                        <label for="extra_where">Where</label>
                        <input tabindex="10" type="text" size="60" id="extra_where" name="extra_where">
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

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
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
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
            #_report_content table td {
                font-size: 13px;
            }
            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
            .total-line {
                font-weight: bold;
                font-size: 15px;
            }
            .grid-table td.border-bottom {
                border-bottom: 2px solid rgb(0, 0, 0);
            }
        </style>
		<?php
	}
}

$pageObject = new DonationsReportPage();
$pageObject->displayPage();
