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

require $GLOBALS['gDocumentRoot'] . '/classes/src/PHPMailer.php';
require $GLOBALS['gDocumentRoot'] . '/classes/src/SMTP.php';
require $GLOBALS['gDocumentRoot'] . '/classes/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

DEFINE("_NOACCESS", 0);
DEFINE("_READONLY", 1);
DEFINE("_READWRITE", 2);
DEFINE("_FULLACCESS", 3);
const VALID_EMAIL_REGEX = "/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/";

###############
# logout - log the user out of the system
###############
function logout() {
	$GLOBALS['gLoggedIn'] = false;
	$GLOBALS['gUserId'] = "";
	$GLOBALS['gUserRow'] = array();
	if (array_key_exists($GLOBALS['gSystemName'], $_SESSION)) {
		foreach ($_SESSION[$GLOBALS['gSystemName']] as $arrayKey => $arrayValue) {
			unset($_SESSION[$GLOBALS['gSystemName']][$arrayKey]);
		}
		unset($_SESSION[$GLOBALS['gSystemName']]);
	}
	saveSessionData();
	session_regenerate_id();
}

###############
# login - log the user into the system
###############
function login($userId, $setLastLoginTime = true) {
	logout();
	$_SESSION[$GLOBALS['gSystemName']] = array();
	$_SESSION[$GLOBALS['gSystemName']]['last_hit'] = time();
	$_SESSION[$GLOBALS['gSystemName']]['user_id'] = $userId;
	$_SESSION[$GLOBALS['gSystemName']]['login_ip_address'] = $_SERVER['REMOTE_ADDR'];
	$_SESSION[$GLOBALS['gSystemName']]['http_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
	saveSessionData();
	$resultSet = executeQuery("select *,(select group_concat(user_group_code) from user_groups join user_group_members using (user_group_id) where user_group_members.user_id = users.user_id) as user_group_codes," .
		"(select group_concat(description) from user_groups join user_group_members using (user_group_id) where user_group_members.user_id = users.user_id) as user_groups," .
		"(select group_concat(user_group_id) from user_group_members where user_id = users.user_id) as user_group_ids," .
		"(select group_concat(category_code) from categories join contact_categories using (category_id) where contact_categories.contact_id = contacts.contact_id) as category_codes," .
		"(select group_concat(description) from categories join contact_categories using (category_id) where contact_categories.contact_id = contacts.contact_id) as categories," .
		"(select group_concat(concat_ws('|',subsystem_id,permission_level)) from user_subsystem_access where user_id = users.user_id order by permission_level desc) as user_subsystem_access, " .
		"(select group_concat(concat_ws('|',page_id,permission_level)) from user_access where user_id = users.user_id order by permission_level desc) as user_access from " .
		"contacts join users using (contact_id) where user_id = ? and inactive = 0", $userId);
	if ($GLOBALS['gUserRow'] = getNextRow($resultSet)) {
		$restrictedIpAddresses = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "RESTRICTED_IP_ADDRESSES");
		if (!empty($restrictedIpAddresses)) {
			$ipAddresses = explode(",", $restrictedIpAddresses);
			if (!in_array($_SERVER['REMOTE_ADDR'], $ipAddresses)) {
				logout();
				return false;
			}
			$GLOBALS['gUserRestrictedByIpAddress'] = true;
			$_SESSION[$GLOBALS['gSystemName']]['user_restricted_by_ip_address'] = true;
			saveSessionData();
		}
		$GLOBALS['gLoggedIn'] = true;
		$GLOBALS['gUserId'] = $GLOBALS['gUserRow']['user_id'];
		$GLOBALS['gClientId'] = (empty($GLOBALS['gDomainClientId']) ? $GLOBALS['gUserRow']['client_id'] : $GLOBALS['gDomainClientId']);
		$GLOBALS['gUserRow']['display_name'] = getUserDisplayName(array("include_company" => false));
		$GLOBALS['gUserRow']['country_name'] = getFieldFromId("country_name", "countries", "country_id", $GLOBALS['gUserRow']['country_id']);
		$GLOBALS['gUserRow']['security_level'] = (empty($GLOBALS['gUserRow']['superuser_flag']) ? getFieldFromId("security_level", "security_levels", "security_level_id", $GLOBALS['gUserRow']['security_level_id']) : 999999999);
		if ($setLastLoginTime) {
			executeQuery("update users set last_login = now() where user_id = ?", $GLOBALS['gUserId']);
			addActivityLog("Logged In");
		}
		$GLOBALS['gUserRow']['user_group_ids'] = array_filter(explode(",", $GLOBALS['gUserRow']['user_group_ids']));

		$userAccess = array();
		if (!empty($GLOBALS['gUserRow']['user_access'])) {
			foreach (explode(",", $GLOBALS['gUserRow']['user_access']) as $thisAccess) {
				$parts = explode("|", $thisAccess);
				$pageId = $parts[0];
				$permissionLevel = $parts[1];
				$userAccess[$pageId] = $permissionLevel;
			}
		}
		$GLOBALS['gUserRow']['user_access'] = $userAccess;

		$userSubsystemAccess = array();
		if (!empty($GLOBALS['gUserRow']['user_subsystem_access'])) {
			foreach (explode(",", $GLOBALS['gUserRow']['user_subsystem_access']) as $thisAccess) {
				$parts = explode("|", $thisAccess);
				$subsystemId = $parts[0];
				$permissionLevel = $parts[1];
				$userSubsystemAccess[$subsystemId] = $permissionLevel;
			}
		}
		$GLOBALS['gUserRow']['user_subsystem_access'] = $userSubsystemAccess;

		if (empty($GLOBALS['gUserRow']['hash_code'])) {
			$hashCode = md5(uniqid(mt_rand(), true) . $GLOBALS['gUserRow']['first_name'] . $GLOBALS['gUserRow']['last_name'] . $GLOBALS['gUserRow']['contact_id'] . $GLOBALS['gUserRow']['email_address'] . $GLOBALS['gUserRow']['date_created']);
			executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $GLOBALS['gUserRow']['contact_id']);
			$GLOBALS['gUserRow']['hash_code'] = $hashCode;
		}

		if (!empty($GLOBALS['gUserRow']['user_type_id'])) {
			$GLOBALS['gUserRow']['user_type'] = getRowFromId("user_types", "user_type_id", $GLOBALS['gUserRow']['user_type_id']);
		} else {
			$GLOBALS['gUserRow']['user_type'] = array();
		}
		if (!empty($GLOBALS['gUserRow']['user_type']['full_client_access'])) {
			$GLOBALS['gUserRow']['full_client_access'] = 1;
		}
		if (!empty($GLOBALS['gUserRow']['superuser_flag'])) {
			$GLOBALS['gUserRow']['administrator_flag'] = 1;
			$GLOBALS['gUserRow']['full_client_access'] = 1;
			$GLOBALS['gWhiteListed'] = true;
		}

		if (empty($GLOBALS['gUserRow']['company_id'])) {
			$GLOBALS['gUserRow']['company_id'] = getFieldFromId("company_id", "companies", "contact_id", $GLOBALS['gUserRow']['contact_id']);
		}
	} else {
		logout();
		return false;
	}
	if (function_exists("_localLogin")) {
		_localLogin();
	}
	freeResult($resultSet);
	return true;
}

function addDebugLog($logEntry, $skipDatabase = false) {
	error_log($GLOBALS['gClientRow']['client_code'] . " : " . date("m/d/Y H:i:s") . " : " . (is_array($logEntry) ? json_encode($logEntry) : $logEntry) . "\n", 3, "/var/log/debug.log");
	if (!empty($logEntry) && !empty($GLOBALS['gPrimaryDatabase']) && !$skipDatabase && !empty($GLOBALS['gClientId'])) {
		executeQuery("insert into debug_log (client_id,content) values (?,?)", $GLOBALS['gClientId'], (is_array($logEntry) ? json_encode($logEntry) : $logEntry));
	}
}

function getBacktrace() {
	$queryLogText = "";
	$bt = debug_backtrace();
	foreach ($bt as $caller) {
		$queryLogText .= " : " . $caller['file'] . ", line " . $caller['line'];
	}
	$queryLogText .= " : " . $_SERVER['REQUEST_URI'];
	return $queryLogText;
}

function addSecurityLog($userName, $securityLogType, $logEntry) {
	if ($GLOBALS['gPrimaryDatabase']) {
		executeQuery("insert into security_log (client_id,security_log_type,user_name,ip_address,log_entry) values " .
			"(?,?,?,?,?)", (empty($GLOBALS['gClientId']) ? 1 : $GLOBALS['gClientId']), strtoupper($securityLogType), strtolower($userName), $_SERVER['REMOTE_ADDR'], $logEntry);
	} else {
		error_log($GLOBALS['gClientRow']['client_code'] . " : " . date("m/d/Y H:i:s") . " : " . $logEntry . "\n", 0);
	}
}

function getSystemMessage($systemMessageCode, $defaultMessage = "", $substitutions = array()) {
	if ($GLOBALS['gSystemPreferencesClientId'] != $GLOBALS['gClientId']) {
		clearGlobals();
	}
	if ($GLOBALS['gSystemMessages'] === false) {
		$GLOBALS['gSystemMessages'] = array();
		$resultSet = executeReadQuery("select * from system_messages where (client_id = ? or client_id = ?) order by system_message_code,client_id desc", $GLOBALS['gClient_id'], $GLOBALS['gDefaultClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists(strtoupper($row['system_message_code']), $GLOBALS['gSystemMessages'])) {
				$GLOBALS['gSystemMessages'][strtoupper($row['system_message_code'])] = $row['content'];
			}
		}
		freeResult($resultSet);
	}
	$systemMessageCode = makeCode($systemMessageCode);
	$message = "";
	if (array_key_exists($systemMessageCode, $GLOBALS['gSystemMessages'])) {
		$message = $GLOBALS['gSystemMessages'][$systemMessageCode];
	} else {
		if (!empty($GLOBALS['gClientId'])) {
			$resultSet = executeReadQuery("select * from system_messages where system_message_code = ? and client_id = ?", $systemMessageCode, $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$message = $row['content'];
			}
			freeResult($resultSet);
		}
		if (empty($message)) {
			$resultSet = executeReadQuery("select * from system_messages where system_message_code = ? and client_id = ?", $systemMessageCode, $GLOBALS['gDefaultClientId']);
			if ($row = getNextRow($resultSet)) {
				$message = $row['content'];
			}
			freeResult($resultSet);
		}
		if (!empty($message)) {
			$GLOBALS['gSystemMessages'][$systemMessageCode] = $message;
		}
	}
	if (empty($message) && !empty($defaultMessage)) {
		executeQuery("insert into system_messages (client_id,system_message_code,description,content) values (?,?,?,?)",
			$GLOBALS['gDefaultClientId'], $systemMessageCode, ucwords(str_replace("_", " ", strtolower($systemMessageCode))), $defaultMessage);
		$message = $defaultMessage;
		$GLOBALS['gSystemMessages'][$systemMessageCode] = $message;
	}
	$message = PlaceHolders::massageContent($message, $substitutions);
	if (strtolower($systemMessageCode) == "basic" && !empty($defaultMessage)) {
		$message .= " (" . $defaultMessage . ")";
	}
	return $message;
}

###############
# getPreference - Get a system preference
###############
$GLOBALS['gSystemPreferencesClientId'] = "";
$GLOBALS['gSystemPreferences'] = array();
$GLOBALS['gClientPreferences'] = array();
$GLOBALS['gUserPreferences'] = array();
$GLOBALS['gNotificationEmails'] = array();

function getPreference($preferenceCode, $preferenceQualifier = "", $defaultValue = "") {
	if ($GLOBALS['gSystemPreferencesClientId'] != $GLOBALS['gClientId']) {
		clearGlobals();
	}
	$preferenceQualifier = strtoupper($preferenceQualifier);
	$preferenceCode = strtoupper($preferenceCode);

	if (empty($preferenceQualifier)) {
		$preferenceQualifier = "NULL_PREFERENCE_QUALIFIER";
	}

	if (array_key_exists($preferenceCode, $GLOBALS['gAllPreferences'])) {
		$preferenceRow = $GLOBALS['gAllPreferences'][$preferenceCode];
	} else {
		$thisPreference = $GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier];
		if (strlen($thisPreference) == 0) {
			$thisPreference = $defaultValue;
		}
		if ($thisPreference == "false") {
			$thisPreference = false;
		}
		return $thisPreference;
	}

	if (array_key_exists($preferenceCode, $GLOBALS['gSystemPreferences']) && array_key_exists($preferenceQualifier, $GLOBALS['gSystemPreferences'][$preferenceCode])) {
		$thisPreference = $GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier];
		if (is_null($thisPreference) || strlen($thisPreference) == 0) {
			$thisPreference = $defaultValue;
		}
		if ($preferenceRow['data_type'] == "tinyint") {
			$thisPreference = ($thisPreference === true || $thisPreference == "true" || $thisPreference == "1");
		}
		return $thisPreference;
	}
	if (!array_key_exists($preferenceCode, $GLOBALS['gSystemPreferences'])) {
		$GLOBALS['gSystemPreferences'][$preferenceCode] = array();
	}
	$GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier] = $preferenceRow['system_value'];
	if ($preferenceRow['client_setable']) {
		if (empty($GLOBALS['gClientPreferences'])) {
			$GLOBALS['gClientPreferences'][$GLOBALS['gClientId']] = array();
			$resultSet = executeReadQuery("select * from client_preferences where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gClientPreferences'][$GLOBALS['gClientId']][$row['preference_id'] . ":" . (empty($row['preference_qualifier']) ? "NULL_PREFERENCE_QUALIFIER" : $row['preference_qualifier'])] = $row;
			}
			freeResult($resultSet);
		}
		$row = (array_key_exists($preferenceRow['preference_id'] . ":" . $preferenceQualifier, $GLOBALS['gClientPreferences'][$GLOBALS['gClientId']]) ? $GLOBALS['gClientPreferences'][$GLOBALS['gClientId']][$preferenceRow['preference_id'] . ":" . $preferenceQualifier] : array());
		if (strlen($row['preference_value']) > 0) {
			$GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier] = $row['preference_value'];
		}
	}
	if ($preferenceRow['user_setable']) {
		if (empty($GLOBALS['gUserPreferences'])) {
			$resultSet = executeReadQuery("select * from user_preferences where user_id = ?", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gUserPreferences'][$row['preference_id'] . ":" . (empty($row['preference_qualifier']) ? "NULL_PREFERENCE_QUALIFIER" : $row['preference_qualifier'])] = $row;
			}
			freeResult($resultSet);
		}
		$row = (array_key_exists($preferenceRow['preference_id'] . ":" . $preferenceQualifier, $GLOBALS['gUserPreferences']) ? $GLOBALS['gUserPreferences'][$preferenceRow['preference_id'] . ":" . $preferenceQualifier] : false);
		if ($row === false) {
			$row = (array_key_exists($preferenceRow['preference_id'] . ":", $GLOBALS['gUserPreferences']) ? $GLOBALS['gUserPreferences'][$preferenceRow['preference_id'] . ":"] : array());
		}
		if (strlen($row['preference_value']) > 0) {
			$GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier] = $row['preference_value'];
		}
	}
	$thisPreference = $GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier];
	if (strlen($thisPreference) == 0) {
		$thisPreference = $defaultValue;
	}
	if ($preferenceRow['data_type'] == "tinyint") {
		$thisPreference = ($thisPreference === true || $thisPreference == "true" || $thisPreference == "1");
	}
	return $thisPreference;
}

function getUserTypePreference($preferenceCode, $preferenceQualifier = "", $defaultValue = "") {
	$thisPreference = "";
	if (empty($preferenceQualifier)) {
		$thisPreference = getPreference($preferenceCode, $GLOBALS['gUserRow']['user_type']['user_type_code'], $defaultValue);
	}
	if (empty($thisPreference)) {
		$thisPreference = getPreference($preferenceCode, $preferenceQualifier, $defaultValue);
	}
	return $thisPreference;
}


###############
# setUserPreference - Set a user preference
###############
function setUserPreference($preferenceCode, $preferenceValue = "", $preferenceQualifier = "", $userId = "") {
	if (empty($userId)) {
		if (!$GLOBALS['gLoggedIn']) {
			return;
		}
		$userId = $GLOBALS['gUserId'];
	}
	if (empty($preferenceQualifier)) {
		$preferenceQualifier = "NULL_PREFERENCE_QUALIFIER";
	}

	$preferenceCode = strtoupper($preferenceCode);
	if (array_key_exists($preferenceCode, $GLOBALS['gAllPreferences'])) {
		$preferenceRow = $GLOBALS['gAllPreferences'][$preferenceCode];
		$preferenceId = $preferenceRow['preference_id'];
	} else {
		$GLOBALS['gPrimaryDatabase']->logError("Non-existent preference '" . $preferenceCode . "' set");
		return;
	}
	if (!$preferenceRow['user_setable']) {
		$GLOBALS['gPrimaryDatabase']->logError("Preference '" . $preferenceCode . "' set, but not user setable");
		return;
	}
	if (empty($preferenceValue)) {
		executeQuery("delete from user_preferences where user_id = ? and preference_qualifier <=> ? and preference_id = ?",
			array($userId, strtoupper($preferenceQualifier), $preferenceId));
	} else {
		$preferenceFields = getMultipleFieldsFromId(array("user_preference_id", "preference_value"), "user_preferences", "user_id", $userId,
			"preference_id = ? and preference_qualifier = ?", $preferenceId, strtoupper($preferenceQualifier));
		if (empty($preferenceFields['user_preference_id'])) {
			executeQuery("insert into user_preferences (user_preference_id,user_id,preference_id,preference_qualifier," .
				"preference_value,version) values (null,?,?,?,?,1)", array($userId, $preferenceId,
				strtoupper($preferenceQualifier), $preferenceValue));
		} else if ($preferenceFields['preference_value'] != $preferenceValue) {
			executeQuery("update user_preferences set preference_value = ? where user_preference_id = ?", $preferenceValue, $preferenceFields['user_preference_id']);
		}
	}
	if (!array_key_exists($preferenceCode, $GLOBALS['gSystemPreferences'])) {
		$GLOBALS['gSystemPreferences'][$preferenceCode] = array();
	}
	$GLOBALS['gSystemPreferences'][$preferenceCode][$preferenceQualifier] = $preferenceValue;
	return;
}

function setupPreferences($preferenceArray) {
	$preferenceTable = new DataTable("preferences");
	$preferenceTable->setSaveOnlyPresent(true);
	foreach ($preferenceArray as $thisPreference) {
		$initialValue = $thisPreference['initial_value'];
		if (!empty($initialValue)) {
			unset($thisPreference['initial_value']);
		}
		$currentValue = $thisPreference['current_value'];
		if (!empty($currentValue)) {
			$initialValue = !empty($initialValue) ? $initialValue : $currentValue;
			unset($thisPreference['current_value']);
		}
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $thisPreference['preference_code']);
		if (empty($preferenceId)) {
			if (!array_key_exists("client_setable", $thisPreference)) {
				$thisPreference['client_setable'] = 1; // default to client_setable
			}
			if (!empty($thisPreference['preference_group'])) {
				$preferenceGroup = $thisPreference['preference_group'];
				unset($thisPreference['preference_group']);
			}
			$preferenceId = $preferenceTable->saveRecord(array("name_values" => $thisPreference));
			if (!empty($preferenceGroup)) {
				executeQuery("insert into preference_group_links (preference_id, preference_group_id) select ?,preference_group_id from preference_groups where preference_group_code = ?",
					$preferenceId, $preferenceGroup);
			}
        } elseif (!empty($thisPreference['system_value'])) {
            executeQuery("update preferences set system_value = ? where preference_id = ?", $thisPreference['system_value'], $preferenceId);
        }

		$clientPreferenceId = getFieldFromId("client_preference_id", "client_preferences", "preference_id",
			$preferenceId, "client_id = ?", $GLOBALS['gClientId']);
		if (empty($clientPreferenceId) && !empty($initialValue)) {
			executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $initialValue);
		} else if (!empty($clientPreferenceId) && !empty($currentValue)) {
			executeQuery("update client_preferences set preference_value = ? where client_id = ? and preference_id = ?", $currentValue, $GLOBALS['gClientId'], $preferenceId);
		}
	}
}

function setClientPreference($preferenceCode, $value, $preferenceQualifier = null) {
	$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $preferenceCode, "client_setable = 1");
	if (!empty($preferenceId)) {
		$qualifierWhere = (empty($preferenceQualifier) ? "preference_qualifier is null" : "preference_qualifier = '" . $preferenceQualifier . "'");
		if (empty($value)) {
			executeQuery("delete from client_preferences where client_id = ? and preference_id = ? and " . $qualifierWhere,
				$GLOBALS['gClientId'], $preferenceId);
		} else {
			$clientPreferenceRow = getRowFromId("client_preferences", "preference_id", $preferenceId, $qualifierWhere);
			if (empty($clientPreferenceRow)) {
				executeQuery("insert into client_preferences (client_id,preference_id,preference_value, preference_qualifier) values (?,?,?,?)",
					$GLOBALS['gClientId'], $preferenceId, $value, $preferenceQualifier);
			} else {
				executeQuery("update client_preferences set preference_value = ? where client_preference_id = ?", $value, $clientPreferenceRow['client_preference_id']);
			}
		}
		$preferenceKey = $preferenceId . ":" . (empty($preferenceQualifier) ? "NULL_PREFERENCE_QUALIFIER" : $preferenceQualifier);
		if (!empty($GLOBALS['gClientPreferences'][$GLOBALS['gClientId']][$preferenceKey])) {
			$clientPreferenceRow['preference_value'] = $value;
			$GLOBALS['gClientPreferences'][$GLOBALS['gClientId']][$preferenceKey] = $clientPreferenceRow;
		}
	}
}

###############
# hasCapability - determine if the user has access to a system wide capability
###############
function hasCapability($userFunctionCode, $userId = "") {
	if (empty($userId)) {
		$userId = $GLOBALS['gUserId'];
	}
	$resultSet = executeReadQuery("select * from user_function_uses where user_id = ? and user_function_id = (select user_function_id from user_functions where user_function_code = ?)",
		$userId, $userFunctionCode);
	if ($row = getNextRow($resultSet)) {
		freeResult($resultSet);
		return true;
	}
	freeResult($resultSet);

	$resultSet = executeReadQuery("select * from user_group_function_uses where user_group_id in (select user_group_id from user_group_members where user_id = ?) and user_function_id = (select user_function_id from user_functions where user_function_code = ?)",
		$userId, $userFunctionCode);
	if ($row = getNextRow($resultSet)) {
		freeResult($resultSet);
		return true;
	}
	freeResult($resultSet);
	return false;
}

###############
# pageFunctionExists - determine if the page has a function
###############
function pageFunctionExists($pageFunctionCode, $pageCode = "") {
	if (empty($pageCode)) {
		$pageId = $GLOBALS['gPageRow']['page_id'];
	} else {
		$pageId = $GLOBALS['gAllPageCodes'][$pageCode];
	}
	$pageFunctionId = getReadFieldFromId("page_function_id", "page_functions", "page_function_code", strtoupper($pageFunctionCode), "page_id = ?", $pageId);
	return (!empty($pageFunctionId));
}

###############
# hasPageCapability - determine if the user has access to a capability within a page
###############
function hasPageCapability($pageFunctionCode, $pageCode = "") {
	if (empty($pageCode)) {
		$pageCode = $GLOBALS['gPageCode'];
	}
	$resultSet = executeReadQuery("select * from user_page_functions where user_id = ? and " .
		"page_function_id = (select page_function_id from page_functions where page_function_code = ? and page_id = (select page_id from pages where page_code = ?))",
		$GLOBALS['gUserId'], strtoupper($pageFunctionCode), $pageCode);
	if ($row = getNextRow($resultSet)) {
		freeResult($resultSet);
		return true;
	}
	$resultSet = executeReadQuery("select * from user_group_page_functions where user_group_id in (select user_group_id from user_group_members where user_id = ?) and " .
		"page_function_id = (select page_function_id from page_functions where page_function_code = ? and page_id = (select page_id from pages where page_code = ?))",
		$GLOBALS['gUserId'], strtoupper($pageFunctionCode), $pageCode);
	if ($row = getNextRow($resultSet)) {
		freeResult($resultSet);
		return true;
	}
	freeResult($resultSet);
	return false;
}

###############
# userHasAttribute - determine if the user has attribute
###############
function userHasAttribute($userAttributeCode, $userId = "", $fullClientAccessReturn = true) {
	if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access']) {
		return $fullClientAccessReturn;
	}
	if (empty($userId)) {
		$userId = $GLOBALS['gUserId'];
	}
	$resultSet = executeReadQuery("select * from user_attributions where user_id = ? and " .
		"user_attribute_id in (select user_attribute_id from user_attributes where user_attribute_code = ?)",
		$userId, $userAttributeCode);
	if ($row = getNextRow($resultSet)) {
		freeResult($resultSet);
		return true;
	} else {
		freeResult($resultSet);
		return false;
	}
}

###############
# hasLegitimateHtml - Determine if the content has real HTML
###############
function hasLegitimateHtml($content) {
	$elements = array("</p>", "</td>", "</table>", "<br>", "</ul>", "</li>", "<br/>", "</h1>", "</span>", "</head>", "</script>", "</body>", "</textarea>");
	foreach ($elements as $thisElement) {
		if (strpos($content, $thisElement) !== false) {
			return true;
		}
	}
	return false;
}

###############
# makeHtml - takes text and turns it into HTML
###############
function makeHtml($content, $parameters = array()) {
	if (isHTML($content)) {
		return $content;
	}
	$content = trim(str_replace("\r\n", "\r", $content));
	$content = trim(str_replace("\n", "\r", $content));
	while (strpos($content, "\r\r\r") !== false) {
		$content = str_replace("\r\r\r", "\r\r", $content);
	}
	if ($parameters['use_br']) {
		$content = str_replace("<p></p>", "", (empty($parameters['no_outer_wrapper']) ? "<p>" : "") . str_replace("\r", "<br/>", $content) . (empty($parameters['no_outer_wrapper']) ? "</p>" : ""));
	} else {
		$content = str_replace("<p></p>", "", (empty($parameters['no_outer_wrapper']) ? "<p>" : "") . str_replace("\r\r", "</p><p>", $content) . (empty($parameters['no_outer_wrapper']) ? "</p>" : ""));
		$content = str_replace("\r", "<br/>", $content);
	}
	return replaceLinks($content);
}

###############
# cleanHtml - clear HTML and remove suspicious content
###############
function cleanHtml($content) {
	$cleanStrings = array("<script", "</script>", "<textarea", "</textarea>");
	foreach ($cleanStrings as $thisString) {
		$content = str_replace($thisString, "", $content);
	}
	return $content;
}

###############
# removeHtml - takes HTML and returns text
###############
function removeHtml($content, $parameters = array()) {
	if (!isHTML($content)) {
		return $content;
	}
	$stripTags = array("p", "br", "br/", "br /", "strong", "li", "ul", "ol", "script", "textarea", "div");
	$stripStrings = array("&nbsp;");
	$content = strip_tags($content);
	foreach ($stripStrings as $thisString) {
		$content = str_replace($thisString, "", $content);
	}
	foreach ($stripTags as $thisTag) {
		$content = str_replace("<" . $thisTag . ">", "", $content);
		$content = str_replace("</" . $thisTag . ">", "", $content);
	}
	return $content;
}

