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

$GLOBALS['gPageCode'] = "CREDITACCOUNTLOG";
require_once "shared/startup.inc";

class CreditAccountLogPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("first_name", "last_name", "business_name", "account_label", "credit_limit", "inactive"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("first_name", "last_name", "business_name", "account_label", "credit_limit", "inactive"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);
			if (!$GLOBALS['gUserRow']['full_client_access']) {
				$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("first_name", "not_editable", true);
		$this->iDataSource->addColumnControl("last_name", "not_editable", true);
		$this->iDataSource->addColumnControl("business_name", "not_editable", true);
		$this->iDataSource->addColumnControl("credit_limit", "not_editable", true);

		$this->iDataSource->addColumnControl("description", "not_null", true);
		$this->iDataSource->addColumnControl("description", "data_type", "varchar");
		$this->iDataSource->addColumnControl("description", "form_label", "Log Entry Description");
		$this->iDataSource->addColumnControl("entry_type", "not_null", true);
		$this->iDataSource->addColumnControl("entry_type", "data_type", "select");
		$this->iDataSource->addColumnControl("entry_type", "choices", array("reduce" => "Expense (reduce available credit)", "increase" => "Income (increase available credit)"));
		$this->iDataSource->addColumnControl("entry_type", "form_label", "Entry Type");
		$this->iDataSource->addColumnControl("amount", "not_null", true);
		$this->iDataSource->addColumnControl("amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("amount", "form_label", "Amount");
		$this->iDataSource->addColumnControl("amount", "minimum_amount", .01);
		$this->iDataSource->addColumnControl("log_notes", "data_type", "text");
		$this->iDataSource->addColumnControl("log_notes", "form_label", "Notes");

		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			$this->iDataSource->addColumnControl("credit_account_designations", "data_type", "custom");
			$this->iDataSource->addColumnControl("credit_account_designations", "control_class", "EditableList");
			$this->iDataSource->addColumnControl("credit_account_designations", "list_table", "credit_account_designations");
			$this->iDataSource->addColumnControl("credit_account_designations", "form_label", "Income Donation Designations");

			$this->iDataSource->setFilterWhere("contacts.client_id = " . $GLOBALS['gClientId'] . " and payment_method_id in (select payment_method_id from payment_methods where " .
				"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_ACCOUNT'))");
		} else {
			$this->iDataSource->setFilterWhere("accounts.contact_id = " . $GLOBALS['gUserRow']['contact_id'] . " and payment_method_id in (select payment_method_id from payment_methods where " .
				"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_ACCOUNT'))");
		}
	}

	function massageUrlParameters() {
		if (!$GLOBALS['gUserRow']['full_client_access']) {
			$resultSet = executeQuery("select account_id from accounts where contact_id = ? and inactive = 0 and payment_method_id in (select payment_method_id from payment_methods where " .
				"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_ACCOUNT'))", $GLOBALS['gUserRow']['contact_id']);
			if ($resultSet['row_count'] == 1) {
				if ($row = getNextRow($resultSet)) {
					$_GET['url_subpage'] = $_GET['url_page'];
					$_GET['url_page'] = "show";
					$_GET['primary_id'] = $row['account_id'];
				}
			}
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            <?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("click", "#add_log_entry", function () {
                if ($(this).prop("checked")) {
                    $("#log_entry").removeClass("hidden");
                    $("#description").focus();
                } else {
                    $("#log_entry").addClass("hidden");
                }
            });
            <?php } ?>
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#add_log_entry").prop("checked",false);
                $("#log_entry").addClass("hidden");
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$logContent = "<table class='grid-table header_sortable'><tr><th>Log Time</th><th>User</th><th>Description</th><th>Amount</th><th>Notes</th></tr>";
		$logEntries = array();
		$resultSet = executeQuery("select * from change_log where table_name = 'accounts' and (old_value <> '[NEW RECORD]' or column_name = 'credit_limit') and column_name not in ('contact_id','payment_method_id') and primary_identifier = ?", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			if ($row['old_value'] == "[NEW RECORD]") {
				$description = "Created account, " . str_replace("_", " ", $row['column_name']) . " set to '" . $row['new_value'] . "'";
			} else {
				$description = "Change " . str_replace("_", " ", $row['column_name']) . " from '" . $row['old_value'] . "' to '" . $row['new_value'] . "'";
			}
			$logEntries[] = array("description" => $description, "log_time" => $row['time_changed'], "user_id" => $row['user_id']);
		}
		$resultSet = executeQuery("select * from credit_account_log where account_id = ?", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$logEntries[] = $row;
		}
		usort($logEntries, array($this, "sortLogEntries"));
		foreach ($logEntries as $thisEntry) {
			$logContent .= "<tr><td>" . date("m/d/Y g:ia", strtotime($thisEntry['log_time'])) . "</td><td>" . getUserDisplayName($thisEntry['user_id']) .
				"</td><td>" . htmlText($thisEntry['description']) . "</td><td>" . (empty($thisEntry['amount']) ? "" : number_format($thisEntry['amount'], 2)) .
				"</td><td>" . htmlText($thisEntry['notes']) . "</td></tr>";
		}
		$returnArray['account_log'] = array("data_value" => $logContent);
		$returnArray['description'] = array("data_value" => "", "crc_value"=>getCrcValue(""));
		$returnArray['entry_type'] = array("data_value" => "", "crc_value"=>getCrcValue(""));
		$returnArray['amount'] = array("data_value" => "", "crc_value"=>getCrcValue(""));
		$returnArray['log_notes'] = array("data_value" => "", "crc_value"=>getCrcValue(""));
	}

	private function sortLogEntries($a, $b) {
		if ($a['log_time'] == $b['log_time']) {
			return 0;
		}
		return ($a['log_time'] > $b['log_time']) ? -1 : 1;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if ($GLOBALS['gPermissionLevel'] > _READONLY && !empty($nameValues['add_log_entry']) && $nameValues['amount'] > 0) {
			$amount = $nameValues['amount'] * ($nameValues['entry_type'] == "reduce" ? -1 : 1);
			executeQuery("insert into credit_account_log (account_id,description,user_id,amount,notes) values (?,?,?,?,?)", $nameValues['primary_id'], $nameValues['description'], $GLOBALS['gUserId'], $amount, $nameValues['log_notes']);
			executeQuery("update accounts set credit_limit = credit_limit + ? where account_id = ?", $amount, $nameValues['primary_id']);
		}
		return true;
	}
}

$pageObject = new CreditAccountLogPage("accounts");
$pageObject->displayPage();
