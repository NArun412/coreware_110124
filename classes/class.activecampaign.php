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

class ActiveCampaign {

	const ACTIVE_CAMPAIGN_LIVE_URL = "https://%client_code%.api-us1.com";
	const VALID_URL_REGEX = "/https\:\/\/[a-zA-Z0-9-_]*\.api-us1\.com/";
	const ACITVE_CAMPAIGN_TEST_URL = "https://ezraweinstein.api-us1.com";
	const VALID_EMAIL_REGEX = "/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/";
	private $iApiKey;
	private $iUseUrl;
	private $iErrorMessage;
	private $iPreferences;
	private $iConnectionId;
	private $iLoggingLevel;
    private $iLogLength;
    private $iBulkOperation = false;
    private $iLogEntry;
    private $iLastLogTime;
    private $iLastMemory;

	function __construct($apiKey, $testMode = false) {
		if ($testMode || $GLOBALS['gDevelopmentServer']) {
			$endPoint = getPreference("ACTIVECAMPAIGN_TEST_URL");
			$this->iUseUrl = $endPoint ?: self::ACITVE_CAMPAIGN_TEST_URL;
		} else {
			$endPoint = getPreference("ACTIVECAMPAIGN_URL");
			$this->iUseUrl = $endPoint ?: str_replace("%client_code%", $GLOBALS['gClientRow']['client_code'], self::ACTIVE_CAMPAIGN_LIVE_URL);
		}
		$this->iApiKey = $apiKey;
		$this->iPreferences = array(
			'donation_days' => false,
			'product_days' => false,
			'include_all' => false,
			'sync_deletes' => false
		);
		$this->iLoggingLevel = getPreference("LOG_ACTIVECAMPAIGN");
        $this->iLogLength = getPreference("ACTIVECAMPAIGN_LOG_LENGTH") ?: 500;
    }

	private function postApi($method, $parameters = array(), $put = false) {
		$header = array('Content-Type: application/json', 'Accept: application/json', "Api-Token: " . $this->iApiKey);
		$hostUrl = $this->iUseUrl . "/" . ltrim($method, "/");
		$postParameters = jsonEncode($parameters);
		$ch = curl_init();
		if ($put) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		} else {
			curl_setopt($ch, CURLOPT_POST, 1);
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
        if ($this->iLoggingLevel > 1 && !$this->iBulkOperation) {
            addDebugLog("ActiveCampaign request: " . ($put ? "PUT " : "POST ") . $hostUrl
                . "\nActiveCampaign Headers: " . implode(";",$header)
                . "\nActiveCampaign Data: " . (strlen($postParameters) > $this->iLogLength ? substr($postParameters,0,$this->iLogLength) . "..." : $postParameters)
                . "\nActiveCampaign Result: " . $response
                . (empty($err) ? "" : "\nActiveCampaign Error: " . $err)
                . "\nActiveCampaign HTTP Status: " . $httpCode);
        }
        if (empty($response)) {
			if ($httpCode == 403 || $httpCode == 404) {
				$this->iErrorMessage = "Unauthorized: incorrect API key";
			} elseif (!preg_match(self::VALID_URL_REGEX, $this->iUseUrl)) {
				$this->iErrorMessage = "Invalid ActiveCampaign URL: " . $this->iUseUrl;
			} else {
				$this->iErrorMessage = $err ?: "Unknown API error";
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
				addDebugLog("ActiveCampaign error: " . $method . ":" . $response . ":" . $postParameters);
			}
			return false;
		}
		return $responseArray;
	}

