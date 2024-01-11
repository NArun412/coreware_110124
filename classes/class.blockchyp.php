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

use BlockChyp\BlockChypIntegration;

class BlockChyp extends eCommerce {

	var $iTestMode = false;
	var $iApiKey = "";
	var $iBearerToken = "";
	var $iSigningKey = "";
	var $iMerchantId = "";
	var $iOptions = array();
	var $iApprovedAvsResponses = array();
    var $iApproveZipMatchForPoBox = false;
    var $iAvsCodes = array(
        'zip_match' => 'Z',
        'address_match' => 'A',
        'no_match' => 'N',
        'retry' => 'R',
        'not_supported' => 'S',
        'match' => 'Y');

	function __construct($merchantAccountRow) {
		require_once __DIR__ . '/BlockChyp/autoload.php';
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow = array();
		}
		if ($GLOBALS['gDevelopmentServer'] || $this->iMerchantAccountRow['merchant_account_code'] == "DEVELOPMENT" || $this->iMerchantAccountRow['account_login'] == "VHIWVQYXWHA4IANMGOMYVZSUDM") {
			$this->iTestMode = true;
		}
		$this->iApiKey = $this->iMerchantAccountRow['account_login'];
		$this->iBearerToken = $this->iMerchantAccountRow['account_key'];
        $this->iSigningKey = CustomField::getCustomFieldData($this->iMerchantAccountRow['merchant_account_id'], "SIGNING_KEY", "MERCHANT_ACCOUNTS");
        $this->iSigningKey = $this->iSigningKey ?: getPreference("CORECLEAR_SIGNING_KEY", $this->iMerchantAccountRow['merchant_account_code']);
        $this->iSigningKey = $this->iSigningKey ?: getPreference("CORECLEAR_SIGNING_KEY");
		$this->iApprovedAvsResponses = Page::getClientPagePreferences("CORECLEAR_AVS_APPROVALS");

		# default is to allow only full match

		if (empty($this->iApprovedAvsResponses)) {
			$this->iApprovedAvsResponses = array(
				'zip_match' => false,
				'address_match' => false,
				'no_match' => false,
				'retry' => false,
				'not_supported' => false);
		}
		$this->iApprovedAvsResponses['match'] = true;

        $preferenceArray = array(
            array('preference_code'=>'CORECLEAR_APPROVE_AVS_ZIP_MATCH_FOR_PO_BOXES', 'description'=>'coreCLEAR Approve Zip Match for PO Box addresses', 'preference_group'=>'INTEGRATION_SETTINGS',
                'detailed_description' => 'Many bank store PO Box addresses in such a way that the street address never matches in AVS.  This setting allows addresses with PO Box in the street address to be processed if the postal code matches.',
                'data_type'=>'tinyint', 'value'=> 1)
        );
        $this->setupPreferences($preferenceArray);

        $this->iApproveZipMatchForPoBox = !empty(getPreference("CORECLEAR_APPROVE_AVS_ZIP_MATCH_FOR_PO_BOXES"));

		$this->iMerchantId = $this->iMerchantAccountRow['merchant_identifier'];

