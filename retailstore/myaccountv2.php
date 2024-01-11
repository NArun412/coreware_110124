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

$GLOBALS['gPageCode'] = "RETAILSTOREMYACCOUNT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gSetRequiredFields'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class MyAccountV2Page extends Page {

    private $iCancelReservationMessage = "";
    private $iMakeAccountInactiveMessage = "";

    function setup() {
		executeQuery("delete from wish_list_items where wish_list_id in (select wish_list_id from wish_lists where user_id = ?)"
			. " and product_id in (select product_id from products where inactive = 1 or internal_use_only = 1)", $GLOBALS['gUserId']);
        if ($GLOBALS['gLoggedIn'] && function_exists("_localServerImportInvoices")) {
            _localServerImportInvoices($GLOBALS['gUserRow']['contact_id']);
        }
        $this->iCancelReservationMessage = $this->getFragment("MY_ACCOUNT_CANCEL_RESERVATION_MESSAGE")
            ?: "Canceling a reservation cannot be undone. Your reservation will be removed and, if a reservation fee was paid, a gift card will be issued in the amount of the fees. Are you sure you want to cancel your reservation?";
        $this->iMakeAccountInactiveMessage = $this->getFragment("MY_ACCOUNT_REMOVE_PAYMENT_METHOD_MESSAGE")
            ?: "Removing a payment account cannot be undone. Any recurring payments using that account will be ended. Are you sure you want to remove this payment account?";
    }

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_orders":
				$orders = array();
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$resultSet = executeQuery("select * from orders where contact_id = ? and deleted = 0 order by order_id desc", $GLOBALS['gUserRow']['contact_id']);
				if (!empty($resultSet['row_count'])) {
					while ($row = getNextRow($resultSet)) {
						$fflRequired = false;
						$statusDescription = getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id'], "inactive = 0 and internal_use_only = 0");
						if (empty($row['address_id'])) {
							$addressRow = $GLOBALS['gUserRow'];
						} else {
							$addressRow = getRowFromId("addresses", "address_id", $row['address_id']);
						}
						$shippingAddress = $addressRow['address_1'] . ", " . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code'];
						$orderItems = array();
						$orderTotal = 0;
						$itemSet = executeQuery("select *,(select group_concat(serial_number) from order_item_serial_numbers where " .
							"order_item_id = order_items.order_item_id) as serial_numbers from order_items join products using (product_id) left outer join product_data using (product_id) where order_id = ? and deleted = 0", $row['order_id']);
						$packArray = array();
						$itemsArray = array();
						while ($itemRow = getNextRow($itemSet)) {
							if (empty($itemRow['pack_product_id'])) {
								if (!empty($orderItemIds[$itemRow['order_item_id']])) {
									$itemRow['quantity'] = $orderItemIds[$itemRow['order_item_id']];
								}
								$itemsArray[] = $itemRow;
								continue;
							}
							$showAsPack = CustomField::getCustomFieldData($itemRow['pack_product_id'], "SHOW_AS_PACK", "PRODUCTS");
							if (empty($showAsPack)) {
								$itemsArray[] = $itemRow;
								continue;
							}
							if (!array_key_exists($itemRow['pack_product_id'], $packArray)) {
								$packRow = array_merge($itemRow, getRowFromId("product_data", "product_id", $itemRow['pack_product_id']),
									getRowFromId("products", "product_id", $itemRow['pack_product_id']));
								$packArray[$itemRow['pack_product_id']] = $packRow;
							} else {
								$packArray[$itemRow['pack_product_id']]['sale_price'] += ($itemRow['sale_price'] * $itemRow['quantity']);
							}
						}
						$itemsArray = array_merge($itemsArray, $packArray);
						foreach ($itemsArray as $itemRow) {
							$itemRow['product_review_id'] = getFieldFromId("product_review_id", "product_reviews", "product_id", $itemRow['product_id'], "user_id = ?", $GLOBALS['gUserId']);
							$orderTotal += $itemRow['quantity'] * $itemRow['sale_price'];
							$itemRow['product_detail_link'] = (empty($itemRow['link_name']) ? "product-details?id=" . $itemRow['product_id'] : "/product/" . $itemRow['link_name']);
							$orderItems[] = $itemRow;
							if ($fflRequiredProductTagId) {
								$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $itemRow['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
								if (!empty($productTagLinkId)) {
									$fflRequired = true;
								}
							}
						}
						if (!empty($orderItems)) {
							$orderTotal -= $row['order_discount'];
							$orderTotal += $row['shipping_charge'];
							$orderTotal += $row['tax_charge'];
							$orderTotal += $row['handling_charge'];
						}
						if ($fflRequired) {
							$fileId = (new FFL($row['federal_firearms_licensee_id']))->getFieldData("file_id");
							if (!empty($fileId)) {
								$fflRequired = false;
							}
						}
						if ($fflRequired) {
							$fileId = getFieldFromId("file_id", "order_files", "order_id", $row['order_id']);
							if (!empty($fileId)) {
								$fflRequired = false;
							}
						}
						$orderShipments = array();
						$shipmentSet = executeQuery("select * from order_shipments where no_notifications = 0 and order_id = ? and internal_use_only = 0", $row['order_id']);
						while ($shipmentRow = getNextRow($shipmentSet)) {
							$orderShipments[] = $shipmentRow;
						}

						$orderItemElement = $this->getPageTextChunk("MY_ACCOUNT_ORDER_ITEM");
						if (empty($orderItemElement)) {
							$orderItemElement = $this->getFragment("MY_ACCOUNT_ORDER_ITEM");
						}
						if (empty($orderItemElement)) {
							ob_start();
							?>
                            <tr class="order-item my-account-table-item">
                                <td class="order-item-img align-center">
                                    <a href="%image_url%" class="pretty-photo"><img src="%image_url%"></a>
                                </td>
                                <td class="order-item-details product-description">
                                    <p><a href='%product_detail_link%' class="order-item-description">%description%</a></p>
                                    <p class="order-item-serial-number %serial_number_hidden%">Serial Number: %serial_numbers%</p>
                                    <p class="order-item-upc-code">UPC: %upc_code%</p>
                                </td>
                                <td class="order-item-quantity"><span class="sm-only">Quantity: </span>%quantity%</td>
                                <td class="order-total-amount"><span class="sm-only">Price: </span>$%sale_price%</td>
                                <td class="my-account-table-controls align-center">
                                    <span class="fad fa-star-sharp-half-stroke %product_review_class%" data-product_id="%product_id%" title="%product_review_text%"></span>
                                    <span class="fad fa-cart-shopping buy-again" data-product_id="%product_id%" title="Buy again"></span>
                                    <span class="fad fa-download download-product %file_id_hidden%" data-product_id="%product_id%" data-file_id="%file_id%" title="Download"></span>
                                    <span class="fad fa-receipt print-receipt" data-order_id="%order_id%" title="Receipt"></span>
                                    <span class="fad fa-messages-question contact-support" data-order_id="%order_id%" title="Contact Support"></span>
                                </td>
                            </tr>
							<?php
							$orderItemElement = ob_get_clean();
						}
						$fflRequiredElement = $this->getPageTextChunk("MY_ACCOUNT_ORDER_FFL_REQUIRED");
						if (empty($fflRequiredElement)) {
							$fflRequiredElement = $this->getFragment("MY_ACCOUNT_ORDER_FFL_REQUIRED");
						}
						if (empty($fflRequiredElement)) {
							ob_start();
							?>
                            <tr class="order-document" id="order_document_%order_id%">
                                <td colspan="5">
                                    <form id="_upload_form_%order_id%" enctype='multipart/form-data'>
                                        <input type="hidden" name="order_id" value="%order_id%">
                                        <div class="hidden form-line" id="_update_document_%order_id%">
                                            <input class="validate[required]" type="file" name="ffl_document_file">
                                            <div class='clear-div'></div>
                                        </div>
                                        <button class="order-document-upload" data-order_id="%order_id%">Upload FFL Document</button>
                                    </form>
                                </td>
                            </tr>
							<?php
							$fflRequiredElement = ob_get_clean();
						}
						$order = $this->getPageTextChunk("MY_ACCOUNT_ORDER");
						if (empty($order)) {
							$order = $this->getFragment("MY_ACCOUNT_ORDER");
						}
						if (empty($order)) {
							ob_start();
							?>
                            %order_header%
                            %order_items%
                            %ffl_required%
                            %order_shipments%
							<?php
							$order = ob_get_clean();
						}
						$orderShipmentsElement = $this->getPageTextChunk("MY_ACCOUNT_ORDER_SHIPMENTS");
						if (empty($orderShipmentsElement)) {
							$orderShipmentsElement = $this->getFragment("MY_ACCOUNT_ORDER_SHIPMENTS");
						}
						if (empty($orderShipmentsElement)) {
							ob_start();
							?>
                            <tr class="order-shipments">
                                <td colspan="5">
                                    <h3>Shipments</h3>

                                    <p>The "In Process" column is the date the shipment process is started and the date the order was submitted to our warehouse for shipment. When the package is shipped, tracking information will be shown.</p>
                                    <table class="order-shipments-table">
                                        <tr>
                                            <th>In Process</th>
                                            <th>Shipped To</th>
                                            <th>Carrier</th>
                                            <th>Tracking #</th>
                                            <th></th>
                                            <th>Products Included</th>
                                        </tr>
                                        %order_shipment_items%
                                    </table>
                                </td>
                            </tr>
							<?php
							$orderShipmentsElement = ob_get_clean();
						}
						$orderShipmentItemElement = $this->getPageTextChunk("MY_ACCOUNT_ORDER_SHIPMENT_ITEMS");
						if (empty($orderShipmentItemElement)) {
							$orderShipmentItemElement = $this->getFragment("MY_ACCOUNT_ORDER_SHIPMENT_ITEMS");
						}
						if (empty($orderShipmentItemElement)) {
							ob_start();
							?>
                            <tr>
                                <td>%date_shipped%</td>
                                <td>%full_name%</td>
                                <td>%carrier_description%</td>
                                <td>%tracking_identifier%</td>
                                <td>%tracking_url%</td>
                                <td>%shipment_item_list%</td>
                            </tr>
							<?php
							$orderShipmentItemElement = ob_get_clean();
						}
						$orderSubstitutions = $row;
						$orderSubstitutions['status_description'] = $statusDescription;
						$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $row['shipping_method_id']);
						$shippingMethodRow = $shippingMethodRow ?: array("description" => "None", "pickup" => 1);
						$orderSubstitutions['shipping_method'] = $shippingMethodRow['description'];
						$orderSubstitutions['shipping_address'] = ($shippingMethodRow['pickup'] ? "Not needed" : $shippingAddress);
						$orderSubstitutions['order_total'] = number_format($orderTotal, 2, ".", ",");
						$orderSubstitutions['order_date'] = date("m/d/Y", strtotime($row['order_time']));

						$orderHeaderElement = $this->getPageTextChunk("MY_ACCOUNT_ORDER_HEADER");
						if (empty($orderHeaderElement)) {
							$orderHeaderElement = $this->getFragment("MY_ACCOUNT_ORDER_HEADER");
						}
						if (empty($orderHeaderElement)) {
							ob_start();
							?>
                            <tr class="order-header">
                                <td colspan="5">
                                    <table>
                                        <tr>
                                            <td><span class="order-number">#%order_id%</span></td>
                                            <td class="order-status">%status_description%</td>
                                            <td><span class="order-date-title">Order Date</span><br><span class="order-date">%order_date%</span></td>
                                            <td><span class="order-shipping-method-title">Shipping Method</span><br><span class="shipping-method">%shipping_method%</span></td>
                                            <td><span class="order-shipping-title">Shipping Address</span><br><span class="shipping-address">%shipping_address%</span></td>
                                            <td><span class="order-total-title">Total</span><br><span class="order-total">$%order_total%</span></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
							<?php
							$orderHeaderElement = ob_get_clean();
						}
						$orderSubstitutions['order_header'] = PlaceHolders::massageContent($orderHeaderElement, $orderSubstitutions);

						$orderSubstitutions['order_items'] = "";
						foreach ($orderItems as $thisItem) {
							$thisItem['image_url'] = ProductCatalog::getProductImage($thisItem['product_id']);
							foreach ($GLOBALS['gImageTypes'] as $imageTypeRow) {
								$parameters = array('image_type' => strtolower($imageTypeRow['image_type_code']));
								$thisItem[strtolower($imageTypeRow['image_type_code']) . "_image_url"] = ProductCatalog::getProductImage($thisItem['product_id'], $parameters);
							}

							$thisItem['serial_number_hidden'] = (empty($thisItem['serial_numbers']) ? "hidden" : "");
							$thisItem['sale_price'] = number_format($thisItem['sale_price'], 2, ".", ",");
							$thisItem['product_review_class'] = (empty($thisItem['product_review_id']) ? "write-review" : "already-reviewed");
							$thisItem['product_review_text'] = (empty($thisItem['product_review_id']) ? "Write Review" : "Reviewed");
							$thisItem['file_id_hidden'] = ($thisItem['virtual_product'] && !empty($thisItem['file_id']) ? "" : "hidden");
							$thisItem['anticipated_ship_date'] = (empty($thisItem['anticipated_ship_date']) ? "" : "Will ship near " . date("m/d/Y", strtotime($thisItem['anticipated_ship_date'])));
							$orderSubstitutions['order_items'] .= PlaceHolders::massageContent($orderItemElement, array_merge($orderSubstitutions, $thisItem));
						}
						if ($fflRequired) {
							$orderSubstitutions['ffl_required'] = PlaceHolders::massageContent($fflRequiredElement, $orderSubstitutions);
						} else {
							$orderSubstitutions['ffl_required'] = "";
						}
						if (!empty($orderShipments)) {
							$orderShipmentsElement = PlaceHolders::massageContent($orderShipmentsElement, $orderSubstitutions);
							$orderShipmentItems = "";
							foreach ($orderShipments as $thisOrderShipment) {
								$shippingCarrierRow = getRowFromId("shipping_carriers", "shipping_carrier_id", $thisOrderShipment['shipping_carrier_id']);
								$thisOrderShipment['date_shipped'] = date("m/d/Y", strtotime($thisOrderShipment['date_shipped']));
								$thisOrderShipment['carrier_description'] = (empty($thisOrderShipment['shipping_carrier_id']) ? $thisOrderShipment['carrier_description'] : $shippingCarrierRow['description']);
								$thisOrderShipment['tracking_identifier'] = (empty($thisOrderShipment['tracking_identifier']) ? "Not Available" : $thisOrderShipment['tracking_identifier']);
								$thisOrderShipment['tracking_url'] = (empty($thisOrderShipment['tracking_identifier']) || empty($shippingCarrierRow['link_url']) ? "" : "<a href='" . str_replace("%tracking_identifier%", $thisOrderShipment['tracking_identifier'], $shippingCarrierRow['link_url']) . "' target='_blank'>Track Shipment</a>");
								$shipmentItemList = "";
								$itemSet = executeQuery("select * from order_shipment_items where order_shipment_id = ?", $thisOrderShipment['order_shipment_id']);
								$itemsArray = array();
								$packArray = array();
								while ($itemRow = getNextRow($itemSet)) {
									$orderItemSet = executeQuery("select * from order_items join products using (product_id) left outer join product_data using (product_id) where order_item_id = ?", $itemRow['order_item_id']);
									$orderItemRow = getNextRow($orderItemSet);
									$productDescription = $orderItemRow['description'] . (empty($orderItemRow['upc_code']) ? "" : ", UPC '" . $orderItemRow['upc_code'] . "'");
									if (empty($orderItemRow['pack_product_id'])) {
										$itemsArray[] = array("quantity" => $itemRow['quantity'], "description" => $productDescription);
										continue;
									}
									$showAsPack = CustomField::getCustomFieldData($orderItemRow['pack_product_id'], "SHOW_AS_PACK", "PRODUCTS");
									if (empty($showAsPack)) {
										$itemsArray[] = array("quantity" => $itemRow['quantity'], "description" => $productDescription);
										continue;
									}
									if (!array_key_exists($orderItemRow['pack_product_id'], $packArray)) {
										$packDescription = getFieldFromId("description", "products", "product_id", $orderItemRow['pack_product_id']);
										$packUpc = getFieldFromId("upc_code", "product_data", "product_id", $orderItemRow['pack_product_id']);
										$productDescription = $packDescription . (empty($packUpc) ? "" : ", UPC '" . $packUpc . "'");
										$quantity = min($itemRow['quantity'], $orderItemRow['pack_quantity']);
										$packArray[$orderItemRow['pack_product_id']] = array("quantity" => $quantity, "description" => $productDescription);
									} elseif ($itemRow['quantity'] < $packArray[$orderItemRow['pack_product_id']]['quantity']) {
										// in case multiple packs were ordered and not all are in the shipment, make sure we have the smallest shipped quantity
										$packArray[$orderItemRow['pack_product_id']]['quantity'] = $itemRow['quantity'];
									}
								}
								$itemsArray = array_merge($itemsArray, $packArray);
								foreach ($itemsArray as $item) {
									$shipmentItemList .= (empty($shipmentItemList) ? "" : "<br>") . "(" . $item['quantity'] . ") of " . $item['description'];
								}
								$thisOrderShipment['shipment_item_list'] = $shipmentItemList;
								$orderShipmentItems .= PlaceHolders::massageContent($orderShipmentItemElement, $thisOrderShipment);
							}
							$orderShipmentsElement = str_replace("%order_shipment_items%", $orderShipmentItems, $orderShipmentsElement);
							$orderSubstitutions['order_shipments'] = $orderShipmentsElement;
						} else {
							$orderSubstitutions['order_shipments'] = "";
						}
						$orders[] = PlaceHolders::massageContent($order, $orderSubstitutions);
					}
				}
				$returnArray['orders'] = $orders;
				ajaxResponse($returnArray);
				break;
			case "upload_document":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				if (empty($_FILES['ffl_document_file']) || empty($orderId)) {
					$returnArray['error_message'] = "Unable to upload document";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$fileId = createFile("ffl_document_file");
				$resultSet = executeQuery("insert into order_files (order_id,description,file_id) values (?,'Uploaded FFL document',?)",
					$orderId, $fileId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to upload document";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
					sendEmail(array("subject" => "Uploaded FFL", "body" => "Customer uploaded an FFL document for order ID " . $orderId, "notification_code" => "order_ffl_document"));
				}
				ajaxResponse($returnArray);
				break;
			case "get_invoices":
				$invoices = array();
				$returnArray = Invoices::getInvoices($GLOBALS['gUserRow']['contact_id']);
				$totalBalance = 0;

				if (!empty($returnArray['invoices'])) {
					$totalBalance = $returnArray['total_balance'];

					foreach ($returnArray['invoices'] as $invoice) {
						$invoiceSubstitutions = $invoice;

						$invoiceElement = $this->getPageTextChunk("MY_ACCOUNT_INVOICE");
						if (empty($invoiceElement)) {
							$invoiceElement = $this->getFragment("MY_ACCOUNT_INVOICE");
						}
						if (empty($invoiceElement)) {
							ob_start();
							?>
                            <tr class="invoice-item my-account-table-item %other_classes%" data-invoice_id="%invoice_id%">
                                <td class="invoice-number-cell">
                                    <a href="#" class='invoice-details'>%invoice_number%</a>
                                </td>
                                <td class="invoice-date-cell">%invoice_date%</td>
                                <td class="date-due-cell">%date_due%</td>
                                <td class="date-completed-cell">%date_completed%</td>
                                <td class="invoice-total-cell align-right">%invoice_total%</td>
                                <td class="balance-due-cell align-right invoice-amount">%balance_due%</td>
                                %if_has_value:order_id%
                                <td class="order-number-cell"><a href='/order-receipt?order_id=%order_id%'>%order_id%</a></td>
                                %endif%
                            </tr>
							<?php
							$invoiceElement = ob_get_clean();
						}

						$invoiceSubstitutions['other_classes'] = $invoice['overdue'] ? "invoice-overdue" : "";
						$invoiceSubstitutions['invoice_number'] = empty($invoice['invoice_number']) ? $invoice['invoice_id'] : $invoice['invoice_number'];
						$invoiceSubstitutions['invoice_date'] = date("m/d/Y", strtotime($invoice['invoice_date']));
						$invoiceSubstitutions['date_due'] = empty($invoice['date_due']) ? "" : date("m/d/Y", strtotime($invoice['date_due']));
						$invoiceSubstitutions['date_completed'] = empty($invoice['date_completed']) ? "" : date("m/d/Y", strtotime($invoice['date_completed']));
						$invoiceSubstitutions['invoice_total'] = number_format($invoice['invoice_total'], 2);
						$invoiceSubstitutions['balance_due'] = number_format($invoice['balance'], 2);

						$invoices[] = PlaceHolders::massageContent($invoiceElement, $invoiceSubstitutions);
					}
				}
				$returnArray['total_balance'] = $totalBalance;
				$returnArray['invoices'] = $invoices;
				ajaxResponse($returnArray);
				break;
			case "get_invoice_details":
				$invoiceDetails = $this->getPageTextChunk("MY_ACCOUNT_INVOICE_DETAILS");
				if (empty($invoiceDetails)) {
					$invoiceDetails = $this->getFragment("MY_ACCOUNT_INVOICE_DETAILS");
				}
				$invoiceDetailsItem = $this->getPageTextChunk("INVOICE_DETAILS_ITEM");
				if (empty($invoiceDetailsItem)) {
					$invoiceDetailsItem = $this->getFragment("INVOICE_DETAILS_ITEM");
				}
				$invoicePaymentsItem = $this->getPageTextChunk("MY_ACCOUNT_INVOICE_PAYMENTS_ITEM");
				if (empty($invoicePaymentsItem)) {
					$invoicePaymentsItem = $this->getFragment("MY_ACCOUNT_INVOICE_PAYMENTS_ITEM");
				}
				$returnArray = Invoices::getInvoiceDetails(array(
					"invoice_id" => $_GET['invoice_id'],
					"invoice_details_template" => $invoiceDetails,
					"invoice_details_item_template" => $invoiceDetailsItem,
					"invoice_payments_item_template" => $invoicePaymentsItem
				));
				ajaxResponse($returnArray);
				break;
			case "get_invoice_payment_form":
				$contactRow = Contact::getContact($GLOBALS['gUserRow']['contact_id']);
				$returnArray['form_content'] = Invoices::getPayInvoicesForm(array("contact_row" => $contactRow, "payment_text" => $this->getPageTextChunk("payment_text")));
				ajaxResponse($returnArray);
				break;
			case "create_payment":
				$contactRow = Contact::getContact($GLOBALS['gUserRow']['contact_id']);
				$returnArray = Invoices::createInvoicePayment(array("contact_row" => $contactRow, "database" => $this->iDatabase,
					"invoice_payment_received_template" => $this->getFragment("INVOICE_PAYMENT_RECEIVED")));
				ajaxResponse($returnArray);
				break;
			case "get_recently_viewed_products":
				$productElements = array();
				$recentlyViewedProductsDaysLimit = getPageTextChunk("MY_ACCOUNT_RECENTLY_VIEWED_PRODUCT_DAYS_LIMIT") ?: 30;
				$resultSet = executeQuery("select product_id, max(log_time) as log_time from product_view_log where contact_id = ?" .
					" and product_id in (select product_id from products where inactive = 0 and internal_use_only = 0 and (expiration_date is null or expiration_date >= current_date))" .
					" and log_time >= date_sub(current_timestamp, interval " . makeParameter($recentlyViewedProductsDaysLimit) .  " day)" .
					" group by product_id order by log_time desc", $GLOBALS['gUserRow']['contact_id']);

				if (!empty($resultSet['row_count'])) {
					$products = array();
					$productIds = array();
					while ($row = getNextRow($resultSet)) {
						$products[] = $row;
						$productIds[] = $row['product_id'];
					}
					$productCatalog = new ProductCatalog();
					$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);

					$wishListProductIds = array();
					$wishList = new WishList();
					foreach ($wishList->getWishListItems() as $wishListItem) {
						$wishListProductIds[] = $wishListItem['product_id'];
					}

					foreach ($products as $product) {
						$productCatalog = new ProductCatalog();
						$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
						if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
							$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
						}
						$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
						if (empty($neverOutOfStock)) {
							$product['inventory_quantity'] = $inventoryCounts[$product['product_id']];
						} else {
							$product['inventory_quantity'] = 1;
						}

						$productRow = ProductCatalog::getCachedProductRow($product['product_id']);
						$productDataRow = getRowFromId("product_data", "product_id", $product['product_id']);
						$productSubstitutions = array_merge($productRow, $productDataRow, $product);
						$productSubstitutions['small_image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("image_type" => "small", "default_image" => $missingProductImage));
						$productSubstitutions['image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("default_image" => $missingProductImage));

						$salePriceInfo = $productCatalog->getProductSalePrice($product['product_id'], array("product_information" => array_merge($productRow, $productDataRow)));
						$productSubstitutions['sale_price'] = $salePriceInfo['sale_price'];

						$productSubstitutions['in_wishlist'] = in_array($product['product_id'], $wishListProductIds);
						$productSubstitutions['wishlist_label'] = $productSubstitutions['in_wishlist'] ? "<i class='fad fa-star added'></i>" : "<i class='far fa-star'></i>";

						$productElement = $this->getPageTextChunk("MY_ACCOUNT_RECENTLY_VIEWED_PRODUCT");
						if (empty($productElement)) {
							$productElement = $this->getFragment("MY_ACCOUNT_RECENTLY_VIEWED_PRODUCT");
						}
						if (empty($productElement)) {
							ob_start();
							?>
                            <tr class="recently-viewed-product-item my-account-table-item %other_classes%" id="recently_viewed_product_%product_view_log_id%" data-product_id="%product_id%">
                                <td class="clickable align-center"><img src="%small_image_url%"></td>
                                <td class="clickable">%description%</td>
                                <td class="viewed-on">%log_time%</td>
                                <td class="sale-price">%sale_price%</td>
                                <td class="my-account-table-controls align-center">
                                    %if_has_value:inventory_quantity%
                                    <span class="fad fa-cart-shopping add-to-cart" data-product_id="%product_id%" title="Add to Cart"></span>
                                    %endif%
                                    %if_has_no_value:inventory_quantity%
                                    <span class="add-to-wishlist add-to-wishlist-%product_id%" data-product_id="%product_id%" data-text="<i class=&quot;far fa-star&quot;></i>"
                                          data-in_text="<i class=&quot;fad fa-star added&quot;></i>" data-adding_text="Adding">%wishlist_label%</span>
                                    %endif%
                                </td>
                            </tr>
							<?php
							$productElement = ob_get_clean();
						}
						$productElements[] = PlaceHolders::massageContent($productElement, $productSubstitutions);
					}
				}
				$returnArray['products'] = $productElements;
				ajaxResponse($returnArray);
				break;
			case "get_payment_methods":
				$accounts = array();
				$resultSet = executeQuery("select * from accounts where account_token is not null and inactive = 0 and contact_id = ?"
					. " order by account_label", $GLOBALS['gUserRow']['contact_id']);

				while ($row = getNextRow($resultSet)) {
					$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']);
					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodTypeId);
					$row['payment_method_type_id'] = $paymentMethodTypeId;
					$row['payment_method_type_code'] = $paymentMethodTypeCode;
					$row['recurring_payment_id'] = getFieldFromId("recurring_payment_id", "recurring_payments", "account_id", $row['account_id'], "(end_date is null or end_date > current_date)");

					$notes = (empty($row['recurring_payment_id']) ? "" : "Used in Recurring Payment");
					if (!empty($row['expiration_date'])) {
						if (time() > strtotime($row['expiration_date'])) {
							$notes .= (empty($notes) ? "" : "; ") . "EXPIRED";
						} elseif (time() > strtotime($row['expiration_date'] . " - 30 days")) {
							$notes .= (empty($notes) ? "" : "; ") . "Expiring soon";
						}
					}
					$row['account_notes'] = $notes;
					$row['account_label'] = htmlText(empty($row['account_label']) ? $row['account_number'] : $row['account_label']);
					$row['account_type'] = htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . " - " . $row['account_number']);
					$row['account_expiration'] = htmlText(empty($row['expiration_date']) ? "" : date("m/y", strtotime($row['expiration_date'])));
					$accounts[] = $row;
				}
				$returnArray['accounts'] = $accounts;
				ajaxResponse($returnArray);
				break;
			case "get_payment_method_form":
				$capitalizedFields = array();
				$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
				if (getPreference("USE_FIELD_CAPITALIZATION")) {
					$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
					while ($row = getNextRow($resultSet)) {
						$capitalizedFields[] = $row['column_name'];
					}
				}
				ob_start();
				?>
                <a href="#" id="return_to_payments" class="cancel-new-payment return-link">
                    <i class="fad fa-arrow-left" aria-hidden="true"></i>
                    Return to payments
                </a>
                <form id="_new_payment_method_form" data-url-action="save_payment_method" data-after-save-function="showPaymentMethods">
                    <h2>Billing Information</h2>
                    <p>Be sure to make payment methods that are no longer needed inactive.</p>
					<?= $forceSameAddress ? "<p>Billing information must match your contact information on file</p>" : "" ?>

                    <div class="form-line" id="_billing_first_name_row">
                        <label for="billing_first_name" class="required-label">First</label>
                        <input tabindex="10" type="text" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_billing_last_name_row">
                        <label for="billing_last_name" class="required-label">Last</label>
                        <input tabindex="10" type="text" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_billing_business_name_row">
                        <label for="billing_business_name">Business Name</label>
                        <input tabindex="10" type="text" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line checkbox-input" id="_same_address_row">
                        <label class=""></label>
                        <input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> value="1"><label class="checkbox-label" for="same_address">Billing address is same as primary address</label>
                        <div class='clear-div'></div>
                    </div>

                    <div id="_billing_address" class="hidden">
                        <h2>Billing Address</h2>

                        <div class="form-line" id="_billing_address_1_row">
                            <label for="billing_address_1" class="required-label">Address</label>
                            <input tabindex="10" type="text" autocomplete='chrome-off' autocomplete='off' data-prefix="billing_" class="autocomplete-address validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
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
                            <input tabindex="10" type="text" class="validate[required] uppercase" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_country_id_row">
                            <label for="billing_country_id" class="">Country</label>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
								<?php
								foreach (getCountryArray() as $countryId => $countryName) {
									?>
                                    <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>
                    </div>

                    <div class="form-line" id="_payment_method_id_row">
                        <h2>Payment Method</h2>
                        <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''"
                                id="payment_method_id" name="payment_method_id" aria-label="Payment Method">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select *,(select payment_method_type_code from payment_method_types where " .
								"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
								"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
								(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
								"inactive = 0 and internal_use_only = 0 and client_id = ? and payment_method_type_id in " .
								"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT')) " .
								"order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
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

                        <div class="form-line" id="_card_code_row">
                            <label for="card_code" class="">Security Code</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="card_code" name="card_code" placeholder="CVV Code" value="">
                            <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img alt="cvv image" id="cvv_image" src="/images/cvv_code.gif"></a>
                            <div class='clear-div'></div>
                        </div>
                    </div> <!-- payment_method_credit_card -->

                    <div class="payment-method-fields" id="payment_method_bank_account">
                        <div class="form-line" id="_routing_number_row">
                            <label for="routing_number" class="">Bank Routing Number</label>
                            <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="9" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_bank_account_number_row">
                            <label for="bank_account_number" class="">Account Number</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                            <div class='clear-div'></div>
                        </div>
						<?php if (!empty($this->getPageTextChunk("VERIFY_BANK_ACCOUNT_NUMBER"))) { ?>
                            <div class="form-line" id="_bank_account_number_again_row">
                                <label for="bank_account_number_again" class="">Re-enter Account Number</label>
                                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="text" class="validate[equals[bank_account_number]]" size="20" maxlength="20" id="bank_account_number_again" name="bank_account_number_again" placeholder="Repeat Bank Account Number" value="">
                                <div class='clear-div'></div>
                            </div>
						<?php } ?>
                    </div> <!-- payment_method_bank_account -->

                    <div class="form-line" id="_account_label_row">
                        <label for="account_label" class="">Account Nickname (for future reference)</label>
                        <input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_set_recurring_row">
                        <input tabindex="10" type="checkbox" checked="checked" class="" id="set_recurring" name="set_recurring" value="1">
                        <label class="checkbox-label" for="set_recurring">Use as default payment method</label>
                        <div class='clear-div'></div>
                    </div>

                    <div class="save-changes-wrapper">
                        <p class="error-message"></p>
                        <button type="button" class="save-changes"><?= getLanguageText("Save Changes") ?></button>
                        <button type="button" class="cancel-changes cancel-new-payment"><?= getLanguageText("Cancel") ?></button>
                    </div>
                </form>
				<?php
				$returnArray['form_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "save_payment_method":
				$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
					"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
						$_POST['payment_method_id']));
				$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
				$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
				if ($forceSameAddress || !empty($_POST['same_address'])) {
					$fields = array("address_1", "city", "state", "postal_code", "country_id");
					if ($forceSameAddress) {
						$fields[] = "first_name";
						$fields[] = "last_name";
					}
					foreach ($fields as $fieldName) {
						$_POST['billing_' . $fieldName] = $GLOBALS['gUserRow'][$fieldName];
					}
				}
				$requiredFields = array(
					"billing_first_name" => array(),
					"billing_last_name" => array(),
					"billing_address_1" => array(),
					"billing_city" => array(),
					"billing_state" => array("billing_country_id" => "1000"),
					"billing_postal_code" => array("billing_country_id" => "1000"),
					"billing_country_id" => array(),
					"payment_method_id" => array(),
					"account_number" => array("payment_method_type_code" => "CREDIT_CARD"),
					"expiration_month" => array("payment_method_type_code" => "CREDIT_CARD"),
					"expiration_year" => array("payment_method_type_code" => "CREDIT_CARD"),
					"card_code" => array("payment_method_type_code" => "CREDIT_CARD"),
					"routing_number" => array("payment_method_type_code" => "BANK_ACCOUNT"),
					"bank_account_number" => array("payment_method_type_code" => "BANK_ACCOUNT"));
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
				$_POST['account_number'] = str_replace(" ", "", $_POST['account_number']);
				$_POST['account_number'] = str_replace("-", "", $_POST['account_number']);
				$_POST['bank_account_number'] = str_replace(" ", "", $_POST['bank_account_number']);
				$_POST['bank_account_number'] = str_replace("-", "", $_POST['bank_account_number']);
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactRow = Contact::getContact($contactId);

				$eCommerce = eCommerce::getEcommerceInstance();
				$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
				if (!empty($achMerchantAccount)) {
					$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
				}
				$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

				if (!$useECommerce || empty($useECommerce)) {
					$returnArray['error_message'] = "Unable to connect to Merchant Services account. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}
				if (!$useECommerce->hasCustomerDatabase()) {
					$returnArray['error_message'] = "Merchant Services account does not support saving payment methods. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}

				$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $GLOBALS['gMerchantAccountId']);
				if (empty($merchantIdentifier)) {
					if (function_exists("_localMyAccountCustomPaymentFields")) {
						$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
						if (is_array($additionalFields)) {
							$contactRow = array_merge($contactRow, $additionalFields);
						}
					}
					$success = $useECommerce->createCustomerProfile($contactRow);
					$response = $useECommerce->getResponse();
					if ($success) {
						$merchantIdentifier = $response['merchant_identifier'];
					}
				}
				if (empty($merchantIdentifier)) {
					$returnArray['error_message'] = "Unable to create the Payment Method. Please contact customer service. #683";
					ajaxResponse($returnArray);
					break;
				}

				$testOrderId = date("Z") + 60000;
				if (!$isBankAccount) {
					$paymentArray = array("amount" => "1.00", "order_number" => $testOrderId, "description" => "Test Transaction", "authorize_only" => true,
						"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
						"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
						"email_address" => $contactRow['email_address'], "contact_id" => $contactId);
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['card_code'];

					if (function_exists("_localMyAccountCustomPaymentFields")) {
						$additionalFields = _localMyAccountCustomPaymentFields($contactId);
						if (is_array($additionalFields)) {
							$paymentArray = array_merge($paymentArray, $additionalFields);
						}
					}

					$success = $useECommerce->authorizeCharge($paymentArray);
					$response = $useECommerce->getResponse();
					if ($success) {
						$paymentArray['transaction_identifier'] = $response['transaction_id'];
						$useECommerce->voidCharge($paymentArray);
					} else {
						$returnArray['error_message'] = "Authorization failed: " . $response['response_reason_text'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$this->iDatabase->startTransaction();
				$accountLabel = $_POST['account_label'];
				if (empty($accountLabel)) {
					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
				}
				$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['business_name']);
				$accountNumber = "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
				$resultSet = executeQuery("insert into accounts (contact_id, account_label, payment_method_id, full_name, account_number, expiration_date) values (?,?,?,?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
					$fullName, $accountNumber, date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year'])));
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$accountId = $resultSet['insert_id'];

				$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
					"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
					"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
					"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id']);
				if ($isBankAccount) {
					$paymentArray['bank_routing_number'] = $_POST['routing_number'];
					$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
					$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
				} else {
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['card_code'];
				}
				if (function_exists("_localMyAccountCustomPaymentFields")) {
					$additionalFields = _localMyAccountCustomPaymentFields($contactId);
					if (is_array($additionalFields)) {
						$paymentArray = array_merge($paymentArray, $additionalFields);
					}
				}

				$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
				if (!$success) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create account. Please contact customer service. #157";
					ajaxResponse($returnArray);
					break;
				}
				$count = 0;
				if (!empty($_POST['set_recurring'])) {
					$resultSet = executeQuery("update recurring_payments set account_id = ?, requires_attention = 0 where (end_date > current_date or end_date is null) and contact_id = ? and account_id is not null and account_id <> ?",
						$accountId, $contactId, $accountId);
					$count = $resultSet['affected_rows'];
					if ($count > 0) {
						$returnArray['info_message'] = "payment account added and updated " . $count . " recurring payment" . ($count == 1 ? "" : "s");
						ContactPayment::notifyCRM($contactId, true);
					}
				}

				$this->iDatabase->commitTransaction();

				$emailAddresses = getNotificationEmails("PAYMENT_METHOD_ADDED");
				if (!empty($emailAddresses)) {
					$subject = "Payment Method added";
					$body = "<p>A payment method was added by " . getDisplayName($GLOBALS['gUserRow']['contact_id']) . ", contact ID " . $GLOBALS['gUserRow']['contact_id'] . "." . (empty($count) ? "" : " " . $count . " recurring payment" . ($count == 1 ? "" : "s") .
							" were updated to use this new payment method.") . "</p>";
					sendEmail(array("body" => $body, "subject" => $subject, "email_addresses" => $emailAddresses));
				}

				addActivityLog("Added new payment method");
				ajaxResponse($returnArray);
				break;
			case "make_account_inactive":
				$accountId = getFieldFromId("account_id", "accounts", "account_id", $_GET['account_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
				if (empty($accountId)) {
					$returnArray['error_message'] = "Unable to remove account";
					ajaxResponse($returnArray);
					break;
				}
				$userCanDelete = $this->getPageTextChunk("USER_CAN_DELETE_PAYMENT_METHOD_IN_USE");
				if (!$userCanDelete) {
					$recurringPaymentId = getFieldFromId("recurring_payment_id", "recurring_payments", "account_id", $accountId);
					if (!empty($recurringPaymentId)) {
						$returnArray['error_message'] = "Unable to remove account because it is used in a recurring payment.";
						ajaxResponse($returnArray);
						break;
					}
				}
				$accountRow = getRowFromId("accounts", "account_id", $accountId);
				$merchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
				$accountsDataSource = new DataSource("accounts");
				$accountsDataSource->setSaveOnlyPresent(true);
				$accountsDataSource->saveRecord(array("name_values" => array("inactive" => "1", "account_token" => "", "merchant_identifier" => "", "merchant_account_id" => ""), "primary_id" => $accountId));

				$merchantIdentifier = $accountRow['merchant_identifier'];
				if (empty($merchantIdentifier)) {
					$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $GLOBALS['gUserRow']['contact_id'], "merchant_account_id = ?", $merchantAccountId);
				}
				if (!empty($accountRow['account_token']) && !empty($merchantIdentifier)) {
					$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
					if ($eCommerce) {
						$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier,
							"account_token" => $accountRow['account_token']));
					}
				}
				$resultSet = executeQuery("select * from recurring_payments where account_id = ?", $accountId);
				$recurringPaymentsTable = new DataTable("recurring_payments");
				$recurringPaymentsTable->setSaveOnlyPresent(true);
				$contactSubscriptionsTable = new DataTable("contact_subscriptions");
				$contactSubscriptionsTable->setSaveOnlyPresent(true);
				while ($row = getNextRow($resultSet)) {
					$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_subscription_id", $row['contact_subscription_id']);

					# Don't make the contact subscription inactive. By making the recurring payment inactive, the subscription will expire.
					if ($contactSubscriptionRow) {
						$subscriptionName = "Subscription '" . getFieldFromId("description", "subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id']) . "'";
						sendEmail(array("subject" => $subscriptionName . " Cancelled", "body" => $subscriptionName . " cancelled by " . getDisplayName($GLOBALS['gUserRow']['contact_id'])
							. " (Contact ID " . $GLOBALS['gUserRow']['contact_id'] . ").\n\nReason: Cancelled because user deleted payment method.", "notification_code" => "SUBSCRIPTIONS"));
					}
				}
				updateUserSubscriptions($GLOBALS['gUserRow']['contact_id']);
				ajaxResponse($returnArray);
				break;
			case "update_contact_info":
				$requiredFields = array(
					"first_name" => array(),
					"last_name" => array(),
					"address_1" => array(),
					"city" => array(),
					"country_id" => array(),
					"email_address" => array(),
					"state" => array("country_id" => "1000"),
					"postal_code" => array("country_id" => "1000"),
					"user_name" => array("create_account" => "1"));
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['create_account'] = $_POST['create_account'];
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				if (!empty($_POST['create_account'])) {
					if (!empty($_POST['new_password']) && !isPCIPassword($_POST['new_password'])) {
						$minimumPasswordLength = getPreference("minimum_password_length");
						if (empty($minimumPasswordLength)) {
							$minimumPasswordLength = 10;
						}
						if (getPreference("PCI_COMPLIANCE")) {
							$noPasswordRequirements = false;
						} else {
							$noPasswordRequirements = getPreference("no_password_requirements");
						}
						$returnArray['error_message'] = getSystemMessage("password_minimum_standards", "Password does not meet minimum standards. Must be at least " . $minimumPasswordLength .
							" characters long" . ($noPasswordRequirements ? "" : " and include an upper and lowercase letter and a number"));
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (!$GLOBALS['gLoggedIn'] && !empty($_POST['new_password'])) {
					if (getPreference("PCI_COMPLIANCE")) {
						executeQuery("delete from user_passwords where time_changed < date_sub(current_date,interval 2 year)");
						$resultSet = executeQuery("select * from user_passwords where user_id = ?", $GLOBALS['gUserId']);
						while ($row = getNextRow($resultSet)) {
							$thisPassword = hash("sha256", $GLOBALS['gUserId'] . $row['password_salt'] . $_POST['new_password']);
							if ($thisPassword == $row['new_password']) {
								$returnArray['error_message'] = getSystemMessage("recent_password", "You cannot reuse a recent password.");
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}
				}
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactTable = new DataTable("contacts");
				$contactTable->setSaveOnlyPresent(true);
				if (empty($contactId)) {
					$resultSet = executeQuery("select * from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
						"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					}
					$_POST['date_created'] = date("Y-m-d");
					if (empty($_POST['source_id'])) {
						$_POST['source_id'] = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
						if (empty($_POST['source_id'])) {
							$_POST['source_id'] = getSourceFromReferer($_SERVER['HTTP_REFERER']);
						}
					}
					if (empty($_POST['contact_type_id'])) {
						$_POST['contact_type_id'] = getFieldFromId("contact_type_id", "contact_types", "contact_type_code", $_POST['contact_type_code'], "inactive = 0");
					}
				} else {
					unset($_POST['source_id']);
					unset($_POST['contact_type_id']);
				}
				if (!$contactId = $contactTable->saveRecord(array("name_values" => $_POST, "primary_id" => $contactId))) {
					$returnArray['error_message'] = getSystemMessage("basic", $contactTable->getErrorMessage()) . ":" . $contactTable->getErrorMessage();
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$phoneUpdated = false;
				if (!empty($_POST["phone_number"])) {
					$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = 'Primary'", $contactId);
					if ($row = getNextRow($resultSet)) {
						if ($_POST["phone_number"] != $row['phone_number']) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST["phone_number"], $row['phone_number_id']);
							$phoneUpdated = true;
						}
					} else {
						executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Primary')",
							$contactId, $_POST["phone_number"]);
						$phoneUpdated = true;
					}
				} else {
					$resultSet = executeQuery("delete from phone_numbers where description = 'Primary' and contact_id = ?", $contactId);
					if ($resultSet['affected_rows'] > 0) {
						$phoneUpdated = true;
					}
				}
				if (!empty($_POST["cell_phone_number"])) {
					$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = 'cell'", $contactId);
					if ($row = getNextRow($resultSet)) {
						if ($_POST["cell_phone_number"] != $row['phone_number']) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST["cell_phone_number"], $row['phone_number_id']);
							$phoneUpdated = true;
						}
					} else {
						executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'cell')",
							$contactId, $_POST["cell_phone_number"]);
						$phoneUpdated = true;
					}
					$customFieldId = CustomField::getCustomFieldIdFromCode("RECEIVE_SMS");
					if (empty($customFieldId)) {
						$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
						$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
							$GLOBALS['gClientId'], "RECEIVE_SMS", "Receive Text Notifications", $customFieldTypeId, "Receive Text Notifications");
						$customFieldId = $insertSet['insert_id'];
						executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
					}
					CustomField::setCustomFieldData($contactId, "RECEIVE_SMS", 'true');
				} else {
					$resultSet = executeQuery("delete from phone_numbers where description = 'cell' and contact_id = ?", $contactId);
					if ($resultSet['affected_rows'] > 0) {
						$phoneUpdated = true;
					}
				}
				if ($contactTable->getColumnsChanged() > 0 || $phoneUpdated) {
					addActivityLog("Updated Contact Info");
				}

				if (!empty($_POST['create_account']) || $GLOBALS['gLoggedIn']) {
					$userTable = new DataTable("users");
					$userTable->setSaveOnlyPresent(true);
					$saveData = array();

					if ($_POST['user_name'] != $GLOBALS['gUserRow']['user_name'] && !empty($_POST['user_name'])) {
						$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($_POST['user_name']), "user_id <> ? and (client_id = ? or superuser_flag = 1)", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
						if (!empty($checkUserId)) {
							$returnArray['error_message'] = "User name is already taken. Please choose another.";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$saveData['user_name'] = $_POST['user_name'];
					}

					if (!empty($_POST['email_address']) && !$GLOBALS['gLoggedIn']) {
						$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address'], "contact_id in (select contact_id from users)");
						if (!empty($existingContactId)) {
							$returnArray['error_message'] = "A User already exists with this email address. Please log in.";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}

					if (!empty($_POST['new_password'])) {
						$currentPassword = hash("sha256", $GLOBALS['gUserId'] . $GLOBALS['gUserRow']['password_salt'] . $_POST['current_password']);
						if ($GLOBALS['gLoggedIn'] && $currentPassword != $GLOBALS['gUserRow']['password']) {
							$returnArray['error_message'] = "Password cannot be reset because current password is not correct.";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						} else {
							$saveData['password_salt'] = getRandomString(64);
							$saveData['password'] = hash("sha256", $GLOBALS['gUserId'] . $saveData['password_salt'] . $_POST['new_password']);
						}
					}

					if (array_key_exists("security_question_id", $_POST)) {
						$saveData['security_question_id'] = $_POST['security_question_id'];
					}
					if (array_key_exists("answer_text", $_POST)) {
						$saveData['answer_text'] = $_POST['answer_text'];
					}
					if (array_key_exists("secondary_security_question_id", $_POST)) {
						$saveData['secondary_security_question_id'] = $_POST['secondary_security_question_id'];
					}
					if (array_key_exists("secondary_answer_text", $_POST)) {
						$saveData['secondary_answer_text'] = $_POST['secondary_answer_text'];
					}
					$confirmUserAccount = getPreference("CONFIRM_USER_ACCOUNT");
					if (!empty($confirmUserAccount) && empty($GLOBALS['gUserId'])) {
						$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
						$saveData['verification_code'] = $randomCode;
						$saveData['locked'] = "1";
					}
					if (!empty($saveData)) {
						if (!$GLOBALS['gLoggedIn']) {
							$saveData['contact_id'] = $contactId;
							$saveData['date_created'] = date("Y-m-d");
						}
						if (!$userId = $userTable->saveRecord(array("name_values" => $saveData, "primary_id" => $GLOBALS['gUserId']))) {
							$returnArray['error_message'] = getSystemMessage("basic", $userTable->getErrorMessage());
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						if (!empty($confirmUserAccount) && !empty($saveData['locked'])) {
							$confirmLink = "https://" . $_SERVER['HTTP_HOST'] . "/confirmuseraccount.php?user_id=" . $userId . "&hash=" . $randomCode;
							sendEmail(array("email_address" => $_POST['email_address'], "send_immediately" => true, "email_code" => "ACCOUNT_CONFIRMATION", "substitutions" => array("confirmation_link" => $confirmLink), "subject" => "Confirm Email Address", "body" => "<p>Click <a href='" . $confirmLink . "'>here</a> to confirm your email address and complete the creation of your user account.</p>"));
							logout();
						}
						if (array_key_exists("password", $saveData) && $GLOBALS['gLoggedIn']) {
							executeQuery("insert into user_passwords (user_id,password_salt,password,time_changed) values (?,?,?,now())", $GLOBALS['gUserId'], $saveData['password_salt'], $saveData['password']);
							executeQuery("update users set last_password_change = now() where user_id = ?", $GLOBALS['gUserId']);
							addActivityLog("Reset Password");
						} else if (!$GLOBALS['gLoggedIn']) {
							$currentPassword = hash("sha256", $userId . $saveData['password_salt'] . $_POST['new_password']);
							executeQuery("update users set password = ? where user_id = ?", $currentPassword, $userId);
						}
					}
					if ($userTable->getColumnsChanged() > 0) {
						addActivityLog("Updated User Information");
					}
				}

				$customFields = CustomField::getCustomFields("contacts", "MY_ACCOUNT");
				foreach ($customFields as $thisCustomField) {
					$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
					if (!$customField->saveData(array_merge($_POST, array("primary_id" => $contactId)))) {
						$returnArray['error_message'] = $customField->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$zaiusApiKey = getPreference("ZAIUS_API_KEY");
				if (!empty($zaiusApiKey)) {
					$contactRow = Contact::getContact($contactId);
					$phoneNumber = Contact::getContactPhoneNumber($contactId);
					$customer = array(array("attributes" => array(
						"coreware_contact_id" => strval($contactRow['contact_id']),
						"first_name" => $contactRow['first_name'],
						"last_name" => $contactRow['last_name'],
						"email" => $contactRow['email_address'],
						"street1" => $contactRow['address_1'],
						"street2" => $contactRow['address_2'],
						"city" => $contactRow['city'],
						"state" => $contactRow['state'],
						"zip" => $contactRow['postal_code'],
						"country" => getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']),
						"phone" => $phoneNumber
					)));
					$zaiusObject = new Zaius($zaiusApiKey);
					$result = $zaiusObject->postApi("profiles", $customer);
					if (!$result) {
						addProgramLog("Zaius Error: " . $zaiusObject->getErrorMessage());
					}
				}

				$returnArray['info_message'] = ($GLOBALS['gLoggedIn'] ? "Changes saved" : "Account Created");
				if (!empty($userId)) {
					$emailId = getFieldFromId("email_id", "emails", "email_code", "NEW_ACCOUNT", "inactive = 0");
					if (!empty($emailId)) {
						$substitutions = $_POST;
						unset($substitutions['new_password']);
						unset($substitutions['password_again']);
						sendEmail(array("email_id" => $emailId, "contact_id" => $contactId, "substitutions" => $substitutions, "email_address" => $_POST['email_address']));
					}
					if (empty($confirmUserAccount) || !is_array($saveData) || empty($saveData['locked'])) {
						login($userId);
					}
				}
				if (!empty($confirmUserAccount)) {
					logout();
					$returnArray['info_message'] = "Please check your email and confirm your user account before you attempt to log in.";
				}
				ajaxResponse($returnArray);
				break;
			case "get_contact_info":
				$contactInfo = array();
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactRow = $GLOBALS['gUserRow'];

				$phoneNumber = $otherPhoneNumber = $cellPhoneNumber = false;
				foreach ($GLOBALS['gUserRow']['phone_numbers'] as $thisPhone) {
					if ($thisPhone['description'] == "Primary" && empty($phoneNumber)) {
						$phoneNumber = $thisPhone['phone_number'];
					} else if (!in_array($thisPhone, array("cell", "mobile", "text")) && empty($otherPhoneNumber)) {
						$otherPhoneNumber = $thisPhone['phone_number'];
					} else if (in_array($thisPhone, array("cell", "mobile", "text")) && empty($cellPhoneNumber)) {
						$cellPhoneNumber = $thisPhone['phone_number'];
					}
				}
				if (empty($phoneNumber)) {
					$phoneNumber = $otherPhoneNumber;
				}

				$contactInfo['phone_number'] = $phoneNumber;
				$contactInfo['cell_phone_number'] = $cellPhoneNumber;

				$addressDetails = empty($contactRow['city']) ? "" : $contactRow['city'];
				if (!empty($contactRow['state'])) {
					$addressDetails .= (empty($addressDetails) ? "" : ", ") . $contactRow['state'];
				}
				if (!empty($contactRow['postal_code'])) {
					$addressDetails .= (empty($addressDetails) ? "" : ", ") . $contactRow['postal_code'];
				}
				$contactInfo['address_details'] = $addressDetails;

				foreach (array("first_name", "middle_name", "last_name", "business_name", "email_address", "address_1", "address_2", "city", "state", "postal_code", "country_id") as $key) {
					$contactInfo[$key] = $contactRow[$key];
				}
				$contactInfo['full_name'] = getDisplayName($contactId);
				$contactInfo['user_name'] = $GLOBALS['gUserRow']['user_name'];

				$returnArray['contact_info'] = $contactInfo;
				ajaxResponse($returnArray);
				break;
			case "get_identifiers":
				$identifiers = array();
				$availableContactIdentifierTypes = array();
				$allContactIdentifierTypes = array();
				$resultSet = executeQuery("select contact_identifier_type_id, description, allow_image, required from contact_identifier_types where client_id = ? and inactive = 0 and internal_use_only = 0 and user_editable = 1", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$allContactIdentifierTypes[] = $row;
					$identifierRow = getRowFromId("contact_identifiers", "contact_id", $GLOBALS['gUserRow']['contact_id'],
						"contact_identifier_type_id = ?", $row['contact_identifier_type_id']);
					if (empty($identifierRow)) {
						$availableContactIdentifierTypes[] = $row;
					} else {
						$row['contact_identifier_id'] = $identifierRow['contact_identifier_id'];
						$row['identifier_value'] = $identifierRow['identifier_value'];
						$row['image_id'] = $identifierRow['image_id'];
						$identifiers[] = $row;
					}
				}
				$returnArray['identifiers'] = $identifiers;
				$returnArray['available_contact_identifier_types'] = $availableContactIdentifierTypes;
				$returnArray['all_contact_identifier_types'] = $allContactIdentifierTypes;
				ajaxResponse($returnArray);
				break;
			case "save_identifier":
				if (empty($_POST['contact_identifier_type_id']) || empty($_POST['identifier_value'])) {
					$returnArray['error_message'] = "Identifier type and value is required";
					ajaxResponse($returnArray);
					break;
				}
				$contactIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_id",
					$_POST['contact_identifier_type_id'], "client_id = ? and inactive = 0 and internal_use_only = 0 and user_editable = 1", $GLOBALS['gClientId']);
				if (empty($contactIdentifierTypeId)) {
					$returnArray['error_message'] = "Identifier type is invalid";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_id", $contactId,
					"contact_identifier_type_id = ?", $contactIdentifierTypeId);

				$dataTable = new DataTable("contact_identifiers");
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "identifier_value" => $_POST['identifier_value'],
					"contact_identifier_type_id" => $contactIdentifierTypeId, "image_id" => $_POST['image_id']), "primary_id" => $contactIdentifierId))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				addActivityLog("Added new contact identifier");
				ajaxResponse($returnArray);
				break;
			case "delete_identifier":
				$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_identifier_id", $_GET['contact_identifier_id'],
					"contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
				if (empty($contactIdentifierId)) {
					$returnArray['error_message'] = "Unable to delete identifier";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from contact_identifiers where contact_identifier_id = ?", $contactIdentifierId);
				ajaxResponse($returnArray);
				break;
			case "update_mailing_list":
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", $_GET['mailing_list_id']);

				if (!empty($mailingListId)) {
					$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
					if (!empty($mailingListRow)) {
						if (!empty($_GET['opt_in'])) {
							if (!empty($mailingListRow['date_opted_out'])) {
								$contactMailingListSource = new DataSource("contact_mailing_lists");
								$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
								addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
							}
						} else {
							if (empty($mailingListRow['date_opted_out'])) {
								$contactMailingListSource = new DataSource("contact_mailing_lists");
								$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_out" => date("Y-m-d")), "primary_id" => $mailingListRow['contact_mailing_list_id']));
								executeQuery("update contact_mailing_lists set date_opted_out = now() where contact_mailing_list_id = ?",
									$mailingListRow['contact_mailing_list_id']);
								addActivityLog("Opted out of mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
							}
						}
					} else {
						if (!empty($_GET['opt_in'])) {
							$contactMailingListSource = new DataSource("contact_mailing_lists");
							$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
							addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
						}
					}
				} else {
					$returnArray['error_message'] = "Unable to update mailing list";
					ajaxResponse($returnArray);
					break;
				}
				ajaxResponse($returnArray);
				break;
			case "update_ffl_dealer":
				if (!empty($_POST['federal_firearms_licensee_id'])) {
					CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER", $_POST['federal_firearms_licensee_id']);
				} else {
					$returnArray['error_message'] = "No selected FFL dealer";
				}
				ajaxResponse($returnArray);
				break;
			case "get_courses":
				$courses = array();
				$resultSet = executeQuery("select * from courses where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")
					. " and (product_id is not null or course_id in (select course_id from course_attendances where user_id = ?)) order by sort_order, description",
					$GLOBALS['gClientId'], $GLOBALS['gUserId']);

				if (!empty($resultSet['row_count'])) {
					$educationLink = $this->getPageTextChunk("EDUCATION_LINK");
					while ($row = getNextRow($resultSet)) {
						if (!Education::canAccessCourse($row['course_id'])) {
							continue;
						}
						$attendanceSet = executeQuery("select min(start_date) as start_date, max(date_completed) as date_completed from course_attendances where course_id = ? and user_id = ?", $row['course_id'], $GLOBALS['gUserId']);
						if (!$attendanceRow = getNextRow($attendanceSet)) {
							$attendanceRow = array();
						}
						$courseSubstitutions = $row;
						$courseSubstitutions['education_link'] = empty($educationLink) ? "" : $educationLink . "?id=" . $row['course_id'];
						$courseSubstitutions['description'] = htmlText($row['description']);
						$courseSubstitutions['start_date'] = empty($attendanceRow['start_date']) ? "" : date("m/d/Y", strtotime($attendanceRow['start_date']));
						$courseSubstitutions['date_completed'] = empty($attendanceRow['date_completed']) ? "" : date("m/d/Y", strtotime($attendanceRow['date_completed']));

						$courseElement = $this->getPageTextChunk("MY_ACCOUNT_COURSE");
						if (empty($courseElement)) {
							$courseElement = $this->getFragment("MY_ACCOUNT_COURSE");
						}
						if (empty($courseElement)) {
							ob_start();
							?>
                            <tr class="course-item my-account-table-item %other_classes%" id="course_%course_id%" data-course_id="%course_id%">
                                <td>
                                    %if_has_value:education_link%
                                    <a href="%education_link%">%description%</a>
                                    %else%
                                    %description%
                                    %endif%
                                </td>
                                <td>%start_date%</td>
                                <td>%date_completed%</td>
                            </tr>
							<?php
							$courseElement = ob_get_clean();
						}
						$courses[] = PlaceHolders::massageContent($courseElement, $courseSubstitutions);
					}
				}
				$returnArray['courses'] = $courses;
				ajaxResponse($returnArray);
				break;
			case "get_event_reservations":
				$eventReservations = array();
				$resultSet = executeQuery("select * from events where contact_id = ? and client_id = ? and event_id not in (select event_id from event_facility_recurrences) and " .
					"(select count(distinct facility_id) from event_facilities where event_id = events.event_id) = 1 and event_id not in (select event_id from event_registrants) order by start_date", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
				if (!empty($resultSet['row_count'])) {
					while ($row = getNextRow($resultSet)) {
						$hourSet = executeQuery("select * from event_facilities where event_id = ?", $row['event_id']);
						if ($hourRow = getNextRow($hourSet)) {
							$dateValue = date("m/d/Y", strtotime($hourRow['date_needed']));
							$hour = $hourRow['hour'];
							$facilityDescription = getFieldFromId("description", "facilities", "facility_id", $hourRow['facility_id']);
						} else {
							continue;
						}
						$workingHour = floor($hour);
						$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
						$displayMinutes = ($hour - $workingHour) * 60;
						$displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
						$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
						$dateValue .= " " . $displayTime;

						$eventReservationSubstitutions = $row;
						$eventReservationSubstitutions['event_type'] = empty($row['event_type_id']) ? "N/A" : getFieldFromId("description",
							"event_types", "event_type_id", $row['event_type_id']);
						$eventReservationSubstitutions['description'] = htmlText($row['description']);
						$eventReservationSubstitutions['facility'] = htmlText($facilityDescription);
						$eventReservationSubstitutions['event_date'] = $dateValue;
						$eventReservationSubstitutions['paid_order_id'] = getFieldFromId("order_id", "orders", "order_id", $row['order_id'],
							"order_id in (select order_id from order_payments where amount > 0)");
						$eventReservationSubstitutions['allow_cancel'] = $row['start_date'] >= date("Y-m-d");

						$eventReservationElement = $this->getPageTextChunk("MY_ACCOUNT_EVENT_RESERVATION");
						if (empty($eventReservationElement)) {
							$eventReservationElement = $this->getFragment("MY_ACCOUNT_EVENT_RESERVATION");
						}
						if (empty($eventReservationElement)) {
							ob_start();
							?>
                            <tr class="event-reservation-item my-account-table-item %other_classes%" id="event_%event_id%" data-event_id="%event_id%">
                                <td class="event-type">%event_type%</td>
                                <td class="description">%description%</td>
                                <td class="facility">%facility%</td>
                                <td class="event-date">%event_date%</td>
                                <td class="event-paid-order">
                                    %if_has_value:paid_order_id%
                                    Paid
                                    %endif%
                                </td>
                                <td class="my-account-table-controls align-center">
                                    %if_has_value:allow_cancel%
                                    <span class="fad fa-ban cancel-event-reservation" title="Cancel"></span>
                                    %endif%
                                </td>
                            </tr>
							<?php
							$eventReservationElement = ob_get_clean();
						}
						$eventReservations[] = PlaceHolders::massageContent($eventReservationElement, $eventReservationSubstitutions);
					}
				}
				$returnArray['event_reservations'] = $eventReservations;
				ajaxResponse($returnArray);
				break;
			case "cancel_event_reservation":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id'], "event_id not in (select event_id from event_facility_recurrences) and " .
					"start_date >= current_date and (select count(distinct facility_id) from event_facilities where event_id = events.event_id) = 1 and event_id not in (select event_id from event_registrants)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Reservation cannot be removed. Contact customer service.";
					ajaxResponse($returnArray);
					break;
				}
                if(function_exists("_localCancelReservation")) {
                    $returnArray = _localCancelReservation(array("event_id"=>$eventId));
                    ajaxResponse($returnArray);
                }
                $GLOBALS['gPrimaryDatabase']->startTransaction();
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$eventTypeRow = getRowFromId("event_types", "event_type_id", $eventRow['event_type_id']);

				$cancelledOrderItems = array();
				$giftCardAmount = 0;
				$giftCardProductId = getFieldFromId("product_id", "products", "product_code", "GIFT_CARD");
				if (!empty($eventTypeRow['product_id']) && !empty($eventRow['order_id'])) {
					$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $eventRow['order_id'], "product_id = ? and deleted = 0", $eventTypeRow['product_id']);
					if (empty($orderItemId)) {
						$returnArray['error_message'] = "This reservation is already cancelled";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$orderItemDataTable = new DataTable("order_items");
					$orderItemDataTable->setSaveOnlyPresent(true);
					if (!$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId))) {
						$returnArray['error_message'] = "Unable to cancel reservation. Please contact customer service. #8752";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$cancelledOrderItems[] = $orderItemId;
					$orderItemRow = getRowFromId("order_items", "product_id", $eventTypeRow['product_id'], "order_id = ?", $eventRow['order_id']);
					$giftCardAmount = false;
					if (function_exists("calculateReservationRefundAmount")) {
						$giftCardAmount = calculateReservationRefundAmount($eventRow, $orderItemRow);
					}
					if ($giftCardAmount === false) {
						$giftCardAmount = ($orderItemRow['sale_price'] * $orderItemRow['quantity']) + $orderItemRow['tax_charge'];
					}
				}
				if ($giftCardAmount > 0 && empty($giftCardProductId)) {
					$returnArray['error_message'] = "Unable to cancel reservation. Please contact customer service. #6843";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				# Delete from event_facilities, event_images, and events
				executeQuery("delete from event_facilities where event_id = ?", $eventRow['event_id']);
				executeQuery("delete from event_images where event_id = ?", $eventRow['event_id']);
				$deleteSet = executeQuery("delete from events where event_id = ?", $eventRow['event_id']);
				if (!empty($deleteSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to cancel reservation. Please contact customer service. #6938";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				if ($giftCardAmount > 0) {
					$giftCard = new GiftCard(array("user_id" => $GLOBALS['gUserId'], "use_refund_prefix" => true));
					if (!$giftCard) {
						$giftCard = new GiftCard();
						$giftCardId = $giftCard->createRefundGiftCard(false, "Gift card for cancelled reservation, Order ID " . $eventRow['order_id']);
						if (empty($giftCardId)) {
							$returnArray['error_message'] = "Unable to cancel event reservation. Please contact customer service. #5982";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						if (!$giftCard->adjustBalance(true, $giftCardAmount, "Reservation cancelled", $eventRow['order_id'])) {
							$returnArray['error_message'] = "Unable to cancel event reservation. Please contact customer service. #4636";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					} else if (!$giftCard->adjustBalance(false, $giftCardAmount, "Reservation cancelled", $eventRow['order_id'])) {
						$returnArray['error_message'] = "Unable to cancel event reservation. Please contact customer service. #5671";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$giftCardNumber = $giftCard->getGiftCardNumber();
					executeQuery("insert into order_notes (order_id, user_id,content) values (?,?,?)", $eventRow['order_id'], $GLOBALS['gUserId'], "Gift card '" . $giftCardNumber . "' issued for canceled reservation");
					executeQuery("insert into order_items (order_id, product_id, description, quantity, sale_price) values (?,?,?,1,?)", $eventRow['order_id'], $giftCardProductId, 'Gift Card - ' . $giftCardNumber, $giftCardAmount);

					if (!empty($cancelledOrderItems)) {
						executeQuery("update order_items set deleted = 1 where order_id = ? and order_item_id in (" . implode(",", $cancelledOrderItems) . ")", $eventRow['order_id']);
					}

					$emailId = getFieldFromId("email_id", "emails", "email_code", "REFUND_GIFT_CARD",  "inactive = 0");
					$substitutions = $GLOBALS['gUserRow'];
					$substitutions['order_id'] = $eventRow['order_id'];
					$substitutions['amount'] = number_format($giftCardAmount, 2, ".", ",");
					$substitutions['description'] = "Gift Card for Canceled Reservation";
					$substitutions['product_code'] = "GIFT_CARD";
					$substitutions['gift_card_number'] = $giftCardNumber;
					$substitutions['gift_message'] = "";
					$subject = "Gift Card for canceled reservation";
					$body = "Your gift card number is %gift_card_number%, to which %amount% was added.";
					$copyEmailAddresses = getNotificationEmails("EVENT_REGISTRATION_CANCELLATION");
					sendEmail(array("email_id" => $emailId, "contact_id" => $GLOBALS['gUserRow']['contact_id'], "subject" => $subject,
						"body" => $body, "substitutions" => $substitutions, "email_addresses" => $GLOBALS['gUserRow']['email_address'],
						"cc_email_addresses" => $copyEmailAddresses));
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['info_message'] = "Your event reservation has been canceled.";
				ajaxResponse($returnArray);
				break;
			case "get_product_reviews":
				$productReviews = array();

				$resultSet = executeQuery("select * from product_reviews where user_id = ? and inactive = 0", $GLOBALS['gUserId']);
				if (!empty($resultSet['row_count'])) {
					while ($row = getNextRow($resultSet)) {
						$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
						if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
							$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
						}

						$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
						$productDataRow = getRowFromId("product_data", "product_id", $row['product_id']);
						$productReviewSubstitutions = array_merge($productRow, $productDataRow, $row);

						$productReviewSubstitutions['small_image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("image_type" => "small", "default_image" => $missingProductImage));
						$productReviewSubstitutions['image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("default_image" => $missingProductImage));
						$productReviewSubstitutions['requires_approval_label'] = $row['requires_approval'] ? "Yes" : "No";
						$productReviewSubstitutions['rating_1'] = $row['rating'] == 1;
						$productReviewSubstitutions['rating_2'] = $row['rating'] == 2;
						$productReviewSubstitutions['rating_3'] = $row['rating'] == 3;
						$productReviewSubstitutions['rating_4'] = $row['rating'] == 4;
						$productReviewSubstitutions['rating_5'] = $row['rating'] == 5;

						$productReviewElement = $this->getPageTextChunk("MY_ACCOUNT_PRODUCT_REVIEW");
						if (empty($productReviewElement)) {
							$productReviewElement = $this->getFragment("MY_ACCOUNT_PRODUCT_REVIEW");
						}
						if (empty($productReviewElement)) {
							ob_start();
							?>
                            <tr class="product-review-item my-account-table-item %other_classes%" id="product_review_%product_review_id%" data-product_id="%product_id%">
                                <td class="date-created">%date_created%</td>
                                <td class="clickable align-center">
                                    <img src="%small_image_url%"><br/>
                                    %description%
                                </td>
                                <td class="rating">
                                    %if_has_value:rating_1%
                                    <span class="fas fa-star"></span><span class="far fa-star"></span><span class="far fa-star"></span><span class="far fa-star"></span><span class="far fa-star"></span>
                                    %endif%
                                    %if_has_value:rating_2%
                                    <span class="fas fa-star"></span><span class="fas fa-star"></span><span class="far fa-star"></span><span class="far fa-star"></span><span class="far fa-star"></span>
                                    %endif%
                                    %if_has_value:rating_3%
                                    <span class="fas fa-star"></span><span class="fas fa-star"></span><span class="fas fa-star"></span><span class="far fa-star"></span><span class="far fa-star"></span>
                                    %endif%
                                    %if_has_value:rating_4%
                                    <span class="fas fa-star"></span><span class="fas fa-star"></span><span class="fas fa-star"></span><span class="fas fa-star"></span><span class="far fa-star"></span>
                                    %endif%
                                    %if_has_value:rating_5%
                                    <span class="fas fa-star"></span><span class="fas fa-star"></span><span class="fas fa-star"></span><span class="fas fa-star"></span><span class="fas fa-star"></span>
                                    %endif%
                                </td>
                                <td class="review">
                                    <strong>%title_text%</strong>
                                    <p>%content%</p>
                                </td>
                                <td class="pending-approval">%requires_approval_label%</td>
                            </tr>
							<?php
							$productReviewElement = ob_get_clean();
						}
						$productReviews[] = PlaceHolders::massageContent($productReviewElement, $productReviewSubstitutions);
					}
				}
				$returnArray['product_reviews'] = $productReviews;
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            let availableContactIdentifierTypes = [];
            let allContactIdentifierTypes = [];
            let selectedInvoicesTotal = 0;

            function showContactInfoOverview() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info", function (returnArray) {
                    Object.keys(returnArray['contact_info']).forEach(fieldName => {
                        const fieldValue = returnArray['contact_info'][fieldName];
                        $("#" + fieldName).val(fieldValue);
                        $(`[data-contact-info-field="${ fieldName }"]`).html(fieldValue);
                    });
                    $("#country_id").trigger("change");
                    $(".my-account-content").addClass("hidden");
                    $("#contact_info_overview_wrapper").removeClass("hidden");

                    if (typeof afterGetContactInfo == "function") {
                        afterGetContactInfo(returnArray);
                    }
                });
            }

            function showPaymentMethods() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_payment_methods", function (returnArray) {
                    const accountsTable = $("#accounts_table tbody");
                    accountsTable.empty();

                    if (!empty(returnArray['accounts'])) {
                        returnArray['accounts'].forEach(account => {
                            accountsTable.append(`<tr class="account-item my-account-table-item" data-account_id="${ account['account_id'] }">
                                <td class="account-label">${ account['account_label'] }</td>
                                <td class="account-type">${ account['account_type'] }</td>
                                <td class="account-expiration">${ account['account_expiration'] }</td>
                                <td>${ account['account_notes'] }</td>
                                <td class="my-account-table-controls align-center">
                                    ${ empty(account['recurring_payment_id']) ? "<span class='fad fa-trash delete-payment-method' title='Delete'></span>" : "" }
                                </td>
                            </tr>`)
                        });
                    } else {
                        accountsTable.append(`<tr class="account-item"><td colspan="5">You have no payment methods configured with your account.</td></tr>`);
                    }
                    $(".my-account-content").addClass("hidden");
                    $("#payments_wrapper").removeClass("hidden");

                    if (typeof afterGetPaymentMethods == "function") {
                        afterGetPaymentMethods(returnArray);
                    }
                });
            }

            function showIdentifiers() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_identifiers", function (returnArray) {
                    const identifiersTable = $("#identifiers_table tbody");
                    identifiersTable.empty();
                    allContactIdentifierTypes = returnArray['all_contact_identifier_types'];

                    if (!empty(returnArray['identifiers'])) {
                        returnArray['identifiers'].forEach(identifier => {
                            identifiersTable.append(`<tr class="identifier-item my-account-table-item" data-image_id="${ identifier['image_id'] }" data-contact_identifier_type_id="${ identifier['contact_identifier_type_id'] }" data-contact_identifier_id="${ identifier['contact_identifier_id'] }">
                                <td class="identifier-description">${ identifier['description'] }</td>
                                <td class="identifier-value">${ identifier['identifier_value'] }</td>
                                <td class="my-account-table-controls align-center">
                                    <span class='fad fa-pencil edit-identifier' title='Edit'></span>
                                    ${ empty(identifier['required']) ? "<span class='fad fa-trash delete-identifier' title='Delete'></span>" : "" }
                                </td>
                            </tr>`);
                        });
                    } else {
                        identifiersTable.append(`<tr class="identifier-item my-account-table-item"><td colspan="3">You have no identifiers configured with your account.</td></tr>`);
                    }

                    if (!empty(returnArray['available_contact_identifier_types'])) {
                        availableContactIdentifierTypes = returnArray['available_contact_identifier_types'];
                        $("#add_identifier").removeClass("hidden");
                    } else {
                        $("#add_identifier").addClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#identifiers_wrapper").removeClass("hidden");

                    if (typeof afterGetIdentifiers == "function") {
                        afterGetIdentifiers(returnArray);
                    }
                });
            }

            function showEventReservations() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_event_reservations", function (returnArray) {
                    if (!empty(returnArray['event_reservations'])) {
                        const eventReservationsElement = $("#event_reservations_content tbody");
                        eventReservationsElement.empty();
                        returnArray['event_reservations'].forEach(eventReservation => eventReservationsElement.append(eventReservation));

                        $("#event_reservations_content").removeClass("hidden");
                        $("#no_event_reservations_message").addClass("hidden");
                    } else {
                        $("#event_reservations_content").addClass("hidden");
                        $("#no_event_reservations_message").removeClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#event_reservations_wrapper").removeClass("hidden");

                    if (typeof afterGetEventReservations == "function") {
                        afterGetEventReservations(returnArray);
                    }
                });
            }

            function calculateAmount() {
                let totalAmount = 0;
                const fullAmount = $("#full_amount").prop("checked");
                if (fullAmount) {
                    $(".custom-amount").addClass("hidden");
                    $("#totals_title").attr("colspan", "5");
                    $(".pay-amount").prop("readonly", true);
                    $("#invoice_list").find(".invoice-amount").each(function () {
                        if ($(this).closest("tr").find(".pay-now").prop("checked")) {
                            let invoiceTotal = parseFloat($(this).html().replace(/,/g, ""));
                            totalAmount = Round(totalAmount + invoiceTotal, 2);
                            $(this).closest("tr").find(".pay-amount").val(invoiceTotal);
                        } else {
                            $(this).closest("tr").find(".pay-amount").val("");
                        }
                    });
                } else {
                    $(".custom-amount").removeClass("hidden");
                    $("#totals_title").attr("colspan", "6");
                    $(".pay-amount").prop("readonly", false);
                    $("#invoice_list").find(".pay-amount").each(function () {
                        if ($(this).closest("tr").find(".pay-now").prop("checked")) {
                            if (empty($(this).val())) {
                                let invoiceTotal = parseFloat($(this).closest("tr").find(".invoice-amount").html().replace(/,/g, ""));
                                $(this).val(invoiceTotal);
                            }
                            totalAmount = Round(totalAmount + parseFloat($(this).val().replace(/,/g, "")), 2);
                        } else {
                            $(this).val("");
                        }
                    });
                }
                selectedInvoicesTotal = totalAmount;
                $("#amount").val(RoundFixed(totalAmount, 2));
                let feeAmount = 0;
                if (empty($("#account_id").val())) {
                    const flatRate = $("#payment_method_id").find("option:selected").data("flat_rate");
                    if (!empty(flatRate)) {
                        feeAmount += flatRate;
                    }
                    const feePercent = $("#payment_method_id").find("option:selected").data("fee_percent");
                    if (!empty(feePercent)) {
                        feeAmount += totalAmount * feePercent / 100;
                    }
                } else {
                    const flatRate = $("#account_id").find("option:selected").data("flat_rate");
                    if (!empty(flatRate)) {
                        feeAmount += flatRate;
                    }
                    const feePercent = $("#account_id").find("option:selected").data("fee_percent");
                    if (!empty(feePercent)) {
                        feeAmount += totalAmount * feePercent / 100;
                    }
                }
                $("#fee_amount").val(RoundFixed(feeAmount, 2));
                $("#total_charge").val(RoundFixed(feeAmount + totalAmount, 2));
                if (empty(feeAmount)) {
                    $("#_fee_amount_row").addClass("hidden");
                    $("#_total_charge_row").addClass("hidden");
                } else {
                    $("#_fee_amount_row").removeClass("hidden");
                    $("#_total_charge_row").removeClass("hidden");
                }
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		$helpDeskLink = $this->getPageTextChunk("help_desk_link");
		?>
        <script>
			<?php if (!empty($helpDeskLink)) { ?>
            $(document).on("click", ".help-desk-ticket", function() {
                window.open("<?= $helpDeskLink ?>?id=" + $(this).data("help_desk_entry_id"));
                return false;
            });
			<?php } ?>
            $(document).on("click", "#menu_header p", function () {
                $("#my_account_wrapper").toggleClass("show-menu");
            });

            $(document).on("click", "[class$='-link']", function () {
                $("#my_account_wrapper").removeClass("show-menu");
                window.scrollTo(0, 0);
            });

            $(document).on("click", "#menu_header", function () {
                $(".my-account-content").addClass("hidden");
                $("#dashboard_wrapper").removeClass("hidden");
            });

            $(document).on("click", ".contact-info-link", function () {
                showContactInfoOverview();
            });

            $(document).on("click", ".edit-contact-info", function () {
                const section = $(this).data("contact-section");
                $(".contact-section").addClass("hidden");
                $(`[data-contact-section="${ section }"]`).removeClass("hidden");

                $("#current_password, #new_password, #password_again")
                    .val("").trigger("change");

                $(".my-account-content").addClass("hidden");
                $("#contact_info_wrapper").removeClass("hidden");
            });

            $(document).on("click", ".wishlists-link", function () {
                $("#wish_list_items_wrapper").empty();
                getWishListItems();

                $(".my-account-content").addClass("hidden");
                $("#wishlists_wrapper").removeClass("hidden");
            });

            $(document).on("click", ".recently-viewed-products-link", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_recently_viewed_products", function (returnArray) {
                    if (!empty(returnArray['products'])) {
                        const productsElement = $("#recently_viewed_products_content tbody");
                        productsElement.empty();
                        returnArray['products'].forEach(product => productsElement.append(product));

                        $("#recently_viewed_products_content").removeClass("hidden");
                        $("#no_recently_viewed_products_message").addClass("hidden");
                    } else {
                        $("#recently_viewed_products_content").addClass("hidden");
                        $("#no_recently_viewed_products_message").removeClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#recently_viewed_products_wrapper").removeClass("hidden");

                    if (typeof afterGetRecentlyViewedProducts == "function") {
                        afterGetRecentlyViewedProducts(returnArray);
                    }
                });
            });

            $(document).on("click", ".orders-link", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_orders", function (returnArray) {
                    if (!empty(returnArray['orders'])) {
                        const ordersElement = $("#orders_content tbody");
                        ordersElement.empty();
                        returnArray['orders'].forEach(order => ordersElement.append(order));

                        $("#orders_content").removeClass("hidden");
                        $("#no_orders_message").addClass("hidden");
                    } else {
                        $("#orders_content").addClass("hidden");
                        $("#no_orders_message").removeClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#orders_wrapper").removeClass("hidden");

                    if (typeof afterGetOrders == "function") {
                        afterGetOrders(returnArray);
                    }
                });
            });

            $(document).on("click", ".payments-link", function () {
                showPaymentMethods();
            });

            $(document).on("click", ".identifiers-link", function () {
                showIdentifiers();
            });

            $(document).on("click", ".ffl-dealer-link", function () {
                if ($("#ffl_dealers_wrapper").length > 0 && !empty($("#postal_code").val())) {
                    getFFLDealers();
                }
                $(".my-account-content").addClass("hidden");
                $("#ffl_dealer_wrapper").removeClass("hidden");
            });

            $(document).on("click", ".tickets-link", function () {
                $(".my-account-content").addClass("hidden");
                $("#ticket_wrapper").removeClass("hidden");
            });

            $(document).on("click", ".logout-link", function () {
                goToLink(null, "logout.php");
            });

            // Contact Info
            $(document).on("click", ".cancel-edit-contact-info", function () {
                window.scrollTo(0, 0);
                $(".my-account-content").addClass("hidden");
                $("#contact_info_overview_wrapper").removeClass("hidden");
            });
            $(document).on("change", ".mailing-list-item input", function () {
                const optIn = $(this).prop("checked") ? 1 : 0;
                const mailingListId = $(this).attr("id").substr("mailing_list_id_".length);
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_mailing_list&mailing_list_id=" + mailingListId + "&opt_in=" + optIn);
            });
            $(document).on("change", "#country_id", function () {
                if ($("#country_id").val() === "1000") {
                    $("#_state_row").addClass("hidden");
                    $("#_state_select_row").removeClass("hidden");
                } else {
                    $("#_state_row").removeClass("hidden");
                    $("#_state_select_row").addClass("hidden");
                }
            });
            $(document).on("change", "#state_select", function () {
                $("#state").val($(this).val());
            });

            // Wishlists
            $(document).on("click", ".wish-list-item .clickable", function () {
                const productId = $(this).closest(".wish-list-item").data("product_id");
                if (!empty(productId)) {
                    document.location = "/product-details?id=" + productId;
                }
                return false;
            });
            $(document).on("click", ".wish-list-item .notify-when-in-stock", function () {
                const productId = $(this).closest("tr").data("product_id");
                setWishListItemNotify(productId, $(this).prop("checked"));
            });
            $(document).on("click", ".wish-list-item .remove-item", function () {
                removeProductFromWishList($(this).data("product_id"));
                $(this).closest("tr").remove();
            });

            // Orders
            $(document).on("click", "#orders_wrapper .write-review", function () {
                window.open("/product-review?product_id=" + $(this).data("product_id"));
                return false;
            });
            $(document).on("click", "#orders_wrapper .buy-again", function () {
                addProductToShoppingCart($(this).data("product_id"));
                $(this).removeClass("buy-again").html("Item In Cart");
                return false;
            });
            $(document).on("click", "#orders_wrapper .download-product", function () {
                document.location = "/download.php?force_download=true&id=" + $(this).data("file_id");
                return false;
            });
            $(document).on("click", "#orders_wrapper .print-receipt", function () {
                window.open("/order-receipt?order_id=" + $(this).data("order_id"));
                return false;
            });
            $(document).on("click", "#orders_wrapper .contact-support", function () {
                window.open("/contact-support?description=Regarding%20Order%20Number%20" + $(this).data("order_id"));
                return false;
            });
            $(document).on("click", "#orders_wrapper .order-document-upload", function () {
                const orderId = $(this).data("order_id");
                if ($(this).hasClass("submit-form")) {
                    const orderUploadForm = $("#_upload_form_" + orderId);
                    const postIFrame = $("#_post_iframe");
                    if (orderUploadForm.validationEngine("validate")) {
                        postIFrame.html("");
                        $(".order-document-upload").addClass("hidden");
                        $("body").addClass("waiting-for-ajax");
                        postIFrame.off("load");
                        orderUploadForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=upload_document").attr("method", "POST").attr("target", "post_iframe").submit();
                        postIFrame.on("load", function () {
                            if (postTimeout != null) {
                                clearTimeout(postTimeout);
                            }
                            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                            const returnText = $(this).contents().find("body").html();
                            const returnArray = processReturn(returnText);
                            if (returnArray === false) {
                                return;
                            }
                            $("#order_document_" + orderId).remove();
                            $(".order-document-upload").removeClass("hidden");
                        });
                        let postTimeout = setTimeout(function () {
                            postTimeout = null;
                            $("#_post_iframe").off("load");
                            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                            displayErrorMessage("Server not responding");
                        }, 60000);
                    }
                } else {
                    $("#_update_document_" + orderId).removeClass("hidden");
                    $(this).addClass("submit-form").html("Submit Document");
                }
                return false;
            });

            // Payments
            $(document).on("change", "#payment_method_id", function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            });
            $(document).on("click", "#add_payment_method", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_payment_method_form", function (returnArray) {
                    if (!empty(returnArray['form_content'])) {
                        // Clear up invoice payment form to avoid element ID conflicts
                        $("#invoice_payments_wrapper").empty();

                        $(".my-account-content").addClass("hidden");
                        $("#new_payment_wrapper").html(returnArray['form_content']);
                        $("#new_payment_wrapper").removeClass("hidden");

                        if (typeof afterGetPaymentMethodForm == "function") {
                            afterGetPaymentMethodForm(returnArray);
                        }
                    }
                });
            });
            $(document).on("click", ".delete-payment-method", function () {
                const accountId = $(this).closest("tr").data("account_id");
                $("#make_account_inactive_dialog").dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Make Account Inactive',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=make_account_inactive&account_id=" + accountId, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    showPaymentMethods();
                                }
                            });
                            $("#make_account_inactive_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#make_account_inactive_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", "#same_address", function () {
                const billingAddress = $("#_billing_address");
                if ($(this).prop("checked")) {
                    billingAddress.addClass("hidden");
                    billingAddress.find("input,select").val("");
                } else {
                    billingAddress.removeClass("hidden");
                }
            });
            $(document).on("click", ".cancel-new-payment", function () {
                window.scrollTo(0, 0);
                $(".my-account-content").addClass("hidden");
                $("#payments_wrapper").removeClass("hidden");
            });

            // Identifiers
            $(document).on("click", "#add_identifier", function () {
                const identifierType = $("#contact_identifier_type_id");
                $("#image_exists").html("");
                identifierType.prop("disabled", false);
                if (identifierType.find("option").length === 0) {
                    identifierType.append($("<option>", { value: "", text: "[Select]" }));
                    allContactIdentifierTypes.forEach(contactIdentifierType => {
                        identifierType.append($("<option>", { value: contactIdentifierType['contact_identifier_type_id'], text: contactIdentifierType['description'] }).data("allow_image", contactIdentifierType['allow_image']));
                    });
                }
                identifierType.find("option").each(function () {
                    $(this).unwrap("span");
                });
                identifierType.find("option").each(function () {
                    const thisId = $(this).val();
                    if (empty(thisId)) {
                        return true;
                    }
                    let foundThisId = false;
                    for (var i in availableContactIdentifierTypes) {
                        if (thisId == availableContactIdentifierTypes[i]['contact_identifier_type_id']) {
                            foundThisId = true;
                            return false;
                        }
                    }
                    if (!foundThisId) {
                        $(this).wrap("<span>");
                    }
                });
                $("#_new_identifier_form")[0].reset();
                $(".my-account-content").addClass("hidden");
                $("#new_identifier_wrapper").removeClass("hidden");
            });
            $(document).on("change", "#contact_identifier_type_id", function () {
                $("#_image_id_file_row").addClass("hidden");
                if (!empty($(this).find("option:selected").data("allow_image"))) {
                    $("#_image_id_file_row").removeClass("hidden");
                }
            });
            $(document).on("click", ".edit-identifier", function () {
                const identifierType = $("#contact_identifier_type_id");
                const contactIdentifierRow = $(this).closest("tr");
                identifierType.find("option").each(function () {
                    $(this).unwrap("span");
                });
                if (identifierType.find("option").length === 0) {
                    identifierType.append($("<option>", { value: "", text: "[Select]" }));
                    allContactIdentifierTypes.forEach(contactIdentifierType => {
                        identifierType.append($("<option>", { value: contactIdentifierType['contact_identifier_type_id'], text: contactIdentifierType['description'] }).data("allow_image", contactIdentifierType['allow_image']));
                    });
                }
                if (empty(contactIdentifierRow.data("image_id"))) {
                    $("#image_exists").html("");
                } else {
                    $("#image_exists").html("Image previously uploaded");
                }
                identifierType.val(contactIdentifierRow.data("contact_identifier_type_id")).trigger("change");
                identifierType.prop("disabled", true);
                $("#identifier_value").val(contactIdentifierRow.find(".identifier-value").text());

                $(".my-account-content").addClass("hidden");
                $("#new_identifier_wrapper").removeClass("hidden");
            });
            $(document).on("click", ".delete-identifier", function () {
                const contactIdentifierId = $(this).closest("tr").data("contact_identifier_id");
                $("#delete_identifier_dialog").dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Delete identifier',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_identifier&contact_identifier_id=" + contactIdentifierId, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    showIdentifiers();
                                }
                            });
                            $("#delete_identifier_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#delete_identifier_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", ".cancel-new-identifier", function () {
                window.scrollTo(0, 0);
                $(".my-account-content").addClass("hidden");
                $("#identifiers_wrapper").removeClass("hidden");
            });

            // Education Courses
            $(document).on("click", ".courses-link", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_courses", function (returnArray) {
                    if (!empty(returnArray['courses'])) {
                        const coursesElement = $("#courses_content tbody");
                        coursesElement.empty();
                        returnArray['courses'].forEach(course => coursesElement.append(course));

                        $("#courses_content").removeClass("hidden");
                        $("#no_courses_message").addClass("hidden");
                    } else {
                        $("#courses_content").addClass("hidden");
                        $("#no_courses_message").removeClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#courses_wrapper").removeClass("hidden");

                    if (typeof afterGetCourses == "function") {
                        afterGetCourses(returnArray);
                    }
                });
            });

            // FFL Dealer
            $(document).on("change", "#ffl_radius", function () {
                getFFLDealers();
            });
            $(document).on("click", ".ffl-dealer", function () {
                const fflId = $(this).data("federal_firearms_licensee_id");
                $("#federal_firearms_licensee_id").val(fflId).trigger("change");

                const fflBusinessName = $(this).find(".ffl-choice-business-name").text();
                const fflAddress = $(this).find(".ffl-choice-address").text();
                const fflCity = $(this).find(".ffl-choice-city").text();
                const fflDisplayName = fflBusinessName + ", " + fflAddress + ", " + fflCity;
                $("#selected_ffl_dealer").html(fflDisplayName);
            });
            $(document).on("keyup", "#ffl_dealer_filter", function () {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("ul#ffl_dealers li").removeClass("hidden");
                } else {
                    $("ul#ffl_dealers li").each(function () {
                        const description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });

            // Invoices
            $(document).on("click", ".invoice-payments-link", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_invoice_payment_form", function (returnArray) {
                    if (!empty(returnArray['form_content'])) {
                        // Clear up new payment form to avoid element ID conflicts
                        $("#new_payment_wrapper").empty();

                        $(".my-account-content").addClass("hidden");
                        $("#invoice_payments_wrapper").html(returnArray['form_content']);
                        $("#invoice_payments_wrapper").removeClass("hidden");

                        $("#payment_method_id").trigger("change");
                        $("#billing_country_id").trigger("change");
                        calculateAmount();

                        if (typeof afterGetInvoicePaymentForm == "function") {
                            afterGetInvoicePaymentForm(returnArray);
                        }
                    }
                });
            });
            $(document).on("click", ".invoice-history-link", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_invoices", function (returnArray) {
                    if (!empty(returnArray['invoices'])) {
                        const invoicesElement = $("#invoices_content tbody");
                        invoicesElement.empty();
                        returnArray['invoices'].forEach(invoice => invoicesElement.append(invoice));

                        $("#invoices_total_balance").html(RoundFixed(returnArray['total_balance'], 2));
                        if (returnArray['total_balance'] > 0) {
                            $("#invoice_payments_link").removeClass("hidden");
                        } else {
                            $("#invoice_payments_link").addClass("hidden");
                        }

                        $("#invoices_content").removeClass("hidden");
                        $("#no_invoices_message").addClass("hidden");
                    } else {
                        $("#invoices_content").addClass("hidden");
                        $("#no_invoices_message").removeClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#invoices_wrapper").removeClass("hidden");

                    if (typeof afterGetInvoices == "function") {
                        afterGetInvoices(returnArray);
                    }
                });
            });
            $(document).on("click", ".invoice-details", function () {
                const invoiceId = $(this).closest("tr").data("invoice_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_invoice_details&invoice_id=" + invoiceId, function (returnArray) {
                    if ("invoice_details" in returnArray) {
                        $("#invoice_details_dialog").html(returnArray['invoice_details']);
                        $("#invoice_details_dialog").dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                            width: 800,
                            title: 'Invoice Details',
                            buttons: {
                                Print: function (event) {
                                    document.location = "/printinvoice.php?invoice_id=" + $("#_invoice_header").data("invoice_id");
                                },
                                Close: function (event) {
                                    $("#invoice_details_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $(document).on("click", "#submit_form", function () {
                if (parseFloat($("#amount").val()) <= 0) {
                    displayErrorMessage("Select one or more invoices to pay");
                    return;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#submit_paragraph").addClass("hidden");
                    $("#processing_payment").removeClass("hidden");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_payment&contact_id=<?= $GLOBALS['gUserRow']['contact_id'] ?>", $("#_edit_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                            return;
                        }
                        if ("response" in returnArray) {
                            $("#invoices_payment_content").html(returnArray['response']);
                        } else {
                            $("#submit_paragraph").removeClass("hidden");
                            $("#processing_payment").addClass("hidden");
                        }
                        if (typeof afterPayInvoice == "function") {
                            afterPayInvoice(returnArray);
                        }
                    });
                }
                return false;
            });
            $(document).on("click", "#billing_country_id", function () {
                if ($(this).val() === "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            });
            $(document).on("click", "#billing_state_select", function () {
                $("#billing_state").val($(this).val());
            });
            $(document).on("change", "#account_id", function () {
                if ($(this).val() !== "") {
                    $("#_new_account").hide();
                } else {
                    $("#_new_account").show();
                }
                calculateAmount();
            });
            $(document).on("change", "#payment_method_id", function () {
                $(".payment-method-fields").hide();
                if ($(this).val() !== "") {
                    const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
                calculateAmount();
            });
            $(document).on("click", ".pay-now", function () {
                calculateAmount();
            });
            $(document).on("click", "#select_all", function () {
                $(".pay-now").prop("checked", true);
                calculateAmount();
                return false;
            });
            $(document).on("change", ".pay-amount", function () {
                if (empty($(this).val())) {
                    $(this).closest("tr").find(".pay-now").prop("checked", false);
                } else {
                    $(this).closest("tr").find(".pay-now").prop("checked", true);
                }
                calculateAmount();
            });
            $(document).on("click", "#full_amount", function () {
                calculateAmount();
            });

            // Product Reviews
            $(document).on("click", ".product-reviews-link", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_reviews", function (returnArray) {
                    if (!empty(returnArray['product_reviews'])) {
                        const productReviewsElement = $("#product_reviews_content tbody");
                        productReviewsElement.empty();
                        returnArray['product_reviews'].forEach(productReview => productReviewsElement.append(productReview));

                        $("#product_reviews_content").removeClass("hidden");
                        $("#no_product_reviews_message").addClass("hidden");
                    } else {
                        $("#product_reviews_content").addClass("hidden");
                        $("#no_product_reviews_message").removeClass("hidden");
                    }

                    $(".my-account-content").addClass("hidden");
                    $("#product_reviews_wrapper").removeClass("hidden");

                    if (typeof afterGetProductReviews == "function") {
                        afterGetProductReviews(returnArray);
                    }
                });
            });

            // Event reservations
            $(document).on("click", ".event-reservations-link", function () {
                showEventReservations()
            });
            $(document).on("click", ".cancel-event-reservation", function () {
                const eventId = $(this).closest("tr").data("event_id");
                $("#cancel_reservation_dialog").dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Cancel Reservation',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=cancel_event_reservation&event_id=" + eventId, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    showEventReservations();
                                }
                            });
                            $("#cancel_reservation_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#cancel_reservation_dialog").dialog('close');
                        }
                    }
                });
            });

            $(document).on("click", ".manage-subscriptions-link", function () {
                goToLink(null, "/customer-subscription-manager");
            });

            $(document).on("click", ".save-changes", function () {
                const iframeElement = $("#_post_iframe");
                const bodyElement = $("body");
                const buttonElement = $(this);
                const formElement = $(this).parents("form");
                $("#contact_identifier_type_id").prop("disabled", false);

                if (formElement.validationEngine("validate")) {
                    buttonElement.prop("disabled", true);
                    bodyElement.addClass("waiting-for-ajax");

                    formElement.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + formElement.data("url-action"))
                        .attr("target", "post_iframe")
                        .attr("method", "POST")
                        .submit();

                    iframeElement.off("load");
                    iframeElement.on("load", function () {
                        bodyElement.removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if (!("error_message" in returnArray)) {
                            window.scrollTo(0, 0);
                            bodyElement.data("just_saved", "true");

                            const afterSaveChangeFunction = formElement.data("after-save-function");
                            if (typeof window[afterSaveChangeFunction] == "function") {
                                window[afterSaveChangeFunction](returnArray);
                            }
                        }
                        buttonElement.prop("disabled", false);
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function mainContent() {
		$resultSet = executeQuery("select count(*) from contact_identifier_types where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
		if ($countRow = getNextRow($resultSet)) {
			$contactIdentifierTypesCount = $countRow['count(*)'];
		}
		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
		$ticketCount = 0;
		$resultSet = executeQuery("select count(*) from help_desk_entries where contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
		if ($row = getNextRow($resultSet)) {
			$ticketCount = $row['count(*)'];
		}
		$resultSet = executeQuery("select count(*) from product_reviews where user_id = ? and inactive = 0", $GLOBALS['gUserId']);
		if ($row = getNextRow($resultSet)) {
			$productReviewsCount = $row['count(*)'];
		}
		$resultSet = executeQuery("select count(*) from courses where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")
			. " and (product_id is not null or course_id in (select course_id from course_attendances where user_id = ?))", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
		if ($row = getNextRow($resultSet)) {
			$coursesCount = $row['count(*)'];
		}
		$resultSet = executeQuery("select count(*) from events where contact_id = ? and client_id = ? and event_id not in (select event_id from event_facility_recurrences) and " .
			"(select count(distinct facility_id) from event_facilities where event_id = events.event_id) = 1 and event_id not in (select event_id from event_registrants)", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$eventReservationsCount = $row['count(*)'];
		}
		$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "contact_id",
			$GLOBALS['gUserRow']['contact_id'], "inactive = 0 and subscription_id in (select subscription_id from subscriptions where inactive = 0 and internal_use_only = 0)");

		$resultSet = executeQuery("select count(*) from invoices where contact_id = ? and client_id = ? and internal_use_only = 0 and inactive = 0",
			$GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$invoicesCount = $row['count(*)'];
		}
		?>
        <div id="my_account_wrapper">
            <div id="menu_wrapper">
                <div id="menu_header">
                    <span class="dashboard-link" href="#">My Account</span>
                    <p><span data-contact-info-field="full_name"><?= $GLOBALS['gUserRow']['display_name'] ?></span></p>
                </div>

                <div id="menu_contents">
                    <a class="contact-info-link" href="#">Contact Info</a>
                    <a class="wishlists-link" href="#">Wishlist</a>
					<?php if (!empty($eventReservationsCount)) { ?>
                        <a class="event-reservations-link" href="#">My Reservations</a>
					<?php } ?>
					<?php if (!empty($contactSubscriptionId)) { ?>
                        <a class="manage-subscriptions-link" href="#">Manage subscriptions</a>
					<?php } ?>
                    <a class="orders-link" href="#">My Orders</a>
                    <a class="payments-link" href="#">Payments</a>
					<?php if (!empty($contactIdentifierTypesCount)) { ?>
                        <a class="identifiers-link" href="#">Identifiers</a>
					<?php } ?>
					<?php if (!empty($coursesCount)) { ?>
                        <a class="courses-link" href="#">Education Courses</a>
					<?php } ?>
					<?php if (!empty($fflRequiredProductTagId)) { ?>
                        <a class="ffl-dealer-link" href="#">FFL Dealer</a>
					<?php } ?>
					<?php if (!empty($invoicesCount)) { ?>
                        <a class="invoice-payments-link" href="#">Pay Invoices</a>
                        <a class="invoice-history-link" href="#">Invoice History</a>
					<?php } ?>
					<?php if ($ticketCount > 0) { ?>
                        <a class="tickets-link" href="#">Help Tickets</a>
					<?php } ?>
                    <a class="recently-viewed-products-link" href="#">Recently Viewed Products</a>
					<?php if (!empty($productReviewsCount)) { ?>
                        <a class="product-reviews-link" href="#">Product Reviews</a>
					<?php } ?>
                    <button class="logout-link">Logout</button>
                </div>
            </div>

            <div id="content_wrapper">
                <p class="error-message"></p>
				<?= $this->iPageData['content'] ?>

                <div id="dashboard_wrapper" class="my-account-content">
                    <h1>Dashboard</h1>
                    <div>
                        <div class="contact-info-link">
                            <i class="fad fa-circle-user" aria-hidden="true"></i>
                            <h3>Contact</h3>
                        </div>
                        <div class="wishlists-link">
                            <i class="fad fa-star" aria-hidden="true"></i>
                            <h3>Wishlist</h3>
                        </div>

						<?php if (!empty($eventReservationsCount)) { ?>
                            <div class="event-reservations-link">
                                <i class="fad fa-calendar-clock" aria-hidden="true"></i>
                                <h3>Reservations</h3>
                            </div>
						<?php } ?>

						<?php if (!empty($contactSubscriptionId)) { ?>
                            <div class="manage-subscriptions-link">
                                <i class="fad fa-repeat" aria-hidden="true"></i>
                                <h3>Subscriptions</h3>
                            </div>
						<?php } ?>

                        <div class="orders-link">
                            <i class="fad fa-cart-shopping" aria-hidden="true"></i>
                            <h3>Orders</h3>
                        </div>
                        <div class="payments-link">
                            <i class="fad fa-credit-card" aria-hidden="true"></i>
                            <h3>Payments</h3>
                        </div>

						<?php if (!empty($contactIdentifierTypesCount)) { ?>
                            <div class="identifiers-link">
                                <i class="fad fa-id-card" aria-hidden="true"></i>
                                <h3>Identifiers</h3>
                            </div>
						<?php } ?>

						<?php if (!empty($coursesCount)) { ?>
                            <div class="courses-link">
                                <i class="fad fa-person-chalkboard" aria-hidden="true"></i>
                                <h3>Education Courses</h3>
                            </div>
						<?php } ?>

						<?php if (!empty($fflRequiredProductTagId)) { ?>
                            <div class="ffl-dealer-link">
                                <i class="fad fa-gun" aria-hidden="true"></i>
                                <h3>FFL Dealer</h3>
                            </div>
						<?php } ?>

						<?php if (!empty($invoicesCount)) { ?>
                            <div class="invoice-payments-link">
                                <i class="fa fa-file-invoice-dollar" aria-hidden="true"></i>
                                <h3>Pay Invoices</h3>
                            </div>
                            <div class="invoice-history-link">
                                <i class="fa fa-file-invoice" aria-hidden="true"></i>
                                <h3>Invoice History</h3>
                            </div>
						<?php } ?>

						<?php if ($ticketCount > 0) { ?>
                            <div class="tickets-link">
                                <i class="fad fa-ticket" aria-hidden="true"></i>
                                <h3>Help Tickets</h3>
                            </div>
						<?php } ?>

                        <div class="recently-viewed-products-link">
                            <i class="fad fa-store" aria-hidden="true"></i>
                            <h3>Recently Viewed Products</h3>
                        </div>

						<?php if (!empty($productReviewsCount)) { ?>
                            <div class="product-reviews-link">
                                <i class="fad fa-comment-lines" aria-hidden="true"></i>
                                <h3>Product Reviews</h3>
                            </div>
						<?php } ?>
                    </div>
                </div>

                <div id="contact_info_overview_wrapper" class="my-account-content hidden">
                    <div>
                        <h2>Contact Info<span class="edit-contact-info" data-contact-section="contact-info">Edit</span></h2>
                        <p data-contact-info-field="full_name"></p>
                        <p data-contact-info-field="email_address"></p>
                        <p data-contact-info-field="phone_number"></p>
                        <p data-contact-info-field="cell_phone_number"></p>
                    </div>

                    <div>
                        <h2>Primary Address<span class="edit-contact-info" data-contact-section="primary-address">Edit</span></h2>
                        <p>
                            <span data-contact-info-field="address_1"></span>
                            <span data-contact-info-field="address_2"></span>
                        </p>
                        <p data-contact-info-field="address_details"></p>
                    </div>

                    <div>
                        <h2>Account Information<span class="edit-contact-info" data-contact-section="account-information">Edit</span></h2>
                        <p>Username: <span data-contact-info-field="user_name"></span></p>
                        <span class="edit-contact-info" data-contact-section="account-information">Change password</span>
                    </div>

					<?php
					$resultSet = executeQuery("select * from mailing_lists where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order, description", $GLOBALS['gClientId']);
					if ($resultSet['row_count'] > 0) {
						?>
                        <div>
                            <h2>Opt-In Mailing Lists</h2>
							<?php
							while ($row = getNextRow($resultSet)) {
								$optedIn = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "contact_id", $GLOBALS['gUserRow']['contact_id'],
									"mailing_list_id = ? and date_opted_out is null", $row['mailing_list_id']);
								?>
                                <div id="_mailing_list_id_<?= $row['mailing_list_id'] ?>_row" class="mailing-list-item">
                                    <input type="checkbox" id="mailing_list_id_<?= $row['mailing_list_id'] ?>" name="mailing_list_id_<?= $row['mailing_list_id'] ?>" value="1" <?= (empty($optedIn) ? "" : "checked='checked'") ?>>
                                    <label for="mailing_list_id_<?= $row['mailing_list_id'] ?>"><?= htmlText($row['description']) ?></label>
                                </div>
								<?php
							}
							?>
                        </div>
						<?php
					}
					?>
                </div>

                <div id="contact_info_wrapper" class="my-account-content hidden">
                    <a href="#" id="return_to_contact_info" class="cancel-edit-contact-info return-link">
                        <i class="fad fa-arrow-left" aria-hidden="true"></i>
                        Return to contact
                    </a>

                    <form id="_contact_info_form" data-url-action="update_contact_info" data-after-save-function="showContactInfoOverview">
                        <div class="contact-section" data-contact-section="contact-info">
                            <h2>Contact Info</h2>
							<?php
							$phoneNumber = $otherPhoneNumber = $cellPhoneNumber = false;
							foreach ($GLOBALS['gUserRow']['phone_numbers'] as $thisPhone) {
								if ($thisPhone['description'] == "Primary" && empty($phoneNumber)) {
									$phoneNumber = $thisPhone['phone_number'];
								} else if (!in_array($thisPhone, array("cell", "mobile", "text")) && empty($otherPhoneNumber)) {
									$otherPhoneNumber = $thisPhone['phone_number'];
								} else if (in_array($thisPhone, array("cell", "mobile", "text")) && empty($cellPhoneNumber)) {
									$cellPhoneNumber = $thisPhone['phone_number'];
								}
							}
							if (empty($phoneNumber)) {
								$phoneNumber = $otherPhoneNumber;
							}

							echo createFormLineControl("contacts", "first_name", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['first_name']));
							echo createFormLineControl("contacts", "middle_name", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['middle_name']));
							echo createFormLineControl("contacts", "last_name", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['last_name']));
							echo createFormLineControl("contacts", "business_name", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['business_name']));

							echo createFormLineControl("contacts", "email_address", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['email_address']));
							echo createFormLineControl("phone_numbers", "phone_number", array("form_label" => "Primary Phone", "not_null" => (!empty($this->getPageTextChunk("phone_required"))), "initial_value" => $phoneNumber));
							echo createFormLineControl("phone_numbers", "phone_number", array("column_name" => "cell_phone_number", "form_label" => "Cell Phone", "help_label" => "For receiving text notifications", "not_null" => false, "initial_value" => $cellPhoneNumber));
							?>
                        </div>

                        <div class="contact-section" data-contact-section="primary-address">
                            <h2>Primary Address</h2>
							<?php
							echo createFormLineControl("contacts", "address_1", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['address_1'], "classes" => "autocomplete-address"));
							echo createFormLineControl("contacts", "address_2", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['address_2']));
							echo createFormLineControl("contacts", "city", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['city']));
							echo createFormLineControl("contacts", "state", array("form_label" => "State/Province", "not_null" => false, "initial_value" => $GLOBALS['gUserRow']['state']));
							?>
                            <div class="form-line" id="_state_select_row">
                                <label for="state_select" class="required-label">State</label>
                                <select tabindex="10" id="state_select" name="state_select" class="validate[required]">
                                    <option value="">[Select]</option>
									<?php
									foreach (getStateArray() as $stateCode => $state) {
										?>
                                        <option value="<?= $stateCode ?>" <?= ($stateCode == $GLOBALS['gUserRow']['state'] ? " selected" : "") ?>><?= htmlText($state) ?></option>
										<?php
									}
									?>
                                </select>
                                <div class='clear-div'></div>
                            </div>
							<?php
							$pageControls = DataSource::returnPageControls();
							if (array_key_exists("country_id", $pageControls) && array_key_exists("initial_value", $pageControls['country_id'])) {
								$initialCountryId = $pageControls['country_id']['initial_value'];
							} else {
								$initialCountryId = 1000;
							}
							echo createFormLineControl("contacts", "postal_code", array("no_required_label" => true, "not_null" => true, "data-conditional-required" => "$(\"#country_id\").val() < 1002", "initial_value" => $GLOBALS['gUserRow']['postal_code']));
							echo createFormLineControl("contacts", "country_id", array("not_null" => true, "initial_value" => (empty($GLOBALS['gUserRow']['country_id']) ? $initialCountryId : $GLOBALS['gUserRow']['country_id'])));
							?>
                        </div>

                        <div class="contact-section" data-contact-section="account-information">
                            <h2>Account Information</h2>
                            <div class="form-line" id="_user_name_row">
                                <label for="user_name" class="required-label">User Name</label>
								<?php if (!$GLOBALS['gLoggedIn']) { ?>
                                    <span class="help-label">We suggest you use your email address</span>
								<?php } ?>
                                <input tabindex="10" type="text" autocomplete="chrome-off" autocomplete="off" class="code-value allow-dash lowercase validate[required]" size="40" maxlength="40" id="user_name" name="user_name" value="<?= $GLOBALS['gUserRow']['user_name'] ?>">
                                <p id="_user_name_message"></p>
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line user-logged-in" id="current_password_row">
                                <label for="current_password">Current Password</label>
                                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[required]" data-conditional-required="!empty($('#new_password').val())" type="password" size="40" maxlength="40" id="current_password" name="current_password" value=""><span class='fad fa-eye show-password'></span>
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line" id="_password_row">
                                <label for="password" class="<?= ($GLOBALS['gLoggedIn'] ? "" : "required-label") ?>"><span class='user-logged-in'>New </span>Password</label>
								<?php
								$helpLabel = getFieldFromId("control_value", "page_controls", "page_id", $GLOBALS['gPageRow']['page_id'], "column_name = 'new_password' and control_name = 'help_label'");
								?>
                                <span class='help-label'><?= $helpLabel ?></span>
								<?php
								$minimumPasswordLength = getPreference("minimum_password_length");
								if (empty($minimumPasswordLength)) {
									$minimumPasswordLength = 10;
								}
								if (getPreference("PCI_COMPLIANCE")) {
									$noPasswordRequirements = false;
								} else {
									$noPasswordRequirements = getPreference("no_password_requirements");
								}
								?>
                                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="<?= ($noPasswordRequirements ? "no-password-requirements " : "") ?>validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]<?= ($GLOBALS['gLoggedIn'] ? "" : ",required") ?>] password-strength" type="password" size="40" maxlength="40" id="new_password" name="new_password" value=""><span class='fad fa-eye show-password'></span>
                                <div class='strength-bar-div hidden' id='new_password_strength_bar_div'>
                                    <p class='strength-bar-label' id='new_password_strength_bar_label'></p>
                                    <div class='strength-bar' id='new_password_strength_bar'></div>
                                </div>
                                <div class='clear-div'></div>
                            </div>

                            <div class="form-line" id="_password_again_row">
                                <label for="password_again" class="<?= ($GLOBALS['gLoggedIn'] ? "" : "required-label") ?>">Re-enter <span class='user-logged-in'>New </span>Password</label>
                                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="password" class="validate[equals[new_password]]" size="40" maxlength="40" id="password_again" name="password_again" value=""><span class='fad fa-eye show-password'></span>
                                <div class='clear-div'></div>
                            </div>
                        </div>

                        <div class="save-changes-wrapper">
                            <p class="error-message"></p>
                            <button type="button" class="save-changes"><?= getLanguageText("Save Changes") ?></button>
                            <button type="button" class="cancel-changes cancel-edit-contact-info"><?= getLanguageText("Cancel") ?></button>
                        </div>
                    </form>
                </div>

                <div id="wishlists_wrapper" class="my-account-content hidden">
                    <h2>Wishlist</h2>
					<?php
					$wishlistWrapper = $this->getFragment("MY_ACCOUNT_WISHLISTS_WRAPPER");
					if (empty($wishlistWrapper)) {
						ob_start();
						?>
                        <table id="wishlists_content">
                            <thead>
                            <tr>
                                <th></th>
                                <th>Description</th>
                                <th>Notify</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="wish_list_items_wrapper"></tbody>
                        </table>
						<?php
						$wishlistWrapper = ob_get_clean();
					}
					echo $wishlistWrapper;
					?>
                </div>

                <div id="recently_viewed_products_wrapper" class="my-account-content hidden">
                    <h2>Recently Viewed Products</h2>
					<?php
					$recentlyViewedProductsWrapper = $this->getFragment("MY_ACCOUNT_RECENTLY_VIEWED_PRODUCTS_WRAPPER");
					if (empty($recentlyViewedProductsWrapper)) {
						ob_start();
						?>
                        <table id="recently_viewed_products_content">
                            <thead>
                            <tr>
                                <th></th>
                                <th>Description</th>
                                <th>Viewed On</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="recently_viewed_products_items_wrapper"></tbody>
                        </table>
						<?php
						$recentlyViewedProductsWrapper = ob_get_clean();
					}
					echo $recentlyViewedProductsWrapper;
					?>
                    <p id="no_recently_viewed_products_message">No recently viewed products found</p>
                </div>

                <div id="orders_wrapper" class="my-account-content hidden">
                    <h2>My Orders</h2>
					<?php
					$ordersWrapper = $this->getFragment("MY_ACCOUNT_ORDERS_WRAPPER");
					if (empty($ordersWrapper)) {
						ob_start();
						?>
                        <table id="orders_content" class="hidden">
                            <thead>
                            <tr>
                                <th></th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
						<?php
						$ordersWrapper = ob_get_clean();
					}
					echo $ordersWrapper;
					?>
                    <p id="no_orders_message">You have no current or past orders</p>
                </div>

                <div id="payments_wrapper" class="my-account-content hidden">
					<?php
					$userCanDelete = $this->getPageTextChunk("USER_CAN_DELETE_PAYMENT_METHOD_IN_USE");
					$inactiveMessage = (!$userCanDelete ? "To make a payment method inactive, all recurring payments using that payment method must first be cancelled."
						: "Making a payment method inactive will also end any recurring payments using that payment method.");
					?>
                    <h2>Payment Methods</h2>
                    <p id="inactive_payment_method_message" class="section-message"><?= $inactiveMessage ?></p>
                    <table id="accounts_table">
                        <thead>
                        <tr>
                            <th>Label</th>
                            <th>Payment Method</th>
                            <th>Expiration</th>
                            <th>Notes</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button id="add_payment_method" class="section-add-item">Add payment method</button>
                </div>

                <div id="new_payment_wrapper" class="my-account-content hidden"></div>

                <div id="identifiers_wrapper" class="my-account-content hidden">
					<?php
					$identifiersMessage = $this->getPageTextChunk("MY_ACCOUNT_IDENTIFIERS_MESSAGE") ?:
						"Upload multiple ID's here such as Veteran ID card (VIC), First Responders, Driver License and Concealed Carry ID's";
					?>
                    <h2>Identifiers</h2>
                    <p id="identifiers_message" class="section-message"><?= $identifiersMessage ?></p>
                    <table id="identifiers_table">
                        <thead>
                        <tr>
                            <th>Label</th>
                            <th>ID Number</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button id="add_identifier" class="section-add-item">Add identifier</button>
                </div>

                <div id="new_identifier_wrapper" class="my-account-content hidden">
                    <a href="#" id="return_to_identifiers" class="cancel-new-identifier return-link">
                        <i class="fad fa-arrow-left" aria-hidden="true"></i>
                        Return to identifiers
                    </a>

                    <form enctype="multipart/form-data" id="_new_identifier_form" data-url-action="save_identifier" data-after-save-function="showIdentifiers">
                        <h2>Type of ID</h2>

                        <div class="form-line" id="_contact_identifier_type_id_row">
                            <select id="contact_identifier_type_id" name="contact_identifier_type_id" class="validate[required]" aria-label="ID Type"></select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_identifier_value_row">
                            <label for="identifier_value" class="required-label">ID Number</label>
                            <input type="text" class="validate[required]" size="50" maxlength="255" id="identifier_value" name="identifier_value" placeholder="ID Number">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line hidden" id="_image_id_file_row">
                            <label for="image_id_file">Image</label>
                            <input type="file" class="" id="image_id_file" name="image_id_file">
                            <input type='hidden' id='image_id" name=' image_id'>
                            <span id='image_exists'></span>
                            <div class='clear-div'></div>
                        </div>

                        <div class="save-changes-wrapper">
                            <p class="error-message"></p>
                            <button type="button" class="save-changes"><?= getLanguageText("Save Changes") ?></button>
                            <button type="button" class="cancel-changes cancel-new-identifier"><?= getLanguageText("Cancel") ?></button>
                        </div>
                    </form>
                </div>

				<?php
				$fflCustomFieldId = CustomField::getCustomFieldIdFromCode("DEFAULT_FFL_DEALER");
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");

				if (!empty($fflCustomFieldId) && !empty($fflRequiredProductTagId)) {
					$federalFirearmsLicenseeId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER");
					$fflDisplayName = "";
					if (!empty($federalFirearmsLicenseeId)) {
						$fflRow = (new FFL($federalFirearmsLicenseeId))->getFFLRow();
						if (!empty($fflRow)) {
							$fflDisplayName = empty($fflRow['licensee_name']) ? "" : $fflRow['licensee_name'];
							if (!empty($fflRow['business_name']) && $fflRow['business_name'] != $fflRow['licensee_name']) {
								$fflDisplayName .= (empty($fflDisplayName) ? "" : ", ") . $fflRow['business_name'];
							}
							$fflDisplayName = $fflDisplayName . ", " . $fflRow['address_1'] . ", " . $fflRow['city'];
						}
					}
					if (empty($fflDisplayName) && !empty($federalFirearmsLicenseeId)) {
						$federalFirearmsLicenseeId = "";
						CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER", "");
					}

					?>
                    <div id="ffl_dealer_wrapper" class="my-account-content hidden">
                        <h2><?= getLanguageText("FFL Dealer") ?></h2>
                        <p>Changing your default FFL does NOT affect existing orders. To change the FFL on an existing order, contact customer service.</p>

                        <form enctype="multipart/form-data" method="post" data-url-action="update_ffl_dealer">
                            <input type="hidden" id="federal_firearms_licensee_id" name="federal_firearms_licensee_id" class="show-next-section" value="<?= $federalFirearmsLicenseeId ?>">
                            <p><?= getLanguageText("Your Default FFL Dealer") ?>: <span id="selected_ffl_dealer"><?= (empty($fflDisplayName) ? getLanguageText("No default selected") : $fflDisplayName) ?></span></p>
                            <p id="ffl_dealer_count_paragraph">
                                <span id="ffl_dealer_count"></span> <?= getLanguageText("Dealers found within") ?>
                                <select id="ffl_radius" aria-label="FFL radius">
                                    <option value="25">25</option>
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                </select> <?= getLanguageText("miles. Choose one below") ?>.
                            </p>
                            <input tabindex="10" type="text" placeholder="<?= getLanguageText("Search/Filter Dealers") ?>" id="ffl_dealer_filter" aria-label="Search FFL">
                            <div id="ffl_dealers_wrapper">
                                <ul id="ffl_dealers">
                                </ul>
                            </div>

                            <div class="save-changes-wrapper">
                                <p class="error-message"></p>
                                <button type="button" class="save-changes"><?= getLanguageText("Save Changes") ?></button>
                            </div>
                        </form>
                    </div>
					<?php
				}
				?>

                <div id="invoice_payments_wrapper" class="my-account-content hidden">
                </div>

                <div id="invoices_wrapper" class="my-account-content hidden">
                    <h2>Invoice History</h2>
					<?php
					$invoicesWrapper = $this->getFragment("MY_ACCOUNT_INVOICES_WRAPPER");
					if (empty($invoicesWrapper)) {
						ob_start();
						?>
                        <div id="invoices_content" class="hidden">
                            <table>
                                <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Invoice Date</th>
                                    <th>Due Date</th>
                                    <th>Date Completed</th>
                                    <th>Invoice Total</th>
                                    <th>Balance Due</th>
                                    <th>Order Number</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                                <tfoot>
                                <tr>
                                    <td id="invoices_total_balance_title" colspan="5" class="highlighted-text">Total Outstanding Invoices</td>
                                    <td id="invoices_total_balance" class="align-right highlighted-text"></td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            </table>

							<?php
							if (getFieldFromId("page_id", "pages", "link_name",
								"invoice-payments", "client_id = ?", $GLOBALS['gClientId'])) {
								echo "<p id='invoice_payments_link'>To make a payment, go to <a class='invoice-payments-link highlighted-text' href='#'>Invoice Payments</a>.</p>";
							}
							?>
                        </div>

						<?php
						$invoicesWrapper = ob_get_clean();
					}
					echo $invoicesWrapper;
					?>
                    <p id="no_invoices_message">No invoices found</p>
                </div>

                <div id="ticket_wrapper" class="my-account-content hidden">
                    <h2><?= getLanguageText("Help Desk Tickets") ?></h2>

                    <table class='grid-table' id='help_desk_ticket_table'>
                        <tr>
                            <th>Ticket #</th>
                            <th>Submitted on</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Closed On</th>
                        </tr>
						<?php
						$resultSet = executeQuery("select * from help_desk_entries where contact_id = ? order by time_submitted desc", $GLOBALS['gUserRow']['contact_id']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <tr class='help-desk-ticket' data-help_desk_entry_id="<?= $row['help_desk_entry_id'] ?>">
                                <td><?= $row['help_desk_entry_id'] ?></td>
                                <td><?= date("m/d/Y g:ia", strtotime($row['time_submitted'])) ?></td>
                                <td><?= htmlText($row['description']) ?></td>
                                <td><?= htmlText(getFieldFromId("description", "help_desk_statuses", "help_desk_status_id", $row['help_desk_status_id'])) ?></td>
                                <td><?= (empty($row['time_closed']) ? "" : date("m/d/Y g:ia", strtotime($row['time_closed']))) ?></td>
                            </tr>
							<?php
						}
						?>
                    </table>
                </div>

                <div id="product_reviews_wrapper" class="my-account-content hidden">
                    <h2>Product Reviews</h2>
					<?php
					$coursesWrapper = $this->getFragment("MY_ACCOUNT_PRODUCT_REVIEWS_WRAPPER");
					if (empty($coursesWrapper)) {
						ob_start();
						?>
                        <table id="product_reviews_content">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Pending Approval</th>
                            </tr>
                            </thead>
                            <tbody id="product_reviews_items_wrapper"></tbody>
                        </table>
						<?php
						$coursesWrapper = ob_get_clean();
					}
					echo $coursesWrapper;
					?>
                    <p id="no_product_reviews_message">No product reviews found</p>
                </div>

                <div id="courses_wrapper" class="my-account-content hidden">
                    <h2>Education Courses</h2>
					<?php
					$coursesWrapper = $this->getFragment("MY_ACCOUNT_COURSES_WRAPPER");
					if (empty($coursesWrapper)) {
						ob_start();
						?>
                        <table id="courses_content">
                            <thead>
                            <tr>
                                <th>Course</th>
                                <th>Started</th>
                                <th>Completed</th>
                            </tr>
                            </thead>
                            <tbody id="courses_items_wrapper"></tbody>
                        </table>
						<?php
						$coursesWrapper = ob_get_clean();
					}
					echo $coursesWrapper;
					?>
                    <p id="no_courses_message">No education courses found</p>
                </div>

                <div id="event_reservations_wrapper" class="my-account-content hidden">
                    <h2>Event Reservations</h2>
                    <p id="event_reservations_intro"><?= getPageTextChunk("MY_ACCOUNT_EVENT_RESERVATIONS_INTRO") ?: "A gift card will be issued for canceled reservations which were paid" ?></p>
					<?php
					$eventReservationsWrapper = $this->getFragment("MY_ACCOUNT_EVENT_RESERVATIONS_WRAPPER");
					if (empty($eventReservationsWrapper)) {
						ob_start();
						?>
                        <table id="event_reservations_content">
                            <thead>
                            <tr>
                                <th>Event Type</th>
                                <th>Event</th>
                                <th>Facility</th>
                                <th>Date/Time</th>
                                <th></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="event_reservations_items_wrapper"></tbody>
                        </table>
						<?php
						$eventReservationsWrapper = ob_get_clean();
					}
					echo $eventReservationsWrapper;
					?>
                    <p id="no_event_reservations_message">No event reservations found</p>
                </div>
            </div>
        </div>

		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function jqueryTemplates() {
		$wishListItemFragment = $this->getFragment("MY_ACCOUNT_WISH_LIST_ITEM");
		if (empty($wishListItemFragment)) {
			ob_start();
			?>
            <table class="hidden" id="wishlist_item_template">
                <tbody id="_wish_list_item_block">
                <tr class="wish-list-item my-account-table-item %other_classes%" id="wish_list_item_id_%wish_list_item_id%" data-product_id="%product_id%">
                    <td class="clickable align-center"><img %image_src%="%small_image_url%"></td>
                    <td class="clickable">%description%<span class="out-of-stock-notice">Out of Stock</span><span class="no-online-order-notice">In-store purchase only</span></td>
                    <td class="align-center">
                        <span class="sm-only">Notify when in stock? </span>
                        <input type="checkbox" class='notify-when-in-stock' name="notify_when_in_stock_%wish_list_item_id%" id="notify_when_in_stock_%wish_list_item_id%" value="1">
                    </td>
                    <td class="wish-list-item-sale-price"><span class="sm-only">Price: </span>%sale_price%</td>
                    <td class="my-account-table-controls align-center">
                        <span class="fad fa-trash remove-item" data-product_id="%product_id%" title="Remove"></span>
                        <span class="fad fa-cart-shopping add-to-cart" data-product_id="%product_id%" title="Add to Cart"></span>
                    </td>
                </tr>
                </tbody>
            </table>
			<?php
			$wishListItemFragment = ob_get_clean();
		}
		echo $wishListItemFragment;
	}

	function internalCSS() {
		?>
        <style>
            #help_desk_ticket_table th {
                font-size: .9rem;
                text-align: left;
            }
            #help_desk_ticket_table td {
                font-size: .8rem;
            }
            #help_desk_ticket_table tr.help-desk-ticket {
                cursor: pointer;
            }
            #dashboard_wrapper h1 {
                font-size: 2.5rem;
                padding: 0 0 60px;
                text-align: left;
            }

            #menu_wrapper a {
                display: block;
                padding: .4rem 1rem;
                font-size: 0.8rem;
            }

            #menu_wrapper button {
                margin: 6rem 1rem 0;
            }

            #menu_wrapper {
                max-width: 400px;
                width: 400px;
                padding: 40px;
            }

            .payment-method-fields {
                display: none;
            }

            #content_wrapper {
                padding: 40px;
                flex-shrink: 1;
            }

            thead tr {
                font-size: 0.75rem;
            }

            thead th {
                padding: 10px;
                text-align: left;
            }

            td.order-item-img img {
                width: 200px;
            }

            #ffl_dealers li.ffl-dealer {
                list-style-type: none;
                margin-bottom: 0;
                padding: 1rem;
                cursor: pointer;
            }

            #ffl_dealers {
                max-height: 600px;
                overflow-y: auto;
            }

            #my_account_wrapper #ffl_dealer_wrapper #ffl_dealer_filter {
                max-width: 400px;
                border-radius: 25rem;
                padding: 5px 20px;
                width: 100%;
            }

            #my_account_wrapper #ffl_radius {
                width: auto;
            }

            #my_account_wrapper p:empty {
                display: none;
            }

            .save-changes-wrapper {
                margin-top: 3rem;
                margin-bottom: 1rem;
            }

            #dashboard_wrapper {
                max-width: 1200px;
                margin: 2rem auto;
            }

            #dashboard_wrapper > div {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
            }

            #dashboard_wrapper > div > div {
                cursor: pointer;
                height: 100px;
                display: flex;
                margin-right: 40px;
                flex: 0 25%;
            }

            #dashboard_wrapper h3 {
                margin: 0;
                flex-grow: 0;
                font-weight: bold;
            }

            #dashboard_wrapper > div > div i {
                flex-grow: 0;
                margin-bottom: 0.5rem;
                font-size: 1.5rem;
                margin-right: .5rem;
            }

            h2 {
                font-size: 1.5rem;
                margin-bottom: 2rem;
            }

            .my-account-table-item td {
                padding: 1rem;
            }

            .my-account-table-item .my-account-table-controls span {
                display: inline-block;
                padding: 0.5rem;
                cursor: pointer;
            }

            #orders_content {
                width: 100%;
            }

            .order-header {
                border-top: 1px solid gainsboro;
            }

            .order-header table {
                width: 100%;
            }

            .order-header table td {
                padding: 1rem;
            }

            .section-message {
                margin: 1rem 0 2rem;
            }

            .section-add-item {
                margin-top: 2rem;
                margin-bottom: 2rem;
            }

            #identifiers_table {
                min-width: 600px;
            }

            #new_payment_wrapper h2,
            #new_identifier_wrapper h2,
            #contact_info_wrapper h2 {
                margin-bottom: 0.5rem;
                margin-top: 1rem;
            }

            #_main_content #menu_header p {
                display: flex;
                align-items: center;
            }

            #_main_content #menu_header p span {
                font-weight: bold;
                font-size: 1.5rem;
            }

            #_main_content #menu_header p i {
                font-size: 1rem;
                text-align: right;
                margin-left: 1rem;
            }

            #_main_content .form-line span.help-label {
                padding-bottom: 10px;
            }

            #_main_content .strength-bar-div {
                padding-top: 10px;
            }

            #menu_header {
                padding: 1rem;
            }

            #menu_header span {
                cursor: pointer;
                font-size: 1rem;
            }

            #contact_info_overview_wrapper .edit-contact-info {
                font-size: 0.75rem;
                cursor: pointer;
            }

            #contact_info_overview_wrapper h2 .edit-contact-info {
                margin-left: 3rem;
                vertical-align: middle;
            }

            #contact_info_overview_wrapper > div {
                margin-bottom: 3rem;
            }

            .mailing-list-item {
                margin: 0.5rem 0;
            }

            .mailing-list-item label {
                padding-left: 10px;
                cursor: pointer;
            }

            .product-review-item .rating span.fa-star {
                color: gold;
            }

            @media (min-width: 600px) {
                #my_account_wrapper {
                    display: flex;
                }

                .sm-only {
                    display: none;
                }

                .order-item-quantity,
                .order-total-amount,
                .wish-list-item-sale-price {
                    text-align: right;
                }
            }

            @media (max-width: 600px) {
                #my_account_wrapper {
                    position: relative;
                    display: block;
                }

                #my_account_wrapper #menu_wrapper {
                    width: 100%;
                    padding: 10px 40px;
                }

                #my_account_wrapper.show-menu #menu_wrapper {
                    position: absolute;
                    height: 100vh;
                }

                #my_account_wrapper #menu_wrapper button {
                    margin: 1rem;
                }

                #my_account_wrapper #menu_header {
                    padding: 0;
                }

                #my_account_wrapper #menu_contents {
                    display: none;
                }

                #my_account_wrapper.show-menu #menu_contents {
                    display: block;
                }

                #my_account_wrapper #dashboard_wrapper {
                    margin: 0 auto;
                }

                #my_account_wrapper #dashboard_wrapper h1 {
                    display: none;
                }

                #my_account_wrapper #content_wrapper {
                    padding: 20px;
                }

                #my_account_wrapper.show-menu #content_wrapper {
                    display: none;
                }

                #my_account_wrapper #dashboard_wrapper > div > div {
                    flex: 0 45%;
                }

                #my_account_wrapper #identifiers_table {
                    min-width: unset;
                }

                /* Hide label and notes on mobile */
                #accounts_table thead th:nth-child(1),
                #accounts_table tbody td:nth-child(1),
                #accounts_table thead th:nth-child(4),
                #accounts_table tbody td:nth-child(4) {
                    display: none;
                }

                #orders_content thead,
                #wishlists_content thead {
                    display: none;
                }

                #orders_content tr,
                #wishlists_content tr {
                    display: flex;
                    flex-direction: column;
                }

                #orders_content td:empty,
                #wishlists_content td:empty {
                    display: none;
                }

                #orders_content td img,
                #wishlists_content td img {
                    max-width: 120px;
                }

                .order-shipments-table th {
                    display: none;
                }

                .wish-list-item-sale-price {
                    text-align: center;
                }

                .my-account-content h2 {
                    margin-top: 0;
                    padding-top: 0;
                }
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="make_account_inactive_dialog" class="dialog-box">
            <p><?=$this->iMakeAccountInactiveMessage?></p>
        </div>

        <div id="delete_identifier_dialog" class="dialog-box">
            <p>Are you sure you want to remove this contact identifier?</p>
        </div>

        <div id="cancel_reservation_dialog" class="dialog-box">
            <p><?=$this->iCancelReservationMessage?></p>
        </div>

        <div id="invoice_details_dialog" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new MyAccountV2Page();
$pageObject->displayPage();
