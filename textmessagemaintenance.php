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

$GLOBALS['gPageCode'] = "TEXTMESSAGEMAINT";
require_once "shared/startup.inc";

class TextMessageMaintenancePage extends Page {
	function massageDataSource() {
		$this->iDataSource->addColumnControl("email_id", "data_type", "select");
		$this->iDataSource->addColumnControl("email_id", "form_label", "Existing Email");
		$this->iDataSource->addColumnControl("email_id", "help_label", "Choose an existing email to use its code and view content");
		$this->iDataSource->addColumnControl("email_id", "get_choices", "emailChoices");

		$this->iDataSource->addColumnControl("email_content", "data_type", "text");
		$this->iDataSource->addColumnControl("email_content", "readonly", true);
		$this->iDataSource->addColumnControl("email_content", "form_label", "Email Content");
		$this->iDataSource->addColumnControl("email_content", "help_label", "Copy and paste text of the email for the text message. Text messages cannot process HTML.");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_email_content":
				$resultSet = executeQuery("select * from emails where email_id = ? and client_id = ? and inactive = 0", $_GET['email_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['email_code'] = $row['email_code'];
					$returnArray['email_content'] = $row['content'];
					$returnArray['description'] = $row['description'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function emailChoices($showInactive = false) {
		$emailChoices = array();
		$resultSet = executeQuery("select * from emails where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$emailChoices[$row['email_id']] = array("key_value" => $row['email_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		return $emailChoices;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['email_id'] = array("data_value" => "");
		$returnArray['email_content'] = array("data_value" => "");
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                if (empty($("#primary_id").val())) {
                    $("#_email_id_row").removeClass("hidden");
                    $("#_email_content_row").removeClass("hidden");
                } else {
                    $("#_email_id_row").addClass("hidden");
                    $("#_email_content_row").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#email_id").change(function () {
                const emailId = $(this).val();
                if (!empty(emailId)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_email_content&email_id=" + emailId, function(returnArray) {
                        if ("email_code" in returnArray) {
                            $("#text_message_code").val(returnArray['email_code']);
                        }
                        if ("email_content" in returnArray) {
                            $("#email_content").val(returnArray['email_content']);
                        }
                        if ("description" in returnArray) {
                            $("#description").val(returnArray['description']);
                        }
                    });
                }
            });
        </script>
		<?php
	}

}

$pageObject = new TextMessageMaintenancePage("text_messages");
$pageObject->displayPage();
