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

class SecondAmendmentWholesale extends ProductDistributor {

	private $iFieldTranslations = array("quantity" => "inventory_qty", "upc_code" => "upc", "product_code" => "stock_number", "description" => "product_description",
		"alternate_description" => "xxxx", "detailed_description" => "description", "manufacturer_code" => "manufacturer_name", "model" => "model",
		"manufacturer_sku" => "mpn", "manufacturer_advertised_price" => "retail_map", "width" => "shipping_width", "length" => "shipping_length", "height" => "shipping_height",
		"weight" => "weight", "base_cost" => "dealer_price", "list_price" => "retail_price", "image_location" => "image",
		"category" => "department_number", "drop_ship_flag" => "blocked_from_drop_ship");

	private $iCategoryCodes = array(1 => "Handguns", 2 => "Used Handguns", 3 => "Used Long Guns", 4 => "Tasers", 5 => "Long Guns", 6 => "NFA Products", 7 => "Reserved",
		8 => "Optics", 9 => "Optical Accessories", 10 => "Magazines", 11 => "Grips, Pads, Stocks, Bipods", 12 => "Soft Gun Cases, Packs, Bags",
		13 => "Misc. Accessories", 14 => "Holsters & Pouches", 15 => "Reloading Equipment", 16 => "Reserved", 17 => "Reserved",
		18 => "Ammunition", 19 => "Survival Supplies", 20 => "Lights, Lasers, & Batteries", 21 => "Cleaning Equipment", 22 => "Airguns",
		23 => "Knives & Tools", 24 => "High Capacity Magazines", 25 => "Safes & Security", 26 => "Safety & Protection", 27 => "Non-Lethal Defense",
		28 => "Reserved", 29 => "Reserved", 30 => "Sights", 31 => "Reserved", 32 => "Barrels, Choke Tubes, & Muzzle Devices",
		33 => "Clothing", 34 => "Parts", 35 => "Slings & Swivels", 36 => "Electronics", 37 => "Not Used",
		38 => "Books, Software, & DVDs", 39 => "Targets", 40 => "Hard Gun Cases", 41 => "Upper Receivers & Conversion Kits", 42 => "SBR Barrels & Upper Receivers",
		43 => "Upper Receivers & Conversion Kits - High Capacity");
	private $iFacets = array("action_type" => 'Action', "barrel_type" => 'Type of Barrel', "barrel_length" => 'Barrel Length',
		"catalog_code" => 'Catalog Code', "chamber" => 'Chamber', "chokes" => 'Chokes', "condition" => 'Condition',
		"capacity" => 'Capacity', "dram" => 'Dram', "edge" => 'Edge', "firing_casing" => 'Firing casing', "finish_color" => 'Finish/Color',
		"fit1" => 'Fit', "fit2" => 'Fit', "feet_per_second" => 'Feet per second', "frame_material" => 'Frame/Material',
		"caliber1" => 'Caliber', "caliber2" => 'Caliber', "grain_weight" => 'Grain Weight', "grips_stock" => 'Grips/Stock', "hand" => 'Hand',
		"manufacturer_part_number" => 'Manufacturer part #', "manufacturer_weight" => 'Manufacturer weight', "moa" => 'MOA', "model1" => 'Model',
		"model2" => 'Model', "new_stock_number" => 'New Stock #', "national_stock_number" => 'NSN (National Stock #)', "objective" => 'Objective',
		"ounce_of_shot" => 'Ounce of shot', "packaging" => 'Packaging', "power" => 'Power', "reticle" => 'Reticle',
		"safety" => 'Safety', "sights" => 'Sights', "size" => 'Size', "type" => 'Type', "units_per_box" => 'Units per box',
		"units_per_case" => 'Units per case', "wt_characteristics" => 'Wt Characteristics');
	public $iTrackingAlreadyRun = false;
	private $iTestUrl = "https://staging.2ndamendmentwholesale.com/";
	private $iLiveUrl = "https://www.2ndamendmentwholesale.com/";
	private $iImagePath = "media/catalog/product/";
	private $iUseUrl;
	private $iDropshipToFFL = true;
	private $iDropshipToCustomer = true;
	private $iToken = "";
	private $iLogging;

