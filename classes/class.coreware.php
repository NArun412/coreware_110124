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

/*
 * The following is required to get a dealer up for the Coreware feed:
 *
 * Create a developer account
 *      - make sure to create user with the developer
 *      - Give access to API Method Group "Dealer Product Catalog Feed"
 *      - If the dealer can buy FFL products, add FFL License number to Custom field with code "FFL_NUMBER"
 *      - Distributor needs a shipping method with code "STANDARD"
 *      - Distributor inventory needs to be in a location with code "DEALER_INVENTORY"
 *
 */

class Coreware extends ProductDistributor {

    protected $iFieldTranslations = array("upc_code" => "upc_code", "product_code" => "product_id", "description" => "description", "detailed_description" => "detailed_description",
		"manufacturer_code" => "product_manufacturer", "model" => "model", "list_price" => "list_price", "manufacturer_sku" => "manufacturer_sku", "manufacturer_advertised_price" => "manufacturer_advertised_price",
		"width" => "width", "length" => "length", "height" => "height", "weight" => "weight", "base_cost" => "base_cost");
	public $iTrackingAlreadyRun = false;
	protected $iLiveDomainName = "demostore.coreware.com";
    protected $iDropshipToFFL = true;
    protected $iDropshipToCustomer = true;
    protected $iSyncStateData = true;

