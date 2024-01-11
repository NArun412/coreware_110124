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

class Zanders extends ProductDistributor {

	private $iConnection;
	private $iDataArrays = array();
	private $iFieldTranslations = array("upc_code" => "upc", "product_code" => "itemno", "description" => "description", "manufacturer_code" => "manufacturer", "list_price" => "itemmsrp",
		"manufacturer_sku" => "itemmpn", "manufacturer_advertised_price" => "itemmapprice", "width" => "width", "length" => "length", "height" => "height", "weight" => "itemweight", "base_cost" => "itemprice");
	private $iLogging;

	function __construct($locationId) {
		$this->iProductDistributorCode = "ZANDERS";
		parent::__construct($locationId);
		$this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_ZANDERS"));
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		$ftpDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (!$this->connect($ftpDirectory)) {
			$this->iErrorMessage = $this->iErrorMessage ?: "FTP credentials are incorrect";
			return false;
		}
		if (!ftp_chdir($this->iConnection, "/Inventory/" . $ftpDirectory)) {
			CustomField::setCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "", "PRODUCT_DISTRIBUTORS");
		}

		$apiUrl = "https://shop2.gzanders.com/webservice/orders?wsdl";
		try {
			$client = new SoapClient($apiUrl, ($GLOBALS['gDevelopmentServer'] || $this->iLogging) ? array('trace' => 1) : array());
		} catch (Exception $e) {
			$this->iErrorMessage = "Error connecting to orders web service. Contact Support.";
			return false;
		}

		$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		/** @noinspection PhpUndefinedMethodInspection */
		if (empty($userName) || empty($password)) {
			$this->iErrorMessage = "Main Account Order credentials are missing.";
			return false;
		}
		$response = $client->getTrackingInfo($userName, $password, "1");
		if ($response['returnCode'] == 1 || $response['reason'] == "Invalid username or password.") {
			$this->iErrorMessage = "Main Account Order credentials are incorrect.";
			return false;
		}

		$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		/** @noinspection PhpUndefinedMethodInspection */
		$response = $client->getTrackingInfo($userName, $password, "1");
		if ($response['returnCode'] == 1 || $response['reason'] == "Invalid username or password.") {
			$this->iErrorMessage = "Dropship Order credentials are incorrect.";
			return false;
		}

		$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		/** @noinspection PhpUndefinedMethodInspection */
		$response = $client->getTrackingInfo($userName, $password, "1");
		if ($response['returnCode'] == 1 || $response['reason'] == "Invalid username or password.") {
			$this->iErrorMessage = "Gun Order credentials are incorrect.";
			return false;
		}

