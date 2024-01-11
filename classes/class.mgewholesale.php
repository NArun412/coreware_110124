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

class MGEWholesale extends ProductDistributor {

	private $iConnection;
	private $iFieldTranslations = array("upc_code" => "barcod", "product_code" => "id", "description" => "name", "alternate_description" => "XXXXXX",
		"detailed_description" => "description", "manufacturer_code" => "manufacturer_id", "model" => "model", "manufacturer_sku" => "XXXXXX", "manufacturer_advertised_price" => "map",
		"width" => "XXXXXX", "length" => "XXXXXX", "height" => "XXXXXX", "weight" => "XXXXXX", "base_cost" => "price");
	public $iTrackingAlreadyRun = false;

	/**
	 * MGEWholesale constructor.
	 * The constructor sets the product distributor code and initializes the location.
	 *
	 * @param $locationId
	 */
	function __construct($locationId) {
		$this->iProductDistributorCode = "MGE_WHOLESALE";
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	/**
	 * Connect to the MGE FTP server
	 *
	 * @return bool
	 */
	function connect() {
//		$this->iConnection = ftp_connect("ftp.mgegroup.com",21,600);
		$this->iConnection = ftp_ssl_connect("ftp.mgegroup.com", 21, 600);
		if (!$this->iConnection) {
			return false;
		}
		if (!ftp_login($this->iConnection, $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'])) {
			$this->iErrorMessage = "Invalid Login";
			return false;
		}
		ftp_set_option($this->iConnection, FTP_USEPASVADDRESS, false);
		ftp_pasv($this->iConnection, true);
		return true;
	}

	/**
	 * Check to see if the connection is successful
	 *
	 * @return bool
	 */
	function testCredentials() {
		return $this->connect();
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

		if (!ftp_chdir($this->iConnection, "FFLfeeds")) {
			$this->iErrorMessage = "FFLfeeds directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/mgewholesale-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "vendorname_items.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}

		if (!ftp_chdir($this->iConnection, "../photos")) {
			$this->iErrorMessage = "Images directory does not exist";
			return false;
		}
		$imageFileList = ftp_nlist($this->iConnection, ".");
		$imageFiles = array();
		foreach ($imageFileList as $thisImageFile) {
			$imageParts = explode("_", trim($thisImageFile, "_"));
			if (count($imageParts) != 2) {
				continue;
			}
			$imageProductCode = makeCode($imageParts[0]);
			if (!array_key_exists($imageProductCode, $imageFiles)) {
				$imageFiles[$imageProductCode] = array();
			}
			$imageFiles[$imageProductCode][] = $thisImageFile;
		}

		$insertCount = 0;
		$productFacetArray = $this->getFacets();

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

			$facetValues = $this->processFacets($thisProductInfo['attributes']);
			unset($facetValues['brand']);

			if (!empty($productId) && array_key_exists($productId, $productIdsProcessed)) {
				$duplicateProductCount++;
				continue;
			}

			if (empty($productId)) {

# Create new product

				$productCode = makeCode($thisProductInfo[$this->iFieldTranslations['product_code']]);
				$description = $corewareProductData['description'];

				if (empty($description)) {
					$description = (empty($thisProductInfo[$this->iFieldTranslations['description']]) ? $thisProductInfo[$this->iFieldTranslations['alternate_description']] : $thisProductInfo[$this->iFieldTranslations['description']]);
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
				if ($thisProductInfo['category'] == "Firearms") {
					$corewareProductData['serializable'] = 1;
				}
				if ($thisProductInfo['category'] == "NFA - Class 3") {
					$corewareProductData['serializable'] = 1;
				}
			}
			if ($thisProductInfo['category'] == "Firearms") {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}
			if ($thisProductInfo['category'] == "NFA - Class 3") {
				$this->addProductTag($productId, $this->iClass3ProductTagId);
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

					$primaryImageId = "";
					$secondaryImageIds = array();
					$checkImageCode = $thisProductInfo[$this->iFieldTranslations['product_code']];
					if (array_key_exists($checkImageCode, $imageFiles)) {
						foreach ($imageFiles[$checkImageCode] as $thisImageFilename) {
							$localImageFilename = $GLOBALS['gDocumentRoot'] . "/cache/mge-" . getRandomString(6) . ".jpg";
							$imageContents = "";
							if (!ftp_pwd($this->iConnection)) {
								$this->connect();
								ftp_chdir($this->iConnection, "/photos");
							}
							if (ftp_get($this->iConnection, $localImageFilename, $thisImageFilename,FTP_BINARY)) {
								$imageContents = file_get_contents($localImageFilename);
								unlink($localImageFilename);
							}
							if (!empty($imageContents)) {
								$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $thisImageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "MGE_IMAGE"));
								if (!empty($imageId)) {
									SimpleImage::reduceImageSize($imageId, array("compression" => 60, "max_image_dimension" => 1600, "convert" => true));
									$imageCount++;
									if (empty($primaryImageId)) {
										$primaryImageId = $imageId;
									} else {
										$secondaryImageIds[] = $imageId;
									}
								}
							}
						}
					}
					if (!empty($primaryImageId)) {
						$imageId = $primaryImageId;
						$this->updateProductField($productId, "image_id", $imageId);
						$productImageId = getFieldFromId("product_image_id", "product_images", "product_id", $productId);
						$imageCount++;
						if (empty($productImageId) && !empty($secondaryImageIds)) {
							foreach ($secondaryImageIds as $imageId) {
								executeQuery("insert into product_images (product_id,image_id,description) values (?,?,'Secondary Image')", $productId, $imageId);
								$imageCount++;
							}
						}
						ProductCatalog::createProductImageFiles($productId, $primaryImageId);
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
				if (is_numeric($thisProductInfo[$this->iFieldTranslations['manufacturer_advertised_price']]) && $thisProductInfo[$this->iFieldTranslations['manufacturer_advertised_price']] > $thisProductInfo[$this->iFieldTranslations['base_cost']]) {
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

			unset($facetValues['model']);

			if (self::$iCorewareShootingSports && !$productCategoryAdded && !empty($thisProductInfo['subcategory'])) {
				$categoryCode = makeCode($thisProductInfo['subcategory']);
				$this->addProductCategories($productId, array($categoryCode));
				$productFacetValues = array();
				foreach ($facetValues as $productFacetCode => $facetValue) {
					if (!empty($facetValue) && array_key_exists($productFacetCode, $productFacetArray[$categoryCode])) {
						$productFacetValues[] = array("product_facet_code" => $productFacetCode, "facet_value" => $facetValue);
					}
				}
				$this->addProductFacets($productId, $productFacetValues);
			}
		}
		fclose($openFile);
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function syncInventory($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		if (!ftp_chdir($this->iConnection, "FFLfeeds")) {
			$this->iErrorMessage = "FFLfeeds directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/mge-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "vendorname_cq.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		$inventoryUpdateArray = array();
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
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['id'],
				"quantity" => $thisProductInfo['qty'],
				"cost" => $thisProductInfo['cost']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		fclose($openFile);
		unlink($catalogFilename);
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	/**
	 * Return an array of manufacturers. The key of the array is the ID or code used by the distributor. The value of the array is an array, containing the following:
	 * business_name - Description of the company. This is the only value required
	 * web_page - manufacturer website
	 * image_url - URL of the manufacturer logo
	 *
	 * @param array $parameters
	 * @return array|bool
	 */
	function getManufacturers($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, "ffldealer")) {
			$this->iErrorMessage = "ffldealer directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/mgewholesale-" . getRandomString(6) . ".xml";
		if (!ftp_get($this->iConnection, $catalogFilename, "vendorname_manufacturers.xml", FTP_ASCII)) {
			$this->iErrorMessage = "Manufacturer file cannot be downloaded";
			return false;
		}
		$fileContents = file_get_contents($catalogFilename);
		$fileContents = trim(str_replace("<?xml version=\"1.0\"?>", "", $fileContents), " \r\n\t\0");
		$fileContents = trim(str_replace("\r\n", "\r", $fileContents), " \r\n\t\0");

		$GLOBALS['gIgnoreError'] = true;
		$responseArray = simplexml_load_string($fileContents, "SimpleXMLElement", LIBXML_NOWARNING);
		$responseArray = processXml($responseArray);
		$GLOBALS['gIgnoreError'] = false;

		if (is_array($responseArray) && array_key_exists("xml", $responseArray)) {
			$responseArray = $responseArray['xml'];
		}

		$productManufacturers = array();
		foreach ($responseArray['vendorname_manufacturers']['type'] as $manufacturerInfo) {
			$productManufacturers[$manufacturerInfo['manufacturer_id']] = array("business_name" => ucwords(strtolower($manufacturerInfo['manufacturer_name'])));
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

	/**
	 * Get categories used by this distributor. Not every distributor even supplied categories, but if they do, this function should return an array where the key of the array entry is the "code"
	 * used by the distributor and the value of the entry is an array with containing the description of the category.
	 *
	 * @param array $parameters
	 * @return array|bool
	 */
	function getCategories($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, "ffldealer")) {
			$this->iErrorMessage = "ffldealer directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/mgewholesale-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "vendorname_items.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}
		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		$productCategories = array();
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
			$categoryCode = makeCode($thisProductInfo['subcategory']);
			if (!array_key_exists($categoryCode, $productCategories)) {
				$productCategories[$categoryCode] = array("description" => ucwords(str_replace("_", " ", $thisProductInfo['subcategory'])));
			}
		}
		uasort($productCategories, array($this, "sortCategories"));
		unlink($catalogFilename);
		return $productCategories;
	}

	function sortCategories($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}

	/**
	 * Facets are always within a category. If the distributor has categories, then it might also have facets. Facets are things like barrel length, color, caliber
	 *
	 * This function returns a multi-level array where the top level is the category code, the next level is the facet code with value of the facet description
	 *
	 * @param array $parameters
	 * @return array|bool
	 */
	function getFacets($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, "ffldealer")) {
			$this->iErrorMessage = "ffldealer directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/mgewholesale-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "vendorname_items.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}
		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		$productFacets = array();
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
			$productCategoryCode = makeCode($thisProductInfo['subcategory']);
			if (!array_key_exists($productCategoryCode, $productFacets)) {
				$productFacets[$productCategoryCode] = array();
			}
			$facetValues = $this->processFacets($thisProductInfo['attributes']);
			foreach ($facetValues as $facet => $facetValue) {
				$facetCode = makeCode($facet);
				if (!array_key_exists($facetCode, $productFacets[$productCategoryCode])) {
					$productFacets[$productCategoryCode][$facetCode] = ucwords(str_replace("_", " ", $facet));
				}
			}
		}
		unlink($catalogFilename);
		return $productFacets;
	}

	function processFacets($facetString) {
		$parts = explode(";", $facetString);
		$facetValues = array();
		foreach ($parts as $thisFacet) {
			$thisFacet = trim($thisFacet);
			$facetParts = explode("=", $thisFacet);
			if (count($facetParts) == 2) {
				$facetValues[$facetParts[0]] = $facetParts[1];
			}
		}
		return $facetValues;
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
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}
				$specialInstructions = "Order ID: " . $orderId . "-%remote_order_id%, Customer: " . $ordersRow['full_name'] . ", Customer Phone: " . $ordersRow['phone_number'];
				$fflFileContent .= '"' . $this->iLocationCredentialRow['user_name'] . '",' .
					'"' . str_replace('"', "", $GLOBALS['gClientName']) . '",' .
					'"' . str_replace('"', "", (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name'])) . '",' .
					'"' . str_replace('"', "", $fflRow[$addressPrefix . 'address_1'] . (empty($fflRow[$addressPrefix . "address_2"]) ? "" : ", " . $fflRow[$addressPrefix . "address_2"])) . '",' .
					'"' . str_replace('"', "", $fflRow[$addressPrefix . 'city']) . '",' .
					'"' . str_replace('"', "", $fflRow[$addressPrefix . 'state']) . '",' .
					'"' . str_replace('"', "", substr($fflRow[$addressPrefix . 'postal_code'], 0, 5)) . '",' .
					'"' . str_replace('"', "", $fflRow['email_address']) . '",' .
					'"' . str_replace('"', "", $fflRow['phone_number']) . '",' .
					'"' . str_replace('"', "", $fflRow['license_number']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['distributor_product_code']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['product_row']['description']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['product_row']['upc_code']) . '",' .
					'"' . $thisItemRow['quantity'] . '",' .
					'"' . $thisItemRow['product_row']['base_cost'] . '",' .
					'"' . $specialInstructions . '"\n';
			}
		}

		$customerFileContent = "";
		if (!empty($customerOrderItemRows)) {
			foreach ($customerOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}
				$specialInstructions = "Order ID: " . $orderId . "-%remote_order_id%, Customer: " . $ordersRow['full_name'] . ", Customer Phone: " . $ordersRow['phone_number'];
				$customerFileContent .= '"' . $this->iLocationCredentialRow['user_name'] . '",' .
					'"' . str_replace('"', "", $GLOBALS['gClientName']) . '",' .
					'"' . str_replace('"', "", $ordersRow['full_name']) . '",' .
					'"' . str_replace('"', "", $addressRow['address_1'] . (empty($addressRow['address_2']) ? "" : ", " . $addressRow['address_2'])) . '",' .
					'"' . str_replace('"', "", $addressRow['city']) . '",' .
					'"' . str_replace('"', "", $addressRow['state']) . '",' .
					'"' . str_replace('"', "", substr($addressRow['postal_code'], 0, 5)) . '",' .
					'"' . str_replace('"', "", $contactRow['email_address']) . '",' .
					'"' . str_replace('"', "", $ordersRow['phone_number']) . '",' .
					'"",' .
					'"' . str_replace('"', "", $thisItemRow['distributor_product_code']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['product_row']['description']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['product_row']['upc_code']) . '",' .
					'"' . $thisItemRow['quantity'] . '",' .
					'"' . $thisItemRow['product_row']['base_cost'] . '",' .
					'"' . $specialInstructions . '"\n';
			}
		}

# Ordering Dealer Number, O. Dealer Name, Ship to Name, Ship To Address, Ship to City, Ship to State, Ship to Zip, Ship to email,
#Ship to phone, Ship to FFL (if Firearm), Item, Item Desc, Item UPC, Item Qty, Item Price, Special Instructions

