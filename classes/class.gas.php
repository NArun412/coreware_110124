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

class Gas extends ProductDistributor {

	private $iConnection;
	private $iFieldTranslations = array("upc_code" => "upc_or_ean_id", "product_code" => "item_id", "description" => "product_name", "detailed_description" => "extended_description", "manufacturer_code" => "manufacturer", "model" => "xxxxx",
		"manufacturer_sku" => "manufacturer_item_id", "manufacturer_advertised_price" => "retail_map", "width" => "width", "length" => "length", "height" => "height", "weight" => "weight", "base_cost" => "cost", "list_price" => "msrp",
		"drop_ship_flag" => "drop_ship_flag", "image_location" => "images", "category" => "categories", "quantity" => "cam_warehouse_qty_available");
	// Other GAS catalog fields:
	// catalog_standard.txt
	// - (item_id may have a header of "1" instead of item_id)
	// - short_description - same as product_name
	// - stock_status - S for stockable, N for nonstock (special order), C for closeout (only available if in stock)
	// - class_id2 - stock_status in word form (does not always match)
	// catalog_dropship.csv (only a subset of products in catalog_standard.txt)
	// - warehouse_1_qty
	// - warehouse_2_qty
	// - sales_pricing_unit - EA, etc.
	private $iTrackingAlreadyRun = false;
    private $iLogging;

	function __construct($locationId) {
		$this->iProductDistributorCode = "GAS";
		parent::__construct($locationId);
        $this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_GAS"));
        $this->getFirearmsProductTags();
	}

	private static function sortFileModified($a, $b) {
		$aIsFile = $a['type'] == "file";
		$bIsFile = $b['type'] == "file";
		if (!$aIsFile && !$bIsFile) {
			return 0;
		}
		if (!$aIsFile && $bIsFile) {
			return -1;
		}
		if ($aIsFile && !$bIsFile) {
			return 1;
		}
		return ($a['modify'] > $b['modify']) ? 1 : -1;
	}

	function testCredentials() {
		return $this->connect();
	}

