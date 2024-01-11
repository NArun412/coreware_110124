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

/**
 * class EditableList
 *
 * The EditableList is a custom control allowing the user to add, edit and delete subtable records.
 *
 * @author Kim D Geiger
 */
class EditableList extends CustomControl {

	private $iColumnList = false;
	private $iDescriptionValues = array();

	/**
	 *    function setColumnList
	 *
	 *    Normally, the list of columns included in the EditableList comes from the subtable. This allows the list to be
	 *    customize in cases where the list is not from a subtable.
	 *
	 * @param array of column objects
	 */
	function setColumnList($columnList) {
		$this->iColumnList = $columnList;
	}

	/**
	 *    function getTemplate
	 *
	 *    Return the JQuery template needed to make this control functional
	 *
	 * @return mixed
	 */
	function getTemplate() {
		return $this->getControl(true);
	}

	/**
	 *    function getControl
	 *
	 *    Generate the HTML markup that is the control
	 *
	 * @param bool $templateOnly
	 * @return mixed
	 */
	function getControl($templateOnly = false) {
		$readonly = $this->iColumn->getControlValue("readonly") == "true";
		$noDelete = $this->iColumn->getControlValue("no_delete") == "true";
		$noAdd = $this->iColumn->getControlValue("no_add") == "true";
		$controlName = $this->iColumn->getControlValue("column_name");
		$tooManyToDisplay = false;
		if ($this->iColumnList) {
			$columns = $this->iColumnList;
			$foreignKeys = array();
			$columnList = array_keys($this->iColumnList);
			$listTablePrimaryKey = "primary_key";
			$listTableControls = array();
		} else {
			$primaryTableName = $this->iColumn->getControlValue("primary_table");
			if (empty($primaryTableName)) {
				$primaryTableName = $this->iPageObject->getDataSource()->getPrimaryTable()->getName();
			}
			$primaryTable = new DataTable($primaryTableName);
			$listTableName = $this->iColumn->getControlValue("list_table");
			if ($listTableName) {
				$listTable = new DataTable($listTableName);
				$listTablePrimaryKey = $listTable->getPrimaryKey();
				$listTableControls = $this->iColumn->getControlValue("list_table_controls");
				if (!is_array($listTableControls)) {
					$listTableControls = array();
				}
				$foreignKey = $this->iColumn->getControlValue("foreign_key_field");
				if (empty($foreignKey)) {
					$foreignKey = $primaryTable->getPrimaryKey();
				}
				if ($GLOBALS['gPrimaryDatabase']->fieldExists($listTableName, $foreignKey) && !empty($GLOBALS['gLimitEditableListRows'])) {
					$resultSet = executeQuery("select " . $foreignKey . ",count(*) from " . $listTableName . " group by " . $foreignKey . " having count(*) > 1000");
					if ($resultSet['row_count'] > 0) {
						$tooManyToDisplay = true;
					}
				}
				$columns = $listTable->getColumns();
				$foreignKeys = $listTable->getForeignKeyList();
				$columnList = $this->iColumn->getControlValue("column_list");
				if (!empty($columnList) && !is_array($columnList)) {
					$columnList = explode(",", $columnList);
				}
				if (is_array($columnList)) {
					foreach ($columnList as $index => $columnName) {
						if (strpos($columnName, ".") === false) {
							$columnList[$index] = $listTableName . "." . $columnName;
						}
					}
				}
			} else {
				$columns = array();
				$columnData = $this->iColumn->getControlValue("column_list");
				if (!is_array($columnData)) {
					$columnData = explode(",", $columnData);
				}
				foreach ($columnData as $columnName => $thisColumnData) {
					if (!is_array($thisColumnData)) {
						$columnName = $thisColumnData;
					}
					$thisColumn = new DataColumn($columnName);
					if (is_array($thisColumnData)) {
						foreach ($thisColumnData as $thisControlName => $thisControlValue) {
							$thisColumn->setControlValue($thisControlName, $thisControlValue);
						}
					}
					$columns[$columnName] = $thisColumn;
				}
				$listTableControls = $this->iColumn->getControlValue("list_table_controls");
				if (!is_array($listTableControls)) {
					$listTableControls = array();
				}
				$listTablePrimaryKey = "primary_id";
			}
		}
		$tabindexValue = $this->iColumn->getControlValue("tabindex");
		if ($tabindexValue !== false) {
			if (empty($tabindexValue)) {
				$tabindex = "";
			} else {
				$tabindex = "tabindex='" . $tabindexValue . "'";
			}
		} else {
			$tabindex = "tabindex='10'";
		}
		ob_start();
		$columnCount = 0;
		$hideColumnCount = 0;
		$classes = $this->iColumn->getControlValue("classes");
		if (!is_array($classes)) {
			$classes = explode(" ", str_replace(",", " ", $classes));
		}
		if ($noAdd) {
			$classes[] = "no-add";
		}
		$maximumRows = $this->iColumn->getControlValue("maximum_rows");
		if (empty($maximumRows)) {
			$maximumRows = 1000;
		}
		if ($tooManyToDisplay) {
			echo "Too many to display";
		} else {
			?>
            <table class="editable-list custom-control <?= implode(" ", $classes) ?>" id="_<?= $controlName ?>_table" data-row_number="1"<?= (empty($maximumRows) ? "" : " data-maximum_rows='" . $maximumRows . "'") ?>>
                <tr class="table-header">
					<?php
					if (empty($columnList)) {
						foreach ($columns as $columnName => $originalColumn) {
							$thisColumn = clone $originalColumn;
							if (!in_array($thisColumn->getControlValue("column_name"), array("client_id", "version", $foreignKey, $listTablePrimaryKey))) {
								$columnList[] = $columnName;
							}
						}
					}
					foreach ($columnList as $columnName) {
						if (!array_key_exists($columnName, $columns)) {
							continue;
						}
						$thisColumn = clone $columns[$columnName];
						if (substr($columnName, (strlen("contact_id") * -1)) == "contact_id") {
							$thisColumn->setControlValue("data_type", "contact_picker");
							$thisColumn->setControlValue("subtype", "contact_picker");
						}
						if (substr($columnName, (strlen("user_id") * -1)) == "user_id") {
							$thisColumn->setControlValue("data_type", "user_picker");
							$thisColumn->setControlValue("subtype", "user_picker");
						}
						if (array_key_exists($columnName, $listTableControls)) {
							foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
								$thisColumn->setControlValue($columnControlName, $columnControlValue);
							}
						} else {
							$plainColumnName = $thisColumn->getControlValue("column_name");
							if (array_key_exists($plainColumnName, $listTableControls)) {
								foreach ($listTableControls[$plainColumnName] as $columnControlName => $columnControlValue) {
									$thisColumn->setControlValue($columnControlName, $columnControlValue);
								}
							}
						}
						$dataType = $thisColumn->getControlValue("data_type");
						$classes = $thisColumn->getControlValue("classes");
						if (!is_array($classes)) {
							$classes = explode(" ", str_replace(",", " ", $classes));
						}
						$classes[] = "size-11-point";
						if ($readonly) {
							$thisColumn->setControlValue("readonly", "true");
						}
						if ($thisColumn->getControlValue('data_type') == "varchar" && !$thisColumn->getControlValue("size")) {
							$thisColumn->setControlValue('size', min($thisColumn->getControlValue('maximum_length'), 30));
						}
						$originalColumnName = $thisColumn->getControlValue('column_name');
						if (array_key_exists($originalColumnName, $GLOBALS['gAutocompleteFields']) && !$thisColumn->getControlValue("no_autocomplete")) {
							$thisColumn->setControlValue("data_type", "autocomplete");
							if (!$thisColumn->getControlValue("data-autocomplete_tag")) {
								$thisColumn->setControlValue("data-autocomplete_tag", $thisColumn->getReferencedTable());
							}
							$classes[] = "editable-select";
						} else if ($thisColumn->getControlValue('foreign_key')) {
							if ($thisColumn->getControlValue('subtype')) {
								if ($dataType == "int" || $dataType == "select") {
									$thisColumn->setControlValue('data_type', $thisColumn->getControlValue('subtype'));
								}
							} else {
								$notNull = $thisColumn->getControlValue("not_null");
								$referencedTable = $thisColumn->getReferencedTable();
								if (!$this->iColumn->getControlValue("ignore_referenced_table_count") && !$notNull && !empty($referencedTable)) {
                                    $limitByClient = $GLOBALS['gPrimaryDatabase']->fieldExists($thisColumn->getReferencedTable(), "client_id") && empty($this->iColumn->getControlValue("limit_by_client"));
                                    if(DataTable::isEmpty($thisColumn->getReferencedTable(), $limitByClient)) {
                                        continue;
                                    }
                                }
								$thisColumn->setControlValue('data_type', "select");
								$classes[] = "editable-select";
							}
						}
						$thisColumn->setControlValue("classes", $classes);
						$columnCount++;
						if ($dataType == "hidden") {
							$hideColumnCount++;
						}
						?>
                        <th class="editable-list-header<?= ($dataType == "hidden" ? " hidden" : "") ?>"><?= $thisColumn->getControlValue('form_label') ?></th>
						<?php
					}
					$additionalColumn = $this->iColumn->getControlValue("additional_column");
					if (!empty($additionalColumn)) {
						if (is_array($additionalColumn)) {
							$additionalColumnHeader = $additionalColumn['form_label'];
						} else {
							$additionalColumnHeader = "";
						}
						?>
                        <th class='align-center'><?= $additionalColumnHeader ?></th>
						<?php
						$columnCount++;
					}
					?>
					<?php if (!$readonly) { ?>
                        <th class="export-editable-list editable-list-row-control"><span id="_export_<?= $controlName ?>" class="fad fa-download"></span></th>
					<?php } ?>
                </tr>
                <tr class="add-row">
					<?php
					$addRowText = $this->iColumn->getControlValue("add_row_text");
					if (empty($addRowText)) {
						$addRowText = "&nbsp;";
					}
					$addRowContent = $this->iColumn->getControlValue("add_row_content");
					if (empty($addRowContent)) {
						?>
                        <th class="align-right" colspan="<?= $columnCount - $hideColumnCount ?>"><?= $addRowText ?></th>
						<?php
					} else {
						echo $addRowContent;
					}
					?>
					<?php if (!$readonly) { ?>
                        <th class="editable-list-row-control"><?php if (!$noDelete) { ?><input type="hidden" name="_<?= $controlName ?>_delete_ids" id="_<?= $controlName ?>_delete_ids" data-crc_value="<?= getCrcValue("") ?>" /><?php } ?><?php if (!$noAdd) { ?>
                            <button <?= $tabindex ?> class="no-ui editable-list-add" data-list_identifier="<?= $controlName ?>"><span class='fad fa-plus-octagon'></span></button><?php } ?></th>
					<?php } ?>
                </tr>
            </table>
			<?php
		}
		$control = ob_get_clean();

# create template for new rows

