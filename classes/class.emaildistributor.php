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

class EmailDistributor extends ProductDistributor {

	public $iTrackingAlreadyRun = false;
	private $iOrderEmail = false;

	function __construct($locationId) {
		$this->iProductDistributorCode = "EMAILDISTRIBUTOR";
		parent::__construct($locationId);
	}

	function testCredentials() {
		return true;
	}

	function syncProducts($parameters = array()) {
		return "Product import not necessary";
	}

	function syncInventory($parameters = array()) {
		return "Inventory import not necessary";
	}

	function getCategories($parameters = array()) {
		return array();
	}

	function getManufacturers($parameters = array()) {
		return array();
	}

	function getFacets($parameters = array()) {
		return array();
	}

	function getCustomDistributorProductCode($productId) {
		return getFieldFromId("manufacturer_sku", "product_data", "product_id", $productId);
	}

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		$this->iOrderEmail = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
		$ordersRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($ordersRow['contact_id']);
		if (empty($ordersRow['address_id'])) {
			$addressRow = $contactRow;
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $ordersRow['address_id']);
			if (empty($addressRow['address_1']) || empty($addressRow['city'])) {
				$addressRow = $contactRow;
			}
		}

		$orderParts = $this->splitOrder($orderId, $orderItems);
		if ($orderParts === false) {
			return false;
		}
		$customerOrderItemRows = $orderParts['customer_order_items'];
		$dealerOrderItemRows = $orderParts['dealer_order_items'];

		$returnValues = array();
		if (!empty($customerOrderItemRows)) {
			$body = "<p>From: " . $GLOBALS['gClientRow']['business_name'] . ", " . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . "</p>";
			$body .= "<p>The following products where ordered on the " . $GLOBALS['gClientRow']['business_name'] . " website. The customer name and shipping address are:</p>";
			$body .= "<p>" . getDisplayName($ordersRow['contact_id']) . "<br>" . $addressRow["address_1"] . "<br>";
			if (!empty($addressRow["address_2"])) {
				$body .= $addressRow["address_2"] . "<br>";
			}
			$body .= $addressRow["city"] . ", " . $addressRow["state"] . " " . $addressRow["postal_code"] . "<br>";
			if ($addressRow['country_id'] != 1000) {
				$body .= getFieldFromId("country_name", "countries", "country_id", $addressRow["country_id"]) . "<br>";
			}
			if (!empty($ordersRow['phone_number'])) {
				$body .= $ordersRow['phone_number'] . "<br>";
			}
			if (!empty($contactRow['email_address'])) {
				$body .= $contactRow['email_address'] . "<br>";
			}
			$body .= "</p><p>The " . $GLOBALS['gClientRow']['business_name'] . " order ID is " . $ordersRow['order_id'] . ".</p><ul>";
			foreach ($customerOrderItemRows as $thisItemRow) {
				if (empty($thisItemRow['description'])) {
					$thisItemRow['description'] = getFieldFromId("description", "products", "product_id", $thisItemRow['product_id']);
				}
				$itemDescription = $thisItemRow['quantity'] . " of " . $thisItemRow['distributor_product_code'] . " - " . $thisItemRow['description'];
				$resultSet = executeQuery("select * from order_item_addons join product_addons using (product_addon_id) where order_item_id = ?", $thisItemRow['order_item_id']);
				while ($row = getNextRow($resultSet)) {
					$itemDescription .= ", " . (empty($row['group_description']) ? "" : $row['group_description'] . ": ") . $row['description'];
				}

				$customFieldSet = executeQuery("select description,custom_field_id,integer_data,number_data,text_data,date_data,(select control_value from custom_field_controls where " .
					"custom_field_id = custom_fields.custom_field_id and control_name = 'data_type') data_type from custom_field_data join custom_fields using (custom_field_id) where " .
					"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS') and primary_identifier = ?", $row['order_item_id']);
				while ($customFieldRow = getNextRow($customFieldSet)) {
					switch ($customFieldRow['data_type']) {
						case "int":
							$customFieldRow['data_value'] = $customFieldRow['integer_data'];
							break;
						case "decimal":
							$customFieldRow['data_value'] = $customFieldRow['number_data'];
							break;
						case "date":
							$customFieldRow['data_value'] = $customFieldRow['date_data'];
							break;
						default:
							if (startsWith($customFieldRow['text_data'], "[")) {
								$customFieldRow['data_value'] = "";
								$dataArray = json_decode($customFieldRow['text_data'], true);
								foreach ($dataArray as $thisRow) {
									foreach ($thisRow as $fieldName => $fieldData) {
										$customFieldRow['data_value'] .= "<br>" . snakeCaseToDescription($fieldName) . ": " . $fieldData;
									}
								}
							} else {
								$customFieldRow['data_value'] = $customFieldRow['text_data'];
							}
							break;
					}
					if (!empty($customFieldRow['data_value'])) {
						$itemDescription .= "<br>" . $customFieldRow['description'] . ": " . $customFieldRow['data_value'];
					}
				}
				$body .= "<li>" . $itemDescription . "</li>";
			}
			$body .= "</ul>";
			sendEmail(array("body" => $body, "subject" => "Order from " . $GLOBALS['gClientRow']['business_name'], "email_address" => $this->iOrderEmail));

			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], $ordersRow['order_id']);
			$remoteOrderId = $orderSet['insert_id'];
			foreach ($customerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $ordersRow['order_id'], "ship_to" => $ordersRow['full_name']);
		}

		if (!empty($dealerOrderItemRows)) {
			$body = "<p>From: " . $GLOBALS['gClientRow']['business_name'] . ", " . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . "</p>";
			$body .= "<p>The following products where ordered on the " . $GLOBALS['gClientRow']['business_name'] . " website. The order needs to be shipped to:</p>";
			$body .= "<p>" . $GLOBALS['gClientRow']['business_name'] . "<br>" . $this->iLocationContactRow["address_1"] . "<br>";
			if (!empty($this->iLocationContactRow["address_2"])) {
				$body .= $this->iLocationContactRow["address_2"] . "<br>";
			}
			$body .= $this->iLocationContactRow["city"] . ", " . $this->iLocationContactRow["state"] . " " . $this->iLocationContactRow["postal_code"] . "<br>";
			if ($this->iLocationContactRow['country_id'] != 1000) {
				$body .= getFieldFromId("country_name", "countries", "country_id", $this->iLocationContactRow["country_id"]) . "<br>";
			}
			if (!empty($this->iLocationContactRow['email_address'])) {
				$body .= $this->iLocationContactRow['email_address'] . "<br>";
			}
			$body .= "</p><p>The " . $GLOBALS['gClientRow']['business_name'] . " order ID is " . $ordersRow['order_id'] . ".</p><ul>";
			foreach ($dealerOrderItemRows as $thisItemRow) {
				if (empty($thisItemRow['description'])) {
					$thisItemRow['description'] = getFieldFromId("description", "products", "product_id", $thisItemRow['product_id']);
				}
				$itemDescription = $thisItemRow['quantity'] . " of " . $thisItemRow['distributor_product_code'] . " - " . $thisItemRow['description'];
				$resultSet = executeQuery("select * from order_item_addons join product_addons using (product_addon_id) where order_item_id = ?", $thisItemRow['order_item_id']);
				while ($row = getNextRow($resultSet)) {
					$itemDescription .= ", " . (empty($row['group_description']) ? "" : $row['group_description'] . ": ") . $row['description'];
				}
				$customFieldSet = executeQuery("select description,custom_field_id,integer_data,number_data,text_data,date_data,(select control_value from custom_field_controls where " .
					"custom_field_id = custom_fields.custom_field_id and control_name = 'data_type') data_type from custom_field_data join custom_fields using (custom_field_id) where " .
					"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS') and primary_identifier = ?", $row['order_item_id']);
				while ($customFieldRow = getNextRow($customFieldSet)) {
					switch ($customFieldRow['data_type']) {
						case "int":
							$customFieldRow['data_value'] = $customFieldRow['integer_data'];
							break;
						case "decimal":
							$customFieldRow['data_value'] = $customFieldRow['number_data'];
							break;
						case "date":
							$customFieldRow['data_value'] = $customFieldRow['date_data'];
							break;
						default:
							if (startsWith($customFieldRow['text_data'], "[")) {
								$customFieldRow['data_value'] = "";
								$dataArray = json_decode($customFieldRow['text_data'], true);
								foreach ($dataArray as $thisRow) {
									foreach ($thisRow as $fieldName => $fieldData) {
										$customFieldRow['data_value'] .= "<br>" . snakeCaseToDescription($fieldName) . ": " . $fieldData;
									}
								}
							} else {
								$customFieldRow['data_value'] = $customFieldRow['text_data'];
							}
							break;
					}
					if (!empty($customFieldRow['data_value'])) {
						$itemDescription .= "<br>" . $customFieldRow['description'] . ": " . $customFieldRow['data_value'];
					}
				}
				$body .= "<li>" . $itemDescription . "</li>";
			}
			$body .= "</ul>";
			sendEmail(array("body" => $body, "subject" => "Order from " . $GLOBALS['gClientRow']['business_name'], "email_address" => $this->iOrderEmail));

			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], $ordersRow['order_id']);
			$remoteOrderId = $orderSet['insert_id'];
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $ordersRow['order_id'], "ship_to" => $ordersRow['full_name']);
		}

		return $returnValues;
	}

	function placeDistributorOrder($productArray, $parameters = array()) {
		$this->iOrderEmail = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
		$userId = $parameters['user_id'];
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$GLOBALS['gPrimaryDatabase']->startTransaction();

# create distributor order record

		$resultSet = executeQuery("insert into distributor_orders (client_id,order_time,location_id,order_number,user_id,notes) values (?,now(),?,999999999,?,?)",
			$GLOBALS['gClientId'], $this->iLocationId, $userId, $parameters['notes']);
		if (!empty($resultSet['sql_error'])) {
			$this->iErrorMessage = "SQL Error: " . $resultSet['sql_error'];
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}
		$distributorOrderId = $resultSet['insert_id'];

# Get Location ID

		$locationId = $this->getLocation();

# Create order item rows

		$dealerOrderItemRows = array();
		foreach ($productArray as $thisProduct) {
			if (empty($thisProduct) || empty($thisProduct['quantity'])) {
				continue;
			}
			if (array_key_exists($thisProduct['product_id'], $dealerOrderItemRows)) {
				$dealerOrderItemRows[$thisProduct['product_id']]['quantity'] += $thisProduct['quantity'];
				$dealerOrderItemRows[$thisProduct['product_id']]['notes'] .= (empty($dealerOrderItemRows[$thisProduct['product_id']]['notes']) ? "" : "\n") . $thisProduct['notes'];
				continue;
			}
			$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_distributor_id",
				$this->iLocationRow['product_distributor_id'], "product_id = ?", $thisProduct['product_id']);
			if (empty($distributorProductCode)) {
				$distributorProductCode = getFieldFromId("manufacturer_sku", "product_data", "product_id", $thisProduct['product_id']);
			}
			$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisProduct['product_id'], "location_id = ?", ProductDistributor::getInventoryLocation($locationId));
			if (empty($inventoryQuantity) || empty($distributorProductCode)) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$this->iErrorMessage = "Product '" . getFieldFromId("description", "products", "product_id", $thisProduct['product_id']) . "' is not available from this distributor. Inventory: " . $inventoryQuantity . ", SKU: " . $distributorProductCode;
				return false;
			}
			$dealerOrderItemRows[$thisProduct['product_id']] = array("distributor_product_code" => $distributorProductCode, "product_id" => $thisProduct['product_id'], "quantity" => $thisProduct['quantity'], "notes" => $thisProduct['notes']);
		}

		if (empty($dealerOrderItemRows)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "No products found to order";
			return false;
		}

		$body = "<p>From: " . $GLOBALS['gClientRow']['business_name'] . ", " . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . "</p>";
		$body .= "<p>The following products where ordered on the " . $GLOBALS['gClientRow']['business_name'] . " website. The order needs to be shipped to:</p>";
		$body .= "<p>" . $GLOBALS['gClientRow']['business_name'] . "<br>" . $this->iLocationContactRow["address_1"] . "<br>";
		if (!empty($this->iLocationContactRow["address_2"])) {
			$body .= $this->iLocationContactRow["address_2"] . "<br>";
		}
		$body .= $this->iLocationContactRow["city"] . ", " . $this->iLocationContactRow["state"] . " " . $this->iLocationContactRow["postal_code"] . "<br>";
		if ($this->iLocationContactRow['country_id'] != 1000) {
			$body .= getFieldFromId("country_name", "countries", "country_id", $this->iLocationContactRow["country_id"]) . "<br>";
		}
		if (!empty($this->iLocationContactRow['email_address'])) {
			$body .= $this->iLocationContactRow['email_address'] . "<br>";
		}
		$body .= "</p><p>The " . $GLOBALS['gClientRow']['business_name'] . " order ID is " . $distributorOrderId . ".</p><ul>";
		foreach ($dealerOrderItemRows as $thisItemRow) {
			if (empty($thisItemRow['description'])) {
				$thisItemRow['description'] = getFieldFromId("description", "products", "product_id", $thisItemRow['product_id']);
			}
			$itemDescription = $thisItemRow['quantity'] . " of " . $thisItemRow['distributor_product_code'] . " - " . $thisItemRow['description'];
			$resultSet = executeQuery("select * from order_item_addons join product_addons using (product_addon_id) where order_item_id = ?", $thisItemRow['order_item_id']);
			while ($row = getNextRow($resultSet)) {
				$itemDescription .= ", " . (empty($row['group_description']) ? "" : $row['group_description'] . ": ") . $row['description'];
			}
			$customFieldSet = executeQuery("select description,custom_field_id,integer_data,number_data,text_data,date_data,(select control_value from custom_field_controls where " .
				"custom_field_id = custom_fields.custom_field_id and control_name = 'data_type') data_type from custom_field_data join custom_fields using (custom_field_id) where " .
				"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS') and primary_identifier = ?", $row['order_item_id']);
			while ($customFieldRow = getNextRow($customFieldSet)) {
				switch ($customFieldRow['data_type']) {
					case "int":
						$customFieldRow['data_value'] = $customFieldRow['integer_data'];
						break;
					case "decimal":
						$customFieldRow['data_value'] = $customFieldRow['number_data'];
						break;
					case "date":
						$customFieldRow['data_value'] = $customFieldRow['date_data'];
						break;
					default:
						if (startsWith($customFieldRow['text_data'], "[")) {
							$customFieldRow['data_value'] = "";
							$dataArray = json_decode($customFieldRow['text_data'], true);
							foreach ($dataArray as $thisRow) {
								foreach ($thisRow as $fieldName => $fieldData) {
									$customFieldRow['data_value'] .= "<br>" . snakeCaseToDescription($fieldName) . ": " . $fieldData;
								}
							}
						} else {
							$customFieldRow['data_value'] = $customFieldRow['text_data'];
						}
						break;
				}
				if (!empty($customFieldRow['data_value'])) {
					$itemDescription .= "<br>" . $customFieldRow['description'] . ": " . $customFieldRow['data_value'];
				}
			}
			$body .= "<li>" . $itemDescription . "</li>";
		}
		$body .= "</ul>";
		sendEmail(array("body" => $body, "subject" => "Order from " . $GLOBALS['gClientRow']['business_name'], "email_address" => $this->iOrderEmail));

		$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $distributorOrderId);

		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		return array();
	}
}
