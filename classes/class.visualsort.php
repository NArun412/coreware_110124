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

class VisualSort extends MaintenancePage {

	function __construct($dataSource) {
		$this->iMaximumListColumns = 3;
		parent::__construct($dataSource);
	}

	function pageElements() {
	}

	function pageHeader($pageHeaderFile = "") {
		if ($pageHeaderFile && file_exists($GLOBALS['gDocumentRoot'] . "/classes/" . $pageHeaderFile)) {
			include_once($pageHeaderFile);
			return true;
		}
		?>
        <div id="_form_header_div">
            <div id="_record_number_section"><span id="_row_count">0</span> Records</div>
            <div id="_form_button_section">
				<?php
				$buttonFunctions = array(
					"save" => array("accesskey" => "s", "icon" => "fad fa-save", "label" => getLanguageText("Save"), "disabled" => ($GLOBALS['gPermissionLevel'] < _READWRITE || $this->iReadonly ? true : false)),
					"list" => array("accesskey" => "l", "icon" => "fad fa-list-ul", "label" => getLanguageText("List"), "disabled" => false));
				$this->displayButtons("all", false, $buttonFunctions);
				?>
            </div>
        </div>
		<?php
		return true;
	}

	function mainContent() {
		echo "<p class='subheader'>Drag and drop to change the order</p><form id='_sort_form' name='_sort_form'><ul id='_sort_list'></ul></form>";
	}

	function onLoadPageJavascript() {
		if ($this->iPageObject->onLoadPageJavascript()) {
			return;
		}
		?>
        <script>
            $(function () {
                $("#_sort_list").sortable({
                    update: function () {
                        manualChangesMade = true;
                        changeSortOrder();
                    }
                });
                $(document).on("tap click", "#_list_button", function () {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                    }
                    return false;
                });
                $(document).on("tap click", "#_save_button", function () {
                    $(this).button("disable");
                    saveChanges(function () {
                        $("body").data("just_saved", "true");
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                    }, function () {
                        $("#_save_button").button("enable");
                    });
                    return false;
                });
				<?php if (!empty($this->iErrorMessage)) { ?>
                displayErrorMessage("<?= htmlText($this->iErrorMessage) ?>");
				<?php } ?>
                getSortList();
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
            function changeSortOrder() {
                var sortOrder = 10;
                $("#_sort_list").find("input[name^=sort_order_]").each(function () {
                    $(this).val(sortOrder);
                    sortOrder += 10;
                });
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
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=guisort&url_action=save_changes", $("#_sort_form").serialize(), function(returnArray) {
                    if ("error_message" in returnArray) {
                        regardlessFunction();
                    } else {
                        displayInfoMessage("Sort order successfully changed");
                        afterFunction();
                    }
                }, function(returnArray) {
                    regardlessFunction();
                });
            }

