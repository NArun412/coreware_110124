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

$GLOBALS['gPageCode'] = "USERPREFERENCES";
require_once "shared/startup.inc";

class UserPreferencesPage extends Page {

	function javascript() {
?>
function customActions(actionName) {
	if (actionName == "resetsettings") {
		document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=resetsettings";
		return true;
	}
	return false;
}
<?php
	}

	function executePageUrlActions() {
		if ($_GET['url_action'] == "resetsettings" && $GLOBALS['gPermissionLevel'] > 1) {
			executeQuery("delete from user_preferences where user_id = ?",$GLOBALS['gUserId']);
		}
	}

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description","preference_value","system_value"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn(array("description","preference_value","system_value"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add","delete"));
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("resetsettings","Reset User Settings");
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("user_preferences","","",true);
		$this->iDataSource->setFilterWhere("user_setable = 1 and internal_use_only = 0 and inactive = 0 and preference_qualifier is null and (user_id = " . $GLOBALS['gUserId'] . " or user_id is null)");
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function dataListProcessing(&$dataList) {
		foreach ($dataList as $index => $row) {
			if ($row['hide_system_value']) {
				$dataList[$index]['system_value'] = "SYSTEM VALUE NOT DISPLAYED";
			} else {
				$clientValue = getFieldFromId("preference_value","client_preferences","preference_id",$row['preference_id'],
					"client_id = ?",$GLOBALS['gClientId']);
				if (!empty($clientValue)) {
					$dataList[$index]['system_value'] = $clientValue;
				}
			}
		}
	}

	function afterGetRecord(&$returnArray) {
		$controlElement = "";
		$resultSet = executeQuery("select * from preferences where preference_id = ?",$returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			if ($row['hide_system_value']) {
				$returnArray['system_value']['data_value'] = "SYSTEM VALUE NOT DISPLAYED";
			} else {
				$clientValue = getFieldFromId("preference_value","client_preferences","preference_id",$row['preference_id']);
				if (!empty($clientValue)) {
					$returnArray['system_value']['data_value'] = $clientValue;
				}
			}
			$dataValue = "";
			$resultSet1 = executeQuery("select * from user_preferences where user_id = ? and preference_id = ?",$GLOBALS['gUserId'],$row['preference_id']);
			if ($row1 = getNextRow($resultSet1)) {
				$dataValue = $row1['preference_value'];
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
			switch ($row['data_type']) {
				case "select":
					$controlElement = "<select tabindex='10' class='field-text %classString%' data-crc_value='" .
						getCrcValue($dataValue) . "' name='preference_value' id='preference_value'>\n";
					$controlElement .= "<option value=''>[Use System Value]</option>\n";
					if (array_key_exists("choices",$row)) {
						$choices = getContentLines($row['choices']);
						foreach ($choices as $index => $choiceValue) {
							$controlElement .= "<option value='" . htmlText($choiceValue) . "' " .
								($choiceValue == $dataValue ? " selected" : "") . ">" . htmlText($choiceValue) . "</option>\n";
						}
					}
					$controlElement .= "</select>\n";
					break;
                case "int":
                case "bigint":
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
					$controlElement = "<textarea class='field-text %classString%' name='preference_value' data-crc_value='" .
						getCrcValue($dataValue) . "' id='preference_value'>" .
						htmlText($dataValue) . "</textarea>";
					break;
			}
			$validationClassString = implode(",",$validationClasses);
			if (!empty($validationClassString) && !$row['readonly']) {
				$validationClassString = "validate[" . $validationClassString . "]";
				$classes[] = $validationClassString;
			}
			$classString = implode(" ",$classes);
			$controlElement = str_replace("%classString%",$classString,$controlElement);
		}
		$returnArray['preference_value_control'] = array("data_value"=>$controlElement);
	}

	function saveChanges() {
		$returnArray = array();
		if (!empty($_POST['primary_id'])) {
			$preferenceId = $_POST['primary_id'];
			$preferenceValue = $_POST['preference_value'];
			executeQuery("delete from user_preferences where user_id = ? and preference_id = ? and preference_qualifier is null",$GLOBALS['gUserId'],$preferenceId);
			if (!empty($preferenceValue)) {
				$resultSet = executeQuery("insert into user_preferences (user_id,preference_id,preference_value) values " .
					"(?,?,?)",$GLOBALS['gUserId'],$preferenceId,$preferenceValue);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic",$resultSet['sql_error']);
				}
			}
		}
		ajaxResponse($returnArray);
	}

}

$pageObject = new UserPreferencesPage("preferences");
$pageObject->displayPage();
