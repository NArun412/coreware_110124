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
		$this->iProcessCode = "calculate_product_prices";
	}

	function process() {

# do the process here

		$clientSet = executeQuery("select * from clients where inactive = 0 and client_id in (select client_id from products) order by rand()");
		$clientCount = $clientSet['row_count'];
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);

			$ignoreStoredPrices = getPreference("IGNORE_STORED_PRICES");
			if (!empty($ignoreStoredPrices)) {
				continue;
			}
			$resultSet = executeQuery("select count(*) from products where client_id = ? and product_id not in (select product_id from product_sale_prices where expiration_time < now())", $GLOBALS['gClientId']);
			$GLOBALS['gStartTime'] = getMilliseconds();
			if ($row = getNextRow($resultSet)) {
				if ($row['count(*)'] == 0) {
					continue;
				}
				$this->addResult("Client '" . $GLOBALS['gClientName'] . " (" . $GLOBALS['gClientRow']['client_code'] . ")' being processed");
				$this->addResult("    " . $row['count(*)'] . " products found to be recalculated");
			}
			$productIds = array();
			$resultSet = executeQuery("select product_id from products where client_id = ? and product_id not in (select product_id from product_sale_prices where expiration_time < now()) limit " . ($clientCount > 10 ? "20000" : "50000"), $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productIds[] = $row['product_id'];
			}

			$count = 0;
			foreach ($productIds as $productId) {
				$count++;
				ProductCatalog::calculateAllProductSalePrices($productId);
			}
			$GLOBALS['gEndTime'] = getMilliseconds();
			if ($count > 0) {
				$average = round((($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000) / $count, 2);
				$this->addResult("    Prices recalculated for " . $count . " product" . ($count == 1 ? "" : "s") . " averaging " . $average);
			} else {
				$this->addResult("    No prices recalculated");
			}
			$this->addResult("Memory usage: " . memory_get_usage());
			if (date("H") < 14 && date("H") > 6) {
				break;
			}
		}
		removeCachedData("stored_product_sale_prices", "stored_product_sale_prices");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
