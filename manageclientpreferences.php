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

$GLOBALS['gPageCode'] = "MANAGECLIENTPREFERENCES";
require_once "shared/startup.inc";

class ThisPage extends Page {
    protected $iInternalUseOnlyWhere = "";


    function setup() {
		if (empty($_GET['group']) && !empty($_POST['preference_group_id'])) {
			$_GET['group'] = getFieldFromId("preference_group_code","preference_groups","preference_group_id",$_POST['preference_group_id']);
		}
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete","add","list"));
		}
         $this->iInternalUseOnlyWhere = !empty($GLOBALS['gUserRow']['superuser_flag']) ? "" : "and internal_use_only = 0";
    }

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function onLoadJavascript() {
?>
<script>
$(".show-description").click(function() {
	$(this).closest(".basic-form-line").find(".preference-info").toggleClass("hidden");
});
$(document).on("keyup", ".filter-text", function (event) {
    const textFilter = $(this).val().toLowerCase();
    if (empty(textFilter)) {
        $(this).closest("div").find(".filter-section").removeClass("hidden");
    } else {
        $(this).closest("div").find(".filter-section").each(function () {
            const description = $(this).find("p").text().toLowerCase() + " " + $(this).find("label").text().toLowerCase();
            if (description.indexOf(textFilter) >= 0) {
                $(this).removeClass("hidden");
            } else {
                $(this).addClass("hidden");
            }
        });
    }
});
</script>
<?php
	}

	function mainContent() {
		$preferenceGroupId = getFieldFromId("preference_group_id","preference_groups","preference_group_code",$_GET['group']);
		$description = getFieldFromId("description","preference_groups","preference_group_code",$_GET['group']);
        if(empty($this->iInternalUseOnlyWhere)) {
            echo "<p>Logged in as a superuser. Internal use only preferences are included.</p>";
        }
        echo "<p><input tabindex='10' class='filter-text ignore-changes' type='text' id='preference_filter_text' placeholder='Filter Preferences'></p>";

		?>
<?php if (!empty($description)) { ?>
<h2><?= htmlText($description) ?></h2>
<?php } ?>
<input type="hidden" id="preference_group_id" name="preference_group_id" value="">
<?php
		if (empty($preferenceGroupId)) {
			$query = "select * from preferences where inactive = 0 {$this->iInternalUseOnlyWhere} and client_setable = 1 order by sort_order,preference_code";
		} else {
			$query = "select * from preferences join preference_group_links using (preference_id) where inactive = 0 {$this->iInternalUseOnlyWhere}" .
				" and preference_group_id = " . $preferenceGroupId . " and client_setable = 1 order by sequence_number,description";
		}
		$resultSet = executeQuery($query);
		while ($row = getNextRow($resultSet)) {
?>
<div class="basic-form-line filter-section" id="_preference_id_<?= $row['preference_id'] ?>_row">
	<label><?= htmlText($row['description'] . " (" . $row['preference_code']  . ")") ?><?= (empty($row['detailed_description']) ? "" : "<span class='far fa-info-circle show-description'></span>") ?></label>
<?php if (!empty($row['detailed_description'])) { ?>
<div class="preference-info hidden">
<?= makeHtml($row['detailed_description']) ?>
</div>
<?php } ?>
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
			switch ($row['data_type']) {
				case "select":
					$controlElement = "<select tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'>\n";
					$controlElement .= "<option value=''>[None]</option>\n";
					if (array_key_exists("choices",$row)) {
						$choices = getContentLines($row['choices']);
						foreach ($choices as $index => $choiceValue) {
							$controlElement .= "<option value='" . htmlText($choiceValue) . "'>" . htmlText($choiceValue) . "</option>\n";
						}
					}
					$controlElement .= "</select>\n";
					break;
                case "bigint":
                case "int":
					$validationClasses[] = "custom[integer]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' size='10' maxlength='10' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
					break;
				case "decimal":
					$validationClasses[] = "custom[number]";
					$classes[] = "align-right";
					$controlElement = "<input tabindex='10' data-decimal-places='2' class='field-text %classString%' type='text' size='10' maxlength='12' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
					break;
				case "tinyint":
					$controlElement = "<select tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'>\n";
					$controlElement .= "<option value=''>[Use Default]</option>\n";
					$controlElement .= "<option value='true'>True</option>\n";
					$controlElement .= "<option value='false'>False</option>\n";
					$controlElement .= "</select>\n";
					break;
                case "date":
                    $controlElement = "<input disabled tabindex='10' class='field-text %classString%' type='text' size='40' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
                    break;
				case "varchar":
					$controlElement = "<input tabindex='10' class='field-text %classString%' type='text' size='40' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "' />";
					break;
				case "text":
					$controlElement = "<textarea tabindex='10' class='field-text %classString%' name='preference_value_" . $row['preference_id'] . "' id='preference_value_" . $row['preference_id'] . "'></textarea>";
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
<?php
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
        $preferenceGroupId = getFieldFromId("preference_group_id","preference_groups","preference_group_code",$_GET['group']);
		$returnArray['preference_group_id'] = array("data_value"=>$preferenceGroupId);
		if (empty($preferenceGroupId)) {
			$query = "select * from preferences where inactive = 0 {$this->iInternalUseOnlyWhere} and client_setable = 1 order by sort_order,description";
		} else {
			$query = "select * from preferences join preference_group_links using (preference_id) where inactive = 0 {$this->iInternalUseOnlyWhere}" .
				" and preference_group_id = " . $preferenceGroupId . " and client_setable = 1 order by sequence_number,description";
		}
		$resultSet = executeQuery($query);
		while ($row = getNextRow($resultSet)) {
			$dataValue = getFieldFromId("preference_value","client_preferences","preference_id",$row['preference_id'],"client_id = ?",$GLOBALS['gClientId']);
			if($row['data_type'] == 'tinyint' && $dataValue == 1) {
                $dataValue = 'true';
            }
            $returnArray['preference_value_' . $row['preference_id']] = array("data_value"=>$dataValue,"crc_value"=>getCrcValue($dataValue));
		}
	}

	function internalCSS() {
?>
<style>
#_main_content .basic-form-line { margin-bottom: 20px; }
#_previous_section { display: none; }
#_next_section { display: none; }
#_record_number_section { display: none; }
#_selected_section { display: none; }
.show-description { font-size: 1.2rem; margin-left: 10px; color: rgb(5,20,225); cursor: pointer; }
.preference-info { max-width: 600px; }
</style>
<?php
	}

	function saveChanges() {
		$returnArray = array();
		$resultSet = executeQuery("select * from preferences where inactive = 0 {$this->iInternalUseOnlyWhere} and client_setable = 1 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists("preference_value_" . $row['preference_id'],$_POST)) {
				continue;
			}
			$clientPreferenceId = getFieldFromId("client_preference_id","client_preferences","preference_id",$row['preference_id'],
				"preference_qualifier is null and client_id = ?",$GLOBALS['gClientId']);
			$saveValues = array();
			$saveValues['client_id'] = $GLOBALS['gClientId'];
			$saveValues['preference_qualifier'] = "";
			$saveValues['preference_id'] = $row['preference_id'];
			$saveValues['preference_value'] = $_POST['preference_value_' . $row['preference_id']];
			if (empty($saveValues['preference_value'])) {
				if (!empty($clientPreferenceId)) {
					$this->iDataSource->deleteRecord(array("primary_id"=>$clientPreferenceId));
				}
			} else {
				$this->iDataSource->saveRecord(array("name_values"=>$saveValues,"primary_id"=>$clientPreferenceId));
			}
		}
		$returnArray['info_message'] = "Changes Saved";
		ajaxResponse($returnArray);
	}
}

$pageObject = new ThisPage("client_preferences");
$pageObject->displayPage();
