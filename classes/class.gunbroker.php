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

class GunBroker {

	private $iDevKey = "bf2a2dd5-883a-4809-9f30-b76c9088a35a";
	private $iCredentials = array();
	private $iErrorMessage = "";
	private $iTestMode = false;
	private $iUrl = "https://api.gunbroker.com/v1";
	private $iTestUrl = "https://api.sandbox.gunbroker.com/v1";
	private $iTestDevKey = "16c4449e-0b24-4eca-b388-0289a5e4bd33";
	private $iLogging = false;
    private $iLogLength;
	private $iGunbrokerUserId;
	private $iIgnoreError = "violates GunBroker.com listing policies";
	private $iAutoEndListings;
	private $iAutoEndMax;
	private $iListingCounts = array();
	private $iItemData = array();
    private $iAccessToken = "";
    private $iTokenExpiration = 0;
    private $iTokenExpirationSeconds = 1200;
	const TEST_UN = "corewareseller";
	const TEST_PW = 'Bref$Drox$1654';
    private $iUpcIndex = array();
    private $iSkuIndex = array();


	function __construct($testMode = false) {
		$this->iCredentials['un'] = getPreference("GUNBROKER_USERNAME");
		$this->iCredentials['pw'] = getPreference("GUNBROKER_PASSWORD");
		$this->iTestMode = ($testMode || $GLOBALS['gDevelopmentServer'] || getPreference("GUNBROKER_TEST_SERVER"));
		if ($this->iTestMode) {
			$this->iUrl = $this->iTestUrl;
			$this->iDevKey = $this->iTestDevKey;
			$this->iCredentials['un'] = self::TEST_UN;
			$this->iCredentials['pw'] = self::TEST_PW;
		}
		if (empty($this->iCredentials['un']) || empty($this->iCredentials['pw'])) {
			throw new Exception("Username and Password are required");
		}
		$this->iLogging = !empty(getPreference("LOG_GUNBROKER"));
        $this->iLogLength = getPreference("GUNBROKER_LOG_LENGTH") ?: 500;
		if (!$this->setUserId()) {
			throw new Exception($this->iErrorMessage ?: "Unknown error occurred");
		}
        $this->iAutoEndListings = empty(getPreference("GUNBROKER_NEVER_END_LISTINGS"));
        $this->iAutoEndMax = getPreference("GUNBROKER_AUTOEND_MAXIMUM") ?: 50;
        $tokenExpirationMinutes = getPreference("GUNBROKER_TOKEN_EXPIRATION_MINUTES");
        if(empty($tokenExpirationMinutes) || !is_numeric($tokenExpirationMinutes)) {
            $tokenExpirationMinutes = 20;
        }
        $this->iTokenExpirationSeconds = $tokenExpirationMinutes * 60;
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function getItemUrl() {
		return ($this->iTestMode ? "https://www.sandbox.gunbroker.com/item/%gunbroker_identifier%" : "https://www.gunbroker.com/item/%gunbroker_identifier%");
	}

    /**
     * @param $parameters
     * @return false|mixed
     * Supported Parameters:
     * - url_path - url path to append to API address
     * - post_fields - data to send (if included, verb will be set to POST)
     * - verb - verb to use if not GET or POST
     * - check_gb_status_code (optional) - if set, will return an error if gbStatusCode is missing from response
     * - timeout (optional) - override default timeout
     * - refresh_token (optional) - only used for recursion if the token expired
     */
    private function makeRequest($parameters) {
        $accessToken = $this->getAccessToken($parameters['refresh_token']);
		if (empty($accessToken)) {
			if (empty($this->iErrorMessage)) {
				$this->iErrorMessage = "Unable to get access token";
			}
			return false;
		}
        $curlUrl = $this->iUrl . "/" . ltrim($parameters['url_path'],"/");

        $timeout = (!empty($parameters['timeout']) && is_numeric($parameters['timeout'])) ? $parameters['timeout'] : $GLOBALS['gCurlTimeout'] * 4;

        $curlOptions = array(
            CURLOPT_URL => $curlUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => 0);

        if($parameters['send_as_form']) {
            $postFields = json_encode($parameters['post_fields']);
            $postFields = str_replace("\/", "/", $postFields);

            $curlOptions[CURLOPT_CUSTOMREQUEST] = "POST";
            $curlOptions[CURLOPT_POSTFIELDS] = array("data"=>$postFields);
            $curlOptions[CURLOPT_HTTPHEADER] = array(
                "X-DevKey: " . $this->iDevKey,
                "X-AccessToken: " . $accessToken,
                "Content-Type: multipart/form-data"
            );
        } else {
            $curlOptions[CURLOPT_HTTPHEADER] = array(
                "X-DevKey: " . $this->iDevKey,
                "X-AccessToken: " . $accessToken,
                "Content-Type: application/json"
            );

            if(!empty($parameters['post_fields'])) {
                $postFields = json_encode($parameters['post_fields']);
                $postFields = str_replace("\/", "/", $postFields);
                $curlOptions[CURLOPT_POSTFIELDS] = $postFields;
                $curlOptions[CURLOPT_CUSTOMREQUEST] = "POST";
            }

            if(!empty($parameters['verb'])) {
                $curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($parameters['verb']);
            }
        }

        $curl = curl_init();
        curl_setopt_array($curl, $curlOptions);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if($httpCode == 401 && empty($parameters['refresh_token'])) { // token expired
            $parameters['refresh_token'] = true;
            if($this->iLogging) {
                addDebugLog("GunBroker access token expired. Refreshing...");
            }
            return $this->makeRequest($parameters);
        }

        if ($this->iLogging) {
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $callers = array();
            for ($index = 1; $index < count($dbt); $index++) {
                $callers[] = $dbt[$index]['class'] . "::" . $dbt[$index]['function'] . "(" . $dbt[$index]['file'] . ", line " . $dbt[$index]['line'] . ")";
            }

            addDebugLog("GunBroker request: " . $curlOptions[CURLOPT_CUSTOMREQUEST] . " " . $curlUrl
                . "\nGunBroker Calling function: " . implode(", ", $callers)
                . (empty($postFields) ? "" : "\nGunBroker Data: " . getFirstPart($postFields, $this->iLogLength))
                . "\nGunBroker Result: " .  getFirstPart($response, $this->iLogLength)
                . (empty($err) ? "" : "\nGunBroker Error: " . $err)
                . "\nGunBroker HTTP Status: " . $httpCode);
        }

        $result = json_decode($response, true);
        if ($httpCode > 299 || ($parameters['check_gb_status_code'] && is_array($result) && !array_key_exists("gbStatusCode", $result))) {
            $this->iErrorMessage = $result['userMessage'] ?: "An error occurred: status code " . $httpCode;
            return false;
        }
        return fixFieldCase($result);
    }


    private function getAccessToken($refreshToken = false) {
        if(!$refreshToken && !empty($this->iAccessToken) && time() < $this->iTokenExpiration) {
            return $this->iAccessToken;
        }

        $curl = curl_init();
        $dataArray = array("username" => $this->iCredentials['un'], "password" => $this->iCredentials['pw']);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->iUrl . "/Users/AccessToken",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS => json_encode($dataArray),
            CURLOPT_HTTPHEADER => array(
                "X-DevKey: " . $this->iDevKey,
                "Content-Type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($curlError) {
            $this->iErrorMessage = $curlError;
            return false;
        }
        $responseArray = json_decode($response, true);
        if(empty($responseArray) || !array_key_exists('accessToken', $responseArray)) {
            $this->iErrorMessage = "Error: " . ($response ?: "No Response from API");
            return false;
        }

        $this->iAccessToken = $responseArray['accessToken'];
        $this->iTokenExpiration = time() + $this->iTokenExpirationSeconds;

        return $responseArray['accessToken'];
    }

    public function sendProducts($productIdArray) {
        $fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");

		$successfulProductIds = array();
		$errorProductIds = array();
		$tableId = getReadFieldFromId("table_id", "tables", "table_name", "product_data");
		$resultSet = executeQuery("select description, column_name from table_columns join column_definitions using (column_definition_id) where table_id = ?", $tableId);
		$fieldDescriptions = array();
		while ($row = getNextRow($resultSet)) {
			$fieldDescriptions[$row['column_name']] = $row['description'];
		}
		$validGunBrokerPaymentMethods = array("Check", "COD", "Escrow", "PayPal", "CertifiedCheck", "USPSMoneyOrder", "MoneyOrder", "FreedomCoin");

		$additionalPaymentMethods = getPreference("GUNBROKER_ADDITIONAL_PAYMENT_METHODS");
		$paymentMethods = array(
			"VisaMastercard" => true,
			"Amex" => true,
			"Discover" => true
		);
		foreach ($validGunBrokerPaymentMethods as $validGunBrokerPaymentMethod) {
			if (stristr($additionalPaymentMethods, $validGunBrokerPaymentMethod) !== false) {
				$paymentMethods[$validGunBrokerPaymentMethod] = true;
			}
		}
		$productCatalog = new ProductCatalog();
		$productInventoryArray = $productCatalog->getInventoryCounts(false, $productIdArray);
		$whichInventoryString = strtoupper(getPreference("GUNBROKER_INVENTORY_FOR_FIXED_PRICE"));
		$whichInventoryStringParts = explode(" ", $whichInventoryString);
		$whichInventory = array_shift($whichInventoryStringParts);

		$resultSet = executeQuery("select *, (select group_concat(state) from product_restrictions where country_id = 1000 and postal_code is null and state is not null and product_id = products.product_id) restricted_states " .
			"from products join product_data using (product_id) join gunbroker_products using (product_id) where products.product_id in (" . implode(",", $productIdArray) . ") and products.client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if ($row['fixed_price'] > 0) { // Don't allow fixed price listings to be sent for out of stock products
				$productInventoryCount = $productInventoryArray[$row['product_id']]['total'];
				if ($whichInventory != "ALL") {
					$productInventoryCount -= $productInventoryArray[$row['product_id']]['distributor'];
				}
				if ($productInventoryCount <= 0) {
					$errorProductIds[$row['product_id']] = "Out of stock" . ($whichInventory == "ALL" ? "" : " (Distributor inventory is excluded)");
					continue;
				}
			}

			$detailedDescription = $row['header_content'] . "\n" . makeHTML($row['detailed_description']);

			$specifications = array();
			if (!empty($row['product_manufacturer_id'])) {
				$specifications[] = array("field_name" => "specs_product_manufacturer", "field_description" => "Manufacturer", "field_value" => getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $row['product_manufacturer_id']));
			}
			if (!empty($row['product_type_id'])) {
				$specifications[] = array("field_name" => "specs_product_type", "field_description" => "Product Type", "field_value" => getFieldFromId("description", "product_types", "product_type_id", $row['product_type_id']));
			}
			$skipFields = array("product_data_id", "client_id", "product_id", "version", "product_distributor_id", "minimum_price", "manufacturer_advertised_price");

			$dontSendUpc = !empty(CustomField::getCustomFieldData($row['product_id'], "GUNBROKER_IGNORE_UPC_CODE", "PRODUCTS"));
			if ($dontSendUpc) {
				$skipFields[] = "upc_code";
			}
			$productDataRow = getRowFromId("product_data", "product_id", $row['product_id']);
			$dataTable = new DataTable("product_data");
			$foreignKeyList = $dataTable->getForeignKeyList();
			foreach ($productDataRow as $fieldName => $fieldValue) {
				if (in_array($fieldName, $skipFields) || empty($fieldValue)) {
					continue;
				}
				$fieldDescription = $fieldDescriptions[$fieldName];
				if (array_key_exists("product_data." . $fieldName, $foreignKeyList)) {
					$thisFieldValue = "";
					foreach ($foreignKeyList["product_data." . $fieldName]['description'] as $thisDescriptionField) {
						$descriptionFieldValue = getReadFieldFromId($thisDescriptionField, $foreignKeyList["product_data." . $fieldName]['referenced_table_name'],
							$foreignKeyList["product_data." . $fieldName]['referenced_column_name'], $fieldValue);
						$thisFieldValue .= $descriptionFieldValue;
					}
					$fieldValue = $thisFieldValue;
				}
				$specifications[] = array("field_name" => "product_data_" . $fieldName, "field_description" => $fieldDescription, "field_value" => $fieldValue);
			}
			$facetSet = executeReadQuery("select * from product_facet_values join product_facets using (product_facet_id) join product_facet_options using (product_facet_option_id) where product_id = ? and " .
				"internal_use_only = 0 and exclude_details = 0 and inactive = 0 order by sort_order,description", $row['product_id']);
			while ($facetRow = getNextRow($facetSet)) {
				if (empty($facetRow['facet_value']) || $facetRow['facet_value'] == "N/A") {
					continue;
				}
				$specifications[] = array("field_name" => "product_facets_" . strtolower($facetRow['product_facet_code']), "field_description" => $facetRow['description'], "field_value" => $facetRow['facet_value']);
			}
			$customSet = executeReadQuery("select * from custom_fields where client_id = ? and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS') and " .
				"inactive = 0 and internal_use_only = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($customRow = getNextRow($customSet)) {
				$customFieldData = CustomField::getCustomFieldData($row['product_id'], $customRow['custom_field_code'], "PRODUCTS");
				if (!empty($customFieldData)) {
					$specifications[] = array("field_name" => "custom_field_" . $customRow['custom_field_code'], "field_description" => $customRow['form_label'], "field_value" => $customFieldData);
				}
			}
			if (!empty($row['restricted_states'])) {
				$specifications[] = array("field_name" => "restricted_states", "field_description" => "Sale not allowed in these states", "field_value" => $row['restricted_states']);
			}

			if (!empty($specifications)) {
				$detailedDescription .= "<h2>Specifications</h2>\n";
				$detailedDescription .= "<table style='border-collapse: collapse;margin-bottom: 20px;'>\n";
				foreach ($specifications as $specificationInfo) {
					$detailedDescription .= "<tr><td style='padding: 4px 8px; border: 1px solid rgb(180,180,180);'>" . $specificationInfo['field_description'] . "</td><td style='padding: 4px 8px; border: 1px solid rgb(180,180,180);'>" . $specificationInfo['field_value'] . "</td></tr>\n";
				}
				$detailedDescription .= "</table>\n";
			}

			$detailedDescription .= "\n" . $row['footer_content'];
			$domainName = getDomainName();
			$detailedDescription = str_replace('src="/getimage.php', 'style="height: auto; max-width: 95%;" src="' . $domainName . '/getimage.php', $detailedDescription);
			$fflProductTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);

