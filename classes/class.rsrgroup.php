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

class RSRGroup extends ProductDistributor {

	private $iConnection;
	private $iFieldNames;
	private $iFieldTranslations = array("upc_code" => "upc_code", "product_code" => "rsr_stock_number", "description" => "product_description", "alternate_description" => "XXXXXX",
		"detailed_description" => "expanded_product_description", "manufacturer_code" => "manufacturer_id", "model" => "model", "manufacturer_sku" => "manufacturer_part_number", "manufacturer_advertised_price" => "retail_map",
		"width" => "shipping_width", "length" => "shipping_length", "height" => "shipping_height", "weight" => "product_weight", "base_cost" => "rsr_regular_price", "list_price" => "retail_price");
	public $iTrackingAlreadyRun = false;
	private $iLogging;

	function __construct($locationId) {
		$this->iProductDistributorCode = "RSR_GROUP";
		$this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_RSR"));
		$this->iFieldNames = array("rsr_stock_number", "upc_code", "product_description", "department_number", "manufacturer_id", "retail_price",
			"rsr_regular_price", "product_weight", "inventory_quantity", "model", "full_manufacturer_name", "manufacturer_part_number",
			"allocated_closeout_deleted", "expanded_product_description", "image_name", "AK", "AL", "AR", "AZ", "CA", "CO", "CT", "DC", "DE", "FL", "GA", "HI",
			"IA", "ID", "IL", "IN", "KS", "KY", "LA", "MA", "MD", "ME", "MI", "MN", "MO", "MS", "MT", "NC", "ND", "NE", "NH", "NJ", "NM", "NV", "NY", "OH", "OK", "OR", "PH", "RI",
			"SC", "SD", "TN", "TX", "UT", "VA", "VT", "WA", "WI", "WV", "WY", "ground_shipments_only", "adult_signature_required",
			"blocked_from_drop_ship", "date_entered", "retail_map", "image_disclaimer", "shipping_length", "shipping_width", "shipping_height", "prop_65", "vendor_approval_required");
		parent::__construct($locationId);
		$this->getFirearmsProductTags();
	}

