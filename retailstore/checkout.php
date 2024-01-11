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
			setCoreCookie("source_id", $sourceId, 6);
		}
		if (empty($_GET['shopping_cart_code'])) {
			$_GET['shopping_cart_code'] = "RETAIL";
		}
		$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
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
		$this->iUseRecaptchaV2 = !empty(getPreference("ORDER_RECAPTCHA_V2_SITE_KEY")) && !empty(getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY"));
	}

	function headerIncludes() {
		?>
        <script src="<?= autoVersion('/js/jsignature/jSignature.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.CompressorSVG.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.UndoButton.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/signhere/jSignature.SignHere.js') ?>"></script>
		<?php
#        echo '<script src="https://serveipqs.com/api/*/QG87fca0KhDNnBUWdwsRDfM3VMnMao8gqtf8leVPhdU3kMVrHbR27Ep8qZgRdFCIDr26b9whOSuas9ktf19z3XPdW42BpUAS20F9EKgATLTitQFO9hizW9dNS77lLfz9Mip6F0FJRedHfsRT5IlZ2PDxOVhpzU6qZAMY5reqXVIavcB9jj3lmZ1nkQefaYZAi0fntizXuJapmnPxRsO2GOGz3IfktahkBsP1U7mvvovE6N0elevbLFBMHldexAcq/learn.js" crossorigin="anonymous"></script><noscript><img src="https://serveipqs.com/api/*/QG87fca0KhDNnBUWdwsRDfM3VMnMao8gqtf8leVPhdU3kMVrHbR27Ep8qZgRdFCIDr26b9whOSuas9ktf19z3XPdW42BpUAS20F9EKgATLTitQFO9hizW9dNS77lLfz9Mip6F0FJRedHfsRT5IlZ2PDxOVhpzU6qZAMY5reqXVIavcB9jj3lmZ1nkQefaYZAi0fntizXuJapmnPxRsO2GOGz3IfktahkBsP1U7mvvovE6N0elevbLFBMHldexAcq/pixel.png" /></noscript>';

        if ($this->iUseRecaptchaV2) {
			?>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
			<?php
		}
	}

	function javascript() {
		$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
			getUserTypePreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");
		$noShippingRequired = getUserTypePreference("RETAIL_STORE_NO_SHIPPING");
		$requireExternalAddressIdentifier = getUserTypePreference("CHECKOUT_REQUIRE_EXTERNAL_ADDRESS");
		$addressArray = array();
		if (empty($requireExternalAddressIdentifier) && !empty($GLOBALS['gUserRow']['address_1']) && !empty($GLOBALS['gUserRow']['city']) && ($GLOBALS['gUserRow']['country_id'] > 1001 || !empty($GLOBALS['gUserRow']['postal_code']))) {
			$addressArray["0"] = array("address_label" => "Primary Address", "address_1" => $GLOBALS['gUserRow']['address_1'], "address_2" => $GLOBALS['gUserRow']['address_2'],
				"city" => $GLOBALS['gUserRow']['city'], "state" => $GLOBALS['gUserRow']['state'], "postal_code" => $GLOBALS['gUserRow']['postal_code'],
				"country_id" => $GLOBALS['gUserRow']['country_id']);
		}
		$resultSet = executeQuery("select address_id,address_label,address_1,address_2,city,state,postal_code,country_id from addresses where contact_id = ? and " .
			"inactive = 0 and address_1 is not null and city is not null and (country_id > 1001 or postal_code is not null) and address_label is not null" . (empty($requireExternalAddressIdentifier) ? "" : " and external_identifier is not null"), $GLOBALS['gUserRow']['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$addressArray[$row['address_id']] = $row;
		}
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
		?>
        <script>
            let additionalPaymentHandlingCharge = <?= $additionalPaymentHandlingCharge ?>;
            emptyImageFilename = "<?= $missingProductImage ?>";
            let credovaPaymentMethodId = "", credovaCheckoutCompleted = false,
                addressArray = <?= jsonEncode($addressArray) ?>, orderInProcess = false;

			<?php
			$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
			if (!empty($fflRequiredProductTagId)) {
			?>
            let fflDealers = [];
			<?php
			}
			?>

            function getItemAvailabilityTexts() {
                if ($(".item-availability").length > 0) {
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

            function getFFLTaxCharge() {
				<?php if ($noShippingRequired) { ?>
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_tax_charge", { federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val() }, function (returnArray) {
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

            function afterAddToCart(productId, quantity) {
                setTimeout(function () {
                    getShoppingCartItems();
                }, 1000);
            }

            function coreAfterGetShoppingCartItems() {
                getFFLTaxCharge();
                if ($("#related_products").length === 0) {
                    return;
                }
                let productIds = "";
                $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
                    productIds += (empty(productIds) ? "" : ",") + $(this).data("product_id");
                });
                loadRelatedProducts(productIds);
            }

            function goToSection(sectionId) {
                if (!empty(sectionId)) {
                    if ($("#" + sectionId + ".checkout-section").length === 0) {
                        sectionId = "";
                    }
                }
                if ($(".section-chooser-option.selected").length === 0 || empty(sectionId)) {
                    $(".checkout-section").not(".hidden").first().find(".previous-section-button").remove();
                    let sectionNumber = 0;
                    $(".section-chooser-option").each(function () {
                        sectionNumber++;
                        $(this).data("section_number", sectionNumber).attr("id", "section_chooser_" + sectionNumber);
                        const thisSectionId = $(this).data("section");
                        $("#" + thisSectionId).data("section_number", sectionNumber);
                    });
                    $(".section-chooser-option").removeClass("selected").not(".hidden").first().addClass("selected");
                    sectionId = $(".section-chooser-option.selected").data("section");
                    $(".checkout-section").addClass("hidden");
                    $("#" + sectionId).removeClass("hidden");
                } else {
                    $(".section-chooser-option").removeClass("selected");
                    $(".section-chooser-option").each(function () {
                        const thisSectionId = $(this).data("section");
                        if (thisSectionId === sectionId) {
                            $(this).addClass("selected");
                            return false;
                        }
                    });
                    sectionId = $(".section-chooser-option.selected").data("section");
                    if (!empty(sectionId)) {
                        $(".checkout-section").addClass("hidden");
                        $("#" + sectionId).removeClass("hidden");
                    }
                }
                setTimeout(function () {
                    $("#" + sectionId).find("input[type=text]:not([readonly='readonly']):not([disabled='disabled']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])").first().focus();
                }, 100);
            }

            let savedShippingMethodId = "";

            function getShippingMethods() {
                if (shippingMethodCountryId === false || shippingMethodState === false || shippingMethodPostalCode === false) {
                    getNewShippingMethods = true;
                }
                if (shippingMethodCountryId != $("#country_id").val()) {
                    getNewShippingMethods = true;
                }
                if (shippingMethodState != $("#state").val()) {
                    getNewShippingMethods = true;
                }
                if (shippingMethodPostalCode != $("#postal_code").val()) {
                    getNewShippingMethods = true;
                }
                shippingMethodCountryId = $("#country_id").val();
                shippingMethodState = $("#state").val();
                shippingMethodPostalCode = $("#postal_code").val();

                $("#shipping_method_id").val("").find("option[value!='']").remove();
                $("#_shipping_method_id_row").addClass("hidden");
                $("#calculating_shipping_methods").removeClass("hidden");
                let pickupShippingMethodId = "";
                if ($("#shipping_type_pickup").length > 0) {
                    pickupShippingMethodId = $("#pickup_shipping_method_id").val();
                }
                const excludePickupLocations = ($("#shipping_type_wrapper").length > 0);
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shipping_methods&exclude_pickup_locations=" + (excludePickupLocations ? "true" : "") + "&pickup_shipping_method_id=" + pickupShippingMethodId, { state: $("#state").val(), postal_code: $("#postal_code").val(), country_id: $("#country_id").val() }, function (returnArray) {
                    $("#shipping_method_id").val("").find("option[value!='']").remove();
                    let shippingMethodCount = 0;
                    let singleShippingMethodId = "";

                    let pickupShippingMethodFound = false;
                    if ("shipping_methods" in returnArray) {
                        for (const i in returnArray['shipping_methods']) {
                            if (pickupShippingMethodId == returnArray['shipping_methods'][i]['key_value']) {
                                pickupShippingMethodFound = true;
                            }

                            const thisOption = $("<option></option>").data("shipping_method_code", returnArray['shipping_methods'][i]['shipping_method_code']).data("shipping_charge", returnArray['shipping_methods'][i]['shipping_charge']).data("pickup", returnArray['shipping_methods'][i]['pickup']).attr("value", returnArray['shipping_methods'][i]['key_value']).text(returnArray['shipping_methods'][i]['description']);
                            $("#shipping_method_id").append(thisOption);
                            singleShippingMethodId = returnArray['shipping_methods'][i]['key_value'];
                            shippingMethodCount++;
                        }
                    }
                    if (!pickupShippingMethodFound && !empty(pickupShippingMethodId) && $("#shipping_type_pickup").length > 0) {
                        $("#pickup_shipping_method_id").val("");
                        $("#pickup_display").html("None Selected");
                    } else if (!empty(pickupShippingMethodId)) {
                        if ($("#shipping_type_pickup").prop("checked")) {
                            savedShippingMethodId = pickupShippingMethodId;
                        }
                    }
                    if ("shipping_calculation_log" in returnArray && $("#shipping_calculation_log").length > 0) {
                        $("#shipping_calculation_log").html(returnArray['shipping_calculation_log']);
                    }
                    if ($("#shipping_method_id").find("option[value!='']").length === 0 && !empty($("#country_id").val()) &&
                        ($("#country_id").val() != 1000 || (!empty($("#state").val()) && !empty($("#postal_code").val())))) {
                        if (empty($(".error-message").first().html())) {
                            displayErrorMessage("No shipping methods found for this location.");
                        }
                    }
                    if (!empty(savedShippingMethodId)) {
                        if ($("#shipping_method_id option[value='" + savedShippingMethodId + "']").length > 0) {
                            $("#shipping_method_id").val(savedShippingMethodId).trigger("change");
                        } else {
                            savedShippingMethodId = "";
                        }
                    }
                    if (empty(savedShippingMethodId) && shippingMethodCount === 1 && !empty(singleShippingMethodId)) {
                        $("#shipping_method_id").val(singleShippingMethodId).trigger("change");
                    }
                    $("#_shipping_method_id_row").removeClass("hidden");
                    $("#calculating_shipping_methods").addClass("hidden");
                    getTaxCharge();
                });
            }

            function getTaxCharge() {
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_tax_charge", { shipping_method_id: $("#shipping_method_id").val(), country_id: $("#country_id").val(), city: $("#city").val(), postal_code: $("#postal_code").val(), state: $("#state").val() }, function (returnArray) {
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
				<?php if ($GLOBALS['gInternalConnection']) { ?>
                if ($("#tax_exempt").prop("checked")) {
                    $("#tax_charge").val("0");
                    $(".tax-charge").html("0.00");
                }
				<?php } ?>
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
                var shippingCharge = (empty(shippingChargeString) ? 0 : parseFloat(shippingChargeString));

                const paymentMethodCount = $("#payment_methods_list").find(".payment-list-item").length;
                var handlingCharge = (paymentMethodCount > 0 ? additionalPaymentHandlingCharge * (paymentMethodCount - 1) : 0);

                $("#payment_method_id").find("option").each(function () {
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
                    var paymentMethodId = $(this).val();
                    $("#payment_methods_list").find(".payment-list-item").each(function () {
                        if ($(this).find(".payment-method-id").val() == paymentMethodId) {
                            var amount = parseFloat($(this).find(".payment-amount-value").val());
                            if (empty(amount) || amount === 0 || isNaN(amount)) {
                                amount = 0;
                            }
                            if (amount === 0) {
                                $(this).find(".primary-payment-method").val("1");
                                amount = cartTotal + shippingCharge + taxCharge;
                                $("#payment_methods_list").find(".payment-list-item").each(function () {
                                    if (!empty($(this).find(".payment-amount-value"))) {
                                        var thisAmount = parseFloat($(this).find(".payment-amount-value").val());
                                        if (empty(thisAmount) || thisAmount == 0 || isNaN(thisAmount)) {
                                            thisAmount = 0;
                                        }
                                        amount -= thisAmount;
                                    }
                                });
                            } else {
                                $(this).find(".primary-payment-method").val("");
                            }
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
                    $(".donation-amount").each(function () {
                        if ($(this).is("input")) {
                            $(this).val(donationAmount);
                        } else {
                            $(this).html(RoundFixed(donationAmount, 2));
                        }
                    });
                    if (isNaN(donationAmount)) {
                        $("#donation_amount").val("");
                        donationAmount = 0;
                    }
                    orderTotal = parseFloat(orderTotal) + parseFloat(donationAmount);
                }
                orderTotal = RoundFixed(orderTotal, 2, true);
                var unlimitedPaymentFound = false;
                var totalPayment = 0;
                $("#payment_methods_list").find(".payment-list-item").each(function () {
                    var maxAmount = $(this).find(".maximum-payment-amount").val();
                    var maxPercent = $(this).find(".maximum-payment-percentage").val();
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
                    var paymentAmount = $(this).find(".payment-amount-value").val();
                    $(this).find(".primary-payment-method").val("");
                    if (empty(paymentAmount) && empty(maxAmount)) {
                        unlimitedPaymentFound = true;
                        $(this).find(".primary-payment-method").val("1");
                    } else if (!empty(maxAmount)) {
                        if (!empty(paymentAmount)) {
                            paymentAmount = Math.min(maxAmount, paymentAmount);
                            if (parseFloat(maxAmount) < parseFloat(paymentAmount)) {
                                $(this).find("payment-amount-value").val(RoundFixed(paymentAmount, 2, true));
                            }
                        }
                        if (parseFloat(paymentAmount) >= parseFloat(orderTotal) || empty(paymentAmount)) {
                            $(this).find(".payment-amount-value").val("");
                            unlimitedPaymentFound = true;
                            $(this).find(".primary-payment-method").val("1");
                        }
                    }
                    if (!empty(paymentAmount)) {
                        totalPayment = parseFloat(paymentAmount) + parseFloat(totalPayment);
                    }
                });

                if (totalPayment < orderTotal) {
                    $("#_balance_remaining_wrapper").removeClass("hidden");
                    $("#_order_total_exceeded").addClass("hidden");
                    $("#_balance_remaining").html(RoundFixed(orderTotal - totalPayment, 2));
                } else {
                    $("#_balance_remaining_wrapper").addClass("hidden");
                    $("#_order_total_exceeded").addClass("hidden");
                    if (totalPayment > orderTotal) {
                        $("#_order_total_exceeded").removeClass("hidden");
                    }
                }

                if (!unlimitedPaymentFound || $("#payment_methods_list").find(".payment-list-item").length > 1) {
                    $("#payment_methods_list").find(".payment-amount").removeClass("hidden");
                } else {
                    $("#payment_methods_list").find(".payment-amount").addClass("hidden");
                }

                var discountAmount = $("#discount_amount").val();
                if (isNaN(discountAmount)) {
                    discountAmount = 0;
                }
                var discountPercent = $("#discount_percent").val();
                if (isNaN(discountPercent)) {
                    discountPercent = 0;
                }
                if (discountAmount == 0 && discountPercent > 0) {
                    discountAmount = Round(cartTotal * (discountPercent / 100), 2);
                }
                if (discountAmount < 0) {
                    discountAmount = 0;
                }
                orderTotal = RoundFixed(orderTotal - discountAmount, 2, false);
                if (discountAmount > 0) {
                    $("#order_summary_discount_wrapper").removeClass("hidden");
                    $(".discount-amount").html(RoundFixed(discountAmount, 2));
                } else {
                    $("#order_summary_discount_wrapper").addClass("hidden");
                    $(".discount-amount").html("");
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
                return orderTotal;
            }

            function addPaymentMethod() {
                if ($("#new_payment").val() == "1") {
                    return;
                }
                var paymentValidated = true;
                $("#payment_method_wrapper").find("input,select").each(function () {
                    if ($(this).is(":visible") && $(this).validationEngine("validate")) {
                        paymentValidated = false;
                    }
                });
                if (!paymentValidated) {
                    return;
                }
                copyPaymentMethodToList();
                $("#payment_method_wrapper").find("input,select").each(function () {
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", false);
                    } else {
                        $(this).val("");
                    }
                });
                $("#billing_country_id").val("1000");
                $("#billing_state").closest(".form-line").addClass("hidden");
                $("#billing_state_select").closest(".form-line").removeClass("hidden");
                $("#new_payment").val("1");
                var paymentMethodNumber = $("#next_payment_method_number").val();
                $("#payment_method_number").val(paymentMethodNumber);
                $("#next_payment_method_number").val(parseInt(paymentMethodNumber) + 1);
                $("#account_id").trigger("change");
                $("#payment_method_id").trigger("change");
                $(this).addClass("hidden");
            }

            function copyPaymentMethodToList() {
                var paymentMethodNumber = $("#payment_method_number").val();
                if ($("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).length === 0) {
                    var paymentListItem = $("#payment_list_template").html();
                    paymentListItem = paymentListItem.replace(new RegExp("%payment_method_number%", 'ig'), paymentMethodNumber);
                    $("#payment_methods_list").append(paymentListItem);
                }
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find("input").each(function () {
                    var elementId = $(this).attr("id");
                    elementId = elementId.substr(0, elementId.length - ("_" + paymentMethodNumber).length);
                    if ($("#" + elementId).length === 0) {
                        return true;
                    }
                    if ($("#" + elementId).is("input[type=checkbox]")) {
                        $(this).val($("#" + elementId).prop("checked") ? "1" : "0");
                    } else {
                        $(this).val($("#" + elementId).val());
                    }
                });
                var paymentMethodDescription = empty($("#payment_method_id").val()) ? "" : $("#payment_method_id option:selected").text();
                if (!empty($("#account_id").val())) {
                    paymentMethodDescription = $("#account_id option:selected").text();
                }
                var accountNumber = $("#account_number").val().substr(-4) + $("#bank_account_number").val().substr(-4) + $("#gift_card_number").val().substr(-4) + $("#loan_number").val().substr(-4) + $("#lease_number").val().substr(-4);
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".payment-method").html(paymentMethodDescription + " " + accountNumber);
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".maximum-payment-amount").val($("#payment_method_id option:selected").data("maximum_payment_amount"));
                if (!empty($("#payment_method_id option:selected").data("maximum_payment_amount"))) {
                    const maximumPaymentAmount = parseFloat($("#payment_method_id option:selected").data("maximum_payment_amount"));
                    const cartTotal = parseFloat($(".cart-total").html().replace(",", "").replace(",", ""));
                    const existingAmount = parseFloat($("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".payment-amount-value").val());
                    if (existingAmount > cartTotal || existingAmount > maximumPaymentAmount) {
                        $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".payment-amount-value").val("")
                    }
                    if (maximumPaymentAmount < cartTotal) {
                        $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".payment-amount-value").val(RoundFixed(maximumPaymentAmount, 2));
                    }
                }
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".maximum-payment-percentage").val($("#payment_method_id option:selected").data("maximum_payment_percentage"));
                if (empty($("#payment_methods_list").find("#payment_amount_" + paymentMethodNumber).val())) {
                    $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".primary-payment-method").val("1");
                } else {
                    $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".primary-payment-method").val("");
                }

                $(".payment-method").each(function () {
                    if (empty($(this).html())) {
                        $(this).closest(".payment-list-item").addClass("hidden");
                    } else {
                        $(this).closest(".payment-list-item").removeClass("hidden");
                    }
                });
                calculateOrderTotal();
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

            $(document).on("click", ".pickup-location-choice", function () {
                const locationId = $(this).data("location_id");
                const locationCode = $(this).data("location_code");
                const shippingMethodId = $(this).data("shipping_method_id");
                const pickupDescription = $(this).closest(".pickup-location-wrapper").find(".pickup-location-description").text();
                $("#shipping_type_pickup").trigger("click");
                $("#pickup_shipping_method_id").val(shippingMethodId);
                $("#pickup_display").html(pickupDescription);
                $("#_choose_pickup_location_dialog").dialog('close');
                getShippingMethods();
                setDefaultLocation(locationCode);
                return false;
            });

            $(document).on("click", "#change_pickup_location", function () {
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_pickup_locations", function (returnArray) {
                    if ("pickup_locations" in returnArray) {
                        $("#pickup_locations").html(returnArray['pickup_locations']);
                        $('#_choose_pickup_location_dialog').dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
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

            $(document).on("click", "#shipping_type_pickup", function () {
                $("#_shipping_method_id_row").addClass("pickup-type-selected");
                $("#shipping_information_section").addClass("internal-pickup");
                $("#shipping_method_id").val($("#pickup_shipping_method_id").val()).trigger("change");
            });

            $("#shipping_type_pickup").trigger("click");
            $(document).on("click", "#shipping_type_ship", function () {
                $("#_shipping_method_id_row").removeClass("pickup-type-selected");
                const shippingMethodCode = $("#shipping_method_id").find("option:selected").data("shipping_method_code");
                const pickupShippingMethod = $("#shipping_method_id").find("option:selected").data("pickup");
                if (shippingMethodCode == "PICKUP" || !empty(pickupShippingMethod)) {
                    $("#shipping_information_section").addClass("internal-pickup");
                } else {
                    $("#shipping_information_section").removeClass("internal-pickup");
                }
            });

            $(document).on("click", "#show_shipping_calculation_log", function () {
                $('#_shipping_calculation_log_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
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
            $(".shipping-address").change(function () {
                if ($("#country_id").val() != 1000) {
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
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=validate_address", { address_1: $("#address_1").val(), city: $("#city").val(), state: $("#state_select").val(), postal_code: $("#postal_code").val() }, function (returnArray) {
                        if ("validated_address" in returnArray) {
                            $("#entered_address").html(returnArray['entered_address']);
                            $("#validated_address").html(returnArray['validated_address']);
                            $('#_validated_address_dialog').dialog({
                                closeOnEscape: true,
                                draggable: true,
                                modal: true,
                                resizable: true,
                                position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                                width: 600,
                                title: 'Address Validation',
                                buttons: {
                                    "Use Mine": function (event) {
                                        $("#_validated_address_dialog").dialog('close');
                                    },
                                    "Use Validated Address": function (event) {
                                        $("#address_1").val(returnArray['address_1']);
                                        $("#city").val(returnArray['city']);
                                        $("#state").val(returnArray['state']);
                                        $("#state_select").val(returnArray['state']);
                                        $("#postal_code").val(returnArray['postal_code']);
                                        $("#_validated_address_dialog").dialog('close');
                                        getFFLDealers();
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
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=validate_address", { address_1: $("#billing_address_1").val(), city: $("#billing_city").val(), state: $("#billing_state_select").val(), postal_code: $("#billing_postal_code").val() }, function (returnArray) {
                        if ("validated_address" in returnArray) {
                            $("#entered_address").html(returnArray['entered_address']);
                            $("#validated_address").html(returnArray['validated_address']);
                            $('#_validated_address_dialog').dialog({
                                closeOnEscape: true,
                                draggable: true,
                                modal: true,
                                resizable: true,
                                position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
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

            $("#login_now_button").click(function () {
                if (!empty($("#login_user_name").val()) && !empty($("#login_password").val())) {
                    $("#login_now_button").addClass("hidden");
                    $("#logging_in_message").html("Logging in...");
                    loadAjaxRequest("/loginform.php?ajax=true&url_action=login", { from_form: "<?= getRandomString(8) ?>", login_user_name: $("#login_user_name").val(), login_password: $("#login_password").val() }, function (returnArray) {
                        if ("error_message" in returnArray) {
                            $("#login_now_button").removeClass("hidden");
                            $("#logging_in_message").html("");
                        } else {
                            $("#logging_in_message").html("Reloading Shopping Cart to reflect your login...");
                            setTimeout(function () {
                                document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                            }, 2000);
                        }
                    });
                }
                return false;
            });
            $("#account_id").data("swipe_string", "");
            $("#payment_method_id").data("swipe_string", "");

            $("#account_id,#payment_method_id").keypress(function (event) {
                var thisChar = String.fromCharCode(event.which);
                if ($(this).data("swipe_string") != "") {
                    if (event.which == 13) {
                        processMagneticData($(this).data("swipe_string"));
                        $(this).data("swipe_string", "");
                    } else {
                        $(this).data("swipe_string", $(this).data("swipe_string") + thisChar);
                    }
                    return false;
                } else {
                    if (thisChar == "%") {
                        $(this).data("swipe_string", "%");
                        setTimeout(function () {
                            if ($(this).data('swipe_string') == "%") {
                                $(this).data('swipe_string', "");
                            }
                        }, 3000);
                        return false;
                    } else {
                        return true;
                    }
                }
            });
            $(document).on("change", "#ffl_radius", function () {
                getFFLDealers();
            });
			<?php if (empty($noSignature)) { ?>
            $(".signature-palette").jSignature({ 'UndoButton': true, "height": 140 });
			<?php } ?>
            $(document).on("change", ".payment-amount-value", function () {
                calculateOrderTotal();
            });

            $("#account_id").change(function () {
                var paymentMethodId = $("#account_id option:selected").data("payment_method_id");
                var paymentMethodTypeCode = $("#account_id option:selected").data("payment_method_type_code");
                if (!empty($("#valid_payment_methods").val())) {
                    if (!isInArray(paymentMethodId, $("#valid_payment_methods").val())) {
                        $(this).val("");
                        paymentMethodId = "";
                        displayErrorMessage("One or more items in the cart cannot be paid for with this payment method.");
                    }
                }
                if (empty($(this).val())) {
                    $(".new-account").removeClass("hidden");
                    $("#payment_method_id").val("");
                    $(".payment-method-fields").addClass("hidden");
                } else {
                    $(".new-account").addClass("hidden");
                    $("#payment_method_id").val(paymentMethodId).trigger("change");
                }
                if (!empty(paymentMethodTypeCode)) {
                    if (paymentMethodTypeCode == "CHARGE_ACCOUNT") {
                        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_account_limit&account_id=" + $(this).val());
                    } else if (paymentMethodTypeCode == "CREDIT_ACCOUNT") {
                        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_credit_account_limit&account_id=" + $(this).val());
                    }
                }
            });
            $("#billing_first_name,#billing_last_name").change(function () {
                if ($("#ffl_section").hasClass("ffl-required")) {
                    if ((!empty($("#billing_first_name").val()) && $("#billing_first_name").val().toLowerCase() != $("#first_name").val().toLowerCase()) ||
                        (!empty($("#billing_last_name").val()) && $("#billing_last_name").val().toLowerCase() != $("#last_name").val().toLowerCase())) {
                        displayErrorMessage("Firearms can only be purchased by the person to whom they are being shipped. This payment method cannot be used.");
                        $(this).val("").focus();
                    }
                }
            });
            $(document).on("click", ".remove-payment", function () {
                var paymentMethodNumber = $(this).closest(".payment-list-item").find(".payment-method-number").val();
                $(this).closest(".payment-list-item").remove();
                if ($("#payment_methods_list").find(".payment-list-item").length == 0) {
                    $("#payment_method_wrapper").find("input,select").each(function () {
                        if ($(this).is("input[type=checkbox]")) {
                            $(this).prop("checked", false);
                        } else {
                            $(this).val("");
                        }
                    });
                    $("#new_account").val("1");
                    $("#billing_country_id").val("1000");
                    $("#billing_state").closest(".form-line").addClass("hidden");
                    $("#billing_state_select").closest(".form-line").removeClass("hidden");
                    var paymentMethodNumber = $("#next_payment_method_number").val();
                    $("#payment_method_number").val(paymentMethodNumber);
                    $("#next_payment_method_number").val(parseInt(paymentMethodNumber) + 1);
                    $("#payment_method_id").trigger("change");
                } else if (paymentMethodNumber == $("#payment_method_number").val()) {
                    $("#new_payment").val("1");
                    $("#payment_methods_list").find(".payment-list-item").first().find(".edit-payment").trigger("click");
                }
            });
            $(document).on("click", ".edit-payment", function () {
                var paymentValidated = true;
                if ($("#new_payment").val() == "") {
                    $("#payment_method_wrapper").find("input,select").each(function () {
                        if ($(this).is(":visible") && $(this).validationEngine("validate")) {
                            paymentValidated = false;
                        }
                    });
                }
                if (!paymentValidated) {
                    return;
                }
                copyPaymentMethodToList();
                $("#payment_method_wrapper").find("input,select").each(function () {
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", false);
                    } else {
                        $(this).val("");
                    }
                });
                $("#new_payment").val("");

                var paymentMethodNumber = $(this).closest(".payment-list-item").find(".payment-method-number").val();
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find("input").each(function () {
                    var elementId = $(this).attr("id");
                    elementId = elementId.substr(0, elementId.length - ("_" + paymentMethodNumber).length);
                    if ($("#" + elementId).is("input[type=checkbox]")) {
                        $("#" + elementId).prop("checked", $(this).val() == "1");
                    } else {
                        $("#" + elementId).val($(this).val());
                    }
                });
                $(".payment-method-logo").removeClass("selected");
                $("#payment_method_logo_" + $("#payment_method_id").val()).addClass("selected");
                $(".payment-method-fields").addClass("hidden");
                if ($("#payment_method_id").val() != "") {
                    var paymentMethodTypeCode = $("#payment_method_id").find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).removeClass("hidden");
                }
                var addressRequired = $("#payment_method_id").find("option:selected").data("address_required");

                if (empty(addressRequired)) {
                    $("#_same_address_row").addClass("hidden");
                    $("#_billing_address").addClass("hidden");
                } else {
                    $("#_same_address_row").removeClass("hidden");
                }
                if (empty(addressRequired) || $("#same_address").prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                    $("#billing_country_id").val("1000");
                } else {
                    $("#_billing_address").removeClass("hidden");
                }
                $("#billing_country_id").trigger("change");
                if (!empty($("#account_id").val())) {
                    $("#account_id").trigger("change");
                } else {
                    $(".new-account").removeClass("hidden");
                }
            });
            $(document).on("change click", "#payment_section input,#payment_section select", function () {
                $("#new_payment").val("");
                copyPaymentMethodToList();
            });

            $("#shipping_method_id").change(function () {
                checkFFLRequirements();
                savedShippingMethodId = $(this).val();
                getTaxCharge();
                const shippingMethodCode = $(this).find("option:selected").data("shipping_method_code");
                const pickupShippingMethod = $(this).find("option:selected").data("pickup");
                if (pickupShippingMethod) {
                    getItemAvailabilityTexts();
                } else {
                    $(".item-availability").addClass("hidden");
                }
                if (shippingMethodCode == "PICKUP" || !empty(pickupShippingMethod) || ($("#shipping_type_pickup").length > 0 && $("#shipping_type_pickup").prop("checked"))) {
                    $("#shipping_information_section").addClass("internal-pickup");
                } else {
                    $("#shipping_information_section").removeClass("internal-pickup");
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
            $("#country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_state_row").hide();
                    $("#_state_select_row").show();
                } else {
                    $("#_state_row").show();
                    $("#_state_select_row").hide();
                }
                $("#postal_code").trigger("change");
            }).trigger("change");
            $("#state_select").change(function () {
                $("#state").val($(this).val());
                addRetailAgreements();
            });

            $("#address_id").change(function () {
                if ($(this).val().length == 0) {
                    $("#shipping_address_wrapper").addClass("hidden");
                } else {
                    $("#shipping_address_wrapper").removeClass("hidden");
                }
                $("#update_primary_address").val("");
                if ($(this).val() == -1) {
                    $("#shipping_address_wrapper").find("input").prop("readonly", false).val("");
                    $("#shipping_address_wrapper").find("select").removeClass("disabled").prop("disabled", false).val("");
                    $("#country_id").val("1000");
                    $("#address_label").focus();
                } else {
                    if ($(this).val() in addressArray) {
                        for (var i in addressArray[$(this).val()]) {
                            $("#" + i).val(addressArray[$(this).val()][i]);
                        }
                        $("#state_select").val(addressArray[$(this).val()]['state']);
                    }
                    if (empty($("#address_1").val()) || empty($("#city").val())) {
                        $("#shipping_address_wrapper").find("input").prop("readonly", false).val("");
                        $("#shipping_address_wrapper").find("select").removeClass("disabled").prop("disabled", false).val("");
                    } else {
                        $("#shipping_address_wrapper").find("input").prop("readonly", true);
                        $("#shipping_address_wrapper").find("select").addClass("disabled").prop("disabled", true);
                    }
                }
                $("#country_id").trigger("change");
            }).trigger("change");

            $("#address_id,#state,#state_select,#postal_code").change(function () {
                getShippingMethods();
            });

            $(document).on("click", ".remove-item", function () {
                var productId = $(this).data("product_id");
                var shoppingCartItemId = $(this).closest(".shopping-cart-item").data("shopping_cart_item_id");
                removeProductFromShoppingCart(productId, shoppingCartItemId);
                $(this).closest(".shopping-cart-item").remove();
                $("#addons_" + shoppingCartItemId).remove();
                $("#custom_fields_" + shoppingCartItemId).remove();
                calculateShoppingCartTotal();
            });

            $("#continue_checkout").click(function () {
                if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.out-of-stock").length > 0) {
                    displayErrorMessage("Out of stock items must be removed from the shopping cart");
                    return false;
                }
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
                $("#add_product").remove();
                $("#related_products_wrapper").remove();
                $(this).closest("p").remove();
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=checkout_started", function (returnArray) {
                    $("body").removeClass("no-waiting-for-ajax");
                });
                $("#_checkout_section_wrapper").removeClass("hidden");
                goToSection();
                sendAnalyticsEvent("checkout", {});
                return false;
            });
            $(".section-chooser-option").click(function () {
                var sectionId = $(this).data("section");
                goToSection(sectionId);
            });
            $(".next-section-button").click(function () {
                var sectionNumber = $(this).closest(".checkout-section").data("section_number");
                sectionNumber++;
                while ($("#section_chooser_" + sectionNumber).not(".unused-section").length == 0) {
                    sectionNumber++;
                    if (sectionNumber > 20) {
                        break;
                    }
                }
                if ($("#section_chooser_" + sectionNumber).length > 0) {
                    $("#section_chooser_" + sectionNumber).trigger("click");
                }
                return false;
            });
            $(".previous-section-button").click(function () {
                var sectionNumber = $(this).closest(".checkout-section").data("section_number");
                sectionNumber--;
                while ($("#section_chooser_" + sectionNumber).not(".unused-section").length == 0) {
                    sectionNumber--;
                    if (sectionNumber <= 0) {
                        break;
                    }
                }
                if ($("#section_chooser_" + sectionNumber).length > 0) {
                    $("#section_chooser_" + sectionNumber).trigger("click");
                }
                return false;
            });
            $(document).on("change", "#email_address", function () {
                if (!$(this).validationEngine("validate")) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=set_shopping_cart_contact&email_address=" + encodeURIComponent($(this).val()), function (returnArray) {
                        if (!empty($("#promotion_code").val())) {
                            $("#promotion_code").trigger("change");
                        }
                        if ("public_identifier" in returnArray) {
                            $("#public_identifier").val(returnArray['public_identifier']);
                        }
                    });
                }
            });
            $(document).on("change", "#payment_method_id", function (event) {
                if (!empty($(this).val()) && !empty($("#valid_payment_methods").val())) {
                    if (!isInArray($(this).val(), $("#valid_payment_methods").val())) {
                        $(this).val("");
                        displayErrorMessage("One or more items in the cart cannot be paid for with this payment method.");
                        return;
                    }
                }
                var paymentMethodCode = $(this).find("option:selected").data("payment_method_code");
                if (paymentMethodCode == "CREDOVA") {
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
                const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");

                $(".payment-description").addClass("hidden");
                if (!empty($(this).val()) && $("#payment_description_" + $(this).val()).length > 0) {
                    $("#payment_description_" + $(this).val()).removeClass("hidden");
                }
                $(".payment-method-logo").removeClass("selected");
                $("#payment_method_logo_" + $(this).val()).addClass("selected");
                $(".payment-method-fields").addClass("hidden");
                if (!empty($(this).val())) {
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).removeClass("hidden");
                    $("#add_payment_method").removeClass("hidden");
                } else {
                    $("#add_payment_method").addClass("hidden");
                }
                var addressRequired = $(this).find("option:selected").data("address_required");
                $("#same_address").prop("checked", true);
                $("#_billing_address").addClass("hidden");
                $("#_billing_address").find("input,select").val("");
                $("#billing_country_id").val("1000").trigger("change");

                if ($("#shipping_information_section").hasClass("not-required") || $("#shipping_information_section").hasClass("internal-pickup")) {
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
            $(document).on("click", "#same_address", function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                    $("#billing_country_id").val("1000").trigger("change");
                } else {
                    $("#_billing_address").removeClass("hidden");
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
                $("#billing_state").val($(this).val());
            });
            $(document).on("change", ".account-id", function () {
                if (!empty($(this).val())) {
                    $("#_new_account_" + paymentMethodNumber).addClass("hidden");
                } else {
                    $("#_new_account_" + paymentMethodNumber).removeClass("hidden");
                }
            });
            $("#add_payment_method").click(function () {
				<?php if (empty($onlyOnePayment)) { ?>
                addPaymentMethod();
				<?php } ?>
                return false;
            });
            $("#view_terms_conditions").click(function () {
                $('#_terms_conditions_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
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
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
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
            $(document).on("change", "#loan_number", function () {
                if (!empty($(this).val())) {
                    var thisElement = $(this);
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=check_loan&loan_number=" + encodeURIComponent($(this).val()), function (returnArray) {
                        if ("loan_information" in returnArray) {
                            thisElement.closest(".payment-method-fields").find(".loan-information").addClass("info-message").html(returnArray['loan_information']);
                            $("#payment_method_id").find("option:selected").data("maximum_payment_amount", returnArray['maximum_payment_amount']).data("maximum_payment_percentage", returnArray['maximum_payment_percentage']).trigger("change");
                        }
                        if ("loan_error" in returnArray) {
                            thisElement.closest(".payment-method-fields").find(".loan-information").addClass("error-message").html(returnArray['loan_error']);
                            thisElement.val("");
                        }
                    });
                }
            });
            $(document).on("change", "#gift_card_number", function () {
                var thisElement = $(this);
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=check_gift_card&gift_card_number=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("gift_card_information" in returnArray) {
                        thisElement.closest(".payment-method-fields").find(".gift-card-information").addClass("info-message").html(returnArray['gift_card_information']);
                        $("#payment_method_id").find("option:selected").data("maximum_payment_amount", returnArray['maximum_payment_amount']).trigger("change");
                    }
                    if ("gift_card_error" in returnArray) {
                        thisElement.closest(".payment-method-fields").find(".gift-card-information").addClass("error-message").html(returnArray['gift_card_error']);
                        thisElement.val("");
                    }
                });
            });
            $("#donation_amount").change(function () {
                $("#donation_amount").val($("#donation_amount").val().replace("$", ""));
                calculateOrderTotal();
            });
            $(".round-up-donation").click(function () {
                $("#donation_amount").val("");
                var orderTotal = calculateOrderTotal();
                orderTotal = orderTotal.replace(",", "").replace(",", "");
                var roundUpAmount = $(this).data("round_amount");
                var addAmount = $(this).data("add_amount");
                var donationAmount = "";
                if (empty(addAmount)) {
                    var newOrderTotal = Math.ceil(orderTotal / roundUpAmount) * roundUpAmount;
                    donationAmount = Round(newOrderTotal - orderTotal, 2);
                } else {
                    donationAmount = addAmount;
                }
                $("#donation_amount").val(donationAmount).trigger("change");
                return false;
            });
			<?php if ($GLOBALS['gInternalConnection']) { ?>
            $("#tax_exempt").click(function () {
                if (!$(this).prop("checked")) {
                    getTaxCharge();
                } else {
                    calculateOrderTotal();
                }
            });
			<?php } ?>
            $("#finalize_order").click(function () {
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
                if ($("#billing_country_id").val() == 1000) {
                    $("#billing_state").val($("#billing_state_select").val());
                }

                $(".formFieldError").removeClass("formFieldError");
                var totalPayment = 0;
                var orderTotal = parseFloat($("#order_total").val());
                var primaryPaymentMethodsFound = 0;
                $("#payment_methods_list").find(".payment-list-item").each(function () {
                    var maxAmount = $(this).find(".maximum-payment-amount").val();
                    var maxPercent = $(this).find(".maximum-payment-percentage").val();
                    if (!empty(maxPercent)) {
                        maxAmount = Round(parseFloat(orderTotal) * (parseFloat(maxPercent) / 100), 2);
                        $(this).find(".maximum-payment-amount").val(maxAmount);
                    }
                    if (empty(maxAmount)) {
                        $(this).find(".payment-amount-value").removeData("maximum-value");
                    } else {
                        $(this).find(".payment-amount-value").data("maximum-value", maxAmount);
                    }
                    var paymentAmount = $(this).find(".payment-amount-value").val();
                    if (empty(paymentAmount)) {
                        primaryPaymentMethodsFound++;
                        paymentAmount = orderTotal + 100;
                    } else if (empty(paymentAmount)) {
                        paymentAmount = maxAmount;
                    } else if (!empty(maxAmount)) {
                        if (parseFloat(maxAmount) < parseFloat(paymentAmount)) {
                            paymentAmount = Math.min(maxAmount, paymentAmount);
                            $(this).find("payment-amount-value").val(RoundFixed(paymentAmount, 2, true));
                        }
                    }
                    totalPayment = parseFloat(paymentAmount) + parseFloat(totalPayment);
                });
                /*
	- login_section
	- shipping_information_section
	- ffl_section
	- donation_section
	- payment_section
	- finalize_section
*/
                $("#_checkout_form").validationEngine("option", "validateHidden", true);
                if ($("#_checkout_form").validationEngine("validate")) {

					<?php if (!empty($forcePaymentMethodId)) { ?>
                    totalPayment = orderTotal;
					<?php } ?>
                    if (totalPayment < orderTotal) {
                        displayErrorMessage("Payment methods do not cover the full order amount. Add another payment method.");
                        $(".payment-section-chooser").trigger("click");
                        orderInProcess = false;
                        return false;
                    }
                    if (primaryPaymentMethodsFound == 0 && totalPayment > orderTotal) {
                        displayErrorMessage("Payment amounts exceed order total. Fix amounts or make one payment amount blank to cover remainder.");
                        $(".payment-section-chooser").trigger("click");
                        orderInProcess = false;
                        return false;
                    }
                    if (primaryPaymentMethodsFound > 1) {
                        displayErrorMessage("More than one payment method is set to cover the remainder.");
                        $(".payment-section-chooser").trigger("click");
                        orderInProcess = false;
                        return false;
                    }

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
                    if (!$("#shipping_information_section").hasClass("not-required") && !$("#shipping_information_section").hasClass("internal-pickup")) {
                        if (empty($("#address_1").val()) || empty($("#city").val())) {
                            $(".shipping-information-section-chooser").trigger("click");
                            displayErrorMessage("A valid shipping address is required");
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
                                    if(completed) {
                                        logEntry += "\n\nCredova checkout completed successfully.";
                                    } else {
                                        logEntry += "\n\nCredova checkout was closed by user without completing.";
                                    }
                                    logJavascriptError(logEntry, 0);
                                    if (completed) {
                                        credovaCheckoutCompleted = true;
                                        setTimeout(function () {
                                            $("#finalize_order").trigger("click");
                                        }, 100);
                                    } else {
                                        credovaCheckoutCompleted = false;
                                        orderInProcess = false;
                                    }
                                });
                            }
                        });
                        return false;
                    }

                    $("#finalize_order").addClass("hidden");
                    $("#processing_order").removeClass("hidden");
                    $("select.disabled").prop("disabled", false);
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_order&cart_total=" + $("#cart_total").val(), $("#_checkout_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
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
                            }, 1000);
                        } else {
                            $("#public_identifier").val("");
                            $("#authentication_token").val("");
                            credovaCheckoutCompleted = false;
                            if ("recalculate" in returnArray || "reload_cart" in returnArray) {
                                getShoppingCartItems();
                            }
                            $("#finalize_order").removeClass("hidden");
                            $("#processing_order").addClass("hidden");
                            orderInProcess = false;
                        }
                        getShoppingCartItemCount();
                    });
                } else {
                    var fieldNames = "";
                    $(".formFieldError").each(function () {
                        fieldNames += (empty(fieldNames) ? "" : ",") + $(this).attr("id");
                    });
                    displayErrorMessage("Required information is missing: " + fieldNames);
                    $("#_checkout_form").validationEngine("hideAll");
                    if ($(".formFieldError").length > 0 && $(".formFieldError").closest(".checkout-section").length > 0) {
                        var sectionChooserId = $(".formFieldError").closest(".checkout-section").attr("id").replace(/_/g, "-");
                        if (!empty(sectionChooserId)) {
                            setTimeout(function () {
                                sectionChooserId += "-chooser";
                                if ($("." + sectionChooserId).length > 0) {
                                    $("." + sectionChooserId).trigger("click");
                                    $("#_checkout_form").validationEngine("validate");
                                }
                            }, 200);
                        }
                    }
                }
                return false;
            });
			<?php
			$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
			if (!empty($fflRequiredProductTagId)) {
			?>
            $("#state,#state_select").change(function () {
                checkFFLRequirements();
            });
            $("#postal_code").change(function () {
                if (!empty($(this).val())) {
                    getFFLDealers();
                }
                if (empty($("#state").val()) && empty($("#city").val()) && $("#country_id").val() == 1000) {
                    loadAjaxRequest("validatepostalcode.php?ajax=true&postal_code=" + $("#postal_code").val(), function (returnArray) {
                        if ("cities" in returnArray && returnArray['cities'].length > 0) {
                            $("#city").val(returnArray['cities'][0]['city']);
                            $("#state").val(returnArray['cities'][0]['state']);
                            $("#state_select").val(returnArray['cities'][0]['state']);
                            $("#city").focus();
                            checkFFLRequirements();
                        }
                    });
                }
            });
            $(document).on("click", ".ffl-dealer", function () {
                if ($(this).hasClass("restricted")) {
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
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_information", { federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val() }, function (returnArray) {
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
            $(document).on("click", "#ffl_dealer_not_found", function () {
                var fflId = $(this).data("federal_firearms_licensee_id");
                $("#federal_firearms_licensee_id").val("");
                $("#selected_ffl_dealer").html("None selected yet");
            });
            $("#ffl_dealer_filter").keyup(function (event) {
                var textFilter = $(this).val().toLowerCase();
                if (textFilter == "") {
                    $("ul#ffl_dealers li").removeClass("hidden");
                } else {
                    $("ul#ffl_dealers li").each(function () {
                        var description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
			<?php
			}
			?>
            getShoppingCartItems();
			<?php
			if (!empty($_GET['promotion_code'])) {
			$promotionCode = getFieldFromId("promotion_code", "promotions", "promotion_code", $_GET['promotion_code'], "inactive = 0");
			if (!empty($promotionCode)) {
			?>
            setTimeout(function () {
                $("#promotion_code").val("<?= $promotionCode ?>").trigger("change");
            }, 1000);
			<?php } ?>
			<?php } ?>
            if (!empty($("#address_id").val())) {
                $("#address_id").trigger("change");
            }
        </script>
		<?php
	}

	function mainContent() {
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

		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$showListPrice = (getFieldFromId("price_calculation_type_id", "pricing_structures", "pricing_structure_code", "DEFAULT") == getFieldFromId("price_calculation_type_id", "price_calculation_types", "price_calculation_type_code", "DISCOUNT"));
		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
		$designations = array();
		$resultSet = executeQuery("select * from designations where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and designation_id in (select designation_id from designation_group_links where " .
			"designation_group_id = (select designation_group_id from designation_groups where designation_group_code = 'PRODUCT_ORDER' and inactive = 0 and client_id = ?)) order by sort_order,description",
			$GLOBALS['gClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$designations[] = $row;
		}

		if ($GLOBALS['gDevelopmentServer']) {
			echo "<H1>This is a test server. No credit cards will be charged.</H1>";
		}
		$fflNumber = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "FFL_NUMBER");
		if (!empty($fflNumber)) {
			echo "<input type='hidden' id='user_ffl_number' value='" . $fflNumber . "'>";
		}

		$sectionText = $this->getPageTextChunk("retail_store_shopping_cart_wrapper");
		if (empty($sectionText)) {
			$sectionText = $this->getFragment("retail_store_shopping_cart_wrapper");
		}
		if (empty($sectionText)) {
			ob_start();
			?>
            <table id="_shopping_cart_items">
                <thead>
                <tr>
                    <th></th>
                    <th>Description</th>
					<?php if ($showListPrice) { ?>
                        <th class="discount-column">List</th>
                        <th class="discount-column">Discount</th>
					<?php } ?>
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
			<?php
			$sectionText = ob_get_clean();
		}

		?>
        <div id="_shopping_cart_wrapper">

            <form id="_checkout_form" enctype="multipart/form-data" method='post'>

                <div id="shopping_cart_contents">
                    <h1>Shopping Cart</h1>
					<?= $this->iPageData['content'] ?>

					<?= $sectionText ?>

					<?php
					$sectionText = $this->getPageTextChunk("retail_store_shopping_cart_under_items");
					if (empty($sectionText)) {
						$sectionText = $this->getFragment("retail_store_shopping_cart_under_items");
					}
					echo $sectionText;
					?>

                    <div id="totals_wrapper">
                        <div><input type="text" tabindex="10" id="add_product" placeholder="Add Product Code or UPC">
                        </div>
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
                            <div><input type="text" tabindex="10" id="promotion_code" placeholder="Promo Code"></div>
						<?php } ?>
						<?php if ($showListPrice) { ?>
                            <div id="total_savings" class="align-right">Total Savings: <span
                                        class="dollar">$</span><span class="cart-savings"></span></div>
						<?php } ?>
                        <div class="align-right">Cart Subtotal: <span class="dollar">$</span><span
                                    class="cart-total"></span></div>
                    </div> <!-- totals_wrapper -->

					<?php
					$credovaCredentials = getCredovaCredentials();
					$credovaUserName = $credovaCredentials['username'];
					$credovaPassword = $credovaCredentials['password'];
					$credovaTest = $credovaCredentials['test_environment'];
					$credovaPaymentMethodId = $credovaCredentials['credova_payment_method_id'];
					if (!empty($credovaUserName)) {
						?>
                        <p class="<?php echo($GLOBALS['gLoggedIn'] ? "" : "create-account ") ?>credova-button checkout-credova-button"
                           data-type="popup"></p>
						<?php
					}
					?>

                    <div id="promotion_code_details"></div>

                    <p class='error-message'></p>
                    <p id="continue_checkout_wrapper">
                        <button tabindex="10" id="continue_checkout" class="hidden">Checkout</button>
                    </p>

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

                    <div id="related_products_wrapper" class="hidden">
                        <h2>Related Products</h2>
                        <div id="related_products">
                        </div>
                    </div>

                </div> <!-- shopping_cart_contents -->

                <input type="hidden" id="valid_payment_methods" value="">
                <input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">
				<?php
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
					$contactId = $shoppingCart->getContact();
				}
				$publicIdentifier = getFieldFromId("public_identifier", "credova_loans", "contact_id", $contactId, "order_id is null");
				if (empty($publicIdentifier)) {
					$publicIdentifier = $_COOKIE['credova_public_identifier'];
				}
				?>
                <input type="hidden" id="public_identifier" name="public_identifier" value="<?= htmlText($publicIdentifier) ?>">
                <input type="hidden" id="authentication_token" name="authentication_token" value="">

				<?php if ($GLOBALS['gUserRow']['administrator_flag']) { ?>
                    <h2 class='red-text'>As an administrator, MAKE SURE this purchase is for yourself. Do not make purchases for others with your account.</h2>
				<?php } ?>
                <div id="_checkout_section_wrapper" class="hidden">

                    <div id="_section_chooser">
						<?php
						$hideLoginSection = getPreference("RETAIL_STORE_HIDE_LOGIN_SECTION");
						if (!$GLOBALS['gLoggedIn'] && !$hideLoginSection) {
							?>
                            <div class="section-chooser-option login-section-chooser" data-section="login_section"
                                 data-section_number="">Login
                            </div>
						<?php } ?>
                        <div class="section-chooser-option shipping-section shipping-information-section-chooser"
                             data-section="shipping_information_section" data-section_number="">Shipping
                        </div>
						<?php if (!empty($fflRequiredProductTagId)) { ?>
                            <div class="section-chooser-option ffl-section ffl-section-chooser"
                                 data-section="ffl_section" data-section_number="">FFL
                            </div>
						<?php } ?>
						<?php if (!empty($designations)) { ?>
                            <div class="section-chooser-option donation-section-chooser" data-section="donation_section"
                                 data-section_number="">Donation
                            </div>
						<?php } ?>
                        <div class="section-chooser-option payment-section-chooser" data-section="payment_section"
                             data-section_number="">Payment
                        </div>
                        <div class="section-chooser-option finalize-section-chooser" data-section="finalize_section"
                             data-section_number="">Finalize
                        </div>
                    </div> <!-- section_chooser -->

					<?php

					/*
	Checkout Sections

	- login_section
	- shipping_information_section
	- ffl_section
	- donation_section
	- payment_section
	- finalize_section
*/

					# Login message

					if (!$GLOBALS['gLoggedIn'] && !$hideLoginSection) {
						?>
                        <div id="login_section" class="hidden form-section-wrapper checkout-section">
							<?php
							if ($shoppingCart->requiresUser()) {
								$sectionText = $this->getPageTextChunk("retail_store_no_guest_checkout");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_no_guest_checkout");
								}
								if (empty($sectionText)) {
									ob_start();
									?>
                                    <p class='red-text'>The contents of this shopping cart requires a user login. Please
                                        create an account <a href='/my-account'>here</a> or log in <a
                                                href='/login'>here</a> to order these products.</p>
									<?php
									$sectionText = ob_get_clean();
								}
							} else {
								$sectionText = $this->getPageTextChunk("retail_store_login_text");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_login_text");
								}
								if (empty($sectionText)) {
									ob_start();
									?>
                                    <p>Creating an account will allow you to track most orders, save payment methods and
                                        shipping addresses, and gain access to special user discounts.
                                        If you already have an account, login below. If not, we need your email address
                                        so we can send you an order confirmation and tracking details when it ships.
                                        If you wish to create an account, add a password.</p>
									<?php
									$sectionText = ob_get_clean();
								}
							}
							echo $sectionText;
							if (!$shoppingCart->requiresUser()) {
								?>
                                <div id="not_logged_in_options">

                                    <div id="login_now">
                                        <h3>Login Now</h3>
                                        <p id="login_now_intro">If you already have an account, enter your user name &
                                            password and click Login.</p>
                                        <p><input tabindex="10" type='text' id='login_user_name' name='login_user_name'
                                                  size='25' maxlength='40' class='lowercase code-value allow-dash'
                                                  placeholder="Username" value="<?= htmlText($loginUserName) ?>"/></p>
                                        <p><input tabindex="10" type='password' id='login_password'
                                                  name='login_password' size='25' maxlength='60'
                                                  placeholder="Password"/></p>
                                        <p>
                                            <button tabindex="10" id="login_now_button">Login</button>
                                        </p>
                                        <p><a id='access_link' href="/login?forgot_password=true">Forgot your Username
                                                or Password?</a></p>
                                        <p id="logging_in_message"></p>
                                    </div> <!-- login_now -->

                                    <div id="guest_checkout">
                                        <h3>Guest Checkout</h3>
                                        <p id="guest_checkout_intro">Email is required if you are checking out as a
                                            guest OR creating an account.</p>
										<?php
										echo createFormLineControl("contacts", "email_address", array("not_null" => !$GLOBALS['gInternalConnection']));
										?>
                                    </div>

                                    <div id="user_information">
                                        <h3>Optional: Create User Account</h3>
                                        <p id="user_information_intro">If you are checking out as a guest, you can
                                            create an account for future use.</p>
										<?php
										echo createFormLineControl("users", "user_name", array("not_null" => false, "help_label" => "Leave blank to use email address"));
										?>
                                        <div class="form-line" id="password_row">
                                            <label for="password">Password</label>
											<?php
											$minimumPasswordLength = getPreference("minimum_password_length");
											if (empty($minimumPasswordLength)) {
												$minimumPasswordLength = 10;
											}
											?>
                                            <input tabindex="10" autocomplete="chrome-off" autocomplete="off"
                                                   class="password-strength validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]]"
                                                   type="password" size="40" maxlength="40" id="password"
                                                   name="password" value=""><span
                                                    class='fad fa-eye show-password'></span>
                                            <div class='strength-bar-div hidden' id='password_strength_bar_div'>
                                                <p class='strength-bar-label' id='password_strength_bar_label'></p>
                                                <div class='strength-bar' id='password_strength_bar'></div>
                                            </div>
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_password_again_row">
                                            <label for="password_again">Re-enter Password</label>
                                            <input tabindex="10" autocomplete="chrome-off" autocomplete="off"
                                                   class="validate[equals[password],minSize[<?= $minimumPasswordLength ?>]]"
                                                   type="password" size="40" maxlength="40" id="password_again"
                                                   name="password_again" value=""><span
                                                    class='fad fa-eye show-password'></span>
                                            <div class='clear-div'></div>
                                        </div>

                                    </div> <!-- user_information -->

                                </div> <!-- not_logged_in_options -->

							<?php } ?>

                            <p class='error-message'></p>
                            <div class="checkout-button-section">
                                <button tabindex="10" class='next-section-button'>Next</button>
                            </div> <!-- checkout-button-section -->

                        </div> <!-- login_section -->
						<?php
					}

					# Shipping Address

					?>
                    <div id="shipping_information_section"
                         class="shipping-section hidden form-section-wrapper checkout-section">
                        <h2>Your Ship To Address</h2>
						<?php
						$shippingAddressText = getFragment("shipping_address_text");
						if (empty($shippingAddressText)) {
							$shippingAddressText = "Some of the items you ordered require additional paperwork as per state law. Select your preferred receiving dealer where we will ship those items.";
						}
						?>
                        <p class='ffl-section'><?= $shippingAddressText ?></p>
						<?php
						$sectionText = $this->getPageTextChunk("retail_store_shipping_address");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_shipping_address");
						}
						echo makeHtml($sectionText);
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
						?>
                        <div id="shipping_address_block">
                            <div id="shipping_address_contact">
								<?= createFormLineControl("contacts", "first_name", array("not_null" => true, "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\") && empty($(\"#business_name\").val())", "initial_value" => $GLOBALS['gUserRow']['first_name'], "help_label" => "Who is this being shipped to?")) ?>
								<?= createFormLineControl("contacts", "last_name", array("not_null" => true, "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\") && empty($(\"#business_name\").val())", "initial_value" => $GLOBALS['gUserRow']['last_name'])) ?>
								<?php if (!$GLOBALS['gLoggedIn'] && $hideLoginSection) { ?>
									<?= createFormLineControl("contacts", "email_address", array("not_null" => true)) ?>
								<?php } ?>
								<?= createFormLineControl("contacts", "business_name", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['business_name'])) ?>
								<?= createFormLineControl("orders", "purchase_order_number", array("not_null" => false, "form_label" => "PO Number")) ?>
								<?php
								$phoneRequired = getPreference("retail_store_phone_required");
								$phoneControls = array("not_null" => true, "form_label" => "Primary Phone Number", "initial_value" => $phoneNumber);
								if (empty($phoneRequired) || $GLOBALS['gInternalConnection']) {
									$phoneControls['no_required_label'] = true;
									$phoneControls['data-conditional-required'] = "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\") && $(\"#ffl_section\").length > 0 && $(\"#ffl_section\").hasClass(\"ffl-required\")";
								} else {
									$phoneControls['data-conditional-required'] = "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\")";
								}
								?>
								<?= createFormLineControl("phone_numbers", "phone_number", $phoneControls) ?>
								<?= createFormLineControl("phone_numbers", "phone_number", array("column_name" => "cell_phone_number", "form_label" => "Cell Phone Number", "help_label" => "To receive text notifications", "initial_value" => $cellPhoneNumber, "not_null" => false)) ?>
								<?php

								$sectionText = $this->getPageTextChunk("retail_store_shipping_method");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_shipping_method");
								}
								echo makeHtml($sectionText);

								$resultSet = executeQuery("select * from shipping_methods where pickup = 1 and location_id is not null and inactive = 0 and internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
								$pickupLocationCount = $resultSet['row_count'];
								if ($pickupLocationCount < 3) {
									?>
                                    <div class="form-line" id="_shipping_method_id_row">
                                        <label for="shipping_method_id" class="">Shipping Method</label>
                                        <select tabindex="10" id="shipping_method_id" name="shipping_method_id"
                                                class='validate[required]'
                                                data-conditional-required='!$("#shipping_information_section").hasClass("not-required") && !$("#shipping_information_section").hasClass("internal-pickup")'>
                                            <option value="">[Select]</option>
                                        </select>
                                        <div class='clear-div'></div>
                                    </div>
								<?php } else { ?>
									<?php
									$locationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
									$locationId = getFieldFromId("location_id", "locations", "location_id", $locationId, "inactive = 0 and internal_use_only = 0 and location_id in (select location_id from shipping_methods where inactive = 0 and internal_use_only = 0 and pickup = 1)");
									if (!empty($locationId)) {
										$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
										$shoppingCartItems = $shoppingCart->getShoppingCartItems();

										$productCatalog = new ProductCatalog();
										$inventoryFound = true;
										foreach ($shoppingCartItems as $thisItem) {
											$inventoryCounts = $productCatalog->getLocationAvailability($thisItem['product_id']);

											$inventoryCounts = $inventoryCounts[$thisItem['product_id']];
											if (!is_array($inventoryCounts)) {
												$inventoryCounts = array("distributor" => 0);
											}
											if ($inventoryCounts['distributor'] > 0) {
												continue;
											}
											if (!array_key_exists($locationId, $inventoryCounts) || $inventoryCounts[$locationId] <= 0) {
												$inventoryFound = false;
											}
										}
										if (!$inventoryFound) {
											$locationId = false;
										}
									}
									$pickupShippingMethodId = getFieldFromId("shipping_method_id", "shipping_methods", "location_id", $locationId, "inactive = 0 and internal_use_only = 0 and pickup = 1");
									$locationDescription = getFieldFromId("description", "locations", "location_id", $locationId);
									if (empty($locationDescription)) {
										$locationDescription = "None Selected";
									}
									?>
                                    <div id="shipping_type_wrapper">
                                        <div class='shipping-type-wrapper' id="_shipping_type_pickup">
                                            <div class='form-line'>
                                                <input type="radio" checked name="shipping_type"
                                                       id="shipping_type_pickup" value="1"><input type='hidden'
                                                                                                  id='pickup_shipping_method_id'
                                                                                                  value='<?= $pickupShippingMethodId ?>'><label
                                                        for="shipping_type_pickup" class="checkbox-label">Pickup at
                                                    <span id='pickup_display'><?= $locationDescription ?></span> <a
                                                            href='#' id="change_pickup_location">change</a></label>
                                            </div>
                                        </div>
                                        <div class='shipping-type-wrapper' id="_shipping_type_ship">
                                            <div class="form-line">
                                                <input type='radio' name='shipping_type' id='shipping_type_ship'
                                                       value='2'><label for="shipping_type_ship" class="checkbox-label">Delivery</label>
                                            </div>
                                            <div class="form-line pickup-type-selected" id="_shipping_method_id_row">
                                                <label for="shipping_method_id" class="">Shipping Method</label>
                                                <select tabindex="10" id="shipping_method_id" name="shipping_method_id"
                                                        class='validate[required]'
                                                        data-conditional-required='!$("#shipping_information_section").hasClass("not-required") && !$("#shipping_information_section").hasClass("internal-pickup")'>
                                                    <option value="">[Select]</option>
                                                </select>
                                                <div class='clear-div'></div>
                                            </div>
                                        </div>
                                    </div>
								<?php } ?>

								<?php if ($GLOBALS['gUserRow']['full_client_access']) { ?>
                                    <div class='form-line admin-logged-in' id="_show_shipping_calculation_log_row">
                                        <button id="show_shipping_calculation_log">Show Shipping Calculation</button>
                                    </div>
								<?php } ?>

                            </div> <!-- shipping_address_contact -->
                            <input type="hidden" id="update_primary_address" name="update_primary_address">
                            <div id="shipping_section_wrapper">
								<?php
								if ($GLOBALS['gLoggedIn']) {
									$requireExternalAddressIdentifier = getUserTypePreference("CHECKOUT_REQUIRE_EXTERNAL_ADDRESS");
									$noNewAddress = getUserTypePreference("CHECKOUT_EXISTING_ADDRESSES_ONLY");
									?>
                                    <div class="form-line" id="_address_id_row">
                                        <label id="address_id_label">Choose A Shipping Address</label>
                                        <select id="address_id" name="address_id" class="validate[required]"
                                                data-conditional-required="!$('#shipping_information_section').hasClass('not-required') && !$('#shipping_information_section').hasClass('internal-pickup')">
											<?php
											$resultSet = executeQuery("select * from addresses where contact_id = ? and inactive = 0 and address_label is not null and address_1 is not null and city is not null" .
												(empty($requireExternalAddressIdentifier) ? "" : " and external_identifier is not null"), $GLOBALS['gUserRow']['contact_id']);
											?>
											<?php if (empty($requireExternalAddressIdentifier) || empty($noNewAddress) || $resultSet['row_count'] != 1) { ?>
                                                <option value="">[Select Shipping Address]</option>
											<?php } ?>
											<?php if (empty($requireExternalAddressIdentifier) && !empty($GLOBALS['gUserRow']['address_1']) && !empty($GLOBALS['gUserRow']['city'])) { ?>
                                                <option value="0">[Use Primary Address]</option>
											<?php } ?>
											<?php if (empty($noNewAddress)) { ?>
                                                <option value="-1">[New Address]</option>
											<?php } ?>
											<?php
											while ($row = getNextRow($resultSet)) {
												?>
                                                <option value="<?= $row['address_id'] ?>"><?= htmlText($row['address_label'] . (empty($row['postal_code']) ? "" : " - " . $row['postal_code'])) ?></option>
												<?php
											}
											?>
                                        </select>
                                    </div>
									<?php
								}
								?>

                                <div class="form-line" id="_business_address_row">
                                    <label class=""></label>
                                    <input tabindex="10" type="checkbox" id="business_address" name="business_address"
                                           value="1"><label class="checkbox-label" for="business_address">This is a
                                        business address</label>
                                    <div class='clear-div'></div>
                                </div>

                                <div id="shipping_address_wrapper" <?= ($GLOBALS['gLoggedIn'] ? 'class="hidden"' : "") ?>>
									<?php if ($GLOBALS['gLoggedIn']) { ?>
										<?= createFormLineControl("addresses", "address_label", array("not_null" => false, "help_label" => "add a label to use the address again later")) ?>
									<?php } ?>
									<?= createFormLineControl("addresses", "address_1", array("not_null" => true, "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\")", "classes" => "shipping-address autocomplete-address")) ?>
									<?= createFormLineControl("addresses", "address_2", array("not_null" => false)) ?>
									<?= createFormLineControl("addresses", "city", array("not_null" => true, "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\")", "classes" => "shipping-address")) ?>

                                    <div class="form-line" id="_state_select_row">
                                        <label for="state_select" class="">State</label>
                                        <select tabindex="10" id="state_select" name="state_select"
                                                class='shipping-address validate[required]'
                                                data-conditional-required="$('#country_id').val() == 1000 && !$('#shipping_information_section').hasClass('not-required') && !$('#shipping_information_section').hasClass('internal-pickup')">
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

									<?= createFormLineControl("addresses", "state", array("not_null" => true, "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\")")) ?>
									<?= createFormLineControl("addresses", "postal_code", array("not_null" => true, "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\")")) ?>
									<?= createFormLineControl("addresses", "country_id", array("not_null" => true, "initial_value" => "1000", "data-conditional-required" => "!$(\"#shipping_information_section\").hasClass(\"not-required\") && !$(\"#shipping_information_section\").hasClass(\"internal-pickup\")", "classes" => "shipping-address")) ?>
                                </div> <!-- shipping_address_wrapper -->
                            </div>
                        </div> <!-- shipping_address_block -->

                        <p id="calculating_shipping_methods" class="hidden">Calculating...</p>

                        <p class='error-message'></p>
                        <div class="checkout-button-section">
							<?php if (!$GLOBALS['gLoggedIn']) { ?>
                                <button tabindex="10" class='previous-section-button'>Previous</button>
							<?php } ?>
                            <button tabindex="10" class='next-section-button'>Next</button>
                        </div> <!-- checkout-button-section -->

                    </div> <!-- shipping_information_section -->
					<?php

					# FFL

					if (!empty($fflRequiredProductTagId)) {
						$fflChoiceElement = ProductCatalog::getFFLChoiceElement();
						$displayName = "";
						if ($GLOBALS['gLoggedIn']) {
							$federalFirearmsLicenseeId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER");
							if (!empty($federalFirearmsLicenseeId)) {
								$fflRow = array();
								$fflRow['distance'] = "";
								$fflRow = (new FFL(array("federal_firearms_licensee_id"=>$federalFirearmsLicenseeId,"only_if_valid"=>true)))->getFFLRow();
								if ($fflRow) {
									$fflRow['expiration_date_notice'] = "";
									if (!empty($fflRow['expiration_date']) && $fflRow['expiration_date'] < date("Y-m-d", strtotime("+30 days"))) {
										$fflRow['expiration_date_notice'] = "<p class='" . ($fflRow['expiration_date'] < date("Y-m-d", strtotime("+30 days")) ? "red-text" : "") . "'>" .
											date("m/d/Y", strtotime($fflRow['expiration_date'])) . "</p>";
									}
									$fflRow['distance'] = "";
								}
								if (empty($fflRow)) {
									CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER", "");
								} else {
									$displayName = $fflChoiceElement;
									foreach ($fflRow as $fieldName => $fieldData) {
										$displayName = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $displayName);
									}
								}
							}
							if (empty($displayName) && !empty($federalFirearmsLicenseeId)) {
								$federalFirearmsLicenseeId = "";
								CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER", "");
							}
						} else {
							$federalFirearmsLicenseeId = "";
						}
						?>
                        <div id="ffl_section" class="hidden ffl-section form-section-wrapper checkout-section">
                            <div id="ffl_selection_wrapper" data-product_tag_id="<?= $fflRequiredProductTagId ?>">
                                <h2><?= getLanguageText("FFL Selection (Required)") ?></h2>
								<?php
								$sectionText = $this->getPageTextChunk("retail_store_ffl_requirement");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_ffl_requirement");
								}
								if (empty($sectionText)) {
									$sectionText = "<p>FFL selection is based on your shipping address. If you haven't yet entered your shipping address, do so now.</p>";
								}
								echo makeHtml($sectionText);
								$customerOrderNote = getPreference("CUSTOMER_ORDER_NOTE");
								$forceFFLDealerRequired = getUserTypePreference("FORCE_FFL_DEALER_REQUIRED");

								?>
                                <input type="hidden" id="federal_firearms_licensee_id"
                                       name="federal_firearms_licensee_id" class=""
                                       value="<?= $federalFirearmsLicenseeId ?>" data-conditional-required="!$('#ffl_dealer_not_found').prop('checked')">
                                <p><?= getLanguageText("Selected Dealer") ?>:
                                    <span id="selected_ffl_dealer"><?= (empty($displayName) ? "None selected yet" : $displayName) ?></span>
                                </p>
								<?php if (!$forceFFLDealerRequired) { ?>
                                    <p id="ffl_dealer_not_found_wrapper"><input type='checkbox' class=""
                                                                                id="ffl_dealer_not_found"
                                                                                name="ffl_dealer_not_found"
                                                                                value="1"><label class="checkbox-label"
                                                                                                 for="ffl_dealer_not_found"><?= getLanguageText("I can't find my dealer") . (empty($customerOrderNote) ? "" : " (Add your dealer details on Finalize tab)") ?></label>
                                    </p>
								<?php } ?>
                                <p id="ffl_dealer_count_paragraph"><span
                                            id="ffl_dealer_count"></span> <?= getLanguageText("Dealers found within") ?>
                                    <select id="ffl_radius">
                                        <option value="25" selected>25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="200">200</option>
                                    </select> <?= getLanguageText("miles. Choose one below.") ?></p>
                                <input type="text" placeholder="<?= getLanguageText("Search/Filter Dealers") ?>"
                                       id="ffl_dealer_filter">
                                <div id="ffl_dealers_wrapper">
                                    <ul id="ffl_dealers">
                                        <li>Enter your shipping address to see a list of available FFL dealers</li>
                                    </ul>
                                </div> <!-- ffl_dealers_wrapper -->
								<?php
								$sectionText = $this->getPageTextChunk("retail_store_restricted_dealers");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_restricted_dealers");
								}
								if (empty($sectionText)) {
									$sectionText = "Dealers highlighted in red cannot handle the items in your cart.";
								}
								echo "<div id='restricted_dealers' class='hidden'>" . $sectionText . "</div>";
								?>
                            </div> <!-- ffl_selection_wrapper -->

                            <p class='error-message'></p>
                            <div class="checkout-button-section">
                                <button tabindex="10" class='previous-section-button'>Previous</button>
                                <button tabindex="10" class='next-section-button'>Next</button>
                            </div> <!-- checkout-button-section -->

                        </div> <!-- ffl_section -->
						<?php
					}

					# Additional Donation

					if (!empty($designations)) {
						?>
                        <div id="donation_section"
                             class="hidden form-section-wrapper checkout-section no-action-required"
                             data-payment_method_count="0">
                            <h2>Donation</h2>
							<?php
							$sectionText = $this->getPageTextChunk("retail_store_donation");
							if (empty($sectionText)) {
								$sectionText = $this->getFragment("retail_store_donation");
							}
							if (!empty($sectionText)) {
								echo makeHtml($sectionText);
							}
							if (count($designations) > 1) {
								?>
                                <div class="form-line" id="_designation_id_row">
                                    <label for="designation_id" class="">Designation</label>
                                    <select tabindex="10" id="designation_id" name="designation_id">
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
							} else {
								?>
                                <input type="hidden" id="designation_id" name="designation_id"
                                       value="<?= $designations[0]['designation_id'] ?>">
								<?php
							}
							?>
                            <div class="form-line" id="_donation_amount_row">
                                <label for="donation_amount"
                                       class="">Donation <?= (count($designations) > 1 ? "Amount" : " for " . htmlText($designations[0]['description'])) ?>
                                    (US $)</label>
                                <input tabindex="10" type="text" id="donation_amount" name="donation_amount"
                                       class="align-right validate[custom[number]]" data-decimal-places="2">
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
                                        <button tabindex="10" class="round-up-donation" data-round_amount="1"
                                                id="round_up_1">Round up $1
                                        </button>
                                        <button tabindex="10" class="round-up-donation" data-round_amount="5"
                                                id="round_up_5">Round up $5
                                        </button>
                                        <button tabindex="10" class="round-up-donation" data-round_amount="10"
                                                id="round_up_10">Round up $10
                                        </button>
                                    </p>
									<?php
									$sectionText = ob_get_clean();
								}
								echo makeHtml($sectionText);
								?>
                            </div> <!-- round_up_buttons -->

                            <p class='error-message'></p>
                            <div class="checkout-button-section">
                                <button tabindex="10" class='previous-section-button'>Previous</button>
                                <button tabindex="10" class='next-section-button'>Next</button>
                            </div> <!-- checkout-button-section -->

                        </div> <!-- donation_section -->
						<?php
					}

					# Payment Methods

					?>
                    <div id="payment_section" class="hidden form-section-wrapper checkout-section">
                        <h2>Payment</h2>
                        <p class="hidden" id="_no_payment_required">No Payment Required<br><span
                                    id='no_payment_details'></span></p>
                        <div id="payment_information_wrapper">
							<?php
							$sectionText = $this->getPageTextChunk("retail_store_payment_methods");
							if (empty($sectionText)) {
								$sectionText = $this->getFragment("retail_store_payment_methods");
							}
							if (empty($sectionText)) {
								$sectionText = "<p>If you want to add more than one payment method, one can have a blank amount, so as to cover the remaining balance.</p>";
							}
							echo makeHtml($sectionText);
							?>
                            <table id="payment_methods_list" <?= (empty($onlyOnePayment) ? "" : " class='hidden'") ?>>
                            </table>
                            <p id='_order_total_exceeded' class='hidden'>Payment Exceeds Order Total</p>
                            <p id='_balance_remaining_wrapper'>Balance Remaining: $<span id="_balance_remaining"></span>
                            </p>
							<?php if (empty($onlyOnePayment)) { ?>
                                <p>
                                    <button tabindex="10" id="add_payment_method" class="hidden">Add Another Payment
                                        Method
                                    </button>
                                </p>
							<?php } ?>
                            <input type="hidden" id="new_payment" value="1">
                            <input type="hidden" id="payment_method_number" value="1">
                            <input type="hidden" id="next_payment_method_number" name="next_payment_method_number"
                                   value="2">
                            <div id="payment_method_wrapper">
                                <div id="payment_information">

									<?php
									$validAccounts = array();
									$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and " .
										"(merchant_account_id is null or merchant_account_id in (select merchant_account_id from merchant_accounts where client_id = ?))", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
									while ($row = getNextRow($resultSet)) {
										$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']));
										if (empty($row['account_token']) && !empty($paymentMethodTypeCode) && in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT"))) {
											continue;
										}
										$merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
										if ($merchantAccountId == $GLOBALS['gMerchantAccountId'] || empty($row['account_token'])) {
											$validAccounts[] = $row;
										}
									}
									if (count($validAccounts) == 0 || empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
										?>
                                        <input type="hidden" id="account_id" name="account_id" value="">
										<?php
									} else {
										?>
                                        <div class="form-line" id="_account_id_row">
                                            <label for="account_id" class="">Select Saved Payment Method</label>
                                            <select tabindex="10" id="account_id" name="account_id" class="">
                                                <option value="">[New Account]</option>
												<?php
												foreach ($validAccounts as $row) {
													?>
                                                    <option value="<?= $row['account_id'] ?>"
                                                            data-payment_method_type_code="<?= $paymentMethodTypeCode ?>"
                                                            data-payment_method_id="<?= $row['payment_method_id'] ?>"><?= htmlText((empty($row['account_label']) ? getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . "-" . substr($row['account_number'], -4) : $row['account_label'])) ?></option>
													<?php
												}
												?>
                                            </select>
                                            <div class='clear-div'></div>
                                        </div>
									<?php } ?>
									<?php
									$paymentLogos = array();
									$paymentDescriptions = "";
									$paymentMethodArray = array();
									$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
										"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
										($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
										"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
										(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
										"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
										"(select payment_method_type_id from payment_method_types where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
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
										if (empty($row['image_id'])) {
											$paymentMethodRow = getRowFromId("payment_methods", "payment_method_code", $row['payment_method_code'], "client_id = ?", $GLOBALS['gDefaultClientId']);
											$row['image_id'] = $paymentMethodRow['image_id'];
										}
										if (!empty($row['image_id'])) {
											$paymentLogos[$row['payment_method_id']] = $row['image_id'];
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
                                        <div class="form-line new-account" id="_payment_method_id_row">
                                            <label for="payment_method_id" class="">Payment Method</label>
                                            <select tabindex="10" class="validate[required]"
                                                    data-conditional-required="$('#order_total').val() > 0 && ($('#account_id').length == 0 || $('#account_id').val() == '')"
                                                    id="payment_method_id" name="payment_method_id">
												<?php if (count($paymentMethodArray) != 1) { ?>
                                                    <option value="">[Select]</option>
												<?php } ?>
												<?php
												foreach ($paymentMethodArray as $row) {
													?>
                                                    <option value="<?= $row['payment_method_id'] ?>"
                                                            data-payment_method_code="<?= $row['payment_method_code'] ?>"
                                                            data-maximum_payment_percentage="<?= $row['percentage'] ?>"
                                                            data-maximum_payment_amount="<?= $row['maximum_payment_amount'] ?>"
                                                            data-flat_rate="<?= $row['flat_rate'] ?>"
                                                            data-fee_percent="<?= $row['fee_percent'] ?>"
                                                            data-address_required="<?= (empty($row['no_address_required']) ? "1" : "") ?>"
                                                            data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
													<?php
												}
												?>
                                            </select>
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="payment-method-fields hidden" id="payment_method_credit_card">
                                            <div class="form-line" id="_account_number_row">
                                                <label for="account_number" class="">Card Number</label>
                                                <input tabindex="10" type="text" class="validate[required]"
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_credit_card').hasClass('hidden')"
                                                       size="20" maxlength="20" id="account_number"
                                                       name="account_number" placeholder="Account Number" value="">
                                                <div id="payment_logos">
													<?php
													foreach ($paymentLogos as $paymentMethodId => $imageId) {
														?>
                                                        <img alt='Payment Method Logo'
                                                             id="payment_method_logo_<?= strtolower($paymentMethodId) ?>"
                                                             class="payment-method-logo"
                                                             src="<?= getImageFilename($imageId, array("use_cdn" => true)) ?>">
														<?php
													}
													?>
                                                </div>
                                                <div class='clear-div'></div>
                                            </div>

                                            <div class="form-line" id="_expiration_month_row">
                                                <label for="expiration_month" class="">Expiration Date</label>
                                                <select tabindex="10" class="expiration-date validate[required]"
                                                        data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_credit_card').hasClass('hidden')"
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
                                                        data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_credit_card').hasClass('hidden')"
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
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_credit_card').hasClass('hidden')"
                                                       size="5" maxlength="4" id="cvv_code" name="cvv_code"
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
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_bank_account').hasClass('hidden')"
                                                       size="20" maxlength="20" id="routing_number"
                                                       name="routing_number" placeholder="Routing Number" value="">
                                                <div class='clear-div'></div>
                                            </div>

                                            <div class="form-line" id="_bank_account_number_row">
                                                <label for="bank_account_number" class="">Account Number</label>
                                                <input tabindex="10" type="text" class="validate[required]"
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_bank_account').hasClass('hidden')"
                                                       size="20" maxlength="20" id="bank_account_number"
                                                       name="bank_account_number" placeholder="Bank Account Number"
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
                                                <input tabindex="10" type="text" class="validate[custom[date]]"
                                                       size="12" maxlength="12" id="payment_time" name="payment_time"
                                                       placeholder="Check Date" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                        </div> <!-- payment_method_check -->

                                        <div class="payment-method-fields hidden" id="payment_method_gift_card">
                                            <div class="form-line" id="_gift_card_number_row">
                                                <label for="gift_card_number" class="">Card Number</label>
                                                <input tabindex="10" type="text" class="validate[required]"
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_gift_card').hasClass('hidden')"
                                                       size="20" maxlength="30" id="gift_card_number"
                                                       name="gift_card_number" placeholder="Card Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                            <p class="gift-card-information"></p>
                                        </div> <!-- payment_method_gift_card -->

                                        <div class="payment-method-fields hidden" id="payment_method_loan">
                                            <div class="form-line" id="_loan_row">
                                                <label for="loan_number" class="">Loan Number</label>
                                                <input tabindex="10" type="text" class="validate[required] uppercase"
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_loan').hasClass('hidden')"
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
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_lease').hasClass('hidden')"
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
                                        <input tabindex="10"
                                               type="checkbox" <?= (empty($forceSameAddress) ? "" : "disabled='disabled'") ?>
                                               id="same_address" name="same_address" checked="checked" value="1"><label
                                                id="same_address_label" class="checkbox-label" for="same_address">Billing
                                            address is same as shipping</label>
                                        <div class='clear-div'></div>
                                    </div>

                                    <div id="_billing_address" class="hidden">

                                        <div class="form-line" id="_billing_first_name_row">
                                            <label for="billing_first_name">First Name</label>
                                            <input tabindex="10" type="text"
                                                   class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>"
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked')"
                                                   size="25" maxlength="25" id="billing_first_name"
                                                   name="billing_first_name" placeholder="First Name"
                                                   value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_last_name_row">
                                            <label for="billing_last_name">Last Name</label>
                                            <input tabindex="10" type="text"
                                                   class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>"
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked')"
                                                   size="30" maxlength="35" id="billing_last_name"
                                                   name="billing_last_name" placeholder="Last Name"
                                                   value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_business_name_row">
                                            <label for="billing_business_name">Business Name</label>
                                            <input tabindex="10" type="text"
                                                   class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>"
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked')"
                                                   size="30" maxlength="35" id="billing_business_name"
                                                   name="billing_business_name" placeholder="Business Name"
                                                   value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_address_1_row">
                                            <label for="billing_address_1" <?= (!$GLOBALS['gInternalConnection'] ? ' class="required-label"' : "") ?>>Address</label>
                                            <input tabindex="10" type="text" data-prefix="billing_" autocomplete='chrome-off' autocomplete='off'
                                                   class="autocomplete-address billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>"
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked')"
                                                   size="30" maxlength="60" id="billing_address_1"
                                                   name="billing_address_1" placeholder="Address" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_address_2_row">
                                            <label for="billing_address_2" class=""></label>
                                            <input tabindex="10" type="text"
                                                   class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>"
                                                   size="30" maxlength="60" id="billing_address_2"
                                                   name="billing_address_2" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_city_row">
                                            <label for="billing_city" <?= (!$GLOBALS['gInternalConnection'] ? ' class="required-label"' : "") ?>>City</label>
                                            <input tabindex="10" type="text"
                                                   class="billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>"
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked')"
                                                   size="30" maxlength="60" id="billing_city" name="billing_city"
                                                   placeholder="City" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_state_row">
                                            <label for="billing_state" class="">State</label>
                                            <input tabindex="10" type="text"
                                                   class="billing-address validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>"
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked') && $('#billing_country_id').val() == 1000"
                                                   size="10" maxlength="30" id="billing_state" name="billing_state"
                                                   placeholder="State" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_state_select_row">
                                            <label for="billing_state_select" class="">State</label>
                                            <select tabindex="10" id="billing_state_select" name="billing_state_select"
                                                    class="validate[<?= (!$GLOBALS['gInternalConnection'] ? "required" : "") ?>]"
                                                    data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked') && $('#billing_country_id').val() == 1000">
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
                                                   data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked') && $('#billing_country_id').val() == 1000"
                                                   id="billing_postal_code" name="billing_postal_code"
                                                   placeholder="Postal Code" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_country_id_row">
                                            <label for="billing_country_id" class="">Country</label>
                                            <select tabindex="10" class="billing-address validate[required]"
                                                    data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#same_address').prop('checked')"
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
                        </div> <!-- payment_information_wrapper -->

                        <p class='error-message'></p>
                        <div class="checkout-button-section">
                            <button tabindex="10" class='previous-section-button'>Previous</button>
                            <button tabindex="10" class='next-section-button'>Next</button>
                        </div> <!-- checkout-button-section -->

                    </div> <!-- payment_section -->
					<?php

					# Finalize and Place Order

					?>
                    <div id="finalize_section" class="hidden form-section-wrapper checkout-section">
						<?php
						$sectionText = $this->getPageTextChunk("retail_store_order_summary");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_order_summary");
						}
						if (empty($sectionText)) {
							ob_start();
							?>
                            <h3>Order Summary</h3>
                            <table id="order_summary_table" class='grid-table'>
                                <tr>
                                    <td class="order-summary-description" id="cart_items_summary_label">Cart Items</td>
                                    <td class='align-right'><span class='cart-total-quantity'></span> product<span
                                                class='cart-total-quantity-plural'>s</span></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description" id="cart_total_summary_label">Cart Total</td>
                                    <td class='align-right cart-total'></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description" id="tax_summary_label">Estimated Tax</td>
                                    <td class="tax-charge align-right"></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description" id="shipping_summary_label">Shipping</td>
                                    <td class="shipping-charge align-right"></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description" id="handling_summary_label">Handling</td>
                                    <td class="handling-charge align-right"></td>
                                </tr>
								<?php if (!empty($designations)) { ?>
                                    <tr>
                                        <td class="order-summary-description" id="donation_summary_label">Donation</td>
                                        <td class="donation-amount align-right"></td>
                                    </tr>
								<?php } ?>
                                <tr id='order_summary_discount_wrapper'>
                                    <td class="order-summary-description" id="discount_summary_label">Discount</td>
                                    <td class="discount-amount align-right"></td>
                                </tr>
                                <tr id="order_summary_total">
                                    <td id="order_total_summary_label">Order Total</td>
                                    <td class="order-total align-right"></td>
                                </tr>
                            </table>
							<?php
							$sectionText = ob_get_clean();
						}
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
						$mailingLists = ob_get_clean();
						?>
                        <div id="finalize_wrapper">
                            <div id="order_summary_wrapper">
                                <h2>Place Order</h2>
								<?php
								if ($GLOBALS['gInternalConnection']) {
									?>
                                    <div class="form-line" id="_order_notes_content_row">
                                        <label for="order_notes_content">Packing Slip Notes</label>
                                        <textarea id="order_notes_content" name="order_notes_content"></textarea>
                                        <div class='clear-div'></div>
                                    </div>
									<?php
								}
								$giftText = $this->getPageTextChunk("retail_store_gift");
								if (empty($giftText)) {
									$giftText = $this->getFragment("retail_store_gift");
								}
								echo makeHtml($giftText);
								?>
                                <div class="shipping-section form-line" id="_gift_order_row">
                                    <input type="checkbox" id="gift_order" name="gift_order" value="1"><label
                                            for="gift_order" class="checkbox-label">This order is a gift</label>
                                    <div class='clear-div'></div>
                                </div>
                                <div class="shipping-section form-line hidden" id="_gift_text_row">
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
                                    <div class="form-line" id="_tax_exempt_number_row">
                                        <label for="tax_exempt_number">Tax Exempt Number</label>
                                        <input type="text" class='validate[required]' id="tax_exempt_id"
                                               name="tax_exempt_id"
                                               data-conditional-required="$('#tax_exempt').prop('checked')"
                                               value="<?= CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "TAX_EXEMPT_ID") ?>">
                                        <div class='clear-div'></div>
                                    </div>

                                    <div class="form-line" id="_tax_exempt_row">
                                        <input type="checkbox" id="tax_exempt" name="tax_exempt" value="1"><label
                                                for="tax_exempt" class="checkbox-label">Tax Exempt</label>
                                        <div class='clear-div'></div>
                                    </div>
								<?php } ?>

                                <input type="hidden" id="tax_charge" name="tax_charge" class="tax-charge" value="0">
                                <input type="hidden" id="shipping_charge" name="shipping_charge" class="shipping-charge"
                                       value="0">
                                <input type="hidden" id="handling_charge" name="handling_charge" class="handling-charge"
                                       value="0">
                                <input type="hidden" id="cart_total_quantity" name="cart_total_quantity"
                                       class="cart-total-quantity" value="0">
                                <input type="hidden" id="cart_total" name="cart_total" class="cart-total" value="0">
                                <input type="hidden" id="discount_amount" name="discount_amount" value="0">
                                <input type="hidden" id="discount_percent" name="discount_percent" value="0">
                                <input type="hidden" id="order_total" name="order_total" class="order-total" value="0">
								<?= makeHtml($sectionText) ?>
                            </div>
                            <div id="finalize_checkboxes_wrapper">
								<?php
								echo $mailingLists;
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
                                    <input tabindex="10" type="text" size="20" maxlength="20" id="bank_name"
                                           name="bank_name" placeholder="Bank Name" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_agree_terms_row">
                                    <input tabindex="10" type="checkbox" name="agree_terms" id="agree_terms"
                                           value="1"><label for="agree_terms">Agree to our terms of service</label>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_confirm_human_row">
                                    <input tabindex="10" type="checkbox" name="confirm_human" id="confirm_human"
                                           value="1"><label class='checkbox-label'>Click here to confirm you are
                                        human</label>
                                    <div class='clear-div'></div>
                                </div>

								<?php
								echo "<h3>Terms & Conditions</h3>";
								$sectionText = $this->getPageTextChunk("retail_store_terms_conditions");
								if (empty($sectionText)) {
									$sectionText = $this->getFragment("retail_store_terms_conditions");
								}
								if (!empty($sectionText)) {
									?>
                                    <div class="form-line" id="_terms_conditions_row">
                                        <input type="checkbox" id="terms_conditions" name="terms_conditions"
                                               class="validate[required]"
                                               value="1" <?= ($GLOBALS['gInternalConnection'] ? " checked" : "") ?>><label
                                                for="terms_conditions" class="checkbox-label">I agree to the Terms and
                                            Conditions.</label> <a href='#' id="view_terms_conditions"
                                                                   class="clickable">Click here to view store Terms and
                                            Conditions.</a>
                                        <div class='clear-div'></div>
                                    </div>
									<?php
									echo "<div class='dialog-box' id='_terms_conditions_dialog'><div id='_terms_conditions_wrapper'>" . makeHtml($sectionText) . "</div></div>";
								}
								?>
                                <div class="form-line hidden" id="_dealer_terms_conditions_row">
                                    <input type="checkbox" id="dealer_terms_conditions" name="dealer_terms_conditions"
                                           class="" value="1">
                                    <label for="dealer_terms_conditions" class="checkbox-label">I agree to the FFL
                                        Dealer's Terms and Conditions.</label>
                                    <a href='#' id="view_dealer_terms_conditions" class="clickable">Click here to view
                                        FFL Dealer's Terms and Conditions.</a>
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

                                <div id="retail_agreements">
                                </div>
								<?php
                                if (!$GLOBALS['gUserRow']['administrator_flag']) {
                                    if (!empty($this->iUseRecaptchaV2)) {
                                        ?>
                                        <div class="g-recaptcha" data-sitekey="<?= getPreference("ORDER_RECAPTCHA_V2_SITE_KEY") ?>"></div>
                                        <?php
                                    } else if (!empty(getPreference("USE_ORDER_CAPTCHA"))) {
                                        $captchaCodeId = createCaptchaCode();
                                        ?>
                                        <input type='hidden' id='captcha_code_id' name='captcha_code_id' value='<?= $captchaCodeId ?>'>

                                        <div class='form-line' id=_captcha_image_row'>
                                            <label></label>
                                            <img src="/captchagenerator.php?id=<?= $captchaCodeId ?>">
                                        </div>

                                        <div class="form-line" id="_captcha_code_row">
                                            <label for="captcha_code" class="">Captcha Text</label>
                                            <input tabindex="10" type="text" size="10" maxlength="10" id="captcha_code"
                                                   name="captcha_code" placeholder="Captcha Text" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                <?php }
                                } ?>
                            </div> <!-- finalize_checkboxes_wrapper -->
                        </div> <!-- finalize_wrapper -->
						<?php
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
                        <p class="error-message"></p>
                        <div class="checkout-button-section">
                            <button tabindex="10" class='previous-section-button'>Previous</button>
                            <button tabindex="10" id="finalize_order">Place My Order</button>
                        </div> <!-- checkout-button-section -->

                    </div> <!-- finalize_section -->
                </div> <!-- _checkout_section_wrapper -->

            </form>
        </div> <!-- shopping_cart_wrapper -->
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function jqueryTemplates() {
		$showListPrice = (getFieldFromId("price_calculation_type_id", "pricing_structures", "pricing_structure_code", "DEFAULT") == getFieldFromId("price_calculation_type_id", "price_calculation_types", "price_calculation_type_code", "DISCOUNT"));
		$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
		$customFieldId = CustomField::getCustomFieldIdFromCode("DEFAULT_LOCATION_ID");

		$shoppingCartItem = $this->getPageTextChunk("retail_store_shopping_cart_item");
		if (empty($shoppingCartItem)) {
			$shoppingCartItem = $this->getFragment("retail_store_shopping_cart_item");
		}
		if (empty($shoppingCartItem)) {
			ob_start();
			?>
            <table class="hidden" id="shopping_cart_item_template">
                <tbody id="_shopping_cart_item_block">
                <tr class="shopping-cart-item %other_classes%" id="shopping_cart_item_%shopping_cart_item_id%"
                    data-shopping_cart_item_id="%shopping_cart_item_id%" data-product_id="%product_id%"
                    data-product_tag_ids="%product_tag_ids%">
                    <td class="align-center"><a href="%image_url%" class="pretty-photo"><img alt="small image"
                                                                                             %image_src%="%small_image_url%"></a>
                    </td>
                    <td class="product-description"><a href="/product-details?id=%product_id%">%description%</a><span
                                class="out-of-stock-notice">Out Of Stock</span><span class="no-online-order-notice">In-store purchase only</span>
                    </td>
					<?php if ($showListPrice) { ?>
                        <td class="discount-column align-right"><span class="product-list-price">%list_price%</span>
                        </td>
                        <td class="discount-column align-right"><span class="product-discount">%discount%</span><span
                                    class="product-savings hidden">%savings%</span></td>
					<?php } ?>
                    <td class="align-right"><span class="original-sale-price">%original_sale_price%</span><span
                                class="dollar">$</span><span class="product-sale-price">%sale_price%</span></td>
                    <td class="align-center product-quantity-wrapper">
                        <span class="fa fa-minus shopping-cart-item-decrease-quantity" data-amount="-1"></span>
                        <input class="product-quantity" data-cart_maximum="%cart_maximum%"
                               data-cart_minimum="%cart_minimum%" value='%quantity%'>
                        <span class="fa fa-plus shopping-cart-item-increase-quantity" data-amount="1"></span>
                    </td>
                    <td class="align-right"><span class="dollar">$</span><span class="product-total"></span><input
                                class="cart-item-additional-charges" type="hidden"
                                name="shopping_cart_item_additional_charges_%shopping_cart_item_id%"
                                id="shopping_cart_item_additional_charges_%shopping_cart_item_id%"></td>
                    <td class="controls align-center"><span class="fa fa-times remove-item"
                                                            data-product_id="%product_id%"></span></td>
                </tr>
                <tr class="item-custom-fields %custom_field_classes%" id="custom_fields_%shopping_cart_item_id%">
                    <td colspan="6">%custom_fields%</td>
                </tr>
                <tr class="item-addons %addon_classes%" id="addons_%shopping_cart_item_id%"
                    data-shopping_cart_item_id="%shopping_cart_item_id%">
                    <td colspan="6">%item_addons%</td>
                </tr>
				<?php if (!empty($showLocationAvailability) && !empty($customFieldId)) { ?>
                    <tr class="item-availability hidden" id="availability_%shopping_cart_item_id%"
                        data-shopping_cart_item_id="%shopping_cart_item_id%">
                        <td colspan="6"></td>
                    </tr>
				<?php } ?>
                </tbody>
            </table>
			<?php
			$shoppingCartItem = ob_get_clean();
		}
		echo makeHtml($shoppingCartItem);
		?>
        <table class="hidden">
            <tbody id="payment_list_template">
            <tr class="payment-list-item hidden" id="payment_method_%payment_method_number%">
                <td class="payment-method"></td>
                <td class="remove-payment">
                    <input type="hidden" class="payment-method-number"
                           id="payment_method_number_%payment_method_number%"
                           name="payment_method_number_%payment_method_number%" value="%payment_method_number%">
                    <input type="hidden" id="account_id_%payment_method_number%"
                           name="account_id_%payment_method_number%">
                    <input type="hidden" class="payment-method-id" id="payment_method_id_%payment_method_number%"
                           name="payment_method_id_%payment_method_number%">
                    <input type="hidden" class="maximum-payment-amount"
                           id="maximum_payment_amount_%payment_method_number%"
                           name="maximum_payment_amount_%payment_method_number%">
                    <input type="hidden" class="maximum-payment-percentage"
                           id="maximum_payment_percentage_%payment_method_number%"
                           name="maximum_payment_percentage_%payment_method_number%">
                    <input type="hidden" id="account_number_%payment_method_number%"
                           name="account_number_%payment_method_number%">
                    <input type="hidden" id="expiration_month_%payment_method_number%"
                           name="expiration_month_%payment_method_number%">
                    <input type="hidden" id="expiration_year_%payment_method_number%"
                           name="expiration_year_%payment_method_number%">
                    <input type="hidden" id="cvv_code_%payment_method_number%" name="cvv_code_%payment_method_number%">
                    <input type="hidden" id="routing_number_%payment_method_number%"
                           name="routing_number_%payment_method_number%">
                    <input type="hidden" id="bank_account_number_%payment_method_number%"
                           name="bank_account_number_%payment_method_number%">
                    <input type="hidden" id="gift_card_number_%payment_method_number%"
                           name="gift_card_number_%payment_method_number%">
                    <input type="hidden" id="loan_number_%payment_method_number%"
                           name="loan_number_%payment_method_number%">
                    <input type="hidden" id="lease_number_%payment_method_number%"
                           name="lease_number_%payment_method_number%">
                    <input type="hidden" id="reference_number_%payment_method_number%"
                           name="reference_number_%payment_method_number%">
                    <input type="hidden" id="payment_time_%payment_method_number%"
                           name="payment_time_%payment_method_number%">
                    <input type="hidden" id="account_label_%payment_method_number%"
                           name="account_label_%payment_method_number%">
                    <input type="hidden" id="same_address_%payment_method_number%"
                           name="same_address_%payment_method_number%">
                    <input type="hidden" id="billing_first_name_%payment_method_number%"
                           name="billing_first_name_%payment_method_number%">
                    <input type="hidden" id="billing_last_name_%payment_method_number%"
                           name="billing_last_name_%payment_method_number%">
                    <input type="hidden" id="billing_business_name_%payment_method_number%"
                           name="billing_business_name_%payment_method_number%">
                    <input type="hidden" id="billing_address_1_%payment_method_number%"
                           name="billing_address_1_%payment_method_number%">
                    <input type="hidden" id="billing_address_2_%payment_method_number%"
                           name="billing_address_2_%payment_method_number%">
                    <input type="hidden" id="billing_city_%payment_method_number%"
                           name="billing_city_%payment_method_number%">
                    <input type="hidden" id="billing_state_%payment_method_number%"
                           name="billing_state_%payment_method_number%">
                    <input type="hidden" id="billing_state_select_%payment_method_number%"
                           name="billing_state_select_%payment_method_number%">
                    <input type="hidden" id="billing_postal_code_%payment_method_number%"
                           name="billing_postal_code_%payment_method_number%">
                    <input type="hidden" id="billing_country_id_%payment_method_number%"
                           name="billing_country_id_%payment_method_number%">
                    <input type="hidden" class="primary-payment-method"
                           id="primary_payment_method_%payment_method_number%"
                           name="primary_payment_method_%payment_method_number%" value="1">
                    <span class="fa fa-times"></span>Remove
                </td>
                <td class="edit-payment"><span class="fa fa-edit"></span>Edit</td>
                <td class="payment-amount hidden"><label>Amount</label><input tabindex="10" type="text"
                                                                              class="payment-amount-value align-right validate[required,custom[number],min[.01],max[999999]]"
                                                                              data-conditional-required="empty($('#primary_payment_method_%payment_method_number%').val())"
                                                                              data-decimal-places="2"
                                                                              id="payment_amount_%payment_method_number%"
                                                                              name="payment_amount_%payment_method_number%"
                                                                              placeholder="Amount"></td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_cart_loading {
                font-size: 64px;
                color: rgb(200, 200, 200);
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

            #same_address_label {
                font-size: 1.5rem;
                margin: 0 0 0 10px;
                padding: 0;
            }

            #same_address {
                font-size: 1.5rem;
                margin: 0;
                padding: 0;
            }

            .pickup-location-wrapper {
                margin: 0 0 20px 0;
                padding: 0 0 20px 0;
                border-bottom: 1px solid rgb(150, 150, 150);
            }

            .pickup-location-wrapper p {
                margin: 0;
                padding: 0;
            }

            .pickup-location-wrapper img {
                height: 140px;
                float: left;
                margin-right: 20px;
            }

            #pickup_locations {
                height: 600px;
                overflow: scroll;
            }

            .shipping-type-wrapper {
                margin: 10px;
                padding: 10px;
            }

            .pickup-type-selected {
                display: none;
            }

            #pickup_display {
                font-size: 1.2rem;
                font-weight: 900;
                color: rgb(0, 80, 0);
                margin-left: 10px;
                margin-right: 10px;
            }

            #change_pickup_location {
                font-size: .8rem;
            }

            #_show_shipping_calculation_log_row {
                margin-top: 20px;
            }

            #show_shipping_calculation_log {
                font-size: .8rem;
                padding: 6px 20px;
            }

            #shipping_information_section.internal-pickup #shipping_address_wrapper {
                display: none;
            }

            #shipping_information_section.internal-pickup #shipping_section_wrapper {
                display: none;
            }

            #guest_checkout {
                flex: 1 1 50%;
            }

            .ffl-choice p {
                padding: 0;
                margin: 0;
            }

            .ffl-choice p.ffl-distance {
                padding-top: 5px;
            }

            .distance--miles {
                display: none;
            }

            #selected_ffl_dealer .ffl-distance {
                display: none;
            }

            #restricted_dealers {
                color: rgb(192, 0, 0);
            }

            .form-line {
                max-width: 800px;
                margin-bottom: 0;
            }

            #ffl_section {
                display: none;
            }

            #ffl_section.ffl-required {
                display: block;
            }

            #ffl_dealers_wrapper {
                height: 300px;
                overflow: scroll;
                max-width: 600px;
                margin: 10px 0;
            }

            #_main_content ul#ffl_dealers {
                list-style: none;
                margin: 0;
            }

            #ffl_dealers li {
                list-style: none;
                margin: 0;
                padding: 5px 10px;
                cursor: pointer;
                background-color: rgb(220, 220, 220);
                border-bottom: 1px solid rgb(200, 200, 200);
                line-height: 1.2;
            }

            #ffl_dealers li:hover {
                background-color: rgb(180, 190, 200);
            }

            #ffl_dealers li.preferred {
                font-weight: 900;
            }

            #ffl_dealers li.restricted {
                background-color: rgb(250, 210, 210);
            }

            #ffl_dealers li.have-license {
                background-color: rgb(180, 230, 180);
            }

            #selected_ffl_dealer {
                font-weight: 900;
                font-size: 1.4rem;
            }

            #ffl_dealer_filter {
                display: block;
                font-size: 1.2rem;
                padding: 5px;
                border-radius: 5px;
                width: 400px;
                margin-bottom: 5px;
            }

            .form-line input[type="text"] {
                width: 80%;
            }

            .form-line select {
                width: 80%;
            }

            #_terms_conditions_wrapper {
                max-height: 80vh;
                height: 800px;
                overflow: scroll;
            }

            #order_summary_table {
                margin: 10px 0 20px 0;
            }

            #order_summary_table tr td:first-child {
                padding-right: 40px;
            }

            #round_up_buttons button {
                margin-right: 10px;
            }

            .form-line select.expiration-date {
                width: 40%;
                max-width: 200px;
                display: inline-block;
            }

            #payment_methods_list {
                margin: 10px 0 20px 0;
            }

            #payment_methods_list td {
                padding: 5px 10px;
                position: relative;
            }

            #payment_methods_list input[type=text] {
                width: 120px;
                margin-left: 20px;
                display: inline;
            }

            #payment_methods_list tr {
                border-bottom: 1px solid rgb(200, 200, 200);
            }

            #payment_methods_list span.fa {
                margin-right: 10px;
            }

            .remove-payment {
                cursor: pointer;
            }

            .edit-payment {
                cursor: pointer;
            }

            .strength-bar-div {
                height: 16px;
                width: 200px;
                margin: 0;
                margin-top: 10px;
                display: block;
                top: 5px;
            }

            #_main_content p.strength-bar-label {
                font-size: .6rem;
                margin: 0;
            }

            .strength-bar {
                font-size: 1px;
                height: 8px;
                width: 10px;
            }

            .payment-description p {
                color: rgb(192, 0, 0);
                margin-top: 10px;
                font-weight: 700;
            }

            #processing_order {
                margin: 10px 0;
            }

            #processing_order p {
                color: rgb(192, 0, 0);
                font-weight: 700;
                font-size: 1.4rem;
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

            tr.item-addons {
                display: none;
            }

            tr.item-addons.active-fields {
                display: table-row;
            }

            div.not-required {
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

            #address_id_label {
                font-size: 1rem;
                color: rgb(180, 10, 10);
                font-weight: 900;
            }

            #related_products_wrapper {
                width: 100%;
                overflow: scroll;
                padding: 20px 0;
            }

            #related_products {
                display: flex;
            }

            #related_products .catalog-item {
                background-color: #fff;
                margin-right: 10px;
                flex: 0 0 auto;
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
                line-height: 1.1;
                width: 240px;
                overflow: hidden;
            }

            #related_products .catalog-item-thumbnail {
                text-align: center;
                margin-bottom: 10px;
                height: 100px;
                position: relative;
            }

            #related_products .catalog-item-thumbnail img {
                max-height: 100px;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                cursor: zoom-in;
            }

            #related_products .catalog-item-description {
                font-size: .9rem;
                text-align: center;
                font-weight: 700;
                height: 80px;
                overflow: hidden;
                position: relative;
            }

            #related_products .catalog-item-description:after {
                content: "";
                position: absolute;
                top: 60px;
                left: 0;
                height: 20px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            #related_products .catalog-item-brand {
                display: none;
            }

            #related_products .catalog-item-product-code {
                display: none;
            }

            #related_products .catalog-item-manufacturer-sku {
                display: none;
            }

            #related_products .catalog-item-upc-code {
                display: none;
            }

            #related_products .catalog-item-price-wrapper {
                font-weight: 900;
                text-align: center;
                padding: 5px 0;
                font-size: 1.2rem;
                color: rgb(0, 0, 0);
            }

            #related_products .catalog-item-out-of-stock {
                padding: 5px;
                text-align: center;
            }

            #related_products .catalog-item-out-of-stock button {
                font-size: .7rem;
                padding: 4px 12px;
                width: 90%;
            }

            #related_products .catalog-item-add-to-cart {
                padding: 5px;
                text-align: center;
            }

            #related_products .catalog-item-add-to-cart button {
                font-size: .7rem;
                padding: 4px 12px;
                width: 90%;
            }

            #related_products .catalog-item-add-to-wishlist {
                padding: 5px;
                text-align: center;
            }

            #related_products .catalog-item-add-to-wishlist button {
                font-size: .7rem;
                padding: 4px 12px;
                width: 90%;
            }

            #related_products .button-subtext {
                display: none;
            }

            #related_products .map-priced-product .button-subtext {
                display: inline;
            }

            #related_products .out-of-stock-product .button-subtext {
                display: inline;
                white-space: pre-line;
            }

            #related_products .catalog-item-out-of-stock {
                display: none;
            }

            #related_products .out-of-stock-product .catalog-item-out-of-stock {
                display: block;
            }

            #related_products .out-of-stock-product .catalog-item-add-to-cart {
                display: none;
            }

            @media (max-width: 800px) {
                #shipping_address_block {
                    display: block;
                }

                #payment_method_wrapper {
                    display: block;
                }
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

            #totals_wrapper {
                font-size: 1.4rem;
                display: flex;
                width: 100%;
                margin: 20px 0;
            }

            #totals_wrapper div {
                flex: 1 1 auto;
            }

            input#add_product {
                width: 95%;
                font-size: 1.2rem;
                max-width: 300px;
            }

            input#promotion_code {
                width: 95%;
                font-size: 1.2rem;
                max-width: 300px;
            }

            #order_summary_table td {
                font-size: 1.2rem;
            }

            #order_summary_table td.order-summary-description {
                background-color: rgb(220, 220, 220);
                padding-right: 40px;
            }

            #order_summary_table td.align-right {
                padding-left: 40px;
            }

            #order_summary_total {
                border-top: 2px solid rgb(40, 40, 40);
            }

            #order_summary_total td {
                font-size: 1.4rem;
                font-weight: 900;
            }

            #_cvv_code_row {
                position: relative;
            }

            #cvv_code {
                width: 180px;
                max-width: 180px;
            }

            #cvv_image {
                height: 60px;
                top: 10px;
                position: absolute;
                left: 220px;
            }

            #payment_logos {
                margin-top: 10px;
            }

            .payment-method-logo {
                opacity: .3;
            }

            .payment-method-logo.selected {
                opacity: 1;
            }

            .original-sale-price {
                text-decoration: line-through;
                opacity: .5;
                margin-right: 10px;
            }

            #_checkout_section_wrapper {
                border: 1px solid rgb(0, 0, 0);
                padding: 0;
                border-radius: 3px;
                margin-bottom: 40px;
                position: relative;
            }

            #_section_chooser {
                display: flex;
                width: 100%;
                border-bottom: 1px solid rgb(0, 0, 0);
            }

            #_section_chooser div {
                flex: 1 1 auto;
                text-align: center;
                cursor: pointer;
                padding: 10px 20px;
                border-left: 1px solid rgb(0, 0, 0);
            }

            #_section_chooser div.section-chooser-option:first-child {
                border-left: none;
            }

            #_section_chooser div.section-chooser-option.selected {
                background-color: rgb(150, 150, 150);
                color: rgb(250, 250, 250);
                font-weight: bold;
            }

            .checkout-section {
                background-color: rgb(255, 255, 255);
                padding: 20px 20px 0 20px;
            }

            .checkout-button-section {
                width: 100%;
                padding: 10px 20px;
                margin-top: 20px;
                border-top: 1px solid rgb(0, 0, 0);
                text-align: center;
            }

            .checkout-button-section button {
                margin: 0 10px;
            }

            #not_logged_in_options {
                display: flex;
                padding-bottom: 40px;
            }

            #not_logged_in_options > div {
                flex: 1 1 auto;
            }

            #login_now {
                padding-right: 30px;
            }

            #guest_checkout {
                padding-left: 30px;
                padding-right: 30px;
                border-right: 1px solid rgb(200, 200, 200);
                border-left: 1px solid rgb(200, 200, 200);
            }

            #user_information {
                padding-left: 30px;
            }

            #login_now {
                text-align: center;
            }

            #login_now p {
                padding: 0;
                margin: 0 auto;
                margin-bottom: 10px;
            }

            #login_now input {
                max-width: 250px;
            }

            #shipping_address_block {
                display: flex;
            }

            #shipping_address_block > div {
                flex: 0 0 50%;
            }

            #payment_method_wrapper {
                display: flex;
            }

            #payment_method_wrapper > div {
                flex: 0 0 50%;
            }

            #shipping_address_contact, #payment_information {
                padding-right: 60px;
            }

            #finalize_wrapper {
                display: flex;
            }

            #finalize_wrapper > div {
                flex: 0 0 50%;
            }

            #order_summary_wrapper {
                padding-right: 20px;
            }

            #finalize_checkboxes_wrapper {
                padding-left: 20px;
            }

            .payment-amount {
                padding-left: 20px;
            }

            .payment-amount-value {
                width: 80px;
                font-size: 12px;
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

            #_balance_remaining_wrapper {
                color: rgb(0, 150, 0);
                font-weight: 700;
            }

            #_order_total_exceeded {
                color: rgb(150, 0, 0);
                font-weight: 700;
                height: auto;
                padding: 2px 8px;
            }

            #ffl_radius {
                display: inline-block;
            }

            p.credova-button .crdv-button {
                background-color: transparent;
            }

            #empty_shopping_cart {
                padding: 0;
            }

            #_checkout_form p.credova-button {
                text-align: right;
            }

            .crdv-button-message {
                font-size: 1.4rem;
                font-weight: 700;
            }

            @media (max-width: 800px) {
                #_section_chooser div {
                    padding: 4px 8px;
                }

                #not_logged_in_options {
                    display: block;
                }

                #login_now {
                    padding-left: 30px;
                }

                #guest_checkout {
                    border-right: none;
                    border-left: none;
                }

                #user_information {
                    padding-right: 30px;
                }

                #shipping_address_block {
                    display: block;
                }

                #shipping_address_contact, #payment_information {
                    padding-right: 0;
                }

                #finalize_wrapper {
                    display: block;
                }

                #order_summary_wrapper {
                    padding-left: 20px;
                }

                #finalize_checkboxes_wrapper {
                    padding-right: 20px;
                }

                table#_shopping_cart_items td img {
                    max-width: 40px;
                    padding: 2px;
                }

                table#_shopping_cart_items td {
                    padding: 1.5px;
                    font-size: .9em;
                    line-height: 1.3;
                }

                table#_shopping_cart_items thead th {
                    font-size: .9em;
                }

                table#_shopping_cart_items td.product-quantity-wrapper span {
                    margin: 0 3px;
                }

                #totals_wrapper div {
                    font-size: .7em;
                    padding: 0 5px;
                }

                .form-line {
                    padding: 5px 10px;
                }

                .form-line input[type="text"] {
                    width: 100%;
                }

                .form-line select {
                    width: 100%;
                }

                #expiration_month_1 {
                    width: initial;
                }

                #expiration_year_1 {
                    width: initial;
                }

                #cvv_code_1 {
                    width: 40%;
                }

                .form-line label.checkbox-label {
                    margin-left: 0;
                }

                table#_shopping_cart_items td.product-description {
                    max-width: 80px;
                    overflow-x: scroll;
                    font-size: .8em;
                }

                #ffl_dealer_filter {
                    width: 100%;
                }

                .jSignature {
                    width: 250px !important;
                }

                #order_summary_table {
                    width: 100%;
                }

                #add_payment_method {
                    width: 100%;
                }

                td.payment-method {
                    font-weight: 600;
                    font-size: 1.3em;
                    text-transform: uppercase;
                }

                #ffl_radius {
                    border: 1px solid #000;
                    background: rgb(240, 240, 240);
                }

                .checkout-section {
                    padding: 10px;
                }
            }

            @media (max-width: 1000px) {
                .discount-column {
                    display: none;
                }
            }

            @media (max-width: 600px) {
                #totals_wrapper {
                    display: block;
                }

                #totals_wrapper div {
                    margin-bottom: 5px;
                }

                #totals_wrapper input {
                    max-width: 100%;
                    width: 100%;
                }
            }

        </style>
		<?php
	}

	function hiddenElements() {
		?>
		<?php if ($GLOBALS['gUserRow']['full_client_access']) { ?>
            <div id="_shipping_calculation_log_dialog" class="dialog-box">
                <div id="shipping_calculation_log"></div>
            </div>
		<?php } ?>

        <div id="_choose_pickup_location_dialog" class="dialog-box">
            <div id="pickup_locations"></div>
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
