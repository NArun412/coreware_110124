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

class Davidsons extends ProductDistributor {

	private $iConnection;
	private $iFieldTranslations = array("upc_code" => "upc_code", "product_code" => "itemno", "description" => "item_description", "alternate_description" => "itemdesc1",
		"detailed_description" => "XXXXXXX", "manufacturer_code" => "manufacturer", "model" => "XXXXXX", "manufacturer_sku" => "XXXXXX", "manufacturer_advertised_price" => "retailmap",
		"width" => "XXXXXX", "length" => "overall_length", "height" => "XXXXXX", "weight" => "weight", "base_cost" => "dealer_price", "list_price" => "retail_price");

	private $iLiveUrl = "https://dealernetwork.davidsonsinc.com/api/orderservice.asmx?WSDL";
	private $iTestUrl = "https://dealernetwork.davidsonsinc.com/testapi/orderservice.asmx?WSDL";
	private $iUseUrl;
	private $iWebServiceClient = false;
	const DAVIDSONS_SECURITY_TOKEN = 'jsb7GxGvoK2GFO/ZOmKpEw5RnbI2W/WXsEFLkwtrcfo=';

	function __construct($locationId) {
		$this->iProductDistributorCode = "DAVIDSONS";
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
        if($this->isSoapInstalled()) {
            $soapOptions = array('soap_version' => SOAP_1_2);
            if ($GLOBALS['gDevelopmentServer']) {
                $this->iUseUrl = $this->iTestUrl;
                $soapOptions['trace'] = 1;
            } else {
                $this->iUseUrl = $this->iLiveUrl;
            }
            try {
                $this->iWebServiceClient = new SoapClient($this->iUseUrl, $soapOptions);
            } catch (Exception $e) {
                $this->iErrorMessage = "Error connecting to webservice: " . $e->getMessage();
            }
        }
	}

    private function isSoapInstalled() {
        if(!(class_exists("SOAPClient"))) {
            $this->iErrorMessage = "SOAPClient is not installed on this server.  Please contact your system administrator.";
            $GLOBALS['gPrimaryDatabase']->logError($this->iErrorMessage);
            return false;
        }
        return true;
    }

    function connect() {
		$this->iConnection = ftp_connect("ftp.davidsonsinventory.com", 21, 600);
		if (!$this->iConnection) {
			return false;
		}
		if (!ftp_login($this->iConnection, 'ftp58074930-1', 'DavDealerInv')) {
			$this->iErrorMessage = "Invalid Login";
			return false;
		}
		ftp_pasv($this->iConnection, true);
		return true;
	}

