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
		$this->iProcessCode = "update_distributor_inventory_quantities";
	}

	function process() {
		$locationArray = array();
		$distributorsFound = array();

		if ($GLOBALS['gClientCount'] < 5) {
			$this->addResult("No locations processed because client count less than 5");
			return;
		}

		$cannotSellRestrictions = array();
		$resultSet = executeQuery("select client_id,product_manufacturer_cannot_sell_distributors.product_distributor_id from product_manufacturer_cannot_sell_distributors join product_manufacturers using (product_manufacturer_id) union " .
			"select client_id,product_department_cannot_sell_distributors.product_distributor_id from product_department_cannot_sell_distributors join product_departments using (product_department_id) union " .
			"select client_id,product_category_cannot_sell_distributors.product_distributor_id from product_category_cannot_sell_distributors join product_categories using (product_category_id)");
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['client_id'],$cannotSellRestrictions)) {
				$cannotSellRestrictions[$row['client_id']] = array();
			}
			$cannotSellRestrictions[$row['client_id']][$row['product_distributor_id']] = $row['product_distributor_id'];
		}

        $skipCount = array();
		$resultSet = executeQuery("select *,locations.description as location_description,(select client_code from clients where locations.client_id = clients.client_id) client_code from locations " .
            "join location_credentials using (location_id) join product_distributors using (product_distributor_id) where " .
			"product_distributors.inactive = 0 and location_credentials.inactive = 0 and locations.inactive = 0 and client_id in (select client_id from clients where clients.inactive = 0) " .
			"order by locations.client_id,primary_location desc,location_id");
		while ($row = getNextRow($resultSet)) {
            if(!empty($row['has_allocated_inventory'])) {
                $this->addResult("Client " . $row['client_code'] ." Location '" . $row['location_description'] . "' skipped because of allocated inventory.");
                $skipCount['allocated']++;
            } elseif(!empty($row['cost_threshold'])) {
                $this->addResult("Client " . $row['client_code'] ." Location '" . $row['location_description'] . "' skipped because a cost threshold is set.");
                $skipCount['cost_threshold']++;
            } elseif(!empty($row['out_of_stock_threshold'])) {
                $this->addResult("Client " . $row['client_code'] ." Location '" . $row['location_description'] . "' skipped because an out of stock threshold is set.");
                $skipCount['stock_threshold']++;
            } elseif(array_key_exists($row['client_id'],$cannotSellRestrictions)) {
                $this->addResult("Client " . $row['client_code'] ." Location '" . $row['location_description'] . "' skipped because cannot sell restrictions are set.");
                $skipCount['cannot_sell']++;
            } elseif(is_array($cannotSellRestrictions[$row['client_id']]) && array_key_exists($row['product_distributor_id'],$cannotSellRestrictions[$row['client_id']])) {
                $this->addResult("Client " . $row['client_code'] ." Location '" . $row['location_description'] . "' skipped because cannot sell restrictions are set for this distributor.");
                $skipCount['cannot_sell_distributor']++;
            } else {
                $locationArray[] = $row;
			}
		}
		foreach ($locationArray as $row) {
			if (array_key_exists($row['product_distributor_id'],$distributorsFound)) {
				continue;
			}
			$startTime = getMilliseconds();
			changeClient($row['client_id']);

			$productDistributor = ProductDistributor::getProductDistributorInstance($row['location_id']);
			if (!$productDistributor) {
				$errorMessage = "ERROR: Can't get location '" . $row['location_description'] . "', product distributor '" . $row['description'] . "' for client " . $GLOBALS['gClientName'];
				$this->addResult($errorMessage);
				$this->iErrorsFound = true;
				continue;
			}
			$response = $productDistributor->syncInventory(array("all_clients"=>true));
			if ($response === false) {
				$errorMessage = "ERROR: location '" . $row['location_description'] . "', skipped";
				$this->addResult($errorMessage);
				$this->iErrorsFound = true;
			} else {
				$productDistributor = null;
				$endTime = getMilliseconds();
				$totalTime = getTimeElapsed($startTime, $endTime);
				$totalTimeMilliseconds = $endTime - $startTime;
				$this->addResult($response . " - '" . $row['description'] . "' taking " . $totalTime);
				$distributorsFound[$row['product_distributor_id']] = $row['product_distributor_id'];
			}
		}
        $this->addResult($skipCount['allocated'] . " Locations skipped due to allocated inventory.");
        $this->addResult($skipCount['cost_threshold'] . " Locations skipped due to cost thresholds.");
        $this->addResult($skipCount['stock_threshold'] . " Locations skipped due to out of stock thresholds.");
        $this->addResult($skipCount['cannot_sell'] . " Locations skipped due to cannot sell restrictions.");
        $this->addResult($skipCount['cannot_sell_distributor'] . " Locations skipped due to cannot sell restrictions on the distributor.");
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
