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
 * class FormList
 *
 * The FormList is a custom control allowing the user to add, edit and delete subtable records. It is similar to the
 * EditableList, but each record is a full form and not just a single line of fields.
 *
 * @author Kim D Geiger
 */
class FormList extends CustomControl {

	private $iColumnList = false;

	/**
	 *	function setColumnList
	 *
	 *	Normally, the list of columns included in the FormList comes from the subtable. This allows the list to be
	 *	customize in cases where the list is not from a subtable.
	 *
	 *  @param array of column objects
	 */
	function setColumnList($columnList) {
		$this->iColumnList = $columnList;
	}

	/**
	 *	function getControl
	 *
	 *	Generate the HTML markup that is the control
	 *
	 *  @param flag indicating whether to return the control or the template. This is only used internally.
	 *	@return markup for the control
	 */
	function getControl($templateOnly = false) {
		$readonly = $this->iColumn->getControlValue("readonly") == "true";
		$noDelete = $this->iColumn->getControlValue("no_delete") == "true";
		$noAdd = $this->iColumn->getControlValue("no_add") == "true";
		$controlName = $this->iColumn->getControlValue("column_name");
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
				$columns = $listTable->getColumns();
				$foreignKeys = $listTable->getForeignKeyList();
				$columnList = $this->iColumn->getControlValue("column_list");
				if (!empty($columnList) && !is_array($columnList)) {
					$columnList = explode(",",$columnList);
				}
				if (is_array($columnList)) {
					foreach ($columnList as $index => $columnName) {
						if (strpos($columnName,".") === false) {
							$columnList[$index] = $listTableName . "." . $columnName;
						}
					}
				}
			} else {
				$columns = array();
				$columnData = $this->iColumn->getControlValue("column_list");
				if (!is_array($columnData)) {
					$columnData = explode(",",$columnData);
				}
				foreach ($columnData as $columnName => $thisColumnData) {
					if (!is_array($thisColumnData)) {
						$columnName = $thisColumnData;
					}
					$thisColumn = new DataColumn($columnName);
					if (is_array($thisColumnData)) {
						foreach ($thisColumnData as $thisControlName => $thisControlValue) {
							$thisColumn->setControlValue($thisControlName,$thisControlValue);
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
		if (empty($columnList)) {
			foreach ($columns as $columnName => $originalColumn) {
				$thisColumn = clone $originalColumn;
				if (!in_array($thisColumn->getControlValue("column_name"),array("client_id","version",$foreignKey,$listTablePrimaryKey))) {
					$columnList[] = $columnName;
				}
			}
		}
		foreach ($listTableControls as $listTableColumnName => $controls) {
			$columnName = (empty($listTableName) ? "" : $listTableName . ".") . $listTableColumnName;
			if (!array_key_exists($columnName,$columns)) {
				$thisColumn = new DataColumn($listTableColumnName);
				foreach ($controls as $listTableControlName => $controlValue) {
					$thisColumn->setControlValue($listTableControlName,$controlValue);
				}
				$columns[$columnName] = $thisColumn;
				if (!in_array($columnName,$columnList)) {
					$columnList[] = $columnName;
				}
			} else {
				foreach ($controls as $listTableControlName => $controlValue) {
					$columns[$columnName]->setControlValue($listTableControlName,$controlValue);
				}
			}
		}
		foreach ($columns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
				if ($thisColumn->getControlValue('primary_table') == "") {
					$thisColumn->setControlValue("primary_table",$listTableName);
				}
			}
		}
		$additionalColumns = $this->iColumn->getControlValue("additional_columns");
		if (is_array($additionalColumns)) {
			foreach ($additionalColumns as $thisColumnName => $additionalColumnInfo) {
				$tableName = $additionalColumnInfo['table_name'];
				$fieldName = $additionalColumnInfo['field_name'];
				if ($tableName && $fieldName) {
					$thisColumn = new DataColumn($fieldName,$tableName);
					if (is_array($additionalColumnInfo['column_controls'])) {
						foreach ($additionalColumnInfo['column_controls'] as $columnControlName => $columnControlValue) {
							$thisColumn->setControlValue($columnControlName,$columnControlValue);
						}
					}
					$thisColumn->setControlValue("column_name",$thisColumnName);
					$columnList[] = $tableName . "." . $fieldName;
					$columns[$tableName . "." . $fieldName] = $thisColumn;
				}
			}
		}
		$addButtonLabel = $this->iColumn->getControlValue("add_button_label");
		if (empty($addButtonLabel)) {
			$addButtonLabel = getLanguageText("Add Record");
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
		$classes = $this->iColumn->getControlValue("classes");
		if (!is_array($classes)) {
			$classes = explode(" ",str_replace(","," ",$classes));
		}
		$maximumRows = $this->iColumn->getControlValue("maximum_rows");
		ob_start();
		?>
        <div class="custom-control form-list <?= implode(" ",$classes) ?>" id="_<?= $controlName ?>_form_list" data-row_number="1" data-control_name="<?= $controlName ?>" data-after_add_row="<?= $this->iColumn->getControlValue("after_add_row") ?>" data-title_generator="<?= $this->iColumn->getControlValue("title_generator") ?>"<?= (empty($maximumRows) ? "" : " data-maximum_rows='" . $maximumRows . "'") ?>>
			<?php if (!$readonly) { ?>
				<?php if (!$noAdd) { ?>
                    <button <?= $tabindex ?> class="form-list-add-button" data-list_identifier="<?= $controlName ?>"><?= $addButtonLabel ?></button>
				<?php } else { ?>
                    <div class="form-list-add-button"></div>
				<?php } ?>
				<?php if (!$noDelete) { ?>
                    <input type="hidden" name="_<?= $controlName ?>_delete_ids" id="_<?= $controlName ?>_delete_ids" data-crc_value="<?= getCrcValue("") ?>" />
				<?php } ?>
			<?php } else { ?>
                <div class="form-list-add-button"></div>
			<?php } ?>
        </div>
		<?php
		$control = ob_get_clean();

# create template for new rows

		$alwaysOpen = $this->iColumn->getControlValue("always_open");
		ob_start();
		?>
        <div id="_<?= $controlName ?>_new_row">
            <div data-div_open="<?= ($alwaysOpen ? "true" : "") ?>" id="_<?= $controlName ?>_row-%rowId%" class="form-list-item" data-row_id="%rowId%">
                <div class="form-list-item-header<?= ($alwaysOpen ? " always-open" : "") ?>">
					<?php if (!$alwaysOpen) { ?>
                        <span class="form-list-item-caret fa fa-caret-right"></span>
					<?php } ?>
                    <p class="form-list-item-title"></p>
                    <input type="hidden" class="form-list-primary-id" name="<?= $controlName ?>_<?= $listTablePrimaryKey ?>-%rowId%" id="<?= $controlName ?>_<?= $listTablePrimaryKey ?>-%rowId%" />
					<?php if (!$readonly && !$noDelete) { ?>
                        <button <?= $tabindex ?> class="no-ui form-list-remove" data-list_identifier="<?= $controlName ?>"><span class='fad fa-trash-alt'></span></button>
					<?php } ?>
                </div> <!-- form-list-item-header -->
                <div class="form-list-item-form<?= ($alwaysOpen ? "" : " hidden") ?>">
					<?php
					$formFilename = $this->iColumn->getControlValue("form_filename");
					if (!empty($formFilename) && file_exists($GLOBALS['gDocumentRoot'] . "/forms/" . $formFilename)) {
						$formContentArray = array();
						$filename = $GLOBALS['gDocumentRoot'] . "/forms/" . $formFilename;
						$handle = fopen($filename, 'r');
						while ($thisLine = fgets($handle)) {
							$formContentArray[] = $thisLine;
						}
						fclose($handle);

						# Iterate through the lines of the form to generate the HTML markup of the form
						$contentIndex = 0;
						$useLine = true;
						$skipField = false;
						$ifStatements = array(true);
						while (true) {
							if ($contentIndex > (count($formContentArray) + 1)) {
								break;
							}
							$line = trim($formContentArray[$contentIndex]);
							$contentIndex++;
							if ($line == "%endif%") {
								array_shift($ifStatements);
								if (!$ifStatements) {
									$ifStatements = array(true);
								}
								$useLine = true;
								foreach ($ifStatements as $ifResult) {
									$useLine = $useLine && $ifResult;
								}
								continue;
							}

							# an if statement in the form allows conditional inclusion of parts of the form
							if (startsWith($line,"%if:")) {
								$evalStatement = substr($line,strlen("%if:"),-1);
								if (!startsWith($evalStatement,"return ")) {
									$evalStatement = "return " . $evalStatement;
								}
								if (substr($evalStatement,-1) == "%") {
									$evalStatement = substr($evalStatement,0,-1);
								}
								if (substr($evalStatement,-1) != ";") {
									$evalStatement .= ";";
								}
								$thisResult = eval($evalStatement);
								array_unshift($ifStatements,$thisResult);
								$useLine = $useLine && $thisResult;
								continue;
							}
							if (!$useLine) {
								continue;
							}

							if (startsWith($line,"%field:")) {
								$fieldName = trim(str_replace("%","",substr($line,strlen("%field:"))));
								if (strpos($fieldName,".") === false) {
									$fieldName = $listTableName . "." . $fieldName;
								}
								if (array_key_exists($fieldName,$columns)) {
									$thisColumn = clone $columns[$fieldName];
									if ($readonly) {
										$thisColumn->setControlValue("readonly",true);
										$thisColumn->setControlValue("not_null",false);
									}
									if (!$thisColumn->getControlValue("form_label")) {
										$thisColumn->setControlValue("form_label","");
									}
									if ($thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" &&
										!$thisColumn->getControlValue('readonly')) {
										$thisColumn->setControlValue("label_class","required-label");
									} else {
										$thisColumn->setControlValue("label_class","");
									}
									if (!$thisColumn->controlValueExists("form_line_classes")) {
										$thisColumn->setControlValue("form_line_classes", "");
									}
									$thisColumn->setControlValue('column_name',$controlName . "_" . $thisColumn->getControlValue('column_name') . "-%rowId%");
									if ($thisColumn->getControlValue("data_type") == "date" && $thisColumn->getControlValue("no-datepicker") != true && !$thisColumn->getControlValue("readonly")) {
										$thisColumn->setControlValue("classes","template-datepicker");
										$thisColumn->setControlValue('no_datepicker',true);
									}
									$thisColumn->setControlValue("initial_value",$thisColumn->getControlValue("default_value"));
								} else {
									$thisColumn = false;
								}
								continue;
							}

							if ($skipField) {
								if (!$thisColumn) {
									continue;
								} else {
									$skipField = false;
								}
							}
							if ($thisColumn) {
								if ($thisColumn->getControlValue('data_type') == "tinyint") {
									$line = str_replace("%form_label%","&nbsp;",$line);
								}
								foreach ($thisColumn->getAllControlValues() as $infoName => $infoData) {
									if (is_string($infoData)) {
										if ($infoName != "form_label") {
											$infoData = htmlText($infoData);
										}
										$line = str_replace("%" . $infoName . "%",$infoData,$line);
									}
								}
								if (strpos($line,"%input_control%") !== false) {
									$line = str_replace("%input_control%",$thisColumn->getControl($this->iPageObject),$line);
								}
							}

							# Substitute a String from the database for multi-lingual support
							if (strpos($line,"%programText:") !== false) {
								$startPosition = strpos($line,"%programText:");
								$programTextCode = substr($line,$startPosition + strlen("%programText:"),strpos($line,"%",$startPosition + 1) - ($startPosition + strlen("%programText:")));
								$programText = getLanguageText($programTextCode);
								$line = str_replace("%programText:" . $programTextCode . "%",$programText,$line);
							}

							echo $line . "\n";
						}

					} else {
						foreach ($columnList as $columnName) {
							if (!array_key_exists($columnName,$columns)) {
								continue;
							}
							$thisColumn = clone $columns[$columnName];
							if (substr($columnName,(strlen("contact_id") * -1)) == "contact_id") {
								$thisColumn->setControlValue("data_type","contact_picker");
								$thisColumn->setControlValue("subtype","contact_picker");
							}
							if (substr($columnName,(strlen("user_id") * -1)) == "user_id") {
								$thisColumn->setControlValue("data_type","user_picker");
								$thisColumn->setControlValue("subtype","user_picker");
							}
							if (array_key_exists($columnName,$listTableControls)) {
								foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
									$thisColumn->setControlValue($columnControlName,$columnControlValue);
								}
							} else {
								$plainColumnName = $thisColumn->getControlValue("column_name");
								if (array_key_exists($plainColumnName,$listTableControls)) {
									foreach ($listTableControls[$plainColumnName] as $columnControlName => $columnControlValue) {
										$thisColumn->setControlValue($columnControlName,$columnControlValue);
									}
								}
							}
							$cellClass = "";
							if ($readonly) {
								$thisColumn->setControlValue("readonly","true");
							}
							if (!$thisColumn->controlValueExists("form_line_classes")) {
								$thisColumn->setControlValue("form_line_classes", "");
							}
							if ($thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" &&
								!$thisColumn->getControlValue('readonly')) {
								$labelClass = "required-label";
							} else {
								$labelClass= "";
							}
							$thisColumn->setControlValue('column_name',$controlName . "_" . $thisColumn->getControlValue('column_name') . "-%rowId%");
							if ($thisColumn->getControlValue("data_type") == "date" && $thisColumn->getControlValue("no-datepicker") != true && !$thisColumn->getControlValue("readonly")) {
								$thisColumn->setControlValue("classes","template-datepicker");
								$thisColumn->setControlValue('no_datepicker',true);
							}
							$thisColumn->setControlValue("initial_value",$thisColumn->getControlValue("default_value"));
							?>
                            <div class="basic-form-line <?= str_replace(","," ",$thisColumn->getControlValue("form_line_classes")) ?>" id="_<?= $thisColumn->getControlValue("column_name") ?>_row">
                                <label for="<?= $thisColumn->getControlValue("column_name") ?>" class="<?= $labelClass ?>"><?= ($thisColumn->getControlValue('data_type') == "tinyint" ? "" : htmlText($thisColumn->getControlValue('form_label'))) ?></label>
								<?= $thisColumn->getControl($this->iPageObject) ?>
                                <div class='basic-form-line-messages'><span class="help-label"><?= $thisColumn->getControlValue('help_label') ?></span><span class='field-error-text'></span></div>
                            </div>
							<?php
						}
					}
					?>
                </div> <!-- form-list-item-form -->
            </div> <!-- form-list-item -->
        </div> <!-- <?= $controlName ?>_form_list_template -->
		<?php
		foreach ($columnList as $columnName) {
			if (!array_key_exists($columnName,$columns)) {
				continue;
			}
			$thisColumn = clone $columns[$columnName];
			if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
				$thisColumn->setControlValue('column_name',$controlName . "_" . $thisColumn->getControlValue('column_name') . "-sectionId");
				if ($readonly) {
					$thisColumn->setControlValue("readonly","true");
				}
				$controlClass = $thisColumn->getControlValue("control_class");
				$customControl = new $controlClass($thisColumn,$this->iPageObject);
				echo $customControl->getTemplate();
			}
		}
		$template = ob_get_clean();
		return ($templateOnly ? $template : $control);
	}

	/**
	 *	function getTemplate
	 *
	 *	Return the JQuery template needed to make this control functional
	 *
	 *  @return markup for the template
	 */
	function getTemplate() {
		return $this->getControl(true);
	}

	/**
	 *	function getRecord
	 *
	 *	Return a data structure representing the data that will populate the control
	 *
	 *  @return array of the data
	 */
	function getRecord($primaryId = "") {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryId;
		}
		$controlName = $this->iColumn->getControlValue("column_name");
		$getRecordFunction = $this->iColumn->getControlValue("get_record");
		if (!empty($getRecordFunction) && method_exists($this->iPageObject,$getRecordFunction)) {
			return $this->iPageObject->$getRecordFunction($this,$primaryId);
		}
		$supplementGetRecordFunction = $this->iColumn->getControlValue("supplement_get_record");
		if ($this->iColumnList) {
			return array();
		}
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$primaryTable = new DataTable($primaryTableName);
		$listTableName = $this->iColumn->getControlValue("list_table");
		if (!$listTableName) {
			return array();
		}
		$listDataSource = new DataSource($listTableName);
		$foreignKey = $this->iColumn->getControlValue("foreign_key_field");
		if (empty($foreignKey)) {
			$foreignKey = $primaryTable->getPrimaryKey();
		}
		$columns = $listDataSource->getColumns();
		$listTableControls = $this->iColumn->getControlValue("list_table_controls");
		if (!is_array($listTableControls)) {
			$listTableControls = array();
		}
		foreach ($listTableControls as $listTableColumnName => $controls) {
			if (!array_key_exists($listTableName . "." . $listTableColumnName,$columns)) {
				$thisColumn = new DataColumn($listTableColumnName);
				foreach ($controls as $listTableControlName => $controlValue) {
					$thisColumn->setControlValue($listTableControlName,$controlValue);
				}
				$columns[$listTableName . "." . $listTableColumnName] = $thisColumn;
			} else {
				foreach ($controls as $listTableControlName => $controlValue) {
					$columns[$listTableName . "." . $listTableColumnName]->setControlValue($listTableControlName,$controlValue);
				}
			}
		}
		$returnData = array();
		if (!empty($primaryId)) {
			$listDataSource->setSearchFields($foreignKey);
			if ($this->iColumn->getControlValue("primary_key_field")) {
				$filterText = getFieldFromId($this->iColumn->getControlValue("primary_key_field"),$primaryTable->getName(),$primaryTable->getPrimaryKey(),$primaryId);
			} else {
				$filterText = $primaryId;
			}
			if (empty($filterText)) {
				return array();
			}
			$listDataSource->setFilterText($filterText);
			if ($this->iColumn->controlValueExists("sort_order")) {
				$sortOrderFields = explode(",",$this->iColumn->getControlValue("sort_order"));
				$sortOrderSetting = "";
				foreach ($sortOrderFields as $thisSortField) {
					$sortField = $listTableName . "." . $thisSortField;
					if (array_key_exists($sortField,$columns)) {
						$referencedTableName = $columns[$sortField]->getReferencedTable();
						$referencedColumnName = $columns[$sortField]->getReferencedColumn();
						$descriptionColumnName = $columns[$sortField]->getReferencedDescriptionColumns()[0];
						if (!empty($referencedTableName) && !empty($referencedColumnName) && !empty($descriptionColumnName)) {
							$listDataSource->addColumnControl($thisSortField . "_description","select_value","select " . $descriptionColumnName . " from " . $referencedTableName . " where " .
								$referencedColumnName . " = " . $sortField);
							$sortOrderSetting .= (empty($sortOrderSetting) ? "" : ",") . $thisSortField . "_description";
						} else {
							$sortOrderSetting .= (empty($sortOrderSetting) ? "" : ",") . $thisSortField;
						}
					}
				}
				$listDataSource->setSortOrder($sortOrderSetting);
			} else {
				if ($GLOBALS['gPrimaryDatabase']->fieldExists($listTableName,"sort_order")) {
					$listDataSource->setSortOrder("sort_order");
				}
			}
			if ($this->iColumn->controlValueExists("reverse_sort")) {
				$listDataSource->setReverseSort($this->iColumn->getControlValue("reverse_sort"));
			}
			if ($this->iColumn->controlValueExists("filter_where")) {
				$listDataSource->setFilterWhere($this->iColumn->getControlValue("filter_where"));
			}
			$listData = $listDataSource->getDataList();
			foreach ($listData as $recordNumber => $record) {
				$newRecord = array();
				foreach ($record as $fieldName => $fieldData) {
					$thisColumn = $columns[$listDataSource->getPrimaryTable()->getName() . "." . $fieldName];
                    if (empty($thisColumn)) {
	                    continue;
                    }
					switch ($thisColumn->getControlValue('data_type')) {
						case "datetime":
						case "date":
							$fieldData = (empty($fieldData) ? "" : date("m/d/Y" . ($thisColumn->getControlValue('data_type') == "datetime" ? " g:i:sa" : ""),strtotime($fieldData)));
							break;
						case "tinyint":
							$fieldData = (empty($fieldData) ? "0" : "1");
							break;
						case "autocomplete":
							if (array_key_exists($fieldName,$GLOBALS['gAutocompleteFields'])) {
								$descriptionFields = explode(",",$GLOBALS['gAutocompleteFields'][$fieldName]['description_field']);
								$descriptionValue = "";
								foreach ($descriptionFields as $thisFieldName) {
									$descriptionValue .= (empty($descriptionValue) ? "" : " - ") .
										($thisFieldName == "contact_id" ? (empty($fieldData) ? "" : getDisplayName($fieldData)) : getFieldFromId($thisFieldName,$thisColumn->getReferencedTable(),
											$GLOBALS['gAutocompleteFields'][$fieldName]['key_field'],$fieldData));
								}
								$newRecord[$fieldName . "-%rowId%_autocomplete_text"] = array("data_value"=>$descriptionValue);
							}
							break;
						case "contact_picker":
							$description = getDisplayName($fieldData,array("include_company"=>true));
							$address1 = getFieldFromId("address_1","contacts","contact_id",$fieldData);
							if (!empty($address1)) {
								if (!empty($description)) {
									$description .= " • ";
								}
								$description .= $address1;
							}
							$city = getFieldFromId("city","contacts","contact_id",$fieldData);
							$state = getFieldFromId("state","contacts","contact_id",$fieldData);
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
							$emailAddress = getFieldFromId("email_address","contacts","contact_id",$fieldData);
							if (!empty($emailAddress)) {
								if (!empty($description)) {
									$description .= " • ";
								}
								$description .= $emailAddress;
							}
							if ($fieldData) {
								$newRecord['select_values'][$fieldName . "-%rowId%_selector"] = array(array("key_value"=>$fieldData,"description"=>$description));
							}
							$newRecord[$fieldName . "-%rowId%_selector"] = array("data_value"=>$fieldData);
							break;
						case "user_picker":
							$description = getUserDisplayName($fieldData);
							if ($fieldData) {
								$newRecord['select_values'][$fieldName . "-%rowId%_selector"] = array(array("key_value"=>$fieldData,"description"=>$description));
							}
							$newRecord[$fieldName . "-%rowId%_selector"] = array("data_value"=>$fieldData);
							break;
						case "image":
						case "image_picker":
							if ($fieldData) {
								$newRecord['select_values'][$fieldName . "-%rowId%"] = array(array("key_value"=>$fieldData,"description"=>getFieldFromId("description","images","image_id",$fieldData)));
							}
							break;
					}
					$newRecord[$fieldName] = array();
					$newRecord[$fieldName]['data_value'] = $fieldData;
					$newRecord[$fieldName]['crc_value'] = getCrcValue($fieldData);
					if ($thisColumn->getControlValue('subtype') == "image") {
						$newRecord[$fieldName]['image_view'] = getImageFilename($fieldData);
					}
					if ($thisColumn->getControlValue('subtype') == "file" && !empty($fieldData)) {
						$newRecord[$fieldName]['file_download'] = "/download.php?file_id=" . $fieldData;
					}
				}
				if ($this->iColumn->getControlValue("not_editable")) {
					$newRecord['not_editable']['data_value'] = true;
				}
				$additionalColumns = $this->iColumn->getControlValue("additional_columns");
				if (is_array($additionalColumns)) {
					foreach ($additionalColumns as $thisColumnName => $additionalColumnInfo) {
						$tableName = $additionalColumnInfo['table_name'];
						$fieldName = $additionalColumnInfo['field_name'];
						if ($tableName && $fieldName) {
							$thisColumn = new DataColumn($fieldName,$tableName);
							if (is_array($additionalColumnInfo['column_controls'])) {
								foreach ($additionalColumnInfo['column_controls'] as $columnControlName => $columnControlValue) {
									$thisColumn->setControlValue($columnControlName,$columnControlValue);
								}
							}
							$fieldData = getFieldFromId($fieldName,$tableName,$additionalColumnInfo['foreign_key'],$record[$additionalColumnInfo['foreign_key']],$additionalColumnInfo['extra_where']);
							switch ($thisColumn->getControlValue('data_type')) {
								case "datetime":
								case "date":
									$fieldData = (empty($fieldData) ? "" : date("m/d/Y" . ($thisColumn->getControlValue('data_type') == "datetime" ? " g:i:sa" : ""),strtotime($fieldData)));
									break;
								case "tinyint":
									$fieldData = (empty($fieldData) ? "0" : "1");
									break;
							}
							$newRecord[$thisColumnName] = array();
							$newRecord[$thisColumnName]['data_value'] = $fieldData;
							$newRecord[$thisColumnName]['crc_value'] = getCrcValue($fieldData);
						}
					}
				}
				foreach ($columns as $columnName => $originalColumn) {
					if ($originalColumn->getControlValue('data_type') == "custom_control" || $originalColumn->getControlValue('data_type') == "custom") {
						$thisColumn = clone $originalColumn;
						$primaryKey = $listDataSource->getPrimaryTable()->getPrimaryKey();
						$thisColumnName = $controlName . "_" . $thisColumn->getControlValue('column_name');
						$thisColumn->setControlValue("primary_table",$listTableName);
						$thisColumn->setControlValue("column_name",$thisColumnName);
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn,$this->iPageObject);
						foreach ($customControl->getRecord($record[$primaryKey]) as $keyValue => $dataValue) {
							$newRecord[$keyValue] = $dataValue;
						}
					}
				}
				if (!empty($supplementGetRecordFunction) && method_exists($this->iPageObject,$supplementGetRecordFunction)) {
					$this->iPageObject->$supplementGetRecordFunction($newRecord);
				}
				$returnData[$recordNumber] = $newRecord;
			}
		}
		$minimumRows = $this->iColumn->getControlValue("minimum_rows");
		if (!empty($minimumRows) && is_numeric($minimumRows)) {
			while (count($returnData) < $minimumRows) {
				$returnData[] = array();
			}
		}
		$returnArray = array();
		$returnArray[$controlName] = $returnData;
		$returnArray["_" . $controlName . "_delete_ids"] = array("data_value"=>"","crc_value"=>getCrcValue(""));
		return $returnArray;
	}

	/**
	 *	function setPrimaryId - set the primary ID of the primary record that will be used to get the subtable records for this control
	 *
	 *  @param Primary ID of primary table
	 */
	function setPrimaryId($primaryId) {
		$this->iPrimaryId = $primaryId;
	}

	/**
	 *	function saveData - given the name/value pairs, save the subtable records
	 *
	 *  @param name/value pairs
	 */
	function saveData($nameValues,$parameters=array()) {
		$readonly = $this->iColumn->getControlValue("readonly") == "true";
		$noDelete = $this->iColumn->getControlValue("no_delete") == "true";
		$noAdd = $this->iColumn->getControlValue("no_add") == "true";
		if ($readonly) {
			return true;
		}
		if (empty($nameValues['primary_id'])) {
			$nameValues['primary_id'] = $this->iPrimaryId;
		}
		$controlName = $this->iColumn->getControlValue("column_name");
		$saveDataFunction = $this->iColumn->getControlValue("save_data");
		if (!empty($saveDataFunction) && method_exists($this->iPageObject,$saveDataFunction)) {
			$returnValue = $this->iPageObject->$saveDataFunction($controlName,$nameValues);
			if ($returnValue !== true) {
				$this->iErrorMessage = $returnValue;
				return false;
			} else {
				return true;
			}
		}
		if ($this->iColumnList) {
			return true;
		}
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$primaryTable = new DataTable($primaryTableName);
		$listTableName = $this->iColumn->getControlValue("list_table");
		if (empty($listTableName)) {
			$columns = array();
			$columnData = $this->iColumn->getControlValue("column_list");
			if (!is_array($columnData)) {
				$columnData = explode(",",$columnData);
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
						$thisColumn->setControlValue($thisControlName,$thisControlValue);
					}
				}
				$columns[$columnName] = $thisColumn;
				if (array_key_exists($columnName,$listTableControls)) {
					foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
						$thisColumn->setControlValue($columnControlName,$columnControlValue);
					}
				}
			}
			$dataArray = array();
			foreach ($nameValues as $fieldName => $fieldData) {
				if (startsWith($fieldName,$controlName . "_primary_id-")) {
					$rowNumber = substr($fieldName,strlen($controlName . "_primary_id-"));
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
						if (array_key_exists($controlName . "_" . $columnName . "-" . $rowNumber,$nameValues)) {
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
		foreach ($listTableControls as $listTableColumnName => $controls) {
			if (array_key_exists($listTableName . "." . $listTableColumnName,$columns)) {
				$columnIndex = $listTableName . "." . $listTableColumnName;
				$thisColumn = $columns[$columnIndex];
			} else {
				$columnIndex = $listTableColumnName;
				$thisColumn = new DataColumn($listTableColumnName);
			}
			foreach ($controls as $listTableControlName => $controlValue) {
				$thisColumn->setControlValue($listTableControlName,$controlValue);
			}
			$columns[$columnIndex] = $thisColumn;
		}
		if (!$noDelete && array_key_exists("_" . $controlName . "_delete_ids",$nameValues)) {
			$deleteIdArray = explode(",",$nameValues["_" . $controlName . "_delete_ids"]);
			$deleteIds = array();
			foreach ($deleteIdArray as $thisId) {
				$thisId = getFieldFromId($listDataSource->getPrimaryTable()->getPrimaryKey(),$listDataSource->getPrimaryTable()->getName(),
					$listDataSource->getPrimaryTable()->getPrimaryKey(),$thisId,$foreignKey . " = ?",$nameValues['primary_id']);
				if (!empty($thisId)) {
					$deleteIds[] = $thisId;
				}
			}
			if (!empty($deleteIds)) {
				$deleteRows = array();
				$subtables = explode(",",$this->iColumn->getControlValue("subtables"));
				if (!empty($subtables)) {
					foreach ($subtables as $subtableName) {
						if (empty($subtableName)) {
							continue;
						}
						$subtable = new DataTable($subtableName);
						$subtableColumns = $subtable->getColumns();
						foreach ($subtableColumns as $columnName => $thisColumn) {
							$subtype = $thisColumn->getControlValue("subtype");
							switch ($subtype) {
								case "image":
								case "file":
									$resultSet = executeQuery("select " . $thisColumn->getControlValue("column_name") . " from " .
										$subtableName . " where " . $listDataSource->getPrimaryTable()->getPrimaryKey() . " in (" . implode(",",$deleteIds) . ")");
									while ($row = getNextRow($resultSet)) {
										if (!empty($row[$thisColumn->getControlValue("column_name")])) {
											$deleteRows[] = array("table_name"=>$subtype . "s","key_name"=>$subtype . "_id","key_value"=>$row[$thisColumn->getControlValue("column_name")]);
										}
									}
									freeResult($resultSet);
									break;
							}
						}
						executeQuery("delete from " . $subtableName . " where " . $listDataSource->getPrimaryTable()->getPrimaryKey() . " in (" . implode(",",$deleteIds) . ")");
					}
				}
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
								$listDataSource->getPrimaryTable()->getPrimaryKey() . " in (" . implode(",",$deleteIds) . ")",
								$nameValues['primary_id']);
							while ($row = getNextRow($resultSet)) {
								if (!empty($row[$thisColumn->getControlValue("column_name")])) {
									$deleteRows[] = array("table_name"=>$subtype . "s","key_name"=>$subtype . "_id","key_value"=>$row[$thisColumn->getControlValue("column_name")]);
								}
							}
							freeResult($resultSet);
							break;
					}
				}
				if ($this->iColumn->getControlValue("primary_key_field")) {
					$primaryId = getFieldFromId($this->iColumn->getControlValue("primary_key_field"),$primaryTable->getName(),$primaryTable->getPrimaryKey(),$nameValues['primary_id']);
				} else {
					$primaryId = $nameValues['primary_id'];
				}
				foreach ($deleteIds as $deleteId) {
					if (!$listDataSource->deleteRecord(array("primary_id"=>$deleteId))) {
						$this->iErrorMessage = $listDataSource->getErrorMessage();
						return false;
					}
				}
				foreach ($deleteRows as $deleteInfo) {
					executeQuery("delete from " . $deleteInfo['table_name'] . " where " . $deleteInfo['key_name'] . " = " . $deleteInfo['key_value']);
				}
			}
		}
		$listDataSource->disableTransactions();
		foreach ($nameValues as $fieldName => $fieldData) {
			if (startsWith($fieldName,$controlName . "_" . $listDataSource->getPrimaryTable()->getPrimaryKey() . "-")) {
				$rowNumber = substr($fieldName,strlen($controlName . "_" . $listDataSource->getPrimaryTable()->getPrimaryKey() . "-"));
				if (!is_numeric($rowNumber)) {
					continue;
				}
				if ($noAdd && empty($fieldData)) {
					continue;
				}
				$saveDataArray = array();
				foreach ($columns as $columnName => $thisColumn) {
					$defaultValue = $thisColumn->getControlValue("default_value");
					if (empty($nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber]) && !empty($defaultValue)) {
						$nameValues[$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber] = $thisColumn->getControlValue("default_value");
					}
					if (array_key_exists($controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber,$nameValues)) {
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
				if ($this->iColumn->getControlValue("primary_key_field")) {
					$primaryId = getFieldFromId($this->iColumn->getControlValue("primary_key_field"),$primaryTable->getName(),$primaryTable->getPrimaryKey(),$nameValues['primary_id']);
				} else {
					$primaryId = $nameValues['primary_id'];
				}
				$saveDataArray[$foreignKey] = $primaryId;
				$beforeSaveRecordFunction = $this->iColumn->getControlValue("before_save_record");
				if (!empty($beforeSaveRecordFunction) && method_exists($this->iPageObject,$beforeSaveRecordFunction)) {
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
				if (!$subtablePrimaryId = $listDataSource->saveRecord(array("name_values"=>$saveDataArray,"primary_id"=>$fieldData,"no_change_log"=>$parameters['no_change_log']))) {
					$this->iErrorMessage = $listDataSource->getErrorMessage();
					return false;
				}
				$additionalColumns = $this->iColumn->getControlValue("additional_columns");
				if (is_array($additionalColumns)) {
					foreach ($additionalColumns as $thisColumnName => $additionalColumnInfo) {
						if (array_key_exists($controlName . "_" . $thisColumnName . "-" . $rowNumber,$nameValues)) {
							$tableName = $additionalColumnInfo['table_name'];
							$fieldName = $additionalColumnInfo['field_name'];
							if ($tableName && $fieldName) {
								$table = new DataTable($tableName);
								$subtableForeignId = getFieldFromId($additionalColumnInfo['foreign_key'],$primaryTable->getName(),$primaryTable->getPrimaryKey(),$nameValues['primary_id']);
								$subtableId = getFieldFromId($table->getPrimaryKey(),$tableName,$additionalColumnInfo['foreign_key'],$subtableForeignId,$additionalColumnInfo['extra_where']);
								$tableFields = array($fieldName=>$nameValues[$controlName . "_" . $thisColumnName . "-" . $rowNumber]);
								if (empty($subtableId) && is_array($additionalColumnInfo['default_values'])) {
									$tableFields[$additionalColumnInfo['foreign_key']] = $subtableForeignId;
									foreach ($additionalColumnInfo['default_values'] as $defaultValueFieldName => $defaultValue) {
										if ($defaultValue === "false") {
											$defaultValue = false;
										} else if ($defaultValue === "true") {
											$defaultValue = true;
										} else if (is_string($defaultValue) && startsWith($defaultValue,"return ")) {
											if (substr($defaultValue,-1) != ";") {
												$defaultValue .= ";";
											}
											$defaultValue = eval($defaultValue);
										}
										$tableFields[$defaultValueFieldName] = $defaultValue;
									}
								} else {
									$table->setPrimaryId($subtableId);
								}
								$table->saveRecord(array("name_values"=>$tableFields,"primary_id"=>$subtableId,"no_change_log"=>$parameters['no_change_log']));
							}
						}
					}
				}
				$customControlValues = $nameValues;
				$customControlValues['primary_id'] = $subtablePrimaryId;
				foreach ($columns as $columnName => $originalColumn) {
					$thisColumn = clone $originalColumn;
					$thisColumn->setControlValue("primary_table",$listTableName);
					$thisColumn->setControlValue("column_name",$controlName . "_" . $thisColumn->getControlValue('column_name') . "-" . $rowNumber);
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn,$this->iPageObject);
						if (!$customControl->saveData($customControlValues)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							break;
						}
					}
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
			$columnData = explode(",",$columnData);
		}
		foreach ($columnData as $columnName => $thisColumnData) {
			if (!is_array($thisColumnData)) {
				$columnName = $thisColumnData;
			}
			$thisColumn = new DataColumn($columnName);
			if (is_array($thisColumnData)) {
				foreach ($thisColumnData as $thisControlName => $thisControlValue) {
					$thisColumn->setControlValue($thisControlName,$thisControlValue);
				}
			}
			if (array_key_exists($columnName,$listTableControls)) {
				foreach ($listTableControls[$columnName] as $columnControlName => $columnControlValue) {
					$thisColumn->setControlValue($columnControlName,$columnControlValue);
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
						$thisArray[$fieldName] = array("data_value"=>$fieldData,"crc_value"=>getCrcValue($fieldData));
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
