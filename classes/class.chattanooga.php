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

class Chattanooga extends ProductDistributor {

	// Field names from GET /items
	//	    "quantity" => "inventory", "upc_code" => "upc", "product_code" => "cssi_id", "description" => "item_name", "alternate_description" => "web_item_description",
	//		"detailed_description" => "", "manufacturer_code" => "manufacturer", "model" => "XXXXX", "manufacturer_sku" => "manufacturer_item_number", "manufacturer_advertised_price" => "map",
	//		"width" => "width", "length" => "length", "height" => "height", "weight" => "ship_weight", "base_cost" => "custom_price", "list_price" => "msrp");
	// Field Names from CSV
	private $iFieldTranslations = array("quantity" => "quantity_in_stock", "upc_code" => "upc", "product_code" => "sku", "description" => "item_name", "alternate_description" => "web_item_description",
		"detailed_description" => "", "manufacturer_code" => "manufacturer", "model" => "XXXXX", "manufacturer_sku" => "manufacturer_item_number", "manufacturer_advertised_price" => "map",
		"width" => "width", "length" => "length", "height" => "height", "weight" => "ship_weight", "base_cost" => "price", "list_price" => "msrp");

	private $iUseUrl = "https://api.chattanoogashooting.com/rest/";
	private $iLogging;
	private $iErrorCodes = array('401' => 'Invalid authorization details provided.',
		'403' => 'Permission denied.',
		'500' => 'Internal Server Error',
		'404' => 'The record specified could not be found.');

	function __construct($locationId) {
		$this->iProductDistributorCode = "CHATTANOOGA";
		$this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_CHATTANOOGA"));
		$this->iClass3Products = true;
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
		$this->iIgnoreProductsNotFound = true;
	}

	function testCredentials() {

		$response = $this->makeRequest("items/product-feed", "");
		if ($response === false) {
			return false;
		}

		$feedUrl = $response['product_feed']['url'];

		return (!empty($feedUrl));
	}

