const API_KEY = "AIzaSyBr8gKoGkFoia4pH2MY_cM0USpwV8YAieQ";

let mapApiLoadInitialized = false;
let mapMarkers = new Map();
secondsToClearMessage = 3;

let dealersCache = [];
let dealersCurrentPage = 1;
let dealersPageSize = 50;
let dealersSelectionMessage = "Stores near";

let dealerTooltipInitialized = false;
let dealerPopperInstance;
let currentLocationPostalCode = -1;

let ezLocatorNav;
let recognizedDealersLoaded = false;
let brandsLoaded = false;
let departmentsLoaded = false;
let dealerSelected = false;
let dealerSelectedPending = false;
let dealerName = "";
let dealerAddress = "";

let dealerSearchProductId;
let distributorInventoryQuantity;

function clearSelectedLocation() {
    $.ajax({
        url: "/retail-store-controller?ajax=true&url_action=remove_default_location",
        type: "GET",
        timeout: 30000,
        success: function (returnText) {
            setSelectedFacetsAsCookies();
            location.reload();
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
        },
        dataType: "text"
    });
}

function updateDealerRelatedElementsVisibility() {
    $("#_item_details .location-selected").addClass("hidden");
    $("#_item_details .no-location-selected").removeClass("hidden");
    $("#_item_details .catalog-item-contact-dealer").addClass("hidden");
    $("#_item_details .catalog-item-add-to-cart").addClass("hidden");
    $("#_item_details .catalog-item-out-of-stock").addClass("hidden");
    $("#_item_details .catalog-item-instant-quote").removeClass("hidden");
    $("#_item_details .catalog-item-compare-prices").removeClass("hidden");
    if (dealerSelected) {
        if (dealerSelectedPending) {
            $(".catalog-item-contact-dealer").removeClass("hidden");
        } else {
            $('.catalog-item-add-to-cart').removeClass('hidden');
            $(".catalog-item-out-of-stock").removeClass("hidden");
        }
        $(".location-selected").removeClass("hidden");
        $(".location-selected").css("display","block");
        $(".no-location-selected").addClass("hidden");
        $(".catalog-item-instant-quote").addClass("hidden");
        $(".catalog-item-compare-prices").addClass("hidden");
    } else {
        $(".location-selected").addClass("hidden");
        $(".no-location-selected").removeClass("hidden");
        $('.catalog-item-add-to-cart').addClass('hidden');
        $('.catalog-item-out-of-stock').addClass('hidden');
        $('.catalog-item-instant-quote').removeClass('hidden');
    }
}

function resetDealerSelected() {
    updateDealerRelatedElementsVisibility();
    if (!dealerSelected && $("#_sidebar_add_location").length === 0) {
        $("#selected_filters").append(`<div id="_sidebar_add_location" class="selected-filter not-removable"><span>Add Location</span></div>`);
    }
    setupDealerSelection();
}

