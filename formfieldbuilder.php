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

$GLOBALS['gPageCode'] = "FORMFIELDBUILDER";
require_once "shared/startup.inc";

class FormFieldBuilderPage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("form_field_controls", "form_field_choices"));
		$this->iDataSource->addColumnControl("help_label", "data_type", "varchar");
		$this->iDataSource->addColumnControl("help_label", "form_label", "Help Label");
		$this->iDataSource->addColumnControl("custom_field_id", "get_choices", "customFieldChoices");
	}

	function customFieldChoices($showInactive = false) {
		$customFieldChoices = array();
		$resultSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$customFieldChoices[$row['custom_field_id']] = array("key_value" => $row['custom_field_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $customFieldChoices;
	}

	function afterGetRecord(&$returnArray) {
		$dataType = getFieldFromId("control_value", "form_field_controls", "form_field_id", $returnArray['primary_id']['data_value'], "control_name = 'data_type'");
		$returnArray['data_type'] = array("data_value" => $dataType, "crc_value" => getCrcValue($dataType));
		$required = getFieldFromId("control_value", "form_field_controls", "form_field_id", $returnArray['primary_id']['data_value'], "control_name = 'not_null'");
		$returnArray['required'] = array("data_value" => ($required == "true" ? 1 : 0), "crc_value" => getCrcValue(($required == "true" ? 1 : 0)));
		$helpLabel = getFieldFromId("control_value", "form_field_controls", "form_field_id", $returnArray['primary_id']['data_value'], "control_name = 'help_label'");
		$returnArray['help_label'] = array("data_value" => $helpLabel, "crc_value" => getCrcValue($helpLabel));
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("delete from form_field_controls where form_field_id = ? and control_name in ('data_type','not_null','help_label')", $nameValues['primary_id']);
		executeQuery("insert into form_field_controls (form_field_id,control_name,control_value) values (?,?,?)",
			$nameValues['primary_id'], "not_null", (empty($nameValues['required']) ? "false" : "true"));
		executeQuery("insert into form_field_controls (form_field_id,control_name,control_value) values (?,?,?)",
			$nameValues['primary_id'], "data_type", $nameValues['data_type']);
		if (!empty($nameValues['help_label'])) {
			executeQuery("insert into form_field_controls (form_field_id,control_name,control_value) values (?,?,?)",
				$nameValues['primary_id'], "help_label", $nameValues['help_label']);
		}
		return true;
	}
}

$pageObject = new FormFieldBuilderPage("form_fields");
$pageObject->displayPage();
