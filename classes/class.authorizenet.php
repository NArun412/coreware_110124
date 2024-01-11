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

class AuthorizeNet extends eCommerce {

	var $iCredentials = array();
	var $iAimLiveUrl = "https://secure2.authorize.net/gateway/transact.dll";
	var $iAimTestUrl = "https://test.authorize.net/gateway/transact.dll";
	var $iCimLiveUrl = "https://api2.authorize.net/xml/v1/request.api";
	var $iCimTestUrl = "https://apitest.authorize.net/xml/v1/request.api";

	var $iDefaultParameters = array("x_version" => "3.1", "x_type" => "AUTH_CAPTURE", "x_method" => "CC", "x_delim_data" => "TRUE",
		"x_delim_char" => "|", "x_encap_char" => "", "x_email_customer" => "FALSE");

	function canDoPreAuthOnly() {
	    return false;
    }

	function __construct($merchantAccountRow) {
		$this->iMerchantAccountRow = $merchantAccountRow;
		if (($GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['merchant_account_code'] != "DEVELOPMENT") ||
			(!$GLOBALS['gDevelopmentServer'] && $this->iMerchantAccountRow['client_id'] != $GLOBALS['gClientId'])) {
			$this->iMerchantAccountRow = array();
		}
		if ($GLOBALS['gDevelopmentServer']) {
			$this->iAimLiveUrl = $this->iAimTestUrl;
			$this->iCimLiveUrl = $this->iCimTestUrl;
		}
		$this->iCredentials = array("login" => $this->iMerchantAccountRow['account_login'], "transaction_key" => $this->iMerchantAccountRow['account_key']);
	}

	function testConnection() {
		$result = $this->authorizeCharge(array("amount" => 5.00, "card_number" => "6011111111111117", "expiration_date" => "10/01/2040", "order_number" => "93489243", "description" => "Test Connection",
			"first_name" => "Kim", "last_name" => "Geiger", "address_1" => "PO Box 439482", "city" => "Lindale", "country_id" => 1000, "contact_id" => 10000, "test_connection" => true, "card_code" => "234"));
		return (strpos($this->iResponse['raw_response'], "password is invalid") === false);
	}

# AIM routines

	function voidCharge($parameters) {
		foreach ($this->iRequiredParameters['void_' . (array_key_exists("bank_routing_number", $parameters) ? "check" : "card")] as $requiredParameter) {
			if (!array_key_exists($requiredParameter, $parameters)) {
				$this->iErrorMessage .= (empty($this->iErrorMessage) ? "" : ", ") . "'" . $requiredParameter . "' is required";
			}
		}
		if (!empty($this->iErrorMessage)) {
			return false;
		}

		if (array_key_exists("bank_routing_number", $parameters)) {
			$parameters['x_method'] = "ECHECK";
		}
		$cleanValues = array();
		$combineArray = array_merge($this->iDefaultParameters, $parameters);
		$postFields = "";
		$postFields .= "x_login=" . $this->iCredentials['login'];
		$postFields .= "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$cleanValues[] = "x_login=" . $this->iCredentials['login'] . "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$postFields .= "&x_version=" . $combineArray['x_version'];
		$postFields .= "&x_type=void";
		$postFields .= "&x_method=" . $combineArray['x_method'];
		$postFields .= "&x_amount=" . rawurlencode($parameters['amount']);
		if (!empty($parameters['card_number'])) {
			$postFields .= "&x_card_num=" . rawurlencode($parameters['card_number']);
			$cleanValues[] = "x_card_num=" . rawurlencode($parameters['card_number']);
			$postFields .= "&x_exp_date=" . rawurlencode(date("m-Y", strtotime($parameters['expiration_date'])));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$postFields .= "&x_card_code=" . rawurlencode($parameters['card_code']);
				$cleanValues[] = "x_card_code=" . rawurlencode($parameters['card_code']);
			}
		} else if (!empty($parameters['bank_routing_number'])) {
			$postFields .= "&x_echeck_type=WEB";
			$postFields .= "&x_bank_aba_code=" . rawurlencode($parameters['bank_routing_number']);
			$cleanValues[] = "x_bank_aba_code=" . rawurlencode($parameters['bank_routing_number']);
			$postFields .= "&x_bank_acct_num=" . rawurlencode($parameters['bank_account_number']);
			$cleanValues[] = "x_bank_acct_num=" . rawurlencode($parameters['bank_account_number']);
			$postFields .= "&x_bank_acct_type=" . rawurlencode($parameters['bank_account_type']);
			$ch = curl_init("http://www.routingnumbers.info/api/name.json?rn=" . $parameters['bank_routing_number']);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
			curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
			$bankJson = curl_exec($ch);
			curl_close($ch);
			$bankInfo = json_decode($bankJson, true);
			$postFields .= "&x_bank_name=" . rawurlencode($bankInfo['name']);
			$postFields .= "&x_bank_acct_name=" . rawurlencode($parameters['first_name'] . " " . $parameters['last_name']);
		}
		$postFields .= "&x_trans_id=" . $combineArray['transaction_identifier'];
		$postFields .= "&x_delim_data=" . $combineArray['x_delim_data'];
		$postFields .= "&x_delim_char=" . $combineArray['x_delim_char'];
		$postFields .= "&x_encap_char=" . $combineArray['x_encap_char'];

