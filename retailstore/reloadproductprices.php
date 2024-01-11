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

$GLOBALS['gPageCode'] = "RELOADPRODUCTPRICES";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {
	function mainContent() {
		$GLOBALS['gStartTime'] = getMilliseconds();
		$resultSet = executeQuery("select * from products join product_data using (product_id) where inactive = 0 order by products.client_id");
		echo "<p>" . $resultSet['row_count'] . " products found</p>";
		$productCatalog = false;
		while ($row = getNextRow($resultSet)) {
			if ($row['client_id'] != $GLOBALS['gClientId']) {
				changeClient($row['client_id']);
				$productCatalog = false;
			}
			if (!$productCatalog) {
				$productCatalog = new ProductCatalog();
			}
			$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row, "no_cache" => true));
		}
		$GLOBALS['gEndTime'] = getMilliseconds();
		echo "<p>Seconds to complete: " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 4) . "</p>";
	}

}

$pageObject = new ThisPage();
$pageObject->displayPage();
