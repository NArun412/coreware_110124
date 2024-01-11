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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "update_gunbroker_listings";
	}

	function process() {
        $startTime = getMilliseconds();
        $gunBrokerUpdateCount = 0;
        $resultSet = executeQuery("select * from client_preferences where client_id in (select client_id from clients where clients.inactive = 0) and preference_id = (select preference_id from preferences where preference_code = 'GUNBROKER_USERNAME')");
        while ($row = getNextRow($resultSet)) {
            if (!empty($row['preference_value'])) {
                changeClient($row['client_id']);
                try {
                    $gunBroker = new GunBroker();
                    $result = $gunBroker->autoUpdateListings();
                    if (!empty(array_filter($result))) {
                        $logEntry = "Updated Gunbroker listings for client " . $GLOBALS['gClientName']
                            . (!empty($result['listed']) ? "\nListings added: " . $result['listed'] : "")
                            . "\nListings updated with new quantity: " . $result['updated']
                            . "\nListings ended because out of stock: " . $result['ended']
                            . (!empty($result['errors']) ? "\nError(s) occurred: " . implode("\n", $result['errors']) : "");
                        $this->addResult($logEntry);
                        sendEmail(array("subject" => "Gunbroker Listings updated", "body" => $logEntry, "notification_code" => "GUNBROKER_LISTINGS"));
                        $gunBrokerUpdateCount++;
                        $logEntry = "Updated Gunbroker listings for client " . $GLOBALS['gClientName'] . GunBroker::parseResults($result);
                        $this->addResult($logEntry);
                        if (!empty(array_filter($result))) {
                            sendEmail(array("subject" => "Gunbroker Listings updated", "body" => str_replace("\n", "<br>", $logEntry), "notification_code" => "GUNBROKER_LISTINGS"));
                        }
                    }
                } catch (Exception $exception) {
                    $logEntry = "Error updating Gunbroker listings for client " . $GLOBALS['gClientName'] . ": " . $exception->getMessage();
                    $this->addResult($logEntry);
                }
            }
        }
        $endTime = getMilliseconds();
        $totalTime = getTimeElapsed($startTime, $endTime);
        $this->addResult("GunBroker Listings updated for " . $gunBrokerUpdateCount . " clients taking " . $totalTime);
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
