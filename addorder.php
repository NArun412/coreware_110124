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

$GLOBALS['gPageCode'] = "ADDORDER";
require_once "shared/startup.inc";

class AddOrderPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_accounts":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
				} else {
					$returnArray['accounts'] = array();
					$resultSet = executeQuery("select * from accounts where inactive = 0 and contact_id = ?", $contactId);
					while ($row = getNextRow($resultSet)) {
						$description = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . " " . str_replace("X", "", $row['account_number']);
						$returnArray['accounts'][] = array("key_value" => $row['account_id'], "description" => $description);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_product_price":
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($_GET['product_id']);
				$salePrice = $salePriceInfo['sale_price'];
				if ($salePrice) {
					$returnArray['sale_price'] = $salePrice;
				}
				$returnArray['description'] = getFieldFromId("description", "products", "product_id", $_GET['product_id']);
				ajaxResponse($returnArray);
				break;
			case "get_addresses":
				$addresses = array();
				$resultSet = executeQuery("select * from addresses where contact_id = ?", $_GET['contact_id']);
				while ($row = getNextRow($resultSet)) {
					$addresses[] = array("key_value" => $row['address_id'], "description" => $row['address_1'] . ", " . $row['city'] . ", " . $row['state'] . " " . $row['postal_code']);
				}
				$returnArray['addresses'] = $addresses;
				$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $_GET['contact_id']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['phone_number'] = $row['phone_number'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "list"));
		}
	}

	function massageDataSource() {
		$valuesArray = Page::getPagePreferences();
		$this->iDataSource->addColumnControl("order_method_id", "initial_value", $valuesArray['order_method_id']);
		$this->iDataSource->addColumnControl("shipping_method_id", "initial_value", $valuesArray['shipping_method_id']);

		$this->iDataSource->addColumnControl("order_items", "data_type", "custom");
		$this->iDataSource->addColumnControl("order_items", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("order_items", "form_label", "Order Items");
		$this->iDataSource->addColumnControl("order_items", "list_table", "order_items");
		$this->iDataSource->addColumnControl("order_items", "column_list", "product_id,description,quantity,sale_price");
		$this->iDataSource->addColumnControl("order_items", "list_table_controls", array("description" => array("form_label" => "Description", "classes" => "product-description"), "quantity" => array("minimum_value" => "1", "classes" => "order-total quantity"), "product_id" => array("classes" => "product-id"), "sale_price" => array("classes" => "order-total sale-price")));

		$this->iDataSource->addColumnControl("order_payments", "data_type", "custom");
		$this->iDataSource->addColumnControl("order_payments", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("order_payments", "form_label", "Order Payments");
		$this->iDataSource->addColumnControl("order_payments", "list_table", "order_payments");
		$this->iDataSource->addColumnControl("order_payments", "column_list", "payment_time,account_id,payment_method_id,reference_number,amount,authorization_code,transaction_identifier");
		$this->iDataSource->addColumnControl("order_payments", "list_table_controls", array("payment_time" => array("default_value" => "", "not_null" => false, "data_type" => "date", "form_label" => "Date (leave blank for now)"), "payment_method_id" => array("not_null" => true), "account_id" => array("data_type" => "select", "classes" => "account-id", "choices" => array())));

		$this->iDataSource->addColumnControl("contact_id", "data_type", "contact_picker");
		$this->iDataSource->addColumnControl("contact_id", "show_id_field", true);

		$this->iDataSource->addColumnControl("full_name", "help_label", "Name on order");
		$this->iDataSource->addColumnControl("full_name", "not_null", true);
		$this->iDataSource->addColumnControl("shipping_method_id", "not_null", true);
		$this->iDataSource->addColumnControl("address_id", "choices", array());
		$this->iDataSource->addColumnControl("address_id", "empty_text", "[Primary Address]");
		$this->iDataSource->addColumnControl("order_total", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_total", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_total", "readonly", true);
		$this->iDataSource->addColumnControl("order_total", "form_label", "Order Total");
		$this->iDataSource->addColumnControl("shipping_charge", "classes", "order-total");
		$this->iDataSource->addColumnControl("tax_charge", "classes", "order-total");
		$this->iDataSource->addColumnControl("handling_charge", "classes", "order-total");

		$this->iDataSource->addColumnControl("designation_id", "data-conditional-required", "parseFloat($(\"#donation_amount\").val()) > 0");
		$this->iDataSource->addColumnControl("designation_id", "data_type", "select");
		$this->iDataSource->addColumnControl("designation_id", "get_choices", "designationChoices");
		$this->iDataSource->addColumnControl("designation_id", "form_label", "Designation");
		$this->iDataSource->addColumnControl("designation_id", "not_null", true);
		$this->iDataSource->addColumnControl("designation_id", "no_required_label", true);

		$this->iDataSource->addColumnControl("donation_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("donation_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("donation_amount", "form_label", "Donation");
		$this->iDataSource->addColumnControl("donation_amount", "classes", "order-total");
		$this->iDataSource->addColumnControl("donation_amount", "not_null", true);
		$this->iDataSource->addColumnControl("donation_amount", "default_value", "0.00");
	}

	function designationChoices($showInactive = false) {
		$designationChoices = array();
		$resultSet = executeQuery("select * from designations where inactive = 0 and requires_attention = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$designationChoices[$row['designation_id']] = array("key_value" => $row['designation_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $designationChoices;
	}

	function javascript() {
		?>
        <script>
            function calculateOrderTotal() {
                let orderTotal = 0;
                const $shippingCharge = $("#shipping_charge");
                if (!empty($shippingCharge.val())) {
                    orderTotal += parseFloat($shippingCharge.val());
                }
                const $taxCharge = $("#tax_charge");
                if (!empty($taxCharge.val())) {
                    orderTotal += parseFloat($taxCharge.val());
                }
                const $handlingCharge = $("#handling_charge");
                if (!empty($handlingCharge.val())) {
                    orderTotal += parseFloat($handlingCharge.val());
                }
                const $donationAmount = $("#donation_amount");
                if (!empty($donationAmount.val())) {
                    orderTotal += parseFloat($donationAmount.val());
                }
                $("#_order_items_table").find(".editable-list-data-row").each(function () {
                    const quantity = $(this).find(".quantity").val();
                    const salePrice = $(this).find(".sale-price").val();
                    if (!empty(quantity) && !empty(salePrice)) {
                        orderTotal += quantity * salePrice;
                    }
                });
                $("#order_total").val(RoundFixed(orderTotal, 2));
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("change", "#contact_id", function () {
                $(".account-id").val("").find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_accounts&contact_id=" + $(this).val(), function(returnArray) {
                        if ("accounts" in returnArray) {
                            for (let i in returnArray['accounts']) {
                                let thisOption = $("<option></option>").attr("value", returnArray['accounts'][i]['key_value']).text(returnArray['accounts'][i]['description']);
                                $(".account-id").append(thisOption);
                            }
                        }
                    });
                }
            });
            $(document).on("change", ".product-id", function () {
                const rowId = $(this).closest("tr").data("row_id");
                const productId = $(this).parent().find('input:hidden:first').val();
                $("#order_items_sale_price-" + rowId).val("");
                $("#order_items_description-" + rowId).val("");
                setTimeout(function () {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_price&product_id=" + productId, function(returnArray) {
                        const $orderItemsSalePrice = $("#order_items_sale_price-" + rowId);
                        if ("sale_price" in returnArray && empty($orderItemsSalePrice.val())) {
                            $orderItemsSalePrice.val(RoundFixed(returnArray['sale_price'], 2, true));
                        }
                        const $orderItemsDescription = $("#order_items_description-" + rowId);
                        if ("description" in returnArray && empty($orderItemsDescription.val())) {
                            $orderItemsDescription.val(returnArray['description']);
                            if (!empty(returnArray['description'])) {
                                setTimeout(function () {
                                    $("#order_items_quantity-" + rowId).focus();
                                }, 500);
                            }
                        }
                    });
                }, 500);
            });
            $(document).on("change", ".order-total", function () {
                calculateOrderTotal();
            });
            $("#full_name").focus(function () {
                const $fullName = $("#full_name");
                if (empty($fullName.val()) && !empty($("#contact_id_selector").val())) {
                    const contactNameParts = $("#contact_id_selector").find("option:selected").text().split(" • ");
                    $fullName.val(contactNameParts[0]);
                }
            });
            $("#contact_id").change(function () {
                $("#address_id").val("").find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_addresses&contact_id=" + $(this).val(), function(returnArray) {
                        if ("addresses" in returnArray) {
                            for (let i in returnArray['addresses']) {
                                $("#address_id").append($("<option></option>").attr("value", returnArray['addresses'][i]['key_value']).text(returnArray['addresses'][i]['description']));
                            }
                        }
                        const $phoneNumber = $("#phone_number");
                        if ("phone_number" in returnArray && empty($phoneNumber.val())) {
                            $phoneNumber.val(returnArray['phone_number']);
                        }
                    });
                }
            });
			<?php
			if (!empty($_GET['contact_id'])) {
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id'], "deleted = 0");
			if (!empty($contactId)) {
			?>
            setTimeout(function () {
                $("#contact_id").val(<?= $contactId ?>).trigger("change");
                $("#order_method_id").focus();
            }, 500);
			<?php } ?>
			<?php } ?>
        </script>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		$nameValues['order_number'] = -1;
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$orderId = $nameValues['primary_id'];
		$orderTotal = round($nameValues['shipping_charge'] + $nameValues['tax_charge'] + $nameValues['handling_charge'] + $nameValues['donation_amount'], 2);
		$paymentTotal = 0;
		$orderItemCount = 0;
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (startsWith($fieldName, "order_items_quantity-")) {
				$rowNumber = substr($fieldName, strlen("order_items_quantity-"));
				$quantity = $fieldValue;
				$salePrice = $nameValues['order_items_sale_price-' . $rowNumber];
				$orderTotal += round($quantity * $salePrice, 2);
				$orderItemCount += $quantity;
			}
			if (startsWith($fieldName, "order_payments_amount-")) {
				$paymentTotal += round($fieldValue, 2);
			}
		}
		$orderTotal = round($orderTotal, 2);
		$paymentTotal = round($paymentTotal, 2);
		$difference = round($orderTotal - $paymentTotal, 2);
		if ($difference != 0) {
			return "Payments total must equal order total: Payments: " . number_format($paymentTotal, 2) . ", Ordered: " . number_format($orderTotal, 2);
		}
		if ($orderItemCount <= 0) {
			return "No order items created";
		}
		if ($nameValues['donation_amount'] > 0) {
			if (empty($nameValues['designation_id'])) {
				return "Designation is required for donation";
			}
			$paymentMethodId = $nameValues['order_payments_payment_method_id-1'];
			$donationFee = Donations::getDonationFee(array("designation_id" => $nameValues['designation_id'], "amount" => $nameValues['donation_amount'], "payment_method_id" => $paymentMethodId));
			$donationCommitmentId = Donations::getContactDonationCommitment($nameValues['contact_id'], $nameValues['designation_id']);
			$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
				"designation_id,amount,donation_fee,donation_commitment_id) values (?,?,now(),?,?,?,?, ?)",
				$GLOBALS['gClientId'], $nameValues['contact_id'], $paymentMethodId, $nameValues['designation_id'], $nameValues['donation_amount'], $donationFee, $donationCommitmentId);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
			$donationId = $resultSet['insert_id'];
			executeQuery("update orders set donation_id = ? where order_id = ?", $donationId, $orderId);
            Donations::processDonation($donationId);
			Donations::completeDonationCommitment($donationCommitmentId);
		}
		$valuesArray = Page::getPagePreferences();
		$valuesArray['order_method_id'] = $nameValues['order_method_id'];
		$valuesArray['shipping_method_id'] = $nameValues['shipping_method_id'];
		Page::setPagePreferences($valuesArray);
		Order::processOrderItems($orderId);
		Order::processOrderAutomation($orderId);
		if (function_exists("_localServerProcessOrder")) {
			_localServerProcessOrder($orderId);
		}
        Order::notifyCRM($orderId);
		coreSTORE::orderNotification($orderId, "order_created");
        Order::reportOrderToTaxjar($orderId);
        return true;
	}

	function afterSaveDone($nameValues) {
		executeQuery("update orders set order_number = order_id where order_id = ?", $nameValues['primary_id']);
		executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $nameValues['primary_id'], $GLOBALS['gUserId'], "Order was manually created");
		$orderItemSet = executeQuery("select * from order_items where order_id = ?", $nameValues['primary_id']);
		$orderItemDataSource = new DataSource("order_items");
		while ($orderItemRow = getNextRow($orderItemSet)) {
			$productSet = executeQuery("select * from products left outer join product_data using (product_id) where products.product_id = ?", $orderItemRow['product_id']);
			$productRow = getNextRow($productSet);
			if (empty($orderItemRow['description'])) {
				$orderItemRow['description'] = $productRow['description'];
			}
			if (!empty($productRow['virtual_product']) && !empty($productRow['file_id'])) {
				$downloadDays = getPreference("DOWNLOAD_PRODUCT_DAYS");
				$productDownloadDays = CustomField::getCustomFieldData($productRow['product_id'], "DOWNLOAD_PRODUCT_DAYS", "PRODUCTS");
				if (!empty($productDownloadDays)) {
					$downloadDays = $productDownloadDays;
				}
				if (!empty($downloadDays) && $downloadDays > 0) {
					$downloadDate = date("m/d/Y", strtotime("+" . $downloadDays . " days"));
					$orderItemRow['download_date'] = $downloadDate;
				}
			}
			$orderItemRow['base_cost'] = $productRow['base_cost'];
			$orderItemRow['list_price'] = $productRow['list_price'];
			$orderItemDataSource->saveRecord(array("name_values" => $orderItemRow, "primary_id" => $orderItemRow['order_item_id']));
		}
		$orderTotal = round($nameValues['shipping_charge'] + $nameValues['tax_charge'] + $nameValues['handling_charge'] + $nameValues['donation_amount'], 2);
		$paymentTotal = 0;
		$orderItemCount = 0;
		$cartTotal = $nameValues['donation_amount'];
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (startsWith($fieldName, "order_items_quantity-")) {
				$rowNumber = substr($fieldName, strlen("order_items_quantity-"));
				$quantity = $fieldValue;
				$salePrice = $nameValues['order_items_sale_price-' . $rowNumber];
				$orderTotal += round($quantity * $salePrice, 2);
				$cartTotal += round($quantity * $salePrice, 2);
				$orderItemCount += $quantity;
			}
			if (startsWith($fieldName, "order_payments_amount-")) {
				$paymentTotal += round($fieldValue, 2);
			}
		}
		$orderTotal = round($orderTotal, 2);
		$resultSet = executeQuery("select * from order_payments where order_id = ?", $nameValues['primary_id']);
		$paymentCount = $resultSet['row_count'];
		while ($row = getNextRow($resultSet)) {
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']));
			if ($paymentCount == 1) {
				executeQuery("update order_payments set amount = ?,shipping_charge = ?,tax_charge = ?,handling_charge = ? where order_payment_id = ?", $cartTotal, $nameValues['shipping_charge'], $nameValues['tax_charge'], $nameValues['handling_charge'], $row['order_payment_id']);
			} else {
				$thisAmount = $row['amount'];
				$shippingCharge = round(($nameValues['shipping_charge'] * ($thisAmount / $orderTotal)), 2);
				$taxCharge = round(($nameValues['tax_charge'] * ($thisAmount / $orderTotal)), 2);
				$handlingCharge = round(($nameValues['handling_charge'] * ($thisAmount / $orderTotal)), 2);
				executeQuery("update order_payments set amount = ?,shipping_charge = ?,tax_charge = ?,handling_charge = ? where order_payment_id = ?", $thisAmount - $shippingCharge - $taxCharge - $handlingCharge, $shippingCharge, $taxCharge, $handlingCharge, $row['order_payment_id']);
			}
			if ($paymentMethodTypeCode == "INVOICE" || $paymentMethodTypeCode == "LAYAWAY") {
				$dateDue = "";
				$invoiceDays = getPreference("LAYAWAY_INVOICE_DAYS");
				if (!empty($invoiceDays)) {
					$dateDue = date("Y-m-d", strtotime("+" . $invoiceDays . " days"));
				}
				$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "invoice_type_code", $paymentMethodTypeCode);
				if (empty($invoiceTypeId) && $paymentMethodTypeCode == "LAYAWAY") {
					$insertSet = executeQuery("insert into invoice_types (client_id,invoice_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], 'LAYAWAY', 'Layaway Invoice');
					$invoiceTypeId = $insertSet['insert_id'];
				}
				$insertSet = executeQuery("insert into invoices (client_id,invoice_number,contact_id, invoice_type_id,invoice_date,date_due,notes) values (?,?,?,?,current_date,?,'Invoice for manually added order')",
					$GLOBALS['gClientId'], $nameValues['primary_id'], $nameValues['contact_id'], $invoiceTypeId, $dateDue);
				$invoiceId = $insertSet['insert_id'];
				executeQuery("insert into invoice_details (invoice_id,detail_date,description,amount,unit_price) values (?,current_date,?,1,?)",
					$invoiceId, "Order #" . $nameValues['primary_id'], $row['amount']);
				executeQuery("update order_payments set invoice_id = ?, notes = 'Created invoice for manually added order' where order_payment_id = ?", $invoiceId, $row['order_payment_id']);
			}
		}
	}
}

$_GET['url_page'] = "new";
$pageObject = new AddOrderPage("orders");
$pageObject->displayPage();
