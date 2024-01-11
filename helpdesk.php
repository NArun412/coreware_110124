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

$GLOBALS['gPageCode'] = "HELPDESKENTRY";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_custom_data":
				$resultSet = executeQuery("select * from help_desk_categories where help_desk_category_id in (select help_desk_category_id from help_desk_type_categories where help_desk_type_id = ?) and " .
					"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $_GET['help_desk_type_id'], $GLOBALS['gClientId']);
				$returnArray['help_desk_categories'] = array();
				while ($row = getNextRow($resultSet)) {
					$returnArray['help_desk_categories'][] = array("key_value" => $row['help_desk_category_id'], "description" => $row['description']);
				}
				ob_start();
				$this->customData($_GET['help_desk_type_id']);
				$returnArray['custom_data'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "create_help_desk_entry":
				$this->iDatabase->startTransaction();
				if ($GLOBALS['gLoggedIn']) {
					$_POST['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$contactId = "";
					$resultSet = executeQuery("select contact_id from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
						"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					}
					if (empty($contactId)) {
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
							"email_address" => $_POST['email_address'])))) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = $contactDataTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
					}
					$_POST['contact_id'] = $contactId;
				}
				$helpDeskEntry = new HelpDesk();
				$helpDeskEntry->addSubmittedData($_POST);
				if ($helpDeskEntry->save()) {
					$returnArray['response'] = $helpDeskEntry->getSubmissionResponse();
				} else {
					$returnArray['error_message'] = $helpDeskEntry->getErrorMessage();
				}
				$helpDeskEntry->addFiles();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#submit_button").click(function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#help_desk_type_id").prop("disabled", false);
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_help_desk_entry").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("response" in returnArray) {
                            $("#_form_content").html(returnArray['response']);
                        }
                    });
                }
                return false;
            });
            $("#help_desk_type_id").change(function () {
                $("#custom_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_data&help_desk_type_id=" + $(this).val(), function(returnArray) {
                        if ("custom_data" in returnArray) {
                            $("#custom_data").html(returnArray['custom_data']);
                            $("#custom_data .required-label").append("<span class='required-tag fa fa-asterisk'></span>");
                            $("#custom_data .datepicker").datepicker({
                                showOn: "button",
                                buttonText: "<span class='fad fa-calendar-alt'></span>",
                                constrainInput: false,
                                dateFormat: "mm/dd/y",
                                yearRange: "c-100:c+10"
                            });
                        }
                        $("#help_desk_category_id").find("option[value!='']").remove();
                        if ("help_desk_categories" in returnArray) {
                            for (var i in returnArray['help_desk_categories']) {
                                var thisOption = $("<option></option>").attr("value", returnArray['help_desk_categories'][i]['key_value']).text(returnArray['help_desk_categories'][i]['description']);
                                $("#help_desk_category_id").append(thisOption);
                            }
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function customData($helpDeskTypeId) {
		if (empty($helpDeskTypeId)) {
			return;
		}
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "help_desk_type_id",
				$helpDeskTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($helpDeskTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function mainContent() {
		echo $this->getPageData("content");
		$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_code", $_GET['type'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		$labels = array();
		if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
			foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
				$labels[strtolower($pageTextChunkCode)] = $pageTextChunkContent;
			}
		}
		?>
        <div id="_form_content">
            <form id="_edit_form" enctype='multipart/form-data'>

				<?php if ($GLOBALS['gLoggedIn']) { ?>

                    <input type="hidden" id="user_id" name="user_id" value="<?= $GLOBALS['gUserId'] ?>">

				<?php } else { ?>

                    <div class="form-line" id="_first_name_row">
                        <label class="required-label">First Name</label>
                        <input type="text" tabindex="10" id="first_name" name="first_name" class="validate[required]" maxlength="25">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_last_name_row">
                        <label class="required-label">Last Name</label>
                        <input type="text" tabindex="10" id="last_name" name="last_name" class="validate[required]" maxlength="35">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_email_address_row">
                        <label class="required-label">Email</label>
                        <input type="text" tabindex="10" id="email_address" name="email_address" class="validate[required,custom[email]]" maxlength="60">
                        <div class='clear-div'></div>
                    </div>

				<?php } ?>

                <div class="form-line" id="_help_desk_type_id_row">
                    <label class="required-label"><?= (array_key_exists("help_desk_type_id_label", $labels) ? $labels['help_desk_type_id_label'] : "Request Type") ?></label>
                    <select class="validate[required]" tabindex="10" id="help_desk_type_id" name="help_desk_type_id"<?= (empty($helpDeskTypeId) ? "" : " disabled='disabled'") ?>>
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from help_desk_types where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option<?= ($row['help_desk_type_id'] == $helpDeskTypeId ? " selected" : "") ?> value="<?= $row['help_desk_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_help_desk_category_id_row">
                    <label><?= (array_key_exists("help_desk_category_id_label", $labels) ? $labels['help_desk_category_id_label'] : "What is this about?") ?></label>
                    <select tabindex="10" id="help_desk_category_id" name="help_desk_category_id">
                        <option value="">[Other]</option>
						<?php
						if (!empty($helpDeskTypeId)) {
							$resultSet = executeQuery("select * from help_desk_categories where help_desk_category_id in (select help_desk_category_id from help_desk_type_categories where help_desk_type_id = ?) and " .
								"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $helpDeskTypeId, $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['help_desk_category_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_description_row">
                    <label class="required-label"><?= (array_key_exists("description_label", $labels) ? $labels['description_label'] : "Subject") ?></label>
                    <input type="text" tabindex="10" id="description" name="description" class="validate[required]">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_content_row">
                    <label class="required-label"><?= (array_key_exists("content_label", $labels) ? $labels['content_label'] : "Message") ?></label>
                    <textarea tabindex="10" id="content" name="content" class="validate[required]"></textarea>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_image_id_row">
                    <label><?= (array_key_exists("image_id_label", $labels) ? $labels['image_id_label'] : "Send an image if that might help") ?></label>
                    <input tabindex="10" type="file" id="image_id_file" name="image_id_file">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_file_id_row">
                    <label><?= (array_key_exists("file_id_label", $labels) ? $labels['file_id_label'] : "Send a file if that helps") ?></label>
                    <input tabindex="10" type="file" id="file_id_file" name="file_id_file">
                    <div class='clear-div'></div>
                </div>

                <div id="custom_data">
                </div>

                <div class="form-line" id="_submit_button_row">
                    <label></label>
                    <button tabindex="10" id="submit_button">Submit</button>
                    <div class='clear-div'></div>
                </div>

            </form>
        </div>
		<?php
		echo $this->getPageData("after_form_content");
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

	function internalCSS() {
		?>
        <style>
            #content {
                width: 800px;
                max-width: 90%;
                height: 500px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