		$ch = curl_init($this->iAimLiveUrl);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$buffer = curl_exec($ch);
		curl_close($ch);

		// cleanLogContent will either:
		// 1) replace matching array indexes if the first parameter is an array (so clean values should have the replacement content)
		// 2) remove matching strings if the first parameter is a string. (so clean values should have the content TO BE replaced)
		$logContent = $this->cleanLogContent($postFields, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
        $eCommerceLogContent = $logContent;
		$logContent = $buffer . "\n";
		$this->writeExternalLog($logContent);
        $eCommerceLogContent .= "\n\n" . $logContent;

		$response = explode($combineArray['x_delim_char'], $buffer);
		$this->iResponse = array();
		$this->iResponse['raw_response'] = $buffer;
		$this->iResponse['response_code'] = $response[0];
		$this->iResponse['response_subcode'] = $response[1];
		$this->iResponse['response_reason_code'] = $response[2];
		$this->iResponse['response_reason_text'] = $response[3];
        $success = $this->iResponse['response_code'] == "1";

        if (empty($parameters['test_connection'])) {
            $eCommerceLogContent = $this->iResponse['response_reason_text'] . "\n\n" . $eCommerceLogContent;
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $eCommerceLogContent, !$success);
        }

        return $success;
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

		if (array_key_exists("bank_routing_number", $parameters) || empty($parameters['card_number'])) {
			return false;
		}
		$cleanValues = array();
		$combineArray = array_merge($this->iDefaultParameters, $parameters);
		$postFields = "";
		$postFields .= "x_login=" . $this->iCredentials['login'];
		$postFields .= "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$cleanValues[] = "x_login=" . $this->iCredentials['login'] . "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$postFields .= "&x_version=" . $combineArray['x_version'];
		$postFields .= "&x_type=credit";
		$postFields .= "&x_method=" . $combineArray['x_method'];
		$postFields .= "&x_amount=" . rawurlencode($parameters['amount']);
		$postFields .= "&x_card_num=" . rawurlencode($parameters['card_number']);
		$cleanValues[] = "x_card_num=" . rawurlencode($parameters['card_number']);
		$postFields .= "&x_trans_id=" . $combineArray['transaction_identifier'];
		$postFields .= "&x_delim_data=" . $combineArray['x_delim_data'];
		$postFields .= "&x_delim_char=" . $combineArray['x_delim_char'];
		$postFields .= "&x_encap_char=" . $combineArray['x_encap_char'];

		$ch = curl_init($this->iAimLiveUrl);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$buffer = curl_exec($ch);
		curl_close($ch);

		// cleanLogContent will either:
		// 1) replace matching array indexes if the first parameter is an array (so clean values should have the replacement content)
		// 2) remove matching strings if the first parameter is a string. (so clean values should have the content TO BE replaced)
		$logContent = $this->cleanLogContent($postFields, $cleanValues) . "\n";
        $this->writeExternalLog($logContent);
        $eCommerceLogContent = $logContent;
        $logContent = $buffer . "\n";
		$this->writeExternalLog($logContent);
        $eCommerceLogContent .= "\n\n" . $logContent;

		$response = explode($combineArray['x_delim_char'], $buffer);
		$this->iResponse = array();
		$this->iResponse['raw_response'] = $buffer;
		$this->iResponse['response_code'] = $response[0];
		$this->iResponse['response_subcode'] = $response[1];
		$this->iResponse['response_reason_code'] = $response[2];
		$this->iResponse['response_reason_text'] = $response[3];

