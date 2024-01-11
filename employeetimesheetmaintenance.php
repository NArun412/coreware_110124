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

$GLOBALS['gPageCode'] = "EMPLOYEETIMESHEETMAINT";
require_once "shared/startup.inc";

class EmployeeTimeSheetMaintenancePage extends Page {
	function setup() {
		$this->iDataSource->addColumnControl("user_id", "readonly", "true");
		$this->iDataSource->addColumnControl("user_id", "get_choices", "userChoices");
		$this->iDataSource->addColumnControl("date_entered", "readonly", "true");
		$this->iDataSource->addColumnControl("start_time", "date_format", "g:ia");
		$this->iDataSource->addColumnControl("end_time", "date_format", "g:ia");
		$this->iDataSource->addColumnControl("user_display_name", "select_value", "select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = employee_time_sheets.user_id)");
		$this->iDataSource->addColumnControl("user_display_name", "form_label", "Employee Name");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_display_name", "date_entered", "start_time", "end_time"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("user_display_name"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("user_display_name", "date_entered"));
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['start_time_hour'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['start_time_minute'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['end_time_hour'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['end_time_minute'] = array("data_value" => "", "crc_value" => getCrcValue(""));
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if ($nameValues['start_time_hour']) {
			$startTime = getFieldFromId("date_entered", "employee_time_sheets", "employee_time_sheet_id", $nameValues['primary_id']);
			$startTime .= " " . $nameValues['start_time_hour'] . ":" . (empty($nameValues['start_time_minute']) ? "00" : $nameValues['start_time_minute']) . ":00";
			executeQuery("update employee_time_sheets set start_time = ? where employee_time_sheet_id = ?", $startTime, $nameValues['primary_id']);
		}
		if ($nameValues['end_time_hour']) {
			$endTime = getFieldFromId("date_entered", "employee_time_sheets", "employee_time_sheet_id", $nameValues['primary_id']);
			$endTime .= " " . $nameValues['end_time_hour'] . ":" . (empty($nameValues['end_time_minute']) ? "00" : $nameValues['end_time_minute']) . ":00";
			executeQuery("update employee_time_sheets set end_time = ? where employee_time_sheet_id = ?", $endTime, $nameValues['primary_id']);
		}
		return true;
	}

	function minuteOptions() {
		for ($x = 0; $x < 60; $x++) {
			$x = (strlen($x) == 1 ? "0" : "") . $x;
			echo "<option value='" . $x . "'>" . $x . "</option>\n";
		}
	}
}

$pageObject = new EmployeeTimeSheetMaintenancePage("");
$pageObject->displayPage();
