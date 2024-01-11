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

$GLOBALS['gPageCode'] = "CLIENTPREFERENCES";
require_once "shared/startup.inc";

class ClientPreferencesPage extends Page {

	function onLoadJavascript() {
		?>
        <script>
            $("#preference_id").change(function () {
                if (empty($(this).val())) {
                    $("#system_value").val("");
                    $("#preference_value_control").html("");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_preference&preference_id=" + $("#preference_id").val(), function(returnArray) {
                        if ("preference_value_control" in returnArray) {
                            $("#preference_value_control").html(returnArray['preference_value_control']['data_value']);
                            $("#system_value").val(returnArray['system_value']['data_value']);
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "change_preference":
				$returnArray['preference_id'] = array("data_value" => $_GET['preference_id']);
				$this->afterGetRecord($returnArray);
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		$controlElement = "";
		$resultSet = executeQuery("select * from preferences where preference_id = ?", $returnArray['preference_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			if ($row['hide_system_value']) {
				$returnArray['system_value']['data_value'] = "SYSTEM VALUE NOT DISPLAYED";
			} else {
				$returnArray['system_value']['data_value'] = $row['system_value'];
			}
			$validationClasses = array();
			$classes = array();
			if (strlen($row['minimum_value']) > 0) {
				$validationClasses[] = "min[" . $row['minimum_value'] . "]";
			}
			if (strlen($row['maximum_value']) > 0) {
				$validationClasses[] = "max[" . $row['maximum_value'] . "]";
			}
			$classes[] = "field-text";
			$dataValue = $returnArray['preference_value']['data_value'];
			switch ($row['data_type']) {
				case "select":
					$controlElement = "<select tabindex='10' class='field-text %classString%' data-crc_value='" .
						getCrcValue($dataValue) . "' name='preference_value' id='preference_value'>\n";
					$controlElement .= "<option value=''>[None]</option>\n";
					if (array_key_exists("choices", $row)) {
						$choices = getContentLines($row['choices']);
						foreach ($choices as $index => $choiceValue) {
							$controlElement .= "<option value='" . htmlText($choiceValue) . "' " .
								($choiceValue == $dataValue ? " selected" : "") . ">" . htmlText($choiceValue) . "</option>\n";
						}
					}
					$controlElement .= "</select>\n";
					break;
				case "int":
					$validationClasses[] = "custom[integer]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($dataValue) . "' value='" .
						htmlText($dataValue) . "' size='10' maxlength='10' name='preference_value' id='preference_value' />";
					break;
				case "decimal":
					$validationClasses[] = "custom[number]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' data-decimal-places='2' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($dataValue) . "' value='" .
						htmlText($dataValue) . "' size='10' maxlength='12' name='preference_value' id='preference_value' />";
					break;
				case "tinyint":
					$controlElement = "<select tabindex='10' class='field-text %classString%' data-crc_value='" .
						getCrcValue($dataValue) . "' name='preference_value' id='preference_value'>\n";
					$controlElement .= "<option value=''>[Use System Value]</option>\n";
					$controlElement .= "<option value='true' " . ($dataValue == "true" ? " selected" : "") . ">True</option>\n";
					$controlElement .= "<option value='false' " . ($dataValue == "false" ? " selected" : "") . ">False</option>\n";
					$controlElement .= "</select>\n";
					break;
				case "varchar":
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($dataValue) . "' value='" .
						htmlText($dataValue) . "' size='40' name='preference_value' id='preference_value' />";
					break;
				case "text":
					$controlElement = "<textarea tabindex='10' class='field-text %classString%' name='preference_value' data-crc_value='" .
						getCrcValue($dataValue) . "' id='preference_value'>" .
						htmlText($dataValue) . "</textarea>";
					break;
			}
			$validationClassString = implode(",", $validationClasses);
			if (!empty($validationClassString) && !$row['readonly']) {
				$validationClassString = "validate[" . $validationClassString . "]";
				$classes[] = $validationClassString;
			}
			$classString = implode(" ", $classes);
			$controlElement = str_replace("%classString%", $classString, $controlElement);
		}
		$returnArray['preference_value_control'] = array("data_value" => $controlElement);
	}

	function massageDataSource() {
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->setFilterWhere("preference_id not in (select preference_id from preferences where internal_use_only = 1)");
		}
	}

	function preferenceChoices($showInactive) {
		$preferenceChoices = array();
		$resultSet = executeQuery("select * from preferences where client_setable = 1" . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and internal_use_only = 0") . " order by sort_order,preference_code");
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$preferenceChoices[$row['preference_id']] = array("key_value" => $row['preference_id'], "description" => $row['preference_code'] . " - " . $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		return $preferenceChoices;
	}

    function beforeSaveChanges(&$nameValues) {
        executeQuery("delete from client_preferences where preference_id = ? and client_id = ? and preference_qualifier <=> ?",$nameValues['preference_id'],$GLOBALS['gClientId'],$nameValues['preference_qualifier']);
        return true;
    }

	function afterSaveChanges($nameValues, $actionPerformed) {
		executeQuery("delete from client_preferences where client_id = ? and preference_value is null", $GLOBALS['gClientId']);
		return true;
	}
}

$pageObject = new ClientPreferencesPage("client_preferences");
$pageObject->displayPage();
