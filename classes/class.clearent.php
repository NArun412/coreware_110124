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

class Clearent extends eCommerce {

	var $iCredentials = array();
	var $iTestUrl = "https://gateway-sb.clearent.net/rest/v2";
	var $iLiveUrl = "https://gateway.clearent.net/rest/v2";
	var $iVersion = "";
	var $iUseUrl = "";
	var $iUserName = "";
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
		$this->iVersion = date("Y-m-d\TH:i:s", filemtime(__FILE__));
        $secCodePref = getPreference('ACH_SEC_CODE');
        if (in_array($secCodePref, array("CCD", "PPD", "TEL", "WEB"))) {
            $this->iSecCode = $secCodePref;
        }
        // Clearent tokens can be used without a customer token
        $this->iRequiredParameters["delete_customer_payment_profile"] = array("account_token");
        $this->iRequiredParameters["update_customer_payment_profile"] = array("account_token");
        $this->iRequiredParameters["create_customer_payment_transaction_request"] = array("amount", "order_number", "account_token");
    }

    function requiresCustomerToken() {
	    return false;
    }

	function testConnection() {
		$this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return ($this->iResponse['response_reason_text'] != "Authentication Failed");
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
		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}

		$cleanValues = array();

		// Invoice is needed to prevent duplicate transaction errors.
		// "Use invoice instead of card" must be set in Settings > Terminal on the merchant portal for this to work.

		$transaction = array(
			"amount" => number_format($parameters['amount'], 2, ".", ""),
			"sales-tax-amount" => number_format(array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : 0, 2, ".", ""),
			"description" => $parameters['description'],
			"order-id" => $parameters['order_number'],
			"invoice" => $parameters['order_number'],
			"purchase-order" => $parameters['po_number'],
			"client-ip" => (empty($_SERVER['REMOTE_ADDR']) ? gethostbyname(gethostname()) : $_SERVER['REMOTE_ADDR']),
			"software-type" => "coreware",
			"software-type-version" => $this->iVersion,
			"email-address" => $parameters['email_address'],
			"email-receipt" => "false",
			"billing" => array(
				"first-name" => $parameters['first_name'],
				"last-name" => $parameters['last_name'],
				"company" => $parameters['business_name'],
				"street" => $parameters['address_1'],
				"city" => $parameters['city'],
				"state" => $parameters['state'],
				"zip" => $parameters['postal_code'],
				"country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']),
				"phone" => $parameters['phone_number'],
			)
		);

		if (!$isACH) {
			$transaction["type"] = (empty($parameters['authorize_only']) ? "sale" : "auth");
			$methodUrl = '/transactions/' . $transaction["type"];
			$transaction['card'] = $parameters['card_number'];
			$transaction['exp-date'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$transaction['csc'] = $parameters['card_code'];
			}
            $cleanValues = array('card' => substr($parameters['card_number'], -4), 'csc' => "");
		} else {
			// Save account profile to allow refunds
			$this->createCustomerPaymentProfile($parameters);
			$transaction['token-id'] = $this->iResponse['account_token'];
			$methodUrl = '/ach/transactions';
			$transaction["type"] = "Debit";
			$transaction['standard-entry-class-code'] = $this->iSecCode;
			$transaction["individual-name"] = $parameters['first_name'] . " " . $parameters['last_name'];
			$cleanValues = array("token-id" => substr($parameters['bank_account_number'], -4));
		}

		if (!empty($parameters['shipping_city']) || !empty($parameters['shipping_address_1']) || !empty($parameters['shipping_last_name'])) {
			$shippingAddress = array();
			if (!empty($parameters['shipping_first_name'])) {
				$shippingAddress['first-name'] = $parameters['shipping_first_name'];
			}
			if (!empty($parameters['shipping_last_name'])) {
				$shippingAddress['last-name'] = $parameters['shipping_last_name'];
			}
			if (!empty($parameters['shipping_business_name'])) {
				$shippingAddress['company'] = $parameters['shipping_business_name'];
			}
			if (!empty($parameters['shipping_address_1'])) {
				$shippingAddress['street'] = $parameters['shipping_address_1'];
			}
			if (!empty($parameters['shipping_city'])) {
				$shippingAddress['city'] = $parameters['shipping_city'];
			}
			if (!empty($parameters['shipping_state'])) {
				$shippingAddress['state'] = $parameters['shipping_state'];
			}
			if (!empty($parameters['shipping_postal_code'])) {
				$shippingAddress['zip'] = $parameters['shipping_postal_code'];
			}
			if (!empty($parameters['shipping_country_id'])) {
				$shippingAddress['country'] = (empty($parameters['shipping_country_id']) ? "US" : getFieldFromId("country_code", "countries", "country_id", $parameters['shipping_country_id']));
			}
			if (!empty($shippingAddress)) {
				$transaction['shipping'] = $shippingAddress;
			}
		}
		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => $methodUrl,
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $rawResponse['code'];
			if (!$isACH) {
				$payload = $rawResponse['payload']['transaction'];
				$this->iResponse['result'] = $payload['result'];
				$this->iResponse['result-code'] = $payload['result-code'];
				$this->iResponse['authorization_code'] = $payload['authorization-code'];
				$this->iResponse['avs_response'] = $payload['avs-result-code'];
				$this->iResponse['card_code_response'] = $payload['csc-result-code'];
				$this->iResponse['transaction_id'] = $payload['id'];
			} else {
				$payload = $rawResponse['payload']['ach-transaction'];
				$this->iResponse['result'] = $payload['status'];
				$this->iResponse['transaction_id'] = $payload['id'];
			}
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = ($payload['display-message'] ?: $payload['transaction']['display-message']);
			if (empty($this->iResponse['display-message']) && !empty($rawResponse['payload']['error']) && $rawResponse['status'] == "fail") {
				$this->iResponse['response_reason_text'] = $rawResponse['payload']['error']['error-message'];
			}
            if(stristr(str_replace(" ", "_", $this->iResponse['response_reason_text']), "AVS_RESPONSE_NOT_ACCEPTED") !== false) {
                $this->iResponse['response_reason_text'] = "Street address and/or postal code doesn't match the billing address on file at the bank";
            }
        }

		$successful = $rawResponse['code'] == "200";
		/*  200	Successful Transaction
         *  400	Something is wrong with your request
         *  401	Unauthorized
         *  402	Business Exception (decline, etc.)
         *  403	Forbidden
         *  500	Internal server error
		**/

		if (empty($parameters['test_connection'])) {
            $logParameters = $transaction;
            $removeParameters = array("card", "exp-date", "csc", "routing-number", "account-number");
            foreach ($removeParameters as $thisParameter) {
                if (array_key_exists($thisParameter, $logParameters)) {
                    $logParameters[$thisParameter] = "";
                }
            }
            $rawResponse['csc'] = "";
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($logParameters) . "\n\n" . jsonEncode($rawResponse), !$successful);
        }
		return $successful;
	}

	function createCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		// Clearent customer profile is separate from payment token.
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

		if (!$isACH) {
			$methodUrl = '/tokens';
			$transaction['customer-key'] = $parameters['merchant_identifier'];
			$transaction['avs-address'] = substr($parameters['address_1'],0,20);
			$transaction['avs-zip'] = $parameters['postal_code'];
			$transaction['card'] = $parameters['card_number'];
			$transaction['exp-date'] = date("my", strtotime($parameters['expiration_date']));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$transaction['csc'] = $parameters['card_code'];
			}
            $cleanValues = array('card' => substr($parameters['card_number'], -4), 'csc' => "");
		} else {
			$methodUrl = '/ach/tokens';
			$transaction = array(
				"routing-number" => $parameters['bank_routing_number'],
				"account-number" => $parameters['bank_account_number'],
				"software-type" => "coreware",
				"software-type-version" => $this->iVersion,
				"account-type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking"),
				"individual-name" => $parameters['first_name'] . " " . $parameters['last_name']
			);
			$cleanValues = array("account_number" => substr($parameters['bank_account_number'], -4),
                "routing-number" => substr($parameters['bank_routing_number'],-4));
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => $methodUrl,
			'fields' => $transaction,
            'clean_values' => $cleanValues
		));

		$successful = true;
		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $rawResponse['code'];
            $payload = $rawResponse['payload'];
            if (!$isACH) {
                if ($this->iResponse['code'] == "200") {
                    $this->iResponse['account_token'] = $payload['tokenResponse']['token-id'];
				} else {
                    if($payload['payloadType'] == "error") {
                        $this->iResponse['response_reason_text'] = $payload['error']['error-message'];
                    }
					$successful = false;
				}
			} else {
				if ($payload['payloadType'] == "error" && $payload['error']['result-code'] == '028') { // Token already exists
					$this->iResponse['account_token'] = $rawResponse['links'][0]['id'];
				} elseif ($this->iResponse['code'] == "200") {
					$this->iResponse['account_token'] = $payload['ach-token']['token-id'];
				} else {
                    if($payload['payloadType'] == "error") {
                        $this->iResponse['response_reason_text'] = $payload['error']['error-message'];
                    }
					$successful = false;
				}
			}
		}
		if ($successful) {
			if ($isACH && empty($parameters['account_id'])) { // Create account when called from authorizeCharge with ACH
				$accountId = getFieldFromId("account_id", "accounts", "account_token", $this->iResponse['account_token'], "contact_id = " . $parameters['contact_id']);
				if (empty($accountId)) {
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number) values (?,?,?,?,?)", $parameters['contact_id'], $parameters['bank_account_type'] . " account",
						getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "ECHECK"),
						$parameters['first_name'] . " " . $parameters['last_name'], "XXXXXX" . substr($parameters['bank_account_number'], -4));
					$accountId = $resultSet['insert_id'];
				}
				$parameters['account_id'] = $accountId;
			}
			$this->setAccountToken($parameters['account_id'], $parameters['merchant_identifier'], $this->iResponse['account_token']);
		}
        if (empty($parameters['test_connection'])) {
            $logParameters = $transaction;
            $removeParameters = array("card","exp-date", "csc", "routing-number", "account-number");
            foreach($removeParameters as $thisParameter) {
                if(array_key_exists($thisParameter, $logParameters)) {
                    $logParameters[$thisParameter] = "";
                }
            }
            $rawResponse['csc'] = "";
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($logParameters) . "\n\n" . jsonEncode($rawResponse), !$successful);
        }

        return ($successful);
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
		$customer = array(
			"client-ip" => (empty($_SERVER['REMOTE_ADDR']) ? gethostbyname(gethostname()) : $_SERVER['REMOTE_ADDR']),
			"software-type" => "coreware",
			"software-type-version" => $this->iVersion,
			"first-name" => $parameters['first_name'],
			"last-name" => $parameters['last_name'],
			"email" => $parameters['email_address'],
			"phone" => $parameters['phone_number'],
			"billing" => array(
				"company" => $parameters['business_name'],
				"street" => $parameters['address_1'],
				"city" => $parameters['city'],
				"state" => $parameters['state'],
				"zip" => $parameters['postal_code'],
				"country" => getFieldFromId("country_code", "countries", "country_id", $parameters['country_id'])
			)
		);

		if (!empty($parameters['shipping_city']) || !empty($parameters['shipping_address_1']) || !empty($parameters['shipping_last_name'])) {
			$shippingAddress = array();
			if (!empty($parameters['shipping_first_name'])) {
				$shippingAddress['first-name'] = $parameters['shipping_first_name'];
			}
			if (!empty($parameters['shipping_last_name'])) {
				$shippingAddress['last-name'] = $parameters['shipping_last_name'];
			}
			if (!empty($parameters['shipping_business_name'])) {
				$shippingAddress['company'] = $parameters['shipping_business_name'];
			}
			if (!empty($parameters['shipping_address_1'])) {
				$shippingAddress['street'] = $parameters['shipping_address_1'];
			}
			if (!empty($parameters['shipping_city'])) {
				$shippingAddress['city'] = $parameters['shipping_city'];
			}
			if (!empty($parameters['shipping_state'])) {
				$shippingAddress['state'] = $parameters['shipping_state'];
			}
			if (!empty($parameters['shipping_postal_code'])) {
				$shippingAddress['zip'] = $parameters['shipping_postal_code'];
			}
			if (!empty($parameters['shipping_country_id'])) {
				$shippingAddress['country'] = (empty($parameters['shipping_country_id']) ? "US" : getFieldFromId("country_code", "countries", "country_id", $parameters['shipping_country_id']));
			}
			if (!empty($shippingAddress)) {
				$customer['shipping'] = $shippingAddress;
			}
		}
		$response = $this->makeRequest(array(
			'method' => 'POST',
			'url' => '/customers',
			'fields' => $customer
		));
		$this->iResponse['merchant_identifier'] = $response['payload']['customer']['customer-key'];

		$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);
        if (empty($parameters['test_connection'])) {
            $logParameters = $customer;
            $removeParameters = array("card","exp-date", "csc", "routing-number", "account-number");
            foreach($removeParameters as $thisParameter) {
                if(array_key_exists($thisParameter, $logParameters)) {
                    $logParameters[$thisParameter] = "";
                }
            }
            $rawResponse['csc'] = "";
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($logParameters) . "\n\n" . jsonEncode($rawResponse), false);
        }
		return true;
	}

	private function makeRequest($options = array()) {
		$this->iOptions = $options;
		$cleanValues = $options['clean_values'];
		unset($options['clean_values']);

		$url = $this->iUseUrl;

		$curlHandle = curl_init();
		$header = array('Content-Type: application/json', 'Accept: application/json', 'api-key: ' . $this->iMerchantAccountRow['account_key']);

		$configOptions = array();
		$configOptions[CURLOPT_HTTPHEADER] = $header;
		$configOptions[CURLOPT_URL] = $url . $options['url'];
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
        $logContent = $options['url'] . ": " . $this->cleanLogContent($options['fields'], $cleanValues) . "\n";
        $this->writeExternalLog($logContent);

        $rawResponse = curl_exec($curlHandle);
		curl_close($curlHandle);

        $logContent = $this->cleanLogContent($rawResponse, $cleanValues) . "\n";
        $this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = str_replace("\u0022", '"', $rawResponse);
		$this->iResponse['http_response'] = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		$this->iResponse['url'] = $this->iUseUrl;
		$this->iResponse['header'] = $header;
		$this->iResponse['options'] = $options;
		$this->iResponse['config'] = $configOptions;

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

		$isACH = !is_numeric($parameters['transaction_identifier']);

		if (!$isACH) {
			$rawResponse = $this->makeRequest(array(
				'method' => 'POST',
				'fields' => array("type" => "void", "id" => $parameters['transaction_identifier']),
				'url' => '/transactions/void'
			));
		} else {
			$rawResponse = $this->makeRequest(array(
				'method' => 'DELETE',
				'fields' => array("type" => "void", "id" => $parameters['transaction_identifier']),
				'url' => '/ach/transactions/' . $parameters['transaction_identifier']
			));
		}

		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['transaction_identifier'], jsonEncode($rawResponse), ($rawResponse['status'] != "success"));
		}
		return (is_array($rawResponse) && $rawResponse['status'] == "success");
	}

	function refundCharge($parameters) {
		$isACH = !is_numeric($parameters['transaction_identifier']);
		foreach ($this->iRequiredParameters['refund_' . ($isACH ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters) || empty($parameters[$requiredParameter])) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		if (!$isACH) {
			$rawResponse = $this->makeRequest(array(
				'method' => 'POST',
				'fields' => array("type" => "refund", "amount" => number_format($parameters['amount'], 2, ".", ""), "id" => $parameters['transaction_identifier']),
				'url' => '/transactions/refund'
			));
		} else {
			// Confirm that transaction is settled before running credit
			$transactionResponse = $this->makeRequest(array('url' => '/ach/transactions/' . $parameters['transaction_identifier']));
			$transactionResponse = $transactionResponse['payload']['ach-transaction'];
			if (strtoupper($transactionResponse['status']) !== 'SETTLED') {
				$this->iErrorMessage = "Only settled ACH transactions can be refunded.";
				return false;
			}
			// Verify that the same account was used for the transaction
			$tokenResponse = $this->makeRequest(array('url' => '/ach/tokens/' . $parameters['account_token']));
			$tokenResponse = $tokenResponse['payload']['ach-token'];
			if ($transactionResponse['routing-number'] != $tokenResponse['routing-number'] || $transactionResponse['account-number'] != $tokenResponse['account-number']) {
				$this->iErrorMessage = "Saved account information does not match transaction.";
				return false;
			}
			$rawResponse = $this->makeRequest(array(
				'method' => 'POST',
				'fields' => array("type" => "Credit",
					"amount" => number_format($parameters['amount'], 2, ".", ""),
					"software-type" => "coreware",
					"software-type-version" => $this->iVersion,
					"standard-entry-class-code" => $this->iSecCode,
					"token-id" => $parameters['account_token']),
				'url' => '/ach/transactions/credit'
			));
		}

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $rawResponse['code'];
			if (!$isACH) {
				$payload = $rawResponse['payload']['transaction'];
				$this->iResponse['result'] = $payload['result'];
				$this->iResponse['result-code'] = $payload['result-code'];
				$this->iResponse['authorization_code'] = $payload['authorization-code'];
			} else {
				$payload = $rawResponse['payload']['ach-transaction'];
				$this->iResponse['result'] = $payload['status'];
			}
			$this->iResponse['display-message'] = $payload['display-message'];
			$this->iResponse['transaction_id'] = $payload['id'];
			if (empty($this->iResponse['display-message']) && !empty($rawResponse['error']) && $rawResponse['status'] == "fail") {
				$this->iResponse['response_reason_text'] = $rawResponse['error']['error-message'];
			}
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

		$transactionDetails = $this->makeRequest(array(
			'url' => '/transactions?id=' . $parameters['transaction_identifier'],
		));
		if (count($transactionDetails['payload']['transactions']['transaction']) > 1 ||
			$transactionDetails['payload']['transactions']['transaction'][0]['id'] != $parameters['transaction_identifier']) {
			$this->iErrorMessage = "Transaction not found.";
			return false;
		}
		$transactionDetails = $transactionDetails['payload']['transactions']['transaction'][0];

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => array("type" => "capture", "id" => $parameters['transaction_identifier'],
				"order-id" => $transactionDetails['order-id'],
				"invoice" => $transactionDetails['invoice'],
				"amount" => number_format($transactionDetails['amount'], 2, ".", ""),
				"sales-tax-amount" => empty($transactionDetails['sales-tax-amount']) ? "0.00" : number_format($transactionDetails['sales-tax-amount'], 2, ".", "")),
			'url' => '/transactions/capture'
		));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $rawResponse['code'];
			$payload = $rawResponse['payload']['transaction'];
			$this->iResponse['result'] = $payload['result'];
			$this->iResponse['result-code'] = $payload['result-code'];
			$this->iResponse['authorization_code'] = $payload['authorization-code'];
			$this->iResponse['display-message'] = $payload['display-message'];
			$this->iResponse['transaction_id'] = $payload['id'];
			if (empty($this->iResponse['display-message']) && !empty($rawResponse['error']) && $rawResponse['status'] == "fail") {
				$this->iResponse['response_reason_text'] = $rawResponse['error']['error-message'];
			}
		}

		$successful = $rawResponse['code'] == "200";
		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['card_number'], jsonEncode($rawResponse), ($rawResponse['status'] != "success"));
		}
		return $successful;
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		$response = $this->makeRequest(array(
			'method' => 'GET',
			'url' => '/customers/' . $parameters['merchant_identifier']
		));

		if ($response) {
			$this->iResponse = array();
			if (is_array($response['payload']['customers'])) { // multiple matching results
				$this->iResponse['merchant_identifier'] = $response['payload']['customers']['customer'][0]['customer-key'];
			} else {
				$this->iResponse['merchant_identifier'] = $response['payload']['customer']['customer-key'];
			}
			return true;
		} else {
			return false;
		}
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
			'url' => '/customers/' . $parameters['merchant_identifier']
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
		$isACH = strpos($parameters['account_token'], "ach") !== false;

		if (!$isACH) {
			$methodUrl = "/tokens/";
		} else {
			$methodUrl = "/ach/tokens/";
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'DELETE',
			'url' => $methodUrl . $parameters['account_token']
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

		$isACH = strpos($parameters['account_token'], "ach") !== false;

		$cleanValues = array();

		if (!$isACH) {
			$this->getCustomerPaymentProfile($parameters);
			$expDate = $this->iResponse['expiration_date'];
			$methodUrl = "/transactions/" . ($parameters['authorize_only'] ? "auth" : "sale");
			$transaction = array(
				"card" => $parameters['account_token'],
				"exp-date" => $expDate,
				"order-id" => $parameters['order_number'],
				"invoice" => $parameters['order_number'],
				"type" => ($parameters['authorize_only'] ? "AUTH" : "SALE"),
				"amount" => number_format($parameters['amount'], 2, ".", "")
			);
			$cleanValues['card'] = 'saved token';
		} else {
			$methodUrl = "/ach/transactions/debit";
			$transaction = array(
				"software-type" => "coreware",
				"software-type-version" => $this->iVersion,
				"type" => "Debit",
				"amount" => number_format($parameters['amount'], 2, ".", ""),
				"standard-entry-class-code" => $this->iSecCode,
				"token-id" => $parameters['account_token']
			);
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'url' => $methodUrl,
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $rawResponse['code'];
			if (!$isACH) {
				$payload = $rawResponse['payload']['transaction'];
				$this->iResponse['result'] = $payload['result'];
				$this->iResponse['result-code'] = $payload['result-code'];
				$this->iResponse['authorization_code'] = $payload['authorization-code'];
				$this->iResponse['avs_response'] = $payload['avs-result-code'];
				$this->iResponse['card_code_response'] = $payload['csc-result-code'];
			} else {
				$payload = $rawResponse['payload']['ach-transaction'];
				$this->iResponse['result'] = $payload['status'];
			}
			$this->iResponse['transaction_id'] = $payload['id'];
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = ($payload['display-message'] ?: $payload['transaction']['display-message']);
            if (empty($this->iResponse['display-message']) && !empty($rawResponse['payload']['error']) && $rawResponse['status'] == "fail") {
                $this->iResponse['response_reason_text'] = $rawResponse['payload']['error']['error-message'];
            }
            if(stristr(str_replace(" ", "_", $this->iResponse['response_reason_text']), "AVS_RESPONSE_NOT_ACCEPTED") !== false) {
                $this->iResponse['response_reason_text'] = "Street address and/or postal code doesn't match the billing address on file at the bank";
            }
            $success = $rawResponse['code'] == "200";
		} else {
		    $success = false;
            $this->iResponse['response_reason_text'] = "Connection error";
        }

        if (empty($parameters['test_connection'])) {
            $logParameters = $transaction;
            if(!$isACH) {
                $logParameters['card'] = "saved token";
                $logParameters['exp-date'] = "";
            } else {
                $logParameters['token-id'] = "saved token";
            }
            $this->writeLog($parameters['account_token'], jsonEncode($logParameters) . "\n\n" . jsonEncode($rawResponse), !$success);
        }

        return $success;
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
		$isACH = (strpos($parameters['account_token'], "ach") !== false);
		if (!$isACH) {
			$methodUrl = '/tokens/';
		} else {
			$methodUrl = '/ach/tokens/';
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'GET',
			'url' => $methodUrl . $parameters['account_token']
		));

		if (!empty($rawResponse)) {
			$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
			$this->iResponse['payment_profile'] = $parameters['account_token'];
			if (!$isACH) {
				$this->iResponse['card_number'] = $rawResponse['payload']['tokenResponse']['last-four-digits'];
				$this->iResponse['expiration_date'] = $rawResponse['payload']['tokenResponse']['exp-date'];
			} else {
				$this->iResponse['account_type'] = $rawResponse['payload']['ach-token']['account-type'];
				$this->iResponse['routing_number'] = $rawResponse['payload']['ach-token']['routing-number'];
				$this->iResponse['account_number'] = $rawResponse['payload']['ach-token']['account-number'];
			}
		}

		return (is_array($rawResponse) && $rawResponse['status'] == "success");
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
		$isACH = !empty($parameters['bank_routing_number']);

		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}

		if (!$isACH) {
			// Clearent's update method for cards only supports changing billing address, not other card info
			$this->iErrorMessage = "Merchant provider does not support updating card information. Create a new payment method instead.";
			return false;
		} else {
			// Clearent's update method for ACH only supports changing the name and description.
			$this->iErrorMessage = "Merchant provider does not support updating bank information. Create a new payment method instead.";
			return false;
		}
	}
}
