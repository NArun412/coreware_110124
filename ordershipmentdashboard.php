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

$GLOBALS['gPageCode'] = "ORDERSHIPMENTDASHBOARD";
require_once "shared/startup.inc";
require_once "classes/easypost/lib/easypost.php";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class ThisPage extends Page {

	var $iEasyPostActive = false;
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
		$this->iEasyPostActive = getPreference($GLOBALS['gDevelopmentServer'] ? "EASY_POST_TEST_API_KEY" : "EASY_POST_API_KEY");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("order_identifier", "order_time", "order_name", "shipment_date", "shipping_carrier_id", "tracking_identifier", "federal_firearms_licensee_id"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("order_status_id"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));

			$filters = array();
			$filters['hide_shipped'] = array("form_label" => "Hide where tracking ID has value", "where" => "tracking_identifier is null", "data_type" => "tinyint", "set_default" => true, "conjunction" => "and");
			$filters['hide_deleted'] = array("form_label" => "Hide deleted orders", "where" => "deleted = 0", "data_type" => "tinyint", "set_default" => true, "conjunction" => "and");
			$filters['hide_completed'] = array("form_label" => "Hide completed orders", "where" => "date_completed is null", "data_type" => "tinyint", "set_default" => true, "conjunction" => "and");

			$orderTags = array();
			$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$orderTags[$row['order_tag_id']] = $row['description'];
			}
			$filters['order_tags'] = array("form_label" => "Order Tag", "where" => "orders.order_id in (select order_id from order_tag_links where order_tag_id = %key_value%)", "data_type" => "select", "choices" => $orderTags, "conjunction" => "and");
			$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['order_status_id_' . $row['order_status_id']] = array("form_label" => $row['description'], "where" => "order_status_id = " . $row['order_status_id'], "data_type" => "tinyint");
			}
			$filters['no_order_status'] = array("form_label" => "No Status Set", "where" => "order_status_id is null", "data_type" => "tinyint");

			$shippingMethods = array();
			$resultSet = executeQuery("select * from shipping_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$shippingMethods[$row['shipping_method_id']] = $row['description'];
			}
			$filters['shipping_method'] = array("form_label" => "Shipping Method",
				"where" => "shipping_method_id = %key_value%", "data_type" => "select", "choices" => $shippingMethods, "conjunction" => "and");

			$resultSet = executeQuery("select * from product_departments where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['department_' . $row['product_department_id']] = array("form_label" => "Contains products from '" . $row['description'] . "'",
					"where" => "orders.order_id in (select order_id from order_items where product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where " .
						"product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = " . $row['product_department_id'] . "))))", "data_type" => "tinyint");
			}

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			if ($this->iEasyPostActive) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("shipment_label" => array("label" => getLanguageText("Create Shipment Label"),
					"disabled" => false), "print_packing_slip" => array("label" => getLanguageText("Print Packing Slip"),
					"disabled" => false), "print_1508" => array("label" => getLanguageText("Print 1508"),
					"disabled" => false)));
			} else {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("print_packing_slip" => array("label" => getLanguageText("Print Packing Slip"),
					"disabled" => false), "print_1508" => array("label" => getLanguageText("Print 1508"),
					"disabled" => false)));
			}
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("print_picklists", "Print Picklist for Selected Orders");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_status", "Set Status of Selected Orders");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("tag_orders", "Tag Selected Orders");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clear_tag_orders", "Remove Tag from Selected Orders");
		}
	}

	function sortRates($a, $b) {
		if ($a['rate'] == $b['rate']) {
			return 0;
		}
		return ($a['rate'] > $b['rate']) ? 1 : -1;
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("orders", "order_id", "order_id");
		$this->iDataSource->setDontUpdateJoinTable(true);

		$this->iDataSource->addColumnControl("address_id", "data_type", "hidden");

		$this->iDataSource->addColumnControl("order_id_display", "form_label", "Order ID");
		$this->iDataSource->addColumnControl("order_id_display", "data_type", "varchar");
		$this->iDataSource->addColumnControl("order_id_display", "inline-width", "120px");
		$this->iDataSource->addColumnControl("order_id_display", "classes", "align-right");
		$this->iDataSource->addColumnControl("order_id_display", "readonly", true);

		$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "form_label", "FFL #");
		$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "data_type", "varchar");
		$this->iDataSource->addColumnControl("federal_firearms_licensee_id", "select_value", "select license_lookup from federal_firearms_licensees where federal_firearms_licensee_id = orders.federal_firearms_licensee_id");

		$this->iDataSource->addColumnLikeColumn("content", "order_notes", "content");
		$this->iDataSource->addColumnLikeColumn("public_access", "order_notes", "public_access");

		$this->iDataSource->addColumnControl("order_status", "data_type", "varchar");
		$this->iDataSource->addColumnControl("order_status", "form_label", "Order Status");
		$this->iDataSource->addColumnControl("order_status", "select_value", "select description from order_status where order_status_id = orders.order_status_id");

		$this->iDataSource->addColumnLikeColumn("order_status_id", "orders", "order_status_id");
		$this->iDataSource->addColumnLikeColumn("date_completed", "orders", "date_completed");

		$this->iDataSource->addColumnControl("content", "not_null", false);
		$this->iDataSource->addColumnControl("add_note", "data_type", "button");
		$this->iDataSource->addColumnControl("add_note", "button_label", "Add Note");

		$this->iDataSource->addColumnControl("order_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("order_name", "readonly", true);
		$this->iDataSource->addColumnControl("order_name", "form_label", "Order Name");
		$this->iDataSource->addColumnControl("order_name", "select_value", "coalesce(order_shipments.full_name,orders.full_name)");

		$this->iDataSource->addColumnControl("order_identifier", "data_type", "int");
		$this->iDataSource->addColumnControl("order_identifier", "form_label", "Order ID");
		$this->iDataSource->addColumnControl("order_identifier", "select_value", "orders.order_id");

		$this->iDataSource->addColumnControl("date_shipped", "form_label", "Date Shipment Created");
		$this->iDataSource->addColumnControl("shipment_sent", "data_type", "date");
		$this->iDataSource->addColumnControl("shipment_sent", "form_label", "Shipment Sent");
		$this->iDataSource->addColumnControl("shipment_sent", "readonly", true);

		$this->iDataSource->addColumnControl("order_time", "data_type", "datetime");
		$this->iDataSource->addColumnControl("order_time", "form_label", "Order Time");

		$this->iDataSource->addColumnControl("location_id", "readonly", true);
		$this->iDataSource->addColumnControl("date_shipped", "readonly", true);
		$this->iDataSource->addColumnControl("label_url", "readonly", true);
		$this->iDataSource->addColumnControl("label_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("label_url", "css-width", "800px");

		$this->iDataSource->addFilterWhere("orders.client_id = " . $GLOBALS['gClientId'] . " and order_shipments.location_id not in (select location_id from locations where inactive = 0 and product_distributor_id is not null)");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_status":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = getFieldFromId("order_id", "order_shipments", "order_shipment_id", $row['primary_identifier']);
				}
				$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_POST['order_status_id']);
				$count = 0;
				if (!empty($orderIds) && !empty($orderStatusId)) {
					foreach ($orderIds as $thisOrderId) {
						if (order::updateOrderStatus($thisOrderId, $orderStatusId)) {
							$count++;
						}
					}
				}
				$returnArray['info_message'] = $count . " orders changed";
				ajaxResponse($returnArray);
				break;
			case "tag_orders":
				$orderTagId = getFieldFromId("order_tag_id", "order_tags", "order_tag_id", $_POST['order_tag_id']);
				if (empty($orderTagId)) {
					$returnArray['error_message'] = "Invalid Order Tag";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("insert ignore into order_tag_links (order_id,order_tag_id) select primary_identifier,? from selected_rows where page_id = ? and user_id = ? and primary_identifier in (select order_id from orders where client_id = ?)",
					$orderTagId, $GLOBALS['gPageId'], $GLOBALS['gUserId'], $GLOBALS['gClientId']);
				ajaxResponse($returnArray);
				break;
			case "clear_tag_orders":
				$orderTagId = getFieldFromId("order_tag_id", "order_tags", "order_tag_id", $_POST['order_tag_id']);
				if (empty($orderTagId)) {
					$returnArray['error_message'] = "Invalid Order Tag";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from order_tag_links where order_tag_id = ? and order_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)",
					$orderTagId, $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "print_picklists":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$returnArray['order_ids'] = implode("|", $orderIds);
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
			case "get_easy_post_label_rates":
				$pagePreferences = Page::getPagePreferences();
				$pagePreferences['weight_unit'] = $_POST['weight_unit'];
				$pagePreferences['predefined_package'] = $_POST['predefined_package'];

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

                Order::updateGunbrokerOrder($orderShipmentRow['order_id']);
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
			case "send_tracking_email":
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentId)) {
					$returnArray['error_message'] = "Invalid Shipment";
					ajaxResponse($returnArray);
					break;
				}
				Order::sendTrackingEmail($orderShipmentId);
				$returnArray['info_message'] = "Tracking Email sent";
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
							"product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'FFL_REQUIRED_" . strtoupper($addressRow['state']) . "')");
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
					"address_2" => $addressRow['address_2'], "city" => $addressRow['city'], "state" => $addressRow['state'], "postal_code" => $addressRow['postal_code'],
					"phone_number" => $orderRow['phone_number'], "residential" => true);

				$returnArray['ship_from'] = "dealer";
				$returnArray['ship_to'] = "customer";
				$returnArray['signature_required'] = $signatureRequired;
				$returnArray['adult_signature_required'] = $adultSignatureRequired;
				if (!empty($orderRow['federal_firearms_licensee_id'])) {
					$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
					$fflName = $fflRow['business_name'];
					$fflAttentionLine = (empty($orderRow['attention_line']) ? EasyPostIntegration::formatAttentionLine($orderRow['full_name'], $orderRow['phone_number']) : $orderRow['attention_line']);
					$returnArray['addresses'][] = array("key_value" => "ffl", "description" => "FFL Dealer Premises", "full_name" => $fflName, "attention_line" => $fflAttentionLine, "address_1" => $fflRow['address_1'],
						"address_2" => $fflRow['address_2'], "city" => $fflRow['city'], "state" => $fflRow['state'], "postal_code" => $fflRow['postal_code'], "phone_number" => $fflRow['phone_number'], "residential" => false);
					$returnArray['addresses'][] = array("key_value" => "ffl_mailing", "description" => "FFL Dealer Mailing", "full_name" => $fflName, "attention_line" => $fflAttentionLine, "address_1" => $fflRow['mailing_address_1'],
						"address_2" => $fflRow['mailing_address_2'], "city" => $fflRow['mailing_city'], "state" => $fflRow['mailing_state'], "postal_code" => $fflRow['mailing_postal_code'], "phone_number" => $fflRow['phone_number'], "residential" => false);
					if ($fflRequired) {
						$returnArray['ship_to'] = "ffl" . (empty($fflRow['mailing_address_preferred']) ? "" : "_mailing");
					}
				}
				$resultSet = executeQuery("select * from locations join contacts using (contact_id) where inactive = 0 and product_distributor_id is null and locations.client_id = ? and address_1 is not null", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] <= 1) {
					$dealerPhoneNumber = Contact::getContactPhoneNumber($GLOBALS['gClientRow']['contact_id'],'Primary');
					$returnArray['addresses'][] = array("key_value" => "dealer", "description" => "Dealer", "full_name" => $GLOBALS['gClientName'], "attention_line" => "", "address_1" => $GLOBALS['gClientRow']['address_1'],
						"address_2" => $GLOBALS['gClientRow']['address_2'], "city" => $GLOBALS['gClientRow']['city'], "state" => $GLOBALS['gClientRow']['state'], "postal_code" => $GLOBALS['gClientRow']['postal_code'],
						"country_id" => $GLOBALS['gClientRow']['country_id'], "phone_number" => $dealerPhoneNumber, "residential" => false);
				} else {
					while ($row = getNextRow($resultSet)) {
						$dealerPhoneNumber = Contact::getContactPhoneNumber($row['contact_id'],'Primary');
						if (empty($dealerPhoneNumber)) {
							$dealerPhoneNumber = Contact::getContactPhoneNumber($GLOBALS['gClientRow']['contact_id'],'Primary');
						}
						if ($returnArray['ship_from'] == "dealer") {
							$returnArray['ship_from'] = "location_" . $row['location_id'];
						}
						$returnArray['addresses'][] = array("key_value" => "location_" . $row['location_id'], "description" => $row['description'], "full_name" => (empty($row['business_name']) ? $GLOBALS['gClientName'] : $row['business_name']),
							"attention_line" => "", "address_1" => $row['address_1'], "address_2" => $row['address_2'], "city" => $row['city'], "state" => $row['state'], "postal_code" => $row['postal_code'],
							"country_id" => $row['country_id'], "phone_number" => $dealerPhoneNumber, "residential" => false);
					}
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#_print_1508_button", function () {
                const orderId = $("#primary_id").val();
                window.open("/print-1508?order_id=" + orderId + "&printable=true");
            });
            $(document).on("click", "#add_note", function () {
                if (!empty($("#content").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_note", { content: $("#content").val(), order_id: $("#order_id").val(), public_access: $("#public_access").prop("checked") ? 1 : 0 }, function(returnArray) {
                        if ("order_note" in returnArray) {
                            $("#order_notes").find(".no-order-notes").remove();
                            var orderNoteBlock = $("#_order_note_template").html();
                            for (var j in returnArray['order_note']) {
                                var re = new RegExp("%" + j + "%", 'g');
                                orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_note'][j]);
                            }
                            $("#order_notes").append(orderNoteBlock);
                            $("#content").val("");
                            $("#public_access").prop("checked", false);
                        }
                    });
                }
                return false;
            });
            $(document).on("tap click", "#_shipment_label_button", function () {
                if (!empty($("#tracking_identifier").val())) {
                    displayErrorMessage("This package already has a tracking ID");
                    return;
                }
                createShipmentLabel();
                return false;
            });
            $(document).on("tap click", "#_print_packing_slip_button", function () {
                var orderShipmentId = $("#primary_id").val();
                window.open("/packing-slip?order_shipment_id=" + orderShipmentId);
                return false;
            });
            $("#label_url").click(function () {
                if (!empty($(this).val())) {
                    window.open($(this).val());
                }
            });
            $("#letter_package").click(function () {
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
			<?php if ($this->iEasyPostActive) { ?>
            $(document).on("change", "#ship_to", function () {
                $("#_easy_post_to_address").find("input").not("input[type=checkbox]").val("");
                if (!empty($(this).val()) && $(this).val() != "-1") {
                    var thisAddress = shippingAddresses[$(this).val()];
                    $("#to_full_name").val(thisAddress['full_name']);
                    $("#to_address_1").val(thisAddress['address_1']);
                    $("#to_address_2").val(thisAddress['address_2']);
                    $("#to_city").val(thisAddress['city']);
                    $("#to_state").val(thisAddress['state']);
                    $("#to_postal_code").val(thisAddress['postal_code']);
                    $("#to_phone_number").val(thisAddress['phone_number']);
                    $("#residential_address").prop("checked", thisAddress['residential']);
                    checkForCustoms();
                }
            });
            $(document).on("change", "#ship_from", function () {
                $("#_easy_post_from_address").find("input").not("input[type=checkbox]").val("");
                if (!empty($(this).val()) && $(this).val() != "-1") {
                    var thisAddress = shippingAddresses[$(this).val()];
                    $("#from_full_name").val(thisAddress['full_name']);
                    $("#from_address_1").val(thisAddress['address_1']);
                    $("#from_address_2").val(thisAddress['address_2']);
                    $("#from_city").val(thisAddress['city']);
                    $("#from_state").val(thisAddress['state']);
                    $("#from_postal_code").val(thisAddress['postal_code']);
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
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_easy_post_customs_items&order_shipment_id=" + orderShipmentId, function(returnArray) {
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

			<?php } ?>
            $(document).on("click", "#_tracking_email_button", function () {
                var orderShipmentId = $("#primary_id").val();
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
            $(document).on("click", ".postage-rate", function () {
                $("#rate_shipment_id").val($(this).closest("p").find(".rate-shipment-id").val());
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            let shippingAddresses = [];

            function customActions(actionName) {
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
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_status", $("#_set_status_form").serialize(), function(returnArray) {
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
                if (actionName === "print_picklists") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=print_picklists", function(returnArray) {
                        if ("order_ids" in returnArray) {
                            window.open("/print-pick-list?order_shipment_id=" + returnArray['order_ids'] + "&printable=true");
                        }
                    });
                }
                if (actionName === "tag_orders") {
                    $("#order_tag_id_label").html("Tag to ADD to selected Orders");
                    $('#_tag_orders_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 400,
                        title: 'Tag Orders',
                        buttons: {
                            Tag: function (event) {
                                if (!empty($("#order_tag_id").val())) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=tag_orders", { order_tag_id: $("#order_tag_id").val() }, function(returnArray) {
                                        getDataList();
                                    });
                                }
                                $("#_tag_orders_dialog").dialog('close');
                            },
                            Cancel: function (event) {
                                $("#_tag_orders_dialog").dialog('close');
                            }
                        }
                    });
                }
                if (actionName === "clear_tag_orders") {
                    $("#order_tag_id_label").html("Tag to REMOVE from selected Orders");
                    $('#_tag_orders_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 400,
                        title: 'Tag Orders',
                        buttons: {
                            Clear: function (event) {
                                if (!empty($("#order_tag_id").val())) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clear_tag_orders", { order_tag_id: $("#order_tag_id").val() }, function(returnArray) {
                                        getDataList();
                                    });
                                }
                                $("#_tag_orders_dialog").dialog('close');
                            },
                            Cancel: function (event) {
                                $("#_tag_orders_dialog").dialog('close');
                            }
                        }
                    });
                }
            }

            function afterGetRecord(returnArray) {
                $("#order_notes").find(".order-note").remove();
                for (var i in returnArray['order_notes']) {
                    var orderNoteBlock = $("#_order_note_template").html();
                    for (var j in returnArray['order_notes'][i]) {
                        var re = new RegExp("%" + j + "%", 'g');
                        orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_notes'][i][j]);
                    }
                    $("#order_notes").append(orderNoteBlock);
                }
                if ($("#order_notes").find(".order-note").length == 0) {
                    $("#order_notes").append("<tr class='order-note no-order-notes'><td colspan='100'>No Notes yet</td></tr>");
                }
                $("#_templates").find("table").each(function () {
                    const elementId = $(this).find("tbody").attr("id");
                    if (elementId.indexOf("_order_item_serial_numbers_") === 0) {
                        $(this).remove();
                    }
                });
                $("#_templates").append(returnArray['jquery_templates']);
                for (const i in returnArray) {
                    if (i.indexOf("order_item_serial_numbers_") !== 0) {
                        continue;
                    }
                    for (const j in returnArray[i]) {
                        addEditableListRow(i, returnArray[i][j]);
                    }
                }
                $("#adult_signature_required").prop("checked", returnArray['adult_signature_required']);
                $("#_edit_form").validationEngine();
            }

            function createShipmentLabel() {
                var orderShipmentId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_shipment_details&order_shipment_id=" + orderShipmentId, function(returnArray) {
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
                        for (var i in returnArray['addresses']) {
                            shippingAddresses[returnArray['addresses'][i]['key_value']] = returnArray['addresses'][i];
                            var thisOption = $("<option></option>").attr("value", returnArray['addresses'][i]['key_value']).text(returnArray['addresses'][i]['description']);
                            $("#ship_to").append(thisOption);
                        }
                        $("#ship_to").val(returnArray['ship_to']).trigger("change");

                        $("#ship_from").find("option").remove();
                        $("#ship_from").append($("<option></option>").attr("value", "").text("[Select]"));
                        $("#ship_from").append($("<option></option>").attr("value", "-1").text("[Custom Address]"));
                        for (var i in returnArray['addresses']) {
                            var thisOption = $("<option></option>").attr("value", returnArray['addresses'][i]['key_value']).text(returnArray['addresses'][i]['description']);
                            $("#ship_from").append(thisOption);
                        }
                        $("#ship_from").val(returnArray['ship_from']).trigger("change");

                        $("#postage_rates").html("");
                        $("#_easy_post_wrapper").find("input").prop("disabled", false);
                        $("#_easy_post_wrapper").find("select").prop("disabled", false);
                        if (!empty($("#ffl_required").val()) && !$("#adult_signature_required").prop("checked")) {
                            $("#signature_required").prop("checked", true);
                        }
                        $("#height").focus();

                        $('#_easy_post_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1000,
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
                                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_easy_post_label_rates&order_shipment_id=" + orderShipmentId, $("#_easy_post_form").serialize(), function(returnArray) {
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
                                                            for (var i in returnArray['rates']) {
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
                                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_easy_post_label&order_shipment_id=" + orderShipmentId, $("#_easy_post_form").serialize(), function(returnArray) {
                                                        if (!("error_message" in returnArray)) {
                                                            $("#shipping_charge").val(returnArray['shipping_charge']);
                                                            $("#shipping_charge").data("crc_value", getCrcValue(returnArray['shipping_charge']));
                                                            $("#tracking_identifier").val(returnArray['tracking_identifier']);
                                                            $("#tracking_identifier").data("crc_value", getCrcValue(returnArray['tracking_identifier']));
                                                            $("#carrier_description").val(returnArray['carrier_description']);
                                                            $("#carrier_description").data("crc_value", getCrcValue(returnArray['carrier_description']));
                                                            $("#shipping_carrier_id").val(returnArray['shipping_carrier_id']);
                                                            $("#shipping_carrier_id").data("crc_value", getCrcValue(returnArray['shipping_carrier_id']));
                                                            $("#label_url").val(returnArray['label_url']);
                                                            $("#label_url").data("crc_value", getCrcValue(returnArray['label_url']));
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
            }
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_set_status_dialog" class="dialog-box">
            <p>Set status for selected orders. This cannot be undone, so be sure.</p>
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

        <div id="_tag_orders_dialog" class="dialog-box">
            <div class='basic-form-line'>
                <label id="order_tag_id_label"></label>
                <select id="order_tag_id" name="order_tag_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['order_tag_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
        </div>

        <div id="_confirm_email_dialog" class="dialog-box">
            Are you sure you want to resend this tracking email?
        </div> <!-- confirm_shipment_dialog -->

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
                                <option value="pound"<?= ($pagePreferences['weight_unit'] == "pound" ? " selected" : "") ?>>Lbs</option>
                                <option value="ounce"<?= ($pagePreferences['weight_unit'] == "ounce" ? " selected" : "") ?>>Oz</option>
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

                        <div class="basic-form-line letter-package">
                            <input tabindex="10" type="checkbox" id="include_media" name="include_media" value="1"><label class="checkbox-label" for="include_media">Include Media Mail (books & videos)</label>
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
                        if(count($predefinedPackages) > 2) {
                            $predefinedWarning = "NOTE: More than 2 predefined packages are configured to be quoted.  This will result in overage charges from EasyPost.";
                        }
                        ?>

                        <p id="predefined_message">Including predefined package sizes will cause fetching rates to take a bit longer.<br>
                            EasyPost charges for excessive rate quotes. Each predefined package is an extra quote.<br><span class="red-text"><?= $predefinedWarning?></span></p>

                        <div class="basic-form-line">
                            <label>Insurance Amount</label>
                            <span class='help-label'><?= getPreference("EASY_POST_INSURANCE_PERCENT") ?: 1 ?>% charge applies. Leave blank to use carrier default</span>
                            <input tabindex="10" type="text" class="validate[custom[number],min[.01]]" data-decimal-places="2" size="10" id="insurance_amount" name="insurance_amount">
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
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

                    <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_customs_items_row">
                        <label>Customs Items (Look up HS Tariff Number at <a href="https://hts.usitc.gov/">hts.usitc.gov</a>)</label>
						<?php
						$customsItems = EasyPostIntegration::getCustomsItemsControl($this);
						echo $customsItems->getControl()
						?>
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
                        <label class="required-label" for="customs_signer">Signer - Name of the person certifying the customs form.</label>
                        <input tabindex="10" id="customs_signer" class="validate[required]" data-conditional-required="$('#_customs_required').val() == 1" name="customs_signer">
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                </div>

                <div id="postage_rates">
                </div>
            </form>
        </div>

		<?php
	}

	function internalCSS() {
		?>
        <style>
            #ffl_section {
                margin: 10px 0;
            }
            .print-1508 {
                cursor: pointer;
            }

            #predefined_message {
                font-size: .7rem;
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

            #postage_rates {
                border-top: 1px solid rgb(180, 180, 180);
                padding-top: 20px;
                margin-top: 20px;
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
                padding: 10px;
            }

            table.order-information td {
                background-color: rgb(240, 240, 240);
                padding: 10px;
            }

            #product_list {
                margin: 10px 0;
            }

            #order_items {
                margin: 10px 0;
            }

            #product_list td {
                position: relative;
            }

            .dialog-box .basic-form-line {
                white-space: nowrap;
            }
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['order_id_display'] = array("data_value" => $returnArray['order_id']['data_value']);
		$signatureRequiredDepartmentCodes = getPreference("EASYPOST_ADULT_SIGNATURE_REQUIRED_DEPARTMENTS");
		$signatureRequiredDepartmentIds = array();
		if (!empty($signatureRequiredDepartmentCodes)) {
			foreach (explode(",", $signatureRequiredDepartmentCodes) as $departmentCode) {
				$signatureRequiredDepartmentIds[] = getFieldFromId("product_department_id", "product_departments", "product_department_code", $departmentCode);
			}
		}
		$adultSignatureRequired = false;

		$orderRow = getRowFromId("orders", "order_id", $returnArray['order_id']['data_value']);
		if (empty($orderRow['federal_firearms_licensee_id'])) {
			$returnArray['ffl_section'] = array("data_value" => "");
		} else {
			$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
			$selectedFFL = "<h2>FFL Dealer</h2>" . "<p>" . $fflRow['license_number'] . "<br>" . (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "<br>" . $fflRow['address_1'] . "<br>" .
                (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . "<br>") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'] . "<br>" .
				(empty($fflRow['email_address']) ? "" : $fflRow['email_address'] . "<br>") .
				(empty($fflRow['phone_number']) ? "" : $fflRow['phone_number'] . "<br>") .
				(empty($fflRow['file_id']) ? "No license" : "<a target='_blank' href='/download.php?id=" . $fflRow['file_id'] . "'>View License</a>") . "<br>" .
				(empty($fflRow['sot_file_id']) ? "No SOT Document" : "<a target='_blank' href='/download.php?id=" . $fflRow['sot_file_id'] . "'>View SOT</a>") . "</p>";
			$returnArray['ffl_section'] = array("data_value" => $selectedFFL);
		}

		$orderStatusId = getFieldFromId("order_status_id", "orders", "order_id", $returnArray['order_id']['data_value']);
		$returnArray['order_status_id'] = array("data_value" => $orderStatusId, "crc_value" => getCrcValue($orderStatusId));
		$dateCompleted = getFieldFromId("date_completed", "orders", "order_id", $returnArray['order_id']['data_value']);
		$dateCompleted = (empty($dateCompleted) ? "" : date("m/d/Y", strtotime($dateCompleted)));
		$returnArray['date_completed'] = array("data_value" => $dateCompleted, "crc_value" => getCrcValue($dateCompleted));

		$returnArray['shipment_sent'] = array("data_value" => (empty($returnArray['tracking_identifier']['data_value']) ? "" : $returnArray['date_shipped']['data_value']));
		$returnArray['order_time'] = array("data_value" => date("m/d/Y g:ia", strtotime(getFieldFromId("order_time", "orders", "order_id", $returnArray['order_id']['data_value']))));
		$returnArray['full_name'] = array("data_value" => getFieldFromId("full_name", "orders", "order_id", $returnArray['order_id']['data_value']));

		ob_start();
		?>
        <table id="order_items" class="grid-table">
            <tr>
                <th>Product</th>
                <th>UPC</th>
                <th class="align-right">Quantity</th>
            </tr>
			<?php
			$totalItems = 0;
			$resultSet = executeQuery("select * from order_items join products using (product_id) join product_data using (product_id) where order_id = ?", $returnArray['order_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				?>
                <tr>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText($row['upc_code']) ?></td>
                    <td class="align-right"><?= $row['quantity'] ?></td>
                </tr>
				<?php
				$totalItems += $row['quantity'];
			}
			?>
        </table>
        <p>Total items in Order: <span class='highlighted-text'><?= $totalItems ?></span></p>
		<?php
		$returnArray['order_items'] = array("data_value" => ob_get_clean());

		ob_start();
		?>
        <table id="product_list" class="grid-table">
            <tr>
                <th>Product</th>
                <th>UPC</th>
                <th>Serial Numbers</th>
                <th class="align-right">Quantity</th>
            </tr>
			<?php
			$totalItems = 0;
			$jqueryTemplates = "";
			$resultSet = executeQuery("select * from order_items join order_shipment_items using (order_item_id) join products using (product_id) join product_data using (product_id) where order_shipment_id = ?", $returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				if (!$adultSignatureRequired) {
					foreach ($signatureRequiredDepartmentIds as $departmentId) {
						if (ProductCatalog::productIsInDepartment($row['product_id'], $departmentId)) {
							$adultSignatureRequired = true;
							break;
						}
					}
				}
				?>
                <tr>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText($row['upc_code']) ?></td>
                    <td>
						<?php
						$serialNumberControl = new DataColumn("order_item_serial_numbers_" . $row['order_item_id']);
						$serialNumberControl->setControlValue("data_type", "custom_control");
						$serialNumberControl->setControlValue("control_class", "EditableList");
						$serialNumberControl->setControlValue("primary_table", "order_items");
						$serialNumberControl->setControlValue("list_table", "order_item_serial_numbers");
						echo $serialNumberControl->getControl($this);

						$customControl = new EditableList($serialNumberControl, $this);
						$jqueryTemplates .= $customControl->getTemplate();

						$returnArray = array_merge($returnArray, $customControl->getRecord($row['order_item_id']));
						?>
                    </td>
                    <td class="align-right"><?= $row['quantity'] ?></td>
                </tr>
				<?php
				$totalItems += $row['quantity'];
			}
			?>
        </table>
        <p>Total items in Shipment: <span class='highlighted-text'><?= $totalItems ?></span></p>
		<?php
		$returnArray['order_shipment_items'] = array("data_value" => ob_get_clean());
		$returnArray['jquery_templates'] = $jqueryTemplates;
		$returnArray['adult_signature_required'] = $adultSignatureRequired;

		$orderNotes = array();
		$resultSet = executeQuery("select * from order_notes where order_id = ? order by time_submitted desc", $returnArray['order_id']['data_value']);
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


	}

	function afterSaveChanges($nameValues, $actionPerformed) {

		$resultSet = executeQuery("select * from order_items join order_shipment_items using (order_item_id) where order_shipment_id = ?", $nameValues['primary_id']);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists("_order_item_serial_numbers_" . $row['order_item_id'] . "_delete_ids", $nameValues)) {
				continue;
			}
			$serialNumberControl = new DataColumn("order_item_serial_numbers_" . $row['order_item_id']);
			$serialNumberControl->setControlValue("data_type", "custom_control");
			$serialNumberControl->setControlValue("control_class", "EditableList");
			$serialNumberControl->setControlValue("primary_table", "order_items");
			$serialNumberControl->setControlValue("list_table", "order_item_serial_numbers");
			$customControl = new EditableList($serialNumberControl, $this);

			if ($customControl->saveData(array_merge($nameValues, array("primary_id" => $row['order_item_id']))) !== true) {
				return $customControl->getErrorMessage();
			}
		}

		$orderId = getFieldFromId("order_id", "order_shipments", "order_shipment_id", $nameValues['primary_id']);
		order::updateOrderStatus($orderId, $nameValues['order_status_id']);
		if (!empty($nameValues['date_completed'])) {
            Order::markOrderCompleted($orderId, $nameValues['date_completed']);
        }
		return true;
	}

	function jqueryTemplates() {
		?>
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
		<?php
		$customsItems = EasyPostIntegration::getCustomsItemsControl($this);
		echo $customsItems->getTemplate();
	}
}

$pageObject = new ThisPage("order_shipments");
$pageObject->displayPage();
