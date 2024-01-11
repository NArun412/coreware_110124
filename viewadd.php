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

$GLOBALS['gPageCode'] = "VIEWADD";
require_once "shared/startup.inc";

class ViewAddPage extends Page {

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
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn("checked");
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));
			$this->iTemplateObject->getTableEditorObject()->setSaveUrl("viewmaintenance.php");
			$this->iTemplateObject->getTableEditorObject()->setListUrl("viewmaintenance.php");
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("change", ".view-name", function () {
                $(this).val($(this).val().toLowerCase().replace(/ /g, "_"));
            });
            if ($("#add_view").length > 0) {
                setTimeout(function () {
                    addRow();
                    $("#add_view_1").focus();
                }, 200);
            }
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function addRow() {
                var rowCount = $('#add_view tr').length;
                var rowContent = $("#new_row").html().replace(/%rowCount%/g, rowCount);
                $("#add_view").append(rowContent);
                $("#add_view_" + rowCount).change(function () {
                    var rowNumber = $(this).attr("id").replace("add_view_", "");
                    if (rowNumber == ($('#add_view tr').length - 1)) {
                        addRow();
                    }
                });
            }
        </script>
		<?php
	}

	function saveChanges() {
		$returnArray = array();
		if (!empty($_POST['primary_id'])) {
			$databaseDefinitionId = $_POST['primary_id'];

			foreach ($_POST as $fieldName => $fieldData) {
				if (!empty($fieldData) && substr($fieldName, 0, strlen("add_view_")) == "add_view_") {
					$resultSet = executeQuery("select * from tables where table_name = ? and database_definition_id = ?",
						$fieldData, $databaseDefinitionId);
					if ($row = getNextRow($resultSet)) {
						$returnArray['error_message'] = "Table/View '" . $fieldData . "' already exists";
						ajaxResponse($returnArray);
						break;
					}
				}
			}

			$this->iDatabase->startTransaction();
			foreach ($_POST as $fieldName => $fieldData) {
				if (!empty($fieldData) && substr($fieldName, 0, strlen("add_view_")) == "add_view_") {
					$rowNumber = substr($fieldName, strlen("add_view_"));
					$subsystemId = $_POST['subsystem_id_' . $rowNumber];
					$description = ucwords(strtolower(str_replace("_", " ", $fieldData)));
					executeQuery("insert into tables (database_definition_id,table_name,description,subsystem_id,table_view) values " .
						"(?,?,?,?,1)", $databaseDefinitionId, $fieldData, $description, $subsystemId);
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
                <td><input tabindex='10' class='view-name' id='add_view_%rowCount%' name='add_view_%rowCount%' size='30'/></td>
                <td><select tabindex='10' class="validate[required]" data-conditional-required="$('#add_view_%rowCount%').val() != ''" id='subsystem_id_%rowCount%' name='subsystem_id_%rowCount%'>
                        <option value="">[Select]</option>
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

	function internalCSS() {
		?>
        .view-name { font-size: 16px; }
		<?php
	}
}

$pageObject = new ViewAddPage("database_definitions");
$pageObject->displayPage();
