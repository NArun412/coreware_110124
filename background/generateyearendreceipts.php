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

class YearEndReceiptBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "generate_year_end_receipts";
	}

	function process() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "YEAR_END_CRITERIA");

		$resultSet = executeQuery("select * from client_preferences where preference_id = ? order by client_id", $preferenceId);
		$this->addResult($resultSet['row_count'] . " Year-end processes found");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
            $this->addResult("Parameters for '" . $GLOBALS['gClientName'] . "': " . $row['preference_value']);

			$parameters = json_decode($row['preference_value'], true);
			if (!is_array($parameters) || empty($parameters) || empty($parameters['year']) || empty($parameters['user_id'])) {
				executeQuery("delete from client_preferences where client_preference_id = ?", $row['client_preference_id']);
				$this->addResult("Invalid parameters for client " . $row['client_id']);
				continue;
			}

			$result = Donations::processYearEndReceipts($parameters);
			if ($result === false) {
				$this->addResult("Error processing receipts for client ID " . $GLOBALS['gClientId']);
			} else if (is_array($result)) {
				$this->addResult($result['contact_count'] . " contacts with donations, " . $result['skip_count'] . " skipped, " . $result['email_count'] . " emails sent, " . $result['download_count'] . " receipts included in CSV download for year-end receipts for " .
					$parameters['year'] . " run by " . getUserDisplayName($parameters['user_id']));
			} else {
				$this->addResult($result);
			}
			executeQuery("delete from client_preferences where client_preference_id = ? and client_id = ?",$row['client_preference_id'],$GLOBALS['gClientId']);
		}
	}
}

$backgroundProcess = new YearEndReceiptBackgroundProcess();
$backgroundProcess->startProcess();