	private function makeRequest($method, $data, $verb = 'GET', $version = 3) {
		$userName = $this->iLocationCredentialRow['user_name']; # '226A9CC605AAC5C88F8157600B0A036C';
		$password = $this->iLocationCredentialRow['password']; # '226A9CC7AF8B74AB68CD4F69F330F727';
		$md5Token = md5($password);
		$authHeaderValue = "Basic " . $userName . ":" . $md5Token;

		$curlOptions = array();

		$curlOptions[CURLOPT_RETURNTRANSFER] = true;
		$curlOptions[CURLOPT_MAXREDIRS] = 10;
		$curlOptions[CURLOPT_HTTPAUTH] = constant('CURLAUTH_BASIC');
		$curlOptions[CURLOPT_FOLLOWLOCATION] = false;
		$curlOptions[CURLOPT_HTTPHEADER] = array(
			"Accept: */*",
			"Accept-Encoding: gzip, deflate",
			"Authorization: " . $authHeaderValue,
			"Host: api.chattanoogashooting.com",
			"Cache-Control: no-cache",
			"Connection: keep-alive");

		$verb = strtoupper($verb);
		$jsonData = false;
		switch ($verb) {
			case "POST":
				$curlOptions[CURLOPT_POST] = 1;
			case "PUT":
			case "PATCH":
				$jsonData = json_encode($data);
				$curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
				$curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($jsonData);
				$curlOptions[CURLOPT_POSTFIELDS] = $jsonData;
				break;
		}
		$curlOptions[CURLOPT_CUSTOMREQUEST] = $verb;

		$url = rtrim($this->iUseUrl, '/') . '/v' . $version . '/' . ltrim($method, "/");
		$curl = curl_init($url);

		foreach ($curlOptions as $option => $value) {
			curl_setopt($curl, $option, $value);
		}

		$returnValue = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$err = curl_error($curl);
		if ($this->iLogging) {
			addDebugLog("Chattanooga request: " . $url
				. "\nChattanooga Data: " . (strlen($jsonData) > 500 ? substr($jsonData, 0, 500) . "..." : $jsonData)
				. "\nChattanooga Result: " . $returnValue
				. (empty($err) ? "" : "\nChattanooga Error: " . $err)
				. "\nChattanooga HTTP Status: " . $httpCode);
		}

		if (empty($returnValue)) {
			$this->iErrorMessage = curl_error($curl);
			if (empty($this->iErrorMessage)) {
				if (array_key_exists($httpCode, $this->iErrorCodes)) {
					$this->iErrorMessage = $this->iErrorCodes[$httpCode];
				} else {
					$this->iErrorMessage = "Unknown API Error";
				}
			}
			return false;
		}

		curl_close($curl);
		if (stristr($returnValue, "<!DOCTYPE html>") !== false) {
			if (stristr($returnValue, "Post Size exceeds the maximum limit") !== false) {
				$this->iErrorMessage = "File is too large to upload. Try using smaller images.";
			} else {
				$this->iErrorMessage = strip_tags(stristr($returnValue, "<body>"));
				$this->iErrorMessage = $this->iErrorMessage ?: "Invalid Response";
			}
			return false;
		}

		$response = json_decode($returnValue, true);
		if (!is_array($response)) {
			$this->iErrorMessage = "Invalid Response";
			return false;
		}
		if (array_key_exists("error_code", $response)) {
			if (array_key_exists("errors", $response) && !empty($response['errors'])) {
				$this->iErrorMessage = $response['errors']['code'] . " " . $response['errors']['message'];
			} else {
				$this->iErrorMessage = $response['error_code'] . " " . $response['message'];
			}
			if (empty($this->iErrorMessage)) {
				$this->iErrorMessage = $response;
			}
			return false;
		}
		return $response;
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

				$cost = $this->roundAmount("base_cost", $thisProductInfo);
				if (empty($cost)) {
					continue;
				} else {
					$internalUseOnly = 0;
				}

				$listPrice = $this->roundAmount("list_price", $thisProductInfo);

				if ($listPrice <= $cost) {
					$listPrice = null;
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

			$productDistributorDropshipProhibitionId = getFieldFromId("product_distributor_dropship_prohibition_id", "product_distributor_dropship_prohibitions", "product_id", $productId,
				"product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
			if (empty($thisProductInfo['drop_ship_flag'])) {
				if (empty($productDistributorDropshipProhibitionId)) {
					executeQuery("insert ignore into product_distributor_dropship_prohibitions (product_id,product_distributor_id) values (?,?)", $productId, $this->iLocationRow['product_distributor_id']);
				}
			} elseif (!empty($productDistributorDropshipProhibitionId)) {
				executeQuery("delete from product_distributor_dropship_prohibitions where product_id = ? and product_distributor_id = ?", $productId, $this->iLocationRow['product_distributor_id']);
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

			# Make sure to clear image ID

			if (self::$iCorewareShootingSports) {
				$originalImageId = $productRow['image_id'];
				$productRow['image_id'] = getFieldFromId("image_id", "images", "image_id", $productRow['image_id'], "os_filename is not null or file_content is not null");
				if (empty($productRow['image_id']) && !empty($thisProductInfo['image_location'])) {
					if (!empty($originalImageId)) {
						$badImageCount++;
						executeQuery("update images set os_filename = 'CSSC_BAD_IMAGES' where image_id = ? or image_id in (select image_id from product_images where product_id = ?)",$originalImageId,$productRow['product_id']);
						executeQuery("update products set image_id = null where product_id = ?",$productRow['product_id']);
						executeQuery("delete from product_images where product_id = ?",$productRow['product_id']);
						executeQuery("delete from image_data where image_id in (select image_id from images where os_filename = 'CSSC_BAD_IMAGES')");
						executeQuery("delete from images where os_filename = 'CSSC_BAD_IMAGES'");
					}

                    $imageUrl = $thisProductInfo['image_location'];
                    if(strpos($imageUrl,"?") !== false) { // strip off parameters that set the image to thumbnail size
                        $imageUrl = substr($imageUrl, 0, strpos($imageUrl, "?"));
                    }
                    $imageFilename = basename($imageUrl);
					$imageId = $imageContents = "";
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (!empty($imageContents)) {
						if (empty($imageFilename)) {
							$imageFilename = "image" . $productRow['image_id'] . ".jpg";
						} elseif(!endsWith($imageFilename, ".jpg")) {
							$imageFilename .= ".jpg";
						}
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $imageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "CHATTANOOGA_IMAGE"));
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

			if (self::$iCorewareShootingSports && empty($productCategoryLinkId) && !$productCategoryAdded && !empty($thisProductInfo['category'])) {
				$categoryCode = makeCode($thisProductInfo['category']);
				$this->addProductCategories($productId, array($categoryCode));
			}
		}

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getProducts() {
		$productArray = getCachedData("chattanooga_product_feed", "");

		if (empty($productArray)) {

			$response = $this->makeRequest("items/product-feed", "");
			if ($response === false) {
				return false;
			}

			$feedUrl = $response['product_feed']['url'];

			if (empty($feedUrl)) {
				if (!empty($response['message'])) {
					$this->iErrorMessage = $response['message'];
				} else {
					$this->iErrorMessage = "No Feed URL";
				}
				return false;
			}
			// Save ONE CSV file each day for 14 days
			$cacheFolder = $GLOBALS['gDocumentRoot'] . "/cache/chattanooga/";
			if (!is_dir($cacheFolder)) {
				mkdir($cacheFolder);
			}
			$daysArray = array();
			foreach (glob($cacheFolder . "*") as $file) {
				if (time() - filemtime($file) > (86400 * 14)) {
					unlink($file);
					continue;
				}
				// keep one file per day
				$day = substr($file, strlen($cacheFolder), 10);
				if (array_key_exists($day, $daysArray)) {
					unlink($file);
				} else {
					$daysArray[$day] = $file;
				}
			}
			$localFilename = $cacheFolder . date("Y-m-d-H-i-s") . ".csv";
            $context = stream_context_create(['http' => ['timeout' => 600]]);
            file_put_contents($localFilename, file_get_contents($feedUrl, false, $context));
			$openFile = fopen($localFilename, "r");
			if (!$openFile) {
				$this->iErrorMessage = "Could not open catalog file";
				return false;
			}
			$fieldNames = array();
			$productArray = array();
			$count = 0;
			while ($csvData = fgetcsv($openFile)) {
				$count++;
				if ($count == 1) {
					foreach ($csvData as $thisName) {
						$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true));
					}
				} else {
					$fieldData = array();
					foreach ($csvData as $index => $thisData) {
						$thisFieldName = $fieldNames[$index];
						// remove invalid characters that MySql will reject
						$thisData = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]|\xED[\xA0-\xBF][\x80-\xBF]/S', '?', $thisData);
						if ($thisFieldName == "category" && strpos($thisData, '|') !== false) {
							$thisData = strtok($thisData, '|');
						}
						$fieldData[$thisFieldName] = trim($thisData);
					}
					// Make sure base_cost uses the higher of the dropship cost and dealer cost.
					$fieldData['price'] = max($fieldData['price'], $fieldData['drop_ship_price']);
					$productArray[$fieldData['sku']] = $fieldData;
				}
			}
			fclose($openFile);

			// no longer using items API
//			$page = 0;
//			while (true) {
//				$page++;
//				$response = $this->makeRequest("items?per_page=50&page=" . $page, "");
//
//				if ($response !== false) {
//					if (is_array($response['items']) && !empty($response['items'])) {
//						foreach ($response['items'] as $thisItem) {
//							if (array_key_exists($thisItem['cssi_id'], $productArray)) {
//								$productArray[$thisItem['cssi_id']] = array_merge($productArray[$thisItem['cssi_id']], $thisItem);
//							}
//						}
//					}
//					if ($page > $response['pagination']['page_count']) {
//						break;
//					}
//				}
//			}
//			foreach ($productArray as $index => $thisItem) {
//				if (!array_key_exists("cssi_id", $thisItem)) {
//					unset($productArray[$index]);
//				}
//                $productArray[$index]['cssi_id'] = $thisItem['sku'];
//				if (empty($productArray[$index]['custom_price'])) {
//					$productArray[$index]['custom_price'] = $thisItem['price'];
//				}
//			}

			setCachedData("chattanooga_product_feed", "", $productArray, 1);
		}

		return $productArray;
	}

	function getFacets($parameters = array()) {
		return array();
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}
		$quantityAvailable = false;
		$cost = false;

		$productArray = $this->getProducts();

		if (empty($productArray) || !is_array($productArray)) {
			return false;
		}

		foreach ($productArray as $thisProductInfo) {
			if ($thisProductInfo[$this->iFieldTranslations['product_code']] != $distributorProductCode) {
				continue;
			}

			$quantityAvailable = $thisProductInfo[$this->iFieldTranslations['quantity']];
			$cost = $this->roundAmount("base_cost", $thisProductInfo);
			break;
		}

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$productArray = $this->getProducts();
		if (empty($productArray) || !is_array($productArray)) {
			return false;
		}
		$inventoryUpdateArray = array();
		foreach ($productArray as $thisProductInfo) {
			$thisProductInfo[$this->iFieldTranslations['base_cost']] = $this->roundAmount("base_cost", $thisProductInfo);
			$thisInventoryUpdate = array("product_code" => $thisProductInfo[$this->iFieldTranslations['product_code']],
				"quantity" => $thisProductInfo[$this->iFieldTranslations['quantity']],
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
				$thisManufacturerCode = makeCode($thisProductInfo['manufacturer']);
				if (empty($productManufacturers[$thisManufacturerCode])) {
					$productManufacturers[$thisManufacturerCode] = array("business_name" => $thisProductInfo['manufacturer'], "products" => array(), "product_count" => 0);
				}
				if (count($productManufacturers[$thisManufacturerCode]['products']) < 5) {
					$productManufacturers[$thisManufacturerCode]['products'][] = array(
						"product_code" => $thisProductInfo[$this->iFieldTranslations['product_code']],
						"description" => $thisProductInfo[$this->iFieldTranslations['description']],
						"base_cost" => $thisProductInfo[$this->iFieldTranslations['base_cost']]);
				}
				$productManufacturers[$thisManufacturerCode]['product_count']++;
			}
		}
		uasort($productManufacturers, array($this, "sortManufacturers"));
		return $productManufacturers;
	}

