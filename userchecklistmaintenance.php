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

$GLOBALS['gPageCode'] = "USERCHECKLISTMAINT";
require_once "shared/startup.inc";

class UserChecklistMaintenancePage extends Page {

	function setup() {
		$this->iDataSource->getPrimaryTable()->setSubtables("user_checklist_items");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("user_id"));
			$this->iTemplateObject->getTableEditorObject()->setFormSortOrder(array("description", "date_completed", "notes", "paste_items", "user_checklist_items", "done_user_checklist_items"));
			$filters = array();
			$filters['hide_done'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("user_checklist_items", "data_type", "custom");
		$this->iDataSource->addColumnControl("user_checklist_items", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("user_checklist_items", "list_table", "user_checklist_items");
		$this->iDataSource->addColumnControl("user_checklist_items", "form_label", "Items");
		$this->iDataSource->addColumnControl("user_checklist_items", "filter_where", "date_completed is null");

		$this->iDataSource->addColumnControl("paste_items", "data_type", "text");
		$this->iDataSource->addColumnControl("paste_items", "form_label", "Paste Items");

		$this->iDataSource->addColumnControl("done_user_checklist_items", "data_type", "custom");
		$this->iDataSource->addColumnControl("done_user_checklist_items", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("done_user_checklist_items", "list_table", "user_checklist_items");
		$this->iDataSource->addColumnControl("done_user_checklist_items", "form_label", "Completed Items");
		$this->iDataSource->addColumnControl("done_user_checklist_items", "filter_where", "date_completed is not null");

		$this->iDataSource->addColumnControl("user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("user_id", "readonly", true);

		$this->iDataSource->addFilterWhere("user_id = " . $GLOBALS['gUserId']);
	}

	function onLoadJavascript() {
		?>
		<script>
			$("#paste_items").change(function() {
				const items = $(this).val().split("\n");
				$.each(items,function(index,value) {
					if (empty(value)) {
						return true;
					}
					const thisRow = {};
					thisRow['description'] = { data_value: value };
					addEditableListRow("user_checklist_items",thisRow);
				});
				$(this).val("");
			});
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			#_user_checklist_items_table.editable-list input[type=text] {
				max-width: 500px;
			}
		</style>
		<?php
	}

}

$pageObject = new UserChecklistMaintenancePage("user_checklists");
$pageObject->displayPage();
