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

$GLOBALS['gPageCode'] = "ORDEREDIT";
require_once "shared/startup.inc";

class ThisPage extends Page {

    function setup() {
        if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
            $this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));
            $this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("user_id","account_id","contact_id"));
        }
    }

    function executePageUrlActions() {
        $returnArray = array();
        switch ($_GET['url_action']) {
            case "create_new_payment":
                $merchantAccountId = $GLOBALS['gMerchantAccountId'];
                $eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
                if (empty($eCommerce)) {
                    $returnArray['error_message'] = "Unable to process payment";
                    ajaxResponse($returnArray);
                    break;
                }
                $orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
                if (empty($orderId)) {
                    $returnArray['error_message'] = "Unable to process payment";
                    ajaxResponse($returnArray);
                    break;
                }
                $chargeAmount = $_POST['billing_total_charge'];
                if (!is_numeric($chargeAmount) || $chargeAmount <= 0) {
                    $returnArray['error_message'] = "Invalid Amount to be charged";
                    ajaxResponse($returnArray);
                    break;
                }
                $returnArray['debug'] = true;
                $GLOBALS['gPrimaryDatabase']->startTransaction();
                $orderRow = getRowFromId("orders", "order_id", $orderId);
                $orderObject = new Order($orderId);
                $contactRow = Contact::getContact($orderRow['contact_id']);
                $paymentArray = array("amount" => $chargeAmount, "order_number" => $orderId, "description" => "Product Order",
                    "first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
                    "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
                    "postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
                    "email_address" => $contactRow['email_address'], "phone_number" => $orderRow['phone_number'], "contact_id" => $orderRow['contact_id']);
                $paymentArray['card_number'] = $_POST['account_number'];
                $paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
                $paymentArray['card_code'] = ($GLOBALS['gInternalConnection'] && empty($_POST['cvv_code']) ? "SKIP_CARD_CODE" : $_POST['cvv_code']);
                $success = $eCommerce->authorizeCharge($paymentArray);
                $response = $eCommerce->getResponse();
                $returnArray['response'] = $response;
                if ($success) {
                    if (!$orderPaymentId = $orderObject->createOrderPayment($_POST['billing_amount'], array("payment_method_id" => $_POST['billing_payment_method_id'],
                        "authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id'], "shipping_charge" => $_POST['billing_shipping_charge'],
                        "tax_charge" => $_POST['billing_tax_charge'], "handling_charge" => $_POST['billing_handling_charge']))) {
                        $returnArray['error_message'] = "Charge successful, but unable to create order payment record: " . $orderObject->getErrorMessage();
                    } else {
                        $returnArray['order_payment'] = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
                        $returnArray['order_payment']['payment_time'] = date("m/d/Y g:i:sa",strtotime($returnArray['order_payment']['payment_time']));
                        $returnArray['charge_amount'] = $chargeAmount;
                    }
                } else {
                    $returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
                    $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                    ajaxResponse($returnArray);
                    break;
                }
                $GLOBALS['gPrimaryDatabase']->commitTransaction();
                coreSTORE::orderNotification($orderId, "payment_created");
                ajaxResponse($returnArray);
                break;
        }
    }

    function massageDataSource() {
        $this->iDataSource->addColumnControl("address_id", "data_type", "select");
        $this->iDataSource->addColumnControl("address_id", "form_label", "Shipping Address");
        $this->iDataSource->addColumnControl("address_id", "choices", array());
        $this->iDataSource->addColumnControl("address_id", "empty_text", "[Use Primary Address]");
        $this->iDataSource->addColumnControl("address_id", "not_null", false);

        $this->iDataSource->addColumnControl("order_number", "readonly", true);

        $this->iDataSource->addColumnControl("order_items", "data_type", "custom");
        $this->iDataSource->addColumnControl("order_items", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("order_items", "list_table", "order_items");
        $this->iDataSource->addColumnControl("order_items", "form_label", "Order Items");
        $this->iDataSource->addColumnControl("order_items", "help_label", "This will not change the charge for the order");
        $this->iDataSource->addColumnControl("order_items", "no_delete", true);
        $this->iDataSource->addColumnControl("order_items", "column_list", "product_id,description,quantity,sale_price,order_item_status_id,deleted");
        $this->iDataSource->addColumnControl("order_items", "list_table_controls", array("quantity" => array("classes" => "quantity"), "sale_price" => array("classes" => "sale-price"), "deleted" => array("classes" => "deleted")));

        $this->iDataSource->addColumnControl("order_payments", "data_type", "custom");
        $this->iDataSource->addColumnControl("order_payments", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("order_payments", "list_table", "order_payments");
        $this->iDataSource->addColumnControl("order_payments", "form_label", "Order Payments");
        $this->iDataSource->addColumnControl("order_payments", "readonly", true);
        $this->iDataSource->addColumnControl("order_payments", "column_list", "payment_time,payment_method_id,amount,shipping_charge,tax_charge,handling_charge,authorization_code,transaction_identifier");
        $this->iDataSource->addColumnControl("order_payments", "list_table_controls", array("payment_method_id" => array("inline-width" => "150px"), "authorization_code" => array("inline-width" => "150px"), "transaction_identifier" => array("inline-width" => "150px")));

        $this->iDataSource->addColumnControl("order_total", "data_type", "decimal");
        $this->iDataSource->addColumnControl("order_total", "decimal-places", "2");
        $this->iDataSource->addColumnControl("order_total", "readonly", true);
        $this->iDataSource->addColumnControl("order_total", "form_label", "Order Total");

        $this->iDataSource->addColumnControl("total_payments", "data_type", "decimal");
        $this->iDataSource->addColumnControl("total_payments", "decimal-places", "2");
        $this->iDataSource->addColumnControl("total_payments", "readonly", true);
        $this->iDataSource->addColumnControl("total_payments", "form_label", "Total Payments");

        $this->iDataSource->addColumnControl("balance_due", "data_type", "decimal");
        $this->iDataSource->addColumnControl("balance_due", "decimal-places", "2");
        $this->iDataSource->addColumnControl("balance_due", "readonly", true);
        $this->iDataSource->addColumnControl("balance_due", "form_label", "Balance Due");

        $this->iDataSource->addColumnControl("referral_contact_id", "data_type", "select");
        $this->iDataSource->addColumnControl("referral_contact_id", "get_choices", "referrerChoices");
        $this->iDataSource->addColumnControl("referral_contact_id", "form_label", "Referrer");
    }

    function referrerChoices($showInactive = false) {
        $referrerChoices = array();
        $resultSet = executeQuery("select contact_id from contacts where client_id = ? and deleted = 0 and contact_id in (select contact_id from contact_categories where category_id in (select category_id from categories where category_code = 'REFERRER'))", $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            $displayName = getDisplayName($row['contact_id']);
            $referrerChoices[$row['contact_id']] = array("key_value" => $row['contact_id'], "description" => $displayName, "inactive" => false);
        }
        freeResult($resultSet);
        return $referrerChoices;
    }

    function afterGetRecord(&$returnArray) {
        $totalPayments = 0;
        $resultSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) as total_payments from order_payments where order_id = ? and deleted = 0", $returnArray['primary_id']['data_value']);
        if ($row = getNextRow($resultSet)) {
            $totalPayments = $row['total_payments'];
        }
        $returnArray['total_payments'] = array("data_value" => $totalPayments);
        if (!is_array($returnArray['select_values'])) {
            $returnArray['select_values'] = array();
        }
        $returnArray['select_values']['address_id'] = array();
        $resultSet = executeQuery("select * from addresses where contact_id = ? order by address_1", $returnArray['contact_id']['data_value']);
        while ($row = getNextRow($resultSet)) {
            $returnArray['select_values']['address_id'][] = array("key_value" => $row['address_id'], "description" => $row['address_1'] . ", " . $row['city']);
        }
        $contactRow = Contact::getContact($returnArray['contact_id']['data_value']);
        $returnArray['billing_first_name'] = array("data_value" => $contactRow['first_name']);
        $returnArray['billing_last_name'] = array("data_value" => $contactRow['last_name']);
        $returnArray['billing_business_name'] = array("data_value" => $contactRow['business_name']);
        $returnArray['billing_address_1'] = array("data_value" => $contactRow['address_1']);
        $returnArray['billing_city'] = array("data_value" => $contactRow['city']);
        $returnArray['billing_state'] = array("data_value" => $contactRow['state']);
        $returnArray['billing_state_select'] = array("data_value" => $contactRow['state']);
        $returnArray['billing_postal_code'] = array("data_value" => $contactRow['postal_code']);
        $returnArray['billing_country_id'] = array("data_value" => $contactRow['country_id']);
    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#order_number").attr("title", "Click to open in Order Dashboard").addClass("tooltip-element");

            $(document).on("click", "#order_number", function () {
                goToLink("/orderdashboard.php?url_page=show&clear_filter=true&primary_id=" + $("#primary_id").val());
            });

            $(document).on("change", "#tax_charge,#shipping_charge,#handling_charge,.quantity,.sale-price", function () {
                calculateOrderTotal();
            });

            $(document).on("click", ".deleted", function () {
                calculateOrderTotal();
            });

            $(document).on("change", "#billing_country_id", function () {
                if ($(this).val() == "1000") {
                    $("#billing_state").closest(".basic-form-line").addClass("hidden");
                    $("#billing_state_select").closest(".basic-form-line").removeClass("hidden");
                } else {
                    $("#billing_state").closest(".basic-form-line").removeClass("hidden");
                    $("#billing_state_select").closest(".basic-form-line").addClass("hidden");
                }
            });

            $(document).on("change", "#billing_state_select", function () {
                $("#billing_state").val($("#billing_state_select").val());
            });

            $(document).on("click", "#capture_new_payment", function () {
                $('#_new_payment_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                    width: 1000,
                    title: 'Authorize New Payment',
                    buttons: {
                        Charge: function (event) {
                            if ($("#_charge_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_new_payment&order_id=" + $("#primary_id").val(), $("#_charge_form").serialize(), function (returnArray) {
                                    if (!("error_messsage" in returnArray) && "order_payment" in returnArray) {
                                        addEditableListRow("order_payments", returnArray['order_payment']);
                                        let totalPayments = parseFloat($("#total_payments").val());
                                        totalPayments += parseFloat(returnArray['charge_amount']);
                                        $("#total_payments").val(RoundFixed(totalPayments,2,true));
                                        calculateOrderTotal();
                                        $("#_new_payment_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_new_payment_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });

            $(document).on("change", ".billing-amount", function () {
                let totalCharge = 0;
                $(".billing-amount").each(function () {
                    let thisAmount = $(this).val();
                    if (!empty(thisAmount) && !isNaN(thisAmount)) {
                        totalCharge += parseFloat(thisAmount);
                    }
                });
                $("#billing_total_charge").val(RoundFixed(totalCharge, 2, true));
            });
        </script>
        <?php
    }

    function javascript() {
        ?>
        <script>
            function afterGetRecord() {
                calculateOrderTotal();
                $("#billing_country_id").trigger("change");
            }

            function calculateOrderTotal() {
                let orderTotal = RoundFixed(parseFloat($("#tax_charge").val()) + parseFloat($("#shipping_charge").val()) + parseFloat($("#handling_charge").val()), 2, true);
                $("#_order_items_table").find(".editable-list-data-row").each(function () {
                    if ($(this).find(".deleted").prop("checked")) {
                        return true;
                    }
                    orderTotal = RoundFixed(parseFloat(orderTotal) + parseFloat($(this).find(".quantity").val()) * parseFloat($(this).find(".sale-price").val()), 2, true);
                });
                $("#order_total").val(orderTotal);
                let totalPayments = parseFloat($("#total_payments").val());
                let balanceDue = orderTotal - totalPayments;
                $("#balance_due").val(RoundFixed(balanceDue, 2, true));
            }
        </script>
        <?php
    }

    function afterSaveChanges($nameValues,$actionPerformed) {
        executeQuery("insert into order_notes (order_id, user_id, content) values (?,?,?)",
            $nameValues['primary_id'], $GLOBALS['gUserId'], "Order edited by " . getUserDisplayName() . " at " . date("m/d/Y h:i:s a"));
        coreSTORE::orderNotification($nameValues['primary_id'], "order_edited");
        return true;
    }

    function hiddenElements() {
        ?>
        <div class='dialog-box' id="_new_payment_dialog">
            <form id="_charge_form">
                <div class="basic-form-line inline-block" id="_billing_first_name_row">
                    <label for="billing_first_name" class="required-label">First Name</label>
                    <input tabindex="10" type="text" class="validate[required]" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_last_name_row">
                    <label for="billing_last_name" class="required-label">Last Name</label>
                    <input tabindex="10" type="text" class="validate[required]" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_billing_business_name_row">
                    <label for="billing_business_name">Business Name</label>
                    <input tabindex="10" type="text" class="" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_billing_address_1_row">
                    <label for="billing_address_1" class="required-label">Street</label>
                    <input tabindex="10" type="text" class="validate[required]" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_city_row">
                    <label for="billing_city" class="required-label">City</label>
                    <input tabindex="10" type="text" class="validate[required]" size="30" maxlength="60" id="billing_city" name="billing_city" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_state_row">
                    <label for="billing_state" class="">State</label>
                    <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="30" id="billing_state" name="billing_state" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_state_select_row">
                    <label for="billing_state_select" class="required-label">State</label>
                    <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]">
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
                    <label for="billing_postal_code" class="required-label">Postal Code</label>
                    <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" id="billing_postal_code" name="billing_postal_code" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_billing_country_id_row">
                    <label for="billing_country_id" class="required-label">Country</label>
                    <select tabindex="10" class="validate[required]" id="billing_country_id" name="billing_country_id">
                        <?php
                        foreach (getCountryArray() as $countryId => $countryName) {
                            ?>
                            <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_amount_row">
                    <label for="billing_amount" class="required-label">Amount</label>
                    <input tabindex="10" type="text" class="billing-amount align-right validate[required,custom[number]]" data-decimal-places='2' size="10" maxlength="10" id="billing_amount" name="billing_amount" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_shipping_charge_row">
                    <label for="billing_shipping_charge" class="required-label">Shipping Charge</label>
                    <input tabindex="10" type="text" class="billing-amount align-right validate[required,custom[number]]" data-decimal-places='2' size="10" maxlength="10" id="billing_shipping_charge" name="billing_shipping_charge" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='clear-div'></div>

                <div class="basic-form-line inline-block" id="_billing_tax_charge_row">
                    <label for="billing_tax_charge" class="required-label">Tax Charge</label>
                    <input tabindex="10" type="text" class="billing-amount align-right validate[required,custom[number]]" data-decimal-places='2' size="10" maxlength="10" id="billing_tax_charge" name="billing_tax_charge" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line inline-block" id="_billing_handling_charge_row">
                    <label for="billing_handling_charge" class="required-label">Handling Charge</label>
                    <input tabindex="10" type="text" class="billing-amount align-right validate[required,custom[number]]" data-decimal-places='2' size="10" maxlength="10" id="billing_handling_charge" name="billing_handling_charge" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='clear-div'></div>

                <div class="basic-form-line inline-block" id="_billing_total_charge_row">
                    <label for="billing_total_charge">Total Charge</label>
                    <input tabindex="10" type="text" class="align-right" readonly="readonly" size="10" maxlength="10" id="billing_total_charge" name="billing_total_charge" value="0.00">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_billing_payment_method_id_row">
                    <label for="billing_payment_method_id" class="required-label">Payment Method</label>
                    <select tabindex="10" class="validate[required]" id="billing_payment_method_id" name="billing_payment_method_id">
                        <option value=''>[Select]</option>
                        <?php
                        $resultSet = executeQuery("select * from payment_methods where inactive = 0 and client_id = ? and payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_CARD') order by description", $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            ?>
                            <option value="<?= $row['payment_method_id'] ?>"><?= htmlText($row['description']) ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_account_number_row">
                    <label for="account_number" class="required-label">Card Number</label>
                    <input tabindex="10" type="text" class="validate[required]" size="20" maxlength="20" id="account_number" name="account_number" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_expiration_month_row">
                    <label for="expiration_month" class="required-label">Expiration Date</label>
                    <select tabindex="10" class="expiration-date validate[required]" id="expiration_month" name="expiration_month">
                        <option value="">[Month]</option>
                        <?php
                        for ($x = 1; $x <= 12; $x++) {
                            ?>
                            <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
                            <?php
                        }
                        ?>
                    </select>
                    <select tabindex="10" class="expiration-date validate[required]" id="expiration_year" name="expiration_year">
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
                    <input tabindex="10" type="text" class="" size="10" maxlength="4" id="cvv_code" name="cvv_code" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <p class='error-message'></p>
            </form>
        </div>
        <?php
    }
}

$pageObject = new ThisPage("orders");
$pageObject->displayPage();