	function testCredentials() {
		// test product feed
		if (!$this->connect()) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unable to access FTP server: contact support";
			return false;
		}
		if (!$this->iWebServiceClient) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unable to connect to web service: contact support";
			return false;
		}
		return true;
	}

	function syncProducts($parameters = array()) {

		$products = $this->getProducts();

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

		foreach ($products as $thisProductInfo) {
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
					$description = (empty($thisProductInfo[$this->iFieldTranslations['description']]) ? $thisProductInfo[$this->iFieldTranslations['alternate_description']] : $thisProductInfo[$this->iFieldTranslations['description']]);
				}

				if (empty($description)) {
					$description = "Product " . $thisProductInfo['product_id'];
				}
				$description = str_replace("\n", " ", $description);
				$description = str_replace("\r", " ", $description);
				while (strpos($description, "  ") !== false) {
					$description = str_replace("  ", " ", $description);
				}

				if (!empty($thisProductInfo[$this->iFieldTranslations['base_cost']])) {
					$cost = $thisProductInfo[$this->iFieldTranslations['base_cost']];
				} elseif (!empty($thisProductInfo[$this->iFieldTranslations['list_price']])) {
					$cost = $thisProductInfo[$this->iFieldTranslations['list_price']];
				} else {
					$cost = "";
				}

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

				//ensure sale price is a float number
				$salePrice = $thisProductInfo['sale_price'];

				if (!empty($salePrice) && $salePrice < $cost) {
					//check if end sale date is >= today
					$endSaleDate = date("Y-m-d", strtotime($thisProductInfo['sale_ends']));
					$today = date("Y-m-d");

					if (empty($thisProductInfo['sale_ends']) || $today <= $endSaleDate) {
						$cost = $salePrice;
					}
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$insertSet = executeQuery("insert into products (client_id,product_code,description,link_name,remote_identifier,product_manufacturer_id," .
					"base_cost,list_price,date_created,time_changed,reindex,internal_use_only) values (?,?,?,?,?, ?,?,?,now(),now(),1, ?)",
					$GLOBALS['gClientId'], $useProductCode, $description, $useLinkName, $remoteIdentifier, $productManufacturerId, $cost,
					$thisProductInfo[$this->iFieldTranslations['list_price']], $internalUseOnly);
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
				if (!empty($thisProductInfo['guntype'])) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if (!empty($thisProductInfo['guntype'])) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}

			if (!self::$iCorewareShootingSports) {
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

		}

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicates";
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

		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/davidsons-inv-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "davidsons_inventory.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Inventory file cannot be downloaded";
			return false;
		}
		$quantityFilename = $GLOBALS['gDocumentRoot'] . "/cache/davidsons-qty-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $quantityFilename, "davidsons_quantity.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Quantity file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		if (!file_exists($catalogFilename) || !file_exists($quantityFilename)) {
			$this->iErrorMessage = "Failed to find file";
			return false;
		}

		$openFile = fopen($catalogFilename, "r");
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			if (empty($fieldNames)) {
				foreach ($csvData as $fieldName) {
					$fieldName = trim(str_replace("#", "", $fieldName));
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if ($thisProductInfo['item'] != $distributorProductCode) {
				continue;
			}
			$quantityAvailable = str_replace("+", "", $thisProductInfo['quantity']);
			$cost = floatval(str_replace('$', '', str_replace(',', '', $thisProductInfo['dealer_price'])));
			break;
		}
		fclose($openFile);

		$openFile = fopen($quantityFilename, "r");
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			if (empty($fieldNames)) {
				foreach ($csvData as $fieldName) {
					$fieldName = trim(str_replace("#", "", $fieldName));
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if ($thisProductInfo['item_number'] != $distributorProductCode) {
				continue;
			}

			$thisProductInfo['quantity_az'] = str_replace("+", "", $thisProductInfo['quantity_az']);
			$thisProductInfo['quantity_nc'] = str_replace("+", "", $thisProductInfo['quantity_nc']);
			// Using !is_numeric() instead of empty() to make sure we catch out of stock products. empty(0) = true
			if (is_numeric($thisProductInfo['quantity_az']) && is_numeric($thisProductInfo['quantity_nc'])) {
				$quantityAvailable = intval($thisProductInfo['quantity_az']) + intval($thisProductInfo['quantity_nc']);
			}
			break;
		}

		fclose($openFile);
		unlink($catalogFilename);
		unlink($quantityFilename);

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/davidsons-inv-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $catalogFilename, "davidsons_inventory.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Inventory file cannot be downloaded";
			return false;
		}
		$quantityFilename = $GLOBALS['gDocumentRoot'] . "/cache/davidsons-qty-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $quantityFilename, "davidsons_quantity.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Quantity file cannot be downloaded";
			return false;
		}
		ftp_close($this->iConnection);
		if (!file_exists($catalogFilename) || !file_exists($quantityFilename)) {
			$this->iErrorMessage = "Failed to find file";
			return false;
		}
		$openFile = fopen($catalogFilename, "r");
		$count = 0;
		$fieldNames = array();
		$inventoryUpdateArray = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = trim(str_replace("#", "", $fieldName));
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}

			$productCost = floatval(str_replace('$', '', str_replace(',', '', $thisProductInfo['dealer_price'])));
			if (empty($productCost)) {
				$productCost = floatval(str_replace('$', '', str_replace(',', '', $thisProductInfo['retail_price'])));
			}
			$salePrice = floatval(str_replace('$', '', str_replace(',', '', $thisProductInfo['sale_price'])));

			if (!empty($salePrice) && $salePrice < $productCost) {//if for some reason sale price is greater than list price then just use list price
				//check if end sale date is >= today
				$endSaleDate = date("Y-m-d", strtotime($thisProductInfo['sale_ends']));
				$today = date("Y-m-d");

				if (empty($thisProductInfo['sale_ends']) || $today <= $endSaleDate) {
					$productCost = $salePrice;
				}
			}
			$inventoryUpdateArray[$thisProductInfo['item']] = array("product_code" => $thisProductInfo['item'],
				"quantity" => str_replace("+", "", $thisProductInfo['quantity']),
				"cost" => $productCost);
		}
		fclose($openFile);

		$openFile = fopen($quantityFilename, "r");
		$count = 0;
		$fieldNames = array();
		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = trim(str_replace("#", "", $fieldName));
					$fieldName = makeCode($fieldName, array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}
			if (!array_key_exists($thisProductInfo['item_number'], $inventoryUpdateArray)) {
				continue;
			}
			$thisProductInfo['quantity_az'] = str_replace("+", "", $thisProductInfo['quantity_az']);
			$thisProductInfo['quantity_nc'] = str_replace("+", "", $thisProductInfo['quantity_nc']);

			// Using !is_numeric() instead of empty() to make sure we catch out of stock products. empty(0) = true
			if (is_numeric($thisProductInfo['quantity_az']) || is_numeric($thisProductInfo['quantity_nc'])) {
				if (!is_numeric($thisProductInfo['quantity_az'])) {
					$thisProductInfo['quantity_az'] = 0;
				}
				if (!is_numeric($thisProductInfo['quantity_nc'])) {
					$thisProductInfo['quantity_nc'] = 0;
				}
				$inventoryUpdateArray[$thisProductInfo['item_number']]['quantity'] = intval($thisProductInfo['quantity_az']) + intval($thisProductInfo['quantity_nc']);
			}
		}
		fclose($openFile);
		unlink($catalogFilename);
		unlink($quantityFilename);
		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	/* This function pulls all the products from Davidsons server in the file davidsons_firearm_attributes.csv
	and returns an array of arrays with each property of a product indexable in each sub array */
	public function getProducts($file = false) {

		if ($file === false) {
			//Recursively get products
			$productsListOne = $this->getProducts("davidsons_inventory.csv");

			//Recursively get products
			// Per Davidsons, davidsons_firearm_attributes.csv is obsolete and no longer updated
			// We should be using DavidsonsInventoryFeed.txt, but that file is not correctly delimited
			//$productsListTwo = $this->getProducts("davidsons_firearm_attributes.csv");
			$productsListTwo = array();

			$productListIndexableByItemId = [];

			$priceFields = array('pricesale', 'msp', 'retail_price', 'dealer_price', 'sale_price', 'dealerprice', 'retailprice', 'retailmap');

			foreach ($productsListOne as $productListRow) {

				foreach ($productListRow as $index => $value) {
					if (in_array($index, $priceFields)) {
						$productListRow[$index] = floatval(str_replace('$', '', str_replace(',', '', $value)));
					}
				}

				$productListIndexableByItemId[$productListRow['itemno']] = $productListRow;
			}

			foreach ($productsListTwo as $productListRow) {

				foreach ($productListRow as $index => $value) {
					if (in_array($index, $priceFields)) {
						$productListRow[$index] = floatval(str_replace('$', '', str_replace(',', '', $value)));
					}
				}

				if (array_key_exists($productListRow['itemno'], $productListIndexableByItemId)) {
					$productListIndexableByItemId[$productListRow['itemno']] = array_merge($productListIndexableByItemId[$productListRow['itemno']], $productListRow);
				}
			}

			return array_values($productListIndexableByItemId);
		}

		if (!$this->connect()) {
			return false;
		}

		$getFileFromServer = $file;
		$inventoryFilename = $GLOBALS['gDocumentRoot'] . "/cache/davidsonswholesale-" . getRandomString(6) . ".csv";
		if (!ftp_get($this->iConnection, $inventoryFilename, $getFileFromServer, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file cannot be downloaded";
			return false;
		}
		if (!file_exists($inventoryFilename)) {
			$this->iErrorMessage = "Import file does not exist";
			return false;
		}

		$openFile = fopen($inventoryFilename, "r");
		$count = 0;
		$fieldNames = array();

		$products = array();

		while ($csvData = fgetcsv($openFile)) {
			$count++;
			if ($count == 1) {
				foreach ($csvData as $fieldName) {
					$fieldName = strtolower(trim($fieldName));
					if ($fieldName == "item #") {
						$fieldName = "ItemNo";
					}
					if ($fieldName == "upc") {
						$fieldName = "upc_code";
					}
					$fieldName = makeCode(trim($fieldName), array("lowercase" => true));
					$fieldNames[] = $fieldName;
				}
				continue;
			}
			$thisProductInfo = array();
			foreach ($fieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($csvData[$index]);
			}

			if (empty($thisProductInfo['itemno'])) {
				continue;
			}

			$upcCode = ProductCatalog::makeValidUPC($thisProductInfo[$this->iFieldTranslations['upc_code']]);
			$upcCode = str_replace('#', '', $upcCode);
			if (empty($upcCode)) {
				continue;
			}

			$products[] = $thisProductInfo;
		}

		return $products;
	}

	function getManufacturers($parameters = array()) {

		$products = $this->getProducts("davidsons_inventory.csv");

		$manufacturers = [];
		$alreadyIncludedManufacturers = [];

		$manufacturerIndex = 'manufacturer';
		foreach ($products as $product) {
			if (!in_array(md5($product[$manufacturerIndex]), $alreadyIncludedManufacturers)) {
				//push a hash value of this manufacturer so that we don't include it more than once in the list
				$alreadyIncludedManufacturers[] = md5($product[$manufacturerIndex]);

				$manufacturers[makeCode($product[$manufacturerIndex])] = array('business_name' => $product[$manufacturerIndex]);

			}
		}

		return $manufacturers;

	}

	function sortManufacturers($a, $b) {
		if ($a['business_name'] == $b['business_name']) {
			return 0;
		}
		return ($a['business_name'] > $b['business_name']) ? 1 : -1;
	}

	function getCategories($parameters = array()) {
		return [];
	}

	function getFacets($parameters = array()) {
		return [];
	}

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		if (!$this->iWebServiceClient) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unable to connect to web service";
			return false;
		}
		$ordersRow = getRowFromId("orders", "order_id", $orderId);

		$orderParts = $this->splitOrder($orderId, $orderItems);
		if ($orderParts === false) {
			return false;
		}
		// Per Davidsons, they do not do any dropship as of Jan 2021.
		$dealerOrderItemRows = array_merge($orderParts['customer_order_items'], $orderParts['dealer_order_items'], $orderParts['ffl_order_items']);

