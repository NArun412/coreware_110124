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

$GLOBALS['gPageCode'] = "APIMETHODMAINT";
require_once "shared/startup.inc";

class ApiMethodMaintenancePage extends Page {
	function massageDataSource() {
		$this->iDataSource->addColumnControl("access_count", "readonly", true);
		$this->iDataSource->addColumnControl("api_method_parameters", "list_table_controls", array("api_parameter_id" => array("get_choices" => "apiParameterChoices", "inline-width" => "300px", "inline-max-width" => "300px")));

        if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
            $apiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_id", $_GET['primary_id']);
            if (empty($apiMethodId)) {
                return;
            }
            $resultSet = executeQuery("select * from api_methods where api_method_id = ?", $apiMethodId);
            $apiMethodRow = getNextRow($resultSet);
            $originalApiMethodCode = $apiMethodRow['api_method_code'];
            $subNumber = 1;
            $queryString = "";
            foreach ($apiMethodRow as $fieldName => $fieldData) {
                if (empty($queryString)) {
                    $apiMethodRow[$fieldName] = "";
                }
                $queryString .= (empty($queryString) ? "" : ",") . "?";
            }
            $newId = "";
            $apiMethodRow['description'] .= " Copy";
            while (empty($newId)) {
                $apiMethodRow['api_method_code'] = $originalApiMethodCode . "_" . $subNumber;
                $resultSet = executeQuery("insert into api_methods values (" . $queryString . ")", $apiMethodRow);
                if ($resultSet['sql_error_number'] == 1062) {
                    $subNumber++;
                    continue;
                }
                $newId = $resultSet['insert_id'];
            }
            $_GET['primary_id'] = $newId;
            $subTables = array("api_method_parameters");
            foreach ($subTables as $tableName) {
                $resultSet = executeQuery("select * from " . $tableName . " where api_method_id = ?", $apiMethodId);
                while ($row = getNextRow($resultSet)) {
                    $queryString = "";
                    foreach ($row as $fieldName => $fieldData) {
                        if (empty($queryString)) {
                            $row[$fieldName] = "";
                        }
                        $queryString .= (empty($queryString) ? "" : ",") . "?";
                    }
                    $row['api_method_id'] = $newId;
                    executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
                }
            }
        }
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearcounts", "Clear Access Counts");
            if ($GLOBALS['gPermissionLevel'] > _READONLY) {
                $this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
                    "disabled" => false)));
            }
		}
    }

	function apiParameterChoices($showInactive = false) {
		$apiParameterChoices = array();
		$resultSet = executeQuery("select * from api_parameters order by column_name");
		while ($row = getNextRow($resultSet)) {
			$apiParameterChoices[$row['api_parameter_id']] = array("key_value" => $row['api_parameter_id'], "description" => $row['column_name'] . " - " . $row['description'], "inactive" => ($row['inactive'] == 1));
		}
		freeResult($resultSet);
		return $apiParameterChoices;
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "clearcounts") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clearcounts", function(returnArray) {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}

    function onLoadJavascript() {
        ?>
        <script>
            <?php
            if ($GLOBALS['gPermissionLevel'] > _READONLY) {
            ?>
            $(document).on("tap click", "#_duplicate_button", function () {
                const $primaryId = $("#primary_id");
                if (!empty($primaryId.val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                    }
                }
                return false;
            });
        </script>
    <?php }
    }

	function executePageUrlActions() {
		if ($_GET['url_action'] == "clearcounts" && $GLOBALS['gPermissionLevel'] > 1) {
			executeQuery("update api_methods set access_count = 0");
			echo jsonEncode(array());
			exit;
		}
	}
}

$pageObject = new ApiMethodMaintenancePage("api_methods");
$pageObject->displayPage();
