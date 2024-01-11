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
		$this->iProcessCode = "also_bought_products";
	}

	function process() {
		$saveClientId = "";
		$productsArray = array();
		$clientSet = executeQuery("select * from clients join related_product_types using (client_id) where clients.inactive = 0 and related_product_types.inactive = 0 and related_product_type_code like 'ALSO_BOUGHT%' order by clients.client_id");
		while ($clientRow = getNextRow($clientSet)) {
			if ($saveClientId != $clientRow['client_id']) {
				$productArray = array();
				$saveClientId = $clientRow['client_id'];
			}
			$this->addResult("Processing for " . $clientRow['client_code'] . ", " . $clientRow['related_product_type_code']);
			$minimumConnections = 2;
			if (startsWith($clientRow['related_product_type_code'],"ALSO_BOUGHT_")) {
				$minimumConnections = substr($clientRow['related_product_type_code'],strlen("ALSO_BOUGHT_"));
			}
			if (empty($minimumConnections) || !is_numeric($minimumConnections) || $minimumConnections <= 0) {
				$minimumConnections = 2;
			}
			$clientId = $clientRow['client_id'];
			if (empty($productsArray)) {
				$resultSet = executeQuery("select order_id,(select group_concat(product_id) from order_items where order_id = orders.order_id and deleted = 0) as product_ids from orders where client_id = ? and " .
					"(select count(*) from order_items where order_id = orders.order_id and deleted = 0) > 1", $clientId);
				while ($row = getNextRow($resultSet)) {
					$productIds = array_filter(explode(",", $row['product_ids']));
					if (count($productIds) < 2) {
						continue;
					}
					foreach ($productIds as $productId) {
						if (!array_key_exists($productId, $productsArray)) {
							$productsArray[$productId] = array();
						}
						foreach ($productIds as $relatedProductId) {
							if ($productId == $relatedProductId) {
								continue;
							}
							if (!array_key_exists($relatedProductId, $productsArray[$productId])) {
								$productsArray[$productId][$relatedProductId] = 0;
							}
							$productsArray[$productId][$relatedProductId]++;
						}
					}
				}
			}
			$insertCount = 0;
			foreach ($productsArray as $productId => $relatedProductIds) {
				foreach ($relatedProductIds as $relatedProductId => $count) {
					if ($relatedProductId == $productId) {
						continue;
					}
					if ($count < $minimumConnections) {
						continue;
					}
					$insertSet = executeQuery("insert ignore into related_products (product_id,associated_product_id,related_product_type_id,version) values (?,?,?,1)",$productId,$relatedProductId,$clientRow['related_product_type_id']);
					$insertCount += $insertSet['affected_rows'];
				}
			}
			$this->addResult("Client " . $clientRow['client_code'] . ", related product type " . $clientRow['related_product_type_code'] . ": " . $insertCount . " rows added");
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
