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
		$this->iProcessCode = "update_map_prices";
	}

	function process() {
		$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_map_policies";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$responseArray = json_decode($response, true);
		$csscMapPolicies = $responseArray['map_policies'];

		$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_product_manufacturers";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		curl_close($ch);
		$productManufacturers = json_decode($response, true);

		$mapSet = executeQuery("select * from map_policies");
		while ($mapRow = getNextRow($mapSet)) {
			$mapPolicies[$mapRow['map_policy_code']] = $mapRow['map_policy_id'];
		}

		$productManufacturerMapPolicies = array();
		foreach ($productManufacturers['product_manufacturers'] as $thisManufacturer) {
			$productManufacturerMapPolicies[$thisManufacturer['product_manufacturer_code']] = $thisManufacturer['map_policy_code'];
		}
		$count = 0;
		$dataTable = new DataTable("product_manufacturers");
		$dataTable->setSaveOnlyPresent(true);
		$GLOBALS['gChangeLogNotes'] = "Change by Update Map Prices";
		$resultSet = executeQuery("select product_manufacturer_id,product_manufacturer_code,(select map_policy_code from map_policies where map_policy_id = product_manufacturers.map_policy_id) map_policy_code from product_manufacturers");
		while ($row = getNextRow($resultSet)) {
			if ($productManufacturerMapPolicies[$row['product_manufacturer_code']] == $row['map_policy_code']) {
				continue;
			}
			$newMapPolicyCode = false;
			foreach ($csscMapPolicies as $thisMapPolicy) {
				if ($thisMapPolicy['map_policy_code'] == $row['map_policy_code']) {
					break;
				}
				if ($thisMapPolicy['map_policy_code'] == $productManufacturerMapPolicies[$row['product_manufacturer_code']]) {
					$newMapPolicyCode = $thisMapPolicy['map_policy_code'];
					break;
				}
			}
			if (!empty($newMapPolicyCode)) {
				$dataTable->saveRecord(array("name_values"=>array("map_policy_id"=>$mapPolicies[$newMapPolicyCode]),"primary_id"=>$row['product_manufacturer_id']));
				$this->addResult($row['product_manufacturer_code'] . ": " . $newMapPolicyCode);
				$count++;
			}
		}
		$this->addResult($count . " MAP policies updated for manufacturers");
		$GLOBALS['gChangeLogNotes'] = "";

		$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_product_metadata&map_prices_only=true";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$rawProductMetadata = $response;
		$uncompressedProductMetadata = json_decode(gzdecode($rawProductMetadata), true);
		$productMetadata = array();
		foreach ($uncompressedProductMetadata['values'] as $index => $row) {
			$thisArray = array();
			foreach ($uncompressedProductMetadata['keys'] as $keyIndex => $thisField) {
				$thisArray[$thisField] = $row[$keyIndex];
			}
			$productMetadata[$index] = $thisArray;
		}

		$upcsToCheck = array();
		$upcResult = executeQuery("select product_data_id,upc_code,manufacturer_advertised_price from product_data where upc_code is not null and (map_expiration_date is null or map_expiration_date < current_date)");
		while ($upcRow = getNextRow($upcResult)) {
			if (!array_key_exists($upcRow['upc_code'], $productMetadata) || $productMetadata[$upcRow['upc_code']]['manufacturer_advertised_price'] == $upcRow['manufacturer_advertised_price']) {
				continue;
			}
			if (!array_key_exists($upcRow['upc_code'], $upcsToCheck)) {
				$upcsToCheck[$upcRow['upc_code']] = array();
			}
			$upcsToCheck[$upcRow['upc_code']][] = $upcRow['product_data_id'];
		}

		$csscClientId = getFieldFromId("client_id", "clients", "client_code", "COREWARE_SHOOTING_SPORTS");
		$count = 0;
		foreach ($upcsToCheck as $upcCode => $updateIds) {
			$productDataIds = "";
			foreach ($updateIds as $thisId) {
				$productDataIds .= (empty($productDataIds) ? "" : ",") . $thisId;
			}
			if (empty($productDataIds)) {
				continue;
			}
			executeQuery("update product_data set manufacturer_advertised_price = ? where product_data_id in (" . $productDataIds . ")", $productMetadata[$upcCode]['manufacturer_advertised_price']);
			$count++;
		}
		$this->addResult($count . " MAP prices updated");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