	private function getApi($method, $delete = false, $replyIsJson = true) {
		$header = array('Content-Type: application/json', 'Accept: application/json', "Api-Token: " . $this->iApiKey);
		$hostUrl = $this->iUseUrl . "/" . ltrim($method, "/");
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		if ($delete) {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
        if ($this->iLoggingLevel > 1 && !$this->iBulkOperation) {
            addDebugLog("ActiveCampaign request: ". ($delete ? "DELETE " : "GET ") . $hostUrl
                . "\nActiveCampaign Headers: " . implode(";",$header)
                . "\nActiveCampaign Result: " . $response
                . (empty($err) ? "" : "\nActiveCampaign Error: " . $err)
                . "\nActiveCampaign HTTP Status: " . $httpCode);
        }

        if (empty($response)) {
			if ($httpCode == 403 || $httpCode == 404) {
				$this->iErrorMessage = "Unauthorized: incorrect API key";
			} elseif (!preg_match(self::VALID_URL_REGEX, $this->iUseUrl)) {
				$this->iErrorMessage = "Invalid ActiveCampaign URL: " . $this->iUseUrl;
			} else {
				$this->iErrorMessage = $err ?: "Unknown API error";
			}
			return false;
		}
		if ($replyIsJson) {
			try {
				$responseArray = json_decode($response, true);
			} catch (Exception $e) {
				$this->iErrorMessage = $e->getMessage();
				$responseArray = array();
			}
		} else {
			$responseArray = $response;
		}
		if ($httpCode >= 400) {
			$this->iErrorMessage = $responseArray['message'];
			$this->iErrorMessage = $this->iErrorMessage ?: $responseArray['errors']['title'];
			$this->iErrorMessage = $this->iErrorMessage ?: $responseArray['errors'][0]['title'];
			if ($this->iLoggingLevel > 0) {
				addDebugLog("ActiveCampaign error: " . $method . ":" . $response);
			}
			return false;
		}
		return $responseArray;
	}

	private function postEvent($parameters = array()) {
		$header = array('Content-Type: application/x-www-form-urlencoded');
		$hostUrl = "https://trackcmp.net/event";
		$postParameters = http_build_query($parameters);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
		curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
        if ($this->iLoggingLevel > 1 && !$this->iBulkOperation) {
            addDebugLog("ActiveCampaign request: " . $hostUrl
                . "\nActiveCampaign Data: " . (strlen($postParameters) > $this->iLogLength ? substr($postParameters,0,$this->iLogLength) . "..." : $postParameters)
                . "\nActiveCampaign Result: " . $response
                . (empty($err) ? "" : "\nActiveCampaign Error: " . $err)
                . "\nActiveCampaign HTTP Status: " . $httpCode);
        }
        if (empty($response)) {
			if ($httpCode == 403 || $httpCode == 404) {
				$this->iErrorMessage = "Unauthorized: incorrect Event key";
			} else {
				$this->iErrorMessage = $err ?: "Unknown API error";
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
				addDebugLog("ActiveCampaign error: Track Event:" . $response . ":" . $postParameters);
			}
			return false;
		}
		return $responseArray;
	}


	private function getConnectionId() {
		if (!empty($this->iConnectionId)) {
			return $this->iConnectionId;
		}
		$result = $this->getApi("api/3/connections");
		if (is_array($result['connections'])) {
			foreach ($result['connections'] as $thisConnection) {
				if ($thisConnection['externalid'] == $GLOBALS['gClientRow']['client_code']) {
					$this->iConnectionId = $thisConnection['id'];
					return $this->iConnectionId;
				}
			}
		}
		$postParameters = array("connection" => array(
			"service" => "coreFORCE",
			"externalid" => $GLOBALS['gClientRow']['client_code'],
			"name" => $GLOBALS['gClientRow']['business_name'],
			"logoUrl" => getDomainName() . "/getimage.php?code=HEADER_LOGO",
			"linkUrl" => getDomainName() . "/admin-menu"));
		$result = $this->postApi("api/3/connections", $postParameters);
		$this->iConnectionId = $result['connection']['id'];
		return $this->iConnectionId;
	}

	function checkDeepDataConnection() {
		$result = $this->getConnectionId(); // make sure connection exists
		return !empty($result);
	}

	function getLists($getMemberCount = false) {
        $this->iBulkOperation = true;
		$returnArray = array();
		$validCategories = array("mailing_lists", "categories", "designations", "designation_groups", "forms", "user_groups", "products", "product_categories");
		$result = $this->getApi("api/3/lists");
		if (!$result) {
			return false;
		}
		$remoteGroups = array();
		$groupDisplay = array();

		foreach ($result['lists'] as $listInfo) {
			$title = $listInfo['name'];
			foreach ($validCategories as $thisCategory) {
				if (substr(strtolower(str_replace(" ", "_", $title)), 0, strlen($thisCategory)) == $thisCategory) {
					$subTitle = trim(substr($title, strlen($thisCategory)), " -:/");
					$corewareId = "";
					switch ($thisCategory) {
						case "mailing_lists":
							$corewareId = getFieldFromId("mailing_list_id", "mailing_lists", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Mailing List '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE Mailing List '" . $subTitle . "'");
							}
							break;
						case "categories":
							$corewareId = getFieldFromId("category_id", "categories", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Contact Category '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE Contact Category '" . $subTitle . "'");
							}
							break;
						case "designations":
							$corewareId = getFieldFromId("designation_id", "designations", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Donors who gave toward '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE designation '" . $subTitle . "'");
							}
							break;
						case "designation_groups":
							$corewareId = getFieldFromId("designation_group_id", "designation_groups", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Donors who gave toward designations in the designation group '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE designation group '" . $subTitle . "'");
							}
							break;
						case "forms":
							$corewareId = getFieldFromId("form_definition_id", "form_definitions", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Contacts who filled out the form '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE form '" . $subTitle . "'");
							}
							break;
						case "user_groups":
							$corewareId = getFieldFromId("user_group_id", "user_groups", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Users in the User Group '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE User Group '" . $subTitle . "'");
							}
							break;
						case "products":
							$corewareId = getFieldFromId("product_id", "products", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Contacts who purchased '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE product '" . $subTitle . "'");
							}
							break;
						case "product_categories":
							$corewareId = getFieldFromId("product_category_id", "product_categories", "description", $subTitle);
							if (!empty($corewareId)) {
								$listDisplay = array("name" => $listInfo['name'], "description" => "Contacts who purchase products in product category '" . $subTitle . "'");
							} else {
								$listDisplay = array("name" => $listInfo['name'], "description" => "No matching coreFORCE product category '" . $subTitle . "'");
							}
							break;
						default:
							$listDisplay = array("name" => $listInfo['name'], "description" => "No matching set of contacts in coreFORCE");
							break;
					}
					if ($getMemberCount) {
						$listResult = $this->getApi("api/3/contacts?listid=" . $listInfo['id']);
						$listDisplay['members'] = $listResult['meta']['total'] ?: 0;
					}
					$groupDisplay[] = $listDisplay;

					if (!empty($corewareId)) {
						$remoteGroups[] = array("category" => $thisCategory, "id" => $listInfo['id'], "coreware_id" => $corewareId);
					}
				}
			}
		}
		$returnArray['remote_groups'] = $remoteGroups;
		$returnArray['group_display'] = $groupDisplay;

		return $returnArray;
	}

    function addResultLog($resultLine = "") {
        $timeNow = getMilliseconds();
        $this->iLastLogTime = $this->iLastLogTime ?: $timeNow;
        $resultLine .= " (" . getTimeElapsed($this->iLastLogTime,$timeNow) . ")";
        $this->iLastLogTime = $timeNow;
        if ($GLOBALS['gDevelopmentServer'] || $this->iLoggingLevel > 0) {
            $currentMemory = memory_get_usage() / 1000;
            $memoryChange = $currentMemory - $this->iLastMemory;
            $this->iLastMemory = $currentMemory;
            addDebugLog($resultLine . " Memory Used: " . number_format($currentMemory, 0, "", ",")
                . " KB Change: " . number_format($memoryChange, 0, "", ",") . " KB");
        }
        $this->iLogEntry .= (empty($this->iLogEntry) ? "" : "\n") . $resultLine;
    }

    function getResultLog() {
        return $this->iLogEntry;
    }

	function syncContacts($valuesArray = array()) {
        $this->iBulkOperation = true;
        $apiKey = $this->iApiKey;
		if (empty($apiKey)) {
			$this->iErrorMessage = "API Key is not set up. Do this in Client Preferences.";
			return false;
		}
		$this->iPreferences['donation_days'] = array_key_exists('donation_days', $valuesArray) ? $valuesArray['donation_days'] : $this->iPreferences['donation_days'];
		$this->iPreferences['product_days'] = array_key_exists('product_days', $valuesArray) ? $valuesArray['product_days'] : $this->iPreferences['product_days'];
		$this->iPreferences['include_all'] = array_key_exists('include_all', $valuesArray) ? $valuesArray['include_all'] : $this->iPreferences['include_all'];
		$this->iPreferences['sync_deletes'] = array_key_exists('sync_deletes', $valuesArray) ? $valuesArray['sync_deletes'] : $this->iPreferences['sync_deletes'];

		$contactsArray = array();

        $this->addResultLog("Sync Contacts started");
		$listResult = $this->getLists();
		if (!$listResult) {
            $this->addResultLog("Get Lists failed");
			return false;
		}
		$remoteGroups = $listResult['remote_groups'];
		$groupDisplay = $listResult['group_display'];
        $this->addResultLog("Lists found: " . count($remoteGroups));

		$skipCount = 0;
		$blankCount = 0;
		$invalidCount = 0;
		$deleteCount = 0;
		$errorCount = 0;
		$updateCount = 0;
		$addCount = 0;
		$activeCampaignIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code",
			"ACTIVECAMPAIGN_ID");
		if (empty($activeCampaignIdentifierTypeId)) {
			$resultSet = executeQuery("insert into contact_identifier_types (client_id, contact_identifier_type_code, description, internal_use_only) values (?,?,?,?)",
				$GLOBALS['gClientId'], "ACTIVECAMPAIGN_ID", "ActiveCampaign Contact Identifier", 1);
			$activeCampaignIdentifierTypeId = $resultSet['insert_id'];
		}

		$includeFflContacts = !empty(getPreference("SHOW_FFL_IN_CONTACTS"));
		$whereStatement = "client_id = ? and deleted = 0";
		if (!$includeFflContacts) {
			$whereStatement .= " and contact_id not in (select contact_id from federal_firearms_licensees)";
		}
		if (empty(getPreference("SHOW_MANUFACTURERS_IN_CONTACTS"))) {
			$whereStatement .= " and contact_id not in (select contact_id from product_manufacturers)";
		}

		$resultSet = executeQuery("select contact_id,first_name,last_name,email_address,(select identifier_value from contact_identifiers "
			. " where contact_identifier_type_id = ? and contact_id = contacts.contact_id limit 1) identifier_value from contacts where " . $whereStatement . " order by identifier_value desc",
			$activeCampaignIdentifierTypeId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$activeCampaignIdentifier = $row['identifier_value'];
			if (array_key_exists($activeCampaignIdentifier, $contactsArray)) {
				$skipCount++;
				continue;
			}
			if (empty($row['email_address']) && empty($activeCampaignIdentifier)) {
				$blankCount++;
				continue;
			}
			if (!preg_match(self::VALID_EMAIL_REGEX, $row['email_address'])) {
				if (empty($activeCampaignIdentifier)) {
					$invalidCount++;
					continue;
				} else {
					$row['email_address'] = "";
				}
			}
			if ($includeFflContacts) {
				$fflContactId = getFieldFromId("contact_id", "contacts", "email_address", $row['email_address'],
					"contact_id in (select contact_id from federal_firearms_licensees)");
				if ($fflContactId == $row['contact_id']) {
					$nonFflContactId = getFieldFromId("contact_id", "contacts", "email_address", $row['email_address'],
						"contact_id not in (select contact_id from federal_firearms_licensees)");
					if (!empty($nonFflContactId)) {
						$skipCount++;
						continue;
					}
				}
			}
			$interestCount = 0;
			$existingInterests = array();
			foreach ($remoteGroups as $groupInfo) {
				switch ($groupInfo['category']) {
					case "mailing_lists":
						$corewareId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "mailing_list_id",
							$groupInfo['coreware_id'], "contact_id = ? and date_opted_out is null", $row['contact_id']);
						break;
					case "categories":
						$corewareId = getFieldFromId("contact_category_id", "contact_categories", "category_id",
							$groupInfo['coreware_id'], "contact_id = ?", $row['contact_id']);
						break;
					case "designations":
						$corewareId = getFieldFromId("donation_id", "donations", "designation_id",
							$groupInfo['coreware_id'], "contact_id = ? and associated_donation_id is null" .
							(empty($this->iPreferences['donation_days']) || !is_numeric($this->iPreferences['donation_days']) ? "" : " and donation_date > date_sub(now(),interval " .
								$this->iPreferences['donation_days'] . " day)"), $row['contact_id']);
						break;
					case "designation_groups":
						$corewareId = getFieldFromId("donation_id", "donations", "contact_id", $row['contact_id'],
							"designation_id in (select designation_id from designation_group_links where designation_group_id = ?) and associated_donation_id is null" .
							(empty($this->iPreferences['donation_days']) || !is_numeric($this->iPreferences['donation_days']) ? "" : " and donation_date > date_sub(now(),interval " .
								$this->iPreferences['donation_days'] . " day)"), $groupInfo['coreware_id']);
						break;
					case "forms":
						$corewareId = getFieldFromId("form_id", "forms", "form_definition_id",
							$groupInfo['coreware_id'], "contact_id = ?", $row['contact_id']);
						break;
					case "user_groups":
						$corewareId = getFieldFromId("user_group_member_id", "user_group_members", "user_group_id",
							$groupInfo['coreware_id'], "user_id in (select user_id from users where contact_id = ?)", $row['contact_id']);
						break;
					case "products":
						$corewareId = getFieldFromId("order_item_id", "order_items", "product_id", $groupInfo['coreware_id'], "order_id in (select order_id from orders where " .
							(empty($this->iPreferences['product_days']) || !is_numeric($this->iPreferences['product_days']) ? "" : "order_time > date_sub(now(), interval " .
								$this->iPreferences['product_days'] . " day) and ") . "contact_id = ?)", $row['contact_id']);
						if (empty($corewareId) && !empty($fflContactId)) {
							$corewareId = getFieldFromId("order_item_id", "order_items", "product_id", $groupInfo['coreware_id'], "order_id in (select order_id from orders where " .
								(empty($this->iPreferences['product_days']) || !is_numeric($this->iPreferences['product_days']) ? "" : "order_time > date_sub(now(), interval " .
									$this->iPreferences['product_days'] . " day) and ") . "contact_id = ?)", $fflContactId);
						}
						break;
					case "product_categories":
						$corewareId = getFieldFromId("order_id", "orders", "contact_id", $row['contact_id'],
							(empty($this->iPreferences['product_days']) || !is_numeric($this->iPreferences['product_days']) ? "" : "order_time > date_sub(now(), interval " .
								$this->iPreferences['product_days'] . " day) and ") . "order_id in (select order_id from order_items where product_id in (select product_id from product_category_links where product_category_id = ?))",
							$groupInfo['coreware_id']);
						if (empty($corewareId) && !empty($fflContactId)) {
							$corewareId = getFieldFromId("order_id", "orders", "contact_id", $fflContactId,
								(empty($this->iPreferences['product_days']) || !is_numeric($this->iPreferences['product_days']) ? "" : "order_time > date_sub(now(), interval " .
									$this->iPreferences['product_days'] . " day) and ") . "order_id in (select order_id from order_items where product_id in (select product_id from product_category_links where product_category_id = ?))",
								$groupInfo['coreware_id']);
						}
						break;
				}
				$existingInterests[$groupInfo['id']] = (empty($corewareId) ? false : true);
				if (!empty($corewareId)) {
					$interestCount++;
				}
			}
			$row['existing_interests'] = $existingInterests;
			if ($interestCount > 0 || $this->iPreferences['include_all'] == "1") {
				$contactsArray[$row['identifier_value'] ?: $row['email_address']] = $row;
			} else if (!empty($row['identifier_value'])) {
				executeQuery("delete from contact_identifiers where contact_id = ? and contact_identifier_type_id = ?", $row['contact_id'], $activeCampaignIdentifierTypeId);
			}
		}
        $this->addResultLog(count($contactsArray) . " contacts to sync.");

