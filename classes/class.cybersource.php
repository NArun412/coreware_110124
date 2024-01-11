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

class Cybersource extends eCommerce {

	var $iCredentials = array();
	var $iTestUrl = "https://apitest.cybersource.com";
	var $iLiveUrl = "https://api.cybersource.com";
	var $iTestUrlSoap = "https://ics2wstest.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.169.wsdl";
	var $iLiveUrlSoap = "https://ics2ws.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.169.wsdl";
	var $iSoapClient = "";
	var $iVersion = "";
	var $iUseUrl = "";
	var $iUseUrlSoap = "";
	var $iUseSoapForTokens = false;
	var $iOrganizationId = "";
	var $iSharedSecret = "";
	var $iSoapToolkitKey = "";
	var $iKeyId = "";
	var $iSecCode = "CCD";
	var $iOptions = array();

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow = array();
		}
		if ($GLOBALS['gDevelopmentServer'] || $this->iMerchantAccountRow['merchant_account_code'] == "DEVELOPMENT") {
			$this->iUseUrl = $this->iTestUrl;
			$this->iUseUrlSoap = $this->iTestUrlSoap;
		} else {
			$this->iUseUrl = $this->iLiveUrl;
			$this->iUseUrlSoap = $this->iLiveUrlSoap;
		}
		if (!empty($this->iMerchantAccountRow['link_url']) && $GLOBALS['gUserRow']['superuser_flag']) {
			$this->iUseUrl = $this->iMerchantAccountRow['link_url'];
		}

		// Allow client to set SEC code; WEB = personal ACH, CCD = business ACH
		$secCodePref = getPreference('CYBERSOURCE_SEC_CODE');
		if (!empty($secCodePref)) {
			$this->iSecCode = $secCodePref;
		}

		$this->iOrganizationId = $this->iMerchantAccountRow['account_login']; // "testrest"
		$this->iSharedSecret = $this->iMerchantAccountRow['account_key']; // $secret_key = "yBJxy6LjM2TmcPGu+GaJrHtkke25fPpUX+UY6/L/1tE=";
		$this->iKeyId = $this->iMerchantAccountRow['merchant_identifier']; // $merchant_key_id = "08c94330-f618-42a3-b09d-e1e43be5efda";
        $soapKey = CustomField::getCustomFieldData($this->iMerchantAccountRow['merchant_account_id'], "SIGNING_KEY", "MERCHANT_ACCOUNTS");
        $soapKey = $soapKey ?: getPreference("CYBERSOURCE_SOAP_KEY", $this->iOrganizationId);
        $soapKey = $soapKey ?: getPreference("CYBERSOURCE_SOAP_KEY");
		if (!empty($soapKey)) {
			$this->iUseSoapForTokens = true;
			$this->iSoapToolkitKey = $soapKey; // username coreware key "uMVyQmD2udz8HAeRRzsv2x0L4qrsp+e1sYc0lXquY2ME18z5vdPtEW0YGBhgeUGqi8CaQQTb41BnRz+EDKnvQSaHB7MsY7XpNQl289vF3yueMPPXb52huyRdIExNioTWXcLmVZjiR8IloXmkAo/LcRVx0NMWXG3UTJG4ozmOa5NzQfbe49CtxRQDWkH/vHd1LboOGTkOwcF8gRgCzZBxDCLdTEQvYqjzlNlgXeg6wKWWSyxTa8s5MHVFw6LNh05oQV2M5QscAnT+GkQPmvxQoM2rlKW9Lw1ItK4++LWFMIysrVW1V3Vgt/aomN/f47Cc980yi09PLz3RVQ6EeGzLNg==
			$this->initializeSoap();
		}

		$this->iVersion = date("Y-m-d\TH:i:s", filemtime(__FILE__));
	}

	private function initializeSoap() {
		$this->iSoapClient = new SoapClient($this->iUseUrlSoap, $GLOBALS['gDevelopmentServer'] ? array('trace' => 1) : array());
		$this->iSoapClient->merchantId = $this->iOrganizationId;
		$this->iSoapClient->transactionKey = $this->iSoapToolkitKey;
		$nameSpace = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";

		$soapUsername = new SoapVar(
			$this->iSoapClient->merchantId,
			XSD_STRING,
			NULL,
			$nameSpace,
			NULL,
			$nameSpace
		);

		$soapPassword = new SoapVar(
			$this->iSoapClient->transactionKey,
			XSD_STRING,
			NULL,
			$nameSpace,
			NULL,
			$nameSpace
		);

		$auth = new stdClass();
		$auth->Username = $soapUsername;
		$auth->Password = $soapPassword;

		$soapAuth = new SoapVar(
			$auth,
			SOAP_ENC_OBJECT,
			NULL,
			$nameSpace,
			'UsernameToken',
			$nameSpace
		);

		$token = new stdClass();
		$token->UsernameToken = $soapAuth;

		$soapToken = new SoapVar(
			$token,
			SOAP_ENC_OBJECT,
			NULL,
			$nameSpace,
			'UsernameToken',
			$nameSpace
		);

		$security = new SoapVar(
			$soapToken,
			SOAP_ENC_OBJECT,
			NULL,
			$nameSpace,
			'Security',
			$nameSpace
		);

		$header = new SoapHeader($nameSpace, 'Security', $security, true);
		$this->iSoapClient->__setSoapHeaders(array($header));
	}

	private function makeSoapRequest($requestFields, $referenceCode = "", $cleanValues = array()) {
        $logContent = $this->cleanLogContent($requestFields, $cleanValues) . "\n";
        $this->writeExternalLog($logContent);

		$request = new stdClass();
		$request->merchantID = $this->iOrganizationId;

		$request->merchantReferenceCode = empty($referenceCode) ? $this->iOrganizationId : $referenceCode;
		$mergedRequest = (object)array_merge((array)$request, (array)$requestFields);
        try{
            $result = $this->iSoapClient->runTransaction($mergedRequest);
        } catch(Exception $e) {
            $this->iErrorMessage = $e->getMessage();
            $result = false;
        }
		return $result;
	}

	private function makeRequest($options = array()) {

		$this->iOptions = $options;
		$cleanValues = $options['clean_values'];
		unset($options['clean_values']);

		$url = $this->iUseUrl . $options['url'];

		$configOptions = array();

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
			case 'patch':
				$configOptions[CURLOPT_CUSTOMREQUEST] = "PATCH";
				break;
			default:
				// GET is default for curl
				$method = 'get';
				break;
		}

		if (!empty($options['fields'])) {
			$configOptions[CURLOPT_POSTFIELDS] = jsonEncode($options['fields'], JSON_UNESCAPED_SLASHES);
		}

		$date = gmdate("D, d M Y G:i:s ") . "GMT";

		$headerParams = (empty($options['headers']) ? [] : $options['headers']);
		$headers = [];

		$headerParams['Content-Type'] = 'application/json';

		foreach ($headerParams as $key => $val) {
			$headers[] = "$key: $val";
		}

		$authHeaders = $this->getHttpSignature($url, $method, $date, $configOptions[CURLOPT_POSTFIELDS]);
		$headerParams = array_merge($headers, $authHeaders);
		$configOptions[CURLOPT_URL] = $url;
		$configOptions[CURLOPT_RETURNTRANSFER] = 1;
		$configOptions[CURLOPT_HTTPHEADER] = $headerParams;
		$configOptions[CURLOPT_HEADER] = 1;
		$configOptions[CURLOPT_VERBOSE] = 0;
		$configOptions[CURLOPT_USERAGENT] = "Mozilla/5.0";

		$curlHandle = curl_init();

		foreach ($configOptions as $optionName => $optionValue) {
			curl_setopt($curlHandle, $optionName, $optionValue);
		}

		$rawResponse = curl_exec($curlHandle);

		$httpHeaderSize = curl_getinfo($curlHandle, CURLINFO_HEADER_SIZE);
		$httpHeader = $this->parseHttpHeaders(substr($rawResponse, 0, $httpHeaderSize));
		$httpBody = substr($rawResponse, $httpHeaderSize);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = str_replace("\u0022", '"', $httpBody);
		$this->iResponse['http_response'] = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		$this->iResponse['response_header'] = $httpHeader;
		$this->iResponse['url'] = $this->iUseUrl;
		$this->iResponse['header'] = $headerParams;
		$this->iResponse['options'] = $options;
		$this->iResponse['config'] = $configOptions;

		$logContent = $options['url'] . ": " . $this->cleanLogContent($options['fields'], $cleanValues) . "\n";
		$this->writeExternalLog($logContent);

		if (empty($rawResponse)) {
			return false;
		}

		return empty($options['return_raw']) ? json_decode($httpBody, true) : $httpBody;
	}


	function testConnection() {
		$this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "state" => "TX", "postal_code" => "75771", "email_address" => "test@test.com",
			"country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
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
		$countryCode = getFieldFromId('country_code', 'countries', 'country_id', $parameters['country_id']);

		$parameters = $this->fillRequiredFields($parameters);

        $cleanValues = array();

        if (!$isACH) {
			$paymentInformation = array(
				"card" => array(
					"expirationYear" => date("Y", strtotime($parameters['expiration_date'])),
					"number" => $parameters['card_number'],
					"securityCode" => $parameters['card_code'],
					"expirationMonth" => date("m", strtotime($parameters['expiration_date']))
				)
			);
			$cleanValues['paymentInformation'] = array(
                "card" => array(
                    "expirationYear" => date("Y", strtotime($parameters['expiration_date'])),
                    "number" => substr($parameters['card_number'], -4),
                    "expirationMonth" => date("m", strtotime($parameters['expiration_date']))
                )
            );
		} else {
			$paymentInformation = array(
				"bank" => array(
					"account" => array(
						"type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "S" : "C"),
						"number" => $parameters['bank_account_number'],
						"encoderId" => "",
						"checkNumber" => "",
						"checkImageReferenceNumber" => ""
					),
					"routingNumber" => $parameters['bank_routing_number']
				)
			);
            $cleanValues['paymentInformation'] = array(
                "bank" => array(
                    "account" => array(
                        "type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "S" : "C"),
                        "number" => substr($parameters['bank_account_number'],-4),
                        "encoderId" => "",
                        "checkNumber" => "",
                        "checkImageReferenceNumber" => ""
                    ),
                    "routingNumber" => substr($parameters['bank_routing_number'],-4)
                )
            );
		}

		$transaction = array(
			"clientReferenceInformation" => array(
				"code" => $parameters['order_number'],
			),
			"processingInformation" => array(
				"commerceIndicator" => "internet",
				"capture" => (empty($parameters['authorize_only']))
			),
			"orderInformation" => array(
				"billTo" => array(
					"firstName" => $parameters['first_name'],
					"lastName" => $parameters['last_name'],
					"address1" => $parameters['address_1'],
					"postalCode" => $parameters['postal_code'],
					"locality" => $parameters['city'],
					"administrativeArea" => $parameters['state'],
					"country" => $countryCode,
					"phoneNumber" => $parameters['phone_number'],
					"email" => $parameters['email_address']
				),
				"amountDetails" => array(
					"totalAmount" => $parameters['amount'],
					"currency" => "USD" // Required field
				)
			),
			"paymentInformation" => $paymentInformation
		);
		if ($isACH) {
			$transaction['processingInformation']['bankTransferOptions']['secCode'] = $this->iSecCode;
		}
		$url = "/pts/v2/payments/";

		$rawResponse = $this->makeRequest(array('method' => 'post', 'url' => $url, 'fields' => $transaction, "clean_values"=>$cleanValues));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $this->iResponse['http_response'];
			if (!$isACH) {
				$this->iResponse['result'] = $rawResponse['status'];
				$this->iResponse['result-code'] = $rawResponse['processorInformation']['responseCode'];
				$this->iResponse['authorization_code'] = $rawResponse['processorInformation']['approvalCode'];
				$this->iResponse['avs_response'] = $rawResponse['processorInformation']['avs']['code'];
				$this->iResponse['transaction_id'] = $rawResponse['id'];
			} else {
				$this->iResponse['result'] = $rawResponse['status'];
				$this->iResponse['transaction_id'] = $rawResponse['id'];
				$this->iResponse['authorization_code'] = $rawResponse['reconciliationId'];
			}
			if (is_array($rawResponse['errorInformation'])) {
				$this->iResponse['response_reason_text'] = $rawResponse['errorInformation']['reason'];
				$this->iResponse['display-message'] = $rawResponse['errorInformation']['message'];
			}
			if (empty($this->iResponse['response_reason_text']) && $this->iResponse['http_response'] > 299) {
				if (!empty($rawResponse['message'])) {
					$this->iResponse['response_reason_text'] = $rawResponse['message'];
				} elseif (!empty($rawResponse['response']['rmsg'])) {
					$this->iResponse['response_reason_text'] = $rawResponse['response']['rmsg'];
				} else {
					$this->iResponse['response_reason_text'] = json_encode($rawResponse);
				}
			}
		}

		$successful = $rawResponse['status'] == 'PENDING' || $rawResponse['status'] == "AUTHORIZED";

		if (empty($parameters['test_connection'])) {
			$logParameters = $transaction;
			if (!$isACH) {
				$logParameters['paymentInformation']['card'] = array();
			} else {
				$logParameters['paymentInformation']['bank'] = array();
			}
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($logParameters) . "\n\n" . jsonEncode($rawResponse), !$successful);
		}
		return $successful;
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
			'fields' => array("clientReferenceInformation" => array("code" => $parameters['order_number'])),
			'url' => '/pts/v2/payments/' . $parameters['transaction_identifier'] . '/voids'
		));

        // Pending charges will return "REVERSED", settled charges will return "VOIDED"
        $success = (is_array($rawResponse) && ($rawResponse['status'] == "REVERSED" || $rawResponse['status'] == "VOIDED"));
        if (empty($parameters['test_connection'])) {
            $this->writeLog($parameters['transaction_identifier'], jsonEncode($rawResponse), !$success);
        }
		return $success;
	}

	function refundCharge($parameters) {

		$transactionDetails = $this->makeRequest(array('method' => 'GET',
			'url' => '/tss/v2/transactions/' . $parameters['transaction_identifier']));
		$isACH = is_array($transactionDetails['paymentInformation']['bank']);

		foreach ($this->iRequiredParameters['refund_' . ($isACH ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters) || empty($parameters[$requiredParameter])) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => array("clientReferenceInformation" => array("code" => $parameters['order_number']),
				"orderInformation" => array("amountDetails" => array("totalAmount" => number_format($parameters['amount'], 2), "currency" => "USD"))),
			'url' => '/pts/v2/payments/' . $parameters['transaction_identifier'] . '/refunds'
		));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $this->iResponse['http_response'];
			$this->iResponse['result'] = $rawResponse['status'];
			$this->iResponse['result-code'] = $rawResponse['processorInformation']['responseCode'];
			$this->iResponse['transaction_id'] = $rawResponse['id'];
			if ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $rawResponse['reason'];
				$this->iResponse['display-message'] = $rawResponse['message'];
			}
		}

        $success = (is_array($rawResponse) && $rawResponse['status'] == "PENDING");
		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['transaction_identifier'], jsonEncode($rawResponse), !$success);
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

		$transactionDetails = $this->makeRequest(array(
			'url' => '/pts/v2/payments/' . $parameters['transaction_identifier'],
		));
		if ($transactionDetails['id'] != $parameters['transaction_identifier']) {
			$this->iErrorMessage = "Transaction not found.";
			return false;
		}

		$rawResponse = $this->makeRequest(array(
			'method' => 'POST',
			'fields' => array("clientReferenceInformation" => array("code" => $transactionDetails['clientReferenceInformation']['code']),
				"orderInformation" => array("amountDetails" => array(
					"totalAmount" => $transactionDetails['orderInformation']['lineItems'][0]['unitPrice'],
					"currency" => "USD"
				))),
			'url' => '/pts/v2/payments/' . $parameters['transaction_identifier'] . '/captures',
		));

		if (!empty($rawResponse)) {
			$this->iResponse['code'] = $this->iResponse['http_response'];
			$this->iResponse['result'] = $rawResponse['status'];
			$this->iResponse['result-code'] = $rawResponse['processorInformation']['responseCode'];
			$this->iResponse['authorization_code'] = $rawResponse['processorInformation']['approvalCode'];
			$this->iResponse['transaction_id'] = $rawResponse['id'];
			if (is_array($rawResponse['errorInformation'])) {
				$this->iResponse['response_reason_text'] = $rawResponse['errorInformation']['reason'];
				$this->iResponse['display-message'] = $rawResponse['errorInformation']['message'];
			}
			if (empty($this->iResponse['response_reason_text']) && $this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $rawResponse['response']['rmsg'];
			}
		}

		$successful = $rawResponse['status'] == "PENDING";
		if (empty($parameters['test_connection'])) {
			$this->writeLog($parameters['card_number'], jsonEncode($rawResponse), ($rawResponse['status'] != "success"));
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
		if (!$this->iUseSoapForTokens) {
			$customer = array(
				"objectInformation" => array(
					"title" => $parameters['first_name'] . " " . $parameters['last_name']
				),
				"buyerInformation" => array(
					"merchantCustomerID" => strval($parameters['contact_id']),
					"email" => $parameters['email_address']
				),
				"clientReferenceInformation" => array(
					"code" => strval($parameters['contact_id'])
				)
			);

			$shippingAddress = array(
				"shipTo" => array(
					"email" => $parameters['email_address']
				)
			);

			if (!empty($parameters['shipping_city']) || !empty($parameters['shipping_address_1']) || !empty($parameters['shipping_last_name'])) {
				if (!empty($parameters['shipping_first_name'])) {
					$shippingAddress["shipTo"]["firstName"] = $parameters['first_name'];
				}
				if (!empty($parameters['shipping_last_name'])) {
					$shippingAddress["shipTo"]["lastName"] = $parameters['last_name'];
				}
				if (!empty($parameters['shipping_business_name'])) {
					$shippingAddress["shipTo"]["company"] = $parameters['business_name'];
				}
				if (!empty($parameters['shipping_address_1'])) {
					$shippingAddress["shipTo"]["address1"] = $parameters['address_1'];
				}
				if (!empty($parameters['shipping_city'])) {
					$shippingAddress["shipTo"]["locality"] = $parameters['city'];
				}
				if (!empty($parameters['shipping_state'])) {
					$shippingAddress["shipTo"]["administrativeArea"] = $parameters['state'];
				}
				if (!empty($parameters['shipping_postal_code'])) {
					$shippingAddress["shipTo"]["postalCode"] = $parameters['postal_code'];
				}
				if (!empty($parameters['shipping_country_id'])) {
					$shippingAddress["shipTo"]["country"] = (empty($parameters['shipping_country_id']) ? "US" : getFieldFromId("country_code", "countries", "country_id", $parameters['shipping_country_id']));
				}
			}
			$response = $this->makeRequest(array(
				'method' => 'POST',
				'url' => '/tms/v2/customers',
				'fields' => $customer
			));
			if (empty($response)) {
				return false;
			} elseif (is_array($response['errors'])) {
				$this->iResponse['response_reason_text'] = $response['errors'][0]['type'];
				$this->iResponse['display-message'] = $response['errors'][0]['message'];
				return false;
			} elseif ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $response['response']['rmsg'];
				return false;
			}

			$merchantProfileId = $response['id'];

			// Save shipping address
			if (!empty($shippingAddress)) {
				$response = $this->makeRequest(array(
					'method' => 'POST',
					'url' => '/tms/v2/customers/' . $merchantProfileId . '/shipping-addresses',
					'fields' => $shippingAddress
				));
			}
			if (empty($response)) {
				return false;
			} elseif (is_array($response['errors'])) {
				$this->iResponse['response_reason_text'] = $response['errors']['type'];
				$this->iResponse['display-message'] = $response['errors']['message'];
				return false;
			} elseif ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $response['response']['rmsg'];
				return false;
			}

		} else { // use SOAP for tokenization - no way to save customer without payment method; create arbitrary identifier
			$merchantProfileId = "CYBSOAP-" . getRandomString(10);
		}
		$this->iResponse['merchant_identifier'] = $merchantProfileId;
		$this->createMerchantProfile($parameters['contact_id'], $this->iResponse['merchant_identifier']);
		return true;
	}

	function getCustomerProfile($parameters) {
		if (!$this->hasCustomerDatabase()) {
			return false;
		}
		if (!$this->iUseSoapForTokens) {
			$response = $this->makeRequest(array(
				'method' => 'GET',
				'url' => '/tms/v2/customers/' . $parameters['merchant_identifier']
			));

			if (empty($response)) {
				return false;
			} elseif (is_array($response['errorInformation'])) {
				$this->iResponse['response_reason_text'] = $response['errorInformation']['reason'];
				$this->iResponse['display-message'] = $response['errorInformation']['message'];
				return false;
			} elseif ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $response['response']['rmsg'];
				return false;
			}
			$this->iResponse['merchant_identifier'] = $response['id'];
		} else { // Use SOAP for tokens - no customer profile
			$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
		}

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
		if (!$this->iUseSoapForTokens) {

			$rawResponse = $this->makeRequest(array(
				'method' => 'GET',
				'url' => '/tms/v2/customers/' . $parameters['merchant_identifier'] . '/payment-instruments/' . $parameters['account_token']
			));

			if (!empty($rawResponse)) {
				$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
				$this->iResponse['payment_profile'] = $parameters['account_token'];
				if (is_array($rawResponse['card'])) {
					$this->iResponse['card_number'] = $rawResponse['_embedded']['instrumentIdentifier']['card']['number'];
					$this->iResponse['expiration_date'] = $rawResponse['card']['expirationMonth'] . substr($rawResponse['card']['expirationYear'], -2);
				} elseif (is_array($rawResponse['bankAccount'])) {
					$this->iResponse['account_type'] = $rawResponse['bankAccount']['type'];
					$this->iResponse['routing_number'] = $rawResponse['_embedded']['instrumentIdentifier']['bankAccount']['routingNumber'];
					$this->iResponse['account_number'] = $rawResponse['_embedded']['instrumentIdentifier']['bankAccount']['number'];
				} else {
					$this->iErrorMessage = "Saved account missing payment method information";
				}
			}

			return (is_array($rawResponse) && $rawResponse['state'] == "ACTIVE");
		} else { // Use SOAP for tokens
			$request = new stdClass();
			$purchaseTotals = new stdClass();
			$purchaseTotals->currency = "USD";
			$request->purchaseTotals = $purchaseTotals;

			$paySubscriptionRetrieveService = new stdClass();
			$paySubscriptionRetrieveService->run = 'true';
			$request->paySubscriptionRetrieveService = $paySubscriptionRetrieveService;

			$recurringSubscriptionInfo = new stdClass();
			$recurringSubscriptionInfo->subscriptionID = $parameters['account_token'];
			$request->recurringSubscriptionInfo = $recurringSubscriptionInfo;

			$result = $this->makeSoapRequest($request);
			if ($result->decision == 'ACCEPT') {
				$this->iResponse['account_token'] = $result->paySubscriptionRetrieveReply->subscriptionID;
				$this->iResponse['raw_response'] = json_decode(json_encode($result), true);
				return true;
			} else {
                $this->iErrorMessage = $this->iErrorMessage ?: json_encode($result);
                $this->iResponse['response_reason_text'] = !empty($result) ? ($result->decision . " " . $result->reasonCode) : "An error occurred and the request was not processed.";
				return false;
			}
		}
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

		$isACH = array_key_exists("bank_routing_number", $parameters);

		foreach ($this->iRequiredParameters['create_customer_payment_profile_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}
		if (!$this->iUseSoapForTokens) {
			// Three steps required for saving a payment token
			// Step 1 - create the customer token
			$this->createCustomerProfile($parameters);
			if (empty($this->iResponse['merchant_identifier'])) {
				return false;
			}
			$merchantProfileId = $this->iResponse['merchant_identifier'];

			// Step 2 - create the instrument identifier (the actual card or bank numbers)
			if (!$isACH) {
				$transaction = array(
					"card" => array(
						"number" => $parameters['card_number']
					));
				$cleanValues['card'] = substr($parameters['card_number'], -4);
			} else {
				$transaction = array(
					"bankAccount" => array(
						"number" => $parameters['bank_account_number'],
						"routingNumber" => $parameters['bank_routing_number']
					)
				);

				$cleanValues['bankAccount'] = array("number" => substr($parameters['bank_account_number'], -4),
                    "routingNumber" => substr($parameters['bank_routing_number'],-4));
			}

			$response = $this->makeRequest(array(
				'method' => 'POST',
				'url' => '/tms/v1/instrumentidentifiers',
				'fields' => $transaction,
                'clean_values' => $cleanValues
			));
			if (empty($response)) {
				return false;
			} elseif (is_array($response['errors'])) {
				$this->iResponse['response_reason_text'] = $response['errors']['type'];
				$this->iResponse['display-message'] = $response['errors']['message'];
				return false;
			} elseif ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $response['response']['rmsg'];
				return false;
			}
			$instrumentIdentifier = $response['id'];

			// Step 3 - using the instrument identifier, create a payment identifier token
			$countryCode = getFieldFromId('country_code', 'countries', 'country_id', $parameters['country_id']);
			$billTo = array(
				"firstName" => $parameters['first_name'],
				"lastName" => $parameters['last_name'],
				"address1" => $parameters['address_1'],
				"postalCode" => $parameters['postal_code'],
				"locality" => $parameters['city'],
				"administrativeArea" => $parameters['state'],
				"country" => $countryCode,
				"email" => $parameters['email_address']
			);
			if (!$isACH) {
				$cardType = $this->getCybersourceCardType($parameters['card_number']);
				$transaction = array(
					"card" => array(
						"expirationYear" => date("Y", strtotime($parameters['expiration_date'])),
						"expirationMonth" => date("m", strtotime($parameters['expiration_date'])),
						"type" => $cardType
					),
					"billTo" => $billTo,
					"instrumentIdentifier" => array(
						"id" => strval($instrumentIdentifier)
					)
				);
			} else {
				// SECCode: WEB = consumer ACH, CCD = business ACH
				$transaction = array(
					"bankAccount" => array(
						"type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking")
					),
					"billTo" => $billTo,
					"processingInformation" => array(
						"bankTransferOptions" => array(
							"SECCode" => $this->iSecCode
						)
					),
					"instrumentIdentifier" => array(
						"id" => strval($instrumentIdentifier)
					)
				);
			}

			$response = $this->makeRequest(array(
				'method' => 'POST',
				'url' => '/tms/v1/paymentinstruments',
				'fields' => $transaction
			));
			if (empty($response)) {
				return false;
			} elseif (is_array($response['errors'])) {
				$this->iResponse['response_reason_text'] = $response['errors'][0]['type'];
				$this->iResponse['display-message'] = $response['errors'][0]['message'];
				return false;
			} elseif ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $response['response']['rmsg'];
				return false;
			}
			$this->iResponse['merchant_identifier'] = $merchantProfileId;
			$this->iResponse['account_token'] = $response['id'];

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
		} else { // use SOAP for tokenization
			$parameters = $this->fillRequiredFields($parameters);

			$cleanValues = array();
			$request = new stdClass();
			$paySubscriptionCreateService = new stdClass();
			$paySubscriptionCreateService->run = 'true';
			$request->paySubscriptionCreateService = $paySubscriptionCreateService;

			$billTo = new stdClass();
			$billTo->firstName = $parameters['first_name'];
			$billTo->lastName = $parameters['last_name'];
			$billTo->street1 = $parameters['address_1'];
			$billTo->city = $parameters['city'];
			$billTo->state = $parameters['state'];
			$billTo->postalCode = $parameters['postal_code'];
			$billTo->country = (empty($parameters['country_id']) ? "US" : getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']));
			$billTo->phoneNumber = $parameters['phone_number'];
			$billTo->email = $parameters['email_address'];
			$request->billTo = $billTo;

			$purchaseTotals = new stdClass();
			$purchaseTotals->currency = "USD";
			$request->purchaseTotals = $purchaseTotals;

			if (!$isACH) {
				$cardType = $this->getCybersourceCardType($parameters['card_number'], true);
				$card = new stdClass();
				$card->accountNumber = $parameters['card_number'];
				$card->expirationMonth = date("m", strtotime($parameters['expiration_date']));
				$card->expirationYear = date("Y", strtotime($parameters['expiration_date']));
				$card->cardType = $cardType;
				$request->card = $card;
                $cleanValues['card'] = array('accountNumber' => substr($parameters['card_number'],-4),
                    'expirationMonth' => date("m", strtotime($parameters['expiration_date'])),
                    'expirationYear' => date("Y", strtotime($parameters['expiration_date'])),
                    'cardType' => $cardType);
			} else {
				$check = new stdClass();
				$check->accountType = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "S" : "C");
				$check->accountNumber = $parameters['bank_account_number'];
				$check->bankTransitNumber = $parameters['bank_routing_number'];
				$check->secCode = $this->iSecCode;
				$request->check = $check;

				$subscription = new stdClass();
				$subscription->paymentMethod = "check";
				$request->subscription = $subscription;
				$cleanValues['check'] = array('accountType' => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "S" : "C"),
                    'accountNumber' => substr($parameters['bank_account_number'],-4),
                    'bankTransitNumber' => substr($parameters['bank_routing_number'],-4),
                    'secCode' => $this->iSecCode);
			}

			$recurringSubscriptionInfo = new stdClass();
			$recurringSubscriptionInfo->frequency = 'on-demand';
			$request->recurringSubscriptionInfo = $recurringSubscriptionInfo;


			$result = $this->makeSoapRequest($request,"", $cleanValues);
			if ($result->decision == 'ACCEPT') {
				$this->iResponse['account_token'] = $result->paySubscriptionCreateReply->subscriptionID;
				$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
			} else {
				$this->iErrorMessage = $this->iErrorMessage ?: json_encode($result);
                $this->iResponse['response_reason_text'] = !empty($result) ? ($result->decision . " " . $result->reasonCode) : "An error occurred and the request was not processed.";
				return false;
			}
		}
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
		if (!$this->iUseSoapForTokens) {

			$this->makeRequest(array(
				'method' => 'DELETE',
				'url' => '/tms/v2/customers/' . $parameters['merchant_identifier']
			));

			$success = $this->iResponse['http_response'] == 204;
		} else { // Use SOAP for tokenization; no customer token in SOAP API
			$success = true;
		}
		return $success;
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
		if (!$this->iUseSoapForTokens) {

			$this->makeRequest(array(
				'method' => 'DELETE',
				'url' => "/tms/v1/paymentinstruments/" . $parameters['account_token']
			));

			return $this->iResponse['http_response'] == 204;
		} else { // use SOAP for tokenization
			$request = new stdClass();
			$recurringSubscriptionInfo = new stdClass();
			$recurringSubscriptionInfo->subscriptionID = $parameters['account_token'];
			$request->recurringSubscriptionInfo = $recurringSubscriptionInfo;

			$paySubscriptionDeleteService = new stdClass();
			$paySubscriptionDeleteService->run = 'true';
			$request->paySubscriptionDeleteService = $paySubscriptionDeleteService;

			$result = $this->makeSoapRequest($request);
			if ($result->decision == 'ACCEPT') {
				return true;
			} else {
				$this->iErrorMessage = $this->iErrorMessage ?: json_encode($result);
                $this->iResponse['response_reason_text'] = !empty($result) ? ($result->decision . " " . $result->reasonCode) : "An error occurred and the request was not processed.";
				return false;
			}
		}
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
		if (!$this->iUseSoapForTokens) {

			$transaction = array(
				"clientReferenceInformation" => array(
					"code" => $parameters['order_number'],
				),
				"processingInformation" => array(
					"commerceIndicator" => "internet"
				),
				"paymentInformation" => array(
					"customer" => array(
						"id" => $parameters['merchant_identifier']
					),
					"paymentInstrument" => array(
						"id" => $parameters['account_token']
					)
				),
				"orderInformation" => array(
					"amountDetails" => array(
						"totalAmount" => $parameters['amount'],
						"currency" => "USD"
					)
				)
			);
			$url = "/pts/v2/payments/";

			$rawResponse = $this->makeRequest(array('method' => 'post', 'url' => $url, 'fields' => $transaction));

			if (!empty($rawResponse)) {
				$this->iResponse['code'] = $this->iResponse['http_response'];
				$this->iResponse['result'] = $rawResponse['status'];
				$this->iResponse['result-code'] = $rawResponse['processorInformation']['responseCode'];
				$this->iResponse['authorization_code'] = $rawResponse['processorInformation']['approvalCode'];
				$this->iResponse['avs_response'] = $rawResponse['processorInformation']['avs']['code'];
				$this->iResponse['transaction_id'] = $rawResponse['id'];
				if (is_array($rawResponse['errorInformation'])) {
					$this->iResponse['response_reason_text'] = $rawResponse['errorInformation']['reason'];
					$this->iResponse['display-message'] = $rawResponse['errorInformation']['message'];
				}
				if (empty($this->iResponse['response_reason_text']) && $this->iResponse['http_response'] > 299) {
					$this->iResponse['response_reason_text'] = $rawResponse['response']['rmsg'];
				}
			}

			$successful = $rawResponse['status'] == 'PENDING' || $rawResponse['status'] == "AUTHORIZED";
		} else { // use SOAP for tokenization
			$this->getCustomerPaymentProfile($parameters);
			$isACH = empty($this->iResponse['raw_response']['paySubscriptionRetrieveReply']['cardAccountNumber']);

			$request = new stdClass();

			if (!$isACH) {
				$ccAuthService = new stdClass();
				$ccAuthService->run = 'true';
				$request->ccAuthService = $ccAuthService;

				if (empty($parameters['authorize_only'])) {
					$ccCaptureService = new stdClass();
					$ccCaptureService->run = 'true';
					$request->ccCaptureService = $ccCaptureService;
				}
			} else {
				$ecDebitService = new stdClass();
				$ecDebitService->run = 'true';
				$ecDebitService->paymentMode = 0; // 0 = process immediately; 1 = defer; 2 = process transaction that was sent with a 1
				$request->ecDebitService = $ecDebitService;

				$check = new stdClass();
				$check->secCode = $this->iSecCode;
				$request->check = $check;
			}

			$purchaseTotals = new stdClass();
			$purchaseTotals->currency = "USD";
			$purchaseTotals->grandTotalAmount = $parameters['amount'];
			$request->purchaseTotals = $purchaseTotals;

			$recurringSubscriptionInfo = new stdClass();
			$recurringSubscriptionInfo->subscriptionID = $parameters['account_token'];
			$request->recurringSubscriptionInfo = $recurringSubscriptionInfo;

			$result = $this->makeSoapRequest($request, $parameters['order_number']);
			if ($result->decision == 'ACCEPT') {
				$this->iResponse['code'] = $result->reasonCode;
				$this->iResponse['result'] = $result->decision;
				$this->iResponse['result-code'] = $result->reasonCode;
				if (!$isACH) {
					$this->iResponse['authorization_code'] = $result->ccAuthReply->authorizationCode;
					$this->iResponse['transaction_id'] = $result->requestID;
					$successful = true;
				} else {
					$this->iResponse['authorization_code'] = $result->ecDebitReply->reconciliationID;
					$this->iResponse['transaction_id'] = $result->requestID;
					$successful = true;
				}
			} else {
                $this->iErrorMessage = $this->iErrorMessage ?: json_encode($result);
                $this->iResponse['response_reason_text'] = !empty($result) ? ($result->decision . " " . $result->reasonCode) : "An error occurred and the transaction was not processed.";
				$successful = false;
			}
			$transaction = json_decode(json_encode($request), true); // convert StdClass to assoc array
			$rawResponse = json_decode(json_encode($result), true);
		}

		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), jsonEncode($transaction) . "\n\n" . jsonEncode($rawResponse), !$successful);
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
		$isACH = !empty($parameters['bank_routing_number']);

		if ((empty($parameters['country_id']) && strlen($parameters['postal_code']) == 10) || $parameters['country_id'] == "1000") {
			$parameters['postal_code'] = substr($parameters['postal_code'], 0, 5);
		}
		if (empty($parameters['country_id'])) {
			$parameters['country_id'] = 1000;
		}
		if (!$this->iUseSoapForTokens) {

			$billTo = array();
			if (!empty($parameters['first_name'])) {
				$billTo["firstName"] = $parameters['first_name'];
			}
			if (!empty($parameters['last_name'])) {
				$billTo["lastName"] = $parameters['last_name'];
			}
			if (!empty($parameters['address_1'])) {
				$billTo["address1"] = $parameters['address_1'];
			}
			if (!empty($parameters['postal_code'])) {
				$billTo["postalCode"] = $parameters['postal_code'];
			}
			if (!empty($parameters['city'])) {
				$billTo["locality"] = $parameters['city'];
			}
			if (!empty($parameters['state'])) {
				$billTo["administrativeArea"] = $parameters['state'];
			}
			if (!empty($parameters['country_id'])) {
				$countryCode = getFieldFromId('country_code', 'countries', 'country_id', $parameters['country_id']);
				$billTo["country"] = $countryCode;
			}
			if (!empty($parameters['phone_number'])) {
				$billTo["phoneNumber"] = $parameters['phone_number'];
			}
			if (!empty($parameters['email_address'])) {
				$billTo["email"] = $parameters['email_address'];
			}

			$postParameters = array();
			// Update paymentInstrument token (data other than account / card numbers)
			if (!$isACH) {
				if (!empty($parameters['expiration_date'])) {
					$postParameters["card"] = array(
						"expirationYear" => date("Y", strtotime($parameters['expiration_date'])),
						"expirationMonth" => date("m", strtotime($parameters['expiration_date']))
					);
				}
			} else {
				if (!empty($parameters['bank_account_type'])) {
					$postParameters ["bankAccount"] = array(
						"type" => (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "savings" : "checking")
					);
				}
			}
			if (!empty($billTo)) {
				$postParameters['billTo'] = $billTo;
			}
			// Anything to update?
			if (empty($postParameters)) {
				$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
				$this->iResponse['account_token'] = $parameters['account_token'];
				return true;
			}

			$response = $this->makeRequest(array(
				'method' => 'PATCH',
				'url' => '/tms/v1/paymentinstruments/' . $parameters['account_token'],
				'fields' => $postParameters
			));

			if (empty($response)) {
				return false;
			} elseif (is_array($response['errors'])) {
				$this->iResponse['response_reason_text'] = $response['errors']['type'];
				$this->iResponse['display-message'] = $response['errors']['message'];
				return false;
			} elseif ($this->iResponse['http_response'] > 299) {
				$this->iResponse['response_reason_text'] = $response['response']['rmsg'];
				return false;
			}
			$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
			$this->iResponse['account_token'] = $response['id'];

			return $response['state'] == 'ACTIVE';
		} else { // use SOAP for tokenization
			$billTo = new stdClass();
			if (!empty($parameters['first_name'])) {
				$billTo->firstName = $parameters['first_name'];
			}
			if (!empty($parameters['last_name'])) {
				$billTo->lastName = $parameters['last_name'];
			}
			if (!empty($parameters['address_1'])) {
				$billTo->street1 = $parameters['address_1'];
			}
			if (!empty($parameters['postal_code'])) {
				$billTo->postalCode = $parameters['postal_code'];
			}
			if (!empty($parameters['city'])) {
				$billTo->city = $parameters['city'];
			}
			if (!empty($parameters['state'])) {
				$billTo->state = $parameters['state'];
			}
			if (!empty($parameters['country_id'])) {
				$countryCode = getFieldFromId('country_code', 'countries', 'country_id', $parameters['country_id']);
				$billTo->country = $countryCode;
			}
			if (!empty($parameters['phone_number'])) {
				$billTo->phoneNumber = $parameters['phone_number'];
			}
			if (!empty($parameters['email_address'])) {
				$billTo->email = $parameters['email_address'];
			}

			$cleanValues = array();
			$request = new stdClass();
			if (!empty($billTo)) {
				$request->billTo = $billTo;
			}
			if (!$isACH) {
				$card = new stdClass();
				if (!empty($parameters['expiration_date'])) {
					$card->expirationYear = date("Y", strtotime($parameters['expiration_date']));
					$card->expirationMonth = date("m", strtotime($parameters['expiration_date']));
					$cleanValues['card']['expirationYear'] = date("Y", strtotime($parameters['expiration_date']));
                    $cleanValues['card']['expirationMonth'] = date("m", strtotime($parameters['expiration_date']));
				}
				if (!empty($parameters['card_number'])) {
					$card->accountNumber = $parameters['card_number'];
					$card->cardType = $this->getCybersourceCardType($parameters['card_number'], true);
					$card->securityCode = $parameters['card_code'];
                    $cleanValues['card']['accountNumber'] = substr($parameters['card_number'],-4);
                    $cleanValues['card']['cardType'] = $this->getCybersourceCardType($parameters['card_number'], true);
                    $cleanValues['card']['securityCode'] = '';
				}
				if (!empty($card)) {
					$request->card = $card;
				}
			} else {
				$check = new stdClass();
				if (!empty($parameters['bank_account_type'])) {
					$check->accountType = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "S" : "C");
                    $cleanValues['check']['accountType'] = (strpos(strtolower($parameters['bank_account_type']), "saving") !== false ? "S" : "C");
				}
				if (!empty($parameters['bank_account_number'])) {
					$check->accountNumber = $parameters['bank_account_number'];
					$check->bankTransitNumber = $parameters['bank_routing_number'];
					$check->secCode = $this->iSecCode;
                    $cleanValues['check']['accountNumber'] = substr($parameters['bank_account_number'],-4);
                    $cleanValues['check']['bankTransitNumber'] = substr($parameters['bank_routing_number'],-4);
                    $cleanValues['check']['secCode'] = $this->iSecCode;
				}
				if (!empty($check)) {
					$request->check = $check;
				}
			}
			// Anything to update?
			if (empty($request)) {
				$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
				$this->iResponse['account_token'] = $parameters['account_token'];
				return true;
			}
			$recurringSubscriptionInfo = new stdClass();
			$recurringSubscriptionInfo->subscriptionID = $parameters['account_token'];
			$request->recurringSubscriptionInfo = $recurringSubscriptionInfo;

			$paySubscriptionUpdateService = new stdClass();
			$paySubscriptionUpdateService->run = 'true';
			$request->paySubscriptionUpdateService = $paySubscriptionUpdateService;

			$result = $this->makeSoapRequest($request,"",$cleanValues);
			if ($result->decision == 'ACCEPT') {
				$this->iResponse['account_token'] = $result->paySubscriptionUpdateReply->subscriptionID;
				$this->iResponse['merchant_identifier'] = $parameters['merchant_identifier'];
				return true;
			} else {
                $this->iErrorMessage = $this->iErrorMessage ?: json_encode($result);
                $this->iResponse['response_reason_text'] = !empty($result) ? ($result->decision . " " . $result->reasonCode) : "An error occurred and the request was not processed.";
				return false;
			}
		}
	}

	function getAchStatusReport($reportDate = "") {
	    if(empty($reportDate) || !strtotime($reportDate)) {
	        $reportDate = date("Y-m-d");
        } else {
	        $reportDate = date("Y-m-d", strtotime($reportDate));
        }
	    $reportName = urlencode(getPreference("CYBERSOURCE_ACH_REPORT_NAME"));
	    if(empty($reportName)) {
	        $reportName = "ProcessorEventsDetailReport";
        }
	    $url = sprintf("/reporting/v3/report-downloads?organizationId=%s&reportName=%s&reportDate=%s", $this->iOrganizationId, $reportName, $reportDate);
	    $response = $this->makeRequest(array("method"=>"GET", "url"=>$url, "return_raw"=>true));

	    // response will be CSV if successful, JSON if error
        if(substr($response,1,1) == "{") {
            $responseArray = json_decode($response,true);
            $this->iErrorMessage = $responseArray['code'] . " " . $responseArray['detail'];
            return false;
        }
        $reportRows = str_getcsv($response, "\n");
        // first row is report metadata; ignore.
        array_shift($reportRows);
        $headers = str_getcsv(array_shift($reportRows));
        $reportData = array();
        foreach($reportRows as $row) {
            $dataRow = array_combine($headers, str_getcsv($row));
            $dataRow['authorization_code'] = $dataRow['TransactionReferenceNumber'];
            $dataRow['transaction_identifier'] = $dataRow['RequestID'];
            $dataRow['notes'] = $dataRow['Event'] . ($dataRow['Event'] !== $dataRow['ProcessorMessage'] ? ": " . $dataRow['ProcessorMessage'] : "");
            $reportData[] = $dataRow;
        }
        if(empty($reportData)) {
            $this->iErrorMessage = "No data in report.";
            return false;
        }

        return $reportData;
    }

	private function fillRequiredFields($parameters) {
		// Supply values for required fields
		if (empty($parameters['phone_number'])) {
			$parameters['phone_number'] = getContactPhoneNumber($parameters['contact_id']);
		}
		if (empty($parameters['phone_number'])) {
			$parameters['phone_number'] = '(555) 555-5555';
		}
		if (empty($parameters['email_address'])) {
			$parameters['email_address'] = getFieldFromId('email_address', 'contacts', 'contact_id', $parameters['contact_id']);
		}
		if (empty($parameters['email_address'])) {
			$parameters['email_address'] = getFieldFromId('email_address', 'contact_emails', 'contact_id', $parameters['contact_id']);
		}
		if (empty($parameters['email_address'])) { // email address is required; use client's email if missing
			$parameters['email_address'] = $GLOBALS['gClientRow']['email_address'];
		}

        $parameters['first_name'] = preg_replace("/[^A-Za-z0-9 ]/", '', $parameters['first_name']);
        $parameters['last_name'] = preg_replace("/[^A-Za-z0-9 ]/", '', $parameters['last_name']);
		if (empty($parameters['first_name']) || empty($parameters['last_name'])) {
			$fullName = preg_replace("/[^A-Za-z0-9 ]/", '', getDisplayName($parameters['contact_id']));
			if (empty($fullName)) {
				$fullName = getDisplayName($parameters['contact_id'], array('use_company' => true));
			}
			if (empty($fullName)) {
				$fullName = $GLOBALS['gClientName'] . ' customer';
			}
			// Remove non-alphanumeric characters from the name to make sure both name parts are valid
            $fullName = preg_replace("/[^A-Za-z0-9 ]/", '', $fullName);
			$nameParts = explode(' ', $fullName);
			if (empty($parameters['first_name'])) {
				$parameters['first_name'] = array_shift($nameParts);
			}
			if (empty($parameters['last_name'])) {
				if (count($nameParts) > 0) {
					$parameters['last_name'] = implode(' ', $nameParts);
				} else {
					$parameters['last_name'] = $fullName;
				}
			}
		}
		return $parameters;
	}

	private function getCybersourceCardType($cardNumber, $useNumericCode = false) {
		$cardType = getCreditCardType($cardNumber);
		if ($useNumericCode) {
			switch ($cardType) {
				case "MASTERCARD":
					return "002";
				case "AMEX":
					return "003";
				case "DISCOVER":
					return "004";
				case "DINERSCLUB":
					return "005";
				case "CARTEBLANCHE":
					return "006";
				case "JCB":
					return "007";
				case "ENROUTE":
					return "014";
				default: // Visa and default for unsupported cards (will error)
					return "001";
			}
		} else {
			switch ($cardType) {
				case "AMEX":
					return "american express";
				case "DINERSCLUB":
					return "diners club";
				case "CARTEBLANCHE":
					return "carte blanche";
				case "ENROUTE":
					return "enRoute";
				default:
					return strtolower($cardType);
			}
		}
	}

	/********
	 * Sample code for HTTP signature authentication from Cybersource git
	 * https://github.com/CyberSource/cybersource-rest-samples-php/blob/master/Samples/Authentication/StandAloneHttpSignature.php
	 */