# Ordering Dealer Number, O. Dealer Name, Ship to Name, Ship To Address, Ship to City, Ship to State, Ship to Zip, Ship to email,
#Ship to phone, Ship to FFL (if Firearm), Item, Item Desc, Item UPC, Item Qty, Item Price, Special Instructions

		$failedItems = array();
		$returnValues = array();

# Submit the orders

		if (!empty($dealerOrderItemRows)) {
			$useApiLocations = strtoupper(getPreference("DAVIDSONS_USE_API_ORDERING_LOCATIONS")) ?: "ALL";
			if ($useApiLocations == "ALL" || stristr($useApiLocations, $this->iLocationRow['location_code']) !== false) {
				$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
					$ordersRow['order_id'], "1234");
				$remoteOrderId = $orderSet['insert_id'];
				$createOrderRequest = new stdClass();
				$createOrderRequest->Token = self::DAVIDSONS_SECURITY_TOKEN;
				$createOrderRequest->ConsumerName = $ordersRow['full_name'];
				$createOrderRequest->ConsumerPhone = $ordersRow['phone_number'];
				$createOrderRequest->DealerCustomerNumber = $this->iLocationCredentialRow['customer_number'];
				$createOrderRequest->ReferenceNumber = $orderId . "-" . $remoteOrderId;

				$itemArray = array();
				foreach ($dealerOrderItemRows as $thisItemRow) {
					$itemRequest = new stdClass();
					$itemRequest->ItemNumber = $thisItemRow['distributor_product_code'];
					$itemRequest->Quantity = $thisItemRow['quantity'];
					$itemArray[] = $itemRequest;
				}
				$createOrderRequest->Items = $itemArray;
				$createOrderRequest->Note = "";
				$createOrderRequest->UseExpeditedShipping = "";
				$createOrder = new stdClass();
				$createOrder->request = $createOrderRequest;
				try {
					$response = $this->iWebServiceClient->CreateOrder($createOrder);
				} catch (Exception $e) {
					$this->iErrorMessage = "Web service error:" . $e->getMessage();
					$response = false;
				}
				if (!$response) {
					$this->iErrorMessage = $this->iErrorMessage ?: "Web service error.";
				} elseif ($response->CreateOrderResult->ResponseCode != 0) {
					$this->iErrorMessage = $response->CreateOrderResult->ResponseCode . ": " . $response->CreateOrderResult->ResponseText;
				}
				if (!empty($this->iErrorMessage)) {
					foreach ($dealerOrderItemRows as $thisItemRow) {
						$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
					}
				} else {
					$orderResultArray = array();
					if (is_array($response->CreateOrderResult->Orders->Order)) {
						$orderResultArray = $response->CreateOrderResult->Orders->Order;
					} else {
						$orderResultArray[] = $response->CreateOrderResult->Orders->Order;
					}
					foreach ($orderResultArray as $index => $orderResult) {
						$orderNumber = $orderResult->OrderNumber;
						if ($index > 0) {
							$insertSet = executeQuery("insert into remote_orders (order_id, order_number) values (?,?)", $ordersRow['order_id'], $orderNumber);
							$remoteOrderId = $insertSet['insert_id'];
						}
						if (is_array($orderResult->OrderLineItems->OrderLineItem)) {
							$lineItemsArray = $orderResult->OrderLineItems->OrderLineItem;
						} else {
							$lineItemsArray = $orderResult->OrderLineItems;
						}
						foreach ($dealerOrderItemRows as $thisItemRow) {
							foreach ($lineItemsArray as $thisLineItem) {
								if ($thisItemRow['distributor_product_code'] != $thisLineItem->ItemNumber) {
									continue;
								}
								if ($thisLineItem->Quantity > 0) {
									executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
										$remoteOrderId, $thisItemRow['order_item_id'], $thisItemRow['quantity']);
								}
							}
						}

						if ($index == 0) {
							executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
							$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $GLOBALS['gClientName']);
						} else {
							$returnValues['dealer_' . $index] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $GLOBALS['gClientName']);
						}
					}
				}
			} else {
				$dealerFileContent = "";
				foreach ($dealerOrderItemRows as $thisItemRow) {
					$thisItemRow['product_row']['description'] = str_replace("\n", " ", $thisItemRow['product_row']['description']);
					$thisItemRow['product_row']['description'] = str_replace("\r", " ", $thisItemRow['product_row']['description']);
					for ($x = 0; $x < 10; $x++) {
						$thisItemRow['product_row']['description'] = str_replace("  ", " ", $thisItemRow['product_row']['description']);
					}
					$specialInstructions = "Order ID: " . $orderId . "-%remote_order_id%, Customer: " . $ordersRow['full_name'] . ", Customer Phone: " . $ordersRow['phone_number'];
					if (empty($dealerFileContent)) {
						$dealerFileContent .= "Login,Dealer,Ship To,Address 1,City,State,Zip,Email,Phone,License #,Item #,Description,UPC,Quantity,Price,Instructions\n";
					}
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

				$orderEmailAddress = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
				if (empty($orderEmailAddress)) {
					$this->iErrorMessage = "No Email Address is sent for sending orders. Set the email address in Location Credentials.";
					return false;
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
						$returnValues['dealer'] = array("error_message" => "Unable to send Email");
					} else {
						$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => $GLOBALS['gClientName']);
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
		if (!$this->iWebServiceClient) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unable to connect to web service";
			return false;
		}

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

		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));

		$returnValues = array();

