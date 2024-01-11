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

$GLOBALS['gPageCode'] = "PRODUCTINVENTORYNOTIFICATIONMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function massageDataSource() {
		if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
		}
		$this->iDataSource->addColumnControl("user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("email_address", "help_label", "Who gets notification?");
		$this->iDataSource->addColumnControl("email_address", "default_value", $GLOBALS['gUserRow']['email_address']);
		$this->iDataSource->addColumnControl("product_distributor_id", "empty_text", "[Total Inventory]");
		$this->iDataSource->addColumnControl("product_distributor_id", "help_label", "Check inventory level from this distributor");
		$this->iDataSource->addColumnControl("product_distributor_id", "get_choices", "productDistributorChoices");
		$this->iDataSource->addColumnControl("comparator", "data_type", "select");
		$this->iDataSource->addColumnControl("comparator", "choices", array("<" => "Less than", "<=" => "Less than or equal", "=>" => "Greater than or equal", ">" => "Greater than", "=" => "Equal"));
		$this->iDataSource->addColumnControl("comparator", "initial_value", ">");
		$this->iDataSource->addColumnControl("comparator", "form_label", "Inventory quantity is");
		$this->iDataSource->addColumnControl("location_id", "help_label", "Place order from this location");
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("location_id", "empty_text", "[Any]");

		$this->iDataSource->addColumnControl("location_id", "form_line_classes", "place-order");
		$this->iDataSource->addColumnControl("order_quantity", "form_line_classes", "place-order");
		$this->iDataSource->addColumnControl("use_lowest_price", "form_line_classes", "place-order");
		$this->iDataSource->addColumnControl("use_lowest_price", "data_type", "select");
		$this->iDataSource->addColumnControl("use_lowest_price", "default_value", "1");
		$this->iDataSource->addColumnControl("use_lowest_price", "help_label", "Only relevant if ordering from any location");
		$this->iDataSource->addColumnControl("use_lowest_price", "choices", array("1"=>"Use Lowest Price Location","0"=>"Use Location Sort Order"));
		$this->iDataSource->addColumnControl("allow_multiple", "form_line_classes", "place-order");
		$this->iDataSource->addColumnControl("allow_multiple", "form_label", "Allow orders from multiple locations (only relevant if ordering from any location)");
	}

	function onLoadJavascript() {
		?>
		<script>
			$("#place_order").click(function () {
				if ($(this).prop("checked")) {
					$(".place-order").removeClass("hidden");
				} else {
					$(".place-order").addClass("hidden");
				}
			})
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			function afterGetRecord() {
				if ($(this).prop("checked")) {
					$(".place-order").removeClass("hidden");
				} else {
					$(".place-order").addClass("hidden");
				}
			}
		</script>
		<?php
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] : "user_location = 0") .
				" and inactive = 0 and product_distributor_id is not null and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function productDistributorChoices($showInactive = false) {
		$productDistributorChoices = array();
		$resultSet = executeQuery("select * from product_distributors where product_distributor_id in (select product_distributor_id from locations where " .
				(empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] : "user_location = 0") .
				" and inactive = 0 and product_distributor_id is not null and client_id = ?) order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productDistributorChoices[$row['product_distributor_id']] = array("key_value" => $row['product_distributor_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $productDistributorChoices;
	}

}

$pageObject = new ThisPage("product_inventory_notifications");
$pageObject->displayPage();
