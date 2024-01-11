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

$GLOBALS['gPageCode'] = "RECURRINGDONATIONMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	var $iSearchContactFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("designation_id", "amount", "first_name", "last_name", "business_name", "payment_method_id", "start_date", "next_billing_date", "end_date", "recurring_donation_type_id"));
			$filters = array();
			$filters['show_requires_attention'] = array("form_label" => "Show Requires Attention", "where" => "requires_attention = 1 and (end_date is null or end_date > current_date)", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['designation_requires_attention'] = array("form_label" => "Has Designation that Requires Attention", "where" => "designation_id in (select designation_id from designations where requires_attention = 1)", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$resultSet = executeQuery("select * from designation_groups where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['designation_group_' . $row['designation_group_id']] = array("form_label" => $row['description'], "where" => "designation_id in (select designation_id from designation_group_links where designation_group_id = " . $row['designation_group_id'] . ")", "data_type" => "tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("clearrequiresattention", "Clear Requires Attention");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "clearrequiresattention":
				executeQuery("update recurring_donations set requires_attention = 0 where contact_id in (select contact_id from contacts where client_id = ?)", $GLOBALS['gClientId']);
				echo jsonEncode(array());
				exit;
			case "get_contact_info":
				$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null", $_GET['contact_id']);
				$returnArray['select_values'] = array();
				while ($row = getNextRow($resultSet)) {
					$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
					if ($accountMerchantAccountId == $GLOBALS['gMerchantAccountId']) {
						$merchantAccountDescription = getFieldFromId("description", "merchant_accounts", "merchant_account_id", $accountMerchantAccountId);
						$description = (empty($merchantAccountDescription) ? "" : $merchantAccountDescription . " - ") . (empty($row['account_label']) ? $row['account_number'] : $row['account_label'] . ", " . $row['account_number']);
						if (empty($description)) {
							$description = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
						}
						$returnArray['select_values'][] = array("key_value" => $row['account_id'], "description" => $description);
					}
				}
				$contactFields = Contact::getMultipleContactFields($_GET['contact_id'],array("first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address"));
				foreach ($contactFields as $fieldName => $fieldData) {
					$returnArray['contact_' . $fieldName] = $fieldData;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
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
		$this->iDataSource->addColumnControl("error_message", "readonly", true);
		$this->iDataSource->addColumnControl("last_attempted", "readonly", true);
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = recurring_donations.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "maximum_length", "25");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("first_name", "not_null", true);
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = recurring_donations.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "maximum_length", "35");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last Name");
		$this->iDataSource->addColumnControl("last_name", "not_null", true);

		$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts where contact_id = recurring_donations.contact_id");
		$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_address", "form_label", "Email");
		$this->iDataSource->addColumnControl("phone_number", "select_value", "select phone_number from phone_numbers where contact_id = recurring_donations.contact_id limit 1");
		$this->iDataSource->addColumnControl("phone_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("phone_number", "form_label", "Phone");

		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "maximum_length", "60");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Business Name");
		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "show_id_field", true);
		$this->iDataSource->addColumnControl("contact_id", "not_editable", true);
		$this->iDataSource->addColumnControl("account_id", "empty_text", "[New Account]");
		$this->iDataSource->addColumnControl("account_id", "get_choices", "getAccounts");
		$this->iDataSource->addColumnControl("account_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("account_number", "maximum_length", "20");
		$this->iDataSource->addColumnControl("account_number", "not_null", true);
		$this->iDataSource->addColumnControl("account_number", "form_label", "Card Number");
		$this->iDataSource->addColumnControl("expiration_month", "data_type", "select");
		$this->iDataSource->addColumnControl("expiration_month", "choices", "return \$GLOBALS['gMonthArray']");
		$this->iDataSource->addColumnControl("expiration_month", "not_null", true);
		$this->iDataSource->addColumnControl("expiration_month", "form_label", "Expiration Month");
		$this->iDataSource->addColumnControl("expiration_year", "data_type", "select");
		$this->iDataSource->addColumnControl("expiration_year", "choices", "return \$GLOBALS['gYearArray']");
		$this->iDataSource->addColumnControl("expiration_year", "not_null", true);
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
		$this->iDataSource->addColumnControl("postal_code", "not_null", true);
		$this->iDataSource->addColumnControl("postal_code", "form_label", "Billing Zip");
		$this->iDataSource->addColumnControl("payment_method_id", "get_choices", "paymentMethodChoices");
		$this->iDataSource->addColumnControl("routing_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("routing_number", "maximum_length", "20");
		$this->iDataSource->addColumnControl("routing_number", "not_null", true);
		$this->iDataSource->addColumnControl("routing_number", "form_label", "Routing Number");
		$this->iDataSource->addColumnControl("bank_account_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("bank_account_number", "maximum_length", "20");
		$this->iDataSource->addColumnControl("bank_account_number", "not_null", true);
		$this->iDataSource->addColumnControl("bank_account_number", "form_label", "Bank Account Number");

		$this->iDataSource->addColumnControl("contact_address_1", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_city", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_postal_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_state", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_phone_number", "data_type", "varchar");
		$this->iDataSource->addColumnControl("contact_address_1", "readonly", true);
		$this->iDataSource->addColumnControl("contact_city", "readonly", true);
		$this->iDataSource->addColumnControl("contact_business_name", "readonly", true);
		$this->iDataSource->addColumnControl("contact_email_address", "readonly", true);
		$this->iDataSource->addColumnControl("contact_first_name", "readonly", true);
		$this->iDataSource->addColumnControl("contact_last_name", "readonly", true);
		$this->iDataSource->addColumnControl("contact_postal_code", "readonly", true);
		$this->iDataSource->addColumnControl("contact_state", "readonly", true);
		$this->iDataSource->addColumnControl("contact_phone_number", "readonly", true);
		$this->iDataSource->addColumnControl("contact_address_1", "form_label", "Address");
		$this->iDataSource->addColumnControl("contact_city", "form_label", "City");
		$this->iDataSource->addColumnControl("contact_business_name", "form_label", "Business Name");
		$this->iDataSource->addColumnControl("contact_email_address", "form_label", "Email");
		$this->iDataSource->addColumnControl("contact_first_name", "form_label", "First Name");
		$this->iDataSource->addColumnControl("contact_last_name", "form_label", "Last Name");
		$this->iDataSource->addColumnControl("contact_postal_code", "form_label", "Postal Code");
		$this->iDataSource->addColumnControl("contact_state", "form_label", "State");
		$this->iDataSource->addColumnControl("contact_phone_number", "form_label", "Phone");

		$this->iDataSource->setFilterWhere("contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . ")");
		$recurringDonationTypeId = getFieldFromId("recurring_donation_type_id", "recurring_donation_types", "units_between", "1", "interval_unit = 'month' and manual_processing = 0");
		if (!empty($recurringDonationTypeId)) {
			$this->iDataSource->addColumnControl("recurring_donation_type_id", "default_value", $recurringDonationTypeId);
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "contact_id in (select contact_id from contacts where (first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			} else if (is_numeric($filterText)) {
				$whereStatement = "contact_id = " . $filterText . " or recurring_donation_id = " . $filterText;
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
				$parts = explode(" ", $filterText);
				$whereStatement = "";
				if (count($parts) == 2) {
					$whereStatement = "(contact_id in (select contact_id from contacts where first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
						" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . "))";
				}
				foreach ($this->iSearchContactFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contacts where " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . ")";
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contact_identifiers where identifier_value = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$this->iDataSource->addFilterWhere($whereStatement);
			}
		}
	}

	function paymentMethodChoices($showInactive = false) {
		$paymentMethodChoices = array();
		$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
			"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods " .
			"where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
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
            $(document).on("tap click", "#_activity_button", function () {
                loadAjaxRequest("/useractivitylog.php?ajax=true&contact_id=" + $("#contact_id").val(), function(returnArray) {
                    if ("activity_log" in returnArray) {
                        $("#_activity_log").html(returnArray['activity_log']);
                    } else {
                        $("#_activity_log").html("<p>No Activity Found</p>");
                    }
                    $('#_activity_log').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'User Activity',
                        buttons: {
                            Close: function (event) {
                                $("#_activity_log").dialog('close');
                            }
                        }
                    });
                });
                return false;
            });
            $("designation_id").change(function () {
                $("#account_id").find("option[value!='']").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info&contact_id=" + $("#contact_id").val() + "&designation_id=" + $("#designation_id").val(), function(returnArray) {
                    if ("select_values" in returnArray) {
                        for (var i in returnArray['select_values']) {
                            $("#account_id").append($("<option></option>").attr("value", returnArray['select_values'][i]['key_value']).text(returnArray['select_values'][i]['description']));
                        }
                    }
                    for (var i in returnArray) {
                        if ($("#" + i).length > 0) {
                            $("#" + i).val(returnArray[i]);
                        }
                    }
                });
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            });
            $("#account_id").change(function () {
                if (empty($(this).val())) {
                    $("#new_account").show();
                    $("#payment_method_id").val("");
                } else {
                    $("#new_account").hide();
                }
            });
            $("#contact_id").change(function () {
                $("#account_id").find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info&contact_id=" + $("#contact_id").val() + "&designation_id=" + $("#designation_id").val(), function(returnArray) {
                        if ("select_values" in returnArray) {
                            for (var i in returnArray['select_values']) {
                                $("#account_id").append($("<option></option>").attr("value", returnArray['select_values'][i]['key_value']).text(returnArray['select_values'][i]['description']));
                            }
                        }
                        for (var i in returnArray) {
                            if ($("#" + i).length > 0) {
                                $("#" + i).val(returnArray[i]);
                            }
                        }
                    });
                }
            });
            $("#first_name").add("#last_name").add("#address_1").add("#postal_code").focus(function () {
                if ($("#first_name").val() == "") {
                    $("#first_name").val($("#contact_first_name").val());
                }
                if ($("#last_name").val() == "") {
                    $("#last_name").val($("#contact_last_name").val());
                }
                if ($("#address_1").val() == "") {
                    $("#address_1").val($("#contact_address_1").val());
                }
                if ($("#postal_code").val() == "") {
                    $("#postal_code").val($("#contact_postal_code").val());
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "clearrequiresattention") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clearrequiresattention", function(returnArray) {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    });
                    return true;
                }
                return false;
            }
            function afterGetRecord() {
                $("#account_id").trigger("change");
                if ($("#primary_id").val() == "") {
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

	function saveChanges() {
		$returnArray = array();
		$contactId = $_POST['contact_id'];
		$contactRow = Contact::getContact($contactId);
		if (empty($contactRow)) {
			$returnArray['error_message'] = "Contact not found";
			ajaxResponse($returnArray);
		}
		$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		if (!$eCommerce) {
			$returnArray['error_message'] = "No merchant service account available.";
			ajaxResponse($returnArray);
		}
		if (empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
			$returnArray['error_message'] = "No customer database for this merchant account.";
			ajaxResponse($returnArray);
		}
		$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
		if (empty($merchantIdentifier)) {
			$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $_POST['first_name'],
				"last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "city" => $_POST['city'],
				"state" => $_POST['state'], "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address']));
			$response = $eCommerce->getResponse();
			if ($success) {
				$merchantIdentifier = $response['merchant_identifier'];
			}
		}
		if (empty($merchantIdentifier)) {
			$returnArray['error_message'] = "Unable to create the customer profile";
			ajaxResponse($returnArray);
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
			$isMerchantAccount = false;
			if ($_POST['payment_method_type_code'] == "BANK_ACCOUNT" || $_POST['payment_method_type_code'] == "CREDIT_CARD") {
				$isMerchantAccount = true;
			}
			if ($isMerchantAccount) {
				$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
				$_POST['account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['account_number']));

				if (!$isBankAccount) {
					$testOrderId = date("Z") + 60000;
					$paymentArray = array("amount" => "1.00", "order_number" => $testOrderId, "description" => "Test Transaction", "authorize_only" => true,
						"first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'],
						"address_1" => $_POST['address_1'], "city" => $contactRow['city'], "state" => $contactRow['state'],
						"postal_code" => $_POST['postal_code'], "country_id" => $contactRow['country_id'], "contact_id" => $contactId);
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['card_code'];
					$success = $eCommerce->authorizeCharge($paymentArray);
					$response = $eCommerce->getResponse();
					if ($success) {
						$paymentArray['transaction_identifier'] = $response['transaction_id'];
						$eCommerce->voidCharge($paymentArray);
					} else {
						$returnArray['error_message'] = "Test Authorization failed: " . $response['response_reason_text'];
						$this->iDataSource->getDatabase()->rollbackTransaction();
						ajaxResponse($returnArray);
					}
				}
			}

			$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? 'bank_account_number' : 'account_number')], -4);
			$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
				"account_number,expiration_date,merchant_account_id) values (?,?,?,?,?, ?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
				$_POST['first_name'] . " " . $_POST['last_name'], "XXXX-" . substr($_POST[($isBankAccount ? 'bank_account_number' : 'account_number')], -4),
				(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_year'] . "-" . $_POST['expiration_month'] . "-01"))), $merchantAccountId);
			if (!empty($resultSet['sql_error'])) {
				$this->iDataSource->getDatabase()->rollbackTransaction();
				$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				ajaxResponse($returnArray);
			}
			$accountId = $resultSet['insert_id'];
			if ($isMerchantAccount) {
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
				$success = $eCommerce->createCustomerPaymentProfile($paymentArray);
				$response = $eCommerce->getResponse();
				if ($success) {
					$accountToken = $response['account_token'];
					$customerPaymentProfileId = $accountToken;
				} else {
					$this->iDataSource->getDatabase()->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create account: " . $response['response_reason_text'] . ", " . $eCommerce->getErrorMessage();
					ajaxResponse($returnArray);
				}
			}
		} else {
			$accountId = $_POST['account_id'];
			$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
		}
		$_POST['account_id'] = $accountId;
		$nameValues = array();
		if (empty($_POST['primary_id'])) {
			$nameValues['contact_id'] = $_POST['contact_id'];
		}
		$nameValues['next_billing_date'] = (empty($_POST['next_billing_date']) ? $_POST['start_date'] : $_POST['next_billing_date']);
		$nameValues['recurring_donation_type_id'] = $_POST['recurring_donation_type_id'];
		$nameValues['amount'] = $_POST['amount'];
		$nameValues['payment_method_id'] = $_POST['payment_method_id'];
		$nameValues['start_date'] = $_POST['start_date'];
		$nameValues['end_date'] = $_POST['end_date'];
		$nameValues['designation_id'] = $_POST['designation_id'];
		$nameValues['anonymous_gift'] = $_POST['anonymous_gift'];
		$nameValues['account_id'] = $_POST['account_id'];
		$nameValues['requires_attention'] = $_POST['requires_attention'];
		$nameValues['notes'] = $_POST['notes'];
		$this->iDataSource->setSaveOnlyPresent(true);
		if (!$primaryId = $this->iDataSource->saveRecord(array("name_values" => $nameValues, "primary_id" => $_POST['primary_id']))) {
			$returnArray['error_message'] = $this->iDataSource->getErrorMessage();
			$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
			$this->iDataSource->getDatabase()->rollbackTransaction();
			ajaxResponse($returnArray);
		}

		$this->iDataSource->getDatabase()->commitTransaction();
		$returnArray['info_message'] = "Recurring Donation created";
		ajaxResponse($returnArray);
	}

	function afterGetRecord(&$returnArray) {
		if (!array_key_exists("select_values", $returnArray)) {
			$returnArray['select_values'] = array();
		}
		$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		$returnArray['select_values']['account_id'] = array();

		$resultSet = executeQuery("select * from accounts where contact_id = ? and ((inactive = 0 and account_token is not null) || account_id = ?)",
			$returnArray['contact_id']['data_value'], $returnArray['account_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
			if ($accountMerchantAccountId == $merchantAccountId) {
				$merchantAccountDescription = getFieldFromId("description", "merchant_accounts", "merchant_account_id", $accountMerchantAccountId);
				$description = (empty($merchantAccountDescription) ? "" : $merchantAccountDescription . " - ") . (empty($row['account_label']) ? $row['account_number'] : $row['account_label'] . ", " . $row['account_number']);
				if (empty($description)) {
					$description = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
				}
				$returnArray['select_values']['account_id'][] = array("key_value" => $row['account_id'], "description" => $description);
			}
		}
		$fieldArray = array("address_1", "city", "business_name", "email_address", "first_name", "last_name", "postal_code", "state");
		if (!empty($returnArray['primary_id']['data_value'])) {
			$resultSet = executeQuery("select * from contacts where contact_id = ?", $returnArray['contact_id']['data_value']);
			if ($row = getNextRow($resultSet)) {
				foreach ($fieldArray as $fieldName) {
					$returnArray["contact_" . $fieldName] = array("data_value" => $row[$fieldName]);
				}
			}
			$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $returnArray['contact_id']['data_value']);
			if ($row = getNextRow($resultSet)) {
				$returnArray["contact_phone_number"] = array("data_value" => $row['phone_number']);
			}
		}
	}

	function getAccounts($showInactive = false) {
		return array();
	}

	function internalCSS() {
		?>
        <style>
            .payment-method-fields {
                display: none;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_activity_log" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new ThisPage("recurring_donations");
$pageObject->displayPage();
