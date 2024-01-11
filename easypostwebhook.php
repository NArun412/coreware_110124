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

$GLOBALS['gPageCode'] = "EASYPOSTWEBHOOK";
require_once "shared/startup.inc";

class EasyPostWebhookPage extends Page {

    function executePageUrlActions() {
        $returnArray = array();
        switch ($_GET['url_action']) {
            case "set_preference":
                $preferenceArray = array(['preference_code' => 'EASY_POST_CREATE_TRACKERS', 'description' => 'Create EasyPost Trackers for All shipments', 'data_type' => 'tinyint', 'client_setable' => 1,
                        'current_value'=>$_GET['easy_post_create_trackers']]);
                setupPreferences($preferenceArray);
                $returnArray['info_message'] = "Preference set successfully.";
                ajaxResponse($returnArray);
                break;
        }
    }

    function mainContent() {
        $webhookKey = getPageTextChunk("EASY_POST_WEBHOOK_KEY");

	    if(empty($_GET['key']) && (!empty($GLOBALS['gUserRow']['superuser_flag']) || !empty($GLOBALS['gUserRow']['full_client_access']))) {
	        if(empty($webhookKey)) {
	            $webhookKey = getRandomString(24);
				$pageId = $GLOBALS['gAllPageCodes']["EASYPOSTWEBHOOK"];
				executeQuery("insert into page_text_chunks (page_text_chunk_code,page_id,description,content) values ('EASY_POST_WEBHOOK_KEY',?,'Easy Post Webhook Key',?)",
					$pageId, $webhookKey);
            }
	        // php://input will not work through a redirect; need to make sure domain name is bare domain only.
	        $webhookUrl = str_replace("www.", "", getDomainName()) . "/easypost-webhook?key=" . $webhookKey;
	        $trackerStatusArray = array("PRE_TRANSIT", "IN_TRANSIT", "OUT_FOR_DELIVERY", "DELIVERED", "AVAILABLE_FOR_PICKUP", "RETURN_TO_SENDER", "FAILURE", "CANCELLED", "ERROR", "UNKNOWN");
	        ?>
			<P>To enable tracking notifications from EasyPost, go to <a href="https://www.easypost.com/account/webhooks-and-events">Webhooks and Events</a> on the EasyPost dashboard.</P>
			<p>Click on Add Webhook, enter the URL below, and click "Create Webhook":</p>
            <div class='basic-form-line'>
                <input id="_webhook_url" type="text" size="100" value="<?= $webhookUrl ?>">
            </div>
			<p>Once this has been created, EasyPost will send tracking events each time a label is created or a shipment has new tracking information.</p>
			<p>To send updates to the recipient of a shipment, use the following codes to create emails and/or text messages (notifications will only be sent if an email or text message exists for that status):</p>
			<ul>
				<?php
				foreach($trackerStatusArray as $thisStatus) {
                    $emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_TRACKING_EMAIL_$thisStatus");
                    if(!empty($emailId) && canAccessPageCode("EMAILMAINT")) {
                        echo "<li><a href='/emails?clear_filter=true&url_page=show&primary_id=$emailId'>RETAIL_STORE_TRACKING_EMAIL_$thisStatus</a> - Created</li>";
                    } else {
                        echo "<li>RETAIL_STORE_TRACKING_EMAIL_$thisStatus</li>";
                    }
				}
				?>
			</ul>
            <p>Tracking events can also update the status of Order Items in that shipment. To do this, create Order Item Statuses matching the EasyPost Status codes:</p>
            <ul>
                <?php
                foreach($trackerStatusArray as $thisStatus) {
                    $orderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", $thisStatus);
                    if(!empty($orderItemStatusId) && canAccessPageCode("EMAILMAINT")) {
                        echo "<li><a href='/order-item-status?clear_filter=true&url_page=show&primary_id=$orderItemStatusId'>$thisStatus</a> - Created</li>";
                    } else {
                        echo "<li>$thisStatus</li>";
                    }
                }
                ?>
            </ul>
            <p>Tracking events can be sent for any tracking number, regardless of whether the shipment was created by EasyPost or not.  NOTE: EasyPost charges per tracker for non-EasyPost shipments.</p>

            <div class='basic-form-line'>
                <input type='checkbox' id='easy_post_create_trackers' name='easy_post_create_trackers' value="1" <?= (getPreference("EASY_POST_CREATE_TRACKERS") ? "checked" : "") ?>>
                <label class="checkbox-label" for='easy_post_create_trackers'>Create EasyPost Trackers for All Shipments</label>
            </div>
            <?php
			return;
        }

	    $testMode = $GLOBALS['gDevelopmentServer'];
	    $easyPostApiKey = getPreference($testMode ? "EASY_POST_TEST_API_KEY" : "EASY_POST_API_KEY");

        if (!empty($easyPostApiKey)) {
            if(empty($webhookKey)) {
                addProgramLog("EasyPost webhook event received, but no key defined.  Do this at /easypost-webhook.");
                echo jsonEncode(["error_message"=>"EasyPost webhook event received, but no key defined"]);
                http_response_code(401);
                exit;
            }
            if($_GET['key'] != $webhookKey) {
                addProgramLog("EasyPost webhook event received, but key does not match.  Update webhook URL at https://www.easypost.com/account/webhooks-and-events.");
                echo jsonEncode(["error_message"=>"EasyPost webhook event received, but key does not match"]);
                http_response_code(401);
                exit;
            }
            $headerInput = file_get_contents("php://input");
            $programLogId = addProgramLog("EasyPost webhook event received.\n\nInput: " . $headerInput );
            if (empty($_POST) && !empty($headerInput)) {
                if (substr($headerInput, 0, 1) == "[" || substr($headerInput, 0, 1) == "{") {
                    $_POST = json_decode($headerInput, true);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        addProgramLog("\n\nBad Request: JSON format error", $programLogId);
                        echo jsonEncode(["error_message"=>"Bad Request: JSON format error"]);
                        http_response_code(400);
                        exit;
                    }
                } else {
                    parse_str($headerInput, $_POST);
                }
            }
            if (array_key_exists("json_post_parameters", $_POST)) {
                try {
                    $postParameters = json_decode($_POST['json_post_parameters'], true);
                    $_POST = array_merge($_POST, $postParameters);
                    unset($_POST['json_post_parameters']);
                } catch (Exception $e) {
                }
            }
            if(empty($_POST)) {
                addProgramLog("\n\nBad Request: No data", $programLogId);
                echo jsonEncode(["error_message"=>"Bad Request: No data"]);
                http_response_code(400);
                exit;
            }
            if(!$testMode && $_POST['mode'] != 'production') {
                addProgramLog("\n\nNon-production webhook event received in production mode.", $programLogId);
                echo jsonEncode(["error_message"=>"Non-production webhook event received in production mode"]);
                http_response_code(400);
                exit;
            }
            switch ($_POST['description']) {
                case "tracker.updated":
                    $trackingResult = $_POST['result'];
                    $previousStatus = $_POST['previous_attributes']['status'];
                    $trackingNumber = $trackingResult['tracking_code'];
                    $isOrderShipment = true;
                    $shipmentRow = getRowFromId( "order_shipments", "tracking_identifier", $trackingNumber);
                    if(empty($shipmentRow)) {
                        $isOrderShipment = false;
                        $shipmentRow = getRowFromId("shipments", "tracking_identifier", $trackingNumber);
                    }
                    if(empty($shipmentRow)) {
                        addProgramLog("\n\nTracking number " . $trackingNumber . " not found for tracking event.", $programLogId);
                        http_response_code(200);
                        exit;
                    }
                    $trackingInfoLines = array("--- EasyPost Tracking ---",
                        "Status: " . $trackingResult['status'],
                        "Tracking URL: " . $trackingResult['public_url'],
                        "Updated: " . date("m/d/Y g:ia", strtotime($trackingResult['updated_at'])));
                    if(stristr($shipmentRow['notes'], $trackingInfoLines[0]) === false) { // No tracking in notes yet - add
                        $shipmentRow['notes'] .= (empty($shipmentRow['notes']) ? "" : "\n") . implode("\n", $trackingInfoLines);
                    } else { // Tracking already exists in notes - replace
                        $newNotes = "";
                        $trackingInfoLine = -1;
                        foreach(getContentLines($shipmentRow['notes']) as $line) {
                            if($line == $trackingInfoLines[0]) {
                                $trackingInfoLine = 0;
                            }
                            if($trackingInfoLine > -1) {
                                $newNotes .= $trackingInfoLines[$trackingInfoLine] . "\n";
                                $trackingInfoLine++;
                                if($trackingInfoLine > 3) {
                                	$trackingInfoLine = -1;
								}
                            } else {
                                $newNotes .= $line . "\n";
                            }
                        }
                        $shipmentRow['notes'] = $newNotes;
                    }
                    if($isOrderShipment) {
                        executeQuery("update order_shipments set notes = ? where order_shipment_id = ?", $shipmentRow['notes'], $shipmentRow['order_shipment_id']);
                        $result = "Order ". $shipmentRow['order_id'] . ", Shipment ID " . $shipmentRow['order_shipment_id']
                            . " updated with tracking status: " . $trackingResult['status'];
                        if($previousStatus != $trackingResult['status']) { // don't send notifications if status hasn't changed
                            $emailCode = "RETAIL_STORE_TRACKING_EMAIL_" . strtoupper($trackingResult['status']);
                            $emailId = getFieldFromId('email_id', "emails", "email_code", $emailCode);
                            if (empty($emailId)) {
                                $emailId = getFieldFromId('text_message_id', "text_messages", "text_message_code", $emailCode);
                            }
                            if (!empty($emailId)) {
                                $orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $shipmentRow['order_shipment_id']);
                                $orderRow = getRowFromId("orders", "order_id", $orderShipmentRow['order_id']);
                                $contactRow = Contact::getContact($orderRow['contact_id']);
                                $emailAddress = getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id", "orders", "order_id", $orderShipmentRow['order_id']));
                                $substitutions = array_merge($orderShipmentRow, $orderRow, $contactRow, $trackingResult);
                                if (!empty($emailAddress)) {
                                    sendEmail(array("email_id" => $emailId, "email_addresses" => $emailAddress, "substitutions" => $substitutions, "contact_id" => $orderRow['contact_id']));
                                    $result .= "; Notification sent to " . $emailAddress;
                                }
                            } else {
                                $result .= "; No corresponding notification found";
                            }
                            $orderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", strtoupper($trackingResult['status']));
                            if(!empty($orderItemStatusId)) {
                                $dataTable = new DataTable("order_items");
                                $dataTable->setSaveOnlyPresent(true);
                                $orderItemResult = executeQuery("select * from order_items where order_item_id in (select order_item_id from order_shipment_items where order_shipment_id = ?)", $shipmentRow['order_shipment_id']);
                                $GLOBALS['gChangeLogNotes'] = "EasyPost tracker update received";
                                $updatedCount = 0;
                                while($orderItemRow = getNextRow($orderItemResult)) { // Process in a loop to trigger actions
                                    $dataTable->saveRecord(["primary_id"=>$orderItemRow['order_item_id'], "name_values"=>["order_item_status_id"=>$orderItemStatusId]]);
                                    $updatedCount++;
                                }
                                $GLOBALS['gChangeLogNotes'] = "";
                                $result .= "; Order Item status set for $updatedCount order items.";
                            } else {
                                $result .= "; No corresponding order item status found";
                            }
                        }
                    } else {
                        executeQuery("update shipments set notes = ? where shipment_id = ?", $shipmentRow['notes'], $shipmentRow['shipment_id']);
                        $result = "; Shipment ID " . $shipmentRow['shipment_id'] . " updated with tracking status.";
                    }
                    break;
                default:
                    $result = "Webhook event '" . $_POST['description'] . "' has no corresponding action.";
                    break;
            }
            addProgramLog("\n" . $result, $programLogId);
            echo jsonEncode(["info_message"=>"Tracking Event received"]);
            http_response_code(200);
            exit;
        } else {
            echo jsonEncode(["error_message"=>"EasyPost API key missing"]);
            http_response_code(401);
            exit;
        }
	}

    function onLoadJavascript() {
        ?>
        <script>
            $("#easy_post_create_trackers").change(function(){
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_preference&easy_post_create_trackers=" + $("#easy_post_create_trackers").prop("checked"), function() {
                });
                return false;
            });
        </script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            #_main_content ul {
                list-style: disc;
                margin: 20px 0 40px 30px;
            }

            #_main_content ul li {
                font-size: small;
                margin: 5px;
            }
        </style>
        <?php
    }
}

$pageObject = new EasyPostWebhookPage();
$pageObject->displayPage();
