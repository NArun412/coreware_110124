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
		$this->iProcessCode = "import_distributor_products";
	}

	function process() {
		$doneLocations = array();

		$resultSet = executeQuery("select * from clients where inactive = 0");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			ProductDistributor::setPrimaryDistributorLocation();
		}
		$limits = getPreference("limit_product_import");
		$permittedProductDistributorCodes = explode(",", $limits);
		$limitClientId = array_shift($permittedProductDistributorCodes);
		if (!empty($limitClientId)) {
			$this->addResult("Limit to client ID " . $limitClientId);
		}
		if (!empty($permittedProductDistributorCodes)) {
			$this->addResult("Limit to Product Distributor Code(s) " . implode(",", $permittedProductDistributorCodes));
		}
		$clientListArray = array();
		$resultSet = executeQuery("select *,(select client_code from clients where client_id = locations.client_id) client_code from locations join location_credentials using (location_id) join product_distributors using (product_distributor_id) where " .
			"product_distributors.inactive = 0 and location_credentials.inactive = 0 and primary_location = 1 and locations.inactive = 0 and locations.user_location = 0 " .
			"and client_id in (select client_id from clients where clients.inactive = 0) order by date_last_run,client_code");
		while ($row = getNextRow($resultSet)) {
			if (!empty($limitClientId) && $row['client_id'] != $limitClientId) {
				continue;
			}
			if (!in_array($row['client_id'], $clientListArray)) {
				$clientListArray[] = $row['client_id'];
			}
		}

		$productDistributorStats = array();
		$catalogErrors = 0;

		foreach ($clientListArray as $thisClientId) {
			changeClient($thisClientId);
			$this->addResult("Processing locations for " . $GLOBALS['gClientName']);
			$resultSet = executeQuery("select * from locations join location_credentials using (location_id) join product_distributors using (product_distributor_id) where " .
				"product_distributors.inactive = 0 and location_credentials.inactive = 0 and primary_location = 1 and locations.inactive = 0 and locations.user_location = 0 " .
				"and client_id = ? order by product_distributors.sort_order,product_distributors.description", $thisClientId);
			while ($row = getNextRow($resultSet)) {
				$this->addResult("Product location '" . $row['description'] . "'");
				if (!empty($permittedProductDistributorCodes) && !in_array($row['product_distributor_code'], $permittedProductDistributorCodes)) {
					$this->addResult("Skipped");
					continue;
				}
				executeQuery("update location_credentials set date_last_run = now() where location_credential_id = ?", $row['location_credential_id']);
				$locationKey = $row['product_distributor_id'] . ":" . $row['client_id'];
				if (in_array($locationKey, $doneLocations)) {
					$this->addResult("Distributor already ran");
					continue;
				}
				$startTime = getMilliseconds();
				$productDistributor = ProductDistributor::getProductDistributorInstance($row['location_id']);
				if (!$productDistributor) {
					$this->addResult("Can't get product distributor '" . $row['description'] . "'");
					$this->iErrorsFound = true;
					continue;
				}
				$caughtError = false;
				$response = false;
				try {
					$response = $productDistributor->syncProducts();
				} catch (Exception $e) {
					$this->addResult($e->getMessage());
					$this->addResult("Error thrown attempting to sync products for product distributor '" . $row['description']);
					sendEmail(array("notification_code" => "DISTRIBUTOR_ERRORS", "subject" => "Unable to sync products for product distributor " . $row['description'],
						"body" => "An error occurred when syncing products from " . $row['description'] . ":\n\n" . $e->getMessage()));
					$this->iErrorsFound = true;
					$caughtError = true;
				}
				if (!$caughtError && $response === false) {
					$this->addResult($productDistributor->getErrorMessage());
					$this->addResult("Empty response attempting to sync products for product distributor '" . $row['description'] . "'");
					sendEmail(array("notification_code" => "DISTRIBUTOR_ERRORS", "subject" => "Unable to sync products for product distributor " . $row['description'],
						"body" => "An error occurred when syncing products from " . $row['description'] . ":\n\n" . $productDistributor->getErrorMessage()));
					if ($productDistributor->getErrorMessage() == "Unable to get product metadata") {
						$catalogErrors++;
					}
					$this->iErrorsFound = true;
				} else {
					$doneLocations[] = $locationKey;
				}
				$productDistributor = null;
				$endTime = getMilliseconds();
				$totalTime = getTimeElapsed($startTime, $endTime);
				$totalTimeMilliseconds = $endTime - $startTime;
				$this->addResult($response . " - '" . $row['description'] . "' taking " . $totalTime . ", memory: " . number_format(memory_get_peak_usage(), 0, "", ","));
				if (!array_key_exists($row['product_distributor_id'], $productDistributorStats)) {
					$productDistributorStats[$row['product_distributor_id']]['description'] = $row['description'];
					$productDistributorStats[$row['product_distributor_id']]['results'] = array();
					$productDistributorStats[$row['product_distributor_id']]['errors'] = 0;
				}
				if ($response === false) {
					$productDistributorStats[$row['product_distributor_id']]['errors']++;
				} else {
					$productDistributorStats[$row['product_distributor_id']]['results'][] = $totalTimeMilliseconds;
				}
			}
			if (getPreference("FLP_PARTNER_ID")) {
				$insertCount = FirearmsLegalProtection::addRelatedProducts();
				if ($insertCount > 0) {
					$this->addResult("FLP added as a related product for " . $insertCount . " FFL-required products");
				}
			}
		}
		foreach ($productDistributorStats as $thisProductDistributorStat) {
			$average = "n/a";
			$thisResultArray = array_filter($thisProductDistributorStat['results']);
			if (count($thisResultArray)) {
				$average = array_sum($thisResultArray) / count($thisResultArray);
			}
			$this->addResult(sprintf("Average time taken for %s: %s [%s error(s)]", $thisProductDistributorStat['description'],
				(is_numeric($average) ? getTimeElapsed(0, $average) : $average), $thisProductDistributorStat['errors']));
		}
		if ($catalogErrors > 0) {
            $emailText = "<html lang=\"en\">\n<body>\n<p>Import distributor products returned 'Unable to get product metadata' " . $catalogErrors . " time(s) on server "
				. getPreference("SYSTEM_NAME") . ".</p></body></html>";
            sendErrorLogEmail(array("subject" => "Catalog access error", "body" => $emailText, "send_immediately" => true, "primary_client" => true));
		}

		# deal with discontinued products

		$resultSet = executeQuery("select * from clients where inactive = 0");
		while ($row = getNextRow($resultSet)) {
			if ($row['client_code'] == "COREWARE_SHOOTING_SPORTS" || ($row['client_code'] == "CORE" && $GLOBALS['gDevelopmentServer'])) {
				changeClient($row['client_id']);
				executeQuery("update products set version = 1");
				executeQuery("update products set version = 1062020 where client_id = ? and date_created < date_sub(current_date,interval 180 day) and " .
					"product_id not in (select product_id from distributor_product_codes) and product_id not in (select product_id from product_inventories) and " .
					"product_id not in (select product_id from order_items) and product_id not in (select product_id from product_custom_fields)",$row['client_id']);
				executeQuery("delete from product_category_links where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from distributor_order_items where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_facet_values where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_tag_links where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_images where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from related_products where product_id in (select product_id from products where version = 1062020) or associated_product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from shopping_cart_items where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from wish_list_items where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_data where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_distributor_dropship_prohibitions where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_remote_images where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_search_word_values where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_prices where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_restrictions where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_payment_methods where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from product_view_log where product_id in (select product_id from products where version = 1062020)");
				executeQuery("delete from potential_product_duplicates where product_id in (select product_id from products where version = 1062020) or duplicate_product_id in (select product_id from products where version = 1062020)");
				$deleteSet = executeQuery("delete from products where version = 1062020");
				if (!empty($deleteSet['sql_error'])) {
					$this->addResult("Unable to delete discontinued products: " . $deleteSet['sql_error']);
				}
			}
		}
		executeQuery("update preferences set system_value = null where preference_code in ('LIMIT_PRODUCT_IMPORT')");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
