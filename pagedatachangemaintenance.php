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

$GLOBALS['gPageCode'] = "PAGEDATACHANGEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	var $iColumnNames = array("css_content", "javascript_code", "link_name", "link_url", "script_arguments", "script_filename", "template_id");

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("page_id in (select page_id from pages where client_id = " . $GLOBALS['gClientId'] . ")");
		$this->iDataSource->addColumnControl("column_name", "data_type", "select");
		$this->iDataSource->addColumnControl("column_name", "get_choices", "columnChoices");
		$this->iDataSource->addColumnControl("column_name", "data-conditional-required", "empty($(\"#template_data_id\").val())");
		$this->iDataSource->addColumnControl("column_name", "not_null", true);
		$this->iDataSource->addColumnControl("template_data_id", "data-conditional-required", "empty($(\"#column_name\").val())");
		$this->iDataSource->addColumnControl("template_data_id", "not_null", true);
	}

	function columnChoices($showInactive = false) {
		$columnChoices = array();
		foreach ($this->iColumnNames as $columnName) {
			$resultSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = (select table_id from tables where table_name = 'pages') and column_name = ?", $columnName);
			if ($row = getNextRow($resultSet)) {
				$columnChoices[$row['column_name']] = array("key_value" => $row['column_name'], "description" => $row['description'], "inactive" => false);
			}
			freeResult($resultSet);
		}
		return $columnChoices;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_template_data":
				$templateData = array();
				$resultSet = executeQuery("select * from template_data where allow_multiple = 0 and group_identifier is null and template_data_id in " .
					"(select template_data_id from template_data_uses where template_id = (select template_id from pages where page_id = ?))", $_GET['page_id']);
				while ($row = getNextRow($resultSet)) {
					$templateData[$row['template_data_id']] = htmlText($row['description']);
				}
				$returnArray['template_data'] = $templateData;
				ajaxResponse($returnArray);
				break;
			case "get_template_data_control":
				$templateDataId = getFieldFromId("template_data_id", "template_data", "template_data_id", $_GET['template_data_id']);
				if (empty($templateDataId)) {
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select * from template_data where group_identifier is null and allow_multiple = 0 and template_data_id = ?", $templateDataId);
				if ($row = getNextRow($resultSet)) {
					$returnArray['data_field'] = getFieldFromDataType($row['data_type']);
					Template::massageTemplateColumnData($row);
					$row['column_name'] = "template_data_id_" . $row['template_data_id'];
					$column = new DataColumn(strtolower($row['column_name']));
					foreach ($row as $fieldName => $fieldData) {
						$column->setControlValue($fieldName, $fieldData);
					}
					ob_start();
					if (!empty($row['css_content'])) {
						echo "<style>#" . $row['column_name'] . "{" . $row['css_content'] . "}</style>\n";
					}
					?>
                    <div class='form-line' id="_row_<?= $row['column_name'] ?>">
						<?php
						echo($row['data_type'] == "tinyint" ? "<label></label>" : "<label id='_label_" . $row['column_name'] . "' for='" . $row['column_name'] . "'" . ($row['not_null'] ? " class='required-label'" : "") . ">" . htmlText($row['description']) . "</label>");
						$column->setControlValue("form_label", $row['description']);
						echo $column->getControl();
						?>
                        <div class='clear-div'></div>
                    </div>
					<?php
					$returnArray['field_name'] = $column->getControlValue("column_name");
					$returnArray['template_data_control'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
			case "get_column_control":
				if (!in_array($_GET['column_name'], $this->iColumnNames)) {
					$returnArray['error_message'] = "Invalid Column: " . $_GET['column_name'];
					ajaxResponse($returnArray);
					break;
				}
				$columnName = $_GET['column_name'];
				$columnInformation = array();
				switch ($columnName) {
					case "template_id":
						$columnInformation['table_name'] = "templates";
						$dataType = "select";
						break;
					case "css_content":
					case "javascript_code":
						$dataType = "text";
						$columnInformation['css_content'] = "height: 600px;";
						break;
					default:
						$dataType = "varchar";
						$columnInformation['data_size'] = 255;
						$columnInformation['css_content'] = "width: 500px;";
				}
				$columnInformation['data_type'] = $dataType;
				$resultSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = (select table_id from tables where table_name = 'pages') and column_name = ?", $columnName);
				if ($row = getNextRow($resultSet)) {
					$columnInformation['description'] = $row['description'];
				}
				$returnArray['data_field'] = getFieldFromDataType($dataType);
				Template::massageTemplateColumnData($columnInformation);
				$columnInformation['column_name'] = "column_data_" . $columnName;
				$column = new DataColumn(strtolower($columnInformation['column_name']));
				foreach ($columnInformation as $fieldName => $fieldData) {
					$column->setControlValue($fieldName, $fieldData);
				}
				ob_start();
				if (!empty($columnInformation['css_content'])) {
					echo "<style>#" . $columnInformation['column_name'] . "{" . $columnInformation['css_content'] . "}</style>\n";
				}
				?>
                <div class='form-line' id="_row_<?= $columnInformation['column_name'] ?>">
					<?php
					echo($columnInformation['data_type'] == "tinyint" ? "<label></label>" : "<label id='_label_" . $columnInformation['column_name'] . "' for='" . $columnInformation['column_name'] . "'" . ($columnInformation['not_null'] ? " class='required-label'" : "") . ">" . htmlText($columnInformation['description']) . "</label>");
					$column->setControlValue("form_label", $columnInformation['description']);
					echo $column->getControl();
					?>
                    <div class='clear-div'></div>
                </div>
				<?php
				$returnArray['field_name'] = $column->getControlValue("column_name");
				$returnArray['column_control'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function templateDataChoices($showInactive = false) {
		$templateDataChoices = array();
		return $templateDataChoices;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#column_name").change(function () {
                if (empty($(this).val())) {
                    $("#_template_data_id_row").removeClass("hidden");
                } else {
                    $("#_template_data_id_row").addClass("hidden");
                    $("#template_data_id").val("");
                }
            });
            $("#template_data_id").change(function () {
                if (empty($(this).val())) {
                    $("#_column_name_row").removeClass("hidden");
                } else {
                    $("#_column_name_row").addClass("hidden");
                    $("#column_name").val("");
                }
            });
            $("#page_id").change(function () {
                $("#template_data_id option").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_template_data&page_id=" + $(this).val(), function(returnArray) {
                        if ("template_data" in returnArray) {
                            if (Object.keys(returnArray['template_data']).length != 1) {
                                $("#template_data_id").append($("<option></option>").attr("value", "").text("[Select]"));
                            }
                            for (var i in returnArray['template_data']) {
                                $("#template_data_id").append($("<option></option>").attr("value", i).text(returnArray['template_data'][i]));
                            }
                            if ("template_data_id" in savedReturnArray) {
                                $("#template_data_id").val(savedReturnArray['template_data_id']['data_value']);
                                $("#template_data_id").data("crc_value", savedReturnArray['template_data_id']['crc_value']);
                            }
                            if ("column_name" in savedReturnArray) {
                                $("#column_name").val(savedReturnArray['column_name']['data_value']);
                                $("#column_name").data("crc_value", savedReturnArray['column_name']['crc_value']);
                            }
                            $("#template_data_id").trigger("change");
                            $("#column_name").trigger("change");
                        }
                    });
                }
            });
            $("#template_data_id").change(function () {
                $("#_new_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_template_data_control&template_data_id=" + $(this).val(), function(returnArray) {
                        if ("template_data_control" in returnArray) {
                            $("#_new_data").html(returnArray['template_data_control']);
                            if (returnArray['data_field'] == "image_id") {
                                if ("select_values" in savedReturnArray && "image_id" in savedReturnArray['select_values']) {
                                    if ($("#" + returnArray['field_name']).is("select")) {
                                        for (var j in savedReturnArray['select_values']["image_id"]) {
                                            var thisOption = $("<option></option>").attr("value", savedReturnArray['select_values']["image_id"][j]['key_value']).text(savedReturnArray['select_values']["image_id"][j]['description']);
                                            $("#" + returnArray['field_name']).append(thisOption);
                                        }
                                    }
                                }
                            }
                            $("#" + returnArray['field_name']).val(savedReturnArray[returnArray['data_field']]['data_value']);
                            $("#_new_data .datepicker").datepicker({
                                showOn: "button",
                                constrainInput: false,
                                dateFormat: "mm/dd/y",
                                buttonText: "<span class='fad fa-calendar-alt'></span>",
                                yearRange: "c-100:c+10"
                            });
                            $("#_new_data .required-label").append("<span class='required-tag'>*</span>");
                            $("#_new_data a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                        }
                    });
                }
            });
            $("#column_name").change(function () {
                $("#_new_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_column_control&column_name=" + $(this).val(), function(returnArray) {
                        if ("column_control" in returnArray) {
                            $("#_new_data").html(returnArray['column_control']);
                            if (returnArray['data_field'] == "image_id") {
                                if ("select_values" in savedReturnArray && "image_id" in savedReturnArray['select_values']) {
                                    if ($("#" + returnArray['field_name']).is("select")) {
                                        for (var j in savedReturnArray['select_values']["image_id"]) {
                                            var thisOption = $("<option></option>").attr("value", savedReturnArray['select_values']["image_id"][j]['key_value']).text(savedReturnArray['select_values']["image_id"][j]['description']);
                                            $("#" + returnArray['field_name']).append(thisOption);
                                        }
                                    }
                                }
                            }
                            $("#" + returnArray['field_name']).val(savedReturnArray[returnArray['data_field']]['data_value']);
                            $("#_new_data .datepicker").datepicker({
                                showOn: "button",
                                buttonText: "<span class='fad fa-calendar-alt'></span>",
                                constrainInput: false,
                                dateFormat: "mm/dd/y",
                                yearRange: "c-100:c+10"
                            });
                            $("#_new_data .required-label").append("<span class='required-tag'>*</span>");
                            $("#_new_data a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            var savedReturnArray = null;

            function afterGetRecord(returnArray) {
                savedReturnArray = returnArray;
                $("#page_id").trigger("change");
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_new_data {
                margin: 30px 0 0 0;
            }
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		if (!empty($returnArray['image_id']['data_value'])) {
			$returnArray['select_values']["image_id"] = array(array("key_value" => $returnArray['image_id']['data_value'], "description" => getFieldFromId("description", "images", "image_id", $returnArray['image_id']['data_value']), "inactive" => "0"));
		}
	}

	function beforeSaveChanges(&$dataValues) {
		foreach ($dataValues as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("template_data_id_")) == "template_data_id_") {
				$row = getRowFromId("template_data", "template_data_id", substr($fieldName, strlen("template_data_id_")));
				if (!empty($row)) {
					$dataField = getFieldFromDataType($row['data_type']);
					$dataValues[$dataField] = $fieldData;
				}
			}
			if (substr($fieldName, 0, strlen("column_data_")) == "column_data_") {
				$columnName = substr($fieldName, strlen("column_data_"));
				switch ($columnName) {
					case "template_id":
						$dataType = "select";
						break;
					case "css_content":
					case "javascript_code":
						$dataType = "text";
						break;
					default:
						$dataType = "varchar";
				}
				$dataField = getFieldFromDataType($dataType);
				$dataValues[$dataField] = $fieldData;
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("page_data_changes");
$pageObject->displayPage();
