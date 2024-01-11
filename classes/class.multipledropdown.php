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

class MultipleDropdown extends CustomControl {

	function getControl() {
		ob_start();
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
		$choiceList = false;
		if ($this->iColumn->getControlValue("get_choices") && $this->iPageObject) {
			$choiceFunction = $this->iColumn->getControlValue("get_choices");
			if (method_exists($this->iPageObject, $choiceFunction)) {
				$tempChoiceList = $this->iPageObject->$choiceFunction();
				$choiceList = array();
				foreach ($tempChoiceList as $thisKey => $thisChoice) {
					if (!is_array($thisChoice)) {
						$choiceList[$thisKey] = array("description" => $thisChoice['description'] . (empty($thisChoice) ? "" : " (Inactive)"), "inactive" => (empty($thisChoice) ? false : true));
					} else {
						$choiceList[$thisKey] = $thisChoice;
					}
				}
			} else {
				if (function_exists($choiceFunction)) {
					$tempChoiceList = $choiceFunction();
					$choiceList = array();
					foreach ($tempChoiceList as $thisKey => $thisChoice) {
						if (!is_array($thisChoice)) {
							$choiceList[$thisKey] = array("description" => $thisChoice['description'] . (empty($thisChoice) ? "" : " (Inactive)"), "inactive" => (empty($thisChoice) ? false : true));
						} else {
							$choiceList[$thisKey] = $thisChoice;
						}
					}
				}
			}
		} else {
			if ($this->iColumn->getControlValue("choices")) {
				$choiceList = $this->iColumn->getControlValue("choices");
			}
		}
		if ($choiceList === false) {
			$controlTableName = $this->iColumn->getControlValue("control_table");
			$controlTable = new DataTable($controlTableName);
			$controlKey = $controlTable->getPrimaryKey();
			$choiceList = array();
			$query = $this->iColumn->getControlValue("choice_query");
			if (empty($query)) {
				$queryWhere = "";
				if ($controlTable->getName() != "clients" && $controlTable->columnExists("client_id")) {
					$queryWhere = "client_id = " . $GLOBALS['gClientId'];
				}
				$descriptionField = ($this->iColumn->getControlValue("control_description_field") ? $this->iColumn->getControlValue("control_description_field") : "description");
				$query = "select " . $controlKey . "," . $descriptionField . ($controlTable->columnExists("inactive") ? ",inactive" : "") . " from " . $controlTableName .
					(empty($queryWhere) ? "" : " where " . $queryWhere) . " order by " . ($controlTable->columnExists("sort_order") ? "sort_order," : "") . $descriptionField;
			}
			$resultSet = executeQuery($query);
			while ($row = getNextRow($resultSet)) {
				$fieldNames = array_keys($row);
				$choiceList[$row[$fieldNames[0]]] = array("key_value" => $row[$fieldNames[0]], "description" => $row[$fieldNames[1]] . (empty($row['inactive']) ? "" : " (Inactive)"), "inactive" => (empty($row['inactive']) ? false : true));
			}
			freeResult($resultSet);
		}
		?>
        <div id="_<?= $this->iColumn->getControlValue("column_name") ?>_selector" class="custom-control multiple-dropdown-container">
            <input class="multiple-dropdown-values" type="hidden" id="<?= $this->iColumn->getControlValue("column_name") ?>" name="<?= $this->iColumn->getControlValue("column_name") ?>" value="">
			<?php
			foreach ($selectedValues as $selectedValue) {
				if (array_key_exists($selectedValue, $choiceList)) {
					?>
                    <div class='multiple-dropdown-selected-value<?= ($choiceList[$selectedValue]['inactive'] ? " inactive-option" : "") ?>' data-value_id="<?= $selectedValue ?>"><?= htmlText($choiceList[$selectedValue]['description']) ?></div>
					<?php
				}
			}
			?>
            <input tabindex="10" type='text' class='multiple-dropdown-filter'>
            <div class="multiple-dropdown-options">
                <ul>
					<?php
					$saveGroup = "";
					foreach ($choiceList as $keyValue => $choiceValues) {
						if (!is_array($choiceValues)) {
							$choiceValues = array("description" => $choiceValues);
						}
						$optGroup = $choiceValues['optgroup'];
						if (!empty($optGroup) && $optGroup != $saveGroup) {
							?>
                            <li class="multiple-dropdown-group"><?= htmlText($optGroup) ?></li>
							<?php
							$saveGroup = $optGroup;
						}
						$description = $choiceValues['description'];
						?>
                        <li class="<?= ($choiceValues['inactive'] ? "inactive-option " : "") ?>multiple-dropdown-option<?= (in_array($keyValue, $selectedValues) ? " multiple-dropdown-disabled" : "") ?>" data-value_id="<?= $keyValue ?>"><?= htmlText($description) ?></li>
						<?php
					}
					?>
                </ul>
            </div>
        </div>
		<?php
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
		if (!empty($primaryId) && !empty($linksTableName)) {
			$resultSet = executeQuery("select * from " . $linksTableName . " where " . $primaryKey . " = ?", $primaryId);
			while ($row = getNextRow($resultSet)) {
				if (!empty($returnIds)) {
					$returnIds .= ",";
				}
				$returnIds .= $row[$controlKey];
			}
			freeResult($resultSet);
		}
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

		$linksTable = new DataTable($linksTableName);
		$linksPrimaryKey = $linksTable->getPrimaryKey();


		$resultSet = executeQuery("select " . $linksPrimaryKey . " from " . $linksTableName . " where " . $primaryKey . " = ?" . (empty($childIds) ? "" : " and " . $controlKey . " not in ($childIds)"), $nameValues['primary_id']);
		while ($row = getNextRow($resultSet)) {
			if (!$linksTable->deleteRecord(array("primary_id" => $row[$linksPrimaryKey]))) {
				$this->iErrorMessage = $linksTable->getErrorMessage();
				return false;
			}
		}
		foreach (explode(",", $childIds) as $childId) {
			if (empty($childId)) {
				continue;
			}
			$resultSet = $this->iColumn->getDatabase()->executeQuery("select " . $primaryKey . " from " . $linksTableName . " where " . $primaryKey . " = ? and " . $controlKey . " = ?", $nameValues['primary_id'], $childId);
			if ($resultSet['row_count'] == 0) {
				$result = $linksTable->saveRecord(array("name_values" => array($primaryKey => $nameValues['primary_id'], $controlKey => $childId)));
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
