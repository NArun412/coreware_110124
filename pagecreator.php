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

$GLOBALS['gPageCode'] = "PAGECREATOR";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "template_id", "creator_user_id", "date_created"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("link_title", "data_type", "varchar");
		$this->iDataSource->addColumnControl("link_title", "form_label", "Menu Item");
		$this->iDataSource->addColumnControl("link_title", "maximum_length", "40");
		$this->iDataSource->addColumnControl("link_name", "not_null", true);
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("menu_id", "data_type", "select");
		$this->iDataSource->addColumnControl("menu_id", "form_label", "Put in Menu");
		$this->iDataSource->addColumnControl("menu_id", "get_choices", "menuChoices");
		$this->iDataSource->addColumnControl("menu_position", "data_type", "int");
		$this->iDataSource->addColumnControl("menu_position", "form_label", "At position");
		$this->iDataSource->addColumnControl("menu_position", "minimum_value", "1");
		$this->iDataSource->addColumnControl("meta_description", "css-height", "50px");
		$this->iDataSource->addColumnControl("meta_description", "css-width", "600px");
		$this->iDataSource->addColumnControl("meta_description", "data_type", "text");
		$this->iDataSource->addColumnControl("meta_keywords", "css-width", "600px");
		$this->iDataSource->addColumnControl("public_access", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("public_access", "form_label", "Public Access");

		$this->iDataSource->addColumnControl("template_id", "include_default_client", false);
		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("creator_user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("date_created", "readonly", true);
		$this->iDataSource->addColumnControl("creator_user_id", "readonly", true);
		$this->iDataSource->addColumnControl("creator_user_id", "get_choices", "userChoices");
		if (hasPageCapability("LIMIT_ACCESS") && !$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("subsystem_id", "not_null", "true");
			$subsystemIds = array();
			$resultSet = executeQuery("select subsystem_id from subsystem_users where user_id = ?", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$subsystemIds[] = $row['subsystem_id'];
			}
			if (empty($subsystemIds)) {
				$subsystemIds[] = "0";
			}
			$this->iDataSource->setFilterWhere("script_filename is null and template_id in (select template_id from templates where include_crud = 0) and template_id <> " . $GLOBALS['gManagementTemplateId'] . " and subsystem_id is not null and subsystem_id in (" . implode(",", $subsystemIds) . ")");
			$this->iDataSource->addColumnControl("subsystem_id", "not_null", "true");
			$this->iDataSource->addColumnControl("subsystem_id", "get_choices", "userSubsystemChoices");
		} else {
			$this->iDataSource->setFilterWhere("script_filename is null and template_id in (select template_id from templates where include_crud = 0) and template_id <> " . $GLOBALS['gManagementTemplateId']);
		}
	}

	function jqueryTemplates() {
		?>
        <div id="page_data_templates"></div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#go_to_page", function () {
                if (!empty($("#primary_id").val())) {
                    if (changesMade()) {
                        displayInfoMessage("Only saved changes will be shown");
                    }
                    const $linkName = $("#link_name");
                    const linkUrl = (empty($linkName.val()) ? $("#script_filename").val() : $linkName.val());
                    if (!empty(linkUrl)) {
                        const $domainName = $("#domain_name");
                        const $scriptArguments = $("#script_arguments");
                        window.open(<?php if (!$GLOBALS['gDevelopmentServer']) { ?>(empty($domainName.val()) ? "" : "http://" + $domainName.val()) + <?php } ?>"/" + linkUrl + (empty($scriptArguments.val()) ? "" : "?" + $scriptArguments.val()));
                    }
                }
                return false;
            });
            $("#meta_description").keydown(function () {
                limitText($(this), $("#_meta_description_character_count"), 255);
            }).keyup(function () {
                limitText($(this), $("#_meta_description_character_count"), 255);
            });
            $("#template_id").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_template&template_id=" + $(this).val() + "&primary_id=" + $("#primary_id").val(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#page_data_div").html(returnArray['page_data_div']['data_value']);
                        afterGetRecord();
                    }
                });
            });
            $("#description").change(function () {
                $("#link_name").val($(this).val()).trigger("change");
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "change_template":
				$urlPage = "show";
				$returnArray['template_id'] = array("data_value" => $_GET['template_id']);
				$returnArray['primary_id'] = array("data_value" => $_GET['primary_id']);
				$this->afterGetRecord($returnArray);
				ajaxResponse($returnArray);
				break;
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['link_title'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['menu_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['menu_position'] = array("data_value" => "", "crc_value" => getCrcValue(""));

		$pageAccessId = getFieldFromId("page_access_id", "page_access", "page_id", $returnArray['primary_id']['data_value'], "public_access = 1 and permission_level = 3");
		$returnArray['public_access'] = array("data_value" => (empty($pageAccessId) ? "0" : "1"), "crc_value" => getCrcValue((empty($pageAccessId) ? "0" : "1")));

		if (!empty($returnArray['primary_id']['data_value'])) {
			$menuItemId = false;
			$resultSet = executeQuery("select * from menu_items where page_id = ?", $returnArray['primary_id']['data_value']);
			if ($row = getNextRow($resultSet)) {
				$menuItemId = $row['menu_item_id'];
				$returnArray['link_title']['data_value'] = $row['link_title'];
				$returnArray['link_title']['crc_value'] = getCrcValue($row['link_title']);
			}

			if (!empty($menuItemId)) {
				$saveMenuContentId = false;
				$saveMenuId = false;
				$resultSet = executeQuery("select * from menu_contents where menu_item_id = ?", $menuItemId);
				if ($row = getNextRow($resultSet)) {
					$returnArray['menu_id']['data_value'] = $row['menu_id'];
					$returnArray['menu_id']['crc_value'] = getCrcValue($row['menu_id']);
					$saveMenuContentId = $row['menu_content_id'];
					$saveMenuId = $row['menu_id'];
				}

				if (!empty($saveMenuContentId)) {
					$resultSet = executeQuery("select * from menu_contents where menu_id = ? order by sequence_number", $saveMenuId);
					$menuPosition = 1;
					while ($row = getNextRow($resultSet)) {
						if ($saveMenuContentId == $row['menu_content_id']) {
							$returnArray['menu_position']['data_value'] = $menuPosition;
							$returnArray['menu_position']['crc_value'] = getCrcValue($menuPosition);
							break;
						}
						$menuPosition++;
					}
				}
			}
		}

		$templateId = $returnArray['template_id']['data_value'];
		if (empty($templateId)) {
			$returnArray['page_data_div'] = array();
			$returnArray['page_data_div']['data_value'] = "";
			return;
		}
		ob_start();
		$returnArray['page_data_div'] = array();

		$templateDataArray = array();
		$completedGroupKeys = array();
		$resultSet = executeQuery("select * from template_data,template_data_uses where template_data.template_data_id = template_data_uses.template_data_id and " .
			"template_id = ? and inactive = 0 order by sequence_number,description", $templateId);
		while ($row = getNextRow($resultSet)) {
			$templateDataArray[$row['template_data_id']] = $row;
		}
		$templates = "";
		$dataArray = array();
		$dataArray['select_values'] = array();
		while (count($templateDataArray) > 0) {
			$row = array_shift($templateDataArray);
			Template::massageTemplateColumnData($row);
			$column = new DataColumn(strtolower($row['column_name']));
			foreach ($row as $fieldName => $fieldData) {
				$column->setControlValue($fieldName, $fieldData);
			}
			if (!empty($row['css_content'])) {
				echo "<style>#" . $row['column_name'] . "{" . $row['css_content'] . "}</style>\n";
			}
			if ((empty($row['group_identifier']) && $row['allow_multiple'] == 0) || $row['subtype'] == "image") {
				$templateDirectory = getFieldFromId("directory_name", "templates", "template_id", $templateId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
				if (!empty($templateDirectory) && $row['data_type'] == "text") {
					if (file_exists($GLOBALS['gDocumentRoot'] . "/templates/" . $templateDirectory . "/ckeditor.css")) {
						$column->setControlValue("data-contents_css", "/templates/" . $templateDirectory . "/ckeditor.css");
					}
					if (file_exists($GLOBALS['gDocumentRoot'] . "/templates/" . $templateDirectory . "/ckeditor.js")) {
						$column->setControlValue("data-styles_set", "/templates/" . $templateDirectory . "/ckeditor.js");
					}
				}
				?>
                <div class='basic-form-line' id="_row_<?= $row['column_name'] ?>">
					<?php
					echo($row['data_type'] == "tinyint" ? "<label></label>" : "<label id='_label_" . $row['column_name'] . "' for='" . $row['column_name'] . "' " . ($row['not_null'] ? " class='required-label'" : "") . ">" . htmlText($row['description']) . "</label>");
					echo $column->getControl();
					?>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

                </div>
				<?php
				$resultSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ? and sequence_number is null",
					$returnArray['primary_id']['data_value'], $row['template_data_id']);
				if ($dataRow = getNextRow($resultSet)) {
					$fieldData = $dataRow[$row['data_field']];
				} else {
					$fieldData = "";
				}
				$fieldName = $row['column_name'];
				switch ($row['data_type']) {
					case "datetime":
					case "date":
						$fieldData = (empty($fieldData) ? "" : date("m/d/Y" . ($row['data_type'] == "datetime" ? " g:i:sa" : ""), strtotime($fieldData)));
						break;
					case "tinyint":
						$fieldData = ($fieldData == 1 ? 1 : 0);
						break;
				}
				switch ($row['subtype']) {
					case "image":
						$dataArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
						$dataArray[$fieldName . "_view"] = array("data_value" => getImageFilename($fieldData));
						$dataArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $fieldData));
						$dataArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
						$dataArray['select_values'][$fieldName] = array(array("key_value" => $fieldData, "description" => getFieldFromId("description", "images", "image_id", $fieldData)));
						break;
				}
				$dataArray[$row['column_name']] = array("data_value" => $fieldData, "crc_value" => getCrcValue($fieldData));
			} else {
				?>
                <div class='basic-form-line' id="_row_<?= $row['column_name'] ?>">
                    <label><?= htmlText(empty($row['group_identifier']) ? $row['description'] : $row['group_identifier']) ?></label>
					<?php
					$rowArray = array($column->getControlValue("column_name") => $column);
					if (!empty($row['group_identifier'])) {
						foreach ($templateDataArray as $index => $templateDataRow) {
							if ($templateDataRow['group_identifier'] == $row['group_identifier']) {
								Template::massageTemplateColumnData($templateDataRow);
								$thisColumn = new DataColumn(strtolower($templateDataRow['column_name']));
								foreach ($templateDataRow as $fieldName => $fieldData) {
									$thisColumn->setControlValue($fieldName, $fieldData);
								}
								$rowArray[$thisColumn->getControlValue("column_name")] = $thisColumn;
								unset($templateDataArray[$index]);
							}
						}
					}
					$multipleDataRow = array();
					foreach ($rowArray as $column) {
						$templateDataId = $column->getControlValue('template_data_id');
						$column->setControlValue("form_label", $column->getControlValue("description"));
						$dataField = $column->getControlValue('data_field');
						$resultSet = executeQuery("select sequence_number,$dataField from page_data where page_id = ? and template_data_id = ? and sequence_number is not null order by sequence_number",
							$returnArray['primary_id']['data_value'], $templateDataId);
						while ($dataRow = getNextRow($resultSet)) {
							if (!array_key_exists($dataRow['sequence_number'], $multipleDataRow)) {
								$multipleDataRow[$dataRow['sequence_number']] = array();
							}
							$multipleDataRow[$dataRow['sequence_number']][$column->getControlValue('column_name')] = array("data_value" => $dataRow[$dataField], "crc_value" => getCrcValue($dataRow[$dataField]));
						}
					}
					$groupIdentifier = $column->getControlValue("group_identifier");
					$controlName = (empty($groupIdentifier) ? $row['data_name'] : str_replace(" ", "_", strtolower($groupIdentifier)));
					$dataArray[$controlName] = array("data_value" => $multipleDataRow);
					$editableListColumn = new DataColumn($controlName);
					$editableList = new EditableList($editableListColumn, $this);
					$editableList->setColumnList($rowArray);
					echo $editableList->getControl();
					?>
                    <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>

                </div>
				<?php
				$templates .= $editableList->getTemplate();
			}
		}
		?>
        <script type="text/javascript">
            dataArray = <?= jsonEncode($dataArray) ?>;
        </script>
		<?php
		$returnArray['page_data_div']['data_value'] = ob_get_clean();
		$returnArray['page_data_templates']['data_value'] = $templates;
	}

	function javascript() {
		?>
        <script>
            function limitText(limitField, limitCount, limitNum) {
                if (limitField.val().length > limitNum) {
                    limitField.val(limitField.val().substring(0, limitNum));
                }
                limitCount.html("<?= getLanguageText("Character Count") ?>: " + (limitField.val().length));
            }

            var dataArray = new Array();
            function afterGetRecord() {
                limitText($("#meta_description"), $("#_meta_description_character_count"), 255);
                $("#page_data_div .datepicker").datepicker({
                    showOn: "button",
                    buttonText: "<span class='fad fa-calendar-alt'></span>",
                    constrainInput: false,
                    dateFormat: "mm/dd/y",
                    yearRange: "c-100:c+10"
                });
                $("#page_data_div .required-label").append("<span class='required-tag'>*</span>");
                $("#page_data_div a[rel^='prettyPhoto']").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                setTimeout("setPageData()", 100);
            }

            function setPageData() {
                if ("select_values" in dataArray) {
                    for (var i in dataArray['select_values']) {
                        if (!$("#" + i).is("select")) {
                            continue;
                        }
                        $("#" + i + " option").each(function () {
                            if ($(this).data("inactive") == "1") {
                                $(this).remove();
                            }
                        });
                        for (var j in dataArray['select_values'][i]) {
                            if ($("#" + i + " option[value='" + dataArray['select_values'][i][j]['key_value'] + "']").length == 0) {
                                var inactive = ("inactive" in dataArray['select_values'][i][j] ? dataArray['select_values'][i][j]['inactive'] : "0");
                                $("#" + i).append("<option data-inactive='" + inactive + "' value='" + dataArray['select_values'][i][j]['key_value'] + "'>" + dataArray['select_values'][i][j]['description'] + "</option>");
                            }
                        }
                    }
                }
                for (var i in dataArray) {
                    if (typeof dataArray[i] == "object" && "data_value" in dataArray[i]) {
                        if ($("input[type=radio][name='" + i + "']").length > 0) {
                            $("input[type=radio][name='" + i + "']").prop("checked", false);
                            $("input[type=radio][name='" + i + "'][value='" + dataArray[i]['data_value'] + "']").prop("checked", true);
                        } else if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", dataArray[i].data_value != 0);
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", dataArray[i].data_value).css("display", (dataArray[i].data_value == "" ? "none" : "inline"));
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            for (var j in dataArray[i].data_value) {
                                addEditableListRow(i, dataArray[i]['data_value'][j]);
                            }
                        } else {
                            $("#" + i).val(dataArray[i].data_value);
                        }
                        if ("crc_value" in dataArray[i]) {
                            $("#" + i).data("crc_value", dataArray[i].crc_value);
                        } else {
                            $("#" + i).removeData("crc_value");
                        }
                    }
                }
            }
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_id", $nameValues['primary_id'], true);
		removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_code", getFieldFromId("page_code","pages","page_id",$nameValues['primary_id']), true);
		removeCachedData("initialized_template_page_data",$nameValues['primary_id']);
		removeCachedData("all_page_codes", "");
		$templateId = $nameValues['template_id'];
		$pageDataSource = new DataSource("page_data");
		$pageDataSource->disableTransactions();
		executeQuery("delete from page_access where page_id = ?", $nameValues['primary_id']);
		if ($nameValues['public_access']) {
			executeQuery("insert ignore into page_access (page_id,public_access,permission_level) values (?,1,3)", $nameValues['primary_id']);
		}
		executeQuery("update template_data set allow_multiple = 1 where group_identifier is not null");
		executeQuery("update template_data set allow_multiple = 0,group_identifier = null where data_type = 'image'");
		$templateDataSet = executeQuery("select * from template_data,template_data_uses where template_data.template_data_id = template_data_uses.template_data_id and template_id = ? and inactive = 0 order by sequence_number,description", $templateId);
		while ($templateDataRow = getNextRow($templateDataSet)) {
			$dataName = "template_data-" . $templateDataRow['data_name'] . "-" . $templateDataRow['template_data_id'];
			if ($templateDataRow['allow_multiple'] == 0) {
				$dataValue = $_POST[$dataName];
				$resultSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ? and sequence_number is null",
					$nameValues['primary_id'], $templateDataRow['template_data_id']);
				if ($row = getNextRow($resultSet)) {
					if (empty($dataValue)) {
						if (!$pageDataSource->deleteRecord(array("primary_id" => $row['page_data_id'], "ignore_subtables" => array("images")))) {
							return $pageDataSource->getErrorMessage();
						}
					}
				} else {
					$pageDataSource->setPrimaryId("");
					$row = array("page_id" => $nameValues['primary_id'], "template_data_id" => $templateDataRow['template_data_id']);
				}
				if (!empty($dataValue)) {
					$fieldName = getFieldFromDataType($templateDataRow['data_type']);
					$row[$fieldName] = $dataValue;
					if (!$pageDataSource->saveRecord(array("name_values" => $row, "primary_id" => $row['page_data_id']))) {
						return $pageDataSource->getErrorMessage();
					}
				}
			} else {
				$controlName = (empty($templateDataRow['group_identifier']) ? $templateDataRow['data_name'] : str_replace(" ", "_", strtolower($templateDataRow['group_identifier'])));
				$dataName = $controlName . "_" . $dataName;
				$sequenceNumberArray = array();
				foreach ($_POST as $fieldName => $dataValue) {
					if (substr($fieldName, 0, strlen($dataName . "-")) == $dataName . "-") {
						$sequenceNumber = substr($fieldName, strlen($dataName . "-"));
						if (!is_numeric($sequenceNumber)) {
							continue;
						}
						$sequenceNumberArray[] = $sequenceNumber;
						$resultSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ? and sequence_number = ?",
							$nameValues['primary_id'], $templateDataRow['template_data_id'], $sequenceNumber);
						if ($row = getNextRow($resultSet)) {
							$pageDataSource->setPrimaryId($row['page_data_id']);
							if (empty($dataValue)) {
								if (!$pageDataSource->deleteRecord()) {
									return $pageDataSource->getErrorMessage();
								}
							}
						} else {
							$pageDataSource->setPrimaryId("");
							$row = array("page_id" => $nameValues['primary_id'], "template_data_id" => $templateDataRow['template_data_id'], "sequence_number" => $sequenceNumber);
						}
						if (!empty($dataValue)) {
							$fieldName = getFieldFromDataType($templateDataRow['data_type']);
							$row[$fieldName] = $dataValue;
							if (!$pageDataSource->saveRecord(array("name_values" => $row, "primary_id" => $row['page_data_id']))) {
								return $pageDataSource->getErrorMessage();
							}
						}
					}
				}
				$sequenceNumberList = implode(",", $sequenceNumberArray);
				$resultSet = executeQuery("delete from page_data where page_id = ? and template_data_id = ?" .
					(empty($sequenceNumberList) ? "" : " and sequence_number not in ($sequenceNumberList)"),
					$nameValues['primary_id'], $templateDataRow['template_data_id']);
			}
		}
		$menuItemId = false;
		$resultSet = executeQuery("select * from menu_items where page_id = ?", $nameValues['primary_id']);
		if ($row = getNextRow($resultSet)) {
			$menuItemId = $row['menu_item_id'];
		}
		if (empty($nameValues['link_title'])) {
			if (!empty($menuItemId)) {
				executeQuery("delete from menu_contents where menu_item_id = ?", $menuItemId);
			}
		} else {
			if (empty($menuItemId)) {
				$insertSet = executeQuery("insert into menu_items (client_id,description,link_title,page_id,not_logged_in,logged_in,administrator_access) values " .
					"(?,?,?,?,1,1,1)", $GLOBALS['gClientId'], $nameValues['description'], $nameValues['link_title'], $nameValues['primary_id']);
				$menuItemId = $insertSet['insert_id'];
			} else {
				$dataTable = new DataTable("menu_items");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => array("link_title" => $nameValues['link_title']), "primary_id" => $menuItemId));
			}
			executeQuery("delete from menu_contents where menu_item_id = ?", $menuItemId);
			if (!empty($nameValues['menu_id'])) {
				$menuPosition = $nameValues['menu_position'];

				executeQuery("SET @sequencenumber = 0");
				executeQuery("update menu_contents set sequence_number = (@sequencenumber:=@sequencenumber+10) where menu_id = ? order by sequence_number", $nameValues['menu_id']);

				$menuId = $nameValues['menu_id'];
				if (empty($menuPosition)) {
					$resultSet = executeQuery("select max(sequence_number) from menu_contents where menu_id = ?", $menuId);
					if ($row = getNextRow($resultSet)) {
						$menuPosition = $row['max(sequence_number)'] + 10;
					} else {
						$menuPosition = 10;
					}
				} else {
					$menuPosition = ($menuPosition * 10) - 5;
				}
				executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)",
					$menuId, $menuItemId, $menuPosition);
			}
		}
		removeCachedData("page_contents", $nameValues['page_code']);
		removeCachedData("menu_contents", "*");
		removeCachedData("admin_menu", "*");
		return true;
	}

	function menuChoices($showInactive = false) {
		$menuChoices = array();
		$resultSet = executeQuery("select * from menus where core_menu = 0 and client_id = ? order by description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$menuChoices[$row['menu_id']] = array("key_value" => $row['menu_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		return $menuChoices;
	}

	function internalCSS() {
		?>
        <style>
            #go_to_page {
                position: absolute;
                top: 0;
                right: 0;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("pages");
$pageObject->displayPage();
