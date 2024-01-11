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

$GLOBALS['gPageCode'] = "HELPDESKENTRYMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$filters = array();
			$filters['from_date'] = array("form_label" => "Earliest Date Submitted", "where" => "time_submitted >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['to_date'] = array("form_label" => "Latest Date Submitted", "where" => "time_submitted <= '%filter_value% 23:59:59'", "data_type" => "date", "conjunction" => "and");
			$filters['hide_closed'] = array("form_label" => "Hide Closed", "where" => "time_closed is null", "conjunction" => "and");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("help_desk_private_notes", "help_desk_public_notes", "help_desk_entry_activities", "help_desk_entry_list_items", "help_desk_entry_votes", "help_desk_tag_links"));
		$this->iDataSource->addColumnControl("last_activity", "select_value",
			"greatest(coalesce((select max(concat_ws(' ', time_submitted,user_name)) from help_desk_public_notes join users using (user_id) where help_desk_entry_id = help_desk_entries.help_desk_entry_id),''),"
			. "coalesce((select max(concat_ws(' ', time_submitted,user_name)) from help_desk_private_notes join users using (user_id)  where help_desk_entry_id = help_desk_entries.help_desk_entry_id),''),time_submitted)");
		$this->iDataSource->addColumnControl("last_activity", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_activity", "form_label", "Last Activity");
		$this->iDataSource->addColumnControl("assigned_to_display", "data_type", "varchar");
		$this->iDataSource->addColumnControl("assigned_to_display", "select_value", "select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = help_desk_entries.user_id)");
		$this->iDataSource->addColumnControl("assigned_to_display", "form_label", "Assigned To");
		$this->iDataSource->addColumnControl("contact_display", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_display", "select_value", "select concat_ws(' ',first_name,last_name) from contacts where contact_id = help_desk_entries.contact_id");
		$this->iDataSource->addColumnControl("contact_display", "form_label", "Contact");

		$this->iDataSource->addColumnControl("help_desk_tag_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("help_desk_tag_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("help_desk_tag_links", "control_table", "help_desk_tags");
		$this->iDataSource->addColumnControl("help_desk_tag_links", "links_table", "help_desk_tag_links");
		$this->iDataSource->addColumnControl("help_desk_tag_links", "form_label", "Tags");

		$customFieldId = CustomField::getCustomFieldIdFromCode("STORE_LOCATION","HELP_DESK");
		if (!empty($customFieldId)) {
			$this->iDataSource->addColumnControl("store_location", "data_type", "varchar");
			$this->iDataSource->addColumnControl("store_location", "select_value", "select description from locations where location_id = (select text_data from custom_field_data where custom_field_id = " . $customFieldId . " and primary_identifier = help_desk_entries.help_desk_entry_id)");
			$this->iDataSource->addColumnControl("store_location", "form_label", "Store Location");
		}

		$this->iDataSource->addColumnControl("project_milestone_id", "get_choices", "projectMilestoneChoices");
	}

	function projectMilestoneChoices($showInactive = false) {
		$projectMilestoneChoices = array();
		$resultSet = executeQuery("select *,(select description from projects where project_id = project_milestones.project_id) project_description from project_milestones where " .
			"project_id in (select project_id from projects where client_id = ?) order by project_description,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$projectMilestoneChoices[$row['project_milestone_id']] = array("key_value" => $row['project_milestone_id'], "description" => $row['description'], "inactive" => false, "optgroup" => $row['project_description']);
		}
		freeResult($resultSet);
		return $projectMilestoneChoices;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_custom_data":
				$returnArray['help_desk_type_id'] = array("data_value" => $_GET['help_desk_type_id']);
				$returnArray['primary_id'] = array("data_value" => $_GET['primary_id']);
				$this->afterGetRecord($returnArray);
				unset($returnArray['help_desk_type_id']);
				unset($returnArray['primary_id']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "help_desk_type_id",
				$returnArray['help_desk_type_id']['data_value'], "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($helpDeskTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
		$returnArray['custom_data'] = array("data_value" => ob_get_clean());
		foreach ($customFields as $thisCustomField) {
			$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "help_desk_type_id",
				$returnArray['help_desk_type_id']['data_value'], "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($helpDeskTypeCustomFieldId)) {
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

	function onLoadJavascript() {
		?>
        <script>
            $("#help_desk_type_id").change(function () {
                $("#custom_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_data&help_desk_type_id=" + $(this).val() + "&primary_id=" + $("#primary_id").val(), function (returnArray) {
                        if ("custom_data" in returnArray && "data_value" in returnArray['custom_data']) {
                            $("#custom_data").html(returnArray['custom_data']['data_value']);
                            afterGetRecord(returnArray);
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            var dataArray = new Object();

            function afterGetRecord(returnArray) {
                $("#custom_data .datepicker").datepicker({
                    showOn: "button",
                    buttonText: "<span class='fad fa-calendar-alt'></span>",
                    constrainInput: false,
                    dateFormat: "mm/dd/y",
                    yearRange: "c-100:c+10"
                });
                $("#custom_data .required-label").append("<span class='required-tag'>*</span>");
                $("#custom_data a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                dataArray = returnArray;
                setTimeout("setCustomData()", 100);
            }

            function setCustomData() {
                if ("select_values" in dataArray) {
                    for (var i in dataArray['select_values']) {
                        if (!$("#" + i).is("select")) {
                            continue;
                        }
                        $("#" + i + " option").each(function () {
                            if ($(this).data("inactive") == "1") {
                                $(this).remove();
                            }
                        });
                        for (var j in dataArray['select_values'][i]) {
                            if ($("#" + i + " option[value='" + dataArray['select_values'][i][j]['key_value'] + "']").length == 0) {
                                var inactive = ("inactive" in dataArray['select_values'][i][j] ? dataArray['select_values'][i][j]['inactive'] : "0");
                                $("#" + i).append("<option data-inactive='" + inactive + "' value='" + dataArray['select_values'][i][j]['key_value'] + "'>" + dataArray['select_values'][i][j]['description'] + "</option>");
                            }
                        }
                    }
                }
                for (var i in dataArray) {
                    if (typeof dataArray[i] == "object" && "data_value" in dataArray[i]) {
                        if ($("input[type=radio][name='" + i + "']").length > 0) {
                            $("input[type=radio][name='" + i + "']").prop("checked", false);
                            $("input[type=radio][name='" + i + "'][value='" + dataArray[i]['data_value'] + "']").prop("checked", true);
                        } else if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", dataArray[i].data_value != 0);
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", dataArray[i].data_value).css("display", (dataArray[i].data_value == "" ? "none" : "inline"));
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            for (var j in dataArray[i].data_value) {
                                addEditableListRow(i, dataArray[i]['data_value'][j]);
                            }
                        } else {
                            $("#" + i).val(dataArray[i].data_value);
                        }
                        if ("crc_value" in dataArray[i]) {
                            $("#" + i).data("crc_value", dataArray[i].crc_value);
                        } else {
                            $("#" + i).removeData("crc_value");
                        }
                    }
                }
                $(".selector-value-list").trigger("change");
                $(".multiple-dropdown-values").trigger("change");
            }
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_entries", "help_desk_entry_id", $nameValues['primary_id']);
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "help_desk_type_id",
				$helpDeskTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($helpDeskTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}

$pageObject = new ThisPage("help_desk_entries");
$pageObject->displayPage();
