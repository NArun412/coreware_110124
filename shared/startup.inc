<?php
error_reporting(E_ERROR | E_PARSE);
//echo hash('sha256',"9009"."365559adc8545704c679b100f4c7c5bd3759ce12f8c002b58e65097c2036d534"."Arun@412");die;
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

/*	startup.inc
	Things to be done in this include, which is run at the beginning of every Page:
	- Set PHP properties
	- Check for PHP properties that might cause security risks
	- include Pages that will be used in all Pages
	- create the global variables used throughout the system, including the userRow and pageRow
	- check to see if the user is logged in
	- check to see if user has timed out, requires new credentials, has been inactivated, or locked out
	- check to see what permission level the user has for the Page being run
	- if the Page code is not set, go to the home page unless the user is a superuser
	- check for hacking attempts and log IP address
	- if IP address is in blacklist, display message
	- set the autoloading function for classes
	- create the error handler function
*/

# Set PHP properties

$GLOBALS['gOverallStartTime'] = round(microtime(true) * 1000);
$GLOBALS['gStartTime'] = round(microtime(true) * 1000);

mb_language('uni');
mb_internal_encoding('UTF-8');
ini_set('gd.jpeg_ignore_warning', true);

$ignoreUrlRequests = array("assetlinks");
foreach ($ignoreUrlRequests as $thisRequest) {
	if (stripos($_SERVER['REQUEST_URI'], $thisRequest) !== false) {
		header("HTTP/1.0 404 Not Found");
		echo json_encode(array());
		exit;
	}
}

if (strpos($_SERVER['REQUEST_URI'], "product-search-results") !== false && count($_GET) == 1 && !empty($_GET['search_text'])) {
	$attackFound = false;
	if (is_array($_GET['search_text'])) {
		$attackFound = true;
	} else if (substr($_GET['search_text'], 0, 1) == "X" && is_numeric(substr($_GET['search_text'], 1))) {
		$attackFound = true;
	} else if (is_numeric($_GET['search_text']) && empty($_SERVER['HTTP_REFERER'])) {
		$attackFound = true;
	}
	if ($attackFound) {
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: 120');
		exit;
	}
}

$runEnvironment = php_sapi_name();
$GLOBALS['gCommandLine'] = ($runEnvironment == "cli");
$GLOBALS['gApcuEnabled'] = !$GLOBALS['gCommandLine'] && ((extension_loaded('apc') && ini_get('apc.enabled')) || (extension_loaded('apcu') && ini_get('apc.enabled')));
if ($GLOBALS['gCommandLine'] || $GLOBALS['gPageCode'] == "BACKGROUNDPROCESS") {
	$memoryLimit = ini_get("memory_limit");
	if (str_replace("M", "", $memoryLimit) < 8192) {
		ini_set("memory_limit", "8192M");
	}
}

# Here, check for cached results that could be fed directly instead of connecting to the database and all the associated overhead

$GLOBALS['gDocumentRoot'] = $_SERVER['DOCUMENT_ROOT'];
if (empty($GLOBALS['gDocumentRoot'])) {
	$GLOBALS['gDocumentRoot'] = str_replace("/shared/startup.inc", "", __FILE__);
}
if (substr($GLOBALS['gDocumentRoot'], -1) == "/" || substr($GLOBALS['gDocumentRoot'], -1) == "\\") {
	$GLOBALS['gDocumentRoot'] = substr($GLOBALS['gDocumentRoot'], 0, -1);
}

if (!isset($_SERVER['HTTPS']) && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
	$_SERVER['HTTPS'] = 'on';
}
if ($GLOBALS['gApcuEnabled']) {
	if ($_GET['url_source'] == "tagged_products") {
		$postVariables = array_merge($_GET, $_POST);
		ksort($postVariables);
		unset($postVariables['_']);
		$urlCacheKey = md5($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . json_encode($postVariables));
		$apcuKey = "ALL|request_search_result|" . $urlCacheKey;
		$returnArray = false;
		if (apcu_exists($apcuKey)) {
			$returnArray = apcu_fetch($apcuKey);
		}
		if (is_array($returnArray) && !empty($returnArray)) {
			echo json_encode($returnArray);
			exit;
		}
	}
	if (strpos($_SERVER['REQUEST_URI'], "getautocompletedata.php") !== false && $_GET['get_value'] == "true") {
		$apcuKey = "ALL|getautocomplete_text|" . $_GET['tag'] . ":" . $_GET['value_id'];
		$autocompleteText = false;
		if (apcu_exists($apcuKey)) {
			$autocompleteText = apcu_fetch($apcuKey);
		}
		if (!empty($autocompleteText)) {
			$returnArray['autocomplete_text'] = $autocompleteText;
			echo json_encode($returnArray);
			exit;
		}
	}
	if (strpos($_SERVER['REQUEST_URI'], "api.php") !== false && $_GET['method'] == "last_form_date") {
		$apcuKey = "ALL|last_form_date_" . $_POST['contact_id'] . "|" . $_POST['form_definition_code'] . ":" . $_POST['form_definition_group_code'];
		$cachedContent = false;
		if (apcu_exists($apcuKey)) {
			$cachedContent = apcu_fetch($apcuKey);
		}
		if (!empty($cachedContent) && is_array($cachedContent)) {
			echo json_encode($cachedContent);
			exit;
		}
	}

	$apcuKey = "ALL|hacking_terms|";
	if (apcu_exists($apcuKey)) {
		$hackingTerms = apcu_fetch($apcuKey);
	}
	if (!empty($hackingTerms) && is_array($hackingTerms) && array_key_exists("search_terms", $hackingTerms) && !empty($hackingTerms['search_terms'])) {
		$searchTerms = $hackingTerms['search_terms'];
		$blackList = false;
		foreach ($searchTerms as $searchTerm) {
			if (stripos($_SERVER['REQUEST_URI'], $searchTerm) !== false) {
				$blackList = true;
				break;
			}
			if (stripos($_SERVER['HTTP_USER_AGENT'], $searchTerm) !== false) {
				$blackList = true;
				break;
			}
		}
		if ($blackList) {
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 120');
			exit;
		}
	}
}

$GLOBALS['gSubdomain'] = "";
$domainName = $_SERVER['HTTP_HOST'];
$domainNameParts = explode(".", $domainName);
$topLevelDomainName = array_pop($domainNameParts);
$GLOBALS['gLocalExecution'] = ($topLevelDomainName == "local") || $_SERVER['REMOTE_ADDR'] == "127.0.0.1";
$mainNameDomainName = array_pop($domainNameParts);
$GLOBALS['gBaseDomainName'] = (empty($domainName) ? "" : $mainNameDomainName . "." . $topLevelDomainName);
if (count($domainNameParts) > 0) {
	$GLOBALS['gSubdomain'] = implode(".", $domainNameParts);
	if ($GLOBALS['gSubdomain'] == "www") {
		$GLOBALS['gSubdomain'] = "";
	}
}

if (!$GLOBALS['gLocalExecution'] && !$GLOBALS['gCommandLine'] && !$_SERVER['HTTPS'] && (file_exists($GLOBALS['gDocumentRoot'] . "/force_https") || $GLOBALS['gForceSSL'])) {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
	exit;
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/shared/preload_cache.inc")) {
	include_once "preload_cache.inc";
}
if (function_exists("_localPreloadCache")) {
	_localPreloadCache();
}

$GLOBALS['gLineEnding'] = ($GLOBALS['gCommandLine'] ? "\n" : "<br>\n");
if (function_exists("newrelic_set_appname")) {
	newrelic_set_appname((empty($_SERVER['HTTP_HOST']) || $GLOBALS['gCommandLine'] ? "" : $_SERVER['HTTP_HOST'] . ";") . 'CoreFORCE');
}

$ipAddress = $_SERVER['REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    foreach (explode(",",$_SERVER['HTTP_X_FORWARDED_FOR']) as $xip) {
        if (filter_var($xip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $ipAddress = $xip;
            break;
        }
    }
} else if (isset($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'],FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
} else if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'],FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
} else if (isset($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'],FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $ipAddress = $_SERVER['HTTP_X_REAL_IP'];
}
if (!empty($ipAddress)) {
	$_SERVER['REMOTE_ADDR'] = $ipAddress;
}
header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime("+ 1 year")));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

$ipAddressPossibilities = array($_SERVER['REMOTE_ADDR']);
for ($x = 1; $x < 32; $x++) {
	$longValue = ip2long($_SERVER['REMOTE_ADDR']);
	$clearValue = pow(2, $x) - 1;
	$newValue = $longValue & ~$clearValue;
	$thisIpAddress = long2ip($newValue) . "/" . (32 - $x);
	$ipAddressPossibilities[] = $thisIpAddress;
}

function isCdnRequest() {
	$parts = explode(".", $_SERVER['HTTP_HOST']);
	return (count($parts) > 2 && stripos($parts[0], "cdn") !== false);
}

function isWebCrawler($favored = false) {
	if ($GLOBALS['gLoggedIn']) {
		return false;
	}
	$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
	$botIdentifiers = array(
		'bot',
		'slurp',
		'crawler',
		'spider',
		'curl',
		'facebook',
		'fetch'
	);
	$favoredBots = array("googlebot", "applebot", "bingbot");
	foreach ($botIdentifiers as $identifier) {
		if (stripos($userAgent, $identifier) !== false) {
			$isFavored = false;
			if ($favored) {
				foreach ($favoredBots as $identifier) {
					if (stripos($userAgent, $identifier) !== false) {
						return true;
					}
				}
				return false;
			}
			return true;
		}
	}
	return false;
}

$requestPage = $_SERVER['REQUEST_URI'];
if (!$GLOBALS['gLocalExecution'] && !isWebCrawler() && strpos($requestPage, "sitemap") === false && !$GLOBALS['gCommandLine'] && (empty($_GET['whitelist_user_id']) || empty($_GET['whitelist_user_name']))) {
	$apcuKey = "ALL|blacklisted_ips|";
	if ($GLOBALS['gApcuEnabled'] && apcu_exists($apcuKey)) {
		$blacklistedIpAddresses = apcu_fetch($apcuKey);
	}
	if (!is_array($blacklistedIpAddresses)) {
		$blackList = file_get_contents($GLOBALS['gDocumentRoot'] . "/cache/" . gethostname() . "_blacklist.inc");
		$blackListArray = explode("\n", $blackList);
		$blacklistedIpAddresses = array();
		foreach ($blackListArray as $thisIpAddress) {
			$blacklistedIpAddresses[$thisIpAddress] = true;
		}
	}

	foreach ($ipAddressPossibilities as $thisIpAddress) {
		if (array_key_exists($thisIpAddress, $blacklistedIpAddresses)) {
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 120');
			exit;
		}
	}
	unset($blackListArray);
	unset($blacklistIpArray);
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/shared/localinitialize.inc")) {
	include_once "localinitialize.inc";
}

# Universal includes

include_once "mimetypes.inc";

# Create some global variables that will simplify some code

$GLOBALS['gDatabaseConnectionCount'] = 0;
$GLOBALS['gLogDatabaseQueries'] = false;
$GLOBALS['gLogLiveQueries'] = false;
$GLOBALS['gQueryList'] = "";
$GLOBALS['gLoggedIn'] = false;
$GLOBALS['gClientId'] = 0;
$GLOBALS['gDefaultClientId'] = 1;
$GLOBALS['gCurlTimeout'] = 30;
$GLOBALS['gAllClientRows'] = array();
$GLOBALS['gClientRow'] = array();
$GLOBALS['gClientName'] = "";
$GLOBALS['gUserId'] = "";
$GLOBALS['gUserRow'] = array();
$GLOBALS['gPageId'] = 0;
$GLOBALS['gPageRow'] = array();
$GLOBALS['gPageObject'] = null;
$GLOBALS['gSystemMessages'] = false;
$GLOBALS['gDomainClientId'] = false;
$GLOBALS['gClientSubsystems'] = array();
$GLOBALS['gClientSubsystemCodes'] = array();
$GLOBALS['gMonthArray'] = array("1" => "January", "2" => "February", "3" => "March", "4" => "April", "5" => "May", "6" => "June", "7" => "July",
	"8" => "August", "9" => "September", "10" => "October", "11" => "November", "12" => "December");
$GLOBALS['gWeekdays'] = array("0" => "Sunday", "1" => "Monday", "2" => "Tuesday", "3" => "Wednesday", "4" => "Thursday", "5" => "Friday", "6" => "Saturday");
$GLOBALS['gWeekdayCodes'] = array("SUN" => "Sunday", "MON" => "Monday", "TUE" => "Tuesday", "WED" => "Wednesday", "THU" => "Thursday",
	"FRI" => "Friday", "SAT" => "Saturday");
$GLOBALS['gNumberWords'] = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten");
$GLOBALS['gValidImageFileTypes'] = array('jpg', 'jpeg', 'gif', 'png', 'svg', "ico", "webp");
$scriptParts = explode("/", $_SERVER['SCRIPT_NAME']);
$GLOBALS['gScriptName'] = $scriptParts[count($scriptParts) - 1];
$GLOBALS['gLinkUrl'] = "";
$GLOBALS['gIgnoreError'] = false;
$GLOBALS['gLanguageColumns'] = array();
$GLOBALS['gLanguageColumnRows'] = array();
$GLOBALS['gNoTranslation'] = false;
$GLOBALS['gTableKeys'] = false;
$GLOBALS['gLanguageText'] = array();
$GLOBALS['gTextTranslations'] = array();
$GLOBALS['gTranslatableColumns'] = array();
$GLOBALS['gInternalConnection'] = false;
$GLOBALS['gInternalDevice'] = false;
$GLOBALS['gPageCodes'] = array();
$GLOBALS['gAllPreferences'] = array();
$GLOBALS['gAllMenus'] = array();
$GLOBALS['gMenuCodes'] = array();
$GLOBALS['gEmbeddedPage'] = false;
$GLOBALS['gDefaultAutocompleteFields'] = array(
	"inventory_product_id" => array("key_field" => "product_id", "description_field" => "product_code,description"),
	"product_id" => array("key_field" => "product_id", "description_field" => "product_code,description"),
	"company_id" => array("key_field" => "company_id", "description_field" => "contact_id"),
	"contains_product_id" => array("key_field" => "product_id", "description_field" => "product_code,description"),
	"product_facet_id" => array("key_field" => "product_facet_id", "description_field" => "description"),
	"product_manufacturer_id" => array("key_field" => "product_manufacturer_id", "description_field" => "description"),
	"product_facet_option_id" => array("key_field" => "product_facet_option_id", "description_field" => "facet_value"),
	"associated_product_id" => array("key_field" => "product_id", "description_field" => "product_code,description"),
	"designation_id" => array("key_field" => "designation_id", "description_field" => "designation_code,description"));