	function testCredentials() {
		$fieldLabels = array();
		$requiredCustomFieldCodeArray = array("DROPSHIP_ORDER_USERNAME", "DROPSHIP_ORDER_PASSWORD");
        $accessoryOnlyAccount = !empty(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ACCESSORY_ONLY_ACCOUNT", "PRODUCT_DISTRIBUTORS"));
        if(!$accessoryOnlyAccount) {
            $requiredCustomFieldCodeArray[] = "FFL_LICENSE_NUMBER";
        }
        foreach ($requiredCustomFieldCodeArray as $customFieldCode) {
			$this->iLocationCredentialRow["custom_field_" . strtolower($customFieldCode)] =
				CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], $customFieldCode, "PRODUCT_DISTRIBUTORS");
			$fieldLabels["custom_field_" . strtolower($customFieldCode)] = getFieldFromId("form_label", "custom_fields", "custom_field_code", $customFieldCode);
		}
		$ignoreFields = array("distributor_source", "date_last_run", "last_inventory_update", "inactive", "version");
		$labelSet = executeQuery("select * from product_distributor_field_labels where product_distributor_id = (select product_distributor_id from product_distributors where product_distributor_code = 'RSR')");
		while ($labelRow = getNextRow($labelSet)) {
			$fieldLabels[$labelRow['column_name']] = array("form_label" => $labelRow['form_label'], "not_null" => $labelRow['not_null']);
		}
		foreach ($this->iLocationCredentialRow as $key => $value) {
			if (!in_array($key, $ignoreFields) && empty($value)) {
				$label = $fieldLabels[$key] ?: $key;
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . $label . " is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if (!$this->connect()) {
			$this->iErrorMessage = "Dealer account credentials are incorrect";
			return false;
		}
		if (!$this->connect(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"),
			CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"))) {
			$this->iErrorMessage = "Dropship account credentials are incorrect";
			return false;
		}
		$directoryWorks = false;
		$directoriesToTry = array(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS"), "keydealer", "ftpdownloads");
		foreach ($directoriesToTry as $inventoryDirectory) {
			if (ftp_chdir($this->iConnection, $inventoryDirectory)) {
				$directoryWorks = true;
				CustomField::setCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY",
					$inventoryDirectory, "PRODUCT_DISTRIBUTORS");
			}
		}
		if (!$directoryWorks) {
			$this->iErrorMessage = "Dropship Credentials do not have access to inventory directory";
			return false;
		}
		return true;
	}

	function connect($username = "", $password = "") {
		if (empty($username) || empty($password)) {
			$username = $this->iLocationCredentialRow['user_name'];
			$password = $this->iLocationCredentialRow['password'];
		}
		$this->iConnection = ftp_ssl_connect("ftps.rsrgroup.com", 2222, 600);
		if (!$this->iConnection) {
			return false;
		}
		try {
			if (!ftp_login($this->iConnection, $username, $password)) {
				$this->iErrorMessage = "Invalid Login";
				return false;
			}
			ftp_pasv($this->iConnection, true);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	function syncProducts($parameters = array()) {
		$dropShipUsername = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		if (!$this->connect($dropShipUsername, CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"))) {
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

		// Get vendor approval file
		$approvalFilenameRemote = "IM-VB-C" . $dropShipUsername . ".csv";
		$approvalFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".csv";
		$vendorApprovalArray = array();
		if (ftp_get($this->iConnection, $approvalFilename, $approvalFilenameRemote, FTP_ASCII)) {
			$openFile = fopen($approvalFilename, "r");
			while ($fields = fgetcsv($openFile)) {
				if (count($fields) == 1) {  // If no data, line will be non-CSV message
					break;
				}
				$vendorApprovalArray[$fields[0]] = $fields[1];
			}
			fclose($openFile);
			unlink($approvalFilename);
		}

		$inventoryDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (empty($inventoryDirectory)) {
			$inventoryDirectory = "ftpdownloads";
		}
		if (!ftp_chdir($this->iConnection, $inventoryDirectory)) {
			$this->iErrorMessage = $inventoryDirectory . " directory does not exist";
			return false;
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		$productAttributes = array();
		if (ftp_get($this->iConnection, $catalogFilename, "attributes-all.txt", FTP_ASCII)) {
			$attributesText = file_get_contents($catalogFilename);
			$attributeLines = getContentLines($attributesText);
			foreach ($attributeLines as $thisAttribute) {
				$attributeFields = explode(";", $thisAttribute);
				$rsrStockNumber = $attributeFields[0];
				$productAttributes[$rsrStockNumber] = $attributeFields;
			}
		}
		unlink($catalogFilename);

		$states = getStateArray(true);

		if ($inventoryDirectory == "keydealer") {
			$inventoryFilename = "rsrinventory-keydlr-new.txt";
		} else {
			$accessoryOnlyAccount = !empty(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ACCESSORY_ONLY_ACCOUNT", "PRODUCT_DISTRIBUTORS"));
			$inventoryFilename = ($accessoryOnlyAccount ? "fulfillment-inv-new.txt" : "rsrinventory-new.txt");
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, $inventoryFilename, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file ' " . $inventoryFilename . "' cannot be downloaded";
			return false;
		}

		$insertCount = 0;

		$fflCategoryCodes = array("1", "2", "3", "5");
		$class3CategoryCodes = array("6");
		self::loadValues('distributor_product_codes');
		$productIdsProcessed = array();

		$processCount = 0;
		$foundCount = 0;
		$updatedCount = 0;
		$noUpc = 0;
		$imageCount = 0;
		$duplicateProductCount = 0;
		$badImageCount = 0;

		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);
		foreach ($catalogLines as $productLine) {

			$productFields = explode(";", $productLine);
			$thisProductInfo = array();
			foreach ($this->iFieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($productFields[$index]);
			}

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

			if (self::$iCorewareShootingSports) {
				$newStates = array();
				foreach ($states as $thisState => $stateName) {
					if (!empty($thisProductInfo[$thisState])) {
						$newStates[] = $thisState;
					}
				}
				$this->syncStates($productId, $newStates);
			} else {
				$this->syncStates($productId, $corewareProductData['restricted_states']);
			}

# DO stuff for both new products and existing products

			// 3 situations:
			// 1. Product is blocked for dropship for all (blocked_from_drop_ship == "Y", vendor_approval_required == "") = block
			// 2. Product is blocked for dropship but allowed for approved dealers (blocked_from_drop_ship == "Y", vendor_approval_required == "F" || "Y")
			//    a. Dealer is approved for that product (A) = allow
			//    b. Dealer is not approved for that product (B or no value) = block
			// 3. Product is not blocked for dropship but vendor approval required - same as #2
			$blockDropship = false;
			if (!empty($thisProductInfo['vendor_approval_required']) && $thisProductInfo['vendor_approval_required'] != "N") {
				if (array_key_exists($thisProductInfo['rsr_stock_number'], $vendorApprovalArray)) {
					$blockDropship = $vendorApprovalArray[$thisProductInfo['rsr_stock_number']] != "A";
				} else {
					$blockDropship = true;
				}
			} elseif (!empty($thisProductInfo['blocked_from_drop_ship']) && $thisProductInfo['blocked_from_drop_ship'] != "N") {
				$blockDropship = true;
			}
			$productDistributorDropshipProhibitionId = getFieldFromId("product_distributor_dropship_prohibition_id", "product_distributor_dropship_prohibitions", "product_id", $productId,
				"product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
			if ($blockDropship) {
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
				if (in_array(strtoupper($thisProductInfo['department_number']), $fflCategoryCodes)) {
					$corewareProductData['serializable'] = 1;
				}
				if (in_array(strtoupper($thisProductInfo['department_number']), $class3CategoryCodes)) {
					$corewareProductData['serializable'] = 1;
				}
			}
			if (in_array(strtoupper($thisProductInfo['department_number']), $fflCategoryCodes)) {
				$this->addProductTag($productId, $this->iFFLRequiredProductTagId);
			}
			if (in_array(strtoupper($thisProductInfo['department_number']), $class3CategoryCodes)) {
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

# check for high res image
					$imageId = $imageContents = "";
					$imageFilename = $thisProductInfo['rsr_stock_number'] . "_1_HR.jpg";
					$directory = "/ftp_highres_images/rsr_number/" . (is_numeric(substr($thisProductInfo['rsr_stock_number'], 0, 1)) ? "#" : strtolower(substr($thisProductInfo['rsr_stock_number'], 0, 1)));
					if (ftp_chdir($this->iConnection, $directory)) {
						$fileSize = ftp_size($this->iConnection, $imageFilename);
						if ($fileSize > 0) {
							$localImageFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".jpg";
							if (ftp_get($this->iConnection, $localImageFilename, $imageFilename, FTP_BINARY)) {
								$imageContents = file_get_contents($localImageFilename);
								unlink($localImageFilename);
							}
						}
					}

# if no high res image, get the normal image
					if (empty($imageContents)) {
						$imageFilename = $thisProductInfo['rsr_stock_number'] . "_1.jpg";
						$directory = "/ftp_images/rsr_number/" . (is_numeric(substr($thisProductInfo['rsr_stock_number'], 0, 1)) ? "#" : strtolower(substr($thisProductInfo['rsr_stock_number'], 0, 1)));
						if (ftp_chdir($this->iConnection, $directory)) {
							$fileSize = ftp_size($this->iConnection, $imageFilename);
							if ($fileSize > 0) {
								$localImageFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".jpg";
								if (ftp_get($this->iConnection, $localImageFilename, $imageFilename, FTP_BINARY)) {
									$imageContents = file_get_contents($localImageFilename);
									unlink($localImageFilename);
								}
							}
						}
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $imageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "RSR_IMAGE"));
						if (!empty($imageId)) {
							SimpleImage::reduceImageSize($imageId, array("compression" => 60, "max_image_dimension" => 1600, "convert" => true));
						}
					}
					$primaryImageId = "";
					if (!empty($imageId)) {
						$this->updateProductField($productId, "image_id", $imageId);
						$primaryImageId = $imageId;
						$imageCount++;
					}

					$productImageId = getFieldFromId("product_image_id", "product_images", "product_id", $productId);
					if (empty($productImageId) || $this->isTopProductDistributor($productId)) {
						$directory = "/ftp_highres_images/rsr_number/" . (is_numeric(substr($thisProductInfo['rsr_stock_number'], 0, 1)) ? "#" : strtolower(substr($thisProductInfo['rsr_stock_number'], 0, 1)));
						if (ftp_chdir($this->iConnection, $directory)) {
							for ($x = 2; $x < 9; $x++) {
								$imageFilename = $thisProductInfo['rsr_stock_number'] . "_" . $x . "_HR.jpg";
								$fileSize = ftp_size($this->iConnection, $imageFilename);
								if ($fileSize > 0) {
									$localImageFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".jpg";
									if (!ftp_get($this->iConnection, $localImageFilename, $imageFilename, FTP_BINARY)) {
										break;
									}
									$imageContents = file_get_contents($localImageFilename);
									unlink($localImageFilename);
									if (!empty($imageContents)) {
										$imageId = createImage(array("extension" => "jpg", "file_content" => $imageContents, "name" => $imageFilename, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description']));
										SimpleImage::reduceImageSize($imageId, array("compression" => 60, "max_image_dimension" => 1600, "convert" => true));
										executeQuery("insert into product_images (product_id,image_id,description) values (?,?,'Secondary Image')", $productId, $imageId);
										$imageCount++;
									}
								} else {
									break;
								}
							}
						}
					}
					ProductCatalog::createProductImageFiles($productId, $primaryImageId);
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
			if (!empty($thisProductInfo[$this->iFieldTranslations['weight']])) {
				$thisProductInfo[$this->iFieldTranslations['weight']] = round($thisProductInfo[$this->iFieldTranslations['weight']] / 16, 2);
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

			if (self::$iCorewareShootingSports && !$productCategoryAdded && !empty($thisProductInfo['department_number'])) {
				$categoryCode = $thisProductInfo['department_number'];
				$this->addProductCategories($productId, array($categoryCode));
			}

			if (self::$iCorewareShootingSports) {
				$productFacetValues = array();
				$attributes = $productAttributes[$thisProductInfo['rsr_stock_number']];
				if (!empty($attributes) && is_array($attributes)) {
					foreach ($attributes as $facetCode => $thisAttribute) {
						if ($facetCode < 2) {
							continue;
						}
						if (!empty($thisAttribute)) {
							$productFacetValues[] = array("product_facet_code" => $facetCode, "facet_value" => $thisAttribute);
						}
					}
				}
				$this->addProductFacets($productId, $productFacetValues);
			}
		}
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		if (self::$iCorewareShootingSports) {
			executeQuery("delete from client_preferences where client_id = ? and preference_id = " .
				"(select preference_id from preferences where preference_code = 'RESET_CATALOG_IMAGES_FROM_RSR')", $GLOBALS['gClientId']);
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

		$dropShipUsername = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$dropShipPassword = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		if (!$this->connect($dropShipUsername, $dropShipPassword)) {
			return false;
		}

		$inventoryDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (empty($inventoryDirectory)) {
			$inventoryDirectory = "ftpdownloads";
		}
		if (!ftp_chdir($this->iConnection, $inventoryDirectory)) {
			$this->iErrorMessage = "Directory '" . $inventoryDirectory . "' does not exist";
			return false;
		}

		$inventoryArray = array();
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, "IM-QTY-CSV.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Inventory file cannot be downloaded";
			return false;
		}
		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);
		foreach ($catalogLines as $productLine) {
			$productFields = explode(",", $productLine);
			$inventoryArray[$productFields[0]] = ($productFields[1] - 0);
		}
		unlink($catalogFilename);

		if ($inventoryDirectory == "keydealer") {
			$inventoryFilename = "rsrinventory-keydlr-new.txt";
		} else {
			$accessoryOnlyAccount = !empty(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ACCESSORY_ONLY_ACCOUNT", "PRODUCT_DISTRIBUTORS"));
			$inventoryFilename = ($accessoryOnlyAccount ? "fulfillment-inv-new.txt" : "rsrinventory-new.txt");
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, $inventoryFilename, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file ' " . $inventoryFilename . "' cannot be downloaded";
			return false;
		}

		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);

		foreach ($catalogLines as $productLine) {
			$productFields = explode(";", $productLine);
			$thisProductInfo = array();
			foreach ($this->iFieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($productFields[$index]);
			}
			if ($thisProductInfo['rsr_stock_number'] != $distributorProductCode) {
				continue;
			}

			if (array_key_exists($thisProductInfo['rsr_stock_number'], $inventoryArray)) {
				$thisProductInfo['inventory_quantity'] = $inventoryArray[$thisProductInfo['rsr_stock_number']];
			}
			$quantityAvailable = $this->adjustQuantity($productId, $thisProductInfo['inventory_quantity'], $thisProductInfo['rsr_regular_price']);
			$cost = $thisProductInfo['rsr_regular_price'];
			break;
		}
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		if ($quantityAvailable === false) {
			return false;
		}
		return array("quantity" => $quantityAvailable, "cost" => $cost);
	}

	function syncInventory($parameters = array()) {
		$dropShipUsername = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$dropShipPassword = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
		if (!$this->connect($dropShipUsername, $dropShipPassword)) {
			return false;
		}

		$inventoryDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (empty($inventoryDirectory)) {
			$inventoryDirectory = "ftpdownloads";
		}
		if (!ftp_chdir($this->iConnection, $inventoryDirectory)) {
			$this->iErrorMessage = "Directory '" . $inventoryDirectory . "' does not exist";
			return false;
		}

		$inventoryArray = array();
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, "IM-QTY-CSV.csv", FTP_ASCII)) {
			$this->iErrorMessage = "Inventory file cannot be downloaded";
			return false;
		}
		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);
		foreach ($catalogLines as $productLine) {
			$productFields = explode(",", $productLine);
			$inventoryArray[$productFields[0]] = ($productFields[1] - 0);
		}
		unlink($catalogFilename);

		if ($inventoryDirectory == "keydealer") {
			$inventoryFilename = "rsrinventory-keydlr-new.txt";
		} else {
			$accessoryOnlyAccount = !empty(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ACCESSORY_ONLY_ACCOUNT", "PRODUCT_DISTRIBUTORS"));
			$inventoryFilename = ($accessoryOnlyAccount ? "fulfillment-inv-new.txt" : "rsrinventory-new.txt");
		}
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, $inventoryFilename, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file ' " . $inventoryFilename . "' cannot be downloaded";
			return false;
		}

		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);

		$inventoryUpdateArray = array();
		foreach ($catalogLines as $productLine) {
			$productFields = explode(";", $productLine);
			$thisProductInfo = array();
			foreach ($this->iFieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($productFields[$index]);
			}
			if (!array_key_exists($thisProductInfo['rsr_stock_number'], $inventoryArray)) {
				continue;
			}
			$thisProductInfo['inventory_quantity'] = $inventoryArray[$thisProductInfo['rsr_stock_number']];

			$thisInventoryUpdate = array("product_code" => $thisProductInfo['rsr_stock_number'],
				"quantity" => $thisProductInfo['inventory_quantity'],
				"cost" => $thisProductInfo['rsr_regular_price']);
			$inventoryUpdateArray[] = $thisInventoryUpdate;
		}
		ftp_close($this->iConnection);
		unlink($catalogFilename);

		$resultArray = (empty($parameters['all_clients']) ? $this->processInventoryUpdates($inventoryUpdateArray) : $this->processInventoryQuantities($inventoryUpdateArray));
		return $resultArray['processed'] . " product quantities processed, " . (array_key_exists("same", $resultArray) ? $resultArray['same'] . " products unchanged, " : "") . (array_key_exists("location_skip", $resultArray) ? $resultArray['location_skip'] . " locations skipped, " : "") . $resultArray['not_found'] . " not found.";
	}

	function getManufacturers($parameters = array()) {
		if (!$this->connect()) {
			return false;
		}

		$inventoryDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (empty($inventoryDirectory)) {
			$inventoryDirectory = "ftpdownloads";
		}
		if (!ftp_chdir($this->iConnection, $inventoryDirectory)) {
			$this->iErrorMessage = $inventoryDirectory . " directory does not exist";
			return false;
		}
		$inventoryFilename = ($inventoryDirectory == "ftpdownloads" ? "rsrinventory-new.txt" : "rsrinventory-keydlr-new.txt");
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, $inventoryFilename, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file ' " . $inventoryFilename . "' cannot be downloaded";
			return false;
		}
		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);
		$productManufacturers = array();
		foreach ($catalogLines as $productLine) {
			$productFields = explode(";", $productLine);
			$thisProductInfo = array();
			foreach ($this->iFieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($productFields[$index]);
			}
			if (empty($thisProductInfo['full_manufacturer_name'])) {
				$thisProductInfo['full_manufacturer_name'] = $thisProductInfo['manufacturer_id'];
			}
			if (!empty($thisProductInfo['manufacturer_id']) &&
				(!array_key_exists($thisProductInfo['manufacturer_id'], $productManufacturers) || $productManufacturers[$thisProductInfo['manufacturer_id']] == $thisProductInfo['manufacturer_id'])) {
				$productManufacturers[$thisProductInfo['manufacturer_id']] = array("business_name" => $thisProductInfo['full_manufacturer_name']);
			}
		}

		uasort($productManufacturers, array($this, "sortManufacturers"));
		unlink($catalogFilename);
		return $productManufacturers;
	}

	function sortManufacturers($a, $b) {
		if ($a['business_name'] == $b['business_name']) {
			return 0;
		}
		return ($a['business_name'] > $b['business_name']) ? 1 : -1;
	}

	function getCategories($parameters = array()) {
		$productCategories = array("1" => array("description" => "Handguns"),
			"02" => array("description" => "Used Handguns"),
			"03" => array("description" => "Used Long Guns"),
			"04" => array("description" => "Tasers"),
			"05" => array("description" => "Long Guns"),
			"06" => array("description" => "NFA Products"),
			"07" => array("description" => "Black Powder"),
			"08" => array("description" => "Optics"),
			"09" => array("description" => "Optical Accessories"),
			"10" => array("description" => "Magazines"),
			"11" => array("description" => "Grips, Pads, Stocks, Bipods"),
			"12" => array("description" => "Soft Gun Cases, Packs, Bags"),
			"13" => array("description" => "Misc. Accessories"),
			"14" => array("description" => "Holsters & Pouches"),
			"15" => array("description" => "Reloading Equipment"),
			"16" => array("description" => "Black Powder Accessories"),
			"17" => array("description" => "Closeout Accessories"),
			"18" => array("description" => "Ammunition"),
			"19" => array("description" => "Survival & Camping Supplies"),
			"20" => array("description" => "Lights, Lasers, & Batteries"),
			"21" => array("description" => "Cleaning Equipment"),
			"22" => array("description" => "Airguns"),
			"23" => array("description" => "Knives & Tools"),
			"24" => array("description" => "High Capacity Magazines"),
			"25" => array("description" => "Safes & Security"),
			"26" => array("description" => "Safety & Protection"),
			"27" => array("description" => "Non-Lethal Defense"),
			"28" => array("description" => "Binoculars"),
			"29" => array("description" => "Spotting Scopes"),
			"30" => array("description" => "Sights"),
			"31" => array("description" => "Optical Accessories"),
			"32" => array("description" => "Barrels, Choke Tubes, & Muzzle Devices"),
			"33" => array("description" => "Clothing"),
			"34" => array("description" => "Parts"),
			"35" => array("description" => "Slings & Swivels"),
			"36" => array("description" => "Electronics"),
			"37" => array("description" => "Not Used"),
			"38" => array("description" => "Books, Software, & DVD's"),
			"39" => array("description" => "Targets"),
			"40" => array("description" => "Hard Gun Cases"),
			"41" => array("description" => "Upper Receivers & Conversion Kits"),
			"42" => array("description" => "SBR Barrels & Upper Receivers"),
			"43" => array("description" => "Upper Receivers & Conversion Kits - High Capacity"));

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
		if (!$this->connect()) {
			return false;
		}

		$inventoryDirectory = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FTP_DIRECTORY", "PRODUCT_DISTRIBUTORS");
		if (empty($inventoryDirectory)) {
			$inventoryDirectory = "ftpdownloads";
		}
		if (!ftp_chdir($this->iConnection, $inventoryDirectory)) {
			$this->iErrorMessage = $inventoryDirectory . " directory does not exist";
			return false;
		}
		$inventoryFilename = ($inventoryDirectory == "ftpdownloads" ? "rsrinventory-new.txt" : "rsrinventory-keydlr-new.txt");
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, $inventoryFilename, FTP_ASCII)) {
			$this->iErrorMessage = "Catalog file ' " . $inventoryFilename . "' cannot be downloaded";
			return false;
		}
		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);
		$productCategories = array();
		foreach ($catalogLines as $productLine) {
			$productFields = explode(";", $productLine);
			$thisProductInfo = array();
			foreach ($this->iFieldNames as $index => $fieldName) {
				$thisProductInfo[$fieldName] = trim($productFields[$index]);
			}
			if (!empty($thisProductInfo['department_number'])) {
				$productCategories[$thisProductInfo['rsr_stock_number']] = $thisProductInfo['department_number'];
			}
		}
		$productFacetNames = array("2" => "Accessories",
			"3" => "Action",
			"4" => "Type of Barrel",
			"5" => "Barrel Length",
			"6" => "Catalog Code",
			"7" => "Chamber",
			"8" => "Chokes",
			"9" => "Condition",
			"10" => "Capacity",
			"11" => "Description",
			"12" => "Dram",
			"13" => "Edge",
			"14" => "Firing casing",
			"15" => "Finish/Color",
			"16" => "Fit",
			"17" => "Secondary Fit",
			"18" => "Feet per second",
			"19" => "Frame/Material",
			"20" => "Caliber",
			"21" => "Secondary Caliber",
			"22" => "Grain Weight",
			"23" => "Grips/Stock",
			"24" => "Hand",
			"25" => "Manufacturer",
			"26" => "Manufacturer part #",
			"27" => "Manufacturer weight",
			"28" => "MOA",
			"29" => "Model",
			"30" => "Secondary Model",
			"31" => "New Stock #",
			"32" => "NSN",
			"33" => "Objective",
			"34" => "Ounce of shot",
			"35" => "Packaging",
			"36" => "Power",
			"37" => "Reticle",
			"38" => "Safety",
			"39" => "Sights",
			"40" => "Size",
			"41" => "Type",
			"42" => "Units per box",
			"43" => "Units per case",
			"44" => "Wt Characteristics",
			"45" => "Sub Category");
		unlink($catalogFilename);
		$catalogFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsr-" . getRandomString(6) . ".txt";
		if (!ftp_get($this->iConnection, $catalogFilename, "attributes-all.txt", FTP_ASCII)) {
			$this->iErrorMessage = "Attributes file cannot be downloaded";
			return false;
		}
		$fullCatalog = file_get_contents($catalogFilename);
		$catalogLines = getContentLines($fullCatalog);
		$productFacets = array();
		foreach ($catalogLines as $productLine) {
			$productFields = explode(";", $productLine);
			$rsrStockNumber = $productFields[0];
			$categoryCode = $productCategories[$rsrStockNumber];
			if (empty($categoryCode)) {
				continue;
			}
			if (!array_key_exists($categoryCode, $productFacets)) {
				$productFacets[$categoryCode] = array();
			}
			foreach ($productFacetNames as $facetCode => $facetDescription) {
				if (empty($productFields[$facetCode]) || array_key_exists($facetCode, $productFacets[$categoryCode])) {
					continue;
				}
				$productFacets[$categoryCode][$facetCode] = $facetDescription;
			}
		}
		unlink($catalogFilename);