$(function () {
    $("#_item_details .location-selected").addClass("hidden");
    setupNav();
    let productId = $("#product_id").val();
    $.ajax({
        url: "/retail-store-controller?ajax=true&url_action=get_ffl_dealers",
        type: "POST",
        data: {default_location: true, json_mode: true, has_active_subscription: true, has_location: true, product_id: productId},
        timeout: 30000,
        success: function (returnText) {
            var returnArray = processReturn(returnText);
            if (returnArray === false) {
                return;
            }
            if ("ffl_dealers" in returnArray && returnArray['ffl_dealers'].length > 0) {
                for (var i in returnArray['ffl_dealers']) {
                    const fflDealer = returnArray['ffl_dealers'][i];
                    dealerSelected = true;
                    dealerSelectedPending = fflDealer.approved && empty(fflDealer.merchant_account_id);
                    updateFflDealerInfoContents(fflDealer);
                    break;
                }
            } else {
                dealerSelected = false;
                dealerSelectedPending = false;
            }
            resetDealerSelected();
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
        },
        dataType: "text"
    });

    $(document).on("click", ".instant-quote, .compare-dealer-prices", function (event) {
        let productId = $(this).data("product_id");
        if (empty(productId)) {
            productId = $("#product_id").val();
        }
        openFflDealerModal(productId);
        event.stopPropagation();
        return false;
    });

    $(document).on("click", ".contact-dealer", function (event) {
        $('#_dealer_information_dialog').dialog({
            closeOnEscape: true,
            draggable: false,
            modal: true,
            resizable: false,
            position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
            width: 500,
            title: 'Dealer Information',
        });
        return false;
    });

    // Back to top
    var backToTop = $("#back-to-top");

    $(window).scroll(function () {
        var scroll = $(this).scrollTop();
        if (scroll >= 500) {
            backToTop.fadeIn(200);
        } else {
            backToTop.fadeOut(200);
        }
    });

    backToTop.click(function () {
        $("body,html").animate({scrollTop: 0}, 500);
    });

    $("#search_text").keyup(function (event) {
        if (event.which == 13) {
            $("#_search_products_submit").trigger("click");
        }
    });

    $(document).on("click", "#_close_mini_cart_button", function () {
        $("#_shopping_cart_modal").removeClass("shown");
    });

    $(document).on("change", "input.mini-cart-item-quantity-number", function () {
        let quantity = $(this).val();
        if (isNaN(quantity)) {
            quantity = 1;
            $(this).val(quantity);
        }
        if (quantity < 0) {
            quantity = 0;
            $(this).val(quantity);
        }

        const productId = $(this).closest(".mini-cart-item-wrapper").data("product_id");
        const cartMaximum = $(this).data("cart_maximum");

        if (!empty(cartMaximum) && quantity > cartMaximum) {
            $(this).val(cartMaximum);
        }
        addProductToShoppingCart(productId, "", quantity, true);
    });

    $(document).on("click", ".mini-cart-item-increase-quantity,.mini-cart-item-decrease-quantity", function () {
        let quantity = 1;
        if ($(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").is("input")) {
            quantity = parseInt($(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").val()
);
        } else {
            quantity = parseInt($(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").html(
));
        }
        const productId = $(this).closest(".mini-cart-item-wrapper").data("product_id");
        const addOn = $(this).data("amount");

        quantity += addOn;
        const cartMaximum = $(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").data("cart_maximum");
        if (!empty(cartMaximum) && quantity > cartMaximum) {
            return;
        }
        if ($(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").is("input")) {
            $(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").val(quantity);
        } else {
            $(this).closest(".mini-cart-item-wrapper").find(".mini-cart-item-quantity-number").html(quantity);
        }
        if (empty(quantity) || quantity < 0) {
            $(this).closest(".mini-cart-item-wrapper").remove();
            quantity = 0;
            removeProductFromShoppingCart(productId);
        } else {
            addProductToShoppingCart(productId, "", quantity, true);
        }
        calculateMiniCartTotal();

        if ($(".add-to-cart-" + productId).length > 0) {
            $(".add-to-cart-" + productId).each(function () {
                let inText = $(this).data("in_text");
                let normalText = $(this).data("text");

                if (!empty(inText)) {
                    $(this).html(quantity === 0 ? normalText : inText);
                }
            });
        }
    });

    $("#_wrapper").removeClass("hidden");

    $(document).on("click",".add-to-wishlist", function () {
        displayInfoMessage(!$(this).find("i").hasClass("added") ? "Added to Wishlist" : "Removed from Wishlist",true);
    });

    setupEZLocator();
    setupBannerSponsorPopups();
});

function setupNav() {
    // Issue with hcOffcanvasNav if there are multiple classes on list items
    //This issue seems to be specific to newer versions of hc-offcanvas (6+)
    // $("#_navbar_menu li").removeClass();

    var Nav = new hcOffcanvasNav("#_navbar_menu", {
        customToggle: "#_mobile_icon",
        disableBody: true,
        width: 300,
        disableAt: 640,
        swipeGestures: false,
        levelOpen: "expand",
        position: "right",
        labelClose: "Close Menu",
    });
}

function afterAddToCart() {
    fillMiniCart(true);
}

function fillMiniCart(showCart) {
    $.ajax({
        url: "/retail-store-controller?ajax=true&url_action=get_shopping_cart_items",
        type: "GET",
        timeout: 30000,
        success: function (returnText) {
            var returnArray = processReturn(returnText);
            if (returnArray === false) {
                return;
            }

            if (!empty(showCart)) {
                $("#_shopping_cart_modal").addClass("shown");
            }

            if (!("shopping_cart_items" in returnArray)) {
                return;
            }

            $("#mini_cart_items_wrapper").html("");

            for (var j in returnArray["shopping_cart_items"]) {
                var productId = returnArray["shopping_cart_items"][j]["product_id"];
                var itemBlock = $("#_mini_cart_item_block").html().replace(new RegExp("%image_src%", "ig"), "src");

                for (var i in returnArray["shopping_cart_items"][j]) {
                    itemBlock = itemBlock.replace(new RegExp("%" + i + "%", "ig"), returnArray["shopping_cart_items"][j][i]);
                }

                $("#mini_cart_items_wrapper").append(itemBlock);

                if ($(".add-to-cart-" + productId).length > 0) {
                    $(".add-to-cart-" + productId).each(function () {
                        var inText = $(this).data("in_text");
                        if (!empty(inText)) {
                            $(this).html(inText);
                        }
                    });
                }
            }

            calculateMiniCartTotal();

            if ("shopping_cart_item_count" in returnArray) {
                $(".shopping-cart-item-count").html(returnArray["shopping_cart_item_count"]);
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
            displayErrorMessage("Server Not Responding");
        },
        dataType: "text",
    });
}

function calculateMiniCartTotal() {
    var totalQuantity = 0;
    var totalAmount = 0;
    $("#mini_cart_items_wrapper")
        .find(".mini-cart-item-wrapper")
        .each(function () {
            var salePrice = parseFloat($(this).find(".mini-cart-item-price").html().replace(/,/g, "").replace("$", ""));
            let quantity = 1;
            if ($(this).find(".mini-cart-item-quantity-number").is("input")) {
                quantity = parseFloat($(this).find(".mini-cart-item-quantity-number").val().replace(/,/g, ""));
            } else {
                quantity = parseFloat($(this).find(".mini-cart-item-quantity-number").html().replace(/,/g, ""));
            }
            var thisTotal = salePrice * quantity;
            totalAmount += thisTotal;
            totalQuantity += quantity;
        });
    $("#_total_items_in_cart").html(RoundFixed(totalQuantity, 0));
    $("#_total_cost_for_cart").html("$" + RoundFixed(totalAmount, 2));
}

function getPostalCode(ignoreLocalStorage, successCallback) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const url = `https://maps.googleapis.com/maps/api/geocode/json?` + `latlng=${position.coords.latitude},${position.coords.longitude}&key=${API_KEY}`;
                $("body").addClass("no-waiting-for-ajax");

                $.ajax({
                    url: url,
                    type: "GET",
                    timeout: empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout,
                    success: function (returnText) {
                        let returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            returnArray = { error_message: "Invalid response" };
                        }
                        const postalCodeAddressComponent = returnArray.results[0].address_components.find((item) => item.types.includes("postal_code"));
                        let postalCode = postalCodeAddressComponent ? postalCodeAddressComponent.long_name : "";
                        currentLocationPostalCode = postalCode;

                        if ((empty(localStorage.getItem("postalCode")) || ignoreLocalStorage) && !empty(postalCode)) {
                            setPostalCode(postalCode);

                            if (successCallback) {
                                successCallback();
                            }
                        }
                    },
                    error: function (XMLHttpRequest, textStatus, errorThrown) {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        if (("adminLoggedIn" in window) && adminLoggedIn) {
                            displayErrorMessage("Server not responding. Please try again or contact support.");
                        }
                    },
                    dataType: "text",
                });
            },
            function (error) {
                if (error.code === error.PERMISSION_DENIED) {
                    $("#_ffl_use_current_location").remove();
                }
            }
        );
    }
}

function setPostalCode(postalCode) {
    $(".ffl-postal-code").val(postalCode);
    $(".editing-postal-code").hide();
    $(".viewing-dealers").show();
    $("#_ffl_availability_message").html(`${dealersSelectionMessage} (${postalCode})`);
}

function loadMapApi() {
    if (!window.mapApiLoadInitialized) {
        window.mapApiLoadInitialized = true;

        let loadMapApiData = {key: API_KEY};
        if (typeof getLoadMapApiData === "function") {
            loadMapApiData = getLoadMapApiData(loadMapApiData);
        }

        $.ajax({
            url: "https://maps.googleapis.com/maps/api/js",
            type: "GET",
            cache: true,
            data: loadMapApiData,
            timeout: empty(gDefaultAjaxTimeout) ? 30000 : gDefaultAjaxTimeout,
            dataType: "script",
            success: function (returnText) {
                const fflDealersMap = $("#_ffl_dealers_map");
                const wacondaLake = {
                    lat: 39.497737,
                    lng: -98.37452,
                };

                window.map = new google.maps.Map(fflDealersMap[0], {
                    zoom: 5,
                    center: wacondaLake,
                });

                if (typeof onLoadMapApiSuccess === "function") {
                    onLoadMapApiSuccess();
                }
            },
        });
    }
}

function addFragmentCloseHandler() {
    const modal = $("#_ffl_dealer_selection_wrapper");
    modal.click(function (event) {
        const element = $(event.target);
        if (!element.hasClass("close-btn")) {
            if (element.parents("#_ffl_dealer_selection").length) {
                return;
            }
        }
        modal.css("display", "none");
        $("body").removeClass("modal-open");
    });
}

function getPagedDealers() {
    const sliceStart = (dealersCurrentPage - 1) * dealersPageSize;
    const sliceEnd = sliceStart + dealersPageSize;
    return dealersCache.slice(sliceStart, sliceEnd);
}

function renderFflDealersMap() {
    window.markers = {};

    getPagedDealers().forEach((fflDealer, index) => {
        const isElite = fflDealer.subscription_code === "ELITE";
        const marker = new google.maps.Marker({
            position: {
                lat: Number(fflDealer.latitude),
                lng: Number(fflDealer.longitude),
            },
            icon: isElite ? "/getimage.php?code=ffl_map_pin" : undefined,
        });
        const id = fflDealer.federal_firearms_licensee_id;
        mapMarkers.set(id, marker);
        window.markers[id] = marker;
        marker.addListener("click", () => {
            var elem = $("#_ffl_dealers_list [data-ffl-dealer-id=" + id + "]"),
                container = $("#_ffl_dealers_list"),
                pos = elem.position().top + container.scrollTop() - container.position().top;
            container.animate(
                {
                    scrollTop: pos,
                },
                1000
            );
            elem.trigger("mouseenter");
            return false;
        });
    });

    new MarkerClusterer(window.map, Object.values(window.markers), {
        imagePath: "https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m",
    });
}

function renderFflDealersList() {
    const pagedDealers = getPagedDealers();
    const dealersElement = $("#_ffl_dealers_list");
    dealersElement.empty();

    $("<ul />").append(pagedDealers.map(renderFflDealer)).appendTo(dealersElement);

    $("#_ffl_dealers_list ul li").on({
        mouseenter: function () {
            const fflId = $(this).data("ffl-dealer-id");
            const marker = mapMarkers.get(fflId);
            // marker.setAnimation(google.maps.Animation.BOUNCE);
            window.map.setZoom(15);
            window.map.setCenter(marker.getPosition());

            const fflDealer = dealersCache.find((dealer) => dealer.federal_firearms_licensee_id === fflId);
            updateFflDealerInfoContents(fflDealer,true);

            $("#_ffl_dealer_selection .hover-ffl-dealer-info").removeClass("hidden");

            $("#_ffl_dealers_list ul li").removeClass("selected");
            $(this).addClass("selected");
        },
        mouseleave: function () {
            const marker = mapMarkers.get($(this).data("ffl-dealer-id"));
            marker.setAnimation(null);
        },
        click: function () {
            onDealerPopupSelectDealer();
        }
    });

    dealersElement.scrollTop(0);
}

function updateFflDealerInfoContents(fflDealer,hover = false) {
    const completeAddress = getFflDealerAddress(fflDealer);
    const fflDetails = $(".hover-ffl-dealer-info");
    const fflName = fflDetails.find(".ffl-dealer-business-name");
    const fflAddress = fflDetails.find(".ffl-dealer-address span");

    fflName.html(`${fflDealer.business_name}`);
    fflName.attr("title", fflDealer.business_name);

    fflAddress.html(completeAddress);
    fflAddress.attr("title", completeAddress);

    fflDetails.data("ffl-dealer-id", fflDealer.federal_firearms_licensee_id);
    if (!hover) {
        dealerName = fflDealer.business_name;
        dealerAddress = completeAddress;
        $('#_business_name_row p').text(dealerName);
        $('#_address_row p').html(dealerAddress);
        $('#_phone_number_row p a').html(fflDealer.phone_number)
            .attr("href", "tel:" + fflDealer.phone_number)
        $('#_email_row p a').html(fflDealer.email_address)
            .attr("href", "mailto:" + fflDealer.email_address);
    }
}

function afterSetDefaultLocation() {
    $("body").addClass("waiting-for-ajax");
    setTimeout(function () {
        location.reload();
    }, 500);
}

function afterLoadRelatedProducts() {
    updateDealerRelatedElementsVisibility();
}

function updateSelectedDealer(fflDealer) {
    const fflId = fflDealer.federal_firearms_licensee_id;

    // Update contact's default FFL dealer
    $.ajax({
        url: "/retail-store-controller?ajax=true&url_action=get_ffl_information",
        type: "POST",
        data: {federal_firearms_licensee_id: fflId},
        timeout: 30000
    });

    setSelectedFacetsAsCookies();

    if (!empty(fflDealer.locations)) {
        const fflLocation = fflDealer.locations[0];
        $("body").addClass("waiting-for-ajax");
        setDefaultLocation(fflLocation.location_code);
    } else {
        $("body").addClass("waiting-for-ajax");
        setTimeout(function () {
            location.reload();
        }, 500);
    }
}

function setSelectedFacetsAsCookies() {
    const selectedFacets = [];
    $(".sidebar-filter:not(.hidden) input:checked").each(function() {
        if (!$(this).parents("[data-field_name='location_id']").length) {
            selectedFacets.push($(this).attr("id"));
        }
    });
    $.cookie("selected_facets", selectedFacets.join(","), {expires: 1, path: "/"});
}

function getFflDealerAddress(fflDealer) {
    const address = [fflDealer.address_1, fflDealer.address_2].filter(Boolean).join(" ");
    const stateAndPostal = [fflDealer.state, fflDealer.postal_code].join(", ");
    return (!empty(address) ? address + "<br>" : "") + fflDealer.city + ", " + stateAndPostal;
}

function renderFflDealer(fflDealer) {
    const listingAddress = [fflDealer.city, fflDealer.state].filter(Boolean).join(", ");
    const isElite = fflDealer.subscription_code === "ELITE";
    const badge = isElite ? `<img src="/getimage.php?code=ffl_listing_badge" alt="Elite Dealer" />` : "";

    const pricingElement = !empty(fflDealer.product_price) ? `<p class="ffl-dealer-pricing"><i class="fa fa-tag"></i> $${RoundFixed(fflDealer.product_price, 2)}</p>` : "";

    let availabilityElement = "";
    if (!empty(dealerSearchProductId)) {
        if (!empty(fflDealer.locations) && !empty(fflDealer.locations[0].inventory_count)) {
            availabilityElement = `<p class="ffl-dealer-in-stock">In Stock at Store</p>`;
        } else if (!empty(distributorInventoryQuantity)) {
            availabilityElement = `<p class="ffl-dealer-pickup-available">Available for Order</p>`;
        } else {
            availabilityElement = `<p class="ffl-dealer-out-of-stock">Out of Stock</p>`;
        }
    }

    let pendingSetupElement = "";
    let isPendingSetup = fflDealer.approved && empty(fflDealer.merchant_account_id);
    if (isPendingSetup) {
        pendingSetupElement = `<div class="ffl-dealer-pending-setup"><i class="" aria-hidden="true"></i>Contact Dealer for Purchase</div>`;
    }

    return $("<li />")
        .attr("data-ffl-dealer-id", fflDealer.federal_firearms_licensee_id)
        .attr("class", isPendingSetup ? "pending-setup" : "")
        .append(`
                        <div class="ffl-dealer-details">
                                <span class="ffl-dealer-business-name">
                                    ${_.escape(fflDealer.business_name)}
                                    ${badge}
                                </span>
                                ${pricingElement}
                                <p class="ffl-dealer-address">
                                    <i class="fa fa-map-marker-alt"></i>
                                    ${_.escape(listingAddress)}
                                </p>
                                ${availabilityElement}
                                ${pendingSetupElement}
                  </div>
                `);
}

function openFflDealerModal(productId) {
    dealerSearchProductId = productId;

    const postalCode = $("#_ffl_filter_postal_code").val();
    if (!empty(postalCode)) {
        searchFflDealers();
    }

    $("#_ffl_dealer_selection_wrapper").css("display", "grid");
    $("body").addClass("modal-open");
    addFragmentCloseHandler();
}

function sortDealerLocations(locations) {
    var withMerchantAccount = [];
    var withoutMerchantAccount = [];

    for (var i = 0; i < locations.length; i++) {
        if (locations[i].merchant_account_id) {
            withMerchantAccount.push(locations[i]);
        } else {
            withoutMerchantAccount.push(locations[i]);
        }
    }

    withMerchantAccount.sort(function(a, b) {
        return a.distance - b.distance;
    });
    withoutMerchantAccount.sort(function(a, b) {
        return a.distance - b.distance;
    });

    return withMerchantAccount.concat(withoutMerchantAccount);
}

function searchFflDealers() {
    const postalCode = $("#_ffl_filter_postal_code").val();

    localStorage.setItem("postalCode", postalCode);

    if (empty(postalCode)) {
        displayErrorMessage("Postal code is required.");
        return;
    }
    let filterRadius = $("#_ffl_filter_radius").val();
    if (empty(filterRadius)) {
        filterRadius = 50;
    }
    const data = {
        radius: filterRadius,
        postal_code: $("#_ffl_filter_postal_code").val(),
        product_id: dealerSearchProductId,
        // sort_by_subscription: true,
        update_coordinates: true,
        has_location: true,
        has_active_subscription: true,
        have_price_structure_only: true,
        limit: 120,
        json_mode: true,
    };

    const url = "/retail-store-controller?ajax=true&url_action=get_ffl_dealers";
    loadAjaxRequest(url, data, (returnArray) => {
        let fflDealers = "ffl_dealers" in returnArray ? returnArray['ffl_dealers'] : [];

        fflDealers = sortDealerLocations(fflDealers);

        dealersCache = fflDealers.filter((fflDealer) => $.isNumeric(fflDealer.latitude) && $.isNumeric(fflDealer.longitude));
        distributorInventoryQuantity = returnArray['inventory_quantity_distributor'];

        $(".editing-postal-code").hide();
        $(".viewing-dealers").show();
        $("#_ffl_availability_message").html(`${dealersSelectionMessage} (${postalCode})`);

        const url = `https://maps.googleapis.com/maps/api/geocode/json?address=${postalCode}&key=${API_KEY}`;
        $("body").addClass("no-waiting-for-ajax");
        loadAjaxRequest(url, (returnArray) => {
            const addressComponents = returnArray.results[0].address_components;
            const locality = addressComponents.find((item) => item.types.includes("locality"));
            const state = addressComponents.find((item) => item.types.includes("administrative_area_level_1"));
            const stateLocality = locality ? `${locality.long_name}, ${state.short_name}` : state.long_name;
            $("#_ffl_availability_message").html(`${dealersSelectionMessage} ${stateLocality} (${postalCode})`);
        });

        renderFflDealersMap();
        renderFflDealersList();

        if (currentLocationPostalCode == postalCode) {
            $("#_ffl_use_current_location").hide();
        } else {
            $("#_ffl_use_current_location").show();
        }

        if (fflDealers.length === 0) {
            $("#_ffl_dealers_list").html("<p>No FFL Dealers found.</p>");
            displayErrorMessage("No FFL dealers found");
        } else {
            $("#_ffl_dealers_list ul li:first-child").trigger("mouseenter");
        }
    })
}

function setupSelectedDealerTooltip() {
    if (!empty(dealerPopperInstance)) {
        dealerPopperInstance.destroy();
    }

    if (dealerSelected) {
        const trigger = document.querySelector("#_selected_ffl");
        const tooltip = document.querySelector("#_selected_ffl_tooltip_wrapper");
        dealerPopperInstance = Popper.createPopper(trigger, tooltip);

        $("#_selected_ffl_container").hover(
            function () {
                $("#_selected_ffl_tooltip_wrapper").addClass("hover").attr("data-show", true);
                dealerPopperInstance.update();
            },
            function () {
                if ($("#_selected_ffl_tooltip_wrapper").hasClass("hover")) {
                    $("#_selected_ffl_tooltip_wrapper").removeAttr("data-show");
                }
            }
        );
    }

    if (!dealerTooltipInitialized) {
        $(window).scroll(function () {
            $("#_selected_ffl_tooltip_wrapper").removeClass("hover").removeAttr("data-show");
        });

        dealerTooltipInitialized = true;

        $("#_selected_ffl_tooltip_wrapper #_select_different_store").click(function () {
            let productId = $("#product_id").val();
            openFflDealerModal(productId);
        });

        $("#_selected_ffl_tooltip_wrapper #_clear_selected_store").click(function () {
            clearSelectedLocation();
        });

        $("#_selected_ffl").click(function () {
            let productId = $("#product_id").val();
            openFflDealerModal(productId);
        });
    }
}

function setupDealerSelection() {
    $("#_ffl_product_details").addClass("hidden");

    $(".editing-postal-code").show();
    $(".viewing-dealers").hide();

    if (!empty(localStorage.getItem("postalCode"))) {
        setPostalCode(localStorage.getItem("postalCode"));
    }

    getPostalCode();
    loadMapApi();

    $(document).on("click", "#_ffl_filter_btn", function () {
        searchFflDealers();
    });

    $(document).on("keypress", "#_ffl_filter_postal_code", function (e) {
        if (e.keyCode === 13) {
            searchFflDealers();
        }
    });

    $("#_ffl_filter_change_location").click(function () {
        $(".editing-postal-code").show();
        $(".viewing-dealers").hide();
    });

    $("#_ffl_use_current_location").click(function () {
        getPostalCode(true, searchFflDealers);
    });

    $("#_ffl_filter_cancel").click(function () {
        $(".editing-postal-code").hide();
        $(".viewing-dealers").show();
    });

    $(".ffl-close-dealer-info").click(function () {
        $(this).parents(".ffl-dealer-info").addClass("hidden");
    });

    $("#_ffl_select_location").click(function () {
        onDealerPopupSelectDealer();
    });

    $("#_retailer_search_menu_item").click(function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        openFflDealerModal();
    });

    if (dealerSelected) {
        $("#_selected_ffl p").html(dealerName);
        $("#_selected_ffl_tooltip .ffl-dealer-business-name").html(dealerName);
        $("#_selected_ffl_tooltip .ffl-dealer-address").html(dealerAddress);
    }
    setupSelectedDealerTooltip();
}

