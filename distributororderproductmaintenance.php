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

$GLOBALS['gPageCode'] = "DISTRIBUTORORDERPRODUCTMAINT";
require_once "shared/startup.inc";

class DistributorOrderProductMaintenancePage extends Page {

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn(array("product_id", "quantity", "location_id", "notes"));
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("location_id", "help_label", "Location to order the product from");
		$this->iDataSource->addColumnControl("location_id", "empty_text", "[Any]");
		$this->iDataSource->addColumnControl("user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] : "user_location = 0") .
			" and inactive = 0 and product_distributor_id is not null and client_id = ? order by sort_order,description", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

}

$pageObject = new DistributorOrderProductMaintenancePage("distributor_order_products");
$pageObject->displayPage();
