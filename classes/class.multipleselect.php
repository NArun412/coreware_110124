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

class MultipleSelect extends CustomControl {

	function getControl() {
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$linksTableName = $this->iColumn->getControlValue("links_table");
		$controlTableName = $this->iColumn->getControlValue("control_table");
		if (!empty($this->iColumn->getControlValue('get_choices')) && !method_exists($GLOBALS['gPageObject'], "customGetControlRecords")) {
			$addNewInfo = array();
		} else {
			$addNewInfo = $GLOBALS['gPrimaryDatabase']->getAddNewInfo($controlTableName);
		}
		$addNewOption = (!empty($addNewInfo) && !empty($addNewInfo['table_name']));
		$userSetsOrder = ($this->iColumn->controlValueExists("user_sets_order") && $this->iColumn->getControlValue("user_sets_order")) || (!empty($linksTableName) && $GLOBALS['gPrimaryDatabase']->fieldExists($linksTableName, "sequence_number"));
		$sortOrder = 0;

		$choiceList = false;
		if ($this->iColumn->getControlValue("get_choices") && $this->iPageObject) {
			$choiceFunction = $this->iColumn->getControlValue("get_choices");
			if (method_exists($this->iPageObject, $choiceFunction)) {
				$tempChoiceList = $this->iPageObject->$choiceFunction();
				$choiceList = array();
				foreach ($tempChoiceList as $thisKey => $thisChoice) {
					if (!is_array($thisChoice)) {
						$choiceList[$thisKey] = array("description" => $thisChoice['description'] . (empty($thisChoice['inactive']) ? "" : " (Inactive)"), "inactive" => (empty($thisChoice['inactive']) ? false : true));
					} else {
						$choiceList[$thisKey] = $thisChoice;
					}
				}
			} else if (function_exists($choiceFunction)) {
				$tempChoiceList = $choiceFunction();
				$choiceList = array();
				foreach ($tempChoiceList as $thisKey => $thisChoice) {
					if (!is_array($thisChoice)) {
						$choiceList[$thisKey] = array("description" => $thisChoice['description'] . (empty($thisChoice['inactive']) ? "" : " (Inactive)"), "inactive" => (empty($thisChoice['inactive']) ? false : true));
					} else {
						$choiceList[$thisKey] = $thisChoice;
					}
				}
			}
		} else if ($this->iColumn->getControlValue("choices")) {
			$choiceList = $this->iColumn->getControlValue("choices");
		}
		if ($choiceList === false && !empty($controlTableName)) {
			$controlTable = new DataTable($controlTableName);
			$controlKey = $controlTable->getPrimaryKey();
			$choiceList = array();
			$controlCodeField = "";
			$query = $this->iColumn->getControlValue("choice_query");
			if (empty($query)) {
				$queryWhere = "";
				if ($controlTable->getName() != "clients" && $controlTable->columnExists("client_id")) {
					$queryWhere = "client_id = " . $GLOBALS['gClientId'];
				}
				$choiceWhere = $this->iColumn->getControlValue("choice_where");
				if (!empty($choiceWhere)) {
					$queryWhere .= (empty($queryWhere) ? "" : " and ") . $choiceWhere;
				}
				$descriptionField = ($this->iColumn->getControlValue("control_description_field") ? $this->iColumn->getControlValue("control_description_field") : "description");
				$controlCodeField = ($this->iColumn->getControlValue("control_code_field") ? $this->iColumn->getControlValue("control_code_field") : "");
				$query = "select " . $controlKey . "," . $descriptionField . (empty($controlCodeField) ? "" : "," . $controlCodeField) . ($controlTable->columnExists("inactive") ? ",inactive" : "") . " from " . $controlTableName .
					(empty($queryWhere) ? "" : " where " . $queryWhere) . " order by " . ($controlTable->columnExists("sort_order") ? "sort_order," : "") . $descriptionField;
			}
			$resultSet = executeQuery($query);
			while ($row = getNextRow($resultSet)) {
				$fieldNames = array_keys($row);
				$choiceList[$row[$fieldNames[0]]] = array("key_value" => $row[$fieldNames[0]], "original_description" => $row[$fieldNames[1]], "description" => $row[$fieldNames[1]] . (empty($controlCodeField) ? "" : " - " . $row[$controlCodeField]) . (empty($row['inactive']) ? "" : " (Inactive)"),
					"inactive" => (empty($row['inactive']) || !empty($this->iColumn->getControlValue("include_inactive")) ? false : true));
			}
			freeResult($resultSet);
		}
		$selectedValues = $this->iColumn->getControlValue("selected_values");
		if (empty($selectedValues)) {
			$selectedValues = array();
			$query = $this->iColumn->getControlValue("selected_values_query");
			if (!empty($query)) {
				$resultSet = executeQuery($query);
				while ($row = getNextRow($resultSet)) {
					$fieldNames = array_keys($row);
					$selectedValues[] = $row[$fieldNames[0]];
				}
				freeResult($resultSet);
			}
		}
        $readonly = $this->iColumn->getControlValue("readonly");
		ob_start();
		if ($this->iColumn->controlValueExists("button_selectors") && $this->iColumn->getControlValue("button_selectors")) {
			?>
            <div class='custom-control selection-control-button-wrapper' id="_<?= $this->iColumn->getControlValue("column_name") ?>_selector" data-user_order="<?= ($userSetsOrder ? "yes" : "") ?>">
				<?php
				foreach ($choiceList as $keyValue => $choiceValues) {
					if (!is_array($choiceValues)) {
						$choiceValues = array("description" => $choiceValues);
					}
					$description = $choiceValues['description'];
					$sortOrder++;
					if (!array_key_exists($keyValue, $selectedValues)) {
						?>
                        <div class="selection-control-button-choice <?= ($choiceValues['inactive'] ? "inactive-option" : "") ?>" data-id="<?= $keyValue ?>"><p><?= htmlText($description) ?></p></div>
						<?php
					}
				}
				?>
                <input type="hidden" class="selector-value-list" name="<?= $this->iColumn->getControlValue("column_name") ?>" id="<?= $this->iColumn->getControlValue("column_name") ?>" value="<?= implode(",", $selectedValues) ?>"/>
                <input type="hidden" class="selector-value-delete-list" name="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" id="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" value=""/>
            </div>
			<?php
		} else {
			if (count($choiceList) <= 18 && !$userSetsOrder) {
				?>
                <div class='custom-control multiple-select-checkbox-wrapper' id="_<?= $this->iColumn->getControlValue("column_name") ?>_wrapper">
					<?php
					foreach ($choiceList as $keyValue => $thisChoice) {
						if (!is_array($thisChoice)) {
							$thisChoice = array("description" => $thisChoice);
						}
						$description = $thisChoice['original_description'];
                        if (empty($description)) {
	                        $description = $thisChoice['description'];
                        }
                        if (!empty($thisChoice['key_value'])) {
	                        $keyValue = $thisChoice['key_value'];
                        }
						if ($thisChoice['inactive']) {
							continue;
						}
						?>
                        <div class='multiple-select-checkbox-option'>
                            <input type='checkbox'<?= ($readonly ? " disabled='disabled'" : "") ?> tabindex='10' data-id='<?= $keyValue ?>' name='<?= $this->iColumn->getControlValue("column_name") . "-" . $keyValue ?>' id='<?= $this->iColumn->getControlValue("column_name") . "-" . $keyValue ?>' value='<?= $keyValue ?>'><label class='checkbox-label' for='<?= $this->iColumn->getControlValue("column_name") . "-" . $keyValue ?>'><?= htmlText($description) ?></label>
                        </div>
						<?php
					}
					?>
                    <input type="hidden" class="selector-value-list" name="<?= $this->iColumn->getControlValue("column_name") ?>" id="<?= $this->iColumn->getControlValue("column_name") ?>" value="<?= implode(",", $selectedValues) ?>"/>
                    <input type="hidden" class="selector-value-delete-list" name="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" id="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" value=""/>
                </div>
				<?php
			} else {
				?>
                <table <?= ($addNewOption ? "data-link_url='" . $addNewInfo['link_url'] . "' data-control_code='" . $addNewInfo['table_name'] . "' " : "") ?>class="custom-control selection-control" id="_<?= $this->iColumn->getControlValue("column_name") ?>_selector" data-user_order="<?= ($userSetsOrder ? "yes" : "") ?>" data-connector="<?= $this->iColumn->getControlValue("column_name") ?>-connector">
                    <tr>
                        <td>
                            <input type="text" class="selection-control-filter" tabindex="10" data-field_name="<?= $this->iColumn->getControlValue("column_name") ?>" placeholder="Filter Choices"/>
                            <div class="selection-choices-div">
                                <ul class="<?= $this->iColumn->getControlValue("column_name") ?>-connector">
									<?php
									foreach ($choiceList as $keyValue => $choiceValues) {
										if (!is_array($choiceValues)) {
											$choiceValues = array("description" => $choiceValues);
										}
										$description = $choiceValues['description'];
										$sortOrder++;
										if (!array_key_exists($keyValue, $selectedValues)) {
											?>
                                            <li class="<?= ($choiceValues['inactive'] ? "inactive-option" : "") ?>" data-sort_order="<?= $sortOrder ?>" data-inactive="<?= ($choiceValues['inactive'] ? "1" : "") ?>" data-id="<?= $keyValue ?>"><?= htmlText($description) ?></li>
											<?php
										}
									}
									?>
                                </ul>
                            </div>
                        </td>
                        <td class="selection-controls">
							<?php if ($addNewOption) { ?>
                                <p>
                                    <button class='add-new-multiple-select' title="Add New Option"><span class="fad fa-plus"></span></button>
                                </p>
							<?php } ?>
                            <p>
                                <button class='select-all-multiple-select' title="Select all"><span class="fad fa-check"></span></button>
                            </p>
                            <p>
                                <button class='remove-all-multiple-select' title="Remove all"><span class="fad fa-times"></span></button>
                            </p>
							<?php if ($userSetsOrder) { ?>
                                <p>
                                    <button class='sort-multiple-select' title="Sort"><span class="fad fa-sort-alpha-up"></span></button>
                                </p>
							<?php } ?>
                        </td>
                        <td>
                            <div class="selection-chosen-div">
                                <ul class="<?= $this->iColumn->getControlValue("column_name") ?>-connector">
									<?php
									$sortOrder = 0;
									foreach ($selectedValues as $keyValue) {
										$sortOrder++;
										if (array_key_exists($keyValue, $choiceList)) {
											?>
                                            <li class="<?= ($choiceList[$keyValue]['inactive'] ? "inactive-option" : "") ?>" data-sort_order="<?= $sortOrder ?>" data-id="<?= $keyValue ?>"><?= htmlText($choiceList[$keyValue]['description']) ?></li>
											<?php
										}
									}
									?>
                                </ul>
                            </div>
                            <input type="hidden" class="selector-value-list" name="<?= $this->iColumn->getControlValue("column_name") ?>" id="<?= $this->iColumn->getControlValue("column_name") ?>" value="<?= implode(",", $selectedValues) ?>"/>
                            <input type="hidden" class="selector-value-delete-list" name="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" id="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" value=""/>
                        </td>
                    </tr>
                </table>
				<?php
			}
		}
		return ob_get_clean();
	}

