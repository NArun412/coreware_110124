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

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class UpdateAchStatusBackgoundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "update_ach_status";
	}

	function process() {
		$clientSet = executeQuery("select * from contacts join clients using (contact_id) where inactive = 0");
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);

			$merchantAccountId = $GLOBALS['gMerchantAccountId'];
			$achMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
			if (!empty($achMerchantAccountId)) {
				$merchantAccountId = $achMerchantAccountId;
			}
			$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
			if (!$eCommerce || !method_exists($eCommerce, 'getAchStatusReport')) {
				$this->addResult($GLOBALS['gClientName'] . ": ACH Status report not supported by merchant provider.");
				continue;
			}
			$reportDates = array();
			if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
				$startDate = strtotime($_GET['start_date']);
				$endDate = strtotime($_GET['end_date']) + 1;
				$thisDate = $startDate;
				while ($thisDate <= $endDate) {
					$reportDates[] = date("Y-m-d", $thisDate);
					$thisDate += 86400;
				}
			} else {
				$reportDates[] = date("Y-m-d");
			}
			if (empty($reportDates)) {
				$this->addResult($GLOBALS['gClientName'] . "Invalid parameters for start and end dates: " . $_GET['start_date'] . "," . $_GET['end_date']);
				continue;
			}
			$invoicePaymentsTable = new DataTable("invoice_payments");
			$invoicePaymentsTable->setSaveOnlyPresent(true);
			$orderPaymentsTable = new DataTable("order_payments");
			$orderPaymentsTable->setSaveOnlyPresent(true);
			$donationsTable = new DataTable("donations");
			$donationsTable->setSaveOnlyPresent(true);
			$transactionCount = 0;
			$paymentCount = 0;

			$this->addResult($GLOBALS['gClientName'] . ": downloading " . count($reportDates) . " ACH report" . (count($reportDates) > 1 ? "s" : ""));
			foreach ($reportDates as $thisDate) {
				$transactions = $eCommerce->getAchStatusReport($thisDate);
				if (!$transactions) {
					$this->addResult("Report date " . $thisDate . ": " . $eCommerce->getErrorMessage());
					continue;
				} else {
					$this->addResult("Report date " . $thisDate . ": " . count($transactions) . " transactions found.");
				}
				foreach ($transactions as $thisTransaction) {
					$foundTransaction = false;
					$nameValues = array("notes" => $thisTransaction['notes']);
					$allowPartialMatch = !empty($_GET['partial_match']);
					$invoicePaymentResult = executeQuery("select invoice_payment_id from invoice_payments where transaction_identifier = ?"
						. ($allowPartialMatch ? " or " : " and ") . "authorization_code = ?", $thisTransaction['transaction_identifier'], $thisTransaction['authorization_code']);
					while ($invoicePaymentRow = getNextRow($invoicePaymentResult)) {
						$foundTransaction = true;
						$invoicePaymentsTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $invoicePaymentRow['invoice_payment_id']));
						$paymentCount++;
					}
					if (!$foundTransaction) {
						$orderPaymentResult = executeQuery("select order_payment_id from order_payments where transaction_identifier = ?"
							. ($allowPartialMatch ? " or " : " and ") . "authorization_code = ?", $thisTransaction['transaction_identifier'], $thisTransaction['authorization_code']);
						while ($orderPaymentRow = getNextRow($orderPaymentResult)) {
							$foundTransaction = true;
							$orderPaymentsTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $orderPaymentRow['order_payment_id']));
							$paymentCount++;
						}
					}
					if (!$foundTransaction) {
						$donationResult = executeQuery("select donation_id from donations where transaction_identifier = ?"
							. ($allowPartialMatch ? " or " : " and ") . "authorization_code = ?", $thisTransaction['transaction_identifier'], $thisTransaction['authorization_code']);
						while ($donationRow = getNextRow($donationResult)) {
							$foundTransaction = true;
							$donationsTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $donationRow['donation_id']));
							$paymentCount++;
						}
					}
					if (!$foundTransaction) {
						$this->addResult("Transaction " . $thisTransaction['transaction_identifier'] . " / authorization code " . $thisTransaction['authorization_code'] . " not found.");
					} else {
						$transactionCount++;
					}
				}
			}
			$this->addResult("Status updated for " . $paymentCount . " payments in " . $transactionCount . " transactions.");
		}
	}

}

$backgroundProcess = new UpdateAchStatusBackgoundProcess();
$backgroundProcess->startProcess();
