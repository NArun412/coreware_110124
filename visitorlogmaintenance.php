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

$GLOBALS['gPageCode'] = "VISITORLOGMAINT";
require_once "shared/startup.inc";

class VisitorLogMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$filters['hide_reservations'] = array("form_label" => "Hide visitors who have reservation", "where" => "contact_id not in (select contact_id from events where start_date = current_date)", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['hide_not_waiting'] = array("form_label" => "Only show visitors who are waiting for reservation", "where" => "visit_type_id in (select visit_type_id from visit_types where waiting_list = 1)", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("send_message" => array("label" => getLanguageText("Send Message"), "disabled" => false)));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "not_editable", true);
		$this->iDataSource->addColumnControl("set_end_time", "data_type", "hidden");
		$this->iDataSource->addColumnControl("end_time", "help_label", "Click to set");
		$this->iDataSource->setFilterWhere("end_time is null and visit_time > '" . date("Y-m-d") . " 00:00:00'");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_text_answer":
				$returnArray['text_content'] = strip_tags(html_entity_decode(getFieldFromId("content", "help_desk_answers", "help_desk_answer_id", $_GET['help_desk_answer_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""))));
				ajaxResponse($returnArray);
				break;
			case "send_text_message":
				$body = $_POST['text_message'];
				if (empty($body)) {
					$returnArray['error_message'] = "No Message to send";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if ($GLOBALS['gPHPVersion'] < 70200) {
					$returnArray['error_message'] = "Unable to send text";
					ajaxResponse($returnArray);
					break;
				}
				$result = TextMessage::sendMessage($contactId, $body);
				if (!$result) {
					$returnArray['error_message'] = "Unable to send text message";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['info_message'] = "Text successfully sent";
				ajaxResponse($returnArray);
				break;
        }
	}

	function supplementaryContent() {
		?>
        <div id='current_reservations'></div>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['set_end_time'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$firstOne = true;
		ob_start();
		$resultSet = executeQuery("select * from events where start_date = current_date and contact_id = ? and inactive = 0", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$facilitySet = executeQuery("select facility_id,(select description from facilities where facility_id = event_facilities.facility_id) description,min(hour),max(hour) from event_facilities where event_id = ? group by facility_id,description", $row['event_id']);
			while ($facilityRow = getNextRow($facilitySet)) {
				if ($firstOne) {
					?>
                    <h2>Reservations for <?= date("D, M j, Y") ?></h2>
                    <ul>
					<?php
					$firstOne = false;
				}
				?>
                <li><?= htmlText($row['description']) ?>, <?= htmlText($facilityRow['description']) ?>, <?= Events::getDisplayTime($facilityRow['min(hour)']) ?>-<?= Events::getDisplayTime($facilityRow['max(hour)'], true) ?></li>
				<?php
			}
		}
		if ($firstOne) {
			?>
            <h2>No reservations today</h2>
			<?php
		} else {
			?>
            </ul>
			<?php
		}
		$returnArray['current_reservations'] = array("data_value" => ob_get_clean());
	}

	function internalCSS() {
		?>
        <style>
            #end_time {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#end_time", function () {
                $(this).val("<?= date("m/d/Y g:i:sa") ?>");
                $("#set_end_time").val("1");
            });
            $(document).on("change", "#text_help_desk_answer_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_text_answer&help_desk_answer_id=" + $(this).val(), function(returnArray) {
                        $("#text_message").val(returnArray['text_content']);
                    });
                }
            });
            $(document).on("click", "#_send_message_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save first");
                } else {
                    $("#_send_text_message_form").clearForm();
                    $('#_send_text_message_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 800,
                        title: 'Send Message to Customer',
                        buttons: {
                            Send: function (event) {
                                if ($("#_send_text_message_form").validationEngine("validate")) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_text_message&contact_id=" + $("#contact_id").val(), $("#_send_text_message_form").serialize(), function (returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            $("#_send_text_message_dialog").dialog('close');
                                        }
                                    });
                                }
                            },
                            Cancel: function (event) {
                                $("#_send_text_message_dialog").dialog('close');
                            }
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_send_text_message_dialog" class="dialog-box">
            <form id="_send_text_message_form">

                <p class="error-message"></p>
				<?php
				$resultSet = executeQuery("select * from help_desk_answers where help_desk_type_id is null and client_id = ?" . ($GLOBALS['gUserRow']['superuser_flag'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . " order by description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
                    <div class="form-line">
                        <label for="text_help_desk_answer_id">Standard Answers</label>
                        <select id="text_help_desk_answer_id" data-url_action="get_text_answer" class="add-new-option"
                                data-link_url="help-desk-answer-maintenance?url_page=new" data-control_code="help_desk_answers">
                            <option value="">[Select]</option>
                            <?php if (empty(getPreference("NO_ADD_NEW_OPTION"))) { ?>
                            <option value="-9999">[Add New]</option>
                            <?php } ?>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['help_desk_answer_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
				?>
                <div class="form-line" id="_text_message_row">
                    <label for="text_message" class="required-label">Content</label>
                    <textarea class="validate[required]" id="text_message" name="text_message"></textarea>
                    <div class='clear-div'></div>
                </div>

            </form>
        </div> <!-- send_text_message_dialog -->
		<?php
	}

}

$pageObject = new VisitorLogMaintenancePage("visitor_log");
$pageObject->displayPage();
