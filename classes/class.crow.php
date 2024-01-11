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

class Crow extends ProductDistributor {

	private $iFieldTranslations = array("quantity" => "inventorycount", "upc_code" => "upc", "product_code" => "sku", "description" => "productname",
        "alternate_description" => "skuname", "detailed_description" => "", "manufacturer_code" => "manufacturer", "model" => "XXXXX",
        "manufacturer_sku" => "xxxxx", "manufacturer_advertised_price" => "mapprice", "width" => "xxxx", "length" => "xxxx", "height" => "xxxx",
        "weight" => "weight", "base_cost" => "currentprice", "list_price" => "regularprice", "image_location" => "largeimage",
        "category" => "categoryhierarchy", "drop_ship_flag" => "dropshipflag");

	private $iLiveUrl = "https://api.crowshootingsupply.com/asmx/ws_orderv2.asmx?WSDL";
	private $iTestUrl = "https://api.crowshootingsupplymtuat.com/asmx/WS_OrderV2.asmx?WSDL";
	private $iUseUrl;
	private $iWebServiceClient = false;
	private $iLogging;

    function __construct($locationId) {
        $this->iProductDistributorCode = "CROW";
        $this->iClass3Products = false;  // Crow says they do not sell Class 3 products through the API
        $this->iLogging = !empty(getPreference("LOG_DISTRIBUTOR_CROW"));
        parent::__construct($locationId);
        $this->getFirearmsProductTags();
        if($this->isSoapInstalled()) {
            if ($GLOBALS['gDevelopmentServer']) {
                $this->iUseUrl = $this->iTestUrl;
                $streamContext = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ]);
            } else {
                $this->iUseUrl = $this->iLiveUrl;
                $streamContext = stream_context_create();
            }
            try {
                $this->iWebServiceClient = new SoapClient($this->iUseUrl, array('soap_version' => SOAP_1_2, 'trace' => 1, 'stream_context' => $streamContext));
            } catch (Exception $e) {
                $this->iErrorMessage = $e->getMessage();
            }
            // user agent is required by Crow's firewall
            ini_set('user_agent', 'coreFORCE/' . date("Y-m-d\TH:i:s", filemtime(__FILE__)));
            ini_set('default_socket_timeout', 120);
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

