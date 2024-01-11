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

$GLOBALS['gPageCode'] = "YEARENDRECEIPTS";
require_once "shared/startup.inc";

class YearEndReceiptsPage extends Page {

	var $iYear = "";
	var $iFileTagId = "";
	var $iPreferenceId = "";

	function setup() {
		$this->iPreferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "YEAR_END_CRITERIA");
		if (empty($this->iPreferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code,description,client_setable,data_type,internal_use_only) values ('YEAR_END_CRITERIA'," .
				"'Year End Criteria',1,'tinyint',1)");
			$this->iPreferenceId = $resultSet['insert_id'];
		}

		$this->iYear = date("Y") - 1;
		$this->iFileTagId = getFieldFromId("file_tag_id", "file_tags", "file_tag_code", "YEAR_END_RECEIPT_" . $this->iYear);
		if (empty($this->iFileTagId)) {
			$insertSet = executeQuery("insert into file_tags (client_id,file_tag_code,description) values (?,?,?)",
				$GLOBALS['gClientId'], "YEAR_END_RECEIPT_" . $this->iYear, "Year End Receipt for " . $this->iYear);
			$this->iFileTagId = $insertSet['insert_id'];
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "queue_receipts":
			case "remove_queue":
				executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $this->iPreferenceId);
				if ($_GET['url_action'] == "remove_queue") {
					$returnArray['info_message'] = "Year end receipts process removed from queue and will NOT be run.";
					ajaxResponse($returnArray);
					break;
				}
				$parameters = $_POST;
				$parameters['user_id'] = $GLOBALS['gUserId'];
				$parameters['year'] = $this->iYear;

				# Queue if a real run, but run immediately if test

