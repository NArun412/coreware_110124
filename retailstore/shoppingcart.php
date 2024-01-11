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

class ThisPage extends Page {

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
			$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0" . (empty($GLOBALS['gInternalConnection']) ? " and internal_use_only = 0" : ""));
			$originalQuantity = $shoppingCart->getProductQuantity($productId);
			if ($originalQuantity == 0) {
				$quantity = 1;
				$shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => true));
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
	}

	function headerIncludes() {
		?>
        <script src="<?= autoVersion('/js/jsignature/jSignature.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.CompressorSVG.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.UndoButton.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/signhere/jSignature.SignHere.js') ?>"></script>
		<?php
	}

	function javascript() {
		$noShippingRequired = getPreference("RETAIL_STORE_NO_SHIPPING");
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
		$addressArray = array();
		$addressArray["0"] = array("address_label" => "Primary Address", "address_1" => $GLOBALS['gUserRow']['address_1'], "address_2" => $GLOBALS['gUserRow']['address_2'],
			"city" => $GLOBALS['gUserRow']['city'], "state" => $GLOBALS['gUserRow']['state'], "postal_code" => $GLOBALS['gUserRow']['postal_code'],
			"country_id" => $GLOBALS['gUserRow']['country_id']);
		$resultSet = executeQuery("select address_id,address_label,address_1,address_2,city,state,postal_code,country_id from addresses where contact_id = ? and " .
			"inactive = 0 and address_label is not null", $GLOBALS['gUserRow']['contact_id']);
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
		?>
        <script>
            emptyImageFilename = "<?= $missingProductImage ?>";
            var addressArray = <?= jsonEncode($addressArray) ?>;
			<?php
			$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
			if (!empty($fflRequiredProductTagId)) {
			?>
            var fflDealers = [];
			<?php
			}
			?>

            function getFFLTaxCharge() {
				<?php if ($noShippingRequired) { ?>
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_tax_charge", { federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val() }, function (returnArray) {
                    let taxCharge = 0;
                    if ("tax_charge" in returnArray) {
                        taxCharge = returnArray['tax_charge'];
                    }
                    $(".tax-charge").each(function () {
                        if ($(this).is("input")) {
                            $(this).val(Round(taxCharge, 2));
                        } else {
                            $(this).html(RoundFixed(taxCharge, 2));
                        }
                    });
                    calculateOrderTotal();
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
                if ($("#related_products").length == 0) {
                    return;
                }
                var productIds = "";
                $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
                    productIds += (empty(productIds) ? "" : ",") + $(this).data("product_id");
                });
                loadRelatedProducts(productIds);
            }

            function showNextSection(sectionNumber) {
                if (empty(sectionNumber)) {
                    var nextElement = $(".checkout-section.hidden").first();
                } else {
                    sectionNumber = parseInt(sectionNumber) + 1;
                    var nextElement = $("div.checkout-section[data-checkout_section_number='" + sectionNumber + "']");
                }
                nextElement.removeClass("hidden");
                if (nextElement.hasClass("no-action-required")) {
                    showNextSection();
                }
            }

            var savedShippingMethodId = "";
            function getShippingMethods() {
                var getNewShippingMethods = true;
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
                if (getNewShippingMethods) {
                    $("#shipping_method_id").val("").find("option[value!='']").remove();
                    $("#_shipping_method_id_row").addClass("hidden");
                    $("#calculating_shipping_methods").removeClass("hidden");
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shipping_methods", { state: $("#state").val(), postal_code: $("#postal_code").val(), country_id: $("#country_id").val() }, function (returnArray) {
                        $("#shipping_method_id").val("").find("option[value!='']").remove();
                        var shippingMethodCount = 0;
                        var singleShippingMethodId = "";
                        if ("shipping_methods" in returnArray) {
                            for (var i in returnArray['shipping_methods']) {
                                var thisOption = $("<option></option>").data("shipping_charge", returnArray['shipping_methods'][i]['shipping_charge']).attr("value", returnArray['shipping_methods'][i]['key_value']).text(returnArray['shipping_methods'][i]['description']);
                                $("#shipping_method_id").append(thisOption);
                                singleShippingMethodId = returnArray['shipping_methods'][i]['key_value'];
                                shippingMethodCount++;
                            }
                        }
                        if (!empty(savedShippingMethodId)) {
                            if ($("#shipping_method_id option[value='" + savedShippingMethodId + "']").length > 0) {
                                $("#shipping_method_id").val(savedShippingMethodId).trigger("change");
                            } else {
                                savedShippingMethodId = "";
                            }
                        }
                        if (empty(savedShippingMethodId) && shippingMethodCount == 1 && !empty(singleShippingMethodId)) {
                            $("#shipping_method_id").val(singleShippingMethodId);
                            $("#shipping_method_section").addClass("no-action-required");
                        }
                        var taxCharge = "0";
                        if ("tax_charge" in returnArray) {
                            taxCharge = returnArray['tax_charge'];
                        }
                        $(".tax-charge").each(function () {
                            if ($(this).is("input")) {
                                $(this).val(Round(taxCharge, 2));
                            } else {
                                $(this).html(RoundFixed(taxCharge, 2));
                            }
                        });
                        $("#_shipping_method_id_row").removeClass("hidden");
                        $("#calculating_shipping_methods").addClass("hidden");
                        calculateOrderTotal();
                    });
                }
            }

            function calculateOrderTotal() {
				<?php if ($GLOBALS['gInternalConnection']) { ?>
                if ($("#tax_exempt").prop("checked")) {
                    $("#tax_charge").val("0");
                }
				<?php } ?>
                var cartTotal = parseFloat(empty($("#cart_total").val()) ? 0 : $("#cart_total").val());
                var taxChargeString = $("#tax_charge").val();
                var taxCharge = (empty(taxChargeString) ? 0 : parseFloat(taxChargeString));
                if (isNaN(taxCharge)) {
                    taxCharge = 0;
                }
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

                var handlingCharge = 0;
                $("#payment_method_id").find("option").each(function () {
                    var flatRate = parseFloat($(this).data("flat_rate"));
                    var feePercent = parseFloat($(this).data("fee_percent"));
                    if ((flatRate == 0 || empty(flatRate) || isNaN(flatRate)) && (empty(feePercent) || feePercent == 0 || isNaN(feePercent))) {
                        return true;
                    }
                    var paymentMethodId = $(this).val();
                    $("#payment_methods_list").find(".payment-list-item").each(function () {
                        if ($(this).find(".payment-method-id").val() == paymentMethodId) {
                            var amount = parseFloat($(this).find(".payment-amount-value").val());
                            if ($(this).find(".primary-payment-method").prop("checked")) {
                                amount = cartTotal + shippingCharge + taxCharge;
                                $("#payment_methods_list").find(".payment-list-item").each(function () {
                                    if (!$(this).find(".primary-payment-method").prop("checked")) {
                                        var thisAmount = parseFloat($(this).find(".payment-amount-value").val());
                                        if (empty(thisAmount) || thisAmount == 0 || isNaN(thisAmount)) {
                                            thisAmount = 0;
                                        }
                                        amount -= thisAmount;
                                    }
                                });
                            } else {
                                if (empty(amount) || amount == 0 || isNaN(amount)) {
                                    amount = 0;
                                }
                            }
                            handlingCharge = Round(flatRate + (amount * feePercent / 100), 2);
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

                var orderTotal = Round(cartTotal + taxCharge + shippingCharge + handlingCharge, 2);
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
                    orderTotal += donationAmount;
                }
                $("#payment_methods_list").find(".payment-list-item").each(function () {
                    var maxAmount = $(this).find(".maximum-payment-amount").val();
                    var maxPercent = $(this).find(".maximum-payment-percentage").val();
                    if (!empty(maxPercent)) {
                        maxAmount = Round(orderTotal * (maxPercent / 100), 2);
                        $(this).find(".maximum-payment-amount").val(maxAmount);
                    }
                    if (empty(maxAmount)) {
                        $(this).find(".payment-amount-value").removeData("maximum-value");
                    } else {
                        $(this).find(".payment-amount-value").data("maximum-value", maxAmount);
                    }
                });

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
                orderTotal = orderTotal - discountAmount;
                if (discountAmount > 0) {
                    $("#order_summary_discount_wrapper").removeClass("hidden");
                    $(".discount-amount").html(RoundFixed(discountAmount, 2));
                } else {
                    $("#order_summary_discount_wrapper").addClass("hidden");
                    $(".discount-amount").html("");
                }

                $(".order-total").each(function () {
                    if ($(this).is("input")) {
                        $(this).val(Round(orderTotal, 2));
                    } else {
                        $(this).html(RoundFixed(orderTotal, 2));
                    }
                });
                if (orderTotal > 0 || $("#shopping_cart_items_wrapper").find(".recurring-payment").length > 0) {
                    $("#_no_payment_required").addClass("hidden");
                    $("#payment_information_wrapper").removeClass("hidden");
                    $("#payment_method_section").removeClass("no-action-required");
                } else {
                    $("#payment_information_wrapper").addClass("hidden");
                    $("#_no_payment_required").removeClass("hidden");
                    $("#payment_method_section").addClass("no-action-required");
                }
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
				<?php if (empty($onlyOnePayment)) { ?>
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".primary-payment").removeClass("hidden");
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".primary-payment-method").prop("checked", false);
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".payment-amount").removeClass("hidden");
				<?php } ?>
            }

            function copyPaymentMethodToList() {
                var paymentMethodNumber = $("#payment_method_number").val();
                if ($("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).length == 0) {
                    var paymentListItem = $("#payment_list_template").html();
                    paymentListItem = paymentListItem.replace(new RegExp("%payment_method_number%", 'ig'), paymentMethodNumber);
                    $("#payment_methods_list").append(paymentListItem);
                }
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find("input").each(function () {
                    var elementId = $(this).attr("id");
                    elementId = elementId.substr(0, elementId.length - ("_" + paymentMethodNumber).length);
                    if ($("#" + elementId).length == 0) {
                        return true;
                    }
                    if ($("#" + elementId).is("input[type=checkbox]")) {
                        $(this).val($("#" + elementId).prop("checked") ? "1" : "0");
                    } else {
                        $(this).val($("#" + elementId).val());
                    }
                });
                var paymentMethodDescription = $("#payment_method_id").val() == "" ? "" : $("#payment_method_id option:selected").text();
                if (!empty($("#account_id").val())) {
                    paymentMethodDescription = $("#account_id option:selected").text();
                }
                var accountNumber = $("#account_number").val().substr(-4) + $("#bank_account_number").val().substr(-4) + $("#gift_card_number").val().substr(-4) + $("#loan_number").val().substr(-4) + $("#lease_number").val().substr(-4);
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".payment-method").html(paymentMethodDescription + " " + accountNumber);
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".maximum-payment-amount").val($("#payment_method_id option:selected").data("maximum_payment_amount"));
                $("#payment_methods_list").find("#payment_method_" + paymentMethodNumber).find(".maximum-payment-percentage").val($("#payment_method_id option:selected").data("maximum_payment_percentage"));
                $(".payment-method").each(function () {
                    if (empty($(this).html())) {
                        $(this).closest(".payment-list-item").addClass("hidden");
                    } else {
                        $(this).closest(".payment-list-item").removeClass("hidden");
                    }
                });
            }

        </script>
		<?php
	}

	function onLoadJavascript() {
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
		$noSignature = getPreference("RETAIL_STORE_NO_SIGNATURE");
		$noShippingRequired = getPreference("RETAIL_STORE_NO_SHIPPING");
		?>
        <script>
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
                } else {
                    var primaryFound = false;
                    $("#payment_methods_list").find(".primary-payment-method").each(function () {
                        if ($(this).prop("checked")) {
                            primaryFound = true;
                            return false;
                        }
                    });
                    if (!primaryFound) {
                        $("#payment_methods_list").find(".primary-payment-method").first().trigger("click");
                    }
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
            $(document).on("click", ".primary-payment-method", function () {
                $("#payment_methods_list").find(".primary-payment").removeClass("hidden");
                $("#payment_methods_list").find(".primary-payment-method").prop("checked", false);
                $("#payment_methods_list").find(".payment-amount").removeClass("hidden").find(".payment-amount-value").val("");
                $(this).closest(".payment-list-item").find(".primary-payment").addClass("hidden");
                $(this).closest(".payment-list-item").find(".payment-amount").addClass("hidden");
                $(this).prop("checked", true);
            });
            $(document).on("change click", "#payment_method_section input,#payment_method_section select", function () {
                $("#new_payment").val("");
                copyPaymentMethodToList();
            });

            $("#shipping_method_id").change(function () {
                savedShippingMethodId = $(this).val();
                calculateOrderTotal();
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
				<?php if ($GLOBALS['gLoggedIn']) { ?>
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=checkout_started", function (returnArray) {
                    $("body").removeClass("no-waiting-for-ajax");
                });
				<?php } ?>
                showNextSection();
                sendAnalyticsEvent("checkout", {});
                return false;
            });
            $(document).on("change", ".show-next-section", function () {
                if ($(this).val().length > 0) {
                    showNextSection($(this).closest("div.checkout-section").data("checkout_section_number"));
                }
            });
            $(document).on("click", "input[type=checkbox].show-next-section", function () {
                showNextSection($(this).closest("div.checkout-section").data("checkout_section_number"));
            });
            $(document).on("change", "#email_address", function () {
                if (!$(this).validationEngine("validate")) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=set_shopping_cart_contact&email_address=" + encodeURIComponent($(this).val()), function (returnArray) {
                        if (!empty($("#promotion_code").val())) {
                            $("#promotion_code").trigger("change");
                        }
                    });
                }
            });
            $(document).on("click", "#terms_conditions", function () {
                showNextSection($(this).closest("div.checkout-section").data("checkout_section_number"));
            });
            $(document).on("change", "#payment_method_id", function (event) {
                if (!empty($(this).val()) && !empty($("#valid_payment_methods").val())) {
                    if (!isInArray($(this).val(), $("#valid_payment_methods").val())) {
                        $(this).val("");
                        displayErrorMessage("One or more items in the cart cannot be paid for with this payment method.");
                    }
                }
                $(".payment-description").addClass("hidden");
                if (!empty($(this).val()) && $("#payment_description_" + $(this).val()).length > 0) {
                    $("#payment_description_" + $(this).val()).removeClass("hidden");
                }
                $(".payment-method-logo").removeClass("selected");
                $("#payment_method_logo_" + $(this).val()).addClass("selected");
                $(".payment-method-fields").addClass("hidden");
                if (!empty($(this).val())) {
                    var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
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

                if ($("#shipping_information_section").hasClass("not-required")) {
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
            $(document).on("change", ".gift-card-number", function () {
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
                calculateOrderTotal();
            });
            $(".round-up-donation").click(function () {
                $("#donation_amount").val("");
                var orderTotal = calculateOrderTotal();
                var roundUpAmount = $(this).data("round_amount");
                var newOrderTotal = Math.ceil(orderTotal / roundUpAmount) * roundUpAmount;
                var donationAmount = Round(newOrderTotal - orderTotal, 2);
                $("#donation_amount").val(donationAmount).trigger("change");
                return false;
            });
			<?php if ($GLOBALS['gInternalConnection']) { ?>
            $("#tax_exempt").click(function () {
                calculateOrderTotal();
            });
			<?php } ?>
            $("#finalize_order").click(function () {
                var totalPayment = 0;
                var orderTotal = parseFloat($("#order_total").val());
                $("#payment_methods_list").find(".payment-list-item").each(function () {
                    var maxAmount = $(this).find(".maximum-payment-amount").val();
                    var maxPercent = $(this).find(".maximum-payment-percentage").val();
                    if (!empty(maxPercent)) {
                        maxAmount = Round(orderTotal * (maxPercent / 100), 2);
                        $(this).find(".maximum-payment-amount").val(maxAmount);
                    }
                    if (empty(maxAmount)) {
                        $(this).find(".payment-amount-value").removeData("maximum-value");
                    } else {
                        $(this).find(".payment-amount-value").data("maximum-value", maxAmount);
                    }
                    var paymentAmount = $(this).find(".payment-amount-value").val();
                    if (empty(paymentAmount) && empty(maxAmount)) {
                        paymentAmount = orderTotal + 100;
                    } else if (empty(paymentAmount)) {
                        paymentAmount = maxAmount;
                    } else if (!empty(maxAmount)) {
                        paymentAmount = Math.min(maxAmount, paymentAmount);
                    }
                    totalPayment += paymentAmount;
                });
                if (totalPayment < orderTotal) {
                    displayErrorMessage("Payment methods do not cover the full order amount. Add another payment method.");
                    return false;
                }

                if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.out-of-stock").length > 0) {
                    displayErrorMessage("Out of stock items must be removed from the shopping cart");
                    return false;
                }
                if ($("#shopping_cart_items_wrapper").find(".shopping-cart-item.no-online-order").length > 0) {
                    displayErrorMessage("In-store purchase only items must be removed from the shopping cart");
                    return false;
                }
                if ($("#ffl_section").length > 0 && $("#ffl_section").hasClass("ffl-required")) {
                    var fflId = $("#federal_firearms_licensee_id").val();
                    if (!$("#ffl_dealer_not_found").prop("checked") && empty(fflId)) {
                        displayErrorMessage("<?= getLanguageText("FFL Dealer") ?> is required");
                        return false;
                    }
                }
                if (!$("#shipping_information_section").hasClass("not-required")) {
                    if (empty($("#address_1").val()) || empty($("#city").val())) {
                        displayErrorMessage("A valid shipping address is required");
                        return false;
                    }
                }

				<?php if (empty($noSignature)) { ?>
                var signatureRequired = false;
                $(".signature-palette").each(function () {
                    var columnName = $(this).closest(".form-line").find("input[type=hidden]").prop("id");
                    var required = $(this).closest(".form-line").find("input[type=hidden]").data("required");
                    $(this).closest(".form-line").find("input[type=hidden]").val("");
                    if (!empty(required) && $(this).jSignature('getData', 'native').length == 0) {
                        $(this).validationEngine("showPrompt", "Required");
                        signatureRequired = true;
                    } else {
                        var data = $(this).jSignature('getData', 'svg');
                        $(this).closest(".form-line").find("input[type=hidden]").val(data[1]);
                    }
                });
                if (signatureRequired) {
                    displayErrorMessage("Signature is required");
                    return false;
                }
				<?php } ?>

                $(this).addClass("hidden");
                $("#processing_order").removeClass("hidden");
                $("select.disabled").prop("disabled", false);
                if ($("#_checkout_form").validationEngine("validate")) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_order&cart_total=" + $("#cart_total").val(), $("#_checkout_form").serialize(), function (returnArray) {
                        $("select.disabled").prop("disabled", true);
                        if ("response" in returnArray) {
                            $("#_shopping_cart_wrapper").html(returnArray['response']);
                        } else {
                            if ("recalculate" in returnArray) {
                                getShoppingCartItems();
                            }
                            $("#finalize_order").removeClass("hidden");
                            $("#processing_order").addClass("hidden");
                        }
                        getShoppingCartItemCount();
                    });
                } else {
                    $(this).removeClass("hidden");
                    $("#processing_order").addClass("hidden");
                }
                return false;
            });
			<?php
			$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
			if (!empty($fflRequiredProductTagId)) {
			?>
            $("#postal_code").change(function () {
                if (!empty($(this).val())) {
                    getFFLDealers();
                }
            });
            $(document).on("click", ".ffl-dealer", function () {
                if ($(this).hasClass("restricted")) {
                    return false;
                }
                var fflId = $(this).data("federal_firearms_licensee_id");
                $("#federal_firearms_licensee_id").val(fflId).trigger("change");
                $("#selected_ffl_dealer").html(fflDealers[fflId]);
                $("#ffl_dealer_not_found").prop("checked", false);
				<?php if ($noShippingRequired) { ?>
                getFFLTaxCharge();
				<?php } ?>
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_ffl_information", { federal_firearms_licensee_id: $("#federal_firearms_licensee_id").val() }, function (returnArray) {
                    if ("dealer_terms_conditions" in returnArray) {
                        $("#_dealer_terms_conditions_wrapper").html(returnArray['dealer_terms_conditions']);
                        $("#_dealer_terms_conditions_row").removeClass("hidden");
                    } else {
                        $("#_dealer_terms_conditions_row").addClass("hidden");
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
        </script>
		<?php
	}

	function mainContent() {
		$_SESSION['form_displayed'] = date("U");
		saveSessionData();
		$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
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

		$checkoutSectionNumber = 0;
		?>
        <div id="_shopping_cart_wrapper">

            <form id="_checkout_form" enctype="multipart/form-data" method='post'>
                <div class="checkout-section">
                    <h1>Shopping Cart</h1>
					<?= $this->iPageData['content'] ?>
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
                        </tbody>
                    </table>

                    <div id="totals_wrapper">
                        <div><input type="text" tabindex="10" id="add_product" placeholder="Add Product Code or UPC"></div>
						<?php
						$resultSet = executeQuery("select count(*) from promotions where client_id = ? and inactive = 0 and " .
							"start_date <= current_date and (expiration_date is null or expiration_date >= current_date)", $GLOBALS['gClientId']);
						$promotionCount = 0;
						if ($row = getNextRow($resultSet)) {
							$promotionCount = $row['count(*)'];
						}
						if ($promotionCount > 0) {
							?>
                            <div><input type="text" tabindex="10" id="promotion_code" placeholder="Promo Code"></div>
						<?php } ?>
						<?php if ($showListPrice) { ?>
                            <div id="total_savings" class="align-right">Total Savings: <span class="dollar">$</span><span class="cart-savings"></span></div>
						<?php } ?>
                        <div class="align-right">Cart Subtotal: <span class="dollar">$</span><span class="cart-total"></span></div>
                    </div> <!-- totals_wrapper -->

                    <div id="promotion_code_details"></div>

                    <p id="continue_checkout_wrapper">
                        <button id="continue_checkout" class="hidden">Checkout</button>
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

                </div> <!-- checkout-section -->

                <input type="hidden" id="valid_payment_methods" value="">
                <input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">

				<?php

				# Login message

				if (!$GLOBALS['gLoggedIn']) {
					?>
                    <div id="login_section" class="hidden checkout-section<?= ($GLOBALS['gInternalConnection'] ? " no-action-required" : "") ?>" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>">
						<?php
						if ($shoppingCart->requiresUser()) {
							$sectionText = $this->getPageTextChunk("retail_store_no_guest_checkout");
							if (empty($sectionText)) {
								$sectionText = $this->getFragment("retail_store_no_guest_checkout");
							}
							if (empty($sectionText)) {
								ob_start();
								?>
                                <p class='red-text'>The contents of this shopping cart requires a user login. Please create an account <a href='/my-account'>here</a> or log in <a href='/login'>here</a> to order these products.</p>
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
                                <p>Creating an account will allow you to track your orders, save payment methods and shipping addresses, and gain access to special user discounts. You can create an account during the checkout process or login here. If you don't wish to create an account, we need your email address so we can communicate with you regarding this order.</p>
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
                                    <p class='error-message'></p>
                                    <p><input type='text' id='login_user_name' name='login_user_name' size='25' maxlength='40' class='lowercase code-value allow-dash' placeholder="Username" value=""/></p>
                                    <p><input type='password' id='login_password' name='login_password' size='25' maxlength='60' placeholder="Password"/></p>
                                    <p>
                                        <button id="login_now_button">Login</button>
                                    </p>
                                    <p><a id='access_link' href="/login?forgot_password=true">Forgot your Username or Password?</a></p>
                                    <p id="logging_in_message"></p>
                                </div> <!-- login_now -->

                                <div id="guest_checkout">
									<?php
									echo createFormLineControl("contacts", "email_address", array("not_null" => !$GLOBALS['gInternalConnection'], "classes" => "show-next-section"));
									?>
                                    <div id="user_information">
                                        <h3>Optional: Create User Account</h3>
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
                                            <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="password-strength validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]]" type="password" size="40" maxlength="40" id="password" name="password" value="">
                                            <div class='strength-bar-div hidden' id='password_strength_bar_div'>
                                                <p class='strength-bar-label' id='password_strength_bar_label'></p>
                                                <div class='strength-bar' id='password_strength_bar'></div>
                                            </div>
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_password_again_row">
                                            <label for="password_again">Re-enter Password</label>
                                            <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[equals[password]]" type="password" size="40" maxlength="40" id="password_again" name="password_again" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                    </div> <!-- user_information -->

                                </div> <!-- guest_checkout -->

                            </div> <!-- no_logged_in_options -->

						<?php } ?>
                    </div> <!-- login_section -->
					<?php
				}

				# Shipping Address

				?>
                <div id="shipping_information_section" class="shipping-section hidden checkout-section" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>">
                    <h2>Shipping Address</h2>
					<?php
					$sectionText = $this->getPageTextChunk("retail_store_shipping_address");
					if (empty($sectionText)) {
						$sectionText = $this->getFragment("retail_store_shipping_address");
					}
					echo makeHtml($sectionText);
					$phoneNumber = Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'],'Primary');
					?>
                    <div id="shipping_address_block">
                        <div id="shipping_address_contact">
							<?= createFormLineControl("contacts", "first_name", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['first_name'])) ?>
							<?= createFormLineControl("contacts", "last_name", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['last_name'])) ?>
							<?= createFormLineControl("contacts", "business_name", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['business_name'])) ?>
							<?= createFormLineControl("orders", "attention_line", array("not_null" => false, "form_label" => "Attention")) ?>
							<?= createFormLineControl("orders", "purchase_order_number", array("not_null" => false, "form_label" => "PO Number")) ?>
							<?php
							$phoneRequired = getPreference("retail_store_phone_required");
							$phoneControls = array("not_null" => true, "form_label" => "Primary Phone Number", "initial_value" => $phoneNumber);
							if (empty($phoneRequired)) {
								$phoneControls['no_required_label'] = true;
								$phoneControls['data-conditional-required'] = "$(\"#ffl_section\").length > 0 && $(\"#ffl_section\").hasClass(\"ffl-required\")";
							}
							?>
							<?= createFormLineControl("phone_numbers", "phone_number", $phoneControls) ?>
							<?php
							if ($GLOBALS['gLoggedIn']) {
								?>
                                <div class="form-line" id="_address_id_row">
                                    <label id="address_id_label">Choose A Shipping Address</label>
                                    <select id="address_id" name="address_id" class="validate[required] show-next-section">
                                        <option value="">[Select Shipping Address]</option>
                                        <option value="0">[Use Primary Address]</option>
                                        <option value="-1">[New Address]</option>
										<?php
										$resultSet = executeQuery("select * from addresses where contact_id = ? and inactive = 0 and address_label is not null", $GLOBALS['gUserRow']['contact_id']);
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
                                <input tabindex="10" type="checkbox" id="business_address" name="business_address" value="1"><label class="checkbox-label" for="business_address">This is a business address</label>
                                <div class='clear-div'></div>
                            </div>

                        </div> <!-- shipping_address_contact -->
                        <input type="hidden" id="update_primary_address" name="update_primary_address">
                        <div id="shipping_address_wrapper" <?php if ($GLOBALS['gLoggedIn']) { ?>class="hidden"<?php } ?>>
							<?php if ($GLOBALS['gLoggedIn']) { ?>
								<?= createFormLineControl("addresses", "address_label", array("not_null" => false, "help_label" => "add a label to use the address again later")) ?>
							<?php } ?>
							<?= createFormLineControl("addresses", "address_1", array("not_null" => true, "classes" => "show-next-section")) ?>
							<?= createFormLineControl("addresses", "address_2", array("not_null" => false)) ?>
							<?= createFormLineControl("addresses", "city", array("not_null" => true)) ?>

                            <div class="form-line" id="_state_select_row">
                                <label for="state_select" class="">State</label>
                                <select tabindex="10" id="state_select" name="state_select" class='validate[required] show-next-section' data-conditional-required="$('#country_id').val() == 1000">
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

							<?= createFormLineControl("addresses", "state", array("not_null" => true, "classes" => "show-next-section")) ?>
							<?= createFormLineControl("addresses", "postal_code", array("not_null" => true)) ?>
							<?= createFormLineControl("addresses", "country_id", array("not_null" => true, "initial_value" => "1000", "classes" => "show-next-section")) ?>
                        </div> <!-- shipping_address_wrapper -->
                    </div> <!-- shipping_address_block -->
                </div> <!-- shipping_information_section -->
				<?php

				# Shipping Method

				?>
                <div id="shipping_method_section" class="shipping-section hidden checkout-section" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>">
                    <h2>Shipping</h2>

					<?php
					$sectionText = $this->getPageTextChunk("retail_store_shipping_method");
					if (empty($sectionText)) {
						$sectionText = $this->getFragment("retail_store_shipping_method");
					}
					echo makeHtml($sectionText);
					?>

                    <div class="form-line" id="_shipping_method_id_row">
                        <label for="shipping_method_id" class="">Shipping Method</label>
                        <select tabindex="10" id="shipping_method_id" name="shipping_method_id" class='validate[required] show-next-section'>
                            <option value="">[Select]</option>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <p id="calculating_shipping_methods" class="hidden">Calculating...</p>

                </div> <!-- shipping_method_section -->
				<?php

				# FFL

				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
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
                <div id="ffl_section" class="hidden checkout-section<?= (empty($federalFirearmsLicenseeId) ? "" : " no-action-required") ?>" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>">
                    <div id="ffl_selection_wrapper" data-product_tag_id="<?= $fflRequiredProductTagId ?>">
                        <h2><?= getLanguageText("FFL Selection (Required)") ?></h2>
						<?php
						$sectionText = $this->getPageTextChunk("retail_store_ffl_requirement");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_ffl_requirement");
						}
						echo makeHtml($sectionText);

						?>
                        <input type="hidden" id="federal_firearms_licensee_id" name="federal_firearms_licensee_id" class="show-next-section" value="<?= $federalFirearmsLicenseeId ?>">
                        <p><?= getLanguageText("Selected Dealer") ?>:
                        <div id="selected_ffl_dealer"><?= (empty($displayName) ? "None selected yet" : $displayName) ?></p>
                            <p id="ffl_dealer_not_found_wrapper"><input type='checkbox' class="show-next-section" id="ffl_dealer_not_found" name="ffl_dealer_not_found" value="1"><label class="checkbox-label" for="ffl_dealer_not_found"><?= getLanguageText("I can't find my dealer") ?></label></p>
                            <p id="ffl_dealer_count_paragraph"><span id="ffl_dealer_count"></span> <?= getLanguageText("Dealers found within") ?> <select id="ffl_radius">
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                </select> <?= getLanguageText("miles. Choose one below.") ?></p>
                            <input type="text" placeholder="<?= getLanguageText("Search/Filter Dealers") ?>" id="ffl_dealer_filter">
                            <div id="ffl_dealers_wrapper">
                                <ul id="ffl_dealers">
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
						<?php
						$noSignature = getPreference("RETAIL_STORE_NO_SIGNATURE");
						if (empty($noSignature)) {
							$sectionText = $this->getPageTextChunk("retail_store_signature_text");
							if (empty($sectionText)) {
								$sectionText = $this->getFragment("retail_store_signature_text");
							}
							if (!empty($sectionText)) {
								echo makeHtml($sectionText);
							}
							?>
                            <div class="form-line" id="_signature_row">
                                <label for="signature" class="">Signature</label>
                                <span class="help-label">Required for FFL purchase</span>
                                <input type='hidden' name='signature' data-required='1' id='signature'>
                                <div class='signature-palette-parent'>
                                    <div id='signature_palette' tabindex="10" class='signature-palette' data-column_name='signature'></div>
                                </div>
                                <div class='clear-div'></div>
                            </div>
						<?php } ?>
                    </div> <!-- ffl_section -->
					<?php
					}

					# Additional Donation

					$designations = array();
					$resultSet = executeQuery("select * from designations where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and designation_id in (select designation_id from designation_group_links where " .
						"designation_group_id = (select designation_group_id from designation_groups where designation_group_code = 'PRODUCT_ORDER' and inactive = 0 and client_id = ?)) order by sort_order,description",
						$GLOBALS['gClientId'], $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$designations[] = $row;
					}
					if (!empty($designations)) {
						?>
                        <div id="donation_section" class="hidden checkout-section no-action-required" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>" data-payment_method_count="0">
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
                                <input type="hidden" id="designation_id" name="designation_id" value="<?= $designations[0]['designation_id'] ?>">
								<?php
							}
							?>
                            <div class="form-line" id="_donation_amount_row">
                                <label for="donation_amount" class="">Donation <?= (count($designations) > 1 ? "Amount" : " for " . htmlText($designations[0]['description'])) ?> (US $)</label>
                                <input tabindex="10" type="text" id="donation_amount" name="donation_amount" class="align-right validate[custom[number]]" data-decimal-places="2">
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
                                        <button class="round-up-donation" data-round_amount="1" id="round_up_1">Round up $1</button>
                                        <button class="round-up-donation" data-round_amount="5" id="round_up_5">Round up $5</button>
                                        <button class="round-up-donation" data-round_amount="10" id="round_up_10">Round up $10</button>
                                    </p>
									<?php
									$sectionText = ob_get_clean();
								}
								echo makeHtml($sectionText);
								?>
                            </div> <!-- round_up_buttons -->
                        </div> <!-- donation_section -->
						<?php
					}

					# Payment Methods

					?>
                    <div id="payment_method_section" class="hidden checkout-section" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>">
                        <h2>Payment</h2>
                        <p class="hidden" id="_no_payment_required">No Payment Required</p>
                        <div id="payment_information_wrapper">
							<?php
							$sectionText = $this->getPageTextChunk("retail_store_payment_methods");
							if (empty($sectionText)) {
								$sectionText = $this->getFragment("retail_store_payment_methods");
							}
							if (empty($sectionText)) {
								$sectionText = "<p>If you want to add more than one payment method, you can select one to be <em><strong>primary</strong></em> and use the other to cover a specific Dollar Amount.</p>";
							}
							echo makeHtml($sectionText);
							?>
                            <table id="payment_methods_list"<?= (empty($onlyOnePayment) ? "" : " class='hidden'") ?>>
                            </table>
							<?php if (empty($onlyOnePayment)) { ?>
                                <p>
                                    <button id="add_payment_method" class="hidden">Add Another Payment Method</button>
                                </p>
							<?php } ?>
                            <input type="hidden" id="new_payment" value="1">
                            <input type="hidden" id="payment_method_number" value="1">
                            <input type="hidden" id="next_payment_method_number" name="next_payment_method_number" value="2">
                            <p id="payment_error" class="error-message"></p>
                            <div id="payment_method_wrapper">
                                <div id="payment_information">

									<?php
									$validAccounts = array();
									$resultSet = executeQuery("select *,(select payment_method_type_id from payment_methods where payment_method_id = accounts.payment_method_id) payment_method_type_id from accounts where contact_id = ? and inactive = 0", $GLOBALS['gUserRow']['contact_id']);
									while ($row = getNextRow($resultSet)) {
										$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $row['payment_method_type_id']);
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
                                            <select tabindex="10" id="account_id" name="account_id" class="show-next-section">
                                                <option value="">[New Account]</option>
												<?php
												foreach ($validAccounts as $row) {
													?>
                                                    <option value="<?= $row['account_id'] ?>" data-payment_method_type_code="<?= $paymentMethodTypeCode ?>" data-payment_method_id="<?= $row['payment_method_id'] ?>"><?= htmlText((empty($row['account_label']) ? getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']) . "-" . substr($row['account_number'], -4) : $row['account_label'])) ?></option>
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
										"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
										(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
										"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
										"(select payment_method_type_id from payment_method_types where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and " .
										"client_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
									while ($row = getNextRow($resultSet)) {
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
                                            <select tabindex="10" class="validate[required] show-next-section" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
                                                <option value="">[Select]</option>
												<?php
												foreach ($paymentMethodArray as $row) {
													?>
                                                    <option value="<?= $row['payment_method_id'] ?>" data-maximum_payment_percentage="<?= $row['percentage'] ?>" data-maximum_payment_amount="0" data-flat_rate="<?= $row['flat_rate'] ?>" data-fee_percent="<?= $row['fee_percent'] ?>" data-address_required="<?= (empty($row['no_address_required']) ? "1" : "") ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
													<?php
												}
												?>
                                            </select>
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="payment-method-fields hidden" id="payment_method_credit_card">
                                            <div class="form-line" id="_account_number_row">
                                                <label for="account_number" class="">Card Number</label>
                                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
                                                <div id="payment_logos">
													<?php
													foreach ($paymentLogos as $paymentMethodId => $imageId) {
														?>
                                                        <img alt="payment method logo" id="payment_method_logo_<?= strtolower($paymentMethodId) ?>" class="payment-method-logo" src="<?= getImageFilename($imageId) ?>">
														<?php
													}
													?>
                                                </div>
                                                <div class='clear-div'></div>
                                            </div>

                                            <div class="form-line" id="_expiration_month_row">
                                                <label for="expiration_month" class="">Expiration Date</label>
                                                <select tabindex="10" class="expiration-date validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_month" name="expiration_month">
                                                    <option value="">[Month]</option>
													<?php
													for ($x = 1; $x <= 12; $x++) {
														?>
                                                        <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
														<?php
													}
													?>
                                                </select>
                                                <select tabindex="10" class="expiration-date validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_year" name="expiration_year">
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
                                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
                                                <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvvnumber.jpg" alt="CVV Code"></a>
                                                <div class='clear-div'></div>
                                            </div>
                                        </div> <!-- payment_method_credit_card -->

                                        <div class="payment-method-fields hidden" id="payment_method_bank_account">
                                            <div class="form-line" id="_routing_number_row">
                                                <label for="routing_number" class="">Bank Routing Number</label>
                                                <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                                                <div class='clear-div'></div>
                                            </div>

                                            <div class="form-line" id="_bank_account_number_row">
                                                <label for="bank_account_number" class="">Account Number</label>
                                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                        </div> <!-- payment_method_bank_account -->

                                        <div class="payment-method-fields hidden" id="payment_method_check">
                                            <div class="form-line" id="_reference_number_row">
                                                <label for="reference_number" class="">Check Number</label>
                                                <input tabindex="10" type="text" class="" size="20" maxlength="20" id="reference_number" name="reference_number" placeholder="Check Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                        </div> <!-- payment_method_check -->

                                        <div class="payment-method-fields hidden" id="payment_method_gift_card">
                                            <div class="form-line" id="_gift_card_number_row">
                                                <label for="gift_card_number" class="">Card Number</label>
                                                <input tabindex="10" type="text" class="gift-card-number validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="30" id="gift_card_number" name="gift_card_number" placeholder="Card Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                            <p class="gift-card-information"></p>
                                        </div> <!-- payment_method_gift_card -->

                                        <div class="payment-method-fields hidden" id="payment_method_loan">
                                            <div class="form-line" id="_loan_row">
                                                <label for="loan_number" class="">Loan Number</label>
                                                <input tabindex="10" type="text" class="validate[required] uppercase" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="30" id="loan_number" name="loan_number" placeholder="Loan Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                        </div> <!-- payment_method_loan -->

                                        <div class="payment-method-fields hidden" id="payment_method_lease">
                                            <div class="form-line" id="_lease_row">
                                                <label for="lease_number" class="">Lease Number</label>
                                                <input tabindex="10" type="text" class="validate[required] uppercase" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="30" id="lease_number" name="lease_number" placeholder="Lease Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                        </div> <!-- payment_method_lease -->

										<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
                                            <div class="form-line" id="_account_label_row">
                                                <label for="account_label" class="">Account Nickname</label>
                                                <span class="help-label">to use this account again in the future</span>
                                                <input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                                                <div class='clear-div'></div>
                                            </div>
										<?php } ?>
                                    </div> <!-- new-account -->
                                </div> <!-- payment_information -->

                                <div id="payment_address" class="new-account">
                                    <div class="form-line hidden" id="_same_address_row">
                                        <label class=""></label>
                                        <input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" value="1"><label class="checkbox-label" for="same_address">Billing address is same as shipping</label>
                                        <div class='clear-div'></div>
                                    </div>

                                    <div id="_billing_address" class="hidden">

                                        <div class="form-line" id="_billing_first_name_row">
                                            <label for="billing_first_name" class="required-label">First Name</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_last_name_row">
                                            <label for="billing_last_name" class="required-label">Last Name</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_business_name_row">
                                            <label for="billing_business_name">Business Name</label>
                                            <input tabindex="10" type="text" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_address_1_row">
                                            <label for="billing_address_1" class="required-label">Address</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
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
                                            <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000">
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
                                            <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
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
                                    </div> <!-- billing_address -->
                                </div> <!-- payment_address -->
                            </div> <!-- payment_method_wrapper -->
                        </div> <!-- payment_information_wrapper -->
                    </div> <!-- payment_method_section -->
					<?php

					# Finalize and Place Order

					?>
                    <div id="finalize_section" class="hidden checkout-section" data-checkout_section_number="<?= ++$checkoutSectionNumber ?>">
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
						$sectionText = $this->getPageTextChunk("retail_store_gift");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_gift");
						}
						echo makeHtml($sectionText);
						?>
                        <div class="shipping-section form-line" id="_gift_order_row">
                            <input type="checkbox" id="gift_order" name="gift_order" value="1"><label for="gift_order" class="checkbox-label">This order is a gift</label>
                            <div class='clear-div'></div>
                        </div>
                        <div class="form-line hidden" id="_gift_text_row">
                            <label for="gift_text">Message to be included on packing slip</label>
                            <textarea id="gift_text" name="gift_text"></textarea>
                            <div class='clear-div'></div>
                        </div>
						<?php
						$sectionText = $this->getPageTextChunk("retail_store_terms_conditions");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_terms_conditions");
						}
						if (!empty($sectionText)) {
							?>
                            <div class="form-line" id="_terms_conditions_row">
                                <input type="checkbox" id="terms_conditions" name="terms_conditions" class="validate[required]" value="1"><label for="terms_conditions" class="checkbox-label">I agree to the Terms and Conditions.</label> <a href='#' id="view_terms_conditions" class="clickable">Click here to view store Terms and Conditions.</a>
                                <div class='clear-div'></div>
                            </div>
							<?php
							echo "<div class='dialog-box' id='_terms_conditions_dialog'><div id='_terms_conditions_wrapper'>" . makeHtml($sectionText) . "</div></div>";
						}
						?>
                        <div class="form-line hidden" id="_dealer_terms_conditions_row">
                            <input type="checkbox" id="dealer_terms_conditions" name="dealer_terms_conditions" class="validate[required]" value="1"><label for="dealer_terms_conditions" class="checkbox-label">I agree to the FFL Dealer's Terms and Conditions.</label> <a href='#' id="view_dealer_terms_conditions" class="clickable">Click here to view FFL Dealer's Terms and Conditions.</a>
                            <div class='clear-div'></div>
                        </div>
                        <div class='dialog-box' id='_dealer_terms_conditions_dialog'>
                            <div id='_dealer_terms_conditions_wrapper'></div>
                        </div>

                        <input type="hidden" id="tax_charge" name="tax_charge" class="tax-charge" value="0">
                        <input type="hidden" id="shipping_charge" name="shipping_charge" class="shipping-charge" value="0">
                        <input type="hidden" id="handling_charge" name="handling_charge" class="handling-charge" value="0">
                        <input type="hidden" id="cart_total_quantity" name="cart_total_quantity" class="cart-total-quantity" value="0">
                        <input type="hidden" id="cart_total" name="cart_total" class="cart-total" value="0">
                        <input type="hidden" id="discount_amount" name="discount_amount" value="0">
                        <input type="hidden" id="discount_percent" name="discount_percent" value="0">
                        <input type="hidden" id="order_total" name="order_total" class="order-total" value="0">
						<?php
						$sectionText = $this->getPageTextChunk("retail_store_finalize_order");
						if (empty($sectionText)) {
							$sectionText = $this->getFragment("retail_store_finalize_order");
						}
						if (empty($sectionText)) {
							ob_start();
							?>
                            <h3>Order Summary</h3>
                            <table id="order_summary" class='grid-table'>
                                <tr>
                                    <td class="order-summary-description">Cart Items</td>
                                    <td class='align-right'><span class='cart-total-quantity'></span> product<span class='cart-total-quantity-plural'>s</span></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description">Cart Total</td>
                                    <td class='align-right cart-total'></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description">Tax</td>
                                    <td class="tax-charge align-right"></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description">Shipping</td>
                                    <td class="shipping-charge align-right"></td>
                                </tr>
                                <tr>
                                    <td class="order-summary-description">Handling</td>
                                    <td class="handling-charge align-right"></td>
                                </tr>
								<?php if (!empty($designations)) { ?>
                                    <tr>
                                        <td class="order-summary-description">Donation</td>
                                        <td class="donation-amount align-right"></td>
                                    </tr>
								<?php } ?>
                                <tr id='order_summary_discount_wrapper'>
                                    <td class="order-summary-description">Discount</td>
                                    <td class="discount-amount align-right"></td>
                                </tr>
                                <tr id="order_summary_total">
                                    <td>Order Total</td>
                                    <td class="order-total align-right"></td>
                                </tr>
                            </table>
							<?php if ($GLOBALS['gInternalConnection']) { ?>

                                <div class="form-line" id="_tax_exempt_number_row">
                                    <label for="tax_exempt_number">Tax Exempt Number</label>
                                    <input type="text" id="tax_exempt_id" name="tax_exempt_id" value="<?= CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "TAX_EXEMPT_ID") ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_tax_exempt_row">
                                    <input type="checkbox" id="tax_exempt" name="tax_exempt" value="1"><label for="tax_exempt" class="checkbox-label">Tax Exempt</label>
                                    <div class='clear-div'></div>
                                </div>
							<?php } ?>
                            %mailing_lists%
                            <p>By placing your order you are stating that you agree to all of the terms outlined in our store policies.</p>
							<?php
							$sectionText = ob_get_clean();
						}
						ob_start();
						$resultSet = executeQuery("select * from mailing_lists where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 0) {
							$optInTitle = $this->getPageTextChunk("opt_in_title");
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
                                    <input type="checkbox" id="mailing_list_id_<?= $row['mailing_list_id'] ?>" name="mailing_list_id_<?= $row['mailing_list_id'] ?>" value="1"<?= (empty($optedIn) ? "" : "checked='checked'") ?>><label for="mailing_list_id_<?= $row['mailing_list_id'] ?>" class="checkbox-label"><?= htmlText($row['description']) ?></label>
                                    <div class='clear-div'></div>
                                </div>
								<?php
							}
						}
						$mailingLists = ob_get_clean();
						$sectionText = str_replace("%mailing_lists%", $mailingLists, $sectionText);
						echo makeHtml($sectionText);
						$shoppingCartItem = $this->getPageTextChunk("retail_store_processing_order");
						if (empty($shoppingCartItem)) {
							$shoppingCartItem = $this->getFragment("retail_store_processing_order");
						}
						if (empty($shoppingCartItem)) {
							$shoppingCartItem = "Order being processed and created. DO NOT hit the back button.";
						}
						echo "<div id='processing_order' class='hidden'>" . makeHtml($shoppingCartItem) . "</div>";
						?>
                        <p class="error-message"></p>
                        <p>
                            <button id="finalize_order">Place My Order</button>
                        </p>
                    </div> <!-- finalize_section -->
            </form>
        </div> <!-- shopping_cart_wrapper -->
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function jqueryTemplates() {
		$showListPrice = (getFieldFromId("price_calculation_type_id", "pricing_structures", "pricing_structure_code", "DEFAULT") == getFieldFromId("price_calculation_type_id", "price_calculation_types", "price_calculation_type_code", "DISCOUNT"));

		$shoppingCartItem = $this->getPageTextChunk("retail_store_shopping_cart_item");
		if (empty($shoppingCartItem)) {
			$shoppingCartItem = $this->getFragment("retail_store_shopping_cart_item");
		}
		if (empty($shoppingCartItem)) {
			ob_start();
			?>
            <table class="hidden" id="shopping_cart_item_template">
                <tbody id="_shopping_cart_item_block">
                <tr class="shopping-cart-item %other_classes%" id="shopping_cart_item_%shopping_cart_item_id%" data-shopping_cart_item_id="%shopping_cart_item_id%" data-product_id="%product_id%" data-product_tag_ids="%product_tag_ids%">
                    <td class="align-center"><a href="%image_url%" class="pretty-photo"><img %image_src%="%small_image_url%"></a></td>
                    <td class="product-description"><a href="/product-details?id=%product_id%">%description%</a><span class="out-of-stock-notice">Out Of Stock</span><span class="no-online-order-notice">In-store purchase only</span></td>
					<?php if ($showListPrice) { ?>
                        <td class="discount-column align-right"><span class="product-list-price">%list_price%</span></td>
                        <td class="discount-column align-right"><span class="product-discount">%discount%</span><span class="product-savings hidden">%savings%</span></td>
					<?php } ?>
                    <td class="align-right"><span class="original-sale-price">%original_sale_price%</span><span class="dollar">$</span><span class="product-sale-price">%sale_price%</span></td>
                    <td class="align-center product-quantity-wrapper"><span class="fa fa-minus shopping-cart-item-decrease-quantity" data-amount="-1"></span><span class="product-quantity" data-cart_maximum="%cart_maximum%" data-cart_minimum="%cart_minimum%">%quantity%</span><span class="fa fa-plus shopping-cart-item-increase-quantity" data-amount="1"></span></td>
                    <td class="align-right"><span class="dollar">$</span><span class="product-total"></span><input class="cart-item-additional-charges" type="hidden" name="shopping_cart_item_additional_charges_%shopping_cart_item_id%" id="shopping_cart_item_additional_charges_%shopping_cart_item_id%"></td>
                    <td class="controls align-center"><span class="fa fa-times remove-item" data-product_id="%product_id%"></span></td>
                </tr>
                <tr class="item-custom-fields %custom_field_classes%" id="custom_fields_%shopping_cart_item_id%">
                    <td colspan="6">%custom_fields%</td>
                </tr>
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
                    <input type="hidden" class="payment-method-number" id="payment_method_number_%payment_method_number%" name="payment_method_number_%payment_method_number%" value="%payment_method_number%">
                    <input type="hidden" id="account_id_%payment_method_number%" name="account_id_%payment_method_number%">
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
                    <span class="fa fa-times"></span>Remove
                </td>
                <td class="edit-payment"><span class="fa fa-edit"></span>Edit</td>
                <td class="primary-payment hidden"><input type="checkbox" checked class="primary-payment-method" id="primary_payment_method_%payment_method_number%" name="primary_payment_method_%payment_method_number%" value="1"><label class="checkbox-label" for="primary_payment_method_%payment_method_number%">Make Primary</label></td>
                <td class="payment-amount hidden"><input type="text" class="payment-amount-value align-right validate[required,custom[number],min[.01],max[999999]]" data-decimal-places="2" id="payment_amount_%payment_method_number%" name="payment_amount_%payment_method_number%" placeholder="Balance"></td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #not_logged_in_options {
                display: flex;
            }
            #login_now {
                padding: 20px 40px;
                text-align: center;
                flex: 1 1 50%;
            }
            #login_now p {
                padding: 0;
                margin: 0 auto;
                margin-bottom: 10px;
            }
            #login_now input {
                max-width: 250px;
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
            }
            .checkout-section {
                padding: 10px 20px 20px 20px;
                margin: 10px auto 20px auto;
                background-color: rgb(255, 255, 255);
            }
            #shipping_address_block {
                display: flex;
            }
            #shipping_address_block div {
                flex: 1 1 50%;
            }
            #payment_method_wrapper {
                display: flex;
            }
            #payment_method_wrapper div {
                flex: 1 1 50%;
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
            #_term_conditions_wrapper {
                max-height: 80vh;
                height: 800px;
                overflow: scroll;
            }
            #finalize_section {
                margin: 40px 0;
            }
            #finalize_section p {
                font-size: 1.2rem;
            }
            #order_summary {
                margin: 10px 0 20px 0;
            }
            #order_summary tr td:first-child {
                padding-right: 40px;
            }
            #round_up_buttons button {
                margin-right: 10px;
            }
            .form-line select.expiration-date {
                width: 40%;
                max-width: 200px;
            }
            #payment_methods_list {
                margin: 10px 0 20px 0;
            }
            #payment_methods_list td {
                padding: 5px 10px;
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
                font-size: 1.1rem;
                padding: 4px 10px;
                width: 60px;
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

            #order_summary td {
                font-size: 1.2rem;
            }
            #order_summary td.order-summary-description {
                background-color: rgb(220, 220, 220);
                padding-right: 40px;
            }
            #order_summary td.align-right {
                padding-left: 40px;
            }
            #order_summary_total {
                border-top: 2px solid rgb(40, 40, 40);
            }
            #order_summary_total td {
                font-size: 1.4rem;
                font-weight: 900;
            }
            #cvv_code {
                width: 180px;
            }
            #cvv_image {
                height: 60px;
                top: 10px;
                position: absolute;
                left: 220px;
            }
            .checkout-section {
                padding: 20px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
            }
            #finalize_order {
                margin: 20px 0;
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

            @media (max-width: 800px) {
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
                span.product-quantity {
                    padding: 5px 10px;
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
                #finalize_section button {
                    width: 100%;
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
                #order_summary {
                    width: 100%;
                }
                #add_payment_method {
                    width: 100%;
                }
                #payment_methods_list {
                    display: flex;
                    flex-direction: column;
                }
                tr.payment-list-item {
                    display: flex;
                    flex-direction: column;
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
}

$pageObject = new ThisPage();
$pageObject->displayPage();
