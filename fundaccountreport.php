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

$GLOBALS['gPageCode'] = "FUNDACCOUNTREPORT";
require_once "shared/startup.inc";

class FundAccountReportPage extends Page implements BackgroundReport {

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

		processPresetDates($_POST['preset_dates'], "entry_date_from", "entry_date_to");

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['fund_account_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "fund_account_id = ?";
			$parameters[] = $_POST['fund_account_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Fund Account is " . getFieldFromId("description", "fund_accounts", "fund_account_id", $_POST['fund_account_id']);
		}

		if (!empty($_POST['date_paid_out'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date_paid_out is " . ($_POST['date_paid_out'] == "not_set" ? "" : "not") . " null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Date Paid Out is " . ($_POST['date_paid_out'] == "not_set" ? "not" : "") . " set";
		}

		if (!empty($_POST['entry_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "entry_date >= ?";
			$parameters[] = makeDateParameter($_POST['entry_date_from']);
		}
		if (!empty($_POST['entry_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "entry_date <= ?";
			$parameters[] = makeDateParameter($_POST['entry_date_to']);
		}
		if (!empty($_POST['entry_date_from']) && !empty($_POST['entry_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Entry Time is between " . date("m/d/Y", strtotime($_POST['entry_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['entry_date_to']));
		} else if (!empty($_POST['entry_date_from'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Entry Time is on or after " . date("m/d/Y", strtotime($_POST['entry_date_from']));
		} else if (!empty($_POST['entry_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Entry Time is on or before " . date("m/d/Y", strtotime($_POST['entry_date_to']));
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

		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
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
		} else {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "designation_id in (select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ")";
		}

		if (!empty($_POST['fund_accounts'])) {
			$fundAccountArray = explode(",", $_POST['fund_accounts']);
		} else {
			$fundAccountArray = array();
		}
		if (count($fundAccountArray) > 0) {
			$designationWhere = "";
			$displayDesignations = "";
			foreach ($fundAccountArray as $fundAccountId) {
				$fundAccountId = getFieldFromId("fund_account_id", "fund_accounts", "fund_account_id", $fundAccountId);
				if (empty($fundAccountId)) {
					continue;
				}
				if (!empty($designationWhere)) {
					$designationWhere .= ",";
				}
				$designationWhere .= "?";
				$parameters[] = $fundAccountId;
				if (!empty($displayDesignations)) {
					$displayDesignations .= ", ";
				}
				$displayDesignations .= getFieldFromId("fund_account_code", "fund_accounts", "fund_account_id", $fundAccountId);
			}
			if (!empty($designationWhere)) {
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "fund_account_id not in (" . $designationWhere . ")";
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Fund Accounts not in (" . $displayDesignations . ")";
			}
		}

		$detailReport = $_POST['report_type'] == "detail";
		$downloadCSV = $_POST['report_type'] == "download";

		$excludeZero = (!empty($_POST['exclude_zero_balance']));
		$negativeFund = (!empty($_POST['show_only_negative']));
		if ($negativeFund) {
			$excludeZero = true;
		}
		$fundArray = array();
		$resultSet = executeReadQuery("select *,(select description from fund_accounts where fund_account_id = fund_account_details.fund_account_id) fund_description," .
			"(select sort_order from designations where designation_id = fund_account_details.designation_id) sort_order," .
			"(select concat_ws(' - ',designation_code,description) from designations where designation_id = fund_account_details.designation_id) designation_description " .
			"from fund_account_details where fund_account_id in (select fund_account_id from fund_accounts where client_id = ?)" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") .
			" order by " . ($_POST['group_by'] == "fund" ? "fund_description,fund_account_id,designation_description,designation_id," : "designation_description,designation_id,fund_description,fund_account_id,") . "entry_date", $parameters);
		while ($row = getNextRow($resultSet)) {
			$fundArray[] = $row;
		}
		$returnArray['report_title'] = "Fund Account Totals " . ($detailReport ? "Details" : "Summary") . " Report";
		$primaryGroupFieldName = ($_POST['group_by'] == "fund" ? "fund_account_id" : "designation_id");
		$secondaryGroupFieldName = ($_POST['group_by'] == "fund" ? "designation_id" : "fund_account_id");
		$primaryGroupDescription = ($_POST['group_by'] == "fund" ? "fund_description" : "designation_description");
		$secondaryGroupDescription = ($_POST['group_by'] == "fund" ? "designation_description" : "fund_description");
		$displayPrimary = "";
		$summarySecondaryDescription = "";
		$summaryPrimaryDescription = "";
		ob_start();
		if ($downloadCSV) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"fund_accounts.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "fund_accounts.csv";
			if ($_POST['group_by'] == "fund") {
				echo "\"Designation\",\"Fund Account\",\"Total\"\r\n";
			} else {
				echo "\"Fund Account\",\"Designation\",\"Total\"\r\n";
			}
		} else {
			?>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?> group by <?= $_POST['group_by'] ?></p>
            <table class="grid-table">
			<?php if ($detailReport) { ?>
                <tr>
					<?php if ($_POST['group_by'] == "fund") { ?>
                        <th>Fund</th>
                        <th>Designation</th>
					<?php } else { ?>
                        <th>Designation</th>
                        <th>Fund</th>
					<?php } ?>
                    <th>Entered</th>
                    <th>Paid Out</th>
                    <th>Entry Description</th>
                    <th>Entry Type</th>
                    <th>Ref #</th>
                    <th>Amount</th>
                    <th>Total</th>
                </tr>
			<?php } else { ?>
                <tr>
					<?php if ($_POST['group_by'] == "fund") { ?>
                        <th>Fund</th>
                        <th>Designation</th>
					<?php } else { ?>
                        <th>Designation</th>
                        <th>Fund</th>
					<?php } ?>
                    <th>Total</th>
                </tr>
			<?php } ?>
			<?php
		}
		if ($detailReport) {
			$savePrimaryGroup = "";
			$saveSecondaryGroup = "";
			$primaryTotal = 0;
			$secondaryTotal = 0;
			$secondaryCount = 0;
			$displaySecondary = "";
			foreach ($fundArray as $row) {
				if ($row[$primaryGroupFieldName] != $savePrimaryGroup || $row[$secondaryGroupFieldName] != $saveSecondaryGroup) {
					if (!empty($saveSecondaryGroup)) {
						?>
                        <tr>
                            <td class="highlighted-text"><?= $displayPrimary ?></td>
                            <td class="highlighted-text" colspan='7'>Total for <?= $summarySecondaryDescription ?></td>
                            <td class="align-right highlighted-text"><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                        </tr>
						<?php
						$displayPrimary = "";
					}
					if ($row[$primaryGroupFieldName] != $savePrimaryGroup) {
						if (!empty($savePrimaryGroup)) {
							?>
                            <tr class="thick-bottom">
                                <td class="highlighted-text" colspan='8'><?= "Total for " . $summaryPrimaryDescription ?></td>
                                <td class="align-right highlighted-text"><?= number_format($primaryTotal, 2, ".", ",") ?></td>
                            </tr>
							<?php
						}
						$savePrimaryGroup = $row[$primaryGroupFieldName];
						$displayPrimary = $row[$primaryGroupDescription];
						$summaryPrimaryDescription = $displayPrimary;
						$primaryTotal = 0;
						$secondaryCount = 0;
					}
					$secondaryCount++;
					$saveSecondaryGroup = $row[$secondaryGroupFieldName];
					$displaySecondary = $row[$secondaryGroupDescription];
					$summarySecondaryDescription = $row[$secondaryGroupDescription];
					if (!empty($_POST['entry_date_from'])) {
						$balanceSet = executeReadQuery("select sum(amount) from fund_account_details where designation_id = ? and " .
							"fund_account_id = ? and entry_date < ?", $row['designation_id'], $row['fund_account_id'], makeDateParameter($_POST['entry_date_from']));
						if ($balanceRow = getNextRow($balanceSet)) {
							$secondaryTotal = $balanceRow['sum(amount)'];
							if (!empty($secondaryTotal)) {
								?>
                                <tr>
                                    <td class="highlighted-text"><?= $displayPrimary ?></td>
                                    <td class="highlighted-text"><?= $displaySecondary ?></td>
                                    <td></td>
                                    <td colspan="3">Previous Balance</td>
                                    <td></td>
                                    <td class="align-right"><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                                    <td class="align-right"><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                                </tr>
								<?php
								$displayPrimary = "";
								$displaySecondary = "";
							} else {
								$secondaryTotal = 0;
							}
						}
					} else {
						$secondaryTotal = 0;
					}
				}
				$secondaryTotal = round($secondaryTotal + $row['amount'], 2);
				$primaryTotal = round($primaryTotal + $row['amount'], 2);
				?>
                <tr>
                    <td class="highlighted-text"><?= $displayPrimary ?></td>
                    <td class="highlighted-text"><?= $displaySecondary ?></td>
                    <td><?= date("m/d/Y", strtotime($row['entry_date'])) ?></td>
                    <td><?= (empty($row['date_paid_out']) ? "" : date("m/d/Y", strtotime($row['date_paid_out']))) ?></td>
                    <td><?= $row['description'] ?></td>
                    <td><?= getFieldFromId("description", "fund_account_entry_types", "fund_account_entry_type_id", $row['fund_account_entry_type_id']) ?></td>
                    <td><?= (empty($row['pay_period_id']) ? $row['reference_number'] : $row['pay_period_id']) ?></td>
                    <td class="align-right"><?= number_format($row['amount'], 2, ".", ",") ?></td>
                    <td class="align-right"><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                </tr>
				<?php
				$displayPrimary = "";
				$displaySecondary = "";
			}
			if (!empty($saveSecondaryGroup)) {
				if ($secondaryTotal != 0) {
					?>
                    <tr>
                        <td class="highlighted-text"><?= $displayPrimary ?></td>
                        <td class="highlighted-text" colspan='7'>Total for <?= $summarySecondaryDescription ?></td>
                        <td class="align-right highlighted-text"><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                    </tr>
					<?php
				}
			}
			if (!empty($savePrimaryGroup)) {
				?>
                <tr>
                    <td class="highlighted-text" colspan='8'><?= "Total for " . $summaryPrimaryDescription ?></td>
                    <td class="align-right highlighted-text"><?= number_format($primaryTotal, 2, ".", ",") ?></td>
                </tr>
				<?php
			}
		} else {
			$summaryArray = array();
			foreach ($fundArray as $row) {
				$summaryKey = $row['fund_account_id'] . ":" . $row['designation_id'];
				if (!array_key_exists($summaryKey, $summaryArray)) {
					$summaryArray[$summaryKey] = $row;
				} else {
					$summaryArray[$summaryKey]['amount'] += $row['amount'];
				}
			}
			if ($excludeZero || $negativeFund) {
				foreach ($summaryArray as $index => $row) {
					$row['amount'] = round($row['amount'], 2) + 0;
					if ($excludeZero && $row['amount'] == 0) {
						unset($summaryArray[$index]);
					} else if ($negativeFund && $row['amount'] >= 0) {
						unset($summaryArray[$index]);
					}
				}
			}
			$savePrimaryGroup = "";
			$saveSecondaryGroup = "";
			$primaryTotal = 0;
			$secondaryTotal = 0;
			$secondaryCount = 0;
			foreach ($summaryArray as $row) {
				if ($row[$primaryGroupFieldName] != $savePrimaryGroup || $row[$secondaryGroupFieldName] != $saveSecondaryGroup) {
					if (!empty($saveSecondaryGroup)) {
						if ($downloadCSV) {
							echo "\"" . str_replace("\"", "", $displayPrimary) . "\",\"" . str_replace("\"", "", $summarySecondaryDescription) . "\",\"" .
								number_format($secondaryTotal, 2, ".", ",") . "\"\r\n";
						} else {
							?>
                            <tr>
                                <td class=""><?= $displayPrimary ?></td>
                                <td class=""><?= $summarySecondaryDescription ?></td>
                                <td class="align-right "><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                            </tr>
							<?php
							$displayPrimary = "";
						}
					}
					if ($row[$primaryGroupFieldName] != $savePrimaryGroup) {
						if (!empty($savePrimaryGroup) && !$downloadCSV) {
							?>
                            <tr class="thick-bottom">
                                <td class="highlighted-text" colspan='2'><?= "Total for " . $summaryPrimaryDescription ?></td>
                                <td class="align-right "><?= number_format($primaryTotal, 2, ".", ",") ?></td>
                            </tr>
							<?php
						}
						$savePrimaryGroup = $row[$primaryGroupFieldName];
						$displayPrimary = $row[$primaryGroupDescription];
						$summaryPrimaryDescription = $displayPrimary;
						$primaryTotal = 0;
						$secondaryCount = 0;
					}
					$secondaryCount++;
					$saveSecondaryGroup = $row[$secondaryGroupFieldName];
					$displaySecondary = $row[$secondaryGroupDescription];
					$summarySecondaryDescription = $row[$secondaryGroupDescription];
					$secondaryTotal = 0;
				}
				$secondaryTotal = round($secondaryTotal + $row['amount'], 2);
				$primaryTotal = round($primaryTotal + $row['amount'], 2);
			}
			if (!empty($saveSecondaryGroup)) {
				if ($secondaryTotal != 0) {
					if ($downloadCSV) {
						echo "\"" . str_replace("\"", "", $displayPrimary) . "\",\"" . str_replace("\"", "", $summarySecondaryDescription) . "\",\"" .
							number_format($secondaryTotal, 2, ".", ",") . "\"\r\n";
					} else {
						?>
                        <tr>
                            <td class=""><?= $displayPrimary ?></td>
                            <td class=""><?= $summarySecondaryDescription ?></td>
                            <td class="align-right "><?= number_format($secondaryTotal, 2, ".", ",") ?></td>
                        </tr>
						<?php
					}
				}
			}
			if (!empty($savePrimaryGroup) && !$downloadCSV) {
				?>
                <tr>
                    <td class="highlighted-text" colspan='2'><?= "Total for " . $summaryPrimaryDescription ?></td>
                    <td class="align-right "><?= number_format($primaryTotal, 2, ".", ",") ?></td>
                </tr>
				<?php
			}
		}
		if ($downloadCSV) {
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
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                        <option value="download">CSV</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_group_by_row">
                    <label for="group_by">Total By</label>
                    <select tabindex="10" id="group_by" name="group_by">
                        <option value="designation">Designation</option>
                        <option value="fund">Fund</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_entry_date_row">
                    <label for="entry_date_from">Entry Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="entry_date_from" name="entry_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="entry_date_to" name="entry_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_fund_account_id_row">
                    <label for="fund_account_id">Fund Account</label>
                    <select tabindex="10" id="fund_account_id" name="fund_account_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from fund_accounts where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['fund_account_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_date_paid_out_id_row">
                    <label for="date_paid_out_id">Date Paid Out</label>
                    <select tabindex="10" id="date_paid_out_id" name="date_paid_out_id">
                        <option value="">[All]</option>
                        <option value="set">Set</option>
                        <option value="not_set">Not Set</option>
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
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
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
				<?php } ?>

				<?php
				$fundAccountControl = new DataColumn("fund_accounts");
				$fundAccountControl->setControlValue("data_type", "custom");
				$fundAccountControl->setControlValue("include_inactive", "true");
				$fundAccountControl->setControlValue("control_class", "MultiSelect");
				$fundAccountControl->setControlValue("control_table", "fund_accounts");
				$fundAccountControl->setControlValue("links_table", "designation_fund_accounts");
				$fundAccountControl->setControlValue("primary_table", "designations");
				$customControl = new MultipleSelect($fundAccountControl, $this);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_fund_accounts_row">
                    <label for="fund_accounts">Exclude Fund Accounts</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_exclude_zero_balance_row">
                    <input tabindex="10" type="checkbox" id="exclude_zero_balance" name="exclude_zero_balance"><label class="checkbox-label" for="exclude_zero_balance">Exclude Fund with Zero Balance (Summary Only)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_show_only_negative_row">
                    <input tabindex="10" type="checkbox" id="show_only_negative" name="show_only_negative"><label class="checkbox-label" for="show_only_negative">Show Only Funds with Negative Balance (Summary Only)</label>
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
            $(".inactive-option").removeClass("inactive-option");
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("fundaccount.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "download") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
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
        </style>
        <style id="_printable_style">
            .grid-table {
                border-left: none;
                border-bottom: none;
            }

            .grid-table tr.thick-bottom td {
                border-bottom-width: 4px;
            }

            .grid-table tr:nth-child(even) td {
                background-color: rgb(230, 230, 230);
            }

            .grid-table tr:nth-child(even) td.spacer {
                background-color: rgb(255, 255, 255);
            }

            .grid-table td.spacer {
                border: none;
            }
        </style>
		<?php
	}
}

$pageObject = new FundAccountReportPage();
$pageObject->displayPage();
