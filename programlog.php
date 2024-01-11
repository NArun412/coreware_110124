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

$GLOBALS['gPageCode'] = "PROGRAMLOG";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearlog", "Clear My Log Entries");
				$filters = array();
				$filters['my_logs'] = array("form_label" => "My Log Entries", "where" => "user_id = " . $GLOBALS['gUserId'], "data_type" => "tinyint");

				$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			}
		}
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "clearlog") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clearlog", function(returnArray) {
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
		if ($_GET['url_action'] == "clearlog" && $GLOBALS['gPermissionLevel'] > 1) {
			executeQuery("delete from program_log where user_id = ?", $GLOBALS['gUserId']);
			echo jsonEncode(array());
			exit;
		}
	}
}

$pageObject = new ThisPage("program_log");
$pageObject->displayPage();
