<?php

/*		This software is the unpublished, confidential, proprietary, intellectual
		property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
		or used in any manner without expressed written consent from Kim David Software, LLC.
		Kim David Software, LLC owns all rights to this work and intends to keep this
		software confidential so as to maintain its value as a trade secret.

		Copyright 2004-Present, Kim David Software, LLC.

		WARNING! This code is part of the Kim David Software's Coreware system.
		Changes made to this source file will be lost when new versions of the
		system are installed.
*/

$GLOBALS['gPageCode'] = "REFUNDDASHBOARD";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class RefundDashboardPage extends Page {

	var $iSearchContactFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");
	var $iSearchFields = array("full_name", "phone_number");

	function setup() {
		$this->iDataSource->addColumnControl("deleted", "data_type", "hidden");

		$this->iDataSource->addColumnControl("order_number", "form_label", "Order #");
		$this->iDataSource->addColumnControl("order_amount", "form_label", "Amount");
		$this->iDataSource->addColumnControl("order_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_amount", "not_sortable", true);
		$this->iDataSource->addColumnControl("order_amount", "select_value", "(select sum(order_items.sale_price * order_items.quantity) " .
			"from order_items where orders.order_id = order_items.order_id) + shipping_charge + tax_charge + handling_charge - order_discount");
		$this->iDataSource->addColumnControl("date_completed", "form_label", "Completed");

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("order_number", "full_name", "order_amount", "order_time", "order_status_id", "date_completed"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("signature");
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("save", "add"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);

			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and");
			$filters['start_date'] = array("form_label" => "Start Date", "where" => "order_time >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Date", "where" => "order_time <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");

			$orderStatuses = array();
			$resultSet = executeQuery("select * from order_status where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$orderStatuses[$row['order_status_id']] = $row['description'];
			}
			$filters['order_status'] = array("form_label" => "Order Status", "where" => "order_status_id = %key_value%", "data_type" => "select", "choices" => $orderStatuses, "conjunction" => "and");

			$paymentMethods = array();
			$resultSet = executeQuery("select * from payment_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$paymentMethods[$row['payment_method_id']] = $row['description'];
			}
			$filters['payment_method'] = array("form_label" => "Payment Method", "where" => "payment_method_id = %key_value% or order_id in (select order_id from order_payments where payment_method_id = %key_value%)", "data_type" => "select", "choices" => $paymentMethods, "conjunction" => "and");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnLikeColumn("public_access", "order_notes", "public_access");
		$this->iDataSource->addColumnControl("add_note", "data_type", "button");
		$this->iDataSource->addColumnControl("add_note", "button_label", "Add Note");
		$this->iDataSource->addColumnControl("add_note", "classes", "keep-visible");
		$this->iDataSource->addColumnLikeColumn("content", "order_notes", "content");
		$this->iDataSource->addColumnControl("content", "not_null", false);
		$this->iDataSource->addColumnControl("content", "classes", "keep-visible");

		$this->iDataSource->addColumnControl("address_id", "choices", array());
        $paymentMethods = array();
        $resultSet = executeQuery("select * from payment_methods where client_id = ? and payment_method_type_id in (select payment_method_type_id from payment_method_types where payment_method_type_code in ('GIFT_CARD','CREDIT_CARD'))",$GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            $paymentMethods[] = $row['payment_method_id'];
        }
		$this->iDataSource->addFilterWhere("deleted = 0");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "add_note":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				if (empty($_POST['content']) || empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("insert into order_notes (order_id,user_id,time_submitted,content,public_access) values (?,?,current_time,?,?)",
					$orderId, $GLOBALS['gUserId'], $_POST['content'], (empty($_POST['public_access']) ? 0 : 1));
				$returnArray['order_note'] = array("user_id" => getUserDisplayName(), "time_submitted" => date("m/d/Y g:ia"), "content" => $_POST['content'], "public_access" => (empty($_POST['public_access']) ? "" : "YES"));
				ajaxResponse($returnArray);
				break;
			case "process_refund":
				$pagePreferences = Page::getPagePreferences();
				$pagePreferences['refund_shipping'] = $_POST['refund_shipping'];
				$pagePreferences['restocking_fee'] = $_POST['restocking_fee'];
				Page::setPagePreferences($pagePreferences);
				Ecommerce::getClientMerchantAccountIds();

				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['primary_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$returnOrderItemIds = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (empty($fieldData) || substr($fieldName, 0, strlen("return_quantity_")) != "return_quantity_") {
						continue;
					}
					$orderItemRow = getRowFromId("order_items", "order_id", $_POST['primary_id'], "order_item_id = ? and deleted = 0", substr($fieldName, strlen("return_quantity_")));
					if (empty($orderItemRow)) {
						$returnArray['error_message'] = "Invalid Order Item";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if ($fieldData > $orderItemRow['quantity'] || !is_numeric($fieldData)) {
						$fieldData = $orderItemRow['quantity'];
					}
					$orderItemDataTable = new DataTable("order_items");
					$orderItemDataTable->setSaveOnlyPresent(true);
					if ($fieldData == $orderItemRow['quantity']) {
						if (!$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemRow['order_item_id']))) {
							$returnArray['error_message'] = $orderItemDataTable->getErrorMessage();
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$returnOrderItemIds[] = $orderItemRow['order_item_id'];
					} else {
						$refundedTaxCharge = round($fieldData * $orderItemRow['tax_charge'] / $orderItemRow['quantity'], 2);
						if (!$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1, "quantity" => $fieldData, "tax_charge" => $refundedTaxCharge), "primary_id" => $orderItemRow['order_item_id']))) {
							$returnArray['error_message'] = $orderItemDataTable->getErrorMessage();
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$returnOrderItemIds[] = $orderItemRow['order_item_id'];
						$orderItemRow['quantity'] = $orderItemRow['quantity'] - $fieldData;
						$orderItemRow['tax_charge'] -= $refundedTaxCharge;
						$orderItemRow['order_item_id'] = "";
						if (!$orderItemDataTable->saveRecord(array("name_values" => $orderItemRow, "primary_id" => ""))) {
							$returnArray['error_message'] = $orderItemDataTable->getErrorMessage();
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

				$totalRefundAmount = 0;
				$refundOrderPaymentIds = array();
				$voidableIds = array();
				$creditCardPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "CREDIT_CARD");
				$giftCardPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "GIFT_CARD");
				$giftCardPaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "GIFT_CARD");
				$refundablePaymentMethodTypes = array($creditCardPaymentMethodTypeId, $giftCardPaymentMethodTypeId);
				foreach ($_POST as $fieldName => $fieldData) {
					if (empty($fieldData) || substr($fieldName, 0, strlen("refund_total_amount_")) != "refund_total_amount_") {
						continue;
					}
					$orderPaymentId = substr($fieldName, strlen("refund_total_amount_"));
					$thisTotalRefundAmount = str_replace(",", "", $fieldData);
					if (!is_numeric($totalRefundAmount)) {
						$returnArray['error_message'] = "Invalid Refund Amount";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$totalRefundAmount += $thisTotalRefundAmount;
					$orderPaymentRow = getRowFromId("order_payments", "order_id", $_POST['primary_id'], "order_payment_id = ? and deleted = 0", $orderPaymentId);
					if (empty($orderPaymentRow)) {
						$returnArray['error_message'] = "Invalid Order Payment #2911" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $orderPaymentId : "");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$accountRow = getRowFromId("accounts", "account_id", $orderPaymentRow['account_id']);
					$accountRow = $accountRow ?: array("payment_method_id" => $orderPaymentRow['payment_method_id'], "merchant_account_id" => $GLOBALS['gDefaultMerchantAccountId']);
                    if (empty($accountRow['merchant_account_id'])) {
	                    $accountRow['merchant_account_id'] = $GLOBALS['gDefaultMerchantAccountId'];
                    }
					$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $accountRow['payment_method_id']);
					if (empty($accountRow['merchant_account_id']) || !empty($orderPaymentRow['invoice_id'])
						|| ($paymentMethodRow['payment_method_type_id'] == $creditCardPaymentMethodTypeId && empty($orderPaymentRow['transaction_identifier']))
						|| !in_array($paymentMethodRow['payment_method_type_id'], $refundablePaymentMethodTypes)) {
						$returnArray['error_message'] = "Invalid Order Payment #5748 - " . jsonEncode($accountRow);
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) as total_amount, " .
						"sum(shipping_charge) as total_shipping_charge, sum(tax_charge) as total_tax_charge, sum(handling_charge) as total_handling_charge " .
						"from order_payments where order_id = ? and account_id = ? and deleted = 0", $orderPaymentRow['order_id'], $orderPaymentRow['account_id']);
					$totalRefundableAmount = 0;
					$totalShippingCharge = 0;
					$totalTaxCharge = 0;
					$totalHandlingCharge = 0;
					if ($row = getNextRow($resultSet)) {
						$totalRefundableAmount = $row['total_amount'];
						$totalShippingCharge = $row['total_shipping_charge'];
						$totalTaxCharge = $row['total_tax_charge'];
						$totalHandlingCharge = $row['total_handling_charge'];
					}
					if ($thisTotalRefundAmount > $totalRefundableAmount) {
						$returnArray['error_message'] = "Refund exceeds total charge on this payment method" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $thisTotalRefundAmount . ":" . $totalRefundableAmount : "");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if ($_POST['refund_shipping_charge_' . $orderPaymentId] > $totalShippingCharge) {
						$returnArray['error_message'] = "Shipping refund exceeds total shipping charge on this payment method" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $_POST['refund_shipping_charge_' . $orderPaymentId] . ":" . $totalShippingCharge : "");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if ($_POST['refund_tax_charge_' . $orderPaymentId] > $totalTaxCharge) {
						$returnArray['error_message'] = "Tax refund exceeds total tax charge on this payment method" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $_POST['refund_tax_charge_' . $orderPaymentId] . ":" . $totalTaxCharge : "");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if ($_POST['refund_handling_charge_' . $orderPaymentId] > $totalHandlingCharge) {
						$returnArray['error_message'] = "Handling refund exceeds total handling charge on this payment method" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $_POST['refund_handling_charge_' . $orderPaymentId] . ":" . $totalHandlingCharge : "");
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$orderPaymentDataTable = new DataTable("order_payments");
					$orderPaymentDataTable->setSaveOnlyPresent(true);

					$refundPaymentRow = $orderPaymentRow;

					// Try to use Void instead of refund if possible.  Criteria:
					// 1. $refundAmount matches original payment amount 100%
					// 2. payment_time is today OR payment is not yet captured
					$isVoidable = false;
					$paymentDate = date("Y-m-d", strtotime($orderPaymentRow['payment_time']));
					$today = date("Y-m-d");
					$originalPaymentAmount = round($orderPaymentRow['amount'] + $orderPaymentRow['tax_charge'] + $orderPaymentRow['shipping_charge'] + $orderPaymentRow['handling_charge'],2);
                    if($orderPaymentRow['not_captured']) {
                        if($thisTotalRefundAmount == $originalPaymentAmount) {
                            $isVoidable = true;
                        } else { // don't allow partial refunds on uncaptured payments
                            $returnArray['error_message'] = "Can not process partial refund on pre-authorization. Capture payment first.";
                            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                            ajaxResponse($returnArray);
                            break;
                        }
                    } else {
                        if ($thisTotalRefundAmount == $originalPaymentAmount && $paymentDate == $today) {
                            $isVoidable = true;
                        }
                    }
					$refundPaymentRow['amount'] = (empty($_POST['refund_amount_' . $orderPaymentId]) ? 0 : round((-1 * $_POST['refund_amount_' . $orderPaymentId]), 2));
					$refundPaymentRow['shipping_charge'] = (empty($_POST['refund_shipping_charge_' . $orderPaymentId]) ? 0 : round((-1 * $_POST['refund_shipping_charge_' . $orderPaymentId]), 2));
					$refundPaymentRow['tax_charge'] = (empty($_POST['refund_tax_charge_' . $orderPaymentId]) ? 0 : round((-1 * $_POST['refund_tax_charge_' . $orderPaymentId]), 2));
					$refundPaymentRow['handling_charge'] = (empty($_POST['refund_handling_charge_' . $orderPaymentId]) ? 0 : round((-1 * $_POST['refund_handling_charge_' . $orderPaymentId]), 2));
					$refundPaymentRow['payment_time'] = date("Y-m-d H:i:s");
                    if ($_POST['send_gift_card']) {
	                    $refundPaymentRow['authorization_code'] = "";
	                    $refundPaymentRow['transaction_identifier'] = "";
                    }
					$refundPaymentRow['notes'] = "Refund processed by " . getUserDisplayName() . (empty($_POST['send_gift_card']) ? "" : ". Gift card sent as refund.");
					if (!$orderPaymentId = $orderPaymentDataTable->saveRecord(array("name_values" => $refundPaymentRow, "primary_id" => ""))) {
						$returnArray['error_message'] = "Unable to process refund";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$refundOrderPaymentIds[] = $orderPaymentId;
					$voidableIds[$orderPaymentId] = $isVoidable;
				}
				$refundSucceeded = false;
				$response = "";

				if ($totalRefundAmount <= 0) {
					$returnArray['error_message'] = "No refund processed";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				if (empty($_POST['send_gift_card'])) {
					if (!empty($refundOrderPaymentIds)) {
						foreach ($refundOrderPaymentIds as $orderPaymentId) {
							$resultSet = executeQuery("select * from order_payments join accounts using (account_id) where order_payment_id = ?", $orderPaymentId);
							$orderPaymentRow = getNextRow($resultSet);
							if (!$orderPaymentRow) {
								if (!$refundSucceeded) {
									$returnArray['error_message'] = "Invalid Order Payment #5892";
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
								} else {
									$response .= "<p class='red-text'>Invalid Order Payment for one of the refunds. Refund will need to be processed manually.</p>";
								}
							}
							$refundAmount = round(-1 * ($orderPaymentRow['amount'] + $orderPaymentRow['shipping_charge'] + $orderPaymentRow['tax_charge'] + $orderPaymentRow['handling_charge']), 2);
							if ($orderPaymentRow['payment_method_id'] == $giftCardPaymentMethodId) {
								$giftCard = new GiftCard(array("gift_card_number" => $orderPaymentRow['account_number']));
								if (!$giftCard->isValid()) {
									$returnArray['error_message'] = "Gift card " . $orderPaymentRow['account_number'] . " not found.";
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
								}
                                if (!$giftCard->adjustBalance(false,$refundAmount,"Refund for order", $_POST['primary_id'])) {
									$returnArray['error_message'] = "Refund to gift card " . $orderPaymentRow['account_number'] . " failed: " . $giftCard->getErrorMessage();
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
								}
								$response .= "<p class='green-text'>Refund to gift card " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . " successfully processed.</p>";
							} else {
								$eCommerce = eCommerce::getEcommerceInstance($orderPaymentRow['merchant_account_id']);
								if (!$eCommerce) {
									if (!$refundSucceeded) {
										$returnArray['error_message'] = "Unable to connect to Merchant Gateway for refund for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . ". (5922)";
										$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
										ajaxResponse($returnArray);
									} else {
										$response .= "<p class='red-text'>Unable to connect to Merchant Gateway for refund for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . ". Refund is processed in Coreware, but refund will need to be manually processed in the merchant gateway. (7421)</p>";
									}
								}
								// If voidable, try void first
								$refundSuccess = false;
								$usedVoid = false;
								if ($voidableIds[$orderPaymentId]) {
									$refundSuccess = $eCommerce->voidCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier']));
									$usedVoid = $refundSuccess;
								}
								if (!$refundSuccess) {
									$refundSuccess = $eCommerce->refundCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier'], "amount" => $refundAmount, "card_number"=>substr($orderPaymentRow['account_number'],-4)));
								}
								$gatewayResponse = $eCommerce->getResponse();
								if (!$refundSuccess) {
									if (!$refundSucceeded) {
										$returnArray['error_message'] = ($GLOBALS['gUserRow']['superuser_flag'] ? jsonEncode($gatewayResponse) : $gatewayResponse['response_reason_text']) . " Refund not successful for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . ". (9421)";
										$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
										ajaxResponse($returnArray);
									} else {
										$response .= "<p class='red-text'>Unable to connect to Merchant Gateway for refund for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . ". Refund is processed in Coreware, but refund will need to be manually processed in the merchant gateway. (6491)</p>";
									}
								}
								if ($usedVoid) {
									$response .= "<p class='green-text'>Transaction on account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . " successfully voided.</p>";
								} else {
									$response .= "<p class='green-text'>Refund for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . " successfully processed.</p>";
								}
							}
							$refundSucceeded = true;
						}
					}
				} else {
                    $giftCard = new GiftCard();
                    $giftCardId = $giftCard->createRefundGiftCard(false,"Gift Card for Refund on Order ID " . $_POST['primary_id']);
                    if (!$giftCardId || !$giftCard->adjustBalance(true,$totalRefundAmount,"Refund for order", $_POST['primary_id'])) {
						$returnArray['error_message'] = "Unable to create Gift Card";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
                        break;
					}
					$emailId = getFieldFromId("email_id", "emails", "email_code", "REFUND_GIFT_CARD",  "inactive = 0");
					$substitutions = $GLOBALS['gUserRow'];
					$substitutions['order_id'] = $_POST['primary_id'];
					$substitutions['amount'] = $totalRefundAmount;
					$substitutions['description'] = "Gift Card for refund of order ID " . $_POST['primary_id'];
					$substitutions['product_code'] = "GIFT_CARD";
					$substitutions['gift_card_number'] = $giftCard->getGiftCardNumber();
					$substitutions['gift_message'] = "";
					$subject = "Gift Card for refund";
					$body = "Your gift card number is %gift_card_number%, for the amount of %amount%.";
					$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id", "orders", "order_id", $_POST['primary_id']));
					sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "substitutions" => $substitutions, "email_addresses" => $emailAddress));
					$response = "<p class='green-text'>A gift card has been issued</p>";
				}

				executeQuery("update orders set shipping_charge = (select sum(shipping_charge) from order_payments where order_id = orders.order_id and deleted = 0)," .
					"tax_charge = (select sum(tax_charge) from order_payments where order_id = orders.order_id and deleted = 0)," .
					"handling_charge = (select sum(handling_charge) from order_payments where order_id = orders.order_id and deleted = 0) where order_id = ?", $orderId);
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$resultSet = executeQuery("select count(*) as total_items,sum(quantity) as total_quantity,sum(tax_charge) as total_tax_charge from order_items where order_id = ? and deleted = 0", $orderId);
				$totalTaxCharge = 0;
				$totalItems = 0;
				$totalQuantity = 0;
				if ($row = getNextRow($resultSet)) {
					if (!empty($row['total_tax_charge'])) {
						$totalTaxCharge = $row['total_tax_charge'];
					}
					if (!empty($row['total_items'])) {
						$totalItems = $row['total_items'];
					}
					if (!empty($row['total_quantity'])) {
						$totalQuantity = $row['total_quantity'];
					}
				}
				if ($totalTaxCharge != $orderRow['tax_charge']) {
					$percentage = ($totalTaxCharge > 0 ? $orderRow['tax_charge'] / $totalTaxCharge : 0);
					$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $orderId);
					$totalRealTaxes = $orderRow['tax_charge'];
					$count = 0;
					while ($row = getNextRow($resultSet)) {
						$count++;
						$newTaxCharge = ($count == $resultSet['row_count'] ? $totalRealTaxes : round($row['tax_charge'] * $percentage, 2));
						executeQuery("update order_items set tax_charge = ? where order_item_id = ?", $newTaxCharge, $row['order_item_id']);
						$totalRealTaxes -= $newTaxCharge;
					}
				}
				if ($totalItems == 0) {
                    updateFieldById("deleted", 1, "orders", "order_id", $orderId, "client_id = ?", $GLOBALS['gClientId']);
                    $pickup = getReadFieldFromId("pickup", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					if (!$pickup || Order::hasPhysicalProducts($orderId)) {
						$emailAddresses = array();
						$resultSet = executeQuery("select email_address from shipping_method_notifications where shipping_method_id = ?", $orderRow['shipping_method_id']);
						while ($row = getNextRow($resultSet)) {
							$emailAddresses[] = $row['email_address'];
						}
						if (!empty($emailAddresses)) {
							$substitutions = $orderRow;
							$emailResult = sendEmail(array("subject" => "Order Deleted", "body" => "<p>Order ID %order_id% from %full_name% has been deleted.</p>", "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
						}
					}
				}

				if (empty($response)) {
					$returnArray['error_message'] = "Nothing Processed";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				$returnArray['response'] = $response;

				$taxjarApiToken = getPreference("taxjar_api_token");
				$taxjarApiReporting = getPreference("taxjar_api_reporting");
				if (!empty($taxjarApiToken) && !empty($taxjarApiReporting)) {
					Order::reportRefundToTaxjar($_POST['primary_id'], $returnOrderItemIds, $totalRefundAmount);
				}
				coreSTORE::orderNotification($orderId, "refund_issued");

				ajaxResponse($returnArray);
				break;
			case "set_status":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_POST['order_status_id']);
				$count = 0;
				if (!empty($orderIds) && !empty($orderStatusId)) {
					$resultSet = executeQuery("update orders set order_status_id = ? where client_id = ? and order_id in (" . implode(",", $orderIds) . ")",
						$orderStatusId, $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " orders changed";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'orders', 'order_id', $count . " order statuses set to " . getFieldFromId("description", "order_status", "order_status_id", $orderStatusId), jsonEncode($orderIds),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "get_answer":
				$returnArray['content'] = getFieldFromId("content", "help_desk_answers", "help_desk_answer_id", $_GET['help_desk_answer_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""));
				ajaxResponse($returnArray);
				break;
			case "get_order_shipments":
				$orderRow = getRowFromId("orders", "order_id", $_GET['order_id']);
				if (empty($orderRow)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderItemTotal = 0;
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$orderItemTotal += $row['sale_price'] * $row['quantity'];
				}

				$orderShipments = array();
				$resultSet = executeQuery("select *,(select order_number from remote_orders where remote_order_id = order_shipments.remote_order_id) order_number from order_shipments " .
					"where order_id = ? order by date_shipped", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$row['location'] = getFieldFromId("description", "locations", "location_id", $row['location_id']);
					$row['product_distributor_id'] = getFieldFromId("product_distributor_id", "locations", "location_id", $row['location_id']);
					$row['date_shipped'] = date("m/d/Y", strtotime($row['date_shipped']));
					$row['order_shipment_items'] = array();
					$itemSet = executeQuery("select *,(select sale_price from order_items where order_item_id = order_shipment_items.order_item_id) as sale_price," .
						"(select product_id from order_items where order_item_id = order_shipment_items.order_item_id) as product_id from order_shipment_items where " .
						"order_shipment_id = ?", $row['order_shipment_id']);
					$shipmentItemTotal = 0;
					while ($itemRow = getNextRow($itemSet)) {
						$itemRow['product_description'] = getFieldFromId("description", "products", "product_id", $itemRow['product_id']);
						$row['order_shipment_items'][] = $itemRow;
						$shipmentItemTotal += $itemRow['sale_price'] * $itemRow['quantity'];
					}
					if ($orderItemTotal == 0) {
						$shippingCharge = 0;
						$taxCharge = 0;
						$handlingCharge = 0;
					} else {
						$shippingCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['shipping_charge'];
						$taxCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['tax_charge'];
						$handlingCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['handling_charge'];
					}
					$row['shipment_amount'] = number_format($shipmentItemTotal + $shippingCharge + $taxCharge + $handlingCharge, 2, ".", "");
					$row['shipping_charge'] = (empty($row['shipping_charge']) ? "" : number_format($row['shipping_charge'], 2, ".", ""));
					$orderShipments[] = $row;
				}
				$returnArray['order_shipments'] = $orderShipments;
				ajaxResponse($returnArray);
				break;
			case "send_email":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$subject = $_POST['email_subject'];
				$body = $_POST['email_body'];
				if (empty($subject) || empty($body)) {
					$returnArray['error_message'] = "Required information is missing";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
				if (empty($emailAddress)) {
					$returnArray['error_message'] = "Customer has no email address";
					ajaxResponse($returnArray);
					break;
				}
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "EMAIL_SENT");
				if (empty($taskTypeId)) {
					$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
				}
				if (empty($taskTypeId)) {
					$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
					if (empty($taskAttributeId)) {
						$returnArray['error_message'] = "No Touchpoint Task Type";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'EMAIL_SENT','Email Sent')", $GLOBALS['gClientId']);
					$taskTypeId = $resultSet['insert_id'];
					executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
				}
				if (empty($taskTypeId)) {
					$returnArray['error_message'] = "No Touchpoint Task Type";
					ajaxResponse($returnArray);
					break;
				}
				$result = sendEmail(array("subject" => $subject, "body" => $body, "email_address" => $emailAddress));
				if ($result) {
					executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task) values " .
						"(?,?,?,?,now(),?,1)", $GLOBALS['gClientId'], $contactId, $subject, $body, $taskTypeId);
				} else {
					$returnArray['error_message'] = "Unable to send email";
				}
				ajaxResponse($returnArray);
				break;
			case "reopen_order":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id'], "date_completed is not null");
				if (empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				$ordersTable = new DataTable("orders");
				$ordersTable->setSaveOnlyPresent(true);
				$ordersTable->saveRecord(array("name_values" => array("date_completed" => ""), "primary_id" => $orderId));
				ajaxResponse($returnArray);
				break;
			case "mark_completed":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id'], "date_completed is null");
				if (empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				Order::markOrderCompleted($orderId);
				ajaxResponse($returnArray);
				break;
			case "get_order_items":
				$orderItems = array();
				$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
					$row['product_description'] = (empty($row['description']) ? $productRow['description'] : $row['description']);

					$customDataSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS' and client_id = ?)", $GLOBALS['gClientId']);
					while ($customDataRow = getNextRow($customDataSet)) {
						$customFieldData = CustomField::getCustomFieldData($row['order_item_id'], $customDataRow['custom_field_code'], "ORDER_ITEMS");
						if (empty($customFieldData)) {
							continue;
						}
						$row['product_description'] .= "<br><span class='highlighted-text'>" . $customDataRow['description'] . "</span>: " . $customFieldData;
					}
					$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $row['order_item_id']);
					while ($addonRow = getNextRow($addonSet)) {
						$salePrice = ($addonRow['quantity'] <= 1 ? $addonRow['sale_price'] : $addonRow['sale_price'] * $addonRow['quantity']);
						$row['product_description'] .= "<br>" . $addonRow['description'] . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")") . " - $" . number_format($salePrice, 2, ".", "");
					}
					$row['serializable'] = $productRow['serializable'];
					$upcCode = getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']);
					if (!empty($upcCode)) {
						$row['product_description'] .= "<br><span class='upc-code'>UPC: " . $upcCode . "</span>";
					}
					$row['total_price'] = $row['sale_price'] * $row['quantity'] + $row['tax_charge'];
					$row['sale_price'] = number_format($row['sale_price'], 2, ".", "");
					$row['total_price'] = number_format($row['total_price'], 2, ".", "");

					if (!empty($row['deleted'])) {
						$row['order_item_status_id'] = -1;
					}
					$orderItems[] = $row;
				}
				$returnArray['order_items'] = $orderItems;

				ajaxResponse($returnArray);

				break;
			case "save_order_status":
				Order::updateOrderStatus($_GET['order_id'], $_GET['order_status_id']);
				ajaxResponse($returnArray);
				break;
			case "save_order_item_status":
				$orderItemDataTable = new DataTable("order_items");
				$orderItemDataTable->setSaveOnlyPresent(true);
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_item_id", $_GET['order_item_id'], "order_id = ?", $_GET['order_id']);
				if (empty($orderItemId)) {
					$returnArray['error_message'] = "Invalid Order Item";
					ajaxResponse($returnArray);
					break;
				}
				if ($_GET['order_item_status_id'] == -1) {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId));
				} else {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 0, "order_item_status_id" => $_GET['order_item_status_id']), "primary_id" => $orderItemId));
				}
				$orderItemStatusCode = getFieldFromId("order_item_status_code", "order_item_statuses", "order_item_status_id", $_GET['order_item_status_id']);
				switch ($orderItemStatusCode) {
					case "BACKORDER":
						$orderItemRow = getRowFromId("order_items", "order_item_id", $orderItemId);
						$productId = $orderItemRow['product_id'];
						$productCatalog = new ProductCatalog();
						$emailAddress = getPreference("BACKORDERED_ITEM_AVAILABLE_NOTIFICATION");
						if (empty($emailAddress)) {
							$emailAddress = $GLOBALS['gUserRow']['email_address'];
						}
						$totalInventory = $productCatalog->getInventoryCounts(true, $productId, false, array("ignore_backorder" => true));
						if ($totalInventory <= 0) {
							$productInventoryNoticationId = getFieldFromId("product_inventory_notification_id", "product_inventory_notifications", "product_id", $productId);
							if (empty($productInventoryNoticationId)) {
								executeQuery("insert into product_inventory_notifications (product_id,user_id,email_address,comparator,quantity,order_quantity,place_order,use_lowest_price,allow_multiple) values " .
									"(?,?,?,?,?, ?,?,?,?)", $productId, $GLOBALS['gUserId'], $emailAddress, ">", 0, $orderItemRow['quantity'], 1, 1, 1);
							}
						}
						break;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			if (is_numeric($filterText) && strlen($filterText) >= 4) {
				$this->iDataSource->addFilterWhere("order_id = " . makeNumberParameter($filterText));
			} else {
				$parts = explode(" ", $filterText);
				$whereStatement = "order_id = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText);
				if (count($parts) == 2) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "(contact_id in (select contact_id from contacts where first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
						" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . "))";
				}
				foreach ($this->iSearchContactFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contacts where " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . ")";
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contact_identifiers where identifier_value = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where license_number = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "order_id in (select order_id from order_shipments where tracking_identifier = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . " order_id in (select order_id from order_items" .
					" where description like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") .
					" or order_item_id in (select order_item_id from order_item_serial_numbers where serial_number like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			}
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#add_note", function () {
                noteContent = textareaVal("content");
                if (empty(noteContent)) {
                    noteContent = $("#content").val();
                }
                if (!empty(noteContent)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_note", { content: noteContent, order_id: $("#primary_id").val(), public_access: $("#public_access").prop("checked") ? 1 : 0 }, function (returnArray) {
                        if ("order_note" in returnArray) {
                            $("#order_notes").find(".no-order-notes").remove();
                            let orderNoteBlock = $("#_order_note_template").html();
                            for (const j in returnArray['order_note']) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_note'][j]);
                            }
                            $("#order_notes").append(orderNoteBlock);
                            textareaVal("content", "");
                            $("#public_access").prop("checked", false);
                        }
                    });
                }
                return false;
            });
            $(document).on("click change", "#show_order_touchpoints", function () {
                $(".touchpoint").addClass("hidden");
                $(".touchpoint.order-" + $("#primary_id").val()).removeClass("hidden");
                if (!$(this).prop("checked")) {
                    $(".touchpoint").removeClass("hidden");
                }
            });
            $(document).on("change", ".refund-amount", function () {
                let totalAmount = 0;
                $(this).closest("tr").find(".refund-amount").each(function () {
                    const thisAmount = (empty($(this).val()) ? 0 : parseFloat($(this).val().replace(/,/g, '')));
                    totalAmount += thisAmount;
                });
                $(this).closest("tr").find(".total-amount").val(RoundFixed(totalAmount, 2, true));
            });
            $(document).on("click", "#process_refund", function () {
                let refundFound = false;
                $("#order_payments").find(".total-amount").each(function () {
                    if (empty($(this).val())) {
                        return true;
                    }
                    const thisRefund = parseFloat($(this).val().replace(/,/g, ''));
                    if (thisRefund > 0) {
                        refundFound = true;
                        return false;
                    }
                });
                if (!refundFound) {
                    displayErrorMessage("No refund entered");
                    return false;
                }
                if (!$("#_edit_form").validationEngine("validate")) {
                    displayErrorMessage("Fix errors and try again");
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=process_refund", $("#_edit_form").serialize(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#details_wrapper").html(returnArray['response']);
                    }
                });
                return false;
            });
            $(document).on("click", ".refund-all", function () {
                const orderPaymentId = $(this).closest("tr").data("order_payment_id");
                const refundShipping = $("#refund_shipping").prop("checked");
                let amount = parseFloat($("#order_payment_" + orderPaymentId).find(".amount").html().replace(/,/g, ''));
                let shippingCharge = (refundShipping ? parseFloat($("#order_payment_" + orderPaymentId).find(".shipping-charge").html().replace(/,/g, '')) : 0);
                let taxCharge = parseFloat($("#order_payment_" + orderPaymentId).find(".tax-charge").html().replace(/,/g, ''));
                let handlingCharge = (refundShipping ? parseFloat($("#order_payment_" + orderPaymentId).find(".handling-charge").html().replace(/,/g, '')) : 0);
                $("#refund_amount_" + orderPaymentId).val(amount).trigger("change");
                $("#refund_shipping_charge_" + orderPaymentId).val(shippingCharge).trigger("change");
                $("#refund_tax_charge_" + orderPaymentId).val(taxCharge).trigger("change");
                $("#refund_handling_charge_" + orderPaymentId).val(handlingCharge).trigger("change");
                return false;
            });
            $(document).on("click", "#refund_all", function () {
                if (!empty($("#restocking_fee_percentage").val())) {
                    $("#restocking_fee").val("");
                }
                $("#order_items").find(".return-quantity").each(function () {
                    $(this).val($(this).closest("tr").find(".quantity").html());
                });
                calculateRefund();
                return false;
            });
            $(document).on("click", "#refund_shipping", function () {
                calculateRefund();
            });
            $(document).on("change", "#restocking_fee", function () {
                calculateRefund();
            });
            $(document).on("change", ".return-quantity", function () {
                if (!empty($("#restocking_fee_percentage").val())) {
                    $("#restocking_fee").val("");
                }
                calculateRefund();
            });
            $("#_confirm_delete_dialog").find(".dialog-text").html("Are you sure you want to delete this order? Any loyalty points earned from this order will be permanently removed and loyalty point used to pay for the order will be restored.");
            $(document).on("click", "#show_deleted_payments", function () {
                if ($(this).prop('checked')) {
                    $("#order_payments").find("tr.deleted-payment").removeClass("hidden");
                } else {
                    $("#order_payments").find("tr.deleted-payment").addClass("hidden");
                }
            });
            $(document).on("change", "#help_desk_answer_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_answer&help_desk_answer_id=" + $(this).val(), function (returnArray) {
                        if ("content" in returnArray) {
                            CKEDITOR.instances['email_body'].setData(returnArray['content']);
                        }
                    });
                }
            });
            $(document).on("click", "#send_email", function () {
                $("#_send_email_form").clearForm();
                $('#_send_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Send Email to Customer',
                    open: function () {
                        addCKEditor();
                    },
                    buttons: {
                        Send: function (event) {
                            CKEDITOR.instances[instance].updateElement();
                            if ($("#_send_email_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_email&order_id=" + $("#primary_id").val(), $("#_send_email_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_send_email_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_send_email_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#reopen_order", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=reopen_order", { order_id: $("#primary_id").val() }, function (returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&primary_id=" + $("#primary_id").val();
                });
                return false;
            });
            $(document).on("click", "#mark_completed", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_completed", { order_id: $("#primary_id").val() }, function (returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                });
                return false;
            });
            $("#_list_button").text("Return To List");
            $("#order_status_id").change(function () {
                $("#order_status_wrapper").attr("class", "");
                $("#order_status_display").html("");
                $("#order_information_block").attr("class", "");
                if (!empty($(this).val())) {
                    const displayText = $(this).find("option:selected").text();
                    $("#order_status_wrapper").addClass("order-status-" + $(this).val());
                    $("#order_status_display").html(displayText);
                    $("#order_information_block").addClass("order-status-" + $(this).val() + "-light");
                }
                if (!empty($("#deleted").val())) {
                    $("#order_status_display").html("Deleted");
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_status&order_id=" + $("#primary_id").val() + "&order_status_id=" + $(this).val());
            });
            $(document).on("change", ".order-item-status-id", function () {
                $(this).closest("tr").attr("class", "").addClass("order-item");
                if (!empty($(this).val())) {
                    $(this).closest("tr").addClass("order-item-status-" + $(this).val());
                }
                const orderItemId = $(this).closest("tr").data("order_item_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_item_status&order_id=" + $("#primary_id").val() + "&order_item_id=" + orderItemId + "&order_item_status_id=" + $(this).val());
            });
            $(document).on("click", ".help-desk-entry", function () {
                const helpDeskEntryId = $(this).data("help_desk_entry_id");
                window.open("/help-desk-dashboard?id=" + helpDeskEntryId);
            });
        </script>
		<?php
	}

	function javascript() {
		$shippedOrderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", "SHIPPED");
		?>
        <script>
            function textareaVal(id, newValue) {
                if ($("#" + id).length > 0) {
                    if (typeof CKEDITOR == 'object' && typeof CKEDITOR.instances[id] == 'object') {
                        if (typeof newValue == 'undefined') {
                            return CKEDITOR.instances[id].getData();
                        } else {
                            CKEDITOR.instances[id].setData(newValue);
                        }
                    } else {
                        if (typeof newValue == 'undefined') {
                            return $("#" + id).val();
                        } else {
                            $("#" + id).val(newValue);
                        }
                    }
                }
            }
            function calculateRefund() {
                let refundableAmount = parseFloat($("#order_total").html().replace(/,/g, ''));
                let calculatedRefund = 0;
                let allRefunded = true;
                $("#order_items").find(".return-quantity").each(function () {
                    const availableQuantity = parseInt($(this).data("return_quantity"));
                    if (empty($(this).val())) {
                        if (availableQuantity > 0) {
                            allRefunded = false;
                        }
                        return true;
                    }
                    const thisQuantity = parseInt($(this).val().replace(/,/g, ''));
                    if (thisQuantity < availableQuantity) {
                        allRefunded = false;
                    }
                    if (thisQuantity <= 0) {
                        return true;
                    }
                    const thisPrice = parseFloat($(this).closest("tr").find(".sale-price").html().replace(/,/g, ''));
                    calculatedRefund += thisQuantity * thisPrice;
                    const taxRefund = Math.round(thisQuantity * 100 * parseFloat($(this).closest("tr").find(".tax-charge").html().replace(/,/g, '')) / parseFloat($(this).closest("tr").find(".quantity").html())) / 100;
                    calculatedRefund += taxRefund;
                });
                if (allRefunded) {
                    calculatedRefund = refundableAmount;
                }
                if (empty($("#restocking_fee").val())) {
                    let restockingFeePercentage = parseFloat($("#restocking_fee_percentage").val().replace(/,/g, ''));
                    if (!empty(restockingFeePercentage) && !isNaN(restockingFeePercentage)) {
                        $("#restocking_fee").val(RoundFixed(calculatedRefund * restockingFeePercentage / 100, 2, true));
                    }
                }
                const refundShipping = $("#refund_shipping").prop("checked");
                if (allRefunded) {
                    if (!refundShipping) {
                        $("#order_payments").find(".order-payment").each(function () {
                            const shippingCharge = parseFloat($(this).find(".shipping-charge").html().replace(/,/g, ''));
                            const handlingCharge = parseFloat($(this).find(".handling-charge").html().replace(/,/g, ''));
                            calculatedRefund = RoundFixed(calculatedRefund - (shippingCharge + handlingCharge), 2, true);
                        });
                    }
                } else {
                    if (refundShipping) {
                        // make sure that tax charged on shipping is accounted for
                        let totalItemsTax = 0;
                        $("#order_items .tax-charge").each(function () {
                            totalItemsTax += parseFloat($(this).html().replace(/,/g, ''))
                        });
                        let totalPaymentsTax = 0;
                        $("#order_payments .tax-charge").each(function () {
                            totalPaymentsTax += parseFloat($(this).html().replace(/,/g, ''))
                        });
                        const shippingTax = totalPaymentsTax - totalItemsTax;
                        const shippingCharge = parseFloat($("#shipping_charge").html().replace(/,/g, '')) + parseFloat($("#handling_charge").html().replace(/,/g, ''));
                        const productTotal = refundableAmount - shippingCharge;
                        const shippingRefund = Math.round(calculatedRefund * shippingCharge * 100 / productTotal) / 100 + shippingTax;
                        calculatedRefund += shippingRefund;
                    }
                }
                if (calculatedRefund > refundableAmount) {
                    calculatedRefund = refundableAmount;
                }
                if (isNaN($("#restocking_fee").val())) {
                    $("#restocking_fee").val("");
                }
                const restockingFee = (empty($("#restocking_fee").val()) ? 0 : parseFloat($("#restocking_fee").val().replace(/,/g, '')));
                calculatedRefund -= restockingFee;

                $("#order_payments").find(".order-payment").each(function () {
                    const orderPaymentId = $(this).data("order_payment_id");
                    $("#refund_amount_" + orderPaymentId).val("").trigger("change");
                    $("#refund_shipping_charge_" + orderPaymentId).val("").trigger("change");
                    $("#refund_tax_charge_" + orderPaymentId).val("").trigger("change");
                    $("#refund_handling_charge_" + orderPaymentId).val("").trigger("change");
                    let amount = RoundFixed(parseFloat($(this).find(".amount").html().replace(/,/g, '')), 2, true);
                    let taxCharge = RoundFixed(parseFloat($(this).find(".tax-charge").html().replace(/,/g, '')), 2, true);
                    let shippingCharge = RoundFixed((refundShipping ? parseFloat($(this).find(".shipping-charge").html().replace(/,/g, '')) : 0), 2, true);
                    let handlingCharge = RoundFixed((refundShipping ? parseFloat($(this).find(".handling-charge").html().replace(/,/g, '')) : 0), 2, true);
                    let thisRefundAmount = parseFloat(amount) + parseFloat(taxCharge) + parseFloat(shippingCharge) + parseFloat(handlingCharge);
                    if (thisRefundAmount > calculatedRefund) {
                        const percentage = calculatedRefund / thisRefundAmount;
                        thisRefundAmount = calculatedRefund;
                        amount = RoundFixed(amount * percentage, 2, true);
                        taxCharge = RoundFixed(taxCharge * percentage, 2, true);
                        shippingCharge = RoundFixed(shippingCharge * percentage, 2, true);
                        handlingCharge = RoundFixed(handlingCharge * percentage, 2, true);
                        const tempAmount = parseFloat(amount) + parseFloat(taxCharge) + parseFloat(shippingCharge) + parseFloat(handlingCharge);
                        if (thisRefundAmount != tempAmount) {
                            amount = RoundFixed(parseFloat(amount) + parseFloat(thisRefundAmount - tempAmount), 2, true);
                        }
                    }
                    if (thisRefundAmount > 0) {
                        $("#refund_amount_" + orderPaymentId).val(amount).trigger("change");
                        $("#refund_shipping_charge_" + orderPaymentId).val(shippingCharge).trigger("change");
                        $("#refund_tax_charge_" + orderPaymentId).val(taxCharge).trigger("change");
                        $("#refund_handling_charge_" + orderPaymentId).val(handlingCharge).trigger("change");
                        calculatedRefund -= thisRefundAmount;
                    }
                });
            }
            function afterGetRecord(returnArray) {
                if ($("#primary_id").length > 0) {
                    document.title = "Order Number " + $("#primary_id").val();
                }
                if ($("#deleted").val() === "1") {
                    $("#_delete_button").find(".button-text").html("Undelete");
                } else {
                    $("#_delete_button").find(".button-text").html("Delete");
                }
                if (empty(returnArray['date_completed']['data_value'])) {
                    $("#order_status_id").trigger("change");
                }
                getOrderItems();
                getOrderShipments();

                $("#order_shipments").find(".order-shipment").remove();
                $("#order_shipments").find(".order-shipment-item").remove();
                for (let i in returnArray['order_shipments']) {
                    let orderShipmentBlock = $("#_order_shipment_template").html();
                    for (let j in returnArray['order_shipments'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderShipmentBlock = orderShipmentBlock.replace(re, returnArray['order_shipments'][i][j]);
                    }
                    $("#order_shipments").append(orderShipmentBlock);
                    if (returnArray['order_shipments'][i]['no_notifications'] === "1") {
                        $("#no_notifications_" + returnArray['order_shipments'][i]['order_shipment_id']).prop("checked", true);
                    }
                    if (!empty(returnArray['order_shipments'][i]['secondary_shipment'])) {
                        $("#order_shipment_" + returnArray['order_shipments'][i]['order_shipment_id']).addClass("secondary-shipment");
                    }
                    if (!empty(returnArray['order_shipments'][i]['product_distributor_id'])) {
                        $("#create_shipping_label_" + returnArray['order_shipments'][i]['order_shipment_id']).remove();
                    }
                    if ("order_shipment_items" in returnArray['order_shipments'][i]) {
                        for (let j in returnArray['order_shipments'][i]['order_shipment_items']) {
                            let orderShipmentItemBlock = $("#_order_shipment_item_template").html();
                            for (let k in returnArray['order_shipments'][i]['order_shipment_items'][j]) {
                                const re = new RegExp("%" + k + "%", 'g');
                                orderShipmentItemBlock = orderShipmentItemBlock.replace(re, returnArray['order_shipments'][i]['order_shipment_items'][j][k]);
                            }
                            $("#order_shipments").append(orderShipmentItemBlock);
                        }
                    }
                }
                if ($("#order_shipments").find(".order-shipment").length === 0) {
                    $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Shipments yet</td></tr>");
                }
                $("#order_notes").find(".order-note").remove();
                for (let i in returnArray['order_notes']) {
                    let orderNoteBlock = $("#_order_note_template").html();
                    for (let j in returnArray['order_notes'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_notes'][i][j]);
                    }
                    $("#order_notes").append(orderNoteBlock);
                }
                if ($("#order_notes").find(".order-note").length === 0) {
                    $("#order_notes").append("<tr class='order-note no-order-notes'><td colspan='100'>No Notes yet</td></tr>");
                }

                $("#order_payments").find(".order-payment").remove();
                for (let i in returnArray['order_payments']) {
                    let orderPaymentBlock = $("#_order_payment_template").html();
                    for (let j in returnArray['order_payments'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderPaymentBlock = orderPaymentBlock.replace(re, returnArray['order_payments'][i][j]);
                    }
                    let additionalClasses = "";
                    if (!empty(returnArray['order_payments'][i]['deleted'])) {
                        additionalClasses = "deleted-payment hidden";
                    }
                    orderPaymentBlock = orderPaymentBlock.replace(new RegExp("%additional_classes%", 'g'), additionalClasses);
                    $("#order_payments").append(orderPaymentBlock);
                    if (!empty(returnArray['order_payments'][i]['transaction_identifier']) || (returnArray['order_payments'][i]['payment_method'] == "Gift Card")) {
                        $("#refund_amount_" + returnArray['order_payments'][i]['order_payment_id']).removeClass("hidden");
                        $("#refund_shipping_charge_" + returnArray['order_payments'][i]['order_payment_id']).removeClass("hidden");
                        $("#refund_tax_charge_" + returnArray['order_payments'][i]['order_payment_id']).removeClass("hidden");
                        $("#refund_handling_charge_" + returnArray['order_payments'][i]['order_payment_id']).removeClass("hidden");
                        $("#refund_total_amount_" + returnArray['order_payments'][i]['order_payment_id']).removeClass("hidden");
                    }
                }
                if ($("#order_payments").find(".order-payment").length === 0) {
                    $("#order_payments").append("<tr class='order-payment'><td colspan='100'>No Payments that can be refunded</td></tr>");
                }

                $("#touchpoints").find(".touchpoint").remove();
                for (let i in returnArray['touchpoints']) {
                    let touchpointBlock = $("#_touchpoint_template").html();
                    for (let j in returnArray['touchpoints'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        touchpointBlock = touchpointBlock.replace(re, returnArray['touchpoints'][i][j]);
                    }
                    $("#touchpoints").append(touchpointBlock);
                }
                if ($("#touchpoints").find(".touchpoint").length === 0) {
                    $("#touchpoints").append("<tr class='touchpoint no-touchpoints'><td colspan='100'>No Touchpoints</td></tr>");
                }
                $("#show_order_touchpoints").prop("checked", ($(".touchpoint.order-" + $("#primary_id").val()).length > 0)).trigger("change");

                $("#help_desk_entries").find(".help-desk-entry").remove();
                for (let i in returnArray['help_desk_entries']) {
                    let helpDeskEntryBlock = $("#_help_desk_entry_template").html();
                    for (let j in returnArray['help_desk_entries'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        helpDeskEntryBlock = helpDeskEntryBlock.replace(re, returnArray['help_desk_entries'][i][j]);
                    }
                    $("#help_desk_entries").append(helpDeskEntryBlock);
                }
                if ($("#help_desk_entries").find(".help-desk-entry").length === 0) {
                    $("#help_desk_entries").append("<tr class='help-desk-entry no-help-desk-entries'><td colspan='100'>No Help Desk Tickets</td></tr>");
                }

                if (!empty(returnArray['deleted']['data_value'])) {
                    $("#_maintenance_form").find("input[type=text]").prop("readonly", true);
                    $("#_maintenance_form").find("select").prop("disabled", true);
                    $("#_maintenance_form").find("textarea").prop("readonly", true);
                    $("#_maintenance_form").find("button").not(".keep-visible").addClass("hidden");
                    $("#_content_row").addClass("hidden");
                    $("#_maintenance_form").find(".delete-shipping-item").addClass("hidden");
                }
            }

            function getOrderItems() {
                $("#order_items").find("tr.order-item").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_items&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("order_items" in returnArray) {
                        for (const i in returnArray['order_items']) {
                            let orderItemBlock = $("#_order_item_template").html();
                            for (const j in returnArray['order_items'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderItemBlock = orderItemBlock.replace(re, returnArray['order_items'][i][j]);
                            }
                            $("#order_items").append(orderItemBlock);
                            if (!empty(returnArray['order_items'][i]['serializable'])) {
                                $("#order_item_" + returnArray['order_items'][i]['order_item_id']).find(".serial-number-wrapper").removeClass("hidden");
                            }
                            $("#order_item_status_id_" + returnArray['order_items'][i]['order_item_id']).val(returnArray['order_items'][i]['order_item_status_id']);
                        }
                        $("#order_items").find(".order-item-status-id").each(function () {
                            $(this).closest("tr").attr("class", "").addClass("order-item");
                            if (!empty($(this).val())) {
                                $(this).closest("tr").addClass("order-item-status-" + $(this).val());
                            }
                        });
                        if (!empty($("#deleted").val()) || !empty($("#date_completed").val())) {
                            $("#order_items").find(".order-item-status-id").prop("disabled", true);
                        }
                    }
                });
            }

            function getOrderShipments() {
                $("#order_shipments").find(".order-shipment").remove();
                $("#order_shipments").find(".order-shipment-item").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_shipments&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("order_shipments" in returnArray) {
                        $("#order_shipments").find(".order-shipment").remove();
                        $("#order_shipments").find(".order-shipment-item").remove();
                        for (let i in returnArray['order_shipments']) {
                            let orderShipmentBlock = $("#_order_shipment_template").html();
                            for (let j in returnArray['order_shipments'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderShipmentBlock = orderShipmentBlock.replace(re, returnArray['order_shipments'][i][j]);
                            }
                            $("#order_shipments").append(orderShipmentBlock);
                            if (!empty(returnArray['order_shipments'][i]['no_notifications'])) {
                                $("#no_notifications_" + returnArray['order_shipments'][i]['order_shipment_id']).prop("checked", true);
                            }
                            if (!empty(returnArray['order_shipments'][i]['secondary_shipment'])) {
                                $("#order_shipment_" + returnArray['order_shipments'][i]['order_shipment_id']).addClass("secondary-shipment");
                            }
                            if (!empty(returnArray['order_shipments'][i]['product_distributor_id'])) {
                                $("#create_shipping_label_" + returnArray['order_shipments'][i]['order_shipment_id']).remove();
                            }
                            if ("order_shipment_items" in returnArray['order_shipments'][i]) {
                                for (const j in returnArray['order_shipments'][i]['order_shipment_items']) {
                                    let orderShipmentItemBlock = $("#_order_shipment_item_template").html();
                                    for (const k in returnArray['order_shipments'][i]['order_shipment_items'][j]) {
                                        const re = new RegExp("%" + k + "%", 'g');
                                        orderShipmentItemBlock = orderShipmentItemBlock.replace(re, returnArray['order_shipments'][i]['order_shipment_items'][j][k]);
                                    }
                                    $("#order_shipments").append(orderShipmentItemBlock);
                                }
                                $("#shipping_carrier_id_" + returnArray['order_shipments'][i]['order_shipment_id']).val(returnArray['order_shipments'][i]['shipping_carrier_id']);
                            }
                        }
                        if ($("#order_shipments").find(".order-shipment").length === 0) {
                            $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Shipments yet</td></tr>");
                        }
                    }
                });
            }

            function afterDeleteRecord(returnArray) {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                return false;
            }

            function changesMade() {
                return false;
            }
        </script>
		<?php
	}

	function deleteRecord() {
		$returnArray = array();
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['primary_id']);
		if (!empty($orderId)) {
            updateFieldById("deleted", (!empty($_POST['deleted']) ? "0" : "1"), "orders", "order_id", $orderId, "client_id = ?", $GLOBALS['gClientId']);
			if ($_POST['deleted'] == "1") {
				$returnArray['deleted'] = "0";
				$returnArray['info_message'] = getLanguageText("Order successfully undeleted");
                $webhookReason = "mark_undeleted";
			} else {
				$returnArray['deleted'] = "1";
                $webhookReason = "mark_deleted";
			}
            Corestore::orderNotification($_POST['primary_id'], $webhookReason);
            $resultSet = executeQuery("select product_id from order_items where order_id = ?", $_POST['primary_id']);
			while ($row = getNextRow($resultSet)) {
				removeCachedData("*", $row['product_id']);
				removeCachedData("base_cost", $row['product_id']);
				removeCachedData("*", $row['product_id']);
			}
			if (empty($_POST['deleted'])) {
				$resultSet = executeQuery("select * from loyalty_program_point_log where order_id = ? and (notes is null or notes not like '%order deleted%')", $orderId);
				while ($row = getNextRow($resultSet)) {
					executeQuery("update loyalty_program_points set point_value = greatest(0,point_value - ?) where loyalty_program_point_id = ?",
						$row['point_value'], $row['loyalty_program_point_id']);
					executeQuery("update loyalty_program_point_log set notes = ? where loyalty_program_point_log_id = ?",
						date("m/d/Y") . ": points reversed because order deleted", $row['loyalty_program_point_log_id']);
				}
			}
		}
		ajaxResponse($returnArray);
	}

	function afterGetRecord(&$returnArray) {
		$pagePreferences = Page::getPagePreferences();
		$returnArray['refund_shipping'] = array("data_value" => (empty($pagePreferences['refund_shipping']) ? 0 : 1));
		$restockingFeePercentage = getPreference("RESTOCKING_FEE_PERCENTAGE");
		$returnArray['restocking_fee_percentage'] = array("data_value" => $restockingFeePercentage);
		if (empty($restockingFeePercentage)) {
			$returnArray['restocking_fee'] = array("data_value" => $pagePreferences['restocking_fee']);
		}

		$orderNotes = array();
		$resultSet = executeQuery("select * from order_notes where order_id = ? order by time_submitted desc", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['time_submitted'] = date("m/d/Y g:ia", strtotime($row['time_submitted']));
			$row['user_id'] = getUserDisplayName($row['user_id']);
			$row['public_access'] = (empty($row['public_access']) ? "" : "YES");
			$orderNotes[] = $row;
		}
		$returnArray['order_notes'] = $orderNotes;
		if (count($orderNotes) > 0) {
			$returnArray['error_message'] = "Check notes below";
		}

		$touchpoints = array();
		$resultSet = executeQuery("select task_id,task_type_id,description,detailed_description,date_completed,order_id from tasks where contact_id = ? order by task_id desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['task_type'] = getFieldFromId("description", "task_types", "task_type_id", $row['task_type_id']);
			$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
			$touchpoints[] = $row;
		}
		$returnArray['touchpoints'] = $touchpoints;

		$helpDeskEntries = array();
		$resultSet = executeQuery("select help_desk_entry_id,description,time_submitted,time_closed from help_desk_entries where contact_id = ? order by time_submitted desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['date_submitted'] = date("m/d/Y", strtotime($row['time_submitted']));
			$row['date_closed'] = (empty($row['time_closed']) ? "" : date("m/d/Y", strtotime($row['time_closed'])));
			$helpDeskEntries[] = $row;
		}
		$returnArray['help_desk_entries'] = $helpDeskEntries;

		$contactIdentifiers = "";
		$resultSet = executeQuery("select * from contact_identifiers join contact_identifier_types using (contact_identifier_type_id) where contact_id = ?", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$contactIdentifiers .= "<p>" . htmlText($row['description']) . ": " . $row['identifier_value'] . "</p>";
		}
		$returnArray['contact_identifiers'] = array("data_value" => $contactIdentifiers);

		$fullName = $returnArray['full_name']['data_value'];
		$returnArray['full_name'] = array("data_value" => "<a target='_blank' href='/contactmaintenance.php?url_page=show&primary_id=" . $returnArray['contact_id']['data_value'] . "&clear_filter=true'>" . $returnArray['full_name']['data_value'] . "</a>");
		$returnArray['location_id'] = array("data_value" => "");
		$promotionId = getFieldFromId("promotion_id", "order_promotions", "order_id", $returnArray['primary_id']['data_value']);
		$returnArray['order_promotion'] = array("data_value" => (empty($promotionId) ? "" : "Used promotion '" . getFieldFromId("description", "promotions", "promotion_id", $promotionId) . "'"));

		if (!empty($returnArray['date_completed']['data_value'])) {
			$returnArray['order_status_display'] = array("data_value" => "Completed on " . date("m/d/Y", strtotime($returnArray['date_completed']['data_value'])));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button class='keep-visible' id='reopen_order'>Reopen Order</button>");
		} else {
			$returnArray['order_status_display'] = array("data_value" => getFieldFromId("description", "order_status", "order_status_id", $returnArray['order_status_id']['data_value']));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button id='mark_completed'>Mark Order Completed</button>");
		}
		$returnArray['email_address'] = array("data_value" => getFieldFromId("email_address", "contacts", "contact_id", $returnArray['contact_id']['data_value']));
		$returnArray['billing_address'] = array("data_value" => "");
		$accountId = getFieldFromId("account_id", "order_payments", "order_id", $returnArray['primary_id']['data_value']);
		if (empty($accountId)) {
			$accountId = $returnArray['account_id']['data_value'];
		}
		if (!empty($accountId)) {
			$accountRow = getRowFromId("accounts", "account_id", $accountId);
			$billingAddressId = $accountRow['address_id'];
			if (empty($billingAddressId)) {
				$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
			} else {
				$addressRow = getRowFromId("addresses", "address_id", $billingAddressId);
			}
			$billingAddress = $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code'];
			if ($addressRow['country_id'] != 1000) {
				$billingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
			}
			$billingAddress .= "</p><p>" . getFieldFromId("description", "payment_methods", "payment_method_id", $accountRow['payment_method_id']) . " - " . $accountRow['account_number'];
			$returnArray['billing_address'] = array("data_value" => $billingAddress);
		}
		if (empty($returnArray['address_id']['data_value'])) {
			$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $returnArray['address_id']['data_value']);
		}
		if (strlen($fullName) > 20) {
			$fullName = str_replace(", ", "<br>", $fullName);
		}
		$shippingAddress = $fullName . "<br>" . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . "," . $addressRow['postal_code'];
		if ($addressRow['country_id'] != 1000) {
			$shippingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
		}
		$shippingAddress .= "</p><p><a target='_blank' href='https://www.google.com/maps?q=" . urlencode($addressRow['address_1'] . ", " . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . ", ") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code']) . "'>Show on map</a>";
		$returnArray['shipping_address'] = array("data_value" => $shippingAddress);
		$returnArray['shipping_method_display'] = array("data_value" => getFieldFromId("description", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		$returnArray['pickup'] = array("data_value" => getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		$returnArray['order_method_display'] = array("data_value" => getFieldFromId("description", "order_methods", "order_method_id", $returnArray['order_method_id']['data_value']));

		$orderItemTotal = 0;
		$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$orderItemTotal += $row['sale_price'] * $row['quantity'];
		}
		$orderTotal = $orderItemTotal - $returnArray['order_discount']['data_value'] + $returnArray['shipping_charge']['data_value'] + $returnArray['handling_charge']['data_value'] + $returnArray['tax_charge']['data_value'];

		$orderPayments = array();
		$paymentTotal = 0;
		$creditCardPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "CREDIT_CARD");
		$giftCardPaymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "GIFT_CARD");
		$resultSet = executeQuery("select * from order_payments where order_id = ? and deleted = 0 order by account_id,payment_time", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			if ($row['amount'] < 0) {
				$orderTotal += $row['amount'];
			}
			if ($row['shipping_charge'] < 0) {
				$orderTotal += $row['shipping_charge'];
			}
			if ($row['tax_charge'] < 0) {
				$orderTotal += $row['tax_charge'];
			}
			if ($row['handling_charge'] < 0) {
				$orderTotal += $row['handling_charge'];
			}
			$paymentTotal = $row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'];
			if (!empty($row['account_id']) && array_key_exists($row['account_id'], $orderPayments) && $paymentTotal < 0) {
				$orderPayments[$row['account_id']]['payment_total'] += $paymentTotal;
				$orderPayments[$row['account_id']]['total_amount'] = number_format(round($orderPayments[$row['account_id']]['payment_total'], 2), 2, ".", "");
				$orderPayments[$row['account_id']]['amount'] += $row['amount'];
				$orderPayments[$row['account_id']]['shipping_charge'] += $row['shipping_charge'];
				$orderPayments[$row['account_id']]['tax_charge'] += $row['tax_charge'];
				$orderPayments[$row['account_id']]['handling_charge'] += $row['handling_charge'];
				$orderPayments[$row['account_id']]['amount'] = number_format($orderPayments[$row['account_id']]['amount'], 2, ".", "");
				$orderPayments[$row['account_id']]['shipping_charge'] = number_format($orderPayments[$row['account_id']]['shipping_charge'], 2, ".", "");
				$orderPayments[$row['account_id']]['tax_charge'] = number_format($orderPayments[$row['account_id']]['tax_charge'], 2, ".", "");
				$orderPayments[$row['account_id']]['handling_charge'] = number_format($orderPayments[$row['account_id']]['handling_charge'], 2, ".", "");
			} else {
				$row['payment_time'] = date("m/d/Y g:i a", strtotime($row['payment_time']));
				$row['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
				$fullName = getFieldFromId("full_name", "accounts", "account_id", $row['account_id']);
				$row['account_number'] = (empty($row['account_id']) ? $row['reference_number'] : (empty($fullName) ? "" : $fullName . ", ") . substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -8));
				$row['payment_total'] = $paymentTotal;
				$row['total_amount'] = number_format($paymentTotal, 2, ".", "");
				$row['shipping_charge'] = number_format($row['shipping_charge'], 2, ".", "");
				$row['tax_charge'] = number_format($row['tax_charge'], 2, ".", "");
				$row['handling_charge'] = number_format($row['handling_charge'], 2, ".", "");
				$accountRow = getRowFromId("accounts", "account_id", $row['account_id']);
				$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $accountRow['payment_method_id']);
				if ($giftCardPaymentMethodTypeId != $paymentMethodRow['payment_method_type_id'] && $creditCardPaymentMethodTypeId != $paymentMethodRow['payment_method_type_id']) {
					continue;
				}
				$orderPayments[$row['account_id']] = $row;
			}
		}
		$returnArray['order_payments'] = $orderPayments;
		if ($orderTotal < 0) {
			$orderTotal = 0;
		}
		$returnArray['order_total'] = array("data_value" => showSignificant($orderTotal, 2));
	}

	function internalCSS() {
		?>
        <style>
            td {
                position: relative;
            }
            .help-desk-entry {
                cursor: pointer;
            &
            :hover td {
                background-color: rgb(240, 240, 180);
            }
            }
            #jquery_templates {
                display: none;
            }
            #_order_header_section {
                margin-top: 20px;
            }

            tr.order-payment.deleted-payment .undelete-payment {
                display: inline-block;
            }

            tr.order-payment.deleted-payment .delete-payment {
                display: none;
            }

            tr.order-payment.deleted-payment td {
                text-decoration: line-through;
                color: rgb(192, 0, 0);
            }

            <?php
			if ($_GET['url_page'] == "show") {
				?>
            #_page_number_controls {
                display: none !important;
            }

            #_form_header_buttons {
                margin-bottom: 0;
            }

            #_management_header {
                padding-bottom: 0;
            }

            #_main_content {
                margin-top: 0;
            }

            p#_error_message {
                margin-bottom: 0;
            }

            <?php
		}
		?>
            .distance--miles {
                display: none;
            }

            #payment_warning {
                color: rgb(192, 0, 0);
                font-weight: 900;
                font-size: 1.2rem;
            }

            #order_filters {
                text-align: center;
            }

            #order_filters button:hover {
                background-color: #000;
                border-color: #000;
                color: #FFF;
            }

            #order_filters button.active {
                background-color: #00807f;
                border-color: #00807f;
                color: #FFF;
            }

            #_list_actions, #_list_search_control, #_list_header_buttons {
                margin-bottom: 0;
            }

            #_add_button {
                display: none;
            }

            #_maintenance_form h2 {
                margin-top: 16px;
            }

            .count-wrapper {
                display: flex;
            }

            .count-wrapper > div {
                flex: 1 1 auto;
                text-align: center;
            }

            .count-wrapper > div:nth-child(2) {
                border-left: 1px solid #d8d8d8;
            }

            #order_information_block {
                background-color: rgb(240, 240, 240);
                border: 1px solid rgb(180, 180, 180);
                padding: 20px;
                display: flex;
                margin-bottom: 10px;
            }

            #order_information_block > div {
                flex: 1 1 auto;
            }

            #_main_content p#full_name {
                font-size: 1.6rem;
                font-weight: 300;
            }

            p label {
                font-size: 1rem;
                margin-right: 20px;
            }

            table.order-information {
                width: 100%;
                margin-bottom: 10px;
                border: 1px solid rgb(150, 150, 150);
            }

            table.order-information tr {
                border: 1px solid rgb(150, 150, 150);
            }

            table.order-information th {
                vertical-align: middle;
                padding: 10px;
            }

            table.order-information td {
                background-color: rgb(240, 240, 240);
                padding: 10px;
            }

            .product-description {
                font-size: .8rem;
                line-height: 1.2;
            }

            .upc-code {
                color: rgb(150, 150, 150);
                font-weight: 300;
                font-size: .8rem;
            }

            #order_status_id {
                min-width: 200px;
                width: 200px;
                max-width: 100%;
            }

            .order-shipment input {
                width: 150px;
                font-size: .8rem;
            }

            .order-shipment .notes {
                height: 26px;
                width: 150px;
            }

            .order-shipment .notes:focus {
                height: 100px;
            }

            .inventory-quantities {
                white-space: nowrap;
                font-size: .7rem;
                line-height: 1.2;
                text-align: left;
            }

            .quantity-input {
                position: relative;
                overflow: visible;
                white-space: nowrap;
            }

            #order_status_wrapper {
                display: flex;
                background-color: rgb(180, 180, 180);
                padding: 20px;
                color: #FFF;
                text-shadow: 0 1px 2px #000000;
                text-transform: uppercase;
                font-size: 1.4rem;
            }

            #order_status_display {
                flex: 1 1 auto;
            }

            #order_id_wrapper {
                text-align: right;
            }

            #order_filters button {
                padding: 5px 15px;
            }

            .basic-form-line.serial-number-wrapper {
                margin-bottom: 0;
                margin-top: 10px;
            }

            #help_desk_answer_id {
                width: 500px;
            }

            .dialog-box .basic-form-line {
                white-space: nowrap;
            }

            .order-item-status-id {
                width: 200px;
            }

            #order_payments td {
                font-size: .7rem;
            }

            #order_payments td.order-payment {
                border-bottom: none;
            }

            #order_payments tr.order-payment-refunds td {
                border-bottom: 2px solid rgb(0, 0, 0);
            }

            #_save_button {
                display: none;
            }

            <?php
			$resultSet = executeQuery("select * from order_status where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$rgb = hex2rgb($row['display_color']);
				$lightRgb = $rgb;
				foreach ($lightRgb as $index => $thisColor) {
					$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
				}
				?>
            .order-status-<?= $row['order_status_id'] ?> {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            .order-status-<?= $row['order_status_id'] ?>-light {
                background-color: rgb(<?= $lightRgb[0] ?>,<?= $lightRgb[1] ?>,<?= $lightRgb[2] ?>) !important;
                border: 1px solid rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            <?php
		}
		$resultSet = executeQuery("select * from order_item_statuses where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$rgb = hex2rgb($row['display_color']);
			$lightRgb = $rgb;
			foreach ($lightRgb as $index => $thisColor) {
				$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
			}
			?>
            .order-item-status-<?= $row['order_item_status_id'] ?> td {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            <?php
		}
		?>
            @media only screen and (max-width: 1000px) {
                #_order_header_section {
                    display: none;
                }

            }
        </style>
		<?php
	}

	function getListRowClasses($columnRow) {
		if (!empty($columnRow['order_status_id'])) {
			return "order-status-" . $columnRow['order_status_id'] . "-light";
		}
		return "";
	}

	function hiddenElements() {
		?>
        <div id="_send_email_dialog" class="dialog-box">
            <form id="_send_email_form">

                <p class="error-message"></p>
				<?php
				$resultSet = executeQuery("select * from help_desk_answers where help_desk_type_id is null and client_id = ?" . ($GLOBALS['gUserRow']['superuser_flag'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . " order by description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
                    <div class="basic-form-line">
                        <label>Standard Answers</label>
                        <select id="help_desk_answer_id">
                            <option value="">[Select]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['help_desk_answer_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?php
				}
				?>
                <div class="basic-form-line" id="_email_subject_row">
                    <label for="email_subject" class="required-label">Subject</label>
                    <input type="text" class="validate[required]" maxlength="255" id="email_subject" name="email_subject">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_email_body_row">
                    <label for="email_body" class="required-label">Content</label>
                    <textarea class="validate[required] ck-editor" id="email_body" name="email_body"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div> <!-- confirm_shipment_dialog -->
		<?php
	}

	function jqueryTemplates() {
		?>
        <table>
            <tbody id="_order_note_template">
            <tr class="order-note" id="order_note_%order_note_id%" data-order_note_id="%order_note_id%">
                <td class='time-submitted'>%time_submitted%</td>
                <td class='user-id'>%user_id%</td>
                <td class='public-access'>%public_access%</td>
                <td class='content'>%content%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_touchpoint_template">
            <tr class="touchpoint order-%order_id%" id="touchpoint_%task_id%">
                <td>%date_completed%</td>
                <td>%task_type%</td>
                <td>%description%</td>
                <td>%detailed_description%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_help_desk_entry_template">
            <tr class="help-desk-entry" id="help_desk_entry_%help_desk_entry_id%" data-help_desk_entry_id="%help_desk_entry_id%">
                <td>%help_desk_entry_id%</td>
                <td>%date_submitted%</td>
                <td>%description%</td>
                <td>%date_closed%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_item_template">
            <tr class="order-item" id="order_item_%order_item_id%" data-order_item_id="%order_item_id%">
                <td><input type='text' size="8" class="validate[custom[integer],min[1],max[%quantity%]] align-right return-quantity" data-return_quantity="%quantity%" name="return_quantity_%order_item_id%" id="return_quantity_%order_item_id%"></td>
                <td class='product-description'>
                    <a href="/productmaintenance.php?url_page=show&primary_id=%product_id%&clear_filter=true" target="_blank">%product_description%</a>
                </td>
                <td class="align-right quantity">%quantity%</td>
                <td class="align-right sale-price">%sale_price%</td>
                <td class="align-right tax-charge">%tax_charge%</td>
                <td class="align-right total-price">%total_price%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_shipment_template">
            <tr class="order-shipment" id="order_shipment_%order_shipment_id%" data-order_shipment_id="%order_shipment_id%">
                <td class='date-shipped'><input type="hidden" class="label-url" value="%label_url%">%date_shipped%</td>
                <td class='location'>%location%</td>
                <td class='order-number'>%order_number%</td>
                <td class='full-name'>%full_name%</td>
                <td class='align-right shipping-charge'>%shipping_charge%</td>
                <td>%tracking_identifier%</td>
                <td><select disabled='disabled' data-field_name="shipping_carrier_id" class='editable-shipping-field shipping-carrier-id' id="shipping_carrier_id_%order_shipment_id%">
                        <option value="">[Other]</option>
						<?php
						$resultSet = executeQuery("select * from shipping_carriers where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['shipping_carrier_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                </td>
                <td>%carrier_description%</td>
                <td colspan="2">%notes%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_shipment_item_template">
            <tr class="order-shipment-item" id="order_shipment_item_%order_shipment_item_id%" data-order_shipment_id="%order_shipment_id%" data-order_shipment_item_id="%order_shipment_item_id%">
                <td></td>
                <td colspan="8">%product_description%</td>
                <td class='align-right'>%quantity%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_payment_template">
            <tr class="order-payment %additional_classes%" id="order_payment_%order_payment_id%" data-order_payment_id="%order_payment_id%">
                <td class='payment-method'>%payment_method%</td>
                <td class='account-number'>%account_number%</td>
                <td class='align-right amount'>%amount%</td>
                <td class='align-right shipping-charge'>%shipping_charge%</td>
                <td class='align-right tax-charge'>%tax_charge%</td>
                <td class='align-right handling-charge'>%handling_charge%</td>
                <td class='align-right total-amount'>%total_amount%</td>
            </tr>
            <tr class='order-payment-refunds' data-order_payment_id="%order_payment_id%">
                <td></td>
                <td>
                    <button id='refund_all_%order_payment_id' class='refund-all'>Refund All</button>
                </td>
                <td class='align-right'><input type='text' size='12' class='validate[custom[number],min[0],max[%amount%] align-right hidden refund-amount' data-decimal-places="2" id="refund_amount_%order_payment_id%" name="refund_amount_%order_payment_id%"></td>
                <td class='align-right'><input type='text' size='12' class='validate[custom[number],min[0],max[%shipping_charge%] align-right hidden refund-amount' data-decimal-places="2" id="refund_shipping_charge_%order_payment_id%" name="refund_shipping_charge_%order_payment_id%"></td>
                <td class='align-right'><input type='text' size='12' class='validate[custom[number],min[0],max[%tax_charge%] align-right hidden refund-amount' data-decimal-places="2" id="refund_tax_charge_%order_payment_id%" name="refund_tax_charge_%order_payment_id%"></td>
                <td class='align-right'><input type='text' size='12' class='validate[custom[number],min[0],max[%handling_charge%] align-right hidden refund-amount' data-decimal-places="2" id="refund_handling_charge_%order_payment_id%" name="refund_handling_charge_%order_payment_id%"></td>
                <td class='align-right'><input type='text' size='12' class='validate[custom[number],min[0],max[%total_amount%] align-right hidden total-amount' readonly='readonly' data-decimal-places="2" id="refund_total_amount_%order_payment_id%" name="refund_total_amount_%order_payment_id%"></td>
            </tr>
            </tbody>
        </table>

		<?php
	}
}

$pageObject = new RefundDashboardPage("orders");
$pageObject->displayPage();
