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

$GLOBALS['gPageCode'] = "EVENTTYPEMAINT";
require_once "shared/startup.inc";

class EventTypeMaintenancePage extends Page {

	function setup() {
		$filters = array();
		$filters['tag_header'] = array("form_label" => "Tags", "data_type" => "header");
		$resultSet = executeQuery("select * from event_type_tags where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['event_type_tag_' . $row['event_type_tag_id']] = array("form_label" => $row['description'],
				"where" => "event_type_id in (select event_type_id from event_type_tag_links where event_type_tag_id = " . $row['event_type_tag_id'] . ")",
				"data_type" => "tinyint");
		}
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"), "disabled" => false)));
		}
	}

    function afterSaveChanges($nameValues) {
        $customFields = CustomField::getCustomFields("event_types");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            if (!$customField->saveData($nameValues)) {
                return $customField->getErrorMessage();
            }
        }
        return true;
    }

	function afterSaveDone($nameValues) {
		if (!empty($nameValues['ended_email_id'])) {
			$customFieldId = CustomField::getCustomFieldIdFromCode("EVENT_ENDED_EMAIL_SENT", "EVENTS");
			if (empty($customFieldId)) {
				$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "EVENTS");
				$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], "EVENT_ENDED_EMAIL_SENT", "Event Ended Email Sent", $customFieldTypeId, "Event Ended Email Sent");
				$customFieldId = $insertSet['insert_id'];
				executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','date')", $customFieldId);
			}
			$eventTypeCustomFieldId = getFieldFromId("event_type_custom_field_id", "event_type_custom_fields", "event_type_id", $nameValues['primary_id'], "custom_field_id = ?", $customFieldId);
			if (empty($eventTypeCustomFieldId)) {
				executeQuery("insert ignore into event_type_custom_fields (event_type_id,custom_field_id) values (?,?)", $nameValues['primary_id'], $customFieldId);
			}
		}
		return true;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("class_instructor_id", "get_choices", "classInstructorChoices");
		$reminderDays = getPreference("EVENT_REMINDER_DAYS");
		if (empty($reminderDays) || !is_numeric($reminderDays) || $reminderDays <= 0) {
			$reminderDays = 3;
		}
		$this->iDataSource->addColumnControl("reminder_email_id", "help_label", "Sent out " . $reminderDays . " day" . ($reminderDays == 1 ? "" : "s") . " before event");
		$this->iDataSource->addColumnControl("event_type_location_emails", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_type_location_emails", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("event_type_location_emails", "form_label", "Location Specific Emails");
		$this->iDataSource->addColumnControl("event_type_location_emails", "list_table", "event_type_location_emails");

		$this->iDataSource->addColumnControl("event_type_tag_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_type_tag_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("event_type_tag_links", "form_label", "Tags");
		$this->iDataSource->addColumnControl("event_type_tag_links", "links_table", "event_type_tag_links");
		$this->iDataSource->addColumnControl("event_type_tag_links", "control_table", "event_type_tags");

		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $_GET['primary_id'], "client_id is not null");
			if (empty($eventTypeId)) {
				return;
			}
			$resultSet = executeQuery("select * from event_types where event_type_id = ?", $eventTypeId);
			$eventTypeRow = getNextRow($resultSet);
			$originalEventTypeCode = $eventTypeRow['event_type_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($eventTypeRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$eventTypeRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$eventTypeRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newEventTypeId = "";
			$eventTypeRow['description'] .= " Copy";
			while (empty($newEventTypeId)) {
				$eventTypeRow['event_type_code'] = $originalEventTypeCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from event_types where event_type_code = ? and client_id = ?",
					$eventTypeRow['event_type_code'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into event_types values (" . $queryString . ")", $eventTypeRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newEventTypeId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newEventTypeId;
			$subTables = array("event_type_custom_fields", "event_type_qualifications", "event_type_location_emails", "event_type_notifications", "event_type_requirements", "event_type_tag_links");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where event_type_id = ?", $eventTypeId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['event_type_id'] = $newEventTypeId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

    function afterGetRecord(&$returnArray) {
        $customFields = CustomField::getCustomFields("event_types");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            $customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
            if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
                $returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
            }
            $returnArray = array_merge($returnArray, $customFieldData);
        }
    }

    function displayCustomFields() {
        $customFields = CustomField::getCustomFields("event_types");
        foreach ($customFields as $thisCustomField) {
            $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
            echo $customField->getControl(array("basic_form_line" => true));
        }
    }

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $(document).on("tap click", "#_duplicate_button", function () {
                const $primaryId = $("#primary_id");
                if (!empty($primaryId.val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                    }
                }
                return false;
            });
			<?php } ?>
        </script>
		<?php
	}

	function classInstructorChoices($showInactive = false) {
		$classInstructorChoices = array();
		$resultSet = executeQuery("select * from class_instructors join contacts using (contact_id) where class_instructors.client_id = ? order by last_name,first_name", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$classInstructorChoices[$row['class_instructor_id']] = array("key_value" => $row['class_instructor_id'], "description" => getDisplayName($row['contact_id']), "inactive" => $row['inactive'] == 1, "data-assigned_user_id" => $row['user_id']);
			}
		}
		return $classInstructorChoices;
	}
}

$pageObject = new EventTypeMaintenancePage("event_types");
$pageObject->displayPage();