$GLOBALS['gStopWords'] = false;
$GLOBALS['gSearchTermSynonyms'] = false;
if (!array_key_exists("gDefaultAjaxTimeout", $GLOBALS)) {
	$GLOBALS['gDefaultAjaxTimeout'] = 30000;
}
$GLOBALS['gInventoryAdjustmentTypeId'] = false;
$GLOBALS['gRestockAdjustmentTypeId'] = false;
$GLOBALS['gSalesAdjustmentTypeId'] = false;
$GLOBALS['gCategoryGroupProducts'] = false;
$GLOBALS['gDepartmentProducts'] = false;
$GLOBALS['gChangeLogNotes'] = "";
$GLOBALS['gAuthenticationErrorLogged'] = false;
$GLOBALS['gReadOnlyAccessCount'] = 0;
$GLOBALS['gPageCacheUsed'] = false;
$GLOBALS['gCustomFieldData'] = array();
$GLOBALS['gOriginalPostVariables'] = $_POST;
$GLOBALS['gOriginalGetVariables'] = $_GET;
$GLOBALS['gIgnoreRecentPages'] = array("ADMIN_PAGE_NOT_FOUND", "INDEX", "ADMINMENU", "LOGIN", "LOGOUT", "URLALIAS");
$GLOBALS['gProductRows'] = array();
$GLOBALS['gProductCodes'] = array();
$GLOBALS['gLanguageTableName'] = false;
$GLOBALS['coreware_product_metadata'] = false;

$versionParts = explode('.', phpversion());
$GLOBALS['gPHPVersion'] = ($versionParts[0] * 10000 + $versionParts[1] * 100 + $versionParts[2]);

$GLOBALS['gRedirectLogEntry'] = "Page Loaded at " . date("m/d/Y g:i a") . "\n";

# Check command line options, specifically for background process

$options = getopt("", array("database::"));
$GLOBALS['gCommandLineDatabaseName'] = $options['database'];

# Reject certain browsers

require_once $GLOBALS['gDocumentRoot'] . "/classes/scss.inc.php";
include_once "commons.inc";
include_once "cssmin.php";

if (!empty($_GET['cart_total'])) {
	$failedOrderAmounts = getCachedData("failed_order_amounts", "", true);
	if (!is_array($failedOrderAmounts)) {
		$failedOrderAmounts = array();
	}
	$checkTime = time() - 120;
	$failureCount = 0;
	foreach ($failedOrderAmounts as $index => $failInfo) {
		if ($failInfo['time'] < $checkTime) {
			unset($failedOrderAmounts[$index]);
			continue;
		}
		if ($failInfo['domain_name'] != $_SERVER['HTTP_HOST'] || $_GET['cart_total'] != $failInfo['amount']) {
			continue;
		}
		$failureCount++;
	}
	setCachedData("failed_order_amounts", "", $failedOrderAmounts, 1, true);
	if ($failureCount > 7) {
		addDebugLog($_SERVER['HTTP_HOST'] . ": " . date("m/d/Y H:i:s") . " : Blocked Credit Card Testing for amount " . $_GET['cart_total'],true);
		echo json_encode(array("error_message"=>"Charge failed (2450): Do not honor"));
		exit;
	}
}


/*
# sample code for timing operations
if ($GLOBALS['gUserId'] == 10000) {
	$GLOBALS['gEndTime'] = getMilliseconds();
	addDebugLog("8hu4898hf: " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime'])/1000,4));
	$GLOBALS['gStartTime'] = getMilliseconds();
}
*/

# Check a text file for IP addresses that are not allowed to use the system. First calculate the addresses that would make, as
# in xxx.xxx.xxx.xxx/xx. This will be used again below.

$GLOBALS['gDatabaseName'] = "gunstoresdb";
$GLOBALS['gDatabaseUsername'] = "root";
$GLOBALS['gDatabasePassword'] = "";
$GLOBALS['gDatabaseHostName'] = "";
$GLOBALS['gDatabaseConnections'] = array();

# Start session

function startSessionWarning($errno, $errstr) {
	session_regenerate_id();
	session_start();
}

ini_set('auto_detect_line_endings', TRUE);
if (!$GLOBALS['gLocalExecution']) {
	if (file_exists($GLOBALS['gDocumentRoot'] . "/allow_http")) {
		ini_set("session.cookie_secure", 0);
		ini_set('session.cookie_httponly', 0);
	} else {
		ini_set("session.cookie_secure", 1);
		ini_set('session.cookie_httponly', 1);
	}
	set_error_handler("startSessionWarning", E_WARNING);
}

session_start();

if (array_key_exists("allow_session_data",$_GET) && !empty($_GET['allow_session_data'])) {
	ksort($_GET);
	$cachedDataKey = md5(jsonEncode(array_diff_key($_GET, array("allow_session_data" => true, "_" => true))));
	if (array_key_exists("session_cached_data", $_SESSION) && array_key_exists($cachedDataKey, $_SESSION['session_cached_data'])) {
		$returnArray = $_SESSION['session_cached_data'][$cachedDataKey];
		unset($_SESSION['session_cached_data'][$cachedDataKey]);
		ajaxResponse($returnArray);
		exit;
	}
}

# get search results and send them
if (array_key_exists("product_search_results_timestamp", $_SESSION) && is_array($_SESSION['product_search_results_timestamp'])) {
	foreach ($_SESSION['product_search_results_timestamp'] as $resultsKey => $timestamp) {
		if (($timestamp + 60) < time()) {
			unset($_SESSION['product_search_results_array'][$resultsKey]);
			unset($_SESSION['product_search_results_timestamp'][$resultsKey]);
		}
	}
}
if (array_key_exists("url_action", $_GET) && $_GET['url_action'] == "get_product_search_results") {
	if (array_key_exists("product_search_results_array", $_SESSION) && is_array($_SESSION['product_search_results_array'])) {
		$returnArray['product_search_results'] = $_SESSION['product_search_results_array'][$_GET['results_key']];
		unset($_SESSION['product_search_results_array'][$_GET['results_key']]);
		unset($_SESSION['product_search_results_timestamp'][$_GET['results_key']]);
		ajaxResponse($returnArray);
		exit;
	}
}

if (!array_key_exists("use_count", $_SESSION)) {
	$_SESSION['use_count'] = 0;
}
if ((!array_key_exists("ajax",$_GET) || empty($_GET['ajax'])) && (!array_key_exists("REQUEST_URI", $_SERVER) || strpos($_SERVER['REQUEST_URI'], "getimage.php") === false)) {
	$_SESSION['use_count']++;
	if ($_SESSION['use_count'] > 20) {
		session_regenerate_id();
		$_SESSION['use_count'] = 0;
	}
}

set_error_handler("customErrorHandler", E_ERROR | E_RECOVERABLE_ERROR);

if ($_GET['ajax'] == "true" && $_GET['url_action'] == "get_user_timeout_seconds") {
	$secondsLeft = max(0, ($_SESSION['timeout'] - time()));
	$returnArray = array('seconds_left' => $secondsLeft, "session" => $_SESSION, "time" => time());
	ajaxResponse($returnArray);
}
$_SESSION['autocomplete_address'] = "coreware system";

# Create primary database connection

$GLOBALS['gDevelopmentServer'] = true;
if (file_exists($GLOBALS['gDocumentRoot'] . "/shared/connect.inc")) {
	include_once "connect.inc";
}
$GLOBALS['gDatabaseConnections'][] = array("domain_name" => "localhost", "development" => true);
$GLOBALS['gDatabaseConnections'][] = array("domain_name" => "*.dev", "development" => true);
$GLOBALS['gDatabaseConnections'][] = array("domain_name" => "*.local", "development" => true);
$GLOBALS['gDatabaseConnections'][] = array("domain_name" => "*.test", "development" => true);
$foundConnection = false;
foreach ($GLOBALS['gDatabaseConnections'] as $databaseInfo) {
	if (empty($databaseInfo['domain_name'])) {
		continue;
	}
	$partialSearch = false;
	if (substr($databaseInfo['domain_name'], 0, 2) == "*.") {
		$databaseInfo['domain_name'] = substr($databaseInfo['domain_name'], 2);
		$partialSearch = true;
	}
	if (substr($databaseInfo['domain_name'], 0, 1) == "*") {
		$databaseInfo['domain_name'] = substr($databaseInfo['domain_name'], 1);
		$partialSearch = true;
	}
	if ($databaseInfo['domain_name'] == $_SERVER['HTTP_HOST'] ||
		($partialSearch && substr($_SERVER['HTTP_HOST'], (-1 * strlen($databaseInfo['domain_name']))) == $databaseInfo['domain_name'])) {
		if (array_key_exists("database_name", $databaseInfo)) {
			$GLOBALS['gDatabaseName'] = $databaseInfo['database_name'];
		}
		if (array_key_exists("user_name", $databaseInfo)) {
			$GLOBALS['gDatabaseUsername'] = $databaseInfo['user_name'];
		}
		if (array_key_exists("password", $databaseInfo)) {
			$GLOBALS['gDatabasePassword'] = $databaseInfo['password'];
		}
		if (array_key_exists("host_name", $databaseInfo)) {
			$GLOBALS['gDatabaseHostName'] = $databaseInfo['host_name'];
		}
		$GLOBALS['gDevelopmentServer'] = $databaseInfo['development'];
		$GLOBALS['gOverrideLocalDatabase'] = $databaseInfo['override_localhost'];
		$GLOBALS['gReadReplicaDatabaseHostName'] = $databaseInfo['read_replica'];
		$foundConnection = true;
		break;
	}
}
if (!$foundConnection) {
	foreach ($GLOBALS['gDatabaseConnections'] as $databaseInfo) {
		if (!empty($databaseInfo['domain_name'])) {
			continue;
		}
		if (array_key_exists("database_name", $databaseInfo)) {
			$GLOBALS['gDatabaseName'] = $databaseInfo['database_name'];
		}
		if (array_key_exists("user_name", $databaseInfo)) {
			$GLOBALS['gDatabaseUsername'] = $databaseInfo['user_name'];
		}
		if (array_key_exists("password", $databaseInfo)) {
			$GLOBALS['gDatabasePassword'] = $databaseInfo['password'];
		}
		if (array_key_exists("host_name", $databaseInfo)) {
			$GLOBALS['gDatabaseHostName'] = $databaseInfo['host_name'];
		}
		$GLOBALS['gDevelopmentServer'] = $databaseInfo['development'];
		$GLOBALS['gOverrideLocalDatabase'] = $databaseInfo['override_localhost'];
		$GLOBALS['gReadReplicaDatabaseHostName'] = $databaseInfo['read_replica'];
		break;
	}
}
if (!$GLOBALS['gDevelopmentServer']) {
	$GLOBALS['gLogDatabaseQueries'] = false;
} else if (!$GLOBALS['gOverrideLocalDatabase']) {
	$GLOBALS['gDatabaseHostName'] = "localhost";
}
$GLOBALS['gPrimaryDatabase'] = new Database($GLOBALS['gDatabaseName'], $GLOBALS['gDatabaseUsername'], $GLOBALS['gDatabasePassword'], $GLOBALS['gDatabaseHostName']);
if ($GLOBALS['gForceDevelopmentServer']) {
	$GLOBALS['gDevelopmentServer'] = true;
}

if ($GLOBALS['gPrimaryDatabase'] === false || !empty($GLOBALS['gPrimaryDatabase']->iDBConnection->connect_errno)) {
	if ($GLOBALS['gDevelopmentServer']) {
		echo $GLOBALS['gDatabaseName'] . ":" . $GLOBALS['gDatabaseUsername'] . ":" . $GLOBALS['gDatabaseHostName'] . ":" . htmlText($_SERVER['HTTP_HOST'] . ":" . $_SERVER['HOSTNAME'] . ":" . gethostname()) . "<br>";
		echo $GLOBALS['gPrimaryDatabase']->iDBConnection->connect_errno;
		exit;
	}
	addDebugLog($GLOBALS['gPrimaryDatabase']->iDBConnection->connect_errno, true);
	if (!$GLOBALS['gCommandLine'] && file_exists($GLOBALS['gDocumentRoot'] . "/maintenance.html")) {
		header("Location: /maintenance.html");
		exit;
	} else {
		echo "<h1>The server is down right now. Sorry for the inconvenience. Please try again later.</h1>";
		echo "<p style='display: none'>Database connection error! Please contact customer service. " . $GLOBALS['gDatabaseName'] . ":" . $GLOBALS['gDatabaseUsername'] . ":" . $GLOBALS['gDatabaseHostName'] . ":" . $_SERVER['HTTP_HOST'] . ":" . $_SERVER['HOSTNAME'] . ":" . gethostname() . "</p>";
		exit;
	}
}

$GLOBALS['gReadReplicaDatabase'] = false;
if (!empty($GLOBALS['gReadReplicaDatabaseHostName'])) {
	$GLOBALS['gReadReplicaDatabase'] = new Database($GLOBALS['gDatabaseName'], $GLOBALS['gDatabaseUsername'], $GLOBALS['gDatabasePassword'], $GLOBALS['gReadReplicaDatabaseHostName'], true);
	if ($GLOBALS['gReadReplicaDatabase'] === false || !empty($GLOBALS['gReadReplicaDatabase']->iDBConnection->connect_errno)) {
		$GLOBALS['gReadReplicaDatabase'] = false;
	}
}
$GLOBALS['gMaximumMemory'] = ini_get('memory_limit');
if (preg_match('/^(\d+)(.)$/', $GLOBALS['gMaximumMemory'], $matches)) {
	if ($matches[2] == 'M') {
		$GLOBALS['gMaximumMemory'] = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
	} else if ($matches[2] == 'K') {
		$GLOBALS['gMaximumMemory'] = $matches[1] * 1024; // nnnK -> nnn KB
	}
}

