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
require_once __DIR__ . "/../shared/startup.inc";

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "update_distributor_inventory";
	}

	function process() {
		$GLOBALS['gForceCostUpdate'] = getPreference("FORCE_COST_UPDATE");
		$limits = getPreference("limit_inventory_update");
		$permittedProductDistributorCodes = explode(",", $limits);
		$limitClientId = array_shift($permittedProductDistributorCodes);
		if (!empty($limitClientId)) {
			$this->addResult("Limit to client ID " . $limitClientId);
		}
		if (!empty($permittedProductDistributorCodes)) {
			$this->addResult("Limit to Product Distributor Code(s) " . implode(",", $permittedProductDistributorCodes));
		}

		$locationArray = array();
		$distributorsFound = array();
        $productDistributorStats = array();

		$backgroundProcessId = getFieldFromId("background_process_id","background_processes","background_process_code","update_distributor_inventory_quantities", "inactive = 0");
		$restrictedRun = ($GLOBALS['gClientCount'] > 8 && !empty($backgroundProcessId) && date("H") > 3 && date("H") < 20);
		$this->addResult($restrictedRun ? "RESTRICTED RUN: " . $this->iBackgroundProcessRow['date_last_run'] . " - " . date("Y-m-d H:i:s") . " - " . $this->iLastStartTime : "");

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

        $resultSet = executeQuery("select *,locations.description as location_description from locations join location_credentials using (location_id) join product_distributors using (product_distributor_id) where " .
			"product_distributors.inactive = 0 and location_credentials.inactive = 0 and locations.inactive = 0 and client_id in (select client_id from clients where clients.inactive = 0) " .
            "order by locations.client_id,primary_location desc,location_id");
		$skipCount = 0;
		while ($row = getNextRow($resultSet)) {
			if (!empty($limitClientId) && $row['client_id'] != $limitClientId) {
				continue;
			}
			if (!empty($permittedProductDistributorCodes) && !in_array($row['product_distributor_code'], $permittedProductDistributorCodes)) {
				continue;
			}
			if ($restrictedRun) {
				if (empty($row['has_allocated_inventory']) && empty($row['cost_threshold']) && empty($row['out_of_stock_threshold']) &&
					(!array_key_exists($row['client_id'],$cannotSellRestrictions) || !array_key_exists($row['product_distributor_id'],$cannotSellRestrictions[$row['client_id']]))) {
					$skipCount++;
					continue;
				}
			}
			$distributorFoundKey = $row['client_id'] . ":" . $row['product_distributor_id'];
			if (in_array($distributorFoundKey,$distributorsFound)) {
				if (!empty($row['primary_location'])) {
					executeQuery("update locations set primary_location = 0 where location_id = ?",$row['location_id']);
				}
				continue;
			}
			if (empty($row['primary_location'])) {
				executeQuery("update locations set primary_location = 1 where location_id = ?", $row['location_id']);
			}
			$distributorsFound[] = $distributorFoundKey;
			$locationArray[] = $row;
		}
		$this->addResult($skipCount . " locations skipped because they use the streamlined inventory");
		foreach ($locationArray as $row) {
			$this->addResult();
			$this->addResult("Processing location: " . $row['location_description']);
			$startTime = getMilliseconds();
			changeClient($row['client_id']);

			$productDistributor = ProductDistributor::getProductDistributorInstance($row['location_id']);
			if (!$productDistributor) {
				$errorMessage = "ERROR: Can't get location '" . $row['location_description'] . "', product distributor '" . $row['description'] . "' for client " . $GLOBALS['gClientName'];
				$this->sendNotification($errorMessage);
				$this->addResult($errorMessage);
				$this->iErrorsFound = true;
				continue;
			}
			$response = $productDistributor->syncInventory();
			if ($response === false) {
				$errorMessage = "ERROR: " . $productDistributor->getErrorMessage() . " for location '" . $row['location_description'] . "', product distributor '" . $row['description'] . "' for client " . $GLOBALS['gClientName'];
				$this->sendNotification($errorMessage);
				$this->addResult($errorMessage);
				$this->iErrorsFound = true;
			} else {
                $productDistributor = null;
                $endTime = getMilliseconds();
                $totalTime = getTimeElapsed($startTime, $endTime);
                $totalTimeMilliseconds = $endTime - $startTime;
                $this->addResult($response . " - '" . $row['description'] . "' (" . $row['location_description'] . ") for client " . $GLOBALS['gClientName'] . " taking " . $totalTime);
				executeQuery("update location_credentials set last_inventory_update = now() where location_id = ?",$row['location_id']);
            }
            if(!array_key_exists($row['product_distributor_id'], $productDistributorStats)) {
                $productDistributorStats[$row['product_distributor_id']]['description'] = $row['description'];
                $productDistributorStats[$row['product_distributor_id']]['results'] = array();
                $productDistributorStats[$row['product_distributor_id']]['errors'] = 0;
            }
            if($response === false) {
                $productDistributorStats[$row['product_distributor_id']]['errors']++;
            } else {
                $productDistributorStats[$row['product_distributor_id']]['results'][] = $totalTimeMilliseconds;
            }
        }
        $totalLocationsProcessed = 0;
        foreach($productDistributorStats as $thisProductDistributorStat) {
            $average = "n/a";
            $thisResultArray = array_filter($thisProductDistributorStat['results']);
            if(count($thisResultArray)) {
                $average = array_sum($thisResultArray)/count($thisResultArray);
                $totalLocationsProcessed += count($thisResultArray);
            }
            $this->addResult(sprintf("Average time taken for %s: %s [%s locations, %s error(s)]", $thisProductDistributorStat['description'],
                (is_numeric($average) ? getTimeElapsed(0,$average) : $average),count($thisResultArray), $thisProductDistributorStat['errors']));
        }
        $this->addResult("Total locations processed: $totalLocationsProcessed");
		executeQuery("update preferences set system_value = null where preference_code in ('FORCE_COST_UPDATE','LIMIT_INVENTORY_UPDATE')");
    }

	function sendNotification($errorMessage) {
		$emailAddresses = getNotificationEmails("INVENTORY_UPDATE_ERROR");
		$emailAddresses = array_merge($emailAddresses, getNotificationEmails("INVENTORY_UPDATE_ERROR", $GLOBALS['gDefaultClientId']));
		sendEmail(array("body" => $errorMessage, "subject" => "Inventory Update errors", "email_addresses" => $emailAddresses));
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