		$dealerFileContent = "";
		if (!empty($dealerOrderItemRows)) {
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}
				$specialInstructions = "Order ID: " . $orderId . "-%remote_order_id%, Customer: " . $ordersRow['full_name'] . ", Customer Phone: " . $ordersRow['phone_number'];
				$dealerFileContent .= '"' . $this->iLocationCredentialRow['user_name'] . '",' .
					'"' . str_replace('"', "", $GLOBALS['gClientName']) . '",' .
					'"' . str_replace('"', "", $GLOBALS['gClientName']) . '",' .
					'"' . str_replace('"', "", $this->iLocationContactRow['address_1'] . (empty($this->iLocationContactRow['address_2']) ? "" : ", " . $this->iLocationContactRow['address_2'])) . '",' .
					'"' . str_replace('"', "", $this->iLocationContactRow['city']) . '",' .
					'"' . str_replace('"', "", $this->iLocationContactRow['state']) . '",' .
					'"' . str_replace('"', "", substr($this->iLocationContactRow['postal_code'], 0, 5)) . '",' .
					'"' . str_replace('"', "", $this->iLocationContactRow['email_address']) . '",' .
					'"' . str_replace('"', "", $this->iLocationContactRow['phone_number']) . '",' .
					'"",' .
					'"' . str_replace('"', "", $thisItemRow['distributor_product_code']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['product_row']['description']) . '",' .
					'"' . str_replace('"', "", $thisItemRow['product_row']['upc_code']) . '",' .
					'"' . $thisItemRow['quantity'] . '",' .
					'"' . $thisItemRow['product_row']['base_cost'] . '",' .
					'"' . $specialInstructions . '"\n';
			}
		}

		$returnValues = array();

