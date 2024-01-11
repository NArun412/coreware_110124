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

$GLOBALS['gPageCode'] = "VALIDATEPOSTALCODE";
$GLOBALS['gPreemptivePage'] = true;
require_once "shared/startup.inc";

if (empty($_GET['ajax'])) {
	header("Location: /");
	exit;
}

$returnArray = array();
$returnArray['cities'] = array();
$postalCode = $_GET['postal_code'];
if (strlen($postalCode) > 5) {
	$postalCode = substr($postalCode,0,5);
}
$resultSet = executeQuery("select city,state from postal_codes where postal_code = ? order by city",$postalCode);
while ($row = getNextRow($resultSet)) {
	$returnArray['cities'][] = array("city"=>$row['city'],"state"=>$row['state']);
}
if (count($returnArray['cities']) == 0 && !empty($postalCode)) {
	$returnArray['error_message'] = getSystemMessage("invalid_postal_code");
}
echo jsonEncode($returnArray);
exit;
