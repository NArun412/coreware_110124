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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}
$GLOBALS['gAllowLongRun'] = true;

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "recurring_donations";
	}

	function process() {
		$resultSet = executeQuery("select * from recurring_donation_changes where date_completed is null and change_date <= current_date");
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['amount'] || !empty($row['next_billing_date']))) {
				$parameters = array();
				$setStatement = "";
				if (!empty($row['amount'])) {
					$parameters[] = $row['amount'];
					$setStatement .= (empty($setStatement) ? "" : ",") . "amount = ?";
				}
				if (!empty($row['next_billing_date'])) {
					$parameters[] = $row['next_billing_date'];
					$setStatement .= (empty($setStatement) ? "" : ",") . "next_billing_date = ?";
				}
				$parameters[] = $row['recurring_donation_id'];
				executeQuery("update recurring_donations set " . $setStatement . " where recurring_donation_id = ?",$parameters);
				executeQuery("update recurring_donation_changes set date_completed = current_date where recurring_donation_change_id = ?",$row['recurring_donation_change_id']);
			}
		}

		$incompleteProcess = false;
		$recurringResults = array();
		$recurringCount = 0;
		$recurringSet = executeQuery("select *,(select client_id from contacts where contact_id = recurring_donations.contact_id) client_id " .
			"from recurring_donations where" . ($GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine'] ? " contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . ") and" : "") .
			" requires_attention = 0 and (end_date > current_date or end_date is null) and contact_id in (select contact_id from contacts where client_id in (select client_id from clients where inactive = 0)) and " .
			"(start_date is null or start_date <= current_date) and next_billing_date <= current_date and recurring_donation_type_id in " .
			"(select recurring_donation_type_id from recurring_donation_types where manual_processing = 0) order by client_id");

		$this->addResult($recurringSet['row_count'] . " recurring donations found to process");
		$recurringRowArray = array();
		while ($recurringRow = getNextRow($recurringSet)) {
			$recurringRowArray[] = $recurringRow;
		}
		foreach ($recurringRowArray as $recurringRow) {
			if (changeClient($recurringRow['client_id'])) {
				sleep(300);
			}
			if ($recurringCount > 900) {
				$this->addResult("Process limit approaching, pausing and restarting");
				$incompleteProcess = true;
				break;
			}
			$recurringGiftErrorEmailId = getFieldFromId("email_id","emails","email_code","RECURRING_GIFT_ERROR_EMAIL");
			$recurringGiftErrorNoUserEmailId = getFieldFromId("email_id","emails","email_code","RECURRING_GIFT_ERROR_NO_USER_EMAIL");
			if (!array_key_exists($GLOBALS['gClientId'],$recurringResults)) {
				$recurringResults[$GLOBALS['gClientId']] = array();
			}
			$inactiveDesignation = getFieldFromId("inactive","designations","designation_id",$recurringRow['designation_id']);
			if (!empty($inactiveDesignation)) {
				$recurringResults[$GLOBALS['gClientId']][] = "Recurring Donation from " . getDisplayName($recurringRow['contact_id']) . ", but designation (" . getFieldFromId("designation_code","designations","designation_id",$recurringRow['designation_id']) . ") is inactive";
				continue;
			}
			$designationRequiresAttention = getFieldFromId("requires_attention","designations","designation_id",$recurringRow['designation_id']);
			if (!empty($designationRequiresAttention)) {
				$recurringResults[$GLOBALS['gClientId']][] = "Recurring Donation for designation that requires attention";
				executeQuery("update recurring_donations set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_donation_id = ?",
					date("m/d/Y h:i:s a T") . ": Recurring Donation for designation that requires attention",$recurringRow['recurring_donation_id']);
				continue;
			}
			eCommerce::getClientMerchantAccountIds();
			$designationMerchantAccountId = $GLOBALS['gMerchantAccountId'];
			$accountSet = executeQuery("select * from contacts,accounts where contacts.contact_id = accounts.contact_id and accounts.account_id = ? and " .
				"inactive = 0 and account_token is not null",$recurringRow['account_id']);
			if ($accountRow = getNextRow($accountSet)) {
				$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
				if (!empty($designationMerchantAccountId) && $designationMerchantAccountId != $accountMerchantAccountId) {
					$recurringResults[$GLOBALS['gClientId']][] = "Payment Account is not from the correct Merchant Account for recurring donation from " . getDisplayName($recurringRow['contact_id']) . " for recurring payment ID " . $recurringRow['recurring_payment_id'];
					executeQuery("update recurring_donations set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_donation_id = ?",
						date("m/d/Y h:i:s a T") . ": Payment Account is not from the correct Merchant Account",$recurringRow['recurring_donation_id']);
					continue;
				}
				$eCommerce = eCommerce::getEcommerceInstance($accountMerchantAccountId);
				if (!$eCommerce) {
					$recurringResults[$GLOBALS['gClientId']][] = "Unable to get Merchant Account for client.";
					continue;
				} else if (empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
					$recurringResults[$GLOBALS['gClientId']][] = "No customer database for merchant account.";
					continue;
				}
				if (empty($accountRow['merchant_identifier'])) {
					$accountRow['merchant_identifier'] = getFieldFromId("merchant_identifier","merchant_profiles","contact_id",$accountRow['contact_id'],"merchant_account_id = ?",$accountMerchantAccountId);
				}
				if (empty($accountRow['merchant_identifier']) && $eCommerce->requiresCustomerToken()) {
					$recurringResults[$GLOBALS['gClientId']][] = "Contact Profile ID for " . getDisplayName($recurringRow['contact_id']) . " is missing";
					executeQuery("update recurring_donations set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_donation_id = ?",
						date("m/d/Y h:i:s a T") . ": Contact Profile ID for " . getDisplayName($recurringRow['contact_id']) . " is missing",$recurringRow['recurring_donation_id']);
					continue;
				}
				$donationFee = Donations::getDonationFee(array("designation_id"=>$recurringRow['designation_id'],"amount"=>$recurringRow['amount'],"payment_method_id"=>$accountRow['payment_method_id']));
				$donationCommitmentId = Donations::getContactDonationCommitment($accountRow['contact_id'],$recurringRow['designation_id'],$recurringRow['donation_source_id']);
				$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
					"account_id,designation_id,project_name,donation_source_id,amount,anonymous_gift,donation_fee,recurring_donation_id,donation_commitment_id,notes) values " .
					"(?,?,now(),?, ?,?,?,?,?, ?,?,?,?,?)",$accountRow['client_id'],$accountRow['contact_id'],
					$accountRow['payment_method_id'],$recurringRow['account_id'],$recurringRow['designation_id'],$recurringRow['project_name'],
					$recurringRow['donation_source_id'],$recurringRow['amount'],(empty($recurringRow['anonymous_gift']) ? "0" : "1"),
					$donationFee,$recurringRow['recurring_donation_id'],$donationCommitmentId,$recurringRow['notes']);
				if (!empty($resultSet['sql_error'])) {
					$recurringResults[$GLOBALS['gClientId']][] = "Unable to create donation record for recurring gift from " . getDisplayName($recurringRow['contact_id']) . " for " .
						getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2);
					executeQuery("update recurring_donations set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_donation_id = ?",
						date("m/d/Y h:i:s a T") . ": Unable to create donation record for recurring gift from " . getDisplayName($recurringRow['contact_id']) . " for " .
						getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2),
						$recurringRow['recurring_donation_id']);
					continue;
				}
				Donations::completeDonationCommitment($donationCommitmentId);
				$donationId = $resultSet['insert_id'];
				Donations::processDonation($donationId);
				$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount"=>$recurringRow['amount'],
					"order_number"=>$donationId,"merchant_identifier"=>$accountRow['merchant_identifier'],"account_token"=>$accountRow['account_token'],"address_id"=>$accountRow['address_id']));
				$response = $eCommerce->getResponse();

				$substitutions = array_merge($accountRow,$recurringRow);
				unset($substitutions['account_number']);
				unset($substitutions['account_token']);
				$substitutions['designation_alias'] = getFieldFromId("alias","designations","designation_id",$recurringRow['designation_id']);
				$substitutions['designation_description'] = getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']);
				$substitutions['designation_code'] = getFieldFromId("designation_code","designations","designation_id",$recurringRow['designation_id']);
				$substitutions['full_name'] = getDisplayName($accountRow['contact_id']);
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
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . getFieldFromId("country_name","countries","country_id",$substitutions['country_id']);
				}
				$substitutions['address_block'] = $addressBlock;

				if ($success) {
					executeQuery("update donations set transaction_identifier = ?,authorization_code = ?,bank_batch_number = ? where donation_id = ?",
						$response['transaction_id'],$response['authorization_code'],$response['bank_batch_number'],$donationId);
				} else {
					$recurringResults[$GLOBALS['gClientId']][] = "Unable to create payment transaction for recurring gift from " . getDisplayName($recurringRow['contact_id']) . " for " .
						getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2) .
						" : " . $response['response_reason_text'];
					if (!empty($response['response_reason_text'])) {

						# send email to donor

						$substitutions['error_message'] = date("m/d/Y h:i:s a T") . ": " . $response['response_reason_text'];
						$accountUserId = Contact::getContactUserId($accountRow['contact_id']);
						$usingRecurringGiftErrorEmailId = (empty($accountUserId) && !empty($recurringGiftErrorNoUserEmailId) ? $recurringGiftErrorNoUserEmailId : $recurringGiftErrorEmailId);
						if (!empty($usingRecurringGiftErrorEmailId)) {
							if ($recurringRow['anonymous_gift']) {
								$anonymizeFields = array("first_name", "last_name", "address_block", "full_name", "address_1", "address_2", "city", "state", "postal_code",
									"country_id", "email_address", "home_phone_number", "cell_phone_number", "billing_first_name",
									"billing_last_name", "billing_address_1", "billing_address_2", "billing_city", "billing_state",
									"billing_postal_code", "billing_country_id");
								foreach ($anonymizeFields as $fieldName) {
									$substitutions[$fieldName] = "Anonymous";
								}
							}

							sendEmail(array("email_id"=>$usingRecurringGiftErrorEmailId,"substitutions"=>$substitutions,"email_address"=>$accountRow['email_address']));
						}

						$emailAddresses = array();
						$emailSet = executeQuery("select email_address from designation_email_addresses where designation_id = ?",$recurringRow['designation_id']);
						while ($emailRow = getNextRow($emailSet)) {
							$emailAddresses[] = $emailRow['email_address'];
						}
						$groupSet = executeQuery("select * from designation_groups where user_id is not null and designation_group_id in (select designation_group_id from designation_group_links where designation_id = ?)",$recurringRow['designation_id']);
						while ($groupRow = getNextRow($groupSet)) {
							$emailAddress = Contact::getUserContactField($groupRow['user_id'],"email_address");
							if (!in_array($emailAddress,$emailAddresses)) {
								$emailAddresses[] = $emailAddress;
							}
						}
						$groupSet = executeQuery("select * from designation_type_notifications where designation_type_id = (select designation_type_id from designations where designation_id = ?)",$recurringRow['designation_id']);
						while ($groupRow = getNextRow($groupSet)) {
							if (!in_array($groupRow['email_address'],$emailAddresses)) {
								$emailAddresses[] = $groupRow['email_address'];
							}
						}
						if (!empty($emailAddresses)) {
							$emailId = getFieldFromId("email_id","emails","email_code","ADMIN_RECURRING_GIFT_ERROR");
							if (empty($emailId)) {
								$body = "Unable to create payment transaction for recurring gift from " . ($recurringRow['anonymous_gift'] ? "Anonymous" : getDisplayName($recurringRow['contact_id'])) . " for " .
									getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2);
								sendEmail(array("body"=>$body,"subject"=>"Recurring Gift Error","email_addresses"=>$emailAddresses));
							} else {
								sendEmail(array("email_id"=>$emailId,"substitutions"=>array("full_name"=>($recurringRow['anonymous_gift'] ? "Anonymous" : getDisplayName($recurringRow['contact_id'])),
									"designation_description"=>getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']),
									"amount"=>number_format($recurringRow['amount'],2),"error_text"=>$response['response_reason_text']),"email_addresses"=>$emailAddresses));
							}
						}
					}
					executeQuery("delete from donations where donation_id = ?",$donationId);
					executeQuery("update recurring_donations set requires_attention = " . (empty($response['response_reason_text']) ? "0" : "1") . ",error_message = ?,last_attempted = now() where recurring_donation_id = ?",
						date("m/d/Y h:i:s a T") . ": Unable to create payment transaction for recurring gift from " . getDisplayName($recurringRow['contact_id']) . " for " .
						getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2) .
						" : " . $response['response_reason_text'],$recurringRow['recurring_donation_id']);
					continue;
				}

				$recurringTypeRow = getRowFromId("recurring_donation_types","recurring_donation_type_id",$recurringRow['recurring_donation_type_id']);
				$validUnits = array("day","week","month");
				$intervalUnit = $recurringTypeRow['interval_unit'];
				if (empty($intervalUnit) || !in_array($intervalUnit,$validUnits)) {
					$intervalUnit = "month";
				}
				$unitsBetween = $recurringTypeRow['units_between'];
				if (empty($unitsBetween) || $unitsBetween < 0) {
					$unitsBetween = 1;
				}
				executeQuery("update recurring_donations set next_billing_date = date_add(next_billing_date,interval " .
					$unitsBetween . " " . $intervalUnit . ") where recurring_donation_id = ?",$recurringRow['recurring_donation_id']);
				do {
					$recurringDonationId = getFieldFromId("recurring_donation_id","recurring_donations","recurring_donation_id",$recurringRow['recurring_donation_id'],
						"next_billing_date <= current_date");
					if (!empty($recurringDonationId)) {
						executeQuery("update recurring_donations set next_billing_date = date_add(next_billing_date,interval " .
							$unitsBetween . " " . $intervalUnit . ") where recurring_donation_id = ?",$recurringRow['recurring_donation_id']);
					}
				} while (!empty($recurringDonationId));

				$receiptProcessed = Donations::processDonationReceipt($donationId,array("email_only"=>true));

				$emailId = getFieldFromId("email_id","emails","email_code","RECURRING_DONATION_RECEIVED","inactive = 0 and client_id = ?",$GLOBALS['gClientId']);
				if (!empty($emailId)) {
					Donations::sendDonationNotifications($donationId,$emailId);
				}
				$recurringCount++;

		# send Emails just like in the publicDonation script

			} else {
				$recurringResults[$GLOBALS['gClientId']][] = "Unable to get valid account for recurring donation from " . getDisplayName($recurringRow['contact_id']) . " for " .
					getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2);
				executeQuery("update recurring_donations set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_donation_id = ?",
					date("m/d/Y h:i:s a T") . ": Unable to get valid account for recurring donation for " .
					getFieldFromId("description","designations","designation_id",$recurringRow['designation_id']) . " for " . number_format($recurringRow['amount'],2),
					$recurringRow['recurring_donation_id']);
			}
		}
		$this->addResult($recurringCount . " recurring donations created");

		$clientSet = executeQuery("select * from clients");
		while ($clientRow = getNextRow($clientSet)) {
			$logEntries = $recurringResults[$clientRow['client_id']];
			if (empty($logEntries)) {
				$logEntries = array();
			}
			sort($logEntries);
			$logEntry = implode("<br>\n",$logEntries);
			$GLOBALS['gClientId'] = $clientRow['client_id'];
			if (!empty($logEntries)) {
				executeQuery("insert into program_log (client_id,program_name,log_entry) values " .
					"(?,'Recurring Donations',?)",$GLOBALS['gClientId'],$logEntry);
				$logEntry = "Recurring Donations processed<br>\n<br>\n" . $logEntry . "<br>\n<br>\n";
			} else {
				$logEntry = "";
			}
			$resultSet = executeQuery("select * from donations where client_id = ? and donation_batch_id is null and donation_date >= date_sub(curdate(),interval 2 day) order by donation_id desc",$GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
				$logEntry .= "Online Donations processed<br>\n<br>\n";
			}
			while ($row = getNextRow($resultSet)) {
				$logEntry .= "On: " . date("m/d/Y",strtotime($row['donation_date'])) . ", From: " . getDisplayName($row['contact_id']) .
					", For: " . getFieldFromId("description","designations","designation_id",$row['designation_id']) .
					(empty($row['project_name']) ? "" : " - " . $row['project_name']) . ", Amount: " . number_format($row['amount'],2) . "<br>\n";
			}
			if (!empty($logEntry)) {
				sendEmail(array("subject"=>"Recurring and online donations","body"=>$logEntry,"notification_code"=>"DONATIONS"));
			}
		}

		if ($incompleteProcess) {
			sleep(3600);
			executeQuery("update background_processes set run_immediately = 1 where background_process_code = ?",$this->iProcessCode);
		} else {
			$clientSet = executeQuery("select * from clients");
			while ($clientRow = getNextRow($clientSet)) {
				$GLOBALS['gClientId'] = $clientRow['client_id'];
				$emailAddresses = getNotificationEmails("DAILY_DONATION_REPORT");
				if (empty($emailAddresses)) {
					continue;
				}
				$designationCriteria = getPreference("DAILY_DONATION_REPORT");
				$whereStatement = "";
				$parameters = array(date("Y-m-d",strtotime("yesterday")),$GLOBALS['gClientId']);
				if (!empty($designationCriteria)) {
					$criteriaLines = getContentLines($designationCriteria);
					foreach ($criteriaLines as $thisLine) {
						$theseParts = explode(":",$thisLine);
						if (count($theseParts) == 1) {
							$theseBits = explode(",",$theseParts[0]);
							$whereStatement .= (empty($whereStatement) ? "" : " or ") . "designation_id in (select designation_id from designations where designation_code in (";
							$firstOne = true;
							foreach ($theseBits as $thisBit) {
								$whereStatement .= ($firstOne ? "" : ",") . "?";
								$parameters[] = $thisBit;
							}
							$whereStatement .= "))";
						} else {
							$theseBits = explode(",",$theseParts[1]);
							if (strtolower($theseParts[0]) == "type") {
								$whereStatement .= (empty($whereStatement) ? "" : " or ") . "designation_id in (select designation_id from designations where designation_type_id in (select designation_type_id from designation_types where designation_type_code in (";
								$firstOne = true;
								foreach ($theseBits as $thisBit) {
									$whereStatement .= ($firstOne ? "" : ",") . "?";
									$parameters[] = $thisBit;
								}
								$whereStatement .= ")))";
							} else if (strtolower($theseParts[0]) == "group") {
								$whereStatement .= (empty($whereStatement) ? "" : " or ") . "designation_id in (select designation_id from designation_group_links where designation_group_id in (select designation_group_id from designation_groups where designation_group_code in (";
								$firstOne = true;
								foreach ($theseBits as $thisBit) {
									$whereStatement .= ($firstOne ? "" : ",") . "?";
									$parameters[] = $thisBit;
								}
								$whereStatement .= ")))";
							}
						}
					}
				}
				$body = "";
				$resultSet = executeQuery("select *,(select description from designations where designation_id = donations.designation_id) designation_description from donations where " .
					"donation_date = ? and donations.client_id = ?" . (empty($whereStatement) ? "" : " and (" . $whereStatement . ")"),$parameters);
				if ($resultSet['row_count'] > 0) {
					$body .= "Donations:<br>";
				}
				while ($row = getNextRow($resultSet)) {
					$count = 0;
					$countSet = executeQuery("select count(*) from donations where contact_id = ? and donation_id < ?",$row['contact_id'],$row['donation_id']);
					if ($countRow = getNextRow($countSet)) {
						$count = $countRow['count(*)'];
					}
					$emailAddress = getFieldFromId("email_address","contacts","contact_id",$row['contact_id']);
					$body .= (empty($body) ? "" : "<br>") . number_format($row['amount'],2,".",",") . " from " . getDisplayName($row['contact_id']) . (empty($emailAddress) ? "" : " (" . $emailAddress . ")") . " for " . $row['designation_description'] . ($count == 0 ? " (First time donor)" : "");
				}
				$resultSet = executeQuery("select *,(select description from designations where designation_id = recurring_donations.designation_id) designation_description from recurring_donations " .
					"where start_date = ? and recurring_donations.contact_id in (select contact_id from contacts where client_id = ?)" . (empty($whereStatement) ? "" : " and (" . $whereStatement . ")"),$parameters);
				if ($resultSet['row_count'] > 0) {
					$body .= (empty($body) ? "" : "<br><br>") . "Recurring Donations:<br>";
				}
				while ($row = getNextRow($resultSet)) {
					$emailAddress = getFieldFromId("email_address","contacts","contact_id",$row['contact_id']);
					$body .= (empty($body) ? "" : "<br>") . number_format($row['amount'],2,".",",") . " from " . getDisplayName($row['contact_id']) . (empty($emailAddress) ? "" : " (" . $emailAddress . ")") . " for " . $row['designation_description'];
				}
				if (!empty($body)) {
					sendEmail(array("subject"=>"Daily Giving Report","body"=>$body,"email_address"=>$emailAddresses));
				}
			}
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
