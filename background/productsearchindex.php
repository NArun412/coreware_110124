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
		$this->iProcessCode = "product_search_index";
	}

	function process() {
		$GLOBALS['gMultipleProductsForWaitingQuantities'] = true;
		$productSet = executeQuery("select client_id,product_id from products where client_id in (select client_id from clients where clients.inactive = 0) and " .
			"(reindex = 1 or not exists (select product_id from product_search_word_values where product_id = products.product_id)) and inactive = 0 order by client_id");
		$this->addResult($productSet['row_count'] . " products found to reindex");
		$count = 0;
		$reindexCount = 0;
		$saveClientId = "";
		$productIdArray = array();

		$startTime = getMilliseconds();

		while ($productRow = getNextRow($productSet)) {
			if ($reindexCount > 50000) {
				break;
			}
			if ($productRow['client_id'] != $saveClientId) {
				if (!empty($productIdArray)) {
					$this->addResult(count($productIdArray) . " products indexed for client " . $GLOBALS['gClientName']);
					$count += ProductCatalog::reindexProducts($productIdArray);
				}
				changeClient($productRow['client_id']);
				$saveClientId = $productRow['client_id'];
				$productIdArray = array();
			}
			$productIdArray[] = $productRow['product_id'];
			$reindexCount++;
		}

		$endTime = getMilliseconds();

		if (!empty($productIdArray)) {
			$this->addResult(count($productIdArray) . " products indexed for client " . $GLOBALS['gClientName']);
			$count += ProductCatalog::reindexProducts($productIdArray);
		}
		$this->addResult($count . " products indexed, taking " . round(($endTime - $startTime) / 1000, 2) . " seconds");

		$auctionItemSet = executeQuery("select client_id,auction_item_id from auction_items where (reindex = 1 or auction_item_id not in (select auction_item_id from auction_item_search_word_values)) and deleted = 0 order by client_id");
		if ($auctionItemSet['row_count'] > 0) {
			$this->addResult($auctionItemSet['row_count'] . " auction items found to reindex");
		}
		$count = 0;
		$reindexCount = 0;
		$saveClientId = "";
		$auctionItemIdArray = array();

		$startTime = getMilliseconds();

		while ($auctionItemRow = getNextRow($auctionItemSet)) {
			if ($reindexCount > 10000) {
				break;
			}
			if ($auctionItemRow['client_id'] != $saveClientId) {
				if (!empty($auctionItemIdArray)) {
					$this->addResult(count($auctionItemIdArray) . " auction items indexed for client " . $GLOBALS['gClientName']);
					$count += Auction::reindexAuctionItems($auctionItemIdArray);
				}
				changeClient($auctionItemRow['client_id']);
				$saveClientId = $auctionItemRow['client_id'];
				$auctionItemIdArray = array();
			}
			$auctionItemIdArray[] = $auctionItemRow['auction_item_id'];
			$reindexCount++;
		}

		$endTime = getMilliseconds();

		if (!empty($auctionItemIdArray)) {
			$this->addResult(count($auctionItemIdArray) . " auction items indexed for client " . $GLOBALS['gClientName']);
			$count += Auction::reindexAuctionItems($auctionItemIdArray);
		}
		if ($count > 0) {
			$this->addResult($count . " auction items indexed, taking " . round(($endTime - $startTime) / 1000, 2) . " seconds");
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
