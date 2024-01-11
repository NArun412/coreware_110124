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

$GLOBALS['gPageCode'] = "ORDERENTRY";
require_once "shared/startup.inc";

class OrderEntryPage extends Page {

	function setup() {
		$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", "ORDER_ENTRY");
		if (empty($orderMethodId)) {
			executeQuery("insert into order_methods (client_id,order_method_code,description) values (?,'ORDER_ENTRY','Order Entry')", $GLOBALS['gClientId']);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_ffl_details":
				$fflRow = (new FFL($_GET['federal_firearms_licensee_id']))->getFFLRow();
				$returnArray['selected_ffl'] = FFL::getFFLBlock($fflRow);
				ajaxResponse($returnArray);
				break;
			case "copy_from_retail":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
                    $returnArray['error_message'] = "Invalid Contact";
					ajaxResponse($returnArray);
					break;
				}
				$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_code", "RETAIL", "contact_id = ?", $contactId);
				if (empty($shoppingCartId)) {
                    $returnArray['error_message'] = "No existing retail shopping cart found";
					ajaxResponse($returnArray);
					break;
				}
				$newShoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_code", "ORDERENTRY", "contact_id = ?", $contactId);
				if (empty($newShoppingCartId)) {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($contactId, "ORDERENTRY");
                    $shoppingCart->removeAllItems();
                    $newShoppingCartId = $shoppingCart->getShoppingCartId();
				}
				$resultSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ?", $shoppingCartId);
				while ($row = getNextRow($resultSet)) {
					$shoppingCartItemId = getFieldFromId("shopping_cart_item_id", "shopping_cart_items", "product_id", $row['product_id'], "shopping_cart_id = ?", $newShoppingCartId);
					if (!empty($shoppingCartItemId)) {
						continue;
					}
					executeQuery("update shopping_cart_items set shopping_cart_id = ? where shopping_cart_item_id = ?", $newShoppingCartId, $row['shopping_cart_item_id']);
				}
				ajaxResponse($returnArray);
				break;
			case "get_default_ffl":
				$returnArray['federal_firearms_licensee_id'] = CustomField::getCustomFieldData($_GET['contact_id'], "DEFAULT_FFL_DEALER");
				ajaxResponse($returnArray);
				break;
			case "update_shopping_cart_item_price":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Unable to get shopping cart";
					ajaxResponse($returnArray);
					break;
				}
				$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_code", "ORDERENTRY", "contact_id = ?", $contactId);
				if (empty($shoppingCartId)) {
					$returnArray['error_message'] = "Unable to get shopping cart";
					ajaxResponse($returnArray);
					break;
				}
				$shoppingCartItemId = getFieldFromId("shopping_cart_item_id", "shopping_cart_items", "shopping_cart_item_id", $_POST['shopping_cart_item_id']);
				if (empty($shoppingCartItemId)) {
					$returnArray['error_message'] = "Unable to get shopping cart item";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("update shopping_cart_items set sale_price = ? where shopping_cart_item_id = ?", $_POST['sale_price'], $shoppingCartItemId);
				ajaxResponse($returnArray);
				break;
			case "get_product_information":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
				if (empty($productId)) {
					$returnArray['error_message'] = "Invalid Product";
					ajaxResponse($returnArray);
					break;
				}
				$productRow = ProductCatalog::getCachedProductRow($productId);
				$productCatalog = new ProductCatalog();
				$productInventory = $productCatalog->getInventoryCounts(true, $productId);
				if ($productInventory[$productId] > 0) {
					$returnArray['info_message'] = $productInventory[$productId] . " in stock";
				} else {
					$returnArray['error_message'] = "Out of stock";
				}
				$salePrice = $productCatalog->getProductSalePrice($productId, array("product_information" => $productRow, "ignore_map" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
				$returnArray['sale_price'] = $salePrice['sale_price'];
				$returnArray['image_url'] = getImageFilename($productRow['image_id'], array("use_cdn" => true));
				ajaxResponse($returnArray);
				break;
			case "get_address_info":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
				} else {
					$addressRow = getRowFromId("addresses", "address_id", $_GET['address_id'], "contact_id = ?", $contactId);
					if (empty($addressRow['address_1']) || empty($addressRow['city'])) {
						$returnArray['display_shipping_address'] = "";
					} else {
						$returnArray['display_shipping_address'] = getAddressBlock($addressRow);
					}
					$returnArray['full_name'] = (empty($addressRow['full_name']) ? getDisplayName($contactId) : $addressRow['full_name']);
				}
				ajaxResponse($returnArray);
				break;
			case "get_contact_info":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
				} else {
					$contactRow = Contact::getContact($contactId);
					if (empty($contactRow['address_1']) || empty($contactRow['city'])) {
						$returnArray['display_shipping_address'] = "";
					} else {
						$returnArray['display_shipping_address'] = getAddressBlock($contactRow);
						$returnArray['city'] = $contactRow['city'];
						$returnArray['state'] = $contactRow['state'];
						$returnArray['postal_code'] = $contactRow['postal_code'];
						$returnArray['country_id'] = $contactRow['country_id'];
					}
					$validAccounts = array();
					$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and (merchant_account_id is null or merchant_account_id in (select merchant_account_id from merchant_accounts where client_id = ?))", $contactId, $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $row['payment_method_id'], "inactive = 0");
						if (empty($paymentMethodRow) && !empty($row['payment_method_id'])) {
							continue;
						}
						$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodRow['payment_method_type_id']);
						if (empty($row['account_token']) && !empty($paymentMethodTypeCode) && in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT"))) {
							continue;
						}
						$row['flat_rate'] = $paymentMethodRow['flat_rate'];
						$row['fee_percent'] = $paymentMethodRow['fee_percent'];
						$row['payment_method_type_code'] = $paymentMethodTypeCode;
						$row['payment_method_code'] = getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $row['payment_method_id']);
						$merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
						if ($merchantAccountId == $GLOBALS['gMerchantAccountId'] || empty($row['account_token'])) {
							$row['description'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . " " . str_replace("X", "", $row['account_number']);
							$row['account_label'] = (empty($row['account_label']) ? $row['account_number'] : $row['account_label']);
							$row['icon_classes'] = eCommerce::getPaymentMethodIcon($row['payment_method_code'], $row['payment_method_type_code']);
							$validAccounts[] = $row;
						}
					}
					$returnArray['accounts'] = $validAccounts;

					$addresses = array();
					$resultSet = executeQuery("select * from addresses where address_1 is not null and city is not null and contact_id = ?", $_GET['contact_id']);
					while ($row = getNextRow($resultSet)) {
						$addresses[] = array("key_value" => $row['address_id'], "city" => $row['city'], "state" => $row['state'], "postal_code" => $row['postal_code'], "country_id" => $row['country_id'],
							"description" => (empty($row['full_name']) ? getDisplayName($row['contact_id']) : $row['full_name']) . ", " . getAddressBlock($row, ", "));
					}
					$returnArray['addresses'] = $addresses;

