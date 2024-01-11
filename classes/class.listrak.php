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

const LISTRAK_DATA_URL = 'https://api.listrak.com';

class Listrak {
	private $iApiUrl;
	private $iErrorMessage;
	private $iAccessToken = "";
    private $iClientId;
    private $iClientSecret;
    private $iBatch = array();
    private $iLogging;
    private $iLogLength;
    private $iDefaultProductImage;
    private $iLocations;

	public function __construct($clientId, $clientSecret) {
		$this->iApiUrl = LISTRAK_DATA_URL . '/data/v1';
        $this->iClientId = $clientId;
        $this->iClientSecret = $clientSecret;
        $this->iLogging = !empty(getPreference("LOG_LISTRAK"));
        $this->iLogLength = getPreference("LISTRAK_LOG_LENGTH") ?: 500;
		$tokenExpiration = getPreference('LISTRAK_TOKEN_EXPIRES');
        if(strtotime($tokenExpiration) < time()) {
            $this->getAccessToken();
        } else {
            $this->iAccessToken = getPreference('LISTRAK_ACCESS_TOKEN');
        }
        $this->iDefaultProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
        if(empty($this->iDefaultProductImage) || stristr($this->iDefaultProductImage, 'empty.jpg') !== false) {
            $this->iDefaultProductImage = "/images/no_product_image.jpg";
        }
        if(!startsWith($this->iDefaultProductImage,"/")) {
            $this->iDefaultProductImage = "/" . $this->iDefaultProductImage;
        }
        $locationsResult = executeQuery("select * from locations where client_id = ?", $GLOBALS['gClientId']);
        while($locationRow = getNextRow($locationsResult)) {
            $locationRow['store_number'] = CustomField::getCustomFieldData($locationRow['location_id'], "STORE_NUMBER", "LOCATIONS", true);
            $this->iLocations[$locationRow['location_id']] = $locationRow;
        }
    }

