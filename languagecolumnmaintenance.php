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

$GLOBALS['gPageCode'] = "LANGUAGECOLUMNMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeColumn(array("description", "table_id", "column_definition_id", "query_text"));
		} else {
			$this->iTemplateObject->getTableEditorObject()->addIncludeColumn(array("description", "table_id", "column_definition_id"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("table_id_display", "list_header", "Data Table");
		$this->iDataSource->addColumnControl("column_definition_id_display", "list_header", "Data Field");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_columns":
				$returnArray['columns'] = "";
				$tableId = $_GET['table_id'];
				$resultSet = executeQuery("select * from table_columns,column_definitions where column_type in ('varchar','text','mediumtext') and table_id = ? and " .
					"table_columns.column_definition_id = column_definitions.column_definition_id and code_value = 0 order by sequence_number", $tableId);
				while ($row = getNextRow($resultSet)) {
					$returnArray['columns'] .= "<option value='" . $row['column_definition_id'] . "'>" . htmlText($row['description']) . "</option>";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#table_id").change(function () {
                $("#column_definition_id").data("last_data", $("#column_definition_id").val());
                $("#column_definition_id option").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_columns&table_id=" + $(this).val(), function(returnArray) {
                        if ("columns" in returnArray) {
                            $("#column_definition_id").append(returnArray['columns']);
                        }
                        $("#column_definition_id").val($("#column_definition_id").data("last_data"));
                    });
                }
            });
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['select_values']['column_definition_id'] = array(array("key_value" => $returnArray['column_definition_id']['data_value'], "description" => "Placeholder"));
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#table_id").trigger("change");
            }
        </script>
		<?php
	}
}

$pageObject = new ThisPage("language_columns");
$pageObject->displayPage();
