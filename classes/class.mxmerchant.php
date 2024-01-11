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

class MxMerchant extends eCommerce {

	var $iCredentials = array();
	var $iTestUrl = "https://sandbox.api.mxmerchant.com";
	var $iLiveUrl = "https://api.mxmerchant.com";
	var $iUseUrl = "";
	var $iUserName = "";
	var $iMerchantId = "";
	var $iSecCode = "WEB";
	var $iOptions = array();

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow = array();
		}
		if ($GLOBALS['gDevelopmentServer'] || $this->iMerchantAccountRow['merchant_account_code'] == "DEVELOPMENT") {
			$this->iUseUrl = $this->iTestUrl;
		} else {
			$this->iUseUrl = $this->iLiveUrl;
		}
		if (!empty($this->iMerchantAccountRow['link_url']) && $GLOBALS['gUserRow']['superuser_flag']) {
			$this->iUseUrl = $this->iMerchantAccountRow['link_url'];
		}
		$this->iUserName = $this->iMerchantAccountRow['account_login'];
		$this->iPassword = $this->iMerchantAccountRow['account_key'];
		$this->iMerchantId = $this->iMerchantAccountRow['merchant_identifier'];
        $secCodePref = getPreference('ACH_SEC_CODE');
        if (in_array($secCodePref, array("CCD", "PPD", "TEL", "WEB"))) {
            $this->iSecCode = $secCodePref;
        }
        // MX tokens can be used without a customer token
        $this->iRequiredParameters["delete_customer_payment_profile"] = array("account_token");
        $this->iRequiredParameters["update_customer_payment_profile"] = array("account_token");
        $this->iRequiredParameters["create_customer_payment_transaction_request"] = array("amount", "order_number", "account_token");
    }

    function requiresCustomerToken() {
        return false;
    }

    private function makeRequest($options = array()) {
        $this->iOptions = $options;
        $cleanValues = $options['clean_values'];
        unset($options['clean_values']);

        $url = $this->iUseUrl;

        $curlHandle = curl_init();
        $authHeaderValue = "Basic " . base64_encode($this->iUserName . ":" . $this->iPassword);

        $header = array('Content-Type: application/json', 'Accept: application/json', 'Authorization: ' . $authHeaderValue);

        $configOptions = array();
        $configOptions[CURLOPT_HTTPHEADER] = $header;
        $configOptions[CURLOPT_URL] = trim($url,"/") ."/" . ltrim($options['url'],"/") . (strstr($options['url'], "?") ? "&" : "?" ) . "echo=true";
        $configOptions[CURLOPT_RETURNTRANSFER] = 1;

        if (array_key_exists('method', $options)) {
            $method = strtolower($options['method']);
            switch ($method) {
                case 'post':
                    $configOptions[CURLOPT_POST] = 1;
                    break;
                case 'delete':
                    $configOptions[CURLOPT_CUSTOMREQUEST] = "DELETE";
                    break;
                case 'put':
                    $configOptions[CURLOPT_CUSTOMREQUEST] = "PUT";
                    break;
            }
        }
        if (!empty($options['fields'])) {
            $configOptions[CURLOPT_POSTFIELDS] = jsonEncode($options['fields'], JSON_UNESCAPED_SLASHES);
        }
        foreach ($configOptions as $optionName => $optionValue) {
            curl_setopt($curlHandle, $optionName, $optionValue);
        }
        // link the request and response logs by the number of seconds since midnight UTC
        $timeIdentifier = time() - date("U", strtotime(date("Y-m-d")));
        $logContent = "Mx-Request-$timeIdentifier: " . $options['method'] . " " . $options['url'] . ": " . $this->cleanLogContent($options['fields'], $cleanValues) . "\n";
        $this->writeExternalLog($logContent);

        $rawResponse = curl_exec($curlHandle);

        $logContent = "Mx-Response-$timeIdentifier: " . $this->cleanLogContent($rawResponse, $cleanValues) . "\n";
        $this->writeExternalLog($logContent);

        $this->iResponse = array();
        $this->iResponse['raw_response'] = json_decode(str_replace("\u0022", '"', $rawResponse), true);
        $this->iResponse['http_response'] = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $this->iResponse['url'] = $this->iUseUrl;
        $this->iResponse['header'] = $header;
        $this->iResponse['options'] = $options;
        $this->iResponse['config'] = $configOptions;

        curl_close($curlHandle);

        if (empty($rawResponse) || substr($rawResponse,0,6) == "<html>") {
            return false;
        }

        return json_decode($rawResponse, true);
    }

    private function formatLog($method, $parameters, $request, $response, $successful) {
        $logEntry = $method . ($successful ? " succeeded: " : " failed: ") . $this->iResponse['display-message'];
        if(!$successful) {
            $logEntry .= " (" . $response['risk']['avsResponse'] . ")";
        }
        $logEntry .= "\nCardholder Name: " . ($response['customerName'] ?: ($parameters['first_name'] . " " . $parameters['last_name']));
        $logEntry .= "\nLast4:" . $response['cardAccount']['last4'] ?: substr($request['card_number'] ,-4);
        $logEntry .= "\nAddress: " . $parameters['address_1'] . ", " . $parameters['city'] . ", " .  $parameters['state'] . ", " .  $parameters['postal_code'];
        $logEntry .= "\nEmail: " . $parameters['email_address'];
        $logEntry .= "\nPhone: " . $parameters['phone_number'];
        $logEntry .= "\n\nRequest: " . jsonEncode($request) . "\n\nResponse: " . jsonEncode($response);

        return $logEntry;
    }

	function testConnection() {
		$this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "postal_code" => "75771", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
        $errorsArray = array("Invalid username or password", "Unauthorized", "merchantId required");
        $success = true;
        foreach($errorsArray as $thisErrorMessage) {
            if(stristr($this->iResponse['response_reason_text'], $thisErrorMessage) !== false) {
                $success = false;
            }
        }
        return $success;
	}

	function authorizeCharge($parameters) {
		$isACH = array_key_exists("bank_routing_number", $parameters);
		foreach ($this->iRequiredParameters['authorize_' . ($isACH ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) > 5) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}

		$transaction = array(
		    "merchantId" => $this->iMerchantId,
            "clientReference" => $parameters['order_number'],
			"amount" => number_format($parameters['amount'], 2, ".", "")
		);

		if (!$isACH) {
			$transaction["authOnly"] = (empty($parameters['authorize_only']) ? "False" : "True");
			$transaction['tenderType'] = "Card";
			$cardAccount = array(
			    "number" => $parameters['card_number'],
                "expiryMonth" => date("m", strtotime($parameters['expiration_date'])),
                "expiryYear" => date("Y", strtotime($parameters['expiration_date']))
            );
            if($parameters['country_id'] == 1000) {
                $cardAccount["avsStreet"] = $parameters['address_1'];
                $cardAccount["avsZip"] = str_replace("-","",$parameters['postal_code']);
            }

			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$cardAccount['cvv'] = $parameters['card_code'];
			}
			$transaction['cardAccount'] = $cardAccount;
            $cleanValues = 	array('cardAccount' => array_merge($cardAccount, array(
                "number" => substr($parameters['card_number'],-4),
                "cvv" => (empty($cardAccount['cvv']) ? "none" : "provided")
            )));
		} else {
		    $transaction['tenderType'] = "ACH";
		    $transaction['paymentType'] = "Sale";
			$transaction['entryClass'] = $this->iSecCode;
			$bankAccount = array(
			    "type"=> (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking"),
                "routingNumber"=> $parameters['bank_routing_number'],
                "accountNumber"=> $parameters['bank_account_number'],
                "name"=> ($parameters['first_name'] . " " . $parameters['last_name']) ?: $parameters['business_name']
            );
			$transaction['bankAccount'] = $bankAccount;
			$cleanValues = array("bankAccount" => array_merge($bankAccount, array(
                "accountNumber"=> substr($parameters['bank_account_number'], -4),
                "routingNumber"=>substr($parameters['bank_routing_number'],-4))));
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => "checkout/v3/payment",
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $rawResponse['code'];
			if (!$isACH) {
				$this->iResponse['result'] = $rawResponse['status'];
				$this->iResponse['authorization_code'] = $rawResponse['authCode'];
				$this->iResponse['avs_response'] = $rawResponse['risk']['avsResponseCode'];
				$this->iResponse['card_code_response'] = $rawResponse['risk']['cvvResponseCode'];
				$this->iResponse['transaction_id'] = $rawResponse['id'];
			} else {
                $this->iResponse['result'] = $rawResponse['status'];
                $this->iResponse['transaction_id'] = $rawResponse['id'];
			}
            $message = $rawResponse['authMessage'];
            $message = $message ?: $rawResponse['message'];
            if(is_array($rawResponse['details']) && $rawResponse['details'][0] != $message) {
                $message .= " " . $rawResponse['details'][0];
            }
			$this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $message;
		}

		$successful = $rawResponse['status'] == "Approved";

		if (empty($parameters['test_connection'])) {
			$logParameters = array_merge($transaction, $cleanValues);
			$this->writeLog((!$isACH ? $parameters['card_number'] : $parameters['bank_account_number']),
                $this->formatLog(($parameters['authorize_only'] ? "Preauth" : "Charge"), $parameters,$logParameters,$rawResponse, $successful), !$successful);
		}
		return $successful;
	}

	function createCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		// customer profile is separate from payment token.
		if (empty($parameters['merchant_identifier'])) {
			if (!$this->createCustomerProfile($parameters)) {
				return false;
			}
			$parameters['merchant_identifier'] = $this->iResponse['merchant_identifier'];
		}

		$isACH = array_key_exists("bank_routing_number", $parameters);

		foreach ($this->iRequiredParameters['create_customer_payment_profile_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
        if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) > 5) || $parameters['country_id'] == "1000") {
            $parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
        }

        if (!$isACH) {
            $methodUrl = '/checkout/v3/customercardaccount?id=' . $parameters['merchant_identifier'];
            $transaction['number'] = $parameters['card_number'];
            $transaction["expiryMonth"] = date("m", strtotime($parameters['expiration_date']));
            $transaction["expiryYear"] = date("Y", strtotime($parameters['expiration_date']));
            $transaction["avsStreet"] = $parameters['address_1'];
            $transaction["avsZip"] = str_replace("-","",$parameters['postal_code']);
            if ($parameters['card_code'] != "SKIP_CARD_CODE") {
                $transaction['cvv'] = $parameters['card_code'];
            }
            $cleanValues = array('number' => substr($parameters['card_number'], -4), 'cvv' => (empty($transaction['cvv']) ? "none" : "provided"));
        } else {
            $methodUrl = '/checkout/v3/customerbankaccount?id=' . $parameters['merchant_identifier'];
            $transaction["type"] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "Savings" : "Checking");
            $transaction["routingNumber"] = $parameters['bank_routing_number'];
            $transaction["accountNumber"] = $parameters['bank_account_number'];
            $transaction["name"] = ($parameters['first_name'] . " " . $parameters['last_name']) ?: $parameters['business_name'];
            $cleanValues = array('accountNumber' => substr($parameters['bank_account_number'], -4),
                'routingNumber' => substr($parameters['bank_routing_number'], -4));
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => $methodUrl,
			'fields' => $transaction,
            'clean_values' => $cleanValues
		));

		$successful = true;
		if (!empty($rawResponse)) {
		    if(array_key_exists("errorCode",$rawResponse)) {
		        $this->iErrorMessage = $rawResponse['message'];
		        $successful = false;
            }
            $this->iResponse['account_token'] = $rawResponse['token'];
            $this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		}
		if ($successful) {
			$this->setAccountToken($parameters['account_id'], $parameters['merchant_identifier'], $this->iResponse['account_token']);
		}
		return $successful;
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
        if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) > 5) || $parameters['country_id'] == "1000") {
            $parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
        }
        $customer = array(
		    "merchantId" => $this->iMerchantId,
			"name" => ($parameters['first_name'] . " " . $parameters['last_name']) ?: $parameters['business_name'],
			"firstName" => $parameters['first_name'],
			"lastName" => $parameters['last_name'],
            "address1" => $parameters['address_1'],
            "address2"  => $parameters['address_2'],
            "city" => $parameters['city'],
            "state" => $parameters['state'],
            "zip" => $parameters['postal_code'],
            "email" => $parameters['email_address'],
            "phone" => $parameters['phone_number']
		);


		$response = $this->makeRequest(array(
			'method' => 'POST',
			'url' => '/checkout/v3/customer',
			'fields' => $customer
		));
		$this->iResponse['merchant_identifier'] = $response['id'];

		$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);
		return true;
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
            'method' => 'DELETE',
            'url' => 'checkout/v3/payment/' . $parameters['transaction_identifier'] . ($parameters['force_refund'] ? "?force=true" : "")
        ));

		$success = $this->iResponse['http_response'] == 204;
		if(!$success) {
		    $this->iErrorMessage = $rawResponse['message'];
            if(!empty($rawResponse['details'][0])) {
                $this->iErrorMessage .= ": " . $rawResponse['details'][0];
            }
        }

		if (empty($parameters['test_connection'])) {
            $this->writeLog($parameters['transaction_identifier'], "Void: Transaction ID " . $parameters['transaction_identifier'] . ($success ? " succeeded." : " failed: " . $this->iErrorMessage) ."\n" . jsonEncode($rawResponse), !$success);
		}
		return $success;
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
        // MX has two ways to refund:
        // - to refund the full transaction, call void with force=true.
        // - to refund a partial transaction without original card number, we need the paymentToken, which we get by calling /payment/{id}

        $payment = $this->makeRequest(array(
            'url' => "checkout/v3/payment/" . $parameters['transaction_identifier']
        ));
		if(!array_key_exists('amount', $payment) || !array_key_exists('paymentToken', $payment)) {
		    $this->iErrorMessage = "Transaction to refund could not be found.";
		    return false;
		}
		$paymentAmount = $payment['amount'];
		$paymentToken = $payment['paymentToken'];

		if($paymentAmount == $parameters['amount']) {
		    $parameters['force_refund'] = true;
		    return $this->voidCharge($parameters);
        }

        $transaction = array(
            "merchantId" => $this->iMerchantId,
            "amount" => number_format(-1 * $parameters['amount'], 2, ".", ""),
            "tenderType" => "Card",
            "paymentToken" => $paymentToken
        );

        $rawResponse = $this->makeRequest(array(
            'method' => 'POST',
            'fields' => $transaction,
            'url' => '/checkout/v3/payment'
        ));

		if (!empty($rawResponse)) {
            $this->iResponse['result'] = $rawResponse['status'];
            $this->iResponse['authorization_code'] = $rawResponse['authCode'];
			$this->iResponse['display-message'] = $rawResponse['authMessage'];
			$this->iResponse['transaction_id'] = $rawResponse['id'];
			if (empty($this->iResponse['display-message']) && !empty($rawResponse['error']) && $rawResponse['status'] == "fail") {
				$this->iResponse['response_reason_text'] = $rawResponse['error']['error-message'];
			}
		}
        $success = (is_array($rawResponse) && $rawResponse['status'] == "Approved");

		if (empty($parameters['test_connection'])) {
            $this->writeLog($parameters['transaction_identifier'], "Refund: Transaction ID " . $parameters['transaction_identifier']
                . " for amount " . $parameters['amount'] . ($success ? " succeeded." : " failed: " . $this->iResponse['response_reason_text']) ."\n" . jsonEncode($rawResponse), !$success);

		}
		return $success;
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

        $payment = $this->makeRequest(array(
            'url' => "checkout/v3/payment/" . $parameters['transaction_identifier']
        ));

		if (empty($payment['paymentToken']) || (is_array($payment['paymentToken']) && count($payment['paymentToken']) != 1) || $payment['id'] != $parameters['transaction_identifier']) {
			$this->iErrorMessage = "Transaction not found.";
			return false;
		}

        $transaction = array(
            "merchantId" => $this->iMerchantId,
            "paymentToken" => $payment['paymentToken'],
            "tenderType" => "Card",
            "amount" => number_format($payment['amount'], 2, ".", ""),
            "authCode" => $parameters['authorization-code'],
            "authOnly" => "false"
        );

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => $transaction,
			'url' => 'checkout/v3/payment'
		));

        if (!empty($rawResponse)) {
            $this->iResponse['result'] = $rawResponse['status'];
            $this->iResponse['authorization_code'] = $rawResponse['authCode'];
            $this->iResponse['display-message'] = $rawResponse['authMessage'];
            $this->iResponse['transaction_id'] = $rawResponse['id'];
            if (empty($this->iResponse['display-message']) && !empty($rawResponse['error']) && $rawResponse['status'] == "fail") {
                $this->iResponse['response_reason_text'] = $rawResponse['error']['error-message'];
            }
        }

        $success = (is_array($rawResponse) && $rawResponse['status'] == "Approved");
        if (empty($parameters['test_connection'])) {
            $this->writeLog($parameters['transaction_identifier'], "Capture" . ($success ? " succeeded." : " failed: " . $this->iResponse['response_reason_text']) . jsonEncode($rawResponse), !$success);

        }

        return (is_array($rawResponse) && $rawResponse['status'] == "Approved");
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		$response = $this->makeRequest(array(
			'method' => 'GET',
			'url' => 'checkout/v3/customer/' . $parameters['merchant_identifier']
		));

		if ($response) {
			$this->iResponse = array();
			$this->iResponse['merchant_identifier'] = $response['id'];
			return true;
		} else {
			return false;
		}
	}

    // No deleteCustomer web method at MxMerchant
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
        if(!$this->getCustomerPaymentProfile($parameters)) {
            $this->iResponse = false;
            return false;
        }
        $paymentMethod = $this->iResponse['payment_method'];
        $subId = $paymentMethod['id'];
        $isACH = !empty($paymentMethod['routingNumber']);

		if (!$isACH) {
			$methodUrl = "checkout/v3/customercardaccount?id=" . $parameters['merchant_identifier'] . "&subId=" . $subId;
		} else {
			$methodUrl = "checkout/v3/customerbankaccount?id=" . $parameters['merchant_identifier'] . "&subId=" . $subId;
		}

		$this->makeRequest(array(
			'method' => 'DELETE',
			'url' => $methodUrl
		));

		return $this->iResponse['http_response'] == 204;
	}

	function createCustomerProfileTransactionRequest($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
        if(!empty($parameters['address_id'])) {
            $addressRow = getRowFromId("addresses", "address_id", $parameters['address_id']);
            $parameters = array_merge(array_filter($addressRow), $parameters);
        }
		foreach ($this->iRequiredParameters['create_customer_payment_transaction_request'] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
        $isACH = false;
        if(!empty($parameters['merchant_identifier'])) {
            if(!$this->getCustomerPaymentProfile($parameters)) {
                $this->iErrorMessage = $this->iErrorMessage ?: "Payment Profile not found.";
                return false;
            }
            $account = $this->iResponse['payment_method'];
            $isACH = !empty($account['routingNumber']);
            $parameters['account_token'] = $account['token'];
        }
        if(is_numeric($parameters['account_token'])) { // legacy vault id, not token (would be updated by getCustomerProfile if possible)
            $this->iErrorMessage = "This saved account must be updated before it can be used for a transaction.";
            return false;
        }
        if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) > 5) || $parameters['country_id'] == "1000") {
            $parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
        }

        $transaction = array(
            "merchantId" => $this->iMerchantId,
            "amount" => number_format($parameters['amount'], 2, ".", "")
        );

        if (!$isACH) {
            $transaction["authOnly"] = (empty($parameters['authorize_only']) ? "False" : "True");
            $transaction['tenderType'] = "Card";
            $cardAccount = array(
                "token" => $parameters['account_token']
            );
            if($parameters['country_id'] == 1000) {
                $cardAccount["avsStreet"] = $parameters['address_1'];
                $cardAccount["avsZip"] = str_replace("-","",$parameters['postal_code']);
            }
            if (!empty($parameters['card_code']) && $parameters['card_code'] != "SKIP_CARD_CODE") {
                $cardAccount['cvv'] = $parameters['card_code'];
            }
            $transaction['cardAccount'] = $cardAccount;
            $cleanValues = 	array('cardAccount' => array_merge($cardAccount, array(
                "token" => substr($parameters['account_token'],-4),
                "cvv" => (empty($cardAccount['cvv']) ? "none" : "provided")
            )));
        } else {
            $transaction['tenderType'] = "ACH";
            $transaction['paymentType'] = "Sale";
            $transaction['entryClass'] = $this->iSecCode;
            $bankAccount = array(
                "token"=> $parameters['account_token']
            );
            $transaction['bankAccount'] = $bankAccount;
            $cleanValues = array_merge($bankAccount, array("bankAccount" => array(
                "token"=> substr($parameters['account_token'], -4)
            )));
        }

        $rawResponse = $this->makeRequest(array(
            'method' => 'POST',
            'url' => "checkout/v3/payment",
            'fields' => $transaction,
            'clean_values' => $cleanValues
        ));

        if (!empty($rawResponse)) {
            $this->iResponse['code'] = $rawResponse['code'];
            if (!$isACH) {
                $this->iResponse['result'] = $rawResponse['status'];
                $this->iResponse['authorization_code'] = $rawResponse['authCode'];
                $this->iResponse['avs_response'] = $rawResponse['risk']['avsResponseCode'];
                $this->iResponse['card_code_response'] = $rawResponse['risk']['cvvResponseCode'];
                $this->iResponse['transaction_id'] = $rawResponse['id'];
            } else {
                $this->iResponse['result'] = $rawResponse['status'];
                $this->iResponse['transaction_id'] = $rawResponse['id'];
            }
            $message = $rawResponse['authMessage'];
            $message = $message ?: $rawResponse['message'];
            if(is_array($rawResponse['details']) && $rawResponse['details'][0] != $message) {
                $message .= " " . $rawResponse['details'][0];
            }
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $message;
        }

        $successful = $rawResponse['status'] == "Approved";

        if (empty($parameters['test_connection'])) {
            $logParameters = array_merge($transaction, $cleanValues);
            $this->writeLog($transaction['account_token'],$this->formatLog(($parameters['authorize_only'] ? "Preauth Token" : "Charge Token"),
                $parameters,$logParameters,$rawResponse,$successful), !$successful);
        }
        return $successful;
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
        if(strpos($parameters['account_token'], "ach") !== false) {
            $isACH = true;
            $parameters['account_token'] = str_replace("ach","", $parameters['account_token']);
        };

        $useId = is_numeric($parameters['account_token']);  // legacy record - id of vaulted card, not token

        $methodUrls =array();
        if(!$isACH) {
            $methodUrls['card'] = "checkout/v3/customercardaccount?id=" . $parameters['merchant_identifier'];
        }
        $methodUrls['bank'] = "checkout/v3/customerbankaccount?id=" . $parameters['merchant_identifier'];

        $found = false;
        foreach($methodUrls as $methodUrl) {
            $rawResponse = $this->makeRequest(array(
                'method' => 'GET',
                'url' => $methodUrl
            ));

            if (!empty($rawResponse)) {
                foreach ($rawResponse['records'] as $thisRecord) {
                    if ($useId) {
                        if ($thisRecord['id'] == $parameters['account_token']) {
                            $found = true;
                            executeQuery("update accounts set account_token = ? where merchant_identifier = ? and account_token = ?",
                                $thisRecord['token'], $parameters['merchant_identifier'], $parameters['account_token']);
                            $this->iResponse['payment_method'] = $thisRecord;
                            break 2;
                        }
                    } else {
                        if ($thisRecord['token'] == $parameters['account_token']) {
                            $found = true;
                            $this->iResponse['payment_method'] = $thisRecord;
                            break 2;
                        }
                    }
                }
            }
        }
        if($found) {
            $this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
            $this->iResponse['payment_profile'] = $this->iResponse['payment_method']['token'];
            $isACH = !empty($this->iResponse['payment_method']['routingNumber']);
            if (!$isACH) {
                $this->iResponse['card_number'] = $this->iResponse['payment_method']['last4'];
                $this->iResponse['expiration_date'] = $this->iResponse['payment_method']['expiryMonth'] . "/" . $this->iResponse['payment_method']['expiryYear'];
            } else {
                $this->iResponse['account_type'] = $this->iResponse['payment_method']['type'];
                $this->iResponse['routing_number'] = $this->iResponse['payment_method']['routingNumber'];
                $this->iResponse['account_number'] = $this->iResponse['payment_method']['last4'];
            }
        } else {
            $this->iErrorMessage = "Payment profile not found.";
        }

		return $found;
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
        if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) > 5) || $parameters['country_id'] == "1000") {
            $parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
        }
        if(!$this->getCustomerPaymentProfile($parameters)) {
            $this->iResponse = false;
            return false;
        }
        $paymentMethod = $this->iResponse['payment_method'];
        $subId = $paymentMethod['id'];
        $isACH = !empty($paymentMethod['routingNumber']);

        $cleanValues = array();
        $somethingToUpdate = false;
        if (array_key_exists("address_1", $parameters)) {
            $somethingToUpdate = true;
            $paymentMethod['avsStreet'] = $parameters['address_1'];
        }
        if (array_key_exists("postal_code", $parameters)) {
            $somethingToUpdate = true;
            $paymentMethod["avsZip"] = str_replace("-","",$parameters['postal_code']);
        }
        if (array_key_exists("expiration_date", $parameters)) {
            $somethingToUpdate = true;
            $paymentMethod['expiryMonth'] = date("m", strtotime($parameters['expiration_date']));
            $paymentMethod['expiryYear'] = date("Y", strtotime($parameters['expiration_date']));
            $cleanValues['expiryMonth'] = date("m", strtotime($parameters['expiration_date']));
            $cleanValues['expiryYear'] = date("Y", strtotime($parameters['expiration_date']));
        }
        if (array_key_exists("card_code", $parameters)) {
            $somethingToUpdate = true;
            $paymentMethod['cvv'] = $parameters['card_code'];
            $cleanValues["cvv"] = (empty($paymentMethod['cvv']) ? "none" : "provided");
        }

        if ($somethingToUpdate) {
            if (!$isACH) {
                $methodUrl = "checkout/v3/customercardaccount?id=" . $parameters['merchant_identifier'] . "&subId=" . $subId;
            } else {
                $methodUrl = "checkout/v3/customerbankaccount?id=" . $parameters['merchant_identifier'] . "&subId=" . $subId;
            }
            $logContent = "Update Customer Payment Profile: " . $this->cleanLogContent($paymentMethod, $cleanValues) . "\n";
            $this->writeExternalLog($logContent);

            $rawResponse = $this->makeRequest(array(
                'method' => 'POST',
                'url' => $methodUrl,
                'fields' => $paymentMethod
            ));

            $logContent = serialize($rawResponse) . "\n";
            $this->writeExternalLog($logContent);
        }

        $this->iResponse['account_token'] = $paymentMethod['token'];
        return $this->iResponse['http_response'] == 201;
    }
}