		return $productFacets;
	}

	function placeOrder($orderId, $orderItems, $additionalParameters = array()) {
		$this->getFirearmsProductTags();
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
		$requireSignature = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "SIGNATURE_REQUIRED", "PRODUCT_DISTRIBUTORS");
		if (!$requireSignature && !empty($additionalParameters['shipment_signature_required'])) {
			$requireSignature = true;
		}
		$defaultShippingMethod = "Grnd";
		if ($additionalParameters['shipment_shipping_method'] == "twoday" || $additionalParameters['shipment_shipping_method'] == "nextday") {
			$defaultShippingMethod = "2day";
		}

		$orderParts = $this->splitOrder($orderId, $orderItems);
		if ($orderParts === false) {
			$this->iErrorMessage = "No items found to order";
			return false;
		}
		$customerOrderItemRows = $orderParts['customer_order_items'];
		$fflOrderItemRows = $orderParts['ffl_order_items'];
		$dealerOrderItemRows = $orderParts['dealer_order_items'];

		$fflFileContent = "";
		$dropshipUserName = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
		$dropshipUserName = str_pad($dropshipUserName, 5, "0", STR_PAD_LEFT);
		$customerNumber = $this->iLocationCredentialRow['customer_number'];
		$customerNumber = str_pad($customerNumber, 5, "0", STR_PAD_LEFT);
		$confirmationEmailAddress = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
		$emailConfirmationText = empty($confirmationEmailAddress) ? "N;" : "Y;" . $confirmationEmailAddress;

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

			$fflFileContent .= "FILEHEADER;00;" . $dropshipUserName . ";" . date("Ymd") . ";%sequence_number%\n";

			$fflFileContent .= $orderId . "-%remote_order_id%;10;" . $ordersRow['full_name'] . ";;" .
				$addressRow['address_1'] . ";" . $addressRow['address_2'] . ";" . $addressRow['city'] . ";" . $addressRow['state'] . ";" .
				substr($addressRow['postal_code'], 0, 5) . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $ordersRow['phone_number'])))) . ";" . $emailConfirmationText . ";;\n";

			$fflFileContent .= $orderId . "-%remote_order_id%;11;" . $fflRow['license_number'] . ";" . (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . ";" .
				substr($fflRow[$addressPrefix . 'postal_code'], 0, 5) . ";" . $ordersRow['full_name'] . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $ordersRow['phone_number'])))) . "\n";

			$totalQuantity = 0;
			$handgunsProductCategoryId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", "HANDGUNS");
			foreach ($fflOrderItemRows as $thisItemRow) {
				if (empty($handgunsProductCategoryId)) {
					$shippingMethod = "2Day";
				} else {
					$shippingMethod = $defaultShippingMethod;
					if (ProductCatalog::productIsInCategoryGroup($thisItemRow['product_id'], $handgunsProductCategoryId)) {
						$shippingMethod = "2Day";
					}
				}
				$fflFileContent .= $orderId . "-%remote_order_id%;20;" . $thisItemRow['distributor_product_code'] . ";" . number_format($thisItemRow['quantity'], 0, "", "") . ";FDX;" . $shippingMethod . "\n";
				$totalQuantity += $thisItemRow['quantity'];
			}

			$fflFileContent .= $orderId . "-%remote_order_id%;90;" . $totalQuantity . "\n";
			$fflFileContent .= "FILETRAILER;99;00001\n";
		}

		$customerFileContent = "";
		if (!empty($customerOrderItemRows)) {
			$customerFileContent .= "FILEHEADER;00;" . $dropshipUserName . ";" . date("Ymd") . ";%sequence_number%\n";
			$customerFileContent .= $orderId . "-%remote_order_id%;10;" . $ordersRow['full_name'] . ";;" .
				$addressRow['address_1'] . ";" . $addressRow['address_2'] . ";" . $addressRow['city'] . ";" . $addressRow['state'] . ";" .
				substr($addressRow['postal_code'], 0, 5) . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $ordersRow['phone_number'])))) . ";" . $emailConfirmationText . ";;" . (empty($requireSignature) ? "" : "A") . "\n";
			$totalQuantity = 0;
			foreach ($customerOrderItemRows as $thisItemRow) {
				$customerFileContent .= $orderId . "-%remote_order_id%;20;" . $thisItemRow['distributor_product_code'] . ";" . number_format($thisItemRow['quantity'], 0, "", "") . ";FDX;" . $defaultShippingMethod . "\n";
				$totalQuantity += $thisItemRow['quantity'];
			}

			$customerFileContent .= $orderId . "-%remote_order_id%;90;" . $totalQuantity . "\n";
			$customerFileContent .= "FILETRAILER;99;00001\n";
		}

		$dealerFileContent = "";
		if (!empty($dealerOrderItemRows)) {
			$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
			$phoneNumber = $this->iLocationContactRow['phone_number'];

			$dealerFileContent .= "FILEHEADER;00;" . $customerNumber . ";" . date("Ymd") . ";%sequence_number%\n";
			$dealerFileContent .= $orderId . "-%remote_order_id%;10;" . $dealerName . ";" . $ordersRow['full_name'] . ";" .
				$this->iLocationContactRow['address_1'] . ";" . $this->iLocationContactRow['address_2'] . ";" . $this->iLocationContactRow['city'] . ";" . $this->iLocationContactRow['state'] . ";" .
				substr($this->iLocationContactRow['postal_code'], 0, 5) . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $phoneNumber)))) . ";" . $emailConfirmationText . ";;\n";
			$dealerFileContent .= $orderId . "-%remote_order_id%;11;" . CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_LICENSE_NUMBER", "PRODUCT_DISTRIBUTORS") . ";" . $dealerName . ";" .
				substr($this->iLocationContactRow['postal_code'], 0, 5) . ";" . $ordersRow['full_name'] . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $ordersRow['phone_number'])))) . "\n";

			$totalQuantity = 0;
			foreach ($dealerOrderItemRows as $thisItemRow) {
				$dealerFileContent .= $orderId . "-%remote_order_id%;20;" . $thisItemRow['distributor_product_code'] . ";" . number_format($thisItemRow['quantity'], 0, "", "") . ";FDX;" . $defaultShippingMethod . "\n";
				$totalQuantity += $thisItemRow['quantity'];
			}

			$dealerFileContent .= $orderId . "-%remote_order_id%;90;" . $totalQuantity . "\n";
			$dealerFileContent .= "FILETRAILER;99;00001\n";

		}

		$returnValues = array();