	function __construct($locationId) {
		$this->iProductDistributorCode = "SECONDAMENDMENTWHOLESALE";
		if ($GLOBALS['gDevelopmentServer']) {
			$this->iUseUrl = $this->iTestUrl;
		} else {
			$this->iUseUrl = $this->iLiveUrl;
		}
		$this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_SECONDAMENDMENT"));
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	private function setToken() {
		$response = $this->postApi("rest/V1/integration/customer/token", array('username' => $this->iLocationCredentialRow['user_name'],
			'password' => $this->iLocationCredentialRow['password'], 'get_token' => true));
		if (!$response || (is_array($response) && array_key_exists("message", $response))) {
			$this->iErrorMessage = $this->iErrorMessage ?: $response['message'];
			$this->iErrorMessage = $this->iErrorMessage ?: "Error: get Token failed";
			return false;
		}
		$this->iToken = $response;
		return true;
	}

	private function postApi($method, $parameters = array()) {
		$header = array('Content-Type: application/json', 'Accept: application/json');
		if ($parameters['get_token']) {
			unset($parameters['get_token']);
		} else {
			if (empty($this->iToken) && !$this->setToken()) {
				return false;
			}
			$header[] = "Authorization: Bearer " . $this->iToken;
		}
		$put = !empty($parameters['put']);
		unset($parameters['put']);
		$hostUrl = $this->iUseUrl . ltrim($method, "/");
		$postParameters = jsonEncode($parameters);
		$ch = curl_init();
		if ($put) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		} else {
			curl_setopt($ch, CURLOPT_POST, 1);
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		if ($this->iLogging) {
			addDebugLog("2nd Amendment request: " . ($put ? "PUT " : "POST ") . $hostUrl
				. "\n2nd Amendment Data: " . getFirstPart($postParameters, 1000)
				. "\n2nd Amendment Result: " . getFirstPart($response, 1000)
				. (empty($err) ? "" : "\n2nd Amendment Error: " . $err)
				. "\n2nd Amendment HTTP Status: " . $httpCode);
		}
		if (empty($response)) {
			if ($httpCode == 403) {
				$this->iErrorMessage = "Unable to access API. IP address may need to be whitelisted with distributor";
			} else {
				$this->iErrorMessage = $err ?: "Unknown API error";
			}
			return false;
		}
		try {
			$responseArray = json_decode($response, true);
		} catch (Exception $e) {
			$this->iErrorMessage = $e->getMessage();
			$responseArray = array();
		}
		if ($httpCode >= 400) {
			$this->iErrorMessage = $this->iErrorMessage ?: $responseArray['message'];
			return false;
		}
		return $responseArray;
	}

	private function getApi($method, $delete = false) {
		if (empty($this->iToken) && !$this->setToken()) {
			return false;
		}
		$header = array('Content-Type: application/json', 'Accept: application/json', "Authorization: Bearer " . $this->iToken);
		$hostUrl = $this->iUseUrl . ltrim($method, "/");
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
		if ($delete) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		if ($this->iLogging) {
			addDebugLog("2nd Amendment request: " . ($delete ? "DELETE " : "GET ") . $hostUrl
				. "\n2nd Amendment Result: " . getFirstPart($response, 1000)
				. (empty($err) ? "" : "\n2nd Amendment Error: " . $err)
				. "\n2nd Amendment HTTP Status: " . $httpCode);
		}
		if (empty($response)) {
			if ($httpCode == 403) {
				$this->iErrorMessage = "Unable to access API. IP address may need to be whitelisted with distributor";
			} else {
				$this->iErrorMessage = $err ?: "Unknown API error";
			}
			return false;
		}
		try {
			$responseArray = json_decode($response, true);
		} catch (Exception $e) {
			$this->iErrorMessage = $e->getMessage();
			$responseArray = array();
		}
		if ($httpCode >= 400) {
			$this->iErrorMessage = $this->iErrorMessage ?: $responseArray['message'];
			return false;
		}
		return $responseArray;
	}

	function testCredentials() {
		return $this->setToken();
	}

	function getProducts() {
		$productArray = array();
		$rawProducts = $this->getApi("rest/V1/feed/product");
		foreach ($rawProducts as $thisProduct) {
			$productArray[$thisProduct[$this->iFieldTranslations['product_code']]] = $thisProduct;
		}
		$attributeArray = $this->getApi("rest/V1/feed/attributes");
		foreach ($attributeArray as $thisAttributeSet) {
			$productArray[$thisAttributeSet['sku']]['facets'] = $thisAttributeSet;
		}

		return $productArray;
	}

	function syncProducts($parameters = array()) {
		$productArray = $this->getProducts();
		if (empty($productArray)) {
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

		$states = getStateArray(true);
		$states["DC"] = "District of Columbia";

		$fflCategoryCodes = array("Handguns", "Used Handguns", "Used Long Guns", "Tasers", "Long Guns", "NFA Products");
		$class3CategoryCodes = array("NFA Products");

		$insertCount = 0;
		$productIdsProcessed = array();

		$processCount = 0;
		$foundCount = 0;
		$updatedCount = 0;
		$noUpc = 0;
		$imageCount = 0;
		$duplicateProductCount = 0;
		$badImageCount = 0;

		foreach ($productArray as $thisProductInfo) {
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

			self::loadValues('distributor_product_codes');
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
				$manufacturerCode = makeCode($thisProductInfo[$this->iFieldTranslations['manufacturer_code']]);
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
				$description = str_replace("\n", " ", $description);
				$description = str_replace("\r", " ", $description);
				while (strpos($description, "  ") !== false) {
					$description = str_replace("  ", " ", $description);
				}

				$detailedDescription = $thisProductInfo[$this->iFieldTranslations['detailed_description']];

				$cost = $thisProductInfo[$this->iFieldTranslations['base_cost']];
				$cost = ($cost > 9999 ? 0 : $cost); // ignore products with $10,000 base cost
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

			if (self::$iCorewareShootingSports) {
				$newStates = array();
				foreach ($states as $thisState => $stateName) {
					$stateField = strtolower($thisState . "_restricted");
					if (!empty($thisProductInfo[$stateField])) {
						$newStates[] = $thisState;
					}
				}

				$this->syncStates($productId, $newStates);
			} else {
				$this->syncStates($productId, $corewareProductData['restricted_states']);
			}

			$corewareProductData['product_manufacturer_id'] = $productManufacturerId;
			$corewareProductData['remote_identifier'] = $remoteIdentifier;

			if (empty($corewareProductData['serializable'])) {
				$corewareProductData['serializable'] = $productRow['serializable'];
				if (in_array($this->iCategoryCodes[$thisProductInfo[$this->iFieldTranslations['category']]], $fflCategoryCodes)) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if (in_array($this->iCategoryCodes[$thisProductInfo[$this->iFieldTranslations['category']]], $fflCategoryCodes)) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}
			if (in_array($this->iCategoryCodes[$thisProductInfo[$this->iFieldTranslations['category']]], $class3CategoryCodes)) {
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

					if (!empty($thisProductInfo['image'])) {
						$imageUrl = $this->iUseUrl . $this->iImagePath . substr($thisProductInfo['image'], 0, 1) . "/"
							. substr($thisProductInfo['image'], 1, 1) . "/" . $thisProductInfo['image'];
						$imageId = "";
						$imageContents = file_get_contents($imageUrl);
						if (!empty($imageContents)) {
							$imageFilename = "image" . $thisProductInfo['product_id'] . ".jpg";
							$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $imageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description']));
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
				$categoryCode = makeCode($this->iCategoryCodes[$thisProductInfo[$this->iFieldTranslations['category']]]);
				$this->addProductCategories($productId, array($categoryCode));
				if (!empty($thisProductInfo['facets'])) { // Add facets
					$productFacets = array();
					foreach ($thisProductInfo['facets'] as $facetCode => $facetValue) {
						if (!array_key_exists($facetCode, $this->iFacets) || empty($facetValue)) {
							continue;
						}
						$thisProductFacet = array();
						$thisProductFacet['product_facet_code'] = makeCode(str_replace(" ", "", $facetCode));
						$thisProductFacet['facet_value'] = $facetValue;
						$productFacets[] = $thisProductFacet;
					}
					$this->addProductFacets($productId, $productFacets);
				}
			}
		}

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}

		$productData = $this->getProducts();
		$productArray = $this->getApi("rest/V1/feed/stock");
		if (empty($productArray) || !is_array($productArray)) {
			return false;
		}

		$quantityAvailable = false;
		$cost = false;

		foreach ($productArray as $thisProductInfo) {
			if ($distributorProductCode != $thisProductInfo['sku']) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['qty'];
			$cost = $productData[$thisProductInfo['sku']][$this->iFieldTranslations['base_cost']];
		}

		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$productData = $this->getProducts();
		$productArray = $this->getApi("rest/V1/feed/stock");
		if (empty($productArray) || !is_array($productArray)) {
			return false;
		}
		$inventoryUpdateArray = array();

		foreach ($productArray as $thisProductInfo) {
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['sku'],
				"quantity" => $thisProductInfo['qty'],
				"cost" => $productData[$thisProductInfo['sku']][$this->iFieldTranslations['base_cost']]);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		$productArray = $this->getApi("rest/V1/feed/product");
		$manufacturerArray = array();
		foreach ($productArray as $thisProduct) {
			$manufacturerName = $thisProduct[$this->iFieldTranslations['manufacturer_code']];
			if (!in_array($manufacturerName, $manufacturerArray)) {
				$manufacturerArray[makeCode($manufacturerName)] = $manufacturerName;
			}
		}
		if (empty($manufacturerArray)) {
			return false;
		}

		$productManufacturers = array();
		foreach ($manufacturerArray as $thisManufacturerCode => $thisManufacturer) {
			$productManufacturers[$thisManufacturerCode] = array("business_name" => $thisManufacturer);
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
		$categories = array();
		foreach ($this->iCategoryCodes as $thisCategory) {
			$categories[makeCode($thisCategory)] = array("description" => $thisCategory);
		}
		return $categories;
	}

	function sortCategories($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}

	function getFacets($parameters = array()) {
		$productArray = $this->getProducts();
		if (empty($productArray)) {
			return false;
		}

		$productFacets = array();
		foreach ($productArray as $thisProduct) {
			$productCategoryCode = makeCode($this->iCategoryCodes[$thisProduct[$this->iFieldTranslations['category']]]);
			if (!array_key_exists($productCategoryCode, $productFacets)) {
				$productFacets[$productCategoryCode] = array();
			}
			foreach ($thisProduct['facets'] as $thisFacetCode => $thisFacetValue) {
				if (!array_key_exists($thisFacetCode, $this->iFacets)) {
					continue;
				}
				if (!empty($thisFacetValue)) {
					$productFacets[$productCategoryCode][$thisFacetCode] = $this->iFacets[$thisFacetCode];
				}
			}
		}
		return $productFacets;
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
		if (!$this->iDropshipToCustomer) {
			$dealerOrderItemRows = array_merge($dealerOrderItemRows, $customerOrderItemRows);
			$customerOrderItemRows = array();
		}
		if (!$this->iDropshipToFFL) {
			$dealerOrderItemRows = array_merge($dealerOrderItemRows, $fflOrderItemRows);
			$fflOrderItemRows = array();
		}

		$fflOrderParameters = array();
		$fflNote = "";
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
			$fflOrderParameters['order_type'] = "ffl";
			$fflOrderParameters['license_lookup'] = $fflRow['license_lookup'];
			$fflOrderParameters['license_number'] = $fflRow['license_number'];
			$fflOrderParameters['first_name'] = $contactRow['first_name'];
			$fflOrderParameters['last_name'] = $contactRow['last_name'];
			$fflOrderParameters['business_name'] = $fflRow['business_name'];
			$fflOrderParameters['phone_number'] = $ordersRow['phone_number'];
			$fflOrderParameters['address_1'] = $fflRow[$addressPrefix . "address_1"];
			$fflOrderParameters['address_2'] = $fflRow[$addressPrefix . "address_2"];
			$fflOrderParameters['city'] = $fflRow[$addressPrefix . "city"];
			$fflOrderParameters['state'] = $fflRow[$addressPrefix . "state"];
			$fflOrderParameters['postal_code'] = $fflRow[$addressPrefix . "postal_code"];
			$fflOrderParameters['country_id'] = $fflRow[$addressPrefix . "country_id"];
			$fflOrderParameters['email_address'] = $contactRow['email_address'];
			$fflOrderParameters['file_id'] = $fflRow['file_id'];
			$fflOrderParameters['sot_file_id'] = $fflRow['sot_file_id'];
			$fflOrderParameters['purchase_order_number'] = $orderId . "-" . "%remote_order_id%";
			$fflOrderParameters['order_items'] = array();
			foreach ($fflOrderItemRows as $thisItemRow) {
				$thisOrderItem = array();
				$thisOrderItem['product_id'] = $thisItemRow['distributor_product_code'];
				$thisOrderItem['quantity'] = $thisItemRow['quantity'];
				$fflOrderParameters['order_items'][] = $thisOrderItem;
			}
			$fflNote = sprintf("Dropship to FFL: %s (%s) for customer %s %s", $fflRow['business_name'], $fflRow['license_number'],
				$ordersRow['full_name'], $ordersRow['phone_number']);
		}

		$customerOrderParameters = array();
		if (!empty($customerOrderItemRows)) {
			$customerOrderParameters['order_type'] = "customer";
			$customerOrderParameters['first_name'] = $contactRow['first_name'];
			$customerOrderParameters['last_name'] = $contactRow['last_name'];
			$customerOrderParameters['phone_number'] = $ordersRow['phone_number'];
			$customerOrderParameters['address_1'] = $addressRow["address_1"];
			$customerOrderParameters['address_2'] = $addressRow["address_2"];
			$customerOrderParameters['city'] = $addressRow["city"];
			$customerOrderParameters['state'] = $addressRow["state"];
			$customerOrderParameters['postal_code'] = $addressRow["postal_code"];
			$customerOrderParameters['country_id'] = $addressRow["country_id"];
			$customerOrderParameters['email_address'] = $contactRow['email_address'];
			$customerOrderParameters['purchase_order_number'] = $orderId . "-" . "%remote_order_id%";
			$customerOrderParameters['order_items'] = array();
			foreach ($customerOrderItemRows as $thisItemRow) {
				$thisOrderItem = array();
				$thisOrderItem['product_id'] = $thisItemRow['distributor_product_code'];
				$thisOrderItem['quantity'] = $thisItemRow['quantity'];
				$customerOrderParameters['order_items'][] = $thisOrderItem;
			}
		}

		$dealerOrderParameters = array();
		if (!empty($dealerOrderItemRows)) {
			$dealerOrderParameters['order_type'] = "dealer";
			$dealerOrderParameters['first_name'] = $contactRow['first_name'];
			$dealerOrderParameters['last_name'] = $contactRow['last_name'];
			$dealerOrderParameters['business_name'] = $this->iLocationContactRow["business_name"];
			$dealerOrderParameters['phone_number'] = $ordersRow['phone_number'];
			$dealerOrderParameters['address_1'] = $this->iLocationContactRow["address_1"];
			$dealerOrderParameters['address_2'] = $this->iLocationContactRow["address_2"];
			$dealerOrderParameters['city'] = $this->iLocationContactRow["city"];
			$dealerOrderParameters['state'] = $this->iLocationContactRow["state"];
			$dealerOrderParameters['postal_code'] = $this->iLocationContactRow["postal_code"];
			$dealerOrderParameters['country_id'] = $this->iLocationContactRow["country_id"];
			$dealerOrderParameters['email_address'] = $contactRow['email_address'];
			$dealerOrderParameters['purchase_order_number'] = $orderId . "-" . "%remote_order_id%";
			$dealerOrderParameters['order_items'] = array();
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$thisOrderItem = array();
				$thisOrderItem['product_id'] = $thisItemRow['distributor_product_code'];
				$thisOrderItem['quantity'] = $thisItemRow['quantity'];
				$dealerOrderParameters['order_items'][] = $thisOrderItem;
			}
		}

		$returnValues = array();

# Submit the orders

		if (!empty($fflOrderParameters)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			foreach ($fflOrderParameters as $index => $value) {
				if (is_scalar($value)) {
					$fflOrderParameters[$index] = str_replace("%remote_order_id%", $remoteOrderId, $value);
				}
			}
			foreach ($fflOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$orderReturn = $this->sendOrder($fflOrderParameters);
			if ($orderReturn['result'] != "OK") {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['ffl'] = array("error_message" => $orderReturn['error_message']);
			} else {
				executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderReturn['order_id'], $remoteOrderId);
				$this->postApi("rest/V1/order/" . $orderReturn['order_id'] . "/comments", array("comment" => $fflNote));
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $orderReturn['order_id'],
					"ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));
				if (!empty($fflRow['file_id'])) {
					sendEmail(array("subject" => sprintf("Order #%s (PO %s)", $orderReturn['order_id'], $fflOrderParameters['purchase_order_number']),
						"email_address" => "dropship@2ndamendmentwholesale.com", "body" => $fflNote . " License file is attached.", "attachment_file_id" => $fflRow['file_id']));
				} else {
					sendEmail(array("subject" => "FFL file missing for order ID " . $orderId, "body" => "<p>FFL file for " . $fflRow['licensee_name'] . " for Order ID " . $orderId
						. " was not available when order was submitted to 2nd Amendment Wholesale.<br>The order was placed, but the FFL file will need to be emailed to dropship@2ndamendmentwholesale.com.",
						"notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
				}
			}
		}

		if (!empty($customerOrderParameters)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			foreach ($customerOrderParameters as $index => $value) {
				if (is_scalar($value)) {
					$customerOrderParameters[$index] = str_replace("%remote_order_id%", $remoteOrderId, $value);
				}
			}
			foreach ($customerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$orderReturn = $this->sendOrder($customerOrderParameters);
			if ($orderReturn['result'] != "OK") {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['customer'] = array("error_message" => $orderReturn['error_message']);
			} else {
				executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderReturn['order_id'], $remoteOrderId);
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $orderReturn['order_id'], "ship_to" => $ordersRow['full_name']);
			}
		}

		if (!empty($dealerOrderParameters)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			foreach ($dealerOrderParameters as $index => $value) {
				if (is_scalar($value)) {
					$dealerOrderParameters[$index] = str_replace("%remote_order_id%", $remoteOrderId, $value);
				}
			}
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$orderReturn = $this->sendOrder($dealerOrderParameters);
			if ($orderReturn['result'] != "OK") {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['dealer'] = array("error_message" => $orderReturn['error_message']);
			} else {
				executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderReturn['order_id'], $remoteOrderId);
				$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderReturn['order_id'], "ship_to" => $GLOBALS['gClientName']);
			}
		}
		return $returnValues;
	}

	private function sendOrder($orderParameters) {
		$billingAddress = $this->iLocationContactRow;
		$billingAddress['first_name'] = $billingAddress['first_name'] ?: "n/a";
		$billingAddress['last_name'] = $billingAddress['last_name'] ?: "n/a";
		$cartResponse = $this->getApi("rest/V1/carts/mine");
		// Get Cart ID
		if ($cartResponse) {
			$cartId = $cartResponse['id'];
			// if Cart already exists, make sure it's empty
			if ($cartResponse['items_count'] > 0) {
				foreach ($cartResponse['items'] as $thisItem) {
					$this->getApi("rest/V1/carts/mine/items/" . $thisItem['item_id'], true);
				}
			}
		} else {
			$cartId = $this->postApi("rest/V1/carts/mine");
		}
		// Add items to Cart
		foreach ($orderParameters['order_items'] as $thisOrderItem) {
			if (!is_array($thisOrderItem)) {
				$GLOBALS['gPrimaryDatabase']->logError("Second Amendment Wholesale: non-array passed as order_item: " . jsonEncode($thisOrderItem));
				if ($this->iLogging) {
					addDebugLog("2nd Amendment PlaceOrder: non-array passed as order_item: " . jsonEncode($thisOrderItem));
				}
				continue;
			}
			$result = $this->postApi("rest/V1/carts/mine/items", array("cartItem" => array(
				"sku" => $thisOrderItem['product_id'],
				"qty" => $thisOrderItem['quantity'],
				"quoteId" => $cartId)));
			if (!$result) {
				return array("result" => "error", "error_message" => $this->iErrorMessage);
			}
		}
		// set PO number
		$this->postApi("rest/V1/carts/mine/set-po-number", array("put" => true,
			"cartId" => $cartId,
			"poNumber" => array("poNumber" => $orderParameters['purchase_order_number'])
		));
		// Get region code for shipping address
		$regions = $this->getApi("rest/all/V1/directory/countries/US");
		$region = false;
		foreach ($regions['available_regions'] as $thisRegion) {
			if ($thisRegion['code'] == $orderParameters['state'] || $thisRegion['name'] == $orderParameters['state']) {
				$region = $thisRegion;
				break;
			}
		}
		if (!$region) {
			$this->iErrorMessage = "Shipping to state '" . $orderParameters['state'] . "' not supported by API.";
			return array("result" => "error", "error_message" => $this->iErrorMessage);
		}
		$billingRegion = false;
		foreach ($regions['available_regions'] as $thisRegion) {
			if ($thisRegion['code'] == $billingAddress['state'] || $thisRegion['name'] == $billingAddress['state']) {
				$billingRegion = $thisRegion;
				break;
			}
		}
		if (!$billingRegion) {
			$this->iErrorMessage = "Billing address in state '" . $billingAddress['state'] . "' not supported by API.";
			return array("result" => "error", "error_message" => $this->iErrorMessage);
		}
		// Get shipping methods
		$address = array("address" => array(
			"region" => $region['name'],
			"region_id" => $region['id'],
			"region_code" => $region['code'],
			"country_id" => "US",
			"street" => array($orderParameters['address_1'], $orderParameters['address_2']),
			"postcode" => $orderParameters['postal_code'],
			"city" => $orderParameters['city'],
			"firstname" => $orderParameters['first_name'],
			"lastname" => $orderParameters['last_name'],
			"company" => $orderParameters['business_name'],
			"email" => $orderParameters['email_address'],
			"telephone" => $orderParameters['phone_number']));
		if ($orderParameters['order_type'] == "dealer") {
			$address['address']['same_as_billing'] = 1;
		}
		$result = $this->postApi("rest/V1/carts/mine/estimate-shipping-methods", $address);
		if (empty($result)) {
			$this->iErrorMessage = $this->iErrorMessage ?: "No shipping methods found.";
			return array("result" => "error", "error_message" => $this->iErrorMessage);
		}
		$shippingMethod = $result[0];
		// Set Shipping Method
		$addressInformation = array("address_information" => array(
			"shipping_address" => $address['address'],
			"billing_address" => array("region" => $billingRegion['name'],
				"region_id" => $billingRegion['id'],
				"region_code" => $billingRegion['code'],
				"country_id" => "US",
				"street" => array($billingAddress['address_1'], $billingAddress['address_2']),
				"postcode" => $billingAddress['postal_code'],
				"city" => $billingAddress['city'],
				"firstname" => $billingAddress['first_name'],
				"lastname" => $billingAddress['last_name'],
				"company" => $billingAddress['business_name'],
				"email" => $billingAddress['email_address'],
				"telephone" => $billingAddress['phone_number']),
			"shipping_carrier_code" => $shippingMethod['carrier_code'],
			"shipping_method_code" => $shippingMethod['method_code']));
		$result = $this->postApi("rest/V1/carts/mine/shipping-information", $addressInformation);
		$paymentMethod = $result['payment_methods'][0];
		if (empty($paymentMethod)) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unable to get payment method.";
			return array("result" => "error", "error_message" => $this->iErrorMessage);
		}
		// Get purchase agreements
		$agreements = $this->getApi("rest/V1/carts/licence");
		// Finalize purchase
		$parameters = array("payment_method" => array(
			"method" => $paymentMethod['code']
		));
		$agreementIds = array();
		foreach ($agreements as $thisAgreement) {
			if ($thisAgreement['is_active'] == "true" && $thisAgreement['mode'] == 1) {
				$agreementIds[] = $thisAgreement['agreement_id'];
			}
		}
		if (!empty($agreementIds)) {
			$parameters['payment_method']['extension_attributes']['agreement_ids'] = $agreementIds;
		}
		$orderNumber = $this->postApi("rest/V1/carts/mine/payment-information", $parameters);
		if (empty($orderNumber)) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Order could not be placed.";
			return array("result" => "error", "error_message" => $this->iErrorMessage);
		}

