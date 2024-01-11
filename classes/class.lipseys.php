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

class Lipseys extends ProductDistributor {

	private $iFacetCodes = array("CaliberGauge", "Caliber", "Action", "Barrel Length", "Barrel", "Capacity", "Finish", "Receiver", "Safety", "Sights", "Stock Frame Grips",
		"Magazine", "Chamber", "Drilled And Tapped", "Rate Of Twist", "Optic Magnification", "Maintube Size", "Objective Size", "Adjustable Objective",
		"Optic Adjustments", "Reticle", "Illuminated Reticle");
	private $iFieldTranslations = array("upc_code" => "upc", "product_code" => "itemNo", "description" => "description1", "alternate_description" => "description2",
		"detailed_description" => "description2", "manufacturer_code" => "manufacturer", "model" => "model", "manufacturer_sku" => "manufacturerModelNo", "manufacturer_advertised_price" => "retailMap",
		"width" => "XXXXXX", "length" => "overallLength", "height" => "XXXXXX", "weight" => "shippingWeight", "base_cost" => "currentPrice", "list_price" => "msrp");
	public $iTrackingAlreadyRun = false;
	private $iPresetUrlHeader = "https://www.coreware4f98ba85e91cd0238b68ffd86501c550.com"; // Provided by Lipseys when IP address cannot be whitelisted
	private $iLogging;

