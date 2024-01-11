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

class SportsSouth extends ProductDistributor {

	private $iFieldTranslations = array("upc_code" => "ITUPC", "product_code" => "ITEMNO", "description" => "SHDESC", "alternate_description" => "IDESC",
		"detailed_description" => "TXTREF", "manufacturer_code" => "ITBRDNO", "model" => "IMODEL", "manufacturer_sku" => "MFGINO", "manufacturer_advertised_price" => "MFPRC",
		"width" => "WIDTH", "length" => "LENGTH", "height" => "HEIGHT", "weight" => "WTPBX", "base_cost" => "CPRC");
	private $iRelatedProductTypeIds = false;
	private $iRelatedProductIds = false;
	private $iUnits = false;
	private static $iWebServiceCacheKeys = array("DailyItemUpdate","BrandUpdate","ManufacturerUpdate");
	private static $iWebServiceResponse = array();

	function __construct($locationId) {
		$this->iProductDistributorCode = "SPORTSSOUTH";
        $this->iLoggingOn = !empty(getPreference("LOG_DISTRIBUTOR_SPORTS_SOUTH"));
        parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		$parameters['LastUpdate'] = date("m/d/Y", strtotime("Yesterday"));
		$parameters['LastItem'] = -1;
		$response = $this->getWebServicesResponse("inventory.asmx/DailyItemUpdate", $parameters);
		if ($response === false || !empty($response['ERROR'])) {
			return false;
		}
		return true;
	}

	function getWebServicesResponse($operation, $parameters = array()) {
		if (!is_array($parameters)) {
			$parameters = array();
		}
		if (in_array($operation, self::$iWebServiceCacheKeys) && array_key_exists($operation,self::$iWebServiceResponse)) {
			return self::$iWebServiceResponse[$operation];
		}
		if (!array_key_exists("CustomerNumber", $parameters)) {
			$parameters['CustomerNumber'] = $this->iLocationCredentialRow['customer_number'];
		}
		if (!array_key_exists("Password", $parameters)) {
			$parameters['Password'] = $this->iLocationCredentialRow['password'];
		}
		if (!array_key_exists("UserName", $parameters)) {
			$parameters['UserName'] = $this->iLocationCredentialRow['user_name'];
		} else {
			$parameters['CustomerNumber'] = $parameters['UserName'];
		}
		$parameters['Source'] = ($this->iLocationCredentialRow['distributor_source'] == "CW2" ? "CW2" : "CW");

		$hostUrl = "http://webservices.theshootingwarehouse.com/smart/" . $operation;
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
		$this->iResponse = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
		curl_close($ch);
        $this->addLogEntry("Sports South request: " . $hostUrl
            . "\nSports South Parameters: " . getFirstPart($postParameters, $this->iLogLength)
            . "\nSports South Result: " . getFirstPart($this->iResponse, $this->iLogLength)
            . (empty($err) ? "" : "\nSports South Error: " . $err)
            . "\nSports South HTTP Status: " . $httpCode . "\n");
        if ($httpCode == 200) {
			if (empty($this->iResponse)) {
				return false;
			}
			$response = html_entity_decode($this->iResponse);
			if (empty($response)) {
				return false;
			}
			$response = trim(str_replace("<?xml version=\"1.0\" encoding=\"utf-8\"?>", "", $response), " \r\n\t\0");
			$response = trim(str_replace("\r\n", "\r", $response), " \r\n\t\0");
			$response = str_replace("<diffgr:diffgram xmlns:msdata=\"urn:schemas-microsoft-com:xml-msdata\" xmlns:diffgr=\"urn:schemas-microsoft-com:xml-diffgram-v1\">", "", $response);
			$response = str_replace("</diffgr:diffgram>", "", $response);

			$GLOBALS['gIgnoreError'] = true;
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$GLOBALS['gIgnoreError'] = false;

			if (is_array($responseArray) && array_key_exists("string", $responseArray)) {
				$responseArray = $responseArray['string'];
			}
			if (is_array($responseArray) && array_key_exists("DataSet", $responseArray)) {
				$responseArray = $responseArray['DataSet'];
			}
			if (is_array($responseArray) && array_key_exists("NewDataSet", $responseArray)) {
				$responseArray = $responseArray['NewDataSet'];
			}
			if (is_array($responseArray) && array_key_exists("Table", $responseArray)) {
				$responseArray = $responseArray['Table'];
			}
			$response = null;
			$this->iResponse = null;
			$response = trimFields($responseArray);
			if (in_array($operation, self::$iWebServiceCacheKeys)) {
				self::$iWebServiceResponse[$operation] = $response;
			}
			return $response;
		} else {
			return false;
		}
	}