###############
# sendEmail - send an email. The parameters array can have numerous entries:
#
# email_addresses => a single email address or an array of addresses
# cc_addresses => a single email address or an array of addresses
# bcc_addresses => a single email address or an array of addresses
# from_name => name from which the email will appear to come
# sender => email address from which the email will appear to have been sent
# reply_email => Email to which any reply will be sent
# email_id => an ID of the email which contains the subject and content. This will override any body or subject parameters
# body => The content of the email
# subject => the subject of the email
# receipt_email => email address that will receive a receipt of the email being opened
# substitutions => an array of substitution values
# is_html => true or false indicating whether the email should be HTML. Default is true.
# email_credential_code => Email credentials to use for sending the mail
# email_credential_id => Email credentials to use for sending the mail
# attachment_string => String that will be put into file and attached to email
# attachment_filename => filename of attachment
# attachment_file_id => file ID of attachment
# notification_code => notification code
# send_after => if the email is queued, it can queued to be sent after this time
###############
function sendEmail($parameters) {
	if ($GLOBALS['gSystemName'] == "CORE" && $GLOBALS['gDevelopmentServer'] && empty(getPreference("DEVELOPMENT_TEST_EMAIL"))) {
		$parameters['send_immediately'] = true;
		return true;
	}
	$errorMessage = true;
	if (!empty($parameters['attachment_string']) && !empty($parameters['attachment_filename'])) {
		$parameters['send_immediately'] = true;
	}
	if (!empty($parameters['attachments'])) {
		$parameters['send_immediately'] = true;
	}
	if (!$parameters['send_immediately']) {
		if (!$GLOBALS['gDevelopmentServer'] && $GLOBALS['gPrimaryDatabase']->tableExists("email_queue") && !empty($parameters)) {
			$maxEmailQueueIdentifier = 0;
			if ($GLOBALS['gPrimaryDatabase']->tableExists("email_log")) {
				$resultSet = executeQuery("select max(email_queue_identifier) from email_log");
				if ($row = getNextRow($resultSet)) {
					$maxEmailQueueIdentifier = $row['max(email_queue_identifier)'];
				}
			}
			if (empty($parameters['email_address']) && empty($parameters['email_addresses']) && !empty($parameters['notification_code'])) {
				$notificationEmailAddress = "";
				if (!is_array($parameters['notification_code'])) {
					$parameters['notification_code'] = array($parameters['notification_code']);
				}
				foreach ($parameters['notification_code'] as $thisNotificationCode) {
					$notificationId = getReadFieldFromId("notification_id", "notifications", "notification_code", $thisNotificationCode, "inactive = 0");
					$notificationEmailAddress = $notificationEmailAddress ?: getReadFieldFromId("email_address", "notification_emails", "notification_id", $notificationId);
				}
				if (empty($notificationEmailAddress)) {
					return true;
				}
			}
			$resultSet = executeQuery("insert into email_queue (client_id,parameters,time_submitted) values (?,?,now())", (empty($GLOBALS['gClientId']) ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']), jsonEncode($parameters));
			if (!empty($resultSet['sql_error'])) {
				return "Email Queue Error: " . $resultSet['sql_error'];
			} else {
				$emailQueueId = $resultSet['insert_id'];
				if ($emailQueueId < $maxEmailQueueIdentifier) {
					executeQuery("delete from email_queue where email_queue_id = ?", $emailQueueId);
					executeQuery("alter table email_queue auto_increment = " . ($maxEmailQueueIdentifier + 100));
					executeQuery("insert into email_queue (client_id,parameters,time_submitted) values (?,?,now())", (empty($GLOBALS['gClientId']) ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']), jsonEncode($parameters));
				}
				return true;
			}
		}
	}
	if (empty($parameters['email_id']) && !empty($parameters['email_code'])) {
		$parameters['email_id'] = getReadFieldFromId("email_id", "emails", "email_code", $parameters['email_code']);
	}
	$mail = new PHPMailer;
	$mail->Timeout = 60;
	$emailCredentials = array();
	if (!empty($parameters['primary_client'])) {
		$emailCredentials = getRowFromId("email_credentials", "email_credential_code", "DEFAULT", "client_id = ?", $GLOBALS['gDefaultClientId']);
	}
	if (!empty($parameters['email_credential_code'])) {
		$newEmailCredentials = getRowFromId("email_credentials", "email_credential_code", $parameters['email_credential_code'], "client_id = ?", $GLOBALS['gClientId']);
		if (!empty($newEmailCredentials)) {
			$emailCredentials = $newEmailCredentials;
		}
	}
	if (!empty($parameters['email_credential_id'])) {
		$newEmailCredentials = getRowFromId("email_credentials", "email_credential_id", $parameters['email_credential_id'], "client_id is not null");
		if (!empty($newEmailCredentials)) {
			$emailCredentials = $newEmailCredentials;
		}
	}
	if (empty($emailCredentials) && !empty($parameters['email_id'])) {
		$newEmailCredentials = getReadRowFromId("email_credentials", "email_credential_id", getReadFieldFromId("email_credential_id", "emails", "email_id", $parameters['email_id']), "client_id = ?", $GLOBALS['gClientId']);
		if (!empty($newEmailCredentials)) {
			$emailCredentials = $newEmailCredentials;
		}
	}
	if (empty($emailCredentials)) {
		$emailCredentials = getRowFromId("email_credentials", "email_credential_code", "DEFAULT", "client_id = ?", $GLOBALS['gClientId']);
	}

	if (!array_key_exists("email_addresses", $parameters) && array_key_exists("email_address", $parameters)) {
		$parameters['email_addresses'] = $parameters['email_address'];
	}
	if (!array_key_exists("cc_addresses", $parameters) && array_key_exists("cc_address", $parameters)) {
		$parameters['cc_addresses'] = $parameters['cc_address'];
	}
	if (!array_key_exists("bcc_addresses", $parameters) && array_key_exists("bcc_address", $parameters)) {
		$parameters['bcc_addresses'] = $parameters['bcc_address'];
	}

	if (array_key_exists("email_addresses", $parameters) && !is_array($parameters['email_addresses'])) {
		$parameters['email_addresses'] = array($parameters['email_addresses']);
	}
	if (array_key_exists("cc_addresses", $parameters) && !is_array($parameters['cc_addresses'])) {
		$parameters['cc_addresses'] = array($parameters['cc_addresses']);
	}
	if (array_key_exists("bcc_addresses", $parameters) && !is_array($parameters['bcc_addresses'])) {
		$parameters['bcc_addresses'] = array($parameters['bcc_addresses']);
	}

	if (!array_key_exists("email_addresses", $parameters)) {
		$parameters['email_addresses'] = array();
	}
	if (!array_key_exists("cc_addresses", $parameters)) {
		$parameters['cc_addresses'] = array();
	}
	if (!array_key_exists("bcc_addresses", $parameters)) {
		$parameters['bcc_addresses'] = array();
	}
	if (!empty($parameters['contact_id'])) {
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $parameters['contact_id']);
		if (empty($contactId)) {
			$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $parameters['contact_id']);
		}
		$parameters['contact_id'] = $contactId;
	}
    if (array_key_exists("substitutions", $parameters) && is_array($parameters['substitutions'])) {
        $parameters['substitutions'] = array_change_key_case($parameters['substitutions']);
    }

    if (!empty($parameters['contact_id'])) {
		if (empty($parameters['email_code'])) {
			$emailCode = getReadFieldFromId("email_code", "emails", "email_id", $parameters['email_id']);
		} else {
			$emailCode = $parameters['email_code'];
		}
		if ($GLOBALS['gPHPVersion'] >= 70200) {
			if (empty($emailCode)) {
				$textMessageContent = strip_tags(str_replace(array("</p>", "<br>"), "\n", html_entity_decode($parameters['body'])));
				$textMessageContent = (strlen($textMessageContent) > 1600 ? substr($textMessageContent, 0, 1500) . "...Message is too long" : $textMessageContent);
				TextMessage::sendMessage($parameters['contact_id'], $textMessageContent, $parameters['substitutions']);
			} else {
				TextMessage::sendMessageCode($parameters['contact_id'], $emailCode, $parameters['substitutions']);
				if (!empty($parameters['text_only'])) {
					$parameters['email_code'] = $emailCode;
					$parametersString = jsonEncode($parameters);
					executeQuery("insert into email_log (client_id,email_id,contact_id,parameters,time_submitted,email_queue_identifier,log_entry) values (?,?,?,?,now(),?,?)",
						(empty($GLOBALS['gClientId']) ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']), $parameters['email_id'], $parameters['contact_id'], (empty($parametersString) ? "Too Big" : $parametersString),
						($errorMessage === true ? $parameters['email_queue_id'] : ""), "Text Message Only Sent");
				}
			}
		}
	}
	if (!empty($parameters['text_only'])) {
		return true;
	}

	try {
		$mail->From = $emailCredentials['email_address'];
		if (array_key_exists("from_name", $parameters)) {
			$mail->FromName = $parameters['from_name'];
		} else {
			$mail->FromName = $emailCredentials['full_name'];
		}
		if (array_key_exists("sender", $parameters)) {
			$mail->Sender = $parameters['sender'];
		} else {
			$mail->Sender = $emailCredentials['email_address'];
		}
		if (array_key_exists("reply_email", $parameters)) {
			$mail->AddReplyTo($parameters['reply_email'], $parameters['reply_name']);
		} else {
			$replyEmail = $emailCredentials['email_address'];
			$replyName = $emailCredentials['full_name'];
			if (!empty($replyEmail)) {
				$mail->AddReplyTo($replyEmail, $replyName);
			}
		}
		if (!empty($emailCredentials['security_setting'])) {
			$mail->SMTPSecure = $emailCredentials['security_setting'];
		}
		if (!empty($emailCredentials['smtp_port'])) {
			$mail->Port = $emailCredentials['smtp_port'];
		}
		if (!empty($parameters['email_id'])) {
			$parameters['email_id'] = getReadFieldFromId("email_id", "emails", "email_id", $parameters['email_id'], "inactive = 0");
		}
		if (!empty($parameters['email_id'])) {
			$parameters['subject'] = getReadFieldFromId("subject", "emails", "email_id", $parameters['email_id']);
			$parameters['body'] = getReadFieldFromId("content", "emails", "email_id", $parameters['email_id']);
			$resultSet = executeQuery("select email_address from email_copies where email_id = ?", $parameters['email_id']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['email_address'], $parameters['email_addresses']) && !in_array($row['email_address'], $parameters['cc_addresses']) && !in_array($row['email_address'], $parameters['bcc_addresses'])) {
					$parameters['cc_addresses'][] = $row['email_address'];
				}
			}
		}
		if (array_key_exists("substitutions", $parameters) && is_array($parameters['substitutions'])) {
			$parameters['body'] = PlaceHolders::massageContent($parameters['body'], $parameters['substitutions']);
			$parameters['subject'] = PlaceHolders::massageContent($parameters['subject'], $parameters['substitutions']);
			$domainName = getDomainName();
			if (!empty($domainName)) {
				$parameters['body'] = str_replace("'/getimage.php", "'" . $domainName . "/getimage.php", $parameters['body']);
				$parameters['body'] = str_replace('"/getimage.php', '"' . $domainName . "/getimage.php", $parameters['body']);
			}
			$newBody = "";
			$bodyLines = getContentLines($parameters['body']);
			$useLine = true;
			foreach ($bodyLines as $thisLine) {
				if ($thisLine == "%endif%") {
					$useLine = true;
					continue;
				}
				if (startsWith($thisLine, "%if_has_value:")) {
					$substitutionFieldName = strtolower(substr($thisLine, strlen("%if_has_value:")));
					if (substr($substitutionFieldName, -1) == "%") {
						$substitutionFieldName = substr($substitutionFieldName, 0, -1);
					}
					$useLine = !empty($parameters['substitutions'][$substitutionFieldName]);
					continue;
				}
				if ($useLine) {
					$newBody .= $thisLine . "\n";
				}
			}
			$parameters['body'] = $newBody;
		}
		$parameters['body'] = PlaceHolders::massageContent($parameters['body']);
		$parameters['subject'] = PlaceHolders::massageContent($parameters['subject']);
		$clientSubstitutions = array();
		$clientSubstitutions['current_year'] = date("Y");
		$clientSubstitutions['client_name'] = $GLOBALS['gClientRow']['business_name'];
		$clientSubstitutions['domain_name'] = getDomainName();
		$clientSubstitutions['client_address_1'] = $GLOBALS['gClientRow']['address_1'];
		$clientSubstitutions['client_address_2'] = $GLOBALS['gClientRow']['address_2'];
		$clientSubstitutions['client_city'] = $GLOBALS['gClientRow']['city'];
		$clientSubstitutions['client_state'] = $GLOBALS['gClientRow']['state'];
		$clientSubstitutions['client_postal_code'] = $GLOBALS['gClientRow']['postal_code'];
		$clientSubstitutions['client_country'] = getReadFieldFromId("country_name", "countries", "country_id", $GLOBALS['gClientRow']['country_id']);
		$clientSubstitutions['client_email_address'] = $GLOBALS['gClientRow']['email_address'];
		$clientSubstitutions['client_phone_number'] = getReadFieldFromId("phone_number", "phone_numbers", "contact_id", $GLOBALS['gClientRow']['contact_id']);
		$resultSet = executeReadQuery("select * from phone_numbers where contact_id = ?", $GLOBALS['gClientRow']['contact_id']);
		$count = 0;
		while ($row = getNextRow($resultSet)) {
			$count++;
			$clientSubstitutions['client_phone_number_' . $count] = $row['phone_number'];
			$clientSubstitutions['client_phone_description_' . $count] = $row['description'];
			if (!empty($row['description'])) {
				$clientSubstitutions['client_' . makeCode($row['description'], array("lowercase" => true)) . '_phone_number'] = $row['phone_number'];
			}
		}
		$addressBlock = $GLOBALS['gClientRow']['address_1'];
		if (!empty($GLOBALS['gClientRow']['address_2'])) {
			$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $GLOBALS['gClientRow']['address_2'];
		}
		$cityLine = $GLOBALS['gClientRow']['city'];
		if (!empty($GLOBALS['gClientRow']['state'])) {
			$cityLine .= (empty($cityLine) ? "" : ", ") . $GLOBALS['gClientRow']['state'];
		}
		if (!empty($GLOBALS['gClientRow']['postal_code'])) {
			$cityLine .= (empty($cityLine) ? "" : " ") . $GLOBALS['gClientRow']['postal_code'];
		}
		if (!empty($cityLine)) {
			$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $cityLine;
		}
		if ($GLOBALS['gClientRow']['country_id'] != 1000) {
			$addressBlock .= (empty($addressBlock) ? "" : "<br>") . getReadFieldFromId("country_name", "countries", "country_id", $GLOBALS['gClientRow']['country_id']);
		}
		$clientSubstitutions['client_address_block'] = $addressBlock;
		foreach ($clientSubstitutions as $nameValue => $dataValue) {
			$dataValue = is_scalar($dataValue) ? $dataValue : "";
			$parameters['body'] = str_ireplace("%" . $nameValue . "%", $dataValue, $parameters['body']);
			$parameters['subject'] = str_ireplace("%" . $nameValue . "%", $dataValue, $parameters['subject']);
		}

		if (empty($parameters['body'])) {
			return "Nothing in email body";
		}
		$mail->Body = $parameters['body'];
		$mail->Subject = $parameters['subject'];
		$mail->IsMail();
		$mail->IsHTML(array_key_exists("is_html", $parameters) ? $parameters['is_html'] : true);
		if (!empty($parameters['attachment_string']) && !empty($parameters['attachment_filename'])) {
			$mail->addStringAttachment($parameters['attachment_string'], $parameters['attachment_filename']);
		} else if (!empty($parameters['attachment_filename'])) {
			$mail->addAttachment($parameters['attachment_filename']);
		} else if (!empty($parameters['attachment_file_id'])) {
			$fileRow = getRowFromId("files", "file_id", $parameters['attachment_file_id']);
			if (!empty($fileRow)) {
				if (!empty($fileRow['os_filename'])) {
					$fileRow['file_content'] = getExternalFileContents($fileRow['os_filename']);
				}
			}
			if (!empty($fileRow['file_content'])) {
				$mail->addStringAttachment($fileRow['file_content'], (empty($parameters['attachment_filename']) ? $fileRow['filename'] : $parameters['attachment_filename']));
			}
		}
		if (!empty($parameters['attachments']) && is_array($parameters['attachments'])) {
			foreach ($parameters['attachments'] as $attachmentFile) {
				if (!empty($attachmentFile['attachment_string']) && !empty($attachmentFile['attachment_filename'])) {
					$mail->addStringAttachment($attachmentFile['attachment_string'], $attachmentFile['attachment_filename']);
				} else if (!empty($attachmentFile['attachment_filename'])) {
					$mail->addAttachment($attachmentFile['attachment_filename']);
				} else if (!empty($attachmentFile['attachment_file_id'])) {
					$fileRow = getRowFromId("files", "file_id", $attachmentFile['attachment_file_id']);
					if (!empty($fileRow)) {
						if (!empty($fileRow['os_filename'])) {
							$fileRow['file_content'] = getExternalFileContents($fileRow['os_filename']);
						}
					}
					if (!empty($fileRow['file_content'])) {
						$mail->addStringAttachment($fileRow['file_content'], (empty($attachmentFile['attachment_filename']) ? $fileRow['filename'] : $attachmentFile['attachment_filename']));
					}
				}
			}
		}

		$mail->IsSMTP();
		$mail->SMTPOptions = array(
			'ssl' => array(
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			)
		);
		$mail->Host = $emailCredentials['smtp_host'];
		$mail->SMTPAuth = true;
		$mail->Username = $emailCredentials['smtp_user_name'];
		$mail->Password = $emailCredentials['smtp_password'];
		$mail->AuthType = $emailCredentials['smtp_auth_type'];
		$mail->ClearAddresses();
		$emailAdded = false;
		$allEmailAddresses = array();

		if ($GLOBALS['gDevelopmentServer']) {
			if (!empty($emailDomains = getPreference("DEVELOPMENT_TEST_EMAIL_DOMAINS"))) {
				foreach (array("email_addresses", "cc_addresses", "bcc_addresses") as $parameterName) {
					foreach ($parameters[$parameterName] as $index => $emailAddress) {
						$parts = explode("@", $emailAddress);
						$domain = array_pop($parts);
						if (stristr($emailDomains, $domain) === false) {
							unset($parameters[$parameterName][$index]);
						}
					}
				}
			} else {
				$parameters['email_addresses'] = array();
				$parameters['cc_addresses'] = array();
				$parameters['bcc_addresses'] = array();
				$developmentEmail = getNotificationEmails("DEVELOPMENT");
				if (empty($developmentEmail)) {
					if ($GLOBALS['gUserRow']['superuser_flag']) {
						$emailAddress = $GLOBALS['gUserRow']['email_address'];
						if (!empty($emailAddress)) {
							$parameters['email_addresses'] = array($emailAddress);
						}
					}
				} else {
					$parameters['email_addresses'] = $developmentEmail;
				}
			}
		} else {
			if (!empty($parameters['notification_code'])) {
				if (!is_array($parameters['notification_code'])) {
					$parameters['notification_code'] = array($parameters['notification_code']);
				}
				foreach ($parameters['notification_code'] as $thisNotificationCode) {
					$theseEmailAddresses = getNotificationEmails($thisNotificationCode);
					foreach ($theseEmailAddresses as $emailAddress) {
						if (!empty($emailAddress)) {
							$parameters['email_addresses'][] = $emailAddress;
						}
					}
				}
			}
		}
		if (array_key_exists("email_addresses", $parameters)) {
			foreach ($parameters['email_addresses'] as $emailAddress) {
				$blacklistId = getReadFieldFromId("email_blacklist_id", "email_blacklist", "email_address", $emailAddress);
				if (!empty($blacklistId)) {
					continue;
				}
				$parts = explode("@", $emailAddress);
				$blacklistId = getReadFieldFromId("email_blacklist_id", "email_blacklist", "email_address", "@" . $parts[1]);
				if (!empty($blacklistId)) {
					continue;
				}
				if (!preg_match(VALID_EMAIL_REGEX, $emailAddress)) {
					continue;
				}
				if (!empty($emailAddress) && !in_array($emailAddress, $allEmailAddresses)) {
					$mail->AddAddress($emailAddress);
					$allEmailAddresses[] = $emailAddress;
					$emailAdded = true;
				}
			}
		}
		if (array_key_exists("cc_addresses", $parameters)) {
			foreach ($parameters['cc_addresses'] as $emailAddress) {
				$blacklistId = getReadFieldFromId("email_blacklist_id", "email_blacklist", "email_address", $emailAddress);
				if (!empty($blacklistId)) {
					continue;
				}
				if (!preg_match(VALID_EMAIL_REGEX, $emailAddress)) {
					continue;
				}
				if (!empty($emailAddress) && !in_array($emailAddress, $allEmailAddresses)) {
					$mail->AddCC($emailAddress);
					$allEmailAddresses[] = $emailAddress;
					$emailAdded = true;
				}
			}
		}
		if (array_key_exists("bcc_addresses", $parameters)) {
			foreach ($parameters['bcc_addresses'] as $emailAddress) {
				$blacklistId = getReadFieldFromId("email_blacklist_id", "email_blacklist", "email_address", $emailAddress);
				if (!empty($blacklistId)) {
					continue;
				}
				if (!preg_match(VALID_EMAIL_REGEX, $emailAddress)) {
					continue;
				}
				if (!empty($emailAddress) && !in_array($emailAddress, $allEmailAddresses)) {
					$mail->AddBCC($emailAddress);
					$allEmailAddresses[] = $emailAddress;
					$emailAdded = true;
				}
			}
		}
		if (!$emailAdded) {
			return "No Email Address included";
		}
        if($parameters['primary_client'] || $GLOBALS['gCommandLine']) {
            $parameters['no_copy'] = true;
        }
		if (empty($parameters['no_copy'])) {
			$theseEmailAddresses = getNotificationEmails("CC_ALL_EMAILS");
			foreach ($theseEmailAddresses as $emailAddress) {
				if (!empty($emailAddress) && !in_array($emailAddress, $allEmailAddresses)) {
					$mail->AddCC($emailAddress);
					$allEmailAddresses[] = $emailAddress;
				}
			}
			$theseEmailAddresses = getNotificationEmails("BCC_ALL_EMAILS");
			foreach ($theseEmailAddresses as $emailAddress) {
				if (!empty($emailAddress) && !in_array($emailAddress, $allEmailAddresses)) {
					$mail->AddBCC($emailAddress);
					$allEmailAddresses[] = $emailAddress;
				}
			}
		}
		$mail->ConfirmReadingTo = $parameters['receipt_email'];
		if ($emailAdded) {
			if (!$mail->Send()) {
				$errorMessage = str_ireplace("https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting", "", $mail->ErrorInfo) . ":" . $mail->Host . ", " . $mail->Username;
			}
		} else {
			$errorMessage = "No Email Address included";
		}
		if (empty($parameters['no_copy'])) {
			$theseEmailAddresses = getNotificationEmails("CC_ALL_EMAILS");
			foreach ($theseEmailAddresses as $emailAddress) {
				if (!empty($emailAddress)) {
					$parameters['cc_addresses'][] = $emailAddress;
				}
			}
			$theseEmailAddresses = getNotificationEmails("BCC_ALL_EMAILS");
			foreach ($theseEmailAddresses as $emailAddress) {
				if (!empty($emailAddress)) {
					$parameters['bcc_addresses'][] = $emailAddress;
				}
			}
		}
		if ($GLOBALS['gPrimaryDatabase']->tableExists("email_log")) {
			$mailData = get_object_vars($mail);
			$logEntry = "Results: " . $errorMessage . "\n" . "Sent to: " . implode(",", $allEmailAddresses) . "\n";
			$mailData['Attachments'] = "";
			foreach ($mail->getAttachments() as $thisAttachment) {
				$mailData['Attachments'] .= sprintf("%s (%s, size %s)\n", $thisAttachment[1], $thisAttachment[4], number_format(strlen($thisAttachment[0])));
			}
			$mailData['Body'] = getFirstPart($mailData['Body'], 9000);
			foreach ($mailData as $fieldName => $fieldData) {
				if (stripos("password", $fieldName) === false) {
					$fieldData = is_array($fieldData) ? jsonEncode($fieldData) : trim($fieldData);
					$logEntry .= $fieldName . ": " . $fieldData . "\n";
				}
			}
			// Log source of email
			$dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
			$parameters ["sending_function"] = $dbt[1]['class'] . "::" . $dbt[1]['function'];
			unset($parameters['attachment_string']);
			$parametersString = jsonEncode($parameters);
			executeQuery("insert into email_log (client_id,email_id,contact_id,parameters,time_submitted,email_queue_identifier,log_entry) values (?,?,?,?,now(),?,?)",
				(empty($GLOBALS['gClientId']) ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']), $parameters['email_id'], $parameters['contact_id'], (empty($parametersString) ? "Too Big" : $parametersString),
				($errorMessage === true ? $parameters['email_queue_id'] : ""), $logEntry);
		}
	} catch
	(Exception $e) {
		$errorMessage = $e->getMessage() . ":" . $mail->Host . ", " . $mail->Username;
	}
	if (stripos($errorMessage, "Could not authenticate") !== false) {
		if ($emailCredentials['authentication_error'] > 10) {
			if ($emailCredentials['error_date'] != date("Y-m-d") && $emailCredentials['client_id'] != $GLOBALS['gDefaultClientId']) {
				$emailAddress = $GLOBALS['gClientRow']['email_address'];
				if (!empty($emailAddress)) {
					$body = "<p>The system is unable to send emails from '" . $emailCredentials['email_address'] . "' for client " . $GLOBALS['gClientName'] .
						"because of authentication errors. Go to System->Preferences->Email Credentials to reset the password.</p>";
					sendEmail(array("subject" => "Unable to send emails", "body" => $body, "email_address" => $emailAddress, "primary_client" => true, "send_immediately" => true));
					executeQuery("update email_credentials set error_date = current_date where email_credential_id = ?", $emailCredentials['email_credential_id']);
				}
				executeQuery("update email_credentials set authentication_error = 0 where email_credential_id = ?", $emailCredentials['email_credential_id']);
			}
		}
		if (!$GLOBALS['gAuthenticationErrorLogged']) {
			executeQuery("update email_credentials set authentication_error = authentication_error + 1 where email_credential_id = ?", $emailCredentials['email_credential_id']);
		}
		$GLOBALS['gAuthenticationErrorLogged'] = true;
	} else {
		executeQuery("update email_credentials set authentication_error = 0 where email_credential_id = ?", $emailCredentials['email_credential_id']);
	}
	if ($errorMessage === true && array_key_exists("email_addresses", $parameters) && empty($parameters['no_notifications'])) {
		$allUserIds = array();
		foreach ($parameters['email_addresses'] as $emailAddress) {
			$resultSet = executeReadQuery("select user_id,email_address from contacts join users using (contact_id) where users.inactive = 0 and (users.administrator_flag = 1 or users.superuser_flag = 1) and " .
				"(users.client_id = ? or users.superuser_flag = 1) and contacts.email_address = ?", $GLOBALS['gClientId'], $emailAddress);
			if ($row = getNextRow($resultSet)) {
				if (!in_array($row['user_id'], $allUserIds)) {
					createUserNotification($row['user_id'], $parameters['subject'], $parameters['body']);
					$allUserIds[] = $row['user_id'];
				}
			}
		}
	}
	return $errorMessage;
}

function sendTestEmail($parameters) {
	$toEmailAddress = $GLOBALS['gUserRow']['email_address'];
	try {
		$mail = new PHPMailer;
		$mail->Timeout = 60;
		$mail->From = $parameters['email_address'];
		if (array_key_exists("from_name", $parameters)) {
			$mail->FromName = $parameters['from_name'];
		} else {
			$mail->FromName = $parameters['full_name'];
		}
		if (array_key_exists("sender", $parameters)) {
			$mail->Sender = $parameters['sender'];
		} else {
			$mail->Sender = $parameters['email_address'];
		}
		if (!empty($parameters['security_setting'])) {
			$mail->SMTPSecure = $parameters['security_setting'];
		}
		if (!empty($parameters['smtp_port'])) {
			$mail->Port = $parameters['smtp_port'];
		}
		$mail->Body = "If this message is received successfully, the email credential '" . $parameters['email_credential_code'] . "' is working.";
		$mail->Subject = "Test Message";
		$mail->IsMail();
		$mail->IsSMTP();
		$mail->SMTPOptions = array(
			'ssl' => array(
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			)
		);
		$mail->Host = $parameters['smtp_host'];
		$mail->SMTPAuth = true;
		$mail->Username = $parameters['smtp_user_name'];
		$mail->Password = $parameters['smtp_password'];
		$mail->AuthType = $parameters['smtp_auth_type'];

		$mail->ClearAddresses();
		$mail->AddAddress($toEmailAddress);
		if (!$mail->Send()) {
			$result = str_ireplace("https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting", "", $mail->ErrorInfo) . ":" . $mail->Host . ", " . $mail->Username;
		} else {
			$result = true;
		}
	} catch (Exception $e) {
		$result = $e->getMessage() . ":" . $mail->Host . ", " . $mail->Username;
	}
	return $result;
}

function sendCredentialsError($parameters) {
	$notificationEmails = getNotificationEmails("DISTRIBUTOR_ERRORS");
	$notificationEmails = $notificationEmails ?: getNotificationEmails("RETAIL_STORE_ORDER_NOTIFICATION");
	$notificationEmails = $notificationEmails ?: array($GLOBALS['gClientRow']['email_address']);

	$parameters['subject'] = "Invalid Credentials for " . $parameters['integration_name'];
	$parameters['body'] = "<html><body><p>The integration '" . $parameters['integration_name'] . "' is not currently functioning because of invalid credentials. You will need to update credentials for this integration.</p>" .
		"<p>The error message is as follows:</p>\n<p>" . $parameters['error_message'] . "</p></body></html>";
	$parameters['email_addresses'] = $notificationEmails;

	// only send one email per integration per client per day
	$emailLogId = getFieldFromId("email_log_id", "email_log", "client_id", $GLOBALS['gClientId'],
		"date(time_submitted) = current_date and parameters like '{\"integration_name\":\"" . $parameters['integration_name'] . "%'");
	if(empty($emailLogId)) {
		sendEmail($parameters);
	}
}

function sendErrorLogEmail($parameters) {
    if(!is_array($parameters)) {
        $parameters = array($parameters);
    }
    $errorLogEmails = getCachedData("error_log_notification", "email_addresses");
    if (empty($errorLogEmails)) {
        $errorLogEmails = getNotificationEmails("ERROR_LOG", $GLOBALS['gDefaultClientId']);
        setCachedData("error_log_notification", "email_addresses", $errorLogEmails);
    }
    if(!empty($errorLogEmails)) {
        $parameters['email_addresses'] = $errorLogEmails;
        $parameters['no_copy'] = true;
        sendEmail($parameters);
    }
}

function loadPageCodes() {
	if (empty($GLOBALS['gPageCodes'])) {
		$resultSet = executeReadQuery("select page_id,page_code from pages where " . (empty($GLOBALS['gPageRow']['template_id']) ? "" : "template_id = " . $GLOBALS['gPageRow']['template_id'] . " and ") . "inactive = 0 and (publish_start_date is null or current_date >= publish_start_date) and " .
			"(publish_end_date is null or current_date <= publish_end_date) and client_id in (?,?)", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gPageCodes'][$row['page_code']] = $row['page_id'];
		}
	}
}

function getPage($pageId) {
	loadPageCodes();
	$pageRow = getCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_id", $pageId, true);
	if (empty($pageRow)) {
		$resultSet = executeReadQuery("select *,(select text_data from page_data where page_id = pages.page_id and template_data_id = (select template_data_id from template_data where data_name = 'primary_table_name')) primary_table_name from pages where page_id = ? and inactive = 0", $pageId);
		if ($row = getNextRow($resultSet)) {
			if ($GLOBALS['gInternalConnection']) {
				$row['internal_use_only'] = 0;
			}
			$pageTextSet = executeQuery("select * from page_text_chunks where page_id = ?", $row['page_id']);
			$row['page_text_chunks'] = array();
			while ($pageTextRow = getNextRow($pageTextSet)) {
				$row['page_text_chunks'][$pageTextRow['page_text_chunk_code']] = $pageTextRow['content'];
				foreach ($row as $fieldName => $fieldData) {
					$row[$fieldName] = str_replace("%" . strtolower($pageTextRow['page_text_chunk_code']) . "%", (is_scalar($pageTextRow['content']) ? $pageTextRow['content'] : ""), $fieldData);
				}
			}
			$pageControlsSet = executeQuery("select * from page_controls where page_id = ?", $row['page_id']);
			$row['page_controls'] = array();
			while ($pageControlsRow = getNextRow($pageControlsSet)) {
				$row['page_controls'][] = $pageControlsRow;
			}
			$row[$row['page_code']] = $row['page_id'];
			setCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_id", $row['page_id'], $row, 1, true);
			freeResult($resultSet);
			return $row;
		} else {
			return false;
		}
	} else {
		return $pageRow;
	}
}

###############
# canAccessPageCode - given a page code, return the permission level the user has to this program
###############
function canAccessPageCode($pageCode, $userId = "") {
	$pageId = $GLOBALS['gAllPageCodes'][$pageCode];
	if ($pageId) {
		return canAccessPage($pageId, $userId);
	}
	return _NOACCESS;
}

###############
# canAccessPage - given a page id, return the permission level the user has
###############
function canAccessPage($pageId, $userId = "") {
	if (empty($pageId)) {
		return _NOACCESS;
	}
	if (empty($userId)) {
		$userRow = $GLOBALS['gUserRow'];
	} else {
		$userRow = Contact::getUser($userId);
	}
	if ($userRow['superuser_flag']) {
		return _FULLACCESS;
	}
	$pageRow = getPage($pageId);
	if (!$pageRow) {
		return _NOACCESS;
	}
	if ($GLOBALS['gDevelopmentServer']) {
		$pageRow['domain_name'] = "";
	}
	if (!empty($pageRow['domain_name'])) {
		$validDomainNames = array($pageRow['domain_name']);
		if (substr($pageRow['domain_name'], 0, 4) == "www.") {
			$validDomainNames[] = substr($pageRow['domain_name'], 4);
		} else {
			$validDomainNames[] = "www." . $pageRow['domain_name'];
		}
		if (!in_array($_SERVER['HTTP_HOST'], $validDomainNames)) {
			return _NOACCESS;
		}
	}
	if ($GLOBALS['gInternalConnection']) {
		$pageRow['internal_use_only'] = 0;
	}

	if ($pageRow['internal_use_only'] && !$userRow['administrator_flag']) {
		return _NOACCESS;
	}

# Check to see if the client can use the page

	$clientPermissionLevel = 0;
	if ($GLOBALS['gClientId'] == $pageRow['client_id']) {
		$clientPermissionLevel = _FULLACCESS;
	} else if (!empty($GLOBALS['gClientId'])) {
		if (is_array($GLOBALS['gClientRow']['client_access']) && array_key_exists($pageId, $GLOBALS['gClientRow']['client_access'])) {
			$clientPermissionLevel = $GLOBALS['gClientRow']['client_access'][$pageId];
		} else {
			$clientTypeId = $GLOBALS['gClientRow']['client_type_id'];
			if (array_key_exists($pageId, $GLOBALS['gPageAccessRecords'])) {
				$usePermissionLevel = false;
				foreach ($GLOBALS['gPageAccessRecords'][$pageId] as $row) {
					if (empty($row['all_client_access']) && $row['client_type_id'] != $clientTypeId) {
						continue;
					}
					if ($usePermissionLevel === false || $row['permission_level'] > $usePermissionLevel) {
						$usePermissionLevel = $row['permission_level'];
					}
				}
				if ($usePermissionLevel !== false) {
					$clientPermissionLevel = $usePermissionLevel;
				}
			} else {
				$resultSet = executeReadQuery("select * from page_access where page_id = ? and (all_client_access = 1 or client_type_id = ?) " .
					"order by permission_level desc", $pageId, $clientTypeId);
				if ($row = getNextRow($resultSet)) {
					$clientPermissionLevel = $row['permission_level'];
				}
				freeResult($resultSet);
			}
		}
	}

# if user has full client access, they get the client's access permissions

	if (!$clientPermissionLevel) {
		return $clientPermissionLevel;
	}

	$permissionLevels = getPermissionLevels($pageId, $userRow);
	$administratorPermissionLevel = $permissionLevels['administrator'];
	$userPermissionLevel = $permissionLevels['user'];
	$publicPermissionLevel = $permissionLevels['public'];

	if ($GLOBALS['gUserRow']['administrator_flag'] && $GLOBALS['gUserRow']['full_client_access']) {
		$permissionLevel = $clientPermissionLevel;
	} else if ($userRow['administrator_flag']) {
		$permissionLevel = max($administratorPermissionLevel, $userPermissionLevel, $publicPermissionLevel);
	} else if ($GLOBALS['gLoggedIn']) {
		$permissionLevel = max($userPermissionLevel, $publicPermissionLevel);
	} else {
		$permissionLevel = $publicPermissionLevel;
	}
	$permissionLevel = min($clientPermissionLevel, $permissionLevel);

# Check permission allowed for the user group to subsystem, but ignore restrictions for CORE_SYSTEM subsystem.

	$systemSubsystemId = getReadFieldFromId("subsystem_id", "subsystems", "subsystem_code", "CORE_SYSTEM");
	if (!empty($userRow['user_id']) && !empty($userRow['user_group_ids']) && is_array($userRow['user_group_ids'])) {
		$resultSet = executeReadQuery("select user_group_id,permission_level from user_group_subsystem_access where subsystem_id <> ? and subsystem_id = (select subsystem_id from pages where page_id = ?) and " .
			"user_group_id in (" . implode(",", $userRow['user_group_ids']) . ") order by permission_level desc", $systemSubsystemId, $pageId);
		while ($row = getNextRow($resultSet)) {
			$permissionLevel = min($row['permission_level'], $clientPermissionLevel);
		}
		freeResult($resultSet);
	}

# Check permission allowed for the user group.

	if (!empty($userRow['user_id']) && !empty($userRow['user_group_ids']) && is_array($userRow['user_group_ids'])) {
        sort($userRow['user_group_ids']);
        $apcuKey = jsonEncode($userRow['user_group_ids']) . ":" . $pageId;
        $userGroupPermissionLevel = getCachedData("user_group_permission", $apcuKey);
        if ($userGroupPermissionLevel === false) {
	        $resultSet = executeReadQuery("select user_group_id, permission_level from user_group_access where user_group_id in " .
		        "(" . implode(",", $userRow['user_group_ids']) . ") and page_id = ? order by permission_level desc", $pageId);
	        while ($row = getNextRow($resultSet)) {
		        $userGroupPermissionLevel = min($row['permission_level'], $clientPermissionLevel);
	        }
	        freeResult($resultSet);
            setCachedData("user_group_permission", $apcuKey, $userGroupPermissionLevel, 48);
        }
        if ($userGroupPermissionLevel !== false) {
	        $permissionLevel = min($userGroupPermissionLevel, $clientPermissionLevel);
        }
	}

# Check permission allowed for the user to subsystem.

	if (!empty($userRow['user_id'])) {
		if (!empty($pageRow['subsystem_id']) && $pageRow['subsystem_id'] != $systemSubsystemId) {
			if (!array_key_exists("user_subsystem_access", $userRow) || !is_array($userRow['user_subsystem_access'])) {
				$resultSet = executeReadQuery("select permission_level from user_subsystem_access where user_id = ? and subsystem_id = ?",
					$userRow['user_id'], $pageRow['subsystem_id']);
				while ($row = getNextRow($resultSet)) {
					$permissionLevel = min($row['permission_level'], $clientPermissionLevel);
				}
				freeResult($resultSet);
			} else if (array_key_exists($pageRow['subsystem_id'], $userRow['user_subsystem_access'])) {
				$permissionLevel = min($userRow['user_subsystem_access'][$pageRow['subsystem_id']], $clientPermissionLevel);
			}
		}
	}

# Check permission allowed for the user, since this can also override the universal access and the user group access.

	if (!empty($userRow['user_id'])) {
		if (!array_key_exists("user_access", $userRow) || !is_array($userRow['user_access'])) {
			$resultSet = executeReadQuery("select permission_level from user_access where user_id = ? and page_id = ? order by permission_level desc",
				$userRow['user_id'], $pageId);
			while ($row = getNextRow($resultSet)) {
				$permissionLevel = min($row['permission_level'], $clientPermissionLevel);
			}
			freeResult($resultSet);
		} else if (array_key_exists($pageId, $userRow['user_access'])) {
			$permissionLevel = min($userRow['user_access'][$pageId], $clientPermissionLevel);
		}
	}

	return $permissionLevel;
}

##############
# getPermissionLevels - get User's permission for a page
##############
function getPermissionLevels($pageId, $userRow = array()) {
	loadPageCodes();
	if (empty($userRow)) {
		$userRow = $GLOBALS['gUserRow'];
	}
	$administratorPermissionLevel = "";
	$userPermissionLevel = "";
	$publicPermissionLevel = "";

	$pageRow = getPage($pageId);
	if (empty($pageRow)) {
		return array("administrator" => 0, "user" => 0, "public" => 0);
	}
	$pageClientId = $pageRow['client_id'];
	if (array_key_exists($pageId, $GLOBALS['gPageAccessRecords'])) {
		$pageRow['page_access_rows'] = $GLOBALS['gPageAccessRecords'][$pageId];
	} else {
		$resultSet = executeReadQuery("select * from page_access where page_id = ? order by page_access_id", $pageId);
		$pageAccess = array();
		while ($row = getNextRow($resultSet)) {
			$pageAccess[] = $row;
		}
		$pageRow['page_access_rows'] = $pageAccess;
	}
	foreach ($pageRow['page_access_rows'] as $row) {
		if ($row['client_type_id']) {
			$clientTypeId = $GLOBALS['gClientRow']['client_type_id'];
			if ($clientTypeId != $row['client_type_id']) {
				continue;
			}
		}
		if ($row['user_type_id'] && $userRow['user_type_id'] != $row['user_type_id']) {
			continue;
		}
		if ($row['user_group_id'] && !isInUserGroup($userRow['user_id'], $row['user_group_id'])) {
			continue;
		}
		if (!$row['all_client_access'] && $pageClientId != $GLOBALS['gClientId']) {
			continue;
		}
		if ($row['administrator_access'] && !$userRow['administrator_flag']) {
			continue;
		}
		if (!$row['public_access'] && !$GLOBALS['gLoggedIn']) {
			continue;
		}
		if (!$row['all_user_access'] && !$row['public_access'] && !$userRow['administrator_flag'] && empty($row['user_group_id']) && empty($row['user_type_id'])) {
			continue;
		}
		if ($row['administrator_access']) {
			$administratorPermissionLevel = (strlen($administratorPermissionLevel) == 0 ? $row['permission_level'] : min($row['permission_level'], $administratorPermissionLevel));
		} else if ($row['all_user_access'] || $row['user_type_id'] || $row['user_group_id']) {
			$userPermissionLevel = (strlen($userPermissionLevel) == 0 ? $row['permission_level'] : min($row['permission_level'], $userPermissionLevel));
		} else if ($row['public_access']) {
			$publicPermissionLevel = (strlen($publicPermissionLevel) == 0 ? $row['permission_level'] : min($row['permission_level'], $publicPermissionLevel));
		}
	}
	freeResult($resultSet);
	if (!$administratorPermissionLevel) {
		$administratorPermissionLevel = 0;
	}
	if (!$userPermissionLevel) {
		$userPermissionLevel = 0;
	}
	if (!$publicPermissionLevel) {
		$publicPermissionLevel = 0;
	}
	$permissionLevels = array("administrator" => $administratorPermissionLevel, "user" => $userPermissionLevel, "public" => $publicPermissionLevel);
	if (function_exists("_localPagePermissions")) {
		$permissionLevels = _localPagePermissions($pageId, $userRow, $permissionLevels);
	}
	return $permissionLevels;
}

function userGroupPages($userGroupId) {
	$pages = array();
	if (empty($userGroupId)) {
		return $pages;
	}
	$resultSet = executeReadQuery("select * from page_access where user_group_id = ? and permission_level > 0", $userGroupId);
	while ($row = getNextRow($resultSet)) {
		if (!array_key_exists($row['page_id'], $pages)) {
			$pages[$row['page_id']] = $row['permission_level'];
		} else if ($pages[$row['page_id']] > $row['permission_level']) {
			$pages[$row['page_id']] = $row['permission_level'];
		}
	}
	$resultSet = executeReadQuery("select * from user_group_subsystem_access where user_group_id = ? and permission_level > 0", $userGroupId);
	while ($row = getNextRow($resultSet)) {
		$pageSet = executeReadQuery("select * from pages where subsystem_id = ?", $row['subsystem_id']);
		while ($pageRow = getNextRow($pageSet)) {
			if (!array_key_exists($pageRow['page_id'], $pages)) {
				$pages[$pageRow['page_id']] = $row['permission_level'];
			} else if ($pages[$pageRow['page_id']] > $row['permission_level']) {
				$pages[$pageRow['page_id']] = $row['permission_level'];
			}
		}
	}
	$resultSet = executeReadQuery("select * from user_group_access where user_group_id = ? and permission_level > 0", $userGroupId);
	while ($row = getNextRow($resultSet)) {
		if (!array_key_exists($row['page_id'], $pages)) {
			$pages[$row['page_id']] = $row['permission_level'];
		} else if ($pages[$row['page_id']] > $row['permission_level']) {
			$pages[$row['page_id']] = $row['permission_level'];
		}
	}
	foreach ($pages as $pageId => $permissionLevel) {
		if (empty($permissionLevel)) {
			unset($pages[$pageId]);
		}
	}
	return $pages;
}

function isInUserGroup($userId, $userGroupId) {
	if (empty($userId) || empty($userGroupId)) {
		return false;
	}
	if ($userId == $GLOBALS['gUserId'] && array_key_exists("user_group_ids", $GLOBALS['gUserRow'])) {
		return in_array($userGroupId, $GLOBALS['gUserRow']['user_group_ids']);
	}
	$userGroupMemberId = getReadFieldFromId("user_group_member_id", "user_group_members", "user_id", $userId, "user_group_id = ?", $userGroupId);
	return (!empty($userGroupMemberId));
}

function isInUserGroupCode($userId, $userGroupCode) {
	if (empty($userId) || empty($userGroupCode)) {
		return false;
	}
	if ($userId == $GLOBALS['gUserId'] && array_key_exists("user_group_codes", $GLOBALS['gUserRow'])) {
		$userGroupCodes = array_filter(explode(",", $GLOBALS['gUserRow']['user_group_codes']));
		return in_array($userGroupCode, $userGroupCodes);
	}
	$userGroupMemberId = getReadFieldFromId("user_group_member_id", "user_group_members", "user_id", $userId, "user_group_id = (select user_group_id from user_groups where user_group_code = ? and client_id = ?)", $userGroupCode, $GLOBALS['gClientId']);
	return (!empty($userGroupMemberId));
}

###############
# getImageFilenameFromCode - given an image code, return the filename of the image
###############
function getImageFilenameFromCode($imageCode, $parameters = array()) {
	$imageId = getCachedData("imgid4code", $imageCode);
	if (empty($imageId)) {
		$imageId = getFieldFromId("image_id", "images", "image_code", $imageCode);
		setCachedData("imgid4code", $imageCode, $imageId, 24);
	}
	if (empty($imageId)) {
		if ($_GET['code'] == "header_logo") {
			if (file_exists("/images/coreware.png")) {
				return "/images/coreware.png";
			}
		}
	}
	return getImageFilename($imageId, $parameters);
}

$GLOBALS['gImageTypes'] = false;

###############
# getImageFilename - given an image ID, return the filename of the image
###############
function getImageFilename($imageId, $parameters = array()) {
	$imageId = str_replace("/", "", $imageId);
	if (!empty($parameters['image_type'])) {
		$parameters['image_type'] = strtoupper($parameters['image_type']);
	}
	if ($GLOBALS['gImageTypes'] === false) {
		$GLOBALS['gImageTypes'] = array();
		$resultSet = executeQuery("select * from image_types where inactive = 0");
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gImageTypes'][$row['image_type_code']] = $row;
		}
	}
	$cdnDomain = ((!empty($parameters['use_cdn'])) ? $GLOBALS['gCdnDomainName'] : "");
	if (empty($imageId)) {
		return $cdnDomain . (empty($parameters['default_image']) ? "/images/empty.jpg" : $parameters['default_image']);
	}

	$imageFilename = "";
	if (array_key_exists("image_type", $parameters)) {
		if (!array_key_exists($parameters['image_type'], $GLOBALS['gImageTypes'])) {
			$parameters['image_type'] = $parameters['alternate_image_type'];
		}
	}
	if (empty($parameters['image_type']) || !array_key_exists($parameters['image_type'], $GLOBALS['gImageTypes'])) {
		$parameters['image_type'] = "full";
	}
    $imageRow = array_key_exists("image_row", $parameters) ? $parameters['image_row'] : false;

	$cachedImageFilenames = getCachedData("img_filenames", $imageId);
	if (!is_array($cachedImageFilenames)) {
		$cachedImageFilenames = array();
	}
	if ($parameters['base_filename_only'] && array_key_exists("base_filename", $cachedImageFilenames)) {
		return $cachedImageFilenames['base_filename'];
	}
	if (!$parameters['base_filename_only'] && array_key_exists($parameters['image_type'], $cachedImageFilenames)) {
		$thisImageFilename = $cachedImageFilenames[$parameters['image_type']];
		if (file_exists($GLOBALS['gDocumentRoot'] . $thisImageFilename)) {
			return $cdnDomain . $thisImageFilename;
		}
	}
	if (!$imageRow) {
		$imageRow = getCachedData("image_row", $imageId);
		if (empty($imageRow)) {
			$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id = ?", $imageId);
			if (!$imageRow = getNextRow($resultSet)) {
				$imageRow = false;
			}
			freeResult($resultSet);
			if (!empty($imageRow)) {
				setCachedData("image_row", $imageId, $imageRow, 24);
			}
		}
	}
	if (!empty($imageRow['remote_storage'])) {
		$remoteImageTypeCode = strtoupper($parameters['image_type']);
		$linkUrl = getFieldFromId("link_url", "remote_image_type_data", "image_id", $imageRow['image_id'],
			"remote_image_type_id = (select remote_image_type_id from remote_image_types where remote_image_type_code = ? and client_id = ?)", $remoteImageTypeCode, $imageRow['client_id']);
		if (empty($linkUrl)) {
			$resultSet = executeQuery("select * from remote_image_type_data join remote_image_types using (remote_image_type_id) where internal_use_only = 0 and image_id = ? order by sort_order", $imageRow['image_id']);
			if ($row = getNextRow($resultSet)) {
				$linkUrl = $row['link_url'];
			}
		}
		if (!empty($linkUrl)) {
			$linkUrlPrefix = getPreference((empty($parameters['secure']) ? "" : "SECURE_") . "REMOTE_IMAGE_URL");
			if (!empty($linkUrlPrefix) && substr($linkUrl, 0, 1) != "/" && substr($linkUrlPrefix, -1) != "/") {
				$linkUrlPrefix .= "/";
			}
			$linkUrl = $linkUrlPrefix . $linkUrl;
		}
		if (empty($linkUrl)) {
			$linkUrl = (empty($parameters['default_image']) ? "/images/empty.jpg" : $parameters['default_image']);
		}
		if ($parameters['base_filename_only']) {
			$cachedImageFilenames['base_filename'] = $linkUrl;
		} else {
			$cachedImageFilenames[$parameters['image_type']] = $linkUrl;
		}
		setCachedData("img_filenames", $imageId, $cachedImageFilenames, (24 * 3));
		return $linkUrl;
	}
	$imageBaseFilename = "";
	if ($imageRow) {
		$hashCode = $imageRow['hash_code'];
		if (empty($hashCode)) {
			$hashCode = getRandomString();
			executeQuery("update images set hash_code = ? where image_id = ?", array($hashCode, $imageId));
		}
		$filename = $GLOBALS['gDocumentRoot'] . "/cache/image-full-" . $imageId . "-" . $hashCode . "." . (empty($imageRow['extension']) ? "jpg" : $imageRow['extension']);
		if (!file_exists($filename) || !empty($parameters['overwrite']) || filesize($filename) == 0) {
			if (!empty($imageRow['os_filename'])) {
				$imageContents = getExternalFileContents($imageRow['os_filename']);
				if (empty($imageContents)) {
					$imageContents = file_get_contents($GLOBALS['gDocumentRoot'] . (empty($parameters['default_image']) ? "/images/empty.jpg" : $parameters['default_image']));
				}
				file_put_contents($filename, $imageContents);
			} else {
				$handle = fopen($filename, 'w');
				fwrite($handle, getFieldFromId("file_content", "images", "image_id", $imageRow['image_id'], "client_id is not null"));
				fclose($handle);
			}
		}
		foreach ($GLOBALS['gImageTypes'] as $row) {
			$imageTypeCode = strtolower($row['image_type_code']);
			$imageFilename = $GLOBALS['gDocumentRoot'] . "/cache/image-" . $imageTypeCode . "-" . $imageId . "-" . $hashCode . "." . (empty($imageRow['extension']) ? "jpg" : $imageRow['extension']);
			if (!file_exists($imageFilename) || !empty($parameters['overwrite']) || filesize($imageFilename) == 0) {
				if ($imageRow['extension'] == "ico") {
					if (!empty($imageRow['os_filename'])) {
						$imageContents = getExternalFileContents($imageRow['os_filename']);
						if (empty($imageContents)) {
							$imageContents = file_get_contents($GLOBALS['gDocumentRoot'] . (empty($parameters['default_image']) ? "/images/empty.jpg" : $parameters['default_image']));
						}
						file_put_contents($filename, $imageContents);
					} else {
						$handle = fopen($imageFilename, 'w');
						fwrite($handle, getFieldFromId("file_content", "images", "image_id", $imageRow['image_id'], "client_id is not null"));
						fclose($handle);
					}
				} else {
					$image = new SimpleImage();
					$image->iCompression = $row['compression'];
					$image->loadImage($filename);
					$image->resizeMax($row['maximum_width'], $row['maximum_height']);
					$image->saveImage($imageFilename, (empty($imageRow['extension']) ? "jpg" : $imageRow['extension']));
				}
			}
		}
		if ($GLOBALS['gDevelopmentServer']) {
			$imageDomainName = "";
		} else {
			$imageDomainName = getPreference("IMAGE_DOMAIN_NAME");
		}
		$imageBaseFilename = $imageId . "-" . $hashCode . "." . (empty($imageRow['extension']) ? "jpg" : $imageRow['extension']);
		$imageFilename = (empty($imageDomainName) ? "" : (strpos($imageDomainName, "http") === false ? "https://" : "") . $imageDomainName) .
			"/cache/image-" . strtolower($parameters['image_type']) . "-" . $imageBaseFilename;
	}
	if (empty($imageFilename)) {
		$imageFilename = (empty($parameters['default_image']) ? "/images/empty.jpg" : $parameters['default_image']);
	}
	$cachedImageFilenames[$parameters['image_type']] = $imageFilename;
	$cachedImageFilenames['base_filename'] = $imageBaseFilename;
	setCachedData("img_filenames", $imageId, $cachedImageFilenames, (24 * 3));
	return ($parameters['base_filename_only'] ? $imageBaseFilename : $cdnDomain . $imageFilename);
}

###############
# getFileFilename - given a file ID, return the filename of the file, if it is publicly accessible
###############
function getFileFilename($fileId, $forceUpdate = false) {
	if (!is_numeric($fileId)) {
		$fileRow = getRowFromId("files", "file_code", $fileId);
	} else {
		$fileRow = getRowFromId("files", "file_id", $fileId);
	}
	if (empty($fileRow) || empty($fileRow['public_access'])) {
		return false;
	}

    if (!$forceUpdate) {
	    $filename = getCachedData("file_filename", $fileRow['file_id']);
	    if (!empty($filename) && file_exists($filename) && filesize($filename) > 0 && !$forceUpdate) {
		    return $filename;
	    }
    }
	$filename = "/cache/file-" . md5($fileRow['file_id'] . ":" . $fileRow['client_id'] . ":" . gethostname()) . "." . $fileRow['extension'];
	if (!file_exists($filename) || filesize($filename) == 0 || $forceUpdate) {
		if (empty($fileRow['os_filename'])) {
			$fileContents = $fileRow['file_content'];
		} else {
			$fileContents = getExternalFileContents($fileRow['os_filename']);
		}
        if (!empty($GLOBALS['gMaximumUploadFileSize']) && strlen($fileContents) > $GLOBALS['gMaximumUploadFileSize']) {
            $fileContents = "Maximum Size Exceeded";
        }
        file_put_contents($GLOBALS['gDocumentRoot'] . $filename, $fileContents);
	}

	setCachedData("file_filename", $fileRow['file_id'], $filename, (24 * 3));
	return $filename;
}

###############
# getRandomString - get a random string used as the seed for the password encoding
###############
function getRandomString($stringLength = 0, $validCharacters = "", $parameters = array()) {
	if (empty($stringLength)) {
		$string = md5(uniqid(mt_rand(), true) . serialize($_SERVER));
		if ($parameters['uppercase']) {
			$string = strtoupper($string);
		}
		return $string;
	}
	if ($stringLength == 64) {
		$string = hash("sha256", uniqid(mt_rand(), true) . serialize($_SERVER));
		if ($parameters['uppercase']) {
			$string = strtoupper($string);
		}
		return $string;
	}
	if (is_array($validCharacters) && empty($parameters)) {
		$parameters = $validCharacters;
		$validCharacters = false;
	}
	if (!is_array($parameters)) {
		$parameters = array();
	}
	$alphabet = (empty($validCharacters) ? "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789" : $validCharacters);
	$numericValues = "0123456789";
	$string = array();
	for ($i = 0; $i < $stringLength; $i++) {
		if ($i == 0 && !empty($parameters['number_start'])) {
			$n = mt_rand(0, strlen($numericValues) - 1);
			$string[] = $numericValues[$n];
			continue;
		}
		$n = mt_rand(0, strlen($alphabet) - 1);
		$string[] = $alphabet[$n];
	}
	$string = implode($string);
	if (!empty($parameters['uppercase'])) {
		$string = strtoupper($string);
	}
	return $string;
}

###############
# getFirstPart - return a portion of a string with ellipsis if there is more
###############
function getFirstPart($string, $length, $wordBreak = false, $showEllipsis = true) {
    if(empty($string) || !is_scalar($string)) {
        return $string;
    }
	if ($showEllipsis) {
		$length -= 3;
	}
	$shortString = "";
	if ($wordBreak) {
		$words = explode(" ", $string);
		foreach ($words as $thisWord) {
			if ((strlen($shortString) + strlen($thisWord)) < $length) {
				$shortString .= $thisWord . " ";
			} else {
				break;
			}
		}
	}
	if (!$wordBreak || ($shortString != $string && strlen($shortString) < 4)) {
		$shortString = (strlen($string) > $length ? mb_substr($string, 0, $length) : $string);
	}
	$shortString = trim($shortString);
	$shortString .= ($shortString != $string && $showEllipsis ? "..." : "");
	return $shortString;
}

###############
# getCrcValue - get CRC value for a string. Used by the browser to check to see if data changed.
###############
function getCrcValue($value, $noHash = false) {
	$value = str_replace("\r\n", "\n", $value);
	$newValue = "";
	for ($x = 0; $x < strlen($value); $x++) {
		$asciiValue = ord(substr($value, $x, 1));
		if ($asciiValue > 122) {
			continue;
		}
		if ($asciiValue < 32) {
			$newValue .= " ";
		} else {
			$newValue .= substr($value, $x, 1);
		}
	}
	$crc32Value = dechex(crc32($newValue));
	while (strlen($crc32Value) < 8) {
		$crc32Value = "0" . $crc32Value;
	}
	return ($noHash ? "" : "#") . strtoupper($crc32Value);
}

###############
# getStateArray - return an array of states
###############
function getStateArray($fiftyOnly = false) {
	$statesArray = array("AL" => "Alabama",
		"AK" => "Alaska",
		"AS" => "American Samoa",
		"AR" => "Arkansas",
		"AZ" => "Arizona",
		"AE" => "Armed Forces",
		"AA" => "Armed Forces Americas",
		"AP" => "Armed Forces Pacific",
		"CA" => "California",
		"CO" => "Colorado",
		"CT" => "Connecticut",
		"DC" => "District of Columbia",
		"DE" => "Delaware",
		"FL" => "Florida",
		"FM" => "Federated States of Micronesia",
		"GA" => "Georgia",
		"GU" => "Guam",
		"HI" => "Hawaii",
		"IA" => "Iowa",
		"ID" => "Idaho",
		"IL" => "Illinois",
		"IN" => "Indiana",
		"KS" => "Kansas",
		"KY" => "Kentucky",
		"LA" => "Louisiana",
		"MA" => "Massachusetts",
		"MD" => "Maryland",
		"ME" => "Maine",
		"MH" => "Marshall Islands",
		"MI" => "Michigan",
		"MN" => "Minnesota",
		"MO" => "Missouri",
		"MP" => "Northern Mariana Islands",
		"MS" => "Mississippi",
		"MT" => "Montana",
		"NC" => "North Carolina",
		"ND" => "North Dakota",
		"NE" => "Nebraska",
		"NH" => "New Hampshire",
		"NJ" => "New Jersey",
		"NM" => "New Mexico",
		"NV" => "Nevada",
		"NY" => "New York",
		"OH" => "Ohio",
		"OK" => "Oklahoma",
		"OR" => "Oregon",
		"PA" => "Pennsylvania",
		"PR" => "Puerto Rico",
		"PW" => "Palau",
		"RI" => "Rhode Island",
		"SC" => "South Carolina",
		"SD" => "South Dakota",
		"TN" => "Tennessee",
		"TX" => "Texas",
		"UT" => "Utah",
		"VA" => "Virginia",
		"VI" => "Virgin Islands",
		"VT" => "Vermont",
		"WA" => "Washington",
		"WI" => "Wisconsin",
		"WV" => "West Virginia",
		"WY" => "Wyoming");
	if ($fiftyOnly) {
		unset($statesArray['AA']);
		unset($statesArray['AE']);
		unset($statesArray['AP']);
		unset($statesArray['AS']);
		unset($statesArray['DC']);
		unset($statesArray['FM']);
		unset($statesArray['GU']);
		unset($statesArray['MH']);
		unset($statesArray['MP']);
		unset($statesArray['PR']);
		unset($statesArray['PW']);
		unset($statesArray['VI']);
	}
	return $statesArray;
}

###############
# getFullStateArray - return an array of states
###############
function getFullStateArray() {
	return array("Armed Forces Americas" => "Armed Forces Americas",
		"Armed Forces" => "Armed Forces",
		"Armed Forces Pacific" => "Armed Forces Pacific",
		"American Samoa" => "American Samoa",
		"Alabama" => "Alabama",
		"Alaska" => "Alaska",
		"Arizona" => "Arizona",
		"Arkansas" => "Arkansas",
		"California" => "California",
		"Colorado" => "Colorado",
		"Connecticut" => "Connecticut",
		"Delaware" => "Delaware",
		"District of Columbia" => "District of Columbia",
		"Florida" => "Florida",
		"Federated States of Micronesia" => "Federated States of Micronesia",
		"Georgia" => "Georgia",
		"Guam" => "Guam",
		"Hawaii" => "Hawaii",
		"Idaho" => "Idaho",
		"Illinois" => "Illinois",
		"Indiana" => "Indiana",
		"Iowa" => "Iowa",
		"Kansas" => "Kansas",
		"Kentucky" => "Kentucky",
		"Louisiana" => "Louisiana",
		"Maine" => "Maine",
		"Maryland" => "Maryland",
		"Massachusetts" => "Massachusetts",
		"Marshall Islands" => "Marshall Islands",
		"Michigan" => "Michigan",
		"Minnesota" => "Minnesota",
		"Mississippi" => "Mississippi",
		"Missouri" => "Missouri",
		"Northern Mariana Islands" => "Northern Mariana Islands",
		"Montana" => "Montana",
		"Nebraska" => "Nebraska",
		"Nevada" => "Nevada",
		"New Hampshire" => "New Hampshire",
		"New Jersey" => "New Jersey",
		"New Mexico" => "New Mexico",
		"New York" => "New York",
		"North Carolina" => "North Carolina",
		"North Dakota" => "North Dakota",
		"Ohio" => "Ohio",
		"Oklahoma" => "Oklahoma",
		"Oregon" => "Oregon",
		"Pennsylvania" => "Pennsylvania",
		"Puerto Rico" => "Puerto Rico",
		"Palau" => "Palau",
		"Rhode Island" => "Rhode Island",
		"South Carolina" => "South Carolina",
		"South Dakota" => "South Dakota",
		"Tennessee" => "Tennessee",
		"Texas" => "Texas",
		"Utah" => "Utah",
		"Vermont" => "Vermont",
		"Virginia" => "Virginia",
		"Virgin Islands" => "Virgin Islands",
		"Washington" => "Washington",
		"West Virginia" => "West Virginia",
		"Wisconsin" => "Wisconsin",
		"Wyoming" => "Wyoming");
}

###############
# getCountryArray - return an array of countries
###############
function getCountryArray($ofacRestricted = false) {
	$countryArray = array();
	$resultSet = executeReadQuery("select country_id,country_name from countries " . ($ofacRestricted ? "where country_id not in (select country_id from country_data where " .
			"country_data_type_id = (select country_data_type_id from country_data_types where country_data_type_code = 'OFAC_RESTRICTED') and integer_data = 1) " : "") .
		"order by sort_order,country_name");
	while ($row = getNextRow($resultSet)) {
		$countryArray[$row['country_id']] = $row['country_name'];
	}
	freeResult($resultSet);
	return $countryArray;
}

###############
# getDisplayName - construct the full name & company for display
###############

function getDisplayName($contactId = false, $parameters = array()) {
	if (empty($contactId)) {
		$contactId = $GLOBALS['gUserRow']['contact_id'];
	}
	return Contact::getDisplayName($contactId, $parameters);
}

###############
# getUserDisplayName - construct the full name for a user
###############

function getUserDisplayName($userId = "", $parameters = array()) {
	return Contact::getUserDisplayName($userId, $parameters);
}

###############
# isInSchedule - check to see if a date is in the repeat rules
###############
function isInSchedule($checkDate, $repeatRules) {
	$checkDate = date("Y-m-d", strtotime($checkDate));
	$parts = parseNameValues($repeatRules);
	if ($checkDate < date("Y-m-d", strtotime($parts['start_date']))) {
		return false;
	}
	if (array_key_exists("until", $parts)) {
		if ($checkDate > date("Y-m-d", strtotime($parts['until']))) {
			return false;
		}
	}
	if (array_key_exists("not", $parts)) {
		foreach (explode(",", $parts['not']) as $notDate) {
			if (!empty($notDate) && $checkDate == date("Y-m-d", strtotime($notDate))) {
				return false;
			}
		}
	}
	$validMonths = array();
	if (array_key_exists("bymonth", $parts)) {
		$validMonths = explode(",", $parts['bymonth']);
	}
	$validWeekdays = array();
	if (array_key_exists("byday", $parts)) {
		foreach (explode(",", $parts['byday']) as $thisPart) {
			if (empty($thisPart)) {
				continue;
			}
			$day = "";
			$weekday = "";
			if (strlen($thisPart) == 3) {
				$weekday = $thisPart;
			} else if (strlen($thisPart) == 4) {
				$day = substr($thisPart, 0, 1);
				$weekday = substr($thisPart, 1);
			} else if (is_numeric($thisPart)) {
				$day = $thisPart;
			}
			if (!empty($day) || !empty($weekday)) {
				$validWeekdays[] = array("day" => $day, "weekday" => $weekday);
			}
		}
	}
	if (empty($parts['interval'])) {
		$parts['interval'] = 1;
	}

	$thisMonth = date("n", strtotime($checkDate));
	$thisDay = date("j", strtotime($checkDate));
	if (count($validMonths) == 0 || in_array($thisMonth, $validMonths)) {
		if (count($validWeekdays) > 0) {
			$validDate = false;
			$thisWeekday = strtoupper(date("D", strtotime($checkDate)));
			$ordinalDay = ceil($thisDay / 7);
			foreach ($validWeekdays as $thisValidWeekdayParts) {
				$thisValidDay = $thisValidWeekdayParts['day'];
				$thisValidWeekday = $thisValidWeekdayParts['weekday'];
				if ($thisValidDay == "-") {
					$thisValidDay = 0;
					$calcDate = strtotime($thisMonth . "/01/" . date("Y", strtotime($checkDate)));
					while (date("n", $calcDate) == $thisMonth) {
						if (strtoupper(date("D", $calcDate)) == $thisWeekday) {
							$thisValidDay++;
						}
						$calcDate = strtotime(date("m/d/Y", $calcDate) . ' + 1 day');
					}
				}
				if (($thisValidDay == $thisDay && empty($thisValidWeekday)) ||
					($thisValidDay == $ordinalDay && $thisValidWeekday == $thisWeekday) ||
					(empty($thisValidDay) && $thisValidWeekday == $thisWeekday)) {
					$validDate = true;
					break;
				}
			}
			if (!$validDate) {
				return false;
			}
		}
	} else {
		return false;
	}

	$foundDates = 0;
	$startDate = date("Y-m-d", strtotime($parts['start_date']));
	$saveYear = date("Y", strtotime($parts['start_date']));
	$saveMonth = date("n", strtotime($parts['start_date']));
	$saveWeek = date("W", strtotime("+ 1 day", strtotime($parts['start_date'])));
	while (true) {
		if ($checkDate < $startDate || ($foundDates >= $parts['count'] && !empty($parts['count']))) {
			return false;
		}
		$thisYear = date("Y", strtotime($startDate));
		$thisMonth = date("n", strtotime($startDate));
		$thisWeek = date("W", strtotime("+1 day", strtotime($startDate)));
		$thisDay = date("j", strtotime($startDate));
		if ($parts['frequency'] == "WEEKLY" && $thisWeek != $saveWeek) {
			if ($foundDates > 0 && $parts['interval'] > 1) {
				$startDate = date("Y-m-d", strtotime("+" . ($parts['interval'] - 1) . " week", strtotime($startDate)));
			}
			$saveWeek = $thisWeek = date("W", strtotime("+1 day", strtotime($startDate)));
		}
		if ($parts['frequency'] == "MONTHLY" && $thisMonth != $saveMonth) {
			if ($foundDates > 0 && $parts['interval'] > 1) {
				$startDate = date("Y-m-d", strtotime("+" . ($parts['interval'] - 1) . " month", strtotime($startDate)));
			}
			$saveMonth = $thisMonth = date("n", strtotime($startDate));
		}
		if ($parts['frequency'] == "YEARLY" && $thisYear != $saveYear) {
			if ($foundDates > 0 && $parts['interval'] > 1) {
				$startDate = date("Y-m-d", strtotime("+" . ($parts['interval'] - 1) . " year", strtotime($startDate)));
			}
			$saveYear = $thisYear = date("Y", strtotime($startDate));
		}
		$validDate = false;
		if (count($validMonths) == 0 || in_array($thisMonth, $validMonths)) {
			if (count($validWeekdays) == 0) {
				$validDate = true;
			} else {
				$thisWeekday = strtoupper(date("D", strtotime($startDate)));
				$ordinalDay = ceil($thisDay / 7);
				foreach ($validWeekdays as $thisValidWeekdayParts) {
					$thisValidDay = $thisValidWeekdayParts['day'];
					$thisValidWeekday = $thisValidWeekdayParts['weekday'];
					if ($thisValidDay == "-") {
						$thisValidDay = 0;
						$calcDate = strtotime($thisMonth . "/01/" . date("Y", strtotime($checkDate)));
						while (date("n", $calcDate) == $thisMonth) {
							if (strtoupper(date("D", $calcDate)) == $thisWeekday) {
								$thisValidDay++;
							}
							$calcDate = strtotime(date("m/d/Y", $calcDate) . ' + 1 day');
						}
					}
					if (($thisValidDay == $thisDay && empty($thisValidWeekday)) ||
						($thisValidDay == $ordinalDay && $thisValidWeekday == $thisWeekday) ||
						(empty($thisValidDay) && $thisValidWeekday == $thisWeekday)) {
						$validDate = true;
						break;
					}
				}
			}
		}
		if ($validDate) {
			if ($checkDate == $startDate) {
				return true;
			} else {
				$foundDates++;
			}
		}
		if ($parts['frequency'] == "DAILY") {
			$startDate = date("Y-m-d", strtotime("+" . $parts['interval'] . " day", strtotime($startDate)));
		} else {
			$startDate = date("Y-m-d", strtotime("+1 day", strtotime($startDate)));
		}
	}
	return true;
}

###############
# parseNameValues - make an array of repeat rules from a string
###############
function parseNameValues($valueString) {
	$allowMultiple = array("not");
	$values = array();
	$valueArray = explode(";", trim($valueString));
	foreach ($valueArray as $thisValue) {
		$valueParts = explode("=", $thisValue);
		if (empty($valueParts[0])) {
			continue;
		}
		if (array_key_exists(strtolower($valueParts[0]), $values) && in_array(strtolower($valueParts[0]), $allowMultiple)) {
			$values[strtolower($valueParts[0])] .= "," . trim($valueParts[1]);
		} else {
			$values[strtolower($valueParts[0])] = trim($valueParts[1]);
		}
	}
	return $values;
}

###############
# autoVersion - calculate an autoversion string from the contents of a file
###############
function autoVersion($file) {
	if (substr($file, 0, 1) != "/") {
		$file = "/" . $file;
	}
	if (!file_exists($GLOBALS['gDocumentRoot'] . $file)) {
		return $file;
	}
	$mtime = filemtime($GLOBALS['gDocumentRoot'] . $file);
	while (strlen($mtime) < 10) {
		$mtime = "0" . $mtime;
	}
	$filename = preg_replace('{\\.([^./]+)$}', ".\$1", $file);
	return $GLOBALS['gCdnDomainName'] . $filename;
}

function htmlText($string) {
    $string = is_scalar($string) ? $string : "";
    return replaceLinks(htmlspecialchars($string, ENT_QUOTES, "UTF-8"));
}

function executeReadQuery($query, $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
	}
	if (empty($GLOBALS['gReadReplicaDatabase']) || $GLOBALS['gLogLiveQueries'] || ($GLOBALS['gDevelopmentServer'] && $GLOBALS['gLogDatabaseQueries'])) {
		return $GLOBALS['gPrimaryDatabase']->executeQuery($query, $parameters);
	} else {
		$GLOBALS['gReadOnlyAccessCount']++;
		return $GLOBALS['gReadReplicaDatabase']->executeQuery($query, $parameters);
	}
}

