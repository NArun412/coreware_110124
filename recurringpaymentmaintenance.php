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

$GLOBALS['gPageCode'] = "RECURRINGPAYMENTMAINT";
require_once "shared/startup.inc";

class RecurringPaymentMaintenancePage extends Page {

	var $iSearchContactFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("first_name", "last_name", "business_name", "list_address_1", "start_date", "next_billing_date", "end_date", "error_message"));
			$filters = array();
			$filters['show_requires_attention'] = array("form_label" => "Show Requires Attention", "where" => "requires_attention = 1", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['without_subscription'] = array("form_label" => "No Contact Subscription and active", "where" => "contact_subscription_id is null and (end_date is null or end_date > current_date)", "data_type" => "tinyint", "conjunction" => "and");
            $filters['other_active_payment_method_exists'] = array("form_label" => "Payment method is inactive and Contact has active payment method",
                "where" => "(end_date is null or end_date > current_date) and contact_id in (select contact_id from accounts where inactive = 0) and account_id in (select account_id from accounts where inactive = 1)", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearrequiresattention", "Clear Requires Attention on selected rows");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "clearrequiresattention":
				$recurringPaymentIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$recurringPaymentIds[] = $row['primary_identifier'];
				}
				if (!empty($recurringPaymentIds)) {
					$insertSet = executeQuery("update recurring_payments set requires_attention = 0 where recurring_payment_id in (" . implode(",", $recurringPaymentIds) . ") and contact_id in (select contact_id from contacts where client_id = ?)", $GLOBALS['gClientId']);
					executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
					executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
							'recurring_payments', 'requires_attention', $insertSet['affected_rows'] . " rows cleared", "Set",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				}
				echo jsonEncode(array());
				exit;
			case "get_contact_info":
				$returnArray['select_values'] = array();
				$returnArray['select_values']['account_id'] = $this->getAccounts($_GET['contact_id']);

				$fieldArray = array("first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code");
				if (canAccessPageCode("CONTACTMAINT")) {
					$fieldArray[] = "notes";
				}
				$contactFields = Contact::getMultipleContactFields($_GET['contact_id'],$fieldArray);
				foreach ($contactFields as $fieldName => $fieldData) {
					$returnArray['contact_' . $fieldName] = $fieldData;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function getAccounts($contactId = "", $accountId = "") {
		$accounts = array();
		if (!empty($contactId)) {
			if (!empty($accountId)) {
				$resultSet = executeQuery("select * from accounts where contact_id = ? and ((inactive = 0 and account_token is not null) or account_id = ?)", $contactId, $accountId);
			} else {
				$resultSet = executeQuery("select * from accounts where contact_id = ? and (inactive = 0 and account_token is not null)", $contactId);
			}
			while ($row = getNextRow($resultSet)) {
				$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
				$merchantAccountInactive = getFieldFromId("inactive", "merchant_accounts", "merchant_account_id", $accountMerchantAccountId);
				if (!$merchantAccountInactive) {
					$description = (empty($row['account_label']) ? $row['account_number'] : $row['account_label'] . ", " . $row['account_number']);
					if (empty($description)) {
						$description = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
					}
					if ($accountMerchantAccountId != $GLOBALS['gMerchantAccountId']) {
						$description .= " (" . getFieldFromId("merchant_account_code", "merchant_accounts", "merchant_account_id", $accountMerchantAccountId) . ")";
					}
					$accounts[] = array("key_value" => $row['account_id'], "description" => $description);
				}
			}
		}
		return $accounts;
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "contact_id in (select contact_id from contacts where (first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
						" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			} else if (is_numeric($filterText)) {
				$whereStatement = "contact_id = " . $filterText . " or recurring_payment_id = " . $filterText;
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
				foreach ($this->iSearchContactFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contacts where " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . ")";
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contact_identifiers where identifier_value = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "error_message like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
				$this->iDataSource->addFilterWhere($whereStatement);
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("recurring_payment_order_items"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
				"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
				"description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
				"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
				"description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
				"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
				"description" => "business_name"));
		$this->iDataSource->addColumnControl("next_billing_date", "help_label", "Leave blank to use start date");
		$this->iDataSource->addColumnControl("next_billing_date", "not_null", "false");
		$this->iDataSource->addColumnControl("next_billing_date", "minimum_value", date("m/d/Y", strtotime("-1 month")));
		$this->iDataSource->addColumnControl("error_message", "readonly", "true");
		$this->iDataSource->addColumnControl("last_attempted", "readonly", "true");

		$this->iDataSource->addColumnControl("list_address_1", "select_value", "select address_1 from contacts where contact_id = recurring_payments.contact_id");
		$this->iDataSource->addColumnControl("list_address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = recurring_payments.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "maximum_length", "25");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = recurring_payments.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "maximum_length", "35");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last Name");

		if (canAccessPageCode("CONTACTMAINT")) {
			$this->iDataSource->addColumnControl("contact_notes", "select_value", "select notes from contacts where contact_id = recurring_payments.contact_id");
			$this->iDataSource->addColumnControl("contact_notes", "data_type", "text");
			$this->iDataSource->addColumnControl("contact_notes", "form_label", "Contact Notes");
			$this->iDataSource->addColumnControl("contact_notes", "help_label", "Notes from the contact record");
			if (canAccessPageCode("CONTACTMAINT") < _READWRITE) {
				$this->iDataSource->addColumnControl("contact_notes", "readonly", true);
			}
		}

		$this->iDataSource->addColumnControl("contact_subscription_id", "data_type", "hidden");
		$this->iDataSource->addColumnControl("contact_subscription_id_display", "data_type", "int");
		$this->iDataSource->addColumnControl("contact_subscription_id_display", "readonly", true);
		$this->iDataSource->addColumnControl("contact_subscription_id_display", "form_label", "Contact Subscription ID");
		if (canAccessPageCode("CONTACTSUBSCRIPTIONMAINT")) {
			$this->iDataSource->addColumnControl("contact_subscription_id_display", "help_label", "Click to open Contact Subscription record");
		}

		$this->iDataSource->addColumnControl("notes", "form_label", "Recurring Payment Notes");
		$this->iDataSource->addColumnControl("notes", "help_label", "Notes for this recurring payment");
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "maximum_length", "60");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Business Name");
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "show_id_field", "true");
		$this->iDataSource->addColumnControl("contact_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("account_id", "empty_text", "[New Account]");
		$this->iDataSource->addColumnControl("account_id", "not_null", false);
		$this->iDataSource->addColumnControl("account_id", "get_choices", "getAccounts");
		$this->iDataSource->addColumnControl("account_id", "help_label", "If a code is shown in parentheses, this account is not using the DEFAULT merchant account.");
		$this->iDataSource->addColumnControl("account_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("account_number", "maximum_length", "20");
		$this->iDataSource->addColumnControl("account_number", "not_null", "true");
		$this->iDataSource->addColumnControl("account_number", "form_label", "Card Number");
		$this->iDataSource->addColumnControl("expiration_month", "data_type", "select");
		$this->iDataSource->addColumnControl("expiration_month", "choices", "return \$GLOBALS['gMonthArray']");
		$this->iDataSource->addColumnControl("expiration_month", "not_null", "true");
		$this->iDataSource->addColumnControl("expiration_month", "form_label", "Expiration Month");
		$this->iDataSource->addColumnControl("expiration_year", "data_type", "select");
		$this->iDataSource->addColumnControl("expiration_year", "choices", "return \$GLOBALS['gYearArray']");
		$this->iDataSource->addColumnControl("expiration_year", "not_null", "true");
		$this->iDataSource->addColumnControl("expiration_year", "form_label", "Expiration Year");
		$this->iDataSource->addColumnControl("card_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("card_code", "size", "5");
		$this->iDataSource->addColumnControl("card_code", "maximum_length", "5");
		$this->iDataSource->addColumnControl("card_code", "form_label", "Card Code");
		$this->iDataSource->addColumnControl("address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("address_1", "maximum_length", "60");
		$this->iDataSource->addColumnControl("address_1", "not_null", "false");
		$this->iDataSource->addColumnControl("address_1", "form_label", "Billing Street");
		$this->iDataSource->addColumnControl("postal_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("postal_code", "maximum_length", "5");
		$this->iDataSource->addColumnControl("postal_code", "not_null", "true");
		$this->iDataSource->addColumnControl("postal_code", "form_label", "Billing Zip");
		$this->iDataSource->addColumnControl("payment_method_id", "get_choices", "paymentMethodChoices");
		$this->iDataSource->addColumnControl("routing_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("routing_number", "maximum_length", "20");
		$this->iDataSource->addColumnControl("routing_number", "not_null", "true");
		$this->iDataSource->addColumnControl("routing_number", "form_label", "Routing Number");
		$this->iDataSource->addColumnControl("bank_account_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bank_account_number", "maximum_length", "20");
		$this->iDataSource->addColumnControl("bank_account_number", "not_null", "true");
		$this->iDataSource->addColumnControl("bank_account_number", "form_label", "Bank Account Number");

		$this->iDataSource->addColumnControl("contact_address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_city", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_postal_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_state", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_address_1", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_city", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_business_name", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_email_address", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_first_name", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_last_name", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_postal_code", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_state", "readonly", "true");
		$this->iDataSource->addColumnControl("contact_address_1", "form_label", "Address");
		$this->iDataSource->addColumnControl("contact_city", "form_label", "City");
		$this->iDataSource->addColumnControl("contact_business_name", "form_label", "Business Name");
		$this->iDataSource->addColumnControl("contact_email_address", "form_label", "Email");
		$this->iDataSource->addColumnControl("contact_first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("contact_last_name", "form_label", "Last Name");
		$this->iDataSource->addColumnControl("contact_postal_code", "form_label", "Postal Code");
		$this->iDataSource->addColumnControl("contact_state", "form_label", "State");

		$this->iDataSource->setFilterWhere("contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . ")");
		$recurringPaymentTypeId = getFieldFromId("recurring_payment_type_id", "recurring_payment_types", "units_between", "1", "interval_unit = 'month'");
		if (!empty($recurringPaymentTypeId)) {
			$this->iDataSource->addColumnControl("recurring_payment_type_id", "default_value", $recurringPaymentTypeId);
		}
	}

	function paymentMethodChoices($showInactive = false) {
		$paymentMethodChoices = array();
		$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
				"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
				"inactive = 0 and internal_use_only = 0 and client_id = ? and payment_method_type_id in " .
				"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
				"client_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$paymentMethodChoices[$row['payment_method_id']] = array("key_value" => $row['payment_method_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "data-payment_method_type_code" => $row['payment_method_type_code']);
			}
		}
		freeResult($resultSet);
		return $paymentMethodChoices;
	}

	function onLoadJavascript() {
		?>
		<script>
			$("#contact_subscription_id_display").click(function () {
				if (!empty($(this).val())) {
					window.open("/contact-subscription-maintenance?clear_filter=true&url_page=show&primary_id=" + $(this).val());
				}
			});
			$("#payment_method_id").change(function () {
				$(".payment-method-fields").hide();
				if (!empty($(this).val())) {
					const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
					$("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
				}
			});
			$("#account_id").change(function () {
				if (empty($(this).val())) {
					$("#new_account").show();
					$("#payment_method_id").val("");
					$("#edit_existing_account_wrapper").addClass("hidden");
				} else {
					$("#new_account").hide();
					$("#edit_existing_account_wrapper").removeClass("hidden");
				}
			});
			$("#contact_id").change(function () {
				$("#account_id").find("option[value!='']").remove();
				if (!empty($(this).val())) {
					loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info&contact_id=" + $("#contact_id").val(), function (returnArray) {
						if ("select_values" in returnArray && "account_id" in returnArray['select_values']) {
							for (const j in returnArray['select_values']['account_id']) {
								$("#account_id").append($("<option></option>").attr("value", returnArray['select_values']['account_id'][j]['key_value']).text(returnArray['select_values']['account_id'][j]['description']));
							}
						}
						for (const i in returnArray) {
							if ($("#" + i).length > 0) {
								$("#" + i).val(returnArray[i]);
							}
						}
					});
				}
			});
			$(document).on("click",".link-subscription",function() {
				$("#contact_subscription_id").val($(this).data("contact_subscription_id"));
                $("#contact_subscription_id_display").val($(this).data("contact_subscription_id"));
            });
			$("#first_name").add("#last_name").add("#address_1").add("#postal_code").focus(function () {
				if (empty($("#first_name").val())) {
					$("#first_name").val($("#contact_first_name").val());
				}
				if (empty($("#last_name").val())) {
					$("#last_name").val($("#contact_last_name").val());
				}
				if (empty($("#address_1").val())) {
					$("#address_1").val($("#contact_address_1").val());
				}
				if (empty($("#postal_code").val())) {
					$("#postal_code").val($("#contact_postal_code").val());
				}
			});
			<?php if (canAccessPageCode("ACCOUNTMAINT")) { ?>
			$(document).on("click", "#edit_existing_account", function () {
				window.open("/accountmaintenance.php?url_page=show&primary_id=" + $("#account_id").val());
			});
			<?php } ?>
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			function customActions(actionName) {
				if (actionName === "clearrequiresattention") {
					loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clearrequiresattention", function (returnArray) {
						document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
					});
					return true;
				}
				return false;
			}

			function afterGetRecord() {
				$("#account_id").trigger("change");
				if (empty($("#primary_id").val())) {
					$("#contact_id").trigger("change");
					$("#contact_id_selector").find("option[value!='']").remove();
				}
			}

			function beforeGetRecord() {
				$("#account_id").find("option[value!='']").remove();
				return true;
			}
		</script>
		<?php
	}

	function beforeSaveChanges() {
		$returnArray = array();
		$contactId = $_POST['contact_id'];
		$contactRow = Contact::getContact($contactId);
		if (empty($contactRow)) {
			$returnArray['error_message'] = "Contact not found";
			ajaxResponse($returnArray);
		}

		$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
		$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		$achECommerce = null;
		$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
		if (!empty($achMerchantAccount)) {
			$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
		}
		$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

		if (!$useECommerce) {
			$returnArray['error_message'] = "No merchant service account available.";
			ajaxResponse($returnArray);
		}
		if (empty($useECommerce) || !$useECommerce->hasCustomerDatabase()) {
			$returnArray['error_message'] = "No customer database for this merchant account.";
			ajaxResponse($returnArray);
		}
		$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $GLOBALS['gMerchantAccountId']);
		if (empty($merchantIdentifier)) {
			$success = $useECommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $_POST['first_name'],
					"last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "city" => $_POST['city'],
					"state" => $_POST['state'], "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address']));
			$response = $useECommerce->getResponse();
			if ($success) {
				$merchantIdentifier = $response['merchant_identifier'];
			}
		}
		if (empty($merchantIdentifier)) {
			$returnArray['error_message'] = "Unable to create the customer profile";
			ajaxResponse($returnArray);
		}
		if (empty($_POST['next_billing_date'])) {
			$_POST['next_billing_date'] = $_POST['start_date'];
		}
		$this->iDataSource->disableTransactions();
		$this->iDataSource->getDatabase()->startTransaction();
		if (empty($_POST['account_id'])) {
			if (empty($_POST['card_code'])) {
				$_POST['card_code'] = "SKIP_CARD_CODE";
			}
			$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
					"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
							$_POST['payment_method_id']));
			$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
			$_POST['account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['account_number']));

			if (!$isBankAccount) {
				$testOrderId = date("Z") + 60000;
				$paymentArray = array("amount" => "1.00", "order_number" => $testOrderId, "description" => "Test Transaction",
						"first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'],
						"address_1" => $_POST['address_1'], "city" => $contactRow['city'], "state" => $contactRow['state'],
						"postal_code" => $_POST['postal_code'], "country_id" => $contactRow['country_id'], "contact_id" => $contactId, "authorize_only" => true);
				$paymentArray['card_number'] = $_POST['account_number'];
				$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
				$paymentArray['card_code'] = $_POST['card_code'];

				$success = $useECommerce->authorizeCharge($paymentArray);
				$response = $useECommerce->getResponse();
				if ($success) {
					$paymentArray['transaction_identifier'] = $response['transaction_id'];
					$useECommerce->voidCharge($paymentArray);
				} else {
					$returnArray['error_message'] = "Test Authorization failed: " . $response['response_reason_text'];
					$this->iDataSource->getDatabase()->rollbackTransaction();
					ajaxResponse($returnArray);
				}
			}

			$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? 'bank_account_number' : 'account_number')], -4);
			$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
					"account_number,expiration_date,merchant_account_id) values (?,?,?,?,?, ?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
					$_POST['first_name'] . " " . $_POST['last_name'], "XXXX-" . substr($_POST[($isBankAccount ? 'bank_account_number' : 'account_number')], -4),
					(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))), $GLOBALS['gMerchantAccountId']);
			if (!empty($resultSet['sql_error'])) {
				$this->iDataSource->getDatabase()->rollbackTransaction();
				$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				ajaxResponse($returnArray);
			}
			$accountId = $resultSet['insert_id'];
			$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
					"first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'],
					"address_1" => $_POST['address_1'], "city" => $contactRow['city'],
					"state" => $contactRow['state'], "postal_code" => $_POST['postal_code'],
					"country_id" => $contactRow['country_id']);
			if ($isBankAccount) {
				$paymentArray['bank_routing_number'] = $_POST['routing_number'];
				$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
				$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
			} else {
				$paymentArray['card_number'] = $_POST['account_number'];
				$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
				if (empty($_POST['card_code'])) {
					$paymentArray['card_code'] = "SKIP_CARD_CODE";
				} else {
					$paymentArray['card_code'] = $_POST['card_code'];
				}
			}
			$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
			$response = $useECommerce->getResponse();
			if ($success) {
				$accountToken = $response['account_token'];
				$customerPaymentProfileId = $accountToken;
			} else {
				$this->iDataSource->getDatabase()->rollbackTransaction();
				$returnArray['error_message'] = "Unable to create account: " . $response['response_reason_text'] . ", " . $useECommerce->getErrorMessage();
				ajaxResponse($returnArray);
			}
		} else {
			$accountId = $_POST['account_id'];
			$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
		}
		$_POST['account_id'] = $accountId;
		ContactPayment::notifyCRM($contactId, true);
		$this->iDataSource->getDatabase()->commitTransaction();
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (canAccessPageCode("CONTACTMAINT") < _READWRITE) {
			return true;
		}
		$contactTable = new DataTable("contacts");
		$contactTable->setSaveOnlyPresent(true);
		$contactTable->saveRecord(array("name_values" => array("notes" => $nameValues['contact_notes']), "primary_id" => $nameValues['contact_id']));
		return true;
	}

	function afterGetRecord(&$returnArray) {
		if (empty($returnArray['contact_subscription_id']['data_value'])) {
			$contactSubscriptionMessage = "This recurring payment is not linked to a subscription.";
			$resultSet = executeQuery("select * from contact_subscriptions join subscriptions using (subscription_id) where contact_subscriptions.inactive = 0 and subscriptions.inactive = 0 " .
                "and contact_id = ? and contact_subscription_id not in (select contact_subscription_id from recurring_payments where recurring_payments.contact_subscription_id is not null)",$returnArray['contact_id']['data_value']);
			if ($resultSet['row_count'] == 0) {
				$contactSubscriptionMessage .= "<br>There are no contact subscriptions that aren't connected to recurring payments. If this recurring payment is for a subscription, it should probably be ended.";
			}
			while ($row = getNextRow($resultSet)) {
				$contactSubscriptionMessage .= "<br><a class='link-subscription' data-contact_subscription_id='" . $row['contact_subscription_id'] . "'>Link to " . htmlText($row['description']) . " started " . date("m/d/Y",strtotime($row['start_date'])) . "</a>";
			}
			$returnArray["contact_subscription_message"] = array("data_value"=>$contactSubscriptionMessage);
		}
		if (!array_key_exists("select_values", $returnArray)) {
			$returnArray['select_values'] = array();
		}
		$returnArray['select_values']['account_id'] = $this->getAccounts($returnArray['contact_id']['data_value'], $returnArray['account_id']['data_value']);

		$fieldArray = array("address_1", "city", "business_name", "email_address", "first_name", "last_name", "postal_code", "state");
		if (canAccessPageCode("CONTACTMAINT")) {
			$fieldArray[] = "notes";
		}
		if (!empty($returnArray['primary_id']['data_value'])) {
			$resultSet = executeQuery("select * from contacts where contact_id = ?", $returnArray['contact_id']['data_value']);
			if ($row = getNextRow($resultSet)) {
				foreach ($fieldArray as $fieldName) {
					$returnArray["contact_" . $fieldName] = array("data_value" => $row[$fieldName]);
				}
			}
		}

		$returnArray['contact_subscription_id_display'] = array("data_value" => $returnArray['contact_subscription_id']['data_value']);

		$returnArray['debug'] = true;
	}

	function internalCSS() {
		?>
		<style>
			.payment-method-fields {
				display: none;
			}

			#contact_subscription_message {
				color: rgb(200,0,0);
			}

			#contact_subscription_id_display {
				cursor: pointer;
			}

		</style>
		<?php
	}
}

$pageObject = new RecurringPaymentMaintenancePage("recurring_payments");
$pageObject->displayPage();