	function testCredentials() {
        if(!$this->isSoapInstalled()) {
            return false;
        }
		$fieldLabels = array();
		$customFieldCodeArray = array("CROW_PRODUCTS_FEED_ID", "CROW_ATTRIBUTES_FEED_ID", "CROW_INVENTORY_FEED_ID", "FFL_ORDER_USERNAME", "FFL_ORDER_PASSWORD");
		foreach ($customFieldCodeArray as $customFieldCode) {
			$this->iLocationCredentialRow["custom_field_" . strtolower($customFieldCode)] =
				CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], $customFieldCode, "PRODUCT_DISTRIBUTORS");
			$fieldLabels["custom_field_" . strtolower($customFieldCode)] = getFieldFromId("form_label", "custom_fields", "custom_field_code", $customFieldCode);
		}
		$ignoreFields = array("distributor_source", "date_last_run", "last_inventory_update", "inactive", "version");
		$labelSet = executeQuery("select * from product_distributor_field_labels where product_distributor_id = (select product_distributor_id from product_distributors where product_distributor_code = 'CROW')");
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
		$dealerEmail = $this->iLocationCredentialRow['user_name']; # email address
		$password = $this->iLocationCredentialRow['password']; # security code
		$request = array("cart" => array(
			"SecurityCode" => $password,
			"CartID" => 0,
			"Email" => $dealerEmail,
			"ShipToFirstName" => "Core",
			"ShipToLastName" => "Ware",
			"ShipToAddress1" => "4321 Main st",
			"ShipToCity" => "Schenectady",
			"ShipToState" => "NY",
			"ShipToZip" => "12345",
			"ShipToCountry" => "United States",
			"BillFirstName" => "Core",
			"BillLastName" => "Ware",
			"BillAddress1" => "4321 Main st",
			"BillCity" => "Schenectady",
			"BillState" => "NY",
			"BillZip" => "12345",
			"BillCountry" => "United States",
			"Phone" => "555-555-1212",
			"ForeignAddressFlag" => "false", // Boolean must be sent as text
			"OutofStockOption" => "Cancel",
			"SourceID" => 0));
		$response = $this->callWebServiceMethod("InitializeCart", $request, "http://Universaldatastream.com/");
		if ($response->InitializeCartResult->Status != "Success") {
			$this->iErrorMessage = "Security Code for placing orders is incorrect.";
			return false;
		}

        $ftpResult = $this->ftpUploadImplicitSsl(["ftp_server" => "sftp.crowshootingsupply.com",
            "ftp_user_name" => $this->iLocationCredentialRow['custom_field_ffl_order_username'],
            "ftp_password" => $this->iLocationCredentialRow['custom_field_ffl_order_password'],
            "test_credentials"=>true]);
        if ($ftpResult !== true) {
            $this->iErrorMessage = "Unable to access FTP server: " . $ftpResult;
            return false;
        }
        return true;
    }

    /**
     * Uses curl to upload a file to an FTP server using implicit FTPS (port 990).  This is not supported by ftp_ssl_connect.
     * @param array $parameters
     * @return string|true true if successful, error message if failed
     */
    private function ftpUploadImplicitSsl($parameters) {
        if (empty($parameters['test_credentials'])) {
            if (!empty($parameters['file_content'])) {
                $localFile = fopen('php://temp', 'r+');
                fwrite($localFile, $parameters['file_content']);
                rewind($localFile);
            } elseif(!empty($parameters['filename'])) {
                $localFile = fopen($parameters['filename'], 'r');
            } else {
                return "No file specified.";
            }
            $ftpServer = 'ftps://' . $parameters['ftp_server'] . '/' . $parameters['ftp_destination_file'];
        } else {
            $ftpServer = 'ftps://' . $parameters['ftp_server'];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ftpServer);
        curl_setopt($ch, CURLOPT_USERPWD, $parameters['ftp_user_name'] . ':' . $parameters['ftp_password']);
        curl_setopt($ch, CURLOPT_USE_SSL, true);
        curl_setopt($ch, CURLOPT_FTPSSLAUTH, CURLFTPAUTH_TLS);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (empty($parameters['test_credentials'])) {
            curl_setopt($ch, CURLOPT_UPLOAD, 1);
            curl_setopt($ch, CURLOPT_INFILE, $localFile);
        }

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        if($this->iLogging) {
            $logEntry = "Crow FTP upload result: " . var_export($result, true) . "\n"
                . "Crow FTP upload info: " . jsonEncode($info) . "\n"
                . (empty($err) ? "" : "Crow FTP upload error: " . $err);
            addDebugLog($logEntry);
        }
        curl_close($ch);
        if (!empty($err)) {
            return $err;
        }
        return true;
    }

    private function callWebServiceMethod($methodName, $requestArray, $xmlNamespace = "") {
        if (!$this->iWebServiceClient) {
            $logEntry = "Crow web service error: could not connect: " . $this->iErrorMessage;
            if ($this->iLogging) {
                addDebugLog($logEntry);
            }
            return false;
        }
        $requestXml = $this->arrayToXml($requestArray, $methodName, null, $xmlNamespace);
        $requestSoap = new SoapVar($requestXml, XSD_ANYXML);
        try {
            $response = call_user_func(array($this->iWebServiceClient, $methodName), $requestSoap);
        } catch (SoapFault $e) {
            $this->iErrorMessage = $e->getMessage();
            $logEntry = "Crow web service error: " . $this->iErrorMessage . "\n\n"
                . "Crow Request XML: " . $this->iWebServiceClient->__getLastRequest() . "\n"
                . "Crow Response XML: " . $this->iWebServiceClient->__getLastResponse() . "\n";
            addProgramLog($logEntry);
            if ($this->iLogging) {
                addDebugLog($logEntry);
            }
            return false;
        }
        $responseArray = json_decode(json_encode($response), true);
        if ($responseArray[$methodName . "Result"]["Status"] != "Success") {
            if (!empty($responseArray[$methodName . "Result"]["Message"])) {
                $this->iErrorMessage = $responseArray[$methodName . "Result"]["Message"];
            } elseif (!empty($responseArray[$methodName . "Result"]["ErrorList"]["string"])) {
                $this->iErrorMessage = $responseArray[$methodName . "Result"]["ErrorList"]["string"];
            } else {
				var_export($responseArray[$methodName . "Result"]);
            }
            $logEntry = "Crow web service error: " . $this->iErrorMessage . "\n\n"
                . "Crow Request XML: " . $this->iWebServiceClient->__getLastRequest() . "\n"
                . "Crow Response XML: " . $this->iWebServiceClient->__getLastResponse() . "\n";
            addProgramLog($logEntry);
            if ($this->iLogging) {
                addDebugLog($logEntry);
            }
            return false;
        }
        if ($this->iLogging) {
            addDebugLog("Crow web service call: " . $methodName . "\n"
                . "Crow Request XML: " . $this->iWebServiceClient->__getLastRequest() . "\n"
                . "Crow Response XML: " . $this->iWebServiceClient->__getLastResponse() . "\n");
        }
        return $response;
    }

    private function arrayToXml($array, $rootElement = null, $parentXml = null, $xmlNamespace = null) {

        if ($parentXml === null) {
            // Make sure root element is actually XML
            if (!preg_match("/\<.*\/\>/", $rootElement)) {
                $rootElement = "<" . $rootElement . "/>";
            }
            // If there is no Root Element then create "root"
			try {
				$xml = new SimpleXMLElement($rootElement !== null ? $rootElement : "<root/>");
				if (!empty($xmlNamespace)) {
					$xml->addAttribute("xmlns", $xmlNamespace);
				}
			} catch (Exception $e) {
				$xml = false;
			}
        } else { // Recursive call - add elements to parent
            $xml = $parentXml;
        }

        // Visit all key value pair
        foreach ($array as $key => $value) {
            // If the value is a nested array then recurse
            if (is_numeric($key)) {
                // Support for multiple elements with the same name. If key is numeric (unnamed key), then add the child directly.
                $this->arrayToXml($value, null, $xml);
            } elseif (is_array($value)) {
                // Recursion for nested array
                $this->arrayToXml($value, null, $xml->addChild($key));
            } else {
                // Add child element.  Make sure special characters are escaped.
                $xml->addChild($key, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
            }
        }

        // Remove the xml version header, as this XML will be added as a child element.
        return str_replace("<?xml version=\"1.0\"?>\n", "", $xml->asXML());
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
        $foundCount = 0;
        $updatedCount = 0;
        $noUpc = 0;
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

            $productDistributorDropshipProhibitionId = getFieldFromId("product_distributor_dropship_prohibition_id", "product_distributor_dropship_prohibitions", "product_id", $productId,
                "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
            if (strtolower($thisProductInfo[$this->iFieldTranslations['drop_ship_flag']]) == "true") {
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
                if ($thisProductInfo['fflflag'] == 1) { // FFLFlag has values 0, 1, 2. FFL required = 1.  2 is the same as 0.
                    $corewareProductData['serializable'] = 1;
                }
            }
            if ($thisProductInfo['fflflag'] == 1) { // FFLFlag has values 0, 1, 2. FFL required = 1.  2 is the same as 0.
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
					$imageName = basename($imageUrl);
					$imageId = $imageContents = "";
					if (urlExists($imageUrl)) {
						$imageContents = file_get_contents($imageUrl);
					}
					if (!empty($imageContents)) {
						$imageId = createImage(array("extension" => "jpg", "image_code"=>"PRODUCT_IMAGE_" . $productId, "file_content" => $imageContents, "name" => $imageName, "description" => $productRow['description'], "detailed_description" => $productRow['detailed_description'], "source_code" => "CROW_IMAGE"));
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
                if (!empty($thisProductInfo['attributes'])) { // Add facets
                    $productFacets = array();
                    foreach ($thisProductInfo['attributes'] as $thisAttribute) {
                        $thisProductFacet = array();
                        $thisProductFacet['product_facet_code'] = makeCode(str_replace(" ", "", $thisAttribute['attributename']));
                        $thisProductFacet['facet_value'] = $thisAttribute['attributevalue'];
                        $productFacets[] = $thisProductFacet;
                    }
                    $this->addProductFacets($productId, $productFacets);
                }
            }
        }

		return $processCount . " processed, " . $insertCount . " inserted, " . $imageCount . " images added, " . $foundCount . " existing, " . $updatedCount . " updated, " . $noUpc . " no UPC, " . $duplicateProductCount . " duplicate products skipped, " . $badImageCount . " bad images found";
    }

    function getProducts() {
        $productArray = getCachedData("crow_product_feed", "");

        if (empty($productArray)) {

            // 2020-04-07 Crow's product feed is authenticated only by URL parameter.
            // SPB feeds: 235 = products (daily), 236 = inventory (every 30 minutes), 237 = attributes (daily)
            $feedType = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_DATA_FEED_TYPE", "PRODUCT_DISTRIBUTORS") ?: "CRSH";
            $productsFeedId = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_PRODUCTS_FEED_ID", "PRODUCT_DISTRIBUTORS");
            $attributesFeedId = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_ATTRIBUTES_FEED_ID", "PRODUCT_DISTRIBUTORS");
            if (empty($feedType) || empty($productsFeedId) || empty($attributesFeedId)) {
                $this->iErrorMessage = "Feed ID credentials are missing";
                return false;
            }

            $feedUrl = "https://api.crowshootingsupply.com/aspx/util/feedgenerator.aspx?id=" . $productsFeedId . "&type=" . $feedType;
            $productArray = $this->getCsvFromUrl($feedUrl);
            if ($productArray === false) {
                $this->iErrorMessage = "Product feed returned no data";
                return false;
            }
            if (!is_array($productArray)) {
                $productArray = array();
            }

            foreach ($productArray as $index => $thisProduct) {
                if (!array_key_exists("skuid", $thisProduct)) {
                    unset($productArray[$index]);
                }
                if (empty($productArray[$index]['custom_price'])) {
                    $productArray[$index]['custom_price'] = $thisProduct['price'];
                }
            }

            // get attribute feed and link attributes to products
            $feedUrl = "https://api.crowshootingsupply.com/aspx/util/feedgenerator.aspx?id=" . $attributesFeedId . "&type=" . $feedType;
            $attributeArray = $this->getCsvFromUrl($feedUrl);

            foreach ($attributeArray as $thisAttribute) {
                // If the attribute refers to a SKU that's not in the product array, skip it.
                if (!array_key_exists($thisAttribute['sku'], $productArray)) {
                    continue;
                }
                $productArray[$thisAttribute['sku']]['attributes'][] = $thisAttribute;
            }

            unset($attributeArray);

            // Crow updates the feed once per day.  Cache it until the next calendar day.
            $hoursToCache = (strtotime('tomorrow 5 am') - strtotime('now')) / 3600;
            setCachedData("crow_product_feed", "", $productArray, $hoursToCache);
        }

        return $productArray;
    }

    private function getCsvFromUrl($csvFileUrl) {
        $this->iLogging = true;
        $startTime = getMilliseconds();
        $context = stream_context_create(['http' => ['timeout' => 600]]);
        $openFile = fopen($csvFileUrl, "r", false, $context);
		if (!$openFile) {
            if ($this->iLogging) {
                addDebugLog("Crow feed returned nothing from " . $csvFileUrl . ", time taken: " . getTimeElapsed($startTime,getMilliseconds()));
            }
            return false;
        }
        $fieldNames = array();
        $resultArray = array();
        $count = 0;
        while ($csvData = fgetcsv($openFile)) {
            $count++;
            if ($count == 1) { // CSV column names
                foreach ($csvData as $thisName) {
                    $fieldNames[] = makeCode(trim($thisName), array("lowercase" => true));
                }
            } else { // CSV data
                $fieldData = array();
                foreach ($csvData as $index => $thisData) {
                    $thisFieldName = $fieldNames[$index];
                    $fieldData[$thisFieldName] = trim($thisData);
                }
                $resultArray[$fieldData['sku']] = $fieldData;
            }
        }
        fclose($openFile);
        if ($this->iLogging) {
            addDebugLog("Crow feed returned " . $count . " records from " . $csvFileUrl . " time taken: " . getTimeElapsed($startTime,getMilliseconds()));
        }
        return $resultArray;
    }

    function getFacets($parameters = array()) {

        $productArray = $this->getProducts();
        if ($productArray === false) {
            return false;
        }

        $productFacets = array();
        foreach ($productArray as $thisProductInfo) {
            if (empty($thisProductInfo[$this->iFieldTranslations['category']])) {
                continue;
            }
            $categoryCode = makeCode($thisProductInfo[$this->iFieldTranslations['category']]);
            if (!array_key_exists($categoryCode, $productFacets)) {
                $productFacets[$categoryCode] = array();
            }
            if (!is_array($thisProductInfo['attributes'])) { // Not every product will have attributes
                continue;
            }
            foreach ($thisProductInfo['attributes'] as $thisAttribute) {
                $facetDescription = $thisAttribute['attributename'];
                $facetCode = makeCode(str_replace(" ", "", $facetDescription));
                if (array_key_exists($facetCode, $productFacets[$categoryCode])) {
                    continue;
                }
                if (empty($thisAttribute['attributevalue'])) {
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

        $feedType = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_DATA_FEED_TYPE", "PRODUCT_DISTRIBUTORS") ?: "CRSH";
        $inventoryFeedId = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_INVENTORY_FEED_ID", "PRODUCT_DISTRIBUTORS");

        $feedUrl = "https://api.crowshootingsupply.com/aspx/util/feedgenerator.aspx?id=" . $inventoryFeedId . "&type=" . $feedType;
        $productInventoryArray = $this->getCsvFromUrl($feedUrl);
        if ($productInventoryArray === false) {
            $this->iErrorMessage = "Product feed returned no data";
            return false;
        }

        foreach ($productInventoryArray as $thisProductInfo) {
            if ($thisProductInfo[$this->iFieldTranslations['product_code']] != $distributorProductCode) {
                continue;
            }
            $quantityAvailable = $thisProductInfo[$this->iFieldTranslations['quantity']];
            $cost = round($thisProductInfo[$this->iFieldTranslations['base_cost']], 2);
            break;
        }

        if ($quantityAvailable === false) {
            return false;
        }
        return array("quantity" => $quantityAvailable, "cost" => $cost);
    }

    function syncInventory($parameters = array()) {
        // 2020-04-07 Crow's data feed is authenticated only by URL parameter.
        // SPB: 235 = products (daily), 236 = inventory (every 30 minutes), 237 = attributes (daily)
        $feedType = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_DATA_FEED_TYPE", "PRODUCT_DISTRIBUTORS") ?: "CRSH";
        $inventoryFeedId = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "CROW_INVENTORY_FEED_ID", "PRODUCT_DISTRIBUTORS");
        $feedUrl = "https://api.crowshootingsupply.com/aspx/util/feedgenerator.aspx?id=" . $inventoryFeedId . "&type=" . $feedType;
        $productInventoryArray = $this->getCsvFromUrl($feedUrl);
        $inventoryUpdateArray = array();
        if ($productInventoryArray === false) {
            $this->iErrorMessage = "Product feed returned no data";
            return false;
		}
		foreach ($productInventoryArray as $thisProductInfo) {
            $thisProductInfo[$this->iFieldTranslations['base_cost']] = round($thisProductInfo[$this->iFieldTranslations['base_cost']], 2);

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
                $productManufacturers[makeCode($thisProductInfo['manufacturer'])] = array("business_name" => $thisProductInfo['manufacturer']);
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

        $distributorCategoryField = "categoryhierarchy";

        $productCategories = array();
        foreach ($productArray as $thisProductInfo) {
            if (!empty($thisProductInfo[$distributorCategoryField])) {
                $productCategories[makeCode($thisProductInfo[$distributorCategoryField])] = array("description" => $thisProductInfo[$distributorCategoryField]);
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
            $addressRow['first_name'] = $contactRow['first_name']; // Crow requires customer name for order
            $addressRow['last_name'] = $contactRow['last_name'];
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
        $dealerOrderItemRows = $orderParts['dealer_order_items']; // Note: Crow says they do not carry class 3 items

        if (!empty($fflOrderItemRows)) {
            $fflRow = (new FFL($ordersRow['federal_firearms_licensee_id']))->getFFLRow();
            if ($fflRow) {
                if (empty($fflRow['first_name']) && empty($fflRow['last_name'])) {
                    $fflRow['first_name'] = $contactRow['first_name'];
                    $fflRow['last_name'] = $contactRow['last_name'];
                }
                $fflRow['first_name'] = $fflRow['first_name'] ?: $fflRow['business_name'];
                $fflRow['last_name'] = $fflRow['last_name'] ?: $fflRow['business_name'];
                $fflRow['license_number'] = str_replace("-", "", $fflRow['license_number']);
                $fflRow['license_type'] = substr($fflRow['license_number'], 6, 2); // From Crow documentation: Must match characters 7 & 8 of the FFL number
                $fflRow['comments'] = sprintf("Customer: %s %s", getDisplayName($contactRow['contact_id']), getContactPhoneNumber($contactRow['contact_id']));
            } else {
                $this->iErrorMessage = "No FFL dealer set for this order";
                return false;
            }
        } else {
            $fflRow = array();
        }

        $failedItems = array();
        $returnValues = array();

        // Submit Customer order items
        if (!empty($customerOrderItemRows)) {
            $orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], 999);
            if (!empty($orderSet['sql_error'])) {
                $returnValues['error_message'] = $this->iErrorMessage = $orderSet['sql_error'];
                foreach ($customerOrderItemRows as $thisItemRow) {
                    $failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
                }
            } else {
                $remoteOrderId = $orderSet['insert_id'];
                $addressRow['postal_code'] = substr($addressRow['postal_code'], 0, 5);
                $purchaseOrderNumber = $orderId . "-" . $remoteOrderId;

                $orderResponse = $this->sendWebServiceOrder($addressRow, $customerOrderItemRows, $purchaseOrderNumber);

                if ($this->iErrorMessage) {
                    $returnValues['error_message'] = $this->iErrorMessage;
                    foreach ($customerOrderItemRows as $thisItemRow) {
                        $failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
                    }
                    executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
                } else {
                    $orderNumber = $orderResponse->SubmitCartAsOrderResult->OrderID;
                    // Orders from Crow are all-or-nothing. There is no line item response in the result.
                    // The web service will not reject an order for insufficient quantity. Crow will communicate that to the dealer after the order is placed.

                    foreach ($customerOrderItemRows as $thisItemRow) {
                        executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
                            $remoteOrderId, $thisItemRow['order_item_id'], $thisItemRow['quantity']);
                    }
                    executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
                    $returnValues['customer'] = array("order_type" => "customer", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $ordersRow['full_name']);
                }
            }
        }

        // Submit Dealer order items
        if (!empty($dealerOrderItemRows)) {
            $orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], 999);
            if (!empty($orderSet['sql_error'])) {
                $returnValues['error_message'] = $this->iErrorMessage = $orderSet['sql_error'];
                foreach ($dealerOrderItemRows as $thisItemRow) {
                    $failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
                }
            } else {
                $remoteOrderId = $orderSet['insert_id'];
                $purchaseOrderNumber = $orderId . "-" . $remoteOrderId;

                $this->iLocationContactRow['postal_code'] = substr($this->iLocationContactRow['postal_code'], 0, 5);

                $orderResponse = $this->sendWebServiceOrder($this->iLocationContactRow, $dealerOrderItemRows, $purchaseOrderNumber);

                if ($this->iErrorMessage) {
                    $returnValues['error_message'] = $this->iErrorMessage;
                    foreach ($dealerOrderItemRows as $thisItemRow) {
                        $failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
                    }
                    executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
                } else {
                    $orderNumber = $orderResponse->SubmitCartAsOrderResult->OrderID;
                    // Orders from Crow are all-or-nothing. There is no line item response in the result.
                    // The web service will not reject an order for insufficient quantity. Crow will communicate that to the dealer after the order is placed.

                    foreach ($dealerOrderItemRows as $thisItemRow) {
                        executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
                            $remoteOrderId, $thisItemRow['order_item_id'], $thisItemRow['quantity']);
                    }

                    executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
                    $returnValues['dealer'] = array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => $GLOBALS['gClientName']);
                }
            }
        }

        // Submit FFL order items
        if (!empty($fflOrderItemRows)) {
            $orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)", $ordersRow['order_id'], 999);
            if (!empty($orderSet['sql_error'])) {
                $returnValues['error_message'] = $this->iErrorMessage = $orderSet['sql_error'];
                foreach ($fflOrderItemRows as $thisItemRow) {
                    $failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
                }
            } else {
                $remoteOrderId = $orderSet['insert_id'];
                $purchaseOrderNumber = $orderId . "-" . $remoteOrderId;

                $fflRow['postal_code'] = substr($fflRow['postal_code'], 0, 5);

                $orderResponse = $this->sendWebServiceOrder($fflRow, $fflOrderItemRows, $purchaseOrderNumber, true);

                if ($this->iErrorMessage) {
                    $returnValues['error_message'] = $this->iErrorMessage;
                    foreach ($fflOrderItemRows as $thisItemRow) {
                        $failedItems[] = array("order_item_id" => $thisItemRow['order_item_id'], "quantity" => $thisItemRow['quantity']);
                    }
                    executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
                } else {
                    $orderNumber = $orderResponse->SubmitCartAsOrderResult->OrderID;
                    // Orders from Crow are all-or-nothing. There is no line item response in the result.
                    // The web service will not reject an order for insufficient quantity. Crow will communicate that to the dealer after the order is placed.

                    $ftpServer = "sftp.crowshootingsupply.com";
                    $fflUsername = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_USERNAME", "PRODUCT_DISTRIBUTORS");
                    $fflPassword = CustomField::getCustomFieldData($this->iLocationCredentialRow['location_credential_id'], "FFL_ORDER_PASSWORD", "PRODUCT_DISTRIBUTORS");
                    $fflAccountNumber = $this->iLocationCredentialRow['customer_number'];

                    $fflLicenseFileContent = "";
                    if (!empty($fflRow['file_id'])) {
                        $extension = getFieldFromId("extension", "files", "file_id", $fflRow['file_id']);
                        $osFilename = getFieldFromId("os_filename", "files", "file_id", $fflRow['file_id']);
                        if (empty($osFilename)) {
                            $fflLicenseFileContent = getFieldFromId("file_content", "files", "file_id", $fflRow['file_id']);
                        } else {
                            $fflLicenseFileContent = getExternalFileContents($osFilename);
                        }
                        if ($extension != 'pdf') {
                            $randomString = getRandomString();
                            $filename = $GLOBALS['gDocumentRoot'] . "/cache/" . $randomString . ".jpg";
                            file_put_contents($filename, $fflLicenseFileContent);
                            $htmlContent = "<html><body><img width='100%' src='file://" . $filename . "'/></body></html>";
                            $fflLicenseFileContent = outputPDF($htmlContent, array('get_contents' => true));
                        }
                    }

                    if (empty($fflLicenseFileContent)) {
                        $this->iErrorMessage = "FFL file missing for license " . $fflRow['license_number'];
                    } else {
                        $fflLicenseFileName = $fflAccountNumber . "_FFL_" . date_format(date_create($fflRow['expiration_date']), 'm-d-Y_')
                            . substr($fflRow['license_number'], -5) . "_CN-" . $orderNumber . ".pdf";

                        if (!$GLOBALS['gDevelopmentServer']) {
                            $uploadResponse = $this->ftpUploadImplicitSsl(["ftp_server" => $ftpServer,
                                "ftp_user_name" => $fflUsername,
                                "ftp_password" => $fflPassword,
                                "ftp_destination_file" => "FFL/" . $fflLicenseFileName,
                                "file_content" => $fflLicenseFileContent]);
                            if ($uploadResponse !== true) {
                                $this->iErrorMessage = "Uploading FFL file failed: " . $uploadResponse;
                            }
                        }
                    }

                    foreach ($fflOrderItemRows as $thisItemRow) {
                        executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
                            $remoteOrderId, $thisItemRow['order_item_id'], $thisItemRow['quantity']);
                    }

                    executeQuery("update remote_orders set order_number = ? where remote_order_id = ?", $orderNumber, $remoteOrderId);
                    $returnValues['ffl'] = array("order_type" => "ffl", "remote_order_id" => $remoteOrderId, "order_number" => $orderNumber, "ship_to" => (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']));

                    if (!empty($this->iErrorMessage)) { // return info message if FFL file upload failed but order succeeded
                        $returnValues['info_message'] = $this->iErrorMessage;
                    }
                }
            }
        }

        if (!empty($failedItems)) {
            $returnValues['failed_items'] = $failedItems;
        }

        return $returnValues;
    }

    private function sendWebServiceOrder($shipToRow, $orderItemRows, $purchaseOrderNumber, $isFflOrder = false) {
        $dealerEmail = $this->iLocationCredentialRow['user_name'];
        $securityCode = $this->iLocationCredentialRow['password'];
        // first and last name are required for the billing address
        $this->iLocationContactRow['first_name'] = $this->iLocationContactRow['first_name'] ?: $this->iLocationContactRow['business_name'];
        $this->iLocationContactRow['last_name'] = $this->iLocationContactRow['last_name'] ?: $this->iLocationContactRow['business_name'];
        $shipToRow['first_name'] = $shipToRow['first_name'] ?: $shipToRow['business_name'];
        $shipToRow['last_name'] = $shipToRow['last_name'] ?: $shipToRow['business_name'];

        $request = array("cart" => array(
            "SecurityCode" => $securityCode,
            "CartID" => 0,
            "Email" => $dealerEmail,
            "ShipToFirstName" => $shipToRow['first_name'],
            "ShipToLastName" => $shipToRow['last_name'],
            "ShipToShop" => $shipToRow['business_name'],
            "ShipToAddress1" => $shipToRow['address_1'],
            "ShipToAddress2" => $shipToRow['address_2'],
            "ShipToCity" => $shipToRow['city'],
            "ShipToState" => $shipToRow['state'],
            "ShipToZip" => $shipToRow['postal_code'],
            "ShipToCountry" => getFieldFromId("country_name", "countries", "country_id", $shipToRow['country_id']),
            "BillFirstName" => $this->iLocationContactRow['first_name'],
            "BillLastName" => $this->iLocationContactRow['last_name'],
            "BillShop" => $this->iLocationContactRow['business_name'],
            "BillAddress1" => $this->iLocationContactRow['address_1'],
            "BillAddress2" => $this->iLocationContactRow['address_2'],
            "BillCity" => $this->iLocationContactRow['city'],
            "BillState" => $this->iLocationContactRow['state'],
            "BillZip" => $this->iLocationContactRow['postal_code'],
            "BillCountry" => getFieldFromId("country_name", "countries", "country_id", $this->iLocationContactRow['country_id']),
            "Phone" => $this->iLocationContactRow['phone_number'],
            "ForeignAddressFlag" => ($shipToRow['country_id'] == 1000 ? "false" : "true"),
            "OutofStockOption" => "Cancel",
            "SourceID" => 0));
        if ($isFflOrder) {
            $request['cart']['Comments'] = $shipToRow['comments'];
        }
        $response = $this->callWebServiceMethod("InitializeCart", $request, "http://Universaldatastream.com/");
        if (!empty($this->iErrorMessage)) {
            return false;
        }
        $cartId = $response->InitializeCartResult->CartID;

        if ($isFflOrder) {
            // Add FFL license details to cart

            $request = array("cFFL" => array(
                "SecurityCode" => $securityCode,
                "CartID" => $cartId,
                "FFLNumber" => $shipToRow['license_number'],
                "FFLExpireDate" => $shipToRow['expiration_date'],
                "FFLType" => $shipToRow['license_type']
            ));
            $this->callWebServiceMethod("UpdateCartWithFFLLicense", $request, "http://Universaldatastream.com/");
            if (!empty($this->iErrorMessage)) {
                return false;
            }
        }

        // Add items to cart
        $request = array("ci" => array(
            "SecurityCode" => $securityCode,
            "CartID" => $cartId));
        $cartItems = array();
        foreach ($orderItemRows as $thisItemRow) {
            $cartItems[] = array("CartItem" => array(
                "SKUID" => 0,
                "SKU" => $thisItemRow['distributor_product_code'],
                "Quantity" => $thisItemRow['quantity'],
                "Status" => "in-stock",
                "UnitPrice" => $thisItemRow['base_cost'],
                "CartItemID" => 0
            ));
        }
        $request["ci"]["CartItems"] = $cartItems;
        $this->callWebServiceMethod("AddCartItems", $request, "http://Universaldatastream.com/");
        if (!empty($this->iErrorMessage)) {
            return false;
        }

        // Set shipping for cart
        $request = array("cs" => array(
            "SecurityCode" => $securityCode,
            "CartID" => $cartId,
            "ShipVia" => "Fixed Flat Rate",
            "ShipCost" => 0,
            "ShipCarrierID" => 0
        ));
        $this->callWebServiceMethod("UpdateCartShipping", $request, "http://Universaldatastream.com/");
        if (!empty($this->iErrorMessage)) {
            return false;
        }

        // Add Payment to cart
        $request = array("cp" => array(
            "SecurityCode" => $securityCode,
            "CartID" => $cartId,
            "PaymentTypeID" => 2,
            "PurchaseOrderNumber" => $purchaseOrderNumber
        ));
        $this->callWebServiceMethod("UpdateCartPayment", $request, "http://Universaldatastream.com/");
        if (!empty($this->iErrorMessage)) {
            return false;
        }

        // Submit cart as order
        $request = array("co" => array(
            "SecurityCode" => $securityCode,
            "CartID" => $cartId
        ));
        $response = $this->callWebServiceMethod("SubmitCartAsOrder", $request, "http://Universaldatastream.com/");
        if (!empty($this->iErrorMessage)) {
            return false;
        }
        return $response;
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

            $cost = ProductCatalog::getLocationBaseCost($thisProduct['product_id'], $thisProduct['location_id'],false);

            $dealerOrderItemRows[$thisProduct['product_id']] = array(
                "distributor_product_code" => $distributorProductCode,
                "product_id" => $thisProduct['product_id'],
                "quantity" => $thisProduct['quantity'],
                "base_cost" => $cost,
                "notes" => $thisProduct['notes']);
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
        $purchaseOrderNumber = $orderPrefix . $distributorOrderId;

        $this->iLocationContactRow['postal_code'] = substr($this->iLocationContactRow['postal_code'], 0, 5);

        $orderResponse = $this->sendWebServiceOrder($this->iLocationContactRow, $dealerOrderItemRows, $purchaseOrderNumber);

        if (!$orderResponse) {
            $this->iErrorMessage = $this->iErrorMessage ?: "Unknown error";
            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
            return false;
        } else {
            $orderNumber = $orderResponse->SubmitCartAsOrderResult->OrderID;
            $productIds = array();

            // Orders from Crow are all-or-nothing. There is no line item response in the result.
            // The web service will not reject an order for insufficient quantity. Crow will communicate that to the dealer after the order is placed.

            foreach ($dealerOrderItemRows as $thisItemRow) {
                executeQuery("insert into distributor_order_items (distributor_order_id,product_id,quantity,notes) values (?,?,?,?)",
                    $distributorOrderId, $thisItemRow['product_id'], $thisItemRow['quantity'], $thisItemRow['notes']);
                $productIds[] = $thisItemRow['product_id'];
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
        $this->iErrorMessage = "";

        $orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $orderShipmentId, "tracking_identifier is null");
        if (empty($orderShipmentRow['remote_order_id'])) {
            return false;
        }
        $remoteOrderRow = getRowFromId("remote_orders", "remote_order_id", $orderShipmentRow['remote_order_id']);
        if (empty($remoteOrderRow['order_number'])) {
            return false;
        }
        $orderNumber = $remoteOrderRow['order_number'];
        $securityCode = $this->iLocationCredentialRow['password'];

        $request = array("o" => array(
            "SecurityCode" => $securityCode,
            "OrderID" => $orderNumber,
            "COBOLOrderID" => 0
        ));
        $response = $this->callWebServiceMethod("GetOrderStatus", $request, "http://Universaldatastream.com/");
        if (!empty($this->iErrorMessage)) {
            return false;
        }

        // Return value is an XML string enclosed inside the <OrderStatus> element of the SOAP response.
        $orderStatusString = $response->GetOrderStatusResult->OrderStatus;
        $orderStatusString = str_replace("<?xml version=\"1.0\" encoding=\"utf-16\"?>\n", "", $orderStatusString);
		try {
			$orderStatusXml = new SimpleXMLElement($orderStatusString);
		} catch (Exception $e) {
			$orderStatusXml = false;
		}

        if ($orderStatusXml->Order->OrderStatus == "ORDER NOT FOUND") {
            $this->iErrorMessage = "Crow web service error getting tracking for order number " . $orderNumber . ": ORDER NOT FOUND";
            return false;
        }
        $trackingInfo = array();
        $trackingInfo['tracking_number'] = trim($orderStatusXml->Order->TrackingNumber);
        $trackingInfo['ship_method'] = $orderStatusXml->Order->ShipMethod;

        // Crow sends the carrier info before the tracking number.  Make sure we don't save anything unless we have all the data.
        if (strlen($trackingInfo['tracking_number']) < 5) {
            return array();
        }

        // TODO: Crow may have up to 10 tracking numbers. How to return multiple numbers?
        // Identify carrier from ShipMethod; assume shipping_carrier_code will be included in ShipMethod
        $resultSet = executeQuery("select shipping_carrier_id, shipping_carrier_code from shipping_carriers");
        $shippingCarriers = array();
        while ($row = getNextRow($resultSet)) {
            $shippingCarriers[$row['shipping_carrier_code']] = $row['shipping_carrier_id'];
        }
        $shippingCarrierId = null;
        foreach (array_keys($shippingCarriers) as $thisCarrierCode) {
			if (strpos(strtoupper($trackingInfo['ship_method']), $thisCarrierCode)) {
                $shippingCarrierId = $shippingCarriers[$thisCarrierCode];
                break;
            }
        }
        // Special case: Crow uses "Parcel Post" for USPS
		if (empty($shippingCarrierId) && strpos(strtoupper($trackingInfo['ship_method']), "PARCEL")) {
            $shippingCarrierId = $shippingCarriers['USPS'];
        }
        $resultSet = executeQuery("update order_shipments set tracking_identifier = ?,carrier_description = ?, shipping_carrier_id = ? where order_shipment_id = ?",
            $trackingInfo['tracking_number'], $trackingInfo['ship_method'], $shippingCarrierId, $orderShipmentId);
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
