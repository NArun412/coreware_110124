<?php

class MailChimp {
	private $apiKey;
	private $apiEndpoint = 'https://<dc>.api.mailchimp.com/3.0';

	const TIMEOUT = 10;
	public $verifySSL = true;

	private $requestSuccessful = false;
	private $lastError		= '';
	private $lastResponse	= array();
	private $lastRequest	= array();

	/**
	* Create a new instance
	* @param string $apiKey Your MailChimp API key
	* @param string $apiEndpoint Optional custom API endpoint
	* @throws \Exception
	*/
	public function __construct($apiKey, $apiEndpoint = null) {
		$this->apiKey = $apiKey;
		if ($GLOBALS['gDevelopmentServer']) {
			$this->verifySSL = false;
		}

		if ($apiEndpoint === null) {
			if (strpos($this->apiKey, '-') === false) {
				throw new \Exception("Invalid MailChimp API key `{$apiKey}` supplied.");
			}
			list(, $dataCenter) = explode('-', $this->apiKey);
			$this->apiEndpoint = str_replace('<dc>', $dataCenter, $this->apiEndpoint);
		} else {
			$this->apiEndpoint = $apiEndpoint;
		}

		$this->lastResponse = array('headers' => null, 'body' => null);
	}

	/**
	* Create a new instance of a Batch request. Optionally with the ID of an existing batch.
	* @param string $batch_id Optional ID of an existing batch, if you need to check its status for example.
	* @return Batch			New Batch object.
	*/
	public function newBatch($batchId = null) {
		return new MailChimpBatch($this, $batchId);
	}

	/**
	* @return string The url to the API endpoint
	*/
	public function getApiEndpoint() {
		return $this->apiEndpoint;
	}


	/**
	* Convert an email address into a 'subscriber hash' for identifying the subscriber in a method URL
	* @param string $email The subscriber's email address
	* @return string		Hashed version of the input
	*/
	public function subscriberHash($email) {
		return md5(strtolower($email));
	}

	/**
	* Was the last request successful?
	* @return bool True for success, false for failure
	*/
	public function success() {
		return $this->requestSuccessful;
	}

	/**
	* Get the last error returned by either the network transport, or by the API.
	* If something didn't work, this should contain the string describing the problem.
	* @return string|false describing the error
	*/
	public function getLastError() {
		return $this->lastError ?: false;
	}

	/**
	* Get an array containing the HTTP headers and the body of the API response.
	* @return array Assoc array with keys 'headers' and 'body'
	*/
	public function getLastResponse() {
		return $this->lastResponse;
	}

	/**
	* Get an array containing the HTTP headers and the body of the API request.
	* @return array Assoc array
	*/
	public function getLastRequest() {
		return $this->lastRequest;
	}

	/**
	* Make an HTTP DELETE request - for deleting data
	* @param string $method URL of the API request method
	* @param array $args Assoc array of arguments (if any)
	* @param int $timeout Timeout limit for request in seconds
	* @return array|false Assoc array of API response, decoded from JSON
	*/
	public function delete($method, $args = array(), $timeout = self::TIMEOUT) {
		return $this->makeRequest('delete', $method, $args, $timeout);
	}

	/**
	* Make an HTTP GET request - for retrieving data
	* @param string $method URL of the API request method
	* @param array $args Assoc array of arguments (usually your data)
	* @param int $timeout Timeout limit for request in seconds
	* @return array|false Assoc array of API response, decoded from JSON
	*/
	public function get($method, $args = array(), $timeout = self::TIMEOUT) {
		return $this->makeRequest('get', $method, $args, $timeout);
	}

	/**
	* Make an HTTP PATCH request - for performing partial updates
	* @param string $method URL of the API request method
	* @param array $args Assoc array of arguments (usually your data)
	* @param int $timeout Timeout limit for request in seconds
	* @return array|false Assoc array of API response, decoded from JSON
	*/
	public function patch($method, $args = array(), $timeout = self::TIMEOUT) {
		return $this->makeRequest('patch', $method, $args, $timeout);
	}