function onDealerPopupSelectDealer() {
    const fflId = $("#_ffl_dealers .hover-ffl-dealer-info").data("ffl-dealer-id");
    const fflDealer = dealersCache.find((dealer) => dealer.federal_firearms_licensee_id === fflId);
    updateSelectedDealer(fflDealer);

    if (dealerSelected) {
        let locationTemplate = $('#_location_filter_template').html();
        locationTemplate = locationTemplate.replace("%location%", dealerName);
        $('#_selected_ffl > p').html(dealerName);
        $("#selected_filters > .selected-filter[data-id*='location']").html(locationTemplate);
    }
    $("#_ffl_dealer_selection_wrapper").css("display", "none");
}

function setupEZLocator() {
    getPostalCode();
    getRecognizedDealers();
    getManufacturers();
    getDepartments();

    if (typeof customSetupEZLocator === "function") {
        customSetupEZLocator();
    } else {
        ezLocatorNav = new hcOffcanvasNav("#_ez_search_finder", {
            customToggle: "#_selected_ffl_locator",
            disableBody: true,
            width: 600,
            height: "100%",
            swipeGestures: true,
            levelOpen: "overlap",
            position: "right",
            insertClose: false,
            navClass: "ez-locator",
        });

        // Styling issue
        $(".ez-ffl-buttons").unwrap();
    }
}