		ob_start();
		if (!$tooManyToDisplay) {
			?>
            <table>
                <tbody id="_<?= $controlName ?>_new_row">
                <tr class="editable-list-data-row" id="_<?= $controlName ?>_row-%rowId%" data-row_id="%rowId%">
					<?php
					foreach ($columnList as $columnName) {
						if (!array_key_exists($columnName, $columns)) {
							continue;
						}
						$thisColumn = clone $columns[$columnName];
						if (substr($columnName, (strlen("contact_id") * -1)) == "contact_id") {
							$thisColumn->setControlValue("data_type", "contact_picker");
							$thisColumn->setControlValue("subtype", "contact_picker");
						}
						if (substr($columnName, (strlen("user_id") * -1)) == "user_id") {
							$thisColumn->setControlValue("data_type", "user_picker");
							$thisColumn->setControlValue("subtype", "user_picker");
						}
						if (array_key_exists($columnName, $listTableControls)) {
							foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
								$thisColumn->setControlValue($columnControlName, $columnControlValue);
							}
						} else {
							$plainColumnName = $thisColumn->getControlValue("column_name");
							if (array_key_exists($plainColumnName, $listTableControls)) {
								foreach ($listTableControls[$plainColumnName] as $columnControlName => $columnControlValue) {
									$thisColumn->setControlValue($columnControlName, $columnControlValue);
								}
							}
						}
						$dataType = $thisColumn->getControlValue("data_type");
						$cellClasses = $thisColumn->getControlValue("cell_classes");
						if (!is_array($cellClasses)) {
							$cellClasses = explode(" ", str_replace(",", " ", $cellClasses));
						}
						if ($readonly) {
							$thisColumn->setControlValue("readonly", "true");
						}
						$classes = $thisColumn->getControlValue("classes");
						if (!is_array($classes)) {
							$classes = explode(" ", str_replace(",", " ", $classes));
						}
						$originalColumnName = $thisColumn->getControlValue('column_name');
						$thisColumn->setControlValue('column_name', $controlName . "_" . $thisColumn->getControlValue('column_name') . "-%rowId%");
						$thisColumn->setControlValue('no_datepicker', true);
						if ($thisColumn->getControlValue('data_type') == "tinyint") {
							$thisColumn->setControlValue('form_label', "");
							$cellClasses[] = "align-center";
						} else if ($thisColumn->getControlValue('data_type') == "date") {
							$classes[] = "editable-date";
						} else if ($thisColumn->getControlValue('data_type') == "int" || $thisColumn->getControlValue('data_type') == "decimal") {
							$classes[] = "editable-number";
							if (empty($cellClasses) || (count($cellClasses) == 1 && empty($cellClasses[0]))) {
								$cellClasses = array("align-right");
							}
						}
						if (array_key_exists($originalColumnName, $GLOBALS['gAutocompleteFields']) && !$thisColumn->getControlValue("no_autocomplete")) {
							$thisColumn->setControlValue("data_type", "autocomplete");
							if (!$thisColumn->getControlValue("data-autocomplete_tag")) {
								$thisColumn->setControlValue("data-autocomplete_tag", $thisColumn->getReferencedTable());
							}
							$classes[] = "editable-select";
						} else if ($thisColumn->getControlValue('foreign_key')) {
							if ($thisColumn->getControlValue('subtype')) {
								if ($dataType == "int" || $dataType == "select") {
									$thisColumn->setControlValue('data_type', $thisColumn->getControlValue('subtype'));
								}
							} else {
								$notNull = $thisColumn->getControlValue("not_null");
								$referencedTable = $thisColumn->getReferencedTable();
								if (!$notNull && !empty($referencedTable)) {
                                    $limitByClient = $GLOBALS['gPrimaryDatabase']->fieldExists($thisColumn->getReferencedTable(), "client_id") && empty($this->iColumn->getControlValue("limit_by_client"));
                                    if(DataTable::isEmpty($thisColumn->getReferencedTable(), $limitByClient)) {
                                        continue;
                                    }
								}
								$classes[] = "editable-select";
								$thisColumn->setControlValue("data_type", "select");
							}
						}
						$initialValue = $thisColumn->getControlValue('initial_value');
						if (empty($initialValue)) {
							$thisColumn->setControlValue("initial_value", $thisColumn->getControlValue("default_value"));
						}
						if ($tabindexValue !== false) {
							$thisColumn->setControlValue("tabindex", $tabindexValue);
						}
						$thisColumn->setControlValue("classes", $classes);
						?>
                        <td class="<?= implode(" ", $cellClasses) ?><?= ($dataType == "hidden" ? " hidden" : "") ?>"><?= $thisColumn->getControl($this->iPageObject) ?></td>
						<?php
					}
					$additionalColumn = $this->iColumn->getControlValue("additional_column");
					if (!empty($additionalColumn)) {
						if (is_array($additionalColumn)) {
							$additionalColumnContent = $additionalColumn['content'];
						} else {
							$additionalColumnContent = $additionalColumn;
						}
						?>
                        <td class='align-center'><?= $additionalColumnContent ?></td>
						<?php
					}
					?>
					<?php if (!$readonly) { ?>
                        <td class="editable-list-row-control align-center"><input type="hidden" class="editable-list-primary-id" name="<?= $controlName ?>_<?= $listTablePrimaryKey ?>-%rowId%" id="<?= $controlName ?>_<?= $listTablePrimaryKey ?>-%rowId%"/><?php if (!$noDelete) { ?>
                            <button tabindex="0" class="no-ui editable-list-remove" data-list_identifier="<?= $controlName ?>"><span class='fad fa-trash-alt'></span></button><?php } ?></td>
					<?php } else { ?>
                        <input type="hidden" class="editable-list-primary-id" name="<?= $controlName ?>_<?= $listTablePrimaryKey ?>-%rowId%" id="<?= $controlName ?>_<?= $listTablePrimaryKey ?>-%rowId%"/>
					<?php } ?>
                </tr>
                </tbody>
            </table>
			<?php
		}
		$template = ob_get_clean();
		return ($templateOnly ? $template : $control);
	}

	/**
	 *    function getRecord
	 *
	 *    Return a data structure representing the data that will populate the control
	 *
	 * @return array of the data
	 */
	function getRecord($primaryId = "") {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryId;
		}
		if ($this->iColumnList) {
			return array();
		}
		$controlName = $this->iColumn->getControlValue("column_name");
		$getRecordFunction = $this->iColumn->getControlValue("get_record");
		if (!empty($getRecordFunction) && method_exists($this->iPageObject, $getRecordFunction)) {
			return $this->iPageObject->$getRecordFunction($controlName, $primaryId);
		}
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$primaryTable = new DataTable($primaryTableName);
		$listTableName = $this->iColumn->getControlValue("list_table");
		if (!$listTableName) {
			return array();
		}
		$listTable = new DataTable($listTableName);
		$listTablePrimaryKey = $listTable->getPrimaryKey();
		$listDataSource = new DataSource($listTableName);
		if (!empty($this->iColumn->getControlValue("no_limit_by_client"))) {
			$listDataSource->getPrimaryTable()->setLimitByClient(false);
		}
		$foreignKey = $this->iColumn->getControlValue("foreign_key_field");
		if (empty($foreignKey)) {
			$foreignKey = $primaryTable->getPrimaryKey();
		}
		$columnList = $this->iColumn->getControlValue("column_list");
		if (!empty($columnList) && !is_array($columnList)) {
			$columnList = explode(",", $columnList);
		}
		if (is_array($columnList)) {
			foreach ($columnList as $index => $columnName) {
				if (strpos($columnName, ".") === false) {
					$columnList[$index] = $listTableName . "." . $columnName;
				}
			}
		}
		$columns = $listDataSource->getColumns();
		$listTableControls = $this->iColumn->getControlValue("list_table_controls");
		if (!is_array($listTableControls)) {
			$listTableControls = array();
		}
		foreach ($columns as $columnName => $thisColumn) {
			if (array_key_exists($columnName, $listTableControls)) {
				foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
					$thisColumn->setControlValue($columnControlName, $columnControlValue);
				}
			} else {
				$plainColumnName = $thisColumn->getControlValue("column_name");
				if (array_key_exists($plainColumnName, $listTableControls)) {
					foreach ($listTableControls[$plainColumnName] as $columnControlName => $columnControlValue) {
						$thisColumn->setControlValue($columnControlName, $columnControlValue);
					}
				}
			}
		}
		if (empty($columnList)) {
			foreach ($columns as $columnName => $originalColumn) {
				$thisColumn = clone $originalColumn;
				if (!in_array($thisColumn->getControlValue("column_name"), array("client_id", "version", $foreignKey, $listTablePrimaryKey))) {
					$columnList[] = $columnName;
				}
			}
		}
		$returnData = array();
		if (!empty($primaryId)) {
			$filterText = "";
			$listDataSource->setSearchFields($foreignKey);
			if ($this->iColumn->getControlValue("primary_key_field")) {
				$resultSet = executeQuery("select " . $this->iColumn->getControlValue("primary_key_field") . " from " . $primaryTable->getName() . " where " . $primaryTable->getPrimaryKey() . " = ?", $primaryId);
				if ($row = getNextRow($resultSet)) {
					$filterText = $row[$this->iColumn->getControlValue("primary_key_field")];
				}
			} else {
				$filterText = $primaryId;
			}
			if (empty($filterText)) {
				return array();
			}
			$listDataSource->setFilterText($filterText);
			if ($this->iColumn->controlValueExists("sort_order")) {
				$sortOrderFields = explode(",", $this->iColumn->getControlValue("sort_order"));
				$sortOrderSetting = "";
				foreach ($sortOrderFields as $thisSortField) {
					$sortField = $listTableName . "." . $thisSortField;
					if (array_key_exists($sortField, $columns)) {
						$referencedTableName = $columns[$sortField]->getReferencedTable();
						$referencedColumnName = $columns[$sortField]->getReferencedColumn();
						$descriptionColumnName = $columns[$sortField]->getReferencedDescriptionColumns()[0];
						if (!empty($referencedTableName) && !empty($referencedColumnName) && !empty($descriptionColumnName)) {
							$listDataSource->addColumnControl($thisSortField . "_description", "select_value", "select " . $descriptionColumnName . " from " . $referencedTableName . " where " .
								$referencedColumnName . " = " . $sortField);
							$sortOrderSetting .= (empty($sortOrderSetting) ? "" : ",") . $thisSortField . "_description";
						} else {
							$sortOrderSetting .= (empty($sortOrderSetting) ? "" : ",") . $thisSortField;
						}
					}
				}
				$listDataSource->setSortOrder($sortOrderSetting);
			} else {
				if ($GLOBALS['gPrimaryDatabase']->fieldExists($listTableName, "sort_order")) {
					$listDataSource->setSortOrder("sort_order");
				}
			}
			if ($this->iColumn->controlValueExists("reverse_sort")) {
				$listDataSource->setReverseSort($this->iColumn->getControlValue("reverse_sort"));
			}
			if ($this->iColumn->controlValueExists("filter_where")) {
				$listDataSource->setFilterWhere(str_replace("%primary_id%", $primaryId, $this->iColumn->getControlValue("filter_where")));
			}
			$listData = $listDataSource->getDataList();
			foreach ($listData as $recordNumber => $record) {
				$newRecord = array();
				foreach ($record as $fieldName => $fieldData) {
					if ($fieldName != $listTablePrimaryKey && $fieldName != $foreignKey && !in_array($listDataSource->getPrimaryTable()->getName() . "." . $fieldName, $columnList)) {
						continue;
					}
					$thisColumn = $columns[$listDataSource->getPrimaryTable()->getName() . "." . $fieldName];
					if (empty($thisColumn)) {
						continue;
					}
					if ($fieldName == "contact_id") {
						$thisColumn->setControlValue("data_type", "contact_picker");
					} else if ($fieldName == "user_id") {
						$thisColumn->setControlValue("data_type", "user_picker");
					}
					$valueDescription = "";
					if ($fieldName != $foreignKey) {
						switch ($thisColumn->getControlValue('data_type')) {
							case "datetime":
							case "date":
								if ($thisColumn->getControlValue("date_format")) {
									$fieldData = (empty($fieldData) ? "" : date($thisColumn->getControlValue("date_format"), strtotime($fieldData)));
								} else {
									$fieldData = (empty($fieldData) ? "" : date("m/d/Y" . ($thisColumn->getControlValue('data_type') == "datetime" ? " g:i:sa" : ""), strtotime($fieldData)));
								}
								break;
							case "time":
								if ($thisColumn->getControlValue("date_format")) {
									$fieldData = (empty($fieldData) ? "" : date($thisColumn->getControlValue("date_format"), strtotime($fieldData)));
								} else {
									$fieldData = (empty($fieldData) ? "" : date("g:i a", strtotime($fieldData)));
								}
								break;
							case "tinyint":
								$fieldData = (empty($fieldData) ? "0" : "1");
								break;
							case "autocomplete":
								$descriptionFields = explode(",", $GLOBALS['gAutocompleteFields'][$fieldName]['description_field']);
								$valueDescription = "";
								foreach ($descriptionFields as $thisFieldName) {
									$tableDescription = "";
									if ($thisFieldName != "contact_id") {
										$tableDescriptionKey = $thisColumn->getReferencedTable() . "." . $thisFieldName;
										$whereStatement = ($GLOBALS['gPrimaryDatabase']->fieldExists($thisColumn->getReferencedTable(), "client_id") && empty($this->iColumn->getControlValue("limit_by_client")) ? " where client_id = " . $GLOBALS['gClientId'] : "");
										$whereStatement .= (empty($whereStatement) ? " where " : " and ") . $GLOBALS['gAutocompleteFields'][$fieldName]['key_field'] . " = ?";

										$descriptionSet = executeQuery("select " . $GLOBALS['gAutocompleteFields'][$fieldName]['key_field'] . "," . $thisFieldName . " from " . $thisColumn->getReferencedTable() .
											$whereStatement, $this->iDescriptionValues[$tableDescriptionKey][$fieldData]);
										while ($descriptionRow = getNextRow($descriptionSet)) {
											$tableDescription = $descriptionRow[$thisFieldName];
										}
									}
									$valueDescription .= (empty($valueDescription) ? "" : " - ") . ($thisFieldName == "contact_id" ? (empty($fieldData) ? "" : getDisplayName($fieldData)) : $tableDescription);
								}
								break;
							case "contact_picker":
								$description = getDisplayName($fieldData, array("include_company" => true));
								$address1 = getFieldFromId("address_1", "contacts", "contact_id", $fieldData);
								if (!empty($address1)) {
									if (!empty($description)) {
										$description .= " • ";
									}
									$description .= $address1;
								}
								$city = getFieldFromId("city", "contacts", "contact_id", $fieldData);
								$state = getFieldFromId("state", "contacts", "contact_id", $fieldData);
								if (!empty($state)) {
									if (!empty($city)) {
										$city .= ", ";
									}
									$city .= $state;
								}
								if (!empty($city)) {
									if (!empty($description)) {
										$description .= " • ";
									}
									$description .= $city;
								}
								$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $fieldData);
								if (!empty($emailAddress)) {
									if (!empty($description)) {
										$description .= " • ";
									}
									$description .= $emailAddress;
								}
								$valueDescription = $description;
								break;
							case "user_picker":
								$description = getUserDisplayName($fieldData);
								$valueDescription = $description;
								break;
							case "select":
								$choices = $thisColumn->getChoices($this->iPageObject, true);
								if (array_key_exists($fieldData, $choices)) {
									if ($choices[$fieldData]['inactive']) {
										$noInactiveTag = $thisColumn->getControlValue("no_inactive_tag");
										$valueDescription = $choices[$fieldData]['description'] . (!empty($noInactiveTag) ? "" : " (Inactive)");
									}
								}
								break;
						}
					}
					$newRecord[$fieldName] = array();
					$newRecord[$fieldName]['data_value'] = $fieldData;
					$newRecord[$fieldName]['crc_value'] = getCrcValue($fieldData);
					if ($valueDescription) {
						$newRecord[$fieldName]['description'] = $valueDescription;
					}
					if ($thisColumn->getControlValue('subtype') == "image") {
						if (!empty($fieldData)) {
							$newRecord[$fieldName]['description'] = getFieldFromId("description", "images", "image_id", $fieldData);
						}
						$newRecord[$fieldName]['image_view'] = getImageFilename($fieldData);
					}
					if ($thisColumn->getControlValue('subtype') == "file" && !empty($fieldData)) {
						$newRecord[$fieldName]['file_download'] = "/download.php?file_id=" . $fieldData;
					}
					if ($thisColumn->getControlValue('subtype') == "file" && !empty($fieldData) && $thisColumn->getControlValue("show_filename")) {
						$newRecord[$fieldName]['filename'] = getFieldFromId("filename", "files", "file_id", $fieldData);
					}
					if ($thisColumn->getControlValue('subtype') == "file" && !empty($fieldData) && $thisColumn->getControlValue("not_editable")) {
						$newRecord[$fieldName]['not_editable'] = true;
					}
				}
				if ($this->iColumn->getControlValue("not_editable")) {
					$newRecord['not_editable']['data_value'] = true;
				}
				$returnData[$recordNumber] = $newRecord;
			}
		}
		$returnArray = array();
		$massageDataFunction = $this->iColumn->getControlValue("massage_data");
		if (!empty($massageDataFunction) && method_exists($this->iPageObject, $massageDataFunction)) {
			$this->iPageObject->$massageDataFunction($primaryId, $returnData);
		}
		$minimumRows = $this->iColumn->getControlValue("minimum_rows");
		if (!empty($minimumRows) && is_numeric($minimumRows)) {
			while (count($returnData) < $minimumRows) {
				$returnData[] = array();
			}
		}
		$returnArray[$controlName] = $returnData;
		$returnArray["_" . $controlName . "_delete_ids"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		return $returnArray;
	}

	/**
	 *    function setPrimaryId - set the primary ID of the primary record that will be used to get the subtable records for this control
	 *
	 * @param Primary ID of primary table
	 */
	function setPrimaryId($primaryId) {
		$this->iPrimaryId = $primaryId;
	}

	/**
	 *    function saveData - given the name/value pairs, save the subtable records
	 *
	 * @param name/value pairs
	 */
	function saveData($nameValues, $parameters = array()) {
		$readonly = $this->iColumn->getControlValue("readonly") == "true";
		$noDelete = $this->iColumn->getControlValue("no_delete") == "true";
		if ($readonly) {
			return true;
		}
		if (empty($nameValues['primary_id'])) {
			$nameValues['primary_id'] = $this->iPrimaryId;
		}
		if ($this->iColumnList) {
			return true;
		}
		$controlName = $this->iColumn->getControlValue("column_name");
		$saveDataFunction = $this->iColumn->getControlValue("save_data");
		if (!empty($saveDataFunction) && method_exists($this->iPageObject, $saveDataFunction)) {
			$returnValue = $this->iPageObject->$saveDataFunction($controlName, $nameValues);
			if ($returnValue !== true) {
				$this->iErrorMessage = $returnValue;
				return false;
			} else {
				return true;
			}
		}
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		if (empty($primaryTableName)) {
			$primaryTableName = $this->iPageObject->getDataSource()->getPrimaryTable()->getName();
		}
		$primaryTable = new DataTable($primaryTableName);
		$listTableName = $this->iColumn->getControlValue("list_table");
		if (empty($listTableName)) {
			$columns = array();
			$columnData = $this->iColumn->getControlValue("column_list");
			if (!is_array($columnData)) {
				$columnData = explode(",", $columnData);
			}
			$listTableControls = $this->iColumn->getControlValue("list_table_controls");
			if (!is_array($listTableControls)) {
				$listTableControls = array();
			}
			foreach ($columnData as $columnName => $thisColumnData) {
				if (!is_array($thisColumnData)) {
					$columnName = $thisColumnData;
				}
				$thisColumn = new DataColumn($columnName);
				if (is_array($thisColumnData)) {
					foreach ($thisColumnData as $thisControlName => $thisControlValue) {
						$thisColumn->setControlValue($thisControlName, $thisControlValue);
					}
				}
				$columns[$columnName] = $thisColumn;
				if (array_key_exists($columnName, $listTableControls)) {
					foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
						$thisColumn->setControlValue($columnControlName, $columnControlValue);
					}
				}
			}
			$dataArray = array();
			foreach ($nameValues as $fieldName => $fieldData) {
				if (substr($fieldName, 0, strlen($controlName . "_primary_id-")) == $controlName . "_primary_id-") {
					$rowNumber = substr($fieldName, strlen($controlName . "_primary_id-"));
					if (!is_numeric($rowNumber)) {
						continue;
					}
					$thisArray = array();
					foreach ($columns as $columnName => $thisColumn) {
						$initialValue = $thisColumn->getControlValue("initial_value");
						$defaultValue = $thisColumn->getControlValue("default_value");
						if (empty($nameValues[$controlName . "_" . $columnName . "-" . $rowNumber]) && !empty($initialValue) && empty($fieldData)) {
							$nameValues[$controlName . "_" . $columnName . "-" . $rowNumber] = $initialValue;
						}
						$dataType = $thisColumn->getControlValue('data_type');
						if ($thisColumn->getControlValue("readonly") && ((empty($initialValue) && empty($defaultValue)))) {
							continue;
						}
						if (($dataType == "literal" || $dataType == "span") && empty($nameValues[$controlName . "_" . $columnName . "-" . $rowNumber])) {
							continue;
						}
						if (empty($nameValues[$controlName . "_" . $columnName . "-" . $rowNumber]) && !empty($defaultValue)) {
							$nameValues[$controlName . "_" . $columnName . "-" . $rowNumber] = $defaultValue;
						}
						if (array_key_exists($controlName . "_" . $columnName . "-" . $rowNumber, $nameValues)) {
							if ($dataType == "image" || $dataType == "image_input") {
								$thisArray[$columnName] = createImage($controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber . "_file");
								if (!$thisArray[$columnName]) {
									$thisArray[$columnName] = $nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber];
								}
							} else if ($dataType == "file") {
								$thisArray[$columnName] = createFile($controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber . "_file");
								if (!$thisArray[$columnName]) {
									$thisArray[$columnName] = $nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber];
								}
							} else {
								$thisArray[$columnName] = $nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber];
							}
						}
					}
					$dataArray[] = $thisArray;
				}
			}
			return jsonEncode($dataArray);
		}
		$listTableControls = $this->iColumn->getControlValue("list_table_controls");
		if (!is_array($listTableControls)) {
			$listTableControls = array();
		}
		$listDataSource = new DataSource($listTableName);
		$foreignKey = $this->iColumn->getControlValue("foreign_key_field");
		if (empty($foreignKey)) {
			$foreignKey = $primaryTable->getPrimaryKey();
		}
		$columns = $listDataSource->getColumns();
		foreach ($columns as $columnName => $thisColumn) {
			if (array_key_exists($columnName, $listTableControls)) {
				foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
					$thisColumn->setControlValue($columnControlName, $columnControlValue);
				}
			} else {
				$plainColumnName = $thisColumn->getControlValue("column_name");
				if (array_key_exists($plainColumnName, $listTableControls)) {
					foreach ($listTableControls[$plainColumnName] as $columnControlName => $columnControlValue) {
						$thisColumn->setControlValue($columnControlName, $columnControlValue);
					}
				}
			}
		}
		if (!$noDelete && array_key_exists("_" . $controlName . "_delete_ids", $nameValues)) {
			$deleteIdArray = explode(",", $nameValues["_" . $controlName . "_delete_ids"]);
			$deleteIds = array();
			foreach ($deleteIdArray as $thisId) {
				$thisId = getFieldFromId($listDataSource->getPrimaryTable()->getPrimaryKey(), $listDataSource->getPrimaryTable()->getName(),
					$listDataSource->getPrimaryTable()->getPrimaryKey(), $thisId, $foreignKey . " = ?", ($foreignKey == $primaryTable->getPrimaryKey() ? $nameValues['primary_id'] : $nameValues[$foreignKey]));
				if (!empty($thisId)) {
					$deleteIds[] = $thisId;
				}
			}
			if (!empty($deleteIds)) {
				$columns = $listDataSource->getColumns();
				$deleteRows = array();
				foreach ($columns as $columnName => $thisColumn) {
					if ($thisColumn->getControlValue("column_name") == $foreignKey) {
						continue;
					}
					$subtype = $thisColumn->getControlValue("subtype");
					switch ($subtype) {
						case "image":
						case "file":
							$resultSet = executeQuery("select " . $thisColumn->getControlValue("column_name") . " from " .
								$listDataSource->getPrimaryTable()->getName() . " where " . $foreignKey . " = ? and " .
								$listDataSource->getPrimaryTable()->getPrimaryKey() . " in (" . implode(",", $deleteIds) . ")",
								$nameValues['primary_id']);
							while ($row = getNextRow($resultSet)) {
								if (!empty($row[$thisColumn->getControlValue("column_name")])) {
									$deleteRows[] = array("table_name" => $subtype . "s", "key_name" => $subtype . "_id", "key_value" => $row[$thisColumn->getControlValue("column_name")]);
								}
							}
							freeResult($resultSet);
							break;
					}
				}
				if ($this->iColumn->getControlValue("primary_key_field")) {
					$primaryId = getFieldFromId($this->iColumn->getControlValue("primary_key_field"), $primaryTable->getName(), $primaryTable->getPrimaryKey(), $nameValues['primary_id']);
				} else {
					$primaryId = $nameValues['primary_id'];
				}
				foreach ($deleteIds as $deleteId) {
					if (!$listDataSource->deleteRecord(array("primary_id" => $deleteId))) {
						$this->iErrorMessage = $listDataSource->getErrorMessage();
						return false;
					}
				}
				$GLOBALS['gIgnoreError'] = true;
				$GLOBALS['gPrimaryDatabase']->ignoreError(true);
				foreach ($deleteRows as $deleteInfo) {
					executeQuery("delete from " . $deleteInfo['table_name'] . " where " . $deleteInfo['key_name'] . " = " . $deleteInfo['key_value']);
				}
				$GLOBALS['gIgnoreError'] = false;
				$GLOBALS['gPrimaryDatabase']->ignoreError(false);
			}
		}
		$listDataSource->disableTransactions();
		foreach ($nameValues as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen($controlName . "_" . $listDataSource->getPrimaryTable()->getPrimaryKey() . "-")) == $controlName . "_" . $listDataSource->getPrimaryTable()->getPrimaryKey() . "-") {
				$rowNumber = substr($fieldName, strlen($controlName . "_" . $listDataSource->getPrimaryTable()->getPrimaryKey() . "-"));
				if (!is_numeric($rowNumber)) {
					continue;
				}
				$saveDataArray = array();
				foreach ($columns as $columnName => $thisColumn) {
					$initialValue = $thisColumn->getControlValue("initial_value");
					$defaultValue = $thisColumn->getControlValue("default_value");
					if (empty($nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber]) && !empty($initialValue) && empty($fieldData)) {
						$nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber] = $initialValue;
					}
					$dataType = $thisColumn->getControlValue('data_type');
					if ($thisColumn->getControlValue("readonly") && (!empty($fieldData) || (empty($initialValue) && empty($defaultValue)))) {
						continue;
					}
					if (($dataType == "literal" || $dataType == "span") && empty($nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber])) {
						continue;
					}
