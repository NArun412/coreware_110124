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

$GLOBALS['gPageCode'] = "EVENTINCLASSPURCHASES";
require_once "shared/startup.inc";

class EventInClassPurchasesPage extends Page {

    function setup() {
        $orderCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDERS");
        if (empty($orderCustomFieldTypeId)) {
            $resultSet = executeQuery("insert into custom_field_types (custom_field_type_code, description) values ('ORDERS','Orders')");
            $orderCustomFieldTypeId = $resultSet['insert_id'];
        }
        $customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "IN_CLASS_PURCHASE_EVENT_ID",
            "custom_field_type_id = ?", $orderCustomFieldTypeId);
        if (empty($customFieldId)) {
            $resultSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label, internal_use_only) " .
                "values (?,'IN_CLASS_PURCHASE_EVENT_ID','In-class Purchase Event ID',?,'In-class Purchase Event ID',1)", $GLOBALS['gClientId'], $orderCustomFieldTypeId);
            $customFieldId = $resultSet['insert_id'];
            executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?,'data_type','int')", $customFieldId);
        }
        $preferenceArray = array(
            array('preference_code'=>'IN_CLASS_PURCHASE_PRODUCTS_CUSTOM_FIELD', 'description'=>'In-class purchase products Custom Field', 'data_type'=>'varchar',
                'detailed_description'=>'Event Type Custom field code to use for in-class purchase products. If not specified, the default is IN_CLASS_PURCHASE_PRODUCTS.'),
            array('preference_code'=>'IN_CLASS_PURCHASE_PRODUCTS_KEY', 'description'=>'In-class purchase products Custom Field','data_type'=>'varchar',
                'detailed_description'=>'If the custom field for in-class purchase products is a JSON object, this preference specifies which key to use for the product codes. If nothing is specified, the system will look for product codes in the root of the object.')
        );
        setupPreferences($preferenceArray);

        $filters = array();
	    $eventTypes = array();
	    $resultSet = executeQuery("select * from event_types where inactive = 0 and client_id = ? order by description", $GLOBALS['gClientId']);
	    while ($row = getNextRow($resultSet)) {
		    $eventTypes[$row['event_type_id']] = $row['description'];
	    }
	    if (!empty($eventTypes)) {
		    $filters['event_type'] = array("form_label" => "Event Type", "where" => "event_type_id = %key_value%", "data_type" => "select", "choices" => $eventTypes);
	    }
        $filters['start_date'] = array("form_label" => "Start Date On or After", "where" => "start_date >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
        $filters['end_date'] = array("form_label" => "Start Date Or or Before", "where" => "start_date <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
	    $this->iTemplateObject->getTableEditorObject()->addFilters($filters);
        $this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "changes"));
    }

	function massageDataSource() {
        $customFieldCode = getPreference("IN_CLASS_PURCHASE_PRODUCTS_CUSTOM_FIELD") ?: "IN_CLASS_PURCHASE_PRODUCTS";
        $customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", $customFieldCode,
            "custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'EVENT_TYPES')");
        $this->iDataSource->setFilterWhere("start_date <= current_date" .
            (empty($customFieldId) ? "" : " and event_type_id in (select primary_identifier from custom_field_data where custom_field_id = " . $customFieldId . ")"));
		$this->iDataSource->addColumnControl("event_id", "not_editable", true);
		$this->iDataSource->addColumnControl("start_date", "not_editable", true);
		$this->iDataSource->addColumnControl("description", "not_editable", true);
		$this->iDataSource->addColumnControl("location_id", "not_editable", true);
	}

    function executePageUrlActions() {
        $returnArray = array();
        switch ($_GET['url_action']) {
            case "place_order":
                $contactId = "";
                $orderItems = array();
                foreach($_POST as $name => $value) {
                    if(startsWith($name, "contact_product")) {
                        $nameParts = explode("_",$name);
                        $contactId = $contactId ?: $nameParts[2];
                        $orderItems[] = ["product_id"=>$nameParts[3], "quantity"=>$value];
                    }
                }
                if(empty($orderItems)) {
                    $returnArray['error_message'] = "No products to order";
                    ajaxResponse($returnArray);
                }
                if(empty($contactId)) {
                    $returnArray['error_message'] = "Contact ID is missing";
                    ajaxResponse($returnArray);
                }
                $locationId = $_POST['location_id'];
                $eventId = $_POST['event_id'];
                $customField = CustomField::getCustomFieldByCode("IN_CLASS_PURCHASE_EVENT_ID","ORDERS");
                $existingOrderId = getFieldFromId("order_id", "orders", "contact_id", $contactId,"order_id in " .
                    "(select primary_identifier from custom_field_data where integer_data = ? and custom_field_id = ?)", $eventId, $customField->getCustomFieldId());
                if(!empty($existingOrderId)) {
                    $returnArray['error_message'] = "Order has already been placed: Order ID " . $existingOrderId;
                    ajaxResponse($returnArray);
                }

                eCommerce::getClientMerchantAccountIds();
                $accountSet = executeQuery("select * from contacts join accounts using (contact_id) where accounts.contact_id = ? and " .
                    "inactive = 0 and account_token is not null", $contactId);
                if (!$accountRow = getNextRow($accountSet)) {
                    $returnArray['error_message'] = "Unable to get account for contact " . $contactId;
                    ajaxResponse($returnArray);
                }
                $accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
                $eCommerce = eCommerce::getEcommerceInstance($accountMerchantAccountId);
                $GLOBALS['gPrimaryDatabase']->startTransaction();

                $logEntry = "Order for in-class purchase placed by " . getUserDisplayName($GLOBALS['gUserId']) . " for contact ID " . $contactId . ":\n\n";
                $orderObject = new Order();
                $orderObject->setCustomerContact($accountRow['contact_id']);
                $orderObject->setOrderField("payment_method_id", $accountRow['payment_method_id']);

                $shoppingCart = ShoppingCart::getShoppingCartForContact($contactId, "IN_CLASS_PURCHASE");
                if (!$shoppingCart) {
                    $returnArray['error_message'] = "Unable to get shopping cart for contact " . $contactId;
                    ajaxResponse($returnArray);
                }
                $shoppingCart->removeAllItems();
                $productCatalog = new ProductCatalog();
                foreach($orderItems as $thisOrderItem) {
                    $salePrice = $productCatalog->getProductSalePrice($thisOrderItem['product_id']);
                    $shoppingCart->addItem(array("product_id" => $thisOrderItem['product_id'], "quantity" => $thisOrderItem['quantity'], "sale_price" => $salePrice['sale_price'], "set_quantity" => true));
                }

                $amount = 0;
                $quantity = 0;
                $shoppingCartItems = $shoppingCart->getShoppingCartItems();
                foreach ($shoppingCartItems as $thisItem) {
                    if ($thisItem['quantity'] > 0) {
                        $orderObject->addOrderItem(array("product_id" => $thisItem['product_id'], "quantity" => $thisItem['quantity'], "sale_price" => $thisItem['sale_price']));
                        $quantity += $thisItem['quantity'];
                        $amount += ($thisItem['quantity'] * $thisItem['sale_price']);
                        $productRow = ProductCatalog::getCachedProductRow($thisOrderItem['product_id']);
                        $logEntry .= $productRow['product_code'] . " | " . $productRow['description'] . " | " . $productRow['upc_code'] . " | " . $thisOrderItem['quantity'] . "\n";
                        $logEntry .= "Sale Price: " . $thisItem['sale_price'] . "\n\n";
                    }
                }
                $logEntry .= jsonEncode($_POST) . "\n";

                if ($quantity == 0) {
                    $returnArray['error_message'] = "Unable to create order for recurring payment because there are no products in the order";
                    $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                    ajaxResponse($returnArray);
                }

                $locationContactId = getFieldFromId("contact_id", "locations", "location_id", $locationId);
                $locationContactId = $locationContactId ?: $GLOBALS['gClientRow']['contact_id'];
                $taxCharge = $orderObject->getTax($locationContactId);
                $orderObject->setOrderField("tax_charge", $taxCharge);

                $paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $accountRow['payment_method_id']);
                if (empty($paymentMethodRow['flat_rate']) || $paymentMethodRow['flat_rate'] == 0) {
                    $paymentMethodRow['flat_rate'] = 0;
                }
                if (empty($paymentMethodRow['fee_percent']) || $paymentMethodRow['fee_percent'] == 0) {
                    $paymentMethodRow['fee_percent'] = 0;
                }
                $handlingCharge = round($paymentMethodRow['flat_rate'] + ($amount * $paymentMethodRow['fee_percent'] / 100), 2);
                $orderObject->setOrderField("handling_charge", $handlingCharge);

                $shippingCharge = 0;
                $orderObject->setOrderField("shipping_charge", $shippingCharge);

                $amount += $taxCharge;
                $amount += $handlingCharge;

                if (!$orderObject->generateOrder()) {
                    $returnArray['error_message'] = "Unable to create order: " . $orderObject->getErrorMessage();
                    $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                    $logEntry .= $returnArray['error_message'];
                    addProgramLog($logEntry);
                    ajaxResponse($returnArray);
                }

                $orderId = $orderObject->getOrderId();
                if ($amount > 0) {
                    $contactPayment = new ContactPayment($accountRow['contact_id'], $eCommerce);
                    if (!$contactPayment->setAccount($accountRow['account_id'])) {
                        $returnArray['error_message'] = "Unable to create order: Invalid account for payment";
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $logEntry .= $returnArray['error_message'];
                        addProgramLog($logEntry);
                        ajaxResponse($returnArray);
                    }

                    $parameters = array();
                    $parameters['order_object'] = $orderObject;
                    $parameters['amount'] = $amount;
                    $parameters['tax_charge'] = $taxCharge;
                    $parameters['handling_charge'] = $handlingCharge;
                    $parameters['shipping_charge'] = $shippingCharge;
                    $parameters['payment_method_id'] = $paymentMethodRow['payment_method_id'];
                    $result = $contactPayment->authorizeCharge($parameters);
                    if (!$result) {
                        $returnArray['error_message'] = "Unable to create order: " . $contactPayment->getErrorMessage();
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $logEntry .= $returnArray['error_message'];
                        addProgramLog($logEntry);
                        ajaxResponse($returnArray);
                        // todo: do we need a notification here?
                    }
                }

                $GLOBALS['gPrimaryDatabase']->commitTransaction();
                $logEntry .= "\nOrder completed, ID " . $orderId;
                addProgramLog($logEntry);

                CustomField::setCustomFieldData($orderId,"IN_CLASS_PURCHASE_EVENT_ID", $eventId, "ORDERS");
                Order::processOrderItems($orderId);
                Order::processOrderAutomation($orderId);
                if (function_exists("_localServerProcessOrder")) {
                    _localServerProcessOrder($orderId);
                }
                $emailId = getFieldFromId("email_id", "emails", "email_code", "IN_CLASS_PURCHASE_ORDER_CONFIRMATION", "inactive = 0");
                if(!empty($emailId)) {
                    $substitutions = array_merge(getRowFromId("contacts", "contact_id", $contactId), getRowFromId("orders", "order_id", $orderId), Order::getOrderItemsSubstitutions($orderId));
                    $substitutions['order_total'] = number_format($amount, 2, ".", ",");
                    $substitutions['tax_charge'] = number_format($taxCharge, 2, ".", ",");
                    $substitutions['shipping_charge'] = number_format($shippingCharge, 2, ".", ",");
                    $substitutions['handling_charge'] = number_format($handlingCharge, 2, ".", ",");
            		$substitutions['order_date'] = date("m/d/Y");
                    sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "contact_id" => $contactId, "email_addresses" => $substitutions['email_address']));
                }

                Order::notifyCRM($orderId);
                coreSTORE::orderNotification($orderId, "order_created");
                Order::reportOrderToTaxjar($orderId);
                $returnArray["info_message"] = "Order " . $orderId . " placed successfully.";
                $returnArray["order_id"] = $orderId;
                ajaxResponse($returnArray);
        }
    }

    function javascript() {
        ?>
        <script>
            function afterGetRecord(returnArray) {
                $("#orders_table").html(returnArray['orders_table']);
            }
        </script>
        <?php
    }

    function onLoadJavascript() {
		?>
        <script>
            $("#orders_table").on("change",".product-quantity",function () {
                let total = 0.0;
                $(this).closest("tr").find("input").each(function () {
                    if (!empty($(this).val()) && !isNaN($(this).val()) && !isNaN($(this).data("price"))) {
                        total += parseInt($(this).val()) * parseFloat($(this).data("price"));
                    }
                })
                $(this).closest("tr").find(".total-cell").text(Number(total).toFixed(2));
                if(total == 0.0) {
                    disableButtons($(this).closest("tr").find("button[id^='place_order']"));
                } else {
                    enableButtons($(this).closest("tr").find("button[id^='place_order']"));
                }
            });
            $("#orders_table").on("tap click","button[id^='place_order']", function () {
                let orderButton = $(this);
                let postData = {event_id: $("#event_id").val(), location_id: $("#location_id").val()};
                $(this).closest("tr").find("input").each(function () {
                    if (!empty($(this).val()) && !isNaN($(this).val())) {
                        postData[$(this).attr('id')] = $(this).val();
                    }
                })
                $('#confirm_place_order_name').text($(this).closest("tr").find(".name-cell").text());
                $('#confirm_place_order_amount').text($(this).closest("tr").find(".total-cell").text());
                $('#_confirm_place_order_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Place Order',
                        buttons: {
                            Confirm: function (event) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=place_order",postData, function(returnArray){
                                    if(!('error_message' in returnArray)) {
                                        let orderLink = "<a target='_blank' href='/order-dashboard?clear_filter=true&url_page=show&primary_id=%order_id%'>Order %order_id%</a>";
                                        let thisTr = orderButton.closest("tr");
                                        thisTr.find(".order-cell").html(orderLink.replaceAll('%order_id%',returnArray['order_id']));
                                        thisTr.find("input").each(function () {
                                            $(this).val('').prop("disabled", true);
                                        })
                                    }
                                    $("#_confirm_place_order_dialog").dialog('close');
                                });
                            },
                            Cancel: function (event) {
                                $("#_confirm_place_order_dialog").dialog('close');
                            }
                        }
                    });

                return false;
            });
        </script>
		<?php
	}

    function internalCSS() {
        ?>
        <style>
        .order-cell {
            text-align: center;
        }
        .total-cell {
            text-align: right;
        }
        </style>
        <?php
    }

    function hiddenElements() {
        ?>
        <div id="_confirm_place_order_dialog" class="dialog-box">
            <p>An order will be placed for <span id="confirm_place_order_name"></span>, and the card on file will be charged $<span id="confirm_place_order_amount"></span>.
                Do you want to continue?</p>
        <?php
    }
    function afterGetRecord(&$returnArray) {
        ob_start();
		$eventRow = getRowFromId("events","event_id", $returnArray['event_id']['data_value']);
        $customFieldCode = getPreference("IN_CLASS_PURCHASE_PRODUCTS_CUSTOM_FIELD") ?: "IN_CLASS_PURCHASE_PRODUCTS";
		$inClassProducts = CustomField::getCustomFieldData($eventRow['event_type_id'], $customFieldCode, "EVENT_TYPES");
        $customFieldKey = getPreference("IN_CLASS_PURCHASE_PRODUCTS_KEY");
        $productArray = json_decode($inClassProducts, true);
        if(empty($productArray)) {
            $productArray = array();
            foreach(explode("|", $inClassProducts) as $thisProduct) {
                if(!empty(trim($thisProduct))) {
                    [$productCode,$quantity] = explode(":", $thisProduct);
                    $productArray[] = array("product_code"=>$productCode,"quantity"=>$quantity);
                }
            }
        }
        if(!empty($customFieldKey)) {
            $productArray = $productArray[$customFieldKey];
        }
        if(empty($productArray)) {
            if(empty($customFieldKey)) {
                echo "<p>No in-class purchase products found in the field " . $customFieldCode . " for this event type. Custom field can be set in client preferences.</p>";
            } else {
                echo "<p>No in-class purchase products found in the " . $customFieldKey . " key of field " . $customFieldCode . " for this event type. Custom field can be set in client preferences.</p>";
            }
            $returnArray['orders_table'] = ob_get_clean();
            return;
        }

        $productHeader = "";
        $productOrderingRow = "<td class='order-cell'>%order_button%</td>";
        $noAccountRow = "<td></td>";
        $productCatalog = new ProductCatalog();
        $customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDERS");
        $customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "IN_CLASS_PURCHASE_EVENT_ID",
            "custom_field_type_id = ?", $customFieldTypeId);
        foreach($productArray as $thisProduct) {
            $productRow = ProductCatalog::getCachedProductRow(getFieldFromId("product_id", "products", "product_code", $thisProduct['product_code']));
            $salePrice = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_row"=>$productRow))['sale_price'];
            $productHeader .= sprintf("<th>%s: %s</th>", $productRow['description'], $salePrice);
            $productOrderingRow .= sprintf("<td><input class='product-quantity' id='contact_product_%%contact_id%%_%s' data-price='%s' %%disabled%%></td>",$productRow['product_id'], $salePrice);
            $noAccountRow .= "<td><input class='no-account' disabled></td>";
        }
        $productHeader .= "<th>Total</th>";
        $productOrderingRow .= "<td id='total_%contact_id%' class='total-cell'></td>";
        $noAccountRow .= "<td class='total-cell'></td>";
        ?>
        <table><tr><th>Contact</th><th class="order-cell">Order</th><?=$productHeader?></tr>
        <?php
		$resultSet = executeQuery("select *, (select account_id from accounts where contact_id = event_registrants.contact_id and account_token is not null and inactive = 0 limit 1) account_id " .
			" from event_registrants where event_id = ?", $_GET['primary_id']);
		while($row = getNextRow($resultSet)) {
            if(empty($row['account_id'])) {
                $thisOrderingRow = $noAccountRow;
            } else {
                $orderId = getFieldFromId("order_id", "orders", "contact_id", $row['contact_id'],
                    "order_id in (select primary_identifier from custom_field_data where custom_field_id = ? and integer_data = ?)", $customFieldId, $eventRow['event_id']);
                if(empty($orderId)) {
                    $thisOrderingRow = str_replace("%order_button%", "<button id='place_order_%contact_id%' class='disabled-button' disabled>Place Order</button>", $productOrderingRow);
                    $thisOrderingRow = str_replace("%disabled%", "", $thisOrderingRow);
                } else {
                    $thisOrderingRow = str_replace("%order_button%",  sprintf("<a target='_blank' href='/order-dashboard?clear_filter=true&url_page=show&primary_id=%s'>Order %s</a>", $orderId, $orderId), $productOrderingRow);
                    $thisOrderingRow = str_replace("%disabled%", "disabled", $thisOrderingRow);
                }
                $thisOrderingRow = str_replace("%contact_id%", $row['contact_id'], $thisOrderingRow);
            }
            ?>
            <tr><td class="name-cell"><?=getDisplayName($row['contact_id'])?></td><?=$thisOrderingRow?></tr>
            <?php
		}
	    ?></table><?php
        $returnArray['orders_table'] = ob_get_clean();
	}

}

$pageObject = new EventInClassPurchasesPage("events");
$pageObject->displayPage();