function ezSearchFflDealers() {
    const postalCode = $(".ez-ffl-postal-code").val();
    if (empty(postalCode)) {
        displayErrorMessage("Postal code is required.");
        return;
    }

    if (typeof customEZSearchFflDealers === "function") {
        customEZSearchFflDealers();
    } else {
        const radius = $(".ez-ffl-radius").val();
        const searchText = $(".ez-ffl-dealer-filter").val();
        const postalCode = $(".ez-ffl-postal-code").val();
        const searchHours = $(".ez-search-hours").val();

        const selectedRecognizedDealers = $(".ez-product-dealer:checked")
            .map(function () {
                return this.value;
            })
            .get()
            .join(",");
        const selectedBrands = $(".ez-product-manufacturer:checked")
            .map(function () {
                return this.value;
            })
            .get()
            .join(",");
        const selectedDepartments = $(".ez-search-department:checked")
            .map(function () {
                return this.value;
            })
            .get()
            .join(",");
        const hasGunRange = $(".ez-product-search-wrapper-range-only").is(":checked") ? 1 : 0;

        const data = {
            radius,
            searchText,
            postalCode,
            searchHours,
            selectedRecognizedDealers,
            selectedBrands,
            selectedDepartments,
            hasGunRange
        };

        localStorage.setItem("ezLocatorSearchData", JSON.stringify(data));
        window.location.href = "/ez-search";
    }
}

