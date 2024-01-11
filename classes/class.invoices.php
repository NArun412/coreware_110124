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

class Invoices {

	public static function getInvoices($contactId) {
		$returnArray = array("invoices" => array());
		$invoicesQuery = "select *, (select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) invoice_total," .
			"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) payment_total from invoices where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
			"client_id = ? and contact_id = ?";

		$resultSet = executeReadQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = 0",
			$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId']);
		if ($resultSet['row_count'] > 0) {
			$dataLimitationQuery = "";
			while ($row = getNextRow($resultSet)) {
				$dataLimitationQuery = PlaceHolders::massageContent($row['query_text']);
				$dataLimitationQuery .= empty($dataLimitationQuery) ? $dataLimitationQuery : " and " . $dataLimitationQuery;
			}
			$invoicesQuery .= " and invoice_id not in (select invoice_id from invoices where " . $dataLimitationQuery . ")";
		}
		freeResult($resultSet);

		$invoicesQuery .= " order by invoice_date, invoice_number";
		$resultSet = executeQuery($invoicesQuery, $GLOBALS['gClientId'], $contactId);

		$totalBalance = 0;
		if ($resultSet['row_count'] > 0) {
			while ($row = getNextRow($resultSet)) {
				$invoice = $row;
				if (empty($invoice['invoice_total'])) {
					$invoice['invoice_total'] = 0;
				}
				if (empty($invoice['payment_total'])) {
					$invoice['payment_total'] = 0;
				}
				$invoice['balance'] = 0;
				if (empty($invoice['date_completed'])) {
					$invoice['balance'] = $invoice['invoice_total'] - $invoice['payment_total'];
					$totalBalance += $invoice['balance'];
				}
				$invoice['order_id'] = getFieldFromId("order_id", "order_payments", "invoice_id", $invoice['invoice_id']);
				$invoice['overdue'] = empty($invoice['date_completed']) && !empty($invoice['date_due']) && $invoice['date_due'] < date("Y-m-d");
				$returnArray['invoices'][] = $invoice;
			}
		}
		$returnArray['total_balance'] = $totalBalance;
		return $returnArray;
	}

