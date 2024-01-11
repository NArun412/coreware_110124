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

$GLOBALS['gPageCode'] = "FUNDACCOUNTDETAILENTRY";
require_once "shared/startup.inc";

class FundAccountDetailEntryPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add", "list"));
		}
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#_save_button", function () {
                disableButtons($("#_save_button"));
                if ($(this).data("ignore") === "true") {
                    enableButtons($("#_save_button"));
                    return false;
                }
                if ($("#_designations_values").find(".editable-list-data-row").length === 0) {
                    displayErrorMessage("No designations chosen");
                    enableButtons($("#_save_button"));
                    return false;
                }
                if ($("#_fund_account_detail_values").find(".editable-list-data-row").length === 0) {
                    displayErrorMessage("No payments created");
                    enableButtons($("#_save_button"));
                    return false;
                }
                if ($("#_permission").val() <= "1") {
                    displayErrorMessage("<?= getSystemMessage("readonly") ?>");
                    enableButtons($("#_save_button"));
                    return false;
                }
                saveChanges(function () {
                    $("#_fund_account_detail_values").find(".editable-list-data-row").remove();
                    $("#_designations_values").find(".editable-list-data-row").remove();
                    enableButtons($("#_save_button"));
                }, function () {
                    enableButtons($("#_save_button"));
                });
                return false;
            });
            $(document).on("tap click", ".preference-value-checkbox", function () {
                $(this).closest(".form-line").find(".preference-value").val($(this).prop("checked") ? "true" : "false");
            });
            displayFormHeader();
            $(".page-record-display").hide();
        </script>
		<?php
		return true;
	}

	function designationChoices($showInactive = false) {
		$designationChoices = array();
		$resultSet = executeQuery("select * from designations where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designationChoices[$row['designation_id']] = array("key_value" => $row['designation_id'], "description" => $row['description']);
		}
		freeResult($resultSet);
		return $designationChoices;
	}

	function fundAccountChoices($showInactive = false) {
		$fundAccountChoices = array();
		$resultSet = executeQuery("select * from fund_accounts where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$fundAccountChoices[$row['fund_account_id']] = array("key_value" => $row['fund_account_id'], "description" => $row['description']);
		}
		freeResult($resultSet);
		return $fundAccountChoices;
	}

	function fundAccountEntryTypeChoices($showInactive = false) {
		$fundAccountEntryTypeChoices = array();
		$resultSet = executeQuery("select * from fund_account_entry_types where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$fundAccountEntryTypeChoices[$row['fund_account_entry_type_id']] = array("key_value" => $row['fund_account_entry_type_id'], "description" => $row['description']);
		}
		freeResult($resultSet);
		return $fundAccountEntryTypeChoices;
	}

	function mainContent() {
		?>
        <form id="_edit_form">
            <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_designations_values">
				<?php
				$designationColumn = new DataColumn("designations");
				$designationColumn->setControlValue("data_type", "custom");
				$designationColumn->setControlValue("control_class", "EditableList");
				$designations = new EditableList($designationColumn, $this);

				$designationId = new DataColumn("designation_name");
				$designationId->setControlValue("data_type", "select");
				$designationId->setControlValue("form_label", "Designations");
				$designationId->setControlValue("not_null", true);
				$designationId->setControlValue("get_choices", "designationChoices");

				$columnList = array("designation_name" => $designationId);
				$designations->setColumnList($columnList);
				?>
                <label>Designations that will split funds</label>
				<?= $designations->getControl() ?>
            </div>

            <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_fund_account_detail_values">
				<?php
				$fundAccountColumn = new DataColumn("fund_account_detail");
				$fundAccountColumn->setControlValue("data_type", "custom");
				$fundAccountColumn->setControlValue("control_class", "EditableList");
				$fundAccounts = new EditableList($fundAccountColumn, $this);

				$fundAccountId = new DataColumn("fund_account_id");
				$fundAccountId->setControlValue("data_type", "select");
				$fundAccountId->setControlValue("form_label", "Fund Account");
				$fundAccountId->setControlValue("get_choices", "fundAccountChoices");
				$fundAccountId->setControlValue("not_null", true);

				$purchaseDate = new DataColumn("purchase_date");
				$purchaseDate->setControlValue("data_type", "date");
				$purchaseDate->setControlValue("form_label", "Purchase Date");
				$purchaseDate->setControlValue("not_null", true);

				$fundAccountEntryTypeId = new DataColumn("fund_account_entry_type_id");
				$fundAccountEntryTypeId->setControlValue("data_type", "select");
				$fundAccountEntryTypeId->setControlValue("form_label", "Fund Account Entry Type");
				$fundAccountEntryTypeId->setControlValue("get_choices", "fundAccountEntryTypeChoices");
				$fundAccountEntryTypeId->setControlValue("not_null", true);

				$description = new DataColumn("description");
				$description->setControlValue("data_type", "varchar");
				$description->setControlValue("form_label", "Description");
				$description->setControlValue("not_null", true);

				$amount = new DataColumn("amount");
				$amount->setControlValue("data_type", "decimal");
				$amount->setControlValue("decimal_places", "2");
				$amount->setControlValue("form_label", "Amount");
				$amount->setControlValue("not_null", true);

				$columnList = array("fund_account_id" => $fundAccountId, "purchase_date" => $purchaseDate, "fund_account_entry_type_id" => $fundAccountEntryTypeId, "description" => $description, "amount" => $amount);
				$fundAccounts->setColumnList($columnList);
				?>
                <label>Fund Account Purchases</label>
				<?= $fundAccounts->getControl() ?>
            </div>
        </form>
		<?php
		return true;
	}

	function jqueryTemplates() {
		$designationColumn = new DataColumn("designations");
		$designationColumn->setControlValue("data_type", "custom");
		$designationColumn->setControlValue("control_class", "EditableList");
		$designations = new EditableList($designationColumn, $this);

		$designationId = new DataColumn("designation_name");
		$designationId->setControlValue("data_type", "select");
		$designationId->setControlValue("form_label", "Designations");
		$designationId->setControlValue("not_null", true);
		$designationId->setControlValue("get_choices", "designationChoices");

		$columnList = array("designation_name" => $designationId);
		$designations->setColumnList($columnList);
		echo $designations->getTemplate();

		$fundAccountColumn = new DataColumn("fund_account_detail");
		$fundAccountColumn->setControlValue("data_type", "custom");
		$fundAccountColumn->setControlValue("control_class", "EditableList");
		$fundAccounts = new EditableList($fundAccountColumn, $this);

		$fundAccountId = new DataColumn("fund_account_id");
		$fundAccountId->setControlValue("data_type", "select");
		$fundAccountId->setControlValue("form_label", "Fund Account");
		$fundAccountId->setControlValue("get_choices", "fundAccountChoices");
		$fundAccountId->setControlValue("not_null", true);

		$purchaseDate = new DataColumn("purchase_date");
		$purchaseDate->setControlValue("data_type", "date");
		$purchaseDate->setControlValue("form_label", "Purchase Date");
		$purchaseDate->setControlValue("not_null", true);

		$fundAccountEntryTypeId = new DataColumn("fund_account_entry_type_id");
		$fundAccountEntryTypeId->setControlValue("data_type", "select");
		$fundAccountEntryTypeId->setControlValue("form_label", "Fund Account Entry Type");
		$fundAccountEntryTypeId->setControlValue("get_choices", "fundAccountEntryTypeChoices");
		$fundAccountEntryTypeId->setControlValue("not_null", true);

		$description = new DataColumn("description");
		$description->setControlValue("data_type", "varchar");
		$description->setControlValue("form_label", "Description");
		$description->setControlValue("not_null", true);

		$amount = new DataColumn("amount");
		$amount->setControlValue("data_type", "decimal");
		$amount->setControlValue("decimal_places", "2");
		$amount->setControlValue("form_label", "Amount");
		$amount->setControlValue("not_null", true);

		$columnList = array("fund_account_id" => $fundAccountId, "purchase_date" => $purchaseDate, "fund_account_entry_type_id" => $fundAccountEntryTypeId, "description" => $description, "amount" => $amount);
		$fundAccounts->setColumnList($columnList);
		echo $fundAccounts->getTemplate();
	}

	function saveChanges() {
		$returnArray = array();
		$designationIds = array();
		foreach ($_POST as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("designations_designation_name-")) == "designations_designation_name-") {
				$designationId = getFieldFromId("designation_id", "designations", "designation_id", $fieldData, "inactive = 0");
				$designationIds[] = $designationId;
			}
		}
		if (empty($designationIds)) {
			$returnArray['error_message'] = "No valid designations chosen";
			ajaxResponse($returnArray);
		}
		$rowCount = 0;
		foreach ($_POST as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("fund_account_detail_purchase_date-")) == "fund_account_detail_purchase_date-") {
				$rowNumber = substr($fieldName, strlen("fund_account_detail_purchase_date-"));
				$purchaseDate = date("Y-m-d", strtotime($fieldData));
				$fundAccountId = getFieldFromId("fund_account_id", "fund_accounts", "fund_account_id", $_POST['fund_account_detail_fund_account_id-' . $rowNumber], "inactive = 0");
				$fundAccountEntryTypeId = getFieldFromId("fund_account_entry_type_id", "fund_account_entry_types", "fund_account_entry_type_id", $_POST['fund_account_detail_fund_account_entry_type_id-' . $rowNumber], "inactive = 0");
				$description = $_POST['fund_account_detail_description-' . $rowNumber];
				$amount = $_POST['fund_account_detail_amount-' . $rowNumber];
				if (empty($fundAccountId) || empty($purchaseDate) || $purchaseDate < '2000-01-01' || empty($fundAccountEntryTypeId) || empty($description) || empty($amount)) {
					continue;
				}
				$negativeAmount = ($amount < 0);
				$amount = abs($amount);
				$eachAmount = floor(($amount * 100) / count($designationIds)) / 100;
				$remainder = $amount - ($eachAmount * count($designationIds));
				foreach ($designationIds as $designationId) {
					$thisAmount = $eachAmount;
					if ($remainder > 0) {
						$thisAmount += .01;
						$remainder -= .01;
					}
					$thisAmount *= ($negativeAmount ? -1 : 1);
					if ($thisAmount != 0) {
						executeQuery("insert into fund_account_details (fund_account_id,designation_id,description,amount,entry_date,date_paid_out,fund_account_entry_type_id) values " .
							"(?,?,?,?,now(),?,?)", $fundAccountId, $designationId, $description, $thisAmount, $purchaseDate, $fundAccountEntryTypeId);
						$rowCount++;
					}
				}
			}
		}
		$returnArray['info_message'] = $rowCount . " fund account detail records created";
		ajaxResponse($returnArray);
	}
}

$pageObject = new FundAccountDetailEntryPage("fund_account_details");
$pageObject->displayPage();
