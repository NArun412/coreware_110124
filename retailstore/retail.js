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

let shippingMethodCountryId = false;
let shippingMethodState = false;
let shippingMethodPostalCode = false;
let priceCalculationLog = false;
let relatedProductsCount = 0;
let detailIntervalTimer = false;
let savedProductDetails = {};
let neverOutOfStock = false;
let forceTile = true;

let manufacturerNames = false;
let displaySearchText = "";
let postVariables = false;
let productFieldNames = false;
let productKeyLookup = false;
let productResults = false;
let productDetailData = false;
let facetDescriptions = false;
let constraints = false;
let resultCount = false;
let queryTime = false;
let pageGroupingData = [];
let productCategoryGroupIds = false;
let emptyImageFilename = "/getimage.php?code=no_product_image";
let shoppingCartProductIds = false;
let wishListProductIds = false;
let productTagCodes = false;
let taggedProductsFunctions = [];
let lastSortOrder = "";
let sortedIndexes = {};
let sortDirection = 1;
let sidebarReductionNeeded = true;
let cdnDomain = "";
let retailAgreements = false;
let orderedItems = false;
let credovaUserName = false;
let credovaTestEnvironment = true;
let availabilityTexts = null;
let filterTextMinimumCount = 6;
let clickedFilterId = "";
let windowScroll = 0;
let userDefaultLocationId = "";
let inStorePurchaseOnlyText = "In-store purchase only";
let orderItemCustomFieldValues = {};
let siteSearchPageLink = false;

let priceRanges = [ { minimum_cost: 0, maximum_cost: 99.99, label: "Under $100", count: 0 },
    { minimum_cost: 100, maximum_cost: 199.99, label: "$100-200", count: 0 },
    { minimum_cost: 200, maximum_cost: 499.99, label: "$200-500", count: 0 },
    { minimum_cost: 500, maximum_cost: 999.99, label: "$500-1000", count: 0 },
    { minimum_cost: 1000, maximum_cost: 99999999.99, label: "Over $1000", count: 0 },
];

function checkForTaggedProductFunctions() {
    if (taggedProductsFunctions.length > 0) {
        const functionName = taggedProductsFunctions.shift();
        if (typeof window[functionName] == "function") {
            window[functionName]();
        }
    } else {
        if (typeof afterTaggedProductsLoaded === "function") {
            // noinspection JSUnresolvedFunction
            afterTaggedProductsLoaded();
            setTimeout(function () {
                $(".product-tag-code-class-3").find(".catalog-item-credova-financing").remove();
            }, 500);
        }
    }
}

$(function () {
    setTimeout(function () {
        checkForTaggedProductFunctions();
    }, 500);
    setTimeout(function () {
        getCredovaUserName();
    }, 500);
});

function getCredovaUserName() {
    if (!empty(credovaUserName)) {
        return;
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_credova_user_name", function (returnArray) {
        if ("credova_user_name" in returnArray && !empty(returnArray['credova_user_name'])) {
            credovaUserName = returnArray['credova_user_name'];
            credovaTestEnvironment = returnArray['credova_test_environment'];
            setTimeout(function() {
                loadCredova();
            },500);
        }
    });
}

function loadCredova() {
    if (empty(credovaUserName)) {
        return;
    }
    $.ajaxSetup({
        cache: true
    });
    $.getScript('https://plugin.credova.com/plugin.min.js', function () {
        $.ajaxSetup({
            cache: false
        });
        // noinspection JSUnresolvedVariable
        CRDV.plugin.config({
            environment: (credovaTestEnvironment ? CRDV.Environment.Sandbox : CRDV.Environment.Production),
            store: credovaUserName
        });
        setTimeout(function () {
            showCredovaMessages();
        }, 500);
        // noinspection JSUnresolvedVariable
        CRDV.plugin.addEventListener(function (event) {
            // noinspection JSUnresolvedVariable
            if (event.eventName === CRDV.EVENT_USER_WAS_APPROVED) {
                // noinspection JSUnresolvedVariable
                const publicId = event.eventArgs.publicId[0];
                const $publicIdentifier = $("input#public_identifier");
                const $paymentMethodId = $("#payment_method_id");
                if ($publicIdentifier.length > 0) {
                    $publicIdentifier.val(publicId);
                    $(".checkout-credova-button").addClass("hidden");
                }
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_credova", { public_identifier: publicId }, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        displayInfoMessage("Select 'Credova' as the payment method and finalize your purchase.");
                        if (empty($paymentMethodId.val())) {
                            let credovaPaymentMethodId = "";
                            $paymentMethodId.find("option").each(function () {
                                const paymentMethodCode = $(this).data("payment_method_code");
                                if (paymentMethodCode === "CREDOVA") {
                                    credovaPaymentMethodId = $(this).val();
                                    return false;
                                }
                            });
                            $paymentMethodId.val(credovaPaymentMethodId).trigger("change");
                        }
                    }
                });
            }
        });
    });
}

function getShoppingCartItems(shoppingCartCode, contactId) {
    if (empty(shoppingCartCode)) {
        shoppingCartCode = "";
    }
    if (empty(contactId)) {
        contactId = "";
    }
    $("#continue_checkout").addClass("hidden");
    $("#quick_checkout").addClass("hidden");
    // noinspection JSUnresolvedVariable
    if (typeof beforeGetShoppingCartItems === "function") {
        // noinspection JSUnresolvedFunction
        beforeGetShoppingCartItems();
    }
    orderItemCustomFieldValues = {};
    $(".order-item-custom-field").each(function () {
        if ($(this).is("input[type=checkbox]")) {
            orderItemCustomFieldValues[$(this).attr("id")] = ($(this).prop("checked") ? "1" : "0");
        } else if (!empty($(this).val())) {
            orderItemCustomFieldValues[$(this).attr("id")] = $(this).val();
        }
    });
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shopping_cart_items&shopping_cart_code=" + shoppingCartCode + "&contact_id=" + contactId, function (returnArray) {
        if ("requires_user" in returnArray) {
            $("#continue_checkout_wrapper").replaceWith(returnArray['requires_user']);
            $("#finalize_section").remove();
            $("#_guest_form_wrapper").addClass("hidden");
        } else {
            $("#_guest_form_wrapper").removeClass("hidden");
        }
        if (!("shopping_cart_items" in returnArray)) {
            return;
        }
        if ("total_savings" in returnArray) {
            $("#total_savings").val(returnArray['total_savings']);
        }
        if ("retail_agreements" in returnArray) {
            retailAgreements = returnArray['retail_agreements'];
        }
        if ("promotion_code" in returnArray) {
            $("#promotion_id").val(returnArray['promotion_id']);
            $("#promotion_code").val(returnArray['promotion_code']);
            $("#promotion_code_description").html(returnArray['promotion_code_description']);
            $("#promotion_code_details").html(returnArray['promotion_code_details']);
            $(".promotion-code").html(returnArray['promotion_code']);
            $("#_promotion_code_wrapper").addClass("hidden");
            if (!empty(returnArray['promotion_code'])) {
                $("#_promotion_message").addClass("hidden");
                $("#_promotion_applied_message").removeClass("hidden");
                $("#added_promotion_code").removeClass("hidden");
                $("#add_promotion_code").addClass("hidden");
            } else {
                $("#_promotion_message").removeClass("hidden");
                $("#_promotion_applied_message").addClass("hidden");
                $("#added_promotion_code").addClass("hidden");
                $("#add_promotion_code").removeClass("hidden");
            }
            if (empty(returnArray['promotion_code_details'])) {
                $("#promotion_code_details").addClass("hidden");
                $("#show_promotion_code_details").addClass("hidden");
            } else {
                $("#promotion_code_details").removeClass("hidden");
                $("#show_promotion_code_details").removeClass("hidden");
            }
        }
        const $shoppingCartItemsWrapper = $("#shopping_cart_items_wrapper");
        const $shoppingCartItemsBlock = $("#_shopping_cart_item_block");
        if ($shoppingCartItemsBlock.length === 0 || $shoppingCartItemsWrapper.length === 0) {
            return;
        }
        if (empty(returnArray['shipping_required'])) {
            $(".shipping-section").addClass("not-required").addClass("no-action-required").addClass("hidden");
            if (!empty($("#postal_code").val() && returnArray['shopping_cart_items'].length > 0)) {
                getFFLDealers();
            }
        } else {
            $(".shipping-section").removeClass("not-required").removeClass("no-action-required").removeClass("hidden");
            if ($(".section-chooser-option.shipping-section.shipping-information-section-chooser.selected").length == 0) {
                $("#shipping_information_section").addClass("hidden");
            }
        }
        if (!empty(returnArray['pickup_locations'])) {
            $("#shipping_method_section").removeClass("hidden").removeClass("not-required");
        }
        $shoppingCartItemsWrapper.html("");
        if ("valid_payment_methods" in returnArray) {
            $("#valid_payment_methods").val(returnArray['valid_payment_methods']);
        } else {
            $("#valid_payment_methods").val("");
        }
        const $templates = $("#_templates");
        if ($templates.find("#_order_item_templates").length > 0) {
            $templates.find("#_order_item_templates").html("");
        } else {
            $templates.append("<div id='_order_item_templates'></div>");
        }
        if ("jquery_templates" in returnArray) {
            $templates.find("#_order_item_templates").append(returnArray['jquery_templates']);
        }
        let shoppingCartItems = returnArray['shopping_cart_items'];
        if (typeof massageShoppingCartItems == "function") {
            shoppingCartItems = massageShoppingCartItems(shoppingCartItems);
        }
        for (let j in shoppingCartItems) {
            if (!empty(shoppingCartItems[j]['order_upsell_product'])) {
                continue;
            }
            if (!("product_restrictions" in shoppingCartItems[j])) {
                shoppingCartItems[j]['product_restrictions'] = "";
            }
            const productId = shoppingCartItems[j]['product_id'];
            let itemBlock = $shoppingCartItemsBlock.html().replace(new RegExp("%image_src%", 'ig'), "src");

            for (let i in shoppingCartItems[j]) {
                if (i == "other_classes") {
                    continue;
                }
                itemBlock = itemBlock.replace(new RegExp("%" + i + "%", 'ig'), shoppingCartItems[j][i]);
            }
            let otherClasses = ("other_classes" in shoppingCartItems[j] ? shoppingCartItems[j]['other_classes'] : "");
            if ("inventory_quantity" in shoppingCartItems[j]) {
                if (shoppingCartItems[j]['inventory_quantity'] <= 0) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "out-of-stock";
                }
            }
            if ("no_online_order" in shoppingCartItems[j]) {
                if (!empty(shoppingCartItems[j]['no_online_order'])) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "no-online-order";
                }
            }
            if ("recurring_payment" in shoppingCartItems[j]) {
                if (!empty(shoppingCartItems[j]['recurring_payment'])) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "recurring-payment";
                }
            }
            const otherClassesArray = otherClasses.split(" ");
            for (let i in otherClassesArray) {
                $("." + otherClassesArray[i] + "-element").removeClass("hidden");
            }

            itemBlock = itemBlock.replace(new RegExp("%other_classes%", 'ig'), otherClasses);

            $shoppingCartItemsWrapper.append(itemBlock);

            const $addToCart = $(".add-to-cart-" + productId);
            if ($addToCart.length > 0) {
                $addToCart.each(function () {
                    const inText = $(this).data("in_text");
                    if (!empty(inText)) {
                        $(this).html(inText);
                    }
                });
            }
        }
        if (shoppingCartItems.length > 0) {
            $("#continue_checkout").removeClass("hidden");
            $("#quick_checkout").removeClass("hidden");
        } else {
            $shoppingCartItemsWrapper.html("<tr><td colspan='8'><p id='empty_shopping_cart'>Your cart is currently empty</p></td></tr>");
            $("#continue_checkout").addClass("hidden");
            $("#quick_checkout").addClass("hidden");
        }
        if ("shopping_cart_item_count" in returnArray) {
            $(".shopping-cart-item-count").html(returnArray['shopping_cart_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
        if ("loyalty_points_awarded" in returnArray) {
            $(".loyalty-points-awarded").html(returnArray['loyalty_points_awarded']);
        }
        $shoppingCartItemsWrapper.find("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
            social_tools: false,
            default_height: 480,
            default_width: 854,
            deeplinking: false
        });
        if ($("#_order_upsell_products").length > 0) {
            $("#_order_upsell_products").html("");
            if ("order_upsell_products" in returnArray) {
                for (var i in returnArray['order_upsell_products']) {
                    let orderUpsellProductTemplate = $("#_order_upsell_product_template").html();
                    for (var j in returnArray['order_upsell_products'][i]) {
                        orderUpsellProductTemplate = orderUpsellProductTemplate.replace(new RegExp("%" + j + "%", 'ig'), returnArray['order_upsell_products'][i][j]);
                    }
                    $("#_order_upsell_products").append(orderUpsellProductTemplate);
                }
            }
            for (let j in shoppingCartItems) {
                if (empty(shoppingCartItems[j]['order_upsell_product'])) {
                    continue;
                }
                $("#order_upsell_product_id_" + shoppingCartItems[j]['product_id']).prop("checked",true);
            }
        }
        if ("custom_field_data" in returnArray) {
            for (let i in returnArray['custom_field_data']) {
                if ($("#_" + i + "_table").is(".editable-list")) {
                    for (let j in returnArray['custom_field_data'][i]) {
                        addEditableListRow(i, returnArray['custom_field_data'][i][j]);
                    }
                } else if ($("#_" + i + "_form_list").is(".form-list")) {
                    for (let j in returnArray['custom_field_data'][i]) {
                        addFormListRow(i, returnArray['custom_field_data'][i][j]);
                    }
                }
            }
        }
        $("table.order-item-custom-field").find("input,select").addClass("order-item-custom-field");
        if ("discount_amount" in returnArray) {
            if ($("#discount_amount").is("input")) {
                $("#discount_amount").val(returnArray['discount_amount']);
            } else {
                $("#discount_amount").html(RoundFixed(-1 * returnArray['discount_amount'], 2));
            }
        } else {
            if ($("#discount_amount").is("input")) {
                $("#discount_amount").val("0.00");
            } else {
                $("#discount_amount").html("0.00");
            }
        }
        if ("discount_percent" in returnArray) {
            $("#discount_percent").val(returnArray['discount_percent']);
        } else {
            $("#discount_percent").val("0");
        }
        $.each(orderItemCustomFieldValues, function (elementId, elementValue) {
            if ($("#" + elementId).is("input[type=checkbox]")) {
                $("#" + elementId).prop("checked", !empty(elementValue));
            } else {
                $("#" + elementId).val(elementValue);
            }
        });
        calculateShoppingCartTotal();
        checkFFLRequirements();
        addRetailAgreements();
        if (typeof getItemAvailabilityTexts == "function") {
            setTimeout(function () {
                getItemAvailabilityTexts()
            }, 100);
        }
        $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
            if (!empty($(this).find(".original-sale-price").html())) {
                $(this).find(".original-sale-price").removeClass("hidden");
                $(this).find(".product-savings-wrapper").removeClass("hidden");
            }
        });
        $(".shopping-cart-item-custom-fields .datepicker").datepicker({
            onClose: function() {
                $(this).trigger("keydown");
            },
            showOn: "button",
            buttonText: "<span class='fad fa-calendar-alt'></span>",
            constrainInput: false,
            dateFormat: "mm/dd/yy",
            yearRange: "c-100:c+10"
        });
        if (typeof afterGetShoppingCartItems == "function") {
            setTimeout(function () {
                afterGetShoppingCartItems(returnArray);
            }, 100);
        }
        if (typeof coreAfterGetShoppingCartItems == "function") {
            setTimeout(function () {
                coreAfterGetShoppingCartItems(returnArray);
            }, 100);
        }
        if (!empty(credovaUserName)) {
            showCredovaMessages();
        }
    });
}

function showCredovaMessages() {
    if ("CRDV" in window) {
        $(".credova-button").html("");
        // noinspection JSUnresolvedVariable
        CRDV.plugin.inject("credova-button");
        if (typeof afterShowCredovaMessages == "function") {
            afterShowCredovaMessages();
        }
    } else {
        setTimeout(function () {
            showCredovaMessages();
        }, 500);
    }
}

function addRetailAgreements() {
    const $retailAgreements = $("#retail_agreements");
    $retailAgreements.html("");
    for (let i in retailAgreements) {
        if (empty(retailAgreements[i]['state']) || retailAgreements[i]['state'] === $("#state").val()) {
            $retailAgreements.append("<div class='form-line' id='_retail_agreement_id_" + retailAgreements[i]['retail_agreement_id'] +
                "_row'><input type='checkbox' id='retail_agreement_id_" + retailAgreements[i]['retail_agreement_id'] + "' name='retail_agreement_id_" +
                retailAgreements[i]['retail_agreement_id'] + "' class='validate[required]' value='1'><label for='retail_agreement_id_" +
                retailAgreements[i]['retail_agreement_id'] + "' class='checkbox-label'>" + retailAgreements[i]['form_label'] + "</label><div class='clear-div'></div></div>");
        }
    }
}

