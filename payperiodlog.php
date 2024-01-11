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

$GLOBALS['gPageCode'] = "PAYPERIODLOG";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function supplementaryContent() {
		?>
		<p>
			<button id="download_quickbooks">Download Quickbooks File</button>
		</p>
		<?php
	}

	function onLoadJavascript() {
		?>
		<script>
            $("#download_quickbooks").click(function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=download_quickbooks&pay_period_id=" + $("#primary_id").val();
                return false;
            });
		</script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "download_quickbooks":
				header("Content-Type: text/iif");
				header("Content-Disposition: attachment; filename=\"gl." . date("Ymd") . ".IIF\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');

				$payPeriodId = getFieldFromId("pay_period_id", "pay_periods", "pay_period_id", $_GET['pay_period_id']);
				if (empty($payPeriodId)) {
					echo "Invalid Pay Period";
					exit;
				}

				$designationIdArray = array();
				$donationArray = array();
				$resultSet = executeQuery("select * from donations where client_id = ? and pay_period_id = ? order by donation_id",
					$GLOBALS['gClientId'], $payPeriodId);
				while ($row = getNextRow($resultSet)) {
					$donationArray[$row['donation_id']] = $row;
					if (!in_array($row['designation_id'], $designationIdArray)) {
						$designationIdArray[] = $row['designation_id'];
					}
				}
				$designationList = implode(",", $designationIdArray);
				$designationArray = array();
				$processErrors = "";
				$resultSet = executeQuery("select * from designations where client_id = ? and designation_id in (" .
					$designationList . ") order by designation_code", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['total_donations'] = 0;
					$row['total_donation_fee'] = 0;
					$row['total_amount'] = 0;
					$row['deductions'] = array();
					$designationArray[$row['designation_id']] = $row;
					$paymentType = getFieldFromId("payment_type", "designation_types", "designation_type_id", $row['designation_type_id']);
					if (!empty($paymentType) && empty($row['class_code'])) {
						$processErrors .= "Designation " . $row['designation_code'] . " has no class code<br>";
					}
					if (!empty($paymentType) && empty($row['full_name'])) {
						$processErrors .= "Designation " . $row['designation_code'] . " has no full name<br>";
					}
					if (!empty($paymentType) && !empty($row['secondary_class_code']) && empty($row['secondary_full_name'])) {
						$processErrors .= "Designation " . $row['designation_code'] . " has a second class code, but no second full name<br>";
					}
					if (empty($row['gl_account_number'])) {
						$processErrors .= "Designation " . $row['designation_code'] . " has no GL account number<br>";
					}
				}
				if (!empty($processErrors)) {
					echo $processErrors;
					exit;
				}
				$designationTypeArray = array();
				$resultSet = executeQuery("select * from designation_types where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$designationTypeArray[$row['designation_type_id']] = $row;
				}

				# Write Header
				echo "!TRNS\tTRNSTYPE\tACCNT\tTOPRINT\tNAME\tADDR1\tADDR2\tADDR3\tADDR4\tADDR5\tMEMO\tDOCNUM\tAMOUNT\tCLASS\n";
				echo "!SPL\tTRNSTYPE\tACCNT\tTOPRINT\tNAME\tADDR1\tADDR2\tADDR3\tADDR4\tADDR5\tMEMO\tDOCNUM\tAMOUNT\tCLASS\n";
				echo "!ENDTRNS\n";

				# Write Staff Support Records
				foreach ($donationArray as $donationId => $donationRow) {
					if (empty($donationRow['donation_fee'])) {
						$donationRow['donation_fee'] = 0;
					}
					if (empty($designationArray[$donationRow['designation_id']]['designation_type_id']) || !$designationTypeArray[$designationArray[$donationRow['designation_id']]['designation_type_id']]['individual_support']) {
						continue;
					}
					$thisAmount = $donationRow['amount'] - $donationRow['donation_fee'];
					$designationArray[$donationRow['designation_id']]['total_donation_fee'] += $donationRow['donation_fee'];
					$designationArray[$donationRow['designation_id']]['total_amount'] += $donationRow['amount'];
					$designationArray[$donationRow['designation_id']]['total_donations'] += $thisAmount;
				}

				$transactionAmount = 0;
				foreach ($designationArray as $designationId => $designationRow) {
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}
					$transactionAmount += $designationRow['total_amount'];
				}

				echo "TRNS\tGENERAL JOURNAL\t" . $this->getPayrollParameter('STAFF_SUPPORT_SUMMARY_ENTRY_ACCOUNT_NUMBER') .
					"\t\t\t\t\t\t\t\t\t" . $this->getPayrollParameter('STAFF_SUPPORT_SUMMARY_ENTRY_DOCUMENT_NUMBER') . "\t" .
					number_format($transactionAmount, 2) . "\t" . $this->getPayrollParameter('STAFF_SUPPORT_SUMMARY_ENTRY_CLASS') . "\n";

				$administrationFeeAmount = 0;
				foreach ($designationArray as $designationId => $designationRow) {
					$designationArray[$designationId]['total_deductions'] = 0;
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}
					$administrationFeeAmount += $designationRow['total_donation_fee'];
					$designationArray[$designationId]['reimbursable_expenses'] = $designationRow['reimbursable_expenses'] = $this->getReimbursableExpenses($designationId, $designationRow['total_donations'], $payPeriodId);
					$deductibleAmount = $designationRow['total_donations'] - $designationRow['reimbursable_expenses'];
					if ($deductibleAmount < 0) {
						$deductibleAmount = 0;
					}
					$deductionAmount = 0;
					if (!empty($designationRow['designation_type_id']) && $designationTypeArray[$designationRow['designation_type_id']]['individual_support']) {
						$saveTotalDonation = $designationRow['total_donations'] - $designationRow['reimbursable_expenses'];
						if ($saveTotalDonation > 0) {
							$resultSet = executeQuery("select * from payroll_deductions where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								if (!empty($row['designation_group_id'])) {
									$designationGroupLinkId = getFieldFromId("designation_group_link_id", "designation_group_links", "designation_id",
										$designationId, "designation_group_id = ?", $row['designation_group_id']);
									if (empty($designationGroupLinkId)) {
										continue;
									}
								}
								$designationDeductionRow = getRowFromId("designation_deductions", "designation_id", $designationId, "(one_time = 0 or pay_period_id = ?) and inactive = 0 and payroll_deduction_id = ?", $payPeriodId, $row['payroll_deduction_id']);
								if (!empty($designationDeductionRow)) {
									$row['amount'] = $designationDeductionRow['amount'];
									$row['percentage'] = $designationDeductionRow['percentage'];
								}
								$thisDeductionAmount = 0;
								if (!empty($row['amount']) && $row['amount'] > 0) {
									$thisDeductionAmount += $row['amount'];
								}
								if (!empty($row['percentage']) && $row['percentage'] > 0 && $row['percentage'] < 100) {
									$thisDeductionAmount += round($saveTotalDonation * $row['percentage'] / 100, 2);
								}
								if ($thisDeductionAmount > $saveTotalDonation) {
									$thisDeductionAmount = $saveTotalDonation;
								}
								if ($thisDeductionAmount > 0) {
									$deductionAmount += $thisDeductionAmount;
									$saveTotalDonation -= $thisDeductionAmount;
									$designationArray[$designationId]['deductions'][] = array("payroll_deduction_id" => $row['payroll_deduction_id'], "amount" => $thisDeductionAmount, "that_amount" => 0);
								}
							}
						}
					}

					$thisAmount = $designationRow['total_donations'] - $deductionAmount;
					if (!empty($designationRow['secondary_class_code'])) {
						$thatAmount = round($thisAmount / 2, 2);
						if (!empty($thatAmount)) {
							echo "SPL\tGENERAL JOURNAL\t" . $designationRow['gl_account_number'] . "\t\t" . $designationRow['secondary_full_name'] .
								"\t\t\t\t\t\t\t" . $this->getPayrollParameter('STAFF_SUPPORT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" .
								number_format($thatAmount * -1, 2) . "\t" . $designationRow['secondary_class_code'] . "\n";
							$thisAmount -= $thatAmount;
						}
						foreach ($designationArray[$designationId]['deductions'] as $index => $deductionInfo) {
							$thisDeductionAmount = round($deductionInfo['amount'] / 2, 2);
							$thatDeductionAmount = $deductionInfo['amount'] - $thisDeductionAmount;
							$designationArray[$designationId]['deductions'][$index]['that_amount'] = $thatDeductionAmount;
							$designationArray[$designationId]['deductions'][$index]['amount'] = $thisDeductionAmount;
							$deductionAccountNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
								$deductionInfo['payroll_deduction_id'], "control_name = 'account_number'");
							$deductionDocumentNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
								$deductionInfo['payroll_deduction_id'], "control_name = 'document_number'");
							echo "SPL\tGENERAL JOURNAL\t" . $deductionAccountNumber . "\t\t" . $designationRow['secondary_full_name'] .
								"\t\t\t\t\t\t\t" . $deductionDocumentNumber . "\t" . number_format($thatDeductionAmount * -1, 2) .
								"\t" . $designationRow['secondary_class_code'] . "\n";
						}
					}
					if (!empty($thisAmount)) {
						echo "SPL\tGENERAL JOURNAL\t" . $designationRow['gl_account_number'] . "\t\t" . $designationRow['full_name'] .
							"\t\t\t\t\t\t\t" . $this->getPayrollParameter('STAFF_SUPPORT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" .
							number_format($thisAmount * -1, 2) . "\t" . $designationRow['class_code'] . "\n";
					}
					foreach ($designationArray[$designationId]['deductions'] as $index => $deductionInfo) {
						$deductionAccountNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
							$deductionInfo['payroll_deduction_id'], "control_name = 'account_number'");
						$deductionDocumentNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
							$deductionInfo['payroll_deduction_id'], "control_name = 'document_number'");
						echo "SPL\tGENERAL JOURNAL\t" . $deductionAccountNumber . "\t\t" . $designationRow['full_name'] .
							"\t\t\t\t\t\t\t" . $deductionDocumentNumber . "\t" . number_format($deductionInfo['amount'] * -1, 2) .
							"\t" . $designationRow['class_code'] . "\n";
					}
					$designationArray[$designationId]['total_donations'] = 0;
				}

				if (!empty($administrationFeeAmount)) {
					echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('STAFF_SUPPORT_ADMIN_FEE_ACCOUNT_NUMBER') . "\t\t\t\t\t\t\t\t\t" .
						$this->getPayrollParameter('STAFF_SUPPORT_ADMIN_FEE_DOCUMENT_NUMBER') . "\t" .
						number_format($administrationFeeAmount * -1, 2) . "\t" . $this->getPayrollParameter('STAFF_SUPPORT_ADMIN_FEE_CLASS') . "\n";
				}
				echo "ENDTRNS\n";

				$resultSet = executeQuery("select * from payroll_deductions where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$deductionAmount = 0;
					foreach ($designationArray as $designationId => $designationRow) {
						foreach ($designationRow['deductions'] as $deductionInfo) {
							if ($deductionInfo['payroll_deduction_id'] == $row['payroll_deduction_id']) {
								$deductionAmount += ($deductionInfo['amount'] + $deductionInfo['that_amount']);
							}
						}
					}
					if ($deductionAmount > 0) {
						$deductionSummaryAccountNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
							$row['payroll_deduction_id'], "control_name = 'summary_account_number'");
						$deductionDocumentNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
							$deductionInfo['payroll_deduction_id'], "control_name = 'document_number'");
						$deductionSummaryClassCode = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
							$deductionInfo['payroll_deduction_id'], "control_name = 'summary_class_code'");
						$deductionSummaryIndividualAccountNumber = getFieldFromId("control_value", "payroll_deduction_controls", "payroll_deduction_id",
							$deductionInfo['payroll_deduction_id'], "control_name = 'individual_summary_account_number'");

						echo "TRNS\tGENERAL JOURNAL\t" . $deductionSummaryAccountNumber . "\t\t\t\t\t\t\t\t\t" . $deductionDocumentNumber . "\t" .
							number_format($deductionAmount * -1, 2) . "\t" . $deductionSummaryClassCode . "\n";

						foreach ($designationArray as $designationId => $designationRow) {
							foreach ($designationRow['deductions'] as $deductionInfo) {
								if ($deductionInfo['payroll_deduction_id'] == $row['payroll_deduction_id']) {
									if (empty($row['include_in_payout'])) {
										$designationArray[$designationId]['total_deductions'] = $deductionInfo['amount'] + $deductionInfo['that_amount'];
									}
									if ($deductionInfo['amount'] > 0) {
										echo "SPL\tGENERAL JOURNAL\t" . $deductionSummaryIndividualAccountNumber .
											"\t\t" . $designationRow['full_name'] . "\t\t\t\t\t\t\t" . $deductionDocumentNumber . "\t" .
											number_format($deductionInfo['amount'], 2) . "\t" . $designationRow['class_code'] . "\n";
									}
									if ($deductionInfo['that_amount'] > 0) {
										echo "SPL\tGENERAL JOURNAL\t" . $deductionSummaryIndividualAccountNumber .
											"\t\t" . $designationRow['secondary_full_name'] . "\t\t\t\t\t\t\t" . $deductionDocumentNumber . "\t" .
											number_format($deductionInfo['amount'], 2) . "\t" . $designationRow['secondary_class_code'] . "\n";
									}
								}
							}
						}

						echo "ENDTRNS\n";
					}
				}

				# Write Corporate Records
				foreach ($donationArray as $donationId => $donationRow) {
					if (empty($donationRow['donation_fee'])) {
						$donationRow['donation_fee'] = 0;
					}
					if (!empty($designationArray[$donationRow['designation_id']]['designation_type_id']) && $designationTypeArray[$designationArray[$donationRow['designation_id']]['designation_type_id']]['individual_support']) {
						continue;
					}
					$thisAmount = $donationRow['amount'] - $donationRow['donation_fee'];
					$designationArray[$donationRow['designation_id']]['total_donation_fee'] += $donationRow['donation_fee'];
					$designationArray[$donationRow['designation_id']]['total_amount'] += $donationRow['amount'];
					$designationArray[$donationRow['designation_id']]['total_donations'] += $thisAmount;
				}

				$transactionAmount = 0;
				foreach ($designationArray as $designationId => $designationRow) {
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}
					$transactionAmount += $designationRow['total_amount'];
				}

				echo "TRNS\tGENERAL JOURNAL\t" . $this->getPayrollParameter('CORPORATE_GIFTS_SUMMARY_ENTRY_ACCOUNT_NUMBER') .
					"\t\t\t\t\t\t\t\t\t" . $this->getPayrollParameter('CORPORATE_GIFTS_SUMMARY_ENTRY_DOCUMENT_NUMBER') . "\t" .
					number_format($transactionAmount, 2) . "\t" . $this->getPayrollParameter('CORPORATE_GIFTS_SUMMARY_ENTRY_CLASS') . "\n";

				$administrationFeeAmount = 0;
				foreach ($designationArray as $designationId => $designationRow) {
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}
					$administrationFeeAmount += $designationRow['total_donation_fee'];

					$designationArray[$designationId]['reimbursable_expenses'] = $designationRow['reimbursable_expenses'] = $this->getReimbursableExpenses($designationId, $designationRow['total_donations'], $payPeriodId);

					$thisAmount = $designationRow['total_donations'];
					if (!empty($designationRow['secondary_class_code'])) {
						$thatAmount = round($thisAmount / 2, 2);
						if (!empty($thatAmount)) {
							echo "SPL\tGENERAL JOURNAL\t" . $designationRow['gl_account_number'] . "\t\t\t\t\t\t\t\t\t" .
								$this->getPayrollParameter('CORPORATE_GIFTS_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" .
								number_format($thatAmount * -1, 2) . "\t" . $designationRow['secondary_class_code'] . "\n";
							$thisAmount -= $thatAmount;
						}
					}
					if (!empty($thisAmount)) {
						echo "SPL\tGENERAL JOURNAL\t" . $designationRow['gl_account_number'] . "\t\t\t\t\t\t\t\t\t" .
							$this->getPayrollParameter('CORPORATE_GIFTS_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" .
							number_format($thisAmount * -1, 2) . "\t" . $designationRow['class_code'] . "\n";
					}
					$designationArray[$designationId]['total_donations'] = 0;
				}

				if (!empty($administrationFeeAmount)) {
					echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('CORPORATE_GIFTS_ADMIN_FEE_ACCOUNT_NUMBER') .
						"\t\t\t\t\t\t\t\t\t" . $this->getPayrollParameter('CORPORATE_GIFTS_ADMIN_FEE_DOCUMENT_NUMBER') . "\t" .
						number_format($administrationFeeAmount * -1, 2) . "\t" . $this->getPayrollParameter('CORPORATE_GIFTS_ADMIN_FEE_CLASS') . "\n";
				}
				echo "ENDTRNS\n";

				# Write Direct Deposit Records
				foreach ($designationArray as $designationId => $designationRow) {
					$designationArray[$designationId]['total_donations'] = 0;
				}
				foreach ($donationArray as $donationId => $donationRow) {
					if (empty($donationRow['donation_fee'])) {
						$donationRow['donation_fee'] = 0;
					}
					$paymentType = getFieldFromId("payment_type", "designation_types", "designation_type_id", getFieldFromId("designation_type_id", "designations", "designation_id", $donationRow['designation_id']));
					if ($paymentType != "D") {
						continue;
					}
					$designationArray[$donationRow['designation_id']]['total_donations'] += $donationRow['amount'] - $donationRow['donation_fee'];
				}

				$transactionAmount = 0;
				foreach ($designationArray as $designationId => $designationRow) {
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}
					if (empty($designationRow['total_deductions'])) {
						$designationRow['total_deductions'] = 0;
					}
					$transactionAmount += $designationRow['total_donations'] - $designationRow['total_deductions'];
				}

				echo "TRNS\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_SUMMARY_ENTRY_ACCOUNT_NUMBER') . "\t\t\t\t\t\t\t\t\t" .
					$this->getPayrollParameter('DIRECT_DEPOSIT_SUMMARY_ENTRY_DOCUMENT_NUMBER') . "\t" .
					number_format($transactionAmount * -1, 2) . "\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_SUMMARY_ENTRY_CLASS') . "\n";

				foreach ($designationArray as $designationId => $designationRow) {
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}

					$supportAmount1 = $designationRow['total_donations'] - $designationRow['total_deductions'];
					$supportAmount2 = 0;
					$expenseAmount1 = 0;
					if ($designationRow['reimbursable_expenses'] > 0) {
						if ($designationRow['reimbursable_expenses'] > $supportAmount1) {
							$expenseAmount1 = $supportAmount1;
						} else {
							$expenseAmount1 = $designationRow['reimbursable_expenses'];
						}
						$supportAmount1 -= $expenseAmount1;
					}
					$expenseAmount2 = 0;
					if (!empty($designationRow['secondary_class_code'])) {
						$supportAmount2 = round($supportAmount1 / 2, 2);
						$expenseAmount2 = round($expenseAmount1 / 2, 2);
						if (!empty($supportAmount2)) {
							if ($designationTypeArray[$designationRow['designation_type_id']]['individual_support']) {
								echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_STAFF_SUPPORT_ACCOUNT_NUMBER') . "\t\t" .
									($designationTypeArray[$designationRow['designation_type_id']]['individual_support'] ? $designationRow['secondary_full_name'] : "") . "\t\t\t\t\t\t\t" .
									$this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" . number_format($supportAmount2, 2) . "\t" .
									$designationRow['secondary_class_code'] . "\n";
							} else {
								echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_CORPORATE_GIFT_ACCOUNT_NUMBER') . "\t\t" .
									($designationTypeArray[$designationRow['designation_type_id']]['individual_support'] ? $designationRow['secondary_full_name'] : "") . "\t\t\t\t\t\t\t" .
									$this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" . number_format($supportAmount2, 2) . "\t" .
									$designationRow['secondary_class_code'] . "\n";
							}
						}
						if (!empty($expenseAmount2)) {
							echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_EXPENSE_REIMBURSEMENT_ACCOUNT_NUMBER') . "\t\t" .
								($designationTypeArray[$designationRow['designation_type_id']]['individual_support'] ? $designationRow['secondary_full_name'] : "") . "\t\t\t\t\t\t\t" .
								$this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" . number_format($expenseAmount2, 2) . "\t" .
								$designationRow['secondary_class_code'] . "\n";
						}
						if (!empty($expenseAmount2)) {
							$reimbursableExpenses = $designationRow['reimbursable_expenses'];
							$reimbursableExpenses -= $expenseAmount2;
							$designationRow['reimbursable_expenses'] = $reimbursableExpenses;
							$designationArray[$designationId]['reimbursable_expenses'] = $reimbursableExpenses;
						}
						$supportAmount1 -= $supportAmount2;
						$expenseAmount1 -= $expenseAmount2;
					}
					if (!empty($supportAmount1)) {
						if ($designationTypeArray[$designationRow['designation_type_id']]['individual_support']) {
							echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_STAFF_SUPPORT_ACCOUNT_NUMBER') . "\t\t" .
								($designationTypeArray[$designationRow['designation_type_id']]['individual_support'] ? $designationRow['full_name'] : "") . "\t\t\t\t\t\t\t" .
								$this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" . number_format($supportAmount1, 2) . "\t" .
								$designationRow['class_code'] . "\n";
						} else {
							echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_CORPORATE_GIFT_ACCOUNT_NUMBER') . "\t\t" .
								($designationTypeArray[$designationRow['designation_type_id']]['individual_support'] ? $designationRow['full_name'] : "") . "\t\t\t\t\t\t\t" .
								$this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" . number_format($supportAmount1, 2) . "\t" .
								$designationRow['class_code'] . "\n";
						}
					}
					if (!empty($expenseAmount1)) {
						echo "SPL\tGENERAL JOURNAL\t" . $this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_EXPENSE_REIMBURSEMENT_ACCOUNT_NUMBER') . "\t\t" .
							($designationTypeArray[$designationRow['designation_type_id']]['individual_support'] ? $designationRow['full_name'] : "") . "\t\t\t\t\t\t\t" .
							$this->getPayrollParameter('DIRECT_DEPOSIT_INDIVIDUAL_ENTRY_DOCUMENT_NUMBER') . "\t" . number_format($expenseAmount1, 2) . "\t" .
							$designationRow['class_code'] . "\n";
					}
					if (!empty($expenseAmount1)) {
						$reimbursableExpenses = $designationRow['reimbursable_expenses'];
						$reimbursableExpenses -= $expenseAmount1;
						$designationRow['reimbursable_expenses'] = $reimbursableExpenses;
						$designationArray[$designationId]['reimbursable_expenses'] = $reimbursableExpenses;
					}
				}
				echo "ENDTRNS\n";
				exit;
		}
	}

	function getPayrollParameter($payrollParameterCode) {
		return getFieldFromId("parameter_value", "payroll_parameter_values", "payroll_parameter_id",
			getFieldFromId("payroll_parameter_id", "payroll_parameters", "payroll_parameter_code", strtoupper($payrollParameterCode)),
			"client_id = ?", $GLOBALS['gClientId']);
	}

	function getReimbursableExpenses($designationId, $maxAmount, $payPeriodId) {
		$reimbursableAmount = 0;
		$minimumSalary = 0;
		$resultSet = executeQuery("select minimum_salary from payroll_groups where payroll_group_id = (select payroll_group_id from designations where designation_id = ?)", $designationId);
		if ($row = getNextRow($resultSet)) {
			$minimumSalary = $row['minimum_salary'];
		}
		$thisYear = date("Y");
		$resultSet = executeQuery("select year(date_paid_out) from pay_periods where pay_period_id = ? and date_paid_out is not null", $payPeriodId);
		if ($row = getNextRow($resultSet)) {
			$thisYear = $row['year(date_paid_out)'];
		}
		$startDate = $thisYear . "-01-01";
		$endDate = $thisYear . "-12-31";
		$resultSet = executeQuery("select date_created from designations where designation_id = ? and date_created between ? and ?",
			$designationId, $startDate, $endDate);
		if ($row = getNextRow($resultSet)) {
			$minimumSalary = round(((365 - date("z", strtotime($row['date_created']))) / 365) * $minimumSalary, 2);
		}
		$currentSalary = 0;
		$resultSet = executeQuery("select * from donations where designation_id = ? and pay_period_id in (select pay_period_id from pay_periods where date_paid_out between ? and ?)",
			$designationId, $startDate, $endDate);
		while ($row = getNextRow($resultSet)) {
			$currentSalary += $row['amount'] - $row['donation_fee'];
		}
		$resultSet = executeQuery("select * from expense_uses where pay_period_id in (select pay_period_id from pay_periods where client_id = ? and " .
			"date_paid_out between ? and ?) and expense_id in (select expense_id from expenses where designation_id = ?)",
			$GLOBALS['gClientId'], $startDate, $endDate, $designationId);
		while ($row = getNextRow($resultSet)) {
			$currentSalary -= $row['amount'];
		}

		# remove the amount in this payroll
		$currentSalary = round(max($currentSalary - $maxAmount, 0), 2);
		$salaryLeft = max($minimumSalary - $currentSalary, 0);
		$usableMaxAmount = max($maxAmount - max($salaryLeft, 0), 0);

		$resultSet = executeQuery("select expense_id,expiration_date,(expiration_date is null) null_as_last,amount,(select coalesce(sum(amount),0) from " .
			"expense_uses where expense_id = expenses.expense_id) amount_used from expenses where designation_id = ? and (expiration_date " .
			"is null or expiration_date >= now()) and amount > (select coalesce(sum(amount),0) from expense_uses where expense_uses.expense_id = expenses.expense_id) order by " .
			"null_as_last,expiration_date,expense_id", $designationId);
		while ($row = getNextRow($resultSet)) {
			$canUseAmount = round($usableMaxAmount - $reimbursableAmount, 2);
			if ($canUseAmount <= 0) {
				break;
			}
			$useAmount = 0;
			$amountRemaining = round($row['amount'] - $row['amount_used'], 2);
			if ($amountRemaining < $canUseAmount) {
				$useAmount = $amountRemaining;
			} else {
				$useAmount = $canUseAmount;
			}
			$reimbursableAmount = round($reimbursableAmount + $useAmount, 2);
			executeQuery("insert into expense_uses (expense_id,date_used,amount,pay_period_id) values " .
				"(?,now(),?,?)", $row['expense_id'], $useAmount, $payPeriodId);
		}
		return $reimbursableAmount;
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
		}
	}
}

$pageObject = new ThisPage("pay_periods");
$pageObject->displayPage();
