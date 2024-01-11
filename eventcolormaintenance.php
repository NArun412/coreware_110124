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

$GLOBALS['gPageCode'] = "EVENTCOLORMAINTENANCE";
require_once "shared/startup.inc";

class EventColorMaintenancePage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("field_value");
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("exclude", "comparator", "field_value_display", "display_color", "sort_order"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("comparator", "data_type", "select");

		$this->iDataSource->addColumnControl("field_value_display", "data_type", "varchar");
		$this->iDataSource->addColumnControl("field_value_display", "form_label", "Value");

		$comparators = array();
		$comparators['event_type'] = "Event Type is";
		$comparators['order'] = "Event has order";
		$comparators['facility_type'] = "Facility Type is";
		$comparators['contact_category'] = "Reserved by Contact with Category";
		$comparators['contact_type'] = "Reserved by Contact Type";
		$comparators['user_type'] = "Reserved by User Type";
		$comparators['user_group'] = "Reserved by User in Group";
		$comparators['reserved'] = "Reserved";
		$this->iDataSource->addColumnControl("comparator", "choices", $comparators);
	}

	function dataListProcessing(&$dataList) {
		foreach ($dataList as $index => $row) {
			switch ($row['comparator']) {
				case "event_type":
					$dataList[$index]['field_value_display'] = getFieldFromId("description", "event_types", "event_type_id", $row['field_value']);
					break;
				case "facility_type":
					$dataList[$index]['field_value_display'] = getFieldFromId("description", "facility_types", "facility_type_id", $row['field_value']);
					break;
				case "contact_category":
					$dataList[$index]['field_value_display'] = getFieldFromId("description", "categories", "category_id", $row['field_value']);
					break;
				case "contact_type":
					$dataList[$index]['field_value_display'] = getFieldFromId("description", "contact_types", "contact_type_id", $row['field_value']);
					break;
				case "user_type":
					$dataList[$index]['field_value_display'] = getFieldFromId("description", "user_types", "user_type_id", $row['field_value']);
					break;
				case "user_group":
					$dataList[$index]['field_value_display'] = getFieldFromId("description", "user_groups", "user_group_id", $row['field_value']);
					break;
			}
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_field_value_control":
				$returnArray['comparator'] = array("data_value" => $_GET['comparator']);
				$this->afterGetRecord($returnArray);
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray = array_merge(array("field_value_control" => array()), $returnArray);
		ob_start();
		switch ($returnArray['comparator']['data_value']) {
			case "event_type":
				echo createFormControl("events", "event_type_id", array("column_name" => "field_value", "not_null" => true));
				break;
			case "facility_type":
				echo createFormControl("facilities", "facility_type_id", array("column_name" => "field_value", "not_null" => true));
				break;
			case "contact_type":
				echo createFormControl("contacts", "contact_type_id", array("column_name" => "field_value", "not_null" => true));
				break;
			case "contact_category":
				echo createFormControl("contact_categories", "category_id", array("column_name" => "field_value", "not_null" => true));
				break;
			case "user_type":
				echo createFormControl("users", "user_type_id", array("column_name" => "field_value", "not_null" => true));
				break;
			case "user_group":
				echo createFormControl("user_group_members", "user_group_id", array("column_name" => "field_value", "not_null" => true));
				break;
		}
		$returnArray['field_value_control']['data_value'] = ob_get_clean();
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#comparator").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_field_value_control&comparator=" + encodeURIComponent($(this).val()), function(returnArray) {
                    if ("field_value_control" in returnArray) {
                        $("#field_value_control").html(returnArray['field_value_control']['data_value']);
                    }
                });
            });
        </script>
		<?php
	}
}

$pageObject = new EventColorMaintenancePage("event_colors");
$pageObject->displayPage();