function checkFFLRequirements() {
    const $fflSelectWrapper = $("#ffl_selection_wrapper");
    if ($fflSelectWrapper.length > 0) {
        const fflProductTagId = $fflSelectWrapper.data("product_tag_id");
        const crProductTagId = $fflSelectWrapper.data("cr_required_product_tag_id");

        let foundFFLProduct = false;
        let allFFLProductCR = true;

        $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
            const productTagIdsString = $(this).data("product_tag_ids");
            if (!empty(productTagIdsString)) {
                const productTagIdArray = String(productTagIdsString).split(",");
                if (isInArray(fflProductTagId, productTagIdArray)) {
                    foundFFLProduct = true;
                    allFFLProductCR = allFFLProductCR && isInArray(crProductTagId, productTagIdArray);
                }
            }
        });
        const shippingState = $("#state").val();
        if (!empty(shippingState)) {
            if ($(".product-tag-ffl_required_" + shippingState.toLowerCase()).length > 0) {
                foundFFLProduct = true;
            }
        }

        const pickup = !empty($("#shipping_method_id option:selected").data("pickup")) || $("#shipping_type_pickup").prop("checked");
        const userFFLNumber = $("#user_ffl_number").val();

        const $fflSection = $("#ffl_section");
        let fflRequired = foundFFLProduct && empty(pickup) && empty(userFFLNumber);

        $("#cr_license_wrapper").addClass("hidden");
        if (fflRequired && allFFLProductCR) {
            const userHasCRLicense = ($("#user_has_cr_license").length > 0 ? $("#user_has_cr_license").val() : false);

            if (!empty(userHasCRLicense)) {
                fflRequired = false;
            } else {
                $("#cr_license_wrapper").removeClass("hidden");
            }
        }

        if (fflRequired) {
            $fflSection.addClass("ffl-required");
            $(".ffl-section").removeClass("unused-section");
            $(".ffl-section").removeClass("hidden");
            $("#ffl_section").addClass("hidden");
            if (empty($("#federal_firearms_licensee_id").val())) {
                $fflSection.removeClass("no-action-required");
            }
            $("#_gift_order_row").addClass("hidden");
            $("#_gift_text_row").addClass("hidden");
            $("#gift_order").prop("checked", false);
            $("#gift_text").val("");
            $("#signature").data("required", "1");
            let $signatureRow = $("#_signature_row");
            if ($signatureRow.length > 0 && !$signatureRow.hasClass("signature-required")) {
                $signatureRow.removeClass("hidden");
            }
            $("#federal_firearms_licensee_id").addClass("validate-hidden");
            $("#federal_firearms_licensee_id").addClass("validate[required]");
        } else {
            $fflSection.removeClass("ffl-required").addClass("no-action-required");
            $(".ffl-section").addClass("unused-section");
            $(".ffl-section").addClass("hidden");
            $("#gift_order").prop("checked", false);
            $("#_gift_order_row").removeClass("hidden");
            $("#_gift_text_row").addClass("hidden");
            let $signatureRow = $("#_signature_row");
            if ($signatureRow.length > 0 && !$signatureRow.hasClass("signature-required")) {
                $("#signature").data("required", "0");
                $signatureRow.addClass("hidden");
            } else {
                $("#signature").data("required", "1");
                $signatureRow.removeClass("hidden");
            }
            $("#federal_firearms_licensee_id").removeClass("validate-hidden");
            $("#federal_firearms_licensee_id").removeClass("validate[required]");
        }
        if (typeof afterCheckFFLRequirements == "function") {
            afterCheckFFLRequirements(fflRequired);
        }
    }
}

function calculateShoppingCartTotal() {
    let totalAmount = 0;
    let totalQuantity = 0;
    let totalSavings = 0;
    $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
        const shoppingCartItemId = $(this).data("shopping_cart_item_id");
        let salePrice = 0;
        if ($(this).find(".product-sale-price").is("input[type=text]")) {
            salePrice = parseFloat($(this).find(".product-sale-price").val().replace(/,/g, ""));
        } else {
            salePrice = parseFloat($(this).find(".product-sale-price").html().replace(/,/g, ""));
        }
        let additionalCharges = parseFloat($(this).find(".cart-item-additional-charges").val());
        if (empty(additionalCharges) || isNaN(additionalCharges)) {
            additionalCharges = 0;
        }
        let addonCharges = 0;
        $("#addons_" + shoppingCartItemId).find(".product-addon").each(function () {
            if ($(this).is("input[type=checkbox]")) {
                if ($(this).prop("checked")) {
                    let thisCharge = $(this).data("sale_price");
                    addonCharges += parseFloat(thisCharge);
                }
            } else if ($(this).is("input[type=text]")) {
                let maximumQuantity = $(this).data("maximum_quantity");
                if (isNaN(maximumQuantity) || empty(maximumQuantity) || maximumQuantity <= 0) {
                    maximumQuantity = 1;
                }
                let addonQuantity = $(this).val();
                if (isNaN(addonQuantity) || empty(addonQuantity) || addonQuantity <= 0) {
                    addonQuantity = 0;
                    $(this).val(addonQuantity);
                }
                if (parseInt(addonQuantity) > parseInt(maximumQuantity)) {
                    addonQuantity = maximumQuantity;
                    $(this).val(addonQuantity);
                }
                if (!empty($(this).val())) {
                    let thisCharge = $(this).data("sale_price");
                    addonCharges += parseFloat(thisCharge) * parseInt(addonQuantity);
                }
            } else if ($(this).is("input[type=hidden]")) {
                let addonQuantity = $(this).val();
                if (isNaN(addonQuantity) || empty(addonQuantity) || addonQuantity <= 0) {
                    addonQuantity = 0;
                    $(this).val(addonQuantity);
                }
                if (!empty($(this).val())) {
                    let thisCharge = $(this).data("sale_price");
                    addonCharges += parseFloat(thisCharge) * parseInt(addonQuantity);
                }
            } else {
                let maximumQuantity = $(this).find("option:selected").data("maximum_quantity");
                if (isNaN(maximumQuantity) || empty(maximumQuantity) || maximumQuantity <= 0) {
                    maximumQuantity = 1;
                }
                const $quantityField = $(this).closest(".form-line").find(".addon-select-quantity");
                let addonQuantity = $quantityField.val();
                if (isNaN(addonQuantity) || empty(addonQuantity) || addonQuantity <= 0) {
                    addonQuantity = 1;
                    $quantityField.val(addonQuantity);
                }
                if (parseInt(addonQuantity) > parseInt(maximumQuantity)) {
                    addonQuantity = maximumQuantity;
                    $quantityField.val(addonQuantity);
                }
                if (!empty($(this).val())) {
                    let thisCharge = $(this).find("option:selected").data("sale_price");
                    addonCharges += parseFloat(thisCharge) * parseInt(addonQuantity);
                }
            }
        });
        if (empty(addonCharges) || isNaN(addonCharges)) {
            addonCharges = 0;
        }
        const $productQuantity = $(this).find(".product-quantity");
        let quantity = 0;
        if ($productQuantity.is("input")) {
            quantity = parseInt($productQuantity.val().replace(/,/g, ""));
        } else {
            quantity = parseInt($productQuantity.html().replace(/,/g, ""));
        }
        let savings = 0;
// check product savings or compare original price
        if ($(this).find(".product-savings").length > 0 && $(this).find(".product-savings").html().length > 0) {
            savings = parseFloat($(this).find(".product-savings").html().replace(/,/g, ""));
        } else if ($(this).find(".original-sale-price").length > 0 && $(this).find(".original-sale-price").html().length > 0) {
            const originalSalePrice = parseFloat($(this).find(".original-sale-price").html().replace(/,/g, ""));
            savings = originalSalePrice - salePrice;
        }
        let thisTotal = Round((salePrice + additionalCharges + addonCharges) * quantity, 2);
        $(this).find(".product-total").html(RoundFixed(thisTotal, 2));
        totalAmount = Round(totalAmount + thisTotal, 2);
        totalQuantity += quantity;
        totalSavings += Round(savings * quantity, 2);
    });
    if ($("#_order_upsell_products").length > 0) {
        $("#_order_upsell_products").find(".order-upgrade-product-id").each(function() {
            if ($(this).prop("checked")) {
                totalAmount += parseFloat($(this).closest(".order-upgrade-product-wrapper").data("total_cost"));
                totalQuantity += parseFloat($(this).closest(".order-upgrade-product-wrapper").data("quantity"));
            }
        });
    }
    $(".cart-total").each(function () {
        if ($(this).is('input')) {
            $(this).val(Round(totalAmount, 2));
        } else {
            $(this).html(RoundFixed(totalAmount, 2));
        }
    });
    if (isNaN(totalSavings)) {
        totalSavings = 0;
    }
    $(".cart-savings").each(function () {
        if ($(this).is('input')) {
            $(this).val(Round(totalSavings, 2));
        } else {
            $(this).html(RoundFixed(totalSavings, 2));
        }
    });
    $(".cart-total-quantity").each(function () {
        if ($(this).is('input')) {
            $(this).val(totalQuantity);
        } else {
            $(this).html(totalQuantity);
        }
    });
    if (totalQuantity === 1) {
        $(".cart-total-quantity-plural").hide();
    } else {
        $(".cart-total-quantity-plural").show();
    }
    if (typeof getShippingMethods == "function") {
        getShippingMethods();
    }
    if (typeof calculateOrderTotal == "function") {
        calculateOrderTotal();
    }
    if ("getTaxCharge" in window) {
        getTaxCharge();
    }
}