		// BlockChyp tokens can be used without a customer token
		$this->iRequiredParameters["delete_customer_payment_profile"] = array("account_token");
		$this->iRequiredParameters["update_customer_payment_profile"] = array("account_token");
		$this->iRequiredParameters["create_customer_payment_transaction_request"] = array("amount", "order_number", "account_token");
	}

    function setupPreferences($preferenceArray) {
        foreach($preferenceArray as $thisPreference) {
            $preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $thisPreference['preference_code']);
            if(empty($preferenceId)) {
                $resultSet = executeQuery("insert into preferences (preference_code,description,detailed_description,data_type,client_setable) values (?,?,?,?,1)",
                    $thisPreference['preference_code'], $thisPreference['description'],$thisPreference['detailed_description'], $thisPreference['data_type']);
                $preferenceId = $resultSet['insert_id'];
                if(!empty($thisPreference['preference_group'])) {
                    executeQuery("insert into preference_group_links (preference_id, preference_group_id) select ?,preference_group_id from preference_groups where preference_group_code = ?",
                        $preferenceId, $thisPreference['preference_group']);
                }
            }
            $clientPreferenceRow = getRowFromId("client_preferences", "preference_id", $preferenceId, "client_id = ?", $GLOBALS['gClientId']);
            if(empty($clientPreferenceRow)) {
                executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $thisPreference['value']);
            }
        }
    }

	function requiresCustomerToken() {
		return false;
	}

	function testConnection() {
		$this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "69349234", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "postal_code" => "75771", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return (!in_array($this->iResponse['response_reason_text'], array("Access Denied", "Internal Server Error")));
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

		$transaction = array(
			"amount" => $this->formatAmount($parameters['amount']),
            "taxAmount" => $this->formatAmount($parameters['tax_charge']),
            "tipAmount" => "0.00",
			"transactionRef" => strval($parameters['order_number']),
            "orderRef" => strval($parameters['order_number']),
            "cardholderName" => $parameters['first_name'] . " " . $parameters['last_name'],
            "description" => $parameters['email_address'] . " " . $parameters['phone_number']
		);
        $items = array();
        if(!empty($parameters['order_items'])) {
            foreach ($parameters['order_items'] as $orderItem) {
                $items[] = array("id" => $orderItem['product_id'],
                    "description" => $orderItem['description'],
                    "quantity" => $orderItem['quantity'],
                    "price" => $orderItem['sale_price'],
                    "extended" => $orderItem['sale_price'] * $orderItem['quantity']);
            }
        }
        if(!empty($items)) {
            $transaction['items'] = $items;
        }

		$method = "charge";
		if (!$isACH) {
			$method = (empty($parameters['authorize_only']) ? "charge" : "preauth");
			$cardAccount = array(
				"pan" => $parameters['card_number'],
				"expMonth" => date("m", strtotime($parameters['expiration_date'])),
				"expYear" => date("Y", strtotime($parameters['expiration_date'])),
				"address" => $parameters['address_1'],
				"postalCode" => $parameters['postal_code']
			);
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$cardAccount['cvv'] = $parameters['card_code'];
			}
			$transaction = array_merge($cardAccount, $transaction);
			$cleanValues = array(
				"pan" => substr($parameters['card_number'], -4),
                "cvv" => ""
			);
		} else {
			$transaction['paymentType'] = "ACH";
			$bankAccount = array(
				"routingNumber" => $parameters['bank_routing_number'],
				"pan" => $parameters['bank_account_number']
			);
			$transaction = array_merge($bankAccount, $transaction);
			$cleanValues = array("pan" => substr($parameters['bank_account_number'], -4),
				"routingNumber" => substr($parameters['bank_routing_number'], -4));
		}

		$rawResponse = $this->makeRequest(array(
			'method' => $method,
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));

		if (!empty($rawResponse)) {
			if (!$isACH) {
				$this->iResponse['result'] = $rawResponse['responseDescription'];
				$this->iResponse['authorization_code'] = $rawResponse['authCode'];
				// empty avsResponse is supported by BlockChyp because of terminal transactions.  For keyed, treat as "no match"
				if (empty($rawResponse['avsResponse']) && $rawResponse['approved']) {
					$rawResponse['avsResponse'] = "no_match";
					$rawResponse['responseDescription'] = "NO MATCH";
				}
				$this->iResponse['avs_response'] = $this->iAvsCodes[$rawResponse['avsResponse']];
				$this->iResponse['card_code_response'] = $rawResponse['cvvResponseCode'];
				$this->iResponse['transaction_id'] = $rawResponse['transactionId'];
			} else {
				$this->iResponse['result'] = $rawResponse['responseDescription'];
				$this->iResponse['transaction_id'] = $rawResponse['transactionId'];
			}
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['responseDescription'];
            if (empty($this->iResponse['display-message']) && !empty($rawResponse['authResponseCode'])) {
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['authResponseCode'];
			}
		}

		$successful = $rawResponse['approved'];
        if($this->iApproveZipMatchForPoBox && stristr(preg_replace("/[^A-Za-z0-9]/", "", $parameters['address_1']), "pobox") !== false) {
            $this->iApprovedAvsResponses['zip_match'] = true;
        }
		if ($successful && !$this->iApprovedAvsResponses[$rawResponse['avsResponse']]) {
			$voidSuccess = $this->voidCharge(array("transaction_identifier" => $rawResponse['transactionId'], "avs_fail"=>1));
            if($voidSuccess) {
                $successful = false;
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = "Street address and/or postal code doesn't match the billing address on file at the bank";
            } else {
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = "Address verification failed, but charge could not be voided.";
                $this->iResponse['transaction_id'] = $rawResponse['transactionId'];
                $this->iResponse['authorization_code'] = $rawResponse['authCode'];
            }
		}

		if (empty($parameters['test_connection'])) {
			$logParameters = array_merge($transaction, $cleanValues);
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']),
                $this->formatLog($method, $parameters,$logParameters,$rawResponse, $successful), !$successful);
		}
		return $successful;
	}

	private function makeRequest($options = array()) {
		$this->iOptions = $options;
        $cleanValues = $options['clean_values'] ?: array();
		unset($options['clean_values']);

		$transaction = $options['fields'];
		$method = $options['method'];

		$logContent = $options['method'] . ": " . $this->cleanLogContent($options['fields'], $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		BlockChypIntegration::setApiKey($this->iApiKey);
		BlockChypIntegration::setBearerToken($this->iBearerToken);
		BlockChypIntegration::setSigningKey($this->iSigningKey);
		if ($this->iTestMode) {
			$transaction['test'] = true;
		}

        try {
            $rawResponse = BlockChypIntegration::$method($transaction);
        } catch(Exception $e) {
            $this->iErrorMessage = $e->getMessage();
        }

		$logContent = $this->cleanLogContent($rawResponse, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = jsonEncode($rawResponse);
        $this->iResponse['options'] = array_merge($options, array("fields"=>array_merge($options['fields'], $cleanValues)));

		if (empty($rawResponse) || (!is_array($rawResponse) && substr($rawResponse, 0, 6) == "<html>")) {
			return false;
		}

		return $rawResponse;
	}

    private function formatLog($method, $parameters, $request, $response, $successful) {
        $logEntry = $method . ($successful ? " succeeded: " : " failed: ") . $this->iResponse['display-message'];
        if(!$this->iApprovedAvsResponses[$response['avsResponse']]) {
            $logEntry .= " (" . $response['avsResponse'] . ")";
        }
        $logEntry .= "\nCardholder Name: " . ($parameters['first_name'] ?: $response['customer']['firstName']) . " " . ($parameters['last_name'] ?: $response['customer']['lastName']);
        $logEntry .= "\nLast4:" . ($request['pan'] ?: substr($response['maskedPan'],-4));
        $logEntry .= "\nAddress: " . $parameters['address_1'] . ", " . $parameters['city'] . ", " .  $parameters['state'] . ", " .  $parameters['postal_code'];
        $logEntry .= "\nEmail: " . ($parameters['email_address'] ?: $response['customer']['emailAddress']);
        $logEntry .= "\nPhone: " . ($parameters['phone_number'] ?: $response['customer']['smsNumber']);
        $logEntry .= "\n\nRequest: " . jsonEncode($request) . "\n\nResponse: " . jsonEncode($response);

        return $logEntry;
    }

    private function formatAmount($amount) {
        if(!empty($amount)) {
            $amount = str_replace(",", "", $amount);
            if (is_numeric($amount)) {
                return number_format($amount, 2, ".", "");
            }
        }
        return "0.00";
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
			'method' => 'void',
			'fields' => array("transactionId" => $parameters['transaction_identifier'])
		));

		$successful = $rawResponse['approved'];
		if (!$successful) {
            $this->iResponse['response_reason_text'] = $rawResponse['responseDescription'];
            $this->iErrorMessage = $rawResponse['responseDescription'];
		}

		if (empty($parameters['test_connection'] && empty($parameters['avs_fail']))) {
			$this->writeLog($parameters['transaction_identifier'], "Void: Transaction ID " . $parameters['transaction_identifier'] . ($successful ? " succeeded." : " failed: " . $rawResponse['responseDescription']) ."\n" . jsonEncode($rawResponse), !$successful);
		}
		return $successful;
	}

	function createCustomerPaymentProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
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
			$transaction = array(
				"pan" => $parameters['card_number'],
				"expMonth" => date("m", strtotime($parameters['expiration_date'])),
				"expYear" => date("Y", strtotime($parameters['expiration_date'])),
				"address" => $parameters['address_1'],
				"postalCode" => $parameters['postal_code']
			);
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$transaction['cvv'] = $parameters['card_code'];
			}
			$cleanValues = array(
				"pan" => substr($parameters['card_number'], -4),
				"cvv" => ""
			);
		} else {
			$transaction['paymentType'] = "ACH";
			$transaction = array(
				"routingNumber" => $parameters['bank_routing_number'],
				"pan" => $parameters['bank_account_number']
			);
			$cleanValues = array("pan" => substr($parameters['bank_account_number'], -4),
				"routingNumber" => substr($parameters['bank_routing_number'], -4));
		}
		if (!empty($parameters['merchant_identifier'])) {
			$response = $this->makeRequest(array(
				'method' => 'customer',
				'fields' => array('customerId' => $parameters['merchant_identifier'])
			));
			if ($response['success']) {
				$transaction['customer'] = $response['customer'];
			}
		}

		$rawResponse = $this->makeRequest(array(
			'method' => "enroll",
			'fields' => $transaction,
			'clean_values' => $cleanValues
		));
        // todo: fix error message

		$successful = $rawResponse['approved'];

		if (!empty($rawResponse)) {
			if (array_key_exists("errorCode", $rawResponse)) {
				$this->iErrorMessage = $rawResponse['message'];
				$successful = false;
			}
            if (empty($rawResponse['avsResponse']) && $rawResponse['approved']) {
                $rawResponse['avsResponse'] = "no_match";
                $rawResponse['responseDescription'] = "NO MATCH";
            }
            if(!$successful) {
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['responseDescription'];
            }
            $this->iResponse['account_token'] = $rawResponse['token'];
			$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		}

        if($this->iApproveZipMatchForPoBox && stristr(preg_replace("/[^A-Za-z0-9]/", "", $parameters['address_1']), "pobox") !== false) {
            $this->iApprovedAvsResponses['zip_match'] = true;
        }
        if ($successful && !$this->iApprovedAvsResponses[$rawResponse['avsResponse']]) {
            $this->deleteCustomerPaymentProfile(array("account_token" => $rawResponse['token']));
            $successful = false;
            $this->iErrorMessage = $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = "Street address and/or postal code doesn't match the billing address on file at the bank";
        }

        if ($successful) {
			$this->setAccountToken($parameters['account_id'], $parameters['merchant_identifier'], $this->iResponse['account_token']);
		}

		if (empty($parameters['test_connection'])) {
			$logParameters = array_merge($transaction, $cleanValues);
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']),
                $this->formatLog("save payment method",$parameters,$logParameters,$rawResponse,$successful), !$successful);
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
		// check for duplicate customer records
		$fields = ["customerRef" => (string)$parameters['contact_id']];
		$response = $this->makeRequest(array(
			'method' => 'customer',
			'fields' => $fields
		));
		if (!$response['success']) {
			$contactIdResult = executeQuery("select retired_contact_identifier from contact_redirect where contact_id = ?", $parameters['contact_id']);
			while ($contactIdRow = getNextRow($contactIdResult)) {
				$fields = ["customerRef" => (string)$contactIdRow['retired_contact_identifier']];
				$response = $this->makeRequest(array(
					'method' => 'customer',
					'fields' => $fields
				));
				if ($response['success']) {
					break;
				}
			}
		}
		if (!$response['success']) {
			$customer = array(
				"customerRef" => (string)$parameters['contact_id'],
				"firstName" => $parameters['first_name'],
				"lastName" => $parameters['last_name'],
				"companyName" => $parameters['business_name'],
				"emailAddress" => $parameters['email_address'],
				"smsNumber" => $parameters['phone_number']
			);

			$response = $this->makeRequest(array(
				'method' => 'updateCustomer',
				'fields' => array("customer" => $customer)
			));
		}
		$successful = $response['success'];
		if ($successful) {
			$this->iResponse['merchant_identifier'] = $response['customer']['id'];

			$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);
		} else {
			$this->iErrorMessage = $response['responseDescription'];
		}
		return $successful;
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
			'method' => 'refund',
			'fields' => array("transactionId" => $parameters['transaction_identifier'], "amount" => number_format($parameters['amount'], 2, ".", ""))
		));

		$successful = false;
		if (!empty($rawResponse)) {
			$successful = $rawResponse['approved'];
			$this->iResponse['authorization_code'] = $rawResponse['authCode'];
			$this->iResponse['result'] = $rawResponse['responseDescription'];
			$this->iResponse['transaction_id'] = $rawResponse['transactionId'];
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['responseDescription'];
            if (empty($this->iResponse['display-message']) && !empty($rawResponse['authResponseCode'])) {
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['authResponseCode'];
            }
		}

		if (empty($parameters['test_connection'])) {
            $this->writeLog($parameters['transaction_identifier'], "Refund: Transaction ID " . $parameters['transaction_identifier'] . ($successful ? " succeeded." : " failed: " . $rawResponse['responseDescription']) ."\n" . jsonEncode($rawResponse), !$successful);

        }
		return $successful;
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
			'method' => "capture",
			'fields' => array('transactionId' => $parameters['transaction_identifier'])
		));

		$successful = false;
		if (!empty($rawResponse)) {
			$successful = $rawResponse['approved'];
			$this->iResponse['authorization_code'] = $rawResponse['authCode'];
			$this->iResponse['result'] = $rawResponse['responseDescription'];
			$this->iResponse['transaction_id'] = $rawResponse['transactionId'];
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['responseDescription'];
            if (empty($this->iResponse['display-message']) && !empty($rawResponse['authResponseCode'])) {
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['authResponseCode'];
            }
        }

		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['transaction_identifier'], "Capture: Transaction ID " . $parameters['transaction_identifier'] . "\n" . jsonEncode($rawResponse), !$successful);
		}

		return $successful;
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		$response = $this->makeRequest(array(
			'method' => 'customer',
			'fields' => array('customerId' => $parameters['merchant_identifier'])
		));
		$successful = $response['success'];

		if ($successful) {
			$this->iResponse['merchant_identifier'] = $response['customer']['id'];
		} else {
			$this->iErrorMessage = $response['responseDescription'];
		}
		return $successful;
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
			'method' => "deleteCustomer",
			'fields' => array('customerId' => $parameters['merchant_identifier'])
		));
		return $rawResponse['success'];
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
			'method' => "deleteToken",
			'fields' => array('token' => $parameters['account_token'])
		));
		return $rawResponse['success'];
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
        if(!empty($parameters['address_id'])) {
            $addressRow = getRowFromId("addresses", "address_id", $parameters['address_id']);
            $parameters = array_merge(array_filter($addressRow), $parameters);
        }

        $transaction = array(
            "amount" => $this->formatAmount($parameters['amount']),
            "taxAmount" => $this->formatAmount($parameters['tax_charge']),
            "tipAmount" => "0.00",
            "transactionRef" => strval($parameters['order_number']),
            "orderRef" => strval($parameters['order_number']),
            "cardholderName" => $parameters['first_name'] . " " . $parameters['last_name'],
            "description" => $parameters['email_address'] . " " . $parameters['phone_number']
        );
        if($parameters['country_id'] == 1000) {
            $transaction["address"] = $parameters['address_1'];
            $transaction["postalCode"] = $parameters['postal_code'];
        }
        $items = array();
        if(!empty($parameters['order_items'])) {
            foreach ($parameters['order_items'] as $orderItem) {
                $items[] = array("id" => $orderItem['product_id'],
                    "description" => $orderItem['description'],
                    "quantity" => $orderItem['quantity'],
                    "price" => $orderItem['sale_price'],
                    "extended" => $orderItem['sale_price'] * $orderItem['quantity']);
            }
        }
        if(!empty($items)) {
            $transaction['items'] = $items;
        }

		$transaction['token'] =  $parameters['account_token'];

		$method = (empty($parameters['authorize_only']) ? "charge" : "preauth");

		$rawResponse = $this->makeRequest(array(
			'method' => $method,
			'fields' => $transaction
		));

		if (!empty($rawResponse)) {
			$this->iResponse['result'] = $rawResponse['responseDescription'];
			$this->iResponse['authorization_code'] = $rawResponse['authCode'];
            $this->iResponse['avs_response'] = $this->iAvsCodes[$rawResponse['avsResponse']];
			$this->iResponse['card_code_response'] = $rawResponse['cvvResponseCode'];
			$this->iResponse['transaction_id'] = $rawResponse['transactionId'];
            $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['responseDescription'];
            if (empty($this->iResponse['display-message']) && !empty($rawResponse['authResponseCode'])) {
                $this->iResponse['response_reason_text'] = $this->iResponse['display-message'] = $rawResponse['authResponseCode'];
            }
		}

		$successful = $rawResponse['approved'];
        // not checking avsResponse here because saved token may have come from POS where address was not provided

		if (empty($parameters['test_connection'])) {
			$logParameters = $transaction;
            $this->writeLog($transaction['account_token'],$this->formatLog($method,$parameters,$logParameters,$rawResponse,$successful), !$successful);
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
        $found = false;
        $thisPaymentMethod = array();
        if(!empty($parameters['merchant_identifier'])) {
            $response = $this->makeRequest(array(
                'method' => 'customer',
                'fields' => array('customerId' => $parameters['merchant_identifier'])
            ));
            $success = $response['success'];

            if ($success) {
                foreach ($response['customer']['paymentMethods'] as $thisPaymentMethod) {
                    if ($parameters['account_token'] == $thisPaymentMethod['token']) {
                        $found = true;
                        break;
                    }
                }
            }
        }
        if(!$found) { // look up by token
            $response = $this->makeRequest(array(
                'method' => 'tokenMetadata',
                'fields' => array('token' => $parameters['account_token'])
            ));
            $success = $response['success'];
            if ($success) {
                $thisPaymentMethod = $response['token'];
                if ($parameters['account_token'] == $thisPaymentMethod['token']) {
                    $found = true;
                    if(!empty($parameters['merchant_identifier']) && empty($thisPaymentMethod['customers'])) {
                        $this->makeRequest(array(
                            'method' => 'linkToken',
                            'fields' => array('token' => $parameters['account_token'], 'customerId' => $parameters['merchant_identifier'])
                        ));
                    }
                }
            }
        }
        if(!empty($thisPaymentMethod)) {
            if (empty($thisPaymentMethod['routingNumber'])) {
                $this->iResponse['card_number'] = $thisPaymentMethod['maskedPan'];
                $this->iResponse['expiration_date'] = $thisPaymentMethod['expiryMonth'] . "/" . $thisPaymentMethod['expiryYear'];
            } else {
                $this->iResponse['routing_number'] = $thisPaymentMethod['routingNumber'];
                $this->iResponse['account_number'] = $thisPaymentMethod['maskedPan'];
            }
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
		$this->iErrorMessage = "Merchant provider does not support updating payment method. Create a new payment method instead.";
		return false;
	}
}
