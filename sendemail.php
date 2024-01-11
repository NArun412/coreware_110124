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

$GLOBALS['gPageCode'] = "SENDEMAIL";
require_once "shared/startup.inc";

class SendEmailPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_additional_emails":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				$resultSet = executeQuery("select * from contact_emails where contact_id = ?", $contactId);
				while ($row = getNextRow($resultSet)) {
					?>
                    <p><input type='checkbox' value='1' id='contact_email_id_<?= $row['contact_email_id'] ?>' name='contact_email_id_<?= $row['contact_email_id'] ?>'><label for='contact_email_id_<?= $row['contact_email_id'] ?>' class='checkbox-label'><?= $row['email_address'] ?></label></p>
					<?php
				};
				$returnArray['additional_emails'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "send_email":
				if (!empty($_POST['send_after'])) {
					$_POST['send_immediately'] = "";
				}
				$substitutions = Contact::getContact($_POST['contact_id']);
				$substitutions["full_name"] = getDisplayName($substitutions['contact_id'], array("dont_use_company" => true));
				$substitutions["salutation"] = (empty($substitutions['salutation']) ? generateSalutation($substitutions) : $substitutions['salutation']);
				$substitutions["country"] = getFieldFromId("country_name", "countries", "country_id", $substitutions['country_id']);
				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("substitutions_substitution_name-")) == "substitutions_substitution_name-") {
						$rowNumber = substr($fieldName, strlen("substitutions_substitution_name-"));
						$substitutions[$fieldValue] = $_POST['substitutions_substitution_value-' . $rowNumber];
					}
				}
				$emailAddresses = array(getFieldFromId("email_address", "contacts", "contact_id", $_POST['contact_id']));
                if(!empty($_POST['email_address'])) {
                    $emailAddresses[] = $_POST['email_address'];
                }
                foreach ($_POST as $fieldName => $fieldValue) {
					if (empty($fieldValue)) {
						continue;
					}
					if (substr($fieldName, 0, strlen("contact_email_id_")) == "contact_email_id_") {
						$contactEmailId = substr($fieldName, strlen("contact_email_id_"));
						$emailAddress = getFieldFromId("email_address", "contact_emails", "contact_email_id", $contactEmailId, "contact_id = ?", $_POST['contact_id']);
						if (!empty($emailAddress)) {
							$emailAddresses[] = $emailAddress;
						}
					}
				}
				$returnString = sendEmail(array("email_credential_code" => $_POST['email_credential_code'], "subject" => $_POST['subject'], "send_immediately" => (empty($_POST['send_immediately']) ? false : true),
					"body" => makeHtml($_POST['content']), "send_after" => (empty($_POST['send_after']) ? "" : date("Y-m-d", strtotime($_POST['send_after']))), "attachment_file_id" => $_POST['attachment_file_id'], "substitutions" => $substitutions, "email_address" => $emailAddresses));
				if ($returnString === true) {
					$returnArray['info_message'] = "Email successfully sent";
					$taskTypeCodes = array("EMAIL_SENT", "EMAIL_RECEIVED", "CONTACT_TASK");
					foreach ($taskTypeCodes as $thisTaskTypeCode) {
						$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", $thisTaskTypeCode, "inactive = 0 and task_type_id in (select task_type_id from task_type_attributes where " .
							"task_attribute_id in (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK'))");
						if (!empty($taskTypeId)) {
							break;
						}
					}
					if (empty($taskTypeId)) {
						$taskTypeId = getFieldFromId("task_type_id", "task_types", "client_id", $GLOBALS['gClientId'], "inactive = 0 and task_type_id in (select task_type_id from task_type_attributes where " .
							"task_attribute_id in (select task_attribute_id from task_attributes where task_attribute_code = 'CONTACT_TASK'))");
					}
					if (!empty($_POST['record_touchpoint']) && $_POST['contact_id'] != $GLOBALS['gUserRow']['contact_id']) {
						executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task) values " .
							"(?,?,?,?,now(),?,1)", $GLOBALS['gClientId'], $_POST['contact_id'], $_POST['subject'], strip_tags($_POST['content']), $taskTypeId);
					}
				} else {
					$returnArray['error_message'] = $returnString;
				}
				ajaxResponse($returnArray);
				break;
			case "get_email":
				$resultSet = executeQuery("select * from emails where email_id = ? and client_id = ?", $_GET['email_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if (false && strpos($row['content'], "<p>") === false) {
						$returnArray['content'] = makeHtml($row['content']);
					} else {
						$returnArray['content'] = $row['content'];
					}
					$returnArray['subject'] = $row['subject'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		$postValues = array();
		if (!empty($_GET['donation_id'])) {
			$resultSet = executeQuery("select * from contacts join donations using (contact_id) where contacts.client_id = ? and donation_id = ? and contacts.contact_id = ?", $GLOBALS['gClientId'], $_GET['donation_id'], $_GET['contact_id']);
			if ($row = getNextRow($resultSet)) {
				$contactId = (empty($row['receipted_contact_id']) ? $row['contact_id'] : $row['receipted_contact_id']);
				$monthlyTotals = array();
				$thisYear = date('Y', strtotime($row['donation_date']));
				for ($x = 1; $x <= 12; $x++) {
					$monthlyTotals[$x] = 0;
				}
				$yearToDateDonations = 0;
				$startDate = $thisYear . "-01-01";
				$endDate = $thisYear . "-12-31";
				$resultSet1 = executeQuery("select * from donations where (receipted_contact_id = ? or contact_id = ?) and " .
					"exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id) and donation_date between ? and ?",
					$contactId, $contactId, $startDate, $endDate);
				while ($row1 = getNextRow($resultSet1)) {
					if (!empty($row1['receipted_contact_id']) && $row1['receipted_contact_id'] != $contactId) {
						continue;
					}
					$monthlyTotals[date("n", strtotime($row1['donation_date']))] += $row1['amount'];
					$yearToDateDonations += $row1['amount'];
				}

				$startDate = $thisYear - 1 . "-01-01";
				$endDate = $thisYear - 1 . "-12-31";
				$lastYearDonations = 0;
				$resultSet1 = executeQuery("select * from donations where (receipted_contact_id = ? or contact_id = ?) and " .
					"exists (select designation_id from designations where not_tax_deductible = 0 and designation_id = donations.designation_id) and donation_date between ? and ?",
					$contactId, $contactId, $startDate, $endDate);
				while ($row1 = getNextRow($resultSet1)) {
					if (!empty($row1['receipted_contact_id']) && $row1['receipted_contact_id'] != $contactId) {
						continue;
					}
					$lastYearDonations += $row1['amount'];
				}
				$designationDescription = getFieldFromId("alias", "designations", "designation_id", $row['designation_id']);
				if (empty($designationDescription)) {
					$designationDescription = getFieldFromId("description", "designations", "designation_id", $row['designation_id']);
				}
				$postValues[] = array("substitution_name" => array("data_value" => "receipt_number"), "substitution_value" => array("data_value" => $row['donation_id']));
				$postValues[] = array("substitution_name" => array("data_value" => "donation_date"), "substitution_value" => array("data_value" => date('m/d/y', strtotime($row['donation_date']))));
				$postValues[] = array("substitution_name" => array("data_value" => "designation_code"), "substitution_value" => array("data_value" => getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id'])));
				$postValues[] = array("substitution_name" => array("data_value" => "designation"), "substitution_value" => array("data_value" => $designationDescription . (!empty($row['anonymous_gift']) ? " (Anonymous)" : "")));
				$postValues[] = array("substitution_name" => array("data_value" => "designation_description"), "substitution_value" => array("data_value" => $designationDescription . (!empty($row['anonymous_gift']) ? " (Anonymous)" : "")));
				$postValues[] = array("substitution_name" => array("data_value" => "payment_method"), "substitution_value" => array("data_value" => getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id'])));
				$postValues[] = array("substitution_name" => array("data_value" => "reference_number"), "substitution_value" => array("data_value" => $row['reference_number']));
				$postValues[] = array("substitution_name" => array("data_value" => "gift_amount"), "substitution_value" => array("data_value" => number_format($row['amount'], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "amount"), "substitution_value" => array("data_value" => number_format($row['amount'], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_1_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[1], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_2_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[2], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_3_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[3], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_4_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[4], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_5_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[5], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_6_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[6], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_7_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[7], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_8_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[8], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_9_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[9], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_10_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[10], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_11_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[11], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "month_12_donations"), "substitution_value" => array("data_value" => number_format($monthlyTotals[12], 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "year_to_date_donations"), "substitution_value" => array("data_value" => number_format($yearToDateDonations, 2)));
				$postValues[] = array("substitution_name" => array("data_value" => "last_year_donations"), "substitution_value" => array("data_value" => number_format($lastYearDonations, 2)));
				$addressBlock = getDisplayName($row['contact_id'], array("dont_use_company" => true));
				if (!empty($row['address_1'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $row['address_1'];
				}
				if (!empty($row['address_2'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $row['address_2'];
				}
				if (!empty($row['city'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $row['city'];
				}
				if (!empty($row['state'])) {
					$addressBlock .= (empty($addressBlock) ? "" : ", ") . $row['state'];
				}
				if (!empty($row['postal_code'])) {
					$addressBlock .= (empty($addressBlock) ? "" : " ") . $row['postal_code'];
				}
				if (!empty($row['country_id']) && $row['country_id'] != 1000) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $row['country_id']);
				}
				$notTaxDeductible = getFieldFromId("not_tax_deductible", "designations", "designation_id", $row['designation_id']);
				$postValues[] = array("substitution_name" => array("data_value" => "not_tax_deductible", "substitution_value" => ($notTaxDeductible ? "NOT tax-deductible" : "")));
				$postValues[] = array("substitution_name" => array("data_value" => "address_block"), "substitution_value" => array("data_value" => $addressBlock));
			}
		} else {
			if (!empty($_GET['contact_id']) && !empty($_GET['donation_date_from'])) {

				$parameters = array($GLOBALS['gClientId']);
				$whereStatement = "contacts.contact_id = ?";
				$parameters[] = $_GET['contact_id'];

				$donationDateParameters = array();
				$donationDateWhere = "";
				if (!empty($_GET['donation_date_from'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donation_date >= ?";
					$donationDateWhere .= "donation_date >= ?";
					$parameters[] = makeDateParameter($_GET['donation_date_from']);
					$donationDateParameters[] = makeDateParameter($_GET['donation_date_from']);
				}
				if (!empty($_GET['donation_date_to'])) {
					if (!empty($whereStatement)) {
						$whereStatement .= " and ";
					}
					$whereStatement .= "donation_date <= ?";
					if (!empty($donationDateWhere)) {
						$donationDateWhere .= " and ";
					}
					$donationDateWhere .= "donation_date <= ?";
					$parameters[] = makeDateParameter($_GET['donation_date_to']);
					$donationDateParameters[] = makeDateParameter($_GET['donation_date_to']);
				}

				$giftDetailLine = getPreference("RECEIPT_DETAIL_LINE");
				if (empty($giftDetailLine)) {
					$giftDetailLine = "%donation_date%\t%amount%\t%designation_code% - %designation_description%";
				}
				$resultSet = executeQuery("select *,(select count(*) from donations where " . $donationDateWhere . " and contact_id = contacts.contact_id) donation_count," .
					"(select sum(amount) from donations where " . $donationDateWhere . " and contact_id = contacts.contact_id) donation_total from contacts where contacts.contact_id in " .
					"(select contacts.contact_id from donations,contacts where donations.contact_id = contacts.contact_id and donations.client_id = ?" .
					(!empty($whereStatement) ? " and " . $whereStatement : "") . ")", array_merge($donationDateParameters, $donationDateParameters, $parameters));
				while ($row = getNextRow($resultSet)) {
					$details = "";
					$detailSet = executeQuery("select * from designations,donations where designations.designation_id = donations.designation_id and " .
						$donationDateWhere . " and donations.contact_id = ?", array_merge($donationDateParameters, array($row['contact_id'])));
					while ($detailRow = getNextRow($detailSet)) {
						$thisDetailLine = $giftDetailLine;
						foreach ($detailRow as $fieldName => $fieldValue) {
							if ($fieldName == "donation_date") {
								$fieldValue = date("m/d/Y", strtotime($fieldValue));
							}
							$thisDetailLine = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $thisDetailLine);
						}
						$details .= $thisDetailLine . "\n";
					}
					$postValues[] = array("substitution_name" => array("data_value" => "start_donation_date"), "substitution_value" => array("data_value" => date('F j, Y', strtotime($_GET['donation_date_from']))));
					$postValues[] = array("substitution_name" => array("data_value" => "end_donation_date"), "substitution_value" => array("data_value" => date('F j, Y', strtotime($_GET['donation_date_to']))));
					$postValues[] = array("substitution_name" => array("data_value" => "gift_count"), "substitution_value" => array("data_value" => number_format($row['donation_count'])));
					$postValues[] = array("substitution_name" => array("data_value" => "total_gifts"), "substitution_value" => array("data_value" => number_format($row['donation_total'], 2)));
					$postValues[] = array("substitution_name" => array("data_value" => "gift_detail"), "substitution_value" => array("data_value" => trim($details)));
				}

			}
		}
		foreach ($_GET as $fieldName => $fieldValue) {
			if (in_array($fieldName, array("content", "subject", "email_id", "contact_id", "donation_id", "donation_date_from", "donation_date_to", "attachment_file_id", "record_touchpoint"))) {
				continue;
			}
			$postValues[] = array("substitution_name" => array("data_value" => $fieldName), "substitution_value" => array("data_value" => $fieldValue));
		}
		?>
        <script>
            let postValues = <?= jsonEncode($postValues) ?>;
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#send_after").change(function () {
                if (!empty($(this).val())) {
                    $("#send_immediately").prop("checked", false);
                }
            })
            $("#contact_id").change(function () {
                $("#additional_emails").html("");
                $("#additional_emails_header").addClass("hidden");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_additional_emails&contact_id=" + $(this).val(), function(returnArray) {
                        if ("additional_emails" in returnArray) {
                            $("#additional_emails").html(returnArray['additional_emails']);
                            if (!empty(returnArray['additional_emails'])) {
                                $("#additional_emails_header").removeClass("hidden");
                            }
                        }
                    });
                }
            })
            $("#email_id").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_email&email_id=" + $(this).val(), function(returnArray) {
                        if ("content" in returnArray) {
                            $("#_substitutions_table").find("input[id^='substitutions_substitution_name-']").each(function () {
                                const rowNumber = $(this).attr("id").replace("substitutions_substitution_name-", "");
                                const value = $("#substitutions_substitution_value-" + rowNumber).val();
                                const find = 'abc';
                                const re = new RegExp("%" + $(this).val() + "%", 'g');
                                returnArray['content'] = returnArray['content'].replace(re, value);
                            });
                            $("#content").val(returnArray['content']);
                            if(typeof ace.edit("content-ace_editor") != 'undefined') {
                                ace.edit("content-ace_editor").setValue(returnArray['content']);
                            }
                            if(typeof CKEDITOR.instances['content'] != 'undefined') {
                                CKEDITOR.instances['content'].setData(returnArray['content']);
                            }
                        }
                        if ("subject" in returnArray) {
                            $("#_substitutions_table").find("input[id^='substitutions_substitution_name-']").each(function () {
                                const rowNumber = $(this).attr("id").replace("substitutions_substitution_name-", "");
                                const value = $("#substitutions_substitution_value-" + rowNumber).val();
                                const find = 'abc';
                                const re = new RegExp("%" + $(this).val() + "%", 'g');
                                returnArray['content'] = returnArray['content'].replace(re, value);
                            });
                            $("#subject").val(returnArray['subject']);
                        }
                    });
                }
            });
            $(document).on("tap click", "#send_email", function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    for (let instance in CKEDITOR.instances) {
                        CKEDITOR.instances[instance].updateElement();
                    }
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_email", $("#_edit_form").serialize());
                }
                return false;
            });
            for (const i in postValues) {
                addEditableListRow("substitutions", postValues[i]);
            }
            $(document).on("tap click", "#choose_myself", function () {
                $("#contact_id").val($(this).data('contact_id'));
                $("#contact_id,#email_id").trigger("change");
                return false;
            });
            $("#contact_id,#email_id").trigger("change");
        </script>
		<?php
	}

	function mainContent() {
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
		?>
        <form id="_edit_form">
            <div class="form-line" id="_email_credential_code_row">
                <label for="email_credential_code">EMail Account</label>
                <select id="email_credential_code" name="email_credential_code">
                    <option value="">[Use Default]</option>
					<?php
					$resultSet = executeQuery("select * from email_credentials where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_id = " . $GLOBALS['gUserId'] . " and " : "") . "client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['email_credential_code'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='clear-div'></div>
            </div>

            <div class="form-line" id="_contact_id_row">
                <label for="contact_id" class="required-label">Contact</label>
                <input type="text" size="10" maxlength="12" class="contact-picker-value align-right contact-picker-field validate[required,custom[integer]]"  data-conditional-required="empty($('#email_address').val())" id="contact_id" name="contact_id" value="<?= $contactId ?>">
                <select class='contact-picker-selector field-text' data-column_name='contact_id' id="contact_id_selector" name="contact_id_selector">
                    <option value="">No contact selected. Click to choose contact -></option>
                </select>
                <button class="contact-picker" data-column_name='contact_id' data-filter_where="email_address is not null<?= (empty($GLOBALS['gUserRow']['administrator_flag']) ? " and responsible_user_id = " . $GLOBALS['gUserId'] : "") ?>">Choose Contact</button>
                <button id="choose_myself" data-contact_id="<?= $GLOBALS['gUserRow']['contact_id'] ?>">Choose Myself</button>
                <div class='clear-div'></div>
            </div>

            <h2 id="additional_emails_header">Additional Emails</h2>
            <div id="additional_emails">
            </div>

            <div class="form-line" id="_email_address_row">
                <label for="email_address">Email Address</label>
                <span class='help-label'>If contact is selected, email will also be sent to contact email.</span>
                <input type="text" id="email_address" name="email_address">
                <div class='clear-div'></div>
            </div>

            <div class="form-line" id="_record_touchpoint_row">
                <label for="record_touchpoint"></label>
                <input type="checkbox" id="record_touchpoint" name="record_touchpoint" value="1"<?= (empty($_GET['record_touchpoint']) ? "" : "checked") ?>><label for="record_touchpoint" class="checkbox-label">Record as Touchpoint</label>
                <div class='clear-div'></div>
            </div>

            <div class="form-line" id="_send_immediately_row">
                <label for="send_immediately"></label>
                <input type="checkbox" id="send_immediately" name="send_immediately" checked="checked" value="1"><label for="send_immediately" class="checkbox-label">Send Immediately</label>
                <div class='clear-div'></div>
            </div>

            <div class="form-line" id="_send_after_row">
                <label for="send_after">Send Date</label>
                <span class='help-label'>Email will not be sent until this date</span>
                <input type="text" id="send_after" name="send_after" class='validate[custom[date]]'>
                <div class='clear-div'></div>
            </div>

            <div class="form-line" id="_subject_row">
                <label for="subject" class="required-label">Subject</label>
                <input type="text" size="60" maxlength="60" class="validate[required]" id="subject" name="subject" value="<?= htmlText($_GET['subject']) ?>">
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <label></label>
                <button id="send_email">Send</button>
                <div class='clear-div'></div>
            </div>
            <div class="form-line" id="_email_id_row">
                <label for="email_id">Select Existing Email</label>
                <select id="email_id" name="email_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from emails where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['email_id'] ?>"<?= ($_GET['email_id'] == $row['email_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='clear-div'></div>
            </div>
            <div class="form-line" id="_content_row">
                <label for="content" class="required-label">Content</label>
                <div class='textarea-wrapper'><textarea class="validate[required] use-ck-editor data-format-HTML" id="content" name="content"><?= htmlText($_GET['content']) ?></textarea>
                    <div class='content-builder' data-id='content'></div>
                </div>
                <div class='clear-div'></div>
            </div>
            <div class="form-line" id="_attachment_file_id">
				<?php

				$fileRow = array();
				$fileColumn = new DataColumn("attachment_file_id");
				if (!empty($_GET['attachment_file_id'])) {
					$fileRow = getRowFromId("files", "file_id", $_GET['attachment_file_id']);
				}
				if (!empty($fileRow)) {
					$fileColumn->setControlValue("initial_value", $fileRow['file_id']);
					$fileColumn->setControlValue("data_type", "hidden");
					echo "<p>File " . $fileRow['filename'] . " will be attached</p>";
					echo $fileColumn->getControl();
				} ?>
                <div class='clear-div'></div>
            </div>
            <div class="form-line" id="_substitution_values">
				<?php
				$substitutionColumn = new DataColumn("substitutions");
				$substitutionColumn->setControlValue("data_type", "custom");
				$substitutionColumn->setControlValue("control_class", "EditableList");
				$substitutions = new EditableList($substitutionColumn, $this);
				$substitutionName = new DataColumn("substitution_name");
				$substitutionName->setControlValue("data_type", "varchar");
				$substitutionName->setControlValue("form_label", "Substitution Name");
				$substitutionValue = new DataColumn("substitution_value");
				$substitutionValue->setControlValue("data_type", "text");
				$substitutionValue->setControlValue("form_label", "Substitution Value");
				$columnList = array("substitution_name" => $substitutionName, "substitution_value" => $substitutionValue);
				$substitutions->setColumnList($columnList);
				?>
                <label>Substitution Values</label>
				<?= $substitutions->getControl() ?>
                <div class='clear-div'></div>
            </div>

        </form>
		<?php
		return true;
	}

	function internalCSS() {
		?>
        #content { height: 400px; }
        #additional_emails { margin-left: 40px; }
		<?php
	}

	function hiddenElements() {
		?>
		<?php include "contactpicker.inc" ?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}

	function jqueryTemplates() {
		$substitutionColumn = new DataColumn("substitutions");
		$substitutionColumn->setControlValue("data_type", "custom");
		$substitutionColumn->setControlValue("control_class", "EditableList");
		$substitutions = new EditableList($substitutionColumn, $this);
		$substitutionName = new DataColumn("substitution_name");
		$substitutionName->setControlValue("data_type", "varchar");
		$substitutionName->setControlValue("form_label", "Substitution Name");
		$substitutionValue = new DataColumn("substitution_value");
		$substitutionValue->setControlValue("data_type", "text");
		$substitutionValue->setControlValue("form_label", "Substitution Value");
		$columnList = array("substitution_name" => $substitutionName, "substitution_value" => $substitutionValue);
		$substitutions->setColumnList($columnList);
		echo $substitutions->getTemplate();
	}
}

$pageObject = new SendEmailPage();
$pageObject->displayPage();
