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

class CheckboxLinks extends CustomControl {

	function getControl() {
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$linksTableName = $this->iColumn->getControlValue("links_table");
		ob_start();
?>
<div class="custom-control checkbox-links-wrapper" id="_<?= $this->iColumn->getControlValue("column_name") ?>_checkbox_links_wrapper">
<?php
		$sortOrder = 0;
		$choiceList = false;
		if ($this->iColumn->getControlValue("get_choices") && $this->iPageObject) {
			$choiceFunction = $this->iColumn->getControlValue("get_choices");
			if (method_exists($this->iPageObject,$choiceFunction)) {
				$tempChoiceList = $this->iPageObject->$choiceFunction();
				$choiceList = array();
				foreach ($tempChoiceList as $thisKey => $thisChoice) {
					if (!is_array($thisChoice)) {
						$choiceList[$thisKey] = array("description"=>$thisChoice['description'] . (empty($thisChoice['inactive']) ? "" : " (Inactive)"),"inactive"=>(empty($thisChoice['inactive']) ? false : true));
					} else {
						$choiceList[$thisKey] = $thisChoice;
					}
				}
			} else if (function_exists($choiceFunction)) {
				$tempChoiceList = $choiceFunction();
				$choiceList = array();
				foreach ($tempChoiceList as $thisKey => $thisChoice) {
					if (!is_array($thisChoice)) {
						$choiceList[$thisKey] = array("description"=>$thisChoice['description'] . (empty($thisChoice['inactive']) ? "" : " (Inactive)"),"inactive"=>(empty($thisChoice['inactive']) ? false : true));
					} else {
						$choiceList[$thisKey] = $thisChoice;
					}
				}
			}
		} else if ($this->iColumn->getControlValue("choices")) {
			$choiceList = $this->iColumn->getControlValue("choices");
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
				$choiceWhere = $this->iColumn->getControlValue("choice_where");
				if (!empty($choiceWhere)) {
					$queryWhere .= (empty($queryWhere) ? "" : " and ") . $choiceWhere;
				}
				$descriptionField = ($this->iColumn->getControlValue("control_description_field") ? $this->iColumn->getControlValue("control_description_field") : "description");
				$query = "select " . $controlKey . "," . $descriptionField . ($controlTable->columnExists("inactive") ? ",inactive" : "") . " from " . $controlTableName .
					(empty($queryWhere) ? "" : " where " . $queryWhere) . " order by " . ($controlTable->columnExists("sort_order") ? "sort_order," : "") . $descriptionField;
			}
			$resultSet = executeQuery($query);
			while ($row = getNextRow($resultSet)) {
				$fieldNames = array_keys($row);
				$choiceList[$row[$fieldNames[0]]] = array("key_value"=>$row[$fieldNames[0]],"description"=>$row[$fieldNames[1]] . (empty($row['inactive']) ? "" : " (Inactive)"),
					"inactive"=>(empty($row['inactive']) || !empty($this->iColumn->getControlValue("include_inactive")) ? false : true));
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
		foreach ($choiceList as $keyValue => $choiceValues) {
			if (!empty($choiceValues['inactive'])) {
				continue;
			}
			if (!is_array($choiceValues)) {
				$choiceValues = array("description"=>$choiceValues);
			}
			$description = $choiceValues['description'];
			$choiceName = "_" . $this->iColumn->getControlValue("column_name") . "_choice_" . $keyValue;
?>
	<div class='checkbox-link-choice-wrapper'><input class='checkbox-link-choice' type="checkbox" name="<?= $choiceName ?>" id="<?= $choiceName ?>" value="<?= $keyValue ?>" data-id="<?= $keyValue ?>"><label class='checkbox-label' for="<?= $choiceName ?>"><?= htmlText($description) ?></label></div>
<?php
		}
?>
	<input type="hidden" class="checkbox-link-choice-list" name="<?= $this->iColumn->getControlValue("column_name") ?>" id="<?= $this->iColumn->getControlValue("column_name") ?>" value="<?= implode(",",$selectedValues) ?>" />
	<input type="hidden" class="checkbox-link-delete-list" name="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" id="_delete_<?= $this->iColumn->getControlValue("column_name") ?>" value="" />
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
		$useSequenceNumber = $GLOBALS['gPrimaryDatabase']->fieldExists($linksTableName,"sequence_number");
		if (!empty($primaryId)) {
			$resultSet = executeQuery("select * from " . $linksTableName . " where " . $primaryKey .
				" = ?" . ($useSequenceNumber ? " order by sequence_number" : ""),$primaryId);
			while ($row = getNextRow($resultSet)) {
				if (!empty($returnIds)) {
					$returnIds .= ",";
				}
				$returnIds .= $row[$controlKey];
			}
			freeResult($resultSet);
		}
		return array($controlName=>array("data_value"=>$returnIds,"crc_value"=>getCrcValue($returnIds)));
	}

	function setPrimaryId($primaryId) {
		$this->iPrimaryId = $primaryId;
	}

	function saveData($nameValues,$parameters=array()) {
		if (empty($nameValues['primary_id'])) {
			$nameValues['primary_id'] = $this->iPrimaryId;
		}
		$controlName = $this->iColumn->getControlValue("column_name");
		$primaryTableName = $this->iColumn->getControlValue("primary_table");
		$linksTableName = $this->iColumn->getControlValue("links_table");
		$controlTableName = $this->iColumn->getControlValue("control_table");

		if (empty($linksTableName)) {
			return jsonEncode(explode(",",$nameValues[$controlName]));
		}

		$primaryTable = new DataTable($primaryTableName);
		$primaryKey = $primaryTable->getPrimaryKey();
		$controlTable = new DataTable($controlTableName);
		if ($this->iColumn->getControlValue("control_key")) {
			$controlKey = $this->iColumn->getControlValue("control_key");
		} else {
			$controlKey = $controlTable->getPrimaryKey();
		}

		$deleteIdArray = explode(",",$nameValues["_delete_" . $controlName]);
		$deleteIds = "";
		foreach ($deleteIdArray as $deleteId) {
			if (is_numeric($deleteId) && !empty($deleteId)) {
				if (!empty($deleteIds)) {
					$deleteIds .= ",";
				}
				$deleteIds .= $deleteId;
			}
		}

		if (!empty($deleteIds)) {
			$resultSet = executeQuery("delete from " . $linksTableName . " where " . $primaryKey . " = ? and " . $controlKey . " in (" . $deleteIds . ")",$nameValues['primary_id']);
			if (!empty($resultSet['sql_error'])) {
				$this->iErrorMessage = getSystemMessage("basic",$resultSet['sql_error']);
				return false;
			}
			if ($linksTableName == "distributor_product_codes") {
				$GLOBALS['gPrimaryDatabase']->logError("deleting from distributor_product_codes in checkboxlinks");
			}
		}

		$childIdArray = explode(",",$nameValues[$controlName]);
		$childIds = "";
		foreach ($childIdArray as $childId) {
			if (is_numeric($childId) && !empty($childId)) {
				if (!empty($childIds)) {
					$childIds .= ",";
				}
				$childIds .= $childId;
			}
		}
		foreach (explode(",",$childIds) as $childId) {
			if (empty($childId)) {
				continue;
			}
			$resultSet = $this->iColumn->getDatabase()->executeQuery("select " . $primaryKey . " from " . $linksTableName . " where " . $primaryKey . " = ? and " . $controlKey . " = ?",$nameValues['primary_id'],$childId);
			if (!$row = getNextRow($resultSet)) {
				$insertSet = $this->iColumn->getDatabase()->executeQuery("insert into " . $linksTableName . " (" . $primaryKey . "," . $controlKey . ") values (?,?)",$nameValues['primary_id'],$childId);
				if (!empty($insertSet['sql_error'])) {
					$this->iErrorMessage = getSystemMessage("basic",$insertSet['sql_error']);
					return false;
				}
			}
		}
		return true;
	}

	function getCustomDataArray($simpleArray) {
		return array("data_value"=>implode(",",$simpleArray),"crc_value"=>getCrcValue(implode(",",$simpleArray)));
	}
}

?>
