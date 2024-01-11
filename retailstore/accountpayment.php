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

$GLOBALS['gPageCode'] = "ACCOUNTPAYMENT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_payment":
				$chargeAccountRow = getRowFromId("accounts", "account_id", $_POST['charge_account_id'], "contact_id = ? and payment_method_id in (select payment_method_id from payment_methods where " .
					"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CHARGE_ACCOUNT'))", $GLOBALS['gUserRow']['contact_id']);
				if (empty($chargeAccountRow)) {
					$returnArray['error_message'] = getLanguageText("Invalid Account");
					ajaxResponse($returnArray);
					break;
				}
				$charges = 0;
				$payments = 0;
				$resultSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) from order_shipments where account_id = ?", $chargeAccountRow['account_id']);
				if ($row = getNextRow($resultSet)) {
					$charges = $row['sum(amount + shipping_charge + tax_charge + handling_charge)'];
				}
				$resultSet = executeQuery("select sum(amount) from account_payments where account_id = ?", $chargeAccountRow['account_id']);
				if ($row = getNextRow($resultSet)) {
					$payments = $row['sum(amount)'];
				}
				$balance = $charges - $payments + ($GLOBALS['gDevelopmentServer'] ? 50 : 0);
				if ($balance <= 0) {
					$returnArray['error_message'] = getLanguageText("No balance on this account");
					ajaxResponse($returnArray);
					break;
				}
				if ($_POST['amount'] <= 0) {
					$returnArray['error_message'] = getLanguageText("Invalid Payment Amount");
					ajaxResponse($returnArray);
					break;
				}
				if ($_POST['amount'] > $balance) {
					$_POST['amount'] = $balance;
				}
				$this->iDatabase->startTransaction();
				if ($_POST['same_address']) {
					$fields = array("first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "country_id");
					foreach ($fields as $fieldName) {
						$_POST['billing_' . $fieldName] = $GLOBALS['gUserRow'][$fieldName];
					}
				}
				$isBankAccount = (!empty($_POST['bank_account_number']));

				$merchantAccountId = $GLOBALS['gMerchantAccountId'];
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
				if (!empty($achMerchantAccount)) {
					$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
				}
				$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

				if (!$useECommerce) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getLanguageText("Unable to connect to Merchant Services. Please contact customer service. #9572");
					ajaxResponse($returnArray);
					break;
				}