function executeQuery($query, $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
	}
    $retries = 0;
    while ($retries < 2) {
	    $retries++;
	    $resultSet = $GLOBALS['gPrimaryDatabase']->executeQuery($query, $parameters);
        if ($resultSet['sql_error_number'] != 1205) {
            break;
        }
        sleep(1);
    }
    return $resultSet;
}

function getNextRow($resultSet) {
	return $GLOBALS['gPrimaryDatabase']->getNextRow($resultSet);
}

function resetResultSet($resultSet) {
	return $GLOBALS['gPrimaryDatabase']->resetResultSet($resultSet);
}

function makeParameter($string) {
	return $GLOBALS['gPrimaryDatabase']->makeParameter($string);
}

function makeDateParameter($dateString) {
	return $GLOBALS['gPrimaryDatabase']->makeDateParameter($dateString);
}

function makeNumberParameter($numberString) {
	return $GLOBALS['gPrimaryDatabase']->makeNumberParameter($numberString);
}

function freeResult(&$resultSet) {
	$GLOBALS['gPrimaryDatabase']->freeResult($resultSet);
	$resultSet = null;
}

function freeReadResult(&$resultSet) {
	if (empty($GLOBALS['gReadReplicaDatabase']) || $GLOBALS['gLogLiveQueries'] || ($GLOBALS['gDevelopmentServer'] && $GLOBALS['gLogDatabaseQueries'])) {
		$GLOBALS['gPrimaryDatabase']->freeResult($resultSet);
	} else {
		$GLOBALS['gReadReplicaDatabase']->freeResult($resultSet);
	}
	$resultSet = null;
}

function getFieldFromId($fieldName, $tableName, $keyName = "", $keyValue = "", $extraWhere = "", $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		unset($parameters[1]);
		unset($parameters[2]);
		unset($parameters[3]);
		unset($parameters[4]);
	}
	return $GLOBALS['gPrimaryDatabase']->getFieldFromId($fieldName, $tableName, $keyName, $keyValue, $extraWhere, $parameters);
}

function updateFieldById($fieldName, $newValue, $tableName, $keyName, $keyValue, $extraWhere = "", $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		unset($parameters[1]);
		unset($parameters[2]);
		unset($parameters[3]);
		unset($parameters[4]);
		unset($parameters[5]);
	}
	return $GLOBALS['gPrimaryDatabase']->updateFieldById($fieldName, $newValue, $tableName, $keyName, $keyValue, $extraWhere, $parameters);
}

function getReadFieldFromId($fieldName, $tableName, $keyName, $keyValue, $extraWhere = "", $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		unset($parameters[1]);
		unset($parameters[2]);
		unset($parameters[3]);
		unset($parameters[4]);
	}
	if (empty($GLOBALS['gReadReplicaDatabase']) || $GLOBALS['gLogLiveQueries'] || ($GLOBALS['gDevelopmentServer'] && $GLOBALS['gLogDatabaseQueries'])) {
		return $GLOBALS['gPrimaryDatabase']->getFieldFromId($fieldName, $tableName, $keyName, $keyValue, $extraWhere, $parameters);
	} else {
		$GLOBALS['gReadOnlyAccessCount']++;
		return $GLOBALS['gReadReplicaDatabase']->getFieldFromId($fieldName, $tableName, $keyName, $keyValue, $extraWhere, $parameters);
	}
}

function getMultipleFieldsFromId($fieldNames, $tableName, $keyName, $keyValue, $extraWhere = "", $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		unset($parameters[1]);
		unset($parameters[2]);
		unset($parameters[3]);
		unset($parameters[4]);
	}
	return $GLOBALS['gPrimaryDatabase']->getMultipleFieldsFromId($fieldNames, $tableName, $keyName, $keyValue, $extraWhere, $parameters);
}

function getReadRowFromId($tableName, $keyName, $keyValue, $extraWhere = "", $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		unset($parameters[1]);
		unset($parameters[2]);
		unset($parameters[3]);
	}
	if (empty($GLOBALS['gReadReplicaDatabase']) || $GLOBALS['gLogLiveQueries'] || ($GLOBALS['gDevelopmentServer'] && $GLOBALS['gLogDatabaseQueries'])) {
		return $GLOBALS['gPrimaryDatabase']->getRowFromId($tableName, $keyName, $keyValue, $extraWhere, $parameters);
	} else {
		$GLOBALS['gReadOnlyAccessCount']++;
		return $GLOBALS['gReadReplicaDatabase']->getRowFromId($tableName, $keyName, $keyValue, $extraWhere, $parameters);
	}
}

function getRowFromId($tableName, $keyName, $keyValue, $extraWhere = "", $parameters = array()) {
	if (!is_array($parameters)) {
		$parameters = func_get_args();
		unset($parameters[0]);
		unset($parameters[1]);
		unset($parameters[2]);
		unset($parameters[3]);
	}
	return $GLOBALS['gPrimaryDatabase']->getRowFromId($tableName, $keyName, $keyValue, $extraWhere, $parameters);
}

function getControlRecords($parameterList = array()) {
	return $GLOBALS['gPrimaryDatabase']->getControlRecords($parameterList);
}

spl_autoload_register(function ($className) {
	if (file_exists($GLOBALS['gDocumentRoot'] . "/classes/class." . strtolower($className) . ".php")) {
		require_once $GLOBALS['gDocumentRoot'] . "/classes/class." . strtolower($className) . ".php";
		return true;
	}
});

function goToPage($pageId) {
	if (!empty($pageId)) {
		if (canAccessPage($pageId)) {
			$row = getPage($pageId);
			if ($row) {
				if (empty($row['domain_name']) || $row['domain_name'] == $_SERVER['HTTP_HOST']) {
					$linkUrl = (empty($row['link_name']) ? $row['script_filename'] : $row['link_name']);
					header("Location: " . $linkUrl . (empty($row['script_arguments']) ? "" : "?" . $row['script_arguments']));
					exit;
				}
			}
		}
	}
	return false;
}

function loadPage($pageId) {
	loadPageCodes();
	if (empty($pageId)) {
		return false;
	}
	$row = getPage($pageId);
	if ($row) {
		$GLOBALS['gPageId'] = $row['page_id'];
		$GLOBALS['gPageCode'] = $row['page_code'];
		$GLOBALS['gPageRow'] = $row;
		if ($GLOBALS['gPageRow']['client_id'] != $GLOBALS['gDefaultClientId'] && !empty($GLOBALS['gPageRow']['template_id'])) {
			$domainNamePages = getCachedData("domain_name_pages", $GLOBALS['gDomainNameRow']['domain_name_id']);
			if ($domainNamePages === false) {
				$domainNamePages = array();
				$resultSet = executeQuery("select * from domain_name_pages where domain_name_id = ?", $GLOBALS['gDomainNameRow']['domain_name_id']);
				while ($row = getNextRow($resultSet)) {
					$domainNamePages[$row['page_id']] = $row['page_id'];
				}
				setCachedData("domain_name_pages", $GLOBALS['gDomainNameRow']['domain_name_id'], $domainNamePages);
			}
			if (is_array($domainNamePages) && !empty($domainNamePages) && !array_key_exists($GLOBALS['gPageId'], $domainNamePages)) {
				$GLOBALS['gRedirectLogEntry'] .= "Redirected because of page not in domain name pages";
				header("Location: /");
				exit;
			}
		}
		$createWebUserLog = getPreference("create_web_user_log");
		if ($createWebUserLog && !$GLOBALS['gUserRow']['administrator_flag'] && !empty($GLOBALS['gWebUserId']) && empty($GLOBALS['gPageRow']['ignore_web_user_log']) && !empty($_SERVER['REMOTE_ADDR'])) {
			executeQuery("insert into web_user_pages (web_user_id,page_id,log_time,ip_address) values (?,?,now(),?)", $GLOBALS['gWebUserId'], $GLOBALS['gPageId'], $_SERVER['REMOTE_ADDR']);
		}
		if ($GLOBALS['gInternalConnection']) {
			$GLOBALS['gPageRow']['internal_use_only'] = 0;
		}
		if (!empty($row['proxy_page_code'])) {
			$GLOBALS['gPageCode'] = $row['proxy_page_code'];
		}
		if (empty($GLOBALS['gPageRow']['window_title'])) {
			$clientWindowTitle = getCachedData("client_window_title", $GLOBALS['gClientId'], true);
			if ($clientWindowTitle === false) {
				$clientWindowTitle = getFieldFromId("text_data", "custom_field_data", "primary_identifier", $GLOBALS['gClientRow']['contact_id'],
					"custom_field_id = (select custom_field_id from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and custom_field_code = 'WINDOW_TITLE' and client_id = ?)", $GLOBALS['gClientId']);
				setCachedData("client_window_title", $GLOBALS['gClientId'], $clientWindowTitle, 24, true);
			}
			if (empty($clientName)) {
				$clientWindowTitle = $GLOBALS['gClientName'];
			}
			$GLOBALS['gPageRow']['window_title'] = (empty($GLOBALS['gPageRow']['window_description']) ? $GLOBALS['gPageRow']['description'] : $GLOBALS['gPageRow']['window_description']) . " | " . $clientWindowTitle;
		}
		# Disable log usage for now
		if (false && $GLOBALS['gPageRow']['log_usage'] && !array_key_exists("ajax", $_GET)) {
			executeQuery("update pages set use_count = use_count + 1 where page_id = ?", $row['page_id']);
			if (!empty($_GET['source'])) {
				$sourceId = getFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['source']));
				if (!empty($sourceId)) {
					$resultSet = executeReadQuery("select * from page_sources where page_id = ? and source_id = ?", $GLOBALS['gPageId'], $sourceId);
					if ($sourceRow = getNextRow($resultSet)) {
						executeQuery("update page_sources set use_count = use_count + 1 where page_source_id = ?", $sourceRow['page_source_id']);
					} else {
						executeQuery("insert into page_sources (page_id,source_id,use_count) values (?,?,1)", $GLOBALS['gPageId'], $sourceId);
					}
					freeResult($resultSet);
				}
			}
			if (!empty($_SERVER['HTTP_REFERER'])) {
				$resultSet = executeReadQuery("select * from page_referers where page_id = ? and referer = ?", $GLOBALS['gPageId'], $_SERVER['HTTP_REFERER']);
				if ($refererRow = getNextRow($resultSet)) {
					executeQuery("update page_referers set use_count = use_count + 1 where page_referer_id = ?", $refererRow['page_referer_id']);
				} else {
					executeQuery("insert into page_referers (page_id,referer,use_count) values (?,?,1)", $GLOBALS['gPageId'], $_SERVER['HTTP_REFERER']);
				}
				freeResult($resultSet);
			}
		}
	} else {
		return false;
	}

	if (!empty($GLOBALS['gPageRow']['link_url'])) {
		header("HTTP/1.1 301 Moved Permanently");
		header("Location: " . $GLOBALS['gPageRow']['link_url']);
		exit;
	}