	function getCategories($parameters = array()) {
		$productArray = $this->getProducts();
		if ($productArray === false) {
			return false;
		}

		$productCategories = array();
		foreach ($productArray as $thisProductInfo) {
			if (!empty($thisProductInfo['category'])) {
				$thisCategoryCode = makeCode($thisProductInfo['category']);
				if (empty($productCategories[$thisCategoryCode])) {
					$productCategories[$thisCategoryCode] = array("description" => $thisProductInfo['category'], "products" => array(), "product_count" => 0);
				}
				if (count($productCategories[$thisCategoryCode]['products']) < 5) {
					$productCategories[$thisCategoryCode]['products'][] = array(
						"product_code" => $thisProductInfo[$this->iFieldTranslations['product_code']],
						"description" => $thisProductInfo[$this->iFieldTranslations['description']],
						"base_cost" => $thisProductInfo[$this->iFieldTranslations['base_cost']]);
				}
				$productCategories[$thisCategoryCode]['product_count']++;
			}
		}
		uasort($productCategories, array($this, "sortCategories"));
		return $productCategories;
	}

	function sortManufacturers($a, $b) {
		if ($a['business_name'] == $b['business_name']) {
			return 0;
		}
		return ($a['business_name'] > $b['business_name']) ? 1 : -1;
	}