	/**
	* Make an HTTP POST request - for creating and updating items
	* @param string $method URL of the API request method
	* @param array $args Assoc array of arguments (usually your data)
	* @param int $timeout Timeout limit for request in seconds
	* @return array|false Assoc array of API response, decoded from JSON
	*/
	public function post($method, $args = array(), $timeout = self::TIMEOUT) {
		return $this->makeRequest('post', $method, $args, $timeout);
	}

	/**
	* Make an HTTP PUT request - for creating new items
	* @param string $method URL of the API request method
	* @param array $args Assoc array of arguments (usually your data)
	* @param int $timeout Timeout limit for request in seconds
	* @return array|false Assoc array of API response, decoded from JSON
	*/
	public function put($method, $args = array(), $timeout = self::TIMEOUT) {
		return $this->makeRequest('put', $method, $args, $timeout);
	}

	/**
	* Performs the underlying HTTP request. Not very exciting.
	* @param string $httpVerb The HTTP verb to use: get, post, put, patch, delete
	* @param string $method The API method to be called
	* @param array $args Assoc array of parameters to be passed
	* @param int $timeout
	* @return array|false Assoc array of decoded result
	* @throws \Exception
	*/
	private function makeRequest($httpVerb, $method, $args = array(), $timeout = self::TIMEOUT) {
		if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
			throw new \Exception("cURL support is required, but can't be found.");
		}

		$url = $this->apiEndpoint . '/' . $method;

		$response = $this->prepareStateForRequest($httpVerb, $method, $url, $timeout);

