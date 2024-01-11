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

$GLOBALS['gPageCode'] = "ACTIONMAINT";
require_once "shared/startup.inc";

class ActionMaintenancePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_result_options":
				$actionResultRow = getRowFromId("action_results", "action_result_id", $_GET['action_result_id']);
				if (!empty($actionResultRow['table_id'])) {
					$tableName = getFieldFromId("table_name", "tables", "table_id", $actionResultRow['table_id']);
					$controlRecords = $this->iDatabase->getControlRecords(array("table_name" => $tableName));
					$returnArray['options'] = $controlRecords;
					$returnArray['option_label'] = getFieldFromId("description", "tables", "table_id", $actionResultRow['table_id']);
				}
				if (!empty($actionResultRow['form_label'])) {
					$returnArray['form_label'] = $actionResultRow['form_label'];
				}
				ajaxResponse($returnArray);
				break;
			case "get_type_options":
				$actionTypeRow = getRowFromId("action_types", "action_type_id", $_GET['action_type_id']);
				if (!empty($actionTypeRow['table_id'])) {
					$tableName = getFieldFromId("table_name", "tables", "table_id", $actionTypeRow['table_id']);
					try {
						$dataTable = new DataTable($tableName);
						if ($tableName == "products") {
							ob_start();
							echo createFormControl($tableName, $dataTable->getPrimaryKey(), array("not_null" => true, "column_name" => "action_identifier", "data_type" => "autocomplete", "data-autocomplete_tag" => "products", "form_label" => "Products", "inline-width" => "700px"));
							$returnArray['action_identifier_control'] = ob_get_clean();
						} else {
							ob_start();
							echo createFormControl($tableName, $dataTable->getPrimaryKey(), array("not_null" => true, "column_name" => "action_identifier", "data_type" => "select", "form_label" => $actionTypeRow['description']));
							$returnArray['action_identifier_control'] = ob_get_clean();
						}
					} catch (Exception $e) {
					}
				}
				if (!empty($actionTypeRow['form_label'])) {
					$returnArray['form_label'] = $actionTypeRow['form_label'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("action_type_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("action_result_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("result_identifier", "data_type", "select");
	}

	function javascript() {
		?>
        <script>
            let actionIdentifier = "";
            function afterGetRecord(returnArray) {
                actionIdentifier = returnArray['action_identifier']['data_value'];
                $("#action_type_id").trigger("change");
                $("#result_identifier").data("data_value", returnArray['result_identifier']['data_value']);
                $("#action_result_id").trigger("change");
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#action_type_id").change(function () {
                $("#_action_text_row").hide();
                $("#action_identifier_control").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_type_options&action_type_id=" + $(this).val(), function(returnArray) {
                        const $actionIdentifierControl = $("#action_identifier_control");
                        if ("action_identifier_control" in returnArray) {
                            $("#action_identifier_control").html(returnArray['action_identifier_control']);
                            $("#action_identifier").val(actionIdentifier);
                            if ($("#action_identifier_autocomplete_text").length > 0) {
                                $("#action_identifier_autocomplete_text").trigger("get_autocomplete_text");
                            }
                        }
                        if ("form_label" in returnArray) {
                            $("#_action_text_row").show().find("label").html(returnArray['form_label']);
                        }
                    });
                }
            });
            $("#action_result_id").change(function () {
                $("#_result_identifier_row").hide();
                $("#_result_text_row").hide();
                const $resultIdentifier = $("#result_identifier");
                $resultIdentifier.find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_result_options&action_result_id=" + $(this).val(), function(returnArray) {
                        if ("options" in returnArray) {
                            for (let i in returnArray['options']) {
                                $resultIdentifier.append($("<option></option>").attr("value", returnArray['options'][i]['key_value']).text(returnArray['options'][i]['description']));
                            }
                            let currentOption = $resultIdentifier.data("data_value");
                            $resultIdentifier.data("data_value", "");
                            if (empty(currentOption)) {
                                currentOption = "";
                            }
                            $("#result_identifier").val(currentOption);
                            $("#_result_identifier_row").show().find("label").html(returnArray['option_label']);
                        }
                        if ("form_label" in returnArray) {
                            $("#_result_text_row").show().find("label").html(returnArray['form_label']);
                        }
                    });
                }
            });
        </script>
		<?php
	}
}

$pageObject = new ActionMaintenancePage("actions");
$pageObject->displayPage();