# Check to see if there are unread system notices. If so, go to the system notices viewer

	$ignoreSystemNotices = getPreference("IGNORE_SYSTEM_NOTICES");
	if (empty($ignoreSystemNotices) && empty($GLOBALS['gPreemptivePage']) && !$GLOBALS['gIgnoreNotices'] && $GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] && $GLOBALS['gPageRow']['template_id'] == $GLOBALS['gManagementTemplateId'] && canAccessPageCode("SYSTEMNOTICES")) {
		$systemNotices = new SystemNotices();
		$count = $systemNotices->getCount();
		if ($count > 0) {
			header("Location: /systemnotices.php");
			exit;
		}
	}

	$GLOBALS['gPageTemplateId'] = $GLOBALS['gPageRow']['template_id'];
	$GLOBALS['gTemplateRow'] = getCachedData("template_row", $GLOBALS['gPageRow']['template_id'], true);
	if ($GLOBALS['gTemplateRow'] === false) {
		$GLOBALS['gTemplateRow'] = getRowFromId("templates", "template_id", $GLOBALS['gPageRow']['template_id'], "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		setCachedData("template_row", $GLOBALS['gPageRow']['template_id'], $GLOBALS['gTemplateRow'], 24, true);
	}
	if ($GLOBALS['gTemplateRow']['template_code'] == "MANAGEMENT" && !empty($GLOBALS['gClientRow']['template_id'])) {
		$GLOBALS['gPageTemplateId'] = $GLOBALS['gPageRow']['template_id'] = $GLOBALS['gClientRow']['template_id'];
		$GLOBALS['gTemplateRow'] = getCachedData("template_row", $GLOBALS['gPageRow']['template_id'], true);
		if ($GLOBALS['gTemplateRow'] === false) {
			$GLOBALS['gTemplateRow'] = getRowFromId("templates", "template_id", $GLOBALS['gPageRow']['template_id'], "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
			setCachedData("template_row", $GLOBALS['gPageRow']['template_id'], $GLOBALS['gTemplateRow'], 24, true);
		}
	}
	$clientPageTemplateId = $GLOBALS['gClientPageTemplates'][$GLOBALS['gPageRow']['page_id']];
	if (!empty($clientPageTemplateId)) {
		$GLOBALS['gPageTemplateId'] = $GLOBALS['gPageRow']['template_id'] = $clientPageTemplateId;
		$GLOBALS['gTemplateRow'] = getCachedData("template_row", $GLOBALS['gPageRow']['template_id'], true);
		if ($GLOBALS['gTemplateRow'] === false) {
			$GLOBALS['gTemplateRow'] = getRowFromId("templates", "template_id", $GLOBALS['gPageRow']['template_id'], "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
			setCachedData("template_row", $GLOBALS['gPageRow']['template_id'], $GLOBALS['gTemplateRow'], 24, true);
		}
	}
	if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
		foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%" . strtolower($pageTextChunkCode) . "%", (is_scalar($pageTextChunkContent) ? $pageTextChunkContent : ""), $fieldData);
			}
		}
	}
	$templateFilename = "";
	if (!empty($GLOBALS['gTemplateRow']['include_crud'])) {
		$templateFilename = "templates/class.administration.php";
	} else if (!empty($GLOBALS['gTemplateRow']['filename']) && substr($GLOBALS['gTemplateRow']['filename'], -4) != ".txt") {
		$templateFilename = $GLOBALS['gDocumentRoot'] . "/templates/" . (empty($GLOBALS['gTemplateRow']['directory_name']) ? "" : $GLOBALS['gTemplateRow']['directory_name'] . "/") . "class." . strtolower($GLOBALS['gTemplateRow']['filename']) . ".php";
	} else if (!empty($GLOBALS['gTemplateRow']['content']) || !empty($GLOBALS['gTemplateRow']['filename'])) {
		$templateFilename = "templates/class.userdefined.php";
	}
	if ($templateFilename && !class_exists("Template", false)) {
		include_once $templateFilename;
	}
	if (!empty($GLOBALS['gTemplateRow']['addendum_filename']) && !class_exists("TemplateAddendum", false)) {
		$addendumTemplateFilename = $GLOBALS['gDocumentRoot'] . "/templates/" . (empty($GLOBALS['gTemplateRow']['directory_name']) ? "" : $GLOBALS['gTemplateRow']['directory_name'] . "/") . "class." . strtolower($GLOBALS['gTemplateRow']['addendum_filename']) . ".php";
		include_once $addendumTemplateFilename;
	}

	$permissionLevels = getPermissionLevels($GLOBALS['gPageId']);
	if (!$GLOBALS['gLoggedIn'] && !$permissionLevels['public']) {
		if ($_GET['ajax'] == "true") {
			$returnArray = array("error_message" => getSystemMessage("logged_out"));
			ajaxResponse($returnArray);
		}
		$_SESSION['GO_TO_URI'] = $_SERVER['REQUEST_URI'];
		saveSessionData();
		$loginLinkName = $GLOBALS['gPageRow']['login_script'];
		if (empty($loginLinkName)) {
			$loginLinkName = getPreference("LOGIN_LINK_NAME");
			if (empty($loginLinkName)) {
				$loginLinkName = "loginform.php";
			}
		}
		$GLOBALS['gRedirectLogEntry'] .= "Redirected because of unauthorized access: " . $_SERVER['REQUEST_URI'];
		addSecurityLog("", "REDIRECT", $GLOBALS['gRedirectLogEntry']);
		header("Location: http" . ($GLOBALS['gLocalExecution'] ? "" : "s") . "://" . $_SERVER['HTTP_HOST'] .
			(substr($loginLinkName, 0, 1) == "/" ? "" : "/") . $loginLinkName . (strpos($loginLinkName, "?") === false ? "?" : "&") . "url_page=login");
		exit;
	}

	$GLOBALS['gLinkUrl'] = "/" . trim((empty($GLOBALS['gPageRow']['link_name']) ? $GLOBALS['gPageRow']['script_filename'] : $GLOBALS['gPageRow']['link_name']), "/");
	$GLOBALS['gPermissionLevel'] = canAccessPage($GLOBALS['gPageId']);

	if ($GLOBALS['gPermissionLevel']) {
		if (false && $GLOBALS['gUserRow']['administrator_flag'] && $GLOBALS['gPageRow']['template_id'] == $GLOBALS['gManagementTemplateId'] && empty($_GET['ajax'])) {
			$lastPageId = getCachedData("last_recent_page_id", $GLOBALS['gUserId']);
			if ($lastPageId != $GLOBALS['gPageId'] && !in_array($GLOBALS['gPageRow']['page_code'], $GLOBALS['gIgnoreRecentPages'])) {
				executeQuery("insert into recent_pages (client_id,user_id,page_id) values (?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'], $GLOBALS['gPageId']);
				setCachedData("last_recent_page_id", $GLOBALS['gUserId'], $GLOBALS['gPageId'], 2);
				executeQuery("delete from recent_pages where recent_page_id not in (select recent_page_id from (select recent_page_id from recent_pages order by recent_page_id desc limit 100) delete_table)");
			}
		}

		if (!empty($GLOBALS['gUrlAlias'])) {
			$scriptArguments = getFieldFromId("script_arguments", "page_aliases", "page_id", $GLOBALS['gPageId'], "link_name = ?", $GLOBALS['gUrlAlias']);
		}
		if (empty($scriptArguments)) {
			$scriptArguments = $GLOBALS['gPageRow']['script_arguments'];
		}
		if (!empty($scriptArguments)) {
			parse_str($scriptArguments, $scriptValues);
			$_GET = array_merge($_GET, $scriptValues);
		}
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$resultSet = executeReadQuery("select * from query_log_directives where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
			if ($row = getNextRow($resultSet)) {
				$GLOBALS['gLogLiveQueries'] = true;
				$GLOBALS['gLogDatabaseQueries'] = true;
			}
		}

		$GLOBALS['gEmbeddablePage'] = false;
		if ($GLOBALS['gTemplateRow']['template_code'] == "EMBED") {
			$GLOBALS['gEmbeddedPage'] = true;
		} else {
			$GLOBALS['gEmbeddedPage'] = false;
		}
		if (!empty($GLOBALS['gPageRow']['script_filename'])) {
			if (substr($GLOBALS['gPageRow']['script_filename'], 0, 1) == "/") {
				$GLOBALS['gPageRow']['script_filename'] = substr($GLOBALS['gPageRow']['script_filename'], 1);
			}
			include $GLOBALS['gPageRow']['script_filename'];
		} else {
			include "showpage.php";
		}
		exit;
	}
	return false;
}

function loadAllMenus() {
	$resultSet = executeReadQuery("select * from menus where (client_id = ? or client_id = ?) order by client_id",
		$GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gAllMenus'][$row['menu_id']] = $row;
		$GLOBALS['gMenuCodes'][$row['menu_code']] = $row['menu_id'];
	}
}

