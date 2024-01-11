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

/*
 * function to get ip quality data for passed in data
 * Passed in data can include:

ip_address
billing_first_name
billing_last_name
billing_company
billing_country
billing_address_1
billing_address_2
billing_city
billing_postcode
billing_email
billing_phone
shipping_first_name
shipping_last_name
shipping_company
shipping_country
shipping_address_1
shipping_address_2
shipping_city
shipping_postcode
shipping_email
shipping_phone

 */

class IpQualityScore {
	public static function getIpQualityData($parameters = array()) {
		$domainName = $_SERVER['HTTP_HOST'];
		if (substr($domainName, 0, 4) == "www.") {
			$domainName = substr($domainName, 4);
		}
		$domainNameTrackingLogId = getCachedData("ip_quality_tracking", $domainName);
		if (empty($domainNameTrackingLogId)) {
			$domainNameTrackingLogId = getFieldFromId("domain_name_tracking_log_id", "domain_name_tracking_log", "domain_name", $domainName, "log_type = 'IP_QUALITY' and log_date = current_date");
		}
		if (empty($domainNameTrackingLogId)) {
			$GLOBALS['gPrimaryDatabase']->ignoreError(true);
			$resultSet = executeQuery("insert ignore into domain_name_tracking_log (client_id,domain_name,log_type,log_date,use_count) values (?,?,'IP_QUALITY',current_date,1)", $GLOBALS['gClientId'], $domainName);
			if (empty($resultSet['sql_error'])) {
				$domainNameTrackingLogId = $resultSet['insert_id'];
				setCachedData("ip_quality_tracking", $domainName, $domainNameTrackingLogId, 24);
			}
			$GLOBALS['gPrimaryDatabase']->ignoreError(false);
		} else {
			$resultSet = executeQuery("update domain_name_tracking_log set use_count = use_count + 1 where domain_name_tracking_log_id = ?", $domainNameTrackingLogId);
		}
		$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "COREGUARD_ENABLED", "client_id = ?", $GLOBALS['gDefaultClientId']);
		$clientIpQualitySetup = getFieldFromId("text_data", "custom_field_data", "custom_field_id", $customFieldId, "primary_identifier = ?", $GLOBALS['gClientRow']['contact_id']);
		if (empty($clientIpQualitySetup)) {
			return array("fraud_score" => 0);
		}
		if (empty($parameters['ip_address'])) {
			$parameters['ip_address'] = $_SERVER['REMOTE_ADDR'];
		}
		$strictness = getPreference("FRAUD_STRICTNESS_LEVEL");
		if (empty($strictness) || !is_numeric($strictness) || !in_array($strictness, array(0, 1, 2, 3))) {
			$strictness = 0;
		}
		$parameters['strictness'] = $strictness;
		/*
		ip_address
		billing_first_name
		billing_last_name
		billing_company
		billing_country
		billing_address_1
		billing_address_2
		billing_city
		billing_postcode
		billing_email
		billing_phone
		shipping_first_name
		shipping_last_name
		shipping_company
		shipping_country
		shipping_address_1
		shipping_address_2
		shipping_city
		shipping_postcode
		shipping_email
		shipping_phone
		*/

		$fieldArray = array("first_name", "last_name", "company" => "business_name", "country" => "country_code", "address_1", "address_2", "city", "region" => "state", "postcode" => "postal_code", "email" => "email_address", "phone" => "phone_number");
		foreach ($fieldArray as $fieldName => $dataField) {
			if (is_numeric($fieldName)) {
				$fieldName = $dataField;
			}
			if (array_key_exists($fieldName, $parameters)) {
				continue;
			}
			$dataValue = $_POST[$dataField];
			if (empty($dataValue)) {
				$dataValue = $_POST['shipping_' . $dataField];
			}
			if (empty($dataValue)) {
				$dataValue = $_POST['billing_' . $dataField];
			}
			if (!empty($dataValue)) {
				$parameters[$fieldName] = $dataValue;
			}
		}

		$liveLookup = false;

		ksort($parameters);
		executeQuery("delete from ip_quality_score_data where expiration_date <= current_date");
		$hashCode = md5(jsonEncode($parameters));
		if ($liveLookup) {
			executeQuery("delete from ip_quality_score_data where hash_code = ?", $hashCode);
			$json = "";
		} else {
			$json = getFieldFromId("content", "ip_quality_score_data", "hash_code", $hashCode);
		}
		if (empty($json)) {
			$ipQualityScoreApiKey = getPreference("IP_QUALITY_SCORE_API_KEY");
			if (empty($ipQualityScoreApiKey)) {
				return array("fraud_score" => 0);
			}

			$userAgent = $_SERVER['HTTP_USER_AGENT'];
			$userLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			$allowPublicAccessPoints = 'true';
			$lighterPenalties = 'false';

			$expirationDate = (count($parameters) == 1 ? date("Y-m-d", strtotime("+ 30 days")) : "");
			$parameters = array_merge($parameters, array(
				'user_agent' => $userAgent,
				'user_language' => $userLanguage,
				'allow_public_access_points' => $allowPublicAccessPoints,
				'lighter_penalties' => $lighterPenalties
			));

			$formattedParameters = http_build_query($parameters);

// Create API URL
			$url = sprintf(
				'https://www.ipqualityscore.com/api/json/ip/%s/%s?%s',
				$ipQualityScoreApiKey,
				$parameters['ip_address'],
				$formattedParameters
			);

// Fetch The Result
			$timeout = 10;

			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);

			$json = curl_exec($curl);
			curl_close($curl);

			$result = json_decode($json, true);
			$savedResults = $result;
			$savedResults['called_parameters'] = $parameters;

			if (isset($result['success']) && $result['success'] === true) {
				executeQuery("insert into ip_quality_score_data (hash_code,content,expiration_date) values (?,?,?)", $hashCode, jsonEncode($savedResults), $expirationDate);
			}
			$json = jsonEncode($savedResults);
		}
		return json_decode($json, true);
	}

	public static function shouldNotAllowEcommerce() {
		$threshold = getPreference("ECOMMERCE_FRAUD_SCORE_THRESHOLD");
		if (empty($threshold)) {
			$threshold = 90;
		}
		$qualityData = self::getIpQualityData();
		$fraudScore = $qualityData['fraud_score'];
		if ($fraudScore >= 100) {
			addDebugLog("DO NOT ALLOW eCommerce:" . $_SERVER['REMOTE_ADDR'] . ":" . $fraudScore . ":" . $threshold);
			return true;
		} else {
			return false;
		}
	}

	public static function shouldBlacklistIpAddress() {
		if ($GLOBALS['gLoggedIn']) {
			return false;
		}
		$threshold = getPreference("BLACKLIST_FRAUD_SCORE_THRESHOLD");
		if (empty($threshold)) {
			$threshold = 98;
		}
		$qualityData = self::getIpQualityData();
		$fraudScore = $qualityData['fraud_score'];
		if ($fraudScore >= 100) {
			addDebugLog("DO NOT ALLOW connection:" . $_SERVER['REMOTE_ADDR'] . ":" . $fraudScore . ":" . $threshold);
			return true;
		} else {
			return false;
		}
	}

	public static function isProxy($ipAddress) {
		$qualityData = self::getIpQualityData();
		return $qualityData['proxy'];
	}
}
