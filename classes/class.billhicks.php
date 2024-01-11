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

class BillHicks extends ProductDistributor {

	private $iConnection;
	private $iFieldTranslations = array("upc_code" => "universal_product_code", "product_code" => "product_name", "description" => "short_description", "detailed_description" => "long_description",
		"manufacturer_code" => "XXXXXX", "model" => "XXXXXX", "list_price" => "msrp", "manufacturer_sku" => "XXXXXX", "manufacturer_advertised_price" => "marp",
		"width" => "XXXXXX", "length" => "XXXXXX", "height" => "XXXXXX", "weight" => "product_weight", "base_cost" => "product_price");
	public $iTrackingAlreadyRun = false;
	private $iOriginalUserName;
	private $iOriginalPassword;

	function __construct($locationId) {
		$this->iProductDistributorCode = "BILLHICKS";
		parent::__construct($locationId);
		$this->iOriginalUserName = $this->iLocationCredentialRow['user_name'];
		$this->iOriginalPassword = $this->iLocationCredentialRow['password'];
		$this->iLocationCredentialRow['user_name'] = "bhc_coreware";
		$this->iLocationCredentialRow['password'] = 'H1ck$Mn19!';
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		$fieldLabels = array();
		$requiredCustomFieldCodeArray = array("FFL_LICENSE_NUMBER");
		foreach ($requiredCustomFieldCodeArray as $customFieldCode) {
			$this->iLocationCredentialRow["custom_field_" . strtolower($customFieldCode)] =
				CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], $customFieldCode, "PRODUCT_DISTRIBUTORS");
			$fieldLabels["custom_field_" . strtolower($customFieldCode)] = getFieldFromId("form_label", "custom_fields", "custom_field_code", $customFieldCode);
		}
		$ignoreFields = array("user_name", "password", "distributor_source", "date_last_run", "last_inventory_update", "inactive", "version");
		$labelSet = executeQuery("select * from product_distributor_field_labels where product_distributor_id = (select product_distributor_id from product_distributors where product_distributor_code = ?)", $this->iProductDistributorRow['product_distributor_code']);
		while ($labelRow = getNextRow($labelSet)) {
			$fieldLabels[$labelRow['column_name']] = array("form_label" => $labelRow['form_label'], "not_null" => $labelRow['not_null']);
		}
		foreach ($this->iLocationCredentialRow as $key => $value) {
			if (!in_array($key, $ignoreFields) && empty($value)) {
				$label = $fieldLabels[$key] ?: $key;
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . $label . " is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if (!$this->connect()) {
			$this->iErrorMessage = "Unable to connect to FTP server";
			return false;
		}
		$directoryWorks = false;
		$directoriesToTry = array(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS"), "Coreware");
		foreach ($directoriesToTry as $inventoryDirectory) {
			if (ftp_chdir($this->iConnection, $inventoryDirectory)) {
				$directoryWorks = true;
				CustomField::setCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY",
					$inventoryDirectory, "PRODUCT_DISTRIBUTORS");
			}
		}
		return $directoryWorks;
	}

	function connect() {
		$this->iConnection = ftp_connect("billhicksco.hostedftp.com", 21, 600);
		if (!$this->iConnection) {
			return false;
		}
		try {
			if (ftp_login($this->iConnection, $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'])) {
				ftp_pasv($this->iConnection, true);
				return true;
			}
		} catch (Exception $e) {
			$this->iErrorMessage = "Invalid Login: " . $this->iLocationCredentialRow['user_name'];
		}
		$this->iErrorMessage = "Invalid Login: " . $this->iLocationCredentialRow['user_name'];
		return false;
	}

	function syncProducts($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		if (!self::$iCorewareShootingSports) {
			if (empty($GLOBALS['coreware_product_metadata'])) {
				$this->getProductMetadata();
			}
			if (empty($GLOBALS['coreware_product_metadata'])) {
				$this->iErrorMessage = "Unable to get product metadata";
				return false;
			}
		}

		$directoryName = "Coreware"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FTP_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$feedDirectoryName = "Feeds"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FEED_DIRECTORY","PRODUCT_DISTRIBUTORS");
		if (!ftp_chdir($this->iConnection, $directoryName . "/" . $feedDirectoryName)) {
			$this->iErrorMessage = "Distributor directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		rsort($fileList);
		$catalogFile = "";
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("BHC_Catalog")) == "BHC_Catalog") {
				$catalogFile = $thisFile;
				break;
			}
		}
		if (empty($catalogFile)) {
			$this->iErrorMessage = "Catalog file not found";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, $catalogFile, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		try {
			if (ftp_chdir($this->iConnection, "../../BHC Digital Images ALL")) {
				$rawImageFileList = ftp_nlist($this->iConnection, ".");
				rsort($rawImageFileList);
			} else {
				$rawImageFileList = array();
			}
		} catch (Exception $e) {
			$rawImageFileList = array();
		}
		$imageFileList = array();
		foreach ($rawImageFileList as $imageUrl) {
			$imageFileList[$imageUrl] = true;
		}

		$insertCount = 0;

		self::loadValues('distributor_product_codes');
		$productIdsProcessed = array();

		$fflCategoryCodes = array("H606", "H607", "H600", "H602", "H603", "H601", "H608");

		$processCount = 0;
		$foundCount = 0;
		$updatedCount = 0;
		$imageCount = 0;
		$noUpc = 0;
		$duplicateProductCount = 0;
		$badImageCount = 0;

		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}

			if (empty($thisProductInfo[$this->iFieldTranslations['product_code']])) {
				continue;
			}