			$productImageUrl = ProductCatalog::getProductImage($row['product_id'], array("no_cache_filename" => true));
			if ($productImageUrl == "/images/empty.jpg") {
				$productImageUrl = "";
			} else {
				if (substr($productImageUrl, 0, 4) != "http") {
					$productImageUrl = $domainName . "/" . ltrim($productImageUrl, "/");
				}
			}
			$premiumFeatures = array();
			if (!empty($row['has_view_counter'])) {
				$premiumFeatures['HasViewCounter'] = true;
			}
			if (!empty($row['is_featured_item'])) {
				$premiumFeatures['IsFeaturedItem'] = true;
			}
			if (!empty($row['is_highlighted'])) {
				$premiumFeatures['IsHighlighted'] = true;
			}
			if (!empty($row['is_show_case_item'])) {
				$premiumFeatures['IsShowCaseItem'] = true;
			}
			if (!empty($row['is_title_boldface'])) {
				$premiumFeatures['IsTitleBoldface'] = true;
			}
			if (!empty($row['is_sponsored_onsite'])) {
				$premiumFeatures['IsSponsoredOnsite'] = true;
			}
			if (!empty($row['scheduled_starting_date'])) {
				$premiumFeatures['ScheduledStartingDate'] = date("Y-m-d\TH:i:s\Z", strtotime($row['scheduled_starting_date']));
			}
			if (!empty($row['subtitle'])) {
				$premiumFeatures['SubTitle'] = $row['subtitle'];
			}
			if (!empty($row['title_color'])) {
				$premiumFeatures['TitleColor'] = $row['title_color'];
			}

