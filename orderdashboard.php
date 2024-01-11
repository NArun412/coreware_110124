<?php

/*		This software is the unpublished, confidential, proprietary, intellectual
		property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
		or used in any manner without expressed written consent from Kim David Software, LLC.
		Kim David Software, LLC owns all rights to this work and intends to keep this
		software confidential so as to maintain its value as a trade secret.

		Copyright 2004-Present, Kim David Software, LLC.

		WARNING! This code is part of the Kim David Software's Coreware system.
		Changes made to this source file will be lost when new versions of the
		system are installed.
*/

$GLOBALS['gPageCode'] = "ORDERDASHBOARD";
require_once "shared/startup.inc";
require_once "classes/easypost/lib/easypost.php";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class OrderDashboardPage extends Page {

	var $iSearchContactFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");
	var $iSearchFields = array("full_name", "phone_number");
	var $iEasyPostActive = false;
	var $iFFLActive = false;
	var $iPredefinedPackages = array("FedExEnvelope" => "FedEx Envelope",
		"FedExBox" => "FedEx Box",
		"FedExPak" => "FedEx Pak",
		"FedExTube" => "FedEx Tube",
		"FedEx10kgBox" => "FedEx 10kg Box",
		"FedEx25kgBox" => "FedEx 25kg Box",
		"FedExSmallBox" => "FedEx Small Box",
		"FedExMediumBox" => "FedEx Medium Box",
		"FedExLargeBox" => "FedEx Large Box",
		"FedExExtraLargeBox" => "FedEx Extra Large Box",
		"UPSLetter" => "UPS Letter",
		"UPSExpressBox" => "UPS Express Box",
		"UPS25kgBox" => "UPS 25kg Box",
		"UPS10kgBox" => "UPS 10kg Box",
		"Tube" => "UPS Tube",
		"Pak" => "UPS Pak",
		"SmallExpressBox" => "UPS Small Express Box",
		"MediumExpressBox" => "UPS Medium Express Box",
		"LargeExpressBox" => "UPS Large Express Box",
		"FlatRateEnvelope" => "USPS Flat Rate Envelope",
		"FlatRateLegalEnvelope" => "USPS Flat Rate Legal Envelope",
		"FlatRatePaddedEnvelope" => "USPS Flat Rate Padded Envelope",
		"SoftPack" => "USPS Soft Pack",
		"SmallFlatRateBox" => "USPS Small Flat Rate Box",
		"MediumFlatRateBox" => "USPS Medium Flat Rate Box",
		"LargeFlatRateBox" => "USPS Large Flat Rate Box",
		"RegionalRateBoxA" => "USPS Regional Rate Box A",
		"RegionalRateBoxB" => "USPS Regional Rate Box B");
	var $iHazmatOptions = array("PRIMARY_CONTAINED" => "Primary Contained",
		"PRIMARY_PACKED" => "Primary Packed",
		"PRIMARY" => "Primary",
		"SECONDARY_CONTAINED" => "Secondary Contained",
		"SECONDARY_PACKED" => "Secondary Packed",
		"SECONDARY" => "Secondary",
		"LIMITED_QUANTITY" => "Limited Quantity",
		"LITHIUM" => "Lithium");

	function setup() {
        ProductCatalog::getInventoryAdjustmentTypes();
        $orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "PAYMENTS_NOT_COMPLETE");
		if (empty($orderStatusId)) {
			$insertSet = executeQuery("insert into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,?,?,?,1)", $GLOBALS['gClientId'], "PAYMENTS_NOT_COMPLETE", "Payments may not have gotten completed", "#CC0000");
			$orderStatusId = $insertSet['insert_id'];
		}
		$resultSet = executeQuery("select * from orders where order_status_id = ? and deleted = 0 and date_completed is null and client_id = ?", $orderStatusId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$cartTotal = 0;
			$itemSet = executeQuery("select sum(quantity * sale_price) as cart_total from order_items where order_id = ?", $row['order_id']);
			if ($itemRow = getNextRow($itemSet)) {
				$cartTotal = $itemRow['cart_total'];
			}
			$paymentTotal = 0;
			$paymentSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) as payment_total from order_payments where order_id = ?", $row['order_id']);
			if ($paymentRow = getNextRow($paymentSet)) {
				$paymentTotal = $paymentRow['payment_total'];
			}
			$donationAmount = 0;
			if (!empty($row['donation_id'])) {
				$donationAmount = getFieldFromId("amount", "donations", "donation_id", $row['donation_id']);
			}
			$orderTotal = $cartTotal + $row['tax_charge'] + $row['shipping_charge'] + $row['handling_charge'] - $row['order_discount'] + $donationAmount;
			if (round($orderTotal - $paymentTotal, 2) != 0) {
				executeQuery("update orders set order_status_id = null where order_id = ?", $row['order_id']);
			}
		}

		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
		$this->iFFLActive = (!empty($fflRequiredProductTagId));

		$this->iEasyPostActive = getPreference($GLOBALS['gDevelopmentServer'] ? "EASY_POST_TEST_API_KEY" : "EASY_POST_API_KEY");

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("order_number", "full_name", "order_amount", "order_time", "order_status_id", "date_completed"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("signature", "create_shipment", "delete_shipments", "add_file", "add_note"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("save", "add"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);

			$filters = array();
			$filters['hide_deleted'] = array("form_label" => "Hide Deleted", "where" => "deleted = 0", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);

			$orangeInventoryThreshold = intval(getPageTextChunk("INVENTORY_ORANGE_LIMIT"));
			$orangeInventoryThreshold = $orangeInventoryThreshold ?: 10;

            $openOrdersSubquery = "order_id in (select order_id from orders where client_id = " . $GLOBALS['gClientId'] . " and deleted = 0 and date_completed is null)";
            $activeLocationsSubquery = "location_id in (select location_id from locations where client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and (primary_location = 1 or product_distributor_id is null))";

			$filters['product_alerts'] = array("form_label" => "Open orders with low inventory products (expect slower response times)", "where" => "order_id in (select order_id from (select order_id,product_id," .
				"(select sum(quantity) from product_inventories where product_id = order_items.product_id and " . $activeLocationsSubquery ." group by product_id) as inventory_quantity, " .
				"(select sum(quantity) from order_items oi where product_id = order_items.product_id and " . $openOrdersSubquery . ") as order_quantity, " .
				"coalesce((select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items oi where product_id = order_items.product_id and " . $openOrdersSubquery . ")),0) as shipped_quantity " .
				"from order_items where " . $openOrdersSubquery . " and quantity > coalesce((select sum(quantity) from order_shipment_items where order_item_id = order_items.order_item_id),0) " .
				"having (inventory_quantity - (order_quantity - shipped_quantity)) > 0 and (inventory_quantity - (order_quantity - shipped_quantity)) < " . $orangeInventoryThreshold . ") temp_table)",
				"data_type" => "tinyint", "conjunction" => "and");

			$filters['uncaptured'] = array("form_label" => "Includes uncaptured payments", "where" => "order_id in (select order_id from order_payments where deleted = 0 and not_captured = 1)", "data_type" => "tinyint", "conjunction" => "and");
			$filters['start_date'] = array("form_label" => "Start Order Date", "where" => "order_time >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Order Date", "where" => "order_time <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['start_date_completed'] = array("form_label" => "Start Date Completed", "where" => "date_completed is not null and date_completed >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date_completed'] = array("form_label" => "End Date Completed", "where" => "date_completed is not null and date_completed <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['over_amount'] = array("form_label" => "Cart Total Over", "where" => "(select sum(sale_price) from order_items where order_id = orders.order_id) > %filter_value%", "data_type" => "decimal", "conjunction" => "and");
			$filters['promotion_code'] = array("form_label" => "Used Promotion", "where" => "order_id in (select order_id from order_promotions join promotions using (promotion_id) where promotion_code like %like_value%)", "data_type" => "varchar", "conjunction" => "and");

			$filters['available_local_inventory'] = array("form_label" => "Unshipped product available in local inventory", "where" => "date_completed is null and deleted = 0 and " .
				"order_id in (select order_id from order_items where quantity > coalesce((select sum(quantity) from order_shipment_items where order_item_id = order_items.order_item_id),0) and " .
				"coalesce((select sum(quantity) from product_inventories where product_id = order_items.product_id and location_id in (select location_id from locations where " .
				"product_distributor_id is null)),0) > (quantity - coalesce((select sum(quantity) from order_shipment_items where order_item_id = order_items.order_item_id),0)))", "conjunction" => "and");

			$userTypes = array();
			$resultSet = executeQuery("select * from user_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$userTypes[$row['user_type_id']] = $row['description'];
			}
			$filters['user_types'] = array("form_label" => "User Type", "where" => "contact_id in (select contact_id from users where user_type_id = %key_value%)", "data_type" => "select", "choices" => $userTypes, "conjunction" => "and");

			$orderTags = array();
			$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$orderTags[$row['order_tag_id']] = $row['description'];
			}
			if (count($orderTags) > 0) {
				$filters['order_tags'] = array("form_label" => "Order Tag", "where" => "order_id in (select order_id from order_tag_links where order_tag_id = %key_value%)", "data_type" => "select", "choices" => $orderTags, "conjunction" => "and");
			}

			$productTags = array();
			$resultSet = executeQuery("select * from product_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productTags[$row['product_tag_id']] = $row['description'];
			}
			$filters['product_tags'] = array("form_label" => "Product Tag", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from product_tag_links where product_tag_id = %key_value%))", "data_type" => "select", "choices" => $productTags, "conjunction" => "and");

			$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['order_status_id_' . $row['order_status_id']] = array("form_label" => $row['description'], "where" => "order_status_id = " . $row['order_status_id'], "data_type" => "tinyint");
			}
			$filters['no_order_status'] = array("form_label" => "No Status Set", "where" => "order_status_id is null", "data_type" => "tinyint");
			$paymentMethods = array();
			$resultSet = executeQuery("select * from payment_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$paymentMethods[$row['payment_method_id']] = $row['description'];
			}
			$filters['payment_method'] = array("form_label" => "Payment Method", "where" => "payment_method_id = %key_value% or order_id in (select order_id from order_payments where payment_method_id = %key_value%)", "data_type" => "select", "choices" => $paymentMethods, "conjunction" => "and");
			$shippingMethods = array();
			$resultSet = executeQuery("select * from shipping_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$shippingMethods[$row['shipping_method_id']] = $row['description'];
			}
			$filters['shipping_method'] = array("form_label" => "Shipping Method", "where" => "shipping_method_id = %key_value%", "data_type" => "select", "choices" => $shippingMethods, "conjunction" => "and");
			$filters['all_shipped'] = array("form_label" => "All items shipped w/Tracking Numbers", "where" => "order_id not in (select order_id from order_shipments where tracking_identifier is null) and order_id not in (select order_id from order_items left outer join order_shipment_items using (order_item_id) where order_shipment_item_id is null)", "data_type" => "tinyint", "conjunction" => "and");
			$filters['none_shipped'] = array("form_label" => "No items shipped w/Tracking Numbers", "where" => "order_id not in (select order_id from order_shipments where tracking_identifier is not null)", "data_type" => "tinyint", "conjunction" => "and");
			$filters['notes'] = array("form_label" => "Notes Contain", "where" => "order_id in (select order_id from order_notes where content like %like_value%)", "data_type" => "varchar", "conjunction" => "and");
			$filters['physical_products'] = array("form_label" => "Only orders with physical products", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 0))", "data_type" => "tinyint", "conjunction" => "and");
			$filters['virtual_products'] = array("form_label" => "Only orders with virtual products", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 1))", "data_type" => "tinyint", "conjunction" => "and");
			$filters['inventory_products'] = array("form_label" => "Only orders with inventoried products", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from products where non_inventory_item = 0))", "data_type" => "tinyint", "conjunction" => "and");
			$filters['product_id'] = array("form_label" => "Orders Containing Product ID", "where" => "order_id in (select order_id from order_items where product_id = %filter_value%)", "data_type" => "int", "conjunction" => "and");
			$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
			$categories = array();
			while ($row = getNextRow($resultSet)) {
				$categories[$row['product_category_id']] = $row['description'];
			}
			$filters['product_category'] = array("form_label" => "Contains products in category", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from product_category_links where product_category_id = %key_value%))", "data_type" => "select", "choices" => $categories, "conjunction" => "and");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			if (!empty(getPreference("ORDER_DASHBOARD_PICKUP_OPTIONS"))) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("pickup_ready" => array("icon" => "fad fa-truck-pickup", "label" => getLanguageText("Ready For Pickup"), "disabled" => false)));
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("picked_up" => array("icon" => "fad fa-check-square", "label" => getLanguageText("Picked Up"), "disabled" => false)));
			}
			if (canAccessPageCode("REFUNDDASHBOARD")) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("refunds" => array("label" => getLanguageText("Refund"), "disabled" => false)));
			}

            $this->iTemplateObject->getTableEditorObject()->addCustomAction("set_status", "Set Status of Selected Orders");
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_selected_completed", "Mark Selected Orders Completed");
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_selected_not_completed", "Mark Selected Orders NOT Completed");
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("print_order_items", "Print Order Items for Selected Orders");
            $this->iTemplateObject->getTableEditorObject()->addCustomAction("auto_processing", "Auto Process Selected Orders");

            $taxjarApiToken = getPreference("taxjar_api_token");
            $taxjarApiReporting = getPreference("taxjar_api_reporting");
            if (!empty($taxjarApiToken) && !empty($taxjarApiReporting)) {
                $this->iTemplateObject->getTableEditorObject()->addCustomAction("report_taxes", "Report Taxes for Selected Orders");
            }

            if (!empty(getPreference("CORESTORE_ENDPOINT")) && !empty(getPreference("CORESTORE_API_KEY"))) {
                $this->iTemplateObject->getTableEditorObject()->addCustomAction("notify_corestore_selected", "Send Selected Orders to coreSTORE");
            }
		}

		if ((empty($_GET['url_page']) || $_GET['url_page'] == "list") && empty($this->iPageData['pre_management_header'])) {
			if (canAccessPageCode("ORDERSREPORT")) {
				$this->iPageData['pre_management_header'] = "<p>Click <a target='_blank' href='/ordersreport.php'>here</a> to get orders statistics</p>";
			}
		}

	}

	function sortPrices($a, $b) {
		if ($a['cost'] == $b['cost']) {
			if ($a['sort_order'] == $b['sort_order']) {
				return 0;
			}
			return ($a['sort_order'] > $b['sort_order']) ? 1 : -1;
		}
		return ($a['cost'] > $b['cost']) ? 1 : -1;
	}

	function sortRates($a, $b) {
		if ($a['rate'] == $b['rate']) {
			return 0;
		}
		return ($a['rate'] > $b['rate']) ? 1 : -1;
	}

	function addDataListReturnValues(&$returnArray) {
		foreach ($returnArray['data_list'] as $index => $row) {
			if (!empty($row['federal_firearms_licensee_id']['data_value'])) {
				$fflRow = (new FFL(array('license_lookup' => $row['federal_firearms_licensee_id']['data_value'])))->getFFLRow();
				if (empty($fflRow['file_id']) || strtotime($fflRow['expiration_date']) < time()) {
					$returnArray['data_list'][$index]['federal_firearms_licensee_id']['class_names'] .= " no-license";
				}
			}
		}
	}

	function dataListProcessing(&$dataList) {
		$columnList = getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageCode']);
		$columnList .= getPreference("MAINTENANCE_EXPORT_COLUMNS", $GLOBALS['gPageCode']);
		if (strpos($columnList, "first_anticipated_ship_date") != false || strpos($columnList, "first_anticipated_ship_date") != false) {
			$anticipatedShipDateSet = executeQuery("select order_id, min(anticipated_ship_date) first_anticipated_ship_date from order_items " .
				"where order_id in (select order_id from orders where client_id = ?) " .
				(getPreference("ALWAYS_SHOW_FIRST_ANTICIPATED_SHIP_DATE") ? "" : " and order_item_id not in (select order_item_id from order_shipment_items) ") .
				"group by order_id", $GLOBALS['gClientId']);
			$anticipatedShipDateArray = array();
			while ($row = getNextRow($anticipatedShipDateSet)) {
				if (!empty($row['first_anticipated_ship_date'])) {
					$anticipatedShipDateArray[$row['order_id']] = date("m/d/Y", strtotime($row['first_anticipated_ship_date']));
				}
			}
			foreach ($dataList as $index => $row) {
				if (array_key_exists($row['order_id'], $anticipatedShipDateArray)) {
					$dataList[$index]['first_anticipated_ship_date'] = $anticipatedShipDateArray[$row['order_id']];
				}
			}
		}
		if (strpos($columnList, "inventory_alert") != false || strpos($columnList, "inventory_alert") != false) {
			$orangeInventoryThreshold = intval(getPageTextChunk("INVENTORY_ORANGE_LIMIT"));
			$redInventoryThreshold = intval(getPageTextChunk("INVENTORY_RED_LIMIT"));
			$orangeInventoryThreshold = $orangeInventoryThreshold ?: 10;
			$redInventoryThreshold = $redInventoryThreshold ?: 5;

			$productSet = executeQuery("select order_id, product_id from order_items where order_id in (select order_id from orders where date_completed is null and deleted = 0) and " .
				"coalesce((select sum(quantity) from order_shipment_items where order_item_id = order_items.order_item_id and order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0)),0) < quantity");
			$productIds = array();
			$orderProductIds = array();
			while ($row = getNextRow($productSet)) {
				$orderProductIds[$row['order_id']][] = $row['product_id'];
				$productIds[$row['product_id']] = true;
			}
			freeResult($productSet);
			if (empty($productIds)) {
				return;
			}

			$productCatalog = new ProductCatalog();
			$inventoryArray = $productCatalog->getInventoryCounts(true, array_keys($productIds));
			$noInventoryProductIds = array();
			$resultSet = executeQuery("select product_id,sum(quantity) from product_inventories where location_id in (select location_id from locations where (product_distributor_id is null or primary_location = 1) and inactive = 0) and product_id in (" .
				implode(",", array_keys($productIds)) . ") group by product_id");
			while ($row = getNextRow($resultSet)) {
				if ($row['sum(quantity)'] <= 0) {
					$noInventoryProductIds[$row['product_id']] = true;
				}
			}

			foreach ($dataList as $index => $row) {
				if (array_key_exists($row['order_id'], $orderProductIds)) {
					foreach ($orderProductIds[$row['order_id']] as $productId) {
						if (array_key_exists($productId, $noInventoryProductIds)) {
							continue;
						}
						if ($inventoryArray[$productId] <= $redInventoryThreshold) {
							$dataList[$index]['inventory_alert'] = "<i class='red-text fas fa-exclamation-circle' title='Very Low Inventory'></i>";
							break;
						} elseif ($inventoryArray[$productId] <= $orangeInventoryThreshold) {
							$dataList[$index]['inventory_alert'] = "<i class='orange-text fas fa-exclamation-triangle' title='Low Inventory'></i>";
						}
					}
				}
			}
		}
	}

	function locationChoices($showInactive = false) {
		$userLocations = array();
		$resultSet = executeQuery("select * from user_locations where user_id = ?", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$userLocations[] = $row['location_id'];
		}

		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where (cannot_ship = 0 or product_distributor_id is not null) and inactive = 0 and client_id = ? order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				if (empty($userLocations) || in_array($row['location_id'], $userLocations)) {
					$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => !empty($row['inactive']), "data-location_group_id" => $row['location_group_id']);
				}
			}
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("ship_to_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("ship_to_address", "form_label", "Ship To Address");
		$this->iDataSource->addColumnControl("ship_to_address", "select_value", "if(address_id is null,(select concat_ws(',',address_1,address_2,city,state,postal_code) from contacts where contact_id = orders.contact_id),(select concat_ws(',',address_1,address_2,city,state,postal_code) from addresses where address_id = orders.address_id))");
		$this->iDataSource->addColumnControl("user_type_id", "form_label", "User Type");
		$this->iDataSource->addColumnControl("user_type_id", "data_type", "varchar");
		$this->iDataSource->addColumnControl("user_type_id", "select_value", "(select description from user_types where user_type_id = (select user_type_id from users where contact_id = orders.contact_id))");

		$this->iDataSource->addColumnControl("shipment_charges", "data_type", "decimal");
		$this->iDataSource->addColumnControl("shipment_charges", "decimal_places", "2");
		$this->iDataSource->addColumnControl("shipment_charges", "form_label", "Shipment Charges");
		$this->iDataSource->addColumnControl("shipment_charges", "select_value", "coalesce((select sum(shipping_charge) from order_shipments where order_id = orders.order_id and shipping_charge is not null),0)");

		$this->iDataSource->addColumnControl("ffl_required", "data_type", "hidden");
		$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "form_label", "FFL #");
		$this->iDataSource->addColumnControl("ffl_license_file_id", "data_type", "file");
		$this->iDataSource->addColumnControl("ffl_license_file_id", "form_label", "License File");

		$this->iDataSource->addColumnControl("ffl_sot_file_id", "data_type", "file");
		$this->iDataSource->addColumnControl("ffl_sot_file_id", "form_label", "SOT Document");

		$this->iDataSource->addColumnControl("order_maximum", "help_label", "Maximum a user can ever order");

		$this->iDataSource->addColumnControl("deleted", "data_type", "hidden");

		$this->iDataSource->addColumnLikeColumn("order_files_description", "order_files", "description");
		$this->iDataSource->addColumnLikeColumn("order_files_file_id", "order_files", "file_id");
		$this->iDataSource->addColumnControl("order_files_file_id", "no_remove", true);
		$this->iDataSource->addColumnControl("order_files_description", "not_null", false);
		$this->iDataSource->addColumnControl("order_files_file_id", "not_null", false);
		$this->iDataSource->addColumnControl("add_file", "data_type", "button");
		$this->iDataSource->addColumnControl("add_file", "button_label", "Add File");

		$this->iDataSource->addColumnLikeColumn("public_access", "order_notes", "public_access");
		$this->iDataSource->addColumnControl("add_note", "data_type", "button");
		$this->iDataSource->addColumnControl("add_note", "button_label", "Add Note");
		$this->iDataSource->addColumnControl("add_note", "classes", "keep-visible");

		$this->iDataSource->addColumnLikeColumn("location_group_id", "locations", "location_group_id");
		$this->iDataSource->addColumnControl("location_group_id", "not_null", false);
		$this->iDataSource->addColumnControl("location_group_id", "empty_text", "[All]");

		$this->iDataSource->addColumnLikeColumn("location_id", "product_inventories", "location_id");
		$this->iDataSource->addColumnControl("location_id", "not_null", false);
		$this->iDataSource->addColumnControl("location_id", "empty_text", "[None]");
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("location_id", "form_label", "Location/Distributor");

		$this->iDataSource->addColumnControl("create_shipment", "data_type", "button");
		$this->iDataSource->addColumnControl("create_shipment", "button_label", "Create Shipment");

		$this->iDataSource->addColumnControl("delete_shipments", "data_type", "button");
		$this->iDataSource->addColumnControl("delete_shipments", "button_label", "Delete Shipments");

		$this->iDataSource->addColumnControl("order_number", "form_label", "Order #");
		$this->iDataSource->addColumnControl("order_time", "not_sortable", true);

        $count = DataTable::getLimitedCount("orders", 200000, true);
		if (empty($count)) {
			$count = getFieldFromId("count(*)", "orders", "client_id", $GLOBALS['gClientId']);
		}
		if ($count > 200000) {
			$this->iDataSource->addColumnControl("order_amount", "not_sortable", true);
		}
		if ($count > 50000) {
			$this->iDataSource->addColumnControl("order_status_id", "not_sortable", true);
		}

		$this->iDataSource->addColumnControl("order_amount", "form_label", "Amount");
		$this->iDataSource->addColumnControl("order_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_amount", "select_value", "(select sum(order_items.sale_price * order_items.quantity) " .
			"from order_items where orders.order_id = order_items.order_id and deleted = 0) + shipping_charge + tax_charge + handling_charge - order_discount");
		$this->iDataSource->addColumnControl("order_subtotal", "form_label", "Subtotal");
		$this->iDataSource->addColumnControl("order_subtotal", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_subtotal", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_subtotal", "select_value", "(select sum(order_items.sale_price * order_items.quantity) " .
			"from order_items where orders.order_id = order_items.order_id and deleted = 0) - order_discount");

		$this->iDataSource->addColumnControl("date_completed", "form_label", "Completed");

		$this->iDataSource->addColumnLikeColumn("content", "order_notes", "content");
		$this->iDataSource->addColumnControl("content", "not_null", false);
		$this->iDataSource->addColumnControl("content", "classes", "keep-visible");

		$this->iDataSource->addColumnControl("address_id", "choices", array());
		$this->iDataSource->addColumnControl("account_id", "choices", array());
		$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "choices", array());
		$this->iDataSource->addColumnControl("user_id", "choices", array());

		$this->iDataSource->addColumnControl("inventory_alert", "data_type", "varchar");
		$this->iDataSource->addColumnControl("inventory_alert", "dont_escape", "true");
		$this->iDataSource->addColumnControl("inventory_alert", "readonly", true);
		$this->iDataSource->addColumnControl("inventory_alert", "form_label", "Inventory Alert");
		$this->iDataSource->addColumnControl("inventory_alert", "help_label", "Alert if any products in this order have low inventory");
		$this->iDataSource->addColumnControl("inventory_alert", "not_sortable", true);

		$this->iDataSource->addColumnControl("first_anticipated_ship_date", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_anticipated_ship_date", "readonly", "true");
		$this->iDataSource->addColumnControl("first_anticipated_ship_date", "form_label", "First Anticipated Ship Date");
		$this->iDataSource->addColumnControl("first_anticipated_ship_date", "help_label", "Earliest anticipated ship date of all unshipped items in order");
		if (!$GLOBALS['gUserRow']['full_client_access']) {
			$userLocationId = getFieldFromId("user_location_id", "user_locations", "user_id", $GLOBALS['gUserId']);
			if (!empty($userLocationId)) {
				$this->iDataSource->addFilterWhere("shipping_method_id in (select shipping_method_id from shipping_methods where pickup = 1 and location_id is not null and " .
					"location_id in (select location_id from user_locations where user_id = " . $GLOBALS['gUserId'] . "))");
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_addon_form_summary":
				$returnArray['content'] = "";
				$orderItemAddonId = getFieldFromId("order_item_addon_id", "order_item_addons", "order_item_addon_id", $_GET['order_item_addon_id'],
					"order_item_id in (select order_item_id from orders where order_id in (select order_id from orders where client_id = ?))", $GLOBALS['gClientId']);
				if (empty($orderItemAddonId)) {
					ajaxResponse($returnArray);
					break;
				}
				$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_addon_id = ?", $orderItemAddonId);
				if ($addonRow = getNextRow($addonSet)) {
					if (function_exists("_localProductAddonSummary")) {
						ob_start();
						_localProductAddonSummary($addonRow['form_definition_id'], $addonRow['content']);
						$returnArray['content'] = ob_get_clean();
					}
					if (empty($returnArray['content'])) {
						$formDefinitionCode = getFieldFromId("form_definition_code", "form_definitions", "form_definition_id", $addonRow['form_definition_id']);
						$summaryFragmentContent = getFieldFromId("content", "fragments", "fragment_code", $formDefinitionCode . "_SUMMARY");
						if (!empty($summaryFragmentContent)) {
							$additionalSubstitutions = array_merge($addonRow, (empty($addonRow['content']) ? array() : json_decode($addonRow['content'], true)));
							unset($additionalSubstitutions['content']);
							$returnArray['content'] = Placeholders::massageContent($summaryFragmentContent, $additionalSubstitutions);
						}
					}
				}
				if (empty($returnArray['content']) && !empty($addonRow['content'])) {
					$content = json_decode($addonRow['content'], true);
					$skipFields = array("in_progress_form_id", "in_progress_form_code", "parent_form_id", "form_definition_id", "form_definition_code", "_add_hash", "create_contact_pdf", "_form_html", "_template_css_filename", "shopping_cart_item_id", "product_addon_id");
					$returnArray['content'] = "<table class='grid-table'><tr><th>Field</th><th>Value</th></tr>";
					foreach ($content as $fieldName => $fieldValue) {
						if (in_array($fieldName, $skipFields)) {
							continue;
						}
						$description = getFieldFromId("form_label", "form_fields", "form_field_code", $fieldName);
						if (empty($description)) {
							$description = "- " . htmlText(ucwords(str_replace("_", " ", $fieldName)));
						}
						$returnArray['content'] .= "<tr><td>" . $description . "</td><td>" . htmlText($fieldValue) . "</td></tr>";
					}
					$returnArray['content'] .= "</table>";
				}
				ajaxResponse($returnArray);
				break;
			case "resend_gift_card_email":
				$orderItemRow = getRowFromId("order_items", "order_item_id", $_GET['order_item_id']);
				if (empty($orderItemRow)) {
					$returnArray['error_message'] = "Invalid Order Item";
					ajaxResponse($returnArray);
					break;
				}
				$orderId = $orderItemRow['order_id'];
				$contactRow = Contact::getContact(getFieldFromId("contact_id", "orders", "order_id", $orderId));
				$resultSet = executeQuery("select * from gift_cards where order_item_id = ?", $orderItemRow['order_item_id']);
				$emailSent = false;
				while ($row = getNextRow($resultSet)) {
					$substitutions = array();
					$substitutions['amount'] = $row['balance'];
					$substitutions['description'] = $row['description'];
					$substitutions['gift_card_number'] = $row['gift_card_number'];

					$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS')", $GLOBALS['gClientId']);
					while ($customFieldRow = getNextRow($customFieldSet)) {
						$substitutions[strtolower($customFieldRow['custom_field_code'])] = CustomField::getCustomFieldData($orderItemRow['order_item_id'], $customFieldRow['custom_field_code'], "ORDER_ITEMS");
					}

					$emailId = "";
					if (!empty($substitutions['recipient_email_address'])) {
						$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_GIFT_CARD_GIVEN", "inactive = 0");
						$emailAddress = $substitutions['recipient_email_address'];
					}
					if (empty($emailId)) {
						$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_GIFT_CARD", "inactive = 0");
					}
					$subject = "Gift Card";
					$body = "Your gift card number is %gift_card_number%, for the amount of %amount%.";
					$emailSent = sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "substitutions" => $substitutions, "email_addresses" => $emailAddress, "contact_id" => $contactRow['contact_id']));
				}
				$returnArray['info_message'] = ($emailSent ? "" : "NO ") . "Email Resent";
				ajaxResponse($returnArray);
				break;
			case "issue_gift_card":
				$giftCard = new GiftCard();
				$returnArray = $giftCard->issueGiftCards($_GET['order_item_id']);
				if (empty($returnArray['error_message'])) {
					$returnArray['info_message'] = "Gift Card Issued";
				}
				ajaxResponse($returnArray);
				break;
			case "print_order_items":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$returnArray['order_ids'] = implode("|", $orderIds);
				ajaxResponse($returnArray);
				break;
			case "get_fraud_report":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$noFraudResult = CustomField::getCustomFieldData($orderId, "NOFRAUD_RESULT", "ORDERS");
				if (!empty($noFraudResult)) {
					$noFraudResult = json_decode($noFraudResult, true);
					switch ($noFraudResult['decision']) {
						case "pass":
							$noFraudClass = "low-risk-fraud";
							break;
						case "review":
							$noFraudClass = "medium-risk-fraud";
							break;
						default:
							$noFraudClass = "high-risk-fraud";
							break;
					}
					$noFraudUrl = "https://portal.nofraud.com/transaction/" . $noFraudResult['results'][0]['id'];
					$returnArray['fraud_report'] = "<p>This transaction was reviewed by NoFraud. <a target='_blank' href='" . $noFraudUrl . "'>See this order on NoFraud.com</a>.</p>";
					$returnArray['fraud_score'] = "<span class='fraud-score " . $noFraudClass . "'>" . $noFraudResult['decision'] . "</span>";
					ajaxResponse($returnArray);
				}
				$ipQualityData = Order::getOrderFraudData($orderId);
				if ($ipQualityData === false) {
					$returnArray['fraud_report'] = "<p>CoreGUARD is not yet activated for your client. Contact your Coreware rep to inquire about CoreGUARD to help protect your store from fraud.</p>";
					$returnArray['fraud_score'] = "N/A";
					ajaxResponse($returnArray);
					break;
				}
				$fraudClass = "low-risk-fraud";
				if ($ipQualityData['fraud_score'] >= 85) {
					$fraudClass = "high-risk-fraud";
				} elseif ($ipQualityData['fraud_score'] >= 75) {
					$fraudClass = "suspicious-fraud";
				} elseif ($ipQualityData['fraud_score'] >= 40) {
					$fraudClass = "medium-risk-fraud";
				}
				$riskClass = "low-risk-fraud";
				if ($ipQualityData['transaction_details']['risk_score'] >= 90) {
					$riskClass = "high-risk-fraud";
				} elseif ($ipQualityData['transaction_details']['risk_score'] >= 85) {
					$riskClass = "risky-fraud";
				} elseif ($ipQualityData['transaction_details']['risk_score'] >= 75) {
					$riskClass = "suspicious-fraud";
				} elseif ($ipQualityData['transaction_details']['risk_score'] >= 40) {
					$riskClass = "medium-risk-fraud";
				}
				if ($ipQualityData['transaction_details']['risk_score'] > $ipQualityData['fraud_score']) {
					$summaryScore = $ipQualityData['transaction_details']['risk_score'];
					$summaryClass = $riskClass;
				} else {
					$summaryScore = $ipQualityData['fraud_score'];
					$summaryClass = $fraudClass;
				}
				ob_start();
				$userBillingDetails = "";
				if (!empty($ipQualityData['called_parameters']['billing_address_1'])) {
					$userBillingDetails = $ipQualityData['called_parameters']['billing_first_name'] . " " . $ipQualityData['called_parameters']['billing_last_name'] . "<br><span class='fraud-info'>" .
						$ipQualityData['called_parameters']['billing_address_1'] . "<br>" . $ipQualityData['called_parameters']['billing_city'] . " " . $ipQualityData['called_parameters']['billing_postcode'] . " " . $ipQualityData['called_parameters']['billing_country'] . "</span><br>" .
						($ipQualityData['transaction_details']['valid_billing_address'] ? "<span class='low-risk-fraud'>Billing address is valid</span>" : "<span class='high-risk-fraud'>Risky Billing Address</span>");
				}
				$userShippingDetails = "";
				if (!empty($ipQualityData['called_parameters']['shipping_address_1']) && $ipQualityData['called_parameters']['shipping_address_1'] != $ipQualityData['called_parameters']['billing_address_1']) {
					$userShippingDetails = $ipQualityData['called_parameters']['shipping_first_name'] . " " . $ipQualityData['called_parameters']['shipping_last_name'] . "<br><span class='fraud-info'>" .
						$ipQualityData['called_parameters']['shipping_address_1'] . "<br>" . $ipQualityData['called_parameters']['shipping_city'] . " " . $ipQualityData['called_parameters']['shipping_postcode'] . " " . $ipQualityData['called_parameters']['shipping_country'] . "</span><br>" .
						($ipQualityData['transaction_details']['valid_shipping_address'] ? "<span class='low-risk-fraud'>Shipping address is valid</span>" : "<span class='high-risk-fraud'>Risky Shipping Address</span>");
				}
				?>
				<div id="fraud_report_content">
					<h2>User & Transaction Scoring Details</h2>
					<?php if (!empty($userBillingDetails)) { ?>
						<div class='fraud-section'>
							<div>User Billing Details</div>
							<div><?= $userBillingDetails ?></div>
						</div>
					<?php } ?>
					<?php if (!empty($userShippingDetails)) { ?>
						<div class='fraud-section'>
							<div>User Shipping Details</div>
							<div><?= $userShippingDetails ?></div>
						</div>
					<?php } ?>
					<?php if (!empty($ipQualityData['called_parameters']['billing_email'])) { ?>
						<div class='fraud-section'>
							<div>User Email</div>
							<div><?= $ipQualityData['called_parameters']['billing_email'] ?><br>
								<?= ($ipQualityData['transaction_details']['valid_billing_email'] ? "<span class='low-risk-fraud'>Email passed reputation check</span>" : "<span class='high-risk-fraud'>Risky Email Address</span>") ?>
							</div>
						</div>
					<?php } ?>
					<?php if (!empty($ipQualityData['called_parameters']['billing_phone'])) { ?>
						<div class='fraud-section'>
							<div>User Phone</div>
							<div><?= formatPhoneNumber($ipQualityData['called_parameters']['billing_phone']) ?><br>
								<?= ($ipQualityData['transaction_details']['risky_billing_phone'] ? "<span class='high-risk-fraud'>Risky Phone Number</span>" : "<span class='low-risk-fraud'>Phone Number passed reputation checks</span>") ?>
							</div>
						</div>
					<?php } ?>
					<div class='fraud-section'>
						<div>Risk Score</div>
						<div><span class='fraud-score <?= $riskClass ?>'><?= $ipQualityData['transaction_details']['risk_score'] ?></span><br><span class='fraud-info'>75+ = suspicious | 85+ = risky | 90+ = high risk</span></div>
					</div>
					<div class='fraud-section'>
						<div>Phone/Name Identity Match</div>
						<div><?= $ipQualityData['transaction_details']['phone_name_identity_match'] ?><br>
							<span class='fraud-info'>"Unknown" - no checks processed,<br>"Match" - positive identity match,<br>"Mismatch" - data matches another user,<br>"No Match" - could not pair identity data.</span>
						</div>
					</div>
					<div class='fraud-section'>
						<div>Phone/Email Identity Match</div>
						<div><?= $ipQualityData['transaction_details']['phone_email_identity_match'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Phone/Address Identity Match</div>
						<div><?= $ipQualityData['transaction_details']['phone_address_identity_match'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Email/Name Identity Match</div>
						<div><?= $ipQualityData['transaction_details']['email_name_identity_match'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Name/Address Identity Match</div>
						<div><?= $ipQualityData['transaction_details']['name_address_identity_match'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Address/Email Identity Match</div>
						<div><?= $ipQualityData['transaction_details']['address_email_identity_match'] ?></div>
					</div>

					<h2>IP Address Reputation & Risk Details — <?= $ipQualityData['called_parameters']['ip_address'] ?></h2>
					<img class='image-right' src='/images/fraud_explained.jpg'>
					<div class='fraud-section'>
						<div>Fraud Score</div>
						<div><span class='fraud-score <?= $fraudClass ?>'><?= $ipQualityData['fraud_score'] ?></span><br><span class='fraud-info'>75+ is suspicious | 85+ is high risk</span></div>
					</div>
					<div class='fraud-section'>
						<div>Location</div>
						<div><?= $ipQualityData['city'] . ", " . $ipQualityData['region'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Device Details</div>
						<div><?= $ipQualityData['operating_system'] . ", " . $ipQualityData['browser'] . "<br>" . $ipQualityData['device_brand'] . " " . $ipQualityData['device_model'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Connection Type</div>
						<div><?= $ipQualityData['connection_type'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Hostname</div>
						<div><?= $ipQualityData['host'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>ISP</div>
						<div><?= $ipQualityData['ISP'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Organization</div>
						<div><?= $ipQualityData['organization'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>ASN</div>
						<div><?= $ipQualityData['ASN'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Proxy Detection</div>
						<div><?= ($ipQualityData['proxy'] ? "<span class='high-risk-fraud'>Suspected Proxy</span>" : "<span class='low-risk-fraud'>No Proxy Activity</span>") ?></div>
					</div>
					<div class='fraud-section'>
						<div>VPN Detection</div>
						<div><?= ($ipQualityData['vpn'] ? "<span class='high-risk-fraud'>Suspected VPN</span>" : "<span class='low-risk-fraud'>No VPN Activity</span>") ?></div>
					</div>
					<div class='fraud-section'>
						<div>TOR Detection</div>
						<div><?= ($ipQualityData['tor'] ? "<span class='high-risk-fraud'>Suspected TOR</span>" : "<span class='low-risk-fraud'>No TOR Activity</span>") ?></div>
					</div>

					<?php
					switch ($ipQualityData['abuse_velocity']) {
						case "high":
							$abuseClass = "high-risk-fraud";
							break;
						case "medium":
							$abuseClass = "suspicious-fraud";
							break;
						case "low":
							$abuseClass = "medium-risk-fraud";
							break;
						default:
							$abuseClass = "low-risk-fraud";
							break;
					}
					?>
					<div class='fraud-section'>
						<div>Abuse Velocity</div>
						<div><span class="<?= $abuseClass ?>"><?= ucwords($ipQualityData['abuse_velocity']) ?></span></div>
					</div>
					<div class='fraud-section'>
						<div>Recent Abuse</div>
						<div><?= ($ipQualityData['recent_abuse'] ? "<span class='high-risk-fraud'>Recent Abuse Detected</span>" : "<span class='low-risk-fraud'>Clean</span>") ?></div>
					</div>
					<div class='fraud-section'>
						<div>BOT Activity</div>
						<div><?= ($ipQualityData['bot_status'] ? "<span class='high-risk-fraud'>BOT Activity Detected</span>" : "<span class='low-risk-fraud'>Clean</span>") ?></div>
					</div>
					<div class='fraud-section'>
						<div>Time Zone</div>
						<div><?= $ipQualityData['timezone'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Geolocation</div>
						<div><?= $ipQualityData['latitude'] . ", " . $ipQualityData['longitude'] ?></div>
					</div>
					<div class='fraud-section'>
						<div>Fraud Request ID</div>
						<div><?= $ipQualityData['request_id'] ?></div>
					</div>
				</div>
				<?php
				$returnArray['fraud_report'] = ob_get_clean();
				$returnArray['fraud_score'] = "<span class='fraud-score " . $summaryClass . "'>" . $summaryScore . "</span>";
				ajaxResponse($returnArray);
				break;
			case "remove_ffl":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderDataTable = new DataTable("orders");
				$orderDataTable->setSaveOnlyPresent(true);
				if (!$orderDataTable->saveRecord(array("name_values" => array("federal_firearms_licensee_id" => ""), "primary_id" => $orderId))) {
					$returnArray['error_message'] = $orderDataTable->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "set_serial_number":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_item_id", $_POST['order_item_id'], "order_id = ?", $orderId);
				$orderItemSerialNumberId = getFieldFromId("order_item_serial_number_id", "order_item_serial_numbers", "order_item_serial_number_id", $_POST['primary_id'],
					"order_item_id = ?", $orderItemId);
				$dataTable = new DataTable("order_item_serial_numbers");
				$returnArray['primary_id'] = $dataTable->saveRecord(array("name_values" => array("order_item_id" => $orderItemId, "serial_number" => $_POST['serial_number']), "primary_id" => $orderItemSerialNumberId));
				ajaxResponse($returnArray);
				break;
			case "remove_order_item_serial_number":
				$dataTable = new DataTable("order_item_serial_numbers");
				$orderItemSerialNumberId = getFieldFromId("order_item_serial_number_id", "order_item_serial_numbers", "order_item_serial_number_id", $_GET['delete_id'],
					"order_item_id = ?", $_GET['order_item_id']);
				$dataTable->deleteRecord(array("primary_id" => $orderItemSerialNumberId));
				ajaxResponse($returnArray);
				break;
			case "save_order_tag":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$orderTagId = getFieldFromId("order_tag_id", "order_tags", "order_tag_id", $_GET['order_tag_id']);
				if (empty($orderTagId)) {
					$returnArray['error_message'] = "Invalid Order Tag";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_GET['checked'])) {
					executeQuery("delete from order_tag_links where order_id = ? and order_tag_id = ?", $orderId, $orderTagId);
				} else {
					executeQuery("insert ignore into order_tag_links (order_id,order_tag_id) values (?,?)", $orderId, $orderTagId);
				}
				ajaxResponse($returnArray);
				break;
			case "report_taxes":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = 0;
				foreach ($orderIds as $orderId) {
					if (Order::reportOrderToTaxjar($orderId)) {
						$count++;
					}
				}
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $count . " order" . ($count == 1 ? "" : "s") . " reported to Taxjar";
				ajaxResponse($returnArray);
				break;
			case "auto_processing":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					Order::processOrderAutomation($row['primary_identifier']);
				}
				ajaxResponse($returnArray);
				break;
			case "set_status":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_POST['order_status_id']);
				$markCompleted = getFieldFromId("mark_completed", "order_status", "order_status_id", $_POST['order_status_id']);
				$count = 0;
				$orderIdList = "";
				if (!empty($orderIds) && !empty($orderStatusId)) {
					foreach ($orderIds as $orderId) {
						if (Order::updateOrderStatus($orderId, $orderStatusId, false, false)) {
							$orderIdList .= (empty($orderIdList) ? "" : ",") . $orderId;
						}
					}
				}
				coreSTORE::orderNotification($orderIdList, "update_status");
				ajaxResponse($returnArray);
				break;
			case "order_picked_up":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = Order::markOrderPickedUp($orderId);
				ajaxResponse($returnArray);

				break;
			case "mark_ready_for_pickup":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = Order::markOrderReadyForPickup($orderId);
				ajaxResponse($returnArray);
				break;
			case "mark_selected_completed":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = 0;
				$orderIdList = "";
				foreach ($orderIds as $orderId) {
					if (Order::markOrderCompleted($orderId, date("Y-m-d"), false)) {
						$orderIdList .= (empty($orderIdList) ? "" : ",") . $orderId;
						$count++;
					}
				}
				coreSTORE::orderNotification($orderIdList, "mark_completed");
				$returnArray['info_message'] = $count . " orders marked completed";
				ajaxResponse($returnArray);
				break;
			case "notify_corestore_selected":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = count($orderIds);
				foreach ($orderIds as $orderId) {
					coreSTORE::orderNotification($orderId);
				}
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $count . " order" . ($count == 1 ? "" : "s") . " sent to coreSTORE";
				ajaxResponse($returnArray);
				break;
			case "mark_selected_not_completed":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = 0;
				$orderIdList = "";
				foreach ($orderIds as $orderId) {
					$orderId = getFieldFromId("order_id", "orders", "order_id", $orderId, "date_completed is not null");
					if (empty($orderId)) {
						ajaxResponse($returnArray);
						break;
					}
					$ordersTable = new DataTable("orders");
					$ordersTable->setSaveOnlyPresent(true);
					if ($ordersTable->saveRecord(array("name_values" => array("date_completed" => ""), "primary_id" => $orderId))) {
						$orderIdList .= (empty($orderIdList) ? "" : ",") . $orderId;
						$count++;
					}
				}
				coreSTORE::orderNotification($orderIdList, "mark_not_completed");
				$returnArray['info_message'] = $count . " orders marked NOT completed";
				ajaxResponse($returnArray);
				break;
			case "capture_payment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_payment_id", $_GET['order_payment_id'], "order_id = ?", $orderId);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order Payment";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentRow = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
				if (empty($orderPaymentRow['not_captured'])) {
					$returnArray['info_message'] = "Payment already captured";
					$returnArray['transaction_identifier'] = $orderPaymentRow['transaction_identifier'];
					ajaxResponse($returnArray);
					break;
				}
				$accountRow = getRowFromId("accounts", "account_id", $orderPaymentRow['account_id']);
				$merchantAccountId = $accountRow['merchant_account_id'] ?: $GLOBALS['gMerchantAccountId'];
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				if (!$eCommerce) {
					$returnArray['error_message'] = "Unable to connect to Merchant Gateway";
					ajaxResponse($returnArray);
					break;
				}
				$success = $eCommerce->captureCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier'], "authorization_code" => $orderPaymentRow['authorization_code']));
				$response = $eCommerce->getResponse();
				if ($success) {
					executeQuery("update order_payments set payment_time = now(),transaction_identifier = ?,not_captured = 0 where order_payment_id = ?", $response['transaction_id'], $orderPaymentId);
					$returnArray['transaction_identifier'] = $response['transaction_id'];
					$returnArray['info_message'] = "Transaction successfully captured.";
				} else {
					// Make sure the payment was not just captured
					usleep(100000);
					$orderPaymentRow = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
					if (empty($orderPaymentRow['not_captured'])) {
						$returnArray['info_message'] = "Payment already captured";
						$returnArray['transaction_identifier'] = $orderPaymentRow['transaction_identifier'];
						ajaxResponse($returnArray);
						break;
					}
					$paymentAmount = $orderPaymentRow['amount'] + $orderPaymentRow['shipping_charge'] + $orderPaymentRow['tax_charge'] + $orderPaymentRow['handling_charge'];
					$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $paymentAmount, "order_number" => $orderId,
						"merchant_identifier" => $accountRow['merchant_identifier'], "account_token" => $accountRow['account_token'], "address_id" => $accountRow['address_id']));
					$response = $eCommerce->getResponse();
					if ($success) {
						executeQuery("update order_payments set payment_time = now(),transaction_identifier = ?,not_captured = 0 where order_payment_id = ?", $response['transaction_id'], $orderPaymentId);
						$returnArray['transaction_identifier'] = $response['transaction_id'];
						$returnArray['info_message'] = "Transaction capture failed, but payment authorized and captured.";
					} else {
						$returnArray['error_message'] = "Payment transaction unable to be captured";
					}
				}

				$notCaptured = false;
				$resultSet = executeQuery("select * from order_payments where order_id = ? and not_captured = 1 and deleted = 0", $orderId);
				if ($row = getNextRow($resultSet)) {
					$notCaptured = true;
				}
				if ($notCaptured) {
					$returnArray['_capture_message'] = "Shipments cannot be created until all payments are captured";
				} else {
					$returnArray['_capture_message'] = "";
				}

				Order::updateGunbrokerOrder($orderId);
				ajaxResponse($returnArray);

				break;
			case "undelete_payment":
			case "delete_payment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_payment_id", $_GET['order_payment_id'], "order_id = ?", $orderId);
				if (empty($orderPaymentId)) {
					$returnArray['error_message'] = "Invalid Order Payment";
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable("order_payments");
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("name_values" => array("deleted" => ($_GET['url_action'] == "delete_payment" ? 1 : 0)), "primary_id" => $orderPaymentId))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
				}
				$orderPaymentRow = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
				$returnArray['not_captured'] = (empty($orderPaymentRow['deleted']) ? (empty($orderPaymentRow['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");

				$notCaptured = false;
				$resultSet = executeQuery("select * from order_payments where order_id = ? and not_captured = 1 and deleted = 0", $orderId);
				if ($row = getNextRow($resultSet)) {
					$notCaptured = true;
				}
				if ($notCaptured) {
					$returnArray['_capture_message'] = "Shipments cannot be created until all payments are captured";
				} else {
					$returnArray['_capture_message'] = "";
				}
				ajaxResponse($returnArray);
				break;
			case "save_payment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_POST['payment_time'])) {
					$_POST['payment_time'] = date("Y-m-d H:i:s");
				} else {
					$paymentDate = date("Y-m-d", strtotime($_POST['payment_time']));
					if ($paymentDate == date("Y-m-d")) {
						$_POST['payment_time'] = $paymentDate . " " . date("H:i:s");
					}
				}
				$dataTable = new DataTable("order_payments");
				$_POST['order_id'] = $orderId;
				if (!$dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => ""))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
				} else {
					$orderPayments = array();
					$resultSet = executeQuery("select * from order_payments where order_id = ? order by payment_time", $orderId);
					while ($row = getNextRow($resultSet)) {
						if (!empty($row['deleted'])) {
							continue;
						}
						$row['payment_time'] = date("m/d/Y g:i a", strtotime($row['payment_time']));
						$row['not_captured'] = (empty($row['deleted']) ? (empty($row['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");
						$row['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
						$row['account_number'] = (empty($row['account_id']) ? $row['reference_number'] : substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -8));
						$row['total_amount'] = number_format($row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'], 2, ".", ",");
						$row['amount'] = number_format($row['amount'], 2, ".", ",");
						$row['shipping_charge'] = number_format($row['shipping_charge'], 2, ".", ",");
						$row['tax_charge'] = number_format($row['tax_charge'], 2, ".", ",");
						$row['handling_charge'] = number_format($row['handling_charge'], 2, ".", ",");
						$orderPayments[] = $row;
					}
					$returnArray['order_payments'] = $orderPayments;
				}
				Order::updateGunbrokerOrder($orderId);
				ajaxResponse($returnArray);
				break;
			case "distributor_order_product":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				$productId = getFieldFromId("product_id", "order_items", "product_id", $_POST['product_id'], "order_id = ?", $orderId);
				if (empty($orderId) || empty($productId)) {
					$returnArray['error_message'] = "Invalid Product";
					ajaxResponse($returnArray);
					break;
				}
				$quantity = $_POST['quantity'];
				if (empty($quantity) || !is_numeric($quantity)) {
					$returnArray['error_message'] = "Invalid Quantity";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['order_item_id'])) {
					executeQuery("delete from distributor_order_products where order_item_id = ?", $_POST['order_item_id']);
				}
				executeQuery("insert into distributor_order_products (client_id,product_id,quantity,location_id,order_item_id,user_id,notes) values (?,?,?,?,?, ?,?)",
					$GLOBALS['gClientId'], $productId, $quantity, $_POST['location_id'], $_POST['order_item_id'], $GLOBALS['gUserId'], "For order ID " . $orderId);
				$returnArray['info_message'] = $quantity . " added to distributor order products";
				ajaxResponse($returnArray);
				break;
			case "resend_receipt":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Order not found";
					ajaxResponse($returnArray);
					break;
				}
				if (Order::sendReceipt($orderId)) {
					$returnArray['info_message'] = "Receipt successfully resent";
				} else {
					$returnArray['error_message'] = "Error sending email";
				}
				ajaxResponse($returnArray);
				break;

			case "get_answer":
				$returnArray['content'] = getFieldFromId("content", "help_desk_answers", "help_desk_answer_id", $_GET['help_desk_answer_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""));
				ajaxResponse($returnArray);
				break;
			case "get_text_answer":
				$returnArray['text_content'] = strip_tags(html_entity_decode(getFieldFromId("content", "help_desk_answers", "help_desk_answer_id", $_GET['help_desk_answer_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""))));
				ajaxResponse($returnArray);
				break;
			case "send_tracking_email":
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentId)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				Order::sendTrackingEmail($orderShipmentId);
				ajaxResponse($returnArray);
				break;
			case "get_order_shipments":
				$orderRow = getRowFromId("orders", "order_id", $_GET['order_id']);
				if (empty($orderRow)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderItemTotal = 0;
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$orderItemTotal += $row['sale_price'] * $row['quantity'];
				}

				$orderShipments = array();
				$orderShipmentItemsQuantity = 0;
				$resultSet = executeQuery("select *,(select order_number from remote_orders where remote_order_id = order_shipments.remote_order_id) order_number from order_shipments left join shipping_carriers sc using (shipping_carrier_id) " .
					"where order_id = ? order by date_shipped", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$row['location'] = getFieldFromId("description", "locations", "location_id", $row['location_id']);
					$row['product_distributor_id'] = getFieldFromId("product_distributor_id", "locations", "location_id", $row['location_id']);
					$row['date_shipped'] = date("m/d/Y", strtotime($row['date_shipped']));
					$row['order_shipment_items'] = array();
					$itemSet = executeQuery("select *,(select sale_price from order_items where order_item_id = order_shipment_items.order_item_id) as sale_price," .
						"(select product_id from order_items where order_item_id = order_shipment_items.order_item_id) as product_id from order_shipment_items where " .
						"order_shipment_id = ?", $row['order_shipment_id']);
					// add Google as default for misc / other carrier
					$row['link_url'] = $row['link_url'] ?: "https://www.google.com/search?q=%tracking_identifier%";
					$shipmentItemTotal = 0;
					while ($itemRow = getNextRow($itemSet)) {
						$itemRow['product_description'] = getFieldFromId("description", "products", "product_id", $itemRow['product_id']);
						$row['order_shipment_items'][] = $itemRow;
						$shipmentItemTotal += $itemRow['sale_price'] * $itemRow['quantity'];
						$orderShipmentItemsQuantity += ($row['secondary_shipment'] ? 0 : $itemRow['quantity']);
					}
					if ($orderItemTotal == 0) {
						$shippingCharge = 0;
						$taxCharge = 0;
						$handlingCharge = 0;
					} else {
						$shippingCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['shipping_charge'];
						$taxCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['tax_charge'];
						$handlingCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['handling_charge'];
					}
					$row['shipment_amount'] = number_format($shipmentItemTotal + $shippingCharge + $taxCharge + $handlingCharge, 2, ".", ",");
					$row['shipping_charge'] = (empty($row['shipping_charge']) ? "" : number_format($row['shipping_charge'], 2, ".", ","));
					if (!empty($row['tracking_identifier'])) {
						$row['link_url'] = str_replace("%tracking_identifier%", $row['tracking_identifier'], $row['link_url']);
					} else {
						$row['link_url'] = "";
					}
					$orderShipments[] = $row;
				}
				$returnArray['order_shipments'] = $orderShipments;
				$returnArray['order_shipment_items_quantity'] = $orderShipmentItemsQuantity;
				ajaxResponse($returnArray);
				break;

			case "get_easy_post_label_rates":
				$pagePreferences = Page::getPagePreferences();
				$pagePreferences['weight_unit'] = $_POST['weight_unit'];
				$pagePreferences['predefined_package'] = $_POST['predefined_package'];
				$pagePreferences['include_media'] = $_POST['include_media'];
				Page::setPagePreferences($pagePreferences);
				EasyPostIntegration::setRecentlyUsedDimensions($_POST['height'], $_POST['width'], $_POST['length']);
				$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $_GET['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentRow)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				$_POST['order_id'] = $orderShipmentRow['order_id'];
				$returnArray = EasyPostIntegration::getLabelRates($this->iEasyPostActive, $_POST);

				ajaxResponse($returnArray);

				break;

			case "create_easy_post_label":
				$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $_GET['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentRow)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				$productIds = array();
				$itemSet = executeQuery("select * from order_shipment_items join order_items using (order_item_id) where order_shipment_id = ?", $orderShipmentRow['order_shipment_id']);
				while ($itemRow = getNextRow($itemSet)) {
					$productIds[] = $itemRow['product_id'];
				}
				freeResult($itemSet);

				$returnArray = EasyPostIntegration::createLabel($this->iEasyPostActive, $_POST, $productIds);
				if (!empty($returnArray['error_message'])) {
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeQuery("update order_shipments set date_shipped = current_date,full_name = ?,shipping_charge = ?,tracking_identifier = ?,label_url = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
					$_POST['to_full_name'], $returnArray['shipping_charge'], $returnArray['tracking_identifier'], $returnArray['label_url'], $returnArray['shipping_carrier_id'], $returnArray['carrier_description'], $orderShipmentRow['order_shipment_id']);
				if ($resultSet['affected_rows'] > 0) {
					Order::sendTrackingEmail($orderShipmentRow['order_shipment_id']);
				}

				ajaxResponse($returnArray);

				break;
			case "get_easy_post_customs_items":
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_shipment_id",
					$_GET['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentId)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['customs_items'] = EasyPostIntegration::getCustomsItems($orderShipmentId);
				ajaxResponse($returnArray);
				break;
			case "get_order_shipment_details":
				$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $_GET['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentRow)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				$height = 0;
				$width = 0;
				$length = 0;
				$weight = 0;
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$fflRequired = false;
				$signatureRequiredDepartmentCodes = getPreference("EASYPOST_SIGNATURE_REQUIRED_DEPARTMENTS");
				$signatureRequiredDepartmentIds = array();
				if (!empty($signatureRequiredDepartmentCodes)) {
					foreach (explode(",", $signatureRequiredDepartmentCodes) as $departmentCode) {
						$signatureRequiredDepartmentIds[] = getFieldFromId("product_department_id", "product_departments", "product_department_code", $departmentCode);
					}
				}
				$adultSignatureRequiredDepartmentCodes = getPreference("EASYPOST_ADULT_SIGNATURE_REQUIRED_DEPARTMENTS");
				$adultSignatureRequiredDepartmentIds = array();
				if (!empty($adultSignatureRequiredDepartmentCodes)) {
					foreach (explode(",", $adultSignatureRequiredDepartmentCodes) as $departmentCode) {
						$adultSignatureRequiredDepartmentIds[] = getFieldFromId("product_department_id", "product_departments", "product_department_code", $departmentCode);
					}
				}
				$signatureRequired = false;
				$adultSignatureRequired = false;

				$orderRow = getRowFromId("orders", "order_id", $orderShipmentRow['order_id']);
				$returnArray['order_row'] = $orderRow;

				$returnArray['addresses'] = array();
				$customerName = (empty($orderRow['full_name']) ? getDisplayName($orderRow['contact_id']) : $orderRow['full_name']);
				$attentionLine = $orderRow['attention_line'];
				if (empty($orderRow['address_id'])) {
					$addressRow = Contact::getContact($orderRow['contact_id']);
				} else {
					$addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
				}
				$resultSet = executeQuery("select * from order_items join order_shipment_items using (order_item_id) where order_shipment_id = ?", $orderShipmentRow['order_shipment_id']);
				while ($row = getNextRow($resultSet)) {
					$productDataRow = getRowFromId("product_data", "product_id", $row['product_id']);
					if (!empty($productDataRow['height']) && $productDataRow['height'] > $height) {
						$height = $productDataRow['height'];
					}
					if (!empty($productDataRow['width']) && $productDataRow['width'] > $width) {
						$width = $productDataRow['width'];
					}
					if (!empty($productDataRow['length']) && $productDataRow['length'] > $length) {
						$length = $productDataRow['length'];
					}
					if (!empty($productDataRow['weight'])) {
						$weight += $productDataRow['weight'] * $row['quantity'];
					}
					if ($fflRequiredProductTagId) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
						if (!empty($productTagLinkId)) {
							$fflRequired = true;
						}
					}
					if (!$fflRequired && strlen($addressRow['state']) == 2) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'],
							"product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'FFL_REQUIRED_" . strtoupper($addressRow['state']) . "' and inactive = 0 and cannot_sell = 0)");
						if (!empty($productTagLinkId)) {
							$fflRequired = true;
						}
					}
					foreach ($signatureRequiredDepartmentIds as $departmentId) {
						if (ProductCatalog::productIsInDepartment($row['product_id'], $departmentId)) {
							$signatureRequired = true;
							break;
						}
					}
					foreach ($adultSignatureRequiredDepartmentIds as $departmentId) {
						if (ProductCatalog::productIsInDepartment($row['product_id'], $departmentId)) {
							$adultSignatureRequired = true;
							break;
						}
					}
				}
				$returnArray['height'] = max(1, ceil($height));
				$returnArray['width'] = max(1, ceil($width));
				$returnArray['length'] = max(1, ceil($length));
				$returnArray['weight'] = max(1, ceil($weight));
				$returnArray['shipment_details'] = "Height: " . $height . "<br>Width: " . $width . "<br>Length: " . $length . "<br>Weight: " . $weight;
				$returnArray['addresses'][] = array("key_value" => "customer", "description" => "Customer", "full_name" => $customerName, "attention_line" => $attentionLine, "address_1" => $addressRow['address_1'],
					"address_2" => $addressRow['address_2'], "city" => $addressRow['city'], "state" => $addressRow['state'], "postal_code" => $addressRow['postal_code'], "country_id" => $addressRow['country_id'],
					"phone_number" => $orderRow['phone_number'], "residential" => true);

				$returnArray['ship_from'] = "dealer";
				$returnArray['ship_to'] = "customer";
				$returnArray['signature_required'] = $signatureRequired;
				$returnArray['adult_signature_required'] = $adultSignatureRequired;
				if (!empty($orderRow['federal_firearms_licensee_id'])) {
					$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
					$fflName = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
					$fflAttentionLine = (empty($attentionLine) ? EasyPostIntegration::formatAttentionLine($orderRow['full_name'], $orderRow['phone_number']) : $attentionLine);

					$returnArray['addresses'][] = array("key_value" => "ffl", "description" => "FFL Dealer Premises", "full_name" => $fflName, "attention_line" => $fflAttentionLine, "address_1" => $fflRow['address_1'],
						"address_2" => $fflRow['address_2'], "city" => $fflRow['city'], "state" => $fflRow['state'], "postal_code" => $fflRow['postal_code'], "country_id" => $fflRow['country_id'], "phone_number" => $fflRow['phone_number'], "residential" => false);
					$returnArray['addresses'][] = array("key_value" => "ffl_mailing", "description" => "FFL Dealer Mailing", "full_name" => $fflName, "attention_line" => $fflAttentionLine, "address_1" => $fflRow['mailing_address_1'],
						"address_2" => $fflRow['mailing_address_2'], "city" => $fflRow['mailing_city'], "state" => $fflRow['mailing_state'], "postal_code" => $fflRow['mailing_postal_code'], "country_id" => $fflRow['country_id'], "phone_number" => $fflRow['phone_number'], "residential" => false);
					if ($fflRequired) {
						$returnArray['ship_to'] = "ffl" . (empty($fflRow['mailing_address_preferred']) ? "" : "_mailing");
					}
				}
				$resultSet = executeQuery("select * from locations join contacts using (contact_id) where inactive = 0 and product_distributor_id is null and locations.client_id = ? and address_1 is not null", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] <= 1) {
					$dealerPhoneNumber = Contact::getContactPhoneNumber($GLOBALS['gClientRow']['contact_id'], 'Primary');
					$clientName = $GLOBALS['gClientName'];
					if (!empty($GLOBALS['gClientRow']['alternate_name'])) {
						$clientName = $GLOBALS['gClientRow']['alternate_name'];
					}
					$returnArray['addresses'][] = array("key_value" => "dealer", "description" => "Dealer", "full_name" => $clientName, "attention_line" => "", "address_1" => $GLOBALS['gClientRow']['address_1'],
						"address_2" => $GLOBALS['gClientRow']['address_2'], "city" => $GLOBALS['gClientRow']['city'], "state" => $GLOBALS['gClientRow']['state'], "postal_code" => $GLOBALS['gClientRow']['postal_code'],
						"country_id" => $GLOBALS['gClientRow']['country_id'], "phone_number" => $dealerPhoneNumber, "residential" => false);
				} else {
					while ($row = getNextRow($resultSet)) {
						$dealerPhoneNumber = Contact::getContactPhoneNumber($row['contact_id'], 'Primary');
						if (empty($dealerPhoneNumber)) {
							$dealerPhoneNumber = Contact::getContactPhoneNumber($GLOBALS['gClientRow']['contact_id'], 'Primary');
						}
						if ($returnArray['ship_from'] == "dealer") {
							$returnArray['ship_from'] = "location_" . $row['location_id'];
						}
						if (!empty($row['alternate_name'])) {
							$row['business_name'] = $row['alternate_name'];
						}
						$businessName = (empty($row['business_name']) ? $GLOBALS['gClientName'] : $row['business_name']);
						if (!empty($row['alternate_name'])) {
							$businessName = $row['alternate_name'];
						}
						$returnArray['addresses'][] = array("key_value" => "location_" . $row['location_id'], "description" => $row['description'], "full_name" => $businessName,
							"attention_line" => "", "address_1" => $row['address_1'], "address_2" => $row['address_2'], "city" => $row['city'], "state" => $row['state'], "postal_code" => $row['postal_code'],
							"country_id" => $row['country_id'], "phone_number" => $dealerPhoneNumber, "residential" => false);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_ffl_details":
				if (!$this->iFFLActive) {
					ajaxResponse($returnArray);
					break;
				}
				$fflRow = (new FFL($_GET['federal_firearms_licensee_id']))->getFFLRow();
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				updateFieldById("federal_firearms_licensee_id", $fflRow['federal_firearms_licensee_id'], "orders", "order_id", $orderId);
				$returnArray['selected_ffl'] = FFL::getFFLBlock($fflRow);
				Order::updateGunbrokerOrder($orderId);
				ajaxResponse($returnArray);
				break;
			case "send_email":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$subject = $_POST['email_subject'];
				$body = $_POST['email_body'];
				if (empty($subject) || empty($body)) {
					$returnArray['error_message'] = "Required information is missing";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
				if (empty($emailAddress)) {
					$returnArray['error_message'] = "Customer has no email address";
					ajaxResponse($returnArray);
					break;
				}
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "EMAIL_SENT");
				if (empty($taskTypeId)) {
					$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
				}
				if (empty($taskTypeId)) {
					$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
					if (empty($taskAttributeId)) {
						$returnArray['error_message'] = "No Touchpoint Task Type";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'EMAIL_SENT','Email Sent')", $GLOBALS['gClientId']);
					$taskTypeId = $resultSet['insert_id'];
					executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
				}
				if (empty($taskTypeId)) {
					$returnArray['error_message'] = "No Touchpoint Task Type";
					ajaxResponse($returnArray);
					break;
				}
				$result = sendEmail(array("subject" => $subject, "body" => $body, "email_address" => $emailAddress, "email_credential_id" => $_POST['email_credential_id']));
				if ($result) {
					executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task,creator_user_id,order_id) values " .
						"(?,?,?,?,now(),?,1,?,?)", $GLOBALS['gClientId'], $contactId, $subject, $body, $taskTypeId, $GLOBALS['gUserId'], $orderId);
				} else {
					$returnArray['error_message'] = "Unable to send email";
				}
				ajaxResponse($returnArray);
				break;
			case "send_text_message":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$body = $_POST['text_message'];
				if (empty($body)) {
					$returnArray['error_message'] = "Required information is missing";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
				if ($GLOBALS['gPHPVersion'] < 70200) {
					$returnArray['error_message'] = "Unable to send text, use email";
					ajaxResponse($returnArray);
					break;
				}
				$result = TextMessage::sendMessage($contactId, $body);
				if (!$result) {
					$returnArray['error_message'] = "Unable to send Text Message";
					ajaxResponse($returnArray);
					break;
				}
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TEXT_SENT");
				if (empty($taskTypeId)) {
					$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
				}
				if (empty($taskTypeId)) {
					$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
					if (empty($taskAttributeId)) {
						$returnArray['info_message'] = "Message sent, but unable to create Touchpoint";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'TEXT_SENT','Text Sent')", $GLOBALS['gClientId']);
					$taskTypeId = $resultSet['insert_id'];
					executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
				}
				executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task,creator_user_id,order_id) values " .
					"(?,?,?,?,now(),?,1,?,?)", $GLOBALS['gClientId'], $contactId, "Text Sent", $body, $taskTypeId, $GLOBALS['gUserId'], $orderId);
				ajaxResponse($returnArray);
				break;
			case "set_anticipated_ship_date":
				executeQuery("update order_items set anticipated_ship_date = ? where order_id = ? and order_item_id = ?",
					makeDateParameter($_POST['anticipated_ship_date']), $_POST['order_id'], $_POST['order_item_id']);
				ajaxResponse($returnArray);
				break;
			case "set_download_date":
				executeQuery("update order_items set download_date = ? where order_id = ? and order_item_id = ?",
					makeDateParameter($_POST['download_date']), $_POST['order_id'], $_POST['order_item_id']);
				ajaxResponse($returnArray);
				break;
			case "reopen_order":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id'], "date_completed is not null");
				if (empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				$ordersTable = new DataTable("orders");
				$ordersTable->setSaveOnlyPresent(true);
				$ordersTable->saveRecord(array("name_values" => array("date_completed" => ""), "primary_id" => $orderId));
				coreSTORE::orderNotification($orderId, "mark_not_completed");
				ajaxResponse($returnArray);
				break;
			case "mark_completed":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id'], "date_completed is null");
				if (empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				Order::markOrderCompleted($orderId);
				ajaxResponse($returnArray);
				break;
			case "get_order_items":
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$fflRequired = false;
				$orderItems = array();
				$totalQuantity = 0;
				$shippedOrderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", "SHIPPED");
				$orderDataRow = getRowFromId("orders", "order_id", $_GET['order_id']);
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				$orangeInventoryThreshold = intval(getPageTextChunk("INVENTORY_ORANGE_LIMIT"));
				$redInventoryThreshold = intval(getPageTextChunk("INVENTORY_RED_LIMIT"));
				$orangeInventoryThreshold = $orangeInventoryThreshold ?: 10;
				$redInventoryThreshold = $redInventoryThreshold ?: 5;

				while ($row = getNextRow($resultSet)) {
					ProductCatalog::updateAllProductLocationCosts($row['product_id']);
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
					$productTypeCode = getFieldFromId("product_type_code", "product_types", "product_type_id", $productRow['product_type_id']);
					if ($fflRequiredProductTagId) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
						if (!empty($productTagLinkId)) {
							$fflRequired = true;
						}
					}
					if (!$fflRequired) {
						$state = getFieldFromId("state", "addresses", "address_id", $orderDataRow['address_id']);
						if (empty($state)) {
							$state = getFieldFromId("state", "contacts", "contact_id", $orderDataRow['contact_id']);
						}
						if (!empty($state)) {
							$fflRequiredStateProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED_" . $state, "inactive = 0 and cannot_sell = 0");
							$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $fflRequiredStateProductTagId);
							if (!empty($productTagLinkId)) {
								$fflRequired = true;
							}
						}
					}
					$row['distributor_order_message'] = "";
					$orderSet = executeQuery("select * from distributor_order_item_links where order_item_id = ?", $row['order_item_id']);
					while ($orderRow = getNextRow($orderSet)) {
						if (empty($row['distributor_order_message'])) {
							$row['distributor_order_message'] = "Ordered in distributor order #" . $orderRow['distributor_order_id'];
						} else {
							$row['distributor_order_message'] .= "," . $orderRow['distributor_order_id'];
						}
					}
					$row['product_description'] = htmlText(empty($row['description']) ? $productRow['description'] : $row['description']);
					$row['additional_product_description'] = "";

					$giftCardEmailAddress = false;
					$customDataSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS' and client_id = ?)", $GLOBALS['gClientId']);
					while ($customDataRow = getNextRow($customDataSet)) {
						$customFieldData = CustomField::getCustomFieldData($row['order_item_id'], $customDataRow['custom_field_code'], "ORDER_ITEMS");
						if (empty($customFieldData)) {
							continue;
						}
						if (startsWith($customFieldData, "[")) {
							$dataArray = json_decode($customFieldData, true);
							$customFieldData = "";
							foreach ($dataArray as $thisRow) {
								foreach ($thisRow as $fieldName => $fieldData) {
									$customFieldData .= "<br>" . snakeCaseToDescription($fieldName) . ": " . $fieldData;
								}
							}
						}
						$row['additional_product_description'] .= "<br><span class='highlighted-text'>" . $customDataRow['description'] . "</span>: " . $customFieldData;
						if ($customDataRow['custom_field_code'] == "RECIPIENT_EMAIL_ADDRESS" && $productTypeCode == "GIFT_CARD") {
							$giftCardEmailAddress = $customFieldData;
						}
					}
					if ($productTypeCode == "GIFT_CARD") {
						$giftCardRow = getRowFromId("gift_cards", "order_item_id", $row['order_item_id']);
						if (empty($giftCardRow)) {
							$row['additional_product_description'] .= "<br><button class='issue-gift-card' data-order_item_id='" . $row['order_item_id'] . "'>Issue Gift Card</button>";
						} elseif (!empty($giftCardEmailAddress)) {
							$row['additional_product_description'] .= "<br><button class='resend-gift-card-email' data-order_item_id='" . $row['order_item_id'] . "'>Resend Gift Card Email</button>";
						}
					}
					if ($productTypeCode == "PROMOTION_SALE") {
						$promotionSet = executeQuery("select * from promotions where order_item_id = ?", $row['order_item_id']);
						while ($promotionRow = getNextRow($promotionSet)) {
							$row['additional_product_description'] .= "<br>Promotion Code: <strong>" . $promotionRow['promotion_code'] . "</strong>";
						}
					}

					$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ? order by sort_order", $row['order_item_id']);
					while ($addonRow = getNextRow($addonSet)) {
						$salePrice = ($addonRow['quantity'] <= 1 ? $addonRow['sale_price'] : $addonRow['sale_price'] * $addonRow['quantity']);
						$row['additional_product_description'] .= "<br>" . $addonRow['description'] . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")") . " - $" . number_format($salePrice, 2, ".", "");
						if (!empty($addonRow['form_definition_id'])) {
							$row['additional_product_description'] .= " - <a href='#' class='addon-form' data-order_item_addon_id='" . $addonRow['order_item_addon_id'] . "'>View Form Summary</a>";
						}
					}
					$row['virtual_product'] = $productRow['virtual_product'];
					$row['file_id'] = $productRow['file_id'];
					$row['serializable'] = $productRow['serializable'];
					$upcCode = getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']);
					if (!empty($upcCode)) {
						$row['additional_product_description'] .= "<br><span class='upc-code copy-to-clipboard'>UPC: <span class='copy-text'>" . $upcCode . "</span></span>";
					}
					$row['additional_product_description'] .= "<br><span class='product-code copy-to-clipboard'>Product Code: <span class='copy-text'>" . getFirstPart($productRow['product_code'], 40) . "</span></span>";
					$row['anticipated_ship_date'] = (empty($row['anticipated_ship_date']) ? "" : date("m/d/Y", strtotime($row['anticipated_ship_date'])));
					$row['download_date'] = (empty($row['download_date']) ? "" : date("m/d/Y", strtotime($row['download_date'])));
					$row['total_price'] = $row['sale_price'] * $row['quantity'];
					$row['sale_price'] = number_format($row['sale_price'], 2, ".", ",");
					$row['total_price'] = number_format($row['total_price'], 2, ".", ",");
					if ($row['virtual_product']) {
						$row['shipped_quantity'] = $row['quantity'];
						if (!empty($shippedOrderItemStatusId)) {
							$row['order_item_status_id'] = $shippedOrderItemStatusId;
						}
					} else {
						$shippedSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0 and internal_use_only = 0)", $row['order_item_id']);
						while ($shippedRow = getNextRow($shippedSet)) {
							$row['shipped_quantity'] = $shippedRow['sum(quantity)'];
						}
						if (empty($row['shipped_quantity'])) {
							$row['shipped_quantity'] = 0;
						}
						if ($row['shipped_quantity'] >= $row['quantity'] && !empty($shippedOrderItemStatusId)) {
							$row['order_item_status_id'] = $shippedOrderItemStatusId;
						}
					}

					ProductDistributor::setPrimaryDistributorLocation();
					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderDataRow['shipping_method_id']);

					$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and location_id in (select location_id from product_inventories where product_id = ? and quantity > 0) order by location_id", $GLOBALS['gClientId'], $row['product_id']);
					$productInventories = array();
					$productDistributorIds = array();
					while ($locationRow = getNextRow($locationSet)) {
						if (!empty($locationRow['product_distributor_id']) && (in_array($locationRow['product_distributor_id'], $productDistributorIds) || empty($locationRow['primary_location']))) {
							continue;
						}
						if (empty(getPreference("INCLUDE_ALL_LOCAL_LOCATIONS")) && !empty($shippingMethodRow['location_id']) && empty($locationRow['product_distributor_id']) && empty($locationRow['warehouse_location']) && $shippingMethodRow['location_id'] != $locationRow['location_id']) {
							continue;
						}
						$productDistributorIds[] = $locationRow['product_distributor_id'];
						$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $locationRow['location_id']);
						if (empty($productInventoryRow)) {
							$productInventoryRow['quantity'] = 0;
						}
						$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $locationRow['location_id'], $productInventoryRow, false);
						if ($productInventoryRow['quantity'] > 0 && (strlen($cost) > 0 || empty($locationRow['product_distributor_id']))) {
							$description = $locationRow['description'];
							$sortOrder = "";
							if (!empty($locationRow['product_distributor_id'])) {
								$description = getReadFieldFromId("description", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
								$sortOrder = getReadFieldFromId("sort_order", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
							}
							$productInventories[] = array("description" => $description, "quantity" => $productInventoryRow['quantity'], "cost" => $cost, "sort_order" => $sortOrder);
						}
					}

					$row['product_inventories'] = "";
					if (!empty($productInventories)) {
						$totalInventory = 0;
						usort($productInventories, array($this, "sortPrices"));
						foreach ($productInventories as $thisPrice) {
							$row['product_inventories'] .= (empty($row['product_inventories']) ? "" : "<br>") . $thisPrice['description'] . " - " . $thisPrice['quantity'] . (empty($thisPrice['cost']) ? "" : "/$" . number_format($thisPrice['cost'], 2, ".", ","));
							$totalInventory += $thisPrice['quantity'];
						}
						$inventoryIcon = ((!empty($orangeInventoryThreshold) && $totalInventory <= $orangeInventoryThreshold) ? "<i id='_inventory_alert' class='orange-text fas fa-exclamation-triangle' title='Low Inventory'></i>" : "");
						$inventoryIcon = ((!empty($redInventoryThreshold) && $totalInventory <= $redInventoryThreshold) ? "<i id='_inventory_alert' class='red-text fas fa-exclamation-circle' title='Very Low Inventory'></i>" : $inventoryIcon);
						$row['product_inventories'] = $inventoryIcon . "<div class='product-inventory'>" . $row['product_inventories'] . "</div>";
					}
					if (!empty($row['deleted'])) {
						$row['order_item_status_id'] = -1;
					} else {
						$totalQuantity += $row['quantity'];
					}
					$orderItems[] = $row;
				}
				if (!empty($shippingMethodRow['pickup'])) {
					$fflRequired = false;
				}
				$returnArray['ffl_required'] = $fflRequired;
				$returnArray['order_items'] = $orderItems;
				$returnArray['order_items_quantity'] = $totalQuantity;

				$resultSet = executeQuery("select * from order_items join products using (product_id) where order_id = ?", $_GET['order_id']);
				ob_start();
				while ($row = getNextRow($resultSet)) {
					if (!$row['serializable']) {
						continue;
					}
					$serialNumberControl = new DataColumn("order_item_serial_numbers_" . $row['order_item_id']);
					$serialNumberControl->setControlValue("data_type", "custom_control");
					$serialNumberControl->setControlValue("control_class", "EditableList");
					$serialNumberControl->setControlValue("primary_table", "order_items");
					$serialNumberControl->setControlValue("list_table", "order_item_serial_numbers");
					$serialNumberControl->setControlValue("list_table_controls", array("serial_number" => array("classes" => "serial-number")));
					$customControl = new EditableList($serialNumberControl, $this);
					echo $customControl->getTemplate();

					$returnArray = array_merge($returnArray, $customControl->getRecord($row['order_item_id']));
				}
				$returnArray['jquery_templates'] = ob_get_clean();

				ajaxResponse($returnArray);

				break;
			case "delete_location_shipments":
				$orderShipmentItemRows = array();
				$resultSet = executeQuery("select * from order_shipment_items where order_shipment_id in "
					. "(select order_shipment_id from order_shipments where order_id = ? and location_id = ?)", $_POST['order_id'], $_POST['location_id']);
				while ($row = getNextRow($resultSet)) {
					$orderShipmentItemRows[] = $row;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				foreach ($orderShipmentItemRows as $orderShipmentItemRow) {
					$orderItemRow = getRowFromId("order_items", "order_item_id", $orderShipmentItemRow['order_item_id']);
					$resultSet = executeQuery("delete from order_shipment_items where order_shipment_item_id = ?", $orderShipmentItemRow['order_shipment_item_id']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					if (empty($resultSet['affected_rows'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Shipment item not found";
						ajaxResponse($returnArray);
						break;
					}
					# remove inventory adjustments
					if (empty($orderShipmentRow['secondary_shipment'])) {
						$locationId = getFieldFromId("location_id", "order_shipments", "order_shipment_id", $orderShipmentItemRow['order_shipment_id']);
						$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
						$locationSet = executeQuery("select * from locations where inactive = 0 and (location_id = ? or product_distributor_id = ?) order by primary_location desc", $locationId, $productDistributorId);
						while ($locationRow = getNextRow($locationSet)) {
							if (!empty($locationRow['product_distributor_id']) && empty($locationRow['primary_location'])) {
								continue;
							}
							$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $orderItemRow['product_id'], "location_id = ?", $locationRow['location_id']);
							if (!empty($productInventoryId)) {
								$deleteQuantity = 0;
								$remainingQuantity = $orderShipmentItemRow['quantity'];
								$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? and inventory_adjustment_type_id = ? and order_id = ?",
									$productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderItemRow['order_id']);
								while ($row = getNextRow($resultSet)) {
									if ($row['quantity'] <= $remainingQuantity) {
										executeQuery("delete from product_inventory_log where product_inventory_log_id = ?", $row['product_inventory_log_id']);
										$deleteQuantity += $row['quantity'];
										$remainingQuantity -= $row['quantity'];
									} else {
										if ($remainingQuantity > 0) {
											executeQuery("update product_inventory_log set quantity = quantity - " . $remainingQuantity . " where product_inventory_log_id = ?", $row['product_inventory_log_id']);
											$deleteQuantity += $remainingQuantity;
											$remainingQuantity = 0;
										}
									}
									if ($remainingQuantity <= 0) {
										break;
									}
								}
								executeQuery("update product_inventories set quantity = quantity + " . $deleteQuantity . " where product_inventory_id = ?", $productInventoryId);
							}
						}
						executeQuery("update order_items set order_item_status_id = null where order_item_id = ? and order_item_status_id in " .
							"(select order_item_status_id from order_item_statuses where order_item_status_code = 'SHIPPED')", $orderShipmentItemRow['order_item_id']);
					}
				}
				$resultSet = executeQuery("delete from order_shipments where order_id = ? and location_id = ?", $_POST['order_id'], $_POST['location_id']);
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($resultSet['affected_rows'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Shipment(s) not found";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['delete_shipment'] = true;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				ajaxResponse($returnArray);
				break;
			case "remove_shipment_item":
				$orderShipmentItemRow = getRowFromId("order_shipment_items", "order_shipment_item_id", $_POST['order_shipment_item_id'],
					"order_shipment_id = ?", $_POST['order_shipment_id']);
				$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
				$orderItemRow = getRowFromId("order_items", "order_item_id", $orderShipmentItemRow['order_item_id']);
				$orderId = getFieldFromId("order_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
				if (empty($orderShipmentItemRow)) {
					$returnArray['error_message'] = "Shipment item not found";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_shipment_items where order_shipment_id = ? and order_shipment_item_id = ?", $_POST['order_shipment_id'], $_POST['order_shipment_item_id']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($resultSet['affected_rows'])) {
					$returnArray['error_message'] = "Shipment item not found";
					ajaxResponse($returnArray);
					break;
				}

# remove from product inventory log

				if (empty($orderShipmentRow['secondary_shipment'])) {
					$locationId = getFieldFromId("location_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
					$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
					$locationSet = executeQuery("select * from locations where inactive = 0 and (location_id = ? or product_distributor_id = ?) order by primary_location desc", $locationId, $productDistributorId);
					while ($locationRow = getNextRow($locationSet)) {
						if (!empty($locationRow['product_distributor_id']) && empty($locationRow['primary_location'])) {
							continue;
						}
						$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $orderItemRow['product_id'], "location_id = ?", $locationRow['location_id']);
						if (!empty($productInventoryId)) {
							$deleteQuantity = 0;
							$remainingQuantity = $orderShipmentItemRow['quantity'];
							$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? and inventory_adjustment_type_id = ? and order_id = ?",
								$productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderItemRow['order_id']);
							while ($row = getNextRow($resultSet)) {
								if ($row['quantity'] <= $remainingQuantity) {
									executeQuery("delete from product_inventory_log where product_inventory_log_id = ?", $row['product_inventory_log_id']);
									$deleteQuantity += $row['quantity'];
									$remainingQuantity -= $row['quantity'];
								} else {
									if ($remainingQuantity > 0) {
										executeQuery("update product_inventory_log set quantity = quantity - " . $remainingQuantity . " where product_inventory_log_id = ?", $row['product_inventory_log_id']);
										$deleteQuantity += $remainingQuantity;
										$remainingQuantity = 0;
									}
								}
								if ($remainingQuantity <= 0) {
									break;
								}
							}
							executeQuery("update product_inventories set quantity = quantity + " . $deleteQuantity . " where product_inventory_id = ?", $productInventoryId);
						}
					}
					executeQuery("update order_items set order_item_status_id = null where order_item_id = ? and order_item_status_id in " .
						"(select order_item_status_id from order_item_statuses where order_item_status_code = 'SHIPPED')", $orderShipmentItemRow['order_item_id']);
				}
				$orderShipmentItemId = getFieldFromId("order_shipment_item_id", "order_shipment_items", "order_shipment_id", $_POST['order_shipment_id']);
				if (empty($orderShipmentItemId)) {
					$returnArray['delete_shipment'] = true;
					$remoteOrderId = getFieldFromId("remote_order_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
					executeQuery("delete from order_shipments where order_shipment_id = ?", $_POST['order_shipment_id']);
					if (!empty($remoteOrderId)) {
						executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
						executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					}
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);

				$orderItemTotal = 0;
				$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
				while ($row = getNextRow($resultSet)) {
					$orderItemTotal += $row['sale_price'] * $row['quantity'];
				}

				$itemSet = executeQuery("select *,(select sale_price from order_items where order_item_id = order_shipment_items.order_item_id) as sale_price," .
					"(select product_id from order_items where order_item_id = order_shipment_items.order_item_id) as product_id from order_shipment_items where " .
					"order_shipment_id = ?", $_POST['order_shipment_id']);
				$shipmentItemTotal = 0;
				while ($itemRow = getNextRow($itemSet)) {
					$shipmentItemTotal += $itemRow['sale_price'] * $itemRow['quantity'];
				}
				$shippingCharge = (empty($orderItemTotal) ? 0 : ($shipmentItemTotal / $orderItemTotal) * $orderRow['shipping_charge']);
				$taxCharge = (empty($orderItemTotal) ? 0 : ($shipmentItemTotal / $orderItemTotal) * $orderRow['tax_charge']);
				$handlingCharge = (empty($orderItemTotal) ? 0 : ($shipmentItemTotal / $orderItemTotal) * $orderRow['handling_charge']);
				$returnArray['shipment_amount'] = number_format($shipmentItemTotal + $shippingCharge + $taxCharge + $handlingCharge, 2, ".", ",");

				coreSTORE::orderNotification($orderId, "shipment_item_removed");
				ajaxResponse($returnArray);

				break;
			case "add_note":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				if (empty($_POST['content']) || empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("insert into order_notes (order_id,user_id,time_submitted,content,public_access) values (?,?,current_time,?,?)",
					$orderId, $GLOBALS['gUserId'], $_POST['content'], (empty($_POST['public_access']) ? 0 : 1));
				$returnArray['order_note'] = array("user_id" => getUserDisplayName(), "time_submitted" => date("m/d/Y g:ia"), "content" => $_POST['content'], "public_access" => (empty($_POST['public_access']) ? "" : "YES"));
				ajaxResponse($returnArray);
				break;
			case "add_file":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['primary_id']);
				if (empty($_POST['order_files_description']) || empty($_FILES['order_files_file_id_file']) || empty($orderId)) {
					$returnArray['error_message'] = "Missing information";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$fileId = createFile("order_files_file_id_file");
				$resultSet = executeQuery("insert into order_files (order_id,description,file_id) values (?,?,?)",
					$orderId, $_POST['order_files_description'], $fileId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to create file: " . $resultSet['sql_error'];
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$returnArray['order_file'] = array("file_id" => $fileId, "description" => $_POST['order_files_description'], "order_file_id" => $resultSet['insert_id']);
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				ajaxResponse($returnArray);
				break;
			case "save_shipment_details":
				$fieldName = $_POST['field_name'];
				$fieldData = $_POST['field_data'];
				$orderShipmentId = $_POST['order_shipment_id'];
				$orderId = $_POST['order_id'];
				$validFields = array("no_notifications", "tracking_identifier", "shipping_carrier_id", "carrier_description", "notes");
				if (!in_array($fieldName, $validFields)) {
					$returnArray['error_message'] = "Invalid Field";
				} else {
					executeQuery("update order_shipments set " . $fieldName . " = ? where order_id = ? and order_shipment_id = ?", $fieldData, $orderId, $orderShipmentId);
				}
				ajaxResponse($returnArray);
				break;
			case "save_show_settings":
				$valuesArray = Page::getPagePreferences();
				if (array_key_exists("show_settings", $_GET)) {
					$valuesArray['show_settings'] = (empty($_GET['show_settings']) ? 0 : 1);
					Page::setPagePreferences($valuesArray);
				}
				ajaxResponse($returnArray);
				break;
			case "create_shipment":
				$valuesArray = Page::getPagePreferences();
				if (array_key_exists("show_settings", $_POST)) {
					$valuesArray['show_settings'] = $_POST['show_settings'];
					Page::setPagePreferences($valuesArray);
				}
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$lastOrderShipmentId = "";
				$resultSet = executeQuery("select max(order_shipment_id) from order_shipments where order_id = ?", $orderId);
				if ($row = getNextRow($resultSet)) {
					$lastOrderShipmentId = $row['max(order_shipment_id)'];
				}
				if (!empty($lastOrderShipmentId) && $lastOrderShipmentId != $_POST['latest_order_shipment_id']) {
					$returnArray['error_message'] = "Someone created a shipment since you opened the order. Close and reopen the order.";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_id", $orderId, "not_captured = 1 and deleted = 0");
				if (!empty($orderPaymentId)) {
					$returnArray['error_message'] = "Shipments cannot be created until all payments are captured";
					ajaxResponse($returnArray);
					break;
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
				if (!empty($shippingMethodRow['pickup'])) {
					$_POST['dont_dropship'] = true;
				}
				$sendPickupTracking = empty(getPreference("NO_TRACKING_FOR_PICKUP_ORDERS")) ? $shippingMethodRow['pickup'] : 0;
				$locationId = getFieldFromId("location_id", "locations", "location_id", $_POST['location_id']);
				if (empty($locationId) && !empty($_POST['location_id'])) {
					$returnArray['error_message'] = "Invalid Location";
					ajaxResponse($returnArray);
					break;
				}
				$orderItems = $_POST['order_items'];
				if (!empty($_POST['dont_dropship'])) {
					foreach ($orderItems as $index => $thisOrderItem) {
						$orderItems[$index]['ship_to'] = "dealer";
					}
				}
				$selectedLocationRow = getRowFromId("locations", "location_id", $locationId);
				$inventoryLocationRow = array();
				if (!empty($selectedLocationRow['product_distributor_id']) && empty($selectedLocationRow['primary_location'])) {
					$inventoryLocationRow = getRowFromId("locations", "product_distributor_id", $selectedLocationRow['product_distributor_id'], "primary_location = 1");
				}
				if (empty($inventoryLocationRow)) {
					$inventoryLocationRow = $selectedLocationRow;
				}
				if (empty($_POST['secondary_shipment']) && empty($selectedLocationRow['ignore_inventory'])) {
					foreach ($orderItems as $index => $thisOrderItem) {
						$quantity = getFieldFromId("quantity", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
						$resultSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and " .
							"order_shipment_id in (select order_shipment_id from order_shipments where order_id = ? and " .
							"secondary_shipment = 0 and internal_use_only = 0)", $thisOrderItem['order_item_id'], $thisOrderItem['order_id']);
						$shippedQuantity = 0;
						if ($row = getNextRow($resultSet)) {
							$shippedQuantity = $row['sum(quantity)'];
						}
						$maximumQuantity = min($thisOrderItem['quantity'], $quantity - $shippedQuantity);
						if ($thisOrderItem['quantity'] > $maximumQuantity) {
							$returnArray['error_message'] = "Invalid Quantities";
							ajaxResponse($returnArray);
							break;
						}
						$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
						$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "location_id", $inventoryLocationRow['location_id'], "product_id = ?", $thisOrderItem['product_id']);
						if (empty($inventoryQuantity)) {
							$productRow = ProductCatalog::getCachedProductRow($thisOrderItem['product_id']);
							if (!empty($productRow['non_inventory_item'])) {
								$productManufacturerCode = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id'], "inactive = 0");
								if (!empty($productManufacturerCode)) {
									$manufacturerLocationId = getFieldFromId("location_id", "locations", "location_code", $productManufacturerCode, "inactive = 0");
									if (!empty($manufacturerLocationId)) {
										$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", $manufacturerLocationId);
										if (empty($productInventoryId)) {
											executeQuery("insert into product_inventories (product_id, location_id, quantity) values (?,?,999999)", $thisOrderItem['product_id'], $manufacturerLocationId);
										} else {
											executeQuery("update product_inventories set quantity = 999999 where product_inventory_id = ?",$productInventoryId);
										}
										$inventoryQuantity = 999999;
									}
								}
							}
						}
						if (empty($inventoryQuantity) || $thisOrderItem['quantity'] > $inventoryQuantity) {
							$returnArray['error_message'] = "This location doesn't have inventory to ship these items";
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $inventoryLocationRow['location_id']);
				if (empty($productDistributorId)) {
					$orderShipmentsDataTable = new DataTable("order_shipments");
					$orderShipmentId = $orderShipmentsDataTable->saveRecord(array("name_values" => array("order_id" => $orderId, "location_id" => $locationId, "date_shipped" => date("m/d/Y"), "secondary_shipment" => (empty($_POST['secondary_shipment']) ? 0 : 1))));

					$orderItemCount = 0;
					foreach ($orderItems as $thisOrderItem) {
						$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
						$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $locationId);
						executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
							$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
						$orderItemCount++;

						# add to product inventory log

						if (empty($_POST['secondary_shipment']) && empty($selectedLocationRow['ignore_inventory'])) {
							if (empty($GLOBALS['gSalesAdjustmentTypeId'])) {
								$GLOBALS['gPrimaryDatabase']->logError("Sales Adjustment type not found");
							} else {
								$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", $locationId);
								if (!empty($productInventoryId)) {
									$productInventoryLogId = getFieldFromId("product_inventory_log_id", "product_inventory_log", "product_inventory_id", $productInventoryId,
										"inventory_adjustment_type_id = ? and order_id = ?", $GLOBALS['gSalesAdjustmentTypeId'], $orderId);
									if (empty($productInventoryLogId)) {
										executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,order_id,user_id,log_time,quantity) values " .
											"(?,?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderId, $GLOBALS['gUserId'], $thisOrderItem['quantity']);
									} else {
										executeQuery("update product_inventory_log set quantity = quantity + " . $thisOrderItem['quantity'] . " where product_inventory_log_id = ?", $productInventoryLogId);
									}
								}
								executeQuery("update product_inventories set quantity = greatest(0,quantity - " . $thisOrderItem['quantity'] . ") where product_inventory_id = ?", $productInventoryId);
							}
						}
					}
					if ($orderItemCount == 0) {
						executeQuery("delete from remote_order_items where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
						executeQuery("delete from remote_orders where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
						executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
					}
				} else {
					$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
					$response = array();
					if ($GLOBALS['gDevelopmentServer'] && empty(getPreference('DEVELOPMENT_TEST_DISTRIBUTORS'))) {
						$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
							$orderRow['order_id'], $orderRow['order_id']);
						$remoteOrderId = $orderSet['insert_id'];
						foreach ($orderItems as $thisOrderItem) {
							executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
								$remoteOrderId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity']);
						}
						$response = array('dealer' => array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderRow['order_id'], "ship_to" => $GLOBALS['gClientName']));
					} else {
						if ($productDistributor) {
							$response = $productDistributor->placeOrder($orderId, $orderItems, array("shipment_shipping_method" => $_POST['shipment_shipping_method'], "shipment_signature_required" => $_POST['shipment_signature_required'], "shipment_adult_signature_required" => $_POST['shipment_adult_signature_required']));
							if ($response === false || array_key_exists("error_message", $response) || array_key_exists("failed_items", $response)) {
								$returnArray['error_message'] = "Error: " . ($productDistributor->getErrorMessage() == $response['error_message'] ? "" : $productDistributor->getErrorMessage() . ":") .
									$response['error_message'] . ($GLOBALS['gUserRow']['superuser_flag'] ? ":" . jsonEncode($response) : "");
								ajaxResponse($returnArray);
								break;
							}
							if (array_key_exists("info_message", $response)) {
								$returnArray['info_message'] = $response['info_message'];
							}
						} else {
							$returnArray['error_message'] = "Unable to get distributor for this location";
						}
					}
					foreach ($response as $shipmentInformation) {
						if (!empty($shipmentInformation['error_message'])) {
							$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . $shipmentInformation['error_message'];
						}
						if (!is_array($shipmentInformation) || (empty($shipmentInformation['ship_to']) && empty($shipmentInformation['remote_order_id']))) {
							continue;
						}
						$checkFields = array("order_type", "remote_order_id", "order_number", "ship_to");
						foreach ($checkFields as $thisField) {
							if (!array_key_exists($thisField, $shipmentInformation)) {
								$shipmentInformation[$thisField] = "";
							}
						}
						$orderShipmentsDataTable = new DataTable("order_shipments");
						$orderShipmentId = $orderShipmentsDataTable->saveRecord(array("name_values" => array("order_id" => $orderId, "location_id" => $locationId, "full_name" => $shipmentInformation['ship_to'],
							"remote_order_id" => $shipmentInformation['remote_order_id'], "no_notifications" => ($shipmentInformation['order_type'] == "dealer" && !$sendPickupTracking ? 1 : 0), "internal_use_only" => ($shipmentInformation['order_type'] == "dealer" ? 1 : 0),
							"date_shipped" => date("m/d/Y"), "secondary_shipment" => (empty($_POST['secondary_shipment']) ? 0 : 1))));
						$orderItemCount = 0;
						$resultSet = executeQuery("select * from remote_order_items where remote_order_id = ?", $shipmentInformation['remote_order_id']);
						while ($thisOrderItem = getNextRow($resultSet)) {
							$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
							$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $inventoryLocationRow['location_id']);
							executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
								$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
							$orderItemCount++;

# add to product inventory log

							if (empty($_POST['secondary_shipment'])) {
								if (empty($GLOBALS['gSalesAdjustmentTypeId'])) {
									$GLOBALS['gPrimaryDatabase']->logError("Sales Adjustment type not found");
								} else {
									$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", $inventoryLocationRow['location_id']);
									if (!empty($productInventoryId)) {
										$productInventoryLogId = getFieldFromId("product_inventory_log_id", "product_inventory_log", "product_inventory_id", $productInventoryId,
											"inventory_adjustment_type_id = ? and order_id = ?", $GLOBALS['gSalesAdjustmentTypeId'], $orderId);
										if (empty($productInventoryLogId)) {
											executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,order_id,user_id,log_time,quantity) values " .
												"(?,?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderId, $GLOBALS['gUserId'], $thisOrderItem['quantity']);
										} else {
											executeQuery("update product_inventory_log set quantity = quantity + " . $thisOrderItem['quantity'] . " where product_inventory_log_id = ?", $productInventoryLogId);
										}
									}
									executeQuery("update product_inventories set quantity = greatest(0,quantity - " . $thisOrderItem['quantity'] . ") where product_inventory_id = ?", $productInventoryId);
								}
							}
						}
						if ($orderItemCount == 0) {
							$remoteOrderId = getFieldFromId("remote_order_id", "order_shipments", "order_shipment_id", $orderShipmentId);
							executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
							executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
							executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
						}
					}
				}
				$shippedQuantities = array();
				foreach ($orderItems as $index => $thisOrderItem) {
					$shippedSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0 and internal_use_only = 0)", $thisOrderItem['order_item_id']);
					while ($shippedRow = getNextRow($shippedSet)) {
						$shippedQuantities[$thisOrderItem['order_item_id']] = $shippedRow['sum(quantity)'];
					}
					if (empty($shippedQuantities[$thisOrderItem['order_item_id']])) {
						$shippedQuantities[$thisOrderItem['order_item_id']] = 0;
					}
				}
				$returnArray['shipped_quantities'] = $shippedQuantities;
				coreSTORE::orderNotification($orderId, "shipment_created");

				ajaxResponse($returnArray);

				break;
			case "get_inventory":
				$locationRow = getRowFromId("locations", "location_id", $_GET['location_id']);
				if (empty($locationRow)) {
					$returnArray['error_message'] = "Invalid Location";
					ajaxResponse($returnArray);
					break;
				}
				$productDistributorId = $locationRow['product_distributor_id'];
				if (!empty($productDistributorId) && empty($locationRow['primary_location'])) {
					$inventoryLocationRow = getRowFromId("locations", "product_distributor_id", $productDistributorId, "primary_location = 1");
					if (empty($inventoryLocationRow)) {
						$inventoryLocationRow = $locationRow;
					}
				} else {
					$inventoryLocationRow = $locationRow;
				}
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				$productInventories = array();
				while ($row = getNextRow($resultSet)) {
					$virtualProduct = getFieldFromId("virtual_product", "products", "product_id", $row['product_id']);
					if ($virtualProduct) {
						$productInventories[$row['order_item_id']] = array("unavailable" => true, "quantity" => 0);
						continue;
					}
					$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $inventoryLocationRow['location_id']);
					if (empty($productInventoryRow['quantity'])) {
						$productInventoryRow['quantity'] = 0;
					}
					$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $inventoryLocationRow['location_id'], $productInventoryRow,false);
					$nonInventoryItem = getFieldFromId("non_inventory_item", "products", "product_id", $row['product_id']);
					if (!empty($nonInventoryItem)) {
						$productInventoryRow['quantity'] = 999999;
					}
					$productInventories[$row['order_item_id']] = array("quantity" => $productInventoryRow['quantity'], "cost" => (empty($cost) ? "" : number_format($cost, 2, ".", ",")));
				}
				$returnArray['product_inventories'] = $productInventories;
				$returnArray['product_distributor_id'] = $productDistributorId;
				ajaxResponse($returnArray);
				break;
			case "save_order_status":
				if (Order::updateOrderStatus($_GET['order_id'], $_GET['order_status_id'])) {
					$returnArray['mark_completed'] = getFieldFromId("mark_completed", "order_status", "order_status_id", $_GET['order_status_id']);
				}
				ajaxResponse($returnArray);
				break;
			case "save_order_item_status":
				$orderItemDataTable = new DataTable("order_items");
				$orderItemDataTable->setSaveOnlyPresent(true);
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_item_id", $_GET['order_item_id'], "order_id = ?", $_GET['order_id']);
				if (empty($orderItemId)) {
					$returnArray['error_message'] = "Invalid Order Item";
					ajaxResponse($returnArray);
					break;
				}
				if ($_GET['order_item_status_id'] == -1) {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId));
				} else {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 0, "order_item_status_id" => $_GET['order_item_status_id']), "primary_id" => $orderItemId));
				}
				$orderItemStatusCode = getFieldFromId("order_item_status_code", "order_item_statuses", "order_item_status_id", $_GET['order_item_status_id']);
				switch ($orderItemStatusCode) {
					case "BACKORDER":
						$orderItemRow = getRowFromId("order_items", "order_item_id", $orderItemId);
						$productId = $orderItemRow['product_id'];
						$productCatalog = new ProductCatalog();
						$totalInventory = $productCatalog->getInventoryCounts(true, $productId, false, array("ignore_backorder" => true));
						$emailAddress = getPreference("BACKORDERED_ITEM_AVAILABLE_NOTIFICATION");
						if (empty($emailAddress)) {
							$emailAddress = $GLOBALS['gUserRow']['email_address'];
						}
						if ($totalInventory <= 0) {
							$productInventoryNoticationId = getFieldFromId("product_inventory_notification_id", "product_inventory_notifications", "product_id", $productId);
							if (empty($productInventoryNoticationId)) {
								executeQuery("insert into product_inventory_notifications (product_id,user_id,email_address,comparator,quantity,order_quantity,place_order,use_lowest_price,allow_multiple) values " .
									"(?,?,?,?,?, ?,?,?,?)", $productId, $GLOBALS['gUserId'], $emailAddress, ">", 0, $orderItemRow['quantity'], 1, 1, 1);
							}
						}
						break;
				}
				coreSTORE::orderNotification($_GET['order_id'], "order_item_changed");
				ajaxResponse($returnArray);
				break;
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			if (is_numeric($filterText) && strlen($filterText) >= 6) {
				$this->iDataSource->addFilterWhere("order_id = " . makeNumberParameter($filterText) . " or order_number = " . makeNumberParameter($filterText) .
					(strlen($filterText) == 10 ? " or orders.contact_id in (select contact_id from phone_numbers where phone_number = '" . formatPhoneNumber($filterText) . "')" : "") .
					(strlen($filterText) >= 10 ? " or order_id in (select order_id from order_items where product_id in " .
						"(select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "'" .
						") or product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "'" .
						") or product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "'))" : "") .
					" or order_id in (select order_id from order_items where order_item_id in (select order_item_id from order_item_serial_numbers where serial_number = '" . $GLOBALS['gPrimaryDatabase']->makeNumberParameter($filterText) . "'))");
			} else {
				$parts = explode(" ", $filterText);
				$whereStatement = "order_id = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText);
				if (count($parts) == 2) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "(contact_id in (select contact_id from contacts where first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
						" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . "))";
				}
				$whereDetails = "";
				foreach ($this->iSearchContactFields as $fieldName) {
					$whereDetails .= (empty($whereDetails) ? "" : " or ") . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contacts where " . $whereDetails . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contact_identifiers where identifier_value = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where license_number = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "order_id in (select order_id from order_shipments where tracking_identifier = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . " order_id in (select order_id from order_items" .
					" where description like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") .
					" or product_id in (select product_id from product_data where upc_code = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . " or manufacturer_sku = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) .
					") or product_id in (select product_id from products where product_code = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) .
					") or order_item_id in (select order_item_id from order_item_serial_numbers where serial_number like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			}
		}
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("click", ".addon-form", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_addon_form_summary&order_item_addon_id=" + $(this).data("order_item_addon_id"), function (returnArray) {
                    $("#_addon_form_content").html(returnArray['content']);
                    $('#_addon_form_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 1000,
                        title: 'Addon Form Summary',
                        buttons: {
                            Close: function (event) {
                                $("#_addon_form_dialog").dialog('close');
                            }
                        }
                    });
                });
            });
            $(document).on("click", "#create_shipment_settings", function () {
                const showSettings = $("#show_settings").prop("checked");
                let buttons = [
                    {
                        text: "Close",
                        click: function () {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_show_settings&show_settings=" + ($("#show_settings").prop("checked") ? "true" : ""));
                            $("#_create_shipment_settings_dialog").dialog('close');
                        }
                    }
                ];
                if (showSettings) {
                    buttons.push(
                        {
                            text: "Create Shipment",
                            click: function () {
                                $("#create_shipment").data("execute", true).trigger("click");
                                $("#_create_shipment_settings_dialog").dialog('close');
                            }
                        }
                    )
                }
                $('#_create_shipment_settings_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: (!showSettings ? "Settings for creating a shipment" : "Create a shipment"),
                    buttons: buttons
                });
            });
            $(document).on("click", ".issue-gift-card", function () {
                const $thisButton = $(this);
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=issue_gift_card&order_item_id=" + $(this).data("order_item_id"), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $thisButton.parents(".product-description-wrapper").find(".product-description").html(returnArray['product_description']);
                        $thisButton.prev("br").remove();
                        $thisButton.remove();
                    }
                });
                return false;
            });
            $(document).on("click", ".resend-gift-card-email", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=resend_gift_card_email&order_item_id=" + $(this).data("order_item_id"));
                return false;
            });
            $(document).on("click change", "#show_order_touchpoints", function () {
                $(".touchpoint").addClass("hidden");
                $(".touchpoint.order-" + $("#primary_id").val()).removeClass("hidden");
                if (!$(this).prop("checked")) {
                    $(".touchpoint").removeClass("hidden");
                }
            });
            $(document).on("click", "#send_text_message", function () {
                $("#_send_text_message_form").clearForm();
                $('#_send_text_message_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Send Text Message to Customer',
                    open: function () {
                        addCKEditor();
                    },
                    buttons: {
                        Send: function (event) {
                            if ($("#_send_text_message_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_text_message&order_id=" + $("#primary_id").val(), $("#_send_text_message_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_send_text_message_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_send_text_message_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#fraud_report_button", function () {
                $('#_fraud_report_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Fraud Report',
                    open: function (event, ui) {
                        $("html, body").animate({ scrollTop: 0 }, 600);
                    },
                    buttons: {
                        Close: function (event) {
                            $("#_fraud_report_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $("#_confirm_delete_dialog").find(".dialog-text").html("Are you sure you want to delete this order? Any loyalty points earned from this order will be permanently removed and loyalty point used to pay for the order will be restored.");
            $(document).on("click", ".order-tag", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_tag&order_id=" + $("#primary_id").val() + "&order_tag_id=" + $(this).data("order_tag_id") + "&checked=" + ($(this).prop("checked") ? "true" : ""));
            });
            if ($("#location_group_id").find("option[value!='']").filter("option[value!='-1']").filter("option[value!='-9999']").length === 0) {
                $("#_location_group_id_row").remove();
            }
            $("#location_group_id").change(function () {
                $("#location_id").find("option").unwrap("span");
                if (!empty($(this).val())) {
                    const locationGroupId = $(this).val();
                    $("#location_id").find("option[value!='']").each(function () {
                        const thisLocationGroupId = $(this).data("location_group_id");
                        if (thisLocationGroupId != locationGroupId) {
                            $(this).wrap("<span></span>");
                        }
                    });
                }
            });
            $(document).on("click", ".delete-payment", function () {
                const $thisPayment = $(this).closest("tr");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_payment&order_id=" + $("#primary_id").val() + "&order_payment_id=" + $thisPayment.data("order_payment_id"), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $thisPayment.addClass("deleted-payment");
                        if (!$("#show_deleted_payments").prop("checked")) {
                            $thisPayment.addClass("hidden");
                        }
                        $thisPayment.find(".not-captured").html(returnArray['not_captured']);
                        $("#_capture_message").html(returnArray['_capture_message']);
                        if (empty(returnArray['_capture_message'])) {
                            enableButtons($("#create_shipment"));
                        } else {
                            disableButtons($("#create_shipment"));
                        }
                    }
                });
            });
            $(document).on("click", ".undelete-payment", function () {
                const $thisPayment = $(this).closest("tr");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=undelete_payment&order_id=" + $("#primary_id").val() + "&order_payment_id=" + $thisPayment.data("order_payment_id"), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $thisPayment.removeClass("deleted-payment");
                        $thisPayment.removeClass("hidden");
                        $("#_capture_message").html(returnArray['_capture_message']);
                        $thisPayment.find(".not-captured").html(returnArray['not_captured']);
                        if (empty(returnArray['_capture_message'])) {
                            enableButtons($("#create_shipment"));
                        } else {
                            disableButtons($("#create_shipment"));
                        }
                    }
                });
            });
            $(document).on("click", "#show_deleted_payments", function () {
                if ($(this).prop('checked')) {
                    $("#order_payments").find("tr.deleted-payment").removeClass("hidden");
                } else {
                    $("#order_payments").find("tr.deleted-payment").addClass("hidden");
                }
            });
            $(document).on("click", ".ffl-required", function () {
                $("html, body").animate({ scrollTop: $("#ffl_section_wrapper").offset().top }, 800);
            });
            $(document).on("click", "#add_payment", function () {
                $('#_payment_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Add Payment',
                    buttons: {
                        Save: function (event) {
                            if ($("#_payment_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_payment&order_id=" + $("#primary_id").val(), $("#_payment_form").serialize(), function (returnArray) {
                                    if ("order_payments" in returnArray) {
                                        $("#_payment_dialog").dialog('close');
                                        $("#_payment_form").clearForm();
                                        $("#order_payments").find(".order-payment").remove();
                                        for (let i in returnArray['order_payments']) {
                                            let orderPaymentBlock = $("#_order_payment_template").html();
                                            for (let j in returnArray['order_payments'][i]) {
                                                const re = new RegExp("%" + j + "%", 'g');
                                                orderPaymentBlock = orderPaymentBlock.replace(re, returnArray['order_payments'][i][j]);
                                            }
                                            let additionalClasses = "";
                                            if (!empty(returnArray['order_payments'][i]['deleted'])) {
                                                additionalClasses = "deleted-payment hidden";
                                            }
                                            orderPaymentBlock = orderPaymentBlock.replace(new RegExp("%additional_classes%", 'g'), additionalClasses);
                                            $("#order_payments").append(orderPaymentBlock);
                                        }
                                        $(".order-payment-invoice-id-").remove();
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_payment_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", ".distributor-order-product", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=distributor_order_product", { product_id: $(this).data("product_id"), quantity: $(this).data("quantity"), order_id: $("#primary_id").val(), order_item_id: $(this).closest(".order-item").data("order_item_id"), location_id: $("#location_id").val() });
            });
            if ($("#location_id").find("option[value!='']").length === 1) {
                $("#location_id").find("option[value='']").remove();
            }
            $(document).on("click", "#resend_receipt", function () {
                $('#_confirm_receipt_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Resend Receipt Email',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=resend_receipt&order_id=" + $("#primary_id").val());
                            $("#_confirm_receipt_email_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_receipt_email_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#letter_package", function () {
                if ($(this).prop("checked")) {
                    $(".letter-package").addClass("hidden");
                    $("#weight").val("3");
                    $("#weight_unit").val("ounce");
                    $("#signature_required").prop("checked", false);
                    $("#adult_signature_required").prop("checked", false);
                    $("#no_email").prop("checked", true);
                } else {
                    $(".letter-package").removeClass("hidden");
                    $("#no_email").prop("checked", false);
                }
            });
            $(document).on("tap click", "#_pickup_ready_button", function () {
                const primaryId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_ready_for_pickup&order_id=" + primaryId, function (returnArray) {
                    if ("order_status_id" in returnArray) {
                        $("#order_status_id").val(returnArray['order_status_id']);
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_picked_up_button", function () {
                const primaryId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=order_picked_up&order_id=" + primaryId, function (returnArray) {
                    $("#_list_button").trigger("click");
                });
                return false;
            });
            $(document).on("tap click", "#_refunds_button", function () {
                const primaryId = $("#primary_id").val();
                document.location = "/refunddashboard.php?url_page=show&clear_filter=true&primary_id=" + primaryId;
                return false;
            });
            $(document).on("change", "#help_desk_answer_id, #text_help_desk_answer_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + $(this).data("url_action") + "&help_desk_answer_id=" + $(this).val(), function (returnArray) {
                        if ("content" in returnArray) {
                            CKEDITOR.instances['email_body'].setData(returnArray['content']);
                        } else if ("text_content" in returnArray) {
                            $("#text_message").val(returnArray['text_content']);
                        }
                    });
                }
            });
            $(document).on("click", "#print_receipt", function () {
                const orderId = $("#primary_id").val();
                window.open("/admin-order-receipt?order_id=" + orderId + "&printable=true");
                return false;
            });
            $(document).on("click", ".print-packing-slip", function () {
                const orderShipmentId = $(this).closest("tr").data("order_shipment_id");
                window.open("/packing-slip?order_shipment_id=" + orderShipmentId);
                return false;
            });
            $("#_secondary_shipment_row").addClass("secondary-shipment");
            $(document).on("click", "#secondary_shipment", function () {
                if (!$(this).prop("checked")) {
                    $("#order_items").find(".shipment-quantity").val("0");
                }
                $("#location_id").trigger("change");
            });


			<?php if ($this->iEasyPostActive) { ?>
            $(document).on("change", "#ship_to", function () {
                $("#_easy_post_to_address").find("input").not("input[type=checkbox]").val("");
                if (!empty($(this).val()) && $(this).val() !== "-1") {
                    const thisAddress = shippingAddresses[$(this).val()];
                    $("#to_full_name").val(thisAddress['full_name']);
                    $("#to_address_1").val(thisAddress['address_1']);
                    $("#to_address_2").val(thisAddress['address_2']);
                    $("#to_city").val(thisAddress['city']);
                    $("#to_state").val(thisAddress['state']);
                    $("#to_postal_code").val(thisAddress['postal_code']);
                    $("#to_country_id").val(thisAddress['country_id']);
                    $("#to_phone_number").val(thisAddress['phone_number']);
                    $("#to_attention_line").val(thisAddress['attention_line']);
                    $("#residential_address").prop("checked", thisAddress['residential']);
                    checkForCustoms();
                }
            });
            $(document).on("change", "#ship_from", function () {
                $("#_easy_post_from_address").find("input").not("input[type=checkbox]").val("");
                if (!empty($(this).val()) && $(this).val() !== "-1") {
                    const thisAddress = shippingAddresses[$(this).val()];
                    $("#from_full_name").val(thisAddress['full_name']);
                    $("#from_address_1").val(thisAddress['address_1']);
                    $("#from_address_2").val(thisAddress['address_2']);
                    $("#from_city").val(thisAddress['city']);
                    $("#from_state").val(thisAddress['state']);
                    $("#from_postal_code").val(thisAddress['postal_code']);
                    $("#from_country_id").val(thisAddress['country_id']);
                    $("#from_phone_number").val(thisAddress['phone_number']);
                }
            });
            $(document).on("change", "#_recently_used_dimensions", function () {
                const selectedDimension = $("#_recently_used_dimensions option:selected").text();
                if (selectedDimension != "[None]") {
                    const dimensions = selectedDimension.split("x");
                    $("#height").val(dimensions[0]);
                    $("#width").val(dimensions[1]);
                    $("#length").val(dimensions[2]);
                }
            });

            function checkForCustoms() {
                if ([ 'AA', 'AE', 'AP', 'AS', 'FM', 'GU', 'GUAM', 'MH', 'MP', 'PW' ].includes($("#to_state").val().toUpperCase())) {
                    $("#_customs_required").val(1);
                    $("#customs_info").removeClass("hidden");
                    if ($("#_customs_items_table .editable-list-data-row").length == 0) {
                        let orderShipmentId = $("#primary_id").val();
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_easy_post_customs_items&order_shipment_id=" + orderShipmentId, function (returnArray) {
                            if ("customs_items" in returnArray) {
                                returnArray["customs_items"].forEach(function (row) {
                                    addEditableListRow("customs_items", row);
                                });
                            }
                        });
                    }
                } else {
                    $("#customs_info").addClass("hidden");
                    $("#_customs_required").val(0);
                }
            }

            $(document).on("change", "#to_state", function () {
                checkForCustoms();
            });
            $(document).on("change", "#customs_contents_type", function () {
                if ($("#customs_contents_type option:selected").val() == "other") {
                    $("#_customs_contents_explanation_row").removeClass("hidden");
                    $("#customs_contents_explanation").addClass("validate[required]");
                } else {
                    $("#_customs_contents_explanation_row").addClass("hidden");
                    $("#customs_contents_explanation").removeClass("validate[required]");
                }
            });
            $(document).on("change", "#customs_restriction_type", function () {
                if ($("#customs_restriction_type option:selected").val() == "other") {
                    $("#_customs_restriction_comments_row").removeClass("hidden");
                    $("#customs_restriction_comments").addClass("validate[required]");
                } else {
                    $("#_customs_restriction_comments_row").addClass("hidden");
                    $("#customs_restriction_comments").removeClass("validate[required]");
                }
            });
            $(document).on("change", "#customs_eel_pfc", function () {
                if ($("#customs_eel_pfc option:selected").val() == "other") {
                    $("#_customs_eel_pfc_other_row").removeClass("hidden");
                    $("#customs_eel_pfc_other").addClass("validate[required]");
                } else {
                    $("#_customs_eel_pfc_other_row").addClass("hidden");
                    $("#customs_eel_pfc_other").removeClass("validate[required]");
                }
            });

            $(document).on("click", ".create-shipping-label", function () {
                const orderShipmentId = $(this).closest("tr").data("order_shipment_id");
                const labelUrl = $(this).closest("tr").find(".label-url").val();
                if (!empty(labelUrl)) {
                    window.open(labelUrl);
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_shipment_details&order_shipment_id=" + orderShipmentId, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#_easy_post_to_address").clearForm();
                        $("#_easy_post_parameters").clearForm();
                        $("#shipment_details").html(returnArray['shipment_details']);
                        $("#height").val(returnArray['height']);
                        $("#width").val(returnArray['width']);
                        $("#length").val(returnArray['length']);
                        $("#weight").val(returnArray['weight']);
                        $("#insurance_amount").val("");
                        $("#adult_signature_required").prop("checked", returnArray['adult_signature_required']);
                        $("#signature_required").prop("checked", returnArray['signature_required'] && !returnArray['adult_signature_required']);

                        $("#ship_to").find("option").remove();
                        shippingAddresses = {};
                        $("#ship_to").append($("<option></option>").attr("value", "").text("[Select]"));
                        $("#ship_to").append($("<option></option>").attr("value", "-1").text("[Custom Address]"));
                        for (let i in returnArray['addresses']) {
                            shippingAddresses[returnArray['addresses'][i]['key_value']] = returnArray['addresses'][i];
                            const thisOption = $("<option></option>").attr("value", returnArray['addresses'][i]['key_value']).text(returnArray['addresses'][i]['description']);
                            $("#ship_to").append(thisOption);
                        }
                        $("#ship_to").val(returnArray['ship_to']).trigger("change");

                        $("#ship_from").find("option").remove();
                        $("#ship_from").append($("<option></option>").attr("value", "").text("[Select]"));
                        $("#ship_from").append($("<option></option>").attr("value", "-1").text("[Custom Address]"));
                        for (const i in returnArray['addresses']) {
                            const thisOption = $("<option></option>").attr("value", returnArray['addresses'][i]['key_value']).text(returnArray['addresses'][i]['description']);
                            $("#ship_from").append(thisOption);
                        }
                        $("#ship_from").val(returnArray['ship_from']).trigger("change");

                        $("#postage_rates").html("");
                        $("#_easy_post_wrapper").find("input").prop("disabled", false);
                        $("#_easy_post_wrapper").find("select").prop("disabled", false);
                        if (!empty($("#ffl_required").val()) && empty(returnArray['adult_signature_required'])) {
                            $("#signature_required").prop("checked", true);
                        }
                        $("#height").focus();

                        $('#_easy_post_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1200,
                            title: 'Create Postage Shipping Label',
                            buttons:
                                [
                                    {
                                        text: "Get Rates",
                                        click: function () {
                                            if ($("#_easy_post_form").validationEngine("validate")) {
                                                if (empty($("#postage_rates").html())) {
                                                    $("#postage_rates").html("<h4 class='align-center'>Getting Available Rates...</h4>");
                                                    $(".create-label-button").prop("disabled", true);
                                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_easy_post_label_rates&order_shipment_id=" + orderShipmentId, $("#_easy_post_form").serialize(), function (returnArray) {
                                                        if (!("error_message" in returnArray)) {
                                                            $("#_easy_post_wrapper").find("input").prop("disabled", true);
                                                            $("#_easy_post_wrapper").find("select").prop("disabled", true);
                                                            $(".create-label-button").html("Create Label");
                                                            $(".create-label-button").prop("disabled", false);
                                                            $("#postage_rates").html("<h3>Available Rates (Choose One)</h3>");
                                                            if (!empty(returnArray['insurance_charge'])) {
                                                                $("#postage_rates").append("<p class='green-text'>Insurance charges: " + returnArray['insurance_charge'] + "</p>");
                                                            }
                                                            $("#postage_rates").append("<input type='hidden' id='rate_shipment_id' name='rate_shipment_id' value=''>");
                                                            for (const i in returnArray['rates']) {
                                                                $("#postage_rates").append("<p><input class='rate-shipment-id' type='hidden' id='rate_shipment_id_" + i + "' value='" + returnArray['rates'][i]['rate_shipment_id'] +
                                                                    "'><input tabindex='10' type='radio' class='validate[required] postage-rate' id='postage_rate_" + i + "' name='postage_rate_id' value='" +
                                                                    returnArray['rates'][i]['id'] + "'><label class='checkbox-label' for='postage_rate_" + i +
                                                                    "'>" + returnArray['rates'][i]['rate'] + ", " + returnArray['rates'][i]['description'] + "</label></p>");
                                                            }
                                                        }
                                                    });
                                                } else {
                                                    $("#_easy_post_wrapper").find("input").prop("disabled", false);
                                                    $("#_easy_post_wrapper").find("select").prop("disabled", false);
                                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_easy_post_label&order_shipment_id=" + orderShipmentId, $("#_easy_post_form").serialize(), function (returnArray) {
                                                        if (!("error_message" in returnArray)) {
                                                            $("#order_shipment_" + orderShipmentId).find(".shipping-charge").html(returnArray['shipping_charge']);
                                                            $("#order_shipment_" + orderShipmentId).find(".full-name").html(returnArray['full_name']);
                                                            $("#no_notifications_" + orderShipmentId).prop("checked", (returnArray['no_notifications'] === "1"));
                                                            $("#tracking_identifier_" + orderShipmentId).val(returnArray['tracking_identifier']);
                                                            $("#carrier_description_" + orderShipmentId).val(returnArray['carrier_description']);
                                                            $("#shipping_carrier_id_" + orderShipmentId).val(returnArray['shipping_carrier_id']);
                                                            $("#order_shipment_" + orderShipmentId).find(".label-url").val(returnArray['label_url']);
                                                            window.open(returnArray['label_url']);
                                                            $("#_easy_post_dialog").dialog('close');
                                                        } else {
                                                            $("#_easy_post_wrapper").find("input").prop("disabled", true);
                                                            $("#_easy_post_wrapper").find("select").prop("disabled", true);
                                                        }
                                                    });
                                                }
                                            }
                                        },
                                        'class': 'create-label-button'
                                    },
                                    {
                                        text: "Cancel",
                                        click: function () {
                                            $("#_easy_post_dialog").dialog('close');
                                        }
                                    }
                                ]
                        });
                    }
                });
                return false;
            });
			<?php } else {if ($this->iFFLActive) { ?>
			<?php } else { ?>
			<?php }} ?>
            $(document).on("click", "#add_file", function () {
                if (empty($("#order_files_description").val()) || empty($("#order_files_file_id_file").val())) {
                    displayErrorMessage("Description and file are both required");
                    return false;
                }
                $("body").addClass("waiting-for-ajax");
                $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_file").attr("method", "POST").attr("target", "post_iframe").submit();
                $("#_post_iframe").off("load");
                $("#_post_iframe").on("load", function () {
                    $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                    const returnText = $(this).contents().find("body").html();
                    const returnArray = processReturn(returnText);
                    if (returnArray === false) {
                        enableButtons($("#_submit_form"));
                        return;
                    }
                    if (!("error_message" in returnArray)) {
                        $("#order_files_description").val("");
                        $("#order_files_file_id_file").val("");
                        let orderFileBlock = $("#_order_file_template").html();
                        for (const j in returnArray['order_file']) {
                            const re = new RegExp("%" + j + "%", 'g');
                            orderFileBlock = orderFileBlock.replace(re, returnArray['order_file'][j]);
                        }
                        $("#order_files").append(orderFileBlock);
                        $(".no-order-files").remove();
                    }
                });
                return false;
            });

			<?php if (getPreference("CENTRALIZED_FFL_STORAGE")) { ?>
            $("#remove_ffl").remove();
            $("#add_ffl").remove();
			<?php } else { ?>
            $(document).on("click", "#remove_ffl", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_ffl&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#selected_ffl").html("None Selected");
                    }
                });
                return false;
            });
            $(document).on("click", "#add_ffl", function () {
                window.open("/federal-firearms-licenses?url_page=new");
                return false;
            });
			<?php } ?>
            $(document).on("click", ".upload-license", function () {
                window.open("/federal-firearms-licenses?clear_filter=true&primary_id_only=true&url_page=show&primary_id=" + $("#federal_firearms_licensee_id").val());
            });
            $("#ffl_search_text").keyup(function (event) {
                if (event.which === 13 || event.which === 3) {
                    $(this).blur();
                }
                return false;
            });
            $("#ffl_search_text").change(function () {
                getFFLDealers();
            });
            $(document).on("click", ".ffl-dealer", function () {
                const fflId = $(this).data("federal_firearms_licensee_id");
                $("#federal_firearms_licensee_id").val(fflId).trigger("change");
            });

            $("#federal_firearms_licensee_id").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_ffl_details&federal_firearms_licensee_id=" + $(this).val() + "&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#selected_ffl").html(returnArray['selected_ffl']);
                    }
                });
            });

            $(document).on("click", "#send_email", function () {
                $("#_send_email_form").clearForm();
                $('#_send_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Send Email to Customer',
                    open: function () {
                        addCKEditor();
                    },
                    buttons: {
                        Send: function (event) {
                            for (instance in CKEDITOR.instances) {
                                CKEDITOR.instances[instance].updateElement();
                            }
                            if ($("#_send_email_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_email&order_id=" + $("#primary_id").val(), $("#_send_email_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_send_email_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_send_email_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("blur", ".anticipated-ship-date", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_anticipated_ship_date", { order_id: $("#primary_id").val(), order_item_id: $(this).closest(".order-item").data("order_item_id"), anticipated_ship_date: $(this).val() });
            });
            $(document).on("blur", ".download-date", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_download_date", { order_id: $("#primary_id").val(), order_item_id: $(this).closest(".order-item").data("order_item_id"), download_date: $(this).val() });
            });
            $(document).on("blur", ".serial-number", function () {
                const rowNumber = $(this).closest("tr").data("row_number");
                const primaryId = $(this).closest("tr").find(".editable-list-primary-id").val();
                const primaryIdElement = $(this).closest("tr").find(".editable-list-primary-id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_serial_number", { order_id: $("#primary_id").val(), order_item_id: $(this).closest(".order-item").data("order_item_id"), serial_number: $(this).val(), primary_id: primaryId }, function (returnArray) {
                    if ("primary_id" in returnArray) {
                        primaryIdElement.val(returnArray['primary_id']);
                    }
                });
            });
            $(document).on("click", "#reopen_order", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=reopen_order", { order_id: $("#primary_id").val() }, function (returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&primary_id=" + $("#primary_id").val();
                });
                return false;
            });

            $(document).on("click", "#mark_completed", function () {
                if (empty($(this).data("skip_before_mark_completed")) && typeof beforeMarkCompleted == "function") {
                    if (!beforeMarkCompleted()) {
                        return false;
                    }
                }
                $(this).data("skip_before_mark_completed", "");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_completed", { order_id: $("#primary_id").val() }, function (returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                });
                return false;
            });
            $(document).on("click", ".print-1508", function () {
                const orderId = $("#primary_id").val();
                window.open("/print-1508?order_id=" + orderId + "&printable=true");
            });
            $(document).on("click", ".track-package", function () {
                if (!empty($(this).data("link_url"))) {
                    window.open($(this).data("link_url"));
                }
            });
            $(document).on("click", ".send-tracking-email", function () {
                const orderShipmentId = $(this).closest(".order-shipment").data("order_shipment_id");
                $('#_confirm_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Send Tracking Email',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_tracking_email", { order_shipment_id: orderShipmentId });
                            $("#_confirm_email_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_email_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", ".delete-shipping-item", function () {
                const orderShipmentId = $(this).closest(".order-shipment-item").data("order_shipment_id");
                const orderShipmentItemId = $(this).closest(".order-shipment-item").data("order_shipment_item_id");
                $('#_confirm_delete_item_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Remove Item from Shipment',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_shipment_item", { order_shipment_item_id: orderShipmentItemId, order_shipment_id: orderShipmentId }, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    $("#order_shipment_item_" + orderShipmentItemId).remove();
                                    if ("delete_shipment" in returnArray) {
                                        $("#order_shipment_" + orderShipmentId).remove();
                                    }
                                    if ($("#order_shipments").find(".order-shipment").length === 0) {
                                        $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Shipments yet</td></tr>");
                                    }
                                    $("#order_shipment_" + orderShipmentId).find(".amount").html(returnArray['shipment_amount']);
                                    getOrderItems();
                                }
                            });
                            $("#_confirm_delete_item_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_item_dialog").dialog('close');
                        }
                    }
                });
            });

            function textareaVal(id, newValue) {
                if ($("#" + id).length > 0) {
                    if (typeof CKEDITOR == 'object' && typeof CKEDITOR.instances[id] == 'object') {
                        if (typeof newValue == 'undefined') {
                            return CKEDITOR.instances[id].getData();
                        } else {
                            CKEDITOR.instances[id].setData(newValue);
                        }
                    } else {
                        if (typeof newValue == 'undefined') {
                            return $("#" + id).val();
                        } else {
                            $("#" + id).val(newValue);
                        }
                    }
                }
            }

            $(document).on("click", "#add_note", function () {
                noteContent = textareaVal("content");
                if (empty(noteContent)) {
                    noteContent = $("#content").val();
                }
                if (!empty(noteContent)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_note", { content: noteContent, order_id: $("#primary_id").val(), public_access: $("#public_access").prop("checked") ? 1 : 0 }, function (returnArray) {
                        if ("order_note" in returnArray) {
                            $("#order_notes").find(".no-order-notes").remove();
                            let orderNoteBlock = $("#_order_note_template").html();
                            for (const j in returnArray['order_note']) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_note'][j]);
                            }
                            $("#order_notes").append(orderNoteBlock);
                            textareaVal("content", "");
                            $("#public_access").prop("checked", false);
                        }
                    });
                }
                return false;
            });
            $(document).on("change", ".editable-shipping-field", function () {
                const orderShipmentId = $(this).closest(".order-shipment").data("order_shipment_id");
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['order_shipment_id'] = orderShipmentId;
                postVariables['field_name'] = $(this).data("field_name");
                postVariables['field_data'] = $(this).val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_shipment_details", postVariables);
                displayInfoMessage("Send the tracking email if needed");
            });
            $(document).on("click", ".no-notification", function () {
                const orderShipmentId = $(this).closest(".order-shipment").data("order_shipment_id");
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['order_shipment_id'] = orderShipmentId;
                postVariables['field_name'] = "no_notifications";
                postVariables['field_data'] = ($(this).prop("checked") ? "1" : "0");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_shipment_details", postVariables);
            });
            $(document).on("click", ".capture-payment", function () {
                const $orderPaymentRow = $(this).closest("tr");
                const orderPaymentId = $orderPaymentRow.data("order_payment_id");
                const $thisButton = $(this);
                $thisButton.addClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=capture_payment&order_payment_id=" + orderPaymentId + "&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        $thisButton.removeClass("hidden");
                    } else {
                        $orderPaymentRow.find(".not-captured").html("Done");
                        $orderPaymentRow.find(".transaction-identifier").html(returnArray['transaction_identifier']);
                        $("#_capture_message").html(returnArray['_capture_message']);
                        if (empty(returnArray['_capture_message'])) {
                            enableButtons($("#create_shipment"));
                        } else {
                            disableButtons($("#create_shipment"));
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#create_shipment", function () {
                if ($("#show_settings").prop("checked")) {
                    if (empty($(this).data("execute"))) {
                        $("#create_shipment_settings").trigger("click");
                        return false;
                    }
                }
                $(this).data("execute", "");
                let latestOrderShipmentId = "";
                $("#order_shipments tr.order-shipment").each(function () {
                    const orderShipmentId = $(this).data("order_shipment_id");
                    if (empty(orderShipmentId) || isNaN(orderShipmentId)) {
                        return true;
                    }
                    if (empty(latestOrderShipmentId) || parseInt(orderShipmentId) > parseInt(latestOrderShipmentId)) {
                        latestOrderShipmentId = orderShipmentId;
                    }
                });
                if ($(".capture-payment").length > 0) {
                    displayErrorMessage("All payments must be captured first.");
                    return false;
                }
                if (empty($("#location_id").val()) && !$("#secondary_shipment").prop("checked")) {
                    displayErrorMessage("Select a shipping location");
                    return false;
                }
                let totalQuantity = 0;
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['location_id'] = $("#location_id").val();

                $("#_create_shipment_settings_dialog input").add("#_create_shipment_settings_dialog select").each(function () {
                    if ($(this).is("input[type=checkbox]")) {
                        postVariables[$(this).attr("id")] = ($(this).prop("checked") ? "1" : "");
                    } else {
                        postVariables[$(this).attr("id")] = $(this).val();
                    }
                });
                postVariables['latest_order_shipment_id'] = latestOrderShipmentId;
                const orderItems = {};
                if (!empty($("#ffl_required").val()) && empty($("#federal_firearms_licensee_id").val()) && empty(postVariables['dont_dropship'])) {
                    displayErrorMessage("FFL Dealer must first be selected");
                    return false;
                }
                $("#order_items").find(".shipment-quantity").each(function () {
                    totalQuantity += $(this).val();
                    if (!empty($(this).val())) {
                        const orderItemId = $(this).closest("tr").data("order_item_id");
                        orderItems[orderItemId] = { order_item_id: orderItemId, quantity: $(this).val() };
                    }
                });
                postVariables['order_items'] = orderItems;
                if (totalQuantity <= 0) {
                    displayErrorMessage("No item quantities set");
                    return false;
                }
                const productDistributorId = $("#location_id").data("product_distributor_id");
                $('#_confirm_shipment_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Create Shipment',
                    open: function (event, ui) {
                        if (empty(productDistributorId)) {
                            $(".confirm-create-shipment").trigger("click");
                        }
                    },
                    buttons:
                        [
                            {
                                text: "Yes",
                                click: function () {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_shipment", postVariables, function (returnArray) {
                                        if ("shipped_quantities" in returnArray) {
                                            for (const i in returnArray['shipped_quantities']) {
                                                $("#shipped_quantity_" + i).val(returnArray['shipped_quantities'][i]);
                                                if (parseInt($("#quantity_" + i).val()) <= parseInt($("#shipped_quantity_" + i).val()) && !empty(shippedOrderItemStatusId)) {
                                                    $("#order_item_status_id_" + i).val(shippedOrderItemStatusId).trigger("change");
                                                }
                                            }
                                        }
                                        $("#location_id").trigger("change");
                                        $("#_create_shipment_settings_dialog").find("input[type='checkbox']").not("#show_settings").prop("checked", false);
                                        $("#_create_shipment_settings_dialog").find("input[type='text']").val("");
                                        $("#_create_shipment_settings_dialog").find("select").val("");
                                        getOrderShipments();
                                    });
                                    $("#_confirm_shipment_dialog").dialog('close');
                                },
                                'class': 'confirm-create-shipment'
                            },
                            {
                                text: "Cancel",
                                click: function () {
                                    $("#_confirm_shipment_dialog").dialog('close');
                                }
                            }
                        ]
                });
                return false;
            });
            $(document).on("click", "#delete_shipments", function () {
                if (empty($("#location_id").val())) {
                    displayErrorMessage("Select a shipping location");
                    return false;
                }
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['location_id'] = $("#location_id").val();
                $('#_confirm_delete_shipments_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Delete Shipments',
                    buttons:
                        [
                            {
                                text: "Yes",
                                click: function () {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_location_shipments", postVariables, function (returnArray) {
                                        if ("shipped_quantities" in returnArray) {
                                            for (const i in returnArray['shipped_quantities']) {
                                                $("#shipped_quantity_" + i).val(returnArray['shipped_quantities'][i]);
                                                if (parseInt($("#quantity_" + i).val()) <= parseInt($("#shipped_quantity_" + i).val()) && !empty(shippedOrderItemStatusId)) {
                                                    $("#order_item_status_id_" + i).val(shippedOrderItemStatusId).trigger("change");
                                                }
                                            }
                                        }
                                        $("#location_id").trigger("change");
                                        $("#_create_shipment_settings_dialog").find("input[type='checkbox']").not("#show_settings").prop("checked", false);
                                        $("#_create_shipment_settings_dialog").find("input[type='text']").val("");
                                        $("#_create_shipment_settings_dialog").find("select").val("");
                                        getOrderShipments();
                                    });
                                    $("#_confirm_delete_shipments_dialog").dialog('close');
                                },
                                'class': 'confirm-delete-shipments'
                            },
                            {
                                text: "Cancel",
                                click: function () {
                                    $("#_confirm_delete_shipments_dialog").dialog('close');
                                }
                            }
                        ]
                });
                return false;
            });
            $(document).on("click", ".ship-all-items", function () {
                $("#order_items").find(".ship-all").not(".hidden").trigger("click");
            });
            $(document).on("click", ".ship-all", function () {
                let orderQuantity = $(this).closest("tr").find(".shipment-quantity").data("order_quantity");
                const orderItemId = $(this).closest("tr").data("order_item_id");
                let shippedQuantity = 0;
                if (!$("#secondary_shipment").prop("checked")) {
                    shippedQuantity = $("#shipped_quantity_" + orderItemId).val();
                }
                orderQuantity -= shippedQuantity;
                $(this).closest("td").find(".shipment-quantity").val(orderQuantity).trigger("change");
            });
            $(document).on("change", ".shipment-quantity", function () {
                const quantity = $(this).val();
                let maximumQuantity = $(this).data("maximum_quantity");
                let orderQuantity = $(this).data("order_quantity");
                const orderItemId = $(this).closest("tr").data("order_item_id");
                let shippedQuantity = 0;
                if (!$("#secondary_shipment").prop("checked")) {
                    shippedQuantity = $("#shipped_quantity_" + orderItemId).val();
                }
                if ($("#secondary_shipment").prop("checked")) {
                    maximumQuantity = 999999;
                }
                orderQuantity -= shippedQuantity;
                if (isNaN(quantity) || quantity < 0 || quantity > orderQuantity) {
                    $(this).val("");
                    displayErrorMessage("Invalid quantity");
                    return;
                }
                if (quantity > maximumQuantity) {
                    displayErrorMessage("Maximum quantity is " + maximumQuantity);
                    $(this).val("");
                }
            });
            $("#location_id").change(function () {
                $(this).data("product_distributor_id", "");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_inventory&order_id=" + $("#primary_id").val() + "&location_id=" + $(this).val(), function (returnArray) {
                        $(".shipment-quantity").addClass("hidden").val("0");
                        $(".ship-all").addClass("hidden");
                        if ("product_inventories" in returnArray) {
                            for (const i in returnArray['product_inventories']) {
                                const quantity = $("#order_item_" + i).find("#quantity_" + i).val();
                                const shippedQuantity = $("#order_item_" + i).find("#shipped_quantity_" + i).val();
                                if (!("unavailable" in returnArray['product_inventories'][i])) {
                                    $("#shipment_quantity_" + i).removeClass("hidden").data("maximum_quantity", Math.min(quantity, returnArray['product_inventories'][i]['quantity'])).data("order_quantity", quantity);
                                    $("#ship_all_" + i).removeClass("hidden");
                                }
                            }
                        }
                        $("#location_id").data("product_distributor_id", returnArray['product_distributor_id']);
                        if (!empty(returnArray['product_distributor_id'])) {
                            disableButtons($("#delete_shipments"));
                        } else {
                            enableButtons($("#delete_shipments"));
                        }
                    });
                } else if ($("#secondary_shipment").prop("checked")) {
                    $(".shipment-quantity").addClass("hidden").val("0");
                    $(".ship-all").addClass("hidden");
                    $("#order_items").find(".order-item").each(function () {
                        const orderItemId = $(this).data("order_item_id");
                        const quantity = $("#order_item_" + orderItemId).find("#quantity_" + orderItemId).val();
                        const shippedQuantity = 0;
                        $("#shipment_quantity_" + orderItemId).removeClass("hidden").data("maximum_quantity", quantity).data("order_quantity", quantity);
                        $("#ship_all_" + orderItemId).removeClass("hidden");
                    });
                }
                return false;
            });
            $("#_list_button").text("Return To List");
            $("#order_status_id").change(function () {
                $("#order_status_wrapper").attr("class", "");
                $("#order_status_display").html("");
                $("#order_information_block").attr("class", "");
                if (!empty($(this).val())) {
                    const displayText = $(this).find("option:selected").text();
                    $("#order_status_wrapper").addClass("order-status-" + $(this).val());
                    $("#order_status_display").html(displayText);
                    $("#order_information_block").addClass("order-status-" + $(this).val() + "-light");
                }
                if (!empty($("#deleted").val())) {
                    $("#order_status_display").html("Deleted");
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_status&order_id=" + $("#primary_id").val() + "&order_status_id=" + $(this).val(), function (returnArray) {
                    if (!empty(returnArray['mark_completed'])) {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    }
                });
            });
            $(document).on("change", ".order-item-status-id", function () {
                $(this).closest("tr").attr("class", "").addClass("order-item");
                if (!empty($(this).val())) {
                    $(this).closest("tr").addClass("order-item-status-" + $(this).val());
                }
                const orderItemId = $(this).closest("tr").data("order_item_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_item_status&order_id=" + $("#primary_id").val() + "&order_item_id=" + orderItemId + "&order_item_status_id=" + $(this).val());
            });
            $(document).on("click", ".postage-rate", function () {
                $("#rate_shipment_id").val($(this).closest("p").find(".rate-shipment-id").val());
            });
            $(document).on("click", ".help-desk-entry", function () {
                const helpDeskEntryId = $(this).data("help_desk_entry_id");
                window.open("/help-desk-dashboard?id=" + helpDeskEntryId);
            });
		</script>
		<?php
	}

	function javascript() {
		$shippedOrderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", "SHIPPED");
		?>
		<script>
            const shippedOrderItemStatusId = "<?= $shippedOrderItemStatusId ?>";
            let shippingAddresses = [];

            function bookmarkTitle() {
                return "Order ID " + $("#primary_id").val();
            }

            function customActions(actionName) {
                if (actionName === "print_order_items") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=print_order_items", function (returnArray) {
                        if ("order_ids" in returnArray) {
                            window.open("/print-order-items?order_id=" + returnArray['order_ids'] + "&printable=true");
                        }
                    });
                }
                if (actionName === "set_status") {
                    $('#_set_status_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Status',
                        buttons: {
                            Save: function (event) {
                                if (!empty($("#order_status_id"))) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_status", $("#_set_status_form").serialize(), function (returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            getDataList();
                                        }
                                    });
                                    $("#_set_status_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_status_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "auto_processing") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=auto_processing", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                    return true;
                }
                if (actionName === "report_taxes") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=report_taxes", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                    return true;
                }
                if (actionName === "mark_selected_completed") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_selected_completed", $("#_set_status_form").serialize(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                }
                if (actionName === "mark_selected_not_completed") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_selected_not_completed", $("#_set_status_form").serialize(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                }
                if (actionName === "notify_corestore_selected") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=notify_corestore_selected", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                    return true;
                }
            }

            function getFFLDealers() {
                $("#ffl_dealers").find("li").remove();
                $("#ffl_dealers").append("<li class='align-center'>Searching</li>");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_dealers&search_text=" + encodeURIComponent($("#ffl_search_text").val()) + "&radius=25&allow_expired=true", function (returnArray) {
                    $("#ffl_dealers").find("li").remove();
                    if ("ffl_dealers" in returnArray) {
                        for (let i in returnArray['ffl_dealers']) {
                            $("#ffl_dealers").append("<li class='ffl-dealer" + (empty(returnArray['ffl_dealers'][i]['preferred']) ? "" : " preferred") + (empty(returnArray['ffl_dealers'][i]['have_license']) ? "" : " have-license") +
                                "' data-federal_firearms_licensee_id='" + returnArray['ffl_dealers'][i]['federal_firearms_licensee_id'] + "'>" + returnArray['ffl_dealers'][i]['display_name'] + "</li>");
                        }
                    }
                });
            }

            function afterGetRecord(returnArray) {
                if (empty(returnArray['contact_address']['data_value'])) {
                    $("#contact_address").addClass("hidden");
                } else {
                    $("#contact_address").removeClass("hidden");
                }
                if (returnArray['can_send_text']) {
                    $("#_send_text_message_wrapper").removeClass("hidden");
                } else {
                    $("#_send_text_message_wrapper").addClass("hidden");
                }
                if (empty(returnArray['pickup']['data_value'])) {
                    $("#_create_shipment_settings_dialog").find("input[type='checkbox']").not("#show_settings").prop("checked", false);
                    $("#_create_shipment_settings_dialog").find("input[type='text']").val("");
                    $("#_create_shipment_settings_dialog").find("select").val("");
                    $("#_pickup_ready_button").addClass("hidden");
                    $("#_picked_up_button").addClass("hidden");
                    $(".create-shipping-label").removeClass("hidden");
                } else {
                    $("#_create_shipment_settings_dialog").find("input[type='checkbox']").not("#show_settings").prop("checked", false);
                    $("#_create_shipment_settings_dialog").find("input[type='text']").val("");
                    $("#_create_shipment_settings_dialog").find("select").val("");
                    $("#_pickup_ready_button").removeClass("hidden");
                    $("#_picked_up_button").removeClass("hidden");
                    $(".create-shipping-label").addClass("hidden");
                }
                if ($("#location_id").find("option").length === 1) {
                    $("#location_id").val($("#location_id").find("option").attr("value"));
                }
                if ($("#primary_id").length > 0) {
                    document.title = "Order Number " + $("#primary_id").val();
                }
                if ($("#deleted").val() === "1") {
                    $("#_delete_button").find(".button-text").html("Undelete");
                } else {
                    $("#_delete_button").find(".button-text").html("Delete");
                }
                if (empty(returnArray['date_completed']['data_value'])) {
                    $("#order_status_id").trigger("change");
                }
                getOrderItems();
                getOrderShipments();

                $("#order_shipments").find(".order-shipment").remove();
                $("#order_shipments").find(".order-shipment-item").remove();
                for (let i in returnArray['order_shipments']) {
                    let orderShipmentBlock = $("#_order_shipment_template").html();
                    for (let j in returnArray['order_shipments'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderShipmentBlock = orderShipmentBlock.replace(re, returnArray['order_shipments'][i][j]);
                    }
                    $("#order_shipments").append(orderShipmentBlock);
                    if (returnArray['order_shipments'][i]['no_notifications'] === "1") {
                        $("#no_notifications_" + returnArray['order_shipments'][i]['order_shipment_id']).prop("checked", true);
                    }
                    if (!empty(returnArray['order_shipments'][i]['secondary_shipment'])) {
                        $("#order_shipment_" + returnArray['order_shipments'][i]['order_shipment_id']).addClass("secondary-shipment");
                    }
                    if (!empty(returnArray['order_shipments'][i]['product_distributor_id'])) {
                        $("#create_shipping_label_" + returnArray['order_shipments'][i]['order_shipment_id']).remove();
                    }
                    if ("order_shipment_items" in returnArray['order_shipments'][i]) {
                        for (let j in returnArray['order_shipments'][i]['order_shipment_items']) {
                            let orderShipmentItemBlock = $("#_order_shipment_item_template").html();
                            for (let k in returnArray['order_shipments'][i]['order_shipment_items'][j]) {
                                const re = new RegExp("%" + k + "%", 'g');
                                orderShipmentItemBlock = orderShipmentItemBlock.replace(re, returnArray['order_shipments'][i]['order_shipment_items'][j][k]);
                            }
                            $("#order_shipments").append(orderShipmentItemBlock);
                        }
                    }
                }
                if ($("#order_shipments").find(".order-shipment").length === 0) {
                    $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Shipments yet</td></tr>");
                }

                $("#order_payments").find(".order-payment").remove();
                for (let i in returnArray['order_payments']) {
                    let orderPaymentBlock = $("#_order_payment_template").html();
                    for (let j in returnArray['order_payments'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderPaymentBlock = orderPaymentBlock.replace(re, returnArray['order_payments'][i][j]);
                    }
                    let additionalClasses = "";
                    if (!empty(returnArray['order_payments'][i]['deleted'])) {
                        additionalClasses = "deleted-payment hidden";
                    }
                    orderPaymentBlock = orderPaymentBlock.replace(new RegExp("%additional_classes%", 'g'), additionalClasses);
                    $("#order_payments").append(orderPaymentBlock);
                }
                $(".order-payment-invoice-id-").remove();
                if ($("#order_payments").find(".order-payment").length === 0) {
                    $("#order_payments").append("<tr class='order-payment'><td colspan='100'>No Payments yet</td></tr>");
                }
                if (empty(returnArray['not_captured'])) {
                    enableButtons($("#create_shipment"));
                } else {
                    disableButtons($("#create_shipment"));
                }
                disableButtons($("#delete_shipments"));

                $("#order_notes").find(".order-note").remove();
                for (let i in returnArray['order_notes']) {
                    let orderNoteBlock = $("#_order_note_template").html();
                    for (let j in returnArray['order_notes'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_notes'][i][j]);
                    }
                    $("#order_notes").append(orderNoteBlock);
                }
                if ($("#order_notes").find(".order-note").length === 0) {
                    $("#order_notes").append("<tr class='order-note no-order-notes'><td colspan='100'>No Notes yet</td></tr>");
                }

                $("#touchpoints").find(".touchpoint").remove();
                for (let i in returnArray['touchpoints']) {
                    let touchpointBlock = $("#_touchpoint_template").html();
                    for (let j in returnArray['touchpoints'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        touchpointBlock = touchpointBlock.replace(re, returnArray['touchpoints'][i][j]);
                    }
                    $("#touchpoints").append(touchpointBlock);
                }
                if ($("#touchpoints").find(".touchpoint").length === 0) {
                    $("#touchpoints").append("<tr class='touchpoint no-touchpoints'><td colspan='100'>No Touchpoints</td></tr>");
                }
                $("#show_order_touchpoints").prop("checked", ($(".touchpoint.order-" + $("#primary_id").val()).length > 0)).trigger("change");

                $("#help_desk_entries").find(".help-desk-entry").remove();
                for (let i in returnArray['help_desk_entries']) {
                    let helpDeskEntryBlock = $("#_help_desk_entry_template").html();
                    for (let j in returnArray['help_desk_entries'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        helpDeskEntryBlock = helpDeskEntryBlock.replace(re, returnArray['help_desk_entries'][i][j]);
                    }
                    $("#help_desk_entries").append(helpDeskEntryBlock);
                }
                if ($("#help_desk_entries").find(".help-desk-entry").length === 0) {
                    $("#help_desk_entries").append("<tr class='help-desk-entry no-help-desk-entries'><td colspan='100'>No Help Desk Tickets</td></tr>");
                }

                $("#order_files").find(".order-file").remove();
                for (const i in returnArray['order_files']) {
                    let orderFileBlock = $("#_order_file_template").html();
                    for (const j in returnArray['order_files'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderFileBlock = orderFileBlock.replace(re, returnArray['order_files'][i][j]);
                    }
                    $("#order_files").append(orderFileBlock);
                }
                if ($("#order_files").find(".order-file").length === 0) {
                    $("#order_files").append("<tr class='order-file no-order-files'><td colspan='100'>No Files yet</td></tr>");
                }

                if (!empty(returnArray['date_completed']['data_value']) || !empty(returnArray['deleted']['data_value'])) {
                    $("#_maintenance_form").find("input[type=text]").prop("readonly", true);
                    $("#_maintenance_form").find("select").prop("disabled", true);
                    $("#_maintenance_form").find("textarea").not(".keep-visible").prop("readonly", true);
                    $("#_maintenance_form").find("button").not(".keep-visible").addClass("hidden");
                    $("#_maintenance_form").find(".delete-shipping-item").addClass("hidden");
                }
                $("#fraud_report").html("<p>Loading</p>");
                $("#fraud_score").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_fraud_report&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("fraud_report" in returnArray) {
                        $("#fraud_report").html(returnArray['fraud_report']);
                        $("#fraud_score").html(returnArray['fraud_score']);
                    }
                });
                $("#location_group_id").trigger("change");
            }

            function afterEditableListRemove(listIdentifier) {
                if (listIdentifier.indexOf("order_item_serial_numbers_") === 0) {
                    const orderItemId = listIdentifier.substring("order_item_serial_numbers_".length);
                    const deleteId = $("#_" + listIdentifier + "_delete_ids").val();
                    $("#_" + listIdentifier + "_delete_ids").val("");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_order_item_serial_number&order_item_id=" + orderItemId + "&delete_id=" + deleteId);
                }
            }

            function getOrderItems() {
                $("#order_items").find("tr.order-item").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_items&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("jquery_templates" in returnArray) {
                        $("#jquery_templates").html(returnArray['jquery_templates']);
                    }
                    if ("order_items" in returnArray) {
                        let downloadDate = false;
                        let anticipatedShipDate = false;
                        for (const i in returnArray['order_items']) {
                            let orderItemBlock = $("#_order_item_template").html();
                            for (const j in returnArray['order_items'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderItemBlock = orderItemBlock.replace(re, returnArray['order_items'][i][j]);
                            }
                            $("#order_items").append(orderItemBlock);
                            if (!empty(returnArray['order_items'][i]['serializable'])) {
                                $("#order_item_" + returnArray['order_items'][i]['order_item_id']).find(".serial-number-wrapper").removeClass("hidden");
                            }
                            $("#order_item_status_id_" + returnArray['order_items'][i]['order_item_id']).val(returnArray['order_items'][i]['order_item_status_id']);
                            if (!empty(returnArray['order_items'][i]['virtual_product']) && !empty(returnArray['order_items'][i]['file_id'])) {
                                $("#order_item_" + returnArray['order_items'][i]['order_item_id']).addClass("virtual-product");
                                $("#order_item_status_id_" + returnArray['order_items'][i]['order_item_id']).addClass("hidden");
                                $("#anticipated_ship_date_" + returnArray['order_items'][i]['order_item_id']).addClass("hidden");
                                downloadDate = true;
                            } else {
                                $("#download_date_" + returnArray['order_items'][i]['order_item_id']).addClass("hidden");
                                if (!empty(returnArray['order_items'][i]['virtual_product'])) {
                                    $("#anticipated_ship_date_" + returnArray['order_items'][i]['order_item_id']).addClass("hidden");
                                } else {
                                    anticipatedShipDate = true;
                                }
                            }
                        }
                        if (downloadDate) {
                            $("#_download_date_header").html("Download<br>Until");
                        } else {
                            $("#_download_date_header").html("");
                        }
                        if (anticipatedShipDate) {
                            $("#_anticipated_ship_date_header").html("Anticipated<br>Ship Date");
                        } else {
                            $("#_anticipated_ship_date_header").html("");
                        }
                        $("#order_items").find(".order-item-status-id").each(function () {
                            $(this).closest("tr").attr("class", "").addClass("order-item");
                            if (!empty($(this).val())) {
                                $(this).closest("tr").addClass("order-item-status-" + $(this).val());
                            }
                        });
                        if (!empty($("#deleted").val()) || !empty($("#date_completed").val())) {
                            $("#order_items").find(".order-item-status-id").prop("disabled", true);
                        }
                        $("#location_id").trigger("change");
                        if (!empty(returnArray['ffl_required'])) {
                            $("#ffl_section_wrapper").removeClass("hidden");
                            $("#ffl_required").val("1");
                            $(".ffl-required").removeClass("hidden");
                        } else {
                            $("#ffl_section_wrapper").addClass("hidden");
                            $("#ffl_required").val("");
                            $(".ffl-required").addClass("hidden");
                        }
                        for (const i in returnArray) {
                            if (i.indexOf("order_item_serial_numbers_") === 0) {
                                for (const j in returnArray[i]) {
                                    addEditableListRow(i, returnArray[i][j]);
                                }
                            }
                        }
                        if ("order_items_quantity" in returnArray) {
                            $("#order_items_quantity").html("Ordered items total quantity: " + returnArray['order_items_quantity'])
                        }
                    }
                });
            }

            function getOrderShipments() {
                $("#order_shipments").find(".order-shipment").remove();
                $("#order_shipments").find(".order-shipment-item").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_shipments&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("order_shipments" in returnArray) {
                        $("#order_shipments").find(".order-shipment").remove();
                        $("#order_shipments").find(".order-shipment-item").remove();
                        for (let i in returnArray['order_shipments']) {
                            let orderShipmentBlock = $("#_order_shipment_template").html();
                            for (let j in returnArray['order_shipments'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderShipmentBlock = orderShipmentBlock.replace(re, returnArray['order_shipments'][i][j]);
                            }
                            $("#order_shipments").append(orderShipmentBlock);
                            if (!empty(returnArray['order_shipments'][i]['no_notifications'])) {
                                $("#no_notifications_" + returnArray['order_shipments'][i]['order_shipment_id']).prop("checked", true);
                            }
                            if (!empty(returnArray['order_shipments'][i]['secondary_shipment'])) {
                                $("#order_shipment_" + returnArray['order_shipments'][i]['order_shipment_id']).addClass("secondary-shipment");
                            }
                            if (!empty(returnArray['order_shipments'][i]['product_distributor_id'])) {
                                $("#create_shipping_label_" + returnArray['order_shipments'][i]['order_shipment_id']).remove();
                            }
                            if ("order_shipment_items" in returnArray['order_shipments'][i]) {
                                for (const j in returnArray['order_shipments'][i]['order_shipment_items']) {
                                    let orderShipmentItemBlock = $("#_order_shipment_item_template").html();
                                    for (const k in returnArray['order_shipments'][i]['order_shipment_items'][j]) {
                                        const re = new RegExp("%" + k + "%", 'g');
                                        orderShipmentItemBlock = orderShipmentItemBlock.replace(re, returnArray['order_shipments'][i]['order_shipment_items'][j][k]);
                                    }
                                    $("#order_shipments").append(orderShipmentItemBlock);
                                }
                                $("#shipping_carrier_id_" + returnArray['order_shipments'][i]['order_shipment_id']).val(returnArray['order_shipments'][i]['shipping_carrier_id']);
                            }
                        }
                        if ($("#order_shipments").find(".order-shipment").length === 0) {
                            $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Shipments yet</td></tr>");
                        }
                        if ("order_shipment_items_quantity" in returnArray) {
                            $("#order_shipment_items_quantity").html("Shipment items total quantity: " + returnArray['order_shipment_items_quantity'])
                        }

                    }
                });
            }

            function afterDeleteRecord(returnArray) {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                return false;
            }

            function changesMade() {
                return false;
            }
		</script>
		<?php
	}

	function deleteRecord() {
		$returnArray = array();
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['primary_id']);
		if (!empty($orderId)) {
			updateFieldById("deleted", (!empty($_POST['deleted']) ? "0" : "1"), "orders", "order_id", $orderId, "client_id = ?", $GLOBALS['gClientId']);
			if ($_POST['deleted'] == "1") {
				$returnArray['deleted'] = "0";
				$returnArray['info_message'] = getLanguageText("Order successfully undeleted");
                $webhookReason = "mark_undeleted";
			} else {
				$returnArray['deleted'] = "1";
                $webhookReason = "mark_deleted";
			}
            Corestore::orderNotification($_POST['primary_id'], $webhookReason);
            $resultSet = executeQuery("select product_id from order_items where order_id = ?", $_POST['primary_id']);
			while ($row = getNextRow($resultSet)) {
				removeCachedData("*", $row['product_id']);
				removeCachedData("base_cost", $row['product_id']);
				removeCachedData("*", $row['product_id']);
			}
			if (empty($_POST['deleted'])) {
				$resultSet = executeQuery("select * from loyalty_program_point_log where order_id = ? and (notes is null or notes not like '%order deleted%')", $orderId);
				while ($row = getNextRow($resultSet)) {
					executeQuery("update loyalty_program_points set point_value = greatest(0,point_value - ?) where loyalty_program_point_id = ?",
						$row['point_value'], $row['loyalty_program_point_id']);
					executeQuery("update loyalty_program_point_log set notes = ? where loyalty_program_point_log_id = ?",
						date("m/d/Y") . ": points reversed because order deleted", $row['loyalty_program_point_log_id']);
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$pickup = getReadFieldFromId("pickup", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
				if (!$pickup || Order::hasPhysicalProducts($orderId)) {
					$emailAddresses = array();
					$resultSet = executeQuery("select email_address from shipping_method_notifications where shipping_method_id = ?", $orderRow['shipping_method_id']);
					while ($row = getNextRow($resultSet)) {
						$emailAddresses[] = $row['email_address'];
					}
					if (!empty($emailAddresses)) {
						$substitutions = $orderRow;
						$emailResult = sendEmail(array("subject" => "Order Deleted", "body" => "<p>Order ID %order_id% from %full_name% has been deleted.</p>", "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
					}
				}
			}
		}
		ajaxResponse($returnArray);
	}

	function orderTags() {
		$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			?>
			<h2>Order Tags</h2>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
				<div class="basic-form-line" id="_order_tag_id_<?= $row['order_tag_id'] ?>_row">
					<input class="order-tag" type="checkbox" data-order_tag_id="<?= $row['order_tag_id'] ?>" id="order_tag_id_<?= $row['order_tag_id'] ?>" name="order_tag_id_<?= $row['order_tag_id'] ?>" value="1"><label class="checkbox-label" for="order_tag_id_<?= $row['order_tag_id'] ?>"><?= htmlText($row['description']) ?></label>

				</div>
				<?php
			}
		}
	}

	function afterGetRecord(&$returnArray) {
		$locationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']);
		$locationGroupId = getFieldFromId("location_group_id", "locations", "location_id", $locationId);
		$returnArray['location_group_id'] = array("data_value" => $locationGroupId);
		if (canAccessPageCode("ORDEREDIT")) {
			$returnArray['order_id_display'] = array("data_value" => "<a href='/orderedit.php?url_page=show&primary_id=" . $returnArray['primary_id']['data_value'] . "'>" . $returnArray['primary_id']['data_value'] . "</a>");
		} else {
			$returnArray['order_id_display'] = array("data_value" => $returnArray['primary_id']['data_value']);
		}
		if ($GLOBALS['gPHPVersion'] < 70200) {
			$returnArray['can_send_text'] = false;
		} else {
			$returnArray['can_send_text'] = TextMessage::canSendTextMessage($returnArray['contact_id']['data_value']);
		}
		$resultSet = executeQuery("select * from order_tags where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$orderTagLinkId = getFieldFromId("order_tag_link_id", "order_tag_links", "order_tag_id", $row['order_tag_id'], "order_id = ?", $returnArray['primary_id']['data_value']);
			$returnArray['order_tag_id_' . $row['order_tag_id']] = array("data_value" => (empty($orderTagLinkId) ? "" : "1"), "crc_value" => getCrcValue((empty($orderTagLinkId) ? "" : "1")));
		}

		$referralContact = (empty($returnArray['referral_contact_id']['data_value']) ? "" : "Referred by: " . getDisplayName($returnArray['referral_contact_id']['data_value']));
		$returnArray['referral_contact'] = array("data_value" => $referralContact);
		$contactIdentifiers = "";
		$resultSet = executeQuery("select * from contact_identifiers join contact_identifier_types using (contact_identifier_type_id) where contact_id = ?", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$contactIdentifiers .= "<p>" . htmlText($row['description']) . ": " . $row['identifier_value'] . "</p>";
		}
		$returnArray['contact_identifiers'] = array("data_value" => $contactIdentifiers);
		if (!empty($returnArray['gift_order']['data_value'])) {
			$returnArray['gift_order'] = array("data_value" => "This order is a gift");
			$returnArray['gift_text'] = array("data_value" => "<label>Gift Instructions:</label><br>" . $returnArray['gift_text']['data_value']);
		} else {
			$returnArray['gift_order'] = array("data_value" => "");
		}

		$fullName = $returnArray['full_name']['data_value'];
		$returnArray['contact_name'] = array("data_value" => "<a target='_blank' href='/contactmaintenance.php?url_page=show&primary_id=" . $returnArray['contact_id']['data_value'] . "&clear_filter=true'>" . getDisplayName($returnArray['contact_id']['data_value']) . "</a>");
		$userRow = Contact::getUserFromContactId($returnArray['contact_id']['data_value']);
		$orderCount = 0;
		if (empty($userRow)) {
			$returnArray['user_info'] = array("data_value" => "");
		} else {
			$returnArray['user_info'] = array("data_value" => "User, " .
				(empty($userRow['user_type_id']) ? "no user type" : "User Type: " . getFieldFromId("description", "user_types", "user_type_id", $userRow['user_type_id'])));
			$countSet = executeQuery("select count(*) from orders where contact_id = ? and order_id < ?", $returnArray['contact_id']['data_value'],
				$returnArray['primary_id']['data_value']);
			$orderCount = 0;
			if ($countRow = getNextRow($countSet)) {
				$orderCount = $countRow['count(*)'];
			}
		}
		$returnArray['order_count'] = array("data_value" => ($orderCount == 0 ? "No previous orders" : $orderCount . " Previous Order" . ($orderCount == 1 ? "" : "s")));

		$returnArray['location_id'] = array("data_value" => "");
		$promotionId = getFieldFromId("promotion_id", "order_promotions", "order_id", $returnArray['primary_id']['data_value']);
		$returnArray['order_promotion'] = array("data_value" => (empty($promotionId) ? "" : "Used promotion '" . getFieldFromId("description", "promotions", "promotion_id", $promotionId) . "'"));
		$fflRow = (new FFL($returnArray['federal_firearms_licensee_id']['data_value']))->getFFLRow();

		$returnArray['selected_ffl'] = array("data_value" => FFL::getFFLBlock($fflRow));

		if (!empty($returnArray['date_completed']['data_value'])) {
			$returnArray['order_status_display'] = array("data_value" => "Completed on " . date("m/d/Y", strtotime($returnArray['date_completed']['data_value'])));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button class='keep-visible' id='reopen_order'>Reopen Order</button>");
		} else {
			$returnArray['order_status_display'] = array("data_value" => getFieldFromId("description", "order_status", "order_status_id", $returnArray['order_status_id']['data_value']));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button id='mark_completed'>Mark Order Completed</button>");
		}
		$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $returnArray['contact_id']['data_value']);
		$returnArray['email_address'] = array("data_value" => (empty($emailAddress) ? "" : "<a href='mailto:" . $emailAddress . "'>" . $emailAddress . "</a>"));

		$contactRow = Contact::getContact($returnArray['contact_id']['data_value']);
		if (empty($returnArray['address_id']['data_value'])) {
			$addressRow = $contactRow;
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $returnArray['address_id']['data_value']);
			if (empty($addressRow['address_1']) && empty($addressRow['city'])) {
				$addressRow = $contactRow;
			}
		}
		if (strlen($fullName) > 20) {
			$fullName = str_replace(", ", "<br>", $fullName);
		}

		$shippingAddressLine1 = $addressRow['address_1'];
		$shippingAddress = $fullName . "<br>" . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . "," . $addressRow['postal_code'];
		$shippingPostalCode = $addressRow['postal_code'];
		if ($addressRow['country_id'] != 1000) {
			$shippingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
		}
		$shippingAddress .= "</p><p><a target='_blank' href='https://www.google.com/maps?q=" . urlencode($addressRow['address_1'] . ", " . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . ", ") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code']) . "'>Show on map</a>";
		$returnArray['shipping_address'] = array("data_value" => $shippingAddress);

		$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value'], "pickup = 1 and location_id is not null");
		if (empty($shippingMethodRow) || !canAccessPageCode("CONTACTMAINT")) {
			$returnArray['shipping_method_display'] = array("data_value" => getFieldFromId("description", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		} else {
			$contactId = getFieldFromId("contact_id", "locations", "location_id", $shippingMethodRow['location_id']);
			$locationDescription = getFieldFromId("description", "locations", "location_id", $shippingMethodRow['location_id']);
			$returnArray['shipping_method_display'] = array("data_value" => "Pickup at <a href='/contactmaintenance.php?url_page=show&primary_id=" . $contactId . "&clear_filter=true' target='_blank'>" . $locationDescription . "</a>");
		}

		$returnArray['source_display'] = array("data_value" => getFieldFromId("description", "sources", "source_id", $returnArray['source_id']['data_value']));
		$returnArray['pickup'] = array("data_value" => getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		$returnArray['order_method_display'] = array("data_value" => getFieldFromId("description", "order_methods", "order_method_id", $returnArray['order_method_id']['data_value']));
		$returnArray['white_pages'] = array("data_value" => "<a target='_blank' href='https://www.whitepages.com/name/" . str_replace(" ", "-", $returnArray['full_name']['data_value']) . "/" . str_replace(" ", "-", $addressRow['city']) . "-" . $addressRow['state'] . "?fs=1&searchedLocation=" . urlencode($addressRow['city'] . ", " . $addressRow['state']) . "&searchedName=" . urlencode($returnArray['full_name']['data_value']) . "'>White Pages Listing</a>");
		$returnArray['reverse_white_pages'] = array("data_value" => "<a target='_blank' href='https://www.whitepages.com/address/" . str_replace(" ", "-", str_replace(",", "", $addressRow['address_1'])) . "/" . str_replace(" ", "-", $addressRow['city']) . "-" . $addressRow['state'] . "'>Reverse Address Lookup</a>");

		$billingAddressLine1 = false;
		$returnArray['billing_address'] = array("data_value" => "");
		$accountId = getFieldFromId("account_id", "order_payments", "order_id", $returnArray['primary_id']['data_value'], "account_id is not null");
		if (empty($accountId)) {
			$accountId = $returnArray['account_id']['data_value'];
		}
		if (!empty($accountId)) {
			$accountRow = getRowFromId("accounts", "account_id", $accountId);
			$billingAddressId = $accountRow['address_id'];
			if (empty($billingAddressId)) {
				$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
			} else {
				$addressRow = getRowFromId("addresses", "address_id", $billingAddressId);
			}
			$billingPostalCode = $addressRow['postal_code'];
			$distance = calculateDistance(getPointForZipCode($billingPostalCode), getPointForZipCode($shippingPostalCode));
			if ($distance > 100) {
				if ($distance < 200) {
					$class = "orange-text";
				} else {
					$class = "red-text";
				}
			}
			$billingAddressLine1 = $addressRow['address_1'];
			$billingAddress = '<div class="' . $class . '">' . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code'];
			if ($addressRow['country_id'] != 1000) {
				$billingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
			}
			$billingAddress .= ($distance > 100 ? "<br>" . number_format($distance) . " miles from shipping address" : "");
			$billingAddress .= "</div></p><p>" . getFieldFromId("description", "payment_methods", "payment_method_id", $accountRow['payment_method_id']) . " - " . $accountRow['account_number'];
			$returnArray['billing_address'] = array("data_value" => $billingAddress);
		}
		$returnArray['contact_address'] = array("data_value" => "");
		if (!empty($contactRow['address_1']) && $contactRow['address_1'] != $billingAddressLine1 && $contactRow['address_1'] != $shippingAddressLine1) {
			$contactAddress = $contactRow['address_1'] . "<br>" . (empty($contactRow['address_2']) ? "" : $contactRow['address_2'] . "<br>") . $contactRow['city'] . ", " . $contactRow['state'] . "," . $contactRow['postal_code'];
			$returnArray['contact_address'] = array("data_value" => $contactAddress);
		}
		$orderItemTotal = 0;
		$orderItemCount = 0;
		$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$orderItemTotal += $row['sale_price'] * $row['quantity'];
			$orderItemCount += $row['quantity'];
		}
		if ($orderItemCount > 0) {
			$orderTotal = $orderItemTotal - $returnArray['order_discount']['data_value'] + $returnArray['shipping_charge']['data_value'] + $returnArray['handling_charge']['data_value'] + $returnArray['tax_charge']['data_value'];
		}
		$donationAmount = getFieldFromId("amount", "donations", "donation_id", $returnArray['donation_id']['data_value']);
		if (empty($donationAmount)) {
			$donationAmount = 0;
		}
		$orderTotal += $donationAmount;
		if ($donationAmount <= 0) {
			$returnArray['donation_amount_wrapper'] = array("data_value" => "");
		} else {
			$returnArray['donation_amount_wrapper'] = array("data_value" => "<label>Donation</label> <span id='donation_amount'>" . number_format($donationAmount, 2, ".", ",") . "</span>");
		}

		$layawayFound = false;
		$giftCardFound = false;
		$invoiceDetails = "";
		$invoiceIdArray = array();
		$openInvoices = false;
		$notCaptured = false;
		$orderPayments = array();
		$paymentTotal = 0;
		$resultSet = executeQuery("select *,(select contact_id from orders where order_id = order_payments.order_id) as contact_id from order_payments where order_id = ? order by payment_time", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['not_captured']) && empty($row['deleted'])) {
				$notCaptured = true;
			}
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']));
			if ($paymentMethodTypeCode == "LAYAWAY") {
				$layawayFound = true;
			}
			if ($paymentMethodTypeCode == "GIFT_CARD") {
				$giftCardFound = true;
			}
			$invoiceId = "";
			if (!empty($row['invoice_id'])) {
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $row['invoice_id'], "inactive = 0 and contact_id = ?", $row['contact_id']);
			}
			if (empty($invoiceId)) {
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_number", $row['order_id'], "inactive = 0 and contact_id = ?", $row['contact_id']);
			}
			if (!empty($invoiceId) && $paymentMethodTypeCode == "INVOICE") {
				executeQuery("update order_payments set invoice_id = ? where order_payment_id = ?", $invoiceId, $row['order_payment_id']);
				$row['invoice_id'] = $invoiceId;
			}
			if (!empty($invoiceId)) {
				$dateCompleted = getFieldFromId("date_completed", "invoices", "invoice_id", $invoiceId);
				if (empty($dateCompleted)) {
					$openInvoices = true;
				}
				$invoiceTotal = 0;
				$invoiceSet = executeQuery("select sum(amount * unit_price) as invoice_total from invoice_details where invoice_id = ?", $invoiceId);
				if ($invoiceRow = getNextRow($invoiceSet)) {
					$invoiceTotal = $invoiceRow['invoice_total'];
				}
				if (empty($invoiceTotal)) {
					$invoiceTotal = 0;
				}
				$totalPayments = 0;
				$invoiceSet = executeQuery("select sum(amount) as total_payments from invoice_payments where invoice_id = ?", $invoiceId);
				if ($invoiceRow = getNextRow($invoiceSet)) {
					$totalPayments = $invoiceRow['total_payments'];
				}
				if (empty($totalPayments)) {
					$totalPayments = 0;
				}
				$balanceDue = $invoiceTotal - $totalPayments;
				if (!in_array($invoiceId, $invoiceIdArray)) {
					$invoiceIdArray[] = $invoiceId;
					$invoiceDetails .= "<h3>Invoice ID " . $invoiceId . ", Balance Due: $" . number_format($balanceDue, 2) . "</h3>";
					$invoiceSet = executeQuery("select *,(select description from payment_methods where payment_method_id = invoice_payments.payment_method_id) as payment_method from invoice_payments where invoice_id = ?", $invoiceId);
					if ($invoiceSet['row_count'] == 0) {
						$invoiceDetails .= "<p>No Payments</p>";
					} else {
						$invoiceDetails .= "<table class='grid-table' id='invoice_details'><tr><th>Payment Date</th><th>Payment Method</th><th>Amount</th></tr>";
						while ($invoiceRow = getNextRow($invoiceSet)) {
							$invoiceDetails .= "<tr><td>" . date("m/d/Y", strtotime($invoiceRow['payment_date'])) . "</td><td>" . $invoiceRow['payment_method'] .
								"</td><td>" . number_format($invoiceRow['amount'], 2) . "</td></tr>";
						}
						$invoiceDetails .= "</table>";
					}
				}
				if ($balanceDue >= 0) {
					$row['invoice_info'] = number_format($balanceDue, 2, ".", ",") . " due";
				} else {
					$row['invoice_info'] = "Invoice Paid";
				}
			}
			$row['payment_time'] = date("m/d/Y g:i a", strtotime($row['payment_time']));
			$row['not_captured'] = (empty($row['deleted']) ? (empty($row['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");
			$row['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
			$fullName = getFieldFromId("full_name", "accounts", "account_id", $row['account_id']);
			$row['account_number'] = (empty($row['account_id']) ? $row['reference_number'] : (empty($fullName) ? "" : $fullName . ", ") . substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -8));
			$row['total_amount'] = number_format($row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'], 2, ".", ",");
			if (empty($row['deleted'])) {
				$paymentTotal += $row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'];
			}
			$row['amount'] = number_format($row['amount'], 2, ".", ",");
			$row['shipping_charge'] = number_format($row['shipping_charge'], 2, ".", ",");
			$row['tax_charge'] = number_format($row['tax_charge'], 2, ".", ",");
			$row['handling_charge'] = number_format($row['handling_charge'], 2, ".", ",");
			$orderPayments[] = $row;
		}
		$returnArray['invoice_details'] = array("data_value" => $invoiceDetails);
		if ($openInvoices) {
			$returnArray['_invoice_message'] = array("data_value" => "This order has open invoices.");
		} else {
			$returnArray['_invoice_message'] = array("data_value" => "");
		}
		$paymentWarning = "";
		if (count($orderPayments) == 0) {
			$paymentWarning = "<p>No Payments have been made for this order</p>";
		} elseif (round($orderTotal - $paymentTotal, 2) != 0) {
			$paymentWarning = "<p>Payments do not equal the order total. Payments: " . number_format($paymentTotal, 2, ".", ",") . ", Order: " . number_format($orderTotal, 2, ".", ",") . "</p>";
		}
		if ($openInvoices) {
			$paymentWarning .= "<p>This order has open invoices.</p>";
		}
		if ($layawayFound) {
			$paymentWarning .= "<p>This order is a layaway.</p>";
		}
		if ($giftCardFound) {
			$resultSet = executeQuery("select * from gift_cards join gift_card_log using (gift_card_id) where gift_card_log.order_id = ? and " .
				"order_item_id is not null and order_item_id in (select order_item_id from order_items where order_id in (select order_id from orders where " .
				"order_time > date_sub(gift_card_log.log_time,interval 72 hour))) and gift_card_log.description = 'Usage for order'", $returnArray['primary_id']['data_value']);
			if ($resultSet['row_count'] > 0) {
				$paymentWarning .= "<p>Gift card was used which was purchased in the previous 72 hours</p>";
			}
		}
		$noFraudResult = CustomField::getCustomFieldData($returnArray['primary_id']['data_value'], "NOFRAUD_RESULT", "ORDERS");
		if (!empty($noFraudResult)) {
			$noFraudResult = json_decode($noFraudResult, true);
			if ($noFraudResult['decision'] == "review" || empty($noFraudResult['verified'])) {
				$noFraudToken = getPreference("NOFRAUD_TOKEN");
				if (!empty($noFraudToken)) {
					$noFraud = new NoFraud($noFraudToken);
					$noFraudResult = $noFraud->updateDecision($returnArray['primary_id']['data_value']);
					if (empty($noFraudResult)) {
						$returnArray['error_message'] = $noFraud->getErrorMessage();
					} else {
						$noFraudResult['verified'] = date("Y-m-d H:i:s");
						CustomField::setCustomFieldData($returnArray['primary_id']['data_value'], "NOFRAUD_RESULT", jsonEncode($noFraudResult), "ORDERS");
					}
				}
			}
			if ($noFraudResult['decision'] == 'review') {
				$paymentWarning .= "<p>This order is pending review for fraud risk</p>";
			} elseif ($noFraudResult['decision'] == 'fail') {
				$paymentWarning .= "<p>This order has a high risk of fraud</p>";
			}
		} else {
            $opticsWarningText = getPreference("OPTICS_FRAUD_WARNING_TEXT");
            if(!empty($opticsWarningText)) {
                $opticsWarningThreshold = getPreference("OPTICS_FRAUD_WARNING_THRESHOLD");
                $opticsWarningThreshold = ((is_numeric($opticsWarningThreshold) && $opticsWarningThreshold > 0) ? $opticsWarningThreshold : 1000);
                $orderItemResult = executeQuery("select sum(sale_price * quantity) as optics_total from order_items where order_id = ? and product_id in (select product_id from product_category_links where 
                    (product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from 
                    product_departments where product_department_code = 'OPTICS')) or product_category_id in (select product_category_id from product_category_group_links where 
                    product_category_group_id in (select product_category_group_id from product_category_group_departments  where product_department_id in 
                    (select product_department_id from product_departments where product_department_code = 'OPTICS'))))) group by order_id", $returnArray['primary_id']['data_value']);
                if($orderItemRow = getNextRow($orderItemResult)) {
                    if($orderItemRow['optics_total'] >= $opticsWarningThreshold) {
                        $paymentWarning .= $opticsWarningText;
                    }
                }
            }
        }
		$returnArray['payment_warning'] = array("data_value" => $paymentWarning);
		$returnArray['order_payments'] = $orderPayments;
		$returnArray['not_captured'] = $notCaptured;
		if ($notCaptured) {
			$returnArray['_capture_message'] = array("data_value" => "Shipments cannot be created until all payments are captured");
		} else {
			$returnArray['_capture_message'] = array("data_value" => "");
		}

		$touchpoints = array();
		$resultSet = executeQuery("select task_id,task_type_id,description,detailed_description,date_completed,order_id from tasks where contact_id = ? order by task_id desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['task_type'] = getFieldFromId("description", "task_types", "task_type_id", $row['task_type_id']);
			$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
			$touchpoints[] = $row;
		}
		$returnArray['touchpoints'] = $touchpoints;

		$helpDeskEntries = array();
		$resultSet = executeQuery("select help_desk_entry_id,description,time_submitted,time_closed from help_desk_entries where contact_id = ? order by time_submitted desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['date_submitted'] = date("m/d/Y", strtotime($row['time_submitted']));
			$row['date_closed'] = (empty($row['time_closed']) ? "" : date("m/d/Y", strtotime($row['time_closed'])));
			$helpDeskEntries[] = $row;
		}
		$returnArray['help_desk_entries'] = $helpDeskEntries;

		$orderNotes = array();
		$resultSet = executeQuery("select * from order_notes where order_id = ? order by time_submitted desc", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['time_submitted'] = date("m/d/Y g:ia", strtotime($row['time_submitted']));
			$row['user_id'] = getUserDisplayName($row['user_id']);
			$row['public_access'] = (empty($row['public_access']) ? "" : "YES");
			$orderNotes[] = $row;
		}
		$returnArray['order_notes'] = $orderNotes;
		if (count($orderNotes) > 0) {
			$returnArray['error_message'] = "Check notes below";
		}

		$orderFiles = array();
		$resultSet = executeQuery("select * from order_files where order_id = ?", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$orderFiles[] = $row;
		}
		$returnArray['order_files'] = $orderFiles;

		$orderProductKey = "";
		$resultSet = executeQuery("select group_concat(concat_ws('|',product_id,quantity,sale_price) order by product_id) order_product_key from order_items where order_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$orderProductKey = $row['order_product_key'];
		}

		$fraudAlert = "";
		$resultSet = executeQuery("select order_id,group_concat(concat_ws('|',product_id,quantity,sale_price) order by product_id) order_product_key from order_items where " .
			"order_id between ? and ? and order_id <> ? group by order_id having order_product_key = ?",
			$returnArray['primary_id']['data_value'] - 200, $returnArray['primary_id']['data_value'] + 200, $returnArray['primary_id']['data_value'], $orderProductKey);
		$duplicateOrderCount = $resultSet['row_count'];
		if ($duplicateOrderCount > 2) {
			$fraudAlert = "CAUTION: there " . ($duplicateOrderCount == 1 ? "is " : "are ") . $duplicateOrderCount . " exact duplicate order" . ($duplicateOrderCount == 1 ? "" : "s") . ", which could indicate fraud.";
		}
		$returnArray['fraud_alert'] = array("data_value" => $fraudAlert);
		$returnArray['ip_address_message'] = array("data_value" => $returnArray['ip_address']['data_value']);
		if (!empty($returnArray['ip_address']['data_value'])) {
			$ipAddress = $returnArray['ip_address']['data_value'];
			$ipAddressData = getIpAddressMetrics($ipAddress);

			if (!empty($ipAddressData)) {
				if (!empty($ipAddressData['latitude']) && !empty($ipAddressData['longitude'])) {
					$orderPoint = array("latitude" => $ipAddressData['latitude'], "longitude" => $ipAddressData['longitude']);
				} else {
					if (!empty($ipAddressData['postal_code'])) {
						$orderPoint = getPointForZipCode($ipAddressData['postal_code']);
					} else {
						$orderPoint = array();
					}
				}
				if (!empty($orderPoint)) {
					$shipPoint = getPointForZipCode($addressRow['postal_code']);
				}
				if (!empty($orderPoint) && !empty($shipPoint)) {
					$distance = calculateDistance($orderPoint, $shipPoint);
					if ($distance < 100) {
						$class = "green-text";
					} else {
						if ($distance < 200) {
							$class = "orange-text";
						} else {
							$class = "red-text";
						}
					}
					$returnArray['ip_address_message']['data_value'] = "<a href='http://ip-api.com/#" . $ipAddress . "' target='_blank' class='" . $class . "'>" . $ipAddress . "</a>";
				}
			}
		}
	}

	function internalCSS() {
		?>
		<style>
			<?php if (!empty($_GET['simplified'])) { ?>
            .advanced-feature {
                display: none !important;
            }

			<?php } ?>

            #_create_shipment_settings_dialog {
                padding-top: 20px;
            }

            #_create_shipment_settings_dialog ul {
                padding-bottom: 10px;
            }

            .fraud-section {
                border-top: 1px solid rgb(200, 200, 200);
                display: flex;
            }

            .fraud-section div {
                padding: 10px 20px;
                flex: 0 0 50%;
            }

            .fraud-section div:last-child {
                text-align: right;
                font-weight: 900;
            }

            .fraud-score {
                font-size: 2rem;
                font-weight: 900;
            }

            .fraud-info {
                font-weight: 300;
                color: rgb(150, 150, 150);
                font-size: .8rem;
            }

            .high-risk-fraud {
                color: rgb(244, 58, 61);
                font-weight: 900;
                text-shadow: 0 0 2px rgb(200, 200, 200);
            }

            .risky-fraud {
                color: rgb(246, 82, 83);
                font-weight: 900;
                text-shadow: 0 0 2px rgb(200, 200, 200);
            }

            .suspicious-fraud {
                color: rgb(249, 106, 106);
                font-weight: 900;
                text-shadow: 0 0 2px rgb(200, 200, 200);
            }

            .medium-risk-fraud {
                color: rgb(236, 162, 159);
                font-weight: 900;
                text-shadow: 0 0 2px rgb(200, 200, 200);
            }

            .low-risk-fraud {
                color: rgb(55, 126, 33);
                font-weight: 900;
                text-shadow: 0 0 2px rgb(200, 200, 200);
            }

            #fraud_report_content img {
                width: 300px;
            }

            #fraud_report_button_wrapper {
                margin-top: 20px;
            }

            #fraud_alert {
                color: rgb(255, 160, 25);
            }

            #create_shipment_settings {
                cursor: pointer;
            }

            .help-desk-entry {
                cursor: pointer;
            }

            .help-desk-entry:hover td {
                background-color: rgb(240, 240, 180);
            }

            #jquery_templates {
                display: none;
            }

            #_order_header_section {
                margin-top: 20px;
            }

            #_capture_message, #_invoice_message {
                color: rgb(192, 0, 0);
            }

            .fad.delete-payment {
                cursor: pointer;
                font-size: 1rem;
            }

            .fad.undelete-payment {
                display: none;
                cursor: pointer;
                font-size: 1rem;
            }

            .delete-payment:hover {
                color: rgb(200, 200, 200);
            }

            .undelete-payment:hover {
                color: rgb(200, 200, 200);
            }

            tr.order-payment.deleted-payment .fad.undelete-payment {
                display: inline-block;
            }

            tr.order-payment.deleted-payment .fad.delete-payment {
                display: none;
            }

            tr.order-payment.deleted-payment td {
                text-decoration: line-through;
                color: rgb(192, 0, 0);
            }

			<?php
			if ($_GET['url_page'] == "show") {
				?>
            #_page_number_controls {
                display: none !important;
            }

            #_form_header_buttons {
                margin-bottom: 0;
            }

            #_management_header {
                padding-bottom: 0;
            }

            #_main_content {
                margin-top: 0;
            }

            p#_error_message {
                margin-bottom: 0;
            }

			<?php
		}
		?>
            .distance--miles {
                display: none;
            }

            #payment_warning p {
                color: rgb(192, 0, 0);
                font-weight: 900;
                font-size: 1.2rem;
            }

            #order_filters {
                text-align: center;
            }

            #order_filters button:hover {
                background-color: #000;
                border-color: #000;
                color: #FFF;
            }

            #order_filters button.active {
                background-color: #00807f;
                border-color: #00807f;
                color: #FFF;
            }

            #_list_actions, #_list_search_control, #_list_header_buttons {
                margin-bottom: 0;
            }

            #_add_button {
                display: none;
            }

            #_maintenance_form h2 {
                margin-top: 16px;
            }

            .count-wrapper {
                display: flex;
            }

            .count-wrapper > div {
                flex: 1 1 auto;
                text-align: center;
            }

            .count-wrapper > div:nth-child(2) {
                border-left: 1px solid #d8d8d8;
            }

            #order_information_block {
                background-color: rgb(240, 240, 240);
                border: 1px solid rgb(180, 180, 180);
                padding: 20px;
                display: flex;
                margin-bottom: 10px;
            }

            #order_information_block > div {
                flex: 1 1 auto;
            }

            #_main_content p#contact_name {
                font-size: 1.6rem;
                font-weight: 300;
            }

            p label {
                font-size: 1rem;
                margin-right: 20px;
            }

            table.order-information {
                width: 100%;
                margin-bottom: 10px;
                border: 1px solid rgb(150, 150, 150);
            }

            table.order-information tr {
                border: 1px solid rgb(150, 150, 150);
            }

            table.order-information th {
                vertical-align: middle;
                padding: 5px;
            }

            table.order-information td {
                background-color: rgb(240, 240, 240);
                padding: 5px;
            }

            .product-description-wrapper {
                font-size: .8rem;
                line-height: 1.2;
            }

            .issue-gift-card, .resend-gift-card-email {
                margin: 10px 0;
            }

            .upc-code, .product-code {
                color: rgb(100, 100, 100);
                font-weight: 500;
                font-size: .8rem;
            }

            #order_status_id {
                min-width: 200px;
                width: 200px;
                max-width: 100%;
            }

            .order-shipment input {
                width: 150px;
                font-size: .8rem;
            }

            .order-shipment .notes {
                height: 26px;
                width: 150px;
            }

            .order-shipment .notes:focus {
                height: 100px;
            }

            .delete-shipping-item {
                cursor: pointer;
            }

            .inventory-quantities {
                white-space: nowrap;
                font-size: .7rem;
                line-height: 1.2;
                text-align: left;
            }

            .quantity-input {
                overflow: visible;
                white-space: nowrap;
            }

            .ship-all {
                margin-right: 5px;
                cursor: pointer;
            }

            .ffl-required {
                cursor: pointer;
                font-size: 1.2rem;
                color: rgb(192, 0, 0);
            }

            #ffl_section {
                background-color: rgb(240, 240, 240);
                display: flex;
                border: 1px solid rgb(180, 180, 180);
            }

            #ffl_section > div {
                padding: 20px 10px;
                flex: 1 1 33%;
            }

            #selected_ffl {
                font-size: .9rem;
                line-height: 1.6;
                margin-bottom: 20px;
            }

            #ffl_search_text {
                width: 100%;
                max-width: 350px;
            }

            #ffl_dealers_wrapper {
                max-height: 200px;
                overflow: scroll;
                max-width: 350px;
                width: 100%
            }

            #ffl_dealers li {
                padding: 5px 10px;
                cursor: pointer;
                background-color: rgb(220, 220, 220);
                border-bottom: 1px solid rgb(200, 200, 200);
                line-height: 1.2;
            }

            #ffl_dealers li:hover {
                background-color: rgb(180, 190, 200);
            }

            #ffl_dealers li.preferred {
                font-weight: 900;
            }

            #ffl_dealers li.have-license {
                background-color: rgb(180, 230, 180);
            }

            .upload-license {
                cursor: pointer;
                font-size: 1.2rem;
                margin-left: 20px;
            }

            .upload-license:hover {
                color: rgb(200, 200, 200);
            }

            #selected_ffl_wrapper {
                padding-left: 20px;
            }

            .ship-all-items {
                cursor: pointer;
            }

            #_easy_post_wrapper {
                display: flex;
            }

            #_easy_post_wrapper > div {
                margin: 0 10px;
                flex: 1 1 auto;
            }

            #_easy_post_wrapper input {
                max-width: 300px;
            }

            #_easy_post_wrapper select {
                max-width: 300px;
            }

            #_easy_post_wrapper input#weight {
                min-width: 10px;
                max-width: 120px;
                width: 120px;
            }

            #_easy_post_wrapper select#weight_unit {
                min-width: 10px;
                max-width: 100px;
                width: 100px;
            }

            #predefined_message {
                font-size: .7rem;
            }

            #order_status_wrapper {
                display: flex;
                background-color: rgb(180, 180, 180);
                padding: 20px;
                color: #FFF;
                text-shadow: 0 1px 2px #000000;
                text-transform: uppercase;
                font-size: 1.4rem;
            }

            #order_status_display {
                flex: 1 1 auto;
            }

            #order_id_wrapper {
                text-align: right;
            }

            #order_id_wrapper a {
                color: rgb(255, 255, 255);
                font-weight: normal;
                text-decoration: none;
            }

            #order_id_wrapper a:hover {
                text-decoration: underline;
            }

            #postage_rates {
                border-top: 1px solid rgb(180, 180, 180);
                padding-top: 20px;
                margin-top: 20px;
            }

            tr.secondary-shipment td {
                background-color: rgb(170, 185, 255);
            }

            .secondary-shipment label {
                color: rgb(170, 185, 255);
            }

            .create-label {
                white-space: nowrap;
            }

            .create-shipping-label {
                cursor: pointer;
                margin-right: 10px;
            }

            .print-packing-slip {
                cursor: pointer;
                margin-right: 10px;
            }

            .send-tracking-email {
                cursor: pointer;
                margin-right: 10px;
            }

            .print-1508 {
                cursor: pointer;
            }

            .track-package {
                margin-left: 10px;
                cursor: pointer;
            }

            #order_filters button {
                padding: 5px 15px;
            }

            .basic-form-line.serial-number-wrapper {
                margin-bottom: 0;
                margin-top: 10px;
            }

            #help_desk_answer_id {
                width: 500px;
            }

            .dialog-box .basic-form-line {
                white-space: nowrap;
            }

            .distributor-order-product {
                cursor: pointer;
            }

            .distributor-order-product:hover {
                color: rgb(200, 200, 200);
            }

            .no-license {
                font-weight: bold;
                color: rgb(192, 0, 0);
            }

            .distributor-order-message {
                font-weight: bold;
                color: rgb(0, 150, 0);
                margin-top: 10px;
            }

            .order-item-status-id {
                width: 200px;
            }

            #order_payments td {
                font-size: .7rem;
            }

            #_save_button {
                display: none;
            }

            #_inventory_alert {
                font-size: 25px;
                float: left;
                margin: 0 5px 5px 0;
            }

			<?php
			$resultSet = executeQuery("select * from order_status where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$rgb = hex2rgb($row['display_color']);
				$lightRgb = $rgb;
				foreach ($lightRgb as $index => $thisColor) {
					$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
				}
				?>
            .order-status-<?= $row['order_status_id'] ?> {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            .order-status-<?= $row['order_status_id'] ?>-light {
                background-color: rgb(<?= $lightRgb[0] ?>,<?= $lightRgb[1] ?>,<?= $lightRgb[2] ?>) !important;
                border: 1px solid rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

			<?php
		}
		$resultSet = executeQuery("select * from order_item_statuses where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$rgb = hex2rgb($row['display_color']);
			$lightRgb = $rgb;
			foreach ($lightRgb as $index => $thisColor) {
				$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
			}
			?>
            .order-item-status-<?= $row['order_item_status_id'] ?> td {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

			<?php
		}
		?>
		</style>
		<?php
	}

	function getListRowClasses($columnRow) {
		if (!empty($columnRow['order_status_id'])) {
			return "order-status-" . $columnRow['order_status_id'] . "-light";
		}
		return "";
	}

	function hiddenElements() {
		$valuesArray = Page::getPagePreferences();
		?>
		<div id="_fraud_report_dialog" class='dialog-box'>
			<div id="fraud_report"></div>
		</div>
		<div id="_create_shipment_settings_dialog" class='dialog-box'>
			<div class="basic-form-line">
				<input type='checkbox'<?= (empty($valuesArray['show_settings']) ? "" : " checked") ?> name='show_settings' id='show_settings' value='1'><label class='checkbox-label' for='show_settings'>Show this dialog whenever creating a shipment</label>
			</div>

			<p>A secondary shipment is a shipment that does not affect inventory and does not count toward a shipment of the item to the customer. A secondary shipment will not affect inventory, will not mark order items as shipped, and can include items already shipped. Typically, though not necessarily, a secondary shipment also does not send user notifications. Some examples of secondary
				shipments could be:</p>
			<ul>
				<li>A shipment from a distributor or supplier back to the dealer, who will then ship it out to the customer.</li>
				<li>A replacement product shipped to the customer.</li>
			</ul>

			<p>These settings get reset after clicking "Create Shipment".</p>

			<div class="basic-form-line">
				<input type='checkbox' name='dont_dropship' id='dont_dropship' value='1'><label class='checkbox-label' for='dont_dropship'>Do not dropship</label>
			</div>

			<div class="basic-form-line">
				<input type='checkbox' name='secondary_shipment' id='secondary_shipment' value='1'><label class='checkbox-label' for='secondary_shipment'>Secondary Shipment</label>
			</div>

			<div class="basic-form-line">
				<input type='checkbox' name='shipment_adult_signature_required' id='shipment_adult_signature_required' value='1'><label class='checkbox-label' for='shipment_adult_signature_required'>Adult Signature Required on Dropship (if supported by distributor)</label>
			</div>

			<div class="basic-form-line">
				<input type='checkbox' name='shipment_signature_required' id='shipment_signature_required' value='1'><label class='checkbox-label' for='shipment_signature_required'>Signature Required on Dropship (if supported by distributor)</label>
			</div>

			<div class="basic-form-line">
				<label>Shipping Method</label>
				<select id='shipment_shipping_method' name='shipment_shipping_method'>
					<option value=''>[Default Shipping Method]</option>
					<option value='premium'>Premium</option>
					<option value='twoday'>2-Day Air</option>
					<option value='nextday'>Next-Day Air</option>
				</select>
				<div class='basic-form-line-messages'><span class="help-label">Not all distributors support alternate shipping method. If the distributor does not, the default will be used.</span><span class='field-error-text'></span></div>
			</div>

		</div>

		<div id="_set_status_dialog" class="dialog-box">
			<p>Set status for selected orders. This cannot be undone, so be sure. Because these are being done in bulk, notifications will not be sent out for these status changes.</p>
			<form id="_set_status_form">
				<div class="basic-form-line" id="_order_status_id_row">
					<label for="order_status_id">Order Status</label>
					<select id="order_status_id" name="order_status_id">
						<option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['order_status_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>
			</form>
		</div>

		<div id="_payment_dialog" class="dialog-box">
			<form id="_payment_form">
				<p class="error-message"></p>

				<p>Creating a payment here will NOT issue a refund or charge the payment method. That should be done manually. This is simply a record of the payment or refund.</p>

				<div class="basic-form-line" id="_payment_time_row">
					<label class="">Payment Date</label>
					<input tabindex="10" type="text" class="validate[custom[date]] datepicker" size="12" id="payment_time" name="payment_time">
					<div class='basic-form-line-messages'><span class="help-label">Leave blank to use current date/time</span><span class='field-error-text'></span></div>

				</div>

				<div class="basic-form-line" id="_payment_method_id_row">
					<label class="required-label">Payment Method</label>
					<select tabindex="10" class="validate[required]" id="payment_method_id" name="payment_method_id">
						<option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from payment_methods where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['payment_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>

				<div class="basic-form-line" id="_amount_row">
					<label class="required-label">Amount</label>
					<input tabindex="10" type="text" class="validate[required,custom[number]]" data-decimal-places="2" size="12" id="amount" name="amount">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>

				<div class="basic-form-line" id="_authorization_code_row">
					<label>CC Authorization Code</label>
					<input tabindex="10" type="text" size="12" id="authorization_code" name="authorization_code">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>

				<div class="basic-form-line" id="_transaction_identifier_row">
					<label>CC Transaction ID</label>
					<input tabindex="10" type="text" size="20" id="transaction_identifier" name="transaction_identifier">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>
			</form>
		</div>

		<div id="_easy_post_dialog" class="dialog-box">
			<form id="_easy_post_form">
				<p class="error-message"></p>
				<div id="_easy_post_wrapper">
					<div id="_easy_post_from_address">
						<h3>From Address</h3>
						<div class="basic-form-line">
							<label class="required-label">Ship From</label>
							<select tabindex="10" id="ship_from" name="ship_from">
								<option value="">[Select]</option>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>
						<?= createFormControl("orders", "full_name", array("column_name" => "from_full_name", "initial_value" => $GLOBALS['gClientName'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "address_1", array("column_name" => "from_address_1", "initial_value" => $GLOBALS['gClientRow']['address_1'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "address_2", array("column_name" => "from_address_2", "initial_value" => $GLOBALS['gClientRow']['address_2'], "not_null" => false)) ?>
						<?= createFormControl("contacts", "city", array("column_name" => "from_city", "initial_value" => $GLOBALS['gClientRow']['city'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "state", array("column_name" => "from_state", "initial_value" => $GLOBALS['gClientRow']['state'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "postal_code", array("column_name" => "from_postal_code", "initial_value" => $GLOBALS['gClientRow']['postal_code'], "not_null" => true)) ?>
						<?= createFormControl("contacts", "country_id", array("column_name" => "from_country_id", "initial_value" => $GLOBALS['gClientRow']['country_id'], "not_null" => true)) ?>
						<?= createFormControl("phone_numbers", "phone_number", array("column_name" => "from_phone_number", "not_null" => true, "data-conditional-required" => "$(\"#signature_required\").prop(\"checked\") || $(\"#adult_signature_required\").prop(\"checked\")")) ?>
					</div>
					<div id="_easy_post_to_address">
						<h3>To Address</h3>

						<div class="basic-form-line">
							<label class="required-label">Ship To</label>
							<select tabindex="10" id="ship_to" name="ship_to">
								<option value="">[Select]</option>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>
						<?= createFormControl("orders", "full_name", array("column_name" => "to_full_name", "not_null" => true)) ?>
						<?= createFormControl("orders", "attention_line", array("column_name" => "to_attention_line", "not_null" => false)) ?>
						<?= createFormControl("contacts", "address_1", array("column_name" => "to_address_1", "not_null" => true)) ?>
						<?= createFormControl("contacts", "address_2", array("column_name" => "to_address_2", "not_null" => false)) ?>
						<?= createFormControl("contacts", "city", array("column_name" => "to_city", "not_null" => true)) ?>
						<?= createFormControl("contacts", "state", array("column_name" => "to_state", "not_null" => true)) ?>
						<?= createFormControl("contacts", "postal_code", array("column_name" => "to_postal_code", "not_null" => true)) ?>
						<?= createFormControl("contacts", "country_id", array("column_name" => "to_country_id", "not_null" => true)) ?>
						<?= createFormControl("phone_numbers", "phone_number", array("column_name" => "to_phone_number", "not_null" => true, "data-conditional-required" => "$(\"#signature_required\").prop(\"checked\") || $(\"#adult_signature_required\").prop(\"checked\")")) ?>

						<div class="basic-form-line">
							<input tabindex="10" type="checkbox" id="residential_address" name="residential_address" value="1"><label class="checkbox-label" for="residential_address">Residential Address</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

					</div>
					<div id="_easy_post_parameters">
						<h3>Contents Details</h3>
						<p id='shipment_details'></p>
						<h4>Package Dimensions</h4>
						<div class="basic-form-line">
							<input tabindex="10" type="checkbox" value="1" id="letter_package" name="letter_package"><label class="checkbox-label" for="letter_package">Ship as a letter</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>
						<div class="basic-form-line letter-package">
							<label class="" for="_recently_used_dimensions">Recently used dimensions</label>
							<select id="_recently_used_dimensions">
								<option selected>[None]</option>
								<?php
								$dimensionArray = EasyPostIntegration::getRecentlyUsedDimensions();
								if (!empty($dimensionArray)) {
									foreach ($dimensionArray as $thisDimension) {
										echo "<option>" . $thisDimension . "</option>";
									}
								}
								?>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line">
							<label class="">Label Date</label>
							<input tabindex="10" type="text" class="validate[custom[date],future]" size="10" id="label_date" name="label_date">
							<div class='basic-form-line-messages'><span class="help-label">Leave blank for today</span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line letter-package">
							<label class="required-label">Height</label>
							<input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="2" size="10" id="height" name="height">
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line letter-package">
							<label class="required-label">Width</label>
							<input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="2" size="10" id="width" name="width">
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line letter-package">
							<label class="required-label">Length</label>
							<input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="2" size="10" id="length" name="length">
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line">
							<label class="required-label">Weight (lbs or oz)</label>
							<input tabindex="10" type="text" class="validate[required,custom[number],min[.01]]" data-decimal-places="4" size="10" id="weight" name="weight">
							<select id="weight_unit" name="weight_unit">
								<?php
								$pagePreferences = Page::getPagePreferences();
								if ($pagePreferences['weight_unit'] != "ounce") {
									$pagePreferences['weight_unit'] = "pound";
								}
								?>
								<option value="pound" <?= ($pagePreferences['weight_unit'] == "pound" ? " selected" : "") ?>>Lbs</option>
								<option value="ounce" <?= ($pagePreferences['weight_unit'] == "ounce" ? " selected" : "") ?>>Oz</option>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line letter-package">
							<input tabindex="10" type="checkbox" id="signature_required" name="signature_required" value="SIGNATURE"><label class="checkbox-label" for="signature_required">Signature is required</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line letter-package">
							<input tabindex="10" type="checkbox" id="adult_signature_required" name="adult_signature_required" value="ADULT_SIGNATURE"><label class="checkbox-label" for="adult_signature_required">Adult Signature is required</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<?php $hazmatOptions = getPreference("EASY_POST_HAZMAT_OPTIONS");
						if (!empty($hazmatOptions)) {
							?>
							<div class="basic-form-line letter-package">

								<label class="" for="hazmat_indicator">Hazardous Materials indicator</label>
								<select id="hazmat_indicator" name="hazmat_indicator">
									<option value="">[None]</option>
									<?php
									foreach ($this->iHazmatOptions as $hazmatCode => $description) {
										?>
										<option value="<?= $hazmatCode ?>"><?= $description ?></option>
										<?php
									}
									?>
								</select>
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

							</div>
						<?php } ?>

						<div class="basic-form-line letter-package">
							<input tabindex="10" type="checkbox" id="no_email" name="no_email" value="1"><label class="checkbox-label" for="no_email">Don't send tracking email</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<?php
						$pagePreferences = Page::getPagePreferences();
						?>

						<div class="basic-form-line letter-package">
							<input tabindex="10" type="checkbox" id="include_media" name="include_media"<?= (empty($pagePreferences['include_media']) ? "" : " checked") ?> value="1"><label class="checkbox-label" for="include_media">Include Media Mail (books & videos)</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line letter-package">
							<select id="predefined_package" name="predefined_package">
								<option value="">[None]</option>
								<?php
								foreach ($this->iPredefinedPackages as $packageCode => $description) {
									?>
									<option <?= ($pagePreferences['predefined_package'] == $packageCode ? "selected" : "") ?> value="<?= $packageCode ?>"><?= $description ?></option>
									<?php
								}
								?>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>
						<?php
						$predefinedWarning = "";
						$predefinedPackages = explode(",", getPreference("ALWAYS_SHOW_PREDEFINED"));
						if (count($predefinedPackages) > 2) {
							$predefinedWarning = "NOTE: More than 2 predefined packages are configured to be quoted.  This will result in overage charges from EasyPost.";
						}
						?>

						<p id="predefined_message">Including predefined package sizes will cause fetching rates to take a bit longer.<br>
							EasyPost charges for excessive rate quotes. Each predefined package is an extra quote.<br><span class="red-text"><?= $predefinedWarning ?></span></p>

						<div class="basic-form-line">
							<label>Insurance Amount</label>
							<input tabindex="10" type="text" class="validate[custom[number],min[.01]]" data-decimal-places="2" size="10" id="insurance_amount" name="insurance_amount">
							<div class='basic-form-line-messages'><span class="help-label"><?= getPreference("EASY_POST_INSURANCE_PERCENT") ?: 1 ?>% charge applies. Leave blank to use carrier default</span><span class='field-error-text'></span></div>

						</div>

						<div class="basic-form-line">
							<input tabindex="10" type="checkbox" id="use_carrier_insurance" name="use_carrier_insurance" value="1"><label class="checkbox-label" for="use_carrier_insurance">Use Carrier Insurance instead of EasyPost Insurance</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

						</div>


					</div>
				</div>
				<div id="customs_info" class="hidden">
					<input id="_customs_required" type="hidden" value="0">
					<H3>Customs Declaration Required</H3>

					<div class="basic-form-line" id="_customs_items_row">
						<label>Customs Items (Look up HS Tariff Number at <a href="https://hts.usitc.gov/">hts.usitc.gov</a>)</label>
						<?php
						$customsItems = EasyPostIntegration::getCustomsItemsControl($this);
						echo $customsItems->getControl()
						?>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line" id="_customs_contents_type_row">
						<label class="required-label" for="customs_contents_type">Contents Type</label>
						<select tabindex="10" id="customs_contents_type" name="customs_contents_type">
							<option selected value="merchandise">Merchandise</option>
							<option value="returned_goods">Returned Goods</option>
							<option value="documents">Documents</option>
							<option value="gift">Gift</option>
							<option value="sample">Sample</option>
							<option value="other">Other</option>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line hidden" id="_customs_contents_explanation_row">
						<label class="required-label" for="customs_contents_explanation">Explanation of "Other" Contents Type</label>
						<input tabindex="10" id="customs_contents_explanation" name="customs_contents_explanation">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line" id="_customs_restriction_type_row">
						<label class="required-label" for="customs_restriction_type">Restriction Type</label>
						<select tabindex="10" id="customs_restriction_type" name="customs_restriction_type">
							<option selected value="none">None</option>
							<option value="other">Other</option>
							<option value="quarantine">Quarantine</option>
							<option value="sanitary_phytosanitary_inspection">Sanitary/Phytosanitary Inspection</option>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line hidden" id="_customs_restriction_comments_row">
						<label class="required-label" for="customs_restriction_comments">Explanation of "Other" Restriction Type</label>
						<input tabindex="10" id="customs_restriction_comments" name="customs_restriction_comments">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line" id="_customs_eel_pfc_row">
						<label class="required-label" for="customs_eel_pfc">Exemption and Exclusion Legend (EEL)</label>
						<select tabindex="10" id="customs_eel_pfc" name="customs_eel_pfc">
							<option selected value="NO EEI 30.2 (d) (2)">NO EEI 30.2 (d) (2) - shipments to US territories</option>
							<option value="NO EEI 30.36">NO EEI 30.36 - shipments to Canada</option>
							<option value="NO EEI 30.37 (a)">NO EEI 30.37 (a) - Shipments under $2500</option>
							<option value="other">Non-exempt - enter AES ITN below</option>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line hidden" id="_customs_eel_pfc_other_row">
						<label class="required-label" for="customs_eel_pfc_other">Automated Export System Internal Transaction Number (AES ITN)</label>
						<input tabindex="10" id="customs_eel_pfc_other" name="customs_eel_pfc_other">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<p>I certify the particulars given in this customs
						declaration are correct. This form does not
						contain any undeclared dangerous articles, or
						articles prohibited by Legislation or by postal
						or customs regulations. I have met all
						applicable export filing requirements under
						federal law and regulations.</p>
					<div class="basic-form-line" id="_customs_certify_row">
						<input type="checkbox" tabindex="10" id="customs_certify" class="validate[required]" data-conditional-required="$('#_customs_required').val() == 1" name="customs_certify">
						<label class="required-label checkbox-label" for="customs_certify">I agree with the above statement</label>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

					<div class="basic-form-line" id="_customs_signer_row">
						<label class="required-label" for="customs_signer">Name of the person certifying the customs form.</label>
						<input tabindex="10" id="customs_signer" class="validate[required]" data-conditional-required="$('#_customs_required').val() == 1" name="customs_signer">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>

				</div>

				<div id="postage_rates">
				</div>
			</form>
		</div>

		<div id="_addon_form_dialog" class='dialog-box'>
			<div id="_addon_form_content"></div>
		</div>

		<div id="_confirm_shipment_dialog" class="dialog-box">
			This will result in an order being placed with the distributor. Are you sure?
		</div> <!-- confirm_shipment_dialog -->

		<div id="_confirm_delete_shipments_dialog" class="dialog-box">
			All shipments from this location will be deleted. Are you sure?
		</div> <!-- _confirm_delete_shipments_dialog -->

		<div id="_confirm_email_dialog" class="dialog-box">
			Are you sure you want to send/resend this tracking email?
		</div> <!-- confirm_shipment_dialog -->

		<div id="_confirm_receipt_email_dialog" class="dialog-box">
			Are you sure you want to resend this receipt?
		</div> <!-- confirm_shipment_dialog -->

		<div id="_confirm_delete_item_dialog" class="dialog-box">
			This will NOT remove the item from an order to a distributor. You will need to contact the distributor directly to do that. This cannot be undone. Are you sure?
		</div> <!-- confirm_shipment_dialog -->

		<div id="_send_email_dialog" class="dialog-box">
			<form id="_send_email_form">

				<p class="error-message"></p>
				<?php
				$resultSet = executeQuery("select * from email_credentials where client_id = ? order by description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 1) {
					?>
					<div class="basic-form-line">
						<label for="email_credential_id">Email Credentials</label>
						<select id="email_credential_id" name='email_credential_id'>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
								<option value="<?= $row['email_credential_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>
					<?php
				}
				?>
				<?php
				$resultSet = executeQuery("select * from help_desk_answers where help_desk_type_id is null and client_id = ?" . ($GLOBALS['gUserRow']['superuser_flag'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . " order by description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
					<div class="basic-form-line">
						<label for="help_desk_answer_id">Standard Answers</label>
						<select id="help_desk_answer_id" data-url_action="get_answer" class="add-new-option"
						        data-link_url="help-desk-answer-maintenance?url_page=new" data-control_code="help_desk_answers">
							<option value="">[Select]</option>
							<?php if (empty(getPreference("NO_ADD_NEW_OPTION"))) { ?>
								<option value="-9999">[Add New]</option>
							<?php } ?>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
								<option value="<?= $row['help_desk_answer_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>
					<?php
				}
				?>
				<div class="basic-form-line" id="_email_subject_row">
					<label for="email_subject" class="required-label">Subject</label>
					<input type="text" class="validate[required]" maxlength="255" id="email_subject" name="email_subject">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>

				<div class="basic-form-line" id="_email_body_row">
					<label for="email_body" class="required-label">Content</label>
					<textarea class="validate[required] ck-editor" id="email_body" name="email_body"></textarea>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>

			</form>
		</div> <!-- send_email_dialog -->

		<div id="_send_text_message_dialog" class="dialog-box">
			<form id="_send_text_message_form">

				<p class="error-message"></p>
				<?php
				$resultSet = executeQuery("select * from help_desk_answers where help_desk_type_id is null and client_id = ?" . ($GLOBALS['gUserRow']['superuser_flag'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . " order by description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
					<div class="basic-form-line">
						<label for="text_help_desk_answer_id">Standard Answers</label>
						<select id="text_help_desk_answer_id" data-url_action="get_text_answer" class="add-new-option"
						        data-link_url="help-desk-answer-maintenance?url_page=new" data-control_code="help_desk_answers">
							<option value="">[Select]</option>
							<?php if (empty(getPreference("NO_ADD_NEW_OPTION"))) { ?>
								<option value="-9999">[Add New]</option>
							<?php } ?>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
								<option value="<?= $row['help_desk_answer_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

					</div>
					<?php
				}
				?>
				<div class="basic-form-line" id="_text_message_row">
					<label for="text_message" class="required-label">Content</label>
					<textarea class="validate[required]" id="text_message" name="text_message"></textarea>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

				</div>

			</form>
		</div> <!-- send_text_message_dialog -->
		<?php
	}

	function jqueryTemplates() {
		?>
		<table>
			<tbody id="_order_item_template">
			<tr class="order-item" id="order_item_%order_item_id%" data-order_item_id="%order_item_id%">
				<td class='product-description-wrapper'>
					<?php
					$pageLink = false;
					if ($_GET['simplified']) {
						if (canAccessPageCode("PRODUCTMAINT_LITE")) {
							$pageLink = "product-maintenance";
						}
					} elseif (canAccessPageCode("PRODUCTMAINT")) {
						$pageLink = "productmaintenance.php";
					}
					?>
					<?php if (empty($pageLink)) { ?>
						<span class="product-description">%product_description%</span> %additional_product_description%
					<?php } else { ?>
						<a href="/<?= $pageLink ?>?url_page=show&primary_id=%product_id%&clear_filter=true" target="_blank"><span class="product-description">%product_description%</span></a>%additional_product_description%
					<?php } ?>
					<div class="basic-form-line hidden serial-number-wrapper custom-control-form-line custom-control-no-help">
						<?php
						$serialNumberControl = new DataColumn("order_item_serial_numbers_%order_item_id%");
						$serialNumberControl->setControlValue("data_type", "custom_control");
						$serialNumberControl->setControlValue("control_class", "EditableList");
						$serialNumberControl->setControlValue("primary_table", "order_items");
						$serialNumberControl->setControlValue("list_table", "order_item_serial_numbers");
						echo $serialNumberControl->getControl($this);
						?>

					</div>
					<div class='distributor-order-message'>%distributor_order_message%</div>
				</td>
				<td class='order-item-status advanced-feature'><select class="order-item-status-id" id="order_item_status_id_%order_item_id%" name="order_item_status_id_%order_item_id%">
						<option value="">[None]</option>
						<option value="-1">[DELETED]</option>
						<?php
						$resultSet = executeQuery("select * from order_item_statuses where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['order_item_status_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select></td>
				<td class="align-center advanced-feature"><input type="text" size="10" class="anticipated-ship-date validate[custom[date]]" id="anticipated_ship_date_%order_item_id%" name="anticipated_ship_date_%order_item_id%" value="%anticipated_ship_date%"></td>
				<td class="align-center"><input type="text" size="10" class="download-date validate[custom[date]]" id="download_date_%order_item_id%" name="download_date_%order_item_id%" value="%download_date%"></td>
				<td class="inventory-quantities">%product_inventories%</td>
				<td class="align-center quantity-input"><span id="ship_all_%order_item_id%" class="ship-all hidden fas fa-check"></span><input type="text" size="4" class="shipment-quantity hidden" id="shipment_quantity_%order_item_id%"
				                                                                                                                               name="shipment_quantity_%order_item_id%"></td>
				<td class="align-right quantity"><input type="hidden" id="shipped_quantity_%order_item_id%" value="%shipped_quantity%"><input type="hidden" id="quantity_%order_item_id%" value="%quantity%">%quantity%</td>
				<td class="align-right sale-price">%sale_price%</td>
				<td class="align-right total-price">%total_price%</td>
				<td class="align-center distributor-order-product advanced-feature" data-quantity="%quantity%" data-product_id="%product_id%" title="Add to Distributor Order"><span class='fas fa-plus-square'></span></td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_order_shipment_template">
			<tr class="order-shipment" id="order_shipment_%order_shipment_id%" data-order_shipment_id="%order_shipment_id%">
				<td class='date-shipped'><input type="hidden" class="label-url" value="%label_url%">%date_shipped%</td>
				<td class='location'>%location%</td>
				<td class='order-number'>%order_number%</td>
				<td class='full-name'>%full_name%</td>
				<td class='create-label'>
					<?php if (!empty($this->iEasyPostActive)) { ?>
						<span title="Create Shipping Label" id="create_shipping_label_%order_shipment_id%" class='far fa-truck create-shipping-label'></span>
					<?php } ?>
					<span title="Print Packing Slip" class="print-packing-slip far fa-print"></span>
					<span title="Send Tracking Email" class="send-tracking-email far fa-envelope"></span>
					<span title="Print 1508" class="print-1508 fab fa-wpforms"></span>
				</td>
				<td class='align-right shipping-charge'>%shipping_charge%</td>
				<td class='align-center'><input class="no-notification" type='checkbox' id="no_notifications_%order_shipment_id%" value="1"></td>
				<td><input data-field_name="tracking_identifier" class='editable-shipping-field tracking-identifier' type="text" id="tracking_identifier_%order_shipment_id%" value="%tracking_identifier%"> <span title="Track Package" class="track-package far fa-map-marked-alt" data-link_url="%link_url%"></span>
				</td>
				<td><select data-field_name="shipping_carrier_id" class='editable-shipping-field shipping-carrier-id' id="shipping_carrier_id_%order_shipment_id%">
						<option value="">[Other]</option>
						<?php
						$resultSet = executeQuery("select * from shipping_carriers where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option value="<?= $row['shipping_carrier_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
				</td>
				<td><input data-field_name="carrier_description" class='editable-shipping-field carrier-description' type="text" id="carrier_description_%order_shipment_id%" value="%carrier_description%"></td>
				<td colspan="2"><textarea data-field_name="notes" class='editable-shipping-field notes' id="notes_%order_shipment_id%">%notes%</textarea></td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_order_shipment_item_template">
			<tr class="order-shipment-item" id="order_shipment_item_%order_shipment_item_id%" data-order_shipment_id="%order_shipment_id%" data-order_shipment_item_id="%order_shipment_item_id%">
				<td></td>
				<td colspan="8">%product_description%</td>
				<td class='align-right'>%quantity%</td>
				<td class="align-center"><span class="delete-shipping-item fad fa-trash-alt"></span></td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_order_payment_template">
			<tr class="order-payment %additional_classes%" id="order_payment_%order_payment_id%" data-order_payment_id="%order_payment_id%">
				<td class='payment_time'>%payment_time%</td>
				<td class='payment-method'>%payment_method%</td>
				<td class='account-number'>%account_number%</td>
				<td class='not-captured'>%not_captured%</td>
				<td class='transaction-identifier'>%transaction_identifier%</td>
				<td class='align-right amount'>%amount%</td>
				<td class='align-right shipping-charge'>%shipping_charge%</td>
				<td class='align-right tax-charge'>%tax_charge%</td>
				<td class='align-right handling-charge'>%handling_charge%</td>
				<td class='align-right total-amount'>%total_amount%</td>
				<td><?php if (canAccessPageCode("INVOICEMAINT")) { ?><a class='order-payment-invoice-id-%invoice_id%' target='_blank' href='/invoicemaintenance.php?url_page=show&primary_id=%invoice_id%'>%invoice_info%</a> <?php } ?></td>
				<td><span class="fad fa-trash-alt delete-payment" title="Delete Payment"></span><span class="fad fa-trash-undo undelete-payment" title="Undelete Payment"></span></td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_order_note_template">
			<tr class="order-note" id="order_note_%order_note_id%" data-order_note_id="%order_note_id%">
				<td class='time-submitted'>%time_submitted%</td>
				<td class='user-id'>%user_id%</td>
				<td class='public-access'>%public_access%</td>
				<td class='content'>%content%</td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_touchpoint_template">
			<tr class="touchpoint order-%order_id%" id="touchpoint_%task_id%">
				<td>%date_completed%</td>
				<td>%task_type%</td>
				<td>%description%</td>
				<td>%detailed_description%</td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_help_desk_entry_template">
			<tr class="help-desk-entry" id="help_desk_entry_%help_desk_entry_id%" data-help_desk_entry_id="%help_desk_entry_id%">
				<td>%help_desk_entry_id%</td>
				<td>%date_submitted%</td>
				<td>%description%</td>
				<td>%date_closed%</td>
			</tr>
			</tbody>
		</table>

		<table>
			<tbody id="_order_file_template">
			<tr class="order-file" id="order_file_%order_file_id%" data-order_file_id="%order_file_id%">
				<td class='order-file-download'><a href="/download.php?id=%file_id%">%description%</a></td>
			</tr>
			</tbody>
		</table>
		<?php
		$customsItems = EasyPostIntegration::getCustomsItemsControl($this);
		echo $customsItems->getTemplate();
	}
}

$pageObject = new OrderDashboardPage("orders");
$pageObject->displayPage();