	public static function getPayInvoicesForm($parameters) {
		$contactRow = $parameters['contact_row'];
		if ($GLOBALS['gDevelopmentServer']) {
			$eCommerce = false;
		} else {
			$eCommerce = eCommerce::getEcommerceInstance();
		}
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$contactId = $contactRow['contact_id'];

		ob_start();
		?>
		<form id="_edit_form">
			<h2>Invoices</h2>
			<div id="invoices_payment_content">
				<p>Select invoices to pay. Click <a href='#' id='select_all'>here</a> to select all. Click an invoice number
					to see its details.</p>
				<table class="grid-table" id="invoice_list">
					<tr>
						<th>Pay Now</th>
						<th class='custom-amount'>Payment Amount</th>
						<th>Invoice #</th>
						<th>Invoice Date</th>
						<th>Due Date</th>
						<th>Invoice Total</th>
						<th>Balance Due</th>
						<th></th>
					</tr>
					<?php
					$invoicesQuery = "select *, (select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) invoice_total," .
						"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) payment_total from invoices where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
						"client_id = ? and contact_id = ? and date_completed is null";

					$resultSet = executeReadQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = 0",
						$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId']);
					if ($resultSet['row_count'] > 0) {
						$dataLimitationQuery = "";
						while ($row = getNextRow($resultSet)) {
							$dataLimitationQuery = PlaceHolders::massageContent($row['query_text']);
							$dataLimitationQuery .= empty($dataLimitationQuery) ? $dataLimitationQuery : " and " . $dataLimitationQuery;
						}
						$invoicesQuery .= " and invoice_id not in (select invoice_id from invoices where " . $dataLimitationQuery . ")";
					}
					freeResult($resultSet);

					$invoicesQuery .= " order by invoice_number, invoice_date";
					$resultSet = executeQuery($invoicesQuery, $GLOBALS['gClientId'], $contactId);

					if ($resultSet['row_count'] > 0) {
						$totalBalance = 0;
						while ($row = getNextRow($resultSet)) {
							if (empty($row['invoice_total'])) {
								$row['invoice_total'] = 0;
							}
							if (empty($row['payment_total'])) {
								$row['payment_total'] = 0;
							}
							$balance = $row['invoice_total'] - $row['payment_total'];
							if ($row['invoice_total'] >= 0 && $balance <= 0) {
								continue;
							}
							$totalBalance += $balance;
							$orderId = getFieldFromId("order_id", "order_payments", "invoice_id", $row['invoice_id']);
							?>
							<tr class="invoice<?= (!empty($row['date_due']) && $row['date_due'] < date("Y-m-d") ? " overdue" : "") ?>" data-invoice_id="<?= $row['invoice_id'] ?>">
								<td class="align-center"><input tabindex="10" type="checkbox" class="pay-now" id="pay_now_<?= $row['invoice_id'] ?>" name="pay_now_<?= $row['invoice_id'] ?>" value="<?= $row['invoice_id'] ?>"></td>
								<td class="align-center custom-amount"><input size="12" class="pay-amount align-right validate[custom[number]]" data-decimal-places="2" id="pay_amount_<?= $row['invoice_id'] ?>" name="pay_amount_<?= $row['invoice_id'] ?>" value=""></td>
								<td><a href="#" class='invoice-details'><?= (empty($row['invoice_number']) ? $row['invoice_id'] : $row['invoice_number']) ?></a></td>
								<td><?= date("m/d/Y", strtotime($row['invoice_date'])) ?></td>
								<td><?= (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due']))) ?></td>
								<td class="align-right"><?= number_format($row['invoice_total'], 2) ?></td>
								<td class="align-right invoice-amount"><?= number_format($balance, 2) ?></td>
								<td><?= (empty($orderId) ? "" : "<a href='/order-receipt?order_id=" . $orderId . "'>View Order</a>") ?></td>
							</tr>
							<?php
						}
						?>
						<tr>
							<td id="totals_title" colspan="6" class="highlighted-text">Total Outstanding Invoices</td>
							<td class="align-right highlighted-text"><?= number_format($totalBalance, 2) ?></td>
							<td></td>
						</tr>
						<?php
					} else {
						?>
						<tr>
							<td colspan="8">No unpaid invoices found</td>
						</tr>
						<?php
					}
					?>
				</table>

				<h2>Payment Information</h2>
				<?= $parameters['payment_text'] ?>

				<div id="form_wrapper">
					<div class="basic-form-line" id="_full_amount_row">
						<input tabindex="10" type="checkbox" id="full_amount" name="full_amount" value="1" checked="checked"><label class="checkbox-label" for="full_amount">Pay full invoice amount(s)</label>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>

					<div class="basic-form-line" id="_amount_row">
						<label for="amount" class="required-label">Amount of payment</label>
						<input tabindex="10" type="text" size="12" maxlength="12" readonly="readonly" class="validate[required,custom[number]] align-right" data-maximum-value-variable="selectedInvoicesTotal" id="amount" name="amount" placeholder="Amount (USD)" data-decimal-places="2" value="0.00">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>

					<div class="basic-form-line hidden" id="_fee_amount_row">
						<label for="fee_amount" id="fee_amount_label">Payment Type Handling Fee</label>
						<input tabindex="10" type="text" size="12" maxlength="12" readonly="readonly" class="align-right" id="fee_amount" name="fee_amount" data-decimal-places="2" value="0.00">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>

					<div class="basic-form-line hidden" id="_total_charge_row">
						<label for="total_charge" id="total_charge_label">Total Charge</label>
						<input tabindex="10" type="text" size="12" maxlength="12" readonly="readonly" class="align-right" id="total_charge" name="total_charge" data-decimal-places="2" value="0.00">
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>

					<?php
					$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null and payment_method_id in (select payment_method_id from payment_methods where " .
						"payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT','GIFT_CARD')))", $contactId);
					if ($resultSet['row_count'] == 0 || empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
						?>
						<input type="hidden" id="account_id" name="account_id" value="">
						<?php
					} else {
						?>
						<div class="basic-form-line" id="_account_id_row">
							<label for="account_id" class="">Select Payment Account</label>
							<select tabindex="10" id="account_id" name="account_id">
								<option value="">[New Account]</option>
								<?php
								while ($row = getNextRow($resultSet)) {
									$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $row['payment_method_id']);
									?>
									<option data-flat_rate="<?= $paymentMethodRow['flat_rate'] ?>" data-fee_percent="<?= $paymentMethodRow['fee_percent'] ?>" value="<?= $row['account_id'] ?>"><?= htmlText((empty($row['account_label']) ? $row['account_number'] : $row['account_label'])) ?></option>
									<?php
								}
								?>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
						</div>
					<?php } ?>

					<div id="_new_account">

						<div class="basic-form-line checkbox-input" id="_same_address_row">
							<input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" value="1"><label class="checkbox-label" for="same_address">Billing address is same as primary address</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
						</div>

						<div id="_billing_address" class="hidden">

							<div class="basic-form-line" id="_billing_first_name_row">
								<label for="billing_first_name" class="required-label">First Name</label>
								<input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($contactRow['first_name']) ?>">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_last_name_row">
								<label for="billing_last_name" class="required-label">Last Name</label>
								<input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($contactRow['last_name']) ?>">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_business_name_row">
								<label for="billing_business_name">Business Name</label>
								<input tabindex="10" type="text" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($contactRow['business_name']) ?>">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_address_1_row">
								<label for="billing_address_1" class="required-label">Street</label>
								<input tabindex="10" type="text" class="validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_address_2_row">
								<label for="billing_address_2" class=""></label>
								<input tabindex="10" type="text" class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_city_row">
								<label for="billing_city" class="required-label">City</label>
								<input tabindex="10" type="text" class="validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_state_row">
								<label for="billing_state" class="">State</label>
								<input tabindex="10" type="text" class="validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_state_select_row">
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
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_postal_code_row">
								<label for="billing_postal_code" class="">Postal Code</label>
								<input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line" id="_billing_country_id_row">
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
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>
						</div> <!-- billing_address -->

						<div id="payment_information">
							<div class="basic-form-line" id="_payment_method_id_row">
								<label for="payment_method_id" class="">Payment Method</label>
								<select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
									<option value="">[Select]</option>
									<?php
									$paymentLogos = array();
									$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
										"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
										($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
										"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
										(empty($contactRow['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $contactRow['user_type_id'] . ")") . ") and " .
										"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and (payment_method_type_id in " .
										"(select payment_method_type_id from payment_method_types where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
										"client_id = ? and payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT','GIFT_CARD'))) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
									while ($row = getNextRow($resultSet)) {
										if (empty($row['image_id'])) {
											$paymentMethodRow = getRowFromId("payment_methods", "payment_method_code", $row['payment_method_code'], "client_id = ?", $GLOBALS['gDefaultClientId']);
											$row['image_id'] = $paymentMethodRow['image_id'];
										}
										if (!empty($row['image_id'])) {
											$paymentLogos[$row['payment_method_id']] = $row['image_id'];
										}
										?>
										<option value="<?= $row['payment_method_id'] ?>" data-flat_rate="<?= $row['flat_rate'] ?>" data-fee_percent="<?= $row['fee_percent'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
										<?php
									}
									?>
								</select>
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="payment-method-fields" id="payment_method_credit_card">
								<div class="basic-form-line" id="_account_number_row">
									<label for="account_number" class="">Card Number</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
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
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_cvv_code_row">
									<label for="cvv_code" class="">Security Code</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
									<a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>
							</div> <!-- payment_method_credit_card -->

							<div class="payment-method-fields" id="payment_method_bank_account">
								<div class="basic-form-line" id="_routing_number_row">
									<label for="routing_number" class="">Bank Routing Number</label>
									<input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="9" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_bank_account_number_row">
									<label for="bank_account_number" class="">Account Number</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<?php if (!empty(getPageTextChunk("VERIFY_BANK_ACCOUNT_NUMBER"))) { ?>
									<div class="basic-form-line" id="_bank_account_number_again_row">
										<label for="bank_account_number_again" class="">Re-enter Account Number</label>
										<input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="text" class="validate[equals[bank_account_number]]" size="20" maxlength="20" id="bank_account_number_again" name="bank_account_number_again" placeholder="Repeat Bank Account Number" value="">
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>
								<?php } ?>

							</div> <!-- payment_method_bank_account -->

							<div class="payment-method-fields" id="payment_method_gift_card">
								<div class="basic-form-line" id="_gift_card_number_row">
									<label for="gift_card_number" class="">Card Number</label>
									<input tabindex="10" type="text" class="validate[required]"
									       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_gift_card').hasClass('hidden')"
									       size="20" maxlength="30" id="gift_card_number"
									       name="gift_card_number" placeholder="Card Number" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>
								<div class="basic-form-line" id="_gift_card_pin_row">
									<label for="gift_card_pin" class="">Pin</label>
									<input tabindex="10" type="text" size="8" maxlength="8" id="gift_card_pin"
									       name="gift_card_pin" placeholder="Pin" value="">
									<div class='clear-div'></div>
								</div>
								<p class="gift-card-information"></p>
							</div> <!-- payment_method_gift_card -->

							<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
								<div class="basic-form-line checkbox-input" id="_save_account_row">
									<input tabindex="10" type="checkbox" id="save_account" name="save_account" value="1"><label class="checkbox-label" for="save_account">Save Account</label>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_account_label_row">
									<label for="account_label" class="">Account Nickname</label>
									<span class="help-label">for future reference, if saved</span>
									<input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>
							<?php } ?>

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
		<?php
		return ob_get_clean();
	}

	public static function getInvoiceDetails($parameters) {
		$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $parameters['invoice_id'],
			"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
		if (empty($invoiceId)) {
			$returnArray['error_message'] = "Invoice not found.";
			return $returnArray;
		}
		$invoiceRow = getRowFromId("invoices", "invoice_id", $invoiceId);
		$invoiceSubstitutions = $invoiceRow;

		// Invoice details
		$invoiceDetailsItem = $parameters['invoice_details_item_template'];
		if (empty($invoiceDetailsItem)) {
			ob_start();
			?>
			<tr>
				<td>%detail_date%</td>
				<td>%description%</td>
				<td class="align-right">%amount%</td>
				<td>%unit_description%</td>
				<td class="align-right">%unit_price%</td>
				<td class="align-right">%extended_price%</td>
			</tr>
			%if_has_value:detailed_description%
			<tr>
				<td></td>
				<td colspan="5">%detailed_description%</td>
			</tr>
			%endif%
			<?php
			$invoiceDetailsItem = ob_get_clean();
		}

		$totalAmount = 0;
		$invoiceSubstitutions['invoice_details_items'] = "";
		$resultSet = executeQuery("select * from invoice_details where invoice_id = ?", $invoiceId);
		while ($row = getNextRow($resultSet)) {
			$itemSubstitutions = $row;
			$totalAmount += ($row['amount'] * $row['unit_price']);

			if (substr($row['description'], 0, strlen("Order #")) == "Order #") {
				$orderId = getFieldFromId("order_id", "orders", "contact_id", $GLOBALS['gUserRow']['contact_id'],
					"order_id = ?", substr($row['description'], strlen("Order #")));
				if (!empty($orderId)) {
					$itemSubstitutions['description'] = str_replace("Order #" . $orderId,
						"Order #<a href='/order-receipt?order_id=" . $orderId . "' target='_blank'>" . $orderId . "</a>", $row['description']);
				}
			}
			$itemSubstitutions['detail_date'] = date("m/d/Y", strtotime($row['detail_date']));
			$itemSubstitutions['description'] = str_replace("\n", "<br>", $itemSubstitutions['description']);
			$itemSubstitutions['amount'] = number_format($row['amount'], 2, ".", "");
			$itemSubstitutions['unit_description'] = htmlText(getFieldFromId("description", "units", "unit_id", $row['unit_id']));
			$itemSubstitutions['unit_price'] = number_format($row['unit_price'], 2);
			$itemSubstitutions['extended_price'] = number_format($row['amount'] * $row['unit_price'], 2);
			$itemSubstitutions['detailed_description'] = str_replace("\n", "<br>", $row['detailed_description']);
			$invoiceSubstitutions['invoice_details_items'] .= PlaceHolders::massageContent($invoiceDetailsItem, array_merge($invoiceSubstitutions, $itemSubstitutions));
		}

		// Invoice payments
		$invoicePaymentsItem = $parameters['invoice_payments_item_template'];
		if (empty($invoicePaymentsItem)) {
			ob_start();
			?>
			<tr>
				<td>%payment_date%</td>
				<td>%payment_method%</td>
				<td class="align-right">%payment_amount%</td>
			</tr>
			<?php
			$invoicePaymentsItem = ob_get_clean();
		}

		$totalPayments = 0;
		$resultSet = executeQuery("select * from invoice_payments where invoice_id = ?", $invoiceId);
		while ($row = getNextRow($resultSet)) {
			$totalPayments += $row['amount'];
			$itemSubstitutions = $row;
			$itemSubstitutions['payment_date'] = date("m/d/Y", strtotime($row['payment_date']));
			$itemSubstitutions['payment_method'] = htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']));
			$itemSubstitutions['payment_amount'] = number_format($row['amount'], 2);
			$invoiceSubstitutions['invoice_payments_items'] .= PlaceHolders::massageContent($invoicePaymentsItem, array_merge($invoiceSubstitutions, $itemSubstitutions));
		}

		$invoiceDetails = $parameters['invoice_details_template'];
		if (empty($invoiceDetails)) {
			ob_start();
			?>
			<h2 id="_invoice_header" data-invoice_id="%invoice_id%">Invoice %invoice_number%</h2>
			<p>Invoice Date: %invoice_date%</p>
			<p>%invoice_notes%</p>

			<h4>Details:</h4>
			<table class="grid-table" id="invoice_details">
				<thead>
				<tr>
					<th>Detail Date</th>
					<th>Description</th>
					<th>Amount</th>
					<th>Unit</th>
					<th>Unit Price</th>
					<th>Extended</th>
				</tr>
				</thead>
				<tbody>
				%invoice_details_items%
				</tbody>
				<tfoot>
				<tr>
					<td colspan="5" class="highlighted-text">Total</td>
					<td class="align-right">%invoice_total_amount%</td>
				</tr>
				</tfoot>
			</table>

			%if_has_value:invoice_payments_items%
			<h4>Payments:</h4>
			<table class="grid-table" id="invoice_payments">
				<thead>
				<tr>
					<th>Payment Date</th>
					<th>Payment Method</th>
					<th>Amount</th>
				</tr>
				</thead>
				<tbody>
				%invoice_payments_items%
				</tbody>
				<tfoot>
				<tr>
					<td colspan="2" class="highlighted-text">Total</td>
					<td class="align-right">%invoice_total_payments%</td>
				</tr>
				</tfoot>
			</table>
			%endif%

			<h4>Balance Due: %invoice_balance_due%</h4>
			<?php
			$invoiceDetails = ob_get_clean();
		}
		$invoiceSubstitutions['invoice_number'] = empty($invoiceRow['invoice_number']) ? "ID " . $invoiceRow['invoice_id'] : $invoiceRow['invoice_number'];
		$invoiceSubstitutions['invoice_date'] = date("m/d/Y", strtotime($invoiceRow['invoice_date']));
		$invoiceSubstitutions['invoice_notes'] = str_replace("\n", "<br>", $invoiceRow['notes']);
		$invoiceSubstitutions['invoice_total_amount'] = number_format($totalAmount, 2);
		$invoiceSubstitutions['invoice_total_payments'] = number_format($totalPayments, 2);
		$invoiceSubstitutions['invoice_balance_due'] = number_format($totalAmount - $totalPayments, 2);

		$returnArray['invoice_details'] = PlaceHolders::massageContent($invoiceDetails, $invoiceSubstitutions);
		return $returnArray;
	}

	public static function createInvoicePayment($parameters) {
		$invoiceArray = array();
		$contactRow = $parameters['contact_row'];
		$contactId = empty($parameters['contact_id']) ? $contactRow['contact_id'] : $parameters['contact_id'];

		foreach ($_POST as $fieldName => $fieldData) {
			if (empty($fieldData)) {
				continue;
			}
			if (substr($fieldName, 0, strlen("pay_")) == "pay_") {
				$parts = explode("_", $fieldName);
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $parts[2], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and contact_id = ?", $contactId);
				if (empty($invoiceId)) {
					$returnArray['error_message'] = "Invalid Invoice. Refresh the screen and start over.";
					ajaxResponse($returnArray);
					break;
				}

				if (empty($invoiceArray[$invoiceId])) {
					$invoiceArray[$invoiceId] = array("invoice_id" => $invoiceId);
				}
				if (substr($fieldName, 0, strlen("pay_amount_")) == "pay_amount_") {
					$invoiceArray[$invoiceId]['payment_amount'] = $fieldData;
				}
			}
		}
		$totalAmount = 0;
		$invoiceResultsArray = array();
		$invoiceIdList = "";
		$invoiceNumberList = "";
		$invoiceIdPaidList = "";
		$invoiceNumberPaidList = "";
		$orderIdList = "";
		$orderNumberList = "";
		$invoiceNumber = "";
		foreach ($invoiceArray as $thisInvoiceId => $thisInvoice) {
			$resultSet = executeQuery("select *,(select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) invoice_total," .
				"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) payment_total from invoices where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and " .
				"contact_id = ? and date_completed is null and invoice_id = ?", $GLOBALS['gClientId'], $contactId, $thisInvoice['invoice_id']);
			if ($row = getNextRow($resultSet)) {
				$invoiceNumber = (empty($row['invoice_number']) ? $row['invoice_id'] : $row['invoice_number']);

				if (empty($row['invoice_total'])) {
					$row['invoice_total'] = 0;
				}
				if (empty($row['payment_total'])) {
					$row['payment_total'] = 0;
				}
				$balance = $row['invoice_total'] - $row['payment_total'];
				if (!empty($_POST['full_amount'])) {
					$invoiceArray[$thisInvoiceId]['payment_amount'] = $thisInvoice['payment_amount'] = $balance;
				} else {
					if (empty($thisInvoice['payment_amount'])) {
						continue;
					}
				}
				if ($thisInvoice['payment_amount'] > $balance) {
					$returnArray['error_message'] = "Payment exceeds balance due for invoice " . $invoiceNumber;
					ajaxResponse($returnArray);
					break;
				}
				$invoiceResult = array("invoice_id" => $row['invoice_id'], "invoice_number" => $invoiceNumber, "payment_amount" => $thisInvoice['payment_amount'],
					"paid" => 0, "order_id" => "", "order_number" => "");
				if (round($thisInvoice['payment_amount'], 2) - round($balance, 2) == 0) {
					$invoiceResult['paid'] = 1;
				}
				$totalAmount += $thisInvoice['payment_amount'];
				$orderId = getFieldFromId("order_id", "order_payments", "invoice_id", $row['invoice_id']);
				if (!empty($orderId)) {
					$invoiceResult['order_id'] = $orderId;
					$invoiceResult['order_number'] = getFieldFromId("order_number", "orders", "order_id", $orderId);
				}
				$invoiceResultsArray[] = $invoiceResult;
			}
		}
		$totalAmount = round($totalAmount, 2);
		$_POST['amount'] = round($_POST['amount'], 2);
		if ($totalAmount <= 0 || $totalAmount != $_POST['amount']) {
			$returnArray['error_message'] = "Invalid Payment Amount. Refresh screen and start over.";
			return $returnArray;
		}
		$database = $parameters['database'];
		$database->startTransaction();

		# Process payment receipt
		$invoicePaymentIdArray = array();

		$processTotal = round($_POST['amount'], 2);
		foreach ($invoiceArray as $thisInvoice) {
			$resultSet = executeQuery("select *,(select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) invoice_total," .
				"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) payment_total from invoices where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
				"client_id = ? and contact_id = ? and date_completed is null and invoice_id = ?", $GLOBALS['gClientId'], $contactId, $thisInvoice['invoice_id']);
			if ($row = getNextRow($resultSet)) {
				if (empty($row['invoice_total'])) {
					$row['invoice_total'] = 0;
				}
				if (empty($row['payment_total'])) {
					$row['payment_total'] = 0;
				}
				$balance = $row['invoice_total'] - $row['payment_total'];
				if (empty($thisInvoice['payment_amount'])) {
					$thisInvoice['payment_amount'] = $balance;
				}
				$paymentAmount = round($thisInvoice['payment_amount'], 2);
				if ($paymentAmount > 0) {
					$insertSet = executeQuery("insert into invoice_payments (invoice_id,payment_date,amount) values (?,current_date,?)", $row['invoice_id'], $paymentAmount);
					if (!empty($insertSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$invoicePaymentIdArray[] = $insertSet['insert_id'];
					coreSTORE::invoicePaymentNotification($insertSet['insert_id']);
				}
				$processTotal = round($processTotal - $paymentAmount, 2);
			}
			self::postPaymentInvoiceProcessing($row['invoice_id']);
		}
		if ($processTotal > 0) {
			$returnArray['error_message'] = "Invalid Payment Amount. Refresh screen and start over.";
			return $returnArray;
		}

		if ($_POST['same_address']) {
			$fields = array("first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "country_id");
			foreach ($fields as $fieldName) {
				$_POST['billing_' . $fieldName] = $contactRow[$fieldName];
			}
		}

		if (!empty($_POST['account_id'])) {
			$resultSet = executeQuery("select payment_method_type_code from payment_method_types join payment_methods using (payment_method_type_id) join accounts using (payment_method_id) where account_id = ?",
				$_POST['account_id']);
			$row = getNextRow($resultSet);
			$isBankAccount = $row['payment_method_type_code'] == 'BANK_ACCOUNT';
			$paymentMethodId = getFieldFromId("payment_method_id", "accounts", "account_id", $_POST['account_id']);
			$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $paymentMethodId);
			$paymentMethodTypeCode = $row['payment_method_type_code'];
		} else {
			$isBankAccount = (!empty($_POST['bank_account_number']));
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
			$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $paymentMethodId);
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodRow['payment_method_type_id']);
		}

		if (empty($paymentMethodId)) {
			$returnArray['error_message'] = "Invalid Payment Method. Refresh screen and start over.";
			return $returnArray;
		}

		# Calculate fee for payment method
		$feeAmount = $paymentMethodRow['flat_rate'];
		if (empty($feeAmount)) {
			$feeAmount = 0;
		}
		if (!empty($paymentMethodRow['fee_percent'])) {
			$feeAmount += $_POST['amount'] * $paymentMethodRow['fee_percent'] / 100;
		}

		$totalAmount = $_POST['amount'] + $feeAmount;

		if ($paymentMethodTypeCode == "GIFT_CARD") {
			$giftCard = new GiftCard(array("gift_card_number" => $_POST['gift_card_number'], "user_id" => $GLOBALS['gUserId']));
			if (!$giftCard) {
				$database->rollbackTransaction();
				$returnArray['error_message'] = "Gift Card doesn't exist";
				return $returnArray;
			}
			$balance = $giftCard->getBalance();
			if ($balance < $totalAmount) {
				$database->rollbackTransaction();
				$returnArray['error_message'] = "Not enough on the gift card to make this payment";
				return $returnArray;
			}
			$logContent = "Usage for invoice ID" . (count($invoicePaymentIdArray) > 1 ? "s " : " ") . implode(",", $invoicePaymentIdArray);
			if (!$giftCard->adjustBalance(false, (-1 * $totalAmount), $logContent, $orderId)) {
				$database->rollbackTransaction();
				$returnArray['error_message'] = "Unable to process the gift card transaction";
				return $returnArray;
			}
			$giftCardNumber = $giftCard->getGiftCardNumber();
			if (!empty($invoicePaymentIdArray)) {
				executeQuery("update invoice_payments set payment_method_id = ?, notes = ? where invoice_payment_id in (" . implode(",", $invoicePaymentIdArray) . ")",
					$paymentMethodId, "Paid with gift card ending in " . substr($giftCardNumber, -4));
			}
		} else {
			$merchantAccountId = $GLOBALS['gMerchantAccountId'];
			$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);

			$achMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
			if (!empty($achMerchantAccountId)) {
				$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
			}
			if (!empty($achMerchantAccountId) && $isBankAccount) {
				$useECommerce = $achECommerce;
				$merchantAccountId = $achMerchantAccountId;
			} else {
				$useECommerce = $eCommerce;
			}

			if (!$useECommerce) {
				$database->rollbackTransaction();
				$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service. #9572";
				return $returnArray;
			}

			# Strip spaces and dashes from account numbers
			$_POST['account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['account_number']));
			$_POST['bank_account_number'] = str_replace("-", "", str_replace(" ", "", $_POST['bank_account_number']));

			# If the user is logged in, get or create a customer profile
			$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
			if (empty($merchantIdentifier) && !empty($useECommerce) && $useECommerce->hasCustomerDatabase()) {

				$customerArray = array("contact_id" => $contactId, "first_name" => $_POST['first_name'],
					"last_name" => $_POST['last_name'], "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "city" => $_POST['city'],
					"state" => $_POST['state'], "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address']);
				if (function_exists("_localInvoicePaymentsCustomPaymentFields")) {
					$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
					if (is_array($additionalFields)) {
						$customerArray = array_merge($customerArray, $additionalFields);
					}
				}

				$success = $useECommerce->createCustomerProfile($customerArray);
				$response = $useECommerce->getResponse();
				if ($success) {
					$merchantIdentifier = $response['merchant_identifier'];
				}
			}
			if (empty($merchantIdentifier) && !empty($_POST['account_id'])) {
				$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #128";
				$database->rollbackTransaction();
				return $returnArray;
			}

			# If new account, create it
			if (empty($_POST['account_id'])) {
				$accountLabel = $_POST['account_label'];
				if (empty($accountLabel)) {
					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
				}
				$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['billing_business_name']);
				$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
					"account_number,expiration_date,merchant_account_id,inactive) values (?,?,?,?,?, ?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
					$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
					(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))),
					$merchantAccountId, ($_POST['save_account'] ? 0 : 1));
				if (!empty($resultSet['sql_error'])) {
					$database->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					return $returnArray;
				}
				$accountId = $resultSet['insert_id'];
			} else {
				$accountId = getFieldFromId("account_id", "accounts", "account_id", $_POST['account_id'], "contact_id = ?", $contactId);
				$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
			}
			$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
			if (empty($accountToken) && !empty($_POST['account_id'])) {
				$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #7851";
				$database->rollbackTransaction();
				return $returnArray;
			}

			$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountId);
			if ($accountMerchantAccountId != $merchantAccountId) {
				$returnArray['error_message'] = "There is a problem with this account. #8352";
				$database->rollbackTransaction();
				return $returnArray;
			}

			# If the user is asking to save account, make sure the account exists
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

			# If the user is asking to save account, make sure the account exists
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
				if (function_exists("_localInvoicePaymentsCustomPaymentFields")) {
					$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
					if (is_array($additionalFields)) {
						$paymentArray = array_merge($paymentArray, $additionalFields);
					}
				}

				$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
				$response = $useECommerce->getResponse();
				if ($success) {
					$customerPaymentProfileId = $accountToken = $response['account_token'];
				} else {
					$database->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create payment account. Do you already have this payment method saved?";
					return $returnArray;
				}
			}

