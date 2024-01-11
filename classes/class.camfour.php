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

class Camfour extends ProductDistributor {

	private $iFieldTranslations = array("upc_code" => "upc_code", "product_code" => "camfour_part_number", "description" => "description", "detailed_description" => "extended_description",
		"manufacturer_code" => "manufacturer", "model" => "xxxxxx", "list_price" => "msrp", "manufacturer_sku" => "manufacturer_part_number",
		"manufacturer_advertised_price" => "list_price_alt", "width" => "xxxxxx", "length" => "xxxxxx", "height" => "xxxxxx", "weight" => "product_weight",
		"base_cost" => "dealer_price", "drop_ship_flag" => "xxxxx", "image_location" => "image_url", "category" => "product_category", "quantity" => "combined_quantity");
	// Other Camfour catalog fields (all field names will be made into codes to fix spaces and capitalization)
	//HC Warehouse Qty Available (all 0 in sample data), but used in production
	// creating combined_quantity from CAM Warehouse Qty Available + HC Warehouse Qty Available

	private $iLiveUrl = "https://www.ezgun.net/";
	private $iTestUrl = "https://ezmag2.allegroconsultants.com/";
	private $iLogging;
	private $iUseUrl;

	function __construct($locationId) {
		$this->iProductDistributorCode = "CAMFOUR";
		$this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_CAMFOUR"));
		parent::__construct($locationId);
		if ($GLOBALS['gDevelopmentServer']) {
			$this->iUseUrl = $this->iTestUrl;
		} else {
			$this->iUseUrl = $this->iLiveUrl;
		}
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		return $this->getProducts() !== false;
	}

	function getProducts() {
		$productArray = getCachedData("camfour_product_feed", "");

		if (empty($productArray)) {

			$fileUrl = $this->iUseUrl . "apidownload/download/products/api/" . $this->iLocationCredentialRow['password'] . ".csv";
			$localFileName = $GLOBALS['gDocumentRoot'] . "/cache/camfour-" . $this->iLocationCredentialRow['password'] . ".csv";
			$lastDownloadTime = filemtime($localFileName);
			if ($lastDownloadTime === false || time() - $lastDownloadTime > 3600) { // Don't redownload file if it was downloaded in the past hour

				$outputFile = fopen($localFileName, "wb");
				if (!$outputFile) {
					$this->iErrorMessage = "Could not save local file";
					return false;
				}
				$curl = curl_init();
				if ($this->iUseUrl === $this->iTestUrl) { // Test server SSL certificate name is invalid
					curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
					curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				}
				curl_setopt_array($curl, array(
					CURLOPT_URL => $fileUrl,
					CURLOPT_FILE => $outputFile,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "GET",
					CURLOPT_HTTPHEADER => array(
						"Accept: */*",
						"Accept-Encoding: gzip, deflate",
						"Cache-Control: no-cache",
						"Connection: keep-alive",
						"cache-control: no-cache"
					),
				));

				curl_exec($curl);
				$err = curl_error($curl);

				curl_close($curl);
				fclose($outputFile);

				if ($err) {
					$this->iErrorMessage = "Curl error: " . $err;
					return false;
				}
			}

			$openFile = fopen($localFileName, "r");
			$fieldNames = array();
			$productArray = array();
			$count = 0;
			while ($csvData = fgetcsv($openFile)) {
				$count++;
				if ($count == 1) {
					if (count($csvData) == 1) { // if API key incorrect, product feed returns HTML.
						$this->iErrorMessage = "Product feed file not found. Check your credentials.";
						return false;
					}
					foreach ($csvData as $thisName) {
						$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true));
					}
				} else {
					$fieldData = array();
					foreach ($csvData as $index => $thisData) {
						$thisFieldName = $fieldNames[$index];
						$fieldData[$thisFieldName] = trim($thisData);
					}
					$productArray[$fieldData[$this->iFieldTranslations['product_code']]] = $fieldData;
				}
			}
			fclose($openFile);