function getRecognizedDealers() {
    const url = scriptFilename + "?ajax=true&url_action=get_affiliated_brands&product_manufacturer_tag_code=MANUFACTURER_RECOGNIZED_DEALER";
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest(url, (returnArray) => {
        recognizedDealersLoaded = true;
        if (returnArray.product_manufacturers) {
            returnArray.product_manufacturers.forEach((manufacturer) => {
                const dealerId = `dealer_${manufacturer.product_manufacturer_id}`;
                $(".ez-product-dealer-description-container").append(`
                    <div class="description">
                        <label class="checkbox-label" title="${manufacturer.description}">
                            <input type="checkbox" class="ez-product-dealer" name="${manufacturer.description}" data-id="${dealerId}"
                                value="${manufacturer.product_manufacturer_code}">${manufacturer.description}</label>
                    </div>
                `);
            });
        }
    });
}

function getManufacturers() {
    const url = scriptFilename + "?ajax=true&url_action=get_affiliated_brands&product_manufacturer_tag_code=AFFILIATED";
    loadAjaxRequest(url,(returnArray)=>{
        brandsLoaded = true;
        if (returnArray.product_manufacturers) {
            returnArray.product_manufacturers.forEach((manufacturer) => {
                const manufacturerId = `manufacturer_${manufacturer.product_manufacturer_id}`;
                $(".ez-product-manufacturer-description-container").append(`
                    <div class="description">
                        <label class="checkbox-label" title="${manufacturer.description}">
                            <input type="checkbox" class="ez-product-manufacturer" name="${manufacturer.description}" data-id="${manufacturerId}"
                                value="${manufacturer.product_manufacturer_code}">${manufacturer.description}</label>
                    </div>
                `);
            });
        }
    });
}