		return true;
	}

	function connect($ftpDirectory = "") {
		if (!empty($this->iConnection)) {
			return true;
		}
		$this->iConnection = ftp_connect("ftp2.gzanders.com", 21, 600);
		if (!$this->iConnection) {
			$this->iErrorMessage = "Cannot connect to FTP server";
			return false;
		}
		if (!ftp_login($this->iConnection, $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'])) {
			$this->iConnection = false;
			$this->iErrorMessage = "FTP credentials are incorrect";
			return false;
		}
		ftp_pasv($this->iConnection, true);

		if (empty($ftpDirectory)) {
			$ftpDirectory = "Coreware";
		}
		$ftpDirectory = "/Inventory/" . $ftpDirectory;
		if (!ftp_chdir($this->iConnection, $ftpDirectory)) {
			$ftpDirectory = "/Inventory/Coreware";
			if (!ftp_chdir($this->iConnection, $ftpDirectory)) {
				$this->iErrorMessage = "Inventory directory is not accessible. Contact Zanders support. FTP directory used: " . $ftpDirectory;
				return false;
			}
		}
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

		$productArray = $this->makeDataArray("zandersinv.xml");
		if ($productArray === false) {
			$this->iErrorMessage = "Problem with the data";
			return false;
		}

		if (!ftp_chdir($this->iConnection, "/Inventory/Images_2")) {
			$this->iErrorMessage = "Images directory does not exist";
			return false;
		}
		$rawImageFileList = ftp_nlist($this->iConnection, ".");
		$imageFileList = array();
		foreach ($rawImageFileList as $thisFile) {
			$imageFileList[$thisFile] = $thisFile;
		}

		if (!ftp_chdir($this->iConnection, "/Inventory/Images")) {
			$this->iErrorMessage = "Alternate Images directory does not exist";
			return false;
		}
		$rawAlternateImageFileList = ftp_nlist($this->iConnection, ".");
		$alternateImageFileList = array();
		foreach ($rawAlternateImageFileList as $thisFile) {
			$alternateImageFileList[$thisFile] = $thisFile;
		}

		$insertCount = 0;
		$productFacetArray = $this->getFacets();

		self::loadValues("distributor_product_codes");
		self::loadValues("product_distributors");
		$productIdsProcessed = array();

		$processCount = 0;
		$imageCount = 0;
		$foundCount = 0;
		$updatedCount = 0;
		$noUpc = 0;
		$duplicateProductCount = 0;
		$badImageCount = 0;

		foreach ($productArray as $thisProductInfo) {
			if (empty($thisProductInfo[$this->iFieldTranslations['product_code']])) {
				continue;
			}

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

# deal with manufacturer

				$productCode = makeCode($thisProductInfo[$this->iFieldTranslations['product_code']]);
				$description = $corewareProductData['description'];

				if (empty($description)) {
					$description = $thisProductInfo[$this->iFieldTranslations['description']];
				}

				$detailedDescription = "";

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
				if ($thisProductInfo['itemserialized'] == "Y") {
					$corewareProductData['serializable'] = 1;
				}
			}

			if ($thisProductInfo['itemserialized'] == "Y" && in_array($thisProductInfo['category'], array("PISTOL", "RECEIVER", "REVOLVER", "RIFLE", "SHOTGUN", "OTHER_FIREARMS"))) {
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

					$imageId = $imageDirectory = "";
					$imageFilename = $thisProductInfo[$this->iFieldTranslations['product_code']] . ".jpg";
					if (array_key_exists($imageFilename, $imageFileList)) {
						$imageDirectory = "/Inventory/Images_2";
					}
					if (empty($imageDirectory)) {
						if (array_key_exists($imageFilename, $alternateImageFileList)) {
							$imageDirectory = "/Inventory/Images";
						}
					}
					if (empty($imageDirectory)) {
						$imageFilename = $thisProductInfo[$this->iFieldTranslations['product_code']] . ".gif";
						if (array_key_exists($imageFilename, $alternateImageFileList)) {
							$imageDirectory = "/Inventory/Images";
						}
					}
					if (!empty($imageDirectory)) {
						if (!ftp_chdir($this->iConnection, $imageDirectory)) {
							$this->iErrorMessage = "Images directory does not exist: " . $imageDirectory;
							return false;
						}
						$localImageFilename = $GLOBALS['gDocumentRoot'] . "/cache/zanders-" . getRandomString(6) . substr($imageFilename, -4);
						$imageContents = "";
						if (ftp_get($this->iConnection, $localImageFilename, $imageFilename, FTP_BINARY)) {
							$imageContents = file_get_contents($localImageFilename);
							unlink($localImageFilename);
						}

						if (!empty($imageContents)) {
							$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $imageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "ZANDERS_IMAGE"));
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
					$useLinkName = makeCode($productRow['description'], array("use_dash" => true, "lowercase" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
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

			$productDistributorDropshipProhibitionId = getFieldFromId("product_distributor_dropship_prohibition_id", "product_distributor_dropship_prohibitions", "product_id", $productId,
				"product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
			if ($thisProductInfo['allowdirectship'] == "N") {
				if (empty($productDistributorDropshipProhibitionId)) {
					executeQuery("insert ignore into product_distributor_dropship_prohibitions (product_id,product_distributor_id) values (?,?)", $productId, $this->iLocationRow['product_distributor_id']);
				}
			} elseif (!empty($productDistributorDropshipProhibitionId)) {
				executeQuery("delete from product_distributor_dropship_prohibitions where product_id = ? and product_distributor_id = ?", $productId, $this->iLocationRow['product_distributor_id']);
			}

			if (self::$iCorewareShootingSports && !$productCategoryAdded && !empty($thisProductInfo['itemcategory'])) {
				$this->addProductCategories($productId, array($thisProductInfo['itemcategory']));
			}

			if (self::$iCorewareShootingSports && empty($productFacetValueId)) {
				$productFacetValues = array();
				if (is_array($thisProductInfo['attributes']) && !empty($thisProductInfo['attributes'])) {
					foreach ($thisProductInfo['attributes'] as $thisAttribute) {
						$facetCode = makeCode($thisAttribute['title']);
						if (!empty($thisAttribute['value']) && array_key_exists($facetCode, $productFacetArray[$thisProductInfo['itemcategory']])) {
							$productFacetValues[] = array("product_facet_code" => $facetCode, "facet_value" => $thisAttribute['value']);
						}
					}
					$this->addProductFacets($productId, $productFacetValues);
				}
			}
		}

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
	}

	function makeDataArray($catalogFile) {
		if (array_key_exists($catalogFile, $this->iDataArrays)) {
			return $this->iDataArrays[$catalogFile];
		}
		$filename = $GLOBALS['gDocumentRoot'] . "/cache/zanders-" . getRandomString(6) . ".xml";
		if (!ftp_get($this->iConnection, $filename, $catalogFile, FTP_ASCII)) {
			$catalogFile = "../" . $catalogFile;
			if (!ftp_get($this->iConnection, $filename, $catalogFile, FTP_ASCII)) {
				$this->iErrorMessage = "Catalog file cannot be downloaded";
				return false;
			}
		}

		$response = file_get_contents($filename);
		unlink($filename);
		$response = trim(str_replace("<?xml version=\"1.0\"?>", "", $response), " \r\n\t\0");
		$response = trim(str_replace("\r\n", "\r", $response), " \r\n\t\0");

		$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
		$responseArray = processXml($responseArray);

		if (is_array($responseArray) && array_key_exists("NewDataSet", $responseArray)) {
			$responseArray = $responseArray['NewDataSet'];
		}

		if (is_array($responseArray) && array_key_exists("ZandersDataOut", $responseArray)) {
			$responseArray = $responseArray['ZandersDataOut'];
		}
		$productArray = array();
		foreach ($responseArray as $thisProductInfo) {
			$thisProductArray = array();
			foreach ($thisProductInfo as $fieldName => $fieldData) {
				if (is_array($fieldData)) {
					if ($fieldName == "ATTRIBUTE") {
						$dataName = "attributes";
						$attributes = array();
						if (array_key_exists("TITLE", $fieldData)) {
							$attributes[] = array("title" => $fieldData['TITLE'], "value" => $fieldData['DATA']);
						} else {
							foreach ($fieldData as $thisAttribute) {
								if (!array_key_exists("TITLE", $thisAttribute)) {
									continue;
								} elseif (strtolower($thisAttribute['TITLE']) == "height in inches" || strtolower($thisAttribute['TITLE']) == "height") {
									$thisProductArray['height'] = $thisAttribute['DATA'];
								} elseif (strtolower($thisAttribute['TITLE']) == "width in inches" || strtolower($thisAttribute['TITLE']) == "width") {
									$thisProductArray['width'] = $thisAttribute['DATA'];
								} elseif (strtolower($thisAttribute['TITLE']) == "length in inches" || strtolower($thisAttribute['TITLE']) == "length") {
									$thisProductArray['length'] = $thisAttribute['DATA'];
								} else {
									$attributes[] = array("title" => $thisAttribute['TITLE'], "value" => $thisAttribute['DATA']);
								}
							}
						}
						$dataValue = $attributes;
					} elseif (array_key_exists("TITLE", $fieldData)) {
						$dataName = strtolower($fieldData['TITLE']);
						$dataValue = $fieldData['DATA'];
					} else {
						continue;
					}
				} else {
					$dataName = strtolower($fieldName);
					$dataValue = $fieldData;
				}
				$thisProductArray[$dataName] = $dataValue;
			}
			$productArray[] = $thisProductArray;
		}
		$this->iDataArrays[$catalogFile] = $productArray;
		return $productArray;
	}

	function getFacets($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		$productArray = $this->makeDataArray("zandersinv.xml");
		if ($productArray === false) {
			$this->iErrorMessage = "Problem with the data";
			return false;
		}

		$productFacets = array();
		foreach ($productArray as $thisProductInfo) {
			$categoryCode = strtoupper($thisProductInfo['itemcategory']);
			if (!empty($categoryCode) && !array_key_exists($categoryCode, $productFacets)) {
				$productFacets[$categoryCode] = array();
			}
			if (is_array($thisProductInfo['attributes']) && !empty($thisProductInfo['attributes'])) {
				foreach ($thisProductInfo['attributes'] as $thisAttribute) {
					if (!empty($thisAttribute['title'])) {
						$productFacets[$categoryCode][makeCode($thisAttribute['title'])] = ucwords(strtolower($thisAttribute['title']));
					}
				}
			}
		}
		return $productFacets;
	}

	function getProductInventoryQuantity($productId) {
		$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
		if (empty($distributorProductCode)) {
			return false;
		}

		$ftpDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (!$this->connect($ftpDirectory)) {
			return false;
		}

		$localFilename = $GLOBALS['gDocumentRoot'] . "/cache/zanders-" . getRandomString(6) . ".csv";

		$GLOBALS['gIgnoreError'] = true;
		if (!ftp_get($this->iConnection, $localFilename, "liveinv.csv", FTP_ASCII)) {
			$GLOBALS['gIgnoreError'] = false;
			$this->iErrorMessage = "Live inventory file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		$GLOBALS['gIgnoreError'] = false;

		$fileContent = file_get_contents($localFilename);
		$fileContent = str_replace('"""', '""', $fileContent);
		file_put_contents($localFilename, $fileContent);

		$openFile = fopen($localFilename, "r");
		$fieldNames = array();
		$quantityAvailable = false;
		$cost = false;

		while ($csvData = fgetcsv($openFile)) {
			if (empty($fieldNames)) {
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
			if ($distributorProductCode != $thisProductInfo['itemnumber']) {
				continue;
			}

			$quantityAvailable = $thisProductInfo['available'];
			$cost = $thisProductInfo['price1'];
			break;
		}
		fclose($openFile);
		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$ftpDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (!$this->connect($ftpDirectory)) {
			return false;
		}
		$localFilename = $GLOBALS['gDocumentRoot'] . "/cache/zanders-" . getRandomString(6) . ".csv";
		$GLOBALS['gIgnoreError'] = true;
		if (!ftp_get($this->iConnection, $localFilename, "liveinv.csv", FTP_ASCII)) {
			$GLOBALS['gIgnoreError'] = false;
			$this->iErrorMessage = "Live inventory file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		$GLOBALS['gIgnoreError'] = false;
		$fileContent = file_get_contents($localFilename);
		$fileContent = str_replace('"""', '""', $fileContent);
		file_put_contents($localFilename, $fileContent);
		$inventoryUpdateArray = array();
		$openFile = fopen($localFilename, "r");
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

			$thisInventoryUpdate = array("product_code" => $thisProductInfo['itemnumber'],
				"quantity" => $thisProductInfo['available'],
				"cost" => $thisProductInfo['price1']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		fclose($openFile);
		unlink($localFilename);
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		$productArray = $this->makeDataArray("zandersinv.xml");
		if ($productArray === false) {
			$this->iErrorMessage = "Problem with the data";
			return false;
		}

		$productManufacturers = array();
		foreach ($productArray as $thisProductInfo) {
			$manufacturerCode = strtoupper($thisProductInfo['manufacturer']);
			if (!empty($manufacturerCode) && !array_key_exists($manufacturerCode, $productManufacturers)) {
				$productManufacturers[$manufacturerCode] = array("business_name" => ucwords(strtolower($thisProductInfo['manufacturer'])));
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

		$productArray = $this->makeDataArray("zandersinv.xml");
		if ($productArray === false) {
			$this->iErrorMessage = "Problem with the data";
			return false;
		}

		$productCategories = array();
		foreach ($productArray as $thisProductInfo) {
			$categoryCode = strtoupper($thisProductInfo['itemcategory']);
			if (!empty($categoryCode) && !array_key_exists($categoryCode, $productCategories)) {
				$productCategories[$categoryCode] = array("description" => ucwords(strtolower($thisProductInfo['itemcategoryname'])));
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

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		$orderData = array();
		$orderData['purchaseOrderNumber'] = strval($orderId);
		$orderData['orderComments'] = "";
		$orderData['shipDate'] = date("Y-m-d");
		$orderData['shipInstructions'] = "";
		$orderData['orderCommentsPhone'] = "";
		$orderData['orderCommentsEmail'] = "";
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
			$this->iErrorMessage = "Unable to get any products for this order";
			return false;
		}
		$customerOrderItemRows = $orderParts['customer_order_items'];
		$fflOrderItemRows = $orderParts['ffl_order_items'];
		$dealerOrderItemRows = $orderParts['dealer_order_items'];
		$storeNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "STORE_NUMBER", "PRODUCT_DISTRIBUTORS");
		if (empty($storeNumber)) {
			$storeNumber = "0001";
		}

		$returnValues = array();
		$fflOrderData = false;
		$fflRow = array();
		if (!empty($fflOrderItemRows)) {

			$fflOrderData = $orderData;
			$fflOrderData['shipViaCode'] = "UG";
			$fflOrderData['payCode'] = "";
			$fflOrderData['shipInstructions'] = substr($ordersRow['full_name'] . str_pad("", 40), 0, 40) . $ordersRow['phone_number'];
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

			$shipToData = array();
			$shipToData['name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
			$shipToData['address1'] = $fflRow[$addressPrefix . 'address_1'];
			$shipToData['address2'] = $fflRow[$addressPrefix . 'address_2'];
			$shipToData['city'] = $fflRow[$addressPrefix . 'city'];
			$shipToData['state'] = $fflRow[$addressPrefix . 'state'];
			$shipToData['zip'] = $fflRow[$addressPrefix . 'postal_code'];
			$shipToData['fflno'] = substr(str_replace("-", "", $fflRow['license_number']), 0, 3) . substr(str_replace("-", "", $fflRow['license_number']), -5);
			$shipToData['fflexp'] = date("Y-m-d", strtotime($fflRow['expiration_date']));
			$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
			if (empty($userName) || empty($password)) {
				$this->iErrorMessage = "Gun Order credentials are missing.  Enter them in Distributor Credentials.";
				return false;
			}

			$apiUrl = "https://shop2.gzanders.com/webservice/shiptoaddresses?wsdl";
			try {
				$client = new SoapClient($apiUrl, ($GLOBALS['gDevelopmentServer'] || $this->iLogging) ? array('trace' => 1) : array());
			} catch (Exception $e) {
				$this->iErrorMessage = "Soap error getting ship to addresses: " . $e->getMessage();
				return false;
			}
			/** @noinspection PhpUndefinedMethodInspection */
			try {
				$response = $client->useShipTo($userName, $password, $shipToData);
			} catch (Exception $e) {
				$GLOBALS['gPrimaryDatabase']->logError($e->getMessage());
				$this->iErrorMessage = "Error setting shipping address: " . $e->getMessage();
				return false;
			}
			if ($this->iLogging) {
				addDebugLog("Zanders Request XML: " . $client->__getLastRequest() . "\n"
					. "Zanders Response XML: " . $client->__getLastResponse() . "\n"
					. (empty($this->iErrorMessage) ? "" : "Zanders Error: " . $this->iErrorMessage));
			}
			if ($response['returnCode'] == 0) {
				$shipToNumber = $response['shipToAddress']['ShipToNo'];
			} else {
				$shipToNumber = $response['searchResults']['ShipToNo'];
			}
			if (empty($shipToNumber)) {
				$shipToNumber = "0002";
				$fflOrderData['payCode'] = "H";
				$returnValues['info_message'] = "The order was placed, but you need to contact Zanders to resolve an issue with the FFL Dealer";
			}
			$fflOrderData['shipToNo'] = $shipToNumber;

			$orderDataItems = array();
			foreach ($fflOrderItemRows as $thisItemRow) {
				$itemParameters = array();
				$itemParameters['itemNumber'] = $thisItemRow['distributor_product_code'];
				$itemParameters['quantity'] = $thisItemRow['quantity'];
				$itemParameters['allowBackOrder'] = false;
				$orderDataItems[] = $itemParameters;
			}
			$fflOrderData['items'] = $orderDataItems;

		}

		$customerOrderData = false;
		if (!empty($customerOrderItemRows)) {
			$customerOrderData = $orderData;
			$customerOrderData['shipViaCode'] = "UM";
			$customerOrderData['shipToName'] = $ordersRow['full_name'];
			$customerOrderData['shipToAddress1'] = $addressRow['address_1'];
			$customerOrderData['shipToAddress2'] = $addressRow['address_2'];
			$customerOrderData['shipToCity'] = $addressRow['city'];
			$customerOrderData['shipToState'] = $addressRow['state'];
			$customerOrderData['shipToZip'] = $addressRow['postal_code'];
			$orderDataItems = array();
			foreach ($customerOrderItemRows as $thisItemRow) {
				$itemParameters = array();
				$itemParameters['itemNumber'] = $thisItemRow['distributor_product_code'];
				$itemParameters['quantity'] = $thisItemRow['quantity'];
				$itemParameters['allowBackOrder'] = false;
				$orderDataItems[] = $itemParameters;
			}
			$customerOrderData['items'] = $orderDataItems;
		}

		$dealerOrderData = false;
		if (!empty($dealerOrderItemRows)) {
			$dealerOrderData = $orderData;
			$dealerOrderData['shipViaCode'] = "BW";
			$dealerOrderData['shipToNo'] = $storeNumber;
			$dealerOrderData['payCode'] = "H";
			$orderDataItems = array();
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$itemParameters = array();
				$itemParameters['itemNumber'] = $thisItemRow['distributor_product_code'];
				$itemParameters['quantity'] = $thisItemRow['quantity'];
				$itemParameters['allowBackOrder'] = false;
				$orderDataItems[] = $itemParameters;
			}
			$dealerOrderData['items'] = $orderDataItems;
		}

		$failedItems = array();

# Submit the orders

		$apiUrl = "https://shop2.gzanders.com/webservice/orders?wsdl";
		try {
			$client = new SoapClient($apiUrl, ($GLOBALS['gDevelopmentServer'] || $this->iLogging) ? array('trace' => 1) : array());
		} catch (Exception $e) {
			$this->iErrorMessage = "Soap Error getting orders web service: " . $e->getMessage();
			return false;
		}

		if (!empty($fflOrderData)) {
			$failedItemIds = array();
			$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");

            try {
                $response = $client->createOrder($userName, $password, $fflOrderData);
            } catch(Exception $exception) {
                $this->iErrorMessage = $exception->getMessage();
            }
			if ($this->iLogging) {
				addDebugLog("Zanders Request XML: " . $client->__getLastRequest() . "\n"
					. "Zanders Response XML: " . $client->__getLastResponse() . "\n"
					. (empty($this->iErrorMessage) ? "" : "Zanders Error: " . $this->iErrorMessage));
			}
			if (!empty($response) && $response['returnCode'] == 0) {
				$fflOrderNumber = $response['orderNumber'];
				if (is_array($response['removedItems'])) {
					foreach ($response['removedItems'] as $removedItems) {
						foreach ($fflOrderItemRows as $thisItemRow) {
							if ($removedItems['itemNumber'] == $thisItemRow['distributor_product_code']) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
								$failedItemIds[] = $thisItemRow['order_item_id'];
								break;
							}
						}
					}
				}
				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
					$ordersRow['order_id'], $fflOrderNumber);
				$remoteOrderId = $orderSet['insert_id'];
				foreach ($fflOrderItemRows as $thisOrderItemRow) {
					if (in_array($thisOrderItemRow['order_item_id'], $failedItemIds)) {
						continue;
					}
					executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
						$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
				}
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $fflOrderNumber, "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));

				if (!empty($fflRow['file_id'])) {
					$osFilename = getFieldFromId("os_filename", "files", "file_id", $fflRow['file_id']);
					$extension = getFieldFromId("extension", "files", "file_id", $fflRow['file_id']);
					if (empty($osFilename)) {
						$fflLicenseFileContent = getFieldFromId("file_content", "files", "file_id", $fflRow['file_id']);
					} else {
						$fflLicenseFileContent = getExternalFileContents($osFilename);
					}
					if (!empty($fflLicenseFileContent)) {
						$directoryName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
						if (empty($directoryName)) {
							$directoryName = "Coreware";
						}
						ftpFilePutContents("ftp2.gzanders.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
							"Inventory/" . $directoryName . "/FFL", substr(str_replace("-", "", $fflRow['license_number']), 0, 3) . substr(str_replace("-", "", $fflRow['license_number']), -5) . "." . $extension, $fflLicenseFileContent);
					}
				}
			} else {
				if (empty($userName) || empty($password)) {
					$this->iErrorMessage = "Gun Order credentials are missing.  Enter them in Distributor Credentials.";
				} else {
					$this->iErrorMessage = "FFL Order: " . ($response['reason'] ?: $this->iErrorMessage);
				}
				return false;
			}
		}

		if (!empty($customerOrderData)) {
			$failedItemIds = array();
			$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
			/** @noinspection PhpUndefinedMethodInspection */
            try {
                $response = $client->createOrder($userName, $password, $customerOrderData);
            } catch(Exception $exception) {
                $this->iErrorMessage = $exception->getMessage();
            }
			if ($this->iLogging) {
				addDebugLog("Zanders Request XML: " . $client->__getLastRequest() . "\n"
					. "Zanders Response XML: " . $client->__getLastResponse() . "\n"
					. (empty($this->iErrorMessage) ? "" : "Zanders Error: " . $this->iErrorMessage));
			}
			if (!empty($response) && $response['returnCode'] == 0) {
				$customerOrderNumber = $response['orderNumber'];
				if (is_array($response['removedItems'])) {
					foreach ($response['removedItems'] as $removedItems) {
						foreach ($customerOrderItemRows as $thisItemRow) {
							if ($removedItems['itemNumber'] == $thisItemRow['distributor_product_code']) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
								$failedItemIds[] = $thisItemRow['order_item_id'];
								break;
							}
						}
					}
				}
				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
					$ordersRow['order_id'], $customerOrderNumber);
				$remoteOrderId = $orderSet['insert_id'];
				foreach ($customerOrderItemRows as $thisOrderItemRow) {
					if (in_array($thisOrderItemRow['order_item_id'], $failedItemIds)) {
						continue;
					}
					executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
						$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
				}
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $customerOrderNumber, "ship_to" => $ordersRow['full_name']);
			} else {
				if (empty($userName) || empty($password)) {
					$this->iErrorMessage = "Dropship Order credentials are missing.  Enter them in Distributor Credentials.";
				} else {
					$this->iErrorMessage = "Customer Order: " . ($response['reason'] ?: $this->iErrorMessage);
				}
				foreach ($customerOrderItemRows as $thisItemRow) {
					$failedItems = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
				}
			}
		}

		if (!empty($dealerOrderData)) {
			$dealerOrderData['shipToName'] = "";
			$dealerOrderData['shipToAddress1'] = "";
			$dealerOrderData['shipToAddress2'] = "";
			$dealerOrderData['shipToCity'] = "";
			$dealerOrderData['shipToState'] = "";
			$dealerOrderData['shipToZip'] = "";

			$failedItemIds = array();
			$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
			/** @noinspection PhpUndefinedMethodInspection */
            try {
                $response = $client->createOrder($userName, $password, $dealerOrderData);
            } catch(Exception $exception) {
                $this->iErrorMessage = $exception->getMessage();
            }
			if ($this->iLogging) {
				addDebugLog("Zanders Request XML: " . $client->__getLastRequest() . "\n"
					. "Zanders Response XML: " . $client->__getLastResponse() . "\n"
					. (empty($this->iErrorMessage) ? "" : "Zanders Error: " . $this->iErrorMessage));
			}
			if (!empty($response) && $response['returnCode'] == 0) {
				$dealerOrderNumber = $response['orderNumber'];
				if (is_array($response['removedItems'])) {
					foreach ($response['removedItems'] as $removedItems) {
						foreach ($dealerOrderItemRows as $thisItemRow) {
							if ($removedItems['itemNumber'] == $thisItemRow['distributor_product_code']) {
								$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
								$failedItemIds[] = $thisItemRow['order_item_id'];
								break;
							}
						}
					}
				}
				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
					$ordersRow['order_id'], $dealerOrderNumber);
				$remoteOrderId = $orderSet['insert_id'];
				foreach ($dealerOrderItemRows as $thisOrderItemRow) {
					if (in_array($thisOrderItemRow['order_item_id'], $failedItemIds)) {
						continue;
					}
					executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
						$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
				}
				$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $dealerOrderNumber, "ship_to" => $GLOBALS['gClientName']);
			} else {
				if (empty($userName) || empty($password)) {
					$this->iErrorMessage = "Main Account Order credentials are missing.  Enter them in Distributor Credentials.";
				} else {
					$this->iErrorMessage = "Dealer Order: " . ($response['reason'] ?: $this->iErrorMessage);
				}
				foreach ($dealerOrderItemRows as $thisItemRow) {
					$failedItems = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
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
		$storeNumber = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "STORE_NUMBER", "PRODUCT_DISTRIBUTORS");
		if (empty($storeNumber)) {
			$storeNumber = "0001";
		}

# Process products to be ordered

		$orderData = array();
		$orderData['purchaseOrderNumber'] = $orderPrefix . $distributorOrderId;
		$orderData['orderComments'] = "";
		$orderData['shipDate'] = date("Y-m-d");
		$orderData['shipInstructions'] = "";
		$orderData['orderCommentsPhone'] = "";
		$orderData['orderCommentsEmail'] = "";

		$dealerOrderData = $orderData;
		$dealerOrderData['shipViaCode'] = "BW";
		$dealerOrderData['shipToNo'] = $storeNumber;
		$dealerOrderData['payCode'] = "H";

		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
		$orderDataItems = array();
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$itemParameters = array();
			$itemParameters['itemNumber'] = $thisItemRow['distributor_product_code'];
			$itemParameters['quantity'] = $thisItemRow['quantity'];
			$itemParameters['allowBackOrder'] = false;
			$orderDataItems[] = $itemParameters;
		}
		$dealerOrderData['items'] = $orderDataItems;

		$returnValues = array();
		$failedItems = array();

# Submit the orders

		$apiUrl = "https://shop2.gzanders.com/webservice/orders?wsdl";
		try {
			$client = new SoapClient($apiUrl, ($GLOBALS['gDevelopmentServer'] || $this->iLogging) ? array('trace' => 1) : array());
		} catch (Exception $e) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = $e->getMessage();
			return false;
		}

		$dealerOrderData['shipToName'] = $dealerName;
		$dealerOrderData['shipToAddress1'] = "";
		$dealerOrderData['shipToAddress2'] = "";
		$dealerOrderData['shipToCity'] = "";
		$dealerOrderData['shipToState'] = "";
		$dealerOrderData['shipToZip'] = "";

		$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		/** @noinspection PhpUndefinedMethodInspection */
		$response = $client->createOrder($userName, $password, $dealerOrderData);
		if ($this->iLogging) {
			addDebugLog("Zanders Request XML: " . $client->__getLastRequest() . "\n"
				. "Zanders Response XML: " . $client->__getLastResponse() . "\n"
				. (empty($this->iErrorMessage) ? "" : "Zanders Error: " . $this->iErrorMessage));
		}
		if ($response['returnCode'] == 0) {
			$dealerOrderNumber = $response['orderNumber'];
			if (is_array($response['removedItems'])) {
				foreach ($response['removedItems'] as $removedItems) {
					foreach ($dealerOrderItemRows as $thisItemRow) {
						if ($removedItems['itemNumber'] == $thisItemRow['distributor_product_code']) {
							$failedItems[$thisItemRow['product_id']] = array("order_item_id" => $thisItemRow['product_id'], "quantity" => $thisItemRow['quantity']);
							break;
						}
					}
				}
			}
			executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $dealerOrderNumber, $distributorOrderId);
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				if (array_key_exists($thisOrderItemRow['product_id'], $failedItems)) {
					continue;
				}
				executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
					$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
			}
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $dealerOrderNumber);
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		} else {
			$this->iErrorMessage = $response['reason'];
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		}

		if (!empty($failedItems)) {
			$returnValues['failed_items'] = $failedItems;
		}
		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		$remoteOrderId = getFieldFromId("remote_order_id", "order_shipments", "order_shipment_id", $orderShipmentId);
		if (empty($remoteOrderId)) {
			return false;
		}
		$orderNumber = getFieldFromId("order_number", "remote_orders", "remote_order_id", $remoteOrderId);
		if (empty($orderNumber)) {
			return false;
		}

		$apiUrl = "https://shop2.gzanders.com/webservice/orders?wsdl";
		try {
			$client = new SoapClient($apiUrl, ($GLOBALS['gDevelopmentServer'] || $this->iLogging) ? array('trace' => 1) : array());
		} catch (Exception $e) {
			return false;
		}
		$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		/** @noinspection PhpUndefinedMethodInspection */
		$response = $client->getTrackingInfo($userName, $password, $orderNumber);
		if ($response['returnCode'] != 0 || $response['numberOfShipments'] < 1) {
			$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
			/** @noinspection PhpUndefinedMethodInspection */
			$response = $client->getTrackingInfo($userName, $password, $orderNumber);
		}
		if ($response['returnCode'] != 0 || $response['numberOfShipments'] < 1) {
			$userName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
			$password = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DEALER_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
			/** @noinspection PhpUndefinedMethodInspection */
			$response = $client->getTrackingInfo($userName, $password, $orderNumber);
		}
		if ($response['returnCode'] != 0 || $response['numberOfShipments'] < 1) {
			return false;
		}
		$trackingInfo = $response['trackingNumbers'][0];
		$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($trackingInfo['shipCompany']));
		$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
			$trackingInfo['trackingNumber'], $shippingCarrierId, $trackingInfo['shipVia'], $orderShipmentId);
		if ($resultSet['affected_rows'] > 0) {
			Order::sendTrackingEmail($orderShipmentId);
			executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingInfo['trackingNumber'],
				"Tracking number added by " . $this->iProductDistributorRow['description']);
			return array($orderShipmentId);
		}
		return array();
	}
}