	function sortCategories($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}

	function roundAmount($fieldName, $thisProductInfo) {
		if (!is_numeric(str_replace(",", "", $thisProductInfo[$this->iFieldTranslations[$fieldName]]))) {
			if ($this->iLogging) {
				addDebugLog("Chattanooga: non-numeric value for " . $fieldName . ":" . jsonEncode($thisProductInfo));
			}
			return 0;
		} else {
			return round(str_replace(",", "", $thisProductInfo[$this->iFieldTranslations[$fieldName]]), 2);
		}
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
		$customerOrderItemRows = $orderParts['customer_order_items'];
		$fflOrderItemRows = $orderParts['ffl_order_items'];
		$dealerOrderItemRows = array_merge($orderParts['dealer_order_items'], $orderParts['class_3_order_items']);

		$insuranceFlag = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_INSURANCE_FLAG", "PRODUCT_DISTRIBUTORS");
		$insuranceFlag = empty($insuranceFlag) ? 0 : 1;

		$failedItems = array();
		$returnValues = array();

		if (!empty($fflOrderItemRows)) {
			$fflRow = (new FFL($ordersRow['federal_firearms_licensee_id']))->getFFLRow();
			if ($fflRow) {
				$fflNumber = str_replace("-", "", $fflRow['license_number']);
				$response = $this->makeRequest("federal-firearms-licenses/" . $fflNumber, "");
				if ($response === false) {
					$this->iErrorMessage = "Error checking FFL with Chattanooga. Confirm that you are authorized for dropship.";
					return false;
				} else {
					$fflOnFile = $response['federal_firearms_licenses'][0]['on_file_flag'] == 1;
					if (!$fflOnFile) { // FFL not on file with CHA; upload through API
						$fileRow = getRowFromId("files", "file_id", $fflRow['file_id']);
						if (!empty($fileRow)) {
							if (!empty($fileRow['os_filename'])) {
								$fileRow['file_content'] = getExternalFileContents($fileRow['os_filename']);
							}
							if (empty($fileRow['file_content'])) {
								$this->iErrorMessage = "FFL license file has no content. " . $this->iErrorMessage;
								return false;
							}
							// convert image to jpg
							if (in_array(strtolower($fileRow['extension']), array("png", "gif", "bmp", "wbmp", "gd2", "webp"))) {
								$image = imagecreatefromstring($fileRow['file_content']);
								ob_start();
								imagejpeg($image, NULL, 100);
								imagedestroy($image);
								$fileRow['file_content'] = ob_get_clean();
								$fileRow['extension'] = 'jpg';
							}
							$data = array('ffl_number' => $fflNumber,
								'ffl_document' => base64_encode($fileRow['file_content']),
								'ffl_document_extension' => $fileRow['extension']);
							if ($this->iLogging) {
								addDebugLog("Chattanooga FFL upload file " . $fileRow['filename'] . " total request size " . strlen(json_encode($data)));
							}
							$response = $this->makeRequest("federal-firearms-licenses", $data, 'POST', 2);
							if ($response === false) { // error from API
								$this->iErrorMessage = "Error submitting FFL documentation to Chattanooga. " . $this->iErrorMessage;
								return false;
							}
						} else {
							$this->iErrorMessage = "FFL is not on file with Chattanooga.  Add the FFL license file and resubmit the order.";
							return false;
						}
					}
				}
			} else {
				$this->iErrorMessage = "No FFL dealer set for this order";
				return false;
			}
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], 999);
			if (!empty($orderSet['sql_error'])) {
				$returnValues['error_message'] = $this->iErrorMessage = $orderSet['sql_error'];
				foreach ($fflOrderItemRows as $thisItemRow) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
			} else {
				$remoteOrderId = $orderSet['insert_id'];

				$handgunsProductCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", "HANDGUNS");
				if (empty($handgunsProductCategoryGroupId)) {
					$shippingMethod = "second_day_air";
				} else {
					$shippingMethod = "ground";
				}

				$sendOrderItems = array();
				foreach ($fflOrderItemRows as $thisItemRow) {
					$sendOrderItems[] = array(
						"item_number" => $thisItemRow['distributor_product_code'],
						"order_quantity" => $thisItemRow['quantity']
					);
					if (ProductCatalog::productIsInCategoryGroup($thisItemRow['product_id'], $handgunsProductCategoryGroupId)) {
						$shippingMethod = "second_day_air";
					}
				}

				$addressRow['postal_code'] = substr($addressRow['postal_code'], 0, 5);
				$postFields = array(
					'purchase_order_number' => $orderId . "-" . $remoteOrderId . "-DS" . ($GLOBALS['gDevelopmentServer'] ? "(TEST)" : ""),
					'drop_ship_flag' => 1,
					'insurance_flag' => $insuranceFlag,
					'customer' => $ordersRow['full_name'] . " " . $ordersRow['phone_number'],
					'delivery_option' => $shippingMethod,
					'federal_firearms_license_number' => $fflNumber,
					'ship_to_address' => array(
						'name' => $fflRow['business_name'],
						'line_1' => $fflRow['address_1'],
						'line_2' => empty($fflRow['address_2']) ? "" : $fflRow['address_2'],
						'city' => $fflRow['city'],
						'state_code' => $fflRow['state'],
						'zip' => $fflRow['postal_code']
					),
					'order_items' => $sendOrderItems
				);

				$response = $this->makeRequest("orders", $postFields, "POST");

				if ($response === false) {
					$returnValues['error_message'] = $this->iErrorMessage;
					foreach ($fflOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} elseif (!array_key_exists("orders", $response)) {
					foreach ($fflOrderItemRows as $thisItemRow) {
						$returnValues['error_message'] = $this->iErrorMessage = $response['errors'];
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} else {
					$orderNumber = $response['orders'][0]['order_number'];
					foreach ($fflOrderItemRows as $thisItemRow) {
						$foundItem = false;
						foreach ($response['orders'][0]['order_items'] as $thisLineItem) {
							if ($thisItemRow['distributor_product_code'] != $thisLineItem['item_number']) {
								continue;
							}
							if (strlen($thisLineItem['ship_quantity']) == 0) {
								$thisLineItem['ship_quantity'] = $thisLineItem['order_quantity'];
							}
							$foundItem = true;
							if ($thisLineItem['ship_quantity'] > 0) {
								executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
									$remoteOrderId, $thisItemRow['order_item_id'], $thisLineItem['ship_quantity']);
							}
							if ($thisLineItem['ship_quantity'] < $thisItemRow['quantity']) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['ship_quantity']);
							}
						}
						if (!$foundItem) {
							$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
						}
					}
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
					$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $fflRow['business_name']);
				}
			}
		}

		if (!empty($customerOrderItemRows)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], 999);
			if (!empty($orderSet['sql_error'])) {
				$returnValues['error_message'] = $this->iErrorMessage = $orderSet['sql_error'];
				foreach ($customerOrderItemRows as $thisItemRow) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
			} else {
				$remoteOrderId = $orderSet['insert_id'];

				$sendOrderItems = array();
				foreach ($customerOrderItemRows as $thisItemRow) {
					$sendOrderItems[] = array(
						"item_number" => $thisItemRow['distributor_product_code'],
						"order_quantity" => $thisItemRow['quantity']
					);
				}

				$addressRow['postal_code'] = substr($addressRow['postal_code'], 0, 5);
				$postFields = array(
					'purchase_order_number' => $orderId . "-" . $remoteOrderId . "-DS" . ($GLOBALS['gDevelopmentServer'] ? "(TEST)" : ""),
					'drop_ship_flag' => 1,
					'insurance_flag' => $insuranceFlag,
					'customer' => $ordersRow['full_name'] . " " . $ordersRow['phone_number'],
					'delivery_option' => 'ground',
					'ship_to_address' => array(
						'name' => $ordersRow['full_name'],
						'line_1' => $addressRow['address_1'],
						'line_2' => empty($addressRow['address_2']) ? "" : $addressRow['address_2'],
						'city' => $addressRow['city'],
						'state_code' => $addressRow['state'],
						'zip' => $addressRow['postal_code']
					),
					'order_items' => $sendOrderItems
				);

				$response = $this->makeRequest("orders", $postFields, 'POST');

				if ($response === false) {
					$returnValues['error_message'] = $this->iErrorMessage;
					foreach ($customerOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} elseif (!array_key_exists("orders", $response)) {
					foreach ($customerOrderItemRows as $thisItemRow) {
						$returnValues['error_message'] = $this->iErrorMessage = $response['errors'];
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} else {
					$orderNumber = $response['orders'][0]['order_number'];
					foreach ($customerOrderItemRows as $thisItemRow) {
						$foundItem = false;
						foreach ($response['orders'][0]['order_items'] as $thisLineItem) {
							if ($thisItemRow['distributor_product_code'] != $thisLineItem['item_number']) {
								continue;
							}
							if (strlen($thisLineItem['ship_quantity']) == 0) {
								$thisLineItem['ship_quantity'] = $thisLineItem['order_quantity'];
							}
							$foundItem = true;
							if ($thisLineItem['ship_quantity'] > 0) {
								executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
									$remoteOrderId, $thisItemRow['order_item_id'], $thisLineItem['ship_quantity']);
							}
							if ($thisLineItem['ship_quantity'] < $thisItemRow['quantity']) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['ship_quantity']);
							}
						}
						if (!$foundItem) {
							$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
						}
					}
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
					$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $ordersRow['full_name']);
				}
			}
		}

		if (!empty($dealerOrderItemRows)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], 999);
			if (!empty($orderSet['sql_error'])) {
				$returnValues['error_message'] = $this->iErrorMessage = $orderSet['sql_error'];
				foreach ($dealerOrderItemRows as $thisItemRow) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
			} else {
				$remoteOrderId = $orderSet['insert_id'];

				$sendOrderItems = array();
				foreach ($dealerOrderItemRows as $thisItemRow) {
					$sendOrderItems[] = array(
						"item_number" => $thisItemRow['distributor_product_code'],
						"order_quantity" => $thisItemRow['quantity']
					);
				}

				$this->iLocationContactRow['postal_code'] = substr($this->iLocationContactRow['postal_code'], 0, 5);

				$postFields = array(
					'purchase_order_number' => $orderId . "-" . $remoteOrderId . ($GLOBALS['gDevelopmentServer'] ? "(TEST)" : ""),
					'drop_ship_flag' => 0,
					'ship_to_address' => array(
						'name' => getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true)),
						'line_1' => $this->iLocationContactRow['address_1'],
						'line_2' => empty($this->iLocationContactRow['address_2']) ? '' : $this->iLocationContactRow['address_2'],
						'city' => $this->iLocationContactRow['city'],
						'state_code' => $this->iLocationContactRow['state'],
						'zip' => $this->iLocationContactRow['postal_code']
					),
					'order_items' => $sendOrderItems
				);

				$response = $this->makeRequest("orders", $postFields, 'POST');

				if ($response === false) {
					$returnValues['error_message'] = $this->iErrorMessage;
					foreach ($dealerOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} elseif (!array_key_exists("orders", $response)) {
					foreach ($dealerOrderItemRows as $thisItemRow) {
						$returnValues['error_message'] = $this->iErrorMessage = $response['errors'];
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} else {
					$orderNumber = $response['orders'][0]['order_number'];
					foreach ($dealerOrderItemRows as $thisItemRow) {
						$foundItem = false;
						foreach ($response['orders'][0]['order_items'] as $thisLineItem) {
							if ($thisItemRow['distributor_product_code'] != $thisLineItem['item_number']) {
								continue;
							}
							if (strlen($thisLineItem['ship_quantity']) == 0) {
								$thisLineItem['ship_quantity'] = $thisLineItem['order_quantity'];
							}
							$foundItem = true;
							if ($thisLineItem['ship_quantity'] > 0) {
								executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
									$remoteOrderId, $thisItemRow['order_item_id'], $thisLineItem['ship_quantity']);
							}
							if ($thisLineItem['ship_quantity'] < $thisItemRow['quantity']) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['ship_quantity']);
							}
						}
						if (!$foundItem) {
							$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
						}
					}
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
					$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $GLOBALS['gClientName']);
				}
			}
		}

		if (!empty($failedItems)) {
			$returnValues['failed_items'] = $failedItems;
		}

		return $returnValues;
	}

	function placeDistributorOrder($productArray, $parameters = array()) {
		$userId = $parameters['user_id'];
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}

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
				$this->iErrorMessage = "Product '" . getFieldFromId("description", "products", "product_id", $thisProduct['product_id']) . "' is not available from this distributor. Inventory: " . $inventoryQuantity;
				return false;
			}
			$dealerOrderItemRows[$thisProduct['product_id']] = array("distributor_product_code" => $distributorProductCode, "product_id" => $thisProduct['product_id'], "quantity" => $thisProduct['quantity'], "notes" => $thisProduct['notes']);
		}

		if (empty($dealerOrderItemRows)) {
			$this->iErrorMessage = "No products found to order";
			return false;
		}

