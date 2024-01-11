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

const FLP_PROD_URL = 'https://firearmslegalprotection.my.salesforce.com/services/data/v46.0';
const FLP_PROD_AUTH_URL = 'https://login.salesforce.com/services/oauth2/token';
const FLP_PROD_CI = '3MVG9Kip4IKAZQEU59mRbcMzaE4xVCy4huLHpG2W3OQzIcMr4TjXBP9Blatk2hADrffT.YaPLVdeJ4U7W6XsF';
const FLP_PROD_CS = 'E0750BEED3E3FC13CCF4FACA1433998364BA3C029B64AF1D31DA6B4DBEB944B1';
const FLP_PROD_UN = 'apiuser@risktheory.com';
const FLP_PROD_PW = 'Sjhi-egv2-FLPSF123dRLE5V1hoDqMVElXre3YBetf';
const FLP_TEST_URL = 'https://firearmslegalprotection--flpsandbox.sandbox.my.salesforce.com/services/data/v46.0';
const FLP_TEST_AUTH_URL = 'https://test.salesforce.com/services/oauth2/token';
const FLP_TEST_CI = '3MVG99VEEJ_Bj3.6JzXi0fRRDU0V671RMAwuINYUcRUdqFzx9KmN87WLneLSsL0c9oGRADe6sc11lFOrawGY3';
const FLP_TEST_CS = 'EA96F709B4DAC72065093991BF3F36FC4D9E76C8F691E106355D55BA729E0435';
const FLP_TEST_UN = 'apiuser@risktheory.com.flpsandbox';
const FLP_TEST_PW = 'Sjhi-egv2-FLPSF123';
// UPC 727785385091 obtained from Speedy Barcodes specifically for FLP 2023-04-05
const FLP_UPC = '727785385091';
const FLP_LATEST_VERSION = 3;


class FirearmsLegalProtection {
	private $iAuthUrl;
	private $iApiUrl;
	private $iErrorMessage;
	private $iAccessToken = "";
	private $iPartnerIdentifier;
	private $iLogging;
    private $iCredentials;

	public function __construct() {
		if ($GLOBALS['gDevelopmentServer']) {
			$this->iApiUrl = FLP_TEST_URL;
			$this->iAuthUrl = FLP_TEST_AUTH_URL;
            $this->iCredentials['ci'] = FLP_TEST_CI;
            $this->iCredentials['cs'] = FLP_TEST_CS;
			$this->iCredentials['un'] = FLP_TEST_UN;
			$this->iCredentials['pw'] = FLP_TEST_PW;
		} else {
			$this->iApiUrl = FLP_PROD_URL;
			$this->iAuthUrl = FLP_PROD_AUTH_URL;
            $this->iCredentials['ci'] = FLP_PROD_CI;
            $this->iCredentials['cs'] = FLP_PROD_CS;
            $this->iCredentials['un'] = FLP_PROD_UN;
			$this->iCredentials['pw'] = FLP_PROD_PW;
		}
		$this->iPartnerIdentifier = getPreference("FLP_PARTNER_ID");
        $this->iLogging = !empty(getPreference("LOG_FLP"));
    }

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	private function getAccessToken() {
		$curl = curl_init();

		$data = array(
			'username' => $this->iCredentials['un'],
			'password' => $this->iCredentials['pw'],
			'client_id' => $this->iCredentials['ci'],
			'client_secret' => $this->iCredentials['cs'],
			'grant_type' => 'password'
		);

		$headers = array(
			'Content-Type: application/x-www-form-urlencoded'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->iAuthUrl,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		$err = curl_error($curl);
		if ($this->iLogging) {
			$logEntry = "FLP Token request: " . http_build_query($data) .
				"\nFLP Token response: " . $result;
			$logEntry .= (empty($err) ? "" : "\nFLP Token Error: " . $err);
			addDebugLog($logEntry);
		}
		if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
			$this->iErrorMessage = ($err ?: "Error getting access token") . ":" . $result;
			return false;
		}
		curl_close($curl);
		$resultArray = json_decode($result, true);
		if (!empty($resultArray['access_token'])) {
			$this->iAccessToken = $resultArray['access_token'];
			return true;
		} else {
			$this->iErrorMessage = "Error getting access token";
			return false;
		}
	}

