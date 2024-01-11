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

$GLOBALS['gPageCode'] = "ERRORLOG";
require_once "shared/startup.inc";

class ErrorLogPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearerrors", "Clear Errors");
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "clearerrors") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clearerrors", function(returnArray) {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}

	function executePageUrlActions() {
		if ($_GET['url_action'] == "clearerrors" && $GLOBALS['gPermissionLevel'] > 1) {
			executeQuery("delete from error_log");
			echo jsonEncode(array());
			exit;
		}
	}
}

$pageObject = new ErrorLogPage("error_log");
$pageObject->displayPage();