function getWishListItems(wishListId) {
    if (empty(wishListId)) {
        wishListId = "";
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_wish_list_items&wish_list_id=" + wishListId, function (returnArray) {
        if (!("wish_list_items" in returnArray)) {
            return;
        }
        const $wishListItemBlock = $("#_wish_list_item_block");
        const $wishListItemsWrapper = $("#wish_list_items_wrapper");
        if ($wishListItemBlock.length === 0 || $wishListItemsWrapper.length === 0) {
            return;
        }
        for (let j in returnArray['wish_list_items']) {
            let itemBlock = $wishListItemBlock.html().replace(new RegExp("%image_src%", 'ig'), "src");

            for (let i in returnArray['wish_list_items'][j]) {
                itemBlock = itemBlock.replace(new RegExp("%" + i + "%", 'ig'), returnArray['wish_list_items'][j][i]);
            }
            let otherClasses = "";
            if ("inventory_quantity" in returnArray['wish_list_items'][j]) {
                if (returnArray['wish_list_items'][j]['inventory_quantity'] <= 0) {
                    otherClasses = "out-of-stock";
                }
            }
            if ("no_online_order" in returnArray['wish_list_items'][j] && !empty(returnArray['wish_list_items'][j]['no_online_order'])) {
                otherClasses += (empty(otherClasses) ? "" : " ") + "no-online-order";
            }
            itemBlock = itemBlock.replace(new RegExp("%other_classes%", 'ig'), otherClasses);

            $wishListItemsWrapper.append(itemBlock);
            if ("notify_when_in_stock" in returnArray['wish_list_items'][j] && !empty(returnArray['wish_list_items'][j]['notify_when_in_stock'])) {
                $("tr#wish_list_item_id_" + returnArray['wish_list_items'][j]['wish_list_item_id']).find(".notify-when-in-stock").prop("checked", true);
            }
            if ("no_online_order" in returnArray['wish_list_items'][j] && !empty(returnArray['wish_list_items'][j]['no_online_order'])) {
                $("#wish_list_item_id_" + returnArray['wish_list_items'][j]['wish_list_item_id']).find(".add-to-cart").addClass("hidden").addClass("no-online-order");
            }
        }
        if ("wish_list_item_count" in returnArray) {
            $(".wish-list-item-count").html(returnArray['wish_list_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
        if ("wish_list_items" in returnArray) {
            $.each(returnArray["wish_list_items"], function (index, item) {
                const thisProductId = item.product_id;
                if (!empty(thisProductId)) {
                    $(".add-to-wishlist-" + thisProductId).each(function () {
                        const inText = $(this).data("in_text");
                        if (!empty(inText)) {
                            $(this).html(inText);
                        }
                    });
                }
            });
        }
        if (typeof afterGetWishListItems == "function") {
            setTimeout(function () {
                afterGetWishListItems(returnArray);
            }, 100);
        }
    });
}

function getShoppingCartItemCount(shoppingCartCode) {
    if (empty(shoppingCartCode)) {
        shoppingCartCode = "";
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_shopping_cart_item_count&shopping_cart_code=" + shoppingCartCode, function (returnArray) {
        if ("shopping_cart_item_count" in returnArray) {
            $(".shopping-cart-item-count").html(returnArray['shopping_cart_item_count']);
        }
        if ("wish_list_item_count" in returnArray) {
            $(".wish-list-item-count").html(returnArray['wish_list_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
    });
}

function getWishListItemCount(wishListId) {
    if (empty(wishListId)) {
        wishListId = "";
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_wish_list_item_count&wish_list_id=" + wishListId, function (returnArray) {
        if ("wish_list_item_count" in returnArray) {
            $(".wish-list-item-count").html(returnArray['wish_list_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
    });
}

function updateShoppingCartItem(shoppingCartItemId, shoppingCartCode, quantity, setQuantity, contactId) {
    return addProductToShoppingCart("", shoppingCartCode, quantity, setQuantity, contactId, shoppingCartItemId)
}

function saveItemAddons(productId) {
    let parameters = {};
    if (!empty(productId)) {
        parameters['product_id'] = productId;
    }
    $("input[type=checkbox].product-addon").each(function () {
        parameters[$(this).attr("id")] = ($(this).prop("checked") ? 1 : 0);
    });
    $("input[type=text].product-addon").each(function () {
        parameters[$(this).attr("id")] = $(this).val();
    });
    $("select.product-addon").each(function () {
        let quantity = $(this).closest(".form-line").find(".addon-select-quantity").val();
        $(this).find("option").each(function () {
            let optionId = $(this).attr("id");
            if (!empty(optionId)) {
                parameters[optionId] = ($(this).is(':selected') ? quantity : 0);
            }
        });
    });
    if ($("#shopping_cart_code").length > 0 && !empty($("#shopping_cart_code").val())) {
        parameters['shopping_cart_code'] = $("#shopping_cart_code").val();
    }
    if (parameters['shopping_cart_code'] == "ORDERENTRY") {
        parameters['contact_id'] = $("#contact_id").val();
    }
    calculateShoppingCartTotal();
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=save_addons", parameters);
}

function addProductToShoppingCart(productId, shoppingCartCode, quantity, setQuantity, contactId, shoppingCartItemId) {
    let parameters = {};
    if (typeof productId == "object") {
        parameters = productId;
        productId = parameters['product_id'];
        shoppingCartCode = parameters['shopping_cart_code'];
        quantity = parameters['quantity'];
        setQuantity = parameters['set_quantity'];
        contactId = parameters['contact_id'];
        shoppingCartItemId = parameters['shopping_cart_item_id'];
        orderUpsellProduct = (!empty(parameters['order_upsell_product']));
    } else {
        orderUpsellProduct = false;
    }
    if (empty(shoppingCartItemId)) {
        shoppingCartItemId = "";
    }
    if (empty(shoppingCartCode)) {
        shoppingCartCode = "";
    }
    if (empty(quantity)) {
        quantity = "";
    }
    if (empty(setQuantity)) {
        setQuantity = false;
    }
    if (empty(contactId)) {
        contactId = "";
    }
    if (empty(orderUpsellProduct)) {
        orderUpsellProduct = false;
    }
    let addonCount = 0;
    $("body").addClass("no-waiting-for-ajax");
    let postFields = {};
    postFields['shopping_cart_code'] = shoppingCartCode;
    postFields['product_id'] = productId;
    postFields['quantity'] = quantity;
    postFields['contact_id'] = contactId;
    postFields['shopping_cart_item_id'] = shoppingCartItemId;
    postFields['order_upsell_product'] = orderUpsellProduct;

    if (!("ignore_addons" in parameters) || empty(parameters['ignore_addons'])) {
        $("input[type=checkbox].product-addon").each(function () {
            postFields[$(this).attr("id")] = ($(this).prop("checked") ? 1 : 0);
            addonCount += ($(this).prop("checked") ? 1 : 0);
        });
        $("input[type=text].product-addon").each(function () {
            postFields[$(this).attr("id")] = $(this).val();
            addonCount += (empty($(this).val()) ? 0 : 1);
        });
        $("select.product-addon").each(function () {
            let quantity = $(this).closest(".form-line").find(".addon-select-quantity").val();
            $(this).find("option").each(function () {
                let optionId = $(this).attr("id");
                if (!empty(optionId)) {
                    postFields[optionId] = ($(this).is(':selected') ? quantity : 0);
                    addonCount += ($(this).is(':selected') ? quantity : 0);
                }
            });
        });
    }
    postFields['addon_count'] = addonCount;
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=" + (setQuantity ? "change_shopping_cart_quantity" : "add_to_shopping_cart") + "&shopping_cart_code=" + shoppingCartCode + "&product_id=" + productId + "&quantity=" + quantity + "&contact_id=" + contactId + "&shopping_cart_item_id=" + shoppingCartItemId, postFields, function(returnArray) {
        if ("shopping_cart_item_id" in returnArray) {
            shoppingCartItemId = returnArray['shopping_cart_item_id'];
        }
        if(empty(productId) && "product_id" in returnArray) {
            productId = returnArray['product_id'];
        }
        if (setQuantity && ("related_products" in returnArray) && !empty(returnArray['related_products'])) {
            // show dialog of related products that can be added to cart.
            if ($("_add_to_cart_related_products").length == 0) {
                $("body").append("<div class='dialog-box' id='_add_to_cart_related_products'><div id='_add_to_cart_related_products_wrapper'></div></div>");
            }
            if (!$("#_add_to_cart_related_products_wrapper").hasClass("dialog-displayed")) {
                $("#_add_to_cart_related_products_wrapper").addClass("dialog-displayed");
                loadRelatedProducts(productId, 'SHOPPING_CART', '_add_to_cart_related_products_wrapper');
                $("#_add_to_cart_related_products").dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Related Products',
                    buttons: {
                        Close: function (event) {
                            $("#_add_to_cart_related_products_wrapper").removeClass("dialog-displayed");
                            $("#_add_to_cart_related_products").dialog('close');
                        }
                    }
                });
            }
        }
        const $addToCart = $(".add-to-cart-" + productId);
        if ("error_message" in returnArray) {
            if ($addToCart.length > 0) {
                $addToCart.each(function () {
                    const addToCartText = $(this).data("text");
                    $(this).html(empty(addToCartText) ? "Add to Cart" : addToCartText);
                });
            }
            if ("reload_cart" in returnArray) {
                getShoppingCartItems($("#shopping_cart_code").val(), contactId);
            }
            return;
        }
        if ("add_to_cart_alert" in returnArray && !empty(returnArray['add_to_cart_alert'])) {
            if ($("_add_to_cart_alert").length == 0) {
                $("body").append("<div class='dialog-box' id='_add_to_cart_alert'></div>");
            }
            $("#_add_to_cart_alert").html(returnArray['add_to_cart_alert']);
            setTimeout(function () {
                $("#_add_to_cart_alert").dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Add To Cart Alert',
                    buttons: {
                        Close: function (event) {
                            $("#_add_to_cart_alert").dialog('close');
                        }
                    }
                });
            }, 1000)
        }

        if ($addToCart.length > 0) {
            $addToCart.each(function () {
                const inText = $(this).data("in_text");
                if (!empty(inText)) {
                    $(this).html(inText);
                }
            });
        }
        if ("shopping_cart_item_count" in returnArray) {
            $(".shopping-cart-item-count").html(returnArray['shopping_cart_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
        if ("reload_cart" in returnArray) {
            getShoppingCartItems($("#shopping_cart_code").val(), contactId);
        } else {
            calculateShoppingCartTotal();
            checkFFLRequirements();
        }
        if ("form_link" in returnArray && !empty(returnArray['form_link'])) {
            document.location = "/" + returnArray['form_link'];
        }
        if (typeof afterAddToCart == "function") {
            setTimeout(function () {
                afterAddToCart(productId, quantity);
            }, 1000);
        }
    });
}

function addProductToWishList(productId, wishListId, notifyWhenInStock) {
    if (empty(wishListId)) {
        wishListId = "";
    }
    notifyWhenInStock = !empty(notifyWhenInStock);
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=add_to_wish_list&wish_list_id=" + wishListId + "&product_id=" + productId + "&notify_when_in_stock=" + (notifyWhenInStock ? "1" : ""), function (returnArray) {

        const $addToWishList = $(".add-to-wishlist-" + productId);
        if ($addToWishList.length > 0) {
            $addToWishList.each(function () {
                const inText = $(this).data("in_text");
                if (!empty(inText)) {
                    $(this).html(inText);
                }
            });
        }
        if ("wish_list_item_count" in returnArray) {
            $(".wish-list-item-count").html(returnArray['wish_list_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
    });
}

function removeProductFromShoppingCart(productId, shoppingCartItemId, shoppingCartCode, contactId) {
    if (empty(shoppingCartCode)) {
        shoppingCartCode = "";
    }
    if (empty(contactId)) {
        contactId = "";
    }
    if (empty(shoppingCartItemId)) {
        shoppingCartItemId = "";
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=remove_from_shopping_cart&shopping_cart_code=" + shoppingCartCode + "&shopping_cart_item_id=" + shoppingCartItemId + "&product_id=" + productId + "&contact_id=" + contactId, function(returnArray) {

        if ("shopping_cart_item_count" in returnArray) {
            $(".shopping-cart-item-count").html(returnArray['shopping_cart_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
        calculateShoppingCartTotal();
        checkFFLRequirements();
    });
}

function removeProductFromWishList(productId, wishListId) {
    if (empty(wishListId)) {
        wishListId = "";
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=remove_from_wish_list&wish_list_id=" + wishListId + "&product_id=" + productId, function (returnArray) {
        const productWishlistTriggers = $(".add-to-wishlist-" + productId);
        if (productWishlistTriggers.length > 0) {
            productWishlistTriggers.each(function () {
                const text = $(this).data("text");
                if (!empty(text)) {
                    $(this).html(text);
                }
            });
        }
        if ("wish_list_item_count" in returnArray) {
            $(".wish-list-item-count").html(returnArray['wish_list_item_count']);
        }
        if ("loyalty_points_total" in returnArray) {
            $(".loyalty-points-total").html(returnArray['loyalty_points_total']);
        }
    });
}

function setWishListItemNotify(productId, notifyWhenInStock, wishListId) {
    if (empty(wishListId)) {
        wishListId = "";
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=set_wish_list_item_notify&wish_list_id=" + wishListId + "&product_id=" + productId + "&notify_when_in_stock=" + (empty(notifyWhenInStock) ? "0" : "1"));
}

function displaySearchResults(catalogItemContainer, keepPageNumber, afterFunction, catalogResultElementId) {
    let scrollToTop = false;
    const $pageNumber = $("#page_number");
    if (empty(keepPageNumber)) {
        $pageNumber.val("1");
        $(".page-number").val("1");
    } else if (keepPageNumber) {
        scrollToTop = true;
    }
    if (empty(catalogResultElementId)) {
        catalogResultElementId = "_catalog_result";
    }
    const $catalogResult = $("#" + catalogResultElementId);
    if ($catalogResult.length === 0) {
        getCatalogResultTemplate(function () {
            displaySearchResults(catalogItemContainer, keepPageNumber, afterFunction, catalogResultElementId);
        });
        return;
    }
    let originalCatalogResult = $catalogResult.html();
    if (empty(originalCatalogResult)) {
        setTimeout(function () {
            displaySearchResults(catalogItemContainer, keepPageNumber, afterFunction, catalogResultElementId);
        }, 500);
        return;
    }
    if (productKeyLookup === false) {
        productKeyLookup = {};
        for (let i in productFieldNames) {
            productKeyLookup[productFieldNames[i]] = i;
        }
    }
    let insertWrapper = false;
    let catalogItemWrapper = catalogItemContainer;
    if (empty(catalogItemContainer)) {
        catalogItemContainer = "_search_results";
        catalogItemWrapper = "_search_results_wrapper";
        insertWrapper = true;
    }
    if (resultCount === 0) {
        $("#" + catalogItemContainer).html("<p>No Results Found MAHATHI ONE!</p>");
        if (typeof afterDisplaySearchResults == "function") {
            // noinspection JSUnresolvedFunction
            afterDisplaySearchResults(catalogItemWrapper);
        }
        if (typeof afterFunction == "function") {
            afterFunction();
        }
        return;
    }
    if (insertWrapper) {
        $("#" + catalogItemContainer).html("<div id='" + catalogItemWrapper + "'></div>");
    } else {
        $("#" + catalogItemContainer).html("");
    }
    let localResultCount = 0;
    let count = 0;

    let filterValues = [];
    let filterIndex = 0;

    if ($("#selected_filters").length == 0) {
        $("<div id='_selected_filter_wrapper'><div id='selected_filters'></div></div>").insertBefore("#_search_results");
    }
    const $selectedFilters = $("#selected_filters");
    $selectedFilters.find(".sidebar-selected-filter").remove();
    $("#sidebar_filters").find(".sidebar-filter").each(function () {
        if ($(this).find("input[type=checkbox]:checked").length === 0) {
            return true;
        }
        filterValues[filterIndex] = {};
        const filterNameElement = $($(this).find("h3")).clone();
        filterNameElement.find("span").remove();
        const filterName = filterNameElement.html();
        filterValues[filterIndex]['field_name'] = $(this).data("field_name");
        filterValues[filterIndex]['filter_values'] = [];
        $(this).find("input[type=checkbox]:checked").each(function () {
            filterValues[filterIndex]['filter_values'].push($(this).val());
            const thisId = $(this).attr("id");
            $selectedFilters.append("<div class='selected-filter sidebar-selected-filter' data-id='" + thisId + "'>" + filterName + ": <span class='filter-text-value'>" +
                $(this).closest("div.filter-option").find("label").html() + "</span><span class='fad fa-times-circle'></span></div>");
        });
        $selectedFilters.find("span.reductive-count").remove();
        filterIndex++;
    });
    if (!empty($("input[type=radio][name=location_availability]").val())) {
        const filterName = "Location";
        const primaryFilter = $(".primary-selected-filter").html();
        let locationAvailability = $("input:radio[name ='location_availability']:checked").val();
        if (!empty(locationAvailability)) {
            const thisFilter = filterName + ": " + $("label[for=location_id_" + locationAvailability + "]").html();
            if (thisFilter !== primaryFilter) {
                filterValues[filterIndex] = {};
                filterValues[filterIndex]['field_name'] = "location_id";
                filterValues[filterIndex]['filter_values'] = [];
                filterValues[filterIndex]['filter_values'].push(locationAvailability);
                $selectedFilters.append("<div class='selected-filter sidebar-selected-filter' data-id='location_id_" + locationAvailability + "'>" + filterName + ": <span class='filter-text-value'>" +
                    $("label[for=location_id_" + locationAvailability + "]").html() + "</span><span class='fad fa-times-circle'></span></div>");
                filterIndex++;
            }
        }
    }

    let sortOrder = $("#product_sort_order").val();
    if (sortOrder !== lastSortOrder || empty(lastSortOrder) || sortedIndexes.length === 0) {
        sortedIndexes = {};
        sortDirection = 1;
        lastSortOrder = sortOrder;
        let sortObjects = [];
        switch (sortOrder) {
            case "description":
                for (let i in productResults) {
                    sortObjects.push({ "data_value": productResults[i][productKeyLookup['description']], "index": i });
                }
                sortObjects.sort(function (a, b) {
                    if (a.data_value === b.data_value) {
                        return 0;
                    } else {
                        return (a.data_value < b.data_value ? -1 : 1);
                    }
                });
                for (let i in sortObjects) {
                    sortedIndexes[i] = sortObjects[i]['index'];
                }
                break;
            case "highest_price":
                sortDirection = -1;
            case "lowest_price":
                for (let i in productResults) {
                    sortObjects.push({ "data_value": productResults[i][productKeyLookup['sale_price']], "index": i });
                }
                sortObjects.sort(function (a, b) {
                    if (a.data_value === b.data_value) {
                        return 0;
                    } else {
                        const aPrice = parseFloat(a.data_value.replace(new RegExp("$", 'ig'), "").replace(new RegExp(",", 'ig'), ""));
                        const bPrice = parseFloat(b.data_value.replace(new RegExp("$", 'ig'), "").replace(new RegExp(",", 'ig'), ""));
                        if (isNaN(aPrice) && !isNaN(bPrice)) {
                            return 1;
                        }
                        if (!isNaN(aPrice) && isNaN(bPrice)) {
                            return -1;
                        }
                        if (isNaN(aPrice) && isNaN(bPrice)) {
                            return 0;
                        }
                        return (aPrice < bPrice ? -1 : 1) * sortDirection;
                    }
                });
                for (let i in sortObjects) {
                    sortedIndexes[i] = sortObjects[i]['index'];
                }
                break;
            case "brand":
                for (let i in productResults) {
                    let productManufacturerId = productResults[i][productKeyLookup['product_manufacturer_id']];
                    let manufacturerName = "";
                    if (productManufacturerId in manufacturerNames) {
                        manufacturerName = manufacturerNames[productManufacturerId][0];
                    }
                    sortObjects.push({ "data_value": manufacturerName, "index": i });
                }
                sortObjects.sort(function (a, b) {
                    if (a.data_value === b.data_value) {
                        return 0;
                    } else {
                        return (a.data_value < b.data_value ? -1 : 1);
                    }
                });
                for (let i in sortObjects) {
                    sortedIndexes[i] = sortObjects[i]['index'];
                }
                break;
            case "sku":
                for (let i in productResults) {
                    sortObjects.push({ "data_value": productResults[i][productKeyLookup['manufacturer_sku']], "index": i });
                }
                sortObjects.sort(function (a, b) {
                    if (a.data_value === b.data_value) {
                        return 0;
                    } else {
                        return (a.data_value < b.data_value ? -1 : 1);
                    }
                });
                for (let i in sortObjects) {
                    sortedIndexes[i] = sortObjects[i]['index'];
                }
                break;
            case "relevance":
                for (let i in productResults) {
                    sortObjects.push({ "data_value": parseFloat(productResults[i][productKeyLookup['relevance']]), "index": i });
                }
                sortObjects.sort(function (a, b) {
                    if (a.data_value === b.data_value) {
                        return 0;
                    } else {
                        return (a.data_value > b.data_value ? -1 : 1);
                    }
                });
                for (let i in sortObjects) {
                    sortedIndexes[i] = sortObjects[i]['index'];
                }
                break;
            default:
                for (let i in productResults) {
                    sortedIndexes[i] = i;
                }
                break;
        }
    }

    let hideOutOfStock = false;
    if ($("#hide_out_of_stock").length > 0) {
        const $hideOutOfStock = $("#hide_out_of_stock");
        if ($hideOutOfStock.length > 0) {
            if ($hideOutOfStock.prop("checked")) {
                hideOutOfStock = true;
            }
        } else if ($.cookie("hide_out_of_stock") == "true" || $.cookie("hide_out_of_stock") === true) {
            hideOutOfStock = true;
        }
        if (typeof $.cookie('hide_out_of_stock') === 'undefined' && typeof (hideOutOfStockDefault) !== "undefined") {
            hideOutOfStock = hideOutOfStockDefault;
        }
    }
    let availableInStoreToday = false;
    if ($("#available_in_store_today").length > 0 && $("#available_in_store_today").prop("checked")) {
        availableInStoreToday = $("#available_in_store_today").val();
    }

    let locationAvailability = $("input:radio[name ='location_availability']:checked").val();
    for (let i in sortedIndexes) {
        let resultIndex = sortedIndexes[i];
        let thisProduct = productResults[resultIndex];
        let productId = thisProduct[productKeyLookup['product_id']];
        productResults[resultIndex]['hide_product'] = true;

        let displayThisProduct = true;

        // check to see if product is in filtered items

        for (filterIndex in filterValues) {
            let fieldName = filterValues[filterIndex]['field_name'];
            if (fieldName === "price_range") {
                let foundValue = false;
                if ("sale_price" in productKeyLookup && productKeyLookup['sale_price'] in thisProduct) {
                    if (thisProduct[productKeyLookup['sale_price']].length === 0) {
                        continue;
                    }
                    const productCost = parseFloat(thisProduct[productKeyLookup['sale_price']].replace(new RegExp(",", 'ig'), ""));
                    for (let i in filterValues[filterIndex]['filter_values']) {
                        const priceRangeIndex = filterValues[filterIndex]['filter_values'][i];
                        if (priceRangeIndex in priceRanges && productCost >= priceRanges[priceRangeIndex]['minimum_cost'] && productCost <= priceRanges[priceRangeIndex]['maximum_cost']) {
                            foundValue = true;
                            break;
                        }
                    }
                }
                if (!foundValue) {
                    displayThisProduct = false;
                    break;
                }
                continue;
            }
            if (fieldName === "location_id") {
                if (!empty(locationAvailability)) {
                    if (!("inventory_quantity_" + locationAvailability in productKeyLookup) || productResults[resultIndex][productKeyLookup['inventory_quantity_' + locationAvailability]] <= 0) {
                        if ($("#shipping_location_availability").val() == "now") {
                            displayThisProduct = false;
                        } else if (hideOutOfStock) {
                            if (!("inventory_quantity_distributor" in productKeyLookup) || productResults[resultIndex][productKeyLookup['inventory_quantity_distributor']] <= 0) {
                                displayThisProduct = false;
                            }
                        }
                    }
                }
                continue;
            }
            if (!(fieldName in productKeyLookup) || !(productKeyLookup[fieldName] in thisProduct)) {
                displayThisProduct = false;
                break;
            }
            const productValues = (thisProduct[productKeyLookup[fieldName]] + "").split(",");
            let foundValue = false;
            for (let i in productValues) {
                if (!empty(productValues[i]) && isInArray(productValues[i], filterValues[filterIndex]['filter_values'], true)) {
                    foundValue = true;
                    break;
                }
            }
            if (!foundValue) {
                displayThisProduct = false;
                break;
            }
        }
        if (hideOutOfStock && "inventory_quantity" in productKeyLookup && productResults[resultIndex][productKeyLookup['inventory_quantity']] <= 0) {
            displayThisProduct = false;
        }
        if (!empty(availableInStoreToday)) {
            if (!("inventory_quantity_" + availableInStoreToday in productKeyLookup) || productResults[resultIndex][productKeyLookup['inventory_quantity_' + availableInStoreToday]] <= 0) {
                displayThisProduct = false;
            }
        }
        if (!displayThisProduct) {
            continue;
        }
        localResultCount++;
        productResults[resultIndex]['hide_product'] = false;
    }

    $(".results-count").html(localResultCount);
    if (localResultCount === 1) {
        $(".results-count-plural").addClass("hidden");
    } else {
        $(".results-count-plural").removeClass("hidden");
    }
    $(".results-count-wrapper").removeClass("hidden");
    const $showCount = $("#show_count");
    let perPageCount = 20;
    if ($showCount.length > 0) {
        perPageCount = parseInt($showCount.val())
    }
    const pageCount = Math.ceil(localResultCount / perPageCount);
    let currentPage = $pageNumber.val();
    if (isNaN(currentPage) || currentPage > pageCount || currentPage <= 0) {
        currentPage = 1;
    }
    if (pageCount <= 1) {
        $(".paging-control").addClass("hidden");
    } else {
        $(".paging-control").removeClass("hidden");
    }
    if (currentPage == 1) {
        $(".previous-page").addClass("hidden");
    } else {
        $(".previous-page").removeClass("hidden");
    }
    if (currentPage == pageCount) {
        $(".next-page").addClass("hidden");
    } else {
        $(".next-page").removeClass("hidden");
    }
    const $topPagingControlPages = $("#_top_paging_control_pages");
    $topPagingControlPages.html("<a href='#' class='page-number current-page' data-page_number='" + currentPage + "'>" + currentPage + "</a>");
    for (let x = 1; x <= 2; x++) {
        if (currentPage > x) {
            $topPagingControlPages.prepend("<a href='#' class='page-number' data-page_number='" + (currentPage - x) + "'>" + (currentPage - x) + "</a>");
        }
        if (currentPage < (pageCount - (x - 1))) {
            $topPagingControlPages.append("<a href='#' class='page-number' data-page_number='" + (parseInt(currentPage) + x) + "'>" + (parseInt(currentPage) + x) + "</a>");
        }
    }
    const $bottomPagingControlPages = $("#_bottom_paging_control_pages");
    $bottomPagingControlPages.html("<a href='#' class='page-number current-page' data-page_number='" + currentPage + "'>" + currentPage + "</a>");
    for (let x = 1; x <= 2; x++) {
        if (currentPage > x) {
            $bottomPagingControlPages.prepend("<a href='#' class='page-number' data-page_number='" + (currentPage - x) + "'>" + (currentPage - x) + "</a>");
        }
        if (currentPage < (pageCount - (x - 1))) {
            $bottomPagingControlPages.append("<a href='#' class='page-number' data-page_number='" + (parseInt(currentPage) + x) + "'>" + (parseInt(currentPage) + x) + "</a>");
        }
    }

    let skipCount = perPageCount * (currentPage - 1);
    let skippedCount = 0;

    let catalogItemIndex = 0;
    let insertedCatalogItems = [];
    let productCatalogItems = {};
    for (let i in sortedIndexes) {
        let resultIndex = sortedIndexes[i];
        if (productResults[resultIndex]['hide_product']) {
            continue;
        }
        if (skipCount > 0 && skippedCount < skipCount) {
            skippedCount++;
            continue;
        }
        let catalogResult = originalCatalogResult;
        let otherClasses = "";
        catalogResult = catalogResult.replace(new RegExp("%image_src%", 'ig'), "src");

        const imageBaseFilenameKey = productKeyLookup['image_base_filename'];
        const imageBaseFilename = productResults[resultIndex][imageBaseFilenameKey];
        const remoteImage = productResults[resultIndex][productKeyLookup['remote_image']];

        if ($("#catalog_result_product_tags_template").length > 0) {
            catalogResult = catalogResult.replace(new RegExp("%product_tags%", 'ig'), $("#catalog_result_product_tags_template").html());
        } else {
            catalogResult = catalogResult.replace(new RegExp("%product_tags%", 'ig'), "");
        }

        if (!empty(remoteImage)) {
            catalogResult = catalogResult.replace(new RegExp("%image_url%", 'ig'), "https://images.coreware.com/images/products/" + remoteImage + ".jpg");
            catalogResult = catalogResult.replace(new RegExp("%full_image_url%", 'ig'), "https://images.coreware.com/images/products/" + remoteImage + ".jpg");
            catalogResult = catalogResult.replace(new RegExp("%small_image_url%", 'ig'), "https://images.coreware.com/images/products/small-" + remoteImage + ".jpg");
        } else if (!empty(productResults[resultIndex][productKeyLookup['remote_image_url']])) {
            catalogResult = catalogResult.replace(new RegExp("%image_url%", 'ig'), productResults[resultIndex][productKeyLookup['remote_image_url']]);
            catalogResult = catalogResult.replace(new RegExp("%full_image_url%", 'ig'), productResults[resultIndex][productKeyLookup['remote_image_url']]);
            catalogResult = catalogResult.replace(new RegExp("%small_image_url%", 'ig'), productResults[resultIndex][productKeyLookup['remote_image_url']]);
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
            if ("inventory_quantity" in productKeyLookup && productResults[resultIndex][productKeyLookup['inventory_quantity']] > 0) {
                if (productResults[resultIndex][productKeyLookup['location_availability']] in availabilityTexts) {
                    productResults[resultIndex][productKeyLookup['location_availability']] = availabilityTexts[productResults[resultIndex][productKeyLookup['location_availability']]];
                }
            } else {
                productResults[resultIndex][productKeyLookup['location_availability']] = "";
            }
        }
        catalogResult = catalogResult.replace(new RegExp("%thumbnail_image_url%", 'ig'), (empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : "/cache/image-thumbnail-") + imageBaseFilename));
        const productId = productResults[resultIndex][productKeyLookup['product_id']];
        let productManufacturerId = productResults[resultIndex][productKeyLookup['product_manufacturer_id']];
        let manufacturerName = "";
        let ignoreMap = !empty(productManufacturerId);
        let mapPolicyCode = "";
        if (!empty(productManufacturerId) && productManufacturerId in manufacturerNames) {
            manufacturerName = manufacturerNames[productManufacturerId][0];
            ignoreMap = (!empty(manufacturerNames[productManufacturerId][1]));
            mapPolicyCode = manufacturerNames[productManufacturerId][2];
            if (ignoreMap) {
                mapPolicyCode = "";
            }
        }

        const mapEnforced = (!empty(productResults[resultIndex][productKeyLookup['map_enforced']]));
        if (mapEnforced) {
            ignoreMap = false;
        }
        const productGroup = ("product_group" in productKeyLookup && !empty(productResults[resultIndex][productKeyLookup['product_group']]));
        const callPrice = (!empty(productResults[resultIndex][productKeyLookup['call_price']]));
        if (callPrice) {
            ignoreMap = false;
        }
        let salePrice = "";

        catalogResult = catalogResult.replace(new RegExp("%manufacturer_name%", 'ig'), manufacturerName);
        if ("original_sale_price" in productKeyLookup && !empty(productResults[resultIndex][productKeyLookup['original_sale_price']])) {
            if ((productResults[resultIndex][productKeyLookup['original_sale_price']] + "").indexOf("$") < 0) {
                const originalPrice = parseFloat(productResults[resultIndex][productKeyLookup['original_sale_price']].toString().replace(",", ""));
                salePrice = parseFloat(productResults[resultIndex][productKeyLookup['sale_price']].toString().replace(",", ""));
                if (!empty(productResults[resultIndex][productKeyLookup['sale_price']]) && originalPrice <= salePrice) {
                    productResults[resultIndex][productKeyLookup['original_sale_price']] = "";
                }
                if ("original_sale_price" in productKeyLookup && !empty(productResults[resultIndex][productKeyLookup['original_sale_price']])) {
                    productResults[resultIndex][productKeyLookup['original_sale_price']] = "$" + RoundFixed(productResults[resultIndex][productKeyLookup['original_sale_price']], 2);
                }
            }
        }
        if (!empty(productResults[resultIndex][productKeyLookup['manufacturer_advertised_price']]) && !ignoreMap) {
            let mapPrice = "";
            if (!empty(productResults[resultIndex][productKeyLookup['manufacturer_advertised_price']])) {
                mapPrice = parseFloat(productResults[resultIndex][productKeyLookup['manufacturer_advertised_price']].replace(new RegExp(",", 'ig'), ""));
            }
            if (!empty(productResults[resultIndex][productKeyLookup['sale_price']])) {
                salePrice = parseFloat(productResults[resultIndex][productKeyLookup['sale_price']].replace(new RegExp(",", 'ig'), ""));
            }
            if (mapPrice > salePrice) {
                if (mapPolicyCode == "CART_PRICE") {
                    catalogResult = catalogResult.replace(new RegExp("%sale_price%", 'ig'), "See price in cart");
                } else {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "map-priced-product";
                    catalogResult = catalogResult.replace(new RegExp("%sale_price%", 'ig'), RoundFixed(productResults[resultIndex][productKeyLookup['manufacturer_advertised_price']], 2));
                }
                productResults[resultIndex][productKeyLookup['original_sale_price']] = "";
            }
        }

        if (!("no_online_order" in productKeyLookup) || empty(productResults[resultIndex][productKeyLookup['no_online_order']])) {
            if ("inventory_quantity" in productKeyLookup) {
                let thisProductInventoryQuantity = productResults[resultIndex][productKeyLookup['inventory_quantity']];
                if (thisProductInventoryQuantity <= 0) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "out-of-stock-product";
                } else if (!empty(userDefaultLocationId) && empty(productResults[resultIndex][productKeyLookup['inventory_quantity_distributor']]) && ("inventory_quantity_" + userDefaultLocationId in productKeyLookup) && empty(productResults[resultIndex][productKeyLookup['inventory_quantity_' + userDefaultLocationId]])) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "out-of-stock-product";
                }
            }
        }
        for (let fieldNumber in productFieldNames) {
            let thisValue = productResults[resultIndex][fieldNumber];
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
            catalogResult = catalogResult.replace(new RegExp("%hidden_if_empty:" + productFieldNames[fieldNumber] + "%", 'ig'), (empty(productResults[resultIndex][fieldNumber]) ? "hidden" : ""));
        }
        if ("product_tag_ids" in productKeyLookup && !empty(productResults[resultIndex][productKeyLookup['product_tag_ids']])) {
            const productTagIds = productResults[resultIndex][productKeyLookup['product_tag_ids']].split(",");
            for (let i in productTagIds) {
                const productTagCode = productTagCodes[productTagIds[i]];
                if (!empty(productTagCode)) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "product-tag-code-" + productTagCode.replace(new RegExp("_", "ig"), "-");
                }
            }
        }

        catalogResult = catalogResult.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
        if (!empty(credovaUserName)) {
            salePrice = productResults[resultIndex][productKeyLookup['sale_price']];
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
        if (typeof localMassageCatalogResult === "function") {
            catalogResult = localMassageCatalogResult(catalogResult, productResults[resultIndex], productKeyLookup);
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
        if ("no_online_order" in productKeyLookup && !empty(productResults[resultIndex][productKeyLookup['no_online_order']])) {
            productCatalogItems[productId].no_online_order = true;
        }
        if ("hide_dollar" in productKeyLookup && !empty(productResults[resultIndex][productKeyLookup['hide_dollar']])) {
            productCatalogItems[productId].hide_dollar = true;
        }
        if (mapPolicyCode == "CART_PRICE") {
            productCatalogItems[productId].hide_dollar = true;
        }
        count++;
        if ($showCount.length > 0 && count >= parseInt($showCount.val())) {
            break;
        }
    }
    let $catalogItemWrapper = $("#" + catalogItemWrapper);
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
        let $addToCart = $(".add-to-cart-" + thisProductId);
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
        let thisProductId = wishListProductIds[i];
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
    setTimeout(function () {
        reduceSidebar();
    }, 100);
    if (scrollToTop) {
        window.scrollTo(0, 0);
    }
    if (orderedItems !== false) {
        for (let i in orderedItems) {
            if ($("#catalog_item_" + orderedItems[i]['product_id']).length > 0) {
                $("#catalog_item_" + orderedItems[i]['product_id']).find(".catalog-item-ordered").remove();
                if (!$("#catalog_item_" + orderedItems[i]['product_id']).hasClass("catalog-list-item")) {
                    $("#catalog_item_" + orderedItems[i]['product_id']).append("<div class='catalog-item-ordered'>Ordered on " + orderedItems[i]['order_date'] + "</div>");
                }
            }
        }
    }
    if (!empty(credovaUserName)) {
        showCredovaMessages();
    }
    // noinspection JSUnresolvedVariable
    if (typeof afterDisplaySearchResults == "function") {
        // noinspection JSUnresolvedFunction
        afterDisplaySearchResults(catalogItemWrapper);
    }
    if (typeof afterFunction == "function") {
        afterFunction();
    }
}

function buildSidebarFilters() {
    let filterIndex = 0;
    const $sidebarFilter = $("#_sidebar_filter");
    const $sidebarFilters = $("#sidebar_filters");
    $sidebarFilters.html("");
    const urlParameters = getURLParameters(location.search);
    if (!neverOutOfStock) {
        $sidebarFilters.html('<div id="hide_out_of_stock_wrapper"><input type="checkbox" id="hide_out_of_stock" name="hide_out_of_stock" value="1"><label class="checkbox-label" for="hide_out_of_stock">Hide out of stock</label></div>');
        let hideOutOfStockCookie = $.cookie("hide_out_of_stock");
        if (typeof $.cookie('hide_out_of_stock') === 'undefined' && typeof (hideOutOfStockDefault) !== "undefined") {
            hideOutOfStockCookie = (empty(hideOutOfStockDefault) ? "false" : "true");
        }
        if ("exclude_out_of_stock" in urlParameters) {
            hideOutOfStockCookie = !empty(urlParameters['exclude_out_of_stock']);
        }
        if (hideOutOfStockCookie == "true") {
            $("#hide_out_of_stock").prop("checked", true);
        }
    }
    let thisFilter = $sidebarFilter.html();
    let allFilters;
    let oneExpanded = false;
    if (empty(constraints)) {
        return;
    }
    if ("locations" in constraints && Object.keys(constraints['locations']).length == 1) {
        let locationId = "";
        for (let i in constraints['locations']) {
            locationId = constraints['locations'][i]['location_id'];
        }
        if (!empty(locationId)) {
            $sidebarFilters.append('<div id="available_in_store_today_wrapper"><input type="checkbox" id="available_in_store_today" name="available_in_store_today" value="' + locationId + '"><label class="checkbox-label" for="available_in_store_today">Available In-Store Today</label></div>');
        }
    } else if ("locations" in constraints && Object.keys(constraints['locations']).length > 1) {
        thisFilter = $sidebarFilter.html();
        const locationFilterIndex = filterIndex;
        thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_" + filterIndex++);
        thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), "Location Availability");
        thisFilter = thisFilter.replace(new RegExp("%search_text%", 'ig'), "Search Location");
        thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "location_id");
        let otherClasses = ($(window).height() > 800 && !oneExpanded ? "opened" : "");
        if (constraints['locations'].length < filterTextMinimumCount) {
            otherClasses += " no-filter-text";
        }
        thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
        let allFilters = "";
        for (let i in constraints['locations']) {
            oneExpanded = true;
            allFilters += "<div class='filter-option'><div class='filter-option-checkbox'><input type='radio' name='location_availability' id='location_id_" +
                constraints['locations'][i]['location_id'] + "' " + (constraints['locations'][i]['location_id'] == userDefaultLocationId && empty(getURLParameter("no_location")) ? "checked='checked' " : "") + " value='" +
                constraints['locations'][i]['location_id'] + "'></div><div class='filter-option-label'><label class='checkbox-label' for='location_id_" +
                constraints['locations'][i]['location_id'] + "'>" + constraints['locations'][i]['description'] + "</label></div></div>";
        }
        thisFilter = thisFilter.replace(new RegExp("%filter_options%", 'ig'), allFilters);
        $sidebarFilters.append(thisFilter);
        $("#filter_id_" + locationFilterIndex).find(".filter-text-filter-wrapper").after("<p class='align-center'><select id='shipping_location_availability'><option value='pickup'>Available for Pickup</option><option value='now'>In-store NOW</option></select></p>");
    }
    if ("product_tags" in constraints) {
        thisFilter = $sidebarFilter.html();
        thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_" + filterIndex++);
        thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), "Tags");
        thisFilter = thisFilter.replace(new RegExp("%search_text%", 'ig'), "Search Tags");
        thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "product_tag_ids");
        let otherClasses = ($(window).height() > 800 && !oneExpanded ? "opened" : "");
        if (constraints['product_tags'].length < filterTextMinimumCount) {
            otherClasses += " no-filter-text";
        }
        thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
        let allFilters = "";
        for (let i in constraints['product_tags']) {
            oneExpanded = true;
            allFilters += "<div class='filter-option'><div class='filter-option-checkbox'><input type='checkbox' id='product_tag_id_" +
                constraints['product_tags'][i]['product_tag_id'] + "' value='" +
                constraints['product_tags'][i]['product_tag_id'] + "'></div><div class='filter-option-label'><label class='checkbox-label' for='product_tag_id_" +
                constraints['product_tags'][i]['product_tag_id'] + "'>" + constraints['product_tags'][i]['description'] + "</label></div></div>";
        }
        thisFilter = thisFilter.replace(new RegExp("%filter_options%", 'ig'), allFilters);
        $sidebarFilters.append(thisFilter);
    }
    if ("categories" in constraints) {
        thisFilter = $sidebarFilter.html();
        thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_" + filterIndex++);
        thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), "Category");
        thisFilter = thisFilter.replace(new RegExp("%search_text%", 'ig'), "Search Category");
        thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "product_category_ids");
        let otherClasses = ($(window).height() > 800 && !oneExpanded ? "opened" : "");
        if (constraints['categories'].length < filterTextMinimumCount) {
            otherClasses += " no-filter-text";
        }
        thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
        let allFilters = "";
        for (let i in constraints['categories']) {
            oneExpanded = true;
            allFilters += "<div class='filter-option'><div class='filter-option-checkbox'><input type='checkbox' id='product_category_id_" +
                constraints['categories'][i]['product_category_id'] + "' value='" +
                constraints['categories'][i]['product_category_id'] + "'></div><div class='filter-option-label'><label class='checkbox-label' for='product_category_id_" +
                constraints['categories'][i]['product_category_id'] + "'>" + constraints['categories'][i]['description'] + "</label></div></div>";
        }
        thisFilter = thisFilter.replace(new RegExp("%filter_options%", 'ig'), allFilters);
        $sidebarFilters.append(thisFilter);
    }
    if ("manufacturers" in constraints) {
        thisFilter = $sidebarFilter.html();
        thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_" + filterIndex++);
        thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), "Brand");
        thisFilter = thisFilter.replace(new RegExp("%search_text%", 'ig'), "Search Brand");
        thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "product_manufacturer_id");
        let otherClasses = ($(window).height() > 800 && !oneExpanded ? "opened" : "");
        if (constraints['manufacturers'].length < filterTextMinimumCount) {
            otherClasses += " no-filter-text";
        }
        thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
        let allFilters = "";
        for (let i in constraints['manufacturers']) {
            oneExpanded = true;
            allFilters += "<div class='filter-option'><div class='filter-option-checkbox'><input type='checkbox' id='product_manufacturer_id_" +
                constraints['manufacturers'][i]['product_manufacturer_id'] + "' value='" +
                constraints['manufacturers'][i]['product_manufacturer_id'] + "'></div><div class='filter-option-label'><label class='checkbox-label' for='product_manufacturer_id_" +
                constraints['manufacturers'][i]['product_manufacturer_id'] + "'>" + constraints['manufacturers'][i]['description'] + "</label></div></div>";
        }
        thisFilter = thisFilter.replace(new RegExp("%filter_options%", 'ig'), allFilters);
        $sidebarFilters.append(thisFilter);
    }

    thisFilter = $sidebarFilter.html();
    thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_prices");
    thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), "Price");
    thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "price_range");
    thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), "");
    allFilters = "";
    for (let i in priceRanges) {
        allFilters += "<div class='filter-option'><div class='filter-option-checkbox'><input type='checkbox' id='price_range_" + i + "' value='" + i + "' data-minimum='" + priceRanges[i]['minimum_cost'] +
            "' data-maximum='" + priceRanges[i]['maximum_cost'] + "'></div><div class='filter-option-label'><label class='checkbox-label' for='price_range_" + i + "'>" + priceRanges[i]['label'] + "</label></div></div>";
    }
    thisFilter = thisFilter.replace(new RegExp("%filter_options%", 'ig'), allFilters);
    $sidebarFilters.append(thisFilter);
    $("#filter_id_prices").find(".filter-text-filter-wrapper").remove();

    if ("facets" in constraints) {
        for (let facetIndex in facetDescriptions) {
            if (!(facetDescriptions[facetIndex]['id'] in constraints['facets'])) {
                continue;
            }
            thisFilter = $sidebarFilter.html();
            thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_" + filterIndex++);
            thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), facetDescriptions[facetIndex]['description']);
            thisFilter = thisFilter.replace(new RegExp("%search_text%", 'ig'), "Search " + facetDescriptions[facetIndex]['description']);
            thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "product_facet_option_ids");
            if (Object.keys(constraints['facets'][facetDescriptions[facetIndex]['id']]).length < filterTextMinimumCount) {
                thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), "no-filter-text");
            } else {
                thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), "");
            }
            allFilters = "";
            for (let i in constraints['facets'][facetDescriptions[facetIndex]['id']]) {
                allFilters += "<div class='filter-option'><div class='filter-option-checkbox'><input type='checkbox' id='product_facet_option_id_" +
                    constraints['facets'][facetDescriptions[facetIndex]['id']][i]['product_facet_option_id'] + "' value='" +
                    constraints['facets'][facetDescriptions[facetIndex]['id']][i]['product_facet_option_id'] + "'></div><div class='filter-option-label'><label class='checkbox-label' for='product_facet_option_id_" +
                    constraints['facets'][facetDescriptions[facetIndex]['id']][i]['product_facet_option_id'] + "'>" + constraints['facets'][facetDescriptions[facetIndex]['id']][i]['description'] + "</label></div></div>";
            }
            thisFilter = thisFilter.replace(new RegExp("%filter_options%", 'ig'), allFilters);
            $sidebarFilters.append(thisFilter);
        }
    }
}

