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

class coreSTORE {

	static private $iCorestoreIpAddresses = array("52.44.89.154", "54.82.86.199", "18.209.150.66");

    static function createSetupCorestoreApiApp($clientId = false) {
        if (empty($clientId)) {
            $clientId = $GLOBALS['gClientId'];
        }
        // make sure setup_corestore api method exists
        $setupCorestoreApiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", "SETUP_CORESTORE");
        if (empty($setupCorestoreApiMethodId)) {
            $insertSet = executeQuery("insert into api_methods (api_method_code, description, detailed_description) values ('SETUP_CORESTORE', 'Set up coreSTORE', 'Use this method to link coreFIRE/coreFORCE to coreSTORE.  " .
                "Login must be called first and session_identifier passed to this method.  The user who logs in to create the connection must be a full client access user or have a value in the custom field COREFIRE_ADMIN.  " .
                "This method can only be called once per coreFORCE client.  If changes are needed after setup, they must be done manually.  api_app_code and api_app_version must also be included in the call.')");
            $setupCorestoreApiMethodId = $insertSet['insert_id'];
        }
        $apiParameterArray = array(array("column_name" => "corestore_endpoint", "data_type" => "varchar", "description" => "coreSTORE Endpoint"),
            array("column_name" => "corestore_api_key", "data_type" => "varchar", "description" => "coreSTORE API Key"),
            array("column_name" => "session_identifier", "data_type" => "varchar", "description" => "Session Identifier"));
        $apiParametersDataTable = new DataTable('api_parameters');
        $apiParametersDataTable->setSaveOnlyPresent(true);
        foreach ($apiParameterArray as $thisParameter) {
            $apiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", $thisParameter['column_name']);
            if (empty($apiParameterId)) {
                $apiParameterId = $apiParametersDataTable->saveRecord(array("name_values" => $thisParameter));
            }
            $apiMethodParameterId = getFieldFromId("api_method_parameter_id", "api_method_parameters", "api_parameter_id", $apiParameterId,
                "api_method_id = ?", $setupCorestoreApiMethodId);
            if (empty($apiMethodParameterId)) {
                executeQuery("insert into api_method_parameters (api_method_id, api_parameter_id) values (?,?)", $setupCorestoreApiMethodId, $apiParameterId);
            }
        }
        $loginApiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", "LOGIN");
        // make sure setup_corestore api method group exists
        $setupCorestoreApiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", "SETUP_CORESTORE");
        if (empty($setupCorestoreApiMethodGroupId)) {
            $insertSet = executeQuery("insert into api_method_groups (api_method_group_code, description) values ('SETUP_CORESTORE', 'Set up coreSTORE')");
            $setupCorestoreApiMethodGroupId = $insertSet['insert_id'];
        }
        executeQuery("insert ignore into api_method_group_links (api_method_group_id, api_method_id) values (?,?)", $setupCorestoreApiMethodGroupId, $setupCorestoreApiMethodId);
        executeQuery("insert ignore into api_method_group_links (api_method_group_id, api_method_id) values (?,?)", $setupCorestoreApiMethodGroupId, $loginApiMethodId);
        // create setup_corestore api app (must be created for every client)
        $setupCorestoreApiAppId = getFieldFromId("api_app_id", "api_apps", "api_app_code", "SETUP_CORESTORE", "client_id = ?", $clientId);
        if(empty($setupCorestoreApiAppId)) {
            $insertSet = executeQuery("insert into api_apps (client_id, api_app_code, description, current_version, minimum_version, recommended_version) " .
                "values (?,'SETUP_CORESTORE', 'Set up coreSTORE', 1.0, 1.0, 1.0)", $clientId);
            $setupCorestoreApiAppId = $insertSet['insert_id'];
        }
        executeQuery("insert ignore into api_app_method_groups (api_app_id, api_method_group_id) values (?,?)", $setupCorestoreApiAppId, $setupCorestoreApiMethodGroupId);
    }

	public static function orderNotification($orderId, $reason = "") {
		$parameters = array("method" => "/index.php/api/v1/orders",
			"order_number" => $orderId,
			"reason" => $reason
		);
		$userFflSet = executeQuery("select * from users where user_id in (select user_id from user_ffls where federal_firearms_licensee_id = " .
			"(select federal_firearms_licensee_id from orders where order_id = ?))", $orderId);
		if ($row = getNextRow($userFflSet)) {
			$parameters['corestore_endpoint'] = CustomField::getCustomFieldData($row['contact_id'], "CORESTORE_ENDPOINT");
			$parameters['corstore_api_key'] = CustomField::getCustomFieldData($row['contact_id'], "CORESTORE_API_KEY");
		}
		self::sendNotification($parameters);
	}

