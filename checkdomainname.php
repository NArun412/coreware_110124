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

/* checkdomainname.php - check domain names in the database to allow on-the-fly URL provisioning (e.g. in response to a form)
 * called via Ajax.  parameters:
 * ajax - required to make sure page is not called by accident
 * domain_name - full domain name to check
 */

$GLOBALS['gPageCode'] = "CHECKDOMAINNAME";
require_once "shared/startup.inc";

if (empty($_GET['ajax'])) {
	header("Location: /");
	exit;
}

$domainName = "";
$valid = true;
$taken = false;
$_GET['domain_name'] = str_replace(" ", "-", strtolower($_GET['domain_name']));
$_GET['domain_name'] = str_replace(array("https://", "http://"), "", strtolower($_GET['domain_name']));
for ($x = 0; $x < strlen($_GET['domain_name']); $x++) {
	$char = substr($_GET['domain_name'], $x, 1);
	if (strpos("abcdefghijklmnopqrstuvwxyz01234567890.-", $char) !== false) {
		$domainName .= $char;
	}
}
$domainName = str_replace("www.", "", $domainName);
if ($domainName !== $_GET['domain_name'] || strlen($domainName) > 253) {
	$valid = false;
}

$returnArray = array();

$resultSet = executeQuery("select domain_name from domain_names where domain_name = ?", $domainName);

$suggestion = "";
if ($resultSet['row_count'] > 0) {
	$taken = true;
	$domainParts = explode(".", $domainName);
	$firstPart = array_shift($domainParts);
	$remainder = implode(".", $domainParts);
	$i = 0;
	do {
		$suggestion = $firstPart . ++$i . "." . $remainder;
		$suggestionSet = executeQuery("select domain_name from domain_names where domain_name = ?", $suggestion);
	} while ($suggestionSet['row_count'] > 0);
}

if ($valid && !$taken) {
	$returnArray['info_domain_name_message'] = "Domain Name '" . $domainName . "' is available";
} else if ($taken) {
	$returnArray['error_domain_name_message'] = "Domain Name '" . $domainName . "' is already taken. Use '" . $suggestion . "' instead?";
} else {
	$returnArray['error_domain_name_message'] = "Domain Name '" . $_GET['domain_name'] . "' is invalid. Use '" . $domainName . "' instead?";
}

echo jsonEncode($returnArray);