        $success = $this->iResponse['response_code'] == "1";

        if (empty($parameters['test_connection'])) {
            $eCommerceLogContent = $this->iResponse['response_reason_text'] . "\n\n" . $eCommerceLogContent;
            $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $eCommerceLogContent, !$success);
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

		$cleanValues = array();
		$combineArray = array_merge($this->iDefaultParameters, $parameters);
		$cardPresent = false;
		$postFields = "";
		$postFields .= "x_login=" . $this->iCredentials['login'];
		$postFields .= "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$cleanValues[] = "x_login=" . $this->iCredentials['login'] . "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$postFields .= "&x_version=" . $combineArray['x_version'];
		$postFields .= "&x_type=" . "PRIOR_AUTH_CAPTURE";
		$postFields .= "&x_auth_code=" . $combineArray['authorization_code'];
		$postFields .= "&x_trans_id=" . $combineArray['transaction_identifier'];

		$ch = curl_init($this->iAimLiveUrl);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$buffer = curl_exec($ch);
		curl_close($ch);

		$logContent = $this->cleanLogContent($postFields, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $buffer . "\n";
		$this->writeExternalLog($logContent);

		$response = explode($combineArray['x_delim_char'], $buffer);
		$this->iResponse = array();
        $this->iResponse['raw_response'] = $buffer;
        if(count($response) == 1) {
            $this->iResponse['response_reason_text'] = $response[0];
        } else {
            if ($cardPresent) {
                $this->iResponse['response_code'] = $response[1];
                $this->iResponse['response_reason_code'] = $response[2];
                $this->iResponse['response_reason_text'] = $response[3];
                $this->iResponse['authorization_code'] = $response[4];
                $this->iResponse['avs_response'] = $response[5];
                $this->iResponse['card_code_response'] = $response[6];
                $this->iResponse['transaction_id'] = $response[7];
            } else {
                $this->iResponse['response_code'] = $response[0];
                $this->iResponse['response_subcode'] = $response[1];
                $this->iResponse['response_reason_code'] = $response[2];
                $this->iResponse['response_reason_text'] = $response[3];
                $this->iResponse['authorization_code'] = $response[4];
                $this->iResponse['avs_response'] = $response[5];
                $this->iResponse['transaction_id'] = $response[6];
                $this->iResponse['card_code_response'] = $response[38];
            }
        }
		if (empty($parameters['test_connection'])) {
			$this->writeLog("", $this->iResponse['response_reason_text'], ($this->iResponse['response_code'] != "1"));
		}

		return ($this->iResponse['response_code'] == "1");
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
		if (array_key_exists("bank_routing_number", $parameters)) {
			$parameters['x_method'] = "ECHECK";
		}

		$cleanValues = array();
		$combineArray = array_merge($this->iDefaultParameters, $parameters);
		$cardPresent = false;
		$postFields = "";
		$postFields .= "x_login=" . $this->iCredentials['login'];
		$postFields .= "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$cleanValues[] = "x_login=" . $this->iCredentials['login'] . "&x_tran_key=" . $this->iCredentials['transaction_key'];
		$postFields .= "&x_version=" . $combineArray['x_version'];
		$postFields .= "&x_type=" . (empty($parameters['authorize_only']) ? $combineArray['x_type'] : "AUTH_ONLY");
		$postFields .= "&x_method=" . $combineArray['x_method'];
		$postFields .= "&x_amount=" . rawurlencode($parameters['amount']);
		if (!empty($parameters['track_1']) || !empty($parameters['track_2'])) {
			$postFields .= "&x_market_type=2";
			$postFields .= "&x_device_type=5";
			$postFields .= (empty($parameters['track_1']) ? "" : "&x_track1=" . rawurlencode($parameters['track_1']));
			if (!empty($parameters['track_1'])) {
				$cleanValues[] = "x_track1=" . rawurlencode($parameters['track_1']);
			}
			$postFields .= (empty($parameters['track_2']) ? "" : "&x_track2=" . rawurlencode($parameters['track_2']));
			if (!empty($parameters['track_2'])) {
				$cleanValues[] = "x_track2=" . rawurlencode($parameters['track_2']);
			}
			$cardPresent = true;
		} else {
			$postFields .= "&x_market_type=0";
		}
		if (!empty($parameters['card_number'])) {
			$postFields .= "&x_card_num=" . rawurlencode($parameters['card_number']);
			$cleanValues[] = "x_card_num=" . rawurlencode($parameters['card_number']);
			$postFields .= "&x_exp_date=" . rawurlencode(date("m-Y", strtotime($parameters['expiration_date'])));
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$postFields .= "&x_card_code=" . rawurlencode($parameters['card_code']);
				$cleanValues[] = "x_card_code=" . rawurlencode($parameters['card_code']);
			}
		} else if (!empty($parameters['bank_routing_number'])) {
			$postFields .= "&x_echeck_type=WEB";
			$postFields .= "&x_bank_aba_code=" . rawurlencode($parameters['bank_routing_number']);
			$cleanValues[] = "x_bank_aba_code=" . rawurlencode($parameters['bank_routing_number']);
			$postFields .= "&x_bank_acct_num=" . rawurlencode($parameters['bank_account_number']);
			$cleanValues[] = "&x_bank_acct_num=" . rawurlencode($parameters['bank_account_number']);
			$postFields .= "&x_bank_acct_type=" . rawurlencode($parameters['bank_account_type']);
			$ch = curl_init("http://www.routingnumbers.info/api/name.json?rn=" . $parameters['bank_routing_number']);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$bankJson = curl_exec($ch);
			curl_close($ch);
			$bankInfo = json_decode($bankJson, true);
			$postFields .= "&x_bank_name=" . rawurlencode($bankInfo['name']);
			$postFields .= "&x_bank_acct_name=" . rawurlencode($parameters['first_name'] . " " . $parameters['last_name']);
		}
		$postFields .= "&x_invoice_num=" . rawurlencode($parameters['order_number']);
		$postFields .= "&x_description=" . rawurlencode($parameters['description']);
		$postFields .= "&x_first_name=" . rawurlencode($parameters['first_name']);
		$postFields .= "&x_last_name=" . rawurlencode($parameters['last_name']);
		$postFields .= "&x_company=" . rawurlencode($parameters['business_name']);
		$postFields .= "&x_address=" . rawurlencode($parameters['address_1']);
		$postFields .= "&x_city=" . rawurlencode($parameters['city']);
		$postFields .= "&x_state=" . rawurlencode($parameters['state']);
		$postFields .= "&x_zip=" . rawurlencode($parameters['postal_code']);
		$postFields .= "&x_country=" . rawurlencode(getFieldFromId("country_code", "countries", "country_id", $parameters['country_id']));
		$postFields .= "&x_email=" . rawurlencode($parameters['email_address']);
		$postFields .= "&x_phone=" . rawurlencode($parameters['phone_number']);
		$postFields .= "&x_cust_id=" . rawurlencode($parameters['contact_id']);
		$postFields .= "&x_customer_ip=" . $_SERVER['REMOTE_ADDR'];
		$postFields .= "&x_delim_data=" . $combineArray['x_delim_data'];
		$postFields .= "&x_delim_char=" . $combineArray['x_delim_char'];
		$postFields .= "&x_encap_char=" . $combineArray['x_encap_char'];
		$postFields .= "&x_tax=" . (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : "0");
		$postFields .= "&x_freight=" . (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : "0");
		$postFields .= "&x_email_customer=" . $combineArray['x_email_customer'];
		$postFields .= "&x_header_email_receipt=";
		$postFields .= "&x_footer_email_receipt=";
		$postFields .= "&x_relay_response=FALSE";

# order items: array("list_price"=>"0.00","discount_rate"=>"0.00","quantity"=>"1","product_id"=>"932","description"=>"Description"