function getDepartments() {
    const url = scriptFilename + "?ajax=true&url_action=get_product_departments";
    loadAjaxRequest(url, (returnArray) => {
        departmentsLoaded = true;
        if (returnArray.product_departments) {
            returnArray.product_departments.forEach((department) => {
                const departmentId = `department_${department.product_department_id}`;
                $(".ez-product-description-container").append(`
                    <div class="description">
                        <label class="checkbox-label" title="${department.description}">
                            <input type="checkbox" class="ez-search-department" name="${department.description}" data-id="${departmentId}"
                                value="${department.product_department_code}">${department.description}</label>
                    </div>
                `);
            });
        }
    });
}

function closeSearch() {
    ezLocatorNav.close();
}

function clearSearchFields() {
    $(".ez-ffl-postal-code").val("");
    $(".ez-ffl-dealer-filter").val("");
    $(".ez-listing-ffl-dealer-filter").val("");

    $(".ez-ffl-radius").val($(".ez-ffl-radius option:first").val());
    $(".ez-listing-ffl-radius").val($(".ez-listing-ffl-radius option:first").val());

    $(".ez-search-hours").val($(".ez-search-hours option:first").val());
    $(".ez-listing-search-hours").val($(".ez-listing-search-hours option:first").val());

    $(".ez-product_manufacturer").prop("checked", false);
    $(".ez-search_department").prop("checked", false);
    $(".ez-product-search-wrapper-range-only").prop("checked", false);
}

