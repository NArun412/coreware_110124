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

$GLOBALS['gPageCode'] = "USERNOTIFICATIONS";
require_once "shared/startup.inc";

class UserNotificationPage extends Page {

	function setup() {
		$this->iDataSource->addColumnControl("subject", "readonly", true);
		$this->iDataSource->addColumnControl("content", "readonly", true);
		$this->iDataSource->addColumnControl("file_id", "readonly", true);
		$this->iDataSource->addColumnControl("time_submitted", "form_label", "Created On");
		$this->iDataSource->addColumnControl("time_submitted", "not_null", "false");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_notification_id", "subject", "time_submitted"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "save"));
			$filters = array();
			$filters['hide_deleted'] = array("form_label" => "Hide Deleted", "where" => "time_deleted is null", "data_type" => "tinyint", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_unread", "Mark Selected Unread");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_read", "Mark Selected as Read");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("delete_selected", "Delete Selected Notifications");
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("delete_all", "Delete All Notifications");
		}
	}

	function beforeList() {
		?>
        <h2>Bold items in the list are unread notifications. Click on each to read them.</h2>
		<?php
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "mark_unread":
				executeQuery("update user_notifications set time_read = null where user_id = ? and user_notification_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "mark_read":
				executeQuery("update user_notifications set time_read = now() where time_read is null and user_id = ? and user_notification_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "delete_selected":
				executeQuery("update user_notifications set time_deleted = now() where time_deleted is null and user_id = ? and user_notification_id in " .
					"(select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "delete_all":
				executeQuery("update user_notifications set time_deleted = now() where time_deleted is null and user_id = ?", $GLOBALS['gUserId']);
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "mark_unread" || actionName === "mark_read" || actionName === "delete_selected" || actionName === "delete_all") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=" + actionName, function(returnArray) {
                        getDataList();
                    });
                    return true;
                }
                return false;
            }

            function afterGetRecord(returnArray) {
                const deleteText = $("#_delete_button").html();
                if (empty($("#time_deleted").val())) {
                    $("#_delete_button").html(deleteText.replace("Undelete", "Delete"));
                } else {
                    $("#_delete_button").html(deleteText.replace("Undelete", "Delete").replace("Delete", "Undelete"));
                }
            }
        </script>
		<?php
	}

	function getListRowClasses($columnRow) {
		$timeRead = getFieldFromId("time_read", "user_notifications", "user_notification_id", $columnRow['user_notification_id']);
		return "user-notification-" . $columnRow['user_notification_id'] . (empty($timeRead) ? " unread" : "");
	}

	function afterGetRecord(&$returnArray) {
		if (!isHtml($returnArray['content']['data_value'])) {
			$returnArray['content']['data_value'] = makeHtml($returnArray['content']['data_value']);
		}
		executeQuery("update user_notifications set time_read = now() where time_read is null and user_notification_id = ? and user_id = ?", $returnArray['primary_id']['data_value'], $GLOBALS['gUserId']);
	}

	function internalCSS() {
		?>
        <style>
            #content {
                width: 1200px;
            }

            #content ul {
                list-style: disc;
                margin-left: 30px;
            }

            #content ul li {
                margin-bottom: 5px;
            }

            tr.unread td.data-row-data {
                font-weight: bold;
                font-size: 115%;
            }
        </style>
		<?php
	}

	function deleteRecord() {
		$returnArray = array();
		if (empty($_POST['time_deleted'])) {
			executeQuery("update user_notifications set time_deleted = now() where time_deleted is null and user_notification_id = ? and user_id = ?", $_POST['primary_id'], $GLOBALS['gUserId']);
		} else {
			executeQuery("update user_notifications set time_deleted = null where user_notification_id = ? and user_id = ?", $_POST['primary_id'], $GLOBALS['gUserId']);
		}
		ajaxResponse($returnArray);
	}

}

$pageObject = new UserNotificationPage("user_notifications");
$pageObject->displayPage();