	function __construct($locationId) {
		$this->iProductDistributorCode = "COREWARE";
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	function callApi($method, $parameters = array()) {
		$parameters['connection_key'] = $this->iLocationCredentialRow['user_name'];
		$hostUrl = "https://" . $this->iLiveDomainName . "/api.php?action=" . $method;
		$postParameters = "json_post_parameters=" . rawurlencode(jsonEncode($parameters));
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout'] * 4);
		$response = curl_exec($ch);
		if (empty($response)) {
			return false;
		}
		try {
			$responseArray = json_decode($response, true);
		} catch (Exception $e) {
			$responseArray = array();
		}
		return $responseArray;
	}

	function testCredentials() {
		$returnArray = $this->callApi("test_credentials");
		return ($returnArray['result'] == "OK");
	}

    function getConnectionKey($username, $password) {
        $returnArray = array();
        $parameters = array("api_app_code"=>"get_connection_key",
            "api_app_version"=>"1.00",
            "user_name"=> $username,
            "password"=>$password);
        $hostUrl = "https://" . $this->iLiveDomainName . "/api.php?action=login";
        $response = getCurlReturn($hostUrl,$parameters);
        $responseArray = json_decode($response,true);
        if(empty($responseArray['session_identifier'])) {
            $returnArray['error_message'] = $responseArray['error_message'] ?: "Login failed";
            return $returnArray;
        }
        unset($parameters['user_name']);
        unset($parameters['password']);
        $parameters["session_identifier"] = $responseArray['session_identifier'];
        $hostUrl = "https://" . $this->iLiveDomainName . "/api.php?action=get_connection_key";
        $response = getCurlReturn($hostUrl,$parameters);
        $responseArray = json_decode($response,true);
        if(empty($responseArray['connection_key'])) {
            $returnArray['error_message'] = $responseArray['error_message'] ?: "Retrieving connection key failed. Contact support.";
            return $returnArray;
        }
        $returnArray['connection_key'] = $responseArray['connection_key'];
        return $returnArray;
    }

	function syncProducts($parameters = array()) {
		$productArray = $this->callApi("get_catalog");
		if (empty($productArray) || !is_array($productArray['products'])) {
			$this->iErrorMessage = "No products returned by API call";
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

		// Make sure the default image isn't pulled into the catalog
		$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
		if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
			$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
		}
		if (empty($missingProductImage)) {
			$missingProductImage = "/images/no_product_image.jpg";
		}
		$missingProductImageContents = file_get_contents($GLOBALS['gDocumentRoot'] . "/" . ltrim($missingProductImage, "/"));
		$missingProductImageSize = strlen($missingProductImageContents);

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

		foreach ($productArray['products'] as $thisProductInfo) {
			if (empty($thisProductInfo[$this->iFieldTranslations['product_code']])) {
				continue;
			}

# find existing product ID

			$upcCode = ProductCatalog::makeValidUPC($thisProductInfo[$this->iFieldTranslations['upc_code']], array("only_valid_values" => true));
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
				$description = str_replace("\n", " ", $description);
				$description = str_replace("\r", " ", $description);
				while (strpos($description, "  ") !== false) {
					$description = str_replace("  ", " ", $description);
				}

				$detailedDescription = $thisProductInfo[$this->iFieldTranslations['detailed_description']];

				$cost = $thisProductInfo[$this->iFieldTranslations['base_cost']];
				if (empty($cost)) {
					continue;
				} else {
					$internalUseOnly = 0;
				}

				$productCodePrefix = substr(ProductDistributor::$iLoadedValues['product_distributors'][$this->iLocationRow['product_distributor_id']]['product_distributor_code'], 0, 1) . $this->iLocationRow['product_distributor_id'] . "_";
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
					addDebugLog("possible duplicate product: " . $productCode . " from " . $this->iLocationRow['description'], true);
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
				if ($this->iSyncStateData) {
					$this->syncStates($productId, $thisProductInfo['restricted_states']);
				}
			} else {
				$this->syncStates($productId, $corewareProductData['restricted_states']);
			}

			$corewareProductData['product_manufacturer_id'] = $productManufacturerId;
			$corewareProductData['remote_identifier'] = $remoteIdentifier;

			$productTags = explode(",", $thisProductInfo['product_tags']);
			if (empty($corewareProductData['serializable'])) {
				$corewareProductData['serializable'] = $productRow['serializable'];
				if (in_array("FFL_REQUIRED", $productTags) || in_array("ffl_required", $productTags)) {
					$corewareProductData['serializable'] = 1;
				}
				if (in_array("CLASS_3", $productTags) || in_array("class_3", $productTags)) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if (in_array("FFL_REQUIRED", $productTags) || in_array("ffl_required", $productTags)) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}
			if (in_array("CLASS_3", $productTags) || in_array("class_3", $productTags)) {
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

					if (!empty($thisProductInfo['image_url']) && (!self::$iCorewareShootingSports || stripos($thisProductInfo['image_url'], "images.coreware.com") === false)) {
						$imageId = "";
						$imageContents = file_get_contents($thisProductInfo['image_url']);
						if (!empty($imageContents) && (strlen($imageContents) != $missingProductImageSize || $imageContents != $missingProductImageContents)) {
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
			$this->addCorewareProductCategories($productId, $corewareProductData);
			$this->addCorewareProductFacets($productId, $corewareProductData);

			if (self::$iCorewareShootingSports) {
				self::loadValues("product_facet_options");
				self::loadValues("product_facet_values");
				self::loadValues("product_facets");
				$existingProductFacetIds = ProductDistributor::$iLoadedValues['product_facet_values'][$productId];
				foreach ($thisProductInfo['facets'] as $productFacetCode => $facetValue) {
					$productFacetId = ProductDistributor::$iLoadedValues['product_facets'][$this->iLocationRow['product_distributor_id']][$productFacetCode];
					if (empty($productFacetId) || empty($facetValue)) {
						continue;
					}
					$productFacetOptionId = $this->getProductFacetOption($facetValue, $productFacetId);
					if (empty($productFacetOptionId)) {
						$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId, "facet_value = ?", $facetValue);
						if (!empty($productFacetOptionId)) {
							ProductDistributor::$iLoadedValues['product_facet_options'][$productFacetId][$facetValue] = $productFacetOptionId;
						}
					}
					if (empty($productFacetOptionId) && !array_key_exists($productFacetId, ProductDistributor::$iLoadedValues['locked_product_facets'])) {
						$insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $facetValue);
						$productFacetOptionId = $insertSet['insert_id'];
						freeResult($insertSet);
						if (!empty($productFacetOptionId)) {
							ProductDistributor::$iLoadedValues['product_facet_options'][$productFacetId][trim(strtolower($facetValue))] = $productFacetOptionId;
						}
					}
					if (!empty($productFacetOptionId)) {
						if (!array_key_exists($productFacetId, $existingProductFacetIds)) {
							$insertSet = executeQuery("insert into product_facet_values (product_id,product_facet_id,product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptionId);
							$existingProductFacetIds[$productFacetId] = $productFacetOptionId;
							freeResult($insertSet);
						} elseif ($productFacetOptionId != $existingProductFacetIds[$productFacetId]) {
							$updateSet = executeQuery("update product_facet_values set product_facet_option_id = ? where product_id = ? and product_facet_id = ?", $productFacetOptionId, $productId, $productFacetId);
							freeResult($updateSet);
						}
					}
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
		$quantityAvailable = false;
		$cost = false;

		$productArray = $this->callApi("get_catalog_inventory");
		if (empty($productArray) || !is_array($productArray) || !array_key_exists("product_inventory", $productArray)) {
			return false;
		}

		foreach ($productArray['product_inventory'] as $thisProductInfo) {
			if ($thisProductInfo[$this->iFieldTranslations['product_code']] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['quantity'];
			$cost = $thisProductInfo['sale_price'];
			break;
		}

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$productArray = $this->callApi("get_catalog_inventory");
		if (empty($productArray) || !is_array($productArray) || !array_key_exists("product_inventory", $productArray)) {
            $this->iErrorMessage = $this->iErrorMessage ?: "Unable to retrieve catalog";
			return false;
		}
		$inventoryUpdateArray = array();
		foreach ($productArray['product_inventory'] as $thisProductInfo) {
			$thisInventoryUpdate = array("product_code" => $thisProductInfo[$this->iFieldTranslations['product_code']],
				"quantity" => $thisProductInfo['quantity'], "cost" => $thisProductInfo['sale_price']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		$manufacturerArray = $this->callApi("get_product_manufacturers");
		if (empty($manufacturerArray)) {
			return false;
		}

		$productManufacturers = array();
		foreach ($manufacturerArray['product_manufacturers'] as $thisManufacturerInfo) {
			$productManufacturers[$thisManufacturerInfo['product_manufacturer_code']] = array("business_name" => $thisManufacturerInfo['description'],
				"web_page" => $thisManufacturerInfo['web_page'], "image_url" => $thisManufacturerInfo['image_url']);
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
		$productTaxonomy = $this->callApi("get_taxonomy_structure");
		if (empty($productTaxonomy)) {
			return false;
		}

		$productCategories = array();
		if (array_key_exists("product_categories", $productTaxonomy)) {
			foreach ($productTaxonomy['product_categories'] as $categoryInformation) {
				if (!empty($categoryInformation['product_category_code'])) {
					$productCategories[$categoryInformation['product_category_code']] = array("description" => $categoryInformation['description']);
					$productCategories[$categoryInformation['product_category_code']]['product_category_groups'] = $categoryInformation['product_category_groups'];
					$productCategories[$categoryInformation['product_category_code']]['product_departments'] = $categoryInformation['product_departments'];
				}
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
		$productTaxonomy = $this->callApi("get_taxonomy_structure");
		if (empty($productTaxonomy)) {
			return false;
		}

		$productFacets = array();
		if (array_key_exists("product_facets", $productTaxonomy)) {
			foreach ($productTaxonomy['product_facets'] as $facetInformation) {
				foreach ($facetInformation['product_categories'] as $productCategoryCode) {
					if (!array_key_exists($productCategoryCode, $productFacets)) {
						$productFacets[$productCategoryCode] = array();
					}
					if (!array_key_exists($facetInformation['product_facet_code'], $productFacets[$productCategoryCode])) {
						$productFacets[$productCategoryCode][$facetInformation['product_facet_code']] = $facetInformation['description'];
					}
				}
			}
		}
		return $productFacets;
	}

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		$ordersRow = getRowFromId("orders", "order_id", $orderId);
        $productCodeField = $this->iFieldTranslations['product_code'];
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

		$fflRow = array();
		$fflOrderParameters = array();
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
			$fflOrderParameters['full_name'] = getDisplayName($ordersRow['contact_id']);
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
				$thisOrderItem[$productCodeField] = $thisItemRow['distributor_product_code'];
				$thisOrderItem['quantity'] = $thisItemRow['quantity'];
				$fflOrderParameters['order_items'][] = $thisOrderItem;
			}
		}

		$customerOrderParameters = array();
		if (!empty($customerOrderItemRows)) {
			$customerOrderParameters['order_type'] = "customer";
			$customerOrderParameters['full_name'] = getDisplayName($ordersRow['contact_id']);
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
				$thisOrderItem[$productCodeField] = $thisItemRow['distributor_product_code'];
				$thisOrderItem['quantity'] = $thisItemRow['quantity'];
				$customerOrderParameters['order_items'][] = $thisOrderItem;
			}
		}

		$dealerOrderParameters = array();
		if (!empty($dealerOrderItemRows)) {
			$dealerOrderParameters['order_type'] = "dealer";
			$dealerOrderParameters['full_name'] = getDisplayName($ordersRow['contact_id']);
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
				$thisOrderItem[$productCodeField] = $thisItemRow['distributor_product_code'];
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
			$orderReturn = $this->callApi("place_order", $fflOrderParameters);
			if ($orderReturn['result'] != "OK") {
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['ffl'] = array("error_message" => $orderReturn['error_message']);
			} else {
				executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderReturn['order_id'], $remoteOrderId);
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $orderReturn['order_id'], "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));
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
			$orderReturn = $this->callApi("place_order", $customerOrderParameters);
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
			$orderReturn = $this->callApi("place_order", $dealerOrderParameters);
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

	function placeDistributorOrder($productArray, $parameters = array()) {
		$userId = $parameters['user_id'];
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
        $productCodeField = $this->iFieldTranslations['product_code'];
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
		$dealerOrderParameters['full_name'] = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
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
			$thisOrderItem[$productCodeField] = $thisItemRow['distributor_product_code'];
			$thisOrderItem['quantity'] = $thisItemRow['quantity'];
			$dealerOrderParameters['order_items'][] = $thisOrderItem;
		}

# Submit the order

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}
		$orderReturn = $this->callApi("place_order", $dealerOrderParameters);
		if ($orderReturn['result'] != "OK") {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$returnValues['dealer'] = array("error_message" => $orderReturn['error_message']);
		} else {
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
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
		$shipmentData = $this->callApi("get_shipment_data", array("order_id" => $remoteOrderRow['order_number']));
		if (empty($shipmentData) || !is_array($shipmentData)) {
			$this->iErrorMessage = "Not shipment data available";
			return false;
		}

		$returnValues = array();

		foreach ($shipmentData['shipment_data'] as $thisShipment) {
			$orderIdParts = explode("-", $thisShipment['purchase_order_number']);
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
			$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($thisShipment['shipping_carrier_code']));
			$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
				$thisShipment['tracking_identifier'], $shippingCarrierId, $thisShipment['shipping_carrier'], $orderShipmentId);
			if ($resultSet['affected_rows'] > 0) {
				Order::sendTrackingEmail($orderShipmentId);
				executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
					$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $thisShipment['tracking_identifier'],
					"Tracking number added by " . $this->iProductDistributorRow['description']);
				$returnValues[] = $orderShipmentId;
			}
		}

		return $returnValues;
	}
}
