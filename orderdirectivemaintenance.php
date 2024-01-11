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

$GLOBALS['gPageCode'] = "ORDERDIRECTIVEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	var $iConditions = array();
	var $iActions = array();

	function setup() {
		$this->iConditions = Order::getOrderDirectiveConditions();
		$this->iActions = Order::getOrderDirectiveActions();
	}

    function massageDataSource() {
        $this->iDataSource->getPrimaryTable()->setSubtables(array("order_directive_actions","order_directive_conditions"));
    }

    function afterGetRecord(&$returnArray) {
		ob_start();
		foreach ($this->iConditions as $thisCondition) {
			$dataValue = getFieldFromId("condition_data","order_directive_conditions","order_directive_id",$returnArray['primary_id']['data_value'],"condition_code = ?",$thisCondition['condition_code']);
?>
<div class="basic-form-line directive-condition" id="_row_condition_data_<?= strtolower($thisCondition['condition_code']) ?>">
<?php if ($thisCondition['data_type'] != "tinyint") { ?>
	<label><?= htmlText($thisCondition['description']) ?></label>
<?php } ?>
<?php if (!empty($thisCondition['help_label'])) { ?>
	<span class='help-label'><?= htmlText($thisCondition['help_label']) ?></span>
<?php } ?>
<?php
			switch ($thisCondition['data_type']) {
				case "select":
?>
	<select id="condition_data_<?= strtolower($thisCondition['condition_code']) ?>" name="condition_data_<?= strtolower($thisCondition['condition_code']) ?>">
		<option value="">[Ignore]</option>
<?php
					$dataTable = new DataTable($thisCondition['select_table']);
					$primaryKey = $dataTable->getPrimaryKey();
					$resultSet = executeQuery("select * from " . $thisCondition['select_table'] . " where inactive = 0 and client_id = ? order by sort_order,description",$GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
?>
		<option <?= ($dataValue == $row[$primaryKey] ? "selected " : "") ?>value="<?= $row[$primaryKey] ?>"><?= htmlText($row['description']) ?></option>
<?php
					}
?>
	</select>
<?php
					break;
				case "int":
					echo "<input type='text' value='" . $dataValue . "' size='10' class='align-right validate[custom[integer],min[1]]' id='condition_data_" . strtolower($thisCondition['condition_code']) . "' name='condition_data_" . strtolower($thisCondition['condition_code']) . "'>";
					break;
				case "decimal":
					echo "<input type='text' value='" . $dataValue . "' size='12' class='align-right validate[custom[number],min[.01]]' data-decimal-places='2' id='condition_data_" . strtolower($thisCondition['condition_code']) . "' name='condition_data_" . strtolower($thisCondition['condition_code']) . "'>";
					break;
				case "tinyint":
					echo "<input " . (!empty($dataValue) ? "checked " : "") . "type='checkbox' value='1' id='condition_data_" . strtolower($thisCondition['condition_code']) . "' name='condition_data_" . strtolower($thisCondition['condition_code']) . "'><label class='checkbox-label' for='condition_data_" . strtolower($thisCondition['condition_code']) . "'>" . htmlText($thisCondition['description']) . "</label>";
					break;
				case "varchar":
					echo "<input type='text' value='" . $dataValue . "' size='40' id='condition_data_" . strtolower($thisCondition['condition_code']) . "' name='condition_data_" . strtolower($thisCondition['condition_code']) . "'>";
					break;
			}
?>
    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
</div>
<?php
		}
		$returnArray['directive_conditions'] = array("data_value"=>ob_get_clean());

		ob_start();
		foreach ($this->iActions as $thisAction) {
			$dataValue = getFieldFromId("action_data","order_directive_actions","order_directive_id",$returnArray['primary_id']['data_value'],"action_code = ?",$thisAction['action_code']);
?>
<div class="basic-form-line directive-action" id="_row_action_data_<?= strtolower($thisAction['action_code']) ?>">
<?php if ($thisAction['data_type'] != "tinyint") { ?>
	<label><?= htmlText($thisAction['description']) ?></label>
<?php } ?>
<?php if (!empty($thisAction['help_label'])) { ?>
	<span class='help-label'><?= htmlText($thisAction['help_label']) ?></span>
<?php } ?>
<?php
			switch ($thisAction['data_type']) {
				case "select":
?>
	<select id="action_data_<?= strtolower($thisAction['action_code']) ?>" name="action_data_<?= strtolower($thisAction['action_code']) ?>">
		<option value="">[Ignore]</option>
<?php
					$dataTable = new DataTable($thisAction['select_table']);
					$primaryKey = $dataTable->getPrimaryKey();
					$resultSet = executeQuery("select * from " . $thisAction['select_table'] . " where inactive = 0 and client_id = ? order by sort_order,description",$GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
?>
		<option <?= ($dataValue == $row[$primaryKey] ? "selected " : "") ?>value="<?= $row[$primaryKey] ?>"><?= htmlText($row['description']) ?></option>
<?php
					}
?>
	</select>
<?php
					break;
				case "int":
					echo "<input type='text' value='" . $dataValue . "' size='10' class='align-right validate[custom[integer],min[1]]' id='action_data_" . strtolower($thisAction['action_code']) . "' name='action_data_" . strtolower($thisAction['action_code']) . "'>";
					break;
				case "decimal":
					echo "<input type='text' value='" . $dataValue . "' size='12' class='align-right validate[custom[number],min[.01]]' data-decimal-places='2' id='action_data_" . strtolower($thisAction['action_code']) . "' name='action_data_" . strtolower($thisAction['action_code']) . "'>";
					break;
				case "tinyint":
					echo "<input " . (!empty($dataValue) ? "checked " : "") . "type='checkbox' value='1' id='action_data_" . strtolower($thisAction['action_code']) . "' name='action_data_" . strtolower($thisAction['action_code']) . "'><label class='checkbox-label' for='action_data_" . strtolower($thisAction['action_code']) . "'>" . htmlText($thisAction['description']) . "</label>";
					break;
				case "varchar":
					echo "<input type='text' value='" . $dataValue . "' class='validate[" . (empty($thisAction['data_format']) ? "" : "custom[" . $thisAction['data_format'] . "]") . "]' size='40' id='action_data_" . strtolower($thisAction['action_code']) . "' name='action_data_" . strtolower($thisAction['action_code']) . "'>";
					break;
			}
?>
    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
</div>
<?php
		}
		$returnArray['directive_actions'] = array("data_value"=>ob_get_clean());
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		executeQuery("delete from order_directive_conditions where order_directive_id = ?",$nameValues['primary_id']);
		executeQuery("delete from order_directive_actions where order_directive_id = ?",$nameValues['primary_id']);
		foreach ($this->iConditions as $thisCondition) {
			if (!empty($nameValues['condition_data_' . strtolower($thisCondition['condition_code'])])) {
			    $dataTable = new DataTable("order_directive_conditions");
			    $dataTable->saveRecord(array("name_values"=>array("order_directive_id"=>$nameValues['primary_id'],"condition_code"=>$thisCondition['condition_code'],"condition_data"=>$nameValues['condition_data_' . strtolower($thisCondition['condition_code'])])));
			}
		}
		foreach ($this->iActions as $thisAction) {
			if (!empty($nameValues['action_data_' . strtolower($thisAction['action_code'])])) {
				$dataTable = new DataTable("order_directive_actions");
				$dataTable->saveRecord(array("name_values"=>array("order_directive_id"=>$nameValues['primary_id'],"action_code"=>$thisAction['action_code'],"action_data"=>$nameValues['action_data_' . strtolower($thisAction['action_code'])])));
			}
		}
		return true;
	}

	function internalCSS() {
?>
<style>
.directive-condition,.directive-action { padding: 10px 0 20px 0; border-bottom: 1px solid rgb(240,240,240); }
</style>
<?php
	}
}

$pageObject = new ThisPage("order_directives");
$pageObject->displayPage();
