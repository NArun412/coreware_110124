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

require_once('stripe/init.php');

class Stripe extends eCommerce {

	var $iSecurityToken = false;

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow['account_login'] = "pk_test_kkk3j9QYIHAdvM9QiUWh1aSM";
			$this->iMerchantAccountRow['account_key'] = "sk_test_fEKo96D5OtoPURRMMiTie5T5";
		}
		$publishableKey = $this->iMerchantAccountRow['account_login'];
		$secretKey = $this->iMerchantAccountRow['account_key'];
		\Stripe\Stripe::setApiKey($secretKey);
	}

	function testConnection() {
		$result = $this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return (strpos($this->iErrorMessage, "Error connecting to Merchant Account") === false);
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

		$logContent = "Void Charge: " . $this->cleanLogContent($parameters['transaction_identifier'], array()) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = \Stripe\Refund::create(array(
				"charge" => $parameters['transaction_identifier']
			));
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
		}
		$this->iResponse = $response['status'] == "succeeded";

		$logContent = $this->iResponse . ":" . $this->iErrorMessage . "\n";
		$this->writeExternalLog($logContent);

		return $this->iResponse;
	}

	function captureCharge($parameters) {
		foreach ($this->iRequiredParameters['capture_charge'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		$cleanValues = array();

		$logContent = "Capture Charge: " . $this->cleanLogContent($parameters, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$charge = \Stripe\Charge::retrieve($parameters['transaction_identifier']);
			$response = $charge->capture();
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response['outcome']['type'];
		$this->iResponse['response_reason_code'] = $response['outcome']['type'];
		$this->iResponse['response_reason_text'] = $response['outcome']['reason'];
		$this->iResponse['authorization_code'] = $response['id'];
		$this->iResponse['avs_response'] = true;
		$this->iResponse['card_code_response'] = $response['paid'];
		$this->iResponse['transaction_id'] = $response['id'];
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'], ($this->iResponse['paid']));
		}

		return $response['paid'];
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
			$parameters['postal_code'] = mb_substr($parameters['postal_code'], 0, 5);
		}
		$cleanValues = array();
		$fullName = (empty($parameters['business_name']) ? $parameters['first_name'] . " " .
			$parameters['last_name'] : $parameters['business_name']);
		if (!empty($parameters['card_number'])) {
			$source = array(
				"object" => "card",
				"exp_month" => date("m", strtotime($parameters['expiration_date'])),
				"exp_year" => date("Y", strtotime($parameters['expiration_date'])),
				"number" => $parameters['card_number'],
				"name" => $fullName,
				"address_line1" => $parameters['address_1'],
				"address_city" => $parameters['city'],
				"address_country" => getFieldFromId("country_name", "countries", "country_id", $parameters['country_id']),
				"address_state" => $parameters['state'],
				"address_zip" => $parameters['postal_code']
			);
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$source['cvc'] = $parameters['card_code'];
				$cleanValues["cvc"] = "";
			}
			$cleanValues["number"] = mb_substr($parameters['card_number'], -4);
			$cleanValues["exp_month"] = date("m", strtotime($parameters['expiration_date']));
			$cleanValues["exp_year"] = date("Y", strtotime($parameters['expiration_date']));
		} else if (!empty($parameters['bank_routing_number'])) {
			$this->iErrorMessage = "ACH unavailable";
			return false;
		}
		$chargeArguments = array(
			"amount" => str_replace(".", "", number_format($parameters['amount'], 2, ".", "")),
			"currency" => "usd",
			"capture" => (empty($parameters['authorize_only']) ? true : false),
			"metadata" => array("order_number" => $parameters['order_number']),
			"description" => $parameters['description'],
			"statement_descriptor" => mb_substr($GLOBALS['gClientName'], 0, 22),
			"source" => $source
		);

		$logContent = "Authorize Charge: " . $this->cleanLogContent($chargeArguments, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = \Stripe\Charge::create($chargeArguments);
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response['outcome']['type'];
		$this->iResponse['response_reason_code'] = $response['outcome']['type'];
		$this->iResponse['response_reason_text'] = $response['outcome']['reason'];
		$this->iResponse['authorization_code'] = $response['id'];
		$this->iResponse['avs_response'] = true;
		$this->iResponse['card_code_response'] = $response['paid'];
		$this->iResponse['transaction_id'] = $response['id'];
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'], ($this->iResponse['paid']));
		}

		return $response['paid'];
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
		$customerData = array();
		$fullName = $parameters['first_name'];
		$fullName .= (empty($fullName) ? "" : " ") . $parameters['last_name'];
		$fullName .= (empty($fullName) || empty($parameters['business_name']) ? "" : ", ") . $parameters['business_name'];
		$customerData['description'] = $fullName;
		$customerData['email'] = $parameters['email_address'];
		$customerData['metadata'] = array("contact_id" => $parameters['contact_id']);
		$customerData['shipping'] = array(
			"name" => $fullName,
			"address" => array("line1" => $parameters['address_1'],
				"city" => $parameters['city'],
				"state" => $parameters['state'],
				"postal_code" => $parameters['postal_code']));
		$logContent = "Create Customer Profile: " . $this->cleanLogContent($customerData, array()) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = \Stripe\Customer::create($customerData);
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$success = $response['object'] == "customer";
		if ($success) {
			$this->iResponse = array();
			$this->iResponse['raw_response'] = $response;
			$this->iResponse['merchant_identifier'] = $response['id'];
			$this->createMerchantProfile($parameters['contact_id'], $response['id']);
			return false;
		} else {
			return true;
		}
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
		$logContent = "Get Customer Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = \Stripe\Customer::retrieve($parameters['merchant_identifier']);
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);
		$success = $response['object'] == "customer";

		if ($success) {
			$this->iResponse = array();
			$this->iResponse['raw_response'] = $response;
			$this->iResponse['merchant_identifier'] = $response['id'];
			return true;
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
		$logContent = "Get Customer Payment Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
		$this->writeExternalLog($logContent);

		try {
			$customer = \Stripe\Customer::retrieve($parameters['merchant_identifier']);
			$response = $customer->sources->retrieve($parameters['account_token']);
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['payment_profile'] = $response->id;
		$this->iResponse['address_1'] = $response->address_line1;
		$this->iResponse['postal_code'] = $response->address_zip;
		$this->iResponse['card_number'] = $response->last4;
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
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = mb_substr($parameters['postal_code'], 0, 5);
		}
		$cleanValues = array();

		$paymentMethod = array();
		$source = array();
		$source['object'] = "card";
		$source['exp_month'] = date("m", strtotime($parameters['expiration_date']));
		$source['exp_year'] = date("Y", strtotime($parameters['expiration_date']));
		$source['number'] = $parameters['card_number'];
		$source['name'] = getDisplayName($parameters['contact_id']);
		$source['address_line1'] = $parameters['address_1'];
		$source['address_city'] = $parameters['city'];
		$source['address_country'] = getFieldFromId("country_name", "countries", "country_id", $parameters['country_id']);
		$source['address_state'] = $parameters['state'];
		$source['address_zip'] = $parameters['postal_code'];
		if ($parameters['card_code'] != "SKIP_CARD_CODE") {
			$source['cvc'] = $parameters['card_code'];
			$cleanValues["cvc"] = "";
		}
		$paymentMethod['source'] = $source;
		$cleanValues["number"] = mb_substr($parameters['card_number'], -4);
		$cleanValues["exp_month"] = date("m", strtotime($parameters['expiration_date']));
		$cleanValues["exp_year"] = date("Y", strtotime($parameters['expiration_date']));

		$logContent = "Add Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		try {
			$customer = \Stripe\Customer::retrieve($parameters['merchant_identifier']);
			$response = $customer->sources->create($paymentMethod);
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account: " . serialize($e);
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $response['id'];
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

		try {
			$customer = \Stripe\Customer::retrieve($parameters['merchant_identifier']);
			$response = $customer->delete();
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

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

		try {
			$customer = \Stripe\Customer::retrieve($parameters['merchant_identifier']);
			$response = $customer->sources->retrieve($parameters['account_token'])->delete();
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account";
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		$this->iResponse['account_token'] = $parameters['account_token'];
		if ($response && !empty($parameters['account_id'])) {
			$this->deleteCustomerAccount($parameters['account_id']);
		}
		return $response;
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

		$chargeArguments = array(
			"amount" => str_replace(".", "", number_format($parameters['amount'], 2, ".", "")),
			"currency" => "usd",
			"metadata" => array("order_number" => $parameters['order_number']),
			"description" => $parameters['description'],
			"statement_descriptor" => mb_substr($GLOBALS['gClientName'], 0, 22),
			"customer" => $parameters['merchant_identifier'],
			"source" => $parameters['account_token'],
			"capture" => ($parameters['authorize_only'] ? false : true)
		);

		$logContent = "Create Customer Profile Transaction: " . $this->cleanLogContent($chargeArguments, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = \Stripe\Charge::create($chargeArguments);
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account: " . serialize($e);
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response['outcome']['type'];
		$this->iResponse['response_reason_code'] = $response['outcome']['type'];
		$this->iResponse['response_reason_text'] = $response['outcome']['reason'];
		$this->iResponse['authorization_code'] = $response['id'];
		$this->iResponse['avs_response'] = true;
		$this->iResponse['card_code_response'] = $response['paid'];
		$this->iResponse['transaction_id'] = $response['id'];

        $success = $response['paid'];
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
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = mb_substr($parameters['postal_code'], 0, 5);
		}

		$cleanValues = array();

		$paymentMethod = array();
		$cleanValues["number"] = mb_substr($parameters['card_number'], -4);
		$cleanValues["exp_month"] = date("m", strtotime($parameters['expiration_date']));
		$cleanValues["exp_year"] = date("Y", strtotime($parameters['expiration_date']));
		if (!empty($parameters['card_code'])) {
			$cleanValues["cvc"] = "";
		}

		$logContent = "Add Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		try {
			$customer = \Stripe\Customer::retrieve($parameters['merchant_identifier']);
			$source = $customer->sources->retrieve($parameters['account_token']);

			if (!empty($parameters['expiration_date'])) {
				$source->exp_month = date("m", strtotime($parameters['expiration_date']));
				$source->exp_year = date("Y", strtotime($parameters['expiration_date']));
			}
			if (!empty($parameters['address_1'])) {
				$source->address_line1 = $parameters['address_1'];
			}
			if (!empty($parameters['city'])) {
				$source->address_city = $parameters['city'];
			}
			if (!empty($parameters['country_id'])) {
				$source->address_country = getFieldFromId("country_name", "countries", "country_id", $parameters['country_id']);
			}
			if (!empty($parameters['state'])) {
				$source->address_state = $parameters['state'];
			}
			if (!empty($parameters['postal_code'])) {
				$source->address_zip = $parameters['postal_code'];
			}

			$response = $source->save();
		} catch (\Stripe\Error\Card $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: " . $err['message'];
			$this->iResponse = false;
			return false;
		} catch (Exception $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = "Stripe Error: Error connecting to Merchant Account: " . serialize($e);
			$this->iResponse = false;
			return false;
		}

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $parameters['account_token'];
		return true;
	}
}