			$dataArray = array(
				"AutoAcceptPrice" => $row['auto_accept_price'],
				"AutoRejectPrice" => $row['auto_reject_price'],
				"AutoRelist" => $row['auto_relist'],
				"AutoRelistFixedCount" => ($row['auto_relist'] == "3" ? $row['auto_relist_fixed_count'] : ""),
				"BuyNowPrice" => $row['buy_now_price'],
				"CanOffer" => ($row['can_offer'] ? true : false),
				"CategoryID" => $row['category_identifier'],
				"Condition" => $row['item_condition'],
				"CountryCode" => "US",
				"Description" => $detailedDescription,
				"FixedPrice" => numberFormat($row['fixed_price'], 2, ".", ""),
				"InspectionPeriod" => $row['inspection_period'],
				"IsFFLRequired" => (!empty($fflProductTagLinkId)),
				"ListingDuration" => $row['listing_duration'],
				"MfgPartNumber" => substr($row['model'], 0, 30),
				"PaymentMethods" => $paymentMethods,
				"PostalCode" => substr($GLOBALS['gClientRow']['postal_code'], 0, 5),
				"Prop65Warning" => $row['prop_65_warning'],
				"ExcludedStates" => $row['restricted_states'],
				"Quantity" => $row['quantity'],
				"ReservePrice" => $row['reserve_price'],
				"SerialNumber" => $row['serial_number'],
				"StandardTextID" => $row['standard_text_identifier'],
				"ShippingProfileID" => $row['shipping_profile_identifier'],
				"ShippingClassesSupported" => array(
					"Ground" => true
				),
				"ShippingClassCosts" => array(
					"Ground" => $row['ground_shipping_cost']
				),
				"SKU" => $row['manufacturer_sku'],
				"StartingBid" => $row['starting_bid'],
				"Title" => $row['description'],
				"UseDefaultSalesTax" => true,
				"GTIN" => ($dontSendUpc ? "" : $row['upc_code']),
				"Weight" => $row['weight'],
				"WeightUnit" => $row['weight_unit'],
				"WhoPaysForShipping" => $row['who_pays_for_shipping'],
				"WillShipInternational" => (!empty($row['will_ship_international']))
			);
			if (!empty($premiumFeatures)) {
				$dataArray['PremiumFeatures'] = $premiumFeatures;
			}
			$productImages = ProductCatalog::getProductAlternateImages($row['product_id'], array("no_cache_filename" => true));
			if(!empty($productImageUrl)) {
				array_unshift($productImages, array("url" => $productImageUrl));
			}
			$productImages = array_filter($productImages);
			if (!empty($productImages)) {
				$dataArray['PictureURLs'] = array();
				foreach ($productImages as $thisImage) {
					if (!is_array($thisImage) || empty($thisImage['url'])) {
						addDebugLog("Invalid GunBroker Image for Product ID " . $row['product_id'] . ":" . $thisImage,true);
						continue;
					}
					$dataArray['PictureURLs'][] = $thisImage['url'];
				}
			}
			if (!empty($row['gunbroker_identifier'])) {
				if ($this->updateListing($row['gunbroker_identifier'], $dataArray) === false) {
					$errorProductIds[$row['product_id']] = $this->iErrorMessage ?: "Update error";
				} else {
                    updateFieldById("date_sent", date("Y-m-d"), "gunbroker_products", "product_id", $row['product_id']);
				}
			} else {
                $responseArray = $this->makeRequest(["url_path"=>"Items", "post_fields"=>$dataArray, "send_as_form"=>true]);

                if (!is_array($responseArray)) {
                    $errorProductIds[$row['product_id']] = $this->iErrorMessage ?: "Unknown error posting product ID " . $row['product_id'];
                    $this->iErrorMessage = "";
				} else {
					if (empty($responseArray['links'])) {
						$errorProductIds[$row['product_id']] = $responseArray['userMessage'] ?: "Unknown error";
					} else {
						$successfulProductIds[] = $row['product_id'];
						$gunbrokerIdentifier = $responseArray['links'][0]['title'];
						if ($GLOBALS['gDevelopmentServer'] || !$this->iTestMode) {
                            updateFieldById("gunbroker_identifier", $gunbrokerIdentifier, "gunbroker_products", "product_id", $row['product_id']);
						}
					}
				}
			}
		}
		return array("errors" => $errorProductIds, "success" => $successfulProductIds);
	}

	public function autoUpdateListings($productIds = array()) {
		$productIds = is_array($productIds) ? $productIds : array($productIds);
		$resultSet = executeQuery("select gunbroker_products.*, products.product_code, product_data.upc_code from gunbroker_products join products using (product_id) left join product_data using (product_id) 
			where product_id in (select product_id from products where client_id = ? and inactive = 0 and internal_use_only = 0)"
			. (empty($productIds) ? "" : " and product_id in (" . implode(",", $productIds) . ")"), $GLOBALS['gClientId']);
		$productCatalog = new ProductCatalog();
		$productIdArray = array();
		$productsArray = array();
		$errors = array();
		while ($row = getNextRow($resultSet)) {
			$productsArray[$row['product_id']] = $row;
			$productIdArray[] = $row['product_id'];
		}
        $this->getAllSellerItems();
		$productInventoryArray = $productCatalog->getInventoryCounts(false, $productIdArray);
		$addNewListings = false;
		$pricingStructureId = false;
		if (!empty(getPreference("GUNBROKER_AUTOLIST_PRODUCTS"))) {
			$addNewListings = true;
			$pricingStructureId = getFieldFromId("pricing_structure_id", "pricing_structures", "pricing_structure_code", "GUNBROKER_AUTOLIST_PRICING", "inactive = 0 and internal_use_only = 0");
		}
		if (!empty($pricingStructureId) && $this->iLogging) {
			addDebugLog("Found pricing structure ID " . $pricingStructureId . " for Gunbroker Autolist pricing");
		}
		$whichInventoryString = strtoupper(getPreference("GUNBROKER_INVENTORY_FOR_FIXED_PRICE"));
		$whichInventoryStringParts = explode(" ", $whichInventoryString);
		$whichInventory = array_shift($whichInventoryStringParts);
		if ($this->iLogging) {
			addDebugLog(sprintf("Updating %s GunBroker listings for client %s: Add new listings = %s, GunBroker Pricing Structure ID = %s, Which inventory = %s",
				count($productsArray), $GLOBALS['gClientName'], $addNewListings ? "true" : "false", $pricingStructureId, $whichInventory));
		}
        $gunbrokerProductsTable = new DataTable("gunbroker_products");
        $gunbrokerProductsTable->setSaveOnlyPresent(true);
		foreach ($productsArray as $thisProduct) {
			$productId = $thisProduct['product_id'];
			$logging = $this->iLogging ?: !empty(CustomField::getCustomFieldData($productId, "GUNBROKER_ALWAYS_LOG", "PRODUCTS"));
			$itemId = $thisProduct['gunbroker_identifier'];
			$gunbrokerPrice = false;
            $priceCalculationLog = "";
			$isFixedPrice = !empty($thisProduct['fixed_price']);
			if (empty($itemId)) { // make sure product isn't already listed by UPC and remove any duplicates for fixed price listings
				$itemId = $this->checkListingByUpc($thisProduct['upc_code'], $isFixedPrice, $logging);
                if(empty($itemId) && !empty(getPreference("GUNBROKER_MATCH_BY_SKU"))) {
                    $this->checkListingBySku($thisProduct['product_code'], $isFixedPrice, $logging);
                }
				if (!empty($itemId)) {
					if ($logging) {
						addDebugLog(sprintf("Found existing Gunbroker listing %s for UPC %s (Product ID %s)", $itemId, $thisProduct['upc_code'], $productId));
					}
                    updateFieldById("gunbroker_identifier", $itemId, "gunbroker_products", "gunbroker_product_id", $thisProduct['gunbroker_product_id'],
                        "product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				}
			}
			if (!empty($pricingStructureId)) {
				$resultSet = executeQuery("select * from products left outer join product_data using (product_id) where product_id = ?", $productId);
				$productRow = getNextRow($resultSet);
				$productRow['pricing_structure_id'] = $pricingStructureId;
				$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("product_information" => $productRow));
				$gunbrokerPrice = $salePriceInfo['sale_price'];
                $priceCalculationLog = $productCatalog->getPriceCalculationLog();
			}
			$productInventoryCount = $productInventoryArray[$productId]['total'];
			if ($whichInventory != "ALL") {
				$productInventoryCount -= $productInventoryArray[$productId]['distributor'];
			}
			$productInventoryCount = max($productInventoryCount, 0);

			# make sure itemID is still current (if sold, GB relists the remaining quantity)
			$itemId = $this->updateItemId($itemId);

			if (empty($itemId)) {
				# situation 1 - not yet listed and in stock: list (fixed price and autolist turned on)
				if ($isFixedPrice && $addNewListings && $productInventoryCount > 0) {
					if (!empty($gunbrokerPrice)) {
                        $GLOBALS['gChangeLogNotes'] = $priceCalculationLog;
                        $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["fixed_price"=>$gunbrokerPrice, "quantity"=>$productInventoryCount]]);
                        $GLOBALS['gChangeLogNotes'] = "";
					} else {
                        $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["quantity"=>$productInventoryCount]]);
					}
					$result = $this->sendProducts(array($productId));
					if (!empty($result['success'])) {
						$this->iListingCounts['listed']++;
						if ($logging) {
							addDebugLog(sprintf("Gunbroker listing added: %s (product ID %s)", getFieldFromId("gunbroker_identifier", "gunbroker_products", "gunbroker_product_id", $thisProduct['gunbroker_product_id']), $productId));
						}
					} else {
						// todo: add requires_attention field to gunbroker_products so that error products are not re-sent every time
						foreach ($result['errors'] as $index => $message) {
							$logEntry = sprintf("Error occurred adding Gunbroker listing for product ID %s (%s): %s", $productId, $index, $message);
							if ($logging && stristr($logEntry, $this->iIgnoreError) === false) {
								addDebugLog($logEntry);
							}
							$errors[] = $logEntry;
						}
					}
				}
			} elseif ($productInventoryCount > 0) {
				$itemData = $this->getItemData($itemId);
				if (empty($gunbrokerPrice)) {
					$salePriceInfo = $productCatalog->getProductSalePrice($productId);
					$gunbrokerPrice = $salePriceInfo['sale_price'];
                    $priceCalculationLog = $productCatalog->getPriceCalculationLog();
					if ($logging) {
                        addDebugLog("Gunbroker price calculation: $priceCalculationLog");
					}
				}
				if ($addNewListings && ($productInventoryCount != $thisProduct['quantity'] || (!empty($gunbrokerPrice) && $gunbrokerPrice != $itemData['fixedPrice']))) {
					# situation 2a - autolist clients only: if price or quantity has changed update listing
					$updateArray = array("Quantity" => $productInventoryCount);
					if (!empty($gunbrokerPrice)) {
						$priceChange = floatval($itemData['fixedPrice']) - floatval($gunbrokerPrice);
						$priceChangePercent = $itemData['fixedPrice'] == 0 ? 1 : $priceChange / $itemData['fixedPrice'];
						if($priceChangePercent > .5 && $priceChange > 50) {
							$gunbrokerPrice = false;
							$logEntry = sprintf("Gunbroker listing price update cancelled because of large price change: %s (Product ID %s): %s to %s (%s%%)", $itemId, $productId,
								$itemData['fixedPrice'], $gunbrokerPrice, $priceChangePercent * 100);
							$GLOBALS['gPrimaryDatabase']->logError($GLOBALS['gClientRow']['client_code'] . ": " . $logEntry);
							if ($logging && stristr($logEntry, $this->iIgnoreError) === false) {
								addDebugLog($logEntry);
							}
							$errors[] = $logEntry;
						} elseif($priceChangePercent > .15 && $priceChange > 0) {
							$logEntry = sprintf("Gunbroker listing price changed by %s%%: %s (Product ID %s): %s to %s", round($priceChangePercent * 100,2), $itemId, $productId,
								$itemData['fixedPrice'], $gunbrokerPrice);
                            addDebugLog($logEntry);
						}
						if(!empty($gunbrokerPrice)) {
							$updateArray['FixedPrice'] = $gunbrokerPrice;
						}
					}
					if ($this->updateListing($itemId, $updateArray)) {
						if (!empty($gunbrokerPrice)) {
                            $GLOBALS['gChangeLogNotes'] = $priceCalculationLog;
                            $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["fixed_price"=>$gunbrokerPrice, "quantity"=>$productInventoryCount]]);
                            $GLOBALS['gChangeLogNotes'] = "";
						} else {
                            $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["quantity"=>$productInventoryCount]]);
						}
						$this->iListingCounts['updated']++;
						if ($logging) {
							addDebugLog(sprintf("Gunbroker listing updated with price and quantity: %s (qty %s, price %s) product ID: %s", $itemId, $productInventoryCount, $gunbrokerPrice, $productId));
						}
					} else {
						$logEntry = sprintf("Error occurred updating price and quantity for Gunbroker listing %s (product id %s): %s", $itemId, $productId, $this->iErrorMessage);
						if ($logging && stristr($logEntry, $this->iIgnoreError) === false) {
							addDebugLog($logEntry);
						}
						if (stristr($logEntry, "You are not the seller") !== false || stristr($logEntry, "Quantity cannot be changed on non-fixed-priced items") !== false) {
                            $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["gunbroker_identifier"=>null]]);
						}
						$errors[] = $logEntry;
						$this->iErrorMessage = "";
					}
				} elseif ($isFixedPrice && $productInventoryCount != $thisProduct['quantity']) {
					# situation 2b - listed and still in stock: update quantity (only if fixed price)
					# If in-stock is less than GunBroker, update. If in-stock is greater than GunBroker, do not update if GUNBROKER_INVENTORY_FOR_FIXED_PRICE is [none]
					if($productInventoryCount < $thisProduct['quantity'] || in_array($whichInventory, array("ALL", "LOCAL"))) {
						$updateArray = array("Quantity" => $productInventoryCount);
						if ($this->updateListing($itemId, $updateArray)) {
                            $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["quantity"=>$productInventoryCount]]);
							$this->iListingCounts['updated']++;
							if ($logging) {
								addDebugLog(sprintf("Gunbroker listing updated with new quantity: %s (qty %s) product ID: %s", $itemId, $productInventoryCount, $productId));
							}
						} else {
							$logEntry = sprintf("Error occurred updating quantity for Gunbroker listing %s (product id %s): %s", $itemId, $productId, $this->iErrorMessage);
							if ($logging && stristr($logEntry, $this->iIgnoreError) === false) {
								addDebugLog($logEntry);
							}
							if (stristr($logEntry, "You are not the seller") !== false || stristr($logEntry, "Quantity cannot be changed on non-fixed-priced items") !== false) {
                                $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["gunbroker_identifier"=>null]]);
                        }
							$errors[] = $logEntry;
							$this->iErrorMessage = "";
						}
					}
				}
			} else {
				# situation 3 - listed and no longer in stock: end (any listing)

				$endThisListing = true;
				$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $productId,
					"quantity > 0 and location_id in (select location_id from locations where inactive = 0)");
				if (!empty($productInventoryId)) {
					$endThisListing = false;
				}
				if ($endThisListing) {
					ProductCatalog::getInventoryAdjustmentTypes();

					$inventoryAdjustmentTypeId = $GLOBALS['gInventoryAdjustmentTypeId'];
					$restockAdjustmentTypeId = $GLOBALS['gRestockAdjustmentTypeId'];
					$inventorySet = executeQuery("select * from product_inventories join product_inventory_log using (product_inventory_id) where product_id = ? and location_id in " .
						"(select location_id from locations where inactive = 0) and inventory_adjustment_type_id in (?,?) order by log_time desc", $productId, $inventoryAdjustmentTypeId, $restockAdjustmentTypeId);
					$locationIdArray = array();
					while ($inventoryRow = getNextRow($inventorySet)) {

						# bale out if we get past inventory records more than 48 hours old
						if ($inventoryRow['log_time'] < date("Y-m-d H:i:s",time() - (48 * 3600))) {
							break;
						}

						# if the inventory record is over 8 hours old, only flag the listing to NOT end if the location had a previous record
						if ($inventoryRow['log_time'] < date("Y-m-d H:i:s",time() - (8 * 3600))) {
							if (array_key_exists($inventoryRow['location_id'], $locationIdArray) && $inventoryRow['quantity'] > 0) {
								$endThisListing = false;
								break;
							}

							# Once the first record for a location older than 8 hours has been checked, don't check for that location again
							unset($locationIdArray[$inventoryRow['location_id']]);
						} else {

							# if the inventory record is less than 8 hours old and there is inventory, break out of the loop and don't end the listing
							if ($inventoryRow['quantity'] > 0) {
								$endThisListing = false;
								break;
							}

							# save that we got an inventory record for this location
							$locationIdArray[$inventoryRow['location_id']] = true;
						}
					}
				}

				if ($endThisListing) {
					if ($this->iListingCounts['ended_total'] < $this->iAutoEndMax) {
						if ($this->endListing($itemId)) {
                            $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["gunbroker_identifier"=>null]]);
							$this->iListingCounts['ended_out_of_stock']++;
							if ($logging) {
								addDebugLog(sprintf("Gunbroker listing ended because out of stock: %s (product id %s)", $itemId, $productId));
							}
						} else {
							$logEntry = sprintf("Error occurred ending Gunbroker listing %s (product id %s): %s", $itemId, $productId, $this->iErrorMessage);
							if ($logging) {
								addDebugLog($logEntry);
							}
							if (stristr($logEntry, "You are not the seller") !== false) {
                                $gunbrokerProductsTable->saveRecord(["primary_id"=>$thisProduct['gunbroker_product_id'], "name_values"=>["gunbroker_identifier"=>null]]);
                        }
							$errors[] = $logEntry;
							$this->iErrorMessage = "";
						}
					} else {
						$this->iListingCounts['ended_over_max']++;
					}
				}
			}
		}
		$emailLogId = getFieldFromId("email_log_id", "email_log", "client_id", $GLOBALS['gClientId'],
			'parameters like \'{"subject":"GunBroker Listing Errors"%\' and time_submitted > current_date');
		if (count($errors) > 0 && empty($emailLogId)) {
			sendEmail(array("subject" => "GunBroker Listing Errors", "body" => "<html><body><p>The following errors occurred while listing products on GunBroker:</p><ul><li>" .
				implode("</li><li>", $errors) . "</li></ul></body></html>", "notification_code" => "GUNBROKER_ERRORS"));
		}
		if ($this->iListingCounts['ended_over_max'] > 0) {
			sendEmail(array("subject" => "Large Number of GunBroker Listings Ended", "body" => "<html><body><p>A large number of GunBroker listings were identified as out of stock. The maximum number of listings to be ended at one time was reached.</p><ul><li>" .
				implode("</li><li>", ["Listings Ended: " . $this->iListingCounts['ended_total'], "Additional out of stock listings that would have been ended: " . $this->iListingCounts['ended_over_max']]) . "</li></ul></body></html>", "notification_code" => "GUNBROKER_ERRORS"));
			$errors[] = "Maximum number of listings to end at one time reached: " . $this->iListingCounts['ended_total'] . ". Additional out of stock listings that would have been ended: " . $this->iListingCounts['ended_over_max'];
		}
		$this->iListingCounts['errors'] = $errors;
		return $this->iListingCounts;
	}

    public static function parseResults($listingCounts) {
        return (!empty($listingCounts['listed']) ? "\nListings added: " . $listingCounts['listed'] ?: "0" : "")
        . "\nListings updated with new quantity: " . $listingCounts['updated'] ?: "0"
        . "\nListings ended because out of stock: " . $listingCounts['ended_out_of_stock'] ?: "0"
        . "\nListings ended because of duplicates: " . $listingCounts['ended_duplicate'] ?: "0"
        . "\nTotal listings ended: " . $listingCounts['ended_total'] ?: "0"
            . (!empty($listingCounts['errors']) ? "\nError(s) occurred: " . implode("\n", $listingCounts['errors']) : "");
    }

	private function updateItemId($itemId) {
		$originalItemId = $itemId;
		$gunbrokerProductRow = getRowFromId("gunbroker_products", "gunbroker_identifier", $originalItemId);
		if (empty($gunbrokerProductRow)) { // listing was already updated
			return false;
		}
		$logging = $this->iLogging ?: !empty(CustomField::getCustomFieldData($gunbrokerProductRow['product_id'], "GUNBROKER_ALWAYS_LOG", "PRODUCTS"));
		$ignoreFields = array("description" => "", "descriptiononly" => "", "itemcharacteristics" => "", "standardtext" => "", "links" => "");
		$relistedAsItemId = -1;
		while (intval($relistedAsItemId) != 0) {
			$itemData = $this->getItemData($itemId);
			if (empty($itemData['itemID'])) { // don't update if an error occurred
				if(is_array($itemData) && endsWith($this->iErrorMessage, "Not Found")) {
					if ($logging) {
						addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) not found; removing Gunbroker ID from listing", $originalItemId, $gunbrokerProductRow['product_id']));
						addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) itemData: %s", $originalItemId, $gunbrokerProductRow['product_id'], jsonEncode(array_diff_key(array_change_key_case($itemData), $ignoreFields))));
					}
					executeQuery("update gunbroker_products set gunbroker_identifier = null where gunbroker_product_id = ?", $gunbrokerProductRow['gunbroker_product_id']);
                    $this->iErrorMessage = "";
				}
				break;
			}
			if (!empty($this->iGunbrokerUserId) && $this->iGunbrokerUserId != $itemData['seller']['userID']) {
				if ($logging) {
					addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) is incorrectly linked from a different seller; removing Gunbroker ID from listing", $originalItemId, $gunbrokerProductRow['product_id']));
					addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) itemData: %s", $originalItemId, $gunbrokerProductRow['product_id'], jsonEncode(array_diff_key(array_change_key_case($itemData), $ignoreFields))));
				}
				executeQuery("update gunbroker_products set gunbroker_identifier = null where gunbroker_product_id = ?", $gunbrokerProductRow['gunbroker_product_id']);
				return false;
			}
			$relistedAsItemId = $itemData['relistedAsItemID'];
			$itemId = $relistedAsItemId ?: $itemId;
		}
		if ($relistedAsItemId == 0 && !empty($itemData['endingDate']) && time() > strtotime($itemData['endingDate'])) { // listing is ended
			if ($logging) {
				addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) has ended; removing Gunbroker ID from listing", $originalItemId, $gunbrokerProductRow['product_id']));
				addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) itemData: %s", $originalItemId, $gunbrokerProductRow['product_id'], jsonEncode(array_diff_key(array_change_key_case($itemData), $ignoreFields))));
			}
			executeQuery("update gunbroker_products set gunbroker_identifier = null where gunbroker_product_id = ?", $gunbrokerProductRow['gunbroker_product_id']);
			$itemId = false;
		} elseif ($originalItemId != $itemId) {
			if ($logging) {
				addDebugLog(sprintf("Gunbroker listing %s (Product ID %s) has been relisted; changing Gunbroker ID to %s",
					$originalItemId, $gunbrokerProductRow['product_id'], $itemId));
			}
			executeQuery("update gunbroker_products set gunbroker_identifier = ? where gunbroker_product_id = ?", $itemId, $gunbrokerProductRow['gunbroker_product_id']);;
		}
		return $itemId;
	}

	public function checkListing($itemId, $programLogId = "") {
		$originalItemId = $itemId;
		$itemId = $this->updateItemId($itemId);
		if ($originalItemId != $itemId) {
			$programLogId = addProgramLog(sprintf("Gunbroker listing %s updated to %s because product was relisted.", $originalItemId, $itemId), $programLogId);
		}
		$gunbrokerProductRow = getRowFromId("gunbroker_products", "gunbroker_identifier", $itemId);
		if (!empty($gunbrokerProductRow)) {
			$productCatalog = new ProductCatalog();
			$inventoryCounts = $productCatalog->getInventoryCounts(true, array($gunbrokerProductRow['product_id']));
			if ($inventoryCounts[$gunbrokerProductRow['product_id']] <= 0) {
				if ($this->endListing($itemId)) {
					executeQuery("update gunbroker_products set gunbroker_identifier = null where gunbroker_product_id = ?", $gunbrokerProductRow['gunbroker_product_id']);
					$this->iListingCounts['ended_out_of_stock']++;
					addProgramLog(sprintf("Gunbroker listing %s ended because product is out of stock.", $itemId), $programLogId);
				}
			}
		}
	}

    private function getAllSellerItems() {
        if (empty($this->iGunbrokerUserId)) {
            return false;
        }
        if(!empty($this->iItemData) && is_array($this->iItemData)) {
            return true;
        }
        $pageSize = 300;
        $pageIndex = 1;
        $items = array();
        while($pageIndex < 100) {
            $result = $this->makeRequest(['url_path' => "Items?IncludeSellers=" . $this->iGunbrokerUserId . "&PageSize=$pageSize&pageIndex=$pageIndex"]);
            if(empty($result['results'])) {
                break;
            }
            $items = array_merge($items, $result['results']);
            $pageIndex++;
        }
        $this->iUpcIndex = array();
        $this->iSkuIndex = array();
        foreach($items as $thisItem) {
            $thisItem = fixFieldCase($thisItem);
            $itemId = $thisItem['itemid'];
            $upc = $thisItem['gtin'];
            $sku = makeCode($thisItem['sku']);
            $this->iItemData[$itemId] = $thisItem;
            if(!empty($upc)) {
                if(array_key_exists($upc, $this->iUpcIndex)) {
                    $this->iUpcIndex[$upc][] = $itemId;
                } else {
                    $this->iUpcIndex[$upc] = [$itemId];
                }
            }
            if(!empty($sku)) {
                if(array_key_exists($sku, $this->iSkuIndex)) {
                    $this->iSkuIndex[$sku][] = $itemId;
                } else {
                    $this->iSkuIndex[$sku] = [$itemId];
                }
            }
        }
        return true;
    }

	public function checkListingByUpc($upcCode, $endDuplicates, $logging = false) {
		$upcCode = trim($upcCode);
		if (empty($upcCode)) {
			return false;
		}
		if (empty($this->iGunbrokerUserId)) {
			return false;
		}
		$logging = $logging || $this->iLogging;

        if($this->getAllSellerItems()) {
            $currentItemIds = $this->iUpcIndex[$upcCode];
            $currentItemIds = $currentItemIds ?: array();
            foreach($currentItemIds as $index=>$itemId) {
                if(empty($this->iItemData[$itemId]['isFixedPrice'])) {
                    unset($currentItemIds[$index]);
                }
            }
        } else {
            $curlUrl = sprintf("Items?UPC=%s&IncludeSellers=%s", $upcCode, $this->iGunbrokerUserId);
            $result = $this->makeRequest(["url_path" => $curlUrl]);

            if (empty($result['results']) || !is_array($result['results'])) {
                return false;
            }
            $currentItemIds = array();
            foreach ($result['results'] as $thisResult) {
                if ($thisResult['gtin'] != $upcCode || empty($thisResult['isFixedPrice'])) {
                    continue;
                }
                $currentItemIds[] = $thisResult['itemID'];
            }
        }
		sort($currentItemIds);
		if ($endDuplicates) {
			while (count($currentItemIds) > 1) {
				$thisItemId = array_shift($currentItemIds);
				if ($this->endListing($thisItemId)) {
					$this->iListingCounts['ended_duplicate']++;
					if ($logging) {
						addDebugLog("Gunbroker item " . $thisItemId . " ended because it is a duplicate (UPC " . $upcCode . ")");
					}
				} else {
					if ($logging) {
						addDebugLog("Gunbroker item " . $thisItemId . " is a duplicate but ending it automatically failed: " . $this->iErrorMessage);
					}
				}
			}
		}
		return array_shift($currentItemIds);
	}

    public function checkListingBySku($productCode, $endDuplicates, $logging = false) {
        $productCode = trim($productCode);
        if (empty($productCode)) {
            return false;
        }
        if (empty($this->iGunbrokerUserId)) {
            return false;
        }
        $logging = $logging || $this->iLogging;

        if($this->getAllSellerItems()) {
            $currentItemIds = $this->iSkuIndex[$productCode];
            $currentItemIds = $currentItemIds ?: array();
            foreach($currentItemIds as $index=>$itemId) {
                if(empty($this->iItemData[$itemId]['isFixedPrice'])) {
                    unset($currentItemIds[$index]);
                }
            }
        }
        sort($currentItemIds);
        if ($endDuplicates) {
            while (count($currentItemIds) > 1) {
                $thisItemId = array_shift($currentItemIds);
                if ($this->endListing($thisItemId)) {
                    $this->iListingCounts['ended_duplicate']++;
                    if ($logging) {
                        addDebugLog("Gunbroker item " . $thisItemId . " ended because it is a duplicate (SKU " . $productCode . ")");
                    }
                } else {
                    if ($logging) {
                        addDebugLog("Gunbroker item " . $thisItemId . " is a duplicate but ending it automatically failed: " . $this->iErrorMessage);
                    }
                }
            }
        }
        return array_shift($currentItemIds);
    }

	private function endListing($itemId) {
		if(!$this->iAutoEndListings) {
			$this->iErrorMessage = "GunBroker listing " .$itemId . " would have been ended, but ending listings is disabled by preference.";
			return false;
		}
		if($this->iListingCounts['ended_total'] >= $this->iAutoEndMax) {
			$this->iErrorMessage = "GunBroker listing " .$itemId . " would have been ended, but the maximum number of listings have already been ended.";
			$this->iListingCounts['ended_over_max']++;
			return false;
		}

        $result = $this->makeRequest(["url_path"=>"Items/" . $itemId,
            "verb"=>"DELETE",
            "check_gb_status_code"=>true]);

        if ($result === false) {
            $this->iErrorMessage = $this->iErrorMessage ?: "Error ending listing " . $itemId;
			return false;
        } elseif (is_array($result) && !array_key_exists("gbStatusCode", $result)) {
            $this->iErrorMessage = $result['userMessage'] ?: "Error ending listing " . $itemId;
			return false;
		}

		$this->iListingCounts['ended_total']++;
        return true;
	}

	private function updateListing($itemId, $dataArray = array()) {
		if (!empty($dataArray['FixedPrice'])) {
			$dataArray['FixedPrice'] = numberFormat($dataArray['FixedPrice'], 2, ".", "");
		}

        return $this->makeRequest(["url_path"=>"Items/" . $itemId,
            "post_fields"=>$dataArray,
            "verb"=>"PUT"]);
	}

	public function updateOrder($orderNumber, $flags) {
        $parameters = array("url_path" => "Orders/" . $orderNumber . "/Flags",
            "post_fields" => $flags
        );
        return $this->makeRequest($parameters);
	}

	public function updateOrderShipping($orderNumber, $orderShipmentRow) {

		$shippingCarrier = getFieldFromId("description", "shipping_carriers", "shipping_carrier_id", $orderShipmentRow['shipping_carrier_id']);
        $shippingCarrier = strtoupper($shippingCarrier ?: $orderShipmentRow['carrier_description']);

        if(startsWith($shippingCarrier,"FDX") || startsWith($shippingCarrier,"FEDEX")) {
            $gunBrokerCarrier = 1;
        } elseif(startsWith($shippingCarrier,"UPS")) {
            $gunBrokerCarrier = 2;
        } elseif(startsWith($shippingCarrier,"USPS")) {
            $gunBrokerCarrier = 3;
        }
		if (empty($gunBrokerCarrier)) {
            if($this->iLogging) {
                addDebugLog("Unable to update tracking for GunBroker order $orderNumber: cannot identify shipping carrier '$shippingCarrier'");
            }
			return false;
		}
		$data = array("TrackingNumber" => $orderShipmentRow['tracking_identifier'], "Carrier" => $gunBrokerCarrier);

        $parameters = array("url_path"=>"Orders/" . $orderNumber . "/Shipping",
            "verb"=>"PUT",
            "post_fields"=>$data);

        return $this->makeRequest($parameters);
    }


	private function setUserId() {

        $result = $this->makeRequest(['url_path'=>"Users/AccountInfo"]);

        if (empty($result)) {
            $this->iErrorMessage = $this->iErrorMessage ?: "Error retrieving User ID";
			return false;
		}
        $this->iGunbrokerUserId = $result['userSummary']['userID'];

		return true;
	}

	public function getCategories() {

        $pageIndex = 1;
        $results = array();
        while ($pageIndex < 10) {
            $result = $this->makeRequest(['url_path' => "Categories?ShowOnlyFirstLevelSubCategories=true&PageSize=300&pageIndex=$pageIndex"]);

            if (empty($result)) {
                $this->iErrorMessage = $this->iErrorMessage ?: "Error getting categories";
                return false;
            }
            if(empty($result['results'])) {
                break;
            }

            $results = array_merge($results, $result['results']);

            $pageIndex++;
        }
		$categories = $this->getCategoryResults($results);
		usort($categories, array($this, "sortCategories"));
		return $categories;
	}

	private function getCategoryResults($results) {
		$thisResult = array();
		foreach ($results as $thisKey => $thisValue) {
			if (!is_array($thisValue)) {
				continue;
			}
			if (array_key_exists("categoryId", $thisValue) || array_key_exists("categoryID", $thisValue)) {
				$categoryId = $thisValue['categoryId'];
				if (empty($categoryId)) {
					$categoryId = $thisValue['categoryID'];
				}
				if (array_key_exists("hasSubCategories", $thisValue) && !$thisValue['hasSubCategories']) {
					$thisResult[] = array("category_id" => $categoryId, "description" => $thisValue['categoryName']);
				}
				if (array_key_exists("subCategories", $thisValue) && is_array($thisValue['subCategories'])) {
					$thisResult = array_merge($thisResult, $this->getCategoryResults($thisValue['subCategories']));
				}
			}
		}
		return $thisResult;
	}

	public function getOrder($orderId) {

        return $this->makeRequest(['url_path'=>"Orders/" . $orderId]);

	}

	public function getOrders($parameters = array()) {

		if (empty($parameters['PageSize'])) {
			$parameters['PageSize'] = 25;
		}
		if (empty($parameters['PageIndex'])) {
			$parameters['PageIndex'] = 1;
		}
        $url = "OrdersSold?";
		$urlParameters = "";
		foreach ($parameters as $parameterName => $parameterValue) {
			$urlParameters .= (empty($urlParameters) ? "" : "&") . $parameterName . "=" . $parameterValue;
		}
		$url .= $urlParameters;

        $result = $this->makeRequest(["url_path"=>$url]);

        if($result === false) {
            $this->iErrorMessage = $this->iErrorMessage ?: "Error retrieving orders";
			return false;
		}
        $orders = $result['results'];

		$returnArray = array();

		foreach ($orders as $thisOrder) {
            $result = $this->makeRequest(['url_path'=>"Orders/" . $thisOrder['orderID']]);
			if (!is_array($result)) {
                $result = array("error" => $this->iErrorMessage ?: "Error retrieving order " . $thisOrder['orderID']);
			}
			$thisOrder = array_merge($thisOrder, $result);
			$returnArray[] = $thisOrder;
		}

		return $returnArray;
	}

	public function getUserContactInfo($userId) {

        return $this->makeRequest(["url_path"=>"Users/ContactInfo?UserID=" . $userId]);

	}

	public function getItemData($itemId, $forceRefresh = false) {
		if(!is_array($itemId) && array_key_exists($itemId,$this->iItemData) && !empty($this->iItemData[$itemId]) && !$forceRefresh) {
			if($this->iLogging) {
				addDebugLog("GunBroker getItemData: using saved data for Item ID: " . $itemId);
			}
			return $this->iItemData[$itemId];
		}

		if (is_array($itemId)) {
            $curlUrl = "Items?itemIds=" . implode(",", $itemId);
			if($this->iLogging) {
				addDebugLog("GunBroker getItemData: calling API for Item IDs: " . implode(",", $itemId));
			}
		} else {
            $curlUrl = "Items/" . $itemId;
			if($this->iLogging) {
				addDebugLog("GunBroker getItemData: calling API for Item ID: " . $itemId);
			}
		}
        $result = $this->makeRequest(['url_path'=>$curlUrl]);

		if (!is_array($result)) {
			$result = array();
		}
		if(is_array($itemId)) {
			if(!array_key_exists("itemid", $result) && endsWith($this->iErrorMessage, "Not Found")) {
				// If one item ID is not found, the whole call will fail; remove the offending item and try again
				$parts = explode(" ",$this->iErrorMessage);
				$index = array_search($parts[1], $itemId);
				if($index !== false) {
					unset($itemId[$index]);
                    $this->iErrorMessage = "";
					return $this->getItemData($itemId);
				} else {
					return false;
				}
			}
			foreach ($result as $thisItem) {
				if(is_array($thisItem)) {
					$this->iItemData[$thisItem['itemid']] = $thisItem;
				}
			}
		} else {
			$this->iItemData[$itemId] = $result;
		}
		return $result;
	}

	private function sortCategories($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description'] ? 1 : -1);
	}
}