function reduceSidebar() {
    if (!sidebarReductionNeeded) {
        return;
    }
    sidebarReductionNeeded = false;
    const $sidebarFilters = $("#sidebar_filters");
    const workingSectionExists = (!empty(clickedFilterId) && $("#" + clickedFilterId).find("input[type=checkbox]:checked").length > 0);
    if (!workingSectionExists) {
        $sidebarFilters.find("input[type=checkbox],input[type=radio]").data("display_count", 0).closest(".filter-option").addClass("hidden");
        $sidebarFilters.find("label span.reductive-count").remove();
    } else {
        $sidebarFilters.find(".sidebar-filter").not("#" + clickedFilterId).find("input[type=checkbox],input[type=radio]").data("display_count", 0).closest(".filter-option").addClass("hidden");
        $sidebarFilters.find(".sidebar-filter").not("#" + clickedFilterId).find("label span.reductive-count").remove();
    }
    for (let i in priceRanges) {
        priceRanges[i]['count'] = 0;
    }
    let displayCountArray = {};

    const locationsExist = ($("input[type=radio][name=location_availability]").length > 0);
    let locationIds = [];
    if (locationsExist) {
        $("input[type=radio][name=location_availability]").each(function () {
            displayCountArray["location_id_" + $(this).val()] = 0;
            locationIds.push($(this).val());
        });
    }
    for (let i in productResults) {
        if ("hide_product" in productResults[i] && productResults[i]['hide_product']) {
            continue;
        }
        const thisProduct = productResults[i];

        if ("product_manufacturer_id" in productKeyLookup && productKeyLookup['product_manufacturer_id'] in thisProduct) {
            const productManufacturerId = thisProduct[productKeyLookup['product_manufacturer_id']];
            if ("product_manufacturer_id_" + productManufacturerId in displayCountArray) {
                displayCountArray["product_manufacturer_id_" + productManufacturerId]++;
            } else {
                displayCountArray["product_manufacturer_id_" + productManufacturerId] = 1;
            }
        }
        if ("product_category_ids" in productKeyLookup && productKeyLookup['product_category_ids'] in thisProduct) {
            const productCategoryIds = thisProduct[productKeyLookup['product_category_ids']].split(",");
            for (let i in productCategoryIds) {
                if ("product_category_id_" + productCategoryIds[i] in displayCountArray) {
                    displayCountArray["product_category_id_" + productCategoryIds[i]]++;
                } else {
                    displayCountArray["product_category_id_" + productCategoryIds[i]] = 1;
                }
            }
        }
        if ("product_tag_ids" in productKeyLookup && productKeyLookup['product_tag_ids'] in thisProduct && !empty(thisProduct[productKeyLookup['product_tag_ids']])) {
            const productTagIds = thisProduct[productKeyLookup['product_tag_ids']].split(",");
            for (let i in productTagIds) {
                if ("product_tag_id_" + productTagIds[i] in displayCountArray) {
                    displayCountArray["product_tag_id_" + productTagIds[i]]++;
                } else {
                    displayCountArray["product_tag_id_" + productTagIds[i]] = 1;
                }
            }
        }
        if ("product_facet_option_ids" in productKeyLookup && productKeyLookup['product_facet_option_ids'] in thisProduct && !empty(thisProduct[productKeyLookup['product_facet_option_ids']])) {
            const productFacetOptionIds = thisProduct[productKeyLookup['product_facet_option_ids']].split(",");
            for (let i in productFacetOptionIds) {
                if ("product_facet_option_id_" + productFacetOptionIds[i] in displayCountArray) {
                    displayCountArray["product_facet_option_id_" + productFacetOptionIds[i]]++;
                } else {
                    displayCountArray["product_facet_option_id_" + productFacetOptionIds[i]] = 1;
                }
            }
        }
        if ("sale_price" in productKeyLookup && productKeyLookup['sale_price'] in thisProduct) {
            if (thisProduct[productKeyLookup['sale_price']].length === 0) {
                continue;
            }
            const productCost = parseFloat(thisProduct[productKeyLookup['sale_price']].replace(new RegExp(",", 'ig'), ""));

            for (let i in priceRanges) {
                if (productCost >= priceRanges[i]['minimum_cost'] && productCost <= priceRanges[i]['maximum_cost']) {
                    priceRanges[i]['count']++;
                }
            }
        }
        if (locationsExist) {
            for (let i in locationIds) {
                if ("inventory_quantity_" + locationIds[i] in productKeyLookup && productKeyLookup["inventory_quantity_" + locationIds[i]] in thisProduct && thisProduct[productKeyLookup["inventory_quantity_" + locationIds[i]]] > 0) {
                    displayCountArray["location_id_" + locationIds[i]]++;
                }
            }
        }
    }

    for (let i in displayCountArray) {
        const $element = $("#" + i);
        if ($element.length > 0) {
            $element.data("display_count", displayCountArray[i]);
            $element.closest(".filter-option").removeClass("hidden");
        }
    }
    for (let i in priceRanges) {
        $("#price_range_" + i).data("display_count", priceRanges[i]['count']);
    }
    $("#filter_id_prices").removeClass("hidden").find(".hidden").removeClass("hidden");
    $sidebarFilters.find(".filter-option").each(function () {
        const filterId = $(this).closest(".sidebar-filter").attr("id");
        if (workingSectionExists && clickedFilterId === filterId) {
            return true;
        }
        $(this).find("label").find("span.reductive-count").remove();
        const displayCount = $(this).find("input[type=checkbox],input[type=radio]").data("display_count");
        if (displayCount === 0) {
            $(this).addClass("hidden");
        }
        if (!empty(displayCount)) {
            $(this).find("label").append("<span class='reductive-count'> (" + displayCount + ")</span>");
        }
    });
    $sidebarFilters.find(".sidebar-filter").each(function () {
        const checkboxCount = $(this).find(".filter-option").not(".hidden").length;
        if (checkboxCount < 1) {
            $(this).addClass("hidden");
        } else {
            $(this).removeClass("hidden");
        }
    });
    checkSelectedFilters();
}