    public static function isSetup() {
        $preferenceArray = array(
            array('preference_code'=>'LOG_LISTRAK', 'description'=>'Log API calls to Listrak', 'data_type'=>'tinyint','temporary_setting'=>1),
            array('preference_code'=>'LISTRAK_LOG_LENGTH', 'description'=>'Length of data to save in Listrak log', 'data_type'=>'int'),
            array('preference_code'=>'LISTRAK_SYNC_WHICH_CONTACTS', 'description'=>'Which contacts to sync with Listrak', 'data_type'=>'select','preference_group'=>'INTEGRATION_SETTINGS',
                'choices'=>"ORDERS - Contacts with orders\n" .
                "SUBSCRIBED - Contacts subscribed to a mailing list\n" .
                "ORDERS_SUBSCRIBED - Contacts with orders or subscribed to a mailing list\n" .
                "ALL - All contacts"),
            array('preference_code'=>'LISTRAK_SYNC_ORDER_HISTORY', 'description'=>'Listrak Sync Order history (one time)', 'data_type'=>'tinyint', 'preference_group'=>'INTEGRATION_SETTINGS')
        );
        setupPreferences($preferenceArray);

        $orderStatusCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDER_STATUS");
        $listrakOrderStatusCustomFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", 'LISTRAK_ORDER_STATUS');
        if(empty($orderStatusCustomFieldTypeId) || empty($listrakOrderStatusCustomFieldId)) {
            return false;
        }
        return true;
    }
    public static function setup() {

        $orderStatusCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDER_STATUS");
        if(empty($orderStatusCustomFieldTypeId)) {
            $insertSet = executeQuery("insert into custom_field_types (custom_field_type_code, description) values ('ORDER_STATUS', 'Order Status')");
            if(!empty($insertSet['sql_error'])) {
                return array("error_message"=>($insertSet['sql_error'] ?: "An error occurred and Listrak order status could not be created."));
            }
            $orderStatusCustomFieldTypeId = $insertSet['insert_id'];
        }

        $listrakOrderStatusCustomFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", 'LISTRAK_ORDER_STATUS');
        if(empty($listrakOrderStatusCustomFieldId)) {
            $insertSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label) values " .
                " (?,'LISTRAK_ORDER_STATUS','Listrak Order Status',?,'Listrak Order Status')", $GLOBALS['gClientId'], $orderStatusCustomFieldTypeId);
            if (!empty($insertSet['sql_error'])) {
                return array("error_message" => ($insertSet['sql_error'] ?: "An error occurred and Listrak order status could not be created."));
            }
            $listrakOrderStatusCustomFieldId = $insertSet['insert_id'];
            $insertSet = executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?,'data_type','select')", $listrakOrderStatusCustomFieldId);
            if (!empty($insertSet['sql_error'])) {
                return array("error_message" => ($insertSet['sql_error'] ?: "An error occurred and Listrak order status could not be created."));
            }
            $choices = array(
                0 => "NotSet",
                1 => "Misc",
                2 => "PreOrder",
                3 => "BackOrder",
                4 => "Pending",
                5 => "Hold",
                6 => "Processing",
                7 => "Shipped",
                8 => "Completed",
                9 => "Returned",
                10 => "Canceled",
                11 => "Unknown"
            );
            foreach ($choices as $key => $value) {
                executeQuery("insert into custom_field_choices (custom_field_id, key_value, description) values (?,?,?)", $listrakOrderStatusCustomFieldId, $key, $value);
            }
        }
        return array();
    }
	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

    private function getAccessToken() {
        $curl = curl_init();

       $data = array(
            'client_id' => $this->iClientId,
            'client_secret' => $this->iClientSecret,
            'grant_type'=>'client_credentials'
        );

        $headers = array(
            'Content-Type: application/x-www-form-urlencoded'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => "https://auth.listrak.com/OAuth2/Token",
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if($this->iLogging) {
            $logEntry = "Listrak Token request: " . http_build_query($data) .
                "\nListrak Token response: " . $result;
            $logEntry .= (empty($err) ? "" : "\nListrak Token Error: " . $err);
            addDebugLog($logEntry);
        }
        if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            return $err . ":" . jsonEncode($result) . ":" . jsonEncode($info);
        }
        curl_close($curl);
        $this->setAccessToken(json_decode($result, true));
        return true;
    }

    private function setAccessToken($tokenResult) {
		if (!is_array($tokenResult)) {
            $GLOBALS['gPrimaryDatabase']->logError("Listrak: invalid token result:" . jsonEncode($tokenResult));
			$tokenResult = array();
		}
        if(!empty($tokenResult['expires_in']) && is_numeric($tokenResult['expires_in'])) {
            $expiresInSeconds = $tokenResult['expires_in'];
        } else {
            $expiresInSeconds = 3600;
        }
        $tokenExpiration = date("c", time() + $expiresInSeconds);
	    $preferenceArray = array(
	        array('code'=>'LISTRAK_ACCESS_TOKEN', 'description'=>'Listrak Access Token', 'data_type'=>'varchar', 'value'=> $tokenResult['access_token']),
            array('code'=>'LISTRAK_TOKEN_EXPIRES', 'description'=>'Listrak Token Expires', 'data_type'=>'date', 'value'=> $tokenExpiration)
        );

	    foreach($preferenceArray as $thisPreference) {
            $preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $thisPreference['code']);
            if(empty($preferenceId)) {
                $result = executeQuery("insert into preferences (preference_code,description, data_type, client_setable) values (?,?,?, 1)",
                    $thisPreference['code'], $thisPreference['description'], $thisPreference['data_type']);
                $preferenceId = $result['insert_id'];
            }
            executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
            executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $thisPreference['value']);
        }
        $this->iAccessToken = $tokenResult['access_token'];
    }

	public function postApi($apiMethod, $data, $verb = "POST", $refresh = false) {
	    if($refresh) {
	        $this->getAccessToken();
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
			CURLOPT_URL            => $this->iApiUrl . "/" . $apiMethod,
			CURLOPT_CUSTOMREQUEST  => $verb,
			CURLOPT_POSTFIELDS     => $jsonData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
        $err = curl_error($curl);
        if(!$refresh && $info['http_code'] == 401 && stripos($result, 'Authorization was denied') !== false) {
            return $this->postApi($apiMethod, $data, $verb, true);
        }
        if($this->iLogging) {
            addDebugLog("Listrak request: " . $this->iApiUrl . "/" . $apiMethod
                . "\nListrak Data: " . (strlen($jsonData) > $this->iLogLength ? substr($jsonData,0,$this->iLogLength) . "..." : $jsonData)
                . "\nListrak Result: " . $result
                . (empty($err) ? "" : "\nListrak Error: " . $err)
                . "\nListrak HTTP Status: " . $info['http_code']);
        }

        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
			$this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $result . ":" . $jsonData;
			return false;
		}
		curl_close($curl);
		return json_decode($result,true);
	}

    private function getApi($apiMethod,$data = array(), $refresh = false) {
	    if($refresh) {
	        $this->getAccessToken();
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
            CURLOPT_URL            => $this->iApiUrl . "/" . $apiMethod . (empty($queryParams) ? "" : "?" . $queryParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if(!$refresh && $info['http_code'] == 401 && stripos($result, 'Authorization was denied') !== false) {
            return $this->getApi($apiMethod, $data, true);
        }
        if($this->iLogging) {
            addDebugLog("Listrak request: " . $this->iApiUrl . "/" . $apiMethod . (empty($queryParams) ? "" : "?" . $queryParams)
                . "\nListrak Result: " . $result
                . (empty($err) ? "" : "\nListrak Error: " . $err)
                . "\nListrak HTTP Status: " . $info['http_code']);
        }

        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
            $this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $result . ":" . $queryParams;
            return false;
        }

        curl_close($curl);
        return json_decode($result,true);
    }

    public function logOrder($orderId, $orderRow = array(), $sendImmediately = true) {
        $orderRow = $orderRow ?: getRowFromId("orders", "order_id", $orderId);
        $contactRow = Contact::getContact($orderRow['contact_id']);
        $phoneNumber = Contact::getContactPhoneNumber($contactRow['contact_id']);

        $orderItems = array();
        $resultSet = executeQuery("select * from order_items join products using (product_id) where order_id = ?", $orderId);
        $orderTotal = $orderRow['shipping_charge'] + $orderRow['tax_charge'] + $orderRow['handling_charge'];
        $itemsTotal = 0;

        while ($row = getNextRow($resultSet)) {
            $row['sale_price'] = round($row['sale_price'],2);
            $thisItem = array();
            $thisItem['itemTotal'] = $row['sale_price'] * $row['quantity'];
            $thisItem['orderNumber'] = $orderRow['order_number'];
            $thisItem['price'] = $row['sale_price'];
            $thisItem['quantity'] = $row['quantity'];
            $thisItem['sku'] = getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']) ?: $row['product_id'];
            $itemsTotal += $row['quantity'] * $row['sale_price'];
            $orderItems[] = $thisItem;
        }
        freeResult($resultSet);
        $orderTotal += $itemsTotal;

        $shippingAddressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
        $shippingAddressRow = $shippingAddressRow ?: $contactRow;

        $billingAddressRow = false;
        $accountId = getFieldFromId("account_id", "order_payments", "order_id", $orderId, "account_id is not null");
        if (empty($accountId)) {
            $accountId = $orderRow['account_id'];
        }
        if (!empty($accountId)) {
            $billingAddressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
            $billingAddressRow = getRowFromId("addresses", "address_id", $billingAddressId);
        }
        $billingAddressRow = $billingAddressRow ?: $contactRow;
        $shipDate = "";
        if(!empty($orderRow['date_completed'])) {
            $shipmentResult = executeQuery("select * from order_shipments where order_id = ?", $orderId);
            while($shipmentRow = getNextRow($shipmentResult)) {
                if(strtotime($shipmentRow['date_shipped']) > strtotime($shipDate)) {
                    $shipDate = date("c", strtotime($shipmentRow['date_shipped']));
                }
            }
        }

        $sendArray = array('billingAddress' => array(
                "country" => getFieldFromId("country_name", "countries", "country_id", $billingAddressRow['country_id']),
                "firstName" => $billingAddressRow['first_name'] ?: $contactRow['first_name'],
                "lastName" => $billingAddressRow['last_name'] ?: $contactRow['last_name'],
                "zip_code" => $billingAddressRow['postal_code'],
                "address1" => $billingAddressRow['address_1'],
                "address2" => $billingAddressRow['address_2'],
                "city" => $billingAddressRow['city'],
                "state" => $billingAddressRow['state']
            ),
            "couponCode" => getFieldFromId("promotion_code", "promotions", "promotion_id",
                getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId)),
            "customerNumber" => $contactRow['contact_id'],
            "dateEntered" => date("c", strtotime($orderRow['order_time'])),
            "email" => $contactRow['email_address'],
            "handlingTotal" => $orderRow['handling_charge'],
            "items" => $orderItems,
            "itemTotal" => $itemsTotal,
            "merchandiseDiscount" => $orderRow['order_discount'],
            "orderNumber" => $orderRow['order_number'],
            "orderTotal" => $orderTotal,
            "shippingAddress" => array(
                "country" => getFieldFromId("country_name", "countries", "country_id", $shippingAddressRow['country_id']),
                "firstName" => $shippingAddressRow['first_name'] ?: $contactRow['first_name'],
                "lastName" => $shippingAddressRow['last_name'] ?: $contactRow['last_name'],
                "phone" => $phoneNumber,
                "zip_code" => $shippingAddressRow['postal_code'],
                "address1" => $shippingAddressRow['address_1'],
                "address2" => $shippingAddressRow['address_2'],
                "city" => $shippingAddressRow['city'],
                "state" => $shippingAddressRow['state']
            ),
            "shippingMethod" => getFieldFromId("description", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']),
            "shippingTotal" => $orderRow['shipping_charge'],
            "source" => getFieldFromId("description", "sources", "source_id", $orderRow['source_id']) ?: "online",
            "taxTotal" => $orderRow['tax_charge']
            );
        if(!empty($shipDate)) {
            $sendArray['shipDate'] = $shipDate;
        }
        $status = CustomField::getCustomFieldData($orderRow['order_status_id'], "LISTRAK_ORDER_STATUS", "ORDER_STATUS");
        if(!empty($status)) {
            $sendArray['status'] = $status;
        }
        $this->iBatch[] = $sendArray;

        if($sendImmediately || count($this->iBatch) > 1000) {
            $response = $this->postApi("Order", $this->iBatch);
            if (empty($response['status']) || $response['status'] != 201) {
                $result = false;
                addProgramLog("Listrak Error on Order: " . $this->getErrorMessage());
            } else {
                $result = true;
                addProgramLog("Listrak Results on Order: " . jsonEncode($response) . ":" . jsonEncode($response));
            }
            $this->iBatch = array();
        } else {
            $result = true;
        }

        return $result;
    }

    public function updateContact($contactId, $contactRow = array(), $registered = true, $sendImmediately = false) {
        $contactRow = $contactRow ?: Contact::getContact($contactId);
        if(!empty($contactRow['birthdate'])) { // make sure contacts under 18 are not marked marketable
            $age = date_diff(date_create(), date_create($contactRow['birthdate']))->format('%y');
            if(intval($age) < 18) {
                $registered = false;
            }
        }
        $mobilePhone = getContactPhoneNumber($contactId, array("cell","mobile", "text"), false);
        $phoneNumber = getContactPhoneNumber($contactId, "home");
        $defaultLocationId = CustomField::getCustomFieldData($contactId, "DEFAULT_LOCATION_ID");
        $storeNumber = "";
        if($defaultLocationId) {
            $storeNumber = $this->iLocations[$defaultLocationId]['store_number'] ?: $this->iLocations[$defaultLocationId]['description'];
        }
        $userTypeSet = executeQuery("select * from user_types where user_type_id = (select user_type_id from users where contact_id = ?)", $contactId);
        if($userTypeRow = getNextRow($userTypeSet)) {
            $userType = $userTypeRow['description'];
        } else {
            $userType = " ";
        }
        $this->iBatch[] = array(
            "address" => array(
                "address1" => $contactRow['address_1'],
                "address2" => $contactRow['address_2'],
                "city" => $contactRow['city'],
                "country" => getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']),
                "state" => $contactRow['state']
            ),
            "birthday" => $contactRow['birthdate'],
            "customerNumber" => $contactRow['contact_id'],
            "email" => $contactRow['email_address'],
            "firstName" => $contactRow['first_name'],
            "lastName" => $contactRow['last_name'],
            "gender" => CustomField::getCustomFieldData($contactId, "GENDER") ?: "",
            "homePhone" => $phoneNumber,
            "mobilePhone" => $mobilePhone,
            "preferredStoreNumber" =>$storeNumber,
            "registered" => $registered,
            "zipCode" => $contactRow['postal_code'],
            "meta2" => $userType
        );

        if($sendImmediately || count($this->iBatch) > 1000) {
            $response = $this->postApi("Customer", $this->iBatch);
            $result = $response['status'] == 201;
            $this->iBatch = array();
        } else {
            $result = true;
        }

        return $result;
    }


    public function updateProduct($productId, $salePrice, $productRow = array(), $sendImmediately = false, $markDiscontinued = false) {
        $this->iErrorMessage = "";
        $domainName = getDomainName();
        if(empty($productRow)) {
            $resultSet = executeQuery("select *, (select description from product_categories where product_category_id =
                    (select product_category_id from product_category_links where product_id = products.product_id order by sequence_number limit 1)) as product_category,
                    (select group_concat(description) from product_tag_links join product_tags using (product_tag_id) where product_tags.inactive = 0 and
                            products.product_id = product_tag_links.product_id and product_tags.internal_use_only = 0 and
                            product_tag_links.start_date < now() and product_tag_links.expiration_date > now()) as product_tags
                    from products left outer join product_data using (product_id) where products.product_id = ?", $productId);
            $productRow = getNextRow($resultSet);
            $productCatalog = new ProductCatalog();
            $salePriceArray = $productCatalog->getProductSalePrice($productId, array("product_information"=>$productRow));
            $salePrice = $salePriceArray['sale_price'] ?: $productRow['list_price'];
            $imageUrl = ProductCatalog::getProductImage($productId, array("product_row"=> $productRow, "no_cache_filename"=>true, "default_image"=>$this->iDefaultProductImage));
            $productRow['image_url'] = $imageUrl;
            $productRow['link_url'] = $domainName . (empty($productRow['link_name']) ? "/product-details?id=" . $productRow['product_id'] : "/product/" . $productRow['link_name']);
        }
        if(empty($productRow)) {
            $this->iErrorMessage = "Invalid Product ID";
            return false;
        }
        if(!$markDiscontinued) {
            if (startsWith($productRow['image_url'], "/cache/")) {
                $productRow['image_url'] = ProductCatalog::getProductImage($productId, array("no_cache_filename" => true, "default_image" => $this->iDefaultProductImage));
            }
            if (empty($productRow['image_url']) || stristr($productRow['image_url'], 'empty.jpg') !== false) {
                $productRow['image_url'] = $domainName . $this->iDefaultProductImage;
            }
            $sendArray = array(
                "brand" => $productRow['manufacturer_name'] ?: getFieldFromId('description', 'product_manufacturers', 'product_manufacturer_id', $productRow['product_manufacturer_id']),
                "category" => $productRow['product_category'],
                "description" => $productRow['detailed_description'],
                "imageUrl" => (startsWith($productRow['image_url'], "/") ? $domainName : "") . $productRow['image_url'],
                "inStock" => $productRow['inventory_count'] > 0,
                "qoh" => $productRow['inventory_count'],
                "isPurchasable" => ($productRow['inventory_count'] > 0 && !$productRow['internal_use_only']),
                "linkUrl" => $productRow['link_url'],
                "msrp" => numberFormat($productRow['list_price'], 2, ".", ""),
                "price" => numberFormat($salePrice, 2, ".", ""),
                "sku" => $productRow['upc_code'] ?: $productId,
                "title" => $productRow['description'],
                "discontinued" => $productRow['inactive'] && (stristr($productRow['product_category'],"discontinued") === false),
                "meta1" => $productId
            );
            if (!empty($productRow['original_sale_price']) && $productRow['original_sale_price'] != $productRow['list_price']
                && (stristr($productRow['product_tags'], "on sale") !== false  || stristr($productRow['product_tags'], "price drop") !== false))  {
                $sendArray['price'] = numberFormat($productRow['original_sale_price'], 2, ".", "");
                $sendArray['salePrice'] = numberFormat($salePrice, 2, ".", "");
                $sendArray['onSale'] = true;
            } else {
                $sendArray['onSale'] = false;
            }
        } else { // mark discontinued
            $sendArray = array(
                "inStock" => false,
                "isPurchasable" => false,
                "linkUrl" => $productRow['link_url'],
                "msrp" => numberFormat($productRow['list_price'], 2, ".", ""),
                "sku" => $productRow['upc_code'] ?: $productId,
                "title" => $productRow['description'],
                "discontinued" => true
            );
        }

        $this->iBatch[] = $sendArray;
        if($sendImmediately || count($this->iBatch) > 1000) {
            $response = $this->postApi("Product", $this->iBatch);
            $result = $response['status'] == 201;
            $this->iBatch = array();
        } else {
            $result = true;
        }

        return $result;
    }

}
