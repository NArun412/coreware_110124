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

$GLOBALS['gPageCode'] = "EMAILMAINT";
require_once "shared/startup.inc";

class EmailMaintenancePage extends Page {

	function setup() {
		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
				"disabled" => false)));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "send_test_email":
				$emailId = getFieldFromId("email_id", "emails", "email_id", $_GET['primary_id']);
				if (!empty($emailId)) {
					if (sendEmail(array("email_id" => $emailId, "email_address" => $GLOBALS['gUserRow']['email_address'], "send_immediately" => true))) {
						$returnArray['info_message'] = "Email was sent";
					} else {
						$returnArray['error_message'] = "Error sending email";
					}
				} else {
					$returnArray['error_message'] = "Error sending email";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("content", "classes", "data-format-HTML use-ck-editor");
		$this->iDataSource->addColumnControl("test_button", "data_type", "button");
		$this->iDataSource->addColumnControl("test_button", "button_label", "Send Test Email");

		$this->iDataSource->addColumnControl("email_copies", "data_type", "custom");
		$this->iDataSource->addColumnControl("email_copies", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("email_copies", "form_label", "CC Email Addresses");
		$this->iDataSource->addColumnControl("email_copies", "help_label", "Whenever this email is sent, copies will be sent to these email addresses");
		$this->iDataSource->addColumnControl("email_copies", "list_table", "email_copies");

		$this->iDataSource->getPrimaryTable()->setSubtables(array("email_changes"));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$emailId = getFieldFromId("email_id", "emails", "email_id", $_GET['primary_id'], "client_id is not null");
			if (empty($emailId)) {
				return;
			}
			$resultSet = executeQuery("select * from emails where email_id = ?", $emailId);
			$emailRow = getNextRow($resultSet);
			$originalEmailCode = $emailRow['email_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($emailRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$emailRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$emailRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newEmailId = "";
			$emailRow['description'] .= " Copy";
			while (empty($newEmailId)) {
				$emailRow['email_code'] = $originalEmailCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from emails where email_code = ? and client_id = ?",
					$emailRow['email_code'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into emails values (" . $queryString . ")", $emailRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newEmailId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newEmailId;
		}
	}

	function beforeSaveChanges(&$nameValues) {
		$nameValues['content'] = processBase64Images($nameValues['content'], true);
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("tap click", "#_duplicate_button", function () {
                if (!empty($("#primary_id").val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                    }
                }
                return false;
            });
			<?php } ?>
            $("#test_button").click(function () {
                if (changesMade()) {
                    displayErrorMessage("Save changes first");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_test_email&primary_id=" + $("#primary_id").val());
                }
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
            }
        </script>
		<?php
	}
}

$pageObject = new EmailMaintenancePage("emails");
$pageObject->displayPage();