function getMenuRow($menuId) {
	if (empty($GLOBALS['gAllMenus'])) {
		loadAllMenus();
	}
	if (array_key_exists($menuId, $GLOBALS['gAllMenus'])) {
		return $GLOBALS['gAllMenus'][$menuId];
	} else {
		$resultSet = executeReadQuery("select * from menus where menu_id = ? and (client_id = ? or client_id = ?) order by client_id desc", $menuId, $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		if ($row = getNextRow($resultSet)) {
			$GLOBALS['gMenuCodes'][$row['menu_code']] = $row['menu_id'];
			$GLOBALS['gAllMenus'][$row['menu_id']] = $row;
			freeResult($resultSet);
			return $row;
		} else {
			return false;
		}
	}
}

function getMenuByCode($menuCode, $parameters = array(), $topLevel = true) {
	if (empty($GLOBALS['gAllMenus'])) {
		loadAllMenus();
	}
	if (array_key_exists(strtoupper($menuCode), $GLOBALS['gMenuCodes'])) {
		$menuRow = getMenuRow($GLOBALS['gMenuCodes'][strtoupper($menuCode)]);
	} else {
		$resultSet = executeReadQuery("select * from menus where menu_code = ? and (client_id = ? or client_id = ?) order by client_id desc", strtoupper($menuCode), $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		if ($row = getNextRow($resultSet)) {
			$GLOBALS['gMenuCodes'][$row['menu_code']] = $row['menu_id'];
			$GLOBALS['gAllMenus'][$row['menu_id']] = $row;
			freeResult($resultSet);
			$menuRow = $row;
		} else {
			$menuRow = false;
		}
	}
	if ($menuRow) {
		return getMenu($menuRow['menu_id'], $parameters, $topLevel);
	} else {
		return "<ul><li>Menu Not Found</li></ul>";
	}
}

$GLOBALS['gPageSubsystems'] = array();

function getMenu($menuId, $parameters = array(), $topLevel = true) {
	if (empty($GLOBALS['gPageSubsystems'])) {
		$GLOBALS['gPageSubsystems'] = getCachedData("page_subsystems", "", true);
		if (!is_array($GLOBALS['gPageSubsystems'])) {
			$GLOBALS['gPageSubsystems'] = array();
		}
		if (empty($GLOBALS['gPageSubsystems'])) {
			$resultSet = executeReadQuery("select page_id,subsystem_id from pages");
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gPageSubsystems'][$row['page_id']] = $row['subsystem_id'];
			}
			setCachedData("page_subsystems", "", $GLOBALS['gPageSubsystems'], 24, true);
		}
	}
	if (!$topLevel) {
		$parameters['reverse'] = false;
	}
	if (empty($parameters['li_class'])) {
		$parameters['li_class'] = "menu-item";
	}
	if (!array_key_exists("menu_level", $parameters)) {
		$parameters['menu_level'] = 0;
	} else {
		$parameters['menu_level']++;
	}
	$menuMarkup = "";
	$menuArray = array();
	$menuRow = getMenuRow($menuId);
	if ($menuRow) {
		$menuArray = $menuRow;
		$menuArray['menu_items'] = array();
		if ($topLevel && !empty($parameters['list_id'])) {
			$menuMarkup .= "<ul id='" . strtolower($parameters['list_id']) . "'>";
		} else {
			$menuMarkup .= "<ul id='" . $parameters['id_prefix'] . "_menu-" . strtolower($menuRow['menu_code']) . "'>";
		}
		$menuItemNumber = 0;

		$menuContents = getCachedData("menu_contents", $menuRow['menu_id'] . "_" . $GLOBALS['gLanguageId']);
		if (empty($menuContents)) {
			$menuItemSet = executeReadQuery("select * from menu_contents join menu_items using (menu_item_id) where " .
				"menu_contents.menu_id = ? and (page_id is null or page_id in (select page_id from pages where inactive = 0 and (publish_start_date is null or " .
				"(publish_start_date is not null and current_date >= publish_start_date)) and " .
				"(publish_end_date is null or (publish_end_date is not null and current_date <= publish_end_date)))) order by sequence_number", $menuRow['menu_id']);
			$menuContents = array();
			while ($menuItemRow = getNextRow($menuItemSet)) {
				$menuContents[] = $menuItemRow;
			}
			setCachedData("menu_contents", $menuRow['menu_id'] . "_" . $GLOBALS['gLanguageId'], $menuContents);
		}
		if ($parameters['reverse']) {
			$menuContents = array_reverse($menuContents);
		}
		foreach ($menuContents as $menuItemRow) {
			$menuItemNumber++;
			$useMenuItem = false;
			if ($GLOBALS['gLoggedIn']) {
				if (!$GLOBALS['gUserRow']['administrator_flag'] && $menuItemRow['logged_in'] == 1) {
					$useMenuItem = true;
				}
			} else {
				if ($menuItemRow['not_logged_in'] == 1) {
					$useMenuItem = true;
				}
			}
			if ($GLOBALS['gUserRow']['administrator_flag'] && $menuItemRow['administrator_access'] == 1) {
				$useMenuItem = true;
			}
			if (!$useMenuItem) {
				continue;
			}
			if (!empty($menuItemRow['page_id'])) {
				if (!canAccessPage($menuItemRow['page_id'])) {
					$menuItemRow['page_id'] = "";
				}
			}
			$linkUrl = $menuItemRow['link_url'];
			if ($linkUrl == "%USERMENUS%" && $GLOBALS['gLoggedIn']) {
				$userMenuMarkup = "";
				$userMenuSet = executeReadQuery("select * from user_menus where user_id = ? order by sequence_number",
					$GLOBALS['gUserId']);
				$count = 0;
				while ($userMenuRow = getNextRow($userMenuSet)) {
					$bookmark = (strpos($userMenuRow['script_filename'], "url_page=show") !== false);
					$userMenuMarkup .= "<li data-user_menu_id='" . $userMenuRow['user_menu_id'] . "' class='user-menu-item " . $parameters['li_class'] . ($userMenuRow['script_filename'] == "/" . $GLOBALS['gPageRow']['script_filename'] ||
						$userMenuRow['script_filename'] == "/" . $GLOBALS['gPageRow']['link_name'] ? " active-page" : "") .
						"' id='" . $parameters['id_prefix'] . strtolower($menuRow['menu_code']) . "_" . $menuItemNumber++ . "' data-script_filename='" .
						$userMenuRow['script_filename'] . "'" . ($userMenuRow['separate_window'] == 1 ? " data-separate_window='YES'" : "") . (empty($userMenuRow['display_color']) ? "" : " style='background-color: " . $userMenuRow['display_color'] . "'") . " >" .
						"<a" . (empty($parameters['a_class']) ? "" : " class='" . $parameters['a_class'] . "'") . " href='" . $userMenuRow['script_filename'] . "'" . ($userMenuRow['separate_window'] == 1 ? " target='_blank'" : "") . ">" .
						$userMenuRow['link_title'] . "</a>" . ($bookmark ? "<span class='remove-user-menu fas fa-times'></span>" : "") . "</li>";
					$count++;
				}
				if (!empty($userMenuMarkup)) {
					if ($count > 10) {
						$menuMarkup .= "<li class='has-submenu menu-item' id='user_menus_menu'><a class='menu-item-link'><span class='menu-text'>Favorites</span></a><span class='submenu-pointer fas fa-chevron-right'></span>" .
							"<ul id='_user_menus_submenu'>" . $userMenuMarkup . "</ul></li>";
					} else {
						$menuMarkup .= $userMenuMarkup;
					}
				}

				freeResult($userMenuSet);
				continue;
			}

			if (false && $linkUrl == "%RECENTPAGES%" && $GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag']) {
				$pagesDisplayed = array();
				$userMenuSet = executeReadQuery("select * from recent_pages where user_id = ? and client_id = ? order by log_time desc", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
				$count = 0;
				$recentMenuMarkup = "";
				while ($userMenuRow = getNextRow($userMenuSet)) {
					if (in_array($userMenuRow['page_id'], $pagesDisplayed)) {
						continue;
					}
					if (!canAccessPage($userMenuRow['page_id'])) {
						continue;
					}
					$thisPageRow = getRowFromId("pages", "page_id", $userMenuRow['page_id'], "client_id is not null");
					if (empty($thisPageRow)) {
						continue;
					}
					if (in_array($thisPageRow['page_code'], $GLOBALS['gIgnoreRecentPages'])) {
						continue;
					}
					$linkTitle = getReadFieldFromId("link_title", "menu_items", "page_id", $userMenuRow['page_id']);
					if (empty($linkTitle)) {
						continue;
					}
					$domainName = $thisPageRow['domain_name'];
					if (!empty($thisPageRow['link_name'])) {
						$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $thisPageRow['link_name'] . (empty($thisPageRow['script_arguments']) ? "" : "?" . $thisPageRow['script_arguments']);
					} else {
						$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $thisPageRow['script_filename'] . (empty($thisPageRow['script_arguments']) ? "" : "?" . $thisPageRow['script_arguments']);
					}
					$pagesDisplayed[] = $userMenuRow['page_id'];

					$recentMenuMarkup .= "<li class='recent-page " . $parameters['li_class'] . "' id='" . $parameters['id_prefix'] . "_recent_page_" . strtolower($row['recent_page_id']) . "' data-script_filename='" .
						$linkUrl . "'><a class='menu-item-link " . (empty($parameters['a_class']) ? "" : " " . $parameters['a_class']) . "' href='" . $linkUrl . "'" . "><span class='menu-text'>" . $linkTitle . "</span></a></li>";
					$count++;
					if ($count >= 10) {
						break;
					}
				}
				if (!empty($recentMenuMarkup)) {
					$menuMarkup .= "<li class='has-submenu menu-item' id='recent_pages_menu'><a class='menu-item-link'><span class='menu-text'>Recent Pages</span></a><span class='submenu-pointer fas fa-chevron-right'></span>" .
						"<ul id='_recent_page_submenu'>" . $recentMenuMarkup . "</ul></li>";
				}
				freeResult($userMenuSet);
				continue;
			}
			if (empty($menuItemRow['subsystem_id']) && !empty($menuItemRow['page_id'])) {
				$menuItemRow['subsystem_id'] = $GLOBALS['gPageSubsystems'][$menuItemRow['page_id']];
			}
			if (!empty($menuItemRow['subsystem_id']) && !in_array($menuItemRow['subsystem_id'], $GLOBALS['gClientSubsystems'])) {
				continue;
			}
			if (!empty($linkUrl)) {
				if (!startsWith($linkUrl, "http") && !startsWith($linkUrl, "javascript") && !startsWith($linkUrl, "tel:") && !startsWith($linkUrl, "mailto") && !startsWith($linkUrl, "#") && !startsWith($linkUrl, "/")) {
					$linkUrl = "/" . $linkUrl;
				}
			} else if (!empty($menuItemRow['page_id'])) {
				$pageRow = getPage($menuItemRow['page_id']);
				$domainName = $pageRow['domain_name'];
				if ($pageRow) {
					if (!empty($pageRow['link_name'])) {
						$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $pageRow['link_name'] . (empty($menuItemRow['query_string']) ? "" : "?" . $menuItemRow['query_string']);
					} else {
						$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $pageRow['script_filename'] . (empty($pageRow['script_arguments']) ? "" : "?" . $pageRow['script_arguments']);
						$linkUrl .= (empty($menuItemRow['query_string']) ? "" : (strpos("?", $linkUrl) === false ? "?" : "&") . $menuItemRow['query_string']);
					}
				}
			}
			$menuItemRow['href'] = $linkUrl;
			$menuArray['menu_items'][$menuItemRow['menu_item_id']] = $menuItemRow;
			if (!empty($parameters['include_top_level_subpointer'])) {
				if (empty($parameters['top_level_subpointer'])) {
					$parameters['top_level_subpointer'] = "<span class='submenu-pointer fas fa-chevron-right'></span>";
				}
			}

			$displayedContent = "<a class='" . $parameters['a_class'] . " menu-item-link' " .
				(empty($menuItemRow['display_color']) ? "" : " style='color: " . $menuItemRow['display_color'] . "'") . (empty($linkUrl) ? "" : " href='" . $linkUrl . "' ") . " >" .
				(empty($menuItemRow['image_id']) ? "<span class='menu-text'>" : "<img alt='menu image' src='" . getImageFilename($menuItemRow['image_id'], array("use_cdn" => true)) .
					"'><span class='image-menu-text'>") . $menuItemRow['link_title'] . "</span></a>";
			if (empty($menuItemRow['menu_id']) && empty($linkUrl)) {
				$displayedContent = $menuItemRow['content'];
			}
			$thisMenuMarkup = "<li class='menu-" . $menuId . " menu-item-" . $menuItemRow['menu_item_id'] .
				(empty($menuItemRow['menu_id']) ? "" : " has-submenu submenu-" . $menuItemRow['menu_id']) .
				" " . $parameters['li_class'] . ($menuItemRow['page_id'] == $GLOBALS['gPageId'] ? " active-page" : "") .
				" " . $menuItemRow['list_item_classes'] . "'" .
				" id='" . $parameters['id_prefix'] . (empty($menuItemRow['list_item_identifier']) ? strtolower($menuRow['menu_code']) . "_" . $menuItemNumber : $menuItemRow['list_item_identifier']) .
				"' data-script_filename='" . $linkUrl . "'" . ($menuItemRow['separate_window'] == 1 ? " data-separate_window='YES'" : "") . ">" .
				$displayedContent . (empty($menuItemRow['menu_id']) || ($topLevel && empty($parameters['include_top_level_subpointer'])) ? "" : $parameters['top_level_subpointer']);
			$submenu = false;
			if (!empty($menuItemRow['menu_id']) && $menuItemRow['menu_id'] != $menuId && $parameters['menu_level'] < 10) {
				$thisSubmenuMarkup = getMenu($menuItemRow['menu_id'], $parameters, false);
				if (!is_array($thisSubmenuMarkup)) {
					if (strpos($thisSubmenuMarkup, "<li") !== false) {
						$thisMenuMarkup .= $thisSubmenuMarkup;
						$submenu = true;
					}
				} else {
					$menuArray['menu_items'][$menuItemRow['menu_item_id']]['submenu'] = $thisSubmenuMarkup;
				}
			}
			if (!empty($linkUrl) || $submenu || !empty($menuItemRow['content'])) {
				$menuMarkup .= $thisMenuMarkup . "</li>";
			} else {
				continue;
			}
		}
		$menuMarkup .= "</ul>";
	}
	if ($parameters['raw_data']) {
		return $menuArray;
	} else {
		return $menuMarkup;
	}
}

function pageFunctionChoices($showInactive = false) {
	$pageFunctionChoices = array();
	$resultSet = executeReadQuery("select * from page_functions where page_id in (select page_id from pages where client_id = ? or client_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
	while ($row = getNextRow($resultSet)) {
		$displayName = getReadFieldFromId("description", "pages", "page_id", $row['page_id'], "client_id is not null") . " - " . $row['page_function_code'];
		$pageFunctionChoices[$row['page_function_id']] = array("key_value" => $row['page_function_id'], "description" => $displayName, "inactive" => false);
	}
	freeResult($resultSet);
	return $pageFunctionChoices;
}

function pageChoices($showInactive = false) {
	$pageChoices = getCachedData("page_choices", "page_choices:" . $GLOBALS['gUserRow']['superuser_flag']);
	if (empty($pageChoices)) {
		$pageChoices = array();
		$resultSet = executeReadQuery("select *,if(subsystem_id is null,if (core_page = 1,'Other',''),(select description from subsystems where subsystem_id = pages.subsystem_id)) subsystem from " .
			"pages where (client_id = ? or client_id = ?) order by subsystem,description", $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if ($row['client_id'] == $GLOBALS['gDefaultClientId'] && $GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
				if (!$GLOBALS['gUserRow']['superuser_flag']) {
					$pageAccessId = getReadFieldFromId("page_access_id", "page_access", "page_id", $row['page_id'], "all_client_access = 1");
					if (empty($pageAccessId)) {
						continue;
					}
				}
			}
			if (empty($row['inactive']) || $showInactive) {
				$pageChoices[$row['page_id']] = array("key_value" => $row['page_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "optgroup" => $row['subsystem']);
			}
		}
		setCachedData("page_choices", "page_choices:" . $GLOBALS['gUserRow']['superuser_flag'], $pageChoices, .1);
	}
	return $pageChoices;
}

function userChoices($showInactive = false) {
	$userChoices = array();
	$resultSet = executeReadQuery("select users.client_id,first_name,last_name,users.user_id,users.user_name,users.inactive,superuser_flag from contacts,users where " .
		"(users.client_id = ? or users.superuser_flag = 1) and contacts.contact_id = users.contact_id and administrator_flag = 1 order by first_name,last_name,business_name",
		$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		if ((empty($row['inactive']) && $GLOBALS['gClientId'] == $row['client_id']) || $showInactive) {
			$displayName = (empty($row['first_name']) && empty($row['last_name']) ? getUserDisplayName($row['user_id']) : $row['first_name'] . " " . $row['last_name']);
			$userChoices[$row['user_id']] = array("key_value" => $row['user_id'], "description" => $displayName, "inactive" => $row['inactive'] == 1 || $row['client_id'] != $GLOBALS['gClientId']);
		}
	}
	freeResult($resultSet);
	return $userChoices;
}

function allUserChoices($showInactive = false) {
	$userChoices = array();
	$resultSet = executeReadQuery("select users.client_id,first_name,last_name,users.user_id,users.user_name,users.inactive,superuser_flag from contacts,users where " .
		"(users.client_id = ? or users.superuser_flag = 1) and contacts.contact_id = users.contact_id order by first_name,last_name,business_name",
		$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		if ((empty($row['inactive']) && $GLOBALS['gClientId'] == $row['client_id']) || $showInactive) {
			$userChoices[$row['user_id']] = array("key_value" => $row['user_id'], "description" => getUserDisplayName($row['user_id']), "inactive" => $row['inactive'] == 1 || $row['client_id'] != $GLOBALS['gClientId']);
		}
	}
	freeResult($resultSet);
	return $userChoices;
}

function clientChoices($showInactive = false) {
	$clientChoices = array();
	foreach ($GLOBALS['gAllClientRows'] as $row) {
		if (!$GLOBALS['gUserRow']['superuser_flag'] && $GLOBALS['gUserRow']['client_id'] != $GLOBALS['gDefaultClientId']) {
			if ($row['client_id'] != $GLOBALS['gClientId']) {
				continue;
			}
		}
		if (empty($row['inactive']) || $showInactive) {
			$clientChoices[$row['client_id']] = array("key_value" => $row['client_id'], "description" => (empty($row['business_name']) ? getDisplayName($row['contact_id']) : $row['business_name']), "inactive" => $row['inactive'] == 1);
		}
	}
	return $clientChoices;
}

function eventChoices($showInactive = false) {
	return Events::eventChoices($showInactive);
}

/**
 * @param $simpleXmlElementObject
 * @param bool $ignoreXmlAttributes
 * @param int $recursionDepth
 * @return array|string
 */
function processXml($simpleXmlElementObject, $ignoreXmlAttributes = true, $recursionDepth = 0) {
	$callerProvidedSimpleXmlElementObject = null;
	if ($recursionDepth == 0) {
		$callerProvidedSimpleXmlElementObject = $simpleXmlElementObject;
	}

	$copyOfSimpleXmlElementObject = $simpleXmlElementObject;
	if ($simpleXmlElementObject instanceof SimpleXMLElement) {
		$simpleXmlElementObject = get_object_vars($simpleXmlElementObject);
	}

	if (is_array($simpleXmlElementObject)) {
		$resultArray = array();
		if (count($simpleXmlElementObject) <= 0) {
			return (trim(strval($copyOfSimpleXmlElementObject)));
		}

		foreach ($simpleXmlElementObject as $key => $value) {
			if (($ignoreXmlAttributes == true) && (is_string($key)) && ($key == "@attributes")) {
				continue;
			}

			$recursionDepth++;
			$resultArray[$key] = processXml($value, $ignoreXmlAttributes, $recursionDepth);

			$recursionDepth--;
		}

		if ($recursionDepth == 0) {
			$tempArray = $resultArray;
			$resultArray = array();
			$resultArray[$callerProvidedSimpleXmlElementObject->getName()] = $tempArray;
		}

		return ($resultArray);
	} else {
		return (trim(strval($simpleXmlElementObject)));
	}
}

function addDataLimitations($dataSource) {
	$resultSet = executeReadQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = 0",
		$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId']);
	while ($row = getNextRow($resultSet)) {
		$checkQuery = PlaceHolders::massageContent($row['query_text']);
		$dataSource->addFilterWhere($checkQuery);
	}
	freeResult($resultSet);
	$resultSet = executeReadQuery("select * from user_data_limitations where user_id = ? and page_id = ? and permission_level = 0",
		$GLOBALS['gUserId'], $GLOBALS['gPageId']);
	while ($row = getNextRow($resultSet)) {
		$checkQuery = PlaceHolders::massageContent($row['query_text']);
		$dataSource->addFilterWhere($checkQuery);
	}
	freeResult($resultSet);
}

function jsonEncode($returnArray, $options = 0) {
	if (is_object($returnArray)) {
		$returnArray = (array)$returnArray;
	}
	if (!is_array($returnArray)) {
		return $returnArray;
	}
	array_walk_recursive($returnArray, function (&$item) {
		if ($item === null) {
			$item = "";
		}
	});
	return json_encode($returnArray, $options | JSON_HEX_TAG | JSON_HEX_QUOT);
}

#################
#showSignificant - Format a number, showing only significant digits
#################
function showSignificant($input, $decimals = -1, $showComma = false) {
	$dontRound = false;
	if ($decimals < 0) {
		$decimals = 0;
		$dontRound = true;
	}
	if (empty($input)) {
		$input = "0";
	}
	$significantNumber = number_format($input, 10, ".", ($showComma ? "," : ""));
	if (empty($significantNumber)) {
		$significantNumber = "0";
	}
	$foundDecimal = false;
	while ((!empty($significantNumber) && !$foundDecimal && substr($significantNumber, -1) == "0") || substr($significantNumber, -1) == ".") {
		if (!$foundDecimal) {
			$foundDecimal = substr($significantNumber, -1) == ".";
		}
		$significantNumber = substr($significantNumber, 0, strlen($significantNumber) - 1);
	}
	if (empty($significantNumber)) {
		$significantNumber = "0";
	}
	if (!$dontRound) {
		$significantNumber = round($significantNumber, $decimals);
	}
	if (!empty($decimals)) {
		if (strpos($significantNumber, ".") === false) {
			$significantNumber .= ".";
		}
		while (strlen($significantNumber) < (strpos($significantNumber, ".") + $decimals + 1)) {
			$significantNumber .= "0";
		}
		while (strpos($significantNumber, ".") < (strlen($significantNumber) - $decimals - 1)) {
			$significantNumber .= substr($significantNumber, 0, strlen($significantNumber) - 1);
		}
	}
	return $significantNumber;
}

function processAction($actionRow, $oldValuesRow, $newValuesRow) {
	$actionTypeCode = getFieldFromId("action_type_code", "action_types", "action_type_id", $actionRow['action_type_id']);
	$actionTriggered = false;
	switch ($actionTypeCode) {
		case "USER_CREATED":
			$actionTriggered = (empty($oldValuesRow) && !empty($newValuesRow['user_id']));
			break;
		case "FORM_SUBMITTED":
			$actionTriggered = (empty($oldValuesRow) && $newValuesRow['form_definition_id'] == $actionRow['action_identifier']);
			break;
		case "PRODUCT_ORDERED":
			$actionTriggered = (empty($oldValuesRow) && $newValuesRow['product_id'] == $actionRow['action_identifier']);
			break;
		case "COST_BELOW":
			$actionTriggered = ($oldValuesRow['base_cost'] != $newValuesRow['base_cost'] && $newValuesRow['product_id'] == $actionRow['action_identifier'] && $oldValuesRow['base_cost'] >= $actionRow['action_text'] && $newValuesRow['base_cost'] < $actionRow['action_text']);
			break;
		case "COST_ABOVE":
			$actionTriggered = ($oldValuesRow['base_cost'] != $newValuesRow['base_cost'] && $newValuesRow['product_id'] == $actionRow['action_identifier'] && $oldValuesRow['base_cost'] <= $actionRow['action_text'] && $newValuesRow['base_cost'] > $actionRow['action_text']);
			break;
		case "CONTACT_TYPE_SET":
			$actionTriggered = (empty($oldValuesRow['contact_type_id']) && !empty($newValuesRow['contact_type_id']) && $newValuesRow['contact_type_id'] == $actionRow['action_identifier']);
			break;
		case "CONTACT_TYPE_REMOVED":
			$actionTriggered = (!empty($oldValuesRow['contact_type_id']) && empty($newValuesRow['contact_type_id']) && $oldValuesRow['contact_type_id'] == $actionRow['action_identifier']);
			break;
		case "USER_TYPE_SET":
			$actionTriggered = (empty($oldValuesRow['user_type_id']) && !empty($newValuesRow['user_type_id']) && $newValuesRow['user_type_id'] == $actionRow['action_identifier']);
			break;
		case "USER_TYPE_REMOVED":
			$actionTriggered = (!empty($oldValuesRow['user_type_id']) && empty($newValuesRow['user_type_id']) && $oldValuesRow['user_type_id'] == $actionRow['action_identifier']);
			break;
		case "OPT_IN_MAILING_LIST":
			$actionTriggered = ((empty($oldValuesRow['date_opted_in']) || !empty($oldValuesRow['date_opted_out'])) && !empty($newValuesRow['date_opted_in']) && empty($newValuesRow['date_opted_out']) && $newValuesRow['mailing_list_id'] == $actionRow['action_identifier']);
			break;
		case "OPT_OUT_MAILING_LIST":
			$actionTriggered = (empty($oldValuesRow['date_opted_out']) && !empty($newValuesRow['date_opted_out']) && $newValuesRow['mailing_list_id'] == $actionRow['action_identifier']);
			break;
		case "ADD_CATEGORY":
			$actionTriggered = (empty($oldValuesRow['category_id']) && !empty($newValuesRow['category_id']) && $newValuesRow['category_id'] == $actionRow['action_identifier']);
			break;
		case "REMOVE_CATEGORY":
			$actionTriggered = (!empty($oldValuesRow['category_id']) && empty($newValuesRow['category_id']) && $newValuesRow['category_id'] == $actionRow['action_identifier']);
			break;
		case "START_WORK_FLOW":
			$actionTriggered = (empty($oldValuesRow['work_flow_definition_id']) && !empty($newValuesRow['work_flow_definition_id']) && $newValuesRow['work_flow_definition_id'] == $actionRow['action_identifier']);
			break;
		case "DESIGNATION_DONATION":
			$actionTriggered = (empty($oldValuesRow['donation_id']) && !empty($newValuesRow['donation_id']) && $newValuesRow['designation_id'] == $actionRow['action_identifier']);
			break;
		case "ADMIN_CREATED_HELP_DESK_TICKET_NOTE":
			$administratorFlag = getFieldFromId("administrator_flag", "users", "user_id", $newValuesRow['user_id']);
			$actionTriggered = (empty($oldValuesRow['help_desk_public_note_id']) && !empty($newValuesRow['help_desk_public_note_id']) && !empty($administratorFlag));
			break;
		case "USER_CREATED_HELP_DESK_TICKET_NOTE":
			$administratorFlag = (empty($newValuesRow['user_id']) ? false : getFieldFromId("administrator_flag", "users", "user_id", $newValuesRow['user_id']));
			$actionTriggered = (empty($oldValuesRow['help_desk_public_note_id']) && !empty($newValuesRow['help_desk_public_note_id']) && empty($administratorFlag));
			break;
		case "HELP_DESK_TICKET_STATUS_CHANGED":
			$actionTriggered = ($newValuesRow['help_desk_status_id'] != $oldValuesRow['help_desk_status_id'] && $newValuesRow['help_desk_status_id'] == $actionRow['help_desk_status_id']);
			break;
		case "HELP_DESK_TICKET_ASSIGNED":
			$actionTriggered = (!empty($newValuesRow['user_id']) && empty($oldValuesRow['user_id']));
			break;
		case "PAUSE_SUBSCRIPTION":
			$actionTriggered = (empty($oldValuesRow['customer_paused']) && !empty($newValuesRow['customer_paused']));
			break;
		case "CANCEL_SUBSCRIPTION":
			$actionTriggered = (empty($oldValuesRow['inactive']) && !empty($newValuesRow['inactive'])) || ((!empty($newValuesRow['expiration_date']) && $newValuesRow['expiration_date'] <= date("Y-m-d")) && (empty($oldValuesRow['expiration_date']) || $oldValuesRow['expiration_date'] > date("Y-m-d")));
			break;
		case "CANCEL_EVENT_REGISTRATION":
			$actionTriggered = (empty($newValuesRow['event_registrant_id']) && !empty($oldValuesRow['event_registrant_id']));
			break;
		case "ORDER_STATUS_SET":
			$actionTriggered = ($newValuesRow['order_status_id'] != $oldValuesRow['order_status_id'] && $newValuesRow['order_status_id'] == $actionRow['action_identifier']);
			break;
        case "ALL_ORDER_ITEM_STATUS_SET":
            $actionTriggered = ($newValuesRow['order_item_status_id'] != $oldValuesRow['order_item_status_id'] && $newValuesRow['order_item_status_id'] == $actionRow['action_identifier']);
            if($actionTriggered) {
                $result = executeQuery("select order_item_id from order_items where order_id = ? and deleted = 0 and order_item_status_id <> ?", $oldValuesRow['order_id'], $actionRow['action_identifier']);
                if(getNextRow($result)) {
                    $actionTriggered = false;
                }
            }
            break;
		case "ADD_PRODUCT_TO_CATEGORY":
			$actionTriggered = ($newValuesRow['product_category_id'] == $actionRow['action_identifier'] && $newValuesRow['order_status_id'] != $oldValuesRow['product_category_id']);
			break;
	}
	if (!$actionTriggered) {
		return;
	}
	$substitutionValues = array();
	$newValuesRow = array_merge($oldValuesRow, $newValuesRow);
	switch ($actionTypeCode) {
		case "USER_CREATED":
			$substitutionValues = Contact::getContact($newValuesRow['contact_id'], true);
			break;
		case "FORM_SUBMITTED":
			$substitutionValues = array_merge(Contact::getContact($newValuesRow['contact_id']), getRowFromId("form_definitions", "form_definition_id", $newValuesRow['form_definition_id']));
			break;
		case "PRODUCT_ORDERED":
		case "COST_ABOVE":
		case "COST_BELOW":
			$substitutionValues = array_merge(Contact::getContact(getFieldFromId("contact_id", "orders", "order_id", $newValuesRow['order_id'])), ProductCatalog::getCachedProductRow($newValuesRow['product_id']));
			break;
		case "CONTACT_TYPE_SET":
			$substitutionValues = array_merge($newValuesRow, getRowFromId("contact_types", "contact_type_id", $newValuesRow['contact_type_id']));
			break;
		case "CONTACT_TYPE_REMOVED":
			$substitutionValues = array_merge($newValuesRow, getRowFromId("contact_types", "contact_type_id", $oldValuesRow['contact_type_id']));
			break;
		case "USER_TYPE_SET":
			$substitutionValues = array_merge($newValuesRow, getRowFromId("user_types", "user_type_id", $newValuesRow['user_type_id']));
			break;
		case "USER_TYPE_REMOVED":
			$substitutionValues = array_merge($newValuesRow, getRowFromId("user_types", "user_type_id", $oldValuesRow['user_type_id']));
			break;
		case "OPT_IN_MAILING_LIST":
		case "OPT_OUT_MAILING_LIST":
			$substitutionValues = array_merge(Contact::getContact($newValuesRow['contact_id']), getRowFromId("mailing_lists", "mailing_list_id", $newValuesRow['mailing_list_id']));
			break;
		case "ADD_CATEGORY":
			$substitutionValues = array_merge(Contact::getContact($newValuesRow['contact_id']), getRowFromId("categories", "category_id", $newValuesRow['category_id']));
			break;
		case "REMOVE_CATEGORY":
			$substitutionValues = array_merge(Contact::getContact($oldValuesRow['contact_id']), getRowFromId("categories", "category_id", $oldValuesRow['category_id']));
			break;
		case "START_WORK_FLOW":
			$substitutionValues = array_merge(Contact::getContact($newValuesRow['contact_id']), getRowFromId("work_flow_definitions", "work_flow_definition_id",
				$newValuesRow['work_flow_definition_id']), array("creator_user" => getUserDisplayName($newValuesRow['creator_user_id'])));
			break;
		case "ADMIN_CREATED_HELP_DESK_TICKET_NOTE":
		case "USER_CREATED_HELP_DESK_TICKET_NOTE":
			$substitutionValues = array_merge($newValuesRow, Contact::getUser($newValuesRow['user_id']));
			break;
		case "REMOVE_HELP_DESK_STATUS":
		case "HELP_DESK_TICKET_STATUS_CHANGED":
		case "HELP_DESK_TICKET_ASSIGNED":
		case "ORDER_STATUS_SET":
        case "ALL_ORDER_ITEM_STATUS_SET":
			$substitutionValues = array_merge($newValuesRow, Contact::getContact($newValuesRow['contact_id']));
			break;
        case "PAUSE_SUBSCRIPTION":
		case "CANCEL_SUBSCRIPTION":
			$substitutionValues = array_merge($newValuesRow, Contact::getContact($newValuesRow['contact_id']));
			$substitutionValues['description'] = getFieldFromId("description", "subscriptions", "subscription_id", $newValuesRow['subscription_id']);
			break;
		case "CANCEL_EVENT_REGISTRATION":
			$substitutionValues = array_merge($newValuesRow, Contact::getContact($newValuesRow['contact_id']));
			$substitutionValues['description'] = getFieldFromId("description", "events", "event_id", $newValuesRow['event_id']);
			break;
	}
	$actionResultCode = getFieldFromId("action_result_code", "action_results", "action_result_id", $actionRow['action_result_id']);
	$contactId = $substitutionValues['contact_id'];
	$userId = Contact::getContactUserId($contactId);
	$logEntry = "Action '" . getFieldFromId("description", "action_types", "action_type_id", $actionRow['action_type_id']) . "' triggered for Contact ID " . $contactId . " - " . getDisplayName($contactId) . "\n";
	$logEntry .= "Resulting action '" . getFieldFromId("description", "action_results", "action_result_id", $actionRow['action_result_id']) . "' taken.\n";

	switch ($actionResultCode) {
		case "ADD_TO_USER_GROUP":
			$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($userGroupId) && !empty($userId)) {
				$insertSet = executeQuery("insert ignore into user_group_members (user_id,user_group_id) values (?,?)", $userId, $userGroupId);
				if ($insertSet['affected_rows'] > 0) {
					$logEntry .= "User Group '" . getFieldFromId("description", "user_groups", "user_group_id", $userGroupId) . "' added to user.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			}
			break;
		case "REMOVE_FROM_USER_GROUP":
			$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($userGroupId) && !empty($userId)) {
				$deleteSet = executeQuery("delete from user_group_members where user_id = ? and user_group_id = ?", $userId, $userGroupId);
				if ($deleteSet['affected_rows'] == 0) {
					$logEntry .= "No action necessary.\n";
				} else {
					$logEntry .= "User Group '" . getFieldFromId("description", "user_groups", "user_group_id", $userGroupId) . "' removed from user.\n";
				}
			}
			break;
		case "ADD_MAILING_LIST":
			$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($mailingListId)) {
				$contactMailingListId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
				if (empty($contactMailingListId)) {
					executeQuery("insert into contact_mailing_lists (contact_id,mailing_list_id,date_opted_in,ip_address) values (?,?,now(),?)", $contactId, $mailingListId, $_SERVER['REMOTE_ADDR']);
					$logEntry .= "Mailing List '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "' added to contact.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			}
			break;
		case "REMOVE_MAILING_LIST":
			$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($mailingListId)) {
				$contactMailingListId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
				if (!empty($contactMailingListId)) {
					executeQuery("update contact_mailing_lists set date_opted_out = now() where date_opted_out is null and contact_mailing_list_id = ?", $contactMailingListId);
					$logEntry .= "Mailing List '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "' removed contact.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			}
			break;
		case "ADD_CATEGORY":
			$categoryId = getFieldFromId("category_id", "categories", "category_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($categoryId)) {
				$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $categoryId, "contact_id = ?", $contactId);
				if (empty($contactCategoryId)) {
					executeQuery("insert into contact_categories (contact_id,category_id) values (?,?)", $contactId, $categoryId);
					$logEntry .= "Contact put in category '" . getFieldFromId("description", "categories", "category_id", $categoryId) . "'.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			}
			break;
		case "REMOVE_CATEGORY":
			$categoryId = getFieldFromId("category_id", "categories", "category_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($categoryId)) {
				$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $categoryId, "contact_id = " . $contactId);
				if (!empty($contactCategoryId)) {
					executeQuery("delete from contact_categories where contact_category_id = ?", $contactCategoryId);
					$logEntry .= "Contact removed from category '" . getFieldFromId("description", "categories", "category_id", $categoryId) . "'.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			}
			break;
		case "START_WORK_FLOW":
			$workFlowDefinitionId = getFieldFromId("work_flow_definition_id", "work_flow_definitions", "work_flow_definition_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($workFlowDefinitionId)) {
				$workFlowInstanceId = getFieldFromId("work_flow_instance_id", "work_flow_instances", "work_flow_definition_id", $workFlowDefinitionId, "contact_id <=> ? and date_completed is null", $contactId);
				if (empty($workFlowInstanceId)) {
					$resultSet = executeQuery("insert into work_flow_instances (work_flow_definition_id,creator_user_id,start_date,contact_id) values " .
						"(?,?,now(),?)", $workFlowDefinitionId, $GLOBALS['gUserId'], $contactId);
					$workFlowInstanceId = $resultSet['insert_id'];
					$resultSet = executeQuery("select * from work_flow_details where work_flow_definition_id = ?", $workFlowDefinitionId);
					while ($row = getNextRow($resultSet)) {
						executeQuery("insert into work_flow_instance_details (work_flow_detail_code,work_flow_instance_id," .
							"task_type_id,task_description,email_id,email_address,sequence_number,work_flow_status_id,start_rules," .
							"days_required,user_id,user_group_id) values " .
							"(?,?,?,?,?,?,?,?,?,?,?,?,?)", $row['work_flow_detail_code'], $workFlowInstanceId, $row['task_type_id'],
							(empty($row['task_description']) ? $row['description'] : $row['task_description']), $row['email_id'],
							$row['email_address'], $row['sequence_number'], $row['work_flow_status_id'], $row['start_rules'], $row['days_required'],
							$row['user_id'], $row['user_group_id']);
					}
					freeResult($resultSet);
					$logEntry .= "Work flow '" . getFieldFromId("description", "work_flow_definitions", "work_flow_definition_id", $workFlowDefinitionId) . "' started.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			}
			break;
		case "SEND_EMAIL":
			$emailId = getFieldFromId("email_id", "emails", "email_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($emailId)) {
				$notificationCode = "";
				$emailAddress = $actionRow['result_text'];
				if (strpos($emailAddress, "@") === false) {
					$notificationCode = $emailAddress;
					$emailAddress = "";
				}
				sendEmail(array("email_id" => $emailId, "notification_code" => $notificationCode, "email_address" => $emailAddress, "substitutions" => $substitutionValues));
				$logEntry .= "Email '" . getFieldFromId("description", "emails", "email_id", $emailId) . "' sent to " . (empty($notificationCode) ? $emailAddress : "notification_code '" . $notificationCode . "'") . ".\n";
			} else {
				$logEntry .= "Email does not exist.\n";
			}
			break;
		case "EMAIL_CONTACT":
			$emailId = getFieldFromId("email_id", "emails", "email_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			$emailAddress = $substitutionValues['email_address'];
			if (!empty($emailId) && !empty($emailAddress)) {
				$dateField = strtolower(makeCode($actionRow['result_text']));
				if (empty($dateField) || !array_key_exists($dateField, $substitutionValues) || empty($substitutionValues[$dateField])) {
					$sendOnDate = "";
				} else {
					$sendOnDate = date("Y-m-d", strtotime($substitutionValues[$dateField]));
					if ($sendOnDate < date("Y-m-d")) {
						$sendOnDate = "";
					}
				}
				sendEmail(array("email_id" => $emailId, "send_after" => $sendOnDate, "contact_id" => $substitutionValues['contact_id'], "email_address" => $emailAddress, "substitutions" => $substitutionValues));
				$logEntry .= "Email '" . getFieldFromId("description", "emails", "email_id", $emailId) . "' sent to " . $emailAddress . (empty($sendOnDate) ? "" : " on " . date("m/d/Y", strtotime($sendOnDate))) . ".\n";
			} else {
				$logEntry .= "Email does not exist or no email address for contact.\n";
			}
			break;
		case "SET_HELP_DESK_STATUS":
			$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $substitutionValues['help_desk_entry_id']);
			$helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($helpDeskEntryId) && !empty($helpDeskStatusId)) {
				$updateSet = executeQuery("update help_desk_entries set help_desk_status_id = ? where client_id = ? and help_desk_entry_id = ? and help_desk_status_id is null", $helpDeskStatusId, $GLOBALS['gClientId'], $helpDeskEntryId);
				if ($updateSet['affected_rows'] > 0) {
					$logEntry .= "Help Desk Entry ID " . $helpDeskEntryId . " set to status '" . getFieldFromId("description", "help_desk_statuses", "help_desk_status_id", $helpDeskStatusId) . "'.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			} else {
				$logEntry .= "Unable to execute action.\n";
			}
			break;
		case "REMOVE_HELP_DESK_STATUS":
			$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $substitutionValues['help_desk_entry_id']);
			$helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($helpDeskEntryId) && !empty($helpDeskStatusId)) {
				$updateSet = executeQuery("update help_desk_entries set help_desk_status_id = null where help_desk_status_id = ? and client_id = ? and help_desk_entry_id = ?", $helpDeskStatusId, $GLOBALS['gClientId'], $helpDeskEntryId);
				if ($updateSet['affected_rows'] > 0) {
					$logEntry .= "Help Desk Entry ID " . $helpDeskEntryId . " status '" . getFieldFromId("description", "help_desk_statuses", "help_desk_status_id", $helpDeskStatusId) . "' cleared.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			} else {
				$logEntry .= "Unable to execute action.\n";
			}
			break;
		case "CLEAR_CONTACT_TYPE":
			$updateSet = executeQuery("update contacts set contact_type_id = null where contact_id = ?", $contactId);
			if ($updateSet['affected_rows'] > 0) {
				$logEntry .= "Contact type cleared for Contact ID " . $contactId . ".\n";
			} else {
				$logEntry .= "No action necessary. User type already cleared for Contact ID " . $contactId . ".\n";
			}
			break;
		case "CLEAR_USER_TYPE":
			$updateSet = executeQuery("update users set user_type_id = null where user_id = ?", $userId);
			if ($updateSet['affected_rows'] > 0) {
				$logEntry .= "User type cleared for User ID " . $userId . ".\n";
			} else {
				$logEntry .= "No action necessary. User type already cleared for User ID " . $userId . ".\n";
			}
			break;
		case "SET_CONTACT_TYPE":
			$contactTypeId = getFieldFromId("contact_type_id", "contact_types", "contact_type_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($contactTypeId)) {
				$updateSet = executeQuery("update contacts set contact_type_id = ? where contact_id = ?", $contactTypeId, $contactId);
				if ($updateSet['affected_rows'] > 0) {
					$logEntry .= "Contact type for Contact ID " . $contactId . " set to '" . getFieldFromId("description", "contact_types", "contact_type_id", $contactTypeId) . "'.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			} else {
				$logEntry .= "Unable to execute action.\n";
			}
			break;
		case "SET_USER_TYPE":
			$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_id", $actionRow['result_identifier'], "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			if (!empty($userTypeId)) {
				$updateSet = executeQuery("update users set user_type_id = ? where user_id = ?", $userTypeId, $userId);
				if ($updateSet['affected_rows'] > 0) {
					$logEntry .= "User type for User ID " . $userId . " set to '" . getFieldFromId("description", "user_types", "user_type_id", $userTypeId) . "'.\n";
				} else {
					$logEntry .= "No action necessary.\n";
				}
			} else {
				$logEntry .= "Unable to execute action.\n";
			}
			break;
		case "PAUSE_SUBSCRIPTION":
			if (empty($newValuesRow['customer_paused'])) {
				$dataTable = new DataTable("contact_subscriptions");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => array("customer_paused" => 1), "primary_id" => $newValuesRow['contact_subscription_id']));
				$logEntry .= "Subscription paused for Contact ID " . $contactId . " for subscription '" . getFieldFromId("description", "subscriptions", "subscription_id", $newValuesRow['subscription_id']) . ".\n";
			} else {
				$logEntry .= "No action necessary.\n";
			}
			updateUserSubscriptions($newValuesRow['contact_id']);
			break;
		case "CANCEL_SUBSCRIPTION":
			if (empty($newValuesRow['inactive'])) {
				$dataTable = new DataTable("contact_subscriptions");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => array("inactive" => 1), "primary_id" => $newValuesRow['contact_subscription_id']));
				$logEntry .= "Subscription cancelled for Contact ID " . $contactId . " for subscription '" . getFieldFromId("description", "subscriptions", "subscription_id", $newValuesRow['subscription_id']) . ".\n";
			} else {
				$logEntry .= "No action necessary.\n";
			}
			updateUserSubscriptions($newValuesRow['contact_id']);
			break;
		case "MARK_ORDER_READY_FOR_PICKUP":
			$returnArray = Order::markOrderReadyForPickup($newValuesRow['order_id'], $actionTypeCode != "ORDER_STATUS_SET");
			if (array_key_exists("error_message", $returnArray)) {
				$logEntry .= "Marking order ready for pickup failed: " . $returnArray['error_message'];
			} else {
				$logEntry .= "Order " . $newValuesRow['order_id'] . " marked ready for pickup: " . $returnArray['info_message'];
			}
			break;
		case "MARK_ORDER_PICKED_UP":
			$returnArray = Order::markOrderPickedUp($newValuesRow['order_id']);
			if (array_key_exists("error_message", $returnArray)) {
				$logEntry .= "Marking order picked up failed: " . $returnArray['error_message'];
			} else {
				$logEntry .= "Order " . $newValuesRow['order_id'] . " marked picked up: " . $returnArray['info_message'];
			}
			break;
        case "SET_ORDER_STATUS":
            if(Order::updateOrderStatus($oldValuesRow['order_id'], $actionRow['result_identifier'])) {
                $logEntry .= "Order status set for Order " . $newValuesRow['order_id'];
            } else {
                $logEntry .= "Setting Order status for Order " . $newValuesRow['order_id'] . " failed.";
            }
            break;
	}
	executeQuery("insert into action_log (client_id,user_id,log_entry) values (?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'], $logEntry);
}

function addProgramLog($logEntry, $programLogId = "") {
	if (is_array($logEntry)) {
		$logEntry = jsonEncode($logEntry);
	}
	$programName = (empty($_SERVER['REQUEST_URI']) ? $GLOBALS['gPageCode'] : $_SERVER['REQUEST_URI']);
	if (!empty($logEntry)) {
		if (mb_strlen($logEntry) > 4000000) {
			$logEntry = mb_substr($logEntry, 0, 4000000);
		}
	}
	if (!empty($programLogId)) {
		$originalLogEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $programLogId);
	}
	if (!empty($originalLogEntry)) {
		$logEntry = $originalLogEntry . "\n\n" . $logEntry;
		executeQuery("update program_log set log_entry = ? where program_log_id = ?", $logEntry, $programLogId);
	} else {
		$logEntry = str_replace("\\" . "u0022", '"', $logEntry);
		$resultSet = executeQuery("insert into program_log (client_id,program_name,user_id,ip_address,log_entry) values (?,?,?,?,?)",
			(empty($GLOBALS['gClientId']) ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']), $programName, $GLOBALS['gUserId'], $_SERVER['REMOTE_ADDR'], $logEntry);
		$programLogId = $resultSet['insert_id'];
	}
	return $programLogId;
}

function distanceBetweenZipCodes($fromZipCode, $toZipCode) {
	$fromPoint = getPointForZipCode($fromZipCode);
	$toPoint = getPointForZipCode($toZipCode);
	return calculateDistance($fromPoint, $toPoint);
}

function getPointForZipCode($zipCode) {
	$zipCode = substr($zipCode, 0, 5);
	$point = array();
	$resultSet = executeReadQuery("select * from postal_codes where country_id = 1000 and postal_code = ? and latitude is not null and longitude is not null", $zipCode);
	if ($row = getNextRow($resultSet)) {
		$point = array("latitude" => $row['latitude'], "longitude" => $row['longitude']);
	}
	return $point;
}

function getZipCodesInRadius($latitude, $longitude, $radius) {
	$postalCodeList = array();
	if (empty($latitude) || empty($longitude) || !is_numeric($latitude) || !is_numeric($longitude)) {
		return $postalCodeList;
	}
	if (!is_numeric($radius) || empty($radius)) {
		$radius = 5;
	}
	$parameters = array($latitude,$latitude,$latitude,$longitude,$longitude,$latitude,$latitude,$latitude,$longitude,$longitude,$radius);
	$queryString = "SELECT distinct(postal_code),((3958*3.1415926*sqrt((latitude - ?)*(latitude - ?) + cos(latitude/57.29578)*cos(? / 57.29578)*(longitude - ?)*(longitude - ?))/180)) distance FROM postal_codes WHERE " .
		"(3958*3.1415926*sqrt((latitude - ?)*(latitude - ?) + cos(latitude/57.29578)*cos(? / 57.29578)*(longitude - ?)*(longitude - ?))/180) <= ?";
	$resultSet = executeReadQuery($queryString,$parameters);
	while ($row = getNextRow($resultSet)) {
		$postalCodeList[$row['postal_code']] = $row['distance'];
	}
	return $postalCodeList;
}

function calculateDistance($point1, $point2, $unit = "") {
	if (empty($point1) || empty($point2) || !array_key_exists("latitude", $point1) || !array_key_exists("latitude", $point2) ||
		!array_key_exists("longitude", $point1) || !array_key_exists("longitude", $point2) || !is_numeric($point1['latitude']) ||
		!is_numeric($point1['longitude']) || !is_numeric($point2['latitude']) || !is_numeric($point2['longitude'])) {
		return false;
	}
	$radius = 3958;                    // Earth's radius (miles)
	$degreesPerRadian = 57.29578;    // Number of degrees/radian (for conversion)

	$distance = ($radius * pi() * sqrt(($point1['latitude'] - $point2['latitude'])
			* ($point1['latitude'] - $point2['latitude'])
			+ cos($point1['latitude'] / $degreesPerRadian)  // Convert these to
			* cos($point2['latitude'] / $degreesPerRadian)  // radians for cos()
			* ($point1['longitude'] - $point2['longitude'])
			* ($point1['longitude'] - $point2['longitude'])
		) / 180);

	switch ($unit) {
		case "feet":
			$distance = $distance * 5280;
			break;
		case "yard":
			$distance = $distance * 1760;
			break;
		case "meter":
			$distance = $distance * 1609.34;
			break;
		case "kilometer":
			$distance = $distance * 1.60934;
			break;
	}
	return $distance;  // Returned using the units used for $radius.
}

function getLanguageText($programTextCode, $tableName = "program_text", $columnName = "content", $primaryKey = "program_text_code") {
	$programTextChunks = getCachedData("program_text", "program_text", true);
	if (!is_array($programTextChunks)) {
		$programTextChunks = array();
		$resultSet = executeReadQuery("select * from program_text");
		while ($row = getNextRow($resultSet)) {
			$programTextChunks[strtolower($row['program_text_code'])] = $row['content'];
		}
		setCachedData("program_text", "program_text", $programTextChunks, 24, true);
	}
	$programText = "";
	$primaryIdentifier = strtoupper(md5($programTextCode));
	$GLOBALS['gNoTranslation'] = true;

	if ($tableName == "program_text" && $primaryKey == "program_text_code" && (array_key_exists(strtolower($primaryIdentifier), $programTextChunks) || array_key_exists(strtolower($programTextCode), $programTextChunks))) {
		$row = $programTextChunks[strtolower($primaryIdentifier)];
		if (empty($row)) {
			$row = $programTextChunks[strtolower($primaryIdentifier)];
		}
		if (is_array($row)) {
			reset($row);
			$primaryIdentifier = $row[key($row)];
			$programText = $row[$columnName];
		} else {
			$programText = $programTextChunks[strtolower($primaryIdentifier)];
		}
	} else {
		$resultSet = executeReadQuery("select * from " . $tableName . " where " . $primaryKey . " = ?", $primaryIdentifier);
		if ($row = getNextRow($resultSet)) {
			reset($row);
			$primaryIdentifier = $row[key($row)];
			$programText = $row[$columnName];
		}
		freeResult($resultSet);
	}

	if (strlen($programText) == 0) {
		try {
			$table = new DataTable($tableName);
			if (empty($primaryKey)) {
				$primaryKey = $table->getPrimaryKey();
			}
			$resultSet = executeReadQuery("select " . $columnName . " from " . $tableName . " where " . $primaryKey . " = ?", strtoupper($primaryIdentifier));
			if ($row = getNextRow($resultSet)) {
				$programText = $row[$columnName];
			}
			freeResult($resultSet);
		} catch (Exception $e) {
		}
	}

	if (strlen($programText) == 0) {
		$programText = str_replace("_", " ", $programTextCode);
		if ($tableName == "program_text") {
			$resultSet = executeReadQuery("select * from program_text where program_text_code = ?", $primaryIdentifier);
			if (!$row = getNextRow($resultSet)) {
				executeQuery("insert into program_text (program_text_code,content) values (?,?)", $primaryIdentifier, $programText);
				$programTextChunks[strtolower($primaryIdentifier)] = $programText;
				removeCachedData("program_text", "program_text", true);
			}
			freeResult($resultSet);
		}
	}

	if ($GLOBALS['gLanguageId'] != $GLOBALS['gEnglishLanguageId']) {
		$languageColumnId = "";
		if (array_key_exists($tableName, $GLOBALS['gLanguageColumns']) && array_key_exists($columnName, $GLOBALS['gLanguageColumns'][$tableName])) {
			$languageColumnId = $GLOBALS['gLanguageColumns'][$tableName][$columnName];
		}
		if (!empty($languageColumnId) && !empty($GLOBALS['gLanguageText'][$languageColumnId][$primaryIdentifier])) {
			$programText = $GLOBALS['gLanguageText'][$languageColumnId][$primaryIdentifier];
			$GLOBALS['gLanguageText'][$languageColumnId][$primaryIdentifier] = $programText;
		} else if (array_key_exists($tableName . "|" . $columnName, $GLOBALS['gTranslatableColumns']) || array_key_exists("|" . $columnName, $GLOBALS['gTranslatableColumns'])) {
			if (!empty($GLOBALS['gTextTranslations'][strtolower($programText)])) {
				$programText = $GLOBALS['gTextTranslations'][strtolower($programText)];
			}
		}
	}

	$GLOBALS['gNoTranslation'] = false;
	return $programText;
}

function hex2rgb($hex) {
	$hex = str_replace("#", "", $hex);
	if (strlen($hex) == 3) {
		$r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
		$g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
		$b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
	} else {
		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
	}
	return array($r, $g, $b);
}

function changeClient($clientId) {
	if (array_key_exists($clientId, $GLOBALS['gAllClientRows']) && !empty($clientId) && $clientId != $GLOBALS['gClientId']) {
		$GLOBALS['gClientRow'] = $GLOBALS['gAllClientRows'][$clientId];
		$GLOBALS['gClientName'] = getDisplayName($GLOBALS['gClientRow']['contact_id'], array("use_company" => true));
		$GLOBALS['gClientId'] = $clientId;
		$GLOBALS['gDevelopmentServer'] = !empty($GLOBALS['gClientRow']['development']);
		clearGlobals();
        ProductCatalog::resetStaticVariables();
		date_default_timezone_set((empty($GLOBALS['timezone']) ? 'America/Denver' : $GLOBALS['timezone']));
		$clientTimezone = getPreference("TIMEZONE");
		if (!empty($clientTimezone)) {
			date_default_timezone_set($clientTimezone);
			executeQuery("set time_zone = ?", $clientTimezone);
		}
		return true;
	}
	return false;
}

function clearGlobals() {
	$GLOBALS['gFieldFromIdValues'] = false;
	$GLOBALS['gSystemPreferences'] = array();
	$GLOBALS['gClientPreferences'] = array();
	$GLOBALS['gUserPreferences'] = array();
	$GLOBALS['gSystemMessages'] = false;
	$GLOBALS['gNotificationEmails'] = array();
	$GLOBALS['gSystemPreferencesClientId'] = $GLOBALS['gClientId'];
	$GLOBALS['gDepartmentProducts'] = false;
	$GLOBALS['gInventoryAdjustmentTypeId'] = false;
	$GLOBALS['gRestockAdjustmentTypeId'] = false;
	$GLOBALS['gSalesAdjustmentTypeId'] = false;
	unset($GLOBALS['gProductSalePriceContactTypeIds']);
	unset($GLOBALS['gProductSalePriceUserTypeIds']);
	unset($GLOBALS['gProductSalePrices']);
	unset($GLOBALS['gCalculateAllProductCatalog']);
}

function getNotificationEmails($notificationCode, $clientId = "") {
	if (empty($clientId)) {
		$clientId = $GLOBALS['gClientId'];
	}
	if (array_key_exists($notificationCode . ":" . $clientId, $GLOBALS['gNotificationEmails'])) {
		return $GLOBALS['gNotificationEmails'][$notificationCode . ":" . $clientId];
	}
	$emailArray = array();
	$resultSet = executeReadQuery("select * from notification_emails where notification_id = (select notification_id from notifications " .
		"where notification_code = ? and client_id = ? and inactive = 0)", strtoupper($notificationCode), $clientId);
	while ($row = getNextRow($resultSet)) {
		$emailArray[] = $row['email_address'];
	}
	$GLOBALS['gNotificationEmails'][$notificationCode . ":" . $clientId] = $emailArray;
	return $emailArray;
}

function getCSSFilename($cssFileCode, $overwrite = false) {
	$cssFileId = getFieldFromId("css_file_id", "css_files", "css_file_code", $cssFileCode);
	return createCSSFile($cssFileId, $overwrite);
}

function createCSSFile($cssFileId, $overwrite = false) {
	$cssRow = getRowFromId("css_files", "css_file_id", $cssFileId, "client_id is not null");
	if (empty($cssRow)) {
		return "";
	}
	$thisPage = new Page();
	$cssRow['content'] = $thisPage->replaceImageReferences($cssRow['content']);
	$cssContent = processCssContent($cssRow['content']);
	$cssFilename = "/cache/css-" . strtolower($GLOBALS['gAllClientRows'][$cssRow['client_id']]['client_code']) . "-" . strtolower($cssRow['css_file_code']) . ".css";
	$filename = $GLOBALS['gDocumentRoot'] . $cssFilename;
	if (!file_exists($filename) || $overwrite) {
		file_put_contents($filename, $cssContent);
	}
	return $cssFilename;
}

function processCssContent($cssContent) {
	$cacheKey = hash("sha256", $cssContent);
	$processedContent = getCachedData("processed_css", $cacheKey);
	if ($processedContent !== false) {
		return $processedContent;
	}
	$cssContentParts = array();
	$cssContentLines = getContentLines($cssContent);
	$thisChunk = "";
	foreach ($cssContentLines as $thisLine) {
		if (substr($thisLine, 0, 1) == "%" && substr($thisLine, -1) == "%") {
			continue;
		}
		if (startsWith($thisLine, "<style") || startsWith($thisLine, "</style")) {
			if (!empty($thisChunk)) {
				$cssContentParts[] = array("type" => "content", "data" => $thisChunk);
				$thisChunk = "";
			}
			$cssContentParts[] = array("type" => "element", "data" => $thisLine);
		} else {
			$thisChunk .= $thisLine . "\n";
		}
	}
	if (!empty($thisChunk)) {
		$cssContentParts[] = array("type" => "content", "data" => $thisChunk);
	}
	$errorFound = false;
	$cssContent = "";
	foreach ($cssContentParts as $thisPart) {
		if ($thisPart['type'] == "content") {
			$scss = new ScssPhp\ScssPhp\Compiler();
			try {
				$cssContent .= "\n" . $scss->compile($thisPart['data']) . "\n";
			} catch (Exception $e) {
				$GLOBALS['gPrimaryDatabase']->logError("Style processing error: " . $e->GetMessage());
				$cssContent .= "\n/* Style processing error: " . $e->GetMessage() . "*/\n";
				$errorFound = true;
			}
		} else {
			$cssContent .= $thisPart['data'];
		}
	}
	if (!$GLOBALS['gDevelopmentServer'] && !$errorFound) {
		$cssContent = CssMin::minify($cssContent);
	}
	setCachedData("processed_css", $cacheKey, $cssContent, 24 * 7);
	return $cssContent;
}

# System makes 4 attempts to get a Geocode
# Try #1 = address 1, address 2, city, state, postal code, country
# Try #2 = address 1, city, state, postal code, country
# Try #3 = address 2, city, state, postal code, country
# Try #4 = address 2, address 1, city, state, postal code, country

function getAddressGeocode($parameters) {
	if (empty($parameters['country_id'])) {
		$parameters['country_id'] = 1000;
	}
	$checkedAddresses = array();
	$validationStatusCode = "";
	$latitude = "";
	$longitude = "";
	$address = "";
	$validationStatus = 0;
	$apiKey = getPreference("GOOGLE_API_KEY");
	$debugLog = "Debug Log";
	$totalTrys = 5 + (empty($parameters['simple_only']) ? 2 : 0);
	for ($addressTry = 1; $addressTry <= $totalTrys; $addressTry++) {
		$debugLog .= "<br>" . "Try: " . $addressTry;
		$address = "";
		switch ($addressTry) {
			case 1:
				$address = $parameters['address_1'] . (empty($parameters['address_1']) ? "" : " ") . $parameters['address_2'];
				break;
			case 2:
				$address = $parameters['address_1'] . (empty($parameters['address_1']) ? "" : " ") . (!empty($parameters['address_2']) ? "#" : "") . $parameters['address_2'];
				break;
			case 3:
				$address = $parameters['address_1'];
				break;
			case 4:
				$address = $parameters['address_2'] . (empty($parameters['address_2']) ? "" : " ") . $parameters['address_1'];
				break;
			case 5:
				$address = (empty($address) || empty($parameters['address_2']) ? "" : ",") . $parameters['address_2'];
				break;
			case 6:
				$addressParts = explode(" ", $parameters['address_1']);
				array_pop($addressParts);
				$address = implode(" ", $addressParts) . (empty($parameters['address_1']) ? "" : " ") . $parameters['address_2'];
				break;
			case 7:
				$addressParts = explode(" ", $parameters['address_1']);
				array_pop($addressParts);
				$address = (empty($address) || empty($parameters['address_1']) ? "" : ",") . implode(" ", $addressParts);
				break;
		}
		if (is_numeric($address) || empty($address)) {
			continue;
		}
		$address = trim($address);
		$address .= (empty($address) || empty($parameters['city']) ? "" : ",") . $parameters['city'];
		if ($parameters['country_id'] == 1000) {
			$address .= (empty($address) || empty($parameters['state']) ? "" : ",") . $parameters['state'];
			$address .= (empty($address) || empty($parameters['postal_code']) ? "" : ",") . $parameters['postal_code'];
		} else {
			$address .= (empty($address) || empty($parameters['postal_code']) ? "" : ",") . $parameters['postal_code'];
			$address .= (empty($address) || empty($parameters['state']) ? "" : (empty($parameters['postal_code']) ? "," : " ")) . $parameters['state'];
			$address .= "," . getFieldFromId("country_name", "countries", "country_id", $parameters['country_id']);
		}
		if (in_array($address, $checkedAddresses)) {
			continue;
		}
		$debugLog .= "<br>" . $address;
		$checkedAddresses[] = $address;
		$url = "http" . (empty($apiKey) ? "" : "s") . "://maps.googleapis.com/maps/api/geocode/json?address=" . rawurlencode($address) . (empty($apiKey) ? "" : "&key=" . $apiKey);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$buffer = curl_exec($ch);
		curl_close($ch);
		$validateData = json_decode($buffer, true);
		$debugLog .= "<br>" . jsonEncode($validateData);

		if ($validateData['status'] == 'OVER_QUERY_LIMIT') {
			if (!empty($apiKey)) {
				// the keyed API returned over query limit, so try with non-key API
				$url = "http://maps.googleapis.com/maps/api/geocode/json?address=" . rawurlencode($address);
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$buffer = curl_exec($ch);
				curl_close($ch);
				$validateData = json_decode($buffer, true);
				$apiKey = "";
			} else {
				// if the non-key API returned over query limit, there's no use trying any other address versions
				$validationStatusCode = "APPROXIMATE";
				$validationStatus = 0;
				break;
			}
		}

		if ($validationStatus == 0 || $validateData['results'][0]['geometry']['location_type'] == "ROOFTOP") {
			$latitude = $validateData['results'][0]['geometry']['location']['lat'];
			$longitude = $validateData['results'][0]['geometry']['location']['lng'];
		}
		if ($validateData['results'][0]['geometry']['location_type'] == "ROOFTOP") {
			$validationStatusCode = "FOUND";
			$validationStatus = 1;
			break;
		} else {
			$validationStatusCode = "APPROXIMATE";
			$validationStatus = 0;
		}
	}
	$debugLog .= "<br>" . $addressTry . ":" . $address . ":" . $latitude . "," . $longitude . ":" . $validationStatusCode . ":" . $validationStatus;
	if ($parameters['debug']) {
		echo $debugLog . "<br>";
	}
	return array("used_address" => $address, "latitude" => $latitude, "longitude" => $longitude, "validation_status_code" => $validationStatusCode, "validation_status" => $validationStatus, "debug_log" => $debugLog);
}

function bytesToSize($bytes) {
	if (!is_numeric($bytes)) {
		return $bytes;
	}
	$kilobyte = 1024;
	$megabyte = $kilobyte * 1024;
	$gigabyte = $megabyte * 1024;
	$terabyte = $gigabyte * 1024;
	if (($bytes >= 0) && ($bytes < $kilobyte)) {
		return $bytes . ' B';
	} else if (($bytes >= $kilobyte) && ($bytes < $megabyte)) {
		return round($bytes / $kilobyte, 2) . ' KB';
	} else if (($bytes >= $megabyte) && ($bytes < $gigabyte)) {
		return round($bytes / $megabyte, 2) . ' MB';
	} else if (($bytes >= $gigabyte) && ($bytes < $terabyte)) {
		return round($bytes / $gigabyte, 2) . ' GB';
	} else if ($bytes >= $terabyte) {
		return round($bytes / $terabyte, 2) . ' TB';
	} else {
		return $bytes . ' B';
	}
}

function getMilliseconds() {
	return round(microtime(true) * 1000);
}

function formatPhoneNumber($phoneNumber) {
	$newPhone = "";
	foreach (str_split($phoneNumber) as $thisChar) {
		if ($thisChar < "0" || $thisChar > "9") {
			continue;
		}
		if (!empty($newPhone) || $thisChar > "1") {
			$newPhone .= $thisChar;
		}
	}
	if (strlen($newPhone) != 10) {
		return $phoneNumber;
	}
	return "(" . substr($newPhone, 0, 3) . ") " . substr($newPhone, 3, 3) . "-" . substr($newPhone, 6, 4);
}

function getCreditCardType($creditCardNumber) {
	$creditCardNumber = str_replace(" ", "", str_replace("-", "", $creditCardNumber));
	if (strlen($creditCardNumber) < 13 || strlen($creditCardNumber) > 16 || !is_numeric($creditCardNumber)) {
		return false;
	}
	$prefix1 = substr($creditCardNumber, 0, 1);
	$prefix2 = substr($creditCardNumber, 0, 2);
	$prefix3 = substr($creditCardNumber, 0, 3);
	$prefix4 = substr($creditCardNumber, 0, 4);
	switch ($prefix2) {
		case "34":
		case "37":
			return "AMEX";
		case "36":
			return "DINERSCLUB";
		case "38":
			return "CARTEBLANCHE";
		case "51":
		case "52":
		case "53":
		case "54":
		case "55":
			return "MASTERCARD";
	}
	switch ($prefix4) {
		case "2014":
		case "2149":
			return "ENROUTE";
		case "2131":
		case "1800":
			return "JCB";
		case "6011":
			return "DISCOVER";
	}
	switch ($prefix3) {
		case "300":
		case "301":
		case "302":
		case "303":
		case "304":
		case "305":
			return "DINERSCLUB";
	}
	switch ($prefix1) {
		case "3":
			return "JCB";
		case "4":
			return "VISA";
	}
	return false;
}

function generateSalutation($contactFields) {
	if ($contactFields['title'] == "Mrs" && !empty($contactFields['last_name'])) {
		return $contactFields['title'] . " " . $contactFields['last_name'];
	}
	if ((strpos($contactFields['title'], " and ") !== false || strpos($contactFields['title'], " & ") !== false) &&
		strpos($contactFields['first_name'], " and ") === false && strpos($contactFields['first_name'], " & ") === false) {
		return $contactFields['title'] . " " . $contactFields['last_name'];
	}
	if (strlen($contactFields['first_name']) > 1) {
		return $contactFields['first_name'];
	}
	if (!empty($contactFields['title'])) {
		return $contactFields['title'] . " " . $contactFields['last_name'];
	}
	if (strlen($contactFields['first_name']) == 1) {
		return "Friend";
	}
	return "Friends";
}

function isHtml($string) {
	preg_match("/<\/?\w+((\s+\w+(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?>/", $string, $matches);
	return (count($matches) > 0);
}

function isPCIPassword($password) {
	$minimumPasswordLength = getPreference("minimum_password_length");
	if (empty($minimumPasswordLength)) {
		$minimumPasswordLength = 10;
	}
	if (getPreference("PCI_COMPLIANCE")) {
		$noPasswordRequirements = false;
	} else {
		$noPasswordRequirements = getPreference("no_password_requirements");
	}
	if ($noPasswordRequirements) {
		return true;
	}
	if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).*$/', $password) || strlen($password) < $minimumPasswordLength) {
		return false;
	}
	return true;
}

function outputPDF($htmlText, $parameters = array()) {
	if (empty($htmlText) && !empty($parameters['fragment_id'])) {
		$htmlText = getFragmentFromId($parameters['fragment_id']);
		if (!array_key_exists("orientation",$parameters)) {
			$checkHtml = str_replace(" ", "", $htmlText);
			if (stripos($checkHtml,"size:landscape") !== false) {
				$parameters['orientation'] = "landscape";
			}
		}
	}
	if (empty($htmlText) && !empty($parameters['fragment_code'])) {
		$htmlText = getFragment($parameters['fragment_code']);
	}
	$htmlText = str_replace("/cache/image", "file:///" . $GLOBALS['gDocumentRoot'] . "/cache/image", $htmlText);
	if (!empty($parameters['substitutions']) && is_array($parameters['substitutions'])) {
		$htmlText = PlaceHolders::massageContent($htmlText, $parameters['substitutions']);
	}
	$randomString = getRandomString();
	$filename = $GLOBALS['gDocumentRoot'] . "/cache/" . $randomString . ".html";
	$pdfFilename = $GLOBALS['gDocumentRoot'] . "/cache/" . $randomString . ".pdf";
	file_put_contents($filename, $htmlText);
	$pageSize = $parameters['page_size'];
	if (empty($pageSize)) {
		$pageSize = "Letter";
	}
	$outputFilename = $parameters['output_filename'];
	if (empty($outputFilename)) {
		$outputFilename = "output.pdf";
	}
	$options = "-s " . $pageSize;
	if (!empty($parameters['orientation'])) {
		$options .= " -O " . ucwords(strtolower($parameters['orientation']));
	}
	if (file_exists("/usr/local/bin/wkhtmltopdf")) {
		$shellCommand = "/usr/local/bin/wkhtmltopdf " . $options . " " . $filename . " " . $pdfFilename;
	} else {
		$shellCommand = "wkhtmltopdf " . $options . " " . $filename . " " . $pdfFilename;
	}
	shell_exec($shellCommand);
	if (empty($parameters['leave_html_file'])) {
		unlink($filename);
	}
	if (!file_exists($pdfFilename)) {
		return false;
	}
	if ($parameters['get_filename']) {
		return $pdfFilename;
	} else if ($parameters['get_contents']) {
		$contents = file_get_contents($pdfFilename);
		unlink($pdfFilename);
		return $contents;
	} else if ($parameters['create_file']) {
		$contents = file_get_contents($pdfFilename);
		unlink($pdfFilename);
		if (empty($contents)) {
			return false;
		}
		$originalFilename = $parameters['filename'];
		if (empty($originalFilename)) {
			$originalFilename = "file.pdf";
		}
		$extension = "pdf";
		$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
		if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
			$maxDBSize = 1000000;
		}
		if (strlen($contents) < $maxDBSize) {
			$fileContent = $contents;
			$osFilename = "";
		} else {
			$fileContent = "";
			$osFilename = "/documents/tmp." . $extension;
		}
		$publicAccess = (empty($parameters['public_access']) ? 0 : 1);
		$allUserAccess = (empty($parameters['all_user_access']) ? 0 : 1);
		$resultSet = executeQuery("insert into files (file_id,client_id,description,date_uploaded,file_tag_id," .
			"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access," .
			"sort_order,version) values (null,?,?,now(),?,?,?,?,?,?,?,1,0,1)", $GLOBALS['gClientId'], (empty($parameters['description']) ? $originalFilename : $parameters['description']),
			$parameters['file_tag_id'], $originalFilename, $extension, $fileContent, $osFilename, $publicAccess, $allUserAccess);
		if (!empty($resultSet['sql_error'])) {
			return false;
		}
		$fileId = $resultSet['insert_id'];
		if (!empty($osFilename)) {
			putExternalFileContents($fileId, $extension, $contents);
		}
		return $fileId;
	} else {
		$pdfContent = file_get_contents($pdfFilename);
		if ($parameters['no_download']) {
			header('Content-type: application/pdf');
		} else {
			header('Content-Type: application/force-download');
			header('Content-Type: application/octet-stream', false);
			header('Content-Type: application/download', false);
		}
		header('Content-disposition: inline; filename="' . $outputFilename . '"');
		header('Cache-Control: public, must-revalidate, max-age=0');
		header('Pragma: public');
		header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime("+ 1 year")));
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		echo $pdfContent;
		unlink($pdfFilename);
	}
	return false;
}

function urlExists($urlPath) {
	$headers = get_headers($urlPath);
	return (stripos($headers[0], "200 OK") ? true : false);
}

function makeWebUserContact($contactId) {
	$createWebUserLog = getPreference("create_web_user_log");
	if (!$createWebUserLog) {
		return;
	}
	if (!empty($GLOBALS['gWebUserId']) && !empty($contactId) && !empty($_SERVER['REMOTE_ADDR'])) {
		$webUserContactId = getFieldFromId("contact_id", "web_users", "web_user_id", $GLOBALS['gWebUserId']);
		if (!empty($webUserContactId) && $webUserContactId != $contactId) {
			$GLOBALS['gWebUserId'] = "";
		}
		if (empty($GLOBALS['gWebUserId'])) {
			$GLOBALS['gWebUserId'] = getFieldFromId("web_user_id", "web_users", "contact_id", $GLOBALS['gUserRow']['contact_id']);
		}
		if (empty($GLOBALS['gWebUserId'])) {
			$resultSet = executeQuery("insert into web_users (ip_address,start_date) values (?,now())", $_SERVER['REMOTE_ADDR']);
			$GLOBALS['gWebUserId'] = $resultSet['insert_id'];
		}
		if (!empty($GLOBALS['gWebUserId'])) {
			setCoreCookie("web_user_id", $GLOBALS['gWebUserId'], (24 * 18250));
			executeQuery("update web_users set contact_id = ? where web_user_id = ?", $contactId, $GLOBALS['gWebUserId']);
		}
	}
}

function makeCode($string, $parameters = array()) {
    $string = is_scalar($string) ? $string : "";
	do {
		$string = str_replace("  ", " ", strtolower($string));
	} while (strpos($string, "  ") !== false);
	if (!empty($parameters['use_dash'])) {
		$string = str_replace(" ", "-", strtolower($string));
		$string = str_replace("_", "-", strtolower($string));
		$string = str_replace("/", "-", strtolower($string));
	} else {
		$string = str_replace(" ", "_", strtolower($string));
		if (empty($parameters['allow_dash'])) {
			$string = str_replace("-", "_", strtolower($string));
		}
	}
	$codeValue = "";
	for ($x = 0; $x < strlen($string); $x++) {
		$char = substr($string, $x, 1);
		if (strpos("abcdefghijklmnopqrstuvwxyz01234567890@_.-", $char) !== false) {
			$codeValue .= $char;
		}
	}
	do {
		$codeValue = str_replace("--", "-", strtolower($codeValue));
		$codeValue = str_replace("__", "_", strtolower($codeValue));
	} while (strpos($codeValue, "--") !== false || strpos($codeValue, "__") !== false);
	if (empty($parameters['lowercase'])) {
		$codeValue = strtoupper($codeValue);
	}
	return $codeValue;
}

function createImage($arrayKey, $parameters = array()) {
	if (is_array($arrayKey)) {
		$fileInformation = $arrayKey;
		if (empty($parameters)) {
			$parameters = $arrayKey;
		}
	} else if (array_key_exists($arrayKey, $_FILES)) {
		$fileInformation = $_FILES[$arrayKey];
	} else if (array_key_exists("file_information", $parameters)) {
		$fileInformation = $parameters['file_information'];
	}
	if (empty($fileInformation['tmp_name']) && empty($fileInformation['file_content'])) {
		return false;
	}
	$clientId = (empty($parameters['client_id']) ? $GLOBALS['gClientId'] : $parameters['client_id']);
	if (empty($parameters['source_id']) && !empty($parameters['source_code'])) {
		$parameters['source_id'] = getFieldFromId("source_id", "sources", "source_code", strtoupper($parameters['source_code']));
		if (empty($parameters['source_id'])) {
			$insertSet = executeQuery("insert into sources (client_id,source_code,description,internal_use_only) values (?,?,?,1)", $clientId, strtoupper($parameters['source_code']), ucwords(strtolower(str_replace("_", " ", $parameters['source_code']))));
			$parameters['source_id'] = $insertSet['insert_id'];
		}
	}
	$extension = (empty($fileInformation['extension']) ? (array_key_exists($fileInformation['type'], $GLOBALS['gMimeTypes']) ? $GLOBALS['gMimeTypes'][$fileInformation['type']] : "jpg") : $fileInformation['extension']);
	if (!empty($fileInformation['tmp_name']) && (!empty($parameters['maximum_width']) || !empty($parameters['maximum_height']))) {
		$image = new SimpleImage();
		$image->iCompression = (empty($parameters['compression']) ? 60 : $parameters['compression']);
		$image->loadImage($fileInformation['tmp_name']);
		$image->resizeMax($parameters['maximum_width'], $parameters['maximum_height']);
		$image->saveImage($fileInformation['tmp_name'], $extension);
	}

	$fullFileContent = $fileContent = (empty($fileInformation['tmp_name']) ? $fileInformation['file_content'] : file_get_contents($fileInformation['tmp_name']));
	$imageSize = strlen($fileContent);
	if ($imageSize == 0) {
		return false;
	}
	$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
	if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
		$maxDBSize = 1000000;
	}
	if ($imageSize < $maxDBSize || !empty($parameters['force_database'])) {
		$osFilename = "";
	} else {
		$fileContent = "";
		$osFilename = "/documents/tmp." . $extension;
	}
	$imageId = $parameters['image_id'];
	if (empty($imageId) && !empty($parameters['image_code'])) {
		$imageId = getFieldFromId("image_id", "images", "image_code", $parameters['image_code'], "client_id = ?", $clientId);
	}
	if (empty($fileInformation['filename'])) {
		$fileInformation['filename'] = $fileInformation['name'];
	}
	if (empty($fileInformation['name'])) {
		$fileInformation['name'] = $fileInformation['filename'];
	}
	if (empty($fileInformation['description'])) {
		$fileInformation['description'] = $fileInformation['filename'];
	}
	if (empty($fileInformation['name'])) {
		$fileInformation['name'] = "Image";
	}
	if (empty($fileInformation['filename'])) {
		$fileInformation['filename'] = "image." . $fileInformation['extension'];
	}
	if (empty($fileInformation['description'])) {
		$fileInformation['description'] = "Image";
	}
	if (empty($imageId)) {
		$resultSet = executeQuery("select * from images where client_id = ? and image_size = ?" .
			(empty($parameters['image_code']) ? "" : " and image_code = " . makeParameter($parameters['image_code'])), $clientId, $imageSize);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['os_filename'])) {
				$existingFileContent = $row['file_content'];
			} else {
				$existingFileContent = getExternalFileContents($row['os_filename']);
			}
			if (!empty($existingFileContent) && $fullFileContent == $existingFileContent) {
				return $row['image_id'];
			}
		}
        if (empty($osFilename) && empty($fileContent)) {
            return false;
        }
		$resultSet = executeQuery("insert into images (client_id,image_code,description,detailed_description,link_url,os_filename,extension,filename,file_content,image_size,source_id,user_id,date_uploaded) values (?,?,?,?,?, ?,?,?,?,?, ?,?,now())",
			$clientId, $parameters['image_code'], (empty($parameters['description']) ? $fileInformation['name'] : $parameters['description']), $parameters['detailed_description'], $parameters['link_url'], $osFilename, $extension,
			$fileInformation['name'], $fileContent, $imageSize, $parameters['source_id'], $GLOBALS['gUserId']);
		$imageId = $resultSet['insert_id'];
	} else {
		if (empty($parameters['image_code'])) {
			$parameters['image_code'] = getFieldFromId("image_code", "images", "image_id", $imageId, "client_id = ?", $clientId);
		}
		if (empty($parameters['description'])) {
			$parameters['description'] = getFieldFromId("description", "images", "image_id", $imageId, "client_id = ?", $clientId);
		}
		$resultSet = executeQuery("update images set image_code = ?,description = ?,os_filename = ?,extension = ?,filename = ?,file_content = ?,image_size = ?,user_id = ?,date_uploaded = now(),hash_code = null where image_id = ?",
			$parameters['image_code'], $parameters['description'], $osFilename, $extension, $fileInformation['name'], $fileContent, $imageSize, $GLOBALS['gUserId'], $imageId);
	}
	if (!empty($resultSet['sql_error'])) {
		return false;
	}
	if (!empty($osFilename)) {
		putExternalImageContents($imageId, $extension, $fullFileContent);
	}
	getImageFilename($imageId);
	return $imageId;
}

