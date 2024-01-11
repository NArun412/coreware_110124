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

$GLOBALS['gPageCode'] = "DEVELOPERTOOLS";
require_once "shared/startup.inc";

class DeveloperToolsPage extends Page {

    protected $iWebhookSecret = "";
    function setup() {
        $this->iWebhookSecret = getPreference("DEPLOYMENT_WEBHOOK_SECRET");
        if(empty($this->iWebhookSecret)) {
            $this->iWebhookSecret = getRandomString();
            $preferenceArray = array(['preference_code' => 'DEPLOYMENT_WEBHOOK_SECRET', 'description' => 'Deployment Webhook Secret', 'data_type' => 'varchar', 'client_setable' => 0,
                'hide_system_value'=>1,'system_value'=>$this->iWebhookSecret]);
            setupPreferences($preferenceArray);
        }
        $pageId = getFieldFromId("page_id", "pages", "page_code", "NEWCODEWEBHOOK", "client_id = ?", $GLOBALS['gDefaultClientId']);
        if(empty($pageId)) {
            $insertSet = executeQuery("insert into pages (client_id, page_code, description,date_created, creator_user_id, link_name, script_filename) " .
                "values (?,'NEWCODEWEBHOOK','Newcode Webhook',CURRENT_DATE,?,'newcode','newcodewebhook.php')", $GLOBALS['gDefaultClientId'],
                $GLOBALS['gUserId']);
            executeQuery("insert into page_access (page_id, public_access, permission_level) values (?,1,3)", $insertSet['insert_id']);
        }
    }