function checkSelectedFilters() {
    if ($("#selected_filters").length == 0) {
        $("<div id='_selected_filter_wrapper'><div id='selected_filters'></div></div>").insertBefore("#_search_results");
    }
    const $selectedFilters = $("#selected_filters").find(".primary-selected-filter");
    if ($selectedFilters.length <= 1) {
        $selectedFilters.addClass("not-removable");
    } else {
        $selectedFilters.removeClass("not-removable");
    }
}

function getFFLDealers(preferredOnly, showMap) {
    if (empty(preferredOnly)) {
        preferredOnly = "";
        if ($("#show_preferred_only").length > 0 && $("#show_preferred_only").prop("checked")) {
            preferredOnly = true;
        }
    } else {
        preferredOnly = true;
    }
    if (empty(showMap)) {
        showMap = false;
    }
    let haveLicenseOnly = "";
    if ($("#show_have_license_only").length > 0 && $("#show_have_license_only").prop("checked")) {
        haveLicenseOnly = true;
    }
    if ($("#ffl_dealers_wrapper").length === 0) {
        return;
    }
    $("#ffl_dealer_count").html("");
    const $fflDealers = $("#ffl_dealers");
    const $postalCode = $("#postal_code");
    const $billingPostalCode = $("#billing_postal_code");
    const $fflRadius = $("#ffl_radius");
    const $restrictedDealers = $("#restricted_dealers");
    $fflDealers.find("li").remove();
    $fflDealers.append("<li class='align-center'>Searching</li>");
    const requestUrl = "/retail-store-controller?ajax=true&url_action=get_ffl_dealers&postal_code=" +
        encodeURIComponent((empty($postalCode.val()) ? "" : $postalCode.val())) + "&preferred_only=" + preferredOnly + "&have_license_only=" + haveLicenseOnly +
        (empty($("#ffl_dealer_filter").val()) ? "" : "&search_text=" + encodeURIComponent($("#ffl_dealer_filter").val())) +
        "&billing_postal_code=" + encodeURIComponent(empty($billingPostalCode.val()) ? "" : $billingPostalCode.val()) +
        "&radius=" + (empty($fflRadius.val()) ? "25" : $fflRadius.val());
    loadAjaxRequest(requestUrl, function (returnArray) {
        $fflDealers.find("li").remove();
        let someRestricted = false;
        $restrictedDealers.addClass("hidden");
        if ("ffl_dealers" in returnArray) {
            fflDealers = [];
            if (showMap) {
                var infowindow = new google.maps.InfoWindow();
                var geocoder = new google.maps.Geocoder();
                geocoder.geocode({ 'address': 'zipcode ' + encodeURIComponent((empty($postalCode.val()) ? "" : $postalCode.val())) }, function (results, status) {
                    if (status == google.maps.GeocoderStatus.OK) {
                        var latitude = results[0].geometry.location.lat();
                        var longitude = results[0].geometry.location.lng();
                        let markerCenter = new google.maps.Marker({
                            position: new google.maps.LatLng(latitude, longitude),
                            map: fflDealersMap
                        });
                        fflDealersMap.setCenter(markerCenter.getPosition());
                        markerCenter.setMap(null);
                    }
                });
            }
            for (let i in returnArray['ffl_dealers']) {
                const fflDealer = returnArray['ffl_dealers'][i];
                const fflId = fflDealer['federal_firearms_licensee_id'];
                if (!empty(fflDealer['restricted'])) {
                    someRestricted = true;
                }
                if (showMap) {
                    let latitude = fflDealer['geocode']['latitude'];
                    let longitude = fflDealer['geocode']['longitude'];
                    let fflDealersMapInfo = fflDealer['display_name'];
                    let marker = new google.maps.Marker({
                        position: new google.maps.LatLng(latitude, longitude),
                        map: fflDealersMap
                    });
                    markers[fflId] = marker;
                    google.maps.event.addListener(marker, 'click', (function (marker, i) {
                        return function () {
                            infowindow.setContent(fflDealersMapInfo);
                            infowindow.open(fflDealersMap, marker);
                        }
                    })(marker, i));
                }
                fflDealers[fflId] = fflDealer['display_name'];
                $fflDealers.append("<li title='Click to select' class='ffl-dealer" + (empty(fflDealer['preferred']) ? "" : " preferred") + (empty(fflDealer['restricted']) ? "" : " restricted") +
                    (empty(fflDealer['have_license']) ? "" : " have-license") + "' data-federal_firearms_licensee_id='" + fflId + "'" +
                    (empty(fflDealer['shipping_method_id']) ? "" : " data-shipping_method_id='" + fflDealer['shipping_method_id'] + "'") + ">" +
                    fflDealer['display_name'] + "</li>");
            }
            if (someRestricted) {
                $restrictedDealers.removeClass("hidden");
            }
            $("#ffl_dealer_count").html(returnArray['ffl_dealers'].length);
        }
        if ($fflDealers.find("li").length === 0 && $fflRadius.length > 0 && parseInt($fflRadius.val()) < 100) {
            $fflRadius.find('option:selected').next().prop('selected', true);
            setTimeout(function () {
                getFFLDealers(preferredOnly, showMap);
            }, 100);
        }
        setTimeout(function () {
            $fflDealers.find(".ffl-dealer").tooltip({
                position: { my: "left top", at: "left+150 top+10" }
            });
        }, 1000);
        if (typeof afterLoadFFLDealers == "function") {
            setTimeout(function () {
                afterLoadFFLDealers()
            }, 100);
        }
    });
}

function addFilter(filterLabel, filterText, fieldName) {
    if ($("#selected_filters").length == 0) {
        $("<div id='_selected_filter_wrapper'><div id='selected_filters'></div></div>").insertBefore("#_search_results");
    }
    $("#selected_filters").append("<div class='selected-filter primary-selected-filter' data-field_name='" + fieldName + "'>" + filterLabel + ": <span class='filter-text-value'>" + filterText + "</span><span class='fad fa-times-circle'></span></div>");
}

function filterList(filterField) {
    let filterText = filterField.val().toLowerCase();
    filterField.closest(".sidebar-filter").find(".filter-option").each(function () {
        if (empty(filterText) || $(this).find('label').text().toLowerCase().indexOf(filterText) >= 0) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
}

function searchProductCatalog() {
    let valuesFound = false;
    const $searchProductsForm = $("#_search_products_form");
    if ($("#_search_products_form").find("#from_form").length == 0) {
        $("#_search_products_form").append("<input type='hidden' name='from_form' value='true'>");
    }
    $searchProductsForm.find("input").each(function () {
        $(this).val($(this).val().replace("/", " "));
        if (empty($(this).val())) {
            $(this).addClass("input-remove");
        } else {
            valuesFound = true;
        }
    });
    if (valuesFound) {
        $searchProductsForm.find(".input-remove").remove();
        sendAnalyticsEvent("search", {searchTerm: $("#search_text").val()});
        $searchProductsForm.attr("method", "GET").attr("action", "/product-search-results").submit();
    } else {
        $searchProductsForm.find(".input-remove").removeClass("input-remove");
    }
    return false;
}

function displayLoadedProducts(returnArray, elementId) {
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

        }
    }
}

