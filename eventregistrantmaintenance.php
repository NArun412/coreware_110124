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

$GLOBALS['gPageCode'] = "EVENTREGISTRANTMAINT";
require_once "shared/startup.inc";

class EventRegistrantMaintenancePage extends Page {

    function setup() {
	    $filters = array();

	    $events = array();
	    $resultSet = executeQuery("select * from events where start_date >= current_date and client_id = ? order by description", $GLOBALS['gClientId']);
	    while ($row = getNextRow($resultSet)) {
		    $events[$row['event_id']] = $row['description'];
	    }
	    if (!empty($events)) {
		    $filters['event'] = array("form_label" => "Event", "where" => "event_id = %key_value%", "data_type" => "select", "choices" => $events);
	    }
	    $this->iTemplateObject->getTableEditorObject()->addFilters($filters);
    }

	function massageDataSource() {
		$this->iDataSource->addColumnControl("event_id", "not_editable", true);
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "not_editable", true);
		$this->iDataSource->addColumnControl("registration_time", "readonly", true);
		$this->iDataSource->addColumnControl("registration_time", "default_value", "now");
		$this->iDataSource->addColumnControl("check_in_time", "readonly", true);
		$this->iDataSource->addColumnControl("order_id", "readonly", true);
		$this->iDataSource->addColumnControl("order_id", "data_type", "int");
		$this->iDataSource->addColumnControl("notes", "form_label", "Notes");
	}

	function javascript() {
		?>
        <script>
            function beforeGetRecord(returnArray) {
                if ("jquery_templates" in returnArray) {
                    $("#_templates").html(returnArray['jquery_templates']);
                }
                return true;
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		$customFields = CustomField::getCustomFields("event_registrations");
		foreach ($customFields as $thisCustomField) {
			$eventRegistrationCustomFieldId = getFieldFromId("event_registration_custom_field_id", "event_registration_custom_fields", "event_id",
				$returnArray['event_id']['data_value'], "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventRegistrationCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl(array("basic_form_line" => true));
		}
		$returnArray['custom_field_wrapper'] = array("data_value" => ob_get_clean());
		ob_start();
		foreach ($customFields as $thisCustomField) {
			$eventRegistrationCustomFieldId = getFieldFromId("event_registration_custom_field_id", "event_registration_custom_fields", "event_id",
				$returnArray['event_id']['data_value'], "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventRegistrationCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
		$returnArray['jquery_templates'] = ob_get_clean();
		foreach ($customFields as $thisCustomField) {
			$eventRegistrationCustomFieldId = getFieldFromId("event_registration_custom_field_id", "event_registration_custom_fields", "event_id",
				$returnArray['event_id']['data_value'], "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventRegistrationCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$customFields = CustomField::getCustomFields("event_registrations");
        $eventId = getFieldFromId("event_id", "event_registrants", "event_registrant_id", $nameValues['primary_id']);
		foreach ($customFields as $thisCustomField) {
			$eventRegistrationCustomFieldId = getFieldFromId("event_registration_custom_field_id", "event_registration_custom_fields", "event_id",
				$eventId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventRegistrationCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
        return true;
	}
}

$pageObject = new EventRegistrantMaintenancePage("event_registrants");
$pageObject->displayPage();
