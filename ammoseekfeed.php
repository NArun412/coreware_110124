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

$GLOBALS['gPageCode'] = "AMMOSEEKFEED";
$GLOBALS['gAllowLongRun'] = true;
$GLOBALS['gMultipleProductsForWaitingQuantities'] = true;
ini_set("memory_limit", "4096M");
require_once "shared/startup.inc";

$programLogId = "";
if ($_SERVER['HTTP_USER_AGENT'] == "AmmoSeek" && in_array($_SERVER['REMOTE_ADDR'], array("45.56.108.142", "45.79.159.167",
		"45.79.159.167", "45.79.159.167", "66.228.33.207", "66.228.33.207", "69.164.218.117", "69.164.221.142", "172.104.12.217", "23.92.67.74"))) {
} else {
	$allowedIpAddresses = array("185.116.236.52", "100.16.160.50", "52.0.58.53", "23.92.67.74", "185.116.236.52", "100.16.160.50");
	$resultSet = executeReadQuery("select * from feed_whitelist_ip_addresses where client_id = ?", $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$allowedIpAddresses[] = $row['ip_address'];
	}
	if (!$GLOBALS['gInternalConnection'] && (isWebCrawler() || !in_array($_SERVER['REMOTE_ADDR'], $allowedIpAddresses))) {
		addProgramLog("Ammo Seek IP address rejection: " . $_SERVER['REMOTE_ADDR'] . "\n\nUser Agent: " . $_SERVER['HTTP_USER_AGENT']);
		exit;
	}
}
$logContent = "AmmoSeek feed accessed by " . $_SERVER['REMOTE_ADDR'] . "\n";
$startTime = getMilliseconds();
$systemName = strtolower(getPreference("system_name"));
$filename = $GLOBALS['gDocumentRoot'] . "/feeds/ammoseekfeed_" . strtolower(str_replace("_", "", $systemName)) . "_" . $GLOBALS['gClientId'] . ".xml";

$feedDetailId = getReadFieldFromId("feed_detail_id", "feed_details", "feed_detail_code", "ammoseekfeed");
if (empty($feedDetailId)) {
	executeQuery("insert into feed_details (client_id,feed_detail_code,time_created,last_activity) values (?,'ammoseekfeed',now(),now())", $GLOBALS['gClientId']);
} else {
	executeQuery("update feed_details set last_activity = now() where feed_detail_id = ?", $feedDetailId);
}
if (file_exists($filename)) {
	$content = file_get_contents($filename);
	$contentLines = getContentLines($content);
	$upcCodes = array();
	$resultSet = executeQuery("select product_id,upc_code from product_data where upc_code is not null and client_id = ?", $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$upcCodes[$row['upc_code']] = $row['product_id'];
	}
	$productIdArray = array();
	foreach ($contentLines as $thisLine) {
		if (startsWith($thisLine, "<upc>")) {
			$upcCode = str_replace("<upc>", "", str_replace("</upc>", "", $thisLine));
			$productId = $upcCodes[$upcCode];
			if (!empty($productId)) {
				$productIdArray[] = $productId;
			}
		}
	}
	$productCatalog = new ProductCatalog();
	$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIdArray);
	$saveProductId = false;
	foreach ($contentLines as $index => $thisLine) {
		if (startsWith($thisLine, "<upc>")) {
			$upcCode = str_replace("<upc>", "", str_replace("</upc>", "", $thisLine));
			$saveProductId = $upcCodes[$upcCode];
		} else if (startsWith($thisLine, "<availability>")) {
			if (!empty($saveProductId)) {
				$contentLines[$index] = "<availability>" . (array_key_exists($saveProductId, $inventoryCounts) && $inventoryCounts[$saveProductId] > 0 ? "in stock" : "out of stock") . "</availability>";
			}
		} else if (startsWith($thisLine, "<price>")) {
			if (!empty($saveProductId)) {
				$salePriceInfo = $productCatalog->getProductSalePrice($saveProductId);
				$salePrice = $salePriceInfo['sale_price'];
				if ($salePrice) {
					$contentLines[$index] = "<price>" . number_format($salePrice, 2, ".", "") . "</price>";
				}
			}
		}
	}
	shutdown();
	$content = implode("\n", $contentLines);
	echo $content;
	exit;
} else {
	file_put_contents($filename, "<productlist retailer=" . $GLOBALS['gDomainName'] . "></productlist>");
	echo "<productlist retailer=" . $GLOBALS['gDomainName'] . "></productlist>";
}