function loadRelatedProducts(productId, relatedProductTypeCode, elementId, afterFunction) {
    if (empty(relatedProductTypeCode)) {
        relatedProductTypeCode = "";
    }
    if (empty(elementId)) {
        elementId = "related_products";
    }
    if ($("#_catalog_result").length === 0) {
        getCatalogResultTemplate(function () {
            loadRelatedProducts(productId, relatedProductTypeCode, elementId, afterFunction);
        });
        return;
    }
    if (!empty(productId) && $("#" + elementId).length > 0) {
        $("body").addClass("no-waiting-for-ajax");
        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products&url_source=related_products&related_product_id=" + productId + "&related_product_type_code=" + relatedProductTypeCode, function (returnArray) {
            displayLoadedProducts(returnArray, elementId);
            if (elementId === "related_products") {
                $("#related_products_wrapper").removeClass("hidden");
            }
            if (typeof afterFunction == "function") {
                afterFunction();
            }
            if (typeof afterLoadRelatedProducts == "function") {
                setTimeout(function () {
                    afterLoadRelatedProducts()
                }, 100);
            }
        });
    }
}

var filterProductSearchTimer = null;

function getFilterProductSearchParameters($productSearchForm) {
    let postFields = {};
    if (!empty($productSearchForm.find(".product-manufacturer-id").val())) {
        postFields['product_manufacturer_id'] = $productSearchForm.find(".product-manufacturer-id").val();
    }
    if (!empty($productSearchForm.find(".product-category-id").val())) {
        postFields['product_category_id'] = $productSearchForm.find(".product-category-id").val();
    }
    let productFacetIds = "";
    let productFacetOptionIds = "";
    $productSearchForm.find(".product-facet-option-id").each(function () {
        if (!empty($(this).val())) {
            productFacetIds += (empty(productFacetOptionIds) ? "" : "|") + $(this).data("product_facet_id");
            productFacetOptionIds += (empty(productFacetOptionIds) ? "" : "|") + $(this).val();
        }
    });
    if (!empty(productFacetIds)) {
        postFields['product_facet_ids'] = productFacetIds;
    }
    if (!empty(productFacetOptionIds)) {
        postFields['product_facet_option_ids'] = productFacetOptionIds;
    }
    if (!empty($productSearchForm.find(".product-tag-id").val())) {
        postFields['product_tag_id'] = $productSearchForm.find(".product-tag-id").val();
    }
    if ($productSearchForm.find(".in-stock-only").prop("checked")) {
        postFields['exclude_out_of_stock'] = 1;
    }
    if (!empty($productSearchForm.find(".sale-price").val())) {
        const salePriceParts = $productSearchForm.find(".sale-price").val().split("-");
        postFields['minimum_price'] = salePriceParts[0];
        postFields['maximum_price'] = salePriceParts[1];
    }
    if (!empty($productSearchForm.find(".state-compliance").val())) {
        postFields['states'] = $productSearchForm.find(".state-compliance").val();
    }
    if (!empty($productSearchForm.find(".search-text").val())) {
        postFields['search_text'] = $productSearchForm.find(".search-text").val();
    }
    if (empty(postFields)) {
        return postFields;
    }
    if (!$productSearchForm.find(".in-stock-only").prop("checked")) {
        postFields['exclude_out_of_stock'] = 0;
    }
    if (!empty($productSearchForm.find(".product-department-id").val())) {
        postFields['product_department_id'] = $productSearchForm.find(".product-department-id").val();
    }
    if (!empty($productSearchForm.find(".field-list").val())) {
        postFields['reductive_field_list'] = $productSearchForm.find(".field-list").val();
    }
    return postFields;
}

function filterProductSearchPageModule($productSearchForm, $fieldChanged) {
    $productSearchForm.find(".product-search-result-count-wrapper").addClass("hidden");
    $productSearchForm.find(".product-search-result-searching-wrapper").removeClass("hidden");
    let postFields = getFilterProductSearchParameters($productSearchForm);
    if (empty(postFields)) {
        $productSearchForm.find(".product-search-clear-form").trigger("click");
        return;
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products", postFields, function (returnArray) {
        if ("result_count" in returnArray) {
            $productSearchForm.find(".product-search-result-searching-wrapper").addClass("hidden");
            $productSearchForm.find(".product-search-result-count-wrapper").removeClass("hidden");
            $productSearchForm.find(".product-search-result-count").html(returnArray['result_count']);
        }
        if ("reductive_data" in returnArray) {
            let thisValue = "";
            for (var i in returnArray['reductive_data']) {
                switch (i) {
                    case "product_manufacturers":
                        if ($productSearchForm.find(".product-manufacturer-id").length == 0) {
                            break;
                        }
                        if ($fieldChanged.hasClass("product-manufacturer-id")) {
                            break;
                        }
                        thisValue = $productSearchForm.find(".product-manufacturer-id").val();
                        $productSearchForm.find(".product-manufacturer-id").find("option").unwrap("span");
                        $productSearchForm.find(".product-manufacturer-id").find("option[value!='']").each(function () {
                            if ($(this).val() != thisValue && !isInArray($(this).val(), returnArray['reductive_data'][i])) {
                                $(this).wrap("<span></span>");
                            }
                        });
                        break;
                    case "product_categories":
                        if ($productSearchForm.find(".product-category-id").length == 0) {
                            break;
                        }
                        if ($fieldChanged.hasClass("product-category-id")) {
                            break;
                        }
                        thisValue = $productSearchForm.find(".product-category-id").val();
                        $productSearchForm.find(".product-category-id").find("option").unwrap("span");
                        $productSearchForm.find(".product-category-id").find("option[value!='']").each(function () {
                            if ($(this).val() != thisValue && !isInArray($(this).val(), returnArray['reductive_data'][i])) {
                                $(this).wrap("<span></span>");
                            }
                        });
                        break;
                    case "product_tags":
                        if ($productSearchForm.find(".product-tag-id").length == 0) {
                            break;
                        }
                        if ($fieldChanged.hasClass("product-tag-id")) {
                            break;
                        }
                        thisValue = $productSearchForm.find(".product-tag-id").val();
                        $productSearchForm.find(".product-tag-id").find("option").unwrap("span");
                        $productSearchForm.find(".product-tag-id").find("option[value!='']").each(function () {
                            if ($(this).val() != thisValue && !isInArray($(this).val(), returnArray['reductive_data'][i])) {
                                $(this).wrap("<span></span>");
                            }
                        });
                        break;
                    default:
                        if (i.substr(0, "product_facet_code-".length) != "product_facet_code-") {
                            break;
                        }
                        const productFacetCode = i.replace("product_facet_code-", "").toUpperCase();
                        if ($fieldChanged.hasClass("product-facet-option-id") && $fieldChanged.data("product_facet_code") == productFacetCode) {
                            break;
                        }
                        let $productFacetField = false;
                        $productSearchForm.find(".product-facet-option-id").each(function () {
                            if ($(this).data("product_facet_code") === productFacetCode) {
                                $productFacetField = $(this);
                                return false;
                            }
                        });
                        if ($productFacetField === false) {
                            break;
                        }
                        thisValue = $productFacetField.val();
                        $productFacetField.find("option").unwrap("span");
                        $productFacetField.find("option[value!='']").each(function () {
                            if ($(this).val() != thisValue && !isInArray($(this).val(), returnArray['reductive_data'][i])) {
                                $(this).wrap("<span></span>");
                            }
                        });
                        break;
                }
            }
        }
    });
}