$resultSet = executeReadQuery("select *,(select group_concat(concat_ws(page_id,permission_level)) from client_access where client_id = clients.client_id) as client_access from contacts join clients using (contact_id) order by business_name,first_name,last_name");
while ($row = getNextRow($resultSet)) {
	$clientAccess = array();
	if (!empty($row['client_access'])) {
		foreach (explode(",", $row['client_access']) as $thisClientAccess) {
			$parts = explode("|", $thisClientAccess);
			$pageId = $parts[0];
			$permissionLevel = $parts[1];
			$clientAccess[$pageId] = $permissionLevel;
		}
	}
	$row['client_access'] = $clientAccess;
	$GLOBALS['gAllClientRows'][$row['client_id']] = $row;
}

# Load Preferences

$GLOBALS['gAllPreferences'] = array();
$resultSet = executeReadQuery("select * from preferences where inactive = 0");
while ($row = getNextRow($resultSet)) {
	$GLOBALS['gAllPreferences'][$row['preference_code']] = $row;
}
$GLOBALS['gClientCount'] = count($GLOBALS['gAllClientRows']);
if ($GLOBALS['gClientCount'] > 10 && empty(getPreference("NO_UPLOAD_SIZE_LIMIT"))) {
	$GLOBALS['gMaximumUploadFileSize'] = 10000000;
	ini_set('upload_max_filesize', '10M');
}

if (empty($_GET['ajax']) && $GLOBALS['gApcuEnabled'] && file_exists($GLOBALS['gDocumentRoot'] . "/cache/" . strtolower(getPreference("SYSTEM_NAME")) . "_clearcache.txt")) {
    $clearCacheContents = file_get_contents($GLOBALS['gDocumentRoot'] . "/cache/" . strtolower(getPreference("SYSTEM_NAME")) . "_clearcache.txt");
    $parts = explode(",", $clearCacheContents);
    $triggerTime = $parts[0];
    $cacheKey = $parts[1];
    if (!empty($triggerTime) && $triggerTime > (time() - 300)) {
        $lastCleared = getCachedData("trigger_server_clear_cache", "trigger_server_clear_cache", true);
        if (empty($lastCleared) || $lastCleared < $triggerTime) {
            if (empty($cacheKey)) {
                addDebugLog("Full server cache clear triggered from file");
                apcu_clear_cache();
            } else {
                addDebugLog("Server cache clear for key '" . $cacheKey . "' triggered from file");
                $cacheTypes = array_filter(explode("|", $cacheKey));
                foreach ($cacheTypes as $cacheType) {
                    removeCachedData($cacheType, "*", true);
                }
            }
            setCachedData("trigger_server_clear_cache", "trigger_server_clear_cache", time(), 24, true);
        }
    } else {
        unlink($GLOBALS['gDocumentRoot'] . "/cache/" . strtolower(getPreference("SYSTEM_NAME")) . "_clearcache.txt");
    }
}


$GLOBALS['gManagementTemplateId'] = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT", "client_id = " . $GLOBALS['gDefaultClientId']);
$useApcuDevelopment = $GLOBALS['gAllPreferences']['USE_APCU_DEVELOPMENT']['system_value'];
if ($GLOBALS['gDevelopmentServer'] && $useApcuDevelopment != "true") {
	$GLOBALS['gApcuEnabled'] = false;
}
$GLOBALS['gTableKeys'] = getCachedData("table_keys", $GLOBALS['gPrimaryDatabase']->getName(), true);
if (!$GLOBALS['gTableKeys']) {
	$resultSet = executeReadQuery("select table_name,column_name from table_columns join tables using (table_id) join column_definitions using (column_definition_id) where primary_table_key = 1");
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gTableKeys'][$row['table_name']] = $row['column_name'];
	}
	setCachedData("table_keys", $GLOBALS['gPrimaryDatabase']->getName(), $GLOBALS['gTableKeys'], 24, true);
}
if (file_exists($GLOBALS['gDocumentRoot'] . "/shared/setglobals.inc")) {
	include_once "setglobals.inc";
}
date_default_timezone_set((empty($GLOBALS['timezone']) ? 'America/Denver' : $GLOBALS['timezone']));
$GLOBALS['gYearArray'] = array();
for ($x = 0; $x < 10; $x++) {
	$GLOBALS['gYearArray'][date("Y") + $x] = date("Y") + $x;
}
$GLOBALS['gExtendedYearArray'] = array();
for ($x = 0; $x < 20; $x++) {
	$GLOBALS['gExtendedYearArray'][date("Y") - 10 + $x] = date("Y") - 10 + $x;
}

function shutdown() {
	if (empty($GLOBALS['gPrimaryDatabase'])) {
		return;
	}
	if (!empty($GLOBALS['gCreateNewBlacklist'])) {
		createBlackList();
	}
	$GLOBALS['gShuttingDown'] = true;
    $error = error_get_last();
    if($error !== NULL && $error['type'] == 1) {
        $GLOBALS['gPrimaryDatabase']->logError("Fatal error:  {$error['message']}");
    }
	$GLOBALS['gLogQueryCounts'] = ($_SERVER['HTTP_HOST'] == "coreware.local" ? true : $GLOBALS['gAllPreferences']['LOG_QUERY_COUNTS']['system_value']);
	if ($GLOBALS['gLogQueryCounts'] && !$GLOBALS['gCommandLine']) {
		foreach ($GLOBALS['gQueryCounts'] as $thisCount) {
			if ($thisCount['count'] > 1) {
				$GLOBALS['gLogQueryCounts'] = false;
				$resultSet = executeQuery("update query_log set statement_count = statement_count + ? where query_text = ? and query_log_code = 'Query Count'", $thisCount['count'], $thisCount['query_text']);
				if ($resultSet['affected_rows'] == 0) {
					executeQuery("insert into query_log (query_log_code,query_text,statement_count,elapsed_time) values ('Query Count',?,?,0)", $thisCount['query_text'], $thisCount['count']);
				}
			}
		}
        foreach ($GLOBALS['gPrimaryDatabase']->getIgnoredErrors as $errorText => $errorCount) {
            $resultSet = executeQuery("update query_log set statement_count = statement_count + ? where query_text = ? and query_log_code = 'Error Count'", $errorCount, $errorText);
            if ($resultSet['affected_rows'] == 0) {
                executeQuery("insert into query_log (query_log_code,query_text,statement_count,elapsed_time) values ('Error Count',?,?,0)", $errorText, $errorCount);
            }
        }
	}
	$memoryUsage = memory_get_usage();
	$GLOBALS['gOverallEndTime'] = getMilliseconds();
	$totalRunTime = round(($GLOBALS['gOverallEndTime'] - $GLOBALS['gOverallStartTime']) / 1000, 2);

	$queryText = ($GLOBALS['gCommandLine'] ? $_SERVER['SCRIPT_FILENAME'] : $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . ", " . $GLOBALS['gReadOnlyAccessCount'] . " read only accesses" . ($GLOBALS['gPageCacheUsed'] ? ", page cache used" : "");
	foreach ($GLOBALS['gPrimaryDatabase']->getQueryStatements() as $statement) {
		$statement->close();
	}
	if ($GLOBALS['gReadReplicaDatabase'] !== false) {
		foreach ($GLOBALS['gReadReplicaDatabase']->getQueryStatements() as $statement) {
			$statement->close();
		}
	}
	$GLOBALS['gPrimaryDatabase']->closeConnection();
	unset ($GLOBALS['gPrimaryDatabase']);
	if ($GLOBALS['gReadReplicaDatabase']) {
		$GLOBALS['gReadReplicaDatabase']->closeConnection();
		unset ($GLOBALS['gReadReplicaDatabase']);
	}
}

register_shutdown_function('shutdown');

# create custom handler that will log any errors to the database

function customErrorHandler($errno, $errstr) {
	if (!$GLOBALS['gIgnoreError']) {
		$GLOBALS['gPrimaryDatabase']->logError("Error Number: " . $errno . "\n  Error Text:" . $errstr);
	}
	return !$GLOBALS['gDevelopmentServer'];
}

$GLOBALS['gEnglishLanguageId'] = getCachedData("language_iso_code", "ENG");
if (empty($GLOBALS['gEnglishLanguageId'])) {
	$GLOBALS['gEnglishLanguageId'] = getFieldFromId("language_id", "languages", "iso_code", "ENG");
	setCachedData("language_iso_code", "ENG", $GLOBALS['gEnglishLanguageId'], 168);
}
$GLOBALS['gSystemName'] = getPreference("system_name");
if (empty($GLOBALS['gSystemName'])) {
	$GLOBALS['gSystemName'] = "COREWARE_SYSTEM";
}

if (!$GLOBALS['gLocalExecution'] && !$GLOBALS['gDevelopmentServer'] && gethostname() != "tools.kimdavidsoftware.com") {
	$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "LAST_CHECK_IN");
	if (empty($preferenceId)) {
		$resultSet = executeQuery("insert into preferences (preference_code,description,data_type) values ('LAST_CHECK_IN','Last Server Check','varchar')");
	}
	$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "LAST_CHECK_IN_RESPONSE");
	if (empty($preferenceId)) {
		$resultSet = executeQuery("insert into preferences (preference_code,description,data_type) values ('LAST_CHECK_IN_RESPONSE','Last Server Check Response','varchar')");
	}
	$lastCheckIn = $GLOBALS['gAllPreferences']['LAST_CHECK_IN']['system_value'];
	if ($lastCheckIn != date("Y-m-d")) {
		executeQuery("update preferences set system_value = ? where preference_code = ?", date("Y-m-d"), "LAST_CHECK_IN");
		$curlHandle = curl_init("https://www.officemadeeasy.com/api.php?action=server_check_in");
		$postFields = "device_identifier=" . $_SERVER['SERVER_ADDR'] . "&api_app_code=server&api_app_version=1&system_name=" . rawurlencode($GLOBALS['gSystemName'] . " - " . $_SERVER['HTTP_HOST']);
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($curlHandle, CURLOPT_HEADER, 0);
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
		$serverResponse = curl_exec($curlHandle);
		if (empty($serverResponse)) {
			$serverResponse = "No response from server: " . curl_error($curlHandle);
		} else {
			$serverResponseArray = json_decode($serverResponse, true);
			if (!empty($serverResponseArray['inactive'])) {
				$GLOBALS['gRedirectLogEntry'] .= "Server not authorized: " . $_SERVER['REQUEST_URI'];
				header('HTTP/1.1 503 Service Temporarily Unavailable');
				header('Status: 503 Service Temporarily Unavailable');
				header('Retry-After: 120');
				exit;
			}
		}
		executeQuery("update preferences set system_value = ? where preference_code = ?", $serverResponse, "LAST_CHECK_IN_RESPONSE");
		curl_close($curlHandle);
	}
}

# check for redirect of domain name

if (strpos($requestPage, "?") !== false) {
	$requestPage = substr($requestPage, 0, strpos($requestPage, "?"));
}
$GLOBALS['gDomainNameRow'] = getCachedData("domain_name_row", $domainName, true);
if (empty($GLOBALS['gDomainNameRow'])) {
	$GLOBALS['gDomainNameRow'] = array();
	$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and inactive = 0", $domainName);
	if (!$GLOBALS['gDomainNameRow'] = getNextRow($resultSet)) {
		$GLOBALS['gDomainNameRow'] = array();
		if (substr($domainName, 0, 4) == "www.") {
			$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and include_www = 1 and inactive = 0", substr($domainName, 4));
			if (!$GLOBALS['gDomainNameRow'] = getNextRow($resultSet)) {
				$GLOBALS['gDomainNameRow'] = array();
			}
		}
	}
	if (!empty($GLOBALS['gDomainNameRow'])) {
		setCachedData("domain_name_row", $domainName, $GLOBALS['gDomainNameRow'], 1, true);
	}
}
if (empty($GLOBALS['gDomainNameRow']) && !$GLOBALS['gCommandLine'] && !$GLOBALS['gDevelopmentServer']) {
	$domainNameParts = explode(".", $domainName);
	if (count($domainNameParts) >= 2) {
		$topLevelDomainName = array_pop($domainNameParts);
		$mainNameDomainName = array_pop($domainNameParts);
		$checkDomainName = "*." . $mainNameDomainName . "." . $topLevelDomainName;
	}
	$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and inactive = 0", $checkDomainName);
	if (!$GLOBALS['gDomainNameRow'] = getNextRow($resultSet)) {
		$GLOBALS['gDomainNameRow'] = array();
	}
	if (!empty($GLOBALS['gDomainNameRow'])) {
		setCachedData("domain_name_row", $domainName, $GLOBALS['gDomainNameRow'], 1, true);
	}
}
if (empty($GLOBALS['gDomainNameRow']) && !$GLOBALS['gCommandLine'] && !$GLOBALS['gDevelopmentServer']) {
	$resultSet = executeReadQuery("select * from domain_names where domain_name = '*' and inactive = 0");
	if (!$GLOBALS['gDomainNameRow'] = getNextRow($resultSet)) {
		$GLOBALS['gDomainNameRow'] = array();
	}
	if (!empty($GLOBALS['gDomainNameRow'])) {
		setCachedData("domain_name_row", $domainName, $GLOBALS['gDomainNameRow'], 1, true);
	}
}
if (!$GLOBALS['gCommandLine'] && !empty($GLOBALS['gDomainNameRow']['forward_domain_name'])) {
	$GLOBALS['gRedirectLogEntry'] .= $_SERVER['HTTP_HOST'] . " redirected to different domain name: " . $GLOBALS['gDomainNameRow']['forward_domain_name'];
	if (!empty($GLOBALS['gDomainNameRow']['permanent_redirect'])) {
		header("HTTP/1.1 301 Moved Permanently");
	}
	setCachedData("redirect_domain_name", $_SERVER['HTTP_HOST'], $GLOBALS['gDomainNameRow']['forward_domain_name'], 24, true);
	header("Location: " . (substr($GLOBALS['gDomainNameRow']['forward_domain_name'], 0, 4) == "http" ? "" : "https://") . $GLOBALS['gDomainNameRow']['forward_domain_name'] . $_SERVER['REQUEST_URI']);
	exit;
}
if (!$GLOBALS['gCommandLine'] && !empty($GLOBALS['gDomainNameRow']['link_url'])) {
	$GLOBALS['gRedirectLogEntry'] .= "Redirected to URL: " . $GLOBALS['gDomainNameRow']['link_url'];
	if (!empty($GLOBALS['gDomainNameRow']['permanent_redirect'])) {
		header("HTTP/1.1 301 Moved Permanently");
	}
	header("Location: " . $GLOBALS['gDomainNameRow']['link_url']);
	exit;
}