		return array("result" => "OK", "order_id" => $orderNumber);
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

		$dealerOrderParameters = array();
		$dealerOrderParameters['order_type'] = "dealer";
		$dealerOrderParameters['first_name'] = $this->iLocationContactRow['first_name'];
		$dealerOrderParameters['last_name'] = $this->iLocationContactRow['last_name'];
		$dealerOrderParameters['phone_number'] = $this->iLocationContactRow['phone_number'];
		$dealerOrderParameters['address_1'] = $this->iLocationContactRow["address_1"];
		$dealerOrderParameters['address_2'] = $this->iLocationContactRow["address_2"];
		$dealerOrderParameters['city'] = $this->iLocationContactRow["city"];
		$dealerOrderParameters['state'] = $this->iLocationContactRow["state"];
		$dealerOrderParameters['postal_code'] = $this->iLocationContactRow["postal_code"];
		$dealerOrderParameters['country_id'] = $this->iLocationContactRow["country_id"];
		$dealerOrderParameters['email_address'] = $this->iLocationContactRow['email_address'];
		$dealerOrderParameters['purchase_order_number'] = $orderPrefix . $distributorOrderId;
		$dealerOrderParameters['order_items'] = array();
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$thisOrderItem = array();
			$thisOrderItem['product_id'] = $thisItemRow['distributor_product_code'];
			$thisOrderItem['quantity'] = $thisItemRow['quantity'];
			$dealerOrderParameters['order_items'][] = $thisOrderItem;
		}

