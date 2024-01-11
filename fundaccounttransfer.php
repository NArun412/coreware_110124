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

$GLOBALS['gPageCode'] = "FUNDACCOUNTTRANSFER";
require_once "shared/startup.inc";

class FundAccountTransferPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_balances":
				if ($GLOBALS['gUserRow']['full_client_access']) {
					$designationId = getFieldFromId("designation_id", "designations", "designation_id", $_GET['designation_id']);
				} else {
					$designationId = getFieldFromId("designation_id", "designations", "designation_id", $_GET['designation_id'],
						"designation_id in (select designation_id from designation_users where user_id = ?)", $GLOBALS['gUserId']);
				}

				if (empty($_GET['from_fund_account_id'])) {
					$returnArray['from_fund_account_message'] = "";
				} else {
					$resultSet = executeQuery("select sum(amount) from fund_account_details where fund_account_id = ? and designation_id = ?", $_GET['from_fund_account_id'], $designationId);
					$fromAmount = 0;
					if ($row = getNextRow($resultSet)) {
						$fromAmount = $row['sum(amount)'];
					}
					$fromDescription = getFieldFromId("description", "fund_accounts", "fund_account_id", $_GET['from_fund_account_id']);
					$returnArray['from_fund_account_message'] = $fromDescription . " has balance of $" . number_format($fromAmount, 2, ".", ",");
				}

				if (empty($_GET['to_fund_account_id'])) {
					$returnArray['to_fund_account_message'] = "";
				} else {
					$resultSet = executeQuery("select sum(amount) from fund_account_details where fund_account_id = ? and designation_id = ?", $_GET['to_fund_account_id'], $designationId);
					$toAmount = 0;
					if ($row = getNextRow($resultSet)) {
						$toAmount = $row['sum(amount)'];
					}
					$toDescription = getFieldFromId("description", "fund_accounts", "fund_account_id", $_GET['to_fund_account_id']);
					$returnArray['to_fund_account_message'] = $toDescription . " has balance of $" . number_format($toAmount, 2, ".", ",");
				}
				ajaxResponse($returnArray);
				break;
		}
	}

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
                saveChanges(function () {
                    setTimeout(function () {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    }, 2000);
                }, function () {
                    enableButtons($("#_save_button"));
                });
                return false;
            });
            $("#designation_id,#from_fund_account_id,#to_fund_account_id").change(function () {
                displayBalanceMessages();
            });
            displayFormHeader();
            $(".page-record-display").hide();
        </script>
		<?php
		return true;
	}

	function javascript() {
		?>
        <script>
            function displayBalanceMessages() {
                $("#from_fund_account_message").html("");
                $("#to_fund_account_message").html("");
                if (!empty($("#designation_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_balances&designation_id=" + $("#designation_id").val() + "&from_fund_account_id=" + $("#from_fund_account_id").val() + "&to_fund_account_id=" + $("#to_fund_account_id").val(), function(returnArray) {
                        if ("from_fund_account_message" in returnArray) {
                            $("#from_fund_account_message").html(returnArray['from_fund_account_message']);
                        }
                        if ("to_fund_account_message" in returnArray) {
                            $("#to_fund_account_message").html(returnArray['to_fund_account_message']);
                        }
                    });
                }
            }
        </script>
		<?php
	}

	function mainContent() {
		?>
        <form id="_edit_form">
            <div class="basic-form-line" id="_designation_id_row">
                <label for="designation_id" class="required-label">Designation</label>
                <select tabindex="10" id="designation_id" name="designation_id" class="validate[required]">
					<?php
					$resultSet = executeQuery("select * from designations where client_id = ? and inactive = 0" .
						($GLOBALS['gUserRow']['full_client_access'] ? "" : " and designation_id in (select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ")") .
						" order by sort_order,description", $GLOBALS['gClientId']);
					if ($resultSet['row_count'] != 1) {
						?>
                        <option value="">[Select]</option>
						<?php
					}
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

			<?php
			echo createFormControl("fund_account_details", "fund_account_id", array("column_name" => "from_fund_account_id", "not_null" => true, "get_choices" => "fundAccountChoices"));
			?>
            <p id="from_fund_account_message"></p>
			<?php
			echo createFormControl("fund_account_details", "fund_account_id", array("column_name" => "to_fund_account_id", "not_null" => true, "get_choices" => "fundAccountChoices"));
			?>
            <p id="to_fund_account_message"></p>
			<?php
			echo createFormControl("fund_account_details", "description", array("not_null" => true));
			echo createFormControl("fund_account_details", "amount", array("not_null" => true));
			?>
        </form>
		<?php
		return true;
	}

	function fundAccountChoices($showInactive = false) {
		$fundAccountChoices = array();
		$resultSet = executeQuery("select * from fund_accounts where client_id = ? and no_transfer_allowed = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$fundAccountChoices[$row['fund_account_id']] = array("key_value" => $row['fund_account_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $fundAccountChoices;
	}

	function saveChanges() {
		if ($GLOBALS['gUserRow']['full_client_access']) {
			$designationId = getFieldFromId("designation_id", "designations", "designation_id", $_POST['designation_id']);
		} else {
			$designationId = getFieldFromId("designation_id", "designations", "designation_id", $_POST['designation_id'],
				"designation_id in (select designation_id from designation_users where user_id = ?)", $GLOBALS['gUserId']);
		}
		$resultSet = executeQuery("select sum(amount) from fund_account_details where fund_account_id = ? and designation_id = ?", $_POST['from_fund_account_id'], $designationId);
		$fromAmount = 0;
		if ($row = getNextRow($resultSet)) {
			$fromAmount = $row['sum(amount)'];
		}
		if ($fromAmount < $_POST['amount']) {
			$returnArray['error_message'] = "From Fund Account doesn't have enough balance.";
			ajaxResponse($returnArray);
		}
		$this->iDatabase->startTransaction();
		$fundAccountEntryTypeId = getFieldFromId("fund_account_entry_type_id", "fund_account_entry_types", "fund_account_entry_type_code", "TRANSFER");
		$insertSet = executeQuery("insert into fund_account_details (fund_account_id,designation_id,description,amount,entry_date,date_paid_out,fund_account_entry_type_id) values " .
			"(?,?,?,?,current_date,current_date,?)", $_POST['from_fund_account_id'], $designationId, $_POST['description'], ($_POST['amount'] * -1), $fundAccountEntryTypeId);
		if (!empty($insertSet['sql_error'])) {
			$this->iDatabase->rollbackTransaction();
			$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
			ajaxResponse($returnArray);
		}
		$insertSet = executeQuery("insert into fund_account_details (fund_account_id,designation_id,description,amount,entry_date,date_paid_out,fund_account_entry_type_id) values " .
			"(?,?,?,?,current_date,current_date,?)", $_POST['to_fund_account_id'], $designationId, $_POST['description'], $_POST['amount'], $fundAccountEntryTypeId);
		if (!empty($insertSet['sql_error'])) {
			$this->iDatabase->rollbackTransaction();
			$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
			ajaxResponse($returnArray);
		}
		$this->iDatabase->commitTransaction();
		$returnArray['info_message'] = "Fund account transfer successful";
		ajaxResponse($returnArray);
	}
}

$pageObject = new FundAccountTransferPage("fund_account_details");
$pageObject->displayPage();