$(function () {
    $(document).on("click", ".product-search-page-module-department-button", function () {
        const formWrapperId = $(this).attr("id") + "_form_wrapper";
        $(this).closest(".product-search-page-module-wrapper").find(".product-search-page-module-form-wrapper").addClass("hidden");
        $(this).closest(".product-search-page-module-wrapper").find(".product-search-page-module-department-button").removeClass("selected");
        $("#" + formWrapperId).removeClass("hidden");
        $(this).addClass("selected");
    });
    $(document).on("click", ".product-search-clear-form", function () {
        $(this).closest("form").clearForm();
        const $productSearchForm = $(this).closest("form");
        $productSearchForm.find(".product-search-result-searching-wrapper").addClass("hidden");
        $productSearchForm.find(".product-search-result-count-wrapper").addClass("hidden");
        $(this).closest("form").find("select").each(function () {
            $(this).find("option").unwrap("span");
        });
        return false;
    });
    $(document).on("click", "input[type=checkbox].product-search-page-module", function () {
        if (!empty(filterProductSearchTimer)) {
            clearTimeout(filterProductSearchTimer);
        }
        const $productSearchForm = $(this).closest("form");
        filterProductSearchTimer = setTimeout(function () {
            filterProductSearchPageModule($productSearchForm);
        }, 500);
    });
    $(document).on("change", ".product-search-page-module", function () {
        if (!empty(filterProductSearchTimer)) {
            clearTimeout(filterProductSearchTimer);
        }
        const $productSearchForm = $(this).closest("form");
        const $fieldChanged = $(this);
        filterProductSearchTimer = setTimeout(function () {
            filterProductSearchPageModule($productSearchForm, $fieldChanged);
        }, 500);
    });
    $(document).on("click", ".product-search-view-results", function () {
        const $productSearchForm = $(this).closest("form");
        let postFields = getFilterProductSearchParameters($productSearchForm);
        let parameters = "no_location=true";
        let parameterAdded = false;
        for (var i in postFields) {
            if (!empty(postFields[i])) {
                parameters += (empty(parameters) ? "" : "&") + i + "=" + encodeURIComponent(postFields[i]);
                parameterAdded = true;
            }
        }
        if (parameterAdded) {
            window.open("/product-search-results?" + parameters);
        } else {
            displayErrorMessage("Select one or more criteria to search for product.");
        }
        return false;
    });

    $(document).on("click", ".catalog-item-compare-button", function () {
        compareProducts();
    });
    $(document).on("click", ".result-display-type", function () {
        if ($(this).hasClass("selected")) {
            return;
        }
        const resultDisplayType = $(this).data("result_display_type");
        $.cookie("result_display_type", resultDisplayType, { expires: 365, path: '/' });
        $(".result-display-type").removeClass("selected");
        $(this).addClass("selected");
        $("#_catalog_result").remove();
        displaySearchResults()
    });
    $(window).resize(function () {
        if ($(window).width() < 1050 && $("#result_display_type_list").hasClass("selected")) {
            $("#result_display_type_tile").trigger("click");
        }
    });
    setTimeout(function () {
        const $publicIdentifier = $("#public_identifier");
        const $paymentMethodId = $("#payment_method_id");
        if ($publicIdentifier.length > 0 && !empty($publicIdentifier.val())) {
            $(".checkout-credova-button").addClass("hidden");
            displayInfoMessage("Select 'Credova' as the payment method and finalize your purchase.");
            if (empty($paymentMethodId.val())) {
                let credovaPaymentMethodId = "";
                $paymentMethodId.find("option").each(function () {
                    const paymentMethodCode = $(this).data("payment_method_code");
                    if (paymentMethodCode === "CREDOVA") {
                        credovaPaymentMethodId = $(this).val();
                        return false;
                    }
                });
                $paymentMethodId.val(credovaPaymentMethodId).trigger("change");
            }
        }
    }, 1000);
    setTimeout(function () {
        if (!empty(credovaUserName)) {
            loadCredova();
        }
    }, 500);
    $(document).on("click", ".credova-apply", function (event) {
        event.stopPropagation();
        event.preventDefault();
        // noinspection JSUnresolvedVariable,JSUnresolvedFunction
        CRDV.plugin.prequalify(300).then(function (res) {
            // noinspection JSUnresolvedVariable
            if (res.approved) {
                const publicId = res.publicId[0];
                $.cookie("credova_public_identifier", publicId, { expires: 365, path: '/' });
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_credova", { public_identifier: publicId }, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        displayInfoMessage("Select 'Credova' as the payment method when you checkout.");
                    }
                });
            }
        });
        return false;
    });
    $(document).on("click", ".sidebar-filter h3", function () {
        $(this).closest(".sidebar-filter").toggleClass("opened");
    });

    $(document).on("click", ".filter-option input[type=checkbox]", function () {
        clickedFilterId = $(this).closest(".sidebar-filter").attr("id");
        sidebarReductionNeeded = true;
        displaySearchResults();
    });

    $(document).on("click", ".filter-option input[type=radio]", function () {
        clickedFilterId = $(this).closest(".sidebar-filter").attr("id");
        sidebarReductionNeeded = true;
        displaySearchResults();
    });

    $(document).on("change", "#shipping_location_availability", function () {
        displaySearchResults();
    });

    $(document).on("keyup", ".filter-text-filter", function () {
        filterList($(this));
    });

    $(document).on("click", ".selected-filter", function () {
        if ($(this).hasClass("not-removable")) {
            return;
        }
        const thisId = $(this).data("id");
        if (empty(thisId)) {
            const fieldName = $(this).data("field_name");
            if (!empty(fieldName)) {
                $("#" + fieldName).val("");
            }
            searchProductCatalog();
        } else {
            $(this).remove();
            const $thisId = $("#" + thisId);
            if ($thisId.is("input[type=checkbox]") || $thisId.is("input[type=radio]")) {
                $thisId.prop("checked", false);
            } else {
                $thisId.val("");
            }
            sidebarReductionNeeded = true;
            setTimeout(function () {
                displaySearchResults();
            }, 50);
        }
        return false;
    });

    $(document).on("change", "#show_count", function () {
        let showCount = $("#show_count").val();
        if (empty(showCount)) {
            showCount = 20;
        }
        $.cookie("show_count", showCount, { expires: 365, path: '/' });
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        setTimeout(function () {
            displaySearchResults();
        }, 50);
    });

    $(document).on("change", "#product_sort_order", function () {
        let sortOrder = $("#product_sort_order").val();
        $.cookie("product_sort_order", sortOrder, { expires: 365, path: '/' });
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        setTimeout(function () {
            displaySearchResults();
        }, 50);
    });

    $(document).on("click", "#hide_out_of_stock", function () {
        $.cookie("hide_out_of_stock", ($(this).prop("checked") ? "true" : "false"), { expires: 365, path: '/' });
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        sidebarReductionNeeded = true;
        setTimeout(function () {
            displaySearchResults();
        }, 50);
    });

    $(document).on("click", "#available_in_store_today", function () {
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        sidebarReductionNeeded = true;
        setTimeout(function () {
            displaySearchResults();
        }, 50);
    });

    $(document).on("click", ".previous-page", function () {
        const $pageNumber = $("#page_number");
        const newPage = parseInt($pageNumber.val()) - 1;
        $(".page-number").val(newPage);
        $pageNumber.val(newPage);
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        setTimeout(function () {
            displaySearchResults("", true);
        }, 50);
        return false;
    });

    $(document).on("click", ".next-page", function () {
        const $pageNumber = $("#page_number");
        const newPage = parseInt($pageNumber.val()) + 1;
        $(".page-number").val(newPage);
        $pageNumber.val(newPage);
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        setTimeout(function () {
            displaySearchResults("", true);
        }, 50);
        return false;
    });

    $(document).on("change", "select.page-number", function () {
        const newPage = $(this).val();
        $(".page-number").val(newPage);
        $("#page_number").val(newPage);
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        setTimeout(function () {
            displaySearchResults("", true);
        }, 50);
    });

    $(document).on("click", "a.page-number", function () {
        const newPage = $(this).data("page_number");
        $(".page-number").val(newPage);
        $("#page_number").val(newPage);
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
        setTimeout(function () {
            displaySearchResults("", true);
        }, 50);
        return false;
    });

    $(document).on("click", ".catalog-item-thumbnail", function (event) {
        $(this).parent("div").find("a.product-detail-link").trigger("click");
    });

    $(document).on("click", "a.product-detail-link", function (event) {
        if (empty($(this).attr("href"))) {
            event.preventDefault();
        }
    });

    $(document).on("click", "#_search_result_close_details_wrapper", function () {
        history.back();
    });

    $(document).on("keydown", function (event) {
        if (event.key == "Escape") {
            if ($("#_search_result_details_wrapper").length > 0) {
                if (!$("#_search_result_details_wrapper").hasClass("hidden")) {
                    history.back();
                }
            }
        }
    });

    $(document).on("click", ".click-product-detail", function (event) {
        const productId = $(this).closest(".catalog-item").data("product_id");
        if (empty(productId)) {
            return;
        }
        if (event.metaKey || event.ctrlKey || !empty($(this).data("separate_window"))) {
            let detailLink = $(this).parent(".catalog-item").find(".product-detail-link").val();
            if (empty(detailLink)) {
                detailLink = "/product-details?id=" + productId;
            }
            window.open(detailLink, "_blank");
            return;
        }
        if ($("#_search_result_details_wrapper").length == 0) {
            let detailLink = $(this).parent(".catalog-item").find(".product-detail-link").val();
            if (empty(detailLink)) {
                detailLink = "/product-details?id=" + productId;
            }
            document.location = detailLink;
            return;
        }
        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
        if (productId in savedProductDetails && (!("neverSaveProductDetails" in window) || !neverSaveProductDetails)) {
            displayProductDetails(savedProductDetails[productId]);
        } else {
            loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_full_product_details&product_id=" + productId, function (returnArray) {
                if ("content" in returnArray) {
                    savedProductDetails[productId] = returnArray;
                    displayProductDetails(returnArray);
                }
            });
        }
        return false;
    });

    $(document).on("click", ".add-to-cart", function () {
        const productId = $(this).data("product_id");

        // If there is a dialog box with ID _call_for_price_dialog and this product is "call for price", show that dialog

        if ($(this).hasClass("call-price") && $("#_call_for_price_dialog").length > 0) {
            $("#_call_for_price_dialog").find(".add-to-cart").data("product_id", productId);
            $("#_call_for_price_dialog").dialog({
                closeOnEscape: true,
                draggable: true,
                modal: true,
                resizable: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 600,
                title: 'Contact Details',
                buttons: {
                    Close: function (event) {
                        $("#_call_for_price_dialog").dialog('close');
                    }
                }
            });
            return;
        }
        if ($("#_call_for_price_dialog").length > 0 && $("#_call_for_price_dialog").hasClass('ui-dialog-content') && $('#_event_details').dialog('isOpen')) {
            $("#_call_for_price_dialog").dialog('close');
        }
        if (typeof ignoreAddToCart !== 'undefined' && ignoreAddToCart === true) {
            return false;
        }
        const buttonText = $(this).data("text");
        if (!empty(buttonText) && buttonText.toLowerCase().indexOf("login") >= 0) {
            document.location = "/login";
            return false;
        }
        if (empty(productId)) {
            return false;
        }
        if ($(this).hasClass("no-online-order")) {
            return;
        }
        const $addToCart = $(".add-to-cart-" + productId);
        let quantityField = $(this).data("quantity_field");
        const $quantityField = $("#" + quantityField);
        let quantity = 1;
        let cartMaximum = 0;
        if (!empty(quantityField)) {
            quantity = $quantityField.val();
            cartMaximum = $quantityField.data("cart_maximum");
        }
        if (!empty(cartMaximum)) {
            if (quantity > cartMaximum) {
                $quantityField.validationEngine("showPrompt", "Maximum quantity is " + cartMaximum, "error", "topRight", true);
                $quantityField.val(cartMaximum);
                quantity = cartMaximum;
            }
        }
        let cartMinimum = 0;
        if (!empty(quantityField)) {
            quantity = $quantityField.val();
            cartMinimum = $quantityField.data("cart_minimum");
        }
        if (!empty(cartMinimum)) {
            if (quantity < cartMinimum) {
                $quantityField.validationEngine("showPrompt", "Minimum quantity is " + cartMinimum, "error", "topRight", true);
                $quantityField.val(cartMinimum);
                quantity = cartMinimum;
            }
        }
        if (empty(quantity) || isNaN(quantity) || quantity < 0) {
            quantity = 1;
        }
        if ($(this).hasClass("product-group")) {
            $(this).closest(".catalog-item").find(".click-product-detail").trigger("click");
        } else if ($(this).hasClass("strict-map")) {
            emailForPrice(productId);
        } else {
            if ($(".add-to-cart-" + productId).length > 0) {
                $(".add-to-cart-" + productId).each(function () {
                    const addingText = $(this).data("adding_text");
                    if (!empty(addingText)) {
                        $(this).html(addingText);
                    }
                });
            }
            addProductToShoppingCart(productId, "", quantity);
        }
        return false;
    });

    $(document).on("click", ".out-of-stock", function () {
        if ($("body").hasClass("order-entry")) {
            return;
        }

        // noinspection JSUnresolvedVariable
        const productId = $(this).closest("[data-product_id]").data("product_id");
        if (!userLoggedIn) {
            if ($("#_non_user_out_of_stock_dialog").length == 0) {
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_non_user_out_of_stock_dialog", function (returnArray) {
                    if ("dialog" in returnArray) {
                        $("body").append(returnArray['dialog']);
                    }
                    $(".out-of-stock-" + productId).first().trigger("click");
                });
                return;
            }
            $("#non_user_out_of_stock_product_id").val(productId);
            $('#_non_user_out_of_stock_dialog').dialog({
                closeOnEscape: true,
                draggable: true,
                modal: true,
                resizable: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 600,
                title: 'Get Notified',
                buttons: {
                    Save: function (event) {
                        if ($("#_non_user_out_of_stock_form").validationEngine("validate")) {
                            loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_non_user_out_of_stock_notification", $("#_non_user_out_of_stock_form").serialize(), function (returnArray) {
                                $("#_non_user_out_of_stock_dialog").dialog('close');
                            });
                        }
                    },
                    Cancel: function (event) {
                        $("#_non_user_out_of_stock_dialog").dialog('close');
                    }
                }
            });
            return;
        }
        if ($("#_out_of_stock_dialog").length == 0) {
            loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_out_of_stock_dialog", function (returnArray) {
                if ("dialog" in returnArray) {
                    $("body").append(returnArray['dialog']);
                }
                $(".out-of-stock-" + productId).first().trigger("click");
            });
            return;
        }
        const $outOfStockDialog = $("#_out_of_stock_dialog");
        $outOfStockDialog.find("button.add-to-wishlist").attr("class", "add-to-wishlist add-to-wishlist-" + productId).data("product_id", productId);
        $outOfStockDialog.dialog({
            closeOnEscape: true,
            draggable: true,
            modal: true,
            resizable: true,
            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
            width: 600,
            title: 'Out of Stock',
            buttons: {
                Close: function (event) {
                    $outOfStockDialog.dialog('close');
                }
            }
        });
    });
    $("#_out_of_stock_dialog").find("button.add-to-wishlist").click(function () {
        $("#_out_of_stock_dialog").dialog('close');
    });
    $(document).on("click", ".add-to-wishlist", function () {
        // noinspection JSUnresolvedVariable
        const productId = $(this).data("product_id");
        if (!userLoggedIn) {
            if ($("#_create_user_dialog").length == 0) {
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_create_user_dialog", function (returnArray) {
                    if ("dialog" in returnArray) {
                        $("body").append(returnArray['dialog']);
                    }
                    $(".add-to-wishlist-" + productId).first().trigger("click");
                });
                return;
            }
            $('#_create_user_dialog').dialog({
                closeOnEscape: true,
                draggable: true,
                modal: true,
                resizable: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 600,
                title: 'User Benefits',
                buttons: {
                    Close: function (event) {
                        $("#_create_user_dialog").dialog('close');
                    }
                }
            });
            return;
        }
        const notifyWhenInStock = $(this).data("notify_when_in_stock");
        if (empty(productId)) {
            return false;
        }
        // If wishlist is already added, remove it
        if ($(this).html() === $(this).data("in_text")) {
            removeProductFromWishList(productId);
        } else {
            addProductToWishList(productId, "", (!empty(notifyWhenInStock)));
        }
        return false;
    });

    $(document).on("click", ".shopping-cart-item-decrease-quantity,.shopping-cart-item-increase-quantity", function () {
        let quantity = 0;
        if ($(this).closest(".shopping-cart-item").find(".product-quantity").is("input")) {
            quantity = parseInt($(this).closest(".shopping-cart-item").find(".product-quantity").val());
        } else {
            quantity = parseInt($(this).closest(".shopping-cart-item").find(".product-quantity").html());
        }
        const shoppingCartItemId = $(this).closest(".shopping-cart-item").data("shopping_cart_item_id");
        const productId = $(this).closest(".shopping-cart-item").data("product_id");
        const addOn = $(this).data("amount");
        quantity += addOn;
        const cartMaximum = $(this).closest(".shopping-cart-item").find(".product-quantity").data("cart_maximum");
        if (!empty(cartMaximum) && quantity > cartMaximum) {
            return;
        }
        const cartMinimum = $(this).closest(".shopping-cart-item").find(".product-quantity").data("cart_minimum");
        if (!empty(cartMinimum) && quantity > 0 && cartMinimum > 0 && quantity < cartMinimum) {
            return;
        }
        if ($(this).closest(".shopping-cart-item").find(".product-quantity").is("input")) {
            $(this).closest(".shopping-cart-item").find(".product-quantity").val(quantity);
        } else {
            $(this).closest(".shopping-cart-item").find(".product-quantity").html(quantity);
        }
        const shoppingCartCode = $(this).data("shopping_cart_code");
        if (empty(shoppingCartItemId)) {
            addProductToShoppingCart(productId, (empty(shoppingCartCode) ? "" : shoppingCartCode), addOn, false, $(this).data("contact_id"));
        } else {
            updateShoppingCartItem(shoppingCartItemId, (empty(shoppingCartCode) ? "" : shoppingCartCode), addOn, false, $(this).data("contact_id"));
        }
        if ("getTaxCharge" in window) {
            getTaxCharge();
        }
    });

    $(document).on("change", "input.product-quantity", function () {
        let quantity = $(this).val();
        if (isNaN(quantity)) {
            quantity = 1;
            $(this).val(quantity);
        }
        if (quantity < 0) {
            quantity = 0;
            $(this).val(quantity);
        }
        const productId = $(this).closest(".shopping-cart-item").data("product_id");
        const cartMaximum = $(this).closest(".shopping-cart-item").find(".product-quantity").data("cart_maximum");
        if (!empty(cartMaximum) && quantity > cartMaximum) {
            $(this).val(cartMaximum);
        }
        const cartMinimum = $(this).closest(".shopping-cart-item").find(".product-quantity").data("cart_minimum");
        if (!empty(cartMinimum) && quantity > 0 && cartMinimum > 0 && quantity < cartMinimum) {
            $(this).val(cartMinimum);
        }
        const shoppingCartCode = $(this).data("shopping_cart_code");
        addProductToShoppingCart(productId, (empty(shoppingCartCode) ? "" : shoppingCartCode), quantity, true, $(this).data("contact_id"));
    });

    $(document).on("click", ".copy-text-icon", function (event) {
        event.stopPropagation();
        event.preventDefault();
        var $tempElement = $("<input>");
        $("body").append($tempElement);
        $tempElement.val($(this).closest("div").find(".copy-text").html()).select();
        document.execCommand("Copy");
        $tempElement.remove();
    });

    $("#add_product").keypress(function (event) {
        var thisChar = String.fromCharCode(event.which);
        if (!empty($(this).data("swipe_string"))) {
            if (event.which === 13) {
                let swipeString = $(this).data("swipe_string");
                $(this).data("swipe_string", "");
                $("#continue_checkout").trigger("click");
                $(".section-chooser-option.finalize-section-chooser").trigger("click");
                let shippingMethodId = "";
                $("#shipping_method_id").find("option").each(function () {
                    if ($(this).data("shipping_method_code") == "PICKUP") {
                        shippingMethodId = $(this).val();
                        return false;
                    }
                });
                if (!empty(shippingMethodId)) {
                    $("#shipping_method_id").val(shippingMethodId).trigger("change");
                    $("#shipping_information_section").addClass("internal-pickup");
                }
                processMagneticData(swipeString, true);
                $("#account_number").trigger("change");
            } else {
                $(this).data("swipe_string", $(this).data("swipe_string") + thisChar);
            }
            return false;
        } else {
            if (thisChar === "%" && empty($(this).val())) {
                $(this).data("swipe_string", "%");
                setTimeout(function () {
                    if ($(this).data('swipe_string') === "%") {
                        $(this).data('swipe_string', "");
                    }
                }, 3000);
                return false;
            } else {
                return true;
            }
        }
    });
    $("#add_product").keyup(function (event) {
        if (event.which === 13 || event.which === 3) {
            $(this).trigger("change");
        } else {
            const thisValue = $(this).val();
            if (thisValue.length === 13 && !isNaN(thisValue)) {
                $(this).trigger("change");
            }
        }
    });

    $(document).on("change", "#add_product", function () {
        const productSearchValue = $(this).val().trim().toLowerCase();
        $(this).val("");
        if (productSearchValue == "cash" || productSearchValue == "check") {
            const paymentMethodCode = productSearchValue.toUpperCase();
            let shippingMethodId = "";
            $("#shipping_method_id").find("option").each(function () {
                if ($(this).data("shipping_method_code") == "PICKUP") {
                    shippingMethodId = $(this).val();
                    return false;
                }
            });
            let paymentMethodId = "";
            $("#payment_method_id").find("option").each(function () {
                if ($(this).data("payment_method_code") == paymentMethodCode) {
                    paymentMethodId = $(this).val();
                    return false;
                }
            });
            if (!empty(shippingMethodId) && !empty(paymentMethodId)) {
                $("#continue_checkout").trigger("click");
                $(".section-chooser-option.finalize-section-chooser").trigger("click");
                $("#first_name").val("Cash");
                $("#last_name").val("Sale");
                $("#payment_method_id").val(paymentMethodId).trigger("change");
                $("#address_id").val("0");
                $("#shipping_method_id").val(shippingMethodId).trigger("change");
                $("#shipping_information_section").addClass("internal-pickup");
            }
            return false;
        }
        if (!empty(productSearchValue)) {
            loadAjaxRequest("/retail-store-controller?ajax=true&url_action=add_product_code&product_search_value=" + encodeURIComponent(productSearchValue), function (returnArray) {
                getShoppingCartItems($("#shopping_cart_code").val());
            });
        }
    });

    $("#promotion_code").keyup(function (event) {
        if (event.which === 13 || event.which === 3) {
            $(this).trigger("change");
        }
    });

    $(document).on("change", "#promotion_code", function () {
        $("#promotion_code_details").html("");
        $("#promotion_code_description").html("");
        $("#promotion_code_details").addClass("hidden");
        $("#show_promotion_code_details").addClass("hidden");
        $("#_promotion_applied_message").addClass("hidden");
        const shoppingCartCode = $(this).data("shopping_cart_code");
        const contactId = $(this).data("contact_id");
        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=add_promotion_code&promotion_code=" + encodeURIComponent($(this).val()) + "&shopping_cart_code=" + (empty(shoppingCartCode) ? "" : shoppingCartCode) + "&contact_id=" + (empty(contactId) ? "" : contactId), function (returnArray) {
            if ("promotion_code" in returnArray && !empty(returnArray['promotion_code'])) {
                $("#promotion_id").val(returnArray['promotion_id']);
                $("#promotion_code").val(returnArray['promotion_code']);
                $(".promotion-code").html(returnArray['promotion_code']);
				$("#_promotion_code_wrapper").addClass("hidden");
				$("#_promotion_message").addClass("hidden");
				$("#_promotion_applied_message").removeClass("hidden");
				$("#added_promotion_code").removeClass("hidden");
				$("#add_promotion_code").addClass("hidden");
            }
            if ("promotion_code_details" in returnArray) {
                $("#promotion_code_description").html(returnArray['promotion_code_description']);
                $("#promotion_code_details").html(returnArray['promotion_code_details']);
                if (empty(returnArray['promotion_code_details'])) {
                    $("#promotion_code_details").addClass("hidden");
                    $("#show_promotion_code_details").addClass("hidden");
                } else {
                    $("#promotion_code_details").removeClass("hidden");
                    $("#show_promotion_code_details").removeClass("hidden");
                }
            }
			getShoppingCartItems(shoppingCartCode, contactId);
        });
    });

    $(document).on("click", ".set-default-location", function () {
        const locationCode = $(this).data("location_code");
        setDefaultLocation(locationCode);
        return false;
    });

    $(document).on("contextmenu", ".catalog-item", function (event) {
        event.preventDefault();
        $(".right-click-menu").data("target_element", $(this).attr("id"));
        $(".right-click-menu").finish().toggle(100).css({
            top: event.pageY + "px",
            left: event.pageX + "px"
        });
    });

    $(document).bind("mousedown", function (event) {
        if (!$(event.target).parents(".right-click-menu").length > 0) {
            $(".right-click-menu").hide(100);
        }
    });

    $(document).on("click", ".right-click-menu li", function () {
        switch ($(this).attr("data-action")) {
            case "open":
                const targetElement = $(".right-click-menu").data("target_element");
                if (empty(targetElement) || $("#" + targetElement).length == 0 || $("#" + targetElement).find(".product-detail-link").length == 0) {
                    break;
                }
                const productDetailLink = $("#" + targetElement).find(".product-detail-link").val();
                if (empty(productDetailLink)) {
                    break;
                }
                window.open(productDetailLink);
                break;
        }

        $(".right-click-menu").hide(100);
    });

    if ($(".right-click-menu").length == 0) {
        $("body").append("<ul class='right-click-menu'><li data-action = \"open\">Open details in new tab</li></ul>");
    }
    getShoppingCartItemCount();
});

function emailForPrice(productId) {
    if ($("#_email_for_price_quote_dialog").length == 0) {
        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_email_for_price_dialog", $("#_email_for_price_form").serialize(), function (returnArray) {
            if ("dialog" in returnArray) {
                $("body").append(returnArray['dialog']);
            }
            emailForPrice(productId);
        });
        return;
    }
    const $emailForPriceForm = $("#_email_for_price_form");
    const $emailForPriceQuoteDialog = $('#_email_for_price_quote_dialog');
    $emailForPriceForm.clearForm();
    $("#email_for_price_product_id").val(productId);
    $emailForPriceQuoteDialog.dialog({
        closeOnEscape: true,
        draggable: true,
        modal: true,
        resizable: true,
        position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
        width: 600,
        title: 'Email for Price',
        buttons: {
            "Get Quote": function (event) {
                if ($emailForPriceForm.validationEngine("validate")) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=email_for_price", $emailForPriceForm.serialize(), function (returnArray) {
                        if ("add_to_cart" in returnArray) {
                            addProductToShoppingCart(productId);
                        }
                    });
                    $emailForPriceQuoteDialog.dialog('close');
                }
            },
            "Add To Cart": function (event) {
                const $addToCart = $(".add-to-cart-" + productId);
                if ($addToCart.length > 0) {
                    $addToCart.each(function () {
                        const addingText = $(this).data("adding_text");
                        if (!empty(addingText)) {
                            $(this).html(addingText);
                        }
                    });
                }
                addProductToShoppingCart(productId);
                $emailForPriceQuoteDialog.dialog('close');
            }
        }
    });
}

function getRelatedResultTemplate(afterFunction) {
    if ($("#_related_product_result").length > 0) {
        return;
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_related_result_html", function (returnArray) {
        if ("related_result_html" in returnArray) {
            $("#_templates").append(returnArray['related_result_html']);
            if (!empty(afterFunction)) {
                setTimeout(function () {
                    afterFunction();
                }, 100);
            }
        }
    });
}

