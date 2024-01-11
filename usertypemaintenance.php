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

$GLOBALS['gPageCode'] = "USERTYPEMAINT";
require_once "shared/startup.inc";

class UserTypeMaintenancePage extends Page {
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables("user_type_access");
		$this->iDataSource->addColumnControl("subscription_id","help_label","Subscription this user type is tied to. When subscription expires, this user type will be removed");
		$this->iDataSource->addColumnControl("link_url","data_type","varchar");
		$this->iDataSource->addColumnControl("link_url","css-width","500px");
	}
}

$pageObject = new UserTypeMaintenancePage("user_types");
$pageObject->displayPage();