	function getTemplate() {
	}

	function getRecord($primaryId = "") {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryId;
		}
		$returnIds = "";
		$controlName = $this->iColumn->getControlValue("column_name");
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$linksTableName = $this->iColumn->getControlValue("links_table");
		$controlTableName = $this->iColumn->getControlValue("control_table");
		$primaryTable = new DataTable($primaryTableName);
		$primaryKey = $primaryTable->getPrimaryKey();
		$controlTable = new DataTable($controlTableName);
		if ($this->iColumn->getControlValue("control_key")) {
			$controlKey = $this->iColumn->getControlValue("control_key");
		} else {
			$controlKey = $controlTable->getPrimaryKey();
		}
		$useSequenceNumber = $GLOBALS['gPrimaryDatabase']->fieldExists($linksTableName, "sequence_number");
		if (!empty($primaryId) && !empty($linksTableName)) {
			$resultSet = executeQuery("select * from " . $linksTableName . " where " . $primaryKey .
				" = ?" . ($useSequenceNumber ? " order by sequence_number" : ""), $primaryId);
			while ($row = getNextRow($resultSet)) {
				if (!empty($returnIds)) {
					$returnIds .= ",";
				}
				$returnIds .= $row[$controlKey];
			}
			freeResult($resultSet);
		}
        $returnIdsArray = explode(",",$returnIds);
		if (!empty($this->iColumn->getControlValue("get_links"))) {
			$getLinksFunction = $this->iColumn->getControlValue("get_links");
			if (method_exists($this->iPageObject, $getLinksFunction)) {
				$returnIds = $this->iPageObject->$getLinksFunction($primaryId);
			} else if (function_exists($getLinksFunction)) {
				$returnIds = $getLinksFunction($primaryId);
			}
		}
		return array($controlName => array("data_value" => $returnIds, "crc_value" => getCrcValue($returnIds)));
	}

	function setPrimaryId($primaryId) {
		$this->iPrimaryId = $primaryId;
	}

	function saveData($nameValues, $parameters = array()) {
		if (empty($nameValues['primary_id'])) {
			$nameValues['primary_id'] = $this->iPrimaryId;
		}
		$controlName = $this->iColumn->getControlValue("column_name");
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$linksTableName = $this->iColumn->getControlValue("links_table");
		$controlTableName = $this->iColumn->getControlValue("control_table");

		if (!empty($this->iColumn->getControlValue("save_data"))) {
			$saveFunction = $this->iColumn->getControlValue("save_data");
			if (method_exists($this->iPageObject, $saveFunction)) {
				return $this->iPageObject->$saveFunction($nameValues);
			} else if (function_exists($saveFunction)) {
				return $saveFunction($nameValues);
			}
		}
		if (empty($linksTableName)) {
			return jsonEncode(explode(",", $nameValues[$controlName]));
		}

		$primaryTable = new DataTable($primaryTableName);
		$primaryKey = $primaryTable->getPrimaryKey();
		$controlTable = new DataTable($controlTableName);
		if ($this->iColumn->getControlValue("control_key")) {
			$controlKey = $this->iColumn->getControlValue("control_key");
		} else {
			$controlKey = $controlTable->getPrimaryKey();
		}

		$useSequenceNumber = $GLOBALS['gPrimaryDatabase']->fieldExists($linksTableName, "sequence_number");

		$deleteIdArray = explode(",", $nameValues["_delete_" . $controlName]);
		$deleteIds = "";
		foreach ($deleteIdArray as $deleteId) {
			if (is_numeric($deleteId) && !empty($deleteId)) {
				if (!empty($deleteIds)) {
					$deleteIds .= ",";
				}
				$deleteIds .= $deleteId;
			}
		}

		$linksTable = new DataTable($linksTableName);
		$linksPrimaryKey = $linksTable->getPrimaryKey();

		if (!empty($deleteIds)) {
			$resultSet = executeQuery("select " . $linksPrimaryKey . " from " . $linksTableName . " where " . $primaryKey . " = ? and " . $controlKey . " in (" . $deleteIds . ")", $nameValues['primary_id']);
			while ($row = getNextRow($resultSet)) {
				if (!$linksTable->deleteRecord(array("primary_id" => $row[$linksPrimaryKey]))) {
					$this->iErrorMessage = $linksTable->getErrorMessage();
					return false;
				}
			}
		}

		$childIdArray = explode(",", $nameValues[$controlName]);
		$childIds = "";
		foreach ($childIdArray as $childId) {
			if (is_numeric($childId) && !empty($childId)) {
				if (!empty($childIds)) {
					$childIds .= ",";
				}
				$childIds .= $childId;
			}
		}
		$sequenceNumber = 0;
		foreach (explode(",", $childIds) as $childId) {
			if (empty($childId)) {
				continue;
			}
			$sequenceNumber++;
			$resultSet = $this->iColumn->getDatabase()->executeQuery("select " . $primaryKey . " from " . $linksTableName . " where " . $primaryKey . " = ? and " . $controlKey . " = ?", $nameValues['primary_id'], $childId);
			if ($row = getNextRow($resultSet)) {
				if ($useSequenceNumber) {
					$updateSet = $this->iColumn->getDatabase()->executeQuery("update " . $linksTableName . " set sequence_number = ? where " . $primaryKey . " = ? and " . $controlKey . " = ?", $sequenceNumber, $nameValues['primary_id'], $childId);
				}
			} else {
				if ($useSequenceNumber) {
					$result = $linksTable->saveRecord(array("name_values" => array($primaryKey => $nameValues['primary_id'], $controlKey => $childId, "sequence_number" => $sequenceNumber)));
				} else {
					$result = $linksTable->saveRecord(array("name_values" => array($primaryKey => $nameValues['primary_id'], $controlKey => $childId)));
				}
				if (!$result) {
					$this->iErrorMessage = $linksTable->getErrorMessage();
					return false;
				}
			}
		}
		return true;
	}

	function getCustomDataArray($simpleArray) {
		return array("data_value" => implode(",", $simpleArray), "crc_value" => getCrcValue(implode(",", $simpleArray)));
	}
}

?>
