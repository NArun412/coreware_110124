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

$GLOBALS['gPageCode'] = "DESIGNATIONMAINT";
require_once "shared/startup.inc";

class DesignationMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("merchant_account_id");
			$resultSet = executeQuery("select count(*) from merchant_accounts where client_id = ?", $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				if ($row['count(*)'] <= 1) {
					$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn("merchant_account_id");
				}
			}
			$filters = array();
			$filters['group_header'] = array("form_label" => "Groups", "data_type" => "header");
			$resultSet = executeQuery("select * from designation_groups where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['designation_group_' . $row['designation_group_id']] = array("form_label" => $row['description'], "where" => "designation_id in (select designation_id from designation_group_links where designation_group_id = " . $row['designation_group_id'] . ")", "data_type" => "tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "check_designation_type":
				$resultSet = executeQuery("select * from designation_types where designation_type_id = ? and client_id = ?",
					$_GET['designation_type_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['payment_type'] = $row['payment_type'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("designation_deductions", "designation_group_links", "designation_projects", "designation_users", "designation_email_addresses"));
		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
		$this->iDataSource->addColumnControl("email_addresses", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_addresses", "form_label", "Email Addresses");
		$this->iDataSource->addColumnControl("email_addresses", "select_value", "select group_concat(email_address) from designation_email_addresses where designation_id = designations.designation_id");
		$this->iDataSource->addColumnControl("designation_files", "data_type", "custom");
		$this->iDataSource->addColumnControl("designation_files", "list_table", "designation_files");
		$this->iDataSource->addColumnControl("designation_files", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("end_recurring_gifts", "data_type", "date");
		$this->iDataSource->addColumnControl("end_recurring_gifts", "form_label", "Date to end all recurring donations for this designations (cannot be undone)");

		$this->iDataSource->addColumnControl("designation_giving_goals", "form_label", "Giving Goals");
		$this->iDataSource->addColumnControl("designation_giving_goals", "data_type", "custom");
		$this->iDataSource->addColumnControl("designation_giving_goals", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("designation_giving_goals", "list_table", "designation_giving_goals");
		$this->iDataSource->addColumnControl("designation_giving_goals", "list_table_controls", array("amount" => array("minimum_value" => 1)));
		$this->iDataSource->addColumnControl("designation_giving_goals", "help_label", "If more than one is active, only the first will be used");
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($nameValues['end_recurring_gifts'])) {
			$resultSet = executeQuery("select * from recurring_donations where designation_id = ? and (end_date is null or end_date > ?)", $nameValues['primary_id'], date("Y-m-d",strtotime($nameValues['end_recurring_gifts'])));
			while ($row = getNextRow($resultSet)) {
				$recurringDonationTable = new DataTable("recurring_donations");
				$recurringDonationTable->setSaveOnlyPresent(true);
				$recurringDonationTable->saveRecord(array("name_values" => array("end_date" => date("m/d/Y",strtotime($nameValues['end_recurring_gifts']))), "primary_id" => $row['recurring_donation_id']));
			}
		}
		$customFields = CustomField::getCustomFields("designations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#expenses_button", function () {
                if (empty($("#primary_id").val())) {
                    displayErrorMessage("Save the designation first");
                } else {
                    goToLink($(this), "/reimbursableexpenses.php?designation_id=" + $("#primary_id").val(), true);
                }
                return false;
            });
            $("#designation_type_id").change(function () {
                if (empty($(this).val())) {
                    $("#direct_debit_info").hide();
                    $("#check_info").hide();
                    $("#payroll_information").hide();
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_designation_type&designation_type_id=" + $(this).val(), function(returnArray) {
                        $("#direct_debit_info").hide();
                        $("#check_info").hide();
                        $("#payroll_information").hide();
                        if ("payment_type" in returnArray) {
                            if (returnArray['payment_type'] === "D") {
                                $("#direct_debit_info").show();
                                $("#payroll_information").show();
                            } else if (returnArray['payment_type'] === "C") {
                                $("#check_info").show();
                                $("#payroll_information").show();
                            }
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#designation_type_id").trigger("change");
            }
        </script>
		<?php
	}

	function internalCSS() {
		$rowCount = 0;
		$resultSet = executeQuery("select count(*) from payroll_deductions where client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$rowCount = $row['count(*)'];
		}
		if (empty($rowCount)) {
			?>
            <style>
                #deduction_section {
                    display: none;
                }
            </style>
			<?php
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['end_recurring_gifts'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$customFields = CustomField::getCustomFields("designations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
	}

	function addCustomFields() {
		$customFields = CustomField::getCustomFields("designations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("designations");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}

$pageObject = new DesignationMaintenancePage("designations");
$pageObject->displayPage();