		if (array_key_exists("order_items", $parameters)) {
			if (is_array($parameters['order_items']) && count($parameters['order_items']) < 20) {
				foreach ($parameters['order_items'] as $orderItem) {
					$discountedPrice = $orderItem['sale_price'];
					if ($orderItem['quantity'] >= 0 && $discountedPrice >= 0) {
						$productCode = getFieldFromId("product_code", "products", "product_id", $orderItem['product_id']);
						if (empty($productCode) || strlen($productCode) > 30) {
							$productCode = $orderItem['product_id'];
						}
						$postFields .= "&x_line_item=" . rawurlencode($orderItem['product_id'] . "<|>" . $productCode . "<|>" .
								(empty($orderItem['description']) ? getFieldFromId("description", "products", "product_id", $orderItem['product_id']) : $orderItem['description']) . "<|>" .
								$orderItem['quantity'] . "<|>" . $discountedPrice . "<|>" . (getFieldFromId("not_taxable", "products", "product_id", $orderItem['product_id']) == 1 ? "N" : "Y"));
					}
				}
			}
		}

		$ch = curl_init($this->iAimLiveUrl);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$buffer = curl_exec($ch);
		curl_close($ch);

		$logContent = $this->cleanLogContent($postFields, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $buffer . "\n";
		$this->writeExternalLog($logContent);

		$response = explode($combineArray['x_delim_char'], $buffer);
        $this->iResponse = array();
        $this->iResponse['raw_response'] = $buffer;
        if(count($response) == 1) {
            $this->iResponse['response_reason_text'] = $response[0];
        } else {
            if ($cardPresent) {
                $this->iResponse['response_code'] = $response[1];
                $this->iResponse['response_reason_code'] = $response[2];
                $this->iResponse['response_reason_text'] = $response[3];
                $this->iResponse['authorization_code'] = $response[4];
                $this->iResponse['avs_response'] = $response[5];
                $this->iResponse['card_code_response'] = $response[6];
                $this->iResponse['transaction_id'] = $response[7];
            } else {
                $this->iResponse['response_code'] = $response[0];
                $this->iResponse['response_subcode'] = $response[1];
                $this->iResponse['response_reason_code'] = $response[2];
                $this->iResponse['response_reason_text'] = $response[3];
                $this->iResponse['authorization_code'] = $response[4];
                $this->iResponse['avs_response'] = $response[5];
                $this->iResponse['transaction_id'] = $response[6];
                $this->iResponse['card_code_response'] = $response[38];
            }
        }
		if (empty($parameters['test_connection'])) {
			$this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $this->iResponse['response_reason_text'], ($this->iResponse['response_code'] != "1"));
		}

