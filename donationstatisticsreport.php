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

$GLOBALS['gPageCode'] = "DONATIONSTATISTICSREPORT";
require_once "shared/startup.inc";

class DonationStatisticsReportPage extends Page implements BackgroundReport {

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

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['contact_type_id'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "contact_id in (select contact_id from contacts where contact_type_id = ?)";
			$parameters[] = $_POST['contact_type_id'];
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

		$resultsArray = array();
		$contactIds = array();
		$totalDonations = 0;
		$totalGifts = 0;
		$smallestGift = 1000000000;
		$largestGift = 0;
		$giftAmountArray = array();
		$giftAmountArray[] = array("min" => 0, "max" => 9.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		$giftAmountArray[] = array("min" => 10, "max" => 24.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		$giftAmountArray[] = array("min" => 25, "max" => 49.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		$giftAmountArray[] = array("min" => 50, "max" => 99.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		$giftAmountArray[] = array("min" => 100, "max" => 249.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		$giftAmountArray[] = array("min" => 250, "max" => 499.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		$giftAmountArray[] = array("min" => 500, "max" => 999.99, "count" => 0, "amount" => 0, "contact_ids" => array());
		for ($x = 1; $x <= 19; $x++) {
			$giftAmountArray[] = array("min" => ($x * 1000), "max" => ((($x + 1) * 1000) - .01), "count" => 0, "amount" => 0, "contact_ids" => array());
		}
		$giftAmountArray[] = array("min" => 20000, "count" => 0, "amount" => 0);
		$designationArray = array();
		$monthArray = array();
		$resultSet = executeReadQuery("select *,(select designation_code from designations where designation_id = donations.designation_id) designation_code," .
			"(select date_posted from donation_batches where donation_batch_id = donations.donation_batch_id) date_posted," .
			"(select batch_number from donation_batches where donation_batch_id = donations.donation_batch_id) batch_number," .
			"(select state from contacts where contact_id = donations.contact_id) state " .
			"from donations where associated_donation_id is null and client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : ""), $parameters);
		if ($resultSet['row_count'] == 0) {
			$smallestGift = 0;
		}
		$stateArray = getStateArray(true);
		foreach ($stateArray as $stateCode => $stateName) {
			$stateArray[$stateCode] = array("count" => 0, "total" => 0, "contact_ids" => array());
		}
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['contact_id'], $contactIds)) {
				$contactIds[$row['contact_id']] = array("count" => 0, "amount" => 0);
			}
			$contactIds[$row['contact_id']]['count']++;
			$contactIds[$row['contact_id']]['amount'] += $row['amount'];
			$totalDonations += $row['amount'];
			$totalGifts++;
			if ($row['amount'] < $smallestGift) {
				$smallestGift = $row['amount'];
			}
			if ($row['amount'] > $largestGift) {
				$largestGift = $row['amount'];
			}
			foreach ($giftAmountArray as $thisAmountKey => $thisGiftAmount) {
				if ($row['amount'] >= $thisGiftAmount['min'] && (!array_key_exists("max", $thisGiftAmount) || $row['amount'] <= $thisGiftAmount['max'])) {
					$giftAmountArray[$thisAmountKey]['count']++;
					$giftAmountArray[$thisAmountKey]['amount'] += $row['amount'];
					$giftAmountArray[$thisAmountKey]['contact_ids'][$row['contact_id']] = $row['contact_id'];
				}
			}
			if (array_key_exists($row['state'], $stateArray)) {
				$stateArray[$row['state']]['count']++;
				$stateArray[$row['state']]['amount'] += $row['amount'];
				$stateArray[$row['state']]['contact_ids'][$row['contact_id']] = $row['contact_id'];
			}
			$designationKey = "D" . $row['designation_code'];
			if (!array_key_exists($designationKey, $designationArray)) {
				$designationArray[$designationKey] = array("designation_code" => $row['designation_code'], "designation_id" => $row['designation_id'], "count" => 0, "amount" => 0, "contact_ids" => array(), "months" => array());
			}
			$designationArray[$designationKey]['count']++;
			$designationArray[$designationKey]['amount'] += $row['amount'];
			$designationArray[$designationKey]['contact_ids'][$row['contact_id']] = $row['contact_id'];
			$monthKey = date("Y-m", strtotime($row['donation_date']));
			if (!array_key_exists($monthKey, $monthArray)) {
				$monthArray[$monthKey] = $monthKey;
			}
			if (!array_key_exists($monthKey, $designationArray[$designationKey]['months'])) {
				$designationArray[$designationKey]['months'][$monthKey] = array("count" => 0, "amount" => 0);
			}
			$designationArray[$designationKey]['months'][$monthKey]['count']++;
			$designationArray[$designationKey]['months'][$monthKey]['amount'] += $row['amount'];
		}
		$smallestDonor = (count($contactIds) == 0 ? 0 : 1000000000);
		$largestDonor = 0;
		$giftChartArray = array();
		$giftChartArray[] = array("min" => 1, "max" => 1, "count" => 0, "amount" => 0);
		$giftChartArray[] = array("min" => 2, "max" => 3, "count" => 0, "amount" => 0);
		$giftChartArray[] = array("min" => 4, "max" => 6, "count" => 0, "amount" => 0);
		$giftChartArray[] = array("min" => 7, "max" => 11, "count" => 0, "amount" => 0);
		$giftChartArray[] = array("min" => 12, "count" => 0, "amount" => 0);
		foreach ($contactIds as $contactId => $giftArray) {
			if ($giftArray['amount'] < $smallestDonor) {
				$smallestDonor = $giftArray['amount'];
			}
			if ($giftArray['amount'] > $largestDonor) {
				$largestDonor = $giftArray['amount'];
			}
			foreach ($giftChartArray as $thisGiftKey => $thisGiftAmount) {
				if ($giftArray['count'] >= $thisGiftAmount['min'] && (!array_key_exists("max", $thisGiftAmount) || $giftArray['count'] <= $thisGiftAmount['max'])) {
					$giftChartArray[$thisGiftKey]['count']++;
					$giftChartArray[$thisGiftKey]['amount'] += $giftArray['amount'];
				}
			}
		}
		ob_start();
		?>
        <h1>Donation Statistics Report</h1>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table class="grid-table">
            <tr>
                <td>Gift Count</td>
                <td><?= $totalGifts ?></td>
            </tr>
            <tr>
                <td>Total Donors</td>
                <td><?= count($contactIds) ?></td>
            </tr>
            <tr>
                <td>Total Donations</td>
                <td>$<?= number_format($totalDonations, 2) ?></td>
            </tr>
            <tr>
                <td>Smallest Donor</td>
                <td>$<?= number_format($smallestDonor, 2) ?></td>
            </tr>
            <tr>
                <td>Largest Donor</td>
                <td>$<?= number_format($largestDonor, 2) ?></td>
            </tr>
            <tr>
                <td>Average Donor</td>
                <td>$<?= number_format((count($contactIds) == 0 ? 0 : $totalDonations / count($contactIds)), 2) ?></td>
            </tr>
            <tr>
                <td>Smallest Gift</td>
                <td>$<?= number_format($smallestGift, 2) ?></td>
            </tr>
            <tr>
                <td>Largest Gift</td>
                <td>$<?= number_format($largestGift, 2) ?></td>
            </tr>
            <tr>
                <td>Average Gift</td>
                <td>$<?= number_format(($totalGifts == 0 ? 0 : $totalDonations / $totalGifts), 2) ?></td>
            </tr>
        </table>
        <h2>Number of Gifts</h2>
        <table class="grid-table">
            <tr>
                <th>Gifts Given</th>
                <th>Donors</th>
                <th>Avg Amount</th>
            </tr>
			<?php
			foreach ($giftChartArray as $thisGiftAmount) {
				if ($thisGiftAmount['count'] == 0) {
					continue;
				}
				?>
                <tr>
                    <td><?= $thisGiftAmount['min'] . (array_key_exists("max", $thisGiftAmount) && $thisGiftAmount['max'] != $thisGiftAmount['min'] ? "-" . $thisGiftAmount['max'] : (!array_key_exists("max", $thisGiftAmount) ? " or more" : "")) ?> gift<?= ($thisGiftAmount['min'] == 1 && $thisGiftAmount['max'] == 1 ? "" : "s") ?></td>
                    <td class="align-right"><?= $thisGiftAmount['count'] ?></td>
                    <td class="align-right">$<?= number_format(($thisGiftAmount['count'] == 0 ? 0 : $thisGiftAmount['amount'] / $thisGiftAmount['count']), 2) ?></td>
                </tr>
			<?php } ?>
        </table>
        <h2>Gift Amounts</h2>
        <table class="grid-table">
            <tr>
                <th>Amount</th>
                <th># of Gifts</th>
                <th>Donors</th>
                <th>Total Amount</th>
            </tr>
			<?php
			foreach ($giftAmountArray as $thisGiftAmount) {
				if ($thisGiftAmount['count'] == 0) {
					continue;
				}
				?>
                <tr>
                    <td>$<?= number_format($thisGiftAmount['min'], 2) . (array_key_exists("max", $thisGiftAmount) && $thisGiftAmount['max'] != $thisGiftAmount['min'] ? " - $" . number_format($thisGiftAmount['max'], 2) : (!array_key_exists("max", $thisGiftAmount) ? " or more" : "")) ?></td>
                    <td class="align-right"><?= $thisGiftAmount['count'] ?></td>
                    <td class="align-right"><?= count($thisGiftAmount['contact_ids']) ?></td>
                    <td class="align-right">$<?= number_format($thisGiftAmount['amount'], 2) ?></td>
                </tr>
			<?php } ?>
        </table>
        <h2>By State</h2>
        <table class='grid-table'>
            <tr>
                <th>State</th>
                <th># of Gifts</th>
                <th># of Donors</th>
                <th>Total Amount</th>
                <th>Average Gift</th>
            </tr>
			<?php
			foreach ($stateArray as $stateCode => $stateInfo) {
				?>
                <tr>
                    <td><?= $stateCode ?></td>
                    <td class='align-right'><?= $stateInfo['count'] ?></td>
                    <td class='align-right'><?= count($stateInfo['contact_ids']) ?></td>
                    <td class='align-right'><?= number_format($stateInfo['amount'], 2, ".", ",") ?></td>
                    <td class='align-right'><?= ($stateInfo['count'] == 0 ? "0.00" : number_format(round($stateInfo['amount'] / $stateInfo['count'], 2), 2, ".", ",")) ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		if (!empty($_POST['include_designations'])) {
			ksort($monthArray);
			ksort($designationArray);
			?>
            <h2>Designation Amounts</h2>
            <table class="grid-table">
				<?php if (!empty($_POST['monthly_totals'])) { ?>
                    <tr>
                        <th></th>
						<?php
						foreach ($monthArray as $thisMonth) {
							$monthDisplay = date("M Y", strtotime($thisMonth . "-01"));
							?>
                            <th colspan="2" class="align-center"><?= $monthDisplay ?></th>
							<?php
						}
						?>
                        <th></th>
                    </tr>
				<?php } ?>
                <tr>
                    <th>Designation</th>
					<?php if (empty($_POST['monthly_totals'])) { ?>
                        <th>Gifts</th>
                        <th>Donors</th>
					<?php } else { ?>
						<?php
						foreach ($monthArray as $thisMonth) {
							?>
                            <th>Gifts</th>
                            <th>Total</th>
							<?php
						}
						?>
					<?php } ?>
                    <th>Total</th>
                </tr>
				<?php
				foreach ($designationArray as $thisDesignationAmount) {
					if ($thisDesignationAmount['count'] == 0) {
						continue;
					}
					?>
                    <tr>
                        <td><?= $thisDesignationAmount['designation_code'] . (empty($_POST['monthly_totals']) ? " - " . getFieldFromId("description", "designations", "designation_id", $thisDesignationAmount['designation_id']) : "") ?></td>
						<?php if (empty($_POST['monthly_totals'])) { ?>
                            <td class="align-right"><?= $thisDesignationAmount['count'] ?></td>
                            <td class="align-right"><?= count($thisDesignationAmount['contact_ids']) ?></td>
						<?php } else { ?>
							<?php
							foreach ($monthArray as $thisMonth) {
								$count = $thisDesignationAmount['months'][$thisMonth]['count'];
								$amount = $thisDesignationAmount['months'][$thisMonth]['amount'];
								if (empty($count)) {
									$count = 0;
									$amount = 0;
								}
								?>
                                <td class="align-right"><?= $count ?></td>
                                <td class="align-right">$<?= number_format($amount, 2, ".", ",") ?></td>
								<?php
							}
							?>
						<?php } ?>
                        <td class="align-right">$<?= number_format($thisDesignationAmount['amount'], 2) ?></td>
                    </tr>
				<?php } ?>
            </table>
		<?php } ?>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		$returnArray['report_title'] = "Donation Statistics Report";
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line" id="_contact_type_id_row">
                    <label for="contact_type_id">Contact Type</label>
                    <select tabindex="10" id="contact_type_id" name="contact_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from contact_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['contact_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label">Limit to donations from contacts of this type</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line preset-date-custom" id="_donation_date_row">
                    <label for="donation_date_from" class="required-label">Donation Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date],required] datepicker" id="donation_date_from" name="donation_date_from">
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

                <div class="basic-form-line" id="_include_designations_row">
                    <input tabindex="10" type="checkbox" checked id="include_designations" name="include_designations"><label class="checkbox-label" for="include_designations">Include Designation Totals</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_monthly_totals_row">
                    <input tabindex="10" type="checkbox" id="monthly_totals" name="monthly_totals"><label class="checkbox-label" for="monthly_totals">Include Monthly Totals for Designations</label>
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
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("donationstatistics.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
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

$pageObject = new DonationStatisticsReportPage();
$pageObject->displayPage();
