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

$GLOBALS['gPageCode'] = "RETAILSTORESHOPPINGCART";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class ShoppingCartPage extends Page {

	var $iFflRequiredProductTagId = "";
	var $iPrimaryPaymentMethodNumber = 99;
	var $iUseRecaptchaV2 = false;

	function setup() {
		$sourceId = "";
		if (array_key_exists("aid", $_GET)) {
			$sourceId = getFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['aid']));
		}
		if (array_key_exists("source", $_GET)) {
			$sourceId = getFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['source']));
		}
		if (!empty($sourceId)) {
			setCoreCookie("source_id", $sourceId, 4);
		}
		if (!empty($_GET['shopping_cart_code'])) {
			$_POST['shopping_cart_code'] = $_GET['shopping_cart_code'];
		}
		if (empty($_POST['shopping_cart_code'])) {
			$_POST['shopping_cart_code'] = "RETAIL";
		}
		$shoppingCart = ShoppingCart::getShoppingCart($_POST['shopping_cart_code']);
		if (!empty($_GET['product_id'])) {
			$productIds = explode("|", $_GET['product_id']);
			foreach ($productIds as $productId) {
				$productId = getFieldFromId("product_id", "products", "product_id", $productId, "inactive = 0" . (empty($GLOBALS['gInternalConnection']) ? " and internal_use_only = 0" : ""));
				if (!empty($productId)) {
					$originalQuantity = $shoppingCart->getProductQuantity($productId);
					if ($originalQuantity == 0) {
						$quantity = 1;
						$shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => true));
					}
				}
			}
		}
		if (!empty($_GET['quote_id'])) {
			executeQuery("update product_map_overrides set inactive = 0 where override_code is null and product_map_override_id = ? and shopping_cart_id = ?", $_GET['quote_id'], $shoppingCart->getShoppingCartId());
			$productId = getFieldFromId("product_id", "product_map_overrides", "product_map_override_id", $_GET['quote_id'], "override_code is null");
			$productId = getFieldFromId("product_id", "products", "product_id", $productId, "inactive = 0" . (empty($GLOBALS['gInternalConnection']) ? " and internal_use_only = 0" : ""));
			$originalQuantity = $shoppingCart->getProductQuantity($productId);
			if ($originalQuantity == 0) {
				$quantity = 1;
				$shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => true));
			}
		}
		$this->iFflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
		$this->iUseRecaptchaV2 = !$GLOBALS['gUserRow']['administrator_flag'] && !empty(getPreference("ORDER_RECAPTCHA_V2_SITE_KEY")) && !empty(getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY"));
	}

	function headerIncludes() {
		?>
		<script src="<?= autoVersion('/js/jsignature/jSignature.js') ?>"></script>
		<script src="<?= autoVersion('/js/jsignature/jSignature.CompressorSVG.js') ?>"></script>
		<script src="<?= autoVersion('/js/jsignature/jSignature.UndoButton.js') ?>"></script>
		<script src="<?= autoVersion('/js/jsignature/signhere/jSignature.SignHere.js') ?>"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js" integrity="sha512-KaIyHb30iXTXfGyI9cyKFUIRSSuekJt6/vqXtyQKhQP6ozZEGY8nOtRS6fExqE4+RbYHus2yGyYg1BrqxzV6YA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
		<?php
		if ($this->iUseRecaptchaV2) {
			?>
			<script src="https://www.google.com/recaptcha/api.js" async defer></script>
			<?php
		}
	}

	public function mainContent() {
		$eCommerce = eCommerce::getEcommerceInstance();
		$_SESSION['form_displayed'] = date("U");
		saveSessionData();
		$loginUserName = $_COOKIE["LOGIN_USER_NAME"];
		if (empty($_POST['shopping_cart_code'])) {
			$_POST['shopping_cart_code'] = "RETAIL";
		}
		$shoppingCart = ShoppingCart::getShoppingCart($_POST['shopping_cart_code']);
		if ($GLOBALS['gLoggedIn']) {
			$contactId = $GLOBALS['gUserRow']['contact_id'];
		} else {
			$contactId = $shoppingCart->getContact();
		}
		$contactRow = getRowFromId("contacts", "contact_id", $contactId);

		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$resultSet = executeQuery("select * from designations where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and designation_id in (select designation_id from designation_group_links where " .
			"designation_group_id = (select designation_group_id from designation_groups where designation_group_code = 'PRODUCT_ORDER' and inactive = 0 and client_id = ?)) order by rand()", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
		$designationRow = getNextRow($resultSet);

		$fflNumber = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "FFL_NUMBER");
		if (!empty($fflNumber)) {
			echo "<input type='hidden' id='user_ffl_number' value='" . $fflNumber . "'>";
		}
		$crLicenseOnFileCategoryId = getFieldFromId("category_id", "categories", "category_code", "CR_LICENSE_ON_FILE");
		if (!empty($crLicenseOnFileCategoryId)) {
			$crLicenseOnFileContactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $crLicenseOnFileCategoryId, "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
			if (!empty($crLicenseOnFileContactCategoryId)) {
				$crLicenseExpirationDate = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "CR_LICENSE_ON_FILE_EXPIRATION_DATE");
				if (!empty($crLicenseExpirationDate) && date("Y-m-d", strtotime($crLicenseExpirationDate)) > date("Y-m-d")) {
					echo "<input type='hidden' id='user_has_cr_license' value='" . $crLicenseExpirationDate . "'>";
				}
			}
		}

		?>
		<h1 id='shopping_cart_header'>Shopping Cart</h1>
		<?php if ($GLOBALS['gUserRow']['administrator_flag'] && !$GLOBALS['gDevelopmentServer']) { ?>
			<h2 class='red-text'>As an administrator, MAKE SURE this purchase is for yourself. Do not make purchases for others with your account.</h2>
		<?php } ?>
		<?= $this->iPageData['content'] ?>
		<?php
		$class3Fragment = $this->getFragment("CLASS_3_CHECKOUT_NOTICE");
		if (!empty($class3Fragment)) {
			?>
			<div class="class-3-notice hidden">
				<?= makeHtml($class3Fragment) ?>
			</div>
			<?php
		}
		?>
		<div id="_shopping_cart_wrapper">
			<form id="_checkout_form" enctype="multipart/form-data" method='post'>
				<input type='hidden' id='checkout_version' name='checkout_version' value='2'>
				<input type="hidden" id="tax_charge" name="tax_charge" class="tax-charge" value="0">
				<input type="hidden" id="shipping_charge" name="shipping_charge" class="shipping-charge" value="0">
				<input type="hidden" id="handling_charge" name="handling_charge" class="handling-charge" value="0">
				<input type="hidden" id="cart_total_quantity" name="cart_total_quantity" class="cart-total-quantity" value="0">
				<input type="hidden" id="cart_total" name="cart_total" class="cart-total" value="0">
				<input type="hidden" id="discount_amount" name="discount_amount" value="0">
				<input type="hidden" id="discount_percent" name="discount_percent" value="0">
				<input type="hidden" id="order_total" name="order_total" class="order-total" value="0">
				<input type="hidden" id="total_savings" name="total_savings" class="total-savings" value="0">
				<input type="hidden" id="source_id" name="source_id" value="">

				<div id="_shopping_cart_contents">
					<div id="_shopping_cart_items_wrapper">
						<p class='shopping-cart-item-count-wrapper'><span class='shopping-cart-item-count'></span> Items</p>
						<div id="_shopping_cart_items">
							<div id="shopping_cart_items_wrapper">
								<p class='align-center'><span id="_cart_loading" class="fad fa-spinner fa-spin"></span></p>
							</div>
						</div>
						<div id='_order_upsell_products'></div>
						<?php
						$sectionText = $this->getPageTextChunk("retail_store_shopping_cart_under_items");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_shopping_cart_under_items");
						}
						echo $sectionText;
						?>
					</div>
					<div id="_shopping_cart_summary_wrapper">
						<div id="_shopping_cart_summary">
							<h3>Order Summary</h3>
							<p class='shipping-section' id='delivery_method_display'></p>
							<div id="_summary_cart_contents_wrapper">
								<div id="_summary_cart_contents"></div>
								<div id="_summary_cart_order_upsell_products"></div>
								<p class='align-center'>
									<button id="edit_cart">Edit Cart</button>
								</p>
							</div>
							<div id='_selected_payment_method_wrapper' class='hidden'>Payment Method(s) <span class="float-right text-right"></span></div>
							<div class='shopping-cart-item-count-wrapper'>Subtotal (<span class='shopping-cart-item-count'></span> items) <span class="float-right cart-total"></span></div>
							<div id='order_summary_discount_wrapper' class='hidden'>Discount <span class="float-right discount-amount">0.00</span></div>
							<?php
							$resultSet = executeQuery("select count(*) from promotions where client_id = ? and inactive = 0 and " .
								"start_date <= current_date and (expiration_date is null or expiration_date >= current_date)", $GLOBALS['gClientId']);
							$promotionCount = 0;
							if ($row = getNextRow($resultSet)) {
								$promotionCount = $row['count(*)'];
							}
							if ($promotionCount == 0) {
								$resultSet = executeQuery("select count(*) from product_map_overrides where shopping_cart_id in (select shopping_cart_id from shopping_carts where client_id = ?)", $GLOBALS['gClientId']);
								if ($row = getNextRow($resultSet)) {
									$promotionCount = $row['count(*)'];
								}
							}
							if ($promotionCount > 0) {
								?>
								<div id="promotion_code_wrapper" class='cart-element'>
									<div id='_promotion_message'>Add a promo code</div>
									<div id='_promotion_code_wrapper' class='hidden'>
										<input type='hidden' tabindex='10' id='promotion_id' name='promotion_id'>
										<input type="text" tabindex="10" id="promotion_code" name="promotion_code" placeholder="Promo Code" autocomplete='chrome-off' autocomplete='off'>
										<button tabindex='10' id='apply_promo' title="Apply Promotion Code" class='tooltip-element'><span class='fas fa-check'></span></button>
									</div>
									<div id='_promotion_applied_message' class='hidden cart-element'><span class='promotion-code' id='applied_promotion_code'></span> applied. <a href='#' id='show_promotion_code_details'>Details</a><span class='fas fa-times'></span></div>
								</div>
								<div id="add_promotion_code" class='checkout-element hidden'>Have a promotion code? Click 'Edit Cart' to add it.</div>
								<div id="added_promotion_code" class='checkout-element hidden'><span class='promotion-code'></span> applied. Click 'Edit Cart' to change.</div>
							<?php } ?>
							<?php if (!empty($designationRow)) { ?>
								<div class='hide-if-zero'>Donation <span class="float-right donation-amount hide-if-zero-value">0.00</span></div>
							<?php } ?>
							<div>Estimated Sales Tax <span class="float-right tax-charge">0.00</span></div>
							<div>Shipping <span class="float-right shipping-charge">0.00</span></div>
							<div class='hide-if-zero'>Handling <span class="float-right hide-if-zero-value handling-charge">0.00</span></div>
							<div id='_total_savings_wrapper' class='hidden'>Total Savings <span class="float-right total-savings"></span></div>
							<div id="_order_total_wrapper">Total <span class="float-right order-total"></span></div>

							<?php
							$credovaCredentials = getCredovaCredentials();
							$credovaUserName = $credovaCredentials['username'];
							$credovaPassword = $credovaCredentials['password'];
							$credovaTest = $credovaCredentials['test_environment'];
							$credovaPaymentMethodId = $credovaCredentials['credova_payment_method_id'];
							if (!empty($credovaUserName)) {
								?>
								<div class="<?php echo($GLOBALS['gLoggedIn'] ? "" : "create-account ") ?>credova-button checkout-credova-button" data-type="popup"></div>
								<?php
							}
							?>
							<div id="checkout_button_wrapper" class='cart-element'>
								<button tabindex="10" id="continue_checkout" class="hidden">Start Checkout</button>
							</div>
							<?php
							if ($GLOBALS['gLoggedIn']) {
								$quickCheckoutEnabled = true;
								$quickCheckoutDetails = "";
								$resultSet = executeQuery("select * from accounts where default_payment_method = 1 and inactive = 0 and contact_id = ? order by account_id desc", $GLOBALS['gUserRow']['contact_id']);
								if ($accountRow = getNextRow($resultSet)) {
									$quickCheckoutDetails .= "<p>" . (empty($accountRow['account_label']) ? "Default Payment Method" : htmlText($accountRow['account_label'])) . (empty($accountRow['account_number']) ? "" : " (" . substr($accountRow['account_number'], -4) . ")") . " will be billed <span id='quick_checkout_total'>%order_total%</span>.</p>";
								} else {
									$quickCheckoutEnabled = false;
								}
								if ($resultSet['row_count'] > 1) {
									executeQuery("update accounts set default_payment_method = 0 where contact_id = ? and account_id <> ?", $GLOBALS['gUserRow']['contact_id'], $accountRow['account_id']);
								}
								if (!$GLOBALS['gUserRow']['administrator_flag']) {
									$orderId = getFieldFromId("order_id", "orders", "contact_id", $GLOBALS['gUserRow']['contact_id'], "deleted = 0");
									if (empty($orderId)) {
										$quickCheckoutEnabled = false;
									}
								}
								$fflRequiredProducts = false;
								$nonFflRequiredProducts = false;
								if ($quickCheckoutEnabled) {
									if (empty($this->iFflRequiredProductTagId)) {
										$nonFflRequiredProducts = true;
									} else {
										$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
										$shoppingCartItems = $shoppingCart->getShoppingCartItems();
										foreach ($shoppingCartItems as $thisShoppingCartItem) {
											$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisShoppingCartItem['product_id'], "product_tag_id = ?", $this->iFflRequiredProductTagId);
											if (!empty($productTagLinkId)) {
												$fflRequiredProducts = true;
											} else {
												$nonFflRequiredProducts = true;
											}
										}
										if ($fflRequiredProducts) {
											$fflRow = (new FFL(array("federal_firearms_licensee_id" => CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER"), "only_if_valid" => true)))->getFFLRow();
											if (empty($fflRow)) {
												$quickCheckoutEnabled = false;
											} else {
												$quickCheckoutDetails .= "<p>FFL Products will be sent to:<br><span id='ffl_products_shipping_address'>" . htmlText($fflRow['business_name']) . "<br>" . $fflRow['address_1'] . "<br>" . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'] . "</span></p>";
											}
										}
									}
								}
								if ($quickCheckoutEnabled && $nonFflRequiredProducts) {
									$resultSet = executeQuery("select * from addresses where default_shipping_address = 1 and address_1 is not null and city is not null and inactive = 0 and contact_id = ? order by address_id desc", $GLOBALS['gUserRow']['contact_id']);
									if ($addressRow = getNextRow($resultSet)) {
										if (empty($addressRow['full_name'])) {
											$addressRow['full_name'] = getDisplayName();
										}
										$quickCheckoutDetails .= "<p>" . ($fflRequiredProducts ? "Non-FFL Products will be shipped to:" : "Ship order to:") . "<br><span id='non_ffl_products_shipping_address'>" . htmlText($addressRow['full_name']) . "<br>" . htmlText($addressRow['address_1']) . "<br>" . (empty($addressRow['address_2']) ? "" : htmlText($addressRow['address_2']) . "<br>") . htmlText($addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code']) . "</span></p>";
									} elseif (!empty($GLOBALS['gUserRow']['address_1']) && !empty($GLOBALS['gUserRow']['city'])) {
										$quickCheckoutDetails .= "<p>" . ($fflRequiredProducts ? "Non-FFL Products will be shipped to:" : "Ship order to:") . "<br><span id='non_ffl_products_shipping_address'>" . htmlText(getDisplayName()) . "<br>" . htmlText($GLOBALS['gUserRow']['address_1']) . "<br>" . (empty($GLOBALS['gUserRow']['address_2']) ? "" : htmlText($GLOBALS['gUserRow']['address_2']) . "<br>") . htmlText($GLOBALS['gUserRow']['city'] . ", " . $GLOBALS['gUserRow']['state'] . " " . $GLOBALS['gUserRow']['postal_code']) . "</span></p>";
									} else {
										$quickCheckoutEnabled = false;
									}
									executeQuery("update addresses set default_shipping_address = 0 where contact_id = ? and address_id <> ?", $GLOBALS['gUserRow']['contact_id'], $addressRow['address_id']);
								}
								if ($quickCheckoutEnabled) {
									?>
									<div id="quick_checkout_wrapper" class='cart-element'>
										<button tabindex="10" id="quick_checkout" class='hidden'>Quick Checkout</button>
									</div>
									<div id="quick_details" class="hidden"><?= $quickCheckoutDetails ?></div>
								<?php } ?>
							<?php } ?>
							<div id="summary_place_order_wrapper" class='hidden checkout-element'>
								<button tabindex="10" id="summary_place_order">Place Order</button>
							</div>
						</div>
					</div>
					<div id="_checkout_process_wrapper">
						<div class="checkout-section not-active shipping-section" data-next_section="payment_information" data-validation_error_function="shippingValidation" id="shipping_information">
							<?php
							$contactInformationRequired = (empty($contactRow['first_name']) || empty($contactRow['last_name']) || empty($contactRow['email_address']));
							$addressRequired = getPreference("ALWAYS_REQUIRE_CUSTOMERS_ADDRESS");
							$contactInformationRequired = $contactInformationRequired || (!empty($addressRequired) && (empty($contactRow['address_1']) || empty($contactRow['city'])));
							if ($contactInformationRequired) {
								$requiredContactInformationFragment = $this->getFragment("CONTACT_INFORMATION_REQUIRED");
								if (empty($requiredContactInformationFragment)) {
									$requiredContactInformationFragment = "<p>This is NOT for delivery. Your contact information is needed to place the order.</p>";
								}
								?>
								<h2>Your Contact Information</h2>
								<?php
								echo makeHtml($requiredContactInformationFragment);
								echo createFormLineControl("contacts", "first_name", array("not_null" => true, "initial_value" => $contactRow['first_name']));
								echo createFormLineControl("contacts", "last_name", array("not_null" => true, "initial_value" => $contactRow['last_name']));
								echo createFormLineControl("contacts", "email_address", array("not_null" => true, "initial_value" => $contactRow['email_address']));
								if ($addressRequired) {
									echo createFormLineControl("contacts", "address_1", array("not_null" => true, "initial_value" => $contactRow['address_1']));
									echo createFormLineControl("contacts", "address_2", array("not_null" => true, "initial_value" => $contactRow['address_2']));
									echo createFormLineControl("contacts", "city", array("not_null" => true, "initial_value" => $contactRow['city']));
									?>
									<div class="form-line" id="_state_select_row">
										<label for="state_select" class="">State</label>
										<select tabindex="10" id="state_select" name="state_select" class='validate[required]' data-field_name="State">
											<option value="">[Select]</option>
											<?php
											foreach (getStateArray() as $stateCode => $state) {
												?>
												<option value="<?= $stateCode ?>" <?= ($contactRow['state'] == $stateCode ? "selected" : "") ?>><?= htmlText($state) ?></option>
												<?php
											}
											?>
										</select>
										<div class='clear-div'></div>
									</div>
									<?php
									echo createFormLineControl("contacts", "state", array("not_null" => false, "initial_value" => $contactRow['state']));
									echo createFormLineControl("addresses", "postal_code", array("not_null" => true, "data-conditional-required" => "$(\"#country_id\").val() == \"1000\""));
									echo createFormLineControl("addresses", "country_id", array("not_null" => true, "initial_value" => (empty($contactRow['country_id']) ? "1000" : $contactRow['country_id'])));
								}
							}
							?>
							<h2>Delivery Information</h2>

							<div class='checkout-section-content'>
								<?php
								$sectionText = $this->getPageTextChunk("retail_store_shipping_method");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_shipping_method");
								}
								echo makeHtml($sectionText);
								?>

								<p id="calculating_shipping_methods" class="hidden">Fetching valid shipping methods and pickup locations...</p>
								<p id="_shipping_error" class='red-text'>No Shipping Methods are available for this shopping cart. Please contact customer support.</p>
								<div id="shipping_type_wrapper" class="form-line">
									<div class='form-line inline-block'>
										<input type="radio" name="shipping_type" id="shipping_type_pickup" value="pickup"><label for="shipping_type_pickup" class="checkbox-label">Store Pickup</label>
									</div>
									<div class="form-line inline-block">
										<input type='radio' name='shipping_type' id='shipping_type_delivery' value='delivery'><label for="shipping_type_delivery" class="checkbox-label">Delivery</label>
									</div>
								</div>

								<div class="form-line shipping-method-details delivery-shipping-method-details" id="_shipping_method_id_row">
									<label id="shipping_method_id_label" for="shipping_method_id" class="">Shipping Method</label>
									<select tabindex="10" id="shipping_method_id" name="shipping_method_id" data-field_name="Shipping Method" class='validate-hidden validate[required]' data-conditional-required='!$("#_shipping_method_id_row").hasClass("not-required") && !$("#shipping_information").hasClass("not-required")'>
										<option value="">[Select]</option>
									</select>
									<div class='clear-div'></div>
								</div>

								<div class='info-section shipping-method-details pickup-shipping-method-details' id='_pickup_location_section'>
									<div class='info-section-header'>
										<div class='info-section-title'>Pickup Location</div>
										<div class='info-section-change clickable'>change</div>
									</div>
									<div class='info-section-content'>
										<p class='info-section-change clickable'>
											<button id='select_pickup_location'>Select Location</button>
										</p>
									</div>
								</div>

								<input type='hidden' class='validate[required] validate-hidden' data-conditional-required='$("#shipping_type_delivery").prop("checked") && !$("#_shipping_method_id_row").hasClass("not-required") && !$("#shipping_information").hasClass("not-required")' id='address_id' name='address_id' value=''>
								<input type='hidden' id='address_1' name='address_1' value=''>
								<input type='hidden' id='city' name='city' value=''>
								<input type='hidden' id='state' name='state' value=''>
								<input type='hidden' id='postal_code' name='postal_code' value=''>
								<input type='hidden' id='country_id' name='country_id' value='1000'>
								<div class='info-section shipping-method-details delivery-shipping-method-details' id='_delivery_location_section'>
									<div class='info-section-header'>
										<div class='info-section-title'>Ship To <span id='ship_to_label'></span></div>
										<div class='info-section-change clickable'>change</div>
									</div>
									<?php
									$fflAddressInformation = $this->getPageTextChunk("FFL_ADDRESS_INFORMATION");
									if (empty($fflAddressInformation)) {
										$fflAddressInformation = $this->getFragment("FFL_ADDRESS_INFORMATION");
									}
									if (empty($fflAddressInformation)) {
										$fflAddressInformation = "Firearms will NOT ship to this address, but will ship to the Firearms dealer selected below. <strong class='red-text'>This is YOUR address. Do not put your FFL dealer's address here.</strong>";
									}
									?>
									<p class='ffl-section' id="ffl_address_information"><?= $fflAddressInformation ?></p>
									<div class='info-section-content'><p class='info-section-change clickable'>Add Address</p></div>
								</div>

								<?php
								$receiveTextNotifications = $phoneNumber = $otherPhoneNumber = $cellPhoneNumber = false;
								foreach ($GLOBALS['gUserRow']['phone_numbers'] as $thisPhone) {
									if ($thisPhone['description'] == "Primary" && empty($phoneNumber)) {
										$phoneNumber = $thisPhone['phone_number'];
									} elseif (!in_array($thisPhone, array("cell", "mobile", "text")) && empty($otherPhoneNumber)) {
										$otherPhoneNumber = $thisPhone['phone_number'];
									} elseif (in_array($thisPhone, array("cell", "mobile", "text")) && empty($cellPhoneNumber)) {
										$cellPhoneNumber = $thisPhone['phone_number'];
									}
								}
								if (empty($phoneNumber)) {
									$phoneNumber = $otherPhoneNumber;
								}
								if (empty($phoneNumber)) {
									$phoneNumber = $cellPhoneNumber;
									$receiveTextNotifications = true;
								}
								$phoneRequired = getPreference("retail_store_phone_required");
								$phoneControls = array("not_null" => (!empty($phoneRequired) && !$GLOBALS['gInternalConnection']), "form_label" => "", "initial_value" => $phoneNumber);
								?>
								<div class='info-section' id="phone_number_info_section">
									<div class='info-section-header'>
										<div class='info-section-title'>Primary Phone Number</div>
									</div>
									<div class='info-section-content'>
										<?= createFormLineControl("phone_numbers", "phone_number", $phoneControls) ?>
										<div class='form-line'>
											<input type='checkbox' value='1' id='receive_text_notifications' name='receive_text_notifications'><label class='checkbox-label' for='receive_text_notifications'>Receive Text Notifications</label>
										</div>
									</div>
								</div>
								<?php

								if (!empty($this->iFflRequiredProductTagId)) {
									$fflChoiceElement = "<p class='info-section-change clickable'><button id='select_ffl_location'>Select FFL</button></p>";
									$defaultFFLFound = false;
									if (!empty($contactId)) {
										$fflRow = (new FFL(array("federal_firearms_licensee_id" => CustomField::getCustomFieldData($contactId, "DEFAULT_FFL_DEALER"), "only_if_valid" => true)))->getFFLRow();
										if ($fflRow) {
											$fflChoiceElement = "<p><span class='ffl-choice-business-name'>%business_name%</span><br><span class='ffl-choice-address'>%address_1%</span><br><span class='ffl-choice-city'>%city%, %state% %postal_code%</span><br><span class='ffl-phone-number'>%phone_number%</span></p>";
											foreach ($fflRow as $fieldName => $fieldData) {
												$fflChoiceElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $fflChoiceElement);
											}
											$defaultFFLFound = true;
										} else {
											CustomField::setCustomFieldData($contactId, "DEFAULT_FFL_DEALER", "");
										}
										$federalFirearmsLicenseeId = $fflRow['federal_firearms_licensee_id'];
									} else {
										$federalFirearmsLicenseeId = "";
									}
									$customerOrderNote = getPreference("CUSTOMER_ORDER_NOTE");
									$forceFFLDealerRequired = getUserTypePreference("FORCE_FFL_DEALER_REQUIRED");
									$crRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CR_REQUIRED");
									?>
									<div class='ffl-section hidden' id='ffl_information'>
										<?php
										$sectionText = $this->getPageTextChunk("retail_store_ffl_requirement");
										if (empty($sectionText)) {
											$sectionText = $this->getFragment("retail_store_ffl_requirement");
										}
										if (empty($sectionText)) {
											$sectionText = "<p>Some of the items you ordered require shipment to a licensed FFL dealer. Select the FFL dealer to which these items will be shipped. FFL selection is based on your shipping address. If you haven't yet entered your shipping address, do so now.</p>";
										}
										echo makeHtml($sectionText);
										?>
									</div>
									<input type="hidden" class="validate[required] validate-hidden" data-field_name="FFL data-conditional-required=" !$('#ffl_dealer_not_found').prop('checked') && !$('#has_cr_license').prop('checked')" id="federal_firearms_licensee_id" name="federal_firearms_licensee_id" value="<?= $federalFirearmsLicenseeId ?>">
									<div class='info-section ffl-section hidden' id="ffl_selection_wrapper" data-product_tag_id="<?= $this->iFflRequiredProductTagId ?>" data-cr_required_product_tag_id="<?= $crRequiredProductTagId ?>">
										<div class='info-section-header'>
											<div class='info-section-title'>FFL Dealer</div>
											<div class='info-section-change clickable'>change</div>
										</div>
										<?php if (!$forceFFLDealerRequired) { ?>
											<p id="ffl_dealer_not_found_wrapper" <?= ($defaultFFLFound ? "class='hidden'" : "") ?>>
												<input type='checkbox' class="" id="ffl_dealer_not_found" name="ffl_dealer_not_found" value="1">
												<label class="checkbox-label" for="ffl_dealer_not_found"><?= getLanguageText("I can't find my dealer") . (empty($customerOrderNote) ? "" : " (Add dealer info on final screen)") ?></label>
											</p>
											<div id="cr_license_wrapper" class="hidden">
												<p id="has_cr_license_wrapper">
													<input type="checkbox" id="has_cr_license" name="has_cr_license" value="1">
													<label class="checkbox-label" for="has_cr_license">I have a C&R License</label>
												</p>
												<p id="cr_license_file_upload_wrapper" class="hidden">
													<input type="file" id="cr_license_file_upload" name="cr_license_file_upload" accept="image/*">
													<label class="checkbox-label" for="cr_license_file_upload">Upload C&R license</label>
												</p>
											</div>
										<?php } ?>
										<div class='info-section-content'><?= $fflChoiceElement ?></div>
									</div> <!-- ffl_section -->
									<?php
								}
								?>

								<?php if ($GLOBALS['gUserRow']['full_client_access']) { ?>
									<div class='form-line admin-logged-in' id="_show_shipping_calculation_log_row">
										<button id="show_shipping_calculation_log">Show Shipping Calculation</button>
									</div>
								<?php } ?>
							</div>
							<button class='checkout-next-button'>Continue</button>
						</div>

						<div class="checkout-section not-active payment-section" data-next_section="finalize_information" data-validation_error_function="paymentValidation" id="payment_information">
							<h2>Payment Information</h2>
							<div class='checkout-section-content'>
								<?php
								$fragment = $this->getFragment("retail_store_payment_information");
								if (!empty($fragment)) {
									echo makeHtml($fragment);
								}
								if (!empty($designationRow)) {
									?>
									<div class='info-section' id="donation_info_section">
										<div class='info-section-header'>
											<div class='info-section-title'>Donation</div>
										</div>
										<div class='info-section-content'>
											<?php
											$sectionText = $this->getPageTextChunk("retail_store_donation");
											if (empty($sectionText)) {
												$sectionText = $this->getFragment("retail_store_donation");
											}
											if (!empty($sectionText)) {
												echo makeHtml($sectionText);
											}
											?>
											<input type="hidden" id="designation_id" name="designation_id" value="<?= $designationRow['designation_id'] ?>">
											<div id='_donation_amount_wrapper'>
												<div class="form-line" id="_donation_amount_row">
													<label for="donation_amount" class="">Donation for <?= htmlText($designationRow['description']) ?></label>
													<input tabindex="10" size="15" type="text" id="donation_amount" name="donation_amount" data-field_name="Donation Amount" class="align-right validate[required,custom[number]]" data-conditional-required='(!empty($("#designation_id").val()))' data-decimal-places="2" value='0.00'>
													<div class='clear-div'></div>
												</div>
												<div id="round_up_buttons">
													<?php
													$sectionText = $this->getPageTextChunk("retail_store_round_up");
													if (empty($sectionText)) {
														$sectionText = $this->getFragment("retail_store_round_up");
													}
													if (empty($sectionText)) {
														ob_start();
														?>
														<p>Round up your purchase with a donation!</p>
														<p>
															<button tabindex="10" class="round-up-donation" data-round_amount="1" id="round_up_1">Round up to<br>nearest $1</button>
															<button tabindex="10" class="round-up-donation" data-round_amount="5" id="round_up_5">Round up to<br>nearest $5</button>
															<button tabindex="10" class="round-up-donation" data-round_amount="10" id="round_up_10">Round up to<br>nearest $10</button>
														</p>
														<?php
														$sectionText = ob_get_clean();
													}
													echo makeHtml($sectionText);
													?>
												</div> <!-- round_up_buttons -->
											</div>
										</div>
									</div>
									<?php
								}

								$paymentMethodNumber = 1;
								$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
									"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where client_id = ? and inactive = 0 and " .
									($GLOBALS['gInternalConnection'] ? "" : "internal_use_only = 0 and ") .
									"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
									(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
									"(payment_method_type_id is null or payment_method_type_id in (select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
									"payment_method_type_code not in ('CREDIT_CARD','BANK_ACCOUNT','THIRD_PARTY','INVOICE'))) order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									if ($row['requires_user'] && !$GLOBALS['gLoggedIn']) {
										continue;
									}
									$row['maximum_payment_amount'] = 0;
									$usePaymentMethod = true;
									switch ($row['payment_method_type_code']) {
										case "CREDOVA":
											$credovaCredentials = getCredovaCredentials();
											if (empty($credovaCredentials['username']) || empty($credovaCredentials['password'])) {
												$usePaymentMethod = false;
											}
											break;
										case "LOYALTY_POINTS":
											$loyaltySet = executeQuery("select * from loyalty_programs where client_id = ? and (user_type_id = ? or user_type_id is null) and inactive = 0 and " .
												"internal_use_only = 0 order by user_type_id desc,sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['user_type_id']);
											if (!$loyaltyProgramRow = getNextRow($loyaltySet)) {
												$loyaltyProgramRow = array();
											}
											$loyaltyProgramPointsRow = getRowFromId("loyalty_program_points", "user_id", $GLOBALS['gUserId'], "loyalty_program_id = ?", $loyaltyProgramRow['loyalty_program_id']);

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
									?>
									<div class='payment-method-wrapper payment-method-section-wrapper' data-payment_method_id="<?= $row['payment_method_id'] ?>"
										 data-payment_method_code="<?= $row['payment_method_code'] ?>"
										 data-maximum_payment_percentage="<?= $row['percentage'] ?>"
										 data-maximum_payment_amount="<?= $row['maximum_payment_amount'] ?>"
										 data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"
										 data-payment_method_number='<?= $paymentMethodNumber ?>'
										 id='payment_method_section_<?= $paymentMethodNumber ?>'>
										<input type="hidden" data-payment_method_number='<?= $paymentMethodNumber ?>' class="payment-method-id" id="payment_method_id_<?= $paymentMethodNumber ?>" name="payment_method_id_<?= $paymentMethodNumber ?>">
										<input type='hidden' name='payment_method_number_<?= $paymentMethodNumber ?>' id='payment_method_number_<?= $paymentMethodNumber ?>' value='<?= $paymentMethodNumber ?>'>
										<p class='apply-payment-method-wrapper'><a href='#' class='apply-payment-method'>Apply <?= htmlText($row['description']) ?> to order</a></p>
										<div class='info-section' id='payment_method_section_<?= $paymentMethodNumber ?>'>
											<div class='info-section-header'>
												<div class='info-section-title'><?= htmlText($row['description']) ?> Applied</div>
												<span class='fad fa-trash remove-applied-payment-method'></span>
											</div>
											<div class='info-section-content'>
												<?= makeHtml($row['detailed_description']) ?>
												<?php
												switch ($row['payment_method_code']) {
													case "CHECK":
														?>
														<div class="applied-payment-method-fields" id="payment_method_check">
															<div class="form-line" id="_reference_number_<?= $paymentMethodNumber ?>_row">
																<input tabindex="10" type="text" class="" size="30" maxlength="20"
																	   id="reference_number_<?= $paymentMethodNumber ?>" name="reference_number_<?= $paymentMethodNumber ?>"
																	   placeholder="Check Number" value="">
																<div class='clear-div'></div>
															</div>

															<div class="form-line" id="_payment_time_<?= $paymentMethodNumber ?>_row">
																<input tabindex="10" type="text" class="validate[custom[date]]" size="15" maxlength="12" id="payment_time_<?= $paymentMethodNumber ?>" name="payment_time_<?= $paymentMethodNumber ?>" placeholder="Check Date" value="">
																<div class='clear-div'></div>
															</div>
														</div> <!-- payment_method_check -->
														<?php
														break;
													case "GIFT_CARD":
														?>
														<div class="applied-payment-method-fields" id="payment_method_gift_card">
															<div class="form-line" id="_gift_card_number_<?= $paymentMethodNumber ?>_row">
																<input tabindex="10" type="text" class="gift-card-number validate[required]" data-field_name="Gift Card Number"
																	   data-conditional-required="$('#payment_method_section_<?= $paymentMethodNumber ?>').hasClass('applied')"
																	   size="40" maxlength="40" id="gift_card_number_<?= $paymentMethodNumber ?>"
																	   name="gift_card_number_<?= $paymentMethodNumber ?>" placeholder="Gift Card Number" value="">
																<div class='clear-div'></div>
															</div>
															<div class="form-line" id="_gift_card_pin_<?= $paymentMethodNumber ?>_row">
																<input tabindex="10" type="text" class="gift-card-pin"
																	   size="8" maxlength="8" id="gift_card_pin_<?= $paymentMethodNumber ?>"
																	   name="gift_card_pin_<?= $paymentMethodNumber ?>" placeholder="Pin" value="">
																<div class='clear-div'></div>
															</div>
															<div class='form-line'>
																<button id='validate_gift_card_<?= $paymentMethodNumber ?>' class='validate-gift-card'>Validate & Check Balance</button>
															</div>
															<p class="gift-card-information"></p>
														</div> <!-- payment_method_gift_card -->
														<?php
														break;
													case "LOAN":
														?>
														<div class="applied-payment-method-fields" id="payment_method_loan">
															<div class="form-line" id="_loan_<?= $paymentMethodNumber ?>_row">
																<input tabindex="10" type="text" class="validate[required] uppercase" data-field_name="Loan Number"
																	   data-conditional-required="$('#payment_method_section_<?= $paymentMethodNumber ?>').hasClass('applied')"
																	   size="40" maxlength="30" id="loan_number_<?= $paymentMethodNumber ?>" name="loan_number_<?= $paymentMethodNumber ?>"
																	   placeholder="Loan Number" value="">
																<div class='clear-div'></div>
															</div>
															<p class="loan-information"></p>
														</div> <!-- payment_method_loan -->
														<?php
														break;
													case "LEASE":
														?>
														<div class="applied-payment-method-fields" id="payment_method_lease">
															<div class="form-line" id="_lease_<?= $paymentMethodNumber ?>_row">
																<input tabindex="10" type="text" class="validate[required] uppercase" data-field_name="Lease Number"
																	   data-conditional-required="$('#payment_method_section_<?= $paymentMethodNumber ?>').hasClass('applied')"
																	   size="40" maxlength="30" id="lease_number_<?= $paymentMethodNumber ?>" name="lease_number_<?= $paymentMethodNumber ?>"
																	   placeholder="Lease Number" value="">
																<div class='clear-div'></div>
															</div>
														</div> <!-- payment_method_lease -->
														<?php
														break;
														?>
													<?php
												}
												?>
												<div class="form-line" id="_payment_amount_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" data-field_name="Payment Amount" class="payment-amount validate[required,custom[number]] min[0]" data-decimal-places='2' size="15" maxlength="12" id="payment_amount_<?= $paymentMethodNumber ?>" name="payment_amount_<?= $paymentMethodNumber ?>" value="">
													<div class='clear-div'></div>
												</div>
												<p class='error-message'></p>
												<div class="form-line apply-payment-method-buttons">
													<button tabindex='10' class='cancel-apply-payment-method-button'>Cancel</button>
													<button tabindex='10' class='apply-payment-method-button'>Apply</button>
													<div class='clear-div'></div>
												</div>
											</div>
										</div>
									</div>
									<?php
									$paymentMethodNumber++;
								}
								$paymentMethodNumber = $this->iPrimaryPaymentMethodNumber;
								?>
								<div class='info-section payment-method-wrapper' id='primary_payment_method_section'>
									<div class='info-section-header'>
										<div class='info-section-title'>Primary Payment Method Details</div>
										<div id='_balance_due_wrapper'>Balance Due: <span id="_balance_due"></span></div>
									</div>
									<div class='info-section-content'>
										<input type="hidden" class="primary-payment-method" id="primary_payment_method_<?= $paymentMethodNumber ?>" name="primary_payment_method_<?= $paymentMethodNumber ?>" value="1">
										<?php
										$validAccounts = array();
										$foundDefaultPaymentMethod = false;
										$resultSet = executeQuery("select *, (select payment_method_type_id from payment_methods where payment_method_id = accounts.payment_method_id) payment_method_type_id from accounts where contact_id = ? and inactive = 0 and " .
											"(merchant_account_id is null or merchant_account_id in (select merchant_account_id from merchant_accounts where client_id = ? and inactive = 0 and internal_use_only = 0))", $contactId, $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $row['payment_method_type_id']);
											if (!in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT", "CREDIT_ACCOUNT", "CHARGE_ACCOUNT"))) {
												continue;
											}
											if (empty($row['account_token']) && in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT"))) {
												continue;
											}
											$row['payment_method_type_code'] = $paymentMethodTypeCode;
											$row['payment_method_code'] = getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $row['payment_method_id']);
											$merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
											if ($merchantAccountId == $GLOBALS['gMerchantAccountId'] || empty($row['account_token'])) {
												$validAccounts[] = $row;
												if (!empty($row['default_payment_method'])) {
													$foundDefaultPaymentMethod = true;
												}
											}
										}
										if (empty($validAccounts)) {
											?>
											<input type='hidden' id="account_id_<?= $paymentMethodNumber ?>" name="account_id_<?= $paymentMethodNumber ?>" value=''>
											<?php
										} else {
											?>
											<div class='form-line' id='account_id_wrapper'>
												<select id="account_id_<?= $paymentMethodNumber ?>" name="account_id_<?= $paymentMethodNumber ?>">
													<option value=''>[Add New Payment Method]</option>
													<?php
													foreach ($validAccounts as $row) {
														$accountLabel = $row['account_label'];
														if (!empty($row['account_number'])) {
															$accountLabel .= (empty($accountLabel) ? "Account ending in " : " - ") . substr($row['account_number'], -4);
														}
														if (empty($accountLabel)) {
															$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
														}
														if (!$foundDefaultPaymentMethod) {
															$row['default_payment_method'] = true;
															$foundDefaultPaymentMethod = true;
														}
														?>
														<option<?= (empty($row['default_payment_method']) ? "" : " selected") ?> data-payment_method_id='<?= $row['payment_method_id'] ?>' value='<?= $row['account_id'] ?>'><?= htmlText($accountLabel) ?></option>
														<?php
													}
													?>
												</select>
											</div>
										<?php } ?>
										<div id="_new_account_wrapper" class='<?= (count($validAccounts) == 0 ? "" : "hidden") ?>'>
											<div class="form-line hidden" id="_payment_method_id_<?= $paymentMethodNumber ?>_row">
												<input type='hidden' name='payment_method_number_<?= $paymentMethodNumber ?>' id='payment_method_number_<?= $paymentMethodNumber ?>' value='<?= $paymentMethodNumber ?>'>
												<select tabindex="10" data-payment_method_number='<?= $paymentMethodNumber ?>' data-field_name="Payment Method" class="payment-method-id validate[required]" data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#primary_payment_method_section').hasClass('no-content')" id="payment_method_id_<?= $paymentMethodNumber ?>" name="payment_method_id_<?= $paymentMethodNumber ?>">
													<option value=''>[Select Payment Method]</option>
													<?php
													$paymentMethodArray = array();
													$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
														"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where client_id = ? and inactive = 0 and " .
														($GLOBALS['gInternalConnection'] ? "" : "internal_use_only = 0 and ") .
														"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
														(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
														"payment_method_type_id in (select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and payment_method_type_code in ('BANK_ACCOUNT','CREDIT_CARD','THIRD_PARTY','INVOICE'))" .
														($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
													while ($row = getNextRow($resultSet)) {
														if ($row['requires_user'] && !$GLOBALS['gLoggedIn']) {
															continue;
														}
														$paymentMethodArray[] = $row;
														?>
														<option data-address_required='<?= (empty($row['no_address_required']) ? "1" : "") ?>' data-payment_method_code='<?= $row['payment_method_code'] ?>' data-payment_method_type_code='<?= $row['payment_method_type_code'] ?>' value='<?= $row['payment_method_id'] ?>'><?= htmlText($row['description']) ?></option>
														<?php
													}
													?>
												</select>
												<div class='clear-div'></div>
											</div>

											<?php if (count($paymentMethodArray) > 1) { ?>
												<p>Select a payment method</p>
											<?php } ?>
											<div id="_payment_method_id_options">
												<?php
												foreach ($paymentMethodArray as $row) {
													?>
													<div class='payment-method-id-option' data-payment_method_id="<?= $row['payment_method_id'] ?>">
														<div class='payment-method-id-option-content'>
															<span class='payment-method-icon <?= eCommerce::getPaymentMethodIcon($row['payment_method_code'], $row['payment_method_type_code']) ?>'></span>
															<p><?= htmlText($row['description']) ?></p>
														</div>
													</div>
													<?php
												}
												?>
											</div>

											<div id="_billing_address_wrapper" class='hidden'>
												<div class="form-line inline-block" id="_billing_first_name_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" data-field_name="Billing First Name"
														   class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#primary_payment_method_section').hasClass('no-content') && !$('#_billing_address').hasClass('hidden')"
														   size="30" maxlength="25" id="billing_first_name_<?= $paymentMethodNumber ?>"
														   name="billing_first_name_<?= $paymentMethodNumber ?>" placeholder="First Name"
														   value="" data-default_value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
													<div class='clear-div'></div>
												</div>

												<div class="form-line inline-block" id="_billing_last_name_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" data-field_name="Billing Last Name"
														   class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#primary_payment_method_section').hasClass('no-content') && !$('#_billing_address').hasClass('hidden')"
														   size="35" maxlength="35" id="billing_last_name_<?= $paymentMethodNumber ?>"
														   name="billing_last_name_<?= $paymentMethodNumber ?>" placeholder="Last Name"
														   value="" data-default_value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
													<div class='clear-div'></div>
												</div>

												<div class="form-line" id="_billing_business_name_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" data-field_name="Billing Business Name"
														   class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#primary_payment_method_section').hasClass('no-content')"
														   size="40" maxlength="35" id="billing_business_name_<?= $paymentMethodNumber ?>"
														   name="billing_business_name_<?= $paymentMethodNumber ?>" placeholder="Business Name"
														   value="" data-default_value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
													<div class='clear-div'></div>
												</div>

												<?php
												$billingAddresses = array();
												$defaultAddressId = false;
												$resultSet = executeQuery("select * from addresses where contact_id = ? and inactive = 0 and address_1 is not null and city is not null order by default_billing_address desc,address_id desc", $contactId);
												while ($row = getNextRow($resultSet)) {
													if (!empty($billingAddresses)) {
														$row['default_billing_address'] = 0;
													} else {
														$row['default_billing_address'] = 1;
													}
													if (empty($defaultAddressId)) {
														$defaultAddressId = $row['address_id'];
													}
													$billingAddresses[] = $row;
												}
												if (!empty($billingAddresses)) {
													?>
													<div class="form-line" id="_billing_address_id_<?= $paymentMethodNumber ?>_row">
														<label>Select a previously used address</label>
														<select tabindex="10" data-payment_method_number='<?= $paymentMethodNumber ?>' data-default_value="<?= $defaultAddressId ?>" class="" id="billing_address_id_<?= $paymentMethodNumber ?>" name="billing_address_id_<?= $paymentMethodNumber ?>">
															<option value=''>[Add New Address]</option>
															<?php
															foreach ($billingAddresses as $row) {
																?>
																<option <?= ($row['default_billing_address'] ? "selected " : "") ?>value='<?= $row['address_id'] ?>'><?= htmlText((empty($row['address_label']) ? "" : $row['address_label'] . ": ") . $row['address_1'] . ", " . $row['city']) ?></option>
																<?php
															}
															?>
														</select>
														<div class='clear-div'></div>
													</div>
												<?php } ?>
												<div id="_billing_address"<?= (empty($billingAddresses) ? "" : " class='hidden'") ?>>

													<?php if ($GLOBALS['gLoggedIn']) { ?>
														<div class="form-line" id="_default_billing_address_<?= $paymentMethodNumber ?>_row">
															<input tabindex="10" type="checkbox" id="default_billing_address_<?= $paymentMethodNumber ?>" name="default_billing_address_<?= $paymentMethodNumber ?>" value="1"><label class='checkbox-label' for="default_billing_address_<?= $paymentMethodNumber ?>">Make this my default billing address</label>
															<div class='clear-div'></div>
														</div>
													<?php } ?>

													<div class="form-line" id="_billing_address_1_<?= $paymentMethodNumber ?>_row">
														<input tabindex="10" type="text" data-prefix="billing_" autocomplete='chrome-off' autocomplete='off'
															   class="autocomplete-address billing-address validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>"
															   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#_billing_address').hasClass('hidden')"
															   size="40" maxlength="60" id="billing_address_1_<?= $paymentMethodNumber ?>"
															   name="billing_address_1_<?= $paymentMethodNumber ?>" data-field_name="Billing Address" placeholder="Address" value="">
														<div class='clear-div'></div>
													</div>

													<div class="form-line inline-block" id="_billing_city_<?= $paymentMethodNumber ?>_row">
														<input tabindex="10" type="text"
															   class="billing-address validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>"
															   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#_billing_address').hasClass('hidden')"
															   size="40" maxlength="60" id="billing_city_<?= $paymentMethodNumber ?>" name="billing_city_<?= $paymentMethodNumber ?>"
															   data-field_name="Billing City" placeholder="City" value="">
														<div class='clear-div'></div>
													</div>

													<div class="form-line inline-block" id="_billing_state_<?= $paymentMethodNumber ?>_row">
														<input tabindex="10" type="text"
															   class="billing-address validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>"
															   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#_billing_address').hasClass('hidden')"
															   size="12" maxlength="30" id="billing_state_<?= $paymentMethodNumber ?>" name="billing_state_<?= $paymentMethodNumber ?>"
															   data-field_name="Billing State" placeholder="State" value="">
														<div class='clear-div'></div>
													</div>

													<div class="form-line inline-block" id="_billing_state_select_<?= $paymentMethodNumber ?>_row">
														<select tabindex="10" id="billing_state_select_<?= $paymentMethodNumber ?>" name="billing_state_select_<?= $paymentMethodNumber ?>"
																class="billing-state-select billing-address validate[required]" data-field_name="Billing State"
																data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#_billing_address').hasClass('hidden') && $('#billing_country_id_<?= $paymentMethodNumber ?>').val() == 1000">
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

													<div class="form-line inline-block" id="_billing_postal_code_<?= $paymentMethodNumber ?>_row">
														<input tabindex="10" type="text"
															   class="validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]"
															   size="15" maxlength="10" data-field_name="Billing Postal Code"
															   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#_billing_address').hasClass('hidden') && $('#billing_country_id_<?= $paymentMethodNumber ?>').val() == 1000"
															   id="billing_postal_code_<?= $paymentMethodNumber ?>" name="billing_postal_code_<?= $paymentMethodNumber ?>"
															   placeholder="Postal Code" value="">
														<div class='clear-div'></div>
													</div>

													<div class="form-line" id="_billing_country_id_<?= $paymentMethodNumber ?>_row">
														<select tabindex="10" class="billing-country-id billing-address validate[required]" data-field_name="Billing Country"
																data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#_billing_address').hasClass('hidden')"
																id="billing_country_id_<?= $paymentMethodNumber ?>" name="billing_country_id_<?= $paymentMethodNumber ?>">
															<?php
															foreach (getCountryArray(true) as $countryId => $countryName) {
																?>
																<option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
																<?php
															}
															?>
														</select>
														<div class='clear-div'></div>
													</div>
												</div>
											</div> <!-- billing_address -->

											<div class="payment-method-fields hidden" id="payment_method_credit_card">
												<div class="form-line" id="_account_number_<?= $paymentMethodNumber ?>_row">
													<label>Card Number</label>
													<input tabindex="10" type="text" class="validate[required]" data-field_name="Credit Card Number"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_credit_card').hasClass('hidden')"
														   size="40" maxlength="20" id="account_number_<?= $paymentMethodNumber ?>"
														   name="account_number_<?= $paymentMethodNumber ?>" placeholder="Card Number" value="">
													<div class='clear-div'></div>
												</div>

												<div class="form-line" id="_expiration_month_<?= $paymentMethodNumber ?>_row">
													<label class="">Expiration Date</label>
													<select tabindex="10" class="expiration-date validate[required]" data-field_name="Expiration Date" data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_credit_card').hasClass('hidden')" id="expiration_month_<?= $paymentMethodNumber ?>" name="expiration_month_<?= $paymentMethodNumber ?>">
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
															data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_credit_card').hasClass('hidden')"
															data-field_name="Expiration Date" id="expiration_year_<?= $paymentMethodNumber ?>" name="expiration_year_<?= $paymentMethodNumber ?>">
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

												<div class="form-line" id="_cvv_code_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" class="cvv-code validate[required]" data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_credit_card').hasClass('hidden')"
														   data-field_name="CVV Code" size="10" maxlength="4" id="cvv_code_<?= $paymentMethodNumber ?>" name="cvv_code_<?= $paymentMethodNumber ?>" placeholder="Security Code (CVV/CVC)" value="">
													<div class='clear-div'></div>
												</div>
												<div class='clear-div'></div>
											</div> <!-- payment_method_credit_card -->

											<div class="payment-method-fields hidden" id="payment_method_bank_account">
												<div class="form-line" id="_routing_number_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text"
														   class="validate[required,custom[routingNumber]]" data-field_name="Routing Number"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_bank_account').hasClass('hidden')"
														   size="30" maxlength="20" id="routing_number_<?= $paymentMethodNumber ?>"
														   name="routing_number_<?= $paymentMethodNumber ?>" placeholder="Routing Number" value="">
													<div class='clear-div'></div>
												</div>

												<div class="form-line" id="_routing_number_again_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text"
														   class="validate[required,equals[routing_number_<?= $paymentMethodNumber ?>]]"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_bank_account').hasClass('hidden')"
														   size="30" maxlength="20" id="routing_number_again_<?= $paymentMethodNumber ?>"
														   name="routing_number_again_<?= $paymentMethodNumber ?>" placeholder="Confirm Routing Number" value="">
													<div class='clear-div'></div>
												</div>

												<div class="form-line" id="_bank_account_number_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" class="validate[required]" data-field_name="Account Number"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_bank_account').hasClass('hidden')"
														   size="40" maxlength="20" id="bank_account_number_<?= $paymentMethodNumber ?>"
														   name="bank_account_number_<?= $paymentMethodNumber ?>" placeholder="Bank Account Number"
														   value="">
													<div class='clear-div'></div>
												</div>

												<div class="form-line" id="_bank_account_number_again_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="text" class="validate[required,equals[bank_account_number_<?= $paymentMethodNumber ?>]]"
														   data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && !$('#payment_method_bank_account').hasClass('hidden')"
														   size="40" maxlength="20" id="bank_account_number_again_<?= $paymentMethodNumber ?>"
														   name="bank_account_number_again_<?= $paymentMethodNumber ?>" placeholder="Bank Account Number"
														   value="">
													<div class='clear-div'></div>
												</div>
											</div> <!-- payment_method_bank_account -->

											<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
												<div class="form-line" id="_default_payment_method_<?= $paymentMethodNumber ?>_row">
													<input tabindex="10" type="checkbox" id="default_payment_method_<?= $paymentMethodNumber ?>" name="default_payment_method_<?= $paymentMethodNumber ?>" value="1" checked='checked'><label class='checkbox-label' for="default_payment_method_<?= $paymentMethodNumber ?>">Make this my default payment method</label>
													<div class='clear-div'></div>
												</div>

												<div class="form-line payment-method-fields hidden" id="_account_label_<?= $paymentMethodNumber ?>_row">
													<label>Assign a nickname to save and use this payment method later</label>
													<input tabindex="10" type="text" size="40" maxlength="40" class='validate[required]' data-conditional-required="!$('#_new_account_wrapper').hasClass('hidden') && $('#default_payment_method_<?= $paymentMethodNumber ?>').prop('checked')" id="account_label_<?= $paymentMethodNumber ?>" name="account_label_<?= $paymentMethodNumber ?>" placeholder="Account Nickname" value="">
													<div class='clear-div'></div>
												</div>
											<?php } ?>

										</div>

										<div class="form-line" id="_payment_amount_<?= $paymentMethodNumber ?>_row">
											<label class="">Payment Amount</label>
											<input tabindex="10" type="text" class="payment-amount validate[required,custom[number]] min[0]" readonly="readonly" data-decimal-places='2' size="15" maxlength="12" id="payment_amount_<?= $paymentMethodNumber ?>" name="payment_amount_<?= $paymentMethodNumber ?>" data-field_name="Payment Amount" value="">
											<div class='clear-div'></div>
										</div>
									</div>
								</div>
							</div>
							<button class='checkout-next-button'>Continue</button>
						</div>

						<div class="checkout-section not-active" id="finalize_information">
							<h2>Finalize Order</h2>
							<div class='checkout-section-content'>
								<?php
								$customerOrderNote = getPreference("CUSTOMER_ORDER_NOTE");
								if (!empty($customerOrderNote)) {
									?>
									<div class="form-line" id="_order_note_row">
										<label for="order_note">Special Instructions</label>
										<textarea id="order_note" name="order_note"></textarea>
										<div class='clear-div'></div>
									</div>
									<?php
								}
								?>

								<?php
								$customerOrderFile = getPreference("CUSTOMER_ORDER_FILE");
								if (!empty($customerOrderFile)) {
									?>
									<div class="form-line" id="_order_file_upload_row">
										<label for="order_file_upload"><?= htmlText($customerOrderFile) ?></label>
										<input type="file" id="order_file_upload" name="order_file_upload">
										<div class='clear-div'></div>
									</div>
									<?php
								}
								?>

								<div class="form-line" id="_bank_name_row">
									<label for="bank_name" class="">Bank Name</label>
									<input tabindex="10" type="text" size="20" maxlength="20" id="bank_name" name="bank_name" placeholder="Bank Name" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_agree_terms_row">
									<input tabindex="10" type="checkbox" name="agree_terms" id="agree_terms" value="1"><label for="agree_terms">Agree to our terms of service</label>
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_confirm_human_row">
									<input tabindex="10" type="checkbox" name="confirm_human" id="confirm_human" value="1"><label class='checkbox-label'>Click here to confirm you are human</label>
									<div class='clear-div'></div>
								</div>
								<input type='hidden' id='quick_checkout_flag' name='quick_checkout_flag' value=''>

								<h3>Terms & Conditions</h3>
								<?php
								$sectionText = $this->getPageTextChunk("retail_store_terms_conditions");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_terms_conditions");
								}
								if (!empty($sectionText)) {
									?>
									<div class="form-line" id="_terms_conditions_row">
										<input type="checkbox" data-field_name="Terms and Conditions" id="terms_conditions" name="terms_conditions" data-conditional-required='!$("#_finalize_order").hasClass("quick-checkout")' class="validate[required]" value="1" <?= ($GLOBALS['gInternalConnection'] ? " checked" : "") ?>><label for="terms_conditions" class="checkbox-label">I agree to the Terms and Conditions.</label> <a href='#' id="view_terms_conditions" class="clickable">Click here to view store Terms and Conditions.</a>
										<div class='clear-div'></div>
									</div>
									<div class='dialog-box' id='_terms_conditions_dialog'>
										<div id='_terms_conditions_wrapper'><?= makeHtml($sectionText) ?></div>
									</div>
									<?php
								}
								?>

								<div class="form-line hidden" id="_dealer_terms_conditions_row">
									<input type="checkbox" id="dealer_terms_conditions" name="dealer_terms_conditions" class="" value="1">
									<label for="dealer_terms_conditions" class="checkbox-label">I agree to the FFL Dealer's Terms and Conditions.</label>
									<a href='#' id="view_dealer_terms_conditions" class="clickable">Click here to view FFL Dealer's Terms and Conditions.</a>
									<div class='clear-div'></div>
								</div>
								<div class='dialog-box' id='_dealer_terms_conditions_dialog'>
									<div id='_dealer_terms_conditions_wrapper'></div>
								</div>

								<?php
								$sectionText = $this->getPageTextChunk("retail_store_terms_conditions_note");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_terms_conditions_note");
								}
								if (empty($sectionText)) {
									$sectionText = "<p>By placing your order, you are stating that you agree to all of the terms outlined in our store policies.</p>";
								}
								echo $sectionText;
								?>

								<div id="retail_agreements"></div>
								<?php
								if (!$GLOBALS['gUserRow']['administrator_flag']) {
									if (!empty($this->iUseRecaptchaV2)) {
										?>
										<div class="g-recaptcha" data-sitekey="<?= getPreference("ORDER_RECAPTCHA_V2_SITE_KEY") ?>"></div>
										<?php
									} elseif (!empty(getPreference("USE_ORDER_CAPTCHA"))) {
										$captchaCodeId = createCaptchaCode();
										?>
										<input type='hidden' id='captcha_code_id' name='captcha_code_id' value='<?= $captchaCodeId ?>'>

										<div class='form-line' id=_captcha_image_row'>
											<label></label>
											<img src="/captchagenerator.php?id=<?= $captchaCodeId ?>">
										</div>

										<div class="form-line" id="_captcha_code_row">
											<label for="captcha_code" class="">Type the text you see above</label>
											<input tabindex="10" class='validate[required]' data-field_name="Captcha" data-conditional-required='!$("#_finalize_order").hasClass("quick-checkout")' type="text" size="10" maxlength="10" id="captcha_code" name="captcha_code" placeholder="Captcha Text" value="">
											<div class='clear-div'></div>
										</div>
										<?php
									}
								}
								$noSignature = getPreference("RETAIL_STORE_NO_SIGNATURE");
								$requireSignature = getPreference("RETAIL_STORE_REQUIRE_SIGNATURE");
								if (empty($noSignature)) {
									$sectionText = $this->getPageTextChunk("retail_store_signature_text");
									if (empty($sectionText)) {
										$sectionText = $this->getFragment("retail_store_signature_text");
									}
									if (!empty($sectionText)) {
										echo makeHtml($sectionText);
									}
									/*
									3 options:
									- No signature - nothing displayed
									- FFL or not
									- always required
									*/
									?>
									<div class="form-line<?= ($requireSignature ? " signature-required" : " hidden") ?>" id="_signature_row">
										<label for="signature" class="">Signature</label>
										<span class="help-label">Required for <?= ($requireSignature ? "" : "FFL ") ?>purchase</span>
										<input type='hidden' name='signature' data-required='<?= ($requireSignature ? "1" : "0") ?>' id='signature'>
										<div class='signature-palette-parent'>
											<div id='signature_palette' tabindex="10" class='signature-palette'
												 data-column_name='signature'></div>
										</div>
										<div class='clear-div'></div>
									</div>
									<?php
								}

								$credovaCredentials = getCredovaCredentials();
								$credovaUserName = $credovaCredentials['username'];
								$credovaPassword = $credovaCredentials['password'];
								$credovaTest = $credovaCredentials['test_environment'];
								$credovaPaymentMethodId = $credovaCredentials['credova_payment_method_id'];

								if (!empty($credovaUserName)) {
									?>
									<div id="credova_information" class="hidden">
										<h2>Credova Information</h2>
										<?php echo createFormLineControl("phone_numbers", "phone_number", array("column_name" => "mobile_phone", "form_label" => "Mobile Phone", "help_label" => "Same number used in Credova process", "not_null" => true, "data-conditional-required" => "!$(\"#credova_information\").hasClass(\"hidden\")")) ?>
									</div>
									<?php
								}

								$finalizeOrderText = $this->getPageTextChunk("retail_store_finalize_order");
								if (empty($processingOrderText)) {
									$finalizeOrderText = $this->getFragment("retail_store_finalize_order");
								}
								if (!empty($finalizeOrderText)) {
									echo makeHtml($finalizeOrderText);
								}

								$processingOrderText = $this->getPageTextChunk("retail_store_processing_order");
								if (empty($processingOrderText)) {
									$processingOrderText = $this->getFragment("retail_store_processing_order");
								}
								if (empty($processingOrderText)) {
									$processingOrderText = "Order being processed and created. DO NOT hit the back button.";
								}
								echo "<div id='processing_order' class='hidden'>" . makeHtml($processingOrderText) . "</div>";
								?>
								<p class='loyalty-points-awarded'></p>

							</div>
						</div>

						<p class='error-message'></p>
						<div id='_checkout_buttons'>
							<button id="return_to_cart">Return to Cart</button>
							<button id='checkout_next_button' class='checkout-next-button'>Next</button>
							<button class='disabled-button' id="_finalize_order">Place Order</button>
						</div>

						<?php

						# local server might include additional checks to assure the customer can check out. If the function exists, it can display some elements
						# to the page. If an element has a class of "checkout-not-allowed", the checkout button will not allow the process to continue.
						# However this function could also simply display some generated content for informational purposes. It would even be possible to include a form
						# in this section and process the data using _localServerProcessOrder

						if (function_exists("_localServerAfterCheckoutButtonContent")) {
							_localServerAfterCheckoutButtonContent();
						}
						$sectionText = $this->getPageTextChunk("retail_store_shopping_cart_after_items");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_shopping_cart_after_items");
						}
						echo $sectionText;
						?>

						<input type="hidden" id="valid_payment_methods" value="">
						<input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">
						<?php
						$publicIdentifier = getFieldFromId("public_identifier", "credova_loans", "contact_id", $contactId, "order_id is null");
						if (empty($publicIdentifier)) {
							$publicIdentifier = $_COOKIE['credova_public_identifier'];
						}
						?>
						<input type="hidden" id="public_identifier" name="public_identifier" value="<?= htmlText($publicIdentifier) ?>">
						<input type="hidden" id="authentication_token" name="authentication_token" value="">
					</div>
				</div>
			</form>
			<?php if (!empty($this->getPageTextChunk("wish_list_products"))) { ?>
				<div id="wish_list_products_wrapper" class="hidden cart-element">
					<h2>From Your Wishlist</h2>
					<div id="wish_list_products">
					</div>
				</div>
			<?php } ?>

			<?php if (!empty($this->getPageTextChunk("also_interested_products"))) { ?>
				<div id="also_interested_products_wrapper" class="hidden cart-element">
					<h2>You also might be interested</h2>
					<div id="also_interested_products">
					</div>
				</div>
			<?php } ?>

			<?php if (!empty($this->getPageTextChunk("related_products"))) { ?>
				<div id="related_products_wrapper" class="hidden cart-element user-logged-in">
					<h2>Related Products</h2>
					<div id="related_products">
					</div>
				</div>
			<?php } ?>
		</div>
		<div id="_user_account_wrapper" class='hidden'>
			<?php
			if ($shoppingCart->requiresUser()) {
				$sectionText = $this->getFragment("retail_store_no_guest_checkout");
				if (empty($sectionText)) {
					ob_start();
					?>
					<p class='red-text'>The contents of this shopping cart requires a user login. Please create an account or log in to order these products.</p>
					<?php
					$sectionText = ob_get_clean();
				}
			} else {
				$sectionText = $this->getFragment("retail_store_login_text");
				if (empty($sectionText)) {
					ob_start();
					?>
					<p>Creating an account will allow you to track most orders, save payment methods and
						shipping addresses, and gain access to special user discounts.
						If you already have an account, login below. If not, we need your email address
						so we can send you an order confirmation and tracking details when it ships.
						If you wish to create an account, we need minimal information to create it.</p>
					<?php
					$sectionText = ob_get_clean();
				}
			}
			echo $sectionText;
			?>
			<p class='error-message'></p>
			<div id="_user_account_options_wrapper">
				<div id='_login_form_wrapper'>
					<h3>Login Now</h3>
					<p id="login_now_intro">If you already have an account, enter your user name & password to sign in.</p>
					<form id="_login_form" method='post'>
						<input type='hidden' name='from_form' value='<?= getRandomString(8) ?>'>
						<div class='form-line'>
							<input tabindex="10" type='text' id='login_user_name' name='login_user_name'
								   size='25' maxlength='40' class='lowercase code-value allow-dash validate[required]'
								   placeholder="Username" value="<?= htmlText($loginUserName) ?>"/>
						</div>
						<div class='form-line'>
							<div class='position-relative'>
								<input tabindex="10" type='password' id='login_password' class='validate[required]'
									   name='login_password' size='25' maxlength='60'
									   placeholder="Password"/><span class='fad fa-eye show-password'></span>
							</div>
						</div>
						<p>
							<button tabindex="10" id="login_now_button">Sign In</button>
						</p>
						<p><a id='access_link' href="/login?forgot_password=true">Forgot your Username or Password?</a></p>
					</form>
					<p id="logging_in_message" class='user-account-message'></p>
				</div>
				<div id='_guest_form_wrapper'>
					<h3>Guest Checkout</h3>
					<?php
					$sectionText = $this->getFragment("retail_store_guest_checkout");
					if (empty($sectionText)) {
						ob_start();
						?>
						<p>Email is required if you are checking out as a guest.</p>
						<?php
						$sectionText = ob_get_clean();
					}
					echo $sectionText;
					?>
					<form id="_guest_form" method='post'>
						<?php
						echo createFormLineControl("contacts", "first_name", array("column_name" => "guest_first_name", "no_required_label" => true, "not_null" => true));
						echo createFormLineControl("contacts", "last_name", array("column_name" => "guest_last_name", "no_required_label" => true, "not_null" => true));
						echo createFormLineControl("contacts", "email_address", array("column_name" => "guest_email_address", "no_required_label" => true, "not_null" => !$GLOBALS['gInternalConnection']));
						?>
						<p id="_guest_checkout">
							<button tabindex="10" id="guest_checkout">Continue Checkout</button>
						</p>
						<p id="guest_checkout_message" class='user-account-message'></p>
						<div id="_confirm_guest_checkout" class='hidden'>
							<p>
								<button tabindex='10' id='confirm_guest_checkout'>Checkout as Guest Anyway</button>
							</p>
						</div>
					</form>
				</div>
				<div id='_create_user_form_wrapper'>
					<h3>Create User Account</h3>
					<form id="_create_user_form" method='post'>
						<?php
						$sectionText = $this->getFragment("retail_store_create_account");
						echo $sectionText;
						echo createFormLineControl("contacts", "email_address", array("column_name" => "create_account_email_address", "no_required_label" => true, "not_null" => true));
						echo createFormLineControl("contacts", "first_name", array("column_name" => "create_account_first_name", "no_required_label" => true, "not_null" => true));
						echo createFormLineControl("contacts", "last_name", array("column_name" => "create_account_last_name", "no_required_label" => true, "not_null" => true));
						echo createFormLineControl("users", "user_name", array("not_null" => false, "help_label" => "Leave blank to use email address"));
						?>
						<p id="_user_name_message"></p>
						<div class="form-line" id="password_row">
							<label for="password">Password</label>
							<?php
							$minimumPasswordLength = getPreference("minimum_password_length");
							if (empty($minimumPasswordLength)) {
								$minimumPasswordLength = 10;
							}
							?>
							<div class='position-relative'>
								<input tabindex="10" autocomplete="chrome-off" autocomplete="off"
									   class="password-strength validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]]"
									   type="password" size="40" maxlength="40" id="password"
									   name="password" value=""><span class='fad fa-eye show-password'></span>
							</div>
							<div class='strength-bar-div hidden' id='password_strength_bar_div'>
								<p class='strength-bar-label' id='password_strength_bar_label'></p>
								<div class='strength-bar' id='password_strength_bar'></div>
							</div>
							<div class='clear-div'></div>
						</div>

						<div class="form-line" id="_password_again_row">
							<label for="password_again">Re-enter Password</label>
							<div class='position-relative'>
								<input tabindex="10" autocomplete="chrome-off" autocomplete="off"
									   class="validate[equals[password],minSize[<?= $minimumPasswordLength ?>]]"
									   type="password" size="40" maxlength="40" id="password_again"
									   name="password_again" value=""><span class='fad fa-eye show-password'></span>
							</div>
							<div class='clear-div'></div>
						</div>
					</form>
					<p>
						<button tabindex="10" id="create_account">Create account and continue checkout</button>
					</p>
					<p id="create_account_message" class='user-account-message'></p>
				</div>
			</div>
		</div>
		<?php
		return true;
	}

	function internalCSS() {
		?>
		<style>
			.signature-palette-parent {
				background-color: rgb(255, 255, 255);
				padding: 0px;
				height: 120px;
			}

			#signature_palette {
				background-color: rgb(255, 255, 255);
			}

			#signature_palette .jSignature {
				width: 600px;
				background-color: rgb(255, 255, 255);
			}

			#signature_palette input {
				bottom: auto !important;
				left: auto !important;
				top: 0px;
				right: 0px;
			}

			#signature_palette .sign-here-image {
				display: none !important;
			}

			#cvv_code_<?= $this->iPrimaryPaymentMethodNumber ?> {
				background-image: url('/images/cvv.png');
				background-position: top right;
				background-size: contain;
				background-repeat: no-repeat;
			}

			#_account_number_<?= $this->iPrimaryPaymentMethodNumber ?>_row {

			#account_number_<?= $this->iPrimaryPaymentMethodNumber ?> {
				background-image: url('/images/credit_card_icons.png');
				background-repeat: no-repeat;
				padding-left: 60px;
				max-width: unset;
				background-size: 45px 197px;
				background-position: 7px 7px;
			}

			}

			.checkout-section .checkout-next-button {
				position: absolute;
				bottom: 10px;
				right: 10px;
			}

			#_shopping_cart_wrapper input[type=text]::placeholder {
				font-weight: 900;
				color: rgb(180, 180, 180);
			}

			#shipping_type_wrapper div {
				width: auto;
				padding: 0 40px 0 0;
			}

			.checkout-section.not-active {
				cursor: pointer;
			}

			.checkout-section.not-active .checkout-next-button {
				display: none;
			}

			.form-line label.checkbox-label {
				font-size: .8rem;
				margin-left: 5px;
			}

			#_payment_method_id_options {
				display: flex;
				flex-wrap: wrap;
			}

			.payment-method-id-option {
				width: 70px;
				height: 60px;
				border-radius: 4px;
				background-color: rgb(240, 240, 240);
				margin: 10px;
				flex: 0 0 auto;
				cursor: pointer;
				opacity: .5;
				position: relative;
			}

			.payment-method-id-option-content {
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				text-align: center;
			}

			.info-section-content .payment-method-id-option span {
				font-size: 1.5rem;
			}

			.info-section-content .payment-method-id-option p {
				text-align: center;
				font-size: .5rem;
				margin: 5px 0 0 0;
				padding: 0;
				max-width: 75px;
			}

			.payment-method-id-option.active {
				opacity: 1;
				background-color: rgb(245, 250, 255);
			}

			#_order_upsell_product_template {
				display: none;
			}

			.form-line select.billing-state-select {
				width: 150px;
				min-width: 150px;
			}

			#_billing_address_wrapper .form-line {
				width: 100%;
				display: block;
			}

			#_billing_address_wrapper .form-line.inline-block {
				width: auto;
				display: inline-block;
			}

			.form-line input[type=text].payment-amount {
				text-align: right;
				font-size: 1rem;
				color: rgb(0, 0, 0);
				font-weight: 700;
				width: 120px;
			}

			.fad.remove-applied-payment-method {
				font-size: 16px;
				position: absolute;
				right: 10px;
				top: 50%;
				transform: translate(0, -50%);
				cursor: pointer;
			}

			.payment-method-section-wrapper .info-section {
				display: none;
			}

			.payment-method-section-wrapper.applying .info-section, .payment-method-section-wrapper.applied .info-section {
				display: block;
			}

			.payment-method-section-wrapper .applied-payment-method-fields {
				display: none;
			}

			.payment-method-section-wrapper.applying .applied-payment-method-fields {
				display: block;
			}

			.payment-method-section-wrapper.applying .apply-payment-method, .payment-method-section-wrapper.applied .apply-payment-method {
				display: none;
			}

			.payment-method-section-wrapper.applied .apply-payment-method-buttons {
				display: none;
			}

			#_summary_cart_order_upsell_products {
				margin-bottom: 10px;
			}

			#_summary_cart_order_upsell_products p {
				padding: 0;
				margin: 0;
			}

			#add_new_payment {
				font-size: .8rem;
			}

			.checkout-section {
				height: 60px;
				border: 1px solid rgb(240, 240, 240);
				border-radius: 5px;
				margin-bottom: 5px;
				padding: 0 10px;
				position: relative;
			}

			#payment_information {
				padding-bottom: 40px;
			}

			.checkout-section h2 {
				opacity: .5;
				padding-top: 10px;
			}

			.checkout-section.active {
				height: auto;
			}

			.checkout-section.active h2 {
				opacity: 1;
			}

			.checkout-section-content {
				display: none;
			}

			.checkout-section.active .checkout-section-content {
				display: block;
			}

			#_quick_confirmation_details p {
				font-size: 1rem;
			}

			#quick_checkout_total {
				font-weight: 700;
			}

			#quick_confirmation_terms {
				font-size: .75rem;
				margin-top: 40px;
			}

			#quick_confirmation_terms p {
				font-size: .75rem;
			}

			#non_ffl_products_shipping_address, #ffl_products_shipping_address {
				margin: 5px 0 0 40px;
				display: block;
				font-weight: 700;
				line-height: 1.1;
				font-size: .9rem;
			}

			#_promotion_applied_message {
				font-size: .7rem;
				background-color: rgb(200, 200, 240);
				padding: 4px;
				position: relative;
			}

			#_promotion_applied_message .fa-times {
				position: absolute;
				right: 4px;
				font-size: .8rem;
				cursor: pointer;
			}

			.promotion-code {
				font-weight: 900;
			}

			#added_promotion_code, #add_promotion_code {
				font-size: .7rem;
				background-color: rgb(200, 200, 240);
				padding: 4px;
				position: relative;
			}

			#_promotion_message {
				cursor: pointer;
				font-size: .9rem;
				color: rgb(60, 120, 80);
				position: relative;
			}

			.distance--miles {
				display: none;
			}

			#_finalize_order {
				font-size: 1.2rem;
				padding: 10px 20px;
				font-weight: 900;
			}

			.form-line .editable-list-data-row input[type=text] {
				max-width: 150px;
			}

			.pickup-location-choice {
				cursor: pointer;
			}

			#promotion_code_wrapper {
				white-space: nowrap;
			}

			#apply_promo {
				padding: 4px 6px;
				margin-left: 20px;
			}

			.delivery-address-wrapper {
				cursor: pointer;
			}

			#wish_list_products {
				display: flex;
				flex-wrap: wrap;
			}

			#wish_list_products .catalog-item {
				max-width: 250px;
				margin-right: 20px;
				border: 1px solid rgb(220, 220, 220);
				padding: 10px;
			}

			#wish_list_products .catalog-item-compare-wrapper {
				display: none;
			}

			#wish_list_products .catalog-item-add-to-wishlist {
				display: none;
			}

			#wish_list_products .catalog-item-location-availability {
				display: none;
			}

			#wish_list_products .catalog-item-credova-financing {
				display: none;
			}

			#related_products {
				display: flex;
				flex-wrap: wrap;
			}

			#related_products .catalog-item {
				max-width: 250px;
				margin-right: 20px;
				border: 1px solid rgb(220, 220, 220);
				padding: 10px;
			}

			#_ffl_selection_dialog li {
				padding: 5px;
			}

			#_ffl_selection_dialog .have-license {
				background-color: rgb(180, 230, 180);
			}

			#_ffl_selection_dialog .preferred {
				font-weight: 900;
				background-color: rgb(200, 230, 255)
			}

			#_ffl_selection_dialog .restricted {
				background-color: rgb(250, 210, 210);
			}

			#search_ffl_dealers {
				margin-left: 20px;
			}

			#_terms_conditions_wrapper {
				max-height: 80vh;
				height: 800px;
				overflow: scroll;
			}

			#validated_address_wrapper {
				width: 100%;
				display: flex;
			}

			#validated_address_wrapper div {
				padding: 20px;
				flex: 0 0 50%;
				font-size: 1.2rem;
			}

			.no-action-required {
				display: none;
			}

			.signature-palette-parent {
				color: rgb(10, 30, 150);
				background-color: rgb(180, 180, 180);
				padding: 20px;
				width: 600px;
				max-width: 100%;
				height: 180px;
				position: relative;
			}

			.signature-palette {
				border: 2px dotted black;
				background-color: rgb(220, 220, 220);
				height: 100%;
				width: 100%;
				position: relative;
			}

			#order_notes_content, #order_note {
				height: 40px;
			}

			#_bank_name_row {
				height: 0 !important;
				min-height: 0 !important;
				max-height: 0 !important;
				margin: 0 !important;
				padding: 0 !important;
				overflow: hidden !important;
			}

			#_agree_terms_row {
				height: 0 !important;
				min-height: 0 !important;
				max-height: 0 !important;
				margin: 0 !important;
				padding: 0 !important;
				overflow: hidden !important;
			}

			#_confirm_human_row {
				height: 0 !important;
				min-height: 0 !important;
				max-height: 0 !important;
				margin: 0 !important;
				padding: 0 !important;
				overflow: hidden !important;
			}

			#cvv_image {
				height: 50px;
				top: 10px;
				position: absolute;
				left: 220px;
			}

			#donation_amount {
				font-size: 1.2rem;
				font-weight: 700;
			}

			.payment-method-amount {
				border: 1px solid rgb(200, 200, 200);
				padding: 4px 10px;
				width: 100px;
			}

			#_balance_due_wrapper {
				line-height: 1;
				position: absolute;
				top: 50%;
				right: 10px;
				transform: translate(0, -50%);
				padding: 0;
				font-size: .7rem;
			}

			#_balance_due {
				font-size: .8rem;
				font-weight: 700;
				margin-left: 10px;
			}

			#designation_images_wrapper {
				display: flex;
				flex-wrap: wrap;
			}

			.designation-image {
				width: 150px;
				cursor: pointer;
				flex: 0 0 auto;
				margin-right: 20px;
				border: 4px solid transparent;
			}

			.designation-image img {
				max-width: 100%;
			}

			.designation-image.selected {
				border: 4px solid rgb(80, 185, 250);
			}

			.ffl-choice {
				cursor: pointer;
			}

			.ffl-choice p {
				padding: 0;
				margin: 0;
				font-size: .9rem;
				line-height: 1.2;
			}

			.ffl-choice-business-name {
				font-weight: 700;
			}

			#ffl_dealers_wrapper {
				max-height: 500px;
				overflow: scroll;
			}

			#ffl_dealers_wrapper ul {
				list-style: none;
			}

			#ffl_dealers_wrapper ul li {
				line-height: 1;
				font-size: .8rem;
				border-bottom: 1px solid rgb(220, 220, 220);
			}

			#pickup_locations, #delivery_addresses {
				max-height: 500px;
				overflow: scroll;
				line-height: 1;
			}

			#_choose_delivery_address_dialog > p {
				margin: 20px 0;
			}

			.delivery-address-description {
				font-size: 1rem;
				font-weight: 700;
			}

			#ship_to_label {
				font-weight: 700;
			}

			#shipping_calculation_log {
				max-height: 500px;
				overflow: scroll;
			}

			.pickup-location-wrapper {
				padding: 10px 5px;
				border-bottom: 1px solid rgb(220, 220, 220);
			}

			.delivery-address-wrapper {
				padding: 10px 5px;
				border-bottom: 1px solid rgb(220, 220, 220);
			}

			.delivery-address-wrapper span {
				margin-right: 20px;
			}

			.pickup-location-description {
				font-size: 1rem;
				font-weight: 700;
			}

			#calculating_shipping_methods {
				color: rgb(192, 0, 0);
			}

			#ffl_information {
				width: 600px;
				max-width: 90%;
			}

			#ffl_information p {
				font-size: 1rem;
				font-weight: 500;
			}

			.info-section {
				position: relative;
				margin-bottom: 10px;
				padding: 30px 0 10px 0;
				border: 1px solid rgb(220, 220, 220);
				width: 800px;
				max-width: 90%;
			}

			.info-section p {
				margin: 10px 0 10px 10px;
			}

			.info-section-header {
				background-color: rgb(220, 220, 220);
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 30px;
			}

			.info-section-title {
				line-height: 1;
				position: absolute;
				top: 50%;
				left: 10px;
				transform: translate(0, -50%);
				padding: 0;
				font-size: .7rem;
				color: rgb(100, 100, 100);
			}

			.info-section-header .info-section-change {
				line-height: 1;
				position: absolute;
				top: 50%;
				right: 10px;
				transform: translate(0, -50%);
				padding: 0;
				font-size: .7rem;
				text-decoration: underline;
				color: rgb(100, 100, 100);
			}

			.info-section-content {
				padding: 10px 20px 0 20px;
				min-height: 30px;
				line-height: 1.2;
			}

			.info-section-content p {
				line-height: 1.2;
				font-size: .9rem;
				font-weight: 400;
			}

			.info-section .form-line input[type=text] {
				border-color: rgb(240, 240, 240);
				outline: none;
			}

			.info-section .form-line input[type=text]:focus {
				background-color: rgb(240, 240, 240);
			}

			#primary_payment_method_section.no-content {
				padding-bottom: 0;
			}

			#primary_payment_method_section.no-content .info-section-content {
				display: none;
			}

			#primary_payment_method_section.no-content .info-section-title {
				display: none;
			}

			.form-line label {
				margin: 0;
				padding-bottom: 2px;
				font-size: 1rem;
			}

			.form-line .help-label {
				font-size: .8rem;
				color: rgb(150, 150, 150);
				padding-bottom: 5px;
			}

			.form-line select {
				font-size: 1rem;
				border: 1px solid rgb(200, 200, 200);
				-webkit-appearance: none;
				appearance: none;
				background-image: url('../images/select_arrow.png'), -webkit-linear-gradient(#FAFAFA, #F4F4F4 40%, #E5E5E5);
				background-position: 97% center;
				background-repeat: no-repeat;
				padding: 4px 10px;
				border-radius: 4px;
				margin: 0;
				min-width: 150px;
				padding-right: 40px;
			}

			.form-line select:focus {
				outline: none;
				box-shadow: 0 0 0 2px rgba(21, 156, 228, 0.4);
			}

			.form-line textarea {
				width: 700px;
				max-width: 100%;
			}

			.form-line input[type="text"], .form-line select, .form-line textarea {
				width: auto;
			}

			button#edit_cart {
				font-size: 90%;
				padding: 5px 40px;
			}

			ul {
				margin-bottom: 20px;
			}

			#promotion_code_details {
				border: 1px solid rgb(100, 100, 100);
				border-radius: 1px;
				background-color: rgb(254, 254, 254);
				padding: 20px 20px 10px 20px;
				max-height: 200px;
				overflow-y: scroll;
			}

			#promotion_code_details p {
				padding: 0 0 10px 0;
				margin: 0;
			}

			.jSignature {
				max-width: 100%;
				max-height: 100%;
			}

			.user-account-message {
				font-size: 1rem;
				color: rgb(15, 180, 50);
				font-weight: 700;
			}

			.show-password {
				z-index: 1000;
				position: absolute;
				right: 10px;
				top: 50%;
				transform: translate(0, -50%);
			}

			input {
				font-size: 1rem;
				padding: 5px 10px;
				border: 1px solid rgb(200, 200, 200);
			}

			button {
				font-size: .8rem;
				padding: 8px 12px;
				border-radius: 2px;
				margin: 0;
			}

			button.disabled-button {
				cursor: auto;
				opacity: .5;
			}

			button.disabled-button:hover {
				background-color: rgb(255, 255, 255);
				color: rgb(200, 200, 200);
			}

			#_finalize_order.disabled-button {
				display: none;
			}

			#_user_account_options_wrapper {
				display: flex;
				margin: 20px 0;
			}

			#_user_account_options_wrapper > div {
				margin: 0 30px 0 0;
				background-color: rgb(240, 240, 240);
				min-height: 200px;
				padding: 30px;
				flex: 0 0 30%;
				position: relative;
			}

			#_user_account_options_wrapper input {
				width: 100%;
			}

			#_user_account_options_wrapper button {
				width: 100%;
			}

			#promotion_code {
				width: 150px;
			}

			#_cart_loading {
				font-size: 64px;
				color: rgb(200, 200, 200);
			}

			#_shopping_cart_wrapper .checkout-element {
				display: none;
			}

			body.checkout #_shopping_cart_wrapper .cart-element {
				display: none;
			}

			body.checkout #_shopping_cart_wrapper .checkout-element {
				display: block;
			}

			#_shopping_cart_contents {
				display: flex;
				width: 100%;
				margin: 0 auto;
				transition: all 0.5s ease;
				position: relative;
			}

			#_shopping_cart_items_wrapper {
				max-width: 100%;
				flex: 0 0 75%;
			}

			#_shopping_cart_summary_wrapper {
				flex: 0 0 25%;
				padding-left: 40px;
			}

			#_checkout_process_wrapper {
				margin-top: 75px;
				flex: 0 0 75%;
				display: none;
			}

			#_shopping_cart_summary > div#_summary_cart_contents_wrapper {
				display: none;
				margin-bottom: 30px;
			}

			.summary-cart-item {
				display: flex;
				margin: 0;
				padding: 0 0 10px 0;
			}

			.summary-cart-item-image {
				flex: 0 0 20%;
			}

			.summary-cart-item-image img {
				max-width: 100%;
				max-height: 50px;
			}

			.summary-cart-item-description {
				flex: 0 0 60%;
				padding: 0 10px;
			}

			.summary-cart-product-description {
				font-size: .8rem;
			}

			.summary-cart-item .summary-cart-item-price-wrapper {
				flex: 0 0 20%;
				text-align: right;
			}

			.summary-cart-item .summary-cart-product-quantity {
				font-size: .6rem;
			}

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents {
				flex-direction: row-reverse;
			}

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_shopping_cart_summary_wrapper {
				margin-top: 75px;
			}

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_summary_cart_contents_wrapper {
				display: block;
			}

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_checkout_process_wrapper {
				display: block;
			}

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_shopping_cart_items_wrapper {
				display: none;
			}

			#_shopping_cart_summary {
				background-color: rgb(240, 240, 240);
				padding: 20px;
				z-index: 10000;
			}

			#_shopping_cart_contents #_shopping_cart_summary.fixed {
				position: fixed;
				top: 110px;
				left: 75%;
				width: 20%;
			}

			#_shopping_cart_summary > div {
				clear: both;
				vertical-align: baseline;
				margin: 0 0 10px 0;
			}

			#_shopping_cart_summary div#_total_savings_wrapper {
				color: rgb(0, 100, 0);
				font-weight: 900;
				margin-top: 20px;
			}

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_total_savings_wrapper {
				display: none;
			}

			#_order_total_wrapper {
				font-weight: 900;
			}

			.checkout-credova-button {
				margin: 10px 0;
			}

			#_shopping_cart_summary > div#checkout_button_wrapper {
				margin-top: 30px;
			}

			#continue_checkout, #quick_checkout {
				width: 100%;
			}

			.shopping-cart-item {
				display: flex;
				margin-bottom: 20px;
				background-color: rgb(240, 240, 240);
				flex-wrap: wrap;
			}

			.shopping-cart-item-image {
				flex: 0 0 15%;
				position: relative;
				min-height: 150px;
				background-color: rgb(255, 255, 255);
				border: 2px solid rgb(240, 240, 240);
			}

			.shopping-cart-item-image img {
				max-width: 100%;
				max-height: 150px;
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				padding: 5px 0;
			}

			.shopping-cart-item-description {
				flex: 0 0 70%;
				padding: 20px 20px 0 20px;
				font-size: 1.2rem;
				line-height: 110%;
			}

			.product-info {
				font-size: .7rem;
				font-weight: 700;
				color: rgb(180, 180, 180);
				text-transform: uppercase;
			}

			.product-info span {
				padding-right: 6px;
				max-width: 120px;
			}

			.product-info span:after {
				content: "|";
				font-weight: 300;
				color: rgb(200, 200, 200);
				padding-left: 6px;
			}

			.shopping-cart-item-price {
				flex: 0 0 15%;
				position: relative;
			}

			.fad.remove-item {
				font-size: 1.5rem;
				position: absolute;
				top: 15px;
				right: 15px;
				cursor: pointer;
			}

			.product-quantity-wrapper {
				position: absolute;
				bottom: 30px;
				right: 30px;
				display: flex;
			}

			.product-quantity-wrapper div {
				height: 35px;
				min-width: 35px;
				background-color: rgb(215, 215, 215);
				border: 1px solid rgb(160, 160, 160);
				color: rgb(0, 0, 0);
				font-size: 1rem;
				margin: 0 2px;
				display: block;
				position: relative;
			}

			.product-quantity {
				text-align: center;
				width: 65px;
				font-size: 1rem;
				height: 33px;
				display: block;
				outline: none;
				border: none;
			}

			.product-total {
				color: rgb(0, 100, 0);
				font-size: 1.5rem;
				position: absolute;
				top: 35%;
				right: 30px;
				text-align: right;
				font-weight: 900;
			}

			.product-total:before {
				content: "$";
			}

			.shopping-cart-item-decrease-quantity, .shopping-cart-item-increase-quantity {
				width: 35px;
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				cursor: pointer;
			}

			.shopping-cart-item-price-wrapper {
				font-size: .8rem;
			}

			.shopping-cart-item-price-wrapper > span {
				margin-right: 10px;
			}

			.original-sale-price, .summary-cart-product-original-price {
				text-decoration: line-through;
				font-size: .8rem;
				color: rgb(192, 0, 0);
			}

			.original-sale-price:before {
				content: "$";
			}

			.product-sale-price {
				font-size: .8rem;
			}

			.product-sale-price:before {
				content: "$";
			}

			.product-savings:before {
				content: "$";
			}

			.shopping-cart-item-custom-fields {
				flex: 1 1 100%;
			}

			.shopping-cart-item-item-addons {
				flex: 1 1 100%;
			}

			.addon-select-quantity {
				width: 50px;
			}

			.out-of-stock-notice {
				display: none;
			}

			.out-of-stock .out-of-stock-notice {
				display: block;
				color: rgb(192, 0, 0);
				font-weight: 900;
				font-size: .9rem;
			}

			.no-online-order-notice {
				display: none;
			}

			.no-online-order .no-online-order-notice {
				display: block;
				color: rgb(192, 0, 0);
				font-weight: 900;
				font-size: .9rem;
			}

			.shopping-cart-item-custom-fields {
				padding-left: 20px;
			}

			.shopping-cart-item-custom-fields .form-line {
				margin: 10px 0 10px 0;
			}

			.shopping-cart-item-custom-fields .form-line:first-child {
				margin-top: 20px;
			}

			.shopping-cart-item-custom-fields .form-line input[type=text], .shopping-cart-item-custom-fields .form-line input[type=password], .shopping-cart-item-custom-fields .form-line textarea {
				margin: 0;
			}

			.shopping-cart-item-custom-fields .form-line input[type=text]:focus, .shopping-cart-item-custom-fields .form-line input[type=password]:focus, .shopping-cart-item-custom-fields .form-line textarea:focus {
				outline: none;
				box-shadow: 0 0 0 2px rgba(21, 156, 228, 0.4);
			}

			.shopping-cart-item-restrictions {
				background-color: rgb(250, 220, 220);
				color: rgb(200, 0, 0);
			}

			.shopping-cart-item-item-addons {
				padding-left: 20px;
			}

			.shopping-cart-item-item-addons .form-line {
				margin: 10px 0 10px 0;
			}

			.shopping-cart-item-item-addons .form-line:first-child {
				margin-top: 20px;
			}

			.shopping-cart-item-item-addons .form-line input[type=text], .shopping-cart-item-item-addons .form-line input[type=password], .shopping-cart-item-item-addons .form-line textarea {
				margin: 0;
			}

			.shopping-cart-item-item-addons .form-line input[type=text]:focus, .shopping-cart-item-item-addons .form-line input[type=password]:focus, .shopping-cart-item-item-addons .form-line textarea:focus {
				outline: none;
				box-shadow: 0 0 0 2px rgba(21, 156, 228, 0.4);
			}

			.form-line input[type=text].product-addon-quantity {
				max-width: 100px;
				text-align: right;
			}

			#_checkout_buttons {
				margin-bottom: 40px;
			}

			#_checkout_buttons button {
				margin-right: 40px;
			}

			@media (max-width: 1100px) {
				#_shopping_cart_contents #_shopping_cart_summary.fixed {
					position: relative;
					top: 0;
					left: 0;
					width: auto;
				}

				button {
					font-size: 1rem;
				}

				#_user_account_options_wrapper {
					display: block;
				}

				#_user_account_options_wrapper > div {
					margin: 0 0 30px 0;
					min-height: 0;
					padding: 20px;
				}

				#_user_account_options_wrapper .form-line {
					max-width: 250px;
				}

				#_user_account_options_wrapper button {
					max-width: 100%;
					width: auto;
				}

				#_shopping_cart_contents {
					display: block;
				}

				body.checkout #_shopping_cart_wrapper #_shopping_cart_contents {
					padding-top: 75px;
				}

				#_shopping_cart_summary_wrapper {
					padding-left: 0;
					width: 100%;
					max-width: 400px;
				}

				body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_shopping_cart_summary_wrapper {
					margin-top: 0;
				}

				.product-quantity-wrapper div {
					height: 25px;
					min-width: 25px;
				}

				.product-quantity {
					height: 23px;
				}
			}

			@media (max-width: 600px) {
				.payment-method-description {
					display: none;
				}

				.checkout-buttons button {
					margin: 0 10px 10px 0;
					font-size: .7rem;
					padding: 5px 10px;
				}

				.product-info {
					display: none;
				}

				.product-savings-wrapper {
					display: none;
				}

				.original-sale-price {
					display: none;
				}

				.product-quantity-wrapper {
					bottom: 10px;
					right: 10px;
				}
			}

		</style>
		<?php
	}

	function javascript() {
		$noShippingRequired = getPreference("RETAIL_STORE_NO_SHIPPING");
		$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
		if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
			$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
		}
		if (empty($missingProductImage)) {
			$missingProductImage = "/images/empty.jpg";
		}
		$additionalPaymentHandlingCharge = getPreference("RETAIL_STORE_ADDITIONAL_PAYMENT_METHOD_HANDLING_CHARGE");
		if (empty($additionalPaymentHandlingCharge)) {
			$additionalPaymentHandlingCharge = 0;
		}

		$pickupLocationDescriptions = array();
		$resultSet = executeQuery("select *,shipping_methods.description as shipping_method_description from shipping_methods left outer join locations using (location_id) left outer join (contacts) using (contact_id) where shipping_methods.inactive = 0 and (locations.inactive = 0 or locations.inactive is null) and pickup = 1 and shipping_methods.client_id = ?" .
			($GLOBALS['gInternalConnection'] ? "" : " and shipping_methods.internal_use_only = 0 and locations.internal_use_only = 0"), $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$address = $row['address_1'];
			$city = trim($row['city'] . (empty($row['city']) || empty($row['state']) ? "" : ", ") . $row['state'] . " " . $row['postal_code']);
			if (!empty($city)) {
				$address .= (empty($address) ? "" : "<br>") . $city;
			}
			if (empty($row['description'])) {
				$row['description'] = $row['shipping_method_description'];
			}
			$pickupLocationDescriptions[$row['shipping_method_id']] = array("shipping_method_id" => $row['shipping_method_id'], "description" => $row['description'], "content" => "<p>" . $row['description'] . (empty($address) ? "" : "<br>" . $address) . "</p>",
				"country_id" => (empty($row['country_id']) ? "1000" : $row['country_id']), "postal_code" => $row['postal_code'], "state" => $row['state']);
		}

		$addressDescriptions = array();
		if ($GLOBALS['gLoggedIn']) {
			$contactId = $GLOBALS['gUserRow']['contact_id'];
		} else {
			$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
			$contactId = $shoppingCart->getContact();
		}
		if (!empty($contactId)) {
			$contactRow = Contact::getContact($contactId);
			$resultSet = executeQuery("select * from addresses where contact_id = ? and address_1 is not null and city is not null and inactive = 0", $contactId);
			$foundBillingAddress = $foundShippingAddress = false;
			while ($row = getNextRow($resultSet)) {
				if ($row['default_billing_address']) {
					$foundBillingAddress = true;
				}
				if ($row['default_shipping_address']) {
					$foundShippingAddress = true;
				}
				$description = (empty($row['address_label']) ? "" : $row['address_label'] . ": ") . $row['address_1'] . ", " . $row['city'];
				$city = trim($row['city'] . (empty($row['state']) ? "" : ", ") . $row['state'] . " " . $row['postal_code']);
				$content = "<p>" . (empty($row['full_name']) ? getDisplayName($contactId) : $row['full_name']) . "<br>" . $row['address_1'] . "<br>" . (empty($row['address_2']) ? "" : $row['address_2'] . "<br>") . $city . "</p>";
				$addressDescriptions[$row['address_id']] = array("address_id" => $row['address_id'], "description" => $description, "content" => $content, "country_id" => $row['country_id'], "postal_code" => $row['postal_code'], "state" => $row['state'], "city" => $row['city'], "address_1" => $row['address_1'], "default_shipping_address" => (!empty($row['default_shipping_address'])), "default_billing_address" => (!empty($row['default_billing_address'])));
			}
			if (!empty($contactRow['address_1']) && !empty($contactRow['city'])) {
				$city = trim($contactRow['city'] . (empty($contactRow['state']) ? "" : ", ") . $contactRow['state'] . " " . $contactRow['postal_code']);
				$content = "<p>" . getDisplayName($contactId) . "<br>" . $contactRow['address_1'] . "<br>" . (empty($contactRow['address_2']) ? "" : $contactRow['address_2'] . "<br>") . $city . "</p>";
				$description = "Primary Address: " . $contactRow['address_1'] . ", " . $contactRow['city'];
				$addressDescriptions["-1"] = array("address_id" => "-1", "description" => $description, "content" => $content, "country_id" => $contactRow['country_id'], "postal_code" => $contactRow['postal_code'], "state" => $contactRow['state'], "city" => $row['city'], "address_1" => $row['address_1'], "default_shipping_address" => !$foundShippingAddress, "default_billing_address" => !$foundBillingAddress);
			}
		}
		$paymentInformation = array();
		$resultSet = executeQuery("select payment_method_id,flat_rate,fee_percent from payment_methods where ((flat_rate is not null and flat_rate > 0) or (fee_percent is not null and fee_percent > 0)) and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$paymentInformation[$row['payment_method_id']] = $row;
		}
		?>
		<script>
			let paymentInformation = <?= jsonEncode($paymentInformation) ?>;
			let pickupLocationDescriptions = <?= jsonEncode($pickupLocationDescriptions) ?>;
			let addressDescriptions = <?= jsonEncode($addressDescriptions) ?>;
			let savedShippingMethodId = false;
			let savedAddressId = false;
			let additionalPaymentHandlingCharge = <?= $additionalPaymentHandlingCharge ?>;
			emptyImageFilename = "<?= $missingProductImage ?>";
			let credovaPaymentMethodId = "", credovaCheckoutCompleted = false, orderInProcess = false;

			<?php
			if (!empty($this->iFflRequiredProductTagId)) {
			?>
			let fflDealers = [];
			<?php
			}
			?>

			function loadAlsoInterestedProducts(productIds) {
				elementId = "also_interested_products";
				if ($("#" + elementId).length == 0) {
					return;
				}
				if ($("#_catalog_result").length === 0) {
					getCatalogResultTemplate(function () {
						loadAlsoInterestedProducts(productIds);
					});
					return;
				}
				$("body").addClass("no-waiting-for-ajax");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products&url_source=also_interested_products&cart_product_ids=" + encodeURIComponent(productIds), function (returnArray) {
					displayLoadedProducts(returnArray, elementId);
					$("#also_interested_products_wrapper").removeClass("hidden");
				});
			}

			function loadWishListProducts() {
				elementId = "wish_list_products";
				if ($("#" + elementId).length == 0) {
					return;
				}
				if ($("#_catalog_result").length === 0) {
					getCatalogResultTemplate(function () {
						loadWishListProducts();
					});
					return;
				}
				$("body").addClass("no-waiting-for-ajax");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products&url_source=wish_list_products", function (returnArray) {
					displayLoadedProducts(returnArray, elementId);
                    if (typeof afterLoadWishlistProducts == "function") {
                        setTimeout(function () {
                            afterLoadWishlistProducts()
                        }, 100);
                    }
					$("#wish_list_products_wrapper").removeClass("hidden");
				});
			}

			function shippingValidation() {
				if ($("#_shipping_method_id_row").hasClass("not-required")) {
					return true;
				}
				if ($("#shipping_type_pickup").prop("checked")) {
					if (empty($("#shipping_method_id").val())) {
						displayErrorMessage("Pickup Location is required");
						return false;
					}
				} else {
					if (empty($("#shipping_method_id").val())) {
						displayErrorMessage("Shipping method is required");
						return false;
					}
					if (empty($("#address_id").val())) {
						displayErrorMessage("Shipping Address is missing");
						return false;
					}
				}
				if (empty($("#federal_firearms_licensee_id").val()) && $("#federal_firearms_licensee_id").hasClass("validate-hidden") && ($("#ffl_dealer_not_found").length == 0 || !$("#ffl_dealer_not_found").prop("checked")) && !$("#has_cr_license").prop("checked")) {
					displayErrorMessage("FFL is required for Firearms purchase");
					return false;
				}
				return true;
			}

			function paymentValidation() {
				let foundCredova = false;
				if (!empty(credovaPaymentMethodId)) {
					$(".payment-method-id").each(function () {
						if ($(this).val() == credovaPaymentMethodId) {
							foundCredova = true;
						}
					});
				}
				if (!foundCredova) {
					$("#credova_information").addClass("hidden");
				} else {
					$("#credova_information").removeClass("hidden");
				}
				if ($(".payment-method-section-wrapper.applying").length > 0) {
					displayErrorMessage("Finish applying payment methods");
					return false;
				}
				if (!$("#primary_payment_method_section").validationEngine("validate")) {
					return false;
				}
				const balanceDue = parseFloat($("#_balance_due").html().replace(new RegExp(",", "g"), ""));
				if (balanceDue > 0) {
					displayErrorMessage("Payment is required.");
					return false;
				}
				return true;
			}

			function getShippingMethods(dontResetDefault) {
				shippingMethodCountryId = $("#country_id").val();
				shippingMethodState = $("#state").val();
				shippingMethodPostalCode = $("#postal_code").val();
				if (empty(savedShippingMethodId)) {
					savedShippingMethodId = $("#shipping_method_id").val();
				}
				$("#shipping_method_id").val("").find("option").unwrap("span");
				$("#shipping_method_id").val("").find("option[value!='']").remove();
				$("#_shipping_method_id_row").addClass("hidden");
				$("#calculating_shipping_methods").removeClass("hidden");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shipping_methods", {state: $("#state").val(), postal_code: $("#postal_code").val(), country_id: $("#country_id").val()}, function (returnArray) {
					$("#shipping_method_id").val("").find("option").unwrap("span");
					$("#shipping_method_id").val("").find("option[value!='']").remove();
					let shippingMethodCount = 0;
					let singleShippingMethodId = "";

					let pickupShippingMethodFound = false;
					let deliveryShippingMethodFound = false;
					if ("shipping_methods" in returnArray) {
						for (const i in returnArray['shipping_methods']) {
							const thisOption = $("<option></option>").data("shipping_method_code", returnArray['shipping_methods'][i]['shipping_method_code']).data("shipping_charge", returnArray['shipping_methods'][i]['shipping_charge']).data("pickup", returnArray['shipping_methods'][i]['pickup']).attr("value", returnArray['shipping_methods'][i]['key_value']).text(returnArray['shipping_methods'][i]['description']);
							$("#shipping_method_id").append(thisOption);
							if (returnArray['shipping_methods'][i]['pickup']) {
								pickupShippingMethodFound = true;
							} else {
								deliveryShippingMethodFound = true;
							}
							singleShippingMethodId = returnArray['shipping_methods'][i]['key_value'];
							shippingMethodCount++;
						}
					}
					if ("shipping_calculation_log" in returnArray && $("#shipping_calculation_log").length > 0) {
						$("#shipping_calculation_log").html(returnArray['shipping_calculation_log']);
					}
					if (!pickupShippingMethodFound && !deliveryShippingMethodFound) {
						$("#_shipping_error").html("No shipping methods found for this location.").removeClass("hidden");
					} else {
						$("#_shipping_error").addClass("hidden");
					}
					if (pickupShippingMethodFound && deliveryShippingMethodFound) {
						$("#shipping_type_wrapper").removeClass("hidden");
					} else {
						$("#shipping_type_wrapper").addClass("hidden");
					}
					if (!dontResetDefault && empty(savedShippingMethodId) && "default_location_shipping_method_id" in returnArray) {
						$("#shipping_type_pickup").trigger("click");
						if (savedShippingMethodId != returnArray['default_location_shipping_method_id']) {
							$("#shipping_method_id").data("force_tax_recalculation", true);
						}
						savedShippingMethodId = returnArray['default_location_shipping_method_id'];
					}
					if (dontResetDefault) {
						savedShippingMethodId = false;
					}
					if (!empty(savedShippingMethodId)) {
						if ($("#shipping_method_id option[value='" + savedShippingMethodId + "']").length > 0) {
							$("#shipping_method_id").val(savedShippingMethodId).trigger("change");
							if (empty($("#shipping_method_id option[value='" + savedShippingMethodId + "']").data("pickup"))) {
								$("#shipping_type_delivery").trigger("click");
							} else {
								$("#shipping_type_pickup").trigger("click");
								$("#_pickup_location_section").data("shipping_method_id", savedShippingMethodId);
							}
						} else {
							savedShippingMethodId = "";
						}
					} else if (empty(savedShippingMethodId) && shippingMethodCount === 1 && !empty(singleShippingMethodId)) {
						$("#shipping_method_id").val(singleShippingMethodId).removeClass("hidden").trigger("change");
						$("#shipping_type_delivery").trigger("click");
					} else if (deliveryShippingMethodFound) {
						$("#shipping_type_delivery").trigger("click");
					} else {
						$("#shipping_type_pickup").trigger("click");
					}
					$("#calculating_shipping_methods").addClass("hidden");
					if (typeof afterGetShippingMethods == "function") {
						setTimeout(function () {
							afterGetShippingMethods(returnArray);
						}, 100);
					}
				});
			}

			function getTaxCharge() {
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_tax_charge", {shipping_method_id: $("#shipping_method_id").val(), country_id: $("#country_id").val(), city: $("#city").val(), postal_code: $("#postal_code").val(), state: $("#state").val(), address_1: $("#address_1").val()}, function (returnArray) {
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

			function coreAfterGetShoppingCartItems() {
				if ($(".product-tag-class_3").length > 0) {
					$(".class-3-notice").removeClass("hidden");
				} else {
					$(".class-3-notice").addClass("hidden");
				}
				<?php if ($noShippingRequired) { ?>
				getFFLTaxCharge();
				<?php } else { ?>
				getTaxCharge();
				<?php } ?>
				<?php if (!empty($_GET['place_order'])) { ?>
				setTimeout(function () {
					$("#continue_checkout").trigger("click");
				}, 100);
				return;
				<?php } ?>
				if ($("#related_products").length > 0) {
					let productIds = "";
					$("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
						productIds += (empty(productIds) ? "" : ",") + $(this).data("product_id");
					});
					loadRelatedProducts(productIds);
				}
				if ($("#also_interested_products").length > 0) {
					let productIds = "";
					$("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
						productIds += (empty(productIds) ? "" : ",") + $(this).data("product_id");
					});
					loadAlsoInterestedProducts(productIds);
				}
				if ($("#wish_list_products").length > 0) {
					loadWishListProducts();
				}
			}

			function afterAddToCart(productId, quantity) {
			}

			function getFFLTaxCharge() {
				<?php if ($noShippingRequired) { ?>
				$("body").addClass("no-waiting-for-ajax");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_tax_charge", {federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val()}, function (returnArray) {
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
				<?php } ?>
			}

			function calculateOrderTotal() {
				<?php if ($GLOBALS['gInternalConnection']) { ?>
				if ($("#tax_exempt").prop("checked")) {
					$("#tax_charge").val("0");
					$(".tax-charge").html("0.00");
				}
				<?php } ?>
				let foundCredova = false;
				if (!empty(credovaPaymentMethodId)) {
					$(".payment-method-id").each(function () {
						if ($(this).val() == credovaPaymentMethodId) {
							foundCredova = true;
						}
					});
				}
				if (!foundCredova) {
					$("#credova_information").addClass("hidden");
				} else {
					$("#credova_information").removeClass("hidden");
				}
				var totalSavings = 0;
				$("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
					if ($(this).find(".product-savings").length > 0) {
						const thisSavings = parseFloat($(this).find(".product-savings").html() * $(this).find(".product-quantity").val());
						totalSavings += thisSavings;
					}
				});
				$("#total_savings").val(totalSavings);
				if (totalSavings > 0) {
					$(".total-savings").html(RoundFixed(totalSavings, 2));
					$("#_total_savings_wrapper").removeClass("hidden");
				} else {
					$(".total-savings").html("0.00");
					$("#_total_savings_wrapper").addClass("hidden");
				}
				var cartTotal = parseFloat(empty($("#cart_total").val()) ? 0 : $("#cart_total").val());
				var taxChargeString = $("#tax_charge").val();
				var taxCharge = (empty(taxChargeString) ? 0 : parseFloat(taxChargeString));
				var shippingChargeString = $("#shipping_method_id option:selected").data("shipping_charge");
				var shippingCharge = (empty(shippingChargeString) ? 0 : parseFloat(shippingChargeString));
				$(".shipping-charge").each(function () {
					if ($(this).is("input")) {
						$(this).val(shippingCharge);
					} else {
						$(this).html(RoundFixed(shippingCharge, 2));
					}
				});

				let paymentMethodCount = 0;
				$(".payment-method-id").each(function () {
					if (!empty($(this).val())) {
						paymentMethodCount++;
					}
				});
				var handlingCharge = (paymentMethodCount > 0 ? additionalPaymentHandlingCharge * (paymentMethodCount - 1) : 0);

				$(".payment-method-id").each(function () {
					const paymentMethodId = $(this).val();
					if (empty(paymentMethodId)) {
						return true;
					}
					if (!(paymentMethodId in paymentInformation)) {
						return true;
					}
					var flatRate = parseFloat(paymentInformation[paymentMethodId]['flat_rate']);
					if (empty(flatRate) || isNaN(flatRate)) {
						flatRate = 0;
					}
					var feePercent = parseFloat(paymentInformation[paymentMethodId]['fee_percent']);
					if (empty(feePercent) || isNaN(feePercent)) {
						feePercent = 0;
					}
					if (flatRate == 0 && feePercent == 0) {
						return true;
					}
					const paymentAmount = parseFloat($(this).closest(".payment-method-wrapper").find(".payment-amount").val());
					if (empty(paymentAmount) || isNaN(paymentAmount)) {
						return true;
					}
					handlingCharge += Round(flatRate + (paymentAmount * feePercent / 100), 2);
				});
				$(".handling-charge").each(function () {
					if ($(this).is("input")) {
						$(this).val(handlingCharge);
					} else {
						$(this).html(RoundFixed(handlingCharge, 2));
					}
				});

				var orderTotal = RoundFixed(cartTotal + taxCharge + shippingCharge + handlingCharge, 2, true);
				if ($("#donation_amount").length > 0) {
					var donationAmountString = $("#donation_amount").val();
					var donationAmount = (empty(donationAmountString) ? 0 : parseFloat(donationAmountString));
					if (isNaN(donationAmount)) {
						$("#donation_amount").val("");
						donationAmount = 0;
					}
					$(".donation-amount").each(function () {
						if ($(this).is("input")) {
							$(this).val(donationAmount);
						} else {
							$(this).html(RoundFixed(donationAmount, 2));
						}
					});
					orderTotal = parseFloat(orderTotal) + parseFloat(donationAmount);
				}
				orderTotal = RoundFixed(orderTotal, 2, true);
				let unlimitedPaymentFound = false;
				let totalPayment = 0;
				let $unlimitedPaymentElement = false;
				$(".payment-method-wrapper").each(function () {
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
					if (empty(maxAmount) && $(this).find(".primary-payment-method").val() == "1") {
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

				var discountAmount = $("#discount_amount").val();
				if (empty(discountAmount) || isNaN(discountAmount)) {
					discountAmount = 0;
				}
				var discountPercent = $("#discount_percent").val();
				if (empty(discountPercent) || isNaN(discountPercent)) {
					discountPercent = 0;
				}
				if (discountAmount == 0 && discountPercent > 0) {
					discountAmount = Round(cartTotal * (discountPercent / 100), 2);
				}
				if (discountAmount < 0) {
					discountAmount = 0;
				}
				orderTotal = RoundFixed(orderTotal - discountAmount, 2, true);
				if (discountAmount > 0) {
					$("#order_summary_discount_wrapper").removeClass("hidden");
					$(".discount-amount").html(RoundFixed(discountAmount, 2));
				} else {
					$("#order_summary_discount_wrapper").addClass("hidden");
					$(".discount-amount").html("0.00");
				}
				if (orderTotal <= 0) {
					$(".payment-section").addClass("not-required").addClass("no-action-required").addClass("hidden");
				} else {
					$(".payment-section").removeClass("not-required").removeClass("no-action-required").removeClass("hidden");
				}

				$(".order-total").each(function () {
					if ($(this).is("input")) {
						$(this).val(RoundFixed(orderTotal, 2, true));
					} else {
						$(this).html(RoundFixed(orderTotal, 2, false));
					}
				});
				$(".credova-button").attr("data-amount", RoundFixed(orderTotal, 2, true));
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
				$(".hide-if-zero").each(function () {
					let thisValue = parseFloat($(this).find(".hide-if-zero-value").html());
					if (thisValue == 0) {
						$(this).addClass("hidden");
					} else {
						$(this).removeClass("hidden");
					}
				})
				if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item").length > 0) {
					setTimeout(function () {
						recalculateBalance();
					}, 100);
				}
				return orderTotal;
			}

			function fillSummaryCart() {
				$("#_summary_cart_contents").html("");
				$("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
					let summaryCartItem = $("#_checkout_summary_cart").html();
					summaryCartItem = summaryCartItem.replace("%image%", $(this).find(".shopping-cart-item-image").html());
					summaryCartItem = summaryCartItem.replace("%description%", $(this).find(".product-description").html());
					summaryCartItem = summaryCartItem.replace("%sale_price%", $(this).find(".product-total").html());
					summaryCartItem = summaryCartItem.replace("%original_price%", $(this).find(".original-sale-price").html());
					summaryCartItem = summaryCartItem.replace("%quantity%", $(this).find(".product-quantity").val());
					$("#_summary_cart_contents").append(summaryCartItem);
				});
				$("#_summary_cart_order_upsell_products").html("");
				$("#_order_upsell_products").find(".order-upgrade-product-wrapper").each(function () {
					if ($(this).find(".order-upgrade-product-id").prop("checked")) {
						let orderUpsellProduct = $(this).find("label").html();
						$("#_summary_cart_order_upsell_products").append("<p>" + orderUpsellProduct + "</p>");
					}
				});
				if ($().prettyPhoto) {
					$("#_summary_cart_contents").find("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({social_tools: false, default_height: 480, default_width: 854, deeplinking: false});
				}
			}

			function getItemAvailabilityTexts() {
				if ($(".shopping-cart-item-availability").length > 0) {
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_item_availability_texts", function (returnArray) {
						if (!("error_message" in returnArray)) {
							for (var i in returnArray) {
								$("#" + i).find("td").html(returnArray[i]);
								$("#" + i).removeClass("hidden");
							}
						}
					});
				}
			}

			function recalculateBalance() {
				if ($("#_order_total_wrapper").find(".order-total").html().length == 0) {
					return;
				}
				let paymentCount = 0;
				let totalPayments = 0;
				let orderTotal = RoundFixed(parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), "")), 2, true);
				let balanceDue = orderTotal;
				$(".payment-method-section-wrapper").find(".payment-amount").each(function () {
					paymentCount++;
					let thisPaymentAmount = parseFloat($(this).val().replace(new RegExp(",", "g"), ""));
					if (isNaN(thisPaymentAmount)) {
						$(this).val("0.00");
					} else {
						if (thisPaymentAmount > balanceDue) {
							thisPaymentAmount = balanceDue;
							$(this).val(RoundFixed(thisPaymentAmount, 2, true));
						}
						totalPayments += thisPaymentAmount;
						balanceDue -= thisPaymentAmount;
					}
				});
				if (balanceDue == 0) {
					$("#primary_payment_method_section").find(".payment-amount").val(RoundFixed(balanceDue, 2, true));
					$("#_balance_due").html(RoundFixed(balanceDue, 2));
					$("#primary_payment_method_section").addClass("no-content");
					$("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val("");
					$("#_new_account_wrapper").addClass("hidden");
				} else {
					$("#primary_payment_method_section").find(".payment-amount").val(RoundFixed(balanceDue, 2, true));
					$("#primary_payment_method_section").removeClass("no-content");
					if (empty($("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val())) {
						$("#_new_account_wrapper").removeClass("hidden");
					} else {
						$("#_new_account_wrapper").addClass("hidden");
					}
					let primaryPaymentMethodSet = $("#_new_account_wrapper").hasClass("hidden") && !empty($("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val());
					if (!primaryPaymentMethodSet) {
						primaryPaymentMethodSet = !$("#_new_account_wrapper").hasClass("hidden") && !empty($("#payment_method_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val());
					}
					if (primaryPaymentMethodSet) {
						$("#primary_payment_method_section").find(".payment-amount").val(RoundFixed(balanceDue, 2, true));
						balanceDue = 0;
					}
					$("#_balance_due").html(RoundFixed(balanceDue, 2));
				}
			}
		</script>
		<?php
	}

	function onLoadJavascript() {
		$ignoreAddressValidation = getUserTypePreference("RETAIL_STORE_IGNORE_ADDRESS_VALIDATION");
		$noSignature = getPreference("RETAIL_STORE_NO_SIGNATURE");
		$requireSignature = getPreference("RETAIL_STORE_REQUIRE_SIGNATURE");
		$noShippingRequired = getUserTypePreference("RETAIL_STORE_NO_SHIPPING");
		$credovaCredentials = getCredovaCredentials();
		$credovaUserName = $credovaCredentials['username'];
		$credovaPassword = $credovaCredentials['password'];
		$credovaTest = $credovaCredentials['test_environment'];
		$credovaPaymentMethodId = $credovaCredentials['credova_payment_method_id'];
		?>
		<script>
			credovaUserName = "<?= $credovaUserName ?>";
			credovaTestEnvironment = <?= (empty($credovaTest) ? "false" : "true") ?>;

			window.onbeforeunload = function () {
				if (orderInProcess) {
					return "DO NOT close the window. Submitting the order is in process.";
				}
			};

			if (!(typeof addEditableListRow === "function")) {
				jQuery.getScript("/js/editablelist.js");
			}

			if (!(typeof addFormListRow === "function")) {
				jQuery.getScript("/js/formlist.js");
			}

			<?php if (empty($noSignature)) { ?>
			$(".signature-palette").jSignature({'UndoButton': true, "height": 140});
			<?php } ?>

			$(document).on("click", "#summary_place_order", function () {
				$("#_finalize_order").trigger("click");
				return false;
			});

			$(document).on("change", "#billing_address_id_<?= $this->iPrimaryPaymentMethodNumber ?>", function () {
				if (empty($(this).val())) {
					$("#_billing_address").removeClass("hidden");
				} else {
					$("#_billing_address").addClass("hidden");
				}
			});
			$(document).on("change", "#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>", function () {
				$(".payment-method-id-option").removeClass("active");
				if (empty($(this).val())) {
					$(".billing-country").trigger("change");
					$("#payment_method_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val("").trigger("change");
					$("#_new_account_wrapper").removeClass("hidden").find(".payment-method-id").first().focus();
					if ($(".payment-method-id-option").length == 1) {
						$(".payment-method-id-option").trigger("click");
					}
				} else {
					$("#_new_account_wrapper").addClass("hidden");
					$(".payment-method-fields").addClass("hidden");
					$("#_billing_address_wrapper").addClass("hidden");
					$("#_new_account_wrapper").find("input[type=text],select").val("");
					$("#_new_account_wrapper").find("input[type=checkbox]").prop("checked", false);
					$("#payment_method_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val($(this).find("option:selected").data("payment_method_id"));
				}
				calculateOrderTotal();
				return false;
			});
			$(document).on("change", ".payment-amount", function () {
				let orderTotal = RoundFixed(parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), "")), 2, true);
				if ($(this).val().length == 0) {
					$(this).val(RoundFixed(orderTotal, 2, true));
				}
				let maximumPaymentAmount = $(this).closest(".payment-method-section-wrapper").data("maximum_payment_amount");
				let maximumPaymentPercentage = $(this).closest(".payment-method-section-wrapper").data("maximum_payment_percentage");
				if (!empty(maximumPaymentPercentage)) {
					const percentMaximumAmount = RoundFixed(parseFloat(orderTotal * maximumPaymentPercentage / 100), 2, true);
					if (empty(maximumPaymentAmount)) {
						maximumPaymentAmount = percentMaximumAmount;
					} else {
						maximumPaymentAmount = Math.min(maximumPaymentAmount, percentMaximumAmount);
					}
				}
				let paymentAmount = parseFloat($(this).val());
				if (!empty(maximumPaymentAmount)) {
					maximumPaymentAmount = parseFloat(maximumPaymentAmount);
					if (maximumPaymentAmount < paymentAmount) {
						$(this).val(RoundFixed(maximumPaymentAmount, 2)).focus();
						displayErrorMessage("Maximum payment amount for this payment method is " + RoundFixed(maximumPaymentAmount, 2));
					}
				}
				calculateOrderTotal();
				return false;
			});
			$(document).on("click", ".apply-payment-method-button", function () {
				let maximumPaymentAmount = $(this).closest(".payment-method-section-wrapper").data("maximum_payment_amount");
				let paymentAmount = parseFloat($(this).closest(".payment-method-section-wrapper").find(".payment-amount").html().replace(new RegExp(",", "g"), ""));
				if (!empty(maximumPaymentAmount)) {
					maximumPaymentAmount = parseFloat(maximumPaymentAmount);
					if (maximumPaymentAmount < paymentAmount) {
						displayErrorMessage("Maximum payment amount for this payment method is " + RoundFixed(maximumPaymentAmount, 2));
						return false;
					}
				}
				if (!$(this).closest(".payment-method-section-wrapper").validationEngine("validate")) {
					return false;
				}
				const paymentMethodCode = $(this).closest(".payment-method-section-wrapper").data("payment_method_code");
				const paymentMethodTypeCode = $(this).closest(".payment-method-section-wrapper").data("payment_method_type_code");

				if (paymentMethodCode == "CREDOVA" || paymentMethodTypeCode == "CREDOVA") {
					credovaPaymentMethodId = $(this).closest(".payment-method-section-wrapper").data("payment_method_id");
					$("#credova_information").removeClass("hidden");
					var publicIdentifier = $("#public_identifier").val();
					if (empty(publicIdentifier)) {
						let orderTotal = RoundFixed(parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), "")), 2, true);
						CRDV.plugin.prequalify(orderTotal).then(function (res) {
							if (res.approved) {
								$("#public_identifier").val(res.publicId[0]);
							}
						});
					}
				}
				if (paymentMethodCode == "GIFT_CARD" || paymentMethodTypeCode == "GIFT_CARD") {
					if (empty($(this).closest(".payment-method-section-wrapper").find(".gift-card-number").val())) {
						displayErrorMessage("Gift card number required");
						return false;
					}
				}

				$(this).closest(".payment-method-section-wrapper").removeClass("applying").addClass("applied");
				$(this).closest(".payment-method-section-wrapper").find(".payment-method-id").val($(this).closest(".payment-method-section-wrapper").data("payment_method_id"));
				calculateOrderTotal();
				return false;
			});
			$(document).on("click", ".cancel-apply-payment-method-button, .remove-applied-payment-method", function () {
				$(this).closest(".payment-method-section-wrapper").removeClass("applying").removeClass("applied");
				$(this).closest(".payment-method-section-wrapper").find(".payment-method-id").val("");
				$(this).closest(".payment-method-section-wrapper").find(".payment-amount").val("");
				$("#_new_account_wrapper").removeClass("hidden");
				calculateOrderTotal();
				return false;
			});
			$(document).on("click", ".apply-payment-method", function () {
				const $thisElement = $(this).closest(".payment-method-section-wrapper");
				let orderTotal = RoundFixed(parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), "")), 2, true);
				let maximumPaymentAmount = $thisElement.data("maximum_payment_amount");
				if (empty(maximumPaymentAmount)) {
					maximumPaymentAmount = 0;
				}
				let maximumPaymentPercentage = $thisElement.data("maximum_payment_percentage");
				if (!empty(maximumPaymentPercentage) && maximumPaymentPercentage > 0) {
					const percentMaximumAmount = RoundFixed(parseFloat(orderTotal * maximumPaymentPercentage / 100), 2, true);
					if (empty(maximumPaymentAmount)) {
						maximumPaymentAmount = percentMaximumAmount;
					} else {
						maximumPaymentAmount = Math.min(maximumPaymentAmount, percentMaximumAmount);
					}
				}
				if (maximumPaymentAmount <= 0) {
					maximumPaymentAmount = orderTotal;
				}
				$thisElement.find(".payment-amount").val(Math.min(maximumPaymentAmount, orderTotal)).trigger("change");
				$thisElement.addClass("applying").find("input[type=text]").first().focus();
				return false;
			});
			$(document).on("click", ".checked-order-upgrade-product", function (event) {
				event.preventDefault();
			});
			$(document).on("click", ".order-upgrade-product-id", function () {
				const productId = $(this).val();
				addProductToShoppingCart({product_id: productId, quantity: ($(this).prop("checked") ? $(this).closest(".order-upgrade-product-wrapper").data("quantity") : 0), set_quantity: true, order_upsell_product: true});
				return true;
			});
			$(document).on("click", ".checkout-section.not-active", function () {
				$("#checkout_next_button").trigger("click");
			});
			$(document).on("click", ".checkout-next-button", function () {
				if ($(this).hasClass("disabled-button")) {
					return false;
				}
				const functionName = $("#_checkout_process_wrapper").find(".checkout-section.active").last().data("validation_error_function");
				if (!empty(functionName) && typeof window[functionName] === "function") {
					if (!window[functionName]()) {
						return false;
					}
				}
				let nextSection = $("#_checkout_process_wrapper").find(".checkout-section.active").last().data("next_section");
				$("#_checkout_process_wrapper").find(".checkout-section.active").last().find(".checkout-next-section").addClass("hidden");
				while (true) {
					if (empty(nextSection)) {
						nextSection = $("#_checkout_process_wrapper").find(".checkout-section").not(".not-required").first().attr("id");
					}
					if ($("#" + nextSection).is(".not-required")) {
						nextSection = $("#" + nextSection).data("next_section");
					} else {
						break;
					}
				}
				$("#_checkout_process_wrapper").find("#" + nextSection).removeClass("not-active").addClass("active");
				if (empty($(this).attr("id"))) {
					$(this).addClass("hidden");
				}
				if (nextSection == "finalize_information") {
					$(".checkout-next-button").addClass("disabled-button");
					$("#_finalize_order").removeClass("disabled-button");
					$("#summary_place_order_wrapper").removeClass("hidden");
				} else {
					if (nextSection == "payment_information") {
						if (empty($("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val())) {
							if ($(".payment-method-id-option").length == 1) {
								$(".payment-method-id-option").trigger("click");
							}
						} else {
							$("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").trigger("change");
						}
					}
					$(".checkout-next-button").removeClass("disabled-button");
					$("#_finalize_order").addClass("disabled-button");
					$("#summary_place_order_wrapper").addClass("hidden");
				}
				if ("afterNextCheckoutButton" in window) {
					afterNextCheckoutButton();
				}
				return false;
			});

			$(document).on("click", "#quick_checkout", function () {
				let orderTotal = RoundFixed(parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), "")), 2, true);
				$("#_quick_confirmation_details").html($("#quick_details").html().replace("%order_total%", orderTotal));
				$('#_quick_confirmation').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 600,
					title: 'Quick Checkout Confirmation',
					buttons: {
						Cancel: function (event) {
							$("#_quick_confirmation").dialog('close');
						},
						Order: function (event) {
							$("#_finalize_order").removeClass("disabled-button").addClass("quick-checkout").trigger("click");
							setTimeout(function () {
								$("#_finalize_order").removeClass("quick-checkout");
							}, 500);
							$("#_quick_confirmation").dialog('close');
						}
					}
				});
				return false;
			});

			$(document).on("click", "#_promotion_applied_message .fa-times", function () {
				$("#promotion_code").val("").trigger("change");
			});
			$(document).on("click", "#show_promotion_code_details", function () {
				$('#_promotion_code_details_dialog').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 600,
					title: 'Promotion Details',
					buttons: {
						Close: function (event) {
							$("#_promotion_code_details_dialog").dialog('close');
						}
					}
				});
				return false;
			});
			$(document).on("click", "#_promotion_message", function () {
				$("#_promotion_code_wrapper").removeClass("hidden");
				$("#_promotion_message").addClass("hidden");
				$("#promotion_code").focus();
				return false;
			});
			$(document).on("click", "#select_ffl_location", function () {
				$(this).closest(".info-section-change").trigger("click");
				return false;
			});
			$(window).on("scroll", function () {
				if (isScrolledIntoView($("#shopping_cart_header"))) {
					$("#_shopping_cart_summary").removeClass("fixed");
				} else if ($("#_summary_cart_contents").find(".summary-cart-item").length < 5) {
					$("#_shopping_cart_summary").addClass("fixed");
				}
			});

			$(document).on("keydown", "#ffl_dealer_filter", function (event) {
				if (event.which == 13) {
					$("#search_ffl_dealers").trigger("click");
				}
			});

			$(document).on("click", "#search_ffl_dealers", function () {
				$("#ffl_radius").val(empty($("#ffl_dealer_filter").val()) ? "100" : "200");
				getFFLDealers();
			});

			$(document).on("click", "#show_preferred_only", function () {
				getFFLDealers();
			});

			$(document).on("click", "#show_have_license_only", function () {
				getFFLDealers();
			});

			$("#_finalize_order").click(function () {
				if ($(this).hasClass("disabled-button")) {
					return false;
				}
				orderInProcess = true;
				let foundCredova = false;
				if (!empty(credovaPaymentMethodId)) {
					$(".payment-method-id").each(function () {
						if ($(this).val() == credovaPaymentMethodId) {
							foundCredova = true;
						}
					});
				}
				if (!foundCredova) {
					$("#credova_information").addClass("hidden");
				}

				$(".formFieldError").removeClass("formFieldError");
				$("#_checkout_form").validationEngine("option", "validateHidden", true);
				if ($("#_checkout_form").validationEngine("validate")) {
					if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.out-of-stock").length > 0) {
						displayErrorMessage("Out of stock items must be removed from the shopping cart");
						orderInProcess = false;
						return false;
					}
					if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.no-online-order").length > 0) {
						displayErrorMessage("In-store purchase only items must be removed from the shopping cart");
						orderInProcess = false;
						return false;
					}
					var signatureRequired = <?= ($requireSignature ? "true" : "false") ?>;
					if ($("#ffl_section").length > 0 && $("#ffl_section").hasClass("ffl-required")) {
						signatureRequired = true;
						var fflId = $("#federal_firearms_licensee_id").val();
						let dealerNotFound = false;
						if ($("#ffl_dealer_not_found").length > 0 && $("#ffl_dealer_not_found").prop("checked")) {
							dealerNotFound = true;
						}
						if (!dealerNotFound && empty(fflId)) {
							displayErrorMessage("<?= getLanguageText("FFL Dealer") ?> is required");
							$(".ffl-section-chooser").trigger("click");
							orderInProcess = false;
							return false;
						}
					}
					if ($(this).hasClass("quick-checkout")) {
						$("#quick_checkout_flag").val("1");
					} else {
						$("#quick_checkout_flag").val("");
					}

					<?php if (empty($noSignature)) { ?>
					if ($(this).hasClass("quick-checkout")) {
						signatureRequired = false;
					} else {
						$(".signature-palette").each(function () {
							var columnName = $(this).closest(".form-line").find("input[type=hidden]").prop("id");
							var required = $(this).closest(".form-line").find("input[type=hidden]").data("required");
							$(this).closest(".form-line").find("input[type=hidden]").val("");
							if (!empty(required) && $(this).jSignature('getData', 'native').length === 0) {
								$(this).validationEngine("showPrompt", "Required");
								signatureRequired = true;
							} else {
								var data = $(this).jSignature('getData', 'svg');
								$(this).closest(".form-line").find("input[type=hidden]").val(data[1]);
								signatureRequired = false;
							}
						});
					}
					if (signatureRequired) {
						displayErrorMessage("Signature is required");
						orderInProcess = false;
						return false;
					}
					<?php } ?>

					<?php if (!empty($this->iUseRecaptchaV2)) { ?>
					if (empty(grecaptcha) || empty(grecaptcha.getResponse())) {
						displayErrorMessage("Captcha invalid, please check \"I'm not a robot checkbox\".");
						orderInProcess = false;
						return false;
					}
					<?php } ?>

					if (foundCredova && !credovaCheckoutCompleted) {
						loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_credova_application", $("#_checkout_form").serialize(), function (returnArray) {
							if ("error_message" in returnArray) {
								orderInProcess = false;
								return;
							}
							if ("public_identifier" in returnArray) {
								$("#public_identifier").val(returnArray['public_identifier']);
								$("#authentication_token").val(returnArray['authentication_token']);
								logJavascriptError("Opening Credova checkout with public ID " + returnArray['public_identifier'] + "\nBrowser info: " + navigator.userAgent, 0);
								CRDV.plugin.checkout(returnArray['public_identifier']).then(function (completed) {
									// Log result to database for later analysis
									let logEntry = "Credova checkout (public ID " + $("#public_identifier").val() + ") response: " + completed.toString();
									logEntry += "\nBrowser info: " + navigator.userAgent;
									if (completed) {
										logEntry += "\n\nCredova checkout completed successfully.";
									} else {
										logEntry += "\n\nCredova checkout was closed by user without completing.";
									}
									logEntry += "\nBrowser info: " + navigator.userAgent;
									logJavascriptError(logEntry, 0);
									if (completed) {
										credovaCheckoutCompleted = true;
										setTimeout(function () {
											$("#_finalize_order").trigger("click");
										}, 100);
									} else {
										credovaCheckoutCompleted = false;
										orderInProcess = false;
									}
								});
							}
						}, function (returnArray) {
							$("#_finalize_order").removeClass("hidden");
							$("#processing_order").addClass("hidden");
							orderInProcess = false;
						});
						return false;
					}

					$("#_finalize_order").addClass("hidden");
					$("#summary_place_order_wrapper").addClass("hidden");
					$("#processing_order").removeClass("hidden");
					$("select.disabled").prop("disabled", false);
					$("body").addClass("waiting-for-ajax");

					$("#_checkout_form").attr("action", "/retail-store-controller?ajax=true&url_action=create_order&cart_total=" + $("#cart_total").val()).attr("method", "POST").attr("target", "post_iframe").submit();
					$("#_post_iframe").off("load");
					$("#_post_iframe").on("load", function () {
						$("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
						const returnText = $(this).contents().find("body").html();
						const returnArray = processReturn(returnText);
						if (returnArray === false) {
							orderInProcess = false;
							return;
						}
						if ("reload_page" in returnArray) {
							setTimeout(function () {
								location.reload();
							}, 3500);
							return;
						}
						$("select.disabled").prop("disabled", true);
						if ("response" in returnArray) {
							$("#_shopping_cart_wrapper").html(returnArray['response']).addClass("order-completed");
							setTimeout(function () {
								orderInProcess = false;
								window.scrollTo(0, 0);
							}, 1000);
						} else {
							$("#public_identifier").val("");
							$("#authentication_token").val("");
							credovaCheckoutCompleted = false;
							if ("recalculate" in returnArray || "reload_cart" in returnArray) {
								getShoppingCartItems();
							}
							$("#_finalize_order").removeClass("hidden");
							$("#summary_place_order_wrapper").removeClass("hidden");
							$("#processing_order").addClass("hidden");
							orderInProcess = false;
						}
						getShoppingCartItemCount();
					});
				} else {
					var fieldNames = "";
					$(".formFieldError").each(function () {
						let thisFieldName = $(this).data("field_name");
						if (empty(thisFieldName)) {
							thisFieldName = $(this).attr("id");
						}
						if (empty(thisFieldName)) {
							thisFieldName = $(this).attr("name");
						}
						fieldNames += (empty(fieldNames) ? "" : ",") + thisFieldName;
					});
					displayErrorMessage("Required information is missing: " + fieldNames);
				}
				return false;
			});

			$("#tax_exempt").click(function () {
				if ($(this).prop("checked")) {
					$("#_tax_exempt_id_row").removeClass("hidden");
					$("#tax_exempt_id").focus();
				} else {
					$("#_tax_exempt_id_row").addClass("hidden");
				}
			});

			$("#view_terms_conditions").click(function () {
				$('#_terms_conditions_dialog').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 1200,
					title: 'Terms and Conditions',
					buttons: {
						Close: function (event) {
							$("#_terms_conditions_dialog").dialog('close');
						}
					}
				});
				return false;
			});

			$("#view_dealer_terms_conditions").click(function () {
				$('#_dealer_terms_conditions_dialog').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 1200,
					title: 'FFL Dealer Terms and Conditions',
					buttons: {
						Close: function (event) {
							$("#_dealer_terms_conditions_dialog").dialog('close');
						}
					}
				});
				return false;
			});

            $(document).on("change", ".gift-card-number,.gift-card-pin", function () {
                let $thisElement = $(this).closest(".payment-method-section-wrapper");
                $thisElement.find(".gift-card-information").html("");
                $thisElement.find(".apply-payment-method-button").addClass("disabled-button");
            });

			$(document).on("click", ".validate-gift-card", function () {
				let $thisElement = $(this).closest(".payment-method-section-wrapper");
				if (!empty($thisElement.find(".gift-card-number").val())) {
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=check_gift_card&gift_card_number=" + encodeURIComponent($thisElement.find(".gift-card-number").val()) + "&gift_card_pin=" + encodeURIComponent($thisElement.find(".gift-card-pin").val()), function (returnArray) {
						if ("gift_card_information" in returnArray) {
							$thisElement.find(".gift-card-information").removeClass("error-message").addClass("info-message").html(returnArray['gift_card_information']);
							$thisElement.data("maximum_payment_amount", returnArray['maximum_payment_amount']);
							const giftAmountAvailable = parseFloat(returnArray['maximum_payment_amount'])
							let orderTotal = RoundFixed(parseFloat($("#_order_total_wrapper").find(".order-total").html().replace(new RegExp(",", "g"), "")), 2, true);
							$thisElement.find(".payment-amount").val(Math.min(giftAmountAvailable, orderTotal)).trigger("change");
						}
						if ("gift_card_error" in returnArray) {
							if ($thisElement.hasClass("applied")) {
								$thisElement.find(".remove-applied-payment-method").trigger("click");
							}
							displayErrorMessage(returnArray['gift_card_error']);
                            $thisElement.find(".gift-card-information").html("");
							$thisElement.find(".gift-card-number").val("").focus();
							$thisElement.find(".gift-card-pin").val("");
                            $thisElement.find(".apply-payment-method-button").addClass("disabled-button");
                        } else {
                            $thisElement.find(".apply-payment-method-button").removeClass("disabled-button");
                        }
					});
				}
                return false;
			});

			$(document).on("change", "#billing_country_id_<?= $this->iPrimaryPaymentMethodNumber ?>", function () {
				if ($(this).val() == "1000") {
					$("#billing_state_<?= $this->iPrimaryPaymentMethodNumber ?>").closest(".form-line").addClass("hidden");
					$("#billing_state_select_<?= $this->iPrimaryPaymentMethodNumber ?>").closest(".form-line").removeClass("hidden");
				} else {
					$("#billing_state_<?= $this->iPrimaryPaymentMethodNumber ?>").closest(".form-line").removeClass("hidden");
					$("#billing_state_select_<?= $this->iPrimaryPaymentMethodNumber ?>").closest(".form-line").addClass("hidden");
				}
			});

			$(document).on("change", "#country_id", function () {
				if ($(this).val() == "1000") {
					$("#state").closest(".form-line").addClass("hidden");
					$("#state_select").closest(".form-line").removeClass("hidden");
				} else {
					$("#state").closest(".form-line").removeClass("hidden");
					$("#state_select").closest(".form-line").addClass("hidden");
				}
			});
			if ($("#country_id").length > 0) {
				setTimeout(function () {
					$("#country_id").trigger("change");
				}, 500);
			}

			$(document).on("change", "#billing_state_select_<?= $this->iPrimaryPaymentMethodNumber ?>", function () {
				$("#billing_state_<?= $this->iPrimaryPaymentMethodNumber ?>").val($("#billing_state_select_<?= $this->iPrimaryPaymentMethodNumber ?>").val());
			});

			$(document).on("change", ".shipping-address", function () {
				if ($("#new_address_country_id").val() != 1000) {
					return;
				}
				var allHaveValue = true;
				$(".shipping-address").each(function () {
					if (empty($(this).val())) {
						allHaveValue = false;
						return false;
					}
				});
				<?php if (!$ignoreAddressValidation) { ?>
				if (allHaveValue) {
					$("body").addClass("no-waiting-for-ajax");
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=validate_address", {address_1: $("#new_address_address_1").val(), city: $("#new_address_city").val(), state: $("#new_address_state_select").val(), postal_code: $("#new_address_postal_code").val()}, function (returnArray) {
						if ("validated_address" in returnArray) {
							$("#entered_address").html(returnArray['entered_address']);
							$("#validated_address").html(returnArray['validated_address']);
							$('#_validated_address_dialog').dialog({
								closeOnEscape: true,
								draggable: true,
								modal: true,
								resizable: true,
								position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
								width: 600,
								title: 'Address Validation',
								buttons: {
									"Use Mine": function (event) {
										$("#_validated_address_dialog").dialog('close');
									},
									"Use Validated Address": function (event) {
										$("#new_address_address_1").val(returnArray['address_1']);
										$("#new_address_city").val(returnArray['city']);
										$("#new_address_state").val(returnArray['state']);
										$("#new_address_state_select").val(returnArray['state']);
										$("#new_address_postal_code").val(returnArray['postal_code']);
										$("#_validated_address_dialog").dialog('close');
									}
								}
							});
						}
					});
				}
				<?php } ?>
			});

			$(document).on("change", ".billing-address", function () {
				if ($("#billing_country_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val() != 1000) {
					return;
				}
				let allHaveValue = true;
				$(".billing-address").each(function () {
					if (empty($(this).val())) {
						allHaveValue = false;
						return false;
					}
				});
				<?php if (!$ignoreAddressValidation) { ?>
				if (allHaveValue) {
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=validate_address", {address_1: $("#billing_address_1_<?= $this->iPrimaryPaymentMethodNumber ?>").val(), city: $("#billing_city_<?= $this->iPrimaryPaymentMethodNumber ?>").val(), state: $("#billing_state_select_<?= $this->iPrimaryPaymentMethodNumber ?>").val(), postal_code: $("#billing_postal_code_<?= $this->iPrimaryPaymentMethodNumber ?>").val()}, function (returnArray) {
						if ("validated_address" in returnArray) {
							$("#entered_address").html(returnArray['entered_address']);
							$("#validated_address").html(returnArray['validated_address']);
							$('#_validated_address_dialog').dialog({
								closeOnEscape: true,
								draggable: true,
								modal: true,
								resizable: true,
								position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
								width: 600,
								title: 'Address Validation',
								buttons: {
									"Use Mine": function (event) {
										$("#_validated_address_dialog").dialog('close');
									},
									"Use Validated Address": function (event) {
										$("#billing_address_1_<?= $this->iPrimaryPaymentMethodNumber ?>").val(returnArray['address_1']);
										$("#billing_city_<?= $this->iPrimaryPaymentMethodNumber ?>").val(returnArray['city']);
										$("#billing_state_<?= $this->iPrimaryPaymentMethodNumber ?>").val(returnArray['state']);
										$("#billing_state_select_<?= $this->iPrimaryPaymentMethodNumber ?>").val(returnArray['state']);
										$("#billing_postal_code_<?= $this->iPrimaryPaymentMethodNumber ?>").val(returnArray['postal_code']);
										$("#_validated_address_dialog").dialog('close');
									}
								}
							});
						}
					});
				}
				<?php } ?>
			});

			$(document).on("click", ".payment-method-id-option", function () {
				$(".payment-method-id-option").removeClass("active");
				$("#payment_method_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val($(this).data("payment_method_id")).trigger("change");
				$(this).addClass("active");
			})

			$(document).on("change", "#payment_method_id_<?= $this->iPrimaryPaymentMethodNumber ?>", function (event) {
				if (!empty($(this).val()) && !empty($("#valid_payment_methods").val())) {
					if (!isInArray($(this).val(), $("#valid_payment_methods").val())) {
						$(this).val("");
						displayErrorMessage("One or more items in the cart cannot be paid for with this payment method.");
						return;
					}
				}
				let paymentMethodCode = $(this).find("option:selected").data("payment_method_code");
				if (empty(paymentMethodCode)) {
					paymentMethodCode = "";
				}
				let paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
				if (empty(paymentMethodTypeCode)) {
					paymentMethodTypeCode = "";
				}
				paymentMethodCode = paymentMethodCode.toUpperCase();
				paymentMethodTypeCode = paymentMethodTypeCode.toUpperCase();
				$(".payment-method-fields").addClass("hidden");
				if (!empty(paymentMethodTypeCode)) {
					$("#payment_method_" + paymentMethodTypeCode.toLowerCase()).removeClass("hidden");
					if (paymentMethodTypeCode == "CREDIT_CARD" || paymentMethodTypeCode == "BANK_ACCOUNT") {
						$("#_account_label_<?= $this->iPrimaryPaymentMethodNumber ?>_row").removeClass("hidden");
					}
				}
				const addressRequired = !empty($(this).find("option:selected").data("address_required"));
				$("#_billing_address_wrapper").addClass("hidden");
				$("#_billing_address_wrapper").find("input[type=text],select").val("");

				if (addressRequired) {
					$("#_billing_address_wrapper").removeClass("hidden");
					$("#_billing_address_wrapper").find("input[type=text],select").each(function () {
						if (!empty($(this).data("default_value"))) {
							$(this).val($(this).data("default_value"));
						}
					});
					$("#billing_country_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val("1000").trigger("change");
					$("#billing_address_id_<?= $this->iPrimaryPaymentMethodNumber ?>").trigger("change");
				}
				$("#_new_account_wrapper").find("input[type=text]:visible").first().focus();
				calculateOrderTotal();
			});

			$(document).on("click", ".round-up-donation", function () {
				$("#donation_amount").val("");
				const orderTotal = calculateOrderTotal();
				const roundUpAmount = $(this).data("round_amount");
				const addAmount = $(this).data("add_amount");
				let donationAmount = "";
				if (empty(addAmount)) {
					const newOrderTotal = Math.ceil(orderTotal / roundUpAmount) * roundUpAmount;
					donationAmount = RoundFixed(newOrderTotal - orderTotal, 2);
				} else {
					donationAmount = addAmount;
				}
				$("#donation_amount").validationEngine("hide").removeClass("formFieldError");
				$("#donation_amount").val(donationAmount).trigger("change");
				return false;
			});

			$("#donation_amount").change(function () {
				$("#donation_amount").val($("#donation_amount").val().replace("$", ""));
				calculateOrderTotal();
			});

			$(document).on("click", ".make-default-shipping-address", function () {
				$("body").addClass("no-waiting-for-ajax");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=make_address_default&address_id=" + $(this).val() + "&default_shipping_address=1");
				$(this).closest(".delivery-address-wrapper").find(".delivery-address-choice").trigger("click");
				return false;
			});

			$(document).on("click", ".delivery-address-choice", function () {
				$("#address_id").val($(this).data("address_id")).trigger("change");
				$("#_choose_delivery_address_dialog").dialog('close');
				return false;
			});

			$(document).on("click", ".delete-delivery-address", function () {
				const $element = $(this);
				$("body").addClass("no-waiting-for-ajax");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=delete_address&address_id=" + $element.data("address_id"), function (returnArray) {
					if (!("error_message" in returnArray)) {
						$element.closest(".delivery-address-wrapper").remove();
					}
				});
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
			$(document).on("click", "#_add_new_address", function () {
				$("#_choose_delivery_address_dialog").dialog('close');
				$("#_new_address_form").clearForm();
				$("#new_address_country_id").val("1000").trigger("change");
				$('#_new_address_dialog').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 800,
					title: 'Create New Address',
					buttons: {
						"Save": function (event) {
							if ($("#_new_address_form").validationEngine("validate")) {
								loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_address", $("#_new_address_form").serialize(), function (returnArray) {
									if ("address_description" in returnArray) {
										addressDescriptions[returnArray['address_description']['address_id']] = returnArray['address_description'];
										$("#address_id").val(returnArray['address_description']['address_id']).trigger("change");
										let optionCount = $("#billing_address_id_<?= $this->iPrimaryPaymentMethodNumber ?>").find("option[value!='']").length;
										let thisOption = $("<option></option>").attr("value", returnArray['address_description']['address_id']).text(returnArray['address_description']['description']);
										$("#billing_address_id_<?= $this->iPrimaryPaymentMethodNumber ?>").append(thisOption);
										if (optionCount == 0) {
											$("#billing_address_id_<?= $this->iPrimaryPaymentMethodNumber ?>").val(returnArray['address_description']['address_id']);
										}
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
			});

			$("#address_id").change(function () {
				const addressId = $(this).val();
				if (empty(addressId)) {
					$("#ship_to_label").html("");
					$("#_delivery_location_section").find(".info-section-content").html("<p class='info-section-change clickable'>Add Address</p>");
					$("#address_1").val("");
					$("#city").val("");
					$("#state").val("");
					$("#postal_code").val("");
					$("#country_id").val("1000");
				} else if (addressId in addressDescriptions) {
					$("#ship_to_label").html(addressDescriptions[addressId]['description']);
					$("#_delivery_location_section").find(".info-section-content").html(addressDescriptions[addressId]['content']);
					$("#address_1").val(addressDescriptions[addressId]['address_1']);
					$("#city").val(addressDescriptions[addressId]['city']);
					$("#state").val(addressDescriptions[addressId]['state']);
					$("#postal_code").val(addressDescriptions[addressId]['postal_code']);
					$("#country_id").val(addressDescriptions[addressId]['country_id']);
					getShippingMethods(true);
				} else {
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_address_description&address_id=" + addressId, function (returnArray) {
						if ("address_description" in returnArray) {
							addressDescriptions[addressId] = returnArray['address_description'];
							$("#address_id").trigger("change");
						}
					});
				}
			});

			$(document).on("click", "#ffl_dealer_not_found", function () {
				clearMessage();
				$(this).closest(".info-section").find(".error-message").remove();
			});

			$(document).on("change", "#has_cr_license", function () {
				clearMessage();
				$(this).closest(".info-section").find(".error-message").remove();

				if ($(this).prop("checked")) {
					$("#cr_license_file_upload_wrapper").removeClass("hidden");
				} else {
					$("#cr_license_file_upload_wrapper").addClass("hidden");
				}
			});

			$(document).on("click", ".info-section-change", function () {
				clearMessage();
				$(this).closest(".info-section").find(".error-message").remove();
			});

			$(document).on("click", ".info-section-change.edit-field", function () {
				$(this).closest(".info-section").find("input[type=text]").focus();
			});

			$(document).on("click", "#ffl_selection_wrapper .info-section-change", function () {
				if (empty($("#postal_code").val())) {
					displayErrorMessage("Set shipping address first");
					return false;
				}
				let fflSelectionRadius = 25;
				if ("localFFLSelectionRadius" in window) {
					fflSelectionRadius = localFFLSelectionRadius;
				}
				if (isNaN(fflSelectionRadius) || fflSelectionRadius > 250 || fflSelectionRadius < 5) {
					fflSelectionRadius = 25;
				}
				$("#ffl_radius").val(fflSelectionRadius);
				$("#ffl_dealer_filter").val("");
				getFFLDealers();
				$('#_ffl_selection_dialog').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 800,
					title: 'FFL Selection',
					buttons: {
						"Close": function (event) {
							$("#_ffl_selection_dialog").dialog('close');
						}
					}
				});
			});

			$(document).on("click", ".ffl-dealer", function () {
				if ($(this).hasClass("restricted")) {
					return false;
				}
				var shippingMethodId = $(this).data("shipping_method_id");
				if (!empty(shippingMethodId) && $("#shipping_method_id").find("option[value=" + shippingMethodId + "]").length > 0) {
					$("#federal_firearms_licensee_id").val("").trigger("change");
					$("#shipping_type_pickup").trigger("click");
					setTimeout(function () {
						$("#shipping_method_id").val(shippingMethodId).trigger("change");
					}, 1000);
					return false;
				}
				var fflId = $(this).data("federal_firearms_licensee_id");

				$("#federal_firearms_licensee_id").val(fflId).trigger("change");

				$("#selected_ffl_dealer").html(fflDealers[fflId]);
				if ($("#ffl_dealer_not_found").length > 0) {
					$("#ffl_dealer_not_found").prop("checked", false);
				}
				<?php if ($noShippingRequired) { ?>
				getFFLTaxCharge();
				<?php } else { ?>
				getTaxCharge();
				<?php } ?>
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_information", {federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val()}, function (returnArray) {
					if ("dealer_terms_conditions" in returnArray) {
						$("#_dealer_terms_conditions_wrapper").html(returnArray['dealer_terms_conditions']);
						$("#_dealer_terms_conditions_row").removeClass("hidden");
						$("#dealer_terms_conditions").attr("class", "validate[required]");
					} else {
						$("#_dealer_terms_conditions_row").addClass("hidden");
						$("#dealer_terms_conditions").attr("class", "");
					}
				});
			});

			$(document).on("change", "#federal_firearms_licensee_id", function () {
				$("#_ffl_selection_dialog").dialog('close');
				<?php if ($noShippingRequired) { ?>
				getFFLTaxCharge();
				<?php } else { ?>
				getTaxCharge();
				<?php } ?>
				if (empty($(this).val())) {
					$("#ffl_selection_wrapper").find(".info-section-content").html("<p class='info-section-change clickable'><button id='select_ffl_location'>Select FFL</button></p>");
				}
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_information", {federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val()}, function (returnArray) {
					if ("ffl_information" in returnArray) {
						$("#ffl_selection_wrapper").find(".info-section-content").html(returnArray['ffl_information']);
						$("#ffl_dealer_not_found_wrapper").addClass("hidden");
					} else {
						$("#federal_firearms_licensee_id").val("");
						$("#ffl_selection_wrapper").find(".info-section-content").html("<p class='info-section-change clickable'><button id='select_ffl_location'>Select FFL</button></p>");
						$("#ffl_dealer_not_found_wrapper").removeClass("hidden");
					}
				});
			});

			$(document).on("click", "#_delivery_location_section .info-section-change", function () {
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_delivery_addresses", function (returnArray) {
					if ("delivery_addresses" in returnArray) {
						$("#delivery_addresses").html(returnArray['delivery_addresses']);
						$('#_choose_delivery_address_dialog').dialog({
							closeOnEscape: true,
							draggable: true,
							modal: true,
							resizable: true,
							position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
							width: 800,
							title: 'Delivery Address',
							buttons: {
								"Close": function (event) {
									$("#_choose_delivery_address_dialog").dialog('close');
								}
							}
						});
					}
				});
				return false;
			});

			$("#shipping_method_id").change(function () {
				<?php if (!empty($this->iFflRequiredProductTagId)) { ?>
				checkFFLRequirements();
				<?php } else { ?>
				$(".ffl-section").remove();
				<?php } ?>
				const recalculateTax = ($(this).val() != savedShippingMethodId) || ($("#address_id").val() != savedAddressId) || (!empty($("#shipping_method_id").data("force_tax_recalculation")))
				$("#shipping_method_id").data("force_tax_recalculation", "");

				savedShippingMethodId = $(this).val();
				savedAddressId = $("#address_id").val();
				if (recalculateTax) {
					getTaxCharge();
				}
				const shippingMethodCode = $(this).find("option:selected").data("shipping_method_code");
				const pickupShippingMethod = $(this).find("option:selected").data("pickup");
				if (pickupShippingMethod) {
					if (!$("#shipping_type_pickup").prop("checked")) {
						$("#shipping_type_pickup").trigger("click");
					}
					$("#_shipping_method_id_row").addClass("hidden");
					getItemAvailabilityTexts();
					let pickupLocationDescription = "";
					if (savedShippingMethodId in pickupLocationDescriptions) {
						pickupLocationDescription = pickupLocationDescriptions[savedShippingMethodId]['content'];
					}
					if (empty(pickupLocationDescription)) {
						$("body").addClass("no-waiting-for-ajax");
						loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_pickup_location_description&shipping_method_id=" + savedShippingMethodId, function (returnArray) {
							if ("pickup_location_description" in returnArray) {
								pickupLocationDescriptions[returnArray['pickup_location_description']['shipping_method_id']] = returnArray['pickup_location_description'];
								$("#_pickup_location_section .info-section-content").html(returnArray['pickup_location_description']['content']);
							}
						});
					} else {
						$("#_pickup_location_section .info-section-content").html(pickupLocationDescription);
					}
				} else {
					$("#_shipping_method_id_row").removeClass("hidden");
					if (!$("#shipping_type_delivery").prop("checked")) {
						setTimeout(function () {
							$("#shipping_type_delivery").trigger("click");
						}, 200);
					}
					$(".item-availability").addClass("hidden");
				}
			});

			$(document).on("click", ".pickup-location-choice", function () {
				const locationCode = $(this).data("location_code");
				const shippingMethodId = $(this).data("shipping_method_id");
				$("#_choose_pickup_location_dialog").dialog('close');
				$("#shipping_method_id").val(shippingMethodId).trigger("change");
				$("#_pickup_location_section").data("shipping_method_id", shippingMethodId);
				if (!empty(locationCode)) {
					setDefaultLocation(locationCode);
				}
				return false;
			});

			$(document).on("click", "#_pickup_location_section .info-section-change", function () {
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_pickup_locations", function (returnArray) {
					if ("pickup_locations" in returnArray) {
						$("#pickup_locations").html(returnArray['pickup_locations']);
						setTimeout(function () {
							$(".pickup-location-choice").tooltip({
								position: {my: "left top", at: "left+150 top+10"}
							});
						}, 1000);
						$('#_choose_pickup_location_dialog').dialog({
							closeOnEscape: true,
							draggable: true,
							modal: true,
							resizable: true,
							position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
							width: 800,
							title: 'Pickup Locations',
							buttons: {
								"Close": function (event) {
									$("#_choose_pickup_location_dialog").dialog('close');
								}
							}
						});
					}
				});
				return false;
			});
			$(document).on("click", "input[type=radio][name=shipping_type]", function () {
				const setShippingMethodId = $("#shipping_method_id").val();
				const pickup = $("#shipping_type_pickup").prop("checked");
				if (pickup) {
					$("#delivery_method_display").html("Delivery Method: Pickup");
				} else {
					$("#delivery_method_display").html("Delivery Method: Shipping");
				}
				const currentPickup = $("#shipping_method_id option:selected").data("pickup");
				if ((empty(currentPickup) && pickup) || (!empty(currentPickup) && !pickup)) {
					$("#shipping_method_id").val("");
				}
				$("#shipping_method_id").find("option").unwrap("span");
				let count = 0;
				let shippingMethodId = "";
				$("#shipping_method_id").find("option").each(function () {
					if (!empty($(this).val())) {
						if ((pickup && empty($(this).data("pickup"))) || (!pickup && !empty($(this).data("pickup")))) {
							$(this).wrap("<span></span>");
						} else {
							count++;
							shippingMethodId = $(this).val();
						}
					}
				});
				if (count == 1 && !empty(shippingMethodId) && shippingMethodId != setShippingMethodId) {
					$("#shipping_method_id").val(shippingMethodId).trigger("change");
				}
				$(".shipping-method-details").addClass("hidden");
				$("." + (pickup ? "pickup" : "delivery") + "-shipping-method-details").removeClass("hidden");
				$("." + (pickup ? "delivery" : "pickup") + "-shipping-method-details").addClass("hidden");
				if (pickup) {
					if (empty($("#shipping_method_id").val()) && !empty($("#_pickup_location_section").data("shipping_method_id"))) {
						const pickupShippingMethodId = $("#_pickup_location_section").data("shipping_method_id");
						setTimeout(function () {
							$("#shipping_method_id").val(pickupShippingMethodId);
							$("#shipping_method_id").trigger("change");
						}, 500);
					}
				} else {
					if (empty($("#address_id").val())) {
						for (var i in addressDescriptions) {
							if (addressDescriptions[i]['default_shipping_address']) {
								$("#address_id").val(i).trigger("change");
							}
						}
					}
					if (empty($("#address_id").val())) {
						if ("-1" in addressDescriptions) {
							$("#address_id").val("-1").trigger("change");
						}
					}
				}
				checkFFLRequirements();
			});
			$(document).on("click", "#show_shipping_calculation_log", function () {
				$('#_shipping_calculation_log_dialog').dialog({
					closeOnEscape: true,
					draggable: true,
					modal: true,
					resizable: true,
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
					width: 600,
					title: 'Shipping Calculation',
					buttons: {
						"Close": function (event) {
							$("#_shipping_calculation_log_dialog").dialog('close');
						}
					}
				});
				return false;
			});
			$(document).on("click", "#return_to_cart,#edit_cart", function () {
				if ($("#related_products_wrapper").find(".catalog-item").length == 0) {
					$("#related_products_wrapper").addClass("hidden");
				}
				if ($("#wish_list_products_wrapper").find(".catalog-item").length == 0) {
					$("#wish_list_products_wrapper").addClass("hidden");
				}
				$(".checkout-next-button").removeClass("hidden");
				$("#_checkout_process_wrapper").find(".checkout-section.active").removeClass("active");
				$("#shopping_cart_header").html("Shopping Cart");
				$("#_checkout_process_wrapper").find(".checkout-next-section").removeClass("hidden");
				$("body").removeClass("checkout");
				if ("afterReturnToCart" in window) {
					afterReturnToCart();
				}
				if ($("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").find("option[value!='']").length > 0) {
					$("#_new_account_wrapper").addClass("hidden");
				} else {
					$("#_new_account_wrapper").removeClass("hidden");
				}
				return false;
			});
			$(document).on("blur", "#user_name", function () {
				if ($(this).is(":visible")) {
					$("#_user_name_message").removeClass("info-message").removeClass("error-message").html("");
					if (!empty($(this).val())) {
						loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val() + "&user_id=<?= $GLOBALS['gUserId'] ?>", function (returnArray) {
							$("#_user_name_message").removeClass("info-message").removeClass("error-message");
							if ("info_user_name_message" in returnArray) {
								$("#_user_name_message").html(returnArray['info_user_name_message']).addClass("info-message");
							}
							if ("error_user_name_message" in returnArray) {
								$("#_user_name_message").html(returnArray['error_user_name_message']).addClass("error-message");
								$("#user_name").val("");
								$("#user_name").focus();
								setTimeout(function () {
									$("#_edit_form").validationEngine("hideAll");
								}, 10);
							}
						});
					} else {
						$("#_user_name_message").val("");
					}
				}
			});
			$(document).on("click", "#confirm_guest_checkout", function () {
				displayErrorMessage("");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=set_shopping_cart_contact&confirm=true&email_address=" + encodeURIComponent($("#guest_email_address").val()) + "&first_name=" + encodeURIComponent($("#guest_first_name").val()) + "&last_name=" + encodeURIComponent($("#guest_last_name").val()), function (returnArray) {
					if (!empty($("#promotion_code").val())) {
						$("#promotion_code").trigger("change");
					}
					if ("public_identifier" in returnArray) {
						$("#public_identifier").val(returnArray['public_identifier']);
					}
					$("#_user_account_wrapper").addClass("hidden");
					$("#_shopping_cart_wrapper").removeClass("hidden");
				});
				return false;
			});
			$(document).on("click", "#guest_checkout", function () {
				if ($("#_guest_form").validationEngine("validate")) {
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=set_shopping_cart_contact&email_address=" + encodeURIComponent($("#guest_email_address").val()) + "&first_name=" + encodeURIComponent($("#guest_first_name").val()) + "&last_name=" + encodeURIComponent($("#guest_last_name").val()), function (returnArray) {
						if (!empty($("#promotion_code").val())) {
							$("#promotion_code").trigger("change");
						}
						if ("public_identifier" in returnArray) {
							$("#public_identifier").val(returnArray['public_identifier']);
						}
						if ("found_user" in returnArray && returnArray['found_user']) {
							$("#_confirm_guest_checkout").removeClass("hidden");
							$("#_guest_checkout").addClass("hidden");
						} else {
							$("#_user_account_wrapper").addClass("hidden");
							$("#_shopping_cart_wrapper").removeClass("hidden");
						}
					});
				}
				return false;
			});
			$(document).on("click", "#create_account", function () {
				if ($("#_create_user_form").validationEngine("validate")) {
					$("#create_account").addClass("hidden");
					$("#create_account_message").html("Logging in...");
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_checkout_user", $("#_create_user_form").serialize(), function (returnArray) {
						if ("error_message" in returnArray) {
							$("#create_account").removeClass("hidden");
							$("#create_account_message").html("");
						} else {
							$("#create_account_message").html("Account created... reloading Shopping Cart to reflect your login...");
							setTimeout(function () {
								document.location = "<?= $GLOBALS['gLinkUrl'] ?>?place_order=true";
							}, 2000);
						}
					});
				}
				return false;
			});
			$(document).on("click", "#login_now_button", function () {
				if ($("#_login_form").validationEngine("validate")) {
					$("#login_now_button").addClass("hidden");
					$("#logging_in_message").html("Logging in...");
					loadAjaxRequest("/loginform.php?ajax=true&url_action=login", $("#_login_form").serialize(), function (returnArray) {
						if ("error_message" in returnArray) {
							$("#login_now_button").removeClass("hidden");
							$("#logging_in_message").html("");
						} else {
							$("#logging_in_message").html("Reloading Shopping Cart to reflect your login...");
							setTimeout(function () {
								document.location = "<?= $GLOBALS['gLinkUrl'] ?>?place_order=true";
							}, 2000);
						}
					});
				}
				return false;
			});
			$(document).on("click", "input[type=checkbox].product-addon", function () {
				saveItemAddons();
			});
			$(document).on("change", "input[type=text].product-addon", function () {
				saveItemAddons();
			});
			$(document).on("change", "input[type=text].addon-select-quantity", function () {
				let maximumQuantity = $(this).closest(".form-line").find("select.product-addon").find("option:selected").data("maximum_quantity");
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
				const $quantityField = $(this).closest("form-line").find(".addon-select-quantity");
				if (isNaN($quantityField.val() || empty($quantityField.val()) || $quantityField.val() <= 0)) {
					$quantityField.val("1");
				}
				if (parseInt($quantityField.val()) > parseInt(maximumQuantity)) {
					$quantityField.val(maximumQuantity);
				}
				saveItemAddons();
			});
			$(document).on("click", "#continue_checkout", function () {
				if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item").length == 0) {
					return false;
				}
				if ($("#_shopping_cart_summary").find(".shopping-cart-item-count").html() == "0") {
					return false;
				}
				if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.out-of-stock").length > 0) {
					displayErrorMessage("Out of stock items must be removed from the shopping cart");
					return false;
				}
				if (!$("#_shopping_cart_items_wrapper").validationEngine("validate")) {
					return false;
				}
				$("#_checkout_process_wrapper").find(".checkout-section").removeClass("active");
				$(".checkout-next-button").removeClass("disabled-button");
				$("#checkout_next_button").trigger("click");
				$("#shopping_cart_header").html("Secure Checkout");
				$("#designation_id").trigger("change");
				fillSummaryCart();
				if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.no-online-order").length > 0) {
					displayErrorMessage("In-store purchase only items must be removed from the shopping cart");
					return false;
				}
				if ($(".checkout-not-allowed").length > 0) {
					displayErrorMessage($(".checkout-not-allowed").html());
					return false;
				}
				if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item").length == 0) {
					return false;
				}
				$("body").addClass("checkout");
				$("body").addClass("no-waiting-for-ajax");
				loadAjaxRequest("/retail-store-controller?ajax=true&url_action=checkout_started", function (returnArray) {
					$("body").removeClass("no-waiting-for-ajax");
				});

				if (!userLoggedIn) {
					$("#_shopping_cart_wrapper").addClass("hidden");
					$("#_user_account_wrapper").removeClass("hidden");
				} else {
					$("#_shopping_cart_wrapper").removeClass("hidden");
				}
				getShippingMethods();
				checkFFLRequirements();

				sendAnalyticsEvent("checkout", {});
				if ("afterStartCheckout" in window) {
					afterStartCheckout();
				}
				return false;
			});
			$(document).on("click", "#apply_promo", function () {
				return false;
			});
			$(document).on("click", ".remove-item", function () {
				var productId = $(this).data("product_id");
				var shoppingCartItemId = $(this).closest(".shopping-cart-item").data("shopping_cart_item_id");
				removeProductFromShoppingCart(productId, shoppingCartItemId);
				$(this).closest(".shopping-cart-item").remove();
				calculateShoppingCartTotal();
			});

			const cleaveCreditCardField = document.getElementById('account_number_<?= $this->iPrimaryPaymentMethodNumber ?>');
			var cleave = new Cleave(cleaveCreditCardField, {
				creditCard: true,
				onCreditCardTypeChanged: function (type) {
					switch (type) {
						case 'amex':
							cleaveCreditCardField.style.backgroundPosition = '7px -119px';
							break;
						case 'visa':
							cleaveCreditCardField.style.backgroundPosition = '7px -35px';
							break;
						case 'discover':
							cleaveCreditCardField.style.backgroundPosition = '7px -161px';
							break;
						case 'mastercard':
							cleaveCreditCardField.style.backgroundPosition = '7px -77px';
							break;
						default:
							cleaveCreditCardField.style.backgroundPosition = '7px 7px';
							break;
					}
				}
			});

			getShoppingCartItems();
			<?php
			if (!empty($_GET['promotion_code'])) {
			$promotionCode = getFieldFromId("promotion_code", "promotions", "promotion_code", $_GET['promotion_code'], "inactive = 0");
			if (!empty($promotionCode)) {
			?>
			setTimeout(function () {
				$("#promotion_code").val("<?= $promotionCode ?>").trigger("change");
			}, 1000);
			<?php
			}
			}
			?>
			if (!empty($("#address_id").val())) {
				$("#address_id").trigger("change");
			}
			<?php if (!empty($_COOKIE['source_id'])) { ?>
			$("#source_id").val("<?= $_COOKIE['source_id'] ?>");
			<?php } ?>
			if ($("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").is("select")) {
				$("#account_id_<?= $this->iPrimaryPaymentMethodNumber ?>").trigger("change");
			}
		</script>
		<?php
	}

	function jqueryTemplates() {
		$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
		$customFieldId = CustomField::getCustomFieldIdFromCode("DEFAULT_LOCATION_ID");

		?>
		<div id="_checkout_summary_cart">
			<div class="summary-cart-item">
				<div class="align-center summary-cart-item-image">%image%</div>
				<div class="summary-cart-item-description">
					<div class='summary-cart-product-description'>%description%</div>
				</div>
				<div class='summary-cart-item-price-wrapper'>
					<div class='summary-cart-product-original-price'>%original_price%</div>
					<div class='summary-cart-product-sale-price'>%sale_price%</div>
					<div class='summary-cart-product-quantity'>qty: %quantity%</div>
				</div>
				<?php if (!empty($showLocationAvailability) && !empty($customFieldId)) { ?>
					<div class="summary-cart-item-availability hidden" id="summary_cart_availability_%shopping_cart_item_id%" data-shopping_cart_item_id="%shopping_cart_item_id%"></div>
				<?php } ?>
			</div>
		</div>
		<div id="_shopping_cart_item_block">
			<?php
			$shoppingCartItemFragment = $this->getFragment("RETAIL_STORE_SHOPPING_CART_ITEM_V2");
			if (empty($shoppingCartItemFragment)) {
				ob_start();
				?>
				<div class="shopping-cart-item %other_classes%" id="shopping_cart_item_%shopping_cart_item_id%" data-product_code='%product_code%'
					 data-shopping_cart_item_id="%shopping_cart_item_id%" data-product_id="%product_id%" data-product_tag_ids="%product_tag_ids%">

					<div class="align-center shopping-cart-item-image"><a href="%image_url%" class="pretty-photo"><img alt="small image" %image_src%="%small_image_url%"></a></div>

					<div class="shopping-cart-item-description">
						<div class='product-description'><a href="/product-details?id=%product_id%">%description%</a></div>
						<div class='product-info'><span class='product-info-product-code'>Item: %product_code%</span>
							<span class='product-info-manufacturer-sku'>SKU: %manufacturer_sku%</span>
							<span class='product-info-model'>Model: %model%</span>
							<span class='product-info-upc-code'>UPC: %upc_code%</span></div>
						<div class="out-of-stock-notice">Out Of Stock</div>
						<div class="no-online-order-notice">In-store purchase only</div>
						<div class='shopping-cart-item-price-wrapper'>Price: <span class="original-sale-price hidden">%original_sale_price%</span>
							<span class='product-sale-price'>%sale_price%</span>
							<span class='product-savings-wrapper hidden'>Savings: <span class="product-savings">%savings%</span></span>
						</div>
					</div>

					<div class="shopping-cart-item-price">
						<span class='remove-item fad fa-trash-alt' data-product_id="%product_id%"></span>
						<span class="product-total"></span>
						<div class="align-center product-quantity-wrapper">
							<div><span class="fa fa-minus shopping-cart-item-decrease-quantity" data-amount="-1"></span></div>
							<div><input tabindex='10' class="product-quantity" data-cart_maximum="%cart_maximum%" data-cart_minimum="%cart_minimum%" value='%quantity%'></div>
							<div><span class="fa fa-plus shopping-cart-item-increase-quantity" data-amount="1"></span></div>
						</div>
						<input class="cart-item-additional-charges" type="hidden" name="shopping_cart_item_additional_charges_%shopping_cart_item_id%" id="shopping_cart_item_additional_charges_%shopping_cart_item_id%">
					</div>
					<div class='shopping-cart-item-restrictions'>%product_restrictions%</div>
					<div class="shopping-cart-item-custom-fields %custom_field_classes%" id="custom_fields_%shopping_cart_item_id%">
						%custom_fields%
					</div>
					<div class="shopping-cart-item-item-addons %addon_classes%" id="addons_%shopping_cart_item_id%" data-shopping_cart_item_id="%shopping_cart_item_id%">
						%item_addons%
					</div>
					<?php if (!empty($showLocationAvailability) && !empty($customFieldId)) { ?>
						<div class="shopping-cart-item-availability hidden" id="availability_%shopping_cart_item_id%" data-shopping_cart_item_id="%shopping_cart_item_id%"></div>
					<?php } ?>
				</div>
				<?php
				$shoppingCartItemFragment = ob_get_clean();
			}
			echo $shoppingCartItemFragment;
			?>
		</div>
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
		<iframe id="_post_iframe" name="post_iframe"></iframe>

		<div id="_order_upsell_product_template">
			<p class='order-upgrade-product-wrapper' data-product_id='%product_id%' data-quantity='%quantity%' data-sale_price='%sale_price%' data-total_cost='%total_cost%'><input type='checkbox' class='order-upgrade-product-id %checked%-order-upgrade-product' name='order_upsell_product_id_%product_id%' id='order_upsell_product_id_%product_id%' value='%product_id%' %checked%><label class='checkbox-label' for='order_upsell_product_id_%product_id%'>%description% (%total_cost%)</label></p>
		</div>

		<div id="_quick_confirmation" class='dialog-box'>
			<div id="_quick_confirmation_details"></div>
			<?php
			$sectionText = $this->getPageTextChunk("retail_store_terms_conditions_note");
			if (empty($sectionText)) {
				$sectionText = $this->getFragment("retail_store_terms_conditions_note");
			}
			if (empty($sectionText)) {
				$sectionText = "<p>By placing your order, you are stating that you agree to all of the terms outlined in our store policies.</p>";
			}
			echo "<div id='quick_confirmation_terms'>" . makeHtml($sectionText) . "</div>";
			?>
		</div>

		<div id="_promotion_code_details_dialog" class='dialog-box'>
			<div id="promotion_code_details"></div>
		</div>

		<?php if ($GLOBALS['gUserRow']['full_client_access']) { ?>
			<div id="_shipping_calculation_log_dialog" class="dialog-box">
				<div id="shipping_calculation_log"></div>
			</div>
		<?php } ?>

		<div id="_choose_pickup_location_dialog" class="dialog-box">
			<div id="pickup_locations"></div>
		</div>

		<div id="_choose_delivery_address_dialog" class="dialog-box">
			<p>Add a new address or click the truck icon to select an existing address.</p>
			<div id="delivery_addresses"></div>
			<p>
				<button id="_add_new_address"><span class='fad fa-map-marker-plus'></span> Add New</button>
			</p>
		</div>

		<div id="_ffl_selection_dialog" class='dialog-box'>
			<?php
			$sectionText = $this->getFragment("retail_store_search_ffl_dealers");
			if (empty($sectionText)) {
				$sectionText = "<p>Click a dealer to select them. <span class='preferred'>Bold, blue dealers</span> are our preferred dealers, <span class='restricted'>red dealers</span> are restricted because they probably can't handle your items and <span class='have-license'>green dealers</span> indicates we have their license on file.</p>";
			}
			echo makeHtml($sectionText);
			?>
			<select id="ffl_radius" class="hidden">
				<option value="25" selected>25</option>
				<option value="50">50</option>
				<option value="100">100</option>
				<option value="200">200</option>
			</select>
			<p><input type="text" placeholder="Search Dealers" name="ffl_dealer_filter" id="ffl_dealer_filter">
				<button id='search_ffl_dealers'>Search</button>
				</button></p>
			<p><input type='checkbox' id='show_preferred_only'><label class='checkbox-label' for='show_preferred_only'>Show only preferred dealers.</label></p>
			<p><input type='checkbox' id='show_have_license_only'><label class='checkbox-label' for='show_have_license_only'>Show only dealers with license on file.</label></p>
			<div id="ffl_dealers_wrapper">
				<ul id="ffl_dealers">
				</ul>
			</div> <!-- ffl_dealers_wrapper -->
		</div>

		<div id="_new_address_dialog" class="dialog-box">
			<form id="_new_address_form">
				<?= createFormLineControl("addresses", "full_name", array("column_name" => "new_address_full_name", "not_null" => false, "help_label" => "Name of person receiving the items. Leave blank to use your name.")) ?>
				<?= createFormLineControl("addresses", "address_1", array("column_name" => "new_address_address_1", "not_null" => true, "classes" => "shipping-address autocomplete-address", "data-prefix" => "new_address_")) ?>
				<?= createFormLineControl("addresses", "address_2", array("column_name" => "new_address_address_2", "not_null" => false, "help_label" => "Apt, suite, unit, building, floor, etc")) ?>
				<?= createFormLineControl("addresses", "city", array("column_name" => "new_address_city", "classes" => "shipping-address", "not_null" => true)) ?>

				<div class="form-line" id="_new_address_state_select_row">
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
					<div class='clear-div'></div>
				</div>

				<?= createFormLineControl("addresses", "state", array("column_name" => "new_address_state", "not_null" => true)) ?>
				<?= createFormLineControl("addresses", "postal_code", array("column_name" => "new_address_postal_code", "not_null" => true)) ?>
				<?= createFormLineControl("addresses", "country_id", array("column_name" => "new_address_country_id", "classes" => "shipping-address", "not_null" => true, "initial_value" => "1000")) ?>
				<?= createFormLineControl("addresses", "default_shipping_address", array("column_name" => "new_address_default_shipping_address", "form_label" => "Set as the default shipping address")) ?>
				<?= createFormLineControl("addresses", "address_label", array("form_label" => "Nickname", "column_name" => "new_address_address_label", "not_null" => false, "help_label" => "add a nickname to identify this address")) ?>
			</form>
		</div>

		<div id="_validated_address_dialog" class="dialog-box">
			<div id="validated_address_wrapper">
				<div id="entered_address">
				</div>
				<div id="validated_address">
				</div>
			</div>
		</div>
		<?php
	}
}

$pageObject = new ShoppingCartPage();
$pageObject->displayPage();
