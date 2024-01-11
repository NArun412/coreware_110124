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

$GLOBALS['gPageCode'] = "DISTRIBUTORORDER";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class DistributorOrderPage extends Page {

	function sortPrices($a, $b) {
		if ($a['cost'] == $b['cost']) {
			return 0;
		}
		return ($a['cost'] > $b['cost']) ? 1 : -1;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_saved_products":
				if (empty($_GET['all']) || empty($GLOBALS['gUserRow']['administrator_flag'])) {
					$resultSet = executeQuery("select * from products join distributor_order_products using (product_id) left outer join product_data using (product_id) where distributor_order_products.user_id = ? and distributor_order_products.client_id = ?", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
				} else {
					$resultSet = executeQuery("select * from products join distributor_order_products using (product_id) left outer join product_data using (product_id) where distributor_order_products.client_id = ?", $GLOBALS['gClientId']);
				}
				$rowNumber = 0;
				ob_start();
				?>
                <table class="grid-table">
                    <tr class="suggested-product" id="" data-product_id="">
                        <th class="product-description">Description</th>
                        <th class="product-upc-code">UPC</th>
                        <th>Notes</th>
                        <th class="product-quantity-wrapper">Order</th>
                        <th class="location-wrapper">Location</th>
                    </tr>
					<?php
					while ($row = getNextRow($resultSet)) {
						$productId = $row['product_id'];
						$rowNumber++;
						$locationCosts = array();
						$locationSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " .
								$GLOBALS['gUserId'] . " and " : "user_location = 0 and ") . "client_id = ? and inactive = 0 and product_distributor_id is not null and primary_location = 1", $GLOBALS['gClientId']);
						while ($locationRow = getNextRow($locationSet)) {
							$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locationRow['location_id']);
							if (empty($productInventoryRow)) {
								$productInventoryRow['quantity'] = 0;
							}
							$cost = ProductCatalog::getLocationBaseCost($productId, $locationRow['location_id'], $productInventoryRow,false);
							if ($productInventoryRow['quantity'] > 0 || strlen($cost) > 0) {
								$locationCosts[] = array("location_id" => $locationRow['location_id'], "location" => $locationRow['description'], "quantity" => $productInventoryRow['quantity'], "cost" => $cost, "description" => $locationRow['description'] . " - " . $productInventoryRow['quantity'] . " for $" . $cost);
							}
						}
						usort($locationCosts, array($this, "sortPrices"));
						?>
                        <tr class="suggested-product" id="suggested_product_<?= $rowNumber ?>" data-product_id="<?= $row['product_id'] ?>">
                            <td class="product-description"><input type='hidden' class='distributor-order-product-id' value='<?= $row['distributor_order_product_id'] ?>'><?= htmlText($row['description']) ?></td>
                            <td class="product-upc-code"><?= htmlText($row['upc_code']) ?></td>
                            <td class="product-notes"><?= htmlText($row['notes']) ?></td>
                            <td class="product-quantity-wrapper"><input type="text" size="6" class="product-quantity align-right" id="product_quantity_<?= $rowNumber ?>" value="<?= $row['quantity'] ?>"></td>
                            <td class="location_wrapper"><select class="location" id="location_<?= $rowNumber ?>">
									<?php if (empty($locationCosts)) { ?>
                                        <option value="">[Unavailable]</option>
									<?php } else { ?>
                                        <option value="">[Don't Order]</option>
									<?php } ?>
									<?php
									foreach ($locationCosts as $locationInfo) {
										?>
                                        <option <?= ($row['location_id'] == $locationInfo['location_id'] ? " selected" : "") ?> data-quantity="<?= $locationInfo['quantity'] ?>" data-cost="<?= $locationInfo['cost'] ?>" data-location="<?= htmlText($locationInfo['location']) ?>" value="<?= $locationInfo['location_id'] ?>"><?= htmlText($locationInfo['description']) ?></option>
										<?php
									}
									?>
                                </select></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				if ($rowNumber > 0) {
					$returnArray['saved_products'] = ob_get_clean();
				} else {
					ob_get_clean();
					$returnArray['info_message'] = "No Saved Products found";
				}
				ajaxResponse($returnArray);
				break;
			case "get_suggested_products":
				$locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['suggested_location_id']);
				if (empty($GLOBALS['gUserRow']['administrator_flag']) || empty($locationId)) {
					$returnArray['error_message'] = "Invalid Location";
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeQuery("select * from product_inventories join products using (product_id) left outer join product_data using (product_id) where " .
					"reorder_level is not null and reorder_level > 0 and quantity <= reorder_level and location_id = ? and product_inventories.product_id in (select product_id from products where client_id = ?) and " .
					"product_inventories.product_id not in (select product_id from distributor_order_items where deleted = 0 and date_received is null and distributor_order_id in " .
					"(select distributor_order_id from distributor_orders where date_completed is null)) order by upc_code,description", $locationId, $GLOBALS['gClientId']);
				$rowNumber = 0;
				ob_start();
				?>
                <table class="grid-table">
                    <tr class="suggested-product" id="" data-product_id="">
                        <th class="product-description">Description</th>
                        <th class="product-upc-code">UPC</th>
                        <th class="product-inventory">On Hand</th>
                        <th class="product-quantity-wrapper">Order</th>
                        <th class="location-wrapper">Location</th>
                    </tr>
					<?php
					while ($row = getNextRow($resultSet)) {
						$productId = $row['product_id'];
						$rowNumber++;
						$waitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
						$row['quantity'] -= $waitingQuantity;
						if ($row['quantity'] < 0) {
							$row['quantity'] = 0;
						}
						$orderQuantity = (empty($row['replenishment_level']) ? "" : $row['replenishment_level'] - $row['quantity']);
						if (!empty($orderQuantity) && $orderQuantity < 0) {
							continue;
						}
						$locationCosts = array();
						$locationSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " .
								$GLOBALS['gUserId'] . " and " : "user_location = 0 and ") . "client_id = ? and inactive = 0 and product_distributor_id is not null and primary_location = 1", $GLOBALS['gClientId']);
						while ($locationRow = getNextRow($locationSet)) {
							$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locationRow['location_id']);
							if (empty($productInventoryRow)) {
								$productInventoryRow['quantity'] = 0;
							}
							$cost = ProductCatalog::getLocationBaseCost($productId, $locationRow['location_id'], $productInventoryRow,false);
							if ($productInventoryRow['quantity'] > 0 || strlen($cost) > 0) {
								$locationCosts[] = array("location_id" => $locationRow['location_id'], "location" => $locationRow['description'], "quantity" => $productInventoryRow['quantity'], "cost" => $cost, "description" => $locationRow['description'] . " - " . $productInventoryRow['quantity'] . " for $" . $cost);
							}
						}
						if (empty($locationCosts)) {
							continue;
						}
						usort($locationCosts, array($this, "sortPrices"));
						?>
                        <tr class="suggested-product" id="suggested_product_<?= $rowNumber ?>" data-product_id="<?= $row['product_id'] ?>">
                            <td class="product-description"><?= htmlText($row['description']) ?></td>
                            <td class="product-upc-code"><?= htmlText($row['upc_code']) ?></td>
                            <td class="product-inventory align-right"><?= $row['quantity'] ?></td>
                            <td class="product-quantity-wrapper"><input type="text" size="6" class="product-quantity align-right" id="product_quantity_<?= $rowNumber ?>" value="<?= $orderQuantity ?>"></td>
                            <td class="location-wrapper"><select class="location" id="location_<?= $rowNumber ?>">
                                    <option value="">[Don't Order]</option>
									<?php
									foreach ($locationCosts as $locationInfo) {
										?>
                                        <option data-quantity="<?= $locationInfo['quantity'] ?>" data-cost="<?= $locationInfo['cost'] ?>" data-location="<?= htmlText($locationInfo['location']) ?>" value="<?= $locationInfo['location_id'] ?>"><?= htmlText($locationInfo['description']) ?></option>
										<?php
									}
									?>
                                </select></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				if ($rowNumber > 0) {
					$returnArray['suggested_products'] = ob_get_clean();
				} else {
					ob_get_clean();
					$returnArray['info_message'] = "No Suggested Products found";
				}
				ajaxResponse($returnArray);
				break;
			case "create_orders":
				if (!empty($_POST['_add_hash'])) {
					$resultSet = $this->iDatabase->executeQuery("select * from add_hashes where add_hash = ?", $_POST['_add_hash']);
					if ($row = $this->iDatabase->getNextRow($resultSet)) {
						$returnArray['error_message'] = "This form has already been saved";
						ajaxResponse($returnArray);
						break;
					}
				}
				if (!empty($_POST['_add_hash'])) {
					executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())", $_POST['_add_hash']);
				}
				$distributorOrders = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("product_id_")) == "product_id_") {
						$lineNumber = substr($fieldName, strlen("product_id_"));
						$productId = $fieldData;
						$locationId = $_POST['location_id_' . $lineNumber];
						$quantity = $_POST['quantity_' . $lineNumber];
						if (empty($productId) || empty($locationId) || empty($quantity) || !is_numeric($quantity)) {
							continue;
						}
						if (!array_key_exists($locationId, $distributorOrders)) {
							$distributorOrders[$locationId] = array();
						}
						$distributorOrders[$locationId][] = array("product_id" => $productId, "quantity" => $quantity, "notes" => $_POST['notes_' . $lineNumber], "distributor_order_product_id" => $_POST['distributor_order_product_id_' . $lineNumber]);
					}
				}
				$resultMessage = "";
				$returnArray['remove_location_ids'] = array();
				foreach ($distributorOrders as $locationId => $productArray) {
					$locationDescription = getFieldFromId("description", "locations", "location_id", $locationId);
					$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
					if (empty($productDistributor)) {
						$returnArray['info_message'] = "Unable to create distributor: " . $locationId;
						ajaxResponse($returnArray);
						break;
					}

# Here: if not administrator, send user contact information for the address

					$returnValue = $productDistributor->placeDistributorOrder($productArray, array("notes" => $_POST['notes']));

					if ($returnValue === false) {
						$resultMessage .= "<p class='error-message'>Order for " . $locationDescription . " was unable to be placed: " . $productDistributor->getErrorMessage() . "</p>";
					} else {
						$distributorOrderId = $returnValue['dealer']['distributor_order_id'];
						if (!empty($distributorOrderId)) {
							foreach ($productArray as $thisProduct) {
								if (array_key_exists("product_ids", $returnValue['dealer']) && !in_array($thisProduct['product_id'], $returnValue['dealer']['product_ids'])) {
									continue;
								}
								if (!empty($thisProduct['distributor_order_product_id'])) {
									if (is_array($returnValue['failed_items']) && in_array($thisProduct['product_id'], $returnValue['failed_items'])) {
										continue;
									}
									$orderItemId = getFieldFromId("order_item_id", "distributor_order_products", "distributor_order_product_id", $thisProduct['distributor_order_product_id']);
									executeQuery("delete from distributor_order_products where client_id = ? and distributor_order_product_id = ?",
										$GLOBALS['gClientId'], $thisProduct['distributor_order_product_id']);
									if (!empty($orderItemId)) {
										executeQuery("insert ignore into distributor_order_item_links (distributor_order_id,order_item_id) values (?,?)", $distributorOrderId, $orderItemId);
									}
								}
							}
						}

						$distributorOrderId = $returnValue['class_3']['distributor_order_id'];
						if (!empty($distributorOrderId)) {
							foreach ($productArray as $thisProduct) {
								if (array_key_exists("product_ids", $returnValue['class_3']) && !in_array($thisProduct['product_id'], $returnValue['dealer']['product_ids'])) {
									continue;
								}
								if (!empty($thisProduct['distributor_order_product_id'])) {
									if (is_array($returnValue['failed_items']) && in_array($thisProduct['product_id'], $returnValue['failed_items'])) {
										continue;
									}
									$orderItemId = getFieldFromId("order_item_id", "distributor_order_products", "distributor_order_product_id", $thisProduct['distributor_order_product_id']);
									executeQuery("delete from distributor_order_products where client_id = ? and distributor_order_product_id = ?",
										$GLOBALS['gClientId'], $thisProduct['distributor_order_product_id']);
									if (!empty($orderItemId)) {
										executeQuery("insert ignore into distributor_order_item_links (distributor_order_id,order_item_id) values (?,?)", $distributorOrderId, $orderItemId);
									}
								}
							}
						}

						$returnArray['remove_location_ids'][] = $locationId;
						if (array_key_exists("failed_items", $returnValue)) {
							$resultMessage .= "<p class='error-message'>" . $productDistributor->getErrorMessage() . "</p>";
							foreach ($returnValue['failed_items'] as $thisProduct) {
								$resultMessage .= "<p class='error-message'>Product ID " . $thisProduct['product_id'] . "(" . getFieldFromId("description", "products", "product_id", $thisProduct['product_id']) . ") was unable to be ordered from " . $locationDescription . ".</p>";
							}
						}
						if (array_key_exists("dealer", $returnValue)) {
							$resultMessage .= "<p>Distributor Order ID " . $returnValue['dealer']['distributor_order_id'] . " placed with " . $locationDescription . ", Distributor Order #" . $returnValue['dealer']['order_number'] . ".</p>";
						}
						if (array_key_exists("class_3", $returnValue)) {
							$resultMessage .= "<p>Distributor Order ID " . $returnValue['class_3']['distributor_order_id'] . " placed with " . $locationDescription . ", Distributor Order #" . $returnValue['class_3']['order_number'] . " for Class 3 products.</p>";
						}
					}
				}
				$returnArray['results'] = $resultMessage;
				ajaxResponse($returnArray);
				break;
			case "search_products":
				$searchText = $_GET['search_text'];
				if (empty($searchText)) {
					ajaxResponse($returnArray);
					break;
				}
				$productArray = array();
				$resultSet = executeQuery("select * from products join product_data using (product_id) where products.client_id = ? and inactive = 0 and (product_code = ? or model = ? or upc_code = ? or manufacturer_sku = ?)", $GLOBALS['gClientId'], $searchText, $searchText, ProductCatalog::makeValidUPC($searchText), $searchText);
				while ($row = getNextRow($resultSet)) {
					$listDescription = $row['product_code'] . "<br><span class='product-description'>" . $row['description'] . "</span><br><span class='product-upc'>" . $row['upc_code'] . "</span>";
					$productArray[] = array("product_id" => $row['product_id'], "list_description" => $listDescription);
				}
				if (empty($productArray)) {
					$resultSet = executeQuery("select * from products join product_data using (product_id) where products.client_id = ? and inactive = 0 and description like ?", $GLOBALS['gClientId'], "%" . $searchText . "%");
					while ($row = getNextRow($resultSet)) {
						$listDescription = $row['product_code'] . "<br><span class='product-description'>" . $row['description'] . "</span><br><span class='product-upc'>" . $row['upc_code'] . "</span>";
						$productArray[] = array("product_id" => $row['product_id'], "list_description" => $listDescription);
					}
				}
				if (empty($productArray)) {
					$returnArray['error_message'] = "No products found";
				} else {
					$returnArray['product_list'] = $productArray;
				}
				ajaxResponse($returnArray);
				break;
			case "get_distributor_prices":
				$productId = $_GET['product_id'];
				$returnArray['image_url'] = ProductCatalog::getProductImage($productId);

				$locationSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " .
						$GLOBALS['gUserId'] . " and " : "user_location = 0 and ") . "client_id = ? and inactive = 0 and product_distributor_id is not null and primary_location = 1", $GLOBALS['gClientId']);
				$productInventories = array();
				while ($locationRow = getNextRow($locationSet)) {
					$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locationRow['location_id']);
					if (!empty($locationRow['product_distributor_id'])) {
						$locationRow['description'] = getReadFieldFromId("description", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
					}
					if (empty($productInventoryRow)) {
						$productInventoryRow['quantity'] = 0;
					}
					$cost = ProductCatalog::getLocationBaseCost($productId, $locationRow['location_id'], $productInventoryRow,false);
					if ($productInventoryRow['quantity'] > 0 || strlen($cost) > 0) {
						$productInventories[] = array("location_id" => $locationRow['location_id'], "description" => $locationRow['description'], "quantity" => $productInventoryRow['quantity'], "cost" => $cost, "product_distributor_id" => $locationRow['product_distributor_id']);
					}
				}

				$returnArray['product_inventories'] = $productInventories;
				$returnArray['product_id'] = $productId;
				$returnArray['distributor_list'] = "";
				$productDistributorIds = array();
				if (!empty($productInventories)) {
					usort($productInventories, array($this, "sortPrices"));
					foreach ($productInventories as $thisPrice) {
						$showPrice = true;
						if (!empty($thisPrice['product_distributor_id'])) {
							if (!in_array($thisPrice['product_distributor_id'], $productDistributorIds)) {
								$productDistributorIds[] = $thisPrice['product_distributor_id'];
							} else {
								$showPrice = false;
							}
						}
						$returnArray['distributor_list'] .= "<div class='product-inventory " . ($showPrice ? "" : "hidden") . "' id='product_inventory_" . $thisPrice['location_id'] .
							"' data-quantity='" . $thisPrice['quantity'] . "' data-cost='" . (empty($thisPrice['cost']) ? "" : number_format($thisPrice['cost'], 2, ".", ",")) . "'>" . $thisPrice['description'] . " - " . $thisPrice['quantity'] .
							(empty($thisPrice['cost']) ? "" : "/$" . number_format($thisPrice['cost'], 2, ".", ",")) . "</div>";
					}
				}
				if (empty($returnArray['distributor_list'])) {
					$returnArray['distributor_list'] = "Not available from any distributors";
				}

				ajaxResponse($returnArray);

				break;
		}
	}

	function javascript() {
		?>
        <script>
            function addProductToOrder(productId, description, upcCode, locationId, locationDescription, quantity, cost, notes, distributorOrderProductId) {
                if (empty(distributorOrderProductId)) {
                    distributorOrderProductId = "";
                }
                if (empty(notes)) {
                    notes = "";
                }
                let orderLineNumber = $("#order_line_number").val();
                orderLineNumber = parseInt(orderLineNumber) + 1;
                $("#order_line_number").val(orderLineNumber);
                if ($("#location_id_" + locationId + "_order").length === 0) {
                    $("#order_wrapper").append("<div class='location-order' id='location_id_" + locationId + "_order'><h2>" +
                        locationDescription + "</h2><table class='grid-table'><tr><th>Product ID</th><th>Description</th><th>UPC</th><th>Quantity</th><th>Cost</th><th>Notes</th><th></th></table></div>");
                }
                $("#location_id_" + locationId + "_order").find("table").append("<tr class='location-order-row' data-order_line_number='" + orderLineNumber + "'><td>" +
                    productId + "</td>" + "<td>" + description + "</td>" + "<td>" + upcCode + "</td>" + "<td class='align-right'>" + quantity + "</td>" + "<td class='align-right'>" + cost + "</td>" +
                    "<td><textarea id='notes_" + orderLineNumber + "' name='notes_" + orderLineNumber + "' class='order-item-notes'>" + notes + "</textarea></td><td><span class='delete-product fas fa-trash'></span></td></tr>");
                $("#_order_form_details").append("<input type='hidden' class='location-" + locationId + " order-data order-line-number-" + orderLineNumber + "' id='product_id_" + orderLineNumber + "' name='product_id_" + orderLineNumber + "' value='" + productId + "'>");
                $("#_order_form_details").append("<input type='hidden' class='location-" + locationId + " order-data order-line-number-" + orderLineNumber + "' id='location_id_" + orderLineNumber + "' name='location_id_" + orderLineNumber + "' value='" + locationId + "'>");
                $("#_order_form_details").append("<input type='hidden' class='location-" + locationId + " order-data order-line-number-" + orderLineNumber + "' id='quantity_" + orderLineNumber + "' name='quantity_" + orderLineNumber + "' value='" + quantity + "'>");
                $("#_order_form_details").append("<input type='hidden' class='location-" + locationId + " order-data order-line-number-" + orderLineNumber + "' id='distributor_order_product_id_" + orderLineNumber + "' name='distributor_order_product_id_" + orderLineNumber + "' value='" + distributorOrderProductId + "'>");
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#location_group_id").change(function () {
                $("#location_id").find("option").unwrap("span");
                $("#suggested_location_id").find("option").unwrap("span");
                if (!empty($(this).val())) {
                    const locationGroupId = $(this).val();
                    $("#location_id").find("option[value!='']").each(function () {
                        const thisLocationGroupId = $(this).data("location_group_id");
                        if (thisLocationGroupId !== locationGroupId) {
                            $(this).wrap("<span></span>");
                        }
                    });
                    $("#suggested_location_id").find("option[value!='']").each(function () {
                        const thisLocationGroupId = $(this).data("location_group_id");
                        if (thisLocationGroupId !== locationGroupId) {
                            $(this).wrap("<span></span>");
                        }
                    });
                }
            });
            $("#suggested_location_id").change(function () {
                if (empty($(this).val())) {
                    return;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_suggested_products&suggested_location_id=" + $(this).val(), function(returnArray) {
                    if ("suggested_products" in returnArray) {
                        $("#_suggested_products").html(returnArray['suggested_products']);
                        $('#_suggested_products_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1200,
                            title: 'Suggested Products',
                            buttons: {
                                "Add To Order": function (event) {
                                    let invalidQuantity = false;
                                    $("#_suggested_products").find(".suggested-product").each(function () {
                                        const productId = $(this).data("product_id");
                                        if (empty(productId)) {
                                            return true;
                                        }
                                        const quantity = $(this).find(".product-quantity").val();
                                        if (empty(quantity)) {
                                            return true;
                                        }
                                        const locationId = $(this).find(".location").val();
                                        if (empty(locationId)) {
                                            return true;
                                        }
                                        const available = $(this).find(".location").find("option:selected").data("quantity");

                                        if (available < quantity) {
                                            $(this).find(".product-quantity").validationEngine("showPrompt", "Quantity exceeds availability", "error", "topLeft", true);
                                            invalidQuantity = true;
                                            return true;
                                        }
                                    });
                                    if (invalidQuantity) {
                                        return;
                                    }
                                    $("#_suggested_products").find(".suggested-product").each(function () {
                                        const productId = $(this).data("product_id");
                                        if (empty(productId)) {
                                            return true;
                                        }
                                        const locationId = $(this).find(".location").val();
                                        if (empty(locationId)) {
                                            return true;
                                        }
                                        const quantity = $(this).find(".product-quantity").val();
                                        if (empty(quantity)) {
                                            return true;
                                        }
                                        const cost = $(this).find(".location").find("option:selected").data("cost");
                                        const description = $(this).find(".product-description").html();
                                        const upcCode = $(this).find(".product-upc-code").html();
                                        const locationDescription = $(this).find(".location").find("option:selected").data("location");

                                        addProductToOrder(productId, description, upcCode, locationId, locationDescription, quantity, cost);
                                    });

                                    $("#_suggested_products_dialog").dialog('close');
                                },
                                Cancel: function (event) {
                                    $("#_suggested_products_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
            });

            $(document).on("click", "#saved_products,#all_saved_products", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_saved_products&all=" + ($(this).attr("id") === "all_saved_products" ? "true" : ""), function(returnArray) {
                    if ("saved_products" in returnArray) {
                        $("#_suggested_products").html(returnArray['saved_products']);
                        $('#_suggested_products_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1200,
                            title: 'Saved Products',
                            buttons: {
                                "Add To Order": function (event) {
                                    let invalidQuantity = false;
                                    $("#_suggested_products").find(".suggested-product").each(function () {
                                        const productId = $(this).data("product_id");
                                        if (empty(productId)) {
                                            return true;
                                        }
                                        const quantity = $(this).find(".product-quantity").val();
                                        if (empty(quantity)) {
                                            return true;
                                        }
                                        const locationId = $(this).find(".location").val();
                                        if (empty(locationId)) {
                                            return true;
                                        }
                                        const available = $(this).find(".location").find("option:selected").data("quantity");

                                        if (available < quantity) {
                                            $(this).find(".product-quantity").validationEngine("showPrompt", "Quantity exceeds availability", "error", "topLeft", true);
                                            invalidQuantity = true;
                                            return true;
                                        }
                                    });
                                    if (invalidQuantity) {
                                        return;
                                    }
                                    $("#_suggested_products").find(".suggested-product").each(function () {
                                        const productId = $(this).data("product_id");
                                        if (empty(productId)) {
                                            return true;
                                        }
                                        const locationId = $(this).find(".location").val();
                                        if (empty(locationId)) {
                                            return true;
                                        }
                                        const quantity = $(this).find(".product-quantity").val();
                                        if (empty(quantity)) {
                                            return true;
                                        }
                                        const distributorOrderProductId = $(this).find(".distributor-order-product-id").val();
                                        const cost = $(this).find(".location").find("option:selected").data("cost");
                                        const description = $(this).find(".product-description").html();
                                        const upcCode = $(this).find(".product-upc-code").html();
                                        const locationDescription = $(this).find(".location").find("option:selected").data("location");
                                        const notes = $(this).find(".product-notes").text();

                                        addProductToOrder(productId, description, upcCode, locationId, locationDescription, quantity, cost, notes, distributorOrderProductId);
                                    });

                                    $("#_suggested_products_dialog").dialog('close');
                                },
                                Cancel: function (event) {
                                    $("#_suggested_products_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });

            $(document).on("click", "#_submit_orders", function () {
                if ($("#_order_form_details").find(".order-data").length === 0) {
                    displayErrorMessage("No orders created");
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_orders", $("#_order_form").serialize(), function(returnArray) {
                    if ("remove_location_ids" in returnArray) {
                        for (const i in returnArray['remove_location_ids']) {
                            $("#location_id_" + returnArray['remove_location_ids'][i] + "_order").remove();
                            $(".location-" + returnArray['remove_location_ids'][i]).remove();
                        }
                    }
                    $("#notes").val("");
                    if ("results" in returnArray) {
                        $("#results").html(returnArray['results']);
                    }
                });
                return false;
            });

            $(document).on("click", "#_add_to_order", function () {
				const productId = $("#product_id").val();
				if (empty(productId)) {
					return;
				}
                $("#results").html("");
                if (!$("#_add_form").validationEngine("validate")) {
                    return false;
                }
                if ($("#product_inventory_" + $("#location_id").val()).length === 0 || $("#product_inventory_" + $("#location_id").val()).data("quantity") < $("#quantity").val()) {
                    displayErrorMessage("Product not available from this location");
                    $("#location_id").val("");
                    return false;
                }
                const cost = $("#product_inventory_" + $("#location_id").val()).data("cost");
                const locationId = $("#location_id").val();
                const quantity = $("#quantity").val();
                const description = $("#product_description").find(".product-description").html();
                const upcCode = $("#product_description").find(".product-upc").html();
                const locationDescription = $("#location_id").find("option:selected").text();

                addProductToOrder(productId, description, upcCode, locationId, locationDescription, quantity, cost);

                $("#quantity").val("");
                $("#search_text").val("");
                $("#product_list").removeClass("loaded");
                $("#product_description").html("").addClass("hidden");
                $("#product_description").html("").addClass("hidden");
                $("#distributor_list").html("");
                $("#_suggested_location_id_row").removeClass("hidden");
                return false;
            });

            $(document).on("change", ".order-item-notes", function () {
                const orderLineNumber = $(this).closest("tr").data("order_line_number");
                $("#notes_" + orderLineNumber).val($(this).val());
            });

            $("#search_text").keyup(function (event) {
                if (event.which === 13 || event.which === 3) {
                    $("#quantity").focus();
                }
            });

            $("#search_text").change(function () {
                $("#product_list").removeClass("loaded");
                $("#product_description").html("").addClass("hidden");
                $("#product_id").val("");
                $("#_suggested_location_id_row").removeClass("hidden");
                if (!empty($(this).val())) {
                    $("#_suggested_location_id_row").addClass("hidden");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=search_products&search_text=" + encodeURIComponent($(this).val()), function(returnArray) {
                        if ("product_list" in returnArray) {
                            $("#product_list").html("");
                            for (const i in returnArray['product_list']) {
                                $("#product_list").append("<div class='product-entry' data-product_id='" + returnArray['product_list'][i]['product_id'] + "'>" + returnArray['product_list'][i]['list_description'] + "</div>");
                            }
                            if ($("#product_list").find(".product-entry").length > 1) {
                                $("#product_list").addClass("loaded");
                            } else {
                                $("#product_list").find(".product-entry").trigger("click");
                            }
                            $("#quantity").focus();
                        } else {
                            $("#_suggested_location_id_row").removeClass("hidden");
                        }
                    });
                }
                return false;
            });

            $(document).on("click", ".product-entry", function () {
                $("#product_description").html($(this).html()).removeClass("hidden");
                $("#product_id").val($(this).data("product_id"));
                $("#distributor_list").html("");
                $("#product_image").html("");
                if (!empty($("#product_id").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_distributor_prices&product_id=" + $("#product_id").val(), function(returnArray) {
                        if ("image_url" in returnArray && !empty(returnArray['image_url'])) {
                            $("#product_image").html("<img alt='product image' src='" + returnArray['image_url'] + "'>");
                        }
                        if ("distributor_list" in returnArray) {
                            $("#distributor_list").html(returnArray['distributor_list']);
                        }
                    });
                }
            });

            $(document).on("click", ".delete-product", function () {
                const orderLineNumber = $(this).closest("tr").data("order_line_number");
                $(this).closest("tr").remove();
                $(".order-line-number-" + orderLineNumber).remove();
            });

            $("#quantity").change(function () {
                if (empty($("#location_id").val())) {
                    return;
                }
                if ($("#product_inventory_" + $("#location_id").val()).length === 0 || $("#product_inventory_" + $("#location_id").val()).data("quantity") < $("#quantity").val()) {
                    displayErrorMessage("Product not available from this location");
                    $("#location_id").val("");
                }
            });

            $("#location_id").change(function () {
                if (!empty($(this).val())) {
                    if ($("#product_inventory_" + $(this).val()).length === 0 || $("#product_inventory_" + $(this).val()).data("quantity") < $("#quantity").val()) {
                        displayErrorMessage("Product not available from this location");
                        $(this).val("");
                    }
                }
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$userLocations = array();
		$resultSet = executeQuery("select * from user_locations where user_id = ?", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$userLocations[] = $row['location_id'];
		}
		?>
        <div id="add_form_wrapper">
            <form id="_add_form">
                <input type="hidden" name="_add_hash" id="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>"/>

                <div id="_form_wrapper">

                    <div id="left_column">

                        <div class="basic-form-line">
                            <label for="search_text">Search Products</label>
                            <span class="help-label">Enter UPC, Manufacturer SKU, Model, Product Code or part of the description</span>
                            <input tabindex="10" type="text" id="search_text" name="search_text">
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div id="product_list"></div>

                        <input type="hidden" id="product_id" name="product_id">
                        <div id="product_description" class="hidden"></div>

                        <div class="basic-form-line">
                            <label for="quantity">Quantity</label>
                            <input tabindex="10" type="text" class="validate[required,min[1],custom[integer]]" id="quantity" name="quantity">
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                    </div> <!-- left_column -->

                    <div id="right_column">

						<?php
						$resultSet = executeQuery("select * from location_groups where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 0) {
							?>
                            <div class="basic-form-line" id="_location_group_id_row">
                                <label for="location_group_id">Location Group</label>
                                <select id="location_group_id" name="location_group_id">
                                    <option value="">[All]</option>
									<?php
									while ($row = getNextRow($resultSet)) {
										?>
                                        <option value="<?= $row['location_group_id'] ?>"><?= htmlText($row['description']) ?></option>
										<?php
									}
									?>
                                </select>
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>
						<?php } ?>

                        <div class="basic-form-line" id="_suggested_location_id_row">
                            <label for="suggested_location_id">Suggested Products for Location</label>
                            <select id="suggested_location_id" name="suggested_location_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] . " and " : "user_location = 0 and ") .
									"inactive = 0 and product_distributor_id is null and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									# only use locations to which user is assigned
									if (!empty($userLocations) && !in_array($row['location_id'], $userLocations)) {
										continue;
									}
									?>
                                    <option value="<?= $row['location_id'] ?>" data-location_group_id="<?= $row['location_group_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label">Based on inventory levels for this location</span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line">
                            <label for="location_id">Order from Location</label>
                            <select tabindex="10" class="validate[required]" id="location_id" name="location_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] . " and " : "user_location = 0 and ") .
									"client_id = ? and inactive = 0 and product_distributor_id is not null and location_id in (select location_id from location_credentials where inactive = 0) order by sort_order,description",
									$GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									if (!empty($userLocations) && !in_array($row['location_id'], $userLocations)) {
										continue;
									}
									?>
                                    <option value="<?= $row['location_id'] ?>" data-location_group_id="<?= $row['location_group_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div id="distributor_list">
                        </div>

                        <div id="product_image">
                        </div>

                    </div> <!-- right_column -->

                </div> <!-- form_wrapper -->

                <p>
                    <button id="_add_to_order">Add To Order</button>
					<?php
					$resultSet = executeQuery("select count(*) from distributor_order_products where user_id = ? and client_id = ?", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						?>
                        <button id="saved_products">Add My Saved Products</button>
					<?php } ?>
					<?php
					if (!empty($GLOBALS['gUserRow']['administrator_flag'])) {
						$resultSet = executeQuery("select count(*) from distributor_order_products where client_id = ?", $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							?>
                            <button id="all_saved_products">Add All Saved Products</button>
							<?php
						}
					}
					?>
                </p>

            </form>
        </div>

        <form id="_order_form">

            <div id="controls_wrapper">

                <p>
                    <button id="_submit_orders">Create & Submit Orders</button>
                </p>

            </div>

            <div class="basic-form-line">
                <label for="notes">Notes for these distributor orders</label>
                <textarea id="notes" name="notes"></textarea>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <input type="hidden" id="order_line_number" name="order_line_number" value="0">
            <div id="_order_form_details">
            </div>

            <div id="results">
            </div>

            <div id="order_wrapper">
            </div>
        </form>
		<?php
		echo $this->iPageData['after_form_content'];
	}

	function hiddenElements() {
		?>
        <div id="_suggested_products_dialog" class="dialog-box">
            <div id="_suggested_products">
            </div>
        </div> <!-- _send_email_dialog -->
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_form_wrapper {
                display: flex;
                margin-bottom: 40px;
            }

            #_form_wrapper > div {
                flex: 0 0 50%;
                padding-right: 40px;
            }

            #product_list {
                display: none;
                border: 1px solid rgb(220, 220, 220);
                margin: 10px 0;
                overflow: scroll;
                height: 250px;
            }

            #product_list .product-entry {
                padding: 5px 10px;
                white-space: nowrap;
                cursor: pointer;
                font-size: .7rem;
                font-weight: 400;
                border-bottom: 1px solid rgb(240, 240, 240);
            }

            #product_list .product-entry:hover {
                background-color: rgb(240, 240, 160);
            }

            #product_list.loaded {
                display: block;
            }

            #product_description {
                font-size: .9rem;
                font-weight: 400;
                margin-bottom: 20px;
                line-height: 1.25;
            }

            #distributor_list {
                font-size: .9rem;
                line-height: 1.2;
            }

            .location-order table {
                width: 80%;
                margin-top: 20px;
            }

            .delete-product {
                cursor: pointer;
            }

            #_suggested_products {
                height: 600px;
                overflow: scroll;
                margin: 20px 0;
            }

            .suggested-product {
                font-size: .8rem;
                width: 100%;
                background-color: rgb(240, 240, 240);
            }

            .suggested-product td {
                flex: 0 0 auto;
                padding: 5px;
            }

            .product-quantity-wrapper {
                text-align: right;
            }

            .product-quantity {
                width: 50px;
            }

            select.location {
                min-width: 200px;
                width: 200px;
            }

            #add_form_wrapper {
                padding-bottom: 20px;
                border-bottom: 1px solid rgb(200, 200, 200);
                margin-bottom: 30px;
            }

            #_submit_orders {
                padding: 20px 40px;
            }

            .order-item-notes {
                width: 200px;
                height: 100px;
            }

            #product_image {
                margin-top: 40px;
            }

            #product_image img {
                max-width: 300px;
                max-height: 300px;
            }
        </style>
		<?php
	}
}

$pageObject = new DistributorOrderPage();
$pageObject->displayPage();