if (!empty($GLOBALS['gBaseDomainName'])) {
	executeQuery("insert ignore into random_data_chunks (random_data_chunk_code,text_data) values (?,?)", "DOMAIN_NAME_" . strtoupper($GLOBALS['gBaseDomainName']), $GLOBALS['gBaseDomainName']);
}

# Get information about this page. If the page ID is null, only a superuser can run it.

$row = getCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_code", $GLOBALS['gPageCode'], true);
if (empty($row)) {
	$resultSet = executeReadQuery("select *,(select text_data from page_data where page_id = pages.page_id and template_data_id = (select template_data_id from template_data where data_name = 'primary_table_name')) primary_table_name from pages where page_code = ?" . ($GLOBALS['gPageCode'] == "LOGIN" ? "" : " and inactive = 0") .
		" and (publish_start_date is null or (publish_start_date is not null and current_date >= publish_start_date)) and " .
		"(publish_end_date is null or (publish_end_date is not null and current_date <= publish_end_date))", $GLOBALS['gPageCode']);
	if ($row = getNextRow($resultSet)) {
		if ($GLOBALS['gInternalConnection']) {
			$row['internal_use_only'] = 0;
		}
		$pageTextSet = executeQuery("select * from page_text_chunks where page_id = ?", $row['page_id']);
		$row['page_text_chunks'] = array();
		while ($pageTextRow = getNextRow($pageTextSet)) {
			$row['page_text_chunks'][$pageTextRow['page_text_chunk_code']] = $pageTextRow['content'];
			foreach ($row as $fieldName => $fieldData) {
				$row[$fieldName] = str_replace("%" . strtolower($pageTextRow['page_text_chunk_code']) . "%", $pageTextRow['content'], $fieldData);
			}
		}
		$pageControlsSet = executeQuery("select * from page_controls where page_id = ?", $row['page_id']);
		$row['page_controls'] = array();
		while ($pageControlsRow = getNextRow($pageControlsSet)) {
			$row['page_controls'][] = $pageControlsRow;
		}
		setCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_code", $GLOBALS['gPageCode'], $row, 1, true);
	} else {
		$row = false;
	}
}
if (!empty($row)) {
	$GLOBALS['gPageId'] = $row['page_id'];
	$GLOBALS['gPageRow'] = $row;
	if (!empty($row['proxy_page_code'])) {
		$GLOBALS['gPageCode'] = $row['proxy_page_code'];
	}
} else {
	$GLOBALS['gPageCode'] = "";
}
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

if (!empty($GLOBALS['gPageRow']['link_url'])) {
	header("HTTP/1.1 301 Moved Permanently");
	header("Location: " . $GLOBALS['gPageRow']['link_url']);
	exit;
}

# Log the ip address if the table exists for it.

if (array_key_exists("REMOTE_ADDR", $_SERVER) && !empty($_SERVER['REMOTE_ADDR'])) {
	if ($GLOBALS['gPrimaryDatabase']->tableExists("ip_address_accesses")) {
		$ipAddress = $_SERVER['REMOTE_ADDR'];
		$resultSet = executeQuery("update ip_address_accesses set access_count = access_count + 1 where ip_address = ?", $ipAddress);
		if ($resultSet['affected_rows'] == 0) {
			$GLOBALS['gPrimaryDatabase']->ignoreError(true);
			$resultSet = executeQuery("insert into ip_address_accesses (ip_address_access_id,ip_address,access_count) values " .
				"(null,?,1)", $ipAddress);
			if (!empty($resultSet['sql_error'])) {
				executeQuery("update ip_address_accesses set access_count = access_count + 1 where ip_address = ?", $ipAddress);
			}
			$GLOBALS['gPrimaryDatabase']->ignoreError(false);
		}
	}
}

# Look for hacking attempts and check ip address. If the url indicates a clear hacking attempt, immediately log the IP address and
# go to the rejectedip.html page. If there is not a hacking attempt, check to see if the ip address is already in the rejected ip address
# table. The entries in the table can be in the form of xxx.xxx.xxx.xxx/xx, so check all the possibilities.

$GLOBALS['gWhiteListed'] = false;

$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
if (strpos($userAgent, "googlebot") !== false || strpos($userAgent, "bingbot") !== false || strpos($userAgent, "ELB-HealthChecker/2.0") != false) {
	$GLOBALS['gWhiteListed'] = true;
}
if (strpos($_SERVER['REQUEST_URI'], "wikiarmsfeed") !== false || strpos($_SERVER['REQUEST_URI'], "gundealsfeed") !== false || strpos($_SERVER['REQUEST_URI'], "ammoseekfeed") !== false) {
	$GLOBALS['gWhiteListed'] = true;
}

if (!empty($_GET['whitelist_user_id']) && !empty($_GET['whitelist_user_name'])) {
	$whiteListUserId = getFieldFromId("user_id", "users", "user_id", $_GET['whitelist_user_id'], "client_id is not null and superuser_flag = 1 and user_name = ?", $_GET['whitelist_user_name']);
	if (!empty($whiteListUserId)) {
		executeQuery("insert ignore into ip_address_whitelist (description,ip_address) values (?,?)", "Whitelisted by URL for user " . $_GET['whitelist_user_name'], $_SERVER['REMOTE_ADDR']);
		executeQuery("delete from ip_address_blacklist where ip_address = ?", $_SERVER['REMOTE_ADDR']);
		$GLOBALS['gWhiteListed'] = true;
		$_SESSION['valid_ip_address'] = "white";
		createBlackList();
	}
}

if (!$GLOBALS['gWhiteListed'] && $_SESSION['valid_ip_address'] == "white") {
	$GLOBALS['gWhiteListed'] = true;
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

$resultSet = executeReadQuery("select * from ip_address_countries where ip_address in (" . $queryPart . ")", $queryParameters);
if (!$row = getNextRow($resultSet)) {
	$ipAddress = $_SERVER['REMOTE_ADDR'];
	$ipAddressData = getIpAddressMetrics($ipAddress);
	if (!empty($ipAddressData['country_id'])) {
		executeQuery("insert ignore into ip_address_countries (ip_address,country_id) values (?,?)", $ipAddress, $ipAddressData['country_id']);
	}
}

if (!$GLOBALS['gLocalExecution'] && !isWebCrawler() && empty($validIpAddress) && !$GLOBALS['gWhiteListed'] && !$GLOBALS['gCommandLine'] && !empty($_SERVER['REMOTE_ADDR'])) {
	# Look for white-listed IP address
	$resultSet = executeReadQuery("select count(*) from ip_address_whitelist where ip_address = ?", $_SERVER['REMOTE_ADDR']);
	if ($row = getNextRow($resultSet)) {
		if ($row['count(*)'] == 0) {
			$resultSet = executeReadQuery("select count(*) from ip_address_countries where country_id in (select country_id from country_data where integer_data = 1 and country_data_type_id = (select country_data_type_id from country_data_types where country_data_type_code = 'NO_ACCESS')) and ip_address in ($queryPart)", $queryParameters);
			if ($row = getNextRow($resultSet)) {
				if ($row['count(*)'] > 0) {
					$ipAddress = $_SERVER['REMOTE_ADDR'];
					$ipAddressData = getIpAddressMetrics($ipAddress);
					if (empty($ipAddressData['country_id']) || !empty(getFieldFromId("country_id", "country_data", "country_id", $ipAddressData['country_id'],
							"integer_data = 1 and country_data_type_id = (select country_data_type_id from country_data_types where country_data_type_code = 'NO_ACCESS')"))) {
						$countryName = getFieldFromId("country_name", "countries", "country_id", $ipAddressData['country_id']) ?: "Unknown";
						header('HTTP/1.1 503 Service Temporarily Unavailable');
						header('Status: 503 Service Temporarily Unavailable');
						header('Retry-After: 120');
						exit;
					} else {
						executeQuery("insert ignore into ip_address_countries (ip_address,country_id) values (?,?)", $ipAddress, $ipAddressData['country_id']);
						executeQuery("delete from ip_address_countries where country_id in (select country_id from country_data where integer_data = 1 and country_data_type_id = (select country_data_type_id from country_data_types where country_data_type_code = 'NO_ACCESS')) and ip_address in ($queryPart)", $queryParameters);
					}
				}
			}
		} else {
			$GLOBALS['gWhiteListed'] = true;
			$_SESSION['valid_ip_address'] = "white";
		}
	}
}

if (!(stripos($_SERVER['REQUEST_URI'], "wpad") === false)) {
	$GLOBALS['gRedirectLogEntry'] .= "Redirected because of wpad: " . $_SERVER['REQUEST_URI'];
	header("Location: /");
	exit;
}

$httpReferer = str_replace("www.", "", parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST));
if (!$GLOBALS['gLocalExecution'] && !isWebCrawler() && !empty($httpReferer)) {
	$resultSet = executeReadQuery("select * from blocked_referers where domain_name = ? or domain_name like ?", $httpReferer, "%." . $httpReferer);
	if ($row = getNextRow($resultSet)) {
		$GLOBALS['gRedirectLogEntry'] .= "Unavailable because of referrer: " . $_SERVER['HTTP_REFERER'] . " : " . $_SERVER['REQUEST_URI'];
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: 120');
		exit;
	}
}

# Check to see if the user is already logged in

$userAgentSessionValidator = getPreference("USER_AGENT_SESSION_VALIDATOR");
$noSessionValidator = getPreference("NO_SESSION_VALIDATOR");
if (!empty($noSessionValidator)) {
	$administratorFlag = getFieldFromId("administrator_flag", "users", "user_id", $_SESSION[$GLOBALS['gSystemName']]['user_id']);
	if (!empty($administratorFlag)) {
		$noSessionValidator = false;
	}
}
if (!empty($noSessionValidator)) {
	$sessionValidator = $currentValidator = $_SERVER['REMOTE_ADDR'];
} else if (!empty($userAgentSessionValidator)) {
	$sessionValidator = $_SESSION[$GLOBALS['gSystemName']]['http_user_agent'];
	$currentValidator = $_SERVER['HTTP_USER_AGENT'];
} else {
	$sessionValidator = $_SESSION[$GLOBALS['gSystemName']]['login_ip_address'];
	$currentValidator = $_SERVER['REMOTE_ADDR'];
}

if (array_key_exists($GLOBALS['gSystemName'], $_SESSION) && array_key_exists("user_id", $_SESSION[$GLOBALS['gSystemName']]) &&
	array_key_exists("last_hit", $_SESSION[$GLOBALS['gSystemName']]) && array_key_exists("login_ip_address", $_SESSION[$GLOBALS['gSystemName']]) &&
	$currentValidator == $sessionValidator) {
	$resultSet = executeReadQuery("select *,(select group_concat(user_group_code) from user_groups join user_group_members using (user_group_id) where user_group_members.user_id = users.user_id) as user_group_codes," .
		"(select group_concat(description) from user_groups join user_group_members using (user_group_id) where user_group_members.user_id = users.user_id) as user_groups," .
		"(select group_concat(user_group_id) from user_group_members where user_id = users.user_id) as user_group_ids," .
		"(select group_concat(category_code) from categories join contact_categories using (category_id) where contact_categories.contact_id = contacts.contact_id) as category_codes," .
		"(select group_concat(description) from categories join contact_categories using (category_id) where contact_categories.contact_id = contacts.contact_id) as categories," .
		"(select group_concat(concat_ws('|',subsystem_id,permission_level)) from user_subsystem_access where user_id = users.user_id order by permission_level desc) as user_subsystem_access, " .
		"(select group_concat(concat_ws('|',page_id,permission_level)) from user_access where user_id = users.user_id order by permission_level desc) as user_access, " .
		"(select group_concat(concat_ws('||',phone_number,description) separator '||||') from phone_numbers where contact_id = contacts.contact_id) as phone_numbers from " .
		"contacts join users using (contact_id) where user_id = ? and inactive = 0", $_SESSION[$GLOBALS['gSystemName']]['user_id']);
	while ($GLOBALS['gUserRow'] = getNextRow($resultSet)) {
		$GLOBALS['gUserRow']['user_group_ids'] = array_filter(explode(",", $GLOBALS['gUserRow']['user_group_ids']));

		$phoneNumbers = array();
		foreach (explode("||||", $GLOBALS['gUserRow']['phone_numbers']) as $thisPhoneNumber) {
			$parts = explode("||", $thisPhoneNumber);
			$phoneNumber = $parts[0];
			$description = $parts[1];
			if (!empty($phoneNumber)) {
				$phoneNumbers[] = array("phone_number" => $phoneNumber, "description" => $description);
			}
		}
		$GLOBALS['gUserRow']['phone_numbers'] = $phoneNumbers;

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

		if (!empty($GLOBALS['gUserRow']['administrator_flag'])) {
			if ($_SESSION[$GLOBALS['gSystemName']]['login_ip_address'] != $_SERVER['REMOTE_ADDR']) {
				break;
			}
		}
		if (empty($GLOBALS['gUserRow']['hash_code'])) {
			$hashCode = md5(uniqid(mt_rand(), true) . $GLOBALS['gUserRow']['first_name'] . $GLOBALS['gUserRow']['last_name'] . $GLOBALS['gUserRow']['contact_id'] . $GLOBALS['gUserRow']['email_address'] . $GLOBALS['gUserRow']['date_created']);
			executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $GLOBALS['gUserRow']['contact_id']);
			$GLOBALS['gUserRow']['hash_code'] = $hashCode;
		}

		$GLOBALS['gLoggedIn'] = true;
		$GLOBALS['gUserId'] = $GLOBALS['gUserRow']['user_id'];
		$GLOBALS['gClientId'] = $GLOBALS['gUserRow']['client_id'];
		$GLOBALS['gUserRow']['display_name'] = getUserDisplayName(array("include_company" => false));
		$GLOBALS['gUserRow']['country_name'] = getFieldFromId("country_name", "countries", "country_id", $GLOBALS['gUserRow']['country_id']);
		$GLOBALS['gUserRow']['security_level'] = (empty($GLOBALS['gUserRow']['superuser_flag']) ? getFieldFromId("security_level", "security_levels", "security_level_id", $GLOBALS['gUserRow']['security_level_id']) : PHP_INT_MAX);
		$GLOBALS['gUserRestrictedByIpAddress'] = $_SESSION[$GLOBALS['gSystemName']]['user_restricted_by_ip_address'];

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
		if (empty($GLOBALS['gUserRow']['language_id'])) {
			if (empty($GLOBALS['gDomainNameRow']['language_id'])) {
				$GLOBALS['gUserRow']['language_id'] = $GLOBALS['gEnglishLanguageId'];
			} else {
				$GLOBALS['gUserRow']['language_id'] = $GLOBALS['gDomainNameRow']['language_id'];
			}
		}

		$GLOBALS['gUserRow']['display_name'] = getUserDisplayName();
		if (empty($GLOBALS['gUserRow']['company_id'])) {
			$GLOBALS['gUserRow']['company_id'] = getFieldFromId("company_id", "companies", "contact_id", $GLOBALS['gUserRow']['contact_id']);
		}
		break;
	}
}

