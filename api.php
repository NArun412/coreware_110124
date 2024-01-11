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

header("Access-Control-Allow-Origin: *");

if (empty($_GET['action']) && !empty($_GET['method'])) {
	$_GET['action'] = $_GET['method'];
}
if (empty($_GET['action'])) {
	$_GET['action'] = $_POST['action'];
}
if (empty($_GET['action'])) {
	$_GET['action'] = $_POST['method'];
}
$returnArray = array();

if (count($_POST) < 1) {
	$headerInput = file_get_contents("php://input");
	if (!empty($headerInput)) {
		if (substr($headerInput, 0, 1) == "[" || substr($headerInput, 0, 1) == "{") {
			$_POST = json_decode($headerInput, true);
			if (json_last_error() != JSON_ERROR_NONE) {
				$returnArray['error_message'] = "JSON format error: " . $headerInput;
				$returnArray['result'] = "ERROR";
				echo json_encode($returnArray);
				exit;
			}
		} else {
			parse_str($headerInput, $_POST);
		}
	}
}
if (array_key_exists("json_post_parameters", $_POST)) {
	try {
		$postParameters = json_decode($_POST['json_post_parameters'], true);
		$_POST = array_merge($_POST, $postParameters);
		unset($_POST['json_post_parameters']);
	} catch (Exception $e) {
	}
}

# put everything into POST AND GET

$_POST = array_merge($_POST, $_GET);
$_GET = $_POST;
$returnArray['post_variables'] = $_POST;
if (!empty($returnArray['post_variables']['password'])) {
	$returnArray['post_variables']['password'] = "XXXXXXXX";
}

$runEnvironment = php_sapi_name();
$GLOBALS['gCommandLine'] = ($runEnvironment == "cli");
$GLOBALS['gApcuEnabled'] = !$GLOBALS['gCommandLine'] && ((extension_loaded('apc') && ini_get('apc.enabled')) || (extension_loaded('apcu') && ini_get('apc.enabled')));
if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/apibeforeload.inc")) {
	include "apibeforeload.inc";
}

# Here, add methods to use cached results. If cached results can be delivered here, the database is NOT hit and significant load is reduced on the server.

switch ($_GET['action']) {
	case "get_facility_reservations":
		$apcuKey = "ALL|get_facility_reservations|" . $_SERVER['HTTP_HOST'] . ":" . json_encode($_POST);
		if (apcu_exists($apcuKey)) {
			$reservations = apcu_fetch($apcuKey);
			if (is_array($reservations)) {
				error_log("LOCATED get_facilities_reservation: " . date("m/d/Y H:i:s") . "\n", 3, "/var/log/debug.log");
				$returnArray['reservations'] = $reservations;
				$returnArray['result'] = "OK";
				echo json_encode($returnArray);
				exit;
			}
		}
}

$GLOBALS['gPageCode'] = "API";
$GLOBALS['gAuthorizeComputer'] = true;
$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gIgnoreNotices'] = true;
$GLOBALS['gApiCall'] = true;
require_once "shared/startup.inc";

if (empty($_POST['connection_key']) && array_key_exists("HTTP_CONNECTION_KEY", $_SERVER)) {
	$_GET['connection_key'] = $_POST['connection_key'] = $_SERVER['HTTP_CONNECTION_KEY'];
}

$GLOBALS['gChangeLogNotes'] = $_SERVER['REQUEST_URI'];

if (!$GLOBALS['gUserRow']['superuser_flag'] && empty($_POST['connection_key']) && empty($_POST['api_app_code'])) {
	logout();
}

# Preemptive running of the transaction count. This was primarily for SprintShip

if ($_POST['action'] == "get_coreware_transaction_count") {
	$systemName = getPreference("system_name");
	$transactionCount = "None Available";
	switch ($systemName) {
		case "SPRINTSHIP":
			$costArray = array();
			$costArray[] = array("base_quantity" => 25000, "base_price" => 800, "per_transaction" => .10);
			$costArray[] = array("base_quantity" => 50000, "base_price" => 1500, "per_transaction" => .07);
			$costArray[] = array("base_quantity" => 100000, "base_price" => 2800, "per_transaction" => .05);
			$costArray[] = array("base_quantity" => 250000, "base_price" => 5000, "per_transaction" => .04);
			$costArray[] = array("base_quantity" => 500000, "base_price" => 8500, "per_transaction" => .035);
			$costArray[] = array("base_quantity" => 1000000, "base_price" => 14000, "per_transaction" => .03);
			$costArray[] = array("base_quantity" => 99999999999, "base_price" => 18000, "per_transaction" => .00);

			$transactionCount = "<html lang='en'><head><style>table { border-collapse: collapse; border: 1px solid black; } td { padding: 5px 10px; border: 1px solid black; }</style><title>Client Status</title></head><body><table><tr><th>Month</th><th>Count</th><th>Charge</th></tr>";
			$oneYearAgo = (date("Y") - 2) . "-" . date("m") . "-01";
			$resultSet = executeQuery("select month(delivery_date) as month,year(delivery_date) as year,count(*) from sprints where " .
				"delivery_date >= ? and sprint_id in (select sprint_id from packages where package_id not in (select package_id from package_tracking_log where " .
				"package_tracking_log_type_id in (select package_tracking_log_type_id from " .
				"package_tracking_log_types where package_tracking_log_type_code in ('CANCELLED','UNDELIVERABLE')))) " .
				"group by month(delivery_date),year(delivery_date) order by year,month", $oneYearAgo);
			while ($row = getNextRow($resultSet)) {
				$count = $row['count(*)'];
				$transactionCount .= "<tr><td>" . $row['month'] . "/" . $row['year'] . "</td><td>" . $count . "</td>";
				$cost = 0;
				foreach ($costArray as $thisCost) {
					if (($cost == 0 || $cost > $thisCost['base_price'])) {
						$remainder = max(0, ($count - $thisCost['base_quantity']));
						$cost = $thisCost['base_price'] + ($remainder * $thisCost['per_transaction']);
					}
				}
				$transactionCount .= "<td>" . (empty($cost) ? "" : number_format($cost, 2, ".", ",")) . "</td></tr>";
			}
			$transactionCount .= "</table></body></html>";
			break;
		case "TOOLS":
			$transactionCount = "<html lang='en'><head><style>table { border-collapse: collapse; border: 1px solid black; } td { padding: 5px 10px; border: 1px solid black; }</style><title>Client Status</title></head><body><table><tr><th>Month</th><th>Count</th></tr>";
			$oneYearAgo = (date("Y") - 2) . "-" . date("m") . "-01";
			$resultSet = executeQuery("select month(donation_date) as month,year(donation_date) as year,count(*) from donations where " .
				"donation_date >= ? and associated_donation_id is null group by month(donation_date),year(donation_date) order by year,month", $oneYearAgo);
			while ($row = getNextRow($resultSet)) {
				$transactionCount .= "<tr><td>" . $row['month'] . "/" . $row['year'] . "</td><td>" . $row['count(*)'] . "</td></tr>";
			}
			$transactionCount .= "</table></body></html>";
	}
	echo $transactionCount;
	exit;
}

$apiMethodsCalled = array();
$_POST['action'] = trim(strtolower($_POST['action']));
$apiMethodsCalled[] = $_POST['action'];
if (!empty($_POST['secondary_api_methods'])) {
	if (is_array($_POST['secondary_api_methods'])) {
		$secondaryApiMethods = $_POST['secondary_api_methods'];
	} else {
		$secondaryApiMethods = explode(",", $_POST['secondary_api_methods']);
	}
	foreach ($secondaryApiMethods as $thisApiMethod) {
		$thisApiMethod = trim(strtolower($thisApiMethod));
		if (!empty($thisApiMethod)) {
			$apiMethodsCalled[] = $thisApiMethod;
		}
	}
}
if (!empty($_POST['secondary_api_method'])) {
	$apiMethodsCalled[] = $_POST['secondary_api_method'];
}

$GLOBALS['gPrimaryApiMethodRow'] = false;
if (count($apiMethodsCalled) > 1) {
	$returnArray['api_method_return_array'] = array();
}

