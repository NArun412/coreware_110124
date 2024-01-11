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

$GLOBALS['gPageCode'] = "PRODUCTMANUFACTURERREBATEMAINT";
require_once "shared/startup.inc";

class ProductManufacturerRebateMaintenancePage extends Page {
	function massageDataSource() {
		$this->iDataSource->addFilterWhere("product_manufacturer_id in (select product_manufacturer_id from product_manufacturers where client_id = " . $GLOBALS['gClientId'] . ")");
	}

    function afterSaveDone($nameValues) {
        if(!empty($nameValues['file_id'])) {
            executeQuery("update files set public_access = 1 where file_id = ?", $nameValues['file_id']);
        }
    }
}

$pageObject = new ProductManufacturerRebateMaintenancePage("product_manufacturer_rebates");
$pageObject->displayPage();