# Load Page Codes

$GLOBALS['gAllPageCodes'] = getCachedData("all_page_codes", "");
if (!is_array($GLOBALS['gAllPageCodes']) || empty($GLOBALS['gAllPageCodes'])) {
	$GLOBALS['gAllPageCodes'] = array();
	$resultSet = executeQuery("select page_id, page_code from pages where client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gAllPageCodes'][$row['page_code']] = $row['page_id'];
	}
	setCachedData("all_page_codes", "", $GLOBALS['gAllPageCodes'], 48);
}

if (!$GLOBALS['gWhiteListed'] && strpos($requestPage, "sitemap") !== false) {
	$GLOBALS['gWhiteListed'] = true;
}
$GLOBALS['gForceHttp'] = getPreference("FORCE_HTTP");
if (!empty($GLOBALS['gForceHttp'])) {
	ini_set("session.cookie_secure", 0);
	session_start();
}
setCoreCookie("TEST_COOKIE", 'yes', false);
if (empty($_COOKIE['AWSELBKEY'])) {
	setCoreCookie("AWSELBKEY", getRandomString(40, array("uppercase" => true)), false);
}

if ($GLOBALS['gUserRow']['superuser_flag']) {
	$developmentLogging = getPreference("DEVELOPMENT_LOGGING");
	if ($developmentLogging) {
		$GLOBALS['gLogLiveQueries'] = true;
		$GLOBALS['gLogDatabaseQueries'] = true;
	}
    if(!empty($_GET['display_errors'])) {
        ini_set('display_errors','On');
        if(is_numeric($_GET['display_errors'])) {
            error_reporting($_GET['display_errors']);
        }
    }
}

if ($GLOBALS['gUserRow']['administrator_flag']) {
	$GLOBALS['gInternalConnection'] = true;
}

$GLOBALS['gAutocompleteFields'] = false;
$GLOBALS['gAutocompleteFields'] = getCachedData("autocomplete_tables_array", "autocomplete_tables_array", true);
if (!is_array($GLOBALS['gAutocompleteFields'])) {
	$GLOBALS['gAutocompleteFields'] = $GLOBALS['gDefaultAutocompleteFields'];
	$resultSet = executeReadQuery("select table_id,table_name,(select column_name from column_definitions join table_columns using (column_definition_id) where table_id = tables.table_id and column_type = 'varchar' and code_value = 0 order by sequence_number limit 1) as description_field from tables where " .
		"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'sort_order')) and " .
		"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'internal_use_only')) and " .
		"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'inactive')) and " .
		"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'client_id')) and " .
		"(select count(*) from table_columns where table_id = tables.table_id) <= 12");
	$tableNames = array();
	$descriptionFields = array();
	while ($row = getNextRow($resultSet)) {
		if (empty($row['description_field'])) {
			continue;
		}
		if (!$GLOBALS['gPrimaryDatabase']->tableExists($row['table_name'])) {
			continue;
		}
		$tableNames[] = $row['table_name'];
		$descriptionFields[$row['table_name']] = $row['description_field'];
	}
	if (!empty($tableNames)) {
		$preferenceTableNames = getPreference("autocomplete_table_names");
		if (!empty($preferenceTableNames)) {
			$autocompleteTableNames = explode(",", $preferenceTableNames);
			foreach ($autocompleteTableNames as $thisTableName) {
				if (in_array($thisTableName, $tableNames)) {
					$primaryKey = $GLOBALS['gPrimaryDatabase']->getPrimaryKey($row['TABLE_NAME']);
					if (empty($primaryKey) || array_key_exists($primaryKey, $GLOBALS['gAutocompleteFields'])) {
						continue;
					}
					$GLOBALS['gAutocompleteFields'][$primaryKey] = array("key_field" => $primaryKey, "description_field" => $descriptionFields[$row['TABLE_NAME']]);
				}
			}
		}
		freeReadResult($resultSet);
	}
	setCachedData("autocomplete_tables_array", "autocomplete_tables_array", $GLOBALS['gAutocompleteFields'], 168, true);
}

if (!$GLOBALS['gLoggedIn'] && !empty($_GET['uid']) && !empty($_GET['key'])) {
	$userId = getFieldFromId("user_id", "users", "user_id", $_GET['uid'], "client_id is not null");
	$username = getFieldFromId("user_name", "users", "user_id", $_GET['uid'], "client_id is not null");
	$passwordSalt = getFieldFromId("password_salt", "users", "user_id", $_GET['uid'], "client_id is not null");
	if (!empty($userId)) {
		$correctKey = md5($userId . ":" . $username . ":" . $_SERVER['REMOTE_ADDR'] . ":" . $passwordSalt . ":" . date("m/d/Y"));
		if ($correctKey == $_GET['key']) {
			login($userId);
		}
	}
}
if (empty($GLOBALS['gLanguageCode'])) {
	$GLOBALS['gLanguageId'] = $GLOBALS['gUserRow']['language_id'];
} else {
	$GLOBALS['gLanguageId'] = getFieldFromId("language_id", "languages", "iso_code", $GLOBALS['gLanguageCode']);
}
if (empty($GLOBALS['gLanguageId'])) {
	$GLOBALS['gLanguageId'] = $GLOBALS['gUserRow']['language_id'];
}
if (empty($GLOBALS['gLanguageId'])) {
	if (empty($GLOBALS['gDomainNameRow']['language_id'])) {
		$GLOBALS['gLanguageId'] = $GLOBALS['gEnglishLanguageId'];
	} else {
		$GLOBALS['gLanguageId'] = $GLOBALS['gDomainNameRow']['language_id'];
	}
}
$GLOBALS['gLanguageCode'] = getFieldFromId("iso_code", "languages", "language_id", $GLOBALS['gLanguageId']);

$domainName = $_SERVER['HTTP_HOST'];
if ($GLOBALS['gDomainNameRow']) {
	$GLOBALS['gDomainClientId'] = $GLOBALS['gDomainNameRow']['domain_client_id'];
	if (!empty($GLOBALS['gDomainNameRow']['domain_client_id']) && empty($GLOBALS['gClientId'])) {
		$GLOBALS['gClientId'] = $GLOBALS['gDomainNameRow']['domain_client_id'];
	}
	if (!empty($GLOBALS['gDomainNameRow']['domain_client_id']) && $GLOBALS['gClientId'] != $GLOBALS['gDomainNameRow']['domain_client_id']) {
		$GLOBALS['gClientId'] = $GLOBALS['gDomainNameRow']['domain_client_id'];
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$GLOBALS['gClientId'] = $GLOBALS['gDomainNameRow']['domain_client_id'];
			$GLOBALS['gUserRow']['client_id'] = $GLOBALS['gDomainNameRow']['domain_client_id'];
			$resultSet = executeQuery("update users set client_id = ? where user_id = ?", $GLOBALS['gDomainNameRow']['domain_client_id'], $GLOBALS['gUserId']);
			if ($resultSet['affected_rows'] > 0) {
				executeQuery("delete from selected_rows where user_id = ?", $GLOBALS['gUserId']);
			}
			executeQuery("update contacts set client_id = ? where contact_id = ?", $GLOBALS['gDomainNameRow']['domain_client_id'], $GLOBALS['gUserRow']['contact_id']);
		} else {
			logout();
			$GLOBALS['gRedirectLogEntry'] .= "Redirected because of unauthorized access by user to domain";
			header("Location: /");
			if ($GLOBALS['gCommandLine']) {
				echo "Client Error" . $GLOBALS['gLineEnding'];
			}
			exit;
		}
	}
}

if (empty($GLOBALS['gDomainClientId'])) {
	if (count($GLOBALS['gAllClientRows']) == 1) {
		$GLOBALS['gDomainClientId'] = $GLOBALS['gAllClientRows'][0]['client_id'];
	}
}
if (empty($GLOBALS['gClientId'])) {
	$GLOBALS['gClientId'] = $GLOBALS['gDefaultClientId'];
}

if (!$GLOBALS['gLocalExecution'] && !$GLOBALS['gWhiteListed'] && !$GLOBALS['gDevelopmentServer'] && !$GLOBALS['gUserRow']['superuser_flag']) {
	$hackingTerms = getCachedData("hacking_terms", "", true);
	if (empty($hackingTerms) || !is_array($hackingTerms) || !array_key_exists("search_terms", $hackingTerms) || !array_key_exists("word_press_terms", $hackingTerms) || empty($hackingTerms['search_terms'])) {
		$searchTerms = array();
		$wordPressTerms = array();
		$resultSet = executeQuery("select * from hacking_terms");
		while ($row = getNextRow($resultSet)) {
			$searchTerms[] = $row['search_term'];
			if ($row['word_press']) {
				$wordPressTerms[] = $row['search_term'];
			}
		}
		$hackingTerms = array("search_terms" => $searchTerms, "word_press_terms" => $wordPressTerms);
		setCachedData("hacking_terms", "", $hackingTerms, 24, true);
	} else {
		$searchTerms = $hackingTerms['search_terms'];
		$wordPressTerms = $hackingTerms['word_press_terms'];
	}
	$blackList = false;
	$wordPress = false;
	$blackListNote = "";
	foreach ($searchTerms as $searchTerm) {
		if ($searchTerm == "passwd" && stripos($_SERVER['REQUEST_URI'], "onepasswd") !== false) {
			continue;
		}
		if (stripos($_SERVER['REQUEST_URI'], $searchTerm) !== false) {
			$blackListNote = $searchTerm . " found in REQUEST_URI (" . $_SERVER['REQUEST_URI'] . ")";
			$blackList = true;
			if (in_array($searchTerm, $wordPressTerms)) {
				$wordPress = true;
			}
			break;
		}
	}

	foreach ($searchTerms as $searchTerm) {
		if (stripos($_SERVER['HTTP_USER_AGENT'], $searchTerm) !== false) {
			$blackListNote = $searchTerm . " found in HTTP_USER_AGENT (" . $_SERVER['HTTP_USER_AGENT'] . ")";
			$blackList = true;
			break;
		}
	}

	if ($wordPress) {
		header("Location: /");
		exit;
	} else if ($blackList) {
		blacklistIpAddress($_SERVER['REMOTE_ADDR'], $blackListNote);
		header('HTTP/1.1 503 Service Temporarily Unavailable');
		header('Status: 503 Service Temporarily Unavailable');
		header('Retry-After: 120');
		exit;
	} else {
		$blacklistedIpAddresses = getCachedData("blacklisted_ips", "", true);
		if (!is_array($blacklistedIpAddresses)) {
			$blacklistedIpAddresses = array();
		}
		if (array_key_exists($_SERVER['REMOTE_ADDR'], $blacklistedIpAddresses)) {
			$GLOBALS['gRedirectLogEntry'] .= "Unavailable because of blacklisted IP: " . $_SERVER['REMOTE_ADDR'] . ", " . $_SERVER['REQUEST_URI'];
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 120');
			exit;
		}
	}
}

# Look for ip address hitting the site excessively. Excessive is more than once per second for 1/2 hour or more