# Submit the orders

		if (!empty($fflFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			$fflFileContent = str_replace("%remote_order_id%", $remoteOrderId, $fflFileContent);
			foreach ($fflOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}
			$sequenceNumber = str_pad(getSequenceNumber("RSR_ORDER", 10000), 4, "0", STR_PAD_LEFT);
			$fflFileContent = str_replace("%sequence_number%", $sequenceNumber, $fflFileContent);
			executeQuery("update remote_orders set order_number = ?,notes = ? where remote_order_id = ?", $remoteOrderId, $fflFileContent, $remoteOrderId);
			if ($this->iLogging) {
				addDebugLog(sprintf("RSR: uploading ffl order file %s to %s, content: %s", "EORD-" . $dropshipUserName . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt", "eo/incoming",
					$fflFileContent));
			}
			$uploadResponse = ftpFilePutContents("ftps.rsrgroup.com", CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"),
				CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"),
				"eo/incoming", "EORD-" . $dropshipUserName . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt", $fflFileContent, true,2222);
			if ($uploadResponse !== true) {
				$this->iErrorMessage = $uploadResponse;
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['ffl'] = array("error_message" => $uploadResponse);
				if ($this->iLogging) {
					addDebugLog(sprintf("RSR: error uploading ffl order file: '%s'", $uploadResponse));
				}
			} else {
				$returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));
			}

			if (!empty($fflRow['file_id'])) {
				$osFilename = getFieldFromId("os_filename", "files", "file_id", $fflRow['file_id']);
				$extension = getFieldFromId("extension", "files", "file_id", $fflRow['file_id']);
				if (empty($osFilename)) {
					$fflLicenseFileContent = getFieldFromId("file_content", "files", "file_id", $fflRow['file_id']);
				} else {
					$fflLicenseFileContent = getExternalFileContents($osFilename);
				}
				if (!empty($fflLicenseFileContent)) {
					if ($this->iLogging) {
						addDebugLog(sprintf("RSR: uploading FFL license file %s, file length: %s",
							"FFL-" . $customerNumber . "-" . substr($fflRow['license_number'], -5) . "." . $extension, strlen($fflLicenseFileContent)));
					}
					$uploadResponse = ftpFilePutContents("ftps.rsrgroup.com", CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"),
						CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"),
						"", "FFL-" . $customerNumber . "-" . substr($fflRow['license_number'], -5) . "." . $extension, $fflLicenseFileContent, true,2222);
					if ($uploadResponse !== true && $this->iLogging) {
						addDebugLog(sprintf("RSR: error uploading FFL license file: '%s'", $uploadResponse));
					}
				}
			}
		}

		if (!empty($customerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			$customerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $customerFileContent);
			foreach ($customerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}

			$sequenceNumber = str_pad(getSequenceNumber("RSR_ORDER", 10000), 4, "0", STR_PAD_LEFT);
			$customerFileContent = str_replace("%sequence_number%", $sequenceNumber, $customerFileContent);
			executeQuery("update remote_orders set order_number = ?,notes = ? where remote_order_id = ?", $remoteOrderId, $customerFileContent, $remoteOrderId);
			if ($this->iLogging) {
				addDebugLog(sprintf("RSR: uploading customer order file %s to %s, content: %s", "EORD-" . $dropshipUserName . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt",
					"eo/incoming", $customerFileContent));
			}
			$uploadResponse = ftpFilePutContents("ftps.rsrgroup.com", CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"),
				CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"),
				"eo/incoming", "EORD-" . $dropshipUserName . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt", $customerFileContent, true,2222);

			if ($uploadResponse !== true) {
				$this->iErrorMessage = $uploadResponse;
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['customer'] = array("error_message" => $uploadResponse);
				if ($this->iLogging) {
					addDebugLog(sprintf("RSR: error uploading customer order file: '%s'", $uploadResponse));
				}
			} else {
				$returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => $ordersRow['full_name']);
			}
		}

		if (!empty($dealerFileContent)) {
			$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
				$ordersRow['order_id'], "123");
			$remoteOrderId = $orderSet['insert_id'];
			$dealerFileContent = str_replace("%remote_order_id%", $remoteOrderId, $dealerFileContent);
			foreach ($dealerOrderItemRows as $thisOrderItemRow) {
				executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
					$remoteOrderId, $thisOrderItemRow['order_item_id'], $thisOrderItemRow['quantity']);
			}

			$sequenceNumber = str_pad(getSequenceNumber("RSR_ORDER", 10000), 4, "0", STR_PAD_LEFT);
			$dealerFileContent = str_replace("%sequence_number%", $sequenceNumber, $dealerFileContent);
			executeQuery("update remote_orders set order_number = ?,notes = ? where remote_order_id = ?", $remoteOrderId, $dealerFileContent, $remoteOrderId);
			if ($this->iLogging) {
				addDebugLog(sprintf("RSR: uploading dealer order file %s to %s, content: %s", "EORD-" . $customerNumber . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt",
					"eo/incoming", $dealerFileContent));
			}
			$uploadResponse = ftpFilePutContents("ftps.rsrgroup.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
				"eo/incoming", "EORD-" . $customerNumber . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt", $dealerFileContent, true, 2222);

			if ($uploadResponse !== true) {
				$this->iErrorMessage = $uploadResponse;
				executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
				executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
				$returnValues['dealer'] = array("error_message" => $uploadResponse);
				if ($this->iLogging) {
					addDebugLog(sprintf("RSR: error uploading dealer order file: '%s'", $uploadResponse));
				}
			} else {
				$returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $remoteOrderId, "ship_to" => $GLOBALS['gClientName']);
			}

			$dealerImageId = getFieldFromId("image_id", "images", "image_code", "DEALER_FFL");
			if (!empty($dealerImageId)) {
				$osFilename = getFieldFromId("os_filename", "images", "image_id", $dealerImageId);
				$extension = getFieldFromId("extension", "images", "image_id", $dealerImageId);
				if (empty($osFilename)) {
					$fflLicenseFileContent = getFieldFromId("file_content", "images", "image_id", $dealerImageId);
				} else {
					$fflLicenseFileContent = getExternalFileContents($osFilename);
				}
				if (!empty($fflLicenseFileContent)) {
					ftpFilePutContents("ftps.rsrgroup.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
						"", "FFL-" . $customerNumber . "-" . substr(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_LICENSE_NUMBER", "PRODUCT_DISTRIBUTORS"), -5) . "." . $extension,
                        $fflLicenseFileContent, true,2222);
				}
			}
		}
		return $returnValues;
	}

	function placeDistributorOrder($productArray, $parameters = array()) {
		$userId = $parameters['user_id'];
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$this->getFirearmsProductTags();
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

		$customerNumber = $this->iLocationCredentialRow['customer_number'];
		$customerNumber = str_pad($customerNumber, 5, "0", STR_PAD_LEFT);
		$confirmationEmailAddress = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "ORDER_EMAIL_ADDRESS", "PRODUCT_DISTRIBUTORS");
		$emailConfirmationText = empty($confirmationEmailAddress) ? "N;" : "Y;" . $confirmationEmailAddress;

		$dealerFileContent = "";
		$dealerName = getDisplayName($this->iLocationContactRow['contact_id'], array("use_company" => true));
		$phoneNumber = $this->iLocationContactRow['phone_number'];

		$dealerFileContent .= "FILEHEADER;00;" . $customerNumber . ";" . date("Ymd") . ";%sequence_number%\n";
		$dealerFileContent .= $orderPrefix . $distributorOrderId . ";10;" . $dealerName . ";" . $dealerName . ";" .
			$this->iLocationContactRow['address_1'] . ";" . $this->iLocationContactRow['address_2'] . ";" . $this->iLocationContactRow['city'] . ";" . $this->iLocationContactRow['state'] . ";" .
			substr($this->iLocationContactRow['postal_code'], 0, 5) . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $phoneNumber)))) . ";" . $emailConfirmationText . ";;\n";
		$dealerFileContent .= $orderPrefix . $distributorOrderId . ";11;" . CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_LICENSE_NUMBER", "PRODUCT_DISTRIBUTORS") . ";" . $dealerName . ";" .
			substr($this->iLocationContactRow['postal_code'], 0, 5) . ";" . $dealerName . ";" . str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $phoneNumber)))) . "\n";

		$totalQuantity = 0;
		foreach ($dealerOrderItemRows as $thisItemRow) {
			$dealerFileContent .= $orderPrefix . $distributorOrderId . ";20;" . $thisItemRow['distributor_product_code'] . ";" . number_format($thisItemRow['quantity'], 0, "", "") . ";FDX;Grnd\n";
			$totalQuantity += $thisItemRow['quantity'];
		}

		$dealerFileContent .= $orderPrefix . $distributorOrderId . ";90;" . $totalQuantity . "\n";
		$dealerFileContent .= "FILETRAILER;99;00001\n";

		$returnValues = array();

