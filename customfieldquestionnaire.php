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

$GLOBALS['gPageCode'] = "CUSTOMFIELDQUESTIONNAIRE";
require_once "shared/startup.inc";

class CustomFieldQuesstionnairePage extends Page {

	private $iContactId;

	function internalCSS() {
		?>
        <style>
            #question_panel {
                width: 100%;
                padding: 20px;
                position: relative;
                overflow: hidden;
            }

            #contact_form {
                margin-bottom: 40px;
            }

            input[type=text], input[type=password] {
                border: 1px solid rgb(200, 200, 200);
                border-radius: 5px;
                padding: 4px 5px;
                outline: none;
                font-size: 14px;
            }

            input[type=checkbox] {
                font-size: 16px;
            }

            input[type=file] {
                width: 200px;
                padding-right: 10px;
            }

            input:focus {
                background-color: rgb(255, 255, 200);
            }

            input.borderless {
                border: 2px solid rgb(255, 255, 255);
            }

            input {
                margin: 0;
            }

            label {
                color: rgb(100, 100, 100);
                font-size: 16px;
                font-weight: bold;
            }

            select.field-text {
                background-color: rgb(250, 250, 250);
                font-size: 14px;
            }

            select.field-text option {
                font-size: 14px;
            }

            textarea {
                width: 600px;
                height: 100px;
                border: 1px solid rgb(200, 200, 200);
                border-radius: 5px;
                font-size: 14px;
                padding: 5px;
            }

            textarea.field-text {
                padding: 5px;
            }

            textarea:focus {
                background-color: rgb(255, 255, 200);
            }

            .checkbox-label {
                padding-left: 10px;
                cursor: pointer;
                display: inline-block;
            }

            .field-label {
                text-align: right;
                white-space: nowrap;
                vertical-align: top;
                padding-top: 8px;
                font-size: 14px;
                font-weight: bold;
                color: rgb(100, 100, 100);
                height: 14px;
                padding-right: 10px;
            }

            .field-label-text {
                display: inline-block;
                text-align: right;
                white-space: nowrap;
                vertical-align: top;
                padding-top: 8px;
                font-size: 14px;
                font-weight: bold;
                color: rgb(100, 100, 100);
                height: 14px;
                padding-right: 10px;
            }

            .field-text {
                vertical-align: bottom;
                font-size: 14px;
                padding: 2px 0 3px;
            }

            .form-line {
                min-height: 28px;
                position: relative;
                margin-bottom: 10px;
            }

            .form-line.inline-block {
                display: inline-block;
            }

            .form-line span.data-content {
                display: inline-block;
                position: absolute;
                top: 5px;
            }

            .form-line span.help-label {
                display: block;
                position: absolute;
                top: 18px;
                width: 185px;
                left: 0;
                text-align: right;
                font-size: 11px;
                color: rgb(150, 150, 150);
            }

            .form-line span.file-info {
                display: inline-block;
                position: absolute;
                top: 0;
                padding-top: 5px;
            }

            .form-line span.extra-info {
                display: inline-block;
                position: relative;
                top: 2px;
                padding-left: 10px;
                font-size: 14px;
                color: rgb(180, 180, 180);
                font-weight: bold;
            }

            .form-line label {
                display: inline-block;
                margin-top: 5px;
                width: 200px;
                text-align: right;
                padding-bottom: 4px;
                padding-right: 15px;
            }

            .form-line p {
                padding: 0;
                margin: 0 0 5px;
            }

            .form-line label.second-label {
                display: inline-block;
                width: auto;
                margin-top: 5px;
                margin-left: 20px;
                margin-right: 10px;
                text-align: right;
                padding: 0;
            }

            .form-line label.align-left {
                text-align: left;
            }

            .form-line label:first-child {
                float: left;
                display: block;
            }

            .form-line p label, .form-line p label:first-child {
                float: none;
                display: inline;
                margin: 0;
                padding: 0;
                width: 100%;
                text-align: left;
            }

            .form-line > img {
                display: inline-block;
                position: relative;
                top: 5px;
            }

            .shortest-label .form-line label, .form-line.shortest-label label {
                width: 100px;
            }

            .shortest-label .form-line span.required-tag, .form-line.shortest-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 85px;
            }

            .shortest-label .form-line span.help-label, .form-line.shortest-label span.help-label {
                width: 85px;
            }

            .shorter-label .form-line label, .form-line.shorter-label label {
                width: 150px;
            }

            .shorter-label .form-line span.required-tag, .form-line.shorter-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 135px;
            }

            .shorter-label .form-line span.help-label, .form-line.shorter-label span.help-label {
                width: 135px;
            }

            .long-label .form-line label, .form-line.long-label label {
                width: 250px;
            }

            .long-label .form-line span.required-tag, .form-line.long-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 235px;
            }

            .long-label .form-line span.help-label, .form-line.long-label span.help-label {
                width: 235px;
            }

            .longer-label .form-line label, .form-line.longer-label label {
                width: 300px;
            }

            .longer-label .form-line span.required-tag, .form-line.longer-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 285px;
            }

            .longer-label .form-line span.help-label, .form-line.longer-label span.help-label {
                width: 285px;
            }

            .longest-label .form-line label, .form-line.longest-label label {
                width: 350px;
            }

            .longest-label .form-line span.required-tag, .form-line.longest-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 335px;
            }

            .longest-label .form-line span.help-label, .form-line.longest-label span.help-label {
                width: 335px;
            }

            .maximum-label .form-line label, .form-line.maximum-label label {
                width: 450px;
            }

            .maximum-label .form-line span.required-tag, .form-line.maximum-label label span.required-tag {
                display: block;
                position: absolute;
                top: 5px;
                left: 435px;
            }

            .maximum-label .form-line span.help-label, .form-line.maximum-label span.help-label {
                width: 435px;
            }

            .longer-label textarea {
                width: 550px;
            }

            .longest-label textarea {
                width: 500px;
            }

            .form-line label.checkbox-label {
                float: none;
                display: inline-block;
                width: auto;
                text-align: left;
                padding-bottom: 0;
            }

            .form-line img.ui-datepicker-trigger {
                padding-left: 10px;
                top: -2px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <!--suppress JSUnresolvedVariable -->
        <script>
			<?php if ($GLOBALS['gLoggedIn']) { ?>
            $(".question").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_question", { custom_field_id: $(this).data("custom_field_id"), custom_field_data: $(this).val(), contact_id: $("#contact_id").val(), hash_code: $("#hash_code").val() });
                }
            });
			<?php } ?>
            $(document).on("tap click", "#submit_questions", function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_questions", $("#_edit_form").serialize(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            if (typeof afterSubmit == "function") {
                                afterSubmit();
                            }
                            if ("response" in returnArray) {
                                $("#question_panel").html(returnArray['response']);
                            }
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->getPageData("content");
		$this->iContactId = $GLOBALS['gUserRow']['contact_id'];
		$hashCode = $GLOBALS['gUserRow']['hash_code'];
		if (!empty($_GET['id']) && !empty($_GET['hash'])) {
			$thisContactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['id'], "hash_code = ?", $_GET['hash']);
			if (!empty($this->iContactId) && $this->iContactId != $thisContactId) {
				logout();
			}
			$this->iContactId = $thisContactId;
			$hashCode = getFieldFromId("hash_code", "contacts", "contact_id", $this->iContactId);
		}
		?>
        <div id="question_panel">
            <p class="error-message" id="_error_message"></p>
            <form id="_edit_form" name="_edit_form">
                <input type="hidden" id="contact_id" name="contact_id" value="<?= $this->iContactId ?>">
                <input type="hidden" id="hash_code" name="hash_code" value="<?= $hashCode ?>">
                <div id="contact_form">
					<?php
					if (empty($this->iContactId)) {
						$formFields = array("first_name", "last_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address");
						$contactTable = new DataTable("contacts");
						foreach ($formFields as $fieldName) {
							$dataColumn = $contactTable->getColumns($fieldName);
							switch ($fieldName) {
								case "country_id":
									$dataColumn->setControlValue("initial_value", "1000");
									break;
								case "first_name":
								case "last_name":
								case "email_address":
									$dataColumn->setControlValue("not_null", "true");
									break;
							}
							$resultSet = executeQuery("select * from page_controls where page_id = ? and column_name = ?", $GLOBALS['gPageId'], $fieldName);
							while ($row = getNextRow($resultSet)) {
								$dataColumn->setControlValue($row['control_name'], $row['control_value']);
							}
							switch ($fieldName) {
								case "country_id":
									$dataColumn->setControlValue("not_null", "true");
									$dataColumn->setControlValue("ignore", "");
									$dataColumn->setControlValue("no_empty_option", "true");
									break;
							}
							if ($dataColumn->getControlValue("ignore") != "true") {
								?>
                                <div class="form-line" id="_first_name_row">
                                    <label for="<?= $dataColumn->getControlValue("column_name") ?>" class="<?= ($dataColumn->getControlValue("not_null") == "true" ? "required-label" : "") ?>"><?= $dataColumn->getControlValue("form_label") ?></label>
									<?= $dataColumn->getControl($this) ?>
                                    <div class='clear-div'></div>
                                </div>
								<?php
							}
						}
						$phoneNumberTable = new DataTable("phone_numbers");
						$dataColumn = $phoneNumberTable->getColumns("phone_number");
						$dataColumn->setControlValue("not_null", "");
						$resultSet = executeQuery("select * from page_controls where page_id = ? and column_name = ?", $GLOBALS['gPageId'], "phone_number");
						while ($row = getNextRow($resultSet)) {
							$dataColumn->setControlValue($row['control_name'], $row['control_value']);
						}
						if ($dataColumn->getControlValue("ignore") != "true") {
							?>
                            <div class="form-line" id="_first_name_row">
                                <label for="<?= $dataColumn->getControlValue("column_name") ?>" class="<?= ($dataColumn->getControlValue("not_null") == "true" ? "required-label" : "") ?>"><?= $dataColumn->getControlValue("form_label") ?></label>
								<?= $dataColumn->getControl($this) ?>
                                <div class='clear-div'></div>
                            </div>
							<?php
						}
					} else {
						?>
                        <p>You are <?= getDisplayName($this->iContactId) ?>. If this is not you, please close this window and contact customer service.</p>
						<?php
					}
					?>
                </div>
				<?php
				$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and custom_field_id in (select custom_field_id from custom_field_group_links where " .
					"custom_field_group_id = (select custom_field_group_id from custom_field_groups where custom_field_group_code = ? and client_id = ?)) and " .
					"client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order,description", $_GET['code'], $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$this->addCustomField($row['custom_field_id']);
				}
				?>
            </form>
            <p class="align-center"><span class="button" id="submit_questions">Submit Answers</span></p>
        </div>
        <div class='clear-div'></div>
		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function addCustomField($customFieldId) {
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and custom_field_id = ?", $customFieldId);
		if ($row = getNextRow($resultSet)) {
			$thisColumn = new DataColumn("custom_field_id_" . $row['custom_field_id']);
			$thisColumn->setControlValue("form_label", $row['form_label']);
			$controlSet = executeQuery("select * from custom_field_controls where custom_field_id = ?", $row['custom_field_id']);
			while ($controlRow = getNextRow($controlSet)) {
				$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
			}
			$choices = $thisColumn->getControlValue("choices");
			$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $row['custom_field_id']);
			while ($choiceRow = getNextRow($choiceSet)) {
				$choices[$choiceRow['key_value']] = $choiceRow['description'];
			}
			$thisColumn->setControlValue("choices", $choices);
			$thisColumn->setControlValue("data-custom_field_id", $row['custom_field_id']);
			$checkbox = ($thisColumn->getControlValue("data_type") == "tinyint");
			$longLabel = (strlen($thisColumn->getControlValue("form_label")) > 25);
			$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?",
				$row['custom_field_id'], $this->iContactId);
			if (!$dataRow = getNextRow($dataSet)) {
				$dataRow = array();
			}
			switch ($thisColumn->getControlValue("data_type")) {
				case "date":
					$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
					break;
				case "bigint":
				case "int":
					$fieldValue = $dataRow['integer_data'];
					break;
				case "decimal":
					$fieldValue = $dataRow['number_data'];
					break;
				case "image":
					$fieldValue = $dataRow['image_id'];
					break;
				case "file":
					$fieldValue = $dataRow['file_id'];
					break;
				case "tinyint":
					$fieldValue = ($dataRow['text_data'] ? "1" : "0");
					break;
				default:
					$fieldValue = $dataRow['text_data'];
					break;
			}
			$thisColumn->setControlValue("initial_value", $fieldValue);
			$thisColumn->setControlValue("classes", "question");
			?>
            <div class="form-line" id="_<?= "custom_field_id_" . $row['custom_field_id'] ?>_row">
				<?php if (!$checkbox) { ?>
					<?php if ($longLabel) { ?><p><?php } ?><label for="<?= "custom_field_id_" . $row['custom_field_id'] ?>"><?= $thisColumn->getControlValue("form_label") ?></label><?php if ($longLabel) { ?></p><?php } ?>
				<?php } ?>
				<?php
				$helpLabel = $thisColumn->getControlValue('help_label');
				if (!empty($helpLabel)) { ?>
                    <span class="help-label"><?= $thisColumn->getControlValue('help_label') ?></span>
				<?php } ?>
				<?= $thisColumn->getControl($this) ?>
                <div class='clear-div'></div>
            </div>
			<?php
		}
	}

	function getCustomFieldType($customFieldId) {
		$dataType = "";
		$controlSet = executeQuery("select * from custom_field_controls where custom_field_id = ? and control_name = 'data_type'", $customFieldId);
		if ($controlRow = getNextRow($controlSet)) {
			$dataType = $controlRow['control_value'];
		}
		$updateField = "";
		switch ($dataType) {
			case "date":
				$updateField = "date_data";
				break;
			case "bigint":
			case "int":
				$updateField = "integer_data";
				break;
			case "decimal":
				$updateField = "number_data";
				break;
			default:
				$updateField = "text_data";
				break;
		}
		return $updateField;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "save_question":
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id'], "hash_code = ?", $_POST['hash_code']);
				}
				if (!empty($contactId)) {
					$customFieldCode = getFieldFromId("custom_field_code", "custom_fields", "custom_field_id", $_POST['custom_field_id']);
					if (!empty($customFieldCode)) {
						CustomField::setCustomFieldData($contactId, $customFieldCode, $_POST['custom_field_data']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "save_questions":
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id'], "hash_code = ?", $_POST['hash_code']);
				}
				if (empty($_POST['country_id'])) {
					$_POST['country_id'] = 1000;
				}
				if (empty($contactId)) {
					$formFields = array("first_name", "last_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address");
					$_POST['source_id'] = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
					if (empty($_POST['source_id'])) {
						$_POST['source_id'] = getSourceFromReferer($_SERVER['HTTP_REFERER']);
					}
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
						"address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
						"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "source_id" => $_POST['source_id'])))) {
						$returnArray['error_message'] = "Error creating customer record";
						ajaxResponse($returnArray);
						break;
					}
					makeWebUserContact($contactId);
					if (!empty($_POST['phone_number'])) {
						executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $_POST['phone_number']);
					}
					setCoreCookie("customer_contact_id", $contactId, 24);
					$_COOKIE['customer_contact_id'] = $contactId;
				}
				$questionDetails = "";
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("custom_field_id_")) != "custom_field_id_") {
						continue;
					}
					$customFieldId = substr($fieldName, strlen("custom_field_id_"));
					if (!is_numeric($customFieldId)) {
						continue;
					}
					$customDescription = getFieldFromId("form_label", "custom_fields", "custom_field_id", $customFieldId);
					$questionDetails .= (empty($questionDetails) ? "" : "<br>\n") . $customDescription . ": " . $fieldData;
					$customFieldCode = getFieldFromId("custom_field_code", "custom_fields", "custom_field_id", $customFieldId);
					CustomField::setCustomFieldData($contactId, $customFieldCode, $fieldData);
				}
				$emailAddresses = getNotificationEmails('CUSTOM_FIELD_QUESTIONNAIRE');
				$responsibleUserId = getFieldFromId("responsible_user_id", "contacts", "contact_id", $contactId);
				if (!empty($responsibleUserId)) {
					$emailAddress = Contact::getUserContactField($responsibleUserId,"email_address");
					if (!in_array($emailAddress, $emailAddresses)) {
						$emailAddresses[] = $emailAddress;
					}
				}
				if (!empty($emailAddresses)) {
					$body = "A customer filled out the questionnaire for custom fields. Details: <br>\n<br>\nName: " . getUserDisplayName() . "<br>\n" . $questionDetails;
					sendEmail(array("subject" => "Questionnaire Submitted for " . getDisplayName($contactId), "body" => $body, "email_addresses" => $emailAddresses));
				}
				$response = $this->getPageTextChunk("submit_response");
				if (!empty($response)) {
					$returnArray['response'] = makeHtml($response);
				}
				ajaxResponse($returnArray);
				break;
		}
	}
}

$pageObject = new CustomFieldQuesstionnairePage();
$pageObject->displayPage();