		$remoteMembers = array();
		// get all ActiveCampaign contacts (including those not in any list)
		$offset = 0;
		$limit = 100;
        // don't try to pull more contacts than the client has in the system
        $contactTotal = $maxContacts = getFieldFromId("count(*)", "contacts", "client_id", $GLOBALS['gClientId']);
		do {
			$remoteContacts = $this->getApi(sprintf("api/3/contacts?limit=%s&offset=%s", $limit, $offset));
            if(!empty($remoteContacts['meta']['total'])) {
                $contactTotal = min($remoteContacts['meta']['total'], $maxContacts);
            }

			if (is_array($remoteContacts['contacts'])) {
				foreach ($remoteContacts['contacts'] as $remoteMember) {
					if (!array_key_exists($remoteMember['id'], $remoteMembers)) {
						$remoteMembers[$remoteMember['id']] = $remoteMember;
					}
				}
			}
			$offset += $limit;
            if($offset % 1000 == 0) {
                $this->addResultLog($offset . " contacts retrieved from ActiveCampaign");
            }
		} while ($offset < $contactTotal);
        $this->addResultLog(count($remoteMembers) . " total contacts found on ActiveCampaign");

		// add ActiveCampaign contacts to lists
		foreach ($remoteGroups as $thisGroup) {
			$offset = 0;
			$limit = 100;
			do {
				$remoteListMembers = $this->getApi(sprintf("api/3/contacts?listid=%s&limit=%s&offset=%s", $thisGroup['id'], $limit, $offset));
				$listTotal = $remoteListMembers['meta']['total'];

				if (is_array($remoteListMembers['contacts'])) {
					foreach ($remoteListMembers['contacts'] as $remoteMember) {
						if (!array_key_exists($remoteMember['id'], $remoteMembers)) {
							$remoteMember['list_ids'] = array($thisGroup['id']);
							$remoteMembers[$remoteMember['id']] = $remoteMember;
						} else {
							$remoteMembers[$remoteMember['id']]['list_ids'][] = $thisGroup['id'];
						}
					}
				}
				$offset += $limit;
                if($offset % 1000 == 0) {
                    $this->addResultLog($offset . " list members retrieved from ActiveCampaign");
                }
            } while ($offset < $listTotal);
		}
        $this->addResultLog("Existing list members retrieved");

