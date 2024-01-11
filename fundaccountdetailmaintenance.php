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

$GLOBALS['gPageCode'] = "FUNDACCOUNTDETAILMAINT";
require_once "shared/startup.inc";

class FundAccountDetailMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("pay_period_id"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("fund_account_id in (select fund_account_id from fund_accounts where client_id = " . $GLOBALS['gClientId'] . ")");
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['_permission'] = array("data_value" => (empty($returnArray['pay_period_id']['data_value']) ? $GLOBALS['gPermissionLevel'] : 1));
	}
}

$pageObject = new FundAccountDetailMaintenancePage("fund_account_details");
$pageObject->displayPage();