# find existing product ID

			$upcCode = ProductCatalog::makeValidUPC($thisProductInfo[$this->iFieldTranslations['upc_code']],array("only_valid_values"=>true));
			if (empty($upcCode)) {
				$noUpc++;
				continue;
			}

			$processCount++;

			$productId = ProductDistributor::$iLoadedValues['distributor_product_codes'][$this->iLocationRow['product_distributor_id']][$thisProductInfo[$this->iFieldTranslations['product_code']]]['product_id'];

			$upcProductId = $this->getProductFromUpc($upcCode);
			if (!empty($upcProductId)) {
				if (!empty($productId) && $upcProductId != $productId) {
					$body = "<p>UPC changed for " . $this->iProductDistributorRow['description'] . " product code: " . $thisProductInfo[$this->iFieldTranslations['product_code']] .
						"<br>Old UPC: " . getFieldFromId("upc_code", "product_data", "product_id", $productId) .
						"(" . getFieldFromId("description", "products", "product_id", $productId) . ")" .
						"<br>New UPC: " . $upcCode . "(" . getFieldFromId("description", "products", "product_id", $upcProductId) . ")" .
						"<p>This change may require manual confirmation to ensure that the distributor feed is correct.</p>";
					sendEmail(array("subject" => "Change in UPC code in distributor feed", "body" => $body, "notification_code" => "DISTRIBUTOR_UPC_CHANGE"));
				}
				$productId = $upcProductId;
			}

			$productManufacturerId = "";
			$corewareProductData = array();
			if (self::$iCorewareShootingSports) {
				$manufacturerCode = $thisProductInfo[$this->iFieldTranslations['manufacturer_code']];
				if (!empty($manufacturerCode)) {
					$productManufacturerId = $this->getManufacturer($manufacturerCode);
				}
			} else {
				if (is_array($GLOBALS['coreware_product_metadata']) && array_key_exists($upcCode, $GLOBALS['coreware_product_metadata'])) {
					$corewareProductData = $GLOBALS['coreware_product_metadata'][$upcCode];
				}
				$productManufacturerId = $this->getManufacturer($corewareProductData['product_manufacturer_code']);
			}
			$remoteIdentifier = $corewareProductData['product_id'];

			if (empty($productId) && !empty($productManufacturerId) && !empty($this->iFieldTranslations['manufacturer_sku']) && !empty($thisProductInfo[$this->iFieldTranslations['manufacturer_sku']])) {
				$newProductId = getFieldFromId("product_id", "products", "product_manufacturer_id", $productManufacturerId, "product_id in (select product_id from product_data where manufacturer_sku = ?)", $thisProductInfo[$this->iFieldTranslations['manufacturer_sku']]);
				if (!array_key_exists($newProductId, $productIdsProcessed)) {
					$productId = $newProductId;
				}
				if (!empty($productId)) {
					$newUpcCode = getFieldFromId("upc_code", "product_data", "product_id", $productId);
					if (!empty($newUpcCode)) {
						$upcCode = $newUpcCode;
					}
				}
			}

			if (!empty($productId) && array_key_exists($productId, $productIdsProcessed)) {
				$duplicateProductCount++;
				continue;
			}

			if (empty($productId)) {
				$productCode = makeCode($thisProductInfo[$this->iFieldTranslations['product_code']]);

				$description = $corewareProductData['description'];

				if (empty($description)) {
					$description = $thisProductInfo[$this->iFieldTranslations['description']];
				}

				$detailedDescription = $thisProductInfo[$this->iFieldTranslations['detailed_description']];
				if (empty($description)) {
					$description = $detailedDescription;
					if (strlen($description) > 255) {
						$string = wordwrap($description, 250);
						$description = mb_substr($string, 0, strpos($string, "\n"));
					} else {
						$detailedDescription = "";
					}
				}

				if (empty($description)) {
					$description = "Product " . $thisProductInfo[$this->iFieldTranslations['product_code']];
				}
				$description = str_replace("\n", " ", $description);
				$description = str_replace("\r", " ", $description);
				while (strpos($description, "  ") !== false) {
					$description = str_replace("  ", " ", $description);
				}

				$cost = $thisProductInfo[$this->iFieldTranslations['base_cost']];
				if (empty($cost)) {
					continue;
				} else {
					$internalUseOnly = 0;
				}

				$productCodePrefix = substr(ProductDistributor::$iLoadedValues['product_distributors'][$this->iLocationRow['product_distributor_id']]['product_distributor_code'],0,1) . $this->iLocationRow['product_distributor_id'] . "_";
				$useProductNumber = 0;
				do {
					$useProductCode = $productCodePrefix . substr($productCode, 0, (95 - strlen($productCodePrefix))) . (empty($useProductNumber) ? "" : "_" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "product_code", $useProductCode);
					if (empty($dupProductId)) {
						break;
					}
					$useProductNumber++;
				} while (!empty($dupProductId));
				if ($useProductNumber > 0) {
					addDebugLog("possible duplicate product: " . $productCode . " from " . $this->iLocationRow['description'],true);
				}

				$useProductNumber = 0;
				do {
					$useLinkName = makeCode($description, array("use_dash" => true, "lowercase" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "link_name", $useLinkName);
					$useProductNumber++;
				} while (!empty($dupProductId));
				$description = str_replace("\n", " ", $description);
				$description = str_replace("\r", " ", $description);
				for ($x = 0; $x < 10; $x++) {
					$description = str_replace("  ", " ", $description);
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$insertSet = executeQuery("insert into products (client_id,product_code,description,detailed_description,link_name,remote_identifier,product_manufacturer_id," .
					"base_cost,list_price,date_created,time_changed,reindex,internal_use_only) values (?,?,?,?,?, ?,?,?,?,now(),now(),1,?)",
					$GLOBALS['gClientId'], $useProductCode, $description, $detailedDescription, $useLinkName, $remoteIdentifier, $productManufacturerId, $cost, $thisProductInfo[$this->iFieldTranslations['list_price']], $internalUseOnly);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					continue;
				}
				$productId = $insertSet['insert_id'];
				freeResult($insertSet);
				$insertSet = executeQuery("insert into product_data (client_id,product_id,upc_code) values (?,?,?)", $GLOBALS['gClientId'], $productId, $upcCode);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					continue;
				}
				freeResult($insertSet);
				$insertSet = executeQuery("insert into product_inventories (product_id,location_id) values (?,?)", $productId, $this->iLocationRow['location_id']);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					continue;
				}
				freeResult($insertSet);
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$insertSet = executeQuery("insert ignore into product_category_links (product_category_id,product_id) select product_category_id,? from product_categories where inactive = 0 and client_id = ? and add_new_product = 1", $productId, $GLOBALS['gClientId']);
				freeResult($insertSet);

				$resultSet = executeQuery("select *,(select group_concat(image_identifier) from product_remote_images where product_id = products.product_id order by primary_image desc) product_remote_images from products left outer join product_data using (product_id) where products.product_id = ? and products.client_id = ?", $productId, $GLOBALS['gClientId']);
				$productRow = getNextRow($resultSet);
				self::loadValues("products");
				ProductDistributor::$iLoadedValues['products'][$productId] = $productRow;
				ProductDistributor::$iLoadedValues['product_upc'][$productId] = $productRow['upc_code'];
				ProductDistributor::$iLoadedValues['product_id_from_upc'][$productRow['upc_code']] = $productId;
				$insertCount++;
			} else {
				$productRow = $this->getProductRow($productId);
				if (empty($productRow)) {
					continue;
				}
				$foundCount++;
			}

			$productIdsProcessed[$productId] = $productId;

			self::loadValues("cannot_sell_product_manufacturers");
			if (array_key_exists($productRow['product_manufacturer_id'], ProductDistributor::$iLoadedValues['cannot_sell_product_manufacturers'][$this->iLocationRow['product_distributor_id']])) {
				continue;
			}

			if (!self::$iCorewareShootingSports) {
				$this->syncStates($productId, $corewareProductData['restricted_states']);
			}

			$corewareProductData['product_manufacturer_id'] = $productManufacturerId;
			$corewareProductData['remote_identifier'] = $remoteIdentifier;

			if (empty($corewareProductData['serializable'])) {
				$corewareProductData['serializable'] = $productRow['serializable'];
				if (in_array(strtoupper($thisProductInfo['category_code']), $fflCategoryCodes)) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if (in_array(strtoupper($thisProductInfo['category_code']), $fflCategoryCodes)) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}

			if (self::$iCorewareShootingSports) {
				$originalImageId = $productRow['image_id'];
				$productRow['image_id'] = getFieldFromId("image_id", "images", "image_id", $productRow['image_id'], "os_filename is not null or file_content is not null");
				if (empty($productRow['image_id'])) {
					if (!empty($originalImageId)) {
						$badImageCount++;
						executeQuery("update images set os_filename = 'CSSC_BAD_IMAGES' where image_id = ? or image_id in (select image_id from product_images where product_id = ?)",$originalImageId,$productRow['product_id']);
						executeQuery("update products set image_id = null where product_id = ?",$productRow['product_id']);
						executeQuery("delete from product_images where product_id = ?",$productRow['product_id']);
						executeQuery("delete from image_data where image_id in (select image_id from images where os_filename = 'CSSC_BAD_IMAGES')");
						executeQuery("delete from images where os_filename = 'CSSC_BAD_IMAGES'");
					}

					$imageId = "";
					$imageFilename = $thisProductInfo['product_name'] . ".jpg";
					if (array_key_exists($imageFilename, $imageFileList)) {
						$localImageFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-" . getRandomString(6) . ".jpg";
						$imageContents = "";
						if (ftp_get($this->iConnection, $localImageFilename, $imageFilename, FTP_BINARY)) {
							$imageContents = file_get_contents($localImageFilename);
							unlink($localImageFilename);
						}
						if (!empty($imageContents)) {
							$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $imageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "BILLHICKS_IMAGE"));
							if (!empty($imageId)) {
								SimpleImage::reduceImageSize($imageId, array("compression" => 60, "max_image_dimension" => 1600, "convert" => true));
							}
						}
					}
					if (!empty($imageId)) {
						$imageCount++;
						$this->updateProductField($productId, "image_id", $imageId);
						ProductCatalog::createProductImageFiles($productId, $imageId);
					}
				}
			} else {
				$this->processRemoteImages($productRow, $corewareProductData);
			}

			if (empty($productRow['link_name'])) {
				$useProductNumber = 0;
				do {
					$useLinkName = makeCode($productRow['description'], array("use_dash" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "link_name", $useLinkName);
					$useProductNumber++;
				} while (!empty($dupProductId));
				$corewareProductData['link_name'] = $useLinkName;
			}

			if (self::$iCorewareShootingSports) {
				if ($thisProductInfo[$this->iFieldTranslations['manufacturer_advertised_price']] > $thisProductInfo[$this->iFieldTranslations['base_cost']]) {
					$corewareProductData['manufacturer_advertised_price'] = $thisProductInfo[$this->iFieldTranslations['manufacturer_advertised_price']];
				}
			}

			if ($productRow['upc_code'] != $upcCode) {
				$realProductId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
				if (!empty($realProductId) && $realProductId != $productId) {
					executeQuery("insert ignore into potential_product_duplicates (client_id,product_id,duplicate_product_id) values (?,?,?)", $GLOBALS['gClientId'], $realProductId, $productId);
				}
			} elseif ($upcCode != $corewareProductData['upc_code']) {
				$realProductId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
				if (!empty($realProductId) && $realProductId != $productId) {
					executeQuery("insert ignore into potential_product_duplicates (client_id,product_id,duplicate_product_id) values (?,?,?)", $GLOBALS['gClientId'], $realProductId, $productId);
					continue;
				}
			}

			$corewareProductData['model'] = $thisProductInfo[$this->iFieldTranslations['model']];
			$corewareProductData['upc_code'] = $upcCode;
			$corewareProductData['manufacturer_sku'] = $thisProductInfo[$this->iFieldTranslations['manufacturer_sku']];
			$corewareProductData['width'] = $thisProductInfo[$this->iFieldTranslations['width']];
			$corewareProductData['length'] = $thisProductInfo[$this->iFieldTranslations['length']];
			$corewareProductData['height'] = $thisProductInfo[$this->iFieldTranslations['height']];
			$corewareProductData['weight'] = $thisProductInfo[$this->iFieldTranslations['weight']];
			if ($thisProductInfo[$this->iFieldTranslations['list_price']] > $thisProductInfo[$this->iFieldTranslations['base_cost']]) {
				$corewareProductData['list_price'] = $thisProductInfo[$this->iFieldTranslations['list_price']];
			} else {
				unset($corewareProductData['list_price']);
			}

			if ($this->updateProductInformation($productId, $productRow, $corewareProductData)) {
				$updatedCount++;
			}
			$this->cleanUpDistributorCodes($productId, $thisProductInfo[$this->iFieldTranslations['product_code']]);
			$productCategoryAdded = $this->addCorewareProductCategories($productId, $corewareProductData);
			$this->addCorewareProductFacets($productId, $corewareProductData);

			if (self::$iCorewareShootingSports && !$productCategoryAdded && !empty($thisProductInfo['category_code'])) {
				$this->addProductCategories($productId, array($thisProductInfo['category_code']));
			}

		}
		fclose($openFile);
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}
		$quantityAvailable = false;
		$cost = false;

		if (!$this->connect()) {
			return false;
		}
		$directoryName = "Coreware"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FTP_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$feedDirectoryName = "Feeds"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FEED_DIRECTORY","PRODUCT_DISTRIBUTORS");
		if (!ftp_chdir($this->iConnection, $directoryName . "/" . $feedDirectoryName)) {
			$this->iErrorMessage = "Distributor directory does not exist: " . $directoryName . "/" . $feedDirectoryName;
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		rsort($fileList);
		$catalogFile = "";
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("BHC_Catalog")) == "BHC_Catalog") {
				$catalogFile = $thisFile;
				break;
			}
		}
		if (empty($catalogFile)) {
			$this->iErrorMessage = "Catalog file not found";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-catalog-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, $catalogFile, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		$inventoryFile = "";
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("BHC_Inventory")) == "BHC_Inventory") {
				$inventoryFile = $thisFile;
				break;
			}
		}
		if (empty($inventoryFile)) {
			$this->iErrorMessage = "Inventory file not found";
			return false;
		}
		$inventoryFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-inventory-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $inventoryFilename, $inventoryFile, FTP_ASCII)) {
			$this->iErrorMessage = "Inventory file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);

		$productCosts = array();
		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}

			if (!empty($thisProductInfo['universal_product_code'])) {
				$productCosts[$thisProductInfo['universal_product_code']] = $thisProductInfo['product_price'];
			}
			break;
		}
		fclose($openFile);
		unlink($catalogFilename);

		$openFile = fopen($inventoryFilename, "r");
		$count = 0;
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if (empty($thisProductInfo['upc']) || $thisProductInfo['product'] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['qty_avail'];
			$cost = $productCosts[$thisProductInfo['upc']];
			break;
		}
		fclose($openFile);

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		$directoryName = "Coreware"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FTP_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$feedDirectoryName = "Feeds"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FEED_DIRECTORY","PRODUCT_DISTRIBUTORS");
		if (!ftp_chdir($this->iConnection, $directoryName . "/" . $feedDirectoryName)) {
			$this->iErrorMessage = "Distributor directory does not exist: " . $directoryName . "/" . $feedDirectoryName;
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		rsort($fileList);
		$catalogFile = "";
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("BHC_Catalog")) == "BHC_Catalog") {
				$catalogFile = $thisFile;
				break;
			}
		}
		if (empty($catalogFile)) {
			$this->iErrorMessage = "Catalog file not found";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-catalog-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, $catalogFile, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}
		$inventoryFile = "";
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("BHC_Inventory")) == "BHC_Inventory") {
				$inventoryFile = $thisFile;
				break;
			}
		}
		if (empty($inventoryFile)) {
			$this->iErrorMessage = "Inventory file not found";
			return false;
		}
		$inventoryFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-inventory-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $inventoryFilename, $inventoryFile, FTP_ASCII)) {
			$this->iErrorMessage = "Inventory file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		$productCosts = array();
		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}

			if (!empty($thisProductInfo['universal_product_code'])) {
				$productCosts[$thisProductInfo['universal_product_code']] = $thisProductInfo['product_price'];
			}
		}
		fclose($openFile);
		unlink($catalogFilename);

		$openFile = fopen($inventoryFilename, "r");
		$count = 0;
		$inventoryUpdateArray = array();
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if (empty($thisProductInfo['upc'])) {
				continue;
			}
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['product'],
				"quantity" => $thisProductInfo['qty_avail'],
				"cost" => $productCosts[$thisProductInfo['upc']]);

			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		fclose($openFile);
		unlink($inventoryFilename);

		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		if (!ftp_chdir($this->iConnection, "BHC Digital Images ALL/ Active Vendors Abbreviations")) {
			$this->iErrorMessage = "Manufacturer directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "Active Vendor List.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Manufacturer file cannot be downloaded";
			return false;
		}

		$count = 0;
		$productManufacturers = array();
		$fieldNames = array();
		$openFile = fopen($catalogFilename, "r");
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisManufacturer = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisManufacturer[$fieldName] = trim($csvData[$index]);
			}
			$productManufacturers[$thisManufacturer['prefix']] = array("business_name" => ucwords(strtolower($thisManufacturer['company'])));
		}
		fclose($openFile);
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		uasort($productManufacturers, array($this, "sortManufacturers"));
		return $productManufacturers;
	}

	function sortManufacturers($a, $b) {
		if ($a['business_name'] == $b['business_name']) {
			return 0;
		}
		return ($a['business_name'] > $b['business_name']) ? 1 : -1;
	}

	function getCategories($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		$directoryName = "Coreware"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FTP_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$feedDirectoryName = "Feeds"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FEED_DIRECTORY","PRODUCT_DISTRIBUTORS");
		if (!ftp_chdir($this->iConnection, $directoryName . "/" . $feedDirectoryName)) {
			$this->iErrorMessage = "Distributor directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		rsort($fileList);
		$catalogFile = "";
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("BHC_Catalog")) == "BHC_Catalog") {
				$catalogFile = $thisFile;
				break;
			}
		}
		if (empty($catalogFile)) {
			$this->iErrorMessage = "Catalog file not found";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, $catalogFile, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		$count = 0;
		$productCategories = array();
		$fieldNames = array();
		$openFile = fopen($catalogFilename, "r");
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			$thisProductInfo['category_code'] = makeCode($thisProductInfo['category_code']);
			if (!empty($thisProductInfo['category_code']) && !array_key_exists($thisProductInfo['category_code'], $productCategories)) {
				$productCategories[$thisProductInfo['category_code']] = array("description" => $thisProductInfo['category_description']);
			}
		}
		fclose($openFile);
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		uasort($productCategories, array($this, "sortCategories"));
		return $productCategories;
	}

	function sortCategories($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}

	function getFacets($parameters = array()) {
		return array();
	}

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		$ordersRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($ordersRow['contact_id']);
		$shipMethod = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "SHIP_VIA", "PRODUCT_DISTRIBUTORS");
		$shipMethod = $shipMethod ?: "UPSF";
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
		$fflOrderItemRows = $orderParts['ffl_order_items'];
		$dealerOrderItemRows = $orderParts['dealer_order_items'];

		$fflFileContent = "";

		$dropshipCustomerNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		if (empty($dropshipCustomerNumber) || !is_numeric($dropshipCustomerNumber)) {
			$dropshipCustomerNumber = $this->iLocationCredentialRow['customer_number'];
		}
		// store number will override address that is sent - need to send 0000 for all dropship orders
		$dropshipStoreNumber = "0000";
		$storeNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "STORE_NUMBER", "PRODUCT_DISTRIBUTORS");
		if (empty($storeNumber)) {
			$storeNumber = "0000";
		}
		$fflRow = array();
		if (!empty($fflOrderItemRows)) {
			$fflRow = (new FFL($ordersRow['federal_firearms_licensee_id']))->getFFLRow();
			if (empty($fflRow)) {
				$this->iErrorMessage = "No FFL Dealer set for this order";
				return false;
			}

			$addressPrefix = (empty($fflRow['mailing_address_preferred']) || empty($fflRow['mailing_address_1']) ? "" : "mailing_");
			if (empty($fflRow[$addressPrefix . 'address_1']) || empty($fflRow[$addressPrefix . 'postal_code']) || empty($fflRow[$addressPrefix . 'city'])) {
				$this->iErrorMessage = "FFL Dealer has no address";
				return false;
			}

			$fflFileContent .= "HL\tCustomer#\tShip to#\tShip to Name1\tAddress 1\tAddress 2\tcity\tstate\tzip\tcust po\tship method\tnotes\tFFL#\t\n";
			$fflFileContent .= "H\t" . $dropshipCustomerNumber . "\t" . $dropshipStoreNumber . "\t" . (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "\t" . $fflRow[$addressPrefix . 'address_1'] . "\t" .
				$fflRow[$addressPrefix . 'address_2'] . "\t" . $fflRow[$addressPrefix . 'city'] . "\t" . $fflRow[$addressPrefix . 'state'] . "\t" . substr($fflRow[$addressPrefix . 'postal_code'], 0, 5) .
				"\t" . $orderId . "-" . "%remote_order_id%\tUPSF\t" . $ordersRow['phone_number'] . "," . $ordersRow['full_name'] . "\t" . $fflRow['license_number'] . "\t\n";
			$fflFileContent .= "LL\tItem\tDescription\tQty\tPrice\n";

			foreach ($fflOrderItemRows as $thisItemRow) {

				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}

				$fflFileContent .= "L\t" . $thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['product_row']['description'] . "\t" . $thisItemRow['quantity'] . "\t" . $thisItemRow['sale_price'] . "\n";
			}
		}

		$customerFileContent = "";
		if (!empty($customerOrderItemRows)) {
			$customerFileContent .= "HL\tCustomer#\tShip to#\tShip to Name1\tAddress 1\tAddress 2\tcity\tstate\tzip\tcust po\tship method\tnotes\tFFL#\t\n";
			$customerFileContent .= "H\t" . $dropshipCustomerNumber . "\t" . $dropshipStoreNumber . "\t" . $ordersRow['full_name'] . "\t" . $addressRow['address_1'] . "\t" . $addressRow['address_2'] . "\t" .
				$addressRow['city'] . "\t" . $addressRow['state'] . "\t" . substr($addressRow['postal_code'], 0, 5) . "\t" . $orderId . "-" . "%remote_order_id%\t" . $shipMethod . "\t\t\t\n";
			$customerFileContent .= "LL\tItem\tDescription\tQty\tPrice\n";

			foreach ($customerOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}

				$customerFileContent .= "L\t" . $thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['product_row']['description'] . "\t" . $thisItemRow['quantity'] . "\t" . $thisItemRow['sale_price'] . "\n";
			}
		}

		$dealerFileContent = "";
		if (!empty($dealerOrderItemRows)) {
			$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
			$dealerFileContent .= "HL\tCustomer#\tShip to#\tShip to Name1\tAddress 1\tAddress 2\tcity\tstate\tzip\tcust po\tship method\tnotes\tFFL#\t\n";
			$dealerFileContent .= "H\t" . $this->iLocationCredentialRow['customer_number'] . "\t" . $storeNumber . "\t" . $dealerName . "\t" . $this->iLocationContactRow['address_1'] . "\t" .
				$this->iLocationContactRow['address_2'] . "\t" . $this->iLocationContactRow['city'] . "\t" . $this->iLocationContactRow['state'] . "\t" . substr($this->iLocationContactRow['postal_code'], 0, 5) .
				"\t" . $orderId . "-" . "%remote_order_id%\t" . $shipMethod . "\t" . $ordersRow['phone_number'] . "," . $ordersRow['full_name'] . "\t" . CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_LICENSE_NUMBER", "PRODUCT_DISTRIBUTORS") . "\t\n";
			$dealerFileContent .= "LL\tItem\tDescription\tQty\tPrice\n";

			foreach ($dealerOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}

				$dealerFileContent .= "L\t" . $thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['product_row']['description'] . "\t" . $thisItemRow['quantity'] . "\t" . $thisItemRow['sale_price'] . "\n";
			}
		}

		$returnValues = array();

