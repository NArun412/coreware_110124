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

$GLOBALS['gPageCode'] = "TOUCHPOINTMAINT";
require_once "shared/startup.inc";

class TouchpointMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("task_type_id","description","creator_user_id","creator_user_id_display","date_completed","assigned_user_id","assigned_user_id_display","date_due"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("task_type_id","description","creator_user_id","creator_user_id_display","date_completed","assigned_user_id","assigned_user_id_display","date_due"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));

			$filters = array();
			$filters['date_completed'] = array("form_label" => "Not Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['date_due'] = array("form_label" => "Response Required", "where" => "date_due is not null and date_completed is null", "data_type" => "tinyint");
			$filters['assigned'] = array("form_label" => "Assigned to Me", "where" => "date_due is not null and date_completed is null and assigned_user_id = " . $GLOBALS['gUserId'], "data_type" => "tinyint");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("contact_id is not null");
		$this->iDataSource->addColumnControl("task_attachments","data_type","custom");
		$this->iDataSource->addColumnControl("task_attachments","control_class","EditableList");
		$this->iDataSource->addColumnControl("task_attachments","form_label","Documents");
		$this->iDataSource->addColumnControl("task_attachments","list_table","task_attachments");
		$this->iDataSource->addColumnControl("task_log","data_type","custom");
		$this->iDataSource->addColumnControl("task_log","control_class","EditableList");
		$this->iDataSource->addColumnControl("task_log","form_label","Log");
		$this->iDataSource->addColumnControl("task_log","list_table","task_log");
		$this->iDataSource->addColumnControl("task_log","list_table_controls",array("log_date"=>array("default_value"=>date("m/d/Y"),"readonly"=>true), "user_id"=>array("default_value"=>$GLOBALS['gUserId'],"readonly"=>true)));
		$this->iDataSource->addColumnControl("creator_user_id","form_label","Created By");
		$this->iDataSource->addColumnControl("creator_user_id","data_type","contact_picker");
		$this->iDataSource->addColumnControl("assigned_user_id","form_label","Assigned User");
		$this->iDataSource->addColumnControl("assigned_user_id","data_type","contact_picker");
		$this->iDataSource->addColumnControl("date_due","form_label","Follow Up By");
		$this->iDataSource->addColumnControl("date_completed","form_label","Done On");
		$this->iDataSource->addColumnControl("task_type_id","get_choices","taskTypeChoices");
	}

	function taskTypeChoices($showInactive = false) {
		$taskTypeChoices = array();
		$resultSet = executeQuery("select * from task_types where task_type_id in (select task_type_id from task_type_attributes " .
			"where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK')) and " .
			"client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$taskTypeChoices[$row['task_type_id']] = array("key_value" => $row['task_type_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "data-assigned_user_id" => $row['user_id']);
			}
		}
		return $taskTypeChoices;
	}
}

$pageObject = new TouchpointMaintenancePage("tasks");
$pageObject->displayPage();
