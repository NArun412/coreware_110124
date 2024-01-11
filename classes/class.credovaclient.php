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

const CREDOVA_PRODUCTION_URL = "https://lending-api.credova.com";
const CREDOVA_SANDBOX_URL = "https://sandbox-lending-api.credova.com";

class CredovaClient {
	private $iCredentials = array();
	private $iAccessToken;
	private $iApiUrl;
	private $iErrorMessage;
	private static $iCredovaApiTimeout = 10;

	public function __construct() {
        $this->iCredentials['un'] = getPreference("CREDOVA_USERNAME");
        $this->iCredentials['pw'] = getPreference("CREDOVA_PASSWORD");
		$this->iApiUrl = ($GLOBALS['gDevelopmentServer'] ? CREDOVA_SANDBOX_URL : CREDOVA_PRODUCTION_URL);
		$this->iAccessToken = null;
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

    public function testCredentials() {
        return $this->authenticate();
    }

	/**
	 * Performs an authentication with Credova API.
	 *
	 * @return bool true or false
	 */
	public function authenticate() {
		$returnValue = false;
		$body = http_build_query(array("username" => $this->iCredentials['un'], "password" => $this->iCredentials['pw']), '', '&');
		$headers = array("Content-Type: application/x-www-form-urlencoded");

		$response = $this->callApi("POST", "v2/token", $body, $headers, FALSE);

		if (!empty($response['json']) and array_key_exists("jwt", $response['json'])) {
			$this->iAccessToken = $response['json']['jwt'];
			$returnValue = true;
		}
		return $returnValue;
	}

    public static function isSetup() {
        $credovaPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "CREDOVA", "inactive = 0");
        $credovaPaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "CREDOVA", "inactive = 0");
        return !empty($credovaPaymentMethodId) && !empty($credovaPaymentMethodTypeId);
    }
    public static function setup() {
        $credovaPaymentMethodTypeRow = getRowFromId("payment_method_types", "payment_method_type_code", "CREDOVA");
        if(empty($credovaPaymentMethodTypeRow)) {
            $insertSet = executeQuery("insert into payment_method_types (client_id, payment_method_type_code, description) values (?,'CREDOVA', 'Credova')", $GLOBALS['gClientId']);
            $credovaPaymentMethodTypeId = $insertSet['insert_id'];
        } else {
            if($credovaPaymentMethodTypeRow['inactive']) {
                updateFieldById("inactive", 0,"payment_method_types", "payment_method_type_id", $credovaPaymentMethodTypeRow['payment_method_type_id']);
            }
            $credovaPaymentMethodTypeId = $credovaPaymentMethodTypeRow['payment_method_type_id'];
        }
        $credovaPaymentMethodRow = getRowFromId("payment_methods", "payment_method_code", "CREDOVA");
        if(empty($credovaPaymentMethodRow)) {
            $insertSet = executeQuery("insert into payment_methods (client_id, payment_method_code, description, payment_method_type_id) values (?,'CREDOVA','Credova', ?)",
                $GLOBALS['gClientId'], $credovaPaymentMethodTypeId);
            $credovaPaymentMethodId = $insertSet['insert_id'];
        } else {
            if($credovaPaymentMethodRow['inactive']) {
                updateFieldById("inactive", 0,"payment_methods", "payment_method_id", $credovaPaymentMethodRow['payment_method_id']);
            }
            $credovaPaymentMethodId = $credovaPaymentMethodRow['payment_method_id'];
        }
        if(empty($credovaPaymentMethodId)) {
            return array("error_message"=>($insertSet['sql_error'] ?: "An error occurred and Credova payment method could not be created."));
        }
        return array();
    }

	/**
	 * Checks if we are authenticated.
	 *
	 * @return bool True if client is authenticated
	 */
	public function isAuthenticated() {
		return !empty($this->iAccessToken);
	}

	/**
	 * Sends financing application to Credova.
	 * @param array $application application assoc array structure
	 * @param string $cburl Webhook URL (must be HTTPS!)
	 * @return array|boolean Array with application id and redirect url
	 */
	public function apply($application, $cburl = null) {
		$body = json_encode($application);
		$headers = array("Content-Type: application/json");

		if ($cburl) {
			$headers[] = "Callback-Url: " . $cburl;
		}

		$response = $this->callApi("POST", "v2/applications", $body, $headers);

		if (!empty($response['json']) and array_key_exists('publicId', $response['json']) and array_key_exists('link', $response['json'])) {
			$returnValue = array($response['json']['publicId'], $response['json']['link']);
		} else {
			$this->iErrorMessage = "Credova v2/applications API returned unexpected response=" . $response['body'];
			return false;
		}

		return $returnValue;
	}

	/**
	 * Check application status by its public id.
	 * @param string	$publicId application public id
	 * @param string	$phone Optional phone number
	 * @return array	 Array with application status
	 */
	public function checkStatus($publicId=null, $phone=null) {
		$url = "";
		if ($publicId) {
			$url = sprintf("v2/applications/%s/status", $publicId);
		} elseif ($phone) {
			$url = sprintf("v2/applications/phone/%s/status", $phone);
		}

		$response = $this->callApi("GET", $url);

		return $response['json'];
	}

	/**
	 * Obtains financing offers from lenders restricted by specified filters.
	 * @param string	$amount Desired amount
	 * @param string	$lenderCode Optional lender code
	 * @param string	$storeId Optional store id
	 * @return array|boolean	 Array with offers
	 */
	public function getOffers($amount, $lenderCode=null, $storeId=null) {
		if (!$lenderCode and !$storeId) {
			$this->iErrorMessage = "Either lenderCode or storeId must be specified.";
			return false;
		}

		$url = "";
		if ($lenderCode) {
			$url = sprintf("v2/Calculator/Lender/%s/Amount/%s", $lenderCode, $amount);
		} elseif ($storeId) {
			$url = sprintf("v2/Calculator/Store/%s/Amount/%s", $storeId, $amount);
		}

		$response = $this->callApi("POST", $url);

		return $response['json'];
	}

	/**
	 * Obtains a list of configured lenders for a retailer.
	 * @return array Array of lenders
	 */
	public function getLenders() {
		$response = $this->callApi("GET", "v2/Lenders");
		return $response['json'];
	}

	/**
	 * Obtains a list of stores for a retailer.
	 * @return array Array of stores
	 */
	public function getStores() {
		$response = $this->callApi("GET", "v2/Stores");
		return $response['json'];
	}

	/**
	 * Signal Credova about a return from a customer.
	 * @param string $publicId Apllication public id
	 * @return true Array with a request status
	 */
	public function requestReturn($publicId) {
		$response = $this->callApi("POST", sprintf("v2/applications/%s/requestReturn", $publicId));
		return $response['json'];
	}

	/**
	 * Upload invoice file in PDF format to Credova.
	 * @param string $publicId Apllication public id
	 * @param string $invoiceFile path to an invoice file (PDF)
	 * @return true True on success
	 */
	public function uploadInvoice($publicId, $invoiceFile) {
		$url = sprintf("v2/applications/%s/uploadInvoice", $publicId);
		$boundary = uniqid();
		$body = $this->encodeMultipart($boundary, array($invoiceFile));

		$headers = array(
			sprintf("Content-Type: multipart/form-data; boundary=%s", $boundary),
			sprintf("Content-Length: %d", strlen($body))
		);

		$response = $this->callApi("POST", $url, $body, $headers);

		if (!empty($response['json']) and array_key_exists("status", $response['json'])) {
			$returnValue = $response['json']['status'];
		} else {
			$this->iErrorMessage = "Credova ${url} API returned unexpected response=" . $response['body'];
			$returnValue = false;
		}
		return $returnValue;
	}

	/**
	 * Creates new delivery information
	 * @param string Application public id
	 * @param array $data Information to be sent
	 * @return array Array with a creation status
	 */
	public function delivery($publicId, $data) {
		$headers = array("Content-Type: application/json");
		$url = sprintf("v2/applications/%s/deliveryInformation/", $publicId);
		$response = $this->callApi("POST", $url, json_encode($data), $headers);

		return $response['json'];
	}

	/**
	 * Insert federal license information
	 * @param array $data Information to be sent
	 * @return string PublicId
	 */
	public function createFFL($data) {
		$body = json_encode($data);
		$headers = array("Content-Type: application/json");

		$response = $this->callApi("POST", "v2/federalLicense/", $body, $headers);

		if (!empty($response['json']) and array_key_exists('publicId', $response['json'])) {
			$returnValue = $response['json']['publicId'];
		} else {
			$this->iErrorMessage = "Credova v2/federalLicense API returned unexpected response=" . $response['body'];
			return false;
		}

		return $returnValue;
	}

	/**
	 * Get federal license information by it's number
	 * @param string $fflNumber Federal license number
	 * @return array license information
	 */
	public function getFFL($fflNumber) {
		$url = sprintf("v2/federalLicense/licenseNumber/%s", $fflNumber);
		$response = $this->callApi("GET", $url);
		return $response['json'];
	}

	/**
	 * Gets the monthly lowest payment option, financed period, and early buyout options for a specific store
	 * @param $amount
	 * @return array Array with calculations
	 */
	public function getLowestPayment($amount) {
		$url = sprintf("v2/calculator/store/%s/amount/%s/lowestPaymentOption", $this->iCredentials['un'], $amount);
		$response = $this->callApi("POST", $url);
		return $response['json'];
	}

	/**
	 * Upload federal license file in PDF format to Credova.
	 * @param string $fflPublicId License public id
	 * @param string $fflLicense path to an license file (PDF)
	 * @return true True on success
	 */
	public function uploadFFL($fflPublicId, $fflLicense) {
		$url = sprintf("v2/federalLicense/%s/uploadFile", $fflPublicId);
		$boundary = uniqid();
		$body = $this->encodeMultipart($boundary, array($fflLicense));

		$headers = array(
			sprintf("Content-Type: multipart/form-data; boundary=%s", $boundary),
			sprintf("Content-Length: %d", strlen($body))
		);

		$response = $this->callApi("POST", $url, $body, $headers);

		if (!empty($response['json']) and array_key_exists("status", $response['json'])) {
			$returnValue = $response['json']['status'];
		} else {
			$this->iErrorMessage = "Credova ${url} API returned unexpected response=" . $response['body'];
			return false;
		}
		return $returnValue;
	}

	//
	// Private methods
	//
	function callApi($method, $path, $body = null, $headers = array(), $sendAuthentication = TRUE) {
		$result = $this->sendRequest($method, $path, $body, $headers, $sendAuthentication);

		if (!empty($result['api_error'])) {
			$this->iErrorMessage = $result['api_error'];
			return false;
		} elseif ($result['status_code'] != 200) {
			$this->iErrorMessage = "Credova responded with an error: status=${result['status_code']} body=${result['body']} headers=${result['headers']}";
			return false;
		}
		return $result;
	}

	private function encodeMultipart($boundary, $filenames, $mime='application/pdf') {
		$data = '';
		$crlf = "\r\n";

		foreach ($filenames as $name) {
			$data .= "--" . $boundary . $crlf
				. 'Content-Disposition: form-data; name="file"; filename="' . $name . '"' . $crlf
				. 'Content-Type: ' . $mime . $crlf;

			$data .= $crlf;
			$data .= file_get_contents($name) . $crlf;
		}
		$data .= "--" . $boundary . "--".$crlf;

		return $data;
	}

	function sendRequest($method, $path, $body = null, $headers = array(), $sendAuthentication = TRUE) {
		$url = $this->iApiUrl . '/' . $path;

		if ($sendAuthentication and !$this->iAccessToken) {
			$this->iErrorMessage = "You must call authenticate() method first.";
			return false;
		}

		if ($this->iAccessToken) {
			$headers[] = 'Authorization: Bearer ' . $this->iAccessToken;
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // we want response body
		curl_setopt($curl, CURLOPT_HEADER, true); // we want headers

		curl_setopt($curl, CURLOPT_VERBOSE, true);

		curl_setopt($curl, CURLOPT_TIMEOUT, self::$iCredovaApiTimeout);
		curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($curl, CURLOPT_ENCODING, ''); // we accept all supported data compress formats

		// comment line below to see whats flying on HTTP layer
		curl_setopt($curl, CURLINFO_HEADER_OUT, true);

		switch ($method) {
			case 'POST':
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
				break;

			case 'GET':
				break;
		}

		$response = curl_exec($curl);

		if (curl_errno($curl) > 0) {
			$this->iErrorMessage = curl_error($curl) . ":" . curl_errno($curl);
			return false;
		}

		$result = array('headers' => '', 'body' => '', 'status_code' => '', 'json' => '', 'api_error' => '');

		$headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$result['headers'] = substr($response, 0, $headerSize);
		$result['body'] = substr($response, $headerSize);
		$result['status_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if ($result['body']) {
			$result['json'] = json_decode($result['body'], true);

			// status_code != 200
			if ($result['json'] and array_key_exists("errors", $result['json'])) {
				$result['api_error'] = implode("", $result['json']['errors']);
			}
		}

		curl_close($curl);

		return $result;
	}
}
