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

$GLOBALS['gPageCode'] = "EMAILLOG";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class EmailLogPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("resend" => array("icon" => "fad fa-paper-plane", "label" => getLanguageText("Resend"),
				"disabled" => false)));
			$filters = array();
			$filters['start_date'] = array("form_label" => "Entries on or after", "where" => "time_submitted >= '%filter_value%'", "data_type" => "date");
			$filters['end_date'] = array("form_label" => "Entries on or before", "where" => "time_submitted <= '%filter_value% 23:59:59'", "data_type" => "date");
            $filters['failures_only'] = array("form_label" => "Failures only", "where" => "log_entry not like 'Results: 1%'", "data_type" => "tinyint");
            $filters['hide_duplicates'] = array("form_label" => "Hide Duplicates", "where" => "email_log_id in (select min(email_log_id) from email_log group by parameters)", "data_type" => "tinyint");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("resend", "Resend Selected");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "resend_email":
				$emailLogId = getFieldFromId("email_log_id", "email_log", "email_log_id", $_GET['primary_id']);
				if (empty($emailLogId)) {
					$returnArray['error_message'] = "Record not found";
					ajaxResponse($returnArray);
					break;
				}
				$parameters = getFieldFromId("parameters", "email_log", "email_log_id", $emailLogId);
				$result = sendEmail(json_decode($parameters, true));
				if ($result === true) {
					$returnArray['info_message'] = "Email resent";
				} else {
					$returnArray['error_message'] = $result;
				}
				ajaxResponse($returnArray);
				break;
			case "resend_selected":
                $sentCount = 0;
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ? and primary_identifier in (select email_log_id from email_log where time_submitted > date_sub(current_date,interval 7 day))", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$parameters = getFieldFromId("parameters", "email_log", "email_log_id", $row['primary_identifier']);
					$result = sendEmail(json_decode($parameters, true));
					if ($result === true) {
						$sentCount++;
					}
					executeQuery("delete from selected_rows where page_id = ? and user_id = ? and primary_identifier = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId'],$row['primary_identifier']);
				}
                $selectedCount = getFieldFromId("count(*)","selected_rows","page_id",$GLOBALS['gPageId'],"user_id = ?",$GLOBALS['gUserId']);
                if ($selectedCount > 0) {
                    $returnArray['error_message'] = "Only emails sent in the last week are included in selected resend. Older emails must be individually resent";
                }
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "resend") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=resend_selected", function(returnArray) {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}
	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#_resend_button", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=resend_email&primary_id=" + $("#primary_id").val());
                return false;
            });
        </script>
		<?php
	}
}

$pageObject = new EmailLogPage("email_log");
$pageObject->displayPage();