$excludeUriTerms = array("api.php", "file_id_only");
if (!$GLOBALS['gLocalExecution'] && !$GLOBALS['gLoggedIn'] && !$GLOBALS['gWhiteListed'] && !isWebCrawler(true) && empty($_GET['ajax']) && !isCdnRequest()) {
	$countAccess = true;
	foreach ($excludeUriTerms as $thisTerm) {
		if (strpos($_SERVER['REQUEST_URI'], $thisTerm) !== false) {
			$countAccess = false;
		}
	}
	if ($countAccess) {
		$ipAddressHitData = getCachedData("ip_address_hits", $_SERVER['REMOTE_ADDR'], true);
		if (empty($ipAddressHitData) || !is_numeric($ipAddressHitData['start_time']) || !is_numeric($ipAddressHitData['hit_count'])) {
			$ipAddressHitData = array("start_time" => date("U"), "hit_count" => 1, "recent_urls" => array($_SERVER['REQUEST_URI']));
			setCachedData("ip_address_hits", $_SERVER['REMOTE_ADDR'], $ipAddressHitData, 24, true);
		} else {
			$ipAddressHitData['hit_count']++;
			if (!is_array($ipAddressHitData['recent_urls'])) {
				$ipAddressHitData['recent_urls'] = array();
			}
			if ($ipAddressHitData['hit_count'] > 100) {
				$ipAddressHitData['recent_urls'][] = $_SERVER['REQUEST_URI'];
				if (count($ipAddressHitData['recent_urls']) > 100) {
					array_shift($ipAddressHitData['recent_urls']);
				}
			}
			$resultSet = executeQuery("select * from site_hit_limits");
			$hitAlertArray = array();
			while ($row = getNextRow($resultSet)) {
				$hitAlertArray[] = $row;
			}
			foreach ($hitAlertArray as $thisHitAlert) {
				$secondsToCheck = $thisHitAlert['elapsed_seconds'];
				$excessiveHitRatePerMinute = $thisHitAlert['hits_per_minute'];
				$startTime = $ipAddressHitData['start_time'];
				$endTime = date("U");
				$elapsedTime = $endTime - $startTime;
				$hitRate = ($elapsedTime == 0 ? 0 : $ipAddressHitData['hit_count'] * 60 / $elapsedTime);
				if ($elapsedTime > $secondsToCheck && $hitRate > $excessiveHitRatePerMinute) {
					if (!$ipAddressHitData['blacklisted']) {
						if (empty($thisHitAlert['log_only'])) {
							$adminLoggedIn = false;
							$resultSet = executeQuery("select user_id from users where client_id = ? and user_name in (select user_name from security_log where security_log_type = 'LOGIN' and ip_address = ? and " .
								"user_name is not null and client_id = ?) and administrator_flag = 1", $GLOBALS['gClientId'], $_SERVER['REMOTE_ADDR'], $GLOBALS['gClientId']);
							if ($resultSet['row_count'] > 0) {
								$adminLoggedIn = true;
							}
							if (!$adminLoggedIn) {
								$elapsedHours = floor($elapsedTime / 3600);
								$elapsedMinutes = floor(($elapsedTime - ($elapsedHours * 3600)) / 60);
								$elapsedSeconds = $elapsedTime - ($elapsedHours * 3600) - ($elapsedMinutes * 60);
								$elapsedDisplayTime = $elapsedHours . ":" . ($elapsedMinutes < 10 ? "0" : "") . $elapsedMinutes . ":" . ($elapsedSeconds < 10 ? "0" : "") . $elapsedSeconds;
								$blackListNote = "Excessive Hit Rate\nDomain Name: " . $_SERVER['HTTP_HOST'] . "\nUser Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\nIP Address: " . $_SERVER['REMOTE_ADDR'] . "\nHit Count: " . $ipAddressHitData['hit_count'] .
									"\nElapsed Time: " . $elapsedDisplayTime . "\nHit Rate: " . $hitRate . "\nMost Recent Hits:\n";
								foreach ($ipAddressHitData['recent_urls'] as $thisUrl) {
									$blackListNote .= $thisUrl . "\n";
								}
								header('HTTP/1.1 503 Service Temporarily Unavailable');
								header('Status: 503 Service Temporarily Unavailable');
								header('Retry-After: 10');
								exit;
							}
						} else {
							$GLOBALS['gPrimaryDatabase']->logError("Excessive Hit Rate from: " . $_SERVER['REMOTE_ADDR'] . ", Data: " . jsonEncode($ipAddressHitData) . ", Elapsed Seconds: " . $elapsedTime . ", Hit Rate:" . $hitRate);
						}
					}
					$ipAddressHitData['blacklisted'] = true;
					break;
				}
			}
			setCachedData("ip_address_hits", $_SERVER['REMOTE_ADDR'], $ipAddressHitData, 24, true);
		}
	}
}

$GLOBALS['gCdnDomainName'] = getPreference("CDN_DOMAIN");
if (!empty($GLOBALS['gCdnDomainName']) && $GLOBALS['gDevelopmentServer']) {
	if (empty(getPreference("USE_CDN_DEVELOPMENT"))) {
		$GLOBALS['gCdnDomainName'] = "";
	}
}
if (!empty($GLOBALS['gCdnDomainName'])) {
	$GLOBALS['gCdnDomainName'] = trim($GLOBALS['gCdnDomainName'], "/");
	if (substr($GLOBALS['gCdnDomainName'], 0, 4) != "http") {
		$GLOBALS['gCdnDomainName'] = "https://" . $GLOBALS['gCdnDomainName'];
	}
}

if ($GLOBALS['gLanguageId'] != $GLOBALS['gEnglishLanguageId']) {
	$GLOBALS['gNoTranslation'] = true;

	$resultSet = executeQuery("select * from column_definitions where translatable = 1");
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gTranslatableColumns']['|' . $row['column_name']] = true;
	}

	$resultSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) table_name, " .
		"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name from table_columns where translatable = 1");
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gTranslatableColumns'][$row['table_name'] . '|' . $row['column_name']] = true;
	}

	$resultSet = executeQuery("select * from text_translations where client_id = ?", $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gTextTranslations'][strtolower($row['english_text'])] = $row['translated_text'];
	}
	$resultSet = executeQuery("select * from language_columns where client_id = ?", $GLOBALS['gClientId']);
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
	$resultSet = executeQuery("select * from language_text where language_id = ? and client_id = ?", $GLOBALS['gLanguageId'], $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		if (array_key_exists($row['language_column_id'], $GLOBALS['gLanguageText'])) {
			$GLOBALS['gLanguageText'][$row['language_column_id']][$row['primary_identifier']] = $row['content'];
		}
	}
	$GLOBALS['gNoTranslation'] = false;

# Reload so that page information is translated
	if (!empty($GLOBALS['gPageId'])) {
		$resultSet = executeQuery("select * from pages where page_id = ?", $GLOBALS['gPageId']);
		if ($row = getNextRow($resultSet)) {
			if ($GLOBALS['gInternalConnection']) {
				$GLOBALS['gPageRow']['internal_use_only'] = 0;
			}
			$pageTextSet = executeQuery("select * from page_text_chunks where page_id = ?", $row['page_id']);
			$GLOBALS['gPageRow']['page_text_chunks'] = array();
			while ($pageTextRow = getNextRow($pageTextSet)) {
				$GLOBALS['gPageRow']['page_text_chunks'][$pageTextRow['page_text_chunk_code']] = $pageTextRow['content'];
				foreach ($GLOBALS['gPageRow'] as $fieldName => $fieldData) {
					$GLOBALS['gPageRow'][$fieldName] = str_replace("%" . strtolower($pageTextRow['page_text_chunk_code']) . "%", $pageTextRow['content'], $fieldData);
				}
			}
			$pageControlsSet = executeQuery("select * from page_controls where page_id = ?", $GLOBALS['gPageRow']['page_id']);
			$GLOBALS['gPageRow']['page_controls'] = array();
			while ($pageControlsRow = getNextRow($pageControlsSet)) {
				$GLOBALS['gPageRow']['page_controls'][] = $pageControlsRow;
			}
			$GLOBALS['gPageRow'] = $row;
		}
	}
}

$GLOBALS['gPageAccessRecords'] = getCachedData("page_access_records", "page_access_records", true);
if (empty($GLOBALS['gPageAccessRecords'])) {
	$GLOBALS['gPageAccessRecords'] = array();
	$resultSet = executeReadQuery("select * from page_access order by page_id,page_access_id");
	while ($row = getNextRow($resultSet)) {
		if (!array_key_exists($row['page_id'], $GLOBALS['gPageAccessRecords'])) {
			$GLOBALS['gPageAccessRecords'][$row['page_id']] = array();
		}
		$GLOBALS['gPageAccessRecords'][$row['page_id']][] = $row;
	}
	setCachedData("page_access_records", "page_access_records", $GLOBALS['gPageAccessRecords'], true);
}

$GLOBALS['gTemplateRow'] = getCachedData("template_row", $GLOBALS['gPageRow']['template_id'], true);
if ($GLOBALS['gTemplateRow'] === false) {
	$GLOBALS['gTemplateRow'] = getRowFromId("templates", "template_id", $GLOBALS['gPageRow']['template_id'], "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
	setCachedData("template_row", $GLOBALS['gPageRow']['template_id'], $GLOBALS['gTemplateRow'], 24, true);
}

header("Service-Worker-Allowed: /");

if (isset($GLOBALS['gXFrameOptions'])) {
	if (!empty($GLOBALS['gXFrameOptions'])) {
		header('X-Frame-Options: ' . $GLOBALS['gXFrameOptions']);
	} else {
		header_remove("X-Frame-Options");
	}
} else if ($GLOBALS['gPageRow']['page_code'] != "URLALIAS" && ($GLOBALS['gTemplateRow']['template_code'] != "EMBED" || empty($GLOBALS['gEmbeddablePage']))) {
	$GLOBALS['gXFrameOptions'] = "SAMEORIGIN";
	header('X-Frame-Options: ' . $GLOBALS['gXFrameOptions']);
} else if ($GLOBALS['gPageRow']['page_code'] != "URLALIAS" && $GLOBALS['gTemplateRow']['template_code'] == "EMBED" && !empty($GLOBALS['gEmbeddablePage'])) {
	$GLOBALS['gEmbeddedPage'] = true;
}

# if the web user is not an admin, log their use of the site
$createWebUserLog = getPreference("create_web_user_log");
if (!empty($_SERVER['REMOTE_ADDR']) && $createWebUserLog) {
	$GLOBALS['gWebUserId'] = getFieldFromId("web_user_id", "web_users", "web_user_id", $_COOKIE["web_user_id"]);
	if (!empty($GLOBALS['gWebUserId']) && !empty($GLOBALS['gUserRow']['contact_id'])) {
		$webUserContactId = getFieldFromId("contact_id", "web_users", "web_user_id", $GLOBALS['gWebUserId']);
		if (!empty($webUserContactId) && $webUserContactId != $GLOBALS['gUserRow']['contact_id']) {
			$GLOBALS['gWebUserId'] = "";
		}
	}
	if (empty($GLOBALS['gWebUserId']) && !empty($GLOBALS['gUserRow']['contact_id'])) {
		$GLOBALS['gWebUserId'] = getFieldFromId("web_user_id", "web_users", "contact_id", $GLOBALS['gUserRow']['contact_id']);
	}
	if (empty($GLOBALS['gWebUserId'])) {
		$resultSet = executeQuery("insert into web_users (ip_address,start_date) values (?,now())", $_SERVER['REMOTE_ADDR']);
		$GLOBALS['gWebUserId'] = $resultSet['insert_id'];
	}
	if (!empty($GLOBALS['gWebUserId'])) {
		setCoreCookie("web_user_id", $GLOBALS['gWebUserId'], (24 * 18250));
	}
	if (!$GLOBALS['gUserRow']['administrator_flag'] && !empty($GLOBALS['gWebUserId']) && empty($GLOBALS['gPageRow']['ignore_web_user_log']) && !empty($GLOBALS['gPageId'])) {
		executeQuery("insert into web_user_pages (web_user_id,page_id,referer,ip_address) values (?,?,?,?)", $GLOBALS['gWebUserId'], $GLOBALS['gPageId'], $_SERVER['HTTP_REFERER'], $_SERVER['REMOTE_ADDR']);
	}
	if (!empty($GLOBALS['gWebUserId']) && !empty($GLOBALS['gUserRow']['contact_id'])) {
		executeQuery("update web_users set contact_id = ? where web_user_id = ?", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gWebUserId']);
	}
}

# Get the user's timeout in minutes. If they get logged out because of time and the request is ajax, notify.

$timeOut = getPreference('TIMEOUT');
if (empty($timeOut)) {
	$timeOut = 30;
}
if (getPreference("PCI_COMPLIANCE") && $GLOBALS['gUserRow']['administrator_flag']) {
	$timeOut = 15;
}
if ($GLOBALS['gDevelopmentServer']) {
	$timeOut = 10000;
}
if (!empty($GLOBALS['gOverrideTimeout'])) {
	$timeOut = $GLOBALS['gOverrideTimeout'];
}
$timedOut = false;
$secondsLeft = ($GLOBALS['gLoggedIn'] ? max(0, ($_SESSION[$GLOBALS['gSystemName']]['last_hit'] + ($timeOut * 60)) - time()) : 0);
if ($GLOBALS['gLoggedIn'] && array_key_exists("last_hit", $_SESSION[$GLOBALS['gSystemName']]) && $secondsLeft == 0) {
	addSecurityLog($GLOBALS['gUserRow']['user_name'], "USER-TIMEOUT", "User timed out: " . jsonEncode($_SESSION));
	$timedOut = true;
	logout();
	if ($_GET['ajax'] == "true") {
		$returnArray = array("error_message" => getSystemMessage("logged_out"));
		ajaxResponse($returnArray);
	}
}

# Reset the time of last activity
if ($GLOBALS['gUserRow']['administrator_flag'] && $_GET['url_action'] == "get_user_notification_count" && !empty($_GET['ajax'])) {
	$userNotificationCount = 0;
	$resultSet = executeQuery("select count(*) from user_notifications where user_id = ? and time_deleted is null and time_read is null", $GLOBALS['gUserId']);
	if ($row = getNextRow($resultSet)) {
		$userNotificationCount = $row['count(*)'];
	}
	if ($userNotificationCount > 0) {
		$userNotificationClass = "notifications-exist";
	} else {
		$userNotificationClass = "";
	}
	$returnArray['user_notification_count'] = $userNotificationCount;
	$returnArray['user_notification_class'] = $userNotificationClass;
	ajaxResponse($returnArray);
}

if ($GLOBALS['gLoggedIn']) {
	$_SESSION[$GLOBALS['gSystemName']]['last_hit'] = time();
	$_SESSION['timeout'] = time() + ($timeOut * 60);
}

$loginLinkName = $GLOBALS['gPageRow']['login_script'];
if (empty($loginLinkName)) {
	$loginLinkName = getPreference("LOGIN_LINK_NAME");
	if (empty($loginLinkName)) {
		$loginLinkName = "loginform.php";
	}
}

# Get Client row

# Check the domain name

if (!$GLOBALS['gLocalExecution'] && !isWebCrawler() && !$GLOBALS['gWhiteListed']) {
	$usCanadaOnly = getPreference("US_CANADA_ONLY");
	$allowWorld = getPreference("ALLOW_WORLD_ACCESS");
	if (empty($usCanadaOnly) && empty($allowWorld)) {
		$usCanadaOnly = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
	}
	if (!empty($usCanadaOnly)) {
		$resultSet = executeReadQuery("select count(*) from ip_address_countries where country_id not in (1000,1001) and ip_address in ($queryPart)", $queryParameters);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] > 0) {
				$ipAddress = $_SERVER['REMOTE_ADDR'];
				$ipAddressData = getIpAddressMetrics($ipAddress);
				if (!in_array($ipAddressData['country_id'], array("1000", "1001"))) {
					header('HTTP/1.1 503 Service Temporarily Unavailable');
					header('Status: 503 Service Temporarily Unavailable');
					header('Retry-After: 120');
					exit;
				} else {
					executeQuery("insert ignore into ip_address_countries (ip_address,country_id) values (?,?)", $ipAddress, $ipAddressData['country_id']);
					executeQuery("delete from ip_address_countries where country_id not in (1000,1001) and ip_address in ($queryPart)", $queryParameters);
				}
			}
		}
	}
	array_unshift($queryParameters, $GLOBALS['gClientId']);
	$resultSet = executeQuery("select count(*) from ip_address_countries where country_id in (select country_id from blocked_countries where client_id = ?) and ip_address in ($queryPart)", $queryParameters);
	if ($row = getNextRow($resultSet)) {
		if ($row['count(*)'] > 0) {
			$ipAddress = $_SERVER['REMOTE_ADDR'];
			$ipAddressData = getIpAddressMetrics($ipAddress);
			if (empty($ipAddressData['country_id']) || !empty(getFieldFromId("country_id", "blocked_countries", "country_id",
					$ipAddressData['country_id'], "client_id = ?", $GLOBALS['gClient_id']))) {
				$countryName = getFieldFromId("country_name", "countries", "country_id", $ipAddressData['country_id']) ?: "Unknown";
				$GLOBALS['gRedirectLogEntry'] .= "Unavailable because of country restrictions (" . $countryName . "): " . $_SERVER['REQUEST_URI'];
				header('HTTP/1.1 503 Service Temporarily Unavailable');
				header('Status: 503 Service Temporarily Unavailable');
				header('Retry-After: 120');
				exit;
			} else {
				executeQuery("insert ignore into ip_address_countries (ip_address,country_id) values (?,?)", $ipAddress, $ipAddressData['country_id']);
				executeQuery("delete from ip_address_countries where country_id in (select country_id from blocked_countries where client_id = ?) and ip_address in ($queryPart)", $queryParameters);
			}
		}
	}
}
if (!file_exists($GLOBALS['gDocumentRoot'] . "/cache/" . gethostname() . "_blacklist.inc")) {
	createBlackList();
}