# Process products to be ordered

		$failedItems = array();
		$returnValues = array();
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

		$sendOrderItems = array();
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$sendOrderItems[] = array(
				"item_number" => $thisItemRow['distributor_product_code'],
				"order_quantity" => $thisItemRow['quantity'] + 0
			);
		}

		$result = $this->makeRequest("orders", [
			'purchase_order_number' => $orderPrefix . $distributorOrderId . ($GLOBALS['gDevelopmentServer'] ? "(TEST)" : ""),
			'drop_ship_flag' => 0,
			'ship_to_address' => array(
				'name' => getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true)),
				'line_1' => $this->iLocationContactRow['address_1'],
				'line_2' => empty($this->iLocationContactRow['address_2']) ? '' : $this->iLocationContactRow['address_2'],
				'city' => $this->iLocationContactRow['city'],
				'state_code' => $this->iLocationContactRow['state'],
				'zip' => $this->iLocationContactRow['postal_code']
			),
			'order_items' => $sendOrderItems
		], 'POST');

		if ($result === false) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unknown error";
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		} elseif (empty($result['orders'])) {
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$returnValues['error_message'] = $this->iErrorMessage = $result['errors'] . ": " . jsonEncode($result);
				$failedItems[$thisItemRow['product_id']] = array("product_id" => $thisItemRow['product_id'], "quantity" => $thisItemRow['quantity']);
			}
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		} else {
			$orderNumber = $result['orders'][0]['order_number'];
			$productIds = array();
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$foundItem = false;
				foreach ($result['orders'][0]['order_items'] as $thisLineItem) {
					if ($thisItemRow['distributor_product_code'] != $thisLineItem['item_number']) {
						continue;
					}
					if (strlen($thisLineItem['ship_quantity']) == 0) {
						$thisLineItem['ship_quantity'] = $thisLineItem['order_quantity'];
					}
					$foundItem = true;
					if ($thisLineItem['ship_quantity'] > 0) {
						executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
							$distributorOrderId, $thisItemRow['product_id'], $thisLineItem['ship_quantity'], $thisItemRow['notes']);
						$productIds[] = $thisItemRow['product_id'];
					}
					if ($thisLineItem['ship_quantity'] < $thisItemRow['quantity']) {
						$failedItems[$thisItemRow['product_id']] = array("product_id" => $thisItemRow['product_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['ship_quantity']);
					}
				}
				if (!$foundItem) {
					$failedItems[$thisItemRow['product_id']] = array("product_id" => $thisItemRow['product_id'], "quantity" => $thisItemRow['quantity']);
				}
			}
			executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $orderNumber, $distributorOrderId);
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $orderNumber, "product_ids" => $productIds);
		}

		if (!empty($failedItems)) {
			$returnValues['failed_items'] = $failedItems;
		}

		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $orderShipmentId, "tracking_identifier is null");
		if (empty($orderShipmentRow['remote_order_id'])) {
			return false;
		}
		$remoteOrderRow = getRowFromId("remote_orders", "remote_order_id", $orderShipmentRow['remote_order_id']);
		if (empty($remoteOrderRow['order_number'])) {
			return false;
		}
		$orderNumber = $remoteOrderRow['order_number'];

		$responseArray = $this->makeRequest("orders/" . $orderNumber . "/shipments", array());
		if ($responseArray === false) {
			return false;
		}

		if (array_key_exists("error_code", $responseArray)) {
			$this->iErrorMessage = $responseArray['message'];
			return false;
		}

		$trackingInfo = $responseArray['order_shipments'][0]['shipments'][0];
		$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($trackingInfo['carrier']));
		$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
			$trackingInfo['tracking_number'], $shippingCarrierId, $trackingInfo['ship_method'], $orderShipmentId);
		if ($resultSet['affected_rows'] > 0) {
			Order::sendTrackingEmail($orderShipmentId);
			executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingInfo['tracking_number'],
				"Tracking number added by " . $this->iProductDistributorRow['description']);
			return array($orderShipmentId);
		}
		return array();
	}

}