				if (empty($parameters['test_mode']) && empty($parameters['contact_id'])) {
					executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $this->iPreferenceId, jsonEncode($parameters));
					$returnArray['info_message'] = "Year end receipts process queued up and will run tonight. If you wish to change the criteria, do so before midnight MST.";
				} else {
					if ($parameters['test_mode']) {
						$parameters['total_giving'] = "";
					}
					$result = Donations::processYearEndReceipts($parameters);
					$returnArray['info_message'] = ($parameters['test_mode'] ? "Test Completed" : "Receipt Produced");
				}
				ajaxResponse($returnArray);
				break;
			case "get_statistics":
				$parameters = $_POST;
				$statistics = "";
				if (empty($parameters['remove_existing'])) {
					$statistics .= "<p>No existing receipts will be removed.</p>";
				} else {
					$count = 0;
					$resultSet = executeQuery("select count(*) from files where client_id = ? and file_tag_id = ? and file_id in (select file_id from contact_files)", $GLOBALS['gClientId'], $this->iFileTagId);
					if ($row = getNextRow($resultSet)) {
						$count = $row['count(*)'];
					}
					$statistics .= "<p>" . ($count == 0 ? "No" : $count) . " existing receipt" . ($count == 1 ? "" : "s") . " will be " . ($count > 0 ? "<strong>PERMANENTLY</strong>" : "") . " removed.</p>";
				}
				$parameters['user_id'] = $GLOBALS['gUserId'];
				$parameters['year'] = $this->iYear;
				$parameters['counts_only'] = true;

				# Process but for counts only

				$result = Donations::processYearEndReceipts($parameters);
				if (is_array($result)) {
					$emailCount = $result['email_count'];
					$downloadCount = $result['download_count'];
					$contactCount = $result['contact_count'];
					$skipCount = $result['skip_count'];
					$statistics .= "<p>" . ($contactCount == 0 ? "No" : $contactCount) . " contact" . ($contactCount == 1 ? "" : "s") . " found with any donations.</p>";
					$statistics .= "<p>" . ($skipCount == 0 ? "No" : $skipCount) . " contact" . ($skipCount == 1 ? "" : "s") . " skipped.</p>";
					$statistics .= "<p>" . ($emailCount == 0 ? "No" : $emailCount) . " email" . ($emailCount == 1 ? "" : "s") . " will be sent.</p>";
					$statistics .= "<p>" . ($downloadCount == 0 ? "No" : $downloadCount) . " contact" . ($downloadCount == 1 ? "" : "s") . " will be included in the download CSV.</p>";
				} else {
					$statistics .= "<p>" . $result . "</p>";
				}
				$returnArray['statistics'] = $statistics;

				ajaxResponse($returnArray);

				break;
		}
	}

	function onLoadJavascript() {
		?>
        <!--suppress JSUnresolvedFunction -->
        <script>
            $(document).on("change", ".statistics-changed", function () {
                getStatistics();
                return false;
            });
            $(document).on("click", "#remove_queue", function () {
                const $preferenceForm = $("#_preference_form");
                if ($preferenceForm.validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_queue", $preferenceForm.serialize());
                }
                return false;
            });
            $(document).on("click", "#queue_receipts", function () {
                const $preferenceForm = $("#_preference_form");
                if ($preferenceForm.validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=queue_receipts", $preferenceForm.serialize(), function (returnArray) {
                        if ("checkUserNotifications" in window) {
                            checkUserNotifications();
                        }
                    });
                }
                return false;
            });
            $(document).on("change", "#contact_id", function () {
                if (empty($("#contact_id").val())) {
                    $("#queue_receipts").html("Queue Receipts");
                } else {
                    $("#queue_receipts").html("Create Receipt");
                }
            });
            $(document).on("click", "#test_mode", function () {
                if ($(this).prop("checked")) {
                    $("#queue_receipts").html("Run Test");
                } else {
                    $("#queue_receipts").html("Queue Receipts");
                }
            })
            getStatistics();
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function getStatistics() {
                const $preferenceForm = $("#_preference_form");
                $("#statistics").html("");
                if (!$("#test_mode").prop("checked")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_statistics", $preferenceForm.serialize(), function (returnArray) {
                        if ("statistics" in returnArray) {
                            $("#statistics").html(returnArray['statistics']);
                        }
                    });
                }
            }
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];

		$emailId = getFieldFromId("email_id", "emails", "email_code", "YEAR_END_RECEIPT_" . $this->iYear, "inactive = 0");
		if (empty($emailId)) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", "YEAR_END_RECEIPT",  "inactive = 0");
		}
		if (empty($emailId)) {
			?>
            <p>Year End Receipt email does not exist. Go to Contacts->Email->Emails and create an email with code 'YEAR_END_RECEIPT_<?= $this->iYear ?>' or just 'YEAR_END_RECEIPT'. You can also go to Donor Management->Setup, in the Emails tab, install the default Year End Receipt email. You WILL, then, want to go to Contacts->Email->Emails and edit that email to customize it for your organization.</p>
			<?php
			echo $this->iPageData['after_form_content'];
			return true;
		}
		$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", "YEAR_END_RECEIPT_" . $this->iYear);
		if (empty($fragmentId)) {
			$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", "YEAR_END_RECEIPT");
		}
		if (empty($fragmentId)) {
			?>
            <p>Year End Receipt fragment, needed to generate the receipt PDF, does not exist. Go to Website->Fragments and create a fragment with code 'YEAR_END_RECEIPT_<?= $this->iYear ?>' or just 'YEAR_END_RECEIPT'. You can also go to Donor Management->Setup, in the Fragments tab, install the default Year End Receipt fragment. You WILL, then, want to go to Website->Fragments and edit that fragment to customize it for your organization.</p>
			<?php
			echo $this->iPageData['after_form_content'];
			return true;
		}
		$fragmentContent = getFieldFromId("content", "fragments", "fragment_id", $fragmentId);
		if (stripos($fragmentContent, str_replace("www.", "", getDomainName()) . "/getimage.php") !== false) {
			?>
            <p>The Year End Receipt fragment contains absolute references to local images. Local images need to be relative references. DON'T use "https://mydomain.com/getimage.php?code=XXXX". DO use "/getimage.php?code=XXXX".</p>
			<?php
		}
		?>
        <H2>Year End Receipts for <?= $this->iYear ?></H2>
		<?php
		$resultSet = executeQuery("select * from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $this->iPreferenceId);
		if ($resultSet['row_count'] > 0) {
			echo "<h3 class='red-text'>Year End Receipts are queued to run</h3>";
		} else {
			echo "<p>Nothing queued to run</p>";
		}
		?>

        <p>Donors that already have an existing receipt will NOT have another generated until "Remove existing receipts" is selected. After process is started, it will run as a background process in the middle of the night. You will receive a user notification when the process is done by morning. The CSV export will be attached to the User Notification. You access user notifications by clicking on the bell icon in the header.</p>
        <form id="_preference_form">

            <div class="form-line" id="_email_credential_code_row">
                <label for="email_credential_code">EMail Account</label>
                <span class='help-label'>For sending emails</span>
                <select id="email_credential_code" name="email_credential_code">
                    <option value="">[Use Default]</option>
					<?php
					$resultSet = executeQuery("select * from email_credentials where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['email_credential_code'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <input type="checkbox" class="statistics-changed" id="remove_existing" name="remove_existing" value="1"><label class="checkbox-label" for="remove_existing">Remove existing receipts - <span class='red-text'>This CANNOT be undone and, if emailing, donors will receive the year end email again.</span></label>
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <input class='statistics-changed' type="checkbox" id="include_all_csv" name="include_all_csv" value="1"><label class="checkbox-label" for="include_all_csv">Include ALL in CSV. If unchecked, only those not emailed will be included in CSV export.</label>
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <input class='statistics-changed' type="checkbox" id="send_no_emails" name="send_no_emails" value="1"><label class="checkbox-label" for="send_no_emails">Send NO emails.</label>
                <div class='clear-div'></div>
            </div>

            <p>Many email providers have a limit on how many emails can be sent each day. You can leave this blank if you are SURE your email provider doesn't have a limitation like this. Gmail DOES. If this has a value, there will be that number of minutes between sending each email. The system will send just one email every X minutes. If the system is unable to send the emails because of this limitation, the email may not go out. We recommend sending one email every 1-5 minutes, as the email limit seems to be typically be around 2000. It is your responsibility to find out what your email limit is from your email provider. If you put a value of 1 here, 1440 emails will be sent per day, a value of 2 will be 720 per day, and so forth. Basically, divide 1440 (the number of minutes in a day) by the number of minutes between emails and that is how per day will be sent. Remember that you have normal emails to send every day as ewll. So if you email ISP limit is 1000, use a value of 2. That
                means 720 emails will go out per day, leaving 280 for your other normal emails.</p>
            <div class="form-line">
                <label for='email_minutes'>Minutes between emails</label>
                <span class="help-label">Blank will mean all emails get sent at once. THIS IS NOT RECOMMENDED.</span>
                <input type="text" id="email_minutes" name="email_minutes" size="10" class="align-right statistics-changed validate[custom[integer],min[1],max[5]]" value="1">
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <label for='download_amount'>Force download (don't send email) when total giving is over</label>
                <span class="help-label">Zero or blank will ignore this field.</span>
                <input type="text" id="download_amount" name="download_amount" size="10" class="align-right statistics-changed validate[custom[number]]" data-decimal-places="2" value="">
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <label for='both_amount'>BOTH send email and include in CSV download when total giving is over this amount</label>
                <span class="help-label">Blank will ignore this field.</span>
                <input type="text" id="both_amount" name="both_amount" size="10" class="align-right statistics-changed validate[custom[number]]" data-decimal-places="2" value="">
                <div class='clear-div'></div>
            </div>

            <div class="form-line">
                <label for='total_giving'>Only send when total giving is over</label>
                <input type="text" id="total_giving" name="total_giving" size="10" class="align-right statistics-changed validate[custom[number]]" data-decimal-places="2" value="">
                <div class='clear-div'></div>
            </div>

			<?= createFormControl("contacts", "contact_id", array("not_null" => "false", "form_label" => "Contact", "help_label" => "Only produce a receipt for this contact", "classes" => "statistics-changed", "data_type" => "contact_picker")) ?>

            <div class="form-line">
                <input type="checkbox" id="test_mode" name="test_mode" value="1"><label class="checkbox-label" for="test_mode">Test mode - only ONE email will be sent, to you, CSV file will be created, and only one file will be created, attached to your contact.</label>
                <div class='clear-div'></div>
            </div>

            <p id="statistics"></p>

            <p class='error-message'></p>
            <div class="form-line">
                <button id="queue_receipts">Queue Receipts</button>
                <button id="remove_queue">Remove Receipt Process from Queue</button>
            </div>
        </form>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function hiddenElements() {
		?>
		<?php include "contactpicker.inc" ?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #statistics {
                margin: 40px 0;
            }

            #statistics p {
                margin-left: 40px;
                color: rgb(0, 80, 0);
                font-size: 1.2rem;
                font-weight: 900;
            }
        </style>
		<?php
	}
}

$pageObject = new YearEndReceiptsPage();
$pageObject->displayPage();
