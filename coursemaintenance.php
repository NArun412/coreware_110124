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

$GLOBALS['gPageCode'] = "COURSEMAINT";
require_once "shared/startup.inc";

class CourseMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
					"disabled" => false)));
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("course_requirements", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("course_requirements", "control_key", "required_course_id");
		$this->iDataSource->addColumnControl("course_requirements", "control_table", "courses");
		$this->iDataSource->addColumnControl("course_requirements", "data_type", "custom");
		$this->iDataSource->addColumnControl("course_requirements", "form_label", "Course Prerequisites");
		$this->iDataSource->addColumnControl("course_requirements", "links_table", "course_requirements");

		$this->iDataSource->addColumnControl("course_lessons", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("course_lessons", "data_type", "custom");
		$this->iDataSource->addColumnControl("course_lessons", "form_label", "Lessons");
		$this->iDataSource->addColumnControl("course_lessons", "list_table", "course_lessons");
		$this->iDataSource->addColumnControl("course_lessons", "sort_order", "sequence_number");

		$this->iDataSource->addColumnControl("maximum_days", "help_label", "course_requirements");
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$courseId = getFieldFromId("course_id", "courses", "course_id", $_GET['primary_id'], "client_id is not null");
			if (empty($courseId)) {
				return;
			}
			$resultSet = executeQuery("select * from courses where course_id = ?", $courseId);
			$courseRow = getNextRow($resultSet);
			$originalCourseCode = $courseRow['course_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($courseRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$courseRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$courseRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newCourseId = "";
			$courseRow['description'] .= " Copy";
			while (empty($newCourseId)) {
				$courseRow['course_code'] = $originalCourseCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from courses where course_code = ? and client_id = ?",
					$courseRow['course_code'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into courses values (" . $queryString . ")", $courseRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newCourseId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newCourseId;
			$subTables = array("course_lessons", "course_requirements");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where course_id = ?", $courseId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['course_id'] = $newCourseId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
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

	function afterSaveChanges($nameValues, $actionPerformed) {
		$customFields = CustomField::getCustomFields("courses");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$customFields = CustomField::getCustomFields("courses");
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
		$customFields = CustomField::getCustomFields("courses");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("courses");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}

$pageObject = new CourseMaintenancePage("courses");
$pageObject->displayPage();