	function __construct($locationId) {
		$this->iProductDistributorCode = "LIPSEYS";
		$this->iClass3Products = true;
		$this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_LIPSEYS"));
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		$userToken = $this->getToken();
		if (empty($userToken)) {
			$this->iErrorMessage = "Dealer account credentials are incorrect" . (empty($this->iErrorMessage) ? "" : ": " . $this->iErrorMessage);
			return false;
		}
		$userToken = $this->getToken(
			CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"),
			CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"));
		if (empty($userToken)) {
			$this->iErrorMessage = "Dropship account credentials are incorrect" . (empty($this->iErrorMessage) ? "" : ": " . $this->iErrorMessage);
			return false;
		}
		return true;
	}

	function getData($method, $cacheHours = 1) {
		if (empty($cacheHours)) {
			$catalog = false;
			$cacheHours = 1;
		} else {
			$catalog = getCachedData("lipseys_" . $method, "");
		}
		if (empty($catalog)) {
			$userToken = $this->getToken();

			if (empty($userToken)) {
				$this->iErrorMessage = (empty($this->iErrorMessage) ? "Login failed" : $this->iErrorMessage);
				return false;
			}

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://api.lipseys.com/api/integration/items/" . $method,
				CURLOPT_RETURNTRANSFER => true,
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
					"Host: api.lipseys.com",
					"Token: " . $userToken,
					"PresetUrl: " . $this->iPresetUrlHeader,
					"cache-control: no-cache"
				),
			));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);

			if ($err) {
				$this->iErrorMessage = "curl error: " . $err;
				return false;
			}

			$lipseysProductsArray = json_decode($response, true);

			if (json_last_error() !== JSON_ERROR_NONE) {
				$this->iErrorMessage = "json parsing error: " . json_last_error_msg();
				return false;
			}

            if (!$lipseysProductsArray['authorized'] || (array_key_exists('errors', $lipseysProductsArray)
                    && is_array($lipseysProductsArray['errors']) && in_array("Not Authorized", $lipseysProductsArray['errors']))) {
                $this->iErrorMessage = 'Fetching catalog was not authorized';
                return false;
            }

			if (!$lipseysProductsArray['success']) {
				$this->iErrorMessage = 'Fetching catalog was unsuccessful';
				return false;
			}

			if (!is_array($lipseysProductsArray['data'])) {
				$this->iErrorMessage = "response array does not contain data key";
				return false;
			}

			$catalog = $lipseysProductsArray['data'];
			setCachedData("lipseys_" . $method, "", $catalog, $cacheHours);
		}
		return $catalog;
	}

	function getToken($username = "", $password = "") {
		if (empty($username) || empty($password)) {
			$username = $this->iLocationCredentialRow['user_name'];
			$password = $this->iLocationCredentialRow['password'];
		}
		$curl = curl_init();

		$parameters = array("Email" => $username, "Password" => $password);

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api.lipseys.com/api/Integration/Authentication/login",
			CURLOPT_ENCODING => "",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($parameters),
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"PresetUrl: " . $this->iPresetUrlHeader,
				"cache-control: no-cache"
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			$this->iErrorMessage = "curl error: " . $err;
			return false;
		}

		$responseArray = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->iErrorMessage = "json parsing error: " . json_last_error_msg();
			return false;
		}

		return $responseArray['token'];
	}

	function syncProducts($parameters = array()) {
		$productArray = $this->getData("CatalogFeed", 4);
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
		$productFacetArray = $this->getFacets();

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
				if ($thisProductInfo['fflRequired']) {
					$corewareProductData['serializable'] = 1;
				}
				if ($thisProductInfo['sotRequired']) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if ($thisProductInfo['fflRequired']) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}
			if ($thisProductInfo['sotRequired']) {
				$this->addProductTag($productId, $this->iClass3ProductTagId);
			}

			if (self::$iCorewareShootingSports) {
				$originalImageId = $productRow['image_id'];
				$productRow['image_id'] = getFieldFromId("image_id", "images", "image_id", $productRow['image_id'], "os_filename is not null or file_content is not null");
				if (empty($productRow['image_id']) && !empty($thisProductInfo['imageName'])) {
					if (!empty($originalImageId)) {
						$badImageCount++;
						executeQuery("update images set os_filename = 'CSSC_BAD_IMAGES' where image_id = ? or image_id in (select image_id from product_images where product_id = ?)",$originalImageId,$productRow['product_id']);
						executeQuery("update products set image_id = null where product_id = ?",$productRow['product_id']);
						executeQuery("delete from product_images where product_id = ?",$productRow['product_id']);
						executeQuery("delete from image_data where image_id in (select image_id from images where os_filename = 'CSSC_BAD_IMAGES')");
						executeQuery("delete from images where os_filename = 'CSSC_BAD_IMAGES'");
					}

					$imageUrl = "http://www.lipseyscloud.com/images/" . rawurlencode($thisProductInfo['imageName']);
					$imageId = $imageContents = "";
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $thisProductInfo['imageName'] . (endsWith($thisProductInfo['imageName'], ".jpg") ? "" : ".jpg"), "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "LIPSEYS_IMAGE"));
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

			if (self::$iCorewareShootingSports && !$productCategoryAdded && !empty($thisProductInfo['type'])) {
				$categoryCode = makeCode($thisProductInfo['type']);
				$this->addProductCategories($productId, array($categoryCode));
				$productFacetValues = array();
				foreach ($this->iFacetCodes as $facetDescription) {
					$facetCode = makeCode(str_replace(" ", "", $facetDescription));
					$facetIndex = strtolower(str_replace(" ", "", $facetDescription));
					if (!empty($productFacetArray[$categoryCode]) && !empty($thisProductInfo[$facetIndex]) && array_key_exists($facetCode, $productFacetArray[$categoryCode])) {
						$productFacetValues[] = array("product_facet_code" => $facetCode, "facet_value" => $thisProductInfo[$facetIndex]);
					}
				}
				$this->addProductFacets($productId, $productFacetValues);
			}
		}

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getFacets($parameters = array()) {
		$productArray = $this->getData("CatalogFeed", 4);
		if ($productArray === false) {
			return false;
		}

		$productFacets = array();
		foreach ($productArray as $thisProductInfo) {
			if (empty($thisProductInfo['type'])) {
				continue;
			}
			$categoryCode = makeCode($thisProductInfo['type']);
			if (!array_key_exists($categoryCode, $productFacets)) {
				$productFacets[$categoryCode] = array();
			}
			foreach ($this->iFacetCodes as $facetDescription) {
				$facetCode = makeCode(str_replace(" ", "", $facetDescription));
				if (array_key_exists($facetCode, $productFacets[$categoryCode])) {
					continue;
				}
				if (empty($thisProductInfo[strtolower(str_replace(" ", "", $facetDescription))])) {
					continue;
				}
				$productFacets[$categoryCode][$facetCode] = $facetDescription;
			}
		}
		return $productFacets;
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}
		$quantityAvailable = false;
		$cost = false;

		$pricingQuantityArray = $this->getData("PricingQuantityFeed", false);
		if (!is_array($pricingQuantityArray['items'])) {
			$this->iErrorMessage = "response array does not contain items key";
			return false;
		}
		$productArray = $pricingQuantityArray['items'];

		// Get allocated inventory
		$allocatedPriceQuantityArray = $this->getData("Allocations");
		$allocatedInventory = array();
		if (is_array($allocatedPriceQuantityArray['items'])) {
			$allocatedArray = $allocatedPriceQuantityArray['items'];
			foreach ($allocatedArray as $thisProductInfo) {
				if (empty($thisProductInfo['quantity'])) {
					continue;
				}
				$allocatedInventory[$thisProductInfo['itemNumber']] = $thisProductInfo['quantity'];
			}
		}

		foreach ($productArray as $index => $thisProductInfo) {
			if (array_key_exists($thisProductInfo['itemNumber'], $allocatedInventory)) {
				$productArray[$index]['quantity'] += $allocatedInventory[$thisProductInfo['itemNumber']];
			}
		}

		foreach ($productArray as $thisProductInfo) {
			if ($thisProductInfo['itemNumber'] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['quantity'];
			$cost = $thisProductInfo['currentPrice'];
			break;
		}
		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$pricingQuantityArray = $this->getData("PricingQuantityFeed");
		if (!is_array($pricingQuantityArray['items'])) {
			$this->iErrorMessage = "response array does not contain items key";
			return false;
		}
		$productArray = $pricingQuantityArray['items'];
		$allocatedInventory = array();
		if (empty($parameters['all_clients'])) {
			$allocatedPriceQuantityArray = $this->getData("Allocations");
			if (!is_array($allocatedPriceQuantityArray['items'])) {
				$this->iErrorMessage = "allocated data does not contain items key";
				return false;
			}
			$allocatedArray = $allocatedPriceQuantityArray['items'];
			foreach ($allocatedArray as $thisProductInfo) {
				if (empty($thisProductInfo['quantity'])) {
					continue;
				}
				$allocatedInventory[$thisProductInfo['itemNumber']] = $thisProductInfo['quantity'];
			}
			foreach ($productArray as $index => $thisProductInfo) {
				if (array_key_exists($thisProductInfo['itemNumber'], $allocatedInventory)) {
					$productArray[$index]['quantity'] += $allocatedInventory[$thisProductInfo['itemNumber']];
				}
			}
		}
		$inventoryUpdateArray = array();
		foreach ($productArray as $thisProductInfo) {
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['itemNumber'],
				"quantity" => $thisProductInfo['quantity'], "cost" => $thisProductInfo['currentPrice']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
			if (array_key_exists($thisProductInfo['itemNumber'], $allocatedInventory) && $allocatedInventory[$thisProductInfo['itemNumber']] > 0) {
				$thisInventoryUpdate['allocated'] = true;
			}
		}
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		$productArray = $this->getData("CatalogFeed", 4);
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

	function getCategories($parameters = array()) {
		$productArray = $this->getData("CatalogFeed", 4);
		if ($productArray === false) {
			return false;
		}

		$productCategories = array();
		foreach ($productArray as $thisProductInfo) {
			if (!empty($thisProductInfo['type'])) {
				$productCategories[makeCode($thisProductInfo['type'])] = array("description" => $thisProductInfo['type']);
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

		if (!empty($fflOrderItemRows)) {
			$fflRow = (new FFL($ordersRow['federal_firearms_licensee_id']))->getFFLRow();
			if (empty($fflRow)) {
				$this->iErrorMessage = "No FFL dealer set for this order";
				return false;
			}
		} else {
			$fflRow = array();
		}

		$failedItems = array();
		$returnValues = array();

		if (!empty($customerOrderItemRows)) {
			$userToken = $this->getToken(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"), CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"));
			if (empty($userToken)) {
				$this->iErrorMessage = (empty($this->iErrorMessage) ? "Login failed" : $this->iErrorMessage);
				return false;
			}

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
						"ItemNo" => $thisItemRow['distributor_product_code'],
						"Quantity" => $thisItemRow['quantity']
					);
				}

				$postFields = [
					'Warehouse' => "",
					'PoNumber' => $orderId . "-" . $remoteOrderId,
					'BillingName' => $ordersRow['full_name'],
					'BillingAddressLine1' => $addressRow['address_1'],
					'BillingAddressLine2' => $addressRow['address_2'],
					'BillingAddressCity' => $addressRow['city'],
					'BillingAddressState' => $addressRow['state'],
					'BillingAddressZip' => $addressRow['postal_code'],
					'ShippingName' => $ordersRow['full_name'],
					'ShippingAddressLine1' => $addressRow['address_1'],
					'ShippingAddressLine2' => $addressRow['address_2'],
					'ShippingAddressCity' => $addressRow['city'],
					'ShippingAddressState' => $addressRow['state'],
					'ShippingAddressZip' => $addressRow['postal_code'],
					'MessageForSalesExec' => "",
					'Items' => $sendOrderItems,
					'Overnight' => false
				];
				$curl = curl_init();

				$addressRow['postal_code'] = substr($addressRow['postal_code'], 0, 5);
				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.lipseys.com/api/integration/order/dropship",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($postFields),
					CURLOPT_HTTPHEADER => array(
						"Content-Type: application/json",
						"Token: " . $userToken,
						"PresetUrl: " . $this->iPresetUrlHeader,
						"cache-control: no-cache"
					),
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

				if ($this->iLogging) {
					addDebugLog("Lipseys request: POST https://api.lipseys.com/api/integration/order/dropship"
						. "\nLipseys Data: " . getFirstPart(jsonEncode($postFields), 1000)
						. "\nLipseys Result: " . getFirstPart($response, 1000)
						. (empty($err) ? "" : "\nLipseys Error: " . $err)
						. "\nLipseys HTTP Status: " . $httpCode);
				}

				curl_close($curl);

				if ($err) {
					$returnValues['error_message'] = $this->iErrorMessage = $err;
					foreach ($customerOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} else {
					$result = json_decode($response, true);
					$result = array_combine(array_map('lcfirst', array_keys($result)), array_values($result));
					if (!$result['success']) {
						foreach ($customerOrderItemRows as $thisItemRow) {
							$returnValues['error_message'] = $this->iErrorMessage = (is_array($result['errors']) ? implode(",", $result['errors']) : $result['errors']);
							$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
						}
						executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					} else {
						$orderNumber = $result['data']['orderNumber'];
						foreach ($customerOrderItemRows as $thisItemRow) {
							$foundItem = false;
							foreach ($result['data']['lineItems'] as $thisLineItem) {
								if ($thisItemRow['distributor_product_code'] != $thisLineItem['itemNumber']) {
									continue;
								}
								$foundItem = true;
								if ($thisLineItem['fulfilledQuantity'] > 0) {
									executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
										$remoteOrderId, $thisItemRow['order_item_id'], $thisLineItem['fulfilledQuantity']);
								}
								if ($thisLineItem['fulfilledQuantity'] < $thisItemRow['quantity']) {
									$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['fulfilledQuantity']);
								}
							}
							if (!$foundItem) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
							}
						}
						executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
						$returnValues['customer'] = array("remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $ordersRow['full_name']);
					}
				}

			}
		}

		if (!empty($fflOrderItemRows)) {
			$userToken = $this->getToken(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"), CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"));
			if (empty($userToken)) {
				$this->iErrorMessage = (empty($this->iErrorMessage) ? "Login failed" : $this->iErrorMessage);
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

				$sendOrderItems = array();
				foreach ($fflOrderItemRows as $thisItemRow) {
					$sendOrderItems[] = array(
						"ItemNo" => $thisItemRow['distributor_product_code'],
						"Quantity" => $thisItemRow['quantity']
					);
				}

				$postFields = array(
					'Ffl' => str_replace("-", "", $fflRow['license_number']),
					'Po' => $orderId . "-" . $remoteOrderId,
					'Name' => $ordersRow['full_name'],
					'Phone' => $ordersRow['phone_number'],
					'Items' => $sendOrderItems,
				);

				$curl = curl_init();

				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.lipseys.com/api/integration/order/dropshipfirearm",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($postFields),
					CURLOPT_HTTPHEADER => array(
						"Content-Type: application/json",
						"Token: " . $userToken,
						"PresetUrl: " . $this->iPresetUrlHeader,
						"cache-control: no-cache"
					),
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

				if ($this->iLogging) {
					addDebugLog("Lipseys request: POST https://api.lipseys.com/api/integration/order/dropshipfirearm"
						. "\nLipseys Data: " . getFirstPart(jsonEncode($postFields), 1000)
						. "\nLipseys Result: " . getFirstPart($response, 1000)
						. (empty($err) ? "" : "\nLipseys Error: " . $err)
						. "\nLipseys HTTP Status: " . $httpCode);
				}

				curl_close($curl);

				if ($err) {
					$returnValues['error_message'] = $this->iErrorMessage = $err;
					foreach ($fflOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} else {
					$result = json_decode($response, true);
					$result = array_combine(array_map('lcfirst', array_keys($result)), array_values($result));
					if (!$result['success']) {
						if (is_array($result['errors']) && in_array("Not DropShip Account", $result['errors'])) {
							$dealerOrderItemRows = array_merge($dealerOrderItemRows, $fflOrderItemRows);
						} else {
							foreach ($fflOrderItemRows as $thisItemRow) {
								$returnValues['error_message'] = $this->iErrorMessage = (is_array($result['errors']) ? implode(",", $result['errors']) : $result['errors']);
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
							}
						}
						executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					} else {
						if (!empty($fflRow['license_number']) && !empty($fflRow['file_id'])) {
							$subject = $this->iLocationCredentialRow['customer_number'] . " - " . $orderId . " - " . $fflRow['license_number'];
							$body = "FFL Contact: " . (empty($fflRow['licensee_name']) ? $fflRow['business_name'] : $fflRow['licensee_name']) . "<br>FFL Phone: " . $fflRow['phone_number'];
							sendEmail(array("body" => $body, "subject" => $subject, "email_address" => "validateffl@lipseys.com", "attachment_file_id" => $fflRow['file_id']));
						}
						$orderNumber = $result['orderNumber'];
						foreach ($fflOrderItemRows as $thisItemRow) {
							$foundItem = false;
							foreach ($result['data']['lineItems'] as $thisLineItem) {
								if ($thisItemRow['distributor_product_code'] != $thisLineItem['itemNumber']) {
									continue;
								}
								$foundItem = true;
								if ($thisLineItem['fulfilledQuantity'] > 0) {
									executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
										$remoteOrderId, $thisItemRow['order_item_id'], $thisLineItem['fulfilledQuantity']);
								}
								if ($thisLineItem['fulfilledQuantity'] < $thisItemRow['quantity']) {
									$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['fulfilledQuantity']);
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
		}

		if (!empty($dealerOrderItemRows)) {
			$userToken = $this->getToken();
			if (empty($userToken)) {
				$this->iErrorMessage = (empty($this->iErrorMessage) ? "Login failed" : $this->iErrorMessage);
				return false;
			}

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
						"ItemNo" => $thisItemRow['distributor_product_code'],
						"Quantity" => $thisItemRow['quantity']
					);
				}

				$postFields = array(
					'PONumber' => $orderId . "-" . $remoteOrderId,
					'EmailConfirmation' => true,
					'Items' => $sendOrderItems,
				);

				// AllocationOrder will fill orders from normal inventory first and then look at allocated inventory, so it is safe to use for all clients.

				$curl = curl_init();

				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.lipseys.com/api/integration/order/AllocationOrder",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($postFields),
					CURLOPT_HTTPHEADER => array(
						"Content-Type: application/json",
						"Token: " . $userToken,
						"PresetUrl: " . $this->iPresetUrlHeader,
						"cache-control: no-cache"
					),
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

				if ($this->iLogging) {
					addDebugLog("Lipseys request: POST https://api.lipseys.com/api/integration/order/AllocationOrder"
						. "\nLipseys Data: " . getFirstPart(jsonEncode($postFields), 1000)
						. "\nLipseys Result: " . getFirstPart($response, 1000)
						. (empty($err) ? "" : "\nLipseys Error: " . $err)
						. "\nLipseys HTTP Status: " . $httpCode);
				}

				curl_close($curl);

				if ($err) {
					$returnValues['error_message'] = $this->iErrorMessage = $err;
					foreach ($dealerOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				} else {
					$result = json_decode($response, true);
					$result = array_combine(array_map('lcfirst', array_keys($result)), array_values($result));
					if (!$result['success']) {
						foreach ($dealerOrderItemRows as $thisItemRow) {
							$returnValues['error_message'] = $this->iErrorMessage = (is_array($result['errors']) ? implode(",", $result['errors']) : $result['errors']);
							$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
						}
						executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					} else {
						$orderNumber = "";
						foreach ($dealerOrderItemRows as $thisItemRow) {
							$foundItem = false;
							foreach ($result['data'] as $thisLineItem) {
								if (empty($orderNumber)) {
									$orderNumber = $thisLineItem['orderNumber'];
								}
								if ($thisItemRow['distributor_product_code'] != $thisLineItem['itemNumber']) {
									continue;
								}
								$foundItem = true;
								if ($thisLineItem['fulfilledQuantity'] > 0) {
									executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
										$remoteOrderId, $thisItemRow['order_item_id'], $thisLineItem['fulfilledQuantity']);
								}
								if ($thisLineItem['fulfilledQuantity'] < $thisItemRow['quantity']) {
									$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['fulfilledQuantity']);
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
		$userToken = $this->getToken();
		if (empty($userToken)) {
			$this->iErrorMessage = (empty($this->iErrorMessage) ? "Login failed" : $this->iErrorMessage);
			return false;
		}

		$sendOrderItems = array();
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$sendOrderItems[] = array(
				"ItemNo" => $thisItemRow['distributor_product_code'],
				"Quantity" => $thisItemRow['quantity'],
				"Note" => $thisItemRow['notes']
			);
		}

		$postFields = array(
			'PONumber' => $orderPrefix . $distributorOrderId,
			'EmailConfirmation' => true,
			'Items' => $sendOrderItems,
		);

		// AllocationOrder will fill orders from normal inventory first and then look at allocated inventory, so it is safe to use for all clients.
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api.lipseys.com/api/integration/order/AllocationOrder",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($postFields),
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json",
				"Token: " . $userToken,
				"PresetUrl: " . $this->iPresetUrlHeader,
				"cache-control: no-cache"
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if ($this->iLogging) {
			addDebugLog("Lipseys request: POST https://api.lipseys.com/api/integration/order/AllocationOrder"
				. "\nLipseys Data: " . getFirstPart(jsonEncode($postFields), 1000)
				. "\nLipseys Result: " . getFirstPart($response, 1000)
				. (empty($err) ? "" : "\nLipseys Error: " . $err)
				. "\nLipseys HTTP Status: " . $httpCode);
		}

		curl_close($curl);

		if ($err) {
			$this->iErrorMessage = $err;
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}
		$result = json_decode($response, true);
		$result = array_combine(array_map('lcfirst', array_keys($result)), array_values($result));
		if (!$result['success']) {
			$this->iErrorMessage = (is_array($result['errors']) ? implode(",", $result['errors']) : $result['errors']);
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		} else {
			$orderNumber = "";
			$productIds = array();
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$foundItem = false;
				foreach ($result['data'] as $thisLineItem) {
					if (empty($orderNumber)) {
						$orderNumber = $thisLineItem['orderNumber'];
					}
					if ($thisItemRow['distributor_product_code'] != $thisLineItem['itemNumber']) {
						continue;
					}
					$foundItem = true;
					if ($thisLineItem['fulfilledQuantity'] > 0) {
						executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
							$distributorOrderId, $thisItemRow['product_id'], $thisItemRow['quantity'], $thisItemRow['notes']);
						$productIds[] = $thisItemRow['product_id'];
					}
					if ($thisLineItem['fulfilledQuantity'] < $thisItemRow['quantity']) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['fulfilledQuantity']);
					}
				}
				if (!$foundItem) {
					$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
			}
			executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $orderNumber, $distributorOrderId);
		}

		$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $orderNumber, "product_ids" => $productIds);
		$GLOBALS['gPrimaryDatabase']->commitTransaction();

		if (!empty($failedItems)) {
			$returnValues['failed_items'] = $failedItems;
		}

		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		if ($this->iTrackingAlreadyRun) {
			return false;
		}
		$this->iTrackingAlreadyRun = true;
		$userTokens = array();
		$userTokens[] = $this->getToken();
		$userTokens[] = $this->getToken(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"), CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"));

		foreach ($userTokens as $userToken) {
			$returnValues = array();
			for ($x = 1; $x <= 3; $x++) {
				$date = date("m/d/Y", strtotime("-" . $x . " day"));

				$curl = curl_init();

				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://api.lipseys.com/api/integration/shipping/oneday",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => "",
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => json_encode($date),
					CURLOPT_HTTPHEADER => array(
						"Content-Type: application/json",
						"Token: " . $userToken,
						"PresetUrl: " . $this->iPresetUrlHeader,
						"cache-control: no-cache"
					),
				));

				$response = curl_exec($curl);
				$err = curl_error($curl);
				$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

				if ($this->iLogging) {
					addDebugLog("Lipseys request: POST https://api.lipseys.com/api/integration/shipping/oneday"
						. "\nLipseys Data: " . $date
						. "\nLipseys Result: " . getFirstPart($response, 1000)
						. (empty($err) ? "" : "\nLipseys Error: " . $err)
						. "\nLipseys HTTP Status: " . $httpCode);
				}

				curl_close($curl);

				if ($err) {
					$this->iErrorMessage = "cURL Error #:" . $err;
					return false;
				}
				$result = json_decode($response, true);
				$resultArray = array_combine(array_map('lcfirst', array_keys($result)), array_values($result));

				if (!empty($resultArray) && array_key_exists("success", $resultArray) && $resultArray['success'] && array_key_exists("data", $resultArray)) {
					$trackingInformation = $resultArray['data'];

					foreach ($trackingInformation as $thisTrackingInfo) {
						if (empty($thisTrackingInfo)) {
							continue;
						}
						$parts = explode("-", $thisTrackingInfo['poNumber']);
						$orderId = $parts[0];
						$remoteOrderId = $parts[1];
						$resultSet = executeQuery("select order_shipment_id from order_shipments where remote_order_id = ? and location_id = ? and " .
							"tracking_identifier is null and order_id = ? and order_id in (select order_id from orders where client_id = ?)",
							$remoteOrderId, $this->iLocationId, $orderId, $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$shippingCarrierCode = explode(" ", $thisTrackingInfo['shippingService'])[0];
							$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($shippingCarrierCode));
							$trackingIdentifier = $thisTrackingInfo['trackingNumber'];
							$carrierDescription = $thisTrackingInfo['shippingService'];
							$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
								$trackingIdentifier, $shippingCarrierId, $carrierDescription, $row['order_shipment_id']);
							if ($resultSet['affected_rows'] > 0) {
								Order::sendTrackingEmail($row['order_shipment_id']);
								$returnValues[] = $row['order_shipment_id'];
							}
						}
						freeResult($resultSet);
					}
				}
			}
		}
		return $returnValues;
	}
}
