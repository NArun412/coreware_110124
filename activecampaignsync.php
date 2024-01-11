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

$GLOBALS['gPageCode'] = "ACTIVECAMPAIGNSYNC";
$GLOBALS['gDefaultAjaxTimeout'] = 3600000;
require_once "shared/startup.inc";

class ActiveCampaignSyncPage extends Page {
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
				$activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
				$activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
				if (empty($activeCampaignApiKey)) {
					$returnArray['error_message'] = "ActiveCampaign API Key is not set. Do this in Client Preferences.";
					ajaxResponse($returnArray);
					break;
				}
				$activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
				$result = $activeCampaign->syncContacts($valuesArray);
                if($GLOBALS['gUserRow']['superuser_flag']) {
                    $returnArray['console'] = $activeCampaign->getResultLog();
                }
				if (!$result) {
					$returnArray['error_message'] = $activeCampaign->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
                ob_start();
				echo "<table class='grid-table' id='sync_categories'>";
				echo "<tr><th>ActiveCampaign List</th><th>coreFORCE Contacts</th></tr>";
				foreach ($result['groups'] as $displayInfo) {
					echo "<tr><td>" . $displayInfo['name'] . "</td><td>" . $displayInfo['description'] . "</td></tr>";
				}
				echo "</table>";
				?>
                <p><?= $result['skip_count'] ?> contacts skipped because of duplicate email addresses.</p>
                <p><?= $result['blank_count'] ?> contacts skipped because of blank email addresses.</p>
                <p><?= $result['invalid_count'] ?> contacts skipped because of invalid email addresses.</p>
                <p><?= $result['delete_count'] ?> ActiveCampaign members deleted.</p>
                <p><?= $result['error_count'] ?> errors occurred because of duplicate email addresses.</p>
                <p><?= $result['update_count'] ?> ActiveCampaign members updated.</p>
                <p><?= $result['add_count'] ?> ActiveCampaign members added.</p>
				<?php
				$returnArray['response'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "check_analytics":
				$analyticsCodeChunkRow = getRowFromId("analytics_code_chunks", "analytics_code_chunk_id",
					getFieldFromId("analytics_code_chunk_id", "templates", "template_id", $_POST['template_id']));
				$returnArray = $analyticsCodeChunkRow;
				$returnArray['is_installed'] = (stristr($analyticsCodeChunkRow['content'], "(function(e,t,o,n,p,r,i)") !== false);
				ajaxResponse($returnArray);
				break;
			case "install_analytics":
				if (empty($_POST['analytics_code_chunk_id'])) {
					$returnArray['error_message'] = "No analytics code specified. Analytics Code must be selected in Template Maintenance first.";
					ajaxResponse($returnArray);
					break;
				}
				$activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
				$activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
				if (empty($activeCampaignApiKey)) {
					$returnArray['error_message'] = "ActiveCampaign API Key is not set. Do this in Client Preferences.";
					ajaxResponse($returnArray);
					break;
				}
				$activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
				$newContent = $activeCampaign->getAnalyticsCode();
				if (empty($newContent)) {
					$returnArray['error_message'] = $activeCampaign->getErrorMessage() ?: "An error occurred retrieving analytics code from ActiveCampaign";
					ajaxResponse($returnArray);
					break;
				}
				$existingContent = getFieldFromId("content", "analytics_code_chunks", "analytics_code_chunk_id", $_POST['analytics_code_chunk_id']);
				if (stristr($existingContent, $newContent) !== false) {
					$returnArray['error_message'] = "Analytics code is already installed.";
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable('analytics_code_chunks');
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("primary_id" => $_POST['analytics_code_chunk_id'],
					"name_values" => array("content" => $existingContent . "\n\n<!--ActiveCampaign site tracking -->\n\n" . $newContent)))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['response'] = "Analytics installed successfully.";
				ajaxResponse($returnArray);
				exit;
			case "test_deep_data":
				$activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
				$activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
				if (empty($activeCampaignApiKey)) {
					$returnArray['error_message'] = "ActiveCampaign API Key is not set. Do this in Client Preferences.";
					ajaxResponse($returnArray);
					break;
				}
				$activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
				$result = $activeCampaign->checkDeepDataConnection();
				if ($result === false) {
					$returnArray['error_message'] = $activeCampaign->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];

		$valuesArray = Page::getPagePreferences();
		$activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
		$activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
		if (empty($activeCampaignApiKey)) {
			echo "ActiveCampaign API Key is not set. Do this in Orders->Setup.";
			return true;
		}
		?>
        <h2>ActiveCampaign List Sync</h2>
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
                    <input tabindex="10" type="checkbox" id="sync_deletes" name="sync_deletes" value="1" <?= (empty($valuesArray['sync_deletes']) ? "" : " checked='checked'") ?>><label for="sync_deletes" class="checkbox-label">Sync will include deletes (contacts in ActiveCampaign, but not in Coreware will be deleted)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <input tabindex="10" type="checkbox" class="limit-field" id="include_all" name="include_all" value="1" <?= (empty($valuesArray['include_all']) ? "" : " checked='checked'") ?>><label for="include_all" class="checkbox-label">Include ALL contacts (not just those in ActiveCampaign lists)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>

        </div>
        <p>Sync is sent as a batch to ActiveCampaign. Results may not appear in ActiveCampaign right away.</p>
		<?php

		$activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
		$listResult = $activeCampaign->getLists(true);
		if (!$listResult) {
			echo sprintf("<p class='red-text'>%s</p>", $activeCampaign->getErrorMessage());
		} else {
			?>
            <div class="basic-form-line">
                <table class='grid-table' id='activecampaign_lists'>
                    <tr>
                        <th>ActiveCampaign List</th>
                        <th>coreFORCE Contacts</th>
                        <th>Members</th>
                    </tr>
					<?php
					foreach ($listResult['group_display'] as $list) {
						echo sprintf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>", $list['name'], $list['description'], $list['members']);
					}
					?>
                </table>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line">
                <button id="sync_contacts">Sync Contacts</button>
            </div>
			<?php
		}
		?>
        <h2>Deep Data Integration</h2>
        <div class="basic-form-line" id="_test_deep_data">
            <button id="test_deep_data">Test Deep Data connection</button>
        </div>

        <h2>ActiveCampaign Analytics</h2>
        <form id="_analytics_form">
            <div class="basic-form-line">
                <label for="template_id">Template</label>

                <select id="template_id" name="template_id">
					<?php
					$resultSet = executeQuery("select * from templates where client_id = ? and inactive = 0 and include_crud = 0 and template_id in (select template_id from pages where subsystem_id is null and inactive = 0)", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						echo sprintf("<option value='%s'>%s</option>", $row['template_id'], $row['description']);
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line">
                <input type="hidden" value="" name="analytics_code_chunk_id" id="analytics_code_chunk_id">
                <label for="_analytics_code_chunk">Analytics Code</label>
                <input readonly id="_analytics_code_chunk" value="[None]">
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line">
                <div class="red-text" id="_analytics_result">Analytics are NOT installed.</div>
            </div>
            <div class="basic-form-line">
                <button id="install_analytics">Install Analytics</button>
            </div>
        </form>

		<?php

		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(".limit-field").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_field", {field_id: $(this).attr("id"), field_value: ($(this).is("input[type=checkbox]") ? ($(this).prop("checked") ? "1" : "0") : $(this).val())});
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
            $("#template_id").change(function () {
                disableButtons($("#install_analytics"));
                $("#analytics_code_chunk_id").val("");
                $("#_analytics_code_chunk").val("[None]");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_analytics", $("#_analytics_form").serialize(), function(returnArray) {
                    if ("analytics_code_chunk_id" in returnArray) {
                        if (!empty(returnArray['analytics_code_chunk_id'])) {
                            $("#analytics_code_chunk_id").val(returnArray['analytics_code_chunk_id']);
                            $("#_analytics_code_chunk").val(returnArray['description']);
                        }
                    }
                    if ("is_installed" in returnArray) {
                        if (!empty(returnArray['is_installed'])) {
                            $("#_analytics_result").html("Analytics are installed.").removeClass("red-text").addClass("green-text");
                        } else {
                            $("#_analytics_result").html("Analytics are NOT installed.").removeClass("green-text").addClass("red-text");
                            if (!empty(returnArray['analytics_code_chunk_id'])) {
                                enableButtons($("#install_analytics"));
                            }
                        }
                    }
                });
                return false;
            });
            $("#install_analytics").click(function () {
                if ($("#_analytics_form").validationEngine("validate")) {
                    disableButtons($(this));
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=install_analytics", $("#_analytics_form").serialize(), function(returnArray) {
                        if ("response" in returnArray) {
                            $("#_analytics_result").html(returnArray['response']).removeClass("red-text").addClass("green-text");
                        }
                    });
                }
                return false;
            });
            $("#test_deep_data").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_deep_data", function(returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_test_deep_data").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_test_deep_data").html("Deep Data connection works").addClass("green-text");
                    }
                });
                return false;
            });
            $("#template_id").trigger("change");
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

$pageObject = new ActiveCampaignSyncPage();
$pageObject->displayPage();
