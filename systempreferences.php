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

$GLOBALS['gPageCode'] = "SYSTEMPREFERENCES";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "system_value"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn(array("description", "system_value"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function mainContent() {
		?>
        <h3 class='red-text'>Changes here will affect ALL clients on this server.</h3>
		<?php
		return false;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#system_value_checkbox", function () {
                $("#system_value").val($(this).prop("checked") ? "true" : "");
            });
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select * from preferences where preference_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
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
			switch ($row['data_type']) {
				case "select":
					$controlElement = "<select tabindex='10' class='field-text %classString%' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value']) . "' name='system_value' id='system_value'>\n";
					$controlElement .= "<option value=''>[None]</option>\n";
					if (array_key_exists("choices", $row)) {
						$choices = getContentLines($row['choices']);
						foreach ($choices as $index => $choiceValue) {
							$controlElement .= "<option value='" . htmlText($choiceValue) . "'" .
								($choiceValue == $returnArray['system_value']['data_value'] ? " selected" : "") . ">" . htmlText($choiceValue) . "</option>\n";
						}
					}
					$controlElement .= "</select>\n";
					break;
				case "int":
				case "bigint":
					$validationClasses[] = "custom[integer]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value']) . "' value='" .
						htmlText($returnArray['system_value']['data_value']) . "' size='10' maxlength='10' name='system_value' id='system_value' />";
					break;
				case "decimal":
					$validationClasses[] = "custom[number]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' data-decimal-places='2' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value']) . "' value='" .
						htmlText($returnArray['system_value']['data_value']) . "' size='10' maxlength='12' name='system_value' id='system_value' />";
					break;
				case "tinyint":
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='checkbox' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value'] == "true" ? "1" : "0") . "' value='true'" .
						($returnArray['system_value']['data_value'] == "true" ? " checked" : "") .
						" name='system_value_checkbox' id='system_value_checkbox' />" .
						"<input type='hidden' id='system_value' name='system_value' value='" . $returnArray['system_value']['data_value'] . "' />" .
						"<label class='checkbox-label' for='system_value_checkbox'>" . htmlText($row['description']) . "</label>";
					break;
				case "varchar":
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value']) . "' value='" .
						htmlText($returnArray['system_value']['data_value']) . "' size='40' name='system_value' id='system_value' />";
					break;
				case "date":
					$controlElement = "<input tabindex='10' class='field-text validate[custom[date]] %classString%' type='text' size='10' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value']) . "' value='" .
						htmlText($returnArray['system_value']['data_value']) . "' name='system_value' id='system_value' />";
					break;
				case "text":
					$controlElement = "<textarea class='field-text %classString%' name='system_value' data-crc_value='" .
						getCrcValue($returnArray['system_value']['data_value']) . "' id='system_value'>" .
						htmlText($returnArray['system_value']['data_value']) . "</textarea>";
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
		$returnArray['system_value_control'] = array("data_value" => $controlElement);
	}

	function internalCSS() {
		?>
        <style>
            textarea {
                height: 600px;
                width: 900px;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("preferences");
$pageObject->displayPage();