# Submit the orders

		executeQuery("update distributor_orders set order_number = ? where distributor_order_id = ?", $distributorOrderId, $distributorOrderId);
		foreach ($dealerOrderItemRows as $thisOrderItemRow) {
			executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
				$distributorOrderId, $thisOrderItemRow['product_id'], $thisOrderItemRow['quantity'], $thisOrderItemRow['notes']);
		}

		$sequenceNumber = str_pad(getSequenceNumber("RSR_ORDER", 10000), 4, "0", STR_PAD_LEFT);
		$dealerFileContent = str_replace("%sequence_number%", $sequenceNumber, $dealerFileContent);
		$uploadResponse = ftpFilePutContents("ftps.rsrgroup.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
			"eo/incoming", "EORD-" . $customerNumber . "-" . date("Ymd") . "-" . $sequenceNumber . ".txt", $dealerFileContent, true,2222);

		if ($uploadResponse !== true) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$this->iErrorMessage = "Unable to submit order";
			return true;
		} else {
			$returnValues['dealer'] = array("distributor_order_id" => $distributorOrderId, "order_number" => $distributorOrderId);
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}

		$dealerImageId = getFieldFromId("image_id", "images", "image_code", "DEALER_FFL");
		if (!empty($dealerImageId)) {
			$osFilename = getFieldFromId("os_filename", "images", "image_id", $dealerImageId);
			$extension = getFieldFromId("extension", "images", "image_id", $dealerImageId);
			if (empty($osFilename)) {
				$fflLicenseFileContent = getFieldFromId("file_content", "images", "image_id", $dealerImageId);
			} else {
				$fflLicenseFileContent = getExternalFileContents($osFilename);
			}
			if (!empty($fflLicenseFileContent)) {
				ftpFilePutContents("ftps.rsrgroup.com", $this->iLocationCredentialRow['user_name'], $this->iLocationCredentialRow['password'],
					"", "FFL-" . $customerNumber . "-" . substr(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_LICENSE_NUMBER", "PRODUCT_DISTRIBUTORS"), -5) . "." . $extension,
                    $fflLicenseFileContent, true,2222);
			}
		}

		return $returnValues;
	}

	function getOrderTrackingData($orderShipmentId) {
		if ($this->iTrackingAlreadyRun) {
			return false;
		}

		if (!$this->connect(CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS"),
			CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "DROPSHIP_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS"))) {
			return false;
		}
		$this->iTrackingAlreadyRun = true;

