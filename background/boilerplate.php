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
		$this->iProcessCode = "background_process_code";
	}

	function process() {

# do the process here
		if (empty($GLOBALS['gClientId'])) {
			$errorFound = true;
		}

		if ($errorFound) {
			$this->addResult("An error was found, so set the error flag. Some notifications only go out when there is an error");
			$this->iErrorsFound = true;
		}
		$this->addResult("This line is added to the background process log");
		$this->addResult("This is the second line is added to the background process log");
		$this->addResult("This third line is added to the background process log");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