		$operationId = 0;
		$batch = array();
		$remoteIdentifiers = array();
		foreach ($remoteMembers as $thisMember) {
			$thisContact = $contactsArray[$thisMember['id']];
			if (empty($thisContact)) {
				$thisContact = $contactsArray[$thisMember['email']];
				if (!empty($thisContact)) {
					$thisContact['identifier_value'] = $thisMember['id'];
					executeQuery("insert ignore into contact_identifiers (contact_id, contact_identifier_type_id, identifier_value) values (?,?,?)",
						$thisContact['contact_id'], $activeCampaignIdentifierTypeId, $thisMember['id']);
				}
			}
			if (empty($thisContact) || empty($thisContact['email_address'])) {
				if (!empty($this->iPreferences['sync_deletes'])) {
					$this->getApi("api/3/contacts/" . $thisMember['contact'], true);
					$deleteCount++;
					if (!empty($thisContact['identifier_value'])) {
						executeQuery("delete from contact_identifiers where contact_id = ? and contact_identifier_type_id = ?", $row['contact_id'], $activeCampaignIdentifierTypeId);
					}
				}
				continue;
			}
			$remoteIdentifiers[$thisMember['id']] = true;

			$remoteInterests = array();
			$existingInterests = $thisContact['existing_interests'];
			if (is_array($thisMember['list_ids'])) {
				foreach ($remoteGroups as $groupInfo) {
					$remoteInterests[$groupInfo['id']] = in_array($groupInfo['id'], $thisMember['list_ids']);
				}
			}
			if (strtolower($thisMember['email']) != strtolower($thisContact['email_address'])) {
				$result = $this->postApi("api/3/contacts/" . $thisMember['id'], array("contact" => array("email" => $thisContact['email_address'],
					"firstName" => $thisContact['first_name'], "lastName" => $thisContact['last_name'])), true);
				if (empty($result)) {
					$errorCount++;
				} else {
					$updateCount++;
				}
			} else if ($thisMember['firstName'] != $thisContact['first_name'] || $thisMember['lastName'] != $thisContact['last_name']
				|| jsonEncode($remoteInterests) != jsonEncode($existingInterests)) {
				$operationId++;
				if ($operationId % 250 == 0) {
					$result = $this->postApi("api/3/import/bulk_import", array("contacts" => $batch));
					if ($this->iLoggingLevel > 0) {
						addDebugLog("ActiveCampaign update contacts result: " . jsonEncode($result));
					}
					$batch = array();
				}
				$updateArray = array("email" => strtolower($thisContact['email_address']),
					"first_name" => $thisContact['first_name'],
					"last_name" => $thisContact['last_name'], "subscribe" => array(), "unsubscribe" => array());
				foreach ($existingInterests as $listId => $subscribe) {
					if ($subscribe && !$remoteInterests[$listId]) {
						$updateArray['subscribe'][] = array("listid" => $listId);
					} elseif (!$subscribe && $remoteInterests[$listId]) {
						$updateArray['unsubscribe'][] = array("listid" => $listId);
					}
				}

				$batch[] = $updateArray;
				$updateCount++;
			}
		}
        $this->addResultLog("Existing members updated");