foreach ($apiMethodsCalled as $thisApiMethod) {
	$thisApiMethod = strtolower($thisApiMethod);
	$apiMethodRow = getRowFromId("api_methods", "api_method_code", $thisApiMethod);
	if (empty($GLOBALS['gPrimaryApiMethodRow'])) {
		$GLOBALS['gPrimaryApiMethodRow'] = $apiMethodRow;
	}
	if (empty($apiMethodRow)) {
		$returnArray['error_message'] = "API Method does not exist: " . $thisApiMethod;
		exitApi();
	}
	$accessCountArray = getCachedData("api_method_access_counts", "");
	if (!$accessCountArray) {
		$accessCountArray = array();
	}
	if (!array_key_exists($apiMethodRow['api_method_id'], $accessCountArray)) {
		$accessCountArray[$apiMethodRow['api_method_id']] = 0;
	}
	$accessCountArray[$apiMethodRow['api_method_id']]++;
	if ($accessCountArray[$apiMethodRow['api_method_id']] >= 100) {
		executeQuery("update api_methods set access_count = access_count + " . $accessCountArray[$apiMethodRow['api_method_id']] . " where api_method_id = ?", $apiMethodRow['api_method_id']);
		$accessCountArray[$apiMethodRow['api_method_id']] = 0;
	}
	setCachedData("api_method_access_counts", "", $accessCountArray, 24);

	$apiValidation = false;
	if (file_exists($GLOBALS['gDocumentRoot'] . "/apilogin.inc")) {
		/** @noinspection PhpIncludeInspection */
		include "apilogin.inc";
	}

	$apiAppRow = array();
	$apiAppRow = getRowFromId("api_apps", "api_app_code", strtoupper($_POST['api_app_code']), "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);

	if (empty($_POST['connection_key']) && !empty($_POST['api_key'])) {
		$_POST['connection_key'] = $_POST['api_key'];
	}
	$developerMethodAccess = false;
	$sessionIdentifier = $_POST['session_identifier'] . "";
	$deviceId = "";

	if ($GLOBALS['gUserRow']['superuser_flag'] && empty($_POST['connection_key']) && empty($_POST['api_app_code'])) {
		$apiValidation = true;
	}

	$developerRow = array();
	if (!$apiValidation) {
		$connectionKey = $_POST['connection_key'];
		if (!empty($connectionKey)) {
			$resultSet = executeQuery("select * from developers where connection_key = ? and inactive = 0 and contact_id in (select contact_id from contacts where client_id = ?)", $connectionKey, $GLOBALS['gClientId']);
			if ($developerRow = getNextRow($resultSet)) {
				$ipSet = executeQuery("select * from developer_ip_addresses where developer_id = ?", $developerRow['developer_id']);
				if ($ipSet['row_count'] > 0) {
					$validIp = false;
				} else {
					$validIp = true;
				}
				while ($ipRow = getNextRow($ipSet)) {
					$ipAddress = $ipRow['ip_address'] . (substr($ipRow['ip_address'], -1) == "." ? "" : ".");
					if ($_SERVER['REMOTE_ADDR'] == $ipRow['ip_address'] || strpos($_SERVER['REMOTE_ADDR'], $ipAddress) == 0) {
						$validIp = true;
						break;
					}
				}
				if (!$validIp) {
					$connectionKey = "";
				} else {
					if (!$developerRow['full_access']) {
						$developerApiMethodId = getFieldFromId("developer_api_method_id", "developer_api_methods", "developer_id",
							$developerRow['developer_id'], "api_method_id = ?", $apiMethodRow['api_method_id']);
						if (empty($developerApiMethodId)) {
							$developerApiMethodGroupId = getFieldFromId("developer_api_method_group_id", "developer_api_method_groups",
								"developer_id", $developerRow['developer_id'], "api_method_group_id in (select api_method_group_id from api_method_group_links where " .
								"api_method_id = ?)", $apiMethodRow['api_method_id']);
							if (empty($developerApiMethodGroupId)) {
								$connectionKey = "";
							}
						}
					}
				}
				if ($connectionKey) {
					$developerId = $developerRow['developer_id'];
					if (!empty($developerRow['user_id'])) {
						login($developerRow['user_id'], false);
					} else {
						$userId = Contact::getContactUserId($developerRow['contact_id']);
						if (!empty($userId)) {
							login($userId, false);
						}
					}
					$developerMethodAccess = true;
				}
			} else {
				$connectionKey = "";
			}
			if (empty($connectionKey)) {
				$returnArray['error_message'] = "Invalid Connection Key for method " . $thisApiMethod;
				exitApi();
			}
			$sessionExpiration = "";
		} else {
			if (empty($_POST['api_app_code']) || empty($_POST['api_app_version'])) {
				$returnArray['error_message'] = "api_app_code, api_app_version are all required";
				exitApi();
			}
			if (empty($apiAppRow)) {
				$returnArray['error_message'] = "Invalid API App";
				exitApi();
			}
			$apiAppVersionParts = explode(".", $_POST['api_app_version']);
			if (count($apiAppVersionParts) > 2) {
				$_POST['api_app_version'] = $apiAppVersionParts[0] . "." . $apiAppVersionParts[1];
			}
			$apiAppMethodId = getFieldFromId("api_app_method_id", "api_app_methods", "api_app_id", $apiAppRow['api_app_id'], "api_method_id = ?", $apiMethodRow['api_method_id']);
			if (empty($apiAppMethodId)) {
				$apiAppMethodId = getFieldFromId("api_app_method_group_id", "api_app_method_groups", "api_app_id", $apiAppRow['api_app_id'],
					"api_method_group_id in (select api_method_group_id from api_method_group_links where api_method_id = ?)", $apiMethodRow['api_method_id']);
			}
			if (empty($apiAppMethodId)) {
				$returnArray['error_message'] = "Invalid API method for this app: " . $_POST['api_app_code'] . ", v " . $_POST['api_app_version'] . ", " . $thisApiMethod;
				exitApi();
			}
			$sessionExpiration = $apiAppRow['default_timeout'];
			if (!empty($_POST['device_identifier'])) {
				$deviceId = getFieldFromId("device_id", "devices", "device_code", strtoupper($_POST['device_identifier']));
				if (empty($deviceId)) {
					$resultSet = executeQuery("insert ignore into devices (client_id,device_code,description) values (?,?,'Device')", $GLOBALS['gClientId'], strtoupper($_POST['device_identifier']));
					$deviceId = $resultSet['insert_id'];
				}
			}
		}

		$apiSessionRow = array();
		if (!empty($sessionIdentifier)) {
			if (!empty($sessionExpiration)) {
				$resultSet = executeQuery("delete from api_sessions where session_identifier = ? " .
					"and last_used < (now() - interval " . $sessionExpiration . " minute)", $sessionIdentifier);
			}
			$resultSet = executeQuery("select * from api_sessions join users using (user_id) where session_identifier = ? and device_id <=> ? and client_id = ?", $sessionIdentifier, $deviceId, $GLOBALS['gClientId']);
			if ($apiSessionRow = getNextRow($resultSet)) {
				$nowTime = date("U");
				$lastTime = date("U", strtotime($apiSessionRow['last_used']));
				login($apiSessionRow['user_id'], false);
				executeQuery("update api_sessions set last_used = now() where api_session_id = ?", $apiSessionRow['api_session_id']);
			} else {
				$sessionIdentifier = "";
			}
		}

		if (!empty($sessionIdentifier) && !empty($apiAppRow['requires_license'])) {
			if (empty($apiSessionRow['license_number'])) {
				$resultSet = executeQuery("select * from api_app_licenses where api_app_id = ? and license_number not in " .
					"(select license_number from api_sessions where license_number is not null and api_app_id = ?)", $apiAppRow['api_app_id'], $apiAppRow['api_app_id']);
				if ($row = getNextRow($resultSet)) {
					executeQuery("update api_sessions set license_number = ? where session_identifier = ?", $row['license_number'], $sessionIdentifier);
					$apiSessionRow['license_number'] = $row['license_number'];
				}
			} else {
				executeQuery("update api_sessions set license_number = null where session_identifier <> ? and api_app_id = ?",
					$sessionIdentifier, $apiAppRow['api_app_id']);
			}
			if (empty($apiSessionRow['license_number'])) {
				$returnArray['error_message'] = getSystemMessage("no_api_app_license", "No API app license available");
				exitApi();
			}
		}

		# Login required if no developer connection and not public access

		if (!$GLOBALS['gLoggedIn'] && !$apiMethodRow['public_access'] && empty($connectionKey)) {
			$returnArray = array("result" => "ERROR");
			$returnArray['error_message'] = getSystemMessage("login_required", "Login required");
			exitApi();
		}
		if ($GLOBALS['gLoggedIn'] && !$GLOBALS['gUserRow']['administrator_flag'] && !$apiMethodRow['public_access'] && !$apiMethodRow['all_user_access'] && !$developerMethodAccess) {
			$returnArray = array("result" => "ERROR");
			$returnArray['error_message'] = $thisApiMethod . ": " . getSystemMessage("invalid_credentials", "Invalid Login Credentials");
			exitApi();
		}
	}
}

if ($GLOBALS['gLoggedIn'] && !empty($_POST['localization_code'])) {
	$languageId = getFieldFromId("language_id", "languages", "localization_code", $_POST['localization_code']);
	if (!empty($languageId) && $languageId != $GLOBALS['gUserRow']['language_id']) {
		$GLOBALS['gUserRow']['language_id'] = $languageId;
		executeQuery("update users set language_id = ? where user_id = ?", $languageId, $GLOBALS['gUserId']);
	}
}
if (!empty($_POST['api_app_version']) && !empty($apiAppRow['minimum_version'])) {
	if ($_POST['api_app_version'] < $apiAppRow['minimum_version']) {
		$returnArray['result'] = "UPDATE";
		$returnArray['update_content'] = $apiAppRow['forced_update_content'];
		if (empty($returnArray['update_content'])) {
			$returnArray['update_content'] = getLanguageText("This app must be upgraded. Your device has version %api_app_version%, but %minimum_version% is the minimum version allowed. Please update the app.");
		}
		foreach ($_POST as $fieldName => $fieldValue) {
			$returnArray['update_content'] = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $returnArray['update_content']);
		}
		foreach ($apiAppRow as $fieldName => $fieldValue) {
			$returnArray['update_content'] = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $returnArray['update_content']);
		}
		ajaxResponse($returnArray);
	}
}

class ApiPage extends Page {
}

$pageObject = new ApiPage();
$_POST = trimFields($_POST);

foreach ($_POST as $fieldName => $fieldData) {
	if (!empty($fieldData)) {
		$resultSet = executeQuery("select * from api_parameters where column_name = ? and data_type is not null", $fieldName);
		if ($row = getNextRow($resultSet)) {
			$dataValid = true;
			switch ($row['data_type']) {
				case "array":
					if (!is_array($fieldData) && !is_object($fieldData)) {
						$_POST[$fieldName . "-original_value"] = $_POST[$fieldName];
						if (substr($_POST[$fieldName], 0, 1) == "{" && substr($_POST[$fieldName], -1) == "}") {
							try {
								$_POST[$fieldName] = json_decode($_POST[$fieldName], true);
							} catch (Exception $e) {
								$_POST[$fieldName] = array();
							}
						} else if (substr($_POST[$fieldName], 0, 1) == "[" && substr($_POST[$fieldName], -1) == "]") {
							try {
								$_POST[$fieldName] = json_decode($_POST[$fieldName], true);
							} catch (Exception $e) {
								$_POST[$fieldName] = array();
							}
						} else {
							$_POST[$fieldName] = explode(",", $fieldData);
						}
					}
					break;
				case "varchar":
					if (is_array($fieldData) || is_object($fieldData)) {
						$dataValid = false;
					}
					$_POST[$fieldName] = $fieldData . "";
					break;
				case "bigint":
				case "int":
					$_POST[$fieldName] = $fieldData = str_replace(",", "", $fieldData);
					if (!is_numeric($fieldData) && strpos($fieldData, ".") === false) {
						$dataValid = false;
					}
					break;
				case "decimal":
					$_POST[$fieldName] = $fieldData = str_replace(",", "", $fieldData);
					if (!is_numeric($fieldData)) {
						$dataValid = false;
					}
					break;
				case "date":
					$date = date_parse($fieldData);
					if ($date["error_count"] > 0 || !checkdate($date["month"], $date["day"], $date["year"])) {
						$dataValid = false;
					}
					break;
				case "tinyint":
					if ($fieldData != "0" && $fieldData != "1" && $fieldData != "true" && $fieldData != "false" && !is_bool($fieldData)) {
						$dataValid = false;
					}
					break;
			}
			if (!$dataValid) {
				$returnArray['error_message'] = "Invalid data type for parameter '" . $fieldName . "': " . $fieldData;
				exitApi();
			}
		}
	}
}

foreach ($apiMethodsCalled as $thisApiMethod) {
	$resultSet = executeQuery("select * from api_method_parameters join api_parameters using (api_parameter_id) where required = 1 and api_method_id = (select api_method_id from api_methods where api_method_code = ?)", $thisApiMethod);
	while ($row = getNextRow($resultSet)) {
		if ($row['data_type'] == "file") {
			if (!array_key_exists($row['column_name'], $_FILES)) {
				$returnArray = array("result" => "ERROR");
				$returnArray['error_message'] = $row['column_name'] . " is required: " . jsonEncode($_FILES);
				exitApi();
			}
		} else {
			if (!array_key_exists($row['column_name'], $_POST)) {
				$returnArray = array("result" => "ERROR");
				$returnArray['error_message'] = $row['column_name'] . " is required";
				exitApi();
			}
		}
	}
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/apipre.inc")) {
	/** @noinspection PhpIncludeInspection */
	include "apipre.inc";
}

