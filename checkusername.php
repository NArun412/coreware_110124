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

$GLOBALS['gPageCode'] = "CHECKUSERNAME";
require_once "shared/startup.inc";

if (empty($_GET['ajax'])) {
	header("Location: /");
	exit;
}

if (empty($_GET['user_name']) && !empty($_GET['email_address'])) {
	$resultSet = executeQuery("select * from contacts left join users using (contact_id) where contacts.client_id = ? and email_address = ? order by user_id desc,contact_id",$GLOBALS['gClientId'],$_GET['email_address']);
	$returnArray = array();
	if ($row = getNextRow($resultSet)) {
		$returnArray['contact_id'] = $row['contact_id'];
		$returnArray['user_id'] = $row['user_id'];
	}
	ajaxResponse($returnArray);
	exit;
}

$userName = "";
$_GET['user_name'] = str_replace(" ", "_", strtolower($_GET['user_name']));
for ($x = 0; $x < strlen($_GET['user_name']); $x++) {
	$char = substr($_GET['user_name'], $x, 1);
	if (strpos("abcdefghijklmnopqrstuvwxyz01234567890@_.-", $char) !== false) {
		$userName .= $char;
	}
}
$fieldId = $_GET['field_id'];

$returnArray = array();
$returnArray["field_id"] = $fieldId;

if (!empty($_GET['user_id'])) {
	$userId = getFieldFromId("user_id", "users", "user_name", $userName, "client_id = ?", $GLOBALS['gClientId']);
	if ($userId == $_GET['user_id']) {
		ajaxResponse($returnArray);
	}
}
$userNameValid = ($_GET['valid'] == "true");
if (empty($_GET['user_id'])) {
	$resultSet = executeQuery("select user_id from users where (user_name = ? or (user_name_alias is not null and user_name_alias = ?)) and (client_id = ? or superuser_flag = 1)", $userName, $userName, $GLOBALS['gClientId']);
} else {
	$resultSet = executeQuery("select user_id from users where (user_name = ? or (user_name_alias is not null and user_name_alias = ?)) and user_id <> ? and (client_id = ? or superuser_flag = 1)", $userName, $userName, $_GET['user_id'], $GLOBALS['gClientId']);
}
if ($resultSet['row_count'] > 0) {
	if ($userNameValid) {
		$returnArray['info_user_name_message'] = "Username '" . $userName . "' is valid";
	} else {
		$returnArray['error_user_name_message'] = "Username '" . $userName . "' is already taken";
	}
} else {
	if ($userNameValid) {
		$returnArray['error_user_name_message'] = "Username '" . $userName . "' is invalid";
	} else {
		$returnArray['info_user_name_message'] = "Username '" . $userName . "' is available";
	}
}
echo jsonEncode($returnArray);

