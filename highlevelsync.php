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

$GLOBALS['gPageCode'] = "HIGHLEVELSYNC";
$GLOBALS['gDefaultAjaxTimeout'] = 3600000;
require_once "shared/startup.inc";

class HighLevelSyncPage extends Page {
    function executePageUrlActions() {
        $returnArray = array();
        switch ($_GET['url_action']) {
            case "sync_contacts":
                $highLevelAccessToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ACCESS_TOKEN");
                $highLevelLocationId = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_LOCATION_ID");
                if (empty($highLevelAccessToken) || empty($highLevelLocationId)) {
                    $returnArray['error_message'] = HighLevel::HIGHLEVEL_DISPLAY_NAME . " API Key is not set. Do this in Orders->Setup.";
                    ajaxResponse($returnArray);
                    break;
                }
                $highLevel = new HighLevel($highLevelAccessToken, $highLevelLocationId);
                $result = $highLevel->syncContacts();
                if (empty($result)) {
                    $returnArray['error_message'] = "No contacts synced.";
                    ajaxResponse($returnArray);
                    break;
                }
                ob_start();
                ?>
                <p><?= $result['add_count'] ?> contacts added.</p>
                <p><?= $result['update_count'] ?> contacts updated.</p>
                <p><?= $result['error_count'] ?> errors occurred.</p>
                <p><?= $result['skipped_count'] ?> contacts skipped.</p>
                <?php
                $returnArray['response'] = ob_get_clean();
                $returnArray['total_synced_count'] = $this->getSyncedContactsCount();
                $returnArray['info_message'] = "Sync " . HighLevel::HIGHLEVEL_DISPLAY_NAME . " contacts successful.";
                ajaxResponse($returnArray);
        }
    }

    function mainContent() {
        $highLevelAccessToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ACCESS_TOKEN");
        $highLevelLocationId = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_LOCATION_ID");
        if (empty($highLevelAccessToken) || empty($highLevelLocationId)) {
            echo HighLevel::HIGHLEVEL_DISPLAY_NAME . " API Key is not set. Do this in Orders->Setup.";
            return true;
        }
        ?>
        <h2><?= HighLevel::HIGHLEVEL_DISPLAY_NAME ?> Sync</h2>
        <p>Contact Sync may take several minutes and results will not be displayed until the sync is finished.<br>
            Contacts are also automatically synced via background process.</p>
        <p>If you have a large number of contacts, you may see a "Server not responding" message, but the sync may still finish successfully. Check contacts on <?= HighLevel::HIGHLEVEL_DISPLAY_NAME ?> before retrying the sync.</p>
        <div class="basic-form-line">
            <button id="sync_contacts">Sync Contacts</button>
        </div>
        <p>Total number of contacts synced: <span id='total_synced_count'><?= $this->getSyncedContactsCount() ?></span></p>
        <div id="sync_contents"></div>
        <?php
        return true;
    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#sync_contacts").click(function () {
                if ($("#_sync_form").validationEngine("validate")) {
                    disableButtons($(this));
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=sync_contacts", function (returnArray) {
                        $("#total_synced_count").html(returnArray['total_synced_count'])
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

    function getSyncedContactsCount() {
        $count = 0;
        $identifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types",
            "contact_identifier_type_code", makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ID");
        $resultSet = executeQuery("select count(distinct contacts.contact_id) as count from contacts"
            . " join contact_identifiers on contacts.contact_id = contact_identifiers.contact_id"
            . " where contact_identifier_type_id = ? and client_id = ? and deleted = 0", $identifierTypeId, $GLOBALS['gClientId']);
        if ($row = getNextRow($resultSet)) {
            $count = $row['count'];
        }
        return $count;
    }
}

$pageObject = new HighLevelSyncPage();
$pageObject->displayPage();