					$resultSet = executeQuery("select * from phone_numbers where contact_id = ?", $_GET['contact_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['phone_number'] = $row['phone_number'];
					}

					$returnArray['full_name'] = getDisplayName($contactId);
				}
				ajaxResponse($returnArray);
				break;
			case "get_product_price":
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($_GET['product_id'], array("no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
				$salePrice = $salePriceInfo['sale_price'];
				if ($salePrice) {
					$returnArray['sale_price'] = $salePrice;
				}
				$returnArray['description'] = getFieldFromId("description", "products", "product_id", $_GET['product_id']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function contactPresets() {
		$contactId = $_SESSION['create_order']['contact_id'];
		if (empty($contactId)) {
			return array();
		}
		$resultSet = executeQuery("select contact_id,address_1,state,city,email_address from contacts where deleted = 0 and client_id = ? " .
			"and contact_id = ?", $GLOBALS['gClientId'], $contactId);
		$contactList = array();
		if ($row = getNextRow($resultSet)) {
			$description = getDisplayName($row['contact_id'], array("include_company" => true, "prepend_company" => true));
			if (!empty($row['address_1'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['address_1'];
			}
			if (!empty($row['state'])) {
				if (!empty($row['city'])) {
					$row['city'] .= ", ";
				}
				$row['city'] .= $row['state'];
			}
			if (!empty($row['city'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['city'];
			}
			if (!empty($row['email_address'])) {
				if (!empty($description)) {
					$description .= " &bull; ";
				}
				$description .= $row['email_address'];
			}
			$contactList[$row['contact_id']] = $description;
		}
		return $contactList;
	}

	function headerIncludes() {
		?>
        <script type="text/javascript" src="/retailstore/retail.js"></script>
		<?php
	}

	function mainContent() {
		$pagePreferences = Page::getPagePreferences();
		?>
        <p class='error-message'></p>
        <form id="_edit_form">
            <input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">
            <input type='hidden' id='checkout_version' name='checkout_version' value='order_entry'>
            <input type='hidden' id='shopping_cart_code' name='shopping_cart_code' value='ORDERENTRY'>
            <input type="hidden" id="tax_charge" name="tax_charge" class="tax-charge" value="0">
            <input type="hidden" id="shipping_charge" name="shipping_charge" class="shipping-charge" value="0">
            <input type="hidden" id="handling_charge" name="handling_charge" class="handling-charge" value="0">
            <input type="hidden" id="cart_total_quantity" name="cart_total_quantity" class="cart-total-quantity" value="0">
            <input type="hidden" id="cart_total" name="cart_total" class="cart-total" value="0">
            <input type="hidden" id="discount_amount" name="discount_amount" value="0">
            <input type="hidden" id="discount_percent" name="discount_percent" value="0">
            <input type="hidden" id="order_total" name="order_total" class="order-total" value="0">
            <input type="hidden" id="total_savings" name="total_savings" class="total-savings" value="0">
            <input type="hidden" id="order_method_code" name="order_method_code" value="ORDER_ENTRY">
            <input type="hidden" id="city" name="city" value="">
            <input type="hidden" id="state" name="state" value="">
            <input type="hidden" id="postal_code" name="postal_code" value="">
            <input type="hidden" id="country_id" name="country_id" value="">
            <input type="hidden" id="order_notes_content" name="order_notes_content" value="">
            <div id="_contact_section" data-next_section="_cart_section" class='wizard-section selected'>
                <h2>Choose Contact</h2>
				<?php
				echo createFormControl("orders", "contact_id", array("not_null" => true, "data_type" => "contact_picker"));
				?>
            </div>
            <div id="_cart_section" data-next_section="_order_details" data-previous_section="_contact_section" class='wizard-section' data-row_number="0">
                <h2>Shopping Cart</h2>
                <table id="_shopping_cart_items">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>&nbsp;</th>
                    </tr>
                    </thead>
                    <tbody id="shopping_cart_items_wrapper">
                    <tr>
                        <td class='align-center'><span id="_cart_loading" class="fad fa-spinner fa-spin"></span></td>
                    </tr>
                    </tbody>
                </table>
                <div class='clear-div'></div>
                <div id='cart_controls'>
                    <div id='add_products_wrapper'>
						<?= createFormControl("order_items", "product_id", array("form_label" => "Add Product", "data_type" => "autocomplete", "not_null" => false)) ?>
                        <p>
                            <button tabindex="10" id="add_to_cart">Add to cart</button>
                            <button tabindex="10" id="copy_from_retail">Import Customer&#x2019;s<br>Shopping Cart</button>
                        </p>
						<?= createFormControl("promotions", "promotion_code", array("not_null" => false, "data-shopping_cart_code" => "ORDERENTRY")) ?>
                        <p id='promotion_code_description'></p>
                    </div>
                    <div id="_shopping_cart_summary_wrapper">
                        <div id="_shopping_cart_summary">
                            <h3>Order Summary</h3>
                            <div class='total-savings-wrapper hidden'>Total Savings <span class="float-right total-savings"></span></div>
                            <div>Subtotal (<span class='shopping-cart-item-count'></span> items) <span class="float-right cart-total"></span></div>
                            <div class='order-summary-discount-wrapper hidden'>Discount <span class="float-right discount-amount">0.00</span></div>
                            <div>Sales Tax <span class="float-right tax-charge">0.00</span></div>
                            <div>Shipping <span class="float-right shipping-charge">0.00</span></div>
                            <div>Handling <span class="float-right handling-charge">0.00</span></div>
                            <div id="_order_total_wrapper" class="order-total-wrapper">Total <span class="float-right order-total"></span></div>
                        </div>
                        <p class='error-message'></p>
                    </div>
                </div>
            </div>
            <div id="_order_details" data-next_section="_order_payments" data-previous_section="_cart_section" class='wizard-section'>
                <h2>Order Details</h2>
                <div id="details_wrapper">
                    <div>
						<?php
						$resultSet = executeQuery("select count(*) from sources where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] > 0) {
								echo createFormControl("orders", "source_id", array("not_null" => false, "initial_value" => $pagePreferences['source_id']));
							}
						}
						echo createFormControl("orders", "purchase_order_number", array("not_null" => false));
						?>
	                    <div id="_shipping_information">
		                    <?php
						echo createFormControl("orders", "full_name", array("not_null" => false, "form_label" => "Ship To Name"));
						echo createFormControl("orders", "address_id", array("not_null" => true, "data-required" => "true", "data-conditional-required" => "!$(\"#_shipping_information\").hasClass(\"hidden\") && $(\"#address_id\").data(\"required\") == \"true\"", "choices" => array(), "show_if_empty" => true));
						?>
                        <div id="_display_shipping_address"></div>
						<?php
						echo createFormControl("orders", "business_address", array("not_null" => false));
						echo createFormControl("orders", "shipping_method_id", array("not_null" => true, "data-conditional-required" => "!$(\"#_shipping_information\").hasClass(\"hidden\")", "choices" => array(), "show_if_empty" => true));
						?>
                        <div class='basic-form-line' id="shipping_calculation_log_wrapper">
                            <label>Shipping Calculation (click here to toggle view)</label>
                            <div id="shipping_calculation_log"></div>
                        </div>
						<?php
						echo createFormControl("orders", "attention_line", array("not_null" => false));
						?>
	                    </div>
	                    <?php
						echo createFormControl("orders", "phone_number", array("not_null" => (!empty($pagePreferences['phone_number_required']))));
						echo createFormControl("orders", "gift_order", array("not_null" => false));
						echo createFormControl("orders", "gift_text", array("not_null" => false, "form_line_classes" => "hidden"));
						?>
                        <div id="ffl_selection_wrapper" class="ffl-section hidden unused-section" data-product_tag_id="<?= getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0") ?>">
                            <h2>FFL Dealer</h2>
                            <input type='hidden' id="federal_firearms_licensee_id" name="federal_firearms_licensee_id">
                            <input type='hidden' id="default_ffl_checked" name="default_ffl_checked">
                            <div id="ffl_selection">

                                <div id="selected_ffl_wrapper">
                                    <div id="selected_ffl">
                                    </div>
                                </div>

                                <div id="ffl_finder">
                                    <p><input type="text" id="ffl_search_text" value="" placeholder="Search Business Name or Postal Code"></p>
                                    <div id="ffl_dealers_wrapper" class='hidden'>
                                        <p id="ffl_dealer_count"></p>
                                        <ul id="ffl_dealers">
                                        </ul>
                                    </div> <!-- ffl_dealers_wrapper -->
                                </div>

                            </div>
                        </div>
                        <div id="_edit_charges" class="hidden">
							<?php
							echo createFormControl("orders", "tax_charge", array("column_name" => "edit_tax_charge", "not_null" => true, "data-conditional-required" => "!$(\"#_edit_charges\").hasClass(\"hidden\")"));
							echo createFormControl("orders", "shipping_charge", array("column_name" => "edit_shipping_charge", "not_null" => true, "data-conditional-required" => "!$(\"#_edit_charges\").hasClass(\"hidden\")"));
							echo createFormControl("orders", "handling_charge", array("column_name" => "edit_handling_charge", "not_null" => true, "data-conditional-required" => "!$(\"#_edit_charges\").hasClass(\"hidden\")"));
							?>
                        </div>
                        <p>
                            <button id="show_edit_charges">Edit Charges</button>
                        </p>
                    </div>
                    <div id="_details_summary_wrapper">
                        <div id="_details_summary">
                            <h3>Order Summary</h3>
                            <div class='total-savings-wrapper hidden'>Total Savings <span class="float-right total-savings"></span></div>
                            <div>Subtotal (<span class='shopping-cart-item-count'></span> items) <span class="float-right cart-total"></span></div>
                            <div class='order-summary-discount-wrapper hidden'>Discount <span class="float-right discount-amount">0.00</span></div>
                            <div>Sales Tax <span class="float-right tax-charge">0.00</span></div>
                            <div>Shipping <span class="float-right shipping-charge">0.00</span></div>
                            <div>Handling <span class="float-right handling-charge">0.00</span></div>
                            <div class="order-total-wrapper">Total <span class="float-right order-total"></span></div>
                        </div>
                        <p class='error-message'></p>
                    </div>
                </div>
            </div>
            <div id="_order_payments" data-previous_section="_order_details" class='wizard-section'>
                <h2>Order Payment</h2>
                <div id="order_payments_wrapper">
                    <div>
                        <div id="_payments_wrapper" data-payment_method_number="1" class="hidden">
                            <h3>Payments</h3>
                            <p>To create a split payment, edit the amount of the first payment and add another.</p>
                        </div>
                        <div id="_saved_accounts_wrapper">
                            <h3>Saved Accounts</h3>
                            <p>Click account to use.</p>
                        </div>
                        <p id="_add_payment_method_wrapper">
                            <button id='_add_payment_method'>Add Payment Method</button>
                        </p>
                        <p id="_balance_due_wrapper"><label>Balance Due:</label><span id="_balance_due"></span></p>
                    </div>
                    <div id="_order_payments_summary_wrapper">
                        <div id="_order_payments_summary">
                            <h3>Order Summary</h3>
                            <div class='total-savings-wrapper hidden'>Total Savings <span class="float-right total-savings"></span></div>
                            <div>Subtotal (<span class='shopping-cart-item-count'></span> items) <span class="float-right cart-total"></span></div>
                            <div class='order-summary-discount-wrapper hidden'>Discount <span class="float-right discount-amount">0.00</span></div>
                            <div>Sales Tax <span class="float-right tax-charge">0.00</span></div>
                            <div>Shipping <span class="float-right shipping-charge">0.00</span></div>
                            <div>Handling <span class="float-right handling-charge">0.00</span></div>
                            <div class="order-total-wrapper">Total <span class="float-right order-total"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div id="_button_section">
            <p>
                <button tabindex="10" id="previous_button" class='hidden'>Previous</button>
                <button tabindex="10" id="next_button">Next</button>
                <button tabindex="10" id="place_order" class='hidden'>Place Order</button>
            </p>
			<?php
			$processingOrderText = $this->getPageTextChunk("retail_store_processing_order");
			if (empty($processingOrderText)) {
				$processingOrderText = $this->getFragment("retail_store_processing_order");
			}
			if (empty($processingOrderText)) {
				$processingOrderText = "Order being processed and created. DO NOT hit the back button.";
			}
			echo "<div id='processing_order' class='hidden'>" . makeHtml($processingOrderText) . "</div>";
			?>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		$presetInformation = array();
		if (array_key_exists("create_order", $_SESSION)) {
			$presetInformation = $_SESSION['create_order'];
			unset($_SESSION['create_order']);
			saveSessionData();
		}
		?>
        <script>
	        $("body").addClass("order-entry");
            $(document).on("change", "#edit_shipping_charge", function () {
                if (isNaN($(this).val())) {
                    $("#edit_shipping_charge").val(RoundFixed($("#shipping_charge").val(), 2, true));
                } else {
                    $("#shipping_charge").val($(this).val());
                    calculateOrderTotal();
                }
            });
            $(document).on("change", "#edit_tax_charge", function () {
                if (isNaN($(this).val())) {
                    $("#edit_tax_charge").val(RoundFixed($("#tax_charge").val(), 2, true));
                } else {
                    $("#tax_charge").val($(this).val());
                    calculateOrderTotal();
                }
            });
            $(document).on("change", "#edit_handling_charge", function () {
                if (isNaN($(this).val())) {
                    $("#edit_handling_charge").val(RoundFixed($("#handling_charge").val(), 2, true));
                } else {
                    $("#handling_charge").val($(this).val());
                    calculateOrderTotal();
                }
            });
            $(document).on("click", "#show_edit_charges", function () {
                $("#edit_shipping_charge").val(RoundFixed($("#shipping_charge").val(), 2, true));
                $("#edit_tax_charge").val(RoundFixed($("#tax_charge").val(), 2, true));
                $("#edit_handling_charge").val(RoundFixed($("#handling_charge").val(), 2, true));
                $("#_edit_charges").removeClass("hidden");
                $(this).closest("p").addClass("hidden");
                return false;
            });
            $(document).on("click", "#validate_gift_card", function () {
                return false;
            });
            $(document).on("click", ".remove-item", function () {
                var productId = $(this).data("product_id");
                var shoppingCartItemId = $(this).closest(".shopping-cart-item").data("shopping_cart_item_id");
                removeProductFromShoppingCart(productId, shoppingCartItemId, "ORDERENTRY", $("#contact_id").val());
                $(this).closest(".shopping-cart-item").remove();
                $("#custom_fields_" + shoppingCartItemId).remove();
                $("#addons_" + shoppingCartItemId).remove();
                calculateShoppingCartTotal();
            });
            $(document).on("click", "#copy_from_retail", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=copy_from_retail&contact_id=" + $("#contact_id").val(), function (returnArray) {
                    loadShoppingCart();
                });
                return false;
            });
            $(document).on("click", "#place_order", function () {
                orderInProcess = true;
                $(".formFieldError").removeClass("formFieldError");
                if ($("#_balance_due").html() != "0.00") {
                    displayErrorMessage("Full Payment has not been made");
                    return;
                }
                $("#_edit_form").validationEngine("option", "validateHidden", true);
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#processing_order").removeClass("hidden");
                    $("select.disabled").prop("disabled", false);
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "/retail-store-controller?ajax=true&url_action=create_order&order_entry=true").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        $("select.disabled").prop("disabled", true);
                        if (returnArray === false || "error_message" in returnArray) {
                            orderInProcess = false;
                            $("#processing_order").addClass("hidden");
                            return;
                        }
                        if ("reload_page" in returnArray) {
                            setTimeout(function () {
                                location.reload();
                            }, 3500);
                            return;
                        }
                        if ("response" in returnArray) {
                            displayErrorMessage(returnArray['response']);
                            orderInProcess = false;
                            setTimeout(function () {
                                location.reload();
                            }, 4000);
                        } else {
                            if ("recalculate" in returnArray || "reload_cart" in returnArray) {
                                getShoppingCartItems("ORDERENTRY", $("#contact_id").val());
                            }
                            $("#processing_order").addClass("hidden");
                            orderInProcess = false;
                        }
                    });
                } else {
                    var fieldNames = "";
                    $(".field-error").each(function () {
                        let thisFieldName = $(this).find("label").text();
                        if(empty(thisFieldName) || thisFieldName == "undefined") {
                            thisFieldName = $(this).data("column_name");
                        }
                        if(empty(thisFieldName) || thisFieldName == "undefined") {
                            thisFieldName = $(this).attr("id");
                        }
                        fieldNames += (empty(fieldNames) ? "" : ",") + thisFieldName;
                    });
                    displayErrorMessage("Required information is missing: " + fieldNames);
                }
            });
            $("#shipping_method_id").change(function () {
                const recalculateTax = ($(this).val() != savedShippingMethodId) || ($("#address_id").val() != savedAddressId);

                savedShippingMethodId = $(this).val();
                savedAddressId = $("#address_id").val();
                if (recalculateTax) {
                    getTaxCharge();
                }
            });

            $("#new_address_country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_new_address_state_row").hide();
                    $("#_new_address_state_select_row").show();
                } else {
                    $("#_new_address_state_row").show();
                    $("#_new_address_state_select_row").hide();
                }
                $("#_new_address_postal_code").trigger("change");
            }).trigger("change");
            $("#new_address_state_select").change(function () {
                $("#new_address_state").val($(this).val());
            });
            $(document).on("change", "#address_id", function () {
                if (!empty($(this).val())) {
                    $("#city").val($(this).find("option:selected").data("city"));
                    $("#state").val($(this).find("option:selected").data("state"));
                    $("#postal_code").val($(this).find("option:selected").data("postal_code"));
                    $("#country_id").val($(this).find("option:selected").data("country_id"));
                }
                if (empty($(this).val())) {
                    $("#_display_shipping_address").html($(this).data("primary_address"));
                    $("#full_name").val($(this).data("full_name"));
                } else if ($(this).val() == "-1") {
                    $("#_new_address_form").clearForm();
                    $("#new_address_country_id").val("1000").trigger("change");
                    $("#new_address_contact_id").val($("#contact_id").val());
                    $('#_new_address_dialog').dialog({
                        closeOnEscape: true,
                        draggable: true,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                        width: 800,
                        title: 'Create New Address',
                        buttons: {
                            "Save": function (event) {
                                if ($("#_new_address_form").validationEngine("validate")) {
                                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_address", $("#_new_address_form").serialize(), function (returnArray) {
                                        if ("display_shipping_address" in returnArray) {
                                            $("#_display_shipping_address").html(returnArray['display_shipping_address']);
                                        }
                                        if ("full_name" in returnArray && !empty(returnArray['full_name'])) {
                                            $("#full_name").val(returnArray['full_name']);
                                        }
                                        if ("address_description" in returnArray) {
                                            $("#address_id").append($("<option></option>").attr("value", returnArray['address_description']['address_id']).text(returnArray['address_description']['dropdown_description']));
                                        }
                                        $("#_new_address_dialog").dialog('close');
                                    });
                                }
                            },
                            "Cancel": function (event) {
                                $("#_new_address_dialog").dialog('close');
                            }
                        }
                    });
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_address_info&contact_id=" + $("#contact_id").val() + "&address_id=" + $(this).val(), function (returnArray) {
                        if ("display_shipping_address" in returnArray) {
                            $("#_display_shipping_address").html(returnArray['display_shipping_address']);
                        }
                        if ("full_name" in returnArray && !empty(returnArray['full_name'])) {
                            $("#full_name").val(returnArray['full_name']);
                        }
                    });
                }
            });
            $(document).on("change", "#_shopping_cart_items .product-sale-price", function () {
                if (!$(this).val().length) {
                    return;
                }
                const shoppingCartItemId = $(this).closest(".shopping-cart-item").data("shopping_cart_item_id");
                const productSalePrice = parseFloat($(this).val());
                const originalSalePrice = parseFloat($(this).closest(".shopping-cart-item").find(".original-sale-price").html().replace(new RegExp(",", "g"), ""));
                $(this).closest(".shopping-cart-item").find(".product-savings").html(RoundFixed(originalSalePrice - productSalePrice, 2));
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_shopping_cart_item_price", { contact_id: $("#contact_id").val(), shopping_cart_item_id: shoppingCartItemId, sale_price: $(this).val() });
                calculateShoppingCartTotal();
                getTaxCharge();
            });
            $(document).on("click", ".ffl-dealer", function () {
                const fflId = $(this).data("federal_firearms_licensee_id");
                $("#federal_firearms_licensee_id").val(fflId).trigger("change");
                $("#ffl_dealers_wrapper").addClass("hidden");
            });

            $("#federal_firearms_licensee_id").change(function () {
                if (empty($(this).val())) {
                    $("#selected_ffl").html("");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_ffl_details&federal_firearms_licensee_id=" + $(this).val() + "&order_id=" + $("#primary_id").val(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#selected_ffl").html(returnArray['selected_ffl']);
                        }
                    });
                }
            });

            $("#ffl_search_text").keyup(function (event) {
                if (event.which === 13 || event.which === 3) {
                    $("#ffl_dealers_wrapper").removeClass("hidden");
                    $(this).blur();
                }
                return false;
            });

            $("#ffl_search_text").change(function () {
                getFFLDealers();
            });

            $(document).on("click", "#shipping_calculation_log_wrapper", function () {
                $(this).toggleClass("enlarged");
            });

            $(document).on("change", "#gift_card_number", function () {
                let thisElement = $(".payment-method-id-option.selected");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=check_gift_card&order_entry=true&contact_id=" + $("#contact_id").val() + "&gift_card_number=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("gift_card_information" in returnArray) {
                        $("#gift_card_information").removeClass("error-message").addClass("info-message").html(returnArray['gift_card_information']);
                        thisElement.data("maximum_payment_amount", returnArray['maximum_payment_amount']);
                    }
                    if ("gift_card_error" in returnArray) {
                        $("#gift_card_information").removeClass("info-message").addClass("error-message").html(returnArray['gift_card_error']);
                        $("#gift_card_number").val("");
                    }
                });
            });

            $(document).on("change", "#billing_country_id", function () {
                if ($(this).val() === "1000") {
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

            $(".billing-address").change(function () {
                if ($("#billing_country_id").val() !== 1000) {
                    return;
                }
                let allHaveValue = true;
                $(".billing-address").each(function () {
                    if (empty($(this).val())) {
                        allHaveValue = false;
                        return false;
                    }
                });
            });

            $(document).on("click", "#same_address", function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                    $("#billing_country_id").val("1000").trigger("change");
                } else {
                    $("#_billing_address").removeClass("hidden");
                    $("#billing_first_name").focus();
                }
            });

            $(document).on("click", ".payment-method-id-option", function () {
                if ($(this).hasClass("disabled")) {
                    return false;
                }
                $(".payment-method-id-option").removeClass("selected");
                $(this).addClass("selected");
                $("#payment_method_id").val($(this).data("payment_method_id")).trigger("change");
            });

            $(document).on("change", "#payment_method_id", function (event) {
                if (!empty($(this).val()) && !empty($("#valid_payment_methods").val())) {
                    if (!isInArray($(this).val(), $("#valid_payment_methods").val())) {
                        $(this).val("");
                        displayErrorMessage("One or more items in the cart cannot be paid for with this payment method.");
                        return;
                    }
                }
                const paymentMethodCode = $(".payment-method-id-option.selected").data("payment_method_code");
                const paymentMethodTypeCode = $(".payment-method-id-option.selected").data("payment_method_type_code");

                $(".payment-description").addClass("hidden");
                if (!empty($(this).val()) && $("#payment_description_" + $(this).val()).length > 0) {
                    $("#payment_description_" + $(this).val()).removeClass("hidden");
                }
                $(".payment-method-logo").removeClass("selected");
                $("#payment_method_logo_" + $(this).val()).addClass("selected");
                $(".payment-method-fields").addClass("hidden");
                if (!empty($(this).val())) {
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).removeClass("hidden").find("input[type=text]").first().focus();
                }
                const addressRequired = $(".payment-method-id-option.selected").data("address_required");
                $("#same_address").prop("checked", true);
                $("#_billing_address").addClass("hidden");
                $("#_billing_address").find("input,select").val("");
                $("#billing_country_id").val("1000").trigger("change");

                if (addressRequired && ($("#shipping_type_pickup").prop("checked") || $("#_delivery_location_section").hasClass("not-required") || $("#_delivery_location_section").hasClass("internal-pickup"))) {
                    $("#_same_address_row").addClass("hidden");
                    $("#same_address").prop("checked", false);
                    $("#_billing_address").removeClass("hidden");
                } else if (empty(addressRequired)) {
                    $("#_same_address_row").addClass("hidden");
                } else {
                    $("#_same_address_row").removeClass("hidden");
                }
                calculateOrderTotal();
            });

            $(document).on("click", "#_add_payment_method", function () {
                $("#_payment_method_form").clearForm();
                $(".payment-method-id-option").removeClass("selected");
                $("#payment_method_id").val($(this).data("payment_method_id")).trigger("change");
                $("#gift_card_information").removeClass("error-message").removeClass("info-message").html("");
                $(".payment-method-id-option").removeClass("disabled");
                $(".payment-method-id-option").each(function () {
                    if (empty($(this).data("maximum_payment_percentage")) && empty($(this).data("maximum_payment_amount"))) {
                        return true;
                    }
                    if ($(this).data("payment_method_type_code") === "gift_card") {
                        return true;
                    }
                    const paymentMethodId = $(this).data("payment_method_id");
                    let foundPaymentMethod = false;
                    $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                        if ($(this).find(".payment-method-id").val() === paymentMethodId) {
                            foundPaymentMethod = true;
                            return false;
                        }
                    });
                    if (foundPaymentMethod) {
                        $(this).addClass("disabled");
                    }
                });

                $('#_add_payment_method_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                    width: 800,
                    title: 'Create New Payment Method',
                    buttons: {
                        "Apply": function (event) {
                            if (empty($("#payment_method_id").val())) {
                                displayErrorMessage("No Payment Method chosen");
                                return;
                            }
                            if ($("#_payment_method_form").validationEngine("validate")) {
                                let paymentBlock = $("#_payment_method_block").html();
                                let paymentMethodNumber = $("#_payments_wrapper").data("payment_method_number");
                                $("#_payments_wrapper").data("payment_method_number", paymentMethodNumber + 1);
                                paymentBlock = paymentBlock.replace(new RegExp("%payment_method_number%", 'g'), paymentMethodNumber);
                                $("#_payments_wrapper").append(paymentBlock).removeClass("hidden");
                                $("#payment_method_" + paymentMethodNumber).tooltip();
                                $("#payment_method_" + paymentMethodNumber).find(".payment-method-image").append($(".payment-method-id-option.selected").find(".payment-method-icon").clone());
                                $("#payment_method_" + paymentMethodNumber).find(".payment-method-image").find(".payment-method-icon").removeClass("payment-method-icon");
                                $("#payment_method_" + paymentMethodNumber).find(".payment-method-account-id").val("");
                                $("#payment_method_" + paymentMethodNumber).find(".payment-method-id").val($("#payment_method_id").val());
                                $("#payment_method_" + paymentMethodNumber).find(".maximum-payment-amount").val($(".payment-method-id-option.selected").data("maximum_payment_amount"));
                                $("#payment_method_" + paymentMethodNumber).find(".maximum-payment-percentage").val($(".payment-method-id-option.selected").data("maximum_payment_percentage"));

                                let maxAmount = $("#payment_method_" + paymentMethodNumber).find(".maximum-payment-amount").val();
                                const maxPercent = $("#payment_method_" + paymentMethodNumber).find(".maximum-payment-percentage").val();
                                if (!empty(maxPercent)) {
                                    const orderTotal = parseFloat($("#order_total").val());
                                    maxAmount = Round(parseFloat(orderTotal) * (parseFloat(maxPercent) / 100), 2);
                                    $(this).find(".maximum-payment-amount").val(maxAmount);
                                }
                                const balanceDue = parseFloat($("#_balance_due").html().replace(new RegExp(",", "g"), ""));
                                const paymentAmount = (empty(maxAmount) ? balanceDue : Math.min(maxAmount, balanceDue));
                                $("#payment_method_" + paymentMethodNumber).find(".payment-method-amount").val(RoundFixed(paymentAmount, 2));
                                $("#payment_method_" + paymentMethodNumber).find(".payment-amount-value").val(RoundFixed(paymentAmount, 2, true));
                                if (empty(maxAmount)) {
                                    $("#_payments_wrapper").find(".primary-payment-method").val("0");
                                    $("#payment_method_" + paymentMethodNumber).find(".primary-payment-method").val("1");
                                } else {
                                    $("#payment_method_" + paymentMethodNumber).find(".primary-payment-method").val("0");
                                }
                                let description = $("#account_label").val();
                                $("#account_number_" + paymentMethodNumber).val($("#account_number").val());
                                if (empty(description) && !empty($("#account_number").val())) {
                                    description = "XXXX-" + $("#account_number").val().substring($("#account_number").val().length - 4);
                                }
                                $("#expiration_month_" + paymentMethodNumber).val($("#expiration_month").val());
                                $("#expiration_year_" + paymentMethodNumber).val($("#expiration_year").val());
                                $("#cvv_code_" + paymentMethodNumber).val($("#cvv_code").val());
                                $("#routing_number_" + paymentMethodNumber).val($("#routing_number").val());
                                $("#bank_account_number_" + paymentMethodNumber).val($("#bank_account_number").val());
                                if (empty(description) && !empty($("#bank_account_number").val())) {
                                    description = "XXXX-" + $("#bank_account_number").val().substring($("#bank_account_number").val().length - 4);
                                }
                                $("#gift_card_number_" + paymentMethodNumber).val($("#gift_card_number").val());
                                if (empty(description) && !empty($("#gift_card_number").val())) {
                                    description = "XXXX-" + $("#gift_card_number").val().substring($("#gift_card_number").val().length - 4);
                                }
                                $("#loan_number_" + paymentMethodNumber).val($("#loan_number").val());
                                if (empty(description) && !empty($("#loan_number").val())) {
                                    description = "XXXX-" + $("#loan_number").val().substring($("#loan_number").val().length - 4);
                                }
                                $("#lease_number_" + paymentMethodNumber).val($("#lease_number").val());
                                if (empty(description) && !empty($("#lease_number").val())) {
                                    description = "XXXX-" + $("#lease_number").val().substring($("#lease_number").val().length - 4);
                                }
                                $("#payment_method_" + paymentMethodNumber).find(".payment-method-description").html(description);
                                $("#reference_number_" + paymentMethodNumber).val($("#reference_number").val());
                                $("#payment_time_" + paymentMethodNumber).val($("#payment_time").val());
                                $("#account_label_" + paymentMethodNumber).val($("#account_label").val());
                                $("#same_address_" + paymentMethodNumber).val($("#same_address").prop("checked") ? "1" : "0");
                                if (!empty($("#billing_state_select").val()) && empty($("#billing_state").val())) {
                                    $("#billing_state").val($("#billing_state_select").val());
                                }
                                $.each([ "first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id" ], function (index, value) {
                                    $("#billing_" + value + "_" + paymentMethodNumber).val($("#billing_" + value).val());
                                });
                                calculateOrderTotal();
                                $("#_add_payment_method_dialog").dialog('close');
                            }
                        },
                        "Cancel": function (event) {
                            $("#_add_payment_method_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });

            $(document).on("click", ".payment-method-remove", function () {
                $(this).closest(".payment-method-wrapper").remove();
                calculateOrderTotal();
            });

            $(document).on("click", ".payment-method-edit", function () {
                const $amountElement = $(this).closest(".payment-method-wrapper").find(".payment-method-amount");
                $amountElement.val($amountElement.val().replace(new RegExp(",", "g"), ""));
                $amountElement.data("saved_amount", $amountElement.val());
                $amountElement.prop("readonly", false).select();
            });

            $(document).on("click", ".payment-method-amount", function (event) {
                if ($(this).prop("readonly")) {
                    $(this).closest("div.payment-method-wrapper").find(".payment-method-edit").trigger("click");
                }
            });

            $(document).on("keydown", ".payment-method-amount", function (event) {
                if (event.which === 13) {
                    $(this).blur();
                }
            });

            $(document).on("blur", ".payment-method-amount", function () {
                if ($(this).hasClass("formFieldError")) {
                    $(this).val($(this).data("saved_amount"));
                }
                let thisAmount = parseFloat($(this).val().replace(new RegExp(",", "g"), ""));
                if (isNaN(thisAmount)) {
                    thisAmount = 0;
                }
                let paymentMethodMaximum = parseFloat($(this).closest(".payment-method-wrapper").find(".maximum-payment-amount").val());
                if (empty(paymentMethodMaximum)) {
                    let orderTotal = parseFloat($("#_order_total_wrapper").find(".order-total").html());
                    let paymentMethodMaximumPercentage = parseFloat($(this).closest(".payment-method-wrapper").find(".maximum-payment-percentage").val());
                    if (!empty(paymentMethodMaximumPercentage)) {
                        paymentMethodMaximum = RoundFixed(orderTotal * paymentMethodMaximumPercentage / 100, 2, false);
                    }
                }
                if (!empty(paymentMethodMaximum) && paymentMethodMaximum > 0 && thisAmount > paymentMethodMaximum) {
                    thisAmount = paymentMethodMaximum;
                    $(this).val(RoundFixed(thisAmount, 2));
                }

                let usesMaximum = false;
                const maximumAmount = parseFloat($("#_balance_due").html()) + parseFloat($(this).data("saved_amount"));
                if (thisAmount >= maximumAmount) {
                    thisAmount = maximumAmount;
                    $(this).val(RoundFixed(thisAmount, 2));
                    usesMaximum = true;
                }
                if (usesMaximum) {
                    $("#_payments_wrapper").find(".primary-payment-method").val("0");
                    $(this).closest(".payment-method-wrapper").find(".primary-payment-method").val("1");
                } else {
                    $(this).closest(".payment-method-wrapper").find(".primary-payment-method").val("0");
                }
                $(this).closest(".payment-method-wrapper").find(".payment-amount-value").val($(this).val().replace(new RegExp(",", "g"), ""));
                $(this).prop("readonly", true);
                if (empty(thisAmount) || thisAmount <= 0) {
                    $(this).closest(".payment-method-wrapper").remove();
                }
                calculateOrderTotal();
            });

            $(document).on("click", ".saved-account-id", function () {
                let paymentBlock = $("#_payment_method_block").html();
                let paymentMethodNumber = $("#_payments_wrapper").data("payment_method_number");
                $("#_payments_wrapper").data("payment_method_number", paymentMethodNumber + 1);
                paymentBlock = paymentBlock.replace(new RegExp("%payment_method_number%", 'g'), paymentMethodNumber);
                $("#_payments_wrapper").find(".primary-payment-method").val("0");
                $("#_payments_wrapper").append(paymentBlock);
                $("#payment_method_" + paymentMethodNumber).tooltip();
                $("#payment_method_" + paymentMethodNumber).find(".payment-method-image").html($(this).find(".saved-account-id-image").html());
                $("#payment_method_" + paymentMethodNumber).find(".payment-method-description").html($(this).find(".saved-account-id-description").html());
                $("#payment_method_" + paymentMethodNumber).find(".payment-method-amount").val($("#_balance_due").html());
                $("#payment_method_" + paymentMethodNumber).find(".payment-amount-value").val($("#_balance_due").html());
                $("#payment_method_" + paymentMethodNumber).find(".payment-method-account-id").val($(this).data("account_id"));
                $("#payment_method_" + paymentMethodNumber).find(".payment-method-id").val($(this).data("payment_method_id"));
                $(this).addClass("used");
                calculateOrderTotal();
            });

            $(document).on("click", "input[type=checkbox].product-addon", function () {
                saveItemAddons();
            });
            $(document).on("change", "input[type=text].product-addon", function () {
                saveItemAddons();
            });
            $(document).on("change", "input[type=text].addon-select-quantity", function () {
                let maximumQuantity = $(this).closest(".basic-form-line").find("select.product-addon").find("option:selected").data("maximum_quantity");
                if (isNaN(maximumQuantity) || empty(maximumQuantity) || maximumQuantity <= 0) {
                    maximumQuantity = 1;
                }
                if (isNaN($(this).val() || empty($(this).val()) || $(this).val() <= 0)) {
                    $(this).val("1");
                }
                if (parseInt($(this).val()) > parseInt(maximumQuantity)) {
                    $(this).val(maximumQuantity);
                }
                saveItemAddons();
            });
            $(document).on("change", "select.product-addon", function () {
                let maximumQuantity = $(this).find("option:selected").data("maximum_quantity");
                if (isNaN(maximumQuantity) || empty(maximumQuantity) || maximumQuantity <= 0) {
                    maximumQuantity = 1;
                }
                const $quantityField = $(this).closest("basic-form-line").find(".addon-select-quantity");
                if (isNaN($quantityField.val() || empty($quantityField.val()) || $quantityField.val() <= 0)) {
                    $quantityField.val("1");
                }
                if (parseInt($quantityField.val()) > parseInt(maximumQuantity)) {
                    $quantityField.val(maximumQuantity);
                }
                saveItemAddons();
            });

            $("#shipping_method_id").change(function () {
                const recalculateTax = ($(this).val() !== savedShippingMethodId) || ($("#address_id").val() !== savedAddressId);

                savedShippingMethodId = $(this).val();
                savedAddressId = $("#address_id").val();
                if (recalculateTax) {
                    getTaxCharge();
                }
                checkFFLRequirements();
            });

            $("#new_address_country_id").change(function () {
                if ($(this).val() === "1000") {
                    $("#_new_address_state_row").hide();
                    $("#_new_address_state_select_row").show();
                } else {
                    $("#_new_address_state_row").show();
                    $("#_new_address_state_select_row").hide();
                }
                $("#_new_address_postal_code").trigger("change");
            }).trigger("change");

            $("#new_address_state_select").change(function () {
                $("#new_address_state").val($(this).val());
            });

            $(document).on("change", "#address_id", function () {
                if (!empty($(this).val())) {
                    $("#city").val($(this).find("option:selected").data("city"));
                    $("#state").val($(this).find("option:selected").data("state"));
                    $("#postal_code").val($(this).find("option:selected").data("postal_code"));
                    $("#country_id").val($(this).find("option:selected").data("country_id"));
                }
                if (empty($(this).val())) {
                    $("#_display_shipping_address").html($(this).data("primary_address"));
                    $("#full_name").val($(this).data("full_name"));
                } else if ($(this).val() === "-1") {
                    $("#_new_address_form").clearForm();
                    $("#new_address_country_id").val("1000").trigger("change");
                    $("#new_address_contact_id").val($("#contact_id").val());
                    $('#_new_address_dialog').dialog({
                        closeOnEscape: true,
                        draggable: true,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                        width: 800,
                        title: 'Create New Address',
                        buttons: {
                            "Save": function (event) {
                                if ($("#_new_address_form").validationEngine("validate")) {
                                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_address", $("#_new_address_form").serialize(), function (returnArray) {
                                        if ("display_shipping_address" in returnArray) {
                                            $("#_display_shipping_address").html(returnArray['display_shipping_address']);
                                        }
                                        if ("full_name" in returnArray && !empty(returnArray['full_name'])) {
                                            $("#full_name").val(returnArray['full_name']);
                                        }
                                        if ("address_description" in returnArray) {
                                            $("#address_id").append($("<option></option>").attr("value", returnArray['address_description']['address_id']).text(returnArray['address_description']['dropdown_description']));
                                        }
                                        $("#_new_address_dialog").dialog('close');
                                    });
                                }
                            },
                            "Cancel": function (event) {
                                $("#_new_address_dialog").dialog('close');
                            }
                        }
                    });
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_address_info&contact_id=" + $("#contact_id").val() + "&address_id=" + $(this).val(), function (returnArray) {
                        if ("display_shipping_address" in returnArray) {
                            $("#_display_shipping_address").html(returnArray['display_shipping_address']);
                        }
                        if ("full_name" in returnArray && !empty(returnArray['full_name'])) {
                            $("#full_name").val(returnArray['full_name']);
                        }
                    });
                }
            });

            $(document).on("change", "#_shopping_cart_items .product-sale-price", function () {
                if (empty($(this).val())) {
                    return;
                }
                const shoppingCartItemId = $(this).closest(".shopping-cart-item").data("shopping_cart_item_id");
                const productSalePrice = parseFloat($(this).val());
                const originalSalePrice = parseFloat($(this).closest(".shopping-cart-item").find(".original-sale-price").html().replace(new RegExp(",", "g"), ""));
                $(this).closest(".shopping-cart-item").find(".product-savings").html(RoundFixed(originalSalePrice - productSalePrice, 2));
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_shopping_cart_item_price", { contact_id: $("#contact_id").val(), shopping_cart_item_id: shoppingCartItemId, sale_price: $(this).val() });
                calculateShoppingCartTotal();
                getTaxCharge();
            });

            $("#contact_picker_new_contact").data("quick_add", true);
            $(document).on("click", "#add_to_cart", function () {
                if (!empty($("#product_id").val())) {
                    addProductToShoppingCart({ product_id: $("#product_id").val(), shopping_cart_code: "ORDERENTRY", quantity: 1, contact_id: $("#contact_id").val(), ignore_addons: true });
                }
                $("#product_id").val("");
                $("#product_id_autocomplete_text").val("");
                return false;
            });

            $(document).on("click", "#next_button", function () {
                if ($(".wizard-section.selected").validationEngine("validate")) {
                    const nextSection = $(".wizard-section.selected").data("next_section");
                    if (!empty(nextSection) && $("#" + nextSection).hasClass("wizard-section")) {
                        $(".wizard-section").removeClass("selected");
                        $("#" + nextSection).addClass("selected");
                    }
                    if (empty($(".wizard-section.selected").data("next_section"))) {
                        $("#next_button").addClass("hidden");
                        $("#place_order").removeClass("hidden");
                    } else {
                        $("#next_button").removeClass("hidden");
                        $("#place_order").addClass("hidden");
                    }
                    if (empty($(".wizard-section.selected").data("previous_section"))) {
                        $("#previous_button").addClass("hidden");
                    } else {
                        $("#previous_button").removeClass("hidden");
                    }
                    console.log(nextSection);
                    switch (nextSection) {
	                    case "_order_details":
                            loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shopping_cart_items&shopping_cart_code=ORDERENTRY&contact_id=" + $("#contact_id").val(), function (returnArray) {
                                console.log(returnArray);
                                if (returnArray['shipping_required']) {
                                    $("#_shipping_information").removeClass("hidden");
                                } else {
                                    $("#_shipping_information").addClass("hidden");
                                }
                            });
                    }
                }
                return false;
            });

            $(document).on("click", "#previous_button", function () {
                const previousSection = $(".wizard-section.selected").data("previous_section");
                if (!empty(previousSection) && $("#" + previousSection).hasClass("wizard-section")) {
                    $(".wizard-section").removeClass("selected");
                    $("#" + previousSection).addClass("selected");
                }
                if (empty($(".wizard-section.selected").data("next_section"))) {
                    $("#next_button").addClass("hidden");
                    $("#place_order").removeClass("hidden");
                } else {
                    $("#next_button").removeClass("hidden");
                    $("#place_order").addClass("hidden");
                }
                if (empty($(".wizard-section.selected").data("previous_section"))) {
                    $("#previous_button").addClass("hidden");
                } else {
                    $("#previous_button").removeClass("hidden");
                }
                return false;
            });

            $(document).on("change", ".order-item-product", function () {
                if (!empty($(this).val())) {
                    const rowNumber = $(this).closest(".order-item").data("row_number");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_information&product_id=" + $(this).val(), function (returnArray) {
                        if ("sale_price" in returnArray) {
                            $("#sale_price_" + rowNumber).val(returnArray['sale_price']);
                            $("#image_wrapper_" + rowNumber).attr("href", returnArray['image_url']);
                            $("#product_image_" + rowNumber).attr("src", returnArray['image_url']);
                            $("#quantity_" + rowNumber).focus();
                        } else {
                            $("#product_id_" + rowNumber).val("");
                            $("#product_id_" + rowNumber + "_autocomplete_text").val("");
                            $("#image_wrapper_" + rowNumber).attr("href", "/images/empty.jpg");
                            $("#product_image_" + rowNumber).attr("src", "/images/empty.jpg");
                        }
                    });
                }
            });

            $(".order-item-quantity,.order-item-sale-price").blur(function () {
                updateAmounts();
            });

            $("#contact_id").change(function () {
                getContactInfo();
                $("#promotion_code").data("contact_id", $(this).val());
                $("#default_ffl_checked").val("");
				<?php if (!empty($presetInformation['contact_id'])) { ?>
                $("#next_button").trigger("click");
				<?php } ?>
            });

            $(document).on("click", "#gift_order", function () {
                if ($(this).prop("checked")) {
                    $("#_gift_text_row").removeClass("hidden");
                } else {
                    $("#_gift_text_row").addClass("hidden");
                }
            });

            $(document).on("click", "#add_product", function () {
                addProduct();
            });

			<?php if (empty($presetInformation['contact_id'])) { ?>
            $("#_contact_id_row").find(".contact-picker").trigger("click");
			<?php } else { ?>
            $("#contact_id").val(<?= $presetInformation['contact_id'] ?>).trigger("change");
            $("#order_method_code").val("<?= $presetInformation['order_method_code'] ?>");
            $("#source_id").val("<?= $presetInformation['source_id'] ?>");
            $("#order_notes_content").val("<?= $presetInformation['order_note'] ?>");
			<?php } ?>
        </script>
		<?php
		return true;
	}

	function javascript() {
		$additionalPaymentHandlingCharge = getPreference("RETAIL_STORE_ADDITIONAL_PAYMENT_METHOD_HANDLING_CHARGE");
		if (empty($additionalPaymentHandlingCharge)) {
			$additionalPaymentHandlingCharge = 0;
		}
		?>
        <script>
            let savedShippingMethodId = false;
            let savedAddressId = false;
            let additionalPaymentHandlingCharge = <?= $additionalPaymentHandlingCharge ?>;

            function afterCheckFFLRequirements(fflRequired) {
                if (fflRequired && empty($("#default_ffl_checked").val())) {
                    $("#default_ffl_checked").val("1");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_default_ffl&contact_id=" + $("#contact_id").val(), function (returnArray) {
                        if ("federal_firearms_licensee_id" in returnArray) {
                            $("#federal_firearms_licensee_id").val(returnArray['federal_firearms_licensee_id']).trigger("change");
                        }
                    });
                }
            }

            function getFFLDealers() {
                $("#ffl_dealers").find("li").remove();
                $("#ffl_dealers").append("<li>Searching</li>");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_dealers&search_text=" + encodeURIComponent($("#ffl_search_text").val()) + "&radius=50&allow_expired=true", function (returnArray) {
                    $("#ffl_dealer_count").html(returnArray['total_dealers_count'] + " dealers found");
                    $("#ffl_dealers").find("li").remove();
                    if ("ffl_dealers" in returnArray) {
                        for (let i in returnArray['ffl_dealers']) {
                            $("#ffl_dealers").append("<li class='ffl-dealer" + (empty(returnArray['ffl_dealers'][i]['preferred']) ? "" : " preferred") + (empty(returnArray['ffl_dealers'][i]['have_license']) ? "" : " have-license") +
                                "' data-federal_firearms_licensee_id='" + returnArray['ffl_dealers'][i]['federal_firearms_licensee_id'] + "'>" + returnArray['ffl_dealers'][i]['display_name'] + "</li>");
                        }
                    }
                    $("#ffl_dealers_wrapper").removeClass("hidden");
                });
            }

            function recalculateBalance() {
                $(".saved-account-id").removeClass("used");

                let paymentCount = 0;
                let totalPayments = 0;
                $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                    paymentCount++;
                    const thisPaymentAmount = parseFloat($(this).find(".payment-amount-value").val());
                    if (isNaN(thisPaymentAmount)) {
                        $(this).remove();
                    } else {
                        totalPayments += thisPaymentAmount;
                    }
                    $(this).find(".payment-method-amount").val(RoundFixed(thisPaymentAmount, 2, false));
                    const accountId = $(this).find(".payment-method-account-id").val();
                    if (!empty(accountId)) {
                        $("#saved_account_id_" + accountId).addClass("used");
                    }
                });
                let orderTotal = parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), ""));
                const balanceDue = parseFloat(RoundFixed(orderTotal - totalPayments, 2, true));
                if (balanceDue < 0) {
                    let foundPrimary = false;
                    $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                        if (empty($(this).find(".primary-payment-method").val())) {
                            return true;
                        }
                        let thisAmount = parseFloat($(this).find(".payment-amount-value").val().replace(new RegExp(",", "g"), ""));
                        thisAmount += balanceDue;
                        if (thisAmount > 0) {
                            $(this).find(".payment-amount-value").val(RoundFixed(parseFloat(thisAmount) + parseFloat(balanceDue), 2, true));
                            foundPrimary = true;
                        }
                        return false;
                    });
                    if (!foundPrimary) {
                        $("#_payments_wrapper").find(".payment-method-wrapper").remove();
                    }
                    setTimeout(function () {
                        recalculateBalance();
                    }, 100);
                    return;
                }
                if (balanceDue > 0 && paymentCount > 0) {
                    let foundPrimary = false;
                    $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                        if (empty($(this).find(".primary-payment-method").val())) {
                            return true;
                        }
                        const thisAmount = parseFloat($(this).find(".payment-amount-value").val());
                        $(this).find(".payment-amount-value").val(RoundFixed(parseFloat(thisAmount) + parseFloat(balanceDue), 2, true));
                        foundPrimary = true;
                        return false;
                    });
                    if (foundPrimary) {
                        setTimeout(function () {
                            recalculateBalance();
                        }, 100);
                        return;
                    }
                }
                $("#_balance_due").html(RoundFixed(balanceDue, 2));

                if ($("#_payments_wrapper").find(".payment-method-wrapper").length > 0) {
                    $("#_payments_wrapper").removeClass("hidden");
                } else {
                    $("#_payments_wrapper").addClass("hidden");
                }
                if ($("#_saved_accounts_wrapper").find(".saved-account-id").not(".used").length > 0) {
                    $("#_saved_accounts_wrapper").removeClass("hidden");
                } else {
                    $("#_saved_accounts_wrapper").addClass("hidden");
                }
                if (balanceDue <= 0) {
                    $("#_add_payment_method_wrapper").addClass("hidden");
                    $("#_saved_accounts_wrapper").addClass("hidden");
                } else {
                    $("#_add_payment_method_wrapper").removeClass("hidden");
                }
            }

            function getShippingMethods() {
                shippingMethodCountryId = $("#country_id").val();
                shippingMethodState = $("#state").val();
                shippingMethodPostalCode = $("#postal_code").val();
                if (empty(savedShippingMethodId)) {
                    savedShippingMethodId = $("#shipping_method_id").val();
                }
                $("#shipping_method_id").val("").find("option[value!='']").remove();
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shipping_methods&shopping_cart_code=ORDERENTRY&contact_id=" + $("#contact_id").val(), { state: $("#state").val(), postal_code: $("#postal_code").val(), country_id: $("#country_id").val() }, function (returnArray) {
                    $("#shipping_method_id").val("").find("option[value!='']").remove();
                    let shippingMethodCount = 0;
                    let singleShippingMethodId = "";
                    if ("shipping_methods" in returnArray) {
                        for (const i in returnArray['shipping_methods']) {
                            const thisOption = $("<option></option>").data("shipping_method_code", returnArray['shipping_methods'][i]['shipping_method_code']).data("shipping_charge", returnArray['shipping_methods'][i]['shipping_charge']).data("pickup", returnArray['shipping_methods'][i]['pickup']).attr("value", returnArray['shipping_methods'][i]['key_value']).text(returnArray['shipping_methods'][i]['description']);
                            $("#shipping_method_id").append(thisOption);
                            singleShippingMethodId = returnArray['shipping_methods'][i]['key_value'];
                            if (!empty(singleShippingMethodId)) {
                                shippingMethodCount++;
                            }
                        }
                    }
                    if (shippingMethodCount === 1) {
                        $("#shipping_method_id").val(singleShippingMethodId).trigger("change");
                    }
                    if ("shipping_calculation_log" in returnArray && $("#shipping_calculation_log").length > 0) {
                        $("#shipping_calculation_log").html(returnArray['shipping_calculation_log']);
                    }
                });
            }

            function coreAfterGetShoppingCartItems() {
                $(".shopping-cart-item-decrease-quantity,.shopping-cart-item-increase-quantity").data("contact_id", $("#contact_id").val());
                $(".product-quantity").data("contact_id", $("#contact_id").val());
                getTaxCharge();
                setTimeout(function () {
                    $("#product_id_autocomplete_text").focus();
                }, 100);
            }

            function getTaxCharge() {
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_tax_charge&shopping_cart_code=ORDERENTRY", { contact_id: $("#contact_id").val(), shipping_method_id: $("#shipping_method_id").val() }, function (returnArray) {
                    if ("tax_charge" in returnArray) {
                        let taxCharge = returnArray['tax_charge'];
                        $(".tax-charge").each(function () {
                            if ($(this).is("input")) {
                                $(this).val(Round(taxCharge, 2));
                            } else {
                                $(this).html(RoundFixed(taxCharge, 2));
                            }
                        });
                        calculateOrderTotal();
                    }
                });
            }

            function calculateOrderTotal() {
                $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                    if (empty($(this).find(".payment-method-id").val()) && empty($(this).find(".payment-method-account-id").val())) {
                        $(this).remove();
                    }
                });
                let totalSavings = 0;
                $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
                    if ($(this).find(".product-savings").length > 0) {
                        const thisSavings = parseFloat($(this).find(".product-savings").html() * $(this).find(".product-quantity").val());
                        totalSavings += thisSavings;
                    }
                });
                $("#total_savings").val(totalSavings);
                if (totalSavings > 0) {
                    $(".total-savings").html(RoundFixed(totalSavings, 2));
                    $(".total-savings-wrapper").removeClass("hidden");
                } else {
                    $(".total-savings").html("0.00");
                    $(".total-savings-wrapper").addClass("hidden");
                }
                let cartTotal = parseFloat(empty($("#cart_total").val()) ? 0 : $("#cart_total").val().replace(new RegExp(",", 'ig'), ""));
                let taxChargeString = $("#tax_charge").val();
                let taxCharge = (empty(taxChargeString) ? 0 : parseFloat(taxChargeString));
                $(".tax-charge").each(function () {
                    if ($(this).is("input")) {
                        $(this).val(Round(taxCharge, 2));
                    } else {
                        $(this).html(RoundFixed(taxCharge, 2));
                    }
                });
                let shippingChargeString = $("#shipping_method_id option:selected").data("shipping_charge");
                let shippingCharge = (empty(shippingChargeString) ? 0 : parseFloat(shippingChargeString));
                if (!$("#_edit_charges").hasClass("hidden")) {
                    shippingChargeString = $("#edit_shipping_charge").val();
                    shippingCharge = (empty(shippingChargeString) ? 0 : parseFloat(shippingChargeString));
                }
                $(".shipping-charge").each(function () {
                    if ($(this).is("input")) {
                        $(this).val(shippingCharge);
                    } else {
                        $(this).html(RoundFixed(shippingCharge, 2));
                    }
                });

                const paymentMethodCount = $("#_payments_wrapper").find(".payment-method-wrapper").length;
                let handlingCharge = (paymentMethodCount > 0 ? additionalPaymentHandlingCharge * (paymentMethodCount - 1) : 0);
                if (!$("#_edit_charges").hasClass("hidden")) {
                    let handlingChargeString = $("#edit_handling_charge").val();
                    handlingCharge = (empty(handlingChargeString) ? 0 : parseFloat(handlingChargeString));
                }
                $(".handling-charge").each(function () {
                    if ($(this).is("input")) {
                        $(this).val(Round(handlingCharge, 2));
                    } else {
                        $(this).html(RoundFixed(handlingCharge, 2));
                    }
                });

                $(".payment-method-id-option").each(function () {
                    let flatRate = parseFloat($(this).data("flat_rate"));
                    if (empty(flatRate) || isNaN(flatRate)) {
                        flatRate = 0;
                    }
                    let feePercent = parseFloat($(this).data("fee_percent"));
                    if (empty(feePercent) || isNaN(feePercent)) {
                        feePercent = 0;
                    }
                    if ((flatRate === 0 || empty(flatRate) || isNaN(flatRate)) && (empty(feePercent) || feePercent === 0 || isNaN(feePercent))) {
                        return true;
                    }
                    let paymentMethodId = $(this).data("payment_method_id");
                    $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                        if ($(this).find(".payment-method-id").val() == paymentMethodId) {
                            let amount = parseFloat($(this).find(".payment-amount-value").val());
                            handlingCharge += Round(flatRate + (amount * feePercent / 100), 2);
                        }
                    });
                });
                $(".handling-charge").each(function () {
                    if ($(this).is("input")) {
                        $(this).val(handlingCharge);
                    } else {
                        $(this).html(RoundFixed(handlingCharge, 2));
                    }
                });

                let orderTotal = RoundFixed(cartTotal + taxCharge + shippingCharge + handlingCharge, 2, true);
                orderTotal = RoundFixed(orderTotal, 2, true);
                let unlimitedPaymentFound = false;
                let totalPayment = 0;
                let $unlimitedPaymentElement = false;
                $("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
                    let maxAmount = $(this).find(".maximum-payment-amount").val();
                    let maxPercent = $(this).find(".maximum-payment-percentage").val();
                    if (!empty(maxPercent)) {
                        maxAmount = Round(parseFloat(orderTotal) * (parseFloat(maxPercent) / 100), 2);
                        $(this).find(".maximum-payment-amount").val(maxAmount);
                    }
                    if (empty(maxAmount)) {
                        $(this).find(".payment-amount-value").removeData("maximum-value");
                    } else {
                        $(this).find(".payment-amount-value").data("maximum-value", maxAmount);
                        if (empty($(this).find(".payment-amount-value").val()) && parseFloat(maxAmount) < parseFloat(orderTotal)) {
                            $(this).find(".payment-amount-value").val(RoundFixed(maxAmount, 2, true));
                        }
                    }
                    let paymentAmount = $(this).find(".payment-amount-value").val();
                    if (empty(maxAmount) && $(this).find(".primary-payment-method").val() === "1") {
                        unlimitedPaymentFound = true;
                        $unlimitedPaymentElement = $(this);
                    } else if (!empty(maxAmount)) {
                        if (!empty(paymentAmount)) {
                            paymentAmount = Math.min(maxAmount, paymentAmount);
                            if (parseFloat(maxAmount) < parseFloat(paymentAmount)) {
                                $(this).find("payment-amount-value").val(RoundFixed(paymentAmount, 2, true));
                            }
                        }
                    }
                    if (!empty(paymentAmount)) {
                        totalPayment = parseFloat(paymentAmount) + parseFloat(totalPayment);
                    }
                });

                if (totalPayment < orderTotal && unlimitedPaymentFound) {
                    let paymentAmount = parseFloat($unlimitedPaymentElement.find(".payment-amount-value").val() + (orderTotal - totalPayment));
                    $unlimitedPaymentElement.find(".payment-amount-value").val(RoundFixed(paymentAmount, 2, false));
                    $unlimitedPaymentElement.find(".payment-method-amount").html(RoundFixed(paymentAmount, 2));
                }

                if (totalPayment < orderTotal) {
                    $("#_order_total_exceeded").addClass("hidden");
                    $("#_balance_remaining").html(RoundFixed(orderTotal - totalPayment, 2));
                } else {
                    $("#_order_total_exceeded").addClass("hidden");
                    if (totalPayment > orderTotal) {
                        $("#_order_total_exceeded").removeClass("hidden");
                    }
                }

                if (!unlimitedPaymentFound || $("#_payments_wrapper").find(".payment-method-wrapper").length > 1) {
                    $("#_payments_wrapper").find(".payment-amount").removeClass("hidden");
                } else {
                    $("#_payments_wrapper").find(".payment-amount").addClass("hidden");
                }

                let discountAmount = $("#discount_amount").val();
                if (empty(discountAmount) || isNaN(discountAmount)) {
                    discountAmount = 0;
                }
                let discountPercent = $("#discount_percent").val();
                if (empty(discountPercent) || isNaN(discountPercent)) {
                    discountPercent = 0;
                }
                if (discountAmount === 0 && discountPercent > 0) {
                    discountAmount = Round(cartTotal * (discountPercent / 100), 2);
                }
                if (discountAmount < 0) {
                    discountAmount = 0;
                }
                orderTotal = RoundFixed(orderTotal - discountAmount, 2, true);
                if (discountAmount > 0) {
                    $(".order-summary-discount-wrapper").removeClass("hidden");
                    $(".discount-amount").html(RoundFixed(discountAmount, 2));
                } else {
                    $(".order-summary-discount-wrapper").addClass("hidden");
                    $(".discount-amount").html("0.00");
                }

                $(".order-total").each(function () {
                    if ($(this).is("input")) {
                        $(this).val(RoundFixed(orderTotal, 2, true));
                    } else {
                        $(this).html(RoundFixed(orderTotal, 2, false));
                    }
                });
                if (parseFloat(orderTotal) > 0 || $("#shopping_cart_items_wrapper").find(".recurring-payment").length > 0) {
                    $("#_no_payment_required").addClass("hidden");
                    $("#payment_information_wrapper").removeClass("hidden");
                    $("#payment_section").removeClass("no-action-required");
                } else {
                    $("#no_payment_details").html("Cart Total: " + RoundFixed(cartTotal, 2) + "<br>Order Total: " + RoundFixed(orderTotal, 2));
                    $("#payment_information_wrapper").addClass("hidden");
                    $("#_no_payment_required").removeClass("hidden");
                    $("#payment_section").addClass("no-action-required");
                }
                setTimeout(function () {
                    recalculateBalance();
                }, 100);
                return orderTotal;
            }

            function loadShoppingCart() {
                if (!empty($("#contact_id").val())) {
                    getShoppingCartItems("ORDERENTRY", $("#contact_id").val());
                }
                return;
            }

            function massageShoppingCartItems(shoppingCartItems) {
                for (const i in shoppingCartItems) {
                    shoppingCartItems[i]['sale_price'] = shoppingCartItems[i]['sale_price'].replace(new RegExp(",", 'ig'), "");
                    if (empty(shoppingCartItems[i]['original_sale_price'])) {
                        shoppingCartItems[i]['original_sale_price'] = shoppingCartItems[i]['sale_price'];
                        shoppingCartItems[i]['savings'] = "0.00";
                    }
                }
                return shoppingCartItems;
            }

            function getContactInfo() {
                const contactId = $("#contact_id").val();
                const savedAddressId = $("#address_id").val();
                $("#address_id").val("").find("option[value!='']").remove();
                if (!empty(contactId)) {
                    $("#address_id").append($("<option></option>").attr("value", "-1").text("[Add New]"));
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info&contact_id=" + contactId, function (returnArray) {
                        $("#address_id").data("primary_address", returnArray['display_shipping_address']);
                        $("#_display_shipping_address").html(returnArray['display_shipping_address']);
                        if (empty(returnArray['display_shipping_address'])) {
                            $("#address_id").data("required", "true").find("option[value='']").text("[Select]");
                            $("#address_id").find("option[value='']").data("city", "");
                            $("#address_id").find("option[value='']").data("state", "");
                            $("#address_id").find("option[value='']").data("postal_code", "");
                            $("#address_id").find("option[value='']").data("country_id", "");
                        } else {
                            $("#address_id").data("required", "").find("option[value='']").text("[Use Primary Address]");
                            $("#city").val(returnArray['city']);
                            $("#state").val(returnArray['state']);
                            $("#postal_code").val(returnArray['postal_code']);
                            $("#country_id").val(returnArray['country_id']);
                        }
                        let foundAddress = false;
                        if ("addresses" in returnArray) {
                            for (let i in returnArray['addresses']) {
                                $("#address_id").append($("<option></option>").attr("value", returnArray['addresses'][i]['key_value']).text(returnArray['addresses'][i]['description']).data("city", returnArray['addresses'][i]['city']).data("state", returnArray['addresses'][i]['state']).data("postal_code", returnArray['addresses'][i]['postal_code']).data("country_id", returnArray['addresses'][i]['country_id']));
                                if (returnArray['addresses'][i]['key_value'] === savedAddressId) {
                                    foundAddress = true;
                                }
                            }
                        }
                        if ("phone_number" in returnArray) {
                            $("#phone_number").val(returnArray['phone_number']);
                        } else {
                            $("#phone_number").val("");
                        }
                        if ("full_name" in returnArray) {
                            $("#full_name").val(returnArray['full_name']);
                            $("#address_id").data("full_name", returnArray['full_name']);
                        } else {
                            $("#full_name").val("");
                            $("#address_id").data("full_name", "");
                        }
                        if ("accounts" in returnArray) {
                            $("#_saved_accounts_wrapper").find(".saved-account-id").remove();
                            for (const i in returnArray['accounts']) {
                                let htmlContent = $("#_account_template").html();
                                for (const j in returnArray['accounts'][i]) {
                                    htmlContent = htmlContent.replace(new RegExp("%" + j + "%", 'g'), returnArray['accounts'][i][j]);
                                }
                                $("#_saved_accounts_wrapper").append(htmlContent);
                            }
                        }
                        loadShoppingCart();
                    });
                }
            }

            function afterAddToCart() {
                loadShoppingCart();
            }

            function addProduct() {
                let rowNumber = $("#_order_items_wrapper").data("row_number");
                rowNumber++;
                let newProduct = $("#_order_item_template").html().replace(new RegExp("%row_number%", 'g'), rowNumber);
                $("#_order_items_wrapper").data("row_number", rowNumber).append(newProduct);
                $("#product_id_" + rowNumber + "_autocomplete_text").focus();
            }
        </script>
		<?php
	}

	function hiddenElements() {
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		?>
        <div id="_add_payment_method_dialog" class="dialog-box">
            <form id="_payment_method_form">
                <div id="payment_method_wrapper">
                    <div id="payment_information">
						<?php
						$paymentDescriptions = "";
						$paymentMethodArray = array();
						$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
							"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if ($row['payment_method_code'] == "CREDOVA") {
								continue;
							}
							$row['maximum_payment_amount'] = 0;
							$usePaymentMethod = true;
							switch ($row['payment_method_type_code']) {
								case "LOYALTY_POINTS":
									$loyaltySet = executeQuery("select * from loyalty_programs where client_id = ? and inactive = 0 " .
										"order by user_type_id desc,sort_order,description", $GLOBALS['gClientId']);
									if (!$loyaltyProgramRow = getNextRow($loyaltySet)) {
										$loyaltyProgramRow = array();
									}
									$loyaltyProgramPointsRow = array();

									$pointDollarValue = 0;
									if (!empty($loyaltyProgramPointsRow)) {
										$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
										$shoppingCartItems = $shoppingCart->getShoppingCartItems();

										$cartTotal = 0;
										foreach ($shoppingCartItems as $thisItem) {
											$cartTotal += ($thisItem['quantity'] * $thisItem['sale_price']);
										}

										$pointSet = executeQuery("select max(point_value) from loyalty_program_values where loyalty_program_id = ? and minimum_amount <= ?", $loyaltyProgramRow['loyalty_program_id'], $cartTotal);
										if ($pointRow = getNextRow($pointSet)) {
											$pointDollarValue = $pointRow['max(point_value)'];
										}
										$pointDollarsAvailable = ($loyaltyProgramPointsRow['point_value'] < $loyaltyProgramRow['minimum_amount'] ? 0 : floor($loyaltyProgramPointsRow['point_value'])) * $pointDollarValue;
										if (!empty($loyaltyProgramRow['maximum_amount']) && $pointDollarsAvailable > $loyaltyProgramRow['maximum_amount']) {
											$pointDollarsAvailable = $loyaltyProgramRow['maximum_amount'];
										}
										$pointDollarsAvailable = floor($pointDollarsAvailable);
									}
									if ($pointDollarsAvailable <= 0) {
										$usePaymentMethod = false;
										break;
									}
									$row['maximum_payment_amount'] = $pointDollarsAvailable;
									$row['detailed_description'] = str_replace("%points_available%", number_format($loyaltyProgramPointsRow['point_value'], 2), $row['detailed_description']);
									$row['detailed_description'] = str_replace("%point_dollars_available%", number_format($pointDollarsAvailable, 2), $row['detailed_description']);
									break;
							}
							if (!$usePaymentMethod) {
								continue;
							}
							$paymentDescriptionAddendum = "";
							if (function_exists("_localServerCartPaymentDescription")) {
								$paymentDescriptionAddendum = _localServerCartPaymentDescription($row['payment_method_id']);
							}
							if (!empty($row['detailed_description']) || !empty($paymentDescriptionAddendum)) {
								$paymentDescriptions .= "<div class='payment-description hidden' id='payment_description_" . $row['payment_method_id'] . "'>" . makeHtml($row['detailed_description']) . (empty($paymentDescriptionAddendum) ? "" : makeHtml($paymentDescriptionAddendum)) . "</div>";
							}
							$paymentMethodArray[] = $row;
						}
						?>
						<?= $paymentDescriptions ?>
                        <div class="new-account">
                            <input type='hidden' id='payment_method_id' name='payment_method_id'>
                            <h2>Select Payment Method</h2>
                            <div id="_payment_method_id_options">
								<?php
								foreach ($paymentMethodArray as $row) {
									?>
                                    <div class='payment-method-id-option' data-payment_method_id="<?= $row['payment_method_id'] ?>"
                                         data-payment_method_code="<?= $row['payment_method_code'] ?>"
                                         data-maximum_payment_percentage="<?= $row['percentage'] ?>"
                                         data-maximum_payment_amount="<?= $row['maximum_payment_amount'] ?>"
                                         data-flat_rate="<?= $row['flat_rate'] ?>"
                                         data-fee_percent="<?= $row['fee_percent'] ?>"
                                         data-address_required="<?= (empty($row['no_address_required']) ? "1" : "") ?>"
                                         data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>">
                                        <span class='payment-method-icon <?= eCommerce::getPaymentMethodIcon($row['payment_method_code'], $row['payment_method_type_code']) ?>'></span>
                                        <p><?= htmlText($row['description']) ?></p>
                                    </div>
									<?php
								}
								?>
                            </div>

                            <div class="payment-method-fields hidden" id="payment_method_credit_card">
                                <div class="basic-form-line" id="_account_number_row">
                                    <label for="account_number" class="">Card Number</label>
                                    <input tabindex="10" type="text" class="validate[required]"
                                           data-conditional-required="!$('#payment_method_credit_card').hasClass('hidden')"
                                           size="20" maxlength="20" id="account_number"
                                           name="account_number" placeholder="Account Number" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>

                                <div class="basic-form-line" id="_expiration_month_row">
                                    <label for="expiration_month" class="">Expiration Date</label>
                                    <select tabindex="10" class="expiration-date validate[required]"
                                            data-conditional-required="!$('#payment_method_credit_card').hasClass('hidden')"
                                            id="expiration_month" name="expiration_month">
                                        <option value="">[Month]</option>
										<?php
										for ($x = 1; $x <= 12; $x++) {
											?>
                                            <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <select tabindex="10" class="expiration-date validate[required]"
                                            data-conditional-required="!$('#payment_method_credit_card').hasClass('hidden')"
                                            id="expiration_year" name="expiration_year">
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
                                    <input tabindex="10" type="text"
                                           class="validate[<?= ($GLOBALS['gInternalConnection'] ? "" : "required") ?>]"
                                           data-conditional-required="!$('#payment_method_credit_card').hasClass('hidden')"
                                           size="10" maxlength="4" id="cvv_code" name="cvv_code"
                                           placeholder="CVV Code" value="">
                                    <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img
                                                id="cvv_image" src="/images/cvvnumber.jpg"
                                                alt="CVV Code"></a>
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
                            </div> <!-- payment_method_credit_card -->

                            <div class="payment-method-fields hidden" id="payment_method_bank_account">
                                <div class="basic-form-line" id="_routing_number_row">
                                    <label for="routing_number" class="">Bank Routing Number</label>
                                    <input tabindex="10" type="text"
                                           class="validate[required,custom[routingNumber]]"
                                           data-conditional-required="!$('#payment_method_bank_account').hasClass('hidden')"
                                           size="20" maxlength="20" id="routing_number"
                                           name="routing_number" placeholder="Routing Number" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>

                                <div class="basic-form-line" id="_bank_account_number_row">
                                    <label for="bank_account_number" class="">Account Number</label>
                                    <input tabindex="10" type="text" class="validate[required]"
                                           data-conditional-required="!$('#payment_method_bank_account').hasClass('hidden')"
                                           size="20" maxlength="20" id="bank_account_number"
                                           name="bank_account_number" placeholder="Bank Account Number"
                                           value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
                            </div> <!-- payment_method_bank_account -->

                            <div class="payment-method-fields hidden" id="payment_method_check">
                                <div class="basic-form-line" id="_reference_number_row">
                                    <label for="reference_number" class="">Check Number</label>
                                    <input tabindex="10" type="text" class="" size="20" maxlength="20"
                                           id="reference_number" name="reference_number"
                                           placeholder="Check Number" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>

                                <div class="basic-form-line" id="_payment_time_row">
                                    <label for="payment_time" class="">Check Date</label>
                                    <input tabindex="10" type="text" class="validate[custom[date]]" size="12" maxlength="12" id="payment_time" name="payment_time" placeholder="Check Date" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
                            </div> <!-- payment_method_check -->

                            <div class="payment-method-fields hidden" id="payment_method_gift_card">
                                <div class="basic-form-line" id="_gift_card_number_row">
                                    <label for="gift_card_number" class="">Card Number</label>
                                    <input tabindex="10" type="text" class="validate[required]"
                                           data-conditional-required="!$('#payment_method_gift_card').hasClass('hidden')"
                                           size="40" maxlength="40" id="gift_card_number"
                                           name="gift_card_number" placeholder="Card Number" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
                                <div class='basic-form-line'>
                                    <button id='validate_gift_card'>Validate & Check Balance</button>
                                </div>
                                <p id="gift_card_information"></p>
                            </div> <!-- payment_method_gift_card -->

                            <div class="payment-method-fields hidden" id="payment_method_loan">
                                <div class="basic-form-line" id="_loan_row">
                                    <label for="loan_number" class="">Loan Number</label>
                                    <input tabindex="10" type="text" class="validate[required] uppercase"
                                           data-conditional-required="!$('#payment_method_loan').hasClass('hidden')"
                                           size="20" maxlength="30" id="loan_number" name="loan_number"
                                           placeholder="Loan Number" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
                                <p class="loan-information"></p>
                            </div> <!-- payment_method_loan -->

                            <div class="payment-method-fields hidden" id="payment_method_lease">
                                <div class="basic-form-line" id="_lease_row">
                                    <label for="lease_number" class="">Lease Number</label>
                                    <input tabindex="10" type="text" class="validate[required] uppercase"
                                           data-conditional-required="!$('#payment_method_lease').hasClass('hidden')"
                                           size="20" maxlength="30" id="lease_number" name="lease_number"
                                           placeholder="Lease Number" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
                            </div> <!-- payment_method_lease -->

							<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
                                <div class="basic-form-line" id="_account_label_row">
                                    <label for="account_label" class="">Account Nickname</label>
                                    <span class="help-label">to use this account again in the future</span>
                                    <input tabindex="10" type="text" class="" size="20" maxlength="30"
                                           id="account_label" name="account_label"
                                           placeholder="Account Label" value="">
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
							<?php } ?>
                        </div> <!-- new-account -->
                    </div> <!-- payment_information -->

					<?php
					$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldByCode("ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
					?>
                    <div id="payment_address" class="new-account">
                        <div class="basic-form-line hidden" id="_same_address_row">
                            <label class=""></label>
                            <input tabindex="10" type="checkbox" <?= (empty($forceSameAddress) ? "" : "disabled='disabled'") ?> id="same_address" name="same_address" checked="checked" value="1"><label id="same_address_label" class="checkbox-label" for="same_address">Billing address is same as shipping</label>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div id="_billing_address" class="hidden">

                            <div class="basic-form-line inline-block" id="_billing_first_name_row">
                                <label for="billing_first_name">First Name</label>
                                <input tabindex="10" type="text"
                                       class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>"
                                       data-conditional-required="!$('#same_address').prop('checked')"
                                       size="25" maxlength="25" id="billing_first_name"
                                       name="billing_first_name" placeholder="First Name"
                                       value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line inline-block" id="_billing_last_name_row">
                                <label for="billing_last_name">Last Name</label>
                                <input tabindex="10" type="text"
                                       class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>"
                                       data-conditional-required="!$('#same_address').prop('checked')"
                                       size="30" maxlength="35" id="billing_last_name"
                                       name="billing_last_name" placeholder="Last Name"
                                       value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line" id="_billing_business_name_row">
                                <label for="billing_business_name">Business Name</label>
                                <input tabindex="10" type="text"
                                       class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>"
                                       data-conditional-required="!$('#same_address').prop('checked')"
                                       size="30" maxlength="35" id="billing_business_name"
                                       name="billing_business_name" placeholder="Business Name"
                                       value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line" id="_billing_address_1_row">
                                <label for="billing_address_1" <?= (!$GLOBALS['gInternalConnection'] ? ' class="required-label"' : "") ?>>Address</label>
                                <input tabindex="10" type="text" data-prefix="billing_" autocomplete='chrome-off' autocomplete='off'
                                       class="autocomplete-address billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>"
                                       data-conditional-required="!$('#same_address').prop('checked')"
                                       size="30" maxlength="60" id="billing_address_1"
                                       name="billing_address_1" placeholder="Address" value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

                            </div>

                            <div class="basic-form-line" id="_billing_city_row">
                                <label for="billing_city" <?= (!$GLOBALS['gInternalConnection'] ? ' class="required-label"' : "") ?>>City</label>
                                <input tabindex="10" type="text"
                                       class="billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>"
                                       data-conditional-required="!$('#same_address').prop('checked')"
                                       size="30" maxlength="60" id="billing_city" name="billing_city"
                                       placeholder="City" value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

                            </div>

                            <div class="basic-form-line" id="_billing_state_row">
                                <label for="billing_state" class="">State</label>
                                <input tabindex="10" type="text"
                                       class="billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>"
                                       data-conditional-required="!$('#same_address').prop('checked')"
                                       size="10" maxlength="30" id="billing_state" name="billing_state"
                                       placeholder="State" value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

                            </div>

                            <div class="basic-form-line" id="_billing_state_select_row">
                                <label for="billing_state_select" class="">State</label>
                                <select tabindex="10" id="billing_state_select" name="billing_state_select"
                                        class="validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]"
                                        data-conditional-required="!$('#same_address').prop('checked') && $('#billing_country_id').val() == 1000">
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
                                <input tabindex="10" type="text"
                                       class="validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]"
                                       size="10"
                                       maxlength="10"
                                       data-conditional-required="!$('#same_address').prop('checked') && $('#billing_country_id').val() == 1000"
                                       id="billing_postal_code" name="billing_postal_code"
                                       placeholder="Postal Code" value="">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>

                            </div>

                            <div class="basic-form-line" id="_billing_country_id_row">
                                <label for="billing_country_id" class="">Country</label>
                                <select tabindex="10" class="billing-address validate[required]"
                                        data-conditional-required="!$('#same_address').prop('checked')"
                                        id="billing_country_id" name="billing_country_id">
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
                    </div> <!-- payment_address -->
                </div> <!-- payment_method_wrapper -->
            </form>
        </div>

        <div id="_new_address_dialog" class="dialog-box">
            <form id="_new_address_form">
                <input type='hidden' id='new_address_contact_id' name='new_address_contact_id'>
				<?= createFormControl("addresses", "address_label", array("column_name" => "new_address_address_label", "not_null" => false, "help_label" => "add a label to identify this address")) ?>
				<?= createFormControl("addresses", "full_name", array("column_name" => "new_address_full_name", "not_null" => false, "help_label" => "Name of person receiving the items. Leave blank to use customer's name.")) ?>
				<?= createFormControl("addresses", "address_1", array("column_name" => "new_address_address_1", "not_null" => true, "classes" => "shipping-address autocomplete-address", "data-prefix" => "new_address_")) ?>
				<?= createFormControl("addresses", "address_2", array("column_name" => "new_address_address_2", "not_null" => false)) ?>
				<?= createFormControl("addresses", "city", array("column_name" => "new_address_city", "classes" => "shipping-address", "not_null" => true)) ?>

                <div class="basic-form-line" id="_new_address_state_select_row">
                    <label for="new_address_state_select" class="">State</label>
                    <select tabindex="10" id="new_address_state_select" name="new_address_state_select" class='shipping-address validate[required]'>
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

				<?= createFormControl("addresses", "state", array("column_name" => "new_address_state", "not_null" => true)) ?>
				<?= createFormControl("addresses", "postal_code", array("column_name" => "new_address_postal_code", "not_null" => true)) ?>
				<?= createFormControl("addresses", "country_id", array("column_name" => "new_address_country_id", "classes" => "shipping-address", "not_null" => true, "initial_value" => "1000")) ?>
            </form>
        </div>

		<?php include "contactpicker.inc" ?>

        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
		ob_start();
		?>
        <table class="hidden" id="shopping_cart_item_template">
            <tbody id="_shopping_cart_item_block">
            <tr class="shopping-cart-item %other_classes%" id="shopping_cart_item_%shopping_cart_item_id%"
                data-shopping_cart_item_id="%shopping_cart_item_id%" data-product_id="%product_id%"
                data-product_tag_ids="%product_tag_ids%">
                <td class="align-center"><a href="%image_url%" class="pretty-photo"><img alt="small image" %image_src%="%small_image_url%"></a>
                </td>
                <td class="product-description"><a href="/product-details?id=%product_id%">%description%</a><span class="out-of-stock-notice">Out Of Stock</span><span class="no-online-order-notice">In-store purchase only</span>
                    <div class='shopping-cart-item-base-cost'>Cost: %base_cost%</div>
                    <div class='shopping-cart-item-price-wrapper'>Original Price: <span class='original-sale-price'>%original_sale_price%</span></div>
                    <div class='product-savings-wrapper hidden'>Savings: <span class="product-savings">%savings%</span></div>
                </td>
                <td class="align-right"><input type='text' class="product-sale-price validate[required,custom[number],min[0]]" data-decimal-places='2' value='%sale_price%'></td>
                <td class="align-center product-quantity-wrapper">
                    <span class="fa fa-minus shopping-cart-item-decrease-quantity" data-shopping_cart_code='ORDERENTRY' data-amount="-1"></span>
                    <input class="product-quantity" data-shopping_cart_code='ORDERENTRY' data-cart_maximum="%cart_maximum%" data-cart_minimum="%cart_minimum%" value='%quantity%'>
                    <span class="fa fa-plus shopping-cart-item-increase-quantity" data-shopping_cart_code='ORDERENTRY' data-amount="1"></span>
                </td>
                <td class="align-right"><span class="dollar">$</span><span class="product-total"></span><input
                            class="cart-item-additional-charges" type="hidden"
                            name="shopping_cart_item_additional_charges_%shopping_cart_item_id%"
                            id="shopping_cart_item_additional_charges_%shopping_cart_item_id%"></td>
                <td class="controls align-center"><span class="fa fa-times remove-item" data-product_id="%product_id%"></span></td>
            </tr>
            <tr class="item-custom-fields %custom_field_classes%" id="custom_fields_%shopping_cart_item_id%">
                <td colspan="6">%custom_fields%</td>
            </tr>
            <tr class="item-addons %addon_classes%" id="addons_%shopping_cart_item_id%" data-shopping_cart_item_id="%shopping_cart_item_id%">
                <td colspan="6">%item_addons%</td>
            </tr>
			<?php if (!empty($showLocationAvailability) && !empty($customFieldId)) { ?>
                <tr class="item-availability hidden" id="availability_%shopping_cart_item_id%" data-shopping_cart_item_id="%shopping_cart_item_id%">
                    <td colspan="6"></td>
                </tr>
			<?php } ?>
            </tbody>
        </table>
		<?php
		$shoppingCartItem = ob_get_clean();
		echo makeHtml($shoppingCartItem);
	}

	function jqueryTemplates() {
		?>
        <div id="_payment_method_block">
            <div class="payment-method-wrapper" id="payment_method_%payment_method_number%">
                <div class='payment-method-image'></div>
                <div class='payment-method-description'></div>
                <div><input title='Click to Edit Amount' type='text' class='align-right payment-method-amount validate[custom[number]]' data-decimal-places='2' readonly='readonly'></div>
                <div class='payment-method-edit'><span class='fad fa-edit' title='Edit Amount'></span></div>
                <div class='payment-method-remove'><span class='fad fa-times-circle' title='Delete Payment'></span></div>
                <input type="hidden" class="payment-method-number" id="payment_method_number_%payment_method_number%" name="payment_method_number_%payment_method_number%" value="%payment_method_number%">
                <input type="hidden" class="payment-method-account-id" id="account_id_%payment_method_number%" name="account_id_%payment_method_number%">
                <input type="hidden" class="payment-method-id" id="payment_method_id_%payment_method_number%" name="payment_method_id_%payment_method_number%">
                <input type="hidden" class="maximum-payment-amount" id="maximum_payment_amount_%payment_method_number%" name="maximum_payment_amount_%payment_method_number%">
                <input type="hidden" class="maximum-payment-percentage" id="maximum_payment_percentage_%payment_method_number%" name="maximum_payment_percentage_%payment_method_number%">
                <input type="hidden" id="account_number_%payment_method_number%" name="account_number_%payment_method_number%">
                <input type="hidden" id="expiration_month_%payment_method_number%" name="expiration_month_%payment_method_number%">
                <input type="hidden" id="expiration_year_%payment_method_number%" name="expiration_year_%payment_method_number%">
                <input type="hidden" id="cvv_code_%payment_method_number%" name="cvv_code_%payment_method_number%">
                <input type="hidden" id="routing_number_%payment_method_number%" name="routing_number_%payment_method_number%">
                <input type="hidden" id="bank_account_number_%payment_method_number%" name="bank_account_number_%payment_method_number%">
                <input type="hidden" id="gift_card_number_%payment_method_number%" name="gift_card_number_%payment_method_number%">
                <input type="hidden" id="loan_number_%payment_method_number%" name="loan_number_%payment_method_number%">
                <input type="hidden" id="lease_number_%payment_method_number%" name="lease_number_%payment_method_number%">
                <input type="hidden" id="reference_number_%payment_method_number%" name="reference_number_%payment_method_number%">
                <input type="hidden" id="payment_time_%payment_method_number%" name="payment_time_%payment_method_number%">
                <input type="hidden" id="account_label_%payment_method_number%" name="account_label_%payment_method_number%">
                <input type="hidden" id="same_address_%payment_method_number%" name="same_address_%payment_method_number%">
                <input type="hidden" id="billing_first_name_%payment_method_number%" name="billing_first_name_%payment_method_number%">
                <input type="hidden" id="billing_last_name_%payment_method_number%" name="billing_last_name_%payment_method_number%">
                <input type="hidden" id="billing_business_name_%payment_method_number%" name="billing_business_name_%payment_method_number%">
                <input type="hidden" id="billing_address_1_%payment_method_number%" name="billing_address_1_%payment_method_number%">
                <input type="hidden" id="billing_address_2_%payment_method_number%" name="billing_address_2_%payment_method_number%">
                <input type="hidden" id="billing_city_%payment_method_number%" name="billing_city_%payment_method_number%">
                <input type="hidden" id="billing_state_%payment_method_number%" name="billing_state_%payment_method_number%">
                <input type="hidden" id="billing_state_select_%payment_method_number%" name="billing_state_select_%payment_method_number%">
                <input type="hidden" id="billing_postal_code_%payment_method_number%" name="billing_postal_code_%payment_method_number%">
                <input type="hidden" id="billing_country_id_%payment_method_number%" name="billing_country_id_%payment_method_number%">
                <input type="hidden" class="primary-payment-method" id="primary_payment_method_%payment_method_number%" name="primary_payment_method_%payment_method_number%" value="1">
                <input type="hidden" class="payment-amount-value" id="payment_amount_%payment_method_number%" name="payment_amount_%payment_method_number%">
            </div>
        </div>
        <div id="_account_template">
            <div class="saved-account-id" id="saved_account_id_%account_id%" data-account_id="%account_id%" title='Use this saved payment method' data-payment_method_id='%payment_method_id%'>
                <div class='saved-account-id-wrapper'>
                    <div class='saved-account-id-image'><span class="%icon_classes%"></span></div>
                    <div class='saved-account-id-description'>%account_label%</div>
                </div>
            </div>
        </div>
        <div id="_order_item_template">
            <div class='order-item' data-row_number='%row_number%'>
                <div class='details'>
                    <a id="image_wrapper_%row_number%" href='/images/empty.jpg' class='pretty-photo'><img alt='Product Image' id="product_image_%row_number%" class='product-image' src='/images/empty.jpg'></a>
					<?= createFormControl("order_items", "product_id", array("classes" => "order-item-product", "column_name" => "product_id_%row_number%", "data_type" => "autocomplete", "not_null" => true)) ?>
					<?= createFormControl("order_items", "quantity", array("classes" => "order-item-quantity", "column_name" => "quantity_%row_number%", "initial_value" => "1", "not_null" => true)) ?>
					<?= createFormControl("order_items", "sale_price", array("classes" => "order-item-sale-price", "column_name" => "sale_price_%row_number%", "not_null" => true)) ?>
                    <div class='basic-form-line'>
                        <label>Extended</label>
                        <input type='text' class='align-right validate[custom[number]] extended-price' data-decimal-places="2" readonly='readonly' id='extended_price_%row_number%' value=''>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
                </div>
                <div class="item-custom-fields">
                </div>
                <div class="item-addons">
                </div>
            </div>
        </div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .ffl-choice {
                font-size: .9rem;
            }
            .ffl-choice p {
                margin: 0;
                padding: 0;
                line-height: 1;
            }
            #ffl_dealer_count {
                font-size: 1rem;
                font-weight: 900;
            }
            .ffl-dealer {
                border-bottom: 1px solid rgb(200, 200, 200);
                padding: 5px 0;
                margin-bottom: 5px;
                cursor: pointer;
            }
            #ffl_dealers_wrapper {
                max-height: 300px;
                overflow: scroll;
                max-width: 400px;
            }
            #ffl_selection {
                display: flex;
                font-size: .8rem;
            }
            #selected_ffl_wrapper {
                margin-right: 20px;
            }

            #ffl_search_text {
                width: 400px;
                max-width: 100%;
            }

            #shipping_calculation_log {
                width: 700px;
                height: 0;
                overflow-y: scroll;
                background-color: rgb(250, 250, 250);
                padding: 0;
            }

            #shipping_calculation_log_wrapper.enlarged #shipping_calculation_log {
                height: 400px;
                border: 1px solid rgb(150, 150, 150);
                padding: 10px;
            }

            #shipping_calculation_log_wrapper label {
                cursor: pointer;
            }

            #cvv_image {
                height: 50px;
                top: 10px;
                position: absolute;
                left: 220px;
            }

            #_payment_method_id_options {
                display: flex;
                flex-wrap: wrap;
            }

            #_payment_method_id_options > div {
                width: 80px;
                height: auto;
                padding: 10px;
                border: 1px solid rgb(200, 200, 200);
                margin: 10px;
                text-align: center;
                flex: 0 0 auto;
                cursor: pointer;
            }

            #_payment_method_id_options > div.selected {
                background-color: rgb(210, 210, 255);
            }

            #_payment_method_id_options > div p {
                font-size: .5rem;
                text-align: center;
                line-height: 1;
                margin: 0;
                padding: 0;
            }

            #_payment_method_id_options > div span.payment-method-icon {
                font-size: 2rem;
                display: block;
                margin-bottom: 5px;
            }

            .payment-method-wrapper {
                display: flex;
                justify-content: space-between;
                position: relative;
                border: 1px solid rgb(220, 220, 220);
                margin: 0 0 20px;
                padding: 20px;
                max-width: 500px;
            }

            .payment-method-wrapper div {
                flex: 0 0 auto;
                margin: 0 10px;
            }

            .payment-method-wrapper div.payment-method-remove {
                font-size: 12px;
                position: absolute;
                top: 5px;
                right: 5px;
                color: rgb(192, 0, 0);
                margin: 0;
                cursor: pointer;
            }

            .payment-method-wrapper .payment-method-image span {
                font-size: 1.6rem;
            }

            .payment-method-amount {
                border: 1px solid rgb(200, 200, 200);
                padding: 4px 10px;
                width: 100px;
            }

            .payment-method-wrapper div.payment-method-edit {
                cursor: pointer;
                font-size: 1.4rem;
            }

            label {
                font-size: .8rem;
            }

            #_balance_due_wrapper {
                width: 90%;
                background-color: rgb(240, 240, 240);
                font-size: 1rem;
                text-align: left;
                padding: 10px;
                margin: 10px auto;
            }

            #_balance_due {
                font-size: 1.2rem;
                font-weight: 700;
                margin-left: 10px;
            }

            .saved-account-id {
                border: 1px solid rgb(220, 220, 220);
                padding: 20px;
                margin-bottom: 20px;
                cursor: pointer;
                max-width: 500px;
            }

            .saved-account-id.used {
                display: none;
            }

            .saved-account-id-wrapper {
                display: flex;
                justify-content: space-between;
            }

            .saved-account-id-wrapper div {
                flex: 0 0 auto;
                margin: 0 10px;
            }

            .saved-account-id span {
                font-size: 1.6rem;
            }

            #order_payments_wrapper {
                display: flex;
            }

            #_order_payments_summary_wrapper {
                flex: 0 0 30%;
            }

            #_order_payments_summary {
                max-width: 300px;
                margin: 0 0 0 auto;
            }

            #_order_payments_summary_wrapper > div {
                font-size: 1rem;
                line-height: 1.5;
            }

            #order_payments_wrapper > div:first-child {
                padding-right: 20px;
            }

            #_display_shipping_address {
                margin-bottom: 20px;
                font-size: .9rem;
                color: rgb(100, 100, 100);
                line-height: 1.5;
            }

            .product-description div {
                font-size: .7rem;
                color: rgb(150, 150, 150);
            }

            #_shopping_cart_summary > div {
                font-size: 1rem;
                line-height: 1.5;
            }

            #cart_controls {
                display: flex;
            }

            #_shopping_cart_summary_wrapper {
                flex: 0 0 30%;
            }

            #_shopping_cart_summary {
                max-width: 300px;
                margin: 0 0 0 auto;
            }

            #add_products_wrapper {
                max-width: 70%;
            }

            #details_wrapper {
                display: flex;
            }

            #_details_summary_wrapper {
                flex: 0 0 30%;
            }

            #_details_summary {
                max-width: 300px;
                margin: 0 0 0 auto;
            }

            #_details_summary > div {
                font-size: 1rem;
                line-height: 1.5;
            }

            #product_id_autocomplete_text, #promotion_code {
                max-width: 50%;
            }

            .product-sale-price {
                font-size: .9rem;
                text-align: right;
                width: 100px;
            }

            #_display_shipping_address {
                margin-bottom: 20px;
                font-size: .9rem;
                color: rgb(100, 100, 100);
                line-height: 1.5;
            }

            .product-description div {
                font-size: .7rem;
                color: rgb(150, 150, 150);
            }

            #_shopping_cart_summary > div {
                font-size: 1rem;
                line-height: 1.5;
            }

            #cart_controls {
                display: flex;
            }

            #_shopping_cart_summary_wrapper {
                flex: 0 0 300px;
            }

            #details_wrapper {
                display: flex;
            }

            #_details_summary_wrapper {
                flex: 0 0 300px;
            }

            #_details_summary > div {
                font-size: 1rem;
                line-height: 1.5;
            }

            #product_id_autocomplete_text, #promotion_code {
                max-width: 50%;
            }

            .product-sale-price {
                font-size: .9rem;
                text-align: right;
                width: 100px;
            }

            #_management_header {
                padding-bottom: 0;
            }

            h2 {
                margin: 0 0 40px 0;
            }

            .order-item {
                padding: 20px;
                border: 1px solid rgb(180, 180, 180);
                background-color: rgb(220, 230, 240);
                margin-bottom: 10px;
            }

            .order-item .details {
                display: flex;
                align-items: center;
            }

            .product-image {
                max-width: 100px;
            }

            .wizard-section {
                display: none;
                min-height: 250px;
                border: 1px solid rgb(200, 200, 200);
                background-color: rgb(240, 240, 240);
                padding: 40px;
                margin-bottom: 40px;
            }

            .wizard-section.selected {
                display: block;
            }

            table#_shopping_cart_items {
                width: 100%;
                background-color: rgb(255, 255, 255);
                margin: 20px auto;
            }

            table#_shopping_cart_items thead th {
                background: rgb(180, 180, 180);
                font-size: .9rem;
                font-weight: 400;
                line-height: 1.2;
                color: rgb(0, 0, 0);
                border: .5px solid #000;
            }

            table#_shopping_cart_items tr {
                border: .5px solid #ccc;
            }

            table#_shopping_cart_items td {
                padding: 20px;
                vertical-align: middle;
                white-space: nowrap;
            }

            table#_shopping_cart_items tr.item-availability {
                font-size: 1.2rem;
                border-top: 1px solid rgb(255, 255, 255);
            }

            table#_shopping_cart_items tr.item-availability td {
                padding-top: 0;
            }

            table#_shopping_cart_items td.product-description {
                white-space: normal;
                line-height: 1.2;
            }

            table#_shopping_cart_items td img {
                max-width: 250px;
                max-height: 80px;
            }

            table#_shopping_cart_items td.controls span {
                font-size: 1.4rem;
                color: rgb(0, 0, 0);
                display: inline-block;
                margin: 0 10px;
                cursor: pointer;
            }

            table#_shopping_cart_items td.controls span:hover {
                color: rgb(180, 190, 200);
            }

            table#_shopping_cart_items td.product-quantity-wrapper span {
                cursor: pointer;
                margin: 0 10px;
            }

            span.product-quantity {
                font-size: 1.2rem;
                padding: 5px 20px;
                border-radius: 3px;
                background-color: rgb(210, 210, 210);
            }

            input.product-quantity {
                font-size: 1.2rem;
                padding: 4px 10px;
                width: 55px;
                border-radius: 3px;
                background-color: rgb(210, 210, 210);
                height: auto;
                text-align: center;
                border: none;
            }

            .out-of-stock-notice {
                display: none;
            }

            .out-of-stock .out-of-stock-notice {
                display: block;
                color: rgb(192, 0, 0);
                font-weight: 900;
                margin-top: 5px;
            }

            .no-online-order-notice {
                display: none;
            }

            .no-online-order .no-online-order-notice {
                display: block;
                color: rgb(192, 0, 0);
                font-weight: 900;
                margin-top: 5px;
            }

            tr.item-custom-fields {
                display: none;
            }

            tr.item-custom-fields.active-fields {
                display: table-row;
            }

            .addon-select-quantity {
                width: 50px;
            }

            tr.item-addons {
                display: none;
            }

            tr.item-addons.active-fields {
                display: table-row;
            }

            #contact_picker_no_contact, #contact_picker_contact_type_id {
                display: none;
            }

        </style>
		<?php
	}
}

$pageObject = new OrderEntryPage();
$pageObject->displayPage();
