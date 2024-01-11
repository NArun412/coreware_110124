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

$GLOBALS['gPageCode'] = "PROJECTMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		$urlAliasTypeId = getFieldFromId("url_alias_type_id", "url_alias_types", "url_alias_type_code", "project-management");
		$pageId = getFieldFromId("page_id", "pages", "script_filename", "project.php");
		if (!empty($pageId) && empty($urlAliasTypeId)) {
			$tableId = getFieldFromId("table_id", "tables", "table_name", "projects");
			executeQuery("insert into url_alias_types (client_id,url_alias_type_code,description,table_id,page_id,parameter_name) values (?,?,?,?,?,?)",
				$GLOBALS['gClientId'], "project-management", "Project", $tableId, $pageId, "id");
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "inactive = 0 and date_completed is null", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("project_files", "project_images", "project_log", "project_member_user_groups", "project_member_users", "project_milestones", "project_notification_exclusions", "project_notifications"));
		$this->iDataSource->addColumnControl("project_link", "data_type", "varchar");
		$this->iDataSource->addColumnControl("project_link", "form_label", "Project Page");
		$this->iDataSource->addColumnControl("project_link", "dont_escape", "true");
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");

		$this->iDataSource->addColumnControl("project_type_id", "not_editable", true);

		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId'] . " or leader_user_id = " . $GLOBALS['gUserId'] .
				" or project_id in (select project_id from project_member_users where user_id = " .
				$GLOBALS['gUserId'] . ") or project_id in (select project_id from project_member_user_groups where user_group_id in " .
				"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . "))");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_custom_data":
				$returnArray = $this->getCustomFieldInformation($_GET['primary_id'], $_GET['project_type_id']);
				ajaxResponse($returnArray);
				break;
			case "get_milestones":
				$projectTypeId = getFieldFromId("project_type_id", "project_types", "project_type_id", $_GET['project_type_id']);
				$returnArray['milestones'] = array();
				if (!empty($projectTypeId)) {
					$resultSet = executeQuery("select * from project_type_milestones where project_type_id = ? order by sequence_number,project_type_milestone_id", $projectTypeId);
					while ($row = getNextRow($resultSet)) {

						$thisRow = array();
						$thisRow['description'] = array("data_value" => $row['description'], "crc_value" => getCrcValue($row['description']));
						$thisRow['project_milestone_code'] = array("data_value" => $row['project_milestone_code'], "crc_value" => getCrcValue($row['project_milestone_code']));
						$thisRow['days_before'] = array("data_value" => $row['days_before'], "crc_value" => getCrcValue($row['days_before']));
						$thisRow['notes'] = array("data_value" => $row['notes'], "crc_value" => getCrcValue($row['notes']));
						$returnArray['milestones'][] = $thisRow;
					}
				}
				$ticketCount = 0;
				$resultSet = executeQuery("select count(*) from project_type_help_desk_entries where project_type_id = ?", $projectTypeId);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > 0) {
						$returnArray['_ticket_creation_message'] = $row['count(*)'] . " ticket" . ($row['count(*)'] == 1 ? "" : "s") . " will be created when project is saved.";
					}
				}

				ajaxResponse($returnArray);
				break;
		}
	}

	function getCustomFieldInformation($projectId, $projectTypeId) {
		$customFieldInformation = array();
		ob_start();
		$customFields = CustomField::getCustomFields("projects");
		foreach ($customFields as $thisCustomField) {
			$projectTypeCustomFieldId = getFieldFromId("project_type_custom_field_id", "project_type_custom_fields", "project_type_id",
				$projectTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($projectTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
		$customFieldInformation['custom_data'] = array("data_value" => ob_get_clean());
		foreach ($customFields as $thisCustomField) {
			$projectTypeCustomFieldId = getFieldFromId("project_type_custom_field_id", "project_type_custom_fields", "project_type_id",
				$projectTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($projectTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($projectId);
			if (is_array($customFieldInformation['select_values']) && is_array($customFieldData['select_values'])) {
				$customFieldData['select_values'] = array_merge($customFieldInformation['select_values'], $customFieldData['select_values']);
			}
			$customFieldInformation = array_merge($customFieldInformation, $customFieldData);
		}
		return $customFieldInformation;
	}

	function internalCSS() {
		?>
        <style>
            .click-ticket {
                cursor: pointer;
            }

            .click-ticket:hover {
                color: rgb(0, 100, 200);
            }

            .message-reply:before {
                content: "\21AA\00A0"
            }

            .log-date {
                font-weight: bold;
            }

            #ticket_list li, #ticket_list span {
                font-size: 12px;
                padding-top: 5px;
            }

            #project_link {
                margin-left: 20px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#project_type_id").change(function () {
                $("#custom_data").html("");
                $("#_ticket_creation_message").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_data&project_type_id=" + $(this).val() + "&primary_id=" + $("#primary_id").val(), function(returnArray) {
                        if ("custom_data" in returnArray && "data_value" in returnArray['custom_data']) {
                            $("#custom_data").html(returnArray['custom_data']['data_value']);
                            afterGetRecord(returnArray);
                        }
                    });
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_milestones&project_type_id=" + $(this).val(), function(returnArray) {
                        $("#_project_milestones_table").find(".editable-list-data-row").remove();
                        if ("milestones" in returnArray) {
                            for (var i in returnArray['milestones']) {
                                addEditableListRow("project_milestones", returnArray['milestones'][i]);
                            }
                        }
                        if ("_ticket_creation_message" in returnArray) {
                            $("#_ticket_creation_message").html(returnArray['_ticket_creation_message']);
                        }
                    });
                }
            });
            $(document).on("click", "#project_link", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save the project first");
                    return false;
                }
                goToLink("/project.php?id=" + $("#primary_id").val());
                return false;
            });
            $(document).on("tap click", ".click-ticket", function () {
                var ticketId = $(this).data("help_desk_entry_id");
                if (ticketId != "") {
                    window.open("/helpdeskdashboard.php?id=" + ticketId);
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            var dataArray = [];

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
                    for (const i in dataArray['select_values']) {
                        if (!$("#" + i).is("select")) {
                            continue;
                        }
                        $("#" + i + " option").each(function () {
                            if ($(this).data("inactive") === "1") {
                                $(this).remove();
                            }
                        });
                        for (const j in dataArray['select_values'][i]) {
                            if ($("#" + i + " option[value='" + dataArray['select_values'][i][j]['key_value'] + "']").length === 0) {
                                const inactive = ("inactive" in dataArray['select_values'][i][j] ? dataArray['select_values'][i][j]['inactive'] : "0");
                                $("#" + i).append("<option data-inactive='" + inactive + "' value = '" + dataArray['select_values'][i][j]['key_value'] + "'>" + dataArray['select_values'][i][j]['description'] + "</option>");
                            }
                        }
                    }
                }
                for (const i in dataArray) {
                    if (i.substr(0, 13) != "custom_field_") {
                        continue;
                    }
                    if (typeof dataArray[i] == "object" && "data_value" in dataArray[i]) {
                        if ($("input[type=radio][name='" + i + "']").length > 0) {
                            $("input[type=radio][name='" + i + "']").prop("checked", false);
                            $("input[type=radio][name='" + i + "'][value='" + dataArray[i]['data_value'] + "']").prop("checked", true);
                        } else if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", (!empty(dataArray[i].data_value)));
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", dataArray[i].data_value).css("display", (empty(dataArray[i].data_value) ? "none" : "inline"));
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            for (const j in dataArray[i].data_value) {
                                addEditableListRow(i, dataArray[i]['data_value'][j]);
                            }
                        } else {
                            $("#" + i).val(dataArray[i].data_value);
                        }
                        if ("crc_value" in dataArray[i]) {
                            $("#" + i).data("crc_value", dataArray[i]['crc_value']);
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

	function beforeDeleteRecord($primaryId) {
		executeQuery("update project_log set parent_log_id = null where project_id = ?", $primaryId);
		$resultSet = executeQuery("select count(*) from help_desk_entries where project_id = ?", $primaryId);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] > 0) {
				return "Help Desk tickets exist for this project so it cannot be deleted";
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$customFieldInformation = $this->getCustomFieldInformation($returnArray['primary_id']['data_value'], $returnArray['project_type_id']['data_value']);
		if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldInformation)) {
			$returnArray['select_values'] = $customFieldInformation['select_values'] = array_merge($returnArray['select_values'], $customFieldInformation['select_values']);
		}
		$returnArray = array_merge($returnArray, $customFieldInformation);

		ob_start();
		$this->getMessageBoards($returnArray['primary_id']['data_value']);
		$returnArray['message_board'] = array("data_value" => ob_get_clean());
		$ticketList = "<ul>";
		$resultSet = executeQuery("select * from help_desk_entries where project_id = ? and project_milestone_id is null order by date_due", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$ticketList .= $this->formatTicket($row);
		}
		$milestoneSet = executeQuery("select * from project_milestones where project_id = ? order by days_before desc,project_milestone_id", $returnArray['primary_id']['data_value']);
		while ($milestoneRow = getNextRow($milestoneSet)) {
			$resultSet = executeQuery("select * from help_desk_entries where project_id = ? and project_milestone_id = ? order by date_due", $returnArray['primary_id']['data_value'], $milestoneRow['project_milestone_id']);
			if ($resultSet['row_count'] > 0) {
				$ticketList .= "<li><span class='highlighted-text'>Milestone - " . $milestoneRow['description'] . "</span><ul>";
				while ($row = getNextRow($resultSet)) {
					$ticketList .= $this->formatTicket($row);
				}
				$ticketList .= "</ul></li>";
			}
		}
		$returnArray['ticket_list'] = array("data_value" => $ticketList);
		return true;
	}

	function getMessageBoards($projectId, $parentLogId = "", $level = 0) {
		$resultSet = executeQuery("select * from project_log where project_id = ? and parent_log_id <=> ? order by log_id", $projectId, $parentLogId);
		while ($row = getNextRow($resultSet)) {
			?>
            <p class="<?= ($level == 0 ? "" : "message-reply") ?>" style="margin-left: <?= ($level * 20) ?>px;"><span class='log-date'><?= date("m/d/Y g:i a", strtotime($row['log_time'])) ?><?= (empty($row['user_id']) ? "" : " by " . getUserDisplayName($row['user_id'])) ?></span>: <?= htmlText($row['content']) ?></p>
			<?php
			$this->getMessageBoards($projectId, $row['log_id'], ($level + 1));
		}
	}

	function formatTicket($row) {
		return "<li class='click-ticket' data-help_desk_entry_id='" . $row['help_desk_entry_id'] . "'>" . $row['description'] .
			(empty($row['assigned_user_id']) ? "" : ", assigned to " . getUserDisplayName($row['assigned_user_id'])) .
			(empty($row['date_due']) ? "" : ", due on " . date("m/d/Y", strtotime($row['date_due']))) .
			(empty($row['time_closed']) ? "" : ", completed on " . date("m/d/Y", strtotime($row['time_closed']))) . "</li>";
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($nameValues['primary_id'])) {
			return true;
		}
		$projectId = $nameValues['primary_id'];
		$content = "";
		$newUserIds = explode(",", $nameValues['project_users']);
		foreach ($newUserIds as $key => $value) {
			if (empty($value)) {
				unset($newUserIds[$key]);
			}
		}
		$removedUserIds = array();
		$resultSet = executeQuery("select user_id from project_member_users where project_id = ?", $projectId);
		while ($row = getNextRow($resultSet)) {
			if (($key = array_search($row['user_id'], $newUserIds)) !== false) {
				unset($newUserIds[$key]);
			} else {
				$removedUserIds[] = $row['user_id'];
			}
		}
		if (!empty($newUserIds) || !empty($removedUserIds)) {
			$content = "Made changes to the user membership: ";
			if (!empty($newUserIds)) {
				$userList = "";
				foreach ($newUserIds as $userId) {
					if (!empty($userList)) {
						$userList .= ", ";
					}
					$userList .= getUserDisplayName($userId);
				}
				$content .= "added " . $userList;
			}
			if (!empty($removedUserIds)) {
				if (!empty($newUserIds)) {
					$content .= " and ";
				}
				$userList = "";
				foreach ($removedUserIds as $userId) {
					if (!empty($userList)) {
						$userList .= ", ";
					}
					$userList .= getUserDisplayName($userId);
				}
				$content .= "removed " . $userList;
			}
		}
		if (!empty($content)) {
			$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
				$projectId, $GLOBALS['gUserId'], $content);
		}
		$content = "";
		$newUserGroupIds = explode(",", $nameValues['project_user_groups']);
		foreach ($newUserGroupIds as $key => $value) {
			if (empty($value)) {
				unset($newUserGroupIds[$key]);
			}
		}
		$removedUserGroupIds = array();
		$resultSet = executeQuery("select user_group_id from project_member_user_groups where project_id = ?", $projectId);
		while ($row = getNextRow($resultSet)) {
			if (($key = array_search($row['user_group_id'], $newUserGroupIds)) !== false) {
				unset($newUserGroupIds[$key]);
			} else {
				$removedUserGroupIds[] = $row['user_group_id'];
			}
		}
		if (!empty($newUserGroupIds) || !empty($removedUserGroupIds)) {
			$content = "Made changes to the user group membership: ";
			if (!empty($newUserGroupIds)) {
				$userGroupList = "";
				foreach ($newUserGroupIds as $userGroupId) {
					if (!empty($userGroupList)) {
						$userGroupList .= ", ";
					}
					$userGroupList .= getFieldFromId("description", "user_groups", "user_group_id", $userGroupId);
				}
				$content .= "added " . $userGroupList;
			}
			if (!empty($removedUserGroupIds)) {
				if (!empty($newUserGroupIds)) {
					$content .= " and ";
				}
				$userGroupList = "";
				foreach ($removedUserGroupIds as $userGroupId) {
					if (!empty($userGroupList)) {
						$userGroupList .= ", ";
					}
					$userGroupList .= getUserDisplayName($userId);
				}
				$content .= "removed " . getFieldFromId("description", "user_groups", "user_group_id", $userGroupId);
			}
		}
		if (!empty($content)) {
			$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
				$projectId, $GLOBALS['gUserId'], $content);
		}
		$editablePrefix = "project_milestones_";
		$primaryKey = "project_milestone_id";
		$checkFields = array("description" => array("description" => "Description", "data_type" => "varchar"),
			"days_before" => array("description" => "Days Before Due Date", "data_type" => "int"),
			"date_completed" => array("description" => "Date Completed", "data_type" => "date"),
			"notes" => array("description" => "Notes", "data_type" => "text"));
		foreach ($nameValues as $fieldName => $fieldData) {
			$content = "";
			if (substr($fieldName, 0, strlen($editablePrefix . $primaryKey . "-")) == ($editablePrefix . $primaryKey . "-")) {
				$rowNumber = substr($fieldName, strlen($editablePrefix . $primaryKey . "-"));
				$primaryId = $fieldData;
				if (!empty($primaryId)) {
					$resultSet = executeQuery("select * from project_milestones where project_milestone_id = ?", $primaryId);
					if (!$oldRow = getNextRow($resultSet)) {
						$oldRow = array();
					}
				} else {
					$oldRow = array();
				}
				if (empty($oldRow)) {
					$content = "Added milestone '" . $nameValues[$editablePrefix . "description-" . $rowNumber] . "'" .
						" due to be completed " . $nameValues[$editablePrefix . "days_before-" . $rowNumber] . " before the project due date";
				} else {
					$changedParts = array();
					foreach ($checkFields as $checkField => $fieldInfo) {
						switch ($fieldInfo['data_type']) {
							case "date":
								$oldValue = (empty($oldRow[$checkField]) ? "" : date("m/d/Y", strtotime($oldRow[$checkField])));
								$newValue = (empty($nameValues[$editablePrefix . $checkField . "-" . $rowNumber]) ? "" : date("m/d/Y", strtotime($nameValues[$editablePrefix . $checkField . "-" . $rowNumber])));
								break;
							default:
								$oldValue = $oldRow[$checkField];
								$newValue = $nameValues[$editablePrefix . $checkField . "-" . $rowNumber];
								break;
						}
						if ($oldValue != $newValue) {
							$changedParts[] = (empty($oldValue) ? "set" : (empty($newValue) ? "removed" : "changed")) .
								" the " . $fieldInfo['description'] . (empty($newValue) || $fieldInfo['data_type'] == "text" ? "" : " to '" .
									$nameValues[$editablePrefix . $checkField . "-" . $rowNumber] . "'");
						}
					}
					if (!empty($changedParts)) {
						$addedCount = 0;
						foreach ($changedParts as $changeText) {
							$addedCount++;
							if (!empty($content)) {
								if ($addedCount == count($changedParts)) {
									$content .= " and ";
								} else {
									$content .= ", ";
								}
							}
							$content .= $changeText;
						}
						$content = "Updated milestone '" . $oldRow['description'] . "': " . $content;
					}
				}
			}
			if (!empty($content)) {
				executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
					$projectId, $GLOBALS['gUserId'], $content);
			}
		}
		$editablePrefix = "project_files_";
		$primaryKey = "project_file_id";
		$checkFields = array("description" => array("description" => "Description", "data_type" => "varchar"));
		foreach ($nameValues as $fieldName => $fieldData) {
			$content = "";
			if (substr($fieldName, 0, strlen($editablePrefix . $primaryKey . "-")) == ($editablePrefix . $primaryKey . "-")) {
				$rowNumber = substr($fieldName, strlen($editablePrefix . $primaryKey . "-"));
				$primaryId = $fieldData;
				if (!empty($primaryId)) {
					$resultSet = executeQuery("select * from project_files where project_file_id = ?", $primaryId);
					if (!$oldRow = getNextRow($resultSet)) {
						$oldRow = array();
					}
				} else {
					$oldRow = array();
				}
				if (empty($oldRow)) {
					$content = "Added project file '" . $nameValues[$editablePrefix . "description-" . $rowNumber] . "'";
				} else {
					$changedParts = array();
					foreach ($checkFields as $checkField => $fieldInfo) {
						switch ($fieldInfo['data_type']) {
							case "date":
								$oldValue = (empty($oldRow[$checkField]) ? "" : date("m/d/Y", strtotime($oldRow[$checkField])));
								$newValue = (empty($nameValues[$editablePrefix . $checkField . "-" . $rowNumber]) ? "" : date("m/d/Y", strtotime($nameValues[$editablePrefix . $checkField . "-" . $rowNumber])));
								break;
							default:
								$oldValue = $oldRow[$checkField];
								$newValue = $nameValues[$editablePrefix . $checkField . "-" . $rowNumber];
								break;
						}
						if ($oldValue != $newValue) {
							$changedParts[] = (empty($oldValue) ? "set" : (empty($newValue) ? "removed" : "changed")) .
								" the " . $fieldInfo['description'] . (empty($newValue) || $fieldInfo['data_type'] == "text" ? "" : " to '" .
									$nameValues[$editablePrefix . $checkField . "-" . $rowNumber] . "'");
						}
					}
					if (array_key_exists($editablePrefix . "file_id-" . $rowNumber . "_file", $_FILES) && !empty($_FILES[$editablePrefix . "file_id-" . $rowNumber . "_file"]['name'])) {
						$changedParts[] = "Updated the file";
					}
					if (!empty($changedParts)) {
						$addedCount = 0;
						foreach ($changedParts as $changeText) {
							$addedCount++;
							if (!empty($content)) {
								if ($addedCount == count($changedParts)) {
									$content .= " and ";
								} else {
									$content .= ", ";
								}
							}
							$content .= $changeText;
						}
						$content = "Updated the project file '" . $oldRow['description'] . "': " . $content;
					}
				}
			}
			if (!empty($content)) {
				$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
					$projectId, $GLOBALS['gUserId'], $content);
			}
		}
		if (!empty($nameValues["_" . $editablePrefix . "delete_ids"])) {
			$deleteIds = explode(",", $nameValues["_" . $editablePrefix . "delete_ids"]);
			foreach ($deleteIds as $deleteId) {
				$resultSet = executeQuery("select description from project_files where project_file_id = ?", $deleteId);
				if ($row = getNextRow($resultSet)) {
					$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
						$projectId, $GLOBALS['gUserId'], "Deleted project file '" . $row['description'] . "'.");
				}
			}
		}
		$editablePrefix = "project_images_";
		$primaryKey = "project_image_id";
		$checkFields = array("description" => array("form_label" => "Description", "data_type" => "varchar"));
		foreach ($nameValues as $fieldName => $fieldData) {
			$content = "";
			if (substr($fieldName, 0, strlen($editablePrefix . $primaryKey . "-")) == ($editablePrefix . $primaryKey . "-")) {
				$rowNumber = substr($fieldName, strlen($editablePrefix . $primaryKey . "-"));
				$primaryId = $fieldData;
				if (!empty($primaryId)) {
					$resultSet = executeQuery("select * from project_images where project_image_id = ?", $primaryId);
					if (!$oldRow = getNextRow($resultSet)) {
						$oldRow = array();
					}
				} else {
					$oldRow = array();
				}
				if (empty($oldRow)) {
					$content = "Added project image '" . $nameValues[$editablePrefix . "description-" . $rowNumber] . "'";
				} else {
					$changedParts = array();
					foreach ($checkFields as $checkField => $fieldInfo) {
						switch ($fieldInfo['data_type']) {
							case "date":
								$oldValue = (empty($oldRow[$checkField]) ? "" : date("m/d/Y", strtotime($oldRow[$checkField])));
								$newValue = (empty($nameValues[$editablePrefix . $checkField . "-" . $rowNumber]) ? "" : date("m/d/Y", strtotime($nameValues[$editablePrefix . $checkField . "-" . $rowNumber])));
								break;
							default:
								$oldValue = $oldRow[$checkField];
								$newValue = $nameValues[$editablePrefix . $checkField . "-" . $rowNumber];
								break;
						}
						if ($oldValue != $newValue) {
							$changedParts[] = (empty($oldValue) ? "set" : (empty($newValue) ? "removed" : "changed")) .
								" the " . $fieldInfo['description'] . (empty($newValue) || $fieldInfo['data_type'] == "text" ? "" : " to '" .
									$nameValues[$editablePrefix . $checkField . "-" . $rowNumber] . "'");
						}
					}
					if (array_key_exists($editablePrefix . "image_id-" . $rowNumber . "_file", $_FILES) && !empty($_FILES[$editablePrefix . "image_id-" . $rowNumber . "_file"]['name'])) {
						$changedParts[] = "Updated the image";
					}
					if (!empty($changedParts)) {
						$addedCount = 0;
						foreach ($changedParts as $changeText) {
							$addedCount++;
							if (!empty($content)) {
								if ($addedCount == count($changedParts)) {
									$content .= " and ";
								} else {
									$content .= ", ";
								}
							}
							$content .= $changeText;
						}
						$content = "Updated the project image '" . $oldRow['description'] . "': " . $content;
					}
				}
			}
			if (!empty($content)) {
				$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
					$projectId, $GLOBALS['gUserId'], $content);
			}
		}
		if (!empty($nameValues["_" . $editablePrefix . "delete_ids"])) {
			$deleteIds = explode(",", $nameValues["_" . $editablePrefix . "delete_ids"]);
			foreach ($deleteIds as $deleteId) {
				$resultSet = executeQuery("select description from project_images where project_image_id = ?", $deleteId);
				if ($row = getNextRow($resultSet)) {
					$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
						$projectId, $GLOBALS['gUserId'], "Deleted project image '" . $row['description'] . "'.");
				}
			}
		}
		return true;
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("projects");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$customFields = CustomField::getCustomFields("projects");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function afterSaveDone($nameValues) {
		$helpDeskData = array();
		$helpDeskData['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
		$helpDeskData['project_id'] = $nameValues['primary_id'];
		$resultSet = executeQuery("select * from project_type_help_desk_entries where project_type_id = ?", $nameValues['project_type_id']);
		while ($row = getNextRow($resultSet)) {
			$helpDeskData['description'] = $row['description'];
			$helpDeskData['help_desk_type_id'] = $row['help_desk_type_id'];
			if (!empty($row['project_milestone_code'])) {
				$helpDeskData['project_milestone_id'] = getFieldFromId("project_milestone_id", "project_milestones", "project_id", $nameValues['primary_id'], "project_milestone_code = ?", $row['project_milestone_code']);
			} else {
				$helpDeskData['project_milestone_id'] = "";
			}
			$helpDeskData['date_due'] = date("Y-m-d", strtotime($nameValues['start_date'] . " +" . $row['due_days'] . " days"));
			$helpDeskData['user_id'] = $row['user_id'];
			$helpDeskData['user_group_id'] = $row['user_group_id'];
			$helpDeskData['content'] = $row['content'];
			$helpDeskEntry = new HelpDesk();
			$helpDeskEntry->addSubmittedData($helpDeskData);
			if (!$helpDeskEntry->save()) {
				$GLOBALS['gPrimaryDatabase']->logError($helpDeskEntry->getErrorMessage());
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("projects");
$pageObject->displayPage();