			# If creating the account didn't work, exit with error .
			if (empty($accountToken) && empty($_POST['account_number']) && empty($_POST['bank_account_number'])) {
				$database->rollbackTransaction();
				$returnArray['error_message'] = "Unable to charge account. Please contact customer service. #5923";
				return $returnArray;
			}

			# Charge the card.
			if (empty($accountToken)) {
				$paymentArray = array("amount" => $totalAmount, "order_number" => $invoiceNumber, "description" => "Account Payment",
					"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'],
					"business_name" => $_POST['billing_business_name'], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
					"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
					"email_address" => $contactRow['email_address'], "contact_id" => $contactId);
				if ($isBankAccount) {
					$paymentArray['bank_routing_number'] = $_POST['routing_number'];
					$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
					$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
				} else {
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['cvv_code'];
				}
				if (function_exists("_localInvoicePaymentsCustomPaymentFields")) {
					$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
					if (is_array($additionalFields)) {
						$paymentArray = array_merge($paymentArray, $additionalFields);
					}
				}

				$success = $useECommerce->authorizeCharge($paymentArray);
				$response = $useECommerce->getResponse();
				if ($success) {
					if (!empty($invoicePaymentIdArray)) {
						executeQuery("update invoice_payments set account_id = ?,payment_method_id = ?,transaction_identifier = ?,authorization_code = ? where invoice_payment_id in (" . implode(",", $invoicePaymentIdArray) . ")",
							$accountId, $paymentMethodId, $response['transaction_id'], $response['authorization_code']);
					}
				} else {
					$database->rollbackTransaction();
					$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
					$useECommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'] . "\n\n" . jsonEncode($response), true);
					return $returnArray;
				}
			} elseif (!empty($useECommerce) && $useECommerce->hasCustomerDatabase()) {
				$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
				if (empty($accountMerchantIdentifier)) {
					$accountMerchantIdentifier = $merchantIdentifier;
				}
				$addressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
				$success = $useECommerce->createCustomerProfileTransactionRequest(array("amount" => $totalAmount, "order_number" => $invoiceNumber,
					"merchant_identifier" => $accountMerchantIdentifier, "account_token" => $accountToken, "address_id" => $addressId));
				$response = $useECommerce->getResponse();
				if ($success) {
					if (!empty($invoicePaymentIdArray)) {
						executeQuery("update invoice_payments set account_id = ?,payment_method_id = ?,transaction_identifier = ?,authorization_code = ? where invoice_payment_id in (" . implode(",", $invoicePaymentIdArray) . ")",
							$accountId, $paymentMethodId, $response['transaction_id'], $response['authorization_code']);
					}
				} else {
					if (!empty($customerPaymentProfileId)) {
						$useECommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
					}
					$database->rollbackTransaction();
					$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
					$useECommerce->writeLog(($isBankAccount ? $_POST['bank_account_number'] : $_POST['card_number']), $response['response_reason_text'] . "\n\n" . jsonEncode($response), true);
					return $returnArray;
				}
			} else {
				$returnArray['error_message'] = "Charge failed: Unable to use saved account.";
				return $returnArray;
			}
		}

		$substitutions = $contactRow;
		if (empty($substitutions['salutation'])) {
			$substitutions['salutation'] = generateSalutation($contactRow);
		}
		$substitutions['full_name'] = getDisplayName($contactRow['contact_id']);
		$substitutions['amount'] = number_format($_POST['amount'], 2);
		$substitutions['payment_amount'] = $_POST['amount'];
		$substitutions['fee_amount'] = $feeAmount;
		$substitutions['total_charge'] = number_format($totalAmount, 2);
		$substitutions['payment_date'] = date("m/d/Y");
		$substitutions['payment_datetime'] = date("m/d/Y g:ia");
		$substitutions['card_holder'] = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'];

		$substitutions['transaction_id'] = $response['transaction_id'];
		$substitutions['authorization_code'] = $response['authorization_code'];
		$substitutions['payment_method'] = $paymentMethodRow['description'];
		$substitutions['account_label'] = (empty($accountLabel) ? $paymentMethodRow['description'] : $accountLabel);
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
		$resultTable = "<table class='invoice-result-table'><tr><th>Invoice Number</th><th>Order Number</th><th>Amount Paid</th><th>Paid in full</th></tr>";
		foreach ($invoiceResultsArray as $invoiceResult) {
			$invoiceIdList .= (empty($invoiceIdList) ? "" : ",") . $invoiceResult['invoice_id'];
			$invoiceNumberList .= (empty($invoiceNumberList) ? "" : ",") . $invoiceResult['invoice_number'];
			if (!empty($invoiceResult['order_id'])) {
				$orderIdList .= (empty($orderIdList) ? "" : ",") . $invoiceResult['order_id'];
				$orderNumberList .= (empty($orderNumberList) ? "" : ",") . $invoiceResult['order_number'];
			}
			if ($invoiceResult['paid']) {
				$invoiceIdPaidList .= (empty($invoiceIdPaidList) ? "" : ",") . $invoiceResult['invoice_id'];
				$invoiceNumberPaidList .= (empty($invoiceNumberPaidList) ? "" : ",") . $invoiceResult['invoice_number'];
			}
			$resultTable .= "<tr><td>" . $invoiceResult['invoice_number'] . "</td><td>" . $invoiceResult['order_number'] . "</td><td>"
				. $invoiceResult['payment_amount'] . "</td><td>" . (empty($invoiceResult['paid']) ? "" : "YES") . "</td></tr>";
		}
		$resultTable .= "</table>";
		$substitutions['invoice_id_list'] = $invoiceIdList;
		$substitutions['invoice_number_list'] = $invoiceNumberList;
		$substitutions['invoice_id_paid_off_list'] = $invoiceIdPaidList;
		$substitutions['invoice_number_paid_off_list'] = $invoiceNumberPaidList;
		$substitutions['order_id_list'] = $orderIdList;
		$substitutions['order_number_list'] = $orderNumberList;
		$substitutions['invoice_result_table'] = $resultTable;
		if (function_exists("_localInvoicePaymentsSubstitutions")) {
			$additionalSubstitutions = _localInvoicePaymentsSubstitutions($contactRow['contact_id']);
			if (is_array($additionalSubstitutions)) {
				$substitutions = array_merge($substitutions, $additionalSubstitutions);
			}
		}

		$database->commitTransaction();

		$emailId = getFieldFromId("email_id", "emails", "email_code", "INVOICE_PAYMENT_ERECEIPT", "inactive = 0");
		if (!empty($emailId)) {
			sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $contactRow['email_address'], "contact_id" => $contactRow['contact_id']));
		}
		$emailId = getFieldFromId("email_id", "emails", "email_code", "INVOICE_PAYMENT_NOTIFICATION", "inactive = 0");
		if (!empty($emailId)) {
			sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "notification_code" => "INVOICE_PAYMENT_NOTIFICATION"));
		} else {
			$body = "A payment of %amount% was received from %full_name%.";
			sendEmail(array("subject" => "Payment received", "body" => $body, "substitutions" => $substitutions, "notification_code" => "INVOICE_PAYMENT_NOTIFICATION"));
		}

		$responseFragment = $parameters['invoice_payment_received_template'];
		if (empty($responseFragment)) {
			$responseFragment = "<p class='align-center'>Your payment of %amount% has been received.</p>";
		}
		$returnArray['response'] = PlaceHolders::massageContent($responseFragment, $substitutions);
		return $returnArray;
	}

	public static function postPaymentInvoiceProcessing($invoiceId) {
		$resultSet = executeQuery("select *,(select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) invoice_total," .
			"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) payment_total from invoices where invoice_id = ?", $invoiceId);
		if ($row = getNextRow($resultSet)) {
			if (empty($row['invoice_total'])) {
				$row['invoice_total'] = 0;
			}
			if (empty($row['payment_total'])) {
				$row['payment_total'] = 0;
			}
			$balance = $row['invoice_total'] - $row['payment_total'];
			if ($balance <= 0) {
				executeQuery("update invoices set date_completed = current_date where invoice_id = ?", $row['invoice_id']);
			}
			if (!empty($row['designation_id'])) {
				$invoicePaymentSet = executeQuery("select * from invoice_payments where invoice_id = ? and donation_id is null", $row['invoice_id']);
				while ($invoicePaymentRow = getNextRow($invoicePaymentSet)) {
					$donationFee = Donations::getDonationFee(array("designation_id" => $row['designation_id'], "amount" => $invoicePaymentRow['amount'], "payment_method_id" => $invoicePaymentRow['payment_method_id']));
					$donationCommitmentId = Donations::getContactDonationCommitment($row['contact_id'], $row['designation_id']);
					$insertSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
						"designation_id,amount,donation_fee,donation_commitment_id,notes) values (?,?,now(),?,?, ?,?,?,?)",
						$GLOBALS['gClientId'], $row['contact_id'], $invoicePaymentRow['payment_method_id'], $row['designation_id'],
						$invoicePaymentRow['amount'], $donationFee, $donationCommitmentId, "Generated from invoice " . $row['invoice_id']);
					$donationId = $insertSet['insert_id'];
					executeQuery("update invoice_payments set donation_id = ? where invoice_payment_id = ?", $donationId, $invoicePaymentRow['invoice_payment_id']);
					Donations::completeDonationCommitment($donationCommitmentId);
					Donations::processDonation($donationId);
					Donations::processDonationReceipt($donationId, array("email_only" => true));
				}
			}
			$paidOrderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "INVOICE_PAID", "inactive = 0");
			$sentOrderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "INVOICE_SENT", "inactive = 0");
			if (!empty($paidOrderStatusId) && !empty($sentOrderStatusId)) {
				$orderSet = executeQuery("select order_id from order_payments join orders using (order_id) where order_status_id = ? and invoice_id = ?", $sentOrderStatusId, $invoiceId);
				while ($orderRow = getNextRow($orderSet)) {
					Order::updateOrderStatus($orderRow['order_id'], $paidOrderStatusId);
				}
			}
		}
	}
}