# Submit the orders

		$directoryName = "Coreware"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FTP_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$toDirectoryName = "To_BHC"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"TO_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$fflDirectoryName = "FFL's"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FFL_DIRECTORY","PRODUCT_DISTRIBUTORS");
		if (!empty($fflFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			$fflFileContent = str_replace("%remote_order_id%", $remoteOrderId, $fflFileContent);
			foreach ($fflOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$uploadResponse = ftpFilePutContents("billhicksco.hostedftp.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				$directoryName . "/" . $toDirectoryName, $this->iLocationCredentialRow['customer_number'] . "-" . $remoteOrderId . ".txt", $fflFileContent);
			if ($uploadResponse !== true) {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['ffl'] = array("error_message" => $uploadResponse);
			} else {
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));
			}

			if (!empty($fflRow['file_id'])) {
				$osFilename = getFieldFromId("os_filename", "files", "file_id", $fflRow['file_id']);
				$extension = getFieldFromId("extension", "files", "file_id", $fflRow['file_id']);
				if (empty($osFilename)) {
					$fflLicenseFileContent = getFieldFromId("file_content", "files", "file_id", $fflRow['file_id']);
				} else {
					$fflLicenseFileContent = getExternalFileContents($osFilename);
				}
				if (!empty($fflLicenseFileContent)) {
					$uploadResponse = ftpFilePutContents("billhicksco.hostedftp.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
						$directoryName . "/" . $fflDirectoryName, substr(str_replace("-", "", $fflRow['license_number']), 0, 3) . substr(str_replace("-", "", $fflRow['license_number']), -5) . "." . $extension, $fflLicenseFileContent);
					if ($uploadResponse !== true) {
						sendEmail(array("subject" => "FFL file failed to upload to Bill Hicks", "body" => "<p>FFL file for " . $fflRow['licensee_name'] . " for Order ID " . $orderId
							. " failed to upload to Bill Hicks.  The error message was: " . $uploadResponse . "<br> The order was placed, but the FFL file will need to be sent to Bill Hicks by email.",
							"notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
					}
				}
			}
			if (!empty($fflRow['sot_file_id'])) {
				$osFilename = getFieldFromId("os_filename", "files", "file_id", $fflRow['sot_file_id']);
				$extension = getFieldFromId("extension", "files", "file_id", $fflRow['sot_file_id']);
				if (empty($osFilename)) {
					$sotLicenseFileContent = getFieldFromId("file_content", "files", "file_id", $fflRow['sot_file_id']);
				} else {
					$sotLicenseFileContent = getExternalFileContents($osFilename);
				}
				if (!empty($sotLicenseFileContent)) {
					$uploadResponse = ftpFilePutContents("billhicksco.hostedftp.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
						$directoryName . "/" . $fflDirectoryName, "SOT" . substr(str_replace("-", "", $fflRow['license_number']), 0, 3) . substr(str_replace("-", "", $fflRow['license_number']), -5) . "." . $extension, $sotLicenseFileContent);
					if ($uploadResponse !== true) {
						sendEmail(array("subject" => "SOT file failed to upload to Bill Hicks", "body" => "<p>SOT file for " . $fflRow['licensee_name'] . " for Order ID " . $orderId
							. " failed to upload to Bill Hicks.  The error message was: " . $uploadResponse . "<br> The order was placed, but the SOT file will need to be sent to Bill Hicks by email.",
							"notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
					}

				}
			}
		}

		if (!empty($customerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			$customerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $customerFileContent);
			foreach ($customerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$uploadResponse = ftpFilePutContents("billhicksco.hostedftp.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				$directoryName . "/" . $toDirectoryName, $this->iLocationCredentialRow['customer_number'] . "-" . $remoteOrderId . ".txt", $customerFileContent);
			if ($uploadResponse !== true) {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['customer'] = array("error_message" => $uploadResponse);
			} else {
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => $ordersRow['full_name']);
			}
		}

		if (!empty($dealerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			$dealerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $dealerFileContent);
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$uploadResponse = ftpFilePutContents("billhicksco.hostedftp.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				$directoryName . "/" . $toDirectoryName, $this->iLocationCredentialRow['customer_number'] . "-" . $remoteOrderId . ".txt", $dealerFileContent);
			if ($uploadResponse !== true) {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['dealer'] = array("error_message" => $uploadResponse);
			} else {
				$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => $GLOBALS['gClientName']);
			}
		}
		return $returnValues;
	}

	function placeDistributorOrder($productArray, $parameters = array()) {
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
			$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisProduct['product_id'], "location_id = ?", ProductDistributor::getInventoryLocation($locationId));
			if (empty($inventoryQuantity) || empty($distributorProductCode)) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$this->iErrorMessage = "Product '" . getFieldFromId("description", "products", "product_id", $thisProduct['product_id']) . "' is not available from this distributor. Inventory: " . $inventoryQuantity;
				return false;
			}
			$dealerOrderItemRows[$thisProduct['product_id']] = array("distributor_product_code" => $distributorProductCode, "product_id" => $thisProduct['product_id'], "quantity" => $thisProduct['quantity'], "notes" => $thisProduct['notes']);
		}

		if (empty($dealerOrderItemRows)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "No products found to order";
			return false;
		}

		$orderPrefix = getPreference("distributor_order_prefix");

# Process products to be ordered

		$storeNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "STORE_NUMBER", "PRODUCT_DISTRIBUTORS");
		if (empty($storeNumber)) {
			$storeNumber = "0000";
		}
		$dealerFileContent = "";
		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
		$dealerPhoneNumber = $this->iLocationContactRow['phone_number'];

		$dealerFileContent .= "HL\tCustomer#\tShip to#\tShip to Name1\tAddress 1\tAddress 2\tcity\tstate\tzip\tcust po\tship method\tnotes\tFFL#\t\n";
		$dealerFileContent .= "H\t" . $this->iLocationCredentialRow['customer_number'] . "\t" . $storeNumber . "\t" . $dealerName . "\t" . $this->iLocationContactRow['address_1'] . "\t" .
			$this->iLocationContactRow['address_2'] . "\t" . $this->iLocationContactRow['city'] . "\t" . $this->iLocationContactRow['state'] . "\t" . substr($this->iLocationContactRow['postal_code'], 0, 5) .
			"\t" . $orderPrefix . $distributorOrderId . "\tUPSF\t" . $dealerPhoneNumber . "," . $dealerName . "\t\t\n";
		$dealerFileContent .= "LL\tItem\tDescription\tQty\tPrice\n";

		$productCatalog = new ProductCatalog();
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$productRow = ProductCatalog::getCachedProductRow($thisItemRow['product_id']);
			$salePriceInfo = $productCatalog->getProductSalePrice($thisItemRow['product_id'], array("product_information" => $productRow));
			$salePrice = $salePriceInfo['sale_price'];
			$productRow['description'] = str_replace("\n", " ", $productRow['description']);
			$productRow['description'] = str_replace("\r", " ", $productRow['description']);
			for ($x = 0; $x < 10; $x++) {
				$productRow['description'] = str_replace("  ", " ", $productRow['description']);
			}

			$dealerFileContent .= "L\t" . $thisItemRow['distributor_product_code'] . "\t" . $productRow['description'] . "\t" . $thisItemRow['quantity'] . "\t" . $salePrice . "\n";
		}

		$returnValues = array();

# Submit the order

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}
		$directoryName = "Coreware"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"FTP_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$toDirectoryName = "To_BHC"; #CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'],"TO_DIRECTORY","PRODUCT_DISTRIBUTORS");
		$uploadResponse = ftpFilePutContents("billhicksco.hostedftp.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
			$directoryName . "/" . $toDirectoryName, $this->iLocationCredentialRow['customer_number'] . "-DO" . $distributorOrderId . ".txt", $dealerFileContent);
		if ($uploadResponse !== true) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "Upload to " . $directoryName . "/" . $toDirectoryName . " error: " . $uploadResponse;
			return false;
		} else {
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $distributorOrderId);
		}

		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		if ($this->iTrackingAlreadyRun) {
			return false;
		}
		$directoryName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		$fromDirectoryName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FROM_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		$filenamePrefixes = array();
		if (!empty($directoryName) && $directoryName != "Coreware") {
			$this->iLocationCredentialRow['user_name'] = $this->iOriginalUserName;
			$this->iLocationCredentialRow['password'] = $this->iOriginalPassword;
			$filenamePrefixes[] = "ASN";
		} else {
			$directoryName = "Coreware";
			$fromDirectoryName = "From_BHC";
			$filenamePrefixes[] = "ASN_856_" . $this->iLocationCredentialRow['customer_number'];
			$dropshipCustomerNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			if (!empty($dropshipCustomerNumber)) {
				$filenamePrefixes[] = "ASN_856_" . $dropshipCustomerNumber;
			}
		}
		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, $directoryName . "/" . $fromDirectoryName)) {
			$this->iErrorMessage = "Distributor directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		$asnFiles = array();
		if (!empty($fileList)) {
			foreach ($fileList as $thisFile) {
				foreach ($filenamePrefixes as $filenamePrefix) {
					if (substr($thisFile, 0, strlen($filenamePrefix . ".")) == $filenamePrefix . ".") {
						$asnFiles[] = $thisFile;
						break;
					}
				}
			}
		}
		$this->iTrackingAlreadyRun = true;
		$returnValues = array();
		foreach ($asnFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			$fieldNames = array();
			foreach ($fileLines as $thisLine) {
				$fieldData = explode("|", $thisLine);
				if ($fieldData[0] == "ASN") {
					$fieldNames = explode("|", $thisLine);
					continue;
				}
				if (empty($thisLine)) {
					continue;
				}
				if (!is_array($GLOBALS['gProductDistributorIssues'])) {
					$GLOBALS['gProductDistributorIssues'] = array();
				}
				if (!array_key_exists($this->iProductDistributorRow['product_distributor_id'], $GLOBALS['gProductDistributorIssues'])) {
					$GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']] = array();
					$resultSet = executeQuery("select hash_code from product_distributor_issues where product_distributor_id = ?", $this->iProductDistributorRow['product_distributor_id']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']][$row['hash_code']] = true;
					}
				}
				if (array_key_exists(md5($thisLine), $GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']])) {
					continue;
				}
				executeQuery("insert into product_distributor_issues (product_distributor_id,hash_code,content) values (?,?,?)", $this->iProductDistributorRow['product_distributor_id'], md5($thisLine), $thisLine);
				if (count($fieldData) < count($fieldNames)) {
					$newFieldNames = array();
					foreach ($fieldNames as $fieldName) {
						if ($fieldName != "Price") {
							$newFieldNames[] = $fieldName;
						}
					}
					$fieldNames = $newFieldNames;
				}
				$thisRecord = array();
				foreach ($fieldNames as $index => $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$thisRecord[strtolower($fieldName)] = (strtolower($fieldData[$index]) == "null" ? "" : trim($fieldData[$index]));
				}
				$orderIdParts = explode("-", $thisRecord['po_number']);
				if (count($orderIdParts) == 2) {
					$orderId = $orderIdParts[0];
					$remoteOrderId = $orderIdParts[1];
					$orderId = getFieldFromId("order_id", "remote_orders", "remote_order_id", $remoteOrderId, "order_id = ?", $orderId);
				} else {
					$remoteOrderId = $orderIdParts[0];
					$orderId = getFieldFromId("order_id", "remote_orders", "remote_order_id", $remoteOrderId);
				}
				if (empty($orderId) || empty($remoteOrderId)) {
					continue;
				}
				if (!empty($thisRecord['bhc_order_number'])) {
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $thisRecord['bhc_order_number'], $remoteOrderId);
				}
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "remote_order_id", $remoteOrderId, "tracking_identifier is null");
				if (empty($orderShipmentId)) {
					continue;
				}
				if (substr(strtolower($thisRecord['carrier']), 0, 2) == "sp") {
					$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", "SPEED");
					if (empty($shippingCarrierId)) {
						$insertSet = executeQuery("insert into shipping_carriers (client_id,shipping_carrier_code,description,link_url) values (?,'SPEED','Spee-Dee Delivery','http://speedeedelivery.com/track-a-shipment/?v=detail&barcode=%tracking_identifier%')", $GLOBALS['gClientId']);
						$shippingCarrierId = $insertSet['insert_id'];
					}
				} else {
					$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", "UPS");
				}
				$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
					$thisRecord['tracking_number'], $shippingCarrierId, $thisRecord['carrier'], $orderShipmentId);
				if ($resultSet['affected_rows'] > 0) {
					Order::sendTrackingEmail($orderShipmentId);
					executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
						$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $thisRecord['tracking_number'],
						"Tracking number added by " . $this->iProductDistributorRow['description']);
					$returnValues[] = $orderShipmentId;
				}
			}
		}
		ftp_close($this->iConnection);

		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, $directoryName . "/" . $fromDirectoryName)) {
			$this->iErrorMessage = "Distributor directory does not exist";
			return false;
		}

