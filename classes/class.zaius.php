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

const ZAIUS_PRODUCTION_URL = "https://api.zaius.com/v3";

class Zaius {
	private $iApiUrl;
	private $iErrorMessage;
	private $iApiKey;

	public function __construct($apiKey) {
		$this->iApiUrl = ZAIUS_PRODUCTION_URL;
		$this->iApiKey = $apiKey;
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function postApi($apiMethod,$data) {

		if ($apiMethod == "profiles") {
			$corewareIdentifier = array("name" => "coreware_contact_id", "display_name" => "Coreware Contact ID", "merge_confidence" => "high", "messaging" => false);
			$this->checkCustomFields("customers", array($corewareIdentifier));
		}

		if (!is_array($data)) {
			$data = array($data);
		}
		$jsonData = json_encode($data);
		$length = strlen($jsonData);

		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json',
			"Content-Length: $length",
		);
		if ($this->iApiKey) {
			$headers[] = 'x-api-key: ' . $this->iApiKey;
		}

		curl_setopt_array($curl, array(
			CURLOPT_URL            => $this->iApiUrl . "/" . $apiMethod,
			CURLOPT_CUSTOMREQUEST  => "POST",
			CURLOPT_POSTFIELDS     => $jsonData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		$err = curl_error($curl);
        curl_close($curl);
		if ($result === false) {
			$this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $jsonData;
			return false;
		} elseif (($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            $this->iErrorMessage = $result . ":" . jsonEncode($info) . ":" . $jsonData;
            return false;
        }
		return json_decode($result,true);
	}

	public function getApi($apiMethod,$data) {
		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json'
		);
		if($this->iApiKey) {
			$headers[] = 'x-api-key: ' . $this->iApiKey;
		}

		$url = $this->iApiUrl . "/" . $apiMethod;
		if (is_array($data) && count($data) > 0) {
			$url .= "?" . http_build_query($data);
		}


		curl_setopt_array($curl, array(
			CURLOPT_URL            => $url,
			CURLOPT_CUSTOMREQUEST  => 'GET',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);

		$err = curl_error($curl);
		curl_close($curl);
		if ($result === false) {
			$this->iErrorMessage = $err;
			return false;
		} elseif ($info['http_code'] != 200 && $info['http_code'] != 202 && $info['http_code']!=404) {
		    $this->iErrorMessage = $result;
		    return false;
        }
		if ($info['http_code'] == 404) {
			return null;
		} else {
			return $result;
		}
	}

	public function checkCustomFields($zaiusObjectName, $fieldArray) {
        // Make sure custom fields we are using exist within products object in Zaius
        $schemaResponse = $this->getApi("schema/objects/" . $zaiusObjectName ."/fields", "");
        $zaiusObjectFields = json_decode($schemaResponse, true);

        if (empty($zaiusObjectFields) || !is_array($zaiusObjectFields)) {
            $this->iErrorMessage = "Error retrieving Zaius Schema: " . $this->iErrorMessage;
            $zaiusObjectFields = array();
        } elseif ($zaiusObjectFields['message'] == "Forbidden") {
            $this->iErrorMessage = "Zaius API key invalid";
            return false;
        }
        foreach ($fieldArray as $thisAddField) {
            $fieldExists = false;
            foreach ($zaiusObjectFields as $thisField) {
                if ($thisField['name'] == $thisAddField['name']) {
                    $fieldExists = true;
                    break;
                }
            }
            if (!$fieldExists) {
                $response = $this->postApi("schema/objects/" . $zaiusObjectName ."/fields", $thisAddField);
                if (!$response) {
                    $this->iErrorMessage = "Error updating Zaius Schema: " . $this->iErrorMessage;
                }
            }
        }
        return true;
    }

	public function logOrder($orderId, $zaiusUseUpc) {
        $addFields = array(array("name" => "coreware_source", "display_name" => "Coreware contact source", "type" => "string"));
        $this->checkCustomFields("customers", $addFields);

        $orderRow = getRowFromId("orders", "order_id", $orderId);
        $contactRow = Contact::getContact($orderRow['contact_id']);
        $phoneNumber = getContactPhoneNumber($contactRow['contact_id']);
        $customer = array("attributes" => array(
            "coreware_contact_id" => strval($contactRow['contact_id']),
            "first_name" => $contactRow['first_name'],
            "last_name" => $contactRow['last_name'],
            "email" => $contactRow['email_address'],
            "street1" => $contactRow['address_1'],
            "street2" => $contactRow['address_2'],
            "city" => $contactRow['city'],
            "state" => $contactRow['state'],
            "zip" => $contactRow['postal_code'],
            "country" => getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']),
            "phone" => $phoneNumber,
            "coreware_source" => getFieldFromId("description", "sources", "source_id", $contactRow['source_id'])
        ));
        $this->postApi("profiles", $customer);

        $zaiusOrderTotal = $orderRow['shipping_charge'] + $orderRow['tax_charge'] + $orderRow['handling_charge'];
        $cartTotal = 0;
        $orderItems = array();
        $resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
        while ($row = getNextRow($resultSet)) {
            $thisItem = array();
            $upcCode = getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']);
            $thisItem['product_id'] = ((empty($zaiusUseUpc) || empty($upcCode)) ? $row['product_id'] : $upcCode);
            $thisItem['quantity'] = $row['quantity'];
            $thisItem['price'] = $row['sale_price'];
            $thisItem['discount'] = "0.00";
            $thisItem['subtotal'] = number_format(round($row['quantity'] * $row['sale_price'], 2),2,".","");
            $cartTotal += $row['quantity'] * $row['sale_price'];
            $orderItems[] = $thisItem;
        }
        $zaiusOrderTotal += $cartTotal;

        if (array_key_exists('vuid', $_COOKIE)) {
            $zaiusVuid = explode("|",$_COOKIE['vuid'])[0];
        } else {
            $zaiusVuid = "";
        }

        $order = array(array("type" => "order", "action" => "purchase",
            "identifiers" => array("email" => $contactRow['email_address'], "coreware_contact_id" => strval($contactRow['contact_id']), "vuid" => $zaiusVuid),
            "data" => array("order" => array("order_id" => strval($orderRow['order_id']),
                "total" => number_format(round($zaiusOrderTotal, 2),2,".",""),
                "discount" => $orderRow['order_discount'],
                "subtotal" => number_format(round($cartTotal, 2),2,".",""),
                "tax" => $orderRow['tax_charge'],
                "shipping" => $orderRow['shipping_charge'],
                "coupon_code" => getFieldFromId("promotion_code", "promotions", "promotion_id",
                    getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId)),
                "items" => $orderItems))));

        $result = $this->postApi("events", $order);
        if (!$result) {
            addProgramLog("Zaius Error: " . $this->getErrorMessage());
        } else {
            addProgramLog("Zaius Results: " . jsonEncode($result) . ":" . jsonEncode($order));
        }
    }
}