function createFile($arrayKey, $parameters = array()) {
	$fileId = "";
	if (empty($parameters) && is_array($arrayKey)) {
		$parameters = $arrayKey;
		$arrayKey = "";
	}
	if (empty($parameters['file_content']) && array_key_exists($arrayKey, $_FILES) && !empty($_FILES[$arrayKey]['name']) && empty($parameters['remove_' . $arrayKey])) {
		$originalFilename = $_FILES[$arrayKey]['name'];
		if (array_key_exists($_FILES[$arrayKey]['type'], $GLOBALS['gMimeTypes'])) {
			$extension = $GLOBALS['gMimeTypes'][$_FILES[$arrayKey]['type']];
		} else {
			$fileNameParts = explode(".", $_FILES[$arrayKey]['name']);
			$extension = $fileNameParts[count($fileNameParts) - 1];
		}
		$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
		if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
			$maxDBSize = 1000000;
		}
		$fullFileContent = file_get_contents($_FILES[$arrayKey]['tmp_name']);
		$resultSet = executeQuery("select * from files where client_id = ? and file_size = ?", $GLOBALS['gClientId'], $_FILES[$arrayKey]['size']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['os_filename'])) {
				$existingFileContent = $row['file_content'];
			} else {
				$existingFileContent = getExternalFileContents($row['os_filename']);
			}
			if (!empty($existingFileContent) && $fullFileContent == $existingFileContent) {
				return $row['file_id'];
			}
		}

		if ($_FILES[$arrayKey]['size'] < $maxDBSize) {
			$fileContent = $fullFileContent;
			$osFilename = "";
		} else {
			$fileContent = "";
			$osFilename = "/documents/tmp." . $extension;
		}
		$publicAccess = (empty($parameters['public_access']) ? 0 : 1);
		$allUserAccess = (empty($parameters['all_user_access']) ? 0 : 1);
		$resultSet = executeQuery("insert into files (file_id,client_id,description,date_uploaded,file_tag_id," .
			"filename,extension,file_content,os_filename,file_size,public_access,all_user_access,administrator_access," .
			"sort_order,version) values (null,?,?,now(),?,?,?,?,?,?,?,?,1,0,1)", $GLOBALS['gClientId'], $originalFilename, $parameters['file_tag_id'],
			$originalFilename, $extension, $fileContent, $osFilename, $_FILES[$arrayKey]['size'], $publicAccess, $allUserAccess);
		if (!empty($resultSet['sql_error'])) {
			return false;
		}
		$fileId = $resultSet['insert_id'];
		if (!empty($osFilename)) {
			putExternalFileContents($fileId, $extension, $fullFileContent);
		}
	} else if (!empty($parameters['file_content']) && !empty($parameters['filename']) && empty($parameters['remove_' . $arrayKey])) {
		$originalFilename = $parameters['filename'];
		$fileNameParts = explode(".", $originalFilename);
		$extension = $fileNameParts[count($fileNameParts) - 1];
		$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
		if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
			$maxDBSize = 1000000;
		}
		$resultSet = executeQuery("select * from files where client_id = ? and file_size = ?", $GLOBALS['gClientId'], strlen($parameters['file_content']));
		while ($row = getNextRow($resultSet)) {
			if (empty($row['os_filename'])) {
				$existingFileContent = $row['file_content'];
			} else {
				$existingFileContent = getExternalFileContents($row['os_filename']);
			}
			if (!empty($existingFileContent) && $parameters['file_content'] == $existingFileContent) {
				return $row['file_id'];
			}
		}

		if (strlen($parameters['file_content']) < $maxDBSize) {
			$fileContent = $parameters['file_content'];
			$osFilename = "";
		} else {
			$fileContent = "";
			$osFilename = "/documents/tmp." . $extension;
		}
		$publicAccess = (empty($parameters['public_access']) ? 0 : 1);
		$allUserAccess = (empty($parameters['all_user_access']) ? 0 : 1);
		$resultSet = executeQuery("insert into files (file_id,client_id,description,date_uploaded," .
			"filename,extension,file_content,os_filename,file_size,public_access,all_user_access,administrator_access," .
			"sort_order,version) values (null,?,?,now(),?,?,?,?,?,?,?,1,0,1)", $GLOBALS['gClientId'], $originalFilename, $originalFilename, $extension, $fileContent, $osFilename, strlen($parameters['file_content']), $publicAccess, $allUserAccess);
		if (!empty($resultSet['sql_error'])) {
			return false;
		}
		$fileId = $resultSet['insert_id'];
		if (!empty($osFilename)) {
			putExternalFileContents($fileId, $extension, $parameters['file_content']);
		}
	}
	return $fileId;
}

function fixFieldCase($row) {
	foreach ($row as $fieldName => $fieldData) {
		$row[strtolower($fieldName)] = $fieldData;
	}
	return $row;
}

function convertSmartQuotes($string) {
	$search = array(chr(145), chr(146), chr(147), chr(148), chr(151));
	$replace = array("'", "'", '"', '"', '-');
	$string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
	return str_replace($search, $replace, $string);
}

function ordinal($number) {
	$ends = array('th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th');
	if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
		return $number . 'th';
	} else {
		return $number . $ends[$number % 10];
	}
}

$GLOBALS['gPageSectionPageId'] = "";
$GLOBALS['gPageSections'] = false;

function canAccessPageSection($pageSectionCode) {
	$canAccess = getCachedData("can_access_page_section", $pageSectionCode . ":" . $GLOBALS['gUserId']);
	if (strlen($canAccess) > 0) {
		return $canAccess;
	}
	if ($GLOBALS['gPageSections'] == false || $GLOBALS['gPageId'] != $GLOBALS['gPageSectionPageId']) {
		$GLOBALS['gPageSections'] = array();
		$resultSet = executeReadQuery("select * from page_sections where page_id = ?", $GLOBALS['gPageId']);
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gPageSections'][$row['page_section_code']] = $row['page_section_id'];
		}
		$GLOBALS['gPageSectionPageId'] = $GLOBALS['gPageId'];
	}
	$pageSectionId = $GLOBALS['gPageSections'][strtoupper($pageSectionCode)];
	if (empty($pageSectionId)) {
		setCachedData("can_access_page_section", $pageSectionCode . ":" . $GLOBALS['gUserId'], "0");
	}
	$pageSectionClientAccessId = getFieldFromId("page_section_client_access_id", "page_section_client_access", "page_section_id", $pageSectionId, "client_id = ?", $GLOBALS['gClientId']);
	if (empty($pageSectionClientAccessId)) {
		setCachedData("can_access_page_section", $pageSectionCode . ":" . $GLOBALS['gUserId'], "0");
	}
	if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access']) {
		setCachedData("can_access_page_section", $pageSectionCode . ":" . $GLOBALS['gUserId'], "1");
		return true;
	}
	$allUserAccess = getFieldFromId("all_user_access", "page_sections", "page_section_code", $pageSectionCode, "page_id = ?", $GLOBALS['gPageId']);
	$canAccess = ($allUserAccess == 1);
	$pageSectionUserAccessFields = getMultipleFieldsFromId(array("page_section_user_access_id", "restricted_access"), "page_section_user_access", "page_section_id", $pageSectionId, "user_id = ?", $GLOBALS['gUserId']);
	if (!empty($pageSectionUserAccessFields)) {
		$canAccess = ($pageSectionUserAccessFields['restricted_access'] != 1);
	}
	setCachedData("can_access_page_section", $pageSectionCode . ":" . $GLOBALS['gUserId'], ($canAccess ? "1" : "0"));
	return $canAccess;
}

function getFieldFromDataType($dataType) {
	switch ($dataType) {
		case "tinyint":
		case "int":
			return "integer_data";
		case "decimal":
			return "number_data";
		case "date":
			return "date_data";
		case "image":
			return "image_id";
		case "file":
			return "file_id";
		default:
			return "text_data";
	}
}

function checkUserCredentials($userName, $submittedPassword) {
	$userName = strtolower($userName);
	$userRow = getRowFromId("users", "user_name", $userName, "inactive = 0 and locked = 0 and (client_id = ? or superuser_flag = 1)", $GLOBALS['gClientId']);
	if (empty($userRow)) {
		return false;
	}
	$userId = $userRow['user_id'];
	$passwordSalt = $userRow['password_salt'];
	$password = $userRow['password'];
	if ($password != hash("sha256", $userId . $passwordSalt . $submittedPassword) && $password != md5($userId . $passwordSalt . $submittedPassword)) {
		return false;
	}
	return $userId;
}

function sortMenu($menuCode, $clientId = "") {
	if (empty($clientId)) {
		$clientId = $GLOBALS['gClientId'];
	}
	$resultSet = executeReadQuery("select * from menus where client_id = ? and menu_code = ?", $clientId, $menuCode);
	if ($row = getNextRow($resultSet)) {
		$sequenceNumber = 2;
		$menuSet = executeQuery("select * from menu_contents join menu_items using (menu_item_id) where menu_contents.menu_id = ? order by link_title", $row['menu_id']);
		while ($menuRow = getNextRow($menuSet)) {
			if ($sequenceNumber != $menuRow['sequence_number']) {
				executeQuery("update menu_contents set sequence_number = ? where menu_content_id = ?", $sequenceNumber, $menuRow['menu_content_id']);
			}
			$sequenceNumber += 2;
		}
	}
}

function getCurlReturn($url, $postParameters = array(), $timeout = 1800, $returnHeader = false) {
	$startTime = getMilliseconds();
	$curlHandle = curl_init();
	$postQueryString = "";
	foreach ($postParameters as $postParameter => $value) {
		if (is_array($value)) {
			foreach ($value as $subValue) {
				$postQueryString .= (empty($postQueryString) ? "" : "&") . $postParameter . '[]=' . $subValue;
			}
		} else {
			$postQueryString .= (empty($postQueryString) ? "" : "&") . $postParameter . '=' . $value;
		}
	}

	if (!empty($postQueryString)) {
		curl_setopt($curlHandle, CURLOPT_POST, 1);
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $postQueryString);
	}

	curl_setopt($curlHandle, CURLOPT_URL, $url);
	curl_setopt($curlHandle, CURLOPT_HEADER, ($returnHeader ? 1 : 0)); // INCLUDE HEADER ? 1 OR 0
	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, min(60, $timeout));
	curl_setopt($curlHandle, CURLOPT_TIMEOUT, $timeout);
	$errorText = curl_error($curlHandle);
	if (!empty($errorText)) {
		$GLOBALS['gPrimaryDatabase']->logError("Curl Return Error: " . $errorText);
	}
	$returnValue = curl_exec($curlHandle);
	curl_close($curlHandle);

	$endTime = getMilliseconds();
	$elapsedTime = round(($endTime - $startTime) / 1000, 2);

	if ($GLOBALS['gLogLiveQueries'] || ($GLOBALS['gLogDatabaseQueries'] && $GLOBALS['gDevelopmentServer'])) {
		$logStatement = "insert into query_log (query_log_code,query_text,content,elapsed_time,user_id) values ('Curl Import',?,?,?,?)";
		$logParameters = array();
		$logParameters[] = $url;
		$queryLogText = str_replace("&", "\n", $postQueryString) . "\n";
		$bt = debug_backtrace();
		foreach ($bt as $caller) {
			$queryLogText .= (empty($queryLogText) ? "" : " : ") . $caller['file'] . ", line " . $caller['line'];
		}
		$queryLogText .= " : " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$logParameters[] = $queryLogText;
		$logParameters[] = $elapsedTime;
		$logParameters[] = $GLOBALS['gUserId'];
		executeQuery($logStatement, $logParameters);
	}
	return $returnValue;
}

function canAccessFile($fileId) {
	$resultSet = executeReadQuery("select * from files where file_id = ?", $fileId);
	if (!$fileRow = getNextRow($resultSet)) {
		return false;
	}
	$canAccessDocument = $GLOBALS['gUserRow']['superuser_flag'] ||
		(($fileRow['client_id'] == $GLOBALS['gClientId'] || $fileRow['client_id'] == $GLOBALS['gDefaultClientId']) &&
			($fileRow['public_access'] == 1 || ($GLOBALS['gLoggedIn'] && $fileRow['all_user_access'] == 1) ||
				($GLOBALS['gUserRow']['administrator_flag'] && $fileRow['administrator_access'])) && !$fileRow['inactive'] && ($GLOBALS['gInternalConnection'] || !$fileRow['internal_use_only']));
	if (!$canAccessDocument) {
		$contactFileId = getFieldFromId("contact_file_id", "contact_files", "file_id", $fileId, "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
		$canAccessDocument = (!empty($contactFileId));
	}
    if (!$canAccessDocument) {
        $contactEventTypeId = getFieldFromId("contact_event_type_id", "contact_event_types", "file_id", $fileId, "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
        $canAccessDocument = (!empty($contactEventTypeId));
    }
	if ($canAccessDocument && !$GLOBALS['gUserRow']['superuser_flag']) {
		if (!empty($fileRow['user_group_id'])) {
			if (array_key_exists("user_group_ids", $GLOBALS['gUserRow'])) {
				if (!in_array($fileRow['user_group_id'], $GLOBALS['gUserRow']['user_group_ids'])) {
					$canAccessDocument = false;
				}
			} else {
				$userGroupMember = getFieldFromId("user_group_member_id", "user_group_members", "user_id", $GLOBALS['gUserId'], "user_group_id = ?", $fileRow['user_group_id']);
				if (empty($userGroupMember)) {
					$canAccessDocument = false;
				}
			}
		}
	}
	if ($canAccessDocument && !$GLOBALS['gUserRow']['superuser_flag']) {
		if (!empty($fileRow['file_folder_id'])) {
			$userGroupId = getFieldFromId("user_group_id", "file_folders", "file_folder_id", $fileRow['file_folder_id']);
			if (!empty($userGroupId)) {
				if (array_key_exists("user_group_ids", $GLOBALS['gUserRow'])) {
					if (!in_array($userGroupId, $GLOBALS['gUserRow']['user_group_ids'])) {
						$canAccessDocument = false;
					}
				} else {
					$userGroupMember = getFieldFromId("user_group_member_id", "user_group_members", "user_id", $GLOBALS['gUserId'], "user_group_id = ?", $userGroupId);
					if (empty($userGroupMember)) {
						$canAccessDocument = false;
					}
				}
			}
		}
	}

# Check to see if the document is for a product and allow access for people who have purchased the product

	if (!$canAccessDocument && $GLOBALS['gLoggedIn']) {
		$resultSet = executeReadQuery("select sum(quantity) from order_items where product_id in (select product_id from products where client_id = ? and file_id = ?) and order_id in (select order_id from orders where " .
			"contact_id = ? and deleted = 0) and (download_date is null or now() <= download_date)", $GLOBALS['gClientId'], $fileId, $GLOBALS['gUserRow']['contact_id']);
		if ($row = getNextRow($resultSet)) {
			$canAccessDocument = ($row['sum(quantity)'] > 0);
		}
	}

# Check to see if the document belongs to a Help Desk Ticket and allow access for creator and assigned users

	if (!$canAccessDocument && $GLOBALS['gLoggedIn']) {
		$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entry_files", "file_id", $fileId);
		if (!empty($helpDeskEntryId)) {
			$helpDeskEntryRow = getRowFromId("help_desk_entries", "help_desk_entry_id", $helpDeskEntryId);
			if ($helpDeskEntryRow['user_id'] == $GLOBALS['gUserId'] || $helpDeskEntryRow['contact_id'] == $GLOBALS['gUserRow']['contact_id']) {
				$canAccessDocument = true;
			} else if (!empty($helpDeskEntryRow['user_group_id']) && isInUserGroup($GLOBALS['gUserId'], $helpDeskEntryRow['user_group_id'])) {
				$canAccessDocument = true;
			} else {
				$userId = getFieldFromId("user_id", "help_desk_entry_users", "user_id", $GLOBALS['gUserId'], "help_desk_entry_id = ?", $helpDeskEntryRow['help_desk_entry_id']);
				if (!empty($userId)) {
					$canAccessDocument = true;
				}
			}
		}
	}

	return $canAccessDocument;
}

$GLOBALS['gMaxSearchTermLength'] = 40;

function replaceLinks($text) {
	while (true) {
		$matches = array();
		preg_match('/http(.*?)\[(.*?)]/', $text, $matches);
		if (empty($matches)) {
			break;
		}
		$replacement = "<a target='_blank' href='ht-tp" . $matches[1] . "'>" . $matches[2] . "</a>";
		$text = str_replace($matches[0], $replacement, $text);
	}
	while (true) {
		$matches = array();
		preg_match('/mailto:(.*?)\[(.*?)]/', $text, $matches);
		if (empty($matches)) {
			break;
		}
		$replacement = "<a href='mail-to:" . $matches[1] . "'>" . $matches[2] . "</a>";
		$text = str_replace($matches[0], $replacement, $text);
	}
	$text = str_replace("ht-tp", "http", $text);
	$text = str_replace("mail-to:", "mailto:", $text);
	return $text;
}

function resetLanguage($languageCode) {
	$languageId = getFieldFromId("language_id", "languages", "iso_code", $languageCode);
	if (empty($languageId)) {
		return;
	}
	$GLOBALS['gLanguageCode'] = $languageCode;
	$GLOBALS['gLanguageId'] = $languageId;
	$GLOBALS['gLanguageColumns'] = array();
	$GLOBALS['gLanguageText'] = array();
	$GLOBALS['gTextTranslations'] = array();
	$GLOBALS['gTranslatableColumns'] = array();
	if ($GLOBALS['gLanguageId'] != $GLOBALS['gEnglishLanguageId']) {
		$GLOBALS['gNoTranslation'] = true;

		$resultSet = executeReadQuery("select * from column_definitions where translatable = 1");
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gTranslatableColumns']['|' . $row['column_name']] = true;
		}

		$resultSet = executeReadQuery("select *,(select table_name from tables where table_id = table_columns.table_id) table_name, " .
			"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name from table_columns where translatable = 1");
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gTranslatableColumns'][$row['table_name'] . '|' . $row['column_name']] = true;
		}

		$resultSet = executeReadQuery("select * from text_translations where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gTextTranslations'][strtolower($row['english_text'])] = $row['translated_text'];
		}

		$resultSet = executeReadQuery("select * from language_columns where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$row['table_name'] = getFieldFromId("table_name", "tables", "table_id", $row['table_id']);
			$row['column_name'] = getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']);
			if (!array_key_exists($row['table_name'], $GLOBALS['gLanguageColumns'])) {
				$GLOBALS['gLanguageColumns'][$row['table_name']] = array();
			}
			$GLOBALS['gLanguageColumns'][$row['table_name']][$row['column_name']] = $row['language_column_id'];
			$GLOBALS['gLanguageColumnRows'][$row['language_column_id']] = $row;
			$GLOBALS['gLanguageText'][$row['language_column_id']] = array();
		}
		$resultSet = executeReadQuery("select * from language_text where language_id = ? and client_id = ?", $GLOBALS['gLanguageId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['language_column_id'], $GLOBALS['gLanguageText'])) {
				$GLOBALS['gLanguageText'][$row['language_column_id']][$row['primary_identifier']] = $row['content'];
			}
		}
		$GLOBALS['gNoTranslation'] = false;

		# Reload so that page information is translated
		if (!empty($GLOBALS['gPageId'])) {
			$resultSet = executeReadQuery("select * from pages where page_id = ?", $GLOBALS['gPageId']);
			if ($row = getNextRow($resultSet)) {
				if ($GLOBALS['gInternalConnection']) {
					$row['internal_use_only'] = 0;
				}
				$pageTextSet = executeQuery("select * from page_text_chunks where page_id = ?", $row['page_id']);
				$row['page_text_chunks'] = array();
				while ($pageTextRow = getNextRow($pageTextSet)) {
					$row['page_text_chunks'][$pageTextRow['page_text_chunk_code']] = $pageTextRow['content'];
					foreach ($row as $fieldName => $fieldData) {
						$row[$fieldName] = str_replace("%" . strtolower($pageTextRow['page_text_chunk_code']) . "%", (is_scalar($pageTextRow['content']) ? $pageTextRow['content'] : ""), $fieldData);
					}
				}
				$pageControlsSet = executeQuery("select * from page_controls where page_id = ?", $row['page_id']);
				$row['page_controls'] = array();
				while ($pageControlsRow = getNextRow($pageControlsSet)) {
					$row['page_controls'][] = $pageControlsRow;
				}
				$row[$row['page_code']] = $row['page_id'];
				$GLOBALS['gPageRow'] = $row;
			}
		}
	}
}

