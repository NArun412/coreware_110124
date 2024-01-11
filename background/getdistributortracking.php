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
		$this->iProcessCode = "distributor_tracking";
	}

	function process() {

		$distributorCount = array();
		$count = 0;
		$resultSet = executeQuery("select * from order_shipments join remote_orders using (remote_order_id) join locations using (location_id) where " .
			"locations.product_distributor_id is not null and shipping_carrier_id is null and carrier_description is null and tracking_identifier is null and " .
            "locations.client_id in (select client_id from clients where inactive = 0) and " .
			"order_shipments.order_id in (select order_id from orders where (date_completed is null or date_completed > date_sub(current_date,interval 30 day))) order by locations.client_id,locations.location_id");
		$this->addResult($resultSet['row_count'] . " shipments found");
		$productDistributor = false;
		$orderShipmentIds = array();
		while ($row = getNextRow($resultSet)) {
			if (changeClient($row['client_id'])) {
				$this->addResult("Processing for: " . $GLOBALS['gClientName']);
			}
			if (empty($productDistributor) || $row['location_id'] != $productDistributor->getLocation() || $GLOBALS['gClientId'] != $productDistributor->getClientId()) {
				$this->addResult("Getting Product Distributor for location: " . $row['location_id'] . ":" . $GLOBALS['gClientId'] . ":" . (empty($productDistributor) ? "false" : $productDistributor->getClientId()) . ":" . (empty($productDistributor) ? "false" : $productDistributor->getLocation()));
				$productDistributor = ProductDistributor::getProductDistributorInstance($row['location_id']);
			}
			if (!$productDistributor) {
				$this->addResult("No product distributor: " . $row['location_id']);
				$this->iErrorsFound = true;
				continue;
			}
			$logEntry = "Checking tracking for shipment ID " . $row['order_shipment_id'] . " from location " . $row['description'];
			if (!array_key_exists($row['product_distributor_id'],$distributorCount)) {
				$distributorCount[$row['product_distributor_id']] = 0;
			}
			$count++;
			$distributorCount[$row['product_distributor_id']]++;
			$returnValue = $productDistributor->getOrderTrackingData($row['order_shipment_id']);
			if ($returnValue !== false && is_array($returnValue)) {
				$orderShipmentIds = array_merge($orderShipmentIds,$returnValue);
				if(!empty($returnValue)) {
                    $logEntry .= ": Tracking Data found";
                }
			} else {
			    $errorMessage = $productDistributor->getErrorMessage();
			    if (!empty($errorMessage)) {
                    $this->addResult("Error for ID " . $row['order_shipment_id'] . " from " . $row['description'] . ": " . $productDistributor->getErrorMessage() . " - " . (is_array($returnValue) ? jsonEncode($returnValue) : $returnValue));
                }
			}
			$this->addResult($logEntry);
		}
		$this->addResult($count . " shipments processed");

		foreach ($distributorCount as $productDistributorId => $distributorCount) {
			$this->addResult($distributorCount . " shipments for " . getFieldFromId("description","product_distributors","product_distributor_id",$productDistributorId));
		}
		$orderIds = array();
		foreach ($orderShipmentIds as $orderShipmentId) {
			$orderIds[] = getFieldFromId("order_id","order_shipments","order_shipment_id",$orderShipmentId);
		}
		$this->addResult("Order IDs: " . implode(",",$orderIds));
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
