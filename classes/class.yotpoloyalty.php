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

const YOTPO_LOYALTY_URL = 'https://loyalty.yotpo.com/api/v2';

class YotpoLoyalty {
	private $iApiUrl;
	private $iErrorMessage;
	private $iApiKey = "";
	private $iGuid = "";

	public function __construct($apiKey, $guid) {
		$this->iApiUrl = YOTPO_LOYALTY_URL;
		$this->iApiKey = $apiKey;
		$this->iGuid = $guid;
	}

	public function logOrder($orderId) {
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$userId = getFieldFromId('user_id', 'users', 'contact_id', $orderRow['contact_id']);
		$resultSet = executeQuery("select *,(select group_concat(category_code) from categories join contact_categories using (category_id) where contact_categories.contact_id = contacts.contact_id) as category_codes," .
			"(select group_concat(description) from categories join contact_categories using (category_id) where contact_categories.contact_id = contacts.contact_id) as categories from " .
			"contacts where contact_id = ?", $orderRow['contact_id']);
		$contactRow = getNextRow($resultSet);

		$orderItems = array();
		$resultSet = executeQuery("select * from order_items join products using (product_id) join product_data using (product_id) where order_id = ?", $orderId);

		$orderTotal = 0;
		while ($row = getNextRow($resultSet)) {
			$row['sale_price'] = round($row['sale_price'], 2);
			$orderTotal += $row['sale_price'];
			$row['upc_code'] = str_replace(" ", "_", $row['upc_code']);
			$sendId = (empty($row['upc_code']) ? $row['product_code'] : $row['upc_code']);
			$orderItems[] = array(
				"id" => $sendId,
				"name" => $row['description'],
				"quantity" => $row['quantity'],
				"price_cents" => $row['sale_price'] * 100,
				"vendor" => getFieldFromId('description', 'product_manufacturers', 'product_manufacturer_id', $row['product_manufacturer_id'])
			);
		}
		freeResult($resultSet);
		$orderTotal = $orderTotal + $orderRow['tax_charge'] + $orderRow['shipping_charge'] + $orderRow['handling_charge'] - $orderRow['order_discount'];
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?: "";

		$order = array(
			"customer_email" => $contactRow['email_address'],
			"customer_id" => $contactRow['contact_id'],
			"total_amount_cents" => $orderTotal * 100,
			"currency_code" => "USD",
			"order_id" => $orderId,
			"coupon_code" => getFieldFromId("promotion_code", "promotions", "promotion_id",
				getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId)),
			"ip_address" => $orderRow['ip_address'],
			"user_agent" => $userAgent,
			"discount_amount_cents" => $orderRow['order_discount'] * 100,
			"items" => $orderItems,
			"customer" => array(
				"tags" => $contactRow['contact_categories'],
				"has_account" => (empty($userId) ? "false" : "true")
			));
		if (empty($userAgent)) { // for orders created by background process
			$order['ignore_ip_ua'] = "true";
		}

		$result = $this->postApi("orders", $order);
		if ($result === false) {
			addProgramLog("Yotpo Loyalty Error on Order: " . $this->getErrorMessage());
		} else {
			addProgramLog("Yotpo Loyalty Results on Order: " . jsonEncode($result) . ":" . jsonEncode($order));
		}
	}

	public function postApi($apiMethod, $data, $verb = "POST") {
		if (!is_array($data)) {
			$data = array($data);
		}
		$jsonData = json_encode($data);

		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json',
			'x-api-key: ' . $this->iApiKey,
			'x-guid: ' . $this->iGuid,
			'Accept: application/json,*/*'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->iApiUrl . "/" . $apiMethod,
			CURLOPT_CUSTOMREQUEST => $verb,
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		if (substr($result, 0, strlen("<!DOCTYPE html>")) == "<!DOCTYPE html>") { // loyalty returns HTML if API method is invalid
			$this->iErrorMessage = "Invalid API call.";
			return false;
		}
		if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200, 201, 202, 204)))) {
			$this->iErrorMessage = curl_error($curl) . ":" . jsonEncode($info) . ":" . $result . ":" . $jsonData;
			return false;
		}
		curl_close($curl);
		return json_decode($result, true);
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function uploadCoupons($input) {
		$redemptionOption = $input['redemption_option'];
        $redemptionRate = getPreference("YOTPO_LOYALTY_REDEMPTION_RATE");
        $redemptionRate = ((is_numeric($redemptionRate) && $redemptionRate > 0) ? $redemptionRate : 1);
        $discountAmount = $discountPercent = 0;
        switch ($redemptionOption['discount_type']) {
			case "fixed_amount":
                $discountAmount = $redemptionOption['discount_amount_cents'] / 100;
                break;
            case "percentage":
                $discountPercent = $redemptionOption['discount_percentage'];
                break;
            default:
                $discountAmount = $redemptionOption['amount'] * $redemptionRate / 100;
        }
        $maxCodes = getPreference("YOTPO_LOYALTY_COUPON_BATCH_SIZE");
		$maxCodes = ((is_numeric($maxCodes) && $maxCodes > 0) ? $maxCodes : 10);
        $codes = array();
		for($count = 0; $count < $maxCodes; $count++) {
			$codes[] = getRandomString(16, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
		}
        $GLOBALS['gPrimaryDatabase']->startTransaction();
        $promotionsTable = new DataTable("promotions");
		$promotionsTable->setSaveOnlyPresent(true);
		foreach ($codes as $thisCode) {
			$nameValues = array(
				"client_id" => $GLOBALS['gClientId'],
				"promotion_code" => $thisCode,
				"description" => $redemptionOption['name'],
				"start_date" => date('Y-m-d'),
				"maximum_usages" => 1,
				"discount_amount" => $discountAmount,
				"discount_percent" => $discountPercent
			);
            if (!$promotionsTable->saveRecord(array("name_values" => $nameValues))) {
                addProgramLog("Yotpo Loyalty Error on creating coupon codes: " . $promotionsTable->getErrorMessage());
                $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                return 0;
            }
		}
		$postData = array("redemption_option_id" => $redemptionOption['id'],
			"codes" => implode(",", $codes));
		$result = $this->postApi("redemption_codes", $postData);
		if ($result === false) {
			addProgramLog("Yotpo Loyalty Error on uploading coupon codes: " . $this->getErrorMessage());
            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
            return 0;
		}
        addProgramLog("Yotpo Loyalty Results on uploading coupon codes: " . jsonEncode($result));
        $GLOBALS['gPrimaryDatabase']->commitTransaction();
		return count($codes);
	}

	private function getApi($apiMethod, $data = array()) {
		if (!is_array($data)) {
			$data = array($data);
		}
		$queryParams = http_build_query($data);

		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json',
			'x-api-key: ' . $this->iApiKey,
			'x-guid: ' . $this->iGuid,
			'Accept: application/json,*/*'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->iApiUrl . "/" . $apiMethod . (empty($queryParams) ? "?" . $queryParams : ""),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));

		$result = curl_exec($curl);
		$info = curl_getinfo($curl);
		if (substr($result, 0, strlen("<!DOCTYPE html>")) == "<!DOCTYPE html>") { // loyalty returns HTML if API method is invalid
			$this->iErrorMessage = "Invalid API call.";
			return false;
		}
		if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200, 201, 202, 204)))) {
			$this->iErrorMessage = curl_error($curl) . ":" . jsonEncode($info) . ":" . $result . ":" . $queryParams;
			return false;
		}

		curl_close($curl);
		return (empty($result) ? true : json_decode($result, true));
	}

}