# Drop ship account

		if (!ftp_chdir($this->iConnection, "eo/outgoing")) {
			$this->iErrorMessage = "EO Outgoing directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		$eshipFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("ESHIP")) == "ESHIP") {
				$eshipFiles[] = $thisFile;
			}
		}
		$eerrFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("EERR")) == "EERR") {
				$eerrFiles[] = $thisFile;
			}
		}
		$econfFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("ECONF")) == "ECONF") {
				$econfFiles[] = $thisFile;
			}
		}
		$ependFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("EPEND")) == "EPEND") {
				$ependFiles[] = $thisFile;
			}
		}

		$shipmentErrorStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "SHIPMENT_ERROR", "inactive = 0");
		if (empty($shipmentErrorStatusId)) {
			$insertSet = executeQuery("insert ignore into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,'SHIPMENT_ERROR','Shipment Error','#FF0000',1)", $GLOBALS['gClientId']);
			$shipmentErrorStatusId = $insertSet['insert_id'];
		}
		$fcaUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
		$this->iTrackingAlreadyRun = true;
		$returnValues = array();
		foreach ($eshipFiles as $thisFile) {
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

			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] == "00") {
					continue;
				}
				if ($lineParts[1] == "60") {
					$orderParts = explode("-", $lineParts[0]);
					$orderId = $orderParts[0];
					$remoteOrderId = $orderParts[1];
					$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId, "remote_order_id = ? and tracking_identifier is null", $remoteOrderId);
					if (empty($orderShipmentId)) {
						continue;
					}
					$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($lineParts[8]));
					$insertSet = executeQuery("update order_shipments set shipping_charge = ?,shipping_carrier_id = ?,carrier_description = ?,tracking_identifier = ? where order_shipment_id = ?",
						($lineParts[6] + $lineParts[7]) / 100, $shippingCarrierId, $lineParts[8] . " " . $lineParts[9], $lineParts[3], $orderShipmentId);
					if ($insertSet['affected_rows'] > 0) {
						Order::sendTrackingEmail($orderShipmentId);
						executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
							$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $lineParts[3],
							"Tracking number added by " . $this->iProductDistributorRow['description']);
						$returnValues[] = $orderShipmentId;
					}
				}
			}
		}
		foreach ($econfFiles as $thisFile) {
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
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				$orderParts = explode("-", $lineParts[0]);
				$orderId = $orderParts[0];
				$remoteOrderId = $orderParts[1];
				if ($lineParts[1] == "30") {
					$orderNumber = $lineParts[2];
					// Make sure each file is only processed once.
					$existingOrderNumber = getFieldFromId("order_number", "remote_orders", "remote_order_id", $remoteOrderId);
					if ($existingOrderNumber == $orderNumber) {
						continue 2;
					}
					executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
				} elseif ($lineParts[1] == "40") {
					if ($lineParts[3] != $lineParts[4]) { // Quantity mismatch; order was partially filled
						$originalQuantity = (int)$lineParts[3];
						$orderedQuantity = (int)$lineParts[4];
						$productId = getFieldFromId("product_id", "distributor_product_codes", "product_code", $lineParts[2], "product_distributor_id = ? and client_id = ?",
							$this->iProductDistributorRow['product_distributor_id'], $GLOBALS['gClientId']);
						$orderItemId = getFieldFromId("order_item_id", "order_items", "product_id", $productId, "order_id = ?", $orderId);
						$orderShipmentItemRow = getRowFromId("order_shipment_items", "order_item_id", $orderItemId);
						if (empty($orderShipmentItemRow) || $orderShipmentItemRow['quantity'] == $orderedQuantity) {
							continue; // file was already processed
						}
						if ($orderedQuantity == "0") {
							executeQuery("delete from order_shipment_items where order_shipment_item_id = ?", $orderShipmentItemRow['order_shipment_item_id']);
							sendEmail(array("subject" => "RSR Order partially filled", "body" => "<p> Order ID# " . $orderId . " from RSR " .
								"was only partially filled. The item '" . $lineParts[2] . "' was out of stock. This item will need to be reordered from a different distributor. The contents of the RSR confirmation file are:</p>" .
								implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
						} else {
							executeQuery("update order_shipment_items set quantity = ? where order_shipment_item_id = ?", $orderedQuantity, $orderShipmentItemRow['order_shipment_item_id']);
							sendEmail(array("subject" => "RSR Order partially filled", "body" => "<p> Order ID# " . $orderId . " from RSR " .
								"was only partially filled. The quantity of " . $originalQuantity . "x '" . $lineParts[2] . "' was not available.  An order for "
								. $orderedQuantity . " was placed successfully. The missing quantity will need to be ordered from a different distributor. The contents of the RSR confirmation file are:</p>" .
								implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
						}
					}
				}
			}
		}
		foreach ($eerrFiles as $thisFile) {
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
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] != "10") {
					continue;
				}
				$orderParts = explode("-", $lineParts[0]);
				$orderId = getFieldFromId("order_id", "orders", "order_id", $orderParts[0], "date_completed is null");
				if (empty($orderId)) {
					$orderId = $distributorOrderId = getFieldFromId("distributor_order_id", "distributor_orders", "distributor_order_id", $orderId, "requires_attention = 0");
				}

				if (empty($orderId)) {
					continue;
				}
				$remoteOrderId = $orderParts[1];
				if (empty($remoteOrderId)) {
					if (!empty($distributorOrderId)) {
						executeQuery("update distributor_orders set requires_attention = 1 where distributor_order_id = ?", $distributorOrderId);
						sendEmail(array("subject" => "RSR Order failed", "body" => "<p>Distributor Order ID# " . $distributorOrderId . " from RSR " .
							"failed. This order will need to be replaced. The contents of the RSR error log (which might give some idea why there shipment failed) are:</p>" .
							implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
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
						executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, implode("\n", $fileLines));
					}
					executeQuery("delete from order_shipment_items where order_shipment_id = ?", $orderShipmentId);
					executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
					executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					sendEmail(array("subject" => "RSR Order failed", "body" => "<p>The shipment for Order ID# " . $orderId . " from RSR " .
						"failed and has been removed. This shipment will need to be replaced. The contents of the RSR error log (which might give some idea why there shipment failed) are:</p>" .
						implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
				}
			}
		}
		foreach ($ependFiles as $thisFile) {
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
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] != "35") {
					continue;
				}
				$orderParts = explode("-", $lineParts[0]);
				$orderId = getFieldFromId("order_id", "orders", "order_id", $orderParts[0], "date_completed is null");
				if (empty($orderId)) {
					continue;
				}
				$remoteOrderId = $orderParts[1];
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
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, "From RSR by background process:\n" . implode("\n", $fileLines));
				}
				sendEmail(array("subject" => "RSR Order requires attention", "body" => "<p>The shipment for Order ID# " . $orderId . " from RSR " .
					"is in pending state because a valid FFL dealer was not found. Contact RSR to get this cleared up. The contents of the RSR pending log are:</p>" .
					implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
			}
		}
		ftp_close($this->iConnection);