		$curlHandle = curl_init();
		curl_setopt($curlHandle, CURLOPT_URL, $url);
		curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
			'Accept: application/vnd.api+json',
			'Content-Type: application/vnd.api+json',
			'Authorization: apikey ' . $this->apiKey
		));
		curl_setopt($curlHandle, CURLOPT_USERAGENT, 'Coreware/MailChimp-API/3.0');
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlHandle, CURLOPT_VERBOSE, true);
		curl_setopt($curlHandle, CURLOPT_HEADER, true);
		curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
		curl_setopt($curlHandle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($curlHandle, CURLOPT_ENCODING, '');
		curl_setopt($curlHandle, CURLINFO_HEADER_OUT, true);

		switch ($httpVerb) {
			case 'post':
				curl_setopt($curlHandle, CURLOPT_POST, true);
				$this->attachRequestPayload($curlHandle, $args);
				break;

			case 'get':
				$query = http_build_query($args, '', '&');
				curl_setopt($curlHandle, CURLOPT_URL, $url . '?' . $query);
				break;

			case 'delete':
				curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;

			case 'patch':
				curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'PATCH');
				$this->attachRequestPayload($curlHandle, $args);
				break;

			case 'put':
				curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, 'PUT');
				$this->attachRequestPayload($curlHandle, $args);
				break;
		}

		$responseContent	= curl_exec($curlHandle);
		$response['headers'] = curl_getinfo($curlHandle);
		$response			= $this->setResponseState($response, $responseContent, $curlHandle);
		$formattedResponse = $this->formatResponse($response);

		curl_close($curlHandle);

		$this->determineSuccess($response, $formattedResponse, $timeout);

		return $formattedResponse;
	}

	/**
	* @param string $httpVerb
	* @param string $method
	* @param string $url
	* @param integer $timeout
	*/
	private function prepareStateForRequest($httpVerb, $method, $url, $timeout) {
		$this->lastError = '';

		$this->requestSuccessful = false;

		$this->lastResponse = array(
			'headers'	=> null, // array of details from curl_getinfo()
			'httpHeaders' => null, // array of HTTP headers
			'body'		=> null // content of the response
		);

		$this->lastRequest = array(
			'method' => $httpVerb,
			'path'	=> $method,
			'url'	=> $url,
			'body'	=> '',
			'timeout' => $timeout,
		);

		return $this->lastResponse;
	}

	/**
	* Get the HTTP headers as an array of header-name => header-value pairs.
	*
	* The "Link" header is parsed into an associative array based on the
	* rel names it contains. The original value is available under
	* the "_raw" key.
	*
	* @param string $headersAsString
	* @return array
	*/
	private function getHeadersAsArray($headersAsString) {
		$headers = array();

		foreach (explode("\r\n", $headersAsString) as $i => $line) {
			if ($i === 0) { // HTTP code
				continue;
			}

			$line = trim($line);
			if (empty($line)) {
				continue;
			}

			list($key, $value) = explode(': ', $line);

			if ($key == 'Link') {
				$value = array_merge(
					array('_raw' => $value),
					$this->getLinkHeaderAsArray($value)
				);
			}

			$headers[$key] = $value;
		}

		return $headers;
	}

	/**
	* Extract all rel => URL pairs from the provided Link header value
	*
	* Mailchimp only implements the URI reference and relation type from
	* RFC 5988, so the value of the header is something like this:
	*
	* 'https://us13.api.mailchimp.com/schema/3.0/Lists/Instance.json; rel="describedBy", <https://us13.admin.mailchimp.com/lists/members/?id=XXXX>; rel="dashboard"'
	*
	* @param string $linkHeaderAsString
	* @return array
	*/
	private function getLinkHeaderAsArray($linkHeaderAsString) {
		$urls = array();

		if (preg_match_all('/<(.*?)>\s*;\s*rel="(.*?)"\s*/', $linkHeaderAsString, $matches)) {
			foreach ($matches[2] as $i => $relName) {
				$urls[$relName] = $matches[1][$i];
			}
		}

		return $urls;
	}

	/**
	* Encode the data and attach it to the request
	* @param resource $curlHandle cURL session handle, used by reference
	* @param array $data Assoc array of data to attach
	*/
	private function attachRequestPayload(&$curlHandle, $data) {
		$encoded = json_encode($data);
		$this->lastRequest['body'] = $encoded;
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $encoded);
	}

	/**
	* Decode the response and format any error messages for debugging
	* @param array $response The response from the curl request
	* @return array|false	The JSON decoded into an array
	*/
	private function formatResponse($response) {
		$this->lastResponse = $response;

		if (!empty($response['body'])) {
			return json_decode($response['body'], true);
		}

		return false;
	}

	/**
	* Do post-request formatting and setting state from the response
	* @param array $response The response from the curl request
	* @param string $responseContent The body of the response from the curl request
	* * @return array	The modified response
	*/
	private function setResponseState($response, $responseContent, $curlHandle) {
		if ($responseContent === false) {
			$this->lastError = curl_error($curlHandle);
		} else {

			$headerSize = $response['headers']['header_size'];

			$response['httpHeaders'] = $this->getHeadersAsArray(substr($responseContent, 0, $headerSize));
			$response['body'] = substr($responseContent, $headerSize);

			if (isset($response['headers']['request_header'])) {
				$this->lastRequest['headers'] = $response['headers']['request_header'];
			}
		}

		return $response;
	}

	/**
	* Check if the response was successful or a failure. If it failed, store the error.
	* @param array $response The response from the curl request
	* @param array|false $formattedResponse The response body payload from the curl request
	* @param int $timeout The timeout supplied to the curl request.
	* @return bool	If the request was successful
	*/
	private function determineSuccess($response, $formattedResponse, $timeout) {
		$status = $this->findHTTPStatus($response, $formattedResponse);

		if ($status >= 200 && $status <= 299) {
			$this->requestSuccessful = true;
			return true;
		}

		if (isset($formattedResponse['detail'])) {
			$this->lastError = sprintf('%d: %s', $formattedResponse['status'], $formattedResponse['detail']);
			return false;
		}

		if( $timeout > 0 && $response['headers'] && $response['headers']['total_time'] >= $timeout ) {
			$this->lastError = sprintf('Request timed out after %f seconds.', $response['headers']['total_time'] );
			return false;
		}

		$this->lastError = 'Unknown error, call getLastResponse() to find out what happened.';
		return false;
	}

	/**
	* Find the HTTP status code from the headers or API response body
	* @param array $response The response from the curl request
	* @param array|false $formattedResponse The response body payload from the curl request
	* @return int HTTP status code
	*/
	private function findHTTPStatus($response, $formattedResponse) {
		if (!empty($response['headers']) && isset($response['headers']['http_code'])) {
			return (int) $response['headers']['http_code'];
		}

		if (!empty($response['body']) && isset($formattedResponse['status'])) {
			return (int) $formattedResponse['status'];
		}

		return 418;
	}
}
