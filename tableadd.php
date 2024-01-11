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

$GLOBALS['gPageCode'] = "TABLEADD";
require_once "shared/startup.inc";

class TableAddPage extends Page {

	function massageUrlParameters() {
		if (empty($_GET['url_page']) && empty($_GET['url_action'])) {
			$resultSet = executeQuery("select * from database_definitions");
			if ($resultSet['row_count'] == 1) {
				$row = getNextRow($resultSet);
				$_GET['url_page'] = "show";
				$_GET['primary_id'] = $row['database_definition_id'];
			}
		}
	}

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn("checked");
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add","delete"));
			$this->iTemplateObject->getTableEditorObject()->setSaveUrl("tablemaintenance.php");
			$this->iTemplateObject->getTableEditorObject()->setListUrl("tablemaintenance.php");
		}
	}

	function internalCSS() {
?>
#add_table td input { font-size: 1rem; }
<?php
	}

	function onLoadJavascript() {
?>
$(document).on("change",".table-name",function() {
	$(this).val($(this).val().toLowerCase().replace(/ /g,"_"));
});
$(document).on("change",".key-name",function() {
	$(this).val($(this).val().toLowerCase().replace(/ /g,"_"));
}).on("focus",".key-name",function() {
	if (empty($(this).val())) {
		var rowNumber = $(this).attr("id").replace("add_primary_id_","");
		if ($("#add_table_" + rowNumber).val() != "") {
			var keyName = $("#add_table_" + rowNumber).val();
			if (keyName.substring(keyName.length - 3) == "ies") {
				keyName = keyName.substring(0,keyName.length - 3) + "y";
			} else if (keyName.substring(keyName.length - 2) == "ss" || keyName.substring(keyName.length - 6) == "status" || keyName.substring(keyName.length - 2) == "as") {
				;
			} else if (keyName.substring(keyName.length - 4) == "uses") {
				keyName = keyName.substring(0,keyName.length - 2);
			} else if (keyName.substring(keyName.length - 4) == "sses") {
				keyName = keyName.substring(0,keyName.length - 2);
			} else if (keyName.substring(keyName.length - 1) == "s") {
				keyName = keyName.substring(0,keyName.length - 1);
			}
			keyName += "_id";
			$(this).val(keyName);
		}
	}
});
if ($("#add_table").length > 0) {
	setTimeout(function() {
		addRow();
		$("#add_table_1").focus();
	},400);
}
<?php
	}

	function javascript() {
?>
function addRow() {
	var rowCount = $('#add_table tr').length;
	var rowContent = $("#new_row").html().replace(/%rowCount%/g,rowCount);
	$("#add_table").append(rowContent);
	$("#add_table_" + rowCount).change(function() {
		var rowNumber = $(this).attr("id").replace("add_table_","");
		if (rowNumber == ($('#add_table tr').length - 1)) {
			addRow();
		}
	});
}
<?php
	}

	function saveChanges() {
		$returnArray = array();
		if (!empty($_POST['primary_id'])) {
			$databaseDefinitionId = $_POST['primary_id'];

			foreach ($_POST as $fieldName => $fieldData) {
				if (!empty($fieldData) && startsWith($fieldName,"add_table_")) {
					$resultSet = executeQuery("select * from tables where table_name = ? and database_definition_id = ?",
						$fieldData,$databaseDefinitionId);
					if ($row = getNextRow($resultSet)) {
						$returnArray['error_message'] = "Table '" . $fieldData . "' already exists";
						ajaxResponse($returnArray);
						break;
					}
				}
			}

			$this->iDatabase->startTransaction();
			foreach ($_POST as $fieldName => $fieldData) {
				if (!empty($fieldData) && startsWith($fieldName,"add_table_")) {
					$rowNumber = substr($fieldName,strlen("add_table_"));
					$keyName = $_POST['add_primary_id_' . $rowNumber];
					if (empty($keyName)) {
						continue;
					}
					$subsystemId = $_POST['subsystem_id_' . $rowNumber];
					$description = ucwords(strtolower(str_replace("_"," ",$fieldData)));
					$resultSet = executeQuery("insert into tables (database_definition_id,table_name,description,subsystem_id) values " .
						"(?,?,?,?)",$databaseDefinitionId,$fieldData,$description,$subsystemId);
					$tableId = $resultSet['insert_id'];
					$resultSet = executeQuery("select * from column_definitions where column_name = ?",$keyName);
					if ($row = getNextRow($resultSet)) {
						$columnDefinitionId = $row['column_definition_id'];
					} else {
						$resultSet = executeQuery("insert into column_definitions (column_name,column_type,not_null) values " .
							"(?,'int',1)",$keyName);
						$columnDefinitionId = $resultSet['insert_id'];
					}
					$description = "ID";
					executeQuery("insert into table_columns (table_column_id,table_id,column_definition_id,description," .
						"sequence_number,primary_table_key,indexed,not_null) values " .
						"(null,?,?,?,100,1,1,1)",$tableId,$columnDefinitionId,$description);
				}
			}
			$this->iDatabase->commitTransaction();
		}
		ajaxResponse($returnArray);
	}

	function jqueryTemplates() {
?>
<table>
<tbody id="new_row">
<tr>
	<td><input tabindex='10' class='table-name' id='add_table_%rowCount%' name='add_table_%rowCount%' size='20'/></td>
	<td><input tabindex='10' class='key-name' id='add_primary_id_%rowCount%' name='add_primary_id_%rowCount%' size='20'/></td>
	<td><select tabindex='10' class="validate[required]" data-conditional-required="$('#add_table_%rowCount%').val() != ''" id='subsystem_id_%rowCount%' name='subsystem_id_%rowCount%'>
		<option value="" selected="selected">[Select]</option>
<?php
		$resultSet = executeQuery("select * from subsystems where inactive = 0 order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
?>
		<option value='<?= $row['subsystem_id'] ?>'><?= htmlText($row['description']) ?></option>
<?php
		}
?>
	</td>
</tr>
</tbody>
</table>
<?php
	}
}

$pageObject = new TableAddPage("database_definitions");
$pageObject->displayPage();