function setupBannerSponsorPopups() {
    $(document).on("click", ".banner a", function () {
        const linkURL = $(this).attr("href");
        if (linkURL.includes("/sponsor/")) {
            const sponsorId = linkURL.substr("/sponsor/".length);
            displaySponsorPopup(sponsorId);
            event.preventDefault();
        }
    });

    $("#_sponsor_preview_dialog_wrapper").click(function (event) {
        const element = $(event.target);
        if (!element.hasClass("close-btn")) {
            if (element.closest("#_sponsor_preview_dialog_content").length) {
                return;
            }
        }
        $("#_sponsor_preview_dialog_wrapper").css("display", "none");
        $("body").removeClass("modal-open");
    });
}

function displaySponsorPopup(sponsorId) {
    $.ajax({
        url: scriptFilename + `?ajax=true&url_action=get_sponsor_data&subscription_code=featured&sponsor_id=${sponsorId}`,
        type: "GET",
        timeout: "30000",
        success: function (returnText) {
            const returnArray = processReturn(returnText);
            if (returnArray === false) {
                return;
            }
            if ("sponsor_page" in returnArray) {
                $("#_sponsor_preview_dialog_content").html(returnArray["sponsor_page"]);
                $("#_sponsor_preview_dialog_wrapper").css("display", "grid");
                $("body").addClass("modal-open");
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
            displayErrorMessage("Error getting sponsor data.");
        },
        dataType: "text",
    });
}

function equalizeTrueElementHeights($elementsToEqualize) {
    let blockHeight = 0;
    $elementsToEqualize.each(function () {
        if ($(this).outerHeight(true) > blockHeight) {
            blockHeight = $(this).outerHeight(true);
        }
    });
    if (blockHeight > 0) {
        $elementsToEqualize.css("height", blockHeight + "px");
    } else {
        $elementsToEqualize.css("height", "49px");
    }
}