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

$GLOBALS['gPageCode'] = "SPLITDONATION";
require_once "shared/startup.inc";

class SplitDonationPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$resultSet = executeQuery("select * from designation_groups where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['designation_group_' . $row['designation_group_id']] = array("form_label" => $row['description'], "where" => "designation_id in (select designation_id from designation_group_links where designation_group_id = " . $row['designation_group_id'] . ")", "data_type" => "tinyint");
			}
			$filters['start_date'] = array("form_label" => "Start Date", "where" => "donation_date >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Date", "where" => "donation_date <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$resultSet = executeQuery("select * from designations where inactive = 0 and client_id = ? order by description", $GLOBALS['gClientId']);
			$designations = array();
			if ($resultSet['row_count'] < 200) {
				while ($row = getNextRow($resultSet)) {
					$designations[$row['designation_id']] = $row['description'];
				}
				$filters['designation_id'] = array("form_label" => "Designations", "where" => "designation_id = %key_value%", "data_type" => "select", "choices" => $designations);
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("donation_id", "batch_number", "first_name", "last_name", "business_name", "donation_date", "designation_id", "project_name", "amount", "email_address", "payment_method_id", "reference_number", "receipt_sent", "donation_source_id"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("donation_id", "batch_number", "first_name", "last_name", "business_name", "donation_date", "designation_id", "amount", "project_name", "email_address", "payment_method_id", "reference_number"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(8);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "(contact_id in (select contact_id from contacts where (first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")))";
				$this->iDataSource->addSearchWhereStatement($whereStatement);
			}
			$this->iDataSource->setFilterText($filterText);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts", "referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts", "referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts", "referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "business_name"));
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last");
		$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Company");

		$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_address", "form_label", "Email");

		$this->iDataSource->addColumnControl("donation_date", "readonly", true);
		$this->iDataSource->addColumnControl("contact_id", "readonly", true);
		$this->iDataSource->addColumnControl("payment_method_id", "readonly", true);
		$this->iDataSource->addColumnControl("reference_number", "readonly", true);
		$this->iDataSource->addColumnControl("amount", "readonly", true);
		$this->iDataSource->addColumnControl("designation_id", "readonly", true);

		$this->iDataSource->addColumnControl("split_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("split_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("split_amount", "form_label", "Split Amount");
		$this->iDataSource->addColumnControl("split_amount", "minimum_value", "0");
		$this->iDataSource->addColumnControl("split_amount", "maximum_value", "1");
		$this->iDataSource->addColumnControl("split_amount", "help_label", "This amount will be removed from the original donation and applied to a new donation with the below designation.");

		$this->iDataSource->addColumnControl("new_designation_id", "data_type", "autocomplete");
		$this->iDataSource->addColumnControl("new_designation_id", "data-autocomplete_tag", "designations");
		$this->iDataSource->addColumnControl("new_designation_id", "form_label", "New Designation");
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['split_amount'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['new_designation_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
	}

	function javascript() {
		?>
		<script>
			function afterGetRecord(returnArray) {
				$("#split_amount").data("maximum-value",returnArray['amount']['data_value']);
			}
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			#_main_content li {
				list-style: disc;
				margin-left: 40px;
			}
			#_main_content ul {
				margin-bottom: 40px;
			}
		</style>
		<?php
	}

	function saveChanges() {
		$returnArray = array();
		$donationId = $_POST['primary_id'];
		$donationRow = getRowFromId("donations", "donation_id", $donationId);
		$splitAmount = $_POST['split_amount'];
		$designationId = $_POST['new_designation_id'];
		if (empty($donationRow) || $splitAmount <= 0 || $splitAmount > $donationRow['amount'] || empty($designationId) || $designationId == $donationRow['designation_id']) {
			$returnArray['error_message'] = "Unable to create new donation";
			ajaxResponse($returnArray);
		}
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$dataTable = new DataTable("donations");
		$dataTable->setSaveOnlyPresent(true);
		if ($splitAmount == $donationRow['amount']) {
			if (!$dataTable->saveRecord(array("name_values"=>array("designation_id"=>$designationId),"primary_id"=>$donationId))) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to save changes";
				ajaxResponse($returnArray);
			}
		} else {
			$newDonationRow = $donationRow;
			$newDonationRow['donation_id'] = "";
			$newDonationRow['amount'] = $splitAmount;
			$newDonationRow['designation_id'] = $designationId;
			unset($newDonationRow['version']);
			if (!$dataTable->saveRecord(array("name_values"=>$newDonationRow))) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to save changes";
				ajaxResponse($returnArray);
			}
			$newAmount = $donationRow['amount'] - $splitAmount;
			if (!$dataTable->saveRecord(array("name_values"=>array("amount"=>$newAmount),"primary_id"=>$donationId))) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to save changes";
				ajaxResponse($returnArray);
			}
		}
		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		$returnArray['info_message'] = "New donation created";
		ajaxResponse($returnArray);
	}
}

$pageObject = new SplitDonationPage("donations");
$pageObject->displayPage();