// Function to parse response headers
// ref/credit: http://php.net/manual/en/function.http-parse-headers.php#112986
	private function parseHttpHeaders($rawHeaders) {
		$headers = [];
		$key = '';
		foreach (getContentLines($rawHeaders) as $thisHeader) {
			$thisHeader = explode(':', $thisHeader, 2);
			if (isset($thisHeader[1])) {

				if (!isset($headers[$thisHeader[0]])) {
					$headers[$thisHeader[0]] = trim($thisHeader[1]);
				} elseif (is_array($headers[$thisHeader[0]])) {
					$headers[$thisHeader[0]] = array_merge($headers[$thisHeader[0]], [trim($thisHeader[1])]);
				} else {
					$headers[$thisHeader[0]] = array_merge([$headers[$thisHeader[0]]], [trim($thisHeader[1])]);
				}
				$key = $thisHeader[0];
			} else {
				if (substr($thisHeader[0], 0, 1) === "\t") {
					$headers[$key] .= "\r\n\t" . trim($thisHeader[0]);
				} elseif (!$key) {
					$headers[0] = trim($thisHeader[0]);
				}
				trim($thisHeader[0]);
			}
		}
		return $headers;
	}

// Function used to generate the digest for the given payload
	private function generateDigest($requestPayload) {
		$utf8EncodedString = utf8_encode($requestPayload);
		$digestEncode = hash("sha256", $utf8EncodedString, true);
		return base64_encode($digestEncode);
	}