# only use default value if the record is new or if the data type is not datetime
					if (empty($nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber]) && !empty($defaultValue)) {
						if ($dataType != "datetime" || empty($fieldData)) {
							$nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber] = $defaultValue;
						}
					}
					if ($this->iColumn->getControlValue("primary_key_field") && $this->iColumn->getControlValue("primary_key_field") != $primaryTable->getPrimaryKey()) {
						$primaryId = getFieldFromId($this->iColumn->getControlValue("primary_key_field"), $primaryTable->getName(), $primaryTable->getPrimaryKey(), $nameValues['primary_id']);
					} else {
						$primaryId = $nameValues['primary_id'];
					}
					if (array_key_exists($controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber, $nameValues)) {
						if (array_key_exists($controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber . "_file", $_FILES)) {
							$_FILES[$thisColumn->getControlValue('column_name') . "_file"] = $_FILES[$controlName . "_" .
							$thisColumn->getControlValue('column_name') . "-" . $rowNumber . "_file"];
						}
						$saveDataArray[$thisColumn->getControlValue('column_name')] = $nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber];
						if (array_key_exists("remove_" . $controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber, $nameValues)) {
							$saveDataArray["remove_" . $thisColumn->getControlValue('column_name')] = $nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber];
						}
					}
				}
				$saveDataArray[$foreignKey] = $primaryId;
				$beforeSaveRecordFunction = $this->iColumn->getControlValue("before_save_record");
				if (!empty($beforeSaveRecordFunction) && method_exists($this->iPageObject, $beforeSaveRecordFunction)) {
					$returnValue = $this->iPageObject->$beforeSaveRecordFunction($saveDataArray);
					if (!$returnValue) {
						continue;
					}
				}
				$skipEmptyField = $this->iColumn->getControlValue("skip_empty_field");
				if (!empty($skipEmptyField) && empty($saveDataArray[$skipEmptyField])) {
					continue;
				}
				$listDataSource->setSaveOnlyPresent(true);
				$listDataSource->ignoreVersion(true);
				if (!$listDataSource->saveRecord(array("name_values" => $saveDataArray, "primary_id" => $fieldData, "no_change_log" => $parameters['no_change_log']))) {
					$this->iErrorMessage = $listDataSource->getErrorMessage();
					return false;
				}
			}
		}
		return true;
	}

	function getCustomDataArray($simpleArray) {
		$columns = array();
		$listTableControls = $this->iColumn->getControlValue("list_table_controls");
		if (!is_array($listTableControls)) {
			$listTableControls = array();
		}
		$columnData = $this->iColumn->getControlValue("column_list");
		if (!is_array($columnData)) {
			$columnData = explode(",", $columnData);
		}
		foreach ($columnData as $columnName => $thisColumnData) {
			if (!is_array($thisColumnData)) {
				$columnName = $thisColumnData;
			}
			$thisColumn = new DataColumn($columnName);
			if (is_array($thisColumnData)) {
				foreach ($thisColumnData as $thisControlName => $thisControlValue) {
					$thisColumn->setControlValue($thisControlName, $thisControlValue);
				}
			}
			if (array_key_exists($columnName, $listTableControls)) {
				foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
					$thisColumn->setControlValue($columnControlName, $columnControlValue);
				}
			}
			$columns[$columnName] = $thisColumn;
		}

		$returnArray = array();
		if (is_array($simpleArray)) {
			foreach ($simpleArray as $thisRow) {
				$thisArray = array();
				if (is_array($thisRow)) {
					foreach ($thisRow as $fieldName => $fieldData) {
						$thisColumn = $columns[$fieldName];
						$thisArray[$fieldName] = array("data_value" => $fieldData, "crc_value" => getCrcValue($fieldData));
						$dataType = (empty($thisColumn) ? "" : $thisColumn->getControlValue('data_type'));
						if (($dataType == "image" || $dataType == "image_input") && !empty($fieldData)) {
							$thisArray[$fieldName]['image_view'] = getImageFilename($fieldData);
						}
						if ($dataType == "file" && !empty($fieldData)) {
							$thisArray[$fieldName]['file_download'] = "/download.php?file_id=" . $fieldData;
						}
					}
					$returnArray[] = $thisArray;
				}
			}
		}
		$minimumRows = $this->iColumn->getControlValue("minimum_rows");
		if (!empty($minimumRows) && is_numeric($minimumRows)) {
			while (count($returnArray) < $minimumRows) {
				$returnArray[] = array();
			}
		}
		return $returnArray;
	}

}

?>