    function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "newcode":
                if(updateFieldById("run_immediately", 1, "background_processes", "background_process_code", "run_newcode")) {
                    $returnArray['results'] = "newcode set to run immediately.";
                    addProgramLog("newcode run by " . getUserDisplayName());
                } else {
                    $returnArray['results'] = "setting newcode to run failed. Check that the background process exists.";
                }
                removeCachedData("system_version", gethostname(), true);
				ajaxResponse($returnArray);
				break;
            case "get_servers":
                $serverArray = getPreference("DEPLOYMENT_WEBHOOK_SERVERS");
                if(!empty($serverArray)) {
                    $serverArray = json_decode($serverArray, true);
                }
                $servers = array();
                foreach ($serverArray as $thisServer) {
                    $rowValues = array();
                    $rowValues['link_url'] = array("data_value" => $thisServer['link_url'], "crc_value" => getCrcValue($thisServer['link_url']));
                    $rowValues['secret'] = array("data_value" => $thisServer['secret'], "crc_value" => getCrcValue($thisServer['secret']));
                    $rowValues['inactive'] = array("data_value" => $thisServer['inactive'], "crc_value" => getCrcValue($thisServer['inactive']));
                    $rowValues['last_result'] = array("data_value" => $thisServer['last_result'], "crc_value" => getCrcValue($thisServer['last_result']));
                    $servers[] = $rowValues;
                }
                $returnArray['servers'] = $servers;
                ajaxResponse($returnArray);
                break;
            case "save_config":
                $serverArray = getPreference("DEPLOYMENT_WEBHOOK_SERVERS");
                $lastResults = array();
                if(!empty($serverArray)) {
                    $serverArray = json_decode($serverArray, true);
                    foreach($serverArray as $thisServer) {
                        $lastResults[$thisServer['link_url']] = $thisServer['last_result'];
                    }
                }
                $servers = array();
                $index = 1;
                while (array_key_exists("webhook_servers_link_url-" . $index, $_POST)) {
                    $servers[] = array(
                        "link_url" => $_POST['webhook_servers_link_url-' . $index],
                        "secret" => trim($_POST['webhook_servers_secret-' . $index]),
                        "inactive"=> $_POST['webhook_servers_inactive-' . $index],
                        "last_result" => (array_key_exists($_POST['webhook_servers_link_url-' . $index], $lastResults) ?
                            $lastResults[$_POST['webhook_servers_link_url-' . $index]] : "")
                    );
                    $index++;
                }
                $preferenceArray = array(['preference_code' => 'DEPLOYMENT_WEBHOOK_SERVERS', 'description' => 'Deployment Webhook Servers', 'data_type' => 'varchar', 'client_setable' => 0,
                    'hide_system_value'=>1,'system_value'=>jsonEncode($servers)]);
                setupPreferences($preferenceArray);
                $returnArray['info_message'] = "Servers set successfully.";
                ajaxResponse($returnArray);
                break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#newcode_button").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=newcode", function(returnArray) {
                    $("#newcode_button").closest("p").html(returnArray['results']);
                });
                return false;
            });
            $("#save_config").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_config", $("#_setup_form").serialize(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_test_credentials").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $('body').data('just_saved', 'true');
                        $("#_test_credentials").html("Configuration Saved Successfully").addClass("green-text");
                    }
                });
                return false;
            });
            function loadServers() {
                if ($("#_webhook_servers_table .editable-list-data-row").length == 0) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_servers", function (returnArray) {
                        if ("servers" in returnArray) {
                            returnArray["servers"].forEach(function (row) {
                                addEditableListRow("webhook_servers", row);
                            });
                        }
                    })
                }
            }
            loadServers();
        </script>
		<?php
	}

    function getSystemVersion($includeDbVersion = false) {
        $systemVersion = getCachedData("system_version", gethostname(), true);
        if(empty($systemVersion)) {
            // get most recently modified file or folder
            $filename = shell_exec("ls -rt " . $GLOBALS['gDocumentRoot'] . " | grep -vE 'js|cache|css' | tail -1");
            $filename = $GLOBALS['gDocumentRoot'] . "/" . trim($filename);
            if (file_exists($filename)) {
                $systemVersion = date("F d Y H:i:s T", filemtime($filename));
            }
            setCachedData("system_version", gethostname(), $systemVersion, .1, true);
        }
        return $systemVersion . ($includeDbVersion ? ", v." . $GLOBALS['gAllPreferences']['DATABASE_VERSION']['system_value'] : "");
    }

    function getWebhookServersControl() {
        $serversColumn = new DataColumn("webhook_servers");
        $serversColumn->setControlValue("data_type", "custom");
        $serversColumn->setControlValue("control_class", "EditableList");
        $serversColumn->setControlValue("classes", "align-center");
        $serversControl = new EditableList($serversColumn, $this);
        $columns = array("link_url" => array("data_type" => "varchar", "form_label" => "Payload URL"),
            "secret" =>array("data_type" => "varchar", "form_label" => "Secret"),
            "inactive" => array("data_type" => "tinyint", "form_label" => "Inactive"),
            "last_result" => array("data_type" => "varchar", "form_label" => "Last Result", "readonly" => true));
        $columnList = array();
        foreach ($columns as $columnName => $thisColumn) {
            $dataColumn = new DataColumn($columnName);
            foreach ($thisColumn as $controlName => $controlValue) {
                $dataColumn->setControlValue($controlName, $controlValue);
            }
            $columnList[$columnName] = $dataColumn;
        }
        $serversControl->setColumnList($columnList);
        return $serversControl;
    }

	function mainContent() {
        if(empty($GLOBALS['gUserRow']['superuser_flag'])) {
            echo "Only super users can run newcode.";
            return;
        }
        $systemVersion = $this->getSystemVersion(true);
        $newcodeRow = getRowFromId("background_processes", "background_process_code", "run_newcode");
        $lastRun = date("F d Y H:i:s T", strtotime($newcodeRow["last_start_time"]));
        $newcodePending = !empty($newcodeRow['run_immediately']);
        // php://input will not work through a redirect; need to make sure domain name is bare domain only.
        $webhookUrl = str_replace("www.", "", getDomainName()) . "/newcode";
		?>
        <p class="align-center">Before running newcode, make sure there is no code in the repository that hasn't been tested or has syntax errors.</p>
        <div class="basic-form-line">
        <table class="grid-table align-center">
            <tr><th>Current system version</th><td><?= $systemVersion ?></td></tr>
            <tr><th>Newcode last run</th><td><?= $lastRun ?></td></tr>
        </table>
        </div>
        <p class="align-center basic-form-line">
            <?= ($newcodePending ? "Newcode is set to run immediately." : '<button id="newcode_button">Set newcode to run immediately</button>') ?>
        </p>
        <?php if($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
            // only show webhook creation details on primary client
            ?>
            <div class="clear-div"></div>
            <p class="align-center">To run newcode automatically when the repository is updated, register the following
                webhook with GitHub (click values to copy):</p>
            <div class="basic-form-line">
            <table class="grid-table align-center">
                <tr>
                    <th>Payload URL</th>
                    <td><span class='copy-to-clipboard copy-text'><?= $webhookUrl ?></span></td>
                </tr>
                <tr>
                    <th>Content Type</th>
                    <td>application/json</td>
                </tr>
                <tr>
                    <th>Secret</th>
                    <td><span class='copy-to-clipboard copy-text'><?= $this->iWebhookSecret ?></span></td>
                </tr>
            </table>
            </div>
            <?php if (empty(getPreference("DEPLOYMENT_WEBHOOK_PROXY"))) { ?>
                <p class="align-center">This server can be configured as a repeater to send webhooks to other coreFORCE
                    servers. All servers should be using the same branch.<br>
                    Enter the server newcode URLs and secrets below.</p>
                <div class="basic-form-line">
                <form id="_setup_form">
                    <?php
                    $webhookServersControl = $this->getWebhookServersControl();
                    echo $webhookServersControl->getControl();
                    ?>
                    <div class="basic-form-line align-center">
                        <button id="save_config">Save Configuration</button>
                    </div>
                </form>
                </div>
                <?php
            } else { ?>
                <p class="align-center">This server is receiving webhooks repeated through another coreFORCE server:
                    <?= getPreference("DEPLOYMENT_WEBHOOK_PROXY") ?>.</p>
                <?php
            }
        }
	}

    function jqueryTemplates() {
        $control = $this->getWebhookServersControl();
        echo $control->getTemplate();
    }
}

$pageObject = new DeveloperToolsPage();
$pageObject->displayPage();
