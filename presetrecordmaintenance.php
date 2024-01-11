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

$GLOBALS['gPageCode'] = "PRESETRECORDMAINT";
require_once "shared/startup.inc";

class PresetRecordMaintenancePage extends Page {

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->setFormSortOrder(array("table_name", "description", "preset_record_code", "preset_record_values"));
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_columns":
				$tableId = getFieldFromId("table_id", "tables", "table_name", $_GET['table_name']);
				$resultSet = executeQuery("select * from column_definitions join table_columns using (column_definition_id) where column_type in ('date','decimal','int','tinyint','varchar') and " .
					"column_name not like '%_id' and column_name <> 'version' and table_id = ? order by sequence_number", $tableId);
				$columnNames = array();
				while ($row = getNextRow($resultSet)) {
					$columnNames[$row['column_name']] = $row['description'];
				}
				$returnArray['column_names'] = $columnNames;
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("table_name", "data_type", "select");
		$this->iDataSource->addColumnControl("table_name", "get_choices", "tableChoices");
		$this->iDataSource->addColumnControl("table_name", "not_editable", true);

		$this->iDataSource->addColumnControl("preset_record_values", "data_type", "custom");
		$this->iDataSource->addColumnControl("preset_record_values", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("preset_record_values", "form_label", "Field Values");
		$this->iDataSource->addColumnControl("preset_record_values", "list_table", "preset_record_values");
		$this->iDataSource->addColumnControl("preset_record_values", "list_table_controls", array("text_data" => array("data_type" => "varchar", "inline-width" => "400px", "inline-max-width" => "400px"),
			"column_name" => array("data_type" => "select", "inline-width" => "300px", "inline-max-width" => "300px")));

		$this->iDataSource->getPrimaryTable()->setSubtables(array("preset_record_values"));
	}

	function tableChoices($showInactive = false) {
		$tableChoices = array();
		$resultSet = executeQuery("select table_id,table_name,description from tables where table_id in (select table_id from table_columns where " .
			"column_definition_id = (select column_definition_id from column_definitions where column_name = 'description')) and table_id in (select table_id from table_columns where " .
			"column_definition_id = (select column_definition_id from column_definitions where column_name = 'sort_order')) and table_id in (select table_id from table_columns where " .
			"column_definition_id = (select column_definition_id from column_definitions where column_name = 'internal_use_only')) and table_id in (select table_id from table_columns where " .
			"column_definition_id = (select column_definition_id from column_definitions where column_name = 'inactive')) order by description");
		while ($row = getNextRow($resultSet)) {
			$tableChoices[$row['table_name']] = array("key_value" => $row['table_name'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $tableChoices;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#description").change(function () {
                if (empty($("#preset_record_code").val())) {
                    $("#preset_record_code").val($("#table_name").val() + " " + $("#description").val());
                }
            });
            $("#table_name").change(function () {
                $("#_preset_record_values_row").find(".editable-list-remove").trigger("click");
                $("#_preset_record_values_new_row").find("select").find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_columns&table_name=" + $(this).val(), function(returnArray) {
                        if ("column_names" in returnArray) {
                            for (const i in returnArray['column_names']) {
                                $("#_preset_record_values_new_row").find("select").append($("<option></option>").attr("value", i).text(returnArray['column_names'][i]));
                            }
                            if (!empty(presetRecordValues)) {
                                for (var j in presetRecordValues) {
                                    addEditableListRow('preset_record_values', presetRecordValues[j]);
                                }
                                $("#_preset_record_values_delete_ids").val("");
                                presetRecordValues = false;
                            }
                        }
                    });
                }
            })
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>

            var presetRecordValues = false;

            function afterGetRecord(returnArray) {
                presetRecordValues = returnArray['preset_record_values'];
                $("#table_name").trigger("change");
            }
        </script>
		<?php
	}
}

$pageObject = new PresetRecordMaintenancePage("preset_records");
$pageObject->displayPage();
