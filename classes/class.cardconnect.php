<?php

/*		This software is the unpublished, confidential, proprietary, intellectual
		property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
		or used in any manner without expressed written consent from Kim David Software, LLC.
		Kim David Software, LLC owns all rights to this work and intends to keep this
		software confidential so as to maintain its value as a trade secret.

		Copyright 2004-Present, Kim David Software, LLC.

		WARNING! This code is part of the Kim David Software's Coreware system.
		Changes made to this source file will be lost when new versions of the
		system are installed.
*/

class CardConnect extends eCommerce {

	var $iCredentials = array();
	var $iUseUrl = "";
	var $iClientObject = false;
	var $iSecurityToken = false;

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow['account_login'] = "testing";
			$this->iMerchantAccountRow['account_key'] = "testing123";
			$this->iMerchantAccountRow['merchant_identifier'] = "496160873888";
			$this->iMerchantAccountRow['link_url'] = "https://fts.cardconnect.com:6443/cardconnect/rest";
		}
		if (empty($this->iMerchantAccountRow['link_url'])) {
			$this->iMerchantAccountRow['link_url'] = "https://fts.cardconnect.com:6443/cardconnect/rest";
		}
		if (substr($this->iMerchantAccountRow['link_url'], 0, 4) != "http") {
			$this->iMerchantAccountRow['link_url'] = "https://" . $this->iMerchantAccountRow['link_url'];
		}
	}

	function testConnection() {
		$result = $this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return (!empty($this->iResponse['raw_response']));
	}

	private function makeRequest($endpoint, $operation, $request) {

		$curlOptions = array();

		$curlOptions[CURLOPT_RETURNTRANSFER] = true;
		$curlOptions[CURLOPT_MAXREDIRS] = 10;
		$curlOptions[CURLOPT_HTTPAUTH] = constant('CURLAUTH_BASIC');
		$curlOptions[CURLOPT_USERPWD] = $this->iMerchantAccountRow['account_login'] . ":" . $this->iMerchantAccountRow['account_key'];
		$curlOptions[CURLOPT_FOLLOWLOCATION] = false;
		$curlOptions[CURLOPT_HTTPHEADER] = array();
		$curlOptions[CURLOPT_HTTPHEADER][] = 'Accept: application/json';
		$curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
		$curlOptions[CURLOPT_USERAGENT] = "CardConnectRestClient-PHP (v1.0)";

		$response = "";
		if ($operation == "put") {
			$request['merchid'] = $this->iMerchantAccountRow['merchant_identifier'];
			$data = json_encode($request);
			$curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($data);
			$curlOptions[CURLOPT_POSTFIELDS] = $data;
		} else {
			$endpoint .= "/" . $this->iMerchantAccountRow['merchant_identifier'];
		}
		$curlOptions[CURLOPT_CUSTOMREQUEST] = strtoupper($operation);

		$url = rtrim($this->iMerchantAccountRow['link_url'], '/') . '/' . $endpoint;
		$curl = curl_init($url);

		foreach ($curlOptions as $option => $value) {
			curl_setopt($curl, $option, $value);
		}

		$returnValue = curl_exec($curl);

		if ($returnValue === false) {
			$this->iErrorMessage = curl_error($curl);
			return false;
		}

		curl_close($curl);
		$response = json_decode($returnValue, true);
		return $response;
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

		$request = array();
		$request['retref'] = $parameters['transaction_identifier'];

		$logContent = "Void Charge: " . $this->cleanLogContent($parameters['transaction_identifier'], array()) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("void", "put", $request);
		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;

		$logContent = "Void Transaction: " . $this->cleanLogContent($parameters, array("card_number"=>"","card_code"=>"")) . "\n";
		$this->writeExternalLog($logContent);

		return ($response['authcode'] == "REVERS");
	}

	function refundCharge($parameters) {
		foreach ($this->iRequiredParameters['refund_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$request = array();
		$request['retref'] = $parameters['transaction_identifier'];
		$request['amount'] = number_format($parameters['amount'], 2, ".", "");

		$logContent = "Refund Charge: " . $this->cleanLogContent($parameters['transaction_identifier'], array()) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("refund", "put", $request);
		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		addProgramLog("Refund: " . $response);

		$logContent = "Refund Transaction: " . $this->cleanLogContent($parameters, array("card_number"=>"","card_code"=>"")) . "\n";
		$this->writeExternalLog($logContent);

		return ($response['respstat'] == "A");
	}

	function captureCharge($parameters) {
		foreach ($this->iRequiredParameters['capture_charge'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		$cleanValues = array();
		$request = array();
		$request['retref'] = $parameters['transaction_identifier'];
		$request['authcode'] = $parameters['authorization_code'];

		$logContent = "Capture Charge: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$response = $this->makeRequest("capture", "put", $request);

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response['setlstat'];
		$this->iResponse['response_reason_code'] = $response['setlstat'];
		$this->iResponse['response_reason_text'] = $response['setlstat'];
		$this->iResponse['transaction_id'] = $response['retref'];
		$this->iResponse['bank_batch_number'] = $response['batchid'];
		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['retref'], $this->iResponse['response_reason_text'], ($this->iResponse['response_code'] != "Accepted" && $this->iResponse['response_code'] != "Queued for Capture"));
		}

		return ($this->iResponse['response_code'] == "Accepted" || $this->iResponse['response_code'] == "Queued for Capture");
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
		$cleanValues = array();

		$request = array();
		$request['name'] = (empty($parameters['business_name']) ? $parameters['first_name'] . " " .
			$parameters['last_name'] : $parameters['business_name']);
		$request['orderid'] = $parameters['order_number'];
		$request['amount'] = number_format($parameters['amount'], 2, ".", "");
		$request['capture'] = (empty($parameters['authorize_only']) ? "Y" : "");
		if (!empty($parameters['card_number'])) {
			$request['account'] = $parameters['card_number'];
			$request['expiry'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$request['cvv2'] = $parameters['card_code'];
                $cleanValues['cvv2'] = "";
			}
            $cleanValues['account'] = substr($parameters['card_number'],-4);
            $cleanValues['expiry'] = date("my", strtotime($parameters['expiration_date']));
		} else if (!empty($parameters['bank_routing_number'])) {
			$request['account'] = $parameters['bank_account_number'];
			$request['bankaba'] = $parameters['bank_routing_number'];
            $cleanValues['account'] = substr($parameters['bank_routing_number'],-4);
            $cleanValues['bankaba'] = substr($parameters['bank_account_number'],-4);
		}
		$request['address'] = $parameters['address_1'];
		$request['postal'] = $parameters['postal_code'];
		$request['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
		if (empty($request['country'])) {
			$request['country'] = "US";
		}
		$request['ecomind'] = "E";

		$logContent = "Authorize Charge: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("auth", "put", $request);

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response['respstat'];
		$this->iResponse['response_reason_code'] = $response['respcode'];
		$this->iResponse['response_reason_text'] = $response['resptext'];
		$this->iResponse['authorization_code'] = $response['authcode'];
		$this->iResponse['avs_response'] = $response['avsresp'];
		$this->iResponse['card_code_response'] = $response['cvvresp'];
		$this->iResponse['transaction_id'] = $response['retref'];
		$this->iResponse['bank_batch_number'] = $response['batchid'];
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'] . "\n" . jsonEncode($response), ($this->iResponse['response_code'] != "A"));
		}

		return ($response['respstat'] == "A");
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		foreach ($this->iRequiredParameters['get_customer_profile'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$response = $this->makeRequest("profile/" . $parameters['merchant_identifier'] . "/", "get", null);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $response['profileid'];
		return true;
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

		$contactRow = Contact::getContact($parameters['contact_id']);
		$customerData = array();
		$customerData['name'] = getDisplayName($parameters['contact_id']);
		$customerData['address'] = $contactRow['address_1'];
		$customerData['city'] = $contactRow['city'];
		$customerData['region'] = $contactRow['state'];
		$customerData['defaultacct'] = "Y";
		$customerData['account'] = "4444333322221111";
		$customerData['expiry'] = "0926";

		if ((empty($contactRow['country_id']) && strlen($contactRow['postal_code']) == 10) || $contactRow['country_id'] == "1000") {
			$contactRow['postal_code'] = substr($contactRow['postal_code'], 0, 5);
		}
		$customerData['postal'] = $contactRow['postal_code'];
		$customerData['country'] = getFieldFromId("country_code", "countries", "country_id", $contactRow['country_id']);
		if (empty($customerData['country'])) {
			$customerData['country'] = "US";
		}
		$response = $this->makeRequest("profile", "put", $customerData);
		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $response['profileid'];
		$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);

		return true;
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
		$logContent = "Get Customer Payment Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("profile/" . $parameters['merchant_identifier'] . "/" . $parameters['account_token'], "get", null);
		if (count($response) == 1) {
			$response = $response[0];
		} else {
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['payment_profile'] = $parameters['account_token'];
		$this->iResponse['address_1'] = $response['address'];
		$this->iResponse['postal_code'] = $response['postal'];
		return true;
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
		$cleanValues = array();

		$customerData = array();
		$customerData['name'] = (empty($parameters['business_name']) ? $parameters['first_name'] . " " .
			$parameters['last_name'] : $parameters['business_name']);
		$customerData['profile'] = $parameters['merchant_identifier'] . "/";
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		$customerData['address'] = $parameters['address_1'];
		$customerData['city'] = $parameters['city'];
		$customerData['region'] = $parameters['state'];
		$customerData['postal'] = $parameters['postal_code'];
		$customerData['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
		if (empty($customerData['country'])) {
			$customerData['country'] = "US";
		}

		if (!empty($parameters['bank_routing_number'])) {
			$customerData['account'] = $parameters['bank_account_number'];
			$customerData['bankaba'] = $parameters['bank_routing_number'];
			$cleanValues["account"] = substr($parameters['bank_account_number'],-4);
			$cleanValues["bankaba"] = substr($parameters['bank_routing_number'],-4);
		} else {

			$cardValidationFields = array();
			$cardValidationFields['amount'] = "0.01";
			$cardValidationFields['account'] = $parameters['card_number'];
			$cardValidationFields['expiry'] = date("my", strtotime($parameters['expiration_date']));
			$cardValidationFields['capture'] = "N";
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$cardValidationFields['cvv2'] = $parameters['card_code'];
			}
			$cardValidationFields['address'] = $parameters['address_1'];
			$cardValidationFields['postal'] = $parameters['postal_code'];
			$cardValidationFields['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
			$cardValidationFields['ecomind'] = "E";
			$response = $this->makeRequest("auth", "put", $cardValidationFields);
			if ($response['respstat'] != "A") {
				$this->iResponse = array();
				$this->iResponse['raw_response'] = $response;
				$this->iResponse['response_code'] = $response['respstat'];
				$this->iResponse['response_reason_code'] = $response['respcode'];
				$this->iResponse['response_reason_text'] = $response['resptext'];
				return false;
			}

			$customerData['account'] = $parameters['card_number'];
			$customerData['expiry'] = date("my", strtotime($parameters['expiration_date']));
			$cleanValues["account"] = substr($parameters['card_number'],-4);
			$cleanValues["expiry"] = date("my", strtotime($parameters['expiration_date']));
			$cleanValues['cvv2'] = "";
		}

		$logContent = "Add Customer Payment Profile: " . $this->cleanLogContent($customerData, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("profile", "put", $customerData);

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $response['acctid'];
		$this->setAccountToken($parameters['account_id'], $parameters['merchant_identifier'], $this->iResponse['account_token']);
		return true;
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
		$logContent = "Delete Customer Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("profile/" . $parameters['merchant_identifier'] . "/", "delete", null);

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		return $response;
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
		$logContent = "Delete Customer Payment Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("profile/" . $parameters['merchant_identifier'] . "/" . $parameters['account_token'], "delete", null);

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		$this->iResponse['account_token'] = $parameters['account_token'];

		if (!empty($response) && !empty($parameters['account_id'])) {
			$this->deleteCustomerAccount($parameters['account_id']);
		}
		return !empty($response);
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
		$cleanValues = array();

		$request = array();
		$request['orderid'] = $parameters['order_number'];
		$request['amount'] = number_format($parameters['amount'], 2, ".", "");
		$request['capture'] = ($parameters['authorize_only'] ? "" : "Y");
		$request['profile'] = $parameters['merchant_identifier'] . "/" . $parameters['account_token'];
		$request['ecomind'] = "E";

		$logContent = "Create Customer Profile Transaction: " . $this->cleanLogContent($parameters, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		$response = $this->makeRequest("auth", "put", $request);

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response['respstat'];
		$this->iResponse['response_reason_code'] = $response['respcode'];
		$this->iResponse['response_reason_text'] = $response['resptext'];
		$this->iResponse['authorization_code'] = $response['authcode'];
		$this->iResponse['avs_response'] = $response['avsresp'];
		$this->iResponse['card_code_response'] = $response['cvvresp'];
		$this->iResponse['transaction_id'] = $response['retref'];
		$this->iResponse['bank_batch_number'] = $response['batchid'];

		$success =($response['respstat'] == "A");
        if (empty($parameters['test_connection'])) {
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'], !$success);
        }

        return $success;
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

		$customerData = array();
		$customerData['profile'] = $parameters['merchant_identifier'] . "/" . $parameters['account_token'];
		$customerData['profileupdate'] = "Y";

		$cleanValues = array();
		$somethingToUpdate = false;
		if (array_key_exists("first_name", $parameters)) {
			$somethingToUpdate = true;
			$customerData['name'] = (empty($parameters['business_name']) ? $parameters['first_name'] . " " .
				$parameters['last_name'] : $parameters['business_name']);
		}
		if (array_key_exists("address_1", $parameters)) {
			$somethingToUpdate = true;
			$customerData['address'] = $parameters['address_1'];
		}
		if (array_key_exists("state", $parameters)) {
			$somethingToUpdate = true;
			$customerData['region'] = $parameters['state'];
		}
		if (array_key_exists("city", $parameters)) {
			$somethingToUpdate = true;
			$customerData['city'] = $parameters['city'];
		}
		if (array_key_exists("postal_code", $parameters)) {
			if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
				$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
			}
			$somethingToUpdate = true;
			$customerData['postal'] = $parameters['postal_code'];
		}
		if (array_key_exists("expiration_date", $parameters)) {
			$somethingToUpdate = true;
			$customerData['expiry'] = date("my", strtotime($parameters['expiration_date']));
			$cleanValues["expiry"] = date("my", strtotime($parameters['expiration_date']));
		}

		if ($somethingToUpdate) {
			$logContent = "Update Customer Payment Profile: " . $this->cleanLogContent($customerData, $cleanValues) . "\n";
			$this->writeExternalLog($logContent);

			$response = $this->makeRequest("profile", "put", $customerData);

			$logContent = serialize($response) . "\n";
			$this->writeExternalLog($logContent);
		}

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $parameters['account_token'];

		return true;
	}
}
