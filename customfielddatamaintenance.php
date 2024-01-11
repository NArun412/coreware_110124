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

$GLOBALS['gPageCode'] = "CUSTOMFIELDDATAMAINT";
require_once "shared/startup.inc";

class CustomFieldDataMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$resultSet = executeQuery("select * from custom_field_types order by description");
			$customFieldTypes = array();
			while ($row = getNextRow($resultSet)) {
				$customFieldTypes[$row['custom_field_type_id']] = $row['description'];
			}
			$filters['custom_field_type'] = array("form_label" => "Custom Field Type", "data_type" => "select", "where" => "custom_field_id in (select custom_field_id from custom_fields where custom_field_type_id = %key_value%)", "choices" => $customFieldTypes);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("primary_identifier", "not_editable", true);
		$this->iDataSource->addColumnControl("custom_field_id", "not_editable", true);
		$this->iDataSource->addFilterWhere("custom_field_id in (select custom_field_id from custom_fields where client_id = " . $GLOBALS['gClientId'] . ")");
	}

}

$pageObject = new CustomFieldDataMaintenancePage("custom_field_data");
$pageObject->displayPage();
