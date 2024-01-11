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

$GLOBALS['gPageCode'] = "SAVEEDITABLECONTENT";
require_once "shared/startup.inc";

if (empty($_GET['ajax'])) {
	header("Location: /");
	exit;
}
if ($_GET['url_action'] != "save_changes" ||
	(empty($GLOBALS['gUserRow']['superuser_flag']) && !hasCapability("CONTENT_EDITABLE")) ||
	empty($_POST['template_data_id']) || empty($_POST['page_id']) || empty($_POST['content'])) {
	echo jsonEncode(array());
	exit;
}

$templateDataRow = getRowFromId("template_data","template_data_id",$_POST['template_data_id']);
if (empty($templateDataRow)) {
	echo jsonEncode(array());
	exit;
}
$fieldName = getFieldFromDataType($templateDataRow['data_type']);
$pageDataRow = getRowFromId("page_data","template_data_id",$_POST['template_data_id'],"page_id = ?",$_POST['page_id']);
if (empty($pageDataRow)) {
	echo jsonEncode(array());
	exit;
}
$content = trim($_POST['content'],"\0 \t\r\n");
$resultSet = executeQuery("insert into change_log (log_id,client_id,user_id,table_name,column_name,primary_identifier,foreign_key_identifier," .
	"old_value,new_value,version) values (null,?,?,?,?,?,?,?,?,1)",array($GLOBALS['gClientId'],$GLOBALS['gUserId'],"page_data",$fieldName,
	$pageDataRow['page_data_id'],$_POST['page_id'],$pageDataRow[$fieldName],$content));
$resultSet = executeQuery("update page_data set " . $fieldName . " = ? where page_data_id = ?",$content,$pageDataRow['page_data_id']);
echo jsonEncode(array());

function getFieldFromDataType($dataType) {
	switch ($dataType) {
        case "int":
        case "bigint":
			return "integer_data";
		case "decimal":
			return "number_data";
		case "date":
			return "date_data";
		case "tinyint":
			return "integer_data";
		case "image":
			return "image_id";
		default:
			return "text_data";
	}
	return "text_data";
}
