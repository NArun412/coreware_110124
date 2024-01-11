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
		$this->iProcessCode = "create_gunbroker_orders";
	}

	function process() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "GUNBROKER_AUTO_CREATE_ORDERS");
		if (empty($preferenceId)) {
			$this->addResult("GUNBROKER_AUTO_CREATE_ORDERS Preference doesn't exist");
			$this->iErrorsFound = true;
			return;
		}
		$clientSet = executeQuery("select * from clients where inactive = 0 and client_id in (select client_id from client_preferences where preference_value is not null and preference_id = ?)", $preferenceId);
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);

			$unpaidAlso = getPreference("GUNBROKER_CREATE_UNPAID_ORDERS");
			$createCount = 0;

			try {
				$gunBroker = new GunBroker();
			} catch (Exception $e) {
				$this->addResult("Unable to get Gunbroker Object for client ". $clientRow['client_code'] . ": " . $e->getMessage());
				$this->iErrorsFound = true;
				continue;
			}

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
			for ($pageNumber = 1; $pageNumber <= 10; $pageNumber++) {

				# get only ordered placed in the last 24 hour. Background process should therefore be run many times a day

				$filterArray = array("PageSize" => 300, "PageIndex" => $pageNumber, "OrderStatus" => "0", "TimeFrame" => "3");

				$orders = $gunBroker->getOrders($filterArray);
				$errorMessage = $gunBroker->getErrorMessage();
				if (!empty($errorMessage)) {
					$this->addResult($errorMessage);
				}
				if (!is_array($orders) || count($orders) == 0) {
					break;
				}
				foreach ($orders as $thisOrder) {
					$itemsWithoutUpcFound = false;
					foreach ($thisOrder['orderItemsCollection'] as $thisItem) {
						$itemData = $gunBroker->getItemData($thisItem['itemID']);
						$itemData['upc'] = trim($itemData['upc']);
                        if(empty($itemData['upc']) && !empty($itemData['gtin'])) {
                            $itemData['upc'] = trim($itemData['gtin']);
                        }
						if (empty($itemData['upc'])) {
							$itemsWithoutUpcFound = true;
							$productId = "";
						} else {
							$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['upc']);
							if (empty($productId)) {
								$productId = getFieldFromId("product_id", "product_data", "upc_code", $itemData['sku']);
							}
							if (empty($productId)) {
								$productId = getFieldFromId("product_id", "product_data", "manufacturer_sku", $itemData['sku']);
							}
						}
						if (empty($productId)) {
							$itemsWithoutUpcFound = true;
						}
					}

					$existingOrderId = getFieldFromId("order_id", "orders", "source_id", $sourceId, "order_time > date_sub(?,interval 1 month) and purchase_order_number = ?", date("Y-m-d", strtotime($thisOrder['orderDate'])), $thisOrder['orderID']);
					if (empty($existingOrderId)) {
						$existingOrderId = getFieldFromId("order_id", "orders", "source_id", $taxCollectedSourceId, "order_time > date_sub(?,interval 1 month) and purchase_order_number = ?", date("Y-m-d", strtotime($thisOrder['orderDate'])), $thisOrder['orderID']);
					}
					if (empty($existingOrderId)) {
						if (!$thisOrder['orderCancelled'] && ($thisOrder['paymentReceived'] || $unpaidAlso)) {
							if ($itemsWithoutUpcFound) {
								$this->addResult("Gunbroker order " . $thisOrder['orderID'] . " cannot be created because an item with no UPC exists");
							} else {
								$orderId = Order::createGunBrokerOrder($thisOrder['orderID']);
								if (is_numeric($orderId)) {
									$createCount++;
								} else {
									$this->addResult("Error for Gunbroker order " . $thisOrder['orderID'] . ": " . $orderId);
								}
							}
						}
					} else { // existing order, check for changes
                        // Check for FFL change
                        $orderRow = getRowFromId("orders", "order_id", $existingOrderId);
                        $gunBrokerOrderFFL = $thisOrder['fflNumber'];
                        if(!empty($gunBrokerOrderFFL)) {
	                        $existingFflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
                            $gunBrokerOrderFFLLookup = str_replace("XXX-XX-XX-", "", $gunBrokerOrderFFL);
                            if($existingFflRow['license_lookup'] != $gunBrokerOrderFFLLookup) {
	                            $newFflId = (new FFL(array("license_lookup"=>$gunBrokerOrderFFLLookup)))->getFieldData("federal_firearms_licensee_id");
                                $orderShipmentRow = getRowFromId("order_shipments", "order_id", $existingOrderId);
                                if(empty($orderShipmentRow)) {
                                    updateFieldById("federal_firearms_licensee_id", $newFflId, "orders", "order_id", $existingOrderId);
                                    if (empty($newFflId)) {
                                        $resultLine = sprintf("Buyer changed FFL for GunBroker order %s to '%s' but no matching FFL found; FFL removed from order ID %s",
                                            $thisOrder['orderID'], $gunBrokerOrderFFL, $existingOrderId);
                                    } else {
                                        $resultLine = sprintf("Buyer changed FFL for GunBroker order %s to '%s'; order %s updated with FFL ID %s",
                                            $thisOrder['orderID'], $gunBrokerOrderFFL, $existingOrderId, $newFflId);
                                    }
                                } else {
                                    $resultLine = sprintf("Buyer changed FFL for GunBroker order %s (order ID %s) to '%s' but order already has a shipment; no changes made.",
                                        $thisOrder['orderID'], $existingOrderId, $gunBrokerOrderFFL);
                                }
                                sendEmail(array("subject" => "GunBroker Order FFL changed", "body" => $resultLine, "notification_code" => "GUNBROKER_ORDERS"));
                                $this->addResult($resultLine);
                            }
                        }
                    }
				}
			}
			$this->addResult($createCount . " orders created for " . $clientRow['client_code']);
			sendEmail(array("subject"=>"Gunbroker Orders","body"=>$this->iResults,"notification_code"=>"GUNBROKER_ORDERS"));
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