foreach ($apiMethodsCalled as $thisApiMethod) {
	$_POST['action'] = $thisApiMethod;
	switch ($_POST['action']) {
		case "logout":
			if ($sessionIdentifier) {
				executeQuery("delete from api_sessions where session_identifier = ?", $sessionIdentifier);
			}
			break;

		case "get_recommended_version":
			$returnArray['recommended_version'] = $apiAppRow['recommended_version'];
			$returnArray['update_content'] = $apiAppRow['content'];
			foreach ($_POST as $fieldName => $fieldValue) {
				$returnArray['update_content'] = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $returnArray['update_content']);
			}
			foreach ($apiAppRow as $fieldName => $fieldValue) {
				$returnArray['update_content'] = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $returnArray['update_content']);
			}
			break;

		case "email_user_name":
			if (empty($_POST['email_address'])) {
				$returnArray['error_message'] = getSystemMessage("no_email_address", "No Email Address sent");
				break;
			}
			$resultSet = executeQuery("select * from users where contact_id in (select contact_id from contacts where " .
				"email_address = ?) and administrator_flag = 0 and inactive = 0 and client_id = ?", $_POST['email_address'], $GLOBALS['gClientId']);
			if ($resultSet['row_count'] != 1) {
				$returnArray['error_message'] = ($resultSet['row_count'] ? getSystemMessage("multiple_users", "More than one user matches criteria") : getSystemMessage("invalid_user", "User not found"));
				break;
			}
			$emailId = getFieldFromId("email_id", "emails", "email_code", "FORGOT_USER_NAME",  "inactive = 0");
			$subject = "Username";
			$body = "Your username is %user_name%.";
			$row = getNextRow($resultSet);
			$substitutions = array("user_name" => $row['user_name']);
			$sendResponse = sendEmail(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "email_addresses" => $_POST['email_address'], "substitutions" => $substitutions, "send_immediately" => true, "contact_id" => $row['contact_id']));
			if ($sendResponse !== true) {
				$returnArray['error_message'] = "Unable to send email: " . $sendResponse;
			}
			break;

		case "get_user_security_question":
			if (empty($_POST['user_name'])) {
				$returnArray['error_message'] = getSystemMessage("invalid_user_name", "Invalid User Name");
				break;
			}
			$resultSet = executeQuery("select * from users where user_name = ? and administrator_flag = 0 and inactive = 0 and " .
				"security_question_id is not null and client_id = ?", $_POST['user_name'], $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$returnArray['user_id'] = $row['user_id'];
				$returnArray['security_question_id'] = $row['security_question_id'];
				$returnArray['security_question'] = getFieldFromId("security_question", "security_questions", "security_question_id", $row['security_question_id']);
			} else {
				$returnArray['error_message'] = getSystemMessage("security_question_not_found", "User or security question not found");
			}
			break;

		case "check_security_answer":
			if (empty($_POST['user_id']) && !empty($_POST['user_name'])) {
				$_POST['user_id'] = getFieldFromId("user_id", "users", "user_name", $_POST['user_name'], "inactive = 0");
			}
			if (empty($_POST['user_id']) || empty($_POST['security_question_id']) || empty($_POST['answer_text'])) {
				$returnArray['error_message'] = getSystemMessage("information_missing", "Required information is missing");
				break;
			}
			$resultSet = executeQuery("select user_id from users where user_id = ? and security_question_id = ? and answer_text = ?",
				$_POST['user_id'], $_POST['security_question_id'], $_POST['answer_text']);
			if (!$row = getNextRow($resultSet)) {
				$returnArray['error_message'] = getSystemMessage("invalid_security_info", "Invalid credentials");
			}
			break;

		case "forgot_password":
			$userRow = getRowFromId("users", "user_name", $_POST['user_name']);
			if (empty($userRow)) {
				$returnArray['error_message'] = "Invalid User Name";
				break;
			}
			executeQuery("delete from forgot_data where user_id = ? or time_requested < (now() - interval 30 minute)", $userRow['user_id']);
			$forgotKey = md5(uniqid(mt_rand(), true));
			$resultSet = executeQuery("insert into forgot_data (forgot_key,user_id,ip_address) values " .
				"(?,?,?)", array($forgotKey, $userRow['user_id'], $_SERVER['REMOTE_ADDR']));
			addSecurityLog($userRow['user_name'], "FORGOT-PASSWORD", "Forgot password email sent");
			$body = "<p>Click on the following link to reset your password.</p><p><a href='" .
				"https://" . $_SERVER['HTTP_HOST'] . "/" . (empty($userRow['administrator_flag']) ? "reset-password" : "resetpassword.php") . "?key=" . $forgotKey . "'>" .
				"https://" . $_SERVER['HTTP_HOST'] . "/" . (empty($userRow['administrator_flag']) ? "reset-password" : "resetpassword.php") . "?key=" . $forgotKey . "</a></p>";
			$emailAddresses = array(getFieldFromId("email_address", "contacts", "contact_id", $userRow['contact_id'], "client_id is not null"));
			$sendResponse = sendEmail(array("subject" => "Reset", "body" => $body, "email_addresses" => $emailAddresses, "send_immediately" => true));
			if ($sendResponse !== true) {
				$returnArray['error_message'] = "Unable to send email";
			}
			break;

		case "reset_password":
			if (empty($_POST['user_id']) || empty($_POST['security_question_id']) || empty($_POST['answer_text']) || empty($_POST['new_password'])) {
				$returnArray['error_message'] = getSystemMessage("information_missing", "Required information is missing");
				break;
			}
			$resultSet = executeQuery("select * from users where user_id = ? and superuser_flag = 0 and administrator_flag = 0 and inactive = 0 and " .
				"security_question_id = ? and answer_text = ? and client_id = ?", $_POST['user_id'], $_POST['security_question_id'],
				$_POST['answer_text'], $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
                if(startsWith($row['password'], "SSO_")) {
                    $returnArray['error_message'] = "This user login is handled by SSO. Password reset not permitted.";
                    break;
                }
				$passwordSalt = getRandomString(64);
				$resultSet = executeQuery("update users set password_salt = ?,password = ? where user_id = ?",
					$passwordSalt, hash("sha256", $_POST['user_id'] . $passwordSalt . $_POST['new_password']), $_POST['user_id']);
				addSecurityLog($row['user_name'], "API-PASSWORD-RESET", 'Forgot Password Reset from API');
				executeQuery("delete from api_sessions where device_id <=> ? and api_app_id = ?", $deviceId, $apiAppRow['api_app_id']);
				while (true) {
					do {
						$sessionIdentifier = getRandomString();
						$apiSessionId = getFieldFromId("api_session_id", "api_sessions", "session_identifier", $sessionIdentifier);
					} while ($apiSessionId);
					$resultSet = executeQuery("insert into api_sessions (device_id,session_identifier,api_app_id,user_id,last_used) values (?,?,?,?,now())",
						$deviceId, $sessionIdentifier, $apiAppRow['api_app_id'], $_POST['user_id']);
					if ($resultSet['sql_error_number'] != 1062) {
						break;
					}
				}
				$returnArray['session_identifier'] = $sessionIdentifier;
				$returnArray['user_id'] = $_POST['user_id'];
			} else {
				$returnArray['error_message'] = getSystemMessage("answer_text_not_found", "Try your answer again");
			}
			break;

		case "get_security_questions":
			$securityQuestions = array();
			$resultSet = executeQuery("select security_question_id,security_question from security_questions where inactive = 0 and internal_use_only = 0 order by sort_order");
			while ($row = getNextRow($resultSet)) {
				$securityQuestions[] = $row;
			}
			$returnArray['security_questions'] = $securityQuestions;
			break;

        case "get_other_user_profile":
            $userRow = getRowFromId("users", "user_id", $_POST['user_id']);
            if(empty($userRow)) {
                $userRow = getRowFromId("users", "user_name", $_POST['user_name']);
            }
            if(empty($userRow)) {
                $returnArray['error_message'] = "Invalid User";
                break;
            }
            // intentional fall-through
		case "get_user_profile":
            $userRow = $userRow ?: $GLOBALS['gUserRow'];
			$resultSet = executeQuery("select first_name,last_name,business_name,address_1,address_2,city,state,postal_code,country_id,email_address,image_id from contacts where contact_id = ?", $userRow['contact_id']);
			if (!$userProfileRow = getNextRow($resultSet)) {
				$returnArray['error_message'] = getSystemMessage("invalid_user", "Invalid User");
				break;
			}
            $userProfileRow['user_id'] = $userRow['user_id'];
			$userProfileRow['user_type_id'] = $userRow['user_type_id'];
			$userProfileRow['user_name'] = $userRow['user_name'];
			$userProfileRow['user_type'] = getFieldFromId('description', 'user_types', 'user_type_id', $userRow['user_type_id']);
			$userProfileRow['phone_numbers'] = array();
			$userProfileRow['image_url'] = getImageFilename($userProfileRow['image_id']);
			$userProfileRow['contact_id'] = $userRow['contact_id'];
			$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $userRow['contact_id']);
			while ($row = getNextRow($resultSet)) {
				$userProfileRow['phone_numbers'][] = array("description" => $row['description'], "phone_number" => $row['phone_number']);
			}
			$returnArray['user_profile'] = $userProfileRow;
			break;

        case "update_other_user":
            $userId = getFieldFromId("user_id", "users", "user_id", $_POST['user_id']);
            if(empty($userId)) {
                $userId = getFieldFromId("user_id", "users", "user_name", $_POST['user_name']);
            }
            if(empty($userId)) {
                $returnArray['error_message'] = "Invalid user ID";
                break;
            }
            $_POST['user_name'] = $_POST['new_user_name'];
            $contactId = getFieldFromId("contact_id", "users", "user_id", $userId);
            // intentional fall-through
        case "update_user":
			$GLOBALS['gPrimaryDatabase']->startTransaction();
            $userId = $userId ?: $GLOBALS['gUserId'];
            $contactId = $contactId ?: $GLOBALS['gUserRow']['contact_id'];
			if (!empty($_POST['country_id'])) {
				$countryId = getFieldFromId("country_id", "countries", "country_id", $_POST['country_id']);
			}
			if (empty($countryId)) {
				$_POST['country_id'] = 1000;
			}
			$fieldArray = array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address");
			$queryString = "";
			$queryParameters = array();
			foreach ($fieldArray as $fieldName) {
				if (array_key_exists($fieldName, $_POST)) {
					$queryString .= (empty($queryString) ? "" : ", ") . $fieldName . " = ?";
					$queryParameters[] = $_POST[$fieldName];
				}
			}
			if (!empty($queryString)) {
				$queryParameters[] = $contactId;
				$resultSet = executeQuery("update contacts set " . $queryString . " where contact_id = ?", $queryParameters);
				if ($resultSet['sql_error']) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("error_updating_user", "Error updating user");
					break;
				}
			}
			$phoneNumbers = array();
			if (array_key_exists("phone_number", $_POST)) {
				$phoneNumbers[] = array("phone_number" => $_POST['phone_number'], "description" => $_POST['description']);
			}
			if (array_key_exists("phone_numbers", $_POST) && is_array($_POST['phone_numbers'])) {
				foreach ($_POST['phone_numbers'] as $phoneInfo) {
					$phoneNumbers[] = array("phone_number" => $phoneInfo['phone_number'], "description" => $phoneInfo['description']);
				}
			}
			foreach ($phoneNumbers as $phoneInfo) {
				if (empty($phoneInfo['phone_number'])) {
					if (!empty($phoneInfo['description'])) {
						executeQuery("delete from phone_numbers where description = ? and contact_id = ?", $phoneInfo['description'], $contactId);
					}
					continue;
				}
				if ($_POST['country_id'] == 1000 || $_POST['country_id'] == 1001) {
					$phoneInfo['phone_number'] = formatPhoneNumber($phoneInfo['phone_number']);
				}
				$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and phone_number = ? and description <=> ?",
					$contactId, $phoneInfo['phone_number'], $phoneInfo['description']);
				if ($resultSet['row_count'] == 0) {
					$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description <=> ?",
                        $contactId, $phoneInfo['description']);
					if ($row = getNextRow($resultSet)) {
						$resultSet = executeQuery("update phone_numbers set phone_number = ?, description = ? where phone_number_id = ?",
							$phoneInfo['phone_number'], $phoneInfo['description'], $row['phone_number_id']);
					} else {
						$resultSet = executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)",
                            $contactId, $phoneInfo['phone_number'], $phoneInfo['description']);
					}
				}
			}
			if (!empty($_POST['user_type_id'])) {
				$userTypeId = getFieldFromId('user_type_id', 'user_types', 'user_type_id', $_POST['user_type_id'], 'client_id = ?', $GLOBALS['gClientId']);
				if (!empty($userTypeId)) {
					$dataTable = new DataTable("users");
					$dataTable->setSaveOnlyPresent(true);
					if (!$dataTable->saveRecord(array("name_values" => array("user_type_id" => $userTypeId), "primary_id" => $userId))) {
						$returnArray['error_message'] = getSystemMessage("update_user_error", "Error Updating User");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						break;
					}
				}
			}
			if (!empty($_POST['security_question_id']) && !empty($_POST['answer_text'])) {
				$securityQuestionId = getFieldFromId("security_question_id", "security_questions", "security_question_id", $_POST['security_question_id']);
				if (!empty($securityQuestionId)) {
					$resultSet = executeQuery("update users set security_question_id = ?, answer_text = ? where user_id = ?", $securityQuestionId, $_POST['answer_text'], $userId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("update_user_error", "Error Updating User");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						break;
					}
				}
			}
			if (!empty($_POST['user_name'])) {
				$usedUserId = getFieldFromId("user_id", "users", "user_name", $_POST['user_name'], "user_id <> ?", $userId);
				if (!empty($usedUserId)) {
					$returnArray['error_message'] = "Username already exists";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					break;
				}
				$resultSet = executeQuery("update users set user_name = ? where user_id = ?", $_POST['user_name'], $userId);
			}
			$imageId = "";
			if (array_key_exists("image_file", $_FILES) && !empty($_FILES['image_file']['tmp_name'])) {
				$imageId = createImage("image_file", array("image_id" => $GLOBALS['gUserRow']['image_id']));
				if ($imageId === false) {
					$returnArray['error_message'] = getSystemMessage("write_image_error", "Error writing image");
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					break;
				}
				executeQuery("update contacts set image_id = ? where contact_id = ?", $imageId, $contactId);
			}
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			break;

		case "update_password":
			if (empty($_POST['password']) || empty($_POST['new_password'])) {
				$returnArray['error_message'] = getSystemMessage("information_missing", "Required information is missing");
				break;
			}
			$passwordSalt = getFieldFromId("password_salt", "users", "user_id", $GLOBALS['gUserId']);
			if ($GLOBALS['gUserRow']['password'] != hash("sha256", $GLOBALS['gUserId'] . $passwordSalt . $_POST['password']) &&
				$GLOBALS['gUserRow']['password'] != md5($GLOBALS['gUserId'] . $passwordSalt . $_POST['password'])) {
				$returnArray['error_message'] = getSystemMessage("invalid_login", "Invalid username/password, try again.");
				break;
			}
			$resultSet = executeQuery("select * from users where inactive = 0 and user_id = ?", $GLOBALS['gUserId']);
			if ($row = getNextRow($resultSet)) {
				$passwordSalt = getRandomString(64);
				$resultSet = executeQuery("update users set password_salt = ?,password = ? where user_id = ?", $passwordSalt, hash("sha256", $GLOBALS['gUserId'] . $passwordSalt . $_POST['new_password']), $GLOBALS['gUserId']);
			} else {
				$returnArray['error_message'] = getSystemMessage("invalid_login", "Invalid username/password, try again.");
			}
			break;

		case "check_user_name":
			if (empty($_POST['user_name'])) {
				$returnArray['error_message'] = getSystemMessage("invalid_user_name", "Invalid Username");
				break;
			}
			$userId = getFieldFromId("user_id", "users", "user_name", $_POST['user_name'], "client_id = ?", $GLOBALS['gClientId']);
			if (empty($userId)) {
				$returnArray['result'] = "AVAILABLE";
			} else {
				$returnArray['result'] = "IN_USE";
			}
			break;

		case "get_user_types":
			$userTypes = array();
			$resultSet = executeQuery("select * from user_types where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description",
				$GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$userTypes[$row['user_type_id']] = $row['description'];
			}
			$returnArray['user_types'] = $userTypes;
			break;

		case "get_countries":
			$countries = array();
			$resultSet = executeQuery("select * from countries order by country_name");
			while ($row = getNextRow($resultSet)) {
				$countries[$row['country_id']] = $row['country_name'];
			}
			$returnArray['countries'] = $countries;
			break;

		case "create_user":
			$countryId = getFieldFromId("country_id", "countries", "country_id", $_POST['country_id']);
			if (empty($countryId)) {
				$countryId = 1000;
			}
			if (empty($_POST['email_address']) || empty($_POST['user_name']) || (empty($_POST['password']) && empty($_POST['send_notification']))) {
				$returnArray['error_message'] = getSystemMessage("information_missing", "Required information is missing");
				break;
			}
			$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_id", $_POST['user_type_id'], "client_id = ?", $GLOBALS['gClientId']);
			if (empty($userTypeId) && !empty($_POST['user_type_id'])) {
				$returnArray['error_message'] = getSystemMessage("invalid_user_type", "Invalid User type");
				break;
			}

			$securityQuestionId = getFieldFromId("security_question_id", "security_questions", "security_question_id", $_POST['security_question_id']);
			$userId = getFieldFromId("user_id", "users", "user_name", $_POST['user_name'], "contact_id in (select contact_id from contacts where email_address = ?) and client_id = ?", $_POST['email_address'], $GLOBALS['gClientId']);
			if (!empty($userId)) {
				$returnArray = array("result" => "EXISTS");
				$returnArray['error_message'] = getSystemMessage("user_exists", "User already exists");
				$returnArray['user_id'] = $userId;
				break;
			}
			$userId = getFieldFromId("user_id", "users", "user_name", $_POST['user_name'], "client_id = ?", $GLOBALS['gClientId']);
			if (!empty($userId)) {
				$returnArray['error_message'] = getSystemMessage("user_name_taken", "Username already in use");
				break;
			}
			$contactTypeId = getFieldFromId("contact_type_id", "user_types", "user_type_id", $userTypeId);
			$GLOBALS['gPrimaryDatabase']->startTransaction();
			$sourceId = getFieldFromId("source_id", "sources", "source_code", $_POST['source_id'], "inactive = 0");
			if (empty($sourceId)) {
				$sourceId = getFieldFromId("source_id", "sources", "source_code", $_POST['source_code'], "inactive = 0");
			}
			if (empty($sourceId)) {
				$sourceId = getFieldFromId("source_id", "sources", "source_code", $_POST['api_app_code'], "inactive = 0");
			}
			$contactDataTable = new DataTable("contacts");
			if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
				"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
				"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $countryId, "source_id" => $sourceId)))) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = getSystemMessage("error_creating_contact", "Error creating contact");
				break;
			}
			if (!empty($_POST['create_company'])) {
				if (empty($_POST['company_type_id']) && !empty($_POST['company_type_code'])) {
					$_POST['company_type_id'] = getFieldFromId("company_type_id", "company_types", "company_type_code", $_POST['company_type_code']);
				}
				$resultSet = executeQuery("insert into companies (contact_id,company_type_id) values (?,?)", $contactId, $_POST['company_type_id']);
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("error_creating_customer", "Error creating customer");
					break;
				}
			}
			$passwordSalt = getRandomString(64);

			// allows us to store user info while waiting for email verification loop
			$inactive = 0;
			if (!empty($_POST['not_verified'])) {
				$inactive = 1;
				$returnArray['token'] = $passwordSalt;
			}

			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($_POST['user_name']), "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
			if (!empty($checkUserId)) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = getSystemMessage("error_creating_user", "Error creating user");
				break;
			}
			if (!empty($_POST['send_notification'])) {
				$_POST['password'] = getRandomString(24);
			}

			$usersTable = new DataTable("users");
			if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => strtolower($_POST['user_name']),
				"password_salt" => $passwordSalt, "password" => $passwordSalt, "inactive" => (empty($_POST['send_notification']) ? "0" : "1"),
				"date_created" => date("Y-m-d H:i:s"))))) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = $usersTable->getErrorMessage();
				break;
			}
			$resultSet = executeQuery("update users set password = ? where user_id = ?", hash("sha256", $userId . $passwordSalt . $_POST['password']), $userId);
			if (!empty($resultSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = getSystemMessage("error_creating_user", "Error creating user");
				break;
			}
			$phoneNumbers = array();
			if (array_key_exists("phone_number", $_POST)) {
				$phoneNumbers[] = array("phone_number" => $_POST['phone_number'], "description" => $_POST['description']);
			}
			if (array_key_exists("phone_numbers", $_POST) && is_array($_POST['phone_numbers'])) {
				foreach ($_POST['phone_numbers'] as $phoneInfo) {
					$phoneNumbers[] = array("phone_number" => $phoneInfo['phone_number'], "description" => $phoneInfo['description']);
				}
			}
			foreach ($phoneNumbers as $phoneInfo) {
				if (empty($phoneInfo['phone_number'])) {
					continue;
				}
				if ($_POST['country_id'] == 1000 || $_POST['country_id'] == 1001) {
					$phoneInfo['phone_number'] = formatPhoneNumber($phoneInfo['phone_number']);
				}
				$resultSet = executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)",
					$contactId, $phoneInfo['phone_number'], $phoneInfo['description']);
			}
			$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", (empty($_POST['task_source_code']) ? $_POST['source_code'] : $_POST['task_source_code']), "inactive = 0");
			if (!empty($contactId) && !empty($taskSourceId)) {
				executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
					"(?,?,'API Create User',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
			}

			foreach ($_POST as $fieldName => $fieldValue) {
				if (empty($fieldValue)) {
					continue;
				}
				if (substr($fieldName, 0, strlen("contact_identifier-")) == "contact_identifier-") {
					$contactIdentifierTypeCode = substr($fieldName, strlen("contact_identifier-"));
					$contactIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code", $contactIdentifierTypeCode);
					if (empty($contactIdentifierTypeId)) {
						continue;
					}
					$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_id", $contactId, "contact_identifier_type_id = ?", $contactIdentifierTypeId);
					$dataTable = new DataTable("contact_identifier");
					$dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "contact_identifier_type_id" => $contactIdentifierTypeId, "identifier_value" => $fieldValue), "primary_id" => $contactIdentifierId));
				}
				if (substr($fieldName, 0, strlen("custom_field-")) == "custom_field-") {
					$customFieldCode = substr($fieldName, strlen("custom_field-"));
					$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode);
					if (empty($customFieldId)) {
						continue;
					}
					CustomField::setCustomFieldData($contactId, $customFieldCode, $fieldValue);
				}
			}

			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			if (!empty($_POST['send_notification'])) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "API_CREATED_USER",  "inactive = 0");
				$body = "";
				$subject = "";
				if (empty($emailId)) {
					$body = "<p>Your account has been created. Your user name is '%user_name%'. Your temporary password is '%password%'.</p>";
					$subject = "Your account";
				}
				$substitutions = array("password" => $_POST['password'], "user_name" => $_POST['user_name']);
				sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "email_address" => $_POST['email_address'], "substitutions" => $substitutions, "contact_id" => $contactId, "email_code" => "API_CREATED_USER"));
			}

		case "login":
			if (empty($_POST['user_name']) || empty($_POST['password'])) {
				$returnArray['error_message'] = getSystemMessage("invalid_login", "Invalid username/password, try again.");
				break;
			}
			$loggedInUserId = false;

			if (!$loggedInUserId = checkUserCredentials(strtolower($_POST['user_name']), $_POST['password'])) {
				$returnArray['error_message'] = getSystemMessage("invalid_login", "Invalid username/password, try again.");
				break;
			}

			if (getFieldFromId("locked", "users", "user_id", $loggedInUserId) == 1) {
				$returnArray['error_message'] = getSystemMessage("locked", "Your account has been locked due to too many unsuccessful login attempts. Contact customer service to unlock your account.");
				break;
			}

			login($loggedInUserId, false);
			$returnArray['user_id'] = $loggedInUserId;
			$returnArray['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
			$returnArray['client_id'] = $GLOBALS['gClientId'];
			if (!empty($deviceId) && !empty($apiAppRow['api_app_id'])) {
				executeQuery("delete from api_sessions where device_id <=> ? and api_app_id = ?", $deviceId, $apiAppRow['api_app_id']);
			}
			while (true) {
				do {
					$sessionIdentifier = getRandomString();
					$apiSessionId = getFieldFromId("api_session_id", "api_sessions", "session_identifier", $sessionIdentifier);
				} while ($apiSessionId);
				$resultSet = executeQuery("insert into api_sessions (device_id,session_identifier,api_app_id,user_id,last_used) values (?,?,?,?,now())",
					$deviceId, $sessionIdentifier, $apiAppRow['api_app_id'], $loggedInUserId);
				if ($resultSet['sql_error_number'] != 1062) {
					break;
				}
			}
			if (!empty($resultSet['sql_error'])) {
				$returnArray['error_message'] = getSystemMessage("error_creating_session", "Error creating session");
				break;
			}
			$taskSourceRow = getRowFromId("task_sources", "task_source_code", (empty($_POST['task_source_code']) ? $_POST['source_code'] : $_POST['task_source_code']), "inactive = 0");
			if (!empty($GLOBALS['gUserRow']['contact_id']) && !empty($taskSourceRow['task_source_id'])) {
				$taskId = getFieldFromId("task_id", "tasks", "contact_id", $GLOBALS['gUserRow']['contact_id'], "task_source_id = ?", $taskSourceRow['task_source_id']);
				if (empty($taskId) || empty($taskSourceRow['allow_only_one'])) {
					executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
						"(?,?,'API Login Touchpoint Logged',now(),1,?)", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['contact_id'], $taskSourceRow['task_source_id']);
				}
			}
			$returnArray['session_identifier'] = $sessionIdentifier;
			break;

		case "log_touchpoint":
			$taskSourceRow = getRowFromId("task_sources", "task_source_code", (empty($_POST['task_source_code']) ? $_POST['source_code'] : $_POST['task_source_code']), "inactive = 0");
			if (!empty($GLOBALS['gUserRow']['contact_id']) && !empty($taskSourceRow['task_source_id'])) {
				$taskId = getFieldFromId("task_id", "tasks", "contact_id", $GLOBALS['gUserRow']['contact_id'], "task_source_id = ?", $taskSourceRow['task_source_id']);
				if (empty($taskId) || empty($taskSourceRow['allow_only_one'])) {
					executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
						"(?,?,'API Touchpoint Logged',now(),1,?)", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['contact_id'], $taskSourceRow['task_source_id']);
				}
			} else {
				$returnArray['error_message'] = getSystemMessage("invalid_source", "Invalid Source Code");
			}
			break;

		case "create_error_log":
			if (empty($_POST['error_message'])) {
				$returnArray['error_message'] = getSystemMessage("information_missing", "Required information is missing");
				break;
			}
			$GLOBALS['gPrimaryDatabase']->logError("API Error: " . $_POST['error_message'], $_POST['query_text']);
			break;

		case "get_device_custom_fields":
			$customFields = CustomField::getCustomFields("devices");
			$returnArray['custom_fields'] = $customFields;
			$returnArray['custom_field_data'] = array();
			foreach ($customFields as $thisCustomField) {
				$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
				$customFieldData = $customField->getRecord($_POST['device_identifier']);
				$returnArray['custom_field_data'][$thisCustomField['custom_field_id']] = $customFieldData;
			}
			break;

		case "update_device_custom_fields":
			$customFields = CustomField::getCustomFields("devices");
			foreach ($customFields as $thisCustomField) {
				if (!array_key_exists("custom_field_id_" . $thisCustomField['custom_field_id'], $_POST)) {
					continue;
				}
				$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
				if (!$customField->saveData(array("primary_id" => $_POST['device_identifier'],
					"custom_field_id_" . $thisCustomField['custom_field_id'] => $_POST['custom_field_id_' . $thisCustomField['custom_field_id']]))) {
					$returnArray['error_message'] = $customField->getErrorMessage();
					break;
				}
			}
			break;

		case "get_ffl_records":
			$searchParameters = array();
			if (!empty($_POST['postal_code'])) {
				$searchParameters['postal_code'] = $_POST['postal_code'];
			}
			if (!empty($_POST['licensee_name'])) {
				$searchParameters['licensee_name'] = $_POST['licensee_name'] . "%";
			}
			if (!empty($_POST['business_name'])) {
				$searchParameters['business_name'] = $_POST['business_name'] . "%";
			}
			$fflRecords = FFL::getFFLRecords($searchParameters,array(),false);
			$returnArray['field_names'] = $fflRecords['field_names'];
			$returnArray['federal_firearms_licensees'] = $fflRecords['ffl_array'];
			break;

		case "get_ffl_dealer":
			$fflRow = (new FFL($_POST))->getFFLRow();
			if ($fflRow) {
				$returnArray['ffl_dealer'] = $fflRow;
			} else {
				$returnArray['error_message'] = "FFL dealer not found";
			}
			break;

		case "add_ffl_record":
		case "update_ffl_record":
			if (getPreference("CENTRALIZED_FFL_STORAGE")) {
				$returnArray['error_message'] = "Centralized FFL Storage enabled. Unable to update or add FFL Records.";
				break;
			}
			if (!empty($_POST['license_number'])) {
				$licenseNumber = $_POST['license_number'];
				if (strlen($licenseNumber) == 15) {
					$licenseNumber = substr($licenseNumber, 0, 1) . "-" . substr($licenseNumber, 1, 2) . "-" . substr($licenseNumber, 3, 3) . "-" . substr($licenseNumber, 6, 2) . "-" . substr($licenseNumber, 8, 2) . "-" . substr($licenseNumber, 10, 5);
				}
				if (strlen($licenseNumber) != 20) {
					$returnArray['error_message'] = "Invalid license number";
					break;
				}
				$_POST['license_lookup'] = substr($licenseNumber, 0, 5) . substr($licenseNumber, 15, 5);
				$_POST['license_number'] = $licenseNumber;
			}

			$GLOBALS['gPrimaryDatabase']->startTransaction();

			$federalFirearmsLicenseeRow = (new FFL($_POST))->getFFLRow();
			if (empty($federalFirearmsLicenseeRow)) {
				if (empty($_POST['license_number'])) {
					$returnArray['error_message'] = "Invalid license number";
					break;
				}
				$_POST['date_created'] = date("m/d/Y");
				$_POST['country_id'] = "1000";
				$address = array("address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'], "postal_code" => $_POST['postal_code']);
				$geocode = getAddressGeocode($address);
				$_POST['latitude'] = $geocode['latitude'];
				$_POST['longitude'] = $geocode['longitude'];

				$dataTable = new DataTable("contacts");
				$contactId = $dataTable->saveRecord(array("name_values" => $_POST));
				$_POST['contact_id'] = $contactId;

				if (!empty($_POST['mailing_address_1']) || !empty($_POST['mailing_city'])) {
					$dataTable = new DataTable("addresses");
					$addressId = $dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "address_label" => "Mailing", "address_1" => $_POST['mailing_address_1'], "city" => $_POST['mailing_city'], "state" => $_POST['mailing_state'], "postal_code" => $_POST['mailing_postal_code'], "country_id" => "1000")));
				}

				if (!empty($_POST['phone_number'])) {
					$dataTable = new DataTable("phone_numbers");
					$phoneNumberId = $dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "phone_number" => formatPhoneNumber($_POST['phone_number']), "description" => "Store")));
				}

				$dataTable = new DataTable("federal_firearms_licensees");
				$fflId = $dataTable->saveRecord(array("name_values" => $_POST));
				if (!$fflId) {
					$returnArray['error_message'] = getSystemMessage("basic", $dataTable->getErrorMessage());
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					break;
				}
			} else {
				$dataTable = new DataTable("federal_firearms_licensees");
				$fflId = $dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => $federalFirearmsLicenseeRow['federal_firearms_licensee_id']));
				if (!$fflId) {
					$returnArray['error_message'] = getSystemMessage("basic", $dataTable->getErrorMessage());
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					break;
				}

				$dataTable = new DataTable("contacts");
				$contactId = $dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => $federalFirearmsLicenseeRow['contact_id']));
				if (!$contactId) {
					$returnArray['error_message'] = getSystemMessage("basic", $dataTable->getErrorMessage());
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					break;
				}

				if (array_key_exists("phone_number", $_POST)) {
					$dataTable = new DataTable("phone_numbers");
					$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "description", "Store", "contact_id = ?", $contactId);
					$dataTable->setPrimaryId($phoneNumberId);
					if (empty($_POST['phone_number'])) {
						$dataTable->deleteRecord();
					} else {
						$dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "phone_number" => $_POST['phone_number'], "description" => "Store")));
					}
				}

				if (array_key_exists("mailing_address_1", $_POST) || array_key_exists("mailing_postal_code", $_POST)) {
					$dataTable = new DataTable("addresses");
					$addressId = getFieldFromId("address_id", "addresses", "address_label", "Mailing", "contact_id = ?", $contactId);
					$dataTable->setPrimaryId($addressId);
					if (empty($_POST['mailing_address_1']) && empty($_POST['mailing_postal_code'])) {
						$dataTable->deleteRecord();
					} else {
						$dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "address_label" => "Mailing", "address_1" => $_POST['mailing_address_1'], "city" => $_POST['mailing_city'], "state" => $_POST['mailing_state'], "postal_code" => $_POST['mailing_postal_code'], "country_id" => "1000")));
					}
				}
			}
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			$returnArray['federal_firearms_licensee_id'] = $fflId;
			break;

		case "create_invoice":
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
			if (empty($contactId)) {
				$returnArray['error_message'] = "Invalid Contact";
				break;
			}
			$billingTermsId = getFieldFromId("billing_terms_id", "billing_terms", "billing_terms_code", $_POST['billing_terms_code']);
			if (empty($billingTermsId) && !empty($_POST['billing_terms_code'])) {
				$returnArray['error_message'] = "Invalid Billing Terms";
				break;
			}
			$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "invoice_type_code", $_POST['invoice_type_code']);
			if (empty($invoiceTypeId) && !empty($_POST['invoice_type_code'])) {
				$returnArray['error_message'] = "Invalid Invoice Type";
				break;
			}
			$GLOBALS['gPrimaryDatabase']->startTransaction();
			$insertSet = executeQuery("insert into invoices (client_id, invoice_number, contact_id, invoice_type_id, invoice_date, date_due, date_completed, billing_terms_id, purchase_order_number, notes) values(?,?,?,?,?,?,?,?,?,?)",
				$GLOBALS['gClientId'], $_POST['invoice_number'], $contactId, $invoiceTypeId, makeDateParameter($_POST['invoice_date']), makeDateParameter($_POST['date_due']),
				makeDateParameter($_POST['date_completed']), $billingTermsId, $_POST['purchase_order_number'], $_POST['notes']);
			if (!empty($insertSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
				break;
			}
			$invoiceId = $insertSet['insert_id'];
			$count = 0;
			foreach ($_POST['invoice_details'] as $thisDetail) {
				if (empty($thisDetail['detail_date'])) {
					$detailDate = $_POST['invoice_date'];
				} else {
					$detailDate = $thisDetail['detail_date'];
				}
				if (empty($thisDetail['description']) || strlen($thisDetail['amount']) == 0 || strlen($thisDetail['unit_price']) == 0 || !is_numeric($thisDetail['amount']) || !is_numeric($thisDetail['unit_price'])) {
					$returnArray['error_message'] = "Description, amount, & unit price are required for detail lines";
					break 2;
				}
				$unitId = getFieldFromId("unit_id", "units", "unit_code", $thisDetail['unit_code']);
				if (empty($unitId) && !empty($thisDetail['unit_code'])) {
					$returnArray['error_message'] = "Invalid Unit Code: " . $thisDetail['unit_code'];
					break 2;
				}
				$insertSet = executeQuery("insert into invoice_details (invoice_id, detail_date, description, amount, unit_id, unit_price) values(?,?,?,?,?, ?)",
					$invoiceId, makeDateParameter($detailDate), $thisDetail['description'], $thisDetail['amount'], $unitId, $thisDetail['unit_price']);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
					break 2;
				}
				$count++;
			}
			if ($count == 0) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "At least one detail line is required.";
				break;
			}
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			$returnArray['invoice_id'] = $invoiceId;
			break;

		case "import_invoices":
			if (!array_key_exists("csv_file", $_FILES)) {
				$returnArray['error_message'] = "No File uploaded";
				break;
			}

			$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
			$hashCode = md5($fieldValue);
			$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
			if (!empty($csvImportId)) {
				$returnArray['error_message'] = "This file has already been imported . ";
				break;
			}
			$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

			$allValidFields = array("invoice_date", "invoice_number", "date_due", "contact_id", "old_contact_id", "date_completed", "billing_terms_code", "description", "amount", "unit_code", "unit_price");
			$requiredFields = array("invoice_number", "amount", "description");
			$numericFields = array("amount", "unit_price");

			$fieldNames = array();
			$importRecords = array();
			$count = 0;
			$errorMessage = "";
			while ($csvData = fgetcsv($openFile)) {
				if (empty($csvData)) {
					continue;
				}
				if ($count == 0) {
					foreach ($csvData as $thisName) {
						$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
					}
					$invalidFields = "";
					foreach ($fieldNames as $fieldName) {
						if (!in_array($fieldName, $allValidFields)) {
							$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
						}
					}
					if (!empty($invalidFields)) {
						$errorMessage .= "Invalid fields in CSV: " . $invalidFields . "\n";
					}
				} else {
					$fieldData = array();
					foreach ($csvData as $index => $thisData) {
						$thisFieldName = $fieldNames[$index];
						$fieldData[$thisFieldName] = trim($thisData);
					}
					$importRecords[] = $fieldData;
				}
				$count++;
			}
			fclose($openFile);
			$billingTerms = array();
			$invoiceTypes = array();
			$units = array();
			foreach ($importRecords as $index => $thisRecord) {

				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
				if (empty($contactId) && !empty($thisRecord['contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
				}
				if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
				}
				if (empty($contactId)) {
					$errorMessage .= "Line " . ($index + 2) . ": Contact not found\n";
				}
				$missingFields = "";

				foreach ($requiredFields as $thisField) {
					if (empty($thisRecord[$thisField])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
					}
				}
				if (!empty($missingFields)) {
					$errorMessage .= "Line " . ($index + 2) . " has missing fields: " . $missingFields . "\n";
				}

				foreach ($numericFields as $fieldName) {
					if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName] + 0) && !is_numeric($thisRecord[$fieldName] + 0)) {
						$errorMessage .= "Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName] . "\n";
					}
				}
				if (!empty($thisRecord['billing_terms_code'])) {
					if (!array_key_exists($thisRecord['billing_terms_code'], $billingTerms)) {
						$billingTerms[$thisRecord['billing_terms_code']] = "";
					}
				}
				if (!empty($thisRecord['invoice_type_code'])) {
					if (!array_key_exists($thisRecord['invoice_type_code'], $invoiceTypes)) {
						$invoiceTypes[$thisRecord['invoice_type_code']] = "";
					}
				}
				if (!empty($thisRecord['unit_code'])) {
					if (!array_key_exists($thisRecord['unit_code'], $units)) {
						$units[$thisRecord['unit_code']] = "";
					}
				}
			}
			foreach ($billingTerms as $thisType => $billingTermsId) {
				$billingTermsId = getFieldFromId("billing_terms_id", "billing_terms", "billing_terms_code", makeCode($thisType));
				if (empty($billingTermsId)) {
					$billingTermsId = getFieldFromId("billing_terms_id", "billing_terms", "description", $thisType);
				}
				if (empty($billingTermsId)) {
					$errorMessage .= "Invalid Billing Terms: " . $thisType . "\n";
				} else {
					$billingTerms[$thisType] = $billingTermsId;
				}
			}
			foreach ($invoiceTypes as $thisType => $invoiceTypeId) {
				$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "invoice_type_code", makeCode($thisType));
				if (empty($invoiceTypeId)) {
					$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "description", $thisType);
				}
				if (empty($invoiceTypeId)) {
					$errorMessage .= "Invalid Invoice Type: " . $thisType . "\n";
				} else {
					$invoiceTypes[$thisType] = $invoiceTypeId;
				}
			}
			foreach ($units as $thisType => $unitId) {
				$unitId = getFieldFromId("unit_id", "units", "unit_code", makeCode($thisType));
				if (empty($unitId)) {
					$unitId = getFieldFromId("unit_id", "units", "description", $thisType);
				}
				if (empty($unitId)) {
					$errorMessage .= "Invalid Unit: " . $thisType . "\n";
				} else {
					$units[$thisType] = $unitId;
				}
			}
			if (!empty($errorMessage)) {
				$returnArray['error_message'] = $errorMessage;
				break;
			}

			$GLOBALS['gPrimaryDatabase']->startTransaction();
			$resultSet = executeQuery("insert into csv_imports(client_id, description, table_name, hash_code, time_submitted, user_id, content) values(?,?,'invoices',?,now(),?,?)",
				$GLOBALS['gClientId'], "API Import", $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
			if (!empty($resultSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
				break;
			}
			$csvImportId = $resultSet['insert_id'];

			$insertCount = 0;
			$updateCount = 0;
			foreach ($importRecords as $index => $thisRecord) {
				if (empty($thisRecord['invoice_date'])) {
					$thisRecord['invoice_date'] = date("Y-m-d");
				}
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
				if (empty($contactId) && !empty($thisRecord['contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
				}
				if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
				}
				if (empty($contactId)) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = "Line " . ($index + 2) . ": Contact not found";
					break 2;
				}

				if (!empty($thisRecord['invoice_number'])) {
					$invoiceId = getFieldFromId("invoice_id", "invoices", "contact_id", $contactId, "inactive = 0 and invoice_number = ?", $thisRecord['invoice_number']);
				} else {
					$invoiceId = getFieldFromId("invoice_id", "invoices", "contact_id", $contactId, "inactive = 0 and invoice_date = ? and date_completed <=> ? and billing_terms_id <=> ?",
						makeDateParameter($thisRecord['invoice_date']), makeDateParameter($thisRecord['date_completed']), $billingTerms[$thisRecord['billing_terms_code']]);
				}

				if (empty($invoiceId)) {
					$insertSet = executeQuery("insert into invoices (client_id, invoice_number, contact_id, invoice_type_id, invoice_date, date_due, date_completed, billing_terms_id) values(?,?,?,?,?,?,?,?)",
						$GLOBALS['gClientId'], $thisRecord['invoice_number'], $contactId, $invoiceTypes[$thisRecord['invoice_type_code']], makeDateParameter($thisRecord['invoice_date']),
						makeDateParameter($thisRecord['date_due']), makeDateParameter($thisRecord['date_completed']), $billingTerms[$thisRecord['billing_terms_code']]);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						break 2;
					}
					$invoiceId = $insertSet['insert_id'];
					$insertCount++;
				}
				if (!array_key_exists("unit_price", $thisRecord)) {
					$thisRecord['unit_price'] = $thisRecord['amount'];
					$thisRecord['amount'] = 1;
				}
				executeQuery("insert into invoice_details(invoice_id, detail_date, description, amount, unit_id, unit_price) values(?,?,?,?,?,?)",
					$invoiceId, makeDateParameter($thisRecord['invoice_date']), $thisRecord['description'], $thisRecord['amount'], $units[$thisRecord['unit_code']], $thisRecord['unit_price']);

				$insertSet = executeQuery("insert into csv_import_details(csv_import_id, primary_identifier) values(?,?)", $csvImportId, $invoiceId);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
					break 2;
				}
			}

			$GLOBALS['gPrimaryDatabase']->commitTransaction();

			$returnArray['response'] = $insertCount . " Invoices imported.\n";
			break;

		case "get_invoices":
			$startDate = (empty($_POST['start_date']) ? "" : date("Y-m-d", strtotime($_POST['start_date'])));
			$endDate = (empty($_POST['end_date']) ? "" : date("Y-m-d", strtotime($_POST['end_date'])));
			$contactId = getReadFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
			if (empty($contactId) && !empty($_POST['contact_id'])) {
				$returnArray['error_message'] = "Invalid Contact";
				break;
			}
			$whereStatement = "";
			$parameters = array($GLOBALS['gClientId']);
			if (!empty($startDate)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoices.invoice_date >= ?";
				$parameters[] = $startDate;
			}
			if (!empty($endDate)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoices.invoice_date <= ?";
				$parameters[] = $endDate;
			}
			if (!empty($contactId)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoices.contact_id = ?";
				$parameters[] = $contactId;
			}
			$resultSet = executeReadQuery("select *,(select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) as invoice_total," .
				"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) as payments_total from contacts join invoices using(contact_id) where " .
				"inactive = 0 and internal_use_only = 0 and invoices.client_id = ?" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
			$invoices = array();
			while ($row = getNextRow($resultSet)) {
				$invoices[] = $row;
			}
			$returnArray['invoices'] = $invoices;
			break;

		case "add_invoice_payment":
			$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $_POST['invoice_id'], "inactive = 0 and contact_id = ?", $_POST['contact_id']);
			if (empty($invoiceId)) {
				$returnArray['error_message'] = "Invoice Not Found";
				break;
			}
			$paymentAmount = $_POST['amount'];
			if ($paymentAmount <= 0) {
				$returnArray['error_message'] = "Only positive payment amounts are allowed";
				break;
			}
			$paymentDate = (empty($_POST['payment_date']) ? date("Y-m-d") : date("Y-m-d", strtotime($_POST['payment_date'])));
			$paymentMethodId = (empty($_POST['payment_method_id']) ? "" : getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", $_POST['payment_method_id']));
			if (!empty($_POST['payment_method_id']) && empty($paymentMethodId)) {
				$returnArray['error_message'] = "Invalid Payment Method ID";
				break;
			}
			if (empty($paymentMethodId) && !empty($_POST['payment_method_code'])) {
				$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", $_POST['payment_method_code']);
				if (empty($paymentMethodId)) {
					$returnArray['error_message'] = "Invalid Payment Method Code";
					break;
				}
			}
			$insertSet = executeQuery("insert into invoice_payments (invoice_id,payment_date,payment_method_id,amount,notes) values (?,?,?,?,?)", $invoiceId, $paymentDate, $paymentMethodId, $paymentAmount, $_POST['notes']);
			if (!empty($insertSet['sql_error'])) {
				$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				ajaxResponse($returnArray);
			}
			Invoices::postPaymentInvoiceProcessing($invoiceId);
			coreSTORE::invoicePaymentNotification($insertSet['insert_id']);
			break;

		case "get_invoice_payments":
			$startDate = (empty($_POST['start_date']) ? "" : date("Y-m-d", strtotime($_POST['start_date'])));
			$endDate = (empty($_POST['end_date']) ? "" : date("Y-m-d", strtotime($_POST['end_date'])));
			$contactId = getReadFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
			if (empty($contactId) && !empty($_POST['contact_id'])) {
				$returnArray['error_message'] = "Invalid Contact";
				break;
			}
			$invoiceId = getReadFieldFromId("invoice_id", "invoices", "invoice_id", $_POST['invoice_id'], "inactive = 0");
			if (empty($contactId) && !empty($_POST['invoice_id'])) {
				$returnArray['error_message'] = "Invalid Invoice";
				break;
			}
			$whereStatement = "";
			$parameters = array($GLOBALS['gClientId']);
			if (!empty($startDate)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoice_payments.payment_date >= ?";
				$parameters[] = $startDate;
			}
			if (!empty($endDate)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoice_payments.payment_date <= ?";
				$parameters[] = $endDate;
			}
			if (!empty($contactId)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoices.contact_id = ?";
				$parameters[] = $contactId;
			}
			if (!empty($invoiceId)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoices.invoice_id = ?";
				$parameters[] = $invoiceId;
			}
			if (!empty($_POST['invoice_number'])) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "invoices.invoice_number = ?";
				$parameters[] = $_POST['invoice_number'];
			}
			$resultSet = executeReadQuery("select *,(select description from payment_methods where payment_method_id = invoice_payments.payment_method_id) as payment_method from contacts join invoices using(contact_id) " .
				"join invoice_payments using(invoice_id) where invoices.client_id = ?" . (empty($whereStatement) ? "" : " and " . $whereStatement), $parameters);
			$invoicePayments = array();
			while ($row = getNextRow($resultSet)) {
				$invoicePayments[] = $row;
			}
			$returnArray['invoice_payments'] = $invoicePayments;
			break;

		case "get_clients":
			if ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
				$clients = array();
				$resultSet = executeReadQuery("select client_code,business_name,city," .
					"(select domain_name from domain_names where (domain_name like '%coreware.com' or domain_name like '%corefire.shop') and domain_client_id = clients.client_id limit 1) domain_name " .
					"from clients join contacts using(contact_id) where clients.inactive = 0 order by business_name");
				while ($row = getNextRow($resultSet)) {
					if (empty($row['business_name'])) {
						$row['business_name'] = ucwords(strtolower(str_replace("_", " ", $row['client_code'])));
					}
					if (empty($row['domain_name'])) {
						$row['domain_name'] = getFieldFromId("domain_name", "domain_names", "domain_client_id",
							getFieldFromId("client_id", "clients", "client_code", $row['client_code']));
					}
					$clients[] = $row;
				}
				$returnArray['clients'] = $clients;
			}
			break;
        case "get_core_data_hash":
            $hashOnly = true;
            // intentional fall through
        case "get_core_data":
            if($GLOBALS['gSystemName'] == "COREWARE" && $GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
                $filename = "{$GLOBALS['gDocumentRoot']}/cache/pagecodes.txt";
                if(!empty($hashOnly)) {
                    if(file_exists($filename)) {
                        $returnArray['core_data_hash'] = hash_file("md5", $filename);
                    } else {
                        $returnArray['error_message'] = "pagecodes file is missing.";
                    }
                } else {
                    echo file_get_contents($filename);
                    exit;
                }
            } else {
                $returnArray["error_message"] = "This API method cannot be run on this server";
            }
            break;
		case "get_distributor_accounts":
			if ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
				$distributorAccounts = array();
				$resultSet = executeReadQuery("select client_code, business_name, product_distributors.description as distributor_name, locations.description, user_name, customer_number, locations.inactive from location_credentials " .
					"join locations using (location_id) join product_distributors using (product_distributor_id) join clients using (client_id) join contacts on clients.contact_id = contacts.contact_id " .
					"where clients.inactive = 0 and clients.development = 0");
				while ($row = getNextRow($resultSet)) {
					if (empty($row['business_name'])) {
						$row['business_name'] = ucwords(strtolower(str_replace("_", " ", $row['client_code'])));
					}
					$distributorAccounts[] = $row;
				}
				$returnArray['distributor_accounts'] = $distributorAccounts;
			}
			break;
		case "get_sales_numbers":
			$fflClients = array();
			$resultSet = executeReadQuery("select client_id from product_tags where product_tag_code = 'FFL_REQUIRED' and inactive = 0 and cannot_sell = 0");
			while ($row = getNextRow($resultSet)) {
				$fflClients[] = $row['client_id'];
			}
			if (empty($fflClients)) {
				$fflClients[] = "0";
			}
			$statisticsTypes = array("total", "last_year", "ytd", "last_month", "this_month", "yesterday", "today");

			$statisticsFields = array("customers", "count", "total");
			$returnArray['ffl_sales'] = array();
			foreach ($statisticsTypes as $thisType) {
				$returnArray['ffl_sales'][$thisType] = array();
				foreach ($statisticsFields as $thisField) {
					$returnArray['ffl_sales'][$thisType][$thisField] = 0;
				}
			}

			$ignoreOrderIds = array();
			$resultSet = executeQuery("select order_id,sum(quantity * sale_price) total_amount from order_items group by order_id having total_amount > 10000000");
			while ($row = getNextRow($resultSet)) {
				$ignoreOrderIds[] = $row['order_id'];
			}

			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . "))) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ")");
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['total']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['total']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['total']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-01-01", strtotime("-1 year"));
			$endDate = date("Y-12-31 23:59:59", strtotime("-1 year"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['last_year']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['last_year']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['last_year']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-01-01");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['ytd']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['ytd']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['ytd']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d", strtotime("first day of previous month"));
			$endDate = date("Y-m-d 23:59:59", strtotime("last day of previous month"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['last_month']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['last_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['last_month']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d", strtotime("first day of this month"));
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['this_month']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['this_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['this_month']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d 00:00:00", strtotime("yesterday"));
			$endDate = date("Y-m-d 23:59:59", strtotime("yesterday"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['yesterday']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['yesterday']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['yesterday']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d 00:00:00");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['ffl_sales']['today']['count'] = $row['count(*)'];
				$returnArray['ffl_sales']['today']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['ffl_sales']['today']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$returnArray['credova_sales'] = array();
			foreach ($statisticsTypes as $thisType) {
				$returnArray['credova_sales'][$thisType] = array();
				foreach ($statisticsFields as $thisField) {
					$returnArray['credova_sales'][$thisType][$thisField] = 0;
				}
			}

			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA')");
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['total']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['total']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['total']['total'] = $row['total_amount'];
			}

			$startDate = date("Y-01-01", strtotime("-1 year"));
			$endDate = date("Y-12-31 23:59:59", strtotime("-1 year"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA') and payment_time between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['last_year']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['last_year']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['last_year']['total'] = $row['total_amount'];
			}

			$startDate = date("Y-01-01");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA') and payment_time between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['ytd']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['ytd']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['ytd']['total'] = $row['total_amount'];
			}

			$startDate = date("Y-m-d", strtotime("first day of previous month"));
			$endDate = date("Y-m-d 23:59:59", strtotime("last day of previous month"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA') and payment_time between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['last_month']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['last_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['last_month']['total'] = $row['total_amount'];
			}

			$startDate = date("Y-m-d", strtotime("first day of this month"));
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA') and payment_time between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['this_month']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['this_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['this_month']['total'] = $row['total_amount'];
			}

			$startDate = date("Y-m-d 00:00:00", strtotime("yesterday"));
			$endDate = date("Y-m-d 23:59:59", strtotime("yesterday"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA') and payment_time between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['yesterday']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['yesterday']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['yesterday']['total'] = $row['total_amount'];
			}

			$startDate = date("Y-m-d 00:00:00");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(order_payments.amount + order_payments.tax_charge + order_payments.shipping_charge + order_payments.handling_charge) as total_amount from " .
				"order_payments join orders using (order_id) where orders.deleted = 0 and order_payments.deleted = 0 and order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_code = 'CREDOVA') and payment_time between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['credova_sales']['today']['count'] = $row['count(*)'];
				$returnArray['credova_sales']['today']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['credova_sales']['today']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$returnArray['other_sales'] = array();
			foreach ($statisticsTypes as $thisType) {
				$returnArray['other_sales'][$thisType] = array();
				foreach ($statisticsFields as $thisField) {
					$returnArray['other_sales'][$thisType][$thisField] = 0;
				}
			}

			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . "))) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ")");
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['total']['count'] = $row['count(*)'];
				$returnArray['other_sales']['total']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['total']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-01-01", strtotime("-1 year"));
			$endDate = date("Y-12-31 23:59:59", strtotime("-1 year"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['last_year']['count'] = $row['count(*)'];
				$returnArray['other_sales']['last_year']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['last_year']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-01-01");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['ytd']['count'] = $row['count(*)'];
				$returnArray['other_sales']['ytd']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['ytd']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d", strtotime("first day of previous month"));
			$endDate = date("Y-m-d 23:59:59", strtotime("last day of previous month"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['last_month']['count'] = $row['count(*)'];
				$returnArray['other_sales']['last_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['last_month']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d", strtotime("first day of this month"));
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['this_month']['count'] = $row['count(*)'];
				$returnArray['other_sales']['this_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['this_month']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d", strtotime("yesterday"));
			$endDate = date("Y-m-d 23:59:59", strtotime("yesterday"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['yesterday']['count'] = $row['count(*)'];
				$returnArray['other_sales']['yesterday']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['yesterday']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$startDate = date("Y-m-d");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(tax_charge),sum(shipping_charge),sum(handling_charge)," .
				"(select sum(sale_price * quantity) from order_items where " . (empty($ignoreOrderIds) ? "" : " order_id not in (" . implode(",", $ignoreOrderIds) . ") and ") . "order_id in (select order_id from orders where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?)) as item_total from orders " .
				"where deleted = 0 and client_id not in (" . implode(", ", $fflClients) . ") and order_time between ? and ?", $startDate, $endDate, $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['other_sales']['today']['count'] = $row['count(*)'];
				$returnArray['other_sales']['today']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['other_sales']['today']['total'] = $row['sum(tax_charge)'] + $row['sum(shipping_charge)'] + $row['sum(handling_charge)'] + $row['item_total'];
			}

			$returnArray['donations'] = array();
			foreach ($statisticsTypes as $thisType) {
				$returnArray['donations'][$thisType] = array();
				foreach ($statisticsFields as $thisField) {
					$returnArray['donations'][$thisType][$thisField] = 0;
				}
			}

			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null");
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['total']['count'] = $row['count(*)'];
				$returnArray['donations']['total']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['total']['total'] = $row['sum(amount)'];
			}

			$startDate = date("Y-01-01", strtotime("-1 year"));
			$endDate = date("Y-12-31 23:59:59", strtotime("-1 year"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null and donation_date between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['last_year']['count'] = $row['count(*)'];
				$returnArray['donations']['last_year']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['last_year']['total'] = $row['sum(amount)'];
			}

			$startDate = date("Y-01-01");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null and donation_date between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['ytd']['count'] = $row['count(*)'];
				$returnArray['donations']['ytd']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['ytd']['total'] = $row['sum(amount)'];
			}

			$startDate = date("Y-m-d", strtotime("first day of previous month"));
			$endDate = date("Y-m-d 23:59:59", strtotime("last day of previous month"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null and donation_date between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['last_month']['count'] = $row['count(*)'];
				$returnArray['donations']['last_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['last_month']['total'] = $row['sum(amount)'];
			}

			$startDate = date("Y-m-d", strtotime("first day of this month"));
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null and donation_date between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['this_month']['count'] = $row['count(*)'];
				$returnArray['donations']['this_month']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['this_month']['total'] = $row['sum(amount)'];
			}

			$startDate = date("Y-m-d", strtotime("yesterday"));
			$endDate = date("Y-m-d 23:59:59", strtotime("yesterday"));
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null and donation_date between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['yesterday']['count'] = $row['count(*)'];
				$returnArray['donations']['yesterday']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['yesterday']['total'] = $row['sum(amount)'];
			}

			$startDate = date("Y-m-d");
			$endDate = date("Y-m-d 23:59:59");
			$resultSet = executeReadQuery("select count(*),count(distinct contact_id),sum(amount) from donations where associated_donation_id is null and donation_date between ? and ?", $startDate, $endDate);
			if ($row = getNextRow($resultSet)) {
				$returnArray['donations']['today']['count'] = $row['count(*)'];
				$returnArray['donations']['today']['customers'] = $row['count(distinct contact_id)'];
				$returnArray['donations']['today']['total'] = $row['sum(amount)'];
			}

			break;

		case "get_coreware_defaults":
			$defaults = array();
			$defaults['emails'] = array();
			$resultSet = executeReadQuery("select * from emails where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$defaults['emails'][] = $row;
			}
			$defaults['fragments'] = array();
			$resultSet = executeReadQuery("select * from fragments where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$defaults['fragments'][] = $row;
			}
			$defaults['notifications'] = array();
			$resultSet = executeReadQuery("select * from notifications where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$defaults['notifications'][] = $row;
			}
			$returnArray['defaults'] = $defaults;
			break;

		case "get_presets":
			$presetRecords = array();
			$resultSet = executeReadQuery("select * from preset_records");
			while ($row = getNextRow($resultSet)) {
				$presetRecords[] = $row;
			}
			$presetRecordValues = array();
			$resultSet = executeReadQuery("select *,(select preset_record_code from preset_records where preset_record_id = preset_record_values.preset_record_id) preset_record_code from preset_record_values");
			while ($row = getNextRow($resultSet)) {
				$presetRecordValues[] = $row;
			}
			$returnArray['preset_records'] = $presetRecords;
			$returnArray['preset_record_values'] = $presetRecordValues;
			break;

		case "test_credentials";
			break;

		case "get_banners":
			$bannerGroupId = "";
			$bannerTagId = "";
			if (!empty($_POST['banner_group_id'])) {
				$bannerGroupId = getFieldFromId("banner_group_id", "banner_groups", "banner_group_id", $_POST['banner_group_id']);
			} else if (!empty($_POST['banner_group_code'])) {
				$bannerGroupId = getFieldFromId("banner_group_id", "banner_groups", "banner_group_code", $_POST['banner_group_code']);
			}
			if (!empty($_POST['banner_tag_id'])) {
				$bannerTagId = getFieldFromId("banner_tag_id", "banner_tags", "banner_tag_id", $_POST['banner_tag_id']);
			} else if (!empty($_POST['banner_tag_code'])) {
				$bannerTagId = getFieldFromId("banner_tag_id", "banner_tags", "banner_tag_code", $_POST['banner_tag_code']);
			}
			$resultSet = executeQuery("select * from banners where client_id = ? and inactive = 0" .
				(empty($bannerGroupId) ? "" : " and banner_id in (select banner_id from banner_group_links where banner_group_id = " . $bannerGroupId . ")") .
				(empty($bannerTagId) ? "" : " and banner_id in (select banner_id from banner_tag_links where banner_tag_id = " . $bannerTagId . ")"), $GLOBALS['gClientId']);
			$returnArray['banners'] = array();
			while ($row = getNextRow($resultSet)) {
				$returnArray['banners'][] = $row;
			}
			break;

		case "get_banner_groups":
			$resultSet = executeQuery("select * from banner_groups where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			$returnArray['banner_groups'] = array();
			while ($row = getNextRow($resultSet)) {
				$returnArray['banner_groups'][] = $row;
			}
			break;

        case "get_connection_key":
            if($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || $GLOBALS['gUserRow']['administrator_flag']) {
                $returnArray['error_message'] = "For security reasons, administrator users cannot use get_connection_key.";
                break;
            }
            $developerRow = getRowFromId("developers", "contact_id", $GLOBALS['gUserRow']['contact_id'], "inactive = 0 and full_access = 0");
            if(empty($developerRow)) {
                $errorMessage = getPreference("API_DEVELOPER_NOT_FOUND_ERROR_MESSAGE") ?: "Developer Record not found. Please contact customer support.";
                $returnArray['error_message'] = $errorMessage;
                break;
            }
            $returnArray['connection_key'] = $developerRow['connection_key'];
            break;
		case "setup_corestore":
			if (!$GLOBALS['gUserRow']['full_client_access'] && !$GLOBALS['gUserRow']['superuser_flag']) {
				if (empty(CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "COREFIRE_ADMIN"))) {
					$returnArray['error_message'] = "You do not have permission to set up coreSTORE.";
					break;
				}
			}
			//1. Save the coreSTORE endpoint and API key to the appropriate preferences.
			//2. Create a developer record for them with appropriate permissions
			//3. Create a coreFORCE API key
			//4. return the coreFORCE API key so coreSTORE can create the eCommerce connection.
			if (empty($_POST['corestore_endpoint']) || empty($_POST['corestore_api_key'])) {
				$returnArray['error_message'] = "corestore_endpoint and corestore_api_key are both required.";
				break;
			}
			if (!empty(getPreference("CORESTORE_ENDPOINT")) && !empty(getPreference("CORESTORE_API_KEY"))) {
				$returnArray['error_message'] = "coreSTORE connection already exists.";
				break;
			}
			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "CORESTORE_API_KEY");
			if (empty($preferenceId)) {
				$insertSet = executeQuery("insert into preferences (preference_code, description, detailed_description, user_setable, client_setable, data_type) values (?,?,?,?,?,?)",
					"CORESTORE_API_KEY", "API Key", "API Key required to connect to the Corestore POS", 0, 1, "varchar");
				$preferenceId = $insertSet['insert_id'];
			}
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id, preference_id, preference_value) values (?,?,?)",
				$GLOBALS['gClientId'], $preferenceId, $_POST['corestore_api_key']);

			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "CORESTORE_ENDPOINT");
			if (empty($preferenceId)) {
				$insertSet = executeQuery("insert into preferences (preference_code, description, detailed_description, user_setable, client_setable, data_type) values (?,?,?,?,?,?)",
					"CORESTORE_ENDPOINT", "Endpoint", "This is the URL that is used to connect to the Corestore POS", 0, 1, "varchar");
				$preferenceId = $insertSet['insert_id'];
			}
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
			executeQuery("insert into client_preferences (client_id, preference_id, preference_value) values (?,?,?)",
				$GLOBALS['gClientId'], $preferenceId, $_POST['corestore_endpoint']);

			if (!empty($_POST['location_code'])) {
				$locationCode = makeCode($_POST['location_code']);
				if (empty(getFieldFromId("location_id", "locations", "location_code", $locationCode, "client_id = ?", $GLOBALS['gClientId']))) {
					$storeLocationId = getFieldFromId("location_id", "locations", "location_code", "STORE",
						"product_distributor_id is null and client_id = ?", $GLOBALS['gClientId']);
					if (!empty($storeLocationId)) {
						executeQuery("update locations set location_code = ?, description = ? where location_id = ?",
							$locationCode, $_POST['location_code'], $storeLocationId);
					}
				}
			}

			# Create Developer record
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$contactId = getFieldFromId('contact_id', "users",
					"client_id", $GLOBALS['gClientId'], "administrator_flag = 1 and superuser_flag = 0");
			} else {
				$contactId = $GLOBALS['gUserRow']['contact_id'];
			}
			$insertSet = executeQuery("insert into contacts (client_id, first_name, last_name, business_name, email_address, date_created) " .
				"(select client_id,'coreSTORE', 'POS', business_name, email_address, now() from contacts where contact_id = ?)", $contactId);
			$posContactId = $insertSet['insert_id'];
			$insertSet = executeQuery("insert into users (client_id,contact_id,user_name,password_salt,password,date_created,last_login,administrator_flag) " .
				"values (?,?,'corestorepos',?,?,now(),now(),1)", $GLOBALS['gClientId'], $posContactId, getRandomString(64), getRandomString(64));
			$posUserId = $insertSet['insert_id'];
			updateUserSubscriptions($posContactId);

			$newApiKey = strtoupper(getRandomString());
			$insertSet = executeQuery("insert into developers (contact_id, connection_key) values (?,?)", $posContactId, $newApiKey);
			$developerId = $insertSet['insert_id'];
			$apiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", "CORESTORE");
			executeQuery("insert into developer_api_method_groups (developer_id, api_method_group_id) values (?,?)", $developerId, $apiMethodGroupId);

			$returnArray['connection_key'] = $newApiKey;
			break;
        case "setup_default_merchant_account":
            if($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
                $returnArray['error_message'] = "setup_default_merchant_account can not be run on the primary client.";
                break;
            }
            $existingDefaultMerchantAccountRow = getRowFromId("merchant_accounts", "merchant_account_code", "DEFAULT");
            if(!empty($existingDefaultMerchantAccountRow) && !empty($existingDefaultMerchantAccountRow['account_key'])) {
                $returnArray['error_message'] = "Default merchant account already exists.";
                break;
            }
            $requiredFields = array("blockchyp"=>array("account_login", "account_key", "custom_field_signing_key"),
                "mxmerchant"=>array("account_login", "account_key", "merchant_identifier"));
            $merchantServiceClass = strtolower($_POST['merchant_service']);
            if(empty($requiredFields[$merchantServiceClass])) {
                $returnArray['error_message'] = sprintf("Merchant service '%s' is not supported.", $merchantServiceClass);
                break;
            }
            $postFields = array_change_key_case($_POST);
            foreach($requiredFields[$merchantServiceClass] as $requiredField) {
                if(empty($postFields[$requiredField])) {
                    $returnArray['error_message'] = sprintf("%s is required for merchant service '%s'", $requiredField, $merchantServiceClass);
                    break;
                }
            }
            $merchantServiceId = getFieldFromId("merchant_service_id", "merchant_services", "class_name", $merchantServiceClass);
            $nameValues = array("client_id"=>$GLOBALS['gClientId'],
                "merchant_account_code" => "DEFAULT",
                "description" => "Default Merchant Account",
                "merchant_service_id" => $merchantServiceId
            );
            $merchantAccountsTable = new DataTable('merchant_accounts');
            switch($merchantServiceClass) {
                case "blockchyp":
                    $nameValues['account_login'] = $postFields['account_login'];
                    $nameValues['account_key'] = $postFields['account_key'];
                    $merchantAccountId = $merchantAccountsTable->saveRecord(array("name_values"=>$nameValues));
                    if(empty($merchantAccountId)) {
                        $returnArray['error_message'] = "Merchant account unable to be created: " . $merchantAccountsTable->getErrorMessage();
                        break 2;
                    }
                    if(!CustomField::setCustomFieldData($merchantAccountId, "SIGNING_KEY", $postFields['custom_field_signing_key'], "MERCHANT_ACCOUNTS")) {
                        $returnArray['error_message'] = "An error occurred setting signing key custom field.";
                        break 2;
                    }
                    break;
                case "mxmerchant":
                    $nameValues['account_login'] = $postFields['account_login'];
                    $nameValues['account_key'] = $postFields['account_key'];
                    $nameValues['merchant_identifier'] = $postFields['merchant_identifier'];
                    $merchantAccountId = $merchantAccountsTable->saveRecord(array("name_values"=>$nameValues));
                    if(empty($merchantAccountId)) {
                        $returnArray['error_message'] = "Merchant account unable to be created: " . $merchantAccountsTable->getErrorMessage();
                        break 2;
                    }
                    break;
            }
            $returnArray['merchant_account_id'] = $merchantAccountId;
            break;
        case "save_oidc_state":
            if(!empty($_POST['state_key']) && !empty($_POST['state_data'])) {
                if(getFieldFromId("random_data_chunk_code", "random_data_chunks", "random_data_chunk_code", $_POST['state_key'])) {
                    executeQuery("update random_data_chunks set text_data = ? where random_data_chunk_code = ?", $_POST['state_data'], $_POST['state_key']);
                } else {
                    executeQuery("insert ignore into random_data_chunks (random_data_chunk_code,text_data) values (?,?)", $_POST['state_key'], $_POST['state_data']);
                }
            }
            break;
	}

	include "api-contacts.inc";
	include "api-events.inc";
	include "api-products.inc";
	include "api-orders.inc";
	include "api-helpdesk.inc";

	if (file_exists($GLOBALS['gDocumentRoot'] . "/apilocal.inc")) {
		/** @noinspection PhpIncludeInspection */
		include "apilocal.inc";
	}

	if (count($apiMethodsCalled) > 1) {
		$returnArray['api_method_return_array'][$_POST['action']] = $returnArray;
	}

}

exitApi();

function exitApi() {
	$returnArray = $GLOBALS['returnArray'];
	$returnArray['api_domain_name'] = $_SERVER['HTTP_HOST'];
	if (!array_key_exists("result", $returnArray)) {
		if (empty($returnArray['error_message'])) {
			$returnArray['result'] = "OK";
			unset($returnArray['error_message']);
		} else {
			$returnArray['result'] = "ERROR";
			if ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
				$returnArray['error_message'] .= " (API URL may be incorrect)";
			}
		}
	}

	$allowLogging = false;
	if ($GLOBALS['gDevelopmentServer']) {
		$allowLogging = true;
	} else {
		$override = getPreference("ALLOW_LIVE_API_LOGGING");
		if (!empty($override)) {
			$allowLogging = true;
		}
	}
	if ($allowLogging && !empty($GLOBALS['gPrimaryApiMethodRow']) && ($returnArray['result'] == "ERROR" || $GLOBALS['apiAppRow']['always_log'] || $GLOBALS['gPrimaryApiMethodRow']['always_log'] || ($GLOBALS['apiAppRow']['log_usage'] && $GLOBALS['gPrimaryApiMethodRow']['log_usage']))) {
		$parameters = "";
		if (is_array($_POST)) {
			foreach ($_POST as $fieldName => $fieldData) {
				if (strpos($fieldName, "password") !== false) {
					$fieldData = "XXXXXXXX";
				}
				$parameters .= $fieldName . ": " . (is_array($fieldData) || is_object($fieldData) ? serialize($fieldData) : $fieldData) . "\n";
			}
		}
		$parameters .= "\n" . serialize($_FILES);
		$results = jsonEncode($returnArray);
		if (empty($results)) {
			$results = jsonEncode(array());
		}
		$endTime = getMilliseconds();
		$totalRunTime = round(($endTime - $GLOBALS['gOverallStartTime']) / 1000, 2);
		executeQuery("insert into api_log (client_id, link_url, parameters, results, api_app_id, api_method_id, ip_address, user_id, developer_id, elapsed_time) values " .
			"(?,?,?,?,?, ?,?,?,?,?)", $GLOBALS['gClientId'], $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], $parameters, $results, $GLOBALS['apiAppRow']['api_app_id'],
			$GLOBALS['gPrimaryApiMethodRow']['api_method_id'], $_SERVER['REMOTE_ADDR'], $GLOBALS['gUserId'], $GLOBALS['developerId'], $totalRunTime);
	}

	header('Content-type: application/json');
	ajaxResponse($returnArray);
}