// Function to generate the HTTP Signature
// param: url - full URL of the request
// param: httpMethod - denotes the HTTP verb
// param: currentDate - stores the current timestamp
	private function getHttpSignature($url, $httpMethod, $currentDate, $payload) {
		$requestHost = parse_url($url, PHP_URL_HOST);
		$requestPath = parse_url($url, PHP_URL_PATH) . (empty(parse_url($url, PHP_URL_QUERY)) ? "" : "?" . parse_url($url, PHP_URL_QUERY));

		// Digest of body must be included if there is a request body.  GET and DELETE do not have a request body, so there is no digest for them.
		$digest = "";
		$includeDigest = $httpMethod == "post" || $httpMethod == "put" || $httpMethod == "patch";

		if ($includeDigest) {
			$digest = $this->generateDigest($payload);
			$signatureString = "host: " . $requestHost . "\ndate: " . $currentDate . "\n(request-target): " . $httpMethod . " " . $requestPath . "\ndigest: SHA-256=" . $digest . "\nv-c-merchant-id: " . $this->iOrganizationId;
			$headerString = "host date (request-target) digest v-c-merchant-id";
		} else { // Get or Delete requests
			$signatureString = "host: " . $requestHost . "\ndate: " . $currentDate . "\n(request-target): " . $httpMethod . " " . $requestPath . "\nv-c-merchant-id: " . $this->iOrganizationId;
			$headerString = "host date (request-target) v-c-merchant-id";
		}

		$signatureByteString = utf8_encode($signatureString);
		$decodeKey = base64_decode($this->iSharedSecret);
		$signature = base64_encode(hash_hmac("sha256", $signatureByteString, $decodeKey, true));
		$signatureHeader = array(
			'keyid="' . $this->iKeyId . '"',
			'algorithm="HmacSHA256"',
			'headers="' . $headerString . '"',
			'signature="' . $signature . '"'
		);

		$signatureToken = "Signature:" . implode(", ", $signatureHeader);

		$host = "Host:" . $requestHost;
		$vcMerchant = "v-c-merchant-id:" . $this->iOrganizationId;
		$headers = array(
			$vcMerchant,
			$signatureToken,
			$host,
			'Date:' . $currentDate
		);

		if ($includeDigest) {
			$digestArray = array("Digest: SHA-256=" . $digest);
			$headers = array_merge($headers, $digestArray);
		}

		return $headers;
	}

}
