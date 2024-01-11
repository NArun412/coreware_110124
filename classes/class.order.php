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

class Order {
	private $iOrderId = false;
	private $iOrderRow = array();
	private $iOrderItems = array();
	private $iPromotionId = array();
	private $iErrorMessage = "";
	private $iCustomFields = array();
	private $iPackNotes = "";

	function __construct($orderId = "") {
		if (!empty($orderId)) {
			$resultSet = executeQuery("select * from orders where order_id = ? and client_id = ?", $orderId, $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$this->iOrderRow = $row;
				$this->iOrderId = $row['order_id'];
			} else {
				$this->iErrorMessage = "Order ID not found";
			}
		}
        ProductCatalog::getInventoryAdjustmentTypes();
	}

	public static function sendTrackingEmail($orderShipmentId) {
		$shippingMethodId = getFieldFromId("shipping_method_id", "orders", "order_id", getFieldFromId("order_id", "order_shipments", "order_shipment_id", $orderShipmentId));
		$pickup = getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $shippingMethodId);
		$emailId = false;
		if ($pickup) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_PICKUP_TRACKING_EMAIL", "inactive = 0");
		}
		if (empty($emailId)) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_TRACKING_EMAIL", "inactive = 0");
		}
		if (empty($emailId)) {
			return false;
		}
		$noNotification = getFieldFromId("no_notifications", "order_shipments", "order_shipment_id", $orderShipmentId);
		if (!empty($noNotification)) {
			return false;
		}
		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $orderShipmentId);
		$orderRow = getRowFromId("orders", "order_id", $orderShipmentRow['order_id']);
		$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $orderRow['contact_id']);
		$copyFFLDealer = getPreference("COPY_FFL_DEALER_CONFIRMATION");
		$bccEmailAddresses = array();
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$shippingCarrierRow = getRowFromId("shipping_carriers", "shipping_carrier_id", $orderShipmentRow['shipping_carrier_id']);
		$substitutions = array_merge($shippingCarrierRow, $orderShipmentRow, $orderRow, $contactRow);

		if (!empty($orderRow['federal_firearms_licensee_id'])) {
			$fflRow = (new FFL(array("federal_firearms_licensee_id" => $orderRow['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
			if ($fflRow) {
				$substitutions['ffl_name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
				$substitutions['store_name'] = (empty($fflRow['licensee_name']) ? $fflRow['business_name'] : $fflRow['licensee_name']);
				$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
				$substitutions['ffl_license_number'] = $fflRow['license_number'];
				$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
				$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];
				$substitutions['store_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];
				if ($copyFFLDealer && !empty($fflRow['email_address'])) {
					$bccEmailAddresses[] = $fflRow['email_address'];
				}
			}
		}
		if (empty($emailAddress) && empty($bccEmailAddresses)) {
			return false;
		}
		if (empty($substitutions['carrier_description'])) {
			$substitutions['carrier_description'] = getFieldFromId("description", "shipping_carriers", "shipping_carrier_id", $substitutions['shipping_carrier_id']);
		}
		$substitutions['order_date'] = date("m/d/Y", strtotime($substitutions['order_time']));
		$substitutions['date_shipped'] = date("m/d/Y", strtotime($substitutions['date_shipped']));
		$substitutions['shipping_carrier'] = $shippingCarrierRow['description'] ?: "Not specified";
		// add Google as default for misc / other carrier
		$shippingCarrierRow['link_url'] = $shippingCarrierRow['link_url'] ?: "https://www.google.com/search?q=%tracking_identifier%";

		$substitutions['link_url'] = str_replace("%tracking_identifier%", $orderShipmentRow['tracking_identifier'], $shippingCarrierRow['link_url']);

		$shippingAddress = (empty($orderRow['address_id']) ? $contactRow : array_merge($contactRow, getRowFromId("addresses", "address_id", $orderRow['address_id'])));
		$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
		if (!empty($shippingAddress['address_2'])) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
		}
		$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
		if (!empty($shippingAddress['postal_code'])) {
			$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
		}
		if (!empty($shippingCityLine)) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
		}
		if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
		}
		$orderItemIds = array();
		$orderShipmentItemsResult = executeQuery("select order_item_id, quantity from order_shipment_items where order_shipment_id = ?", $orderShipmentId);
		while ($orderShipmentItemRow = getNextRow($orderShipmentItemsResult)) {
			$orderItemIds[$orderShipmentItemRow['order_item_id']] = $orderShipmentItemRow['quantity'];
		}
		$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderShipmentRow['order_id'], false, $orderItemIds), $substitutions);

		sendEmail(array("email_id" => $emailId, "email_addresses" => $emailAddress, "bcc_addresses" => $bccEmailAddresses, "substitutions" => $substitutions, "contact_id" => $orderRow['contact_id']));
		if (!empty($orderRow['purchase_order_number']) && $orderRow['order_method_id'] == getFieldFromId("order_method_id", "order_methods", "order_method_code", "GUNBROKER")) {
			try {
				$gunbroker = new GunBroker();
				$gunbroker->updateOrderShipping($orderRow['purchase_order_number'], $orderShipmentRow);
			} catch (Exception $e) {
			}
			self::updateGunbrokerOrder($orderRow['order_id'], $orderRow);
		}
        // Trackers are automatically created for EasyPost shipments, so if there is a label_url there is already a tracker
        if(empty($orderShipmentRow['label_url']) && getPreference("EASY_POST_CREATE_TRACKERS")) {
            $easyPostApiKey = getPreference($GLOBALS['gDevelopmentServer'] ? "EASY_POST_TEST_API_KEY" : "EASY_POST_API_KEY");
            if(!empty($easyPostApiKey)) {
                $result = EasyPostIntegration::createTracker($easyPostApiKey, $orderShipmentRow['tracking_identifier'], $shippingCarrierRow['shipping_carrier_code']);
                if($result !== true) {
                    addProgramLog("Error occurred creating EasyPost tracker for Order ID " . $orderRow['order_id'] . ": $result");
                }
            }
        }
		return true;
	}

	public function setPromotionId($promotionId) {
		$this->iPromotionId = $promotionId;
	}

	public static function getOrderItemsSubstitutions($orderId, $includePrices = true, $orderItemIds = array()) {
		$returnArray = array("order_items_quantity" => 0, "cart_total" => 0);
		$orderItems = "";
		$orderItemsTable = "<table id='order_items_table'><tr><th class='product-code-header'>Product Code</th><th class='upc-code-header'>UPC</th>" .
			"<th class='description-header'>Description</th><th class='quantity-header'>Quantity</th>" .
			($includePrices ? "<th class='price-header'>Price</th><th class='extended-header'>Extended</th><th class='product-download-header'></th>" : "") . "</tr>";
		$domainName = getDomainName();
		$orderItemsWhere = "";
		if (!empty($orderItemIds)) {
			$orderItemsWhere = " and order_item_id in (" . implode(",", array_keys($orderItemIds)) . ")";
		}
		$itemsSet = executeQuery("select * from order_items where order_id = ? and deleted = 0" . $orderItemsWhere, $orderId);
		$itemsArray = array();
		$packArray = array();
		while ($row = getNextRow($itemsSet)) {
			if (empty($row['pack_product_id'])) {
				if (!empty($orderItemIds[$row['order_item_id']])) {
					$row['quantity'] = $orderItemIds[$row['order_item_id']];
				}
				$itemsArray[] = $row;
				continue;
			}
			$showAsPack = CustomField::getCustomFieldData($row['pack_product_id'], "SHOW_AS_PACK", "PRODUCTS");
			if (empty($showAsPack)) {
				$itemsArray[] = $row;
				continue;
			}
			if (!array_key_exists($row['pack_product_id'], $packArray)) {
				$packArray[$row['pack_product_id']] = array("description" => $row['description'], "quantity" => $row['pack_quantity'], "product_id" => $row['pack_product_id'], "sale_price" => $row['sale_price']);
			} else {
				$packArray[$row['pack_product_id']]['sale_price'] += ($row['sale_price'] * $row['quantity']);
			}
		}
		$itemsArray = array_merge($itemsArray, $packArray);
		foreach ($itemsArray as $row) {
			$returnArray['order_items_quantity'] += $row['quantity'];
			$returnArray['cart_total'] += $row['quantity'] * $row['sale_price'];
			$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
			$productRow['image_url'] = ProductCatalog::getProductImage($row['product_id'], array("no_cache_filename" => true));
			if (!startsWith($productRow['image_url'], "http")) {
				$productRow['image_url'] = getDomainName() . "/" . ltrim($productRow['image_url'], "/");
			}
			$productAddons = "";
			$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $row['order_item_id']);
			while ($addonRow = getNextRow($addonSet)) {
				$productAddons .= "<br>Add on: " . htmlText($addonRow['description']) . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")");
			}

			$addonSet = executeQuery("select description,custom_field_id,integer_data,number_data,text_data,date_data,(select control_value from custom_field_controls where " .
				"custom_field_id = custom_fields.custom_field_id and control_name = 'data_type') data_type from custom_field_data join custom_fields using (custom_field_id) where " .
				"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS') and primary_identifier = ?", $row['order_item_id']);
			while ($addonRow = getNextRow($addonSet)) {
				switch ($addonRow['data_type']) {
					case "int":
						$addonRow['data_value'] = $addonRow['integer_data'];
						break;
					case "decimal":
						$addonRow['data_value'] = $addonRow['number_data'];
						break;
					case "date":
						$addonRow['data_value'] = $addonRow['date_data'];
						break;
					default:
						if (startsWith($addonRow['text_data'], "[")) {
							$addonRow['data_value'] = "";
							$dataArray = json_decode($addonRow['text_data'], true);
							foreach ($dataArray as $thisRow) {
								foreach ($thisRow as $fieldName => $fieldData) {
									$addonRow['data_value'] .= "<br>" . snakeCaseToDescription($fieldName) . ": " . $fieldData;
								}
							}
						} else {
							$addonRow['data_value'] = $addonRow['text_data'];
						}
						break;
				}
				$productAddons .= "<br>" . $addonRow['description'] . ": " . $addonRow['data_value'];
			}

			$serialNumberSet = executeQuery("select * from order_item_serial_numbers where order_item_id = ?", $row['order_item_id']);
			while ($serialNumberRow = getNextRow($serialNumberSet)) {
				$productAddons .= "<br>Serial Number: " . htmlText($serialNumberRow['serial_number']);
			}
			$productUpcLink = (empty($productRow['upc_code']) ? "" : "<a href='" . $domainName . (empty($productRow['link_name']) ? "/product-details?id=" . $productRow['product_id'] : "/product/" . $productRow['link_name']) . "'>" . $productRow['upc_code'] . "</a>");
			if ($includePrices) {
				$orderItems .= "<div class='order-item-line'><span class='product-code'>" . $productRow['product_code'] . "</span><span class='upc-code'>" . $productUpcLink . "</span>" .
					"<span class='product-image'><img src='" . $productRow['image_url'] . "'></span>" .
					"<span class='product-description'>" . $productRow['description'] . $productAddons . "</span>" .
					"<span class='product-quantity'>" . $row['quantity'] . "</span>" .
					"<span class='product-price'>$" . number_format($row['sale_price'], 2) . "</span>" .
					"<span class='product-extended'>$" . number_format(($row['quantity'] * $row['sale_price']), 2) . "</span>" .
					"<span class='product-download'>" . (empty($productRow['virtual_product']) || empty($productRow['file_id']) ? "" : "<a href='" . $domainName . "/download.php?id=" . $productRow['file_id'] . "'>Download</a>") . "</span>" .
					"</div>";
				$orderItemsTable .= "<tr class='order-item-row'><td class='product-code'>" . $productRow['product_code'] . "</td><td class='upc-code'>" . $productUpcLink . "</td>" .
					"<td class='product-description' colspan='5'>" . $productRow['description'] . $productAddons . "</td></tr>" .
					"<tr><td class='product-code'></td><td class='upc-code'></td><td class='product-description'></td><td class='align-right product-quantity'>" . $row['quantity'] . "</td>" .
					"<td class='align-right product-price'>$" . number_format($row['sale_price'], 2) . "</td>" .
					"<td class='align-right product-extended'>$" . number_format(($row['quantity'] * $row['sale_price']), 2) . "</td>" .
					"<td class='product-download'>" . (empty($productRow['virtual_product']) || empty($productRow['file_id']) ? "" : "<a href='" . $domainName . "/download.php?id=" . $productRow['file_id'] . "'>Download</a>") . "</td></tr>";
			} else {
				$orderItems .= "<div class='order-item-line'><span class='product-code'>" . $productRow['product_code'] . "</span><span class='upc-code'>" . $productUpcLink . "</span>" .
					"<span class='product-description'>" . $productRow['description'] . $productAddons . "</span>" .
					"<span class='product-quantity'>" . $row['quantity'] . "</span>" .
					"</div>";
				$orderItemsTable .= "<tr class='order-item-row'><td class='product-code'>" . $productRow['product_code'] . "</td><td class='upc-code'>" . $productUpcLink . "</td>" .
					"<td class='product-description'>" . $productRow['description'] . $productAddons . "</td>" .
					"<td class='align-right product-quantity'>" . $row['quantity'] . "</td></tr>";
			}
		}
		$orderItemsTable .= "</table>";
		$returnArray['order_items'] = $orderItems;
		$returnArray['order_items_table'] = $orderItemsTable;
		$returnArray['cart_total'] = number_format($returnArray['cart_total'], 2);

		return $returnArray;
	}

	public static function markOrderReadyForPickup($orderId, $setStatus = true) {
		$returnArray = array();
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$locationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id'], "pickup = 1");
		if (empty($locationId)) {
			$returnArray['error_message'] = "This order is not a pickup.";
			return $returnArray;
		}
		$ignoreInventoryForPickup = getPreference("IGNORE_INVENTORY_FOR_PICKUP");
		$substitutions = array_merge($contactRow, $orderRow);
		$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
		$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
		$substitutions['shipping_method'] = $shippingMethodRow['description'];
		$locationRow = getRowFromId("locations", "location_id", $shippingMethodRow['location_id']);
		$substitutions['location'] = $locationRow['description'];
		$substitutions['location_address_block'] = getAddressBlock(Contact::getContact($locationRow['contact_id']));

		$pickupOrderItems = "";
		$pickupOrderItemsTable = "";
		$pickupOrderItemsTableHeader = "<table id='pickup_items_table'><tr><th class='product-code-header'>Product Code</th><th class='description-header'>Description</th><th class='quantity-header'>Quantity</th></tr>";
		$shippedOrderItems = "";
		$shippedOrderItemsTable = "";
		$shippedOrderItemsTableHeader = "<table id='shipped_items_table'><tr><th class='product-code-header'>Product Code</th><th class='description-header'>Description</th><th class='quantity-header'>Quantity</th></tr>";
		$waitingOrderItems = "";
		$waitingOrderItemsTable = "";
		$waitingOrderItemsTableHeader = "<table id='waiting_items_table'><tr><th class='product-code-header'>Product Code</th><th class='description-header'>Description</th><th class='quantity-header'>Quantity</th></tr>";
		$totalPickupQuantity = 0;
		$totalWaitingQuantity = 0;
		$totalShippedQuantity = 0;
		$totalItems = 0;
		$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $orderId);
		while ($thisItem = getNextRow($resultSet)) {
			$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
			$inventoryQuantity = (empty($ignoreInventoryForPickup) ? getFieldFromId("quantity", "product_inventories", "product_id",
				$productRow['product_id'], "location_id = ?", $locationId) ?: 0 : 999999);
			$shippedQuantity = 0;
			$shipSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and " .
				"order_shipment_id in (select order_shipment_id from order_shipments where location_id is not null and " .
				"location_id not in (select location_id from locations where inactive = 0 and product_distributor_id is not null))", $thisItem['order_item_id']);
			if ($shipRow = getNextRow($shipSet)) {
				if (!empty($shipRow['sum(quantity)'])) {
					$shippedQuantity = $shipRow['sum(quantity)'];
				}
			}
			$totalItems += $thisItem['quantity'];
			$pickupQuantity = min($inventoryQuantity, $thisItem['quantity'] - $shippedQuantity);
			$waitingQuantity = $thisItem['quantity'] - $pickupQuantity;
			$totalPickupQuantity += $pickupQuantity;
			$totalWaitingQuantity += $waitingQuantity;
			$totalShippedQuantity += $shippedQuantity;

			if ($pickupQuantity > 0) {
				$pickupOrderItems .= "<div class='order-item-line'><span class='product-code'>" . $productRow['product_code'] . "</span>" .
					"<span class='product-description'>" . $productRow['description'] . "</span>" .
					"<span class='product-quantity'>" . $pickupQuantity . "</span>" .
					"</div>";
				$pickupOrderItemsTable .= "<tr class='order-item-row'><td class='product-code'>" . $productRow['product_code'] . "</td>" .
					"<td class='product-description'>" . $productRow['description'] . "</td>" .
					"<td class='align-right product-quantity'>" . $pickupQuantity . "</td></tr>";
			}
			if ($shippedQuantity > 0) {
				$shippedOrderItems .= "<div class='order-item-line'><span class='product-code'>" . $productRow['product_code'] . "</span>" .
					"<span class='product-description'>" . $productRow['description'] . "</span>" .
					"<span class='product-quantity'>" . $shippedQuantity . "</span>" .
					"</div>";
				$shippedOrderItemsTable .= "<tr class='order-item-row'><td class='product-code'>" . $productRow['product_code'] . "</td>" .
					"<td class='product-description'>" . $productRow['description'] . "</td>" .
					"<td class='align-right product-quantity'>" . $shippedQuantity . "</td></tr>";
			}
			if ($waitingQuantity > 0) {
				$waitingOrderItems .= "<div class='order-item-line'><span class='product-code'>" . $productRow['product_code'] . "</span>" .
					"<span class='product-description'>" . $productRow['description'] . "</span>" .
					"<span class='product-quantity'>" . $waitingQuantity . "</span>" .
					"</div>";
				$waitingOrderItemsTable .= "<tr class='order-item-row'><td class='product-code'>" . $productRow['product_code'] . "</td>" .
					"<td class='product-description'>" . $productRow['description'] . "</td>" .
					"<td class='align-right product-quantity'>" . $waitingQuantity . "</td></tr>";
			}
		}
		if (!empty($pickupOrderItemsTable)) {
			$pickupOrderItemsTable = $pickupOrderItemsTableHeader . $pickupOrderItemsTable . "</table>";
		}
		if (!empty($shippedOrderItemsTable)) {
			$shippedOrderItemsTable = $shippedOrderItemsTableHeader . $shippedOrderItemsTable . "</table>";
		}
		if (!empty($waitingOrderItemsTable)) {
			$waitingOrderItemsTable = $waitingOrderItemsTableHeader . $waitingOrderItemsTable . "</table>";
		}
		$substitutions['pickup_order_items'] = $pickupOrderItems;
		$substitutions['pickup_order_items_table'] = $pickupOrderItemsTable;
		$substitutions['shipped_order_items'] = ($totalShippedQuantity > 0 ? "<p><strong>Items Already Shipped/Picked Up</strong></p>" : "") . $shippedOrderItems;
		$substitutions['shipped_order_items_table'] = ($totalShippedQuantity > 0 ? "<p><strong>Items Already Shipped/Picked Up</strong></p>" : "") . $shippedOrderItemsTable;
		$substitutions['waiting_order_items'] = ($totalWaitingQuantity > 0 ? " <p><strong>Items Still Pending</strong></p>" : "") . $waitingOrderItems;
		$substitutions['waiting_order_items_table'] = ($totalWaitingQuantity > 0 ? " <p><strong>Items Still Pending</strong></p>" : "") . $waitingOrderItemsTable;
		$substitutions['total_pickup_quantity'] = $totalPickupQuantity;
		if ($totalPickupQuantity == $totalItems) {
			$substitutions['order_item_text'] = "Your Order";
		} elseif ($totalWaitingQuantity > 0) {
			$substitutions['order_item_text'] = "Part of your Order";
		} else {
			$substitutions['order_item_text'] = "The remainder of your Order";
		}

		if ($totalPickupQuantity <= 0) {
			$returnArray['error_message'] = "None of the items in this order are in stock and ready to be picked up.";
			return $returnArray;
		}

		$returnArray = array_merge($returnArray, self::capturePayment($orderId));
		if (array_key_exists('error_message', $returnArray)) {
			return $returnArray;
		}
		if ($setStatus) {
			$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "READY_FOR_PICKUP");
			if (!empty($orderStatusId)) {
				Order::updateOrderStatus($orderId, $orderStatusId);
				$returnArray['order_status_id'] = $orderStatusId;
			}
		}

		$emailId = getFieldFromId("email_id", "emails", "email_code", "READY_FOR_PICKUP", "inactive = 0");
		$subject = "";
		$body = "";
		if (empty($emailId)) {
			$subject = "Your order is ready for pickup";
			$body = "<p>Thank you for your order! %order_item_text% is ready for pickup.</p><p><strong>Items Ready for Pickup</strong></p>%pickup_order_items_table%\n" .
				"%shipped_order_items_table%\n%waiting_order_items_table%\n";
		}
		sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "email_address" => $contactRow['email_address'], "substitutions" => $substitutions, "contact_id" => $contactRow['contact_id']));
		$returnArray['info_message'] = "Ready for Pickup notification email sent to " . $contactRow['email_address'];
		return $returnArray;
	}

	public static function markOrderPickedUp($orderId) {
		$returnArray = array();
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$locationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id'], "pickup = 1");
		$ignoreInventoryForPickup = getPreference("IGNORE_INVENTORY_FOR_PICKUP");

		if (empty($locationId)) {
			$returnArray['error_message'] = "This order is not a pickup.";
			return $returnArray;
		}

		$locationRow = getRowFromId("locations", "location_id", $locationId);
		$substitutions = array_merge($contactRow, $orderRow);
		$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
		$substitutions['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
		$substitutions['location'] = getFieldFromId("description", "locations", "location_id", getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']));

		$pickupOrderItems = "";
		$pickupOrderItemsTable = "<table id='pickup_items_table'><tr><th class='product-code-header'>Product Code</th><th class='description-header'>Description</th><th class='quantity-header'>Quantity</th></tr>";

		$totalWaitingQuantity = 0;
		$shipmentOrderItems = array();
		$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $orderId);
		while ($thisItem = getNextRow($resultSet)) {
			$inventoryQuantity = (empty($ignoreInventoryForPickup) ? getFieldFromId("quantity", "product_inventories", "product_id", $thisItem['product_id'], "location_id = ?", $locationId) : 999999);
			$shippedQuantity = 0;
			$shipSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and " .
				"order_shipment_id in (select order_shipment_id from order_shipments where location_id is not null and " .
				"location_id not in (select location_id from locations where inactive = 0 and product_distributor_id is not null))", $thisItem['order_item_id']);
			if ($shipRow = getNextRow($shipSet)) {
				$shippedQuantity = $shipRow['sum(quantity)'];
			}
			$pickupQuantity = max($inventoryQuantity, $thisItem['quantity'] - $shippedQuantity);
			$waitingQuantity = $thisItem['quantity'] - $pickupQuantity;
			$totalWaitingQuantity += $waitingQuantity;
			if ($pickupQuantity <= 0) {
				continue;
			}
			$shipmentOrderItems[] = array("order_item_id" => $thisItem['order_item_id'], "product_id" => $thisItem['product_id'], "quantity" => $thisItem['quantity']);

			$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);

			$pickupOrderItems .= "<div class='order-item-line'><span class='product-code'>" . $productRow['product_code'] . "</span>" .
				"<span class='product-description'>" . $productRow['description'] . "</span>" .
				"<span class='product-quantity'>" . $pickupQuantity . "</span>" .
				"</div>";
			$pickupOrderItemsTable .= "<tr class='order-item-row'><td class='product-code'>" . $productRow['product_code'] . "</td>" .
				"<td class='product-description'>" . $productRow['description'] . "</td>" .
				"<td class='align-right product-quantity'>" . $pickupQuantity . "</td></tr>";
		}
		$pickupOrderItemsTable .= "</table>";
		$substitutions['pickup_order_items'] = $pickupOrderItems;
		$substitutions['pickup_order_items_table'] = $pickupOrderItemsTable;

		if (empty($shipmentOrderItems)) {
			$returnArray['error_message'] = "None of the items in this order are in stock and ready to be picked up.";
			return $returnArray;
		}

		$orderShipmentsDataTable = new DataTable("order_shipments");
		$orderShipmentId = $orderShipmentsDataTable->saveRecord(array("name_values" => array("order_id" => $orderId, "location_id" => $locationId, "date_shipped" => date("m/d/Y"))));

		$orderItemCount = 0;
		foreach ($shipmentOrderItems as $thisOrderItem) {
			$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $locationId);
			executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
				$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
			$orderItemCount++;

			# add to product inventory log

			if (empty($locationRow['ignore_inventory'])) {
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

		if ($totalWaitingQuantity > 0) {
			$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "PICKUP_DONE_SHIPMENT_REQUIRED");
			if (!empty($orderStatusId)) {
				Order::updateOrderStatus($orderId, $orderStatusId);
				$returnArray['order_status_id'] = $orderStatusId;
			}
		} else {
			$orderId = getFieldFromId("order_id", "orders", "order_id", $orderId, "date_completed is null");
			if (empty($orderId)) {
				return $returnArray;
			}
			Order::markOrderCompleted($orderId);
		}

		$emailId = getFieldFromId("email_id", "emails", "email_code", "PICKUP_DONE", "inactive = 0");
		if (!empty($emailId)) {
			sendEmail(array("email_id" => $emailId, "email_address" => $contactRow['email_address'], "substitutions" => $substitutions, "contact_id" => $contactRow['contact_id']));
			$returnArray['info_message'] = "Pickup confirmation email sent to " . $contactRow['email_address'];
		} else {
			$returnArray['info_message'] = "Pickup complete.";
		}
		return $returnArray;
	}

	public static function markOrderCompleted($orderId, $dateCompleted = "", $notifyCorestore = true) {
		$dateCompleted = $dateCompleted ?: date("Y-m-d");
		if (!updateFieldById("date_completed", $dateCompleted, "orders", "order_id", $orderId, "date_completed is null")) {
			return false;
		}
		$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", getPreference("COMPLETED_ORDER_STATUS_ID"));
		if (!empty($orderStatusId)) {
			self::updateOrderStatus($orderId, $orderStatusId);
		}
		$productsSet = executeQuery("select product_id from order_items where order_id = ?", $orderId);
		while ($productRow = getNextRow($productsSet)) {
			removeCachedData("product_waiting_quantity", $productRow['product_id']);
		}
		if ($notifyCorestore) {
			coreSTORE::orderNotification($orderId, "mark_completed");
		}
		self::notifyCRM($orderId, "mark_completed");
		Order::updateGunbrokerOrder($orderId);
		return true;
	}

	public static function capturePayment($orderId) {
		$returnArray = array();
		$resultSet = executeQuery("select * from order_payments where order_id = ? and not_captured = 1 and deleted = 0", $orderId);
		while ($orderPaymentRow = getNextRow($resultSet)) {
			$orderPaymentId = $orderPaymentRow['order_payment_id'];
			$orderPaymentRow = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
			$accountRow = getRowFromId("accounts", "account_id", $orderPaymentRow['account_id']);
			$merchantAccountId = $accountRow['merchant_account_id'] ?: $GLOBALS['gMerchantAccountId'];
			$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
			if (!$eCommerce) {
				$returnArray['error_message'] = "Unable to connect to Merchant Gateway to capture payment. Go to details page for further processing.";
				return $returnArray;
			}
			$success = $eCommerce->captureCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier'], "authorization_code" => $orderPaymentRow['authorization_code']));
			$response = $eCommerce->getResponse();
			if ($success) {
				executeQuery("update order_payments set payment_time = now(),transaction_identifier = ?,not_captured = 0 where order_payment_id = ?", $response['transaction_id'], $orderPaymentId);
				$returnArray['transaction_identifier'] = $response['transaction_id'];
			} else {
				$paymentAmount = $orderPaymentRow['amount'] + $orderPaymentRow['shipping_charge'] + $orderPaymentRow['tax_charge'] + $orderPaymentRow['handling_charge'];
				$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $paymentAmount, "order_number" => $orderId,
					"merchant_identifier" => $accountRow['merchant_identifier'], "account_token" => $accountRow['account_token'], "address_id" => $accountRow['address_id']));
				$response = $eCommerce->getResponse();
				if ($success) {
					executeQuery("update order_payments set payment_time = now(),transaction_identifier = ?,not_captured = 0 where order_payment_id = ?", $response['transaction_id'], $orderPaymentId);
					$returnArray['transaction_identifier'] = $response['transaction_id'];
				} else {
					$returnArray['error_message'] = "Payment transaction unable to be captured. Go to details page for further processing.";
					return $returnArray;
				}
			}
		}
		return $returnArray;
	}


	public static function getOrderDirectiveConditions() {
		$conditions = array();
		$conditions[] = array("description" => "Contains product type", "condition_code" => "PRODUCT_TYPE", "select_table" => "product_types", "data_type" => "select");
		$conditions[] = array("description" => "Does NOT contain product type", "condition_code" => "NOT_PRODUCT_TYPE", "select_table" => "product_types", "data_type" => "select");
		$conditions[] = array("description" => "Contains product tagged as", "condition_code" => "TAGGED", "select_table" => "product_tags", "data_type" => "select");
		$conditions[] = array("description" => "Does NOT contain product tagged as", "condition_code" => "NOT_TAGGED", "select_table" => "product_tags", "data_type" => "select");
		$conditions[] = array("description" => "Contains product in category", "condition_code" => "CATEGORY", "select_table" => "product_categories", "data_type" => "select");
		$conditions[] = array("description" => "Does NOT contain product in category", "condition_code" => "NOT_CATEGORY", "select_table" => "product_categories", "data_type" => "select");
		$conditions[] = array("description" => "Contains product in department", "condition_code" => "DEPARTMENT", "select_table" => "product_departments", "data_type" => "select");
		$conditions[] = array("description" => "Contains ONLY products in department", "condition_code" => "ONLY_DEPARTMENT", "select_table" => "product_departments", "data_type" => "select");
		$conditions[] = array("description" => "Does NOT contain product in department", "condition_code" => "NOT_DEPARTMENT", "select_table" => "product_departments", "data_type" => "select");
		$conditions[] = array("description" => "Does NOT contain product available in local inventory", "condition_code" => "NO_LOCAL_INVENTORY", "data_type" => "tinyint");
		$conditions[] = array("description" => "ALL products are available in local inventory", "condition_code" => "ALL_LOCAL_INVENTORY", "data_type" => "tinyint");
		$conditions[] = array("description" => "Shipping Method is", "condition_code" => "SHIPPING_METHOD", "select_table" => "shipping_methods", "data_type" => "select");
		$conditions[] = array("description" => "Uses Payment Method", "condition_code" => "PAYMENT_METHOD", "select_table" => "payment_methods", "data_type" => "select");
		$conditions[] = array("description" => "Uses Payment Method Type", "condition_code" => "PAYMENT_METHOD_TYPE", "select_table" => "payment_method_types", "data_type" => "select");
		$conditions[] = array("description" => "Does NOT use Payment Method Type", "condition_code" => "NOT_PAYMENT_METHOD_TYPE", "select_table" => "payment_method_types", "data_type" => "select");
		$conditions[] = array("description" => "Order Total is Over", "condition_code" => "TOTAL_OVER", "data_type" => "decimal");
		$conditions[] = array("description" => "Order Total is Under", "condition_code" => "TOTAL_UNDER", "data_type" => "decimal");
		$conditions[] = array("description" => "Number of items is over", "condition_code" => "ITEMS_OVER", "data_type" => "int");
		$conditions[] = array("description" => "Number of items is under", "condition_code" => "ITEMS_UNDER", "data_type" => "int");
		$conditions[] = array("description" => "Shipping address is same as billing address or order is for pickup", "condition_code" => "SAME_ADDRESS", "data_type" => "tinyint");
		$conditions[] = array("description" => "Order location within X miles of shipping address", "condition_code" => "PROXIMITY_UNDER", "data_type" => "int");
		$conditions[] = array("description" => "Order location more than X miles from shipping address", "condition_code" => "PROXIMITY_OVER", "data_type" => "int");
		$conditions[] = array("description" => "All items available from", "condition_code" => "AVAILABLE_FROM", "select_table" => "locations", "data_type" => "select");
		$conditions[] = array("description" => "Customer has X or more previous orders", "condition_code" => "PREVIOUS_ORDERS", "data_type" => "int");
		$conditions[] = array("description" => "Shipping address is in state", "condition_code" => "IN_STATE", "data_type" => "varchar", "help_label" => "enter a comma separated list of US state codes");
		$conditions[] = array("description" => "Shipping address is NOT in state", "condition_code" => "NOT_IN_STATE", "data_type" => "varchar", "help_label" => "enter a comma separated list of US state codes");
		$conditions[] = array("description" => "FFL is selected and license file exists", "condition_code" => "FFL_EXISTS", "data_type" => "tinyint");
		return $conditions;
	}

	public static function getOrderDirectiveActions() {
		$actions = array();
		$actions[] = array("description" => "Set order status", "action_code" => "SET_STATUS", "select_table" => "order_status", "data_type" => "select");
		$actions[] = array("description" => "Ship Available Items from Location", "action_code" => "SHIP_LOCATION", "select_table" => "locations", "data_type" => "select", "help_label" => "shipment will be created for this location for any items available");
        if ($GLOBALS['gUserRow']['superuser_flag']) {
	        $actions[] = array("description" => "Ship Available Items from up to two Distributors", "action_code" => "SHIP_TWO_DISTRIBUTORS", "select_table" => "locations", "data_type" => "select", "help_label" => "shipment will be created from up to two distributors for whatever items are available");
        }
		$actions[] = array("description" => "Ship All Items from Lowest Cost Distributor", "action_code" => "SHIP_CHEAPEST", "data_type" => "tinyint", "help_label" => "shipment will be created for a distributor location with the lowest cost and having all items");
		$actions[] = array("description" => "Send Notification", "action_code" => "SEND_EMAIL", "data_type" => "varchar", "data_format" => "email", "help_label" => "A standard email will be sent to this email address");
		$actions[] = array("description" => "Send Customer Email", "action_code" => "SEND_CUSTOMER_EMAIL", "data_type" => "select", "select_table" => "emails", "help_label" => "The selected email will be sent to the customer");
		return $actions;
	}

	/**
	 * @param $orderId
	 * @return false|mixed|string
	 */
	public static function getOrderReceipt($orderId) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		if (empty($orderRow['address_id'])) {
			$addressRow = array();
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
		}
		$orderItems = array();
		$resultSet = executeQuery("select *,order_items.description as order_item_description,(select group_concat(serial_number separator ', ') from order_item_serial_numbers where " .
			"order_item_id = order_items.order_item_id) as serial_numbers from order_items join products using (product_id) left outer join product_data using (product_id) where order_id = ? and order_items.deleted = 0", $orderId);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['order_item_description'])) {
				$row['description'] = $row['order_item_description'];
			}
			$row['product_addons'] = array();
			$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $row['order_item_id']);
			while ($addonRow = getNextRow($addonSet)) {
				$row['product_addons'][] = $addonRow;
			}
			$row['custom_fields'] = array();
			$addonSet = executeQuery("select description,custom_field_id,integer_data,number_data,text_data,date_data,(select control_value from custom_field_controls where " .
				"custom_field_id = custom_fields.custom_field_id and control_name = 'data_type') data_type from custom_field_data join custom_fields using (custom_field_id) where " .
				"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS') and primary_identifier = ?", $row['order_item_id']);
			while ($addonRow = getNextRow($addonSet)) {
				switch ($addonRow['data_type']) {
					case "int":
						$addonRow['data_value'] = $addonRow['integer_data'];
						break;
					case "decimal":
						$addonRow['data_value'] = $addonRow['number_data'];
						break;
					case "date":
						$addonRow['data_value'] = $addonRow['date_data'];
						break;
					default:
						if (startsWith($addonRow['text_data'], "[")) {
							$addonRow['data_value'] = "";
							$dataArray = json_decode($addonRow['text_data'], true);
							foreach ($dataArray as $thisRow) {
								foreach ($thisRow as $fieldName => $fieldData) {
									$addonRow['data_value'] .= "<br>" . snakeCaseToDescription($fieldName) . ": " . $fieldData;
								}
							}
						} else {
							$addonRow['data_value'] = $addonRow['text_data'];
						}
						break;
				}
				$row['custom_fields'][] = $addonRow;
			}
			$orderItems[] = $row;
		}
		$receiptFragment = getFragment("RETAIL_STORE_ORDER_RECEIPT");
		$headerImageId = getFieldFromId("image_id", "images", "image_code", "RECEIPT_HEADER_LOGO");
		if (empty($headerImageId)) {
			$headerImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO");
		}
		if (empty($receiptFragment)) {
			ob_start();
			?>
            <div id="_receipt_wrapper">
                <p><img class="header-image" alt='header logo' src="/getimage.php?id=%header_image_id%"></p>
                <p>%store_name%<br>%store_address%</p>
                %receipt_header_text%
                <h2>Receipt</h2>
                <p>Order Number: %order_number%<br>
                    Order Date: %order_date%</p>

                <table id="address_section">
                    <tr>
                        <td id="contact_section">
                            <h3>Ordered By</h3>
                            %contact_id%<br>
                            %contact_name%<br>
                            %contact_address%<br>
                            <br>
                            %if_has_value:purchase_order_number%
                            PO #: %purchase_order_number%<br>
                            %endif%
                            Payment by: %payment_method%
                        </td>
                        <td id="shipping_section">
                            <h3>Shipping Address</h3>
                            %full_name%<br>
                            %shipping_address%<br>
                            <br>
                            Shipping Method: %shipping_method%
                        </td>
                        <td>
                            %ffl_dealer%
                        </td>
                    </tr>
                </table>

                <h3>Order Items</h3>

                %order_items_table%

                %receipt_signature_text%
                <p id="signature">%signature%</p>
                %print_notes%

                %receipt_footer_text%
            </div>
			<?php
			$receiptFragment = ob_get_clean();
		}
		$substitutions = $orderRow;
		$substitutions['print_notes'] = "";
		$resultSet = executeQuery("select * from order_notes where public_access = 1 and order_id = ?", $orderRow['order_id']);
		while ($row = getNextRow($resultSet)) {
			$substitutions['print_notes'] .= (empty($substitutions['print_notes']) ? "" : "\n") . $row['content'];
		}
		$substitutions['receipt_signature_text'] = makeHtml(getFragment("RETAIL_STORE_RECEIPT_SIGNATURE_TEXT"));
		$substitutions['print_notes'] = makeHtml($substitutions['print_notes']);
		$substitutions['header_image_id'] = $headerImageId;
		$substitutions['store_name'] = $GLOBALS['gClientName'];
		$substitutions['store_address'] = $GLOBALS['gClientRow']['address_1'] . "<br>" . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . " " . $GLOBALS['gClientRow']['postal_code'];
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $GLOBALS['gClientRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$substitutions['store_address'] .= "<br>" . $row['phone_number'] . " " . $row['description'];
		}
		if (!empty($GLOBALS['gClientRow']['email_address'])) {
			$substitutions['store_address'] .= "<br>" . $GLOBALS['gClientRow']['email_address'];
		}
		$substitutions['receipt_header_text'] = makeHtml(getFragment("RETAIL_STORE_RECEIPT_HEADER"));
		$substitutions['receipt_footer_text'] = makeHtml(getFragment("RETAIL_STORE_RECEIPT_FOOTER"));
		$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
		$substitutions['contact_name'] = getDisplayName($orderRow['contact_id']);
		$substitutions['contact_address'] = getAddressBlock($contactRow);
		if (!empty($orderRow['phone_number'])) {
			$substitutions['contact_address'] .= "<br>" . $orderRow['phone_number'];
		}
		$billingAddressRow = $contactRow;
		$resultSet = executeQuery("select * from order_payments left outer join accounts using (account_id) where order_id = ? and deleted = 0", $orderRow['order_id']);
		$substitutions['payment_method'] = "";
		$substitutions['payment_table'] = "";
		if ($resultSet['row_count'] > 1) {
			$substitutions['payment_method'] = "Multiple methods";
			$substitutions['billing_address_block'] = "Multiple";
			$paymentTable = '<table class="grid-table" id="order_payments_table"><tr><th>Payment Method</th><th>Label</th><th class="align-right">Amount</th><th>Billing Address</th></tr>';
			while ($row = getNextRow($resultSet)) {
				$paymentMethod = ($row['invoice_id'] ? "Invoice Number " . $row['invoice_id'] : getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']));
				$paymentAddressRow = (empty($row['address_id']) ? $billingAddressRow : getRowFromId("addresses", "address_id", $row['address_id']));
				$paymentTable .= sprintf('<tr><td>%s</td><td>%s</td><td class="align-right">%s</td><td>%s</td></tr>',
					$paymentMethod,
					$row['account_label'],
					number_format($row['amount'], 2),
					str_replace("<br>", " ", getAddressBlock($paymentAddressRow)));
			}
			$paymentTable .= "</table>";
			$substitutions['payment_table'] = $paymentTable;
		} else {
			if ($row = getNextRow($resultSet)) {
				if (!empty($row['account_id'])) {
					$substitutions['payment_method'] = getFieldFromId("account_label", "accounts", "account_id", $row['account_id']);
				}
				if (!empty($row['address_id'])) {
					$billingAddressRow = getRowFromId("addresses", "address_id", $row['address_id']);
				}
				if (empty($row['payment_method'])) {
					$substitutions['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
				}
			}
			$substitutions['billing_address_block'] = getAddressBlock($billingAddressRow);
		}

		$noShippingRequired = getPreference("RETAIL_STORE_NO_SHIPPING");
		if ($noShippingRequired) {
			$substitutions['shipping_address'] = "";
			$substitutions['full_name'] = "";
			$substitutions['shipping_method'] = "Pickup";
		} else {
			if (strlen($substitutions['full_name']) > 20) {
				$substitutions['full_name'] = str_replace(", ", "<br>", $substitutions['full_name']);
			}
			if (empty($addressRow)) {
				$substitutions['shipping_address'] = getAddressBlock($contactRow);
			} else {
				$substitutions['shipping_address'] = getAddressBlock($addressRow);
			}
			if (!empty($orderRow['attention_line'])) {
				$substitutions['shipping_address'] = $orderRow['attention_line'] . "<br>" . $substitutions['shipping_address'];
			}
			$substitutions['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);

			$shippingMethodLocationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
			$substitutions['location'] = getFieldFromId("description", "locations", "location_id", $shippingMethodLocationId);

			$locationRow = getRowFromId("locations", "location_id", $shippingMethodLocationId);
			$locationContactRow = Contact::getContact($locationRow['contact_id']);
			$substitutions['pickup_location_phone_number'] = Contact::getContactPhoneNumber($locationRow['contact_id']);
			$substitutions['pickup_location_name'] = $locationContactRow['business_name'];
			$substitutions['pickup_location_address'] = $locationContactRow['address_1'] . ", " . $locationContactRow['city'] . ", " . $locationContactRow['state'] . " " . $locationContactRow['postal_code'];
		}
		$substitutions['ffl_dealer'] = "";
		if (!empty($orderRow['federal_firearms_licensee_id'])) {
			$substitutions['ffl_dealer'] = "<h3>FFL Dealer Address</h3>";
			$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
			$substitutions['ffl_dealer'] .= $fflRow['license_number'] . "<br>";
			$substitutions['ffl_dealer'] .= (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "<br>";
			$substitutions['ffl_dealer'] .= $fflRow['address_1'] . "<br>" . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . "<br>") . $fflRow['city'] . ", " . $fflRow['state'] . " " . substr($fflRow['postal_code'], 0, 5);
			$substitutions['ffl_dealer'] .= (empty($fflRow['phone_number']) ? "" : "<br>" . $fflRow['phone_number']);
		}
		$listPricingStructureId = empty(getPreference("HIDE_LIST_PRICE_IN_RECEIPT")) && getFieldFromId("pricing_structure_id", "pricing_structures", "inactive", "0", "price_calculation_type_id = (select price_calculation_type_id from price_calculation_types where price_calculation_type_code = 'DISCOUNT')");
        $hideHandlingCharge = !empty(getPreference("HIDE_HANDLING_CHARGE_IN_RECEIPT"));
		ob_start();
		?>
        <table class="grid-table" id="order_items_table">
            <tr>
                <th class="receipt-product-header">Product</th>
                <th class="receipt-qty-header">Qty</th>
				<?php if (!empty($listPricingStructureId)) { ?>
                    <th class="receipt-list-header">List</th>
                    <th class="receipt-disc-header">Disc</th>
				<?php } ?>
                <th class="receipt-price-header">Price</th>
                <th class="receipt-extend-header">Extend</th>
            </tr>
			<?php
			$wordWrapChars = getPageTextChunk("WORD_WRAP_CHARS");
            if (!is_numeric($wordWrapChars)) {
                $wordWrapChars = 85;
            }
            $currencyParameters = array();
            $currencyCode = getPageTextChunk("CURRENCY_CODE");
            if(!empty($currencyCode)) {
                $currencyParameters['currency'] = $currencyCode;
            }
            $hideProductAddons = getPageTextChunk("HIDE_PRODUCT_ADDONS");
            $hideProductAddons = !empty($hideProductAddons) && strtolower($hideProductAddons) !== 'false';

			$orderTotal = 0;
			$donationRow = array("amount" => 0);
			if (!empty($orderRow['donation_id'])) {
				$donationRow = getRowFromId("donations", "donation_id", $orderRow['donation_id']);
			}
			$itemsArray = array();
			$packArray = array();
			foreach ($orderItems as $row) {
				if (empty($row['pack_product_id'])) {
					$itemsArray[] = $row;
					continue;
				}
				$showAsPack = CustomField::getCustomFieldData($row['pack_product_id'], "SHOW_AS_PACK", "PRODUCTS");
				if (empty($showAsPack)) {
					$itemsArray[] = $row;
					continue;
				}
				if (!array_key_exists($row['pack_product_id'], $packArray)) {
					$packArray[$row['pack_product_id']] = array("description" => $row['description'], "quantity" => $row['pack_quantity'], "product_id" => $row['pack_product_id'], "sale_price" => $row['sale_price']);
				} else {
					$packArray[$row['pack_product_id']]['sale_price'] += ($row['quantity'] * $row['sale_price']);
				}
			}
			$itemsArray = array_merge($itemsArray, $packArray);
            $totalSavings = 0.00;
			foreach ($itemsArray as $itemRow) {
				$orderTotal += $itemRow['sale_price'] * $itemRow['quantity'];
				$productAddons = "";
                if(!$hideProductAddons) {
                    foreach ($itemRow['product_addons'] as $thisAddon) {
                        $productAddons .= "<br>Add on: " . htmlText($thisAddon['description']) . ($thisAddon['quantity'] <= 1 ? "" : " (Qty: " . $thisAddon['quantity'] . ")");
                    }
                    foreach ($itemRow['custom_fields'] as $thisAddon) {
                        $productAddons .= "<br>" . $thisAddon['description'] . ": " . $thisAddon['data_value'];
                    }
                }
				?>
                <tr>
                    <td class="receipt-product"><?= htmlText($itemRow['description']) . $productAddons ?><?= (empty(getPreference("RETAIL_STORE_INCLUDE_PRODUCT_CODE")) ? "" : "<br>Product Code: " . htmlText($itemRow['product_code'])) ?><?= (empty($itemRow['upc_code']) ? "" : "<br>UPC: " . htmlText($itemRow['upc_code'])) ?><?= (empty($itemRow['serial_numbers']) ? "" : "<br>Serial Number: " . wordwrap(htmlText($itemRow['serial_numbers']), $wordWrapChars, "<br>")) ?></td>
                    <td class="align-right receipt-qty"><?= $itemRow['quantity'] ?></td>
					<?php if (!empty($listPricingStructureId)) {
                        if(!empty(floatval($itemRow['list_price'])) && $itemRow['list_price'] > $itemRow['sale_price']) {
                            $totalSavings += floatval($itemRow['list_price']) - $itemRow['sale_price'];
                        }
                        ?>
                        <td class="align-right receipt-list"><?= (empty(floatval($itemRow['list_price'])) || $itemRow['list_price'] < $itemRow['sale_price'] ? "" : currencyFormat($itemRow['list_price'], $currencyParameters)) ?></td>
                        <td class="align-right receipt-disc"><?= (empty(floatval($itemRow['list_price'])) || $itemRow['list_price'] < $itemRow['sale_price'] ? "" : number_format((1 - ($itemRow['sale_price'] / $itemRow['list_price'])) * 100, 2)) ?>
                            %
                        </td>
					<?php } ?>
                    <td class="align-right receipt-price"><?= currencyFormat($itemRow['sale_price'], $currencyParameters) ?></td>
                    <td class="align-right receipt-extend"><?= currencyFormat($itemRow['sale_price'] * $itemRow['quantity'], $currencyParameters) ?></td>
                </tr>
				<?php
			}
			?>
            <tr>
                <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">
                    Subtotal
                </td>
                <td class="total-line align-right"><?= currencyFormat($orderTotal, $currencyParameters) ?></td>
            </tr>
			<?php if ($orderRow['tax_charge'] > 0) { ?>
                <tr>
                    <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">Tax</td>
                    <td class="total-line align-right"><?= currencyFormat($orderRow['tax_charge'], $currencyParameters) ?></td>
                </tr>
			<?php } ?>
            <tr>
                <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">
                    Shipping<?= (!$hideHandlingCharge && $orderRow['handling_charge'] == 0 ? "/Handling" : "") ?></td>
                <td class="total-line align-right"><?= currencyFormat($orderRow['shipping_charge'], $currencyParameters) ?></td>
            </tr>
			<?php if (!$hideHandlingCharge && $orderRow['handling_charge'] > 0) { ?>
                <tr>
                    <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">
                        Handling
                    </td>
                    <td class="total-line align-right"><?= currencyFormat($orderRow['handling_charge'], $currencyParameters) ?></td>
                </tr>
			<?php } ?>
			<?php if (!empty($orderRow['donation_id'])) { ?>
                <tr>
                    <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">
                        Donation
                        for <?= getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']) ?></td>
                    <td class="total-line align-right"><?= currencyFormat($donationRow['amount'], $currencyParameters) ?></td>
                </tr>
			<?php } ?>
			<?php if (!empty($orderRow['order_discount']) && $orderRow['order_discount'] > 0) { ?>
                <tr>
                    <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">Order Discount</td>
                    <td class="total-line align-right"><?= currencyFormat($orderRow['order_discount'], $currencyParameters) ?></td>
                </tr>
			<?php } ?>
            <tr>
                <td class="total-line align-right" colspan="<?= (!empty($listPricingStructureId) ? "5" : "3") ?>">Order
                    Total
                </td>
                <td class="total-line align-right"><?= currencyFormat($orderTotal + $orderRow['tax_charge'] + $orderRow['shipping_charge'] + $orderRow['handling_charge'] + $donationRow['amount'] - $orderRow['order_discount'], $currencyParameters) ?></td>
            </tr>
        </table>
		<?php
		$substitutions['order_items_table'] = ob_get_clean();
        $substitutions['total_savings'] = number_format($totalSavings, 2);
		return PlaceHolders::massageContent($receiptFragment, $substitutions);
	}

	public static function sendReceipt($orderId) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);

		$shippingAddress = (empty($orderRow['address_id']) ? $contactRow : array_merge($contactRow, getRowFromId("addresses", "address_id", $orderRow['address_id'])));
		$substitutions = $shippingAddress;
		$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
		if (!empty($shippingAddress['address_2'])) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
		}
		$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
		if (!empty($shippingAddress['postal_code'])) {
			$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
		}
		if (!empty($shippingCityLine)) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
		}
		if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
		}
		$substitutions = array_merge($substitutions, $orderRow);

		$cartTotal = 0;
		$cartTotalQuantity = 0;
		$substitutions['domain_name'] = $domainName = getDomainName();
		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
		$fflRequired = false;
		$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $orderId);
		while ($thisItem = getNextRow($resultSet)) {
			$cartTotal += ($thisItem['quantity'] * $thisItem['sale_price']);
			$cartTotalQuantity++;
			if ($fflRequiredProductTagId) {
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisItem['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
				if (!empty($productTagLinkId)) {
					$fflRequired = true;
				}
			}
		}
		$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderId), $substitutions);

		$orderPayments = "";
		$orderPaymentsTable = "<table id='order_payments_table'><tr>" .
			"<th class='payment_method-header'>Payment Method</th>" .
			"<th class='payment-amount-header'>Amount</th></tr>";
		$resultSet = executeQuery("select * from order_payments where order_id = ? and deleted = 0", $orderId);
		while ($thisPayment = getNextRow($resultSet)) {
			$orderPayments .= "<div class='order-payment-line'><span class='payment-method'>" . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPayment['payment_method_id']) . "</span>" .
				"<span class='payment-amount'>" . number_format($thisPayment['amount'] + $thisPayment['shipping_charge'] + $thisPayment['tax_charge'] + $thisPayment['handling_charge'], 2) . "</span>" .
				"</div>";
			$orderPaymentsTable .= "<tr class='order-payment-row'><td class='payment-method'>" . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPayment['payment_method_id']) . "</td>" .
				"<td class='payment-amount'>" . number_format($thisPayment['amount'] + $thisPayment['shipping_charge'] + $thisPayment['tax_charge'] + $thisPayment['handling_charge'], 2) . "</td></tr>";
		}
		$orderPaymentsTable .= "</table>";
		$substitutions['order_payments'] = $orderPayments;
		$substitutions['order_payments_table'] = $orderPaymentsTable;

		$orderTotal = $cartTotal + $orderRow['tax_charge'] + $orderRow['shipping_charge'] + $orderRow['handling_charge'] - $orderRow['order_discount'];

		$substitutions['order_id'] = $orderId;
		if (empty($orderRow['donation_id'])) {
			$substitutions['donation_amount'] = "0.00";
			$substitutions['designation_code'] = "";
			$substitutions['designation_description'] = "";
		} else {
			$donationAmount = getFieldFromId("amount", "donations", "donation_id", $orderRow['donation_id']);
			$orderTotal += $donationAmount;
			$substitutions['donation_amount'] = number_format($donationAmount, 2);
			$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", getFieldFromId("designation_id", "donations", "donation_id", $orderRow['donation_id']));
			$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", getFieldFromId("designation_id", "donations", "donation_id", $orderRow['donation_id']));
		}
		$substitutions['order_total'] = number_format($orderTotal, 2);
		$substitutions['tax_charge'] = number_format($orderRow['tax_charge'], 2);
		$substitutions['shipping_charge'] = number_format($orderRow['shipping_charge'], 2);
		$substitutions['handling_charge'] = number_format($orderRow['handling_charge'], 2);
		$substitutions['order_discount'] = $orderRow['order_discount'];
		$substitutions['cart_total'] = number_format($cartTotal, 2);
		$substitutions['cart_total_quantity'] = $cartTotalQuantity;
		$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
		$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
		$substitutions['ffl_name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
		$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
		$substitutions['ffl_license_number'] = $fflRow['license_number'];

		$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
		$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];

		$billingAddressRow = $contactRow;
		$resultSet = executeQuery("select * from order_payments left outer join accounts using (account_id) where order_id = ? and address_id is not null", $orderRow['order_id']);
		if ($row = getNextRow($resultSet)) {
			$billingAddressRow = getRowFromId("addresses", "address_id", $row['address_id']);
		}
		$substitutions['billing_address_block'] = $billingAddressRow['address_1'];
		if (!empty($billingAddressRow['address_2'])) {
			$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . $billingAddressRow['address_2'];
		}
		$billingCityLine = $billingAddressRow['city'] . (empty($billingAddressRow['city']) || empty($billingAddressRow['state']) ? "" : ", ") . $billingAddressRow['state'];
		if (!empty($billingAddressRow['postal_code'])) {
			$billingCityLine .= (empty($billingCityLine) ? "" : " ") . $billingAddressRow['postal_code'];
		}
		if (!empty($billingCityLine)) {
			$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . $billingCityLine;
		}
		if (!empty($billingAddressRow['country_id']) && $billingAddressRow['country_id'] != 1000) {
			$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $billingAddressRow['country_id']);
		}

		$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_CONFIRMATION", "inactive = 0");
		if ($fflRequired && empty($orderRow['federal_firearms_licensee_id'])) {
			$substitutions['need_ffl_dealer'] = getFragment("RETAIL_STORE_NEED_FFL_DEALER");
		} else {
			$substitutions['need_ffl_dealer'] = "";
		}
		if (empty($shippingAddress['email_address'])) {
			$shippingAddress['email_address'] = $contactRow['email_address'];
		}
		return sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $shippingAddress['email_address']));
	}

	/**
	 * @param $orderId
	 * @return false|mixed|string
	 */
	public static function getPaperReceipt($orderId) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		if (empty($orderRow['address_id'])) {
			$addressRow = $contactRow;
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
		}
		$orderItems = array();
		$resultSet = executeQuery("select *,(select group_concat(serial_number) from order_item_serial_numbers where " .
			"order_item_id = order_items.order_item_id) as serial_numbers from order_items join products using (product_id) left outer join product_data using (product_id) where order_id = ? and order_items.deleted = 0", $orderId);
		while ($row = getNextRow($resultSet)) {
			$orderItems[] = $row;
		}
		$receiptFragment = getFragment("RETAIL_STORE_PAPER_RECEIPT");
		$headerImageId = getFieldFromId("image_id", "images", "image_code", "RECEIPT_HEADER_LOGO");
		if (empty($headerImageId)) {
			$headerImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO");
		}
		if (empty($receiptFragment)) {
			ob_start();
			?>
            <div id="_receipt_wrapper">
                <p><img class="header-image" alt='header logo' src="/getimage.php?id=%header_image_id%"></p>
                <p>%store_name%<br>%store_address%</p>
                %receipt_header_text%
                <h2>Receipt</h2>
                <p>Order Number: %order_number%<br>
                    Order Date: %order_date%</p>

                <h3>Order Items</h3>

                %order_items_table%

                %receipt_footer_text%
            </div>
			<?php
			$receiptFragment = ob_get_clean();
		}
		$substitutions = $orderRow;
		$substitutions['header_image_id'] = $headerImageId;
		$substitutions['store_name'] = $GLOBALS['gClientName'];
		$substitutions['store_address'] = $GLOBALS['gClientRow']['address_1'] . "<br>" . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . " " . $GLOBALS['gClientRow']['postal_code'];
		$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $GLOBALS['gClientRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$substitutions['store_address'] .= "<br>" . $row['phone_number'] . " " . $row['description'];
		}
		if (!empty($GLOBALS['gClientRow']['email_address'])) {
			$substitutions['store_address'] .= "<br>" . $GLOBALS['gClientRow']['email_address'];
		}
		$substitutions['receipt_header_text'] = makeHtml(getFragment("RETAIL_STORE_PAPER_RECEIPT_HEADER"));
		$substitutions['receipt_footer_text'] = makeHtml(getFragment("RETAIL_STORE_PAPER_RECEIPT_FOOTER"));
		$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
		ob_start();
		?>
        <table class="grid-table" id="order_items_table">
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Extend</th>
            </tr>
			<?php
			$orderTotal = 0;
			$donationRow = array("amount" => 0);
			if (!empty($orderRow['donation_id'])) {
				$donationRow = getRowFromId("donations", "donation_id", $orderRow['donation_id']);
			}
			$itemsArray = array();
			$packArray = array();
			foreach ($orderItems as $row) {
				if (empty($row['pack_product_id'])) {
					$itemsArray[] = $row;
					continue;
				}
				$showAsPack = CustomField::getCustomFieldData($row['pack_product_id'], "SHOW_AS_PACK", "PRODUCTS");
				if (empty($showAsPack)) {
					$itemsArray[] = $row;
					continue;
				}
				if (!array_key_exists($row['pack_product_id'], $packArray)) {
					$packArray[$row['pack_product_id']] = array("description" => $row['description'], "quantity" => $row['pack_quantity'], "product_id" => $row['pack_product_id'], "sale_price" => $row['sale_price']);
				} else {
					$packArray[$row['pack_product_id']]['sale_price'] += ($row['sale_price'] * $row['quantity']);
				}
			}
			$itemsArray = array_merge($itemsArray, $packArray);
			foreach ($itemsArray as $itemRow) {
				$serialNumbers = "";
				if (!empty($itemRow['serial_numbers'])) {
					$serialNumbersArray = explode(",", $itemRow['serial_numbers']);
					$count = 0;
					foreach ($serialNumbersArray as $thisSerialNumber) {
						$count++;
						if ($count % 5 == 0) {
							$serialNumbers .= "<br>&nbsp;&nbsp;";
						}
						$serialNumbers .= $thisSerialNumber . "&nbsp;&nbsp;&nbsp;&nbsp;";
					}
				}
				$productAddons = "";
				$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $itemRow['order_item_id']);
				while ($addonRow = getNextRow($addonSet)) {
					$productAddons .= "<br>Add on: " . htmlText($addonRow['description']) . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")");
				}
				$orderTotal += $itemRow['sale_price'] * $itemRow['quantity']
				?>
                <tr>
                    <td><?= htmlText($itemRow['description']) . $productAddons ?><?= (empty(getPreference("RETAIL_STORE_INCLUDE_PRODUCT_CODE")) ? "" : "<br>Product Code: " .
							htmlText($itemRow['product_code'])) ?><?= (empty($itemRow['upc_code']) ? "" : "<br>UPC: " . htmlText($itemRow['upc_code'])) ?><?= (empty($serialNumbers) ? "" : "<br>Serial Number: " .
							"<span id='serial_number_list'>" . $serialNumbers . "</span>") ?></td>
                    <td class="align-right"><?= $itemRow['quantity'] ?></td>
                    <td class="align-right"><?= number_format($itemRow['sale_price'], 2) ?></td>
                    <td class="align-right"><?= number_format($itemRow['sale_price'] * $itemRow['quantity'], 2) ?></td>
                </tr>
				<?php
			}
			?>
            <tr>
                <td class="total-line align-right" colspan="3">
                    Subtotal
                </td>
                <td class="total-line align-right"><?= number_format($orderTotal, 2) ?></td>
            </tr>
			<?php if ($orderRow['tax_charge'] > 0) { ?>
                <tr>
                    <td class="total-line align-right" colspan="3">Tax</td>
                    <td class="total-line align-right"><?= number_format($orderRow['tax_charge'], 2) ?></td>
                </tr>
			<?php } ?>
            <tr>
                <td class="total-line align-right" colspan="3">
                    Shipping<?= ($orderRow['handling_charge'] == 0 ? "/Handling" : "") ?></td>
                <td class="total-line align-right"><?= number_format($orderRow['shipping_charge'], 2) ?></td>
            </tr>
			<?php if ($orderRow['handling_charge'] > 0) { ?>
                <tr>
                    <td class="total-line align-right" colspan="3">
                        Handling
                    </td>
                    <td class="total-line align-right"><?= number_format($orderRow['handling_charge'], 2) ?></td>
                </tr>
			<?php } ?>
			<?php if (!empty($orderRow['donation_id'])) { ?>
                <tr>
                    <td class="total-line align-right" colspan="3">
                        Donation
                        for <?= getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']) ?></td>
                    <td class="total-line align-right"><?= number_format($donationRow['amount'], 2) ?></td>
                </tr>
			<?php } ?>
			<?php if (!empty($orderRow['order_discount']) && $orderRow['order_discount'] > 0) { ?>
                <tr>
                    <td class="total-line align-right" colspan="3">Order Discount</td>
                    <td class="total-line align-right"><?= number_format($orderRow['order_discount'], 2) ?></td>
                </tr>
			<?php } ?>
            <tr>
                <td class="total-line align-right" colspan="3">Order Total</td>
                <td class="total-line align-right"><?= number_format($orderTotal + $orderRow['tax_charge'] + $orderRow['shipping_charge'] + $orderRow['handling_charge'] + $donationRow['amount'] - $orderRow['order_discount'], 2) ?></td>
            </tr>
        </table>
		<?php
		$substitutions['order_items_table'] = ob_get_clean();
		return PlaceHolders::massageContent($receiptFragment, $substitutions);
	}

	public static function reportOrderToTaxjar($orderId) {
		if (function_exists("_localReportTax")) {
			$result = _localReportTax($orderId);
			if ($result !== false) {
				return $result;
			}
		}
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		if (empty($orderRow)) {
			return false;
		}
		$taxjarApiToken = getPreference("taxjar_api_token");
		$taxjarApiReporting = getPreference("taxjar_api_reporting");
		if (empty($taxjarApiToken) || empty($taxjarApiReporting)) {
			return false;
		}
        $useProductCode = !empty(getPreference("taxjar_use_product_code_for_line_items"));

		$client = false;
		require_once __DIR__ . '/../taxjar/vendor/autoload.php';
		try {
			$client = TaxJar\Client::withApiKey($taxjarApiToken);
			$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
		} catch (Exception $e) {
			return false;
		}

		$updateExisting = false;
		try {
			$order = $client->showOrder($orderId);
			if (!empty($order->transaction_id)) {
				$updateExisting = true;
			}
		} catch (Exception $e) {
		}

		$cartTotal = 0;
		$lineItems = array();
		$marketplaceOrder = false;
		$sourceRow = getRowFromId("sources", "source_id", $orderRow['source_id']);
		if ($sourceRow['tax_exempt']) {
			$marketplaceOrder = true;
		}
		if (!$marketplaceOrder) {
			$taxExemptId = CustomField::getCustomFieldData($orderRow['contact_id'], "TAX_EXEMPT_ID");
			if (!empty($taxExemptId)) {
				$marketplaceOrder = true;
			}
		}
		$resultSet = executeQuery("select * from order_items join products using (product_id) where order_id = ? and deleted = 0", $orderId);
		while ($orderItemRow = getNextRow($resultSet)) {
            $productIdentifier = ($useProductCode ? $orderItemRow['product_code'] : $orderItemRow['product_id']);
			$thisLineItem = array("product_identifier" => $productIdentifier, "description" => $orderItemRow['description'], "quantity" => $orderItemRow['quantity'], "unit_price" => $orderItemRow['sale_price'], "discount" => 0, "sales_tax" => $orderItemRow['tax_charge']);
			$lineItems[] = $thisLineItem;
			$cartTotal += $orderItemRow['quantity'] * $orderItemRow['sale_price'];
			$productCategoryId = getFieldFromId("product_category_id", "product_category_links", "product_id", $orderItemRow['product_id'],
				"product_category_id in (select product_category_id from product_category_group_links where " .
				"product_category_group_id in (select product_category_group_id from product_category_groups where product_category_group_code = 'MARKETPLACE'))");
			if (!empty($productCategoryId)) {
				$marketplaceOrder = true;
			}
		}
		if ($orderRow['order_discount'] > 0) {
			$totalDiscount = $orderRow['order_discount'];
			$count = 0;
			foreach ($lineItems as $index => $thisLineItem) {
				$count++;
				if ($count == count($lineItems)) {
					$lineItems[$index]['discount'] = number_format($totalDiscount, 2, ".", "");
				} else {
					$lineTotal = $thisLineItem['quantity'] * $thisLineItem['unit_price'];
					$thisDiscount = round(($lineTotal / $cartTotal) * $orderRow['order_discount'], 2);
					$lineItems[$index]['discount'] = number_format($thisDiscount, 2, ".", "");
					$totalDiscount -= $thisDiscount;
				}
			}
		}
		$address1 = $postalCode = $city = $state = $countryId = "";
		$fromAddressInfo = $GLOBALS['gClientRow'];
		$gotAddresses = false;
		$contactId = $orderRow['contact_id'];
		if (!empty($contactId)) {
			$contactRow = Contact::getContact($contactId);
			if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
				$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
			}
			$address1 = $contactRow['address_1'];
			$city = $contactRow['city'];
			$state = $contactRow['state'];
			$postalCode = $contactRow['postal_code'];
			$countryId = $contactRow['country_id'];
		}

		if (!empty($orderRow['shipping_method_id'])) {
			$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
			if ($shippingMethodRow['pickup'] && !empty($shippingMethodRow['location_id'])) {
				$locationContactId = getFieldFromId("contact_id", "locations", "location_id", $shippingMethodRow['location_id']);
				$contactRow = Contact::getContact($locationContactId);
				if (empty($contactRow['state']) || empty($contactRow['postal_code'])) {
					$contactRow = $GLOBALS['gClientRow'];
				}
				if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
				$fromAddressInfo['address_1'] = $address1;
				$fromAddressInfo['city'] = $city;
				$fromAddressInfo['state'] = $state;
				$fromAddressInfo['postal_code'] = $postalCode;
				$fromAddressInfo['country_id'] = $countryId;
				$gotAddresses = true;
			}
		}

		// Not using FFL to calculate tax; sales tax should be based on buyer's address, not transfer location
		if (!$gotAddresses && !empty(getPreference("USE_FFL_ADDRESS_FOR_TAX_CALCULATION"))) {
			if (!empty($orderRow['federal_firearms_licensee_id'])) {
				$fflContactId = (new FFL($orderRow['federal_firearms_licensee_id']))->getFieldData("contact_id");
				if (!empty($fflContactId)) {
					$contactRow = Contact::getContact($fflContactId);
					if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
						$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
					}
					$address1 = $contactRow['address_1'];
					$city = $contactRow['city'];
					$state = $contactRow['state'];
					$postalCode = $contactRow['postal_code'];
					$countryId = $contactRow['country_id'];
					$gotAddresses = true;
				}
			}
		}

		if (!$gotAddresses) {
			if (!empty($addressId)) {
				$contactRow = getRowFromId("addresses", "address_id", $addressId);
				if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
			} elseif (!empty($contactId)) {
				$contactRow = Contact::getContact($contactId);
				if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
			}
		}
		$programLogId = "";
		if (empty($postalCode) || empty($state)) {
			$programLogId = addProgramLog("Taxjar Order Reporting: Required fields missing. Using client contact.");
			$postalCode = $postalCode ?: $GLOBALS['gClientRow']['postal_code'];
			$state = $state ?: $GLOBALS['gClientRow']['state'];
		}

		$orderData = [
			'transaction_id' => $orderId,
			'transaction_date' => date("c", strtotime($orderRow['order_time'])),
			'from_country' => getFieldFromId("country_code", "countries", "country_id", $fromAddressInfo['country_id']),
			'from_zip' => $fromAddressInfo['postal_code'],
			'from_state' => $fromAddressInfo['state'],
			'from_city' => $fromAddressInfo['city'],
			'from_street' => $fromAddressInfo['address_1'],
			'to_country' => getFieldFromId("country_code", "countries", "country_id", $countryId),
			'to_zip' => $postalCode,
			'to_state' => $state,
			'to_city' => $city,
			'to_street' => $address1,
			'amount' => number_format($cartTotal + $orderRow['shipping_charge'] - $orderRow['order_discount'], 2, ".", ""),
			'shipping' => $orderRow['shipping_charge'],
			'sales_tax' => $orderRow['tax_charge'],
			'plugin' => "coreware",
			'customer_id' => $contactId
		];
		if ($marketplaceOrder) {
			$orderData['exemption_type'] = "marketplace";
		}
		$orderData['line_items'] = $lineItems;
		try {
			if($updateExisting) {
				$response = $client->updateOrder($orderData);
			} else {
				$response = $client->createOrder($orderData);
			}
			addProgramLog("Taxjar Order Reporting:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nResponse: " . jsonEncode($response), $programLogId);
		} catch (Exception $e) {
			addProgramLog("Taxjar Order Reporting Error: " . $e->getMessage() . "\n\nData: " . jsonEncode($orderData), $programLogId);
			if(startsWith($e->getMessage(),"403")) {
				sendCredentialsError(["integration_name"=>"TaxJar","error_message"=>$e->getMessage()]);
			} else {
                $GLOBALS['gPrimaryDatabase']->logError("Taxjar Order Reporting Error: " . $e->getMessage() . "\n\nData: " . jsonEncode($orderData));
            }
			return false;
		}
		return true;
	}

	public static function reportRefundToTaxjar($orderId, $orderItemIds = array(), $totalRefundAmount) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		if (!is_float($totalRefundAmount)) {
			$totalRefundAmount = floatval(str_replace(",", "", $totalRefundAmount));
		}
		if (empty($orderRow)) {
			return false;
		}
		$taxjarApiToken = getPreference("taxjar_api_token");
		$taxjarApiReporting = getPreference("taxjar_api_reporting");
		if (empty($taxjarApiToken) || empty($taxjarApiReporting)) {
			return false;
		}
        $useProductCode = !empty(getPreference("taxjar_use_product_code_for_line_items"));

		$client = false;
		require_once __DIR__ . '/../taxjar/vendor/autoload.php';
		try {
			$client = TaxJar\Client::withApiKey($taxjarApiToken);
			$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
		} catch (Exception $e) {
			return false;
		}

		try {
			$order = $client->showOrder($orderId);
			if (empty($order->transaction_id)) {
				return false;
			}
		} catch (Exception $e) {
		}

		$cartTotal = 0;
		$lineItems = array();
		$totalTax = 0;
		if (!empty($orderItemIds)) {
			$resultSet = executeQuery("select * from order_items join products using (product_id) where order_id = ? and client_id = ? and order_item_id in (" . implode(",", $orderItemIds) . ")", $orderId, $GLOBALS['gClientId']);
			while ($orderItemRow = getNextRow($resultSet)) {
                $productIdentifier = ($useProductCode ? $orderItemRow['product_code'] : $orderItemRow['product_id']);
                $thisLineItem = array("product_identifier" => $productIdentifier, "description" => $orderItemRow['description'], "quantity" => $orderItemRow['quantity'], "unit_price" => -1 * $orderItemRow['sale_price'], "discount" => 0, "sales_tax" => -1 * $orderItemRow['tax_charge']);
				$totalTax += $orderItemRow['tax_charge'];
				$lineItems[] = $thisLineItem;
				$cartTotal += abs($orderItemRow['quantity']) * abs($orderItemRow['sale_price']);
			}
		} else {
			$cartTotal = max($totalRefundAmount - $orderRow['shipping_charge'] - $orderRow['handling_charge'], 0);
			$totalTax = $orderRow['tax_charge'];
			$lineItems[] = array("product_identifier" => "NA", "description" => "No products returned", "quantity" => 1, "unit_price" => -1 * $cartTotal, "discount" => 0, "sales_tax" => -1 * $totalTax);
		}

		$address1 = $postalCode = $city = $state = $countryId = "";
		$fromAddressInfo = $GLOBALS['gClientRow'];
		$gotAddresses = false;
		$contactId = $orderRow['contact_id'];
		if (!empty($contactId)) {
			$contactRow = Contact::getContact($contactId);
			if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
				$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
			}
			$address1 = $contactRow['address_1'];
			$city = $contactRow['city'];
			$state = $contactRow['state'];
			$postalCode = $contactRow['postal_code'];
			$countryId = $contactRow['country_id'];
		}

		if (!empty($orderRow['shipping_method_id'])) {
			$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
			if ($shippingMethodRow['pickup'] && !empty($shippingMethodRow['location_id'])) {
				$locationContactId = getFieldFromId("contact_id", "locations", "location_id", $shippingMethodRow['location_id']);
				$contactRow = Contact::getContact($locationContactId);
				if (empty($contactRow['state']) || empty($contactRow['postal_code'])) {
					$contactRow = $GLOBALS['gClientRow'];
				}
				if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
				$fromAddressInfo['address_1'] = $address1;
				$fromAddressInfo['city'] = $city;
				$fromAddressInfo['state'] = $state;
				$fromAddressInfo['postal_code'] = $postalCode;
				$fromAddressInfo['country_id'] = $countryId;
				$gotAddresses = true;
			}
		}

		if (!$gotAddresses) {
			if (!empty($orderRow['federal_firearms_licensee_id'])) {
				$fflContactId = (new FFL($orderRow['federal_firearms_licensee_id']))->getFieldData("contact_id");
				if (!empty($fflContactId)) {
					$contactRow = Contact::getContact($fflContactId);
					if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
						$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
					}
					$address1 = $contactRow['address_1'];
					$city = $contactRow['city'];
					$state = $contactRow['state'];
					$postalCode = $contactRow['postal_code'];
					$countryId = $contactRow['country_id'];
					$gotAddresses = true;
				}
			}
		}

		if (!$gotAddresses) {
			if (!empty($addressId)) {
				$contactRow = getRowFromId("addresses", "address_id", $addressId);
				if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
			} elseif (!empty($contactId)) {
				$contactRow = Contact::getContact($contactId);
				if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
			}
		}
		$programLogId = "";
		if (empty($postalCode) || empty($state)) {
			$programLogId = addProgramLog("Taxjar Refund Reporting: Required fields missing. Using client contact.");
			$postalCode = $postalCode ?: $GLOBALS['gClientRow']['postal_code'];
			$state = $state ?: $GLOBALS['gClientRow']['state'];
		}


		$shippingCharge = min($orderRow['shipping_charge'] + $orderRow['handling_charge'], $totalRefundAmount - $cartTotal);

		$orderData = [
			'transaction_id' => $orderId . '-refund-' . str_replace("#", "", getCrcValue(implode(",", $orderItemIds))),
			'transaction_date' => date("c", strtotime($orderRow['order_time'])),
			'transaction_reference_id' => $orderId,
			'from_country' => getFieldFromId("country_code", "countries", "country_id", $fromAddressInfo['country_id']),
			'from_zip' => $fromAddressInfo['postal_code'],
			'from_state' => $fromAddressInfo['state'],
			'from_city' => $fromAddressInfo['city'],
			'from_street' => $fromAddressInfo['address_1'],
			'to_country' => getFieldFromId("country_code", "countries", "country_id", $countryId),
			'to_zip' => $postalCode,
			'to_state' => $state,
			'to_city' => $city,
			'to_street' => $address1,
			'amount' => number_format(($cartTotal + $shippingCharge) * -1, 2, ".", ""),
			'shipping' => number_format($shippingCharge * -1, 2, ".", ""),
			'sales_tax' => number_format($totalTax * -1, 2, ".", ""),
			'plugin' => "coreware",
			'customer_id' => $contactId
		];
		$orderData['line_items'] = $lineItems;
		try {
			$response = $client->createRefund($orderData);
			addProgramLog("Taxjar Refund Reporting:\n\nRefund Data: " . jsonEncode($orderData) . "\n\nResponse: " . jsonEncode($response), $programLogId);
		} catch (Exception $e) {
			addProgramLog("Taxjar Refund Reporting Error: " . $e->getMessage() . "\n\nData: " . jsonEncode($orderData), $programLogId);
			if(startsWith($e->getMessage(),"403")) {
				sendCredentialsError(["integration_name"=>"TaxJar","error_message"=>$e->getMessage()]);
			} else {
                $GLOBALS['gPrimaryDatabase']->logError("Taxjar Refund Reporting Error: " . $e->getMessage() . "\n\nData: " . jsonEncode($orderData));
            }
			return false;
		}
		return true;
	}

	public static function hasPhysicalProducts($orderId) {
		$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $orderId, "product_id in (select product_id from products where virtual_product = 0)");
		return (!empty($orderItemId));
	}

	public static function getTaxRate($parameters = array()) {
		if (!empty($parameters['location_id'])) {
			$contactId = getFieldFromId("contact_id", "locations", "location_id", $parameters['location_id']);
			$taxRate = CustomField::getCustomFieldData($contactId, "TAX_RATE");
			if (!empty($taxRate)) {
				return $taxRate;
			}
			$state = getFieldFromId("state", "contacts", "contact_id", $contactId);
			$postalCode = getFieldFromId("postal_code", "contacts", "contact_id", $contactId);
			$stateTaxRate = getFieldFromId("tax_rate", "state_tax_rate", "state", $state);
			if (empty($stateTaxRate)) {
				$stateTaxRate = 0;
			}
			$postalCodeTaxRate = getFieldFromId("tax_rate", "postal_code_tax_rate", "postal_code", $postalCode);
			if (empty($postalCodeTaxRate)) {
				$postalCodeTaxRate = 0;
			}
			return ($stateTaxRate + $postalCodeTaxRate);
		}
		return 0;
	}

	public static function createGunBrokerOrder($gunBrokerOrderId, $submittedValues = array()) {
		$sourceId = getFieldFromId("source_id", "sources", "source_code", "GUNBROKER");
		if (empty($sourceId)) {
			$insertSet = executeQuery("insert into sources (client_id,source_code,description,internal_use_only) values (?,?,?,1)", $GLOBALS['gClientId'], "GUNBROKER", "GunBroker");
			$sourceId = $insertSet['insert_id'];
		}
		$taxCollectedSourceId = getFieldFromId("source_id", "sources", "source_code", "GUNBROKER_WITH_TAXES");
		if (empty($taxCollectedSourceId)) {
			$insertSet = executeQuery("insert into sources (client_id,source_code,description,tax_exempt,internal_use_only) values (?,?,?,1,1)", $GLOBALS['gClientId'], "GUNBROKER_WITH_TAXES", "GunBroker With Taxes Already Collected");
			$taxCollectedSourceId = $insertSet['insert_id'];
		}

		$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", "GUNBROKER");
		if (empty($orderMethodId)) {
			$insertSet = executeQuery("insert into order_methods (client_id,order_method_code,description,internal_use_only) values (?,?,?,1)", $GLOBALS['gClientId'], "GUNBROKER", "GunBroker");
			$orderMethodId = $insertSet['insert_id'];
		}

		try {
			$gunBroker = new GunBroker();
		} catch (Exception $e) {
			return "Unable to get orders from GunBroker. Make sure username & password are set and correct.";
		}
		$orderData = $gunBroker->getOrder($gunBrokerOrderId);
		$userContactInfo = $gunBroker->getUserContactInfo($orderData['buyer']['userID']);

		$orderNote = "GunBroker order ID <a href='https://www.gunbroker.com/order?orderid=" . $orderData['orderID'] . "'>" . $orderData['orderID'] . "</a>";
		$existingOrderId = getFieldFromId("order_id", "order_notes", "content", $orderNote);
		if (!empty($existingOrderId)) {
			return "GunBroker order " . $orderData['orderID'] . " has already been created as order " . $existingOrderId . ".";
		}

		$GLOBALS['gPrimaryDatabase']->startTransaction();

		$resultSet = executeQuery("select * from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
			"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $userContactInfo['email']);
		if ($contactRow = getNextRow($resultSet)) {
			$contactId = $contactRow['contact_id'];
		}
		if (empty($contactId)) {
			$contactDataTable = new DataTable("contacts");
			if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $userContactInfo['firstName'], "last_name" => $userContactInfo['lastName'],
				"business_name" => $userContactInfo['companyName'], "address_1" => $userContactInfo['address1'], "address_2" => $userContactInfo['address2'],
				"city" => $userContactInfo['city'], "state" => $userContactInfo['state'],
				"postal_code" => $userContactInfo['postalCode'], "email_address" => $userContactInfo['email'], "source_id" => $sourceId)))) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				return $contactDataTable->getErrorMessage();
			}
			$contactRow = Contact::getContact($contactId);
		}

		$federalFirearmsLicenseeId = "";
		if (!empty($orderData['fflNumber'])) {
			$fflLookup = substr($orderData['fflNumber'], 0, 5) . substr($orderData['fflNumber'], -5);
			$ffl = new FFL(array("license_lookup" => $fflLookup));
			$fflRow = $ffl->getFFLRow();
			$federalFirearmsLicenseeId = $fflRow["federal_firearms_licensee_id"];
			if (empty($federalFirearmsLicenseeId)) {
				$GLOBALS['gPrimaryDatabase']->logError("GunBroker FFL does not exist: " . $orderData['fflNumber']);
			} else {
				if(!empty($orderData['eFFLUrl']) && empty($fflRow["file_id"]) && $fflRow['client_id'] == $GLOBALS['gClientId']) {
					$fileContents = file_get_contents($orderData['eFFLUrl']);
					$fileId = createFile(array("filename" => "FFL License File", "file_content" => $fileContents));
					if(!empty($fileId)) {
						updateFieldById("file_id", $fileId,"federal_firearms_licensees", "federal_firearms_licensee_id", $federalFirearmsLicenseeId);
					}
				}
			}
		}

		$addressId = false;
		// GunBroker puts FFL address into shipping address.  If FFL is specified, ignore shipping address
		if (empty($federalFirearmsLicenseeId) && !empty($orderData['shipToAddress1'])) {
			$userAddress = strtolower(str_replace(" ", "", implode(",", array($contactRow['address_1'], $contactRow['address_2'],
				$contactRow['city'], $contactRow['state'], $contactRow['postal_code']))));
			$orderAddress = strtolower(str_replace(" ", "", implode(",", array($orderData['shipToAddress1'], $orderData['shipToAddress2'],
				$orderData['shipToCity'], $orderData['shipToState'], $orderData['shipToPostalCode']))));
			if ($userAddress !== $orderAddress) {
				$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId,
					"address_1 = ? and city = ? and state = ? and postal_code = ?",
					$orderData['shipToAddress1'], $orderData['shipToCity'], $orderData['shipToState'], $orderData['shipToPostalCode']);
				if (empty($addressId)) {
					$resultSet = executeQuery("insert into addresses (contact_id, address_label, address_1, address_2, city, state, postal_code, country_id)"
						. " values (?,?,?,?,?,?,?,?)", $contactId, "Shipping", $orderData['shipToAddress1'], $orderData['shipToAddress2'],
						$orderData['shipToCity'], $orderData['shipToState'], $orderData['shipToPostalCode'], 1000);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return getSystemMessage("basic", $resultSet['sql_error']);
					}
					$addressId = $resultSet['insert_id'];
				}
			}
		}

		$phoneNumber = "";
		if (!empty($userContactInfo['phone'])) {
			$phoneNumber = formatPhoneNumber($userContactInfo['phone']);
			$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $phoneNumber, "contact_id = ?", $contactId);
			if (empty($phoneNumberId)) {
				executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Primary')", $contactId, $phoneNumber);
			}
		}

		$orderObject = new Order();
		$orderObject->setOrderField("contact_id", $contactId);
		foreach ($orderData['items'] as $thisItem) {
			$itemData = $gunBroker->getItemData($thisItem['itemID']);
			if (empty($itemData)) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				return "Unable to get product for '" . $thisItem['title'] . "': " . jsonEncode($thisItem);
			}
			$productId = $submittedValues['product_id_' . $thisItem['itemID']];
			$orderItemProductRow = ProductCatalog::getCachedProductRow($productId);
			$itemData['upc'] = trim($itemData['upc']);
			if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
				$itemData['upc'] = trim($itemData['gtin']);
			}
			if (empty($productId) && !empty($itemData['upc'])) {
				$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc']);
			}
			if (empty($productId)) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				return "Unable to get product for '" . (empty($itemData['upc']) ? "NO UPC" : $itemData['upc']) . "'";
			}
			$orderItem = array("product_id" => $productId, "sale_price" => $thisItem['itemPrice'], "quantity" => $thisItem['quantity']);

			$resultSet = executeQuery("select * from product_pack_contents where product_id = ?", $productId);
			if ($resultSet['row_count'] > 0) {
				$originalSalePrice = $thisItem['itemPrice'];
				$packContents = array();
				$totalSalePrices = 0;
				$productCatalog = new ProductCatalog();
				while ($row = getNextRow($resultSet)) {
					$productRow = ProductCatalog::getCachedProductRow($row['contains_product_id']);
					$salePriceInfo = $productCatalog->getProductSalePrice($row['contains_product_id'], array("product_information" => $productRow));
					$thisSalePrice = $salePriceInfo['sale_price'];
					$totalSalePrices += $row['quantity'] * $thisSalePrice;
					$productRow['sale_price'] = $thisSalePrice;
					$packContents[] = array_merge($row, $productRow);
				}
				$percentDiscount = $originalSalePrice / $totalSalePrices;
				$finalSaleTotal = 0;
				$addItems = array();
				foreach ($packContents as $index => $thisContent) {
					$salePrice = round($thisContent['sale_price'] * $percentDiscount, 2);
					$finalSaleTotal += ($salePrice * $thisContent['quantity']);
					$addItems[] = array("product_id" => $thisContent['product_id'], "sale_price" => $salePrice, "quantity" => ($thisContent['quantity'] * $orderItem['quantity']), "pack_product_id" => $productId, "pack_description" => $orderItemProductRow['description'], "pack_quantity" => $orderItem['quantity']);
				}
				if ($finalSaleTotal != $originalSalePrice) {
					$remaining = $originalSalePrice - $finalSaleTotal;
					foreach ($addItems as $index => $thisAddItem) {
						if ($thisAddItem['quantity'] == $orderItem['quantity']) {
							$addItems[$index]['sale_price'] += $remaining;
							break;
						}
					}
				}
				foreach ($addItems as $thisAddItem) {
					$orderObject->addOrderItem($thisAddItem);
				}

				$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $productId, "quantity > 0");
				if (!empty($productInventoryId)) {
					executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity) values " .
						"(?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $GLOBALS['gUserId'], $thisItem['quantity']);
					executeQuery("update product_inventories set quantity = quantity - " . $thisItem['quantity'] . " where product_inventory_id = ?", $productInventoryId);
				}
			} else {
				$orderObject->addOrderItem($orderItem);
			}
		}
		$orderObject->setOrderField("source_id", (empty($orderData['salesTaxTotal']) ? $sourceId : $taxCollectedSourceId));
		$orderObject->setOrderField("federal_firearms_licensee_id", $federalFirearmsLicenseeId);
		$orderObject->setOrderField("full_name", $orderData['billToName']);
		if ($addressId !== false) {
			$orderObject->setOrderField("address_id", $addressId);
		}
		$orderObject->setOrderField("order_method_id", $orderMethodId);
		$orderObject->setOrderField("phone_number", $phoneNumber);
		$orderObject->setOrderField("purchase_order_number", $orderData['orderID']);
		$orderObject->setOrderField("shipping_charge", ($orderData['shipCost'] + $orderData['shipInsuranceCost']));

		$taxProductId = getFieldFromId("product_id", "products", "product_code", "GUNBROKER_COLLECTED_TAXES", "inactive = 0");
		if (empty($taxProductId)) {
			$orderObject->setOrderItemTaxes($orderData['salesTaxTotal']);
			$orderObject->setOrderField("tax_charge", $orderData['salesTaxTotal']);
		} else {
			if ($orderData['salesTaxTotal'] > 0) {
				$orderObject->addOrderItem(array("product_id" => $taxProductId, "quantity" => 1, "sale_price" => $orderData['salesTaxTotal']));
			}
			$orderObject->setOrderField("tax_charge", 0);
		}
		$orderObject->setOrderField("handling_charge", $orderData['shipHandlingCost'] + $orderData['lostCashDiscount']);
		if ($submittedValues['mark_completed']) {
			$orderObject->setOrderField("date_completed", date("Y-m-d", strtotime($orderData['lastModifiedDate'])));
		}
		if (!$orderObject->generateOrder()) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return $orderObject->getErrorMessage();
		}

		if (!empty($orderData['paymentReceivedDate'])) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "GUNBROKER");
			if (empty($paymentMethodId)) {
				$insertSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,internal_use_only) values (?,?,?,1)",
					$GLOBALS['gClientId'], "GUNBROKER", "GunBroker");
				$paymentMethodId = $insertSet['insert_id'];
			}
			$orderTotal = round($orderData['orderTotal'] - ($orderData['shipCost'] + $orderData['shipInsuranceCost']) - $orderData['salesTaxTotal'] - $orderData['lostCashDiscount'] - $orderData['shipHandlingCost'], 2);
			$orderObject->createOrderPayment($orderTotal, array("payment_method_id" => $paymentMethodId, "shipping_charge" => ($orderData['shipCost'] + $orderData['shipInsuranceCost']), "handling_charge" => $orderData['shipHandlingCost'] + $orderData['lostCashDiscount'],
				"tax_charge" => $orderData['salesTaxTotal'], "payment_time" => date("Y-m-d H:i:s", strtotime($orderData['paymentReceivedDate']))));
		}

		$orderId = $orderObject->getOrderId();
		if (!empty($submittedValues['order_status_id'])) {
			Order::updateOrderStatus($orderId, $submittedValues['order_status_id']);
		} else {
			Order::updateOrderStatusCode($orderId, "NEW_GUNBROKER_ORDER");
		}

		$orderNoteUserId = $GLOBALS['gUserId'];
		if (empty($orderNoteUserId)) {
			$orderNoteUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
		}
		executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId, $orderNote);
		if ($orderData['lostCashDiscount'] > 0) {
			executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId, "Credit card fee of " . $orderData['lostCashDiscount'] . " added to handling charge.");
		}
		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		$programLogId = addProgramLog("Order placed by contact ID " . $contactId . " from GunBroker order " . $gunBrokerOrderId . ".\n\nOrder Completed, ID " . $orderId);
		foreach ($orderData['items'] as $thisItem) {
			$gunBroker->checkListing($thisItem['itemID'], $programLogId);
		}
		Order::processOrderItems($orderId);
		Order::processOrderAutomation($orderId);
		coreSTORE::orderNotification($orderId, "order_created");
		Order::notifyCRM($orderId);
		return $orderId;
	}

	public static function updateGunbrokerOrder($orderId, $orderRow = array()) {
		if (empty($orderRow)) {
			$orderRow = getRowFromId("orders", "order_id", $orderId);
		}
		if (empty($orderRow['purchase_order_number']) || $orderRow['order_method_id'] != getFieldFromId("order_method_id", "order_methods", "order_method_code", "GUNBROKER")) {
			return false;
		}
		try {
			$gunBroker = new GunBroker();
			$gunBrokerOrder = $gunBroker->getOrder($orderRow['purchase_order_number']);
			if (!empty($gunBrokerOrder['shipDate'])) { // Once an order is marked shipped on GB, it can not be edited.
                if(!empty(getPreference("LOG_GUNBROKER"))) {
                    addDebugLog(sprintf( "Unable to update GunBroker order %s: order already has shipped date: '%s'", $orderRow['purchase_order_number'], $gunBrokerOrder['shipDate']));
                }
				return false;
			}
			$flags = array();
			if (empty($gunBrokerOrder['paymentReceivedDate']) && !empty(getRowFromId("order_payments", "order_id", $orderId, "deleted = 0 and not_captured = 0"))) {
				$flags['PaymentReceived'] = true;
			}
			if (empty($gunBrokerOrder['fflReceivedDate']) && !empty((new FFL($orderRow['federal_firearms_licensee_id']))->getFieldData("file_id"))) {
				$flags['FFLReceived'] = true;
			}
			if (!empty($orderRow['date_completed']) &&
				!empty(getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId, "tracking_identifier is not null"))) {
				$flags['OrderShipped'] = true;
			}
			if (!empty($flags)) {
				$gunBroker->updateOrder($orderRow['purchase_order_number'], $flags);
			}
		} catch (Exception $e) {
		}
		return true;
	}

	public static function updateOrderStatus($orderId, $orderStatusId, $forceUpdate = false, $notifyCorestore = true) {
		if (!empty($orderStatusId)) {
			$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $orderStatusId);
			if (empty($orderStatusId)) {
				return false;
			}
		}
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		if (!$forceUpdate && $orderRow['order_status_id'] == $orderStatusId) {
			return false;
		}
		$dataTable = new DataTable("orders");
		$dataTable->setSaveOnlyPresent(true);
		$dataTable->setPrimaryId($orderId);
		$nameValues = array("order_status_id" => $orderStatusId);
		$markCompleted = getFieldFromId("mark_completed", "order_status", "order_status_id", $orderStatusId);
		if (!empty($markCompleted) && empty($orderRow['date_completed'])) {
			$nameValues['date_completed'] = date("Y-m-d");
		} else {
			$markCompleted = false;
		}
		if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
			return false;
		}
		if ($markCompleted) {
			$productsSet = executeQuery("select product_id from order_items where order_id = ?", $orderId);
			while ($productRow = getNextRow($productsSet)) {
				removeCachedData("product_waiting_quantity", $productRow['product_id']);
			}
		}

		if (!empty($orderStatusId)) {
			executeQuery("insert into order_status_changes (order_id,order_status_id) values (?,?)", $orderId, $orderStatusId);

			$contactRow = Contact::getContact($orderRow['contact_id']);
			if (empty($orderRow['address_id'])) {
				$shippingAddress = $contactRow;
			} else {
				$shippingAddress = getRowFromId("addresses", "address_id", $orderRow['address_id']);
			}
			$substitutions = array_merge($contactRow, $orderRow);

			$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
			if (!empty($shippingAddress['address_2'])) {
				$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
			}
			$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
			if (!empty($shippingAddress['postal_code'])) {
				$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
			}
			if (!empty($shippingCityLine)) {
				$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
			}
			if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
				$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
			}
			$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
			$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();

			$substitutions['ffl_name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
			$substitutions['store_name'] = (empty($fflRow['licensee_name']) ? $fflRow['business_name'] : $fflRow['licensee_name']);
			$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
			$substitutions['ffl_license_number'] = $fflRow['license_number'];
			$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
			$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];
			$substitutions['store_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];

			$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
			$substitutions['shipping_method'] = $shippingMethodRow['description'];
			$locationRow = getRowFromId("locations", "location_id", $shippingMethodRow['location_id']);
			$substitutions['location'] = $locationRow['description'];
			$substitutions['location_address_block'] = getAddressBlock(Contact::getContact($locationRow['contact_id']));

			$substitutions['domain_name'] = getDomainName();
			$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderId, false), $substitutions);
			$cartTotal = floatval(str_replace(",", "", $substitutions['cart_total']));
			$substitutions['order_total'] = number_format($cartTotal + $orderRow['shipping_charge'] + $orderRow['tax_charge'] + $orderRow['handling_charge'], 2);

			$emailAddresses = array();
			$resultSet = executeQuery("select * from order_status_notifications where order_status_id = ?", $orderStatusId);
			while ($row = getNextRow($resultSet)) {
				$emailAddresses[] = $row['email_address'];
			}
			if (!empty($emailAddresses)) {
				$body = "<p>Order ID " . $orderId . " status change from " . (empty($orderRow['order_status_id']) ? "[NO STATUS]" : "'" . getFieldFromId("description", "order_status", "order_status_id", $orderRow['order_status_id']) . "'") .
					" to '" . getFieldFromId("description", "order_status", "order_status_id", $orderStatusId) . "'</p>" .
					"<p>Order Details: </p><p>" . $orderRow['full_name'] . "<br>" . $substitutions['shipping_address_block'] . "</p>" . $substitutions['order_items_table'] . "<p>Order Total: " . $substitutions['order_total'] . "</p>";
				sendEmail(array("body" => "Status change to '" . getFieldFromId("description", "order_status", "order_status_id", $orderStatusId) . "' for Order ID " . $orderId,
					"subject" => "Order Status Change", "email_addresses" => $emailAddresses));
			}
			$emailId = getFieldFromId("email_id", "order_status", "order_status_id", $orderStatusId);
			if (!empty($emailId)) {
				sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $contactRow['email_address'], "contact_id" => $contactRow['contact_id']));
			}
			if ($notifyCorestore) {
				self::notifyCRM($orderId, "update_status");
				coreSTORE::orderNotification($orderId, "update_status");
			}
			$orderStatusCode = getFieldFromId("order_status_code", "order_status", "order_status_id", $orderStatusId);
			switch ($orderStatusCode) {
				case "INVOICE_SENT":
					executeQuery("update invoices set date_due = date_add(current_date,interval 30 day) where date_due is null and date_completed is null and invoice_id in (select invoice_id from order_payments where invoice_id is not null and order_id = ?)",$orderId);
					break;
				case "BACKORDER":
					$resultSet = executeReadQuery("select order_item_id,product_id,quantity,(select sum(quantity) from order_shipment_items where exists (select order_shipment_id from order_shipments " .
						"where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0) and order_item_id in (select order_item_id from order_items as oi where " .
						"product_id = order_items.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null))) as quantity_shipped from order_items " .
						"where deleted = 0 and order_id = ?", $orderId);
					while ($row = getNextRow($resultSet)) {
						if (empty($row['quantity'])) {
							$row['quantity'] = 0;
						}
						if (empty($row['quantity_shipped'])) {
							$row['quantity_shipped'] = 0;
						}
						$waitingQuantity = max(0, $row['quantity'] - $row['quantity_shipped']);
						if ($waitingQuantity > 0) {
							$productId = $row['product_id'];
							$productCatalog = new ProductCatalog();
							$totalInventory = $productCatalog->getInventoryCounts(true, $productId, false, array("ignore_backorder" => true));
							if ($totalInventory <= 0) {
								$productInventoryNoticationId = getFieldFromId("product_inventory_notification_id", "product_inventory_notifications", "product_id", $productId);
								if (empty($productInventoryNoticationId)) {
									$emailAddress = getPreference("BACKORDERED_ITEM_AVAILABLE_NOTIFICATION");
									if (empty($emailAddress)) {
										$emailAddress = $GLOBALS['gUserRow']['email_address'];
									}
									if (empty($emailAddress)) {
										if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
											$userId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0 and contact_id in (select contact_id from contacts where email_address is not null)");
											$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id", "users", "user_id", $userId));
										} else {
											$userId = $GLOBALS['gUserId'];
											$emailAddress = $GLOBALS['gUserRow']['email_address'];
										}
									}
									executeQuery("insert into product_inventory_notifications (product_id,user_id,email_address,comparator,quantity,order_quantity,place_order,use_lowest_price,allow_multiple) values " .
										"(?,?,?,?,?, ?,?,?,?)", $productId, $userId, $emailAddress, ">", 0, $row['quantity'], 1, 1, 1);
								}
							}
						}
					}
					break;
			}
		}
		return true;
	}

	public static function updateOrderStatusCode($orderId, $orderStatusCode, $forceUpdate = false) {
		$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", $orderStatusCode);
		if (empty($orderStatusId)) {
			return false;
		} else {
			return Order::updateOrderStatus($orderId, $orderStatusId, $forceUpdate);
		}
	}

	public static function processOrderItems($orderId, $parameters = array()) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		if (empty($orderRow)) {
			return;
		}
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$promotionRow = ShoppingCart::getCachedPromotionRow($orderRow['promotion_id']);
		$orderItemSet = executeQuery("select * from order_items where order_id = ?", $orderId);
		$clearProductPriceCache = false;
		$productIds = array();
		$productCatalog = new ProductCatalog();
		while ($orderItemRow = getNextRow($orderItemSet)) {
			$productPriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $orderItemRow['product_id'], "location_id is not null");
			if (!empty($productPriceId)) {
				$clearProductPriceCache = true;
			}
			removeCachedData("product_waiting_quantity", $orderItemRow['product_id']);
			removeCachedData("product_prices", $orderItemRow['product_id']);
			ProductCatalog::calculateProductCost($orderItemRow['product_id'], "Product is sold");
			ProductCatalog::calculateAllProductSalePrices($orderItemRow['product_id']);
			$productRow = ProductCatalog::getCachedProductRow($orderItemRow['product_id']);
			$productIds[] = $orderItemRow['product_id'];

			$totalInventoryCounts = false;
			$locationInventoryCounts = false;
			$sendNotification = false;
			$resultSet = executeQuery("select * from locations join contacts using (contact_id) where inactive = 0 and internal_use_only = 0 and " .
				"notification_threshold is not null and notification_threshold > 0 and email_address is not null and locations.client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (empty($row['cannot_ship'])) {
					if ($totalInventoryCounts === false) {
						$totalInventoryCounts = $productCatalog->getInventoryCounts(true, $orderItemRow['product_id']);
					}
					if (array_key_exists($orderItemRow['product_id'], $totalInventoryCounts) && $totalInventoryCounts[$orderItemRow['product_id']] <= $row['notification_threshold']) {
						$sendNotification = false;
					}
				} else {
					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					if ($shippingMethodRow['location_id'] == $row['location_id'] && !empty($shippingMethodRow['pickup'])) {
						if ($locationInventoryCounts === false) {
							$locationInventoryCounts = $productCatalog->getInventoryCounts(false, array($orderItemRow['product_id']));
						}
						if (array_key_exists($orderItemRow['product_id'], $locationInventoryCounts) && array_key_exists($row['location_id'], $locationInventoryCounts[$orderItemRow['product_id']])) {
							if ($locationInventoryCounts[$orderItemRow['product_id']][$row['location_id']] <= $row['notification_threshold']) {
								$sendNotification = true;
							}
						}
					}
				}
				if ($sendNotification) {
					sendEmail(array("email_address" => $row['email_address'], "subject" => "Low Inventory", "body" => "<p>The product '" . $productRow['description'] .
						"', ID " . $productRow['product_id'] . " has been ordered, but is low at location '" . $row['description'] . "'.</p>"));
				}
			}

			# Check for various product types

			$productTypeCode = getFieldFromId("product_type_code", "product_types", "product_type_id", $productRow['product_type_id']);
			switch ($productTypeCode) {
				case "UPGRADE_SUBSCRIPTION":
					$fromSubscriptionCode = CustomField::getCustomFieldData($productRow['product_id'], "FROM_SUBSCRIPTION_CODE", "PRODUCTS");
					$fromSubscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", $fromSubscriptionCode, "inactive = 0 and internal_use_only = 0");
					if (empty($fromSubscriptionId)) {
						break;
					}
					$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "subscription_id", $fromSubscriptionId,
						"contact_id = ? and inactive = 0 and customer_paused = 0 and start_date <= current_date and (expiration_date is null or expiration_date > current_date)", $orderRow['contact_id']);
					if (empty($contactSubscriptionId)) {
						break;
					}
					$toSubscriptionCode = CustomField::getCustomFieldData($productRow['product_id'], "TO_SUBSCRIPTION_CODE", "PRODUCTS");
					$toSubscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", $toSubscriptionCode, "inactive = 0 and internal_use_only = 0");
					if (empty($toSubscriptionId)) {
						break;
					}
					$recurringPaymentRow = getRowFromId("recurring_payments", "contact_subscription_id", $contactSubscriptionId);
					$subscriptionProductId = false;
					if (!empty($recurringPaymentRow)) {
						$subscriptionProductId = CustomField::getCustomFieldData($productRow['product_id'], "NEW_RENEWAL_PRODUCT_CODE", "PRODUCTS");
						if (!empty($subscriptionProductId)) {
							$subscriptionProductId = getFieldFromId("product_id", "subscription_products", "subscription_id", $toSubscriptionId,
								"recurring_payment_type_id = ? and product_id = ?", $recurringPaymentRow['recurring_payment_type_id'], $subscriptionProductId);
						}
						if (empty($subscriptionProductId)) {
							$subscriptionProductId = getFieldFromId("product_id", "subscription_products", "subscription_id", $toSubscriptionId,
								"recurring_payment_type_id = ?", $recurringPaymentRow['recurring_payment_type_id']);
						}
						if (empty($subscriptionProductId)) {
							break;
						}
					}
					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					$dataTable->setPrimaryId($contactSubscriptionId);
					if (!$dataTable->saveRecord(array("name_values" => array("subscription_id" => $toSubscriptionId)))) {
						break;
					}
					updateUserSubscriptions($orderRow['contact_id']);

					if (!empty($recurringPaymentRow) && !empty($subscriptionProductId)) {
						$renewalProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "SUBSCRIPTION_RENEWAL");
						$salePriceInfo = $productCatalog->getProductSalePrice($subscriptionProductId);
						$resultSet = executeQuery("select *,(select product_type_id from products where product_id = recurring_payment_order_items.product_id) as product_type_id from recurring_payment_order_items where recurring_payment_id = ?", $recurringPaymentRow['recurring_payment_id']);
						while ($row = getNextRow($resultSet)) {
							if ($renewalProductTypeId == $row['product_type_id']) {
								$dataTable = new DataTable("recurring_payment_order_items");
								$dataTable->setSaveOnlyPresent(true);
								$dataTable->saveRecord(array("name_values" => array("product_id" => $subscriptionProductId, "sale_price" => $salePriceInfo['sale_price']), "primary_id" => $row['recurring_payment_order_item_id']));
								break;
							}
						}
					}
					break;
				case "MEMBERSHIP":
				case "SUBSCRIPTION_STARTUP":
					$emailId = getFieldFromId("email_id", "emails", "email_code", "EMAIL_CONTACT_USER", "inactive = 0");
					$emailBody = "<p>You can create a user account, with which you can access member benefits.</p>" .
						"<p>This site is SSL secure. It is as secure as the website of your bank.</p>" .
						"<p>PLEASE do not use spaces in your user name. If you do the system will make a space into an underscore.</p>" .
						"<p>The information you will need to create this account is:</p>" .
						"<p>Site Code: %site_code%</p>" .
						"<p>Your Contact ID: %contact_id%</p>" .
						"<p>Hash Code: %hash_code%</p>" .
						"<p>Email Address: %email_address%</p>" .
						"<p>You can create your user account by going to https://%http_host%/createcontactuser.php or click <a href='https://%http_host%/createcontactuser.php?site_code=%site_code%&contact_id=%contact_id%&hash_code=%hash_code%'>here</a>.</p>";
					$emailSubject = "Create User Account";
					if (!empty($contactRow['email_address'])) {
						$userId = Contact::getContactUserId($orderRow['contact_id']);
						if (empty($userId)) {
							$substitutions = $contactRow;
							if (empty($substitutions['hash_code'])) {
								$hashCode = md5(uniqid(mt_rand(), true) . $substitutions['first_name'] . $substitutions['last_name'] . $substitutions['contact_id'] . $substitutions['email_address'] . $substitutions['date_created']);
								executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $contactRow['contact_id']);
								$substitutions['hash_code'] = $hashCode;
							}
							$substitutions['http_host'] = getDomainName(true);
							$substitutions['site_code'] = getFieldFromId("client_code", "clients", "client_id", $GLOBALS['gClientId']);
							sendEmail(array("email_id" => $emailId, "subject" => $emailSubject, "body" => $emailBody, "email_addresses" => $contactRow['email_address'], "substitutions" => $substitutions, "contact_id" => $contactRow['contact_id']));
						}
					}
					$customData = CustomField::getCustomFieldData($orderItemRow['order_item_id'], "ADDITIONAL_MEMBERS", "ORDER_ITEMS");
					if (empty($customData)) {
						break;
					}
					$customFieldData = json_decode($customData, true);
					if (!is_array($customFieldData)) {
						break;
					}
					$relationshipTypeId = getFieldFromId("relationship_type_id", "relationship_types", "relationship_type_code", "SHARES_MEMBERSHIP");
					if (empty($relationshipTypeId)) {
						$insertSet = executeQuery("insert into relationship_types (client_id,relationship_type_code,description) values (?,'SHARES_MEMBERSHIP','Sharing Membership')", $GLOBALS['gClientId']);
						$relationshipTypeId = $insertSet['insert_id'];
					}
					foreach ($customFieldData as $thisRow) {
						if (empty($thisRow['first_name']) && empty($thisRow['last_name']) && empty($thisRow['email_address'])) {
							continue;
						}
						$contactId = getFieldFromId("contact_id", "contacts", "first_name", $thisRow['first_name'], "last_name = ? and email_address = ? and contact_id <> ?",
							$thisRow['last_name'], $thisRow['email_address'], $contactRow['contact_id']);
						if (empty($contactId)) {
							$contactTable = new DataTable("contacts");
							if (!array_key_exists("country_id", $thisRow)) {
								$thisRow['country_id'] = 1000;
							}
							$thisRow['date_created'] = date("Y-m-d");
							$contactId = $contactTable->saveRecord(array("name_values" => $thisRow));
							$relationshipId = "";
						} else {
							$relationshipId = getFieldFromId("relationship_id", "relationships", "contact_id", $contactRow['contact_id'],
								"related_contact_id = ? and relationship_type_id = ?", $contactId, $relationshipTypeId);
						}
						if (!empty($thisRow['phone_number'])) {
							$phoneNumber = formatPhoneNumber($thisRow['phone_number']);
							$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $phoneNumber);
							if (empty($phoneNumberId)) {
								executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $phoneNumber);
							}
						}
						if (empty($relationshipId)) {
							executeQuery("insert into relationships (contact_id,related_contact_id,relationship_type_id) values (?,?,?)",
								$contactRow['contact_id'], $contactId, $relationshipTypeId);
						}
						$userId = Contact::getContactUserId($contactId);
						if (empty($userId)) {
							$substitutions = Contact::getContact($contactId);
							if (empty($substitutions['hash_code'])) {
								$hashCode = md5(uniqid(mt_rand(), true) . $substitutions['first_name'] . $substitutions['last_name'] . $substitutions['contact_id'] . $substitutions['email_address'] . $substitutions['date_created']);
								executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $contactId);
								$substitutions['hash_code'] = $hashCode;
							}
							$substitutions['http_host'] = getDomainName(true);
							$substitutions['site_code'] = getFieldFromId("client_code", "clients", "client_id", $GLOBALS['gClientId']);
							sendEmail(array("email_id" => $emailId, "subject" => $emailSubject, "body" => $emailBody, "email_addresses" => $thisRow['email_address'], "substitutions" => $substitutions, "contact_id" => $contactId));
						}
					}
					break;
				case "EVENT_REGISTRATION":
					if ($parameters['event_registration_done']) {
						break;
					}
					$resultSet = executeQuery("select * from events where start_date >= current_date and product_id = ? and client_id = ?", $orderItemRow['product_id'], $GLOBALS['gClientId']);
					if ($resultSet['row_count'] != 1) {
						break;
					}
					$eventRow = getNextRow($resultSet);
					$customData = CustomField::getCustomFieldData($orderItemRow['order_item_id'], "EVENT_REGISTRANTS", "ORDER_ITEMS");
					if (empty($customData)) {
						$customFieldData = array();
					} else {
						$customFieldData = json_decode($customData, true);
					}
					if (!is_array($customFieldData)) {
						$customFieldData = array();
					}
					$itemQuantity = $orderItemRow['quantity'];
					$emailAddresses = array();
					foreach ($customFieldData as $thisRow) {
						if (empty($thisRow['first_name']) && empty($thisRow['last_name']) && empty($thisRow['email_address'])) {
							$contactId = $orderRow['contact_id'];
						} elseif ($thisRow['first_name'] == $contactRow['first_name'] && $thisRow['last_name'] == $contactRow['last_name'] && $thisRow['email_address'] == $contactRow['email_address']) {
							$contactId = $orderRow['contact_id'];
						} else {
							if (!empty($thisRow['email_address']) && !in_array($thisRow['email_address'], $emailAddresses)) {
								$emailAddresses[] = $thisRow['email_address'];
							}
							$contactId = getFieldFromId("contact_id", "contacts", "first_name", $thisRow['first_name'], "last_name = ? and email_address = ?",
								$thisRow['last_name'], $thisRow['email_address']);
							if (empty($contactId)) {
								$contactTable = new DataTable("contacts");
								if (!array_key_exists("country_id", $thisRow)) {
									$thisRow['country_id'] = 1000;
								}
								$thisRow['date_created'] = date("Y-m-d");
								$contactId = $contactTable->saveRecord(array("name_values" => $thisRow));
							}
						}
						if (!empty($thisRow['phone_number'])) {
							$phoneNumber = formatPhoneNumber($thisRow['phone_number']);
							$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $phoneNumber);
							if (empty($phoneNumberId)) {
								executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $phoneNumber);
							}
						}
						executeQuery("insert into event_registrants (event_id,contact_id,registration_time,order_id) values (?,?,now(),?)", $eventRow['event_id'], $contactId, $orderRow['order_id']);
						$itemQuantity--;
					}
					while ($itemQuantity > 0) {
						executeQuery("insert into event_registrants (event_id,contact_id,registration_time,order_id) values (?,?,now(),?)", $eventRow['event_id'], $orderRow['contact_id'], $orderRow['order_id']);
						$itemQuantity--;
					}
					$attendeeCounts = Events::getAttendeeCounts($eventRow['event_id']);
					if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
						executeQuery("update products set non_inventory_item = 0 where product_id = ?", $orderItemRow['product_id']);
						executeQuery("update product_inventories set quantity = 0 where product_id = ?", $orderItemRow['product_id']);
					}

					$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $orderRow['contact_id']);
					if (!empty($substitutions['email_address']) && !in_array($substitutions['email_address'], $emailAddresses)) {
						$emailAddresses[] = $substitutions['email_address'];
					}

					if (empty($eventRow['email_id'])) {
						$eventRow['email_id'] = getFieldFromId("email_id", "event_type_location_emails", "event_type_id", $eventRow['event_type_id'], "location_id = ?", $eventRow['location_id']);
						if (empty($eventRow['email_id'])) {
							$eventRow['email_id'] = getFieldFromId("email_id", "event_types", "event_type_id", $eventRow['event_type_id']);
						}
					}
					if (!empty($eventRow['email_id'])) {
						sendEmail(array("email_id" => $eventRow['email_id'], "email_address" => $emailAddresses, "substitutions" => $substitutions, "contact_id" => $contactRow['contact_id']));
					}

					break;
				case "GIFT_CARD":
					$unCapturedPaymentExists = getFieldFromId("order_payment_id", "order_payments", "order_id", $orderId, "not_captured = 1");
					if (!$unCapturedPaymentExists && empty(getPreference("MANUAL_GIFT_CARD_ISSUANCE"))) {
						$giftCard = new GiftCard();
						$giftCard->issueGiftCards($orderItemRow['order_item_id']);
					}
					break;
				case "PROMOTION_SALE":
					$substitutions = array();
					$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS')", $GLOBALS['gClientId']);
					while ($customFieldRow = getNextRow($customFieldSet)) {
						$substitutions[strtolower($customFieldRow['custom_field_code'])] = CustomField::getCustomFieldData($orderItemRow['order_item_id'], $customFieldRow['custom_field_code'], "ORDER_ITEMS");
					}
					$customFields = array();
					$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS')", $GLOBALS['gClientId']);
					while ($customFieldRow = getNextRow($customFieldSet)) {
						$customFields[strtolower($customFieldRow['custom_field_code'])] = CustomField::getCustomFieldData($productRow['product_id'], $customFieldRow['custom_field_code'], "PRODUCTS");
					}
					if (empty($customFields['promotion_code'])) {
						break;
					}
					$promotionId = getReadFieldFromId("promotion_id", "promotions", "promotion_code", $customFields['promotion_code']);
					$promotionRow = ShoppingCart::getCachedPromotionRow($promotionId);
					if (empty($promotionRow)) {
						break;
					}

					$promoNumber = $orderItemRow['quantity'];
					while ($promoNumber > 0) {
						do {
							$promotionCode = strtoupper(getRandomString(25));
							$promotionId = getFieldFromId("promotion_id", "promotions", "promotion_code", $promotionCode);
						} while (!empty($promotionId));
						$newPromotionRow = $promotionRow;
						$newPromotionRow['promotion_id'] = "";
						$newPromotionRow['promotion_code'] = $promotionCode;
						$newPromotionRow['order_item_id'] = $orderItemRow['order_item_id'];
						if (empty($newPromotionRow['maximum_usages'])) {
							$newPromotionRow['maximum_usages'] = 1;
						}
						$newPromotionRow['internal_use_only'] = 0;
						$newPromotionRow['inactive'] = 0;
						$dataTable = new DataTable("promotions");
						if (!$newPromotionId = $dataTable->saveRecord(array("name_values" => $newPromotionRow, "primary_id" => ""))) {
							break;
						}
						# copy subtables
						$promotionTables = array("promotion_files", "promotion_purchased_product_categories", "promotion_purchased_product_category_groups",
							"promotion_purchased_product_departments", "promotion_purchased_product_manufacturers", "promotion_purchased_product_tags",
							"promotion_purchased_product_types", "promotion_purchased_products", "promotion_purchased_sets", "promotion_rewards_excluded_product_categories",
							"promotion_rewards_excluded_product_category_groups", "promotion_rewards_excluded_product_departments", "promotion_rewards_excluded_product_manufacturers",
							"promotion_rewards_excluded_product_tags", "promotion_rewards_excluded_product_types", "promotion_rewards_excluded_products",
							"promotion_rewards_excluded_sets", "promotion_rewards_product_categories", "promotion_rewards_product_category_groups",
							"promotion_rewards_product_departments", "promotion_rewards_product_manufacturers", "promotion_rewards_product_tags", "promotion_rewards_product_types",
							"promotion_rewards_products", "promotion_rewards_sets", "promotion_rewards_shipping_charges", "promotion_terms_contact_types",
							"promotion_terms_countries", "promotion_terms_excluded_product_categories", "promotion_terms_excluded_product_category_groups",
							"promotion_terms_excluded_product_departments", "promotion_terms_excluded_product_manufacturers", "promotion_terms_excluded_product_tags",
							"promotion_terms_excluded_product_types", "promotion_terms_excluded_products", "promotion_terms_excluded_sets", "promotion_terms_product_categories",
							"promotion_terms_product_category_groups", "promotion_terms_product_departments", "promotion_terms_product_manufacturers",
							"promotion_terms_product_tags", "promotion_terms_product_types", "promotion_terms_products", "promotion_terms_sets", "promotion_terms_user_types");

						foreach ($promotionTables as $tableName) {
							$promotionSet = executeQuery("select * from " . $tableName . " where promotion_id = ?", $promotionId);
							while ($promotionRow = getNextRow($promotionSet)) {
								$promotionRow['promotion_id'] = $newPromotionId;
								executeQuery("insert into " . $tableName . " values (" . implode(",", array_fill(0, count($promotionRow), "?")) . ")",
									$promotionRow);
							}
						}

						$substitutions['promotion_code'] = $promotionCode;
						$substitutions['description'] = $productRow['description'];
						$substitutions['product_code'] = $productRow['product_code'];
						$substitutions['from_name'] = $orderRow['full_name'];
						$substitutions['from_email_address'] = $contactRow['email_address'];

						$emailId = "";
						$giftGiven = false;
						if (!empty($substitutions['recipient_email_address'])) {
							$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_PROMOTION_PURCHASE_GIVEN", "inactive = 0");
							$emailAddress = $substitutions['recipient_email_address'];
							$giftGiven = true;
						} else {
							$emailAddress = $contactRow['email_address'];
						}
						if (empty($emailId)) {
							$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_PROMOTION_PURCHASE", "inactive = 0");
						}
						if ($giftGiven) {
							$body = "The promotion '%description%' was purchased for you by %from_name%. The promotion code is %promotion_code%.";
							$subject = "Promotion Gift";
						} else {
							$body = "Your purchase of '%description%' is complete. The promotion code is %promotion_code%.";
							$subject = "Promotion Purchase";
						}
						sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "substitutions" => $substitutions, "email_addresses" => $emailAddress, "contact_id" => $contactRow['contact_id']));
						$promoNumber--;
					}

					break;
			}

			$externalSubscriptionClass = CustomField::getCustomFieldData($productRow['product_id'], "EXTERNAL_SUBSCRIPTION_CODE", "PRODUCTS");
			if (!empty($externalSubscriptionClass)) {
				$externalSubscriptionError = "";
                if(class_exists($externalSubscriptionClass)) {
                    try {
                        $externalSubscription = new $externalSubscriptionClass();
                        $response = $externalSubscription->logPurchase($orderId);
                        if (!$response) {
                            $externalSubscriptionError = $externalSubscription->getErrorMessage();
                        }
                    } catch (Exception $exception) {
                        $externalSubscriptionError = $exception->getMessage();
                    }
                } else {
                    $externalSubscriptionError = "External subscription class " . $externalSubscriptionClass . " not found";
                }
				if (!empty($externalSubscriptionError)) {
					addProgramLog("Failed to upload purchase of " . $productRow['product_code'] . ": " . $externalSubscriptionError);
				}
			}

			# Check for new subscriptions

			$orderPaymentRow = getRowFromId("order_payments", "order_id", $orderRow['order_id'], "payment_method_id in (select payment_method_id from payment_methods" .
				" where payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT')))");
			$subscriptionProductRow = getRowFromId("subscription_products", "setup_product_id", $orderItemRow['product_id']);
			$subscriptionSetupProcessed = false;
			$subscriptionRenewalProcessed = false;

			# Process in a while loop so we can easily break out

			while (!empty($subscriptionProductRow)) {
				$subscriptionRow = getRowFromId("subscriptions", "subscription_id", $subscriptionProductRow['subscription_id']);

				# Skip if an unending subscription exists for a time based subscription

				if ($subscriptionRow['disallow_duplicates'] && $subscriptionProductRow['setup_interval_unit'] != "units") {
					$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "contact_id", $orderRow['contact_id'],
						"subscription_id = ? and expiration_date is null and inactive = 0 and customer_paused = 0", $subscriptionProductRow['subscription_id']);
					if (!empty($contactSubscriptionId)) {
						break;
					}
				}

				if ($subscriptionProductRow['setup_interval_unit'] == "units") {
					$recurringPaymentTypeRow = getRowFromId("recurring_payment_types", "recurring_payment_type_id", $subscriptionProductRow['recurring_payment_type_id']);
					$subscriptionProductRow['setup_interval_unit'] = $recurringPaymentTypeRow['interval_unit'];
					$subscriptionProductRow['setup_units_between'] = $recurringPaymentTypeRow['units_between'];
				}
				switch ($subscriptionProductRow['setup_interval_unit']) {
					case "day":
						$nextBillingDate = date("Y-m-d", strtotime("+" . $subscriptionProductRow['setup_units_between'] . " days"));
						break;
					case "week":
						$nextBillingDate = date("Y-m-d", strtotime("+" . $subscriptionProductRow['setup_units_between'] . " weeks"));
						break;
					default:
						$nextBillingDate = date("Y-m-d", strtotime("+" . $subscriptionProductRow['setup_units_between'] . " months"));
						break;
				}

				# If subscription is not allowing duplicates, check for an existing subscription. If one exists, treat it as a renewal. Never use a subscription marked inactive. That was done by an admin and can only be undone by one.

				$contactSubscriptionRow = array();
				if ($subscriptionRow['disallow_duplicates']) {
					$resultSet = executeQuery("select * from contact_subscriptions where contact_id = ? and inactive = 0 and expiration_date is not null and subscription_id = ? and " .
						"expiration_date > date_sub(current_date,interval 1 month) order by expiration_date", $orderRow['contact_id'], $subscriptionProductRow['subscription_id']);
					$contactSubscriptionRow = getNextRow($resultSet);
				}
				$GLOBALS['gChangeLogNotes'] = "Update subscription (8923)";
				if (empty($contactSubscriptionRow)) {
					$resultSet = executeQuery("insert into contact_subscriptions (contact_id,subscription_id,start_date,expiration_date) values (?,?,current_date,?)",
						$orderRow['contact_id'], $subscriptionProductRow['subscription_id'], date("Y-m-d", strtotime($nextBillingDate . " +1 day")));
					$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_subscription_id", $resultSet['insert_id']);
					self::createRecurringPayment($orderItemRow, $orderPaymentRow, $subscriptionProductRow, $nextBillingDate, $parameters, $resultSet['insert_id']);
				} else {

					# if subscription is tagged to ignore skipped and the expiration date is more than a month ago, then make it current

					if ($subscriptionRow['ignore_skipped'] && !empty($contactSubscriptionRow['expiration_date']) && $contactSubscriptionRow['expiration_date'] < date("Y-m-d", strtotime("-1 months"))) {
						$expirationDate = date("Y-m-d", strtotime($nextBillingDate . " +1 day"));
						$unitsRemaining = $subscriptionProductRow['setup_units_between'];
					} else {

						# Update expiration date and remaining units when the subscription is being treated as a renewal

						$expirationDate = (empty($contactSubscriptionRow['expiration_date']) ? "" : date("Y-m-d", strtotime($contactSubscriptionRow['expiration_date'])));
						$unitsRemaining = $contactSubscriptionRow['units_remaining'];
						switch ($subscriptionProductRow['setup_interval_unit']) {
							case "units":
								$unitsRemaining += $subscriptionProductRow['setup_units_between'];
								break;
							case "day":
								$expirationDate = (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate . " +" . $subscriptionProductRow['setup_units_between'] . " days")));
								break;
							case "week":
								$expirationDate = (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate . " +" . $subscriptionProductRow['setup_units_between'] . " weeks")));
								break;
							default:
								$expirationDate = (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate . " +" . $subscriptionProductRow['setup_units_between'] . " months")));
								break;
						}
					}

					# update the recurring payment if one already exists, make sure it is updated to use the account used to purchase this product

					$recurringPaymentResult = executeQuery("select * from recurring_payments where contact_subscription_id = ?", $contactSubscriptionRow['contact_subscription_id']);
					if ($recurringPaymentResult['row_count'] > 1) {
						$emailAddresses = getNotificationEmails("ERRONEOUS_SUBSCRIPTION_PURCHASE");
						$body = "The contact subscription record for " . getDisplayName($contactSubscriptionRow['contact_id'])
							. ", subscription " . getFieldFromId("description", "subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id'])
							. " has " . $recurringPaymentResult['row_count'] . " recurring payments.\n\nThis may cause errors including double billing. Extra recurring payment(s) should be deleted.";
						sendEmail(array("body" => $body, "subject" => "Duplicate recurring payment for " . getDisplayName($contactSubscriptionRow['contact_id']), "email_addresses" => $emailAddresses));
					}
					if ($recurringPaymentRow = getNextRow($recurringPaymentResult)) {
						$recurringPaymentsTable = new DataTable("recurring_payments");
						$recurringPaymentsTable->setSaveOnlyPresent(true);
						$recurringPaymentId = $recurringPaymentRow['recurring_payment_id'];
						$recurringPaymentsTable->saveRecord(array("primary_id" => $recurringPaymentId, "name_values" => array("next_billing_date" => $nextBillingDate, "end_date" => "",
							"account_id" => (empty($orderPaymentRow['account_id']) ? $recurringPaymentRow['account_id'] : $orderPaymentRow['account_id']), "requires_attention" => 0)));
						ContactPayment::notifyCRM($orderRow['contact_id'], true);
					} else {
						self::createRecurringPayment($orderItemRow, $orderPaymentRow, $subscriptionProductRow, $nextBillingDate, $parameters, $contactSubscriptionRow['contact_subscription_id']);
					}

					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					$subscriptionExpirationDate = date('Y-m-d', strtotime($nextBillingDate . ' +1 day'));
					$dataTable->saveRecord(array("name_values" => array("expiration_date" => $subscriptionExpirationDate, "units_remaining" => $unitsRemaining), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
				}
				$GLOBALS['gChangeLogNotes'] = "";

				if ($subscriptionRow['disallow_duplicates']) {
					$resultSet = executeQuery("update contact_subscriptions set inactive = 1 where contact_id = ? and subscription_id = ? and contact_subscription_id <> ?",
						$contactSubscriptionRow['contact_id'], $contactSubscriptionRow['subscription_id'], $contactSubscriptionRow['contact_subscription_id']);
					$resultSet = executeQuery("update recurring_payments set end_date = current_date where contact_id = ? and contact_subscription_id is not null and " .
						"contact_subscription_id in (select contact_subscription_id from contact_subscriptions where contact_id = ? and subscription_id = ? and " .
						"contact_subscription_id <> ?)", $contactSubscriptionRow['contact_id'], $contactSubscriptionRow['contact_id'], $contactSubscriptionRow['subscription_id'], $contactSubscriptionRow['contact_subscription_id']);
				}
				$GLOBALS['gChangeLogNotes'] = "Updating User Subscriptions from Orders class";
				updateUserSubscriptions($contactSubscriptionRow['contact_id']);
				$GLOBALS['gChangeLogNotes'] = "";
				$subscriptionSetupProcessed = true;
				break;
			}

			# Check for subscription renewals

			$subscriptionProductRow = getRowFromId("subscription_products", "product_id", $orderItemRow['product_id']);

			# only process renewal if a setup was not processed. This would only happen in the unlikely even that a product is tagged as BOTH setup and renewal

			while (!$subscriptionSetupProcessed && !empty($subscriptionProductRow)) {
				$subscriptionRow = getRowFromId("subscriptions", "subscription_id", $subscriptionProductRow['subscription_id']);

				$contactSubscriptionRow = array();

				# Check to see if the order is for a specific contact subscription

				$contactSubscriptionId = getFieldFromId("contact_subscription_id", "recurring_payments", "recurring_payment_id", $orderRow['recurring_payment_id']);
				$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_subscription_id", $contactSubscriptionId,
					"contact_id = ? and subscription_id = ?", $orderRow['contact_id'], $subscriptionProductRow['subscription_id']);

				# If none is found, get the oldest active subscription that has expired in the last month or not expired

				if (empty($contactSubscriptionRow)) {
					$contactSubscriptionSet = executeQuery("select * from contact_subscriptions where contact_id = ? and subscription_id = ? and inactive = 0 and " .
						"expiration_date is not null and expiration_date > date_sub(current_date,interval 1 month) order by expiration_date",
						$orderRow['contact_id'], $subscriptionProductRow['subscription_id']);
					$contactSubscriptionRow = getNextRow($contactSubscriptionSet);
				}

				# If there is still no subscription found and an unending subscription IS found, do nothing

				if (empty($contactSubscriptionRow)) {
					$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "contact_id", $orderRow['contact_id'],
						"subscription_id = ? and inactive = 0 and expiration_date is null", $subscriptionProductRow['subscription_id']);
					if (!empty($contactSubscriptionId)) {
						break;
					}
				}

				# If no subscription is found, create one. This should never happen, but it is possible

				$GLOBALS['gChangeLogNotes'] = "Update subscription (6948)";
				if (empty($contactSubscriptionRow)) {
					$emailAddresses = getNotificationEmails("ERRONEOUS_SUBSCRIPTION_PURCHASE");
					$body = "A renewal product was purchased by " . getDisplayName($orderRow['contact_id']) . ", subscription " . $subscriptionRow['description'] . ", but the contact has no subscription.";
					sendEmail(array("body" => $body, "subject" => "No subscription for renewal product", "email_addresses" => $emailAddresses));
				} else {

					# Update the existing subscription.

					$expirationDate = (empty($contactSubscriptionRow['expiration_date']) ? "" : date("Y-m-d", strtotime($contactSubscriptionRow['expiration_date'])));
					$unitsRemaining = $contactSubscriptionRow['units_remaining'];
					switch ($subscriptionProductRow['interval_unit']) {
						case "units":
							$unitsRemaining += $subscriptionProductRow['units_between'];
							break;
						case "day":
							$expirationDate = (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate . " +" . $subscriptionProductRow['units_between'] . " days")));
							break;
						case "week":
							$expirationDate = (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate . " +" . $subscriptionProductRow['units_between'] . " weeks")));
							break;
						default:
							$expirationDate = (empty($expirationDate) ? "" : date("Y-m-d", strtotime($expirationDate . " +" . $subscriptionProductRow['units_between'] . " months")));
							break;
					}
					$nextBillingDate = date("Y-m-d", strtotime($expirationDate . " -1 day"));
					$recurringPaymentResult = executeQuery("select * from recurring_payments where contact_subscription_id = ?", $contactSubscriptionRow['contact_subscription_id']);

					# If there are multiple recurring payments using the same subscription, send a notification

					if ($recurringPaymentResult['row_count'] > 1) {
						$emailAddresses = getNotificationEmails("ERRONEOUS_SUBSCRIPTION_PURCHASE");
						$body = "The contact subscription record for " . getDisplayName($contactSubscriptionRow['contact_id'])
							. ", subscription " . getFieldFromId("description", "subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id'])
							. " has " . $recurringPaymentResult['row_count'] . " recurring payments.\n\nThis may cause errors including double billing. Extra recurring payment(s) should be deleted.";
						sendEmail(array("body" => $body, "subject" => "Duplicate recurring payment for " . getDisplayName($contactSubscriptionRow['contact_id']), "email_addresses" => $emailAddresses));
					}

					# Update or create the recurring payment

					if ($recurringPaymentRow = getNextRow($recurringPaymentResult)) {
						$recurringPaymentsTable = new DataTable("recurring_payments");
						$recurringPaymentsTable->setSaveOnlyPresent(true);
						$recurringPaymentId = $recurringPaymentRow['recurring_payment_id'];
						// handle renewals sold via API
						if ($recurringPaymentRow['next_billing_date'] < $nextBillingDate) {
							$recurringPaymentsTable->saveRecord(array("primary_id" => $recurringPaymentId,
								"name_values" => array("next_billing_date" => $nextBillingDate)));
						}
						$accountRow = getRowFromId("accounts", "account_id", $recurringPaymentRow['account_id']);
						if ($recurringPaymentRow['requires_attention'] || empty($accountRow) || !empty($accountRow['inactive']) || empty($accountRow['account_token'])) {
							$recurringPaymentsTable->saveRecord(array("primary_id" => $recurringPaymentId,
								"name_values" => array("account_id" => $orderPaymentRow['account_id'], "requires_attention" => 0)));
							ContactPayment::notifyCRM($orderRow['contact_id'], true);
						}
					} else {
						self::createRecurringPayment($orderItemRow, $orderPaymentRow, $subscriptionProductRow, $nextBillingDate, $parameters, $contactSubscriptionRow['contact_subscription_id']);
					}
					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					$subscriptionExpirationDate = date('Y-m-d', strtotime($nextBillingDate . ' +1 day'));
					$dataTable->saveRecord(array("name_values" => array("inactive" => 0, "expiration_date" => $subscriptionExpirationDate, "units_remaining" => $unitsRemaining), "primary_id" => $contactSubscriptionRow['contact_subscription_id']));
					updateUserSubscriptions($contactSubscriptionRow['contact_id']);
				}
				$GLOBALS['gChangeLogNotes'] = "";
				$subscriptionRenewalProcessed = true;
				break;
			}
		}
		if (!empty(getPreference("GUNBROKER_AUTOLIST_PRODUCTS"))) {
            try {
                $gunBroker = new GunBroker();
                $gunbrokerResults = $gunBroker->autoUpdateListings($productIds);
                if (!empty(array_filter($gunbrokerResults))) {
                    addProgramLog("GunBroker Listings updated due to order placed: " . GunBroker::parseResults($gunbrokerResults));
                }
            } catch (Exception $e) {
                addProgramLog("Unable to update GunBroker Listings updated due to order placed: " . $e->getMessage());
            }
		}

		# clear product sale price cache if any products had one

		if (function_exists("_localProcessOrderItems")) {
			_localProcessOrderItems($orderId);
		}
	}

	public static function processOrderAutomation($orderId) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		if (empty($orderRow)) {
			return;
		}
        // Make sure waiting quantities saved prior to order do not affect processing rules
        removeCachedData("product_waiting_quantity","*");
        $GLOBALS['gProductWaitingQuantities'] = array();

        $detailedLogging = !empty(getPreference("LOG_ORDER_DIRECTIVE_DETAILED_RESULTS"));
		$fraudRisk = false;
		$noFraudPassed = false;
		$noFraudResult = CustomField::getCustomFieldData($orderId, "NOFRAUD_RESULT", "ORDERS");
		if (!empty($noFraudResult)) {
			$noFraudResult = json_decode($noFraudResult, true);
			if ($noFraudResult['decision'] == "pass") {
				$noFraudPassed = true;
			} else {
				$fraudRisk = true;
			}
		} else {
			$ipQualityData = self::getOrderFraudData($orderId);
			if (is_array($ipQualityData) && is_numeric($ipQualityData['fraud_score'])) {
				$threshold = getPreference("AUTOMATED_ORDER_FRAUD_THRESHOLD");
				if (empty($threshold)) {
					$threshold = 40;
				}
				if ($ipQualityData['fraud_score'] > $threshold) {
					$fraudRisk = true;
				}
			}
		}

		$matchingOrderDirectiveId = "";
		$allOrderDirectives = array();
		$directiveSet = executeQuery("select * from order_directives where client_id = ? and inactive = 0 order by sort_order,order_directive_id", $GLOBALS['gClientId']);
		while ($directiveRow = getNextRow($directiveSet)) {
			$allOrderDirectives[] = $directiveRow;
		}
		$logEntry = "";
		$matchingOrderItemIds = array();
		if (function_exists("_localCustomOrderDirectives")) {
			$customOrderDirectives = _localCustomOrderDirectives($orderRow);
			if (is_array($customOrderDirectives)) {
				$allOrderDirectives = array_merge($customOrderDirectives, $customOrderDirectives);
			}
		}
		foreach ($allOrderDirectives as $directiveRow) {
			$useDirective = true;
			$conditionSet = executeQuery("select * from order_directive_conditions where order_directive_id = ?", $directiveRow['order_directive_id']);
			while ($conditionRow = getNextRow($conditionSet)) {
				switch ($conditionRow['condition_code']) {
					case "PRODUCT_TYPE":
						$typeCount = 0;
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productTypeId = getFieldFromId("product_type_id", "products", "product_id", $row['product_id'], "product_type_id = ?", $conditionRow['condition_data']);
							if (!empty($productTypeId)) {
								$typeCount++;
								$matchingOrderItemIds[] = $row['order_item_id'];
							}
						}
						if ($typeCount == 0) {
                            $notMatchedReason = "no products of type {$conditionRow['condition_data']} were found";
							$useDirective = false;
						}
						break;
					case "NOT_PRODUCT_TYPE":
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productTypeId = getFieldFromId("product_type_id", "products", "product_id", $row['product_id'], "product_type_id = ?", $conditionRow['condition_data']);
							if (!empty($productTypeId)) {
                                $notMatchedReason = "products of type {$conditionRow['condition_data']} were found";
								$useDirective = false;
								break;
							}
						}
						break;
					case "TAGGED":
						$taggedCount = 0;
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $conditionRow['condition_data']);
							if (!empty($productTagLinkId)) {
								$taggedCount++;
								$matchingOrderItemIds[] = $row['order_item_id'];
							}
						}
						if ($taggedCount == 0) {
                            $notMatchedReason = "no products tagged {$conditionRow['condition_data']} were found";
							$useDirective = false;
						}
						break;
					case "NOT_TAGGED":
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $conditionRow['condition_data']);
							if (!empty($productTagLinkId)) {
                                $notMatchedReason = "products tagged {$conditionRow['condition_data']} were found";
								$useDirective = false;
								break;
							}
						}
						break;
					case "CATEGORY":
						$categoryCount = 0;
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $row['product_id'], "product_category_id = ?", $conditionRow['condition_data']);
							if (!empty($productCategoryLinkId)) {
								$categoryCount++;
								$matchingOrderItemIds[] = $row['order_item_id'];
							}
						}
						if ($categoryCount == 0) {
                            $notMatchedReason = "no products in category {$conditionRow['condition_data']} were found";
							$useDirective = false;
						}
						break;
					case "NOT_CATEGORY":
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $row['product_id'], "product_category_id = ?", $conditionRow['condition_data']);
							if (!empty($productCategoryLinkId)) {
                                $notMatchedReason = "products in category {$conditionRow['condition_data']} were found";
								$useDirective = false;
								break;
							}
						}
						break;
					case "DEPARTMENT":
					case "ONLY_DEPARTMENT":
						$departmentCount = 0;
						$nonDepartmentCount = 0;
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							if (ProductCatalog::productIsInDepartment($row['product_id'], $conditionRow['condition_data'])) {
								$departmentCount++;
								$matchingOrderItemIds[] = $row['order_item_id'];
							} else {
								$nonDepartmentCount++;
							}
						}
						if ($departmentCount == 0) {
                            $notMatchedReason = "no products in department {$conditionRow['condition_data']} were found";
                            $useDirective = false;
						} elseif($nonDepartmentCount > 0 && $conditionRow['condition_code'] == "ONLY_DEPARTMENT") {
                            $notMatchedReason = "products in department other than {$conditionRow['condition_data']} were found";
                            $useDirective = false;
                        }
						break;
					case "NOT_DEPARTMENT":
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							if (ProductCatalog::productIsInDepartment($row['product_id'], $conditionRow['condition_data'])) {
                                $notMatchedReason = "products in department {$conditionRow['condition_data']} were found";
								$useDirective = false;
								break;
							}
						}
						break;
					case "NO_LOCAL_INVENTORY":
					case "ALL_LOCAL_INVENTORY":
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						$allLocalInventoryAvailable = true;
						$localInventoryAvailable = false;
						while ($row = getNextRow($resultSet)) {
							$inventoryQuantity = 0;
							$inventorySet = executeQuery("select sum(quantity) from product_inventories where product_id = ? and " .
								"location_id in (select location_id from locations where product_distributor_id is null and ignore_inventory = 0 and inactive = 0)", $row['product_id']);
							if ($inventoryRow = getNextRow($inventorySet)) {
								$inventoryQuantity = $inventoryRow['sum(quantity)'];
								if (empty($inventoryQuantity)) {
									$inventoryQuantity = 0;
								}
							}
							$totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
                            # add back in the quantity in this order, since they are waiting (but waiting should not be negative)
                            $totalWaitingQuantity = max($totalWaitingQuantity - $row['quantity'], 0);
                            $inventoryQuantity -= $totalWaitingQuantity;

							if ($inventoryQuantity < $row['quantity']) {
								$allLocalInventoryAvailable = false;
							} else {
								$localInventoryAvailable = true;
							}
						}
						if ($conditionRow['condition_code'] == "NO_LOCAL_INVENTORY" && $localInventoryAvailable) {
                            $notMatchedReason = "products with local inventory were found";
							$useDirective = false;
						} elseif ($conditionRow['condition_code'] == "ALL_LOCAL_INVENTORY" && !$allLocalInventoryAvailable) {
                            $notMatchedReason = "not all products have local inventory";
							$useDirective = false;
						}
						break;
					case "SHIPPING_METHOD":
						$shippingMethodId = getFieldFromId("shipping_method_id", "orders", "order_id", $orderId, "shipping_method_id = ?", $conditionRow['condition_data']);
						if (empty($shippingMethodId)) {
                            $notMatchedReason = "shipping method {$conditionRow['condition_data']} was not used";
							$useDirective = false;
						}
						break;
					case "PAYMENT_METHOD":
						$paymentMethodId = getFieldFromId("payment_method_id", "order_payments", "order_id", $orderId, "payment_method_id = ?", $conditionRow['condition_data']);
						if (empty($paymentMethodId)) {
                            $notMatchedReason = "payment method {$conditionRow['condition_data']} was not used";
							$useDirective = false;
						}
						break;
					case "PAYMENT_METHOD_TYPE":
						$paymentMethodId = getFieldFromId("payment_method_id", "order_payments", "order_id", $orderId, "payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?)", $conditionRow['condition_data']);
						if (empty($paymentMethodId)) {
                            $notMatchedReason = "payment method type {$conditionRow['condition_data']} was not used";
							$useDirective = false;
						}
						break;
					case "NOT_PAYMENT_METHOD_TYPE":
						$paymentMethodId = getFieldFromId("payment_method_id", "order_payments", "order_id", $orderId, "payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?)", $conditionRow['condition_data']);
						if (!empty($paymentMethodId)) {
                            $notMatchedReason = "payment method type {$conditionRow['condition_data']} was used";
							$useDirective = false;
						}
						break;
					case "TOTAL_OVER":
					case "TOTAL_UNDER":
						$resultSet = executeQuery("select sum(quantity * sale_price) from order_items where order_id = ?", $orderId);
						$cartTotal = 0;
						if ($row = getNextRow($resultSet)) {
							$cartTotal = $row['sum(quantity * sale_price)'];
						}
						$orderTotal = $cartTotal + $orderRow['shipping_charge'] + $orderRow['handling_charge'] + $orderRow['tax_charge'];
						if (!empty($orderRow['donation_id'])) {
							$orderTotal += getFieldFromId("amount", "donations", "donation_id", $orderRow['donation_id']);
						}
						if ($conditionRow['condition_code'] == "TOTAL_OVER" && $orderTotal <= $conditionRow['condition_data']) {
                            $notMatchedReason = "order total not over {$conditionRow['condition_data']}";
							$useDirective = false;
						} else {
							if ($conditionRow['condition_code'] == "TOTAL_UNDER" && $orderTotal >= $conditionRow['condition_data']) {
                                $notMatchedReason = "order total not under {$conditionRow['condition_data']}";
								$useDirective = false;
							}
						}
						break;
					case "ITEMS_OVER":
					case "ITEMS_UNDER":
						$resultSet = executeQuery("select sum(quantity) from order_items where order_id = ?", $orderId);
						$cartTotal = 0;
						if ($row = getNextRow($resultSet)) {
							$cartTotal = $row['sum(quantity)'];
						}
						if ($conditionRow['condition_code'] == "ITEMS_OVER" && $cartTotal <= $conditionRow['condition_data']) {
                            $notMatchedReason = "item total not over {$conditionRow['condition_data']}";
							$useDirective = false;
						} else {
							if ($conditionRow['condition_code'] == "ITEMS_UNDER" && $cartTotal >= $conditionRow['condition_data']) {
                                $notMatchedReason = "item total not under {$conditionRow['condition_data']}";
								$useDirective = false;
							}
						}
						break;
					case "SAME_ADDRESS":
						$pickup = getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
						if (!$pickup) {
							$addressRow = (empty($orderRow['address_id']) ? Contact::getContact($orderRow['contact_id']) : getRowFromId("addresses", "address_id", $orderRow['address_id']));
							$resultSet = executeQuery("select count(*) from order_payments where order_id = ? and account_id is not null and " .
								"account_id in (select account_id from accounts where address_id is null or address_id in (select address_id from addresses where address_1 = ?))", $orderId, $addressRow['address_1']);
							if ($row = getNextRow($resultSet)) {
								if ($row['count(*)'] == 0) {
                                    $notMatchedReason = "billing address does not match shipping";
									$useDirective = false;
								}
							} else {
                                $notMatchedReason = "billing address does not match shipping";
								$useDirective = false;
							}
						}
						break;
					case "PROXIMITY_UNDER":
					case "PROXIMITY_OVER":
						if (empty($orderRow['ip_address'])) {
							$useDirective = false;
							break;
						}
						$ipAddress = $orderRow['ip_address'];
						$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
						if (empty($ipAddressData)) {
							$curlHandle = curl_init("http://ip-api.com/json/" . $ipAddress);
							curl_setopt($curlHandle, CURLOPT_HEADER, 0);
							curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 15);
							curl_setopt($curlHandle, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
							$ipAddressRaw = curl_exec($curlHandle);
							curl_close($curlHandle);
							if (empty($ipAddressRaw)) {
								$ipAddressData = array();
							} else {
								$ipAddressData = json_decode($ipAddressRaw, true);
							}
							if (!empty($ipAddressData)) {
								executeQuery("insert ignore into ip_address_metrics (ip_address,country_id,city,state,postal_code,latitude,longitude) values (?,?,?,?,?, ?,?)",
									$ipAddress, getFieldFromId("country_id", "countries", "country_code", $ipAddressData['countryCode']), $ipAddressData['city'], $ipAddressData['region'],
									$ipAddressData['zip'], $ipAddressData['lat'], $ipAddressData['lon']);
								$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
							}
						}
						if (empty($ipAddressData)) {
                            $notMatchedReason = "geo-location data for IP address could not be found";
							$useDirective = false;
							break;
						}
						if (!empty($ipAddressData['latitude']) && !empty($ipAddressData['longitude'])) {
							$orderPoint = array("latitude" => $ipAddressData['latitude'], "longitude" => $ipAddressData['longitude']);
						} else {
							if (!empty($ipAddressData['postal_code'])) {
								$orderPoint = getPointForZipCode($ipAddressData['postal_code']);
							} else {
								$orderPoint = array();
							}
						}
						if (empty($orderPoint)) {
                            $notMatchedReason = "location for order point could not be determined";
							$useDirective = false;
							break;
						}
						$addressRow = (empty($orderRow['address_id']) ? Contact::getContact($orderRow['contact_id']) : getRowFromId("addresses", "address_id", $orderRow['address_id']));
						$shipPoint = getPointForZipCode($addressRow['postal_code']);
						if (empty($shipPoint)) {
                            $notMatchedReason = "location for shipping address could not be determined";
							$useDirective = false;
							break;
						}
						$distance = calculateDistance($orderPoint, $shipPoint);
						if ($conditionRow['condition_code'] == "PROXIMITY_OVER" && $distance <= $conditionRow['condition_data']) {
                            $notMatchedReason = "distance from order point to shipping address is not over {$conditionRow['condition_data']}";
							$useDirective = false;
						} else {
							if ($conditionRow['condition_code'] == "PROXIMITY_UNDER" && $distance >= $conditionRow['condition_data']) {
                                $notMatchedReason = "distance from order point to shipping address is not under {$conditionRow['condition_data']}";
								$useDirective = false;
							}
						}
						break;
					case "AVAILABLE_FROM":
						$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
						while ($row = getNextRow($resultSet)) {
							$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories",
								"location_id", $conditionRow['condition_data'], "product_id = ? and quantity >= ?", $row['product_id'], $row['quantity']);
							if (empty($productInventoryId)) {
                                $notMatchedReason = "not all products are available from location {$conditionRow['condition_data']}";
								$useDirective = false;
								break;
							}
						}
						break;
					case "PREVIOUS_ORDERS":
						$resultSet = executeQuery("select count(*) from orders where contact_id = ? and order_id < ?", $orderId);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] < $conditionRow['condition_data']) {
                                $notMatchedReason = "contact has less than {$conditionRow['condition_data']} previous orders";
								$useDirective = false;
							}
						} else {
                            $notMatchedReason = "contact has less than {$conditionRow['condition_data']} previous orders";
							$useDirective = false;
						}
						break;
					case "IN_STATE":
					case "NOT_IN_STATE":
						$addressRow = (empty($orderRow['address_id']) ? Contact::getContact($orderRow['contact_id']) : getRowFromId("addresses", "address_id", $orderRow['address_id']));
						$stateList = explode(",", $conditionRow['condition_data']);
						if ($conditionRow['condition_code'] == "IN_STATE" && !in_array($addressRow['state'], $stateList)) {
                            $notMatchedReason = "contact address is not in state {$conditionRow['condition_data']}";
							$useDirective = false;
						} else {
							if ($conditionRow['condition_code'] == "NOT_IN_STATE" && in_array($addressRow['state'], $stateList)) {
                                $notMatchedReason = "contact address is in state {$conditionRow['condition_data']}";
								$useDirective = false;
							}
						}
						break;
					case "FFL_EXISTS":
						$fileId = (new FFL($orderRow['federal_firearms_licensee_id']))->getFieldData("file_id");
						if (empty($fileId)) {
                            $notMatchedReason = "no FFL license file found";
							$useDirective = false;
						}
						break;
					default:
                        $notMatchedReason = "unknown condition code";
						$useDirective = false;
						break;
				}
				if (!$useDirective) {
					break;
				}
			}
			if ($useDirective && function_exists("_localCustomOrderUseDirective")) {
				$useDirective = _localCustomOrderUseDirective($orderRow, $directiveRow);
                if($detailedLogging && !$useDirective) {
                    $notMatchedReason = "custom directive function returned false";
                }
			}
			if ($useDirective) {
				$matchingOrderDirectiveId = $directiveRow['order_directive_id'];
				$logEntry .= "Order Directive matched: '{$directiveRow['description']}' for order ID $orderId\n";
				break;
			} elseif($detailedLogging && !empty($notMatchedReason)) {
                $logEntry .= "Order Directive '{$directiveRow['description']}' for order ID $orderId not matched because of condition {$conditionRow['condition_code']}: $notMatchedReason\n";
            }
		}
		if (empty($matchingOrderDirectiveId)) {
            if($detailedLogging && !empty($logEntry)) {
                addProgramLog($logEntry);
            }
			return;
		}

		if ($noFraudPassed) {
			$returnArray = self::capturePayment($orderId);
			if (array_key_exists('error_message', $returnArray)) {
				$logEntry .= "Capturing payment returned error: " . $returnArray['error_message'] . "\n";
			} else {
				$logEntry .= "NoFraud passed the order. Payment captured successfully.\n";
			}
		}
		$unCapturedPaymentExists = getFieldFromId("order_payment_id", "order_payments", "order_id", $orderId, "not_captured = 1");
		$shipmentExists = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId);

        // 2023-05-18 Removing Regardless Actions but leaving code in place for future use.
		$regardlessActions = array();
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$errorsFound = false;
		$actionCompleted = false;
		$ignoreLocalInventory = !empty(getPreference("AUTOMATED_ORDER_IGNORE_LOCAL_INVENTORY"));
		$actionSet = executeQuery("select * from order_directive_actions where order_directive_id = ?", $matchingOrderDirectiveId);
		while ($actionRow = getNextRow($actionSet)) {
			if (!in_array($actionRow['action_code'], $regardlessActions)) {
				if ($fraudRisk) {
					$logEntry .= "Order Action skipped because of fraud risk: " . $actionRow['action_code'] . "\n";
					continue;
				}
				if ($unCapturedPaymentExists) {
					$logEntry .= "Order Action skipped because of uncaptured payments: " . $actionRow['action_code'] . "\n";
					continue;
				}
				if ($shipmentExists) {
					$logEntry .= "Order Action skipped because shipments already exist: " . $actionRow['action_code'] . "\n";
					continue;
				}
				if ($orderRow['deleted']) {
					$logEntry .= "Order Action skipped because order is deleted: " . $actionRow['action_code'] . "\n";
					continue;
				}
				if ($orderRow['date_completed']) {
					$logEntry .= "Order Action skipped because order is marked completed: " . $actionRow['action_code'] . "\n";
					continue;
				}
			}
			$logEntry .= "Order Action taken: " . $actionRow['action_code'] . "\n";
			$matchingItemsOnly = false;
			switch ($actionRow['action_code']) {
				case "SET_STATUS":
					$logEntry .= "Setting order status: " . $actionRow['action_data'] . "\n";
					$orderStatusId = getFieldFromId("order_status_id", "orders", "order_id", $orderId);
					if (empty($orderStatusId)) {
						Order::updateOrderStatus($orderId, $actionRow['action_data']);
						$actionCompleted = $actionCompleted ?: in_array($actionRow['action_code'], $regardlessActions);
					} else {
						$logEntry .= "Cannot set status, already set\n";
					}
					break;
				case "SHIP_LOCATION_MATCHING":
					$matchingItemsOnly = true;
				case "SHIP_LOCATION":
					$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId);
					if (!empty($orderShipmentId)) {
						$logEntry .= "Shipment already exists\n";
						$errorsFound = true;
						break;
					}
					$logEntry .= "Create Shipment: " . $actionRow['action_data'] . "\n";
					$locationRow = getRowFromId("locations", "location_id", $actionRow['action_data'], "inactive = 0 and location_id not in (select location_id from location_credentials where inactive = 1)");
					if (empty($locationRow)) {
						$logEntry .= "Invalid shipment location\n";
						$errorsFound = true;
						break;
					}
					$locationId = $locationRow['location_id'];
					if (empty($locationRow['product_distributor_id']) || !empty($locationRow['primary_location'])) {
						$inventoryLocationId = $locationId;
					} else {
						$inventoryLocationId = getFieldFromId("location_id", "locations", "product_distributor_id", $locationRow['product_distributor_id'], "primary_location = 1");
						if (empty($inventoryLocationId)) {
							$inventoryLocationId = $locationId;
						}
					}

					$orderItems = array();
					$resultSet = executeQuery("select * from order_items where order_id = ?" .
						($matchingItemsOnly ? " and order_item_id in (" . implode(",", $matchingOrderItemIds) . ")" : ""), $orderId);
					while ($row = getNextRow($resultSet)) {
						$inventoryQuantityRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $inventoryLocationId);
						if (empty($inventoryQuantityRow)) {
							continue;
						}
						$inventoryQuantity = $inventoryQuantityRow['quantity'];
						if (empty($inventoryQuantity)) {
							$inventoryQuantity = 0;
						}
						$totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
						# add back in the quantity in this order, since they are waiting (but waiting should not be negative)
						$totalWaitingQuantity = max($totalWaitingQuantity - $row['quantity'], 0);
						$inventoryQuantity -= $totalWaitingQuantity;

						if ($inventoryQuantity < $row['quantity']) {
							continue;
						}
						$orderItems[$row['order_item_id']] = array("order_item_id" => $row['order_item_id'], "quantity" => $row['quantity']);
					}
					if (empty($orderItems)) {
						$logEntry .= "No items found to ship\n";
						$errorsFound = true;
						break;
					}

					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					if (!empty($shippingMethodRow['pickup'])) {
						foreach ($orderItems as $index => $thisOrderItem) {
							$orderItems[$index]['ship_to'] = "dealer";
						}
					}

					$response = Order::createShipment($orderId, $orderItems, $locationId);
					if ($response['success']) {
						$actionCompleted = true;
					} else {
						$errorsFound = true;
					}
					if($detailedLogging) {
						$logEntry .= $response['detailed_results'] . "\n";
					}
					$logEntry .= $response['result'] . "\n";
					break;
				case "SHIP_CHEAPEST":
					$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId);
					if (!empty($orderShipmentId)) {
						$logEntry .= "Shipment already exists\n";
						$errorsFound = true;
						break;
					}
					$logEntry .= "Ship from cheapest distributor: " . $actionRow['action_data'] . "\n";
					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					$locationGroupId = false;
					$pickupLocationId = false;
					if ($shippingMethodRow['pickup'] && !empty($shippingMethodRow['location_id'])) {
						$pickupLocationId = $shippingMethodRow['location_id'];
						$locationGroupId = getFieldFromId("location_group_id", "locations", "location_id", $shippingMethodRow['location_id']);
					}
					$locationArray = array();
					if (!empty($locationGroupId)) {
						$resultSet = executeQuery("select * from locations where location_group_id = ? and inactive = 0 and cannot_ship = 0 and (" .
							($ignoreLocalInventory ? "warehouse_location = 1" : "product_distributor_id is null") .
							" or (product_distributor_id is not null and location_id in (select location_id from location_credentials where inactive = 0))) and client_id = ?",
							$locationGroupId, $GLOBALS['gClientId']);
					} else {
						$resultSet = executeQuery("select * from locations where inactive = 0 and cannot_ship = 0 and (" .
							($ignoreLocalInventory ? "warehouse_location = 1" : "product_distributor_id is null") .
							" or (product_distributor_id is not null and primary_location = 1 and location_id in (select location_id from location_credentials where inactive = 0))) and client_id = ?",
							$GLOBALS['gClientId']);
					}
					while ($row = getNextRow($resultSet)) {
						$row['total_cost'] = 0;
						$locationArray[] = $row;
					}

					$orderItems = array();
					$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
					while ($row = getNextRow($resultSet)) {
						if (!empty($pickupLocationId)) {
							$localInventoryQuantity = 0;
							$inventorySet = executeQuery("select quantity from product_inventories where product_id = ? and location_id = ?", $row['product_id'], $pickupLocationId);
							if ($inventoryRow = getNextRow($inventorySet)) {
								$localInventoryQuantity = $inventoryRow['quantity'];
								if (empty($localInventoryQuantity)) {
									$localInventoryQuantity = 0;
								}
							}
							$totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
							$localInventoryQuantity -= $totalWaitingQuantity;
							if ($localInventoryQuantity >= $row['quantity']) {
								continue;
							}
						}
						foreach ($locationArray as $index => $locationInfo) {
							if (empty($locationInfo['product_distributor_id']) || !empty($locationInfo['primary_location'])) {
								$inventoryLocationId = $locationInfo['location_id'];
							} else {
								$inventoryLocationId = getFieldFromId("location_id", "locations", "product_distributor_id", $locationInfo['product_distributor_id'], "primary_location = 1");
								if (empty($inventoryLocationId)) {
									$inventoryLocationId = $locationInfo['location_id'];
								}
							}

							$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $inventoryLocationId);
							if (empty($productInventoryRow)) {
								unset($locationArray[$index]);
								$logEntry .= "No inventory record found for Product ID " . $row['product_id'] . " at location ID " . $inventoryLocationId . "\n";
								continue;
							}
							$inventoryQuantity = $productInventoryRow['quantity'];
							if (empty($inventoryQuantity)) {
								$inventoryQuantity = 0;
							}
							$totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
							# add back in the quantity in this order, since they are waiting (but waiting should not be negative)
							$totalWaitingQuantity = max($totalWaitingQuantity - $row['quantity'], 0);
							$inventoryQuantity -= $totalWaitingQuantity;

							if ($inventoryQuantity < $row['quantity']) {
								$logEntry .= "Not enough Inventory for Product ID " . $row['product_id'] . " at location ID " . $inventoryLocationId . ", " . $row['quantity'] . " ordered, " . $inventoryQuantity . " in inventory, " . $totalWaitingQuantity . " waiting\n";
								unset($locationArray[$index]);
								continue;
							}
							$logEntry .= "Found Inventory for Product ID " . $row['product_id'] . " at location ID " . $inventoryLocationId . ", " . $row['quantity'] . " ordered, " . $inventoryQuantity . " in inventory\n";
							$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $inventoryLocationId, $productInventoryRow, false);
							if (empty($cost)) {
								$logEntry .= "Found Inventory for Product ID " . $row['product_id'] . " at location ID " . $inventoryLocationId . ", but no cost for inventory\n";
								unset($locationArray[$index]);
								continue;
							}
							if ($cost >= $row['sale_price']) {
								$logEntry .= "Found Inventory for Product ID " . $row['product_id'] . " at location ID " . $inventoryLocationId . ", but cost is greater than sale price\n";
								unset($locationArray[$index]);
								continue;
							}
							$locationArray[$index]['total_cost'] += ($row['quantity'] * $cost);
                        }
						$orderItems[$row['order_item_id']] = array("order_item_id" => $row['order_item_id'], "quantity" => $row['quantity']);
					}
					if (empty($orderItems) || empty($locationArray)) {
						$logEntry .= "No order items or unable to find location with all products: " . jsonEncode($orderItems) . " - " . jsonEncode($locationArray) . "\n";
						$errorsFound = true;
						break;
					}
					$locationId = false;
					$saveAmount = 0;
					foreach ($locationArray as $thisLocation) {
						if ($locationId === false || $thisLocation['total_cost'] < $saveAmount) {
							$locationId = $thisLocation['location_id'];
							$saveAmount = $thisLocation['total_cost'];
						}
					}
					if (empty($locationId)) {
						$logEntry .= "Unable to find location with all products\n";
						$errorsFound = true;
						break;
					}

					if (!empty($shippingMethodRow['pickup'])) {
						foreach ($orderItems as $index => $thisOrderItem) {
							$orderItems[$index]['ship_to'] = "dealer";
						}
					}

					$response = Order::createShipment($orderId, $orderItems, $locationId);
					if ($response['success']) {
						$actionCompleted = true;
					} else {
						$errorsFound = true;
					}
					if($detailedLogging) {
						$logEntry .= $response['detailed_results'] . "\n";
					}
					$logEntry .= $response['result'] . "\n";
					break;
				case "SHIP_TWO_DISTRIBUTORS":
					$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId);
					if (!empty($orderShipmentId)) {
						$logEntry .= "Shipment already exists\n";
						$errorsFound = true;
						break;
					}
					$logEntry .= "Ship from up to two distributors: " . $actionRow['action_data'] . "\n";
					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					$locationGroupId = false;
					$pickupLocationId = false;
					if ($shippingMethodRow['pickup'] && !empty($shippingMethodRow['location_id'])) {
						$pickupLocationId = $shippingMethodRow['location_id'];
						$locationGroupId = getFieldFromId("location_group_id", "locations", "location_id", $shippingMethodRow['location_id']);
					}
					$locationArray = array();
					if (!empty($locationGroupId)) {
						$resultSet = executeQuery("select * from locations where location_group_id = ? and inactive = 0 and cannot_ship = 0 and " .
							"(warehouse_location = 1 or (product_distributor_id is not null and location_id in (select location_id from location_credentials where inactive = 0))) and client_id = ?",
							$locationGroupId, $GLOBALS['gClientId']);
					} else {
						$resultSet = executeQuery("select * from locations where (product_distributor_id is not null and primary_location = 1 and " .
							"location_id not in (select location_id from location_credentials where inactive = 1) or warehouse_location = 1) and " .
							"inactive = 0 and cannot_ship = 0 and client_id = ?", $GLOBALS['gClientId']);
					}
					while ($row = getNextRow($resultSet)) {
						$row['available_items'] = array();
						$locationArray[] = $row;
					}

					$orderItems = array();
					$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
					while ($row = getNextRow($resultSet)) {
						if (!empty($pickupLocationId)) {
							$localInventoryQuantity = 0;
							$inventorySet = executeQuery("select quantity from product_inventories where product_id = ? and location_id = ?", $row['product_id'], $pickupLocationId);
							if ($inventoryRow = getNextRow($inventorySet)) {
								$localInventoryQuantity = $inventoryRow['quantity'];
								if (empty($localInventoryQuantity)) {
									$localInventoryQuantity = 0;
								}
							}
							$totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
							$localInventoryQuantity -= $totalWaitingQuantity;
							if ($localInventoryQuantity >= $row['quantity']) {
								continue;
							}
						}
						$foundLocation = false;
						foreach ($locationArray as $index => $locationInfo) {
							if (empty($locationInfo['product_distributor_id']) || !empty($locationInfo['primary_location'])) {
								$inventoryLocationId = $locationInfo['location_id'];
							} else {
								$inventoryLocationId = getFieldFromId("location_id", "locations", "product_distributor_id", $locationInfo['product_distributor_id'], "primary_location = 1");
								if (empty($inventoryLocationId)) {
									$inventoryLocationId = $locationInfo['location_id'];
								}
							}

							$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $inventoryLocationId);
							if (empty($productInventoryRow)) {
								continue;
							}
							$inventoryQuantity = $productInventoryRow['quantity'];
							if (empty($inventoryQuantity)) {
								$inventoryQuantity = 0;
							}
							$totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
							# add back in the quantity in this order, since they are waiting (but waiting should not be negative)
							$totalWaitingQuantity = max($totalWaitingQuantity - $row['quantity'], 0);
							$inventoryQuantity -= $totalWaitingQuantity;

							if ($inventoryQuantity < $row['quantity']) {
								continue;
							}
							$logEntry .= "Found Inventory for Product ID " . $row['product_id'] . " at location ID " . $inventoryLocationId . ", " . $row['quantity'] . " ordered, " . $inventoryQuantity . " in inventory\n";
							$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $inventoryLocationId, $productInventoryRow,false);
							if (empty($cost)) {
								continue;
							}
							if ($cost >= $row['sale_price']) {
								continue;
							}
							$foundLocation = true;
							$locationArray[$index]['available_items'] = array("order_item_id" => $row['order_item_id'], "quantity" => $row['quantity'], "cost" => $cost);
						}
						if ($foundLocation) {
							$orderItems[$row['order_item_id']] = array("order_item_id" => $row['order_item_id'], "quantity" => $row['quantity']);
						}
					}
					if (empty($orderItems) || empty($locationArray)) {
						$logEntry .= "No order items or unable to find location with products: " . jsonEncode($orderItems) . " - " . jsonEncode($locationArray) . "\n";
						$errorsFound = true;
						break;
					}
					$locationId = false;
					$availableItems = array();
					$saveCount = 0;
					$saveTotal = 0;
					foreach ($locationArray as $locationIndex => $thisLocation) {
						if (count($thisLocation['available_items']) == 0) {
							continue;
						}
						$thisTotal = 0;
						foreach ($thisLocation['available_items'] as $thisAvailableItem) {
							$thisTotal += ($thisAvailableItem['quantity'] * $thisAvailableItem['cost']);
						}
						if (count($thisLocation['available_items']) > $saveCount) {
							$locationId = $thisLocation['location_id'];
							$availableItems = $thisLocation['available_items'];
							$saveCount = count($thisLocation['available_items']);
							$saveTotal = $thisTotal;
						} elseif (count($thisLocation['available_items']) == $saveCount) {
							if ($thisTotal < $saveTotal) {
								$locationId = $thisLocation['location_id'];
								$availableItems = $thisLocation['available_items'];
								$saveCount = count($thisLocation['available_items']);
								$saveTotal = $thisTotal;
							}
						}
					}
					if (empty($locationId) || empty($availableItems)) {
						$logEntry .= "Unable to find location with any products\n";
						$errorsFound = true;
						break;
					}

					foreach ($availableItems as $index => $thisOrderItem) {
						if (!empty($shippingMethodRow['pickup'])) {
							$availableItems[$index]['ship_to'] = "dealer";
						}
						unset($availableItems[$index]['cost']);
						unset($orderItems[$availableItems['order_item_id']]);
					}
					unset($locationArray[$locationIndex]);

					$response = Order::createShipment($orderId, $availableItems, $locationId);
					if ($response['success']) {
						$actionCompleted = true;
					} else {
						$errorsFound = true;
					}
					if($detailedLogging) {
						$logEntry .= $response['detailed_results'] . "\n";
					}
					$logEntry .= $response['result'] . "\n";
					if (!empty($orderItems) && $actionCompleted) {
						$locationId = false;
						$availableItems = array();
						$saveCount = 0;
						$saveTotal = 0;
						foreach ($locationArray as $locationIndex => $thisLocation) {
							if (count($thisLocation['available_items']) == 0) {
								continue;
							}
							$thisTotal = 0;
							foreach ($thisLocation['available_items'] as $thisAvailableItem) {
								$thisTotal += ($thisAvailableItem['quantity'] * $thisAvailableItem['cost']);
							}
							if (count($thisLocation['available_items']) > $saveCount) {
								$locationId = $thisLocation['location_id'];
								$availableItems = $thisLocation['available_items'];
								$saveCount = count($thisLocation['available_items']);
								$saveTotal = $thisTotal;
							} elseif (count($thisLocation['available_items']) == $saveCount) {
								if ($thisTotal < $saveTotal) {
									$locationId = $thisLocation['location_id'];
									$availableItems = $thisLocation['available_items'];
									$saveCount = count($thisLocation['available_items']);
									$saveTotal = $thisTotal;
								}
							}
						}
						if (empty($locationId) || empty($availableItems)) {
							$logEntry .= "Unable to find secondary location with any products\n";
							break;
						}

						foreach ($availableItems as $index => $thisOrderItem) {
							if (!empty($shippingMethodRow['pickup'])) {
								$availableItems[$index]['ship_to'] = "dealer";
							}
							unset($availableItems[$index]['cost']);
							unset($orderItems[$availableItems['order_item_id']]);
						}
						unset($locationArray[$locationIndex]);

						$response = Order::createShipment($orderId, $availableItems, $locationId);
						if ($response['success']) {
							$actionCompleted = true;
						} else {
							$errorsFound = true;
						}
						if($detailedLogging) {
							$logEntry .= $response['detailed_results'] . "\n";
						}
						$logEntry .= $response['result'] . "\n";
					}
					break;
				case "SEND_EMAIL":
					$logEntry .= "Send email: " . $actionRow['action_data'] . "\n";
					$body = "Order ID " . $orderId . " met the conditions of automatic order processing control '" . $directiveRow['description'] . "'.";
					sendEmail(array("subject" => "Order placed", "body" => $body, "email_address" => $actionRow['action_data']));
                    $actionCompleted = $actionCompleted ?: in_array($actionRow['action_code'], $regardlessActions);
                    break;
				case "SEND_CUSTOMER_EMAIL":
					$logEntry .= "Send customer email: " . $actionRow['action_data'] . "\n";
					$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $orderRow['contact_id']);
					$shippingAddress = (empty($orderRow['address_id']) ? Contact::getContact($orderRow['contact_id']) : getRowFromId("addresses", "address_id", $orderRow['address_id']));
					$substitutions = array_merge($shippingAddress, $orderRow);
					$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
					if (!empty($shippingAddress['address_2'])) {
						$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
					}
					$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
					if (!empty($shippingAddress['postal_code'])) {
						$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
					}
					if (!empty($shippingCityLine)) {
						$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
					}
					if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
						$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
					}

					$resultSet = executeQuery("select quantity,sum(quantity * sale_price) from order_items where order_id = ? and deleted = 0", $orderId);
					$cartTotal = 0;
					$cartTotalQuantity = 0;
					if ($row = getNextRow($resultSet)) {
						$cartTotalQuantity += $row['quantity'];
						$cartTotal = $row['sum(quantity * sale_price)'];
					}
					$orderTotal = $cartTotal + $orderRow['shipping_charge'] + $orderRow['handling_charge'] + $orderRow['tax_charge'];
					$donationRow = getRowFromId("donations", "donation_id", $orderRow['donation_id']);
					if (!empty($orderRow['donation_id'])) {
						$orderTotal += $donationRow['amount'];
					}

					$substitutions['order_total'] = number_format($orderTotal, 2);
					$substitutions['tax_charge'] = number_format($orderRow['tax_charge'], 2);
					$substitutions['shipping_charge'] = number_format($orderRow['shipping_charge'], 2);
					$substitutions['handling_charge'] = number_format($orderRow['handling_charge'], 2);
					$substitutions['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					$substitutions['location'] = getFieldFromId("description", "locations", "location_id", getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']));
					$substitutions['cart_total'] = number_format($cartTotal, 2);
					$substitutions['cart_total_quantity'] = $cartTotalQuantity;
					$substitutions['donation_amount'] = number_format($donationRow['amount'], 2);
					$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $donationRow['designation_id']);
					$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']);
					$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
					$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
					$substitutions['ffl_name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
					$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
					$substitutions['ffl_license_number'] = $fflRow['license_number'];

					$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
					$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];
					$substitutions['domain_name'] = getDomainName();
					$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderId), $substitutions);
					if (function_exists("_localServerAdditionalOrderSubstitutions")) {
						$substitutions = array_merge($substitutions, _localServerAdditionalOrderSubstitutions($orderId));
					}

					$emailId = getFieldFromId("email_id", "emails", "email_id", $actionRow['action_data'], "inactive = 0");
					if (!empty($emailId)) {
						sendEmail(array("email_address" => $emailAddress, "email_id" => $emailId, "substitutions" => $substitutions, "contact_id" => $orderRow['contact_id']));
					}
                    $actionCompleted = $actionCompleted ?: in_array($actionRow['action_code'], $regardlessActions);
                    break;
			}
		}
		if ($errorsFound && !$actionCompleted) {
			$logEntry .= "Errors found, nothing processed\n";
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		} else {
			if ($errorsFound) {
				$logEntry .= "Errors found but some actions completed successfully\n";
			}
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		addProgramLog($logEntry);
	}

	public static function getOrderFraudData($orderId) {
		$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "COREGUARD_ENABLED", "client_id = ?", $GLOBALS['gDefaultClientId']);
		$clientIpQualitySetup = getFieldFromId("text_data", "custom_field_data", "custom_field_id", $customFieldId, "primary_identifier = ?", $GLOBALS['gClientRow']['contact_id']);
		$ipQualityScoreApiKey = getPreference("IP_QUALITY_SCORE_API_KEY");
		if (empty($clientIpQualitySetup) || empty($ipQualityScoreApiKey)) {
			return false;
		}
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$accountId = getFieldFromId("account_id", "order_payments", "order_id", $orderId);
		if (empty($accountId)) {
			$accountId = $orderRow['account_id'];
		}
		if (empty($accountId)) {
			$billingAddressRow = $contactRow;
		} else {
			$accountRow = getRowFromId("accounts", "account_id", $accountId);
			$billingAddressId = $accountRow['address_id'];
			if (empty($billingAddressId)) {
				$billingAddressRow = $contactRow;
			} else {
				$billingAddressRow = getRowFromId("addresses", "address_id", $billingAddressId);
				if (empty($billingAddressRow['address_1'])) {
					$billingAddressRow = $contactRow;
				}
			}
		}
		if (empty($orderRow['address_id'])) {
			$shippingAddressRow = $contactRow;
		} else {
			$shippingAddressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
			if (empty($shippingAddressRow['address_1'])) {
				$shippingAddressRow = $contactRow;
			}
		}

		$parameters = array();
		$parameters['ip_address'] = $orderRow['ip_address'];
		$parameters['billing_first_name'] = $contactRow['first_name'];
		$parameters['billing_last_name'] = $contactRow['last_name'];
		$parameters['billing_company'] = $contactRow['business_name'];
		$parameters['billing_country'] = getFieldFromId("country_code", "countries", "country_id", $billingAddressRow['country_id']);
		$parameters['billing_address_1'] = $billingAddressRow['address_1'];
		$parameters['billing_address_2'] = $billingAddressRow['address_2'];
		$parameters['billing_city'] = $billingAddressRow['city'];
		$parameters['billing_region'] = $billingAddressRow['state'];
		$parameters['billing_postcode'] = $billingAddressRow['postal_code'];
		$parameters['billing_email'] = $contactRow['email_address'];
		$parameters['billing_phone'] = str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $orderRow['phone_number']))));
		$parameters['shipping_first_name'] = $contactRow['first_name'];
		$parameters['shipping_last_name'] = $contactRow['last_name'];
		$parameters['shipping_company'] = $contactRow['business_name'];
		$parameters['shipping_country'] = getFieldFromId("country_code", "countries", "country_id", $shippingAddressRow['country_id']);
		$parameters['shipping_address_1'] = $shippingAddressRow['address_1'];
		$parameters['shipping_address_2'] = $shippingAddressRow['address_2'];
		$parameters['shipping_city'] = $shippingAddressRow['city'];
		$parameters['shipping_region'] = $shippingAddressRow['state'];
		$parameters['shipping_postcode'] = $shippingAddressRow['postal_code'];
		$parameters['shipping_email'] = $contactRow['email_address'];
		$parameters['shipping_phone'] = str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $orderRow['phone_number']))));

		return IpQualityScore::getIpQualityData($parameters);
	}

	public static function createShipment($orderId, $orderItems, $locationId) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
		$sendPickupTracking = (empty(getPreference("NO_TRACKING_FOR_PICKUP_ORDERS")) ? $shippingMethodRow['pickup'] : 0);

		$returnArray = array();
		$locationDescription = getFieldFromId("description", "locations", "location_id", $locationId);
		$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
		if (empty($productDistributorId)) {
			$resultSet = executeQuery("insert into order_shipments (order_id,location_id,date_shipped) values (?,?,current_date)", $orderId, $locationId);
			$orderShipmentId = $resultSet['insert_id'];
			$orderItemCount = 0;
			foreach ($orderItems as $thisOrderItem) {
				$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
				$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $locationId);
				executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
					$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
				$orderItemCount++;

# add to product inventory log

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
			if ($orderItemCount == 0) {
				executeQuery("delete from remote_order_items where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
				executeQuery("delete from remote_orders where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
				executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
				$returnArray['success'] = false;
				$returnArray['result'] = "No items found to ship";
			} else {
				$returnArray['success'] = true;
				$returnArray['result'] = sprintf("Shipment created for %s items from %s.", $orderItemCount, $locationDescription);
			}
		} else {
			$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
			if ($GLOBALS['gDevelopmentServer']) {
				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $orderId, $orderId);
				$remoteOrderId = $orderSet['insert_id'];
				foreach ($orderItems as $thisOrderItem) {
					executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
						$remoteOrderId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity']);
				}
				$response = array('dealer' => array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderId, "ship_to" => $GLOBALS['gClientName']));
			} else {
				$response = $productDistributor->placeOrder($orderId, $orderItems);
				if ($response === false || array_key_exists("error_message", $response)) {
					$returnArray['success'] = false;
					$returnArray['result'] = sprintf("Creating shipment from %s failed: %s", $locationDescription,
						$productDistributor->getErrorMessage());
					return $returnArray;
				}
			}
			$returnArray['detailed_results'] = "Order Response: " . jsonEncode($response);
			foreach ($response as $shipmentInformation) {
				$resultSet = executeQuery("insert into order_shipments (order_id,location_id,full_name,date_shipped,remote_order_id,no_notifications,internal_use_only) values (?,?,?,current_date,?,?,?)",
					$orderId, $locationId, $shipmentInformation['ship_to'], $shipmentInformation['remote_order_id'], ($shipmentInformation['order_type'] == "dealer" && !$sendPickupTracking ? 1 : 0),
					($shipmentInformation['order_type'] == "dealer" ? 1 : 0));
				$orderShipmentId = $resultSet['insert_id'];
				$orderItemCount = 0;
				$resultSet = executeQuery("select * from remote_order_items where remote_order_id = ?", $shipmentInformation['remote_order_id']);
				while ($thisOrderItem = getNextRow($resultSet)) {
					$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
					$returnArray['detailed_results'] .= "\nRemote Order Item: " . jsonEncode($thisOrderItem);

					$productInventoryRow = getRowFromId("product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", ProductDistributor::getInventoryLocation($locationId));
					if (empty($productInventoryRow['quantity'])) {
						$productInventoryRow['quantity'] = 0;
					}
					$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], ProductDistributor::getInventoryLocation($locationId), $productInventoryRow);
					executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
						$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
					$orderItemCount++;

# add to product inventory log

					$productInventoryId = $productInventoryRow['product_inventory_id'];
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
			if ($orderItemCount == 0) {
				executeQuery("delete from remote_order_items where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
				executeQuery("delete from remote_orders where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
				executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
				$returnArray['success'] = false;
				$returnArray['result'] = "No items ordered";
			} else {
				$returnArray['success'] = true;
				$returnArray['result'] = sprintf("Shipment created for %s items from %s.", $orderItemCount, $locationDescription);
			}
		}
		return $returnArray;
	}

	public static function notifyCRM($orderId, $reason = "") {
		if (empty($reason)) {
			$zaiusApiKey = getPreference("ZAIUS_API_KEY");
			$zaiusUseUpc = getPreference("ZAIUS_USE_UPC");
			if (!empty($zaiusApiKey)) {
				$zaius = new Zaius($zaiusApiKey);
				$zaius->logOrder($orderId, $zaiusUseUpc);
			}

			$infusionSoftToken = getPreference("INFUSIONSOFT_ACCESS_TOKEN");
			if (!empty($infusionSoftToken)) {
				$infusionSoft = new InfusionSoft($infusionSoftToken);
				$infusionSoft->logOrder($orderId);
			}

			$yotpoAppKey = getPreference('YOTPO_APP_KEY');
			$yotpoSecretKey = getPreference('YOTPO_SECRET_KEY');
			if (!empty($yotpoAppKey) && !empty($yotpoSecretKey)) {
				$yotpo = new Yotpo($yotpoAppKey, $yotpoSecretKey);
				$yotpo->logOrder($orderId);
			}

			$yotpoLoyaltyApiKey = getPreference("YOTPO_LOYALTY_API_KEY");
			$yotpoLoyaltyGuid = getPreference("YOTPO_LOYALTY_GUID");
			if (!empty($yotpoLoyaltyApiKey) && !empty($yotpoLoyaltyGuid)) {
				$yotpoLoyalty = new YotpoLoyalty($yotpoLoyaltyApiKey, $yotpoLoyaltyGuid);
				$yotpoLoyalty->logOrder($orderId);
			}

			$highLevelAccessToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ACCESS_TOKEN");
			$highLevelLocationId = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_LOCATION_ID");
			if (!empty($highLevelAccessToken) && !empty($highLevelLocationId)) {
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$highLevel = new HighLevel($highLevelAccessToken, $highLevelLocationId);
				$highLevel->removeContactTag($orderRow['contact_id'], "cart_abandoned");
			}
		}

		$listrakClientId = getPreference('LISTRAK_CLIENT_ID');
		$listrakClientSecret = getPreference('LISTRAK_CLIENT_SECRET');
		if (!empty($listrakClientId)) {
			$listrak = new Listrak($listrakClientId, $listrakClientSecret);
			switch ($reason) {
				case "":
                case "update_status":
                case "mark_completed":
					$listrak->logOrder($orderId);
					break;
			}
		}

		$activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
		$activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
		if (!empty($activeCampaignApiKey)) {
			$activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
			switch ($reason) {
				case "":
					if (!$activeCampaign->logOrder($orderId)) {
						addProgramLog("Logging order in ActiveCampaign failed: " . $activeCampaign->getErrorMessage());
					}
					break;
				case "update_status":
					$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
					$newStatusCode = getFieldFromId("order_status_code", "order_status", "order_status_id",
						getFieldFromId("order_status_id", "orders", "order_id", $orderId));
					if (!$activeCampaign->logEvent($contactId, "Order status set to " . $newStatusCode, array("order_id" => $orderId))) {
						addProgramLog("Logging event in ActiveCampaign failed: " . $activeCampaign->getErrorMessage());
					}
					break;
			}
		}
	}

	public static function getPostalCodeTaxRateRow($countryId, $postalCode) {
		if (empty($postalCode)) {
			return array();
		} else {
			return getRowFromId("postal_code_tax_rates", "client_id", $GLOBALS['gClientId'],
				"country_id = ? and (postal_code = ? or postal_code = ? or postal_code = ?) and product_category_id is null and product_department_id is null",
				$countryId, $postalCode, substr($postalCode, 0, 5), substr($postalCode, 0, 3));
		}
	}

	public static function getStateTaxRateRow($countryId, $state) {
		if (empty($state)) {
			return array();
		} else {
			return getRowFromId("state_tax_rates", "client_id", $GLOBALS['gClientId'],
				"country_id = ? and state = ?  and product_category_id is null and product_department_id is null",
				$countryId, $state);
		}
	}

	private static function createRecurringPayment($orderItemRow, $orderPaymentRow, $subscriptionProductRow, $nextBillingDate, $parameters, $contactSubscriptionId = "") {
		if (!empty($subscriptionProductRow['product_id']) && !empty($subscriptionProductRow['recurring_payment_type_id']) && !empty($orderPaymentRow['account_id'])) {
			$orderRow = getRowFromId("orders", "order_id", $orderItemRow['order_id']);
			$promotionId = getFieldFromId("promotion_id", "order_promotions", "order_id", $orderRow['order_id']);
			$sourceId = getFieldFromId("source_id", "sources", "source_id", $orderRow['source_id'], "source_code not in ('CORESTORE','API')");
			$resultSet = executeQuery("insert into recurring_payments (contact_id,recurring_payment_type_id,payment_method_id,shipping_method_id,promotion_id,source_id,start_date,next_billing_date,account_id) values " .
				"(?,?,?,?,?,?,current_date,?,?)", $orderRow['contact_id'], $subscriptionProductRow['recurring_payment_type_id'], $orderPaymentRow['payment_method_id'], $orderRow['shipping_method_id'], $promotionId, $sourceId,
				$nextBillingDate, $orderPaymentRow['account_id']);
			$recurringPaymentId = $resultSet['insert_id'];
			$productCatalog = new ProductCatalog();
			$salePriceInfo = $productCatalog->getProductSalePrice($subscriptionProductRow['product_id']);
			$salePrice = $salePriceInfo['sale_price'];
			if (is_array($parameters['product_additional_charges']) && array_key_exists($orderItemRow['product_id'], $parameters['product_additional_charges'])) {
				$salePrice += $parameters['product_additional_charges'][$orderItemRow['product_id']];
			}
			if (!empty($promotionRow['discount_percent'])) {
				$percentSalePrice = round($salePrice * ((100 - $promotionRow['discount_percent']) / 100), 2);
				$salePrice = min(0, $percentSalePrice);
			}
			$productAddons = array();
			$addonSet = executeQuery("select * from order_item_addons where order_item_id = ?", $orderItemRow['order_item_id']);
			while ($addonRow = getNextRow($addonSet)) {
				$productAddons[] = $addonRow;
				$salePrice += $addonRow['sale_price'] * $addonRow['quantity'];
			}
			$insertSet = executeQuery("insert into recurring_payment_order_items (recurring_payment_id,product_id,quantity,sale_price) values (?,?,1,?)",
				$recurringPaymentId, $subscriptionProductRow['product_id'], $salePrice);
			$recurringPaymentOrderItemId = $insertSet['insert_id'];
			foreach ($productAddons as $thisAddon) {
				executeQuery("insert into recurring_payment_order_item_addons (recurring_payment_order_item_id,product_addon_id,quantity,sale_price) values (?,?,?,?)", $recurringPaymentOrderItemId, $thisAddon['product_addon_id'], $thisAddon['quantity'], $thisAddon['sale_price']);
			}
			if (!empty($recurringPaymentId) && !empty($contactSubscriptionId)) {
				executeQuery("update recurring_payments set contact_subscription_id = ? where contact_subscription_id is null and recurring_payment_id = ?", $contactSubscriptionId, $recurringPaymentId);
			}
		}
	}

	function setOrderField($fieldName, $fieldValue) {
		if ($fieldName == "order_id") {
			return false;
		}
		$this->iOrderRow[$fieldName] = $fieldValue;
		return true;
	}

	function addOrderItem($orderItem) {
		if (!is_array($orderItem) || empty($orderItem['product_id']) || empty($orderItem['quantity'])) {
			return false;
		}
		$orderItem['list_price'] = getFieldFromId("list_price", "products", "product_id", $orderItem['product_id']);
		$orderItem['base_cost'] = getFieldFromId("base_cost", "products", "product_id", $orderItem['product_id']);
		$this->iOrderItems[] = $orderItem;
		return true;
	}

	function generateOrder($presetOrderNumber = "") {
		if (empty($this->iOrderRow) || empty($this->iOrderItems)) {
			$this->iErrorMessage = "No order to create";
			return false;
		}
		if (empty($this->iOrderRow['contact_id'])) {
			$this->iErrorMessage = "Contact not set";
			return false;
		}
		if (empty($this->iOrderRow['full_name'])) {
			$this->iOrderRow['full_name'] = getDisplayName($this->iOrderRow['contact_id']);
		}
		$orderDataSource = new DataSource("orders");
		$orderDataSource->disableTransactions();
		$this->iOrderRow['order_number'] = $presetOrderNumber;
		if (empty($this->iOrderRow['order_number'])) {
			$this->iOrderRow['order_number'] = -1;
		}
		if (empty($this->iOrderRow['ip_address'])) {
			$this->iOrderRow['ip_address'] = $_SERVER['REMOTE_ADDR'];
		}
		if (empty($this->iOrderRow['user_id'])) {
			$this->iOrderRow['user_id'] = $GLOBALS['gUserId'];
		}
		$chargeFields = array("tax_charge", "shipping_charge", "handling_charge");
		foreach ($chargeFields as $thisField) {
			if (empty($this->iOrderRow[$thisField])) {
				$this->iOrderRow[$thisField] = 0;
			}
		}
		if (!$orderDataSource->saveRecord(array("name_values" => $this->iOrderRow, "primary_id" => $this->iOrderId))) {
			$this->iErrorMessage = $orderDataSource->getErrorMessage();
			return false;
		}
		$orderId = $orderDataSource->getPrimaryId();
		$this->iOrderId = $orderId;
		$this->iOrderRow['order_id'] = $orderId;
		if (empty($presetOrderNumber)) {
			$this->iOrderRow['order_number'] = $orderId;
			$nameValues = array("order_number" => $orderId);
			if (!$orderDataSource->saveRecord(array("name_values" => $nameValues, "primary_id" => $this->iOrderId))) {
				$this->iErrorMessage = $orderDataSource->getErrorMessage();
				return false;
			}
		}
        if ($this->iOrderRow['tax_charge'] > 0) {
            $cartTotal = 0;
            $itemTaxTotal = 0;
	        foreach ($this->iOrderItems as $orderItemRow) {
		        $cartTotal += $orderItemRow['quantity'] * $orderItemRow['sale_price'];
                $itemTaxTotal += $orderItemRow['tax_charge'];
	        }
            if ($itemTaxTotal == 0) {
                $totalTax = $this->iOrderRow['tax_charge'];
	            foreach ($this->iOrderItems as $orderIndex => $orderItemRow) {
		            $itemTotal = $orderItemRow['quantity'] * $orderItemRow['sale_price'];
                    $taxCharge = round($this->iOrderRow['tax_charge'] * $itemTotal / $cartTotal,2);
                    if (($totalTax - $taxCharge) < .04) {
	                    $taxCharge = $totalTax;
                    }
                    $totalTax -= $taxCharge;
		            $this->iOrderItems[$orderIndex]['tax_charge'] = $taxCharge;
                    if ($totalTax <= 0) {
	                    break;
                    }
	            }
            }
        }
		$orderItemDataSource = new DataSource("order_items");
		$orderItemDataSource->disableTransactions();
		foreach ($this->iOrderItems as $orderIndex => $orderItemRow) {
			$orderItemRow['order_id'] = $orderId;
			if (!array_key_exists("sale_price", $orderItemRow) || strlen($orderItemRow['sale_price']) == 0) {
				$orderItemRow['sale_price'] = $orderItemRow['list_price'];
			}
			$productSet = executeQuery("select * from products left outer join product_data using (product_id) where products.product_id = ?", $orderItemRow['product_id']);
			$productRow = getNextRow($productSet);
			if (empty($orderItemRow['description'])) {
				$orderItemRow['description'] = $productRow['description'];
			}
			if (!empty($orderItemRow['order_item_id'])) {
				$emailAddresses = array();
				$resultSet = executeQuery("select * from product_sale_notifications where product_id = ?", $orderItemRow['product_id']);
				$productCatalog = new ProductCatalog();
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['maximum_quantity'])) {
						$inventoryCounts = $productCatalog->getInventoryCounts(true, $row['product_id']);
						$inStockQuantity = (!array_key_exists($row['product_id'], $inventoryCounts) ? 0 : $inventoryCounts[$row['product_id']]);
						if ($inStockQuantity > $row['maximum_quantity']) {
							continue;
						}
					}
					$emailAddresses[] = $row['email_address'];
				}
				if (!empty($emailAddresses)) {
					$body = "<p>An order was placed for the following product:</p><p>Product ID: " . $productRow['product_id'] . "<br>Description: " . $productRow['description'] . "<br>UPC: " . $productRow['upc_code'] . "</p>";
					sendEmail(array("subject" => "Product Ordered", "body" => $body, "email_addresses" => $emailAddresses));
				}
			}
			if (!empty($productRow['virtual_product']) && !empty($productRow['file_id'])) {
				$downloadDays = getPreference("DOWNLOAD_PRODUCT_DAYS");
				$productDownloadDays = CustomField::getCustomFieldData($productRow['product_id'], "DOWNLOAD_PRODUCT_DAYS", "PRODUCTS");
				if (!empty($productDownloadDays)) {
					$downloadDays = $productDownloadDays;
				}
				if (!empty($downloadDays) && $downloadDays > 0) {
					$downloadDate = date("m/d/Y", strtotime("+" . $downloadDays . " days"));
					$orderItemRow['download_date'] = $downloadDate;
				}
			}
			$chargeFields = array("tax_charge");
			foreach ($chargeFields as $thisField) {
				if (empty($orderItemRow[$thisField])) {
					$orderItemRow[$thisField] = 0;
				}
			}
			if (!$orderItemId = $orderItemDataSource->saveRecord(array("name_values" => $orderItemRow, "primary_id" => $orderItemRow['order_item_id']))) {
				$this->iErrorMessage = $orderItemDataSource->getErrorMessage();
				return false;
			}
			$orderItemRow['order_item_id'] = $orderItemId;
			$this->iOrderItems[$orderIndex] = $orderItemRow;
			if (is_array($orderItemRow['product_addons'])) {
				foreach ($orderItemRow['product_addons'] as $thisAddon) {
					if (!empty($thisAddon['content']) && is_string($thisAddon['content'])) {
						$contentArray = json_decode($thisAddon['content'], true);
						if (!empty($contentArray['shopping_cart_item_id'])) {
							unset($contentArray['shopping_cart_item_id']);
							$contentArray['order_item_id'] = $orderItemId;
							$thisAddon['content'] = jsonEncode($contentArray);
						}
					}
					executeQuery("insert into order_item_addons (order_item_id,product_addon_id,quantity,sale_price,content) values (?,?,?,?,?)", $orderItemId, $thisAddon['product_addon_id'], $thisAddon['quantity'], $thisAddon['sale_price'], $thisAddon['content']);
				}
			}

			if (!empty($this->iCustomFields)) {
				$customFields = CustomField::getCustomFields("order_items");
				foreach ($customFields as $thisCustomField) {
					$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "custom_field_id", $thisCustomField['custom_field_id'],
						"product_id = ?", $orderItemRow['product_id']);
					if (empty($productCustomFieldId)) {
						continue;
					}
					$columnName = "custom_field_" . $thisCustomField['custom_field_id'] . "_item_" . $orderItemRow['shopping_cart_item_id'];
					$customField = CustomField::getCustomField($thisCustomField['custom_field_id'], $columnName);
					if (!$customField->saveData(array_merge(array("primary_id" => $orderItemId), $this->iCustomFields))) {
						$this->iErrorMessage = $customField->getErrorMessage();
						return false;
					}
					$contactCustomFieldCode = getFieldFromId("control_value", "custom_field_controls", "custom_field_id", $thisCustomField['custom_field_id'], "control_name = 'contact_custom_field_code'");
					if (!empty($contactCustomFieldCode)) {
						$customFieldValue = CustomField::getCustomFieldData($orderItemId, $thisCustomField['custom_field_code'], "order_items");
						CustomField::setCustomFieldData($this->iOrderRow['contact_id'], $contactCustomFieldCode, $customFieldValue);
					}
				}
			}
		}
		if (!empty($this->iPromotionId)) {
			$this->iPromotionId = getReadFieldFromId("promotion_id", "promotions", "promotion_id", $this->iPromotionId);
		}
		if (!empty($this->iPromotionId)) {
			executeQuery("delete from order_promotions where order_id = ? and promotion_id <> ?", $orderId, $this->iPromotionId);
			$orderPromotionId = getFieldFromId("order_promotion_id", "order_promotions", "order_id", $orderId, "promotion_id = ?", $this->iPromotionId);
			if (empty($orderPromotionId)) {
				executeQuery("insert ignore into order_promotions (order_id,promotion_id) values (?,?)", $orderId, $this->iPromotionId);
			}
		} else {
			executeQuery("delete from order_promotions where order_id = ?", $orderId);
		}
		return true;
	}

	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	function createOrderPayment($amount = -1, $parameters = array()) {
		$orderId = $this->iOrderId;
		if (empty($orderId)) {
			$this->iErrorMessage = "No order for payment";
			return false;
		}
		if ($amount < 0) {
			$this->iErrorMessage = "Negative payment amount not allowed";
			return false;
		}
		$orderPaymentDataSource = new DataSource("order_payments");
		$orderPaymentDataSource->disableTransactions();
		if (is_array($parameters)) {
			$orderPaymentRow = $parameters;
			$orderPaymentRow['order_id'] = $orderId;
		} else {
			$orderPaymentRow = array("order_id" => $orderId);
		}
		if (empty($orderPaymentRow['payment_time'])) {
			$orderPaymentRow['payment_time'] = date("Y-m-d H:i:s");
		}
		if (empty($orderPaymentRow['payment_date'])) {
			$orderPaymentRow['payment_date'] = date("Y-m-d");
		}
		$orderPaymentRow['amount'] = $amount;
		if (!$orderPaymentId = $orderPaymentDataSource->saveRecord(array("name_values" => $orderPaymentRow, "primary_id" => ""))) {
			$this->iErrorMessage = $orderPaymentDataSource->getErrorMessage() . ":" . $orderId . ":" . jsonEncode($orderPaymentRow) . ":" . jsonEncode(getRowFromId("orders", "order_id", $orderId));
			return false;
		}
		if (!empty($parameters['account_id'])) {
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id",
				getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $parameters['payment_method_id']));
			if ($paymentMethodTypeCode == "CREDIT_ACCOUNT") {
				$totalPayment = -1 * ($orderPaymentRow['amount'] + (empty($orderPaymentRow['shipping_charge']) ? 0 : $orderPaymentRow['shipping_charge']) + (empty($orderPaymentRow['tax_charge']) ? 0 : $orderPaymentRow['tax_charge']) + (empty($orderPaymentRow['handling_charge']) ? 0 : $orderPaymentRow['handling_charge']));
				executeQuery("insert into credit_account_log (account_id,description,user_id,amount) values (?,?,?,?)", $parameters['account_id'],
					"Payment for order ID " . $orderId, $GLOBALS['gUserId'], $totalPayment);
				executeQuery("update accounts set credit_limit = credit_limit + ? where account_id = ?", $totalPayment, $parameters['account_id']);
			}
		}
		return $orderPaymentId;
	}

	function getOrderId() {
		return $this->iOrderId;
	}

	function getPackNotes() {
		return $this->iPackNotes;
	}

	function populateFromShoppingCart($shoppingCart) {
		if (!is_a($shoppingCart, "ShoppingCart")) {
			return false;
		}
		$orderItems = $shoppingCart->getShoppingCartItems();
		$productCatalog = new ProductCatalog();
		foreach ($orderItems as $orderItem) {
			if (!empty($orderItem['additional_charges'])) {
				$orderItem['sale_price'] += $orderItem['additional_charges'];
			}
			if (is_array($orderItem['product_addons'])) {
				foreach ($orderItem['product_addons'] as $thisAddon) {
					$orderItem['sale_price'] += $thisAddon['sale_price'] * $thisAddon['quantity'];
				}
			}
			$productId = getFieldFromId("product_id", "products", "product_id", $orderItem['product_id']);
			if (empty($productId)) {
				continue;
			}
			$cartProductRow = ProductCatalog::getCachedProductRow($productId);
			$resultSet = executeQuery("select * from product_pack_contents where product_id = ?", $productId);
			if ($resultSet['row_count'] > 0) {
				$this->iPackNotes .= (empty($this->iPackNotes) ? "" : "\n") . "<p>Pack purchase: <a target='_blank' href='/productmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $productId . "'>" .
					htmlText(getFieldFromId("description", "products", "product_id", $productId)) . "</a></p>";

				$customFields = CustomField::getCustomFields("order_items");
				foreach ($customFields as $thisCustomField) {
					$columnName = "custom_field_" . $thisCustomField['custom_field_id'] . "_item_" . $orderItem['shopping_cart_item_id'];
					if (empty($_POST[$columnName])) {
						continue;
					}
					$this->iPackNotes .= "<p>" . $thisCustomField['form_label'] . ": " . $_POST[$columnName] . "</p>";
				}

				$originalSalePrice = $orderItem['sale_price'];
				$packContents = array();
				$totalSalePrices = 0;
				$giftCardTotals = 0;
				while ($row = getNextRow($resultSet)) {
					$productRow = ProductCatalog::getCachedProductRow($row['contains_product_id']);
					$productDataRow = getRowFromId("product_data", "product_id", $row['contains_product_id']);
					$productRow['product_type_code'] = getFieldFromId("product_type_code", "product_types", "product_type_id", $productRow['product_type_id']);
					$salePriceInfo = $productCatalog->getProductSalePrice($row['contains_product_id'], array("product_information" => array_merge($productRow, $productDataRow)));
					$productRow['sale_price'] = $thisSalePrice = $salePriceInfo['sale_price'];
					if ($productRow['product_type_code'] != "GIFT_CARD") {
						$totalSalePrices += $row['quantity'] * $thisSalePrice;
					} else {
						$giftCardTotals += $thisSalePrice * $row['quantity'];
					}
					$packContents[] = array_merge($row, $productRow);
				}
				$skipGiftCards = true;
				if ($totalSalePrices > 0) {
					$originalSalePrice -= $giftCardTotals;
					$percentDiscount = $originalSalePrice / $totalSalePrices;
				} else {
					if ($giftCardTotals > 0) {
						$percentDiscount = $originalSalePrice / $giftCardTotals;
						$skipGiftCards = false;
					} else {
						$percentDiscount = 0;
					}
				}
				$finalSaleTotal = 0;
				$addItems = array();
				foreach ($packContents as $index => $thisContent) {
					if ($skipGiftCards && $thisContent['product_type_code'] == "GIFT_CARD") {
						$salePrice = $thisContent['sale_price'];
					} else {
						$salePrice = round($thisContent['sale_price'] * $percentDiscount, 2);
						$finalSaleTotal += ($salePrice * $thisContent['quantity']);
					}
					$addItems[] = array("product_id" => $thisContent['product_id'], "sale_price" => $salePrice, "quantity" => ($thisContent['quantity'] * $orderItem['quantity']), "product_type_code" => $thisContent['product_type_code'], "pack_product_id" => $productId, "pack_description" => $cartProductRow['description'], "pack_quantity" => $orderItem['quantity']);
				}
				if ($finalSaleTotal != $originalSalePrice) {
					$remaining = $originalSalePrice - $finalSaleTotal;
					foreach ($addItems as $index => $thisAddItem) {
						if ($skipGiftCards && $thisAddItem['product_type_code'] == "GIFT_CARD") {
							continue;
						}
						if ($thisAddItem['quantity'] == $orderItem['quantity']) {
							$addItems[$index]['sale_price'] += $remaining;
							break;
						}
					}
				}
				foreach ($addItems as $thisAddItem) {
					$this->addOrderItem($thisAddItem);
				}

				$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $productId, "quantity > 0");
				if (!empty($productInventoryId)) {
					executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity) values " .
						"(?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $GLOBALS['gUserId'], $orderItem['quantity']);
					executeQuery("update product_inventories set quantity = quantity - " . $orderItem['quantity'] . " where product_inventory_id = ?", $productInventoryId);
				}

			} else {
				$this->addOrderItem($orderItem);
			}
		}
		if (empty($this->iOrderRow['contact_id'])) {
			$this->iOrderRow['contact_id'] = $shoppingCart->getContact();
		}
		if (empty($this->iOrderRow['contact_id'])) {
			$this->iOrderRow['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
		}
		if (empty($this->iOrderRow['user_id'])) {
			$this->iOrderRow['user_id'] = $shoppingCart->getUser();
		}
		if (empty($this->iOrderRow['user_id'])) {
			$this->iOrderRow['user_id'] = $GLOBALS['gUserId'];
		}
		$this->iPromotionId = $shoppingCart->getPromotionId();
		return true;
	}

	function checkOutOfStock() {
		$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
		$outOfStock = false;
		if (!$neverOutOfStock) {
			$productCatalog = new ProductCatalog();
			$productIds = array();
			foreach ($this->iOrderItems as $thisItem) {
				$productIds[] = $thisItem['product_id'];
			}
			$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
			foreach ($this->iOrderItems as $thisItem) {
				$nonInventoryItem = getFieldFromId("non_inventory_item", "products", "product_id", $thisItem['product_id']);
				if (!empty($nonInventoryItem)) {
					continue;
				}
				$inStockQuantity = (!array_key_exists($thisItem['product_id'], $inventoryCounts) ? 0 : $inventoryCounts[$thisItem['product_id']]);
				if ($thisItem['quantity'] > $inStockQuantity) {
					$outOfStock = ($outOfStock === false ? "" : $outOfStock . ", ") . "'" . getFieldFromId("description", "products", "product_id",
							$thisItem['product_id']) . "' has " . ($inStockQuantity == 0 ? "none" : "only " . $inStockQuantity . " in stock");
				}
			}
		}
		return $outOfStock;
	}

	function setCustomFields($customFields) {
		$this->iCustomFields = $customFields;
	}

	function getOrderField($fieldName) {
		return $this->iOrderRow[$fieldName];
	}

	function getOrderRow() {
		return $this->iOrderRow;
	}

	function setCustomerContact($contactId) {
		$this->iOrderRow['contact_id'] = $contactId;
	}

	function getTax($locationContactId = "", $programLogId = "") {
		$useContactId = empty($locationContactId) ? $this->iOrderRow['contact_id'] : $locationContactId;
		$logAllTaxCalculations = getPreference("LOG_TAX_CALCULATION");
		$allowZipPlus4 = !empty(getPreference("ALLOW_ZIP_PLUS_4_FOR_TAX_CALCULATIONS"));
		$taxExemptId = CustomField::getCustomFieldData($useContactId, "TAX_EXEMPT_ID");
		if (!empty($taxExemptId)) {
			if ($logAllTaxCalculations) {
				addProgramLog("Tax calculation: Contact ID " . $useContactId . " has tax exempt ID " . $taxExemptId, $programLogId);
			}
			return 0;
		}
		$taxExempt = getFieldFromId("tax_exempt", "sources", "source_id", $this->iOrderRow['source_id']);
		if (!empty($taxExempt)) {
			if ($logAllTaxCalculations) {
				addProgramLog("Tax calculation: Source ID " . $this->iOrderRow['source_id'] . " is tax exempt.", $programLogId);
			}
			return 0;
		}

		$logEntry = "";
		$taxRate = CustomField::getCustomFieldData($useContactId, "TAX_RATE");
		$flatTaxAmount = 0;
		$gotTaxRate = (strlen($taxRate) > 0);
		if ($gotTaxRate) {
			$logEntry = "Custom tax rate for contact";
		}

		$address1 = $postalCode = $city = $state = $countryId = "";
		$fromAddressInfo = $GLOBALS['gClientRow'];
		if (!empty($useContactId)) {
			$contactRow = Contact::getContact($useContactId);
			if (empty($contactRow)) { // if using client contact row, it will be in the management client
				$contactResult = executeQuery("select * from contacts where contact_id = ? and client_id = ?", $useContactId, $GLOBALS['gDefaultClientId']);
				$contactRow = getNextRow($contactResult);
			}
			if (strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
				$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
			}
			$address1 = $contactRow['address_1'];
			$city = $contactRow['city'];
			$state = $contactRow['state'];
			$postalCode = $contactRow['postal_code'];
			$countryId = $contactRow['country_id'];
		}

		if (!$gotTaxRate) {
			if (!empty($this->iOrderRow['shipping_method_id'])) {
				$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $this->iOrderRow['shipping_method_id']);
				if ($shippingMethodRow['pickup'] && !empty($shippingMethodRow['location_id'])) {
					$contactId = getFieldFromId("contact_id", "locations", "location_id", $shippingMethodRow['location_id']);
					$contactRow = Contact::getContact($contactId);
					if (empty($contactRow['state']) && empty($contactRow['postal_code'])) {
						$contactRow = $GLOBALS['gClientRow'];
					}
					if (!$allowZipPlus4 && strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
						$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
					}
					$address1 = $contactRow['address_1'];
					$city = $contactRow['city'];
					$state = $contactRow['state'];
					$postalCode = $contactRow['postal_code'];
					$countryId = $contactRow['country_id'];
					$fromAddressInfo['address_1'] = $address1;
					$fromAddressInfo['city'] = $city;
					$fromAddressInfo['state'] = $state;
					$fromAddressInfo['postal_code'] = $postalCode;
					$fromAddressInfo['country_id'] = $countryId;
					$gotTaxRate = true;
					$logEntry = "Tax rate based on shipping method";
					$taxRateRow = self::getPostalCodeTaxRateRow($contactRow['country_id'], $contactRow['postal_code']);
					if (empty($taxRateRow)) {
						$taxRateRow = self::getStateTaxRateRow($contactRow['country_id'], $contactRow['state']);
					}
					if (!empty($taxRateRow)) {
						$taxRate = $taxRateRow['tax_rate'];
						$flatTaxAmount = $taxRateRow['flat_rate'];
					}
				}
			}
		}

		// Not using FFL to calculate tax; sales tax should be based on buyer's address, not transfer location
		if (!$gotTaxRate && !empty(getPreference("USE_FFL_ADDRESS_FOR_TAX_CALCULATION"))) {
			if (!empty($this->iOrderRow['federal_firearms_licensee_id'])) {
				$contactId = (new FFL($this->iOrderRow['federal_firearms_licensee_id']))->getFieldData("contact_id");
				if (!empty($contactId)) {
					$contactRow = Contact::getContact($contactId);
					if (!$allowZipPlus4 && strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
						$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
					}
					$address1 = $contactRow['address_1'];
					$city = $contactRow['city'];
					$state = $contactRow['state'];
					$postalCode = $contactRow['postal_code'];
					$countryId = $contactRow['country_id'];
					$gotTaxRate = true;
					$logEntry = "Tax rate from FFL dealer";
					$taxRateRow = self::getPostalCodeTaxRateRow($contactRow['country_id'], $contactRow['postal_code']);
					if (empty($taxRateRow)) {
						$taxRateRow = self::getStateTaxRateRow($contactRow['country_id'], $contactRow['state']);
					}
					if (!empty($taxRateRow)) {
						$taxRate = $taxRateRow['tax_rate'];
						$flatTaxAmount = $taxRateRow['flat_rate'];
					}
				}
			}
		}

		if (!$gotTaxRate) {
			if (!empty($this->iOrderRow['address_id'])) {
				$contactRow = getRowFromId("addresses", "address_id", $this->iOrderRow['address_id']);
				if (!$allowZipPlus4 && strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
				$taxRateRow = self::getPostalCodeTaxRateRow($contactRow['country_id'], $contactRow['postal_code']);
				if (empty($taxRateRow)) {
					$taxRateRow = self::getStateTaxRateRow($contactRow['country_id'], $contactRow['state']);
				}
				if (!empty($taxRateRow)) {
					$taxRate = $taxRateRow['tax_rate'];
					$flatTaxAmount = $taxRateRow['flat_rate'];
					$gotTaxRate = true;
					$logEntry = "Tax rate from order address";
				}
			} elseif (!empty($useContactId)) {
				$contactRow = Contact::getContact($useContactId);
				if (empty($contactRow)) { // if using client contact row, it will be in the management client
					$contactResult = executeQuery("select * from contacts where contact_id = ? and client_id = ?", $useContactId, $GLOBALS['gDefaultClientId']);
					$contactRow = getNextRow($contactResult);
				}
				if (!$allowZipPlus4 && strlen($contactRow['postal_code']) > 5 && $contactRow['country_id'] == 1000) {
					$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
				}
				$address1 = $contactRow['address_1'];
				$city = $contactRow['city'];
				$state = $contactRow['state'];
				$postalCode = $contactRow['postal_code'];
				$countryId = $contactRow['country_id'];
				$taxRateRow = self::getPostalCodeTaxRateRow($contactRow['country_id'], $contactRow['postal_code']);
				if (empty($taxRateRow)) {
					$taxRateRow = self::getStateTaxRateRow($contactRow['country_id'], $contactRow['state']);
				}
				if (!empty($taxRateRow)) {
					$taxRate = $taxRateRow['tax_rate'];
					$flatTaxAmount = $taxRateRow['flat_rate'];
					$gotTaxRate = true;
					$logEntry = "Tax rate from contact address";
				}
			}
		}

		if (empty($taxRate) || !$gotTaxRate) {
			$taxRate = 0;
		}

		$totalTax = 0;
		$cartTotal = 0;
		foreach ($this->iOrderItems as $orderItemRow) {
			$cartTotal += $orderItemRow['quantity'] * $orderItemRow['sale_price'];
		}
		$discountAmount = 0;
		if (!empty($this->iPromotionId)) {
			$promotionRow = ShoppingCart::getCachedPromotionRow($this->iPromotionId);
			if ($promotionRow['discount_percent'] > 0) {
				$discountAmount = round($cartTotal * ($promotionRow['discount_percent'] / 100), 2);
			}
			if ($promotionRow['discount_amount'] > 0) {
				$discountAmount += $promotionRow['discount_amount'];
			}
		}
		$discountPercent = ($cartTotal <= 0 ? 0 : $discountAmount / $cartTotal);
		if (function_exists("_localGetTax")) {
			$toAddressInfo = array(
				"country_id" => $countryId,
				'postal_code' => $postalCode,
				'state' => $state,
				'city' => $city,
				'address_1' => $address1
			);
			$result = _localGetTax($this->iOrderItems, $fromAddressInfo, $toAddressInfo, $this->iOrderRow['shipping_charge'], $discountPercent, $programLogId);
			if ($result !== false) {
				foreach ($result['order_items'] as $thisId => $thisOrderItem) {
					if (array_key_exists($thisId, $this->iOrderItems)) {
						$this->iOrderItems[$thisId]['tax_charge'] = $thisOrderItem['tax_charge'];
					}
				}
				if (!empty($result['invoice_number']) && empty($this->iOrderRow['purchase_order_number'])) {
					$this->iOrderRow['purchase_order_number'] = $result['invoice_number'];
				}
				return $result['tax_charge'];
			}
		}
		$taxjarApiToken = getPreference("taxjar_api_token");
		$orderData = array();
		if (!empty($taxjarApiToken)) {
			require_once __DIR__ . '/../taxjar/vendor/autoload.php';
			try {
				$client = TaxJar\Client::withApiKey($taxjarApiToken);
				$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
				$stateList = getCachedData("taxjar", "nexus_list");
				if (empty($stateList)) {
					$nexusList = $client->nexusRegions();
					$stateList = array();
					foreach ((array)$nexusList as $thisNexus) {
						$stateList[] = $thisNexus->region_code;
					}
					setCachedData("taxjar", "nexus_list", $stateList, 1);
				}
				if (!in_array($state, $stateList)) {
					foreach ($this->iOrderItems as $index => $thisOrderItem) {
						$this->iOrderItems[$index]['tax_charge'] = 0;
					}
					if ($logAllTaxCalculations) {
						addProgramLog("Tax calculation: Client does not have TaxJar nexus in state " . $state, $programLogId);
					}
					return 0;
				}
				$orderData = [
					'from_country' => getFieldFromId("country_code", "countries", "country_id", $fromAddressInfo['country_id']),
					'from_zip' => $fromAddressInfo['postal_code'],
					'from_state' => $fromAddressInfo['state'],
					'from_city' => $fromAddressInfo['city'],
					'from_street' => $fromAddressInfo['address_1'],
					'to_country' => getFieldFromId("country_code", "countries", "country_id", $countryId),
					'to_zip' => $postalCode,
					'to_state' => $state,
					'to_city' => $city,
					'to_street' => $address1,
					'shipping' => $this->iOrderRow['shipping_charge'],
					'plugin' => "coreware"
				];
				$lineItems = array();
				$amount = 0;
				foreach ($this->iOrderItems as $orderIndex => $orderItemRow) {
					$taxableAmount = $orderItemRow['quantity'] * $orderItemRow['sale_price'] * (1 - $discountPercent);
					$unitPrice = round($taxableAmount / $orderItemRow['quantity'], 2);
					$thisLineItem = array("id" => $orderIndex, "quantity" => $orderItemRow['quantity'], "unit_price" => $unitPrice);
					$amount += ($orderItemRow['quantity'] * $unitPrice);
					$taxjarProductCategoryCode = CustomField::getCustomFieldData($orderItemRow['product_id'], "TAXJAR_PRODUCT_CATEGORY_CODE", "PRODUCTS");
					if (!empty($taxjarProductCategoryCode)) {
						$thisLineItem['product_tax_code'] = $taxjarProductCategoryCode;
					} else {
						$taxSet = executeQuery("select product_tax_code from product_categories join product_category_links using (product_category_id) where product_id = ? and product_tax_code is not null order by sequence_number", $orderItemRow['product_id']);
						if ($taxRow = getNextRow($taxSet)) {
							$thisLineItem['product_tax_code'] = $taxRow['product_tax_code'];
						}
					}
					$lineItems[] = $thisLineItem;
				}
				$orderData['amount'] = $amount;
				$orderData['line_items'] = $lineItems;
				$tax = $client->taxForOrder($orderData);
				addProgramLog("Taxjar Tax calculation:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nTax: " . jsonEncode($tax), $programLogId);
				$totalTax = round($tax->amount_to_collect, 2);
                if($GLOBALS['gPrimaryDatabase']->tableExists("order_item_tax_details")) {
                    $invoiceNumber = "TaxJar" . str_pad(getSequenceNumber("TAXJAR_CALCULATION_NUMBER"),10,"0",STR_PAD_LEFT);
                    $_SESSION['tax_calculation_invoice_number'] = $invoiceNumber;
                    $taxDetailsTable = new DataTable("order_item_tax_details");
                    $jurisdictions = $tax->jurisdictions;
                    $zoneLevels = [ "city"=>"city_tax_rate","county"=>"county_tax_rate","special_district"=>"special_tax_rate","state"=>"state_sales_tax_rate"];
                    foreach ((array)$tax->breakdown->line_items as $thisLineItem) {
                        if (array_key_exists($thisLineItem->id, $this->iOrderItems)) {
                            $this->iOrderItems[$thisLineItem->id]['tax_charge'] = $thisLineItem->tax_collectable;
                            foreach($zoneLevels as $thisZoneLevel=>$thisTaxRateField) {
                                $nameValues = ["invoice_number" => $invoiceNumber,
                                    "part_number" => getFieldFromId("product_code", "products", "product_id", $this->iOrderItems[$thisLineItem->id]['product_id']),
                                    "tax_rate" => $thisLineItem->$thisTaxRateField,
                                    "authority_name" => $jurisdictions->$thisZoneLevel,
                                    "authority_type" => $thisTaxRateField,
                                    "effective_zone_level" => $thisZoneLevel,
                                    "taxable_state" => $jurisdictions->state,
                                    "taxable_county" => $jurisdictions->county,
                                    "taxable_city" => $jurisdictions->city
                                ];
                                $taxDetailsTable->saveRecord(["name_values"=>$nameValues]);
                            }
                        }
                    }
                } else {
                    foreach ((array)$tax->breakdown->line_items as $thisLineItem) {
                        if (array_key_exists($thisLineItem->id, $this->iOrderItems)) {
                            $this->iOrderItems[$thisLineItem->id]['tax_charge'] = $thisLineItem->tax_collectable;
                        }
                    }
                }
				return $totalTax;
			} catch (Exception $e) {
				addProgramLog("Taxjar Tax calculation Error:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nError: " . $e->getMessage(), $programLogId);
				if(startsWith($e->getMessage(),"403")) {
					sendCredentialsError(["integration_name"=>"TaxJar","error_message"=>$e->getMessage()]);
				} else {
                    $GLOBALS['gPrimaryDatabase']->logError("Taxjar Tax calculation Error:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nError: " . $e->getMessage());
                }
			}
		}
		// check order_items for specific tax rates
		$taxRateRows = array();
		if (!empty($postalCode)) {
			$resultSet = executeQuery("select * from postal_code_tax_rates where client_id = ? and country_id = ? and (postal_code = ? or postal_code = ?) and (product_category_id is not null or product_department_id is not null)",
				$GLOBALS['gClientId'], $countryId, $postalCode, substr($postalCode, 0, 3));
			while ($row = getNextRow($resultSet)) {
				$taxRateRows[] = $row;
			}
		}
		if (!empty($state)) {
			$resultSet = executeQuery("select * from state_tax_rates where client_id = ? and country_id = ? and state = ? and (product_category_id is not null or product_department_id is not null)",
				$GLOBALS['gClientId'], $countryId, $state);
			while ($row = getNextRow($resultSet)) {
				$taxRateRows[] = $row;
			}
		}
		foreach ($this->iOrderItems as $orderIndex => $orderItemRow) {
			$notTaxable = getFieldFromId("not_taxable", "products", "product_id", $orderItemRow['product_id']);
			if ($notTaxable) {
				$this->iOrderItems[$orderIndex]['tax_charge'] = 0;
				continue;
			}
			$taxableAmount = $orderItemRow['quantity'] * $orderItemRow['sale_price'] * (1 - $discountPercent);
			$thisTaxRate = false;
			$thisFlatTaxAmount = 0;
			$taxRateId = getFieldFromId("tax_rate_id", "products", "product_id", $orderItemRow['product_id']);
			if (!empty($taxRateId)) {
				$thisTaxRate = getFieldFromId("tax_rate", "tax_rates", "tax_rate_id", $taxRateId);
				$thisFlatTaxAmount = getFieldFromId("flat_rate", "tax_rates", "tax_rate_id", $taxRateId);
			} else {
				foreach ($taxRateRows as $row) {
					if (!empty($row['product_category_id'])) {
						if (ProductCatalog::productIsInCategory($orderItemRow['product_id'], $row['product_category_id'])) {
							$thisFlatTaxAmount = $row['flat_rate'];
							$thisTaxRate = $row['tax_rate'];
							break;
						}
					}
					if (!empty($row['product_department_id'])) {
						if (ProductCatalog::productIsInDepartment($orderItemRow['product_id'], $row['product_department_id'])) {
							$thisFlatTaxAmount = $row['flat_rate'];
							$thisTaxRate = $row['tax_rate'];
							break;
						}
					}
				}
			}
			if ($thisTaxRate === false) {
				$thisTaxRate = $taxRate;
				$thisFlatTaxAmount = $flatTaxAmount;
			}
			$thisTaxCharge = $thisFlatTaxAmount + round($taxableAmount * $thisTaxRate / 100, 2);
			$totalTax += $thisTaxCharge;
			$this->iOrderItems[$orderIndex]['tax_charge'] = $thisTaxCharge;
			if ($thisTaxCharge > 0 || $logAllTaxCalculations) {
				$logEntry .= "\n" . $thisTaxCharge . " tax for product ID " . $orderItemRow['product_id'] . ", Tax rate: " . $thisTaxRate . ($thisFlatTaxAmount > 0 ? " + flat tax: " . $thisFlatTaxAmount : "");
			}
		}
		$taxShipping = getPreference("TAX_SHIPPING");
		if ($taxShipping) {
			$thisTaxCharge = round($this->iOrderRow['shipping_charge'] * $taxRate / 100, 2);
			$logEntry .= "\n" . $thisTaxCharge . " tax for shipping";
			$totalTax += $thisTaxCharge;
		}
		if ($totalTax > 0 || $logAllTaxCalculations) {
			$logEntry .= "\n" . "Contact ID " . $useContactId;
			addProgramLog($logEntry, $programLogId);
		}
		return $totalTax;
	}

	function setOrderItemTaxes($totalTax) {
		if ($totalTax <= 0) {
			return;
		}
		$normalTax = $this->getTax();
		if ($normalTax == $totalTax) {
			return;
		}
		$cartTotal = 0;
		foreach ($this->iOrderItems as $orderItemRow) {
			$cartTotal += $orderItemRow['quantity'] * $orderItemRow['sale_price'];
		}
		if ($normalTax <= 0 && $cartTotal <= 0) {
			return;
		}
		foreach ($this->iOrderItems as $orderIndex => $orderItemRow) {
			if ($normalTax <= 0) {
				$thisPrice = ($orderItemRow['quantity'] * $orderItemRow['sale_price']);
				$thisTax = round($totalTax * $thisPrice / $cartTotal, 2);
			} else {
				$thisTax = round(($totalTax * $orderItemRow['tax_charge']) / $normalTax, 2);
			}
			$totalTax -= $thisTax;
			if ($totalTax < .05) {
				$thisTax += $totalTax;
			}
			$this->iOrderItems[$orderIndex]['tax_charge'] = $thisTax;
		}
	}

	function getOrderItems() {
		return $this->iOrderItems;
	}

	public static function copyPromotionCode($promotionCode, $fieldValues = array()) {
		$promotionId = getFieldFromId("promotion_id", "promotions", "promotion_code", $promotionCode);
		if (empty($promotionId)) {
			return false;
		}
		return Order::copyPromotion($promotionId, $fieldValues);
	}

	public static function copyPromotion($promotionId, $fieldValues = array()) {
		$promotionRow = ShoppingCart::getCachedPromotionRow($promotionId);
		$promotionId = $promotionRow['promotion_id'];
		if (empty($promotionId)) {
			return false;
		}
		$subtables = array("promotion_banners", "promotion_files", "promotion_group_links", "promotion_purchased_product_categories", "promotion_purchased_product_category_groups", "promotion_purchased_product_departments",
			"promotion_purchased_product_manufacturers", "promotion_purchased_product_tags", "promotion_purchased_product_types", "promotion_purchased_products", "promotion_purchased_sets",
			"promotion_rewards_excluded_product_categories", "promotion_rewards_excluded_product_category_groups", "promotion_rewards_excluded_product_departments", "promotion_rewards_excluded_product_manufacturers",
			"promotion_rewards_excluded_product_tags", "promotion_rewards_excluded_product_types", "promotion_rewards_excluded_products", "promotion_rewards_excluded_sets", "promotion_rewards_product_categories",
			"promotion_rewards_product_category_groups", "promotion_rewards_product_departments", "promotion_rewards_product_manufacturers", "promotion_rewards_product_tags", "promotion_rewards_product_types",
			"promotion_rewards_products", "promotion_rewards_sets", "promotion_rewards_shipping_charges", "promotion_terms_contact_types", "promotion_terms_countries", "promotion_terms_excluded_product_categories",
			"promotion_terms_excluded_product_category_groups", "promotion_terms_excluded_product_departments", "promotion_terms_excluded_product_manufacturers", "promotion_terms_excluded_product_tags",
			"promotion_terms_excluded_product_types", "promotion_terms_excluded_products", "promotion_terms_excluded_sets", "promotion_terms_product_categories", "promotion_terms_product_category_groups",
			"promotion_terms_product_departments", "promotion_terms_product_manufacturers", "promotion_terms_product_tags", "promotion_terms_product_types", "promotion_terms_products", "promotion_terms_sets", "promotion_terms_user_types");
		unset($promotionRow['promotion_id']);
		unset($promotionRow['version']);
		foreach ($fieldValues as $fieldName => $fieldValue) {
			$promotionRow[$fieldName] = $fieldValue;
		}
		if (!array_key_exists("promotion_code", $fieldValues)) {
			$promotionRow['promotion_code'] = getRandomString(20, array("uppercase" => true));
		}
		$promotionDataTable = new DataTable("promotions");
		if (!$newPromotionId = $promotionDataTable->saveRecord(array("name_values" => $promotionRow, "primary_id" => ""))) {
			return false;
		}
		foreach ($subtables as $thisTable) {
			$primaryKey = $GLOBALS['gPrimaryDatabase']->getPrimaryKey($thisTable);
			$resultSet = executeQuery("select * from " . $thisTable . " where promotion_id = ?", $promotionId);
			while ($row = getNextRow($resultSet)) {
				unset($row[$primaryKey]);
				unset($row['version']);
				$row['promotion_id'] = $newPromotionId;
				executeQuery("insert into " . $thisTable . " values (" . implode(",", array_fill(0, count($row), "?")) . ")", $row);
			}
		}
		return $newPromotionId;
	}
}
