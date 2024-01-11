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

$GLOBALS['gPageCode'] = "GETUSERPICKERLIST";
require_once "shared/startup.inc";

$returnArray = array();
if (!empty($_GET['user_id'])) {
	$resultSet = executeQuery("select user_id from users where inactive = 0 and client_id = ? and user_id = ?",$GLOBALS['gClientId'],$_GET['user_id']);
	$userInfo = array();
	if ($row = getNextRow($resultSet)) {
		$description = getUserDisplayName($row['user_id']);
		$userInfo = array("description"=>$description,"user_id"=>$row['user_id']);
		$returnArray['user_info'] = $userInfo;
	}
	ajaxResponse($returnArray);
}
$filterTextParts = explode(" ",$_POST['user_picker_filter_text']);
$fields = array("first_name","last_name","business_name","user_name");
$whereStatement = "";
$whereParameters = array($GLOBALS['gClientId']);

$pagePreferences = Page::getPagePreferences("GETUSERPICKERLIST");
$pagePreferences['user_picker_user_type_id'] = $_POST['user_picker_user_type_id'];
$pagePreferences['user_picker_user_group_id'] = $_POST['user_picker_user_group_id'];
Page::setPagePreferences($pagePreferences,"GETUSERPICKERLIST");
if (!empty($_POST['user_picker_filter_text'])) {
	$whereSegment = "";
	foreach ($fields as $fieldName) {
		$whereSegment .= (empty($whereSegment) ? "" : " or ") . $fieldName . " like ?";
		$whereParameters[] = $_POST['user_picker_filter_text'] . "%";
	}
	foreach ($filterTextParts as $textPart) {
		if (strlen($textPart) > 1) {
			$thisWherePart = "";
			foreach ($fields as $fieldName) {
				if (!empty($thisWherePart)) {
					$thisWherePart .= " or ";
				}
				$thisWherePart .= $fieldName . " like ?";
				$whereParameters[] = $textPart . "%";
			}
			if (!empty($whereSegment)) {
				$whereSegment .= " and ";
			}
			if (!empty($thisWherePart)) {
				$whereSegment .= "(" . $thisWherePart . ")";
			}
		}
	}
	if (!empty($whereSegment)) {
		$whereStatement .= (empty($whereStatement) ? "" : " or ") . "(" . $whereSegment . ")";
	}

	if (count($filterTextParts) == 2) {
		$lastName = array_pop($filterTextParts);
		$firstName = implode(" ", $filterTextParts);
		$whereSegment = "(first_name like ?";
		$whereParameters[] = $firstName . "%";
		$whereSegment .= " and last_name like ?)";
		$whereParameters[] = $lastName . "%";
		$whereStatement .= (empty($whereStatement) ? "" : " or ") . $whereSegment;
	}
}
$resultSet = executeQuery("select users.user_id,user_name,city,state from users join contacts using (contact_id) where inactive = 0 and users.client_id = ?" .
	(empty($_POST['user_picker_user_type_id']) ? "" : " and user_type_id = " . makeNumberParameter($_POST['user_picker_user_type_id'])) .
	(empty($_POST['user_picker_user_group_id']) ? "" : " and user_id in (select user_id from user_group_members where user_group_id = " . makeNumberParameter($_POST['user_picker_user_group_id']) . ")") .
	(empty($_POST['_user_picker_filter_where']) ? "" : " and " . $_POST['_user_picker_filter_where']) .
	(!empty($_GET['admin']) || !empty($_POST['admin']) ? " and administrator_flag = 1" : "") .
	(empty($whereStatement) ? "" : " and (" . $whereStatement . ")") . " order by users.date_created desc limit 50",$whereParameters);
$userList = array();
while ($row = getNextRow($resultSet)) {
	$userDisplayName = getUserDisplayName($row['user_id']);
	$description = $userDisplayName . ", " . $row['city'] . ", " . $row['state'];
	$userList[] = array("description" => $description, "user_id" => $row['user_id'], "display_name" => $userDisplayName, "user_name" => $row['user_name']);
}
$returnArray['users'] = $userList;
echo jsonEncode($returnArray);
