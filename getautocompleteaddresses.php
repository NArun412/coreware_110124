<?php

$GLOBALS['gPageCode'] = "GETAUTOCOMPLETEADDRESSES";
require_once "shared/startup.inc";

require_once('classes/smartystreets/src/StaticCredentials.php');
require_once('classes/smartystreets/src/ClientBuilder.php');
require_once('classes/smartystreets/src/US_Autocomplete/Lookup.php');
require_once('classes/smartystreets/src/US_Autocomplete/Client.php');
use SmartyStreets\PhpSdk\StaticCredentials;
use SmartyStreets\PhpSdk\ClientBuilder;
use SmartyStreets\PhpSdk\US_Autocomplete\Lookup;

if ($_SESSION['autocomplete_address'] != "coreware system") {
	ajaxResponse(array());
	exit;
}

$lookup = new USAutocomplete();
$addresses = $lookup->run();
ajaxResponse($addresses);
exit;

class USAutocomplete {
	public function run() {
		if (is_scalar($_GET['search']) && strlen($_GET['search']) && $_GET['country_id'] == 1000 && !$GLOBALS['gDevelopmentServer']) {
			$_GET['search'] = strtolower($_GET['search']);
			$addressSearchResults = getCachedData("autocomplete_addresses",$_GET['search'],true);

			if (!is_array($addressSearchResults)) {
				$domainName = $_SERVER['HTTP_HOST'];
				if (substr($domainName,0,4) == "www.") {
					$domainName = substr($domainName,4);
				}
				$domainNameTrackingLogId = getCachedData("smarty_streets_tracking",$domainName);
				if (empty($domainNameTrackingLogId)) {
					$domainNameTrackingLogId = getFieldFromId("domain_name_tracking_log_id", "domain_name_tracking_log", "domain_name", $domainName, "log_type = 'SMARTY_STREETS' and log_date = current_date");
				}
				if (empty($domainNameTrackingLogId)) {
					$GLOBALS['gPrimaryDatabase']->ignoreError(true);
					$resultSet = executeQuery("insert ignore into domain_name_tracking_log (client_id,domain_name,log_type,log_date,use_count) values (?,?,'SMARTY_STREETS',current_date,1)",$GLOBALS['gClientId'],$domainName);
					if (empty($resultSet['sql_error'])) {
						$domainNameTrackingLogId = $resultSet['insert_id'];
						setCachedData("smarty_streets_tracking",$domainName,$domainNameTrackingLogId,24);
					}
					$GLOBALS['gPrimaryDatabase']->ignoreError(false);
				} else {
					$resultSet = executeQuery("update domain_name_tracking_log set use_count = use_count + 1 where domain_name_tracking_log_id = ?",$domainNameTrackingLogId);
				}

				$curlHandle = curl_init();
				$url = "https://us-autocomplete-pro.api.smartystreets.com/lookup?key=32345522799156561&search=" . urlencode($_GET['search']);
				curl_setopt($curlHandle, CURLOPT_URL, $url);
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
				curl_setopt($curlHandle, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
				curl_setopt($curlHandle, CURLOPT_REFERER, 'https://coreware.com');
				$errorText = curl_error($curlHandle);
				$returnValue = curl_exec($curlHandle);

				curl_close($curlHandle);
				if (empty($returnValue)) {
					return array();
				}
				try {
					$suggestions = json_decode($returnValue, true);
				} catch (Exception $e) {
					return array();
				}
				if (!is_array($suggestions['suggestions'])) {
					return array();
				}
				$addressSearchResults = array();
				foreach ($suggestions['suggestions'] as $thisSuggestion) {
					$thisAddress = array("address_1" => $thisSuggestion['street_line'], "address_2" => $thisSuggestion['secondary'], "city" => $thisSuggestion['city'], "state" => $thisSuggestion['state'], "postal_code" => $thisSuggestion['zipcode']);
					$addressSearchResults[] = $thisAddress;
				}
			}
			setCachedData("autocomplete_addresses",$_GET['search'],$addressSearchResults,48,true);
			return $addressSearchResults;
		}
		return array();
	}
}