	public function postApi($apiMethod, $data, $verb = "POST") {
		if (!$this->getAccessToken()) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Error getting access token";
		}

		if (!is_array($data)) {
			$data = array($data);
		}
		$jsonData = json_encode($data);

		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->iAccessToken,
			'Accept: application/json,*/*'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => rtrim($this->iApiUrl, "/") . "/" . $apiMethod,
			CURLOPT_CUSTOMREQUEST => $verb,
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		$err = curl_error($curl);
		if ($this->iLogging) {
			addDebugLog("FLP request: " . $info['url']
				. "\nFLP Data: " . (strlen($jsonData) > 500 ? substr($jsonData, 0, 500) . "..." : $jsonData)
				. "\nFLP Result: " . $result
				. (empty($err) ? "" : "\nFLP Error: " . $err)
				. "\nFLP HTTP Status: " . $info['http_code']);
		}

		if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200, 201, 202, 204)))) {
			$this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $result . ":" . $jsonData;
			return false;
		}
		curl_close($curl);
		return json_decode($result, true);
	}

	private function getApi($apiMethod, $data = array(), $refresh = false) {
		if (!$this->getAccessToken()) {
			$this->iErrorMessage = $this->iErrorMessage ?: "Error getting access token";
		}

		if (!is_array($data)) {
			$data = array($data);
		}
		$queryParams = http_build_query($data);

		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->iAccessToken,
			'Accept: application/json,*/*'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => rtrim($this->iApiUrl, "/") . "/" . $apiMethod . (empty($queryParams) ? "" : "?" . $queryParams),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		$err = curl_error($curl);
		if ($this->iLogging) {
			addDebugLog("FLP request: " . $info['url']
				. "\nFLP Result: " . $result
				. (empty($err) ? "" : "\nFLP Error: " . $err)
				. "\nFLP HTTP Status: " . $info['http_code']);
		}

		if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200, 201, 202, 204)))) {
			$this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $result . ":" . $queryParams;
			return false;
		}

		curl_close($curl);
		return json_decode($result, true);
	}

	public function logPurchase($orderId) {
		if (empty($this->iPartnerIdentifier)) {
			$this->iErrorMessage = "Partner ID is not set.  Do this in Client Preferences.";
			return false;
		}
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $orderId, "product_id in (select product_id from products where product_code = 'FLP')");
		if (empty($orderItemId)) {
			$this->iErrorMessage = "FLP purchase not found.";
			return false;
		}
		$productAddonValue = getFieldFromId("description", "product_addons", "product_addon_id",
			getFieldFromId("product_addon_id", "order_item_addons", "order_item_id", $orderItemId));
		$trainingDate = CustomField::getCustomFieldData($orderItemId, "FLP_TRAINING_COURSE_DATE", "ORDER_ITEMS");
		$agreeToTerms = (empty(CustomField::getCustomFieldData($orderItemId, "FLP_AGREE_TO_TERMS", "ORDER_ITEMS")) ? 0 : 1);
		$stateName = CustomField::getCustomFieldData($orderItemId, "FLP_STATE_OF_RESIDENCE", "ORDER_ITEMS");

		$data = array(
			"First_Name__c" => $contactRow['first_name'],
			"Last_Name__c" => $contactRow['last_name'],
			"Email__c" => $contactRow['email_address'],
			"Phone__c" => $orderRow['phone_number'],
			"Residence_State__c" => $stateName,
			"PartnerId__c" => $this->iPartnerIdentifier,
			"Firearms_training_course_taken__c" => $productAddonValue,
			"Traning_Course_Completed_Date__c" => date("Y-m-d", strtotime($trainingDate)),
			"Agreed_Term_and_Conditions__c" => $agreeToTerms
		);
		$result = $this->postApi("sobjects/Retail_Sold_Upload__c", $data);
		return $result['success'] == 'true';
	}

	public static function needsSetup() {
        $preferenceArray = array(
            array('preference_code'=>'FLP_PARTNER_ID', 'description'=>'FLP Partner ID', 'data_type'=>'varchar','preference_group'=>'INTEGRATION_SETTINGS',
                'detailed_description'=>'Partner ID used for online purchases of FLP in coreFORCE'),
            array('preference_code'=>'FLP_AFFILIATE_LINK', 'description'=>'FLP Affiliate Link', 'data_type'=>'varchar','preference_group'=>'INTEGRATION_SETTINGS',
                'detailed_description'=>'Affiliate link used for purchases on the FLP website for clients in insurance states'),
            array('preference_code'=>'LOG_FLP', 'description'=>'Log API calls to FLP', 'data_type'=>'tinyint','temporary_setting'=>1),
            array('preference_code'=>'FLP_VERSION', 'description'=>'FLP Version', 'data_type'=>'int',
                'detailed_description'=>'Version of FLP currently set up. If the current version is higher than the saved version, the "Update FLP" button will be available.')
        );
        setupPreferences($preferenceArray);
        $productId = getFieldFromId("product_id", "products", "product_code", "FLP", "client_id = ?", $GLOBALS['gClientId']);
		$customFieldData = CustomField::getCustomFieldData($productId, "EXTERNAL_SUBSCRIPTION_CODE", "PRODUCTS");
		if (empty($productId) || $customFieldData != "FirearmsLegalProtection") {
			return "Set Up FLP";
		}
        $currentVersion = getPreference("FLP_VERSION");
        if(empty($currentVersion) || $currentVersion < FLP_LATEST_VERSION) {
            return "Update FLP";
        }
		return false;
	}

	public static function setup() {

		# 1. Make sure a category exists for this product
		$flpCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", "FLP");
		if (empty($flpCategoryId)) {
			$insertSet = executeQuery("insert into product_categories (client_id, product_category_code, description) values (?,'FLP', 'Firearms Legal Protection')", $GLOBALS['gClientId']);
			$flpCategoryId = $insertSet['insert_id'];
		}

		# 2. import the current product from the Catalog; if it already exists, merge the old one with the current one
		$existingProductRow = getRowFromId("products", "product_code", "FLP", "client_id = ?", $GLOBALS['gClientId']);
		if (!empty($existingProductRow)) {
            executeQuery("update products set product_code = ? where product_id = ?", 'FLP_' . getRandomString(8), $existingProductRow['product_id']);
            executeQuery("update product_data set upc_code = null where product_id = ?", $existingProductRow['product_id']);
        }
        $returnArray = ProductCatalog::importProductFromUPC(FLP_UPC);
        if(!empty($returnArray['error_message'])) {
            return $returnArray;
        }
        $productRow = getRowFromId("products", "product_code", "FLP", "client_id = ?", $GLOBALS['gClientId']);

        # product type, not taxable, and non-inventory item are not imported from the Catalog. Set them here.
        executeQuery("update products set not_taxable = 1, non_inventory_item = 1 where product_id = ?",
            $productRow['product_id']);

        $returnArray = self::getFullProductInformation(FLP_UPC);
        if(!empty($returnArray['error_message'])) {
            return $returnArray;
        }

        $termsAndConditionsContent = $returnArray['notes'];

        foreach ($returnArray['product_prices'] as $productPriceRow) {
            $productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", $productPriceRow['product_price_type_code']);
            executeQuery("insert into product_prices (product_id, product_price_type_id, price) values (?,?,?)",
                $productRow['product_id'], $productPriceTypeId, $productPriceRow['price']);
        }
        foreach ($returnArray['product_videos'] as $productVideoRow) {
            $mediaServiceId = getFieldFromId("media_service_id", "media_services", "media_service_code", $productVideoRow['media_service_code']);
            if (!empty($productVideoRow['image_id'])) {
                $imageUrl = "https://shootingsports.coreware.com/getimage.php?id=" . $productVideoRow['image_id'];
                $imageContents = file_get_contents($imageUrl);
                $imageId = createImage(array("extension" => "jpg", "file_content" => $imageContents, "name" => $productVideoRow['video_identifier'] . ".jpg",
                    "description" => $productVideoRow['description'], "detailed_description" => $productVideoRow['detailed_description']));
            } else {
                $imageId = '';
            }
            $insertSet = executeQuery("insert into media (client_id, media_service_id, description, alternate_title, subtitle, full_name, detailed_description, link_name, content, " .
                "meta_keywords, link_url, video_identifier, image_id) values (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                $GLOBALS['gClientId'],
                $mediaServiceId,
                $productVideoRow['description'],
                $productVideoRow['alternate_title'],
                $productVideoRow['subtitle'],
                $productVideoRow['full_name'],
                $productVideoRow['detailed_description'],
                $productVideoRow['link_name'],
                $productVideoRow['content'],
                $productVideoRow['meta_keywords'],
                $productVideoRow['link_url'],
                $productVideoRow['video_identifier'],
                $imageId);
            $mediaId = $insertSet['insert_id'];
            executeQuery("insert into product_videos (product_id, description, media_id) values (?,?,?)", $productRow['product_id'],
                $productVideoRow['product_video_description'], $mediaId);
        }
        foreach ($returnArray['product_custom_fields'] as $productCustomFieldRow) {
            $customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", $productCustomFieldRow['custom_field_code']);
            if (empty($customFieldId)) {
                $customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", $productCustomFieldRow['custom_field_type_code']);
                $insertSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label) values (?,?,?,?,?)",
                    $GLOBALS['gClientId'],
                    $productCustomFieldRow['custom_field_code'],
                    $productCustomFieldRow['description'],
                    $customFieldTypeId,
                    $productCustomFieldRow['form_label']);
                $customFieldId = $insertSet['insert_id'];
                foreach ($productCustomFieldRow['custom_field_controls'] as $customFieldControlRow) {
                    executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?,?,?)",
                        $customFieldId, $customFieldControlRow['control_name'], $customFieldControlRow['control_value']);
                }
                foreach ($productCustomFieldRow['custom_field_choices'] as $customFieldChoiceRow) {
                    executeQuery("insert into custom_field_choices (custom_field_id, key_value, description) values (?,?,?)",
                        $customFieldId, $customFieldChoiceRow['key_value'], $customFieldChoiceRow['description']);
                }
            }
            executeQuery("insert into product_custom_fields (product_id, custom_field_id) values (?,?)", $productRow['product_id'], $customFieldId);
        }
        foreach ($returnArray['custom_fields'] as $customFieldRow) {
            $customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", $customFieldRow['custom_field_code']);
            if (empty($customFieldId)) {
                $customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", $customFieldRow['custom_field_type_code']);
                $insertSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label, internal_use_only) values (?,?,?,?,?,?)",
                    $GLOBALS['gClientId'],
                    $customFieldRow['custom_field_code'],
                    $customFieldRow['description'],
                    $customFieldTypeId,
                    $customFieldRow['form_label'],
                    $customFieldRow['internal_use_only']);
                $customFieldId = $insertSet['insert_id'];
                foreach ($customFieldRow['custom_field_controls'] as $customFieldControlRow) {
                    executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?,?,?)",
                        $customFieldId, $customFieldControlRow['control_name'], $customFieldControlRow['control_value']);
                }
                foreach ($customFieldRow['custom_field_choices'] as $customFieldChoiceRow) {
                    executeQuery("insert into custom_field_choices (custom_field_id, key_value, description) values (?,?,?)",
                        $customFieldId, $customFieldChoiceRow['key_value'], $customFieldChoiceRow['description']);
                }
            }
            executeQuery("insert into custom_field_data (primary_identifier, custom_field_id, integer_data, number_data, text_data, date_data) values (?,?,?,?,?,?)",
                $productRow['product_id'],
                $customFieldId,
                $customFieldRow['integer_data'],
                $customFieldRow['number_data'],
                $customFieldRow['text_data'],
                $customFieldRow['date_data']);
        }
        foreach ($returnArray['product_addons'] as $productAddonRow) {
            executeQuery("insert into product_addons (product_id, description, group_description, manufacturer_sku, maximum_quantity, sale_price, internal_use_only, inactive) values (?,?,?,?,?,?,?,?)",
                $productRow['product_id'],
                $productAddonRow['description'],
                $productAddonRow['group_description'],
                $productAddonRow['manufacturer_sku'],
                $productAddonRow['maximum_quantity'],
                $productAddonRow['sale_price'],
                $productAddonRow['internal_use_only'],
                $productAddonRow['inactive']);
        }
		if(!empty($existingProductRow)) {
            $result = ProductCatalog::mergeProducts($productRow['product_id'], $existingProductRow['product_id']);
            if($result !== true) {
                $returnArray['error_message'] = $result['results'];
            }
        }

		if (empty($returnArray['error_message'])) {
			# 3. Make sure the custom field EXTERNAL_SUBSCRIPTION_CODE exists.
			$productsCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "PRODUCTS");
			$externalSubscriptionCustomField = CustomField::getCustomFieldByCode("EXTERNAL_SUBSCRIPTION_CODE", "PRODUCTS");
			if (empty($externalSubscriptionCustomField)) {
				$resultSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label, internal_use_only) " .
					"values (?,'EXTERNAL_SUBSCRIPTION_CODE', 'External Subscription Code',?, 'External Subscription Code', 1)", $GLOBALS['gClientId'], $productsCustomFieldTypeId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] .= $resultSet['sql_error'];
				} else {
					$externalSubscriptionCustomFieldId = $resultSet['insert_id'];
					executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) " .
						"values (?,'data_type','varchar')", $externalSubscriptionCustomFieldId);
				}
			}

			# 4. Set the custom field value for the product
            CustomField::setCustomFieldData($productRow['product_id'], "EXTERNAL_SUBSCRIPTION_CODE", "FirearmsLegalProtection", "PRODUCTS");

			# 5. Make sure the product is in the category
			executeQuery("insert ignore into product_category_links (product_id, product_category_id) values (?,?)", $productRow['product_id'], $flpCategoryId);

            # 6. Create the Terms and Conditions page
            $pageCode = $GLOBALS['gClientRow']['client_code'] . '_FLP_TERMS_AND_CONDITIONS';
            $pageId = $GLOBALS['gAllPageCodes'][$pageCode];
            if(empty($pageId)) {
                $templateId = getFieldFromId("template_id", "pages", "link_name", "product-details");
                $insertSet = executeQuery("insert ignore into pages (client_id, page_code, description, date_created, creator_user_id, link_name, template_id) values (?,?,?,?,?,?,?)",
                    $GLOBALS['gClientId'], $pageCode, "FLP Terms & Conditions", date("Y-m-d"), $GLOBALS['gUserId'],
                    "flp-terms-and-conditions", $templateId);
                $pageId = $insertSet['insert_id'];
            }
            $templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "content");
            $pageDataId = getFieldFromId("page_data_id", "page_data", "page_id", $pageId, "template_data_id = ?", $templateDataId);
            if(empty($pageDataId)) {
                executeQuery("insert ignore into page_data (page_id, template_data_id, text_data) values (?,?,?)", $pageId, $templateDataId, $termsAndConditionsContent);
            } else {
                executeQuery("update page_data set text_data = ? where page_data_id = ?", $termsAndConditionsContent, $pageDataId);
            }
            executeQuery("insert ignore into page_access (page_id, public_access, permission_level) values (?,1,3)", $pageId);

            # 7. Create upsell fragment and add to shopping cart page
            ob_start(); ?>
