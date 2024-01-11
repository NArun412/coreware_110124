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

class TNBC extends eCommerce {
	private $iLiveUrl = "https://secure.tnbcigateway.com/api/transact.php";
    private $iUserName = "";
    private $iSecCode = "WEB";

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow = array();
		}
		$this->iUserName = $this->iMerchantAccountRow['account_login'];
		$this->iPassword = $this->iMerchantAccountRow['account_key'];
        if (!empty($this->iMerchantAccountRow['link_url']) && !startsWith($this->iMerchantAccountRow['link_url'], "http")) {
            $this->iMerchantAccountRow['link_url'] = "https://" . $this->iMerchantAccountRow['link_url'];
        }
        $this->iLiveUrl = $this->iMerchantAccountRow['link_url'] ?: $this->iLiveUrl;
        if(!endsWith($this->iLiveUrl, "/api/transact.php")) {
            $this->iLiveUrl = rtrim($this->iLiveUrl, "/") . "/api/transact.php";
        }
        $secCodePref = getPreference('ACH_SEC_CODE');
        if (in_array($secCodePref, array("CCD", "PPD", "TEL", "WEB"))) {
            $this->iSecCode = $secCodePref;
        }
    }

	function testConnection() {
		$result = $this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return ($this->iResponse['response_reason_text'] != "Authentication Failed");
	}

	private function doPost($requestType, $parameters, $cleanValues) {
        if(!empty($this->iUserName) && strtolower($this->iUserName) != "n/a") {
            $query = "username=" . $this->iUserName . "&password=" . $this->iPassword;
        } else {
            $query = "security_key=" . $this->iPassword;
        }
		$parameters['ipaddress'] = $_SERVER['REMOTE_ADDR'];
		foreach ($parameters as $parameterName => $parameterValue) {
			$query .= "&" . $parameterName . "=" . $parameterValue;
		}
		$logContent = $requestType . ": " . $this->cleanLogContent($parameters, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iLiveUrl);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		curl_setopt($ch, CURLOPT_POST, 1);

		if (!($response = curl_exec($ch))) {
			$this->iErrorMessage = "No response from Merchant Services";
			$this->iResponse = false;
			return false;
		}
		curl_close($ch);
		unset($ch);

		$this->writeExternalLog($response);
		$data = explode("&", $response);
		$rawResponse = array();
		foreach ($data as $responsePart) {
			$rdata = explode("=", $responsePart);
			$rawResponse[$rdata[0]] = $rdata[1];
		}

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		$this->iResponse['raw_response_array'] = $rawResponse;
		$this->iResponse['response_code'] = $rawResponse['response'];
		$this->iResponse['response_reason_code'] = $rawResponse['response_code'];
		$this->iResponse['response_reason_text'] = $rawResponse['responsetext'];
		$this->iResponse['authorization_code'] = $rawResponse['authcode'];
		$this->iResponse['avs_response'] = $rawResponse['avsresponse'];
		$this->iResponse['card_code_response'] = $rawResponse['cvvresponse'];
		$this->iResponse['transaction_id'] = $rawResponse['transactionid'];
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['ccnumber']) ? $parameters['ccnumber'] : $parameters['checkaccount']), $this->iResponse['response_reason_text'], ($this->iResponse['response_code'] != "1"));
		}

		return ($this->iResponse['response_code'] == "1");

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
		$postParameters = array("type" => "void", "transactionid" => $parameters['transaction_identifier']);

		return $this->doPost("Void", $postParameters, array());
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
        $postParameters = array("type" => "refund", "transactionid" => $parameters['transaction_identifier']);
        if ($parameters['amount'] > 0) {
            $postParameters['amount'] = $parameters['amount'];
        }

        return $this->doPost("Refund", $postParameters, array());
    }

        function captureCharge($parameters) {
		foreach ($this->iRequiredParameters['capture_charge'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		$cleanValues = array();
		$postParameters = array("type" => "capture");
		$postParameters['transactionid'] = $parameters['transaction_identifier'];

		return $this->doPost("Capture Charge", $postParameters, $cleanValues);
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
		$postParameters = array("type" => "sale");

		if (!empty($parameters['card_number'])) {
			$postParameters['ccnumber'] = $parameters['card_number'];
			$postParameters['ccexp'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$postParameters['cvv'] = $parameters['card_code'];
				$cleanValues['cvv'] = "";
			}
			$cleanValues["ccnumber"] = substr($parameters['card_number'], -4);
			$cleanValues["ccexp"] = date("my", strtotime($parameters['expiration_date']));
			if (!empty($parameters['track_1']) || !empty($parameters['track_2'])) {
				$postParameters['track_1'] = $parameters['track_1'];
				$postParameters['track_2'] = $parameters['track_2'];
				$cleanValues["track_1"] = "";
				$cleanValues["track_2"] = "";
			}
			$postParameters['payment'] = "creditcard";
		} else if (!empty($parameters['bank_routing_number'])) {
			$name = $parameters['first_name'];
			$name .= (empty($name) ? "" : " ") . $parameters['last_name'];
			$name .= (empty($name) ? "" : " ") . $parameters['business_name'];
			$postParameters['checkname'] = $name;
			$postParameters['checkaba'] = $parameters['bank_routing_number'];
			$postParameters['checkaccount'] = $parameters['bank_account_number'];
			$postParameters['account_holder_type'] = ((!empty($parameters['business_name']) || $this->iSecCode == "CCD") ? "business" : "personal");
            $postParameters['account_type'] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking");
			$postParameters['sec_code'] = $this->iSecCode;
			$postParameters['payment'] = "check";
			$cleanValues["checkaba"] =  substr($parameters['bank_routing_number'], -4);
			$cleanValues["checkaccount"] = substr($parameters['bank_account_number'], -4);
		}
		$postParameters['firstname'] = $parameters['first_name'];
		$postParameters['lastname'] = $parameters['last_name'];
		$postParameters['company'] = $parameters['business_name'];
		$postParameters['orderid'] = $parameters['order_number'];
		$postParameters['orderdescription'] = $parameters['description'];
		$postParameters['amount'] = $parameters['amount'];
		$postParameters['tax'] = (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : "0");
		$postParameters['shipping'] = (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : "0");
		$postParameters['address1'] = $parameters['address_1'];
		$postParameters['address2'] = $parameters['address_2'];
		$postParameters['city'] = $parameters['city'];
		$postParameters['state'] = $parameters['state'];
		$postParameters['zip'] = $parameters['postal_code'];
		$postParameters['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
		$postParameters['email'] = $parameters['email_address'];
		if (!empty($parameters['po_number'])) {
			$postParameters['ponumber'] = $parameters['po_number'];
		}
		if (!empty($parameters['fax'])) {
			$postParameters['fax'] = $parameters['fax'];
		}
		if (!empty($parameters['phone_number'])) {
			$postParameters['phone'] = $parameters['phone_number'];
		}
		if (!empty($parameters['web_page'])) {
			$postParameters['website'] = $parameters['web_page'];
		}
		if (!empty($parameters['shipping_first_name'])) {
			$postParameters['shipping_firstname'] = $parameters['shipping_first_name'];
		}
		if (!empty($parameters['shipping_last_name'])) {
			$postParameters['shipping_lastname'] = $parameters['shipping_last_name'];
		}
		if (!empty($parameters['shipping_business_name'])) {
			$postParameters['shipping_company'] = $parameters['shipping_business_name'];
		}
		if (!empty($parameters['shipping_address_1'])) {
			$postParameters['shipping_address1'] = $parameters['shipping_address_1'];
		}
		if (!empty($parameters['shipping_address_2'])) {
			$postParameters['shipping_address2'] = $parameters['shipping_address_2'];
		}
		if (!empty($parameters['shipping_city'])) {
			$postParameters['shipping_city'] = $parameters['shipping_city'];
		}
		if (!empty($parameters['shipping_state'])) {
			$postParameters['shipping_state'] = $parameters['shipping_state'];
		}
		if (!empty($parameters['shipping_postal_code'])) {
			$postParameters['shipping_zip'] = $parameters['shipping_postal_code'];
		}
		if (!empty($parameters['shipping_country_id'])) {
			$postParameters['shipping_country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['shipping_country_id']);
		}
		if (!empty($parameters['shipping_email'])) {
			$postParameters['shipping_email'] = $parameters['shipping_email'];
		}
		$postParameters['test_connection'] = $parameters['test_connection'];

		return $this->doPost("Authorize Charge", $postParameters, $cleanValues);
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		$this->iResponse = array();
		$this->iResponse['raw_response'] = "";
		$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
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

		$merchantIdentifier = "tnbc-" . $parameters['contact_id'] . "-" . strtoupper(getRandomString(6));
		$this->iResponse = array();
		$this->iResponse['raw_response'] = "";
		$this->iResponse['raw_response_array'] = array();
		$this->iResponse['merchant_identifier'] = $merchantIdentifier;
		$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);

		return true;
	}

	function getCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		$this->iResponse = array();
		$this->iResponse['raw_response'] = "";
		$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		$this->iResponse['account_token'] = $parameters['account_token'];
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

		if (!empty($parameters['card_number']) && !empty($parameters['card_code'])) {
			$cleanValues = array();
			$postParameters = array("type" => "validate");

			$postParameters['ccnumber'] = $parameters['card_number'];
			$postParameters['ccexp'] = date("my", strtotime($parameters['expiration_date']));
			$postParameters['cvv'] = $parameters['card_code'];
			$cleanValues["cvv"] = "";
			$cleanValues["ccnumber"] = substr($parameters['card_number'], -4);
			$cleanValues["ccexp"] = date("my", strtotime($parameters['expiration_date']));

			$postParameters['payment'] = "creditcard";
			$postParameters['firstname'] = $parameters['first_name'];
			$postParameters['lastname'] = $parameters['last_name'];
			$postParameters['company'] = $parameters['business_name'];
			$postParameters['orderid'] = $parameters['order_number'];
			$postParameters['orderdescription'] = $parameters['description'];
			$postParameters['address1'] = $parameters['address_1'];
			$postParameters['address2'] = $parameters['address_2'];
			$postParameters['city'] = $parameters['city'];
			$postParameters['state'] = $parameters['state'];
			$postParameters['zip'] = $parameters['postal_code'];
			$postParameters['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
			$postParameters['email'] = $parameters['email_address'];
			$testValue = $this->doPost("Validate CC", $postParameters, $cleanValues);
			if (!$testValue) {
				return false;
			}
		}

		$cleanValues = array();
		$postParameters = array("customer_vault" => "add_customer");

		$contactId = $parameters['contact_id'];
		$postParameters['customer_vault_id'] = "tnbc-" . $contactId . "-" . strtoupper(getRandomString(6));

		if (!empty($parameters['card_number'])) {
			$postParameters['ccnumber'] = $parameters['card_number'];
			$postParameters['ccexp'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$postParameters['cvv'] = $parameters['card_code'];
				$cleanValues["cvv"] = "";
			}
			$cleanValues["ccnumber"] = substr($parameters['card_number'], -4);
			$cleanValues["ccexp"] = date("my", strtotime($parameters['expiration_date']));
			if (!empty($parameters['track_1']) || !empty($parameters['track_2'])) {
				$postParameters['track_1'] = $parameters['track_1'];
				$postParameters['track_2'] = $parameters['track_2'];
				$cleanValues["track_1"] = "";
				$cleanValues["track_2"] = "";
			}
			$postParameters['payment'] = "creditcard";
		} else if (!empty($parameters['bank_routing_number'])) {
			$name = $parameters['first_name'];
			$name .= (empty($name) ? "" : " ") . $parameters['last_name'];
			$name .= (empty($name) ? "" : " ") . $parameters['business_name'];
			$postParameters['checkname'] = $name;
			$postParameters['checkaba'] = $parameters['bank_routing_number'];
			$postParameters['checkaccount'] = $parameters['bank_account_number'];
            $postParameters['account_holder_type'] = ((!empty($parameters['business_name']) || $this->iSecCode == "CCD") ? "business" : "personal");
            $postParameters['account_type'] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking");
			$postParameters['sec_code'] = $this->iSecCode;
			$postParameters['payment'] = "check";
			$cleanValues["checkaba"] = substr($parameters['bank_routing_number'], -4);
			$cleanValues["checkaccount"] = substr($parameters['bank_account_number'],-4);
		}
		$postParameters['first_name'] = (empty($parameters['first_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['first_name']);
		$postParameters['last_name'] = (empty($parameters['last_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['last_name']);
		$postParameters['company'] = $parameters['business_name'];
		$postParameters['address1'] = $parameters['address_1'];
		$postParameters['city'] = $parameters['city'];
		$postParameters['state'] = $parameters['state'];
		$postParameters['zip'] = $parameters['postal_code'];
		$postParameters['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
		$postParameters['email'] = $parameters['email_address'];

		$returnValue = $this->doPost("Create Customer Payment Profile", $postParameters, $cleanValues);
		if ($returnValue) {
			$this->iResponse['merchant_identifier'] = $postParameters['customer_vault_id'];
			$this->iResponse['account_token'] = $postParameters['customer_vault_id'];
			$this->setAccountToken($parameters['account_id'], $postParameters['customer_vault_id'], $this->iResponse['account_token']);
		}
		return $returnValue;
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

		$postParameters = array();
		$cleanValues = array();
		$postParameters = array("customer_vault" => "delete_customer");
		$postParameters['customer_vault_id'] = $parameters['account_token'];

		$returnValue = $this->doPost("Delete Customer Payment Profile", $postParameters, $cleanValues);
		if ($returnValue && !empty($parameters['account_id'])) {
			$this->deleteCustomerAccount($parameters['account_id']);
		}
		return $returnValue;
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
		$postParameters = array();
		$postParameters['customer_vault_id'] = $parameters['account_token'];
		$postParameters['orderid'] = $parameters['order_number'];
		$postParameters['orderdescription'] = $parameters['description'];
		$postParameters['amount'] = $parameters['amount'];
		$postParameters['tax'] = (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : "0");
		$postParameters['shipping'] = (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : "0");
		$postParameters['type'] = ($parameters['authorize_only'] ? "auth" : "sale");

		return $this->doPost("Create Customer Profile Transaction", $postParameters, $cleanValues);
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

		$postParameters = array();
		$cleanValues = array();
		$postParameters = array("customer_vault" => "update_customer");

		$postParameters['customer_vault_id'] = $parameters['account_token'];
		if (!empty($parameters['card_number'])) {
			$postParameters['ccnumber'] = $parameters['card_number'];
			$postParameters['ccexp'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$postParameters['cvv'] = $parameters['card_code'];
				$cleanValues["cvv"] = "";
			}
			$cleanValues["ccnumber"] = substr($parameters['card_number'], -4);
			$cleanValues["ccexp"] = date("my", strtotime($parameters['expiration_date']));
			if (!empty($parameters['track_1']) || !empty($parameters['track_2'])) {
				$postParameters['track_1'] = $parameters['track_1'];
				$postParameters['track_2'] = $parameters['track_2'];
                $cleanValues["track_1"] = "";
                $cleanValues["track_2"] = "";
			}
			$postParameters['payment'] = "creditcard";
		} else if (!empty($parameters['bank_routing_number'])) {
			$name = $parameters['first_name'];
			$name .= (empty($name) ? "" : " ") . $parameters['last_name'];
			$name .= (empty($name) ? "" : " ") . $parameters['business_name'];
			$postParameters['checkname'] = $name;
			$postParameters['checkaba'] = $parameters['bank_routing_number'];
			$postParameters['checkaccount'] = $parameters['bank_account_number'];
            $postParameters['account_holder_type'] = ((!empty($parameters['business_name']) || $this->iSecCode == "CCD") ? "business" : "personal");
            $postParameters['account_type'] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking");
			$postParameters['sec_code'] = $this->iSecCode;
			$postParameters['payment'] = "check";
			$cleanValues["checkaba"] = substr($parameters['bank_routing_number'], -4);
			$cleanValues["checkaccount"] =  substr($parameters['bank_account_number'], -4);
		}
		$postParameters['first_name'] = (empty($parameters['first_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['first_name']);
		$postParameters['last_name'] = (empty($parameters['last_name']) && !empty($parameters['business_name']) ? $parameters['business_name'] : $parameters['last_name']);
		$postParameters['company'] = $parameters['business_name'];
		$postParameters['address1'] = $parameters['address_1'];
		$postParameters['city'] = $parameters['city'];
		$postParameters['state'] = $parameters['state'];
		$postParameters['zip'] = $parameters['postal_code'];
		$postParameters['country'] = getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']);
		$postParameters['email'] = $parameters['email_address'];

		return $this->doPost("Update Customer Payment Profile", $postParameters, $cleanValues);
	}
}
