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

$GLOBALS['gPageCode'] = "RETAILSTOREORDERSTATUS";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
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
		}
	}

	function javascript() {
		?>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(".write-review").click(function () {
                var productId = $(this).data("product_id");
                window.open("/product-review?product_id=" + productId);
                return false;
            });
            $(".buy-again").click(function () {
                var productId = $(this).data("product_id");
                var orderItemId = $(this).data("order_item_id");
                if (empty(orderItemId)) {
                    addProductToShoppingCart(productId);
                } else {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=buy_again&order_item_id=" + orderItemId, function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            if ("unable_to_add" in returnArray) {
                                addProductToShoppingCart(productId);
                            } else {
                                if (typeof afterAddToCart == "function") {
                                    setTimeout(function () {
                                        afterAddToCart(productId, 1);
                                    }, 100);
                                }
                            }
                        }
                    });
                }
                $(this).removeClass("buy-again").html("Item In Cart");
                return false;
            });
            $(".download-product").click(function () {
                var fileId = $(this).data("file_id");
                document.location = "/download.php?force_download=true&id=" + fileId;
                return false;
            });
            $(".print-receipt").click(function () {
                var orderId = $(this).closest(".retail-order").data("order_id");
                window.open("/order-receipt?order_id=" + orderId);
                return false;
            });
            $(".contact-support").click(function () {
                var orderId = $(this).closest(".retail-order").data("order_id");
                window.open("/contact-support?description=Regarding%20Order%20Number%20" + orderId);
                return false;
            });

            $(".order-document-upload").click(function () {
                var orderId = $(this).data("order_id");
                if ($(this).hasClass("submit-form")) {
                    if ($("#_upload_form_" + orderId).validationEngine("validate")) {
                        $("#_post_iframe").html("");
                        $(".order-document-upload").addClass("hidden");
                        $("body").addClass("waiting-for-ajax");
                        $("#_post_iframe").off("load");
                        $("#_upload_form_" + orderId).attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=upload_document").attr("method", "POST").attr("target", "post_iframe").submit();
                        $("#_post_iframe").on("load", function () {
                            if (postTimeout != null) {
                                clearTimeout(postTimeout);
                            }
                            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                            var returnText = $(this).contents().find("body").html();
                            const returnArray = processReturn(returnText);
                            if (returnArray === false) {
                                return;
                            }
                            $("#order_document_" + orderId).remove();
                            $(".order-document-upload").removeClass("hidden");
                        });
                        postTimeout = setTimeout(function () {
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
        </script>
		<?php
	}

	function mainContent() {
		$loyaltyMessage = "";
		if ($GLOBALS['gLoggedIn']) {
			$resultSet = executeQuery("select * from loyalty_programs where client_id = ? and (user_type_id = ? or user_type_id is null) and inactive = 0 and " .
				"internal_use_only = 0 order by user_type_id desc,sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['user_type_id']);
			if (!$loyaltyProgramRow = getNextRow($resultSet)) {
				$loyaltyProgramRow = array();
			}
			$loyaltyProgramPointsRow = getRowFromId("loyalty_program_points", "user_id", $GLOBALS['gUserId'], "loyalty_program_id = ?", $loyaltyProgramRow['loyalty_program_id']);
			$pointDollarValue = 0;
			$resultSet = executeQuery("select point_value from loyalty_program_values where loyalty_program_id = ? order by minimum_amount", $loyaltyProgramRow['loyalty_program_id']);
			if ($row = getNextRow($resultSet)) {
				$pointDollarValue = $row['point_value'];
			}
			if (empty($pointDollarValue)) {
				$pointDollarValue = 0;
			}
			$pointDollarsAvailable = floor($loyaltyProgramPointsRow['point_value']) * $pointDollarValue;
			if ($pointDollarsAvailable > 0) {
				$loyaltyMessage = "<p class='loyalty-points-message'>You have accumulated <span class='loyalty-points-value'>" . floor($loyaltyProgramPointsRow['point_value']) . "</span> loyalty points worth <span class='loyalty-points-dollars'>$" . number_format($pointDollarsAvailable, 2, ".", ",") . "</span> on a future order.</p>";
			}
		}
		?>
        <div id="_order_status_content_wrapper">
            <div id="_order_status_content">
                <h1>ORDERS</h1>
				<?= $loyaltyMessage ?>
				<?= $this->iPageData['content'] ?>

                <p id="error_message" class="error-message"></p>
				<?php
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$resultSet = executeQuery("select * from orders where contact_id = ? and deleted = 0 order by order_id desc", $GLOBALS['gUserRow']['contact_id']);
				if ($resultSet['row_count'] == 0) {
					?>
                    <h2>You have no current or past orders</h2>
					<?php
				}
				$productCatalog = new ProductCatalog();
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
						"order_item_id = order_items.order_item_id) as serial_numbers,(select count(*) from order_item_addons where order_item_id = order_items.order_item_id) as addon_count from order_items join products using (product_id) left outer join product_data using (product_id) where order_id = ? and deleted = 0", $row['order_id']);
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

					$orderItemElement = $this->getPageTextChunk("retail_store_order_status_item");
					if (empty($orderItemElement)) {
						$orderItemElement = $this->getFragment("retail_store_order_status_item");
					}
					if (empty($orderItemElement)) {
						ob_start();
						?>
                        <tr class="order-item">
                            <td class="order-item-img align-center"><a href="%image_url%" class="pretty-photo"><img src="%image_url%"></a></td>
                            <td class="order-item-details product-description">
                                <p><span class="order-item-product-code">Product Code: %product_code%</span></p>
                                <p><a href='%product_detail_link%'><span class="order-item-description">%description%</span></a></p>
                                <p><span class="order-item-serial-number %serial_number_hidden%">Serial Number: %serial_numbers%</span></p>
                                <p><span class="order-item-upc-code">UPC: %upc_code%</span></p>
                                <p><span class="order-total-amount">$%sale_price%</span></p>
                                <p><span class="order-anticipated-ship-date">%anticipated_ship_date%</span></p>
                            </td>
                            <td class="order-item-quantity align-right">%quantity%</td>
                            <td class="order-item-options align-center">
                                <button class="order-item-button %product_review_class%" data-product_id="%product_id%">%product_review_text%</button>
                                <button class="order-item-button buy-again %hidden_if_not_searchable%" %buy_again_disabled% data-addon_count="%addon_count%" data-product_id="%product_id%" data-order_item_id="%order_item_id%">%buy_again_text%</button>
                                <button class="order-item-button download-product %file_id_hidden%" data-product_id="%product_id%" data-file_id="%file_id%">Download</button>
                            </td>
                        </tr>
						<?php
						$orderItemElement = ob_get_clean();
					}
					$fflRequiredElement = $this->getPageTextChunk("retail_store_order_status_ffl_required");
					if (empty($fflRequiredElement)) {
						$fflRequiredElement = $this->getFragment("retail_store_order_status_ffl_required");
					}
					if (empty($fflRequiredElement)) {
						ob_start();
						?>
                        <tr class="order-document" id="order_document_%order_id%">
                            <td colspan="2">
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
					$orderStatusElement = $this->getPageTextChunk("retail_store_order_status");
					if (empty($orderStatusElement)) {
						$orderStatusElement = $this->getFragment("retail_store_order_status");
					}
					if (empty($orderStatusElement)) {
						ob_start();
						?>
                        <table class="retail-order order-status-%order_status_id%" data-order_id="%order_id%">

                            <thead>
                            <tr>
                                <th><span class="order-number"><a id="order%order_id%">#%order_id%</a></span></th>
                                <th><span class="order-status-title"></span>Order Status<br><span class="order-status">%status_description%</span></th>
                                <th><span class="order-date-title">Order Date</span><br><span class="order-date">%order_date%</span></th>
                                <th><span class="order-shipping-method-title">Shipping Method</span><br><span class="shipping-method">%shipping_method%</span></th>
                                <th><span class="order-shipping-title">Shipping Address</span><br><span class="shipping-address">%shipping_address%</span></th>
                                <th><span class="order-total-title">Total</span><br><span class="order-total">$%order_total%</span></th>
                            </tr>
                            </thead>

                            <tbody class="order-block-wrapper">
                            <tr class="order-block">
                                <td colspan="6">
                                    <table class="order-item-details">
                                        <tbody class="order-items-details-wrapper">
                                        <tr class="order-items">
                                            <td class="order-item-details">

                                                <table class="order-block-details">
                                                    <tbody class="order-block-details-wrapper">
                                                    %order_items%
                                                    </tbody>
                                                </table>
                                            <td class="order-option-buttons align-center">
                                                <button class="order-option-button print-receipt">Receipt</button>
                                                <button class="order-option-button contact-support">Contact Support</button>
                                            </td>
                                        </tr>
                                        %ffl_required%
                                        %order_shipments%
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            </tbody>
                        </table>
						<?php
						$orderStatusElement = ob_get_clean();
					}
					$orderShipmentsElement = $this->getPageTextChunk("retail_store_order_status_shipments");
					if (empty($orderShipmentsElement)) {
						$orderShipmentsElement = $this->getFragment("retail_store_order_status_shipments");
					}
					if (empty($orderShipmentsElement)) {
						ob_start();
						?>
                        <tr class="order-shipments">
                            <td colspan="2">
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
					$orderShipmentItemElement = $this->getPageTextChunk("retail_store_order_status_shipment_items");
					if (empty($orderShipmentItemElement)) {
						$orderShipmentItemElement = $this->getFragment("retail_store_order_status_shipment_items");
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
						$thisItem['hidden_if_not_searchable'] = (!empty($thisItem['not_searchable']) ? "hidden" : "");
						$inventoryCount = $productCatalog->getInventoryCounts(true,array($thisItem['product_id']));
						if($inventoryCount[$thisItem['product_id']] > 0) {
							$thisItem['buy_again_text'] = "Buy Again";
							$thisItem['buy_again_disabled'] = "";
						} else {
							$thisItem['buy_again_text'] = "Out of Stock";
							$thisItem['buy_again_disabled'] = "disabled";
						}
						$thisOrderItemElement = $orderItemElement;
						foreach ($thisItem as $fieldName => $fieldData) {
							$thisOrderItemElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $thisOrderItemElement);
						}
						$orderSubstitutions['order_items'] .= $thisOrderItemElement;
					}
					if ($fflRequired) {
						foreach ($orderSubstitutions as $fieldName => $fieldData) {
							$fflRequiredElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $fflRequiredElement);
						}
						$orderSubstitutions['ffl_required'] = $fflRequiredElement;
					} else {
						$orderSubstitutions['ffl_required'] = "";
					}
					if (!empty($orderShipments)) {
						foreach ($orderSubstitutions as $fieldName => $fieldData) {
							$orderShipmentsElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $orderShipmentsElement);
						}
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
							$thisOrderShipmentItemElement = $orderShipmentItemElement;
							foreach ($thisOrderShipment as $fieldName => $fieldData) {
								$thisOrderShipmentItemElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $thisOrderShipmentItemElement);
							}
							$orderShipmentItems .= $thisOrderShipmentItemElement;
						}
						$orderShipmentsElement = str_replace("%order_shipment_items%", $orderShipmentItems, $orderShipmentsElement);
						$orderSubstitutions['order_shipments'] = $orderShipmentsElement;
					} else {
						$orderSubstitutions['order_shipments'] = "";
					}
					foreach ($orderSubstitutions as $fieldName => $fieldData) {
						$orderStatusElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $orderStatusElement);
					}
					echo $orderStatusElement;
				}
				?>
            </div>
        </div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
        <style>
            table.retail-order th {
                background-color: rgb(200, 200, 200);
            }

            table.retail-order {
                border: 3px solid rgb(200, 200, 200);
                box-shadow: 0 1px 0.5px 0 rgba(180, 180, 180, .5);
            }

            <?php
					$resultSet = executeQuery("select * from order_status where display_color is not null and client_id = ? and inactive = 0 and internal_use_only = 0",$GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$rgb = hex2rgb($row['display_color']);
						$lightRgb = $rgb;
						foreach ($lightRgb as $index => $thisColor) {
							$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
						}
			?>
            table.order-status-<?= $row['order_status_id'] ?> th {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            table.order-status-<?= $row['order_status_id'] ?> {
                border: 3px solid rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>);
                box-shadow: 0 1px 0.5px 0 rgba(180, 180, 180, .5);
            }

            <?php
					}
			?>

            table.order-item-details {
                width: 100%;
            }

            #_order_status_content button {
                margin: 5px 0;
                display: block;
            }

            table.retail-order {
                width: 100%;
                background: rgb(255, 255, 255);
                box-shadow: 1px 1px rgba(180, 180, 180, .5);
                margin-bottom: 20px;
            }

            table.retail-order th {
                font-size: 1.2em;
                line-height: 1.4;
                color: rgb(50, 50, 50);
                text-transform: uppercase;
                padding: 4px 10px;
                font-weight: 800;
                vertical-align: middle;
                text-align: left;
            }

            table.retail-order tr {
                border: .5px solid rgb(192, 192, 192);
            }

            table.retail-order td {
                padding: 4px 10px;
                vertical-align: top;
            }

            table.retail-order td.order-item-img {
                vertical-align: middle;
            }

            table.retail-order td.order-item-img img {
                max-height: 100px;
                max-width: 200px;
            }

            table.retail-order td.product-description {
                vertical-align: middle;
                padding-right: 40px;
            }

            table.retail-order p span {
                font-size: 1.2rem;
                line-height: 1.1;
            }

            .order-shipments h3 {
                margin-top: 10px;
            }

            .order-shipments-table th {
                border: .5px solid rgb(192, 192, 192);
            }

            .order-shipments-table td {
                border: .5px solid rgb(192, 192, 192);
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
