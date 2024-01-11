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

$GLOBALS['gPageCode'] = "APISESSIONMAINT";
require_once "shared/startup.inc";

class ApiSessionMaintenancePage extends Page {

	function setup() {
		$this->iDataSource->addColumnControl("user_id", "readonly", true);
		$this->iDataSource->addColumnControl("device_id", "readonly", true);
		$this->iDataSource->addColumnControl("session_identifier", "readonly", true);
		$this->iDataSource->addColumnControl("last_used", "readonly", true);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "save"));
		}
	}
}

$pageObject = new ApiSessionMaintenancePage("api_sessions");
$pageObject->displayPage();