	public static function giftCardNotification($giftCardNumber, $reason, $description, $newBalance) {
		$parameters = array("method" => "/index.php/webhooks/giftcard",
			"gift_card_number" => $giftCardNumber,
			"reason" => $reason,
			"description" => $description,
			"balance" => $newBalance,
			"source" => "coreforce"
		);
		self::sendNotification($parameters);
	}

	public static function contactNotification($contactId, $reason = "") {
		if (!$GLOBALS['gSkipCorestoreContactUpdate']) {
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $contactId,
				"contact_id not in (select contact_id from federal_firearms_licensees) and " .
				"contact_id not in (select contact_id from product_manufacturers) and " .
				"contact_id not in (select contact_id from locations)");
			if (!empty($contactId)) {
				$parameters = array("method" => "/index.php/webhooks/contact",
					"contact_id" => $contactId,
					"reason" => $reason);
				self::sendNotification($parameters);
			}
		}
	}

	public static function invoicePaymentNotification($invoicePaymentId) {
		$invoicePaymentRow = getRowFromId("invoice_payments", "invoice_payment_id", $invoicePaymentId,
			"invoice_id in (select invoice_id from invoices where client_id = ?)", $GLOBALS['gClientId']);
		$invoiceRow = getRowFromId("invoices", "invoice_id", $invoicePaymentRow['invoice_id']);
		if (!empty($invoiceRow)) {
			$parameters = array("method" => "/index.php/webhooks/invoice",
				"invoice_id" => $invoiceRow['invoice_id'],
				"invoice_payment" => $invoicePaymentRow);
			self::sendNotification($parameters);
		}
	}

	private static function sendNotification($parameters) {
		if ($_POST['source_code'] == "CORESTORE" || $GLOBALS['gAPISourceCode'] == "CORESTORE" || in_array($_SERVER['REMOTE_ADDR'], self::$iCorestoreIpAddresses)) {
			return;
		}
		if (array_key_exists("corestore_endpoint", $parameters) && array_key_exists("corestore_api_key", $parameters)) {
			$corestoreEndpoint = $parameters['corestore_endpoint'];
			$corestoreApiKey = $parameters['corestore_api_key'];
		} else {
			$corestoreEndpoint = getPreference("CORESTORE_ENDPOINT");
			$corestoreApiKey = getPreference("CORESTORE_API_KEY");
		}
        $logging = !empty(getPreference("LOG_CORESTORE"));
        if (!array_key_exists("source", $parameters)) {
			$parameters['source'] = 'coreforce';
		}
		if (!empty($corestoreApiKey) && !empty($corestoreEndpoint)) {
			if ($GLOBALS['gPrimaryDatabase']->tableExists("corestore_queue")) {
				executeQuery("insert into corestore_queue (client_id, parameters) values (?,?)", $GLOBALS['gClientId'], jsonEncode($parameters));
			} else {
				if (!startsWith($corestoreEndpoint, "http")) {
					$corestoreEndpoint = "https://" . $corestoreEndpoint;
				}
                $startMilliseconds = getMilliseconds();
				$method = $parameters['method'];
				unset($parameters['method']);
				$corestoreEndpoint = trim($corestoreEndpoint, "/") . $method;
				$postParameters = json_encode($parameters);
				$headers = array(
					"x-api-key: " . $corestoreApiKey,
					"Content-Type: application/json",
					"Content-Length: " . strlen($postParameters)
				);
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_TIMEOUT_MS, ($logging ? 10000 : 100));
				curl_setopt($ch, CURLOPT_URL, $corestoreEndpoint);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);

				$result = curl_exec($ch);
				$err = curl_error($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				if (getPreference("LOG_CORESTORE")) {
					addDebugLog("coreSTORE request: " . $corestoreEndpoint
						. "\ncoreSTORE Data: " . $postParameters
						. "\ncoreSTORE Result: " . $result
						. (empty($err) ? "" : "\ncoreSTORE Error: " . $err)
						. "\ncoreSTORE HTTP Status: " . $httpCode
                        . "\ncoreSTORE Time Elapsed: " . getTimeElapsed($startMilliseconds, getMilliseconds()));
				}
			}
		}
	}
}
