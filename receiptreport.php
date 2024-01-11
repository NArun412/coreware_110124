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

$GLOBALS['gPageCode'] = "RECEIPTREPORT";
$GLOBALS['gDefaultAjaxTimeout'] = 120000;
require_once "shared/startup.inc";

class ReceiptReportPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_resend_count":
			case "create_report":
				if (empty($_POST['receipt_type'])) {
					return;
				}
				saveStoredReport();

				$whereStatement = "";
				$newWhereStatement = "";
				$parameters = array($GLOBALS['gClientId']);

				if (!empty($_POST['donation_id'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.donation_id = ?";
					$parameters[] = $_POST['donation_id'];
				}

				if (!empty($_POST['donation_date_from'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.donation_date >= ?";
					$parameters[] = makeDateParameter($_POST['donation_date_from']);
				}

				if (!empty($_POST['donation_date_to'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.donation_date <= ?";
					$parameters[] = makeDateParameter($_POST['donation_date_to']);
				}

				if (!empty($_POST['only_tax_deductible'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.designation_id not in (select designation_id from designations where not_tax_deductible = 1)";
				}

				if (!empty($_POST['batch_number_from'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.donation_batch_id is not null and donations.donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and batch_number >= ?)";
					$parameters[] = $GLOBALS['gClientId'];
					$parameters[] = $_POST['batch_number_from'];
				}
				if (!empty($_POST['batch_number_to'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.donation_batch_id is not null and donations.donation_batch_id in (select donation_batch_id from donation_batches where client_id = ? and batch_number <= ?)";
					$parameters[] = $GLOBALS['gClientId'];
					$parameters[] = $_POST['batch_number_to'];
				}

				if (!empty($_POST['no_batch'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.donation_batch_id is null";
				}

				if (!empty($_POST['amount_from'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "amount >= ?";
					$parameters[] = $_POST['amount_from'];
				}
				if (!empty($_POST['amount_to'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "amount <= ?";
					$parameters[] = $_POST['amount_to'];
				}
				if (!empty($_POST['total_giving_from']) && !empty($_POST['total_giving_to'])) {
					$newWhereStatement = "(select sum(amount) from donations d where client_id = ? and coalesce(receipted_contact_id,contact_id) = donations.contact_id and exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id)" .
						(empty($whereStatement) ? "" : " and " . str_replace("donations.", "d.", $whereStatement)) . ") between ? and ?";
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= $newWhereStatement;
					$parameters = array_merge($parameters, $parameters);
					$parameters[] = $_POST['total_giving_from'];
					$parameters[] = $_POST['total_giving_to'];
				} else if (!empty($_POST['total_giving_from'])) {
					$newWhereStatement = "(select sum(amount) from donations d where client_id = ? and coalesce(receipted_contact_id,contact_id) = donations.contact_id and exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id)" .
						(empty($whereStatement) ? "" : " and " . str_replace("donations.", "d.", $whereStatement)) . ") >= ?";
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= $newWhereStatement;
					$parameters = array_merge($parameters, $parameters);
					$parameters[] = $_POST['total_giving_from'];
				} else if (!empty($_POST['total_giving_to'])) {
					$newWhereStatement .= "(select sum(amount) from donations d where client_id = ? and coalesce(receipted_contact_id,contact_id) = donations.contact_id and exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id)" .
						(empty($whereStatement) ? "" : " and " . str_replace("donations.", "d.", $whereStatement)) . ") <= ?";
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= $newWhereStatement;
					$parameters = array_merge($parameters, $parameters);
					$parameters[] = $_POST['total_giving_to'];
				}

				if (!empty($_POST['contact_id'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.contact_id = ?";
					$parameters[] = $_POST['contact_id'];
				}

				if (!empty($_POST['designation_groups'])) {
					$designationGroupArray = explode(",", $_POST['designation_groups']);
				} else {
					$designationGroupArray = array();
				}
				if (count($designationGroupArray) > 0) {
					$designationGroupWhere = "";
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
					}
					if (!empty($designationGroupWhere)) {
						if (!empty($whereStatement)) {
							$whereStatement .= " and ";
						}
						$whereStatement .= "designation_id not in (select designation_id from designation_group_links where designation_group_id in (" . $designationGroupWhere . "))";
					}
				}

				if (!empty($_POST['selected_donors'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donations.contact_id in (select primary_identifier from selected_rows where user_id = ? and page_id = ?)";
					$parameters[] = $GLOBALS['gUserId'];
					$parameters[] = $GLOBALS['gAllPageCodes']["CONTACTMAINT"];
				}

				if ($_POST['receipt_type'] == "summary") {
					$pdfReceiptBatchId = "";
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "associated_donation_id is null";
					$giftDetailLine = getPreference("RECEIPT_DETAIL_LINE");
					if (empty($giftDetailLine)) {
						$giftDetailLine = "%donation_date%\t%designation_code% - %designation_description%\t%amount%";
					}

					if ($_GET['url_action'] == "create_report") {
						header("Content-Type: text/csv");
						header("Content-Disposition: attachment; filename=\"receipts.csv\"");
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						header('Pragma: public');
						echo '"ContactId","Title","First","Middle","Last","Suffix","FullName","Salutation","BusinessName","Address1","Address2","City","State",' .
							'"PostalCode","Country","EmailAddress","UserName","StartDonationDate","EndDonationDate","GiftCount","TotalGifts","GiftDetail"' . "\n";
					}
					$emailCount = 0;
					$downloadCount = 0;
					$pdfCount = 0;
					$failedEmailCount = 0;
					$resultSet = executeQuery("select * from donations where donations.client_id = ?" .
						(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by contact_id,donation_id", $parameters);
					$exportArray = array();
					while ($row = getNextRow($resultSet)) {
						$contactRow = Contact::getContact((empty($row['receipted_contact_id']) ? $row['contact_id'] : $row['receipted_contact_id']));
                        $contactId = $contactRow['contact_id'];
						$row = array_merge($row, $contactRow);
						if (!array_key_exists($contactId, $exportArray)) {
							$row['gift_count'] = 0;
							$row['donation_total'] = 0;
							$row['gift_detail'] = "";
							$row['gift_detail_table'] = "<table id='donation_details' style='width: 600px'><tr><th style='text-align: left; padding: 5px 20px;'>Date</th><th style='text-align: left; padding: 5px 20px;'>Designation</th><th style='text-align: left; padding: 5px 20px;'>Amount</th></tr>";
							$exportArray[$contactId] = $row;
						}
						$row["designation_description"] = getFieldFromId("description", "designations", "designation_id", $row['designation_id']);
						$row["designation_code"] = getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']);
						$thisDetailLine = $giftDetailLine;
						foreach ($row as $fieldName => $fieldValue) {
							if ($fieldName == "donation_date") {
								$fieldValue = date("m/d/Y", strtotime($fieldValue));
							}
							$thisDetailLine = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $thisDetailLine);
						}
						$exportArray[$contactId]['gift_detail'] .= (empty($exportArray[$contactId]['gift_detail']) ? "" : "\n") . $thisDetailLine;
						$exportArray[$contactId]['gift_detail_table'] .= "<tr><td style='padding: 5px 20px;'>" . date("m/d/Y", strtotime($row['donation_date'])) . "</td><td style='padding: 5px 20px;'>" . $row['designation_code'] . " - " . $row['designation_description'] . "</td><td style='padding: 5px 20px;'>" . number_format($row['amount'], 2, ".", ",") . "</td></tr>";
						$exportArray[$contactId]['gift_count']++;
						$exportArray[$contactId]['donation_total'] += $row['amount'];
					}
					foreach ($exportArray as $row) {
						if (!empty($_POST['category_id'])) {
							$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "contact_id", $row['contact_id'], "category_id = ?", $_POST['category_id']);
							if (empty($contactCategoryId)) {
								$contactCategoryDataSource = new DataSource("contact_categories");
								$contactCategoryDataSource->saveRecord(array("name_values" => array("contact_id" => $row['contact_id'], "category_id" => $_POST['category_id']), "primary_id" => ""));
							}
						}
						$receiptSent = false;
						$substitutions = array("contact_id" => $row['contact_id'],
							"title" => $row['title'],
							"first_name" => $row['first_name'],
							"middle_name" => $row['middle_name'],
							"last_name" => $row['last_name'],
							"suffix" => $row['suffix'],
							"full_name" => getDisplayName($row['contact_id'], array("dont_use_company" => true, "include_title" => true)),
							"salutation" => (empty($row['salutation']) ? generateSalutation($row) : $row['salutation']),
							"business_name" => $row['business_name'],
							"address_1" => $row['address_1'],
							"address_2" => $row['address_2'],
							"city" => $row['city'],
							"state" => $row['state'],
							"postal_code" => $row['postal_code'],
							"country" => getFieldFromId("country_name", "countries", "country_id", $row['country_id']),
							"email_address" => $row['email_address'],
							"donation_date_from" => date('m/d/y', strtotime($_POST['donation_date_from'])),
							"donation_date_to" => date('m/d/y', strtotime($_POST['donation_date_to'])),
							"donation_year" => date('Y', strtotime($_POST['donation_date_from'])),
							"gift_count" => $row['gift_count'],
							"donation_total" => number_format($row['donation_total'], 2),
							"gift_detail_table" => $row['gift_detail_table'] . "</table>",
							"gift_detail" => str_replace("\n", "<br>", str_replace("\r", "<br>", str_replace("\t", ", ", $row['gift_detail']))));
						$addressBlock = $substitutions['full_name'];
						if (!empty($substitutions['address_1'])) {
							$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['address_1'];
						}
						if (!empty($substitutions['address_2'])) {
							$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['address_2'];
						}
						if (!empty($substitutions['city'])) {
							$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['city'];
						}
						if (!empty($substitutions['state'])) {
							$addressBlock .= (empty($addressBlock) ? "" : ", ") . $substitutions['state'];
						}
						if (!empty($substitutions['postal_code'])) {
							$addressBlock .= (empty($addressBlock) ? "" : " ") . $substitutions['postal_code'];
						}
						if (!empty($substitutions['country_id']) && $substitutions['country_id'] != 1000) {
							$addressBlock .= (empty($addressBlock) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $substitutions['country_id']);
						}
						$substitutions['address_block'] = $addressBlock;
						if (empty($_POST['force_download']) && !empty($_POST['email_id']) && !empty($row['email_address'])) {
							if ($_GET['url_action'] == "create_report") {
								$result = sendEmail(array("email_id" => $_POST['email_id'], "substitutions" => $substitutions, "email_addresses" => $row['email_address']));
								if ($result === true) {
									$receiptSent = true;
								} else {
									$failedEmailCount++;
								}
							}
							$emailCount++;
						}
						if (!$receiptSent) {
							$downloadCount++;
							if ($_GET['url_action'] == "create_report") {
								echo '"' . $row['contact_id'] . '",';
								echo '"' . str_replace('"', '""', $row['title']) . '",';
								echo '"' . str_replace('"', '""', $row['first_name']) . '",';
								echo '"' . str_replace('"', '""', $row['middle_name']) . '",';
								echo '"' . str_replace('"', '""', $row['last_name']) . '",';
								echo '"' . str_replace('"', '""', $row['suffix']) . '",';
								echo '"' . str_replace('"', '""', getDisplayName($row['contact_id'])) . '",';
								echo '"' . str_replace('"', '""', $row['salutation']) . '",';
								echo '"' . str_replace('"', '""', $row['business_name']) . '",';
								echo '"' . str_replace('"', '""', $row['address_1']) . '",';
								echo '"' . str_replace('"', '""', $row['address_2']) . '",';
								echo '"' . str_replace('"', '""', $row['city']) . '",';
								echo '"' . str_replace('"', '""', $row['state']) . '",';
								echo '"' . str_replace('"', '""', $row['postal_code']) . '",';
								echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", $row['country_id'])) . '",';
								echo '"' . str_replace('"', '""', $row['email_address']) . '",';
								echo '"' . str_replace('"', '""', getFieldFromId("user_name", "users", "contact_id", $row['contact_id'])) . '",';
								echo '"' . str_replace('"', '""', date('m/d/y', strtotime($_POST['donation_date_from']))) . '",';
								echo '"' . str_replace('"', '""', date('m/d/y', strtotime($_POST['donation_date_to']))) . '",';
								echo '"' . str_replace('"', '""', number_format($row['gift_count'])) . '",';
								echo '"' . str_replace('"', '""', number_format($row['donation_total'], 2)) . '",';
								echo '"' . str_replace('"', '""', trim($row['gift_detail'])) . '"' . "\r\n";
							}
						}
						if (!empty($_POST['fragment_id'])) {
							$pdfCount++;
							if ($_GET['url_action'] == "create_report") {
								if (empty($pdfReceiptBatchId)) {
									$insertSet = executeQuery("insert into pdf_receipt_batches (client_id,pdf_receipt_batch_code,description,fragment_id,user_id,time_submitted) values " .
										"(?,?,?,?,?,now())", $GLOBALS['gClientId'], getRandomString(100), (empty($_POST['description']) ? "PDF Receipt" : $_POST['description']), $_POST['fragment_id'], $GLOBALS['gUserId']);
									$pdfReceiptBatchId = $insertSet['insert_id'];
								}
								$insertSet = executeQuery("insert into pdf_receipt_entries (pdf_receipt_batch_id,contact_id,parameters) values " .
									"(?,?,?)", $pdfReceiptBatchId, $row['contact_id'], jsonEncode($substitutions));
							}
						}
					}
					if ($_GET['url_action'] == "get_resend_count") {
						$returnArray['resend_message'] = (empty($emailCount) ? "No" : $emailCount) . " email" . ($emailCount == 1 ? "" : "s") . " will be sent. " .
							(empty($downloadCount) ? "No" : $downloadCount) . " CSV receipt" . ($downloadCount == 1 ? "" : "s") . " will be downloaded. " .
							(empty($pdfCount) ? "No" : $pdfCount) . " PDF receipt" . ($pdfCount == 1 ? "" : "s") . " will be created.";
						ajaxResponse($returnArray);
						break;
					} else {
						$doneMessage = (empty($emailCount) ? "No" : $emailCount) . " email" . ($emailCount == 1 ? "" : "s") . " are being sent. " .
							(empty($failedEmailCount) ? "No" : $failedEmailCount) . " failed email" . ($failedEmailCount == 1 ? "" : "s") . ". " .
							(empty($downloadCount) ? "No" : $downloadCount) . " CSV receipt" . ($downloadCount == 1 ? "" : "s") . " were downloaded. " .
							(empty($pdfCount) ? "No" : $pdfCount) . " PDF receipt" . ($pdfCount == 1 ? "" : "s") . " were created.";
						addProgramLog($doneMessage);
					}
				} else {
					if (!empty($_POST['ignore_backouts'])) {
						$whereStatement .= (empty($whereStatement) ? "" : " and ") . "associated_donation_id is null";
					}
					if (!empty($_POST['receipt_sent'])) {
						if (!empty($whereStatement)) {
							$whereStatement .= " and ";
						}
						$whereStatement .= "donations.receipt_sent is null";
					}
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(donations.donation_batch_id is null or donations.donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null))";

					if ($_GET['url_action'] == "get_resend_count") {
						$resultSet = executeQuery("select * from donations where donations.client_id = ? and receipt_sent is not null" .
							(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by contact_id,donation_id", $parameters);
						$resendCount = $resultSet['row_count'];
						if ($resendCount == 0) {
							$returnArray['resend_message'] = "No Receipts will be resent";
						} else {
							$returnArray['resend_message'] = "Running these receipts will result in " . $resendCount . " receipt" . ($resendCount == 1 ? "" : "s") . (empty($_POST['force_download']) ? " being RESENT" : " that have already been sent being download") . ". Are you sure?";
						}
						ajaxResponse($returnArray);
						break;
					}

					$datetime = date("ymdHis");
					header("Content-Type: text/csv");
					header("Content-Disposition: attachment; filename=\"receipts." . $datetime . ".csv\"");
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');

					echo '"ContactId","Title","First","Middle","Last","Suffix","FullName","Salutation","BusinessName","Address1","Address2","City","State","PostalCode","Country",' .
						'"PhoneNumber1","PhoneDescription1","PhoneNumber2","PhoneDescription2","PhoneNumber3","PhoneDescription3","PhoneNumber4","PhoneDescription4","PhoneNumber5","PhoneDescription5","ReceiptNumber","DonationDate","DesignationCode",' .
						'"Designation","PaymentMethod","ReferenceNumber","GiftAmount","Month1Donations","Month2Donations","Month3Donations","Month4Donations","Month5Donations","Month6Donations",' .
						'"Month7Donations","Month8Donations","Month9Donations","Month10Donations","Month11Donations","Month12Donations","YearToDateDonations","LastYearDonations"' . "\n";

					$resultSet = executeQuery("select * from donations where donations.client_id = ?" .
						(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by contact_id,donation_id", $parameters);
					$downloadCount = 0;
					$emailCount = 0;
					while ($row = getNextRow($resultSet)) {
						$receiptProcessed = Donations::processDonationReceipt($row, array("resend_receipts" => empty($_POST['receipt_sent']), "force_download" => $_POST['force_download']));
						if ($receiptProcessed === false) {
							continue;
						} else if ($receiptProcessed !== true) {
							echo $receiptProcessed;
							$downloadCount++;
						} else {
							$emailCount++;
						}
					}
					$doneMessage = (empty($emailCount) ? "No" : $emailCount) . " email" . ($emailCount == 1 ? "" : "s") . " are being sent. " .
						(empty($downloadCount) ? "No" : $downloadCount) . " CSV receipt" . ($downloadCount == 1 ? "" : "s") . " were downloaded.";
					addProgramLog($doneMessage);
				}
				exit;
		}
	}

	function mainContent() {
		$receiptPolicyString = getPreference("RECEIPT_POLICY");
		$receiptPolicyParts = explode(" ", $receiptPolicyString);
		$receiptPolicy = array_shift($receiptPolicyParts);
		$policyStatement = "";
		switch ($receiptPolicy) {
			case "PAPER":
				$policyStatement = "Your receipt policy is that ALL receipts are printed and email receipts are never used.";
# all receipts that need to be created will be paper
				$paperQuery = "select count(*) from donations where donations.client_id = " . $GLOBALS['gClientId'] . " and receipt_sent is null and (donation_batch_id is null or donation_batch_id in " .
					"(select donation_batch_id from donation_batches where date_posted is not null))";
				$emailQuery = "";
				$alternatePaperQuery = "";
				$alternateEmailQuery = "";
				break;
			case "BOTH":
				$policyStatement = "Your receipt policy is that donations that are not in a batch (made online or by the automatic recurring process) will get an email receipt, if possible. Any receipt that is not emailed or that is for a donation in a batch will be printed.";
# Paper receipts:
#		exclude NO_RECEIPT
#		exclude receipts already run
#		donations in a batch that is posted and contact not tagged EMAIL_RECEIPT or email address is null
#		donations not in a batch and contact tagged PAPER_RECEIPT or email address is null
				$paperQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and " .
					"receipted_contact_id is null and contacts.contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'NO_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and " .
					"(donation_batch_id is not null and donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null) and " .
					"(contacts.contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'EMAIL_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) or email_address is null) or (donation_batch_id is null and " .
					"(contacts.contact_id in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'PAPER_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) or email_address is null)))";
				$alternatePaperQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and " .
					"receipted_contact_id is not null and receipted_contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'NO_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and " .
					"(donation_batch_id is not null and donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null) and " .
					"(receipted_contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'EMAIL_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) or email_address is null) or (donation_batch_id is null and " .
					"(receipted_contact_id in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'PAPER_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) or email_address is null)))";
# Email Receipts:
#		exclude NO_RECEIPT
#		exclude receipts already run
#		donations in a batch that is posted and contact is tagged EMAIL_RECEIPT and email address is not null
#		donations not in a batch and contact not tagged PAPER_RECEIPT and email address is not null
				$emailQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and receipted_contact_id is null and " .
					"contacts.contact_id not in (select contact_id from contact_categories where " .
					"category_id = (select category_id from categories where category_code = 'NO_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and " .
					"(donation_batch_id is not null and donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null) and (contacts.contact_id in (select contact_id " .
					"from contact_categories where category_id = (select category_id from categories where category_code = 'EMAIL_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and " .
					"email_address is not null) or (donation_batch_id is null and (contacts.contact_id not in (select contact_id " .
					"from contact_categories where category_id = (select category_id from categories where category_code = 'PAPER_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and email_address is not null)))";
				$alternateEmailQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and receipted_contact_id is not null and " .
					"receipted_contact_id not in (select contact_id from contact_categories where " .
					"category_id = (select category_id from categories where category_code = 'NO_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and " .
					"(donation_batch_id is not null and donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null) and (receipted_contact_id in (select contact_id " .
					"from contact_categories where category_id = (select category_id from categories where category_code = 'EMAIL_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and " .
					"email_address is not null) or (donation_batch_id is null and (receipted_contact_id not in (select contact_id " .
					"from contact_categories where category_id = (select category_id from categories where category_code = 'PAPER_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and email_address is not null)))";;
				break;
			default:
				$policyStatement = "Your receipt policy is that receipts will always be emailed when possible. If a receipt cannot be emailed, it will be printed.";
# Paper receipts:
#		exclude NO_RECEIPT
#		exclude receipts already run
#		donations in a batch that is posted and contact tagged PAPER_RECEIPT or email address is null
#		donations not in a batch and contact tagged PAPER_RECEIPT or email address is null
				$paperQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and receipted_contact_id is null and " .
					"contacts.contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where category_code = 'NO_RECEIPT' and " .
					"client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and " .
					"(donation_batch_id is null or donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null)) and " .
					"(contacts.contact_id in (select contact_id from contact_categories where category_id = (select category_id from categories where category_code = 'PAPER_RECEIPT' and " .
					"client_id = " . $GLOBALS['gClientId'] . ")) or email_address is null)";
				$alternatePaperQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and receipted_contact_id is not null and " .
					"receipted_contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where category_code = 'NO_RECEIPT' and " .
					"client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and " .
					"(donation_batch_id is null or donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null)) and " .
					"(receipted_contact_id in (select contact_id from contact_categories where category_id = (select category_id from categories where category_code = 'PAPER_RECEIPT' and " .
					"client_id = " . $GLOBALS['gClientId'] . ")) or email_address is null)";
# Email Receipts:
#		exclude NO_RECEIPT
#		exclude receipts already run
#		donations in a batch that is posted and contact is not tagged PAPER_RECEIPT and email address is not null
#		donations not in a batch and contact not tagged PAPER_RECEIPT and email address is not null
				$emailQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and receipted_contact_id is null and " .
					"contacts.contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where category_code = 'NO_RECEIPT' and " .
					"client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and (donation_batch_id is null or donation_batch_id in (select donation_batch_id from donation_batches where " .
					"date_posted is not null)) and (contacts.contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'PAPER_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and email_address is not null)";
				$alternateEmailQuery = "select count(*) from donations join contacts using (contact_id) where donations.client_id = " . $GLOBALS['gClientId'] . " and receipted_contact_id is not null and " .
					"receipted_contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where category_code = 'NO_RECEIPT' and " .
					"client_id = " . $GLOBALS['gClientId'] . ")) and receipt_sent is null and (donation_batch_id is null or donation_batch_id in (select donation_batch_id from donation_batches where " .
					"date_posted is not null)) and (receipted_contact_id not in (select contact_id from contact_categories where category_id = (select category_id from categories where " .
					"category_code = 'PAPER_RECEIPT' and client_id = " . $GLOBALS['gClientId'] . ")) and email_address is not null)";
				break;
		}
		$totalCount = 0;
		$paperCount = 0;
		$emailCount = 0;
		$resultSet = executeQuery("select count(*) from donations where client_id = ? and receipt_sent is null and (donation_batch_id is null or donation_batch_id in (select donation_batch_id from donation_batches where date_posted is not null))", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$totalCount = $row['count(*)'];
		}
		$resultSet = executeQuery($paperQuery);
		if ($row = getNextRow($resultSet)) {
			$paperCount = $row['count(*)'];
		}
		$resultSet = executeQuery($alternatePaperQuery);
		if ($row = getNextRow($resultSet)) {
			$paperCount += $row['count(*)'];
		}
		$resultSet = executeQuery($emailQuery);
		if ($row = getNextRow($resultSet)) {
			$emailCount = $row['count(*)'];
		}
		$resultSet = executeQuery($alternateEmailQuery);
		if ($row = getNextRow($resultSet)) {
			$emailCount += $row['count(*)'];
		}
		?>
        <h1>Generate Receipts</h1>
        <p><?= $policyStatement ?></p>
        <p>There are <span class="highlighted-text"><?= $totalCount ?></span> donation<?= ($totalCount == 1 ? "" : "s") ?> whose receipt has not been sent. Some of these will be for donors who don't wish to receive a receipt.</p>
        <p>Following policy, there are <span class="highlighted-text"><?= $emailCount ?></span> receipt<?= ($emailCount == 1 ? "" : "s") ?> waiting to be sent by email and <span class="highlighted-text"><?= $paperCount ?></span> receipt<?= ($paperCount == 1 ? "" : "s") ?> waiting to be printed.</p>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_receipt_type_row">
                    <label for="receipt_type">Receipt Type</label>
                    <select tabindex="10" id="receipt_type" name="receipt_type">
                        <option value="individual">Single Gift</option>
                        <option value="summary">Summary</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_contact_id_row">
                    <label for="contact_id">Donor ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="contact_id" name="contact_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_selected_donors_row">
                    <input tabindex="10" type="checkbox" id="selected_donors" name="selected_donors"><label class="checkbox-label" for="selected_donors">Include Selected Contacts</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_donation_id_row">
                    <label for="donation_id">Donation ID</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="donation_id" name="donation_id">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_donation_date_row">
                    <label for="donation_date_from">Donation Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date],required] datepicker" data-conditional-required="$('#receipt_type').val() == 'summary'" id="donation_date_from" name="donation_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date],required] datepicker" data-conditional-required="$('#receipt_type').val() == 'summary'" id="donation_date_to" name="donation_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_amount_row">
                    <label for="amount_from">Donation Amount: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="amount_from" name="amount_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="amount_to" name="amount_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_total_giving_row">
                    <label for="total_giving_from">Total Giving: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="total_giving_from" name="total_giving_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="total_giving_to" name="total_giving_to">
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

                <div class="basic-form-line" id="_batch_number_row">
                    <label for="batch_number_from">Batch Number: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_from" name="batch_number_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[integer]]" id="batch_number_to" name="batch_number_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_no_batch_row">
                    <label for="no_batch"></label>
                    <input tabindex="10" type="checkbox" value="1" id="no_batch" name="no_batch"><label class="checkbox-label" for="no_batch">Not in a batch</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line receipt-parameter individual-parameter" id="_receipt_sent_row">
                    <label for="receipt_sent"></label>
                    <input tabindex="10" type="checkbox" value="1" id="receipt_sent" name="receipt_sent" checked="checked"><label class="checkbox-label" for="receipt_sent">Ignore donations whose receipt is already sent</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line receipt-parameter individual-parameter" id="_ignore_backouts_row">
                    <label for="ignore_backouts"></label>
                    <input tabindex="10" type="checkbox" value="1" id="ignore_backouts" name="ignore_backouts"><label class="checkbox-label" for="ignore_backouts">Ignore backouts</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_tax_deductible_row">
                    <label for="only_tax_deductible"></label>
                    <input tabindex="10" type="checkbox" value="1" id="only_tax_deductible" name="only_tax_deductible" checked="checked"><label class="checkbox-label" for="only_tax_deductible">Only include tax deductible designations</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_force_download_row">
                    <label for="force_download"></label>
                    <input tabindex="10" type="checkbox" value="1" id="force_download" name="force_download"><label class="checkbox-label" for="force_download">Send NO emails, Download all</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line receipt-parameter summary-parameter" id="_email_id_row">
                    <label for="email_id">Summary Email Receipt</label>
                    <select tabindex="10" id="email_id" name="email_id" class="">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from emails where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['email_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line receipt-parameter summary-parameter" id="_category_id_row">
                    <label for="category_id">Summary Category</label>
                    <select tabindex="10" id="category_id" name="category_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['category_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label">added to contacts who get summary receipt</span><span class='field-error-text'></span></div>
                </div>

                <p class='summary-parameter'>For Year-End Receipts, it is strongly encouraged to use the Year-End Receipt Report.</p>

                <div class="basic-form-line receipt-parameter summary-parameter" id="_fragment_id_row">
                    <label for="fragment_id">Fragment for PDF Receipts</label>
                    <select tabindex="10" id="fragment_id" name="fragment_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from fragments where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['fragment_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label">Receipts will be created and attached to contact (Summary only)</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line receipt-parameter summary-parameter" id="_description_row">
                    <label for="filename">File Description for PDF Receipts</label>
                    <input class="validate[required]" data-conditional-required="$('#receipt_type').val() == 'summary' && !empty($('#fragment_id').val())" tabindex="10" type="text" size="40" maxlength="60" id="description" name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <p class="error-message"></p>

                <div class="basic-form-line">
                    <input type="hidden" id="confirm_resend" name="confirm_resend" value="">
                    <button tabindex="10" id="create_report">Create Receipts</button>
                    <button tabindex="10" class="hidden" id="confirm_resend_button">Confirm</button>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#receipt_type").change(function () {
                $(".receipt-parameter").addClass("hidden");
                $("." + $(this).val() + "-parameter").removeClass("hidden");
            }).trigger("change");
            $(document).on("tap click", ".basic-form-line", function () {
                displayInfoMessage("");
                $("#create_report").removeClass("hidden");
                $("#confirm_resend_button").addClass("hidden");
            });
            $(document).on("tap click", "#create_report", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    if (($("#receipt_type").val() == "individual" && !$("#receipt_sent").prop("checked")) || $("#receipt_type").val() == "summary") {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_resend_count", $("#_report_form").serialize(), function(returnArray) {
                            if ("resend_message" in returnArray) {
                                displayInfoMessage(returnArray['resend_message'], false);
                                $("#create_report").addClass("hidden");
                                $("#confirm_resend_button").removeClass("hidden");
                            }
                        });
                    } else {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    }
                }
                return false;
            });
            $(document).on("tap click", "#confirm_resend_button", function () {
                displayInfoMessage("");
                $("#create_report").removeClass("hidden");
                $("#confirm_resend_button").addClass("hidden");
                if ($("#_report_form").validationEngine("validate")) {
                    $("#confirm_resend").val("1");
                    $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #report_parameters {
                margin-top: 40px;
            }

            p.highlighted-text {
                font-size: 16px;
            }
        </style>
		<?php
	}
}

$pageObject = new ReceiptReportPage();
$pageObject->displayPage();
