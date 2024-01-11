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

$GLOBALS['gPageCode'] = "HIGHLEVELTOKEN";
require_once "shared/startup.inc";

class HighLevelTokenPage extends Page {

    function mainContent() {
        if (!empty($_GET['code'])) {
            $getTokenResult = HighLevel::getAccessToken($_GET['code']);
            if (!empty($getTokenResult)) {
                $result = HighLevel::HIGHLEVEL_DISPLAY_NAME . " Access Token set successfully. You can close this page.";
            } else {
                $result = HighLevel::HIGHLEVEL_DISPLAY_NAME . " Access Token request failed: " . $getTokenResult;
            }
        } else {
            $result = "This page must be called from Retail Store setup.";
        }
        echo '<P>' . $result . '</P>';
    }

}

$pageObject = new HighLevelTokenPage();
$pageObject->displayPage();
