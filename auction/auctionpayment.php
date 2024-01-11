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

$GLOBALS['gPageCode'] = "AUCTIONPAYMENT";
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class AuctionPaymentPage extends Page {

	function executePageUrlActions() {
		switch ($_GET['url_action']) {
			case "create_payment":
				$auctionItemRow = getRowFromId("auction_items", "auction_item_id", $_POST['auction_item_id'], "deleted = 0");
				$hash = md5($auctionItemRow['auction_item_id'] . ":" . $auctionItemRow['user_id'] . ":" . $auctionItemRow['start_time']);
				$buyNowHash = md5("buy_now:" . $auctionItemRow['auction_item_id'] . ":" . $auctionItemRow['user_id'] . ":" . $auctionItemRow['start_time']);
				$buyNow = $_POST['hash'] == $buyNowHash;
				if (empty($auctionItemRow) || empty($_POST['hash']) || !in_array($_POST['hash'], array($hash, $buyNowHash))) {
					$returnArray['error_message'] = "Invalid auction item.";
					ajaxResponse($returnArray);
					exit;
				}

				$totalPurchase = 0;
				if ($buyNow) {
                    if (!empty($auctionItemRow['date_completed']) || empty($auctionItemRow['buy_now_price']) || $auctionItemRow['end_time'] <= date("Y-m-d H:i:s")) {
						$returnArray['error_message'] = "Auction item is no longer available.";
						ajaxResponse($returnArray);
						exit;
                    }
					$totalPurchase = $auctionItemRow['buy_now_price'];
				} else {
					$resultSet = executeQuery("select * from auction_item_purchases where auction_item_id = ? and inactive = 0 and user_id = ?",
						$auctionItemRow['auction_item_id'], $GLOBALS['gUserId']);
					while ($auctionItemPurchasesRow = getNextRow($resultSet)) {
						$totalPurchase += ($auctionItemPurchasesRow['quantity'] * $auctionItemPurchasesRow['price']);
					}
                }

				$totalPayments = 0;
				$resultSet = executeQuery("select * from auction_item_payments where auction_item_id = ?", $auctionItemRow['auction_item_id']);
				while ($auctionItemPaymentRow = getNextRow($resultSet)) {
					$totalPayments += $auctionItemPaymentRow['amount'];
				}
				if ($totalPayments >= $totalPurchase) {
					$returnArray['error_message'] = "No payment is necessary.";
					ajaxResponse($returnArray);
					exit;
				}
				$totalAmount = $totalPurchase - $totalPayments;
				if ($totalAmount <= 0 || $totalAmount != $_POST['amount']) {
					$returnArray['error_message'] = "Invalid payment amount. Refresh screen and start over.";
					ajaxResponse($returnArray);
					exit;
				}

				$paymentRequired = (!empty($this->getPageTextChunk("PAYMENT_REQUIRED")) && (!empty($eCommerce) || !empty($achECommerce)));

				if ($paymentRequired || !empty($_POST['submit_payment'])) {
					$GLOBALS['gPrimaryDatabase']->startTransaction();

					$isBankAccount = (!empty($_POST['bank_account_number']));
					$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
					$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $paymentMethodId);

					if (empty($paymentMethodRow)) {
						$returnArray['error_message'] = "Invalid payment method. Refresh screen and start over.";
						ajaxResponse($returnArray);
						exit;
					}

					$merchantAccountInformation = false;
					$achMerchantAccountId = false;
					if (function_exists("_localGetMerchantAccountInformation")) {
                        $parameters = array_merge($_POST, $auctionItemRow);
						$merchantAccountInformation = _localGetMerchantAccountInformation($parameters);
					}
					if (empty($merchantAccountInformation)) {
						$merchantAccountId = $GLOBALS['gMerchantAccountId'];
						$eCommerce = $achECommerce = eCommerce::getEcommerceInstance($merchantAccountId);
						$achMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
						if (!empty($achMerchantAccountId)) {
							$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
						}
					} else {
						if (is_array($merchantAccountInformation)) {
							$merchantAccountId = $merchantAccountInformation['merchant_account_id'];
							$achMerchantAccountId = $merchantAccountInformation['ach_merchant_account_id'];
						} else {
							$merchantAccountId = $merchantAccountInformation;
						}
						$eCommerce = $achECommerce = eCommerce::getEcommerceInstance($merchantAccountId);
						if (!empty($achMerchantAccountId) && $achMerchantAccountId != $merchantAccountId) {
							$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
						}
					}

					$_POST['account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['account_number']));
					$_POST['bank_account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['bank_account_number']));

					if ($isBankAccount) {
						if (empty($achECommerce) || empty($_POST['bank_account_number'])) {
							$returnArray['error_message'] = "Invalid payment method. Refresh screen and start over.";
							ajaxResponse($returnArray);
							exit;
						}
						$useECommerce = $achECommerce;
					} else {
						if (empty($eCommerce) || empty($_POST['account_number'])) {
							$returnArray['error_message'] = "Invalid payment method. Refresh screen and start over.";
							ajaxResponse($returnArray);
							exit;
						}
						$useECommerce = $eCommerce;
					}

					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
					$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['billing_business_name']);
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number,expiration_date,merchant_account_id,inactive) values (?,?,?,?,?, ?,?,?)", $GLOBALS['gUserRow']['contact_id'], $accountLabel, $_POST['payment_method_id'],
						$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
						(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))),
						$merchantAccountId, 0);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						return $returnArray;
					}
					$accountId = $resultSet['insert_id'];

					# Charge the card.
					if (empty($accountToken)) {
						$paymentArray = array("amount" => $totalAmount, "order_number" => $auctionItemRow['auction_item_id'], "description" => "Auction Item Payment",
							"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'],
							"business_name" => $_POST['billing_business_name'], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
							"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
							"email_address" => $GLOBALS['gUserRow']['email_address'], "contact_id" => $GLOBALS['gUserRow']['contact_id']);
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
							executeQuery("insert into auction_item_payments (auction_item_id, payment_date, payment_method_id, amount, authorization_code, transaction_identifier, notes) values (?,current_date,?,?,?, ?,?)",
								$auctionItemRow['auction_item_id'], $_POST['payment_method_id'], $totalAmount, $response['authorization_code'], $response['transaction_id'], $_POST['notes']);
						} else {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
							$useECommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'] . "\n\n" . jsonEncode($response), true);
							return $returnArray;
						}
					}
				}

                if ($buyNow) {
					$auctionObject = new Auction();
					if (!$auctionObject->buyNow($auctionItemRow['auction_item_id'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $auctionObject->getErrorMessage();
						return $returnArray;
					}
                }

				$substitutions = array_merge($GLOBALS['gUserRow'], $auctionItemRow, $_POST);
				if (empty($substitutions['salutation'])) {
					$substitutions['salutation'] = generateSalutation($GLOBALS['gUserRow']);
				}
				$substitutions['full_name'] = getDisplayName($GLOBALS['gUserRow']['contact_id']);
				$substitutions['amount'] = number_format($_POST['amount'], 2);
				$substitutions['payment_date'] = date("m/d/Y");
				$substitutions['payment_datetime'] = date("m/d/Y g:ia");
				$substitutions['card_holder'] = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'];

				$substitutions['transaction_id'] = $response['transaction_id'];
				$substitutions['authorization_code'] = $response['authorization_code'];
				$substitutions['payment_method'] = $paymentMethodRow['description'];
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

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$emailId = getFieldFromId("email_id", "emails", "email_code", "AUCTION_PAYMENT_RECEIPT", "inactive = 0");
				if (!empty($emailId)) {
					sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $GLOBALS['gUserRow']['email_address'], "contact_id" => $GLOBALS['gUserRow']['contact_id']));
				}
				$emailId = getFieldFromId("email_id", "emails", "email_code", "AUCTION_PAYMENT_NOTIFICATION", "inactive = 0");
				if (!empty($emailId)) {
					sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "notification_code" => "AUCTION_PAYMENT_NOTIFICATION"));
				} else {
					$body = "A payment of %amount% was received from %full_name% for auction item ID %auction_item_id%.";
					sendEmail(array("subject" => "Payment received", "body" => $body, "substitutions" => $substitutions, "notification_code" => "AUCTION_PAYMENT_NOTIFICATION"));
				}

				$responseFragment = getFragment('auction_payment_received');
				if (empty($responseFragment)) {
					$responseFragment = "<p class='align-center'>Your payment of %amount% has been received.</p>";
				}
				$returnArray['response'] = PlaceHolders::massageContent($responseFragment, $substitutions);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("click", "#submit_form", function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#submit_paragraph").addClass("hidden");
                    $("#processing_payment").removeClass("hidden");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_payment", $("#_edit_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                            return;
                        }
                        if ("response" in returnArray) {
                            $("#payment_wrapper").html(returnArray['response']);
                        } else {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                        }
                    });
                }
                return false;
            });
            $("#billing_country_id").change(function () {
                if ($(this).val() === "1000") {
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
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if ($(this).val() !== "") {
                    const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            }).trigger("change");
		</script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$auctionItemRow = getRowFromId("auction_items", "auction_item_id", $_GET['id']);
		$hash = md5($auctionItemRow['auction_item_id'] . ":" . $auctionItemRow['user_id'] . ":" . $auctionItemRow['start_time']);
		$buyNowHash = md5("buy_now:" . $auctionItemRow['auction_item_id'] . ":" . $auctionItemRow['user_id'] . ":" . $auctionItemRow['start_time']);
		$buyNow = $_GET['hash'] == $buyNowHash;
		if (empty($auctionItemRow) || empty($_GET['hash']) || !in_array($_GET['hash'], array($hash, $buyNowHash))) {
			echo "<p>Auction item not found</p>";
			return true;
		}

		$totalQuantity = 0;
		$totalPurchase = 0;

		if ($buyNow) {
			if (!empty($auctionItemRow['date_completed']) || empty($auctionItemRow['buy_now_price']) || $auctionItemRow['end_time'] <= date("Y-m-d H:i:s")) {
				echo "<p>Buy now is not valid for this auction item</p>";
				return true;
			}
			$totalQuantity = 1;
			$totalPurchase = $auctionItemRow['buy_now_price'];
		} else {
			$purchases = array();
			$resultSet = executeQuery("select * from auction_item_purchases where auction_item_id = ? and inactive = 0 and user_id = ?",
				$auctionItemRow['auction_item_id'], $GLOBALS['gUserId']);
			while ($auctionItemPurchasesRow = getNextRow($resultSet)) {
				$totalQuantity += $auctionItemPurchasesRow['quantity'];
				$totalPurchase += ($auctionItemPurchasesRow['quantity'] * $auctionItemPurchasesRow['price']);
				$purchases[] = $auctionItemPurchasesRow;
			}
			if (empty($purchases)) {
				echo "<p>Auction item not found</p>";
				return true;
			}
		}

		$payments = array();
		$totalPayments = 0;
		$resultSet = executeQuery("select * from auction_item_payments where auction_item_id = ?", $auctionItemRow['auction_item_id']);
		while ($auctionItemPaymentRow = getNextRow($resultSet)) {
			$totalPayments += $auctionItemPaymentRow['amount'];
			$payments[] = $auctionItemPaymentRow;
		}
		if ($totalPayments >= $totalPurchase) {
			echo "<p>No Payment is necessary</p>";
			return true;
		}
		$amountDue = $totalPurchase - $totalPayments;
		?>
		<div id="payment_wrapper">
            <div id="auction_details">
                <h2>Item Details</h2>
                <table class='grid-table' id='item_details'>
                    <tr>
                        <th></th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                    </tr>
                    <tr>
                        <th>Purchases</th>
                        <td><?= $totalQuantity ?></td>
                        <td class='align-right'><?= number_format($totalPurchase, 2) ?></td>
                    </tr>
                    <tr>
                        <th>Payments</th>
                        <td><?= count($payments) ?></td>
                        <td class='align-right'><?= number_format($totalPayments, 2) ?></td>
                    </tr>
                </table>
            </div>

			<?php
			$merchantAccountInformation = false;
			$achMerchantAccountId = false;
			if (function_exists("_localGetMerchantAccountInformation")) {
                $parameters = array_merge($_POST, $auctionItemRow);
				$merchantAccountInformation = _localGetMerchantAccountInformation($parameters);
			}
			if (empty($merchantAccountInformation)) {
				$merchantAccountId = $GLOBALS['gMerchantAccountId'];
				$eCommerce = $achECommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				$achMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
				if (!empty($achMerchantAccountId)) {
					$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
				}
			} else {
				if (is_array($merchantAccountInformation)) {
					$merchantAccountId = $merchantAccountInformation['merchant_account_id'];
					$achMerchantAccountId = $merchantAccountInformation['ach_merchant_account_id'];
				} else {
					$merchantAccountId = $merchantAccountInformation;
				}
				$eCommerce = $achECommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				if (!empty($achMerchantAccountId) && $achMerchantAccountId != $merchantAccountId) {
					$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
				}
			}
			$capitalizedFields = array();
			if (getPreference("USE_FIELD_CAPITALIZATION")) {
				$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
				while ($row = getNextRow($resultSet)) {
					$capitalizedFields[] = $row['column_name'];
				}
			}
			$paymentRequired = (!empty($this->getPageTextChunk("PAYMENT_REQUIRED")) && (!empty($eCommerce) || !empty($achECommerce)));

			ob_start();
			?>
			<form id="_edit_form">
				<input type='hidden' name='auction_item_id' value='<?= $auctionItemRow['auction_item_id'] ?>'>
				<input type='hidden' name='hash' value='<?= $_GET['hash'] ?>'>
				<h2>Payment</h2>
				<div id="auction_payment_content">
					<div id="form_wrapper">
						<div class="basic-form-line<?= ($paymentRequired ? " hidden" : "") ?>" id="_submit_payment_row">
							<input tabindex="10" type="checkbox" id="submit_payment" name="submit_payment" value="1" checked="checked"><label class="checkbox-label" for="submit_payment">Submit Payment</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
						</div>

						<div class="basic-form-line" id="_amount_row">
							<label for="amount">Amount of payment</label>
							<input tabindex="10" type="text" size="12" maxlength="12" readonly="readonly" class="validate[required,custom[number]] align-right" id="amount" name="amount"
                                   data-decimal-places="2" value="<?= number_format($amountDue, 2) ?>">
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
						</div>

						<div id="_new_account">

							<div id="_billing_address" class="hidden">

								<div class="basic-form-line" id="_billing_first_name_row">
									<label for="billing_first_name" class="required-label">First Name</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_last_name_row">
									<label for="billing_last_name" class="required-label">Last Name</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_business_name_row">
									<label for="billing_business_name">Business Name</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_address_1_row">
									<label for="billing_address_1" class="required-label">Street</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_address_2_row">
									<label for="billing_address_2" class=""></label>
									<input tabindex="10" type="text" class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_city_row">
									<label for="billing_city" class="required-label">City</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_state_row">
									<label for="billing_state" class="">State</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_state_select_row">
									<label for="billing_state_select" class="">State</label>
									<select tabindex="10" data-conditional-required="$('#submit_payment').prop('checked')" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#billing_country_id').val() == 1000">
										<option value="">[Select]</option>
										<?php
										foreach (getStateArray() as $stateCode => $state) {
											?>
											<option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
											<?php
										}
										?>
									</select>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_postal_code_row">
									<label for="billing_postal_code" class="">Postal Code</label>
									<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]" size="10" maxlength="10" data-conditional-required="$('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_billing_country_id_row">
									<label for="billing_country_id" class="">Country</label>
									<select tabindex="10" data-conditional-required="$('#submit_payment').prop('checked')" class="validate[required]" id="billing_country_id" name="billing_country_id">
										<?php
										foreach (getCountryArray(true) as $countryId => $countryName) {
											?>
											<option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
											<?php
										}
										?>
									</select>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>
							</div> <!-- billing_address -->

							<div id="payment_information">
								<div class="basic-form-line" id="_payment_method_id_row">
									<label for="payment_method_id" class="">Payment Method</label>
									<select tabindex="10" class="validate[required]" id="payment_method_id" name="payment_method_id">
										<option value="">[Select]</option>
										<?php
										$paymentLogos = array();
										$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
											"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
											($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
											"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
											(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
											"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and (payment_method_type_id in " .
											"(select payment_method_type_id from payment_method_types where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
											"client_id = ? and payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT'))) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											if (empty($achECommerce) && $row['payment_method_type_code'] == "BANK_ACCOUNT") {
												continue;
											}
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
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="payment-method-fields" id="payment_method_credit_card">
									<div class="basic-form-line" id="_account_number_row">
										<label for="account_number" class="">Card Number</label>
										<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_credit_card').hasClass('hidden')" class="validate[required]" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
										<div id="payment_logos">
											<?php
											foreach ($paymentLogos as $paymentMethodId => $imageId) {
												?>
												<img alt="Payment Method Logo" id="payment_method_logo_<?= strtolower($paymentMethodId) ?>" class="payment-method-logo" src="<?= getImageFilename($imageId, array("use_cdn" => true)) ?>">
												<?php
											}
											?>
										</div>
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>

									<div class="basic-form-line" id="_expiration_month_row">
										<label for="expiration_month" class="">Expiration Date</label>
										<select tabindex="10" class="validate[required]" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_credit_card').hasClass('hidden')" id="expiration_month" name="expiration_month">
											<option value="">[Month]</option>
											<?php
											for ($x = 1; $x <= 12; $x++) {
												?>
												<option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
												<?php
											}
											?>
										</select>
										<select tabindex="10" class="validate[required]" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_credit_card').hasClass('hidden')" id="expiration_year" name="expiration_year">
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
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>

									<div class="basic-form-line" id="_cvv_code_row">
										<label for="cvv_code" class="">Security Code</label>
										<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_credit_card').hasClass('hidden')" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
										<a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>
								</div> <!-- payment_method_credit_card -->

								<div class="payment-method-fields" id="payment_method_bank_account">
									<div class="basic-form-line" id="_routing_number_row">
										<label for="routing_number" class="">Bank Routing Number</label>
										<input tabindex="10" type="text" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_bank_account').hasClass('hidden')" class="validate[required,custom[routingNumber]]" size="20" maxlength="9" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>

									<div class="basic-form-line" id="_bank_account_number_row">
										<label for="bank_account_number" class="">Account Number</label>
										<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_bank_account').hasClass('hidden')" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>

									<?php if (!empty(getPageTextChunk("VERIFY_BANK_ACCOUNT_NUMBER"))) { ?>
										<div class="basic-form-line" id="_bank_account_number_again_row">
											<label for="bank_account_number_again" class="">Re-enter Account Number</label>
											<input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="text" class="validate[equals[bank_account_number]]" data-conditional-required="$('#submit_payment').prop('checked') && !$('#payment_method_bank_account').hasClass('hidden')" size="20" maxlength="20" id="bank_account_number_again" name="bank_account_number_again" placeholder="Repeat Bank Account Number" value="">
											<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
										</div>
									<?php } ?>

								</div> <!-- payment_method_bank_account -->
							</div> <!-- payment_information -->
						</div> <!-- new_account -->

						<p class="error-message"></p>
						<p id="processing_payment" class="hidden">Payment being processed. Do not close window.</p>
						<p id="_submit_paragraph">
							<button tabindex="10" id="submit_form">Submit</button>
						</p>
					</div> <!-- form_wrapper -->
				</div>
			</form>
		</div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
		<style>
            #payment_wrapper {
                padding: 20px;
                width: 80%;
                max-width: 1200px;
                margin: 0 auto;
            }
		</style>
		<?php
	}
}

$pageObject = new AuctionPaymentPage();
$pageObject->displayPage();