            function getSortList() {
                disableButtons();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=guisort&url_action=get_sort_list", function(returnArray) {
                    enableButtons();
                    $("#_sort_list li").remove();
                    if ("data_list" in returnArray && typeof returnArray['data_list'] == "object") {
                        for (var i in returnArray['data_list']) {
                            $("#_sort_list").append(returnArray['data_list'][i]);
                        }
                    }
                    $("#_row_count").html(returnArray['row_count']);
                    changeSortOrder();
                });
            }
        </script>
		<?php
	}

	function getSortList() {
		$returnArray = array();

		$foreignKeys = $this->iDataSource->getForeignKeyList();
		foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
			if (!in_array($columnName, $this->iExcludeListColumns)) {
				if (!empty($foreignKeyInfo['description'])) {
					$this->iDataSource->addColumnControl($foreignKeyInfo['column_name'] . "_display", "select_value",
						"select concat_ws(' '," . implode(",", $foreignKeyInfo['description']) . ") from " .
						$foreignKeyInfo['referenced_table_name'] . " where " . $foreignKeyInfo['referenced_column_name'] . " = " . $columnName);
					$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => $foreignKeyInfo['referenced_table_name'],
						"referenced_column_name" => $foreignKeyInfo['referenced_column_name'], "foreign_key" => $foreignKeyInfo['column_name'],
						"description" => $foreignKeyInfo['description']));
				}
			}
		}

		if (count($this->iFilters) > 0) {
			$setFilters = array();
			$setFilterText = getPreference("MAINTENANCE_SET_FILTERS", $GLOBALS['gPageRow']['page_code']);
			if (strlen($setFilterText) > 0) {
				$setFilters = json_decode($setFilterText, true);
			} else {
				foreach ($this->iFilters as $filterCode => $filterInfo) {
					if ($filterInfo['set_default']) {
						$setFilters[$filterCode] = 1;
					}
				}
				setUserPreference("MAINTENANCE_SET_FILTERS", jsonEncode($setFilters), $GLOBALS['gPageRow']['page_code']);
			}
			if (method_exists($this->iPageObject, "filtersLoaded")) {
				$this->iPageObject->filtersLoaded($setFilters);
			}
			$filterAndWhere = "";
			$filterWhere = "";
			foreach ($this->iFilters as $filterCode => $filterInfo) {
				if (empty($filterInfo['where'])) {
					continue;
				}
				if (strlen($setFilters[$filterCode]) == 0 && !empty($filterInfo['default_value'])) {
					$setFilters[$filterCode] = $filterInfo['default_value'];
				}
				if (!empty($setFilters[$filterCode])) {
					$filterValueParameter = ($filterInfo['data_type'] == "date" ? makeDateParameter($setFilters[$filterCode]) : makeParameter($setFilters[$filterCode]));
					if ($filterInfo['conjunction'] == "and") {
						$filterAndWhere .= (empty($filterAndWhere) ? "" : " and ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, $filterInfo['where'])) . ")";
					} else {
						$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, $filterInfo['where'])) . ")";
					}
				} else if (!empty($filterInfo['not_where'])) {
					if ($filterInfo['conjunction'] == "and") {
						$filterAndWhere .= (empty($filterAndWhere) ? "" : " and ") . $filterInfo['not_where'];
					} else {
						$filterWhere .= (empty($filterWhere) ? "" : " or ") . $filterInfo['not_where'];
					}
				}
				$returnArray['_set_filter_' . $filterCode] = array("data_value" => $setFilters[$filterCode]);
			}
			$returnArray['_set_filters'] = "0";
			if (!empty($filterAndWhere)) {
				$filterWhere = (empty($filterWhere) ? "" : "(" . $filterWhere . ") and ") . $filterAndWhere;
			}
			if (empty($filterWhere)) {
				foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
					if (empty($filterInfo['no_filter_default'])) {
						continue;
					}
					$setFilters[$filterCode] = $filterInfo['no_filter_default'];
					if (!empty($filterInfo['where'])) {
						if (!empty($setFilters[$filterCode])) {
							$filterValueParameter = ($filterInfo['data_type'] == "date" ? makeDateParameter($setFilters[$filterCode]) : makeParameter($setFilters[$filterCode]));
							$filterLikeParameter = makeParameter("%" . $setFilters[$filterCode] . "%");
							$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, str_replace("%like_value%", $filterLikeParameter, $filterInfo['where']))) . ")";
						}
					}
					$returnArray['_set_filter_' . $filterCode] = array("data_value" => $setFilters[$filterCode]);
					break;
				}
			}
			if (!empty($filterWhere)) {
				$returnArray['filter_set'] = true;
				$this->iDataSource->addFilterWhere($filterWhere);
			}
		}

		$columns = $this->iDataSource->getColumns();

		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 0 || (count($listColumns) == 1 && empty($listColumns[0]))) {
			$listColumns = array();
			foreach ($columns as $columnName => $thisColumn) {
				if (!in_array($columnName, $this->iExcludeListColumns) && $thisColumn->getControlValue('data_type') != "tinyint" &&
					$thisColumn->getControlValue('data_type') != "longblob") {
					$listColumns[] = $columnName;
					if (count($listColumns) >= $this->iMaximumListColumns) {
						break;
					}
				}
			}
		}
		$sortListColumns = array();
		foreach ($listColumns as $thisIndex => $columnName) {
			if (array_key_exists($columnName, $columns) && !in_array($columnName, $this->iExcludeListColumns)) {
				$sortListColumns[] = $columnName;
				if (count($sortListColumns) >= $this->iMaximumListColumns) {
					break;
				}
			}
		}
		$listColumns = $sortListColumns;

		if (array_key_exists($sortOrderColumn, $columns)) {
			$this->iDataSource->setSortOrder($sortOrderColumn . (array_key_exists($sortOrderColumn, $foreignKeys) ? "_display" : ""));
			$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
			$this->iDataSource->setReverseSort($reverseSortOrder);
		}
		$dataList = $this->iDataSource->getDataList();

		$returnArray['row_count'] = $this->iDataSource->getDataListCount();
		$returnArray['data_list'] = array();
		$rowNumber = 0;
		foreach ($dataList as $rowIndex => $columnRow) {
			$rowNumber++;
			$rowData = "<li class='ui-state-default'><input type='hidden' name='primary_id_" . $rowNumber .
				"' id='primary_id_" . $rowNumber . "' value='" . $columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()] . "'><input type='hidden' name='sort_order_" .
				$rowNumber . "' id='sort_order_" . $rowNumber . "' value='" . $columnRow['sort_order'] . "'><table><tr>";
			foreach ($listColumns as $fullColumnName) {
				$dataValue = "";
				$columnName = (strpos($fullColumnName, ".") === false ? $fullColumnName : substr($fullColumnName, strpos($fullColumnName, ".") + 1));
				switch ($columns[$fullColumnName]->getControlValue('data_type')) {
					case "date":
						$dataValue = (empty($columnRow[$columnName]) ? "" : date("m/d/Y", strtotime($columnRow[$columnName])));
						break;
					case "bigint":
					case "int":
						$dataValue = number_format($columnRow[$columnName], 0, "", "");
						break;
					case "select":
						$dataValue = htmlText(getFirstPart($columnRow[$columnName . "_display"], 30));
						break;
					case "decimal":
						$dataValue = number_format($columnRow[$columnName], $columns[$fullColumnName]->getControlValue('decimal_places'), ".", ",");
						break;
					case "tinyint":
						$dataValue = (empty($columnRow[$columnName]) ? "" : "YES");
						break;
					default:
						$dataValue = htmlText(getFirstPart($columnRow[$columnName], 30));
				}
				$rowData .= "<td class='" . $fullColumnName . "'>" . $dataValue . "</td>";
			}
			$rowData .= "</tr></table></li>";
			$returnArray['data_list'][$rowIndex] = $rowData;
		}
		ajaxResponse($returnArray);
	}

	function saveChanges() {
		$returnArray = array();
		$this->iDataSource->getDatabase()->startTransaction();
		$this->iDataSource->disableTransactions();
		$this->iDataSource->setSaveOnlyPresent(true);
		foreach ($_POST as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("primary_id_")) == "primary_id_") {
				$rowNumber = substr($fieldName, strlen("primary_id_"));
				if (!is_numeric($rowNumber)) {
					continue;
				}
				$primaryId = $fieldData;
				$sortOrder = $_POST['sort_order_' . $rowNumber];
				if (!$this->iDataSource->saveRecord(array("primary_id" => $primaryId, "name_values" => array("sort_order" => $sortOrder)))) {
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

	function jqueryTemplates() {
	}

	function setPreferences() {
	}

	function getDataList() {
	}

	function exportCSV($exportAll) {
	}

	function getRecord() {
	}

	function deleteRecord() {
	}

	function getSpreadsheetList() {
	}
}
