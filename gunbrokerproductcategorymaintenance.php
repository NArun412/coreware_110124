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

$GLOBALS['gPageCode'] = "GUNBROKERPRODUCTCATEGORYMAINT";
require_once "shared/startup.inc";

class GunbrokerProductCategoryMaintenancePage extends Page {

	function massageDataSource() {

		$gunBrokerCategories = getCachedData("gunbroker_category_choices", "all", true);
		if (empty($gunBrokerCategories)) {
			try {
				$gunBroker = new GunBroker();
				$rawCategories = $gunBroker->getCategories();
				if ($rawCategories === false) {
					$gunBrokerCategories = array();
				} else {
					$gunBrokerCategories = array();
					foreach ($rawCategories as $thisData) {
						$gunBrokerCategories[] = array("key_value"=>$thisData['category_id'],"raw_description"=>$thisData['description'], "description"=>$thisData['description'] . " (ID " . $thisData['category_id'] . ")");
					}
					setCachedData("gunbroker_category_choices", "all", $gunBrokerCategories, 24, true);
				}
			} catch (Exception $exception) {
				$gunBrokerCategories = array();
			}
		}
		$this->iDataSource->addColumnControl("category_identifier", "data_type", "select");
		$this->iDataSource->addColumnControl("category_identifier", "choices", $gunBrokerCategories);
		$this->iDataSource->addColumnControl("category_identifier", "form_label", "GunBroker Category");
		$this->iDataSource->addColumnControl("category_identifier", "help_label", "If no categories are available, your GunBroker credentials are wrong");
		$this->iDataSource->addColumnControl("category_identifier", "not_null", true);

		$this->iDataSource->addFilterWhere("product_category_id in (select product_category_id from product_categories where client_id = " . $GLOBALS['gClientId'] . ")");
	}

}

$pageObject = new GunbrokerProductCategoryMaintenancePage("gunbroker_product_categories");
$pageObject->displayPage();
