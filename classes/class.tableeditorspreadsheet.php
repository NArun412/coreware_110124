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

class TableEditorSpreadsheet extends TableEditor {

	function pageElements() {
		?>
        <div class="hidden" id="_page_buttons_content">
			<?php
			$buttonFunctions = array(
				"save" => array("accesskey" => "s", "icon" => "fad fa-save", "label" => getLanguageText("Save"), "disabled" => ($GLOBALS['gPermissionLevel'] < _READWRITE || $this->iReadonly ? true : false)),
				"list" => array("accesskey" => "l", "icon" => "fad fa-list-ul", "label" => getLanguageText("List"), "disabled" => false));
			$this->displayButtons("all", false, $buttonFunctions);
			?>
        </div>
		<?php
		return true;
	}

	function mainContent() {
		?>
        <table id='_spreadsheet_list' data-row_number="1">
            <tr class="column-header">
				<?php
				$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
				if (count($listColumns) == 1 && empty($listColumns[0])) {
					unset($listColumns[0]);
				}
				$columnThreshold = getPreference("SPREADSHEET_COLUMN_THRESHOLD");
				if (empty($columnThreshold) || !is_numeric($columnThreshold)) {
					$columnThreshold = 5;
				}
				$columnCount = 0;
				$columns = $this->iDataSource->getColumns();
				foreach ($columns as $columnName => $thisColumn) {
					if ($columnCount >= $columnThreshold) {
						break;
					}
					if (!empty($listColumns) && !in_array($columnName, $listColumns)) {
						continue;
					}
					if ($thisColumn->getControlValue('wysiwyg')) {
						continue;
					}
					if ($thisColumn->getControlValue('foreign_key')) {
						if ($thisColumn->getControlValue('subtype')) {
							$thisColumn->setControlValue("data_type", $thisColumn->getControlValue('subtype'));
						} else {
							$thisColumn->setControlValue('data_type', "select");
							$choices = $thisColumn->getChoices($this->iPageObject);
							if ((empty($choices) || count($choices) == 0) && !$thisColumn->getControlValue('not_null')) {
								$this->addExcludeListColumn($columnName);
							}
						}
					}
					if (in_array($columnName, $this->iExcludeListColumns)) {
						continue;
					}
					$useColumn = false;
					switch ($thisColumn->getControlValue('data_type')) {
						case "varchar":
						case "text":
						case "mediumtext":
						case "decimal":
						case "date":
						case "select":
						case "tinyint":
							$useColumn = true;
							break;
						case "bigint":
						case "int":
							if (!$thisColumn->getControlValue('subtype')) {
								$useColumn = true;
							}
							break;
					}
					if ($useColumn) {
						$columnCount++;
						?>
                        <th<?= ($thisColumn->getControlValue('data_type') == "tinyint" ? " class='align-center'" : "") ?>><?= htmlText($thisColumn->getControlValue('form_label')) ?></th>
						<?php
					}
				}
				?>
            </tr>
        </table>
		<?php
	}

