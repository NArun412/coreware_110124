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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}
$GLOBALS['gAllowLongRun'] = true;

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "preload_web_pages";
	}

	function process() {
		$linkUrls = array();
		$tableId = getReadFieldFromId("table_id", "tables", "table_name", "product_departments");
		$preferenceId = getReadFieldFromId("preference_id", "preferences", "preference_code", "WEB_URL");
		$skipPreloadPreferenceId = getReadFieldFromId("preference_id", "preferences", "preference_code", "SKIP_PRELOAD");
		$resultSet = executeReadQuery("select * from url_alias_types where client_id in (select client_id from clients where inactive = 0) and table_id = ? and " .
			"client_id in (select client_id from client_preferences where preference_value is not null and preference_id = ?) order by client_id", $tableId, $preferenceId);
		while ($row = getNextRow($resultSet)) {
			$domainName = getReadFieldFromId("preference_value","client_preferences","client_id",$row['client_id'],"preference_id = ?",$preferenceId);
			if (!empty($skipPreloadPreferenceId)) {
				$skipPreload = getReadFieldFromId("preference_value", "client_preferences", "client_id", $row['client_id'], "preference_id = ?", $skipPreloadPreferenceId);
				if (!empty($skipPreload)) {
					continue;
				}
			}
			if (empty($domainName)) {
				continue;
			}
			if (!empty($row['domain_name']) && $domainName != $row['domain_name']) {
				continue;
			}
			if (substr($domainName, 0, 4) !== "http") {
				$domainName = "https://" . $domainName;
			}
			if (substr($domainName, -1) !== "/") {
				$domainName = $domainName . "/";
			}
			$url = $domainName . $row['url_alias_type_code'] . "/%product_department%";
			$linkUrls[] = array("link_url" => $url, "client_id" => $row['client_id']);
		}
		$count = 0;
		if (count($linkUrls) > 0) {
			$pauseTime = round(3600 / count($linkUrls));
			if ($pauseTime > 30 || $pauseTime < 5) {
				$pauseTime = 5;
			}
		} else {
			$pauseTime = 5;
		}
		foreach ($linkUrls as $linkInfo) {
			$linkUrl = $linkInfo['link_url'];
			$linkSet = executeReadQuery("select link_name from product_departments where link_name is not null and client_id = ?" .
				" and inactive = 0 and internal_use_only = 0", $linkInfo['client_id']);
			while ($linkRow = getNextRow($linkSet)) {
				$useLinkUrl = str_replace("%product_department%", $linkRow['link_name'], $linkUrl);
				$GLOBALS['gStartTime'] = getMilliseconds();
				$curlHandle = curl_init();
				curl_setopt($curlHandle, CURLOPT_URL, $useLinkUrl);
				curl_setopt($curlHandle, CURLOPT_HEADER, 0);
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curlHandle, CURLOPT_FORBID_REUSE, false);
				curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 1);
				curl_setopt($curlHandle, CURLOPT_TIMEOUT, 1);
				curl_setopt($curlHandle, CURLOPT_DNS_CACHE_TIMEOUT, 1);
				curl_setopt($curlHandle, CURLOPT_FRESH_CONNECT, true);
				$siteContent = curl_exec($curlHandle);
				curl_close($curlHandle);
				$GLOBALS['gEndTime'] = getMilliseconds();
				$this->addResult("URL loading: " . $useLinkUrl . ", " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000,2) . " seconds, " . strlen($siteContent) . " bytes");
				sleep($pauseTime);
				$count++;
			}
		}
		$this->addResult($count . " pages loaded");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