	function syncProducts($parameters = array()) {

		$parameters['LastUpdate'] = "1/1/1990";
		$parameters['LastItem'] = -1;

		$response = $this->getWebServicesResponse("inventory.asmx/DailyItemUpdate", $parameters);
		if ($response === false || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		if (empty($response)) {
			return "Nothing to process";
		}

		$newResponse = "";
		foreach ($response as $thisResponse) {
			$newResponse .= jsonEncode($thisResponse) . "\n";
		}
		$response = $newResponse;

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
		$productManufacturerArray = $this->getManufacturers();

		$processCount = 0;
		$imageCount = 0;
		$foundCount = 0;
		$updatedCount = 0;
		$noUpc = 0;
		$duplicateUpcCount = 0;
		$duplicateProductCount = 0;
		$productList = array();
		$badImageCount = 0;

		self::loadValues('distributor_product_codes');
		$productIdsProcessed = array();

		# Sports South feed has multiple instances of two different products with the same UPC. Because of this,
		# we will skip ALL products that share a UPC with another product.

		$upcCount = array();
		foreach (preg_split("/((\r?\n)|(\r\n?))/", $response) as $line) {
			if (empty($line)) {
				continue;
			}
			$thisProductInfo = json_decode($line, true);
			if (empty($thisProductInfo) || !is_array($thisProductInfo)) {
				continue;
			}
			foreach ($thisProductInfo as $index => $lineData) {
				if (is_array($lineData)) {
					$thisProductInfo[$index] = trim($lineData[0]);
				}
			}
			if (empty($thisProductInfo[$this->iFieldTranslations['product_code']])) {
				continue;
			}

			$upcCode = ProductCatalog::makeValidUPC($thisProductInfo[$this->iFieldTranslations['upc_code']], array("only_valid_values" => true));
			if (empty($upcCode)) {
				$noUpc++;
				continue;
			}
			if (!array_key_exists($upcCode, $upcCount)) {
				$upcCount[$upcCode] = 1;
			} else {
				$upcCount[$upcCode]++;
			}
		}

		$skippedDuplicateProducts = array();
		foreach (preg_split("/((\r?\n)|(\r\n?))/", $response) as $line) {
			if (empty($line)) {
				continue;
			}
			$thisProductInfo = json_decode($line, true);
			if (empty($thisProductInfo) || !is_array($thisProductInfo)) {
				continue;
			}
			foreach ($thisProductInfo as $index => $lineData) {
				if (is_array($lineData)) {
					$thisProductInfo[$index] = trim($lineData[0]);
				}
			}
			if (empty($thisProductInfo[$this->iFieldTranslations['product_code']])) {
				continue;
			}

			$upcCode = ProductCatalog::makeValidUPC($thisProductInfo[$this->iFieldTranslations['upc_code']], array("only_valid_values" => true));
			if (empty($upcCode)) {
				$noUpc++;
				continue;
			}
			if (array_key_exists($upcCode, $upcCount) && $upcCount[$upcCode] > 1) {
				$skippedDuplicateProducts[] = $thisProductInfo;
				$duplicateUpcCount++;
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

# ignore case products for now. THey have the same UPC as regular products but different prices. We need to come up with a solution for them.

			if ($thisProductInfo['UOM'] == "CS" && !empty($productId)) {
				$existingProductCode = getFieldFromId("product_code", "products", "product_id", $productId);
				if ($existingProductCode != $thisProductInfo[$this->iFieldTranslations['product_code']]) {
					continue;
				}
			}

			$productManufacturerId = "";
			$corewareProductData = array();
			if (self::$iCorewareShootingSports) {
				$manufacturerCode = $thisProductInfo[$this->iFieldTranslations['manufacturer_code']];
				if (!array_key_exists($manufacturerCode, $productManufacturerArray)) {
					$manufacturerCode = $thisProductInfo['IMFGNO'];
				}
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

			$description = $corewareProductData['description'];

			if (empty($description) || !empty(getPreference("SPORTS_SOUTH_UPDATE_DESCRIPTIONS"))) {
				$corewareProductData['description'] = $description = (empty($thisProductInfo[$this->iFieldTranslations['description']]) ? $thisProductInfo[$this->iFieldTranslations['alternate_description']] : $thisProductInfo[$this->iFieldTranslations['description']]);
			}

			if (empty($productId) && self::$iCorewareShootingSports) {
				$detailedDescription = "";
				$detailedDescriptionReferenceId = $thisProductInfo[$this->iFieldTranslations['detailed_description']];
				if ($detailedDescriptionReferenceId > 0) {
					$detailedDescriptionArray = $this->getWebServicesResponse("inventory.asmx/GetText", array("ItemNumber" => $detailedDescriptionReferenceId));
					if (is_array($detailedDescriptionArray) && array_key_exists("CATALOGTEXT", $detailedDescriptionArray)) {
						$detailedDescription = $detailedDescriptionArray['CATALOGTEXT'];
					}
				}
				if (empty($description)) {
					$description = $detailedDescription;
					if (strlen($description) > 255) {
						$string = wordwrap($description, 250);
						$description = mb_substr($string, 0, strpos($string, "\n"));
					} else {
						$detailedDescription = "";
					}
				}
				if ($corewareProductData['detailed_description'] || !empty(getPreference("SPORTS_SOUTH_UPDATE_DESCRIPTIONS"))) {
					$corewareProductData['detailed_description'] = $detailedDescription;
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

			if (empty($productId)) {
				$productCode = makeCode($thisProductInfo[$this->iFieldTranslations['product_code']]);

				$cost = $thisProductInfo[$this->iFieldTranslations['base_cost']];
				if (empty($cost)) {
					continue;
				} else {
					$internalUseOnly = 0;
				}
				if ($thisProductInfo['CATCD'] == "L" || $thisProductInfo['CATCD'] == "X") {
					continue;
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
				$newProduct = true;
				$insertCount++;
			} else {
				$productRow = $this->getProductRow($productId);
				if (empty($productRow)) {
					continue;
				}
				$newProduct = false;
				$foundCount++;
			}

			$productIdsProcessed[$productId] = $productId;

			self::loadValues("cannot_sell_product_manufacturers");
			if (array_key_exists($productRow['product_manufacturer_id'], ProductDistributor::$iLoadedValues['cannot_sell_product_manufacturers'][$this->iLocationRow['product_distributor_id']])) {
				continue;
			}

			if (!self::$iCorewareShootingSports) {
				$this->syncStates($productId, $corewareProductData['restricted_states']);
				if ($newProduct && !empty($corewareProductData['related_products'])) {
					if ($this->iRelatedProductTypeIds === false) {
						$this->iRelatedProductTypeIds = array();
						$resultSet = executeQuery("select * from related_product_types where client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$this->iRelatedProductTypeIds[$row['related_product_type_code']] = $row['related_product_type_id'];
						}
						$this->iRelatedProductIds = array();
						$resultSet = executeQuery("select * from related_products where product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$this->iRelatedProductIds[$row['product_id'] . ":" . $row['associated_product_id'] . ":" . $row['related_product_type_id']] = true;
						}
					}

					$relatedProducts = explode(",", $corewareProductData['related_products']);
					foreach ($relatedProducts as $relatedProductInfo) {
						$parts = explode("||", $relatedProductInfo);
						$associatedProductId = $this->getProductFromUpc($parts[0]);
						if (empty($associatedProductId) || $productId == $associatedProductId) {
							continue;
						}
						if (empty($parts[1])) {
							$relatedProductTypeId = "";
						} else {
							$relatedProductTypeId = $this->iRelatedProductTypeIds[$parts[1]];
							if (empty($relatedProductTypeId)) {
								$resultSet = executeQuery("insert into related_product_types (client_id,related_product_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], makeCode($parts[1]), ucwords(strtolower(str_replace("_", " ", $parts[1]))));
								$relatedProductTypeId = $resultSet['insert_id'];
								$this->iRelatedProductTypeIds[$parts[1]] = $relatedProductTypeId;
							}
						}
						if (!array_key_exists($productId . ":" . $associatedProductId . ":" . $relatedProductTypeId, $this->iRelatedProductIds)) {
							executeQuery("insert ignore into related_products (product_id,associated_product_id,related_product_type_id) values (?,?,?)", $productId, $associatedProductId, $relatedProductTypeId);
							$this->iRelatedProductIds[$productId . ":" . $associatedProductId . ":" . $relatedProductTypeId] = true;
						}
					}
				}
			}

# DO stuff for both new products and existing products

			$corewareProductData['product_manufacturer_id'] = $productManufacturerId;
			$corewareProductData['remote_identifier'] = $remoteIdentifier;

			if (empty($corewareProductData['serializable'])) {
				$corewareProductData['serializable'] = $productRow['serializable'];
				if ($thisProductInfo['UOM'] == "GN") {
					$corewareProductData['serializable'] = 1;
				}
			}
			if ($thisProductInfo['UOM'] == "GN") {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}

			if (self::$iCorewareShootingSports) {
				$originalImageId = $productRow['image_id'];
				$productRow['image_id'] = getFieldFromId("image_id", "images", "image_id", $productRow['image_id'], "os_filename is not null or file_content is not null");
				if (empty($productRow['image_id']) && !empty($thisProductInfo['PICREF'])) {
					if (!empty($originalImageId)) {
						$badImageCount++;
						executeQuery("update images set os_filename = 'CSSC_BAD_IMAGES' where image_id = ? or image_id in (select image_id from product_images where product_id = ?)",$originalImageId,$productRow['product_id']);
						executeQuery("update products set image_id = null where product_id = ?",$productRow['product_id']);
						executeQuery("delete from product_images where product_id = ?",$productRow['product_id']);
						executeQuery("delete from image_data where image_id in (select image_id from images where os_filename = 'CSSC_BAD_IMAGES')");
						executeQuery("delete from images where os_filename = 'CSSC_BAD_IMAGES'");
					}

					$imageId = "";
					$imageUrl = "http://media.server.theshootingwarehouse.com/large/" . $thisProductInfo['PICREF'] . ".jpg";
					$imageContents = "";
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (empty($imageContents)) {
						$imageUrl = "http://media.server.theshootingwarehouse.com/hires/" . $thisProductInfo['PICREF'] . ".png";
						if (urlExists($imageUrl)) {
							$imageContents = file_get_contents($imageUrl);
						}
					}
					if (empty($imageContents)) {
						$imageUrl = "http://media.server.theshootingwarehouse.com/small/" . $thisProductInfo['PICREF'] . ".jpg";
						if (urlExists($imageUrl)) {
							$imageContents = file_get_contents($imageUrl);
						}
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $thisProductInfo['PICREF'] . ".jpg", "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "SPORTS_SOUTH_IMAGE"));
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
					$useLinkName = makeCode($productRow['description'], array("use_dash" => true, "lowercase" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "link_name", $useLinkName);
					$useProductNumber++;
				} while (!empty($dupProductId));
				$corewareProductData['link_name'] = $useLinkName;
			}
			if (!empty($thisProductInfo['UOM']) && empty($corewareProductData['unit_id'])) {
				if ($this->iUnits === false) {
					$this->iUnits = array();
					$resultSet = executeQuery("select * from units where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$this->iUnits[$row['unit_code']] = $row['unit_id'];
					}
					freeResult($resultSet);
				}
				$corewareProductData['unit_id'] = $this->iUnits[strtoupper($thisProductInfo['UOM'])];
				if (empty($corewareProductData['unit_id'])) {
					$insertSet = executeQuery("insert into units (client_id,unit_code,description,quantity) values (?,?,?,1)", $GLOBALS['gClientId'],
						strtoupper($thisProductInfo['UOM']), $thisProductInfo['UOM']);
					$corewareProductData['unit_id'] = $insertSet['insert_id'];
					$this->iUnits[strtoupper($thisProductInfo['UOM'])] = $corewareProductData['unit_id'];
					freeResult($insertSet);
				}
			}
			if (self::$iCorewareShootingSports) {
				if ($thisProductInfo['MFPRTYP'] == "M" || $thisProductInfo['MFPRTYP'] == "U") {
					if ($thisProductInfo[$this->iFieldTranslations['manufacturer_advertised_price']] > $thisProductInfo[$this->iFieldTranslations['base_cost']]) {
						$corewareProductData['manufacturer_advertised_price'] = $thisProductInfo[$this->iFieldTranslations['manufacturer_advertised_price']];
					}
				} else {
					$corewareProductData['manufacturer_advertised_price'] = "";
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
				$productList[$thisProductInfo[$this->iFieldTranslations['product_code']]] = $productId;
			}
			$corewareProductData = null;
		}

		if (self::$iCorewareShootingSports && !empty($productList)) {
			$productData = array();
			$itemNumberArray = array();
			foreach ($productList as $itemNumber => $productId) {
				$itemNumberArray[] = $itemNumber;
			}
			$itemNumbers = array_chunk($itemNumberArray, 5000);
			foreach ($itemNumbers as $thisItemNumberList) {
				$itemNumberList = implode(",", $thisItemNumberList);
				$parameters = array("connection_key" => "E1942D361A00008369221D03AC4DE1B8", "product_codes" => $itemNumberList, "product_ids" => "[]");
				$hostUrl = "https://www.theshootingwarehouse.com/api.php?action=get_product_taxonomy";
				$postParameters = "";
				foreach ($parameters as $parameterKey => $parameterValue) {
					$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
				}
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
				curl_setopt($ch, CURLOPT_URL, $hostUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
				curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
				$response = curl_exec($ch);
				curl_close($ch);
				$productCategories = json_decode($response, true);

				$hostUrl = "https://www.theshootingwarehouse.com/api.php?action=get_product_facet_values";
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
				curl_setopt($ch, CURLOPT_URL, $hostUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
				curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
				$response = curl_exec($ch);
				curl_close($ch);
				$productFacets = json_decode($response, true);

				$productInformationArray = array();
				foreach ($productCategories['product_categories'] as $thisProductInfo) {
					$productId = $productList[$thisProductInfo['product_code']];
					if (empty($productId)) {
						continue;
					}
					if (!array_key_exists($productId, $productInformationArray)) {
						$productInformationArray[$productId] = array();
					}
					$productInformationArray[$productId]['product_categories'] = $thisProductInfo['product_categories'];
				}
				foreach ($productFacets['product_facet_values'] as $thisProductInfo) {
					$productId = $productList[$thisProductInfo['product_code']];
					if (empty($productId)) {
						continue;
					}
					if (!array_key_exists($productId, $productInformationArray)) {
						continue;
					}
					$productInformationArray[$productId]['product_facet_values'] = $thisProductInfo['product_facet_values'];
				}

				foreach ($productInformationArray as $productId => $productInformation) {
					$productData[$productId] = $productInformation;
				}
			}

			foreach ($productData as $productId => $productInformation) {
				$this->addProductCategories($productId, $productInformation['product_categories'], true);
				$this->addProductFacets($productId, $productInformation['product_facet_values'], true);
			}
		}
		$productList = null;

		if (self::$iCorewareShootingSports) {
			executeQuery("delete from client_preferences where client_id = ? and preference_id = " .
				"(select preference_id from preferences where preference_code = 'RESET_CATALOG_IMAGES_FROM_SPORTS_SOUTH')", $GLOBALS['gClientId']);
		}
		freeResult($updateSet);
		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateUpcCount . " duplicate UPCs skipped, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function getManufacturers($parameters = array()) {
		$response = $this->getWebServicesResponse("inventory.asmx/BrandUpdate");
		if (!$response || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		$productManufacturers = array();
		foreach ($response as $manufacturerInformation) {
			if (!empty($manufacturerInformation['BRDNO'])) {
				$imageUrl = "http://media.server.theshootingwarehouse.com/Logos/png/" . $manufacturerInformation['BRDNO'] . ".png";
				if (!urlExists($imageUrl)) {
					$imageUrl = "http://media.server.theshootingwarehouse.com/Logos/small/" . $manufacturerInformation['BRDNO'] . ".jpg";
					if (!urlExists($imageUrl)) {
						$imageUrl = "";
					}
				}
				$productManufacturers[$manufacturerInformation['BRDNO']] = array("business_name" => $manufacturerInformation['BRDNM'],
					"web_page" => $manufacturerInformation['BRDURL'], "image_url" => $imageUrl);
			}
		}
		$response = $this->getWebServicesResponse("inventory.asmx/ManufacturerUpdate");
		if (!$response || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		foreach ($response as $manufacturerInformation) {
			if (!empty($manufacturerInformation['MFGNO']) && !array_key_exists($manufacturerInformation['MFGNO'], $productManufacturers)) {
				$imageUrl = "http://media.server.theshootingwarehouse.com/Logos/png/" . $manufacturerInformation['MFGNO'] . ".png";
				if (!urlExists($imageUrl)) {
					$imageUrl = "http://media.server.theshootingwarehouse.com/Logos/small/" . $manufacturerInformation['MFGNO'] . ".jpg";
					if (!urlExists($imageUrl)) {
						$imageUrl = "";
					}
				}
				$productManufacturers[$manufacturerInformation['MFGNO']] = array("business_name" => $manufacturerInformation['MFGNM'], "web_page" => $manufacturerInformation['MFGURL'],
					"image_url" => $imageUrl);
			}
		}
		uasort($productManufacturers, array($this, "sortManufacturers"));
		return $productManufacturers;
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}

		$operation = "inventory.asmx/OnhandUpdate";
		$parameters['LastUpdate'] = "1/1/1990";
		$parameters['LastItem'] = -1;

		$response = $this->getWebServicesResponse($operation, $parameters);
		if ($response === false || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		if (empty($response)) {
			return false;
		}

		if (array_key_exists("Onhand", $response)) {
			$response = $response['Onhand'];
		}
		$quantityAvailable = false;
		$cost = false;

		$allocatedInventory = array();
		$allocatedUsername = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ALLOCATED_USERNAME", "PRODUCT_DISTRIBUTORS");
		$allocatedPassword = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ALLOCATED_PASSWORD", "PRODUCT_DISTRIBUTORS");

		if (empty($allocatedUsername) && empty($allocatedPassword)) {
			$allocatedUsername = $this->iLocationCredentialRow['user_name'];
			$allocatedPassword = $this->iLocationCredentialRow['password'];
		}

		if (!empty($allocatedUsername) && !empty($allocatedPassword)) {
			$operation = "inventoryspecial.asmx/GetOpenOrderDetails";
			$parameters = array("UserName" => $allocatedUsername, "Password" => $allocatedPassword);
			$allocatedResponse = $this->getWebServicesResponse($operation, $parameters);
			if (is_array($allocatedResponse) && !empty($allocatedResponse)) {
				foreach ($allocatedResponse as $thisProductInfo) {
					if (empty($thisProductInfo['ORQTY'])) {
						continue;
					}
					$allocatedInventory[$thisProductInfo['ORITEM']] = $thisProductInfo['ORQTY'];
				}
			}
			foreach ($response as $index => $thisProductInfo) {
				if (array_key_exists($thisProductInfo['I'], $allocatedInventory)) {
					$response[$index]['Q'] += $allocatedInventory[$thisProductInfo['I']];
				}
			}
		}

		foreach ($response as $thisProductInfo) {
			if ($thisProductInfo['I'] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = $thisProductInfo['Q'];
			$cost = $thisProductInfo['C'];
			break;
		}
		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$operation = "inventory.asmx/OnhandUpdate";
		$operationParameters = array();
		$operationParameters['LastUpdate'] = "1/1/1990";
		$operationParameters['LastItem'] = -1;
		$response = $this->getWebServicesResponse($operation, $operationParameters);
		if ($response === false || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		if (empty($response)) {
			return "Nothing to process";
		}
		if (array_key_exists("Onhand", $response)) {
			$response = $response['Onhand'];
		}

		$allocatedInventory = array();
		if (empty($parameters['all_clients'])) {
			$allocatedUsername = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ALLOCATED_USERNAME", "PRODUCT_DISTRIBUTORS");
			$allocatedPassword = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ALLOCATED_PASSWORD", "PRODUCT_DISTRIBUTORS");

			if (empty($allocatedUsername) && empty($allocatedPassword)) {
				$allocatedUsername = $this->iLocationCredentialRow['user_name'];
				$allocatedPassword = $this->iLocationCredentialRow['password'];
			}

			if (!empty($allocatedUsername) && !empty($allocatedPassword)) {
				$operation = "inventoryspecial.asmx/GetOpenOrderDetails";
				$operationParameters = array("UserName" => $allocatedUsername, "Password" => $allocatedPassword);
				$allocatedResponse = getCachedData("sports_south_allocated_inventory", $allocatedUsername);
				if (empty($allocatedResponse)) {
					$allocatedResponse = $this->getWebServicesResponse($operation, $operationParameters);
				}
				if ($allocatedResponse === false || !empty($allocatedResponse['ERROR'])) {
					$this->iErrorMessage = "Unable to get response from web services: " . (!$allocatedResponse ? "No response" : $allocatedResponse['ERROR']);
				} elseif (is_array($allocatedResponse) && !empty($allocatedResponse)) {
					setCachedData("sports_south_allocated_inventory", $allocatedUsername, $allocatedResponse, 1);
					foreach ($allocatedResponse as $thisProductInfo) {
						if (empty($thisProductInfo['ORQTY'])) {
							continue;
						}
						$allocatedInventory[$thisProductInfo['ORITEM']] = $thisProductInfo['ORQTY'];
					}
				}
				foreach ($response as $index => $thisProductInfo) {
					if (array_key_exists($thisProductInfo['I'], $allocatedInventory)) {
						$response[$index]['Q'] += $allocatedInventory[$thisProductInfo['I']];
					}
				}
			}
		}

		$inventoryUpdateArray = array();
		foreach ($response as $thisProductInfo) {
			$thisInventoryUpdate = array("product_code" => $thisProductInfo['I'],
				"quantity" => $thisProductInfo['Q'],
				"cost" => $thisProductInfo['C']);
			if (array_key_exists($thisProductInfo['I'], $allocatedInventory) && $allocatedInventory[$thisProductInfo['I']] > 0) {
				$thisInventoryUpdate['allocated'] = true;
			}
			$inventoryUpdateArray[$thisProductInfo['I']] = $thisInventoryUpdate;
		}

		$operation = "inventory.asmx/DiscontinuedItems";
		$operationParameters = array();
		$response = $this->getWebServicesResponse($operation, $operationParameters);

		if ($response === false || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
		} else {
			foreach ($response as $thisProductInfo) {
				$inventoryUpdateArray[$thisProductInfo['ITEMNO']] = array("product_code" => $thisProductInfo['ITEMNO'],
					"quantity" => 0, "cost" => 0);
			}
		}
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function sortManufacturers($a, $b) {
		if ($a['business_name'] == $b['business_name']) {
			return 0;
		}
		return ($a['business_name'] > $b['business_name']) ? 1 : -1;
	}

	function getCategories($parameters = array()) {
		$productTaxonomy = $this->getTaxonomyStructure();
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

	function getTaxonomyStructure() {
		$taxonomyStructure = getCachedData("sports_south_taxonomy_structure", "", true);
		if (!empty($taxonomyStructure)) {
			return $taxonomyStructure;
		}
		$parameters = array("connection_key" => "E1942D361A00008369221D03AC4DE1B8");
		$hostUrl = "https://www.theshootingwarehouse.com/api.php?action=get_taxonomy_structure";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
		$response = curl_exec($ch);
		curl_close($ch);
		$taxonomyStructure = json_decode($response, true);
		setCachedData("sports_south_taxonomy_structure", "", $taxonomyStructure, 24, true);
		return $taxonomyStructure;
	}

	function sortCategories($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}

	function getFacets($parameters = array()) {
		$productTaxonomy = $this->getTaxonomyStructure();
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
		$parameters = $additionalParameters;
		$parameters['PO'] = $orderId;
		$parameters['CustomerOrderNumber'] = "";
		$parameters['SalesMessage'] = "";
		$parameters['ShipVia'] = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "SHIP_VIA", "PRODUCT_DISTRIBUTORS");
		if (array_key_exists("shipment_shipping_method", $additionalParameters)) {
			if ($additionalParameters['shipment_shipping_method'] == "premium") {
				$parameters['ShipVia'] = "G";
			} elseif ($additionalParameters['shipment_shipping_method'] == "twoday") {
				$parameters['ShipVia'] = "2";
			} elseif ($additionalParameters['shipment_shipping_method'] == "nextday") {
				$parameters['ShipVia'] = "N";
			}
		}
		$requireSignature = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "SIGNATURE_REQUIRED", "PRODUCT_DISTRIBUTORS");
		if (!$requireSignature && !empty($additionalParameters['shipment_signature_required'])) {
			$requireSignature = true;
		}
		$adultSignatureRequired = (!empty($additionalParameters['shipment_adult_signature_required']));
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
		$returnValues = array();

		$fflOrderNumber = "";
		$fflRow = array();
		if (!empty($fflOrderItemRows)) {
			$fflRow = (new FFL($ordersRow['federal_firearms_licensee_id']))->getFFLRow();
			if (empty($fflRow)) {
				$this->iErrorMessage = "No FFL Dealer set for this order";
				return false;
			}

# Make sure FFL can accept transfer. Added a preference to ignore for Dunhams

			$addressPrefix = (empty($fflRow['mailing_address_preferred']) || empty($fflRow['mailing_address_1']) ? "" : "mailing_");
			$ignoreFFLCheck = getPreference("IGNORE_FFL_CHECK");
			if (!$ignoreFFLCheck) {
				$fflTransferCode = $this->checkFFLTransfer($ordersRow['federal_firearms_licensee_id']);
				if ($fflTransferCode == "N" || $fflTransferCode == "E") {
					$this->iErrorMessage = "Sports South is unable to transfer to this FFL Dealer";
					return false;
				} elseif ($fflTransferCode == "U") {
					$returnValues['info_message'] = "The order was placed, but you need to send a copy of the FFL license to Sports South";
				}
				if (empty($fflRow[$addressPrefix . 'address_1']) || empty($fflRow[$addressPrefix . 'postal_code']) || empty($fflRow[$addressPrefix . 'city'])) {
					$this->iErrorMessage = "FFL Dealer has no address";
					return false;
				}
			}
			$parameters['ShipToName'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
			$parameters['ShipToAttn'] = "Transferee: " . getDisplayName($contactRow['contact_id']);
			$parameters['ShipToAddr1'] = $fflRow[$addressPrefix . 'address_1'];
			$parameters['ShipToAddr2'] = $fflRow[$addressPrefix . 'address_2'];
			$parameters['ShipToCity'] = $fflRow[$addressPrefix . 'city'];
			$parameters['ShipToState'] = $fflRow[$addressPrefix . 'state'];
			$parameters['ShipToZip'] = substr(str_replace("-","", $fflRow[$addressPrefix . 'postal_code']), 0, 5);
			$parameters['ShipToPhone'] = $fflRow['phone_number'];
			$parameters['AdultSignature'] = "False";
			$parameters['Signature'] = "False";
			$parameters['Insurance'] = "False";

			$this->addLogEntry(jsonEncode($parameters));
			$response = $this->getWebServicesResponse("orders.asmx/AddHeader", $parameters);
			if (!$response || !empty($response['ERROR'])) {
				$this->iErrorMessage = "Place Order: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				return false;
			}
			$fflOrderNumber = (is_array($response) ? $response['int'][0] : $response);
			if (empty($fflOrderNumber)) {
				$this->iErrorMessage = "Unable to create Order: " . (is_array($response) || is_object($response) ? jsonEncode($response) : $response);
				return false;
			}
			if (!empty($additionalParameters['purchase_order_number'])) {
				$parameters['PO'] = str_replace("%order_number%", $fflOrderNumber, $additionalParameters['purchase_order_number']);
				$parameters['OrderNumber'] = $fflOrderNumber;
				$this->getWebServicesResponse("orders.asmx/UpdateHeader", $parameters);
				$this->addLogEntry(jsonEncode($parameters));
			}
			foreach ($fflOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}
				$itemParameters = array();
				$itemParameters['OrderNumber'] = $fflOrderNumber;
				$itemParameters['SSItemNumber'] = $thisItemRow['distributor_product_code'];
				$itemParameters['Quantity'] = $thisItemRow['quantity'];
				$itemParameters['OrderPrice'] = "0.00";
				$itemParameters['CustomerItemNumber'] = $thisItemRow['product_id'];
				$itemParameters['CustomerItemDescription'] = $thisItemRow['product_row']['description'];
				$itemParameters['PONumber'] = "";
				$itemParameters['Comment'] = $thisItemRow['comment'];
				$response = $this->getWebServicesResponse("ordersexpanded.asmx/AddDetailExpanded", $itemParameters);
				if (!$response || !empty($response['ERROR']) || $response == "false") {
					$this->iErrorMessage = "FFL Order Item: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
					$this->deleteOpenOrder($fflOrderNumber);
					return false;
				}
				$this->addLogEntry(jsonEncode($itemParameters));
			}
			$shipParameters = array();
			$shipParameters['SystemOrderNumber'] = $fflOrderNumber;
			$shipParameters['ShipInst1'] = "FFL # " . $fflRow['license_number'];
			$shipParameters['ShipInst2'] = "PHONE # " . str_replace("(", "", str_replace(")", "", str_replace(" ", "", str_replace("-", "", $ordersRow['phone_number']))));
			$response = $this->getWebServicesResponse("orders.asmx/AddShipInstructions", $shipParameters);
			if (!$response || !empty($response['ERROR']) || $response == "false") {
				$this->iErrorMessage = "FFL Shipment Instructions: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				$this->deleteOpenOrder($fflOrderNumber);
				return false;
			}
			$this->addLogEntry(jsonEncode($shipParameters));
		}
		$customerOrderNumber = "";
		if (!empty($customerOrderItemRows)) {
			$parameters['ShipToName'] = $ordersRow['full_name'];
			$parameters['ShipToAttn'] = "";
			$parameters['ShipToAddr1'] = $addressRow['address_1'];
			$parameters['ShipToAddr2'] = $addressRow['address_2'];
			$parameters['ShipToCity'] = $addressRow['city'];
			$parameters['ShipToState'] = $addressRow['state'];
			$parameters['ShipToZip'] = substr(str_replace("-","", $addressRow['postal_code']), 0, 5);
			$parameters['ShipToPhone'] = $ordersRow['phone_number'];
			$parameters['AdultSignature'] = !empty($adultSignatureRequired) ? "True" : "False";
			$parameters['Signature'] = !empty($requireSignature) ? "True" : "False";
			$parameters['Insurance'] = "False";

			$this->addLogEntry(jsonEncode($parameters));
			$response = $this->getWebServicesResponse("orders.asmx/AddHeader", $parameters);
			if (!$response || !empty($response['ERROR'])) {
				$this->iErrorMessage = "Dropship Order: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				$this->deleteOpenOrder($fflOrderNumber);
				return false;
			}
			$customerOrderNumber = (is_array($response) ? $response['int'][0] : $response);
			if (empty($customerOrderNumber)) {
				$this->iErrorMessage = "Unable to create Order: " . (is_array($response) || is_object($response) ? jsonEncode($response) : $response);
				$this->deleteOpenOrder($fflOrderNumber);
				return false;
			}
			if (!empty($additionalParameters['purchase_order_number'])) {
				$parameters['PO'] = str_replace("%order_number%", $customerOrderNumber, $additionalParameters['purchase_order_number']);
				$parameters['OrderNumber'] = $customerOrderNumber;
				$this->getWebServicesResponse("orders.asmx/UpdateHeader", $parameters);
				$this->addLogEntry(jsonEncode($parameters));
			}
			foreach ($customerOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}
				$itemParameters = array();
				$itemParameters['OrderNumber'] = $customerOrderNumber;
				$itemParameters['SSItemNumber'] = $thisItemRow['distributor_product_code'];
				$itemParameters['Quantity'] = $thisItemRow['quantity'];
				$itemParameters['OrderPrice'] = "0.00";
				$itemParameters['CustomerItemNumber'] = $thisItemRow['product_id'];
				$itemParameters['CustomerItemDescription'] = $thisItemRow['product_row']['description'];
				$itemParameters['PONumber'] = "";
				$itemParameters['Comment'] = $thisItemRow['comment'];
				$response = $this->getWebServicesResponse("ordersexpanded.asmx/AddDetailExpanded", $itemParameters);
				if (!$response || !empty($response['ERROR']) || $response == "false") {
					$this->iErrorMessage = "Dropship Items: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
					$this->deleteOpenOrder($fflOrderNumber);
					$this->deleteOpenOrder($customerOrderNumber);
					return false;
				}
				$this->addLogEntry(jsonEncode($itemParameters));
			}
		}

		$dealerOrderNumber = "";
		if (!empty($dealerOrderItemRows)) {
			$parameters['ShipToName'] = "";
			$parameters['ShipToAttn'] = "Order ID: " . $orderId;
			$parameters['ShipToAddr1'] = "";
			$parameters['ShipToAddr2'] = "";
			$parameters['ShipToCity'] = "";
			$parameters['ShipToState'] = "";
			$parameters['ShipToZip'] = substr(str_replace("-","", $GLOBALS['gClientRow']['postal_code']), 0, 5);
			$parameters['ShipToPhone'] = "";
			$parameters['AdultSignature'] = "False";
			$parameters['Signature'] = "False";
			$parameters['Insurance'] = "False";

			$this->addLogEntry(jsonEncode($parameters));
			$response = $this->getWebServicesResponse("orders.asmx/AddHeader", $parameters);
			if (!$response || !empty($response['ERROR'])) {
				$this->iErrorMessage = "Dealer Order: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']) . ($GLOBALS['gUserRow']['superuser_flag'] ? " - " . jsonEncode($parameters) : "");
				$this->deleteOpenOrder($fflOrderNumber);
				$this->deleteOpenOrder($customerOrderNumber);
				return false;
			}
			$dealerOrderNumber = (is_array($response) ? $response['int'][0] : $response);
			if (empty($dealerOrderNumber)) {
				$this->iErrorMessage = "Unable to create Order: " . (is_array($response) || is_object($response) ? jsonEncode($response) : $response);
				$this->deleteOpenOrder($fflOrderNumber);
				$this->deleteOpenOrder($customerOrderNumber);
				return false;
			}
			if (!empty($additionalParameters['purchase_order_number'])) {
				$parameters['PO'] = str_replace("%order_number%", $dealerOrderNumber, $additionalParameters['purchase_order_number']);
				$parameters['OrderNumber'] = $dealerOrderNumber;
				$this->getWebServicesResponse("orders.asmx/UpdateHeader", $parameters);
				$this->addLogEntry(jsonEncode($parameters));
			}
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
				$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
				for ($x = 0; $x < 10; $x++) {
					$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
				}
				$itemParameters = array();
				$itemParameters['OrderNumber'] = $dealerOrderNumber;
				$itemParameters['SSItemNumber'] = $thisItemRow['distributor_product_code'];
				$itemParameters['Quantity'] = $thisItemRow['quantity'];
				$itemParameters['OrderPrice'] = "0.00";
				$itemParameters['CustomerItemNumber'] = $thisItemRow['product_id'];
				$itemParameters['CustomerItemDescription'] = $thisItemRow['product_row']['description'];
				$itemParameters['PONumber'] = "";
				$itemParameters['Comment'] = $thisItemRow['comment'];
				$response = $this->getWebServicesResponse("ordersexpanded.asmx/AddDetailExpanded", $itemParameters);
				if (!$response || !empty($response['ERROR']) || $response == "false") {
					$this->iErrorMessage = "Dealer Items: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
					$this->deleteOpenOrder($fflOrderNumber);
					$this->deleteOpenOrder($customerOrderNumber);
					$this->deleteOpenOrder($dealerOrderNumber);
					return false;
				}
				$this->addLogEntry(jsonEncode($itemParameters));
			}
		}

# Submit the orders

		if (!empty($fflOrderNumber)) {
			$response = $this->getWebServicesResponse("orders.asmx/Submit", array("OrderNumber" => $fflOrderNumber));
			if (!$response || !empty($response['ERROR']) || $response == "false") {
				$this->iErrorMessage = "Submit FFL: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				$this->deleteOpenOrder($fflOrderNumber);
				$this->deleteOpenOrder($customerOrderNumber);
				return false;
			}

			$this->getWebServicesResponse("orders.asmx/GetDetail", array("OrderNumber" => $fflOrderNumber));

			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], $fflOrderNumber);
			$remoteOrderId = $orderSet['insert_id'];
			foreach ($fflOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $fflOrderNumber, "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));

			if (!empty($fflRow['license_number']) && !empty($fflRow['file_id'])) {
				$response = $this->getWebServicesResponse("orders.asmx/TransferFFLCheck", array("FFL" => $fflRow['license_number']));
				if ($response != "V") {
					$subject = $this->iLocationCredentialRow['customer_number'] . " - " . $orderId;
					$body = "FFL Contact: " . (empty($fflRow['licensee_name']) ? $fflRow['business_name'] : $fflRow['licensee_name']) . "<br>FFL Phone: " . $fflRow['phone_number'];
					sendEmail(array("body" => $body, "subject" => $subject, "email_address" => "fulfillment@sportssouth.biz", "attachment_file_id" => $fflRow['file_id']));
				}
			}
		}

		if (!empty($customerOrderNumber)) {
			$response = $this->getWebServicesResponse("orders.asmx/Submit", array("OrderNumber" => $customerOrderNumber));
			if (!$response || !empty($response['ERROR']) || $response == "false") {
				$this->iErrorMessage = "Submit Dropship: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				$this->deleteOpenOrder($customerOrderNumber);
			} else {

				$this->getWebServicesResponse("orders.asmx/GetDetail", array("OrderNumber" => $customerOrderNumber));

				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
					$ordersRow['order_id'], $customerOrderNumber);
				$remoteOrderId = $orderSet['insert_id'];
				foreach ($customerOrderItemRows as $thisOrderItemRow) {
					executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
						$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
				}
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $customerOrderNumber, "ship_to" => $ordersRow['full_name']);
			}
		}

		if (!empty($dealerOrderNumber)) {
			$response = $this->getWebServicesResponse("orders.asmx/Submit", array("OrderNumber" => $dealerOrderNumber));
			if (!$response || !empty($response['ERROR']) || $response == "false") {
				$this->iErrorMessage = "Submit Dealer: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				$this->deleteOpenOrder($dealerOrderNumber);
			} else {

				$this->getWebServicesResponse("orders.asmx/GetDetail", array("OrderNumber" => $dealerOrderNumber));

				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
					$ordersRow['order_id'], $dealerOrderNumber);
				$remoteOrderId = $orderSet['insert_id'];
				foreach ($dealerOrderItemRows as $thisOrderItemRow) {
					executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
						$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
				}
				$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $dealerOrderNumber, "ship_to" => $GLOBALS['gClientName']);
			}
		}
		return $returnValues;
	}

	function checkFFLTransfer($federalFirearmsLicenseeId) {
		$licenseNumber = (new FFL($federalFirearmsLicenseeId))->getFieldData("license_number");
		$response = $this->getWebServicesResponse("orders.asmx/TransferFFLCheck", array("FFL" => $licenseNumber));
		if (!$response || !empty($response['ERROR'])) {
			$this->iErrorMessage = "Check FFL: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		return $response;
	}

	private function deleteOpenOrder($orderNumber) {
		$this->getWebServicesResponse("orders.asmx/DeleteOpenOrder", array("OrderNumber" => $orderNumber));
	}

	function placeDistributorOrder($productArray, $parameters = array()) {
		$userId = $parameters['user_id'];
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$userPlacedOrder = !empty($this->iLocationRow['user_location']);
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
		freeResult($resultSet);

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

		$orderParameters = array();
		$orderParameters['PO'] = $orderPrefix . $distributorOrderId;
		$orderParameters['CustomerOrderNumber'] = "";
		$orderParameters['SalesMessage'] = "";
		$orderParameters['ShipVia'] = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "SHIP_VIA", "PRODUCT_DISTRIBUTORS");

		$orderParameters['ShipToName'] = "";
		$orderParameters['ShipToAttn'] = "Distributor Order ID: " . $orderPrefix . $distributorOrderId;
		$orderParameters['ShipToAddr1'] = "";
		$orderParameters['ShipToAddr2'] = "";
		$orderParameters['ShipToCity'] = "";
		$orderParameters['ShipToState'] = "";
		$orderParameters['ShipToZip'] = substr(str_replace("-","", ($userPlacedOrder ? $GLOBALS['gUserRow']['postal_code'] : $GLOBALS['gClientRow']['postal_code'])), 0, 5);
		$orderParameters['ShipToPhone'] = "";
		$orderParameters['AdultSignature'] = "False";
		$orderParameters['Signature'] = "False";
		$orderParameters['Insurance'] = "False";

		$this->addLogEntry(jsonEncode($orderParameters));
		$response = $this->getWebServicesResponse("orders.asmx/AddHeader", $orderParameters);
		if (!$response || !empty($response['ERROR'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "Dealer Order: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			return false;
		}
		$dealerOrderNumber = (is_array($response) ? $response['int'][0] : $response);
		if (empty($dealerOrderNumber)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "Unable to create Order: " . (is_array($response) || is_object($response) ? jsonEncode($response) : $response);
			return false;
		}
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
			$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
			for ($x = 0; $x < 10; $x++) {
				$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
			}
			$itemParameters = array();
			$itemParameters['OrderNumber'] = $dealerOrderNumber;
			$itemParameters['SSItemNumber'] = $thisItemRow['distributor_product_code'];
			$itemParameters['Quantity'] = $thisItemRow['quantity'];
			$itemParameters['OrderPrice'] = "0.00";
			$itemParameters['CustomerItemNumber'] = $thisItemRow['product_id'];
			$itemParameters['CustomerItemDescription'] = $thisItemRow['product_row']['description'];
			$itemParameters['PONumber'] = "";
			$itemParameters['Comment'] = $thisItemRow['comment'];
			$response = $this->getWebServicesResponse("ordersexpanded.asmx/AddDetailExpanded", $itemParameters);
			if (!$response || !empty($response['ERROR']) || $response == "false") {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$this->iErrorMessage = "Dealer Items: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
				$this->deleteOpenOrder($dealerOrderNumber);
				return false;
			}
			$this->addLogEntry(jsonEncode($itemParameters));
		}

		$returnValues = array();

# Submit the orders

		$response = $this->getWebServicesResponse("orders.asmx/Submit", array("OrderNumber" => $dealerOrderNumber));
		if (!$response || !empty($response['ERROR']) || $response == "false") {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "Submit Dealer: Unable to get response from web services: " . (!$response ? "No response" : $response['ERROR']);
			$this->deleteOpenOrder($dealerOrderNumber);
			return false;

			# Remove code to delete order for now.
			# $this->getWebServicesResponse("orders.asmx/GetDetail", array("OrderNumber" => $fflOrderNumber));
		} else {

# Create distributor order items

			executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $dealerOrderNumber, $distributorOrderId);
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
					$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
			}
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $dealerOrderNumber);
		}

		$GLOBALS['gPrimaryDatabase']->commitTransaction();
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
		$response = $this->getWebServicesResponse("invoices.asmx/GetTrackingByOrderNumber", array("OrderNumber" => $remoteOrderRow['order_number']));
		if (empty($response) || !is_array($response)) {
			return array();
		}
		if (!array_key_exists("SHPDTE", $response)) {
			foreach ($response as $thisResponse) {
				if (is_array($thisResponse) && array_key_exists("SHPDTE", $thisResponse)) {
					$response = $thisResponse;
					break;
				}
			}
		}
		if (empty($response['SHPDTE'])) {
			$this->iErrorMessage = "Empty Ship Date";
			return false;
		}
		$dateShipped = substr($response['SHPDTE'], 0, 4) . "-" . substr($response['SHPDTE'], 4, 2) . "-" . substr($response['SHPDTE'], 6, 2);
		$trackingIdentifier = $response['TRACKNO'];
		$shippingCharge = $response['SHPAMT'];
		$serviceCode = $response['SERVICE'];
		$shippingCodes = array();
		$shippingCodes["2D"] = array("description" => "Fedex 2nd Day", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["XS"] = array("description" => "Fedex 3 Day", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["FG"] = array("description" => "Fedex Ground", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["HD"] = array("description" => "Fedex Home Delivery", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["PO"] = array("description" => "Fedex Priority Overnight", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["FP"] = array("description" => "Fedex SmartPost", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["SO"] = array("description" => "Fedex Standard Overnight", "shipping_carrier_code" => "FEDEX");
		$shippingCodes["LG"] = array("description" => "Lonestar Ground", "shipping_carrier_code" => "");
		$shippingCodes["AA"] = array("description" => "Freight - AAA Cooper", "shipping_carrier_code" => "");
		$shippingCodes["AB"] = array("description" => "Freight - Air Borne Express", "shipping_carrier_code" => "");
		$shippingCodes["CW"] = array("description" => "Freight - Conway Transport", "shipping_carrier_code" => "");
		$shippingCodes["FX"] = array("description" => "Freight - Fedex Freight", "shipping_carrier_code" => "");
		$shippingCodes["OD"] = array("description" => "Freight - Old Dominion", "shipping_carrier_code" => "");
		$shippingCodes["SA"] = array("description" => "Freight - SAIA", "shipping_carrier_code" => "");
		$shippingCodes["SE"] = array("description" => "Freight - SouthEast Freight", "shipping_carrier_code" => "");
		$shippingCodes["UP"] = array("description" => "Freight - UPS Freight", "shipping_carrier_code" => "UPS");
		$shippingCodes["05"] = array("description" => "UPS 2 Day Air", "shipping_carrier_code" => "UPS");
		$shippingCodes["07"] = array("description" => "UPS 3 Day Select", "shipping_carrier_code" => "UPS");
		$shippingCodes["09"] = array("description" => "UPS Ground", "shipping_carrier_code" => "UPS");
		$shippingCodes["MI"] = array("description" => "UPS Mail Innovations", "shipping_carrier_code" => "UPS");
		$shippingCodes["01"] = array("description" => "UPS Next Day Air", "shipping_carrier_code" => "UPS");
		$shippingCodes["03"] = array("description" => "UPS Next Day Saver", "shipping_carrier_code" => "UPS");
		$shippingCodes["SB"] = array("description" => "UPS SurePost", "shipping_carrier_code" => "UPS");
		$shippingCodes["SP"] = array("description" => "UPS SurePost", "shipping_carrier_code" => "UPS");
		$shippingCodes["PM"] = array("description" => "USPS Priority Mail", "shipping_carrier_code" => "USPS");

		$returnArray = array();
		$shippingInformation = $shippingCodes[$serviceCode];
		$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", $shippingInformation['shipping_carrier_code']);
		$carrierDescription = $shippingInformation['description'];
		$resultSet = executeQuery("update order_shipments set date_shipped = ?,tracking_identifier = ?,shipping_charge = ?,shipping_carrier_id = ?,carrier_description = ? " .
			"where order_shipment_id = ?", $dateShipped, $trackingIdentifier, $shippingCharge, $shippingCarrierId, $carrierDescription, $orderShipmentId);
		if ($resultSet['affected_rows'] > 0) {
			Order::sendTrackingEmail($orderShipmentId);
			executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingIdentifier,
				"Tracking number added by " . $this->iProductDistributorRow['description']);
			$returnArray[] = $orderShipmentId;
		}
		// check for partially fulfilled orders
		$response = $this->getWebServicesResponse("OrdersExpanded.asmx/GetDetail", array("OrderNumber" => $remoteOrderRow['order_number'],
			"CustomerOrderNumber" => $orderShipmentRow['order_id']));
		if (!empty($response)) {
			if (array_key_exists('ORITEM', $response)) {
				$response = array($response);
			}
			foreach ($response as $thisResponse) {
				$productId = getFieldFromId("product_id", "distributor_product_codes", 'product_code', $thisResponse['ORITEM'],
					"product_distributor_id = ? and client_id = ?", $this->iLocationRow['product_distributor_id'], $this->iLocationRow['client_id']);
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $orderShipmentRow['order_id'], "product_id = ?", $productId);
				$orderShipmentItemRow = getRowFromId("order_shipment_items", "order_shipment_id", $orderShipmentId, "order_item_id = ?", $orderItemId);
				if (array_key_exists('ORQTYF', $thisResponse) && $orderShipmentItemRow['quantity'] > $thisResponse['ORQTYF'] && is_numeric($thisResponse['ORQTYF']) && $thisResponse['ORQTYF'] > 0) { // order was partially fulfilled
					sendEmail(array("subject" => "Sports South Order partially filled", "body" => "<p> Order ID# " . $orderShipmentRow['order_id'] . " from Sports South " .
						"was only partially filled. The quantity of " . $orderShipmentItemRow['quantity'] . "x '" . $thisResponse['ORCUSD'] . "' was not available. " . ($thisResponse['ORQTYF'] > 0 ? "An order for "
							. $thisResponse['ORQTYF'] . " was placed successfully." : "No order was placed.") . " The missing quantity will need to be ordered from a different distributor.</p>",
						"notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
					executeQuery("update order_shipment_items set quantity = ? where order_shipment_item_id = ?", $thisResponse['ORQTYF'], $orderShipmentItemRow['order_shipment_item_id']);
				}
			}
		}
		return $returnArray;
	}

	# custom function used only by Dunhams

	function updateInventoryQuantities($itemNumbers) {
		$response = $this->getWebServicesResponse("inventory.asmx/OnhandUpdatebyCSV", array("CSVItems" => $itemNumbers));
		if (empty($response) || !empty($response['ERROR'])) {
			return false;
		} else {
			foreach ($response as $thisProductInfo) {
				$productId = getFieldFromId("product_id", "distributor_product_codes", "product_code", $thisProductInfo['I']);
				if (empty($productId)) {
					continue;
				}

				if (empty($thisProductInfo['Q'])) {
					$thisProductInfo['Q'] = 0;
				}

				$totalCost = ($thisProductInfo['C'] * $thisProductInfo['Q']);
				$this->updateProductInventory($productId, $thisProductInfo['Q'], $totalCost);
			}
		}
		return true;
	}
}