			setCachedData("camfour_product_feed", "", $productArray, 1);
		}

		return $productArray;
	}

	function syncProducts($parameters = array()) {

		$productArray = $this->getProducts();

		if ($productArray === false) {
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
		$imageCount = 0;
		$noUpc = 0;
		$foundCount = 0;
		$updatedCount = 0;
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

				$cost = round($thisProductInfo[$this->iFieldTranslations['base_cost']], 2);
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

				$listPrice = ($thisProductInfo[$this->iFieldTranslations['list_price']] > 0 ? $thisProductInfo[$this->iFieldTranslations['list_price']] : null);
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$insertSet = executeQuery("insert into products (client_id,product_code,description,detailed_description,link_name,remote_identifier,product_manufacturer_id," .
					"base_cost,list_price,date_created,time_changed,reindex,internal_use_only) values (?,?,?,?,?, ?,?,?,?,now(),now(),1, ?)",
					$GLOBALS['gClientId'], $useProductCode, $description, $detailedDescription, $useLinkName, $remoteIdentifier, $productManufacturerId, $cost, $listPrice, $internalUseOnly);
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
				if (!empty($thisProductInfo['serialized_flag'])) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if (!empty($thisProductInfo['serialized_flag'])) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}

			if (self::$iCorewareShootingSports) {
				$originalImageId = $productRow['image_id'];
				$productRow['image_id'] = getFieldFromId("image_id", "images", "image_id", $productRow['image_id'], "os_filename is not null or file_content is not null");
				if (empty($productRow['image_id']) && !empty($thisProductInfo[$this->iFieldTranslations['image_location']])) {
					if (!empty($originalImageId)) {
						$badImageCount++;
						executeQuery("update images set os_filename = 'CSSC_BAD_IMAGES' where image_id = ? or image_id in (select image_id from product_images where product_id = ?)",$originalImageId,$productRow['product_id']);
						executeQuery("update products set image_id = null where product_id = ?",$productRow['product_id']);
						executeQuery("delete from product_images where product_id = ?",$productRow['product_id']);
						executeQuery("delete from image_data where image_id in (select image_id from images where os_filename = 'CSSC_BAD_IMAGES')");
						executeQuery("delete from images where os_filename = 'CSSC_BAD_IMAGES'");
					}

					$imageUrl = $thisProductInfo[$this->iFieldTranslations['image_location']];
					$imageId = $imageContents = "";
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $thisProductInfo[$this->iFieldTranslations['product_code']] . ".jpg", "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "CAMFOUR_IMAGE"));
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

			if (self::$iCorewareShootingSports && !$productCategoryAdded && !empty($thisProductInfo[$this->iFieldTranslations['category']])) {
				$categoryCode = makeCode($thisProductInfo[$this->iFieldTranslations['category']]);
				$this->addProductCategories($productId, array($categoryCode));
			}
		}

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}
		$quantityAvailable = false;
		$cost = false;

		$productArray = $this->getProducts();

		if ($productArray === false) {
			return false;
		}

		foreach ($productArray as $thisProductInfo) {
			if ($thisProductInfo[$this->iFieldTranslations['product_code']] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = max(intval($thisProductInfo['cam_warehouse_qty_available']) + intval($thisProductInfo['hc_warehouse_qty_available']), 0);
			$cost = round($thisProductInfo[$this->iFieldTranslations['base_cost']], 2);
			break;
		}
		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$productArray = $this->getProducts();
		if ($productArray === false) {
			return false;
		}
		$inventoryUpdateArray = array();
		foreach ($productArray as $thisProductInfo) {
			$thisProductInfo[$this->iFieldTranslations['base_cost']] = round($thisProductInfo[$this->iFieldTranslations['base_cost']], 2);
			$combinedQuantity = max(intval($thisProductInfo['cam_warehouse_qty_available']) + intval($thisProductInfo['hc_warehouse_qty_available']), 0);

			$thisInventoryUpdate = array("product_code" => $thisProductInfo[$this->iFieldTranslations['product_code']],
				"quantity" => $combinedQuantity,
				"cost" => $thisProductInfo[$this->iFieldTranslations['base_cost']]);
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
		if ($a['company_name'] == $b['company_name']) {
			return 0;
		}
		return ($a['company_name'] > $b['company_name']) ? 1 : -1;
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
		// Camfour does not have facet information
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

		foreach ($orderItems as $index => $thisOrderItemInfo) {
			if (!is_array($thisOrderItemInfo)) {
				$thisOrderItemInfo = array("order_item_id" => $thisOrderItemInfo);
			}
			$thisOrderItemRow = getRowFromId("order_items", "order_item_id", $thisOrderItemInfo['order_item_id']);
			$isClass3Product = getFieldFromId("product_tag_link_id", "product_tag_links", "product_tag_id", $this->iClass3ProductTagId, "product_id = ?", $thisOrderItemRow['product_id']);
			if ($isClass3Product) {
				$thisOrderItemInfo['ship_to'] = "dealer";
			}
			$orderItems[$index] = $thisOrderItemInfo;
		}

		$orderParts = $this->splitOrder($orderId, $orderItems);
		if ($orderParts === false) {
			return false;
		}
		$dropshipAllowed = getPreference("CAMFOUR_DROPSHIP_AUTHORIZED");
		if (empty($dropshipAllowed)) {  // Dropship is permitted only for certain customers
			$dealerOrderItemRows = array_merge($orderParts['customer_order_items'], $orderParts['dealer_order_items'], $orderParts['ffl_order_items']);
			$fflOrderItemRows = array();
			$customerOrderItemRows = array();
		} else {
			$customerOrderItemRows = $orderParts['customer_order_items'];
			$fflOrderItemRows = $orderParts['ffl_order_items'];
			$dealerOrderItemRows = $orderParts['dealer_order_items'];
		}

		$fflRow = array();
		$fflFileContent = "";
		$fflLicenseFileExtension = false;
		$fflLicenseFileContent = false;
		$fflName = false;
		if (!empty($fflOrderItemRows)) {
			$fflRow = (new FFL($ordersRow['federal_firearms_licensee_id']))->getFFLRow();
			if (empty($fflRow)) {
				$this->iErrorMessage = "No FFL Dealer set for this order";
				return false;
			}
			$fileRow = getRowFromId("files", "file_id", $fflRow['file_id']);
			if (!empty($fileRow)) {
				if (!empty($fileRow['os_filename'])) {
					$fileRow['file_content'] = getExternalFileContents($fileRow['os_filename']);
				}
			} else {
				$this->iErrorMessage = "FFL license file is missing";
				return false;
			}
			$fileRow['extension'] = strtolower($fileRow['extension']);
			$fflLicenseFileExtension = ($fileRow['extension'] == "jpg" ? "jpeg" : $fileRow['extension']);
			$fflLicenseFileContent = $fileRow['file_content'];

			$fflFileContent .= createCsvRow(array('PO#', 'Sku', 'Qty',
				'Name', 'Company', 'Address1', 'Address2',
				'City', 'State', 'Postal Code', 'Country',
				'LEAVE BLANK', 'LEAVE BLANK', 'Comments',
				'Is Dropship', 'FFL Required', 'FFL Number', 'FFL Expiration'));
			$poNumber = $orderId . "-%remote_order_id%";
			$fflName = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);

			foreach ($fflOrderItemRows as $thisItemRow) {
				$fflFileContent .= createCsvRow(array($poNumber, $thisItemRow['distributor_product_code'], $thisItemRow['quantity'],
					$ordersRow['full_name'], $fflName,
					$fflRow['mailing_address_1'], $fflRow['mailing_address_2'],
					$fflRow['mailing_city'], $fflRow['mailing_state'], $fflRow['mailing_postal_code'],
					getFieldFromId("country_name", "countries", "country_id", $fflRow['mailing_country_id']), '', '',
					'Customer order #' . $orderId, "Y", "Y", $fflRow['license_number'], $fflRow['expiration_date']));
			}

		}

		$customerFileContent = "";
		if (!empty($customerOrderItemRows)) {
			$customerFileContent .= createCsvRow(array('PO#', 'Sku', 'Qty',
				'Name', 'Company', 'Address1', 'Address2',
				'City', 'State', 'Postal Code', 'Country',
				'LEAVE BLANK', 'LEAVE BLANK', 'Comments',
				'Is Dropship', 'FFL Required', 'FFL Number', 'FFL Expiration'));
			$poNumber = $orderId . "-%remote_order_id%";

			foreach ($customerOrderItemRows as $thisItemRow) {
				$customerFileContent .= createCsvRow(array($poNumber, $thisItemRow['distributor_product_code'], $thisItemRow['quantity'],
					$ordersRow['full_name'], $contactRow['business_name'], $addressRow['address_1'], $addressRow['address_2'],
					$addressRow['city'], $addressRow['state'], $addressRow['postal_code'],
					getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']), '', '',
					'Customer order #' . $orderId, "Y", "N", '', ''));
			}
		}
		$dealerFileContent = "";
		if (!empty($dealerOrderItemRows)) {
			$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
			$dealerFileContent .= createCsvRow(array('PO#', 'Sku', 'Qty',
				'Name', 'Company', 'Address1', 'Address2',
				'City', 'State', 'Postal Code', 'Country',
				'LEAVE BLANK', 'LEAVE BLANK', 'Comments'));

			$poNumber = $orderId . "-%remote_order_id%";

			foreach ($dealerOrderItemRows as $thisItemRow) {
				$dealerFileContent .= createCsvRow(array($poNumber, $thisItemRow['distributor_product_code'], $thisItemRow['quantity'],
					$dealerName, $this->iLocationContactRow['business_name'], $this->iLocationContactRow['address_1'], $this->iLocationContactRow['address_2'],
					$this->iLocationContactRow['city'], $this->iLocationContactRow['state'], $this->iLocationContactRow['postal_code'],
					getFieldFromId("country_name", "countries", "country_id", $this->iLocationContactRow['country_id']), '', '',
					'For customer order #' . $orderId));
			}
		}

		$returnValues = array();

# Submit the orders

		if (!empty($fflFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$orderId, "123");
			$remoteOrderId = $orderSet['insert_id'];
			$fflFileContent = str_replace("%remote_order_id%", $remoteOrderId, $fflFileContent);
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			foreach ($fflOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}

			$fflFilename = $GLOBALS['gDocumentRoot'] . "/cache/camfour-order" . $orderId . ".csv";
			$fflFile = fopen($fflFilename, "w");
			fwrite($fflFile, $fflFileContent);
			fclose($fflFile);

			$fflLicenseFilename = $GLOBALS['gDocumentRoot'] . "/cache/" . $fflRow['license_number'] . "." . $fflLicenseFileExtension;
			$fflLicenseFile = fopen($fflLicenseFilename, "w");
			fwrite($fflLicenseFile, $fflLicenseFileContent);
			fclose($fflLicenseFile);

			$responseArray = $this->sendApiOrder($fflFilename, $fflLicenseFilename, $fflLicenseFileExtension);

			if (!empty($this->iErrorMessage)) {
				$failedItems = array();
				foreach ($fflOrderItemRows as $thisItemRow) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['failed_items'] = $failedItems;
			} else {
				$messageParts = explode(" ", $responseArray['message']);
				$remoteOrderNumber = $messageParts[3];
				if (stripos($remoteOrderNumber, ",")) { // response may include multiple order numbers
					$remoteOrderNumber = explode(",", $remoteOrderNumber)[0];
				}
				if (is_numeric($remoteOrderNumber)) {
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderNumber, $remoteOrderId);
				}
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderNumber, "ship_to" => $fflName);
			}
		}

		if (!empty($customerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$orderId, "123");
			$remoteOrderId = $orderSet['insert_id'];
			$customerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $customerFileContent);
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			foreach ($customerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}

			$customerFilename = $GLOBALS['gDocumentRoot'] . "/cache/camfour-order" . $orderId . ".csv";
			$customerFile = fopen($customerFilename, "w");
			fwrite($customerFile, $customerFileContent);
			fclose($customerFile);

			$responseArray = $this->sendApiOrder($customerFilename);

			if (!empty($this->iErrorMessage)) {
				$failedItems = array();
				foreach ($customerOrderItemRows as $thisItemRow) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['failed_items'] = $failedItems;
			} else {
				$messageParts = explode(" ", $responseArray['message']);
				$remoteOrderNumber = $messageParts[3];
				if (stripos($remoteOrderNumber, ",")) { // response may include multiple order numbers
					$remoteOrderNumber = explode(",", $remoteOrderNumber)[0];
				}
				if (is_numeric($remoteOrderNumber)) {
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderNumber, $remoteOrderId);
				}
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderNumber, "ship_to" => $ordersRow['full_name']);
			}
		}

		if (!empty($dealerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$orderId, "123");
			$remoteOrderId = $orderSet['insert_id'];
			$dealerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $dealerFileContent);
			executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderId, $remoteOrderId);
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}

			$dealerFilename = $GLOBALS['gDocumentRoot'] . "/cache/camfour-order" . $orderId . ".csv";
			$dealerFile = fopen($dealerFilename, "w");
			fwrite($dealerFile, $dealerFileContent);
			fclose($dealerFile);

			$responseArray = $this->sendApiOrder($dealerFilename);

			if (!empty($this->iErrorMessage)) {
				$failedItems = array();
				foreach ($dealerOrderItemRows as $thisItemRow) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['failed_items'] = $failedItems;
			} else {
				$messageParts = explode(" ", $responseArray['message']);
				$remoteOrderNumber = $messageParts[3];
				if (stripos($remoteOrderNumber, ",")) { // response may include multiple order numbers
					$remoteOrderNumber = explode(",", $remoteOrderNumber)[0];
				}
				if (is_numeric($remoteOrderNumber)) {
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $remoteOrderNumber, $remoteOrderId);
				}
				$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderNumber, "ship_to" => $GLOBALS['gClientName']);
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
			$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisProduct['product_id'], "location_id = ?", $locationId);
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

		$dealerFileContent = "";
		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));

		$poNumber = $orderPrefix . $distributorOrderId;

		$dealerFileContent .= createCsvRow(array('PO#', 'Sku', 'Qty',
			'Name', 'Company', 'Address1', 'Address2',
			'City', 'State', 'Postal Code', 'Country',
			'LEAVE BLANK', 'LEAVE BLANK', 'Comments'));

		foreach ($dealerOrderItemRows as $thisItemRow) {
			$dealerFileContent .= createCsvRow(array($poNumber, $thisItemRow['distributor_product_code'], $thisItemRow['quantity'],
				$dealerName, $this->iLocationContactRow['business_name'], $this->iLocationContactRow['address_1'], $this->iLocationContactRow['address_2'],
				$this->iLocationContactRow['city'], $this->iLocationContactRow['state'], $this->iLocationContactRow['postal_code'],
				getFieldFromId("country_name", "countries", "country_id", $this->iLocationContactRow['country_id']), '', '',
				'Distributor order #' . $distributorOrderId));
		}

		$returnValues = array();