		return ($this->iResponse['response_code'] == "1");
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
		$cleanValues = array();
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<getCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"</getCustomerProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$this->iResponse['merchant_identifier'] = $responseArray['customerProfileId'];
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				return false;
			}
		} else {
			$this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
			return false;
		}
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
		$cleanValues = array();
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<createCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<profile>" .
			"<merchantCustomerId>" . $parameters['contact_id'] . "</merchantCustomerId>" .
            (empty($parameters['email_address']) ? "" : "<email>" . $parameters['email_address'] . "</email>") .
			"</profile>" .
			"<validationMode>none</validationMode>" .
			"</createCustomerProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$this->iResponse['merchant_identifier'] = $responseArray['customerProfileId'];
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				if ($this->iResponse['response_reason_code'] == "E00039") {
					$merchantIdentifier = intval(str_replace(" already exists.", "", str_replace("a duplicate record with id ", "", strtolower($this->iResponse['response_reason_text']))));
					if (is_numeric($merchantIdentifier)) {
						$this->iResponse['merchant_identifier'] = $merchantIdentifier;
						$this->createMerchantProfile($parameters['contact_id'], $merchantIdentifier);
						return true;
					}
				}
				return false;
			}
		} else {
			$this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
			return false;
		}
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
		$cleanValues = array();
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<getCustomerPaymentProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"<customerPaymentProfileId>" . $parameters['account_token'] . "</customerPaymentProfileId>" .
			"</getCustomerPaymentProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$this->iResponse['payment_profile'] = $responseArray['paymentProfile'];
				$this->iResponse['first_name'] = $this->iResponse['payment_profile']['billTo']['firstName'];
				$this->iResponse['last_name'] = $this->iResponse['payment_profile']['billTo']['lastName'];
				$this->iResponse['business_name'] = $this->iResponse['payment_profile']['billTo']['company'];
				$this->iResponse['address_1'] = $this->iResponse['payment_profile']['billTo']['address'];
				$this->iResponse['city'] = $this->iResponse['payment_profile']['billTo']['city'];
				$this->iResponse['state'] = $this->iResponse['payment_profile']['billTo']['state'];
				$this->iResponse['postal_code'] = $this->iResponse['payment_profile']['billTo']['zip'];
				$this->iResponse['country'] = $this->iResponse['payment_profile']['billTo']['country'];
				if (array_key_exists("payment", $this->iResponse['payment_profile']) && array_key_exists("creditCard", $this->iResponse['payment_profile']['payment'])) {
					$this->iResponse['card_number'] = $this->iResponse['payment_profile']['payment']['creditCard']['cardNumber'];
				}
				if (array_key_exists("payment", $this->iResponse['payment_profile']) && array_key_exists("bankAccount", $this->iResponse['payment_profile']['payment'])) {
					$this->iResponse['account_type'] = $this->iResponse['payment_profile']['payment']['bankAccount']['accountType'];
					$this->iResponse['routing_number'] = $this->iResponse['payment_profile']['payment']['bankAccount']['routingNumber'];
					$this->iResponse['account_number'] = $this->iResponse['payment_profile']['payment']['bankAccount']['accountNumber'];
					$this->iResponse['echeck_type'] = $this->iResponse['payment_profile']['payment']['bankAccount']['echeckType'];
					$this->iResponse['bank_name'] = $this->iResponse['payment_profile']['payment']['bankAccount']['bankName'];
				}
                $this->iResponse['payment_profile'] = $responseArray['paymentProfile']['customerPaymentProfileId'];
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				return false;
			}
		} else {
			$this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
			return false;
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
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<createCustomerPaymentProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"<paymentProfile>" .
			"<billTo>" .
			"<firstName>" . htmlText($parameters['first_name']) . "</firstName>" .
			"<lastName>" . htmlText($parameters['last_name']) . "</lastName>" .
			"<company>" . htmlText($parameters['business_name']) . "</company>" .
			"<address>" . htmlText($parameters['address_1']) . "</address>" .
			"<city>" . htmlText($parameters['city']) . "</city>" .
			"<state>" . htmlText($parameters['state']) . "</state>" .
			"<zip>" . htmlText($parameters['postal_code']) . "</zip>" .
			"<country>" . htmlText(getFieldFromId("country_name", "countries", "country_id", $parameters['country_id'])) . "</country>" .
			"</billTo>";
		if (!empty($parameters['bank_routing_number'])) {
			$ch = curl_init("http://www.routingnumbers.info/api/name.json?rn=" . $parameters['bank_routing_number']);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$bankJson = curl_exec($ch);
			curl_close($ch);
			$bankInfo = json_decode($bankJson, true);
			$firstName = str_replace(" and ", " & ", $parameters['first_name']);
			if (strpos($firstName, " & ") !== false) {
				$position = strpos($firstName, " & ");
				$firstName = substr($firstName, 0, $position);
			}
			$nameOnAccount = $firstName . " " . $parameters['last_name'];
			if (strlen($nameOnAccount) > 22) {
				$nameOnAccount = substr($nameOnAccount, 0, 22);
			}
			$content .= "<payment><bankAccount>" .
				"<accountType>" . $parameters['bank_account_type'] . "</accountType>" .
				"<routingNumber>" . $parameters['bank_routing_number'] . "</routingNumber>" .
				"<accountNumber>" . $parameters['bank_account_number'] . "</accountNumber>" .
				"<nameOnAccount>" . htmlText($nameOnAccount) . "</nameOnAccount>" .
				"<echeckType>WEB</echeckType>" .
				"<bankName>" . htmlText($bankInfo['name']) . "</bankName>" .
				"</bankAccount></payment>";
			$cleanValues[] = "<routingNumber>" . $parameters['bank_routing_number'] . "</routingNumber>";
			$cleanValues[] = "<accountNumber>" . $parameters['bank_account_number'] . "</accountNumber>";
		} else {
			$content .= "<payment><creditCard>" .
				"<cardNumber>" . $parameters['card_number'] . "</cardNumber>" .
				"<expirationDate>" . date("Y-m", strtotime($parameters['expiration_date'])) . "</expirationDate>";
			if ($parameters['card_code'] != "SKIP_CARD_CODE") {
				$content .= "<cardCode>" . $parameters['card_code'] . "</cardCode>";
			}
			$content .= "</creditCard></payment>";
			$cleanValues[] = "<cardNumber>" . $parameters['card_number'] . "</cardNumber>";
			$cleanValues[] = "<cardCode>" . $parameters['card_code'] . "</cardCode>";
		}
		$content .= "</paymentProfile><validationMode>none</validationMode></createCustomerPaymentProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues);
        $this->writeExternalLog($logContent);
        $eCommerceLogContent = $logContent;
        $logContent = $response . "\n";
		$this->writeExternalLog($logContent);
        $eCommerceLogContent .= "\n\n" . $logContent;

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
        if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
            $success = $responseArray['messages']['resultCode'] == "Ok";
            if (empty($parameters['test_connection'])) {
                $eCommerceLogContent = $this->iResponse['response_reason_text'] . "\n\n" . $eCommerceLogContent;
                $this->writeLog((!empty($parameters['card_number']) ? $parameters['card_number'] : $parameters['bank_account_number']), $eCommerceLogContent, !$success);
            }
            if ($success) {
				$this->iResponse['account_token'] = $responseArray['customerPaymentProfileId'];
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				return false;
			}
		} else {
			$this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
			return false;
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
		$cleanValues = array();
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<deleteCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"</deleteCustomerProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$this->iResponse['merchant_identifier'] = $responseArray['customerProfileId'];
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				return false;
			}
		} else {
			$this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
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
		$cleanValues = array();
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<deleteCustomerPaymentProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"<customerPaymentProfileId>" . $parameters['account_token'] . "</customerPaymentProfileId>" .
			"</deleteCustomerPaymentProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$this->iResponse['account_token'] = $responseArray['customerPaymentProfileId'];
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				return false;
			}
		} else {
			$this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
			return false;
		}
		if (!empty($parameters['account_id'])) {
			$this->deleteCustomerAccount($parameters['account_id']);
		}
		return true;
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
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<transaction>";
		if ($parameters['authorize_only']) {
			$this->iDefaultParameters['x_type'] = "AUTH_ONLY";
		}
		switch ($this->iDefaultParameters['x_type']) {
			case "AUTH_CAPTURE":
				$content .= "<profileTransAuthCapture>";
				break;
			case "AUTH_ONLY":
				$content .= "<profileTransAuthOnly>";
				break;
			case "CREDIT":
				$content .= "<profileTransRefund>";
				break;
			case "PRIOR_AUTH_CAPTURE":
				$content .= "<profileTransPriorAuthCapture>";
				break;
			case "VOID":
				$content .= "<profileTransVoid>";
				break;
		}
		$content .= "<amount>" . $parameters['amount'] . "</amount>" .
			"<tax><amount>" . (array_key_exists("tax_charge", $parameters) ? $parameters['tax_charge'] : "0") . "</amount></tax>" .
			"<shipping><amount>" . (array_key_exists("shipping_charge", $parameters) ? $parameters['shipping_charge'] : "0") . "</amount></shipping>";

		if (array_key_exists("order_items", $parameters)) {
			foreach ($parameters['order_items'] as $orderItem) {
				$discountedPrice = $orderItem['sale_price'];
				$content .= "<lineItems><itemId>" . $orderItem['product_id'] . "</itemId><name>" .
					htmlText(getFirstPart(getFieldFromId("product_code", "products", "product_id", $orderItem['product_id']), 30, false, false)) .
					"</name><description>" . htmlText((empty($orderItem['description']) ? getFieldFromId("description", "products", "product_id", $orderItem['product_id']) : $orderItem['description'])) .
					"</description><quantity>" . $orderItem['quantity'] . "</quantity><unitPrice>" . $discountedPrice . "</unitPrice></lineItems>";
			}
		}

		$content .= "<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"<customerPaymentProfileId>" . $parameters['account_token'] . "</customerPaymentProfileId>" .
			"<order><invoiceNumber>" . $parameters['order_number'] . "</invoiceNumber></order>";
		switch ($this->iDefaultParameters['x_type']) {
			case "AUTH_CAPTURE":
				$content .= "</profileTransAuthCapture>";
				break;
			case "AUTH_ONLY":
				$content .= "</profileTransAuthOnly>";
				break;
			case "CREDIT":
				$content .= "</profileTransRefund>";
				break;
			case "PRIOR_AUTH_CAPTURE":
				$content .= "</profileTransPriorAuthCapture>";
				break;
			case "VOID":
				$content .= "</profileTransVoid>";
				break;
		}
		$content .= "</transaction>" .
			"<extraOptions><![CDATA[x_customer_ip=" . $_SERVER['REMOTE_ADDR'] . "]]></extraOptions>" .
			"</createCustomerProfileTransactionRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$directResponse = $responseArray['directResponse'];
                $this->iDefaultParameters['x_delim_char'] = ",";
                $response = explode($this->iDefaultParameters['x_delim_char'], $directResponse);
                $this->iResponse['response_code'] = $response[0];
                $this->iResponse['response_subcode'] = $response[1];
                $this->iResponse['response_reason_code'] = $response[2];
                $this->iResponse['response_reason_text'] = $response[3];
                $this->iResponse['authorization_code'] = $response[4];
                $this->iResponse['avs_response'] = $response[5];
                $this->iResponse['transaction_id'] = $response[6];
                $this->iResponse['card_code_response'] = $response[38];
                $success = true;
			} else {
				$this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$success = false;
			}
		} else {
			$this->iResponse['response_reason_text']  = "Connection to authorize.net failed.";
			$success = false;
		}

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
		$cleanValues = array();
		$cleanValues[] = "<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>";
		$content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
			"<updateCustomerPaymentProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
			"<merchantAuthentication>" .
			"<name>" . $this->iCredentials['login'] . "</name>" .
			"<transactionKey>" . $this->iCredentials['transaction_key'] . "</transactionKey>" .
			"</merchantAuthentication>" .
			"<customerProfileId>" . $parameters['merchant_identifier'] . "</customerProfileId>" .
			"<paymentProfile>" .
			"<billTo>" .
			"<firstName>" . htmlText($parameters['first_name']) . "</firstName>" .
			"<lastName>" . htmlText($parameters['last_name']) . "</lastName>" .
			"<company>" . htmlText($parameters['business_name']) . "</company>" .
			"<address>" . htmlText($parameters['address_1']) . "</address>" .
			"<city>" . htmlText($parameters['city']) . "</city>" .
			"<state>" . htmlText($parameters['state']) . "</state>" .
			"<zip>" . htmlText($parameters['postal_code']) . "</zip>" .
			"<country>" . htmlText($parameters['country']) . "</country>" .
			"</billTo>";
		if (!empty($parameters['routing_number'])) {
			$firstName = str_replace(" and ", " & ", $parameters['first_name']);
			if (strpos($firstName, " & ") !== false) {
				$position = strpos($firstName, " & ");
				$firstName = substr($firstName, 0, $position);
			}
			$nameOnAccount = $firstName . " " . $parameters['last_name'];
			if (strlen($nameOnAccount) > 22) {
				$nameOnAccount = substr($nameOnAccount, 0, 22);
			}
			$content .= "<payment><bankAccount>" .
				"<accountType>" . $parameters['account_type'] . "</accountType>" .
				"<routingNumber>" . $parameters['routing_number'] . "</routingNumber>" .
				"<accountNumber>" . $parameters['account_number'] . "</accountNumber>" .
				"<nameOnAccount>" . htmlText($nameOnAccount) . "</nameOnAccount>" .
				"<echeckType>" . $parameters['echeck_type'] . "</echeckType>" .
				"<bankName>" . $parameters['bank_name'] . "</bankName>" .
				"</bankAccount></payment>";
			$cleanValues[] = "<routingNumber>" . $parameters['routing_number'] . "</routingNumber>";
			$cleanValues[] = "<accountNumber>" . $parameters['account_number'] . "</accountNumber>";
		} else {
			$content .= "<payment><creditCard>" .
				"<cardNumber>" . $parameters['card_number'] . "</cardNumber>" .
				"<expirationDate>" . (array_key_exists("expiration_date", $parameters) ? date("Y-m", strtotime($parameters['expiration_date'])) : "XXXX") .
				"</expirationDate></creditCard></payment>";
			$cleanValues[] = "<cardNumber>" . $parameters['card_number'] . "</cardNumber>";
		}
		$content .= "<customerPaymentProfileId>" . $parameters['account_token'] . "</customerPaymentProfileId>" .
			"</paymentProfile><validationMode>none</validationMode></updateCustomerPaymentProfileRequest>";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->iCimLiveUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: text/xml"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec($ch);

		$logContent = $this->cleanLogContent($content, $cleanValues) . "\n";
		$this->writeExternalLog($logContent);
		$logContent = $response . "\n";
		$this->writeExternalLog($logContent);

		$this->iResponse = array();
		$this->iResponse['raw_response'] = $response;
		if ($response) {
			$responseArray = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
			$responseArray = processXml($responseArray);
			$responseArray = reset($responseArray);
			if ($responseArray['messages']['resultCode'] == "Ok") {
				$this->iResponse['account_token'] = $responseArray['customerPaymentProfileId'];
			} else {
				$this->iErrorMessage = $this->iResponse['response_reason_text'] = $responseArray['messages']['message']['text'];
				$this->iResponse['response_reason_code'] = $responseArray['messages']['message']['code'];
				return false;
			}
		} else {
			$this->iErrorMessage = $this->iResponse['response_reason_text'] = "Connection to authorize.net failed.";
			return false;
		}
		return true;
	}
}