<script>
    $(function(){
        $("#flp_add_to_cart").click(function() {
            loadAjaxRequest("/retail-store-controller?ajax=true&url_action=add_product_code&product_search_value=FLP", function (returnArray) {
                getShoppingCartItems($("#shopping_cart_code").val());
            });
            return false;
        });
    });
</script>
<style>
    #flp_add_to_cart {
        display: block;
        text-align: center;
    }
    #flp_add_to_cart img {
        max-width: 100%;
    }
</style>
<a href="#" id='flp_add_to_cart'>
    <picture>
        <source srcset="/images/flp_upsell_desktop_animated.gif" media="(min-width: 640px)" />
        <img src="/images/flp_upsell_mobile_animated.gif" />
    </picture>
</a>
<?php       $fragmentContent = ob_get_clean();
            $fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", "FLP_ADD_TO_CART_BANNER");
            if (empty($fragmentId)) {
                executeQuery("insert into fragments (client_id, fragment_code, description, content) values (?,?,?,?)", $GLOBALS['gClientId'], "FLP_ADD_TO_CART_BANNER",
                    "FLP Add to Cart Banner", $fragmentContent);
            } else {
                executeQuery("update fragments set content = ? where fragment_id = ?", $fragmentContent, $fragmentId);
            }
            $checkoutPages = array("retailstore/simplifiedcheckout.php", "retailstore/checkoutv2.php", "retailstore/checkout.php", "retailstore/shoppingcart.php");
            $shoppingCartPageId = getFieldFromId("page_id", "pages", "link_name", "shopping-cart",
                "script_filename in ('" . implode("','", $checkoutPages) . "')");
            $shoppingCartPageId = $shoppingCartPageId ?: executeQuery("select page_id from pages where client_id = ? and inactive = 0 and internal_use_only = 0 and script_filename in ('" .
                implode("','", $checkoutPages) . "')", $GLOBALS['gClientId']);
            if (!empty($shoppingCartPageId)) {
                $templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "content");
                $pageDataRow = getRowFromId("page_data", "page_id", $shoppingCartPageId, "template_data_id = ?", $templateDataId);
                if (empty($pageDataRow)) {
                    executeQuery("insert into page_data (page_id, template_data_id, text_data) values (?,?,?)",
                        $shoppingCartPageId, $templateDataId, "%module:fragment:FLP_ADD_TO_CART_BANNER%");
                } else {
                    if (stristr($pageDataRow['text_data'], "%module:fragment:FLP_ADD_TO_CART_BANNER%") === false) {
                        $pageDataRow['text_data'] = "%module:fragment:FLP_ADD_TO_CART_BANNER%\n" . $pageDataRow['text_data'];
                        updateFieldById("text_data", $pageDataRow['text_data'], "page_data", "page_data_id", $pageDataRow['page_data_id']);
                    }
                }
            }
            setClientPreference("FLP_VERSION", FLP_LATEST_VERSION);
            $returnArray['info_message'] = "FLP Product created successfully.";
        }
		return $returnArray;
	}

    public static function setupAffiliate($affiliateLink) {
        // affiliate banner is used for clients in states where FLP must be sold as insurance
        $returnArray = array();
        if(empty($affiliateLink)) {
            $returnArray['error_message'] = "Enter Affiliate link first";
            return $returnArray;
        }
        $affiliateLink = filter_var($affiliateLink,FILTER_SANITIZE_URL);
        if(!startsWith($affiliateLink,"http")) {
            $affiliateLink = "https://" . $affiliateLink;
        }
        if(!startsWith($affiliateLink,"https://firearmslegal.com") || $affiliateLink == "https://firearmslegal.com") {
            $returnArray['error_message'] = "Invalid Affiliate link. Affiliate link should have format https://firearmslegal.com/?ref=XXXX";
            return $returnArray;
        }
        setClientPreference("FLP_AFFILIATE_LINK", $affiliateLink);
        ob_start(); ?>
<style>
    #flp_affiliate {
        display: block;
        text-align: center;
    }
    #flp_affiliate img {
        max-width: 100%;
    }
