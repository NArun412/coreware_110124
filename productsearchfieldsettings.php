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

$GLOBALS['gPageCode'] = "PRODUCTSEARCHFIELDSETTINGS";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function afterSaveChanges($nameValues,$actionPerformed) {
		executeQuery("update products set reindex = 1 where client_id = ?",$GLOBALS['gClientId']);
		return true;
	}
}

$pageObject = new ThisPage("product_search_field_settings");
$pageObject->displayPage();