# Submit the orders

		$orderEmailAddress = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
		if (empty($orderEmailAddress)) {
			$this->iErrorMessage = "No Email Address is sent for sending orders";
			return false;
		}
		$fflRow = array();
		if (!empty($fflFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "1234");
			$remoteOrderId = $orderSet['insert_id'];
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			$fflFileContent = str_replace("%remote_order_id%", $remoteOrderId, $fflFileContent);
			foreach ($fflOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$attachments = array();
			$attachments[] = array("attachment_string" => $fflFileContent, "attachment_filename" => $orderId . "-" . $remoteOrderId . ".csv");
			if (!empty($fflRow['file_id'])) {
				$attachments[] = array("attachment_file_id" => $fflRow['file_id']);
			}
			if (!empty($fflRow['sot_file_id'])) {
				$attachments[] = array("attachment_file_id" => $fflRow['sot_file_id']);
			}
			$result = sendEmail(array("body" => "Order from " . $GLOBALS['gClientName'] . " is attached", "subject" => "Order from " . $GLOBALS['gClientName'],
				"attachments" => $attachments, "email_address" => $orderEmailAddress, "cc_addresses" => array($this->iLocationContactRow['email_address'])));
			if ($result !== true) {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['error_message'] .= (empty($returnValues['error_message']) ? "" : ", ") . "Unable to send Email for FFL order: " . $result;
			} else {
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));
			}
		}

		if (!empty($customerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], "1234");
			$remoteOrderId = $orderSet['insert_id'];
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			$customerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $customerFileContent);
			foreach ($customerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$result = sendEmail(array("body" => "Order from " . $GLOBALS['gClientName'] . " is attached", "subject" => "Order from " . $GLOBALS['gClientName'],
				"attachment_string" => $customerFileContent, "attachment_filename" => $orderId . "-" . $remoteOrderId . ".csv", "email_address" => $orderEmailAddress, "cc_addresses" => array($this->iLocationContactRow['email_address'])));
			if ($result !== true) {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['error_message'] .= (empty($returnValues['error_message']) ? "" : ", ") . "Unable to send Email for Customer order: " . $result;
			} else {
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => $ordersRow['full_name']);
			}
		}

		if (!empty($dealerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "1234");
			$remoteOrderId = $orderSet['insert_id'];
			$dealerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $dealerFileContent);
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$result = sendEmail(array("body" => "Order from " . $GLOBALS['gClientName'] . " is attached", "subject" => "Order from " . $GLOBALS['gClientName'],
				"attachment_string" => $dealerFileContent, "attachment_filename" => $orderId . "-" . $remoteOrderId . ".csv", "email_address" => $orderEmailAddress, "cc_addresses" => array($this->iLocationContactRow['email_address'])));
			if ($result !== true) {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['error_message'] .= (empty($returnValues['error_message']) ? "" : ", ") . "Unable to send Email for dealer order: " . $result;
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
			$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
			$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
			for ($x = 0; $x < 10; $x++) {
				$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
			}
			$specialInstructions = "Order ID: " . $orderPrefix . $distributorOrderId;
			$dealerFileContent .= '"' . $this->iLocationCredentialRow['user_name'] . '",' .
				'"' . str_replace('"', "", $dealerName) . '",' .
				'"' . str_replace('"', "", $dealerName) . '",' .
				'"' . str_replace('"', "", $this->iLocationContactRow['address_1'] . (empty($this->iLocationContactRow['address_2']) ? "" : ", " . $this->iLocationContactRow['address_2'])) . '",' .
				'"' . str_replace('"', "", $this->iLocationContactRow['city']) . '",' .
				'"' . str_replace('"', "", $this->iLocationContactRow['state']) . '",' .
				'"' . str_replace('"', "", substr($this->iLocationContactRow['postal_code'], 0, 5)) . '",' .
				'"' . str_replace('"', "", $this->iLocationContactRow['email_address']) . '",' .
				'"' . str_replace('"', "", $this->iLocationContactRow['phone_number']) . '",' .
				'"",' .
				'"' . str_replace('"', "", $thisItemRow['distributor_product_code']) . '",' .
				'"' . str_replace('"', "", $thisItemRow['product_row']['description']) . '",' .
				'"' . str_replace('"', "", $thisItemRow['product_row']['upc_code']) . '",' .
				'"' . $thisItemRow['quantity'] . '",' .
				'"' . $thisItemRow['product_row']['base_cost'] . '",' .
				'"' . $specialInstructions . '"\n';
		}

		$returnValues = array();

