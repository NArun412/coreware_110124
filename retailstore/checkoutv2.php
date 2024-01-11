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
		<?php
		if ($this->iUseRecaptchaV2) {
			?>
			<script src="https://www.google.com/recaptcha/api.js" async defer></script>
			<?php
		}
	}

	public function mainContent() {
		$_SESSION['form_displayed'] = date("U");
		saveSessionData();
		$loginUserName = $_COOKIE["LOGIN_USER_NAME"];
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
		$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
			getUserTypePreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");
		if (!empty($forcePaymentMethodId)) {
			$onlyOnePayment = true;
		}
		$eCommerce = eCommerce::getEcommerceInstance();
		if (empty($_POST['shopping_cart_code'])) {
			$_POST['shopping_cart_code'] = "RETAIL";
		}
		$shoppingCart = ShoppingCart::getShoppingCart($_POST['shopping_cart_code']);
		if ($GLOBALS['gLoggedIn']) {
			$contactId = $GLOBALS['gUserRow']['contact_id'];
		} else {
			$contactId = $shoppingCart->getContact();
		}

		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$designations = array();
		$resultSet = executeQuery("select * from designations where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and designation_id in (select designation_id from designation_group_links where " .
			"designation_group_id = (select designation_group_id from designation_groups where designation_group_code = 'PRODUCT_ORDER' and inactive = 0 and client_id = ?)) order by sort_order,description",
			$GLOBALS['gClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designations[] = $row;
		}

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
						<p><span class='shopping-cart-item-count'></span> Items</p>
						<div id="_shopping_cart_items">
							<div id="shopping_cart_items_wrapper">
								<p class='align-center'><span id="_cart_loading" class="fad fa-spinner fa-spin"></span></p>
							</div>
						</div>
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
							<div id="_summary_cart_contents_wrapper">
								<div id="_summary_cart_contents">

								</div>
								<p class='align-center'>
									<button id="edit_cart">Edit Cart</button>
								</p>
							</div>
							<div id='_selected_payment_method_wrapper' class='hidden'>Payment Method(s) <span class="float-right text-right"></span></div>
							<div>Subtotal (<span class='shopping-cart-item-count'></span> items) <span class="float-right cart-total"></span></div>
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
									<div id='_promotion_message'>Add a promo code<span class='fas fa-chevron-down'></span></div>
									<div id='_promotion_code_wrapper' class='hidden'>
										<input type='hidden' tabindex='10' id='promotion_id' name='promotion_id'>
										<input type="text" tabindex="10" id="promotion_code" name="promotion_code" placeholder="Promo Code" autocomplete='chrome-off' autocomplete='off'>
										<button tabindex='10' id='apply_promo' title="Apply Promotion Code" class='tooltip-element'><span class='fas fa-check'></span></button>
									</div>
									<div id='_promotion_applied_message' class='hidden'><span class='promotion-code' id='applied_promotion_code'></span> applied. <a href='#' id='show_promotion_code_details'>Details</a><span class='fas fa-times'></span></div>
								</div>
								<div id="add_promotion_code" class='checkout-element hidden'>Have a promotion code? Click 'Edit Cart' to add it.</div>
								<div id="added_promotion_code" class='checkout-element hidden'><span class='promotion-code'></span> applied. Click 'Edit Cart' to change.</div>
							<?php } ?>
							<?php if (!empty($designations)) { ?>
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

						</div>
						<p class='error-message'></p>
					</div>
					<div id="_checkout_process_wrapper">
						<section id="_checkout_section_form" class='tabbed-content ignore-tab-clicks'>

							<ul id="progressbar" class='tabbed-content-nav'>
								<li class="tabbed-content-tab" id="_shipping_tab" data-section_id="shipping_information">Shipping</li>
								<li class="tabbed-content-tab" id="_payment_tab" data-section_id="payment_information">Payment</li>
								<li class="tabbed-content-tab" id="_finalize_tab" data-section_id="finalize_information">Finalize</li>
							</ul>

							<!-- Business Information Section -->
							<div class='tabbed-content-body' id="checkout_form_wrapper">
								<div class="tabbed-content-page" data-validation_error_function="shippingValidation" id="shipping_information">
									<h2>Delivery Information</h2>

									<?php
									$sectionText = $this->getPageTextChunk("retail_store_shipping_method");
									if (empty($sectionText)) {
										$sectionText = $this->getFragment("retail_store_shipping_method");
									}
									echo makeHtml($sectionText);
									?>

									<p id="calculating_shipping_methods" class="shipping-section hidden">Fetching valid shipping methods and pickup locations...</p>
									<p id="_shipping_error" class='shipping-section red-text'>No Shipping Methods are available for this shopping cart. Please contact customer support.</p>
									<div id="shipping_type_wrapper" class="shipping-section form-line hidden">
										<label>How would you like to receive your item(s)?</label>
										<div class="form-line">
											<input type='radio' name='shipping_type' id='shipping_type_delivery' value='delivery'><label for="shipping_type_delivery" class="checkbox-label">Delivery</label>
										</div>
										<div class='form-line'>
											<input type="radio" name="shipping_type" id="shipping_type_pickup" value="pickup"><label for="shipping_type_pickup" class="checkbox-label">Store Pickup</label>
										</div>
									</div>

									<div class="shipping-section form-line shipping-method-details delivery-shipping-method-details" id="_shipping_method_id_row">
										<label id="shipping_method_id_label" for="shipping_method_id" class="">Shipping Method</label>
										<select tabindex="10" id="shipping_method_id" name="shipping_method_id" class='validate-hidden validate[required]' data-conditional-required='!$("#_shipping_method_id_row").hasClass("not-required")'>
											<option value="">[Select]</option>
										</select>
										<div class='clear-div'></div>
									</div>

									<div class='shipping-section info-section shipping-method-details pickup-shipping-method-details' id='_pickup_location_section'>
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

									<input type='hidden' class='validate[required] validate-hidden' data-conditional-required='$("#shipping_type_delivery").prop("checked") && !$("#_shipping_method_id_row").hasClass("not-required")' id='address_id' name='address_id' value=''>
									<input type='hidden' id='address_1' name='address_1' value=''>
									<input type='hidden' id='city' name='city' value=''>
									<input type='hidden' id='state' name='state' value=''>
									<input type='hidden' id='postal_code' name='postal_code' value=''>
									<input type='hidden' id='country_id' name='country_id' value='1000'>
									<div class='shipping-section info-section shipping-method-details delivery-shipping-method-details' id='_delivery_location_section'>
										<div class='info-section-header'>
											<div class='info-section-title'>Ship To <span id='ship_to_label'></span></div>
											<div class='info-section-change clickable'>change</div>
										</div>
										<p class='ffl-section'>Firearms will NOT ship to this address, but will ship to the Firearms dealer selected below. <strong class="red-text">This is YOUR address. Do not put your FFL dealer's address here.</strong></p>
										<div class='info-section-content'><p class='info-section-change clickable'>Add Address</p></div>
									</div>

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
									$phoneRequired = getPreference("retail_store_phone_required");
									$phoneControls = array("not_null" => true, "form_label" => "", "initial_value" => $phoneNumber, "data-conditional-required" => "empty($(\"#cell_phone_number\").val())");
									if (!empty($phoneRequired) && !$GLOBALS['gInternalConnection']) {
										$phoneControls['not_null'] = true;
									}
									?>
									<div class='info-section'>
										<div class='info-section-header'>
											<div class='info-section-title'>Primary Phone Number</div>
											<div class='info-section-change clickable edit-field'>change</div>
										</div>
										<?= createFormLineControl("phone_numbers", "phone_number", $phoneControls) ?>
									</div>
									<div class='info-section'>
										<div class='info-section-header'>
											<div class='info-section-title'>Cell phone number, for text notifications</div>
											<div class='info-section-change clickable edit-field'>change</div>
										</div>
										<?= createFormLineControl("phone_numbers", "phone_number", array("column_name" => "cell_phone_number", "form_label" => "", "initial_value" => $cellPhoneNumber, "not_null" => false)) ?>
									</div>
									<?php
									$phoneNumberTypes = explode(",", $this->getPageTextChunk("PHONE_NUMBER_LABELS"));
									foreach ($phoneNumberTypes as $phoneNumberType) {
										if (empty($phoneNumberType) || $phoneNumberType == "primary" || $phoneNumberType == "cell") {
											continue;
										}
										?>
										<div class='info-section'>
											<div class='info-section-header'>
												<div class='info-section-title'><?= $phoneNumberType ?></div>
												<div class='info-section-change clickable edit-field'>change</div>
											</div>
											<?= createFormLineControl("phone_numbers", "phone_number", array("column_name" => makeCode($phoneNumberType, array("lowercase" => true)) . "_phone_number", "form_label" => "", "not_null" => false)) ?>
										</div>
										<?php
									}
									if (!empty($this->getPageTextChunk("SHOW_PO_NUMBER")) || $GLOBALS['gUserRow']['administrator_flag']) {
										?>
										<div class='info-section'>
											<div class='info-section-header'>
												<div class='info-section-title'>PO Number</div>
												<div class='info-section-change clickable edit-field'>change</div>
											</div>
											<?= createFormLineControl("orders", "purchase_order_number", array("not_null" => false, "form_label" => "")) ?>
										</div>
										<?php
									}

									if (!empty($this->iFflRequiredProductTagId)) {
										$fflChoiceElement = "<p class='info-section-change clickable'><button id='select_ffl_location'>Select FFL</button></p>";
										if (!empty($contactId)) {
											$fflRow = (new FFL(array("federal_firearms_licensee_id" => CustomField::getCustomFieldData($contactId, "DEFAULT_FFL_DEALER"), "only_if_valid" => true)))->getFFLRow();
											if ($fflRow) {
												$fflRow['phone_number'] = Contact::getContactPhoneNumber($fflRow['contact_id'], 'Store');
												$fflChoiceElement = "<p><span class='ffl-choice-business-name'>%business_name%</span><br><span class='ffl-choice-address'>%address_1%</span><br><span class='ffl-choice-city'>%city%, %state% %postal_code%</span><br><span class='ffl-phone-number'>%phone_number%</span></p>";
												foreach ($fflRow as $fieldName => $fieldData) {
													$fflChoiceElement = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $fflChoiceElement);
												}
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
										<input type="hidden" class="validate[required] validate-hidden" data-conditional-required="!$('#ffl_dealer_not_found').prop('checked') && !$('#has_cr_license').prop('checked')" id="federal_firearms_licensee_id" name="federal_firearms_licensee_id" value="<?= $federalFirearmsLicenseeId ?>">
										<div class='info-section ffl-section hidden' id="ffl_selection_wrapper" data-product_tag_id="<?= $this->iFflRequiredProductTagId ?>" data-cr_required_product_tag_id="<?= $crRequiredProductTagId ?>">
											<div class='info-section-header'>
												<div class='info-section-title'>FFL Dealer</div>
												<div class='info-section-change clickable'>change</div>
											</div>
											<?php if (!$forceFFLDealerRequired) { ?>
												<p id="ffl_dealer_not_found_wrapper">
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

								<div class="tabbed-content-page" data-validation_error_function="paymentValidation" id="payment_information">
									<h2>Payment Information</h2>
									<?php
									$fragment = $this->getFragment("retail_store_payment_information");
									if (!empty($fragment)) {
										echo makeHtml($fragment);
									}
									if (!empty($designations)) {
										?>
										<div class='info-section'>
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
												if (count($designations) > 1) {
													$showImages = true;
													if (count($designations) > 6) {
														$showImages = false;
													} else {
														foreach ($designations as $thisDesignation) {
															if (empty($thisDesignation['image_id'])) {
																$showImages = false;
																break;
															}
														}
													}
													if ($showImages) {
														?>
														<input type="hidden" class='validate[required] validate-hidden' data-conditional-required='(!empty($("#donation_amount").val()))' id="designation_id" name="designation_id" value="">
														<div id="designation_images_wrapper">
															<?php
															foreach ($designations as $row) {
																?>
																<div class='designation-image' data-designation_id='<?= $row['designation_id'] ?>'><img src="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>"></div>
																<?php
															}
															?>
														</div>
														<?php
													} else {
														?>
														<div class="form-line" id="_designation_id_row">
															<label for="designation_id" class="">Designation</label>
															<select class='validate[required] validate-hidden' data-conditional-required='(!empty($("#donation_amount").val()))' tabindex="10" id="designation_id" name="designation_id">
																<option value=''>[Select]</option>
																<?php
																foreach ($designations as $row) {
																	?>
																	<option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
																	<?php
																}
																?>
															</select>
															<div class='clear-div'></div>
														</div>
														<?php
													}
												} else {
													?>
													<input type="hidden" id="designation_id" name="designation_id" value="<?= $designations[0]['designation_id'] ?>">
													<?php
												}
												?>
												<div id='_donation_amount_wrapper' class='hidden'>
													<div class="form-line" id="_donation_amount_row">
														<label for="donation_amount"
															   class="">Donation <?= (count($designations) > 1 ? "Amount" : " for " . htmlText($designations[0]['description'])) ?></label>
														<input tabindex="10" size="12" type="text" id="donation_amount" name="donation_amount" class="align-right validate[required,custom[number]]" data-conditional-required='(!empty($("#designation_id").val()))' data-decimal-places="2">
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
									?>
									<div class='info-section'>
										<div class='info-section-header'>
											<div class='info-section-title'>Payment Details</div>
										</div>
										<div class='info-section-content'>
											<div id="_payments_wrapper" data-payment_method_number="1" class="hidden">
												<h3>Payments</h3>
												<p>To create a split payment, edit the amount of the first payment and add another.</p>
											</div>
											<?php
											$validAccounts = array();
											$resultSet = executeQuery("select *, (select payment_method_type_id from payment_methods where payment_method_id = accounts.payment_method_id) payment_method_type_id from accounts where contact_id = ? and inactive = 0 and " .
												"(merchant_account_id is null or merchant_account_id in (select merchant_account_id from merchant_accounts where client_id = ? and inactive = 0 and internal_use_only = 0))", $contactId, $GLOBALS['gClientId']);
											while ($row = getNextRow($resultSet)) {
												if (!empty($forcePaymentMethodId) && $row['payment_method_id'] != $forcePaymentMethodId) {
													continue;
												}
												$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $row['payment_method_type_id']);
												if (!in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT", "GIFT_CARD", "CHARGE_ACCOUNT", "CREDIT_ACCOUNT"))) {
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
												}
											}
											if (count($validAccounts) > 0) {
												?>
												<div id="_saved_accounts_wrapper">
													<h3>Saved Accounts</h3>
													<p>Click account to use.</p>
													<?php
													foreach ($validAccounts as $row) {
														$accountLabel = (empty($row['account_label']) ? $row['account_number'] : $row['account_label']);
														$iconClasses = eCommerce::getPaymentMethodIcon($row['payment_method_code'], $row['payment_method_type_code']);
														$maximumPaymentAmount = false;
														if (in_array($row['payment_method_type_code'], array("CHARGE_ACCOUNT", "CREDIT_ACCOUNT"))) {
															if ($row['payment_method_type_code'] == "CHARGE_ACCOUNT") {
																$charges = 0;
																$payments = 0;
																$chargeSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) from order_payments where account_id = ?", $row['account_id']);
																if ($chargeRow = getNextRow($chargeSet)) {
																	$charges = $chargeRow['sum(amount + shipping_charge + tax_charge + handling_charge)'];
																}
																$paymentSet = executeQuery("select sum(amount) from account_payments where account_id = ?", $row['account_id']);
																if ($paymentRow = getNextRow($paymentSet)) {
																	$payments = $paymentRow['sum(amount)'];
																}
																$balance = $charges - $payments;
																$maximumPaymentAmount = max(0, $row['credit_limit'] - $balance);
															} else {
																$maximumPaymentAmount = max(0, $row['credit_limit']);
															}
															if ($maximumPaymentAmount <= 0) {
																continue;
															}
														}
														?>
														<div class="saved-account-id" id="saved_account_id_<?= $row['account_id'] ?>" data-account_id="<?= $row['account_id'] ?>"
															 data-maximum_payment_amount='<?= $maximumPaymentAmount ?>' title='Use this saved payment method' data-payment_method_type_code='<?= $row['payment_method_type_code'] ?>'>
															<div class='saved-account-id-wrapper'>
																<div class='saved-account-id-image'><span class="<?= $iconClasses ?>"></span></div>
																<div class='saved-account-id-description'><?= $accountLabel ?></div>
															</div>
														</div>
													<?php } ?>
												</div>
											<?php } ?>
											<p id="_add_payment_method_wrapper">
												<button id='_add_payment_method'>Add Payment Method</button>
											</p>
											<p id="_balance_due_wrapper"><label>Balance Due:</label><span id="_balance_due"></span></p>
										</div>
									</div>
								</div>

								<div class="tabbed-content-page" id="finalize_information">
									<h2>Finalize Order</h2>
									<?php if ($GLOBALS['gInternalConnection']) { ?>
										<div class="form-line" id="_order_notes_content_row">
											<label for="order_notes_content">Packing Slip Notes</label>
											<textarea id="order_notes_content" name="order_notes_content"></textarea>
											<div class='clear-div'></div>
										</div>
									<?php } ?>

									<?php
									$giftText = $this->getPageTextChunk("retail_store_gift");
									if (empty($giftText)) {
										$giftText = $this->getFragment("retail_store_gift");
									}
									echo makeHtml($giftText);
									?>
									<div class="form-line" id="_gift_order_row">
										<input type="checkbox" id="gift_order" name="gift_order" value="1"><label
											for="gift_order" class="checkbox-label">This order is a gift</label>
										<div class='clear-div'></div>
									</div>
									<div class="form-line hidden" id="_gift_text_row">
										<label for="gift_text">Gift message to be included on packing slip</label>
										<textarea id="gift_text" name="gift_text"></textarea>
										<div class='clear-div'></div>
									</div>

									<?php
									$customerOrderNote = getPreference("CUSTOMER_ORDER_NOTE");
									if (!empty($customerOrderNote) || $GLOBALS['gInternalConnection']) {
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

									<?php if ($GLOBALS['gInternalConnection']) { ?>
										<div class="form-line" id="_tax_exempt_row">
											<input type="checkbox" id="tax_exempt" name="tax_exempt" value="1"><label for="tax_exempt" class="checkbox-label">Tax Exempt</label>
											<div class='clear-div'></div>
										</div>

										<div class="form-line hidden" id="_tax_exempt_id_row">
											<label for="tax_exempt_id">Tax Exempt Number</label>
											<input type="text" class='validate[required]' id="tax_exempt_id" name="tax_exempt_id" data-conditional-required="$('#tax_exempt').prop('checked')" value="<?= CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "TAX_EXEMPT_ID") ?>">
											<div class='clear-div'></div>
										</div>
									<?php } ?>

									<?php
									ob_start();
									$resultSet = executeQuery("select * from mailing_lists where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
									if ($resultSet['row_count'] > 0) {
										$optInTitle = $this->getPageTextChunk("retail_store_opt_in_title");
										if (empty($optInTitle)) {
											$optInTitle = $this->getFragment("retail_store_opt_in_title");
										}
										if (empty($optInTitle)) {
											$optInTitle = "Opt-In Mailing Lists";
										}
										?>
										<h3><?= $optInTitle ?></h3>

										<?php
										while ($row = getNextRow($resultSet)) {
											$optedIn = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "contact_id", $GLOBALS['gUserRow']['contact_id'],
												"mailing_list_id = ? and date_opted_out is null", $row['mailing_list_id']);
											?>
											<div class="form-line" id="_mailing_list_id_<?= $row['mailing_list_id'] ?>_row">
												<label></label>
												<input type="checkbox" id="mailing_list_id_<?= $row['mailing_list_id'] ?>"
													   name="mailing_list_id_<?= $row['mailing_list_id'] ?>"
													   value="1" <?= (empty($optedIn) ? "" : "checked='checked'") ?>><label
													for="mailing_list_id_<?= $row['mailing_list_id'] ?>"
													class="checkbox-label"><?= htmlText($row['description']) ?></label>
												<div class='clear-div'></div>
											</div>
											<?php
										}
									}
									echo ob_get_clean();

									$resultSet = executeQuery("select count(*) from contacts where client_id = ? and deleted = 0 and contact_id in (select contact_id from contact_categories where " .
										"category_id = (select category_id from categories where client_id = ? and category_code = 'REFERRER'))", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
									if ($row = getNextRow($resultSet)) {
										if ($row['count(*)'] > 0) {
											?>
											<div class="form-line" id="_referral_contact_id_row">
												<label for="referral_contact_id" class="">Did someone refer you?</label>
												<?php if ($row['count(*)'] > 100) { ?>
													<input class="" type="hidden" id="referral_contact_id"
														   name="referral_contact_id" value="">
													<input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="autocomplete-field"
														   type="text" size="50"
														   name="referral_contact_id_autocomplete_text"
														   id="referral_contact_id_autocomplete_text"
														   data-autocomplete_tag="referral_contacts">
												<?php } else { ?>
													<select id="referral_contact_id" name="referral_contact_id">
														<option value="">[None]</option>
														<?php
														$resultSet = executeQuery("select contact_id,first_name,last_name from contacts where client_id = ? and deleted = 0 and contact_id in (select contact_id from contact_categories where " .
															"category_id = (select category_id from categories where client_id = ? and category_code = 'REFERRER')) order by first_name,last_name", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
														while ($row = getNextRow($resultSet)) {
															?>
															<option value="<?= $row['contact_id'] ?>"><?= htmlText(getDisplayName($row['contact_id'])) ?></option>
															<?php
														}
														?>
													</select>
												<?php } ?>
												<div class='clear-div'></div>
											</div>
											<?php
										}
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

									<h3>Terms & Conditions</h3>
									<?php
									$sectionText = $this->getPageTextChunk("retail_store_terms_conditions");
									if (empty($sectionText)) {
										$sectionText = $this->getFragment("retail_store_terms_conditions");
									}
									if (!empty($sectionText)) {
										?>
										<div class="form-line" id="_terms_conditions_row">
											<input type="checkbox" id="terms_conditions" name="terms_conditions" class="validate[required]" value="1" <?= ($GLOBALS['gInternalConnection'] ? " checked" : "") ?>><label for="terms_conditions" class="checkbox-label">I agree to the Terms and Conditions.</label> <a href='#' id="view_terms_conditions" class="clickable">Click here to view store Terms and Conditions.</a>
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
										$sectionText = "<p>By placing your order you are stating that you agree to all of the terms outlined in our store policies.</p>";
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
												<input tabindex="10" class='validate[required]' type="text" size="10" maxlength="10" id="captcha_code" name="captcha_code" placeholder="Captcha Text" value="">
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
										<div class="form-line<?= ($requireSignature ? " signature-required" : " hidden") ?>"
											 id="_signature_row">
											<label for="signature" class="">Signature</label>
											<span class="help-label">Required for <?= ($requireSignature ? "" : "FFL ") ?>purchase</span>
											<input type='hidden' name='signature'
												   data-required='<?= ($requireSignature ? "1" : "0") ?>' id='signature'>
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
							<div class='tabbed-content-buttons'>
								<button id="return_to_cart">Return to Cart</button>
								<button class='tabbed-content-previous-page'>Previous</button>
								<button class='tabbed-content-next-page'>Next</button>
								<button class='tabbed-content-finalize-page' id="_finalize_order">Place Order</button>
							</div>

						</section>
					</div>
				</div>
				<div id="_add_product_wrapper" class='cart-element form-line'>
					<label>Add other products to cart</label>
					<span class="help-label">Enter a value and hit return to add product to your cart</span>
					<input type="text" tabindex="10" id="add_product" placeholder="Product Code or UPC">
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

				<div id="wish_list_products_wrapper" class="hidden cart-element">
					<h2>From Your Wishlist</h2>
					<div id="wish_list_products">
					</div>
				</div>

				<div id="related_products_wrapper" class="hidden cart-element user-logged-in">
					<h2>Related Products</h2>
					<div id="related_products">
					</div>
				</div>

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
			</form>
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

			#_promotion_applied_message {
				font-size: .7rem;
				background-color: rgb(250, 0, 0);
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

			#_promotion_message .fa-chevron-down {
				position: absolute;
				right: 0;
			}

			.distance--miles {
				display: none;
			}

			#payment_method_wrapper {
				max-height: 600px;
				overflow: scroll;
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

			.payment-method-id-option.disabled {
				opacity: 20%;
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

			#order_notes_content, #gift_text, #order_note {
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

			#donation_amount {
				font-size: 1.2rem;
				font-weight: 700;
			}

			.payment-method-wrapper {
				display: flex;
				justify-content: space-between;
				position: relative;
				border: 1px solid rgb(220, 220, 220);
				margin: 0 0 20px;
				padding: 20px;
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

			#_balance_due_wrapper {
				width: 90%;
				background-color: rgb(240, 240, 240);
				font-size: 1rem;
				text-align: center;
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
				padding-top: 30px;
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

			.info-section .form-line {
				margin-bottom: 5px;
			}

			.info-section .form-line input[type=text] {
				border-color: rgb(240, 240, 240);
				outline: none;
				margin-left: 10px;
			}

			.info-section .form-line input[type=text]:focus {
				background-color: rgb(240, 240, 240);
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

			.tabbed-content > ul.tabbed-content-nav {
				justify-content: center;
			}

			.make-default-shipping-address-wrapper {
				display: none;
			}

			.tabbed-content > ul.tabbed-content-nav li {
				background-color: whitesmoke;
				border: none;
				margin: 0 2.5px;
				font-size: 1rem;
				text-transform: uppercase;
				padding: 10px 30px;
				color: red;
			}

			.tabbed-content > ul.tabbed-content-nav li.active {
				background-color: red;
				color: white;
			}

			.tabbed-content .tabbed-content-body {
				border-color: whitesmoke;
				padding: 10px;
			}

			#progressbar {
				overflow: hidden;
				display: flex;
				justify-content: space-evenly;
				text-align: center;
				margin: 0 0 30px 0;
				position: absolute;
				top: 0;
				left: 0;
				transform: translate(0, 0);
				width: 100%;
			}

			#progressbar.fixed {
				position: fixed;
				background-color: rgb(255, 255, 255);
				padding: 30px 0 20px 0;
				border-bottom: 1px solid rgb(220, 220, 220);
				z-index: 9999;
			}

			#progressbar li {
				background-color: transparent;
				border-radius: 0;
				padding: 0;
				font-weight: normal;
				border: none;
				list-style-type: none;
				color: gray;
				text-transform: capitalize;
				font-size: 12px;
				flex: 1;
				position: relative;
			}

			#progressbar li.visited::before {
				color: transparent;
				background: radial-gradient(lightgray 40%, white 50%);
			}

			#progressbar li.visited.active:before {
				background: radial-gradient(red 40%, white 50%);
			}

			#progressbar li.visited.active:after {
				background: lightgray;
				border: 2px;
			}

			#progressbar li:first-child:after {
				content: none;
			}

			#progressbar li:before {
				content: "\2713";
				width: 20px;
				height: 20px;
				line-height: 20px;
				display: block;
				font-size: 10px;
				color: white;
				border: 2px solid lightgray;
				background: white;
				border-radius: 50px;
				margin: 0 auto 5px auto;
				position: relative;
				z-index: 1;
			}

			#progressbar li:after {
				content: "";
				width: 100%;
				height: 3px;
				background: lightgray;
				position: absolute;
				left: -50%;
				top: 10px;
				z-index: 0;
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

			input#add_product {
				width: 95%;
				max-width: 300px;
			}

			button {
				font-size: .8rem;
				padding: 8px 12px;
				border-radius: 2px;
				margin: 0;
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

			body.checkout #_shopping_cart_wrapper #_shopping_cart_contents #_shopping_cart_summary.fixed {
				position: relative;
				top: 0;
				left: 0;
				width: auto;
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

			#checkout_button_wrapper {
				margin-top: 20px;
			}

			#continue_checkout {
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

			.tabbed-content-buttons {
				margin-bottom: 40px;
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

				.tabbed-content .tabbed-content-buttons button {
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
		$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
			getUserTypePreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");
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
			if (!empty($contactRow['address_1']) && !empty($contactRow['city'])) {
				$city = trim($contactRow['city'] . (empty($contactRow['state']) ? "" : ", ") . $contactRow['state'] . " " . $contactRow['postal_code']);
				$content = "<p>" . getDisplayName($contactId) . "<br>" . $contactRow['address_1'] . "<br>" . (empty($contactRow['address_2']) ? "" : $contactRow['address_2'] . "<br>") . $city . "</p>";
				$addressDescriptions["-1"] = array("address_id" => "-1", "description" => "Primary Address", "content" => $content, "country_id" => $contactRow['country_id'], "postal_code" => $contactRow['postal_code'], "state" => $contactRow['state'], "city" => $row['city'], "address_1" => $row['address_1']);
			}
			$resultSet = executeQuery("select * from addresses where contact_id = ? and address_1 is not null and city is not null and inactive = 0", $contactId);
			while ($row = getNextRow($resultSet)) {
				$description = (empty($row['address_label']) ? "Alternate Address" : $row['address_label']);
				$city = trim($row['city'] . (empty($row['state']) ? "" : ", ") . $row['state'] . " " . $row['postal_code']);
				$content = "<p>" . (empty($row['full_name']) ? getDisplayName($contactId) : $row['full_name']) . "<br>" . $row['address_1'] . "<br>" . (empty($row['address_2']) ? "" : $row['address_2'] . "<br>") . $city . "</p>";
				$addressDescriptions[$row['address_id']] = array("address_id" => $row['address_id'], "description" => $description, "content" => $content, "country_id" => $row['country_id'], "postal_code" => $row['postal_code'], "state" => $row['state'], "city" => $row['city'], "address_1" => $row['address_1']);
			}
		}

		?>
		<script>
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
					if ("product_results" in returnArray) {
						if (returnArray['result_count'] > 0) {
							let manufacturerNames = returnArray['manufacturer_names'];
							let productFieldNames = returnArray['product_field_names'];
							let wishListProductResults = returnArray['product_results'];
							shoppingCartProductIds = returnArray['shopping_cart_product_ids'];
							wishListProductIds = returnArray['wishlist_product_ids'];
							emptyImageFilename = returnArray['empty_image_filename'];

							catalogItemWrapper = elementId;

							let productKeyLookup = {};
							for (let i in productFieldNames) {
								productKeyLookup[productFieldNames[i]] = i;
							}

							let originalCatalogResult = $("#_related_result").html();
							if (empty(originalCatalogResult)) {
								originalCatalogResult = $("#_catalog_result").html();
							}

							let catalogItemIndex = 0;
							let insertedCatalogItems = [];
							let productCatalogItems = {};
							for (let resultIndex in wishListProductResults) {
								let catalogResult = originalCatalogResult;
								let otherClasses = "";
								catalogResult = catalogResult.replace(new RegExp("%image_src%", 'ig'), "src");

								const imageBaseFilenameKey = productKeyLookup['image_base_filename'];
								const imageBaseFilename = wishListProductResults[resultIndex][imageBaseFilenameKey];
								const remoteImage = wishListProductResults[resultIndex][productKeyLookup['remote_image']];
								const remoteImageUrl = wishListProductResults[resultIndex][productKeyLookup['remote_image_url']];

								if ($("#catalog_result_product_tags_template").length > 0) {
									catalogResult = catalogResult.replace(new RegExp("%product_tags%", 'ig'), $("#catalog_result_product_tags_template").html());
								} else {
									catalogResult = catalogResult.replace(new RegExp("%product_tags%", 'ig'), "");
								}

								if (!empty(remoteImage)) {
									catalogResult = catalogResult.replace(new RegExp("%image_url%", 'ig'), "https://images.coreware.com/images/products/" + remoteImage + ".jpg");
									catalogResult = catalogResult.replace(new RegExp("%full_image_url%", 'ig'), "https://images.coreware.com/images/products/" + remoteImage + ".jpg");
									catalogResult = catalogResult.replace(new RegExp("%small_image_url%", 'ig'), "https://images.coreware.com/images/products/small-" + remoteImage + ".jpg");
								} else if (!empty(remoteImageUrl)) {
                                    catalogResult = catalogResult.replace(new RegExp("%image_url%", 'ig'), remoteImageUrl);
                                    catalogResult = catalogResult.replace(new RegExp("%full_image_url%", 'ig'), remoteImageUrl);
                                    catalogResult = catalogResult.replace(new RegExp("%small_image_url%", 'ig'), remoteImageUrl);
                                }
								catalogResult = catalogResult.replace(new RegExp("%image_url%", 'ig'),
									(empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : cdnDomain + "/cache/image-full-") + imageBaseFilename));
								catalogResult = catalogResult.replace(new RegExp("%full_image_url%", 'ig'),
									(empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : cdnDomain + "/cache/image-full-") + imageBaseFilename));
								catalogResult = catalogResult.replace(new RegExp("%small_image_url%", 'ig'),
									(empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : cdnDomain + "/cache/image-small-") + imageBaseFilename));
								catalogResult = catalogResult.replace(new RegExp("%thumbnail_image_url%", 'ig'),
									(empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : cdnDomain + "/cache/image-thumbnail-") + imageBaseFilename));
								if (!empty(availabilityTexts) && typeof availabilityTexts === 'object') {
									if ("inventory_quantity" in productKeyLookup && wishListProductResults[resultIndex][productKeyLookup['inventory_quantity']] > 0) {
										if (wishListProductResults[resultIndex][productKeyLookup['location_availability']] in availabilityTexts) {
											wishListProductResults[resultIndex][productKeyLookup['location_availability']] = availabilityTexts[wishListProductResults[resultIndex][productKeyLookup['location_availability']]];
										}
									} else {
										wishListProductResults[resultIndex][productKeyLookup['location_availability']] = "";
									}
								}
								catalogResult = catalogResult.replace(new RegExp("%thumbnail_image_url%", 'ig'), (empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : "/cache/image-thumbnail-") + imageBaseFilename));
								const productId = wishListProductResults[resultIndex][productKeyLookup['product_id']];
								const productManufacturerId = wishListProductResults[resultIndex][productKeyLookup['product_manufacturer_id']];
								let manufacturerName = "";
								let ignoreMap = true;
								let mapPolicyCode = "";
								if (!empty(productManufacturerId) && productManufacturerId in manufacturerNames) {
									manufacturerName = manufacturerNames[productManufacturerId][0];
									ignoreMap = (!empty(manufacturerNames[productManufacturerId][1]));
									if (!ignoreMap) {
										mapPolicyCode = manufacturerNames[productManufacturerId][2];
									}
								}

								const mapEnforced = (!empty(wishListProductResults[resultIndex][productKeyLookup['map_enforced']]));
								if (mapEnforced) {
									ignoreMap = false;
								}
								const productGroup = ("product_group" in productKeyLookup && !empty(wishListProductResults[resultIndex][productKeyLookup['product_group']]));
								const callPrice = (!empty(wishListProductResults[resultIndex][productKeyLookup['call_price']]));
								if (callPrice) {
									ignoreMap = false;
								}
								catalogResult = catalogResult.replace(new RegExp("%manufacturer_name%", 'ig'), manufacturerName);
								if ("original_sale_price" in productKeyLookup && !empty(wishListProductResults[resultIndex][productKeyLookup['original_sale_price']]) && !empty(wishListProductResults[resultIndex][productKeyLookup['sale_price']]) && wishListProductResults[resultIndex][productKeyLookup['original_sale_price']] <= wishListProductResults[resultIndex][productKeyLookup['sale_price']]) {
									wishListProductResults[resultIndex][productKeyLookup['original_sale_price']] = "";
								}
								if ("original_sale_price" in productKeyLookup && !empty(wishListProductResults[resultIndex][productKeyLookup['original_sale_price']])) {
									wishListProductResults[resultIndex][productKeyLookup['original_sale_price']] = "$" + RoundFixed(wishListProductResults[resultIndex][productKeyLookup['original_sale_price']], 2);
								}
								if (!empty(wishListProductResults[resultIndex][productKeyLookup['manufacturer_advertised_price']]) && !ignoreMap) {
									let mapPrice = "";
									if (!empty(wishListProductResults[resultIndex][productKeyLookup['manufacturer_advertised_price']])) {
										mapPrice = parseFloat(wishListProductResults[resultIndex][productKeyLookup['manufacturer_advertised_price']].replace(new RegExp(",", 'ig'), ""));
									}
									let salePrice = "";
									if (!empty(wishListProductResults[resultIndex][productKeyLookup['sale_price']])) {
										salePrice = parseFloat(wishListProductResults[resultIndex][productKeyLookup['sale_price']].replace(new RegExp(",", 'ig'), ""));
									}
									if (mapPrice > salePrice) {
										if (mapPolicyCode === "CART_PRICE") {
											catalogResult = catalogResult.replace(new RegExp("%sale_price%", 'ig'), "See price in cart");
										} else {
											otherClasses += (empty(otherClasses) ? "" : " ") + "map-priced-product";
											catalogResult = catalogResult.replace(new RegExp("%sale_price%", 'ig'), RoundFixed(wishListProductResults[resultIndex][productKeyLookup['manufacturer_advertised_price']], 2));
										}
									}
								}

								if (!("no_online_order" in productKeyLookup) || empty(wishListProductResults[resultIndex][productKeyLookup['no_online_order']])) {
									if ("inventory_quantity" in productKeyLookup && wishListProductResults[resultIndex][productKeyLookup['inventory_quantity']] <= 0) {
										otherClasses += (empty(otherClasses) ? "" : " ") + "out-of-stock-product";
									}
								}
								for (let fieldNumber in productFieldNames) {
									let thisValue = wishListProductResults[resultIndex][fieldNumber];
									if (empty(thisValue)) {
										thisValue = "";
									}
									if (productKeyLookup['sale_price'] === fieldNumber) {
										if (empty(thisValue)) {
											thisValue = "0.00";
										} else {
											const testValue = thisValue.replace(new RegExp(",", "ig"), "");
											if (!isNaN(testValue)) {
												thisValue = RoundFixed(thisValue.replace(new RegExp(",", "ig"), ""), 2);
											}
										}
									}
									catalogResult = catalogResult.replace(new RegExp("%" + productFieldNames[fieldNumber] + "%", 'ig'), thisValue);
								}
								for (let fieldNumber in productFieldNames) {
									catalogResult = catalogResult.replace(new RegExp("%hidden_if_empty:" + productFieldNames[fieldNumber] + "%", 'ig'), (empty(wishListProductResults[resultIndex][fieldNumber]) ? "hidden" : ""));
								}
								if ("product_tag_ids" in productKeyLookup && !empty(wishListProductResults[resultIndex][productKeyLookup['product_tag_ids']])) {
									const productTagIds = wishListProductResults[resultIndex][productKeyLookup['product_tag_ids']].split(",");
									for (let i in productTagIds) {
										const productTagCode = productTagCodes[productTagIds[i]];
										if (!empty(productTagCode)) {
											otherClasses += (empty(otherClasses) ? "" : " ") + "product-tag-code-" + productTagCode.replace(new RegExp("_", "ig"), "-");
										}
									}
								}

								catalogResult = catalogResult.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
								if (!empty(credovaUserName)) {
									let salePrice = wishListProductResults[resultIndex][productKeyLookup['sale_price']];
									if (empty(salePrice)) {
										catalogResult = catalogResult.replace(new RegExp("%credova_financing%", 'ig'), "");
									} else {
										salePrice = salePrice.replace(new RegExp(",", "ig"), "");
										if (salePrice >= 150 && salePrice <= 10000) {
											// noinspection JSUnresolvedVariable
											catalogResult = catalogResult.replace(new RegExp("%credova_financing%", 'ig'), "<p class='" + (userLoggedIn ? "" : "create-account ") + "credova-button' data-amount='" +
												salePrice + "' data-type='popup'></p>");
										} else {
											catalogResult = catalogResult.replace(new RegExp("%credova_financing%", 'ig'), "");
										}
									}
								} else {
									catalogResult = catalogResult.replace(new RegExp("%credova_financing%", 'ig'), "");
								}

								insertedCatalogItems[catalogItemIndex++] = catalogResult;
								productCatalogItems[productId] = {};
								productCatalogItems[productId].map_enforced = false;
								productCatalogItems[productId].call_price = false;
								productCatalogItems[productId].product_group = false;
								productCatalogItems[productId].no_online_order = false;
								productCatalogItems[productId].hide_dollar = false;
								if (mapEnforced) {
									productCatalogItems[productId].map_enforced = true;
								}
								if (productGroup) {
									productCatalogItems[productId].product_group = true;
								}
								if (callPrice) {
									productCatalogItems[productId].call_price = true;
								}
								if (mapPolicyCode === "CART_PRICE") {
									productCatalogItems[productId].hide_dollar = true;
								}
								if ("no_online_order" in productKeyLookup && !empty(wishListProductResults[resultIndex][productKeyLookup['no_online_order']])) {
									productCatalogItems[productId].no_online_order = true;
								}
								if ("hide_dollar" in productKeyLookup && !empty(wishListProductResults[resultIndex][productKeyLookup['hide_dollar']])) {
									productCatalogItems[productId].hide_dollar = true;
								}
							}
							const $catalogItemWrapper = $("#" + catalogItemWrapper);
							$catalogItemWrapper.html(insertedCatalogItems.join(""));
							for (let thisProductId in productCatalogItems) {
								const $catalogItem = $("#catalog_item_" + thisProductId);
								if (productCatalogItems[thisProductId].map_enforced) {
									let newLabel = $catalogItem.find("button.add-to-cart").data("strict_map");
									$catalogItem.find("button.add-to-cart").html(newLabel).addClass("strict-map");
								} else if (productCatalogItems[thisProductId].call_price) {
									let newLabel = $catalogItem.find("button.add-to-cart").data("call_price");
									$catalogItem.find("button.add-to-cart").html(newLabel).addClass("call-price");
								} else if (productCatalogItems[thisProductId].product_group) {
									$catalogItem.find("button.add-to-cart").html("Click for Options").addClass("product-group");
								}
								if (productCatalogItems[thisProductId].no_online_order) {
									$catalogItem.find("button.add-to-cart").html(inStorePurchaseOnlyText).addClass("no-online-order");
								}
								if (productCatalogItems[thisProductId].hide_dollar) {
									$catalogItem.find(".catalog-item-price-wrapper").find(".dollar").remove();
								}
							}
							for (let i in shoppingCartProductIds) {
								let thisProductId = shoppingCartProductIds[i];
								const $addToCart = $(".add-to-cart-" + thisProductId);
								if ($addToCart.length > 0) {
									$addToCart.each(function () {
										if (!$(this).hasClass("no-online-order")) {
											const inText = $(this).data("in_text");
											if (!empty(inText)) {
												$(this).html(inText);
											}
										}
									});
								}
							}
							for (let i in wishListProductIds) {
								const thisProductId = wishListProductIds[i];
								const $addToWishlist = $(".add-to-wishlist-" + thisProductId);
								if ($addToWishlist.length > 0) {
									$addToWishlist.each(function () {
										const inText = $(this).data("in_text");
										if (!empty(inText)) {
											$(this).html(inText);
										}
									});
								}
							}
							$catalogItemWrapper.append("<div class='clear-div'></div>");
							$catalogItemWrapper.find("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
								social_tools: false,
								default_height: 480,
								default_width: 854,
								deeplinking: false
							});
							if (!empty(credovaUserName)) {
								showCredovaMessages();
							}
                            if (typeof afterLoadWishlistProducts == "function") {
                                setTimeout(function () {
                                    afterLoadWishlistProducts()
                                }, 100);
                            }
							$("#wish_list_products_wrapper").removeClass("hidden");
						}
					}
				});
			}

			function afterGoToTabbedContentPage($listItem) {
				if ($listItem.is(":last-child")) {
					$("#_finalize_order").removeClass("disabled-button");
				} else {
					$("#_finalize_order").addClass("disabled-button");
				}
				if ($listItem.attr("id") == "_payment_tab") {
					if ($(".saved-account-id").length == 0 && $("#_payments_wrapper").find(".payment-method-wrapper").length == 0) {
						setTimeout(function () {
							$("#_add_payment_method").trigger("click");
						}, 500);
					}
				}
			}

			function shippingValidation() {
				if ($("#_shipping_method_id_row").hasClass("not-required")) {
					return true;
				}
				if ($("#shipping_type_pickup").prop("checked")) {
					if (empty($("#shipping_method_id").val())) {
						$("#_pickup_location_section").find(".error-message").remove();
						$("#_pickup_location_section").find(".info-section-content").append("<p class='error-message'>Pickup Location is required</p>");
						displayErrorMessage("Required information is missing");
						return false;
					}
				} else {
					if (empty($("#address_id").val())) {
						$("#_delivery_location_section").find(".error-message").remove();
						$("#_delivery_location_section").find(".info-section-content").append("<p class='error-message'>Shipping Address is required</p>");
						displayErrorMessage("Required information is missing");
						return false;
					}
				}
				if (empty($("#federal_firearms_licensee_id").val()) && $("#federal_firearms_licensee_id").hasClass("validate-hidden") && ($("#ffl_dealer_not_found").length == 0 || !$("#ffl_dealer_not_found").prop("checked")) && !$("#has_cr_license").prop("checked")) {
					$("#ffl_selection_wrapper").find(".error-message").remove();
					$("#ffl_selection_wrapper").find(".info-section-content").append("<p class='error-message'>FFL Dealer is required</p>");
					displayErrorMessage("Required information is missing");
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
				}
				if ($(".designation-image").length > 0 && empty($("#designation_id").val()) && !empty($("#donation_amount").val())) {
					$("#_donation_amount_row").before("<p class='error-message' id='designation_error'>Choose a charity to which the donation will go</p>");
					setTimeout(function () {
						clearMessage();
						$("#designation_error").remove();
					}, 3000);
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
				if ($("#wish_list_products").length > 0) {
					loadWishListProducts();
				}
			}

			function afterAddToCart(productId, quantity) {
				setTimeout(function () {
					getShoppingCartItems();
				}, 1000);
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
				$("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
					if (empty($(this).find(".payment-method-id").val()) && empty($(this).find(".payment-method-account-id").val())) {
						$(this).remove();
					}
				});
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

				const paymentMethodCount = $("#_payments_wrapper").find(".payment-method-wrapper").length;
				var handlingCharge = (paymentMethodCount > 0 ? additionalPaymentHandlingCharge * (paymentMethodCount - 1) : 0);

				$(".payment-method-id-option").each(function () {
					var flatRate = parseFloat($(this).data("flat_rate"));
					if (empty(flatRate) || isNaN(flatRate)) {
						flatRate = 0;
					}
					var feePercent = parseFloat($(this).data("fee_percent"));
					if (empty(feePercent) || isNaN(feePercent)) {
						feePercent = 0;
					}
					if ((flatRate === 0 || empty(flatRate) || isNaN(flatRate)) && (empty(feePercent) || feePercent === 0 || isNaN(feePercent))) {
						return true;
					}
					var paymentMethodId = $(this).data("payment_method_id");
					$("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
						if ($(this).find(".payment-method-id").val() == paymentMethodId) {
							var amount = parseFloat($(this).find(".payment-amount-value").val());
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

				if (!unlimitedPaymentFound || $("#_payments_wrapper").find(".payment-method-wrapper").length > 1) {
					$("#_payments_wrapper").find(".payment-amount").removeClass("hidden");
				} else {
					$("#_payments_wrapper").find(".payment-amount").addClass("hidden");
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

				$(".order-total").each(function () {
					if ($(this).is("input")) {
						$(this).val(RoundFixed(orderTotal, 2, true));
					} else {
						$(this).html(RoundFixed(orderTotal, 2, false));
					}
				});
				$(".credova-button").attr("data-amount", RoundFixed(orderTotal, 2, true));
				<?php if (empty($forcePaymentMethodId)) { ?>
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
				<?php } else { ?>
				$("#no_payment_details").html("Cart Total: " + RoundFixed(cartTotal, 2) + "<br>Order Total: " + RoundFixed(orderTotal, 2));
				$("#payment_information_wrapper").addClass("hidden");
				$("#_no_payment_required").addClass("hidden");
				$("#payment_section").addClass("no-action-required").addClass("hidden");
				$(".payment-section-chooser").addClass("hidden").addClass("unused-section");
				<?php } ?>
				$(".hide-if-zero").each(function () {
					let thisValue = parseFloat($(this).find(".hide-if-zero-value").html());
					if (thisValue == 0) {
						$(this).addClass("hidden");
					} else {
						$(this).removeClass("hidden");
					}
				})
				setTimeout(function () {
					recalculateBalance();
				}, 100);
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
				$(".saved-account-id").removeClass("used");

				let paymentCount = 0;
				let totalPayments = 0;
				$("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
					paymentCount++;
					const thisPaymentAmount = parseFloat($(this).find(".payment-amount-value").val().replace(new RegExp(",", "g"), ""));
					if (isNaN(thisPaymentAmount)) {
						$(this).remove();
					} else {
						totalPayments += thisPaymentAmount;
					}
					if (empty($(this).find(".maximum-payment-amount").val()) && empty($(this).find(".maximum-payment-percentage").val())) {
						$(this).find(".payment-method-amount").val(RoundFixed(thisPaymentAmount, 2, false));
					}
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
							if (empty($(this).find(".maximum-payment-amount").val()) && empty($(this).find(".maximum-payment-percentage").val())) {
								$(this).find(".payment-amount-value").val(RoundFixed(parseFloat(thisAmount) + parseFloat(balanceDue), 2, true));
								foundPrimary = true;
							}
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
						if (empty($(this).find(".maximum-payment-amount").val()) && empty($(this).find(".maximum-payment-percentage").val())) {
							$(this).find(".payment-amount-value").val(RoundFixed(parseFloat(thisAmount) + parseFloat(balanceDue), 2, true));
							foundPrimary = true;
							return false;
						}
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

			function updateSelectedPaymentMethods() {
				const selectedPaymentMethods = $("#_payments_wrapper .payment-method-description")
					.map(function () {
						return $(this).text();
					})
					.get().join("<br>");
				$("#_selected_payment_method_wrapper span").html(selectedPaymentMethods);
			}
		</script>
		<?php
	}

	function onLoadJavascript() {
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
		$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
			getUserTypePreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");
		if (!empty($forcePaymentMethodId)) {
			$onlyOnePayment = true;
		}
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
					$("#progressbar").removeClass("fixed");
					$("#_shopping_cart_summary").removeClass("fixed");
				} else {
					$("#progressbar").addClass("fixed");
					$("#_shopping_cart_summary").addClass("fixed");
				}
			});

			$(document).on("click", "#validate_gift_card", function () {
				return false;
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
					if ($("#ffl_section").length > 0 && $("#ffl_section").hasClass("ffl-required")) {
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

					<?php if (empty($noSignature)) { ?>
					var signatureRequired = <?= ($requireSignature ? "true" : "false") ?>;
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
							$("#_shopping_cart_wrapper").html(returnArray['response']);
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
							$("#processing_order").addClass("hidden");
							orderInProcess = false;

							goToTabbedContentPage($("#_payment_tab"));
						}
						getShoppingCartItemCount();
					});
				} else {
					var fieldNames = "";
					$(".formFieldError").each(function () {
						let thisFieldName = $(this).attr("id");
						if (empty(thisFieldName)) {
							$(this).attr("name");
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

			$("#gift_order").click(function () {
				if ($(this).prop("checked")) {
					$("#_gift_text_row").removeClass("hidden");
					$("#gift_text").focus();
				} else {
					$("#_gift_text_row").addClass("hidden");
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

			$(document).on("click", "#validate_gift_card", function () {
				const thisElement = $(".payment-method-id-option.selected");
                if (!empty($("#gift_card_number").val())) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=check_gift_card&gift_card_number=" + encodeURIComponent($("#gift_card_number").val()) + "&gift_card_pin=" + encodeURIComponent($("#gift_card_pin").val()), function (returnArray) {
                        if ("gift_card_information" in returnArray) {
                            $("#gift_card_information").removeClass("error-message").addClass("info-message").html(returnArray['gift_card_information']);
                            thisElement.data("maximum_payment_amount", returnArray['maximum_payment_amount']);
                        }
                        if ("gift_card_error" in returnArray) {
                            $("#gift_card_information").removeClass("info-message").addClass("error-message").html(returnArray['gift_card_error']);
                            $("#gift_card_number").val("");
						$("#gift_card_pin").val("");
                        }
                    });
                }
			});

			$(document).on("change", "#billing_country_id", function () {
				if ($(this).val() == "1000") {
					$("#billing_state").closest(".form-line").addClass("hidden");
					$("#billing_state_select").closest(".form-line").removeClass("hidden");
				} else {
					$("#billing_state").closest(".form-line").removeClass("hidden");
					$("#billing_state_select").closest(".form-line").addClass("hidden");
				}
			});

			$(document).on("change", "#billing_state_select", function () {
				$("#billing_state").val($("#billing_state_select").val());
			});

			$(".shipping-address").change(function () {
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

			$(".billing-address").change(function () {
				if ($("#billing_country_id").val() != 1000) {
					return;
				}
				var allHaveValue = true;
				$(".billing-address").each(function () {
					if (empty($(this).val())) {
						allHaveValue = false;
						return false;
					}
				});
				<?php if (!$ignoreAddressValidation) { ?>
				if (allHaveValue) {
					loadAjaxRequest("/retail-store-controller?ajax=true&url_action=validate_address", {address_1: $("#billing_address_1").val(), city: $("#billing_city").val(), state: $("#billing_state_select").val(), postal_code: $("#billing_postal_code").val()}, function (returnArray) {
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
										$("#billing_address_1").val(returnArray['address_1']);
										$("#billing_city").val(returnArray['city']);
										$("#billing_state").val(returnArray['state']);
										$("#billing_state_select").val(returnArray['state']);
										$("#billing_postal_code").val(returnArray['postal_code']);
										$("#_validated_address_dialog").dialog('close');
									}
								}
							});
						}
					});
				}
				<?php } ?>
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
				let paymentMethodCode = $(".payment-method-id-option.selected").data("payment_method_code");
				if (empty(paymentMethodCode)) {
					paymentMethodCode = "";
				}
				let paymentMethodTypeCode = $(".payment-method-id-option.selected").data("payment_method_type_code");
				if (empty(paymentMethodTypeCode)) {
					paymentMethodTypeCode = "";
				}
				paymentMethodCode = paymentMethodCode.toUpperCase();
				paymentMethodTypeCode = paymentMethodTypeCode.toUpperCase();
				if (paymentMethodCode == "CREDOVA" || paymentMethodTypeCode == "CREDOVA") {
					$("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
						if ($(this).find(".payment-method-id").val() == $("#payment_method_id").val()) {
							displayErrorMessage("This payment method can only be used once on an order.");
							$(".payment-method-id-option.selected").addClass("disabled");
							$(".payment-method-id-option").removeClass("selected");
							$("#payment_method_id").val("");
							return;
						}
					});
					credovaPaymentMethodId = $(this).val();
					$("#credova_information").removeClass("hidden");
					var publicIdentifier = $("#public_identifier").val();
					if (empty(publicIdentifier)) {
						var orderTotal = parseFloat($("#order_total").val());
						CRDV.plugin.prequalify(orderTotal).then(function (res) {
							if (res.approved) {
								$("#public_identifier").val(res.publicId[0]);
							}
						});
					}
				}

				if (paymentMethodTypeCode.toUpperCase() == "CREDIT_CARD" || paymentMethodTypeCode.toUpperCase() == "BANK_ACCOUNT") {
					$("#_account_label_row").removeClass("hidden");
				} else {
					$("#_account_label_row").addClass("hidden");
				}
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
				var addressRequired = $(".payment-method-id-option.selected").data("address_required");
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
					if ($(this).data("payment_method_type_code") == "gift_card") {
						return true;
					}
					const paymentMethodId = $(this).data("payment_method_id");
					let foundPaymentMethod = false;
					$("#_payments_wrapper").find(".payment-method-wrapper").each(function () {
						if ($(this).find(".payment-method-id").val() == paymentMethodId) {
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
					position: {my: "center top", at: "center top+5%", of: window, collision: "none"},
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

								var maxAmount = $("#payment_method_" + paymentMethodNumber).find(".maximum-payment-amount").val();
								var maxPercent = $("#payment_method_" + paymentMethodNumber).find(".maximum-payment-percentage").val();
								if (!empty(maxPercent)) {
									const orderTotal = parseFloat($("#order_total").val());
									maxAmount = Round(parseFloat(orderTotal) * (parseFloat(maxPercent) / 100), 2);
									$(this).find(".maximum-payment-amount").val(maxAmount);
								}
								const balanceDue = parseFloat($("#_balance_due").html().replace(new RegExp(",", "g"), ""));
								var paymentAmount = (empty(maxAmount) ? balanceDue : Math.min(maxAmount, balanceDue));
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
                                $("#gift_card_pin_" + paymentMethodNumber).val($("#gift_card_pin").val());
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
								description = $(".payment-method-id-option.selected p").text() + (empty(description) ? "" : " " + description);
								$("#payment_method_" + paymentMethodNumber).find(".payment-method-description").html(description);
								$("#reference_number_" + paymentMethodNumber).val($("#reference_number").val());
								$("#payment_time_" + paymentMethodNumber).val($("#payment_time").val());
								$("#account_label_" + paymentMethodNumber).val($("#account_label").val());
								$("#same_address_" + paymentMethodNumber).val($("#same_address").prop("checked") ? "1" : "0");
								if (!empty($("#billing_state_select").val()) && empty($("#billing_state").val())) {
									$("#billing_state").val($("#billing_state_select").val());
								}
								$.each(["first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id"], function (index, value) {
									$("#billing_" + value + "_" + paymentMethodNumber).val($("#billing_" + value).val());
								});
								calculateOrderTotal();
								updateSelectedPaymentMethods();
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
				recalculateBalance();
				updateSelectedPaymentMethods();
			});

			<?php if (!$onlyOnePayment) { ?>
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
				if (event.which == 13) {
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
				const maximumAmount = parseFloat($("#_balance_due").html().replace(new RegExp(",", "g"), "")) + parseFloat($(this).data("saved_amount"));
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
				setTimeout(function () {
					calculateOrderTotal();
				}, 100)
			});
			<?php } ?>

			$(document).on("click", ".saved-account-id", function () {
				let paymentBlock = $("#_payment_method_block").html();
				let paymentMethodNumber = $("#_payments_wrapper").data("payment_method_number");
				$("#_payments_wrapper").data("payment_method_number", paymentMethodNumber + 1);
				paymentBlock = paymentBlock.replace(new RegExp("%payment_method_number%", 'g'), paymentMethodNumber);
				$("#_payments_wrapper").find(".primary-payment-method").val("0");
				$("#_payments_wrapper").append(paymentBlock);
				$("#payment_method_" + paymentMethodNumber).tooltip();
				let maximumPaymentAmount = $(this).data("maximum_payment_amount");
				let paymentAmount = parseFloat($("#_balance_due").html().replace(new RegExp(",", "g"), ""));
				if (!empty(maximumPaymentAmount)) {
					maximumPaymentAmount = parseFloat(maximumPaymentAmount);
					if (maximumPaymentAmount < paymentAmount) {
						paymentAmount = maximumPaymentAmount;
					}
				}
				$("#payment_method_" + paymentMethodNumber).find(".payment-method-image").html($(this).find(".saved-account-id-image").html());
				$("#payment_method_" + paymentMethodNumber).find(".payment-method-description").html($(this).find(".saved-account-id-description").html());
				$("#payment_method_" + paymentMethodNumber).find(".maximum-payment-amount").val($(this).data("maximum_payment_amount"));
				$("#payment_method_" + paymentMethodNumber).find(".payment-method-account-id").val($(this).data("account_id"));
				$("#payment_method_" + paymentMethodNumber).find(".payment-amount-value").val(RoundFixed(paymentAmount, 2, true));
				$("#payment_method_" + paymentMethodNumber).find(".payment-method-amount").val(RoundFixed(paymentAmount, 2, true));
				$(this).addClass("used");
				recalculateBalance();

				const paymentMethodTypeCode = $(this).data("payment_method_type_code");
				if (!empty(paymentMethodTypeCode)) {
					if (paymentMethodTypeCode == "CHARGE_ACCOUNT") {
						loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_account_limit&account_id=" + $(this).data("account_id"));
					} else if (paymentMethodTypeCode == "CREDIT_ACCOUNT") {
						loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_credit_account_limit&account_id=" + $(this).data("account_id"));
					}
				}
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

			$(document).on("click", ".designation-image", function () {
				const alreadySelected = $(this).hasClass("selected");
				$(".designation-image").removeClass("selected");
				$("#designation_id").val("");
				let designationId = "";
				if (!alreadySelected) {
					$(this).addClass("selected");
					designationId = $(this).data("designation_id");
				}
				$("#designation_id").val(designationId).trigger("change");
			});

			$(document).on("change", "#designation_id", function () {
				if (empty($(this).val())) {
					$("#_donation_amount_wrapper").addClass("hidden");
					$("#donation_amount").val("0.00");
				} else {
					$("#_donation_amount_wrapper").removeClass("hidden");
					$("#donation_amount").focus();
					if (empty($("#donation_amount").val())) {
						$("#donation_amount").val("0.00");
					}
				}
				calculateOrderTotal();
			});

			$(document).on("click", ".delivery-address-wrapper", function () {
				$("#address_id").val($(this).find(".delivery-address-choice").data("address_id")).trigger("change");
				$("#_choose_delivery_address_dialog").dialog('close');
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
					} else {
						$("#federal_firearms_licensee_id").val("");
						$("#ffl_selection_wrapper").find(".info-section-content").html("<p class='info-section-change clickable'><button id='select_ffl_location'>Select FFL</button></p>");
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
						$("#shipping_method_id").val($("#_pickup_location_section").data("shipping_method_id"));
					}
					calculateOrderTotal();
				} else {
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
				$("#shopping_cart_header").html("Shopping Cart");
				$("body").removeClass("checkout");
				<?php if (!empty($this->getPageTextChunk("SCROLL_IN_VIEW"))) { ?>
				scrollInView($("#_shopping_cart_wrapper"), -100);
				<?php } ?>
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
				$("#shopping_cart_header").html("Checkout");
				$("#designation_id").trigger("change");
				$(".tabbed-content-tab").first().trigger("click");
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
				<?php if (!empty($this->getPageTextChunk("SCROLL_IN_VIEW"))) { ?>
				scrollInView($("#_shopping_cart_wrapper"), -100);
				<?php } ?>
				getShippingMethods();
				checkFFLRequirements();

				sendAnalyticsEvent("checkout", {});
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
		</script>
		<?php
	}

	function jqueryTemplates() {
		$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
		$customFieldId = CustomField::getCustomFieldIdFromCode("DEFAULT_LOCATION_ID");
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
		$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
			getUserTypePreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");
		if (!empty($forcePaymentMethodId)) {
			$onlyOnePayment = true;
		}

		?>
		<div id="_payment_method_block">
			<div class="payment-method-wrapper" id="payment_method_%payment_method_number%">
				<div class='payment-method-image'></div>
				<div class='payment-method-description'></div>
				<div><input title='Click to Edit Amount' type='text' class='align-right payment-method-amount validate[custom[number]]' data-decimal-places='2' readonly='readonly'></div>
				<?php if (!$onlyOnePayment) { ?>
					<div class='payment-method-edit'><span class='fad fa-edit' title='Edit Amount'></span></div>
				<?php } ?>
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
				<input type="hidden" id="gift_card_pin_%payment_method_number%" name="gift_card_pin_%payment_method_number%">
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
				<div class="shopping-cart-item %other_classes%" id="shopping_cart_item_%shopping_cart_item_id%"
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
		$eCommerce = eCommerce::getEcommerceInstance();
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
		$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
			getUserTypePreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");
		if (!empty($forcePaymentMethodId)) {
			$onlyOnePayment = true;
		}
		?>
		<iframe id="_post_iframe" name="post_iframe"></iframe>

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
			<p>Click the truck icon to select an existing address or add a new address.</p>
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
				<?= createFormLineControl("addresses", "address_label", array("column_name" => "new_address_address_label", "not_null" => false, "help_label" => "add a label to identify this address")) ?>
				<?= createFormLineControl("addresses", "full_name", array("column_name" => "new_address_full_name", "not_null" => false, "help_label" => "Name of person receiving the items. Leave blank to use your name.")) ?>
				<?= createFormLineControl("addresses", "address_1", array("column_name" => "new_address_address_1", "not_null" => true, "classes" => "shipping-address autocomplete-address", "data-prefix" => "new_address_")) ?>
				<?= createFormLineControl("addresses", "address_2", array("column_name" => "new_address_address_2", "not_null" => false)) ?>
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

		<div id="_add_payment_method_dialog" class="dialog-box">
			<p class='error-message'></p>
			<form id="_payment_method_form">
				<div id="payment_method_wrapper">
					<div id="payment_information">
						<?php
						$paymentDescriptions = "";
						$paymentMethodArray = array();
						$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
							"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
							($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
							($onlyOnePayment ? "(percentage = 0 or percentage is null) and " : "") .
							"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
							(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
							"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
							"(select payment_method_type_id from payment_method_types where inactive = 0 and " . ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] ? "" : "internal_use_only = 0 and ") .
							"client_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (!empty($forcePaymentMethodId) && $row['payment_method_id'] != $forcePaymentMethodId) {
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
							<?php
							$fragment = $this->getFragment("retail_store_payment_information");
							if (!empty($fragment)) {
								echo makeHtml($fragment);
							}
							?>
							<div id="_payment_method_id_options">
								<?php
								foreach ($paymentMethodArray as $row) {
									if (function_exists("_localValidatePaymentMethod")) {
										if (!_localValidatePaymentMethod($row['payment_method_id'])) {
											continue;
										}
									}
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
								<div class="form-line" id="_account_number_row">
									<label for="account_number" class="">Card Number</label>
									<input tabindex="10" type="text" class="validate[required]"
										   data-conditional-required="!$('#payment_method_credit_card').hasClass('hidden')"
										   size="20" maxlength="20" id="account_number"
										   name="account_number" placeholder="Account Number" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_expiration_month_row">
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
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_cvv_code_row">
									<label for="cvv_code" class="">Security Code</label>
									<input tabindex="10" type="text"
										   class="validate[<?= ($GLOBALS['gInternalConnection'] ? "" : "required") ?>]"
										   data-conditional-required="!$('#payment_method_credit_card').hasClass('hidden')"
										   size="10" maxlength="4" id="cvv_code" name="cvv_code"
										   placeholder="CVV Code" value="">
									<a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img
											id="cvv_image" src="/images/cvvnumber.jpg"
											alt="CVV Code"></a>
									<div class='clear-div'></div>
								</div>
							</div> <!-- payment_method_credit_card -->

							<div class="payment-method-fields hidden" id="payment_method_bank_account">
								<div class="form-line" id="_routing_number_row">
									<label for="routing_number" class="">Bank Routing Number</label>
									<input tabindex="10" type="text"
										   class="validate[required,custom[routingNumber]]"
										   data-conditional-required="!$('#payment_method_bank_account').hasClass('hidden')"
										   size="20" maxlength="20" id="routing_number"
										   name="routing_number" placeholder="Routing Number" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_routing_number_again_row">
									<label for="routing_number_again" class="">Confirm Bank Routing Number</label>
									<input tabindex="10" type="text"
										   class="validate[required,equals[routing_number]]"
										   data-conditional-required="!$('#payment_method_bank_account').hasClass('hidden')"
										   size="20" maxlength="20" id="routing_number_again"
										   name="routing_number_again" placeholder="Confirm Routing Number" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_bank_account_number_row">
									<label for="bank_account_number" class="">Account Number</label>
									<input tabindex="10" type="text" class="validate[required]"
										   data-conditional-required="!$('#payment_method_bank_account').hasClass('hidden')"
										   size="20" maxlength="20" id="bank_account_number"
										   name="bank_account_number" placeholder="Bank Account Number"
										   value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_bank_account_number_again_row">
									<label for="bank_account_number_again" class="">Confirm Account Number</label>
									<input tabindex="10" type="text" class="validate[required,equals[bank_account_number]]"
										   data-conditional-required="!$('#payment_method_bank_account').hasClass('hidden')"
										   size="20" maxlength="20" id="bank_account_number_again"
										   name="bank_account_number_again" placeholder="Bank Account Number"
										   value="">
									<div class='clear-div'></div>
								</div>
							</div> <!-- payment_method_bank_account -->

							<div class="payment-method-fields hidden" id="payment_method_check">
								<div class="form-line" id="_reference_number_row">
									<label for="reference_number" class="">Check Number</label>
									<input tabindex="10" type="text" class="" size="20" maxlength="20"
										   id="reference_number" name="reference_number"
										   placeholder="Check Number" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_payment_time_row">
									<label for="payment_time" class="">Check Date</label>
									<input tabindex="10" type="text" class="validate[custom[date]]" size="12" maxlength="12" id="payment_time" name="payment_time" placeholder="Check Date" value="">
									<div class='clear-div'></div>
								</div>
							</div> <!-- payment_method_check -->

							<div class="payment-method-fields hidden" id="payment_method_gift_card">
								<div class="form-line" id="_gift_card_number_row">
									<label for="gift_card_number" class="">Card Number</label>
									<input tabindex="10" type="text" class="validate[required]"
										   data-conditional-required="!$('#payment_method_gift_card').hasClass('hidden')"
										   size="40" maxlength="40" id="gift_card_number"
										   name="gift_card_number" placeholder="Card Number" value="">
									<div class='clear-div'></div>
								</div>
								<div class="form-line" id="_gift_card_pin_row">
									<label for="gift_card_pin" class="">Pin</label>
									<input tabindex="10" type="text" size="8" maxlength="8" id="gift_card_pin"
										   name="gift_card_pin" placeholder="Pin" value="">
									<div class='clear-div'></div>
								</div>
								<div class='form-line'>
									<button id='validate_gift_card'>Validate & Check Balance</button>
								</div>
								<p id="gift_card_information"></p>
							</div> <!-- payment_method_gift_card -->

							<div class="payment-method-fields hidden" id="payment_method_loan">
								<div class="form-line" id="_loan_row">
									<label for="loan_number" class="">Loan Number</label>
									<input tabindex="10" type="text" class="validate[required] uppercase"
										   data-conditional-required="!$('#payment_method_loan').hasClass('hidden')"
										   size="20" maxlength="30" id="loan_number" name="loan_number"
										   placeholder="Loan Number" value="">
									<div class='clear-div'></div>
								</div>
								<p class="loan-information"></p>
							</div> <!-- payment_method_loan -->

							<div class="payment-method-fields hidden" id="payment_method_lease">
								<div class="form-line" id="_lease_row">
									<label for="lease_number" class="">Lease Number</label>
									<input tabindex="10" type="text" class="validate[required] uppercase"
										   data-conditional-required="!$('#payment_method_lease').hasClass('hidden')"
										   size="20" maxlength="30" id="lease_number" name="lease_number"
										   placeholder="Lease Number" value="">
									<div class='clear-div'></div>
								</div>
							</div> <!-- payment_method_lease -->

							<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
								<div class="form-line" id="_account_label_row">
									<label for="account_label" class="">Account Nickname</label>
									<span class="help-label">to use this account again in the future</span>
									<input tabindex="10" type="text" class="" size="20" maxlength="30"
										   id="account_label" name="account_label"
										   placeholder="Account Label" value="">
									<div class='clear-div'></div>
								</div>
							<?php } ?>
						</div> <!-- new-account -->
					</div> <!-- payment_information -->

					<?php
					$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
					?>
					<div id="payment_address" class="new-account">
						<div class="form-line hidden" id="_same_address_row">
							<label class=""></label>
							<input tabindex="10" type="checkbox" <?= (empty($forceSameAddress) ? "" : "disabled='disabled'") ?> id="same_address" name="same_address" checked="checked" value="1"><label id="same_address_label" class="checkbox-label" for="same_address">Billing address is same as shipping</label>
							<div class='clear-div'></div>
						</div>

						<div id="_billing_address" class="hidden">

							<div class="form-line inline-block" id="_billing_first_name_row">
								<label for="billing_first_name">First Name</label>
								<input tabindex="10" type="text"
									   class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>"
									   data-conditional-required="!$('#same_address').prop('checked')"
									   size="25" maxlength="25" id="billing_first_name"
									   name="billing_first_name" placeholder="First Name"
									   value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
								<div class='clear-div'></div>
							</div>

							<div class="form-line inline-block" id="_billing_last_name_row">
								<label for="billing_last_name">Last Name</label>
								<input tabindex="10" type="text"
									   class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>"
									   data-conditional-required="!$('#same_address').prop('checked')"
									   size="30" maxlength="35" id="billing_last_name"
									   name="billing_last_name" placeholder="Last Name"
									   value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_business_name_row">
								<label for="billing_business_name">Business Name</label>
								<input tabindex="10" type="text"
									   class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>"
									   data-conditional-required="!$('#same_address').prop('checked')"
									   size="30" maxlength="35" id="billing_business_name"
									   name="billing_business_name" placeholder="Business Name"
									   value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_address_1_row">
								<label for="billing_address_1" <?= (!$GLOBALS['gInternalConnection'] ? ' class="required-label"' : "") ?>>Address</label>
								<input tabindex="10" type="text" data-prefix="billing_" autocomplete='chrome-off' autocomplete='off'
									   class="autocomplete-address billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>"
									   data-conditional-required="!$('#same_address').prop('checked')"
									   size="30" maxlength="60" id="billing_address_1"
									   name="billing_address_1" placeholder="Address" value="">
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_city_row">
								<label for="billing_city" <?= (!$GLOBALS['gInternalConnection'] ? ' class="required-label"' : "") ?>>City</label>
								<input tabindex="10" type="text"
									   class="billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>"
									   data-conditional-required="!$('#same_address').prop('checked')"
									   size="30" maxlength="60" id="billing_city" name="billing_city"
									   placeholder="City" value="">
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_state_row">
								<label for="billing_state" class="">State</label>
								<input tabindex="10" type="text"
									   class="billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>"
									   data-conditional-required="!$('#same_address').prop('checked')"
									   size="10" maxlength="30" id="billing_state" name="billing_state"
									   placeholder="State" value="">
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_state_select_row">
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
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_postal_code_row">
								<label for="billing_postal_code" class="">Postal Code</label>
								<input tabindex="10" type="text"
									   class="validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]"
									   size="10"
									   maxlength="10"
									   data-conditional-required="!$('#same_address').prop('checked') && $('#billing_country_id').val() == 1000"
									   id="billing_postal_code" name="billing_postal_code"
									   placeholder="Postal Code" value="">
								<div class='clear-div'></div>
							</div>

							<div class="form-line" id="_billing_country_id_row">
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
								<div class='clear-div'></div>
							</div>
						</div> <!-- billing_address -->
					</div> <!-- payment_address -->
				</div> <!-- payment_method_wrapper -->
			</form>
		</div>
		<?php
	}
}

$pageObject = new ShoppingCartPage();
$pageObject->displayPage();
