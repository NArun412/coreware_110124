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

class eBizCharge extends eCommerce {

	var $iCredentials = array();
	var $iUseUrl = "https://soap.ebizcharge.net/eBizService.svc?singleWsdl"; // sandbox and live use same URL; apikey determines test vs live
	var $iClientObject = false;
	var $iSecurityToken = false;

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow['account_login'] = "coreware";
			$this->iMerchantAccountRow['account_key'] = "8f9d8d41-67d9-42d8-b8b5-da9cf6daea0c";
		}
		if ($GLOBALS['gDevelopmentServer'] || ($this->iMerchantAccountRow['account_login'] == "coreware" && $this->iMerchantAccountRow['account_key'] == "8f9d8d41-67d9-42d8-b8b5-da9cf6daea0c")) {
			$parameters = array('trace' => 1);
		} else {
			$parameters = array();
		}
		try {
            $this->iClientObject = new SoapClient($this->iUseUrl, $parameters);
        } catch(SoapFault $e) {
		    $this->iErrorMessage = $e->getMessage();
        }
		$this->iSecurityToken = array(
			'SecurityId' => $this->iMerchantAccountRow['account_key'],
			'UserId' => $this->iMerchantAccountRow['account_login'],
			'Password' => $this->iMerchantAccountRow['account_login']
        );
	}

	function testConnection() {
		$this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040",
            "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000,
            "test_connection" => true, "card_code" => "234"));
		return empty($this->iErrorMessage);
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
        $request['Command'] = "void";
        $request['RefNum'] = $parameters['transaction_identifier'];
        // Required fields in TransactionRequestObject Schema
        $request['IsRecurring'] = false;
        $request['IgnoreDuplicate'] = true;
        $request['CustReceipt'] = false;

        $request['ClientIP'] = $_SERVER['REMOTE_ADDR'];

        $logContent = "Void Charge: " . $this->cleanLogContent($parameters['transaction_identifier'], array()) . "\n";
        $this->writeExternalLog($logContent);
        try {
            $response = $this->iClientObject->runTransaction(array("securityToken" => $this->iSecurityToken, "tran" => $request));
            $response = $response->runTransactionResult;
        } catch (SoapFault $e) {
            $this->writeExternalLog(jsonEncode($e));
            $this->iErrorMessage = $e->getMessage();
            $this->iResponse = false;
            return false;
        }

        // Return response as array instead of StdClass
        $this->iResponse = json_decode(json_encode($response), true );
        if($this->iResponse->ResultCode !== "A") {
            $this->iErrorMessage = $this->iResponse->Error;
        }
        $logContent = $this->iResponse->Result . ":" . $this->iErrorMessage . "\n";
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

        $request = array();
        $request['Command'] = "Credit";
        $request['RefNum'] = $parameters['transaction_identifier'];
        // Required fields in TransactionRequestObject Schema
        $request['IsRecurring'] = false;
        $request['IgnoreDuplicate'] = true;
        $request['CustReceipt'] = false;
        $request['Details'] = array(
            "Amount" => $parameters['amount'],
            "Shipping" => 0, // required
            "Tax" => 0, // required
            "NonTax" => true, // required
            "Subtotal" => $parameters['amount'], // required
            "Duty" => 0, // required
            "Discount" => 0, // required
            "AllowPartialAuth" => false, // required
            "Tip" => 0 // required
        );

        $request['ClientIP'] = $_SERVER['REMOTE_ADDR'];

        $logContent = "Refund Charge: " . $this->cleanLogContent($parameters['transaction_identifier'], array()) . "\n";
        $this->writeExternalLog($logContent);
        try {
            $response = $this->iClientObject->runTransaction(array("securityToken" => $this->iSecurityToken, "tran" => $request));
            $response = $response->runTransactionResult;
        } catch (SoapFault $e) {
            $this->writeExternalLog(jsonEncode($e));
            $this->iErrorMessage = $e->getMessage();
            $this->iResponse = false;
            return false;
        }

        $this->iResponse = json_decode(json_encode($response), true );
        if($this->iResponse->ResultCode !== "A") {
            $this->iErrorMessage = $this->iResponse->Error;
        }
        $logContent = $this->iResponse->Result . ":" . $this->iErrorMessage . "\n";
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
        // Required fields in TransactionRequestObject Schema
        $request['IsRecurring'] = false;
        $request['IgnoreDuplicate'] = true;
        $request['CustReceipt'] = false;

        $request['Command'] = "Capture";
        $request['AuthCode'] = $parameters['authorization_code'];
        $request['RefNum'] = $parameters['transaction_identifier'];

		$logContent = "Capture Charge: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
            $response = $this->iClientObject->runTransaction(array("securityToken" => $this->iSecurityToken, "tran" => $request));
            $response = $response->runTransactionResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = jsonEncode($response) . "\n";
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
        // Required fields in TransactionRequestObject Schema
        $request['IsRecurring'] = false;
		$request['IgnoreDuplicate'] = true;
		$request['CustReceipt'] = false;
		$tax = (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : 0);
		$shipping = (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : 0);
		$subtotal = $parameters['amount'] - $tax - $shipping;

		$request['Details'] = array(
			"Invoice" => $parameters['order_number'],
			"PONum" => $parameters['order_number'],
			"OrderID" => $parameters['order_number'],
            "Description" => empty($parameters['description']) ? "Order " . $parameters['order_number'] : $parameters['description'],
			"Amount" => $parameters['amount'],
			"Tax" => $tax,
			"Shipping" => $shipping,
            "NonTax" => $tax == 0, // required
            "Subtotal" => $subtotal, // required
            "Duty" => 0, // required
            "Discount" => 0, // required
            "AllowPartialAuth" => false, // required
            "Tip" => 0, // required
        );
		if (!empty($parameters['card_number'])) {
			$request['Command'] = (empty($parameters['authorize_only']) ? "Sale" : "AuthOnly");
			$request['CreditCardData'] = array(
				"CardNumber" => $parameters['card_number'],
				"CardExpiration" => date("my", strtotime($parameters['expiration_date'])),
				"AvsStreet" => $parameters['address_1'],
				"AvsZip" => $parameters['postal_code'],
                "InternalCardAuth" => false, // required
                "CardPresent" => false // required
                );
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$request['CreditCardData']['CardCode'] = $parameters['card_code'];
				$cleanValues['CreditCardData']["CardCode"] = '';
			}
            $cleanValues['CreditCardData']["CardNumber"] = substr($parameters['card_number'],-4);
            $cleanValues['CreditCardData']["CardExpiration"] = date("my", strtotime($parameters['expiration_date']));
			if (!empty($parameters['track_1']) || !empty($parameters['track_2'])) {
				$request['CreditCardData']['CardPresent'] = true;
				$request['CreditCardData']['MagStripe'] = (empty($parameters['track_1']) ? $parameters['track_2'] : $parameters['track_1']);
                $cleanValues['CreditCardData']["MagStripe"] = '';
			}
		} else if (!empty($parameters['bank_routing_number'])) {
			$request['Command'] = "check";
			$request['CheckData'] = array(
				"Routing" => $parameters['bank_routing_number'],
				"Account" => $parameters['bank_account_number'],
				"AccountType" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking"));
            $cleanValues['CheckData']["Routing"] = substr($parameters['bank_routing_number'],-4);
            $cleanValues['CheckData']["Account"] = substr($parameters['bank_account_number'],-4);
		}
		$request['ClientIP'] = $_SERVER['REMOTE_ADDR'];
        $request['CustomerId'] = empty($parameters['customer_id']) ? $parameters['contact_id'] : $parameters['customer_id'];
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
                $lineItems[] = array("LineItem"=>array(
                    "ProductRefNum" => $thisOrderItem['product_id'],
                    "SKU" => getFieldFromId("product_code", "products", "product_id", $thisOrderItem['product_id']),
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
                    "TaxRate" => ""));
            }
        }
        if (!empty($lineItems)) {
            $request['LineItems'] = $lineItems;
        } else {
            $request['LineItems'] = array("LineItem"=>array(
                "ProductRefNum" => $parameters['order_number'],
                "SKU" => $parameters['order_number'],
                "ProductName" => $parameters['description'],
                "Description" => $parameters['description'],
                "UnitPrice" => $subtotal,
                "Qty" => 1,
                "Taxable" => $tax!==0,
                "CommodityCode" => "",
                "UnitOfMeasure" => "",
                "DiscountAmount" => "",
                "DiscountRate" => "",
                "TaxAmount" => "",
                "TaxRate" => ""));
        }
		$logContent = "Authorize Charge: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->runTransaction(array("securityToken" => $this->iSecurityToken, "tran" => $request));
            $response = $response->runTransactionResult;
		} catch (SoapFault $e) {
			$this->iErrorMessage = $e->getMessage();
            $response = new stdClass();
            $response->Error = $e->getMessage();
		}

		$logContent = jsonEncode($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = json_decode(json_encode($response),true);
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

	// Get the customerToken / custNum (long integer) given the customerInternalId (GUID)
    // customerInternalId is a more reliable identifier, but some methods only take the customerToken.
	private function getCustomerToken($customerInternalId) {
        try {
            $response = $this->iClientObject->GetCustomerToken(array("securityToken" => $this->iSecurityToken, "customerInternalId" => $customerInternalId));
            return $response->GetCustomerTokenResult;
        } catch (SoapFault $e) {
            $this->writeExternalLog(jsonEncode($e));
            $this->iErrorMessage = $e->getMessage();
            $this->iResponse = false;
            return false;
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
			$response = $this->iClientObject->GetCustomer(array("securityToken"=> $this->iSecurityToken, "customerId" => empty($parameters['customer_id']) ? $parameters['contact_id'] : $parameters['customer_id'],
                "customerInternalId" => $parameters['merchant_identifier']));
			$response = $response->GetCustomerResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = jsonEncode($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
        $this->iResponse['raw_response'] = json_decode(json_encode($response),true);
		$this->iResponse['merchant_identifier'] = $response->CustomerInternalId;
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
		$customerData['CustomerId'] = empty($parameters['customer_id']) ? $parameters['contact_id'] : $parameters['customer_id'];
        $customerData["FirstName"] = (empty($parameters['first_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['first_name']);
        $customerData["LastName"] = (empty($parameters['last_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['last_name']);
		$customerData["CompanyName"] = $parameters['business_name'];
        $customerData["Email"] = $parameters['email_address'];

		$customerData['BillingAddress'] = array(
			"Address1" => $parameters['address_1'],
			"City" => $parameters['city'],
			"State" => $parameters['state'],
			"ZipCode" => $parameters['postal_code']);
		$logContent = "Create Customer Profile: " . $this->cleanLogContent($customerData, array()) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->AddCustomer(array("securityToken" => $this->iSecurityToken, "customer" => $customerData));
			$response = $response->AddCustomerResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}
        if($response->Error == "Record already exists") {
            $response = $this->iClientObject->GetCustomer(array("securityToken"=> $this->iSecurityToken, "customerId" => empty($parameters['customer_id']) ? $parameters['contact_id'] : $parameters['customer_id'],
                "customerInternalId" => $parameters['merchant_identifier']));
            $response = $response->GetCustomerResult;
        }

		$logContent = jsonEncode($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
        $this->iResponse['raw_response'] = json_decode(json_encode($response),true);
        if(!empty($response->CustomerInternalId)) {
            $this->iResponse['merchant_identifier'] = $response->CustomerInternalId;
            $this->createMerchantProfile(empty($parameters['customer_id']) ? $parameters['contact_id'] : $parameters['customer_id'], $this->iResponse['merchant_identifier']);
            return true;
        } else {
            $this->iErrorMessage = $response->Error;
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
		$customerToken = $this->getCustomerToken($parameters['merchant_identifier']);
		if(!empty($this->iErrorMessage)) {
		    $this->iResponse = false;
		    return false;
        }
        $logContent = "Get Customer Payment Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
        $this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->GetCustomerPaymentMethodProfile(array("securityToken" => $this->iSecurityToken, "customerToken" => $customerToken,
				"paymentMethodId" => $parameters['account_token']));
			$response = $response->GetCustomerPaymentMethodProfileResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = jsonEncode($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
        $this->iResponse['raw_response'] = json_decode(json_encode($response),true);
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
		$paymentMethod['MethodName'] = $parameters['account_label'];
		$paymentMethod['SecondarySort'] = "0";
		if (!empty($parameters['bank_routing_number'])) {
			$paymentMethod['MethodType'] = "ACH";
			$paymentMethod['AccountType'] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking");
			$paymentMethod['Account'] = $parameters['bank_account_number'];
			$paymentMethod['Routing'] = $parameters['bank_routing_number'];
			$cleanValues["Account"] = substr($parameters['bank_account_number'],-4);
			$cleanValues["Routing"] = substr($parameters['bank_routing_number'],-4);
		} else {
			$paymentMethod['MethodType'] = "CreditCard";
			$paymentMethod['AvsStreet'] = $parameters['address_1'];
			$paymentMethod['AvsZip'] = $parameters['postal_code'];
			$paymentMethod['CardNumber'] = $parameters['card_number'];
			$cleanValues["CardNumber"] = substr($parameters['card_number'],-4);
			$paymentMethod['CardExpiration'] = date("my", strtotime($parameters['expiration_date']));
			$cleanValues["CardExpiration"] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$paymentMethod['CardCode'] = $parameters['card_code'];
				$cleanValues["CardCode"] = '';
			}
			$paymentMethod['Created'] = $paymentMethod['Modified'] = date( DateTime::RFC3339);
		}

		$logContent = "Add Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->AddCustomerPaymentMethodProfile(array("securityToken" => $this->iSecurityToken, "customerInternalId" => $parameters['merchant_identifier'],
				"paymentMethodProfile" => $paymentMethod));
			$response = $response->AddCustomerPaymentMethodProfileResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = jsonEncode($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $response;
		$this->setAccountToken($parameters['account_id'], $parameters['merchant_identifier'], $this->iResponse['account_token']);
		return true;
	}

	// No deleteCustomer web method at eBizCharge
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
        return true;
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

        $customerToken = $this->getCustomerToken($parameters['merchant_identifier']);
        if(!empty($this->iErrorMessage)) {
            $this->iResponse = false;
            return false;
        }
        $logContent = "Delete Customer Payment Profile: " . $this->cleanLogContent($parameters, array()) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->DeleteCustomerPaymentMethodProfile(array("securityToken"=>$this->iSecurityToken, "customerToken"=>$customerToken,
                "paymentMethodId"=>$parameters['account_token']));
			$response = $response->DeleteCustomerPaymentMethodProfileResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$this->iResponse = false;
			return false;
		}

		$logContent = jsonEncode($response) . "\n";
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
		$request = array();
		$request['Command'] = $parameters['authorize_only'] ? "AuthOnly" : "";
		// Required fields in CustomerTransactionRequest
        $request['isRecurring'] = false;
        $request['IgnoreDuplicate'] = true;
        $request['CustReceipt'] = false;
        $request['MerchReceipt'] = false;
        $tax = (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : 0);
        $shipping = (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : 0);
        $subtotal = $parameters['amount'] - $tax - $shipping;

        $request['Details'] = array(
			"Invoice" => $parameters['order_number'],
			"PONum" => $parameters['order_number'],
			"OrderID" => $parameters['order_number'],
			"Description" => empty($parameters['description']) ? "Order " . $parameters['order_number'] : $parameters['description'],
			"Amount" => $parameters['amount'],
			"Tax" => $tax,
			"Shipping" => $shipping,
            "NonTax" => $tax == 0, // required
            "Subtotal" => $subtotal, // required
            "Duty" => 0, // required
            "Discount" => 0, // required
            "AllowPartialAuth" => false, // required
            "Tip" => 0, // required
        );
		$request['ClientIP'] = $_SERVER['REMOTE_ADDR'];
		if (!empty($parameters['card_code']) && $parameters['card_code'] != "SKIP_CARD_CODE") {
			$request['CardCode'] = $parameters['card_code'];
			$cleanValues["CardCode"] = '';
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
        }
        if (!empty($lineItems)) {
            $request['LineItems'] = $lineItems;
        } else {
            $request['LineItems'] = array("LineItem"=>array(
                "ProductRefNum" => $parameters['order_number'],
                "SKU" => $parameters['order_number'],
                "ProductName" => $parameters['description'],
                "Description" => $parameters['description'],
                "UnitPrice" => $subtotal,
                "Qty" => 1,
                "Taxable" => $tax!==0,
                "CommodityCode" => "",
                "UnitOfMeasure" => "",
                "DiscountAmount" => "",
                "DiscountRate" => "",
                "TaxAmount" => "",
                "TaxRate" => ""));
        }

        $customerToken = $this->getCustomerToken($parameters['merchant_identifier']);
        if(!empty($this->iErrorMessage)) {
            $this->iResponse = false;
            return false;
        }
        $logContent = "Create Customer Profile Transaction: " . $this->cleanLogContent($request, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		try {
			$response = $this->iClientObject->runCustomerTransaction(array("securityToken"=>$this->iSecurityToken, "custNum"=>$customerToken,
				"paymentMethodID"=>$parameters['account_token'], "tran"=>$request));
			$response = $response->runCustomerTransactionResult;
		} catch (SoapFault $e) {
			$this->writeExternalLog(jsonEncode($e));
			$this->iErrorMessage = $e->getMessage();
			$response = new stdClass();
            $response->Error = $e->getMessage();
		}

		$logContent = jsonEncode($response) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
        $this->iResponse['raw_response'] = json_decode(json_encode($response),true);
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
		if(!$this->getCustomerPaymentProfile($parameters)) {
            $this->iResponse = false;
            return false;
        }
		$paymentMethod = $this->iResponse['raw_response'];

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
			$cleanValues["CardCode"] = date("my", strtotime($parameters['expiration_date']));
		}

		if ($somethingToUpdate) {
            $customerToken = $this->getCustomerToken($parameters['merchant_identifier']);
            if(!empty($this->iErrorMessage)) {
                $this->iResponse = false;
                return false;
            }

            $logContent = "Update Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
			$this->writeExternalLog($logContent);
			try {
				$response = $this->iClientObject->UpdateCustomerPaymentMethodProfile(array("securityToken" =>$this->iSecurityToken, "customerToken"=> $customerToken,
                    "paymentMethodProfile" => $paymentMethod));
				$response = $response->UpdateCustomerPaymentMethodProfileResult;
			} catch (SoapFault $e) {
                $this->writeExternalLog(jsonEncode($e));
				$this->iErrorMessage = $e->getMessage();
				$this->iResponse = false;
				return false;
			}

			$logContent = jsonEncode($response) . "\n";
			$this->writeExternalLog($logContent);
		}

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['account_token'] = $parameters['account_token'];
		return true;
	}
}
