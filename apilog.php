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

$GLOBALS['gPageCode'] = "APILOG";
require_once "shared/startup.inc";

class ApiLogPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearlog", "Clear Log");
            $filters = array();
            $resultSet = executeQuery("select * from api_methods where inactive = 0 and api_method_id in (select distinct api_method_id from api_log) order by description limit 20");
            while ($row = getNextRow($resultSet)) {
                $filters['api_method_id_' . $row['api_method_id']] = array("form_label" => $row['description'], "where" => "api_method_id = " . $row['api_method_id'], "data_type" => "tinyint");
            }
            $this->iTemplateObject->getTableEditorObject()->addFilters($filters);
        }
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "clearlog") {
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
			executeQuery("delete from api_log where client_id = ?", $GLOBALS['gClientId']);
			echo jsonEncode(array());
			exit;
		}
	}
}

$pageObject = new ApiLogPage("api_log");
$pageObject->displayPage();