function createBlackList() {
	$filename = $GLOBALS['gDocumentRoot'] . "/cache/" . gethostname() . "_blacklist.inc";
	if (file_exists($filename)) {
		unlink($filename);
	}
    $blacklistedIpAddresses = array();
	$resultSet = executeQuery("select ip_address from ip_address_blacklist where ip_address not in (select ip_address from ip_address_whitelist)");
	while ($row = getNextRow($resultSet)) {
        $blacklistedIpAddresses[] = $row['ip_address'];
	}
    setCachedData("blacklisted_ips", "", $blacklistedIpAddresses, 24, true);

    file_put_contents($filename, implode("\n", $blacklistedIpAddresses));

	$filename = $GLOBALS['gDocumentRoot'] . "/cache/" . gethostname() . "_whitelist.inc";
	if (file_exists($filename)) {
		unlink($filename);
	}
	$whitelist = "";
	$resultSet = executeQuery("select ip_address from ip_address_whitelist");
	while ($row = getNextRow($resultSet)) {
		$whitelist .= $row['ip_address'] . "\n";
	}
	file_put_contents($filename, $whitelist);
}

function getFieldControl($tableName, $columnName, $additionalControls = array()) {
	return createFormControl($tableName, $columnName, $additionalControls);
}

function createFormControl($tableName, $columnName, $additionalControls = array()) {
	if (!$GLOBALS['gPrimaryDatabase']->tableExists($tableName)) {
		return "Table does not exist";
	}
	try {
		$dataTable = new DataTable($tableName);
	} catch (Exception $e) {
		return "Table does not exist";
	}
	if (!$dataTable->columnExists($columnName)) {
		return "Column does not exist";
	}
	$thisColumn = $dataTable->getColumns($columnName);
	foreach ($additionalControls as $controlName => $controlValue) {
		$thisColumn->setControlValue($controlName, $controlValue);
	}
	addPageControls($thisColumn);
	if (!$thisColumn->controlValueExists("label_class")) {
		if ($thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" &&
			!$thisColumn->getControlValue('readonly') && !$thisColumn->getControlValue("no_required_label")) {
			$thisColumn->setControlValue("label_class", "required-label");
		} else {
			$thisColumn->setControlValue("label_class", "");
		}
	}
	if ($thisColumn->getControlValue('data_type') == "tinyint" && !$thisColumn->getControlValue('normal_label')) {
		$formLabel = "";
	} else {
		$formLabel = $thisColumn->getControlValue("form_label");
	}
	$helpLabel = $thisColumn->getControlValue("help_label");
	if ($thisColumn->getControlValue("data_type") == "select" && empty($thisColumn->getControlValue("show_if_empty"))) {
		$choices = $thisColumn->getChoices($GLOBALS['gPageObject']);
		if ((empty($choices) || count($choices) == 0) && !$thisColumn->getControlValue('not_null') && !$thisColumn->getControlValue('show_no_choices')) {
			return "";
		}
	}
	ob_start();
	?>
	<?php if (empty($additionalControls['control_only'])) { ?>
        <div class="basic-form-line <?= $thisColumn->getControlValue('form_line_classes') ?>" id="_<?= $thisColumn->getControlValue("column_name") ?>_row" data-column_name="<?= $thisColumn->getControlValue("column_name") ?>">
		<?php if (!empty($formLabel)) { ?>
            <label for="<?= $thisColumn->getControlValue("column_name") ?>" class="<?= $thisColumn->getControlValue("label_class") ?>"><?= $formLabel ?></label>
		<?php } ?>
	<?php } ?>
	<?= $thisColumn->getControl($GLOBALS['gPageObject']) ?>
	<?php if (empty($additionalControls['control_only'])) { ?>
        <div class='basic-form-line-messages'><span class="help-label"><?= $helpLabel ?></span><span class='field-error-text'></span></div>
        <div class='clear-div'></div>
        </div>
	<?php } ?>
	<?php
	return ob_get_clean();
}

function createFormLineControl($tableName, $columnName, $additionalControls = array()) {
	if (!$GLOBALS['gPrimaryDatabase']->tableExists($tableName)) {
		return "Table does not exist";
	}
	try {
		$dataTable = new DataTable($tableName);
	} catch (Exception $e) {
		return "Table does not exist";
	}
	if (!$dataTable->columnExists($columnName)) {
		return "Column does not exist";
	}
	$thisColumn = $dataTable->getColumns($columnName);
	foreach ($additionalControls as $controlName => $controlValue) {
		$thisColumn->setControlValue($controlName, $controlValue);
	}
	addPageControls($thisColumn);
	if (!$thisColumn->controlValueExists("label_class")) {
		if ($thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" &&
			!$thisColumn->getControlValue('readonly') && !$thisColumn->getControlValue("no_required_label")) {
			$thisColumn->setControlValue("label_class", "required-label");
		} else {
			$thisColumn->setControlValue("label_class", "");
		}
	}
	if ($thisColumn->getControlValue('data_type') == "tinyint" && !$thisColumn->getControlValue('normal_label')) {
		$formLabel = "";
	} else {
		$formLabel = $thisColumn->getControlValue("form_label");
	}
	$helpLabel = $thisColumn->getControlValue("help_label");
	if ($thisColumn->getControlValue("data_type") == "select" && empty($thisColumn->getControlValue("show_if_empty"))) {
		$choices = $thisColumn->getChoices($GLOBALS['gPageObject']);
		if ((empty($choices) || count($choices) == 0) && !$thisColumn->getControlValue('not_null') && !$thisColumn->getControlValue('show_no_choices')) {
			return "";
		}
	}
	if (empty($thisColumn->getControlValue("data-field_name"))) {
		$thisColumn->setControlValue("data-field_name",str_replace('"',"'",$formLabel));
	}
	ob_start();
	?>
	<?php if (empty($additionalControls['control_only'])) { ?>
        <div class="form-line <?= $thisColumn->getControlValue('form_line_classes') ?>" id="_<?= $thisColumn->getControlValue("column_name") ?>_row">
		<?php if (!empty($formLabel)) { ?>
            <label for="<?= $thisColumn->getControlValue("column_name") ?>" class="<?= $thisColumn->getControlValue("label_class") ?>"><?= $formLabel ?></label>
		<?php } ?>
		<?php if (!empty($helpLabel)) { ?>
            <span class="help-label"><?= $helpLabel ?></span>
		<?php } ?>
	<?php } ?>
	<?= $thisColumn->getControl($GLOBALS['gPageObject']) ?>
	<?php if (empty($additionalControls['control_only'])) { ?>
        <div class='clear-div'></div>
        </div>
	<?php } ?>
	<?php
	return ob_get_clean();
}

function addPageControls(&$dataColumn) {
	if (!is_a($dataColumn, "DataColumn")) {
		return false;
	}
	if (array_key_exists("page_controls", $GLOBALS['gPageRow'])) {
		foreach ($GLOBALS['gPageRow']['page_control'] as $row) {
			$dataColumn->setControlValue($row['control_name'], $row['control_value']);
		}
	} else {
		$resultSet = executeReadQuery("select * from page_controls where page_id = ? and column_name = ?", $GLOBALS['gPageId'], $dataColumn->getName());
		while ($row = getNextRow($resultSet)) {
			$dataColumn->setControlValue($row['control_name'], $row['control_value']);
		}
	}
	return true;
}

function addActivityLog($logEntry, $activityTag = "") {
	if (!empty($_GET['ajax']) || !empty($GLOBALS['gApiCall']) || empty($GLOBALS['gUserId']) || empty($logEntry)) {
		return;
	}
	$ignoreUserActivityLog = getPreference("ignore_user_activity_log");
	if (!$ignoreUserActivityLog) {
		executeQuery("insert into user_activity_log (activity_tag,user_id,log_time,log_entry) values (?,?,now(),?)", makeCode($activityTag), $GLOBALS['gUserId'], $logEntry);
	}
}

function trimFields($array) {
	if (!is_array($array)) {
		return trim($array);
	}
	foreach ($array as $fieldName => $fieldData) {
		if (is_array($fieldData) || is_object($fieldData)) {
			$array[$fieldName] = trimFields($fieldData);
		} else {
			$array[$fieldName] = trim($array[$fieldName]);
		}
	}
	return $array;
}

function blacklistIpAddressList($ipAddressList, $blacklistNote) {
	if (!is_array($ipAddressList)) {
		if (strpos($ipAddressList, ",") !== false) {
			$ipAddressList = explode(",", $ipAddressList);
		} else if (strpos($ipAddressList, "\r") !== false) {
			$ipAddressList = getContentLines($ipAddressList);
		} else if (strpos($ipAddressList, " ") !== false) {
			$ipAddressList = explode(" ", $ipAddressList);
		} else {
			$ipAddressList = array($ipAddressList);
		}
	}
	foreach ($ipAddressList as $ipAddress) {
		blacklistIpAddress($ipAddress, $blacklistNote);
	}
	createBlackList();
}

function blacklistIpAddress($ipAddress, $blackListNote) {
	if (substr($ipAddress, 0, 3) == "0.0" || substr($ipAddress, 0, 5) == "10.1.") {
		return;
	}
	$blacklistedIpAddresses = getCachedData("blacklisted_ips", "", true);
	if (!is_array($blacklistedIpAddresses)) {
		$blacklistedIpAddresses = array();
	}

	if (array_key_exists($ipAddress, $blacklistedIpAddresses)) {
		return;
	}
	$blacklistedIpAddresses[$ipAddress] = true;

	executeQuery("insert ignore into ip_address_blacklist (ip_address_blacklist_id,ip_address,access_count,notes) values (null,?,1,?)", $ipAddress, $blackListNote);
	createBlackList();

	if ($ipAddress == $_SERVER['REMOTE_ADDR']) {
		if ($GLOBALS['gLoggedIn']) {
			$GLOBALS['gChangeLogNotes'] = "Inactive of getting blacklisted";
			$userDataTable = new DataTable("users");
			$userDataTable->setSaveOnlyPresent(true);
			$userDataTable->saveRecord(array("name_values" => array("inactive" => "1"), "primary_id" => $GLOBALS['gUserId']));
			$GLOBALS['gChangeLogNotes'] = "";
			addActivityLog("Blacklisted IP . " . $ipAddress . " and made inactive");
			logout();
		}
	}
	return true;
}

function clientUsesSubsystem($subsystemCode) {
	return ($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] || (in_array($subsystemCode, $GLOBALS['gClientSubsystemCodes'])));
}

function fragmentExists($fragmentCode) {
	if (!is_array($GLOBALS['gFoundFragments']) || !isset($GLOBALS['gFoundFragments'])) {
		$GLOBALS['gFoundFragments'] = array();
		$resultSet = executeReadQuery("select fragment_code from fragments where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gFoundFragments'][$row['fragment_code']] = true;
		}
	}
	return array_key_exists(strtoupper($fragmentCode), $GLOBALS['gFoundFragments']);
}

function getFragment($fragmentCode, $substitutionValues = array()) {
	if (!is_array($GLOBALS['gFragmentContents']) || !isset($GLOBALS['gFragmentContents'])) {
		$GLOBALS['gFragmentContents'] = array();
		$resultSet = executeReadQuery("select * from fragments where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gFragmentContents'][$row['fragment_code']] = $row['content'];
		}
	}
	$fragmentCode = strtoupper($fragmentCode);
	if (array_key_exists($fragmentCode, $GLOBALS['gFragmentContents'])) {
		$content = massageFragmentContent($GLOBALS['gFragmentContents'][$fragmentCode], $substitutionValues);
	} else if (!empty($GLOBALS['gPageObject'])) {
		$content = $GLOBALS['gPageObject']->getFragment($fragmentCode, $substitutionValues);
	}
	return $content;
}

function massageFragmentContent($content, $substitutionValues = array()) {
	if (!empty($content) && strpos($content, "%module:") !== false) {
		$fragmentLines = getContentLines($content);
		$content = "";
		foreach ($fragmentLines as $thisLine) {
			if (substr($thisLine, 0, strlen("%module:")) == "%module:") {
				$methodName = substr($thisLine, strlen("%module:"));
				if (substr($methodName, -1) == "%") {
					$methodName = substr($methodName, 0, -1);
				}
				foreach ($substitutionValues as $fieldName => $fieldValue) {
					$methodName = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $methodName);
				}
				$parts = explode(":", $methodName);
				if (count($parts) > 1) {
					$methodName = array_shift($parts);
				}
				$pageModule = PageModule::getPageModuleInstance($methodName);
				if (!empty($pageModule)) {
					$pageModule->setParameters($parts);
					ob_start();
					$pageModule->displayContent();
					$content .= ob_get_clean();
				}
			} else {
				$content .= $thisLine . "\r";
			}
		}
	}
	$content = PlaceHolders::massageContent($content, $substitutionValues);
	return $content;
}

function getFragmentFromId($fragmentId) {
	return getFragment(getReadFieldFromId("fragment_code", "fragments", "fragment_id", $fragmentId));
}

function getPageTextChunk($pageTextChunkCode, $substitutions = array(), $defaultValue = "") {
	if (empty($GLOBALS['gPageObject'])) {
		$content = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", strtoupper($pageTextChunkCode), "template_id = ?", $GLOBALS['gPageTemplateId']);
		$pageTextChunkId = getFieldFromId("page_text_chunk_id", "page_text_chunks", "page_id", $GLOBALS['gPageId'], "page_text_chunk_code = ?", strtoupper($pageTextChunkCode));
		if (!empty($pageTextChunkId)) {
			$content = getFieldFromId("content", "page_text_chunks", "page_text_chunk_id", $pageTextChunkId);
		}
		$content = PlaceHolders::massageContent($content, $substitutions);
		return trim($content);
	} else {
		return $GLOBALS['gPageObject']->getPageTextChunk($pageTextChunkCode, $substitutions, $defaultValue);
	}
}

function isFromCountry($countryCodes, $ipAddress = "") {
	if (!is_array($countryCodes)) {
		$countryCodes = explode(",", $countryCodes);
	}
	if (empty($ipAddress)) {
		$ipAddress = $_SERVER['REMOTE_ADDR'];
	}
	$ipAddressPossibilities = array($_SERVER['REMOTE_ADDR']);
	for ($x = 1; $x < 32; $x++) {
		$longValue = ip2long($ipAddress);
		$clearValue = pow(2, $x) - 1;
		$newValue = $longValue & ~$clearValue;
		$thisIpAddress = long2ip($newValue) . "/" . (32 - $x);
		$ipAddressPossibilities[] = $thisIpAddress;
	}
	$queryPart = "";
	$queryParameters = array();
	foreach ($ipAddressPossibilities as $ipAddress) {
		if (!empty($queryPart)) {
			$queryPart .= ",";
		}
		$queryPart .= "?";
		$queryParameters[] = $ipAddress;
	}
	$countryIds = array();
	foreach ($countryCodes as $countryCode) {
		$countryId = getFieldFromId("country_id", "countries", "country_code", $countryCode);
		if (!empty($countryId)) {
			$countryIds[] = $countryId;
		}
	}
	if (empty($countryIds)) {
		return false;
	}
	$resultSet = executeReadQuery("select * from ip_address_countries where ip_address in ($queryPart) and country_id in (" . implode(",", $countryIds) . ")", $queryParameters);
	if ($row = getNextRow($resultSet)) {
		return true;
	}
	return false;
}

function ftpFilePutContents($ftpHost, $ftpUserName, $ftpPassword, $ftpDirectory, $filename, $content, $useSSL = false, $port = 21) {

	// Create temporary file
	$localFile = fopen('php://temp', 'r+');
	fwrite($localFile, $content);
	rewind($localFile);

	// FTP connection
	if ($useSSL) {
		$ftpConnection = ftp_ssl_connect($ftpHost, $port, 600);
		// Required when FTP server is behind a firewall with few ports open
		if ($ftpConnection) {
			ftp_set_option($ftpConnection, FTP_USEPASVADDRESS, false);
		}
	} else {
		$ftpConnection = ftp_connect($ftpHost, $port, 600);
	}
	if (!$ftpConnection) {
		return "Unable to make connection";
	}

	// FTP login
	if (!ftp_login($ftpConnection, $ftpUserName, $ftpPassword)) {
		return "Invalid Login";
	}
	ftp_pasv($ftpConnection, true);

	// change directory
	if (!empty($ftpDirectory)) {
		if (!ftp_chdir($ftpConnection, $ftpDirectory)) {
			if (ftp_mkdir($ftpConnection, $ftpDirectory)) {
				if (!ftp_chdir($ftpConnection, $ftpDirectory)) {
					return "Directory does not exist";
				}
			} else {
				return "Directory does not exist";
			}
		}
	}

	// FTP upload
	$uploadResult = ftp_fput($ftpConnection, $filename, $localFile, FTP_BINARY);

	// Error handling
	if (!$uploadResult) {
		return "Upload Failed: " . ftp_pwd($ftpConnection) . ":" . $filename . ":" . jsonEncode(error_get_last());
	}

	// Close FTP connection
	ftp_close($ftpConnection);

	// Close file handle
	fclose($localFile);
	return true;
}

function getAddressBlock($contactRow, $lineEnding = "<br>", $parameters = array()) {
	return Contact::getAddressBlock($contactRow, $lineEnding, $parameters);
}

function getSequenceNumber($sequenceNumberIdentifier, $rolloverNumber = 0) {
	$insertSet = executeQuery("insert into sequence_numbers (client_id,sequence_number_identifier,date_used) values (?,?,current_date)", $GLOBALS['gClientId'], $sequenceNumberIdentifier);
	$sequenceNumberId = $insertSet['insert_id'];
	$resultSet = executeQuery("select count(*) from sequence_numbers where client_id = ? and sequence_number_identifier = ? and sequence_number_id <= ?",
		$GLOBALS['gClientId'], $sequenceNumberIdentifier, $sequenceNumberId);
	$row = getNextRow($resultSet);
	$sequenceNumber = $row['count(*)'];
	if ($rolloverNumber > 0) {
		$sequenceNumber = $sequenceNumber % $rolloverNumber;
	}
	return $sequenceNumber;
}

function getMergedFilename($filesArray, $fileType = "js") {
	$fullFileContent = "";
	if (is_array($filesArray)) {
		foreach ($filesArray as $thisFile) {
			if (file_exists($GLOBALS['gDocumentRoot'] . $thisFile)) {
				$fullFileContent .= file_get_contents($GLOBALS['gDocumentRoot'] . $thisFile) . ($fileType == "js" ? ";" : "");
			}
		}
	} else {
		$fullFileContent = $filesArray;
	}
	$hashCode = "merged-" . hash("sha256", $fullFileContent);
	if (!file_exists($GLOBALS['gDocumentRoot'] . "/" . $fileType . "/" . $hashCode . "." . $fileType)) {
		file_put_contents($GLOBALS['gDocumentRoot'] . "/" . $fileType . "/" . $hashCode . "." . $fileType, $fullFileContent);
	}
	return "/" . $fileType . "/" . $hashCode . "." . $fileType;
}

function clearClientCache($justCore = false) {
	if (!$GLOBALS['gApcuEnabled']) {
		return false;
	}
	$cachePrefix = ($justCore ? "ALL" : $GLOBALS['gClientId'] . "-" . $GLOBALS['gClientRow']['client_code']) . "\|";
	if (class_exists("APCUIterator", false)) {
		removeApcuKeys("/" . $cachePrefix . ".*/");
	} else {
		apcu_clear_cache();
		addProgramLog("Cache cleared by calling clearClientCache");
	}
}

function checkCachedData($cacheType, $cacheKey, $ignoreClient = false) {
	if (!$GLOBALS['gApcuEnabled']) {
		return false;
	}
	$useClientId = $GLOBALS['gClientId'];
	$useClientCode = $GLOBALS['gClientRow']['client_code'];
	if ($ignoreClient !== $GLOBALS['gClientId'] && $ignoreClient !== false && $ignoreClient !== true) {
		$useClientId = $ignoreClient;
		$useClientCode = $GLOBALS['gAllClientRows'][$ignoreClient]['client_code'];
	}
	$apcuKey = ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "|" . $cacheType . "|" . $cacheKey;
	return apcu_exists($apcuKey);
}

function getCachedData($cacheType, $cacheKey, $ignoreClient = false) {
	if (!$GLOBALS['gApcuEnabled']) {
		return false;
	}
	$useClientId = $GLOBALS['gClientId'];
	$useClientCode = $GLOBALS['gClientRow']['client_code'];
	if ($ignoreClient !== $GLOBALS['gClientId'] && $ignoreClient !== false && $ignoreClient !== true) {
		$useClientId = $ignoreClient;
		$useClientCode = $GLOBALS['gAllClientRows'][$ignoreClient]['client_code'];
	}
	$apcuKey = ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "|" . $cacheType . "|" . $cacheKey;
	if (apcu_exists($apcuKey)) {
		return apcu_fetch($apcuKey);
	}
	return false;
}

function cachedDataExists($cacheType, $cacheKey, $ignoreClient = false) {
	if (!$GLOBALS['gApcuEnabled']) {
		return false;
	}
	$useClientId = $GLOBALS['gClientId'];
	$useClientCode = $GLOBALS['gClientRow']['client_code'];
	if ($ignoreClient !== $GLOBALS['gClientId'] && $ignoreClient !== false && $ignoreClient !== true) {
		$useClientId = $ignoreClient;
		$useClientCode = $GLOBALS['gAllClientRows'][$ignoreClient]['client_code'];
	}
	$apcuKey = ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "|" . $cacheType . "|" . $cacheKey;
	return apcu_exists($apcuKey);
}

function setCachedData($cacheType, $cacheKey, $cacheData, $storeHours = 24, $ignoreClient = false) {
	if (!$GLOBALS['gApcuEnabled']) {
		return false;
	}
	$useClientId = $GLOBALS['gClientId'];
	$useClientCode = $GLOBALS['gClientRow']['client_code'];
	if ($ignoreClient !== $GLOBALS['gClientId'] && $ignoreClient !== false && $ignoreClient !== true) {
		$useClientId = $ignoreClient;
		$useClientCode = $GLOBALS['gAllClientRows'][$ignoreClient]['client_code'];
	}
	$apcuKey = ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "|" . $cacheType . "|" . $cacheKey;
	$tries = 0;
	while ($tries < 3) {
		$result = apcu_store($apcuKey, $cacheData, round($storeHours * 3600));
		if ($result) {
			break;
		}
		usleep(100000);
		if (apcu_exists($apcuKey)) {
			apcu_delete($apcuKey);
		}
		$tries++;
	}
	if (!$result) {
		apcu_delete($apcuKey);
	}
	return $result;
}

function removeCachedData($cacheType, $cacheKey, $ignoreClient = false) {
	if (!$GLOBALS['gApcuEnabled']) {
		return false;
	}
	$useClientId = $GLOBALS['gClientId'];
	$useClientCode = $GLOBALS['gClientRow']['client_code'];
	if ($ignoreClient !== $GLOBALS['gClientId'] && $ignoreClient !== false && $ignoreClient !== true) {
		$useClientId = $ignoreClient;
		$useClientCode = $GLOBALS['gAllClientRows'][$ignoreClient]['client_code'];
	}
	if ($cacheKey == "*" && $cacheType == "*") {
		clearClientCache();
	} else if ($cacheKey == "*") {
		if (class_exists("APCUIterator", false)) {
			removeApcuKeys("/^" . ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "\|" . $cacheType . "\|.*/");
		} else {
			apcu_clear_cache();
			addProgramLog("Cache cleared by removing all cached data");
		}
	} else if ($cacheType == "*") {
		if (class_exists("APCUIterator", false)) {
			removeApcuKeys("/^" . ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "\|.*\|" . $cacheKey . "/");
		} else {
			apcu_clear_cache();
			addProgramLog("Cache cleared by removing all cache types");
		}
	} else {
		$apcuKey = ($ignoreClient === true ? "ALL" : $useClientId . "-" . $useClientCode) . "|" . $cacheType . "|" . $cacheKey;
		return apcu_delete($apcuKey);
	}
}

function removeApcuKeys($cacheKey, $checkKey = "") {
	try {
		foreach (new APCUIterator($cacheKey) as $apcuCache) {
			if (empty($checkKey) || preg_match($checkKey, $apcuCache['key'])) {
				apcu_delete($apcuCache['key']);
			}
		}
	} catch (Exception $e) {
		$GLOBALS['gPrimaryDatabase']->logError("APCUIterator Error: " . $e . " : " . $cacheKey);
	}
}

function triggerServerClearCache($cacheKey = false) {
	$fileContents = time() . "," . (empty($cacheKey) ? "" : $cacheKey);
	file_put_contents($GLOBALS['gDocumentRoot'] . "/cache/" . strtolower(getPreference("SYSTEM_NAME")) . "_clearcache.txt", $fileContents);
	setCachedData("trigger_server_clear_cache", "trigger_server_clear_cache", time(), 24, true);
}

function getSourceFromReferer($referer) {
	$sourceId = "";
	$resultSet = executeQuery("select * from sources join source_referers using (source_id) where ? like concat('%',referer,'%') and client_id = ? and " .
		"inactive = 0 order by sort_order", $referer, $GLOBALS['gClientId']);
	if ($row = getNextRow($resultSet)) {
		$sourceId = $row['source_id'];
	}
	return $sourceId;
}

function processBase64Images($content, $includeDomain = false) {
	do {
		$imagePosition = strpos($content, "data:image/jpeg;base64,");
		if ($imagePosition === false) {
			$imagePosition = strpos($content, "data:image/png;base64,");
		}
		if ($imagePosition === false) {
			continue;
		}
		$endPosition = strpos($content, "\"", $imagePosition + strlen("data:image"));
		if ($endPosition === false) {
			$content = substr_replace($content, "", $imagePosition, strlen("data:image"));
			continue;
		}
		$base64Content = substr($content, $imagePosition, $endPosition - $imagePosition);
		$extension = "jpg";
		$base64Content = str_replace("data:image/jpeg;base64,", "", $base64Content);
		if (strpos($base64Content, "data:image/png;base64,") !== false) {
			$base64Content = str_replace("data:image/png;base64,", "", $base64Content);
			$extension = "png";
		}
		$imageContent = base64_decode($base64Content);
		$imageId = createImage(array("extension" => $extension, "file_content" => $imageContent, "name" => getRandomString(6) . ".jpg", "description" => "Converted Base 64 Image"));

		$imageUrl = ($includeDomain ? "https://" . $_SERVER['HTTP_HOST'] : "") . "/getimage.php?id=" . $imageId;

		$content = substr_replace($content, $imageUrl, $imagePosition, $endPosition - $imagePosition);
	} while ($imagePosition !== false);

	do {
		$imagePosition = strpos($content, "Content-Transfer-Encoding: base64");
		if ($imagePosition === false) {
			continue;
		}
		$startPosition = strpos($content, "\n\n", $imagePosition) + 2;
		$endPosition = strpos($content, "--", $startPosition);
		if ($endPosition === false) {
			$endPosition = strlen($content);
		}
		$base64Content = substr($content, $startPosition, $endPosition - $startPosition);
		$imageContent = base64_decode($base64Content);

		$testImage = imagecreatefromstring($imageContent);
		$isImage = true;
		if ($testImage) {
			$size = getimagesizefromstring($imageContent);
			if (!$size || $size[0] == 0 || $size[1] == 0 || !$size['mime']) {
				$isImage = false;
			}
		} else {
			$isImage = false;
		}
		if ($isImage) {
			$imageId = createImage(array("extension" => $extension, "file_content" => $imageContent, "name" => getRandomString(6) . ".jpg", "description" => "Converted Base 64 Image"));
			$imageUrl = ($includeDomain ? "https://" . $_SERVER['HTTP_HOST'] : "") . "/getimage.php?id=" . $imageId;
			$replaceContent = "<img src='" . $imageUrl . "'>\n";
		} else {
			$replaceContent = "\n" . base64_decode($base64Content) . "\n";
		}
		$content = substr_replace($content, $replaceContent, $imagePosition, $endPosition - $imagePosition);
	} while ($imagePosition !== false);
	return $content;
}

function getSassHeaders($templateId = "") {
	$sassHeaders = "";
	if (empty($templateId)) {
		$templateId = $GLOBALS['gPageRow']['template_id'];
	}
	if (empty($templateId)) {
		return $sassHeaders;
	}
	$sassHeaders = getCachedData("sass_headers", $templateId, true);
	if ($sassHeaders === false) {
		$sassHeaders = "";
		$resultSet = executeQuery("select * from sass_headers join template_sass_headers using (sass_header_id) where template_id = ?", $templateId);
		while ($row = getNextRow($resultSet)) {
			$sassHeaders .= $row['content'] . "\n\n";
		}
		setCachedData("sass_headers", $templateId, $sassHeaders, 24, true);
	}
	return $sassHeaders;
}

function createUserNotification($userId, $subject, $content, $fileId = "") {
	executeQuery("insert into user_notifications (user_id,subject,content,time_submitted,file_id) values (?,?,?,now(),?)", $userId, $subject, $content, $fileId);
}

function isMobile() {
	$useragent = $_SERVER['HTTP_USER_AGENT'];
	return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
}

/**
 *
 * Utility function for formatting time taken by a process
 * @param $startMilliseconds
 * @param $endMilliseconds
 * @return string Formatted time stamp
 */
function getTimeElapsed($startMilliseconds, $endMilliseconds) {
	$totalTime = "";
	$seconds = round(($endMilliseconds - $startMilliseconds) / 1000, 1);
	$totalSeconds = $seconds;
	if ($seconds > 3600) {
		$hours = floor($seconds / 3600);
		$totalTime .= (empty($totalTime) ? "" : ", ") . $hours . " hour" . ($hours > 1 ? "s" : "");
		$seconds = $seconds - ($hours * 3600);
	}
	if ($seconds > 60) {
		$minutes = floor($seconds / 60);
		$totalTime .= (empty($totalTime) ? "" : ", ") . $minutes . " minute" . ($minutes > 1 ? "s" : "");
		$seconds = $seconds - ($minutes * 60);
	}
	if (empty($totalTime)) {
		$totalTime = $totalSeconds . " seconds";
	} else {
		$totalTime .= ", " . round($seconds, 1) . " second" . ($seconds == 1 ? "" : "s") . " (" . $totalSeconds . " seconds)";
	}
	return $totalTime;
}

function codeToText($codeValue) {
	$text = str_replace("_", " ", str_replace("-", " ", $codeValue));
	$lowerCaseCharacter = false;
	$newText = "";
	for ($x = 0; $x < strlen($text); $x++) {
		$thisChar = substr($text, $x, 1);
		if (ctype_upper($thisChar)) {
			if ($lowerCaseCharacter) {
				$newText .= " ";
			}
			$lowerCaseCharacter = false;
		} else {
			$lowerCaseCharacter = true;
		}
		$newText .= $thisChar;
	}
	return ucwords(strtolower($newText));
}

function createCsvRow($fields) {
	$returnValue = "";
	foreach ($fields as $index => $field) {
		$returnValue .= (empty($returnValue) ? "" : ",") . '"' . str_replace('"', "", $field) . '"';
	}
	$returnValue .= "\r\n";
	return $returnValue;
}

function getIpAddressMetrics($ipAddress) {
	$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
	if (empty($ipAddressData)) {
		$ipAddressRaw = getCurlReturn("http://ip-api.com/json/" . $ipAddress);
		if (empty($ipAddressRaw)) {
			$ipAddressData = array();
		} else {
			$ipAddressData = json_decode($ipAddressRaw, true);
		}
		if (!empty($ipAddressData)) {
			$ipAddressData['country_id'] = getFieldFromId("country_id", "countries", "country_code", $ipAddressData['countryCode']);
			executeQuery("insert ignore into ip_address_metrics (ip_address,country_id,city,state,postal_code,latitude,longitude) values (?,?,?,?,?, ?,?)",
				$ipAddress, $ipAddressData['country_id'], $ipAddressData['city'], $ipAddressData['region'],
				$ipAddressData['zip'], $ipAddressData['lat'], $ipAddressData['lon']);
			$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
		}
	}
	return $ipAddressData;
}

function getPresetDateOptions($parameters = array()) {
	?>
    <div class='basic-form-line'>
        <label>Preset Date Options</label>
        <select tabindex='10' id="preset_dates" name="preset_dates">
			<?php if (empty($parameters['future_only'])) { ?>
                <option value=''>Custom Dates</option>
                <option value='today'>Today</option>
                <option value='yesterday'>Yesterday</option>
                <option value='tomorrow'>Tomorrow</option>
                <option value='last_7_days'>Last 7 Days</option>
                <option value='last_30_days'>Last 30 Days</option>
                <option value='this_week'>This Week</option>
                <option value='next_week'>Next Week</option>
                <option value='next_30_days'>Next 30 Days</option>
                <option value='last_week'>Last Week</option>
                <option value='this_month'>This Month</option>
                <option value='last_month'>Last Month</option>
                <option value='this_quarter'>This Quarter</option>
                <option value='last_quarter'>Last Quarter</option>
                <option value='this_year'>This Year</option>
                <option value='last_year'>Last Year</option>
                <option value='all_time'>All Time</option>
			<?php } else { ?>
                <option value=''>Custom Dates</option>
                <option value='today'>Today</option>
                <option value='tomorrow'>Tomorrow</option>
                <option value='this_week'>This Week</option>
                <option value='next_week'>Next Week</option>
                <option value='next_30_days'>Next 30 Days</option>
                <option value='this_month'>This Month</option>
                <option value='this_quarter'>This Quarter</option>
                <option value='this_year'>This Year</option>
			<?php } ?>
        </select>
    </div>
	<?php
}

