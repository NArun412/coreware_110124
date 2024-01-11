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

$GLOBALS['gPageCode'] = "LESSONMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("label" => getLanguageText("Duplicate"),
			"disabled" => false)));
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_template_css":
				$returnArray['css_content'] = "<style>" . getFieldFromId("css_content", "templates", "template_id", $_GET['template_id']) . "</style>";
				$returnArray['debug'] = true;
				ajaxResponse($returnArray);
				break;
		}
	}

	function mediaChoices($showInactive = false) {
		$mediaChoices = array();
		$resultSet = executeQuery("select *,(select description from media_series where media_series_id = media.media_series_id) series_description from media where client_id = ? order by series_description,sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$mediaChoices[$row['media_id']] = array("key_value" => $row['media_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "optgroup" => $row['series_description']);
		}
		return $mediaChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnLikeColumn("template_id", "pages", "template_id");
		$this->iDataSource->addColumnControl("template_id", "help_label", "View Lesson with CSS from this Template");

		$this->iDataSource->addColumnControl("minimum_time", "minimum_value", "0");
		$this->iDataSource->addColumnControl("content_type", "data_type", "select");
		$this->iDataSource->addColumnControl("content_type", "form_label", "Content Type");
		$this->iDataSource->addColumnControl("content_type", "no_empty_option", true);
		$this->iDataSource->addColumnControl("content_type", "choices", array("content" => "HTML", "media_id" => "Video", "pdf_file_id" => "PDF"));
		$this->iDataSource->addColumnControl("content", "form_line_classes", "content-type");
		$this->iDataSource->addColumnControl("content", "wysiwyg", true);
		$this->iDataSource->addColumnControl("pdf_file_id", "form_line_classes", "content-type");
		$this->iDataSource->addColumnControl("media_id", "form_line_classes", "content-type");
		$this->iDataSource->addColumnControl("media_id", "get_choices", "mediaChoices");
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$lessonId = getFieldFromId("lesson_id", "lessons", "lesson_id", $_GET['primary_id'], "client_id is not null");
			if (empty($lessonId)) {
				return;
			}
			$resultSet = executeQuery("select * from lessons where lesson_id = ?", $lessonId);
			$lessonRow = getNextRow($resultSet);
			$queryString = "";
			foreach ($lessonRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$lessonRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$lessonRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newLessonId = "";
			$lessonRow['description'] .= " Copy";
			$resultSet = executeQuery("insert into lessons values (" . $queryString . ")", $lessonRow);
			$newLessonId = $resultSet['insert_id'];
			$_GET['primary_id'] = $newLessonId;
			$subTables = array("lesson_assignments");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where lesson_id = ?", $lessonId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['lesson_id'] = $newLessonId;
					$insertSet = executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$customFields = CustomField::getCustomFields("lessons");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		if ($nameValues['content_type'] == "pdf_file_id") {
			executeQuery("update lessons set media_id = null,content = null where lesson_id = ?", $nameValues['primary_id']);
		} else if ($nameValues['content_type'] == "media_id") {
			executeQuery("update lessons set pdf_file_id = null,content = null where lesson_id = ?", $nameValues['primary_id']);
		} else {
			executeQuery("update lessons set media_id = null,pdf_file_id = null where lesson_id = ?", $nameValues['primary_id']);
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$contentType = "content";
		if (!empty($returnArray['pdf_file_id']['data_value'])) {
			$contentType = "pdf_file_id";
		} else if (!empty($returnArray['media_id']['data_value'])) {
			$contentType = "media_id";
		}
		$returnArray['content_type'] = array("data_value" => $contentType, "crc_value" => getCrcValue($contentType));
		$customFields = CustomField::getCustomFields("lessons");
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
		$customFields = CustomField::getCustomFields("lessons");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("lessons");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#view_lesson", function () {
                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
                $("#_view_lesson_dialog").html("");
                if (!empty($("#template_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_template_css&template_id=" + $("#template_id").val(), function(returnArray) {
                        $("#_view_lesson_dialog").html(returnArray['css_content']);
                        $("#_view_lesson_dialog").append($("#content").val());
                        $('#_view_lesson_dialog').dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1000,
                            title: 'View Lesson',
                            close: function (event, ui) {
                                $("#_view_lesson_dialog").html("");
                            },
                            buttons: {
                                Cancel: function (event) {
                                    $("#_view_lesson_dialog").html("");
                                    $("#_view_lesson_dialog").dialog('close');
                                }
                            }
                        });
                    });
                } else {
                    $("#_view_lesson_dialog").append($("#content").val());
                    $('#_view_lesson_dialog').dialog({
                        closeOnEscape: true,
                        draggable: true,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 1000,
                        title: 'View Lesson',
                        buttons: {
                            Cancel: function (event) {
                                $("#_view_lesson_dialog").dialog('close');
                            }
                        }
                    });
                }
                return false;
            });
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $(document).on("tap click", "#_duplicate_button", function () {
                if ($("#primary_id").val() != "") {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                    }
                }
                return false;
            });
			<?php } ?>
            $("#content_type").change(function () {
                $(".content-type").addClass("hidden");
                $("#_" + $(this).val() + "_row").removeClass("hidden");
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#content_type").trigger("change");
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if ($("#primary_id").val() == "") {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
            }
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div class='dialog-box' id='_view_lesson_dialog'>
        </div>
		<?php
	}
}

$pageObject = new ThisPage("lessons");
$pageObject->displayPage();