		foreach ($contactsArray as $thisContact) {
			if (empty($thisContact['email_address'])) {
				continue;
			}
			if (empty($thisContact['identifier_value'])) {
				$result = $this->postApi("api/3/contacts", array("contact" => array("email" => strtolower($thisContact['email_address']),
					"first_name" => $thisContact['first_name'],
					"last_name" => $thisContact['last_name'])));
				if (empty($result)) {
					$errorCount++;
					continue;
				} else {
					$thisContact['identifier_value'] = $result['contact']['id'];
					executeQuery("insert ignore into contact_identifiers (contact_id, contact_identifier_type_id, identifier_value) values (?,?,?)",
						$thisContact['contact_id'], $activeCampaignIdentifierTypeId, $thisContact['identifier_value']);
				}
			}
			if (!array_key_exists($thisContact['identifier_value'], $remoteIdentifiers)) {
				$operationId++;
				if ($operationId % 250 == 0) {
					$result = $this->postApi("api/3/import/bulk_import", array("contacts" => $batch));
					if ($this->iLoggingLevel > 0) {
						addDebugLog("ActiveCampaign add contacts result: " . jsonEncode($result));
					}
					$batch = array();
				}
				$existingInterests = $thisContact['existing_interests'];
				$updateArray = array("email" => strtolower($thisContact['email_address']),
					"first_name" => $thisContact['first_name'],
					"last_name" => $thisContact['last_name'], "subscribe" => array(), "unsubscribe" => array());
				foreach ($existingInterests as $listId => $subscribe) {
					if ($subscribe) {
						$updateArray['subscribe'][] = array("listid" => $listId);
					}
				}
				$batch[] = $updateArray;
				$addCount++;
			}
		}
		if (!empty($batch)) {
			$result = $this->postApi("api/3/import/bulk_import", array("contacts" => $batch));
			if ($this->iLoggingLevel > 0) {
				addDebugLog("ActiveCampaign add contacts result: " . jsonEncode($result));
			}
		}
        $this->addResultLog("New contacts added to ActiveCampaign");