</style>
<a href="<?= $affiliateLink ?>" target="_blank" id="flp_affiliate">
    <picture>
        <source srcset="/images/flp_upsell_desktop_affiliate.png" media="(min-width: 640px)" />
        <img src="/images/flp_upsell_mobile_affiliate.png" />
    </picture>
</a>
<?php   $fragmentContent = ob_get_clean();
        $fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", "FLP_AFFILIATE_BANNER");
        if (empty($fragmentId)) {
            executeQuery("insert into fragments (client_id, fragment_code, description, content) values (?,?,?,?)", $GLOBALS['gClientId'], "FLP_AFFILIATE_BANNER",
                "FLP Affiliate Banner", $fragmentContent);
        } else {
            executeQuery("update fragments set content = ? where fragment_id = ?", $fragmentContent, $fragmentId);
        }
        $checkoutPages = array("retailstore/simplifiedcheckout.php", "retailstore/checkoutv2.php", "retailstore/checkout.php", "retailstore/shoppingcart.php");
        $shoppingCartPageId = getFieldFromId("page_id", "pages", "link_name", "shopping-cart",
            "script_filename in ('" . implode("','", $checkoutPages) . "')");
        $shoppingCartPageId = $shoppingCartPageId ?: executeQuery("select page_id from pages where client_id = ? and inactive = 0 and internal_use_only = 0 and script_filename in ('" .
            implode("','", $checkoutPages) . "')", $GLOBALS['gClientId']);
        if (!empty($shoppingCartPageId)) {
            $templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "content");
            $pageDataRow = getRowFromId("page_data", "page_id", $shoppingCartPageId, "template_data_id = ?", $templateDataId);
            if (empty($pageDataRow)) {
                executeQuery("insert into page_data (page_id, template_data_id, text_data) values (?,?,?)",
                    $shoppingCartPageId, $templateDataId, "%module:fragment:FLP_AFFILIATE_BANNER%");
            } else {
                if (stristr($pageDataRow['text_data'], "%module:fragment:FLP_AFFILIATE_BANNER%") === false) {
                    $pageDataRow['text_data'] = "%module:fragment:FLP_AFFILIATE_BANNER%\n" . $pageDataRow['text_data'];
                    updateFieldById("text_data", $pageDataRow['text_data'], "page_data", "page_data_id", $pageDataRow['page_data_id']);
                }
            }
        }
        $returnArray['info_message'] = "FLP Affiliate banner created successfully.";
        return $returnArray;
    }

    public static function addRelatedProducts() {
        $fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
        if(empty($fflRequiredProductTagId)) {
            return false;
        }
        $flpProductId = getFieldFromId("product_id", "product_data", "upc_code", FLP_UPC);
        $flpProductId = $flpProductId ?: getFieldFromId("product_id", "product_data", "upc_code", "FLP_STARTUP");
        if(empty($flpProductId)) {
            return false;
        }
        $relatedProductTypeId = getFieldFromId("related_product_type_id", "related_product_types", "related_product_type_code", "LEGAL_PROTECTION");
        if(empty($relatedProductTypeId)) {
            $insertSet = executeQuery("insert into related_product_types (client_id,related_product_type_code,description) values (?,?,?)",
                $GLOBALS['gClientId'], "LEGAL_PROTECTION", "Legal Protection");
            $relatedProductTypeId = $insertSet['insert_id'];
        }
        $productsResult = executeQuery("select product_id from product_tag_links where product_tag_id = ? and product_id not in (select product_id from related_products where associated_product_id = ?)",
            $fflRequiredProductTagId, $flpProductId);
        $insertCount = 0;
        while($productRow = getNextRow($productsResult)) {
            $insertSet = executeQuery("insert ignore into related_products (product_id, associated_product_id, related_product_type_id,version) values (?,?,?,2)",
                $productRow['product_id'], $flpProductId, $relatedProductTypeId);
            if(!empty($insertSet['insert_id'])) {
                $insertCount++;
            }
        }
        return $insertCount;
    }

	private static function getFullProductInformation($upcCode) {
		$returnArray = array();
		$parameters['connection_key'] = "760C0DCAB2BD193B585EB9734F34B3B6";
		$parameters['upc_code'] = $upcCode;
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_full_product_information";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$productInformation = json_decode($response, true);
		if (!is_array($productInformation) || !array_key_exists('product_information', $productInformation)) {
			$returnArray['error_message'] = "Product not found in Coreware Catalog";
			return $returnArray;
		}
		return $productInformation['product_information'];
	}

}