# Submit the orders

		$createOrderRequest = new stdClass();
		$createOrderRequest->Token = self::DAVIDSONS_SECURITY_TOKEN;
		$createOrderRequest->ConsumerName = $dealerName;
		$createOrderRequest->ConsumerPhone = $this->iLocationContactRow['phone_number'];
		$createOrderRequest->DealerCustomerNumber = $this->iLocationCredentialRow['customer_number'];
		$createOrderRequest->ReferenceNumber = $orderPrefix . $distributorOrderId;

		$itemArray = array();
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$itemRequest = new stdClass();
			$itemRequest->ItemNumber = $thisItemRow['distributor_product_code'];
			$itemRequest->Quantity = $thisItemRow['quantity'];
			$itemArray[] = $itemRequest;
		}
		$createOrderRequest->Items = $itemArray;
		$createOrderRequest->Note = $parameters['notes'];
		$createOrderRequest->UseExpeditedShipping = "";
		$createOrder = new stdClass();
		$createOrder->request = $createOrderRequest;
		try {
			$response = $this->iWebServiceClient->CreateOrder($createOrder);
		} catch (Exception $e) {
			$this->iErrorMessage = "Web service error: " . $e->getMessage();
			$response = false;
		}
		if (!$response) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Web service error.";
		} elseif ($response->CreateOrderResult->ResponseCode != 0) {
			$this->iErrorMessage = $response->CreateOrderResult->ResponseCode . ": " . $response->CreateOrderResult->ResponseText;
		}
		if (!empty($this->iErrorMessage)) {
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
			}
		} else {
			// orders can be fulfilled as multiple remote orders
			$orderResultArray = array();
			if (is_array($response->CreateOrderResult->Orders->Order)) {
				$orderResultArray = $response->CreateOrderResult->Orders->Order;
			} else {
				$orderResultArray[] = $response->CreateOrderResult->Orders->Order;
			}
			$productIds = array();
			$orderNumber = "";
			foreach ($orderResultArray as $orderResult) {
				$orderNumber .= (empty($orderNumber) ? "" : ",") . $orderResult->OrderNumber;
				if (is_array($orderResult->OrderLineItems->OrderLineItem)) {
					$lineItemsArray = $orderResult->OrderLineItems->OrderLineItem;
				} else {
					$lineItemsArray = $orderResult->OrderLineItems;
				}
				foreach ($dealerOrderItemRows as $thisItemRow) {
					foreach ($lineItemsArray as $thisLineItem) {
						if ($thisItemRow['distributor_product_code'] != $thisLineItem->ItemNumber) {
							continue;
						}
						if ($thisLineItem->Quantity > 0) {
							executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
								$distributorOrderId, $thisItemRow['product_id'], $thisLineItem->Quantity, $thisItemRow['notes']);
							$productIds[] = $thisItemRow['product_id'];
						} else {
							$failedItems[$thisItemRow['product_id']] = array("product_id" => $thisItemRow['product_id'], "quantity" => $thisItemRow['quantity'] - $thisLineItem['ship_quantity']);
						}
					}
				}
			}
			foreach ($dealerOrderItemRows as $thisItemRow) {
				if (!in_array($thisItemRow['product_id'], $productIds)) {
					$failedItems[$thisItemRow['product_id']] = array("product_id" => $thisItemRow['product_id'], "quantity" => $thisItemRow['quantity']);
				}
			}
			executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $orderNumber, $distributorOrderId);
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $orderNumber, "product_ids" => $productIds);
		}

		if (!empty($this->iErrorMessage)) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return false;
		} else {
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $distributorOrderId);
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		if (!$this->iWebServiceClient) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Unable to connect to web service";
			return false;
		}
		$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $orderShipmentId, "tracking_identifier is null");
		if (empty($orderShipmentRow['remote_order_id'])) {
			return false;
		}
		$remoteOrderRow = getRowFromId("remote_orders", "remote_order_id", $orderShipmentRow['remote_order_id']);
		if (empty($remoteOrderRow['order_number'])) {
			return false;
		}
		$orderNumber = $remoteOrderRow['order_number'];

		$getOrderNoRequest = new stdClass();
		$getOrderNoRequest->Token = self::DAVIDSONS_SECURITY_TOKEN;
		$getOrderNoRequest->OrderNo = $orderNumber;
		$getOrderNoRequest->CustomerNo = $this->iLocationCredentialRow['customer_number'];
		$getOrder = new stdClass();
		$getOrder->request = $getOrderNoRequest;
		try {
			$response = $this->iWebServiceClient->GetOrder($getOrder);
		} catch (Exception $e) {
			$this->iErrorMessage = "Web service error: " . $e->getMessage();
			$response = false;
		}
		if (!$response) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Web service error.";
		} elseif ($response->ResponseCode != 0) {
			$this->iErrorMessage = $response->ResponseCode . ": " . $response->ResponseText;
		}
		if (empty($this->iErrorMessage)) {
			$orderData = $response->GetOrderResult->OrdHeaders->OrdHeader;
			$shippingCarrierId = null;
			$shippingCarriers = array("UPS" => "UPS", "FEDEX" => "FEDEX", "FDX" => "FEDEX", "USPS" => "USPS", "FDX_GRND" => "FDX_GRND");
			foreach ($shippingCarriers as $thisCode => $thisCarrier) {
				if (stristr($orderData->ShipVia, $thisCode) !== false) {
					$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", $thisCarrier);
					break;
				}
			}
			if (!empty($orderData->TrackingNumber)) {
				$trackingNumber = trim(str_replace("Track #:", "", $orderData->TrackingNumber));
				$resultSet = executeQuery("update order_shipments set tracking_identifier = ?,shipping_carrier_id = ?,carrier_description = ? where order_shipment_id = ?",
					$trackingNumber, $shippingCarrierId, $orderData->ShipVia, $orderShipmentId);
				if ($resultSet['affected_rows'] > 0) {
					Order::sendTrackingEmail($orderShipmentId);
					executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
						$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $trackingNumber,
						"Tracking number added by " . $this->iProductDistributorRow['description']);
					return array($orderShipmentId);
				}
			}
		}
		return array();
	}

}
