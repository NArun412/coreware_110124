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

class Donations {

	public static function getDonationFee($parameters) {
		if (empty($parameters['designation_id'])) {
			$GLOBALS['gPrimaryDatabase']->logError("Donation fee attempted for empty donation");
			return 0;
		}
		$donationFeeArray = array();
		$resultSet = executeQuery("select * from donation_fees where client_id = ? order by sequence_number", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$donationFeeArray[] = $row;
		}
		freeResult($resultSet);
		$designationGroups = array();
		$resultSet = executeQuery("select * from designation_group_links where designation_id = ?", $parameters['designation_id']);
		while ($row = getNextRow($resultSet)) {
			$designationGroups[] = $row['designation_group_id'];
		}
		$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $parameters['payment_method_id']);
		$donationFee = 0;
		foreach ($donationFeeArray as $feeRow) {
			$feeUsed = false;
			switch ($feeRow['comparator']) {
				case (">"):
					if ($parameters['amount'] > $feeRow['amount']) {
						$feeUsed = true;
					}
					break;
				case (">="):
				case ("=>"):
					if ($parameters['amount'] >= $feeRow['amount']) {
						$feeUsed = true;
					}
					break;
				case ("<"):
					if ($parameters['amount'] < $feeRow['amount']) {
						$feeUsed = true;
					}
					break;
				case ("<="):
				case ("=<"):
					if ($parameters['amount'] <= $feeRow['amount']) {
						$feeUsed = true;
					}
					break;
				case ("="):
					if ($parameters['amount'] == $feeRow['amount']) {
						$feeUsed = true;
					}
					break;
			}
			if ($feeUsed && !empty($feeRow['payment_method_type_id'])) {
				$feeUsed = $feeRow['payment_method_type_id'] == $paymentMethodTypeId;
			}
			if ($feeUsed && !empty($feeRow['payment_method_id'])) {
				$feeUsed = $feeRow['payment_method_id'] == $parameters['payment_method_id'];
			}
			if ($feeUsed && !empty($feeRow['designation_group_id'])) {
				$feeUsed = (in_array($feeRow['designation_group_id'], $designationGroups));
			}
			if ($feeUsed && !empty($feeRow['designation_id'])) {
				$feeUsed = $feeRow['designation_id'] == $parameters['designation_id'];
			}
			if ($feeUsed) {
				if (!empty($feeRow['fee_amount'])) {
					$donationFee += $feeRow['fee_amount'];
				}
				if (!empty($feeRow['fee_percent'])) {
					$donationFee += round($parameters['amount'] * ($feeRow['fee_percent'] / 100), 2);
				}
				if (!empty($feeRow['minimum_fee']) && $donationFee < $feeRow['minimum_fee']) {
					$donationFee = $feeRow['minimum_fee'];
				}
				if (!empty($feeRow['maximum_fee']) && $donationFee > $feeRow['maximum_fee']) {
					$donationFee = $feeRow['maximum_fee'];
				}
				break;
			}
		}
		return $donationFee;
	}

	public static function getFeeMessage($designationId = "") {
		$designationGroupIds = false;
		if (!empty($designationId)) {
			$designationGroupIds = array();
			$resultSet = executeQuery("select * from designation_group_links where designation_id = ?", $designationId);
			while ($row = getNextRow($resultSet)) {
				$designationGroupIds[] = $row['designation_group_id'];
			}
		}
		$lineArray = array();
		$feeArray = array();
		$lastSequenceNumber = 0;
		$parameters = array($GLOBALS['gClientId']);
		$resultSet = executeQuery("select * from donation_fees where client_id = ? order by sequence_number", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if ($designationGroupIds !== false && !empty($row['designation_group_id']) && !in_array($row['designation_group_id'], $designationGroupIds)) {
				continue;
			}
			if (!empty($row['designation_id']) && !empty($designationId) && $designationId != $row['designation_id']) {
				continue;
			}
			if (!empty($lastSequenceNumber) && $feeArray[$lastSequenceNumber]['amount'] == $row['amount'] && $feeArray[$lastSequenceNumber]['comparator'] == $row['comparator'] &&
				$feeArray[$lastSequenceNumber]['fee_amount'] == $row['fee_amount'] && $feeArray[$lastSequenceNumber]['fee_percent'] == $row['fee_percent'] &&
				$feeArray[$lastSequenceNumber]['minimum_fee'] == $row['minimum_fee'] && $feeArray[$lastSequenceNumber]['maximum_fee'] == $row['maximum_fee']) {
				if (!empty($row['payment_method_id'])) {
					$feeArray[$lastSequenceNumber]['payment_method_ids'][] = $row['payment_method_id'];
				}
				if (!empty($row['payment_method_type_id'])) {
					$typeSet = executeQuery("select * from payment_methods where client_id = ? and payment_method_type_id = ?", $GLOBALS['gClientId'], $row['payment_method_type_id']);
					while ($typeRow = getNextRow($typeSet)) {
						$feeArray[$lastSequenceNumber]['payment_method_ids'][] = $typeRow['payment_method_id'];
					}
				}
			} else {
				$lastSequenceNumber = $row['sequence_number'];
				$feeArray[$lastSequenceNumber] = array("amount" => $row['amount'], "comparator" => $row['comparator'], "fee_amount" => $row['fee_amount'], "designation_group_id" => $row['designation_group_id'],
					"fee_percent" => $row['fee_percent'], "minimum_fee" => $row['minimum_fee'], "maximum_fee" => $row['maximum_fee'], "payment_method_ids" => array($row['payment_method_id']));
				if (!empty($row['payment_method_type_id'])) {
					$typeSet = executeQuery("select * from payment_methods where client_id = ? and payment_method_type_id = ?", $GLOBALS['gClientId'], $row['payment_method_type_id']);
					while ($typeRow = getNextRow($typeSet)) {
						$feeArray[$lastSequenceNumber]['payment_method_ids'][] = $typeRow['payment_method_id'];
					}
				}
			}
		}
		freeResult($resultSet);
		$usedCriteria = array();
		foreach ($feeArray as $row) {
			$thisLine = "";
			if (empty($designationId) && !empty($row['designation_group_id'])) {
				$thisLine = "Designation Group is " . getFieldFromId("description", "designation_groups", "designation_group_id", $row['designation_group_id']);
			}
			if (!empty($row['amount'])) {
				if (!empty($thisLine)) {
					$thisLine .= " and ";
				}
				switch ($row['comparator']) {
					case (">"):
						$thisLine .= "Amount greater than " . number_format($row['amount'], 2);
						break;
					case (">="):
						$thisLine .= "Amount greater than or equal " . number_format($row['amount'], 2);
						break;
					case ("<"):
						$thisLine .= "Amount less than " . number_format($row['amount'], 2);
						break;
					case ("<="):
						$thisLine .= "Amount less than or equal " . number_format($row['amount'], 2);
						break;
					case ("="):
						$thisLine .= "Amount equal " . number_format($row['amount'], 2);
						break;
				}
			}
			$oneAdded = false;
			foreach ($row['payment_method_ids'] as $paymentMethodId) {
				if (empty($paymentMethodId)) {
					continue;
				}
				if (!$oneAdded) {
					if (!empty($thisLine)) {
						$thisLine .= " and ";
					}
					$thisLine .= "Payment method is " . getFieldFromId("description", "payment_methods", "payment_method_id", $paymentMethodId);
				} else {
					$thisLine .= ", " . getFieldFromId("description", "payment_methods", "payment_method_id", $paymentMethodId);
				}
				$oneAdded = true;
			}
			if (in_array($thisLine, $usedCriteria)) {
				continue;
			}
			$usedCriteria[] = $thisLine;
			if (empty($thisLine)) {
				$thisLine = "All Remaining Gifts";
			}
			if (!empty($thisLine)) {
				$thisLine .= ": ";
			}
			$feeAdded = false;
			if ($row['fee_amount'] > 0) {
				$thisLine .= "Flat fee of " . number_format($row['fee_amount'], 2);
				$feeAdded = true;
			}
			if ($row['fee_percent'] > 0) {
				if ($feeAdded) {
					$thisLine .= " plus ";
				}
				$thisLine .= number_format($row['fee_percent'], 2) . " percent of amount";
				$feeAdded = true;
			}
			if ($row['minimum_fee'] > 0) {
				if ($feeAdded) {
					$thisLine .= ", ";
				}
				$thisLine .= "minimum fee of " . number_format($row['minimum_fee'], 2);
				$feeAdded = true;
			}
			if ($row['maximum_fee'] > 0) {
				if ($feeAdded) {
					$thisLine .= ", ";
				}
				$thisLine .= "maximum fee of " . number_format($row['maximum_fee'], 2);
				$feeAdded = true;
			}
			if (!$feeAdded) {
				$thisLine .= "No Fee";
			}
			$thisLine .= "<br/>\n";
			if (!in_array($thisLine, $lineArray)) {
				$lineArray[] = $thisLine;
			}
		}
		$feeMessage = "";
		foreach ($lineArray as $thisLine) {
			$feeMessage .= $thisLine;
		}
		return $feeMessage;
	}

	public static function processYearEndReceipts($parameters) {
		$fileTagId = getFieldFromId("file_tag_id", "file_tags", "file_tag_code", "YEAR_END_RECEIPT_" . $parameters['year']);
		if (empty($fileTagId)) {
			return "File tag doesn't exist for client " . $GLOBALS['gClientId'];
		}
		$emailRow = getRowFromId("emails", "email_code", "YEAR_END_RECEIPT_" . $parameters['year']);
		if (empty($emailRow)) {
			$emailRow = getRowFromId("emails", "email_code", "YEAR_END_RECEIPT");
		}
		if (empty($emailRow)) {
			return "Year-End Receipt email doesn't exist for client " . $GLOBALS['gClientId'];
		}
		$fragmentRow = getRowFromId("fragments", "fragment_code", "YEAR_END_RECEIPT_" . $parameters['year']);
		if (empty($fragmentRow)) {
			$fragmentRow = getRowFromId("fragments", "fragment_code", "YEAR_END_RECEIPT");
		}
		if (empty($fragmentRow)) {
			return "Year-End Receipt fragment doesn't exist for client " . $GLOBALS['gClientId'];
		}
		$parameters['user_id'] = getFieldFromId("user_id", "users", "user_id", $parameters['user_id'], "inactive = 0 and (administrator_flag = 1 or superuser_flag = 1)");
		if (empty($parameters['user_id'])) {
			return "Year-End Receipt cannot be run anonymously - client " . $GLOBALS['gClientId'];
		}

		$countsOnly = (!empty($parameters['counts_only']));
		if ($countsOnly) {
			$parameters['test_mode'] = "";
		}

		if (!$countsOnly && !empty($parameters['remove_existing'])) {
			$fileIds = array();
			$fileSet = executeQuery("select file_id from contact_files where contact_id in (select contact_id from contacts where client_id = ?) and " .
				"file_id in (select file_id from files where client_id = ? and file_tag_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $fileTagId);
			while ($fileRow = getNextRow($fileSet)) {
				$fileIds[] = $fileRow['file_id'];
			}
			executeQuery("delete from contact_files where contact_id in (select contact_id from contacts where client_id = ?) and " .
				"file_id in (select file_id from files where client_id = ? and file_tag_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $fileTagId);
			if (!empty($fileIds)) {
				foreach ($fileIds as $fileId) {
					executeQuery("delete ignore from files where file_id = ?", $fileId);
				}
			}
		}
		if (empty($parameters['total_giving'])) {
			$parameters['total_giving'] = 0;
		}
		if (empty($parameters['download_amount'])) {
			$parameters['download_amount'] = false;
		}
		$giftDetailLine = getPreference("RECEIPT_DETAIL_LINE");
		if (empty($giftDetailLine)) {
			$giftDetailLine = "%donation_date%, %designation_code% - %designation_description%, %amount%";
		}
		$thisPage = new Page();
		$startDate = $parameters['year'] . "-01-01";
		$endDate = $parameters['year'] . "-12-31";
		$emailCount = 0;
		$fileCount = 0;
		$downloadCount = 0;
		$skipCount = 0;
		$testMode = !empty($parameters['test_mode']);
		$delayMinutes = $parameters['email_minutes'];
		if (empty($parameters['email_minutes']) || $testMode) {
			$delayMinutes = 0;
		} else if (!is_numeric($parameters['email_minutes'])) {
			$delayMinutes = 1;
		}
		$userContactId = Contact::getUserContactId($parameters['user_id']);
		$userEmailAddress = getFieldFromId("email_address", "contacts", "contact_id", $userContactId);
		$csvContent = '"ContactId","Title","First","Middle","Last","Suffix","FullName","Salutation","BusinessName","Address1","Address2","City","State",' .
			'"PostalCode","Country","EmailAddress","UserName","StartDonationDate","EndDonationDate","GiftCount","TotalGifts","GiftDetail"' . "\n";
		if ($countsOnly) {
			$queryStatement = "select coalesce(receipted_contact_id,contact_id) as use_contact_id,(select email_address from contacts where contact_id = coalesce(donations.receipted_contact_id,donations.contact_id)) as email_address,sum(amount) as total_donations from donations where " .
				(empty($parameters['contact_id']) ? "" : "contact_id = ? and ") . "client_id = ? and associated_donation_id is null and " .
				"donation_date between ? and ? and designation_id in (select designation_id from designations where not_tax_deductible = 0) " .
				(empty($parameters['remove_existing']) ? " and ((receipted_contact_id is not null and receipted_contact_id not in (select contact_id from contact_files where " .
					"file_id in (select file_id from files where file_tag_id = " . $fileTagId . "))) or (receipted_contact_id is null and contact_id not in (select contact_id from contact_files where " .
					"file_id in (select file_id from files where file_tag_id = " . $fileTagId . "))))" : "") . " group by use_contact_id,email_address";
			$queryParameters = array($GLOBALS['gClientId'], $startDate, $endDate);
			if (!empty($parameters['contact_id'])) {
				array_unshift($queryParameters, $parameters['contact_id']);
			}
		} else {
			$queryStatement = "select * from contacts where " . (empty($parameters['contact_id']) ? "" : "contact_id = ? and ") .
				($testMode ? "first_name is not null and last_name is not null and address_1 is not null and " : "") .
				"client_id = ? and (contact_id in (select contact_id from donations where client_id = ? and receipted_contact_id is null and associated_donation_id is null and donation_date between ? and ? and " .
				"designation_id in (select designation_id from designations where not_tax_deductible = 0)) or contact_id in (select receipted_contact_id from donations where client_id = ? and receipted_contact_id is not null and associated_donation_id is null and donation_date between ? and ? and " .
				"designation_id in (select designation_id from designations where not_tax_deductible = 0)))" . (empty($parameters['remove_existing']) && !$testMode ? " and " .
					"contact_id not in (select contact_id from contact_files where file_id in (select file_id from files where file_tag_id = " . $fileTagId . "))" : "");
			$queryParameters = array($GLOBALS['gClientId'], $GLOBALS['gClientId'], $startDate, $endDate, $GLOBALS['gClientId'], $startDate, $endDate);
			if (!empty($parameters['contact_id'])) {
				array_unshift($queryParameters, $parameters['contact_id']);
			}
		}
		$contactSet = executeQuery($queryStatement, $queryParameters);
		addDebugLog($queryStatement);
		addDebugLog($contactSet['row_count']);
		$contactCount = $contactSet['row_count'];
		while ($contactRow = getNextRow($contactSet)) {
			if ($testMode && $emailCount > 0 && $fileCount > 0 && $downloadCount > 0) {
				break;
			}
			$donationDetails = "";
			$donationDetailsTable = "<table id='donation_details' style='width: 600px'><tr><th style='text-align: left; padding: 5px 20px;'>Date</th><th style='text-align: left; padding: 5px 20px;'>Designation</th><th style='text-align: left; padding: 5px 20px;'>Amount</th></tr>";
			$giftCount = 0;
			$donationTotal = 0;
			if ($countsOnly) {
				$donationTotal = $contactRow['total_donations'];
				if (empty($donationTotal)) {
					$donationTotal = 0;
				}
			} else {
				$donationSet = executeQuery("select * from donations where client_id = ? and ((contact_id = ? and receipted_contact_id is null) or (receipted_contact_id = ? and " .
					"receipted_contact_id is not null)) and associated_donation_id is null and donation_date between ? and ? and designation_id in (select designation_id from designations where not_tax_deductible = 0)",
					$GLOBALS['gClientId'], $contactRow['contact_id'], $contactRow['contact_id'], $startDate, $endDate);
				while ($donationRow = getNextRow($donationSet)) {
					$designationFields = getMultipleFieldsFromId(array("description", "designation_code"), "designations", "designation_id", $donationRow['designation_id']);
					$donationRow["designation_description"] = $designationFields["description"];
					$donationRow["designation_code"] = $designationFields['designation_code'];
					$thisDetailLine = $giftDetailLine;
					foreach ($donationRow as $fieldName => $fieldValue) {
						if ($fieldName == "donation_date") {
							$fieldValue = date("m/d/Y", strtotime($fieldValue));
						}
						$thisDetailLine = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $thisDetailLine);
					}
					$donationDetails .= (empty($donationDetails) ? "" : "\n") . $thisDetailLine;
					$donationDetailsTable .= "<tr><td style='padding: 5px 20px;'>" . date("m/d/Y", strtotime($donationRow['donation_date'])) . "</td><td style='padding: 5px 20px;'>" . $donationRow['designation_code'] . " - " . $donationRow['designation_description'] . "</td><td style='padding: 5px 20px;'>" . number_format($donationRow['amount'], 2, ".", ",") . "</td></tr>";
					$giftCount++;
					$donationTotal += $donationRow['amount'];
				}
				$donationDetailsTable .= "</table>";
			}
			if ($donationTotal <= 0) {
				continue;
			}
			if (!$testMode && $donationTotal < $parameters['total_giving']) {
				$skipCount++;
				continue;
			}
			$receiptSent = false;
			$substitutions = array("contact_id" => $contactRow['contact_id'],
				"title" => $contactRow['title'],
				"first_name" => $contactRow['first_name'],
				"middle_name" => $contactRow['middle_name'],
				"last_name" => $contactRow['last_name'],
				"suffix" => $contactRow['suffix'],
				"full_name" => getDisplayName($contactRow['contact_id'], array("dont_use_company" => true, "include_title" => true)),
				"salutation" => (empty($contactRow['salutation']) ? generateSalutation($contactRow) : $contactRow['salutation']),
				"business_name" => $contactRow['business_name'],
				"address_1" => $contactRow['address_1'],
				"address_2" => $contactRow['address_2'],
				"city" => $contactRow['city'],
				"state" => $contactRow['state'],
				"postal_code" => $contactRow['postal_code'],
				"country" => ($countsOnly ? "" : getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id'])),
				"email_address" => $contactRow['email_address'],
				"donation_date_from" => date('m/d/y', strtotime($startDate)),
				"donation_date_to" => date('m/d/y', strtotime($endDate)),
				"donation_year" => date('Y', strtotime($startDate)),
				"gift_count" => $giftCount,
				"donation_total" => number_format($donationTotal, 2),
				"gift_detail_table" => $donationDetailsTable,
				"gift_detail" => str_replace("\n", "<br>", str_replace("\r", "<br>", str_replace("\t", ", ", str_replace("\\t", ", ", $donationDetails)))));
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
				$addressBlock .= (empty($addressBlock) ? "" : "<br>") . ($countsOnly ? "" : getFieldFromId("country_name", "countries", "country_id", $substitutions['country_id']));
			}
			$substitutions['address_block'] = $addressBlock;

			$htmlContent = $fragmentRow['content'];
			$htmlContent = PlaceHolders::massageContent($htmlContent, $substitutions);
			if (!$countsOnly) {
				$htmlContent = $thisPage->replaceImageReferences($htmlContent);
			}
			$fileId = "";
			if (!$countsOnly && (!$testMode || $fileCount == 0)) {
				$fileId = outputPDF($htmlContent, array("create_file" => true, "filename" => "receipt.pdf", "description" => "Year-End Receipt for " . $parameters['year'] . " - " . (empty($contactRow['last_name']) ? $contactRow['contact_id'] : $contactRow['last_name']), "file_tag_id" => $fileTagId));
				if ($fileId) {
					executeQuery("insert into contact_files (contact_id,description,file_id) values (?,?,?)",
						($testMode ? $userContactId : $contactRow['contact_id']), "Year-End Receipt for " . $parameters['year'], $fileId);
					$fileCount++;
				} else {
					return "Failed to produce a file for client " . $GLOBALS['gClientId'];
				}
			}

			if (((strlen($parameters['both_amount']) > 0 && $donationTotal > $parameters['both_amount']) || (empty($parameters['download_amount']) || $donationTotal <= $parameters['download_amount'])) && empty($parameters['send_no_emails']) && !empty($contactRow['email_address'])) {
				if ($countsOnly) {
					$receiptSent = true;
					$emailCount++;
				} else if (!$testMode || $emailCount == 0) {
					$sendTime = date("Y-m-d H:i:s", strtotime("+ " . round($delayMinutes) . " minutes"));
					$delayMinutes += $parameters['email_minutes'];
					$emailParameters = array("email_id" => $emailRow['email_id'], "email_credential_code" => $parameters['email_credential_code'],
						"substitutions" => $substitutions, "email_address" => ($testMode ? $userEmailAddress : $contactRow['email_address']), "attachment_file_id" => $fileId, "send_immediately" => $testMode, "send_after" => $sendTime);
					$result = sendEmail($emailParameters);
					if ($result === true) {
						$receiptSent = true;
						$emailCount++;
					}
				}
			}

			if (!$receiptSent || (strlen($parameters['both_amount']) > 0 && $donationTotal > $parameters['both_amount']) || (!empty($parameters['download_amount']) && $donationTotal > $parameters['download_amount']) || !empty($parameters['include_all_csv']) || !empty($parameters['send_no_emails']) || empty($contactRow['email_address'])) {
				$downloadCount++;
				$csvContent .= '"' . $contactRow['contact_id'] . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['title']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['first_name']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['middle_name']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['last_name']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['suffix']) . '",';
				$csvContent .= '"' . str_replace('"', '""', ($countsOnly ? "" : getDisplayName($contactRow['contact_id']))) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['salutation']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['business_name']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['address_1']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['address_2']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['city']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['state']) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['postal_code']) . '",';
				$csvContent .= '"' . str_replace('"', '""', ($countsOnly ? "" : getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']))) . '",';
				$csvContent .= '"' . str_replace('"', '""', $contactRow['email_address']) . '",';
				$csvContent .= '"' . str_replace('"', '""', ($countsOnly ? "" : getFieldFromId("user_name", "users", "contact_id", $contactRow['contact_id']))) . '",';
				$csvContent .= '"' . str_replace('"', '""', date('m/d/y', strtotime($startDate))) . '",';
				$csvContent .= '"' . str_replace('"', '""', date('m/d/y', strtotime($endDate))) . '",';
				$csvContent .= '"' . str_replace('"', '""', number_format($giftCount)) . '",';
				$csvContent .= '"' . str_replace('"', '""', number_format($donationTotal, 2)) . '",';
				$csvContent .= '"' . str_replace('"', '""', trim($donationDetails)) . '"' . "\r\n";
			}
		}
		$content = ($testMode ? "TEST MODE: " : "") . "Year-End receipts are created for " . $parameters['year'] . "\n\n" . $emailCount . " emails " . ($testMode ? "would be " : "") . "sent\n" . $downloadCount . " receipts included in CSV download, " . $fileCount . " contact files created\n";
		$fileId = "";
		if ($downloadCount > 0 && !$countsOnly) {
			$fileId = createFile(array("filename" => "receipts.csv", "file_content" => $csvContent));
		}
		if (!$countsOnly) {
			createUserNotification($parameters['user_id'], "Year-End Receipt Process Done", $content, $fileId);
		}
		return array("email_count" => $emailCount, "download_count" => $downloadCount, "contact_count" => $contactCount, "skip_count" => $skipCount);
	}

	public static function processDonation($donationId) {
		$donationRow = getRowFromId("donations", "donation_id", $donationId);
		if (empty($donationRow)) {
			return;
		}
		$accountId = getFieldFromId("account_id", "credit_account_designations", "designation_id", $donationRow['designation_id'],
			"account_id in (select account_id from accounts where inactive = 0 and payment_method_id in (select payment_method_id from payment_methods where " .
			"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_ACCOUNT')))");
		if (!empty($accountId)) {
			executeQuery("insert into credit_account_log (account_id,description,amount) values (?,?,?)", $accountId,
				"Donation ID " . $donationId . " from " . getDisplayName($donationRow['contact_id']) . " for " . getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']),
				$donationRow['amount']);
			executeQuery("update accounts set credit_limit = credit_limit + ? where account_id = ?", $donationRow['amount'], $accountId);
		}
	}

	public static function processDonationReceipt($donationId, $parameters = array()) {
		if (is_array($donationId)) {
			$donationRow = $donationId;
			$donationId = $donationRow['donation_id'];
		} else {
			$donationRow = getRowFromId("donations", "donation_id", $donationId);
		}
		$recurringDonationRow = getRowFromId("recurring_donations", "recurring_donation_id", $donationRow['recurring_donation_id']);
		$receiptPolicyString = getPreference("RECEIPT_POLICY");
		$receiptPolicyParts = explode(" ", $receiptPolicyString);
		$receiptPolicy = strtoupper(array_shift($receiptPolicyParts));
		$noReceiptCategoryId = getFieldFromId("category_id", "categories", "category_code", "NO_RECEIPT");
		$paperReceiptCategoryId = getFieldFromId("category_id", "categories", "category_code", "PAPER_RECEIPT");
		$emailReceiptCategoryId = getFieldFromId("category_id", "categories", "category_code", "EMAIL_RECEIPT");

		$contactRow = Contact::getContact((empty($donationRow['receipted_contact_id']) ? $donationRow['contact_id'] : $donationRow['receipted_contact_id']));
		$donationRow = array_merge($contactRow, $donationRow);
		$contactId = $contactRow['contact_id'];
		$monthlyTotals = array();
		$yearToDateDonations = 0;

		$GLOBALS['gStartTime'] = getMilliseconds();

		$thisYear = date('Y', strtotime($donationRow['donation_date']));
		for ($x = 1; $x <= 12; $x++) {
			$monthlyTotals[$x] = 0;
		}
		$startDate = $thisYear . "-01-01";
		$endDate = date("Y-m-d", strtotime($donationRow['donation_date']));
		$resultSet1 = executeQuery("select * from donations where (receipted_contact_id = ? or contact_id = ?) and " .
			"exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id) and donation_date between ? and ?",
			$contactId, $contactId, $startDate, $endDate);
		while ($row1 = getNextRow($resultSet1)) {
			if (!empty($row1['receipted_contact_id']) && $row1['receipted_contact_id'] != $contactId) {
				continue;
			}
			$monthlyTotals[date("n", strtotime($row1['donation_date']))] += $row1['amount'];
			$yearToDateDonations += $row1['amount'];
		}

		$startDate = $thisYear - 1 . "-01-01";
		$endDate = $thisYear - 1 . "-12-31";
		$lastYearDonations = 0;
		$resultSet1 = executeQuery("select * from donations where (receipted_contact_id = ? or contact_id = ?) and " .
			"exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id) and donation_date between ? and ?",
			$contactId, $contactId, $startDate, $endDate);
		while ($row1 = getNextRow($resultSet1)) {
			if (!empty($row1['receipted_contact_id']) && $row1['receipted_contact_id'] != $contactId) {
				continue;
			}
			$lastYearDonations += $row1['amount'];
		}

		$designationDescription = getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']);
		$notTaxDeductible = getFieldFromId("not_tax_deductible", "designations", "designation_id", $donationRow['designation_id']);
		$passedInSubstitutions = $parameters['substitutions'];
		if (!is_array($passedInSubstitutions)) {
			$passedInSubstitutions = array();
		}
		if (empty($donationRow['project_name'])) {
			$projectName = "";
		} else {
			$projectLabel = getFieldFromId("project_label", "designation_types", "designation_type_id",
				getFieldFromId("designation_type_id", "designations", "designation_id", $donationRow['designation_id']));
			$designationProjectLabel = getFieldFromId("project_label", "designations", "designation_id", $donationRow['designation_id']);
			$projectLabel = (empty($designationProjectLabel) ? $projectLabel : $designationProjectLabel);
			$projectName = (empty($projectLabel) ? "Project" : $projectLabel) . ": " . $donationRow['project_name'] . "<br>";
		}
		if (empty($donationRow['notes'])) {
			$memo = "";
		} else {
			$memoLabel = getFieldFromId("memo_label", "designations", "designation_id", $donationRow['designation_id']);
			if (empty($memoLabel)) {
				$memo = $donationRow['notes'];
			} else {
				$memo = $memoLabel . ": " . $donationRow['notes'] . "<br>";
			}
		}

		$substitutions = array_merge($passedInSubstitutions, array("contact_id" => $contactId,
			"title" => $donationRow['title'],
			"first_name" => $donationRow['first_name'],
			"middle_name" => $donationRow['middle_name'],
			"last_name" => $donationRow['last_name'],
			"suffix" => $donationRow['suffix'],
			"full_name" => getDisplayName($contactRow['contact_id'], array("include_title" => true)),
			"salutation" => (empty($donationRow['salutation']) ? generateSalutation($donationRow) : $donationRow['salutation']),
			"business_name" => $donationRow['business_name'],
			"address_1" => $donationRow['address_1'],
			"address_2" => $donationRow['address_2'],
			"city" => $donationRow['city'],
			"state" => $donationRow['state'],
			"postal_code" => $donationRow['postal_code'],
			"country" => getFieldFromId("country_name", "countries", "country_id", $donationRow['country_id']),
			"receipt_number" => $donationRow['donation_id'],
			"donation_id" => $donationRow['donation_id'],
			"donation_date" => date('m/d/y', strtotime($donationRow['donation_date'])),
			"designation_code" => getFieldFromId("designation_code", "designations", "designation_id", $donationRow['designation_id']),
			"designation" => $designationDescription . (!empty($donationRow['anonymous_gift']) ? " (Anonymous)" : ""),
			"designation_description" => $designationDescription . (!empty($donationRow['anonymous_gift']) ? " (Anonymous)" : ""),
			"not_tax_deductible" => ($notTaxDeductible ? "NOT tax-deductible" : ""),
			"designation_alias" => getFieldFromId("alias", "designations", "designation_id", $donationRow['designation_id']),
			"payment_method" => getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']),
			"reference_number" => $donationRow['reference_number'],
			"gift_amount" => number_format($donationRow['amount'], 2),
			"amount" => number_format($donationRow['amount'], 2),
			"month_1_donations" => number_format($monthlyTotals[1], 2),
			"month_2_donations" => number_format($monthlyTotals[2], 2),
			"month_3_donations" => number_format($monthlyTotals[3], 2),
			"month_4_donations" => number_format($monthlyTotals[4], 2),
			"month_5_donations" => number_format($monthlyTotals[5], 2),
			"month_6_donations" => number_format($monthlyTotals[6], 2),
			"month_7_donations" => number_format($monthlyTotals[7], 2),
			"month_8_donations" => number_format($monthlyTotals[8], 2),
			"month_9_donations" => number_format($monthlyTotals[9], 2),
			"month_10_donations" => number_format($monthlyTotals[10], 2),
			"month_11_donations" => number_format($monthlyTotals[11], 2),
			"month_12_donations" => number_format($monthlyTotals[12], 2),
			"year_to_date_donations" => number_format($yearToDateDonations, 2),
			"last_year_donations" => number_format($lastYearDonations, 2),
			"project_name" => $projectName,
			"memo" => $memo));
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
		$substitutions['recurring_message'] = (empty($recurringDonationRow) ? "This is a one-time donation." : "Next recurring donation will be processed on " . date("m/d/Y", strtotime($recurringDonationRow['next_billing_date'])) . ".");
		$noReceipt = getFieldFromId("contact_category_id", "contact_categories", "category_id", $noReceiptCategoryId, "contact_id = ?", $contactId);
		$paperReceipt = getFieldFromId("contact_category_id", "contact_categories", "category_id", $paperReceiptCategoryId, "contact_id = ?", $contactId);
		$emailReceipt = getFieldFromId("contact_category_id", "contact_categories", "category_id", $emailReceiptCategoryId, "contact_id = ?", $contactId);

		$eReceiptEmailId = getFieldFromId("email_id", "designations", "designation_id", $donationRow['designation_id']);
		if (empty($eReceiptEmailId)) {
			$eReceiptEmailId = getFieldFromId("email_id", "designation_types", "designation_type_id",
				getFieldFromId("designation_type_id", "designations", "designation_id", $donationRow['designation_id']));
		}
		if (empty($eReceiptEmailId)) {
			$eReceiptEmailId = getFieldFromId("email_id", "emails", "email_code", "ERECEIPT", "inactive = 0");
		}
		if ($parameters['substitutions_only']) {
			return array("substitutions" => $substitutions, "email_id" => $eReceiptEmailId);
		}
		$receiptSent = false;
		if (empty($parameters['force_download']) && !$noReceipt && !$paperReceipt && (($receiptPolicy == "BOTH" && empty($donationRow['donation_batch_id'])) || empty($receiptPolicy) || $receiptPolicy == "EMAIL" || $emailReceipt) && !empty($donationRow['email_address'])) {
			if (!empty($eReceiptEmailId)) {
				if ($parameters['resend_receipts'] || empty($donationRow['receipt_sent'])) {
					if (sendEmail(array("donation_id" => $donationId, "email_id" => $eReceiptEmailId, "substitutions" => $substitutions, "email_addresses" => $donationRow['email_address']))) {
						$receiptSent = true;
					}
				} else {
					$receiptSent = true;
				}
			} else {
				sendEmail(array("subject" => "No receipt email", "body" => "There is no email receipt to send to a donor.", "email_address" => getNotificationEmails("DONATIONS")));
			}
		}
		$receiptContents = "";
		if (!$receiptSent && !$noReceipt && !$parameters['email_only']) {
			ob_start();
			echo '"' . $contactId . '",';
			echo '"' . str_replace('"', '""', $donationRow['title']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['first_name']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['middle_name']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['last_name']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['suffix']) . '",';
			echo '"' . str_replace('"', '""', getDisplayName($contactRow['contact_id'], array("dont_use_company" => true, "include_title" => true))) . '",';
			echo '"' . str_replace('"', '""', (empty($donationRow['salutation']) ? generateSalutation($donationRow) : $donationRow['salutation'])) . '",';
			echo '"' . str_replace('"', '""', $donationRow['business_name']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['address_1']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['address_2']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['city']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['state']) . '",';
			echo '"' . str_replace('"', '""', $donationRow['postal_code']) . '",';
			echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", $donationRow['country_id'])) . '",';
			$phoneSet = executeQuery("select * from phone_numbers where contact_id = ?", $contactId);
			$phoneCount = 0;
			while ($phoneRow = getNextRow($phoneSet)) {
				$phoneCount++;
				echo '"' . str_replace('"', '""', $phoneRow['phone_number']) . '",';
				echo '"' . str_replace('"', '""', $phoneRow['description']) . '",';
			}
			while ($phoneCount < 5) {
				$phoneCount++;
				echo '"","",';
			}
			echo '"' . str_replace('"', '""', $donationRow['donation_id']) . '",';
			echo '"' . str_replace('"', '""', date('m/d/y', strtotime($donationRow['donation_date']))) . '",';
			echo '"' . str_replace('"', '""', getFieldFromId("designation_code", "designations", "designation_id", $donationRow['designation_id'])) . '",';
			echo '"' . str_replace('"', '""', $designationDescription) . (!empty($donationRow['anonymous_gift']) ? " (Anonymous)" : "") . '",';
			echo '"' . str_replace('"', '""', getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id'])) . '",';
			echo '"' . str_replace('"', '""', $donationRow['reference_number']) . '",';
			echo '"' . str_replace('"', '""', number_format($donationRow['amount'], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[1], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[2], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[3], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[4], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[5], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[6], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[7], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[8], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[9], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[10], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[11], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($monthlyTotals[12], 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($yearToDateDonations, 2)) . '",';
			echo '"' . str_replace('"', '""', number_format($lastYearDonations, 2)) . '"' . "\r\n";
			$receiptContents = ob_get_clean();
			$receiptSent = true;
		}
		if ($receiptSent) {
			executeQuery("update donations set receipt_sent = now() where donation_id = ? and receipt_sent is null", $donationRow['donation_id']);
		}
		if (empty($receiptContents)) {
			return true;
		} else {
			return $receiptContents;
		}
	}

	public static function getContactDonationCommitment($contactId, $designationId, $donationSourceId = "") {
		$donationCommitmentId = "";
		$donationCommitmentTypeId = getFieldFromId("donation_commitment_type_id", "donation_sources", "donation_source_id", $donationSourceId);
		if (!empty($donationCommitmentTypeId)) {
			$resultSet = executeQuery("select * from donation_commitments where contact_id = ? and (designation_id is null or designation_id = ?) and " .
				"date_completed is null and donation_commitment_type_id = ? order by start_date", $contactId, $designationId, $donationCommitmentTypeId);
			if ($row = getNextRow($resultSet)) {
				$donationCommitmentId = $row['donation_commitment_id'];
			}
		}
		if (!empty($designationId) && empty($donationCommitmentId)) {
			$resultSet = executeQuery("select * from donation_commitments where contact_id = ? and (designation_id is null or designation_id = ?) and " .
				"date_completed is null order by start_date", $contactId, $designationId);
			while ($row = getNextRow($resultSet)) {
				$designationArray = Donations::getDonationCommitmentTypeDesignations($row['donation_commitment_type_id']);
				if (array_key_exists($designationId, $designationArray)) {
					$donationCommitmentId = $row['donation_commitment_id'];
				}
			}
		}
		return $donationCommitmentId;
	}

	public static function getDonationCommitmentTypeDesignations($donationCommitmentTypeId) {
		$designationArray = array();
		$donationCommitmentTypeRow = getRowFromId("donation_commitment_types", "donation_commitment_type_id", $donationCommitmentTypeId);
		if (empty($donationCommitmentTypeRow)) {
			return $designationArray;
		}
		if ($donationCommitmentTypeRow['all_designations']) {
			$resultSet = executeQuery("select * from designations where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$designationArray[$row['designation_id']] = $row['designation_id'];
			}
		} else {
			$resultSet = executeQuery("select * from donation_commitment_type_designations where donation_commitment_type_id = ?", $donationCommitmentTypeId);
			while ($row = getNextRow($resultSet)) {
				$designationArray[$row['designation_id']] = $row['designation_id'];
			}
			$resultSet = executeQuery("select * from designations where designation_id in (select designation_id from designation_group_links where designation_group_id in " .
				"(select designation_group_id from donation_commitment_type_designation_groups where donation_commitment_type_id = ?))", $donationCommitmentTypeId);
			while ($row = getNextRow($resultSet)) {
				$designationArray[$row['designation_id']] = $row['designation_id'];
			}
		}
		$resultSet = executeQuery("select * from donation_commitment_type_designation_exclusions where donation_commitment_type_id = ?", $donationCommitmentTypeId);
		while ($row = getNextRow($resultSet)) {
			unset($designationArray[$row['designation_id']]);
		}
		$resultSet = executeQuery("select * from designations where designation_id in (select designation_id from designation_group_links where designation_group_id in " .
			"(select designation_group_id from donation_commitment_type_designation_group_exclusions where donation_commitment_type_id = ?))", $donationCommitmentTypeId);
		while ($row = getNextRow($resultSet)) {
			unset($designationArray[$row['designation_id']]);
		}
		return $designationArray;
	}

	public static function completeDonationCommitment($donationCommitmentId) {
		if (empty($donationCommitmentId)) {
			return;
		}
		$amount = getFieldFromId("amount", "donation_commitments", "donation_commitment_id", $donationCommitmentId);
		if (!empty($amount)) {
			$resultSet = executeQuery("select sum(amount) from donations where donation_commitment_id = ?", $donationCommitmentId);
			if ($row = getNextRow($resultSet)) {
				if ($row['sum(amount)'] >= $amount) {
					executeQuery("update donation_commitments set date_completed = current_date where donation_commitment_id = ? and date_completed is null", $donationCommitmentId);
				}
			}
		}
	}

	public static function sendDonationNotifications($donationId, $emailId) {
		$emailId = getFieldFromId("email_id", "emails", "email_id", $emailId, "inactive = 0");
		$resultSet = executeQuery("select * from donations join contacts using (contact_id) where donations.client_id = ? and donation_id = ?", $GLOBALS['gClientId'], $donationId);
		if (empty($emailId) || !$donationRow = getNextRow($resultSet)) {
			return false;
		}
		$emailAddresses = array();
		$optOutEmails = getFieldFromId("opt_out_emails", "designations", "designation_id", $donationRow['designation_id']);
		if (empty($optOutEmails)) {
			$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?", $donationRow['designation_id']);
			while ($emailRow = getNextRow($emailSet)) {
				$emailAddresses[] = $emailRow['email_address'];
			}
			$emailSet = executeQuery("select email_address from contacts where contact_id in (select contact_id from users where user_id in (select user_id from designation_users where designation_id = ?))", $donationRow['designation_id']);
			while ($emailRow = getNextRow($emailSet)) {
				$emailAddresses[] = $emailRow['email_address'];
			}
		}
		if (!empty($donationRow['donation_source_id'])) {
			$emailSet = executeQuery("select email_address from contacts where email_address is not null and contact_id = (select contact_id from donation_sources where donation_source_id = ?)", $donationRow['donation_source_id']);
			if ($emailRow = getNextRow($emailSet)) {
				$emailAddresses[] = $emailRow['email_address'];
			}
		}
		$groupSet = executeQuery("select * from designation_groups where user_id is not null and designation_group_id in (select designation_group_id from designation_group_links where designation_id = ?)", $donationRow['designation_id']);
		while ($groupRow = getNextRow($groupSet)) {
			$emailAddress = Contact::getUserContactField($groupRow['user_id'],"email_address");
			if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
				$emailAddresses[] = $emailAddress;
			}
		}
		$groupSet = executeQuery("select * from designation_type_notifications where designation_type_id = (select designation_type_id from designations where designation_id = ?)", $donationRow['designation_id']);
		while ($groupRow = getNextRow($groupSet)) {
			if (!in_array($groupRow['email_address'], $emailAddresses)) {
				$emailAddresses[] = $groupRow['email_address'];
			}
		}
		$groupSet = executeQuery("select * from donation_notifications join donation_notification_email_addresses using (donation_notification_id) where " .
			"client_id = ? and amount >= ? and inactive = 0 and ((donation_notification_id not in (select donation_notification_id from donation_notification_designations) and " .
			"donation_notification_id not in (select donation_notification_id from donation_notification_designation_groups)) or donation_notification_id in " .
			"(select donation_notification_id from donation_notification_designations where designation_id = ?) or donation_notification_id in " .
			"(select donation_notification_id from donation_notification_designation_groups where designation_group_id in " .
			"(select designation_group_id from designation_group_links where designation_id = ?)))", $GLOBALS['gClientId'], $donationRow['amount'], $donationRow['designation_id'], $donationRow['designation_id']);
		while ($groupRow = getNextRow($groupSet)) {
			if (!in_array($groupRow['email_address'], $emailAddresses)) {
				$emailAddresses[] = $groupRow['email_address'];
			}
		}
		$emailAddresses = array_filter(array_unique($emailAddresses));
		if (!empty($emailAddresses)) {
			$substitutions = $donationRow;
			$substitutions['designation_alias'] = getFieldFromId("alias", "designations", "designation_id", $donationRow['designation_id']);
			$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']) . (!empty($donationRow['anonymous_gift']) ? " (Anonymous)" : "");
			$substitutions['designation'] = getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']) . (!empty($donationRow['anonymous_gift']) ? " (Anonymous)" : "");
			$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $donationRow['designation_id']);
			$substitutions['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $donationRow['payment_method_id']);
			$substitutions['full_name'] = getDisplayName($donationRow['contact_id']);
			$substitutions['receipt_number'] = $donationRow['donation_id'];
			$substitutions['donation_date'] = date("m/d/Y",strtotime($donationRow['donation_date']));
			$substitutions['gift_amount'] = $donationRow['amount'];
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
			if ($donationRow['anonymous_gift']) {
				$anonymizeFields = array("first_name", "last_name", "address_block", "full_name", "address_1", "address_2", "city", "state", "postal_code",
					"country_id", "email_address", "home_phone_number", "cell_phone_number", "billing_first_name",
					"billing_last_name", "billing_address_1", "billing_address_2", "billing_city", "billing_state",
					"billing_postal_code", "billing_country_id");
				foreach ($anonymizeFields as $fieldName) {
					$substitutions[$fieldName] = "Anonymous";
				}
			}
			sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $emailAddresses));
		}
		return true;
	}
}
