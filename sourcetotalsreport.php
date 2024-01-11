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

$GLOBALS['gPageCode'] = "SOURCETOTALSREPORT";
require_once "shared/startup.inc";

class SourceTotalsReportPage extends Page implements BackgroundReport {

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

		if (!empty($_POST['donation_source_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "donation_source_id = ?";
			$parameters[] = $_POST['donation_source_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Donation Source is '" . getFieldFromId("description", "donation_sources", "donation_source_id", $_POST['donation_source_id']) . "'";
		}

		if (!empty($_POST['state'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "contact_id in (select contact_id from contacts where client_id = ? and state = ?)";
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
		$detailOutput = $_POST['report_type'] == "export";
		ob_start();
		if ($detailOutput) {
			echo "\"Designation Code\",\"Date\",\"Donor\",\"Address 1\",\"Address 2\",\"City\",\"State\",\"Postal Code\",\"Country\",\"Batch Number\",\"Amount\"\r\n";
		}

		$donationArray = array();
		$resultSet = executeReadQuery("select *,(select donation_source_code from donation_sources where donation_source_id = donations.donation_source_id) donation_source_code," .
			"(select date_posted from donation_batches where donation_batch_id = donations.donation_batch_id) date_posted," .
			"(select batch_number from donation_batches where donation_batch_id = donations.donation_batch_id) batch_number" .
			($fileOutput ? "" : ",(select description from donation_source_groups where donation_source_group_id = (select donation_source_group_id from donation_sources where donation_source_id = donations.donation_source_id)) donation_source_group") .
			" from donations where donation_source_id is not null and client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by " . ($fileOutput ? "" : "donation_source_group,") . "donation_source_code,donation_date", $parameters);
		while ($row = getNextRow($resultSet)) {
			if ($detailOutput) {
				$contactRow = Contact::getContact($row['contact_id']);
				echo "\"" . str_replace("\"", "", $row['donation_source_code']) . "\",\"" . date("m/d/y", strtotime($row['donation_date'])) . "\",\"" .
					str_replace('"', "", getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "")) . '",' .
					str_replace('"', "", $contactRow['address_1']) . '",' .
					str_replace('"', "", $contactRow['address_2']) . '",' .
					str_replace('"', "", $contactRow['city']) . '",' .
					str_replace('"', "", $contactRow['state']) . '",' .
					str_replace('"', "", $contactRow['postal_code']) . '",' .
					str_replace('"', "", getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id'])) . '",' .
					$row['batch_number'] . "\",\"" .
					number_format($row['amount'], 2) . "\"\r\n";
				continue;
			}
			$donationArray[] = $row;
		}
		if ($detailOutput) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"sourcetotals.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "sourcetotals.csv";

			$returnArray['report_export'] = ob_get_clean();
			return $returnArray;
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
			$returnArray['report_title'] = "Donation Source Code Totals " . ($detailReport ? "Details" : "Summary") . " Report";
			?>
            <p><?= $displayCriteria ?></p>
			<?php if (count($batchNumberArray) > 0) { ?>
                <p>This report includes the following unposted batches: <?= implode(",", $batchNumberArray) ?></p>
			<?php } ?>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
			<?php if ($detailReport) { ?>
                <tr>
                    <th>Donation Source</th>
                    <th>Date</th>
                    <th>From</th>
					<?php if ($_POST['include_project']) { ?>
                        <th>Project</th>
					<?php } ?>
                    <th>Batch #</th>
                    <th>Recur</th>
                    <th>Amount</th>
                </tr>
			<?php } else { ?>
                <tr>
                    <th>Donation Source Code</th>
                    <th>Description</th>
                    <th class="align-right">Count</th>
                    <th class="align-right">Total</th>
                    <th class="align-right">Fees</th>
                    <th class="align-right">Net</th>
                </tr>
			<?php } ?>
			<?php
		}
		$saveGroup = "";
		$saveGroupCount = 0;
		$saveGroupFee = 0;
		$saveGroupTotal = 0;
		$saveDonationSource = "";
		$saveCount = 0;
		$saveFee = 0;
		$saveAmount = 0;
		foreach ($donationArray as $row) {
			if (empty($row['donation_fee'])) {
				$row['donation_fee'] = 0;
			}
			if ($row['donation_source_id'] != $saveDonationSource) {
				if (!empty($saveDonationSource)) {
					if ($detailReport) {
						?>
                        <tr>
                            <td colspan="<?= ($_POST['include_project'] ? "4" : "3") ?>" class="highlighted-text">Total for <?= getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) . " - " . getFieldFromId("description", "donation_sources", "donation_source_id", $saveDonationSource) ?></td>
                            <td class="highlighted-text align-center" colspan="2"><?= number_format($saveCount, 0) ?> gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                            <td class="align-right highlighted-text"><?= number_format($saveAmount, 2) ?></td>
                        </tr>
						<?php
					} else {
						if ($fileOutput) {
							echo getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) . "\t" .
								getFieldFromId("description", "donation_sources", "donation_source_id", $saveDonationSource) . "\t" .
								number_format($saveAmount, 2) . "\n";
						} else {
							?>
                            <tr>
                                <td><?= getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) ?></td>
                                <td><?= getFieldFromId("description", "donation_sources", "donation_source_id", $saveDonationSource) ?></td>
                                <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                                <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                                <td class="align-right"><?= number_format($saveFee, 2) ?></td>
                                <td class="align-right"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                            </tr>
							<?php
						}
					}
				}
				$saveDonationSource = $row['donation_source_id'];
				$saveCount = 0;
				$saveFee = 0;
				$saveAmount = 0;
			}

			if ($row['donation_source_group'] != $saveGroup) {
				if (!empty($saveGroupCount)) {
					if (empty($saveGroup)) {
						$saveGroup = "[NONE]";
					}
					if ($detailReport) {
						?>
                        <tr>
                            <td colspan="<?= ($_POST['include_project'] ? "4" : "3") ?>" class="border-bottom align-right highlighted-text">Total for donation source group <?= $saveGroup ?></td>
                            <td class="border-bottom align-center highlighted-text" colspan="2"><?= number_format($saveGroupCount, 0) ?> gift<?= ($saveGroupCount == 1 ? "" : "s") ?></td>
                            <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupTotal, 2) ?></td>
                        </tr>
						<?php
					} else {
						if (!$fileOutput) {
							?>
                            <tr>
                                <td colspan="2" class="border-bottom align-right highlighted-text">Total for donation source group <?= $saveGroup ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupCount, 0) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupTotal, 2) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupFee, 2) ?></td>
                                <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupTotal - $saveGroupFee, 2) ?></td>
                            </tr>
							<?php
						}
					}
				}
				$saveGroup = $row['donation_source_group'];
				$saveGroupCount = 0;
				$saveGroupFee = 0;
				$saveGroupTotal = 0;
			}

			$totalCount++;
			$totalDonations += $row['amount'];
			$totalFees += $row['donation_fee'];
			$saveCount++;
			$saveFee += $row['donation_fee'];
			$saveAmount += $row['amount'];
			$saveGroupCount++;
			$saveGroupFee += $row['donation_fee'];
			$saveGroupTotal += $row['amount'];
			if ($detailReport) {
				$displayName = getDisplayName($row['contact_id']) . (!empty($row['anonymous_gift']) ? " - ANONYMOUS" : "");
				$contactRow = Contact::getContact($row['contact_id']);
				$displayName .= "<br>" . $contactRow['address_1'];
				if (!empty($contactRow['address_2'])) {
					$displayName .= "<br>" . $contactRow['address_2'];
				}
				$displayName .= "<br>" . $contactRow['city'];
				if (!empty($contactRow['state'])) {
					$displayName .= ", " . $contactRow['state'];
				}
				if (!empty($contactRow['postal_code'])) {
					$displayName .= " " . $contactRow['postal_code'];
				}
				if ($contactRow['country_id'] != 1000) {
					$displayName .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']);
				}
				?>
                <tr>
                    <td><?= ($saveCount == 1 ? getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) : "") ?></td>
                    <td><?= date("m/d/Y", strtotime($row['donation_date'])) ?></td>
                    <td><?= $displayName ?></td>
					<?php if ($_POST['include_project']) { ?>
                        <td><?= htmlText($row['project_name']) ?></td>
					<?php } ?>
                    <td class="align-right"><?= getFieldFromId("batch_number", "donation_batches", "donation_batch_id", $row['donation_batch_id']) ?></td>
                    <td class="align-center"><?= (empty($row['recurring_donation_id']) ? "" : "YES") ?></td>
                    <td class="align-right"><?= number_format($row['amount'], 2) ?></td>
                </tr>
				<?php
			}
		}
		if (!empty($saveDonationSource)) {
			if ($detailReport) {
				?>
                <tr>
                    <td colspan="<?= ($_POST['include_project'] ? "4" : "3") ?>" class="highlighted-text">Total for <?= getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) . " - " . getFieldFromId("description", "donation_sources", "donation_source_id", $saveDonationSource) ?></td>
                    <td class="highlighted-text align-center" colspan="2"><?= number_format($saveCount, 0) ?> gift<?= ($saveCount == 1 ? "" : "s") ?></td>
                    <td class="align-right highlighted-text"><?= number_format($saveAmount, 2) ?></td>
                </tr>
				<?php
			} else {
				if ($fileOutput) {
					echo getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) . "\t" .
						getFieldFromId("description", "donation_sources", "donation_source_id", $saveDonationSource) . "\t" .
						number_format($saveAmount, 2) . "\n";
				} else {
					?>
                    <tr>
                        <td><?= getFieldFromId("donation_source_code", "donation_sources", "donation_source_id", $saveDonationSource) ?></td>
                        <td><?= getFieldFromId("description", "donation_sources", "donation_source_id", $saveDonationSource) ?></td>
                        <td class="align-right"><?= number_format($saveCount, 0) ?></td>
                        <td class="align-right"><?= number_format($saveAmount, 2) ?></td>
                        <td class="align-right"><?= number_format($saveFee, 2) ?></td>
                        <td class="align-right"><?= number_format($saveAmount - $saveFee, 2) ?></td>
                    </tr>
					<?php
				}
			}
		}
		if (!empty($saveGroupCount)) {
			if (empty($saveGroup)) {
				$saveGroup = "[NONE]";
			}
			if ($detailReport) {
				?>
                <tr>
                    <td colspan="<?= ($_POST['include_project'] ? "4" : "3") ?>" class="border-bottom align-right highlighted-text">Total for donation source group <?= $saveGroup ?></td>
                    <td class="border-bottom align-center highlighted-text" colspan="2"><?= number_format($saveGroupCount, 0) ?> gift<?= ($saveGroupCount == 1 ? "" : "s") ?></td>
                    <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupTotal, 2) ?></td>
                </tr>
				<?php
			} else {
				if (!$fileOutput) {
					?>
                    <tr>
                        <td colspan="2" class="border-bottom align-right highlighted-text">Total for donation source group <?= $saveGroup ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupCount, 0) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupTotal, 2) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupFee, 2) ?></td>
                        <td class="border-bottom align-right highlighted-text"><?= number_format($saveGroupTotal - $saveGroupFee, 2) ?></td>
                    </tr>
					<?php
				}
			}
		}
		if (!$fileOutput) {
			?>
            <tr>
                <td colspan='<?= ($detailReport ? ($_POST['include_project'] ? "4" : "3") : "2") ?>' class="total-line">Total for Report</td>
                <td colspan='<?= ($detailReport ? "2" : "1") ?>' class="total-line align-center"><?= number_format($totalCount, 0) ?> gift<?= ($totalCount == 1 ? "" : "s") ?></td>
                <td class="total-line align-right"><?= number_format($totalDonations, 2) ?></td>
				<?php if (!$detailReport) { ?>
                    <td class="align-right total-line"><?= number_format($totalFees, 2) ?></td>
                    <td class="align-right total-line"><?= number_format($totalDonations - $totalFees, 2) ?></td>
				<?php } ?>
            </tr>
            </table>
			<?php
		}
		$reportContent = ob_get_clean();
		if ($fileOutput) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/plain";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"donationsources.txt\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['report_export'] = $reportContent;
			$returnArray['filename'] = "donationsources.txt";
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

                <div class="form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                        <option value="file">Delimited</option>
                        <option value="export">Delimited Detail</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_donation_id_row">
                    <label for="donation_id">Donation ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="donation_id" name="donation_id">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_contact_id_row">
                    <label for="contact_id">Donor ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="contact_id" name="contact_id">
                    <div class='clear-div'></div>
                </div>

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

                <div class="form-line" id="_payment_method_type_id_row">
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
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_payment_method_id_row">
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
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_batch_number_row">
                    <label for="batch_number_from">Batch Number: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_from" name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_include_no_batch_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_no_batch" name="include_no_batch"><label class="checkbox-label" for="include_no_batch">Include Donations without Batch</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_donation_source_id_row">
                    <label for="donation_source_group_id">Donation Source</label>
                    <select tabindex="10" id="donation_source_id" name="donation_source_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from donation_sources where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['donation_source_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_donation_source_group_id_row">
                    <label for="donation_source_group_id">Donation Source Group</label>
                    <select tabindex="10" id="donation_source_group_id" name="donation_source_group_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from donation_source_groups where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['donation_source_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
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
                <div class="form-line" id="_designations_row">
                    <label for="designations">Designations</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_project_name_row">
                    <label for="project_name">Project</label>
                    <input tabindex="10" type="text" size="30" maxlength="30" id="project_name" name="project_name">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_include_project_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="include_project" name="include_project"><label class="checkbox-label" for="include_project">Include Project (Detail Only)</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_state_row">
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
                    <div class='clear-div'></div>
                </div>

				<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                    <div class="form-line" id="_extra_where_row">
                        <label for="extra_where">Where</label>
                        <input tabindex="10" type="text" size="60" id="extra_where" name="extra_where">
                        <div class='clear-div'></div>
                    </div>
				<?php } ?>

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
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("sourcetotals.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
                    if (reportType == "export" || reportType == "file") {
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

$pageObject = new SourceTotalsReportPage();
$pageObject->displayPage();
