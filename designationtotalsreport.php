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

$GLOBALS['gPageCode'] = "DESIGNATIONTOTALSREPORT";
require_once "shared/startup.inc";

class DesignationTotalsReportPage extends Page implements BackgroundReport {

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

		if (!empty($_POST['donation_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_id = ?";
			$parameters[] = $_POST['donation_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation ID is " . $_POST['donation_id'];
		}

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
		} else {
			if (!empty($_POST['donation_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Donation date is on or after " . date("m/d/Y", strtotime($_POST['donation_date_from']));
			} else {
				if (!empty($_POST['donation_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Donation date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
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
		} else {
			if (!empty($_POST['date_paid_out_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Date paid is on or after " . date("m/d/Y", strtotime($_POST['date_paid_out_from']));
			} else {
				if (!empty($_POST['date_paid_out_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Date paid is on or before " . date("m/d/Y", strtotime($_POST['date_paid_out_to']));
				}
			}
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
		} else {
			if (!empty($_POST['batch_number_from'])) {
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
			} else {
				if (!empty($_POST['batch_number_to'])) {
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
			}
		}

		if (!empty($batchWhere)) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(" . (empty($_POST['include_no_batch']) ? "" : "donation_batch_id is null or ") . "donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and (" . $batchWhere . ")))";
			if (!empty($_POST['include_no_batch'])) {
				$displayCriteria .= " (Include donations with no batch)";
			}
		} else {
			if (!empty($_POST['include_no_batch'])) {
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "donation_batch_id is null";
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Donation Batch is empty";
			}
		}

		if (!empty($_POST['include_only_online'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_batch_id is null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Include Only Online";
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

		if (!empty($_POST['project_name'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "project_name like ?";
			$parameters[] = "%" . $_POST['project_name'] . "%";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Project Name contains '" . $_POST['project_name'] . "'";
		}

		if (!empty($_POST['state'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "contact_id in (select contact_id from contacts where client_id = ? and state = ?)";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['state'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "State is " . $_POST['state'];
		}

		if ($GLOBALS['gUserRow']['superuser_flag'] && !empty($_POST['extra_where'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(" . $_POST['extra_where'] . ")";
		}

		$detailReport = $_POST['report_type'] == "detail";
		$fileOutput = $_POST['report_type'] == "file";
		$exportReport = $_POST['report_type'] == "export";
		ob_start();
		if ($exportReport) {
			echo "\"Designation Code\",\"Date\",\"Donor\",\"Email\",\"Batch Number\",\"Payment Method\",\"Recurring\",\"Amount\",\"Project\",\"Memo\"\r\n";
		}

		$donationArray = array();
		$resultSet = executeReadQuery("select *,(select designation_code from designations where designation_id = donations.designation_id) designation_code," .
			"(select date_posted from donation_batches where donation_batch_id = donations.donation_batch_id) date_posted," .
			"(select batch_number from donation_batches where donation_batch_id = donations.donation_batch_id) batch_number" .
			($fileOutput || empty($_POST['include_subtotals']) ? "" : ",(select description from designation_types where designation_type_id = (select designation_type_id from designations where designation_id = donations.designation_id)) designation_type") .
			" from donations where client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by " . ($fileOutput || empty($_POST['include_subtotals']) ? "" : "designation_type,") . "designation_code,donation_date", $parameters);
		while ($row = getNextRow($resultSet)) {
			if ($exportReport) {
				echo "\"" . str_replace("\"", "", $row['designation_code']) . "\",\"" . date("m/d/y", strtotime($row['donation_date'])) . "\",\"" .
					str_replace("\"", "", getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "")) . "\",\"" .
					str_replace("\"", "", getFieldFromId("email_address", "contacts", "contact_id", $row['contact_id'])) . "\",\"" .
					$row['batch_number'] . "\",\"" . getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) .
					(empty($row['account_id']) ? "" : " - " . substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -4)) . "\",\"" .
					(empty($row['recurring_donation_id']) ? "" : "YES") . "\",\"" . number_format($row['amount'], 2) . "\",\"" .
					str_replace('"', "", $row['project_name']) . "\",\"" . str_replace('"', "", $row['notes']) . "\"\r\n";
				continue;
			}
			$donationArray[] = $row;
		}
		if ($exportReport) {
			header("Content-Type: text/csv");
			header("Content-Disposition: attachment; filename=\"donations.csv\"");
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			$reportContent = ob_get_clean();
			echo $reportContent;
			exit;
		}
		$batchNumberArray = array();
		foreach ($donationArray as $row) {
			if (empty($row['date_posted'])) {
				if (!in_array($row['batch_number'], $batchNumberArray) && !empty($row['batch_number'])) {
					$batchNumberArray[] = $row['batch_number'];
				}
			}
		}
		sort($batchNumberArray);
		if (!$fileOutput) {
			$returnArray['report_title'] = "Designation Code Totals " . ($detailReport ? "Details" : "Summary") . " Report";
			?>
            <p><?= $displayCriteria ?></p>
			<?php if (count($batchNumberArray) > 0) { ?>
                <p>This report includes the following unposted
                    batches: <?= implode(",", $batchNumberArray) ?></p>
			<?php } ?>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
			<?php if ($detailReport) { ?>
                <tr>
                    <th>Designation</th>
                    <th>Date</th>
                    <th>From</th>
					<?php if ($_POST['include_project']) { ?>
                        <th>Project</th>
					<?php } ?>
					<?php if ($_POST['include_notes']) { ?>
                        <th>Notes</th>
					<?php } ?>
                    <th>Payment Method</th>
                    <th>Reference #</th>
                    <th>Batch #</th>
                    <th>Recur</th>
                    <th>Amount</th>
                    <th>Fees</th>
                    <th>Net</th>
                </tr>
			<?php } else { ?>
                <tr>
                    <th>Designation Code</th>
                    <th>Description</th>
                    <th class="align-right">Count</th>
                    <th class="align-right">Total</th>
                    <th class="align-right">Fees</th>
                    <th class="align-right">Net</th>
                </tr>
			<?php } ?>
			<?php
		}
		$saveType = "";
		$saveTypeCount = 0;
		$saveTypeFee = 0;
		$saveTypeTotal = 0;
		$saveDesignation = "";
		$saveCount = 0;
		$saveFee = 0;
		$saveAmount = 0;
		foreach ($donationArray as $row) {
			if (empty($row['donation_fee'])) {
				$row['donation_fee'] = 0;
			}
			if ($row['designation_id'] != $saveDesignation) {
				if (!empty($saveDesignation)) {
					if ($detailReport) {
						$colspan = 5 + ($_POST['include_project'] ? 1 : 0) + ($_POST['include_notes'] ? 1 : 0);
						?>
                        <tr>
                            <td colspan="<?= $colspan ?>" class="highlighted-text">Total
                                for <?= getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) . " - " . getFieldFromId("description", "designations", "designation_id", $saveDesignation) ?></td>
                            <td class="highlighted-text align-center"
                                colspan="2"><?= number_format($saveCount, 0) ?>
                                gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                            <td class="align-right highlighted-text"><?= number_format($saveAmount, 2) ?></td>
                            <td class="align-right highlighted-text"><?= number_format($saveFee, 2) ?></td>
                            <td class="align-right highlighted-text"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                        </tr>
						<?php
					} else {
						if ($fileOutput) {
							echo getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) . "\t" .
								getFieldFromId("description", "designations", "designation_id", $saveDesignation) . "\t" .
								getFieldFromId("email_address", "designation_email_addresses", "designation_id", $saveDesignation) . "\t" .
								number_format($saveAmount, 2) . "\n";
						} else {
							?>
                            <tr>
								<?php if (canAccessPageCode("DESIGNATIONMAINT")) { ?>
                                    <td><a target="_blank" href=/designationmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $saveDesignation ?>'><?= getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) ?></a></td>
								<?php } else { ?>
                                    <td><?= getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) ?></td>
								<?php } ?>
                                <td><?= getFieldFromId("description", "designations", "designation_id", $saveDesignation) ?></td>
                                <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                                <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                                <td class="align-right"><?= number_format($saveFee, 2) ?></td>
                                <td class="align-right"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                            </tr>
							<?php
						}
					}
				}
				$saveDesignation = $row['designation_id'];
				$saveCount = 0;
				$saveFee = 0;
				$saveAmount = 0;
			}

			if ($row['designation_type'] != $saveType) {
				if (!empty($saveTypeCount)) {
					if (empty($saveType)) {
						$saveType = "[NONE]";
					}
					if ($detailReport) {
						$colspan = 5 + ($_POST['include_project'] ? 1 : 0) + ($_POST['include_notes'] ? 1 : 0);
						if (!empty($_POST['include_subtotals'])) {
							?>
                            <tr>
                                <td colspan="<?= $colspan ?>"
                                    class="border-bottom align-right highlighted-text">Total for designation
                                    type <?= $saveType ?></td>
                                <td class="border-bottom align-center highlighted-text"
                                    colspan="2"><?= number_format($saveTypeCount, 0) ?>
                                    gift<?= ($saveTypeCount == 1 ? "" : "s") ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal, 2) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeFee, 2) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal - $saveTypeFee, 2) ?></td>
                            </tr>
							<?php
						}
					} else {
						if (!$fileOutput && !empty($_POST['include_subtotals'])) {
							?>
                            <tr>
                                <td colspan="2" class="border-bottom align-right highlighted-text">Total for
                                    designation type <?= $saveType ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeCount, 0) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal, 2) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeFee, 2) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal - $saveTypeFee, 2) ?></td>
                            </tr>
							<?php
						}
					}
				}
				$saveType = $row['designation_type'];
				$saveTypeCount = 0;
				$saveTypeFee = 0;
				$saveTypeTotal = 0;
			}

			$totalCount++;
			$totalDonations += $row['amount'];
			$totalFees += $row['donation_fee'];
			$saveCount++;
			$saveFee += $row['donation_fee'];
			$saveAmount += $row['amount'];
			$saveTypeCount++;
			$saveTypeFee += $row['donation_fee'];
			$saveTypeTotal += $row['amount'];
			if ($detailReport) {
				?>
                <tr>
					<?php if (canAccessPageCode("DESIGNATIONMAINT")) { ?>
                        <td><?= ($saveCount == 1 ? "<a target='_blank' href='/designationmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $saveDesignation . "'>" . getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) . "</a>" : "") ?></td>
					<?php } else { ?>
                        <td><?= ($saveCount == 1 ? getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) : "") ?></td>
					<?php } ?>
                    <td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
					<?php if (canAccessPageCode("CONTACTMAINT")) { ?>
                        <td><a target='_blank' href='/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['contact_id'] ?>'><?= getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "") ?></a></td>
					<?php } else { ?>
                        <td><?= getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "") ?></td>
					<?php } ?>
					<?php if ($_POST['include_project']) { ?>
                        <td><?= htmlText($row['project_name']) ?></td>
					<?php } ?>
					<?php if ($_POST['include_notes']) { ?>
                        <td><?= htmlText($row['notes']) ?></td>
					<?php } ?>
                    <td class="align-right"><?= getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . (empty($row['account_id']) ? "" : " - " . substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -4)) ?></td>
                    <td class="align-right"><?= $row['reference_number'] ?></td>
					<?php if (canAccessPageCode("DONATIONBATCHMAINT")) { ?>
                        <td class="align-right"><a target='_blank' href='/donationbatchmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['donation_batch_id'] ?>'><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></a></td>
					<?php } else { ?>
                        <td class="align-right"><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></td>
					<?php } ?>
                    <td class="align-center"><?= (empty($row['recurring_donation_id']) ? "" : "YES") ?></td>
                    <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                    <td class="align-right"><?= number_format($row['donation_fee'], 2) ?></td>
                    <td class="align-right"><?= number_format($row['amount'] - $row['donation_fee'], 2) ?></td>
                </tr>
				<?php
			}
		}
		if (!empty($saveDesignation)) {
			if ($detailReport) {
				$colspan = 5 + ($_POST['include_project'] ? 1 : 0) + ($_POST['include_notes'] ? 1 : 0);
				?>
                <tr>
                    <td colspan="<?= $colspan ?>" class="highlighted-text">Total
                        for <?= getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) . " - " . getFieldFromId("description", "designations", "designation_id", $saveDesignation) ?></td>
                    <td class="highlighted-text align-center" colspan="2"><?= number_format($saveCount, 0) ?>
                        gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                    <td class="align-right highlighted-text"><?= number_format($saveAmount, 2) ?></td>
                    <td class="align-right highlighted-text"><?= number_format($saveFee, 2) ?></td>
                    <td class="align-right highlighted-text"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                </tr>
				<?php
			} else {
				if ($fileOutput) {
					echo getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) . "\t" .
						getFieldFromId("description", "designations", "designation_id", $saveDesignation) . "\t" .
						number_format($saveAmount, 2) . "\n";
				} else {
					?>
                    <tr>
	                    <?php if (canAccessPageCode("DESIGNATIONMAINT")) { ?>
                            <td><a target='_blank' href='/designationmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $saveDesignation ?>'><?= getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) ?></a></td>
	                    <?php } else { ?>
                            <td><?= getFieldFromId("designation_code", "designations", "designation_id", $saveDesignation) ?></td>
	                    <?php } ?>
                        <td><?= getFieldFromId("description", "designations", "designation_id", $saveDesignation) ?></td>
                        <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                        <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                        <td class="align-right"><?= number_format($saveFee, 2) ?></td>
                        <td class="align-right"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                    </tr>
					<?php
				}
			}
		}
		if (!empty($saveTypeCount)) {
			if (empty($saveType)) {
				$saveType = "[NONE]";
			}
			if ($detailReport) {
				$colspan = 5 + ($_POST['include_project'] ? 1 : 0) + ($_POST['include_notes'] ? 1 : 0);
				if (!empty($_POST['include_subtotals'])) {
					?>
                    <tr>
                        <td colspan="<?= $colspan ?>" class="border-bottom align-right highlighted-text">Total
                            for designation type <?= $saveType ?></td>
                        <td class="border-bottom align-center highlighted-text"
                            colspan="2"><?= number_format($saveTypeCount, 0) ?>
                            gift<?= ($saveTypeCount == 1 ? "" : "s") ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal, 2) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeFee, 2) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal - $saveTypeFee, 2) ?></td>
                    </tr>
					<?php
				}
			} else {
				if (!$fileOutput && !empty($_POST['include_subtotals'])) {
					?>
                    <tr>
                        <td colspan="2" class="border-bottom align-right highlighted-text">Total for designation
                            type <?= $saveType ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeCount, 0) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal, 2) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeFee, 2) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveTypeTotal - $saveTypeFee, 2) ?></td>
                    </tr>
					<?php
				}
			}
		}
		if (!$fileOutput) {
			$colspan = 5 + ($_POST['include_project'] ? 1 : 0) + ($_POST['include_notes'] ? 1 : 0);
			?>
            <tr>
                <td colspan='<?= ($detailReport ? $colspan : "2") ?>' class="total-line">Total for Report</td>
                <td colspan='<?= ($detailReport ? "2" : "1") ?>'
                    class="total-line align-center"><?= number_format($totalCount, 0) ?>
                    gift<?= ($totalCount == 1 ? "" : "s") ?></td>
                <td class="total-line align-right"><?= number_format($totalDonations, 2) ?></td>
                <td class="align-right total-line"><?= number_format($totalFees, 2) ?></td>
                <td class="align-right total-line"><?= number_format($totalDonations - $totalFees, 2) ?></td>
            </tr>
            </table>
			<?php
		}
		$reportContent = ob_get_clean();
		if ($fileOutput) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/plain";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"designations.txt\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['report_export'] = $reportContent;
			$returnArray['filename'] = "designations.txt";
		} else {
			$returnArray['report_content'] = $reportContent;
		}
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
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                        <option value="file">Delimited</option>
                        <option value="export">Delimited Detail</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_donation_id_row">
                    <label for="donation_id">Donation ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[integer]]" id="donation_id" name="donation_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_contact_id_row">
                    <label for="contact_id">Donor ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[integer]]" id="contact_id" name="contact_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_donation_date_row">
                    <label for="donation_date_from">Donation Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[date]] datepicker" id="donation_date_from"
                           name="donation_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[date]] datepicker" id="donation_date_to"
                           name="donation_date_to">
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
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[date]] datepicker" id="date_paid_out_from"
                           name="date_paid_out_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[date]] datepicker" id="date_paid_out_to"
                           name="date_paid_out_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_not_paid_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="only_not_paid" name="only_not_paid"><label
                            class="checkbox-label" for="only_not_paid">Only Not Paid Out</label>
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
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[integer]]" id="batch_number_from"
                           name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12"
                           class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_no_batch_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_no_batch" name="include_no_batch"><label
                            class="checkbox-label" for="include_no_batch">Include Donations without Batch</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_only_online_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_only_online" name="include_only_online"><label
                            class="checkbox-label" for="include_only_online">Include ONLY donations made online</label>
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
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_designation_groups_row">
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
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_designations_row">
                    <label for="designations">Designations</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_project_name_row">
                    <label for="project_name">Project</label>
                    <input tabindex="10" type="text" size="30" maxlength="30" id="project_name" name="project_name">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_project_row">
                    <input tabindex="10" type="checkbox" id="include_project" name="include_project"><label
                            class="checkbox-label" for="include_project">Include Project (Detail Only)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_notes_row">
                    <input tabindex="10" type="checkbox" id="include_notes" name="include_notes"><label
                            class="checkbox-label" for="include_notes">Include Notes (Detail Only)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_subtotals_row">
                    <input tabindex="10" type="checkbox" id="include_subtotals" name="include_subtotals"><label
                            class="checkbox-label" for="include_subtotals">Subtotal by Designation Type</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_state_row">
                    <label for="state">State of Donor</label>
                    <select tabindex="10" id="state" name="state">
                        <option value="">[All]</option>
						<?php
						foreach (getStateArray() as $stateCode => $state) {
							?>
                            <option value="<?= $stateCode ?>"><?= $state ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
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
                    const reportType = $("#report_type").val();
                    if (reportType === "export" || reportType === "file") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
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

$pageObject = new DesignationTotalsReportPage();
$pageObject->displayPage();
