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

class HighLevel {

	const HIGHLEVEL_CI = "6413cf2c09edfb1419037620-lfbx343h";
	const HIGHLEVEL_CS = "4a7c11cd-c26e-4211-b300-3f170e10bfd5";

	const HIGHLEVEL_LIVE_URL = "https://services.leadconnectorhq.com";
	const HIGHLEVEL_DISPLAY_NAME = "coreILLA";

	private $iAccessToken;
	private $iHighLevelLocationId;
	private $iIdentifierTypeId;

	private $iBaseUrl;
	private $iErrorMessage;
	private $iLoggingLevel;
    private $iLastLogTime;
    private $iLastMemory;

	function __construct($accessToken, $highLevelLocationId) {
		$this->iBaseUrl = HighLevel::getBaseURL();
		$this->iAccessToken = $accessToken;
		$this->iHighLevelLocationId = $highLevelLocationId;
		$this->iLoggingLevel = getPreference("LOG_HIGHLEVEL");

		$tokenExpiration = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_TOKEN_EXPIRES");
		if (strtotime($tokenExpiration) < time()) {
			self::refreshToken();
			$this->iAccessToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ACCESS_TOKEN");
		}

		$this->iIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types",
			"contact_identifier_type_code", makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ID");
		if (empty($this->iIdentifierTypeId)) {
			$resultSet = executeQuery("insert into contact_identifier_types (client_id, contact_identifier_type_code, description, internal_use_only) values (?,?,?,?)",
				$GLOBALS['gClientId'], makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ID", HighLevel::HIGHLEVEL_DISPLAY_NAME . " Contact Identifier", 1);
			$this->iIdentifierTypeId = $resultSet['insert_id'];
		}
	}

	private static function getRedirectUrl() {
        $linkName = getFieldFromId("link_name", "pages", "script_filename", "highleveltoken.php", "client_id = ?", $GLOBALS['gDefaultClientId']);
        if(empty($linkName)) {
            $linkName = makeCode(self::HIGHLEVEL_DISPLAY_NAME . "-token",["lowercase"=>true,"use_dash"=>true]);
            $managementTemplateId = getFieldFromId("template_id", "templates","template_code", "MANAGEMENT", "client_id = ?", $GLOBALS['gDefaultClientId']);
            $insertSet = executeQuery("insert into pages (client_id, page_code, description,date_created, creator_user_id, link_name, template_id, script_filename) " .
                "values (?,?,?,CURRENT_DATE,?,?,?,'highleveltoken.php')", $GLOBALS['gDefaultClientId'], makeCode(self::HIGHLEVEL_DISPLAY_NAME . "_TOKEN"),
                self::HIGHLEVEL_DISPLAY_NAME ." Token", $GLOBALS['gUserId'], $linkName, $managementTemplateId);
            $pageId = $insertSet['insert_id'];
            executeQuery("insert into page_access (page_id, all_client_access, administrator_access, permission_level) values (?,1,1,3)", $pageId);
        }
		return getDomainName() . "/" . $linkName;
	}

	public static function getAuthorizeUrl() {
		$redirectUrl = HighLevel::getRedirectUrl();
		$scopes = ['contacts.readonly', 'contacts.write', 'locations/customFields.readonly', 'locations/customFields.write',
			'locations/tags.readonly', 'locations/tags.write'];

		return sprintf("https://marketplace.gohighlevel.com/oauth/chooselocation?response_type=code&redirect_uri=%s&client_id=%s&scope=%s",
			$redirectUrl, HighLevel::HIGHLEVEL_CI, implode(" ", $scopes));
	}

	private static function getBaseURL() {
		$endPoint = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_URL");
		return $endPoint ?: HighLevel::HIGHLEVEL_LIVE_URL;
	}

	public static function getAccessToken($authorizationCode) {
		$data = array(
			'client_id' => HighLevel::HIGHLEVEL_CI,
			'client_secret' => HighLevel::HIGHLEVEL_CS,
			'grant_type' => 'authorization_code',
			'code' => $authorizationCode
		);
		return HighLevel::updateToken($data);
	}

	public static function refreshToken() {
		$refreshToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . '_REFRESH_TOKEN');
		if (empty($refreshToken)) {
			return "Refresh token not found. Re-authorize with " . HighLevel::HIGHLEVEL_DISPLAY_NAME . " to get a new access token.";
		}
		$data = array(
			'client_id' => HighLevel::HIGHLEVEL_CI,
			'client_secret' => HighLevel::HIGHLEVEL_CS,
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken
		);
		return HighLevel::updateToken($data);
	}