function processPresetDates($presetValue, $startFieldName, $endFieldName, $includeFuture = false) {
	if (empty($presetValue)) {
		return;
	}

	$_POST[$endFieldName] = date("m/d/Y");
	switch ($presetValue) {
		case "today":
			$_POST[$startFieldName] = date("m/d/Y");
			break;
		case "tomorrow":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("tomorrow"));
			$_POST[$endFieldName] = date("m/d/Y", strtotime("tomorrow"));
			break;
		case "yesterday":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("yesterday"));
			$_POST[$endFieldName] = date("m/d/Y", strtotime("yesterday"));
			break;
		case "last_7_days":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("-6 days"));
			break;
		case "last_30_days":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("-30 days"));
			break;
		case "this_week":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("last sunday"));
			$_POST[$endFieldName] = ($includeFuture ? date("m/d/Y", strtotime("this saturday")) : date("m/d/Y"));
			break;
		case "next_week":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("last sunday +7 days"));
			$_POST[$endFieldName] = date("m/d/Y", strtotime("this saturday +7 days"));
			break;
		case "next_30_days":
			$_POST[$startFieldName] = date("m/d/Y");
			$_POST[$endFieldName] = date("m/d/Y", strtotime("+30 days"));
			break;
		case "last_week":
			$_POST[$startFieldName] = date("m/d/Y", strtotime("last sunday -7 days"));
			$_POST[$endFieldName] = date("m/d/Y", strtotime("last saturday"));
			break;
		case "this_month":
			$_POST[$startFieldName] = date("m/01/Y");
			$_POST[$endFieldName] = ($includeFuture ? date("m/d/Y", strtotime("last day of this month")) : date("m/d/Y"));
			break;
		case "last_month":
			$_POST[$startFieldName] = date("m/01/Y", strtotime("first day of last month"));
			$_POST[$endFieldName] = date("m/d/Y", strtotime("last day of last month"));
			break;
		case "this_quarter":
			$currentMonth = date('m');
			if ($currentMonth <= 3) {
				$_POST[$startFieldName] = date("01/01/Y");
				$_POST[$endFieldName] = date("03/31/Y");
			} else if ($currentMonth <= 6) {
				$_POST[$startFieldName] = date("04/01/Y");
				$_POST[$endFieldName] = date("06/30/Y");
			} else if ($currentMonth <= 9) {
				$_POST[$startFieldName] = date("07/01/Y");
				$_POST[$endFieldName] = date("09/30/Y");
			} else {
				$_POST[$startFieldName] = date("10/01/Y");
				$_POST[$endFieldName] = date("12/31/Y");
			}
			break;
		case "last_quarter":
			$currentMonth = date('m');
			$currentYear = date('Y');
			if ($currentMonth <= 3) {
				$_POST[$startFieldName] = "10/01/" . ($currentYear - 1);
				$_POST[$endFieldName] = "12/31/" . ($currentYear - 1);
			} else if ($currentMonth <= 6) {
				$_POST[$startFieldName] = date("01/01/Y");
				$_POST[$endFieldName] = date("03/31/Y");
			} else if ($currentMonth <= 9) {
				$_POST[$startFieldName] = date("04/01/Y");
				$_POST[$endFieldName] = date("06/30/Y");
			} else {
				$_POST[$startFieldName] = date("07/01/Y");
				$_POST[$endFieldName] = date("09/30/Y");
			}
			break;
		case "this_year":
			$_POST[$startFieldName] = date("01/01/Y");
			$_POST[$endFieldName] = ($includeFuture ? date("12/31/Y") : date("m/d/Y"));
			break;
		case "last_year":
			$_POST[$startFieldName] = date("01/01/Y", strtotime("last year"));
			$_POST[$endFieldName] = date("12/31/Y", strtotime("last year"));
			break;
		case "all_time":
			$_POST[$startFieldName] = "";
			$_POST[$endFieldName] = "";
			break;
	}
}

function saveStoredReport($className = "") {
	if (empty($_POST['stored_report_description'])) {
		return;
	}
	if (!empty($_POST['_stored_report_id'])) {
		$storedReportId = getFieldFromId("stored_report_id", "stored_reports", "stored_report_id", $_POST['_stored_report_id'], "user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageRow']['page_id']);
	} else {
		$storedReportId = getFieldFromId("stored_report_id", "stored_reports", "user_id", $GLOBALS['gUserId'], "page_id = ? and description = ?", $GLOBALS['gPageRow']['page_id'], $_POST['stored_report_description']);
	}
	$dataTable = new DataTable("stored_reports");
	$dataTable->setSaveOnlyPresent(true);
	$nameValues = array("description" => $_POST['stored_report_description'], "class_name" => $className, "page_id" => $GLOBALS['gPageRow']['page_id'], "user_id" => $GLOBALS['gUserId'], "parameters" => jsonEncode($_POST));
	$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $storedReportId));
	return;
}

function getStoredReports() {
	$resultSet = executeQuery("select * from stored_reports where user_id = ? and page_id = ? order by description", $GLOBALS['gUserId'], $GLOBALS['gPageRow']['page_id']);
	if ($resultSet['row_count'] > 0) {
		?>
        <div class='basic-form-line' id='_stored_report_id_row'>
            <label>Load Stored Report</label>
            <select id="_stored_report_id" name="_stored_report_id">
                <option value=''>[Select]</option>
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['stored_report_id'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
        </div>

		<?php if ($GLOBALS['gUserRow']['administrator_flag']) { ?>
            <p><a href='/stored-report-maintenance' target='_blank'>Manage Stored Reports</a></p>
		<?php } ?>
		<?php
	}
}

function storedReportDescription() {
	?>
    <div class="basic-form-line" id="_stored_report_description_row">
        <label for="stored_report_description">Save Report As</label>
        <input tabindex="10" type="text" size="60" id="stored_report_description" name="stored_report_description">
        <div class='basic-form-line-messages'>
            <span class='help-label'>Give this report a name to save the parameters as a stored report</span><span class='field-error-text'></span></div>
        <div class='clear-div'></div>
    </div>
	<?php
}

###############
# maskString - format a string according to a mask pattern (e.g. FFLs, SSNs, credit card numbers)
# $input - string to be formatted (delimiters will be removed)
# $maskPattern - format string; # = original character, X = X (mask).  Anything else will be retained as formatting
###############

function maskString($input, $maskPattern) {
	$input = str_replace(array("-", "(", ")", "."), "", $input);
	$maskPattern = strtoupper($maskPattern);
	$output = "";
	$inputIndex = 0;
	for ($maskIndex = 0; $maskIndex < strlen($maskPattern); $maskIndex++) {
		switch (substr($maskPattern, $maskIndex, 1)) {
			case "#": // Show original character
				$output .= substr($input, $inputIndex, 1);
				$inputIndex++;
				break;
			case "X": // Mask character
				$output .= "X";
				$inputIndex++;
				break;
			default: // Formatting character; do not skip input character
				$output .= substr($maskPattern, $maskIndex, 1);
				break;
		}
	}
	return $output;
}

function getFflExpirationDate($licenseNumber) {
	$licXprDate = substr(str_replace("-", "", $licenseNumber), 8, 2);
	$months = array("A" => "1", "B" => "2", "C" => "3", "D" => "4", "E" => "5", "F" => "6", "G" => "7", "H" => "8", "J" => "9", "K" => "10", "L" => "11", "M" => "12");
	$thisYear = date("Y");
	$yearRange = array();
	for ($y = -5; $y < 5; $y++) {
		$yearLookup = substr($thisYear + $y, -1);
		$yearRange[$yearLookup] = $thisYear + $y;
	}
	$month = $months[substr($licXprDate, 1, 1)];
	if (empty($month)) {
		return false;
	}
	$year = $yearRange[substr($licXprDate, 0, 1)];
	if (empty($year)) {
		return false;
	}
	return $year . "-" . substr("0" . $month, -2) . "-01";
}

function getContactPhoneNumber($contactId, $descriptions = array("Primary"), $useAny = true) {
	return Contact::getContactPhoneNumber($contactId, $descriptions, $useAny);
}

function getExternalFileContents($osFilename) {
	$contents = "";
	if (strtoupper(substr($osFilename, 0, 3)) == "S3:") {
		$s3Filename = substr($osFilename, 3);
		$awsAccessKey = getPreference("AWS_S3_ACCESS_KEY");
		$awsSecretKey = getPreference("AWS_S3_SECRET_KEY");

		$awsRegion = getPreference("AWS_REGION");
		$s3 = new S3($awsAccessKey, $awsSecretKey, false, 's3' . (empty($awsRegion) ? "" : "." . $awsRegion) . '.amazonaws.com', $awsRegion);
		$s3->setSignatureVersion('v4');

		$parts = explode("/", $s3Filename);
		$filename = array_pop($parts);
		$bucketName = trim(implode("/", $parts), "/");

		$contents = $s3->getObjectContent($bucketName, $filename);
	} else {
		if (file_exists($osFilename)) {
			$contents = file_get_contents($osFilename);
		} else {
			if (strpos($osFilename, "/") === false) {
				$fileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
				if (empty($fileDirectory)) {
					$fileDirectory = "/documents/";
				}
				if (substr($fileDirectory, -1) != "/" && substr($fileDirectory, -1) != "\\") {
					$fileDirectory .= "/";
				}
				$osFilename = $fileDirectory . $osFilename;
			}
			if (file_exists($osFilename)) {
				$contents = file_get_contents($osFilename);
			}
		}
	}
	return $contents;
}

function getExternalImageContents($osFilename) {
	return getExternalFileContents($osFilename);
}

function putExternalFileContents($fileId, $extension, $contents) {
	$fileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
	if (empty($fileDirectory)) {
		$fileDirectory = "/documents/";
	}
	if (substr($fileDirectory, -1) != "/" && substr($fileDirectory, -1) != "\\") {
		$fileDirectory .= "/";
	}
	$filename = "file" . $fileId . "." . $extension;
	$osFilename = $fileDirectory . $filename;
	if (strtoupper(substr($fileDirectory, 0, 3)) == "S3:") {
		$fileDirectory = substr($fileDirectory, 3);
		$fileDirectory = trim($fileDirectory, "/");
		if (strpos($fileDirectory, "/") === false) {
			$fileDirectory .= "/" . strtolower(getPreference("SYSTEM_NAME"));
		}
		$osFilename = "S3://" . $fileDirectory . "/" . $filename;
		$awsAccessKey = getPreference("AWS_S3_ACCESS_KEY");
		$awsSecretKey = getPreference("AWS_S3_SECRET_KEY");

		$awsRegion = getPreference("AWS_REGION");
		$s3 = new S3($awsAccessKey, $awsSecretKey, false, 's3' . (empty($awsRegion) ? "" : "." . $awsRegion) . '.amazonaws.com', $awsRegion);
		$s3->setSignatureVersion('v4');

		$fileParts = explode("/", str_replace("S3://", "", $osFilename), 2);
		$bucketName = $fileParts[0];
		$objectName = $fileParts[1];
		$objectInfo = $s3->getObjectInfo($bucketName, $objectName);
		if (!empty($objectInfo) && md5($contents) == $objectInfo['hash']) {
			executeQuery("update files set os_filename = ?, file_content = null, file_size = ? where file_id = ?", $osFilename, strlen($contents), $fileId);
			return $osFilename;
		}
		if ($s3->putObject($contents, $fileDirectory, $filename)) {
			executeQuery("update files set os_filename = ?, file_content = null, file_size = ? where file_id = ?", $osFilename, strlen($contents), $fileId);
		} else {
			executeQuery("update files set os_filename = null, file_content = ?, file_size = ? where file_id = ?", $contents, strlen($contents), $fileId);
		}
	} else {
		if (file_put_contents($osFilename, $contents)) {
			executeQuery("update files set os_filename = ?,file_content = null, file_size = ? where file_id = ?", $osFilename, strlen($contents), $fileId);
		} else {
			executeQuery("update files set os_filename = null,file_content = ?, file_size = ? where file_id = ?", $contents, strlen($contents), $fileId);
		}
	}
	return $osFilename;
}

function putExternalImageContents($imageId, $extension, $contents) {
	$fileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
	if (empty($fileDirectory)) {
		$fileDirectory = "/documents/";
	}
	if (substr($fileDirectory, -1) != "/" && substr($fileDirectory, -1) != "\\") {
		$fileDirectory .= "/";
	}
	$filename = "image" . $imageId . "." . $extension;
	$osFilename = $fileDirectory . $filename;
	if (strtoupper(substr($fileDirectory, 0, 3)) == "S3:") {
		$fileDirectory = substr($fileDirectory, 3);
		if (strpos($fileDirectory, "/") === false) {
			$fileDirectory .= "/" . strtolower(getPreference("SYSTEM_NAME"));
		}
		$fileDirectory = trim($fileDirectory, "/");
		if (strpos($fileDirectory, "/") === false) {
			$fileDirectory .= "/" . strtolower(getPreference("SYSTEM_NAME"));
		}
		$osFilename = "S3://" . $fileDirectory . "/" . $filename;
		$awsAccessKey = getPreference("AWS_S3_ACCESS_KEY");
		$awsSecretKey = getPreference("AWS_S3_SECRET_KEY");

		$awsRegion = getPreference("AWS_REGION");
		$s3 = new S3($awsAccessKey, $awsSecretKey, false, 's3' . (empty($awsRegion) ? "" : "." . $awsRegion) . '.amazonaws.com', $awsRegion);
		$s3->setSignatureVersion('v4');
		$fileParts = explode("/", str_replace("S3://", "", $osFilename), 2);
		$bucketName = $fileParts[0];
		$objectName = $fileParts[1];
		$objectInfo = $s3->getObjectInfo($bucketName, $objectName);
		if (!empty($objectInfo) && md5($contents) == $objectInfo['hash']) {
			executeQuery("update images set hash_code = null,image_size = ?,extension = ?,os_filename = ?,file_content = null where image_id = ?", strlen($contents), $extension, $osFilename, $imageId);
			return $osFilename;
		}
		if ($s3->putObject($contents, $fileDirectory, $filename)) {
			executeQuery("update images set hash_code = null,image_size = ?,extension = ?,os_filename = ?,file_content = null where image_id = ?", strlen($contents), $extension, $osFilename, $imageId);
		} else {
			executeQuery("update images set hash_code = null,image_size = ?,extension = ?,os_filename = null,file_content = ? where image_id = ?", strlen($contents), $extension, $contents, $imageId);
		}
	} else {
		if (file_put_contents($osFilename, $contents)) {
			executeQuery("update images set hash_code = null,image_size = ?,extension = ?,os_filename = ?,file_content = null where image_id = ?", strlen($contents), $extension, $osFilename, $imageId);
		} else {
			executeQuery("update images set hash_code = null,image_size = ?,extension = ?,os_filename = null,file_content = ? where image_id = ?", strlen($contents), $extension, $contents, $imageId);
		}
	}
	return $osFilename;
}

function ajaxResponse($returnArray) {
	if (!is_array($returnArray)) {
		$returnArray = array($returnArray);
	}
	if ($GLOBALS['gUserRow']['superuser_flag'] && array_key_exists('error_message', $returnArray) && !is_array($returnArray['error_message'])) {
		$returnArray['console'] .= (empty($returnArray['console']) ? "" : "\n") . $returnArray['error_message'];
	}
	echo jsonEncode($returnArray);
	exit;
}

function getTimeChoices($startTime = 6, $endTime = 22, $increment = .5, $forEndTime = false) {
	$returnArray = array();
	for ($x = $startTime; $x <= $endTime; $x += $increment) {
		$hour = floor($x);
		$minutes = ($x - $hour) * 60;
		if ($x == 12) {
			$displayTime = "noon";
		} else if ($x == 24) {
			$displayTime = "midnight";
		} else {
			$displayTime = ($hour < 13 ? ($hour = 0 ? "12" : $hour) : ($hour - 12)) . ":" . str_pad($minutes, 2, "0", STR_PAD_LEFT) . ($x < 12 ? "am" : "pm");
		}
		$timeValue = ($forEndTime ? $x - $increment : $x);
		$returnArray[strval($timeValue)] = array("key_value" => $timeValue, "description" => $displayTime, "inactive" => false);
	}
	return $returnArray;
}

function updateUserSubscriptions($contactId = "") {
	$results = array();
	if(!empty($contactId)) {
		$limitClientId = getFieldFromId("client_id", "contacts", "contact_id", $contactId);
	} elseif(!$GLOBALS['gCommandLine']) {
		$limitClientId = $GLOBALS['gClientId'];
	}
	$clientSet = executeQuery("select * from clients where inactive = 0" . (empty($limitClientId) ? "" : " and client_id = " . $limitClientId));
	while ($clientRow = getNextRow($clientSet)) {
		$clientId = $clientRow['client_id'];
		changeClient($clientId);

		# remove user types where subscription is not active

		$userTypeSet = executeQuery("select * from user_types where subscription_id is not null and subscription_id in (select subscription_id from subscriptions where inactive = 0) and client_id = ? order by subscription_id", $clientId);

		$userUserTypes = array();
		$subscriptionRow = array();
		$filterWhere = "";
		if (!empty($contactId)) {
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $contactId);
			if (!empty($contactId)) {
				$filterWhere = " and contact_id = " . $contactId;
			}
		}
		$userTable = new DataTable("users");
		$userTable->setSaveOnlyPresent(true);
		while ($userTypeRow = getNextRow($userTypeSet)) {
			if (!array_key_exists($userTypeRow['user_type_code'], $userUserTypes)) {
				$userUserTypes[$userTypeRow['user_type_code']] = array();
			}
			if ($userTypeRow['subscription_id'] != $subscriptionRow['subscription_id']) {
				$subscriptionRow = getRowFromId("subscriptions", "subscription_id", $userTypeRow['subscription_id']);
			}
			// Set User type for active subscriptions
			$count = 0;
			$results[] = "Processing for user type " . $userTypeRow['description'] . ", Subscription " . $subscriptionRow['description'];
			$resultSet = executeQuery("select contact_id,(select user_id from users where contact_id = contact_subscriptions.contact_id) as user_id," .
				"(select user_type_id from users where contact_id = contact_subscriptions.contact_id) as user_type_id from contact_subscriptions where " .
				($subscriptionRow['units_subscription'] ? "units_remaining > 0" : "start_date <= current_date and (expiration_date is null or expiration_date >= current_date)") .
				" and subscription_id = ? and inactive = 0" . $filterWhere, $userTypeRow['subscription_id']);
			while ($row = getNextRow($resultSet)) {
				$userUserTypes[$userTypeRow['user_type_code']][] = $row['user_id'];
				if ($row['user_type_id'] != $userTypeRow['user_type_id']) {
					$userTable->saveRecord(array("name_values" => array("user_type_id" => $userTypeRow['user_type_id']), "primary_id" => $row['user_id']));
					$count++;
				}
			}
			$results[] = $count . " user types set for subscription " . $subscriptionRow['description'];

			// Set User type for shared memberships with active subscriptions
			$count = 0;
			$resultSet = executeQuery("select related_contact_id,(select user_id from users where contact_id = relationships.related_contact_id) as user_id," .
				"(select user_type_id from users where contact_id = relationships.related_contact_id) as user_type_id from relationships where " .
				"relationship_type_id in (select relationship_type_id from relationship_types where relationship_type_code = 'SHARES_MEMBERSHIP') and " .
				"contact_id in (select contact_id from contact_subscriptions where " .
				($subscriptionRow['units_subscription'] ? "units_remaining > 0" : "start_date <= current_date and (expiration_date is null or expiration_date >= current_date)") .
				" and subscription_id = ? and inactive = 0)" . $filterWhere, $userTypeRow['subscription_id']);
			while ($row = getNextRow($resultSet)) {
				$userUserTypes[$userTypeRow['user_type_code']][] = $row['user_id'];
				if ($row['user_type_id'] != $userTypeRow['user_type_id']) {
					$userTable->saveRecord(array("name_values" => array("user_type_id" => $userTypeRow['user_type_id']), "primary_id" => $row['user_id']));
					$count++;
				}
			}
			$results[] = $count . " user types set for shared members for subscription " . $subscriptionRow['description'];

			// remove user type for users with lapsed subscription
			$resultSet = executeQuery("select * from users where user_type_id = ?" . $filterWhere, $userTypeRow['user_type_id']);
			$count = 0;
			while ($row = getNextRow($resultSet)) {
				if (in_array($row['user_id'], $userUserTypes[$userTypeRow['user_type_code']])) {
					continue;
				}
				$userTable->saveRecord(array("name_values" => array("user_type_id" => ""), "primary_id" => $row['user_id']));
				$count++;
			}
			$results[] = $count . " user types cleared for subscription " . $subscriptionRow['description'];
		}

		# remove user groups where subscription is not active

		if (empty($contactId)) {
			$userUserGroups = array();

			$subscriptionSet = executeQuery("select *,(select user_group_code from user_groups where user_group_id = subscriptions.user_group_id) user_group_code," .
				"(select group_concat(user_id) from user_group_members where user_group_id = subscriptions.user_group_id) user_ids from subscriptions where user_group_id is not null and inactive = 0 and client_id = ? order by subscription_id", $clientId);
			while ($subscriptionRow = getNextRow($subscriptionSet)) {
				$userGroupCode = $subscriptionRow['user_group_code'];
				if (!array_key_exists($userGroupCode, $userUserGroups)) {
					$userUserGroups[$userGroupCode] = array('user_group_id' => $subscriptionRow['user_group_id'], "existing_user_ids" => array(), "user_ids" => array());
				}
				$userIds = explode(",", $subscriptionRow['user_ids']);
				foreach ($userIds as $thisUserId) {
					if (!empty($thisUserId)) {
						$userUserGroups[$userGroupCode]['existing_user_ids'][$thisUserId] = $thisUserId;
					}
				}

				$resultSet = executeQuery("select user_id from users where contact_id in (select contact_id from contact_subscriptions where " .
					($subscriptionRow['units_subscription'] ? "units_remaining > 0" : "start_date <= current_date and (expiration_date is null or expiration_date >= current_date)") .
					" and subscription_id = ? and inactive = 0)", $subscriptionRow['subscription_id']);
				while ($row = getNextRow($resultSet)) {
					$userUserGroups[$userGroupCode]['user_ids'][$row['user_id']] = $row['user_id'];
				}

				$resultSet = executeQuery("select user_id from users where contact_id in (select related_contact_id from relationships where relationship_type_id in (select relationship_type_id from relationship_types where relationship_type_code = 'SHARES_MEMBERSHIP') and " .
					"contact_id in (select contact_id from contact_subscriptions where " . ($subscriptionRow['units_subscription'] ? "units_remaining > 0" : "start_date <= current_date and (expiration_date is null or expiration_date >= current_date)") .
					" and subscription_id = ? and inactive = 0))", $subscriptionRow['subscription_id']);
				while ($row = getNextRow($resultSet)) {
					$userUserGroups[$userGroupCode]['user_ids'][$row['user_id']] = $row['user_id'];
				}
			}
			if (!empty($userUserGroups)) {
				foreach ($userUserGroups as $userGroupInfo) {
					$existingUserIds = $userGroupInfo['existing_user_ids'];
					$userIds = $userGroupInfo['user_ids'];
					$newUserIds = array();
					$deleteUserIds = array();
					foreach ($existingUserIds as $userId) {
						if (!array_key_exists($userId, $userIds)) {
							$deleteUserIds[] = $userId;
						}
					}
					foreach ($userIds as $userId) {
						if (!array_key_exists($userId, $existingUserIds)) {
							$newUserIds[] = $userId;
						}
					}
					if (!empty($deleteUserIds)) {
						$deleteSet = executeQuery("delete from user_group_members where user_id in (" . implode(",", $deleteUserIds) . ") and user_group_id = ?", $userGroupInfo['user_group_id']);
					}
					if (!empty($newUserIds)) {
						$insertSet = executeQuery("insert ignore into user_group_members (user_id,user_group_id) select user_id," . $userGroupInfo['user_group_id'] .
							" from users where user_id in (" . implode(",", $newUserIds) . ")");
					}
				}
			}
		} else {
			$userId = getFieldFromId("user_id", "users", "contact_id", $contactId);
			if (!empty($userId)) {
				$subscriptionSet = executeQuery("select * from subscriptions where user_group_id is not null and inactive = 0 and client_id = ? order by subscription_id", $clientId);
				while ($subscriptionRow = getNextRow($subscriptionSet)) {
					$userBelongs = false;
					$resultSet = executeQuery("select user_id from users where user_id = ? and contact_id in (select contact_id from contact_subscriptions where " .
						($subscriptionRow['units_subscription'] ? "units_remaining > 0" : "start_date <= current_date and (expiration_date is null or expiration_date >= current_date)") .
						" and subscription_id = ? and inactive = 0)", $userId, $subscriptionRow['subscription_id']);
					while ($row = getNextRow($resultSet)) {
						$userBelongs = true;
					}

					if (!$userBelongs) {
						$resultSet = executeQuery("select user_id from users where user_id = ? and contact_id in (select related_contact_id from relationships where relationship_type_id in (select relationship_type_id from relationship_types where relationship_type_code = 'SHARES_MEMBERSHIP') and " .
							"contact_id in (select contact_id from contact_subscriptions where " . ($subscriptionRow['units_subscription'] ? "units_remaining > 0" : "start_date <= current_date and (expiration_date is null or expiration_date >= current_date)") .
							" and subscription_id = ? and inactive = 0))", $userId, $subscriptionRow['subscription_id']);
						while ($row = getNextRow($resultSet)) {
							$userBelongs = true;
						}
					}
					if ($userBelongs) {
						executeQuery("insert ignore into user_group_members (user_id,user_group_id) values (?,?)", $userId, $subscriptionRow['user_group_id']);
					} else {
						executeQuery("delete from user_group_members where user_id = ? and user_group_id = ?", $userId, $subscriptionRow['user_group_id']);
					}
				}
			}
		}
	}
	return $results;
}

function getDomainName($noProtocol = false, $bareDomain = false, $ignorePreference = false) {
	$domainName = ($ignorePreference ? "" : getPreference("WEB_URL"));

	if (empty($domainName)) {
		$domainName = $_SERVER['HTTP_HOST'];
	}
	if (empty($domainName)) {
		$domainName = getFieldFromId("domain_name", "domain_names", "domain_client_id", $GLOBALS['gClientId'], "page_id is not null");
	}
	if (substr($domainName, 0, 4) !== "http" && !$noProtocol) {
		$domainName = "https://" . $domainName;
	}
	if ($noProtocol) {
		$domainName = str_replace("https://", "", $domainName);
		$domainName = str_replace("http://", "", $domainName);
	}
	if ($bareDomain) {
		$domainName = str_replace("//www.", "//", $domainName);
		if (substr($domainName, 0, 4) == "www.") {
			$domainName = substr($domainName, 4);
		}
	}
	return rtrim($domainName, "/");
}

function createCaptchaCode() {
	$captchaCodeId = "";
	while (empty($captchaCodeId)) {
		$insertSet = executeQuery("insert ignore into captcha_codes (client_id,captcha_code) values (?,?)", $GLOBALS['gClientId'], getRandomString(6, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'));
		$captchaCodeId = $insertSet['insert_id'];
	}
	return $captchaCodeId;
}

function startsWith($string, $startString) {
    if(empty($string) || !is_scalar($string)) {
        return false;
    }
	return substr(strtolower($string), 0, strlen($startString)) === strtolower($startString);
}

function endsWith($string, $endString) {
    if(empty($string) || !is_scalar($string)) {
        return false;
    }
	return substr(strtolower($string), -1 * strlen($endString)) === strtolower($endString);
}

function getUrlAliasTypeCode($tableName, $parameterName, $alternateParameterName = "") {
	$domainName = getDomainName(true);
	$urlAliasTypeCode = getCachedData("url_alias_type_code", $tableName . ":" . $parameterName . ":" . $alternateParameterName . ":" . $domainName);
	if (!empty($urlAliasTypeCode)) {
		return $urlAliasTypeCode;
	}
	$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", $parameterName,
		"table_id = (select table_id from tables where table_name = ?) and domain_name = ?", $tableName, $domainName);
	if (empty($urlAliasTypeCode)) {
		$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", $parameterName,
			"table_id = (select table_id from tables where table_name = ?) and domain_name is null", $tableName);
	}
	if (empty($urlAliasTypeCode) && !empty($alternateParameterName)) {
		$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", $alternateParameterName,
			"table_id = (select table_id from tables where table_name = ?) and domain_name = ?", $tableName, $domainName);
	}
	if (empty($urlAliasTypeCode) && !empty($alternateParameterName)) {
		$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", $alternateParameterName,
			"table_id = (select table_id from tables where table_name = ?) and domain_name is null", $tableName);
	}
	setCachedData("url_alias_type_code", $tableName . ":" . $parameterName . ":" . $alternateParameterName . ":" . $domainName, $urlAliasTypeCode);
	return $urlAliasTypeCode;
}

function getContentLines($content, $parameters = array()) {
	if (empty($parameters['limit']) || !is_numeric($parameters['limit'])) {
		$parameters['limit'] = PHP_INT_MAX;
	}
	$contentLines = explode("\n", str_replace("\r", "\n", trim($content)), $parameters['limit']);
	if (empty($parameters['dont_trim'])) {
		foreach ($contentLines as $index => $thisLine) {
			$contentLines[$index] = trim($thisLine);
		}
	}
	if (empty($parameters['leave_blank_lines'])) {
		$contentLines = array_values(array_filter($contentLines, 'strlen'));
	}
	return $contentLines;
}

function setCoreCookie($name, $value, $hoursToExpire = 720) {
	setcookie($name, $value, (!is_numeric($hoursToExpire) || $hoursToExpire === false ? 0 : time() + ($hoursToExpire * 3600)), "/", "", ($GLOBALS['gForceHttp'] || ($GLOBALS['gLocalExecution'] && $GLOBALS['gDevelopmentServer']) ? false : true), true);
	$_COOKIE[$name] = $value;
}

function numberFormat($num, $decimals = 0, $decimalSeparator = ".", $thousandsSeparator = ",") {
	if (empty($num) || !is_scalar($num) || !is_numeric(str_replace(",", "", $num))) {
		$num = 0;
	}
	return number_format(str_replace(",", "", $num), $decimals, $decimalSeparator, $thousandsSeparator);
}

function currencyFormat($number, $parameters = array()) {
    if (!is_array($parameters)) {
        $parameters['currency'] = $parameters;
    }
    if (empty($parameters['currency'])) {
        return numberFormat($number, 2);
    }
    if(extension_loaded('intl')) {
        $parameters['locale'] = $parameters['locale'] ?: "en_US";
        $fmt = numfmt_create($parameters['locale'], NumberFormatter::CURRENCY);
        return numfmt_format_currency($fmt, $number, $parameters['currency']);
    } else {
        $currencySymbol = '$';
        if(strtolower($parameters['currency']) != "usd") {
            $GLOBALS['gPrimaryDatabase']->logError("currencyFormat requires php_intl extension for currencies other than USD.");
            $currencySymbol = "";
        }
        return $currencySymbol . numberFormat($number, 2);
    }
}

function generateContactIdentifier($contactIdentifierTypeId) {
	$format = strtolower(getPreference("AUTOGENERATE_CONTACT_IDENTIFIER_FORMAT"));
	$contactIdentifierTypeCode = getFieldFromId("contact_identifier_type_code", "contact_identifier_types",
		"contact_identifier_type_id", $contactIdentifierTypeId);
	do {
		switch ($format) {
			case "numeric":
				$idString = getRandomString(7, "0123456789");
				break;
			case "numeric12":
				$idString = getRandomString(12, "0123456789");
				break;
			case "serial":
				$idString = 1000000 + getSequenceNumber($contactIdentifierTypeCode, 1000000);
				break;
			default:
				$idString = strtoupper(getRandomString(7));
		}
		$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "identifier_value", $idString,
			"contact_identifier_type_id = ?", $contactIdentifierTypeId);
	} while (!empty($contactIdentifierId));
	return $idString;
}

function systemDown() {
	$systemDownMessage = getPreference("SYSTEM_DOWN_MESSAGE")
		?: "<h1>The system is down to make improvements and give you a better experience! Thank you for your patience. Please try again later.</h1>";
	if (file_exists($GLOBALS['gDocumentRoot'] . "/systemdownlocal.html")) {
		$systemDownContent = file_get_contents($GLOBALS['gDocumentRoot'] . "/systemdownlocal.html");
	} else {
		$systemDownContent = file_get_contents($GLOBALS['gDocumentRoot'] . "/systemdown.html");
	}
	echo str_replace("%system_down_message%", $systemDownMessage, $systemDownContent);
	exit;
}

function getCredovaCredentials() {
	if (is_array($GLOBALS['gCredovaCredentials'])) {
		return $GLOBALS['gCredovaCredentials'];
	}
	if (function_exists("_localGetCredovaUsername")) {
		$returnArray = _localGetCredovaUsername();
		if (is_array($returnArray)) {
			$GLOBALS['gCredovaCredentials'] = $returnArray;
			return $returnArray;
		}
	}
	$returnArray = array();
	$returnArray['credova_payment_method_id'] = getReadFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "CREDOVA", "inactive = 0");
	if (!empty($returnArray['credova_payment_method_id'])) {
		$returnArray['username'] = getPreference("CREDOVA_USERNAME");
		$returnArray['password'] = getPreference("CREDOVA_PASSWORD");
		$returnArray['test_environment'] = (!empty($GLOBALS['gDevelopmentServer']));
	}
	$GLOBALS['gCredovaCredentials'] = $returnArray;
	return $returnArray;
}

function getAnalyticsProductKey($productRow) : string {
    // make sure default values are empty strings
    $productRow = array_merge(['product_id' => '', 'upc_code' => '', 'product_code' => ''], array_filter($productRow ?: array()));
    if(!empty(getPreference("ANALYTICS_USE_PRODUCT_CODE"))) {
        return $productRow['product_code'];
    }
    if(!empty(getPreference("ZAIUS_API_KEY"))) {
        return (empty(getPreference("ZAIUS_USE_UPC")) || empty($productRow['upc_code']) ? $productRow['product_id'] : $productRow['upc_code']);
    }
    return (empty($productRow['upc_code']) ? $productRow['product_id'] : $productRow['upc_code']);
}

function camelCaseToSnakeCase($string) {
	$string = str_replace(" ", "_", str_replace("  ", " ", str_replace("   ", " ", $string)));
	return str_replace("__", "_", str_replace("__", "_", strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string))));
}

function camelCaseToDescription($string) {
	$string = str_replace("  ", " ", str_replace("  ", " ", strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $string))));
	return ucwords(str_replace("  ", " ", str_replace("   ", " ", $string)));
}

function snakeCaseToDescription($string) {
	return ucwords(str_replace("_", " ", $string));
}

function snakeCaseToCamelCase($string) {
	return lcfirst(str_replace(" ", "", ucwords(str_replace("_", " ", $string))));
}

function saveSessionData() {
	ini_set('session.use_only_cookies', false);
	ini_set('session.use_cookies', false);
	ini_set('session.use_trans_sid', false);
	ini_set('session.cache_limiter', null);

	$saveSessionData = $_SESSION;
	session_start();
	$_SESSION = $saveSessionData;
	session_write_close();
}

function getSystemVersion($includeDbVersion = false) {
    $systemVersion = getCachedData("system_version", gethostname(), true);
    if(empty($systemVersion)) {
        // get most recently modified file or folder
        $filename = shell_exec("ls -rt " . $GLOBALS['gDocumentRoot'] . " | grep -vE 'js|cache|css' | tail -1");
        $filename = $GLOBALS['gDocumentRoot'] . "/" . trim($filename);
        if (file_exists($filename)) {
            $systemVersion = date("F d Y H:i:s T", filemtime($filename));
        }
        setCachedData("system_version", gethostname(), $systemVersion, .1, true);
    }
    $dbVersion = "";
    if($includeDbVersion) {
        $dbVersion = ", v." . $GLOBALS['gAllPreferences']['DATABASE_VERSION']['system_value'];
        $branchRef = getBranchRef();
        $dbVersion .= empty($branchRef) ? "" : ", $branchRef";
    }
    return $systemVersion . $dbVersion;
}

// will update the saved preference if running as a user with access to .git, otherwise will return the saved preference
function getBranchRef() {
    $branchRef = file_get_contents($GLOBALS['gDocumentRoot'] . "/.git/HEAD");
    $savedBranchRef = getPreference("DEPLOYMENT_BRANCH_REF");
    if (!empty($branchRef)) {
        $branchRef = str_replace("ref: ", "", $branchRef);
        if ($branchRef != $savedBranchRef) {
            $preferenceArray = array(['preference_code' => 'DEPLOYMENT_BRANCH_REF', 'description' => 'Deployment Branch Name', 'data_type' => 'varchar', 'client_setable' => 0,
                'hide_system_value' => 1, 'system_value' => $branchRef]);
            setupPreferences($preferenceArray);
        }
    } else {
        $branchRef = $savedBranchRef;
    }
    return str_replace("refs/heads/", "", trim($branchRef));
}

function sendDeploymentWebhook($headerInput) {
    $serverArray = getPreference("DEPLOYMENT_WEBHOOK_SERVERS");
    $results = "";
    if (!empty($serverArray)) {
        $serverArray = json_decode($serverArray, true);
        foreach ($serverArray as $index => $thisServer) {
            if (!empty($thisServer['inactive'])) {
                $results .= sprintf("Repeating webhook to %s is inactive.\n", $thisServer['link_url']);
                continue;
            }
            $url = filter_var($thisServer['link_url'], FILTER_SANITIZE_URL);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $results .= sprintf("Configured to repeat webhook to %s but this URL is invalid.\n", $thisServer['link_url']);
                continue;
            }
            $signature = hash_hmac('sha256', $headerInput, $thisServer['secret']);

            $headers = array(
                "x-hub-signature-256: sha256=$signature",
                "x-webhook-proxy: " . gethostname(),
                "Content-Type: application/json",
                "Content-Length: " . strlen($headerInput)
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $headerInput);

            $result = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results .= sprintf("Webhook repeated to server %s: status code %s%s\n", $url, $httpCode, (empty($err) ? "" : ", error $err"));
            if(strstr($result,"\n") !== false) { // if response is multi-line, ignore it
                $result = "";
            }
            $thisServer['last_result'] = "$httpCode $result";
            $serverArray[$index] = $thisServer;
        }
        executeQuery("UPDATE preferences SET system_value = '" . json_encode($serverArray) . "' WHERE preference_code = 'DEPLOYMENT_WEBHOOK_SERVERS'");
    }
    return $results;
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/shared/localcommons.inc")) {
	include_once "localcommons.inc";
} elseif ($_SERVER['SERVER_NAME'] == 'coreware.local' && file_exists($GLOBALS['gDocumentRoot'] . "/local/localcommons.inc")) {
	include_once $GLOBALS['gDocumentRoot'] . "/local/localcommons.inc";
}