# Strip spaces and dashes from account numbers

				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$_POST['account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['account_number']));
				$_POST['bank_account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['bank_account_number']));

# If the user is logged in, get or create a customer profile

				$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId,
					"merchant_account_id = ?", ($isBankAccount ? $achMerchantAccount : $merchantAccountId));
				if (empty($merchantIdentifier) && !empty($useECommerce) && $useECommerce->hasCustomerDatabase()) {
					$success = $useECommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $_POST['first_name'],
						"last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "city" => $_POST['city'],
						"state" => $_POST['state'], "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address']));
					$response = $useECommerce->getResponse();
					if ($success) {
						$merchantIdentifier = $response['merchant_identifier'];
					}
				}
				if (empty($merchantIdentifier) && !empty($_POST['account_id'])) {
					$returnArray['error_message'] = getLanguageText("There is a problem using an existing payment method. Please create a new one. #128");
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

# if new account, create it

				if (empty($_POST['account_id'])) {
					$accountLabel = $_POST['account_label'];
					if (empty($accountLabel)) {
						$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
					}
					$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['billing_business_name']);
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number,expiration_date,merchant_account_id,inactive) values (?,?,?,?,?, ?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
						$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
						(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_year'] . "-" . $_POST['expiration_month'] . "-01"))), $merchantAccountId, ($_POST['save_account'] ? 0 : 1));
					if (!empty($resultSet['sql_error'])) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$accountId = $resultSet['insert_id'];
				} else {
					$accountId = getFieldFromId("account_id", "accounts", "account_id", $_POST['account_id'], "contact_id = ?", $contactId);
					$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
				}
				$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
				$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
				if (empty($accountToken) && !empty($_POST['account_id'])) {
					$returnArray['error_message'] = getLanguageText("There is a problem using an existing payment method. Please create a new one. #7851");
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountId);
				if ($accountMerchantAccountId != $merchantAccountId) {
					$returnArray['error_message'] = getLanguageText("There is a problem with this account. #8352");
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

# Create payment record if it is not recurring

				if (!empty($accountId) && empty($_POST['payment_method_id'])) {
					$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
				}
				$resultSet = executeQuery("insert into account_payments (account_id,payment_date,payment_method_id,payment_account_id,amount) values (?,now(),?,?,?)",
					$chargeAccountRow['account_id'], $_POST['payment_method_id'], $accountId, $_POST['amount']);
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$accountPaymentId = $resultSet['insert_id'];

# if the user is asking to save account, make sure the account exists

				if ($_POST['save_account'] && empty($accountToken)) {
					$resultSet = executeQuery("select * from accounts where contact_id = ? and account_token is not null and account_number like ? and payment_method_id = ?",
						$contactId, "%" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4), $_POST['payment_method_id']);
					$foundAccount = false;
					while ($row = getNextRow($resultSet)) {
						$thisMerchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
						if ($thisMerchantAccountId == $merchantAccountId) {
							$foundAccount = true;
							break;
						}
					}
					if ($foundAccount) {
						$_POST['save_account'] = "";
					}
				}

# if the user is asking to save account, make sure the account exists

				if ($_POST['save_account'] && empty($accountToken) && !empty($useECommerce) && $useECommerce->hasCustomerDatabase()) {
					$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
						"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'],
						"business_name" => $_POST['billing_business_name'], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id']);
					if ($isBankAccount) {
						$paymentArray['bank_routing_number'] = $_POST['routing_number'];
						$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
						$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
					} else {
						$paymentArray['card_number'] = $_POST['account_number'];
						$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
						$paymentArray['card_code'] = $_POST['cvv_code'];
					}
					$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
					$response = $useECommerce->getResponse();
					if ($success) {
						$customerPaymentProfileId = $accountToken = $response['account_token'];
						$accountMerchantIdentifier = $merchantIdentifier;
					} else {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = getLanguageText("Unable to create payment account. Do you already have this payment method saved?");
						ajaxResponse($returnArray);
						break;
					}
				}

# If creating the account didn't work, exit with error.

				if (empty($accountToken) && empty($_POST['account_number']) && empty($_POST['bank_account_number'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getLanguageText("Unable to charge account. Please contact customer service. #5923");
					ajaxResponse($returnArray);
					break;
				}

# Charge the card.

				if (empty($accountToken)) {
					$paymentArray = array("amount" => $_POST['amount'], "order_number" => $accountPaymentId, "description" => "Account Payment",
						"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'],
						"business_name" => $_POST['billing_business_name'], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
						"email_address" => $GLOBALS['gUserRow']['email_address'], "contact_id" => $contactId);
					if ($isBankAccount) {
						$paymentArray['bank_routing_number'] = $_POST['routing_number'];
						$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
						$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
					} else {
						$paymentArray['card_number'] = $_POST['account_number'];
						$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
						$paymentArray['card_code'] = $_POST['cvv_code'];
					}
					$success = $useECommerce->authorizeCharge($paymentArray);
					$response = $useECommerce->getResponse();
					if ($success) {
						executeQuery("update account_payments set transaction_identifier = ?,authorization_code = ? where account_payment_id = ?",
							$response['transaction_id'], $response['authorization_code'], $accountPaymentId);
					} else {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
						$useECommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
						ajaxResponse($returnArray);
						break;
					}
				} else if (!empty($useECommerce) && $useECommerce->hasCustomerDatabase()) {
					$addressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
					$success = $useECommerce->createCustomerProfileTransactionRequest(array("amount" => $_POST['amount'], "order_number" => $accountPaymentId, "address_id" => $addressId,
						"merchant_identifier" => (empty($accountMerchantIdentifier) ? $merchantIdentifier : $accountMerchantIdentifier), "account_token" => $accountToken));
					$response = $useECommerce->getResponse();
					if ($success) {
						executeQuery("update account_payments set transaction_identifier = ?,authorization_code = ? where account_payment_id = ?",
							$response['transaction_id'], $response['authorization_code'], $accountPaymentId);
					} else {
						if (!empty($customerPaymentProfileId)) {
							$useECommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
						}
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
						echo jsonEncode($returnArray);
						$useECommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
						exit;
					}
				}

				$contactRow = $GLOBALS['gUserRow'];
				$substitutions = $contactRow;
				if (empty($substitutions['salutation'])) {
					$substitutions['salutation'] = generateSalutation($contactRow);
				}
				$substitutions['full_name'] = getDisplayName($contactRow['contact_id']);
				$substitutions['amount'] = $_POST['amount'];
				$substitutions['payment_amount'] = $_POST['amount'];
				$substitutions['account_label'] = (empty($chargeAccountRow['account_label']) ? getFieldFromId("description", "payment_methods", "payment_method_id", $chargeAccountRow['payment_method_id']) : $chargeAccountRow['account_label']);
				$addressBlock = $substitutions['full_name'];
				if (!empty($substitutions['address_1'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['address_1'];
				}
				if (!empty($substitutions['address_2'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['address_2'];
				}
				if (!empty($substitutions['city'])) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . $substitutions['city'];
				}
				if (!empty($substitutions['state'])) {
					$addressBlock .= (empty($addressBlock) ? "" : ", ") . $substitutions['state'];
				}
				if (!empty($substitutions['postal_code'])) {
					$addressBlock .= (empty($addressBlock) ? "" : " ") . $substitutions['postal_code'];
				}
				if (!empty($substitutions['country_id']) && $substitutions['country_id'] != 1000) {
					$addressBlock .= (empty($addressBlock) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $substitutions['country_id']);
				}
				$substitutions['address_block'] = $addressBlock;

# process payment receipt

				$this->iDatabase->commitTransaction();

				$emailId = getFieldFromId("email_id", "emails", "email_code", "PAYMENT_ERECEIPT", "inactive = 0");
				if (!empty($emailId)) {
					sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $GLOBALS['gUserRow']['email_address'], "contact_id" => $GLOBALS['gUserRow']['contact_id']));
				}
				$emailId = getFieldFromId("email_id", "emails", "email_code", "PAYMENT_NOTIFICATION", "inactive = 0");
				if (!empty($emailId)) {
					sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "notification_code" => "PAYMENT_NOTIFICATION"));
				} else {
					$body = "A payment of %amount% was received from %full_name%.";
					sendEmail(array("subject" => "Payment received", "body" => $body, "substitutions" => $substitutions, "notification_code" => "PAYMENT_NOTIFICATION"));
				}

				$responseFragment = $this->getFragment("PAYMENT_RECEIVED");
				if (empty($responseFragment)) {
					$responseFragment = "Your payment of %amount% has been received.";
				}
                $responseFragment = PlaceHolders::massageContent($responseFragment, $substitutions);
				$returnArray['response'] = $responseFragment;
				ajaxResponse($returnArray);
				break;
			case "get_balance":
				$accountId = getFieldFromId("account_id", "accounts", "account_id", $_GET['account_id'], "contact_id = ? and payment_method_id in (select payment_method_id from payment_methods where " .
					"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CHARGE_ACCOUNT'))", $GLOBALS['gUserRow']['contact_id']);
				if (empty($accountId)) {
					$returnArray['error_message'] = "Invalid Account";
					ajaxResponse($returnArray);
					break;
				}
				$charges = 0;
				$payments = 0;
				$resultSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) from order_shipments where account_id = ?", $accountId);
				if ($row = getNextRow($resultSet)) {
					$charges = $row['sum(amount + shipping_charge + tax_charge + handling_charge)'];
				}
				$resultSet = executeQuery("select sum(amount) from account_payments where account_id = ?", $accountId);
				if ($row = getNextRow($resultSet)) {
					$payments = $row['sum(amount)'];
				}
				$balance = $charges - $payments;
				$returnArray['account_balance'] = "Current balance is $" . ($balance <= 0 ? "0.00" : number_format($balance, 2, ".", ","));
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#submit_form").click(function () {
                $("#submit_paragraph").addClass("hidden");
                $("#processing_payment").removeClass("hidden");
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_payment", $("#_edit_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                            return;
                        }
                        if ("response" in returnArray) {
                            $("#form_wrapper").html(returnArray['response']);
                        } else {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                        }
                    });
                }
                return false;
            });
            $("#billing_country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            }).trigger("change");
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $("#same_address").click(function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                } else {
                    $("#_billing_address").removeClass("hidden");
                }
            });
            $("#account_id").change(function () {
                if (!empty($(this).val())) {
                    $("#_new_account").hide();
                } else {
                    $("#_new_account").show();
                }
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            }).trigger("change");
            $("#charge_account_id").change(function () {
                $("#account_balance").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_balance&account_id=" + $(this).val(), function(returnArray) {
                        if ("account_balance" in returnArray) {
                            $("#account_balance").html(returnArray['account_balance']);
                        }
                    });
                }
            }).trigger("change");
        </script>
		<?php
	}

	function mainContent() {
		if (!$GLOBALS['gDevelopmentServer']) {
			$eCommerce = eCommerce::getEcommerceInstance();
		} else {
			$eCommerce = false;
		}
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and payment_method_id in (select payment_method_id from payment_methods where " .
			"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CHARGE_ACCOUNT'))", $GLOBALS['gUserRow']['contact_id']);
		?>
        <div id="form_wrapper">
            <form id="_edit_form">
                <div class="form-line" id="_charge_account_id_row">
                    <label for="charge_account_id" class="">Make payment to account</label>
                    <select tabindex="10" id="charge_account_id" name="charge_account_id" class="validate[required]">
						<?php if ($resultSet['row_count'] > 1) { ?>
                            <option value="">[Select]</option>
						<?php } ?>
						<?php
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['account_id'] ?>"<?= ($resultSet['row_count'] == 1 ? " selected" : "") ?>><?= htmlText((empty($row['account_label']) ? $row['account_number'] : $row['account_label'])) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <p id="account_balance"></p>

                <div class="form-line" id="_amount_row">
                    <label for="amount" class="required-label">Amount of payment</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="validate[required,custom[number],min[0]] align-right" id="amount" name="amount" placeholder="Amount (USD)" data-decimal-places="2" value="<?= (is_numeric($_GET['amount']) && !empty($_GET['amount']) ? number_format($_GET['amount'], 2, ".", "") : "") ?>">
                    <div class='clear-div'></div>
                </div>

                <h2>Payment Information</h2>

				<?php
				$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null and payment_method_id in (select payment_method_id from payment_methods where " .
					"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT')))", $GLOBALS['gUserRow']['contact_id']);
				if ($resultSet['row_count'] == 0 || empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
					?>
                    <input type="hidden" id="account_id" name="account_id" value="">
					<?php
				} else {
					?>
                    <div class="form-line" id="_account_id_row">
                        <label for="account_id" class="">Select Payment Account</label>
                        <select tabindex="10" id="account_id" name="account_id">
                            <option value="">[New Account]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['account_id'] ?>"><?= htmlText((empty($row['account_label']) ? $row['account_number'] : $row['account_label'])) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>
				<?php } ?>

                <div id="_new_account">

                    <div class="form-line checkbox-input" id="_same_address_row">
                        <label class=""></label>
                        <input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" value="1"><label class="checkbox-label" for="same_address">Billing address is same as primary address</label>
                        <div class='clear-div'></div>
                    </div>

                    <div id="_billing_address" class="hidden">

                        <div class="form-line" id="_billing_first_name_row">
                            <label for="billing_first_name" class="required-label">First Name</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_last_name_row">
                            <label for="billing_last_name" class="required-label">Last Name</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_business_name_row">
                            <label for="billing_business_name">Business Name</label>
                            <input tabindex="10" type="text" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_address_1_row">
                            <label for="billing_address_1" class="required-label">Street</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_address_2_row">
                            <label for="billing_address_2" class=""></label>
                            <input tabindex="10" type="text" class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_city_row">
                            <label for="billing_city" class="required-label">City</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_state_row">
                            <label for="billing_state" class="">State</label>
                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_state_select_row">
                            <label for="billing_state_select" class="">State</label>
                            <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#billing_country_id').val() == 1000">
                                <option value="">[Select]</option>
								<?php
								foreach (getStateArray() as $stateCode => $state) {
									?>
                                    <option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_postal_code_row">
                            <label for="billing_postal_code" class="">Postal Code</label>
                            <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_country_id_row">
                            <label for="billing_country_id" class="">Country</label>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
								<?php
								foreach (getCountryArray(true) as $countryId => $countryName) {
									?>
                                    <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>
                    </div> <!-- billing_address -->

                    <div id="payment_information">
                        <div class="form-line" id="_payment_method_id_row">
                            <label for="payment_method_id" class="">Payment Method</label>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
                                <option value="">[Select]</option>
								<?php
								$paymentLogos = array();
								$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
									"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
									"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
									(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
									"inactive = 0 and internal_use_only = 0 and client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
									"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
									"client_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									if (empty($row['image_id'])) {
										$paymentMethodRow = getRowFromId("payment_methods", "payment_method_code", $row['payment_method_code'], "client_id = ?", $GLOBALS['gDefaultClientId']);
										$row['image_id'] = $paymentMethodRow['image_id'];
									}
									if (!empty($row['image_id'])) {
										$paymentLogos[$row['payment_method_id']] = $row['image_id'];
									}
									?>
                                    <option value="<?= $row['payment_method_id'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="payment-method-fields" id="payment_method_credit_card">
                            <div class="form-line" id="_account_number_row">
                                <label for="account_number" class="">Card Number</label>
                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
                                <div id="payment_logos">
									<?php
									foreach ($paymentLogos as $paymentMethodId => $imageId) {
										?>
                                        <img alt="Payment Logo" id="payment_method_logo_<?= strtolower($paymentMethodId) ?>" class="payment-method-logo" src="<?= getImageFilename($imageId, array("use_cdn" => true)) ?>">
										<?php
									}
									?>
                                </div>
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line" id="_expiration_month_row">
                                <label for="expiration_month" class="">Expiration Date</label>
                                <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_month" name="expiration_month">
                                    <option value="">[Month]</option>
									<?php
									for ($x = 1; $x <= 12; $x++) {
										?>
                                        <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
										<?php
									}
									?>
                                </select>
                                <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_year" name="expiration_year">
                                    <option value="">[Year]</option>
									<?php
									for ($x = 0; $x < 12; $x++) {
										$year = date("Y") + $x;
										?>
                                        <option value="<?= $year ?>"><?= $year ?></option>
										<?php
									}
									?>
                                </select>
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line" id="_cvv_code_row">
                                <label for="cvv_code" class="">Security Code</label>
                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
                                <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
                                <div class='clear-div'></div>
                            </div>
                        </div> <!-- payment_method_credit_card -->

                        <div class="payment-method-fields" id="payment_method_bank_account">
                            <div class="form-line" id="_routing_number_row">
                                <label for="routing_number" class="">Bank Routing Number</label>
                                <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line" id="_bank_account_number_row">
                                <label for="bank_account_number" class="">Account Number</label>
                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                                <div class='clear-div'></div>
                            </div>
                        </div> <!-- payment_method_bank_account -->

						<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
                            <div class="form-line checkbox-input" id="_save_account_row">
                                <label class=""></label>
                                <input tabindex="10" type="checkbox" id="save_account" name="save_account" value="1"><label class="checkbox-label" for="save_account">Save Account</label>
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line" id="_account_label_row">
                                <label for="account_label" class="">Account Nickname</label>
                                <span class="help-label">for future reference, if saved</span>
                                <input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                                <div class='clear-div'></div>
                            </div>
						<?php } ?>

                    </div> <!-- payment_information -->
                </div> <!-- new_account -->
            </form>

            <p id="processing_payment" class="hidden">Payment being processed. Do not close window.</p>
            <p id="_submit_paragraph">
                <button tabindex="10" id="submit_form">Submit</button>
            </p>
        </div> <!-- form_wrapper -->
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
