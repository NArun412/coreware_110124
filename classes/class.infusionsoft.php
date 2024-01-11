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

class InfusionSoft {
    const INFUSIONSOFT_CI = 'w1qMLTxyVGOJ4ScUuKHwoYsOQFFqPPFR';
    const INFUSIONSOFT_CS = 'G6G1DI2JTCRfaoRS';
    const INFUSIONSOFT_URL = 'https://api.infusionsoft.com';
// using format "Y-m-d\TH:i:s.vO" instead of "c" because infusionsoft does not parse ISO 8601 timezones correctly
    const INFUSIONSOFT_DATETIME_FORMAT = "Y-m-d\TH:i:s.vO";
	private $iApiUrl;
	private $iErrorMessage;
	private $iAccessToken = "";
	private $iContactIdentifierTypeId = "";
	private $iInfusionsoftTags;
	private $iInfusionsoftCustomFields;

	public function __construct($accessToken) {
		$this->iApiUrl = self::INFUSIONSOFT_URL . '/crm/rest/v1';
        $this->iAccessToken = $accessToken;
		$tokenExpiration = getPreference('INFUSIONSOFT_TOKEN_EXPIRES');
        if(strtotime($tokenExpiration) < time()) {
		    self::refreshToken();
		    $this->iAccessToken = getPreference('INFUSIONSOFT_ACCESS_TOKEN');
        }
        // make sure INFUSIONSOFT_CONTACT_ID contact identifier type exists
        $this->iContactIdentifierTypeId = getFieldFromId('contact_identifier_type_id', 'contact_identifier_types', 'contact_identifier_type_code', 'INFUSIONSOFT_CONTACT_ID');
        if (empty($this->iContactIdentifierTypeId)) {
            $result = executeQuery("insert into contact_identifier_types (client_id, contact_identifier_type_code, description, internal_use_only) values (?, 'INFUSIONSOFT_CONTACT_ID', 'InfusionSoft Contact ID', 1)",
                $GLOBALS['gClientId']);
            $this->iContactIdentifierTypeId = $result['insert_id'];
        }
        // make sure INFUSIONSOFT_PRODUCT_ID custom field exists
        $infusionSoftProductCustomFieldId = getFieldFromId("custom_field_id", 'custom_fields', 'custom_field_code', 'INFUSIONSOFT_PRODUCT_ID');
        if (empty($infusionSoftProductCustomFieldId)) {
            $productsCustomFieldTypeId = getFieldFromId('custom_field_type_id', 'custom_field_types', 'custom_field_type_code', 'PRODUCTS');
            $result = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label)"
                . " values (?,'INFUSIONSOFT_PRODUCT_ID', 'Infusionsoft Product Id', ?, 'Infusionsoft Product ID')", $GLOBALS['gClientId'], $productsCustomFieldTypeId);
            executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?,'data_type', 'int')", $result['insert_id']);
        }
    }

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	private static function getRedirectUrl() {
        return getDomainName() . "/infusionsofttoken.php";
    }

	public static function getAuthorizeUrl() {
	    $redirectUrl = self::getRedirectUrl();

	    return sprintf("https://accounts.infusionsoft.com/app/oauth/authorize?response_type=code&redirect_uri=%s&realm=realm&client_id=%s&scope=",
            $redirectUrl, self::INFUSIONSOFT_CI);
    }

    public static function getAccessToken($authorizationCode) {
        $curl = curl_init();

        $data = array(
            'client_id' => self::INFUSIONSOFT_CI,
            'client_secret' => self::INFUSIONSOFT_CS,
            'grant_type'=>'authorization_code',
            'redirect_uri'=>self::getRedirectUrl(),
            'code'=>$authorizationCode
        );

        $headers = array(
            'Content-Type: application/x-www-form-urlencoded'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => self::INFUSIONSOFT_URL . "/token",
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            $err = curl_error($curl) . ":" . jsonEncode($result) . ":" . jsonEncode($info);
            return $err;
        }
        curl_close($curl);
        self::setAccessToken(json_decode($result, true));
        return true;
    }

    public static function refreshToken() {
	    $refreshToken = getPreference('INFUSIONSOFT_REFRESH_TOKEN');
	    if(empty($refreshToken)) {
	        return "Refresh token not found.  Re-authorize with Infusionsoft to get a new access token.";
        }
        $curl = curl_init();

        $data = array(
            'grant_type'=>'refresh_token',
            'refresh_token'=>$refreshToken
        );

        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(self::INFUSIONSOFT_CI . ':' . self::INFUSIONSOFT_CS)
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => self::INFUSIONSOFT_URL . "/token",
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            return curl_error($curl) . ":" . jsonEncode($info);
        }
        curl_close($curl);
        self::setAccessToken(json_decode($result, true));
        return true;
    }

    public static function setAccessToken($tokenResult) {
	    $tokenExpiration = date_add(date_create(), date_interval_create_from_date_string( $tokenResult['expires_in']. ' seconds'));
	    $preferenceArray = array(
	        array('code'=>'INFUSIONSOFT_ACCESS_TOKEN', 'description'=>'Infusionsoft Access Token', 'data_type'=>'varchar', 'value'=> $tokenResult['access_token']),
            array('code'=>'INFUSIONSOFT_REFRESH_TOKEN', 'description'=>'Infusionsoft Refresh Token', 'data_type'=>'varchar', 'value'=> $tokenResult['refresh_token']),
            array('code'=>'INFUSIONSOFT_TOKEN_EXPIRES', 'description'=>'Infusionsoft Token Expires', 'data_type'=>'date', 'value'=> $tokenExpiration->format('c'))
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
    }

	public function postApi($apiMethod,$data, $verb = "POST", $refresh = false) {
	    if($refresh) {
	        self::refreshToken();
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
        if(!$refresh && $info['http_code'] == 401 && stripos('Access Token expired', $result) !== false) {
            return $this->postApi($apiMethod, $data, $verb, true);
        }
		if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
			$this->iErrorMessage = curl_error($curl) . ":" . jsonEncode($info) . ":" . $result . ":" . $jsonData;
			return false;
		}
		curl_close($curl);
		return json_decode($result,true);
	}

    private function getApi($apiMethod,$data = array(), $refresh = false) {
	    if($refresh) {
	        self::refreshToken();
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
        if(!$refresh && $info['http_code'] == 401 && stripos('Access Token expired', $result) !== false) {
            return $this->getApi($apiMethod, $data, true);
        }
        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
            $this->iErrorMessage = curl_error($curl) . ":" . jsonEncode($info) . ":" . $result . ":" . $queryParams;
            return false;
        }

        curl_close($curl);
        return json_decode($result,true);
    }

    // Legacy XML-RPC API for methods not supported by REST (e.g. adding custom fields to tables other than Contacts)
	private function xmlApi($xmlData) {
        $xmlData = str_replace('%access_token%', $this->iAccessToken, $xmlData);

        $curl = curl_init();

        $headers = array(
            'Content-Type: application/xml',
            'Accept: */*'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => self::INFUSIONSOFT_URL . "/crm/xmlrpc/v1?access_token=" . $this->iAccessToken,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $xmlData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            $this->iErrorMessage = curl_error($curl) . ":" . jsonEncode($info) . ":" . $result . ":" . $xmlData;
            return false;
        }
        curl_close($curl);
        $xmlResult = simplexml_load_string($result);
        $jsonResult = json_encode($xmlResult);

        return json_decode($jsonResult,true);
    }

    private function populateTags() {
	    if(empty($this->iInfusionsoftTags)) {
            $infusionSoftTags = $this->getApi("tags");
            $this->iInfusionsoftTags = (is_array($infusionSoftTags) && array_key_exists("tags", $infusionSoftTags)) ? $infusionSoftTags['tags'] : array();
        }
    }

    private function populateCustomFields() {
        if(empty($this->iInfusionsoftCustomFields)) {
            $infusionSoftCustomFields = $this->getApi("contacts/model");
            $this->iInfusionsoftCustomFields = (is_array($infusionSoftCustomFields) && array_key_exists("custom_fields", $infusionSoftCustomFields)) ? $infusionSoftCustomFields['custom_fields'] : array();
        }
    }

    public function logOrder($orderId) {
        $orderRow = getRowFromId("orders", "order_id", $orderId);
        $contactRow = Contact::getContact($orderRow['contact_id']);
        $phoneNumber = Contact::getContactPhoneNumber($contactRow['contact_id']);

        $infusionSoftContactId = $this->updateContact($orderRow['contact_id']);

        $orderItems = array();
        $resultSet = executeQuery("select * from order_items join products using (product_id) where order_id = ?", $orderId);
        $orderTotal = $orderRow['shipping_charge'] + $orderRow['tax_charge'] + $orderRow['handling_charge'];

        $eventRegistrationTypeId = getFieldFromId('product_type_id', 'product_types', 'product_type_code', 'EVENT_REGISTRATION');
        $subscriptionStartTypeId = getFieldFromId('product_type_id', 'product_types', 'product_type_code', 'SUBSCRIPTION_STARTUP');
        $subscriptionRenewalTypeId = getFieldFromId('product_type_id', 'product_types', 'product_type_code', 'SUBSCRIPTION_RENEWAL');
        $subscriptionFieldPrefix = "";

        while ($row = getNextRow($resultSet)) {
            $row['sale_price'] = round($row['sale_price'],2);
            $infusionSoftProductId = $this->updateProduct($row['product_id'], $row['sale_price'], $row);
            $thisItem = array();
            $thisItem['description'] = $row['description'];
            $thisItem['product_id'] = $infusionSoftProductId;
            $thisItem['quantity'] = $row['quantity'];
            $thisItem['price'] = $row['sale_price'];
            $orderTotal += $row['quantity'] * $row['sale_price'];
            $orderItems[] = $thisItem;
            switch($row['product_type_id']) {
                case $eventRegistrationTypeId:
                    $eventId = getFieldFromId('event_id', 'events', 'product_id', $row['product_id']);
                    $eventSet = executeQuery("select event_type_id, description, start_date, (select min(hour) from event_facilities where event_id = events.event_id) start_hour from events where event_id = ?", $eventId);
                    $eventRow = getNextRow($eventSet);
                    $eventType = getMultipleFieldsFromId(array('event_type_code', 'description'), 'event_types', 'event_type_id', $eventRow['event_type_id']);
                    $eventDateTime = $this->formatDate(strtotime($eventRow['start_date']) + $eventRow['start_hour'] * 3600);
                    freeResult($eventSet);

                    $eventFieldName = $eventType['description'];
                    $this->addCustomFieldToContact($infusionSoftContactId, $eventFieldName, $eventDateTime, "DateTime");

                    $eventTagName = "Registered for " . $eventType['description'];
                    $this->tagContact($infusionSoftContactId, $eventTagName);

                    break;
                case $subscriptionStartTypeId:
                    $subscriptionFieldPrefix = 'setup_';
                case $subscriptionRenewalTypeId:
                    $productIdField = $subscriptionFieldPrefix . "product_id";
                    $subscriptionProductRow = getRowFromId("subscription_products", $productIdField, $row['product_id']);
                    $subscriptionRow = getRowFromId("subscriptions", "subscription_id", $subscriptionProductRow['subscription_id']);
                    $expirationDate = getFieldFromId("expiration_date", "contact_subscriptions", "subscription_id",
                        $subscriptionProductRow['subscription_id'], "contact_id = " . $contactRow['contact_id']);

                    $subscriptionName = $subscriptionRow['description'] . " expiration";
                    $this->addCustomFieldToContact($infusionSoftContactId, $subscriptionName, $this->formatDate($expirationDate),"DateTime");
                    break;
            }
        }
        freeResult($resultSet);

        $order = array(
            "contact_id" => $infusionSoftContactId,
            "order_date" => $this->formatDate($orderRow['order_time']),
            "order_items" => $orderItems,
            "order_title" => "eCommerce purchase",
            "order_type" => "Online",
            "promo_codes" => array(getFieldFromId("promotion_code", "promotions", "promotion_id",
                getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId))),
            "shipping_address" => array(
                "country_code" => getFieldFromId("iso_code", "countries", "country_id", $contactRow['country_id']),
                "first_name" => $contactRow['first_name'],
                "last_name" => $contactRow['last_name'],
                "phone" => $phoneNumber,
                "line1" => $contactRow['address_1'],
                "line2" => $contactRow['address_2'],
                "locality" => $contactRow['city'],
                "region" => $contactRow['state'],
                "zip_code" => $contactRow['postal_code']
            ));

        $result = $this->postApi("orders", $order);
        if (!$result) {
            addProgramLog("InfusionSoft Error on Order: " . $this->getErrorMessage());
        } else {
            addProgramLog("InfusionSoft Results on Order: " . jsonEncode($result) . ":" . jsonEncode($order));
            $infusionSoftOrderId = $result['id'];
            // Mark infusionsoft order as paid
            $payment = array("date" => $this->formatDate($orderRow['order_time']),
                "notes" => "Paid via coreFORCE eCommerce",
                "payment_method_type" => "CREDIT_CARD",
                "payment_amount" => strval(round($orderTotal, 2)));
            $result = $this->postApi("orders/" . $infusionSoftOrderId . "/payments" , $payment);
            if (!$result) {
                addProgramLog("InfusionSoft Error on payment: " . $this->getErrorMessage());
            }
        }
    }

    public function updateEventRegistrants($eventId) {
	    $addCount = 0;
	    $deleteCount = 0;
        $eventSet = executeQuery("select event_type_id, description, start_date, (select min(hour) from event_facilities where event_id = events.event_id) start_hour from events where event_id = ?", $eventId);
        $eventRow = getNextRow($eventSet);
        $eventType = getMultipleFieldsFromId(array('event_type_code', 'description'), 'event_types', 'event_type_id', $eventRow['event_type_id']);
        $eventDateTime = $this->formatDate(strtotime($eventRow['start_date']) + $eventRow['start_hour'] * 3600);
        freeResult($eventSet);

        $eventTagName = "Registered for " . $eventType['description'];
        $eventTagId = $this->getTagId($eventTagName);
        $eventFieldName = $eventType['description'];
        $eventFieldId = $this->getCustomFieldId($eventFieldName, "DateTime");
        $infusionSoftRegistrants = array();
        $taggedContacts = $this->getApi("tags/" . $eventTagId . "/contacts"); // get all contacts tagged with event type
        foreach($taggedContacts['contacts'] as $thisContact) {
            $contact = $this->getApi("contacts/" . $thisContact['contact']['id'], array("optional_properties"=>"custom_fields"));
            foreach($contact['custom_fields'] as $thisCustomField) { // check each contact to see if date/time matches (using strtotime because of timezone)
                if($thisCustomField['id'] == $eventFieldId && strtotime($thisCustomField['content']) == strtotime($eventDateTime)) {
                    $infusionSoftRegistrants[$contact['id']] = $contact;
                    break;
                }
            }
        }

        $registrantSet = executeReadQuery("select *, (select identifier_value from contact_identifiers join contact_identifier_types using (contact_identifier_type_id)"
            . " where event_registrants.contact_id = contact_identifiers.contact_id and contact_identifier_types.contact_identifier_type_code = 'INFUSIONSOFT_CONTACT_ID') as infusionsoft_contact_id"
            . " from event_registrants join contacts using (contact_id) where event_id = ?", $eventId);

        while ($registrantRow = getNextRow($registrantSet)) {
            if(array_key_exists($registrantRow['infusionsoft_contact_id'], $infusionSoftRegistrants)) { // matches - ignore
                unset($infusionSoftRegistrants[$registrantRow['infusionsoft_contact_id']]);
            } else { // does not exist in infusionSoft - add
                $infusionSoftContactId = $this->updateContact($registrantRow['contact_id'], "Registered for an event");
                // Custom field needs to be sent before tag or else tag will trigger an email with no date
                $this->addCustomFieldToContact($infusionSoftContactId, $eventFieldName, $eventDateTime);
                $this->tagContact($infusionSoftContactId, $eventTagName);
                $addCount++;
            }
        }

        // any remaining records from infusionsoft are out of date - delete
        foreach($infusionSoftRegistrants as $infusionSoftContactId => $thisRegistrant) {
            $this->tagContact($infusionSoftContactId, $eventTagName, true);
            $this->addCustomFieldToContact($infusionSoftContactId, $eventFieldName, "");
            $deleteCount++;
        }

        return array("add_count"=>$addCount, "delete_count"=>$deleteCount);
    }

    public function updateContact($contactId, $optInReason = "Made a purchase") {
        $contactRow = Contact::getContact($contactId);
        if(!empty($contactRow['birthdate'])) { // make sure contacts under 18 are not marked marketable
            $age = date_diff(date_create(), date_create($contactRow['birthdate']))->format('%y');
            if(intval($age) < 18) {
                $optInReason = "";
            }
        }
        $phoneNumber = Contact::getContactPhoneNumber($contactRow['contact_id']);
        $infusionSoftContactId = getFieldFromId('identifier_value', 'contact_identifiers', 'contact_id', $contactRow['contact_id'],
            "contact_identifier_type_id = " . $this->iContactIdentifierTypeId);
        if(!empty($infusionSoftContactId)) {
            // Get contact record from infusionsoft to make sure it still exists (may have been merged)
            $result = $this->getApi("contacts/" . $infusionSoftContactId);
            if (!empty($result['id'])) {
                $infusionSoftContactId = $result['id'];
            } else {
                executeQuery("delete from contact_identifiers where contact_id = ? and contact_identifier_type_id = ?",
                    $contactRow['contact_id'], $this->iContactIdentifierTypeId);
                $infusionSoftContactId = "";
            }
        }
        $customer = array(
            "given_name" => $contactRow['first_name'],
            "family_name" => $contactRow['last_name'],
            "addresses" => array(array(
                "field" => "SHIPPING",
                "line1" => $contactRow['address_1'],
                "line2" => $contactRow['address_2'],
                "locality" => $contactRow['city'],
                "postal_code" => $contactRow['postal_code'],
                "country_code" => getFieldFromId("iso_code", "countries", "country_id", $contactRow['country_id'])
            )),
            "email_addresses" => array(array("email" => $contactRow['email_address'], 'field' => 'EMAIL1')),
            "phone_numbers" => array(array("number" => $phoneNumber, "field" => "PHONE1"))
        );
        if(!empty($optInReason)) {
            $customer["opt_in_reason"] = $optInReason;
        }
        if (empty($infusionSoftContactId)) {
            $customer['duplicate_option'] = "EmailAndName";
            $result = $this->postApi("contacts", $customer, "PUT");
            if (is_array($result)) {
                $infusionSoftContactId = $result['id'];
                executeQuery("insert ignore into contact_identifiers (contact_id, contact_identifier_type_id, identifier_value) values (?,?,?)",
                    $contactRow['contact_id'], $this->iContactIdentifierTypeId, $infusionSoftContactId);
            } else {
                addProgramLog("InfusionSoft Error creating contact: " . $this->getErrorMessage());
            }
        } else {
            $result = $this->postApi("contacts/" . $infusionSoftContactId, $customer, "PATCH");
            if (!is_array($result)) {
                addProgramLog("InfusionSoft Error updating contact: " . $this->getErrorMessage());
            }
        }
        return $infusionSoftContactId;
    }

    private function formatDate($input) {
        if(!is_numeric($input)) {
            $input = strtotime($input);
        }
        $tzOffset = 0;
        $timezone = getPreference("INFUSIONSOFT_SERVER_TIME_ZONE");
        if(!empty($timezone)) {
            $localTimeZone = timezone_open(date_default_timezone_get());
            $infusionsoftTimeZone = timezone_open($timezone);
            if($infusionsoftTimeZone != false) {
                $tzOffset = $localTimeZone->getOffset(new DateTime()) - $infusionsoftTimeZone->getOffset(new DateTime());
            }
        }
        return date(self::INFUSIONSOFT_DATETIME_FORMAT, $input + $tzOffset);
    }

    private function getTagId($tagName) {
        $this->populateTags();
        $tagId = "";
        foreach ($this->iInfusionsoftTags as $thisTag) {
            if (strtolower($thisTag['name']) == strtolower($tagName)) {
                $tagId = $thisTag['id'];
                break;
            }
        }
        if (empty($tagId)) {
            $result = $this->postApi("tags", array("name" => $tagName, "description" => $tagName));
            $tagId = $result['id'];
            $this->iInfusionsoftTags[] = array('name'=>$tagName, 'id'=>$tagId);
        }
        return $tagId;
    }

    private function getCustomFieldId($customFieldName, $customFieldDataType = "string") {
        $this->populateCustomFields();
        foreach ($this->iInfusionsoftCustomFields as $thisCustomField) {
            if (strtolower($thisCustomField['label']) == strtolower($customFieldName)) {
                $customFieldId = $thisCustomField['id'];
                break;
            }
        }
        if (empty($customFieldId)) {
            $result = $this->postApi("contacts/model/customFields", array("field_type" => $customFieldDataType, "label" => $customFieldName));
            $customFieldId = $result['id'];
            $this->iInfusionsoftCustomFields[] = array('label'=>$customFieldName, 'id'=>$customFieldId);
        }
        return $customFieldId;
    }

    public function tagContact($infusionSoftContactId, $tagName, $delete = false) {
        $this->iErrorMessage = "";
        $tagId = $this->getTagId($tagName);
        if($delete) {
            $this->postApi("tags/" . $tagId . "/contacts/" . $infusionSoftContactId, array(),  "DELETE");
            if(!empty($this->iErrorMessage)) {
                addProgramLog("InfusionSoft Error removing tag from contact: " . $this->getErrorMessage());
            }
        } else {
            $result = $this->postApi("tags/" . $tagId . "/contacts", array("ids" => array($infusionSoftContactId)));
            if (!is_array($result)) {
                addProgramLog("InfusionSoft Error tagging contact: " . $this->getErrorMessage());
            }
        }
    }

    public function addCustomFieldToContact($infusionSoftContactId, $customFieldName, $customFieldValue, $customFieldDataType = "string") {
        $this->iErrorMessage = "";
        $customFieldId = $this->getCustomFieldId($customFieldName, $customFieldDataType);
        $data = array("id" => $customFieldId);
        if(!empty($customFieldValue)) { // to unset a value, omit the content field
            $data['content'] = $customFieldValue;
        }

        $result = $this->postApi("contacts/" . $infusionSoftContactId,
            array("custom_fields" => array($data)), "PATCH");
        if (!is_array($result)) {
            addProgramLog("InfusionSoft Error adding custom field to contact: " . $this->getErrorMessage());
        }
    }

    public function updateProduct($productId, $salePrice, $productRow = array()) {
	    $this->iErrorMessage = "";
	    if(empty($productRow)) {
            $productRow = getRowFromId("products", "product_id", $productId);
        }
        $infusionSoftProductId = CustomField::getCustomFieldData($productId, 'INFUSIONSOFT_PRODUCT_ID', 'PRODUCTS', true);
        $data = array(
            "product_desc" => $productRow['detailed_description'],
            "product_name" => $productRow['description'],
            "product_price" => $salePrice,
            "sku" => $productRow['product_code']
        );

        if (empty($infusionSoftProductId)) { // Create product in InfusionSoft
            $result = $this->postApi("products", $data);
            if (empty($result)) {
                $this->iErrorMessage = "Product update failed. Response: " . $this->iErrorMessage;
            } else {
                CustomField::setCustomFieldData($productId, 'INFUSIONSOFT_PRODUCT_ID', $result['id'], 'PRODUCTS');
                $infusionSoftProductId = $result['id'];
            }
        } else { // Update existing product in Infusionsoft
            $result = $this->postApi("products/" . $infusionSoftProductId, $data, "PATCH");
            if (empty($result)) {
                $this->iErrorMessage = "Product update failed. Response: " . $this->iErrorMessage;
            }
        }
        if(empty($this->iErrorMessage)) {
            return $infusionSoftProductId;
        } else {
            return false;
        }
    }

}
