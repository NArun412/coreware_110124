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
$GLOBALS['gDontReloadUsersContacts'] = true;

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class SyncRetailStoreBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "sync_retail_store";
	}

	function process() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "SYNC_PRODUCT_MANUFACTURERS");
		$productManufacturers = array();
		$mapPolicies = array();
		$resultSet = executeQuery("select * from client_preferences where preference_id = ? and preference_value = 'true'", $preferenceId);
		if ($resultSet['row_count'] > 0) {
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
			curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout'] * 10);
			$response = curl_exec($ch);
			curl_close($ch);
			$productManufacturers = json_decode($response, true);

			$mapSet = executeQuery("select * from map_policies");
			while ($mapRow = getNextRow($mapSet)) {
				$mapPolicies[$mapRow['map_policy_code']] = $mapRow['map_policy_id'];
			}
		}
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$neverAddManufacturers = !empty(getPreference("NEVER_ADD_MANUFACTURERS"));
			$this->addResult("Syncing Product Manufacturers for " . $GLOBALS['gClientName']);
			$insertCount = 0;
			$updateCount = 0;
			foreach ($productManufacturers['product_manufacturers'] as $thisManufacturer) {
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $thisManufacturer['product_manufacturer_code']);
				$imageUrl = false;
				if (!empty($thisManufacturer['image_id'])) {
					if (empty($thisManufacturer['filename'])) {
						$thisManufacturer['filename'] = "manufacturer_logo_" . $thisManufacturer['image_id'];
					}
					if (empty($thisManufacturer['extension'])) {
						$thisManufacturer['extension'] = "jpg";
					}
					$imageUrl = "https://shootingsports.coreware.com/getimage.php?id=" . $thisManufacturer['image_id'];
				}
				if (empty($productManufacturerId)) {
					if (!$neverAddManufacturers) {
						$imageId = "";
						if (!empty($imageUrl)) {
							$imageContents = "";
							if (urlExists($imageUrl)) {
								$imageContents = file_get_contents($imageUrl);
							}
							if (!empty($imageContents)) {
								$imageId = createImage(array("extension" => $thisManufacturer['extension'], "file_content" => $imageContents, "name" => $thisManufacturer['filename'], "description" => $thisManufacturer['description'], "image_code" => "MANUFACTURER_LOGO_" . getRandomString(6)));
							}
						}

						$contactDataTable = new DataTable("contacts");
						$contactId = $contactDataTable->saveRecord(array("name_values" => array("title" => $thisManufacturer['title'], "first_name" => $thisManufacturer['first_name'],
							"middle_name" => $thisManufacturer['middle_name'], "last_name" => $thisManufacturer['last_name'], "suffix" => $thisManufacturer['suffix'],
							"preferred_first_name" => $thisManufacturer['preferred_first_name'], "alternate_name" => $thisManufacturer['alternate_name'],
							"business_name" => $thisManufacturer['business_name'], "job_title" => $thisManufacturer['job_title'], "salutation" => $thisManufacturer['salutation'],
							"address_1" => $thisManufacturer['address_1'], "address_2" => $thisManufacturer['address_2'], "city" => $thisManufacturer['city'], "state" => $thisManufacturer['state'],
							"postal_code" => $thisManufacturer['postal_code'], "attention_line" => $thisManufacturer['attention_line'], "email_address" => $thisManufacturer['email_address'],
							"web_page" => $thisManufacturer['web_page'], "image_id" => $imageId, "country_id" => $thisManufacturer['country_id'])));

						$mapPolicyId = $mapPolicies[$thisManufacturer['map_policy_code']];
						if (!empty($thisManufacturer['map_policy_code']) && empty($mapPolicyId)) {
							$mapPolicyId = $mapPolicies['MAP_MINIMUM'];
						}
						$insertSet = executeQuery("insert into product_manufacturers (client_id,product_manufacturer_code,description,contact_id,link_name,map_policy_id,percentage,cannot_dropship) values (?,?,?,?,?, ?,?,?)",
							$GLOBALS['gClientId'], $thisManufacturer['product_manufacturer_code'], $thisManufacturer['description'], $contactId, $thisManufacturer['link_name'], $mapPolicyId,
							$thisManufacturer['percentage'], $thisManufacturer['cannot_dropship']);
						$productManufacturerId = $insertSet['insert_id'];

						foreach ($thisManufacturer['distributors'] as $productDistributorCode) {
							$productDistributorId = getFieldFromId("product_distributor_id", "product_distributors", "product_distributor_code", $productDistributorCode);
							if (!empty($productDistributorId)) {
								executeQuery("insert ignore into product_manufacturer_distributor_dropships (product_manufacturer_id,product_distributor_id) values (?,?)", $productManufacturerId, $productDistributorId);
							}
						}
						foreach ($thisManufacturer['departments'] as $productDepartmentCode) {
							$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
							if (!empty($productDepartmentId)) {
								executeQuery("insert ignore into product_manufacturer_dropship_exclusions (product_manufacturer_id,product_department_id) values (?,?)", $productManufacturerId, $productDepartmentId);
							}
						}
						$insertCount++;
					}
				} else {
					$contactId = getFieldFromId("contact_id", "product_manufacturers", "product_manufacturer_id", $productManufacturerId);
					if (!empty($imageUrl)) {
						$imageId = getFieldFromId("image_id", "contacts", "contact_id", $contactId);
						if (empty($imageId)) {
							$imageContents = "";
							if (urlExists($imageUrl)) {
								$imageContents = file_get_contents($imageUrl);
							}
							if (!empty($imageContents)) {
								$imageId = createImage(array("extension" => $thisManufacturer['extension'], "file_content" => $imageContents, "name" => $thisManufacturer['filename'], "description" => $thisManufacturer['description'], "image_code" => "MANUFACTURER_LOGO_" . getRandomString(6)));
							}
							if (!empty($imageId)) {
								executeQuery("update contacts set image_id = ? where contact_id = ? and image_id is null", $imageId, $contactId);
							}
						}
					}

					$updateCount++;
				}
			}
			$this->addResult($insertCount . " Manufacturer records inserted, " . $updateCount . " Manufacturer records updated");
			$this->addResult("Product Manufacturers synced for client " . $GLOBALS['gClientName']);
		}
		executeQuery("delete from client_preferences where preference_id = ?", $preferenceId);

		// Check system / default client value
		$centralizedFFLStorage = getPreference("CENTRALIZED_FFL_STORAGE");
		$neverSyncFFL = getPreference("NEVER_SYNC_FFL");
		if (empty($neverSyncFFL)) {
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "SYNC_FEDERAL_FIREARMS_LICENSEES");
			$neverSyncPreferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "NEVER_SYNC_FFL");
			$resultSet = executeQuery("select * from client_preferences where (preference_id = ? and preference_value = 'true') and client_id in (select client_id from clients where inactive = 0) ", $preferenceId);
			if ($resultSet['row_count'] == 0) {
				$resultSet = executeQuery("select * from clients where inactive = 0 and client_id in (select client_id from product_tags where product_tag_code = 'FFL_REQUIRED' and inactive = 0 and cannot_sell = 0) " .
					" and client_id not in (select client_id from client_preferences where preference_id = ? and preference_value in (1,'true'))", $neverSyncPreferenceId);
				$this->addResult("Updating FFL Dealers for all " . $resultSet['row_count'] . " client(s)");
			} else {
				$this->addResult("Updating FFL Dealers for " . $resultSet['row_count'] . " specific client(s)");
			}
			$fflDealers = array();
			if ($resultSet['row_count'] > 0) {
				$startTime = getMilliseconds();
				$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
				$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_ffl_records";
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
				curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout'] * 10);
				$response = curl_exec($ch);
				echo (curl_error($ch)) . "<br>";
				curl_close($ch);
				$fflDealers = json_decode($response, true);
				$this->addResult((is_array($fflDealers['federal_firearms_licensees']) ? count($fflDealers['federal_firearms_licensees']) : 0) . " FFL Dealers found in catalog. Elapsed time: " . getTimeElapsed($startTime, getMilliseconds()));
			}

			$GLOBALS['gSkipCorestoreContactUpdate'] = true;
			while ($row = getNextRow($resultSet)) {
				if ($centralizedFFLStorage && $row['client_id'] != $GLOBALS['gDefaultClientId']) {
					$this->addResult("FFL Dealers NOT synced for client " . $GLOBALS['gClientName'] . " because FFLs are centralized");
					continue;
				}
				$startTime = getMilliseconds();
				changeClient($row['client_id']);
				$this->addResult("Syncing FFL Dealers for " . $GLOBALS['gClientName']);
				$existingFFLs = array();
				$fflSet = executeQuery("select license_number from federal_firearms_licensees where client_id = ?",$GLOBALS['gClientId']);
				while ($fflRow = getNextRow($fflSet)) {
					$existingFFLs[$fflRow['license_number']] = true;
				}
				$insertCount = 0;
				$updateCount = 0;
				$foundCount = 0;
				foreach ($fflDealers['federal_firearms_licensees'] as $thisDealerData) {
					$thisDealer = array();
					foreach ($fflDealers['field_names'] as $index => $fieldName) {
						$thisDealer[$fieldName] = $thisDealerData[$index];
					}
					if (empty($thisDealer['license_number']) || empty($thisDealer['license_lookup'])) {
						continue;
					}
					if (array_key_exists($thisDealer['license_number'],$existingFFLs)) {
						continue;
					}
					$GLOBALS['gPrimaryDatabase']->startTransaction();

					$federalFirearmsLicenseeId = getFieldFromId("federal_firearms_licensee_id", "federal_firearms_licensees", "license_lookup", $thisDealer['license_lookup']);
					if (empty($federalFirearmsLicenseeId)) {
						$federalFirearmsLicenseeId = getFieldFromId("federal_firearms_licensee_id", "federal_firearms_licensees", "license_number", $thisDealer['license_number']);
					}
					if (empty($federalFirearmsLicenseeId)) {
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("title" => $thisDealer['title'], "first_name" => $thisDealer['first_name'],
							"middle_name" => $thisDealer['middle_name'], "last_name" => $thisDealer['last_name'], "suffix" => $thisDealer['suffix'],
							"preferred_first_name" => $thisDealer['preferred_first_name'], "alternate_name" => $thisDealer['alternate_name'],
							"business_name" => $thisDealer['business_name'], "job_title" => $thisDealer['job_title'], "salutation" => $thisDealer['salutation'],
							"address_1" => $thisDealer['address_1'], "address_2" => $thisDealer['address_2'], "city" => $thisDealer['city'], "state" => $thisDealer['state'],
							"postal_code" => $thisDealer['postal_code'], "latitude" => $thisDealer['latitude'], "longitude" => $thisDealer['longitude'],
							"attention_line" => $thisDealer['attention_line'], "email_address" => $thisDealer['email_address'],
							"web_page" => $thisDealer['web_page'], "country_id" => $thisDealer['country_id'])))) {
							$this->addResult($contactDataTable->getErrorMessage());
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							continue;
						}
						$insertSet = executeQuery("insert into federal_firearms_licensees (client_id,license_lookup,license_number,licensee_name,contact_id,expiration_date,mailing_address_preferred,preferred,inactive) values (?,?,?,?,?, ?,?,?,?)",
							$GLOBALS['gClientId'], $thisDealer['license_lookup'], $thisDealer['license_number'], $thisDealer['licensee_name'], $contactId, $thisDealer['expiration_date'], $thisDealer['mailing_address_preferred'],
							$thisDealer['preferred'], $thisDealer['inactive']);
						if (!empty($insertSet['sql_error'])) {
							$this->addResult($insertSet['sql_error']);
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							continue;
						}
						$insertCount++;
					} else {
						$fflRow = getRowFromId("federal_firearms_licensees", "federal_firearms_licensee_id", $federalFirearmsLicenseeId);
						$updateFields = array('license_number', 'license_lookup', 'licensee_name', 'expiration_date', 'preferred', 'inactive');
						$updated = false;
						$somethingToUpdate = false;
						foreach ($updateFields as $thisFieldName) {
							if ($fflRow[$thisFieldName] != $thisDealer[$thisFieldName]) {
								$somethingToUpdate = true;
								break;
							}
						}
						if ($somethingToUpdate) {
							executeQuery("update federal_firearms_licensees set license_number = ?, license_lookup = ?, licensee_name = ?, expiration_date = ? where federal_firearms_licensee_id = ?",
								$thisDealer['license_number'], $thisDealer['license_lookup'], $thisDealer['licensee_name'], $thisDealer['expiration_date'], $federalFirearmsLicenseeId);
							if (!empty($thisDealer['preferred'])) {
								executeQuery("update federal_firearms_licensees set preferred = 1 where federal_firearms_licensee_id = ?", $federalFirearmsLicenseeId);
							}
							if (!empty($thisDealer['inactive'])) {
								executeQuery("update federal_firearms_licensees set inactive = 1 where federal_firearms_licensee_id = ?", $federalFirearmsLicenseeId);
							}
							$updated = true;
						}
						$contactId = $fflRow['contact_id'];
						$contactRow = Contact::getContact($fflRow['contact_id']);
						$updateFields = array("business_name", "address_1", "city", "state", "postal_code", "latitude", "longitude");
						$somethingToUpdate = false;
						foreach ($updateFields as $thisFieldName) {
							if ($contactRow[$thisFieldName] != $thisDealer[$thisFieldName]) {
								$somethingToUpdate = true;
								break;
							}
						}
						if ($somethingToUpdate) {
							$dataTable = new DataTable("contacts");
							$dataTable->setSaveOnlyPresent(true);
							$nameValues = array("business_name" => $thisDealer['business_name'], "address_1" => $thisDealer['address_1'], "city" => $thisDealer['city'], "state" => $thisDealer['state'], "postal_code" => $thisDealer['postal_code']);
							if (!empty($thisDealer['latitude'])) {
								$nameValues['latitude'] = $thisDealer['latitude'];
							}
							if (!empty($thisDealer['longitude'])) {
								$nameValues['longitude'] = $thisDealer['longitude'];
							}
							$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $contactId));
							$updated = true;
						}
						if (!empty($thisDealer['mailing_address_1']) || !empty($thisDealer['mailing_city'])) {
							$updateFields = array("address_1", "city", "state", "postal_code");
							$somethingToUpdate = false;
							$addressRow = getRowFromId("addresses", "address_label", "Mailing", "contact_id = ?", $contactId);
							foreach ($updateFields as $thisFieldName) {
								if ($addressRow[$thisFieldName] != $thisDealer['mailing_' . $thisFieldName]) {
									$somethingToUpdate = true;
									break;
								}
							}
							if ($somethingToUpdate) {
								$dataTable = new DataTable("addresses");
								$addressId = getFieldFromId("address_id", "addresses", "address_label", "Mailing", "contact_id = ?", $contactId);
								$dataTable->setPrimaryId($addressId);
								$dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "address_label" => "Mailing",
									"address_1" => $thisDealer['mailing_address_1'], "city" => $thisDealer['mailing_city'], "state" => $thisDealer['mailing_state'], "postal_code" => $thisDealer['mailing_postal_code'], "country_id" => "1000")));
								$updated = true;
							}
						}
						if ($updated) {
							$updateCount++;
						} else {
							$foundCount++;
						}
					}
					if (!empty($contactId) && !empty($thisDealer['phone_number'])) {
						$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $thisDealer['phone_number']);
						if (empty($phoneNumberId)) {
							executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Store')", $contactId, $thisDealer['phone_number']);
						}
					}
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				$this->addResult($insertCount . " FFL records inserted, " . $updateCount . " FFL records updated, "
					. $foundCount . " existing FFL records unchanged taking " . getTimeElapsed($startTime, getMilliseconds()));
				$purgeCount = 0;
				$purgeSet = executeQuery("select * from federal_firearms_licensees where (license_number like '_-__-___-03%' or license_number like '_-__-___-06%' or expiration_date < date_sub(current_date,interval 45 day)) and " .
					"federal_firearms_licensee_id not in (select federal_firearms_licensee_id from orders where federal_firearms_licensee_id is not null) and client_id = ?", $GLOBALS['gClientId']);
				$federalFirearmsLicensees = array();
				while ($purgeRow = getNextRow($purgeSet)) {
					$federalFirearmsLicensees[] = $purgeRow;
				}
				foreach ($federalFirearmsLicensees as $federalFirearmsLicenseeInfo) {
					$GLOBALS['gPrimaryDatabase']->startTransaction();
					$deleteSet = executeQuery("delete from federal_firearms_licensees where federal_firearms_licensee_id = ?", $federalFirearmsLicenseeInfo['federal_firearms_licensee_id']);
					if (!empty($deleteSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					$deleteSet = executeQuery("delete from phone_numbers where contact_id = ?", $federalFirearmsLicenseeInfo['contact_id']);
					if (!empty($deleteSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					$deleteSet = executeQuery("delete from addresses where contact_id = ?", $federalFirearmsLicenseeInfo['contact_id']);
					if (!empty($deleteSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					$deleteSet = executeQuery("delete from contacts where contact_id = ?", $federalFirearmsLicenseeInfo['contact_id']);
					if (!empty($deleteSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						continue;
					}
					if (!empty($federalFirearmsLicenseeInfo['file_id'])) {
						executeQuery("delete ignore from files where file_id = ?", $federalFirearmsLicenseeInfo['file_id']);
					}
					if (!empty($federalFirearmsLicenseeInfo['sot_file_id'])) {
						executeQuery("delete ignore from files where file_id = ?", $federalFirearmsLicenseeInfo['sot_file_id']);
					}
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
					$purgeCount++;
				}
				if ($purgeCount > 0) {
					$this->addResult($purgeCount . " Expired or unusable FFL dealers purged.");
				}
				$this->addResult("FFL Dealers synced for client " . $GLOBALS['gClientName'] . ". Time elapsed: " . getTimeElapsed($startTime, getMilliseconds()));
			}
			executeQuery("delete from client_preferences where preference_id = ?", $preferenceId);
		} else {
			$this->addResult("FFL sync skipped because Never Sync FFLs preference is set.");
		}
	}
}

$backgroundProcess = new SyncRetailStoreBackgroundProcess();
$backgroundProcess->startProcess();
