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

$GLOBALS['gPageCode'] = "DEGREEPROGRAMMAINT";
require_once "shared/startup.inc";

class DegreeProgramMaintenance extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("degree_program_requirements", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("degree_program_requirements", "control_key", "required_degree_program_id");
		$this->iDataSource->addColumnControl("degree_program_requirements", "control_table", "degree_programs");
		$this->iDataSource->addColumnControl("degree_program_requirements", "data_type", "custom");
		$this->iDataSource->addColumnControl("degree_program_requirements", "form_label", "Degree Prerequisites");
		$this->iDataSource->addColumnControl("degree_program_requirements", "links_table", "degree_program_requirements");

		$this->iDataSource->addColumnControl("degree_program_courses", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("degree_program_courses", "control_table", "courses");
		$this->iDataSource->addColumnControl("degree_program_courses", "data_type", "custom");
		$this->iDataSource->addColumnControl("degree_program_courses", "form_label", "Courses");
		$this->iDataSource->addColumnControl("degree_program_courses", "links_table", "degree_program_courses");
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$customFields = CustomField::getCustomFields("degree_programs");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$customFields = CustomField::getCustomFields("degree_programs");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
	}

	function addCustomFields() {
		$customFields = CustomField::getCustomFields("degree_programs");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("degree_programs");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}


$pageObject = new DegreeProgramMaintenance("degree_programs");
$pageObject->displayPage();
