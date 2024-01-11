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

$GLOBALS['gPageCode'] = "MAILCHIMPSYNC";
require_once "shared/startup.inc";

class MailChimpSyncPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "save_field":
				$valuesArray = Page::getPagePreferences();
				if (array_key_exists("field_id", $_POST)) {
					$valuesArray[$_POST['field_id']] = $_POST['field_value'];
					Page::setPagePreferences($valuesArray);
				}
				ajaxResponse($returnArray);
				break;
			case "sync_contacts":
				$valuesArray = Page::getPagePreferences();
				if (array_key_exists('sync_deletes', $_POST)) {
					$valuesArray['sync_deletes'] = $_POST['sync_deletes'];
				}
				$mailChimpAPIKey = getPreference("MAILCHIMP_API_KEY");
				$mailChimpListId = getPreference("MAILCHIMP_LIST_ID");
				if (empty($mailChimpAPIKey) || empty($mailChimpListId)) {
					$returnArray['error_message'] = "API Key or List ID are not setup. Do this in Client Preferences.";
					ajaxResponse($returnArray);
					break;
				}
				$mailChimpSync = new MailChimpSync($mailChimpAPIKey, $mailChimpListId);
				$result = $mailChimpSync->syncContacts($valuesArray);
				if (!$result) {
					$returnArray['error_message'] = $mailChimpSync->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				echo "<table class='grid-table' id='sync_categories'>";
				echo "<tr><th>MailChimp Group</th><th>Coreware Contacts</th></tr>";
				foreach ($result['groups'] as $displayInfo) {
					echo "<tr><td>" . $displayInfo[0] . "</td><td>" . $displayInfo[1] . "</td></tr>";
				}
				echo "</table>";
				?>
                <p><?= $result['skip_count'] ?> contacts skipped because of duplicate email addresses.</p>
                <p><?= $result['delete_count'] ?> MailChimp members deleted.</p>
                <p><?= $result['error_count'] ?> errors occurred because of duplicate email addresses.</p>
                <p><?= $result['update_count'] ?> MailChimp members updated.</p>
                <p><?= $result['add_count'] ?> MailChimp members added.</p>
				<?php
				$returnArray['response'] = ob_get_clean();
				ajaxResponse($returnArray);
				exit;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];

		if (!empty($_GET['batch_id'])) {
			$mailChimpAPIKey = getPreference("MAILCHIMP_API_KEY");
			$mailChimpListId = getPreference("MAILCHIMP_LIST_ID");
			if (empty($mailChimpAPIKey) || empty($mailChimpListId)) {
				$returnArray['error_message'] = "API Key or List ID are not setup. Do this in Client Preferences.";
				ajaxResponse($returnArray);
			}
			$mailChimp = new MailChimp($mailChimpAPIKey);

			$batch = $mailChimp->newBatch();
			$status = $batch->checkStatus($_GET['batch_id']);
			var_dump($status);
			return true;
		}

		$valuesArray = Page::getPagePreferences();
		?>
        <div id="sync_contents">
            <form id="_sync_form">

                <div class="basic-form-line">
                    <label>Limit Product Purchases to last X Days</label>
                    <span class="help-label">leave blank to include all</span>
                    <input tabindex="10" type="text" size="12" class="align-right validate[custom[integer]] limit-field" id="product_days" name="product_days" value="<?= $valuesArray['product_days'] ?>">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <label>Limit Donations to last X Days</label>
                    <span class="help-label">leave blank to include all</span>
                    <input tabindex="10" type="text" size="12" class="align-right validate[custom[integer]] limit-field" id="donation_days" name="donation_days" value="<?= $valuesArray['donation_days'] ?>">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <input tabindex="10" type="checkbox" id="sync_deletes" name="sync_deletes" value="1" <?= (empty($valuesArray['sync_deletes']) ? "" : " checked='checked'") ?>><label for="sync_deletes" class="checkbox-label">Sync will include deletes (contacts in MailChimp, but not in Coreware will be deleted)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <input tabindex="10" type="checkbox" class="limit-field" id="include_all" name="include_all" value="1" <?= (empty($valuesArray['include_all']) ? "" : " checked='checked'") ?>><label for="include_all" class="checkbox-label">Include ALL contacts (not just those in MailChimp groups)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>

        </div>
        <p>Sync is sent as a batch to MailChimp. Results may not appear in your MailChimp right away.</p>
		<?php
		$mailChimpAPIKey = getPreference("MAILCHIMP_API_KEY");
		$mailChimpListId = getPreference("MAILCHIMP_LIST_ID");
		if (empty($mailChimpAPIKey) || empty($mailChimpListId)) {
			echo "<p>API Key or List ID are not setup. Do this in Client Preferences.</p>";
			return true;
		}
		$mailChimp = new MailChimp($mailChimpAPIKey);
		$mailChimpList = $mailChimp->get("lists/" . $mailChimpListId);
		echo "<p>Currently, the list has " . (empty($mailChimpList['stats']['member_count']) ? "no" : $mailChimpList['stats']['member_count']) . " member" . ($mailChimpList['stats']['member_count'] == 1 ? "" : "s");
		?>
        <p>
            <button id="sync_contacts">Sync Contacts</button>
        </p>

		<?php
		$batchId = $_GET['batch_id'];
		if (!empty($batchId) && $GLOBALS['gUserRow']['superuser_flag']) {
			$mailChimp = new MailChimp($mailChimpAPIKey);
			$batch = $mailChimp->newBatch($batchId);
			$response = $batch->checkStatus();
			echo "<pre>";
			var_dump($response);
			echo "</pre>";
			$lastResponse = $mailChimp->getLastResponse();
			echo "<pre>";
			var_dump($lastResponse);
			echo "</pre>";
		}
		if (!empty($_GET['show_members']) && $GLOBALS['gUserRow']['superuser_flag']) {
			$mailChimp = new MailChimp($mailChimpAPIKey);
			echo "<pre>";
			var_dump($mailChimpList);
			echo "</pre>";
			$mailChimpMembers = $mailChimp->get("lists/" . $mailChimpListId . "/members", array("count" => $mailChimpList['stats']['member_count']));
			echo "<pre>";
			var_dump($mailChimpMembers);
			echo "</pre>";
		}
		?>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(".limit-field").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_field", { field_id: $(this).attr("id"), field_value: ($(this).is("input[type=checkbox]") ? ($(this).prop("checked") ? "1" : "0") : $(this).val()) });
            });
            $("#sync_contacts").click(function () {
                if ($("#_sync_form").validationEngine("validate")) {
                    disableButtons($(this));
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=sync_contacts", function(returnArray) {
                        if ("response" in returnArray) {
                            $("#sync_contents").html(returnArray['response']);
                        }
                        enableButtons($("#sync_contacts"));
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #sync_categories {
                margin: 40px 0;
            }
        </style>
		<?php
	}
}

$pageObject = new MailChimpSyncPage();
$pageObject->displayPage();
