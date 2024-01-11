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

$GLOBALS['gPageCode'] = "SYSTEMNOTICES";
$GLOBALS['gIgnoreNotices'] = true;
$GLOBALS['gPreemptivePage'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		$this->iDataSource->addColumnControl("subject", "readonly", "true");
		$this->iDataSource->addColumnControl("creator_user_id", "readonly", "true");
		$this->iDataSource->addColumnControl("creator_user_id", "get_choices", "userChoices");
		$this->iDataSource->addColumnControl("creator_user_id", "form_label", "Created By");
		$this->iDataSource->addColumnControl("time_submitted", "form_label", "Created On");
		$this->iDataSource->addColumnControl("time_submitted", "not_null", "false");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("subject", "content", "creator_user_id", "time_submitted"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "save"));
			$filters = array();
			$filters['hide_deleted'] = array("form_label" => "Hide Deleted", "where" => "system_notice_id not in (select system_notice_id from system_notice_users where " .
				"user_id = " . $GLOBALS['gUserId'] . " and time_deleted is not null)", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_unread", "Mark Selected Unread");
		}
	}

	function beforeList() {
		?>
        <h2>Bold items in the list are unread system messages. Click on each to read them.</h2>
		<?php
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("require_acceptance", "data_type", "hidden");
		$this->iDataSource->addColumnControl("acknowledge_acceptance", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("acknowledge_acceptance", "form_label", "I acknowledge that I have read and accept this system notice and its contents.");
		$this->iDataSource->addColumnControl("acknowledge_acceptance", "not_null", true);

		$this->iDataSource->setFilterWhere("(start_time is null or start_time <= current_time) and (end_time is null or end_time >= current_time) and (all_user_access = 1 or " .
			"system_notice_id in (select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . ")" .
			(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ? "" : " or full_client_access = 1") . ")");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "acknowledge_notice":
				$systemNoticeId = getFieldFromId("system_notice_id", "system_notices", "system_notice_id", $_GET['system_notice_id']);
				if (empty($systemNoticeId)) {
					$returnArray['error_message'] = "Invalid System Notice";
				} else {
					$systemNoticeUserId = getFieldFromId("system_notice_user_id", "system_notice_users", "system_notice_id", $systemNoticeId, "user_id = ?", $GLOBALS['gUserId']);
					if (empty($systemNoticeUserId)) {
						executeQuery("insert into system_notice_users (system_notice_id,user_id,time_read) values (?,?,now())", $systemNoticeId, $GLOBALS['gUserId']);
					} else {
						executeQuery("update system_notice_users set time_read = now() where time_read is null and system_notice_id = ? and user_id = ?", $systemNoticeId, $GLOBALS['gUserId']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "mark_unread":
				$resultSet = executeQuery("update system_notice_users set time_read = null where user_id = ? and system_notice_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "mark_unread") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=" + actionName, function(returnArray) {
                        getDataList();
                    });
                    return true;
                }
                return false;
            }

            function afterGetRecord(returnArray) {
                const deleteText = $("#_delete_button").html();
                if ($("#time_deleted").val() == "") {
                    $("#_delete_button").html(deleteText.replace("Undelete", "Delete"));
                } else {
                    $("#_delete_button").html(deleteText.replace("Undelete", "Delete").replace("Delete", "Undelete"));
                }
                if (empty(returnArray['require_acceptance']['data_value']) || !empty(returnArray['time_read']['data_value'])) {
                    $("#_acknowledge_acceptance_row").addClass("hidden");
                } else {
                    $("#_acknowledge_acceptance_row").removeClass("hidden");
                }
            }

            function beforeDeleteRecord() {
                if (!$("#_acknowledge_acceptance_row").hasClass("hidden")) {
                    displayErrorMessage("You must accept this message to continue");
                    return false;
                }
                return true;
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#acknowledge_acceptance").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=acknowledge_notice&system_notice_id=" + $("#primary_id").val(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#_acknowledge_acceptance_row").addClass("hidden");
                    }
                });
            });
        </script>
		<?php
	}

	function getListRowClasses($columnRow) {
		$timeRead = getFieldFromId("time_read", "system_notice_users", "system_notice_id", $columnRow['system_notice_id'], "user_id = ?", $GLOBALS['gUserId']);
		$classes = "system-notice-" . $columnRow['system_notice_id'] . (empty($timeRead) ? " unread" : "");
		return $classes;
	}

	function afterGetRecord(&$returnArray) {
		if (!isHtml($returnArray['content']['data_value'])) {
			$returnArray['content']['data_value'] = makeHtml($returnArray['content']['data_value']);
		}
		$timeRead = getFieldFromId("time_read", "system_notice_users", "system_notice_id", $returnArray['primary_id']['data_value'], "user_id = ?", $GLOBALS['gUserId']);
		$timeDeleted = getFieldFromId("time_deleted", "system_notice_users", "system_notice_id", $returnArray['primary_id']['data_value'], "user_id = ?", $GLOBALS['gUserId']);
		$returnArray['time_read'] = array("data_value" => $timeRead);
		$returnArray['acknowledge_acceptance'] = array("data_value" => (empty($timeRead) ? "0" : "1"));
		$returnArray['time_deleted'] = array("data_value" => $timeDeleted);
		$systemNoticeUserId = getFieldFromId("system_notice_user_id", "system_notice_users", "system_notice_id", $returnArray['primary_id']['data_value'], "user_id = ?", $GLOBALS['gUserId']);
		if (empty($systemNoticeUserId) && empty($returnArray['require_acceptance']['data_value'])) {
			executeQuery("insert into system_notice_users (system_notice_id,user_id,time_read) values (?,?,now())", $returnArray['primary_id']['data_value'], $GLOBALS['gUserId']);
		} else {
			executeQuery("update system_notice_users set time_read = now() where time_read is null and system_notice_id = ? and user_id = ?", $returnArray['primary_id']['data_value'], $GLOBALS['gUserId']);
		}
	}

	function internalCSS() {
		$resultSet = executeQuery("select * from system_notices where display_color is not null and (start_time is null or start_time <= current_time) and (end_time is null or end_time >= current_time)");
		while ($row = getNextRow($resultSet)) {
			?>
            tr.system-notice-<?= $row['system_notice_id'] ?> td.data-row-data { color: <?= $row['display_color'] ?>; }
			<?php
		}
		?>
        #content { width: 1200px; }
        #content ul { list-style: disc; margin-left: 30px; }
        #content ul li { margin-bottom: 5px; }
        tr.unread td.data-row-data { font-weight: bold; font-size: 115%; }
        #_acknowledge_acceptance_row .checkbox-label { color: rgb(192,0,0); font-weight: 900; font-size: 1.2rem; }
		<?php
	}

	function deleteRecord() {
		$returnArray = array();
		if (empty($_POST['time_deleted'])) {
			executeQuery("update system_notice_users set time_deleted = now() where time_deleted is null and system_notice_id = ? and user_id = ?", $_POST['primary_id'], $GLOBALS['gUserId']);
		} else {
			executeQuery("update system_notice_users set time_deleted = null where system_notice_id = ? and user_id = ?", $_POST['primary_id'], $GLOBALS['gUserId']);
		}
		ajaxResponse($returnArray);
	}

}

$pageObject = new ThisPage("system_notices");
$pageObject->displayPage();