# Submit the order

		$orderEmailAddress = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
		if (empty($orderEmailAddress)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "No Email Address is sent for sending orders";
			return false;
		}
		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}

		$result = sendEmail(array("body" => "Order from " . $dealerName . " is attached", "subject" => "Order from " . $dealerName,
			"attachment_string" => $dealerFileContent, "attachment_filename" => $orderPrefix . $distributorOrderId . ".csv", "email_address" => $orderEmailAddress, "cc_addresses" => array($this->iLocationContactRow['email_address'])));
		if ($result !== true) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "No Email Address is set for sending orders";
			return false;
		} else {
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $distributorOrderId);
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		if ($this->iTrackingAlreadyRun) {
			return false;
		}
		if (!$this->connect()) {
			return false;
		}
		$this->iTrackingAlreadyRun = true;

		if (!ftp_chdir($this->iConnection, "TrackingInfo")) {
			$this->iErrorMessage = "TrackingInfo directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		$trackingFiles = array();
		if (is_array($fileList)) {
			foreach ($fileList as $thisFile) {
				if (strpos($thisFile, ".csv") !== false) {
					$trackingFiles[] = $thisFile;
				}
			}
		}
		$fcaUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
		if (empty($fcaUserId)) {
			return false;
		}
		$returnValues = array();
		foreach ($trackingFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/mgegroup-" . getRandomString(6) . ".csv";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$openFile = fopen($tempFilename, "r");
			$fieldNames = array();
			$count = 0;
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
				$orderNumber = $thisProductInfo['usr_vend_ord_no'];
				$parts = explode("-", $orderNumber);
				$orderId = $parts[0];
				$orderId = getFieldFromId("order_id", "orders", "order_id", $orderId, "date_completed is null");
				if (empty($orderId)) {
					continue;
				}
				$remoteOrderId = $parts[1];
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId, "remote_order_id = ? and tracking_identifier is null", $remoteOrderId);
				if (empty($orderShipmentId)) {
					continue;
				}
				$orderNote = $thisProductInfo['bus_dat'] . ": " . $thisProductInfo['trk_no_1'];
				$orderNoteId = getFieldFromId("order_note_id", "order_notes", "order_id", $orderId, "content = ?", $orderNote);
				if (empty($orderNoteId)) {
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, $orderNote);
				}
				$trackingNumber = $thisProductInfo['trk_no_1'];
				$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", "UPS");
				$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
					$trackingNumber, $shippingCarrierId, "UPS", $orderShipmentId);
				if ($resultSet['affected_rows'] > 0) {
					Order::sendTrackingEmail($orderShipmentId);
					executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
						$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingNumber,
						"Tracking number added by " . $this->iProductDistributorRow['description']);
					$returnValues[] = $orderShipmentId;
				}
			}
			unlink($tempFilename);
		}

		return $returnValues;
	}
}
