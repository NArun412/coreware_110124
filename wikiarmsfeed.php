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

$GLOBALS['gPageCode'] = "WIKIARMSFEED";
require_once "shared/startup.inc";

$allowedIpAddresses = array("52.0.58.53", "207.244.83.229", "198.7.56.46", "207.244.83.227", "207.244.83.236", "69.180.112.233", "89.216.98.192", "23.105.168.23", "23.105.168.20", "23.105.168.21", "23.105.168.22", "23.105.168.21", "23.105.168.23", "23.105.168.18", "23.105.168.19");
$resultSet = executeReadQuery("select * from feed_whitelist_ip_addresses where client_id = ?", $GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
	$allowedIpAddresses[] = $row['ip_address'];
}
if (!$GLOBALS['gInternalConnection'] && (isWebCrawler() || !in_array($_SERVER['REMOTE_ADDR'], $allowedIpAddresses))) {
	addProgramLog("WikiArms IP address rejection: " . $_SERVER['REMOTE_ADDR']);
	exit;
}
$startTime = getMilliseconds();
$systemName = strtolower(getPreference("system_name"));
$filename = $GLOBALS['gDocumentRoot'] . "/feeds/wikiarmsfeed_" . strtolower(str_replace("_","",$systemName)) . "_" . $GLOBALS['gClientId'] . ".xml";

$feedDetailId = getReadFieldFromId("feed_detail_id", "feed_details", "feed_detail_code", "wikiarmsfeed");
if (empty($feedDetailId)) {
	executeQuery("insert into feed_details (client_id,feed_detail_code,time_created,last_activity) values (?,'wikiarmsfeed',now(),now())", $GLOBALS['gClientId']);
} else {
	executeQuery("update feed_details set last_activity = now() where feed_detail_id = ?", $feedDetailId);
}
shutdown();
if (file_exists($filename)) {
	echo file_get_contents($filename);
} else {
	file_put_contents($filename, jsonEncode(array()));
	echo jsonEncode(array());
}
