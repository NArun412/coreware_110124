<?php

class MailChimpBatch {
	private $mailChimp;

	private $operations = array();
	private $batchId;

	public function __construct(MailChimp $mailChimp, $batchId = null) {
		$this->mailChimp = $mailChimp;
		$this->batchId = $batchId;
	}

	public function getBatchId() {
		return $this->batchId;
	}

	/**
	* Add an HTTP DELETE request operation to the batch - for deleting data
	* @param   string $id ID for the operation within the batch
	* @param   string $method URL of the API request method
	* @return  void
	*/
	public function delete($id, $method) {
		$this->queueOperation('DELETE', $id, $method);
	}

	/**
	* Add an HTTP GET request operation to the batch - for retrieving data
	* @param   string $id ID for the operation within the batch
	* @param   string $method URL of the API request method
	* @param   array $args Assoc array of arguments (usually your data)
	* @return  void
	*/
	public function get($id, $method, $args = array()) {
		$this->queueOperation('GET', $id, $method, $args);
	}

	/**
	* Add an HTTP PATCH request operation to the batch - for performing partial updates
	* @param   string $id ID for the operation within the batch
	* @param   string $method URL of the API request method
	* @param   array $args Assoc array of arguments (usually your data)
	* @return  void
	*/
	public function patch($id, $method, $args = array()) {
		$this->queueOperation('PATCH', $id, $method, $args);
	}

	/**
	* Add an HTTP POST request operation to the batch - for creating and updating items
	* @param   string $id ID for the operation within the batch
	* @param   string $method URL of the API request method
	* @param   array $args Assoc array of arguments (usually your data)
	* @return  void
	*/
	public function post($id, $method, $args = array()) {
		$this->queueOperation('POST', $id, $method, $args);
	}

	/**
	* Add an HTTP PUT request operation to the batch - for creating new items
	* @param   string $id ID for the operation within the batch
	* @param   string $method URL of the API request method
	* @param   array $args Assoc array of arguments (usually your data)
	* @return  void
	*/
	public function put($id, $method, $args = array()) {
		$this->queueOperation('PUT', $id, $method, $args);
	}

	/**
	* Execute the batch request
	* @param int $timeout Request timeout in seconds (optional)
	* @return  array|false   Assoc array of API response, decoded from JSON
	*/
	public function execute($timeout = 30) {
		$req = array('operations' => $this->operations);

		$result = $this->mailChimp->post('batches', $req, $timeout);

		if ($result && isset($result['id'])) {
			$this->batchId = $result['id'];
			addProgramLog("MailChimp Batch ID for " . $GLOBALS['gClientRow']['client_code'] . ": " . $this->batchId);
		}

		return $result;
	}

	/**
	* Check the status of a batch request. If the current instance of the Batch object
	* was used to make the request, the batchId is already known and is therefore optional.
	* @param string $batchId ID of the batch about which to enquire
	* @return  array|false   Assoc array of API response, decoded from JSON
	*/
	public function checkStatus($batchId = null) {
		if ($batchId === null && $this->batchId) {
			$batchId = $this->batchId;
		}

		return $this->mailChimp->get('batches/' . $batchId);
	}

	/**
	*  Get operations
	*  @return array
	*/
	public function getOperations() {
		return $this->operations;
	}

	/**
	* Add an operation to the internal queue.
	* @param   string $httpVerb GET, POST, PUT, PATCH or DELETE
	* @param   string $id ID for the operation within the batch
	* @param   string $method URL of the API request method
	* @param   array $args Assoc array of arguments (usually your data)
	* @return  void
	*/
	private function queueOperation($httpVerb, $id, $method, $args = null) {
		$operation = array(
			'operation_id' => $id,
			'method' => $httpVerb,
			'path' => $method,
		);

		if ($args) {
			if($httpVerb == 'GET') {
				$key = 'params';
				$operation[$key] = $args;
			} else {
				$key = 'body';
				$operation[$key] = json_encode($args,JSON_FORCE_OBJECT);
			}
		}

		$this->operations[] = $operation;
	}
}
