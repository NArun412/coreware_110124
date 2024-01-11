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

$GLOBALS['gPageCode'] = "CUSTOMFIELDBUILDER";
require_once "shared/startup.inc";

class CustomFieldBuilderPage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("custom_field_controls", "custom_field_group_links", "custom_field_choices"));
	}

	function afterGetRecord(&$returnArray) {
		$dataType = getFieldFromId("control_value", "custom_field_controls", "custom_field_id", $returnArray['primary_id']['data_value'], "control_name = 'data_type'");
		$returnArray['data_type'] = array("data_value" => $dataType, "crc_value" => getCrcValue($dataType));
		$required = getFieldFromId("control_value", "custom_field_controls", "custom_field_id", $returnArray['primary_id']['data_value'], "control_name = 'not_null'");
		$returnArray['required'] = array("data_value" => ($required == "true" ? 1 : 0), "crc_value" => getCrcValue(($required == "true" ? 1 : 0)));
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("delete from custom_field_controls where custom_field_id = ? and control_name in ('data_type','not_null')", $nameValues['primary_id']);
		executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)",
			$nameValues['primary_id'], "not_null", (empty($nameValues['required']) ? "false" : "true"));
		executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)",
			$nameValues['primary_id'], "data_type", $nameValues['data_type']);
		return true;
	}
}

$pageObject = new CustomFieldBuilderPage("custom_fields");
$pageObject->displayPage();