# Main account

		if (!$this->connect()) {
			return false;
		}

		if (!ftp_chdir($this->iConnection, "eo/outgoing")) {
			$this->iErrorMessage = "EO Outgoing directory does not exist";
			return false;
		}
		$fileList = ftp_nlist($this->iConnection, ".");
		$eshipFiles = array();
		if (!is_array($fileList)) {
			$fileList = array();
		}
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("ESHIP")) == "ESHIP") {
				$eshipFiles[] = $thisFile;
			}
		}
		$eerrFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("EERR")) == "EERR") {
				$eerrFiles[] = $thisFile;
			}
		}
		$econfFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("ECONF")) == "ECONF") {
				$econfFiles[] = $thisFile;
			}
		}
		$ependFiles = array();
		foreach ($fileList as $thisFile) {
			if (substr($thisFile, 0, strlen("EPEND")) == "EPEND") {
				$ependFiles[] = $thisFile;
			}
		}

		$this->iTrackingAlreadyRun = true;
		foreach ($eshipFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] == "00") {
					continue;
				}
				if ($lineParts[1] == "60") {
					$orderParts = explode("-", $lineParts[0]);
					$orderId = $orderParts[0];
					$remoteOrderId = $orderParts[1];
					$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId, "remote_order_id = ? and tracking_identifier is null", $remoteOrderId);
					if (empty($orderShipmentId)) {
						continue;
					}
					$shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($lineParts[8]));
					$insertSet = executeQuery("update order_shipments set shipping_charge = ?,shipping_carrier_id = ?,carrier_description = ?,tracking_identifier = ? where order_shipment_id = ?",
						($lineParts[6] + $lineParts[7]) / 100, $shippingCarrierId, $lineParts[8] . " " . $lineParts[9], $lineParts[3], $orderShipmentId);
					if ($insertSet['affected_rows'] > 0) {
						Order::sendTrackingEmail($orderShipmentId);
						executeQuery("insert into change_log (client_id,user_id,table_name,primary_identifier,column_name,new_value, notes) values (?,?,?,?,?,?,?)",
							$GLOBALS['gClientId'], $GLOBALS['gUserId'], 'order_shipments', $orderShipmentId, 'tracking_identifier', $lineParts[3],
							"Tracking number added by " . $this->iProductDistributorRow['description']);
						$returnValues[] = $orderShipmentId;
					}
				}
			}
		}
		foreach ($econfFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] != "30") {
					continue;
				}
				$orderParts = explode("-", $lineParts[0]);
				$orderId = $orderParts[0];
				$remoteOrderId = $orderParts[1];
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_id", $orderId, "remote_order_id = ? and tracking_identifier is null", $remoteOrderId);
				$orderNumber = $lineParts[2];
				if (empty($orderShipmentId)) {
					continue;
				}
				executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
			}
		}
		foreach ($eerrFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] != "10") {
					continue;
				}
				$orderParts = explode("-", $lineParts[0]);
				$orderId = getFieldFromId("order_id", "orders", "order_id", $orderParts[0], "date_completed is null");
				if (empty($orderId)) {
					continue;
				}
				$remoteOrderId = $orderParts[1];
				if (empty($remoteOrderId)) {
					$distributorOrderId = getFieldFromId("distributor_order_id", "distributor_orders", "distributor_order_id", $orderId, "requires_attention = 0");
					if (!empty($distributorOrderId)) {
						executeQuery("update distributor_orders set requires_attention = 1 where distributor_order_id = ?", $distributorOrderId);
						sendEmail(array("subject" => "RSR Order failed", "body" => "<p>Distributor Order ID# " . $distributorOrderId . " from RSR " .
							"failed. This order will need to be replaced. The contents of the RSR error log (which might give some idea why there shipment failed) are:</p>" .
							implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
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
						executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, implode("\n", $fileLines));
					}
					executeQuery("delete from order_shipment_items where order_shipment_id = ?", $orderShipmentId);
					executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
					executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
					executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					sendEmail(array("subject" => "RSR Order failed", "body" => "<p>The shipment for Order ID# " . $orderId . " from RSR " .
						"failed and has been removed. This shipment will need to be replaced. The contents of the RSR error log (which might give some idea why there shipment failed) are:</p>" .
						implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
				}
			}
		}
		foreach ($ependFiles as $thisFile) {
			$tempFilename = $GLOBALS['gDocumentRoot'] . "/cache/rsrgroup-" . getRandomString(6) . ".txt";
			if (!ftp_get($this->iConnection, $tempFilename, $thisFile, FTP_ASCII)) {
				continue;
			}
			$fileLines = getContentLines(file_get_contents($tempFilename));
			unlink($tempFilename);
			foreach ($fileLines as $thisLine) {
				$lineParts = explode(";", $thisLine);
				if ($lineParts[1] != "35") {
					continue;
				}
				$orderParts = explode("-", $lineParts[0]);
				$orderId = getFieldFromId("order_id", "orders", "order_id", $orderParts[0], "date_completed is null");
				if (empty($orderId)) {
					continue;
				}
				$remoteOrderId = $orderParts[1];
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
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $fcaUserId, implode("\n", $fileLines));
				}
				sendEmail(array("subject" => "RSR Order requires attention", "body" => "<p>The shipment for Order ID# " . $orderId . " from RSR " .
					"is in pending state because a valid FFL dealer was not found. Contact RSR to get this cleared up. The contents of the RSR pending log are:</p>" .
					implode("<br>", $fileLines), "notification_code" => array("RETAIL_STORE_ORDER_NOTIFICATION", "DISTRIBUTOR_ERRORS")));
			}
		}
		ftp_close($this->iConnection);
		return $returnValues;
	}

}