	public static function setAccessToken($tokenResult) {
		$tokenExpiration = date_add(date_create(), date_interval_create_from_date_string($tokenResult['expires_in'] . ' seconds'));
		$preferenceArray = array(
			array('preference_code' => makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . '_ACCESS_TOKEN', 'description' => HighLevel::HIGHLEVEL_DISPLAY_NAME . ' Access Token',
				'data_type' => 'varchar', 'current_value' => $tokenResult['access_token']),
			array('preference_code' => makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . '_REFRESH_TOKEN', 'description' => HighLevel::HIGHLEVEL_DISPLAY_NAME . ' Refresh Token',
				'data_type' => 'varchar', 'current_value' => $tokenResult['refresh_token']),
			array('preference_code' => makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . '_TOKEN_EXPIRES', 'description' => HighLevel::HIGHLEVEL_DISPLAY_NAME . ' Token Expires',
				'data_type' => 'date', 'current_value' => $tokenExpiration->format('c')),
			array('preference_code' => makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . '_LOCATION_ID', 'description' => HighLevel::HIGHLEVEL_DISPLAY_NAME . ' Location ID',
				'data_type' => 'varchar', 'current_value' => $tokenResult['locationId'])
		);
		setupPreferences($preferenceArray);
	}

	function syncContact($contactRow) {
		$contactId = $contactRow['contact_id'];
		$highLevelContact = $this->getHighLevelContact($contactRow['contact_id']);
		$highLevelContactId = false;

		$contactTags = array();
		if (!empty($highLevelContact) && !empty($highLevelContact['contact'])) {
			$highLevelContactId = $highLevelContact['contact']['id'];

			// Get contact tags from HighLevel
			if (!empty($highLevelContact['contact']['tags'])) {
				$contactTags = $highLevelContact['contact']['tags'];
			}
		}
		// Remove mailing lists and contact categories tags to remove those that no longer applies
		foreach ($contactTags as $index => $tag) {
			if (startsWith($tag, "mailing_list_") || startsWith($tag, "contact_category_")) {
				unset($contactTags[$index]);
			}
		}
		// Mailing list tags
		$resultSet = executeQuery("select mailing_list_code from contact_mailing_lists join mailing_lists using (mailing_list_id) where inactive = 0"
			. " and client_id = ? and contact_id = ? and date_opted_out is null" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId'], $contactId);
		while ($row = getNextRow($resultSet)) {
			$contactTags[] = "mailing_list_" . strtolower($row['mailing_list_code']);
		}
		// Contact categories tags
		$resultSet = executeQuery("select category_code from contact_categories join categories using (category_id) where inactive = 0"
			. " and client_id = ? and contact_id = ?". ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId'], $contactId);
		while ($row = getNextRow($resultSet)) {
			$contactTags[] = "contact_category_" . strtolower($row['category_code']);
		}

		$requestBody = array(
			"firstName" => $contactRow['first_name'],
			"lastName" => $contactRow['last_name'],
			"name" => getDisplayName($contactId),
			"email" => $contactRow['email_address'],
			"phone" => Contact::getContactPhoneNumber($contactId),
			"address1" => $contactRow['address_1'] . (empty($contactRow['address_2']) ? "" : ", " . $contactRow['address_2']),
			"city" => $contactRow['city'],
			"state" => $contactRow['state'],
			"postalCode" => $contactRow['postal_code'],
			"website" => $contactRow['web_page'],
			"country" => getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']),
			"timezone" => getFieldFromId("timezone_identifier", "timezones", "timezone_id", $contactRow['timezone_id']),
			"source" => "coreFORCE",
			"tags" => array_values($contactTags)
		);

		if (empty($highLevelContactId)) {
			// For some reason HighLevel API doesn't support these fields for PUT /contacts/<contactId> yet
			$requestBody['gender'] = CustomField::getCustomFieldData($contactId, "GENDER") ?: "";
			$requestBody['companyName'] = $contactRow['business_name'];

			$requestBody['locationId'] = $this->iHighLevelLocationId;
			$syncResponse = $this->sendRequest(array("method" => "POST", "request_body" => $requestBody,
				"url" => "/contacts/upsert"));
		} else {
			$syncResponse = $this->sendRequest(array("method" => "PUT", "request_body" => $requestBody,
				"url" => "/contacts/" . $highLevelContactId));
		}

		if (!empty($syncResponse) && !empty($syncResponse['contact'])) {
			if (empty($highLevelContactId) && !empty($syncResponse['contact']['id'])) {
				executeQuery("insert ignore into contact_identifiers (contact_id, contact_identifier_type_id, identifier_value) values (?,?,?)",
					$contactId, $this->iIdentifierTypeId, $syncResponse['contact']['id']);
			}
			return $syncResponse;
		}
		return false;
	}

	private function getHighLevelContact($contactId) {
		$highLevelContactId = $this->getHighLevelContactId($contactId);
		if (!empty($highLevelContactId)) {
			return $this->sendRequest(array("url" => "/contacts/" . $highLevelContactId));
		}
		return false;
	}

	private function sendRequest($parameters) {
		$curlHandle = curl_init();

		$configOptions = array();
		$authorizationHeader = "Authorization: Bearer " . $this->iAccessToken;
		$headers = array("Content-Type: application/json", "Accept: application/json", "Version: 2021-07-28", $authorizationHeader);

		$configOptions[CURLOPT_HTTPHEADER] = $headers;
		$configOptions[CURLOPT_URL] = trim($this->iBaseUrl,"/") . "/" . ltrim($parameters['url'],"/");
		$configOptions[CURLOPT_RETURNTRANSFER] = 1;

		$method = "GET";
		if (array_key_exists('method', $parameters)) {
			$method = strtolower($parameters['method']);
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
		if (!empty($parameters['request_body'])) {
			$configOptions[CURLOPT_POSTFIELDS] = jsonEncode($parameters['request_body'], JSON_UNESCAPED_SLASHES);
		}
		foreach ($configOptions as $optionName => $optionValue) {
			curl_setopt($curlHandle, $optionName, $optionValue);
		}

		$response = curl_exec($curlHandle);
		$httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
		$curlError = curl_error($curlHandle);

		if ($this->iLoggingLevel > 2) {
			$logMessage = HighLevel::HIGHLEVEL_DISPLAY_NAME . " Request: " . $configOptions[CURLOPT_URL] . "\n"
				. "\nResponse: " . $response . "\nHTTP status: " . $httpCode;
			if (!empty($curlError)) {
				$logMessage .= "\nError: " . $curlError;
			}
			if (!empty($configOptions[CURLOPT_POSTFIELDS])) {
				$logMessage .= "\nRequest body: " . $configOptions[CURLOPT_POSTFIELDS];
			}
			addDebugLog($logMessage);
		}
		if (empty($response)) {
			if ($httpCode == 403 || $httpCode == 404) {
				$this->iErrorMessage = "Unauthorized: incorrect API key";
			} else {
				$this->iErrorMessage = $curlError ?: "Unknown API error";
			}
			return false;
		}
		try {
			$responseArray = json_decode($response, true);
		} catch (Exception $e) {
			$this->iErrorMessage = $e->getMessage();
			$responseArray = array();
		}
		if ($httpCode >= 400) {
			$this->iErrorMessage = $responseArray['message'];
			$this->iErrorMessage = $this->iErrorMessage ?: $responseArray['errors']['title'];
			$this->iErrorMessage = $this->iErrorMessage ?: $responseArray['errors'][0]['title'];
			if ($this->iLoggingLevel > 0) {
				$logMessage = HighLevel::HIGHLEVEL_DISPLAY_NAME . " Error: " . $this->iErrorMessage . "\nHTTP method: " . $method . "\nResponse: " . $response;
				if (!empty($configOptions[CURLOPT_POSTFIELDS])) {
					$logMessage .= "\nRequest Body:" . $configOptions[CURLOPT_POSTFIELDS];
				}
				addDebugLog($logMessage);
			}
			return false;
		}
		return $responseArray;
	}

	private static function updateToken($data) {
		$curlHandle = curl_init();
		curl_setopt_array($curlHandle, array(
			CURLOPT_URL => HighLevel::getBaseURL() . "/oauth/token",
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => http_build_query($data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/x-www-form-urlencoded'
			),
			CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
			CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
		));
		$response = curl_exec($curlHandle);
		$info = curl_getinfo($curlHandle);
		if ($response === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
			$error = curl_error($curlHandle) . ":" . jsonEncode($info);
			addDebugLog(HighLevel::HIGHLEVEL_DISPLAY_NAME . " Error: Creating/refreshing Token. Response: " . $error);
			return $error;
		}
		curl_close($curlHandle);

		try {
			$tokenResult = json_decode($response, true);
			HighLevel::setAccessToken($tokenResult);
			return $tokenResult['access_token'];
		} catch (Exception $e) {
			addDebugLog(HighLevel::HIGHLEVEL_DISPLAY_NAME . " Error: Creating/refreshing token. Response: " . $e->getMessage());
			return $e->getMessage();
		}
	}

	function addResultLog($resultLine) {
		$timeNow = getMilliseconds();
		$this->iLastLogTime = $this->iLastLogTime ?: $timeNow;
		$timeElapsed = getTimeElapsed($this->iLastLogTime, $timeNow);
		$this->iLastLogTime = $timeNow;
		if ($GLOBALS['gDevelopmentServer'] || $this->iLoggingLevel > 0) {
			$currentMemory = memory_get_usage() / 1000;
			$memoryChange = $currentMemory - $this->iLastMemory;
			$this->iLastMemory = $currentMemory;
			addDebugLog($resultLine . "\nMemory Used: " . intval($currentMemory) . "KB. Change: " . intval($memoryChange) . "KB. Time Elapsed: " . $timeElapsed);
		}
	}

	function syncContacts() {
		if (empty($this->iAccessToken) || empty($this->iHighLevelLocationId)) {
			$this->iErrorMessage = HighLevel::HIGHLEVEL_DISPLAY_NAME . " Access Token is not set. Do this in Orders->Setup.";
			return false;
		}

		$this->addResultLog("Sync contacts started for client: " . $GLOBALS['gClientId']);

		$addCount = 0;
		$updateCount = 0;
		$errorCount = 0;
		$skippedCount = 0;

		$identifierTypeId = $this->iIdentifierTypeId;
		$resultSet = executeQuery("select contacts.*, (select identifier_value from contact_identifiers"
			. " where contact_identifier_type_id = ? and contact_id = contacts.contact_id limit 1) identifier_value"
			. " from contacts where client_id = ? and deleted = 0"
			. " and contact_id not in (select contact_id from locations)"
			. " and contact_id not in (select contact_id from federal_firearms_licensees)"
			. " and contact_id not in (select contact_id from product_manufacturers)"
			. " and contact_id not in (select contact_id from users where superuser_flag = 1)"
			. " and contact_id not in (select contact_id from clients)"
			. " order by identifier_value desc", $identifierTypeId, $GLOBALS['gClientId']);

		while ($row = getNextRow($resultSet)) {
			$highLevelIdentifier = $row['identifier_value'];

			if (!filter_var($row['email_address'], FILTER_VALIDATE_EMAIL)) {
				if ($this->iLoggingLevel > 1) {
					addDebugLog(HighLevel::HIGHLEVEL_DISPLAY_NAME . " contact sync skipped due to invalid email: " . $row['email_address']);
				}
				$skippedCount++;
				continue;
			}
			$syncResponse = $this->syncContact($row);
			if (!empty($syncResponse)) {
				if (empty($highLevelIdentifier)) {
					$addCount++;
				} else {
					$updateCount++;
				}
			} else {
				$errorCount++;
			}
		}

		$returnArray = array();
		$returnArray['add_count'] = $addCount;
		$returnArray['update_count'] = $updateCount;
		$returnArray['error_count'] = $errorCount;
		$returnArray['skipped_count'] = $skippedCount;

		$this->addResultLog("Sync contacts completed for client: " . $GLOBALS['gClientId']
			. "\nResult: " . json_encode($returnArray));
		return $returnArray;
	}

	private function getHighLevelContactId($contactId) {
		return getFieldFromId("identifier_value", "contact_identifiers",
			"contact_id", $contactId, "contact_identifier_type_id = " . $this->iIdentifierTypeId);
	}

	public function addContactTag($contactId, $tagName) {
		$highLevelContactId = $this->getHighLevelContactId($contactId);
		$tagRecord = array("tags" => [$tagName]);

		// If there's no existing cart_abandoned tag for the HighLevel location, skip tagging the contact to avoid
		// large queue if a workflow doesn't exist yet
		if ($tagName == "cart_abandoned") {
			$parameters = array("url" => "/locations/" . $this->iHighLevelLocationId . "/tags");
			$response = $this->sendRequest($parameters);
			$locationTags = $response['tags'];
			$tagInArray = in_array("cart_abandoned", array_column($locationTags, 'name'));
			if (!$tagInArray) {
				$this->iErrorMessage = "cart_abandoned tag doesn't exist in coreILLA. Skip tagging";
				$this->addResultLog($this->iErrorMessage);
				return false;
			}
		}

		$parameters = array(
			"method" => "POST",
			"url" => "/contacts/" . $highLevelContactId . "/tags",
			"request_body" => $tagRecord
		);
		return $this->sendRequest($parameters);
	}

	public function removeContactTag($contactId, $tagName) {
		$highLevelContactId = $this->getHighLevelContactId($contactId);
		$tagRecord = array("tags" => [$tagName]);
		$parameters = array(
			"method" => "DELETE",
			"url" => "/contacts/" . $highLevelContactId . "/tags",
			"request_body" => $tagRecord
		);
		return $this->sendRequest($parameters);
	}

	public function getOrCreateCustomField($fieldKey, $fieldName) {
		$parameters = array("url" => "/locations/" . $this->iHighLevelLocationId . "/customFields");
		$response = $this->sendRequest($parameters);
		$customFields = $response['customFields'];
		$index = array_search('contact.' . $fieldKey, array_column($customFields, 'fieldKey'));
		if ($index !== false) {
			return $customFields[$index]['id'];
		} else {
			$parameters = array(
				"method" => "POST",
				"url" => "/locations/" . $this->iHighLevelLocationId . "/customFields",
				"request_body" => array(
					"fieldKey" => $fieldKey,
					"dataType" => "LARGE_TEXT",
					"name" => $fieldName)
			);
			$response = $this->sendRequest($parameters);
			return $response['customField']['id'];
		}
	}

	public function logAbandonedCart($shoppingCartId, $substitutions) {
		$shoppingCartRow = getRowFromId("shopping_carts", "shopping_cart_id", $shoppingCartId);
		$contactId = $shoppingCartRow['contact_id'];
		$contactRow = Contact::getContact($contactId);

		if ($shoppingCartRow['shopping_cart_code'] !== "RETAIL") {
			// Skip shopping carts that are not RETAIL
			$this->iErrorMessage = "Shopping cart (" . $shoppingCartId . ") is not of retail type.";
			return false;
		}

		if (!filter_var($contactRow['email_address'], FILTER_VALIDATE_EMAIL)) {
			$this->iErrorMessage = "Invalid email (" . $contactRow['email_address'] . ").";
			return false;
		}

		// Sync Contact
		$syncResponse = $this->syncContact($contactRow);
		if (empty($syncResponse) || empty($syncResponse['contact'])) {
			$this->iErrorMessage = "Sync contact failed when logging abandoned cart.";
			return false;
		}

		// Get or create custom field
		$orderItemsCustomFieldId = $this->getOrCreateCustomField("order_items", "Order Items");
		$orderItemsTableCustomFieldId = $this->getOrCreateCustomField("order_items_table", "Order Items Table");

		$requestBody = array(
			"customFields" => array(
				array("id" => $orderItemsCustomFieldId, "value" => $substitutions['order_items']),
				array("id" => $orderItemsTableCustomFieldId, "value" => $substitutions['order_items_table'])));

		$parameters = array(
			"method" => "PUT",
			"url" => "/contacts/" . $syncResponse['contact']['id'],
			"request_body" => $requestBody
		);
		// Update custom fields and add contact tag
		return $this->sendRequest($parameters) &&
			$this->addContactTag($shoppingCartRow['contact_id'], "cart_abandoned");
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

}
