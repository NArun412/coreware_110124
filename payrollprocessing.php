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

$GLOBALS['gPageCode'] = "PAYROLLPROCESSING";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {

			case "send_emails":
				if (!array_key_exists("designation_list", $_POST)) {
					$designationList = "";
					$emailList = "";
					$totalCount = 0;
					$resultSet = executeQuery("select distinct designation_id from donations where client_id = ? and " .
						"donation_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?) order by designation_id",
						$GLOBALS['gClientId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
					while ($row = getNextRow($resultSet)) {
						$emailAddresses = array();
						$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $row['designation_id']);
						while ($emailRow = getNextRow($emailSet)) {
							$emailAddresses[] = $emailRow['email_address'];
						}
						if (empty($emailAddresses)) {
							continue;
						}
						if (!empty($designationList)) {
							$designationList .= ",";
						}
						$designationList .= $row['designation_id'];
						$emailList .= implode(",", $emailAddresses) . "\n";
						$totalCount++;
					}
					ob_start();
					?>
                    <input type="hidden" name="total_count" id="total_count" value="<?= $totalCount ?>">
                    <input type="hidden" name="processed_count" id="processed_count" value="0">
                    <input type="hidden" name="designation_list" id="designation_list" value="<?= $designationList ?>">
                    <textarea name="email_list" id="email_list"><?= $emailList ?></textarea>
                    </p>
                    <p>
                        <button id="payroll_report">Go To Payroll Report</button>
                    </p>
					<?php
					$returnArray['next_step'] = ob_get_clean();
					$returnArray['total_count'] = $totalCount;
					$returnArray['processed_count'] = 0;
					ajaxResponse($returnArray);
					break;
				}
				$totalCount = $_POST['total_count'];
				$processedCount = $_POST['processed_count'];
				$ccAddress = getNotificationEmails("COPY_PAYROLL");
				$payPeriodId = getFieldFromId("pay_period_id", "pay_periods", "pay_period_id", $_POST['pay_period_id']);
				if (empty($payPeriodId)) {
					$returnArray['process_errors'] = "Invalid Pay Period";
					ajaxResponse($returnArray);
					break;
				}
				$datePaidOut = date("m/d/Y", strtotime(getFieldFromId("date_paid_out", "pay_periods", "pay_period_id", $payPeriodId)));

				$designationArray = explode(",", $_POST['designation_list']);
				$includeBackouts = getPreference("INCLUDE_BACKOUTS_GIVING_REPORT");
				$includeNotes = getPreference("INCLUDE_NOTES_GIVING_REPORT");
				for ($x = 0; $x < 5; $x++) {
					if (count($designationArray) == 0) {
						$returnArray['designation_list'] = "";
						$returnArray['done_message'] = $processedCount . " reports emailed" . (empty($ccAddress) ? "" : ", copies sent to " . implode(",", $ccAddress));
						$returnArray['total_count'] = $totalCount;
						$returnArray['processed_count'] = $processedCount;
						ajaxResponse($returnArray);
						break;
					}

					$thisDesignationId = array_shift($designationArray);
					$emailAddresses = array();
					$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $thisDesignationId);
					while ($emailRow = getNextRow($emailSet)) {
						$emailAddresses[] = $emailRow['email_address'];
					}
					if (!empty($emailAddresses)) {
						$resultSet = executeQuery("select donation_id from donations where designation_id = ? and " .
							"client_id = ? and " . ($includeBackouts ? "" : "associated_donation_id is null and ") . "donation_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?) order by donation_id",
							$thisDesignationId, $GLOBALS['gClientId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
						$donationArray = array();
						while ($row = getNextRow($resultSet)) {
							$donationArray[] = $row['donation_id'];
						}
						$givingReport = "<h1>Giving Report</h1>\n";
						if (!empty($_POST['email_introduction'])) {
							$givingReport .= makeHtml($_POST['email_introduction']);
						}
						$designationCode = getFieldFromId("designation_code", "designations", "designation_id", $thisDesignationId);
						$description = getFieldFromId("description", "designations", "designation_id", $thisDesignationId);
						$givingReport .= $designationCode . " - " . $description . "<br/>\n";
						$givingReport .= date("M j, Y") . "<br/>\n";
						$givingReport .= "----------------------------------<br/>\n";
						$reportTotal = 0;
						$totalFees = 0;
						foreach ($donationArray as $donationId) {
							$resultSet1 = executeQuery("select * from donations where client_id = ? and donation_id = ?", $GLOBALS['gClientId'], $donationId);
							$donationRow = getNextRow($resultSet1);
							if (empty($donationRow['donation_fee'])) {
								$donationRow['donation_fee'] = 0;
							}
							$resultSet1 = executeQuery("select * from contacts where contact_id = ?", $donationRow['contact_id']);
							$contactRow = getNextRow($resultSet1);
							if ($donationRow['anonymous_gift']) {
								$givingReport .= "Anonymous Gift: $" . number_format($donationRow['amount'], 2) . "<br/>\n";
								if (!empty($donationRow['project_name'])) {
									$givingReport .= "Given for: " . $donationRow['project_name'] . "<br>\n";
								}
								$givingReport .= "*** Given on " . date("F j, Y", strtotime($donationRow['donation_date'])) . "<br/>\n";
								$givingReport .= "Given by " . getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']) .
									", Administration fee is $" . number_format($donationRow['donation_fee'], 2) . ", Net gift amount is $" .
									number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) . "<br/>\n";
							} else {
								$lines = array();
								$lines[] = $contactRow['contact_id'];
								$lines[] = $contactRow['first_name'] . " " . $contactRow['last_name'];
								$lines[] = $contactRow['business_name'];
								$lines[] = $contactRow['address_1'];
								$lines[] = $contactRow['address_2'];
								$lines[] = $contactRow['city'] . (empty($contactRow['state']) ? "" : ", " . $contactRow['state']) . " " . $contactRow['postal_code'];
								if ($contactRow['country_id'] != 1000) {
									$lines[] = getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']);
								}
								$lines[] = $contactRow['email_address'];
								$phoneSet = executeQuery("select * from phone_numbers where contact_id = ?", $contactRow['contact_id']);
								while ($phoneRow = getNextRow($phoneSet)) {
									$lines[] = $phoneRow['phone_number'] . " " . $phoneRow['description'];
								}
								foreach ($lines as $line) {
									$line = trim($line);
									if (!empty($line)) {
										$givingReport .= $line . "<br/>\n";
									}
								}
								if (!empty($donationRow['project_name'])) {
									$givingReport .= "Given for: " . $donationRow['project_name'] . "<br>\n";
								}
								$givingReport .= "*** Given on " . date("F j, Y", strtotime($donationRow['donation_date'])) .
									", Receipt #" . $donationId . ", $" . number_format($donationRow['amount'], 2) . " USD<br/>\n";
								$givingReport .= "Given by " . getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']) .
									", Administration fee is $" . number_format($donationRow['donation_fee'], 2) . ", Net gift amount is $" .
									number_format($donationRow['amount'] - $donationRow['donation_fee'], 2) . "<br/>\n";
							}
							if ($includeNotes && !empty($donationRow['notes'])) {
								$givingReport .= "Notes: " . htmlText($donationRow['notes']) . "<br/>\n";
							}
							if (!empty($donationRow['recurring_donation_id'])) {
								$givingReport .= "Recurring: " . getFieldFromId("description", "recurring_donation_types", "recurring_donation_type_id", getFieldFromId("recurring_donation_type_id", "recurring_donations", "recurring_donation_id", $donationRow['recurring_donation_id'])) . "<br>\n";
							}
							$reportTotal += $donationRow['amount'];
							$totalFees += $donationRow['donation_fee'];
							$givingReport .= "----------------------------------<br/>\n";
						}
						$givingReport .= "Total Gifts: $" . number_format($reportTotal, 2) . ", Total Fees: $" .
							number_format($totalFees, 2) . ", Net Total: $" . number_format($reportTotal - $totalFees, 2) . "<br/>\n";

						$resultSet = executeQuery("select *,(select description from fund_accounts where fund_account_id = fund_account_details.fund_account_id) fund_description " .
							"from fund_account_details where designation_id = ? " .
							"and pay_period_id = ? order by fund_description", $thisDesignationId, $payPeriodId);
						if ($resultSet['row_count'] > 0) {
							$givingReport .= "<br/>\nFund Account Deductions<br/>\n";
						}
						$totalFund = 0;
						while ($row = getNextRow($resultSet)) {
							$givingReport .= $row['fund_description'] . " - $" . number_format($row['amount'], 2) . "<br/>\n";
							$totalFund += $row['amount'];
						}
						if ($totalFund > 0) {
							$givingReport .= "Donations: $" . number_format($reportTotal - $totalFees, 2) . ", Total Funds: $" . number_format($totalFund, 2) .
								", Net after funds: $" . number_format($reportTotal - $totalFees - $totalFund, 2) . "<br/>\n";
						}

						$feeMessage = Donations::getFeeMessage($thisDesignationId);
						if (!empty($feeMessage)) {
							$givingReport .= "<br/>\nFee Schedule:<br/>\n" . $feeMessage;
						}

						$emailMessage = "";
						$errorMessage = sendEmail(array("email_credential_code" => "designation_notification", "email_credential_id" => $_GET['email_credential_id'], "subject" => "Giving Report for " . $designationCode . " for " . $datePaidOut, "body" => $givingReport, "email_addresses" => $emailAddresses, "cc_address" => $ccAddress));
						if ($errorMessage !== true) {
							$emailMessage .= "Email to " . implode(",", $emailAddresses) . " for designation code " . $designationCode . " not sent: " . $errorMessage;
						} else {
							$emailMessage .= "Email sent to " . implode(",", $emailAddresses) . " for designation code " . $designationCode . " (" . $description . ")";
						}
						if ($processedCount == 0) {
							$returnArray['email_list'] = $emailMessage . "\n";
						} else {
							$returnArray['email_list_addition'] = $emailMessage . "\n";
						}

						$resultSet = executeQuery("select * from pay_periods where pay_period_id = ?", $payPeriodId);
						if ($row = getNextRow($resultSet)) {
							$logEntry = $row['log_entry'] . "\n" . ($processedCount == 0 ? "\n" : "") . $emailMessage;
							$resultSet = executeQuery("update pay_periods set log_entry = ? where pay_period_id = ?", $logEntry, $payPeriodId);
						}
						$processedCount++;
					}
				}

				$designationList = "";
				foreach ($designationArray as $designationId) {
					if (!empty($designationList)) {
						$designationList .= ",";
					}
					$designationList .= $designationId;
				}
				$returnArray['designation_list'] = $designationList;
				if (empty($designationList)) {
					$returnArray['done_message'] = $processedCount . " reports emailed" . (empty($ccAddress) ? "" : ", copies sent to " . implode(",", $ccAddress));
					$resultSet = executeQuery("select * from pay_periods where pay_period_id = ?", $payPeriodId);
					if ($row = getNextRow($resultSet)) {
						$logEntry = $row['log_entry'] . "\n\n" . $processedCount . " reports emailed" . (empty($ccAddress) ? "" : ", copies sent to " . implode(",", $ccAddress));
						executeQuery("update pay_periods set log_entry = ? where pay_period_id = ?", $logEntry, $payPeriodId);
					}
				}
				$returnArray['processed_count'] = $processedCount;
				ajaxResponse($returnArray);
				break;

			case "downloads_done":
				ob_start();
				?>
                <div class="basic-form-line">
                    <label>Email Sending Account</label>
                    <select id="email_credential_id" name="email_credential_id">
                        <option value="">[Default]</option>
						<?php
						$resultSet = executeQuery("select * from email_credentials where client_id = ? order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['email_credential_id'] ?>"<?= ($row['email_credential_code'] == "DESIGNATION_NOTIFICATION" ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line">
                    <label>Email Introduction</label>
                    <span class="help-label">This will be included at the top of the email.</span>
                    <textarea id="email_introduction" name="email_introduction"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <p id="send_emails_message" class="highlighted-text color-green">
                    <button id="send_emails">Send Giving Emails</button>
                </p>
				<?php
				$returnArray['next_step'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;

			case "download_payroll_file":
				if (function_exists("customPayrollFileDownload")) {
					customPayrollFileDownload();
				}
				$fileType = getPreference("PAYROLL_FILE_TYPE");
				if (empty($fileType) || !array_key_exists($fileType, $GLOBALS['gMimeTypes'])) {
					$fileType = "text/plain";
				}
				header("Content-Type: " . $fileType);
				header("Content-Disposition: attachment; filename=\"directdeposit." . date("Ymd") . "." . $GLOBALS['gMimeTypes'][$fileType] . "\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');

				$payPeriodId = getFieldFromId("pay_period_id", "pay_periods", "pay_period_id", $_GET['pay_period_id']);
				if (!empty($payPeriodId)) {
					$resultSet = executeQuery("select * from pay_periods where pay_period_id = ?", $payPeriodId);
					if ($row = getNextRow($resultSet)) {
						$resultSet = executeQuery("update pay_periods set log_entry = ? where pay_period_id = ?",
							$row['log_entry'] . "\n\nPayroll file downloaded", $payPeriodId);
					}
				} else {
					echo "Invalid Pay Period";
					exit;
				}

				$designationTypeArray = array();
				$resultSet = executeQuery("select * from designation_types where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$designationTypeArray[$row['designation_type_id']] = $row;
				}

				$designationIdArray = array();
				$donationArray = array();
				$resultSet = executeQuery("select * from donations where client_id = ? and donation_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?) order by donation_id",
					$GLOBALS['gClientId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$donationArray[$row['donation_id']] = $row;
					if (!in_array($row['designation_id'], $designationIdArray)) {
						$designationIdArray[] = $row['designation_id'];
					}
				}
				$designationList = implode(",", $designationIdArray);
				$designationArray = array();
				$resultSet = executeQuery("select * from designations where client_id = ? and designation_id in (" .
					$designationList . ") order by designation_code", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['total_donations'] = 0;
					$row['total_donation_fee'] = 0;
					$row['total_amount'] = 0;
					$row['deductions'] = array();
					$designationArray[$row['designation_id']] = $row;
				}
				$fileLine = getPreference("PAYROLL_FILE_FORMAT");
				if (empty($fileLine)) {
					$fileLine = "%designation_code%,%account_number%,%account_type%,%routing_number%,%amount%,%full_name%";
				}

				# Write Direct Deposit Records
				$transactionAmount = 0;
				foreach ($donationArray as $donationId => $donationRow) {
					if (empty($donationRow['donation_fee'])) {
						$donationRow['donation_fee'] = 0;
					}
					$paymentType = getFieldFromId("payment_type", "designation_types", "designation_type_id", $designationArray[$donationRow['designation_id']]['designation_type_id']);
					if ($paymentType != "D") {
						continue;
					}
					$thisAmount = $donationRow['amount'] - $donationRow['donation_fee'];
					$transactionAmount += $thisAmount;
					$designationArray[$donationRow['designation_id']]['total_donations'] += $thisAmount;
				}

				usort($designationArray, array($this, "designationSort"));

				foreach ($designationArray as $designationRow) {
					$designationId = $designationRow['designation_id'];
					if (empty($designationRow['total_donations'])) {
						continue;
					}
					$requestedSalary = CustomField::getCustomFieldData($designationId, "REQUESTED_SALARY", "DESIGNATIONS");
					if (!empty($requestedSalary) && $requestedSalary > 0) {
						$lastYear = date("Y") - 1;
						$lastYearTotal = 0;
						$lastYearCount = 0;
						$resultSet = executeQuery("select count(*),sum(amount) from paycheck_records where designation_id = ? and " .
							"pay_period_id in (select pay_period_id from pay_periods where date_paid_out between ? and ?)", $designationId, $lastYear . "-01-01", $lastYear . "-12-31");
						if ($row = getNextRow($resultSet)) {
							$lastYearCount = $row['count(*)'];
							$lastYearTotal = $row['sum(amount)'];
						}
						$ytdTotal = 0;
						$ytdCount = 0;
						$resultSet = executeQuery("select count(*),sum(amount) from paycheck_records where designation_id = ? and " .
							"pay_period_id in (select pay_period_id from pay_periods where year(date_paid_out) = year(current_date))", $designationId);
						if ($row = getNextRow($resultSet)) {
							$ytdCount = $row['count(*)'];
							$ytdTotal = $row['sum(amount)'];
						}
						if ($lastYearCount > 0 and $ytdCount = 0) {
							$totalLastYearDonations = 0;
							$resultSet = executeQuery("select sum(amount - coalesce(donation_fee,0)) as total_donations from donations where designation_id = ? and donation_date between ? and ? and associated_donation_id is null",
								$designationId, $lastYear . "-01-01", $lastYear . "-12-31");
							if ($row = getNextRow($resultSet)) {
								$totalLastYearDonations = $row['total_donations'];
							}
							$remainder = $totalLastYearDonations - $lastYearTotal;
							if ($remainder > 0) {
								$designationRow['total_donations'] += $remainder;
							}
						}
						$newTotalSalary = $ytdTotal + $designationRow['total_donations'];
						if ($newTotalSalary > $requestedSalary) {
							$designationRow['total_donations'] = $requestedSalary - $ytdTotal;
							$newTotalSalary = $requestedSalary;
						}
						if ($designationRow['total_donations'] <= 0) {
							continue;
						}
						if ($ytdTotal > ($requestedSalary * .9)) {
							$emailAddresses = array();
							$resultSet = executeQuery("select * from designation_email_addresses where designation_id = ?", $designationId);
							while ($row = getNextRow($resultSet)) {
								$emailAddresses[] = $row['email_address'];
							}
							$body = "Designation '" . $designationRow['description'] . " (" . $designationRow['designation_code'] . ") has exceeded 90% of requested salary. " .
								"Requested salary is $" . number_format($requestedSalary, 2, ".", ",") . " and has received $" . number_format($newTotalSalary, 2, ".", ",");
							sendEmail(array("body" => $body, "subject" => "Designation Salary", "email_addresses" => $emailAddresses, "notification_code" => "DESIGNATION_SALARY"));
						}
						$insertSet = executeQuery("insert into paycheck_records (pay_period_id,designation_id,amount) values (?,?,?)", $payPeriodId, $designationId, $designationRow['total_donations']);
					}

					$designationRow['account_type_number'] = ($designationRow['account_type'] == "S" ? "32" : "22");
					$designationRow['reimbursable_expenses'] = 0;
					$resultSet = executeQuery("select coalesce(sum(amount),0) from expense_uses where pay_period_id = ? and " .
						"expense_id in (select expense_id from expenses where designation_id = ?)", $payPeriodId, $designationId);
					if ($row = getNextRow($resultSet)) {
						$designationRow['reimbursable_expenses'] = $row['coalesce(sum(amount),0)'];
					}

					$deductionAmount = 0;
					if (!empty($designationRow['designation_type_id']) && $designationTypeArray[$designationRow['designation_type_id']]['individual_support']) {
						$saveTotalDonation = $designationRow['total_donations'] - $designationRow['reimbursable_expenses'];
						if ($saveTotalDonation > 0) {
							$resultSet = executeQuery("select * from payroll_deductions where client_id = ? and inactive = 0 and include_in_payout = 0 order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								if (!empty($row['designation_group_id'])) {
									$designationGroupLinkId = getFieldFromId("designation_group_link_id", "designation_group_links", "designation_id",
										$designationId, "designation_group_id = ?", $row['designation_group_id']);
									if (empty($designationGroupLinkId)) {
										continue;
									}
								}
								$designationDeductionRow = getRowFromId("designation_deductions", "designation_id", $designationId, "(one_time = 0 or pay_period_id = ?) && inactive = 0 and payroll_deduction_id = ?", $payPeriodId, $row['payroll_deduction_id']);
								if (!empty($designationDeductionRow)) {
									$row['amount'] = $designationDeductionRow['amount'];
									$row['percentage'] = $designationDeductionRow['percentage'];
									executeQuery("update designation_deductions set pay_period_id = ? where designation_deduction_id = ?",$payPeriodId, $designationDeductionRow['designation_deduction_id']);
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
								}
							}
						}
					}

					$totalPayout = $designationRow['total_donations'] - $deductionAmount;

					$supportAmount1 = $totalPayout;
					$supportAmount2 = 0;
					if (!empty($designationRow['secondary_class_code'])) {
						$supportAmount2 = round($supportAmount1 / 2, 2);
						$supportAmount1 -= $supportAmount2;
					}
					if ($supportAmount1 > 0) {
						$designationRow['amount'] = number_format($supportAmount1, 2, ".", "");
						$fullName = (empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']);
						$designationRow['full_name'] = $fullName;
						$designationRow['full_name_22'] = mb_substr($fullName, 0, 22);
						$thisFileLine = $fileLine;
						foreach ($designationRow as $fieldName => $fieldData) {
							$thisFileLine = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? str_replace(",", " ", $fieldData) : ""), $thisFileLine);
						}
						echo $thisFileLine . "\n";
					}
					if ($supportAmount2 > 0) {
						$designationRow['amount'] = number_format($supportAmount2, 2, ".", "");
						$fullName = (empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']);
						$designationRow['full_name'] = $fullName;
						$designationRow['full_name_22'] = mb_substr($fullName, 0, 22);
						$designationRow['designation_code'] .= "A";
						$thisFileLine = $fileLine;
						foreach ($designationRow as $fieldName => $fieldData) {
							$thisFileLine = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? str_replace(",", " ", $fieldData) : ""), $thisFileLine);
						}
						echo $thisFileLine . "\n";
					}
				}
				exit;

			case "select_donations":
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$resultSet = executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) " .
					"select " . $GLOBALS['gUserId'] . "," . $GLOBALS['gPageId'] . ",donation_id from donations where " .
					"client_id = ? and pay_period_id = ?", $GLOBALS['gClientId'], $_GET['pay_period_id']);
				$returnArray['row_count'] = $resultSet['affected_rows'];
				ajaxResponse($returnArray);
				break;

			case "download_checks_file":
				header("Content-Type: text/iif");
				header("Content-Disposition: attachment; filename=\"checks." . date("Ymd") . ".IIF\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');

				$payPeriodId = getFieldFromId("pay_period_id", "pay_periods", "pay_period_id", $_GET['pay_period_id']);
				if (!empty($payPeriodId)) {
					$resultSet = executeQuery("select * from pay_periods where pay_period_id = ?", $payPeriodId);
					if ($row = getNextRow($resultSet)) {
						$resultSet = executeQuery("update pay_periods set log_entry = ? where pay_period_id = ?",
							$row['log_entry'] . "\n\nChecks File downloaded", $payPeriodId);
					}
				} else {
					echo "Invalid Pay Period";
					exit;
				}

				$designationIdArray = array();
				$donationArray = array();
				$resultSet = executeQuery("select * from donations where client_id = ? and donation_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?) order by donation_id",
					$GLOBALS['gClientId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
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
					$addressText = getContentLines($row['address_text']);
					$row['address_1'] = trim($addressText[0]);
					$row['address_2'] = trim($addressText[1]);
					$row['address_3'] = trim($addressText[2]);
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

				$this->iDatabase->startTransaction();

				echo "!TRNS\tTRNSTYPE\tACCNT\tTOPRINT\tNAME\tADDR1\tADDR2\tADDR3\tADDR4\tADDR5\tMEMO\tDOCNUM\tAMOUNT\tCLASS\n";
				echo "!SPL\tTRNSTYPE\tACCNT\tTOPRINT\tNAME\tADDR1\tADDR2\tADDR3\tADDR4\tADDR5\tMEMO\tDOCNUM\tAMOUNT\tCLASS\n";
				echo "!ENDTRNS\n";

				# Write Check Records
				foreach ($donationArray as $donationId => $donationRow) {
					if (empty($donationRow['donation_fee'])) {
						$donationRow['donation_fee'] = 0;
					}
					$paymentType = getFieldFromId("payment_type", "designation_types", "designation_type_id", getFieldFromId("designation_type_id", "designations", "designation_id", $donationRow['designation_id']));
					if ($paymentType != "C") {
						continue;
					}
					$thisAmount = $donationRow['amount'] - $donationRow['donation_fee'];
					$designationArray[$donationRow['designation_id']]['total_donations'] += $thisAmount;
				}

				foreach ($designationArray as $designationId => $designationRow) {
					if ($designationRow['total_donations'] <= 0) {
						continue;
					}
					$resultSet = executeQuery("select coalesce(sum(amount),0) from expense_uses where pay_period_id = ? and " .
						"expense_id in (select expense_id from expenses where designation_id = ?)", $payPeriodId, $designationId);
					if ($row = getNextRow($resultSet)) {
						$designationArray[$designationId]['reimbursable_expenses'] = $designationRow['reimbursable_expenses'] = $row['coalesce(sum(amount),0)'];
					}
					$deductionAmount = 0;
					if (!empty($designationRow['designation_type_id']) && $designationTypeArray[$designationRow['designation_type_id']]['individual_support']) {
						$saveTotalDonation = $designationRow['total_donations'] - $designationRow['reimbursable_expenses'];
						if ($saveTotalDonation > 0) {
							$resultSet = executeQuery("select * from payroll_deductions where client_id = ? and inactive = 0 and include_in_payout = 0 order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								if (!empty($row['designation_group_id'])) {
									$designationGroupLinkId = getFieldFromId("designation_group_link_id", "designation_group_links", "designation_id",
										$designationId, "designation_group_id = ?", $row['designation_group_id']);
									if (empty($designationGroupLinkId)) {
										continue;
									}
								}
								$designationDeductionRow = getRowFromId("designation_deductions", "designation_id", $designationId, "(one_time = 0 or pay_period_id = ?) && inactive = 0 and payroll_deduction_id = ?", $payPeriodId, $row['payroll_deduction_id']);
								if (!empty($designationDeductionRow)) {
									$row['amount'] = $designationDeductionRow['amount'];
									$row['percentage'] = $designationDeductionRow['percentage'];
									executeQuery("update designation_deductions set pay_period_id = ? where designation_deduction_id = ?",$payPeriodId, $designationDeductionRow['designation_deduction_id']);
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
								}
							}
						}
					}

					$supportAmount1 = $designationRow['total_donations'] - $deductionAmount;
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

					$docnum = CustomField::getCustomFieldData($designationRow['designation_id'], "check_docnum", "DESIGNATIONS");
					$dontPrintCheck = CustomField::getCustomFieldData($designationRow['designation_id'], "dont_print_check", "DESIGNATIONS");
					if (!empty($designationRow['secondary_class_code'])) {
						$supportAmount2 = round($supportAmount1 / 2, 2);
						$expenseAmount2 = round($expenseAmount1 / 2, 2);
						if (($supportAmount2 + $expenseAmount2) != 0) {
							echo "TRNS\tCHECK\t" . $this->getPayrollParameter('CHECK_REGISTER_SUMMARY_ENTRY_ACCOUNT_NUMBER') . "\t" . (empty($dontPrintCheck) ? "Y" : "") . "\t" .
								(empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']) . "\t" .
								(empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']) . "\t" . $designationRow['address_1'] .
								"\t" . $designationRow['address_2'] . "\t" . $designationRow['address_3'] . "\t\t" . $designationRow['designation_code'] . " - " . $designationRow['description'] . "\t" . $docnum . "\t" .
								number_format(($supportAmount2 + $expenseAmount2) * -1, 2) . "\t" . $this->getPayrollParameter('CHECK_REGISTER_SUMMARY_ENTRY_CLASS') . "\n";
						}
						if (!empty($supportAmount2)) {
							echo "SPL\tCHECK\t" . $this->getPayrollParameter('CHECK_REGISTER_SUPPORT_ACCOUNT_NUMBER') . "\t" . (empty($dontPrintCheck) ? "Y" : "") . "\t" .
								(empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']) . "\t" .
								(empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']) . "\t" . $designationRow['address_1'] .
								"\t" . $designationRow['address_2'] . "\t" . $designationRow['address_3'] . "\t\t" . $designationRow['designation_code'] . " - " . $designationRow['description'] . "\t" . $docnum . "\t" .
								number_format($supportAmount2, 2) . "\t" . $designationRow['secondary_class_code'] . "\n";
						}
						if (!empty($expenseAmount2)) {
							echo "SPL\tCHECK\t" . $this->getPayrollParameter('CHECK_REGISTER_EXPENSE_REIMBURSEMENT_ACCOUNT_NUMBER') . "\t" . (empty($dontPrintCheck) ? "Y" : "") . "\t" .
								(empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']) . "\t" .
								(empty($designationRow['secondary_full_name']) ? $designationRow['description'] : $designationRow['secondary_full_name']) . "\t" . $designationRow['address_1'] .
								"\t" . $designationRow['address_2'] . "\t" . $designationRow['address_3'] . "\t\t" . $designationRow['designation_code'] . " - " . $designationRow['description'] . "\t" . $docnum . "\t" .
								number_format($expenseAmount2, 2) . "\t" . $designationRow['secondary_class_code'] . "\n";
						}
						echo("ENDTRNS\n");
						if (!empty($expenseAmount2)) {
							$reimbursableExpenses = $designationRow['reimbursable_expenses'];
							$reimbursableExpenses -= $expenseAmount2;
							$designationRow['reimbursable_expenses'] = $reimbursableExpenses;
							$designationArray[$designationId]['reimbursable_expenses'] = $reimbursableExpenses;
						}
						$supportAmount1 -= $supportAmount2;
						$expenseAmount1 -= $expenseAmount2;
					}
					if (($supportAmount1 + $expenseAmount1) != 0) {
						echo "TRNS\tCHECK\t" . $this->getPayrollParameter('CHECK_REGISTER_SUMMARY_ENTRY_ACCOUNT_NUMBER') . "\t" . (empty($dontPrintCheck) ? "Y" : "") . "\t" .
							(empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']) . "\t" .
							(empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']) . "\t" . $designationRow['address_1'] .
							"\t" . $designationRow['address_2'] . "\t" . $designationRow['address_3'] . "\t\t" . $designationRow['designation_code'] . " - " . $designationRow['description'] . "\t" . $docnum . "\t" .
							number_format(($supportAmount1 + $expenseAmount1) * -1, 2) . "\t" . $this->getPayrollParameter('CHECK_REGISTER_SUMMARY_ENTRY_CLASS') . "\n";
					}
					if (!empty($supportAmount1)) {
						echo "SPL\tCHECK\t" . $this->getPayrollParameter('CHECK_REGISTER_SUPPORT_ACCOUNT_NUMBER') . "\t" . (empty($dontPrintCheck) ? "Y" : "") . "\t" .
							(empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']) . "\t" .
							(empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']) . "\t" . $designationRow['address_1'] .
							"\t" . $designationRow['address_2'] . "\t" . $designationRow['address_3'] . "\t\t" . $designationRow['designation_code'] . " - " . $designationRow['description'] . "\t" . $docnum . "\t" .
							number_format($supportAmount1, 2) . "\t" . $designationRow['class_code'] . "\n";
					}
					if (!empty($expenseAmount1)) {
						echo "SPL\tCHECK\t" . $this->getPayrollParameter('CHECK_REGISTER_EXPENSE_REIMBURSEMENT_ACCOUNT_NUMBER') . "\t" . (empty($dontPrintCheck) ? "Y" : "") . "\t" .
							(empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']) . "\t" .
							(empty($designationRow['full_name']) ? $designationRow['description'] : $designationRow['full_name']) . "\t" . $designationRow['address_1'] .
							"\t" . $designationRow['address_2'] . "\t" . $designationRow['address_3'] . "\t\t" . $designationRow['designation_code'] . " - " . $designationRow['description'] . "\t" . $docnum . "\t" .
							number_format($expenseAmount1, 2) . "\t" . $designationRow['class_code'] . "\n";
					}
					echo("ENDTRNS\n");
					if (!empty($expenseAmount1)) {
						$reimbursableExpenses = $designationRow['reimbursable_expenses'];
						$reimbursableExpenses -= $expenseAmount1;
						$designationRow['reimbursable_expenses'] = $reimbursableExpenses;
						$designationArray[$designationId]['reimbursable_expenses'] = $reimbursableExpenses;
					}
				}
				$this->iDatabase->commitTransaction();
				exit;

			case "download_gl_file":
				header("Content-Type: text/iif");
				header("Content-Disposition: attachment; filename=\"gl." . date("Ymd") . ".IIF\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');

				$payPeriodId = getFieldFromId("pay_period_id", "pay_periods", "pay_period_id", $_GET['pay_period_id']);
				if (!empty($payPeriodId)) {
					$resultSet = executeQuery("select * from pay_periods where pay_period_id = ?", $payPeriodId);
					if ($row = getNextRow($resultSet)) {
						$resultSet = executeQuery("update pay_periods set log_entry = ? where pay_period_id = ?",
							$row['log_entry'] . "\n\nGL File downloaded and expenses updated", $payPeriodId);
					}
				} else {
					echo "Invalid Pay Period";
					exit;
				}

				$designationIdArray = array();
				$donationArray = array();
				$resultSet = executeQuery("select * from donations where client_id = ? and donation_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?) order by donation_id",
					$GLOBALS['gClientId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
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

				$this->iDatabase->startTransaction();

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
								$designationDeductionRow = getRowFromId("designation_deductions", "designation_id", $designationId, "(one_time = 0 or pay_period_id = ?) && inactive = 0 and payroll_deduction_id = ?", $payPeriodId, $row['payroll_deduction_id']);
								if (!empty($designationDeductionRow)) {
									$row['amount'] = $designationDeductionRow['amount'];
									$row['percentage'] = $designationDeductionRow['percentage'];
									executeQuery("update designation_deductions set pay_period_id = ? where designation_deduction_id = ?",$payPeriodId, $designationDeductionRow['designation_deduction_id']);
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
									$designationArray[$designationId]['deductions'][] = array("payroll_deduction_id" => $row['payroll_deduction_id'], "include_in_payout" => $row['include_in_payout'], "amount" => $thisDeductionAmount, "that_amount" => 0);
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
					if ($deductibleAmount <= 0) {
						continue;
					}
					if ($row['include_in_payout']) {
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
					}
					foreach ($designationArray as $designationId => $designationRow) {
						foreach ($designationRow['deductions'] as $deductionInfo) {
							if ($deductionInfo['payroll_deduction_id'] == $row['payroll_deduction_id']) {
								if (empty($deductionInfo['include_in_payout'])) {
									$designationArray[$designationId]['total_deductions'] = $deductionInfo['amount'] + $deductionInfo['that_amount'];
								} else {
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
					}

					echo "ENDTRNS\n";
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
				$this->iDatabase->commitTransaction();
				exit;

			case "test_download_checks_file":
			case "test_download_gl_file":
				$designationIdArray = array();
				$donationArray = array();
				$resultSet = executeQuery("select * from donations where client_id = ? and donation_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?) order by donation_id",
					$GLOBALS['gClientId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$donationArray[$row['donation_id']] = $row;
					if (!in_array($row['designation_id'], $designationIdArray)) {
						$designationIdArray[] = $row['designation_id'];
					}
				}
				$designationList = implode(",", $designationIdArray);
				$processErrors = "";
				$resultSet = executeQuery("select * from designations where client_id = ? and designation_id in (" .
					$designationList . ") order by designation_code", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
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
					$returnArray['process_errors'] = $processErrors;
				}
				ajaxResponse($returnArray);
				break;

			case "set_date":
				$fundLog = "";
				$payPeriodId = $_POST['pay_period_id'];
				$datePaidOut = makeDateParameter($_POST['date_paid_out']);
				$resultSet = executeQuery("update donations set pay_period_id = ? where pay_period_id is null and client_id = ? and donation_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $payPeriodId, $GLOBALS['gClientId'],
					$GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$donationsUpdated = $resultSet['affected_rows'];
				if (!empty($payPeriodId)) {
					$resultSet = executeQuery("select * from pay_periods where pay_period_id = ?", $payPeriodId);
					if ($row = getNextRow($resultSet)) {
						$resultSet = executeQuery("update pay_periods set log_entry = ?,date_paid_out = ? where pay_period_id = ?",
							$row['log_entry'] . "\n\nDate paid out set on " . $donationsUpdated . " donations to " . $_POST['date_paid_out'], $datePaidOut, $payPeriodId);
					}
				}
				$resultSet = executeQuery("select * from donations where amount > 0" . (empty($_POST['force_fee_recalculation']) ? " and donation_fee is null" : "") . " and pay_period_id = ?", $payPeriodId);
				while ($row = getNextRow($resultSet)) {
					if (empty($row['associated_donation_id'])) {
						$donationId = $row['donation_id'];
						$donationFee = Donations::getDonationFee(array("designation_id" => $row['designation_id'], "amount" => $row['amount'], "payment_method_id" => $row['payment_method_id']));
						executeQuery("update donations set donation_fee = ? where donation_id = ?", $donationFee, $donationId);
					}
				}
				if (!empty($_POST['force_fee_recalculation'])) {
					$returnArray['force_fee_recalculation'] = true;
				}
				$resultSet = executeQuery("select count(*) gift_count,coalesce(sum(amount),0) total_donations,sum(coalesce(donation_fee,0)) total_fees from donations where client_id = ? and pay_period_id = ?", $GLOBALS['gClientId'], $payPeriodId);
				if ($row = getNextRow($resultSet)) {
					$returnArray['total_fees'] = number_format($row['total_fees'], 2, ".", ",");
					$returnArray['net_donations'] = number_format($row['total_donations'] - $row['total_fees'], 2, ".", ",");
				}
				$fundAccounts = array();
				if (!empty($payPeriodId)) {
					$designationTotals = array();
					$resultSet = executeQuery("select designation_id,sum(amount),sum(donation_fee) from donations where pay_period_id = ? " .
						"group by designation_id order by designation_id", $payPeriodId);
					while ($row = getNextRow($resultSet)) {
						$designationTotals[$row['designation_id']] = array("total" => $row['sum(amount)'], "fees" => $row['sum(donation_fee)']);
					}
					$resultSet = executeQuery("select * from fund_accounts where inactive = 0 and client_id = ? order by sort_order,fund_account_id",
						$GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$designationGroupRows = array();
						$designationGroupSet = executeQuery("select * from fund_account_designation_groups where fund_account_id = ?", $row['fund_account_id']);
						while ($designationGroupRow = getNextRow($designationGroupSet)) {
							$designationGroupRows[$designationGroupRow['designation_group_id']] = $designationGroupRow;
						}
						$row['designation_group_rows'] = $designationGroupRows;
						$row['total_amount'] = 0;
						$fundAccounts[] = $row;
					}
					foreach ($designationTotals as $designationId => $designationInfo) {
						$payrollTotal = $designationInfo['total'];
						$donationFeeTotal = $designationInfo['fees'];
						$remainingTotal = $payrollTotal - $donationFeeTotal;
						$designationGroups = array();
						$resultSet = executeQuery("select * from designation_group_links where designation_id = ?", $designationId);
						while ($row = getNextRow($resultSet)) {
							$designationGroups[] = $row['designation_group_id'];
						}
						if (empty($designationGroups)) {
							continue;
						}
						foreach ($fundAccounts as $index => $fundRow) {
							$designationGroupId = "";
							foreach ($designationGroups as $thisDesignationGroupId) {
								if (array_key_exists($thisDesignationGroupId, $fundRow['designation_group_rows'])) {
									$designationGroupId = $thisDesignationGroupId;
									break;
								}
							}
							if (empty($designationGroupId)) {
								continue;
							}
							$fundRow['amount'] = $fundRow['designation_group_rows'][$designationGroupId]['amount'];
							$fundRow['percentage'] = $fundRow['designation_group_rows'][$designationGroupId]['percentage'];
							$fundRow['minimum_amount'] = $fundRow['designation_group_rows'][$designationGroupId]['minimum_amount'];
							$fundRow['maximum_amount'] = $fundRow['designation_group_rows'][$designationGroupId]['maximum_amount'];
							$fundRow['per_month_maximum'] = $fundRow['designation_group_rows'][$designationGroupId]['per_month_maximum'];
							$designationFundAccounts = getRowFromId("designation_fund_accounts", "designation_id", $designationId, "fund_account_id = ?", $fundRow['fund_account_id']);
							$designationFundAccountOverrides = getRowFromId("designation_fund_account_overrides", "designation_id", $designationId, "pay_period_id is null and fund_account_id = ?", $fundRow['fund_account_id']);
							if (!empty($designationFundAccounts)) {
								$fundRow['amount'] = $designationFundAccounts['amount'];
								$fundRow['percentage'] = $designationFundAccounts['percentage'];
								if (strlen($designationFundAccounts['minimum_amount']) > 0) {
									$fundRow['minimum_amount'] = $designationFundAccounts['minimum_amount'];
								}
								if (strlen($designationFundAccounts['maximum_amount']) > 0) {
									$fundRow['maximum_amount'] = $designationFundAccounts['maximum_amount'];
								}
								if (strlen($designationFundAccounts['per_month_maximum']) > 0) {
									$fundRow['per_month_maximum'] = $designationFundAccounts['per_month_maximum'];
								}
							}
							if (!empty($designationFundAccountOverrides)) {
								$fundRow['amount'] = $designationFundAccountOverrides['amount'];
								$fundRow['percentage'] = $designationFundAccountOverrides['percentage'];
								executeQuery("update designation_fund_account_overrides set pay_period_id = ? where designation_fund_account_override_id = ?", $payPeriodId, $designationFundAccountOverrides['designation_fund_account_override_id']);
							}
							if (empty($fundRow['minimum_amount'])) {
								$fundRow['minimum_amount'] = 0;
							}
							if (empty($fundRow['maximum_amount']) || $fundRow['maximum_amount'] <= 0) {
								$fundRow['maximum_amount'] = 999999999;
							}
							if (empty($fundRow['per_month_maximum']) || $fundRow['per_month_maximum'] <= 0) {
								$fundRow['per_month_maximum'] = 999999999;
							}
							$thisAmount = max($fundRow['amount'] + round(($payrollTotal * $fundRow['percentage'] / 100), 2), $fundRow['minimum_amount']);
							$currentAmount = 0;
							$resultSet = executeQuery("select sum(amount) from fund_account_details where designation_id = ? and fund_account_id = ?",
								$designationId, $fundRow['fund_account_id']);
							if ($row = getNextRow($resultSet)) {
								$currentAmount = (empty($row['sum(amount)']) ? 0 : $row['sum(amount)']);
							}
							$thisAmount = min($fundRow['maximum_amount'] - $currentAmount, $thisAmount);
							$currentAmount = 0;
							$startDate = date("Y-m", strtotime($_POST['date_paid_out'])) . "-01";
							$endDate = date("Y-m-t", strtotime($_POST['date_paid_out']));
							$resultSet = executeQuery("select sum(amount) from fund_account_details where designation_id = ? and " .
								"fund_account_id = ? and entry_date between ? and ?",
								$designationId, $fundRow['fund_account_id'], $startDate, $endDate);
							if ($row = getNextRow($resultSet)) {
								$currentAmount = (empty($row['sum(amount)']) ? 0 : $row['sum(amount)']);
							}
							$thisAmount = min($fundRow['per_month_maximum'] - $currentAmount, $thisAmount);
							$thisAmount = min($remainingTotal, $thisAmount);
							$fundLog .= $designationId . ": " . $fundRow['amount'] . ", " . $fundRow['percentage'] . ", " . $fundRow['minimum_amount'] . ", " . $fundRow['maximum_amount'] . ", " . $fundRow['per_month_maximum'] . " = " . $thisAmount . "\n";
							if ($thisAmount > 0) {
								$fundAccounts[$index]['total_amount'] += $thisAmount;
								executeQuery("insert into fund_account_details (fund_account_id,designation_id,description,amount,entry_date,date_paid_out,pay_period_id) " .
									"values (?,?,'Payroll Deduction',?,now(),now(),?)", $fundRow['fund_account_id'], $designationId, $thisAmount, $payPeriodId);
								$remainingTotal -= $thisAmount;
							}
						}
						$designationRemainderFundRow = getRowFromId("designation_remainder_funds", "designation_id", $designationId);
						if (!empty($designationRemainderFundRow)) {
							$thisAmount = $remainingTotal - $designationRemainderFundRow['amount'];
							if ($thisAmount > 0) {
								foreach ($fundAccounts as $index => $fundRow) {
									if ($fundRow['fund_account_id'] == $designationRemainderFundRow['fund_account_id']) {
										$fundAccounts[$index]['total_amount'] += $thisAmount;
										executeQuery("insert into fund_account_details (fund_account_id,designation_id,description,amount,entry_date,date_paid_out,pay_period_id) " .
											"values (?,?,'Payroll Remainder Deduction',?,now(),now(),?)", $fundRow['fund_account_id'], $designationId, $thisAmount, $payPeriodId);
										$remainingTotal -= $thisAmount;
										break;
									}
								}
							}
						}
					}
				}
				$fundMessage = "";
				foreach ($fundAccounts as $fundRow) {
					if ($fundRow['total_amount'] == 0) {
						continue;
					}
					if (empty($fundMessage)) {
						$fundMessage = "Fund account contributions:";
					}
					$fundMessage .= "<br>" . $fundRow['description'] . " - " . number_format($fundRow['total_amount'], 2);
				}
				$returnArray['fund_accounts'] = $fundMessage;
				$returnArray['set_date_message'] = $donationsUpdated . " donation" . ($donationsUpdated == 1 ? "" : "s") . " have had date paid out set to " . $_POST['date_paid_out'];
				ob_start();
				?>
                <h3 id="download_header">Downloads</h3>
                <div>
					<?php if (getPreference("ACCOUNTING_DOWNLOADS")) { ?>
                        <button class="download-button" id="gl_file">Quickbooks GL File</button>
                        <button class="download-button" id="checks_file">Quickbooks Checks File</button>
					<?php } ?>
					<?php
					if (function_exists("customClientPayrollDownloads")) {
						customClientPayrollDownloads();
					}
					?>
                    <button class="download-button" id="payroll_file">Payroll File</button>
                </div>
                <p>
				<?php
				$returnArray['next_step'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;

			case "confirm_amounts":
				ob_start();
				?>
                <p id="force_fee_recalculation_wrapper"><input type="checkbox" id="force_fee_recalculation" name="force_fee_recalculation" value="1"><label class="checkbox-label" for="force_fee_recalculation">Force recalculation of fees</label></p>
                <p class="highlighted-text color-green" id="set_date_message"><label for="date_paid_out">Date Paid Out</label><input type="text" id="date_paid_out" name="date_paid_out" size="12" maxlength="12" class="validate[custom[date],required] datepicker" value="<?= date("m/d/Y") ?>">
                    <button id="set_date_button">Set Date Paid Out</button>
                </p>
                <p class="highlighted-text color-green" id="fund_accounts"></p>
				<?php
				$returnArray['next_step'] = ob_get_clean();
				$resultSet = executeQuery("insert into pay_periods (client_id,date_created,donation_count,total_donations,user_id,log_entry) values " .
					"(?,now(),?,?,?,?)", $GLOBALS['gClientId'], $_POST['gift_count'], $_POST['total_donations'], $GLOBALS['gUserId'], "Payroll processing started at " . date("m/d/y g:ia") . "\n\nWhere: " .
					$_POST['where_statement'] . "\n\nGifts: " . $_POST['gift_count'] . "\nTotal Donations: " . number_format($_POST['total_donations'], 2));
				$returnArray['pay_period_id'] = $resultSet['insert_id'];
				ajaxResponse($returnArray);
				break;

			case "create_report":
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

				if (!empty($_POST['contact_id'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.contact_id = ?";
					$parameters[] = $_POST['contact_id'];
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Contact ID is " . $_POST['contact_id'];
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
				if (!empty($whereStatement)) {
					$whereStatement .= " and ";
				}
				$whereStatement .= "(donation_batch_id is null or exists (select donation_batch_id from donation_batches where " .
					"donation_batch_id = donations.donation_batch_id and date_posted is not null)) and pay_period_id is null";
				ob_start();
				$returnArray['where_statement'] = $whereStatement . " - " . implode(",", $parameters);
				$returnArray['report_title'] = "Donation Processing";
				?>
                <p>Select: <?= (empty($displayCriteria) ? "All unprocessed donations" : $displayCriteria) ?></p>
				<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                <p>Select: <?= (empty($whereStatement) ? "All unprocessed donations" : $whereStatement) ?></p>
			<?php } ?>
                <p>Run on <?= date("m-d-Y") ?> by <?= getUserDisplayName() ?></p>
				<?php
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$resultSet = executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) " .
					"select " . $GLOBALS['gUserId'] . "," . $GLOBALS['gPageId'] . ",donation_id from donations where " .
					"client_id = ?" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
				$resultSet = executeQuery("select count(*) from donations where designation_id in (select designation_id from " .
					"designations where requires_attention = 1) and pay_period_id is null and client_id = ?" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
				$attentionCount = 0;
				if ($row = getNextRow($resultSet)) {
					$attentionCount = $row['count(*)'];
				}
				$resultSet = executeQuery("select * from recurring_donations where designation_id in (select designation_id from " .
					"designations where requires_attention = 1 and client_id = ?) and (end_date > current_date or end_date is null) and " .
					"(start_date is null or start_date <= current_date)", $GLOBALS['gClientId']);
				$recurringAttentionCount = $resultSet['row_count'];
				$recurringAttentionDetails = "";
				while ($row = getNextRow($resultSet)) {
					$recurringAttentionDetails .= (empty($recurringAttentionDetails) ? "" : "<br>") . "$" . number_format($row['amount'], 2, ".", ",") . " for " . getFieldFromId("description", "designations", "designation_id", $row['designation_id']) . " from " . getDisplayName($row['contact_id']);
				}
				$resultSet = executeQuery("select count(*) gift_count,coalesce(sum(amount),0) total_donations,sum(coalesce(donation_fee,0)) total_fees from donations where client_id = ?" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
				if ($row = getNextRow($resultSet)) {
					$returnArray['gift_count'] = $row['gift_count'];
					$returnArray['total_donations'] = $row['total_donations'];
					$returnArray['total_fees'] = $row['total_fees'];
				} else {
					$returnArray['gift_count'] = 0;
					$returnArray['total_donations'] = 0;
					$returnArray['total_fees'] = 0;
				}
				$resultSet = executeQuery("select count(*) gift_count,coalesce(sum(amount),0) total_donations from donations where client_id = ? and donation_batch_id is null" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
				if ($row = getNextRow($resultSet)) {
					$returnArray['no_batch_gift_count'] = $row['gift_count'];
					$returnArray['no_batch_total_donations'] = $row['total_donations'];
				} else {
					$returnArray['no_batch_gift_count'] = 0;
					$returnArray['no_batch_total_donations'] = 0;
				}
				$resultSet = executeQuery("select count(*) gift_count,coalesce(sum(amount),0) total_donations from donations where client_id = ? and donation_batch_id is not null" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
				if ($row = getNextRow($resultSet)) {
					$returnArray['batch_gift_count'] = $row['gift_count'];
					$returnArray['batch_total_donations'] = $row['total_donations'];
				} else {
					$returnArray['batch_gift_count'] = 0;
					$returnArray['batch_total_donations'] = 0;
				}
				?>
                <div id="donation_process">
                    <p id="process_errors"></p>
                    <form id="_process_parameters">
                        <input type="hidden" id="where_statement" name="where_statement">
                        <input type="hidden" id="pay_period_id" name="pay_period_id">
                        <input type="hidden" id="gift_count" name="gift_count">
                        <input type="hidden" id="total_donations" name="total_donations">
                        <p class="highlighted-text">Found <?= $returnArray['gift_count'] ?> gift<?= ($returnArray['gift_count'] == 1 ? "" : "s") ?> for <?= number_format($returnArray['total_donations'], 2, ".", ",") ?></p>
						<?php if ($returnArray['no_batch_gift_count'] > 0 && $returnArray['batch_gift_count'] > 0) { ?>
                            <p class="highlighted-text">Found <?= $returnArray['batch_gift_count'] ?> gift<?= ($returnArray['gift_count'] == 1 ? "" : "s") ?> for <?= number_format($returnArray['batch_total_donations'], 2, ".", ",") ?> in batches</p>
                            <p class="highlighted-text">Found <?= $returnArray['no_batch_gift_count'] ?> gift<?= ($returnArray['gift_count'] == 1 ? "" : "s") ?> for <?= number_format($returnArray['no_batch_total_donations'], 2, ".", ",") ?> NOT in batches</p>
						<?php } ?>
                        <p class="highlighted-text">Totals Fees: <span id='total_fees'><?= number_format($returnArray['total_fees'], 2, ".", ",") ?></span>, Net Donations: <span id='net_donations'><?= number_format($returnArray['total_donations'] - $returnArray['total_fees'], 2, ".", ",") ?></span></p>
						<?php if ($returnArray['gift_count'] > 0 && $attentionCount == 0 && $recurringAttentionCount == 0) { ?>
                            <p id="confirm_message" class="color-green highlighted-text">
                                <button id="confirm_totals">Confirm The Totals</button>
                            </p>
						<?php } else { ?>
							<?php if ($returnArray['gift_count'] == 0) { ?>
                                <p id="confirm_message" class="color-red highlighted-text">No donations found for this criteria.</p>
							<?php } ?>
							<?php if ($attentionCount > 0) { ?>
                                <p class="color-red highlighted-text">Payroll cannot be processed because <?= ($attentionCount < 10 ? $GLOBALS['gNumberWords'][$attentionCount] : $attentionCount) ?> donation<?= ($attentionCount == 1 ? "" : "s") ?> require<?= ($attentionCount == 1 ? "s" : "") ?> attention. <a href="/donationsrequiringattention.php">Click here</a> to work on <?= ($attentionCount == 1 ? "it" : "them") ?>.</p>
							<?php } ?>
							<?php if ($recurringAttentionCount > 0) { ?>
                                <p class="color-red highlighted-text">Payroll cannot be processed because <?= ($recurringAttentionCount < 10 ? $GLOBALS['gNumberWords'][$recurringAttentionCount] : $recurringAttentionCount) ?> recurring donation<?= ($recurringAttentionCount == 1 ? "" : "s") ?> are for a designation that require<?= ($recurringAttentionCount == 1 ? "s" : "") ?> attention.<br><?= $recurringAttentionDetails ?><br><a href="/recurringdonationmaintenance.php">Click here</a> to work on <?= ($recurringAttentionCount == 1 ? "it" : "them") ?>.</p>
							<?php } ?>
						<?php } ?>
                    </form>
                </div>
				<?php
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;

			case "remove_payroll":
				$resultSet = executeQuery("select * from pay_periods where client_id = ? and pay_period_id = ? and date_created > SUBDATE(now(), INTERVAL 3 DAY)", $GLOBALS['gClientId'], $_GET['pay_period_id']);
				if ($payPeriodRow = getNextRow($resultSet)) {
					$resultSet = executeQuery("update donations set pay_period_id = null where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					$resultSet = executeQuery("update designation_fund_account_overrides set pay_period_id = null where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					$resultSet = executeQuery("update designation_deductions set pay_period_id = null where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					$resultSet = executeQuery("delete from expense_uses where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					$resultSet = executeQuery("delete from fund_account_details where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					$resultSet = executeQuery("delete from paycheck_records where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					$resultSet = executeQuery("delete from pay_periods where pay_period_id = ?", $payPeriodRow['pay_period_id']);
					addProgramLog("Pay Period Removed: " . jsonEncode($payPeriodRow));
				}
				ajaxResponse($returnArray);
				break;
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
		$resultSet = executeReadQuery("select minimum_salary from payroll_groups where payroll_group_id = (select payroll_group_id from designations where designation_id = ?)", $designationId);
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

	function designationSort($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description'] ? 1 : -1);
	}

	function mainContent() {
		$previousDays = 3;
		if (!empty($_GET['previous_days']) && is_numeric($_GET['previous_days'])) {
			$previousDays = $_GET['previous_days'];
		}
		$resultSet = executeQuery("select *,(select count(*) from donations where pay_period_id = pay_periods.pay_period_id) as donation_count," .
			"(select sum(amount) from donations where pay_period_id = pay_periods.pay_period_id) as donation_total from pay_periods where client_id = ? and date_created > SUBDATE(now(), INTERVAL " . $previousDays . " DAY) order by pay_period_id desc", $GLOBALS['gClientId']);
		while ($payPeriodRow = getNextRow($resultSet)) {
			?>
            <div class="payroll-removal">
                <p class="error-message">Payroll was run on <?= date("m/d/Y", strtotime($payPeriodRow['date_created'])) . " by " . getUserDisplayName($payPeriodRow['user_id']) ?>.</p>
                <p class="error-message">Pay Period <?= $payPeriodRow['pay_period_id'] ?>, <?= $payPeriodRow['donation_count'] ?> donations totaling $<?= number_format($payPeriodRow['donation_total'], 2, ".", ",") ?></p>
                <p class="error-message">Do you want to remove this payroll and rerun it or create a new payroll?</p>
                <p>
                    <button tabindex="10" class="remove-payroll" data-pay_period_id="<?= $payPeriodRow['pay_period_id'] ?>">Remove Payroll</button>
                </p>
            </div>
			<?php
		}
		?>
        <div id="report_parameters">
            <p class="color-red">Only donations that have not been processed in a previous pay period will be included.</p>
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line" id="_batch_number_row">
                    <label for="batch_number_from">Batch Number: From</label>
                    <span class="help-label">Including a batch number range WILL exclude online donations that don't have a batch number</span>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_from" name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_no_batch_row">
                    <input tabindex="10" type="checkbox" id="include_no_batch" name="include_no_batch"><label class="checkbox-label" for="include_no_batch">Only Include Donations without Batch</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_contact_id_row">
                    <label>Contact ID</label>
                    <input tabindex="10" class='validate[custom[integer]]' type="text" id="contact_id" name="contact_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_donation_date_row">
                    <label for="donation_date_from">Donation Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_from" name="donation_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="donation_date_to" name="donation_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_type_id_row">
                    <label for="designation_type_id">Designation Type</label>
                    <select tabindex="10" id="designation_type_id" name="designation_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeQuery("select * from designation_types where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
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
						$resultSet = executeQuery("select * from designation_groups where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
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

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Process Payroll</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="new_parameters_button">Search Again</button>
            <button id="help_button">Help</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(".selector-value-list").trigger("change");
            $(document).on("tap click", "#payroll_report", function () {
                document.location = "/payrollreport.php?url_page=show&primary_id=" + $("#pay_period_id").val();
                return false;
            });
            $(document).on("tap click", "#help_button", function () {
                $('#_help_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Help',
                    buttons: {
                        close: function (event) {
                            $("#_help_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", ".remove-payroll", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_payroll&pay_period_id=" + $(this).data("pay_period_id"), function(returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                });
                return false;
            });
            $(document).on("tap click", "#create_new_payroll", function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?new=true";
                return false;
            });
            $(document).on("tap click", "#create_report", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                        if ("report_content" in returnArray) {
                            $("#report_parameters").hide();
                            $("#_report_title").html(returnArray['report_title']).show();
                            $("#_report_content").html(returnArray['report_content']).show();
                            $("#_button_row").show();
                            $("#where_statement").val(returnArray['where_statement']);
                            $("#gift_count").val(returnArray['gift_count']);
                            $("#total_donations").val(returnArray['total_donations']);
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
            $(document).on("tap click", "#set_date_button", function () {
                if ($("#_process_parameters").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_date", $("#_process_parameters").serialize(), function(returnArray) {
                        if ("next_step" in returnArray) {
                            $("#_process_parameters").append(returnArray['next_step']);
                            $("#set_date_message").html(returnArray['set_date_message']);
                            $("#fund_accounts").html(returnArray['fund_accounts']);
                            if ("force_fee_recalculation" in returnArray) {
                                $("#force_fee_recalculation_wrapper").addClass("green-text").html("Fees recalculated");
                            } else {
                                $("#force_fee_recalculation_wrapper").html("");
                            }
                        }
                        if ("total_fees" in returnArray) {
                            $("#total_fees").html(returnArray['total_fees']);
                            $("#net_donations").html(returnArray['net_donations']);
                        }
                    });
                    $("#set_date_message").html("");
                }
                return false;
            });
            $(document).on("tap click", "#confirm_totals", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=confirm_amounts", $("#_process_parameters").serialize(), function(returnArray) {
                    if ("next_step" in returnArray) {
                        $("#_process_parameters").append(returnArray['next_step']);
                        $("#_report_content .datepicker").datepicker({
                            showOn: "button",
                            buttonText: "<span class='fad fa-calendar-alt'></span>",
                            constrainInput: false,
                            dateFormat: "mm/dd/yy",
                            yearRange: "c-100:c+10"
                        });
                        $("#pay_period_id").val(returnArray['pay_period_id']);
                        $("#_process_parameters").validationEngine();
                        $("#date_paid_out").focus();
                    }
                });
                $("#confirm_message").html("Number and amount were confirmed");
                $("#new_parameters_button").remove();
                return false;
            });
            $(document).on("tap click", "#gl_file", function () {
                $("#process_errors").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_download_gl_file", $("#_process_parameters").serialize(), function(returnArray) {
                    if ("process_errors" in returnArray) {
                        $("#process_errors").html(returnArray['process_errors']);
                    } else {
                        $("#gl_file").replaceWith("<p class='highlighted-text color-green'>GL File Downloaded</p>");
                        checkAllDownloads();
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=download_gl_file&pay_period_id=" + $("#pay_period_id").val();
                    }
                });
                return false;
            });
            $(document).on("tap click", "#checks_file", function () {
                $("#process_errors").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_download_checks_file", $("#_process_parameters").serialize(), function(returnArray) {
                    if ("process_errors" in returnArray) {
                        $("#process_errors").html(returnArray['process_errors']);
                    } else {
                        $("#checks_file").replaceWith("<p class='highlighted-text color-green'>Checks File Downloaded</p>");
                        checkAllDownloads();
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=download_checks_file&pay_period_id=" + $("#pay_period_id").val();
                    }
                });
                return false;
            });
            $(document).on("tap click", "#payroll_file", function () {
                $("#payroll_file").replaceWith("<p class='highlighted-text color-green'>Payroll File Downloaded</p>");
                checkAllDownloads();
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=download_payroll_file&pay_period_id=" + $("#pay_period_id").val();
                return false;
            });
            $(document).on("tap click", "#send_emails", function () {
                $("#send_emails_message").html("");
                sendEmails();
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function sendEmails() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_emails&email_credential_id=" + $("#email_credential_id").val(), $("#_process_parameters").serialize(), function(returnArray) {
                    if ("process_errors" in returnArray) {
                        $("#process_errors").html(returnArray['process_errors']);
                        return;
                    }
                    if ("next_step" in returnArray) {
                        $("#_process_parameters").append(returnArray['next_step']);
                    }
                    if ("processed_count" in returnArray) {
                        $("#processed_count").val(returnArray['processed_count']);
                    }
                    if ("total_count" in returnArray) {
                        $("#total_count").val(returnArray['total_count']);
                    }
                    if ("email_list" in returnArray) {
                        $("#email_list").val(returnArray['email_list']);
                    }
                    if ("email_list_addition" in returnArray) {
                        $("#email_list").val($("#email_list").val() + returnArray['email_list_addition']);
                    }
                    if ("designation_list" in returnArray) {
                        $("#designation_list").val(returnArray['designation_list']);
                    }
                    if ("done_message" in returnArray) {
                        $("#send_emails_message").html(returnArray['done_message']);
                        $("#payroll_report").show();
                    } else {
                        $("#send_emails_message").html($("#processed_count").val() + " of " + $("#total_count").val() + " emails sent");
                        setTimeout(function () {
                            sendEmails();
                        }, 400);
                    }
                });
            }
            function checkAllDownloads() {
                $("#download_header").remove();
                if ($(".download-button").length == 0) {
                    setTimeout(function () {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=downloads_done", $("#_process_parameters").serialize(), function(returnArray) {
                            if ("next_step" in returnArray) {
                                $("#_process_parameters").append(returnArray['next_step']);
                            }
                        });
                    }, 1000);
                }
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .payroll-removal {
                margin-bottom: 20px;
            }

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

            #_help_dialog ul {
                list-style-type: disc;
                margin-left: 20px;
                font-size: 14px;
            }

            #_help_dialog ul li {
                margin-bottom: 5px;
            }

            #donation_process label {
                margin-right: 10px;
            }

            #set_date_button {
                margin-left: 20px;
            }

            #process_errors {
                color: rgb(205, 0, 0);
                font-weight: bold;
                font-size: 18px;
            }

            #email_list {
                width: 1100px;
                height: 100px;
            }

            #payroll_report {
                display: none;
            }

            .color-red {
                font-size: 16px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_help_dialog" class="dialog-box">
            <p class="highlighted-text">The donation process is as follows:</p>
            <ul>
                <li>Confirm the number and amounts of the donations selected.</li>
                <li>Set the date paid out for the donations.</li>
                <li>Download files.</li>
                <li>Send Giving Report Emails.</li>
            </ul>
            </p>
        </div>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
