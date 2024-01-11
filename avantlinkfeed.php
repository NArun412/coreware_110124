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

$GLOBALS['gPageCode'] = "AVANTLINKFEED";
require_once "shared/startup.inc";
// Per Avantlink (ticket 65837), they cannot provide an IP address to be whitelisted for the feed, so it has to be open to the public.
/*
$allowedIpAddresses = array("52.0.58.53", "207.244.83.229", "198.7.56.46", "207.244.83.227", "207.244.83.236", "69.180.112.233", "89.216.98.192", "23.105.168.23", "23.105.168.20", "23.105.168.21",
	"23.105.168.22", "23.105.168.21", "23.105.168.23", "23.105.168.18", "23.105.168.19", "66.198.240.12", "209.124.72.35");
$resultSet = executeReadQuery("select * from feed_whitelist_ip_addresses where client_id = ?", $GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
	$allowedIpAddresses[] = $row['ip_address'];
}
if (!$GLOBALS['gInternalConnection'] && (isWebCrawler() || !in_array($_SERVER['REMOTE_ADDR'], $allowedIpAddresses))) {
	addProgramLog("Avant Link Feed IP address rejection: " . $_SERVER['REMOTE_ADDR'] . "\n\nUser Agent: " . $_SERVER['HTTP_USER_AGENT']);
	exit;
}
*/
$logContent = "Avantlink feed accessed by " . $_SERVER['REMOTE_ADDR'] . " User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n\n";
addProgramLog($logContent);

$startTime = getMilliseconds();
$systemName = strtolower(getPreference("system_name"));
$filename = $GLOBALS['gDocumentRoot'] . "/feeds/avantlinkfeed_" . strtolower(str_replace("_", "", $systemName)) . "_" . $GLOBALS['gClientId'] . ".csv";

$feedDetailId = getReadFieldFromId("feed_detail_id", "feed_details", "feed_detail_code", "avantlinkfeed");
if (empty($feedDetailId)) {
	executeQuery("insert into feed_details (client_id,feed_detail_code,time_created,last_activity) values (?,'avantlinkfeed',now(),now())", $GLOBALS['gClientId']);
} else {
	executeQuery("update feed_details set last_activity = now() where feed_detail_id = ?", $feedDetailId);
}

// Avantlink requires the CSV data to be downloaded as a file in the browser rather than rendering in the browser window

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header(sprintf( "Content-Disposition: attachment;filename=%s.csv", $GLOBALS['gClientRow']['client_code']));

if (file_exists($filename)) {
	$upcCodes = array();
	$resultSet = executeQuery("select product_id,upc_code from product_data where upc_code is not null and client_id = ?", $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$upcCodes[$row['upc_code']] = $row['product_id'];
	}
	$productIdArray = array();
	$count = 0;
	$fieldNames = array();
	$productCatalog = new ProductCatalog();
	$openFile = fopen($filename, "r");
	while ($csvData = fgetcsv($openFile)) {
		if ($count == 0) {
			foreach ($csvData as $thisName) {
				$fieldNames[] = $thisName;
			}
			$count++;
			continue;
		}
		$count++;
		$fieldData = array();
		foreach ($csvData as $index => $thisData) {
			$fieldData[$fieldNames[$index]] = trim($thisData);
		}
		$productId = $upcCodes[$fieldData['UPC/GTIN']];
		if (!empty($productId)) {
			$productIdArray[] = $productId;
		}
	}
	fclose($openFile);
	$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIdArray);
	$productRecords = array();
	$openFile = fopen($filename, "r");
	while ($csvData = fgetcsv($openFile)) {
		if ($count == 0) {
			foreach ($csvData as $thisName) {
				$fieldNames[] = $thisName;
			}
			$count++;
			continue;
		}
		$count++;
		$fieldData = array();
		foreach ($csvData as $index => $thisData) {
			$fieldData[$fieldNames[$index]] = trim($thisData);
		}
		$productId = $upcCodes[$fieldData['UPC/GTIN']];
		if (empty($productId)) {
			continue;
		}
		if (!array_key_exists($productId,$inventoryCounts) || $inventoryCounts[$productId] <= 0) {
			continue;
		}
		$salePriceInfo = $productCatalog->getProductSalePrice($productId);
		$salePrice = $salePriceInfo['sale_price'];
		if ($salePrice) {
			$fieldData['Sale Price'] = number_format($salePrice, 2, ".", "");
		}
		$productRecords[] = $fieldData;
	}
	fclose($openFile);
	echo createCsvRow($fieldNames);
} else {
	$headers = array('SKU',
		'Product Name',
		'Long Description',
		'Department',
		'Image URL',
		'Buy Link',
		'Retail Price',
		'Manufacturer ID',
		'Brand Name',
		'Sale Price',
		'Item Based Commission',
		'UPC/GTIN');
	$productRecords = array();
	file_put_contents($filename, createCsvRow($headers));
	echo createCsvRow($headers);
}
foreach ($productRecords as $thisRecord) {
	echo createCsvRow($thisRecord);
}
