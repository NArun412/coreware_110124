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

$GLOBALS['gPageCode'] = "REIMBURSABLEEXPENSEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	var $iDesignationId = "";

	function setup() {
		if (empty($_GET) && empty($_POST)) {
			$_COOKIE['designation_id'] = "";
		}
		$this->iDataSource->addColumnControl("amount_used","select_value","select sum(amount) from expense_uses where expense_id = expenses.expense_id");
		$this->iDataSource->addColumnControl("amount_used","form_label","Used");
		$this->iDataSource->addColumnControl("amount_used","data_type","decimal");
		$this->iDataSource->addColumnControl("amount_used","decimal_places","2");
		$this->iDataSource->addColumnControl("amount_remaining","select_value","(amount - (select sum(amount) from expense_uses where expense_id = expenses.expense_id))");
		$this->iDataSource->addColumnControl("amount_remaining","form_label","Remaining");
		$this->iDataSource->addColumnControl("amount_remaining","data_type","decimal");
		$this->iDataSource->addColumnControl("amount_remaining","decimal_places","2");
		if ($_GET['ajax'] != "true") {
			$this->iDesignationId = getFieldFromId("designation_id","designations","designation_id",$_GET['designation_id']);
			if (empty($this->iDesignationId)) {
				$this->iDesignationId = getFieldFromId("designation_id","designations","designation_id",$_POST['designation_id']);
			}
			if (empty($this->iDesignationId)) {
				$this->iDesignationId = getFieldFromId("designation_id","designations","designation_id",$_COOKIE['designation_id']);
			}
			setCoreCookie("designation_id",$this->iDesignationId,24);
			$_COOKIE['designation_id'] = $this->iDesignationId;
		}
		$this->iDesignationId = $_COOKIE['designation_id'];
		$this->iDataSource->addColumnControl("designation_id","default_value",$this->iDesignationId);
		$this->iDataSource->setFilterWhere("designation_id " . (empty($this->iDesignationId) ? "is null" : "= " . $this->iDesignationId));

		$noExpenses = (getFieldFromId("no_expenses","payroll_groups","payroll_group_id",getFieldFromId("payroll_group_id","designations","designation_id",$this->iDesignationId)) == 1);
		if ($noExpenses) {
			if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
			}
		}
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("designation_id","log_date","amount","expiration_date","amount_used","amount_remaining"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);
		}
	}

	function onLoadJavascript() {
		if (empty($this->iDesignationId)) {
?>
window.close();
setTimeout(function() {
	document.location = "/designationmaintenance.php";
},500);
<?php
			return;
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['designation_description'] = array("data_value"=>getFieldFromId("description","designations","designation_id",$returnArray['designation_id']['data_value']));
		$returnArray['created_by'] = array("data_value"=>getUserDisplayName($returnArray['user_id']['data_value']));
		$returnArray['date_used'] = array("data_value"=>date("m/d/Y"),"crc_value"=>getCrcValue(date("m/d/Y")));
		$returnArray['used_amount'] = array("data_value"=>"","crc_value"=>getCrcValue(""));
		$returnArray['used_notes'] = array("data_value"=>"","crc_value"=>getCrcValue(""));
		$uses = ob_start();
?>
<table class="grid-table">
<tr>
	<th>Date Used</th>
	<th>Amount</th>
	<th>Notes</th>
</tr>
<?php
		$resultSet = executeQuery("select * from expense_uses where expense_id = ? order by date_used",$returnArray['primary_id']['data_value']);
		if ($resultSet['row_count'] == 0) {
?>
<tr>
	<td colspan="3">No Uses</td>
</tr>
<?php
		}
		while ($row = getNextRow($resultSet)) {
?>
<tr>
	<td><?= date("m/d/Y",strtotime($row['date_used'])) ?></td>
	<td><?= number_format($row['amount'],2) ?></td>
	<td><?= $row['notes'] ?></td>
</tr>
<?php
		}
?>
</table>
<?php
		$returnArray['expense_uses'] = array("data_value"=>ob_get_clean());
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		if (!empty($nameValues['date_used']) && !empty($nameValues['used_amount'])) {
			$amountUsed = 0;
			$resultSet = executeQuery("select sum(amount) from expense_uses where expense_id = ?",$nameValues['primary_id']);
			if ($row = getNextRow($resultSet)) {
				if (!empty($row['sum(amount)'])) {
					$amountUsed = $row['sum(amount)'];
				}
			}
			$amountAvailable = $nameValues['amount'] - $amountUsed;
			if ($amountAvailable > 0) {
				$usedAmount = $nameValues['used_amount'];
				if ($usedAmount > $amountAvailable) {
					$usedAmount = $amountAvailable;
				}
				$notes = $_POST['used_notes'];
				$resultSet = executeQuery("insert into expense_uses (expense_id,date_used,amount," .
					"notes) values (?,?,?,?)",$nameValues['primary_id'],makeDateParameter($nameValues['date_used']),$usedAmount,$notes);
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("expenses");
$pageObject->displayPage();
