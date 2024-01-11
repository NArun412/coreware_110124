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

$GLOBALS['gPageCode'] = "QUERYLOGDIRECTIVEMAINT";
require_once "shared/startup.inc";

if (empty($GLOBALS['gUserRow']['superuser_flag'])) {
	header("Location: /");
	exit;
}

class ThisPage extends Page {

	function massageDataSource() {
		$this->iDataSource->addColumnControl("user_id","readonly",true);
		$this->iDataSource->addColumnControl("user_id","get_choices","userChoices");
		$this->iDataSource->addColumnControl("user_id","default_value",$GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("page_id","get_choices","pageChoices");
	}

}

$pageObject = new ThisPage("query_log_directives");
$pageObject->displayPage();