	function connect() {
		$this->iConnection = ftp_connect("ftp.gunaccessorysupply.com", 21, 600);
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
		$productArray = $this->getProducts();
		if (!$productArray) {
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

		foreach ($productArray as $thisProductInfo) {
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

					$imageId = $imageContents = "";
					$imageUrl = str_replace("http:", "https:", $thisProductInfo['link_to_image']);
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $productRow['product_code'] . ".jpg", "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "GAS_IMAGE"));
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
		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getProducts() {
		$productArray = getCachedData("gas_product_feed", "");

		if (empty($productArray)) {

			if (!$this->connect()) {
				return false;
			}

			if (!ftp_chdir($this->iConnection, "standard")) {
				$this->iErrorMessage = "standard directory does not exist";
				return false;
			}
			$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-standard-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $catalogFilename, "catalog_standard.txt", FTP_ASCII)) {
				$this->iErrorMessage = "Standard Catalog file cannot be downloaded";
				return false;
			}
			$dropshipFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-dropship-" . getRandomString(6) . ".csv";
			if (!ftp_get($this->iConnection, $dropshipFilename, "catalog_dropship.csv", FTP_ASCII)) {
				$this->iErrorMessage = "Dropship Catalog file cannot be downloaded";
				return false;
			}

			// Dropship file is smaller than standard file; load to array
			$openFile = fopen($dropshipFilename, "r");
			$fieldNames = array('item_id', 'brand_name', 'short_code', 'upc', 'weight', 'length', 'width', 'height', 'category', 'item_desc',
				'msrp', 'map', 'warehouse_1_qty', 'warehouse_2_qty', 'sales_pricing_unit', 'cost');

			$dropshipArray = array();
			$firstLine = true;
			while ($csvData = fgetcsv($openFile)) {
				if ($firstLine) {
					$firstLine = false;
					continue;
				}
				$thisProductInfo = array();
				foreach ($fieldNames as $index => $fieldName) {
					$thisProductInfo[$fieldName] = trim($csvData[$index]);
				}
				$dropshipArray[$thisProductInfo[$this->iFieldTranslations['product_code']]] = $thisProductInfo;
			}

			$openFile = fopen($catalogFilename, "r");
			$count = 0;
			$fieldNames = array('item_id', 'manufacturer_item_id', 'upc_or_ean_id', 'manufacturer', 'product_name', 'short_description', 'extended_description',
				'images', 'weight', 'length', 'width', 'height', 'categories', 'retail_map', 'msrp', 'stock_status', 'class_id2');
			while ($csvData = fgetcsv($openFile, 0, "\t")) {
				$count++;
				if ($count == 1) {
					continue;
				}
				$thisProductInfo = array();
				foreach ($fieldNames as $index => $fieldName) {
					$thisProductInfo[$fieldName] = trim($csvData[$index]);
				}
				$thisCode = $thisProductInfo[$this->iFieldTranslations['product_code']];
				if (empty($thisCode)) {
					continue;
				}

				if (array_key_exists($thisCode, $dropshipArray)) {
					$thisProductInfo = array_merge($thisProductInfo, $dropshipArray[$thisCode]);
					$thisProductInfo['drop_ship_flag'] = 1;
				} else {
					$thisProductInfo['drop_ship_flag'] = 0;
				}

				$productArray[$thisCode] = $thisProductInfo;
			}
			fclose($openFile);

			setCachedData("gas_product_feed", "", $productArray, 1);
		}

		return $productArray;
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

		if (!ftp_chdir($this->iConnection, "standard")) {
			$this->iErrorMessage = "standard directory does not exist";
			return false;
		}
		$inventoryFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-inventory-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $inventoryFilename, "inventory_standard.csv", FTP_ASCII)) {
			$this->iErrorMessage = "inventory_standard file cannot be downloaded";
			return false;
		}
		$costFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-cost-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $costFilename, "cost_standard.csv", FTP_ASCII)) {
			$this->iErrorMessage = "cost_standard file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);

		$openFile = fopen($inventoryFilename, "r");
		$fieldNames = fgetcsv($openFile);
		$inventoryArray = array();
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			$inventoryArray[$thisProductInfo['item_id']] = $thisProductInfo;
		}
		fclose($openFile);
		unlink($inventoryFilename);
		$openFile = fopen($costFilename, "r");
		$fieldNames = fgetcsv($openFile);
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if (is_array($inventoryArray[$thisProductInfo['item_id']])) {
				$inventoryArray[$thisProductInfo['item_id']] = array_merge($inventoryArray[$thisProductInfo['item_id']], $thisProductInfo);
			} else {
				$inventoryArray[$thisProductInfo['item_id']] = $thisProductInfo;
			}
		}
		fclose($openFile);
		unlink($costFilename);

		foreach ($inventoryArray as $thisProductInfo) {
			if ($thisProductInfo['item_id'] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['qty_available'];
			$cost = $thisProductInfo['cost'];
			break;
		}

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		if (!ftp_chdir($this->iConnection, "standard")) {
			$this->iErrorMessage = "standard directory does not exist";
			return false;
		}
		$inventoryFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-inventory-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $inventoryFilename, "inventory_standard.csv", FTP_ASCII)) {
			$this->iErrorMessage = "inventory_standard file cannot be downloaded";
			return false;
		}
		$costFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-cost-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $costFilename, "cost_standard.csv", FTP_ASCII)) {
			$this->iErrorMessage = "cost_standard file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		$openFile = fopen($inventoryFilename, "r");
		$fieldNames = fgetcsv($openFile);
		$inventoryArray = array();
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			$inventoryArray[$thisProductInfo['item_id']] = $thisProductInfo;
		}
		fclose($openFile);
		unlink($inventoryFilename);
		$openFile = fopen($costFilename, "r");
		$fieldNames = fgetcsv($openFile);
		$inventoryUpdateArray = array();
		while ($csvData = fgetcsv($openFile)) {
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if (is_array($inventoryArray[$thisProductInfo['item_id']])) {
				$inventoryArray[$thisProductInfo['item_id']] = array_merge($inventoryArray[$thisProductInfo['item_id']], $thisProductInfo);
			} else {
				$inventoryArray[$thisProductInfo['item_id']] = $thisProductInfo;
			}
		}
		fclose($openFile);
		unlink($costFilename);
		foreach ($inventoryArray as $thisProductInfo) {
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['item_id'],
				"quantity" => $thisProductInfo['qty_available'],
				"cost" => $thisProductInfo['cost']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		$productArray = $this->getProducts();
		if ($productArray === false) {
			return false;
		}

		$productManufacturers = array();
		foreach ($productArray as $thisProductInfo) {
			if (!empty($thisProductInfo['manufacturer'])) {
				$productManufacturers[makeCode($thisProductInfo['manufacturer'])] = array("business_name" => $thisProductInfo['manufacturer']);
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
		$productArray = $this->getProducts();
		if ($productArray === false) {
			return false;
		}

		$productCategories = array();
		foreach ($productArray as $thisProductInfo) {
			if (!empty($thisProductInfo[$this->iFieldTranslations['category']])) {
				$productCategories[makeCode($thisProductInfo[$this->iFieldTranslations['category']])] = array("description" => $thisProductInfo[$this->iFieldTranslations['category']]);
			}
		}
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
		// GAS does not carry firearms, so no FFL orders
		$customerOrderItemRows = $orderParts['customer_order_items'];
		$dealerOrderItemRows = array_merge($orderParts['dealer_order_items'], $orderParts['ffl_order_items']);

		$fileHeaders = array("order_no", "customer_id", "ship_to_id", "ship_to_name", "ship_to_address1", "ship_to_address2", "ship_to_city", "ship_to_state", "ship_to_zip", "ship_method", "item_id", "price", "qty", "shipping_instructions");

        $customerFileContent = "";
		if (!empty($customerOrderItemRows)) {
            $customerFileContent = createCsvRow($fileHeaders);
			foreach ($customerOrderItemRows as $thisItemRow) {
				$customerFileContent .= createCsvRow(array($orderId . "-%remote_order_id%",
					$this->iLocationCredentialRow['user_name'],
					(empty($this->iLocationCredentialRow['customer_number']) ? $this->iLocationCredentialRow['user_name'] : $this->iLocationCredentialRow['customer_number']),
					substr($ordersRow['full_name'], 0, 40),
					substr($addressRow['address_1'], 0, 40),
					substr($addressRow['address_2'], 0, 40),
					substr($addressRow['city'], 0, 25),
					$addressRow['state'],
					substr($addressRow['postal_code'], 0, 5),
					"GRNDF",
					$thisItemRow['distributor_product_code'],
					$thisItemRow['base_cost'],
					$thisItemRow['quantity'],
					""));
			}
		}

        $dealerFileContent = "";
        if (!empty($dealerOrderItemRows)) {
            $dealerFileContent = createCsvRow($fileHeaders);
            $dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));

			foreach ($dealerOrderItemRows as $thisItemRow) {
				$dealerFileContent .= createCsvRow(array($orderId . "-%remote_order_id%",
					$this->iLocationCredentialRow['user_name'],
					(empty($this->iLocationCredentialRow['customer_number']) ? $this->iLocationCredentialRow['user_name'] : $this->iLocationCredentialRow['customer_number']),
					substr($dealerName, 0, 40),
					substr($this->iLocationContactRow['address_1'], 0, 40),
					substr($this->iLocationContactRow['address_2'], 0, 40),
					substr($this->iLocationContactRow['city'], 0, 25),
					$this->iLocationContactRow['state'],
					substr($this->iLocationContactRow['postal_code'], 0, 5),
					"GRND",
					$thisItemRow['distributor_product_code'],
					$thisItemRow['base_cost'],
					$thisItemRow['quantity'],
					""));
			}
		}

		$returnValues = array();

# Submit the orders

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
			$uploadResponse = ftpFilePutContents("ftp.gunaccessorysupply.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				"orders", "order-" . $orderId . "-" . $remoteOrderId . ".csv", $customerFileContent);

            if($this->iLogging) {
                addDebugLog("GAS dropship order upload: ftp.gunaccessorysupply.com/orders"
                    . "\nGAS Username: " . $this->iLocationCredentialRow['user_name']
                    . "\nGAS Filename: " . "order-" . $orderId . "-" . $remoteOrderId . ".csv"
                    . "\nGAS Data: " . getFirstPart($customerFileContent, 5000)
                    . "\nGAS Result: " . jsonEncode($uploadResponse));
            }
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
			$uploadResponse = ftpFilePutContents("ftp.gunaccessorysupply.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				"orders", "order-" . $orderId . "-" . $remoteOrderId . ".csv", $dealerFileContent);
            if($this->iLogging) {
                addDebugLog("GAS dealer order upload: ftp.gunaccessorysupply.com/orders"
                    . "\nGAS Username: " . $this->iLocationCredentialRow['user_name']
                    . "\nGAS Filename: " . "order-" . $orderId . "-" . $remoteOrderId . ".csv"
                    . "\nGAS Data: " . getFirstPart($dealerFileContent, 5000)
                    . "\nGAS Result: " . jsonEncode($uploadResponse));
            }
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

		$fileHeaders = array("order_no", "customer_id", "ship_to_id", "ship_to_name", "ship_to_address1", "ship_to_address2", "ship_to_city", "ship_to_state", "ship_to_zip", "ship_method", "item_id", "price", "qty", "shipping_instructions");

		$dealerFileContent = createCsvRow($fileHeaders);
		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));

		foreach ($dealerOrderItemRows as $thisItemRow) {
			$dealerFileContent .= createCsvRow(array($orderPrefix . $distributorOrderId,
				$this->iLocationCredentialRow['user_name'],
				(empty($this->iLocationCredentialRow['customer_number']) ? $this->iLocationCredentialRow['user_name'] : $this->iLocationCredentialRow['customer_number']),
				substr($dealerName, 0, 40),
				substr($this->iLocationContactRow['address_1'], 0, 40),
				substr($this->iLocationContactRow['address_2'], 0, 40),
				substr($this->iLocationContactRow['city'], 0, 25),
				$this->iLocationContactRow['state'],
				substr($this->iLocationContactRow['postal_code'], 0, 5),
				"GRND",
				$thisItemRow['distributor_product_code'],
				$thisItemRow['base_cost'],
				$thisItemRow['quantity'],
				""));
		}

		$returnValues = array();

# Submit the orders

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}

		$uploadResponse = ftpFilePutContents("ftp.gunaccessorysupply.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
			"orders", "order-" . $orderPrefix . $distributorOrderId . ".csv", $dealerFileContent);
        if($this->iLogging) {
            addDebugLog("GAS distributor order upload: ftp.gunaccessorysupply.com/orders"
                . "\nGAS Username: " . $this->iLocationCredentialRow['user_name']
                . "\nGAS Filename: " . "order-" . $orderPrefix . $distributorOrderId . ".csv"
                . "\nGAS Data: " . getFirstPart($dealerFileContent, 5000)
                . "\nGAS Result: " . jsonEncode($uploadResponse));
        }
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

		if (!ftp_chdir($this->iConnection, "tracking")) {
			$this->iErrorMessage = "tracking directory does not exist";
			$this->iTrackingAlreadyRun = true;
			return false;
		}

		$remoteFileList = ftp_mlsd($this->iConnection, ".");
		usort($remoteFileList, array(static::class, "sortFileModified"));
		$latestFilename = array_pop($remoteFileList)['name'];

		$returnValues = array();
		$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-tracking-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $tempFilename, $latestFilename, FTP_ASCII)) {
			$this->iErrorMessage = "Cannot Get File";
			$this->iTrackingAlreadyRun = true;
			return false;
		}
		$openFile = fopen($tempFilename, "r");
		$headers = fgetcsv($openFile);
		$lines = array();
		while ($line = fgetcsv($openFile)) {
			$lines[] = array_combine($headers, $line);
		}
		fclose($openFile);
		unlink($tempFilename);
		foreach ($lines as $thisLine) {
			$orderIdParts = explode("-", $thisLine['po_no']);
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
			$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "remote_order_id", $remoteOrderId, "tracking_identifier is null");
			if (empty($orderShipmentId)) {
				continue;
			}
			$upsShippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", "UPS");
			$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
				$thisLine['tracking_no'], $upsShippingCarrierId, "UPS", $orderShipmentId);
			if ($resultSet['affected_rows'] > 0) {
				Order::sendTrackingEmail($orderShipmentId);
				executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
					$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $thisLine['tracking_no'],
					"Tracking number added by " . $this->iProductDistributorRow['description']);
				$returnValues[] = $orderShipmentId;
			}
		}

		$this->iTrackingAlreadyRun = true;
		ftp_chdir($this->iConnection, "../orders");
		if (!ftp_chdir($this->iConnection, "error")) {
			$this->iErrorMessage = "error directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		$problemFiles = array();
		foreach ($fileList as $thisFile) {
			if (strpos($thisFile, ".csv") !== false) {
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
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/gas-" . getRandomString(6) . ".csv";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}

			$openFile = fopen($tempFilename, "r");
			$headers = fgetcsv($openFile);
			$fileLines = array();
			while ($line = fgetcsv($openFile)) {
				$fileLines[] = array_combine($headers, $line);
			}
			fclose($openFile);
			unlink($tempFilename);

			ftp_delete($this->iConnection, $thisFile);
			foreach ($fileLines as $line) {
				$orderParts = explode("-", $line['order_no']);
				if (empty($orderId)) {
					continue;
				}
				$remoteOrderId = $orderParts[1];
				$errorMessage = "Order failed: " . jsonEncode($line);
				if (empty($remoteOrderId)) {
					$distributorOrderId = getFieldFromId("distributor_order_id", "distributor_orders", "distributor_order_id", $orderId, "requires_attention = 0");
					if (!empty($distributorOrderId)) {
						executeQuery("update distributor_orders set requires_attention = 1 where distributor_order_id = ?", $distributorOrderId);
						sendEmail(array("subject" => "GAS Order failed", "body" => "<p>Distributor Order ID# " . $distributorOrderId . " from GAS " .
							"failed. This order will need to be replaced. The contents of the GAS error log (which might give some idea why there shipment failed) are:</p>" .
							jsonEncode($line), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
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
						executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, "From GAS by background process: " . $errorMessage);
					}
					executeQuery("delete from order_shipment_items where order_shipment_id = ?", $orderShipmentId);
					executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
					executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					sendEmail(array("subject" => "GAS Order failed", "body" => "<p>The shipment for Order ID# " . $orderId . " from GAS " .
						"failed. This order will need to be replaced. The contents of the GAS error log (which might give some idea why there shipment failed) are:</p>" .
						jsonEncode($line), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
				}
			}
		}

		return $returnValues;
	}
}