# Submit the order

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}

		$dealerFilename = $GLOBALS['gDocumentRoot'] . "/cache/camfour-distributor-order" . $distributorOrderId . ".csv";
		$dealerFile = fopen($dealerFilename, "w");
		fwrite($dealerFile, $dealerFileContent);
		fclose($dealerFile);

		$responseArray = $this->sendApiOrder($dealerFilename);

		if (!empty($this->iErrorMessage)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		} else {
			$messageParts = explode(" ", $responseArray['message']);
			$remoteOrderNumber = $messageParts[3];
			if (stripos($remoteOrderNumber, ",")) { // response may include multiple order numbers
				$remoteOrderNumber = explode(",", $remoteOrderNumber)[0];
			}
			if (is_numeric($remoteOrderNumber)) {
				executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $remoteOrderNumber, $distributorOrderId);
			}
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $remoteOrderNumber);
		}

		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		return $returnValues;
	}

	private function sendApiOrder($filename, $fflLicenseFilename = false, $fflLicenseFileExtension = "jpg") {
		$curl = curl_init();
		if ($this->iUseUrl === $this->iTestUrl) { // Test server SSL certificate name is invalid
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		}
		$orderCsvFile = new CURLFile($filename, "text/csv");
		$postFields = array(
			'customer' => $this->iLocationCredentialRow['user_name'],
			'api_key' => $this->iLocationCredentialRow['password'],
			'order_csv' => $orderCsvFile
		);
		if (!empty($fflLicenseFilename)) {
			$fflLicenseFile = new CURLFile($fflLicenseFilename, ($fflLicenseFileExtension == "pdf" ? "application/" : "image/") . $fflLicenseFileExtension);
			$postFields['ffl_file'] = $fflLicenseFile;
		}
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->iUseUrl . "/bulkorders/customer/api",
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $postFields,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		if ($this->iLogging) {
			addDebugLog("Camfour request: POST " . $this->iUseUrl . "/bulkorders/customer/api"
				. "\nCamfour Data: " . getFirstPart(jsonEncode($postFields), 1000)
				. "\nCamfour Result: " . getFirstPart($response, 1000)
				. (empty($err) ? "" : "\nCamfour Error: " . $err)
				. "\nCamfour HTTP Status: " . $httpCode);
		}

		if (substr($response, 0, strlen("<!doctype html>")) == "<!doctype html>") {
			$response = implode("\n", array_filter(array_map('trim', explode("\n", strip_tags($response)))));
		}

		$responseArray = json_decode($response, true);

		if ($err) {
			$this->iErrorMessage = $err;
			$responseArray = false;
		} elseif ($responseArray['code'] !== 1) {
			$this->iErrorMessage = ($responseArray['message'] ?: $response);
			$responseArray = false;
		}
		return $responseArray;
	}

	function getOrderTrackingData($orderShipmentId) {

		$this->iErrorMessage = "Camfour does not provide tracking via API";
		return false;


	}
}
