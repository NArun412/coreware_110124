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

$GLOBALS['gPageCode'] = "CREATETOUCHPOINT";
require_once "shared/startup.inc";

class CreateTouchpointPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "list"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("creator_user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("creator_user_id", "data_type", "select");
		$this->iDataSource->addColumnControl("creator_user_id", "get_choices", "userChoices");
		$this->iDataSource->addColumnControl("task_type_id", "get_choices", "taskTypeChoices");
		$this->iDataSource->addColumnControl("task_type_id", "get_choices", "taskTypeChoices");
		$this->iDataSource->addColumnControl("task_type_id", "not_null", true);
		$this->iDataSource->addColumnControl("description", "not_null", true);

		$this->iDataSource->addColumnControl("contact_id", "not_null", true);
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");

		$this->iDataSource->addColumnControl("selected_contacts", "data_type", "tinyint");
		$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = (select page_id from pages where page_code = 'CONTACTMAINT')", $GLOBALS['gUserId']);
		$count = 0;
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		$this->iDataSource->addColumnControl("selected_contacts", "form_label", "Add to all selected contacts - " . ($count == 0 ? "none" : ($count . " contact" . ($count == 1 ? "" : "s"))) . " selected");

		$this->iDataSource->addColumnControl("requires_response", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("requires_response", "form_label", "Requires a followup response");
		$this->iDataSource->addColumnControl("requires_response", "ignore_crc", true);
		$this->iDataSource->addColumnControl("date_completed", "initial_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("date_completed", "data-conditional-required", "!\$(\"#requires_response\").prop(\"checked\")");
		$this->iDataSource->addColumnControl("date_completed", "not_null", true);
		$this->iDataSource->addColumnControl("assigned_user_id", "get_choices", "userChoices");
		$this->iDataSource->addColumnControl("assigned_user_id", "not_null", true);
		$this->iDataSource->addColumnControl("assigned_user_id", "data-conditional-required", "\$(\"#requires_response\").prop(\"checked\")");
		$this->iDataSource->addColumnControl("last_changed", "default_value", date("Y-m-d H:i:s"));
		$this->iDataSource->addColumnControl("date_due", "not_null", true);
		$this->iDataSource->addColumnControl("date_due", "data-conditional-required", "\$(\"#requires_response\").prop(\"checked\")");
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

	function beforeSaveChanges(&$nameValues) {
		if (!empty($nameValues['selected_contacts'])) {
			$resultSet = executeQuery("select primary_identifier from selected_rows where user_id = ? and page_id = (select page_id from pages where page_code = 'CONTACTMAINT')", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $row['primary_identifier']);
				if (!empty($contactId)) {
					$nameValues['contact_id'] = $contactId;
					executeQuery("delete from selected_rows where user_id = ? and primary_identifier = ? and page_id = (select page_id from pages where page_code = 'CONTACTMAINT')", $GLOBALS['gUserId'], $contactId);
					break;
				}
			}
		}
		return true;
	}

	function afterSaveChanges($nameValues) {
		if (!empty($nameValues['selected_contacts'])) {
			unset($nameValues['primary_id']);
			unset($nameValues['_add_hash']);
			unset($nameValues['version']);
			$dataTable = new DataTable("tasks");
			$resultSet = executeQuery("select primary_identifier from selected_rows where user_id = ? and page_id = (select page_id from pages where page_code = 'CONTACTMAINT')", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $row['primary_identifier']);
				if (empty($contactId)) {
					continue;
				}
				$nameValues['contact_id'] = $contactId;
				if (!$primaryId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => ""))) {
					return $dataTable->getErrorMessage();
				}
			}
			executeQuery("delete from selected_rows where user_id = ? and page_id = (select page_id from pages where page_code = 'CONTACTMAINT')", $GLOBALS['gUserId']);
		}
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#selected_contacts", function () {
                if ($(this).prop("checked")) {
                    $("#_contact_id_row").addClass("hidden");
                } else {
                    $("#_contact_id_row").removeClass("hidden");
                }
            });
            $("#requires_response").click(function () {
                if ($(this).prop("checked")) {
                    $(".requires-response").removeClass("hidden");
                    $("#date_completed").val("");
                } else {
                    $(".requires-response").addClass("hidden");
                }
            });
        </script>
		<?php
	}
}

$_GET['url_page'] = "new";
$pageObject = new CreateTouchpointPage("tasks");
$pageObject->displayPage();
