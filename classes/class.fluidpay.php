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

class FluidPay extends eCommerce {

	var $iCredentials = array();
	var $iTestUrl = "https://sandbox.fluidpay.com/api";
	var $iLiveUrl = "https://app.fluidpay.com/api";
	var $iUserName = "";
	var $iOptions = array();
    var $iSecCode = "WEB";

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow = array();
		}
		if ($GLOBALS['gDevelopmentServer'] || $this->iMerchantAccountRow['merchant_account_code'] == "DEVELOPMENT") {
			$this->iLiveUrl = $this->iTestUrl;
		}
		if (!empty($this->iMerchantAccountRow['link_url']) && $GLOBALS['gUserRow']['superuser_flag']) {
			$this->iLiveUrl = $this->iMerchantAccountRow['link_url'];
		}
        $secCodePref = strtoupper(getPreference('ACH_SEC_CODE'));
        if (in_array($secCodePref, array("CCD", "PPD", "TEL", "WEB"))) {
            $this->iSecCode = $secCodePref;
        }
		$this->iUserName = $this->iMerchantAccountRow['account_login'];
		$this->iPassword = $this->iMerchantAccountRow['account_key'];
	}

	function testConnection() {
		$this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/" . date("Y",strtotime("+ 1 year")), "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return (!in_array($this->iResponse['response_reason_text'], array("Authentication Failed", "unauthorized")) && !startsWith($this->iResponse['response_reason_text'], "general error"));
	}

	function authorizeCharge($parameters) {
		foreach ($this->iRequiredParameters['authorize_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}
        $parameters['first_name'] = $parameters['first_name'] ?: $parameters['business_name'];
        $parameters['last_name'] = $parameters['last_name'] ?: $parameters['business_name'];

		$cleanValues = array();

		$transaction = array(
			"amount" => round($parameters['amount'] * 100),
			"tax_amount" => round(array_key_exists("tax_charge", $parameters) ? ($parameters['tax_charge'] * 100) : 0),
			"shipping_amount" => round(array_key_exists("shipping_charge", $parameters) ? ($parameters['shipping_charge'] * 100) : 0),
			"currency" => "USD",
			"description" => $parameters['description'],
			"order_id" => strval($parameters['order_number']),
			"po_number" => $parameters['po_number'],
			"ip_address" => (empty($_SERVER['REMOTE_ADDR']) ? gethostbyname(gethostname()) : $_SERVER['REMOTE_ADDR']),
			"email_receipt" => false,
			"email_address" => $parameters['email_address'],
			"create_vault_record" => false,
			"billing_address" => array(
				"first_name" => $parameters['first_name'],
				"last_name" => $parameters['last_name'],
				"company" => $parameters['business_name'],
				"address_line_1" => $parameters['address_1'],
				"city" => $parameters['city'],
				"state" => $parameters['state'],
				"postal_code" => $parameters['postal_code'],
				"country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']),
				"phone" => $parameters['phone_number'],
				"email" => $parameters['email_address']
			)
		);

		if (!empty($parameters['card_number'])) {
			$chargeType = "card";
			$transaction["type"] = (empty($parameters['authorize_only']) ? "sale" : "authorize");
			$transaction['payment_method'] = array(
				"card" => array(
					"entry_type" => "keyed",
					"number" => $parameters['card_number'],
					"expiration_date" => date("m/y", strtotime($parameters['expiration_date']))));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$transaction['payment_method']['card']['cvc'] = $parameters['card_code'];
			}
			$cleanValues['payment_method'] = array("card" => array("cvc" => "", "number" => substr($parameters['card_number'], -4)));
		} else {
			$chargeType = "ach";
			$transaction["type"] = "sale";
			if (!empty($parameters['bank_routing_number'])) {
				$transaction['payment_method'] = array(
					"ach" => array(
						"routing_number" => $parameters['bank_routing_number'],
						"account_number" => $parameters['bank_account_number'],
						"sec_code" => $this->iSecCode,
						"account_type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking")));
				$cleanValues['payment_method'] = array("ach" => array("account_number" => substr($parameters['bank_account_number'], -4)));
			}
		}

		if (!empty($parameters['shipping_city']) || !empty($parameters['shipping_address_1']) || !empty($parameters['shipping_last_name'])) {
			$shippingAddress = array();
			if (!empty($parameters['shipping_first_name'])) {
				$shippingAddress['first_name'] = $parameters['shipping_first_name'];
			}
			if (!empty($parameters['shipping_last_name'])) {
				$shippingAddress['last_name'] = $parameters['shipping_last_name'];
			}
			if (!empty($parameters['shipping_business_name'])) {
				$shippingAddress['company'] = $parameters['shipping_business_name'];
			}
			if (!empty($parameters['shipping_address_1'])) {
				$shippingAddress['address_line_1'] = $parameters['shipping_address_1'];
			}
			if (!empty($parameters['shipping_city'])) {
				$shippingAddress['city'] = $parameters['shipping_city'];
			}
			if (!empty($parameters['shipping_state'])) {
				$shippingAddress['state'] = $parameters['shipping_state'];
			}
			if (!empty($parameters['shipping_postal_code'])) {
				$shippingAddress['postal_code'] = $parameters['shipping_postal_code'];
			}
			if (!empty($parameters['shipping_country_id'])) {
				$shippingAddress['country'] = (empty($parameters['shipping_country_id']) ? "US" : getFieldFromId("country_code", "countries", "country_id", $parameters['shipping_country_id']));
			}
			if (!empty($parameters['shipping_email'])) {
				$shippingAddress['email'] = $parameters['shipping_email'];
			}
			if (!empty($shippingAddress)) {
				$transaction['shipping_address'] = $shippingAddress;
			}
		}
		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => '/transaction',
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));

		if (!empty($rawResponse)) {
			$this->iResponse['response_code'] = $rawResponse['data']['response_code'];
			$this->iResponse['response_reason_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_text'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_text'];
			$this->iResponse['authorization_code'] = $rawResponse['data']['response_body'][$chargeType]['auth_code'];
			$this->iResponse['avs_response'] = $rawResponse['data']['response_body'][$chargeType]['avs_response_code'];
            if (in_array($this->iResponse['avs_response'], array("A", "Z", "N"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
            $responseText = strtoupper(str_replace(" ", "", $rawResponse['data']['response_body'][$chargeType]['processor_response_text']));
            if (in_array($responseText, array("ADDRESSMATCH", "ZIPMATCH", "NOMATCH"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
			$this->iResponse['card_code_response'] = $rawResponse['data']['response_body'][$chargeType]['cvv_response_code'];
            if($this->iResponse['card_code_response'] == "N") {
                $this->iResponse['response_reason_text'] = "Declined";
            }
			$this->iResponse['transaction_id'] = $rawResponse['data']['id'];
			if (empty($this->iResponse['response_reason_text']) && !empty($rawResponse['msg']) && $rawResponse['status'] == "failed") {
				$this->iResponse['response_reason_text'] = $rawResponse['msg'];
			}
		}

		$successful = $rawResponse['data']['response_code'] == "100";

		if (empty($parameters['test_connection'])) {
			$logParameters = $transaction;
			$logParameters['payment_method']['card']['number'] = "";
			$logParameters['payment_method']['card']['cvc'] = "";
			$logParameters['payment_method']['ach']['account_number'] = "";
			$logParameters['payment_method']['ach']['routing_number'] = "";
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($logParameters) . "\n\n" . jsonEncode($rawResponse), !$successful);
		}
		return $successful;
	}

	private function makeRequest($options = array()) {
		$this->iOptions = $options;
		$cleanValues = $options['clean_values'];
		unset($options['clean_values']);

		$url = $this->iLiveUrl;

		$curlHandle = curl_init();
		$header = array('Content-Type: application/json', 'Authorization: ' . $this->iMerchantAccountRow['account_key']);

		$configOptions = array();
		$configOptions[CURLOPT_HTTPHEADER] = $header;
		$configOptions[CURLOPT_URL] = $url . $options['url'];
		$configOptions[CURLOPT_RETURNTRANSFER] = 1;

		if (array_key_exists('method', $options)) {
			if (strtolower($options['method']) == 'post') {
				$configOptions[CURLOPT_POST] = 1;
			}
			if (strtolower($options['method']) == 'delete') {
				$configOptions[CURLOPT_CUSTOMREQUEST] = "DELETE";
			}
		}
		if (!empty($options['fields'])) {
			$configOptions[CURLOPT_POSTFIELDS] = jsonEncode($options['fields'], JSON_UNESCAPED_SLASHES);
		}
		foreach ($configOptions as $optionName => $optionValue) {
			curl_setopt($curlHandle, $optionName, $optionValue);
		}
		$rawResponse = curl_exec($curlHandle);
		curl_close($curlHandle);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = str_replace("\u0022", '"', $rawResponse);
		$this->iResponse['http_response'] = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		$this->iResponse['url'] = $this->iLiveUrl;
		$this->iResponse['header'] = $header;
		$this->iResponse['options'] = $options;
		$this->iResponse['config'] = $configOptions;

		$logContent = $options['url'] . ": " . $this->cleanLogContent($options['fields'], $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		$this->writeExternalLog(str_replace("\u0022", '"', $rawResponse));

		if (empty($rawResponse)) {
			return false;
		}

		return json_decode($rawResponse, true);
	}

	function voidCharge($parameters) {
		foreach ($this->iRequiredParameters['void_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => array("transaction_id" => $parameters['transaction_identifier']),
			'url' => '/transaction/' . $parameters['transaction_identifier'] . '/void'
		));

		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['transaction_identifier'], jsonEncode($rawResponse), ($rawResponse['status'] != "success"));
		}
		return (is_array($rawResponse) && $rawResponse['status'] == "success");
	}

	function refundCharge($parameters) {
		foreach ($this->iRequiredParameters['refund_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters) || empty($parameters[$requiredParameter])) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => array("amount"=>round($parameters['amount'] * 100),"transaction_id" => $parameters['transaction_identifier']),
			'url' => '/transaction/' . $parameters['transaction_identifier'] . '/refund'
		));
		if (!is_array($rawResponse['data']['response_body'])) {
			return false;
		}

		if (array_key_exists("card", $rawResponse['data']['response_body'])) {
			$chargeType = "card";
		} else {
			$chargeType = "ach";
		}
		if (!empty($rawResponse)) {
			$this->iResponse['response_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_text'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_text'] . $rawResponse['msg'];
            $this->iResponse['authorization_code'] = $rawResponse['data']['response_body'][$chargeType]['auth_code'];
            $this->iResponse['avs_response'] = $rawResponse['data']['response_body'][$chargeType]['avs_response_code'];
            if (in_array($this->iResponse['avs_response'], array("A", "Z", "N"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
            $responseText = strtoupper(str_replace(" ", "", $rawResponse['data']['response_body'][$chargeType]['processor_response_text']));
            if (in_array($responseText, array("ADDRESSMATCH", "ZIPMATCH", "NOMATCH"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
			$this->iResponse['card_code_response'] = $rawResponse['data']['response_body'][$rawResponse['data']['payment_method']]['cvv_response_code'];
            if($this->iResponse['card_code_response'] == "N") {
                $this->iResponse['response_reason_text'] = "Declined";
            }
            $this->iResponse['transaction_id'] = $rawResponse['data']['id'];
		}

		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['transaction_identifier'], jsonEncode($rawResponse), ($rawResponse['status'] != "success"));
		}
		return (is_array($rawResponse) && $rawResponse['status'] == "success");
	}

	function captureCharge($parameters) {
		foreach ($this->iRequiredParameters['capture_charge'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => array("transaction_id" => $parameters['transaction_identifier']),
			'url' => '/transaction/' . $parameters['transaction_identifier'] . '/capture'
		));
		if (is_array($rawResponse['data']['response_body']) && array_key_exists("card", $rawResponse['data']['response_body'])) {
			$chargeType = "card";
		} else {
			$chargeType = "ach";
		}

		if (!empty($rawResponse)) {
			$this->iResponse = array();
			$this->iResponse['raw_response'] = str_replace("\u0022", '"', $rawResponse);
			$this->iResponse['response_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_text'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_text'];
			$this->iResponse['authorization_code'] = $rawResponse['data']['response_body'][$chargeType]['auth_code'];
			$this->iResponse['avs_response'] = $rawResponse['data']['response_body'][$chargeType]['avs_response_code'];
            if (in_array($this->iResponse['avs_response'], array("A", "Z", "N"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
            $responseText = strtoupper(str_replace(" ", "", $rawResponse['data']['response_body'][$chargeType]['processor_response_text']));
            if (in_array($responseText, array("ADDRESSMATCH", "ZIPMATCH", "NOMATCH"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
			$this->iResponse['card_code_response'] = $rawResponse['data']['response_body'][$chargeType]['cvv_response_code'];
            if($this->iResponse['card_code_response'] == "N") {
                $this->iResponse['response_reason_text'] = "Declined";
            }
            $this->iResponse['transaction_id'] = $rawResponse['data']['id'];
		}
		if (empty($parameters['test_connection'])) {
			$this->writeLog(($chargeType == "card" ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($rawResponse), ($rawResponse['status'] != "success"));
		}
		return (is_array($rawResponse) && $rawResponse['status'] == "success" && $this->iResponse['response_reason_code'] == "00");
	}

	function createCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['create_customer_profile'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$this->iResponse['merchant_identifier'] = "FLUIDPAY-" . getRandomString(10);
		$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);
		return true;
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		$response = $this->makeRequest(array(
			'method' => 'GET',
			'url' => '/customer/' . $parameters['merchant_identifier']
		));

		if ($response) {
			$this->iResponse['merchant_identifier'] = $response['data']['id'];
			return $this->iResponse;
		} else {
			return false;
		}
	}

	function getCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['get_customer_payment_profile'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'GET',
			'url' => '/customer/' . $parameters['merchant_identifier']
		));

		if (!empty($rawResponse)) {
			$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
			$this->iResponse['payment_profile'] = strval($parameters['account_token']);

			$this->iResponse['payment_profile'] = strval($parameters['account_token']);
			$this->iResponse['address_1'] = $rawResponse['data']['billing_address']['address_line_1'];
			$this->iResponse['postal_code'] = $rawResponse['data']['billing_address']['postal_code'];
			if (!empty($rawResponse['data']['payment_method']['card'])) {
				$this->iResponse['card_number'] = $rawResponse['data']['payment_method']['card']['masked_card'];
				$this->iResponse['expiration_date'] = $rawResponse['data']['payment_method']['card']['expiration_date'];
			} else {
				if (!empty($rawResponse['data']['payment_method']['ach'])) {
					$this->iResponse['account_type'] = $rawResponse['data']['payment_method']['ach']['account_type'];
					$this->iResponse['routing_number'] = $rawResponse['data']['payment_method']['ach']['routing_number'];
					$this->iResponse['account_number'] = $rawResponse['data']['payment_method']['ach']['account_number'];
				}
			}
		}

		return (is_array($rawResponse) && $rawResponse['status'] == "success");
	}

	function createCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['create_customer_payment_profile_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}
        $parameters['first_name'] = $parameters['first_name'] ?: $parameters['business_name'];
        $parameters['last_name'] = $parameters['last_name'] ?: $parameters['business_name'];

		$contactId = $parameters['contact_id'];
		$cleanValues = array();
		$customer = array(
			"description" => getDisplayName($contactId),
			"billing_address" => array(
				"first_name" => $parameters['first_name'],
				"last_name" => $parameters['last_name'],
				"company" => $parameters['business_name'],
				"address_line_1" => $parameters['address_1'],
				"city" => $parameters['city'],
				"state" => $parameters['state'],
				"postal_code" => $parameters['postal_code'],
				"country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']),
				"email" => $parameters['email_address']
			),
			"shipping_address" => array(
				"first_name" => $parameters['first_name'],
				"last_name" => $parameters['last_name'],
				"company" => $parameters['business_name'],
				"address_line_1" => $parameters['address_1'],
				"city" => $parameters['city'],
				"state" => $parameters['state'],
				"postal_code" => $parameters['postal_code'],
				"country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']),
				"email" => $parameters['email_address']
			)
		);
		$paymentMethodCode = "";
		if (!empty($parameters['card_number'])) {
			$customer['payment_method'] = array(
				"card" => array(
					"entry_type" => "keyed",
					"card_number" => $parameters['card_number'],
					"expiration_date" => date("m/y", strtotime($parameters['expiration_date']))));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$customer['payment_method']['card']['cvc'] = $parameters['card_code'];
			}
			$cleanValues['payment_method'] = array("card" => array("cvc" => "", "card_number" => substr($parameters['card_number'], -4)));
			$paymentMethodCode = "card";
		} else {
			if (!empty($parameters['bank_routing_number'])) {
				$customer['payment_method'] = array(
					"ach" => array(
						"routing_number" => $parameters['bank_routing_number'],
						"account_number" => $parameters['bank_account_number'],
						"sec_code" => $this->iSecCode,
						"account_type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking")));
				$cleanValues['payment_method'] = array("ach" => array("account_number" => substr($parameters['bank_account_number'], -4)));
				$paymentMethodCode = "ach";
			}
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => '/customer',
			'fields' => $customer,
            "clean_values" => $cleanValues
		));
		$successful = (is_array($rawResponse) && $rawResponse['status'] == "success" && !empty($rawResponse['data']['payment_method'][$paymentMethodCode]['id']));
		if (!empty($rawResponse)) {
			$this->iResponse['merchant_identifier'] = $rawResponse['data']['id'];
			$this->iResponse['account_token'] = $rawResponse['data']['payment_method'][$paymentMethodCode]['id'];
			$this->setAccountToken($parameters['account_id'], $this->iResponse['merchant_identifier'], $this->iResponse['account_token']);
		}
		if (empty($parameters['test_connection'])) {
			$this->writeLog(jsonEncode($customer['payment_method']), jsonEncode(array_merge($customer, array("payment_method" => array()))) . "\n\n" . jsonEncode($rawResponse), !$successful);
		}
		return ($successful);
	}

	function deleteCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['delete_customer_profile'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'DELETE',
			'url' => '/customer/' . $parameters['merchant_identifier']
		));

		return (is_array($rawResponse) && $rawResponse['status'] == "success");
	}

	function deleteCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['delete_customer_payment_profile'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'DELETE',
			'url' => '/customer/' . $parameters['merchant_identifier']
		));

		return (is_array($rawResponse) && $rawResponse['status'] == "success");
	}

	function createCustomerProfileTransactionRequest($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['create_customer_payment_transaction_request'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		$billingAddressRow = getRowFromId("addresses","address_id",$parameters['address_id']);
		$contactRow = Contact::getContact($billingAddressRow['contact_id']);
        $paymentMethodTypeSet = executeQuery("select * from payment_method_types where payment_method_type_id = " .
            "(select payment_method_type_id from payment_methods where payment_method_id = (select payment_method_id from accounts where account_token = ? and merchant_identifier = ?))",
            $parameters['account_token'], $parameters['merchant_identifier']);
        $paymentMethodType = 'card';
        if($paymentMethodTypeRow = getNextRow($paymentMethodTypeSet)) {
            if($paymentMethodTypeRow['payment_method_type_code'] == 'BANK_ACCOUNT') {
                $paymentMethodType = 'ach';
            }
        }
        $contactRow['first_name'] = $contactRow['first_name'] ?: $contactRow['business_name'];
        $contactRow['last_name'] = $contactRow['last_name'] ?: $contactRow['business_name'];

        $cleanValues = array();
		$transaction = array(
			"type" => (empty($parameters['authorize_only']) ? "sale" : "authorize"),
			"amount" => round($parameters['amount'] * 100),
			"tax_amount" => round(array_key_exists("tax_charge", $parameters) ? ($parameters['tax_charge'] * 100) : 0),
			"shipping_amount" => round(array_key_exists("shipping_charge", $parameters) ? ($parameters['shipping_charge'] * 100) : 0),
			"currency" => "USD",
			"description" => $parameters['description'],
			"order_id" => strval($parameters['order_number']),
			"po_number" => $parameters['po_number'],
			"ip_address" => (empty($_SERVER['REMOTE_ADDR']) ? gethostbyname(gethostname()) : $_SERVER['REMOTE_ADDR']),
			"email_receipt" => false,
			"payment_method" => array(
				"customer" => array(
					"id" => $parameters['merchant_identifier'],
                    "payment_method_type" => $paymentMethodType,
					"payment_method_id" => strval($parameters['account_token'])
				)
			),
			"billing_address" => array(
				"first_name" => $contactRow['first_name'],
				"last_name" => $contactRow['last_name'],
				"company" => $contactRow['business_name'],
				"address_line_1" => $billingAddressRow['address_1'],
				"city" => $billingAddressRow['city'],
				"state" => $billingAddressRow['state'],
				"postal_code" => $billingAddressRow['postal_code'],
				"country" => getFieldFromId("country_code", "countries", "country_id", $billingAddressRow['country_id']),
				"email" => $contactRow['email_address']
			),
		);

		$shippingAddress = array();
		if (!empty($parameters['shipping_first_name'])) {
			$shippingAddress['first_name'] = $parameters['shipping_first_name'];
		}
		if (!empty($parameters['shipping_last_name'])) {
			$shippingAddress['last_name'] = $parameters['shipping_last_name'];
		}
		if (!empty($parameters['shipping_business_name'])) {
			$shippingAddress['company'] = $parameters['shipping_business_name'];
		}
		if (!empty($parameters['shipping_address_1'])) {
			$shippingAddress['address_line_1'] = $parameters['shipping_address_1'];
		}
		if (!empty($parameters['shipping_city'])) {
			$shippingAddress['city'] = $parameters['shipping_city'];
		}
		if (!empty($parameters['shipping_state'])) {
			$shippingAddress['state'] = $parameters['shipping_state'];
		}
		if (!empty($parameters['shipping_postal_code'])) {
			$shippingAddress['postal_code'] = $parameters['shipping_postal_code'];
		}
		if (!empty($parameters['shipping_country_id'])) {
			$shippingAddress['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['shipping_country_id']);
		}
		if (!empty($parameters['shipping_email'])) {
			$shippingAddress['email'] = $parameters['shipping_email'];
		}
		if (!empty($shippingAddress)) {
			$transaction['shipping_address'] = $shippingAddress;
		}
		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => '/transaction',
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));

		if (!empty($rawResponse)) {
			$chargeType = $rawResponse['data']['payment_type'];
			$this->iResponse['response_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_code'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_code'];
			$this->iResponse['response_reason_text'] = $rawResponse['data']['response_body'][$chargeType]['processor_response_text'] . $rawResponse['msg'];
            $this->iResponse['authorization_code'] = $rawResponse['data']['response_body'][$chargeType]['auth_code'];
            $this->iResponse['avs_response'] = $rawResponse['data']['response_body'][$chargeType]['avs_response_code'];
            if (in_array($this->iResponse['avs_response'], array("A", "Z", "N"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
            $responseText = strtoupper(str_replace(" ", "", $rawResponse['data']['response_body'][$chargeType]['processor_response_text']));
            if (in_array($responseText, array("ADDRESSMATCH", "ZIPMATCH", "NOMATCH"))) {
                $this->iResponse['response_reason_text'] = "Street address and/or ZIP doesn't match the billing address on file at the bank";
            }
			$this->iResponse['card_code_response'] = $rawResponse['data']['response_body'][$rawResponse['data']['payment_method']]['cvv_response_code'];
            if($this->iResponse['card_code_response'] == "N") {
                $this->iResponse['response_reason_text'] = "Declined";
            }
            $this->iResponse['transaction_id'] = $rawResponse['data']['id'];
		}
		$successful = $rawResponse['data']['response_code'] == "100";
		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['account_token'], jsonEncode($transaction) . "\n\n" . jsonEncode($rawResponse), !$successful);
		}
		return $successful;
	}

	function updateCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['update_customer_payment_profile'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}

		$postParameters = array();
		$cleanValues = array();
		$paymentMethod = "";
		if (!empty($parameters['card_number'])) {
			$postParameters['card'] = array();
			$postParameters['card']['card_number'] = $parameters['card_number'];
			$postParameters['card']['expiration_date'] = date("m/y", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$postParameters['cvc'] = $parameters['card_code'];
			}
			$cleanValues['card'] = array("card" => array("cvc" => "", "card_number" => substr($parameters['card_number'], -4)));
			$paymentMethod = "card";
		} else {
			if (!empty($parameters['bank_routing_number'])) {
				$transaction['payment_method'] = array(
					"ach" => array(
						"routing_number" => $parameters['bank_routing_number'],
						"account_number" => $parameters['bank_account_number'],
						"sec_code" => $this->iSecCode,
						"account_type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking")));
				$cleanValues['ach'] = array("ach" => array("account_number" => substr($parameters['bank_account_number'], -4)));
				$paymentMethod = "ach";
			}
		}

		return $this->makeRequest(array(
			'method' => 'POST',
			'url' => '/customer/' . $parameters['merchant_identifier'] . "/paymentmethod/" . $paymentMethod . "/" . $parameters['account_token'],
			'fields' => $postParameters,
			"clean_values" => $cleanValues));
	}
}
