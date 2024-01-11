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

$GLOBALS['gPageCode'] = "DESIGNATIONFUNDACCOUNTMAINT";
require_once "shared/startup.inc";

class DesignationFundAccountMaintenancePage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));
			$filters = array();
			$filters['group_header'] = array("form_label" => "Groups", "data_type" => "header");
			$resultSet = executeQuery("select * from designation_groups where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['designation_group_' . $row['designation_group_id']] = array("form_label" => $row['description'], "where" => "designation_id in (select designation_id from designation_group_links where designation_group_id = " . $row['designation_group_id'] . ")", "data_type" => "tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("designation_code", "readonly", "true");
		$this->iDataSource->addColumnControl("description", "readonly", "true");
		$this->iDataSource->setFilterWhere(($GLOBALS['gUserRow']['full_client_access'] ? "" : "inactive = 0 and designation_id in (select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ")"));
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("remainder_fund_account_id", "data_type", "select");
		$this->iDataSource->addColumnControl("remainder_fund_account_id", "form_label", "Remainder Fund Account");
		$this->iDataSource->addColumnControl("remainder_fund_account_id", "help_label", "All but the remainder amount will be put into this fund");
		$this->iDataSource->addColumnControl("remainder_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("remainder_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("remainder_amount", "minimum_value", "0");
		$this->iDataSource->addColumnControl("remainder_amount", "form_label", "Remainder Amount");
		$this->iDataSource->addColumnControl("remainder_amount", "help_label", "All but this amount will be put into the Remainder Fund");
	}

	function fundDeductions() {
		?>
        <table id="fund_list" class="grid-table">
            <tr>
                <th>Use Defaults</th>
                <th>Fund</th>
                <th>Fixed Amount</th>
                <th>Percentage</th>
				<?php if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("EDIT_FUND_MINIMUM_MAXIMUM")) { ?>
                    <th>Payroll Minimum</th>
                    <th>Fund Maximum</th>
                    <th>Max Per Month</th>
				<?php } ?>
            </tr>
        </table>
		<?php
	}

	function massageUrlParameters() {
		if (!$GLOBALS['gUserRow']['full_client_access']) {
			$resultSet = executeQuery("select designation_id from designations where client_id = ? and inactive = 0 and " .
				"designation_id in (select designation_id from designation_users where user_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
			if ($resultSet['row_count'] == 1) {
				if ($row = getNextRow($resultSet)) {
					$_GET['url_subpage'] = $_GET['url_page'];
					$_GET['url_page'] = "show";
					$_GET['primary_id'] = $row['designation_id'];
				}
			}
		}
	}

	function afterGetRecord(&$returnArray) {
		if (!is_array($returnArray['select_values'])) {
			$returnArray['select_values'] = array();
		}
		$returnArray['select_values']['remainder_fund_account_id'] = array();
		$resultSet = executeQuery("select * from fund_accounts where fund_account_id in (select fund_account_id from fund_account_designation_groups where designation_group_id in " .
			"(select designation_group_id from designation_group_links where designation_id = ?)) and inactive = 0 and " .
			"internal_use_only = 0 and client_id = ? order by sort_order,description", $returnArray['primary_id']['data_value'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['select_values']['remainder_fund_account_id'][] = array("key_value" => $row['fund_account_id'], "description" => $row['description']);
		}
		$designationRemainderFundRow = getRowFromId("designation_remainder_funds", "designation_id", $returnArray['primary_id']['data_value']);
		$returnArray['remainder_fund_account_id'] = array("data_value" => $designationRemainderFundRow['fund_account_id'], "crc_value" => getCrcValue($designationRemainderFundRow['fund_account_id']));
		$returnArray['remainder_amount'] = array("data_value" => (empty($designationRemainderFundRow) ? "" : number_format($designationRemainderFundRow['amount'], 2, ".", "")),
			"crc_value" => getCrcValue(empty($designationRemainderFundRow) ? "" : number_format($designationRemainderFundRow['amount'], 2, ".", "")));

		$resultSet = executeQuery("select * from fund_accounts where fund_account_id in (select fund_account_id from fund_account_designation_groups where designation_group_id in " .
			"(select designation_group_id from designation_group_links where designation_id = ?)) and inactive = 0 and " .
			"internal_use_only = 0 and client_id = ? order by sort_order,description", $returnArray['primary_id']['data_value'], $GLOBALS['gClientId']);
		$fundsArray = array();
		while ($row = getNextRow($resultSet)) {
			$defaultSet = executeQuery("select max(amount),max(percentage),max(minimum_amount),min(maximum_amount),min(per_month_maximum) from fund_account_designation_groups where designation_group_id in " .
				"(select designation_group_id from designation_group_links where designation_id = ?) and fund_account_id = ?",
				$returnArray['primary_id']['data_value'], $row['fund_account_id']);
			$defaultRow = getNextRow($defaultSet);
			$fundAccountRow = getRowFromId("designation_fund_accounts", "designation_id", $returnArray['primary_id']['data_value'],
				"fund_account_id = ?", $row['fund_account_id']);
			$thisFundArray = array("fund_account_id" => $row['fund_account_id'], "use_default" => (empty($fundAccountRow) ? 1 : 0),
				"description" => $row['description'], "amount" => (empty($fundAccountRow) ? $defaultRow['max(amount)'] : $fundAccountRow['amount']),
				"percentage" => (empty($fundAccountRow) ? $defaultRow['max(percentage)'] : $fundAccountRow['percentage']));
			if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("EDIT_FUND_MINIMUM_MAXIMUM")) {
				$thisFundArray['minimum_amount'] = (empty($fundAccountRow) ? $defaultRow['max(minimum_amount)'] : $fundAccountRow['minimum_amount']);
				$thisFundArray['maximum_amount'] = (empty($fundAccountRow) ? $defaultRow['min(maximum_amount)'] : $fundAccountRow['maximum_amount']);
				$thisFundArray['per_month_maximum'] = (empty($fundAccountRow) ? $defaultRow['min(per_month_maximum)'] : $fundAccountRow['per_month_maximum']);
			}
			$fundsArray[] = $thisFundArray;
		}
		$returnArray['fund_list'] = $fundsArray;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".fund-field", function () {
                if ($(this).closest(".fund-line").find(".use-default").prop("checked")) {
                    $(this).closest(".fund-line").find(".use-default").trigger("click");
                }
            });
            $(document).on("click", ".use-default", function () {
                const fundAccountId = $(this).closest("tr").find(".fund-account-id").val();
                $("#amount_" + fundAccountId).prop("readonly", $(this).prop("checked"));
                $("#percentage_" + fundAccountId).prop("readonly", $(this).prop("checked"));
				<?php if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("EDIT_FUND_MINIMUM_MAXIMUM")) { ?>
                $("#minimum_amount_" + fundAccountId).prop("readonly", $(this).prop("checked"));
                $("#maximum_amount_" + fundAccountId).prop("readonly", $(this).prop("checked"));
                $("#per_month_maximum_" + fundAccountId).prop("readonly", $(this).prop("checked"));
				<?php } ?>
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#fund_list").find("tr.fund-line").remove();
                if ("fund_list" in returnArray) {
                    for (const i in returnArray['fund_list']) {
                        const fundAccountId = returnArray['fund_list'][i]['fund_account_id'];
                        const fundLine = $("#fund_line").html().replace(/%fund_account_id%/g, fundAccountId);
                        $("#fund_list").append(fundLine);
                        $("#use_default_" + fundAccountId).prop("checked", returnArray['fund_list'][i]['use_default']);
                        $("#fund_description_" + fundAccountId).text(returnArray['fund_list'][i]['description']);
                        $("#amount_" + fundAccountId).val(returnArray['fund_list'][i]['amount']).prop("readonly", returnArray['fund_list'][i]['use_default']);
                        $("#percentage_" + fundAccountId).val(returnArray['fund_list'][i]['percentage']).prop("readonly", returnArray['fund_list'][i]['use_default']);
						<?php if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("EDIT_FUND_MINIMUM_MAXIMUM")) { ?>
                        $("#minimum_amount_" + fundAccountId).val(returnArray['fund_list'][i]['minimum_amount']).prop("readonly", returnArray['fund_list'][i]['use_default']);
                        $("#maximum_amount_" + fundAccountId).val(returnArray['fund_list'][i]['maximum_amount']).prop("readonly", returnArray['fund_list'][i]['use_default']);
                        $("#per_month_maximum_" + fundAccountId).val(returnArray['fund_list'][i]['per_month_maximum']).prop("readonly", returnArray['fund_list'][i]['use_default']);
						<?php } ?>
                    }
                }
            }
        </script>
		<?php
	}

	function JqueryTemplates() {
		?>
        <table>
            <tbody id="fund_line">
            <tr class="fund-line" id="fund_line_%fund_account_id%">
                <td class="align-center"><input class="fund-account-id" type="hidden" id="fund_account_id_%fund_account_id%" name="fund_account_id_%fund_account_id%" value="%fund_account_id%"><input class="use-default" tabindex="10" type="checkbox" id="use_default_%fund_account_id%" name="use_default_%fund_account_id%" value="Y"></td>
                <td id="fund_description_%fund_account_id%"></td>
                <td class="align-right"><input tabindex="10" type="text" size="14" class="fund-field align-right validate[required,custom[number]]" data-decimal-places="2" id="amount_%fund_account_id%" name="amount_%fund_account_id%"></td>
                <td class="align-right"><input tabindex="10" type="text" size="6" class="fund-field align-right validate[required,custom[number],max[100]]" data-decimal-places="2" id="percentage_%fund_account_id%" name="percentage_%fund_account_id%"></td>
				<?php if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("EDIT_FUND_MINIMUM_MAXIMUM")) { ?>
                    <td class="align-right"><input tabindex="10" type="text" size="14" class="fund-field align-right validate[custom[number]]" data-decimal-places="2" id="minimum_amount_%fund_account_id%" name="minimum_amount_%fund_account_id%"></td>
                    <td class="align-right"><input tabindex="10" type="text" size="14" class="fund-field align-right validate[custom[number]]" data-decimal-places="2" id="maximum_amount_%fund_account_id%" name="maximum_amount_%fund_account_id%"></td>
                    <td class="align-right"><input tabindex="10" type="text" size="14" class="fund-field align-right validate[custom[number]]" data-decimal-places="2" id="per_month_maximum_%fund_account_id%" name="per_month_maximum_%fund_account_id%"></td>
				<?php } ?>
            </tr>
            </tbody>
        </table>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("select * from fund_accounts where fund_account_id in (select fund_account_id from fund_account_designation_groups where designation_group_id in " .
			"(select designation_group_id from designation_group_links where designation_id = ?)) and inactive = 0 and " .
			"internal_use_only = 0 and client_id = ? order by sort_order,description", $nameValues['primary_id'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists("fund_account_id_" . $row['fund_account_id'], $nameValues)) {
				continue;
			}

			$defaultSet = executeQuery("select max(amount),max(percentage),max(minimum_amount),min(maximum_amount),min(per_month_maximum) from fund_account_designation_groups where designation_group_id in " .
				"(select designation_group_id from designation_group_links where designation_id = ?) and fund_account_id = ?",
				$row['fund_account_id'], $row['fund_account_id']);
			$defaultRow = getNextRow($defaultSet);

			$designationFundAccountRow = getRowFromId("designation_fund_accounts", "fund_account_id", $row['fund_account_id'], "designation_id = ?", $nameValues['primary_id']);
			executeQuery("delete from designation_fund_accounts where fund_account_id = ? and designation_id = ?",
				$row['fund_account_id'], $nameValues['primary_id']);
			if (empty($nameValues['use_default_' . $row['fund_account_id']])) {
				if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("EDIT_FUND_MINIMUM_MAXIMUM")) {
					executeQuery("insert into designation_fund_accounts (designation_id,fund_account_id,amount,percentage,minimum_amount,maximum_amount,per_month_maximum) " .
						"values (?,?,?,?,?,?,?)", $nameValues['primary_id'], $row['fund_account_id'], $nameValues['amount_' . $row['fund_account_id']],
						$nameValues['percentage_' . $row['fund_account_id']], $nameValues['minimum_amount_' . $row['fund_account_id']],
						$nameValues['maximum_amount_' . $row['fund_account_id']], $nameValues['per_month_maximum_' . $row['fund_account_id']]);
				} else {
					$designationFundAccountRow['minimum_amount'] = (empty($designationFundAccountRow['designation_fund_account_id']) ? $defaultRow['max(minimum_amount)'] : $designationFundAccountRow['minimum_amount']);
					$designationFundAccountRow['maximum_amount'] = (empty($designationFundAccountRow['designation_fund_account_id']) ? $defaultRow['min(maximum_amount)'] : $designationFundAccountRow['maximum_amount']);
					$designationFundAccountRow['per_month_maximum'] = (empty($designationFundAccountRow['designation_fund_account_id']) ? $defaultRow['min(per_month_maximum)'] : $designationFundAccountRow['per_month_maximum']);
					executeQuery("insert into designation_fund_accounts (designation_id,fund_account_id,amount,percentage,minimum_amount,maximum_amount,per_month_maximum) " .
						"values (?,?,?,?,?,?,?)", $nameValues['primary_id'], $row['fund_account_id'], $nameValues['amount_' . $row['fund_account_id']],
						$nameValues['percentage_' . $row['fund_account_id']],
						(strlen($designationFundAccountRow['minimum_amount']) == 0 ? $row['minimum_amount'] : $designationFundAccountRow['minimum_amount']),
						(strlen($designationFundAccountRow['maximum_amount']) == 0 ? $row['maximum_amount'] : $designationFundAccountRow['maximum_amount']),
						(strlen($designationFundAccountRow['per_month_maximum']) == 0 ? $row['per_month_maximum'] : $designationFundAccountRow['per_month_maximum']));
				}
			}
		}
		executeQuery("delete from designation_remainder_funds where designation_id = ?", $nameValues['primary_id']);
		if (!empty($nameValues['remainder_fund_account_id']) && strlen($nameValues['remainder_amount']) > 0 && $nameValues['remainder_amount'] >= 0) {
			executeQuery("insert into designation_remainder_funds (designation_id,fund_account_id,amount) values (?,?,?)",
				$nameValues['primary_id'], $nameValues['remainder_fund_account_id'], $nameValues['remainder_amount']);
		}
		return true;
	}
}

$pageObject = new DesignationFundAccountMaintenancePage("designations");
$pageObject->displayPage();