# Submit the order

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}
		$orderReturn = $this->sendOrder($dealerOrderParameters);
		if ($orderReturn['result'] != "OK") {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$returnValues['dealer'] = array("error_message" => $orderReturn['error_message']);
		} else {
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			$this->postApi("rest/V1/order/" . $orderReturn['order_id'] . "/comments", array("comment" => "PO Number " . $orderPrefix . $distributorOrderId));
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $orderReturn['order_id']);
		}

		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {

		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $orderShipmentId);
		if (empty($orderShipmentRow['remote_order_id'])) {
			return false;
		}
		$remoteOrderRow = getRowFromId("remote_orders", "remote_order_id", $orderShipmentRow['remote_order_id']);
		if (empty($remoteOrderRow['order_number'])) {
			return false;
		}
		$shipmentData = $this->getApi("/rest/V1/shipments?searchCriteria[filter_groups][0][filters][0][field]=order_id&searchCriteria[filter_groups][0][filters][0][value]="
			. $remoteOrderRow['order_number']);
		if (!is_array($shipmentData) || !array_key_exists("items", $shipmentData)) {
			$this->iErrorMessage = $this->iErrorMessage ?: "No shipment data available";
			return false;
		}

		$returnValues = array();

		foreach ($shipmentData['items'] as $thisShipment) {
			if ($thisShipment['order_id'] != $remoteOrderRow['order_number']) {
				continue;
			}
			$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "remote_order_id", $remoteOrderRow['remote_order_id'], "tracking_identifier is null");
			if (empty($orderShipmentId)) {
				continue;
			}
			if (empty($thisShipment['tracks'])) {
				continue;
			}
			$trackingInfo = $thisShipment['tracks'][0];

			$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($trackingInfo['carrier_code']));
			$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
				$trackingInfo['track_number'], $shippingCarrierId, $trackingInfo['title'], $orderShipmentId);
			if ($resultSet['affected_rows'] > 0) {
				Order::sendTrackingEmail($orderShipmentId);
				executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
					$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingInfo['track_number'],
					"Tracking number added by " . $this->iProductDistributorRow['description']);
				$returnValues[] = $orderShipmentId;
			}
		}

		return $returnValues;
	}
}
