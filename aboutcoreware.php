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

$GLOBALS['gPageCode'] = "ABOUTCOREWARE";
require_once "shared/startup.inc";

class AboutCorewarePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "agree_to_terms":
				executeQuery("update users set agreement_date = current_date where user_id = ?", $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#terms_agreement", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=agree_to_terms", function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#_terms_agreement_row").remove();
                    }
                });
            });
        </script>
		<?php
	}

	function agreementControl() {
		if (empty($GLOBALS['gUserRow']['agreement_date']) && (!empty($GLOBALS['gUserRow']['full_client_access']) || !empty($GLOBALS['gUserRow']['superuser_flag']))) {
			?>
            <div class='form-line' id="_terms_agreement_row">
                <input type='checkbox' id='terms_agreement' name='terms_agreement' value='1'><label
                        for='terms_agreement' class='checkbox-label red-text'>I agree to the Terms of Service, Privacy
                    Policy, and Acceptable Use Policy of Coreware</label>
                <div class='clear-div'></div>
            </div>
			<?php
		}
	}
}

$pageObject = new AboutCorewarePage();
$pageObject->displayPage();
