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

$GLOBALS['gPageCode'] = "MANAGEUSERPREFERENCES";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete","add","list"));
		}
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function javascript() {
?>
function afterSaveChanges() {
	$("body").data("just_saved","true");
	setTimeout(function() {
		document.location = "/";
	},1000);
	return true;
}
<?php
	}

	function onLoadJavascript() {
?>
$(document).on("click","#_save_button",function() {
	if ($(this).data("ignore") == "true") {
		return false;
	}
	if ($("#_permission").val() <= "1") {
		displayErrorMessage("<?= getSystemMessage("readonly") ?>");
		return false;
	}
	disableButtons($(this));
	saveChanges(function() {
		enableButtons($("#_save_button"));
	},function() {
		enableButtons($("#_save_button"));
	});
	return false;
});
$(document).on("tap click",".preference-value-checkbox",function() {
	$(this).closest(".basic-form-line").find(".preference-value").val($(this).prop("checked") ? "true" : "false");
});
displayFormHeader();
$(".page-record-display").hide();
enableButtons($("#_save_button"));
<?php
		return true;
	}

	function mainContent() {
?>
<h1>User Preferences</h1>
<form id="_edit_form" name="_edit_form">
<?php
		$resultSet = executeQuery("select * from preferences where inactive = 0 and " . (empty($GLOBALS['gUserRow']['superuser_flag']) ? "internal_use_only = 0 and " : "") . "user_setable = 1 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			if ($row['hide_system_value']) {
				$row['system_value'] = "SYSTEM VALUE NOT DISPLAYED";
			}
?>
<div class="basic-form-line" id="_preference_id_<?= $row['preference_id'] ?>_row">
	<h3><?= htmlText($row['description']) ?></h3>
	<label for="preference_id_<?= $row['preference_id'] ?>">Your Setting</label>
<?php
			$controlElement = "";
			$validationClasses = array();
			$classes = array();
			if (strlen($row['minimum_value']) > 0) {
				$validationClasses[] = "min[" . $row['minimum_value'] . "]";
			}
			if (strlen($row['maximum_value']) > 0) {
				$validationClasses[] = "max[" . $row['maximum_value'] . "]";
			}
			$classes[] = "field-text";
			$dataValue = getFieldFromId("preference_value","user_preferences","preference_id",$row['preference_id'],"user_id = ? and preference_qualifier is null",$GLOBALS['gUserId']);
			switch ($row['data_type']) {
				case "select":
					$controlElement = "<select tabindex='10' class='field-text %classString%' data-crc_value='" .
						getCrcValue($dataValue) . "' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'>\n";
					$controlElement .= "<option value=''>[None]</option>\n";
					if (array_key_exists("choices",$row)) {
						$choices = getContentLines($row['choices']);
						foreach ($choices as $index => $choiceValue) {
							$controlElement .= "<option value='" . htmlText($choiceValue) . "'" .
								($choiceValue == $dataValue ? " selected" : "") . ">" . htmlText($choiceValue) . "</option>\n";
						}
					}
					$controlElement .= "</select>\n";
					break;
                case "bigint":
                case "int":
					$validationClasses[] = "custom[integer]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($dataValue) . "' value='" .
						htmlText($dataValue) . "' size='10' maxlength='10' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
					break;
				case "decimal":
					$validationClasses[] = "custom[number]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' data-decimal-places='2' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($dataValue) . "' value='" .
						htmlText($dataValue) . "' size='10' maxlength='12' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
					break;
				case "tinyint":
					$controlElement = "<select tabindex='10' class='field-text %classString%' data-crc_value='" .
						getCrcValue($dataValue) . "' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'>\n";
					$controlElement .= "<option value=''>[Use System Value]</option>\n";
					$controlElement .= "<option value='true'" . ($dataValue == "true" ? " selected" : "") . ">True</option>\n";
					$controlElement .= "<option value='false'" . ($dataValue == "false" ? " selected" : "") . ">False</option>\n";
					$controlElement .= "</select>\n";
					break;
				case "varchar":
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($dataValue) . "' value='" .
						htmlText($dataValue) . "' size='40' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
					break;
				case "text":
					$controlElement = "<textarea tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' data-crc_value='" .
						getCrcValue($dataValue) . "' id='preference_value_" . $row['preference_id'] . "'>" .
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
			echo $controlElement;
?>
    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
</div>
<div class="basic-form-line" id="_system_value_<?= $row['preference_id'] ?>_row">
	<label for="system_value_<?= $row['preference_id'] ?>">System Setting</label>
	<textarea class="system-value" readonly="readonly" id="system_value_<?= $row['preference_id'] ?>"><?= htmlText($row['system_value']) ?></textarea>
    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
</div>
<div class="divider"></div>
<?php
		}
?>
</form>
<?php
		return true;
	}

	function internalCSS() {
?>
<style>
.system-value { height: 40px }
.divider { width: 100%; }
h1 { margin-bottom: 20px; }
h3 { color: rgb(40,40,40); }
#_previous_section { display: none; }
#_next_section { display: none; }
#_record_number_section { display: none; }
#_selected_section { display: none; }
</style>
<?php
	}

	function saveChanges() {
		$returnArray = array();
		$resultSet = executeQuery("select * from preferences where inactive = 0" . (empty($GLOBALS['gUserRow']['superuser_flag']) ? " and internal_use_only = 0" : "") . " and user_setable = 1 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$userPreferenceId = getFieldFromId("user_preference_id","user_preferences","preference_id",$row['preference_id'],
				"preference_qualifier is null and user_id = ?",$GLOBALS['gUserId']);
			$saveValues = array();
			$saveValues['user_id'] = $GLOBALS['gUserId'];
			$saveValues['preference_qualifier'] = "";
			$saveValues['preference_id'] = $row['preference_id'];
			$saveValues['preference_value'] = $_POST['preference_value_' . $row['preference_id']];
			if (strlen($_POST['preference_value_' . $row['preference_id']]) == 0) {
				if (!empty($userPreferenceId)) {
					$this->iDataSource->deleteRecord(array("primary_id"=>$userPreferenceId));
				}
			} else {
				executeQuery("delete from user_preferences where preference_id = ? and user_id = ?",$row['preference_id'],$GLOBALS['gUserId']);
				$this->iDataSource->saveRecord(array("name_values"=>$saveValues,"primary_id"=>$userPreferenceId));
			}
		}
		ajaxResponse($returnArray);
	}
}

$pageObject = new ThisPage("user_preferences");
$pageObject->displayPage();