$clientTimezone = getPreference("TIMEZONE");
if (!empty($clientTimezone)) {
	date_default_timezone_set($clientTimezone);
	executeQuery("set time_zone = ?", $clientTimezone);
}

$internalIpAddresses = explode(",", str_replace("\r", ",", str_replace("\n", ",", getPreference("INTERNAL_IP_ADDRESSES"))));
foreach ($ipAddressPossibilities as $checkIpAddress) {
	if (in_array($checkIpAddress, $internalIpAddresses)) {
		$GLOBALS['gInternalDevice'] = true;
		$GLOBALS['gInternalConnection'] = true;
		$GLOBALS['gPageRow']['internal_use_only'] = 0;
		break;
	}
}

$GLOBALS['gClientRow'] = getCachedData("client_row", $GLOBALS['gClientId'], true);
$GLOBALS['gClientName'] = getCachedData("client_name", $GLOBALS['gClientId'], true);
if (empty($GLOBALS['gClientRow']) || empty($GLOBALS['gClientName'])) {
	$GLOBALS['gClientRow'] = $GLOBALS['gAllClientRows'][$GLOBALS['gClientId']];
	$GLOBALS['gClientName'] = getDisplayName($GLOBALS['gClientRow']['contact_id'], array("use_company" => true));
	$GLOBALS['gClientRow']['phone_numbers'] = array();
	$resultSet = executeQuery("select * from phone_numbers where contact_id = ? order by phone_number_id", $GLOBALS['gClientRow']['contact_id']);
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gClientRow']['phone_numbers'][] = $row;
	}
	setCachedData("client_row", $GLOBALS['gClientId'], $GLOBALS['gClientRow'], 2, true);
	setCachedData("client_name", $GLOBALS['gClientId'], $GLOBALS['gClientName'], 2, true);
}
if (!file_exists($GLOBALS['gDocumentRoot'] . "/cache/" . gethostname() . "_blacklist.inc")) {
	createBlackList();
}

# make client active for client load process

if (!empty($_GET['connection_key']) && $_GET['source'] == "client_load" && in_array($GLOBALS['gPageCode'], array("DOWNLOAD", "GETIMAGE"))) {
	$GLOBALS['gClientRow']['inactive'] = false;
}

if (!empty($GLOBALS['gClientRow']['inactive']) && !$GLOBALS['gDevelopmentServer'] && !$GLOBALS['gUserRow']['superuser_flag']) {
	if (substr($requestPage, 0, strlen("/loginform.php")) != "/loginform.php") {
		header("Location: /client_inactive.html");
		exit;
	}
}

if (!$GLOBALS['gDevelopmentServer'] && !$GLOBALS['gUserRow']['administrator_flag'] && empty($_GET['ajax']) && !empty($GLOBALS['gDomainNameRow']['internal_use_only']) && strpos($_SERVER['REQUEST_URI'], "getimage.php") === false) {
	systemDown();
}

if (!$GLOBALS['gDevelopmentServer'] && !$GLOBALS['gUserRow']['superuser_flag'] && empty($_GET['ajax']) && stripos($_SERVER['REQUEST_URI'], "getimage") === false) {
	if (!empty(getPreference("SYSTEM_DOWN"))) {
		systemDown();
	}
}

if (!empty($GLOBALS['gClientRow']['development'])) {
	$GLOBALS['gDevelopmentServer'] = true;
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
eCommerce::getClientMerchantAccountIds();

# Get Client Templates

$GLOBALS['gClientPageTemplates'] = getCachedData("client_page_templates", "client_page_templates");
if (!is_array($GLOBALS['gClientPageTemplates'])) {
	$resultSet = executeQuery("select * from client_page_templates where client_id = ?", $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gClientPageTemplates'][$row['page_id']] = $row['template_id'];
	}
	setCachedData("client_page_templates", "client_page_templates", $GLOBALS['gClientPageTemplates']);
}

# Check to see if it has been more than 4 hours since the background process has run. If so, email error notification. Time can be set by preference

if (!$GLOBALS['gDevelopmentServer'] && $GLOBALS['gUserRow']['superuser_flag']) {
	$totalCount = 0;
	$resultSet = executeQuery("select count(*) from background_process_log where background_process_id is null");
	if ($row = getNextRow($resultSet)) {
		$totalCount = $row['count(*)'];
	}
	$count = 0;
	$backgroundProcessInterval = getPreference("BACKGROUND_PROCESS_INTERVAL", "", 240);
	$resultSet = executeQuery("select * from background_process_log where background_process_id is null order by background_process_log_id desc limit 1");
	if ($row = getNextRow($resultSet)) {
		if (strtotime($row['run_time']) > (time() - (60 * $backgroundProcessInterval))) {
			$count = 1;
		}
	}
	if ($count == 0 && $totalCount > 0) {
		$systemValue = $GLOBALS['gAllPreferences']['LAST_BACKGROUND_PROCESS_NOTICE']['system_value'];
		$lastDate = (empty($systemValue) ? "" : date("Y-m-d", strtotime($systemValue)));
		if (empty($lastDate) || $lastDate != date("Y-m-d")) {
			$sendToAddresses = getNotificationEmails("ERROR_LOG", $GLOBALS['gDefaultClientId']);
			$emailText = "<html lang=\"en\">\n<body>\n<p>Background process has not run in over " . $backgroundProcessInterval . " minutes on server " . getPreference("SYSTEM_NAME") . ".<\p></body></html>";
			$emailAddresses = array();
			$emailAdded = false;
			foreach ($sendToAddresses as $emailAddress) {
				if (empty($emailAddress)) {
					continue;
				}
				$emailAddresses[] = $emailAddress;
				$emailAdded = true;
			}
			if ($emailAdded) {
				sendEmail(array("subject" => "Background Process", "body" => $emailText, "email_addresses" => $emailAddresses, "send_immediately" => true, "primary_client" => true));
			}
			executeQuery("update preferences set system_value = ? where preference_code = 'LAST_BACKGROUND_PROCESS_NOTICE'", date("Y-m-d"));
		}
	}
}

# Check to see if there are unread system notices. If so, go to the system notices viewer

$ignoreSystemNotices = getPreference("IGNORE_SYSTEM_NOTICES");
if (empty($ignoreSystemNotices) && $_GET['ajax'] != "true" && empty($GLOBALS['gPreemptivePage']) && empty($GLOBALS['gIgnoreNotices']) && $GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] && $GLOBALS['gPageRow']['template_id'] == $GLOBALS['gManagementTemplateId'] && canAccessPageCode("SYSTEMNOTICES")) {
	$systemNotices = new SystemNotices();
	$count = $systemNotices->getCount();
	if ($count > 0) {
		header("Location: /systemnotices.php");
		exit;
	}
}

# Check for agreement to terms of service

if (!$GLOBALS['gDevelopmentServer'] && $_GET['ajax'] != "true" && empty($GLOBALS['gPreemptivePage']) && ($GLOBALS['gUserRow']['full_client_access'] || $GLOBALS['gUserRow']['superuser_flag']) &&
	$GLOBALS['gPageRow']['template_id'] == $GLOBALS['gManagementTemplateId'] && empty($GLOBALS['gUserRow']['agreement_date'])) {
	header("Location: /about-coreware");
	exit;
}

# Load Pages

$redirectPageId = "";
if ($requestPage == "/" || substr($requestPage, 0, strlen("/index.php")) == "/index.php") {
	if (!empty($GLOBALS['gUserRow']['user_type_id'])) {
		$userTypeHomePage = getFieldFromId("link_url", "user_types", "user_type_id", $GLOBALS['gUserRow']['user_type_id']);
		if (!empty($userTypeHomePage)) {
			header("Location: " . $userTypeHomePage);
			exit;
		}
	}

	$domainName = $_SERVER['HTTP_HOST'];
	if ($GLOBALS['gUserRow']['administrator_flag'] && !empty($GLOBALS['gDomainNameRow']['admin_page_id']) && canAccessPage($GLOBALS['gDomainNameRow']['admin_page_id'])) {
		$redirectPageId = $GLOBALS['gDomainNameRow']['admin_page_id'];
	} else if ($GLOBALS['gLoggedIn'] && !empty($GLOBALS['gDomainNameRow']['user_page_id'])) {
		if (canAccessPage($GLOBALS['gDomainNameRow']['user_page_id'])) {
			$redirectPageId = $GLOBALS['gDomainNameRow']['user_page_id'];
		} else {
			logout();
			$GLOBALS['gRedirectLogEntry'] .= "Redirected because of unauthorized access to page: " . $_SERVER['REQUEST_URI'];
			addSecurityLog($GLOBALS['gUserRow']['user_name'], "REDIRECT", $GLOBALS['gRedirectLogEntry']);
			header("Location: /");
			exit;
		}
	} else if (!empty($GLOBALS['gDomainNameRow']['page_id'])) {
		$pageIdArray = array($GLOBALS['gDomainNameRow']['page_id']);
		$resultSet = executeQuery("select * from domain_name_alternate_pages where domain_name_id = ?", $GLOBALS['gDomainNameRow']['domain_name_id']);
		while ($row = getNextRow($resultSet)) {
			$pageIdArray[] = $row['page_id'];
		}
		foreach ($pageIdArray as $index => $pageId) {
			if (!canAccessPage($pageId)) {
				unset($pageIdArray[$index]);
			}
		}
		if (!empty($pageIdArray)) {
			$redirectPageId = $pageIdArray[array_rand($pageIdArray)];
		}
	}
}

# get the template that the Page will use.

$GLOBALS['gPageTemplateId'] = $GLOBALS['gPageRow']['template_id'];
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
			$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%" . strtolower($pageTextChunkCode) . "%", $pageTextChunkContent, $fieldData);
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
if (!empty($GLOBALS['gTemplateRow']['addendum_filename'])) {
	$addendumTemplateFilename = $GLOBALS['gDocumentRoot'] . "/templates/" . (empty($GLOBALS['gTemplateRow']['directory_name']) ? "" : $GLOBALS['gTemplateRow']['directory_name'] . "/") . "class." . strtolower($GLOBALS['gTemplateRow']['addendum_filename']) . ".php";
	include_once $addendumTemplateFilename;
}

$noSiteMap = getPreference("NO_SITEMAP");
if (!empty($noSiteMap)) {
	setCachedData("no_sitemap", $_SERVER['HTTP_HOST'], true, 168, true);
} else {
	removeCachedData("no_sitemap", $_SERVER['HTTP_HOST'], true);
	$cachedProductCount = getCachedData("site_product_count", $_SERVER['HTTP_HOST'], true);
	if ($cachedProductCount === false || strlen($cachedProductCount) == 0) {
		$pageId = getFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php", "inactive = 0");
		$resultSet = executeQuery("select * from domain_name_pages where domain_name_id = ?", $GLOBALS['gDomainNameRow']['domain_name_id']);
		$foundPage = false;
		if ($resultSet['row_count'] == 0) {
			$foundPage = true;
		} else {
			while ($row = getNextRow($resultSet)) {
				if ($row['page_id'] == $pageId) {
					$foundPage = true;
					break;
				}
			}
		}
		$cachedProductCount = 0;
		if ($foundPage) {
			$cachedProductCount = getReadFieldFromId("text_data", "random_data_chunks", "random_data_chunk_code", "product_count-" . $GLOBALS['gClientId']);
			if (empty($cachedProductCount) || !is_numeric($cachedProductCount)) {
				$resultSet = executeQuery("select count(*) from products where client_id = ? and inactive = 0 and internal_use_only = 0 and " .
					"product_manufacturer_id not in (select product_manufacturer_id from product_manufacturers where cannot_sell = 1) and " .
					"product_id not in (select product_id from product_category_links where product_category_id in (select product_category_id from product_categories " .
					"where cannot_sell = 1 or product_category_code in ('INTERNAL_USE_ONLY','DISCONTINUED','INACTIVE')))", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$cachedProductCount = $row['count(*)'];
				}
				executeQuery("delete from random_data_chunks where random_data_chunk_code = ?", "product_count-" . $GLOBALS['gClientId']);
				executeQuery("insert ignore into random_data_chunks (random_data_chunk_code,text_data) values (?,?)", "product_count-" . $GLOBALS['gClientId'], $cachedProductCount);
			}
		}
		setCachedData("site_product_count", $_SERVER['HTTP_HOST'], $cachedProductCount, 168, true);
	}
}

