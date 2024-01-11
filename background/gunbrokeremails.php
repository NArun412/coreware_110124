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
		$this->iProcessCode = "gunbroker_emails";
	}

	function process() {
		$clientSet = executeQuery("select * from clients");
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);

# get gunbroker orders

			$autoEmail = getPreference("GUNBROKER_AUTO_EMAIL");
			if (empty($autoEmail)) {
				continue;
			}
			try {
				$gunBroker = new GunBroker();
			} catch (Exception $e) {
				$this->addResult("Unable to get orders from GunBroker for client " . $clientRow['client_code'] . ". Make sure username & password are set and correct.");
				continue;
			}
			$nameValues = array();
			$nameValues['PageSize'] = "300";
			$nameValues['PageIndex'] = "1";
			$nameValues['OrderStatus'] = "0";
			$nameValues['TimeFrame'] = "3";
			$orders = $gunBroker->getOrders($nameValues);
			$productCatalog = new ProductCatalog();
			$emailableOrders = array();
			foreach ($orders as $thisOrder) {
				if ($thisOrder['paymentReceived'] || $thisOrder['orderCancelled']) {
					continue;
				}
				$gunbrokerOrderCode = "GUNBROKER_CUSTOMER_" . $thisOrder['orderID'];
				$emailLogId = getFieldFromId("email_log_id", "email_log", "client_id", $GLOBALS['gClientId'], "parameters like ?", "%" . $gunbrokerOrderCode . "%");
				$emailQueueId = getFieldFromId("email_queue_id", "email_queue", "client_id", $GLOBALS['gClientId'], "parameters like ?", "%" . $gunbrokerOrderCode . "%");
				if (!empty($emailLogId) || !empty($emailQueueId)) {
					continue;
				}

				$items = "";
				$itemsWithoutUpcFound = false;
				$belowPrice = false;
				foreach ($thisOrder['orderItemsCollection'] as $thisItem) {
					$itemData = $gunBroker->getItemData($thisItem['itemID']);
					$itemData['upc'] = trim($itemData['upc']);
					if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
						$itemData['upc'] = trim($itemData['gtin']);
					}
					if (empty($itemData['upc'])) {
						$itemsWithoutUpcFound = true;
					}
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc']);
					if (empty($productId)) {
						$itemsWithoutUpcFound = true;
						$items .= (empty($items) ? "" : "<br>") . $thisItem['quantity'] . " of NO UPC - " . $thisItem['title'];
					} else {
						$salePriceInfo = $productCatalog->getProductSalePrice($productId);
						$salePrice = $salePriceInfo['sale_price'];
						if ($salePrice < $thisItem['itemPrice']) {
							$belowPrice = true;
						}
						$items .= (empty($items) ? "" : "<br>") . $thisItem['quantity'] . " of <a target='_blank' href='/products?url_page=show&primary_id=" . $productId . "'>" . $itemData['upc'] . " - " . $thisItem['title'] . "</a>";
					}
				}
				if ($belowPrice || $itemsWithoutUpcFound) {
					continue;
				}
				$emailableOrders[] = $thisOrder['orderID'];
			}
# Send Emails

			$count = 0;
			foreach ($emailableOrders as $gunBrokerOrderId) {
				$gunbrokerOrderCode = "GUNBROKER_CUSTOMER_" . $gunBrokerOrderId;
				$emailLogId = getFieldFromId("email_log_id", "email_log", "client_id", $GLOBALS['gClientId'], "parameters like ?", "%" . $gunbrokerOrderCode . "%");
				$emailQueueId = getFieldFromId("email_queue_id", "email_queue", "client_id", $GLOBALS['gClientId'], "parameters like ?", "%" . $gunbrokerOrderCode . "%");
				if (!empty($emailLogId) || !empty($emailQueueId)) {
					$this->addResult("Email for " . $gunBrokerOrderId . " already sent");
					continue;
				}

				$orderData = $gunBroker->getOrder($gunBrokerOrderId);
				if ($orderData['paymentReceived']) {
					continue;
				}
				$userContactInfo = $gunBroker->getUserContactInfo($orderData['buyer']['userID']);
				if (empty($orderData['billToEmail'])) {
					$orderData['billToEmail'] = $userContactInfo['email'];
				}
				if (empty($orderData['billToEmail'])) {
					continue;
				}

				$productIdList = "";
				$productIdArray = array();
				$productRow = array();
				$productFailure = false;
				foreach ($orderData['items'] as $thisItem) {
					$itemData = $gunBroker->getItemData($thisItem['itemID']);
					if (empty($itemData)) {
						$productFailure = true;
						break;
					}
					$itemData['upc'] = trim($itemData['upc']);
					if (empty($itemData['upc']) && !empty($itemData['gtin'])) {
						$itemData['upc'] = trim($itemData['gtin']);
					}
					if (empty($itemData['upc'])) {
						$productFailure = true;
						break;
					}
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc'], "product_id in (select product_id from products where inactive = 0 and internal_use_only = 0)");
					if (empty($productId)) {
						$productFailure = true;
						break;
					}
					$productRow = ProductCatalog::getCachedProductRow($productId);
					$productDataRow = getRowFromId("product_data", "product_id", $productId);
					$productIdList .= (empty($productIdList) ? "" : "|") . $productId;
					$thisItem = array("product_id" => $productId, "sale_price" => $thisItem['itemPrice'], "quantity" => $thisItem['quantity']);
					$productIdArray[] = $thisItem;
				}
				if ($productFailure) {
					continue;
				}

				$substitutions = array();
				$promotionCode = $substitutions['promotion_code'] = strtoupper(getRandomString(24));
				$resultSet = executeQuery("insert into promotions (client_id,promotion_code,description,start_date,maximum_usages) values (?,?,?,current_date,1)",
					$GLOBALS['gClientId'], $promotionCode, "GunBroker Sale");
				$promotionId = $resultSet['insert_id'];
				foreach ($productIdArray as $thisItem) {
					executeQuery("insert into promotion_rewards_products (promotion_id,product_id,maximum_quantity,amount) values (?,?,?,?)",
						$promotionId, $thisItem['product_id'], $thisItem['quantity'], $thisItem['sale_price']);
				}

				$domainName = getDomainName();
				$addToCartLink = $domainName . "/shopping-cart?product_id=" . $productIdList . "&promotion_code=" . $promotionCode;
				$emailAddress = $orderData['billToEmail'];
				$substitutions = array_merge($productRow, $productDataRow, array("promotion_code" => $promotionCode, "add_to_cart_link" => $addToCartLink, "first_name" => $userContactInfo['firstName'], "last_name" => $userContactInfo['lastName'],
					"email_address" => $emailAddress, "gunbroker_order_code" => $gunbrokerOrderCode));
				$emailId = getFieldFromId("email_id", "emails", "email_code", "GUNBROKER_CUSTOMER_CART", "inactive = 0");
				$subject = "Gunbroker purchase finalization";
				$body = "<p>Congratulations, %first_name%, for your purchase from Gunbroker!</p><p>Your purchase can be finalized by going to this link:</p><p><a href='%add_to_cart_link%'>%add_to_cart_link%</a></p><p>Shipment will take place shortly after completing the checkout process.</p>";
				sendEmail(array("email_address" => $emailAddress, "body" => $body, "subject" => $subject, "email_id" => $emailId, "substitutions" => $substitutions));
				$this->addResult("Email sent to " . $emailAddress . " for GunBroker Order ID " . $gunBrokerOrderId);
				$count++;
			}

			$this->addResult($count . " Emails sent for GunBroker orders for client " . $clientRow['client_code']);
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
