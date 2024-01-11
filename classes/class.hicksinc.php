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

class HicksInc extends ProductDistributor {

	private $iConnection;
	private $iFieldTranslations = array("upc_code" => "upc", "product_code" => "item_number", "description" => "description", "detailed_description" => "detailed_description", "manufacturer_code" => "vendor_name", "model" => "IMODEL",
		"manufacturer_sku" => "manufacturer_number", "manufacturer_advertised_price" => "map", "width" => "width", "length" => "length", "height" => "depth", "weight" => "weight", "base_cost" => "price", "list_price" => "msrp");
	private $iTrackingAlreadyRun = false;

	function __construct($locationId) {
		$this->iProductDistributorCode = "HICKSINC";
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		return $this->connect();
	}

	function connect() {
		$this->iConnection = ftp_connect("ftp.hicksinc.com", 21, 600);
		if (!$this->iConnection) {
			return false;
		}
		if (!ftp_login($this->iConnection, $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'])) {
			$this->iErrorMessage = "Invalid Login";
			return false;
		}
		ftp_pasv($this->iConnection, true);
		return true;
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

		if (!ftp_chdir($this->iConnection, "fh")) {
			$this->iErrorMessage = "fh directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "full.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		$insertCount = 0;

		self::loadValues('distributor_product_codes');
		$productIdsProcessed = array();

		$processCount = 0;
		$foundCount = 0;
		$updatedCount = 0;
		$imageCount = 0;
		$noUpc = 0;
		$duplicateProductCount = 0;
		$badImageCount = 0;

		$openFile = fopen($catalogFilename, "r");
		$fieldNames = array("customer_number", "item_number", "change_date", "change_time", "firearm_code", "status", "manufacturer_number", "vendor_name", "description",
			"detailed_description", "filler1", "category", "upc", "link_to_image", "filler2", "map", "msrp", "price", "quantity_on_hand", "weight", "length", "width", "depth",
			"break_pack", "dealer_pack", "unknown", "unknown2");
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}

			if (empty($thisProductInfo[$this->iFieldTranslations['product_code']])) {
				continue;
			}

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

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$insertSet = executeQuery("insert into products (client_id,product_code,description,detailed_description,link_name,remote_identifier,product_manufacturer_id," .
					"base_cost,list_price,date_created,time_changed,reindex,internal_use_only) values (?,?,?,?,?, ?,?,?,?,now(),now(),1, ?)",
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
				if ($thisProductInfo['firearm_code'] == "Y") {
					$corewareProductData['serializable'] = 1;
				}
			}
			if ($thisProductInfo['firearm_code'] == "Y") {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}

			if (self::$iCorewareShootingSports) {
				$originalImageId = $productRow['image_id'];
				$productRow['image_id'] = getFieldFromId("image_id", "images", "image_id", $productRow['image_id'], "os_filename is not null or file_content is not null");
				if (empty($productRow['image_id']) && !empty($thisProductInfo['link_to_image'])) {
					if (!empty($originalImageId)) {
						$badImageCount++;
						executeQuery("update images set os_filename = 'CSSC_BAD_IMAGES' where image_id = ? or image_id in (select image_id from product_images where product_id = ?)",$originalImageId,$productRow['product_id']);
						executeQuery("update products set image_id = null where product_id = ?",$productRow['product_id']);
						executeQuery("delete from product_images where product_id = ?",$productRow['product_id']);
						executeQuery("delete from image_data where image_id in (select image_id from images where os_filename = 'CSSC_BAD_IMAGES')");
						executeQuery("delete from images where os_filename = 'CSSC_BAD_IMAGES'");
					}

					$imageId = "";
					$imageContents = "";
					$imageUrl = str_replace("http:", "https:", $thisProductInfo['link_to_image']);
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "file_content" => $imageContents, "name" => $productRow['product_code'] . ".jpg", "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "HICKS_IMAGE"));
						if (!empty($imageId)) {
							SimpleImage::reduceImageSize($imageId, array("compression" => 60, "max_image_dimension" => 1600, "convert" => true));
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
				$corewareProductData['upc_code'] = $upcCode;
			}

			$corewareProductData['model'] = $thisProductInfo[$this->iFieldTranslations['model']];
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

		if (!ftp_chdir($this->iConnection, "fh")) {
			$this->iErrorMessage = "fh directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "qtyprc.csv", FTP_ASCII)) {
			$zipFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv.zip";
			if (ftp_get($this->iConnection, $zipFilename, "qtyprc.csv.zip", FTP_ASCII)) {
				$zip = new ZipArchive();
				$resource = $zip->open($zipFilename);
				if ($resource === true) {
					$zip->extractTo($catalogFilename);
					$zip->close();
				} else {
					$this->iErrorMessage = "Catalog zip file cannot be uncompressed";
					return false;
				}
			} else {
				$this->iErrorMessage = "Catalog zip file cannot be downloaded";
				return false;
			}
		}
		ftp_close($this->iConnection);

		$fieldNames = array("customer_number", "item_number", "change_date", "change_time", "price", "quantity");
		$openFile = fopen($catalogFilename, "r");
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if ($thisProductInfo['item_number'] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['quantity'];
			$cost = $thisProductInfo['price'];
			break;
		}
		fclose($openFile);
		unlink($catalogFilename);

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		if (!ftp_chdir($this->iConnection, "fh")) {
			$this->iErrorMessage = "fh directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "qtyprc.csv", FTP_ASCII)) {
			$zipFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv.zip";
			if (ftp_get($this->iConnection, $zipFilename, "qtyprc.csv.zip", FTP_ASCII)) {
				$zip = new ZipArchive();
				$resource = $zip->open($zipFilename);
				if ($resource === true) {
					$zip->extractTo($catalogFilename);
					$zip->close();
				} else {
					$this->iErrorMessage = "Catalog zip file cannot be uncompressed";
					return false;
				}
			} else {
				$this->iErrorMessage = "Catalog zip file cannot be downloaded";
				return false;
			}
		}
		ftp_close($this->iConnection);
		$fieldNames = array("customer_number", "item_number", "change_date", "change_time", "price", "quantity");
		$openFile = fopen($catalogFilename, "r");
		$inventoryUpdateArray = array();
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['item_number'],
				"quantity" => $thisProductInfo['quantity'], "cost" => $thisProductInfo['price']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		fclose($openFile);
		unlink($catalogFilename);
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, "fh")) {
			$this->iErrorMessage = "fh directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "full.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		$openFile = fopen($catalogFilename, "r");
		$fieldNames = array("customer_number", "item_number", "change_date", "change_time", "firearm_code", "status", "manufacturer_number", "vendor_name", "description",
			"detailed_description", "filler1", "category", "upc", "link_to_image", "filler2", "filler3", "msrp", "price", "quantity_on_hand", "weight", "length", "width", "depth", "break_pack", "dealer_pack");
		$productManufacturers = array();
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			$manufacturerCode = makeCode($thisProductInfo['vendor_name']);
			if (!empty($manufacturerCode) && !array_key_exists($manufacturerCode, $productManufacturers)) {
				$productManufacturers[$manufacturerCode] = array("business_name" => ucwords(strtolower($thisProductInfo['vendor_name'])));
			}
		}

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

		if (!ftp_chdir($this->iConnection, "fh")) {
			$this->iErrorMessage = "fh directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "full.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		$openFile = fopen($catalogFilename, "r");
		$productCategories = array();
		$fieldNames = array("customer_number", "item_number", "change_date", "change_time", "firearm_code", "status", "manufacturer_number", "vendor_name", "description",
			"detailed_description", "filler1", "category", "upc", "link_to_image", "filler2", "filler3", "msrp", "price", "quantity_on_hand", "weight", "length", "width", "depth", "break_pack", "dealer_pack");
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			$categoryCode = makeCode($thisProductInfo['category']);
			if (!empty($categoryCode) && !array_key_exists($categoryCode, $productCategories)) {
				$productCategories[$categoryCode] = array("description" => ucwords(strtolower($thisProductInfo['category'])));
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

			foreach ($fflOrderItemRows as $thisItemRow) {
				$fflFileContent .= "\t" . $orderId . "-%remote_order_id%\t" . (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "\t" .
					$fflRow[$addressPrefix . 'address_1'] . "\t" . $fflRow[$addressPrefix . 'address_2'] . "\t" . $fflRow[$addressPrefix . 'city'] . "\t" . $fflRow[$addressPrefix . 'state'] . "\t" . substr($fflRow[$addressPrefix . 'postal_code'], 0, 5) . "\t" .
					$thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['quantity'] . "\t\t" .
					str_replace("-", "", $fflRow['license_number']) . "\t" . (empty($fflRow['mailing_address_preferred']) ? "M" : "P") . "\t" . $ordersRow['full_name'] . "\t" . $ordersRow['phone_number'] . "\n";
			}
		}

		$customerFileContent = "";
		if (!empty($customerOrderItemRows)) {
			foreach ($customerOrderItemRows as $thisItemRow) {
				$customerFileContent .= "\t" . $orderId . "-%remote_order_id%\t" . $ordersRow['full_name'] . "\t" .
					$addressRow['address_1'] . "\t" . $addressRow['address_2'] . "\t" . $addressRow['city'] . "\t" . $addressRow['state'] . "\t" . substr($addressRow['postal_code'], 0, 5) . "\t" .
					$thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['quantity'] . "\t\t" .
					"\t\t" . $ordersRow['full_name'] . "\t" . $ordersRow['phone_number'] . "\n";
			}
		}

		$dealerFileContent = "";
		if (!empty($dealerOrderItemRows)) {
			$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));

			foreach ($dealerOrderItemRows as $thisItemRow) {
				$dealerFileContent .= "\t" . $orderId . "-%remote_order_id%\t" . $dealerName . "\t" .
					$this->iLocationContactRow['address_1'] . "\t" . $this->iLocationContactRow['address_2'] . "\t" . $this->iLocationContactRow['city'] . "\t" . $this->iLocationContactRow['state'] . "\t" . substr($this->iLocationContactRow['postal_code'], 0, 5) . "\t" .
					$thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['quantity'] . "\t\t" .
					"\t\t" . $ordersRow['full_name'] . "\t" . $ordersRow['phone_number'] . "\n";
			}
		}

		$returnValues = array();

# Submit the orders

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
			$uploadResponse = ftpFilePutContents("ftp.hicksinc.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				"th", $orderId . "-" . $remoteOrderId . ".txt", $fflFileContent);
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
					ftpFilePutContents("ftp.hicksinc.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
						"th/fflonly", str_replace("-", "", $fflRow['license_number']) . "." . $extension, $fflLicenseFileContent);
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
			$uploadResponse = ftpFilePutContents("ftp.hicksinc.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				"th", $orderId . "-" . $remoteOrderId . ".txt", $customerFileContent);
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
			$uploadResponse = ftpFilePutContents("ftp.hicksinc.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				"th", $orderId . "-" . $remoteOrderId . ".txt", $dealerFileContent);
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
			$this->iErrorMessage = $resultSet['sql_error'];
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}
		$distributorOrderId = $resultSet['insert_id'];
		$orderPrefix = getPreference("distributor_order_prefix");

# Get Location ID

		$locationId = $this->iLocationId;

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

# Process products to be ordered

		$dealerFileContent = "";
		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));

		foreach ($dealerOrderItemRows as $thisItemRow) {
			$dealerFileContent .= "\t" . $orderPrefix . $distributorOrderId . "\t" . $dealerName . "\t" .
				$this->iLocationContactRow['address_1'] . "\t" . $this->iLocationContactRow['address_2'] . "\t" . $this->iLocationContactRow['city'] . "\t" . $this->iLocationContactRow['state'] . "\t" . substr($this->iLocationContactRow['postal_code'], 0, 5) . "\t" .
				$thisItemRow['distributor_product_code'] . "\t" . $thisItemRow['quantity'] . "\t\t\t\t\t\n";
		}

		$returnValues = array();

# Submit the orders

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}
		$uploadResponse = ftpFilePutContents("ftp.hicksinc.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
			"th", $orderPrefix . $distributorOrderId . ".txt", $dealerFileContent);
		if ($uploadResponse !== true) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = $uploadResponse;
			return false;
		} else {
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $distributorOrderId);
		}

		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		if ($this->iTrackingAlreadyRun) {
			$this->iErrorMessage = "";
			return false;
		}
		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, "fh")) {
			$this->iErrorMessage = "fh directory does not exist";
			$this->iTrackingAlreadyRun = true;
			return false;
		}
		if (!ftp_chdir($this->iConnection, "tracking")) {
			$this->iErrorMessage = "tracking directory does not exist";
			$this->iTrackingAlreadyRun = true;
			return false;
		}
		$returnValues = array();
		$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $tempFilename, "tracking.tab", FTP_ASCII)) {
			$this->iErrorMessage = "Cannot Get File";
			$this->iTrackingAlreadyRun = true;
			return false;
		}
		$fileLines = getContentLines(file_get_contents($tempFilename));
		unlink($tempFilename);
		foreach ($fileLines as $thisLine) {
			$thisRecord = explode("\t", $thisLine);
			$orderIdParts = explode("-", $thisRecord[0]);
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
			if (!empty($thisRecord[4])) {
				executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $thisRecord[4], $remoteOrderId);
			}
			$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "remote_order_id", $remoteOrderId, "tracking_identifier is null");
			if (empty($orderShipmentId)) {
				continue;
			}
			$upsShippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($thisRecord[1]));
			$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
				$thisRecord[2], $upsShippingCarrierId, $thisRecord[1], $orderShipmentId);
			if ($resultSet['affected_rows'] > 0) {
				Order::sendTrackingEmail($orderShipmentId);
				executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
					$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $thisRecord[2],
					"Tracking number added by " . $this->iProductDistributorRow['description']);
				$returnValues[] = $orderShipmentId;
			}
		}

		$this->iTrackingAlreadyRun = true;
		if (!ftp_chdir($this->iConnection, "../problems")) {
			$this->iErrorMessage = "problems directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		$problemFiles = array();
		foreach ($fileList as $thisFile) {
			if (strpos($thisFile, ".txt") !== false) {
				$problemFiles[] = $thisFile;
			}
		}
		$shipmentErrorStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "SHIPMENT_ERROR", "inactive = 0");
		if (empty($shipmentErrorStatusId)) {
			$insertSet = executeQuery("insert ignore into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,'SHIPMENT_ERROR','Shipment Error','#FF0000',1)", $GLOBALS['gClientId']);
			$shipmentErrorStatusId = $insertSet['insert_id'];
		}
		$fcaUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
		foreach ($problemFiles as $thisFile) {
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
			if (array_key_exists(md5($thisFile), $GLOBALS['gProductDistributorIssues'][$this->iProductDistributorRow['product_distributor_id']])) {
				continue;
			}
			executeQuery("insert into product_distributor_issues (product_distributor_id,hash_code,content) values (?,?,?)", $this->iProductDistributorRow['product_distributor_id'], md5($thisFile), $thisFile);

			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/hicksinc-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = explode("\t", file_get_contents($tempFilename));

			ftp_delete($this->iConnection, $thisFile);
			unlink($tempFilename);
			$orderParts = explode("-", $fileLines[2]);
			$orderId = getFieldFromId("order_id", "orders", "order_id", $orderParts[0], "date_completed is null");
			if (empty($orderId)) {
				continue;
			}
			$remoteOrderId = $orderParts[1];
			$errorMessage = $fileLines[22];
			if (empty($remoteOrderId)) {
				$distributorOrderId = getFieldFromId("distributor_order_id", "distributor_orders", "distributor_order_id", $orderId, "requires_attention = 0");
				if (!empty($distributorOrderId)) {
					executeQuery("update distributor_orders set requires_attention = 1 where distributor_order_id = ?", $distributorOrderId);
					sendEmail(array("subject" => "Hicks, Inc Order failed", "body" => "<p>Distributor Order ID# " . $distributorOrderId . " from Hicks, Inc " .
						"failed. This order will need to be replaced. The error message from Hicks, Inc is:</p>" .
						$errorMessage, "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
				}
			} else {
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId, "remote_order_id = ? and tracking_identifier is null", $remoteOrderId);
				if (empty($orderShipmentId)) {
					continue;
				}
				$orderStatusId = getFieldFromId("order_status_id", "orders", "order_id", $orderId);
				if ($orderStatusId == $shipmentErrorStatusId) {
					continue;
				}
				Order::updateOrderStatus($orderId, $shipmentErrorStatusId);
				if (!empty($fcaUserId)) {
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, "From Hicks, Inc by background process: " . $errorMessage);
				}
				executeQuery("delete from order_shipment_items where order_shipment_id = ?", $orderShipmentId);
				executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				sendEmail(array("subject" => "Hicks, Inc Order failed", "body" => "<p>The shipment for Order ID# " . $orderId . " from Hicks, Inc " .
					"failed and has been removed. This shipment will need to be replaced. The error message from Hicks, Inc is:</p>" .
					$errorMessage, "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
			}
		}

		return $returnValues;
	}
}
