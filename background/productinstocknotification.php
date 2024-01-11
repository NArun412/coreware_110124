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
		$this->iProcessCode = "product_in_stock_notification";
	}

	function process() {
		$countArray = array();
		$resultArray = array();
		$resultSet = executeQuery("select * from wish_list_items join wish_lists using (wish_list_id) join users using (user_id) join contacts using (contact_id) where notify_when_in_stock = 1 and " .
			"(product_id in (select product_id from view_of_active_products where non_inventory_item = 1) or " .
			"product_id in (select product_id from product_inventories where quantity > 0 and location_id in " .
			"(select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and (product_distributor_id is null or primary_location = 1)))) " .
			"order by users.client_id");

		$productCatalog = new ProductCatalog();
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			if (!array_key_exists($GLOBALS['gClientRow']['client_code'], $resultArray)) {
				$resultArray[$GLOBALS['gClientRow']['client_code']] = "";
			}

			$forceOutOfStock = CustomField::getCustomFieldData($row['product_id'], "OUT_OF_STOCK", "PRODUCTS");
			if (!empty($forceOutOfStock)) {
				continue;
			}

			$wishListNotificationQuantityThreshold = getPreference("RETAIL_STORE_WISH_LIST_NOTIFICATION_QUANTITY_THRESHOLD");
			$wishListNotificationQuantityThreshold = (empty($wishListNotificationQuantityThreshold) || $wishListNotificationQuantityThreshold < 0 ? 1 : $wishListNotificationQuantityThreshold);

			$customSubstitutions = array();
			if (function_exists("_localGetInventoryCount")) {
				$inventoryCount = _localGetInventoryCount(array("product_id" => $row['product_id'], "contact_id" => $row['contact_id']));
				if (is_array($inventoryCount)) {
					if (array_key_exists("substitutions",$inventoryCount) && is_array($inventoryCount['substitutions'])) {
						$customSubstitutions = $inventoryCount['substitutions'];
					}
					$inventoryCount = $inventoryCount['inventory_count'];
				}
				$wishListNotificationQuantityMet = $inventoryCount >= $wishListNotificationQuantityThreshold;
			} else {
				$inventoryCounts = $productCatalog->getInventoryCounts(true, $row['product_id']);
				$wishListNotificationQuantityMet = !empty($inventoryCounts[$row['product_id']]) && $inventoryCounts[$row['product_id']] >= $wishListNotificationQuantityThreshold;
			}
			if (!$wishListNotificationQuantityMet) {
				continue;
			}
			$inventoryDetailCounts = $productCatalog->getInventoryCounts(false,$row['product_id']);

			$wishListNotificationWaitingThreshold = getPreference("RETAIL_STORE_WISH_LIST_NOTIFICATION_WAITING_THRESHOLD");
			$wishListNotificationWaitingMet = false;
			if (empty($wishListNotificationWaitingThreshold)) {
				$wishListNotificationWaitingMet = true;
			} else {
				$countSet = executeQuery("select count(*) from wish_list_items where product_id = ? and notify_when_in_stock = 1", $row['product_id']);
				if ($countRow = getNextRow($countSet)) {
					if ($countRow['count(*)'] <= $wishListNotificationWaitingThreshold) {
						$wishListNotificationWaitingMet = true;
					}
				}
			}
			if (!$wishListNotificationWaitingMet) {
				continue;
			}

			$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
			$contactRow = Contact::getContact($row['contact_id']);
			$imageUrl = ProductCatalog::getProductImage($row['product_id']);
			$productRow['image_url'] = (startsWith($imageUrl,"http") ? $imageUrl : getDomainName() . $imageUrl);

			$urlLink = (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);
			$productRow['url_link'] = $urlLink;
			$eventRow = getRowFromId("events", "product_id", $row['product_id']);
			if(!empty($eventRow)) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "EVENT_WAITLIST_NOTIFICATION", "inactive = 0");
                $customSubstitutions = array_merge($customSubstitutions, Events::getEventRegistrationSubstitutions($eventRow,$row['contact_id']));
			} else {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_IN_STOCK_NOTIFICATION", "inactive = 0");
			}
			$emailAddress = $row['email_address'];
			if (empty($emailAddress) || empty($emailId)) {
				continue;
			}
			$bccEmails = array();
			$relationshipTypeCode = getPreference("RETAIL_STORE_WISH_LIST_NOTIFICATION_BCC_RELATIONSHIP_TYPE");
			if (!empty($relationshipTypeCode)) {
				$relationshipsSet = executeQuery("select related_contact_id,(select email_address from contacts where contact_id = relationships.related_contact_id) email_address from relationships where contact_id = ?" .
					" and relationship_type_id = (select relationship_type_id from relationship_types where relationship_type_code = ?)", $row['contact_id'], $relationshipTypeCode);
				while ($relationshipRow = getNextRow($relationshipsSet)) {
					$bccEmail = $relationshipRow['email_address'];
					if (!empty($bccEmail)) {
						$bccEmails[] = $bccEmail;
					}
				}
			}
			sendEmail(array("email_id" => $emailId, "email_address" => $emailAddress, "bcc_addresses" => $bccEmails,  "substitutions" => array_merge($contactRow, $productRow, $customSubstitutions), "contact_id" => $contactRow['contact_id'], "additional_information"=>$inventoryDetailCounts));
			$updateSet = executeQuery("update wish_list_items set notify_when_in_stock = 0 where wish_list_item_id = ?", $row['wish_list_item_id']);
			$resultArray[$GLOBALS['gClientRow']['client_code']] .= "Email sent to " . $emailAddress . " for product " . $productRow['product_code'] . "\n";
			if ($updateSet['affected_rows'] == 0 || !empty($updateSet['sql_error'])) {
				$resultArray[$GLOBALS['gClientRow']['client_code']] .= "Updating wishlist failed for contact " . $row['contact_id'] . ": " . $updateSet['sql_error'] . "\n";
			}
			$countArray[$GLOBALS['gClientRow']['client_code']]++;
		}
		foreach ($countArray as $clientCode => $count) {
			$this->addResult($count . " wish list notification emails sent for client " . $clientCode);
			if (!empty($resultArray[$clientCode])) {
				$this->addResult($resultArray[$clientCode]);
			}
		}

		$countArray = array();
		$resultArray = array();
		$resultSet = executeQuery("select * from product_availability_notifications join products using (product_id) join contacts using (contact_id) where " .
			"product_id in (select product_id from product_inventories where quantity > 0 and location_id in " .
			"(select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and (product_distributor_id is null or primary_location = 1))) order by contacts.client_id");

		$productCatalog = new ProductCatalog();
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			if (!array_key_exists($GLOBALS['gClientRow']['client_code'], $resultArray)) {
				$resultArray[$GLOBALS['gClientRow']['client_code']] = "";
			}

			$forceOutOfStock = CustomField::getCustomFieldData($row['product_id'], "OUT_OF_STOCK", "PRODUCTS");
			if (!empty($forceOutOfStock)) {
				continue;
			}

			$wishListNotificationQuantityThreshold = getPreference("RETAIL_STORE_WISH_LIST_NOTIFICATION_QUANTITY_THRESHOLD");
			$wishListNotificationQuantityThreshold = $wishListNotificationQuantityThreshold ?: 1;

			$inventoryCounts = $productCatalog->getInventoryCounts(true, $row['product_id']);
			$wishListNotificationQuantityMet = !empty($inventoryCounts[$row['product_id']]) && $inventoryCounts[$row['product_id']] >= $wishListNotificationQuantityThreshold;

			if (!$wishListNotificationQuantityMet) {
				continue;
			}

			$wishListNotificationWaitingThreshold = getPreference("RETAIL_STORE_WISH_LIST_NOTIFICATION_WAITING_THRESHOLD");
			$wishListNotificationWaitingMet = false;
			if (empty($wishListNotificationWaitingThreshold)) {
				$wishListNotificationWaitingMet = true;
			} else {
				$countSet = executeQuery("select count(*) from wish_list_items where product_id = ? and notify_when_in_stock = 1", $row['product_id']);
				if ($countRow = getNextRow($countSet)) {
					if ($countRow['count(*)'] <= $wishListNotificationWaitingThreshold) {
						$wishListNotificationWaitingMet = true;
					}
				}
			}
			if (!$wishListNotificationWaitingMet) {
				continue;
			}
			$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
			$productDataRow = getRowFromId("product_data", "product_id", $row['product_id']);
			$contactRow = Contact::getContact($row['contact_id']);
			$productRow['image_url'] = ProductCatalog::getProductImage($row['product_id']);

			$urlLink = (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);
			$productRow['url_link'] = $urlLink;
			$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_IN_STOCK_NOTIFICATION", "inactive = 0");
			$emailAddress = $row['email_address'];
			if (empty($emailAddress) || empty($emailId)) {
				continue;
			}
			sendEmail(array("email_id" => $emailId, "email_address" => $emailAddress, "substitutions" => array_merge($contactRow, $productRow, $productDataRow), "contact_id" => $contactRow['contact_id']));
			executeQuery("delete from product_availability_notifications where product_availability_notification_id = ?", $row['product_availability_notification_id']);
			$resultArray[$GLOBALS['gClientRow']['client_code']] .= "Email sent to " . $emailAddress . " for product " . $productRow['product_code'] . "\n";
			$countArray[$GLOBALS['gClientRow']['client_code']]++;
		}
		foreach ($countArray as $clientCode => $count) {
			$this->addResult($count . " product notification emails sent for client " . $clientCode);
			if (!empty($resultArray[$clientCode])) {
				$this->addResult($resultArray[$clientCode]);
			}
		}

		$countArray = array();
		$orderCountArray = array();
		$resultSet = executeQuery("select * from product_inventory_notifications join products using (product_id) where product_inventory_notifications.inactive = 0 and products.inactive = 0 order by products.client_id");
		$this->addResult($resultSet['row_count'] . " notifications found to process");
		while ($row = getNextRow($resultSet)) {
			$productDistributorIds = array();
			$inventoryCount = 0;
			$countSet = executeQuery("select * from product_inventories where product_id = ? and quantity > 0 and location_id in (select location_id from locations where " .
				(empty($row['product_distributor_id']) ? "product_distributor_id is not null" : "product_distributor_id = " . $row['product_distributor_id']) . " and primary_location = 1 and inactive = 0 and ignore_inventory = 0)", $row['product_id']);
			while ($countRow = getNextRow($countSet)) {
				if (in_array($countRow['product_distributor_id'], $productDistributorIds)) {
					continue;
				}
				$inventoryCount += $countRow['quantity'];
				$productDistributorIds[] = $countRow['product_distributor_id'];
			}
			$useNotification = false;

			switch ($row['comparator']) {
				case "=":
				case "==":
					if ($inventoryCount == $row['quantity']) {
						$useNotification = true;
					}
					break;
				case "<":
					if ($inventoryCount < $row['quantity']) {
						$useNotification = true;
					}
					break;
				case ">":
					if ($inventoryCount > $row['quantity']) {
						$useNotification = true;
					}
					break;
				case "=>":
				case ">=":
					if ($inventoryCount >= $row['quantity']) {
						$useNotification = true;
					}
					break;
				case "=<":
				case "<=":
					if ($inventoryCount <= $row['quantity']) {
						$useNotification = true;
					}
					break;
			}
			if (!$useNotification) {
				continue;
			}
			changeClient($row['client_id']);
			$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
			$productDataRow = getRowFromId("product_data", "product_id", $row['product_id']);
			$body = "<p>A product has reached an inventory quantity (" . $inventoryCount . ") to trigger a notification.</p><p>Product ID: " . $productRow['product_id'] . "<br>Product Code: " . $productRow['product_code'] . "<br>Description: " .
				htmlText($productRow['description']) . "<br>UPC Code: " . $productDataRow['upc_data'] . "</p><p>Criteria: Quantity is " . $row['comparator'] . " " . $row['quantity'] . (empty($locationDescription) ? "" : " from " . $locationDescription) . "</p>";
			$subject = "Product Inventory Notification";
			$notificationCompleted = true;
			$placeOrder = false;
			if (!empty($row['place_order']) && $row['order_quantity'] > 0) {
				$placeOrder = true;
			}
			$productInventories = array();
			if ($placeOrder) {
				$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is not null and primary_location = 1 and " .
					"location_id in (select location_id from product_inventories where product_id = ? and quantity > 0) order by sort_order,location_id", $GLOBALS['gClientId'], $row['product_id']);
				$productDistributorIds = array();
				while ($locationRow = getNextRow($locationSet)) {
					if (in_array($locationRow['product_distributor_id'], $productDistributorIds)) {
						continue;
					}
					$productDistributorIds[] = $locationRow['product_distributor_id'];
					$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $locationRow['location_id']);
					if (empty($productInventoryRow)) {
						$productInventoryRow['quantity'] = 0;
					}
					if ($productInventoryRow['quantity'] <= 0) {
						continue;
					}
					$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $locationRow['location_id'], $productInventoryRow,false);
					$locationRow['quantity'] = $productInventoryRow['quantity'];
					$locationRow['cost'] = $cost;
					$productInventories[$locationRow['location_id']] = $locationRow;
				}
			}

			if ($placeOrder) {
				if (empty($productInventories)) {
					$body .= "<p>Product Distributor order unable to be created because there is no inventory.</p>";
					$notificationCompleted = false;
					$placeOrder = false;
				}
			}
			if ($placeOrder) {
				if (!empty($row['location_id']) && !array_key_exists($row['location_id'], $productInventories)) {
					$body .= "<p>Product Distributor order unable to be created because there is no inventory.</p>";
					$notificationCompleted = false;
					$placeOrder = false;
				}
			}
			# get inventory and costs for all distributors
			# get location information for each location
			# check to see if there is inventory for selected location
			# if no selected location, order locations with inventory according to choice
			# place order
			# if order is less than desired, and multiple orders selected, place another until fulfilled

			if ($placeOrder) {
				if (!empty($row['location_id'])) {
					$productInventories = array($productInventories[$row['location_id']]);
				} else {
					if (!empty($row['use_lowest_price'])) {
						usort($productInventories, array($this, "sortPrice"));
					}
					$productInventories = array_values($productInventories);
					if (empty($row['allow_multiple'])) {
						$productInventories = array($productInventories[0]);
					}
				}
				$orderQuantity = $row['order_quantity'];
				$orderCountArray[$GLOBALS['gClientRow']['client_code']] = 0;
				foreach ($productInventories as $thisLocation) {
					$productDistributor = ProductDistributor::getProductDistributorInstance($thisLocation['location_id']);
					if (!$productDistributor) {
						$body .= "<p>Product Distributor unable to be created for location " . $thisLocation['description'] . "(" . $thisLocation['location_id'] . ".</p>";
					} else {
						$quantity = min($orderQuantity, $thisLocation['quantity']);
						$productOrderArray = array(array("product_id" => $row['product_id'], "quantity" => $quantity));
						$returnValue = $productDistributor->placeDistributorOrder($productOrderArray, array("notes" => "Placed automatically from Product Inventory Notifications", "user_id" => $row['user_id']));
						if ($returnValue === false) {
							$body .= "<p>Order for " . $thisLocation['description'] . "(" . $thisLocation['location_id'] . ") was unable to be placed: " . $productDistributor->getErrorMessage() . "</p>";
						} else {
							$orderCountArray[$GLOBALS['gClientRow']['client_code']]++;
							if (array_key_exists("dealer", $returnValue)) {
								$body .= "<p>Distributor Order ID " . $returnValue['dealer']['distributor_order_id'] . " placed for " . $quantity . " with " . $thisLocation['description'] . ", Distributor Order #" . $returnValue['dealer']['order_number'] . ".</p>";
							}
							if (array_key_exists("class_3", $returnValue)) {
								$body .= "<p>Distributor Order ID " . $returnValue['class_3']['distributor_order_id'] . " placed for " . $quantity . " with " . $thisLocation['description'] . ", Distributor Order #" . $returnValue['class_3']['order_number'] . " for a Class 3 product.</p>";
							}
						}
						$orderQuantity -= $quantity;
					}
					if ($orderQuantity <= 0) {
						break;
					}
				}
				if ($orderCountArray[$GLOBALS['gClientRow']['client_code']] == 0) {
					$notificationCompleted = false;
				}
			}
			$result = sendEmail(array("body" => $body, "subject" => $subject, "email_address" => $row['email_address'], "send_immediately" => true));
			if (!$result) {
				$notificationCompleted = false;
			}
			if ($notificationCompleted) {
				executeQuery("update product_inventory_notifications set inactive = 1 where product_inventory_notification_id = ?", $row['product_inventory_notification_id']);
			}
			$countArray[$GLOBALS['gClientRow']['client_code']]++;
		}
		foreach ($countArray as $clientCode => $count) {
			$this->addResult($count . " dealer notification emails sent for client " . $clientCode);
		}
		foreach ($orderCountArray as $clientCode => $count) {
			$this->addResult($count . " dealer distributor orders placed for client " . $clientCode);
		}

		$orderCount = 0;
		$dateLastRun = date("z", strtotime($this->iBackgroundProcessRow['last_start_time']));
		if ($dateLastRun != date("z")) {

			executeQuery("update product_inventories set on_order_quantity = null where on_order_quantity is not null and product_id not in (select product_id from distributor_order_items " .
				"where distributor_order_id in (select distributor_order_id from distributor_orders where date_completed is null))");

			$clientIds = array();
			$resultSet = executeQuery("select distinct client_id from product_inventories join locations using (location_id) where reorder_level is not null and manual_order = 0 and " .
				"quantity <= reorder_level and locations.inactive = 0 and product_distributor_id is null and on_order_quantity is null");
			while ($row = getNextRow($resultSet)) {
				$clientIds[] = $row['client_id'];
			}
			$this->addResult(count($clientIds) . " clients found with replenishment/reorder levels");

			foreach ($clientIds as $clientId) {
				changeClient($clientId);
				$autoOrderProcessing = getPreference("AUTO_ORDER_PROCESSING");
				$notificationEmails = getNotificationEmails("PRODUCT_REORDER_LEVEL_REACHED");
				if (empty($autoOrderProcessing) && empty($notificationEmails)) {
					continue;
				}
				$notificationCount = 0;
				$notificationEmailBody = "<p>The following products are at or below reorder level (ID, UPC, description):</p><ul>";

				$usedProductInventory = array();
				$resultSet = executeQuery("select *,(select sort_order from locations where location_id = product_inventories.location_id) as location_sort_order " .
					"from product_inventories where on_order_quantity is null and reorder_level is not null and manual_order = 0 and " .
					"quantity <= reorder_level and location_id in (select location_id from locations where " .
					"inactive = 0 and product_distributor_id is null and client_id = ?) order by location_sort_order,location_id", $clientId);
				$this->addResult($resultSet['row_count'] . " products found for auto reordering or reorder level notification for client " . $GLOBALS['gClientName']);
				while ($row = getNextRow($resultSet)) {
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
					if (!empty($notificationEmails)) {
						$notificationCount++;
						$notificationEmailBody .= "<li>" . $productRow['product_id'] . ", " . $productRow['upc_code'] . ", " . $productRow['description'] . "</li>";
					}
					if (empty($autoOrderProcessing) || empty($row['replenishment_level']) || $row['replenishment_level'] < $row['quantity']) {
						continue;
					}
					$fcaUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
					$localLocationRow = getRowFromId("locations", "location_id", $row['location_id']);
					if (!array_key_exists($row['product_id'], $usedProductInventory)) {
						$usedProductInventory[$row['product_id']] = array();
					}

					$productInventories = array();
					$productDistributorId = getFieldFromId("product_distributor_id", "product_manufacturers", "product_manufacturer_id", getFieldFromId("product_manufacturer_id", "products", "product_id", $row['product_id']));
					$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is not null and " .
						(empty($productDistributorId) ? "" : "product_distributor_id = " . $productDistributorId . " and ") . "primary_location = 1 and " .
						"location_id in (select location_id from product_inventories where product_id = ? and quantity > 0) order by sort_order,location_id", $GLOBALS['gClientId'], $row['product_id']);
					$productDistributorIds = array();
					while ($locationRow = getNextRow($locationSet)) {
						if (in_array($locationRow['product_distributor_id'], $productDistributorIds)) {
							continue;
						}
						if (!array_key_exists($locationRow['product_distributor_id'], $usedProductInventory[$row['product_id']])) {
							$usedProductInventory[$row['product_id']][$locationRow['product_distributor_id']] = 0;
						}
						$productDistributorIds[] = $locationRow['product_distributor_id'];
						$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $locationRow['location_id']);
						if (empty($productInventoryRow)) {
							$productInventoryRow['quantity'] = 0;
						}
						$productInventoryRow['quantity'] -= $usedProductInventory[$row['product_id']][$locationRow['product_distributor_id']];
						if ($productInventoryRow['quantity'] <= 0) {
							continue;
						}
						$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $locationRow['location_id'], $productInventoryRow,false);
						$locationRow['quantity'] = $productInventoryRow['quantity'];
						$locationRow['cost'] = $cost;
						$productInventories[$locationRow['location_id']] = $locationRow;
					}

					if (empty($productInventories)) {
						continue;
					}

					usort($productInventories, array($this, "sortPrice"));
					$productInventories = array_values($productInventories);

					$orderQuantity = $row['replenishment_level'] - $row['quantity'];
					$orderCount = 0;
					foreach ($productInventories as $thisLocation) {
						$orderLocationId = $thisLocation['location_id'];
						if (!empty($localLocationRow['location_group_id'])) {
							$locationSet = executeQuery("select location_id from locations where product_distributor_id = ? and location_group_id = ?", $thisLocation['product_distributor_id'], $localLocationRow['location_group_id']);
							if ($locationRow = getNextRow($locationSet)) {
								$orderLocationId = $locationRow['location_id'];
							}
						}
						$productDistributor = ProductDistributor::getProductDistributorInstance($orderLocationId);
						if (!$productDistributor) {
							$this->addResult("Product Distributor unable to be created for location " . $thisLocation['description'] . "(" . $thisLocation['location_id'] . ".");
						} elseif ($GLOBALS['gDevelopmentServer'] && empty(getPreference('DEVELOPMENT_TEST_DISTRIBUTORS'))) {
							$this->addResult("Distributor order not created on non-production client: location " . $thisLocation['description'] . "(" . $thisLocation['location_id'] . ".");
						} else {
							$quantity = min($orderQuantity, $thisLocation['quantity']);
							$productOrderArray = array(array("product_id" => $row['product_id'], "quantity" => $quantity));
							$returnValue = $productDistributor->placeDistributorOrder($productOrderArray, array("notes" => "Placed automatically from Product Inventory Replenishment Levels", "user_id" => $fcaUserId));
							if ($returnValue === false) {
								$this->addResult("Order for " . $thisLocation['description'] . "(" . $thisLocation['location_id'] . ") was unable to be placed: " . $productDistributor->getErrorMessage());
							} else {
								$usedProductInventory[$row['product_id']][$thisLocation['product_distributor_id']] += $quantity;
								if (!empty($notificationEmails)) {
									$notificationEmailBody .= "<li>**** Order successfully placed for this product for quantity " . $quantity . " from " . $thisLocation['description'] . "</li>";
								}
								$orderCount++;
								if (array_key_exists("dealer", $returnValue)) {
									$this->addResult("Distributor Order ID " . $returnValue['dealer']['distributor_order_id'] . " placed for " . $quantity . " with " . $thisLocation['description'] . ", Distributor Order #" . $returnValue['dealer']['order_number'] . ".");
								}
								if (array_key_exists("class_3", $returnValue)) {
									$this->addResult("Distributor Order ID " . $returnValue['class_3']['distributor_order_id'] . " placed for " . $quantity . " with " . $thisLocation['description'] . ", Distributor Order #" . $returnValue['class_3']['order_number'] . " for a Class 3 product.");
								}
							}
							$orderQuantity -= $quantity;
						}
						if ($orderQuantity <= 0) {
							break;
						}
					}
				}
				if ($notificationCount > 0) {
					$notificationEmailBody .= "</ul>";
					sendEmail(array("body" => $notificationEmailBody, "subject" => "Products at or below reorder level", "email_addresses" => $notificationEmails));
				}
			}

			$this->addResult($orderCount . " dealer distributor orders placed");
		}
	}

	function sortPrice($a, $b) {
		if ($a['cost'] == $b['cost']) {
			if ($a['sort_order'] == $b['sort_order']) {
				return 0;
			}
			return ($a['sort_order'] < $b['sort_order'] ? -1 : 1);
		}
		return ($a['cost'] < $b['cost'] ? -1 : 1);
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