function getCatalogResultTemplate(afterFunction) {
    if ($("#_catalog_result").length > 0) {
        return;
    }
    if ($(window).width() < 1050) {
        $.cookie("result_display_type", "tile", { expires: 365, path: '/' });
        $(".result-display-type").removeClass("selected");
        $("#result_display_type_tile").addClass("selected");
    }
    if ($(window).width() < 1050) {
        forceTile = true
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_catalog_result_html&force_tile=" + (forceTile ? "true" : ""), function (returnArray) {
        if ("catalog_result_html" in returnArray) {
            $("#_templates").append(returnArray['catalog_result_html']);
            if (!empty(afterFunction)) {
                setTimeout(function () {
                    afterFunction();
                }, 100);
            }
        }
    });
}

function getDefaultLocation() {
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_default_location", function (returnArray) {
        if ("default_location_id" in returnArray) {
            $.cookie("default_location_id", returnArray['default_location_id'], { expires: 365 * 10, path: '/' });
            if (empty(returnArray['default_location_id'])) {
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(function (position) {
                        const currentLatitude = position.coords.latitude;
                        const currentLongitude = position.coords.longitude;
                        setDefaultLocation("", currentLatitude, currentLongitude);
                    }, function (error) {
                        setDefaultLocation("", 0, 0);
                    });
                }
            } else {
                autofillLocation(returnArray);
            }
            if (typeof afterGetDefaultLocation == "function") {
                setTimeout(function () {
                    afterGetDefaultLocation()
                }, 100);
            }
        }
    });
}

function setDefaultLocation(locationCode, currentLatitude, currentLongitude) {
    if (!$("body").hasClass("waiting-for-ajax")) {
        $("body").addClass("no-waiting-for-ajax");
    }
    if (empty(currentLatitude)) {
        currentLatitude = "";
    }
    if (empty(currentLongitude)) {
        currentLongitude = "";
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=set_default_location", { location_code: locationCode, latitude: currentLatitude, longitude: currentLongitude }, function (returnArray) {
        if ("default_location_id" in returnArray) {
            $.cookie("default_location_id", returnArray['default_location_id'], { expires: 365 * 12, path: '/' });
            autofillLocation(returnArray);
            if (typeof afterSetDefaultLocation === "function") {
                afterSetDefaultLocation();
            }
        }
    });
}

function autofillLocation(locationRow) {
    if ("default_location_id" in locationRow) {
        if ("business_name" in locationRow) {
            $(".autofill-location-business_name").html(locationRow['business_name']).removeClass("hidden");
        }
        if ("location_description" in locationRow) {
            $(".autofill-location-description").html(locationRow['location_description']).removeClass("hidden");
        }
        if ("location_link" in locationRow) {
            $(".autofill-location-link").attr("href", locationRow['location_link']);
        }
        if ("address_1" in locationRow) {
            $(".autofill-location-address1").html(locationRow['address_1']);
        }
        if ("address_2" in locationRow) {
            $(".autofill-location-address2").html(locationRow['address_2']);
        }
        if ("city" in locationRow && "state" in locationRow) {
            $(".autofill-location-city").html(locationRow['city'] + ', ' + locationRow['state'] + ' ' + locationRow['postal_code']);
        }
        if ("phone_number" in locationRow) {
            $(".autofill-location-phone").html(locationRow['phone_number']);
        }
        if ("distance" in locationRow) {
            $(".autofill-location-distance").html(locationRow['distance']);
        }
        if ("address_block" in locationRow) {
            $(".autofill-location-address_block").html(locationRow['address_block']);
        }
    }
}

function displayProductDetails(returnArray) {
    windowScroll = $(window).scrollTop();
    $("#_search_result_details_wrapper").html("<div id='_search_result_close_details_wrapper'><span class='fas fa-times'></span></div>" + returnArray['content']).removeClass("hidden").siblings().addClass("hidden");
    $("#_search_result_details_wrapper").find("a[href^='http']").not("a[rel^='prettyPhoto']").not(".same-page").attr("target", "_blank");
    if (!("adminLoggedIn" in window) || !adminLoggedIn) {
        $("#_search_result_details_wrapper").find(".admin-logged-in").remove();
    }
    if (!("userLoggedIn" in window) || !userLoggedIn) {
        $("#_search_result_details_wrapper").find(".user-logged-in").remove();
    }

    setTimeout(function() {
        $(window).scrollTop(0);
    },100);
    history.pushState("", document.title, returnArray['link_url']);
    document.title = returnArray['page_title'];
    if (detailIntervalTimer !== false) {
        clearInterval(detailIntervalTimer);
    }
    detailIntervalTimer = setInterval(function () {
        if (window.location.hash != "#product_detail") {
            if (!empty($("#_search_result_details_wrapper").data("keep_open"))) {
                return;
            }
            $("#_search_result_details_wrapper").html("").addClass("hidden").siblings().removeClass("hidden");
            setTimeout(function () {
                $(window).scrollTop(windowScroll);
            }, 100);
            clearInterval(detailIntervalTimer);
        }
    }, 100);
    showCredovaMessages();
    if (typeof afterDisplayProductDetails == "function") {
        setTimeout(function () {
            afterDisplayProductDetails()
        }, 100);
    }
}

function compareProducts() {
    let productCount = 0;
    let productString = "";
    $(".catalog-item-compare:checkbox:checked").each(function () {
        productCount++;
        productString += (empty(productString) ? "" : "|") + $(this).closest(".catalog-item").data("product_id");
    });
    if (productCount < 2) {
        displayErrorMessage("Select at least two products");
        return;
    }
    if ($("#compare_dialog").length == 0) {
        $("body").append("<div id='_compare_products_dialog' class='dialog-box'><div id='compare_products_data'></div></div>");
    }
    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=compare_products&product_ids=" + encodeURIComponent(productString), function (returnArray) {
        if ("compare_products_data" in returnArray) {
            $("#compare_products_data").html(returnArray['compare_products_data']);
            $('#_compare_products_dialog').dialog({
                closeOnEscape: true,
                draggable: true,
                modal: true,
                resizable: true,
                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                width: 1200,
                title: 'Compare Products',
                buttons: {
                    Close: function (event) {
                        $("#_compare_products_dialog").dialog('close');
                    }
                }
            });
        }
    });
}

function sendAnalyticsEvent(event, eventData) {
    if(typeof eventData == "undefined" && typeof event == "object") {
        eventData = event['event_data'];
        event = event['event'];
        eventData.productKey = eventData['product_key'];
        eventData.productName = eventData['product_name'];
        eventData.productPrice = eventData['sale_price'];
        eventData.productManufacturer = eventData['manufacturer_name'];
        eventData.productCategory = eventData['product_category'];
        eventData.searchTerm = eventData['search_term'];
    }
    // if the product in eventData isn't the same as productDetailData (e.g. remove from cart), don't use productDetailData
    if(productDetailData != false && productDetailData.productKey != eventData.productKey) {
        productDetailData = {};
    }
    // populate product data from search results if necessary
    if (productDetailData === false && !empty(productResults) && !empty(productKeyLookup)) {
        productDetailData = {};
        productDetailData.productKey = eventData.productKey;
        let index = false;
        for (let x = 0; x < productResults.length; x++) {
            if (productResults[x][0] == eventData.productId) {
                index = x;
                break;
            }
        }
        if (index !== false) {
            productDetailData.productName = productResults[index][productKeyLookup['description']];
            productDetailData.productPrice = productResults[index][productKeyLookup['sale_price']];
            if (typeof productResults[index][productKeyLookup['product_category_ids']] !== "undefined") {
                let firstCategoryId = productResults[index][productKeyLookup['product_category_ids']].split(",")[0];
                if (("productCategories" in window) && !empty(productCategories)) {
                    for (let c = 0; c < productCategories.length; c++) {
                        if (productCategories[c]['id'] == firstCategoryId) {
                            productDetailData.productCategory = productCategories[c]['description'];
                            break;
                        }
                    }
                }
            }
            let productManufacturerId = productResults[index][productKeyLookup['product_manufacturer_id']];
            if (productManufacturerId in manufacturerNames) {
                productDetailData.productManufacturer = manufacturerNames[productManufacturerId][0];
            }
        }
    }
    let setQuantity = false;
    if (typeof eventData.setQuantity !== 'undefined') {
        setQuantity = eventData.setQuantity;
    }
    let price = 0.0;
    switch (event) {
        case "search":
            if (typeof zaius !== 'undefined' && !empty(zaius)) {
                zaius.event("navigation", { action: "search", search_term: eventData.searchTerm, category: "" });
            }
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                dataLayer.push({
                    event: "search",
                    search_term: eventData.searchTerm
                });
            }
            break;
        case "detail":
            if (typeof zaius !== 'undefined' && !empty(zaius)) {
                zaius.event("product", { action: "detail", product_id: eventData.productKey });
            }
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                price = 0.0
                if(typeof eventData.productPrice != "undefined") {
                    price = parseFloat(eventData.productPrice.toString().replace("$", "").replace(/,/g, ""));
                }

                dataLayer.push({
                    event: "view_item",
                    ecommerce: {
                        currency: "USD",
                        value: price,
                        items: [
                            {
                                item_id: eventData.productKey,
                                item_name: eventData.productName,
                                item_brand: eventData.productManufacturer,
                                item_category: eventData.productCategory,
                                price: price,
                                quantity: 1
                            }
                        ]
                    }
                });
            } else if (typeof dataLayer !== 'undefined') {
                dataLayer.push({
                    'event': 'eec.detail',
                    'ecommerce': {
                        'detail': {
                            'products': [ {
                                'id': eventData.productKey,
                                'name': eventData.productName,
                                'price': eventData.productPrice,
                                'brand': eventData.productManufacturer,
                                'category': eventData.productCategory
                            } ]
                        }
                    }
                });
            }
            if (typeof _ltk !== 'undefined') {
                let sku = empty(eventData.upcCode) ? eventData.productId : eventData.upcCode;
                _ltk.Activity.AddProductBrowse(sku);
            }
            break;
        case "add_to_cart":
            if (typeof zaius !== 'undefined' && !empty(zaius) && !setQuantity) {
                zaius.event("product", { action: "add_to_cart", product_id: eventData.productKey });
            }
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4)) && !setQuantity) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                dataLayer.push({
                    event: "add_to_cart",
                    ecommerce: {
                        currency: "USD",
                        value: eventData.productPrice * eventData.quantity,
                        items: [
                            {
                                item_id: eventData.productKey,
                                item_name: eventData.productName,
                                item_brand: eventData.productManufacturer,
                                item_category: eventData.productCategory,
                                price: eventData.productPrice,
                                quantity: eventData.quantity
                            }
                        ]
                    }
                });
            } else if (typeof dataLayer !== 'undefined' && !setQuantity) {
                dataLayer.push({
                    'event': 'eec.add',
                    'ecommerce': {
                        'add': {
                            'products': [ {
                                'id': eventData.productKey,
                                'name': eventData.productName,
                                'price': eventData.productPrice,
                                'brand': eventData.productManufacturer,
                                'category': eventData.productCategory
                            } ]
                        }
                    }
                });
            }
            if (typeof _ltk !== 'undefined') {
                for (let productId in eventData.items) {
                    let sku = empty(eventData.items[productId]['upc_code']) ? productId : eventData.items[productId]['upc_code'];
                    _ltk.SCA.AddItemWithLinks(sku, eventData.items[productId]['quantity'], eventData.items[productId]['sale_price']);
                }
                _ltk.SCA.Submit();
            }
            break;
        case "remove_from_cart":
            if (typeof zaius !== 'undefined' && !empty(zaius) && !setQuantity) {
                zaius.event("product", { action: "remove_from_cart", product_id: eventData.productKey });
            }
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                dataLayer.push({
                    event: "remove_from_cart",
                    ecommerce: {
                        currency: 'USD',
                        items: {
                            item_id: eventData.productKey,
                            item_name: eventData.productName,
                            item_brand: eventData.productManufacturer,
                            item_category: eventData.productCategory
                        }
                    }
                });
            } else if (typeof dataLayer !== 'undefined' && !setQuantity) {
                dataLayer.push({
                    'event': 'eec.remove',
                    'ecommerce': {
                        'remove': {
                            'products': [ {
                                'id': eventData.productKey,
                                'name': eventData.productName,
                                'price': eventData.productPrice,
                                'brand': eventData.productManufacturer,
                                'category': eventData.productCategory
                            } ]
                        }
                    }
                });
            }
            if (typeof _ltk !== 'undefined') {
                if (typeof eventData.items == 'object' && Object.keys(eventData.items).length == 0) {
                    _ltk.SCA.ClearCart();
                }
                for (let productId in eventData.items) {
                    let sku = empty(eventData.items[productId]['upc_code']) ? productId : eventData.items[productId]['upc_code'];
                    _ltk.SCA.AddItemWithLinks(sku, eventData.items[productId]['quantity'], eventData.items[productId]['sale_price']);
                }
                _ltk.SCA.Submit();
            }
            break;
        case "add_to_wishlist":
            if (typeof zaius !== 'undefined' && !empty(zaius)) {
                zaius.event("product", { action: "add_to_wishlist", product_id: eventData.productKey });
            }
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                dataLayer.push({
                    event: "add_to_wishlist",
                    ecommerce: {
                        currency: 'USD',
                        value: eventData.productPrice,
                        items: {
                            item_id: eventData.productKey,
                            item_name: eventData.productName,
                            item_brand: eventData.productManufacturer,
                            item_category: eventData.productCategory
                        }
                    }
                });
            }
            break;
        case "remove_from_wishlist":
            if (typeof zaius !== 'undefined' && !empty(zaius)) {
                zaius.event("product", { action: "remove_from_wishlist", product_id: eventData.productKey });
            }
            break;
        case "checkout":
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                dataLayer.push({
                    event: "begin_checkout"
                });
            } else if (typeof dataLayer !== 'undefined') {
                dataLayer.push({
                    'event': 'eec.checkout',
                    'ecommerce': {
                        'checkout': {
                            'actionField': { 'step': 1, 'option': '' },
                        }
                    }
                });
            }
            break;
        case "purchase":
            if(!"orderItems" in eventData) {
                break;
            }
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                let transactionProducts = [];
                for (let i in eventData.orderItems) {
                    price = 0.0;
                    if(typeof eventData.orderItems[i].salePrice != "undefined") {
                        price = parseFloat(eventData.orderItems[i].salePrice.toString().replace("$", "").replace(/,/g, ""));
                    }
                    quantity = parseInt(eventData.orderItems[i].quantity);
                    transactionProducts.push({
                        item_id: eventData.orderItems[i].upcCode,
                        item_name: eventData.orderItems[i].description,
                        price: price,
                        item_brand: eventData.orderItems[i].productManufacturer,
                        item_category: eventData.orderItems[i].productCategory,
                        quantity: quantity
                    });
                }
                dataLayer.push({
                    event: "purchase",
                    ecommerce: {
                        transaction_id: eventData.orderId,
                        currency: 'USD',
                        value: eventData.orderTotal,
                        tax: eventData.taxCharge,
                        shipping: eventData.shippingCharge,
                        coupon: eventData.promotionCode,
                        items: transactionProducts
                    }});
            } else if (typeof dataLayer !== 'undefined') {
                let transactionProducts = [];
                for (var i in eventData.orderItems) {
                    transactionProducts.push({
                        'id': eventData.orderItems[i].upcCode,
                        'name': eventData.orderItems[i].description,
                        'price': eventData.orderItems[i].salePrice,
                        'brand': eventData.orderItems[i].productManufacturer,
                        'category': eventData.orderItems[i].productCategory,
                        'quantity': eventData.orderItems[i].quantity
                    });
                }
                let ecomData = {
                    'purchase': {
                        'actionField': {
                            'id': eventData.orderId,
                            'revenue': eventData.orderTotal,
                            'tax':eventData.taxCharge,
                            'shipping': eventData.shippingCharge,
                        },
                        'products': transactionProducts
                    }
                };
                dataLayer.push({'event':'eec.purchase', 'ecommerce':ecomData});
            }

            if(typeof _ltk != 'undefined') {
                _ltk.Order.SetCustomer(eventData.customerEmail, eventData.customerFirstName, eventData.customerLastName);
                _ltk.Order.OrderNumber = eventData.orderId;
                _ltk.Order.ItemTotal = eventData.cartTotal;
                _ltk.Order.ShippingTotal = eventData.shippingCharge;
                _ltk.Order.TaxTotal = eventData.taxCharge;
                _ltk.Order.HandlingTotal = eventData.handlingCharge;
                _ltk.Order.OrderTotal = eventData.orderTotal;

                for(i in eventData.orderItems) {
                    _ltk.Order.AddItem(eventData.orderItems[i].upcCode, eventData.orderItems[i].quantity, eventData.orderItems[i].salePrice);
                }

                _ltk.Order.Submit();
            }
            break;
        case "load_cart":
            if (typeof _ltk !== 'undefined') {
                for (let productId in eventData.items) {
                    let sku = empty(eventData.items[productId]['upc_code']) ? productId : eventData.items[productId]['upc_code'];
                    _ltk.SCA.AddItemWithLinks(sku, eventData.items[productId]['quantity'], eventData.items[productId]['sale_price']);
                }
                _ltk.SCA.Submit();
            }
            break;
        default: // support for custom events
            if (typeof dataLayer !== "undefined" && (!("noGA4" in window) || empty(noGA4))) {
                dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
                if("loggedInUserId" in window && !empty(loggedInUserId)) {
                    dataLayer.push({user_id: loggedInUserId});
                }
                eventData.event = event;
                dataLayer.push(eventData);
            }
            break;
    }
}
