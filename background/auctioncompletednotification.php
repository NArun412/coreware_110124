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

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "auction_completed_notification";
	}

	function process() {

		$clientSet = executeQuery("select * from clients where client_id in (select client_id from auction_items where deleted = 0)");
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);

			$auctionItemStatusId = getFieldFromId("auction_item_status_id", "auction_item_statuses", "auction_item_status_code", "PAYMENT_NOTIFICATION_SENT");
			if (empty($auctionItemStatusId)) {
				$insertSet = executeQuery("insert into auction_item_statuses (client_id,auction_item_status_code,description) values (?,'PAYMENT_NOTIFICATION_SENT','Payment Notification Sent')", $GLOBALS['gClientId']);
				$auctionItemStatusId = $insertSet['insert_id'];
			}

			$auctionObject = new Auction();

			$purchaseCount = 0;
			$endCount = 0;
			$relistCount = 0;
			$resultSet = executeQuery("select * from auction_items where client_id = ? and date_completed is null and deleted = 0 and end_time <= now() and auction_item_id not in (select auction_item_id from auction_item_purchases where inactive = 0)", $clientRow['client_id']);
			while ($row = getNextRow($resultSet)) {
				if (!$auctionObject->createAuctionItemPurchase($row['auction_item_id'])) {
					if (!empty($row['relist_until_sold'])) {
						$auctionObject->relistAuctionItem($row['auction_item_id']);
						$relistCount++;
					} else {
						$auctionObject->notifySeller($row,'ended');
						$endCount++;
					}
				} else {
					$auctionObject->notifySeller($row,'sold');
					$purchaseCount++;
				}
			}
			$this->addResult($purchaseCount . " auction items ended and purchases created");
			$this->addResult($relistCount . " auction items ended and relisted because of no purchase");
			$this->addResult($endCount . " auction items ended without a purchase");

			$count = 0;
			$resultSet = executeQuery("select * from auction_items where client_id = ? and date_completed is null and deleted = 0 and end_time <= now() and " .
				"auction_item_id in (select auction_item_id from auction_item_purchases where inactive = 0) and auction_item_id not in (select auction_item_id from auction_item_status_links where auction_item_status_id = ?)",
				$clientRow['client_id'], $auctionItemStatusId);
			while ($row = getNextRow($resultSet)) {
				if ($auctionObject->setAuctionItemStatus($row['auction_item_id'], $auctionItemStatusId)) {
					$count++;
				}
			}
			$this->addResult($count . " purchase notifications sent");
		}

	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