# Check to see if the user is logged out, but accessing a Page that requires login

$permissionLevels = getPermissionLevels($GLOBALS['gPageId']);
if (!$GLOBALS['gLoggedIn'] && !$permissionLevels['public']) {
	if ($_GET['ajax'] == "true") {
		$returnArray = array("error_message" => getSystemMessage("logged_out"));
		ajaxResponse($returnArray);
	}
	if (strpos($_SERVER['REQUEST_URI'], $loginLinkName) === false) {
		if (empty($_SESSION['GO_TO_URI'])) {
			$_SESSION['GO_TO_URI'] = $_SERVER['REQUEST_URI'];
		}
		$GLOBALS['gRedirectLogEntry'] .= "Redirected for login";
		addSecurityLog("", "REDIRECT", $GLOBALS['gRedirectLogEntry']);
		header("Location: https://" . $_SERVER['HTTP_HOST'] .
			(substr($loginLinkName, 0, 1) == "/" ? "" : "/") . $loginLinkName . (strpos($loginLinkName, "?") === false ? "?" : "&") . "url_page=" . ($timedOut ? "re" : "") . "login");
		if ($GLOBALS['gCommandLine']) {
			echo "Login Required" . $GLOBALS['gLineEnding'];
		}
		exit;
	} else {
		echo "<p>Login Page is missing.</p>";
		echo "<p style='display: none'>" . htmlText($GLOBALS['gDatabaseName'] . ":" . $GLOBALS['gDatabaseUsername'] . ":" . $GLOBALS['gDatabaseHostName'] . ":" . $_SERVER['HTTP_HOST'] . ":" .
				$_SERVER['HOSTNAME'] . ":" . gethostname() . ":" . $_SERVER['REQUEST_URI'] . ":" . $loginLinkName . ":" . jsonEncode($permissionLevels) . ":" . $GLOBALS['gPageId'] . ":" .
				jsonEncode($GLOBALS['gPageRow'])) . "</p>";
		exit;
	}
}

if (empty($_GET['ajax']) && getPreference("PCI_COMPLIANCE") && empty($GLOBALS['gPreemptivePage']) && !$GLOBALS['gSetRequiredFields'] && $GLOBALS['gUserRow']['administrator_flag']) {
	$requiredFields = array("first_name", "last_name", "email_address", "security_question_id", "answer_text", "secondary_security_question_id", "secondary_answer_text");
	foreach ($requiredFields as $fieldName) {
		if (empty($GLOBALS['gUserRow'][$fieldName])) {
			header("Location: /userselfmaintenance.php?required=true");
			exit;
		}
	}
}

$skipAuthorize = false;
$neverTwoFactor = getPreference("NEVER_TWO_FACTOR_AUTHENTICATION");
$authorizationNotNeeded = getPreference("AUTHORIZATION_NOT_NEEDED");
if ($GLOBALS['gUserRestrictedByIpAddress'] || $GLOBALS['gEmbeddedPage'] || !empty($authorizationNotNeeded) || ($neverTwoFactor && empty($GLOBALS['gUserRow']['security_question_id']) && empty($GLOBALS['gUserRow']['secondary_security_question_id']))) {
	$skipAuthorize = true;
}
$allUserTwoFactor = false;
if ($GLOBALS['gLoggedIn'] && !$GLOBALS['gUserRow']['administrator_flag']) {
	$allUserTwoFactor = getPreference("ALL_USER_TWO_FACTOR_AUTHENTICATION");
}

if (!$skipAuthorize && !$GLOBALS['gInternalDevice'] && empty($GLOBALS['gPreemptivePage']) && empty($GLOBALS['gAuthorizeComputer']) && $GLOBALS['gLoggedIn'] && ($GLOBALS['gUserRow']['administrator_flag'] || $allUserTwoFactor) && empty($_SESSION['original_user_id']) && empty($_GET['ajax']) && !$GLOBALS['gDevelopmentServer']) {
	$authorizationCode = $_COOKIE["COMPUTER_AUTHORIZATION_" . $GLOBALS['gUserId']];
	if ($authorizationCode != hash("sha256", $GLOBALS['gUserRow']['security_question_id'] . ":" . $GLOBALS['gUserRow']['secondary_security_question_id'] . ":" . $GLOBALS['gUserId'])) {
		if (empty($_SESSION['GO_TO_URI'])) {
			$_SESSION['GO_TO_URI'] = $_SERVER['REQUEST_URI'];
		}
		header("Location: /authorizecomputer.php");
		exit;
	}
}

$_SESSION['speed_tester'] = false;
if (!$GLOBALS['gLoggedIn'] && (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "gtmetrix") !== false || strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "chrome-lighthouse") !== false)) {
	$_SESSION['speed_tester'] = true;
	setCachedData("known_speed_tester", $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_ADDR'], (1 / 60));
} else {
	if (!empty(getCachedData("known_speed_tester", $_SERVER['REMOTE_ADDR']))) {
		$_SESSION['speed_tester'] = true;
	}
}
session_write_close();

$GLOBALS['gLinkUrl'] = "/" . trim((empty($GLOBALS['gPageRow']['link_name']) ? $GLOBALS['gPageRow']['script_filename'] : $GLOBALS['gPageRow']['link_name']), "/");
if (empty($GLOBALS['gLinkUrl'])) {
	$GLOBALS['gLinkUrl'] = "/" . trim($_SERVER['SCRIPT_NAME'], "/");
}

$pageScriptFilename = "";
if ($GLOBALS['gPageRow']['page_code'] == "URLALIAS") {
	$requestUri = trim(explode("?", $_SERVER['REQUEST_URI'])[0], "/");
	if (strpos($requestUri, "/") === false) {
		$pageScriptFilename = getCachedData("page_script_filename", $requestUri);
		if ($pageScriptFilename === false) {
			$pageScriptFilename = "";
			$resultSet = executeReadQuery("select script_filename from pages where link_name = ? order by client_id desc", $requestUri);
			if ($row = getNextRow($resultSet)) {
				$pageScriptFilename = $row['script_filename'];
			}
			setCachedData("page_script_filename", $requestUri, $pageScriptFilename);
		}
	}
} else {
	$pageScriptFilename = $GLOBALS['gPageRow']['script_filename'];
}

if ($GLOBALS['gApcuEnabled'] && $GLOBALS['gUserRow']['administrator_flag'] && empty($_GET['ajax']) && function_exists("apcu_sma_info")) {
	$memoryInfo = apcu_sma_info();

	$numberSegments = $freeSegments = $fragmentSize = $freeTotal = 0;
	for ($i = 0; $i < $memoryInfo['num_seg']; $i++) {
		$pointer = 0;
		foreach ($memoryInfo['block_lists'][$i] as $block) {
			if ($block['offset'] != $pointer) {
				++$numberSegments;
			}
			$pointer = $block['offset'] + $block['size'];
			if ($block['size'] < (5 * 1024 * 1024)) {
				$fragmentSize += $block['size'];
			}
			$freeTotal += $block['size'];
		}
		$freeSegments += count($memoryInfo['block_lists'][$i]);
	}
	if ($freeTotal > 0) {
		$fragmentation = ($fragmentSize / $freeTotal) * 100;
		if ($fragmentation > 90) {
			if ($GLOBALS['gApcuEnabled']) {
				apcu_clear_cache();
			}
		}
	}
}

if ($GLOBALS['gLoggedIn'] && empty($GLOBALS['gPreemptivePage']) && empty($GLOBALS['gPasswordReset']) && stristr($pageScriptFilename, "resetpassword.php") === false) {

# Get Page to be used for passwords. Then check for forced password change or expired password change

	$passwordPageName = getPreference("PASSWORD_PAGE");
	if (empty($passwordPageName)) {
		$passwordPageName = ($GLOBALS['gUserRow']['administrator_flag'] ? "resetpassword.php" : "reset-password");
	}
	if (!startsWith($GLOBALS['gUserRow']['password'],"SSO_")) {
		$maxPasswordDays = getPreference("PASSWORD_EXPIRE");
		if (empty($maxPasswordDays) || !is_numeric($maxPasswordDays)) {
			$maxPasswordDays = 0;
		}
		if (getPreference("PCI_COMPLIANCE") && $GLOBALS['gUserRow']['administrator_flag']) {
			$maxPasswordDays = 90;
		}
		if (empty($GLOBALS['gUserRow']['last_password_change'])) {
			$daysSince = ceil((date("U") - strtotime($GLOBALS['gUserRow']['date_created'])) / 86400);
		} else {
			$daysSince = ceil((date("U") - strtotime($GLOBALS['gUserRow']['last_password_change'])) / 86400);
		}
		if ($daysSince >= $maxPasswordDays && $maxPasswordDays > 0) {
			$GLOBALS['gRedirectLogEntry'] .= "Redirected for reset password";
			addSecurityLog("", "REDIRECT", $GLOBALS['gRedirectLogEntry']);
			header("Location: " . $passwordPageName . (strpos($passwordPageName, "?") === false ? "?" : "&") . "url_subpage=expired");
			exit;
		}
		if ($GLOBALS['gUserRow']['force_password_change']) {
			$GLOBALS['gRedirectLogEntry'] .= "Redirected for reset password";
			addSecurityLog("", "REDIRECT", $GLOBALS['gRedirectLogEntry']);
			header("Location: " . $passwordPageName . (strpos($passwordPageName, "?") === false ? "?" : "&") . "url_subpage=forced");
			exit;
		}
	}
}

if (function_exists("clientRequirements")) {
	$returnValue = clientRequirements();
	if ($returnValue !== false && $returnValue !== true && strlen($returnValue) > 0 && empty($GLOBALS['gPreemptivePage'])) {
		$GLOBALS['gRedirectLogEntry'] .= "Redirected for client requirements";
		addSecurityLog("", "REDIRECT", $GLOBALS['gRedirectLogEntry']);
		header("Location: " . $returnValue);
		exit;
	}
}

if (!empty($GLOBALS['gPageCode'])) {
	$GLOBALS['gPermissionLevel'] = canAccessPageCode((empty($GLOBALS['gProxyPageCode']) ? $GLOBALS['gPageCode'] : $GLOBALS['gProxyPageCode']));
	if (empty($GLOBALS['gPermissionLevel'])) {
		$GLOBALS['gRedirectLogEntry'] .= "Redirected because of unauthorized access: " . $_SERVER['REQUEST_URI'];
		addSecurityLog($GLOBALS['gUserRow']['user_name'], "REDIRECT", $GLOBALS['gRedirectLogEntry']);
		header("Location: /");
		if ($GLOBALS['gCommandLine']) {
			echo "Permission Denied" . $GLOBALS['gLineEnding'];
		}
		exit;
	}
} else {
	if (!$GLOBALS['gUserRow']['superuser_flag']) {
		$GLOBALS['gRedirectLogEntry'] .= "Redirected because of unauthorized access: " . $_SERVER['REQUEST_URI'];
		addSecurityLog($GLOBALS['gUserRow']['user_name'], "REDIRECT", $GLOBALS['gRedirectLogEntry']);
		header("Location: /");
		if ($GLOBALS['gCommandLine']) {
			echo "Permission Denied" . $GLOBALS['gLineEnding'];
		}
		exit;
	}
}

if ($GLOBALS['gUserRow']['superuser_flag']) {
	$resultSet = executeQuery("select * from query_log_directives where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
	if ($row = getNextRow($resultSet)) {
		$GLOBALS['gLogLiveQueries'] = true;
		$GLOBALS['gLogDatabaseQueries'] = true;
	}
}

$GLOBALS['gClientSubsystems'] = getCachedData("client_subsystems", "client_subsystems");
$GLOBALS['gClientSubsystemCodes'] = getCachedData("client_subsystem_codes", "client_subsystem_codes");
if (empty($GLOBALS['gClientSubsystems']) || empty($GLOBALS['gClientSubsystemCodes'])) {
	$resultSet = executeQuery("select * from subsystems where subsystem_code = 'CORE_SYSTEM' or subsystem_id in (select subsystem_id from client_subsystems where client_id = ?)", $GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$GLOBALS['gClientSubsystems'][] = $row['subsystem_id'];
		$GLOBALS['gClientSubsystemCodes'][] = $row['subsystem_code'];
		setCachedData("client_subsystems", "client_subsystems", $GLOBALS['gClientSubsystems'], 1);
		setCachedData("client_subsystem_codes", "client_subsystem_codes", $GLOBALS['gClientSubsystemCodes'], 1);
	}
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/shared/custom_startup.inc")) {
	include_once "custom_startup.inc";
}

# if just going to a domain name, check to see if the user or domain has a specific page to go to

if (!empty($redirectPageId)) {
	loadPage($redirectPageId);
}

if ($templateFilename && !class_exists("Template", false)) {
	include_once $templateFilename;
}

if (!empty($pageScriptFilename) && file_exists($GLOBALS['gDocumentRoot'] . "/local/" . str_replace(".php", ".inc", $pageScriptFilename))) {
	include_once $GLOBALS['gDocumentRoot'] . "/local/" . str_replace(".php", ".inc", $pageScriptFilename);
}