# Read acknowledgement files

		$filenamePrefixes = array();
		if (!empty($directoryName) && $directoryName != "Coreware") {
			$this->iLocationCredentialRow['user_name'] = $this->iOriginalUserName;
			$this->iLocationCredentialRow['password'] = $this->iOriginalPassword;
			$filenamePrefixes[] = "ACK";
		} else {
			$filenamePrefixes[] = "ACK_855_" . $this->iLocationCredentialRow['customer_number'];
			$dropshipCustomerNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			if (!empty($dropshipCustomerNumber)) {
				$filenamePrefixes[] = "ACK_855_" . $dropshipCustomerNumber;
			}
		}

		$ackFiles = array();
		if (is_array($fileList) && !empty($fileList)) {
			foreach ($fileList as $thisFile) {
				foreach ($filenamePrefixes as $filenamePrefix) {
					if (substr($thisFile, 0, strlen($filenamePrefix . ".")) == $filenamePrefix . ".") {
						$ackFiles[] = $thisFile;
						break;
					}
				}
			}
		}
		// Make sure SHIPMENT_ERROR status exists
		$shipmentErrorStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "SHIPMENT_ERROR", "inactive = 0");
		if (empty($shipmentErrorStatusId)) {
			$insertSet = executeQuery("insert ignore into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,'SHIPMENT_ERROR','Shipment Error','#FF0000',1)", $GLOBALS['gClientId']);
			$shipmentErrorStatusId = $insertSet['insert_id'];
		}
		$fcaUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");

		$returnValues = array();
		foreach ($ackFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/billhicks-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			$fieldNames = array();
			foreach ($fileLines as $thisLine) {
				$fieldData = explode("|", $thisLine);
				if ($fieldData[0] == "ACK") {
					$fieldNames = explode("|", $thisLine);
					continue;
				}
				if (empty($thisLine)) {
					continue;
				}
				if (!is_array($GLOBALS['gProductDistributorIssues'])) {
					$GLOBALS['gProductDistributorIssues'] = array();
				}
				if (!array_key_exists($this->iProductDistributorRow['product_distributor_id'], $GLOBALS['gProductDistributorIssues'])) {
					$GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']] = array();
					$resultSet = executeQuery("select hash_code from product_distributor_issues where product_distributor_id = ?", $this->iProductDistributorRow['product_distributor_id']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']][$row['hash_code']] = true;
					}
				}
				if (array_key_exists(md5($thisLine), $GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']])) {
					continue;
				}
				executeQuery("insert into product_distributor_issues (product_distributor_id,hash_code,content) values (?,?,?)", $this->iProductDistributorRow['product_distributor_id'], md5($thisLine), $thisLine);
				if (count($fieldData) < count($fieldNames)) {
					$newFieldNames = array();
					foreach ($fieldNames as $fieldName) {
						if ($fieldName != "Price") {
							$newFieldNames[] = $fieldName;
						}
					}
					$fieldNames = $newFieldNames;
				}
				$thisRecord = array();
				foreach ($fieldNames as $index => $fieldName) {
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$thisRecord[strtolower($fieldName)] = (strtolower($fieldData[$index]) == "null" ? "" : trim($fieldData[$index]));
				}
				$orderIdParts = explode("-", $thisRecord['po_number']);
				if (count($orderIdParts) == 2) {
					$orderId = $orderIdParts[0];
					$remoteOrderId = $orderIdParts[1];
					$orderId = getFieldFromId("order_id", "remote_orders", "remote_order_id", $remoteOrderId, "order_id = ?", $orderId);
				} else {
					$remoteOrderId = $orderIdParts[0];
					$orderId = getFieldFromId("order_id", "remote_orders", "remote_order_id", $remoteOrderId);
				}
				if (empty($orderId) || empty($remoteOrderId)) {
					continue;
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				if (!empty($orderRow['date_completed']) || !empty($orderRow['deleted'])) {
					continue;
				}
				$quantityOrdered = $thisRecord['quantity_ordered'];
				$quantityCommitted = $thisRecord['quantity_committed'];
				if ($quantityCommitted < $quantityOrdered) {
					$errorMessage = "From Bill Hicks by background process: Product out of stock. Quantity Ordered: " . $quantityOrdered . ", Quantity Committed: " . $quantityCommitted;

					// Check order_notes to make sure we don't set status multiple times
					$notesResult = executeReadQuery("select * from order_notes where order_id = ?", $orderId);
					while ($notesRow = getNextRow($notesResult)) {
						if (strpos($notesRow['content'], "From Bill Hicks by background process: Product out of stock") !== false) {
							continue 2;
						}
					}
					freeResult($notesResult);
					Order::updateOrderStatus($orderId, $shipmentErrorStatusId);
					if (!empty($fcaUserId)) {
						executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, $errorMessage);
					}
					executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					sendEmail(array("subject" => "Bill Hicks Order failed", "body" => "<p>The shipment for Order ID# " . $orderId . " from Bill Hicks " .
						"failed and has been removed. This shipment will need to be replaced. The error message from Bill Hicks is:</p>" .
						$errorMessage, "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
				} elseif (!empty($thisRecord['bhc_order_number'])) {
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $thisRecord['bhc_order_number'], $remoteOrderId);
				}
			}
		}
		return $returnValues;
	}
}