		$returnArray['groups'] = $groupDisplay;
		$returnArray['skip_count'] = $skipCount;
		$returnArray['blank_count'] = $blankCount;
		$returnArray['invalid_count'] = $invalidCount;
		$returnArray['delete_count'] = $deleteCount;
		$returnArray['error_count'] = $errorCount;
		$returnArray['update_count'] = $updateCount;
		$returnArray['add_count'] = $addCount;
		return $returnArray;
	}

	private function getActiveCampaignContactId($contactRow) {
		$contactId = $contactRow['contact_id'];
		if (!preg_match(self::VALID_EMAIL_REGEX, $contactRow['email_address'])) {
			return false;
		}

		$activeCampaignIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code",
			"ACTIVECAMPAIGN_ID");
		if (empty($activeCampaignIdentifierTypeId)) {
			$resultSet = executeQuery("insert into contact_identifier_types (client_id, contact_identifier_type_code, description, internal_use_only) values (?,?,?,?)",
				$GLOBALS['gClientId'], "ACTIVECAMPAIGN_ID", "ActiveCampaign Contact Identifier", 1);
			$activeCampaignIdentifierTypeId = $resultSet['insert_id'];
		}
		$activeCampaignContactIdentifier = getFieldFromId("identifier_value", "contact_identifiers", "contact_id", $contactId,
			"contact_identifier_type_id = ?", $activeCampaignIdentifierTypeId);

		if (!empty($activeCampaignContactIdentifier)) { // make sure ActiveCampaign id is valid
			$result = $this->getApi("api/3/contacts/" . $activeCampaignContactIdentifier);
			if ($result === false) {
				executeQuery("delete from contact_identifiers where contact_id = ? and contact_identifier_type_id = ? and identifier_value = ?",
					$contactId, $activeCampaignIdentifierTypeId . $activeCampaignContactIdentifier);
				$activeCampaignContactIdentifier = false;
			}
		}

		if (empty($activeCampaignContactIdentifier)) {
			// check to see if contact already exists
			$result = $this->getApi("api/3/contacts?filters[email]=" . $contactRow['email_address']);
			if (!empty($result['contacts']) && is_array($result['contacts'])) {
				$activeCampaignContactIdentifier = $result['contacts'][0]['id'];
			}
			if (empty($activeCampaignContactIdentifier)) {  // create new contact
				$result = $this->postApi("api/3/contacts", array("contact" => array("email" => strtolower($contactRow['email_address']),
					"first_name" => $contactRow['first_name'],
					"last_name" => $contactRow['last_name'])));
				$activeCampaignContactIdentifier = $result['contact']['id'];
			}
			if (!empty($activeCampaignContactIdentifier)) {
				executeQuery("insert ignore into contact_identifiers (contact_id, contact_identifier_type_id, identifier_value) values (?,?,?)",
					$contactId, $activeCampaignIdentifierTypeId, $activeCampaignContactIdentifier);
			}
		}
		return $activeCampaignContactIdentifier;
	}

	private function getCustomerId($contactRow, $connectionId) {
		$contactId = $contactRow['contact_id'];
		if (!preg_match(self::VALID_EMAIL_REGEX, $contactRow['email_address'])) {
			$contactRow['email_address'] = "";
		}

		$activeCampaignCustomerIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code",
			"ACTIVECAMPAIGN_CUSTOMER_ID");
		if (empty($activeCampaignCustomerIdentifierTypeId)) {
			$resultSet = executeQuery("insert into contact_identifier_types (client_id, contact_identifier_type_code, description, internal_use_only) values (?,?,?,?)",
				$GLOBALS['gClientId'], "ACTIVECAMPAIGN_CUSTOMER_ID", "ActiveCampaign Customer Identifier", 1);
			$activeCampaignCustomerIdentifierTypeId = $resultSet['insert_id'];
		}

		$activeCampaignCustomerIdentifier = getFieldFromId("identifier_value", "contact_identifiers", "contact_id", $contactId,
			"contact_identifier_type_id = ?", $activeCampaignCustomerIdentifierTypeId);
		if (!empty($activeCampaignCustomerIdentifier)) { // make sure ActiveCampaign id is valid
			$result = $this->getApi("api/3/ecomCustomers/" . $activeCampaignCustomerIdentifier);
			if ($result === false) {
				executeQuery("delete from contact_identifiers where contact_id = ? and contact_identifier_type_id = ? and identifier_value = ?",
					$contactId, $activeCampaignCustomerIdentifierTypeId, $activeCampaignCustomerIdentifier);
				$activeCampaignCustomerIdentifier = false;
			}
		}

		if (empty($activeCampaignCustomerIdentifier)) {
			// check to see if customer already exists
			if (!empty($contactRow['email_address'])) {
				$result = $this->getApi("api/3/ecomCustomers?filters[connectionid]=" . $this->iConnectionId . "&filters[email]=" . $contactRow['email_address']);
				if (!empty($result['ecomCustomers']) && is_array($result['ecomCustomers'])) {
					$activeCampaignCustomerIdentifier = $result['ecomCustomers'][0]['id'];
				}
				if (empty($activeCampaignCustomerIdentifier)) {  // create new customer
					$result = $this->postApi("api/3/ecomCustomers", array("ecomCustomer" => array(
						"connectionid" => $connectionId,
						"externalid" => $contactId,
						"email" => $contactRow['email_address'])));
					$activeCampaignCustomerIdentifier = $result['ecomCustomer']['id'];
				}
			} else {
				$result = $this->getApi("api/3/ecomCustomers?filters[connectionid]=" . $this->iConnectionId . "&filters[externalid]=" . $contactId);
				if (!empty($result["ecomCustomers"]) && is_array($result['ecomCustomers'])) {
					foreach ($result['ecomCustomers'] as $thisCustomer) {
						$activeCampaignCustomerIdentifier = $thisCustomer['id'];
					}
				}
			}
			if (!empty($activeCampaignCustomerIdentifier)) {
				executeQuery("insert ignore into contact_identifiers (contact_id, contact_identifier_type_id, identifier_value) values (?,?,?)",
					$contactId, $activeCampaignCustomerIdentifierTypeId, $activeCampaignCustomerIdentifier);
			}
		}
		return $activeCampaignCustomerIdentifier;
	}

	public function tagContact($contactId, $tagName, $delete = false) {
		$tagResult = $this->getApi("api/3/tags?search=" . urlencode($tagName));
		$tagId = "";
		if (is_array($tagResult) && is_array($tagResult['tags'])) {
			foreach ($tagResult['tags'] as $thisTag) {
				if (strtolower($thisTag['tag']) == strtolower($tagName) && $thisTag['tagType'] == "contact") {
					$tagId = $thisTag['id'];
				}
			}
		}
		if (empty($tagId)) {
			$tagResult = $this->postApi("api/3/tags", array("tag" => array("tagType" => "contact", "tag" => $tagName)));
			if ($tagResult == false) {
				return false;
			} else {
				$tagId = $tagResult['tag']['id'];
			}
		}
		$activeCampaignContactId = $this->getActiveCampaignContactId(Contact::getContact($contactId));
		if (!empty($tagId) && !empty($activeCampaignContactId)) {
			if (!$delete) {
				$this->postApi("api/3/contactTags", array("contactTag" => array("contact" => $activeCampaignContactId, "tag" => $tagId)));
			} else {
				$contactTagResult = $this->getApi("api/3/contacts/" . $activeCampaignContactId . "/contactTags");
				$contactTagId = "";
				foreach ($contactTagResult['contactTags'] as $thisContactTag) {
					if ($thisContactTag["tag"] = $tagId) {
						$contactTagId = $thisContactTag['id'];
						break;
					}
				}
				if (!empty($contactTagId)) {
					$this->getApi("api/3/contactTags/" . $contactTagId, true);
				}
			}
		}
	}

	public function logOrder($orderId) {
		$connectionId = $this->getConnectionId();
		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$contactId = $orderRow['contact_id'];
		$contactRow = Contact::getContact($contactId);

		$activeCampaignCustomerIdentifier = $this->getCustomerId($contactRow, $connectionId);
		if (empty($activeCampaignCustomerIdentifier)) {
			return false;
		}

		$orderItems = array();
		$resultSet = executeQuery("select * from order_items join products using (product_id) join product_data using (product_id) where order_id = ?", $orderId);
		$orderTotal = ($orderRow['shipping_charge'] + $orderRow['tax_charge'] + $orderRow['handling_charge']) * 100;

		while ($row = getNextRow($resultSet)) {
			$row['sale_price'] = round($row['sale_price'], 2) * 100;
			$thisItem = array();
			$thisItem['externalid'] = $row['upc_code'] ?: $row['product_code'];
			$thisItem['name'] = $row['description'];
			$thisItem['quantity'] = $row['quantity'];
			$thisItem['price'] = $row['sale_price'];
			$categoryResult = executeReadQuery("select description from product_categories where product_category_id ="
				. " (select product_category_id from product_category_links where product_id = ? order by sequence_number limit 1)", $row['product_id']);
			if ($categoryRow = getNextRow($categoryResult)) {
				$thisItem['category'] = $categoryRow['description'];
			} else {
				$thisItem['category'] = "";
			}
			$thisItem['sku'] = $row['manufacturer_sku'];
			$thisItem['description'] = $row['detailed_description'];
			$imageUrl = ProductCatalog::getProductImage($row['product_id'], array("no_cache_filename" => true, "product_row" => $row));
			$thisItem['imageUrl'] = (substr($imageUrl, 0, 1) == "/" ? getDomainName() . $imageUrl : $imageUrl);
			$thisItem['productUrl'] = getDomainName() . "/product/" . $row['link_name'];

			$orderTotal += $row['quantity'] * $row['sale_price'];
			$orderItems[] = $thisItem;
		}
		freeResult($resultSet);

		$ecomOrder = array("ecomOrder" => array(
			"externalid" => $orderId,
			"source" => "1",
			"email" => $contactRow['email_address'],
			"externalCreatedDate" => date("c", strtotime($orderRow['order_time'])),
			"shippingMethod" => getFieldFromId("description", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']),
			"shippingAmount" => round($orderRow['shipping_charge'], 2) * 100,
			"taxAmount" => round($orderRow['tax_charge'], 2) * 100,
			"discountAmount" => round($orderRow['order_discount'], 2) * 100,
			"totalPrice" => round($orderTotal, 2),
			"currency" => "USD",
			"connectionid" => $connectionId,
			"customerid" => $activeCampaignCustomerIdentifier,
			"orderDiscounts" => array("name" => getFieldFromId("promotion_code", "promotions", "promotion_id",
				getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId)),
				"type" => "order",
				"amount" => round($row['order_discount'], 2) * 100),
			"orderProducts" => $orderItems
		));

		return $this->postApi("api/3/ecomOrders", $ecomOrder);
	}

	public function logAbandonedCart($shoppingCartId) {
		$connectionId = $this->getConnectionId();
		$shoppingCartRow = getRowFromId("shopping_carts", "shopping_cart_id", $shoppingCartId);
		$contactId = $shoppingCartRow['contact_id'];
		$contactRow = Contact::getContact($contactId);
        $checkoutLink = getFieldFromId("link_name", "pages", "link_name", "shopping-cart","inactive = 0 and internal_use_only = 0");
        if(empty($checkoutLink)) {
            $checkoutPages = array("retailstore/simplifiedcheckout.php", "retailstore/checkoutv2.php", "retailstore/checkout.php", "retailstore/shoppingcart.php");
            $checkoutLink = getFieldFromId("link_name", "pages", "inactive", 0,
                "script_filename in ('" . implode("','", $checkoutPages) . "') and internal_use_only = 0");
        }
        $checkoutLink = getDomainName() . "/" . $checkoutLink;

		$activeCampaignCustomerIdentifier = $this->getCustomerId($contactRow, $connectionId);
		if (empty($activeCampaignCustomerIdentifier)) {
            $this->iErrorMessage = "ActiveCampaign Customer ID cannot be found for contact ID " . $contactId;
			executeQuery("update shopping_carts set abandon_email_sent = 1 where shopping_cart_id = ?", $shoppingCartId);
			return false;
		}

        // externalcheckoutid is required to be unique.  If it exists update the existing one.
        $result = $this->getApi("api/3/ecomOrders?filters[externalcheckoutid]=" . $shoppingCartId);

        $remoteId = false;
        if($result['meta']['total'] == 1) {
            $remoteId = $result['ecomOrders'][0]['id'];
        }

		$resultSet = executeQuery("select *,products.description as product_description from products join product_data using (product_id) join shopping_cart_items using (product_id) where shopping_cart_id = ?", $shoppingCartId);

		while ($row = getNextRow($resultSet)) {
			if (empty($row['description'])) {
				$row['description'] = $row['product_description'];
			}
			$row['sale_price'] = round($row['sale_price'], 2) * 100;
			$thisItem = array();
			$thisItem['externalid'] = $row['upc_code'] ?: $row['product_code'];
			$thisItem['name'] = $row['description'];
			$thisItem['quantity'] = $row['quantity'];
			$thisItem['price'] = $row['sale_price'];
			$categoryResult = executeReadQuery("select description from product_categories where product_category_id ="
				. " (select product_category_id from product_category_links where product_id = ? order by sequence_number limit 1)", $row['product_id']);
			if ($categoryRow = getNextRow($categoryResult)) {
				$thisItem['category'] = $categoryRow['description'];
			} else {
				$thisItem['category'] = "";
			}
			$thisItem['sku'] = $row['manufacturer_sku'];
			$thisItem['description'] = $row['detailed_description'];
			$imageUrl = ProductCatalog::getProductImage($row['product_id'], array("no_cache_filename" => true, "product_row" => $row));
			$thisItem['imageUrl'] = (substr($imageUrl, 0, 1) == "/" ? getDomainName() . $imageUrl : $imageUrl);
			$thisItem['productUrl'] = getDomainName() . "/product/" . $row['link_name'];

			$cartTotal += $row['quantity'] * $row['sale_price'];
			$cartItems[] = $thisItem;
		}
		freeResult($resultSet);

		$ecomOrder = array("ecomOrder" => array(
			"externalcheckoutid" => $shoppingCartId,
			"source" => "1",
			"email" => $contactRow['email_address'],
			"externalCreatedDate" => date("c", strtotime($shoppingCartRow['date_created'])),
			"abandonedDate" => date("c", strtotime($shoppingCartRow['last_activity'])),
			"totalPrice" => round($cartTotal, 2),
			"currency" => "USD",
			"connectionid" => $connectionId,
			"customerid" => $activeCampaignCustomerIdentifier,
			"orderProducts" => $cartItems,
            "orderUrl" => $checkoutLink
		));

        if(empty($remoteId)) {
            $result = $this->postApi("api/3/ecomOrders", $ecomOrder);
        } else {
            $result = $this->postApi("api/3/ecomOrders/" . $remoteId, $ecomOrder, true);
        }
		if ($result !== false) {
			executeQuery("update shopping_carts set abandon_email_sent = 1 where shopping_cart_id = ?", $shoppingCartId);
		}
		return $result;
	}

	public function logEvent($contactId, $eventName, $parameters = array()) {
		// need to log events for:
		// 1. Order status updated
		// 2. recurring payment failed
		// 3. payment method for recurring payment updated
		$eventKey = getPreference("ACTIVECAMPAIGN_EVENT_KEY");
		if (empty($eventKey)) {
			$this->iErrorMessage = "ActiveCampaign Event Tracking Key is missing";
			return false;
		}
		$analyticsCode = $this->getAnalyticsCode();
		$accountStartPos = stripos($analyticsCode, "'setAccount', '") + strlen("'setAccount', '");
		$accountId = substr($analyticsCode, $accountStartPos, stripos($analyticsCode, "'", $accountStartPos + 1) - $accountStartPos);
		$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
		$request = array("key" => $eventKey,
			"event" => $eventName,
			"eventData" => jsonEncode($parameters),
			"actid" => $accountId,
			"visit" => jsonEncode(array("email" => $emailAddress)));
		$result = $this->postEvent($request);
		return $result;
	}

	public function getAnalyticsCode() {
		$this->postApi("api/3/siteTrackingDomains", array("siteTrackingDomain" => array("name" => getDomainName(true))));
		$result = $this->getApi("api/3/siteTracking/code", false, false);

		if (!startsWith($result, "<script")) {
			$result = false;
		}
		return $result;
	}


	/**
	 * @return mixed
	 */
	public function getErrorMessage() {
		return $this->iErrorMessage;
	}
}
