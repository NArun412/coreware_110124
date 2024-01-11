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

$GLOBALS['gPageCode'] = "NEWCODEWEBHOOK";
require_once "shared/startup.inc";

$webhookSecret = getPreference("DEPLOYMENT_WEBHOOK_SECRET");

$headerInput = file_get_contents("php://input");
$logContent = "Input:\n$headerInput\n\nHeaders:\n";
foreach ($_SERVER as $key => $value) {
    if (startsWith($key, "HTTP_")) {
        $logContent .= "$key: $value\n";
    }
}
if (empty($webhookSecret)) {
    addProgramLog("Newcode webhook event received, but no secret defined. Do this in Developer Tools.\n\n$logContent");
    echo "Newcode webhook event received, but no secret defined";
    http_response_code(401);
    exit;
}
// check signature
$headerSignature = str_replace("sha256=", "", $_SERVER['HTTP_X_HUB_SIGNATURE_256']);
$signature = hash_hmac('sha256', $headerInput, $webhookSecret);
if (!hash_equals($signature, $headerSignature)) {
    addProgramLog("Newcode webhook event received, but signature does not match. Update webhook secret in GitHub.\n\n$logContent\n\nCalculated Signature: $signature");
    echo "Newcode webhook event received, but signature does not match";
    http_response_code(401);
    exit;
}
$preferenceArray = array(['preference_code' => 'DEPLOYMENT_WEBHOOK_PROXY', 'description' => 'Deployment Webhook Proxy', 'data_type' => 'varchar', 'client_setable' => 0,
    'hide_system_value' => 1, 'system_value' => $_SERVER['HTTP_X_WEBHOOK_PROXY']]);
setupPreferences($preferenceArray);
if(empty($_SERVER['HTTP_X_WEBHOOK_PROXY'])) {
    executeQuery("update preferences set system_value = null where preference_code = 'DEPLOYMENT_WEBHOOK_PROXY'");
}
// check branch
$inputArray = json_decode($headerInput, true);
$updatedBranchRef = str_replace("refs/heads/", "",strtolower(trim($inputArray['ref'])));
$branchRef = ($updatedBranchRef == "update_core_data" ? "update_core_data" : getBranchRef());
if ($updatedBranchRef != $branchRef) {
    addProgramLog("Newcode webhook event received, but the updated branch ($updatedBranchRef) is not running on this server ($branchRef). No action taken.\n"
        . "If the branch was recently changed, newcode needs to be run manually first.\n\n$logContent");
    $response = "Newcode event received, but branch is not running on this server";
    $responseHttpCode = 202;
} else {
    if($branchRef == "update_core_data") {
        $backgroundProcessRow = getRowFromId("background_processes", "background_process_code", "update_core_data");
    } else {
        $backgroundProcessRow = getRowFromId("background_processes", "background_process_code", "run_newcode");
    }
    if (updateFieldById("run_immediately", 1, "background_processes", "background_process_code", $backgroundProcessRow['background_process_code'])) {
        $programLogId = addProgramLog("{$backgroundProcessRow['description']} set to run from webhook event.\n\n$logContent");
        $response = "{$backgroundProcessRow['description']} event received";
        $responseHttpCode = 200;
    } else {
        $programLogId = addProgramLog("Setting {$backgroundProcessRow['description']} to run failed. Check that the background process exists.\n\n$logContent");
        $response = "{$backgroundProcessRow['description']} event received, but background process failed to run";
        $responseHttpCode = 202;
    }
    $webhookResults = sendDeploymentWebhook($headerInput);
    addProgramLog($webhookResults, $programLogId);
}

echo $response;
http_response_code($responseHttpCode);
exit;