	function onLoadPageJavascript() {
		if ($this->iPageObject->onLoadPageJavascript()) {
			return;
		}
		?>
        <script>
            $(function () {
                $("#_management_content").wrapInner("<form id='_edit_form'></form>");
                $(document).on("tap click", "#_list_button", function () {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                    }
                    return false;
                });
                $(document).on("tap click", "#_save_button", function () {
                    disableButtons($(this));
                    saveChanges(function () {
                        $('body').data('just_saved', 'true');
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                    }, function () {
                        enableButtons($("#_save_button"));
                    });
                    return false;
                });
                $(document).on("keydown", "#_spreadsheet_list input,textarea,select", function (event) {
                    var elementId = $(this).attr("id").split("-");
                    var elementName = elementId[0];
                    var rowNumber = elementId[1];
                    switch (event.which) {
                        case 38:
                            if (rowNumber > 1) {
                                rowNumber = rowNumber - 1;
                                $("#" + elementName + "-" + rowNumber).focus().select();
                            }
                            event.stopPropagation();
                            return false;
                        case 40:
                            rowNumber = rowNumber - 0 + 1;
                            $("#" + elementName + "-" + rowNumber).focus().select();
                            event.stopPropagation();
                            return false;
                        default:
                            return true;
                    }
                });
				<?php if (!empty($this->iErrorMessage)) { ?>
                displayErrorMessage("<?= htmlText($this->iErrorMessage) ?>");
				<?php } ?>
                getSpreadsheetList();
                displaySpreadsheetHeader();
            });
        </script>
		<?php
	}

	function pageJavascript() {
		if ($this->iPageObject->pageJavascript()) {
			return;
		}
		?>
        <script>
            function displaySpreadsheetHeader() {
                $(".page-heading").html("<?= htmlText($GLOBALS['gPageRow']['description']) ?>");
                $(".page-buttons,.page-form-buttons").html($("#_page_buttons_content").html());
                $("#_page_buttons_content").html("");
                $(".page-list-control").remove();
                $(".page-previous-button").remove();
                $(".page-next-button").remove();
                $(".page-record-display").remove();
                $(".page-controls").show();
            }
            function saveChanges(afterFunction, regardlessFunction) {
                if (regardlessFunction == null || regardlessFunction == undefined) {
                    regardlessFunction = function () {
                    };
                }
                if (afterFunction == null || afterFunction == undefined) {
                    afterFunction = function () {
                    };
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=spreadsheet&url_action=save_changes", $("#_edit_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                            regardlessFunction();
                        } else {
                            displayInfoMessage("<?= getSystemMessage("all_changes_saved") ?>");
                            afterFunction();
                        }
                    });
                } else {
                    regardlessFunction();
                }
            }

            function getSpreadsheetList() {
                disableButtons();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=spreadsheet&url_action=get_spreadsheet_list", function(returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    }
                    enableButtons();
                    $("#_spreadsheet_list tr").not(".column-header").remove();
                    $("#_row_count").html(returnArray['row_count']);
                    if ("data_list" in returnArray && typeof returnArray['data_list'] == "object") {
                        for (var i in returnArray['data_list']) {
                            var rowNumber = $("#_spreadsheet_list").data("row_number");
                            $("#_spreadsheet_list").data("row_number", rowNumber - 0 + 1);
                            var newRow = $("#_row_template").html().replace(/%rowId%/g, rowNumber);
                            $("#_spreadsheet_list").append(newRow);
                            for (var j in returnArray['data_list'][i]) {
                                if ($("#" + j + "-" + rowNumber).is("input[type=checkbox]")) {
                                    $("#" + j + "-" + rowNumber).prop("checked", returnArray['data_list'][i][j]['data_value'] == 1);
                                } else {
                                    $("#" + j + "-" + rowNumber).val(returnArray['data_list'][i][j]['data_value']);
                                }
                                if ("crc_value" in returnArray['data_list'][i][j]) {
                                    $("#" + j + "-" + rowNumber).data("crc_value", returnArray['data_list'][i][j]['crc_value']);
                                } else {
                                    $("#" + j + "-" + rowNumber).removeData("crc_value");
                                }
                            }
                        }
                    }
                });
            }
        </script>
		<?php
	}

	function jqueryTemplates() {
		?>
        <table>
            <tbody id="_row_template">
			<?php
			$keyDone = false;
			$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
			if (count($listColumns) == 1 && empty($listColumns[0])) {
				unset($listColumns[0]);
			}
			$columnThreshold = getPreference("SPREADSHEET_COLUMN_THRESHOLD");
			if (empty($columnThreshold) || !is_numeric($columnThreshold)) {
				$columnThreshold = 5;
			}
			$columnCount = 0;
			$columns = $this->iDataSource->getColumns();
			foreach ($columns as $columnName => $thisColumn) {
				$cellClass = "";
				if ($columnCount >= $columnThreshold) {
					break;
				}
				if (!empty($listColumns) && !in_array($columnName, $listColumns)) {
					continue;
				}
				if (in_array($columnName, $this->iExcludeListColumns)) {
					continue;
				}
				if ($thisColumn->getControlValue('wysiwyg')) {
					continue;
				}
				$useColumn = false;
				switch ($thisColumn->getControlValue('data_type')) {
					case "varchar":
						$maxlength = $thisColumn->getControlValue('maximum_length');
						if ($thisColumn->getControlValue("size")) {
							$size = $thisColumn->getControlValue('size');
						} else {
							$size = min($maxlength, 30);
							if (empty($size)) {
								$size = 30;
							}
						}
						if ($size > 30) {
							$size = 30;
						}
						$thisColumn->setControlValue('size', $size);
					case "text":
					case "mediumtext":
					case "decimal":
					case "date":
						$thisColumn->setControlValue('no_datepicker', true);
					case "select":
						$useColumn = true;
						break;
					case "tinyint":
						$useColumn = true;
						$thisColumn->setControlValue("form_label", "");
						$cellClass = "align-center";
						break;
					case "bigint":
					case "int":
						if (!$thisColumn->getControlValue('subtype')) {
							$useColumn = true;
						}
						break;
				}
				if ($useColumn) {
					$columnCount++;
					$thisColumn->setControlValue('column_name', $thisColumn->getControlValue("column_name") . "-%rowId%");
					?>
                    <td<?= (empty($cellClass) ? "" : " class='" . $cellClass . "'") ?>><?= ($keyDone ? "" : "<input type='hidden' name='primary_id-%rowId%' id='primary_id-%rowId%'>") ?><?= $thisColumn->getControl() ?></td>
					<?php
					$keyDone = true;
				}
			}
			?>
            </tbody>
        </table>
		<?php
	}

	function getSpreadsheetList() {
		$returnArray = array();
		$columnList = array();
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 1 && empty($listColumns[0])) {
			unset($listColumns[0]);
		}
		$columnThreshold = getPreference("SPREADSHEET_COLUMN_THRESHOLD");
		if (empty($columnThreshold) || !is_numeric($columnThreshold)) {
			$columnThreshold = 5;
		}
		$columnCount = 0;
		$columns = $this->iDataSource->getColumns();
		foreach ($columns as $columnName => $thisColumn) {
			if ($columnCount >= $columnThreshold) {
				break;
			}
			if (!empty($listColumns) && !in_array($columnName, $listColumns)) {
				continue;
			}
			if (in_array($columnName, $this->iExcludeListColumns)) {
				continue;
			}
			if ($thisColumn->getControlValue('wysiwyg')) {
				continue;
			}
			$useColumn = false;
			switch ($thisColumn->getControlValue('data_type')) {
				case "varchar":
				case "text":
				case "mediumtext":
				case "decimal":
				case "date":
				case "select":
				case "tinyint":
					$useColumn = true;
					break;
				case "bigint":
				case "int":
					if (!$thisColumn->getControlValue('subtype')) {
						$useColumn = true;
					}
					break;
			}
			if ($useColumn) {
				$columnCount++;
				$columnList[] = $thisColumn->getControlValue('column_name');
			}
		}
		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		if (array_key_exists($sortOrderColumn, $columns)) {
			$sortOrderColumns = array($sortOrderColumn);
			$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
			$reverseSortOrders = array($reverseSortOrder ? "desc" : "asc");
			if (!empty($secondarySortOrderColumn)) {
				$sortOrderColumns[] = $secondarySortOrderColumn;
				$reverseSortOrders[] = (getPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']) ? "desc" : "asc");
			}
			$this->iDataSource->setSortOrder($sortOrderColumns, $reverseSortOrders);
		}

		$dataList = $this->iDataSource->getDataList();
		$returnArray['row_count'] = $this->iDataSource->getDataListCount();
		$returnArray['data_list'] = array();
		foreach ($dataList as $row) {
			$thisRow = array();
			$thisRow['primary_id'] = array("data_value" => $row[$this->iDataSource->getPrimaryTable()->getPrimaryKey()]);
			foreach ($row as $fieldName => $fieldData) {
				if (!in_array($fieldName, $columnList)) {
					continue;
				}
				$fullColumnName = $this->iTableName . "." . $fieldName;
				switch ($this->iColumnDataArray[$fullColumnName]['data_type']) {
					case "date":
						$fieldData = (empty($fieldData) ? "" : date("m/d/Y", strtotime($fieldData)));
						break;
				}
				$thisRow[$fieldName] = array("data_value" => $fieldData, "crc_value" => getCrcValue($fieldData));
			}
			$returnArray['data_list'][] = $thisRow;
		}
		ajaxResponse($returnArray);
	}

	function saveChanges() {
		$returnArray = array();
		$columnList = array();
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 1 && empty($listColumns[0])) {
			unset($listColumns[0]);
		}
		$columnThreshold = getPreference("SPREADSHEET_COLUMN_THRESHOLD");
		if (empty($columnThreshold) || !is_numeric($columnThreshold)) {
			$columnThreshold = 5;
		}
		$columnCount = 0;
		$columns = $this->iDataSource->getColumns();
		foreach ($columns as $columnName => $thisColumn) {
			if ($columnCount >= $columnThreshold) {
				break;
			}
			if (!empty($listColumns) && !in_array($columnName, $listColumns)) {
				continue;
			}
			if (in_array($columnName, $this->iExcludeListColumns)) {
				continue;
			}
			if ($thisColumn->getControlValue('wysiwyg')) {
				continue;
			}
			$useColumn = false;
			switch ($thisColumn->getControlValue('data_type')) {
				case "varchar":
				case "text":
				case "mediumtext":
				case "decimal":
				case "date":
				case "select":
				case "tinyint":
					$useColumn = true;
					break;
				case "bigint":
				case "int":
					if (!$thisColumn->getControlValue('subtype')) {
						$useColumn = true;
					}
					break;
			}
			if ($useColumn) {
				$columnCount++;
				$columnList[$thisColumn->getControlValue('column_name')] = $thisColumn;
			}
		}
		$this->iDataSource->getDatabase()->startTransaction();
		$this->iDataSource->disableTransactions();
		$this->iDataSource->setSaveOnlyPresent(true);
		foreach ($_POST as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("primary_id-")) == "primary_id-") {
				$rowNumber = substr($fieldName, strlen("primary_id-"));
				if (!is_numeric($rowNumber)) {
					continue;
				}
				$nameValues = array();
				foreach ($columnList as $columnName => $thisColumn) {
					if ($thisColumn->getControlValue("data_type") == "tinyint" || array_key_exists($columnName . "-" . $rowNumber, $_POST)) {
						$nameValues[$columnName] = $_POST[$columnName . "-" . $rowNumber];
					}
				}
				if (!$this->iDataSource->saveRecord(array("primary_id" => $fieldData, "name_values" => $nameValues))) {
					$returnArray['error_message'] = $this->iDataSource->getErrorMessage();
					$this->iDataSource->getDatabase()->rollbackTransaction();
					break;
				}
			}
		}
		if (!array_key_exists("error_message", $returnArray)) {
			$this->iDataSource->getDatabase()->commitTransaction();
		}
		ajaxResponse($returnArray);
	}

	function internalPageCSS() {
		$this->iPageObject->internalPageCSS();
	}

	function hiddenElements() {
	}

	function setPreferences() {
	}

	function getDataList() {
	}

	function getSortList() {
	}

	function exportCSV($exportAll = false) {
	}

	function getRecord() {
	}

	function deleteRecord() {
	}

}
