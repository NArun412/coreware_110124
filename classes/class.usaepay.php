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

class usaEpay extends eCommerce {

	var $iCredentials = array();
	var $iLiveUrl = "https://secure.usaepay.com/soap/gate/PAVX3QHE/usaepay.wsdl";
	var $iTestUrl = "https://sandbox.usaepay.com/soap/gate/PAVX3QHE/usaepay.wsdl";
	var $iUseUrl = "";
	var $iClientObject = false;
	var $iSecurityToken = false;

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow['account_login'] = "5932";
			$this->iMerchantAccountRow['account_key'] = "53Yy6inUbe7waJW4gzoUbmfKJAXvhhbZ";
		}
		if ($GLOBALS['gDevelopmentServer'] || ($this->iMerchantAccountRow['account_login'] == "5932" && $this->iMerchantAccountRow['account_key'] == "53Yy6inUbe7waJW4gzoUbmfKJAXvhhbZ")) {
			$this->iUseUrl = $this->iTestUrl;
		} else {
			$this->iUseUrl = $this->iLiveUrl;
		}
		$pin = $this->iMerchantAccountRow['account_login'];
		$sourceKey = $this->iMerchantAccountRow['account_key'];
		// try {
		// 	$this->iClientObject = new SoapClient($this->iUseUrl);
		// } catch (Exception $e) {
		// 	if ($GLOBALS['gDevelopmentServer']) {
		// 		$this->iClientObject = false;
		// 	}
		// }
		$seed = time() . mt_rand();
		$clear = $sourceKey . $seed . $pin;
		$hash = sha1($clear);
		$this->iSecurityToken = array(
			'SourceKey' => $sourceKey,
			'PinHash' => array(
				'Type' => 'sha1',
				'Seed' => $seed,
				'HashValue' => $hash
			),
			'ClientIP' => $_SERVER['REMOTE_ADDR'],
		);
	}

	function testConnection() {
		$result = $this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return (strpos($this->iErrorMessage, "Specified source key not found") === false);
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
            $this->iResponse = $this->iClientObject->voidTransaction($this->iSecurityToken, $parameters['transaction_identifier']);
        } catch (SoapFault $e) {
            $this->writeExternalLog(serialize($e));
            $this->iErrorMessage = $e->getMessage();
            $this->iResponse = false;
        }

        $logContent = $this->iResponse . ":" . $this->iErrorMessage . "\n";
        $this->writeExternalLog($logContent);

        return $this->iResponse;
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

        $logContent = "Refund Charge: " . $this->cleanLogContent($parameters['transaction_identifier'], array()) . "\n";
        $this->writeExternalLog($logContent);
        try {
            $this->iResponse = $this->iClientObject->refundTransaction($this->iSecurityToken, $parameters['transaction_identifier'], $parameters['amount']);
        } catch (SoapFault $e) {
            $this->writeExternalLog(serialize($e));
            $this->iErrorMessage = $e->getMessage();
            $this->iResponse = false;
        }

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
		$request = array();
		$request['ClientIP'] = $_SERVER['REMOTE_ADDR'];
		$request['CustomerId'] = $parameters['contact_id'];
		$request['BillingAddress'] = array(
			"FirstName" => (!empty($parameters['bank_routing_number']) && empty($parameters['first_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['first_name']),
			"LastName" => (!empty($parameters['bank_routing_number']) && empty($parameters['last_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['last_name']),
			"Company" => $parameters['business_name'],
			"Street" => $parameters['address_1'],
			"City" => $parameters['city'],
			"State" => $parameters['state'],
			"Zip" => $parameters['postal_code'],
			"Country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']),
			"Email" => $parameters['email_address']);
		if (array_key_exists("order_items", $parameters) && !empty($parameter['order_items'])) {
			$lineItems = array();
			foreach ($parameters['order_items'] as $thisOrderItem) {
				if (empty($thisOrderItem['product_id'])) {
					continue;
				}
				$lineItems[] = array(
					"ProductRefNum" => $thisOrderItem['product_id'],
					"ProductKey" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
					"SKU" => "",
					"ProductName" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
					"Description" => getFieldFromId("description", "products", "product_id", $thisOrderItem['product_id']),
					"UnitPrice" => $thisOrderItem['sale_price'],
					"Qty" => (empty($thisOrderItem['quantity']) ? "1" : $thisOrderItem['quantity']),
					"Taxable" => "",
					"CommodityCode" => "",
					"UnitOfMeasure" => "",
					"DiscountAmount" => "",
					"DiscountRate" => "",
					"TaxAmount" => "",
					"TaxRate" => "");
			}
			if (!empty($lineItems)) {
				$request['LineItems'] = $lineItems;
			}
		}
	    $cleanValues['order_items'] = "";
		$logContent = "Capture Charge: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->runTransaction($this->iSecurityToken, $request);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response->ResultCode;
		$this->iResponse['response_reason_code'] = $response->Result;
		$this->iResponse['response_reason_text'] = $response->Error;
		$this->iResponse['authorization_code'] = $response->AuthCode;
		$this->iResponse['avs_response'] = $response->AvsResult;
		$this->iResponse['card_code_response'] = $response->CardCodeResult;
		$this->iResponse['transaction_id'] = $response->RefNum;
		$this->iResponse['bank_batch_number'] = $response->BatchNum;
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'], ($this->iResponse['response_code'] != "A"));
		}

		return ($this->iResponse['response_code'] == "A");
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
		$request['AccountHolder'] = (empty($parameters['business_name']) ? $parameters['first_name'] . " " .
			$parameters['last_name'] : $parameters['business_name']);
		$request['Details'] = array(
			"Invoice" => $parameters['order_number'],
			"PONum" => $parameters['order_number'],
			"OrderID" => $parameters['order_number'],
			"Description" => $parameters['description'],
			"Amount" => $parameters['amount'],
			"Tax" => (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : "0"),
			"Shipping" => (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : "0"));
		if (!empty($parameters['card_number'])) {
			$request['Command'] = (empty($parameters['authorize_only']) ? "Sale" : "AuthOnly");
			$request['CreditCardData'] = array(
				"CardNumber" => $parameters['card_number'],
				"CardExpiration" => date("my", strtotime($parameters['expiration_date'])),
				"AvsStreet" => $parameters['address_1'],
				"AvsZip" => $parameters['postal_code']);
            $cleanValues['CreditCardData'] = array(
                "CardNumber" => substr($parameters['card_number'], -4),
                "CardExpiration" => date("my", strtotime($parameters['expiration_date'])),
                "AvsStreet" => $parameters['address_1'],
                "AvsZip" => $parameters['postal_code']);
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$request['CreditCardData']['CardCode'] = $parameters['card_code'];
                $cleanValues['CreditCardData']['CardCode'] = "";
			}
			if (!empty($parameters['track_1']) || !empty($parameters['track_2'])) {
				$request['CreditCardData']['CardPresent'] = true;
				$request['CreditCardData']['MagStripe'] = (empty($parameters['track_1']) ? $parameters['track_2'] : $parameters['track_1']);
                $cleanValues['CreditCardData']['MagStripe'] = "";
			}
		} else if (!empty($parameters['bank_routing_number'])) {
			$request['Command'] = "check";
			$request['CheckData'] = array(
				"Routing" => $parameters['bank_routing_number'],
				"Account" => $parameters['bank_account_number'],
				"AccountType" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking"));
            $cleanValues['CheckData'] = array(
                "Routing" => substr($parameters['bank_routing_number'], -4),
                "Account" => substr($parameters['bank_account_number'], -4),
                "AccountType" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking"));
		}
		$request['ClientIP'] = $_SERVER['REMOTE_ADDR'];
		$request['CustomerId'] = $parameters['contact_id'];
		$request['BillingAddress'] = array(
			"FirstName" => (!empty($parameters['bank_routing_number']) && empty($parameters['first_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['first_name']),
			"LastName" => (!empty($parameters['bank_routing_number']) && empty($parameters['last_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['last_name']),
			"Company" => $parameters['business_name'],
			"Street" => $parameters['address_1'],
			"City" => $parameters['city'],
			"State" => $parameters['state'],
			"Zip" => $parameters['postal_code'],
			"Country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']),
			"Email" => $parameters['email_address']);
		if (array_key_exists("order_items", $parameters) && !empty($parameter['order_items'])) {
			$lineItems = array();
			foreach ($parameters['order_items'] as $thisOrderItem) {
				if (empty($thisOrderItem['product_id'])) {
					continue;
				}
				$lineItems[] = array(
					"ProductRefNum" => $thisOrderItem['product_id'],
					"ProductKey" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
					"SKU" => "",
					"ProductName" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
					"Description" => getFieldFromId("description", "products", "product_id", $thisOrderItem['product_id']),
					"UnitPrice" => $thisOrderItem['sale_price'],
					"Qty" => (empty($thisOrderItem['quantity']) ? "1" : $thisOrderItem['quantity']),
					"Taxable" => "",
					"CommodityCode" => "",
					"UnitOfMeasure" => "",
					"DiscountAmount" => "",
					"DiscountRate" => "",
					"TaxAmount" => "",
					"TaxRate" => "");
			}
			if (!empty($lineItems)) {
				$request['LineItems'] = $lineItems;
			}
		}
		$cleanValues['order_items'] = "";
		$logContent = "Authorize Charge: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		if ($GLOBALS['gDevelopmentServer'] && $GLOBALS['gClientRow']['client_code'] == "CORE") {
			$response = new stdClass();
			$response->ResultCode = "A";
			$response->RefNum = "983948752975";
			$response->AuthCode = "DOWOFD";
			$response->Result = "";
			$response->Error = "";
			$response->AvsResult = "";
			$response->CardCodeResult = "";
			$response->BatchNum = "";
		} else {
			try {
				$response = $this->iClientObject->runTransaction($this->iSecurityToken, $request);
			} catch (SoapFault $e) {
				$this->writeExternalLog(serialize($e));
				$this->iErrorMessage = $e->getMessage();
				$this->iResponse = false;
				return false;
			}
			$logContent = serialize($response) . "\n";
			$this->writeExternalLog($logContent);
		}

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response->ResultCode;
		$this->iResponse['response_reason_code'] = $response->Result;
		$this->iResponse['response_reason_text'] = $response->Error;
		$this->iResponse['authorization_code'] = $response->AuthCode;
		$this->iResponse['avs_response'] = $response->AvsResult;
		$this->iResponse['card_code_response'] = $response->CardCodeResult;
		$this->iResponse['transaction_id'] = $response->RefNum;
		$this->iResponse['bank_batch_number'] = $response->BatchNum;
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'], ($this->iResponse['response_code'] != "A"));
		}

		return ($this->iResponse['response_code'] == "A");
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
			$response = $this->iClientObject->getCustomer($this->iSecurityToken, $parameters['merchant_identifier']);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $response->CustNum;
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
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		$customerData = array();
		$customerData['CustomerID'] = $parameters['contact_id'];
		$customerData['Enabled'] = false;
		$customerData['Amount'] = 0;
		$customerData['Description'] = "";
		$customerData['Next'] = "2100-12-31";
		$customerData['NumLeft'] = 0;
		$customerData['OrderID'] = 0;
		$customerData['ReceiptNote'] = "";
		$customerData['Schedule'] = "";
		$customerData['SendReceipt'] = false;
		$customerData['BillingAddress'] = array(
			"FirstName" => (empty($parameters['first_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['first_name']),
			"LastName" => (empty($parameters['last_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['last_name']),
			"Company" => $parameters['business_name'],
			"Street" => $parameters['address_1'],
			"City" => $parameters['city'],
			"State" => $parameters['state'],
			"Zip" => $parameters['postal_code'],
			"Email" => $parameters['email_address']);
		$logContent = "Create Customer Profile: " . $this->cleanLogContent($customerData, array()) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->addCustomer($this->iSecurityToken, $customerData);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['merchant_identifier'] = $response;
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
		try {
			$response = $this->iClientObject->getCustomerPaymentMethod($this->iSecurityToken, $parameters['merchant_identifier'],
				$parameters['account_token']);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['payment_profile'] = $parameters['account_token'];
		$this->iResponse['address_1'] = $response->AvsStreet;
		$this->iResponse['postal_code'] = $response->AvsZip;
		if ($response->MethodType == "cc") {
			$this->iResponse['card_number'] = $response->CardNumber;
		}
		if ($response->MethodType == "check") {
			$this->iResponse['account_type'] = $response->AccountType;
			$this->iResponse['routing_number'] = $response->Routing;
			$this->iResponse['account_number'] = $response->Account;
		}
		return true;
	}

	function createCustomerPaymentProfile($parameters) {
		if ($GLOBALS['gLocalExecution']) {
			$this->setAccountToken($parameters['account_id'], $parameters['merchant_identifier'], "943891734597");
			return true;
		}
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
		$cleanValues = array();
		$paymentMethod = array();
		$paymentMethod['MethodName'] = "";
		$paymentMethod['SecondarySort'] = "0";
		if (!empty($parameters['bank_routing_number'])) {
			$paymentMethod['MethodType'] = "ACH";
			$paymentMethod['AccountType'] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking");
			$paymentMethod['Account'] = $parameters['bank_account_number'];
			$paymentMethod['Routing'] = $parameters['bank_routing_number'];
            $cleanValues['Account'] = substr($parameters['bank_account_number'],-4);
            $cleanValues['Routing'] = substr($parameters['bank_routing_number'],-4);
		} else {
			$paymentMethod['MethodType'] = "CreditCard";
			$paymentMethod['AvsStreet'] = $parameters['address_1'];
			$paymentMethod['AvsZip'] = $parameters['postal_code'];
			$paymentMethod['CardNumber'] = $parameters['card_number'];
			$cleanValues["CardNumber"] = substr($parameters['card_number'],-4);
			$paymentMethod['CardExpiration'] = date("my", strtotime($parameters['expiration_date']));
            $cleanValues['CardExpiration'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$paymentMethod['CardCode'] = $parameters['card_code'];
                $cleanValues['CardCode'] = "";
			}
		}

		$cleanValues['order_items'] = "";
		$logContent = "Add Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->addCustomerPaymentMethod($this->iSecurityToken, $parameters['merchant_identifier'],
				$paymentMethod, false, false);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $response;
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
			$response = $this->iClientObject->deleteCustomer($this->iSecurityToken, $parameters['merchant_identifier']);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
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
			$response = $this->iClientObject->deleteCustomerPaymentMethod($this->iSecurityToken, $parameters['merchant_identifier'], $parameters['account_token']);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
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
		if ($GLOBALS['gLocalExecution']) {
			$this->iResponse = array();
			$this->iResponse['authorization_code'] = "OSDIGHOI";
			$this->iResponse['transaction_id'] = "9857293465234";
			$this->iResponse['bank_batch_number'] = 59238;
			return true;
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
		$request['Command'] = "";
		$request['Details'] = array(
			"Command" => ($parameters['authorize_only'] ? "AuthOnly" : ""),
			"Invoice" => $parameters['order_number'],
			"PONum" => $parameters['order_number'],
			"OrderID" => $parameters['order_number'],
			"Description" => $parameters['description'],
			"Amount" => $parameters['amount'],
			"Tax" => (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : "0"),
			"Shipping" => (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : "0"));
		$request['ClientIP'] = $_SERVER['REMOTE_ADDR'];
		if (!empty($parameters['card_code']) && $parameters['card_code'] != "SKIP_CARD_CODE") {
			$request['CardCode'] = $parameters['card_code'];
            $cleanValues['CardCode'] = "";
		}
		if (array_key_exists("order_items", $parameters) && !empty($parameter['order_items'])) {
			$lineItems = array();
			foreach ($parameters['order_items'] as $thisOrderItem) {
				if (empty($thisOrderItem['product_id'])) {
					continue;
				}
				$lineItems[] = array(
					"ProductRefNum" => $thisOrderItem['product_id'],
					"ProductKey" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
					"SKU" => "",
					"ProductName" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
					"Description" => getFieldFromId("description", "products", "product_id", $thisOrderItem['product_id']),
					"UnitPrice" => $thisOrderItem['sale_price'],
					"Qty" => (empty($thisOrderItem['quantity']) ? "1" : $thisOrderItem['quantity']),
					"Taxable" => "",
					"CommodityCode" => "",
					"UnitOfMeasure" => "",
					"DiscountAmount" => "",
					"DiscountRate" => "",
					"TaxAmount" => "",
					"TaxRate" => "");
			}
			if (!empty($lineItems)) {
				$request['LineItems'] = $lineItems;
			}
		}
		$cleanValues['order_items'] = "";

		$logContent = "Create Customer Profile Transaction: " . $this->cleanLogContent($parameters, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->runCustomerTransaction($this->iSecurityToken, $parameters['merchant_identifier'],
				$parameters['account_token'], $request);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = serialize($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['response_code'] = $response->ResultCode;
		$this->iResponse['response_reason_code'] = $response->Result;
		$this->iResponse['response_reason_text'] = $response->Error;
		$this->iResponse['authorization_code'] = $response->AuthCode;
		$this->iResponse['avs_response'] = $response->AvsResult;
		$this->iResponse['card_code_response'] = $response->CardCodeResult;
		$this->iResponse['transaction_id'] = $response->RefNum;
		$this->iResponse['bank_batch_number'] = $response->BatchNum;

        $success = ($this->iResponse['response_code'] == "A");
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
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		try {
			$paymentMethod = $this->iClientObject->getCustomerPaymentMethod($this->iSecurityToken, $parameters['merchant_identifier'], $parameters['account_token']);
		} catch (SoapFault $e) {
			$this->writeExternalLog(serialize($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}
		$cleanValues = array();
		$somethingToUpdate = false;
		if (array_key_exists("address_1", $parameters)) {
			$somethingToUpdate = true;
			$paymentMethod->AvsStreet = $parameters['address_1'];
		}
		if (array_key_exists("postal_code", $parameters)) {
			$somethingToUpdate = true;
			$paymentMethod->AvsZip = $parameters['postal_code'];
		}
		if (array_key_exists("expiration_date", $parameters)) {
			$somethingToUpdate = true;
			$paymentMethod->CardExpiration = date("my", strtotime($parameters['expiration_date']));
			$cleanValues["CardExpiration"] = date("my", strtotime($parameters['expiration_date']));
		}
		if (array_key_exists("card_code", $parameters)) {
			$somethingToUpdate = true;
			$paymentMethod->CardCode = $parameters['card_code'];
			$cleanValues["CardCode"] = "";
		}

		$cleanValues['order_items'] = "";
		if ($somethingToUpdate) {
			$logContent = "Update Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
			$this->writeExternalLog($logContent);
			try {
				$response = $this->iClientObject->updateCustomerPaymentMethod($this->iSecurityToken, $paymentMethod, false);
			} catch (SoapFault $e) {
				$this->iErrorMessage = $e->getMessage();
				$this->iResponse = false;
				return false;
			}

			$logContent = serialize($response) . "\n";
			$this->writeExternalLog($logContent);
		}

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $parameters['account_token'];
		return true;
	}
}
